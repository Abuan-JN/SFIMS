<?php
// reports.php
require_once '../config/database.php';
require_once '../config/app.php';

if (!is_logged_in()) {
    redirect('index.php');
}

$db = Database::getInstance();
$type = $_GET['type'] ?? 'inventory';
$format = $_GET['format'] ?? 'html';

$data = [];
$filename = "report_" . $type . "_" . date('Ymd') . ".csv";

// Logic for different report types
if ($type === 'inventory') {
    $sql = "SELECT i.*, c.name as category_name 
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id 
            WHERE i.status = 'active'";
    if (isset($_GET['low_stock'])) {
        $sql .= " AND i.current_quantity <= i.threshold_quantity";
    }
    $sql .= " ORDER BY i.name ASC";
    $data = $db->query($sql)->fetchAll();
} elseif ($type === 'received' || $type === 'issued') {
    $tx_type = strtoupper($type === 'received' ? 'RECEIVE' : 'ISSUE');
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $sql = "SELECT t.*, i.name as item_name, u.full_name as user_name 
            FROM transactions t 
            JOIN items i ON t.item_id = i.id 
            LEFT JOIN users u ON t.performed_by = u.id 
            WHERE t.type = ? AND t.date BETWEEN ? AND ? 
            ORDER BY t.date DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute([$tx_type, $start_date, $end_date]);
    $data = $stmt->fetchAll();
} elseif ($type === 'assets') {
    $sql = "SELECT ii.*, i.name as item_name, d.name as dept_name, r.name as room_name, b.name as building_name
            FROM item_instances ii 
            JOIN items i ON ii.item_id = i.id 
            LEFT JOIN departments d ON ii.assigned_department_id = d.id
            LEFT JOIN rooms r ON ii.room_id = r.id
            LEFT JOIN buildings b ON r.building_id = b.id
            ORDER BY i.name ASC, ii.barcode_value ASC";
    $data = $db->query($sql)->fetchAll();
}

// Handle CSV Export
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    if ($data) {
        fputcsv($output, array_keys($data[0]));
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    }
    fclose($output);
    exit();
}

$page_title = 'Reports';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">System Reports</h2>
    </div>
    <div class="col-md-6 text-end">
        <div class="dropdown">
            <button class="btn btn-success dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-download me-1"></i> Export Data
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item"
                        href="?type=<?php echo $type; ?>&format=csv&<?php echo http_build_query($_GET); ?>">CSV
                        Export</a></li>
                <li><a class="dropdown-item" href="#" id="exportPDF">PDF Export</a></li>
            </ul>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <ul class="nav nav-pills mb-3">
            <li class="nav-item">
                <a class="nav-link <?php echo $type === 'inventory' ? 'active' : ''; ?>" href="?type=inventory">Current
                    Inventory</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $type === 'received' ? 'active' : ''; ?>" href="?type=received">Received
                    Stock</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $type === 'issued' ? 'active' : ''; ?>" href="?type=issued">Issued
                    Stock</a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $type === 'assets' ? 'active' : ''; ?>" href="?type=assets">Fixed Assets Inventory</a>
            </li>
        </ul>

        <?php if ($type === 'received' || $type === 'issued'): ?>
            <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                <div class="col-md-4">
                    <label class="form-label small fw-bold">From Date</label>
                    <input type="date" name="start_date" class="form-control"
                        value="<?php echo $_GET['start_date'] ?? date('Y-m-01'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">To Date</label>
                    <input type="date" name="end_date" class="form-control"
                        value="<?php echo $_GET['end_date'] ?? date('Y-m-d'); ?>">
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Filter Range</button>
                </div>
            </form>
        <?php endif; ?>

        <div class="table-responsive">
            <table class="table table-hover align-middle border" id="reportTable">
                <thead class="table-light">
                    <?php if ($type === 'inventory'): ?>
                        <tr>
                            <th>Item Name</th>
                            <th>Category</th>
                            <th>UOM</th>
                            <th>Stock Level</th>
                            <th>Current Qty</th>
                            <th>Threshold</th>
                        </tr>
                    <?php elseif ($type === 'received' || $type === 'issued'): ?>
                        <tr>
                            <th>Date</th>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>
                                <?php echo $type === 'received' ? 'Source' : 'Recipient'; ?>
                            </th>
                            <th>Remarks</th>
                            <th>User</th>
                        </tr>
                    <?php elseif ($type === 'assets'): ?>
                        <tr>
                            <th>Barcode</th>
                            <th>Item Name</th>
                            <th>Serial No.</th>
                            <th>Status</th>
                            <th>Current Assignment</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if ($data): ?>
                        <?php foreach ($data as $row): ?>
                            <?php if ($type === 'inventory'): ?>
                                <tr
                                    class="<?php echo $row['current_quantity'] <= $row['threshold_quantity'] ? 'table-danger' : ''; ?>">
                                    <td class="fw-semibold">
                                        <?php echo h($row['name']); ?>
                                    </td>
                                    <td>
                                        <?php echo h($row['category_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo h($row['uom']); ?>
                                    </td>
                                    <td>
                                        <?php if ($row['current_quantity'] <= $row['threshold_quantity']): ?>
                                            <span class="text-danger fw-bold"><i class="bi bi-exclamation-triangle"></i> Low</span>
                                        <?php else: ?>
                                            <span class="text-success">Good</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['current_quantity']; ?>
                                    </td>
                                    <td>
                                        <?php echo $row['threshold_quantity']; ?>
                                    </td>
                                </tr>
                            <?php elseif ($type === 'received' || $type === 'issued'): ?>
                                <tr>
                                    <td>
                                        <?php echo date('M d, Y', strtotime($row['date'])); ?>
                                    </td>
                                    <td>
                                        <?php echo h($row['item_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo $row['quantity']; ?>
                                    </td>
                                    <td>
                                        <?php echo h($row['department_or_person']); ?>
                                    </td>
                                    <td><small class="text-muted">
                                            <?php echo h($row['remarks']); ?>
                                        </small></td>
                                    <td>
                                        <?php echo h($row['user_name']); ?>
                                    </td>
                                </tr>
                            <?php elseif ($type === 'assets'): ?>
                                <tr>
                                    <td class="fw-mono">
                                        <?php echo h($row['barcode_value']); ?>
                                    </td>
                                    <td>
                                        <?php echo h($row['item_name']); ?>
                                    </td>
                                    <td>
                                        <?php echo h($row['serial_number'] ?: '--'); ?>
                                    </td>
                                    <td><span class="badge bg-secondary">
                                            <?php echo ucfirst($row['status']); ?>
                                        </span></td>
                                    <td>
                                        <?php if($row['dept_name']): ?>
                                            <?php echo h($row['dept_name']); ?>
                                            <?php if($row['room_name']): ?>
                                                <br><small class="text-muted"><?php echo h($row['building_name'] . ' - ' . $row['room_name']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            Warehouse
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center py-4 text-muted">No data available for the selected
                                criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.getElementById('exportPDF').addEventListener('click', function(e) {
    e.preventDefault();
    
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF('l', 'pt', 'a4'); // Landscape, points, A4
    
    const reportType = "<?php echo ucfirst($type); ?> Report";
    const dateStr = "Generated on: <?php echo date('M d, Y H:i'); ?>";
    
    doc.setFontSize(18);
    doc.text("SFIMS - Supply and Facilities Inventory Management System", 40, 40);
    
    doc.setFontSize(14);
    doc.text(reportType, 40, 65);
    
    doc.setFontSize(10);
    doc.setTextColor(100);
    doc.text(dateStr, 40, 85);
    
    doc.autoTable({
        html: '#reportTable',
        startY: 100,
        theme: 'striped',
        headStyles: { fillColor: [13, 110, 253] }, // Bootstrap Primary Blue
        styles: { fontSize: 9 }
    });
    
    doc.save(`SFIMS_${reportType.replace(' ', '_')}_<?php echo date('Ymd'); ?>.pdf`);
});
</script>

<?php require_once '../partials/footer.php'; ?>
