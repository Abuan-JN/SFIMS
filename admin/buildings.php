<?php
/**
 * Facilities Infrastructure Management
 * 
 * Manages the high-level physical locations (buildings) and specific rooms.
 * 1. Creates new building entries and rooms.
 * 2. Deletes buildings and rooms (Blocked by DB foreign keys if tied to assets).
 * 3. Provides foundational data for room-level assignments.
 */

require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Process New Building Addition
if ($_SESSION['role'] === 'Admin' && isset($_POST['add_building'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $name = trim($_POST['name']);
    if ($name) {
        try {
            $stmt = $db->prepare("INSERT INTO buildings (name) VALUES (?)");
            $stmt->execute([$name]);
            set_flash_message('success', 'Building added successfully.');
            redirect('admin/buildings.php');
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Process Building Removal
if ($_SESSION['role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_building'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['delete_building'];
    try {
        $stmt = $db->prepare("DELETE FROM buildings WHERE id = ?");
        $stmt->execute([$id]);
        set_flash_message('success', 'Building deleted successfully.');
        redirect('admin/buildings.php');
    } catch (PDOException $e) {
        $error = "Cannot delete building. It might have rooms assigned to it.";
    }
}

// Process New Room Addition
if ($_SESSION['role'] === 'Admin' && isset($_POST['add_room'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $name = trim($_POST['name']);
    $building_id = (int)$_POST['building_id'];
    $floor = trim($_POST['floor']);
    if ($name && $building_id) {
        try {
            $stmt = $db->prepare("INSERT INTO rooms (building_id, name, floor) VALUES (?, ?, ?)");
            $stmt->execute([$building_id, $name, $floor]);
            set_flash_message('success', 'Room added successfully.');
            redirect('admin/buildings.php');
        } catch (PDOException $e) {
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Process Room Removal
if ($_SESSION['role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_room'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $id = (int)$_POST['delete_room'];
    try {
        $stmt = $db->prepare("DELETE FROM rooms WHERE id = ?");
        $stmt->execute([$id]);
        set_flash_message('success', 'Room deleted successfully.');
        redirect('admin/buildings.php');
    } catch (PDOException $e) {
        $error = "Cannot delete room. It might have assets assigned to it.";
    }
}

$buildings = $db->query("SELECT * FROM buildings ORDER BY name ASC")->fetchAll();
$rooms_raw = $db->query("SELECT * FROM rooms ORDER BY name ASC")->fetchAll();
$rooms_by_building = [];
foreach ($rooms_raw as $r) {
    if (!isset($rooms_by_building[$r['building_id']])) {
        $rooms_by_building[$r['building_id']] = [];
    }
    $rooms_by_building[$r['building_id']][] = $r;
}

$page_title = 'Facilities Management';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Facilities Management</h2>
        <p class="text-muted small">Manage buildings and their associated rooms.</p>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($_SESSION['role'] === 'Admin'): ?>
            <div class="dropdown d-inline-block me-2">
                <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bulk Import
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="import_master.php?type=buildings">Import Buildings</a></li>
                    <li><a class="dropdown-item" href="import_master.php?type=rooms">Import Rooms</a></li>
                </ul>
            </div>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBuildingModal">
                <i class="bi bi-plus-lg me-1"></i> Add Building
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
<?php endif; ?>

<?php if ($buildings): ?>
    <div class="accordion shadow-sm" id="facilitiesAccordion">
        <?php foreach ($buildings as $index => $b): ?>
            <?php 
                $b_id = $b['id'];
                $headingId = 'heading' . $b_id;
                $collapseId = 'collapse' . $b_id;
                $b_rooms = isset($rooms_by_building[$b_id]) ? $rooms_by_building[$b_id] : [];
            ?>
            <div class="accordion-item border-0 mb-2 rounded overflow-hidden" style="background-color: var(--sfims-card-bg);">
                <h2 class="accordion-header" id="<?php echo $headingId; ?>">
                    <button class="accordion-button collapsed fw-bold py-3 px-4 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#<?php echo $collapseId; ?>" aria-expanded="false" aria-controls="<?php echo $collapseId; ?>" style="background-color: rgba(0,0,0,0.02);">
                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                            <span class="fs-5 text-primary"><i class="bi bi-building me-2"></i><?php echo h($b['name']); ?></span>
                            <div class="d-flex align-items-center gap-3">
                                <span class="badge bg-secondary"><?php echo count($b_rooms); ?> Rooms</span>
                            </div>
                        </div>
                    </button>
                </h2>
                <div id="<?php echo $collapseId; ?>" class="accordion-collapse collapse" aria-labelledby="<?php echo $headingId; ?>" data-bs-parent="#facilitiesAccordion">
                    <div class="accordion-body p-0 border-top">
                        <div class="d-flex justify-content-between align-items-center bg-light px-4 py-2 border-bottom">
                            <span class="small fw-semibold text-muted">ROOMS IN <?php echo strtoupper(h($b['name'])); ?></span>
                            <div class="d-flex gap-2">
                                <?php if ($_SESSION['role'] === 'Admin'): ?>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#addRoomModal" data-building-id="<?php echo $b_id; ?>" data-building-name="<?php echo h($b['name']); ?>">
                                        <i class="bi bi-plus-circle me-1"></i> Add Room
                                    </button>
                                    <form method="POST" action="" class="d-inline m-0 p-0" onsubmit="return confirm('Delete this building? All associated rooms must be deleted first or reassigned.');">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete_building" value="<?php echo $b_id; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i> Delete Building
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($b_rooms): ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0 border-0">
                                    <thead class="bg-light opacity-75">
                                        <tr>
                                            <th class="ps-4" style="width: 5%;">ID</th>
                                            <th style="width: 60%;">Room Name</th>
                                            <th style="width: 20%;">Floor Location</th>
                                            <?php if ($_SESSION['role'] === 'Admin'): ?>
                                            <th class="text-end pe-4" style="width: 15%;">Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($b_rooms as $r): ?>
                                            <tr>
                                                <td class="ps-4 text-muted small"><?php echo $r['id']; ?></td>
                                                <td class="fw-semibold"><?php echo h($r['name']); ?></td>
                                                <td><?php echo h($r['floor'] ?: '--'); ?></td>
                                                <?php if ($_SESSION['role'] === 'Admin'): ?>
                                                <td class="text-end pe-4">
                                                    <form method="POST" action="" class="d-inline">
                                                        <?php csrf_field(); ?>
                                                        <input type="hidden" name="delete_room" value="<?php echo $r['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this room?')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-info-circle fs-4 d-block mb-2"></i>
                                No rooms recorded for this building.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <div class="card shadow-sm border-0">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-building fs-1 d-block mb-3"></i>
            <h5 class="fw-bold">No Buildings Found</h5>
            <p class="mb-0">Start by adding a building to manage your facilities.</p>
        </div>
    </div>
<?php endif; ?>

<?php if ($_SESSION['role'] === 'Admin'): ?>
<!-- Add Building Modal -->
<div class="modal fade" id="addBuildingModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title text-white">Add New Building</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Building Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Main Building, Annex A">
                </div>
            </div>
            <div class="modal-footer border-top-0 border-top-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_building" class="btn btn-primary">Save Building</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Room Modal -->
<div class="modal fade" id="addRoomModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <input type="hidden" name="building_id" id="roomBuildingId">
            <div class="modal-header border-bottom-0">
                <h5 class="modal-title text-white">Add Room to <span id="roomBuildingName" class="text-accent fw-bold text-white"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Room Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Room 101, Computer Lab 1" autofocus>
                </div>
                <div class="mb-3">
                    <label class="form-label">Floor Location (Optional)</label>
                    <input type="text" name="floor" class="form-control" placeholder="e.g., 1st Floor, 2nd Floor">
                </div>
            </div>
            <div class="modal-footer border-top-0">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_room" class="btn btn-primary">Save Room</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var addRoomModal = document.getElementById('addRoomModal');
    if(addRoomModal) {
        addRoomModal.addEventListener('show.bs.modal', function(event) {
            var button = event.relatedTarget;
            var buildingId = button.getAttribute('data-building-id');
            var buildingName = button.getAttribute('data-building-name');
            
            document.getElementById('roomBuildingId').value = buildingId;
            document.getElementById('roomBuildingName').textContent = buildingName;
        });
    }
});
</script>
<?php endif; ?>

<?php require_once '../partials/footer.php'; ?>
