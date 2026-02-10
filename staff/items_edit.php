<?php
/**
 * Item Catalog Editor
 * 
 * Handles modifications to existing item types in the master list.
 * 1. Updates core metadata and stock thresholds.
 * 2. Supports deactivating items (Soft Delete) by changing status to 'inactive'.
 * 3. Redirection to the item details page upon successful update.
 * 4. Logs the update for administrative history.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$id = (int) ($_GET['id'] ?? 0); // Targeted master Item ID

if (!$id) {
    set_flash_message('danger', 'Invalid item ID.');
    redirect('inventory/items.php');
}

$error = '';
$success = '';

// Pre-load existing data for the edit form fields
$stmt = $db->prepare("SELECT * FROM items WHERE id = ?");
$stmt->execute([$id]);
$item = $stmt->fetch();

if (!$item) {
    set_flash_message('danger', 'Item not found.');
    redirect('items.php');
}

// Process the update request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $uom = trim($_POST['uom'] ?? '');
    $threshold = (int) ($_POST['threshold_quantity'] ?? 0);
    $status = $_POST['status'] ?? 'active';

    if ($name && $category_id && $uom) {
        $stmt = $db->prepare("UPDATE items SET name = ?, description = ?, category_id = ?, uom = ?, threshold_quantity = ?, status = ? WHERE id = ?");
        if ($stmt->execute([$name, $description, $category_id, $uom, $threshold, $status, $id])) {

            // Record the change in the administrative audit log
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'ITEM_UPDATE', 'Item', ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], $id, "Updated item: $name"]);

            set_flash_message('success', 'Item updated successfully.');
            redirect('inventory/item_details.php?id=' . $id);
        } else {
            $error = "Failed to update item.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}

$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$page_title = 'Edit Item - ' . $item['name'];
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Edit Item:
                    <?php echo h($item['name']); ?>
                </h4>
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
                        <input type="text" name="name" id="name" class="form-control"
                            value="<?php echo h($item['name']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label fw-semibold">Description</label>
                        <textarea name="description" id="description" class="form-control"
                            rows="3"><?php echo h($item['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="category_id" class="form-label fw-semibold">Category <span
                                    class="text-danger">*</span></label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <option value="">Select Category</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" <?php echo $item['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                        <?php echo h($cat['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="uom" class="form-label fw-semibold">Unit of Measure <span
                                    class="text-danger">*</span></label>
                            <input type="text" name="uom" id="uom" class="form-control"
                                value="<?php echo h($item['uom']); ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="threshold_quantity" class="form-label fw-semibold">Low Stock Threshold</label>
                            <input type="number" name="threshold_quantity" id="threshold_quantity" class="form-control"
                                value="<?php echo $item['threshold_quantity']; ?>" min="0">
                        </div>
                        <div class="col-md-6 mb-4">
                            <label for="status" class="form-label fw-semibold">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>
                                    >Active</option>
                                <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>
                                    >Inactive</option>
                            </select>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="../inventory/item_details.php?id=<?php echo $id; ?>" class="btn btn-light border">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>