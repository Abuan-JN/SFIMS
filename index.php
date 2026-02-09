<?php
/**
 * Root index.php (Initial Entry Point)
 * 
 * Determines whether a user is authenticated and routes them accordingly.
 */

require_once 'config/app.php';

// If already logged in, skip login and go to dashboard
if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('auth/login.php');
}
