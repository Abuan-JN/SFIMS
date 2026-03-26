<?php
/**
 * Condemned Assets Log
 * 
 * Displays a historical log of all assets that have been condemned.
 * 1. Supports filtering by sub-status: 'condemned-serviced' (Repairable) or 'condemned-trash' (Disposed).
 * 2. Allows Staff to change the status of a condemned asset (e.g., restore after repair).
 * 3. Shows the last updated date to track when the condemnation occurred.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();

// Handle Status Change (Staff Action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    
    $instance_id = (int) $_POST['instance_id'];
    $new_status  = $_POST['new_status'];
    $allowed_statuses = ['condemned-serviced', 'condemned-trash', 'in-stock'];

    if ($instance_id && in_array($new_status, $allowed_statuses)) {
        try {
            $db->beginTransaction();

            // Fetch old status to know if we are restoring
            $stmt = $db->prepare("SELECT status, item_id FROM item_instances WHERE id = ?");
            $stmt->execute([$instance_id]);
            $inst = $stmt->fetch();
            
            if (!$inst) throw new Exception("Instance not found.");

            // Update the instance status
            $stmt = $db->prepare("UPDATE item_instances SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $instance_id]);

            // If restoring to in-stock, increment the master item quantity
            if ($new_status === 'in-stock') {
                $db->prepare("UPDATE items SET current_quantity = current_quantity + 1 WHERE id = ?")->execute([$inst['item_id']]);
            }
            // If previously in-stock and now being condemned, decrement (this page only handles already-condemned, but for safety)
            // No action needed as the condemn.php handles the initial decrement.

            // Log the action
            $logStmt = $db->prepare("INSERT INTO audit_logs (user_id, action_type, entity_name, entity_id, description) VALUES (?, 'UPDATE', 'ItemInstance', ?, ?)");
            $logStmt->execute([$_SESSION['user_id'], $instance_id, "Changed condemned asset status to '$new_status'."]);

            $db->commit();
            set_flash_message('success', 'Asset status updated successfully.');
        } catch (Exception $e) {
            $db->rollBack();
            set_flash_message('danger', 'Error: ' . $e->getMessage());
        }
    } else {
        set_flash_message('danger', 'Invalid status or instance.');
    }
    
    header("Location: condemned_assets.php?filter=" . ($_POST['current_filter'] ?? 'all') . "&page=" . ($_POST['current_page'] ?? 1));
    exit;
}

// Filter and Pagination Setup
$filter    = $_GET['filter'] ?? 'all';
$page      = max(1, (int)($_GET['page'] ?? 1));
$per_page  = 20;

// Base WHERE clause
$where_sql    = "WHERE ii.status IN ('condemned-serviced', 'condemned-trash')";
$filter_params = [];
if ($filter !== 'all') {
    $where_sql     = "WHERE ii.status = ?";
    $filter_params[] = $filter;
}

// Count total matching assets (for pagination math)
$count_sql = "SELECT COUNT(*) FROM item_instances ii $where_sql";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($filter_params);
$total_assets = (int)$count_stmt->fetchColumn();
$total_pages  = max(1, ceil($total_assets / $per_page));
$page         = min($page, $total_pages);
$offset       = ($page - 1) * $per_page;

// Paginated asset query
$sql = "SELECT ii.*, i.name as item_name, bc.barcode_value 
        FROM item_instances ii 
        JOIN items i ON ii.item_id = i.id 
        JOIN barcodes bc ON ii.barcode_id = bc.id
        $where_sql
        ORDER BY ii.last_updated DESC
        LIMIT $per_page OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($filter_params);
$assets = $stmt->fetchAll();

$page_title = 'Condemned Assets';
require_once '../partials/header.php';
?>

<div class="row mb-4">
    <div class="col-md-6">
        <h2 class="fw-bold mb-1">Condemned Assets Log</h2>
        <p class="text-muted small mb-0">Record of items marked for disposal or out for external servicing. Staff can update status after inspection or repair.</p>
    </div>
    <div class="col-md-6 text-end">
        <?php if ($total_assets > 0): ?>
        <a href="condemn_print.php?filter=<?php echo urlencode($filter); ?>" class="btn btn-primary no-print me-2">
            <i class="bi bi-file-earmark-pdf me-1"></i> Print Turn Over Form
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-outline-dark no-print"><i class="bi bi-printer me-1"></i> Print Log</button>
    </div>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body d-flex justify-content-between align-items-center">
        <div class="nav nav-pills">
            <a class="nav-link <?php echo $filter === 'all' ? 'active' : ''; ?>" href="?filter=all&page=1">All Condemned</a>
            <a class="nav-link <?php echo $filter === 'condemned-serviced' ? 'active' : ''; ?>" href="?filter=condemned-serviced&page=1">For Servicing</a>
            <a class="nav-link <?php echo $filter === 'condemned-trash' ? 'active' : ''; ?>" href="?filter=condemned-trash&page=1">Trash / Disposed</a>
        </div>
        <small class="text-muted"><?php echo $total_assets; ?> total record<?php echo $total_assets !== 1 ? 's' : ''; ?></small>
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
                                    <div class="btn-group">
                                        <a href="../inventory/item_details.php?id=<?php echo $a['item_id']; ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye me-1"></i>View
                                        </a>
                                        <!-- Change Status button opens a modal -->
                                        <button type="button" class="btn btn-sm btn-outline-primary change-status-btn"
                                            data-instance-id="<?php echo $a['id']; ?>"
                                            data-current-status="<?php echo h($a['status']); ?>"
                                            data-item-name="<?php echo h($a['item_name']); ?>"
                                            data-barcode="<?php echo h($a['barcode_value']); ?>"
                                            data-bs-toggle="modal" data-bs-target="#changeStatusModal">
                                            <i class="bi bi-arrow-repeat me-1"></i>Change Status
                                        </button>
                                    </div>
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
    <?php if ($total_pages > 1): ?>
    <div class="card-footer d-flex justify-content-between align-items-center bg-white py-2">
        <small class="text-muted">Showing <?php echo $offset + 1; ?>–<?php echo min($offset + $per_page, $total_assets); ?> of <?php echo $total_assets; ?></small>
        <nav>
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $page - 1; ?>">&laquo;</a>
                </li>
                <?php
                $start_p = max(1, $page - 2);
                $end_p   = min($total_pages, $page + 2);
                for ($p = $start_p; $p <= $end_p; $p++): ?>
                    <li class="page-item <?php echo $p === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a>
                    </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?filter=<?php echo urlencode($filter); ?>&page=<?php echo $page + 1; ?>">&raquo;</a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Change Status Modal -->
<div class="modal fade" id="changeStatusModal" tabindex="-1" aria-labelledby="changeStatusModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="changeStatusModalLabel"><i class="bi bi-arrow-repeat me-2"></i>Change Asset Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <?php csrf_field(); ?>
                <input type="hidden" name="change_status" value="1">
                <input type="hidden" name="instance_id" id="modal_instance_id">
                <input type="hidden" name="current_filter" value="<?php echo h($filter); ?>">
                <input type="hidden" name="current_page" value="<?php echo $page; ?>">
                <div class="modal-body">
                    <p class="text-muted small"><strong>Asset:</strong> <span id="modal_barcode"></span> &mdash; <span id="modal_item_name"></span></p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Set New Status</label>
                        <select name="new_status" class="form-select" id="modal_new_status">
                            <option value="condemned-serviced">For Servicing (Send for Repair)</option>
                            <option value="condemned-trash">Disposed / Trash</option>
                            <option value="in-stock">Restore to In-Stock (Repaired / Recovered)</option>
                        </select>
                    </div>
                    <div id="restore_warning" class="alert alert-warning d-none">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Restoring this asset</strong> will add 1 unit back to the master inventory count for this item.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.change-status-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            document.getElementById('modal_instance_id').value = this.dataset.instanceId;
            document.getElementById('modal_barcode').textContent = this.dataset.barcode;
            document.getElementById('modal_item_name').textContent = this.dataset.itemName;
            // Start selector from the other option so user actively picks
            const sel = document.getElementById('modal_new_status');
            if (this.dataset.currentStatus === 'condemned-serviced') {
                sel.value = 'condemned-trash';
            } else {
                sel.value = 'condemned-serviced';
            }
            document.getElementById('restore_warning').classList.add('d-none');
        });
    });

    document.getElementById('modal_new_status').addEventListener('change', function() {
        const warning = document.getElementById('restore_warning');
        if (this.value === 'in-stock') {
            warning.classList.remove('d-none');
        } else {
            warning.classList.add('d-none');
        }
    });
});
</script>

<?php require_once '../partials/footer.php'; ?>
