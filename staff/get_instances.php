<?php
/**
 * Asset Instance API Endpoint (AJAX)
 * 
 * Provides a JSON list of physical units for a specific item.
 * 1. Checks for an active session (Authorization).
 * 2. Filters by item_id and optionally by status (e.g., only 'in-stock' units).
 * 3. Joins the barcodes table to provide tracking IDs for the frontend.
 * 4. Used primarily by dynamic forms like 'disburse.php' to show selectable units.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Authorization Check: Only logged-in users can access the inventory via API
if (!is_logged_in()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$db = Database::getInstance();
$item_id = (int) ($_GET['item_id'] ?? 0); // The master item to look up
$status = $_GET['status'] ?? '';          // Optional: Filter by status (e.g., 'in-stock')

if ($item_id) {
    // Basic SQL to fetch units and their barcodes
    $sql = "SELECT ii.*, b.barcode_value FROM item_instances ii JOIN barcodes b ON ii.barcode_id = b.id WHERE ii.item_id = ?";
    $params = [$item_id];

    // Dynamically append status filter if provided
    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY barcode_value ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $instances = $stmt->fetchAll();

    // Output strictly as JSON for frontend processing
    header('Content-Type: application/json');
    echo json_encode($instances);
} else {
    // Return an empty set if no item ID is provided
    header('Content-Type: application/json');
    echo json_encode([]);
}
