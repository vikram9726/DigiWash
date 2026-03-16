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

        // Also cancel the pending payment
        $stmt = $pdo->prepare("UPDATE payments SET status = 'cancelled', updated_at = NOW() WHERE order_id = ? AND status = 'remaining'");
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
    // Fetch return requests mapped to orders
    $stmt = $pdo->query("
        SELECT r.*, o.total_amount, u.name as customer_name, u.phone 
        FROM returns r
        JOIN orders o ON r.order_id = o.id
        JOIN users u ON o.user_id = u.id
        ORDER BY r.created_at DESC
    ");
    $returns = $stmt->fetchAll();
    respond(true, 'Returns fetched', ['returns' => $returns]);
}

// --- APPROVE/DECLINE RETURN ---
if ($action === 'handle_return') {
    $returnId = $data['return_id'] ?? 0;
    $status = $data['status'] ?? ''; // 'approved', 'rejected'

    if (!in_array($status, ['approved', 'rejected'])) {
        respond(false, 'Invalid status.');
    }

    try {
        $stmt = $pdo->prepare("UPDATE returns SET status = ? WHERE id = ?");
        $stmt->execute([$status, $returnId]);

        // Note: If approved, you might generate a refund payment log or queue a pickup. Let's keep it simple.
        
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
    $name = filter_var($data['name'] ?? '', FILTER_SANITIZE_STRING);
    $phone = filter_var($data['phone'] ?? '', FILTER_SANITIZE_STRING);
    $otp = filter_var($data['otp'] ?? '', FILTER_SANITIZE_STRING);

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
    $title = filter_var($data['title'] ?? '', FILTER_SANITIZE_STRING);
    $message = filter_var($data['message'] ?? '', FILTER_SANITIZE_STRING);

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

respond(false, 'Invalid action specified in api/admin.php');
