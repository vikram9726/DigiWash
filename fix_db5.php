<?php
require 'config.php';

$errors = [];
$successes = [];

function safeExec($pdo, $sql, $label, &$successes, &$errors) {
    try {
        $pdo->exec($sql);
        $successes[] = $label;
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false ||
            strpos($e->getMessage(), "already exists") !== false) {
            $successes[] = "$label (already exists, skipped)";
        } else {
            $errors[] = "$label — ERROR: " . $e->getMessage();
        }
    }
}

// ── 1. coupon_usages table (missing from original schema) ──────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS coupon_usages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    coupon_id INT NOT NULL,
    user_id INT NOT NULL,
    order_id INT DEFAULT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (coupon_id),
    INDEX (user_id)
)", "Create coupon_usages table", $successes, $errors);

// ── 2. coupons table extended columns ─────────────────────────
$couponCols = $pdo->query("SHOW COLUMNS FROM coupons")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('usage_limit', $couponCols))
    safeExec($pdo, "ALTER TABLE coupons ADD COLUMN usage_limit INT DEFAULT NULL AFTER is_active", "coupons.usage_limit", $successes, $errors);
if (!in_array('per_user_limit', $couponCols))
    safeExec($pdo, "ALTER TABLE coupons ADD COLUMN per_user_limit INT DEFAULT 1 AFTER usage_limit", "coupons.per_user_limit", $successes, $errors);
if (!in_array('min_order_amount', $couponCols))
    safeExec($pdo, "ALTER TABLE coupons ADD COLUMN min_order_amount DECIMAL(10,2) DEFAULT 0 AFTER per_user_limit", "coupons.min_order_amount", $successes, $errors);
if (!in_array('expires_at', $couponCols))
    safeExec($pdo, "ALTER TABLE coupons ADD COLUMN expires_at DATETIME DEFAULT NULL AFTER min_order_amount", "coupons.expires_at", $successes, $errors);

// ── 3. orders table: missing columns ──────────────────────────
$orderCols = $pdo->query("SHOW COLUMNS FROM orders")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('invoice_id', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN invoice_id INT DEFAULT NULL AFTER delivery_id", "orders.invoice_id", $successes, $errors);
if (!in_array('picked_up_at', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN picked_up_at TIMESTAMP NULL DEFAULT NULL", "orders.picked_up_at", $successes, $errors);
if (!in_array('delivered_at', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN delivered_at TIMESTAMP NULL DEFAULT NULL", "orders.delivered_at", $successes, $errors);
if (!in_array('lat', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN lat DECIMAL(10,8) NULL DEFAULT NULL", "orders.lat", $successes, $errors);
if (!in_array('lng', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN lng DECIMAL(11,8) NULL DEFAULT NULL", "orders.lng", $successes, $errors);
if (!in_array('pickup_address', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN pickup_address TEXT NULL DEFAULT NULL", "orders.pickup_address", $successes, $errors);
if (!in_array('bypass_photo_url', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN bypass_photo_url VARCHAR(255) NULL DEFAULT NULL", "orders.bypass_photo_url", $successes, $errors);
if (!in_array('delivery_otp', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN delivery_otp VARCHAR(6) NULL DEFAULT NULL", "orders.delivery_otp", $successes, $errors);
if (!in_array('cancellation_reason', $orderCols))
    safeExec($pdo, "ALTER TABLE orders ADD COLUMN cancellation_reason TEXT NULL DEFAULT NULL", "orders.cancellation_reason", $successes, $errors);

// ── 4. users table: missing columns ───────────────────────────
$userCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('auto_order_frequency', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN auto_order_frequency ENUM('NONE','MONDAYS') DEFAULT 'NONE' AFTER pay_later_status", "users.auto_order_frequency", $successes, $errors);
if (!in_array('auto_order_next_date', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN auto_order_next_date DATE NULL DEFAULT NULL AFTER auto_order_frequency", "users.auto_order_next_date", $successes, $errors);
if (!in_array('pay_later_plan', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN pay_later_plan ENUM('NONE','PAY_LATER_4','PAY_LATER_8','PAY_LATER_12') DEFAULT 'NONE'", "users.pay_later_plan", $successes, $errors);
if (!in_array('pay_later_status', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN pay_later_status ENUM('locked','pending_approval','approved','declined') DEFAULT 'locked'", "users.pay_later_status", $successes, $errors);
if (!in_array('qr_code_hash', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN qr_code_hash VARCHAR(128) NULL DEFAULT NULL", "users.qr_code_hash", $successes, $errors);
if (!in_array('fcm_token', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN fcm_token TEXT NULL DEFAULT NULL", "users.fcm_token", $successes, $errors);
if (!in_array('firebase_uid', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN firebase_uid VARCHAR(128) UNIQUE DEFAULT NULL", "users.firebase_uid", $successes, $errors);
if (!in_array('alt_contact', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN alt_contact VARCHAR(15) NULL DEFAULT NULL", "users.alt_contact", $successes, $errors);
if (!in_array('email', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN email VARCHAR(100) NULL DEFAULT NULL", "users.email", $successes, $errors);
if (!in_array('market_id', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN market_id INT NULL DEFAULT NULL", "users.market_id", $successes, $errors);
if (!in_array('current_orders', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN current_orders INT NOT NULL DEFAULT 0", "users.current_orders", $successes, $errors);
if (!in_array('is_online', $userCols))
    safeExec($pdo, "ALTER TABLE users ADD COLUMN is_online TINYINT(1) NOT NULL DEFAULT 1", "users.is_online", $successes, $errors);

// ── 5. payments table: invoice_id link ────────────────────────
$paymentCols = $pdo->query("SHOW COLUMNS FROM payments")->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('invoice_id', $paymentCols))
    safeExec($pdo, "ALTER TABLE payments ADD COLUMN invoice_id INT DEFAULT NULL", "payments.invoice_id", $successes, $errors);

// ── 6. order_items table ───────────────────────────────────────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT DEFAULT NULL,
    product_price_id INT DEFAULT NULL,
    product_name VARCHAR(255) NOT NULL,
    size_label VARCHAR(100) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(10,2) NOT NULL,
    INDEX (order_id)
)", "Create order_items table", $successes, $errors);

// ── 7. products & product_prices tables ───────────────────────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    image_url VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Create products table", $successes, $errors);

safeExec($pdo, "CREATE TABLE IF NOT EXISTS product_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size_label VARCHAR(100) NOT NULL,
    unit VARCHAR(50) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (product_id)
)", "Create product_prices table", $successes, $errors);

// ── 8. returns table ───────────────────────────────────────────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    reason TEXT,
    admin_status ENUM('pending','approved','declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Create returns table", $successes, $errors);

// ── 9. invoices table ──────────────────────────────────────────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(50) NOT NULL,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    description TEXT,
    status ENUM('unpaid','paid') DEFAULT 'unpaid',
    rzp_order_id VARCHAR(100) DEFAULT NULL,
    rzp_payment_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)", "Create invoices table", $successes, $errors);

// ── 10. invoice_orders table ───────────────────────────────────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS invoice_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    order_id INT NOT NULL
)", "Create invoice_orders table", $successes, $errors);

// ── 11. notifications table ────────────────────────────────────
safeExec($pdo, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id)
)", "Create notifications table", $successes, $errors);

echo "<style>body{font-family:sans-serif;max-width:800px;margin:30px auto;padding:20px;}
.ok{color:#16a34a;margin:4px 0;} .err{color:#dc2626;margin:4px 0;}
h1{color:#1e293b;} h2{color:#475569;font-size:1rem;margin-top:20px;}
</style>";
echo "<h1>🔧 DigiWash DB Patch v5 — Schema Fix</h1>";
echo "<h2>✅ Successes (" . count($successes) . ")</h2>";
foreach ($successes as $s) echo "<div class='ok'>✓ $s</div>";
if ($errors) {
    echo "<h2>❌ Errors (" . count($errors) . ")</h2>";
    foreach ($errors as $e) echo "<div class='err'>✗ $e</div>";
} else {
    echo "<br><div style='background:#f0fdf4;border:1px solid #86efac;padding:1rem;border-radius:8px;color:#166534;font-weight:700;'>
        ✅ All schema patches applied successfully! Your dashboard should now display data correctly.
        <br><br><a href='user/dashboard.php' style='color:#2563eb;'>→ Go to User Dashboard</a>
    </div>";
}
?>
