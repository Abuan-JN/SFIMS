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
$instance_status_filter = $_GET['instance_status'] ?? 'all';
$inst_search = trim($_GET['inst_search'] ?? '');
$inst_page = max(1, (int)($_GET['inst_page'] ?? 1));
$inst_per_page = 20;

if ($item['category_name'] === 'Fixed Assets') {
    // Count by status for filter tabs
    $instance_counts = [];
    $count_stmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM item_instances WHERE item_id = ? GROUP BY status");
    $count_stmt->execute([$id]);
    foreach ($count_stmt->fetchAll() as $row) {
        $instance_counts[$row['status']] = $row['cnt'];
    }
    $instance_counts['all'] = array_sum($instance_counts);

    // Count filtered instances for pagination
    $count_sql = "SELECT COUNT(*) FROM item_instances ii JOIN barcodes bc ON ii.barcode_id = bc.id WHERE ii.item_id = ?";
    $count_params = [$id];
    if ($instance_status_filter !== 'all') {
        $count_sql .= " AND ii.status = ?";
        $count_params[] = $instance_status_filter;
    }
    if ($inst_search) {
        $count_sql .= " AND (bc.barcode_value LIKE ? OR ii.serial_number LIKE ?)";
        $count_params[] = "%$inst_search%";
        $count_params[] = "%$inst_search%";
    }
    $count_stmt = $db->prepare($count_sql);
    $count_stmt->execute($count_params);
    $total_instances = (int)$count_stmt->fetchColumn();
    $inst_total_pages = max(1, ceil($total_instances / $inst_per_page));
    $inst_page = min($inst_page, $inst_total_pages);
    $inst_offset = ($inst_page - 1) * $inst_per_page;

    // Join with barcodes and location tables to provide a complete status of each asset
    $inst_sql = "SELECT ii.*, d.name as dept_name, r.name as room_name, b.name as building_name, bc.barcode_value
                          FROM item_instances ii 
                          JOIN barcodes bc ON ii.barcode_id = bc.id
                          LEFT JOIN departments d ON ii.assigned_department_id = d.id 
                          LEFT JOIN rooms r ON ii.room_id = r.id 
                          LEFT JOIN buildings b ON r.building_id = b.id
                          WHERE ii.item_id = ?";
    $inst_params = [$id];
    if ($instance_status_filter !== 'all') {
        $inst_sql .= " AND ii.status = ?";
        $inst_params[] = $instance_status_filter;
    }
    if ($inst_search) {
        $inst_sql .= " AND (bc.barcode_value LIKE ? OR ii.serial_number LIKE ?)";
        $inst_params[] = "%$inst_search%";
        $inst_params[] = "%$inst_search%";
    }
    $inst_sql .= " ORDER BY ii.id DESC LIMIT $inst_per_page OFFSET $inst_offset";
    $stmt = $db->prepare($inst_sql);
    $stmt->execute($inst_params);
    $instances = $stmt->fetchAll();
}

// 3. Fetch Transaction History with server-side pagination
$tx_page = max(1, (int)($_GET['tx_page'] ?? 1));
$tx_per_page = 20;

// Count total transactions for this item
$total_tx_stmt = $db->prepare("SELECT COUNT(*) FROM transactions WHERE item_id = ?");
$total_tx_stmt->execute([$id]);
$total_transactions = (int)$total_tx_stmt->fetchColumn();
$tx_total_pages = max(1, ceil($total_transactions / $tx_per_page));
$tx_page = min($tx_page, $tx_total_pages);
$tx_offset = ($tx_page - 1) * $tx_per_page;

$stmt = $db->prepare("SELECT t.*, u.full_name as user_name, d.name as dept_name 
                      FROM transactions t 
                      LEFT JOIN users u ON t.performed_by = u.id 
                      LEFT JOIN departments d ON t.department_id = d.id
                      WHERE t.item_id = ? 
                      ORDER BY t.created_at DESC LIMIT $tx_per_page OFFSET $tx_offset");
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
                            <?php if ($item['category_name'] === 'Fixed Assets'): ?>
                                <span class="badge bg-light text-dark border">Tracked Asset</span>
                            <?php elseif ($item['current_quantity'] == 0): ?>
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
                    <div>
                        <h5 class="card-title mb-0">Asset Instances <span class="badge bg-secondary ms-1"><?php echo $instance_counts['all'] ?? 0; ?></span></h5>
                        <nav class="mt-2">
                            <?php
                            $status_tabs = [
                                'all'           => ['label' => 'All',           'color' => 'secondary'],
                                'in-stock'      => ['label' => 'In Stock',      'color' => 'success'],
                                'issued'        => ['label' => 'Issued',        'color' => 'primary'],
                                'under repair'  => ['label' => 'Under Repair',  'color' => 'warning'],
                                'condemned-serviced' => ['label' => 'Servicing','color' => 'danger'],
                                'condemned-trash'    => ['label' => 'Disposed', 'color' => 'dark'],
                            ];
                            foreach ($status_tabs as $status_key => $tab):
                                $cnt = $instance_counts[$status_key] ?? 0;
                                if ($status_key !== 'all' && $cnt === 0) continue;
                                $active = $instance_status_filter === $status_key ? 'active' : '';
                                // Ensure text is dark when not active for contrast, or white when active
                                $text_class = $active ? 'text-white' : 'text-dark';
                            ?>
                            <a class="btn btn-sm btn-outline-<?php echo $tab['color']; ?> me-1 <?php echo $active; ?> <?php echo $text_class; ?> py-0"
                                href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($status_key); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=1&tx_page=<?php echo $tx_page; ?>">
                                <?php echo $tab['label']; ?>
                                <span class="badge bg-<?php echo $tab['color']; ?> ms-1"><?php echo $cnt; ?></span>
                            </a>
                            <?php endforeach; ?>
                        </nav>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <form action="" method="GET" class="d-flex gap-1 align-items-center">
                            <input type="hidden" name="id" value="<?php echo $id; ?>">
                            <input type="hidden" name="instance_status" value="<?php echo h($instance_status_filter); ?>">
                            <div class="input-group input-group-sm" style="max-width: 250px;">
                                <input type="text" name="inst_search" class="form-control" placeholder="Search barcode/serial..." value="<?php echo h($inst_search); ?>">
                                <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i></button>
                                <?php if($inst_search): ?>
                                    <a href="?id=<?php echo $id; ?>&instance_status=<?php echo h($instance_status_filter); ?>&inst_page=1" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                                <?php endif; ?>
                            </div>
                        </form>
                        <a href="../inventory/barcode_print.php?item_id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-dark"
                            target="_blank"><i class="bi bi-printer me-1"></i> Print All</a>
                    </div>
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
                                                        <?php if (strpos($instance['status'], 'condemned-') !== 0): ?>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li><a class="dropdown-item text-danger" href="../staff/condemn.php?id=<?php echo $instance['id']; ?>"><i class="bi bi-trash me-2"></i>Condemn Asset</a></li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                <?php if (!$instances): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                        <?php echo $instance_status_filter !== 'all' ? 'No instances match this status filter.' : 'No instances found. Receive stock to generate barcodes.'; ?>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($inst_total_pages > 1): ?>
                    <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2 px-4">
                        <small class="text-muted">Showing <?php echo $inst_offset + 1; ?>–<?php echo min($inst_offset + $inst_per_page, $total_instances); ?> of <?php echo $total_instances; ?> instances</small>
                        <nav>
                            <ul class="pagination pagination-sm mb-0">
                                <li class="page-item <?php echo $inst_page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($instance_status_filter); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=<?php echo $inst_page - 1; ?>&tx_page=<?php echo $tx_page; ?>">&laquo;</a>
                                </li>
                                <?php
                                $inst_start_p = max(1, $inst_page - 2);
                                $inst_end_p = min($inst_total_pages, $inst_page + 2);
                                for ($ip = $inst_start_p; $ip <= $inst_end_p; $ip++): ?>
                                    <li class="page-item <?php echo $ip === $inst_page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($instance_status_filter); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=<?php echo $ip; ?>&tx_page=<?php echo $tx_page; ?>"><?php echo $ip; ?></a>
                                    </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $inst_page >= $inst_total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($instance_status_filter); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=<?php echo $inst_page + 1; ?>&tx_page=<?php echo $tx_page; ?>">&raquo;</a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
            </div>
        <?php endif; ?>

            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom d-flex justify-content-between align-items-center py-3">
                <h5 class="card-title mb-0">Transaction History <span class="text-muted small fw-normal">(<?php echo $total_transactions; ?> total)</span></h5>
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
            <?php if ($tx_total_pages > 1): ?>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-2">
                <small class="text-muted">Page <?php echo $tx_page; ?> of <?php echo $tx_total_pages; ?> (<?php echo $total_transactions; ?> records)</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item <?php echo $tx_page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($instance_status_filter); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=<?php echo $inst_page ?? 1; ?>&tx_page=<?php echo $tx_page - 1; ?>">&laquo;</a>
                        </li>
                        <?php
                        $tx_start_p = max(1, $tx_page - 2);
                        $tx_end_p = min($tx_total_pages, $tx_page + 2);
                        for ($tp = $tx_start_p; $tp <= $tx_end_p; $tp++): ?>
                            <li class="page-item <?php echo $tp === $tx_page ? 'active' : ''; ?>">
                                <a class="page-link" href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($instance_status_filter); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=<?php echo $inst_page ?? 1; ?>&tx_page=<?php echo $tp; ?>"><?php echo $tp; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo $tx_page >= $tx_total_pages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?id=<?php echo $id; ?>&instance_status=<?php echo urlencode($instance_status_filter); ?>&inst_search=<?php echo urlencode($inst_search); ?>&inst_page=<?php echo $inst_page ?? 1; ?>&tx_page=<?php echo $tx_page + 1; ?>">&raquo;</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php else: ?>
            <div class="card-footer bg-white text-center py-3">
                <a href="transactions.php?item_id=<?php echo $item['id']; ?>" class="text-decoration-none small">View All Transactions <i class="bi bi-arrow-right"></i></a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>