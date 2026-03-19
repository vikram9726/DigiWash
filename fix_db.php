<?php
require_once 'config.php';

$fixes = [];

// 1. Add updated_at to payments if missing
try {
    $pdo->query("ALTER TABLE payments ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    $fixes[] = "✓ Added updated_at to payments";
} catch(Exception $e) { $fixes[] = "• payments.updated_at already exists"; }

// 2. Create products table
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        description TEXT,
        image_url VARCHAR(255),
        is_active BOOLEAN DEFAULT TRUE,
        sort_order INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    $fixes[] = "✓ Created products table";
} catch(Exception $e) { $fixes[] = "• products table: " . $e->getMessage(); }

// 3. Create product_prices table (variable sizing)
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS product_prices (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        size_label VARCHAR(50) NOT NULL COMMENT 'e.g. Small, Medium, Large, Per Kg',
        price DECIMAL(10,2) NOT NULL,
        unit VARCHAR(30) DEFAULT 'per piece' COMMENT 'per piece, per kg, per set',
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        INDEX(product_id)
    )");
    $fixes[] = "✓ Created product_prices table";
} catch(Exception $e) { $fixes[] = "• product_prices table: " . $e->getMessage(); }

// 4. Create order_items table to link orders to products
try {
    $pdo->query("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NOT NULL,
        product_price_id INT NOT NULL,
        product_name VARCHAR(150) NOT NULL,
        size_label VARCHAR(50) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        quantity INT NOT NULL DEFAULT 1,
        line_total DECIMAL(10,2) NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        INDEX(order_id)
    )");
    $fixes[] = "✓ Created order_items table";
} catch(Exception $e) { $fixes[] = "• order_items: " . $e->getMessage(); }

// 5. Add uploads/products folder
$dir = __DIR__ . '/uploads/products/';
if (!is_dir($dir)) { mkdir($dir, 0755, true); $fixes[] = "✓ Created uploads/products/"; }
else $fixes[] = "• uploads/products/ already exists";

echo "<pre style='font-family:monospace; font-size:14px; padding:20px;'>";
echo "<b>DigiWash DB Migration</b>\n\n";
foreach ($fixes as $f) echo $f . "\n";
echo "\n<b style='color:green'>Done.</b></pre>";
