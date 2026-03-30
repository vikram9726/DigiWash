<?php
require 'config.php';
try {
    // Add Razorpay tracking columns to marketplace_orders
    try { $pdo->exec("ALTER TABLE marketplace_orders ADD COLUMN razorpay_order_id VARCHAR(100) DEFAULT NULL;"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE marketplace_orders ADD COLUMN razorpay_payment_id VARCHAR(100) DEFAULT NULL;"); } catch(Exception $e) {}
    
    // Add delivery assignment to staff_requests
    try { $pdo->exec("ALTER TABLE staff_requests ADD COLUMN delivery_id INT DEFAULT NULL;"); } catch(Exception $e) {}
    try { $pdo->exec("ALTER TABLE staff_requests ADD FOREIGN KEY (delivery_id) REFERENCES users(id) ON DELETE SET NULL;"); } catch(Exception $e) {}

    echo "SUCCESS: DB updated.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
