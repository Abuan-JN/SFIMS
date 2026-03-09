<?php
/**
 * Asset Movement Module
 * 
 * Specifically for relocated Fixed Assets that have already been issued.
 * 1. Updates the department and room assignment for a physical unit.
 * 2. Records a 'MOVE' transaction for audit purposes.
 * 3. Keeps historical context of where the asset moved from.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$id = (int)($_GET['id'] ?? 0); // Targeted Physical Instance ID

// Basic validation: Instance ID must be present
if (!$id) redirect('inventory/items.php');

// Fetch current location info to provide context for the move
$stmt = $db->prepare("SELECT ii.*, i.name as item_name, d.name as current_dept, r.name as current_room, b.barcode_value
                      FROM item_instances ii 
                      JOIN items i ON ii.item_id = i.id 
                      JOIN barcodes b ON ii.barcode_id = b.id
                      LEFT JOIN departments d ON ii.assigned_department_id = d.id
                      LEFT JOIN rooms r ON ii.room_id = r.id
                      WHERE ii.id = ?");
$stmt->execute([$id]);
$instance = $stmt->fetch();

// Safety: Only issued assets should be 'moved'. In-stock assets use 'disburse' instead.
if (!$instance || $instance['status'] !== 'issued') {
    set_flash_message('danger', 'Only issued assets can be moved.');
    redirect('inventory/item_details.php?id=' . ($instance['item_id'] ?? ''));
}

// Handle the movement request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_dept_id = (int)$_POST['department_id'];
    $new_room_id = (int)$_POST['room_id'];
    $remarks = trim($_POST['remarks'] ?? '');

    try {
        $db->beginTransaction();

        // Update the physical instance with its new location data
        $stmt = $db->prepare("UPDATE item_instances SET assigned_department_id = ?, room_id = ? WHERE id = ?");
        $stmt->execute([$new_dept_id, $new_room_id, $id]);

        // Record a transaction of type 'MOVE'
        $stmt = $db->prepare("INSERT INTO transactions (item_id, instance_id, type, quantity, date, department_id, room_id, remarks, performed_by) VALUES (?, ?, 'MOVE', 1, ?, ?, ?, ?, ?)");
        $stmt->execute([$instance['item_id'], $id, date('Y-m-d'), $new_dept_id, $new_room_id, "Moved from " . ($instance['current_dept'] ?: 'Unknown') . ". " . $remarks, $_SESSION['user_id']]);
        $tx_id = $db->lastInsertId();

        $db->commit();
        set_flash_message('success', 'Asset moved successfully.');

        // Allow the user to print a new disbursement/acknowledgement form post-move in a New Tab
        // We use JavaScript to open the new tab, then redirect the main window back to the item details
        echo "<script>
            window.open('disburse_print.php?id=$tx_id', '_blank');
            window.location.href = '../inventory/item_details.php?id={$instance['item_id']}';
        </script>";
        exit();
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Update failed: " . $e->getMessage();
    }
}

$departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$all_rooms = $db->query("SELECT r.*, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();

$page_title = 'Move Asset Location';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Move Asset Location: <?php echo h($instance['barcode_value']); ?></h4>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info py-2">
                    <small>Currently at: <strong><?php echo h($instance['current_dept'] ?: 'No Dept'); ?></strong> - <?php echo h($instance['current_room'] ?: 'No Room'); ?></small>
                </div>

                <form method="POST">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">New Department</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>" <?php echo $instance['assigned_department_id'] == $d['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($d['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">New Room Location</label>
                            <select name="room_id" class="form-select" required>
                                <option value="">Select Room</option>
                                <?php foreach ($all_rooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>" <?php echo $instance['room_id'] == $r['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($r['building_name']); ?> - <?php echo h($r['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Reason for Move / Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <a href="../inventory/item_details.php?id=<?php echo $instance['item_id']; ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Confirm Move</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
