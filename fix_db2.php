<?php
require 'config.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Add timestamp columns to orders if not exist
    $cols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('picked_up_at', $cols)) {
        $pdo->exec("ALTER TABLE orders ADD picked_up_at TIMESTAMP NULL DEFAULT NULL AFTER updated_at");
    }
    if (!in_array('delivered_at', $cols)) {
        $pdo->exec("ALTER TABLE orders ADD delivered_at TIMESTAMP NULL DEFAULT NULL AFTER picked_up_at");
    }
    
    echo "DB updated successfully.\n";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
