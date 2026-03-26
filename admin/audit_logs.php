<?php
/**
 * System Audit Trail
 * 
 * Provides a read-only historical log of all critical system actions.
 * 1. Tracks Who, What, When, and precisely Which entity was modified.
 * 2. Supports full-text search across descriptions, action types, and usernames.
 * 3. Joins with the users table to provide human-readable actor names.
 * 4. Limits view to the most recent 500 entries for performance.
 * 5. Serves as the primary evidence for institutional compliance and security auditing.
 */

require_once '../config/database.php';
require_once '../config/app.php';

require_role('Admin');

$db = Database::getInstance();
$search = $_GET['search'] ?? ''; // Search query from filter form
$action_type_filter = $_GET['action_type'] ?? '';

// Base Query: aggregate logs with user metadata
$sql = "SELECT al.*, u.username, u.full_name 
        FROM audit_logs al 
        LEFT JOIN users u ON al.user_id = u.id 
        WHERE 1=1";
$params = [];

// Apply filter if search query is provided
if ($search) {
    $sql .= " AND (al.description LIKE ? OR al.action_type LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($action_type_filter) {
    $sql .= " AND al.action_type = ?";
    $params[] = $action_type_filter;
}

// Get distinct action types for the filter dropdown
$action_types = $db->query("SELECT DISTINCT action_type FROM audit_logs ORDER BY action_type ASC")->fetchAll();

// Sort by recency and enforce safety limit
$sql .= " ORDER BY al.timestamp DESC LIMIT 500";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$page_title = 'Audit Logs';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-5">
        <h2 class="fw-bold mb-1">Audit Logs
            <span class="badge bg-secondary ms-2" style="font-size:0.75rem!important;vertical-align:middle;"><?php echo count($logs); ?></span>
        </h2>
        <p class="text-muted small mb-0">System-wide security trail of all administrative and inventory actions.</p>
    </div>
    <div class="col-md-7">
        <form method="GET" action="" class="row g-2">
            <div class="col-md-5">
                <div class="input-group">
                    <input type="text" name="search" class="form-control"
                        placeholder="Search logs..." value="<?php echo h($search); ?>">
                </div>
            </div>
            <div class="col-md-4">
                <select name="action_type" class="form-select">
                    <option value="">All Action Types</option>
                    <?php foreach($action_types as $at): ?>
                    <option value="<?php echo h($at['action_type']); ?>" <?php echo $action_type_filter === $at['action_type'] ? 'selected' : ''; ?>><?php echo h($at['action_type']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-outline-secondary flex-grow-1" type="submit"><i class="bi bi-funnel me-1"></i>Filter</button>
                <?php if ($search || $action_type_filter): ?>
                    <a href="audit_logs.php" class="btn btn-outline-secondary"><i class="bi bi-x"></i></a>
                <?php endif; ?>
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
                            <td colspan="5" class="text-center py-5">
                                <i class="bi bi-shield-check text-muted" style="font-size:3rem;"></i>
                                <p class="fw-bold mt-3 mb-1">No log entries found</p>
                                <p class="text-muted small mb-0"><?php echo ($search || $action_type_filter) ? 'No logs match the current filters.' : 'The audit log is clean.'; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>