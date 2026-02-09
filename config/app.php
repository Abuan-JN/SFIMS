<?php
/**
 * Global Configuration and Helper Functions
 * 
 * This file initializes the session, defines the base URL,
 * and provides common utility functions used across the application.
 */

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
