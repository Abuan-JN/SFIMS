<?php
/**
 * Login Page Design and Authentication Logic
 */

// Includes core configuration files for database connection and app functions
require_once '../config/database.php';
require_once '../config/app.php';

// SECURITY: If a user is already logged in, skip the login page and send them to the dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Initializes an empty error variable to store messages for the user
$error = '';

// RATE LIMITING CALCULATION
$max_attempts = 5;
$lockout_time = 300; // 5 minutes in seconds

if (isset($_SESSION['login_locked_until']) && time() < $_SESSION['login_locked_until']) {
    $remaining = ceil(($_SESSION['login_locked_until'] - time()) / 60);
    $error = "Too many failed attempts. Please try again in $remaining minute(s).";
} 
// AUTHENTICATION LOGIC: Runs only when the user clicks the "Sign In" button (POST request)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizes username input to remove extra spaces
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        $db = Database::getInstance();
        // DATABASE QUERY: Searches for a user matching the provided username
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        // VALIDATION: Checks if user exists and if the password matches the encrypted hash
        if ($user && password_verify($password, $user['password_hash'])) {
            // Reset failed login attempts on success
            unset($_SESSION['login_attempts']);
            unset($_SESSION['login_locked_until']);
            
            // STATUS CHECK: Only 'active' users are allowed into the system
            if ($user['status'] === 'active') {
                // SESSION START: Stores user data in the browser session for persistent login
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['status'] = $user['status'];
                $_SESSION['last_activity'] = time(); // Initialize activity timer

                // AUDIT LOG: Records the login event in the database for security monitoring
                $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, description) VALUES (?, 'LOGIN', 'User', ?)");
                $logStmt->execute([$user['id'], "User logged in: " . $user['username']]);

                // SUCCESS: Forwards the user to the main dashboard
                redirect('dashboard.php');
                
            } elseif ($user['status'] === 'pending') {
                $error = "Your account is awaiting approval by an administrator.";
            } else {
                $error = "Your account is currently deactivated.";
            }
        } else {
            // RATE LIMITING: Track failed attempts
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            if ($_SESSION['login_attempts'] >= $max_attempts) {
                $_SESSION['login_locked_until'] = time() + $lockout_time;
                $error = "Too many failed attempts. Please try again in 5 minutes.";
            } else {
                // ERROR: Generic message to prevent "username enumeration" security risks
                $error = "Invalid username or password. " . ($max_attempts - $_SESSION['login_attempts']) . " attempts remaining.";
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Sets the page title before including the header partial
$page_title = 'Login';
require_once '../partials/header.php';
?>

<style>
    /* GLOBAL BACKGROUND: Sets the dark overlay and the PLMun building image */
    body {
        background: linear-gradient(rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.75)), 
                    url('../assets/img/plmunbuilding.png');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
    }

    /* TOP NAVIGATION HEADER: Fixed at the top with a glass-blur effect */
    .top-header {
        background-color: rgba(26, 32, 36, 0.95); 
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        width: 100%;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1000;
        padding: 12px 0;
    }

    .header-content {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .header-logo {
        height: 45px;
        width: auto;
    }

    .brand-text p {
        color: #ecf0f1;
        font-size: 0.85rem;
        text-transform: uppercase;
        margin: 0;
        font-weight: 500;
        letter-spacing: 1px;
    }

    /* CENTERED WRAPPER: Centers the login card on the screen */
    .login-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 100px 20px 40px 20px;
    }

    /* LOGIN CARD: Custom dark styling with depth and rounded corners */
    .login-card {
        background-color: #1a2024 !important; 
        border-radius: 25px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.6);
        width: 100%;
        max-width: 420px;
        border: 1px solid rgba(255,255,255,0.1) !important;
        overflow: hidden;
    }

    /* INPUT DESIGN: Pill-shaped fields with icons on the left side */
    .custom-input .input-group-text {
        background-color: rgba(255, 255, 255, 0.08) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-right: none;
        color: #f8f9fa;
        padding-left: 20px;
        border-radius: 50px 0 0 50px !important;
    }

    .custom-input .form-control {
        background-color: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-left: none;
        color: white !important;
        padding: 12px 20px;
        border-radius: 0 50px 50px 0 !important;
    }

    .custom-input .form-control::placeholder {
        color: rgba(255,255,255,0.4);
    }

    /* INPUT FOCUS: Subtle border highlight when typing */
    .custom-input .form-control:focus {
        background-color: rgba(255, 255, 255, 0.1) !important;
        box-shadow: none;
        border-color: rgba(255,255,255,0.3);
    }

    .form-label {
        margin-left: 15px;
        font-weight: 500;
        letter-spacing: 0.5px;
        color: rgba(255,255,255,0.8);
    }

    /* SIGN-IN BUTTON: High-contrast white button with hover animation */
    .btn-signin {
        background-color: #ffffff;
        border: none;
        padding: 14px;
        font-weight: 700;
        text-transform: uppercase;
        color: #1a2024; 
        border-radius: 50px;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .btn-signin:hover {
        background-color: #e0e0e0;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    /* REGISTRATION LINK: Styling for the bottom redirect link */
    .reg-link {
        color: #ffffff !important;
        text-decoration: none;
        transition: 0.3s;
        border-bottom: 1px solid rgba(255,255,255,0.3);
    }
    
    .reg-link:hover {
        color: #ffffff !important;
        border-bottom: 1px solid #ffffff;
    }


    /* ICON CONTAINER: Circular background for the lock icon */
    .shield-icon {
        background: rgba(255,255,255,0.05);
        width: 80px;
        height: 80px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        margin: 0 auto;
        border: 1px solid rgba(255,255,255,0.1);
    }
</style>

<header class="top-header">
    <div class="container">
        <div class="header-content">
            <img src="../assets/img/logoplmun.png" alt="Logo" class="header-logo">
            <div class="brand-text">
                <p>Supply and Facility Information Management System</p>
            </div>
        </div>
    </div>
</header>

<div class="login-wrapper">
    <div class="card login-card">
        <div class="card-body p-5">
            <div class="text-center mb-5">
                <div class="shield-icon mb-3">
                    <i class="bi bi-shield-lock" style="font-size: 2.5rem; color: #ffffff;"></i>
                </div>
                <h2 class="fw-bold text-white mb-1">SFIMS</h2>
                <p class="small text-white-50">Authorized Personnel Only</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small border-0 text-center mb-4" style="background-color: rgba(255, 0, 0, 0.2); color: #ffb3b3; border-radius: 10px;">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="mb-4">
                    <label class="form-label small text-uppercase">Username</label>
                    <div class="input-group custom-input">
                        <span class="input-group-text"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" class="form-control" placeholder="Username" required autofocus>
                    </div>
                </div>
                
                <div class="mb-5">
                    <label class="form-label small text-uppercase">Password</label>
                    <div class="input-group custom-input">
                        <span class="input-group-text"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" class="form-control" placeholder="Password" required>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-signin">Sign In</button>
                </div>
            </form>

            <div class="text-center mt-5">
                <p class="mb-0 small text-white-50">New to the system? 
                    <a href="register.php" class="reg-link fw-bold">Register here</a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php 
// Includes the standard footer (scripts and closing tags)
require_once '../partials/footer.php'; 
?>
