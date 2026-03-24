<?php
require_once 'config.php';

echo "<h1>Updating Database Schema (Delivery Navigation)</h1>";

try {
    // 1. Add columns to orders table
    echo "Adding lat, lng, and address to orders table...<br>";
    $pdo->exec("ALTER TABLE orders ADD COLUMN lat DECIMAL(10,8) NULL DEFAULT NULL AFTER user_id");
    $pdo->exec("ALTER TABLE orders ADD COLUMN lng DECIMAL(11,8) NULL DEFAULT NULL AFTER lat");
    $pdo->exec("ALTER TABLE orders ADD COLUMN pickup_address TEXT NULL DEFAULT NULL AFTER lng");
    echo "Added successfully.<br>";

    echo "<h3>All updates completed successfully!</h3>";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<span style='color:orange;'>Columns already exist. Skipping.</span><br>";
        echo "<h3>All updates completed successfully!</h3>";
    } else {
        echo "<h3 style='color:red;'>Database Error: " . $e->getMessage() . "</h3>";
    }
}
?>
