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
        <h2 class="fw-bold">User Management</h2>
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
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light border dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                            Actions
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <form method="POST" action="users.php?status=<?php echo $statusFilter; ?>">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                                <?php if ($user['status'] !== 'active'): ?>
                                                <li><button type="submit" name="action" value="activate" class="dropdown-item text-success"><i class="bi bi-check-circle me-2"></i>Activate</button></li>
                                                <?php endif; ?>
                                                <?php if ($user['status'] === 'active'): ?>
                                                <li><button type="submit" name="action" value="deactivate" class="dropdown-item text-warning"><i class="bi bi-slash-circle me-2"></i>Deactivate</button></li>
                                                <?php endif; ?>
                                                <?php if ($user['role'] === 'Staff'): ?>
                                                <input type="hidden" name="role" value="Admin" id="role_<?php echo $user['id']; ?>">
                                                <li><button type="submit" name="action" value="change_role" class="dropdown-item" onclick="return confirm('Promote this user to Admin?')"><i class="bi bi-shield-check me-2"></i>Make Admin</button></li>
                                                <?php else: ?>
                                                <input type="hidden" name="role" value="Staff" id="role_<?php echo $user['id']; ?>">
                                                <li><button type="submit" name="action" value="change_role" class="dropdown-item" onclick="return confirm('Demote this admin to Staff?')"><i class="bi bi-person me-2"></i>Make Staff</button></li>
                                                <?php endif; ?>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><button type="submit" name="action" value="delete" class="dropdown-item text-danger" onclick="return confirm('Permanently delete this user? This cannot be undone.')"><i class="bi bi-trash me-2"></i>Delete</button></li>
                                            </form>
                                        </ul>
                                    </div>
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