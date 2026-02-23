<?php
/**
 * Registration Module
 * 
 * Allows new users (Staff) to create accounts. 
 * Accounts are created with a 'pending' status and require 
 * administrator approval before access is granted.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Prevent registered users from accessing this page
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle the registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'Staff'; // Default role for all new registered users

    // Basic validation
    if ($full_name && $username && $password && $confirm_password) {
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $db = Database::getInstance();

            // Safety Check: Verify that the username is not already taken
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = "Username already exists.";
            } else {
                // Securely hash the password and insert the new user record
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, ?, 'pending')");

                if ($stmt->execute([$full_name, $username, $hash, $role])) {
                    $success = "Registration successful! Your account is pending approval by an administrator.";
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

$page_title = 'Register';
require_once '../partials/header.php';
?>

<div class="row justify-content-center align-items-center min-vh-75">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <h2 class="fw-bold">Create Account</h2>
                    <p class="text-muted">Register for SPMO PLMun access</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <?php echo h($success); ?>
                        <div class="mt-3">
                            <a href="login.php" class="btn btn-success btn-sm">Go to Login</a>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="">
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" name="full_name" id="full_name" class="form-control"
                                value="<?php echo h($_POST['full_name'] ?? ''); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" name="username" id="username" class="form-control"
                                value="<?php echo h($_POST['username'] ?? ''); ?>" required>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="password" class="form-label">Password</label>
                                <input type="password" name="password" id="password" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-control"
                                    required>
                            </div>
                        </div>
                        <!-- The 'role' is defaulted to 'Staff' securely in the PHP processor. No user input required here. -->
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary p-2 fw-semibold">Register</button>
                        </div>
                    </form>

                    <div class="text-center mt-4">
                        <p class="mb-0 text-muted">Already have an account? <a href="login.php"
                                class="text-decoration-none fw-semibold">Sign In</a></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>