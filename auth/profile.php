<?php
/**
 * User Profile Management
 * 
 * Allows the currently logged-in user to:
 * 1. Modify their displayed full name.
 * 2. Change their account password securely.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection: Redirect to login if user session is invalid
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Load the most current user information from the database
$user_id = $_SESSION['user_id'];
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Handle form submissions for profile or password changes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    // Logic for updating basic profile info (Full Name)
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name'] ?? '');
        if ($full_name) {
            $stmt = $db->prepare("UPDATE users SET full_name = ? WHERE id = ?");
            if ($stmt->execute([$full_name, $user_id])) {
                $_SESSION['full_name'] = $full_name; // Sync session with new name
                $success = "Profile updated successfully.";
            }
        }
        
    // Logic for security updates (Change Password)
    } elseif (isset($_POST['change_password'])) {
        $old_pass = $_POST['old_password'] ?? '';
        $new_pass = $_POST['new_password'] ?? '';
        $confirm_pass = $_POST['confirm_password'] ?? '';

        // Verify current password before allowing change
        if (password_verify($old_pass, $user['password_hash'])) {
            if ($new_pass === $confirm_pass) {
                if (strlen($new_pass) >= 8) {
                    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                    $stmt->execute([$hash, $user_id]);
                    $success = "Password changed successfully.";
                } else {
                    $error = "New password must be at least 8 characters.";
                }
            } else {
                $error = "Passwords do not match.";
            }
        } else {
            $error = "Incorrect current password.";
        }
    }
}

$page_title = 'My Profile';
require_once '../partials/header.php';
?>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0 text-center p-4">
            <div class="mb-3">
                <i class="bi bi-person-circle display-1 text-primary"></i>
            </div>
            <h4 class="fw-bold">
                <?php echo h($user['full_name']); ?>
            </h4>
            <p class="text-muted mb-1">@
                <?php echo h($user['username']); ?>
            </p>
            <span class="badge bg-primary">
                <?php echo h($user['role']); ?>
            </span>
            <div class="mt-3 small text-muted">Member since
                <?php echo date('M Y', strtotime($user['created_at'])); ?>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0">Profile Settings</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="full_name" class="form-control"
                            value="<?php echo h($user['full_name']); ?>" required>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">Update Name</button>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="mb-0">Security (Change Password)</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?php csrf_field(); ?>
                    <div class="mb-3">
                        <label class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" name="old_password" class="form-control" id="old_password_field" required>
                            <button class="btn btn-outline-secondary" type="button" onclick="togglePw('old_password_field', this)"><i class="bi bi-eye"></i></button>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" class="form-control" id="new_password_field" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw('new_password_field', this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" name="confirm_password" class="form-control" id="confirm_password_field" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePw('confirm_password_field', this)"><i class="bi bi-eye"></i></button>
                            </div>
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-warning">Update Password</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
function togglePw(id, btn) {
    var input = document.getElementById(id);
    var icon = btn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>

<?php require_once '../partials/footer.php'; ?>