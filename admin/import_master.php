<?php
/**
 * Unified Master Data Importer (CSV)
 * 
 * Supports bulk creation of Buildings, Rooms, Departments, and Sub-Categories.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role('Admin');

$db = Database::getInstance();
$error = '';
$success = '';

$type = $_GET['type'] ?? 'buildings';
$allowed_types = ['buildings', 'rooms', 'departments', 'sub_categories'];

if (!in_array($type, $allowed_types)) {
    redirect('dashboard.php');
}

// Map types to display names and guidelines
$meta = [
    'buildings' => [
        'title' => 'Buildings',
        'headers' => 'Building Name',
        'template' => "Building Name\nMain Building\nAnnex A\nLibrary Building",
        'guidelines' => ['Column 1: Building Name (Unique)']
    ],
    'rooms' => [
        'title' => 'Rooms',
        'headers' => 'Building Name, Room Name, Floor',
        'template' => "Building Name, Room Name, Floor\nMain Building, Room 101, 1st Floor\nMain Building, ComLab 1, 2nd Floor\nAnnex A, Registrar, 1st Floor",
        'guidelines' => [
            'Column 1: Building Name (Must already exist)',
            'Column 2: Room Name',
            'Column 3: Floor (e.g., 1st Floor)'
        ]
    ],
    'departments' => [
        'title' => 'Departments',
        'headers' => 'Department Name',
        'template' => "Department Name\nInformation Technology\nRegistrar\nSPMO\nCollege of Arts & Sciences",
        'guidelines' => ['Column 1: Department Name (Unique)']
    ],
    'sub_categories' => [
        'title' => 'Sub-Categories',
        'headers' => 'Category Name, Sub-Category Name',
        'template' => "Category Name, Sub-Category Name\nFixed Assets, Laptop\nFixed Assets, Printer\nConsumables, Office Supplies",
        'guidelines' => [
            'Column 1: Category Name (Must be "Fixed Assets" or "Consumables")',
            'Column 2: Sub-Category Name'
        ]
    ]
];

$current_meta = $meta[$type];

// Handle CSV upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');

    $file = $_FILES['csv_file'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        $header = fgetcsv($handle); // Headers

        $success_count = 0;
        $db->beginTransaction();

        try {
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (empty(array_filter($row))) continue;

                if ($type === 'buildings') {
                    $name = trim($row[0]);
                    if (empty($name)) continue;
                    $stmt = $db->prepare("INSERT IGNORE INTO buildings (name) VALUES (?)");
                    $stmt->execute([$name]);
                    if ($stmt->rowCount() > 0) $success_count++;
                } 
                elseif ($type === 'departments') {
                    $name = trim($row[0]);
                    if (empty($name)) continue;
                    $stmt = $db->prepare("INSERT IGNORE INTO departments (name) VALUES (?)");
                    $stmt->execute([$name]);
                    if ($stmt->rowCount() > 0) $success_count++;
                }
                elseif ($type === 'rooms') {
                    $building_name = trim($row[0]);
                    $room_name = trim($row[1]);
                    $floor = trim($row[2] ?? '');
                    
                    if (empty($building_name) || empty($room_name)) continue;

                    // Resolve Building
                    $stmt = $db->prepare("SELECT id FROM buildings WHERE name = ?");
                    $stmt->execute([$building_name]);
                    $b = $stmt->fetch();
                    if (!$b) continue;

                    // Check for duplicate room in building
                    $stmt = $db->prepare("SELECT id FROM rooms WHERE building_id = ? AND name = ?");
                    $stmt->execute([$b['id'], $room_name]);
                    if ($stmt->fetch()) continue;

                    $stmt = $db->prepare("INSERT INTO rooms (building_id, name, floor) VALUES (?, ?, ?)");
                    $stmt->execute([$b['id'], $room_name, $floor]);
                    $success_count++;
                }
                elseif ($type === 'sub_categories') {
                    $cat_name = trim($row[0]);
                    $sub_name = trim($row[1]);
                    
                    if (empty($cat_name) || empty($sub_name)) continue;

                    // Resolve Category
                    $stmt = $db->prepare("SELECT id FROM categories WHERE name = ?");
                    $stmt->execute([$cat_name]);
                    $c = $stmt->fetch();
                    if (!$c) continue;

                    // Check for duplicate sub-cat in cat
                    $stmt = $db->prepare("SELECT id FROM sub_categories WHERE category_id = ? AND name = ?");
                    $stmt->execute([$c['id'], $sub_name]);
                    if ($stmt->fetch()) continue;

                    $stmt = $db->prepare("INSERT INTO sub_categories (category_id, name) VALUES (?, ?)");
                    $stmt->execute([$c['id'], $sub_name]);
                    $success_count++;
                }
            }

            $db->commit();
            set_flash_message('success', "Successfully imported $success_count {$current_meta['title']}.");
            redirect('admin/' . $type . '.php');
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Import failed: " . $e->getMessage();
        }
        fclose($handle);
    } else {
        $error = "File upload failed.";
    }
}

$page_title = 'Import ' . $current_meta['title'];
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h4 class="card-title mb-0">Import <?php echo $current_meta['title']; ?> (CSV)</h4>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo h($error); ?></div>
                <?php endif; ?>

                <div class="alert alert-info border-0 shadow-none">
                    <h6 class="fw-bold"><i class="bi bi-info-circle me-1"></i> Data Guidelines</h6>
                    <ul class="mb-0 small">
                        <?php foreach ($current_meta['guidelines'] as $guide): ?>
                            <li><?php echo h($guide); ?></li>
                        <?php endforeach; ?>
                        <li>The first row must be headers (<?php echo h($current_meta['headers']); ?>).</li>
                        <li>Duplicates found in the file or existing system will be skipped.</li>
                    </ul>
                </div>

                <form method="POST" enctype="multipart/form-data" class="mt-4">
                    <?php csrf_field(); ?>
                    <div class="mb-4">
                        <label class="form-label fw-bold">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Run Import Process</button>
                        <a href="data:text/csv;charset=utf-8,<?php echo rawurlencode($current_meta['template']); ?>" download="import_<?php echo $type; ?>_template.csv" class="btn btn-outline-success">
                            <i class="bi bi-download me-1"></i>Download Template CSV
                        </a>
                        <a href="<?php echo $type; ?>.php" class="btn btn-light border">Cancel & Back</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
