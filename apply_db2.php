<?php
require 'config.php';
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN pay_later_plan ENUM('NONE', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12') DEFAULT 'NONE';");
    $pdo->exec("ALTER TABLE users ADD COLUMN pay_later_status ENUM('locked', 'pending_approval', 'approved', 'declined') DEFAULT 'locked';");
    echo "SUCCESS";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
