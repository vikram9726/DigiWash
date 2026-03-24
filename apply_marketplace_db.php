<?php
require 'config.php';
try {
    $pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        category VARCHAR(100) NOT NULL,
        size VARCHAR(50) NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        image VARCHAR(255),
        stock INT NOT NULL DEFAULT 0,
        status ENUM('active', 'inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    );
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        delivery_id INT DEFAULT NULL,
        total_amount DECIMAL(10, 2) NOT NULL,
        payment_type ENUM('online', 'credit', 'COD') DEFAULT 'online',
        payment_status ENUM('pending', 'paid') DEFAULT 'pending',
        status ENUM('placed', 'assigned', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'placed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id),
        FOREIGN KEY (delivery_id) REFERENCES users(id)
    );
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS marketplace_order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        price DECIMAL(10, 2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES marketplace_orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES marketplace_products(id)
    );
    ");

    $pdo->exec("
    CREATE TABLE IF NOT EXISTS user_wallet (
        user_id INT PRIMARY KEY,
        credit_limit DECIMAL(10, 2) DEFAULT 2000.00,
        used_credit DECIMAL(10, 2) DEFAULT 0.00,
        FOREIGN KEY (user_id) REFERENCES users(id)
    );
    ");

    echo "SUCCESS\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
