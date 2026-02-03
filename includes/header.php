<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SFIMS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding-top: 56px; }
        .sidebar { height: 100vh; position: fixed; top: 56px; left: 0; width: 250px; background-color: #f8f9fa; padding-top: 20px; border-right: 1px solid #dee2e6; }
        .main-content { margin-left: 250px; padding: 20px; }
        @media (max-width: 768px) { .sidebar { display: none; } .main-content { margin-left: 0; } }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">SFIMS</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                                <?= htmlspecialchars($_SESSION['username']) ?> 
                                (<?= $_SESSION['role_id'] == 1 ? 'Admin' : ($_SESSION['role_id'] == 2 ? 'Staff' : 'Head') ?>)
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
    
    <?php if(isset($_SESSION['user_id'])): ?>
    <div class="sidebar">
        <ul class="nav flex-column">
            <li class="nav-item"><a class="nav-link" href="index.php">Dashboard</a></li>
            <?php if($_SESSION['role_id'] == 1): ?>
                <li class="nav-item"><a class="nav-link" href="admin_users.php">Manage Users</a></li>
                <li class="nav-item"><a class="nav-link" href="departments.php">Departments</a></li>
                <!-- <li class="nav-item"><a class="nav-link" href="settings.php">Settings</a></li> -->
            <?php endif; ?>
            <?php if($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2): ?>
                <li class="nav-item"><a class="nav-link" href="items.php">Inventory Items</a></li>
                <li class="nav-item"><a class="nav-link" href="receive.php">Receive Items</a></li>
                <li class="nav-item"><a class="nav-link" href="distribute.php">Distribute Items</a></li>
                <li class="nav-item"><a class="nav-link" href="import.php">Import Data</a></li>
            <?php endif; ?>
            <?php if($_SESSION['role_id'] == 1 || $_SESSION['role_id'] == 2 || $_SESSION['role_id'] == 3): ?>
                 <li class="nav-item"><a class="nav-link" href="reports.php">Reports</a></li>
            <?php endif; ?>
        </ul>
    </div>
    <div class="main-content">
    <?php else: ?>
    <div class="container mt-5">
    <?php endif; ?>
