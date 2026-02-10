# SFIMS - Supply and Facilities Inventory Management System

<div align="center">

![SFIMS Logo](https://www.plmun.edu.ph/images/plmun_logo.png)

**A web-based, mobile-responsive inventory management system for Pamantasan ng Lungsod ng Muntinlupa**

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/releases/7.4.php)
[![Bootstrap Version](https://img.shields.io/badge/Bootstrap-5.3-purple)](https://getbootstrap.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)

</div>

---

## 1. Project Overview

SFIMS (Supply and Facilities Inventory Management System) is a comprehensive web application designed to digitize and streamline the receiving, tracking, and distribution of expendable and non-expendable items for educational institutions. The system leverages barcode technology for efficient asset tracking and implements role-based access control with an admin-approved registration workflow.

### Key Features

- **📦 Inventory Management**: Complete CRUD operations for items with support for both consumables and fixed assets
- **📱 Mobile-Responsive Design**: Built with Bootstrap 5 for seamless use across desktop and mobile devices
- **🏷️ Barcode Integration**: Auto-generation and scanning capabilities for fixed asset tracking
- **🔐 Role-Based Access Control**: Admin approval workflow for user registration
- **📊 Comprehensive Reporting**: CSV and PDF export for inventory, transactions, and asset reports
- **🔔 Low Stock Alerts**: Automated notifications when inventory falls below thresholds
- **📋 Audit Trail**: Complete tracking of all system activities for accountability
- **📁 File Attachments**: Support for uploading documents (PO, DR, delivery receipts)

---

## 2. Technology Stack

### Backend
- **PHP 7.4/8.0+**: Server-side scripting with OOP principles
- **Plain PHP (No Framework)**: Modular architecture following MVC patterns
- **PDO**: Secure database access with prepared statements

### Frontend
- **HTML5**: Semantic markup for accessibility
- **CSS3**: Custom styling with Bootstrap 5 framework
- **JavaScript**: Dynamic interactions and AJAX functionality
- **Bootstrap Icons**: Iconography system

### Database
- **MySQL 8.0+ / MariaDB**: Relational database with InnoDB engine
- **Normalized Schema**: Proper foreign keys and cascading deletes

### Server
- **Apache**: Web server (via XAMPP for development)
- **XAMPP**: Development environment (Windows)

### Additional Libraries
- **jsPDF**: Client-side PDF generation
- **jsPDF-AutoTable**: Table formatting for PDF exports

---

## 3. Project Structure

```
SFIMS/
├── index.php                 # Application entry point
├── dashboard.php              # Main dashboard with statistics
├── README.md                  # Project documentation
│
├── config/
│   ├── app.php               # Global configuration & helper functions
│   └── database.php          # Database connection (Singleton pattern)
│
├── auth/
│   ├── login.php             # User authentication
│   ├── logout.php            # Session termination
│   ├── register.php          # User registration
│   └── profile.php           # User profile management
│
├── admin/
│   ├── users.php             # User management (activate/deactivate)
│   ├── audit_logs.php        # System audit log viewer
│   ├── buildings.php         # Building management
│   ├── departments.php       # Department management
│   └── rooms.php             # Room/location management
│
├── inventory/
│   ├── items.php             # Inventory list with filters
│   ├── item_details.php      # Detailed item view
│   ├── transactions.php      # Transaction history
│   ├── barcode_lookup.php    # Quick asset search by barcode
│   └── barcode_print.php     # Barcode label printing
│
├── staff/
│   ├── items_add.php         # Add new items to catalog
│   ├── items_edit.php        # Edit existing items
│   ├── receive.php           # Stock receiving (incoming inventory)
│   ├── disburse.php          # Stock disbursement (outgoing)
│   ├── move.php              # Asset location transfer
│   ├── condemn.php           # Asset disposal/condemnation
│   ├── import_stock.php      # Bulk import via CSV
│   ├── dept_assets.php      # Assets grouped by department
│   ├── room_assets.php      # Assets grouped by room
│   ├── condemned_assets.php # View all condemned assets
│   ├── get_instances.php    # AJAX endpoint for asset instances
│   ├── disburse_print.php    # Print disbursement form
│   └── condemn_print.php     # Print condemnation form
│
├── reports/
│   └── reports.php          # Unified reporting dashboard
│
├── partials/
│   ├── header.php           # Global page header & navigation
│   └── footer.php           # Global page footer & scripts
│
├── models/                   # (Reserved for future model classes)
│
├── core/
│   └── test_db.php          # Database connection diagnostic tool
│
├── scripts/
│   └── migrate_barcodes.php # Database migration scripts
│
├── docs/
│   ├── database.sql         # Database schema & seed data
│   ├── setup_guide.md        # Installation instructions
│   └── user_manual.md       # End-user documentation
│
├── assets/
│   ├── css/                 # Custom stylesheets
│   └── js/                  # Custom JavaScript files
│
└── uploads/                 # File attachments directory
```

---

## 4. Installation Guide

### Prerequisites

- **XAMPP for Windows** (or equivalent LAMP/WAMP stack)
  - Apache 2.4+
  - PHP 7.4 or higher
  - MySQL 8.0 or MariaDB 10.4+
- **Web Browser**: Modern browser (Chrome, Firefox, Edge, Safari)
- **Code Editor**: VS Code, PhpStorm, or similar

### Setup Steps

#### 1. Clone/Download the Project

Copy the `SFIMS` folder to your XAMPP's `htdocs` directory:

```bash
C:\xampp\htdocs\SFIMS
```

#### 2. Database Setup

1. Open **phpMyAdmin** (`http://localhost/phpmyadmin`)
2. Create a new database named `sfims`
3. Select the `sfims` database
4. Go to the **Import** tab
5. Choose the `database.sql` file located in `docs/database.sql`
6. Click **Go** to execute the SQL script

#### 3. Configuration

Edit `config/database.php` to match your MySQL credentials:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sfims');
define('DB_USER', 'root');
define('DB_PASS', ''); // Default XAMPP has no password
```

Edit `config/app.php` to set the correct base URL:

```php
define('BASE_URL', 'http://localhost/SFIMS/');
```

#### 4. Start the Server

1. Launch XAMPP Control Panel
2. Start **Apache** and **MySQL** services
3. Open your browser and navigate to: `http://localhost/SFIMS/`

#### 5. Default Login Credentials

| Role     | Username | Password  |
|----------|----------|-----------|
| Admin    | admin    | password  |

> ⚠️ **Security Note**: Change the default admin password immediately after first login.

---

## 5. User Roles & Permissions

### User Status Workflow

SFIMS implements a three-status user account system:

| Status     | Description                                      |
|------------|--------------------------------------------------|
| `pending`  | New registration awaiting admin approval        |
| `active`   | Fully authenticated user with system access     |
| `deactivated` | Revoked access (soft lock)                   |

### Registration Flow

1. **Public Registration**: Users can register via `/auth/register.php`
2. **Admin Approval**: Admins review pending registrations in Admin > Users
3. **Account Activation**: Admin activates the account to enable login
4. **Login Access**: Only users with `active` status can authenticate

### Role Management

- **Admin**: Full system access including user management, audit logs, and all operations
- **Staff**: Can manage inventory, process transactions, and generate reports

---

## 6. Usage Guide

### 6.1 Dashboard Overview

The dashboard (`dashboard.php`) provides:
- Total item count
- Low stock alerts
- Pending user approvals
- Department statistics
- Recent activity feed
- Quick action buttons

### 6.2 Adding New Items

1. Navigate to **Inventory** > **Add New Item**
2. Fill in:
   - **Item Name**: Descriptive name
   - **Description**: Detailed specifications
   - **Category**: Consumables or Fixed Assets
   - **Unit of Measure**: pcs, box, set, etc.
   - **Low Stock Threshold**: Alert level
3. Click **Save Item**

### 6.3 Receiving Stock

1. Go to **Staff** > **Receive Items**
2. Select the item from the dropdown
3. Enter **Quantity Received**
4. For Fixed Assets: Enter serial numbers and barcodes
5. Optional: Upload delivery receipt or PO
6. Click **Receive Stock**

### 6.4 Disbursing Items

1. Navigate to **Staff** > **Disburse Items**
2. Select item and quantity
3. For Fixed Assets: Select specific instances via barcode scan
4. Choose **Department** and **Room** location
5. Enter **Recipient Name**
6. Click **Confirm Disbursement**
7. Print the disbursement form

### 6.5 Barcode Operations

#### Barcode Lookup
- Navigate to **Inventory** > **Search Barcode**
- Scan or type the barcode
- View asset details, current status, and location

#### Printing Barcodes
- Go to **Item Details** for a fixed asset
- Click **Print Barcodes** for printable labels

### 6.6 Asset Movement

To relocate an already-issued asset:
1. Navigate to **Staff** > **Assets by Dept/Room**
2. Select the asset to move
3. Click **Move** action
4. Select new department and room
5. Add remarks and confirm

### 6.7 Condemning Assets

1. Go to **Item Details** for a fixed asset
2. Click **Condemn** action
3. Select condemnation type:
   - **For Servicing**: Repairable assets
   - **Trash**: Non-repairable disposal
4. Add remarks and confirm

---

## 7. Reports & Analytics

### Available Reports

| Report Type     | Description                                    |
|-----------------|------------------------------------------------|
| **Current Inventory** | All items with stock levels and status     |
| **Low Stock**       | Items below threshold quantity           |
| **Received Stock**  | Inbound transactions with date filters     |
| **Issued Stock**    | Outbound transactions with date filters   |
| **Fixed Assets**     | Complete asset registry with locations    |

### Export Options

- **CSV Export**: Server-side generation for data analysis
- **PDF Export**: Client-side generation for formal documentation

---

## 8. Configuration Options

### Environment Variables (config/database.php)

```php
// Database Configuration
define('DB_HOST', 'localhost');     // Database server
define('DB_NAME', 'sfims');        // Database name
define('DB_USER', 'root');         // Database username
define('DB_PASS', '');             // Database password

// Application Configuration (config/app.php)
define('BASE_URL', 'http://localhost/SFIMS/');
```

### File Upload Settings

Allowed file types: `jpg`, `jpeg`, `png`, `pdf`, `doc`, `docx`
Maximum file size: Server-defined (typically 2MB-8MB)

---

## 9. API Reference

### AJAX Endpoints

| Endpoint                  | Method | Description                              |
|---------------------------|--------|------------------------------------------|
| `/staff/get_instances.php`| GET    | Fetch available instances for an item    |
| `/inventory/barcode_lookup.php`| GET | Search asset by barcode                |

### URL Parameters

#### Barcode Lookup
```
GET /inventory/barcode_lookup.php?barcode={value}
```

#### Reports
```
GET /reports/reports.php?type={inventory|received|issued|assets}&format={html|csv}&start_date={date}&end_date={date}
```

---

## 10. Development Guide

### Coding Standards

- **PHP**: Follow PSR-12 coding style
- **SQL**: Use prepared statements for all queries
- **HTML**: Semantic markup with Bootstrap 5 classes
- **JavaScript**: Vanilla JS with minimal dependencies

### Database Transactions

Always use database transactions for multi-step operations:

```php
$db->beginTransaction();
try {
    // Database operations
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

### Audit Logging

Record all significant actions:

```php
$logStmt = $db->prepare("INSERT INTO audit_logs 
    (user_id, action_type, entity_name, entity_id, description) 
    VALUES (?, ?, ?, ?, ?)");
$logStmt->execute([$user_id, 'ACTION_NAME', 'Entity', $entity_id, 'Description']);
```

### Access Control

Use the provided helper functions:

```php
// Check if user is logged in
if (!is_logged_in()) {
    redirect('auth/login.php');
}

// Require authentication (expandable for role checks)
require_role();
```

---

## 11. Troubleshooting

### Common Issues

#### "Connection failed" Error
- Verify MySQL service is running
- Check credentials in `config/database.php`
- Ensure database `sfims` exists

#### File Upload Fails
- Check `uploads/` directory has write permissions
- Verify file size and type restrictions

#### Barcode Scanner Not Working
- Ensure the barcode input field is focused
- Scanners typically emulate keyboard input

#### Session Expires
- Increase session timeout in `php.ini`
- Check browser cookie settings

### Diagnostic Tool

Access the database diagnostic tool at:
```
http://localhost/SFIMS/core/test_db.php
```

This tool verifies:
- Database connection
- Admin user existence
- Password hash verification

---

## 12. Contributing

### Pull Request Process

1. **Fork** the repository
2. Create a **feature branch**: `git checkout -b feature/your-feature`
3. **Commit** changes with descriptive messages
4. **Push** to your fork
5. Submit a **Pull Request** for review

### Commit Message Format

```
type(scope): description

- Add new feature
- Fix bug
- Update documentation
```

Types: `feat`, `fix`, `docs`, `style`, `refactor`, `test`, `chore`

### Testing Requirements

- Test all changes in local XAMPP environment
- Verify database operations work correctly
- Check responsive design on mobile devices

---

## 13. Security Considerations

- **Password Hashing**: All passwords hashed with `password_hash()`
- **SQL Injection Protection**: PDO prepared statements throughout
- **XSS Prevention**: Output escaping with `h()` function
- **Session Management**: Secure session handling with status checks
- **File Upload Validation**: Type and size restrictions enforced

---

## 14. License

This project is proprietary software developed for **Pamantasan ng Lungsod ng Muntinlupa**. All rights reserved.

---

## 15. Support

For support or questions:
- Refer to the [User Manual](docs/user_manual.md)
- Review the [Setup Guide](docs/setup_guide.md)
- Contact the development team

---

<div align="center">

**Built with ❤️ for Pamantasan ng Lungsod ng Muntinlupa**

*SFIMS v2.1 - Supply and Facilities Inventory Management System*

</div>
