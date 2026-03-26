# SFIMS - Supply and Facilities Inventory Management System

<div align="center">

![SFIMS Logo](assets/img/logoplmun.png)

**A comprehensive, web-based, and mobile-responsive inventory management system for Pamantasan ng Lungsod ng Muntinlupa (PLMun).**

[![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)](https://www.php.net/releases/7.4.php)
[![Bootstrap Version](https://img.shields.io/badge/Bootstrap-5.3-purple)](https://getbootstrap.com/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-orange)](https://www.mysql.com/)
[![License](https://img.shields.io/badge/License-Proprietary-green)](LICENSE)

</div>

---

## 1. Project Overview

SFIMS (Supply and Facilities Inventory Management System) is an advanced, comprehensive web application meticulously designed to digitize and streamline the lifecycle of university assets. It efficiently handles the receiving, tracking, relocation, and distribution of both **expendable (consumables)** and **non-expendable (fixed assets)** items for educational institutions.

Built with scalability and security in mind, the system leverages barcode technology for high-speed tracking, integrates detailed reporting metrics, and implements strict role-based access controls with an admin-approved registration workflow.

### Key Features & Capabilities

- **📦 Complete Inventory Management**: Full CRUD operations for items, seamlessly separating logic between bulk consumables (tracked by quantity) and fixed assets (tracked by unique instances, serial numbers, and barcodes).
- **🏷️ Smart Barcode System**: Automated barcode generation for every fixed asset. Features include literal barcode image rendering in the browser (via TEC-IT API), bulk print layouts (`barcode_print.php`), and a dedicated scanner-ready lookup tool.
- **🔄 Advanced Workflow Automation**:
  - Auto-calculation of quantities during asset disbursement.
  - Granular tracking of asset movements (Room-to-Room, Department-to-Department).
  - Condemnation workflows with distinct classifications (Servicing vs. Trash).
- **📥 Bulk Data Handling (CSV)**: Unified import interfaces for Master Data (Buildings, Rooms, Departments, Sub-Categories), Item Catalogs, and Initial Stock Intake—complete with dynamic template generation and location mapping.
- **📊 Institutional Reporting**: Unified reporting dashboard filtering data by Current Inventory, Received Stock, Issued Stock, and Fixed Asset Registers. Includes dual-format exporting (Server-side CSV, Client-side jsPDF).
- **🎨 Modern UI/UX**: Fully responsive, mobile-first design utilizing Bootstrap 5.3, complete with a meticulously refined Dark Mode system (`data-bs-theme`) and dynamic sidebar navigation.
- **🛡️ Hardened Security**: System-wide CSRF protection, brute-force defense (login throttling), secure session persistence, XSS prevention, and strict validation of all POST endpoints.

---

## 2. Technology Stack

### Backend Architecture

- **PHP 7.4/8.0+**: Server-side processing utilizing a clean, MVC-inspired modular architecture without heavy frameworks.
- **PDO (PHP Data Objects)**: Optimized database layer utilizing native prepared statements and persistent connections (`PDO::ATTR_PERSISTENT`) for maximum query execution speed.

### Frontend Technologies

- **HTML5 & CSS3**: Semantic markup with comprehensive custom overrides for high-contrast dark mode accessibility.
- **Bootstrap 5.3**: Core UI framework for responsive grids, components, and native dark mode (`data-bs-theme`).
- **JavaScript (ES6+)**: Handles dynamic DOM updates, AJAX requests (e.g., background notification marking), form validations, and barcode scanner event delegation.
- **jsPDF & jsPDF-AutoTable**: Client-side library used to generate formal, institutional-grade PDF documents directly in the browser.

### Data Storage

- **MySQL 8.0+ / MariaDB**: Relational database engine (InnoDB) configured with `utf8mb4` encoding to ensure data integrity across all character sets.

---

## 3. Comprehensive System Architecture

The software is organized into highly specialized modules to enforce separation of concerns:

```text
SFIMS/
├── index.php                 # Application entry point / Router
├── dashboard.php             # Analytical dashboard (Stats, Low Stock, Alerts)
│
├── config/                   # Core System configuration
│   ├── app.php               # Global settings, CSRF utilities, Session handling
│   └── database.php          # Singleton PDO connection wrapper
│
├── auth/                     # Authentication & Identity Management
│   ├── login.php             # Secure login with throttling
│   ├── register.php          # Pending registration workflow
│   ├── logout.php            # Session termination
│   └── profile.php           # User account and password updates
│
├── admin/                    # System Administration & Master Data
│   ├── users.php             # Role and account status management
│   ├── audit_logs.php        # Immutable system activity tracking
│   ├── import_master.php     # Unified CSV importer for taxonomies
│   ├── buildings.php, rooms.php, departments.php  # Location taxonomies
│   └── categories.php, sub_categories.php         # Item classifications
│
├── staff/                    # Operational Workflows (Core Business Logic)
│   ├── items_add.php         # Item creation (Includes Catalog CSV Import UI)
│   ├── items_edit.php        # Item metadata modifications
│   ├── import_stock.php      # Bulk Stock Intake with 'Storage Location' parsing
│   ├── receive.php           # Manual PO and stock receiving form
│   ├── disburse.php          # Asset issuance (Auto-qty for Fixed Assets)
│   ├── move.php              # Re-assign assets between rooms/departments
│   ├── condemn.php           # Mark assets for servicing or disposal
│   ├── condemn_print.php, disburse_print.php      # Institutional Forms (With PLMun Logo)
│   ├── dept_assets.php, room_assets.php           # Location-centric inventory views
│   ├── condemned_assets.php  # Disposal registry
│   ├── instance_edit.php     # Micro-management of specific asset serials
│   └── get_instances.php     # AJAX API for fetching available stock
│
├── inventory/                # Item Catalog & Tracking Lookups
│   ├── items.php             # Main Catalog with multi-parameter filtering
│   ├── item_details.php      # Deep dive view, literal barcode generation via TEC-IT
│   ├── transactions.php      # Granular movement ledger
│   ├── barcode_lookup.php    # Rapid scanner-friendly search interface
│   └── barcode_print.php     # Bulk label generation script
│
├── reports/                  # Analytics & Export Module
│   └── reports.php           # Centralized query builder, CSV/PDF generator
│
├── partials/                 # Reusable View Components
│   ├── header.php            # Global navigation, dark mode init, notifications
│   └── footer.php            # Global scripts, AJAX markers
│
├── ajax/                     # Asynchronous Endpoints
│   └── mark_notif_read.php   # Background notification status updater
│
├── docs/                     # Technical Documentation
│   ├── database.sql          # Base schema and initial seeding
│   ├── user_manual.md        # User operation guide
│   └── setup_guide.md        # Deployment guide
│
└── assets/, uploads/         # Static assets, user-uploaded receipts/POs
```

---

## 4. Workflows & State Machines

### 4.1 Asset Lifecycle (Fixed Assets)

1. **Creation**: Item defined in Catalog (`items_add.php` or CSV).
2. **Receiving**: Stock received (`receive.php` or `import_stock.php`). System generates an `item_instance` for every `qty=1` with a unique `barcode_id`. Status: `in-stock`.
3. **Issuance**: Item disbursed (`disburse.php`). Assigned to Department/Room. Status becomes `issued`.
4. **Relocation**: Asset moved (`move.php`). Updates `room_id`. Status remains `issued`.
5. **Condemnation**: Asset breaks (`condemn.php`). Moved to `under repair`, `disposed`, or `lost`.

### 4.2 User Lifecycle

1. User registers via `/auth/register.php`. Account is marked `pending`.
2. Administrator reviews the queue in `/admin/users.php`.
3. Admin approves user. Account status becomes `active`.
4. User logs in. Role (`Admin` or `Staff`) dictates UI visibility in `partials/header.php`.

---

## 5. Security Protocols

The SFIMS platform deploys multiple layers of security to protect university data:

1. **Anti-CSRF Engine**: Every `POST` form utilizes `csrf_field()` which embeds an encrypted token. Operations without valid tokens are immediately rejected.
2. **Strict Parameter Binding**: Prevents SQL Injection. All PDO statements use `execute([$params])`. Emulated prepares are disabled in `config/database.php`.
3. **Session Hardening**: Sessions enforce `HttpOnly`, preventing JavaScript access to session cookies, mitigating XSS session hijacking.
4. **Action Refactoring**: All destructive actions (e.g., Deleting items, altering user states) are strictly mapped to `POST` methods—they can no longer be triggered via URL `GET` requests.
5. **Brute Force Defense**: `/auth/login.php` actively tracks failed attempts by IP and applies timed application lockouts.

---

## 6. Installation & Deployment Guide

### Prerequisites

- PHP 7.4 or newer (8.1+ recommended for optimal performance)
- MySQL 8.0+ or MariaDB 10.4+
- Web Server (Apache/Nginx)
- PHP Extensions: `pdo_mysql`, `mbstring`, `curl`

### Setup Instructions

1. **Deploy Files**: Clone the repository into your web server's document root (e.g., `htdocs/SFIMS`).
2. **Database Initialization**:
   - Create a MySQL database named `sfims`.
   - Import the schema using `/docs/database.sql`.
3. **Configuration**:
   - Rename or edit `config/database.php` and set your `DB_HOST`, `DB_USER`, and `DB_PASS`.
   - Edit `config/app.php` to define your absolute `BASE_URL` (e.g., `http://localhost/SFIMS/`).
4. **Folder Permissions**: Ensure the `/uploads/` directory has write permissions (`chmod 775`).
5. **Initial Access**:
   - Login: `admin`
   - Password: `password`
   - *Requirement: Change this password immediately via the Profile page.*

---

## 7. Advanced Usage / API Integration

### Client-Side PDF Generation

SFIMS utilizes `jsPDF` for generating institutional headers securely on the client side, significantly reducing server overhead. When modifications are needed to the PLMun header formats, modify the `reports.php` JavaScript block handling `doc.text()` and `doc.autoTable()`.

### AJAX Connectivity

For external integrations or future mobile app expansion, the system utilizes lightweight AJAX endpoints returning JSON structures.

- **Get Available Stock**: `GET /staff/get_instances.php?item_id=X&status=in-stock`
- **Barcode Resolution**: `GET /inventory/barcode_lookup.php?barcode=XXXXXXXX`

---

<div align="center">

**Built with ❤️ for Pamantasan ng Lungsod ng Muntinlupa**

*SFIMS - Empowering Institutional Integrity Through Technology*

</div>
