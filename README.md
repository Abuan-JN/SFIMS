Build a **web-based, mobile-responsive Supply and Facilities Inventory Management System (SFIMS)** for Pamantasan ng Lungsod ng Muntinlupa to digitize the receiving, tracking, and distribution of expendable and non‑expendable items using barcodes, with role-based access, reporting, low-stock notifications, file attachments, and an admin-approved registration flow. [getbootstrap](https://getbootstrap.com/docs/3.3/)

***

## 1. Project overview

The system must:  
- Run in modern browsers on desktop and mobile (responsive layout, mobile-first friendly). [w3schools](https://www.w3schools.com/html/html_responsive.asp)
- Use **plain PHP + MySQL** (no full-stack framework required, but structured/modeled code is expected). [phpgurukul](https://phpgurukul.com/inventory-management-system-using-php-and-mysql/)
- Follow a clear structure aligned with planning–analysis–design principles from Software Engineering, while implementation can be iterative. 

***

## 2. Tech stack and environment

- **Frontend**  
  - HTML5, CSS3, JavaScript.  
  - Bootstrap for mobile-first, responsive layout and components. [browserstack](https://www.browserstack.com/guide/bootstrap-mobile-responsive)

- **Backend**  
  - Plain PHP (PHP 7+ or 8+) using OOP and organized structure (simple MVC or modular). [phpzag](https://www.phpzag.com/build-inventory-system-with-ajax-php-mysql/)

- **Database**  
  - MySQL / MariaDB.  
  - Use proper normalization and foreign keys; ERD should follow inventory best practices. [geeksforgeeks](https://www.geeksforgeeks.org/sql/how-to-design-er-diagrams-for-inventory-and-warehouse-management/)

- **Server / runtime**  
  - Development: XAMPP (Apache + PHP + MySQL) on Windows 11, mid-range hardware (Intel Core i3 / Ryzen 3, 4 GB RAM, 250 GB storage).   
  - Deployment target: standard LAMP or equivalent shared hosting.

- **Language**  
  - UI and documentation in **English only**.

***

## 3. User roles and permissions

Implement at least these roles with role-based access control:

- **Admin**  
  - Full access to user management (including approvals).  
  - Configure item categories, locations, stock thresholds.  
  - View all reports and audit logs.  
  - Cannot be self-selected on public registration.

- **Inventory Staff**  
  - Manage items (create/update, not hard-delete).  
  - Process receiving and issuing.  
  - Generate and print barcodes.  
  - Upload and view transaction documents.

- **Viewer / Department Head** (optional but recommended)  
  - Read-only access to inventory lists and reports relevant to them.

***

## 4. Authentication, registration, and authorization

### 4.1 Login and sessions

- Login form for username + password.  
- Use password hashing (e.g., PHP `password_hash` and `password_verify`).  
- Maintain secure sessions, with logout and optional session timeout.  
- After login, redirect users based on role if needed (e.g., Admin → Admin dashboard, Staff → operations dashboard). [codewithawa](https://codewithawa.com/posts/user-account-management,-roles,-permissions,-authentication-php-and-mysql)

### 4.2 Extended user registration with admin approval

Implement a public registration flow plus admin approval:

1. **Registration page (public)**  
   - Accessible without login (e.g., `/register.php`).  
   - Fields:  
     - Full name  
     - Username  
     - Password + Confirm password (validation for minimum length/strength)  
     - Desired role (dropdown, **excluding Admin**, e.g., Inventory Staff, Viewer).  
   - On submit:  
     - Insert a new user row in `users` table with:  
       - Provided full name, username, hashed password, desired role.  
       - `status = 'pending'` (or similar) to prevent login.  
     - Show a confirmation message: “Your account is pending approval by an administrator.” [stackoverflow](https://stackoverflow.com/questions/10628133/php-admin-approval-for-user-registration)

2. **Login behavior for pending / deactivated users**  
   - During login, check both credentials and user `status`.  
   - If `status = 'pending'`: deny login, display message like “Your account is awaiting approval by an administrator.”  
   - If `status = 'deactivated'`: deny login, with message such as “Your account is currently deactivated.” [stackoverflow](https://stackoverflow.com/questions/56205736/take-users-to-specific-pages-based-on-their-roles-using-php-and-mysql)
   - Only `status = 'active'` should be allowed to access the system.

3. **Admin users management page (with pending users)**  
   - In the Admin “Users” tab, display a table of users with filters or sections for:  
     - Active users  
     - Deactivated users  
     - Pending users (new registrations awaiting approval).  
   - For each user record show: full name, username, role, status, date created.  
   - Provide actions:  
     - Activate (set `status = 'active'`)  
     - Deactivate (set `status = 'deactivated'`)  
     - Optional edit (full name, username, role), with appropriate restrictions.  
   - For **pending users**:  
     - Allow Admin to review requested role and info.  
     - Admin decides to **Activate** (approve) or **Deactivate/Reject**. [stackoverflow](https://stackoverflow.com/questions/10628133/php-admin-approval-for-user-registration)

4. **Database and status field**  
   - The `users` table must include a column like `status` (e.g., `ENUM('pending','active','deactivated')`). [codewithawa](https://codewithawa.com/posts/user-account-management,-roles,-permissions,-authentication-php-and-mysql)
   - All authorization checks must validate both role and `status = 'active'`.

***

## 5. Item and inventory management

### 5.1 Item master data

- CRUD for items (create, view, update, deactivate instead of hard delete).  
- Fields:  
  - item_id (PK)  
  - name  
  - description  
  - category (expendable / non‑expendable)  
  - unit_of_measure (pcs, box, set, etc.)  
  - current_quantity (derived from transactions)  
  - location / storage area  
  - threshold_quantity (for low-stock alerts; optional for non‑expendables)  
  - status (active/inactive)

### 5.2 Non‑expendable item instances

- Separate entity/table for **ItemInstance** for non‑expendables:  
  - instance_id (PK)  
  - item_id (FK → Item)  
  - serial_number (nullable)  
  - barcode_value (unique)  
  - status (in-stock, issued, under repair, disposed, lost)  
  - assigned_department_or_person (nullable)  

### 5.3 Receiving items

- Form to record receiving operations:  
  - Item (select/search)  
  - For expendables: quantity received.  
  - For non‑expendables: number of units; auto-generate instance records and barcodes.  
  - Date received  
  - Supplier (optional)  
  - Reference number (e.g., PO/DR)  
  - Remarks  
  - **File upload** for supporting documents (PDF/JPG/PNG) with size/type validation.  
- On submission:  
  - Insert Transaction record(s) of type `RECEIVE`.  
  - Update `current_quantity` for items.  
  - Create ItemInstance rows for non‑expendables with barcodes.

### 5.4 Issuing / distributing items

- Form to issue items to departments/persons:  
  - Item  
  - For expendables: quantity issued, validated against stock.  
  - For non‑expendables: pick specific instances or scan barcode to select.  
  - Date issued  
  - Receiving department/person  
  - Purpose / remarks  
  - Optional file upload (e.g., signed requisition form).  
- On submission:  
  - Insert Transaction record(s) of type `ISSUE`.  
  - Decrease `current_quantity`.  
  - Update ItemInstance status and assignment.

### 5.5 Inventory views

- Items list with search, filter (by category, location, status, low stock), and pagination.  
- Item detail page with:  
  - Item info  
  - Current quantity and threshold  
  - Transaction history (received/issued)  
  - Non‑expendable instances table with barcode/status/assignee.

***

## 6. Barcode management

1. **Generation**  
   - Auto-generate a unique `barcode_value` per non‑expendable ItemInstance; store in DB.  

2. **Printing**  
   - Page to select items/instances and show a printable layout of barcode labels (item name + barcode image).  

3. **Scanning workflows**  
   - Forms with a focused textbox to accept barcode scans (scanner acts as keyboard).  
   - Use barcode scans for:  
     - Quick item/instance lookup.  
     - Fast issuing (scan asset → confirm issuance).  
     - Physical verification (audit) by scanning assets.

***

## 7. Reporting and audit

### 7.1 Reports

- **Current inventory report**  
  - List all items with current_quantity, category, location, and low-stock indicator.  

- **Received items report**  
  - Filter by date range, item, category, supplier.  

- **Issued items report**  
  - Filter by date range, item, category, department/person.  

- **Non‑expendable assets report**  
  - List ItemInstances with status and assigned department/person.  

- All reports should support export to **CSV** at minimum; PDF export is optional but preferred.

### 7.2 Low-stock alerts

- Show items where `current_quantity ≤ threshold_quantity` in a dedicated section or dashboard widget.  
- Use color highlighting (e.g., red rows) for low-stock items.

### 7.3 Audit logging

- Maintain **AuditLog** for critical operations: login/logout, user management, item CRUD, receiving, issuing, file uploads, barcode generation.  
- Fields:  
  - log_id  
  - user_id  
  - action_type  
  - entity_name  
  - entity_id  
  - timestamp  
  - description/details  
- Admin interface to search and filter audit records.

***

## 8. File uploads and storage

- Allow attachments on receiving and issuing transactions.  
- Store files in a secure server directory; reference via TransactionAttachment table:  
  - attachment_id  
  - transaction_id (FK)  
  - original_filename  
  - stored_filename  
  - file_type  
  - file_size  
  - uploaded_at  
- Enforce max size and allowed extensions; deny dangerous types.

***

## 9. Non-functional requirements

- **Responsiveness & mobile support**: all pages should be usable on phones and desktops (Bootstrap grid, responsive navbar, responsive tables where possible). [pluralsight](https://www.pluralsight.com/labs/codeLabs/guided-build-a-responsive-layout-with-bootstrap)
- **Security**:  
  - Use prepared statements/parameterized queries for all DB access. [phpzag](https://www.phpzag.com/build-inventory-system-with-ajax-php-mysql/)
  - Escape output to prevent XSS.  
  - Hash passwords securely.  
- **Performance**:  
  - Paginate large lists; use basic indexing in DB.  
  - AJAX for search/filter improves UX but is optional. [phpzag](https://www.phpzag.com/build-inventory-system-with-ajax-php-mysql/)
- **Data integrity**:  
  - Foreign keys and appropriate constraints (User–Transaction–AuditLog, Item–ItemInstance–Transaction). [geeksforgeeks](https://www.geeksforgeeks.org/sql/how-to-design-er-diagrams-for-inventory-and-warehouse-management/)

***

## 10. Data model (high-level)

Minimum entities (devs should design full ERD):

- **User**: user_id, full_name, username, password_hash, role, status, created_at.  
- **Item**: item_id, name, description, category, unit_of_measure, current_quantity, location, threshold_quantity, status.  
- **ItemInstance**: instance_id, item_id, serial_number, barcode_value, status, assigned_department_or_person.  
- **Transaction**: transaction_id, item_id, instance_id (nullable), transaction_type (RECEIVE/ISSUE/ADJUSTMENT), quantity, date, department_or_person, remarks, performed_by.  
- **AuditLog**: log_id, user_id, action_type, entity_name, entity_id, timestamp, description.  
- **TransactionAttachment**: attachment_id, transaction_id, filenames, type, size, uploaded_at. [youtube](https://www.youtube.com/watch?v=tEhGIYN4vic)

***

## 11. Deliverables

- Full PHP source code (structured, commented where needed).  
- SQL script to create all tables and constraints, plus seed data.  
- ERD diagram (image or .mwb, etc.). [youtube](https://www.youtube.com/watch?v=tEhGIYN4vic)
- Setup guide for XAMPP (import DB, configure DB credentials, run app). [phpgurukul](https://phpgurukul.com/inventory-management-system-using-php-and-mysql/)
- Short English user manual: login, registration and approval flow, managing items, receiving, issuing, barcode operations, reports, attachments, and user management.