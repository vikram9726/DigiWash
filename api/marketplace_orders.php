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

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
$serverCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($serverCsrf) || !hash_equals($serverCsrf, $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

// ─── GET MARKETPLACE ORDERS ────────────────────────────────────────────────
if ($action === 'get_orders') {
    $userId = (int)$_SESSION['user_id'];
    $role = $_SESSION['role'] ?? 'customer';
    
    // User sees their own orders
    if ($role === 'customer') {
        $stmt = $pdo->prepare("
            SELECT o.*, d.name as delivery_name, d.phone as delivery_phone
            FROM marketplace_orders o
            LEFT JOIN users d ON o.delivery_id = d.id
            WHERE o.user_id = ?
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
    } else if ($role === 'admin') {
        $statusFilter = $data['status'] ?? '';
        $whereClause = $statusFilter ? "WHERE o.status = :status" : "";
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as user_name, u.phone as user_phone, u.shop_address, d.name as delivery_name
            FROM marketplace_orders o
            JOIN users u ON o.user_id = u.id
            LEFT JOIN users d ON o.delivery_id = d.id
            $whereClause
            ORDER BY o.created_at DESC
        ");
        if ($statusFilter) {
            $stmt->execute(['status' => $statusFilter]);
        } else {
            $stmt->execute();
        }
    } else if ($role === 'delivery') {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as user_name, u.phone as user_phone, u.shop_address, u.lat, u.lng
            FROM marketplace_orders o
            JOIN users u ON o.user_id = u.id
            WHERE o.delivery_id = ? AND o.status IN ('assigned', 'picked_up', 'out_for_delivery')
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$userId]);
    } else {
        respond(false, 'Invalid Role.');
    }

    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch order items
    foreach ($orders as &$order) {
        $itStmt = $pdo->prepare("
            SELECT i.*, p.name, p.category, p.size, p.image
            FROM marketplace_order_items i
            JOIN marketplace_products p ON i.product_id = p.id
            WHERE i.order_id = ?
        ");
        $itStmt->execute([$order['id']]);
        $order['items'] = $itStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($order);

    respond(true, 'Orders fetched', ['orders' => $orders]);
}

respond(false, 'Unknown action.');
