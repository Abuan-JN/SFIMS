<?php
/**
 * SFIMS Connection & Authentication Debug Tool
 * 
 * This utility script verifies:
 * 1. Database connectivity through the Database singleton.
 * 2. Existence of the 'admin' user.
 * 3. Correctness of password hashing and verification.
 */

// Load database configuration
require_once 'config/database.php';

echo "<h3>SFIMS Debug Tool</h3>";

try {
    $db = Database::getInstance();
    echo "<p style='color:green;'>✅ Database connection successful!</p>";

    $stmt = $db->query("SELECT * FROM users WHERE username = 'admin'");
    $user = $stmt->fetch();

    if ($user) {
        echo "<p>User 'admin' found.</p>";
        $passwordToTest = 'password';
        if (password_verify($passwordToTest, $user['password_hash'])) {
            echo "<p style='color:green;'>✅ Password 'password' verified successfully!</p>";
        } else {
            echo "<p style='color:red;'>❌ Password 'password' failed verification.</p>";
            echo "<p>Current Hash in DB: " . $user['password_hash'] . "</p>";
            echo "<p>New Hash for 'password': " . password_hash('password', PASSWORD_DEFAULT) . "</p>";
        }
    } else {
        echo "<p style='color:red;'>❌ User 'admin' not found in database. Did you import database.sql?</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red;'>❌ Connection failed: " . $e->getMessage() . "</p>";
}
?>