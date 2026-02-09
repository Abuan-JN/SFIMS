<?php
/**
 * Barcode Print Module
 * 
 * Generates a printable label view for one or more asset instances.
 * This file is intended to be opened in a new tab for direct browser printing.
 * It integrates with the TEC-IT Barcode API to generate the barcode images.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
if (!is_logged_in()) {
    redirect('auth/login.php');
}

$db = Database::getInstance();

// Determine if we are printing a single instance or all instances of a specific item
$item_id = (int) ($_GET['item_id'] ?? 0);
$instance_id = (int) ($_GET['instance_id'] ?? 0);

$instances = [];

if ($instance_id) {
    // Fetch a single specific asset label
    $stmt = $db->prepare("SELECT ii.*, i.name as item_name, b.barcode_value FROM item_instances ii JOIN items i ON ii.item_id = i.id JOIN barcodes b ON ii.barcode_id = b.id WHERE ii.id = ?");
    $stmt->execute([$instance_id]);
    $instances = $stmt->fetchAll();
} elseif ($item_id) {
    // Fetch all asset labels belonging to a particular item type
    $stmt = $db->prepare("SELECT ii.*, i.name as item_name, b.barcode_value FROM item_instances ii JOIN items i ON ii.item_id = i.id JOIN barcodes b ON ii.barcode_id = b.id WHERE ii.item_id = ?");
    $stmt->execute([$item_id]);
    $instances = $stmt->fetchAll();
}

// Ensure there is actually data to print
if (!$instances) {
    die("No instances found to print.");
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Print Barcodes | SFIMS</title>
    <style>
        body {
            font-family: sans-serif;
            margin: 0;
            padding: 20px;
        }

        .label-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .label-card {
            border: 1px solid #ddd;
            padding: 15px;
            text-align: center;
            border-radius: 5px;
            page-break-inside: avoid;
        }

        .item-name {
            font-weight: bold;
            font-size: 14px;
            margin-bottom: 5px;
        }

        .barcode-img {
            max-width: 100%;
            height: auto;
        }

        .barcode-val {
            font-family: monospace;
            font-size: 12px;
            margin-top: 5px;
        }

        .serial {
            font-size: 10px;
            color: #666;
        }

        @media print {
            .no-print {
                display: none;
            }

            body {
                padding: 0;
            }
        }
    </style>
</head>

<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()"
            style="padding: 10px 20px; cursor: pointer; background: #0d6efd; color: white; border: none; border-radius: 4px;">Print
            Labels</button>
        <button onclick="window.close()"
            style="padding: 10px 20px; cursor: pointer; background: #6c757d; color: white; border: none; border-radius: 4px;">Close</button>
    </div>

    <div class="label-grid">
        <?php foreach ($instances as $inst): ?>
            <div class="label-card">
                <div class="item-name">
                    <?php echo h($inst['item_name']); ?>
                </div>
                <!-- Using TEC-IT Barcode API for demo/simple implementation -->
                <img src="https://barcode.tec-it.com/barcode.ashx?data=<?php echo urlencode($inst['barcode_value']); ?>&code=Code128&multiplebarcodes=false&translate-esc=false&unit=Fit&dpi=96&imagetype=Png&rotation=0&color=%23000000&bgcolor=%23ffffff&qunit=Mm&quiet=0"
                    alt="<?php echo h($inst['barcode_value']); ?>" class="barcode-img">
                <div class="barcode-val">
                    <?php echo h($inst['barcode_value']); ?>
                </div>
                <?php if ($inst['serial_number']): ?>
                    <div class="serial">S/N:
                        <?php echo h($inst['serial_number']); ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>

</html>