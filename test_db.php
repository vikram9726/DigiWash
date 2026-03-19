<?php
require_once 'config.php';
echo "Tables:\n";
foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) echo "  - $t\n";

echo "\nProducts table cols:\n";
foreach($pdo->query("DESCRIBE products")->fetchAll() as $r) echo "  {$r['Field']} {$r['Type']}\n";

echo "\nproduct_prices cols:\n";
foreach($pdo->query("DESCRIBE product_prices")->fetchAll() as $r) echo "  {$r['Field']} {$r['Type']}\n";

echo "\nProducts count: " . $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn() . "\n";
echo "Prices count:  " . $pdo->query("SELECT COUNT(*) FROM product_prices")->fetchColumn() . "\n";
