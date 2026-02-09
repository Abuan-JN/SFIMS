<?php
// config/app.php

session_start();

// Base URL configuration (adjust as needed)
define('BASE_URL', 'http://localhost/SoftEn/SFIMS/');

/**
 * Redirect to a given URL
 */
function redirect($path)
{
    header("Location: " . BASE_URL . $path);
    exit();
}

/**
 * Flash messages
 */
function set_flash_message($type, $message)
{
    $_SESSION['flash'] = [
        'type' => $type, // success, danger, warning, info
        'message' => $message
    ];
}

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
 * Check if user is logged in
 */
function is_logged_in()
{
    return isset($_SESSION['user_id']) && $_SESSION['status'] === 'active';
}

/**
 * Require a specific role
 */
function require_role($roles = null)
{
    if (!is_logged_in()) {
        set_flash_message('danger', 'Please login to access this page.');
        redirect('index.php');
    }
    // With only one role ('Staff'), any active logged-in user is authorized.
}

/**
 * Sanitize XSS
 */
function h($string)
{
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
