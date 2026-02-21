<?php
/**
 * Global Page Header Template
 * 
 * Standardizes the look, feel, and navigation across the entire SFIMS application.
 * 1. Initializes the HTML document and injects dynamic Page Titles.
 * 2. Loads the 'Inter' typography and Bootstrap 5 design system.
 * 3. Defines institutional design tokens (Colors, Shadows, Nav Styles).
 * 4. Renders the role-based primary navigation (visible only to logged-in users).
 * 5. Handles global 'Flash Messages' for user feedback.
 */
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo isset($page_title) ? $page_title . ' | SFIMS' : 'SFIMS - Supply and Facilities Inventory Management System'; ?>
    </title>
    <!-- Bootstrap 5 CSS Framework -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Iconography System -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <!-- Modern Institutional Typography -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- SFIMS Design System Overrides -->
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
        }

        .navbar-brand {
            font-weight: 700;
            color: #0d6efd;
        }

        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }

        .btn-primary {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }

        .sidebar {
            min-height: 100vh;
            background-color: #fff;
            border-right: 1px solid #dee2e6;
        }
    </style>
</head>

<body class="bg-light">
    <?php if (is_logged_in()): ?>
        <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top shadow-sm">
            <div class="container-fluid">
                <a class="navbar-brand fw-bold" href="<?php echo BASE_URL; ?>dashboard.php">
                    <i class="bi bi-box-seam me-2"></i>SFIMS
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php">Dashboard</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>inventory/items.php">Inventory</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>inventory/transactions.php">Transactions</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>reports/reports.php">Reports</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>inventory/barcode_lookup.php">Search Barcode</a>
                        </li>
                        <?php if (is_logged_in()): ?>
                            <li class="nav-item dropdown">
                                <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Management</a>
                                <ul class="dropdown-menu">
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/users.php">User
                                            Management</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header">Master Data</h6></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/categories.php">Categories</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/sub_categories.php">Sub-Categories</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/buildings.php">Buildings</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/rooms.php">Rooms</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/departments.php">Departments</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><h6 class="dropdown-header">Asset Management</h6></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>staff/dept_assets.php">Assets by Dept</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>staff/room_assets.php">Assets by Room</a></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>staff/condemned_assets.php">Condemned Assets</a></li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/audit_logs.php">Audit
                                            Logs</a></li>
                                </ul>
                            </li>
                        <?php endif; ?>
                    </ul>
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                                data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle me-1"></i><?php echo h($_SESSION['full_name']); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><span class="dropdown-header">Staff Account</span></li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>profile.php">Account Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?php echo BASE_URL; ?>auth/logout.php">Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>
    <div class="container py-4">
        <?php display_flash_message(); ?>