<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE payments MODIFY COLUMN payment_mode ENUM('COD', 'ONLINE', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12') DEFAULT 'COD';");
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
