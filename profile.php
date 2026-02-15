<?php
/**
 * User Profile Settings
 * 
 * Allows users to update their personal information and password.
 */

require_once 'config/database.php';
require_once 'config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$error = '';
$success = '';
$user_id = $_SESSION['user_id'];

// Fetch current user data
$user = $db->query("SELECT * FROM users WHERE id = $user_id")->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if ($full_name) {
        if ($new_password) {
            if ($new_password === $confirm_password) {
                // Update with password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE users SET full_name = ?, password = ? WHERE id = ?");
                if ($stmt->execute([$full_name, $hashed_password, $user_id])) {
                    $success = "Profile updated successfully. Password changed.";
                    $_SESSION['full_name'] = $full_name; // Update session
                } else {
                    $error = "Failed to update profile.";
                }
            } else {
                $error = "New passwords do not match.";
            }
        } else {
            // Update name only
            $stmt = $db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $user_id])) {
                $success = "Profile updated successfully.";
                $_SESSION['full_name'] = $full_name; // Update session
            } else {
                $error = "Failed to update profile.";
            }
        }
    } else {
        $error = "Full Name is required.";
    }
}

$page_title = 'Account Settings';
require_once 'partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Account Settings</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo h($success); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label fw-semibold">Username</label>
                        <input type="text" class="form-control" value="<?php echo h($user['username']); ?>" disabled>
                        <div class="form-text">Username cannot be changed.</div>
                    </div>

                    <div class="mb-3">
                        <label for="full_name" class="form-label fw-semibold">Full Name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo h($user['full_name']); ?>" required>
                    </div>

                    <hr class="my-4">
                    <h5 class="mb-3">Change Password</h5>

                    <div class="mb-3">
                        <label for="new_password" class="form-label fw-semibold">New Password</label>
                        <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Leave blank to keep current password">
                    </div>

                    <div class="mb-4">
                        <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm new password">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="dashboard.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
