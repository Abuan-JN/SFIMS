<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
require_once 'includes/db.php';
include 'includes/header.php';
?>

<div class="row">
    <div class="col-md-12">
        <h2>Dashboard</h2>
        <p>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?>!</p>
        
        <div class="row">
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-header">Total Items</div>
                    <div class="card-body">
                        <h5 class="card-title">0</h5>
                        <p class="card-text">Items in inventory.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-danger mb-3">
                    <div class="card-header">Low Stock</div>
                    <div class="card-body">
                        <h5 class="card-title">0</h5>
                        <p class="card-text">Items below threshold.</p>
                    </div>
                </div>
            </div>
            <!-- Add more widgets as needed -->
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>