<?php
require_once 'config.php';

echo "Starting database seed for DigiWash End-to-End Testing...\n";

try {
    // 1. Clear existing data to ensure a clean test
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE returns");
    $pdo->exec("TRUNCATE TABLE payments");
    $pdo->exec("TRUNCATE TABLE orders");
    $pdo->exec("TRUNCATE TABLE users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "Existing tables truncated.\n";

    // 2. Create Admin User
    $stmt = $pdo->prepare("INSERT INTO users (phone, name, role, dummy_otp) VALUES (?, ?, ?, ?)");
    $stmt->execute(['9726232915', 'Super Admin', 'admin', '123456']);
    $adminId = $pdo->lastInsertId();
    echo "Admin created (Phone: 9726232915, OTP: 123456)\n";

    // 3. Create Delivery Partner
    $stmt = $pdo->prepare("INSERT INTO users (phone, name, role, dummy_otp) VALUES (?, ?, ?, ?)");
    $stmt->execute(['8888888888', 'Fast Delivery Guy', 'delivery', '123456']);
    $deliveryId = $pdo->lastInsertId();
    echo "Delivery created (Phone: 8888888888, OTP: 123456)\n";

    // 4. Create Normal Customer 1 (New, no orders)
    $stmt = $pdo->prepare("INSERT INTO users (phone, name, email, shop_address, role, dummy_otp, qr_code_hash) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute(['7777777777', 'Alice Customer', 'alice@test.com', '123 Laundry Lane', 'customer', '123456', 'alice_qr_test_hash']);
    $aliceId = $pdo->lastInsertId();
    echo "Customer 'Alice' created (Phone: 7777777777, OTP: 123456)\n";

    // 5. Create Normal Customer 2 (Heavy user, has 4 unpaid delivered orders to test lock)
    $stmt->execute(['6666666666', 'Bob The Shop Owner', 'bob@store.com', '456 Dirty Cloth St.', 'customer', '123456', 'bob_qr_test_hash']);
    $bobId = $pdo->lastInsertId();
    echo "Customer 'Bob' created (Phone: 6666666666, OTP: 123456)\n";

    // -- Generate Scenarios --

    // Scenario A: Alice has one Pending Pickup (unassigned)
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, total_amount, instructions, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$aliceId, 250.00, 'Please wash gently', 'pending']);
    $aliceOrder1 = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO payments (order_id, user_id, amount, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$aliceOrder1, $aliceId, 250.00, 'remaining']);
    echo "Alice has 1 Pending Order (Unassigned)\n";

    // Scenario B: Alice has one Order Out for Delivery (Assigned to Delivery Partner)
    $stmt = $pdo->prepare("INSERT INTO orders (user_id, delivery_id, total_amount, status, delivery_otp) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$aliceId, $deliveryId, 500.00, 'out_for_delivery', '123456']);
    $aliceOrder2 = $pdo->lastInsertId();
    $stmt = $pdo->prepare("INSERT INTO payments (order_id, user_id, amount, status) VALUES (?, ?, ?, ?)");
    $stmt->execute([$aliceOrder2, $aliceId, 500.00, 'remaining']);
    echo "Alice has 1 Order Out For Delivery (OTP '123456', Assigned to 'Fast Delivery Guy')\n";

    // Scenario C: Bob has 4 Delivered Orders, but hasn't paid (To test the 4-order limit)
    for ($i = 1; $i <= 4; $i++) {
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, delivery_id, total_amount, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bobId, $deliveryId, 100.00, 'delivered']);
        $bobOrderId = $pdo->lastInsertId();
        
        $stmt = $pdo->prepare("INSERT INTO payments (order_id, user_id, amount, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$bobOrderId, $bobId, 100.00, 'remaining']);
    }
    echo "Bob has 4 Delivered but Unpaid orders (Should trigger the payment lock when ordering)\n";

    // Scenario D: Bob made a return request on his 4th order
    $stmt = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, photo_url, admin_status) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$bobOrderId, $bobId, 'Shirt was missing a button when it came back', 'uploads/returns/dummy.jpg', 'pending']);
    echo "Bob requested a return on his last order. Admin should see this.\n";

    echo "\n=== SEEDING SUCCESSFUL ===\n";
    echo "Login Credentials:\n";
    echo "Admin: 9726232915\n";
    echo "Delivery: 8888888888\n";
    echo "Customer (Clean): 7777777777\n";
    echo "Customer (Locked out & Return Request): 6666666666\n";

} catch (Exception $e) {
    echo "Error During Seeding: " . $e->getMessage() . "\n";
}
?>
