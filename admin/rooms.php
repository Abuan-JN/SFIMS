<?php
/**
 * Room Asset Destination Management
 * 
 * Manages specific physical locations (rooms/labs) within buildings.
 * 1. Links rooms to parent buildings via foreign key.
 * 2. Tracks floor location for easier physical asset location.
 * 3. Provides precise destination targets for asset 'MOVE' operations.
 * 4. Aggregates building names for a human-readable room list.
 */

require_once '../config/database.php';
require_once '../config/app.php';

require_role('Admin');

$db = Database::getInstance();
$error = '';
$success = '';

// Process New Room Addition
if (isset($_POST['add_room'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $name = trim($_POST['name']);
    $building_id = (int)$_POST['building_id'];
    $floor = trim($_POST['floor']);
    if ($name && $building_id) {
        try {
            // Relate the room to a physical building
            $stmt = $db->prepare("INSERT INTO rooms (building_id, name, floor) VALUES (?, ?, ?)");
            $stmt->execute([$building_id, $name, $floor]);
            set_flash_message('success', 'Room added successfully.');
            redirect('admin/rooms.php');
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Process Room Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id = (int)$_POST['delete'];
    try {
        // Note: Assets assigned to this room will prevent deletion via DB constraints.
        $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        set_flash_message('success', 'Room deleted successfully.');
        redirect('admin/rooms.php');
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$rooms = $db->query("SELECT r.*, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();
$buildings = $db->query("SELECT * FROM buildings ORDER BY name ASC")->fetchAll();

$page_title = 'Manage Rooms';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Manage Rooms</h2>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoomModal">
            <i class="bi bi-plus-lg me-1"></i> Add Room
        </button>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Building</th>
                        <th>Room Name</th>
                        <th>Floor</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rooms): ?>
                        <?php foreach ($rooms as $r): ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-light text-dark border"><?php echo h($r['building_name']); ?></span>
                                </td>
                                <td class="fw-semibold"><?php echo h($r['name']); ?></td>
                                <td><?php echo h($r['floor'] ?: '--'); ?></td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete" value="<?php echo $r['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this room?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No rooms found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title">Add New Room</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Building</label>
                    <select name="building_id" class="form-select" required>
                        <option value="">Select Building</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?php echo $b['id']; ?>"><?php echo h($b['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Room Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Room 101, Computer Lab 1">
                </div>
                <div class="mb-3">
                    <label class="form-label">Floor Location</label>
                    <input type="text" name="floor" class="form-control" placeholder="e.g., 1st Floor, 2nd Floor">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_room" class="btn btn-primary">Save Room</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
