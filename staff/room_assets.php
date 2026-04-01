<?php
/**
 * Room Assets Inventory View
 * 
 * Displays all assets physically located in a specific room, regardless of department assignment.
 * 1. Fetches rooms and their parent buildings for the selection dropdown.
 * 2. Retrieves item instances filtering by room_id.
 * 3. Joins items, departments, and barcodes for detailed metadata.
 * 4. Enables location-based physical auditing and printable room inventory lists.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role('Staff');

$db = Database::getInstance();
$room_id = (int)($_GET['room_id'] ?? 0); // Room filter ID from URL

// Prepare room list with building context for the selection form
$rooms = $db->query("SELECT r.*, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();

$assets = [];
if ($room_id) {
    // Query: List all active assets in the selected room
    $stmt = $db->prepare("SELECT ii.*, i.name as item_name, d.name as dept_name, bc.barcode_value
                          FROM item_instances ii 
                          JOIN items i ON ii.item_id = i.id 
                          JOIN barcodes bc ON ii.barcode_id = bc.id
                          LEFT JOIN departments d ON ii.assigned_department_id = d.id
                          WHERE ii.room_id = ? AND ii.status != 'disposed'
                          ORDER BY i.name ASC");
    $stmt->execute([$room_id]);
    $assets = $stmt->fetchAll();
}

$page_title = 'Assets per Room';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Room Assets Inventory</h2>
        <p class="text-muted small mb-0">Track and manage fixed assets assigned to specific physical rooms.</p>
    </div>
    <?php if ($room_id): ?>
    <div class="col-md-6 text-end">
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer me-1"></i> Print Room List</button>
    </div>
    <?php endif; ?>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-8">
                <label class="form-label fw-bold">Select Room Location</label>
                <select name="room_id" class="form-select select2" onchange="this.form.submit()">
                    <option value="">-- Choose Building & Room --</option>
                    <?php foreach ($rooms as $r): ?>
                        <option value="<?php echo $r['id']; ?>" <?php echo $room_id == $r['id'] ? 'selected' : ''; ?>>
                            <?php echo h($r['building_name'] . ' - ' . $r['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">View Room</button>
            </div>
        </form>
    </div>
</div>

<?php if ($room_id): ?>
    <?php 
    $room_info = '';
    foreach($rooms as $r) if($r['id'] == $room_id) $room_info = $r['building_name'] . ' - ' . $r['name'];
    ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0">Inventory for Location: <strong><?php echo h($room_info); ?></strong></h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th class="ps-4">Barcode</th>
                            <th>Item Name</th>
                            <th>Serial No.</th>
                            <th>Assigned Dept</th>
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
                                    <td><?php echo h($a['dept_name'] ?: 'Warehouse'); ?></td>
                                    <td><span class="badge bg-secondary"><?php echo ucfirst($a['status']); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-door-open text-muted" style="font-size:3rem;"></i>
                                    <p class="fw-bold mt-3 mb-1">No assets found</p>
                                    <p class="text-muted small mb-0">No assets are currently located in this room.</p>
                                </td>
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
    .no-print, .navbar { display: none !important; }
    .card { box-shadow: none !important; border: 1px solid #ddd !important; }
}
</style>

<?php require_once '../partials/footer.php'; ?>
