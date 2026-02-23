<?php
/**
 * Global Configuration and Helper Functions
 * 
 * This file initializes the session, defines the base URL,
 * and provides common utility functions used across the application.
 */

// Secure session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_samesite', 'Lax');
// ini_set('session.cookie_secure', 1); // Only enable if running on HTTPS

// Start the session for user authentication and flash messages
session_start();

// BASE_URL is the root web path of the application.
// Ensure this matches your project's directory structure in htdocs.
define('BASE_URL', 'http://localhost/SoftEn/SFIMS/');

/**
 * redirect()
 * 
 * Terminates current script and sends the user to a different page.
 * Uses BASE_URL to resolve the full path.
 */
function redirect($path)
{
    header("Location: " . BASE_URL . $path);
    exit();
}

/**
 * set_flash_message()
 * 
 * Stores a one-time message in the session to be displayed on the next page load.
 * Commonly used for success or error notifications after form submissions.
 * 
 * @param string $type The Bootstrap alert class (e.g., success, danger, warning)
 * @param string $message The message text to display
 */
function set_flash_message($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * display_flash_message()
 * 
 * Checks for a flash message in the session and outputs it as a Bootstrap alert.
 * The message is "unset" (deleted) immediately after output.
 */
function display_flash_message()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        echo '<div class="alert alert-' . $flash['type'] . ' alert-dismissible fade show" role="alert">
                ' . $flash['message'] . '
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
              </div>';
        unset($_SESSION['flash']);
    }
}

/**
 * is_logged_in()
 * 
 * Verifies if a user session exists and if the user status is active.
 * 
 * @return boolean True if logged in and active
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && $_SESSION['status'] === 'active';
}

/**
 * require_role()
 * 
 * Access control helper. Redirects users to login if they are not authenticated.
 * This can be expanded to check specific roles (e.g., Admin vs Staff).
 * 
 * @param mixed $roles Optional role or array of roles authorized to view the page
 */
function require_role($roles = null)
{
    if (!is_logged_in()) {
        set_flash_message('danger', 'Please login to access this page.');
        redirect('auth/login.php');
    }

    if ($roles !== null) {
        if (!is_array($roles)) {
            $roles = [$roles];
        }

        if (!in_array($_SESSION['role'], $roles)) {
            set_flash_message('danger', 'Access Denied: You do not have permission to view this page.');
            redirect('dashboard.php');
        }
    }
}

/**
 * h()
 * 
 * Short alias for htmlspecialchars to prevent Cross-Site Scripting (XSS).
 * Should be used whenever echoing user-supplied data into the HTML.
 */
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * generate_csrf_token()
 * 
 * Generates a CSRF token for the user session if one does not exist.
 * 
 * @return string The current CSRF token
 */
function generate_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * csrf_field()
 * 
 * Outputs a hidden HTML input field containing the current CSRF token.
 */
function csrf_field()
{
    $token = generate_csrf_token();
    echo '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

/**
 * verify_csrf_token()
 * 
 * Verifies the submitted CSRF token against the one stored in the session.
 * Terminates the application if the token is invalid or missing.
 * 
 * @param string $token The token submitted typically via POST
 */
function verify_csrf_token($token)
{
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        // Output generic error and terminate for security
        die('CSRF token validation failed. Please refresh the page and try again.');
    }
}
