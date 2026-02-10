<?php
/**
 * Institutional Inventory Disbursement Form
 * 
 * Generates printable forms aligned with university standards:
 * 1. Material Requisition Slip (MRS) for Consumables.
 * 2. Specialized Disbursement Form for Fixed Assets.
 * 
 * Controls Header, Columns, and Signature blocks based on Item Category.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$ids = isset($_GET['ids']) ? array_map('intval', $_GET['ids']) : [];
if (isset($_GET['id'])) $ids[] = (int)$_GET['id'];
if (empty($ids)) die("No transaction IDs provided.");

$db = Database::getInstance();
$placeholders = implode(',', array_fill(0, count($ids), '?'));

// Enhanced Query: Fetching Category Name to determine layout
$stmt = $db->prepare("SELECT t.*, i.name as item_name, i.uom, c.name as category_name, 
                             d.name as dept_name, r.name as room_name, b.name as building_name, 
                             u.full_name as issuer_name, bc.barcode_value, ii.serial_number
                      FROM transactions t
                      JOIN items i ON t.item_id = i.id
                      LEFT JOIN categories c ON i.category_id = c.id
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

// Determine primary category for form layout
$category = $transactions[0]['category_name'];
$isFixedAsset = (stripos($category, 'Fixed Asset') !== false);
$t = $transactions[0]; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo $isFixedAsset ? 'Fixed Asset Disbursement' : 'Material Requisition Slip'; ?> - #<?php echo $t['id']; ?></title>
    <style>
        @page { size: auto; margin: 10mm; }
        body { font-family: 'Arial', sans-serif; font-size: 11pt; color: #000; margin: 0; padding: 20px; }
        
        /* Header Organization */
        .inst-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .logo-box { width: 80px; text-align: left; }
        .logo-box img { max-width: 100%; height: auto; }
        .title-box { flex-grow: 1; text-align: left; padding-left: 20px; }
        .title-box h1 { font-size: 14pt; margin: 0; font-weight: bold; }
        .title-box h2 { font-size: 11pt; margin: 5px 0; font-weight: bold; text-transform: uppercase; }
        .meta-box { width: 220px; font-size: 9pt; text-align: right; border-left: 1px solid #ddd; padding-left: 10px; }
        .meta-line { margin-bottom: 3px; }

        /* Table Structure */
        table { width: 100%; border-collapse: collapse; margin-top: 20px; table-layout: fixed; }
        th, td { border: 1px solid #000; padding: 8px; text-align: center; overflow: hidden; }
        th { background: #f2f2f2; font-weight: bold; font-size: 10pt; }
        .text-left { text-align: left; }

        /* Footer / Signatures */
        .inst-footer { margin-top: 40px; font-size: 9pt; border-top: 1px solid #000; padding-top: 10px; }
        .sig-container { display: flex; justify-content: space-between; margin-top: 40px; }
        .sig-box { width: 45%; }
        .sig-line { border-top: 1px solid #000; margin-top: 45px; padding-top: 5px; text-align: center; font-weight: bold; }
        .sig-sub { font-size: 8pt; text-align: center; color: #444; }

        .form-info { display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 20px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .info-item { width: 48%; margin-bottom: 5px; font-size: 10pt; }

        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: right;">
        <button onclick="window.print()" style="padding: 10px 20px; background: #007bff; color: #fff; border: none; border-radius: 5px; cursor: pointer;">Print Document</button>
    </div>

    <!-- Institutional Header -->
    <div class="inst-header">
        <div class="logo-box">
            <!-- Institutional Logo Placeholder -->
            <div style="width: 70px; height: 70px; border: 2px solid #333; display: flex; align-items: center; justify-content: center; font-size: 8pt; font-weight: bold; text-align: center;">UNIVERSITY LOGO</div>
        </div>
        <div class="title-box">
            <h1>PAMANTASAN NG LUNGSOD NG MUNTINLUPA</h1>
            <?php if (!$isFixedAsset): ?>
                <h2>Supply Property and Management Office Material Requisition Slip</h2>
            <?php else: ?>
                <h2>Fixed Assets Disbursement & Inventory Control</h2>
            <?php endif; ?>
        </div>
        <div class="meta-box">
            <div class="meta-line">Form No. ______________</div>
            <div class="meta-line">Effectivity: <?php echo date('d/m/Y'); ?></div>
            <div class="meta-line">Approved by: ____________</div>
        </div>
    </div>

    <div class="form-info">
        <div class="info-item"><strong>Department:</strong> <?php echo h($t['dept_name']); ?></div>
        <div class="info-item"><strong>Date:</strong> <?php echo date('F d, Y', strtotime($t['date'])); ?></div>
        <div class="info-item"><strong>Location:</strong> <?php echo $t['room_name'] ? h($t['building_name'] . ' - ' . $t['room_name']) : 'Central Warehouse'; ?></div>
        <div class="info-item"><strong>Control/Reference No.:</strong> #<?php echo str_pad($t['id'], 6, '0', STR_PAD_LEFT); ?></div>
    </div>

    <table>
        <thead>
            <?php if (!$isFixedAsset): ?>
                <!-- Consumables Layout -->
                <tr>
                    <th style="width: 15%;">Control No.</th>
                    <th style="width: 15%;">Date</th>
                    <th style="width: 35%;">Item / Description</th>
                    <th style="width: 10%;">No. of Items</th>
                    <th style="width: 15%;">Received By</th>
                    <th style="width: 10%;">Signature</th>
                </tr>
            <?php else: ?>
                <!-- Fixed Assets Layout -->
                <tr>
                    <th style="width: 10%;">Quantity</th>
                    <th style="width: 40%;">Description / Serial Number</th>
                    <th style="width: 25%;">Location</th>
                    <th style="width: 25%;">Property No. (Barcode)</th>
                </tr>
            <?php endif; ?>
        </thead>
        <tbody>
            <?php foreach ($transactions as $row): ?>
                <?php if (!$isFixedAsset): ?>
                    <tr>
                        <td>#<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($row['date'])); ?></td>
                        <td class="text-left"><?php echo h($row['item_name']); ?></td>
                        <td><?php echo $row['quantity'] . ' ' . $row['uom']; ?></td>
                        <td><?php echo h($row['recipient_name'] ?: '---'); ?></td>
                        <td></td>
                    </tr>
                <?php else: ?>
                    <tr>
                        <td><?php echo $row['quantity']; ?></td>
                        <td class="text-left">
                            <strong><?php echo h($row['item_name']); ?></strong><br>
                            <small>S/N: <?php echo h($row['serial_number'] ?: 'N/A'); ?></small>
                        </td>
                        <td><?php echo h($row['room_name'] ?: ($row['dept_name'] ?: 'N/A')); ?></td>
                        <td><strong><?php echo h($row['barcode_value']); ?></strong></td>
                    </tr>
                <?php endif; ?>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Signature Blocks -->
    <div class="sig-container">
        <?php if (!$isFixedAsset): ?>
            <div class="sig-box">
                <div class="sig-line"><?php echo h($t['issuer_name']); ?></div>
                <div class="sig-sub">Issued by: SPMO Personnel</div>
            </div>
            <div class="sig-box">
                <div class="sig-line"><?php echo h($t['recipient_name'] ?: '____________________'); ?></div>
                <div class="sig-sub">Recipient Name & Signature</div>
            </div>
        <?php else: ?>
            <div class="sig-box">
                <strong>Received from:</strong>
                <div class="sig-line">____________________</div>
                <div class="sig-sub">Head SPMO / Procurement Office</div>
                <div class="sig-line">____________________</div>
                <div class="sig-sub">Property Custodian & Inventory Control</div>
            </div>
            <div class="sig-box">
                <strong>Received by:</strong>
                <div class="sig-line"><?php echo h($t['recipient_name'] ?: '____________________'); ?></div>
                <div class="sig-sub">Name and Signature</div>
                <div style="margin-top: 20px; text-align: center;">
                    Date: ____________________
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Institutional Footer (Consumables only as per spec) -->
    <?php if (!$isFixedAsset): ?>
        <div class="inst-footer">
            <div style="display: flex; justify-content: space-between;">
                <div>Revision Date: __________</div>
                <div>Nature of Revision: __________</div>
                <div>Document Distribution</div>
                <div>Page 1 of 1</div>
            </div>
        </div>
    <?php endif; ?>

    <script>
        // Optional: window.print();
    </script>
</body>
</html>
