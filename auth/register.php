<?php
/**
 * Registration Module - SFIMS UI
 */

// BACKEND: Includes essential configuration files for database and system functions
require_once '../config/database.php';
require_once '../config/app.php';

// SECURITY: Prevent already logged-in users from accessing the registration page
if (is_logged_in()) {
    redirect('dashboard.php');
}

// Variables to hold feedback messages for the user
$error = '';
$success = '';

// FORM HANDLING: Executes when the user submits the registration form (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitizes inputs by removing unnecessary whitespace
    $full_name = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = 'Staff'; // Default role assigned to new registrants

    // VALIDATION: Ensures all required fields are filled
    if ($full_name && $username && $password && $confirm_password) {
        // Checks if the two password fields match
        if ($password !== $confirm_password) {
            $error = "Passwords do not match.";
        } 
        // Security check: Minimum password length requirement
        elseif (strlen($password) < 6) {
            $error = "Password must be at least 6 characters.";
        } else {
            $db = Database::getInstance();
            
            // DUPLICATE CHECK: Verifies if the username is already taken in the database
            $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->fetch()) {
                $error = "Username already exists.";
            } else {
                // PASSWORD HASHING: Encrypts the password using a secure industry-standard algorithm
                $hash = password_hash($password, PASSWORD_DEFAULT);
                
                // DATA INSERTION: Saves the new user into the database with a 'pending' status
                $stmt = $db->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES (?, ?, ?, ?, 'pending')");

                if ($stmt->execute([$full_name, $username, $hash, $role])) {
                    $success = "Registration successful! Your account is pending approval.";
                } else {
                    $error = "Something went wrong. Please try again.";
                }
            }
        }
    } else {
        $error = "Please fill in all fields.";
    }
}

// Defines page title and includes the global header partial
$page_title = 'Register';
require_once '../partials/header.php';
?>

<style>
    /* GLOBAL BACKGROUND: Consistent design with the login page using the university building image */
    body {
        background: linear-gradient(rgba(0, 0, 0, 0.75), rgba(0, 0, 0, 0.75)), 
                    url('plmunbuilding.png');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        font-family: 'Inter', 'Segoe UI', Roboto, sans-serif;
    }

    /* HEADER STYLING: Glassmorphism effect for the top navigation bar */
    .top-header {
        background-color: rgba(26, 32, 36, 0.95);
        backdrop-filter: blur(10px);
        width: 100%;
        position: fixed;
        top: 0;
        z-index: 1000;
        padding: 12px 0;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .header-logo { height: 45px; }

    /* REGISTER CARD WRAPPER: Centers the wider registration card on the screen */
    .register-wrapper {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 110px 20px 60px 20px;
    }

    .register-card {
        background-color: #1a2024 !important; 
        border-radius: 25px;
        box-shadow: 0 25px 50px rgba(0,0,0,0.6);
        width: 100%;
        max-width: 550px; 
        border: 1px solid rgba(255,255,255,0.1) !important;
        overflow: hidden;
    }

    /* INPUT STYLING: Pill-shaped input fields with icons for a modern UI */
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

    /* Focus state: Increases border brightness when the field is active */
    .custom-input .form-control:focus {
        background-color: rgba(255, 255, 255, 0.1) !important;
        box-shadow: none;
        border-color: rgba(255,255,255,0.3);
    }

    /* Text labels positioned above inputs */
    .form-label {
        color: rgba(255, 255, 255, 0.8);
        margin-left: 15px;
        font-size: 0.8rem;
        text-transform: uppercase;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    /* BUTTON DESIGN: High-contrast white button with lift animation on hover */
    .btn-register {
        background-color: #ffffff;
        border: none;
        padding: 14px;
        font-weight: 700;
        text-transform: uppercase;
        color: #1a2024;
        border-radius: 50px;
        letter-spacing: 1px;
        transition: 0.3s;
    }

    .btn-register:hover {
        background-color: #e0e0e0;
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    }

    /* Link styling for navigating back to login */
    .login-link {
        color: #ffffff !important;
        text-decoration: none;
        border-bottom: 1px solid rgba(255,255,255,0.3);
    }
    
    .login-link:hover {
        color: #ffffff !important;
        border-bottom: 1px solid #ffffff;
    }

    /* Hides global footer to keep registration focus centered */
    footer { display: none !important; }

    /* Decorative circle for the registration icon */
    .icon-circle {
        background: rgba(255,255,255,0.05);
        width: 70px;
        height: 70px;
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
        <div class="d-flex align-items-center gap-3">
            <img src="logoplmun.png" alt="Logo" class="header-logo">
            <div class="text-white small text-uppercase fw-medium" style="letter-spacing: 1px;">
                SFIMS | Registration
            </div>
        </div>
    </div>
</header>

<div class="register-wrapper">
    <div class="card register-card">
        <div class="card-body p-5">
            <div class="text-center mb-5">
                <div class="icon-circle mb-3">
                    <i class="bi bi-person-plus text-white" style="font-size: 2rem;"></i>
                </div>
                <h2 class="fw-bold text-white mt-3">Create Account</h2>
                <p class="small text-white-50">Provide your details to register</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small border-0 text-center mb-4" style="background-color: rgba(255, 0, 0, 0.2); color: #ffb3b3; border-radius: 10px;">
                    <i class="bi bi-exclamation-circle me-2"></i><?php echo h($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success py-4 border-0 text-center mb-4" style="background-color: rgba(40, 167, 69, 0.2); color: #98fb98; border-radius: 15px;">
                    <i class="bi bi-check-circle-fill d-block mb-2" style="font-size: 2rem;"></i>
                    <p class="mb-3"><?php echo h($success); ?></p>
                    <a href="login.php" class="btn btn-light btn-sm rounded-pill fw-bold px-4">Sign In Now</a>
                </div>
            <?php else: ?>
                <form method="POST">
                    <div class="mb-4">
                        <label class="form-label">Full Name</label>
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="bi bi-person-vcard"></i></span>
                            <input type="text" name="full_name" class="form-control" placeholder="Juan Dela Cruz" value="<?php echo h($_POST['full_name'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Username</label>
                        <div class="input-group custom-input">
                            <span class="input-group-text"><i class="bi bi-at"></i></span>
                            <input type="text" name="username" class="form-control" placeholder="username123" value="<?php echo h($_POST['username'] ?? ''); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Password</label>
                            <div class="input-group custom-input">
                                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                <input type="password" name="password" class="form-control" placeholder="••••••" required>
                            </div>
                        </div>
                        <div class="col-md-6 mb-4">
                            <label class="form-label">Confirm Password</label>
                            <div class="input-group custom-input">
                                <span class="input-group-text"><i class="bi bi-shield-check"></i></span>
                                <input type="password" name="confirm_password" class="form-control" placeholder="••••••" required>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid mt-2">
                        <button type="submit" class="btn btn-register">Register</button>
                    </div>
                </form>

                <div class="text-center mt-5">
                    <p class="mb-0 small text-white-50">Already have an account? 
                        <a href="login.php" class="login-link fw-bold">Sign In</a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php 
// Includes the global footer script bundle
require_once '../partials/footer.php'; 
?>
