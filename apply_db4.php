<?php
require 'config.php';
try {
    // Width options for marketplace products (per-meter pricing)
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_product_widths (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        label VARCHAR(100) NOT NULL,
        price_per_meter DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (product_id) REFERENCES marketplace_products(id) ON DELETE CASCADE
    );
    ");

    // Staff representative requests from users
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS staff_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT,
        status ENUM('pending','seen','resolved') DEFAULT 'pending',
        admin_note TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    );
    ");

    // Add length_meters to order items (for per-meter products)
    try { $pdo->exec("ALTER TABLE marketplace_order_items ADD COLUMN length_meters DECIMAL(6,2) DEFAULT NULL;"); } catch(Exception $e) { echo "length_meters already exists or error: ".$e->getMessage()."\n"; }
    // Add width_label to order items
    try { $pdo->exec("ALTER TABLE marketplace_order_items ADD COLUMN width_label VARCHAR(100) DEFAULT NULL;"); } catch(Exception $e) { echo "width_label already exists or error: ".$e->getMessage()."\n"; }

    echo "SUCCESS: DB schema updated.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
