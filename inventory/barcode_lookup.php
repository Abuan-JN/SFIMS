<?php
/**
 * Barcode Lookup Module
 * 
 * Provides a specialized tool for searching unique asset instances by their barcode.
 * Unlike the general items lookup, this module returns a single, specific 
 * physical item (Fixed Asset) and its current location/assignment.
 */

require_once '../config/database.php';
require_once '../config/app.php';
require_once '../config/rate_limiter.php';

// SECURITY: Rate limiting for barcode lookup
check_rate_limit('barcode_lookup', 60, 60);

// Auth Protection: Redirect to login if user session is invalid
require_role('Staff');

$db = Database::getInstance();
$barcode = $_GET['barcode'] ?? '';
$result = null;

// Search for a specific asset instance using the scanned/entered barcode
if ($barcode) {
    // Extensive JOIN to gather item name, current status, department, and room details
    $stmt = $db->prepare("SELECT ii.*, i.name as item_name, i.uom, i.description, b.barcode_value, 
                                 c.name as category_name, d.name as dept_name, r.name as room_name, bl.name as building_name
                          FROM item_instances ii 
                          JOIN items i ON ii.item_id = i.id 
                          JOIN categories c ON i.category_id = c.id
                          JOIN barcodes b ON ii.barcode_id = b.id
                          LEFT JOIN departments d ON ii.assigned_department_id = d.id
                          LEFT JOIN rooms r ON ii.room_id = r.id
                          LEFT JOIN buildings bl ON r.building_id = bl.id
                          WHERE b.barcode_value = ?");
    $stmt->execute([$barcode]);
    $result = $stmt->fetch();
}

$page_title = 'Barcode Lookup';
require_once '../partials/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-upc-scan me-2"></i>Quick Asset Lookup</h4>
                <form method="GET" action="">
                    <div class="input-group input-group-lg">
                        <input type="text" name="barcode" class="form-control" placeholder="Scan or enter barcode..."
                            value="<?php echo h($barcode); ?>" autofocus>
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                    <div class="form-text mt-2">Use a handheld scanner or type the numeric/alphanumeric code.</div>
                </form>
            </div>
        </div>

        <?php if ($barcode && !$result): ?>
            <div class="alert alert-warning">No asset found with barcode: <strong>
                    <?php echo h($barcode); ?>
                </strong></div>
        <?php endif; ?>

        <?php if ($result): ?>
            <div class="card shadow-sm border-0 border-start border-primary border-5">
                <div class="card-body p-4">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <span class="badge bg-primary mb-2"><?php echo h($result['category_name']); ?></span>
                            <h3 class="fw-bold mb-0">
                                <?php echo h($result['item_name']); ?>
                            </h3>
                        </div>
                        <span class="badge bg-<?php
                        echo match ($result['status']) {
                            'in-stock' => 'success',
                            'issued' => 'primary',
                            'under repair' => 'warning',
                            'disposed' => 'danger',
                            default => 'secondary'
                        };
                        ?> fs-6">
                            <?php echo ucfirst($result['status']); ?>
                        </span>
                    </div>

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="text-muted small text-uppercase fw-bold">Barcode</label>
                            <p class="fw-mono mb-0">
                                <?php echo h($result['barcode_value']); ?>
                            </p>
                        </div>
                        <div class="col-6">
                            <label class="text-muted small text-uppercase fw-bold">Serial Number</label>
                            <p class="mb-0">
                                <?php echo h($result['serial_number'] ?: '--'); ?>
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small text-uppercase fw-bold">Current Assignment</label>
                            <p class="mb-0 fw-semibold text-primary">
                                <?php 
                                if ($result['status'] === 'in-stock') {
                                    echo 'Warehouse / In Stock';
                                } else {
                                    $assignment = [];
                                    if ($result['dept_name']) $assignment[] = $result['dept_name'];
                                    if ($result['assigned_person']) $assignment[] = $result['assigned_person'];
                                    if ($result['room_name']) $assignment[] = $result['room_name'] . ' (' . $result['building_name'] . ')';
                                    
                                    echo h(implode(' - ', $assignment) ?: 'Assigned / No Location Details');
                                }
                                ?>
                            </p>
                        </div>
                        <div class="col-12">
                            <label class="text-muted small text-uppercase fw-bold">Description</label>
                            <p class="small text-muted mb-0">
                                <?php echo h($result['description']); ?>
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top d-grid">
                        <a href="item_details.php?id=<?php echo $result['item_id']; ?>" class="btn btn-outline-primary">View
                            Full Item Details</a>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>