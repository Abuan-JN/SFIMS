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

<style>
    .hover-card {
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .hover-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15) !important;
    }
    .icon-box {
        width: 60px;
        height: 60px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 12px;
    }
</style>

<!-- Welcome Section -->
<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold text-dark">Welcome back,
            <span class="text-primary"><?php echo h($_SESSION['full_name']); ?></span>
        </h2>
        <p class="text-muted">Here is what's happening in SFIMS today.</p>
    </div>
    <div class="col-md-6 text-end d-flex align-items-center justify-content-end gap-2">
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer me-1"></i> Print Summary</button>
    </div>
</div>

<!-- Statistical Tiles -->
<div class="row g-4 mb-5">
    <!-- Total Items Tile -->
    <div class="col-md-3">
        <div class="card bg-white h-100 border-0 shadow-sm hover-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-primary bg-opacity-10">
                        <i class="bi bi-box-seam text-primary fs-3"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-subtitle text-muted text-uppercase small fw-bold mb-1">Total Items</h6>
                        <h2 class="card-title mb-0 fw-bold">
                            <?php echo $stats['total_items']; ?>
                        </h2>
                    </div>
                </div>
                <a href="inventory/items.php" class="text-decoration-none small fw-semibold stretched-link">View Inventory <i
                        class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts Tile -->
    <div class="col-md-3">
        <div class="card bg-white h-100 border-0 shadow-sm hover-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-danger bg-opacity-10">
                        <i class="bi bi-exclamation-triangle text-danger fs-3"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-subtitle text-muted text-uppercase small fw-bold mb-1">Low Stock</h6>
                        <h2 class="card-title mb-0 fw-bold">
                            <?php echo $stats['low_stock']; ?>
                        </h2>
                    </div>
                </div>
                <a href="reports/reports.php?type=inventory&low_stock=1" class="text-decoration-none small fw-semibold text-danger stretched-link">View Alerts <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>

    <!-- Pending Approvals Tile (Administrative) -->
    <div class="col-md-3">
            <div class="card bg-white h-100 border-0 shadow-sm hover-card">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box bg-warning bg-opacity-10">
                            <i class="bi bi-person-check text-warning fs-3"></i>
                        </div>
                        <div class="ms-3">
                            <h6 class="card-subtitle text-muted text-uppercase small fw-bold mb-1">Pending Users</h6>
                            <h2 class="card-title mb-0 fw-bold">
                                <?php echo $stats['pending_users']; ?>
                            </h2>
                        </div>
                    </div>
                    <a href="admin/users.php?status=pending" class="text-decoration-none small fw-semibold text-warning stretched-link">Review Requests <i
                            class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>

    <!-- Departments Tile -->
    <div class="col-md-3">
        <div class="card bg-white h-100 border-0 shadow-sm hover-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-box bg-info bg-opacity-10">
                        <i class="bi bi-buildings text-info fs-3"></i>
                    </div>
                    <div class="ms-3">
                        <h6 class="card-subtitle text-muted text-uppercase small fw-bold mb-1">Departments</h6>
                        <h2 class="card-title mb-0 fw-bold">
                            <?php echo $db->query("SELECT COUNT(*) FROM departments")->fetchColumn(); ?>
                        </h2>
                    </div>
                </div>
                <a href="admin/departments.php" class="text-decoration-none small fw-semibold text-info stretched-link">Manage Depts <i
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
            <div class="card-body p-0">
                <?php
                // Fetch the 5 most recent audit logs to show system history
                $recentLogs = $db->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 5")->fetchAll();
                if ($recentLogs):
                    ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($recentLogs as $log): ?>
                            <li class="list-group-item px-4 py-3 border-light">
                                <div class="d-flex align-items-start">
                                    <div class="flex-shrink-0 mt-1">
                                        <i class="bi bi-clock-history text-primary"></i>
                                    </div>
                                    <div class="ms-3">
                                        <p class="mb-1 text-dark fw-medium">
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
        <div class="card bg-white shadow-sm border-0">
            <div class="card-header bg-white border-bottom py-3">
                <h5 class="card-title mb-0">Quick Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-3">
                    <a href="staff/receive.php" class="btn btn-outline-primary text-start p-3 hover-card border-0 bg-light">
                        <div class="d-flex align-items-center">
                            <span class="icon-box bg-white text-primary rounded-circle shadow-sm me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-plus-lg"></i>
                            </span>
                            <span class="fw-semibold">Receive Items</span>
                        </div>
                    </a>
                    <a href="staff/disburse.php" class="btn btn-outline-primary text-start p-3 hover-card border-0 bg-light">
                        <div class="d-flex align-items-center">
                            <span class="icon-box bg-white text-primary rounded-circle shadow-sm me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-box-arrow-right"></i>
                            </span>
                            <span class="fw-semibold">Disburse Items</span>
                        </div>
                    </a>
                    <a href="staff/items_add.php" class="btn btn-outline-primary text-start p-3 hover-card border-0 bg-light">
                        <div class="d-flex align-items-center">
                            <span class="icon-box bg-white text-primary rounded-circle shadow-sm me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-folder-plus"></i>
                            </span>
                            <span class="fw-semibold">New Item</span>
                        </div>
                    </a>
                    <a href="reports/reports.php" class="btn btn-outline-secondary text-start p-3 hover-card border-0 bg-light">
                        <div class="d-flex align-items-center">
                            <span class="icon-box bg-white text-secondary rounded-circle shadow-sm me-3" style="width: 40px; height: 40px;">
                                <i class="bi bi-file-earmark-text"></i>
                            </span>
                            <span class="fw-semibold text-dark">Generate Reports</span>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'partials/footer.php'; ?>
