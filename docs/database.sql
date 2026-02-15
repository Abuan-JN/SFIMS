-- SFIMS Database Schema Initialization
-- Version: 2.1 (Normalized Barcodes)
-- Description: Standardizes the institutional inventory tracking schema.
-- Highlights: 
--   - Cascading deletions for child entities.
--   - Role-based status defaults.
--   - Institutional tracking via Barcodes & Serial Numbers.
-- Target: MySQL / MariaDB (InnoDB Engine)
CREATE DATABASE IF NOT EXISTS sfims;
USE sfims;
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
    role ENUM('Staff') NOT NULL,
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
    -- Derived but cached for performance
    status ENUM('active', 'inactive') DEFAULT 'active',
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
        'Staff',
        'active'
    );
INSERT INTO categories (name)
VALUES ('Consumables'),
    ('Fixed Assets');
-- Seed Sub-Categories
INSERT INTO sub_categories (category_id, name)
VALUES (1, 'Office Supplies'),
    (1, 'Cleaning Supplies'),
    (1, 'Medical Supplies'),
    (2, 'IT Equipment'),
    (2, 'Furniture'),
    (2, 'Machinery');