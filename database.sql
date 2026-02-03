-- Create Database
CREATE DATABASE IF NOT EXISTS sfims_db;
USE sfims_db;
-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    role_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);
-- Insert Roles
INSERT INTO roles (name)
VALUES ('Admin'),
    ('Inventory Staff'),
    ('Department Head') ON DUPLICATE KEY
UPDATE name = name;
-- Departments Table
CREATE TABLE IF NOT EXISTS departments (
    department_id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT
);
-- Users Table
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (role_id) REFERENCES roles(role_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);
-- Items Table
CREATE TABLE IF NOT EXISTS items (
    item_id INT AUTO_INCREMENT PRIMARY KEY,
    item_name VARCHAR(100) NOT NULL,
    category_type ENUM('Expendable', 'Non-Expendable') NOT NULL,
    main_category_code VARCHAR(10) NOT NULL,
    subcategory_code VARCHAR(10) NOT NULL,
    description TEXT,
    quantity_total INT DEFAULT 0,
    location VARCHAR(100),
    per_item_threshold INT DEFAULT 5,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
-- Barcodes Table
CREATE TABLE IF NOT EXISTS barcodes (
    barcode_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    barcode_value VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('Available', 'In Use', 'Damaged', 'Lost') DEFAULT 'Available',
    current_department_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (current_department_id) REFERENCES departments(department_id)
);
-- Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    user_id INT NOT NULL,
    department_id INT DEFAULT NULL,
    type ENUM('Received', 'Distributed', 'Adjustment') NOT NULL,
    quantity INT NOT NULL,
    remarks TEXT,
    date_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(item_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (department_id) REFERENCES departments(department_id)
);