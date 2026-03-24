<?php
require 'config.php';
try {
    // 1. Create markets table
    $pdo->exec("CREATE TABLE IF NOT EXISTS markets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        lat DECIMAL(10, 8) NOT NULL,
        lng DECIMAL(11, 8) NOT NULL,
        radius_km DECIMAL(5, 2) NOT NULL DEFAULT 5.00,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // 2. Alter users table
    $userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('market_id', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD market_id INT NULL DEFAULT NULL AFTER alt_contact");
    }
    if (!in_array('lat', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD lat DECIMAL(10, 8) NULL DEFAULT NULL AFTER market_id");
    }
    if (!in_array('lng', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD lng DECIMAL(11, 8) NULL DEFAULT NULL AFTER lat");
    }
    if (!in_array('current_orders', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD current_orders INT NOT NULL DEFAULT 0 AFTER dummy_otp");
    }
    if (!in_array('is_online', $userCols)) {
        $pdo->exec("ALTER TABLE users ADD is_online TINYINT(1) NOT NULL DEFAULT 1 AFTER current_orders");
    }
    
    // 3. Alter orders table
    $orderCols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('market_id', $orderCols)) {
        $pdo->exec("ALTER TABLE orders ADD market_id INT NULL DEFAULT NULL AFTER user_id");
    }
    
    // Update ENUM for orders.status to include 'assigned'
    // First, let's get existing statuses or just override: pending, assigned, picked_up, in_process, out_for_delivery, delivered, cancelled
    $pdo->exec("ALTER TABLE orders MODIFY status ENUM('pending', 'assigned', 'picked_up', 'in_process', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending'");

    echo "DB Schema Updated successfully for Markets & Auto Assign.\n";
} catch(Exception $e) {
    echo "Error updating DB: " . $e->getMessage() . "\n";
}
?>
