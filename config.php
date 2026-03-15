<?php
// Database Configuration
$host = '127.0.0.1';
$db   = 'digiwash';
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
    // If the database doesn't exist yet, we can catch it or show a friendly error
    die("Database Connection Failed. Please ensure you have imported schema.sql into MySQL. Error: " . $e->getMessage());
}

// Start Session globally if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
