# SPMO PLMun User Manual

## 1. Introduction

The SPMO PLMun (Supply and Property Management Office) is designed to track expendable and non-expendable items using barcodes for easy management and reporting.

## 2. Getting Started

### 2.1 Login

Access the login page at `index.php`. Enter your username and password to enter the system. Only approved users can log in.

### 2.2 Registration

New users can register via `register.php`. Once registered, an Admin must approve the account before access is granted.

## 3. Inventory Management

### 3.1 Adding Items

Staff and Admins can add new items in the "Inventory" section.

- Admins can edit item details such as name, category, and minimum threshold.
- To permanently remove an item from the system, an Admin can click the "Delete" button inside the `Actions` column. *Warning: Deleting an asset is a destructive action that will cascade and permanently erase all its recorded instances, barcodes, and transaction logs.*
Items are classified as:

- **Consumables**: Common supplies tracked by quantity only.
- **Fixed Assets**: Assets tracked individually with unique barcodes and serial numbers.

### 3.2 Receiving Items

Use the **Receive Items** form to add stock. For non-expendable items, the system will automatically generate unique barcodes for each unit received. You can also upload supporting documents (e.g., Delivery Receipts).

### 3.3 Issuing Items

Use the **Issue Items** form to distribute stock to departments or persons.

- For non-expendables, you must select the specific asset (barcode) being issued.
- The system will track who currently has each asset.

## 4. Barcode Operations

### 4.1 Printing Barcodes

On the "Item Details" page for non-expendable items, you can click "Print Barcodes" to generate a printable layout of labels.

### 4.2 Barcode Lookup

Navigate to "Search Barcode" and scan an asset's barcode to instantly see its current status and assignment.

## 5. Reports and Audit

### 5.1 System Reports

Generate reports for:

- Current Inventory (with Low Stock alerts)
- Received/Issued History within date ranges
- Fixed Assets Asset tracking

All reports can be exported as **CSV** files.

### 5.2 Audit Logs (Admin Only)

Admins can view all system activities, including user logons and critical inventory changes, in the Audit Logs section.
