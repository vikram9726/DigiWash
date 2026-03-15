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

if (!isset($_SESSION['user_id'])) {
    respond(false, 'Unauthorized. Please log in.');
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

// CSRF Protection Check
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token. Request denied.');
}

// --- GET DASHBOARD STATS ---
if ($action === 'get_dashboard_stats') {
    // Active orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending', 'picked_up', 'in_process', 'out_for_delivery')");
    $stmt->execute([$userId]);
    $activeOrders = $stmt->fetchColumn();

    // Completed orders
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
    $stmt->execute([$userId]);
    $completedOrders = $stmt->fetchColumn();

    // Pending payment
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'remaining'");
    $stmt->execute([$userId]);
    $pendingPayment = $stmt->fetchColumn() ?: 0.00;

    respond(true, 'Stats fetched', [
        'active_orders' => $activeOrders,
        'completed_orders' => $completedOrders,
        'pending_payment' => $pendingPayment
    ]);
}

// --- CREATE ORDER ---
if ($action === 'create_order') {
    $weight = (float)($data['weight'] ?? 0);
    $instructions = filter_var($data['instructions'] ?? '', FILTER_SANITIZE_STRING);

    if ($weight <= 0) {
        respond(false, 'Please enter a valid approximate weight.');
    }

    // 1. Check if user needs profile setup
    $stmt = $pdo->prepare("SELECT name, shop_address FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (empty($user['name']) || empty($user['shop_address'])) {
        respond(false, 'Please complete your profile details before creating an order.');
    }

    // 2. Enforce the 4-Order Payment Lock Logic
    // Count how many completed unpaid orders exist
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN orders o ON p.order_id = o.id WHERE p.user_id = ? AND p.status = 'remaining' AND o.status = 'delivered'");
    $stmt->execute([$userId]);
    $unpaidCompletedOrders = $stmt->fetchColumn();

    // If they have 4 or more, block new orders until they pay (or lock to COD only later during payment phase)
    if ($unpaidCompletedOrders >= 4) {
        respond(false, 'You have reached the maximum limit of 4 unpaid delivered orders. Please clear your remaining dues to create new pay-later orders.');
        // Note: In a full app, we might still allow them to create a strict COD order here.
    }

    // 3. Create the Order
    try {
        $pdo->beginTransaction();

        $pricePerKg = 50; // Example base rate
        $totalAmount = $weight * $pricePerKg;

        $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_status, cancellation_reason) VALUES (?, 'pending', ?, 'remaining', ?)");
        $stmt->execute([$userId, $totalAmount, $instructions]);
        $orderId = $pdo->lastInsertId();

        // 4. Create the corresponding Payment record (Defaults to pending COD/Pay Later)
        $stmt = $pdo->prepare("INSERT INTO payments (user_id, order_id, payment_mode, status, amount) VALUES (?, ?, 'COD', 'remaining', ?)");
        $stmt->execute([$userId, $orderId, $totalAmount]);

        $pdo->commit();
        respond(true, 'Order created successfully! Our delivery partner will be assigned shortly.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Failed to create order. Error: ' . $e->getMessage());
    }
}

// --- FETCH ORDERS ---
if ($action === 'get_orders') {
    $type = $data['type'] ?? 'ongoing'; // ongoing or completed

    if ($type === 'ongoing') {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND status != 'delivered' AND status != 'cancelled' ORDER BY created_at DESC");
    } else {
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? AND (status = 'delivered' OR status = 'cancelled') ORDER BY created_at DESC");
    }
    
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    respond(true, 'Orders fetched', ['orders' => $orders]);
}

// --- FETCH PAYMENTS ---
if ($action === 'get_payments') {
    $type = $data['type'] ?? 'remaining'; // 'remaining' or 'completed'

    $stmt = $pdo->prepare("SELECT p.*, o.total_amount FROM payments p JOIN orders o ON p.order_id = o.id WHERE p.user_id = ? AND p.status = ? ORDER BY p.created_at DESC");
    $stmt->execute([$userId, $type]);
    $payments = $stmt->fetchAll();

    respond(true, 'Payments fetched', ['payments' => $payments]);
}

// --- SUBMIT RETURN REQUEST ---
if ($action === 'request_return') {
    $orderId = $_POST['order_id'] ?? 0;
    $reason = filter_var($_POST['reason'] ?? '', FILTER_SANITIZE_STRING);

    if (empty($orderId) || empty($reason)) {
        respond(false, 'Order ID and reason are required.');
    }

    // Verify order belongs to user and is delivered
    $stmt = $pdo->prepare("SELECT status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();

    if (!$order || $order['status'] !== 'delivered') {
        respond(false, 'Only delivered orders can be returned.');
    }

    if (!isset($_FILES['return_photo']) || $_FILES['return_photo']['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'A clear photo of the issue is required for a return request.');
    }

    $uploadDir = '../uploads/returns/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $fileExt = strtolower(pathinfo($_FILES['return_photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($fileExt, ['jpg', 'jpeg', 'png'])) respond(false, 'Only JPG/PNG images allowed.');

    $newFileName = 'return_' . $orderId . '_' . time() . '.' . $fileExt;
    
    if (move_uploaded_file($_FILES['return_photo']['tmp_name'], $uploadDir . $newFileName)) {
        $photoUrl = 'uploads/returns/' . $newFileName;

        try {
            $stmt = $pdo->prepare("INSERT INTO returns (order_id, user_id, reason, photo_url) VALUES (?, ?, ?, ?)");
            $stmt->execute([$orderId, $userId, $reason, $photoUrl]);
            respond(true, 'Return request submitted successfully. Our admin will review it.');
        } catch (\Exception $e) {
            respond(false, 'Database Error: ' . $e->getMessage());
        }
    } else {
        respond(false, 'Failed to upload photo.');
    }
}

respond(false, 'Invalid action specified in api/orders.php');
