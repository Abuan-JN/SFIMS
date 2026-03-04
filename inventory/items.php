<?php
/**
 * Inventory List Module
 * 
 * Provides a searchable and filterable overview of all inventory items.
 * Supports filtering by Name/Description, Category, and Active/Inactive status.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Access Control: Redirect to login if user session is invalid
require_role();

$db = Database::getInstance();

// Retrieve filter parameters from the URL
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$status = $_GET['status'] ?? '';

// Base query to fetch items with their category names
$sql = "SELECT i.*, c.name as category_name 
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id 
        WHERE 1=1";
$params = [];

// Dynamic SQL construction based on active filters
if ($search) {
    $sql .= " AND (i.name LIKE ? OR i.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($category) {
    $sql .= " AND i.category_id = ?";
    $params[] = $category;
}

if ($status) {
    $sql .= " AND i.status = ?";
    $params[] = $status;
}

// Order alphabetically by name
$sql .= " ORDER BY i.name ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Categories retrieval for the dropdown filter
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$page_title = 'Inventory List';
require_once '../partials/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-4">
        <h2 class="fw-bold">Inventory List</h2>
    </div>
    <div class="col-md-8 text-end">
        <a href="../staff/receive.php" class="btn btn-success me-2"><i class="bi bi-box-seam me-1"></i> Receive Stock</a>
        <a href="../staff/disburse.php" class="btn btn-danger me-2"><i class="bi bi-box-arrow-right me-1"></i> Disburse Stock</a>
        <a href="../staff/items_add.php" class="btn btn-primary me-2"><i class="bi bi-plus-lg me-1"></i> Add New Item</a>
        <a href="../staff/import_items.php" class="btn btn-outline-primary"><i class="bi bi-cloud-upload me-1"></i> Import Items</a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <div class="input-group">
                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search items..."
                        value="<?php echo h($search); ?>">
                </div>
            </div>
            <div class="col-md-3">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo h($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">All Status</option>
                    <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-secondary">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Item Name</th>
                        <th>Category</th>
                        <th>UOM</th>
                        <th>Stock Level</th>
                        <th>Current Qty</th>
                        <th class="text-end pe-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($items): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-primary">
                                        <?php echo h($item['name']); ?>
                                    </div>
                                    <div class="small text-muted">
                                        <?php echo h(substr($item['description'], 0, 50)) . (strlen($item['description']) > 50 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php echo h($item['category_name']); ?>
                                </td>
                                <td>
                                    <?php echo h($item['uom']); ?>
                                </td>
                                <td>
                                    <?php if ($item['current_quantity'] <= $item['threshold_quantity']): ?>
                                        <span class="badge bg-danger">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">
                                    <?php echo $item['current_quantity']; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <a href="item_details.php?id=<?php echo $item['id']; ?>"
                                        class="btn btn-sm btn-outline-primary">View</a>
                                    <a href="../staff/items_edit.php?id=<?php echo $item['id']; ?>"
                                        class="btn btn-sm btn-outline-secondary">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-1 d-block mb-3"></i>
                                No items found matching your criteria.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>