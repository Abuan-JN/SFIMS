<?php
/**
 * Dashboard (Main Control Panel)
 * 
 * This page provides a high-level overview of the system, including:
 * - Real-time inventory statistics (Total items, low stock alerts).
 * - Pending user approvals.
 * - Quick access to common operations (Receive, Disburse, etc.).
 * - Recent audit activity logs.
 */

require_once 'config/database.php';
require_once 'config/app.php';

// Access Control: Ensure only logged-in users can view the dashboard
if (!is_logged_in()) {
    redirect('index.php');
}

$db = Database::getInstance();

// Gather key metrics for the summary tiles
$stats = [
    'total_items' => $db->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn(),
    'low_stock' => $db->query("SELECT COUNT(*) FROM items WHERE status = 'active' AND current_quantity <= threshold_quantity")->fetchColumn(),
    'pending_users' => $db->query("SELECT COUNT(*) FROM users WHERE status = 'pending'")->fetchColumn(),
    'total_transactions' => $db->query("SELECT COUNT(*) FROM transactions")->fetchColumn()
];

$page_title = 'Dashboard';
require_once 'partials/header.php';
?>

<!-- Welcome Section -->
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold">Welcome,
            <?php echo h($_SESSION['full_name']); ?>!
        </h2>
        <p class="text-muted">Here is what's happening in SFIMS today.</p>
    </div>
    <div class="col-md-6 text-end d-flex align-items-center justify-content-end gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer me-1"></i> Print Stock Summary</button>
    </div>
</div>

<!-- Statistical Tiles -->
<div class="row g-4 mb-5">
    <!-- Total Items Tile -->
    <div class="col-md-3">
        <div class="card bg-white h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0 bg-primary bg-opacity-10 p-3 rounded">
                        <i class="bi bi-box-seam text-primary fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-subtitle text-muted mb-1">Total Items</h6>
                        <h3 class="card-title mb-0">
                            <?php echo $stats['total_items']; ?>
                        </h3>
                    </div>
                </div>
                <a href="inventory/items.php" class="text-decoration-none small">View Inventory <i
                        class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts Tile -->
    <div class="col-md-3">
        <div class="card bg-white h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0 bg-danger bg-opacity-10 p-3 rounded">
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-subtitle text-muted mb-1">Low Stock</h6>
                        <h3 class="card-title mb-0">
                            <?php echo $stats['low_stock']; ?>
                        </h3>
                    </div>
                </div>
                <a href="reports/reports.php?type=inventory&low_stock=1" class="text-decoration-none small text-danger">Stock
                    Alerts <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Pending Approvals Tile (Administrative) -->
    <div class="col-md-3">
            <div class="card bg-white h-100 border-warning shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="flex-shrink-0 bg-warning bg-opacity-10 p-3 rounded">
                            <i class="bi bi-person-plus text-warning fs-4"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="card-subtitle text-muted mb-1">Pending Approval</h6>
                            <h3 class="card-title mb-0">
                                <?php echo $stats['pending_users']; ?>
                            </h3>
                        </div>
                    </div>
                    <a href="admin/users.php?status=pending" class="text-decoration-none small text-warning">Review Users <i
                            class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

    <!-- Departments Tile -->
    <div class="col-md-3">
        <div class="card bg-white h-100">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="flex-shrink-0 bg-info bg-opacity-10 p-3 rounded">
                        <i class="bi bi-building text-info fs-4"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-subtitle text-muted mb-1">Departments</h6>
                        <h3 class="card-title mb-0">
                            <?php echo $db->query("SELECT COUNT(*) FROM departments")->fetchColumn(); ?>
                        </h3>
                    </div>
                </div>
                <a href="admin/departments.php" class="text-decoration-none small text-info">View All <i
                        class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>

<!-- Detailed Inventory Table (Consumables Focus) -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card bg-white shadow-sm">
            <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Consumables Inventory Status</h5>
                <a href="reports/reports.php?type=inventory" class="btn btn-sm btn-link">View Full Report</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Status</th>
                                <th class="text-end">Current Stock</th>
                                <th class="text-end">Threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Fetch top 10 consumables by quantity to highlight stock levels
                            $consumables = $db->query("SELECT i.* FROM items i JOIN categories c ON i.category_id = c.id WHERE c.name = 'Consumables' ORDER BY i.current_quantity ASC LIMIT 10")->fetchAll();
                            foreach($consumables as $c):
                                $is_low = $c['current_quantity'] <= $c['threshold_quantity'];
                            ?>
                            <tr class="<?php echo $is_low ? 'table-danger' : ''; ?>">
                                <td><?php echo h($c['name']); ?></td>
                                <td><span class="badge <?php echo $is_low ? 'bg-danger' : 'bg-success'; ?>"><?php echo $is_low ? 'Low Stock' : 'Good'; ?></span></td>
                                <td class="text-end fw-bold"><?php echo $c['current_quantity']; ?> <?php echo h($c['uom']); ?></td>
                                <td class="text-end text-muted small"><?php echo $c['threshold_quantity']; ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Recent Activity Feed -->
    <div class="col-md-8">
        <div class="card bg-white shadow-sm mb-4">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Recent Activity</h5>
            </div>
            <div class="card-body">
                <?php
                // Fetch the 5 most recent audit logs to show system history
                $recentLogs = $db->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 5")->fetchAll();
                if ($recentLogs):
                    ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentLogs as $log): ?>
                            <li class="list-group-item px-0 py-3 border-light">
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <i class="bi bi-info-circle text-muted"></i>
                                    </div>
                                    <div class="ms-3">
                                        <p class="mb-0">
                                            <?php echo h($log['description']); ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y h:i A', strtotime($log['timestamp'])); ?>
                                        </small>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted text-center py-4">No recent activity.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Navigation Links -->
    <div class="col-md-4">
        <div class="card bg-white shadow-sm">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="staff/receive.php" class="btn btn-outline-primary text-start"><i
                            class="bi bi-plus-circle me-2"></i> Receive Items</a>
                    <a href="staff/disburse.php" class="btn btn-outline-primary text-start"><i
                            class="bi bi-box-arrow-right me-2"></i> Disburse Items</a>
                    <a href="staff/items_add.php" class="btn btn-outline-primary text-start"><i
                            class="bi bi-folder-plus me-2"></i> New Item</a>
                    <a href="reports/reports.php" class="btn btn-outline-secondary text-start"><i
                            class="bi bi-file-earmark-text me-2"></i> Generate Reports</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
