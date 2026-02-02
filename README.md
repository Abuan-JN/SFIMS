# SFIMS
Supply and Facilities Inventory Management System. Proposes and details the development of a web-based Supply and Facility Inventory Management System (SFIMS) for a university environment, targeting improved tracking, reporting, and supply control.

You are tasked with designing and implementing a web‑based Supply and Facility Inventory Management System (SFIMS) for a university/academic institution. The system digitizes receiving, tracking, and distributing school supplies and facilities, with barcode support and per‑item low‑stock thresholds. 

### 1. Business context and goals

- The university currently manages inventory manually (paper/Excel) which leads to errors and mismatched records, especially for non‑expendable items with serials.   
- Goal: Provide a centralized web system where inventory staff can receive, record, and distribute items, and department heads can view items and reports relevant to their departments.   
- The system must track both expendable and non‑expendable items, support barcode generation/scanning, offer reports, and provide in‑app low‑stock alerts per item.   

### 2. User roles and registration

Implement these roles and registration logic:

- Roles:  
  - Admin  
  - Inventory Staff  
  - Department Head   
- Admin user is created outside of the public registration flow (e.g., seeded or created via a secure admin setup). There must be no self‑registration for Admin.  
- Registration page:  
  - Inputs: Full Name, Username, Password, Role (Inventory Staff or Department Head).  
  - After registration, the new user account is created as **inactive**.  
  - An Admin must explicitly approve/activate the account before the user can log in.  
- Login must prevent inactive users from accessing the system and show a clear “awaiting approval” message.  

### 3. In‑scope functionality

1. Authentication and authorization
   - User login with username and password.  
   - Role‑based access control:  
     - Admin: full access, including user approvals, master data, and all reports.  
     - Inventory Staff: manage items, receiving, distribution, and view inventory/reports.  
     - Department Head: read‑only access to inventory and reports limited to their department’s items.   
   - Secure password hashing and server‑side validation.  

2. Inventory management
   - CRUD for items with at least:  
     - item_name  
     - category (Expendable / Non‑Expendable)  
     - main_category_code (e.g., Appliances, PC Parts, Electrical, Modules, Scantron, School Supplies, see barcode spec)   
     - subcategory_code (depends on main category, see barcode spec)   
     - description  
     - quantity_total  
     - location  
     - per_item_threshold (low‑stock threshold)  
     - barcode_root or pattern as needed  
   - Separate behavior for expendable vs non‑expendable where appropriate (e.g., individual barcodes for each non‑expendable unit vs bulk quantities for expendables).   
   - Inventory list with filters (category, main category, subcategory, location, low‑stock, etc.).   

3. Barcode specification and handling

Implement the barcode format and logic based on the separate barcode documentation.   

- Barcode format:  
  - General structure: `[1st]/[2nd]/[ID]`.   
  - 1st variable – Category (main category):  
    - 0 → Appliances  
    - 1 → PC Parts  
    - 2 → Electrical  
    - 3 → Modules  
    - 4 → Scantron  
    - 5 → School Supplies   
  - 2nd variable – Subcategory (depends on main category), examples:  
    - Appliances: 0 → Air Con, 1 → Fan, 2 → Projector, 3 → TV  
    - PC Parts: 0 → Monitor, 1 → CPU, 2 → LAN Cable, 3 → Keyboard, 4 → Mouse  
    - Electrical: 0 → Light, 1 → Wires, 2 → Outlet, 3 → Extension Cable  
    - Modules: 0 → CS, 1 → IT, 2 → Masscom, 3 → Crim  
    - School Supplies: 0 → Marker, 1 → Bondpaper, 2 → Pen, 3 → Pencil   
  - ID: a 4‑digit item identifier (e.g., 0001).   
- Example barcodes to match:   
  - Air Con: `0/0/0001`  
  - Fan: `0/1/0001`  
  - CPU: `1/1/0001`  
  - Keyboard: `1/3/0001`  
  - Light: `2/0/0001`  
  - Module (IT): `3/1/0001`  
  - Pen: `5/2/0001` (School Supplies → Pen; correct the example in DB if needed).  
- Requirements:  
  - Provide a configuration/mapping for categories and subcategories so adding new subtypes in the future is possible without changing core logic.   
  - Automatically generate the ID portion sequentially per (main_category, subcategory) combination.  
  - Use a standard barcode symbology (e.g., Code 128) compatible with common USB barcode scanners (scanner acts as keyboard input).  
  - Store each barcode (for non‑expendables, each physical unit) in a dedicated Barcode table.   
  - Provide pages or modals to generate and print barcode labels. Label layout can be simple for now (e.g., barcode + human‑readable code).  

4. Receiving items
   - Workflow/form to record incoming items.   
   - On receive:  
     - Update quantity_total for the item.  
     - Log a “Received” transaction with item_id, user_id, quantity, date_time, remarks.   
     - For non‑expendable items:  
       - Generate individual barcodes using the barcode specification.  
       - Insert records into Barcode table for each unit.  
       - Offer an option to print the barcodes.   

5. Distributing items
   - Workflow/form to record distribution of items to departments.   
   - On distribute:  
     - Decrease quantity_total and validate that enough stock exists.  
     - Log a “Distributed” transaction with item_id, user_id, department_id, quantity, date_time, remarks.   
   - For non‑expendables:  
     - Allow assignment of specific barcode(s) to a department so that you can see which department has which unit.   

6. Department modeling and Department Head access
   - Create a Department table and associate Department Head users with a department.   
   - For non‑expendable items, track which department a given barcode/unit is currently assigned to.   
   - Department Head role:  
     - Can log in and view items and transactions that belong to their department only.  
     - Cannot modify items or transactions.  

7. Reporting and visibility
   - Dashboard with:  
     - Current stock levels.  
     - Recent transactions.  
     - Count/list of items below their individual per_item_threshold.   
   - Reports:  
     - Current inventory report, with filters by category, subcategory, location, and department.   
     - Transaction history with filters (date range, item, type, user, department).   
   - Export reports to CSV (and optionally Excel) for record‑keeping.   

8. Notifications (in‑app only, for now)
   - Each item has its own per_item_threshold field.   
   - If quantity_total ≤ per_item_threshold, show in‑app alerts (e.g., highlighted in lists, dashboard widget, or notification panel).   
   - No email/SMS notifications are required in this version.  

9. Importing existing data (Word/Excel/PDF)

The system must support importing existing inventory or item lists from external files.   

- Supported input formats: at least Word (.docx), Excel (.xlsx/.xls), and PDF.  
- Provide an “Import Data” page where Admin/Inventory Staff can:  
  - Upload a file.  
  - Map columns (where feasible) to system fields (e.g., item_name, category, subcategory, quantity, location).  
  - Preview parsed data and confirm before insertion.  
- Implement reasonable constraints:  
  - Excel/Word: assume tabular structures.  
  - PDF: start with simple table‑based PDFs; clearly validate and report errors for unsupported layouts.  
- Ensure that when importing, barcodes are either:  
  - Generated automatically following the barcode rules when missing, or  
  - Validated against the expected format when barcodes are provided.   

### 4. Technology and architecture

- Architecture: 3‑tier (Presentation, Application, Data).   
  - Presentation: HTML, CSS, Bootstrap for responsive UI.   
  - Application: PHP for backend logic, JavaScript for client‑side behavior.   
  - Data: MySQL database.   
- Environment: Should work on common school setups (e.g., Windows, XAMPP; modest hardware like i3/Ryzen 3, 4–8GB RAM).   

### 5. Data model (minimum, to refine)

- User(user_id, full_name, username, password_hash, email, role_id, department_id nullable, is_active flag).   
- Role(role_id, name) with values: Admin, Inventory Staff, Department Head.   
- Department(department_id, name, description).   
- Item(item_id, item_name, category_type, main_category_code, subcategory_code, description, quantity_total, location, per_item_threshold, created_at, updated_at).   
- Barcode(barcode_id, item_id, barcode_value, current_department_id nullable, status).   
- Transaction(transaction_id, item_id, user_id, department_id nullable, type (Received/Distributed/etc.), quantity, date_time, remarks).   

Developer may add linking tables and enums as needed, but the relationships above must be preserved: one user → many transactions, one item → many transactions, one item → many barcodes, one department → many assigned barcodes/items.   

### 6. UX expectations

- Register page: Full Name, Username, Password, Role; after submit, show “awaiting admin approval” message.  
- Login page: error for invalid credentials and message for inactive accounts.  
- Admin pages:  
  - Approve/activate/deactivate users.  
  - Manage departments, items, thresholds, category/subcategory mappings.  
- Inventory staff pages:  
  - Dashboard (key metrics, low stock, recent transactions).   
  - Receive Items.   
  - Distribute Items.   
  - Inventory listing and detail.   
  - Import Data.   
- Department Head pages:  
  - Dashboard showing items and non‑expendable units for their department.  
  - Read‑only inventory and transaction reports filtered to their department.  

### 7. Quality and non‑functional requirements

- Good performance for typical school‑scale data; common operations should feel responsive.   
- Security:  
  - Password hashing, server‑side validation, CSRF protection, and strict role checks.  
- Reliability:  
  - Graceful error handling for imports, barcode conflicts, insufficient stock, invalid scans.  
- Extensibility:  
  - Codebase should allow later addition of request/approval workflows and multi‑campus support without major rewrites.   

### 8. Deliverables

- Database schema and migration scripts.  
- Backend source code (PHP) and routing/controller structure.  
- Frontend templates (Bootstrap) and JavaScript code.  
- Setup instructions for a local environment (e.g., XAMPP).  
- Technical documentation:  
  - Explanation of barcode generation logic and category mappings.   
  - Description of registration and approval flow.  
  - Notes on import formats and limitations.  

***
