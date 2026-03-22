<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE payments ADD COLUMN rzp_order_id VARCHAR(100) DEFAULT NULL AFTER amount;");
    $pdo->exec("ALTER TABLE payments ADD COLUMN rzp_payment_id VARCHAR(100) DEFAULT NULL AFTER rzp_order_id;");
    echo "SUCCESS";
} catch (Exception $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "SUCCESS";
    } else {
        echo "ERROR: " . $e->getMessage();
    }
}
