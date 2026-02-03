<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$id = $_GET['id'] ?? null;
$item = null;
$main_cats = $pdo->query("SELECT * FROM ref_main_categories ORDER BY name")->fetchAll();

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM items WHERE item_id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch();
}

$error = '';
$success = '';

if ($_SERVER["POST"] = "POST" && isset($_POST['save'])) {
    $item_name = trim($_POST['item_name']);
    $category_type = $_POST['category_type'];
    $main_cat_id = $_POST['main_category_id']; // ID from select
    $sub_cat_id = $_POST['subcategory_id']; // ID from select
    // We need Codes for the items table
    // Fetch codes for selected IDs
    $stmt = $pdo->prepare("SELECT code FROM ref_main_categories WHERE main_id = ?");
    $stmt->execute([$main_cat_id]);
    $main_code = $stmt->fetchColumn();
    
    $stmt = $pdo->prepare("SELECT code FROM ref_subcategories WHERE sub_id = ?");
    $stmt->execute([$sub_cat_id]);
    $sub_code = $stmt->fetchColumn();
    
    $description = trim($_POST['description']);
    $location = trim($_POST['location']);
    $threshold = (int)$_POST['threshold'];
    $quantity = (int)$_POST['quantity']; // Only for new items or specific adjustment logic
    
    if (empty($item_name) || $main_code === false || $sub_code === false) {
        $error = "Name and Categories are required.";
    } else {
        if ($id) {
            // Update
            $sql = "UPDATE items SET item_name=?, category_type=?, main_category_code=?, subcategory_code=?, description=?, location=?, per_item_threshold=? WHERE item_id=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item_name, $category_type, $main_code, $sub_code, $description, $location, $threshold, $id]);
            $success = "Item updated successfully.";
            // Refresh item
            $stmt = $pdo->prepare("SELECT * FROM items WHERE item_id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
        } else {
            // Add
            $sql = "INSERT INTO items (item_name, category_type, main_category_code, subcategory_code, description, quantity_total, location, per_item_threshold) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$item_name, $category_type, $main_code, $sub_code, $description, $quantity, $location, $threshold]);
            $success = "Item added successfully.";
            $id = $pdo->lastInsertId();
            // Fetch for edit mode
            $stmt = $pdo->prepare("SELECT * FROM items WHERE item_id = ?");
            $stmt->execute([$id]);
            $item = $stmt->fetch();
            // Redirect to edit to avoid resubmission
            // header("Location: item_form.php?id=$id"); 
            // exit();
        }
    }
}

include 'includes/header.php';
?>

<h2><?= $item ? 'Edit Item' : 'Add New Item' ?></h2>

<?php if($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<form method="POST" class="card p-4 shadow-sm">
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Item Name</label>
            <input type="text" name="item_name" class="form-control" value="<?= htmlspecialchars($item['item_name'] ?? '') ?>" required>
        </div>
         <div class="col-md-6">
            <label class="form-label">Category Type</label>
            <select name="category_type" class="form-select" required>
                <option value="Expendable" <?= ($item['category_type'] ?? '') == 'Expendable' ? 'selected' : '' ?>>Expendable</option>
                <option value="Non-Expendable" <?= ($item['category_type'] ?? '') == 'Non-Expendable' ? 'selected' : '' ?>>Non-Expendable</option>
            </select>
        </div>
    </div>
    
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">Main Category</label>
            <select name="main_category_id" id="main_category" class="form-select" required onchange="loadSubcats()">
                <option value="">Select...</option>
                <?php foreach ($main_cats as $mc): ?>
                    <?php 
                        // Find ID if editing by matching code
                        $selected = ''; 
                        if ($item && $item['main_category_code'] == $mc['code']) $selected = 'selected';
                    ?>
                    <option value="<?= $mc['main_id'] ?>" <?= $selected ?>><?= htmlspecialchars($mc['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label class="form-label">Subcategory</label>
            <select name="subcategory_id" id="subcategory" class="form-select" required>
                <!-- Filled via JS -->
            </select>
        </div>
    </div>

    <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($item['description'] ?? '') ?></textarea>
    </div>

    <div class="row mb-3">
        <div class="col-md-4">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($item['location'] ?? '') ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Quantity</label>
            <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($item['quantity_total'] ?? '0') ?>" <?= $item ? 'readonly' : '' ?>>
            <?php if($item): ?><small class="text-muted">Use "Receive" to add stock.</small><?php endif; ?>
        </div>
        <div class="col-md-4">
            <label class="form-label">Low Stock Threshold</label>
            <input type="number" name="threshold" class="form-control" value="<?= htmlspecialchars($item['per_item_threshold'] ?? '5') ?>">
        </div>
    </div>

    <button type="submit" name="save" class="btn btn-primary">Save Item</button>
    <a href="items.php" class="btn btn-secondary">Cancel</a>
</form>

<script>
// Retrieve old subcode for editing pre-selection
const oldSubCode = <?= $item['subcategory_code'] ?? 'null' ?>;

function loadSubcats() {
    const mainId = document.getElementById('main_category').value;
    const subSelect = document.getElementById('subcategory');
    subSelect.innerHTML = '<option value="">Loading...</option>';
    
    if(!mainId) { subSelect.innerHTML = ''; return; }

    fetch(`api/get_subcategories.php?main_id=${mainId}`)
        .then(response => response.json())
        .then(data => {
            subSelect.innerHTML = '<option value="">Select...</option>';
            data.forEach(sub => {
                const selected = (oldSubCode !== null && oldSubCode == sub.code) ? 'selected' : '';
                subSelect.innerHTML += `<option value="${sub.sub_id}" ${selected}>${sub.name}</option>`;
            });
            // Clear oldSubCode after first load so changing main cat resets sub
            // But we actually need to match code, not ID, if we change logic. 
            // Current API returns ID, Name, Code. 
            // Saved item has Code. "oldSubCode == sub.code" works.
        });
}

// Initial load if editing
if(document.getElementById('main_category').value) {
    loadSubcats();
}
</script>

<?php include 'includes/footer.php'; ?>
