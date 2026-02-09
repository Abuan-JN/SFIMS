# SFIMS Setup Guide (XAMPP)

Follow these steps to set up the **Supply and Facilities Inventory Management System (SFIMS)** on your local environment.

## 1. Prerequisites
- **XAMPP** installed (Apache, PHP, MySQL/MariaDB).
- Windows 11 environment.

## 2. File Placement
1.  Copy the `sfims` folder into your XAMPP's `htdocs` directory:
    - Path: `C:\xampp\htdocs\sfims`

## 3. Database Setup
1.  Open your browser and go to `http://localhost/phpmyadmin`.
2.  Create a new database named `sfims`.
3.  Click on the `sfims` database, go to the **Import** tab.
4.  Choose the `database.sql` file located in `C:\xampp\htdocs\sfims\database.sql`.
5.  Click **Go** to run the script.

## 4. Configuration
1.  Open `C:\xampp\htdocs\sfims\config\database.php`.
2.  Ensure the credentials match your MySQL setup (Default XAMPP: root / [no password]).
    ```php
    define('DB_HOST', 'localhost');
    define('DB_NAME', 'sfims');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    ```

## 5. Accessing the System
1.  Start **Apache** and **MySQL** from the XAMPP Control Panel.
2.  Open your browser and navigate to: `http://localhost/sfims/`
3.  Login with the default admin account:
    - **Username**: `admin`
    - **Password**: `password`

## 6. Troubleshooting
- If you get a "Connection failed" error, check your credentials in `config/database.php`.
- Ensure the `uploads/` directory has write permissions if file uploads fail.
