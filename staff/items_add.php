<?php
/**
 * Item Catalog Creation
 * 
 * Manages the addition of new item types to the master inventory list.
 * 1. Collects basic metadata (Name, Category, UoM).
 * 2. Sets a 'Low Stock Threshold' for automated dashboard alerts.
 * 3. Initializes the item with zero quantity (In-stock occurs via 'Receive').
 * 4. Logs the creation event in the audit trail.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$error = '';
$success = '';

// Process the new item form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $uom = trim($_POST['uom'] ?? '');
    $threshold = (int) ($_POST['threshold_quantity'] ?? 0);

    if ($name && $category_id && $uom) {
        // Check for duplicate item name
        $stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE name = ?");
        $stmt->execute([$name]);
        if ($stmt->fetchColumn() > 0) {
            $error = "An item with this name already exists.";
        } else {
            // Insert into master items table
            $stmt = $db->prepare("INSERT INTO items (name, description, category_id, uom, threshold_quantity, current_quantity, status) VALUES (?, ?, ?, ?, ?, 0, 'active')");
        if ($stmt->execute([$name, $description, $category_id, $uom, $threshold])) {
            $itemId = $db->lastInsertId();

            // Record the action for security auditing
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'ITEM_CREATE', 'Item', ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], $itemId, "Created new item: $name"]);

            set_flash_message('success', 'Item added successfully.');
            redirect('inventory/items.php');
        } else {
            $error = "Failed to add item.";
        }
    }
} else {
    $error = "Please fill in all required fields.";
}
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$page_title = 'Add New Item';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Add New Item</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <?php echo h($error); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="name" class="form-label fw-semibold">Item Name <span
                                class="text-danger">*</span></label>
                        <input type="text" name="name" id="name" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label fw-semibold">Category <span
                                    class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>">
                                        <?php echo h($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="uom" class="form-label fw-semibold">Unit of Measure <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="uom" id="uom" class="form-control"
                                placeholder="pcs, box, set, etc." required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-4">
                            <label for="threshold_quantity" class="form-label fw-semibold">Low Stock Threshold</label>
                            <input type="number" name="threshold_quantity" id="threshold_quantity" class="form-control"
                                value="0" min="0">
                            <div class="form-text">Alert will trigger when stock falls to or below this level.</div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="../inventory/items.php" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>