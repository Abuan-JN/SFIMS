<?php
/**
 * Department Assets Dashboard
 * 
 * Provides a specialized view of all fixed assets currently assigned to a specific department.
 * 1. Fetches all departments for the selection dropdown.
 * 2. If a department is selected, retrieves all 'issued' item instances for that department.
 * 3. Joins multiple tables (items, barcodes, rooms, buildings) to provide a complete inventory snapshot.
 * 4. Includes CSS print media queries for generating physical inventory reports.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$dept_id = (int)($_GET['dept_id'] ?? 0); // Department filter ID from URL

// Load departments list for the filter form
$departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();

$assets = [];
if ($dept_id) {
    // Complex Query: Aggregate physical instance data with its master item and location metadata
    $stmt = $db->prepare("SELECT ii.*, i.name as item_name, i.uom, r.name as room_name, b.name as building_name, bc.barcode_value
                          FROM item_instances ii 
                          JOIN items i ON ii.item_id = i.id 
                          JOIN barcodes bc ON ii.barcode_id = bc.id
                          LEFT JOIN rooms r ON ii.room_id = r.id 
                          LEFT JOIN buildings b ON r.building_id = b.id
                          WHERE ii.assigned_department_id = ? AND ii.status = 'issued'
                          ORDER BY i.name ASC");
    $stmt->execute([$dept_id]);
    $assets = $stmt->fetchAll();
}

$page_title = 'Assets per Department';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Department Assets Dashboard</h2>
    </div>
    <?php if ($dept_id): ?>
    <div class="col-md-6 text-end">
        <button onclick="window.print()" class="btn btn-outline-dark"><i class="bi bi-printer me-1"></i> Print Inventory</button>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">Select Department</label>
                <select name="dept_id" class="form-select" onchange="this.form.submit()">
                    <option value="">-- Choose Department --</option>
                    <?php foreach ($departments as $d): ?>
                        <option value="<?php echo $d['id']; ?>" <?php echo $dept_id == $d['id'] ? 'selected' : ''; ?>>
                            <?php echo h($d['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">View Assets</button>
            </div>
        </form>
    </div>
</div>

<?php if ($dept_id): ?>
    <?php 
    $dept_name = '';
    foreach($departments as $d) if($d['id'] == $dept_id) $dept_name = $d['name'];
    ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Assets Assigned to: <strong><?php echo h($dept_name); ?></strong></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Barcode</th>
                            <th>Item Name</th>
                            <th>Serial No.</th>
                            <th>Room Location</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($assets): ?>
                            <?php foreach ($assets as $a): ?>
                                <tr>
                                    <td class="ps-4 fw-mono"><?php echo h($a['barcode_value']); ?></td>
                                    <td class="fw-semibold"><?php echo h($a['item_name']); ?></td>
                                    <td><?php echo h($a['serial_number'] ?: '--'); ?></td>
                                    <td><?php echo h($a['building_name'] . ' - ' . $a['room_name']); ?></td>
                                    <td><span class="badge bg-primary">Issued</span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No assets currently assigned to this department.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
<?php endif; ?>

<style>
@media print {
    .no-print, .navbar, .btn-print { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php require_once '../partials/footer.php'; ?>
