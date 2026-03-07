<?php
/**
 * Bulk Stock Importer (CSV)
 * 
 * Facilitates the mass intake of inventory from external spreadsheets.
 * 1. Parses CSV files using fgetcsv.
 * 2. Dynamically creates new items if the name is not found in the system.
 * 3. Categorizes items to determine if physical instances/barcodes are needed.
 * 4. Wraps the entire import process in a single transaction for data integrity.
 * 5. Automatically generates unique barcodes and unit records for 'Fixed Assets'.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Handle CSV file upload and processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        // Skips the first row (headers)
        $header = fgetcsv($handle); 

        $success_count = 0;
        // Transaction ensures that if one row fails, the entire import is rolled back
        $db->beginTransaction();
        try {
            while (($row = fgetcsv($handle)) !== FALSE) {
                // Minimum data validation: Name, Category, Qty
                if (count($row) < 3) continue;

                $name = trim($row[0]);
                $category_name = trim($row[1]);
                $qty = (int)$row[2];
                $uom = trim($row[3] ?? 'pcs');
                $supplier = trim($row[4] ?? '');
                $remarks = trim($row[5] ?? '');
                $serial = trim($row[6] ?? '');
                $location_str = trim($row[7] ?? '');

                // Step 0: Resolve Storage Location (Room)
                $room_id = null;
                if (!empty($location_str)) {
                    if (strpos($location_str, ' - ') !== false) {
                        list($b_name, $r_name) = explode(' - ', $location_str, 2);
                        $stmt = $db->prepare("SELECT r.id FROM rooms r JOIN buildings b ON r.building_id = b.id WHERE b.name = ? AND r.name = ?");
                        $stmt->execute([trim($b_name), trim($r_name)]);
                    } else {
                        $stmt = $db->prepare("SELECT id FROM rooms WHERE name = ? LIMIT 1");
                        $stmt->execute([$location_str]);
                    }
                    $rm = $stmt->fetch();
                    if ($rm) $room_id = $rm['id'];
                }

                // Step 1: Check if the item already exists in the master list
                $stmt = $db->prepare("SELECT id FROM items WHERE name = ?");
                $stmt->execute([$name]);
                $item = $stmt->fetch();

                if (!$item) {
                    // Step 1b: Map Category Name to ID
                    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->execute([$category_name]);
                    $cat = $stmt->fetch();
                    $cat_id = $cat ? $cat['id'] : null;

                    // Create the master Item entry
                    $stmt = $db->prepare("INSERT INTO items (name, category_id, uom, current_quantity) VALUES (?, ?, ?, 0)");
                    $stmt->execute([$name, $cat_id, $uom]);
                    $item_id = $db->lastInsertId();
                } else {
                    $item_id = $item['id'];
                }

                // Step 2: Record the overall 'RECEIVE' transaction
                $stmt = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, source_supplier, remarks, performed_by, room_id) VALUES (?, 'RECEIVE', ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $qty, date('Y-m-d'), $supplier, $remarks, $_SESSION['user_id'], $room_id]);
                $transaction_id = $db->lastInsertId();

                // Step 3: Increment the master inventory level
                $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity + ? WHERE id = ?");
                $stmt->execute([$qty, $item_id]);

                // Step 4: Special Logic for Fixed Assets - Individual units
                if ($category_name === 'Fixed Assets') {
                    for ($i = 0; $i < $qty; $i++) {
                        // Use serial as barcode if provided and qty is 1
                        if ($qty === 1 && !empty($serial)) {
                            $barcode_val = $serial;
                        } else {
                            // Auto-generate unique barcode
                            $barcode_val = 'BC-' . str_pad($item_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -6));
                        }
                        
                        // Register the barcode
                        $stmt = $db->prepare("INSERT INTO barcodes (item_id, barcode_value) VALUES (?, ?)");
                        $stmt->execute([$item_id, $barcode_val]);
                        $barcode_id = $db->lastInsertId();

                        // Create the physical instance record
                        $stmt = $db->prepare("INSERT INTO item_instances (item_id, barcode_id, serial_number, room_id, status) VALUES (?, ?, ?, ?, 'in-stock')");
                        $stmt->execute([$item_id, $barcode_id, $serial, $room_id]);
                    }
                }

                $success_count++;
            }
            $db->commit();
            set_flash_message('success', "Successfully imported $success_count entries.");
            redirect('inventory/items.php');
        } catch (Exception $e) {
            // Revert all changes if any single record fails
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
                            Expected CSV Headers: <code>Name, Category, Qty, UOM, Supplier, Remarks, Serial Number, Storage Location</code>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">Start Import</button>
                        <a href="data:text/csv;charset=utf-8,Name,Category,Qty,UOM,Supplier,Remarks,Serial%20Number,Storage%20Location%0AHP%20LaserJet%20Pro,Fixed%20Assets,1,pcs,Office%20Supply%20Co,New%20arival,SN-123456,Rizal%20Building%20-%20ComLab%201%0APaper%20A4,Consumables,50,reams,Paper%20Corp,Stock%20replenishment,,Warehouse" download="stock_import_template.csv" class="btn btn-outline-success border">
                            <i class="bi bi-download me-1"></i>Download Sample Template
                        </a>
                        <a href="receive.php" class="btn btn-light border">Back to Manual Receive</a>
                    </div>
                </form>

                <div class="mt-4 p-3 bg-light rounded border text-dark">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Instruction</h6>
                    <small class="text-muted d-block mb-1">Items will be created if they don't exist.</small>
                    <small class="text-muted d-block mb-1">Specific Serials require <code>Qty=1</code> for Fixed Assets.</small>
                    <small class="text-muted d-block mb-1"><strong>Storage Location:</strong> Use <code>Building - Room</code> (e.g., Rizal - ComLab 1) or just <code>Room Name</code>.</small>
                    <small class="text-muted d-block">Barcodes will be automatically generated for Fixed Assets.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
