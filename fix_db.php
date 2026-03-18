<?php
require_once 'config.php';
try {
    // Update admin phone from 9999999999 to 9726232915
    $stmt = $pdo->prepare("UPDATE users SET phone = ? WHERE phone = ? AND role = 'admin'");
    $stmt->execute(['9726232915', '9999999999']);
    echo "Rows updated: " . $stmt->rowCount() . "\n";

    // Verify
    $rows = $pdo->query("SELECT id, phone, name, role, dummy_otp FROM users")->fetchAll();
    foreach ($rows as $r) {
        echo "ID:{$r['id']} | Phone:{$r['phone']} | Name:{$r['name']} | Role:{$r['role']} | OTP:{$r['dummy_otp']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
