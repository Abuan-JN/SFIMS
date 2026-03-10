<?php
/**
 * Building Infrastructure Management
 * 
 * Manages the high-level physical locations within the institution.
 * 1. Creates new building entries.
 * 2. Deletes buildings (Blocked by DB foreign keys if rooms exist).
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
    // Validate CSRF token
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
if ($_SESSION['role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id = (int)$_POST['delete'];
    try {
        // Note: Referential Integrity is enforced by the database.
        // If rooms are assigned to this building, the DELETE will throw a 1451 exception.
        $stmt = $db->prepare("DELETE FROM buildings WHERE id = ?");
        $stmt->execute([$id]);
        set_flash_message('success', 'Building deleted successfully.');
        redirect('admin/buildings.php');
    } catch (PDOException $e) {
        $error = "Cannot delete building. It might have rooms assigned to it.";
    }
}

$buildings = $db->query("SELECT * FROM buildings ORDER BY name ASC")->fetchAll();
$page_title = 'Manage Buildings';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Manage Buildings</h2>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($_SESSION['role'] === 'Admin'): ?>
            <a href="import_master.php?type=buildings" class="btn btn-outline-primary me-2">
                <i class="bi bi-file-earmark-spreadsheet me-1"></i> Bulk Import
            </a>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addBuildingModal">
                <i class="bi bi-plus-lg me-1"></i> Add Building
            </button>
        <?php endif; ?>
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
                        <th class="ps-4">ID</th>
                        <th>Building Name</th>
                        <th>Created At</th>
                        <?php if ($_SESSION['role'] === 'Admin'): ?>
                            <th class="text-end pe-4">Actions</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($buildings): ?>
                        <?php foreach ($buildings as $b): ?>
                            <tr>
                                <td class="ps-4"><?php echo $b['id']; ?></td>
                                <td class="fw-semibold"><?php echo h($b['name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($b['created_at'])); ?></td>
                                <?php if ($_SESSION['role'] === 'Admin'): ?>
                                    <td class="text-end pe-4">
                                        <form method="POST" action="" class="d-inline">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="delete" value="<?php echo $b['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this building?')">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">No buildings found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($_SESSION['role'] === 'Admin'): ?>
<!-- Add Building Modal -->
<div class="modal fade" id="addBuildingModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title text-white">Add New Building</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Building Name</label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Main Building, Annex A">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_building" class="btn btn-primary">Save Building</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once '../partials/footer.php'; ?>
