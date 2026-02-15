<?php
/**
 * View Attachments Module
 * 
 * Displays all files uploaded for a specific transaction.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection
require_role();

$db = Database::getInstance();
$tx_id = (int) ($_GET['tx_id'] ?? 0);

if (!$tx_id) {
    redirect('inventory/transactions.php');
}

// Fetch transaction details
$stmt = $db->prepare("SELECT t.*, i.name as item_name FROM transactions t JOIN items i ON t.item_id = i.id WHERE t.id = ?");
$stmt->execute([$tx_id]);
$tx = $stmt->fetch();

if (!$tx) {
    set_flash_message('danger', 'Transaction not found.');
    redirect('inventory/transactions.php');
}

// Fetch attachments
$stmt = $db->prepare("SELECT * FROM attachments WHERE transaction_id = ?");
$stmt->execute([$tx_id]);
$attachments = $stmt->fetchAll();

$page_title = 'Transaction Attachments';
require_once '../partials/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8">
        <h2 class="fw-bold"><i class="bi bi-paperclip me-2"></i>Attachments</h2>
        <p class="text-muted">Transaction for <strong><?php echo h($tx['item_name']); ?></strong> on <?php echo date('M d, Y', strtotime($tx['date'])); ?></p>
    </div>
    <div class="col-md-4 text-end">
        <a href="../inventory/transactions.php" class="btn btn-outline-secondary">Back to Transactions</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <?php if ($attachments): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($attachments as $att): ?>
                            <tr>
                                <td>
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    <?php echo h($att['original_filename']); ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?php echo strtoupper($att['file_type']); ?></span></td>
                                <td><?php echo number_format($att['file_size'] / 1024, 2); ?> KB</td>
                                <td class="text-end">
                                    <a href="../uploads/<?php echo h($att['stored_filename']); ?>" 
                                       target="_blank" 
                                       class="btn btn-sm btn-primary">
                                       <i class="bi bi-download me-1"></i> Open File
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <p class="text-center py-5 text-muted">No attachments found for this transaction.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../partials/footer.php'; ?>
