<?php
/**
 * Database Configuration and Connection Class
 * 
 * This file defines database credentials and implements a Singleton pattern
 * to manage a single PDO database connection throughout the application lifecycle.
 */

// Connection parameters
define('DB_HOST', 'localhost');
define('DB_NAME', 'sfims');
define('DB_USER', 'root');
define('DB_PASS', '');

class Database {
    // Holds the single instance of this class
    private static $instance = null;
    
    // Holds the PDO connection object
    private $conn;

    /**
     * Private constructor to prevent direct instantiation.
     * Establishes the connection using PDO with error reporting enabled.
     */
    private function __construct() {
        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS,
                [
                    // Setting error mode to exception for better debugging
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    // Default fetch mode as associative array
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    // Ensure UTF-8 character encoding
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
                ]
            );
        } catch (PDOException $e) {
            // Terminate script if database connection fails
            die("Connection failed: " . $e->getMessage());
        }
    }

    /**
     * getInstance()
     * 
     * Static method to retrieve the existing connection object or create a new one.
     * 
     * @return PDO The active database connection
     */
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance->conn;
    }
}
