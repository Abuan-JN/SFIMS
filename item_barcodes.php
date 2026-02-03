<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? null;
if (!$id) { header("Location: items.php"); exit(); }

// Fetch Item details
$stmt = $pdo->prepare("SELECT i.*, m.code as m_code, s.code as s_code 
                       FROM items i 
                       LEFT JOIN ref_main_categories m ON i.main_category_code = m.code 
                       LEFT JOIN ref_subcategories s ON s.main_id = m.main_id AND s.code = i.subcategory_code
                       WHERE item_id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) { die("Item not found"); }

// Fetch existing barcodes
$barcodes_stmt = $pdo->prepare("SELECT * FROM barcodes WHERE item_id = ?");
$barcodes_stmt->execute([$id]);
$barcodes = $barcodes_stmt->fetchAll();

include 'includes/header.php';
?>

<h2>Manage Barcodes: <?= htmlspecialchars($item['item_name']) ?></h2>

<div class="card mb-3">
    <div class="card-body">
        <p><strong>Category:</strong> <?= $item['category_type'] ?></p>
        <p><strong>Code Prefix:</strong> <?= $item['m_code'] ?>/<?= $item['s_code'] ?>/...</p>
        
        <?php if($item['category_type'] == 'Non-Expendable'): ?>
            <div class="alert alert-info">Individual barcodes are tracked for each unit.</div>
        <?php else: ?>
            <div class="alert alert-info">Expendable items typically use a single generic barcode or batch barcodes.</div>
            <a href="print_label.php?code=<?= $item['m_code'] ?>/<?= $item['s_code'] ?>/GENERIC&name=<?= urlencode($item['item_name']) ?>" target="_blank" class="btn btn-primary">Print Generic Label</a>
        <?php endif; ?>
    </div>
</div>

<?php if($item['category_type'] == 'Non-Expendable'): ?>
<h3>Existing Units (<?= count($barcodes) ?>)</h3>
<!-- Note: Generation happens in Receive, but we can list here -->
<div class="table-responsive">
    <table class="table table-striped table-sm">
        <thead>
            <tr>
                <th>Barcode</th>
                <th>Status</th>
                <th>Current Dept</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($barcodes as $b): ?>
            <tr>
                <td><?= htmlspecialchars($b['barcode_value']) ?></td>
                <td><?= htmlspecialchars($b['status']) ?></td>
                <td><?= htmlspecialchars($b['current_department_id'] ?? '-') ?></td>
                <td>
                    <a href="print_label.php?code=<?= urlencode($b['barcode_value']) ?>&name=<?= urlencode($item['item_name']) ?>" target="_blank" class="btn btn-sm btn-secondary">Print</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
