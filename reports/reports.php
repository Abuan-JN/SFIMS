<?php
/**
 * Unified System Reporting Dashboard
 * 
 * Centralizes all analytical data extraction and export functions.
 * 1. Supports multi-module reporting: Inventory Levels, Received Stock, Issued Stock, and Fixed Assets.
 * 2. Provides real-time filtering (e.g., Low Stock alerts, Date Range filters).
 * 3. Dual-Format Exports:
 *    - CSV: Server-side generation for raw data analysis.
 *    - PDF: Client-side generation (jsPDF) for formal presentation-ready documents.
 */

require_once '../config/database.php';
require_once '../config/app.php';

// Auth Protection: Ensure the user is authenticated before exposing institutional data
require_role();

$db = Database::getInstance();
$type = $_GET['type'] ?? 'inventory'; // active report module
$format = $_GET['format'] ?? 'html';   // display mode (html vs csv download)

$data = [];
$filename = "report_" . $type . "_" . date('Ymd') . ".csv";

// Load categories globally for filters
$categories = $db->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Logic: Route to the specific data extraction query based on 'type'
if ($type === 'inventory') {
    // Current Stock Status: Joins categories for contextual grouping
    $sql = "SELECT i.*, c.name as category_name 
            FROM items i 
            LEFT JOIN categories c ON i.category_id = c.id";
    // Optional Filter: Only show items that are at or below the safety threshold
    if (isset($_GET['low_stock'])) {
        $sql .= " AND i.current_quantity <= i.threshold_quantity";
    }
    $sql .= " ORDER BY i.name ASC";
    $data = $db->query($sql)->fetchAll();
} elseif ($type === 'received' || $type === 'issued') {
    // Transactional History: Aggregates movement over a specific date range
    $start_date = $_GET['start_date'] ?? date('Y-m-01'); // Defaults to first of current month
    $end_date = $_GET['end_date'] ?? date('Y-m-d');

    $sql = "SELECT t.*, i.name as item_name, u.full_name as user_name, c.name as category_name,
                   COALESCE(d.name, t.recipient_name, t.source_supplier, '---') as department_or_person
            FROM transactions t 
            JOIN items i ON t.item_id = i.id 
            LEFT JOIN categories c ON i.category_id = c.id
            LEFT JOIN users u ON t.performed_by = u.id 
            LEFT JOIN departments d ON t.department_id = d.id
            WHERE t.date BETWEEN ? AND ? ";

    if ($type === 'received') {
        $sql .= " AND t.type = 'RECEIVE'";
    } else {
        // 'issued' report now covers both legacy ISSUE and new DISBURSE types
        $sql .= " AND t.type IN ('ISSUE', 'DISBURSE')";
    }
    
    $params = [$start_date, $end_date];
    if (!empty($_GET['category_id'])) {
        $sql .= " AND i.category_id = ?";
        $params[] = $_GET['category_id'];
    }
    
    $sql .= " ORDER BY t.date DESC";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
} elseif ($type === 'assets') {
    // Fixed Asset Registry: Maps physical units to their institutional locations
    $sql = "SELECT ii.*, i.name as item_name, d.name as dept_name, r.name as room_name, b.name as building_name, bc.barcode_value
            FROM item_instances ii 
            JOIN items i ON ii.item_id = i.id 
            JOIN barcodes bc ON ii.barcode_id = bc.id
            LEFT JOIN departments d ON ii.assigned_department_id = d.id
            LEFT JOIN rooms r ON ii.room_id = r.id
            LEFT JOIN buildings b ON r.building_id = b.id
            WHERE 1=1";
            
    $params = [];
    
    // Apply Filters for Fixed Assets
    if (!empty($_GET['asset_status'])) {
        $sql .= " AND ii.status = ?";
        $params[] = $_GET['asset_status'];
    }
    if (!empty($_GET['department_id'])) {
        $sql .= " AND ii.assigned_department_id = ?";
        $params[] = $_GET['department_id'];
    }
    if (!empty($_GET['room_id'])) {
        $sql .= " AND ii.room_id = ?";
        $params[] = $_GET['room_id'];
    }

    $sql .= " ORDER BY i.name ASC, bc.barcode_value ASC";
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    
    // Fetch filter reference data
    $departments = $db->query("SELECT * FROM departments ORDER BY name ASC")->fetchAll();
    $rooms = $db->query("SELECT r.id, r.name as room_name, b.name as building_name FROM rooms r JOIN buildings b ON r.building_id = b.id ORDER BY b.name ASC, r.name ASC")->fetchAll();
}

// Handle CSV Export
if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');

    if ($data) {
        // Add Filter Metadata
        $filter_text = "All Records";
        if ($type === 'inventory' && isset($_GET['low_stock'])) {
            $filter_text = "Low Stock Items Only";
        } elseif ($type === 'received' || $type === 'issued') {
            $filter_text = "Date Range: " . date('M d, Y', strtotime($start_date ?? date('Y-m-d'))) . " to " . date('M d, Y', strtotime($end_date ?? date('Y-m-d')));
            if (!empty($_GET['category_id'])) {
                $cat_name = array_column(array_filter($categories, fn($c) => $c['id'] == $_GET['category_id']), 'name')[0] ?? $_GET['category_id'];
                $filter_text .= " | Category: " . $cat_name;
            }
        } elseif ($type === 'assets') {
            $filter_parts = [];
            if (!empty($_GET['asset_status'])) $filter_parts[] = "Status: " . $_GET['asset_status'];
            if (!empty($_GET['department_id'])) {
                $dept_name = array_column(array_filter($departments, fn($d) => $d['id'] == $_GET['department_id']), 'name')[0] ?? $_GET['department_id'];
                $filter_parts[] = "Dept: " . $dept_name;
            }
            if (!empty($_GET['room_id'])) {
                $room_name = array_column(array_filter($rooms, fn($r) => $r['id'] == $_GET['room_id']), 'room_name')[0] ?? $_GET['room_id'];
                $filter_parts[] = "Room: " . $room_name;
            }
            if (!empty($filter_parts)) {
                $filter_text = implode(', ', $filter_parts);
            }
        }

        fputcsv($output, [strtoupper($type) . ' REPORT']);
        fputcsv($output, ['Generated By:', $_SESSION['full_name'] ?? '']);
        fputcsv($output, ['Date Generated:', date('Y-m-d H:i:s')]);
        fputcsv($output, ['Applied Filter:', $filter_text]);
        fputcsv($output, []); // Empty row separator

        if ($type === 'assets') {
            // Institutional Format for Fixed Asset Inventory
            fputcsv($output, ['No.', 'Full Description of the Assets', 'Serial Number', 'Acquisition Date', 'Remarks', 'Place / Room Located', 'Contact No.', 'Person Responsible']);
            $counter = 1;
            foreach ($data as $row) {
                fputcsv($output, [
                    $counter++,
                    $row['item_name'] . ' (BC: ' . $row['barcode_value'] . ')',
                    $row['serial_number'] ?: '--',
                    date('Y-m-d', strtotime($row['last_updated'])),
                    $row['status'],
                    $row['room_name'] ? ($row['building_name'] . ' - ' . $row['room_name']) : ($row['dept_name'] ?: 'Warehouse'),
                    $row['contact_number'] ?: '--',
                    $row['assigned_person'] ?: 'Unassigned'
                ]);
            }
        } else {
            // General CSV Export for other reports
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
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
        <!-- Global actions if any -->
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
                <div class="col-md-3">
                    <label class="form-label small fw-bold">From Date</label>
                    <input type="date" name="start_date" class="form-control"
                        value="<?php echo $_GET['start_date'] ?? date('Y-m-01'); ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">To Date</label>
                    <input type="date" name="end_date" class="form-control"
                        value="<?php echo $_GET['end_date'] ?? date('Y-m-d'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Category Filter</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo ($_GET['category_id'] ?? '') == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo h($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                </div>
                <div class="col-12 mt-3 d-flex gap-2 justify-content-end border-top pt-3">
                    <a href="?type=<?php echo $type; ?>&format=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
                    <button type="button" class="btn btn-danger btn-sm export-pdf-btn"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($type === 'assets'): ?>
            <form method="GET" class="row g-2 mb-4 bg-light p-3 rounded">
                <input type="hidden" name="type" value="<?php echo $type; ?>">
                
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Status</label>
                    <select name="asset_status" class="form-select select2">
                        <option value="">All Statuses</option>
                        <option value="in-stock" <?php echo ($_GET['asset_status'] ?? '') === 'in-stock' ? 'selected' : ''; ?>>In-Stock</option>
                        <option value="issued" <?php echo ($_GET['asset_status'] ?? '') === 'issued' ? 'selected' : ''; ?>>Issued</option>
                        <option value="under repair" <?php echo ($_GET['asset_status'] ?? '') === 'under repair' ? 'selected' : ''; ?>>Under Repair</option>
                        <option value="disposed" <?php echo ($_GET['asset_status'] ?? '') === 'disposed' ? 'selected' : ''; ?>>Disposed</option>
                        <option value="lost" <?php echo ($_GET['asset_status'] ?? '') === 'lost' ? 'selected' : ''; ?>>Lost</option>
                        <option value="condemned-serviced" <?php echo ($_GET['asset_status'] ?? '') === 'condemned-serviced' ? 'selected' : ''; ?>>Condemned - Serviced</option>
                        <option value="condemned-trash" <?php echo ($_GET['asset_status'] ?? '') === 'condemned-trash' ? 'selected' : ''; ?>>Condemned - Trash</option>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-bold">Department</label>
                    <select name="department_id" class="form-select select2">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $d): ?>
                            <option value="<?php echo $d['id']; ?>" <?php echo ($_GET['department_id'] ?? '') == $d['id'] ? 'selected' : ''; ?>>
                                <?php echo h($d['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Room</label>
                    <select name="room_id" class="form-select select2">
                        <option value="">All Rooms</option>
                        <?php foreach ($rooms as $r): ?>
                            <option value="<?php echo $r['id']; ?>" <?php echo ($_GET['room_id'] ?? '') == $r['id'] ? 'selected' : ''; ?>>
                                <?php echo h($r['building_name'] . ' - ' . $r['room_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i></button>
                    <a href="?type=assets" class="btn btn-outline-secondary w-100 ms-1" title="Clear Filters"><i class="bi bi-x-lg"></i></a>
                </div>
                
                <div class="col-12 mt-3 d-flex gap-2 justify-content-end border-top pt-3">
                    <a href="?type=<?php echo $type; ?>&format=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
                    <button type="button" class="btn btn-danger btn-sm export-pdf-btn"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if ($type === 'inventory'): ?>
            <div class="d-flex justify-content-end gap-2 mb-3">
                <a href="?type=<?php echo $type; ?>&format=csv&<?php echo http_build_query($_GET); ?>" class="btn btn-success btn-sm"><i class="bi bi-file-earmark-spreadsheet me-1"></i> Export CSV</a>
                <button type="button" class="btn btn-danger btn-sm export-pdf-btn"><i class="bi bi-file-earmark-pdf me-1"></i> Export PDF</button>
            </div>
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
                            <?php if ($type === 'issued'): ?>
                                <th class="text-end no-print-pdf">Action</th>
                            <?php endif; ?>
                        </tr>
                    <?php elseif ($type === 'assets'): ?>
                        <tr>
                            <th style="width: 50px;">No.</th>
                            <th>Full Description of the Assets</th>
                            <th>Serial Number</th>
                            <th>Acquisition Date</th>
                            <th>Remarks</th>
                            <th>Place / Room Located</th>
                            <th>Contact No.</th>
                            <th>Person Responsible</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php if ($data): ?>
                        <?php $counter = 1; foreach ($data as $row): ?>
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
                                    <?php if ($type === 'issued'): ?>
                                        <td class="text-end no-print-pdf">
                                            <a href="../staff/disburse_print.php?id=<?php echo $row['id']; ?>" target="_blank" class="btn btn-sm btn-outline-dark" title="Print Disbursement Form">
                                                <i class="bi bi-printer"></i> Print
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php elseif ($type === 'assets'): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td>
                                        <strong class="d-block"><?php echo h($row['item_name']); ?></strong>
                                        <span class="badge bg-light text-dark border fw-mono small">BC: <?php echo h($row['barcode_value']); ?></span>
                                    </td>
                                    <td><?php echo h($row['serial_number'] ?: '--'); ?></td>
                                    <td><?php echo h($row['last_updated'] ? date('M d, Y', strtotime($row['last_updated'])) : '--'); ?></td>
                                    <td><?php echo h($row['status']); ?></td>
                                    <td>
                                        <?php if($row['dept_name']): ?>
                                            <?php echo h($row['dept_name']); ?>
                                            <?php if($row['room_name']): ?>
                                                <br><small class="text-muted"><?php echo h($row['building_name'] . ' - ' . $row['room_name']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Warehouse</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo h($row['contact_number'] ?: '--'); ?></td>
                                    <td><?php echo h($row['assigned_person'] ?: 'Unassigned'); ?></td>
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

<!-- Hidden Logo for PDF Generation -->
<img src="<?php echo BASE_URL; ?>assets/img/plmunicon.jpg" id="plmunLogo" style="display:none;" crossorigin="anonymous">

<script>
document.querySelectorAll('.export-pdf-btn').forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'pt', 'a4'); // Landscape, points, A4
        const pageWidth = doc.internal.pageSize.getWidth();
        const pageHeight = doc.internal.pageSize.getHeight();
        
        const reportType = "<?php echo strtoupper($type); ?> REPORT";
        const dateRangeStr = "<?php 
            if ($type === 'received' || $type === 'issued') {
                echo 'From ' . date('F d, Y', strtotime($start_date)) . ' to ' . date('F d, Y', strtotime($end_date));
                if (!empty($_GET['category_id'])) {
                    $cat_name = array_column(array_filter($categories, fn($c) => $c['id'] == $_GET['category_id']), 'name')[0] ?? $_GET['category_id'];
                    echo ' (Category: ' . $cat_name . ')';
                }
            } else {
                echo 'As of ' . date('F d, Y');
                if ($type === 'inventory' && isset($_GET['low_stock'])) {
                    echo ' (Filter: Low Stock Only)';
                } elseif ($type === 'assets') {
                    $js_filters = [];
                    if (!empty($_GET['asset_status'])) $js_filters[] = "Status: " . $_GET['asset_status'];
                    if (!empty($_GET['department_id'])) {
                        $js_filters[] = "Dept: " . (array_column(array_filter($departments, fn($d) => $d['id'] == $_GET['department_id']), 'name')[0] ?? $_GET['department_id']);
                    }
                    if (!empty($_GET['room_id'])) {
                        $js_filters[] = "Room: " . (array_column(array_filter($rooms, fn($r) => $r['id'] == $_GET['room_id']), 'room_name')[0] ?? $_GET['room_id']);
                    }
                    if (!empty($js_filters)) {
                        echo ' (Filter: ' . implode(', ', $js_filters) . ')';
                    }
                }
            }
        ?>";
        const generatedBy = "Generated by: <?php echo h($_SESSION['full_name']); ?>";
        const generatedDate = "Date Generated: <?php echo date('F d, Y h:i A'); ?>";

        // Helper for Centered Text
        function centerText(text, y, size = 12, style = 'normal') {
            doc.setFontSize(size);
            doc.setFont('helvetica', style);
            const textWidth = doc.getStringUnitWidth(text) * size / doc.internal.scaleFactor;
            const x = (pageWidth - textWidth) / 2;
            doc.text(text, x, y);
        }

        // --- HEADER ---
        let yPos = 40;
        
        // Draw Logo if loaded
        const logoImg = document.getElementById('plmunLogo');
        if (logoImg && logoImg.complete) {
            doc.addImage(logoImg, 'JPEG', 40, 30, 60, 60);
        }

        centerText("Republic of the Philippines", yPos, 10);
        yPos += 15;
        centerText("PAMANTASAN NG LUNGSOD NG MUNTINLUPA", yPos, 14, 'bold');
        yPos += 15;
        centerText("Supply and Property Management Office", yPos, 12);
        yPos += 10;
        doc.setLineWidth(1.5);
        doc.line(40, yPos, pageWidth - 40, yPos); // Header Line
        
        yPos += 30;
        centerText(reportType, yPos, 16, 'bold');
        yPos += 15;
        centerText(dateRangeStr, yPos, 10, 'italic');

        // Temporarily hide columns flagged for exclusion in PDF
        document.querySelectorAll('.no-print-pdf').forEach(el => el.style.display = 'none');

        // --- DATA TABLE ---
        doc.autoTable({
            html: '#reportTable',
            startY: yPos + 20,
            theme: 'grid',
            headStyles: { 
                fillColor: [255, 255, 255], 
                textColor: [0, 0, 0], 
                lineWidth: 1, // Add border to header
                lineColor: [0, 0, 0] // Black border
            },
            styles: { 
                fontSize: 9, 
                lineColor: [0, 0, 0], // Black border for body
                lineWidth: 0.5 
            },
            didDrawPage: function (data) {
                // Footer (Page Number)
                const str = 'Page ' + doc.internal.getNumberOfPages();
                doc.setFontSize(8);
                doc.text(str, pageWidth - 60, pageHeight - 20);
                doc.text(generatedDate, 40, pageHeight - 20);
            }
        });
        
        // Restore hidden columns
        document.querySelectorAll('.no-print-pdf').forEach(el => el.style.display = '');

        // --- SIGNATORIES ---
        let finalY = doc.lastAutoTable.finalY + 50;
        
        // Ensure signatures don't fall off the page
        if (finalY > pageHeight - 100) {
            doc.addPage();
            finalY = 60;
        }

        doc.setFontSize(10);
        doc.setFont('helvetica', 'normal');

        const sectionWidth = pageWidth / 3;
        
        // Prepared By
        let xPos = 40;
        doc.text("Prepared by:", xPos, finalY);
        doc.text("<?php echo h($_SESSION['full_name']); ?>", xPos, finalY + 30); // Auto-fill current user
        doc.line(xPos, finalY + 32, xPos + 150, finalY + 32); // Underline
        doc.text("Inventory Staff", xPos, finalY + 45);

        // Noted By
        xPos += sectionWidth;
        doc.text("Noted by:", xPos, finalY);
        doc.line(xPos, finalY + 32, xPos + 150, finalY + 32);
        doc.text("Department Head / Supply Officer", xPos, finalY + 45);

        // Approved By
        xPos += sectionWidth;
        doc.text("Approved by:", xPos, finalY);
        doc.line(xPos, finalY + 32, xPos + 150, finalY + 32);
        doc.text("University Administrator", xPos, finalY + 45);
        
        const filename = `SPMO_PLMun_${reportType.replace(/\s+/g, '_')}_<?php echo date('Ymd'); ?>.pdf`;
        doc.save(filename);
    });
});
</script>

<?php require_once '../partials/footer.php'; ?>
