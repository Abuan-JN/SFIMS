<?php
/**
 * Direct Database Stress Test Seeder for SFIMS
 * 
 * Performs high-speed data insertion using PDO transactions.
 * This is an alternative to importing massive .sql files.
 */

// --- CONFIGURATION ---
$itemCount = 20000; // Total items to generate
$batchSize = 500;   // Process in batches for progress reporting
// ---------------------

require_once __DIR__ . '/../config/database.php';

// Set environment for large-scale operations
set_time_limit(0);
ignore_user_abort(true);
ini_set('memory_limit', '512M');

// Force flush response output (for browser progress)
if (php_sapi_name() !== 'cli') {
    ini_set('output_buffering', '0');
    ini_set('zlib.output_compression', '0');
    while (ob_get_level()) ob_end_flush();
    ob_implicit_flush(true);
}

function logLine($msg) {
    $line = "[" . date('H:i:s') . "] $msg" . (php_sapi_name() === 'cli' ? "\n" : "<br>\n");
    echo $line;
    if (php_sapi_name() !== 'cli') {
        echo str_pad('', 4096); // Push to browser
    }
}

try {
    $db = Database::getInstance();
    logLine("<b>SFIMS High-Performance Seeder Started</b>");
    logLine("Target: $itemCount items with associated asset instances.");

    $db->beginTransaction();

    // 1. Resolve / Create Categories
    $catIds = [];
    $stmt = $db->query("SELECT id, name FROM categories WHERE name IN ('Consumables', 'Fixed Assets')");
    while ($row = $stmt->fetch()) {
        $catIds[$row['name']] = $row['id'];
    }

    if (!isset($catIds['Consumables'])) {
        $db->prepare("INSERT INTO categories (name) VALUES ('Consumables')")->execute();
        $catIds['Consumables'] = $db->lastInsertId();
    }
    if (!isset($catIds['Fixed Assets'])) {
        $db->prepare("INSERT INTO categories (name) VALUES ('Fixed Assets')")->execute();
        $catIds['Fixed Assets'] = $db->lastInsertId();
    }

    $consumableId = $catIds['Consumables'];
    $fixedAssetId = $catIds['Fixed Assets'];

    // 2. Ensure Sub-Categories Exist
    $sub_cats = [$consumableId => [], $fixedAssetId => []];
    foreach ([$consumableId, $fixedAssetId] as $cid) {
        $stmt = $db->prepare("SELECT id FROM sub_categories WHERE category_id = ?");
        $stmt->execute([$cid]);
        $sub_cats[$cid] = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($sub_cats[$cid])) {
            $defaults = ($cid == $consumableId) 
                ? ['Office Supplies', 'Cleaning Supplies', 'Stationery']
                : ['Computer Hardware', 'Furniture', 'Electronics'];
            
            foreach ($defaults as $name) {
                $db->prepare("INSERT INTO sub_categories (category_id, name) VALUES (?, ?)")->execute([$cid, $name]);
                $sub_cats[$cid][] = $db->lastInsertId();
            }
        }
    }

    // 3. References (Room & User)
    $roomId = $db->query("SELECT id FROM rooms LIMIT 1")->fetchColumn();
    if (!$roomId) {
        $db->prepare("INSERT INTO buildings (name) VALUES ('Main Building')")->execute();
        $bid = $db->lastInsertId();
        $db->prepare("INSERT INTO rooms (building_id, name, floor) VALUES (?, 'Room 101', '1st Floor')")->execute([$bid]);
        $roomId = $db->lastInsertId();
    }

    $userId = $db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
    if (!$userId) {
        $hash = password_hash('password', PASSWORD_DEFAULT);
        $db->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES ('Admin', 'admin', ?, 'Admin', 'active')")->execute([$hash]);
        $userId = $db->lastInsertId();
    }

    // 4. Pre-compile statements for speed
    $insertItem = $db->prepare("INSERT INTO items (name, description, category_id, sub_category_id, uom, threshold_quantity, current_quantity) VALUES (?, ?, ?, ?, ?, 5, ?)");
    $insertBarcode = $db->prepare("INSERT INTO barcodes (item_id, barcode_value) VALUES (?, ?)");
    $insertInstance = $db->prepare("INSERT INTO item_instances (item_id, barcode_id, serial_number, status, room_id, assigned_person, contact_number) VALUES (?, ?, ?, 'in-stock', ?, 'Stress Tester', '555-0100')");
    $insertTransaction = $db->prepare("INSERT INTO transactions (item_id, type, quantity, date, performed_by, remarks) VALUES (?, 'RECEIVE', ?, CURDATE(), ?, 'Stress data initial load')");

    $uoms = ['pcs', 'box', 'set', 'unit', 'ream'];
    
    logLine("Seeding items...");
    for ($i = 1; $i <= $itemCount; $i++) {
        $catId = ($i <= ($itemCount / 2)) ? $consumableId : $fixedAssetId;
        $subCatId = $sub_cats[$catId][array_rand($sub_cats[$catId])];
        $isFixed = ($catId === $fixedAssetId);
        
        $name = ($isFixed ? "Stress Asset " : "Stress Consumable ") . $i;
        $desc = "Automatically generated stress test item #" . $i;
        $uom = $uoms[array_rand($uoms)];
        $qty = rand(5, 50); // Lower initial qty per asset type to keep instances manageable but realistic

        // A. Insert Item
        $insertItem->execute([$name, $desc, $catId, $subCatId, $uom, $qty]);
        $itemId = $db->lastInsertId();

        // B. Insert Main Transaction Log
        $insertTransaction->execute([$itemId, $qty, $userId]);

        if (!$isFixed) {
            // C1. Consumables: Single Barcode
            $insertBarcode->execute([$itemId, "C-$itemId-" . time()]);
        } else {
            // C2. Fixed Assets: Multiple instances
            for ($j = 0; $j < $qty; $j++) {
                $insertBarcode->execute([$itemId, "A-$itemId-$j-" . time()]);
                $barcodeId = $db->lastInsertId();
                $insertInstance->execute([$itemId, $barcodeId, "SN-$itemId-$j", $roomId]);
            }
        }

        if ($i % $batchSize === 0) {
            logLine("Progress: $i / $itemCount items created...");
        }
    }

    $db->commit();
    logLine("<b style='color:green;'>SUCCESS: Database seeding complete!</b>");
    logLine("Total Items Created: $itemCount");
    logLine("You can now verify the results in the Condemned Assets and Reports pages.");

} catch (Exception $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    logLine("<b style='color:red;'>FATAL ERROR:</b> " . $e->getMessage());
}
