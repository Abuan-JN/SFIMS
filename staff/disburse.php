<?php
/**
 * Stock Disbursement Module
 * 
 * Records the issuance of items to specific departments or rooms.
 * 1. Checks for sufficient stock availability.
 * 2. If 'Fixed Asset', requires selection of specific physical instances via barcode.
 * 3. Updates asset status to 'issued' and assigns location/department.
 * 4. Decrements global inventory count.
 * 5. Generates multiple transaction records for each fixed asset instance.
 * 6. Provides a link to print the Disbursement Form.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Check: Ensure the user has permissions to record distributions
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Handle the disbursement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $item_id = (int) $_POST['item_id'];
    $type = 'DISBURSE';
    $quantity = (int) $_POST['quantity'];
    $date = $_POST['date'] ?: date('Y-m-d');
    $dept_id = (int) $_POST['department_id'];
    $room_id = (int) ($_POST['room_id'] ?? 0);
    $recipient = trim($_POST['recipient_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $user_id = $_SESSION['user_id'];
    $transaction_ids = [];

    if ($item_id && $quantity > 0 && $dept_id) {
        try {
            // Begin transaction to ensure consistency across multiple tables
            $db->beginTransaction();

            $stmt = $db->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
            $stmt->execute([$item_id]);
            $item = $stmt->fetch();

            if (!$item) throw new Exception("Item not found.");
            
            // Safety Check: Verify stock is available before proceeding
            if ($item['current_quantity'] < $quantity) throw new Exception("Insufficient stock (Available: " . $item['current_quantity'] . ").");

            // Category Specific Logic: Fixed Assets require individual tracking
            if ($item['category_name'] === 'Fixed Assets') {
                $instance_ids = $_POST['instance_ids'] ?? [];
                // Validation: Ensure the user selected the exact number of physical units
                if (count($instance_ids) !== $quantity) throw new Exception("Please select exactly $quantity asset instances.");
                if (!$room_id) throw new Exception("Room location is required for fixed assets.");

                foreach ($instance_ids as $inst_id) {
                    // Update the status and assignment of each physical unit
                    $stmt = $db->prepare("UPDATE item_instances SET status = 'issued', assigned_department_id = ?, room_id = ?, assigned_person = ?, contact_number = ? WHERE id = ? AND status = 'in-stock'");
                    $stmt->execute([$dept_id, $room_id, $recipient, $contact_number, $inst_id]);

                    if ($stmt->rowCount() === 0) throw new Exception("Asset instance ID $inst_id is either not found or already issued.");

                    // Record an individual transaction record for each unique asset unit
                    $stmt = $db->prepare("INSERT INTO transactions (item_id, instance_id, type, quantity, date, department_id, room_id, recipient_name, contact_number, remarks, performed_by) VALUES (?, ?, 'DISBURSE', 1, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$item_id, $inst_id, $date, $dept_id, $room_id, $recipient, $contact_number, $remarks, $user_id]);
                    $transaction_ids[] = $db->lastInsertId();
                }
            } else {
                // Consumables Logic: Record as a single bulk disbursement
                $stmt = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, department_id, recipient_name, contact_number, remarks, performed_by) VALUES (?, 'DISBURSE', ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$item_id, $quantity, $date, $dept_id, $recipient, $contact_number, $remarks, $user_id]);
                $transaction_ids[] = $db->lastInsertId();
            }

            // Sync the global inventory levels
            $stmt = $db->prepare("UPDATE items SET current_quantity = current_quantity - ? WHERE id = ?");
            $stmt->execute([$quantity, $item_id]);

            // Audit Trail Log
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'DISBURSE', 'Item', ?, ?)");
            $logStmt->execute([$user_id, $item_id, "Disbursed $quantity " . $item['uom'] . " to recipient: $recipient"]);

            // Commit all database changes
            $db->commit();
            set_flash_message('success', 'Stock disbursed successfully.');
            // Allow printing of the form using the gathered transaction IDs
            $redirect_params = http_build_query(['success_ids' => $transaction_ids]);
            redirect('staff/disburse.php?' . $redirect_params);

        } catch (Exception $e) {
            // Roll back all changes if an error occurs during the multi-step process
            $db->rollBack();
            $error = $e->getMessage();
        }
    } else {
        $error = "Please fill in all required fields.";
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
        <div class="card shadow-sm border-0">
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
                    <div class="row g-3 mb-4">
                        <div class="col-md-8">
                            <label class="form-label fw-bold">Item to Disburse</label>
                            <select name="item_id" id="item_id" class="form-select select2" required>
                                <option value="">Select Item</option>
                                <?php foreach ($items as $it): ?>
                                    <option value="<?php echo $it['id']; ?>" data-category="<?php echo h($it['category_name']); ?>" data-stock="<?php echo $it['current_quantity']; ?>">
                                        <?php echo h($it['name']); ?> (Avl: <?php echo $it['current_quantity']; ?> <?php echo h($it['uom']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Quantity</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                        </div>
                    </div>

                    <div id="assetSelectionSection" class="d-none card bg-light mb-4 border-0">
                        <div class="card-body">
                            <h6 class="fw-bold text-primary mb-3">Fixed Asset Instance Selection</h6>
                            <div class="input-group mb-3">
                                <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
                                <input type="text" id="barcodeSearch" class="form-control" placeholder="Scan Barcode to Select Item Rapidly...">
                            </div>
                            <div id="instanceList" class="row g-2" style="max-height: 250px; overflow-y: auto;">
                                <!-- Instances loaded via AJAX -->
                            </div>
                            <div class="form-text mt-2">Select exactly the quantity specified above.</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Department</label>
                            <select name="department_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $d): ?>
                                    <option value="<?php echo $d['id']; ?>"><?php echo h($d['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6" id="roomSelectionDiv">
                            <label class="form-label fw-bold">Assign Room Location</label>
                            <select name="room_id" id="room_id" class="form-select">
                                <option value="">Select Room (Optional for Consumables)</option>
                                <?php
                                $all_rooms = $db->query("SELECT r.*, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();
                                foreach ($all_rooms as $r): ?>
                                    <option value="<?php echo $r['id']; ?>"><?php echo h($r['building_name']); ?> - <?php echo h($r['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Recipient Name</label>
                            <input type="text" name="recipient_name" class="form-control" placeholder="e.g., Juan Dela Cruz" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Contact Number</label>
                            <input type="text" name="contact_number" class="form-control" placeholder="e.g., 09123456789">
                        </div>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-4">
                        <a href="../inventory/items.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary px-4">Confirm Disbursement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const itemSelect = document.getElementById('item_id');
    const quantityInput = document.getElementById('quantity');
    const assetSection = document.getElementById('assetSelectionSection');
    const instanceList = document.getElementById('instanceList');
    const barcodeSearch = document.getElementById('barcodeSearch');
    const roomIdSelect = document.getElementById('room_id');

    function loadInstances(itemId) {
        if(!itemId) return;
        instanceList.innerHTML = '<div class="col-12 text-center p-3">Loading...</div>';
        fetch(`get_instances.php?item_id=${itemId}&status=in-stock`)
            .then(r => r.json())
            .then(data => {
                let html = '';
                if(data.length > 0) {
                    data.forEach(inst => {
                        html += `
                            <div class="col-md-6">
                                <div class="form-check border p-2 bg-white rounded">
                                    <input class="form-check-input ms-0 me-2" type="checkbox" name="instance_ids[]" value="${inst.id}" id="inst_${inst.id}" data-barcode="${inst.barcode_value}">
                                    <label class="form-check-label" for="inst_${inst.id}">
                                        <strong>${inst.barcode_value}</strong><br>
                                        <small class="text-muted">${inst.serial_number || 'No Serial'}</small>
                                    </label>
                                </div>
                            </div>
                        `;
                    });
                } else {
                    html = '<div class="col-12 text-center p-3 text-danger">No available instances.</div>';
                }
                instanceList.innerHTML = html;
            });
    }

    itemSelect.addEventListener('change', function() {
        const cat = this.options[this.selectedIndex].getAttribute('data-category');
        if(cat === 'Fixed Assets') {
            assetSection.classList.remove('d-none');
            roomIdSelect.required = true;
            quantityInput.readOnly = true; // Auto-calculated
            quantityInput.value = ''; // Reset until selected
            loadInstances(this.value);
        } else {
            assetSection.classList.add('d-none');
            roomIdSelect.required = false;
            quantityInput.readOnly = false;
        }
    });

    // Delegate event listener for dynamically loaded checkboxes
    instanceList.addEventListener('change', function(e) {
        if(e.target && e.target.type === 'checkbox') {
            const checkedCount = document.querySelectorAll('input[name="instance_ids[]"]:checked').length;
            quantityInput.value = checkedCount > 0 ? checkedCount : '';
        }
    });

    barcodeSearch.addEventListener('input', function() {
        const val = this.value.trim().toLowerCase();
        if(!val) return;
        const checkboxes = document.querySelectorAll('input[name="instance_ids[]"]');
        checkboxes.forEach(cb => {
            if(cb.getAttribute('data-barcode').toLowerCase() === val && !cb.checked) {
                cb.checked = true;
                this.value = '';
                // Highlight or sound feedback could be added here
                
                // Trigger change event to update quantity
                const event = new Event('change', { bubbles: true });
                cb.dispatchEvent(event);
            }
        });
    });

    document.getElementById('disburseForm').addEventListener('submit', function(e) {
        const cat = itemSelect.options[itemSelect.selectedIndex].getAttribute('data-category');
        if(cat === 'Fixed Assets') {
            const checked = document.querySelectorAll('input[name="instance_ids[]"]:checked').length;
            if(checked != quantityInput.value) {
                e.preventDefault();
                alert(`Please select exactly ${quantityInput.value} instances.`);
            }
        }
    });
});
</script>

<?php require_once '../partials/footer.php'; ?>
