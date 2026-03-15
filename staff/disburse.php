<?php
/**
 * Stock Bulk Disbursement Module
 * 
 * Records the issuance of items to specific departments or rooms via a Cart system.
 * 1. Checks for sufficient stock availability.
 * 2. Processes Consumables by bulk quantity reduction.
 * 3. Processes Fixed Assets by updating exact physical instances to 'issued'.
 * 4. Decrements global inventory counts for each item type.
 * 5. Generates multiple transaction records for auditing.
 * 6. Provides a link to print the unified Disbursement Form.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Check: Ensure the user has permissions to record distributions
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Handle the bulk disbursement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $type = 'DISBURSE';
    $date = $_POST['date'] ?: date('Y-m-d');
    $dept_id = (int) $_POST['department_id'];
    $room_id = !empty($_POST['room_id']) ? (int) $_POST['room_id'] : null;
    $recipient = trim($_POST['recipient_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $user_id = $_SESSION['user_id'];
    
    $c_item_ids = $_POST['c_item_ids'] ?? [];
    $c_quants = $_POST['c_quants'] ?? [];
    $f_instance_ids = $_POST['f_instance_ids'] ?? []; // Format: array of raw instance IDs
    
    $transaction_ids = [];

    if ($dept_id && (!empty($c_item_ids) || !empty($f_instance_ids))) {
        try {
            // Begin transaction to ensure consistency across multiple tables and items
            $db->beginTransaction();

            // 1. Process Consumables
            for ($i = 0; $i < count($c_item_ids); $i++) {
                $item_id = (int) $c_item_ids[$i];
                $quantity = (int) $c_quants[$i];

                if ($quantity <= 0) continue;

                $stmt = $db->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch();

                if (!$item || $item['category_name'] === 'Fixed Assets') throw new Exception("Invalid consumable item ID $item_id.");
                if ($item['current_quantity'] < $quantity) throw new Exception("Insufficient stock for {$item['name']} (Available: {$item['current_quantity']}).");

                // Record as a single bulk disbursement for this item
                $stmt = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, department_id, recipient_name, contact_number, remarks, performed_by) VALUES (?, 'DISBURSE', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $quantity, $date, $dept_id, $recipient, $contact_number, $remarks, $user_id]);
                $transaction_ids[] = $db->lastInsertId();

                // Sync the global inventory levels
                $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity - ? WHERE id = ?");
                $stmt->execute([$quantity, $item_id]);

                // Audit Trail Log
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'DISBURSE', 'Item', ?, ?)");
                $logStmt->execute([$user_id, $item_id, "Disbursed $quantity {$item['uom']} of {$item['name']} to $recipient"]);
            }

            // 2. Process Fixed Assets automatically
            // Group instance IDs by item_id so we can batch process them and avoid duplicate queries
            $asset_groups = [];
            foreach ($f_instance_ids as $inst_id) {
                // Fetch the item_id for this instance
                $stmt = $db->prepare("SELECT item_id, status FROM item_instances WHERE id = ?");
                $stmt->execute([$inst_id]);
                $instance_data = $stmt->fetch();
                
                if (!$instance_data || $instance_data['status'] !== 'in-stock') {
                    throw new Exception("Asset instance ID $inst_id is either not found or already issued.");
                }
                
                $i_id = $instance_data['item_id'];
                if (!isset($asset_groups[$i_id])) {
                    $asset_groups[$i_id] = [];
                }
                $asset_groups[$i_id][] = $inst_id;
            }

            foreach ($asset_groups as $item_id => $instances) {
                $quantity = count($instances);

                $stmt = $db->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
                $stmt->execute([$item_id]);
                $item = $stmt->fetch();

                if (!$item || $item['current_quantity'] < $quantity) {
                    throw new Exception("Insufficient stock for Fixed Asset {$item['name']}.");
                }

                foreach ($instances as $inst_id) {
                    // Update the status and assignment of each physical unit
                    $stmt = $db->prepare("UPDATE item_instances SET status = 'issued', assigned_department_id = ?, room_id = ?, assigned_person = ?, contact_number = ? WHERE id = ?");
                    $stmt->execute([$dept_id, $room_id, $recipient, $contact_number, $inst_id]);

                    // Record an individual transaction record for each unique asset unit
                    $stmt = $db->prepare("INSERT INTO transactions (item_id, instance_id, type, quantity, date, department_id, room_id, recipient_name, contact_number, remarks, performed_by) VALUES (?, ?, 'DISBURSE', 1, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$item_id, $inst_id, $date, $dept_id, $room_id, $recipient, $contact_number, $remarks, $user_id]);
                    $transaction_ids[] = $db->lastInsertId();
                }

                // Sync the global inventory levels
                $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity - ? WHERE id = ?");
                $stmt->execute([$quantity, $item_id]);

                // Audit Trail Log
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'DISBURSE', 'Item', ?, ?)");
                $logStmt->execute([$user_id, $item_id, "Disbursed $quantity unit(s) of {$item['name']} to $recipient"]);
            }

            // Commit all database changes
            $db->commit();
            set_flash_message('success', 'Bulk stock disbursed successfully.');
            // Allow printing of the form using the gathered transaction IDs
            $redirect_params = http_build_query(['success_ids' => $transaction_ids]);
            redirect('staff/disburse.php?' . $redirect_params);

        } catch (Exception $e) {
            // Roll back all changes if an error occurs during the multi-step process
            $db->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields and add at least one item to the cart.";
    }
}

$items = $db->query("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.current_quantity > 0 ORDER BY i.name ASC")->fetchAll();
$departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
$buildings = $db->query("SELECT * FROM buildings ORDER BY name ASC")->fetchAll();

$page_title = 'Disburse Items';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Record Stock Disbursement</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['success_ids'])): ?>
                    <div class="alert alert-success d-flex justify-content-between align-items-center">
                        <div><i class="bi bi-check-circle me-1"></i> Disbursement recorded successfully.</div>
                        <?php 
                        $print_params = http_build_query(['ids' => $_GET['success_ids']]);
                        ?>
                        <a href="disburse_print.php?<?php echo $print_params; ?>" target="_blank" class="btn btn-sm btn-success"><i class="bi bi-printer me-1"></i> Print Form</a>
                    </div>
                <?php endif; ?>

                <form method="POST" action="" id="disburseForm">
                    <?php csrf_field(); ?>
                    
                    <div class="bg-light p-3 rounded mb-4 border">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="fw-bold mb-0"><i class="bi bi-cart-dash me-2"></i>Add Items to Disburse List</h6>
                        </div>
                        <div class="row g-2 align-items-start">
                            <div class="col-md-7">
                                <label class="form-label small fw-bold">Select Item</label>
                                <select id="itemSelector" class="form-select select2">
                                    <option value="">Search and select...</option>
                                    <?php foreach ($items as $it): ?>
                                        <option value="<?php echo $it['id']; ?>" data-name="<?php echo h($it['name']); ?>" data-category="<?php echo h($it['category_name']); ?>" data-uom="<?php echo h($it['uom']); ?>" data-stock="<?php echo $it['current_quantity']; ?>">
                                            <?php echo h($it['name']); ?> (Avl: <?php echo $it['current_quantity']; ?> <?php echo h($it['uom']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Consumables Quick Add -->
                            <div class="col-md-5 d-none row g-2" id="consumableAddGroup">
                                <div class="col-8">
                                    <label class="form-label small fw-bold">Quantity</label>
                                    <input type="number" id="qtySelector" class="form-control" min="1" value="1">
                                </div>
                                <div class="col-4 d-grid align-items-end">
                                    <button type="button" class="btn btn-primary h-100" style="margin-top: 28px;" id="addConsumableBtn">Add</button>
                                </div>
                            </div>
                            
                        </div>

                        <!-- Fixed Asset Scanner Add -->
                        <div id="assetSelectionSection" class="d-none mt-3 p-3 bg-white border rounded">
                            <h6 class="fw-bold text-primary mb-3 small text-uppercase">Fixed Asset Instance Selection</h6>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" id="barcodeSearch" class="form-control" placeholder="Scan Barcode to Select Item Rapidly...">
                            </div>
                            <div id="instanceList" class="row g-2" style="max-height: 200px; overflow-y: auto;">
                                <!-- Instances loaded via AJAX -->
                            </div>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-primary btn-sm px-4" id="addFixedAssetsBtn">Add Selected Instances to Cart</button>
                            </div>
                        </div>
                    </div>

                    <!-- The Cart Array -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered table-hover align-middle" id="cartTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Item Name / Details</th>
                                    <th>Category</th>
                                    <th style="width: 120px;">Quantity</th>
                                    <th style="width: 80px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr id="emptyCartRow">
                                    <td colspan="4" class="text-center text-muted py-4">No items added yet. Please select an item to begin.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Department <span class="text-danger">*</span></label>
                            <select name="department_id" class="form-select select2" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo h($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 text-end">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- Optional Tracking Data Collapse -->
                    <details class="mb-4 border rounded bg-light" id="optionalDetails">
                        <summary class="p-3 fw-bold fw-semibold" style="cursor: pointer; list-style: none;">
                            <i class="bi bi-chevron-down me-2"></i> Delivery & Room Logging Info (Optional)
                        </summary>
                        <div class="p-3 border-top bg-white row g-3">
                            <div class="col-md-12" id="roomSelectionDiv">
                                <label class="form-label fw-bold">Assign Room Location</label>
                                <select name="room_id" id="room_id" class="form-select select2">
                                    <option value="">Select Room (Highly Recommended for Fixed Assets)</option>
                                    <?php
                                    $all_rooms = $db->query("SELECT r.*, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();
                                    foreach ($all_rooms as $r): ?>
                                        <option value="<?php echo $r['id']; ?>"><?php echo h($r['building_name']); ?> - <?php echo h($r['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Recipient Name</label>
                                <input type="text" name="recipient_name" class="form-control" placeholder="e.g., Juan Dela Cruz">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control" placeholder="e.g., 09123456789">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold">Remarks</label>
                                <textarea name="remarks" class="form-control" rows="2"></textarea>
                            </div>
                        </div>
                    </details>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <a href="../inventory/items.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="bi bi-check-circle me-1"></i> Confirm Disbursement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('itemSelector');
    
    // UI Elements
    const consumableGroup = document.getElementById('consumableAddGroup');
    const assetSection = document.getElementById('assetSelectionSection');
    
    const qtyInput = document.getElementById('qtySelector');
    const addConsumableBtn = document.getElementById('addConsumableBtn');
    
    const instanceList = document.getElementById('instanceList');
    const barcodeSearch = document.getElementById('barcodeSearch');
    const addFixedAssetsBtn = document.getElementById('addFixedAssetsBtn');
    
    const cartTbody = document.getElementById('cartTable').querySelector('tbody');
    const emptyRow = document.getElementById('emptyCartRow');

    function loadInstances(itemId) {
        if(!itemId) return;
        instanceList.innerHTML = '<div class="col-12 text-center p-3">Loading physical units...</div>';
        fetch(`get_instances.php?item_id=${itemId}&status=in-stock`)
            .then(r => r.json())
            .then(data => {
                let html = '';
                if(data.length > 0) {
                    data.forEach(inst => {
                        // Check if this instance is already in the cart so we don't display it
                        if (!document.querySelector(`input[name="f_instance_ids[]"][value="${inst.id}"]`)) {
                            html += `
                                <div class="col-md-6">
                                    <div class="form-check border p-2 bg-white rounded">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" value="${inst.id}" id="inst_${inst.id}" data-barcode="${inst.barcode_value}">
                                        <label class="form-check-label w-100" style="cursor:pointer;" for="inst_${inst.id}">
                                            <strong>${inst.barcode_value}</strong><br>
                                            <span class="text-muted small">${inst.serial_number || 'No Serial'}</span>
                                        </label>
                                    </div>
                                </div>
                            `;
                        }
                    });
                }
                if (!html) {
                    html = '<div class="col-12 text-center p-3 text-warning">All available instances for this item are either checked out or already in your cart.</div>';
                }
                instanceList.innerHTML = html;
            });
    }

    selector.addEventListener('change', function() {
        const option = this.options[this.selectedIndex];
        if (!option.value) {
            consumableGroup.classList.add('d-none');
            assetSection.classList.add('d-none');
            return;
        }

        const cat = option.getAttribute('data-category');
        const maxStock = parseInt(option.getAttribute('data-stock'));
        
        if (cat === 'Fixed Assets') {
            consumableGroup.classList.add('d-none');
            assetSection.classList.remove('d-none');
            loadInstances(this.value);
        } else {
            consumableGroup.classList.remove('d-none');
            assetSection.classList.add('d-none');
            qtyInput.max = maxStock;
            qtyInput.value = 1;
        }
    });

    // Handle Adding Consumables to Cart
    addConsumableBtn.addEventListener('click', function() {
        const option = selector.options[selector.selectedIndex];
        const id = selector.value;
        const name = option.getAttribute('data-name');
        const uom = option.getAttribute('data-uom');
        const maxStock = parseInt(option.getAttribute('data-stock'));
        const qty = parseInt(qtyInput.value);

        if (qty <= 0 || qty > maxStock) {
            alert(`Please enter a valid quantity (1 to ${maxStock}).`);
            return;
        }

        const existingRow = document.getElementById('cart-c-row-' + id);
        if (existingRow) {
            const currentObj = existingRow.querySelector('.cart-qty');
            let currentQty = parseInt(currentObj.value);
            if (currentQty + qty > maxStock) {
                alert(`Cannot exceed available stock (${maxStock}). You already have ${currentQty} in the cart.`);
                return;
            }
            currentObj.value = currentQty + qty;
        } else {
            const tr = document.createElement('tr');
            tr.id = 'cart-c-row-' + id;
            tr.innerHTML = `
                <td>
                    <strong>${name}</strong>
                    <input type="hidden" name="c_item_ids[]" value="${id}">
                </td>
                <td><span class="badge bg-secondary">Consumable</span></td>
                <td>
                    <div class="input-group input-group-sm">
                        <input type="number" name="c_quants[]" class="form-control cart-qty" value="${qty}" min="1" max="${maxStock}" readonly>
                        <span class="input-group-text">${uom}</span>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-btn"><i class="bi bi-x-lg"></i></button>
                </td>
            `;
            cartTbody.appendChild(tr);

            tr.querySelector('.remove-btn').addEventListener('click', function() {
                tr.remove();
                checkEmptyCart();
            });
            emptyRow.style.display = 'none';
        }

        $(selector).val(null).trigger('change');
    });

    // Handle Quick Scan Barcode Selection
    barcodeSearch.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        if(!val) return;
        const checkboxes = instanceList.querySelectorAll('input[type="checkbox"]');
        checkboxes.forEach(cb => {
            if (cb.getAttribute('data-barcode').toLowerCase() === val && !cb.checked) {
                cb.checked = true;
                this.value = ''; // clear
                // If using a scanner, wait a tiny bit then auto-click Add
                setTimeout(() => addFixedAssetsBtn.click(), 100);
            }
        });
    });

    // Prevent 'Enter' key inside barcode scanner from submitting the form
    barcodeSearch.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault(); // Stop form submission
            // If there's an exact match already checked, click Add
            addFixedAssetsBtn.click();
        }
    });

    // Handle Adding Fixed Assets to Cart
    addFixedAssetsBtn.addEventListener('click', function() {
        const option = selector.options[selector.selectedIndex];
        const name = option.getAttribute('data-name');
        let addedCount = 0;

        instanceList.querySelectorAll('input[type="checkbox"]:checked').forEach(cb => {
            const instId = cb.value;
            const barcode = cb.getAttribute('data-barcode');
            
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <strong>${name}</strong>
                    <div class="small text-muted"><i class="bi bi-upc-scan me-1"></i>${barcode}</div>
                    <input type="hidden" name="f_instance_ids[]" value="${instId}">
                </td>
                <td><span class="badge bg-light text-dark border">Fixed Asset</span></td>
                <td>
                    <span class="badge bg-primary px-3 py-2">1 Unit</span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-danger remove-btn"><i class="bi bi-x-lg"></i></button>
                </td>
            `;
            cartTbody.appendChild(tr);

            tr.querySelector('.remove-btn').addEventListener('click', function() {
                tr.remove();
                checkEmptyCart();
            });
            
            addedCount++;
        });

        if (addedCount > 0) {
            emptyRow.style.display = 'none';
            // Reload the instances display to hide carted items
            loadInstances(selector.value); 
        } else {
            alert("No units selected to add.");
        }
    });

    function checkEmptyCart() {
        if (cartTbody.querySelectorAll('tr').length === 1) { // Only emptyRow is left
            emptyRow.style.display = '';
        }
    }

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
    
    // Prevent form submission if empty
    document.getElementById('disburseForm').addEventListener('submit', function(e) {
        if (cartTbody.querySelectorAll('tr:not(#emptyCartRow)').length === 0) {
            alert('Please add at least one item to the list before confirming disbursement.');
            e.preventDefault();
        }
    });
});
</script>

<?php require_once '../partials/footer.php'; ?>
