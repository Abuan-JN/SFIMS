<?php
// staff/get_instances.php
require_once '../config/database.php';
require_once '../config/app.php';

if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance();
$item_id = (int) ($_GET['item_id'] ?? 0);
$status = $_GET['status'] ?? '';

if ($item_id) {
    $sql = "SELECT ii.*, b.barcode_value FROM item_instances ii JOIN barcodes b ON ii.barcode_id = b.id WHERE ii.item_id = ?";
    $params = [$item_id];

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY barcode_value ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $instances = $stmt->fetchAll();

    header('Content-Type: application/json');
    echo json_encode($instances);
} else {
    header('Content-Type: application/json');
    echo json_encode([]);
}
