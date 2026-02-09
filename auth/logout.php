<?php
// logout.php
require_once '../config/database.php';
require_once '../config/app.php';

if (isset($_SESSION['user_id'])) {
    $db = Database::getInstance();
    $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, description) VALUES (?, 'LOGOUT', 'User', ?)");
    $logStmt->execute([$_SESSION['user_id'], "User logged out: " . $_SESSION['username']]);
}

session_unset();
session_destroy();

redirect('auth/login.php');
