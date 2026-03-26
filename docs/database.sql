-- SPMO PLMun Database Schema Initialization
-- Version: 2.4 (Full UX - Logic Sync)
-- Description: Standardizes the institutional inventory tracking schema.
-- Highlights: 
--   - Cascading deletions for child entities.
--   - Role-based status defaults.
--   - Institutional tracking via Barcodes & Serial Numbers.
--   - Notification Read/Unread support.
-- Target: MySQL / MariaDB (InnoDB Engine)
-- CREATE DATABASE IF NOT EXISTS sfims;
-- USE sfims;
-- 1. Categories Table (Consumables, Fixed Assets, etc.)
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
-- 1.05 Sub-Categories Table
CREATE TABLE IF NOT EXISTS sub_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- 1.1 Buildings Table
CREATE TABLE IF NOT EXISTS buildings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
-- 1.2 Rooms Table
CREATE TABLE IF NOT EXISTS rooms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    building_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    floor VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- 1.3 Departments Table
CREATE TABLE IF NOT EXISTS departments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
-- 2. Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(255) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('Staff', 'Admin') NOT NULL,
    status ENUM('pending', 'active', 'deactivated') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE = InnoDB;
-- 3. Items Table (Master Data)
CREATE TABLE IF NOT EXISTS items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category_id INT,
    sub_category_id INT,
    uom VARCHAR(50) NOT NULL,
    -- Unit of Measure (pcs, box, etc.)
    threshold_quantity INT DEFAULT 0,
    current_quantity INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE
    SET NULL,
        FOREIGN KEY (sub_category_id) REFERENCES sub_categories(id) ON DELETE
    SET NULL
) ENGINE = InnoDB;
-- 4. Barcodes Table
CREATE TABLE IF NOT EXISTS barcodes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    barcode_value VARCHAR(100) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- 4. Item Instances (For Fixed Assets)
CREATE TABLE IF NOT EXISTS item_instances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    serial_number VARCHAR(100),
    barcode_id INT NOT NULL,
    status ENUM(
        'in-stock',
        'issued',
        'under repair',
        'disposed',
        'lost',
        'condemned-serviced',
        'condemned-trash'
    ) DEFAULT 'in-stock',
    assigned_department_id INT NULL,
    room_id INT NULL,
    assigned_person VARCHAR(255),
    contact_number VARCHAR(20),
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (barcode_id) REFERENCES barcodes(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_department_id) REFERENCES departments(id) ON DELETE
    SET NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE
    SET NULL
) ENGINE = InnoDB;
-- 5. Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    instance_id INT NULL,
    -- NULL for consumables
    type ENUM(
        'RECEIVE',
        'DISBURSE',
        'ADJUSTMENT',
        'CONDEMN',
        'MOVE'
    ) NOT NULL,
    quantity INT NOT NULL,
    date DATE NOT NULL,
    department_id INT NULL,
    room_id INT NULL,
    recipient_name VARCHAR(255),
    contact_number VARCHAR(20),
    source_supplier VARCHAR(255),
    remarks TEXT,
    performed_by INT NOT NULL,
    -- User ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE,
    FOREIGN KEY (instance_id) REFERENCES item_instances(id) ON DELETE
    SET NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE
    SET NULL,
        FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE
    SET NULL,
        FOREIGN KEY (performed_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE = InnoDB;
-- 6. Transaction Attachments
CREATE TABLE IF NOT EXISTS attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id INT NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename VARCHAR(255) NOT NULL,
    file_type VARCHAR(50),
    file_size INT,
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE CASCADE
) ENGINE = InnoDB;
-- 7. Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(100) NOT NULL,
    entity_name VARCHAR(100) NOT NULL,
    entity_id INT,
    description TEXT,
    is_read TINYINT(1) DEFAULT 0,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE
    SET NULL
) ENGINE = InnoDB;
-- Seed Data (Default Admin - Password: password)
-- Hash generated using PHP: password_hash('password', PASSWORD_DEFAULT)
INSERT INTO users (full_name, username, password_hash, role, status)
VALUES (
        'System Admin',
        'admin',
        '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
        'Admin',
        'active'
    );
INSERT INTO categories (name)
VALUES ('Consumables'),
    ('Fixed Assets');
-- Seed Sub-Categories
INSERT INTO sub_categories (category_id, name)
VALUES (2, 'Printer'),
    (2, 'PC Parts'),
    (2, 'Appliances'),
    (2, 'Furniture'),
    (2, 'Laptop'),
    (1, 'Office Supplies'),
    (1, 'School Supplies'),
    (1, 'Medical Equipment'),
    (1, 'Medical Supplies'),
    (1, 'Cleaning Supplies');
-- Seed Buildings
INSERT INTO buildings (name)
VALUES ('Rizal Building'),
    ('Student Center'),
    ('SLRC Building');
-- Seed Rooms
INSERT INTO rooms (building_id, name, floor)
VALUES (1, 'ComLab 1', '2nd Floor'),
    (1, 'ComLab 2', '2nd Floor'),
    (1, 'ComLab 3', '2nd Floor'),
    (1, 'ComLab 4', '2nd Floor'),
    (1, 'ComLab 5', '2nd Floor'),
    (1, 'ComLab 6', '2nd Floor'),
    (1, 'Business Center', '1st Floor'),
    (1, 'Admin Office', '1st Floor'),
    (2, 'Registrar', '1st Floor'),
    (2, 'Treasury Office', '1st Floor'),
    (2, 'NSTP Office', '3rd Floor'),
    (3, 'Library', '1st Floor'),
    (3, 'Science Lab', '2nd Floor'),
    (3, 'Chemistry Lab', '2nd Floor'),
    (3, 'Physics Lab', '2nd Floor'),
    (3, 'Biology Lab', '2nd Floor');
-- Seed Departments
INSERT INTO departments (name)
VALUES ('CITCS Department'),
    ('CoA Department'),
    ('CoAS Department'),
    ('Business Center'),
    ('Admin'),
    ('Registrar'),
    ('Treasury Office');
-- Seed Sample Items
INSERT INTO items (
        name,
        description,
        category_id,
        sub_category_id,
        uom,
        threshold_quantity,
        current_quantity
    )
VALUES (
        'Dell Optiplex 3050',
        'Desktop Computer for office use',
        2,
        5,
        'pcs',
        5,
        0
    ),
    (
        'HP ProBook 450',
        'Laptop for office use',
        2,
        5,
        'pcs',
        5,
        0
    ),
    (
        'Lenovo ThinkPad X1',
        'Laptop for office use',
        2,
        5,
        'pcs',
        5,
        0
    ),
    (
        'Acer Aspire 5',
        'Laptop for office use',
        2,
        5,
        'pcs',
        5,
        0
    ),
    (
        'Asus VivoBook',
        'Laptop for office use',
        2,
        5,
        'pcs',
        5,
        0
    ),
    (
        'Hanabishi Stand Fan',
        'Stand fan for office use',
        2,
        3,
        'pcs',
        5,
        0
    ),
    (
        'Metal Arm Chair',
        'Arm chair for Room use',
        2,
        4,
        'pcs',
        5,
        0
    ),
    (
        'Wooden Desk',
        'Desk for Room use',
        2,
        4,
        'pcs',
        5,
        0
    ),
    (
        'Logitech USB Mouse',
        'Standard wired mouse',
        1,
        1,
        'pcs',
        20,
        0
    ),
    (
        'A4 Bond Paper',
        '80gsm White Paper',
        1,
        6,
        'ream',
        10,
        0
    ),
    (
        'Legal Bond Paper',
        '80gsm White Paper',
        1,
        6,
        'ream',
        10,
        0
    ),
    (
        'Short Bond Paper',
        '80gsm White Paper',
        1,
        6,
        'ream',
        10,
        0
    ),
    (
        'Canon Pixma Printer',
        'Office multifunction printer',
        2,
        1,
        'pcs',
        2,
        0
    ),
    (
        'HDMI Cable 3m',
        'High-speed HDMI cable',
        1,
        1,
        'pcs',
        15,
        0
    ),
    (
        'USB Type-C Cable',
        'High-speed USB Type-C cable',
        1,
        1,
        'pcs',
        15,
        0
    );
-- Database Optimization Script for SFIMS
-- Purpose: Improves performance for Condemned Assets and large Inventory Logs.
-- Usage: Run these commands in your MySQL/MariaDB database management tool (e.g. phpMyAdmin).
-- 1. Index for fast status filtering (Essential for Condemned Assets)
CREATE INDEX idx_item_instances_status ON item_instances (status);
-- 2. Index for fast sorting (Essential for Logs/Reports showing latest first)
CREATE INDEX idx_item_instances_last_updated ON item_instances (last_updated);
-- 3. Composite Index for optimized JOINs (Optional but recommended)
-- Improves performance when joining items and instances
CREATE INDEX idx_item_instances_item_barcode ON item_instances (item_id, barcode_id);
-- 4. Index on Barcodes table for faster lookups
CREATE INDEX idx_barcodes_value ON barcodes (barcode_value);