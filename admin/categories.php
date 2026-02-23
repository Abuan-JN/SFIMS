<?php
/**
 * Item Categories Management
 * 
 * Manages the top-level categories for inventory items.
 * 1. Creates new categories (e.g., Consumables, IT Equipment).
 * 2. Lists all existing categories.
 * 3. Prevents deletion if items are currently assigned to the category.
 */

require_once '../config/database.php';
require_once '../config/app.php';

require_role('Admin');

$db = Database::getInstance();
$error = '';
$success = '';

// Process Category Creation
if (isset($_POST['add_category'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $name = trim($_POST['name']);
    if ($name) {
        try {
            $stmt = $db->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);
            set_flash_message('success', 'Category added successfully.');
            redirect('admin/categories.php');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) { // Integrity constraint violation (Duplicate entry)
                $error = "A category with that name already exists.";
            } else {
                $error = "Error: " . $e->getMessage();
            }
        }
    }
}

// Process Category Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $category_id = (int)$_POST['delete'];
    try {
        // Enforces referential integrity manually instead of relying solely on ON DELETE SET NULL
        // We shouldn't delete categories that are in use to prevent data loss.
        $stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error = "Cannot delete category heavily assigned to $count item(s). Please reassign items first.";
        } else {
            $stmt = $db->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$category_id]);
            set_flash_message('success', 'Category deleted successfully.');
            redirect('admin/categories.php');
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$page_title = 'Manage Categories';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Manage Categories</h2>
    </div>
    <div class="col-md-6 text-end">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-lg me-1"></i> Add Category
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
                        <th class="ps-4">ID</th>
                        <th>Category Name</th>
                        <th>Created At</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($categories): ?>
                        <?php foreach ($categories as $c): ?>
                            <tr>
                                <td class="ps-4"><?php echo $c['id']; ?></td>
                                <td class="fw-semibold text-primary"><?php echo h($c['name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($c['created_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete" value="<?php echo $c['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this category?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                No categories found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Office Supplies, Vehicles">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_category" class="btn btn-primary">Save Category</button>
            </div>
        </form>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
