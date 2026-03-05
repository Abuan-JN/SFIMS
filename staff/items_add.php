<?php
/**
 * Item Catalog Creation
 * 
 * Manages the addition of new item types to the master inventory list.
 * 1. Collects basic metadata (Name, Category, UoM).
 * 2. Sets a 'Low Stock Threshold' for automated dashboard alerts.
 * 3. Initializes the item with zero quantity (In-stock occurs via 'Receive').
 * 4. Logs the creation event in the audit trail.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Process the new item form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    // Case 1: CSV Bulk Import
    if (isset($_FILES['csv_file'])) {
        $file = $_FILES['csv_file'];
        if ($file['error'] === UPLOAD_ERR_OK) {
            $handle = fopen($file['tmp_name'], 'r');
            $header = fgetcsv($handle); // Header line

            $success_count = 0;
            $db->beginTransaction();

            try {
                while (($row = fgetcsv($handle)) !== FALSE) {
                    if (count($row) < 4) continue;

                    $name = trim($row[0]);
                    $category_name = trim($row[1]);
                    $sub_category_name = trim($row[2]);
                    $uom = trim($row[3]);
                    $threshold = (int)($row[4] ?? 0);
                    $description = trim($row[5] ?? '');

                    if (empty($name) || empty($category_name)) continue;

                    // 1. Check for duplicates
                    $stmt = $db->prepare("SELECT id FROM items WHERE name = ?");
                    $stmt->execute([$name]);
                    if ($stmt->fetch()) continue;

                    // 2. Resolve Category
                    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->execute([$category_name]);
                    $cat = $stmt->fetch();
                    if (!$cat) continue;
                    $cat_id = $cat['id'];

                    // 3. Resolve Sub-Category (Optional)
                    $sub_cat_id = null;
                    if (!empty($sub_category_name)) {
                        $stmt = $db->prepare("SELECT id FROM sub_categories WHERE category_id = ? AND name = ?");
                        $stmt->execute([$cat_id, $sub_category_name]);
                        $sub_cat = $stmt->fetch();
                        if ($sub_cat) {
                            $sub_cat_id = $sub_cat['id'];
                        }
                    }

                    // 4. Insert Item
                    $stmt = $db->prepare("INSERT INTO items (name, description, category_id, sub_category_id, uom, threshold_quantity, current_quantity, status) VALUES (?, ?, ?, ?, ?, ?, 0, 'active')");
                    $stmt->execute([$name, $description, $cat_id, $sub_cat_id, $uom, $threshold]);
                    $item_id = $db->lastInsertId();

                    // 5. Audit Log
                    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'ITEM_IMPORT', 'Item', ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], $item_id, "Imported item via CSV: $name"]);

                    $success_count++;
                }
                $db->commit();
                fclose($handle);
                set_flash_message('success', "Successfully imported $success_count items into the catalog.");
                redirect('inventory/items.php');
            } catch (Exception $e) {
                $db->rollBack();
                fclose($handle);
                $error = "Import failed: " . $e->getMessage();
            }
        } else {
            $error = "File upload failed.";
        }
    } 
    // Case 2: Single Item Add
    else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category_id = (int) ($_POST['category_id'] ?? 0);
        $uom = trim($_POST['uom'] ?? '');
        $threshold = (int) ($_POST['threshold_quantity'] ?? 0);
        $sub_category_id = !empty($_POST['sub_category_id']) ? (int) $_POST['sub_category_id'] : null;

        if ($name && $category_id && $uom) {
            // Check for duplicate item name
            $stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE name = ?");
            $stmt->execute([$name]);
            if ($stmt->fetchColumn() > 0) {
                $error = "An item with this name already exists.";
            } else {
                // Insert into master items table
                $stmt = $db->prepare("INSERT INTO items (name, description, category_id, sub_category_id, uom, threshold_quantity, current_quantity, status) VALUES (?, ?, ?, ?, ?, ?, 0, 'active')");
                if ($stmt->execute([$name, $description, $category_id, $sub_category_id, $uom, $threshold])) {
                    $itemId = $db->lastInsertId();

                    // Record the action for security auditing
                    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'ITEM_CREATE', 'Item', ?, ?)");
                    $logStmt->execute([$_SESSION['user_id'], $itemId, "Created new item: $name"]);

                    set_flash_message('success', 'Item added successfully.');
                    redirect('inventory/items.php');
                } else {
                    $error = "Failed to add item.";
                }
            }
        } else {
            $error = "Please fill in all required fields.";
        }
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$is_import = (isset($_GET['mode']) && $_GET['mode'] === 'import');
$page_title = $is_import ? 'Import Catalog Items' : 'Add New Item';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0"><?php echo h($page_title); ?></h4>
                <?php if ($is_import): ?>
                    <a href="items_add.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-plus-circle me-1"></i> Add Single Item
                    </a>
                <?php else: ?>
                    <a href="items_add.php?mode=import" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-cloud-upload me-1"></i> Bulk Import
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (!$is_import): ?>
                <form method="POST" action="">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Item Name <span
                                class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label fw-semibold">Category <span
                                    class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select" required onchange="filterSubCategories()">
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo h($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="sub_category_id" class="form-label fw-semibold">Sub-Category</label>
                            <select name="sub_category_id" id="sub_category_id" class="form-select">
                                <option value="">Select Category First</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="uom" class="form-label fw-semibold">Unit of Measure <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="uom" id="uom" class="form-control"
                                placeholder="pcs, box, set, etc." required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="threshold_quantity" class="form-label fw-semibold">Low Stock Threshold</label>
                            <input type="number" name="threshold_quantity" id="threshold_quantity" class="form-control"
                                value="0" min="0">
                            <div class="form-text">Alert will trigger when stock falls to or below this level.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="../inventory/items.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Item</button>
                    </div>
                </form>
                <?php else: ?>
                    <div class="alert alert-info border-0 shadow-none">
                        <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Bulk Import Guidelines</h6>
                        <ul class="mb-0 small">
                            <li>The first row of your CSV must be headers.</li>
                            <li><strong>Category</strong> and <strong>Item Name</strong> are required.</li>
                            <li>Names must match existing categories and sub-categories.</li>
                            <li>Duplicate item names will be automatically skipped.</li>
                        </ul>
                    </div>

                    <form method="POST" enctype="multipart/form-data" class="mt-4">
                        <?php csrf_field(); ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold">Upload CSV File</label>
                            <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                            <div class="form-text mt-2 text-muted">
                                Format: <code>Name, Category, Sub-Category, UOM, Threshold, Description</code>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="bi bi-cloud-upload me-2"></i>Run Import Process
                            </button>
                            <a href="data:text/csv;charset=utf-8,Name,Category,Sub-Category,UOM,Threshold,Description%0ALaptop%20X1,Fixed%20Assets,Laptop,set,5,Modern%20workstation" download="sfims_catalog_template.csv" class="text-center small text-decoration-none py-2">
                                <i class="bi bi-download me-1"></i>Download Template CSV
                            </a>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php 
// Fetch all sub-categories to use in JS
$allSubCats = $db->query("SELECT * FROM sub_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<script>
    const subCategories = <?php echo json_encode($allSubCats); ?>;

    function filterSubCategories() {
        const catId = document.getElementById('category_id').value;
        const subCatSelect = document.getElementById('sub_category_id');
        
        // Clear current options
        subCatSelect.innerHTML = '<option value="">Select Sub-Category</option>';
        
        if (catId) {
            const filtered = subCategories.filter(sub => sub.category_id == catId);
            if (filtered.length > 0) {
                filtered.forEach(sub => {
                    const option = document.createElement('option');
                    option.value = sub.id;
                    option.textContent = sub.name;
                    subCatSelect.appendChild(option);
                });
                subCatSelect.disabled = false;
            } else {
                const option = document.createElement('option');
                option.textContent = "No sub-categories available";
                subCatSelect.appendChild(option);
                subCatSelect.disabled = true;
            }
        } else {
            subCatSelect.disabled = true;
            subCatSelect.innerHTML = '<option value="">Select Category First</option>';
        }
    }
</script>

<?php require_once '../partials/footer.php'; ?>