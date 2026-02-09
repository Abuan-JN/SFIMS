<?php
// staff/condemn.php
require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0); // Instance ID

if (!$id) redirect('inventory/items.php');

// Fetch instance info
$stmt = $db->prepare("SELECT ii.*, i.name as item_name, b.barcode_value FROM item_instances ii JOIN items i ON ii.item_id = i.id JOIN barcodes b ON ii.barcode_id = b.id WHERE ii.id = ?");
$stmt->execute([$id]);
$instance = $stmt->fetch();

if (!$instance) redirect('inventory/items.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status = $_POST['status']; // condemned-serviced or condemned-trash
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $db->beginTransaction();

        // Update instance status
        $stmt = $db->prepare("UPDATE item_instances SET status = ?, assigned_department_id = NULL, room_id = NULL, assigned_person = NULL WHERE id = ?");
        $stmt->execute([$status, $id]);

        // Record transaction
        $stmt = $db->prepare("INSERT INTO transactions (item_id, instance_id, type, quantity, date, remarks, performed_by) VALUES (?, ?, 'CONDEMN', 1, ?, ?, ?)");
        $stmt->execute([$instance['item_id'], $id, date('Y-m-d'), "Status: $status. $remarks", $_SESSION['user_id']]);

        $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity - 1 WHERE id = ?");
        $stmt->execute([$instance['item_id']]);

        // Audit Log
        $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'CONDEMN', 'Asset', ?, ?)");
        $logStmt->execute([$_SESSION['user_id'], $id, "Condemned asset (BC: " . $instance['barcode_value'] . ") with status: $status"]);

        $db->commit();
        set_flash_message('success', 'Asset condemned successfully.');
        redirect('item_details.php?id=' . $instance['item_id']);
    } catch (Exception $e) {
        $db->rollBack();
        die("Error: " . $e->getMessage());
    }
}
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
                        <a href="item_details.php?id=<?php echo $instance['item_id']; ?>" class="btn btn-light border">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
