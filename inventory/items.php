<?php
/**
 * Inventory List Module
 * 
 * Provides a searchable and filterable overview of all inventory items.
 * Supports filtering by Name/Description, Category, Sub-Category, and Stock Level.
 * Implements server-side pagination for optimal performance with large datasets.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Access Control: Redirect to login if user session is invalid
require_role();

$db = Database::getInstance();

// --- Handle Item Deletion (Admin Only) ---
if ($_SESSION['role'] === 'Admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $delete_id = (int)$_POST['delete_item'];
    
    try {
        $db->beginTransaction();
        $stmt = $db->prepare("DELETE FROM items WHERE id = ?");
        $stmt->execute([$delete_id]);
        $db->commit();
        set_flash_message('success', 'Item and all associated data deleted successfully.');
    } catch (PDOException $e) {
        $db->rollBack();
        error_log("Item Deletion Error: " . $e->getMessage());
        set_flash_message('danger', 'Error deleting item.');
    }
    header("Location: items.php");
    exit;
}

// --- Filter & Pagination Parameters ---
$search = trim($_GET['search'] ?? '');
$category_id = (int)($_GET['category'] ?? 0);
$sub_category_id = (int)($_GET['sub_category'] ?? 0);
$stock_level = $_GET['stock_level'] ?? '';

$items_per_page = 50;
$p = max(1, (int)($_GET['page'] ?? 1)); // Renamed from $current_page to avoid conflict with header.php
$offset = ($p - 1) * $items_per_page;

// --- Built Filter Query Parts ---
$where_clauses = ["1=1"];
$params = [];

if ($search !== '') {
    $where_clauses[] = "(i.name LIKE ? OR i.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
}

if ($category_id > 0) {
    $where_clauses[] = "i.category_id = ?";
    $params[] = $category_id;
}

if ($sub_category_id > 0) {
    $where_clauses[] = "i.sub_category_id = ?";
    $params[] = $sub_category_id;
}

if ($stock_level !== '') {
    if ($stock_level === 'in_stock') {
        $where_clauses[] = "i.current_quantity > i.threshold_quantity";
    } elseif ($stock_level === 'low_stock') {
        $where_clauses[] = "i.current_quantity <= i.threshold_quantity AND i.current_quantity > 0";
    } elseif ($stock_level === 'out_of_stock') {
        $where_clauses[] = "i.current_quantity = 0";
    }
}

$where_sql = implode(" AND ", $where_clauses);

// --- Get Total Count for Pagination ---
$count_query = "SELECT COUNT(*) FROM items i WHERE $where_sql";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_items = (int)$count_stmt->fetchColumn();
$total_pages = (int)ceil($total_items / $items_per_page);

// Ensure current page is within valid range
if ($total_pages > 0 && $p > $total_pages) {
    $p = $total_pages;
    $offset = ($p - 1) * $items_per_page;
}

// --- Fetch Items for Current Page ---
$sql = "SELECT i.*, c.name as category_name, sc.name as sub_category_name
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN sub_categories sc ON i.sub_category_id = sc.id
        WHERE $where_sql
        ORDER BY i.name ASC 
        LIMIT $items_per_page OFFSET $offset";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// --- Prepare Pagination URLs ---
$query_data = $_GET;
unset($query_data['page']);
$base_query_string = http_build_query($query_data);
$link_suffix = $base_query_string ? '&' . $base_query_string : '';

// --- Static Data for Filters ---
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$all_sub_cats = $db->query("SELECT * FROM sub_categories ORDER BY name ASC")->fetchAll();

$page_title = 'Inventory List';
require_once '../partials/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-4">
        <h2 class="fw-bold mb-1">Inventory List
            <span class="badge bg-secondary fs-6 ms-2" style="font-size:0.8rem!important;vertical-align:middle;"><?php echo number_format($total_items); ?></span>
        </h2>
        <p class="text-muted small mb-0">Browse, search, and manage all catalogued items.</p>
    </div>
    <div class="col-md-8 d-flex justify-content-md-end align-items-center gap-2 flex-wrap mt-3 mt-md-0">
        <div class="dropdown d-none" id="bulkActionContainer">
            <button class="btn btn-dark dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-list-check me-1"></i> Bulk Actions (<span id="selectedCount">0</span>)
            </button>
            <ul class="dropdown-menu shadow">
                <li><a class="dropdown-item fw-bold text-danger" href="javascript:void(0)" id="bulkDisburseBtn"><i class="bi bi-arrow-up-square-fill me-2"></i>Bulk Disburse</a></li>
                <li><a class="dropdown-item" href="javascript:void(0)" id="bulkReceiveBtn"><i class="bi bi-arrow-down-square-fill me-2"></i>Bulk Receive</a></li>
            </ul>
        </div>
        <a href="../staff/receive.php" class="btn btn-success"><i class="bi bi-arrow-down-square-fill me-1"></i> <span class="d-none d-sm-inline">Receive Stock</span></a>
        <a href="../staff/disburse.php" class="btn btn-danger"><i class="bi bi-arrow-up-square-fill me-1"></i> <span class="d-none d-sm-inline">Disburse Stock</span></a>
        <a href="../staff/items_add.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i> <span class="d-none d-sm-inline">Add New Item</span></a>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
            <div class="col-md-4">
                <div class="input-group">
                    <button type="submit" class="btn bg-white border border-end-0"><i class="bi bi-search"></i></button>
                    <input type="text" name="search" class="form-control border-start-0" placeholder="Search items..."
                        value="<?php echo h($search); ?>" autocomplete="off">
                </div>
            </div>
            <div class="col-md-2">
                <select name="category" id="categoryFilter" class="form-select">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                            <?php echo h($cat['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="sub_category" id="subCategoryFilter" class="form-select">
                    <option value="">All Sub-Categories</option>
                    <?php foreach ($all_sub_cats as $sc): ?>
                        <option value="<?php echo $sc['id']; ?>"
                            data-cat="<?php echo $sc['category_id']; ?>"
                            <?php echo $sub_category_id == $sc['id'] ? 'selected' : ''; ?>
                            <?php echo ($category_id && $sc['category_id'] != $category_id) ? 'class="d-none"' : ''; ?>>
                            <?php echo h($sc['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select name="stock_level" class="form-select">
                    <option value="">All Stock Levels</option>
                    <option value="in_stock" <?php echo $stock_level === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?php echo $stock_level === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?php echo $stock_level === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            <div class="col-md-1 d-grid">
                <button type="submit" class="btn btn-secondary"><i class="bi bi-funnel"></i></button>
            </div>
            <div class="col-md-1 d-grid">
                <a href="items.php" class="btn btn-outline-secondary" title="Clear Filters"><i class="bi bi-x-circle"></i></a>
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
                        <th class="ps-4" style="width: 40px;"><input class="form-check-input" type="checkbox" id="selectAll"></th>
                        <th>Item Name</th>
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
                                <td class="ps-4"><input class="form-check-input item-checkbox" type="checkbox" value="<?php echo $item['id']; ?>"></td>
                                <td>
                                    <div class="fw-semibold text-primary"><?php echo h($item['name']); ?></div>
                                    <div class="small text-muted">
                                        <?php if ($item['sub_category_name']): ?>
                                            <span class="badge bg-secondary me-1" style="font-size:0.7rem"><?php echo h($item['sub_category_name']); ?></span>
                                        <?php endif; ?>
                                        <?php echo h(substr($item['description'] ?? '', 0, 50)) . (strlen($item['description'] ?? '') > 50 ? '...' : ''); ?>
                                    </div>
                                </td>
                                <td><?php echo h($item['category_name']); ?></td>
                                <td><?php echo h($item['uom']); ?></td>
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
                                <td class="fw-bold"><?php echo (int)$item['current_quantity']; ?></td>
                                <td class="text-end pe-4">
                                    <div class="btn-group">
                                        <a href="item_details.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-eye"></i> View</a>
                                        <a href="../staff/items_edit.php?id=<?php echo $item['id']; ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i> Edit</a>
                                        <?php if ($_SESSION['role'] === 'Admin'): ?>
                                        <form method="POST" action="" class="d-inline ms-1" onsubmit="return confirm('Delete this item?');">
                                            <?php csrf_field(); ?>
                                            <input type="hidden" name="delete_item" value="<?php echo $item['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="bi bi-box-seam text-muted" style="font-size:3rem;"></i>
                                <p class="fw-bold mt-3 mb-1">No items found</p>
                                <p class="text-muted small">Try adjusting your filters.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
        <div class="d-flex justify-content-between align-items-center p-3 border-top">
            <div class="small text-muted">
                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $items_per_page, $total_items); ?> of <?php echo number_format($total_items); ?> entries
            </div>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?php echo $p <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo ($p - 1) . $link_suffix; ?>">Previous</a>
                    </li>
                    <?php
                    $start_p = max(1, $p - 2);
                    $end_p = min($total_pages, $p + 2);
                    if ($start_p > 1) {
                        echo '<li class="page-item"><a class="page-link" href="?page=1'.$link_suffix.'">1</a></li>';
                        if ($start_p > 2) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    for ($i = $start_p; $i <= $end_p; $i++): ?>
                        <li class="page-item <?php echo $i == $p ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i . $link_suffix; ?>"><?php echo $i; ?></a>
                        </li>
                    <?php endfor;
                    if ($end_p < $total_pages) {
                        if ($end_p < $total_pages - 1) echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        echo '<li class="page-item"><a class="page-link" href="?page=' . $total_pages . $link_suffix . '">'.$total_pages.'</a></li>';
                    }
                    ?>
                    <li class="page-item <?php echo $p >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo ($p + 1) . $link_suffix; ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Bulk Actions Setup
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.item-checkbox');
    const bulkContainer = document.getElementById('bulkActionContainer');
    const selectedCountSpan = document.getElementById('selectedCount');
    const bulkDisburseBtn = document.getElementById('bulkDisburseBtn');
    const bulkReceiveBtn = document.getElementById('bulkReceiveBtn');

    function updateBulkUI() {
        const checkedCount = document.querySelectorAll('.item-checkbox:checked').length;
        if (checkedCount > 0) {
            bulkContainer.classList.remove('d-none');
            selectedCountSpan.textContent = checkedCount;
        } else {
            bulkContainer.classList.add('d-none');
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkUI();
        });
    }

    checkboxes.forEach(cb => cb.addEventListener('change', updateBulkUI));

    function getSelectedIds() {
        return Array.from(document.querySelectorAll('.item-checkbox:checked')).map(cb => cb.value).join(',');
    }

    if (bulkDisburseBtn) {
        bulkDisburseBtn.addEventListener('click', function() {
            const ids = getSelectedIds();
            if (ids) window.location.href = `../staff/disburse.php?item_ids=${ids}`;
        });
    }

    if (bulkReceiveBtn) {
        bulkReceiveBtn.addEventListener('click', function() {
            const ids = getSelectedIds();
            if (ids) window.location.href = `../staff/receive.php?item_ids=${ids}`;
        });
    }

    // Dynamic Sub-Category filter based on selected Category
    const catFilter = document.getElementById('categoryFilter');
    const subCatFilter = document.getElementById('subCategoryFilter');
    if (catFilter && subCatFilter) {
        catFilter.addEventListener('change', function() {
            const selectedCat = this.value;
            Array.from(subCatFilter.options).forEach(opt => {
                if (!opt.value) return; 
                if (!selectedCat || opt.getAttribute('data-cat') === selectedCat) {
                    opt.classList.remove('d-none');
                } else {
                    opt.classList.add('d-none');
                    if (opt.selected) subCatFilter.value = '';
                }
            });
        });
    }
});
</script>

<?php require_once '../partials/footer.php'; ?>