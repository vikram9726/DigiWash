<?php
require_once 'config.php';
$rows = $pdo->query("SELECT id, phone, name, role, dummy_otp FROM users")->fetchAll();
foreach ($rows as $r) {
    echo "ID:{$r['id']} | Phone:{$r['phone']} | Name:{$r['name']} | Role:{$r['role']} | DummyOTP:{$r['dummy_otp']}\n";
}
?>
