<?php
$host = '127.0.0.1';
$db   = 'sfims_db';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Attempt to connect without dbname to see if we can create it (optional, mainly for initial setup checks)
    try {
        $dsn_no_db = "mysql:host=$host;charset=$charset";
        $pdo_temp = new PDO($dsn_no_db, $user, $pass, $options);
        // We won't auto-create here to avoid complexity, just let the error show if DB missing
        // throw new \PDOException($e->getMessage(), (int)$e->getCode());
    } catch (\PDOException $e2) {
       // Just swallow
    }
    
    die("Database Connection Failed: " . $e->getMessage());
}
?>
