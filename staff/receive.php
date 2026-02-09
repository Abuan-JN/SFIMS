<?php
/**
 * Stock Receiving Module
 * 
 * Handles the intake of new inventory.
 * 1. Validates input and starts a database transaction.
 * 2. Creates a record in the 'transactions' table.
 * 3. If item is a 'Fixed Asset', generates unique barcodes and instances.
 * 4. Increments the global 'current_quantity' for the item.
 * 5. Handles file uploads (DR, PO) as transaction attachments.
 * 6. Logs the action for auditing.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Check: Ensure user has appropriate permissions
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Check if an item was pre-selected (e.g., coming from Item Details page)
$preselected_item_id = (int) ($_GET['item_id'] ?? 0);

// Process the stock intake form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item_id = (int) $_POST['item_id'];
    $type = 'RECEIVE';
    $quantity = (int) $_POST['quantity'];
    $date = $_POST['date'] ?: date('Y-m-d');
    $supplier = trim($_POST['supplier'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $user_id = $_SESSION['user_id'];

    if ($item_id && $quantity > 0) {
        try {
            // Use Transaction to ensure all related records (instances, barcodes, inventory count) are atomic
            $db->beginTransaction();

            // Fetch item metadata to check category logic
            $stmt = $db->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if (!$item)
                throw new Exception("Item not found.");

            // Create the primary Transaction record
            $stmt = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, source_supplier, remarks, performed_by) VALUES (?, 'RECEIVE', ?, ?, ?, ?, ?)");
            $stmt->execute([$item_id, $quantity, $date, $supplier, $remarks, $user_id]);
            $transaction_id = $db->lastInsertId();

            // Category Logic: Generate physical instances only for Fixed Assets
            if ($item['category_name'] === 'Fixed Assets') {
                $serials = $_POST['serials'] ?? [];
                $custom_barcodes = $_POST['barcodes'] ?? [];
                for ($i = 0; $i < $quantity; $i++) {
                    $serial = $serials[$i] ?? '';
                    // Generate a unique barcode if not provided by the user
                    $barcode_val = !empty($custom_barcodes[$i]) ? trim($custom_barcodes[$i]) : 'BC-' . str_pad($item_id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(uniqid(), -6));

                    // Store the barcode string
                    $stmt = $db->prepare("INSERT INTO barcodes (item_id, barcode_value) VALUES (?, ?)");
                    $stmt->execute([$item_id, $barcode_val]);
                    $barcode_id = $db->lastInsertId();

                    // Map the physical instance to the item and barcode record
                    $stmt = $db->prepare("INSERT INTO item_instances (item_id, serial_number, barcode_id, status) VALUES (?, ?, ?, 'in-stock')");
                    $stmt->execute([$item_id, $serial, $barcode_id]);
                }
            }

            // Sync the master inventory count
            $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity + ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);

            // File Attachment Logic: Storing related documents (PDF/Images)
            if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];

                if (in_array($ext, $allowed)) {
                    $stored_name = uniqid('att_') . '.' . $ext;
                    $upload_dir = '../uploads/';
                    if (!is_dir($upload_dir))
                        mkdir($upload_dir, 0777, true);

                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $stored_name)) {
                        $stmt = $db->prepare("INSERT INTO attachments (transaction_id, original_filename, stored_filename, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$transaction_id, $file['name'], $stored_name, $file['type'], $file['size']]);
                    }
                }
            }

            // Record the successful operation in the system audit trail
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'RECEIVE', 'Transaction', ?, ?)");
            $logStmt->execute([$user_id, $transaction_id, "Received $quantity " . $item['uom'] . " for " . $item['name']]);

            // Finalize all database changes
            $db->commit();
            set_flash_message('success', 'Stock received successfully.');
            redirect('item_details.php?id=' . $item_id);

        } catch (Exception $e) {
            // Revert changes if any step of the intake process fails
            $db->rollBack();
            $error = "Transaction failed: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

$items = $db->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.status = 'active' ORDER BY i.name ASC")->fetchAll();
$page_title = 'Receive Items';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Record Stock Receiving</h4>
                <a href="import_stock.php" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV</a>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="receiveForm">
                    <div class="mb-3">
                        <label for="item_id" class="form-label fw-semibold">Item to Receive <span
                                class="text-danger">*</span></label>
                        <select name="item_id" id="item_id" class="form-select select2" required>
                            <option value="">Select Item</option>
                            <?php foreach ($items as $it): ?>
                                <option value="<?php echo $it['id']; ?>"
                                    data-category="<?php echo h($it['category_name']); ?>" <?php echo $preselected_item_id == $it['id'] ? 'selected' : ''; ?>>
                                    <?php echo h($it['name']); ?> (Current:
                                    <?php echo $it['current_quantity']; ?>
                                    <?php echo h($it['uom']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label fw-semibold">Quantity Received <span
                                    class="text-danger">*</span></label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="date" class="form-label fw-semibold">Date Received</label>
                            <input type="date" name="date" id="date" class="form-control"
                                value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                    </div>

                    <div id="fixedAssetFields" class="d-none mb-3">
                        <label class="form-label fw-semibold text-primary">Asset Details (Optional)</label>
                        <div id="assetInputsContainer">
                            <!-- Dynamic asset inputs here -->
                        </div>
                        <div class="form-text mt-2 text-info"><i class="bi bi-info-circle"></i> Leave Barcode blank to auto-generate. You can use a scanner to fill the barcode field.</div>
                    </div>

                    <div class="mb-3">
                        <label for="supplier" class="form-label fw-semibold">Source / Supplier</label>
                        <input type="text" name="supplier" id="supplier" class="form-control"
                            placeholder="e.g., Procurement Dept, Office Depot">
                    </div>

                    <div class="mb-3">
                        <label for="remarks" class="form-label fw-semibold">Remarks</label>
                        <textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="attachment" class="form-label fw-semibold">Attachment (PDF/Image)</label>
                        <input type="file" name="attachment" id="attachment" class="form-control">
                        <div class="form-text">Upload PO, DR, or delivery receipt.</div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <a href="../items.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Receive Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const itemSelect = document.getElementById('item_id');
        const quantityInput = document.getElementById('quantity');
        const fixedAssetFields = document.getElementById('fixedAssetFields');
        const assetInputsContainer = document.getElementById('assetInputsContainer');

        function updateFields() {
            const selectedOption = itemSelect.options[itemSelect.selectedIndex];
            const category = selectedOption ? selectedOption.getAttribute('data-category') : '';
            const qty = parseInt(quantityInput.value) || 0;

            if (category === 'Fixed Assets' && qty > 0) {
                fixedAssetFields.classList.remove('d-none');
                const cappedQty = Math.min(qty, 20); // Limit dynamic fields for better performance

                let html = '<div class="row g-2">';
                for (let i = 0; i < cappedQty; i++) {
                    html += `
                    <div class="col-md-6 mb-2">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">#${i + 1}</span>
                            <input type="text" name="serials[]" class="form-control" placeholder="Serial No.">
                            <input type="text" name="barcodes[]" class="form-control" placeholder="Scan Barcode (Optional)">
                        </div>
                    </div>
                `;
                }
                html += '</div>';
                if (qty > 20) {
                    html += '<div class="alert alert-warning py-1 small mt-2">Only the first 20 assets can have custom serials/barcodes in this form. The rest will be auto-generated.</div>';
                }
                assetInputsContainer.innerHTML = html;
            } else {
                fixedAssetFields.classList.add('d-none');
                assetInputsContainer.innerHTML = '';
            }
        }

        itemSelect.addEventListener('change', updateFields);
        quantityInput.addEventListener('input', updateFields);

        // Initial check
        updateFields();
    });
</script>

<?php require_once '../partials/footer.php'; ?>