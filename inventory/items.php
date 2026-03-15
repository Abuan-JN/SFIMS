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

// Handle Item Deletion
if ($_SESSION['role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $delete_id = $_POST['delete_item'];
    
    try {
        $db->beginTransaction();
        
        // Let MySQL CASCADE handle deleting barcodes, instances, and transactions linked to this item.
        $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$delete_id]);
        
        $db->commit();
        set_flash_message('success', 'Item and all associated data deleted successfully.');
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Item Deletion Error: " . $e->getMessage());
        set_flash_message('danger', 'Error deleting item. Please check error logs.');
    }
    
    // Redirect to refresh the list
    header("Location: items.php");
    exit;
}

$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$stock_level = $_GET['stock_level'] ?? '';

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

if ($stock_level) {
    if ($stock_level === 'in_stock') {
        $sql .= " AND i.current_quantity > i.threshold_quantity";
    } elseif ($stock_level === 'low_stock') {
        $sql .= " AND i.current_quantity <= i.threshold_quantity AND i.current_quantity > 0";
    } elseif ($stock_level === 'out_of_stock') {
        $sql .= " AND i.current_quantity = 0";
    }
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
        <h2 class="fw-bold">Inventory List
            <span class="badge bg-secondary fs-6 ms-2" style="font-size:0.8rem!important;vertical-align:middle;"><?php echo count($items); ?></span>
        </h2>
    </div>
    <div class="col-md-8 d-flex justify-content-md-end gap-2 flex-nowrap">
        <a href="../staff/receive.php" class="btn btn-success"><i class="bi bi-box-seam me-1"></i> Receive Stock</a>
        <a href="../staff/disburse.php" class="btn btn-danger"><i class="bi bi-box-arrow-right me-1"></i> Disburse Stock</a>
        <a href="../staff/items_add.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> Add New Item</a>
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
                <select name="stock_level" class="form-select">
                    <option value="">All Stock Levels</option>
                    <option value="in_stock" <?php echo $stock_level === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?php echo $stock_level === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?php echo $stock_level === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
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
                                    <?php if ($item['category_name'] === 'Fixed Assets'): ?>
                                        <span class="badge bg-light text-dark border">Tracked Asset</span>
                                    <?php elseif ($item['current_quantity'] == 0): ?>
                                        <span class="badge bg-secondary">Out of Stock</span>
                                    <?php elseif ($item['current_quantity'] <= $item['threshold_quantity']): ?>
                                        <span class="badge bg-danger">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold">
                                    <?php echo $item['current_quantity']; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="item_details.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary" title="View Details">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="../staff/items_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary" title="Edit Item">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <?php if ($_SESSION['role'] === 'Admin'): ?>
                                        <form method="POST" action="" class="d-inline ms-1" onsubmit="return confirm('WARNING: Are you sure you want to delete this item? This action is permanent and will cascade to delete all instances and transactions associated with this item!');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="delete_item" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Item">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center py-5">
                                <i class="bi bi-box-seam text-muted" style="font-size:3rem;"></i>
                                <p class="fw-bold mt-3 mb-1">No items found</p>
                                <p class="text-muted small mb-3"><?php echo ($search || $category || $stock_level) ? 'No items match your current filters. Try clearing them.' : 'The inventory is empty. Start by adding your first item!'; ?></p>
                                <?php if (!$search && !$category && !$stock_level): ?>
                                <a href="../staff/items_add.php" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Add First Item</a>
                                <?php else: ?>
                                <a href="items.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-circle me-1"></i> Clear Filters</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>