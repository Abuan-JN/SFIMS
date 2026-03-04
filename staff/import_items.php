<?php
/**
 * Item Catalog Importer (CSV)
 * 
 * Allows administrative staff to bulk populate the master item list.
 * 1. Parses CSV files with metadata (Name, Category, Sub-Category, UoM, etc.).
 * 2. Validates against existing categories and sub-categories.
 * 3. Prevents duplicate item names within the catalog.
 * 4. Wraps the process in a transaction for atomicity.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // Header: Name, Category, Sub-Category, UOM, Threshold, Description

        $success_count = 0;
        $db->beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) < 4) continue; // Minimum: Name, Category, UOM

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
            set_flash_message('success', "Successfully imported $success_count items into the catalog.");
            redirect('inventory/items.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
        fclose($handle);
    } else {
        $error = "File upload failed.";
    }
}

$page_title = 'Import Catalog Items';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Import Catalog Items (CSV)</h4>
                <a href="../inventory/items.php" class="btn btn-sm btn-outline-secondary">Back to List</a>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="alert alert-info border-0 shadow-none">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Data Guidelines</h6>
                    <ul class="mb-0 small">
                        <li>The first row of your CSV must be headers.</li>
                        <li><strong>Category</strong> and <strong>Item Name</strong> are required.</li>
                        <li>Category & Sub-Category names must match existing records in the system.</li>
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

                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="bi bi-cloud-upload me-2"></i>Run Import Process
                        </button>
                    </div>
                </form>

                <div class="mt-5 border-top pt-4">
                    <h6 class="fw-bold mb-3">Download Sample Template</h6>
                    <p class="text-muted small">Use this template to ensure your data format is correct before uploading.</p>
                    <a href="data:text/csv;charset=utf-8,Name,Category,Sub-Category,UOM,Threshold,Description%0ALaptop%20X1,Fixed%20Assets,Laptop,set,5,Modern%20workstation" download="sfims_catalog_template.csv" class="btn btn-sm btn-outline-primary">
                        <i class="bi bi-download me-1"></i>sfims_catalog_template.csv
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
