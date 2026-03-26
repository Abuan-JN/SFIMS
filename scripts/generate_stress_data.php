<?php
/**
 * Stress Test Data Generator for SFIMS
 * Automatically seeds sub-categories if missing to ensure data integrity.
 */

require_once __DIR__ . '/../config/database.php';

try {
    $db = Database::getInstance();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

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
$sub_cats = [
    $consumableId => [],
    $fixedAssetId => []
];

foreach ([$consumableId, $fixedAssetId] as $cid) {
    $stmt = $db->prepare("SELECT id FROM sub_categories WHERE category_id = ?");
    $stmt->execute([$cid]);
    $sub_cats[$cid] = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($sub_cats[$cid])) {
        // Seed some defaults
        $defaults = ($cid == $consumableId) 
            ? ['Office Supplies', 'Cleaning Supplies', 'Stationery']
            : ['Computer Hardware', 'Furniture', 'Electronics'];
        
        foreach ($defaults as $name) {
            $db->prepare("INSERT INTO sub_categories (category_id, name) VALUES (?, ?)")->execute([$cid, $name]);
            $sub_cats[$cid][] = $db->lastInsertId();
        }
    }
}

// 3. Ensure a Room and User exist for references
$roomId = $db->query("SELECT id FROM rooms LIMIT 1")->fetchColumn();
if (!$roomId) {
    // Seed a building and room if none
    $db->prepare("INSERT INTO buildings (name) VALUES ('Main Building')")->execute();
    $bid = $db->lastInsertId();
    $db->prepare("INSERT INTO rooms (building_id, name, floor) VALUES (?, 'Room 101', '1st Floor')")->execute([$bid]);
    $roomId = $db->lastInsertId();
}

$userId = $db->query("SELECT id FROM users LIMIT 1")->fetchColumn();
if (!$userId) {
    // Seed default admin
    $hash = password_hash('password', PASSWORD_DEFAULT);
    $db->prepare("INSERT INTO users (full_name, username, password_hash, role, status) VALUES ('Admin', 'admin', ?, 'Admin', 'active')")->execute([$hash]);
    $userId = $db->lastInsertId();
}

$outputFile = __DIR__ . '/../docs/stress_test_data.sql';
$fp = fopen($outputFile, 'w');

if (!$fp) {
    die("Unable to open file for writing.");
}

fwrite($fp, "-- Stress Test Data for SFIMS (Robust Generation)\n");
fwrite($fp, "-- NOTE: This script assumes you have Categories and Sub-Categories seeded.\n\n");

// We generate the SQL for ITEMS, BARCODES, INSTANCES
// Since the IDs might vary, we'll use variables in the SQL result as well.

// 1. Items
$uoms = ['pcs', 'box', 'set', 'unit', 'ream'];
$itemCount = 20000;

fwrite($fp, "INSERT INTO items (name, description, category_id, sub_category_id, uom, threshold_quantity, current_quantity) VALUES \n");

for ($i = 1; $i <= $itemCount; $i++) {
    $catId = ($i <= 10000) ? $consumableId : $fixedAssetId;
    $subCatId = $sub_cats[$catId][array_rand($sub_cats[$catId])];
    $name = ($catId == $consumableId ? "Stress Consumable " : "Stress Asset ") . $i;
    $desc = "Automatically generated stress test item #" . $i;
    $uom = $uoms[array_rand($uoms)];
    $qty = rand(10, 500);
    
    $line = "('" . addslashes($name) . "', '" . addslashes($desc) . "', $catId, $subCatId, '$uom', 5, $qty)";
    if ($i < $itemCount) {
        fwrite($fp, $line . ",\n");
    } else {
        fwrite($fp, $line . ";\n\n");
    }
}

// 2. Barcodes & Instances
fwrite($fp, "-- Generate Barcodes and Instances\n");
fwrite($fp, "SET @start_item_id = (SELECT IFNULL(MAX(id), 0) FROM items) - $itemCount + 1;\n\n");

// --- Process Consumables (Items 1-1000) ---
fwrite($fp, "-- 2.1 Consumables: One barcode per item type\n");
fwrite($fp, "INSERT INTO barcodes (item_id, barcode_value) \n");
fwrite($fp, "SELECT id, CONCAT('C-', id, '-', UNIX_TIMESTAMP()) \n");
fwrite($fp, "FROM items WHERE id >= @start_item_id AND category_id = $consumableId;\n\n");

// --- Process Fixed Assets (Items 1001-2000) ---
fwrite($fp, "-- 2.2 Fixed Assets: Unique barcode and instance per unit\n");
fwrite($fp, "-- We use a nested loop in the generator script to create individual unit records\n");

$assetQuery = $db->prepare("SELECT id, name, current_quantity FROM items WHERE id >= (SELECT IFNULL(MAX(id), 0) FROM items) - $itemCount + 1001 AND category_id = $fixedAssetId");
// Note: This logic assumes the generator is running on a DB where items were JUST inserted, 
// but since this script GENERATES an SQL file for LATER use, we need to handle the item IDs dynamically in the SQL.
// Instead of a massive number of INSERTS in PHP, we'll use a SQL sequence/pivot approach to expand units into instances.

fwrite($fp, "-- Helper procedure to generate instances (for assets)\n");
fwrite($fp, "DROP PROCEDURE IF EXISTS GenerateInstances;\n");
fwrite($fp, "DELIMITER //\n");
fwrite($fp, "CREATE PROCEDURE GenerateInstances(IN start_id INT, IN count_items INT, IN cat_id INT, IN rm_id INT)\n");
fwrite($fp, "BEGIN\n");
fwrite($fp, "    DECLARE i INT DEFAULT 0;\n");
fwrite($fp, "    DECLARE item_id_val INT;\n");
fwrite($fp, "    DECLARE qty INT;\n");
fwrite($fp, "    DECLARE cur_item CURSOR FOR SELECT id, current_quantity FROM items WHERE id >= start_id AND category_id = cat_id;\n");
fwrite($fp, "    DECLARE CONTINUE HANDLER FOR NOT FOUND SET item_id_val = NULL;\n");
fwrite($fp, "    \n");
fwrite($fp, "    OPEN cur_item;\n");
fwrite($fp, "    read_loop: LOOP\n");
fwrite($fp, "        FETCH cur_item INTO item_id_val, qty;\n");
fwrite($fp, "        IF item_id_val IS NULL THEN LEAVE read_loop; END IF;\n");
fwrite($fp, "        \n");
fwrite($fp, "        SET i = 0;\n");
fwrite($fp, "        WHILE i < qty DO\n");
fwrite($fp, "            -- Insert unique barcode\n");
fwrite($fp, "            INSERT INTO barcodes (item_id, barcode_value) VALUES (item_id_val, CONCAT('A-', item_id_val, '-', i, '-', UNIX_TIMESTAMP()));\n");
fwrite($fp, "            SET @last_barcode = LAST_INSERT_ID();\n");
fwrite($fp, "            \n");
fwrite($fp, "            -- Insert instance linked to barcode\n");
fwrite($fp, "            INSERT INTO item_instances (item_id, barcode_id, serial_number, status, room_id) \n");
fwrite($fp, "            VALUES (item_id_val, @last_barcode, CONCAT('SN-', item_id_val, '-', i), 'in-stock', rm_id);\n");
fwrite($fp, "            \n");
fwrite($fp, "            SET i = i + 1;\n");
fwrite($fp, "        END WHILE;\n");
fwrite($fp, "    END LOOP;\n");
fwrite($fp, "    CLOSE cur_item;\n");
fwrite($fp, "END //\n");
fwrite($fp, "DELIMITER ;\n\n");

fwrite($fp, "CALL GenerateInstances(@start_item_id + 1000, 1000, $fixedAssetId, $roomId);\n");
fwrite($fp, "DROP PROCEDURE IF EXISTS GenerateInstances;\n\n");

// 4. Transactions
fwrite($fp, "-- 3. Generate Transactions (Initial Load)\n");
fwrite($fp, "INSERT INTO transactions (item_id, type, quantity, date, performed_by, remarks) \n");
fwrite($fp, "SELECT id, 'RECEIVE', current_quantity, CURDATE(), $userId, 'Stress data initial load' \n");
fwrite($fp, "FROM items WHERE id >= @start_item_id;\n");

fclose($fp);
echo "SQL file generated successfully: docs/stress_test_data.sql\n";
echo "Run mysql < docs/stress_test_data.sql to populate the database.\n";
