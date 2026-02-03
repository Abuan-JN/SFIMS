<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Fetch filters
$main_cats = $pdo->query("SELECT * FROM ref_main_categories ORDER BY name")->fetchAll();
$depts = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

// Build Query
$where = "1=1";
$params = [];

if (!empty($_GET['search'])) {
    $where .= " AND (item_name LIKE ? OR description LIKE ?)";
    $params[] = "%" . $_GET['search'] . "%";
    $params[] = "%" . $_GET['search'] . "%";
}
if (!empty($_GET['main_category'])) {
    // We store code in items table, but filter by ID here usually. 
    // Wait, items table HAS main_category_code (VARCHAR).
    // But ref table has code (INT).
    // We should filter by the code value.
    $stmt = $pdo->prepare("SELECT code FROM ref_main_categories WHERE main_id = ?");
    $stmt->execute([$_GET['main_category']]);
    $code = $stmt->fetchColumn();
    if ($code !== false) {
        $where .= " AND main_category_code = ?";
        $params[] = $code;
    }
}
if (isset($_GET['low_stock']) && $_GET['low_stock'] == 1) {
    $where .= " AND quantity_total <= per_item_threshold";
}

$sql = "SELECT i.*, m.name as main_name, s.name as sub_name 
        FROM items i 
        LEFT JOIN ref_main_categories m ON i.main_category_code = m.code 
        LEFT JOIN ref_subcategories s ON s.main_id = m.main_id AND s.code = i.subcategory_code
        WHERE $where ORDER BY i.item_name";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>Inventory Items</h2>
    <a href="item_form.php" class="btn btn-primary">Add New Item</a>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form method="GET" class="row g-2">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="Search items..." value="<?= htmlspecialchars($_GET['search'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <select name="main_category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($main_cats as $mc): ?>
                        <option value="<?= $mc['main_id'] ?>" <?= (isset($_GET['main_category']) && $_GET['main_category'] == $mc['main_id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($mc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" name="low_stock" value="1" id="lowStock" <?= isset($_GET['low_stock']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="lowStock">Low Stock Only</label>
                </div>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-secondary w-100">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="table-responsive">
    <table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>Item Name</th>
                <th>Category</th>
                <th>Qty</th>
                <th>Location</th>
                <th>Threshold</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr class="<?= $item['quantity_total'] <= $item['per_item_threshold'] ? 'table-warning' : '' ?>">
                <td><?= htmlspecialchars($item['item_name']) ?></td>
                <td>
                    <?= htmlspecialchars($item['main_name'] ?? $item['main_category_code']) ?> / <?= htmlspecialchars($item['sub_name'] ?? $item['subcategory_code']) ?>
                    <br><small class="text-muted"><?= $item['category_type'] ?></small>
                </td>
                <td><strong><?= $item['quantity_total'] ?></strong></td>
                <td><?= htmlspecialchars($item['location']) ?></td>
                <td><?= $item['per_item_threshold'] ?></td>
                <td>
                    <a href="item_form.php?id=<?= $item['item_id'] ?>" class="btn btn-sm btn-info">Edit</a>
                    <a href="item_barcodes.php?id=<?= $item['item_id'] ?>" class="btn btn-sm btn-secondary">Barcode</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($items)): ?>
                <tr><td colspan="6" class="text-center">No items found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include 'includes/footer.php'; ?>
