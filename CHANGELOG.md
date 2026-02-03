# Changelog

All notable changes to the SFIMS project will be documented in this file.

## [Unreleased] - 2026-02-03

### Added

- **Project Structure**: Created core directories (`css`, `js`, `includes`, `uploads`) and configuration.
- **Database**:
  - Implemented initial schema (`database.sql`) for Users, Roles, Departments, Inventory Items, and Barcodes.
  - Added reference tables for Main Categories and Subcategories (`update_schema.sql`).
  - Secure database connection script using PDO (`includes/db.php`).
- **Authentication**:
  - Registration page with Role selection (Staff/Head) and "Inactive" default status.
  - Login page with account activation check.
  - Secure Logout functionality.
  - Role-based session management.
- **Admin Dashboard**:
  - User Management: List users, Activat/Deactivate accounts, Delete users.
  - Department Management: CRUD operations for university departments.
  - Category Configuration: Manage Main Categories and dynamic Subcategories (AJAX support).
- **Inventory Management**:
  - **Item Listing**: View inventory with filtering by Search, Category, and Low Stock status.
  - **Item CRUD**: Add and Edit items with dynamic category dropdowns.
  - **Barcode integration**:
    - `item_barcodes.php`: Manage unit-level barcodes for Non-Expendable items.
    - `print_label.php`: Printable view for item labels using Code 128 (via JsBarcode).
- **UI/UX**:
  - Integrated Bootstrap 5 for responsive layout.
  - Created reusable `header.php` and `footer.php` components.
  - Dashboard overview with basic stats widgets (Total Items, Low Stock).

### Changed

- Refactored `index.php` to serve as the main authenticated dashboard.
- Updated `items.php` to display human-readable category names instead of codes.

### Pending

- Transaction workflows (Receive/Distribute).
- Reporting module with PDF export.
