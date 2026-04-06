<?php
/**
 * Stock Bulk Receiving Module
 * 
 * Handles the intake of new inventory via a "Cart" system.
 * 1. Validates input and starts a database transaction.
 * 2. Creates a record in the 'transactions' table for each item.
 * 3. Generates unique barcodes and instances automatically for Fixed Assets.
 * 4. Increments the global 'current_quantity' for each item.
 * 5. Handles file uploads (DR, PO) as global attachments.
 * 6. Logs the action for auditing.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Check: Ensure user has appropriate permissions
require_role('Staff');

$db = Database::getInstance();
$error = '';
$success = '';

// Check if an item was pre-selected (e.g., coming from Item Details page)
$preselected_item_id = (int) ($_GET['item_id'] ?? 0);

// Process the stock bulk intake form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $item_ids = $_POST['item_ids'] ?? [];
    $quantities = $_POST['quantities'] ?? [];
    $serials_data = $_POST['serials'] ?? [];
    
    $date = $_POST['date'] ?: date('Y-m-d');
    $supplier = trim($_POST['supplier'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $room_id = !empty($_POST['room_id']) ? (int) $_POST['room_id'] : null;
    $user_id = $_SESSION['user_id'];
    
    global $global_transaction_id;
    $global_transaction_id = null;

    if (!empty($item_ids) && count($item_ids) === count($quantities)) {
        try {
            // Use Transaction to ensure all related records (instances, barcodes, inventory count) are atomic
            $db->beginTransaction();

            // Handle Global File Attachment Logic first (attach to the first transaction, or create a dummy global tx?)
            // We will attach the file to the first item's transaction_id to keep the DB schema happy.
            $first_transaction_id = null;

            for ($i = 0; $i < count($item_ids); $i++) {
                $item_id = (int) $item_ids[$i];
                $quantity = (int) $quantities[$i];

                if ($quantity <= 0) continue;

                // Fetch item metadata to check category logic
                $stmt = $db->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch();

                if (!$item) throw new Exception("Item ID $item_id not found.");

                // Create the primary Transaction record for this line item
                $stmt = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, source_supplier, remarks, performed_by, room_id) VALUES (?, 'RECEIVE', ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $quantity, $date, $supplier, $remarks, $user_id, $room_id]);
                $transaction_id = $db->lastInsertId();
                
                if (is_null($first_transaction_id)) {
                    $first_transaction_id = $transaction_id;
                }

                // Category Logic: Generate physical instances automatically for Fixed Assets
                if ($item['category_name'] === 'Fixed Assets') {
                    $typeCode = 1;
                    $catId = $item['category_id'];
                    $subCatId = $item['sub_category_id'] ?? 0;
                    $pItemId = str_pad($item_id, 4, '0', STR_PAD_LEFT);

                    for ($j = 0; $j < $quantity; $j++) {
                        // Get serial number from post data if available for this specific instance
                        $serial = !empty($serials_data[$item_id][$j]) ? trim($serials_data[$item_id][$j]) : null;

                        // If serial number is provided, use it as the barcode value
                        // Otherwise, auto-generate the barcode in the standard format
                        if ($serial) {
                            $barcode_val = $serial;
                        } else {
                            $suffix = strtoupper(substr(uniqid(), -4));
                            $barcode_val = "{$typeCode}/{$catId}/{$subCatId}/{$pItemId}-{$suffix}";
                        }

                        // Store the barcode string
                        $stmt = $db->prepare("INSERT INTO barcodes (item_id, barcode_value) VALUES (?, ?)");
                        $stmt->execute([$item_id, $barcode_val]);
                        $barcode_id = $db->lastInsertId();

                        // Map the physical instance
                        $stmt = $db->prepare("INSERT INTO item_instances (item_id, serial_number, barcode_id, room_id, status) VALUES (?, ?, ?, ?, 'in-stock')");
                        $stmt->execute([$item_id, $serial, $barcode_id, $room_id]);
                    }
                }

                // Sync the master inventory count
                $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity + ? WHERE id = ?");
                $stmt->execute([$quantity, $item_id]);

                // Record the successful operation in the system audit trail
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'RECEIVE', 'Transaction', ?, ?)");
                $logStmt->execute([$user_id, $transaction_id, "Received $quantity " . $item['uom'] . " for " . $item['name']]);
            }

            // File Attachment Logic: Attach to the first transaction ID
            if ($first_transaction_id && isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['attachment'];
                
                // SECURITY: File size validation (5MB max)
                $max_size = 5 * 1024 * 1024; // 5MB
                if ($file['size'] > $max_size) {
                    throw new Exception("File size exceeds 5MB limit.");
                }
                
                // SECURITY: MIME type validation using finfo_file
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
                if (!in_array($mime_type, $allowed_mimes)) {
                    throw new Exception("Invalid file type. Only JPEG, PNG, PDF, and DOC/DOCX files are allowed.");
                }
                
                // SECURITY: Generate random filename to prevent path traversal
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'];
                
                if (in_array($ext, $allowed_exts)) {
                    $stored_name = 'att_' . bin2hex(random_bytes(16)) . '.' . $ext;
                    $upload_dir = '../uploads/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

                    if (move_uploaded_file($file['tmp_name'], $upload_dir . $stored_name)) {
                        $stmt = $db->prepare("INSERT INTO attachments (transaction_id, original_filename, stored_filename, file_type, file_size) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$first_transaction_id, $file['name'], $stored_name, $mime_type, $file['size']]);
                    }
                }
            }

            // Finalize all database changes
            $db->commit();
            set_flash_message('success', 'Bulk stock received successfully.');
            redirect('inventory/transactions.php');

        } catch (Exception $e) {
            // Revert changes if any step of the intake process fails
            $db->rollBack();
            $error = "Transaction failed: " . $e->getMessage();
        }
    } else {
        $error = "Please add at least one item to the receiving cart.";
    }
}

$items = $db->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id ORDER BY i.name ASC")->fetchAll();
$rooms = $db->query("SELECT r.id, r.name as room_name, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();
$page_title = 'Receive Items';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Record Stock Receiving</h4>
                <a href="import_stock.php" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Import CSV</a>
            </div>
            <div class="card-body p-4">
                <div class="alert alert-info border-0 small mb-4">
                    <i class="bi bi-info-circle-fill me-2"></i> <strong>What is this form for?</strong> Use this page to log new items coming into the supply room from external suppliers. This will increase your current stock levels.
                </div>
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" enctype="multipart/form-data" id="receiveForm">
                    <?php csrf_field(); ?>
                    
                    <div class="bg-light p-3 rounded mb-4 border">
                        <h6 class="fw-bold mb-3"><i class="bi bi-cart-plus me-2"></i>Add Items to List</h6>
                        <div class="row g-2 align-items-end">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold">Select Item</label>
                                <select id="itemSelector" class="form-select select2">
                                    <option value="">Search and select...</option>
                                    <?php foreach ($items as $it): ?>
                                        <option value="<?php echo $it['id']; ?>" data-name="<?php echo h($it['name']); ?>" data-category="<?php echo h($it['category_name']); ?>" data-uom="<?php echo h($it['uom']); ?>">
                                            <?php echo h($it['name']); ?> (<?php echo h($it['category_name']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Quantity</label>
                                <input type="number" id="qtySelector" class="form-control" min="1" value="1">
                            </div>
                            <div class="col-md-2 d-grid">
                                <button type="button" class="btn btn-primary" id="addToListBtn">Add</button>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover align-middle" id="cartTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Name</th>
                                    <th>Category</th>
                                    <th style="width: 120px;">Quantity</th>
                                    <th style="width: 80px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr id="emptyCartRow">
                                    <td colspan="4" class="text-center text-muted py-4">No items added yet. Please add items using the selector above.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label for="date" class="form-label fw-semibold">Date Received</label>
                            <input type="date" name="date" id="date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="room_id" class="form-label fw-semibold">Intended Storage Location</label>
                            <select name="room_id" id="room_id" class="form-select select2">
                                <option value="">Warehouse (Default)</option>
                                <?php foreach ($rooms as $rm): ?>
                                    <option value="<?php echo $rm['id']; ?>">
                                        <?php echo h($rm['building_name'] . ' - ' . $rm['room_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <details class="mb-4 border rounded bg-light" id="optionalDetails">
                        <summary class="p-3 fw-bold fw-semibold" style="cursor: pointer; list-style: none;">
                            <i class="bi bi-chevron-down me-2"></i> Document Data & Supplier Info (Optional)
                        </summary>
                        <div class="p-3 border-top bg-white">
                            <div class="mb-3">
                                <label for="supplier" class="form-label fw-semibold">Source / Supplier</label>
                                <input type="text" name="supplier" id="supplier" class="form-control" placeholder="e.g., Procurement Dept, Office Depot">
                            </div>
                            <div class="mb-3">
                                <label for="remarks" class="form-label fw-semibold">Remarks</label>
                                <textarea name="remarks" id="remarks" class="form-control" rows="2"></textarea>
                            </div>
                            <div>
                                <label for="attachment" class="form-label fw-semibold">Attachment (PDF/Image)</label>
                                <input type="file" name="attachment" id="attachment" class="form-control">
                                <div class="form-text">Upload PO, DR, or delivery receipt.</div>
                            </div>
                        </div>
                    </details>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <a href="../inventory/items.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-success px-5 fw-bold"><i class="bi bi-check-circle me-1"></i> Receive Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    /* RAPID SCAN UI */
    .rapid-scan-group {
        border: 2px dashed rgba(13, 110, 253, 0.2) !important;
        background: rgba(13, 110, 253, 0.02) !important;
        transition: all 0.2s ease;
    }
    .rapid-scan-group:focus-within {
        border-color: #0d6efd !important;
        background: rgba(13, 110, 253, 0.05) !important;
    }
    .scanner-input {
        letter-spacing: 1px;
        font-family: 'Courier New', Courier, monospace;
        font-weight: bold;
    }
    /* SUCCESS FLASH ANIMATION */
    .individual-serial.is-valid-scan {
        animation: scanSuccess 0.8s ease;
    }
    @keyframes scanSuccess {
        0% { background-color: #198754; color: #fff; border-color: #198754; }
        100% { background-color: transparent; color: inherit; border-color: #ced4da; }
    }
    .serial-field-wrap .input-group-text {
        background-color: rgba(0,0,0,0.03);
        color: #6c757d;
        font-weight: bold;
    }
</style>


<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('itemSelector');
    const qtyInput = document.getElementById('qtySelector');
    const addBtn = document.getElementById('addToListBtn');
    const cartTbody = document.getElementById('cartTable').querySelector('tbody');
    const emptyRow = document.getElementById('emptyCartRow');

    addBtn.addEventListener('click', function() {
        if (!selector.value || qtyInput.value <= 0) {
            alert('Please select a valid item and quantity greater than 0.');
            return;
        }

        const option = selector.options[selector.selectedIndex];
        const id = selector.value;
        const name = option.getAttribute('data-name');
        const cat = option.getAttribute('data-category');
        const uom = option.getAttribute('data-uom');
        const qty = parseInt(qtyInput.value);

        // Check if item already exists in cart, update qty if so
        const existingRow = document.getElementById('cart-row-' + id);
        if (existingRow) {
            const existingQtyInput = existingRow.querySelector('.cart-qty');
            existingQtyInput.value = parseInt(existingQtyInput.value) + qty;
            if (cat === 'Fixed Assets') renderSerialInputs(existingRow, id, existingQtyInput.value);
        } else {
            // Add new row
            const tr = document.createElement('tr');
            tr.id = 'cart-row-' + id;
            tr.innerHTML = `
                <td>
                    <strong>${name}</strong>
                    <input type="hidden" name="item_ids[]" value="${id}">
                    ${cat === 'Fixed Assets' ? `<div class="serial-inputs mt-2 small" id="serials-container-${id}"></div>` : ''}
                </td>
                <td><span class="badge bg-secondary">${cat}</span></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" name="quantities[]" class="form-control cart-qty" value="${qty}" min="1" data-id="${id}" data-category="${cat}">
                        <span class="input-group-text">${uom}</span>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-btn"><i class="bi bi-x-lg"></i></button>
                </td>
            `;
            cartTbody.appendChild(tr);

            if (cat === 'Fixed Assets') renderSerialInputs(tr, id, qty);

            // Bind remove button
            tr.querySelector('.remove-btn').addEventListener('click', function() {
                tr.remove();
                if (cartTbody.querySelectorAll('tr').length === 1) { // Only empty row left
                    emptyRow.style.display = 'block';
                }
            });

            emptyRow.style.display = 'none';
        }

        // Reset inputs
        $(selector).val(null).trigger('change');
        qtyInput.value = 1;
    });

    function renderSerialInputs(row, itemId, qty) {
        const container = row.querySelector('.serial-inputs');
        if (!container) return;

        // Save current values to restore them after innerHTML reset
        const currentInputs = container.querySelectorAll('.individual-serial');
        const vals = Array.from(currentInputs).map(i => i.value);

        let html = `
            <div class="rapid-scan-group mb-3 p-2 border rounded bg-white shadow-sm">
                <label class="form-label small fw-bold text-primary mb-1"><i class="bi bi-upc-scan me-1"></i> Rapid Scan Mode</label>
                <div class="input-group input-group-sm">
                    <input type="text" class="form-control scanner-input" placeholder="Scan Serial into this field..." data-item-id="${itemId}">
                    <span class="input-group-text bg-primary text-white"><i class="bi bi-lightning-fill"></i></span>
                </div>
                <div class="form-text mt-1" style="font-size:0.65rem;">Auto-fills the list below on scan</div>
            </div>
            <p class="mb-1 fw-bold text-muted small"><i class="bi bi-list-ol me-1"></i> Serial Numbers Inventory (${qty})</p>
        `;
        
        for (let i = 0; i < qty; i++) {
            const v = vals[i] || '';
            html += `
                <div class="input-group input-group-sm mb-1 serial-field-wrap">
                    <span class="input-group-text px-2" style="font-size:0.6rem; min-width:30px;">#${i+1}</span>
                    <input type="text" name="serials[${itemId}][]" class="form-control individual-serial" placeholder="Reference S/N" value="${v}">
                </div>`;
        }
        container.innerHTML = html;
        
        // Focus the scanner input if it was just added and it's the only asset
        const scanField = container.querySelector('.scanner-input');
        if(scanField) scanField.focus();
    }

    // RAPID SCAN LOGIC
    cartTbody.addEventListener('keydown', function(e) {
        if (e.target.classList.contains('scanner-input') && e.key === 'Enter') {
            e.preventDefault();
            const val = e.target.value.trim();
            if (!val) return;

            const itemId = e.target.getAttribute('data-item-id');
            const container = e.target.closest('.serial-inputs');
            const individualInputs = container.querySelectorAll('.individual-serial');
            
            // Duplicate Check
            let isDuplicate = false;
            individualInputs.forEach(input => {
                if(input.value.trim().toLowerCase() === val.toLowerCase()) isDuplicate = true;
            });

            if(isDuplicate) {
                alert('This Serial Number has already been scanned for this item.');
                e.target.value = '';
                return;
            }

            // Find first empty
            let found = false;
            for (let input of individualInputs) {
                if (!input.value.trim()) {
                    input.value = val;
                    input.classList.add('is-valid-scan');
                    setTimeout(() => input.classList.remove('is-valid-scan'), 1000);
                    found = true;
                    break;
                }
            }

            if (!found) {
                alert('All serial slots for this item are already filled. Increase quantity to add more.');
            }

            e.target.value = '';
            e.target.focus();
        }
    });

    cartTbody.addEventListener('input', function(e) {
        if (e.target.classList.contains('cart-qty')) {
            const id = e.target.getAttribute('data-id');
            const cat = e.target.getAttribute('data-category');
            if (cat === 'Fixed Assets') {
                renderSerialInputs(e.target.closest('tr'), id, e.target.value);
            }
        }
    });

    // Make <details> chevron spin
    const details = document.getElementById('optionalDetails');
    const summary = details.querySelector('summary i');
    details.addEventListener('toggle', function() {
        if (details.open) {
            summary.classList.replace('bi-chevron-down', 'bi-chevron-up');
        } else {
            summary.classList.replace('bi-chevron-up', 'bi-chevron-down');
        }
    });
    
    // Auto-load items from URL (Single or Multiple Selection support)
    const urlParams = new URLSearchParams(window.location.search);
    let itemIdsParam = urlParams.get('item_ids') || urlParams.get('item_id');
    
    if (itemIdsParam) {
        const ids = itemIdsParam.split(',');
        ids.forEach(id => {
            const opt = Array.from(selector.options).find(o => o.value == id);
            if (opt) {
                $(selector).val(id).trigger('change');
                addBtn.click();
            }
        });
    }
});
</script>

<?php require_once '../partials/footer.php'; ?>