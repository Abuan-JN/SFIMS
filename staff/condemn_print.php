<?php
/**
 * Turn Over from Condemned Items (Institutional Form)
 * 
 * Generates the formal turnover document for items marked as condemned.
 * 1. Tracks acquisition history and condition.
 * 2. Provides signature blocks for Department Heads and Admin Officers.
 * 3. Aligns with the university's standardized Turn Over form.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$ids = isset($_GET['ids']) ? array_map('intval', $_GET['ids']) : [];
if (isset($_GET['id'])) $ids[] = (int)$_GET['id'];
$filter = $_GET['filter'] ?? null;

$assets = [];

if (!empty($ids)) {
    // Original behavior: Fetch specific IDs
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT ii.*, i.name as item_name, i.uom, bc.barcode_value, 
                   t.date as condemn_date, t.remarks as condemn_remarks
            FROM item_instances ii
            JOIN items i ON ii.item_id = i.id
            LEFT JOIN barcodes bc ON ii.barcode_id = bc.id
            LEFT JOIN transactions t ON t.instance_id = ii.id AND t.type = 'CONDEMN'
            WHERE ii.id IN ($placeholders)";
    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    $assets = $stmt->fetchAll();
} elseif ($filter) {
    // New behavior: Fetch based on filter (more efficient for 'Print All')
    $where_sql = "WHERE ii.status IN ('condemned-serviced', 'condemned-trash')";
    $filter_params = [];
    if ($filter !== 'all') {
        $where_sql = "WHERE ii.status = ?";
        $filter_params[] = $filter;
    }
    
    $sql = "SELECT ii.*, i.name as item_name, i.uom, bc.barcode_value, 
                   t.date as condemn_date, t.remarks as condemn_remarks
            FROM item_instances ii
            JOIN items i ON ii.item_id = i.id
            LEFT JOIN barcodes bc ON ii.barcode_id = bc.id
            LEFT JOIN transactions t ON t.instance_id = ii.id AND t.type = 'CONDEMN'
            $where_sql
            ORDER BY ii.last_updated DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($filter_params);
    $assets = $stmt->fetchAll();
} else {
    die("No asset IDs or filter provided.");
}

if (!$assets) die("No condemned assets found matching the criteria.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Turn Over - Condemned Items</title>
    <style>
        body { font-family: 'Arial', sans-serif; font-size: 10pt; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; }
        .header h1 { font-size: 14pt; margin: 0; }
        .sub-header { margin: 20px 0; font-weight: bold; }
        
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #000; padding: 6px; text-align: center; }
        th { background: #eee; font-size: 9pt; }
        .text-left { text-align: left; }

        .form-meta { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .cert-box { margin-top: 40px; }
        .sig-row { display: flex; justify-content: space-between; margin-top: 40px; }
        .sig-line { border-bottom: 1px solid #000; width: 250px; text-align: center; margin-bottom: 5px; }

        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; cursor: pointer;">Print Form</button>
    </div>

    <div class="header">
        <h1>PAMANTASAN NG LUNGSOD NG MUNTINLUPA</h1>
        <h2>TURN OVER FROM CONDEMNED ITEMS</h2>
    </div>

    <div class="form-meta">
        <div><strong>From:</strong> _________________________</div>
        <div><strong>To:</strong> ___________________________</div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 25%;">Item Description</th>
                <th style="width: 12%;">CGM Number</th>
                <th style="width: 13%;">Serial Number</th>
                <th style="width: 8%;">Qty</th>
                <th style="width: 12%;">Date Acquired</th>
                <th style="width: 10%;">Value</th>
                <th style="width: 10%;">Condition</th>
                <th style="width: 10%;">Covering MRS/Date</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($assets as $a): ?>
            <tr>
                <td class="text-left"><?php echo h($a['item_name']); ?></td>
                <td><?php echo h($a['barcode_value']); ?></td>
                <td><?php echo h($a['serial_number'] ?: '--'); ?></td>
                <td>1 <?php echo h($a['uom']); ?></td>
                <td>________________</td>
                <td>________________</td>
                <td><?php echo ucfirst(str_replace('condemned-', '', $a['status'])); ?></td>
                <td><?php echo $a['condemn_date'] ? date('m/d/Y', strtotime($a['condemn_date'])) : '____'; ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="cert-box">
        <p>"I hereby certify that the above property/properties are turned over in the condition indicated."</p>
    </div>

    <div class="sig-row">
        <div>
            <div class="sig-line">&nbsp;</div>
            <div style="text-align: center;">Date</div>
        </div>
        <div>
            <div class="sig-line">&nbsp;</div>
            <div style="text-align: center;">Head of Dept / Admin Officer</div>
        </div>
    </div>

</body>
</html>
