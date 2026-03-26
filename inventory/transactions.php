<?php
/**
 * Transaction History Module
 * 
 * Provides a system-wide audit trail for all inventory movements.
 * Users can view and filter history by:
 * - Transaction Type (RECEIVE, DISBURSE, MOVE, CONDEMN)
 * - Specific Item ID
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection: Redirect to login if user session is invalid
require_role();

$db = Database::getInstance();

// Retrieve filters from URL
$type = $_GET['type'] ?? '';
$item_id = (int) ($_GET['item_id'] ?? 0);
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Base query to fetch transactions with item, user, and location metadata
$sql = "SELECT t.*, i.name as item_name, u.full_name as user_name, d.name as dept_name, r.name as room_name, b.name as building_name,
               (SELECT COUNT(*) FROM attachments WHERE transaction_id = t.id) as attachment_count
        FROM transactions t 
        JOIN items i ON t.item_id = i.id 
        LEFT JOIN users u ON t.performed_by = u.id 
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN rooms r ON t.room_id = r.id
        LEFT JOIN buildings b ON r.building_id = b.id
        WHERE 1=1";
$params = [];

// Apply dynamic filters
if ($type) {
    $sql .= " AND t.type = ?";
    $params[] = $type;
}

if ($item_id) {
    $sql .= " AND t.item_id = ?";
    $params[] = $item_id;
}

if ($date_from) {
    $sql .= " AND DATE(t.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND DATE(t.created_at) <= ?";
    $params[] = $date_to;
}

// Pagination Setup
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;

// Count total records for pagination
$count_sql = "SELECT COUNT(*) FROM transactions t WHERE 1=1";
if ($type) $count_sql .= " AND t.type = ?";
if ($item_id) $count_sql .= " AND t.item_id = ?";
if ($date_from) $count_sql .= " AND DATE(t.created_at) >= ?";
if ($date_to) $count_sql .= " AND DATE(t.created_at) <= ?";

$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total_tx = (int)$count_stmt->fetchColumn();

$total_pages = max(1, ceil($total_tx / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

// Order by most recent transaction first
$sql .= " ORDER BY t.created_at DESC LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$page_title = 'Transaction History';
require_once '../partials/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Transaction History
            <span class="badge bg-secondary ms-2" style="font-size:0.75rem!important;vertical-align:middle;"><?php echo $total_tx; ?></span>
        </h2>
        <p class="text-muted small mb-0">View a chronological record of all stock movements across the system.</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <a href="transactions.php" class="btn btn-outline-secondary <?php echo !$type ? 'active' : ''; ?>">All</a>
            <a href="transactions.php?type=RECEIVE"
                class="btn btn-outline-secondary <?php echo $type === 'RECEIVE' ? 'active' : ''; ?>">Received</a>
            <a href="transactions.php?type=DISBURSE"
                class="btn btn-outline-secondary <?php echo $type === 'DISBURSE' ? 'active' : ''; ?>">Disbursed</a>
            <a href="transactions.php?type=MOVE"
                class="btn btn-outline-secondary <?php echo $type === 'MOVE' ? 'active' : ''; ?>">Moved</a>
            <a href="transactions.php?type=CONDEMN"
                class="btn btn-outline-secondary <?php echo $type === 'CONDEMN' ? 'active' : ''; ?>">Condemned</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="type" value="<?php echo h($type); ?>">
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo h($date_from); ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold mb-1">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo h($date_to); ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100"><i class="bi bi-funnel me-1"></i>Apply</button>
            </div>
            <?php if ($date_from || $date_to): ?>
            <div class="col-md-2">
                <a href="transactions.php?type=<?php echo h($type); ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-x me-1"></i>Clear</a>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Date</th>
                        <th>Item</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Department/Source</th>
                        <th>Performed By</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($transactions): ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td class="ps-4">
                                    <?php echo date('M d, Y h:i A', strtotime($tx['created_at'])); ?>
                                </td>
                                <td>
                                    <a href="item_details.php?id=<?php echo $tx['item_id']; ?>"
                                        class="text-decoration-none fw-semibold">
                                        <?php echo h($tx['item_name']); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php 
                                    $badge_class = 'bg-secondary';
                                    if($tx['type'] === 'RECEIVE') $badge_class = 'bg-success';
                                    if($tx['type'] === 'DISBURSE') $badge_class = 'bg-danger';
                                    if($tx['type'] === 'MOVE') $badge_class = 'bg-info';
                                    if($tx['type'] === 'CONDEMN') $badge_class = 'bg-dark';
                                    ?>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $tx['type']; ?>
                                    </span>
                                </td>
                                <td class="fw-bold">
                                    <?php echo $tx['quantity']; ?>
                                </td>
                                <td>
                                    <?php if($tx['type'] === 'RECEIVE'): ?>
                                        <small class="text-muted">From:</small> <?php echo h($tx['source_supplier'] ?: '--'); ?>
                                    <?php elseif($tx['type'] === 'DISBURSE' || $tx['type'] === 'MOVE'): ?>
                                        <small class="text-muted">To:</small> <?php echo h($tx['dept_name']); ?>
                                        <?php if($tx['room_name']): ?>
                                            <br><small class="text-muted"><?php echo h($tx['building_name'] . ' - ' . $tx['room_name']); ?></small>
                                        <?php endif; ?>
                                        <?php if($tx['recipient_name']): ?>
                                            <br><small class="text-muted">Recp: <?php echo h($tx['recipient_name']); ?></small>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        --
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo h($tx['user_name']); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <?php if ($tx['attachment_count'] > 0): ?>
                                            <a href="../staff/view_attachments.php?tx_id=<?php echo $tx['id']; ?>" class="btn btn-sm btn-outline-info" title="View Attachments">
                                                <i class="bi bi-paperclip"></i>
                                            </a>
                                        <?php endif; ?>
                                        <button class="btn btn-sm btn-light border" type="button" 
                                                data-bs-toggle="popover" 
                                                title="Transaction Remarks" 
                                                data-bs-content="<?php echo h($tx['remarks'] ?: 'No remarks provided.'); ?>">
                                            <i class="bi bi-info-circle"></i> <span class="d-none d-md-inline">Notes</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-clock-history text-muted" style="font-size:3rem;"></i>
                                <p class="fw-bold mt-3 mb-1">No transactions found</p>
                                <p class="text-muted small mb-0"><?php echo ($type || $date_from || $date_to) ? 'No records match the selected filters.' : 'No transactions have been recorded yet.'; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center bg-white py-2 border-top">
        <small class="text-muted">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_tx); ?> of <?php echo $total_tx; ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <?php 
                $query_params = $_GET; 
                ?>
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <?php $query_params['page'] = $page - 1; ?>
                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">&laquo;</a>
                </li>
                <?php
                $start_p = max(1, $page - 2);
                $end_p   = min($total_pages, $page + 2);
                for ($p = $start_p; $p <= $end_p; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <?php $query_params['page'] = $p; ?>
                        <a class="page-link" href="?<?php echo http_build_query($query_params); ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <?php $query_params['page'] = $page + 1; ?>
                    <a class="page-link" href="?<?php echo http_build_query($query_params); ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
        var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
            return new bootstrap.Popover(popoverTriggerEl, {
                trigger: 'focus'
            })
        })
    });
</script>

<?php require_once '../partials/footer.php'; ?>