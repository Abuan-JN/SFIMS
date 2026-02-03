<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role_id'] != 1) {
    header("Location: index.php");
    exit();
}

$message = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $target_user_id = $_POST['user_id'];
    
    if ($_POST['action'] == 'activate') {
        $stmt = $pdo->prepare("UPDATE users SET is_active = 1 WHERE user_id = ?");
        $stmt->execute([$target_user_id]);
        $message = "User activated successfully.";
    } elseif ($_POST['action'] == 'deactivate') {
        // Prevent deactivating self
        if ($target_user_id == $_SESSION['user_id']) {
            $message = "You cannot deactivate your own account.";
        } else {
            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$target_user_id]);
            $message = "User deactivated successfully.";
        }
    } elseif ($_POST['action'] == 'delete') {
         if ($target_user_id == $_SESSION['user_id']) {
            $message = "You cannot delete your own account.";
        } else {
            // Check for related transactions before deleting (optional, strict FK might block it)
            // For now, simple delete
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
                $stmt->execute([$target_user_id]);
                $message = "User deleted successfully.";
            } catch (PDOException $e) {
                $message = "Cannot delete user. Likely has related records.";
            }
        }
    }
}

// Fetch Users
$stmt = $pdo->query("
    SELECT u.*, r.name as role_name 
    FROM users u 
    JOIN roles r ON u.role_id = r.role_id 
    ORDER BY u.created_at DESC
");
$users = $stmt->fetchAll();

include 'includes/header.php';
?>

<h2>User Management</h2>

<?php if($message): ?>
    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-striped table-bordered">
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Username</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td><?= $user['user_id'] ?></td>
                <td><?= htmlspecialchars($user['full_name']) ?></td>
                <td><?= htmlspecialchars($user['username']) ?></td>
                <td><?= htmlspecialchars($user['role_name']) ?></td>
                <td>
                    <?php if($user['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Inactive</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                        <?php if(!$user['is_active']): ?>
                            <button type="submit" name="action" value="activate" class="btn btn-sm btn-success">Activate</button>
                        <?php else: ?>
                            <button type="submit" name="action" value="deactivate" class="btn btn-sm btn-warning">Deactivate</button>
                        <?php endif; ?>
                        <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
