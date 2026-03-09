<?php
/**
 * Asset Condemnation Module
 * 
 * Handles the removal of assets from active inventory due to damage or disposal.
 * 1. Updates the item_instance status (condemned-serviced or condemned-trash).
 * 2. Clears assignments (Dept, Person, Room).
 * 3. Records a 'CONDEMN' transaction.
 * 4. Decrements global 'current_quantity' for the item type.
 * 5. Logs the action for the administrative audit trail.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0); // Targeted Physical Instance ID

if (!$id) redirect('inventory/items.php');

// Retrieve metadata for the specific asset unit
$stmt = $db->prepare("SELECT ii.*, i.name as item_name, b.barcode_value, d.name as dept_name, r.name as room_name 
                      FROM item_instances ii 
                      JOIN items i ON ii.item_id = i.id 
                      JOIN barcodes b ON ii.barcode_id = b.id 
                      LEFT JOIN departments d ON ii.assigned_department_id = d.id 
                      LEFT JOIN rooms r ON ii.room_id = r.id 
                      WHERE ii.id = ?");
$stmt->execute([$id]);
$instance = $stmt->fetch();

if (!$instance) redirect('inventory/items.php');

// Handle the condemnation request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status']; // e.g., 'condemned-serviced' (Repairable) or 'condemned-trash' (Disposed)
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $db->beginTransaction();

        // Step 1: Update the unit's status and remove active assignments
        $stmt = $db->prepare("UPDATE item_instances SET status = ?, assigned_department_id = NULL, room_id = NULL, assigned_person = NULL, contact_number = NULL WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Construct detailed remarks including previous location if any
        $prev_location = 'Warehouse';
        if ($instance['dept_name']) {
            $prev_location = $instance['dept_name'];
            if ($instance['room_name']) $prev_location .= ' - ' . $instance['room_name'];
        }
        $full_remarks = "Status: $status. Prev Loc: $prev_location. $remarks";

        // Step 2: Create a system transaction for reporting and history
        $stmt = $db->prepare("INSERT INTO transactions (item_id, instance_id, type, quantity, date, remarks, performed_by) VALUES (?, ?, 'CONDEMN', 1, ?, ?, ?)");
        $stmt->execute([$instance['item_id'], $id, date('Y-m-d'), $full_remarks, $_SESSION['user_id']]);

        // Step 3: Decrement the master count ONLY if the item was 'in-stock'. 
        // If it was 'issued', it has already been deducted from current_quantity during the disburse transaction.
        if ($instance['status'] === 'in-stock') {
            $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity - 1 WHERE id = ?");
            $stmt->execute([$instance['item_id']]);
        }

        // Step 4: System Audit Log entry
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'CONDEMN', 'Asset', ?, ?)");
        $logStmt->execute([$_SESSION['user_id'], $id, "Condemned asset (BC: " . $instance['barcode_value'] . ") with status: $status"]);

// Finalize all database operations
        $db->commit();
        set_flash_message('success', 'Asset condemned successfully.');
        redirect('inventory/item_details.php?id=' . $instance['item_id']);
    } catch (Exception $e) {
        // Safe revert if any DB step fails
        $db->rollBack();
        die("Error: " . $e->getMessage());
    }
}

$page_title = 'Condemn Asset';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm mt-5">
            <div class="card-header bg-danger text-white py-3">
                <h4 class="mb-0">Condemn Asset: <?php echo h($instance['barcode_value']); ?></h4>
            </div>
            <div class="card-body p-4">
                <p>Asset: <strong><?php echo h($instance['item_name']); ?></strong></p>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Condemnation Category</label>
                        <select name="status" class="form-select" required>
                            <option value="condemned-serviced">For Servicing (Repairable)</option>
                            <option value="condemned-trash">Trash (Non-Repairable)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Reason / Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3" placeholder="Describe why this asset is being condemned..."></textarea>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-danger">Confirm Condemnation</button>
                        <a href="../inventory/item_details.php?id=<?php echo $instance['item_id']; ?>" class="btn btn-light border">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
