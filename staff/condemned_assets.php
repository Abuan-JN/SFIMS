<?php
// staff/condemned_assets.php
require_once '../config/database.php';
require_once '../config/app.php';

require_role();

$db = Database::getInstance();
$filter = $_GET['filter'] ?? 'all'; // all, condemned-serviced, condemned-trash

$sql = "SELECT ii.*, i.name as item_name, bc.barcode_value 
        FROM item_instances ii 
        JOIN items i ON ii.item_id = i.id 
        JOIN barcodes bc ON ii.barcode_id = bc.id
        WHERE ii.status LIKE 'condemned-%'";

if ($filter !== 'all') {
    $sql .= " AND ii.status = " . $db->quote($filter);
}

$sql .= " ORDER BY ii.last_updated DESC";
$assets = $db->query($sql)->fetchAll();

$page_title = 'Condemned Assets';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Condemned Assets Log</h2>
    </div>
    <div class="col-md-6 text-end">
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer me-1"></i> Print Log</button>
    </div>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body">
        <div class="nav nav-pills">
            <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all">All Condemned</a>
            <a class="nav-link <?php echo $filter === 'condemned-serviced' ? 'active' : ''; ?>" href="?filter=condemned-serviced">For Servicing</a>
            <a class="nav-link <?php echo $filter === 'condemned-trash' ? 'active' : ''; ?>" href="?filter=condemned-trash">Trash / Disposed</a>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th class="ps-4">Barcode</th>
                        <th>Item Name</th>
                        <th>Current Status</th>
                        <th>Last Action</th>
                        <th class="text-end pe-4 no-print">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($assets): ?>
                        <?php foreach ($assets as $a): ?>
                            <tr>
                                <td class="ps-4 fw-mono"><?php echo h($a['barcode_value']); ?></td>
                                <td class="fw-semibold"><?php echo h($a['item_name']); ?></td>
                                <td>
                                    <span class="badge <?php echo $a['status'] === 'condemned-serviced' ? 'bg-warning text-dark' : 'bg-danger'; ?>">
                                        <?php echo $a['status'] === 'condemned-serviced' ? 'For Servicing' : 'Trash'; ?>
                                    </span>
                                </td>
                                <td><small class="text-muted"><?php echo date('M d, Y', strtotime($a['last_updated'])); ?></small></td>
                                <td class="text-end pe-4 no-print">
                                    <a href="../item_details.php?id=<?php echo $a['item_id']; ?>" class="btn btn-sm btn-link">View Item</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">No condemned assets found matching the filter.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
