<?php
// staff/import_stock.php
require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$db = Database::getInstance();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // Assuming first row is header: Name, Category, Qty, UOM, Supplier, Remarks

        $success_count = 0;
        $db->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) < 3) continue;

                $name = trim($row[0]);
                $category_name = trim($row[1]);
                $qty = (int)$row[2];
                $uom = trim($row[3] ?? 'pcs');
                $supplier = trim($row[4] ?? '');
                $remarks = trim($row[5] ?? '');

                // Find or create item
                $stmt = $db->prepare("SELECT id FROM items WHERE name = ?");
                $stmt->execute([$name]);
                $item = $stmt->fetch();

                if (!$item) {
                    // Find category ID
                    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->execute([$category_name]);
                    $cat = $stmt->fetch();
                    $cat_id = $cat ? $cat['id'] : null;

                    $stmt = $db->prepare("INSERT INTO items (name, category_id, uom, current_quantity) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$name, $cat_id, $uom]);
                    $item_id = $db->lastInsertId();
                } else {
                    $item_id = $item['id'];
                }

                // Record transaction
                $stmt = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, source_supplier, remarks, performed_by) VALUES (?, 'RECEIVE', ?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $qty, date('Y-m-d'), $supplier, $remarks, $_SESSION['user_id']]);
                $transaction_id = $db->lastInsertId();

                // Update item qty
                $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity + ? WHERE id = ?");
                $stmt->execute([$qty, $item_id]);

                // Handle Fixed Assets (automatically generate barcodes for imported assets)
                if ($category_name === 'Fixed Assets') {
                    for ($i = 0; $i < $qty; $i++) {
                        $barcode_val = 'BC-' . str_pad($item_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -6));
                        
                        // Insert into barcodes
                        $stmt = $db->prepare("INSERT INTO barcodes (item_id, barcode_value) VALUES (?, ?)");
                        $stmt->execute([$item_id, $barcode_val]);
                        $barcode_id = $db->lastInsertId();

                        // Insert into item_instances
                        $stmt = $db->prepare("INSERT INTO item_instances (item_id, barcode_id, status) VALUES (?, ?, 'in-stock')");
                        $stmt->execute([$item_id, $barcode_id]);
                    }
                }

                $success_count++;
            }
            $db->commit();
            set_flash_message('success', "Successfully imported $success_count entries.");
            redirect('../items.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
        fclose($handle);
    } else {
        $error = "File upload error.";
    }
}

$page_title = 'Import Stock Reports';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0">Import Stock Reports (CSV)</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                        <div class="form-text mt-2">
                            Expected CSV Headers: <code>Name, Category, Qty, UOM, Supplier, Remarks</code>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Start Import</button>
                        <a href="receive.php" class="btn btn-light border">Back to Manual Receive</a>
                    </div>
                </form>

                <div class="mt-4 p-3 bg-light rounded border">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Instruction</h6>
                    <small class="text-muted d-block mb-1">Items will be created if they don't exist.</small>
                    <small class="text-muted d-block">Fixed Assets will have barcodes auto-generated.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
