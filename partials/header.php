 <!DOCTYPE html>
<html lang="en" data-theme="dark" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' | SFIMS' : 'SFIMS'; ?></title>

    <script>
        /**
         * THEME ENGINE (JavaScript)
         * This self-invoking function runs immediately to prevent a "white flash" on page load.
         */
        (function() {
            // Retrieves the user's saved theme from localStorage, defaulting to 'dark' if none exists
            const savedTheme = localStorage.getItem('sfims-theme') || 'dark';
            // Injects the theme into the root <html> element so CSS variables update instantly
            document.documentElement.setAttribute('data-theme', savedTheme);
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="shortcut icon" href="<?php echo BASE_URL; ?>assets/img/plmunicon.jpg" type="image/jpg">
    
    <style>
        /* GLOBAL TRANSITIONS: Applies smooth color and shadow fading to all elements for theme switching */
        *,
        *::before,
        *::after {
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease, box-shadow 0.3s ease;
        }

        /* ROOT VARIABLES (Light Mode): Defines the default color palette for the system */
        :root {
            --sfims-bg: #f3f7f4;
            --sfims-card-bg: #ffffff;
            --sfims-text: #1e3a1a;
            --sfims-border: #dce3dc;
            --sfims-green: #1e451e;
            --sfims-accent: #2d5a27;
            --sfims-input-bg: #ffffff;
            --header-dark: #ffffff;
            --sidebar-width: 260px;
            --nav-shadow: rgba(0, 0, 0, 0.05);
            --sidebar-text: rgba(255, 255, 255, 0.85);
            --dropdown-header-text: #6c757d;
        }

        /* DARK MODE OVERRIDES: Replaces variable values when <html> has data-theme="dark" */
        [data-theme="dark"] {
            --sfims-bg: #0d140e;
            --sfims-card-bg: #151d16;
            --sfims-text: #ffffff;
            --sfims-border: #242f25;
            --sfims-green: #1e451e;
            --sfims-input-bg: #2a332b;
            --header-dark: #151d16;
            --nav-shadow: rgba(0, 0, 0, 0.4);
            --sfims-accent: #ffffff;
            --sidebar-text: #ffffff;
            --dropdown-header-text: #cbd5e1; /* Lighter slate gray */
        }

        /* Body Configuration: Sets background, font, and ensures the page fills the screen height */
        body {
            background-color: var(--sfims-bg) !important;
            color: var(--sfims-text) !important;
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* Essential Dark Mode Overrides for Bootstrap Classes */
        [data-theme="dark"] .bg-white {
            background-color: var(--sfims-card-bg) !important;
            color: var(--sfims-text) !important;
        }

        [data-theme="dark"] .bg-light {
            background-color: rgba(255, 255, 255, 0.05) !important;
            color: var(--sfims-text) !important;
        }

        [data-theme="dark"] .text-dark,
        [data-theme="dark"] .text-black,
        [data-theme="dark"] h1, 
        [data-theme="dark"] h2, 
        [data-theme="dark"] h3, 
        [data-theme="dark"] h4, 
        [data-theme="dark"] h5, 
        [data-theme="dark"] h6,
        [data-theme="dark"] .card-title,
        [data-theme="dark"] .modal-title,
        [data-theme="dark"] .nav-tabs .nav-link {
            color: #ffffff !important;
        }

        [data-theme="dark"] .card-header,
        [data-theme="dark"] .card-footer {
            background-color: rgba(255, 255, 255, 0.03) !important;
            border-color: var(--sfims-border) !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .text-muted, 
        [data-theme="dark"] .text-secondary {
            color: #94a3b8 !important; /* Lighter slate gray for readability */
        }

        [data-theme="dark"] .btn-light {
            background-color: #2a332b !important;
            border-color: var(--sfims-border) !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-dark {
            border-color: #ffffff !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .btn-outline-dark:hover {
            background-color: #ffffff !important;
            color: #000000 !important;
        }

        [data-theme="dark"] ::placeholder {
            color: #64748b !important;
            opacity: 1 !important;
        }

        [data-theme="dark"] .table {
            --bs-table-bg: transparent;
            --bs-table-color: var(--sfims-text);
            --bs-table-border-color: var(--sfims-border);
            color: var(--sfims-text);
        }

        [data-theme="dark"] .table-hover > tbody > tr:hover > * {
            --bs-table-bg-state: rgba(255, 255, 255, 0.05);
            color: var(--sfims-text);
        }

        [data-theme="dark"] .modal-content {
            background-color: var(--sfims-card-bg);
            border-color: var(--sfims-border);
        }

        [data-theme="dark"] .modal-header,
        [data-theme="dark"] .modal-footer {
            border-color: var(--sfims-border);
        }

        [data-theme="dark"] .list-group-item {
            background-color: var(--sfims-card-bg) !important;
            border-color: var(--sfims-border) !important;
            color: var(--sfims-text) !important;
        }

        [data-theme="dark"] .alert-info {
            background-color: rgba(13, 202, 240, 0.1) !important;
            border-color: rgba(13, 202, 240, 0.2) !important;
            color: #b9eaf5 !important;
        }
        
        [data-theme="dark"] .border-bottom,
        [data-theme="dark"] .border-top,
        [data-theme="dark"] .border {
            border-color: var(--sfims-border) !important;
        }

        [data-theme="dark"] .badge.bg-info.text-dark {
            color: #000000 !important; /* Keep info badges dark text on light background unless we change the badge bg too */
            background-color: #0dcaf0 !important;
        }

        /* Overall body color insurance */
        [data-theme="dark"] p, 
        [data-theme="dark"] span,
        [data-theme="dark"] label {
            color: inherit;
        }

        /* Form Styling: Theme-aware input fields with rounded borders */
        .form-control,
        .form-select {
            background-color: var(--sfims-input-bg) !important;
            border: 1px solid var(--sfims-border) !important;
            color: var(--sfims-text) !important;
            border-radius: 8px;
        }

        /* Focus Interaction: Changes border color and adds a subtle glow when user selects a field */
        .form-control:focus,
        .form-select:focus {
            background-color: var(--sfims-input-bg) !important;
            border-color: var(--sfims-accent) !important;
            box-shadow: 0 0 0 0.25rem rgba(255, 255, 255, 0.1);
            color: var(--sfims-text) !important;
        }

        /* Adjusts the visibility of the calendar icon in date inputs for dark mode */
        input::-webkit-calendar-picker-indicator {
            filter: invert(1) opacity(0.5);
        }

        /* Fixed Header: Ensures the top navigation stays visible while scrolling down */
        .sticky-header-container {
            position: sticky;
            top: 0;
            z-index: 1030;
            box-shadow: 0 2px 10px var(--nav-shadow);
        }

        /* Navbar Styling: Horizontal bar background and spacing */
        .navbar {
            background-color: var(--header-dark) !important;
            border-bottom: 1px solid var(--sfims-border) !important;
            padding: 0.6rem 0 !important;
        }

        /* Brand Logo: Typography settings for the main "SFIMS" title */
        .brand-display {
            font-weight: 800;
            font-size: 1.3rem;
            color: var(--sfims-text) !important;
            display: flex;
            align-items: center;
            text-decoration: none;
        }

        /* Navigation Icon Hover Effects: Lift animation and color change for top links */
        .nav-link i {
            transition: transform 0.2s ease, color 0.2s ease;
        }

        .nav-link:hover i {
            transform: translateY(-2px);
            color: #4ade80 !important;
        }

        /* APP LAYOUT: Flexbox container to split space between sidebar and content */
        .app-container {
            display: flex;
            flex: 1;
        }

        /* Sidebar Styling: Fixed side menu with rounded edges and green branding color */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--sfims-green);
            padding: 20px 12px;
            flex-shrink: 0;
            margin: 15px;
            border-radius: 24px;
            height: calc(100vh - 110px);
            /* Height minus header and margins */
            position: sticky;
            top: 90px;
            display: flex;
            flex-direction: column;
        }

        /* Category labels inside the sidebar (e.g., QUICK ACTIONS) */
        .sidebar-header {
            padding: 10px 15px 15px;
            font-size: 0.7rem;
            font-weight: 800;
            color: rgba(255, 255, 255, 0.5);
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* Menu grouping: Spaces navigation links vertically */
        .sidebar-menu {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        /* Sidebar link aesthetics: Pill-shaped buttons with smooth transitions */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 12px 18px;
            color: var(--sidebar-text) !important;
            text-decoration: none;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        /* Icons inside sidebar links */
        .sidebar-link i {
            margin-right: 12px;
            font-size: 1.1rem;
        }

        /* Hover/Active states: Reverses colors to highlight the selected menu item */
        .sidebar-link:hover,
        .sidebar-link.active {
            background: #f1f3f2 !important;
            color: #1e451e !important;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Main Workspace: The actual content area where data and forms appear */
        .main-content {
            flex: 1;
            padding: 25px 30px;
        }

        /* DROPDOWNS: Custom popup menus for user settings and activity logs */
        .dropdown-menu {
            background-color: var(--sfims-card-bg) !important;
            border: 1px solid var(--sfims-border) !important;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2) !important;
            border-radius: 16px;
            padding: 10px;
            max-height: 85vh;
            overflow-y: auto;
        }

        /* Titles inside dropdown menus */
        .dropdown-header {
            color: var(--dropdown-header-text) !important;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }

        /* Individual rows inside a dropdown */
        .dropdown-item {
            color: var(--sfims-text) !important;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
        }

        .dropdown-item i {
            margin-right: 10px;
            font-size: 1.1rem;
            opacity: 0.7;
        }

        /* Hover effect for dropdown items */
        .dropdown-item:hover {
            background-color: #f1f3f2 !important;
            color: #1e451e !important;
        }

        /* Theme Toggle UI: The container for the dark mode switch button */
        .theme-switch-card {
            background: rgba(45, 90, 39, 0.2);
            border: 1px solid var(--sfims-border);
            border-radius: 12px;
            padding: 12px;
            margin-bottom: 8px;
            color: var(--sfims-text) !important;
        }

        /* Utility class for accent colors */
        .text-accent {
            color: var(--sfims-accent) !important;
        }

        /* Custom Scrollbars: Minimalist design for notification containers and menus */
        .overflow-auto::-webkit-scrollbar,
        .dropdown-menu::-webkit-scrollbar {
            width: 6px;
        }

        .overflow-auto::-webkit-scrollbar-thumb,
        .dropdown-menu::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }

        [data-theme="dark"] .overflow-auto::-webkit-scrollbar-thumb,
        [data-theme="dark"] .dropdown-menu::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Header Clock: Compact time and date display in the navbar */
        .header-clock-wrap {
            text-align: right;
            margin-right: 16px;
            line-height: 1.2;
        }

        #header-time {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--sfims-accent);
        }

        #header-date {
            font-size: 0.65rem;
            color: var(--sfims-text);
            opacity: 0.6;
        }
    </style>
</head>
<body>
    <?php if (is_logged_in()): ?>
    <div class="sticky-header-container">
        <nav class="navbar navbar-expand-lg">
            <div class="container-fluid px-4">
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="brand-display">
                    <i class="bi bi-box-seam-fill me-2" style="color: #4ade80"></i>SFIMS
                </a>
                
                <div class="collapse navbar-collapse d-flex justify-content-between">
                    <ul class="navbar-nav flex-row align-items-center gap-3 ms-4">
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>dashboard.php" title="Home">
                                <i class="bi bi-house-door-fill fs-4 text-accent"></i>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?php echo BASE_URL; ?>inventory/items.php" title="Inventory">
                                <i class="bi bi-box-seam fs-4 text-accent"></i>
                            </a>
                        </li>
                        <li class="nav-item d-none d-lg-block">
                            <a class="nav-link fw-bold text-accent" href="<?php echo BASE_URL; ?>inventory/barcode_lookup.php">
                                <i class="bi bi-upc-scan me-1"></i> Search Barcode
                            </a>
                        </li>
                    </ul>

                    <ul class="navbar-nav align-items-center flex-row">
                        <div class="header-clock-wrap d-none d-md-block">
                            <div id="header-time"></div>
                            <div id="header-date"></div>
                        </div>

                        <li class="nav-item dropdown me-3">
                            <?php 
                                $unreadCount = isset($db) ? $db->query("SELECT COUNT(*) FROM audit_logs WHERE is_read = 0")->fetchColumn() : 0;
                            ?>
                            <a class="nav-link position-relative p-0" href="#" id="activityDropdown" data-bs-toggle="dropdown" onclick="markAsRead()">
                                <i class="bi bi-bell-fill fs-5 text-accent"></i>
                                <span id="notif-red-dot" class="position-absolute top-0 start-100 translate-middle p-1 bg-danger border border-light rounded-circle <?php echo $unreadCount > 0 ? '' : 'd-none'; ?>"></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end py-0 overflow-hidden" style="width: 320px;">
                                <li class="dropdown-header border-bottom py-3 bg-light bg-opacity-10">
                                    <h6 class="mb-0 fw-bold"><i class="bi bi-clock-history me-2"></i>Recent Activity</h6>
                                </li>
                                <div class="overflow-auto" style="max-height: 350px;">
                                    <?php 
                                    $recentLogs = isset($db) ? $db->query("SELECT * FROM audit_logs ORDER BY timestamp DESC LIMIT 8")->fetchAll() : [];
                                    foreach ($recentLogs as $log): ?>
                                    <li class="dropdown-item border-bottom py-2">
                                        <div class="text-wrap w-100">
                                            <p class="mb-0 small fw-medium"><?php echo h($log['description']); ?></p>
                                            <small class="text-muted" style="font-size: 0.65rem;"><?php echo date('M d | h:i A', strtotime($log['timestamp'])); ?></small>
                                        </div>
                                    </li>
                                    <?php endforeach; ?>
                                </div>
                                <li class="text-center py-2"><a href="<?php echo BASE_URL; ?>admin/audit_logs.php" class="small text-decoration-none fw-bold text-accent">View All Logs</a></li>
                            </ul>
                        </li>

                        <li class="nav-item dropdown border-start ps-3">
                            <a class="nav-link dropdown-toggle d-flex align-items-center text-accent" href="#" id="userDropdown" data-bs-toggle="dropdown">
                                <i class="bi bi-person-circle fs-4 me-2"></i>
                                <span class="d-none d-sm-inline fw-bold"><?php echo h($_SESSION['full_name']); ?></span>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <div class="px-2">
                                    <div class="theme-switch-card d-flex justify-content-between align-items-center">
                                        <span class="small fw-bold"><i class="bi bi-moon-stars-fill me-2"></i>Dark Mode</span>
                                        <div class="form-check form-switch mb-0">
                                            <input class="form-check-input" type="checkbox" id="darkModeToggle">
                                        </div>
                                    </div>
                                </div>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/users.php"><i class="bi bi-people-fill"></i> User Management</a></li>
                                <li><a class="dropdown-item d-lg-none" href="<?php echo BASE_URL; ?>inventory/barcode_lookup.php"><i class="bi bi-upc-scan"></i> Search Barcode</a></li>
                                <li><hr class="dropdown-divider d-lg-none"></li>
                                <li><h6 class="dropdown-header">Master Data</h6></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/categories.php"><i class="bi bi-tags"></i> Categories</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/sub_categories.php"><i class="bi bi-tag"></i> Sub-Categories</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/buildings.php"><i class="bi bi-building"></i> Buildings</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/rooms.php"><i class="bi bi-door-open"></i> Rooms</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>admin/departments.php"><i class="bi bi-diagram-3"></i> Departments</a></li>
                                <li><h6 class="dropdown-header">Asset Management</h6></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>staff/dept_assets.php"><i class="bi bi-collection"></i> Assets by Dept</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>staff/room_assets.php"><i class="bi bi-geo-alt"></i> Assets by Room</a></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>staff/condemned_assets.php"><i class="bi bi-trash"></i> Condemned Assets</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo BASE_URL; ?>auth/profile.php"><i class="bi bi-gear-fill"></i> Settings</a></li>
                                <li><a class="dropdown-item text-danger fw-bold" href="<?php echo BASE_URL; ?>auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </div>

    <div class="app-container">
        <aside class="sidebar d-none d-md-flex">
            <div class="sidebar-header">Quick Actions</div>
            <nav class="sidebar-menu">
                <?php 
                $current_page = $_SERVER['PHP_SELF'];
                function is_active($path) {
                    global $current_page;
                    return strpos($current_page, $path) !== false ? 'active' : '';
                }
                ?>
                <a href="<?php echo BASE_URL; ?>dashboard.php" class="sidebar-link <?php echo is_active('dashboard.php'); ?>"><i class="bi bi-speedometer2"></i> Overview</a>
                <a href="<?php echo BASE_URL; ?>staff/items_add.php" class="sidebar-link <?php echo is_active('items_add.php'); ?>"><i class="bi bi-plus-circle-fill"></i> New Item</a>
                <a href="<?php echo BASE_URL; ?>staff/receive.php" class="sidebar-link <?php echo is_active('receive.php'); ?>"><i class="bi bi-arrow-down-square-fill"></i> Receive Stock</a>
                <a href="<?php echo BASE_URL; ?>staff/disburse.php" class="sidebar-link <?php echo is_active('disburse.php'); ?>"><i class="bi bi-arrow-up-square-fill"></i> Disburse Stock</a>
                <a href="<?php echo BASE_URL; ?>reports/reports.php" class="sidebar-link <?php echo is_active('reports.php'); ?>"><i class="bi bi-bar-chart-line-fill"></i> System Reports</a>
                <a href="<?php echo BASE_URL; ?>inventory/transactions.php" class="sidebar-link <?php echo is_active('transactions.php'); ?>"><i class="bi bi-clock-history"></i> Transactions History</a>
            </nav>
        </aside>
        
        <main class="main-content">
    <?php endif; ?>
