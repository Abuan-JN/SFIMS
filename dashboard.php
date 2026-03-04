<?php
/**
 * Dashboard / Home Page - SFIMS
 * Purpose: This is the main entry point after login, displaying the inventory overview.
 */

// BACKEND: require_once loads the database and app configurations only once to save resources
require_once 'config/database.php'; // Connects to the SQL database
require_once 'config/app.php';      // Loads global functions and session security

// SECURITY: require_role() checks if the user has permission to view this specific page
require_role();

// DATABASE LOGIC: Initialize the DB connection and prepare an array for summary data
$db = Database::getInstance();
$stats = [
    // Counts all rows in the 'items' table where the status column equals 'active'
    'total_items' => $db->query("SELECT COUNT(*) FROM items WHERE status = 'active'")->fetchColumn(),
    
    // Counts active items where the current stock is less than or equal to the minimum threshold
    'low_stock'   => $db->query("SELECT COUNT(*) FROM items WHERE status = 'active' AND current_quantity <= threshold_quantity")->fetchColumn(),
    
    // Counts the total number of entries in the 'departments' table
    'dept_count'  => $db->query("SELECT COUNT(*) FROM departments")->fetchColumn(),

    // Counts categories and sub-categories
    'cat_count'   => $db->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
    'sub_cat_count' => $db->query("SELECT COUNT(*) FROM sub_categories")->fetchColumn()
];

// UI SETTINGS: Sets the tab title and includes the common navigation header
$page_title = 'Home'; 
require_once 'partials/header.php';
?>

<div class="dashboard-wrapper mt-3 container-fluid">
    
    <div class="row mb-4">
        <div class="col-12">
            </div>
    </div>

    <div class="row g-4 mb-4">
        
        <div class="col-md-3">
            <div class="card h-100 dashboard-card border-0 shadow-sm">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box-pbi bg-green-op me-3">
                            <i class="bi bi-box-seam-fill text-green"></i>
                        </div>
                        <h6 class="card-label small fw-bold mb-0">Total Items</h6>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <h2 class="mb-0 fw-800 card-value me-2"><?php echo number_format($stats['total_items']); ?></h2>
                        <span class="text-muted small text-adaptive">Units</span>
                    </div>
                    <div class="mt-3">
                        <a href="inventory/items.php" class="text-green text-decoration-none small stretched-link fw-bold">
                            View Full Inventory <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-accent bg-green"></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 dashboard-card border-0 shadow-sm">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box-pbi bg-danger-op me-3">
                            <i class="bi bi-shield-exclamation text-danger"></i>
                        </div>
                        <h6 class="card-label small fw-bold mb-0 text-danger">Low Stock Alert</h6>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <h2 class="mb-0 fw-800 card-value text-danger me-2"><?php echo number_format($stats['low_stock']); ?></h2>
                        <span class="text-danger small fw-bold opacity-75">Needs Attention</span>
                    </div>
                    <div class="mt-3">
                        <a href="reports/reports.php?low_stock=1" class="text-danger text-decoration-none small stretched-link fw-bold">
                            View Stock Alerts <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-accent bg-danger"></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 dashboard-card border-0 shadow-sm">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box-pbi bg-info-op me-3">
                            <i class="bi bi-diagram-3 text-info"></i>
                        </div>
                        <h6 class="card-label small fw-bold mb-0 text-info">Sub-Categories</h6>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <h2 class="mb-0 fw-800 card-value me-2 text-info"><?php echo number_format($stats['sub_cat_count']); ?></h2>
                        <span class="text-info small fw-bold opacity-75"><?php echo $stats['cat_count']; ?> Categories</span>
                    </div>
                    <div class="mt-3">
                        <a href="admin/sub_categories.php" class="text-info text-decoration-none small stretched-link fw-bold">
                            Manage Categories <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-accent bg-info"></div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card h-100 dashboard-card border-0 shadow-sm">
                <div class="card-body p-4 position-relative">
                    <div class="d-flex align-items-center mb-3">
                        <div class="icon-box-pbi bg-warning-op me-3">
                            <i class="bi bi-building-gear text-warning"></i>
                        </div>
                        <h6 class="card-label small fw-bold mb-0 text-warning">Departments</h6>
                    </div>
                    <div class="d-flex align-items-baseline">
                        <h2 class="mb-0 fw-800 card-value me-2 text-warning"><?php echo number_format($stats['dept_count']); ?></h2>
                        <span class="text-warning small fw-bold opacity-75">Active Units</span>
                    </div>
                    <div class="mt-3">
                        <a href="admin/departments.php" class="text-warning text-decoration-none small stretched-link fw-bold">
                            Manage Entities <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
                <div class="card-accent bg-warning"></div>
            </div>
        </div>
    </div>
</div>

<style>
        /* TYPOGRAPHY: Imports the 'Inter' font from Google Fonts */
    @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap');

    /* GLOBAL THEME: Defines a standard corner radius for consistent design */
    :root {
        --system-radius: 12px;
    }

    /* FONT WEIGHT: Defines a custom helper for extra bold text */
    .dashboard-wrapper {
        font-family: 'Inter', sans-serif;
    }

    .fw-800 {
        font-weight: 800;
    }

    /* UI CONSISTENCY: Applies the standard radius to all buttons and navigation elements */
    .btn,
    .nav-pills .nav-link,
    .nav-tabs .nav-link,
    .page-link {
        border-radius: var(--system-radius) !important;
        padding: 8px 20px;
        transition: all 0.2s ease;
    }

    /* NAV PILLS: Adds a subtle glow effect to the active tab */
    .nav-pills .nav-link.active {
        box-shadow: 0 4px 10px rgba(13, 110, 253, 0.2);
    }

    /* CARD STYLE: Defines the background and transition for the metrics cards */
    .dashboard-card {
        background: #ffffff !important;
        border-radius: var(--system-radius) !important;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        overflow: hidden;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }

    /* HOVER INTERACTION: Makes the card lift up and increases shadow depth */
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08) !important;
    }

    /* TEXT SIZING: Large impact sizing for numerical values and small labels */
    .card-value {
        font-size: 2.6rem;
        letter-spacing: -1.5px;
        color: #1e293b;
    }

    .card-label {
        letter-spacing: 0.8px;
        text-transform: uppercase;
        color: #64748b;
    }

    /* ICON BOXES: Centered boxes for icons with specific dimensions */
    .icon-box-pbi {
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 10px;
        font-size: 1.5rem;
    }

    /* COLORS: Semi-transparent background colors for the icon boxes */
    .bg-green-op {
        background: rgba(46, 204, 113, 0.15) !important;
    }

    .bg-danger-op {
        background: rgba(220, 53, 69, 0.15) !important;
    }

    .bg-info-op {
        background: rgba(13, 202, 240, 0.15) !important;
    }

    .bg-warning-op {
        background: rgba(255, 193, 7, 0.15) !important;
    }

    .text-green {
        color: #2ecc71 !important;
    }

    .text-warning {
        color: #f1c40f !important;
    }

    /* ACCENT BAR ANIMATION: Colored line at bottom expands to full width on hover */
    .card-accent {
        height: 4px;
        width: 30%;
        position: absolute;
        bottom: 0;
        left: 0;
        transition: width 0.3s ease;
    }

    .dashboard-card:hover .card-accent {
        width: 100%;
    }

    /* DARK THEME: Overrides colors for dark mode preference */
    [data-theme="dark"] .dashboard-title {
        color: #f8fafc !important;
    }

    [data-theme="dark"] .dashboard-card {
        background: #1e1e2d !important;
        border: 1px solid rgba(255, 255, 255, 0.08) !important;
    }

    [data-theme="dark"] .card-value {
        color: #ffffff !important;
    }

    /* INPUT THEME: Style adjustments for form fields in dark mode */
    [data-theme="dark"] .form-control,
    [data-theme="dark"] .form-select,
    [data-theme="dark"] .input-group-text {
        background-color: #2b2b3d !important;
        border-color: #45455d !important;
        color: #ffffff !important;
        border-radius: var(--system-radius) !important;
    }

    /* FORM UTILITIES: Placeholder color and date picker icon visibility in dark mode */
    [data-theme="dark"] ::placeholder {
        color: #94a3b8 !important;
    }

    [data-theme="dark"] input[type="date"]::-webkit-calendar-picker-indicator {
        filter: invert(1);
    }
</style>

<?php 
// FOOTER: Includes the common closing partial and scripts
require_once 'partials/footer.php'; 
?>
