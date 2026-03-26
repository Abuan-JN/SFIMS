<?php
/**
 * Item Sub-Categories Management
 * 
 * Manages the secondary classification for inventory items.
 * 1. Creates new sub-categories (e.g., Laptops, Desktops under IT Equipment).
 * 2. Links them to primary categories.
 * 3. Prevents deletion if items are currently assigned to the sub-category.
 */

require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Process Individual Sub-Category Creation
if (isset($_POST['add_sub_category'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $category_id = (int)$_POST['category_id'];
    $name = trim($_POST['name']);
    if ($category_id && $name) {
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM sub_categories WHERE category_id = ? AND name = ?");
            $stmt->execute([$category_id, $name]);
            if ($stmt->fetchColumn() > 0) {
                $error = "A sub-category with that name already exists under the selected category.";
            } else {
                $stmt = $db->prepare("INSERT INTO sub_categories (category_id, name) VALUES (?, ?)");
                $stmt->execute([$category_id, $name]);
                set_flash_message('success', 'Sub-Category added successfully.');
                redirect('inventory/sub_categories.php');
            }
        } catch (PDOException $e) { $error = "Error: " . $e->getMessage(); }
    } else { $error = "Category and Name are required."; }
}

// Process Bulk Sub-Category Creation
if (isset($_POST['bulk_add_sub_category'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $category_id = (int)$_POST['category_id'];
    $bulk_text = trim($_POST['bulk_names']);
    
    if ($category_id && $bulk_text) {
        $names = explode("\n", $bulk_text);
        $added_count = 0;
        $skipped_count = 0;
        
        $db->beginTransaction();
        try {
            foreach ($names as $name) {
                $name = trim($name);
                if (empty($name)) continue;
                
                // Check for duplicate
                $stmt = $db->prepare("SELECT id FROM sub_categories WHERE category_id = ? AND name = ?");
                $stmt->execute([$category_id, $name]);
                if ($stmt->fetch()) {
                    $skipped_count++;
                    continue;
                }
                
                $stmt = $db->prepare("INSERT INTO sub_categories (category_id, name) VALUES (?, ?)");
                $stmt->execute([$category_id, $name]);
                $added_count++;
            }
            $db->commit();
            
            $msg = "Successfully added $added_count sub-category(ies).";
            if ($skipped_count > 0) $msg .= " ($skipped_count skipped as duplicates)";
            set_flash_message('success', $msg);
            redirect('inventory/sub_categories.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Bulk add failed: " . $e->getMessage();
        }
    } else { $error = "Category and Names are required."; }
}

// Process Sub-Category Removal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $sub_category_id = (int)$_POST['delete'];
    try {
        // Enforces referential integrity
        $stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE sub_category_id = ?");
        $stmt->execute([$sub_category_id]);
        $count = $stmt->fetchColumn();

        if ($count > 0) {
            $error = "Cannot delete sub-category assigned to $count item(s). Please reassign items first.";
        } else {
            $stmt = $db->prepare("DELETE FROM sub_categories WHERE id = ?");
            $stmt->execute([$sub_category_id]);
            set_flash_message('success', 'Sub-Category deleted successfully.');
            redirect('inventory/sub_categories.php');
        }
    } catch (PDOException $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch lists
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$sql = "SELECT s.*, c.name as category_name 
        FROM sub_categories s 
        JOIN categories c ON s.category_id = c.id 
        ORDER BY c.name ASC, s.name ASC";
$sub_categories = $db->query($sql)->fetchAll();

$page_title = 'Manage Sub-Categories';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Manage Sub-Categories</h2>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#bulkAddSubCategoryModal">
                <i class="bi bi-list-task me-1"></i> Bulk Add
            </button>
            <?php if ($_SESSION['role'] === 'Admin'): ?>
                <a href="../admin/import_master.php?type=sub_categories" class="btn btn-outline-secondary">
                    <i class="bi bi-file-earmark-spreadsheet me-1"></i> CSV Import
                </a>
            <?php endif; ?>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubCategoryModal">
                <i class="bi bi-plus-lg me-1"></i> Add Single
            </button>
        </div>
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
                        <th>Parent Category</th>
                        <th>Sub-Category Name</th>
                        <th>Created At</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sub_categories): ?>
                        <?php foreach ($sub_categories as $sc): ?>
                            <tr>
                                <td class="ps-4"><?php echo $sc['id']; ?></td>
                                <td><?php echo h($sc['category_name']); ?></td>
                                <td class="fw-semibold text-primary"><?php echo h($sc['name']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($sc['created_at'])); ?></td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="delete" value="<?php echo $sc['id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this sub-category?')">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                No sub-categories found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Sub-Category Modal -->
<div class="modal fade" id="addSubCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Add New Sub-Category</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">Parent Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo h($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Sub-Category Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g., Laptops, Markers">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="add_sub_category" class="btn btn-primary px-4">Save Sub-Category</button>
            </div>
        </form>
    </div>
</div>

<!-- Bulk Add Sub-Category Modal -->
<div class="modal fade" id="bulkAddSubCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content text-dark">
            <?php csrf_field(); ?>
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-list-task me-2"></i>Bulk Add Sub-Categories</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small border-0">
                    <i class="bi bi-info-circle me-2"></i> Enter one sub-category name per line.
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Parent Category <span class="text-danger">*</span></label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo h($cat['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Sub-Category Names <span class="text-danger">*</span></label>
                    <textarea name="bulk_names" class="form-control" rows="8" required placeholder="Laptops&#10;Monitors&#10;Printers&#10;Keyboards"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="bulk_add_sub_category" class="btn btn-info px-4">Bulk Save</button>
            </div>
        </form>
    </div>
</div>


<?php require_once '../partials/footer.php'; ?>
