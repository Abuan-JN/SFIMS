<?php
// staff/disburse_print.php
require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$ids = isset($_GET['ids']) ? array_map('intval', $_GET['ids']) : [];
if (isset($_GET['id'])) $ids[] = (int)$_GET['id'];
if (empty($ids)) die("No transaction IDs provided.");

$db = Database::getInstance();
$placeholders = implode(',', array_fill(0, count($ids), '?'));

// Fetch transactions and item details
$stmt = $db->prepare("SELECT t.*, i.name as item_name, i.uom, d.name as dept_name, r.name as room_name, b.name as building_name, u.full_name as issuer_name, bc.barcode_value, ii.serial_number
                      FROM transactions t
                      JOIN items i ON t.item_id = i.id
                      LEFT JOIN departments d ON t.department_id = d.id
                      LEFT JOIN rooms r ON t.room_id = r.id
                      LEFT JOIN buildings b ON r.building_id = b.id
                      LEFT JOIN users u ON t.performed_by = u.id
                      LEFT JOIN item_instances ii ON t.instance_id = ii.id
                      LEFT JOIN barcodes bc ON ii.barcode_id = bc.id
                      WHERE t.id IN ($placeholders)
                      ORDER BY t.created_at ASC");
$stmt->execute($ids);
$transactions = $stmt->fetchAll();

if (!$transactions) die("Transactions not found.");
$t = $transactions[0]; // Header info from first transaction
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Disbursement Form - #<?php echo $t['id']; ?></title>
    <style>
        body { font-family: sans-serif; padding: 40px; color: #333; line-height: 1.6; }
        .header { text-align: center; margin-bottom: 40px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 24px; }
        .row { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .col { flex: 1; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #f4f4f4; }
        .footer { margin-top: 60px; display: flex; justify-content: space-between; }
        .sig { border-top: 1px solid #333; width: 250px; text-align: center; margin-top: 50px; }
        @media print { .btn-print { display: none; } }
    </style>
</head>
<body>
    <div style="text-align: right;" class="btn-print">
        <button onclick="window.print()" style="padding: 8px 16px; cursor: pointer; background: #0d6efd; color: #fff; border: none; border-radius: 4px;">Print Now</button>
    </div>

    <div class="header">
        <h1>PAMANTASAN NG LUNGSOD NG MUNTINLUPA</h1>
        <p>Supply and Facility Inventory Management System (SFIMS)</p>
        <h2>INVENTORY DISBURSEMENT FORM</h2>
    </div>

    <div class="row">
        <div class="col">
            <strong>Date:</strong> <?php echo date('F d, Y', strtotime($t['date'])); ?><br>
            <strong>Trans ID:</strong> #<?php echo str_pad($t['id'], 6, '0', STR_PAD_LEFT); ?>
        </div>
        <div class="col" style="text-align: right;">
            <strong>Department:</strong> <?php echo h($t['dept_name']); ?><br>
            <strong>Location:</strong> <?php echo $t['room_name'] ? h($t['building_name'] . ' - ' . $t['room_name']) : 'N/A'; ?>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Item Description</th>
                <th>Quantity</th>
                <th>Unit</th>
                <th>Tracking / Barcode</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($transactions as $row): ?>
            <tr>
                <td><?php echo h($row['item_name']); ?></td>
                <td><?php echo $row['quantity']; ?></td>
                <td><?php echo h($row['uom']); ?></td>
                <td>
                    <?php if ($row['barcode_value']): ?>
                        <strong><?php echo h($row['barcode_value']); ?></strong><br>
                        <small>S/N: <?php echo h($row['serial_number'] ?: 'N/A'); ?></small>
                    <?php else: ?>
                        Consumable Stock
                    <?php endif; ?>
                </td>
                <td><?php echo h($row['remarks']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <div>
            <div class="sig">Prepared/Issued By</div>
            <p><?php echo h($t['issuer_name']); ?></p>
        </div>
        <div>
            <div class="sig">Received By (Signature over Printed Name)</div>
            <p><?php echo h($t['recipient_name'] ?: '____________________'); ?></p>
        </div>
    </div>

    <script>
        // Auto print window
        // window.print();
    </script>
</body>
</html>
