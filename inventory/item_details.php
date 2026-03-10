<?php
/**
 * Item Details Module
 * 
 * Displays comprehensive information for a specific inventory item, including:
 * 1. Basic specifications (UOM, Category, Description).
 * 2. Real-time stock levels.
 * 3. Individual asset instances (for Fixed Assets, including barcodes).
 * 4. Recent transaction history (Receipts, Disbursements, Movements).
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection: Redirect to login if user session is invalid
require_role();

// Validate Item ID from URL
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    set_flash_message('danger', 'Invalid item ID.');
    redirect('inventory/items.php');
}

$db = Database::getInstance();

// 1. Fetch Basic Item Info
$stmt = $db->prepare("SELECT i.*, c.name as category_name FROM items i LEFT JOIN categories c ON i.category_id = c.id WHERE i.id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash_message('danger', 'Item not found.');
    redirect('inventory/items.php');
}

// 2. Fetch Individual Instances (Applicable only for 'Fixed Assets' category)
$instances = [];
if ($item['category_name'] === 'Fixed Assets') {
    // Join with barcodes and location tables to provide a complete status of each asset
    $stmt = $db->prepare("SELECT ii.*, d.name as dept_name, r.name as room_name, b.name as building_name, bc.barcode_value
                          FROM item_instances ii 
                          JOIN barcodes bc ON ii.barcode_id = bc.id
                          LEFT JOIN departments d ON ii.assigned_department_id = d.id 
                          LEFT JOIN rooms r ON ii.room_id = r.id 
                          LEFT JOIN buildings b ON r.building_id = b.id
                          WHERE ii.item_id = ? ORDER BY ii.id DESC");
    $stmt->execute([$id]);
    $instances = $stmt->fetchAll();
}

// 3. Fetch Recent Transaction History (limit to latest 20 for performance)
$stmt = $db->prepare("SELECT t.*, u.full_name as user_name, d.name as dept_name 
                      FROM transactions t 
                      LEFT JOIN users u ON t.performed_by = u.id 
                      LEFT JOIN departments d ON t.department_id = d.id
                      WHERE t.item_id = ? 
                      ORDER BY t.created_at DESC LIMIT 20");
$stmt->execute([$id]);
$transactions = $stmt->fetchAll();

$page_title = 'Item Details - ' . $item['name'];
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-8">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="items.php">Inventory</a></li>
                <li class="breadcrumb-item active">
                    <?php echo h($item['name']); ?>
                </li>
            </ol>
        </nav>
        <h2 class="fw-bold">
            <?php echo h($item['name']); ?>
        </h2>
    </div>
    <div class="col-md-4 d-flex justify-content-md-end gap-2 flex-nowrap">
        <?php if (in_array($_SESSION['role'], ['Admin', 'Inventory Staff'])): ?>
            <a href="../staff/items_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-outline-secondary"><i
                    class="bi bi-pencil me-1"></i> Edit</a>
            <a href="../staff/receive.php?item_id=<?php echo $item['id']; ?>" class="btn btn-primary"><i
                    class="bi bi-plus-lg me-1"></i> Receive</a>
            <a href="../staff/disburse.php?item_id=<?php echo $item['id']; ?>" class="btn btn-warning"><i
                    class="bi bi-box-arrow-right me-1"></i> Disburse</a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Item Column -->
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Item Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="text-muted small text-uppercase fw-bold">Description</label>
                    <p class="mb-0">
                        <?php echo h($item['description'] ?: 'No description provided.'); ?>
                    </p>
                </div>
                <hr class="text-light">
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="text-muted small text-uppercase fw-bold">Category</label>
                        <p class="mb-0"><span class="badge bg-info text-dark">
                                <?php echo h($item['category_name']); ?>
                            </span></p>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="text-muted small text-uppercase fw-bold">UOM</label>
                        <p class="mb-0">
                            <?php echo h($item['uom']); ?>
                        </p>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <label class="text-muted small text-uppercase fw-bold">Threshold</label>
                        <p class="mb-0">
                            <?php echo $item['threshold_quantity']; ?>
                        </p>
                    </div>
                    <div class="col-6">
                        <label class="text-muted small text-uppercase fw-bold">Stock Level</label>
                        <p class="mb-0">
                            <?php if ($item['current_quantity'] == 0): ?>
                                <span class="badge bg-secondary">Out of Stock</span>
                            <?php elseif ($item['current_quantity'] <= $item['threshold_quantity']): ?>
                                <span class="badge bg-danger">Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-success">In Stock</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 bg-primary text-white">
            <div class="card-body text-center py-4">
                <h6 class="text-uppercase small mb-2 opacity-75">Current Inventory</h6>
                <h1 class="display-3 fw-bold mb-0">
                    <?php echo $item['current_quantity']; ?>
                </h1>
                <p class="mb-0 opacity-75">
                    <?php echo h($item['uom']); ?>(s) available
                </p>
                <?php if ($item['current_quantity'] <= $item['threshold_quantity']): ?>
                    <div class="mt-3 badge bg-danger p-2"><i class="bi bi-exclamation-triangle me-1"></i> Low Stock Alert
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- History/Instances Column -->
    <div class="col-lg-8">
        <?php if ($item['category_name'] === 'Fixed Assets'): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                    <h5 class="card-title mb-0">Asset Instances (Barcodes)</h5>
                    <a href="barcode_print.php?item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-dark"
                        target="_blank"><i class="bi bi-printer me-1"></i> Print All</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Barcode</th>
                                    <th>Serial No.</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th class="text-end pe-4">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($instances): ?>
                                    <?php foreach ($instances as $instance): ?>
                                        <tr>
                                            <td class="ps-4 fw-mono">
                                                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($instance['barcode_value']); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Png&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0" alt="<?php echo h($instance['barcode_value']); ?>" style="max-height: 40px; border-radius: 4px; background: white; padding: 2px;">
                                                <div class="small text-muted mt-1"><?php echo h($instance['barcode_value']); ?></div>
                                            </td>
                                            <td>
                                                <?php echo h($instance['serial_number'] ?: '--'); ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php
                                                echo match ($instance['status']) {
                                                    'in-stock' => 'success',
                                                    'issued' => 'primary',
                                                    'under repair' => 'warning',
                                                    'disposed' => 'danger',
                                                    'lost' => 'dark',
                                                    default => 'secondary'
                                                };
                                                ?>">
                                                    <?php echo ucfirst($instance['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo h($instance['dept_name'] ?: '--'); ?>
                                                <?php if($instance['room_name']): ?>
                                                    <br><small class="text-muted"><?php echo h($instance['building_name'] . ' - ' . $instance['room_name']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end pe-4">
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                        Actions
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li><a class="dropdown-item" href="../staff/instance_edit.php?id=<?php echo $instance['id']; ?>"><i class="bi bi-pencil me-2"></i>Edit Details</a></li>
                                                        <?php if ($instance['status'] === 'issued'): ?>
                                                            <li><a class="dropdown-item" href="../staff/move.php?id=<?php echo $instance['id']; ?>"><i class="bi bi-arrows-move me-2"></i>Move Location</a></li>
                                                        <?php endif; ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="../staff/condemn.php?id=<?php echo $instance['id']; ?>"><i class="bi bi-trash me-2"></i>Condemn Asset</a></li>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No instances found. Receive stock to
                                            generate barcodes.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Recent Transaction History</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="ps-4">Date</th>
                                <th>Type</th>
                                <th>Qty</th>
                                <th>Dept/Person</th>
                                <th>Performed By</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($transactions): ?>
                                <?php foreach ($transactions as $tx): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <?php echo date('M d, Y', strtotime($tx['date'])); ?>
                                        </td>
                                        <td>
                                            <span
                                                class="badge <?php echo $tx['type'] === 'RECEIVE' ? 'bg-success' : ($tx['type'] === 'DISBURSE' ? 'bg-danger' : ($tx['type'] === 'MOVE' ? 'bg-info' : 'bg-secondary')); ?>">
                                                <?php echo $tx['type']; ?>
                                            </span>
                                        </td>
                                        <td class="fw-bold">
                                            <?php echo $tx['quantity']; ?>
                                        </td>
                                        <td>
                                            <?php 
                                            if ($tx['type'] === 'RECEIVE') {
                                                echo h($tx['source_supplier'] ?: '--');
                                            } else {
                                                echo h($tx['dept_name'] ?: ($tx['recipient_name'] ?: '--'));
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo h($tx['user_name']); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center py-4 text-muted">No transactions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="card-footer bg-white text-center py-3">
                <a href="transactions.php?item_id=<?php echo $item['id']; ?>" class="text-decoration-none small">View
                    All Transactions <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>