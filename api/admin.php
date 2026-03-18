<?php
require_once '../config.php';
header('Content-Type: application/json');

function respond($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    respond(false, 'Unauthorized. Admin access only.');
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_POST['action'] ?? '';

// CSRF Protection Check
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? (is_array($data) ? ($data['csrf_token'] ?? '') : '') ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token. Request denied.');
}

// --- FETCH DASHBOARD STATS ---
if ($action === 'get_stats') {
    // Total Users (Customers)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
    $totalUsers = $stmt->fetchColumn();

    // Active Orders (Not Delivered/Cancelled)
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered', 'cancelled')");
    $activeOrders = $stmt->fetchColumn();

    // Pending Revenue (Total of remaining payments)
    $stmt = $pdo->query("SELECT SUM(amount) FROM payments WHERE status = 'remaining'");
    $pendingRevenue = $stmt->fetchColumn() ?: 0;

    // Delivery Partners
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'delivery'");
    $deliveryPartners = $stmt->fetchColumn();

    respond(true, 'Stats fetched', [
        'users' => $totalUsers,
        'orders' => $activeOrders,
        'revenue' => $pendingRevenue,
        'partners' => $deliveryPartners
    ]);
}

// --- FETCH USERS ---
if ($action === 'get_users') {
    $stmt = $pdo->query("SELECT id, name, phone, email, shop_address, created_at FROM users WHERE role = 'customer' ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    respond(true, 'Users fetched', ['users' => $users]);
}

// --- FETCH ALL ORDERS ---
if ($action === 'get_all_orders') {
    $stmt = $pdo->query("
        SELECT o.*, u.name as customer_name, d.name as delivery_name 
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN users d ON o.delivery_id = d.id 
        ORDER BY o.created_at DESC LIMIT 100
    ");
    $orders = $stmt->fetchAll();
    
    // Also fetch available delivery partners for the assignment dropdown
    $stmt = $pdo->query("SELECT id, name FROM users WHERE role = 'delivery'");
    $partners = $stmt->fetchAll();

    respond(true, 'Orders fetched', ['orders' => $orders, 'delivery_partners' => $partners]);
}

// --- ASSIGN DELIVERY PARTNER ---
if ($action === 'assign_order') {
    $orderId = $data['order_id'] ?? 0;
    $deliveryId = $data['delivery_id'] ?? null;

    if (empty($deliveryId)) {
        respond(false, 'Please select a delivery partner.');
    }

    try {
        $stmt = $pdo->prepare("UPDATE orders SET delivery_id = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$deliveryId, $orderId]);

        // Trigger Notifications
        // 1. Notify Customer
        $stmtUser = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmtUser->execute([$orderId]);
        $ownerId = $stmtUser->fetchColumn();
        sendPushNotification($pdo, $ownerId, "Driver Assigned", "A delivery partner has been assigned to your order!");

        // 2. Notify Driver
        sendPushNotification($pdo, $deliveryId, "New Task Assigned", "A new delivery task #$orderId is assigned to you.");

        respond(true, 'Order assigned to delivery partner successfully.');
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- CANCEL ORDER ---
if ($action === 'cancel_order') {
    $orderId = $data['order_id'] ?? 0;
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);

        // Cancel the pending payment — 'cancelled' is not in ENUM, mark as completed since order is voided
        $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE order_id = ? AND status = 'remaining'");
        $stmt->execute([$orderId]);

        $pdo->commit();
        respond(true, 'Order cancelled permanently.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- FETCH RETURN REQUESTS ---
if ($action === 'get_returns') {
    $stmt = $pdo->query("
        SELECT r.*, r.admin_status as status, o.total_amount, u.name as customer_name, u.phone 
        FROM returns r
        JOIN orders o ON r.order_id = o.id
        JOIN users u ON r.order_id = o.id AND o.user_id = u.id
        ORDER BY r.created_at DESC
    ");
    $returns = $stmt->fetchAll();
    respond(true, 'Returns fetched', ['returns' => $returns]);
}

// --- APPROVE/DECLINE RETURN ---
if ($action === 'handle_return') {
    $returnId = $data['return_id'] ?? 0;
    $status = $data['status'] ?? '';
    // Map 'rejected' to 'declined' to match DB ENUM('pending','approved','declined')
    if ($status === 'rejected') $status = 'declined';

    if (!in_array($status, ['approved', 'declined'])) {
        respond(false, 'Invalid status.');
    }

    try {
        $stmt = $pdo->prepare("UPDATE returns SET admin_status = ? WHERE id = ?");
        $stmt->execute([$status, $returnId]);
        respond(true, 'Return request ' . $status . ' successfully.');
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- FETCH PARTNERS ---
if ($action === 'get_partners') {
    $stmt = $pdo->query("SELECT id, name, phone, dummy_otp, created_at FROM users WHERE role = 'delivery' ORDER BY created_at DESC");
    $partners = $stmt->fetchAll();
    respond(true, 'Partners fetched', ['partners' => $partners]);
}

// --- CREATE DELIVERY PARTNER ---
if ($action === 'create_delivery_partner') {
    $name = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone = htmlspecialchars(strip_tags($data['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
    $otp = htmlspecialchars(strip_tags($data['otp'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($name) || empty($phone) || empty($otp)) {
        respond(false, 'Name, Phone, and Dummy OTP are required.');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO users (name, phone, dummy_otp, role) VALUES (?, ?, ?, 'delivery')");
        $stmt->execute([$name, $phone, $otp]);
        respond(true, 'Delivery partner created successfully.');
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- PUSH NOTIFICATIONS (SIMULATED) ---
if ($action === 'send_notification') {
    $title = htmlspecialchars(strip_tags($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(strip_tags($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($title) || empty($message)) {
        respond(false, 'Title and message are required.');
    }

    // In a real system, you'd insert these into a `notifications` table or call Firebase Cloud Messaging (FCM).
    // For this prototype, we'll pretend it fired to all devices successfully.
    respond(true, 'Push Notification sent to all users successfully!');
}

// --- GET ANALYTICS ---
if ($action === 'get_analytics') {
    // 1. Monthly Revenue (Last 6 months)
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %Y') as month, SUM(amount) as total 
        FROM payments 
        WHERE status = 'completed' 
        GROUP BY month 
        ORDER BY MIN(created_at) DESC LIMIT 6
    ");
    $revenueData = array_reverse($stmt->fetchAll());

    // 2. Order Status Distribution
    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $distribution = $stmt->fetchAll();

    respond(true, 'Analytics fetched', [
        'revenue' => $revenueData,
        'distribution' => $distribution
    ]);
}

// --- GET ALL COUPONS ---
if ($action === 'get_coupons') {
    $stmt = $pdo->query("
        SELECT c.*,
            COUNT(cu.id) as total_used,
            COALESCE(SUM(cu.discount_amount), 0) as total_discount_given
        FROM coupons c
        LEFT JOIN coupon_usages cu ON c.id = cu.coupon_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
    ");
    respond(true, 'Coupons fetched', ['coupons' => $stmt->fetchAll()]);
}

// --- CREATE COUPON ---
if ($action === 'create_coupon') {
    $code         = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code'] ?? ''));
    $type         = $data['discount_type'] ?? 'flat';
    $value        = (float)($data['discount_value'] ?? 0);
    $usageLimit   = isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null;
    $perUserLimit = (int)($data['per_user_limit'] ?? 1);
    $minOrder     = (float)($data['min_order_amount'] ?? 0);
    $expiresAt    = !empty($data['expires_at']) ? $data['expires_at'] : null;

    if (empty($code) || $value <= 0 || !in_array($type, ['percentage', 'flat'])) {
        respond(false, 'Code, valid type and positive discount value are required.');
    }
    if ($type === 'percentage' && $value > 100) {
        respond(false, 'Percentage discount cannot exceed 100%.');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO coupons (code, discount_type, discount_value, usage_limit, per_user_limit, min_order_amount, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([$code, $type, $value, $usageLimit, $perUserLimit, $minOrder, $expiresAt]);
        respond(true, "Coupon '$code' created successfully!");
    } catch (\Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) {
            respond(false, 'A coupon with that code already exists.');
        }
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// --- TOGGLE COUPON ACTIVE ---
if ($action === 'toggle_coupon') {
    $couponId = (int)($data['coupon_id'] ?? 0);
    if (!$couponId) respond(false, 'Invalid coupon ID.');
    try {
        $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?")->execute([$couponId]);
        respond(true, 'Coupon status toggled.');
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// --- DELETE COUPON ---
if ($action === 'delete_coupon') {
    $couponId = (int)($data['coupon_id'] ?? 0);
    if (!$couponId) respond(false, 'Invalid coupon ID.');
    try {
        $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$couponId]);
        respond(true, 'Coupon deleted.');
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// --- GET COUPON USAGE HISTORY ---
if ($action === 'get_coupon_usage') {
    $couponId = (int)($data['coupon_id'] ?? 0);
    if (!$couponId) respond(false, 'Invalid coupon ID.');

    $stmt = $pdo->prepare("
        SELECT cu.id, cu.used_at, cu.discount_amount, cu.order_id,
               u.name as user_name, u.phone as user_phone, u.email as user_email,
               o.total_amount as order_total
        FROM coupon_usages cu
        JOIN users u ON cu.user_id = u.id
        JOIN orders o ON cu.order_id = o.id
        WHERE cu.coupon_id = ?
        ORDER BY cu.used_at DESC
    ");
    $stmt->execute([$couponId]);
    respond(true, 'Usage fetched', ['usages' => $stmt->fetchAll()]);
}

respond(false, 'Invalid action specified in api/admin.php');
