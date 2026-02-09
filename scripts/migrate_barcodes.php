<?php
/**
 * Database Migration: Barcode Refactoring
 * 
 * This maintenance script upgrades the database schema to support the independent 'barcodes' table.
 * 1. Checks for CLI or authorized Web execution.
 * 2. Creates the `barcodes` table with strict indexing.
 * 3. Extracts existing barcode strings from `item_instances` and populates the new table.
 * 4. Relinks all physical item instances to their new normalized barcode IDs.
 * 5. Ensures referential integrity using transactions and foreign key constraints.
 * 
 * @usage cli: php migrate_barcodes.php
 * @usage web: browse to /scripts/migrate_barcodes.php?run=1
 */

require_once __DIR__ . '/../config/database.php';

// Execution Guard
if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    die("This script must be run from the command line or with ?run=1");
}

$db = Database::getInstance();

try {
    $db->beginTransaction();

    echo "Creating barcodes table if not exists...\n";
    $db->exec("CREATE TABLE IF NOT EXISTS barcodes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        item_id INT NOT NULL,
        barcode_value VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
    ) ENGINE = InnoDB");

    echo "Migrating existing barcodes...\n";
    $stmt = $db->query("SELECT DISTINCT item_id, barcode_value FROM item_instances");
    $count = 0;
    while ($row = $stmt->fetch()) {
        $check = $db->prepare("SELECT id FROM barcodes WHERE barcode_value = ?");
        $check->execute([$row['barcode_value']]);
        if (!$check->fetch()) {
            $insert = $db->prepare("INSERT INTO barcodes (item_id, barcode_value) VALUES (?, ?)");
            $insert->execute([$row['item_id'], $row['barcode_value']]);
            $count++;
        }
    }
    echo "Migrated $count barcodes.\n";

    echo "Updating item_instances references...\n";
    // Check if barcode_id column exists
    $columns = $db->query("SHOW COLUMNS FROM item_instances LIKE 'barcode_id'")->fetch();
    if (!$columns) {
        $db->exec("ALTER TABLE item_instances ADD COLUMN barcode_id INT NULL AFTER serial_number");
        $db->exec("ALTER TABLE item_instances ADD FOREIGN KEY (barcode_id) REFERENCES barcodes(id) ON DELETE CASCADE");
    }

    $db->exec("UPDATE item_instances ii 
               SET barcode_id = (SELECT id FROM barcodes b WHERE b.barcode_value = ii.barcode_value)
               WHERE barcode_id IS NULL");

    echo "Migration complete. You may now manually drop the barcode_value column from item_instances if desired.\n";

    $db->commit();
} catch (Exception $e) {
    if ($db->inTransaction()) $db->rollBack();
    die("Migration failed: " . $e->getMessage() . "\n");
}
