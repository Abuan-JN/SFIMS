<?php
require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

try {
    // Mark all unread notifications for the system as read
    // Note: Since audit logs are system-wide, we mark all unread ones.
    // If multi-user notification tracking is needed later, this can be filtered by user_id.
    $stmt = $db->prepare("UPDATE audit_logs SET is_read = 1 WHERE is_read = 0");
    $stmt->execute();
    
    header('Content-Type: application/json');
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
