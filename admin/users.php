<?php
/**
 * User Account Management Dashboard
 * 
 * Provides administrative control over system access.
 * 1. Activates 'pending' registrations from new staff.
 * 2. Deactivates accounts to strictly revoke access (Soft Lock).
 * 3. Permanently deletes accounts (with protection against self-deletion).
 * 4. Filters users by their current status for batch processing.
 * 5. Logs all account lifecycle events for accountability.
 */

require_once '../config/database.php';
require_once '../config/app.php';

require_role('Admin');

$db = Database::getInstance();
$error = '';
$success = '';

// Process Account Lifecycle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['id'])) {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $id = (int) $_POST['id'];
    $action = $_POST['action'];
    $newStatus = '';

    if ($action === 'activate')
        $newStatus = 'active';
    if ($action === 'deactivate')
        $newStatus = 'deactivated';

    // Permanent Deletion logic
    if ($action === 'delete') {
        // Prevention: Users cannot delete their own active session account
        $stmt = $db->prepare("DELETE FROM users WHERE id = ? AND id != ?");
        if ($stmt->execute([$id, $_SESSION['user_id']])) {
            $success = "User deleted successfully.";
            // High-priority Audit Log for account removal
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'USER_DELETE', 'User', ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], $id, "Deleted user account ID $id"]);
        } else {
            $error = "Failed to delete user.";
        }
    }

    // Status Toggle logic (Active / Deactivated)
    if ($newStatus) {
        $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ? AND id != ?");
        if ($stmt->execute([$newStatus, $id, $_SESSION['user_id']])) {
            $success = "User status updated successfully.";
            // Record the status shift in the audit trail
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'USER_UPDATE', 'User', ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], $id, "Updated user status to $newStatus for ID $id"]);
        } else {
            $error = "Failed to update user status.";
        }
    }
    // Role Change logic
    if ($action === 'change_role' && isset($_POST['role'])) {
        $newRole = $_POST['role'];
        if (in_array($newRole, ['Admin', 'Staff'])) {
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ? AND id != ?");
            if ($stmt->execute([$newRole, $id, $_SESSION['user_id']])) {
                $success = "User role updated to $newRole successfully.";
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'USER_UPDATE', 'User', ?, ?)");
                $logStmt->execute([$_SESSION['user_id'], $id, "Changed role to $newRole for user account ID $id"]);
            } else {
                $error = "Failed to update user role.";
            }
        }
    }
}

// Fetch Users
$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT * FROM users WHERE id != ?";
$params = [$_SESSION['user_id']];

if ($statusFilter) {
    $sql .= " AND status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$page_title = 'User Management';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">User Management</h2>
        <p class="text-muted small mb-0">Manage system access, assign roles, and control account statuses.</p>
    </div>
    <div class="col-md-6 text-end">
        <div class="btn-group">
            <a href="users.php" class="btn btn-outline-secondary <?php echo !$statusFilter ? 'active' : ''; ?>">All</a>
            <a href="users.php?status=pending"
                class="btn btn-outline-secondary <?php echo $statusFilter === 'pending' ? 'active' : ''; ?>">Pending</a>
            <a href="users.php?status=active"
                class="btn btn-outline-secondary <?php echo $statusFilter === 'active' ? 'active' : ''; ?>">Active</a>
            <a href="users.php?status=deactivated"
                class="btn btn-outline-secondary <?php echo $statusFilter === 'deactivated' ? 'active' : ''; ?>">Deactivated</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Full Name</th>
                        <th>Username</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($users): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="ps-4 fw-semibold">
                                    <?php echo h($user['full_name']); ?>
                                </td>
                                <td>
                                    <?php echo h($user['username']); ?>
                                </td>
                                <td><span class="badge bg-info text-dark">
                                        <?php echo h($user['role']); ?>
                                    </span></td>
                                <td>
                                    <?php if ($user['status'] === 'active'): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php elseif ($user['status'] === 'pending'): ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Deactivated</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                </td>
                                <td class="text-end pe-4">
                                    <form method="POST" action="users.php?status=<?php echo h($statusFilter); ?>" class="d-inline">
                                        <?php csrf_field(); ?>
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        <div class="btn-group shadow-sm">
                                            <?php if ($user['status'] !== 'active'): ?>
                                                <button type="submit" name="action" value="activate" class="btn btn-sm btn-outline-success" title="Activate Account">
                                                    <i class="bi bi-check-circle"></i> <span class="d-none d-lg-inline">Activate</span>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-outline-warning" title="Deactivate Account">
                                                    <i class="bi bi-slash-circle"></i> <span class="d-none d-lg-inline">Deactivate</span>
                                                </button>
                                            <?php endif; ?>

                                            <?php if ($user['role'] === 'Staff'): ?>
                                                <button type="submit" name="action" value="change_role" class="btn btn-sm btn-outline-info" onclick="return confirm('Promote this user to Admin?')" title="Make Admin">
                                                    <i class="bi bi-shield-check"></i> <span class="d-none d-lg-inline">Role</span>
                                                </button>
                                                <input type="hidden" name="role" value="Admin">
                                            <?php else: ?>
                                                <button type="submit" name="action" value="change_role" class="btn btn-sm btn-outline-info" onclick="return confirm('Demote this admin to Staff?')" title="Make Staff">
                                                    <i class="bi bi-person"></i> <span class="d-none d-lg-inline">Role</span>
                                                </button>
                                                <input type="hidden" name="role" value="Staff">
                                            <?php endif; ?>

                                            <button type="submit" name="action" value="delete" class="btn btn-sm btn-outline-danger" onclick="return confirm('Permanently delete this user? This cannot be undone.')" title="Delete Account">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-people text-muted" style="font-size:3rem;"></i>
                                <p class="fw-bold mt-3 mb-1">No users found</p>
                                <p class="text-muted small mb-0"><?php echo $statusFilter ? "No '$statusFilter' users found." : 'No users have been registered yet.'; ?></p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>