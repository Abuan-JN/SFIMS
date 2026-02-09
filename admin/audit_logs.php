<?php
// admin/audit_logs.php
require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$db = Database::getInstance();
$search = $_GET['search'] ?? '';

$sql = "SELECT al.*, u.username, u.full_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (al.description LIKE ? OR al.action_type LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY al.timestamp DESC LIMIT 500";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$page_title = 'Audit Logs';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Audit Logs</h2>
    </div>
    <div class="col-md-6">
        <form method="GET" action="">
            <div class="input-group">
                <input type="text" name="search" class="form-control"
                    placeholder="Search by action, user, or description..." value="<?php echo h($search); ?>">
                <button class="btn btn-outline-secondary" type="submit">Search</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4">Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($logs): ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td class="ps-4 small text-muted">
                                    <?php echo date('M d, Y', strtotime($log['timestamp'])); ?><br>
                                    <?php echo date('h:i:s A', strtotime($log['timestamp'])); ?>
                                </td>
                                <td>
                                    <span class="fw-bold">
                                        <?php echo h($log['username'] ?: 'System'); ?>
                                    </span><br>
                                    <small class="text-muted">
                                        <?php echo h($log['full_name']); ?>
                                    </small>
                                </td>
                                <td><span class="badge bg-dark">
                                        <?php echo h($log['action_type']); ?>
                                    </span></td>
                                <td><small class="text-uppercase fw-bold text-muted">
                                        <?php echo h($log['entity_name']); ?>
                                    </small></td>
                                <td>
                                    <div class="small">
                                        <?php echo h($log['description']); ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">No logs found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>