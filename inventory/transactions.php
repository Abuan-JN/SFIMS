<?php
// transactions.php
require_once '../config/database.php';
require_once '../config/app.php';

if (!is_logged_in()) {
    redirect('auth/login.php');
}

$db = Database::getInstance();
$type = $_GET['type'] ?? '';
$item_id = (int) ($_GET['item_id'] ?? 0);

$sql = "SELECT t.*, i.name as item_name, u.full_name as user_name, d.name as dept_name, r.name as room_name, b.name as building_name
        FROM transactions t 
        JOIN items i ON t.item_id = i.id 
        LEFT JOIN users u ON t.performed_by = u.id 
        LEFT JOIN departments d ON t.department_id = d.id
        LEFT JOIN rooms r ON t.room_id = r.id
        LEFT JOIN buildings b ON r.building_id = b.id
        WHERE 1=1";
$params = [];

if ($type) {
    $sql .= " AND t.type = ?";
    $params[] = $type;
}

if ($item_id) {
    $sql .= " AND t.item_id = ?";
    $params[] = $item_id;
}

$sql .= " ORDER BY t.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$page_title = 'Transaction History';
require_once '../partials/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-6">
        <h2 class="fw-bold">Transaction History</h2>
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
                                    <!-- Could add a view attachment button here if exists -->
                                    <button class="btn btn-sm btn-light" type="button" title="View Details"><i
                                            class="bi bi-eye"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted">No transactions found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>