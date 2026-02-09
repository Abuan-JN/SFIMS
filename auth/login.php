<?php
/**
 * Login Module
 * 
 * Handles user authentication, session initialization, 
 * and audit logging for successful logins.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Redirect to dashboard if the user is already authenticated
if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';

// Process the login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = Database::getInstance();
        
        // Retrieve user record by username
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // Verify password hash and check account status
        if ($user && password_verify($password, $user['password_hash'])) {
            if ($user['status'] === 'active') {
                // Initialize user session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status'];

                // Audit Log: Record login event for security tracking
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, description) VALUES (?, 'LOGIN', 'User', ?)");
                $logStmt->execute([$user['id'], "User logged in: " . $user['username']]);

                redirect('dashboard.php');
            } elseif ($user['status'] === 'pending') {
                $error = "Your account is awaiting approval by an administrator.";
            } else {
                $error = "Your account is currently deactivated.";
            }
        } else {
            $error = "Invalid username or password.";
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

$page_title = 'Login';
require_once '../partials/header.php';
?>

<div class="row justify-content-center align-items-center min-vh-75">
    <div class="col-md-5 col-lg-4">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <div class="text-center mb-4">
                    <img src="https://www.plmun.edu.ph/images/plmun_logo.png" alt="Logo" class="mb-3" style="width: 80px; height: 80px;">
                    <h2 class="fw-bold mt-2">SFIMS Login</h2>
                    <h5 class="text-primary fw-semibold">Pamantasan ng Lungsod ng Muntinlupa</h5>
                    <p class="text-muted">Supply and Facility Inventory Management System</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-person"></i></span>
                            <input type="text" name="username" id="username" class="form-control border-start-0"
                                required autofocus>
                        </div>
                    </div>
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-lock"></i></span>
                            <input type="password" name="password" id="password" class="form-control border-start-0"
                                required>
                        </div>
                    </div>
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary p-2 fw-semibold">Sign In</button>
                    </div>
                </form>

                <div class="text-center mt-4">
                    <p class="mb-0 text-muted">Don't have an account? <a href="register.php"
                            class="text-decoration-none fw-semibold">Register here</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .min-vh-75 {
        min-height: 75vh;
    }

    .input-group-text {
        border-color: #dee2e6;
    }

    .form-control:focus {
        box-shadow: none;
        border-color: #0d6efd;
    }
</style>

<?php require_once '../partials/footer.php'; ?>