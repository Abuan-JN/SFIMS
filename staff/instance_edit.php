<?php
/**
 * Physical Instance Editor
 * 
 * Allows administrative staff to manually update details for a specific asset unit.
 * 1. Updates metadata like Serial Number.
 * 2. Manually overrides status (e.g., from 'issued' to 'under repair').
 * 3. Reassigns the asset to a different department, room, or person without a full transaction flow.
 * 4. Logs the manual update for security tracking.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0); // Target Physical Instance ID

// Validation: Ensure the instance exists before allowing edits
if (!$id) {
    set_flash_message('danger', 'Invalid instance ID.');
    redirect('../items.php');
}

$error = '';
$success = '';

// Fetch the current state of the physical unit and its master item details
$stmt = $db->prepare("SELECT ii.*, i.name as item_name, b.barcode_value 
                      FROM item_instances ii 
                      JOIN items i ON ii.item_id = i.id 
                      JOIN barcodes b ON ii.barcode_id = b.id 
                      WHERE ii.id = ?");
$stmt->execute([$id]);
$instance = $stmt->fetch();

if (!$instance) {
    set_flash_message('danger', 'Instance not found.');
    redirect('../items.php');
}

// Process the manual update form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $serial = trim($_POST['serial_number'] ?? '');
    $status = $_POST['status'] ?? 'in-stock';
    $dept_id = (int) ($_POST['department_id'] ?? 0) ?: null; // Use NULL if empty
    $room_id = (int) ($_POST['room_id'] ?? 0) ?: null;     // Use NULL if empty
    $person = trim($_POST['assigned_person'] ?? '');

    try {
        // Direct SQL update on the physical instance metadata
        $stmt = $db->prepare("UPDATE item_instances SET serial_number = ?, status = ?, assigned_department_id = ?, room_id = ?, assigned_person = ? WHERE id = ?");
        if ($stmt->execute([$serial, $status, $dept_id, $room_id, $person, $id])) {
            
            // Record this manual override in the system audit log
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'INSTANCE_UPDATE', 'ItemInstance', ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], $id, "Updated instance details for " . $instance['barcode_value']]);

            set_flash_message('success', 'Instance updated successfully.');
            redirect('../item_details.php?id=' . $instance['item_id']);
        } else {
            $error = "Failed to update instance.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$rooms = $db->query("SELECT r.*, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();

$page_title = 'Edit Instance - ' . $instance['barcode_value'];
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Edit Asset Instance: <?php echo h($instance['barcode_value']); ?></h4>
                <p class="text-muted small mb-0">Item: <?php echo h($instance['item_name']); ?></p>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="serial_number" class="form-label fw-semibold">Serial Number</label>
                            <input type="text" name="serial_number" id="serial_number" class="form-control"
                                value="<?php echo h($instance['serial_number']); ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="status" class="form-label fw-semibold">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <?php
                                $statuses = ['in-stock', 'issued', 'under repair', 'disposed', 'lost', 'condemned-serviced', 'condemned-trash'];
                                foreach ($statuses as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo $instance['status'] === $s ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('-', ' ', $s)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="department_id" class="form-label fw-semibold">Assigned Department</label>
                            <select name="department_id" id="department_id" class="form-select">
                                <option value="">-- None / Warehouse --</option>
                                <?php foreach ($departments as $dept): ?>
                                    <option value="<?php echo $dept['id']; ?>" <?php echo $instance['assigned_department_id'] == $dept['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($dept['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="room_id" class="form-label fw-semibold">Location / Room</label>
                            <select name="room_id" id="room_id" class="form-select">
                                <option value="">-- No Room --</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?php echo $room['id']; ?>" <?php echo $instance['room_id'] == $room['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($room['building_name'] . ' - ' . $room['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label for="assigned_person" class="form-label fw-semibold">Assigned Person</label>
                        <input type="text" name="assigned_person" id="assigned_person" class="form-control"
                            value="<?php echo h($instance['assigned_person']); ?>" placeholder="e.g. Juan Dela Cruz">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="../item_details.php?id=<?php echo $instance['item_id']; ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
