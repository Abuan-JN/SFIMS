<?php
/**
 * Logout Module
 * 
 * Records the logout event in the audit trail, 
 * clears all session data, and redirects to the login page.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// If a user is currently logged in, log their departure
if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, description) VALUES (?, 'LOGOUT', 'User', ?)");
    $logStmt->execute([$_SESSION['user_id'], "User logged out: " . $_SESSION['username']]);
}

// Clear all session variables and destroy the session 
session_unset();
session_destroy();

// Send the user back to the login screen
redirect('auth/login.php');
