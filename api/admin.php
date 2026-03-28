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

// ─────────────────────────────────────────────
// OVERVIEW / STATS
// ─────────────────────────────────────────────
if ($action === 'get_stats') {
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'");
    $totalUsers = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered', 'cancelled')");
    $activeOrders = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status = 'remaining'");
    $pendingRevenue = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'delivery'");
    $deliveryPartners = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE status = 'delivered'");
    $totalRevenue = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders");
    $totalOrders = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM returns WHERE admin_status = 'pending'");
    $pendingReturns = $stmt->fetchColumn();

    respond(true, 'Stats fetched', [
        'users'           => $totalUsers,
        'orders'          => $activeOrders,
        'revenue'         => number_format($pendingRevenue, 2),
        'total_revenue'   => number_format($totalRevenue, 2),
        'partners'        => $deliveryPartners,
        'total_orders'    => $totalOrders,
        'pending_returns' => $pendingReturns
    ]);
}

// ─────────────────────────────────────────────
// ANALYTICS
// ─────────────────────────────────────────────
if ($action === 'get_analytics') {
    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %Y') as month, SUM(amount) as total 
        FROM payments 
        WHERE status = 'completed' 
        GROUP BY month 
        ORDER BY MIN(created_at) DESC LIMIT 6
    ");
    $revenueData = array_reverse($stmt->fetchAll());

    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM orders GROUP BY status");
    $distribution = $stmt->fetchAll();

    $stmt = $pdo->query("
        SELECT DATE_FORMAT(created_at, '%b %Y') as month, COUNT(*) as count
        FROM orders
        GROUP BY month
        ORDER BY MIN(created_at) DESC LIMIT 6
    ");
    $orderTrends = array_reverse($stmt->fetchAll());

    respond(true, 'Analytics fetched', [
        'revenue'      => $revenueData,
        'distribution' => $distribution,
        'order_trends' => $orderTrends
    ]);
}

// ─────────────────────────────────────────────
// USER MANAGEMENT
// ─────────────────────────────────────────────
if ($action === 'get_users') {
    $search = '%' . ($data['search'] ?? '') . '%';
    $filter = $data['filter'] ?? 'all';
    
    $whereClause = "WHERE u.role = 'customer' AND (u.name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    if ($filter === 'pay_later') {
        $whereClause .= " AND u.pay_later_status != 'locked'";
    }

    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.phone, u.email, u.shop_address, u.created_at, u.is_blocked, u.pay_later_plan, u.pay_later_status,
               COUNT(o.id) as total_orders,
               COALESCE(SUM(p.amount),0) as total_spent
        FROM users u
        LEFT JOIN orders o ON o.user_id = u.id AND o.status = 'delivered'
        LEFT JOIN payments p ON p.user_id = u.id AND p.status = 'completed'
        $whereClause
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $stmt->execute([$search, $search, $search]);
    $users = $stmt->fetchAll();
    respond(true, 'Users fetched', ['users' => $users]);
}

if ($action === 'get_user_orders') {
    $userId = (int)($data['user_id'] ?? 0);
    if (!$userId) respond(false, 'User ID required.');
    
    $stmtUser = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmtUser->execute([$userId]);
    $userData = $stmtUser->fetch();
    if (!$userData) respond(false, 'User not found');

    $stmt = $pdo->prepare("
        SELECT o.*, d.name as delivery_name,
               p.payment_mode, p.status as payment_status, p.amount as payment_amount
        FROM orders o
        LEFT JOIN users d ON o.delivery_id = d.id
        LEFT JOIN payments p ON p.order_id = o.id
        WHERE o.user_id = ?
        ORDER BY o.created_at DESC LIMIT 100
    ");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    $stmtPay = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as paid FROM payments WHERE user_id = ? AND status = 'completed'");
    $stmtPay->execute([$userId]);
    $totalPaid = $stmtPay->fetchColumn();

    $stmtDue = $pdo->prepare("SELECT COALESCE(SUM(amount),0) as due FROM payments WHERE user_id = ? AND status = 'remaining'");
    $stmtDue->execute([$userId]);
    $totalDue = $stmtDue->fetchColumn();

    respond(true, 'History fetched', [
        'user' => $userData, 
        'orders' => $orders, 
        'total_paid' => $totalPaid, 
        'total_due' => $totalDue
    ]);
}

if ($action === 'toggle_block_user') {
    $userId = (int)($data['user_id'] ?? 0);
    if (!$userId) respond(false, 'Invalid user.');
    try {
        $pdo->prepare("UPDATE users SET is_blocked = NOT COALESCE(is_blocked, 0) WHERE id = ? AND role = 'customer'")->execute([$userId]);
        respond(true, 'User block status toggled.');
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

if ($action === 'assign_pay_later') {
    $userId = (int)($data['user_id'] ?? 0);
    $plan = $data['plan'] ?? ''; 
    if ($plan === 'DECLINED') {
        $pdo->prepare("UPDATE users SET pay_later_status = 'declined' WHERE id = ?")->execute([$userId]);
        respond(true, "Request declined.");
    } elseif (in_array($plan, ['PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12'])) {
        $pdo->prepare("UPDATE users SET pay_later_plan = ?, pay_later_status = 'approved' WHERE id = ?")->execute([$plan, $userId]);
        respond(true, "Successfully approved for $plan.");
    } else {
        respond(false, 'Invalid plan.');
    }
}

if ($action === 'revoke_pay_later') {
    $userId = (int)($data['user_id'] ?? 0);
    if (!$userId) respond(false, 'Invalid user.');
    $pdo->prepare("UPDATE users SET pay_later_plan = 'NONE', pay_later_status = 'locked' WHERE id = ?")->execute([$userId]);
    respond(true, "Pay Later approval revoked.");
}

if ($action === 'delete_user') {
    $userId = (int)($data['user_id'] ?? 0);
    if (!$userId) respond(false, 'Invalid user.');
    try {
        $pdo->beginTransaction();
        // Remove coupon usages
        $pdo->prepare("DELETE FROM coupon_usages WHERE user_id = ?")->execute([$userId]);
        // Remove returns
        $pdo->prepare("DELETE FROM returns WHERE user_id = ?")->execute([$userId]);
        // Remove payments
        $pdo->prepare("DELETE FROM payments WHERE user_id = ?")->execute([$userId]);
        // Nullify delivery assignments
        $pdo->prepare("UPDATE orders SET delivery_id = NULL WHERE delivery_id = ?")->execute([$userId]);
        // Remove orders
        $pdo->prepare("DELETE FROM orders WHERE user_id = ?")->execute([$userId]);
        // Remove user
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'customer'")->execute([$userId]);
        $pdo->commit();
        respond(true, 'User and all associated data deleted.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Delete failed: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────
// ORDER MANAGEMENT
// ─────────────────────────────────────────────
if ($action === 'get_all_orders') {
    $filter = $data['filter'] ?? 'all'; // all, pending, active, delivered, cancelled
    $search = '%' . ($data['search'] ?? '') . '%';
    $marketIdFilter = (int)($data['market_id'] ?? 0);

    $where = "WHERE (u.name LIKE ? OR u.phone LIKE ? OR CAST(o.id AS CHAR) LIKE ?)";
    $params = [$search, $search, $search];

    if ($marketIdFilter > 0) {
        $where .= " AND o.market_id = ?";
        $params[] = $marketIdFilter;
    }

    if ($filter === 'active') {
        $where .= " AND o.status NOT IN ('delivered','cancelled')";
    } elseif (in_array($filter, ['pending','assigned','delivered','cancelled','in_process','out_for_delivery','picked_up'])) {
        $where .= " AND o.status = ?";
        $params[] = $filter;
    }

    $stmt = $pdo->prepare("
        SELECT o.*, u.name as customer_name, u.phone as customer_phone, 
               d.name as delivery_name, m.name as market_name
        FROM orders o 
        JOIN users u ON o.user_id = u.id 
        LEFT JOIN users d ON o.delivery_id = d.id 
        LEFT JOIN markets m ON o.market_id = m.id
        $where
        ORDER BY o.created_at DESC LIMIT 150
    ");
    $stmt->execute($params);
    $orders = $stmt->fetchAll();
    
    $stmt2 = $pdo->query("SELECT id, name FROM users WHERE role = 'delivery' ORDER BY name");
    $partners = $stmt2->fetchAll();

    $stmt3 = $pdo->query("SELECT id, name FROM markets ORDER BY name ASC");
    $markets = $stmt3->fetchAll();

    respond(true, 'Orders fetched', ['orders' => $orders, 'delivery_partners' => $partners, 'markets' => $markets]);
}

if ($action === 'assign_order') {
    $orderId    = (int)($data['order_id'] ?? 0);
    $deliveryId = (int)($data['delivery_id'] ?? 0);
    if (!$orderId || !$deliveryId) respond(false, 'Order ID and partner required.');
    try {
        $pdo->prepare("UPDATE orders SET delivery_id = ?, updated_at = NOW() WHERE id = ?")->execute([$deliveryId, $orderId]);
        $stmtUser = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmtUser->execute([$orderId]);
        $ownerId = $stmtUser->fetchColumn();
        
        $title1 = "Driver Assigned";
        $msg1 = "A delivery partner has been assigned to your order!";
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title1, $msg1]);
        sendPushNotification($pdo, $ownerId, $title1, $msg1);
        
        $title2 = "New Task Assigned";
        $msg2 = "A new delivery task #$orderId is assigned to you.";
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$deliveryId, $title2, $msg2]);
        sendPushNotification($pdo, $deliveryId, $title2, $msg2);
        
        respond(true, 'Order assigned to delivery partner successfully.');
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

if ($action === 'update_order_status') {
    $orderId   = (int)($data['order_id'] ?? 0);
    $newStatus = $data['status'] ?? '';
    $allowed   = ['pending','picked_up','in_process','out_for_delivery','delivered','cancelled'];
    if (!$orderId || !in_array($newStatus, $allowed)) respond(false, 'Invalid order or status.');
    try {
        $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?")->execute([$newStatus, $orderId]);
        
        $stmtUser = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
        $stmtUser->execute([$orderId]);
        $ownerId = $stmtUser->fetchColumn();
        
        $statusLabels = [
            'pending' => 'Pending',
            'picked_up' => 'Picked Up',
            'in_process' => 'In Process',
            'out_for_delivery' => 'Out for Delivery',
            'delivered' => 'Delivered',
            'cancelled' => 'Cancelled'
        ];
        $displayStatus = $statusLabels[$newStatus] ?? $newStatus;
        
        $title = "Order Update";
        $msg = "Your order #$orderId is now $displayStatus.";
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title, $msg]);
        sendPushNotification($pdo, $ownerId, $title, $msg);
        
        respond(true, "Order #$orderId status updated to $newStatus.");
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

if ($action === 'cancel_order') {
    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) respond(false, 'Invalid order.');
    try {
        $pdo->beginTransaction();
        // Get the delivery_id before cancelling so we can release the load counter
        $stmtOrd = $pdo->prepare("SELECT delivery_id FROM orders WHERE id = ?");
        $stmtOrd->execute([$orderId]);
        $orderRow = $stmtOrd->fetch();

        $pdo->prepare("UPDATE orders SET status = 'cancelled', delivery_id = NULL, updated_at = NOW() WHERE id = ?")->execute([$orderId]);

        // Release delivery partner's active order count if assigned
        if (!empty($orderRow['delivery_id'])) {
            $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$orderRow['delivery_id']]);
        }

        // Delete the remaining payment record (do NOT mark as 'completed' — that inflates revenue)
        $pdo->prepare("DELETE FROM payments WHERE order_id = ? AND status = 'remaining'")->execute([$orderId]);
        // Release any coupon usage for the order
        $pdo->prepare("DELETE FROM coupon_usages WHERE order_id = ?")->execute([$orderId]);

        $pdo->commit();
        respond(true, 'Order cancelled successfully.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────
// RETURN REQUESTS
// ─────────────────────────────────────────────
if ($action === 'get_returns') {
    $filter = $data['filter'] ?? 'all';
    $where  = $filter !== 'all' ? "WHERE r.admin_status = '$filter'" : '';
    $stmt   = $pdo->query("
        SELECT r.id, r.order_id, r.reason, r.photo_url, r.created_at, r.admin_status,
               o.total_amount, u.name as customer_name, u.phone
        FROM returns r
        JOIN orders o ON r.order_id = o.id
        JOIN users u ON r.user_id = u.id
        $where
        ORDER BY r.created_at DESC
    ");
    respond(true, 'Returns fetched', ['returns' => $stmt->fetchAll()]);
}

if ($action === 'handle_return') {
    $returnId = (int)($data['return_id'] ?? 0);
    $status   = $data['status'] ?? '';
    if ($status === 'rejected') $status = 'declined';
    if (!in_array($status, ['approved', 'declined'])) respond(false, 'Invalid status.');
    try {
        $pdo->prepare("UPDATE returns SET admin_status = ? WHERE id = ?")->execute([$status, $returnId]);
        respond(true, "Return request $status successfully.");
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────
// MARKET MANAGEMENT
// ─────────────────────────────────────────────
if ($action === 'get_markets') {
    $stmt = $pdo->query("SELECT * FROM markets ORDER BY name ASC");
    respond(true, 'Markets fetched', ['markets' => $stmt->fetchAll()]);
}

if ($action === 'create_market') {
    $name = trim($data['name'] ?? '');
    if (!$name) respond(false, 'Market name is required.');
    $pdo->prepare("INSERT INTO markets (name) VALUES (?)")->execute([$name]);
    respond(true, 'Market created successfully.');
}

if ($action === 'update_market') {
    $id = (int)($data['market_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    if (!$id || !$name) respond(false, 'Market ID and Name are required.');
    $pdo->prepare("UPDATE markets SET name = ? WHERE id = ?")->execute([$name, $id]);
    respond(true, 'Market updated successfully.');
}

if ($action === 'delete_market') {
    $id = (int)($data['market_id'] ?? 0);
    if (!$id) respond(false, 'Invalid market ID.');
    // Set market_id = NULL before deleting
    $pdo->prepare("UPDATE users SET market_id = NULL WHERE market_id = ?")->execute([$id]);
    $pdo->prepare("UPDATE orders SET market_id = NULL WHERE market_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM markets WHERE id = ?")->execute([$id]);
    respond(true, 'Market deleted successfully.');
}

// ─────────────────────────────────────────────
// DELIVERY PARTNERS
// ─────────────────────────────────────────────
if ($action === 'get_partners') {
    $stmt = $pdo->query("
        SELECT u.id, u.name, u.phone, u.dummy_otp, u.created_at, u.market_id, m.name as market_name, u.is_online, u.current_orders,
               COUNT(o.id) as total_assignments,
               SUM(CASE WHEN o.status = 'delivered' THEN 1 ELSE 0 END) as completed,
               SUM(CASE WHEN o.status NOT IN ('delivered','cancelled') THEN 1 ELSE 0 END) as active
        FROM users u
        LEFT JOIN orders o ON o.delivery_id = u.id
        LEFT JOIN markets m ON u.market_id = m.id
        WHERE u.role = 'delivery'
        GROUP BY u.id
        ORDER BY u.created_at DESC
    ");
    $markets = $pdo->query("SELECT id, name FROM markets ORDER BY name ASC")->fetchAll();
    respond(true, 'Partners fetched', ['partners' => $stmt->fetchAll(), 'markets' => $markets]);
}

if ($action === 'create_delivery_partner') {
    $name  = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $phone = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
    $otp   = preg_replace('/[^0-9]/', '', $data['otp'] ?? '');
    $marketId = (int)($data['market_id'] ?? 0) ?: null;

    if (empty($name))             respond(false, 'Name is required.');
    if (strlen($phone) !== 10)    respond(false, 'Phone must be exactly 10 digits.');
    if (!preg_match('/^[6-9]/', $phone)) respond(false, 'Phone must start with 6–9.');
    if (empty($otp) || strlen($otp) < 4) respond(false, 'OTP must be at least 4 digits.');

    try {
        $pdo->prepare("INSERT INTO users (name, phone, dummy_otp, role, market_id) VALUES (?, ?, ?, 'delivery', ?)")->execute([$name, $phone, $otp, $marketId]);
        respond(true, 'Delivery partner created successfully.');
    } catch (\Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate'))
            respond(false, 'A user with this phone already exists.');
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

if ($action === 'update_delivery_partner') {
    $partnerId = (int)($data['partner_id'] ?? 0);
    $name      = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $otp       = preg_replace('/[^0-9]/', '', $data['otp'] ?? '');
    $marketId  = (int)($data['market_id'] ?? 0) ?: null;

    if (!$partnerId || empty($name)) respond(false, 'Partner ID and name are required.');
    if (!empty($otp) && strlen($otp) < 4) respond(false, 'OTP must be at least 4 digits.');

    try {
        if (!empty($otp)) {
            $pdo->prepare("UPDATE users SET name = ?, dummy_otp = ?, market_id = ? WHERE id = ? AND role = 'delivery'")->execute([$name, $otp, $marketId, $partnerId]);
        } else {
            $pdo->prepare("UPDATE users SET name = ?, market_id = ? WHERE id = ? AND role = 'delivery'")->execute([$name, $marketId, $partnerId]);
        }
        respond(true, 'Partner updated successfully.');
    } catch (\Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

if ($action === 'delete_delivery_partner') {
    $partnerId = (int)($data['partner_id'] ?? 0);
    if (!$partnerId) respond(false, 'Invalid partner ID.');
    try {
        $pdo->beginTransaction();
        // Unassign them from orders first (don't delete the orders)
        $pdo->prepare("UPDATE orders SET delivery_id = NULL WHERE delivery_id = ?")->execute([$partnerId]);
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'delivery'")->execute([$partnerId]);
        $pdo->commit();
        respond(true, 'Delivery partner removed.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Delete failed: ' . $e->getMessage());
    }
}

if ($action === 'get_partner_stats') {
    $partnerId = (int)($data['partner_id'] ?? 0);
    if (!$partnerId) respond(false, 'Invalid partner ID.');

    $stmt = $pdo->prepare("
        SELECT o.id, o.status, o.total_amount, o.created_at, u.name as customer_name, u.phone
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.delivery_id = ?
        ORDER BY o.created_at DESC LIMIT 30
    ");
    $stmt->execute([$partnerId]);
    $orders = $stmt->fetchAll();
    respond(true, 'Partner stats', ['orders' => $orders]);
}

// ─────────────────────────────────────────────
// PUSH NOTIFICATIONS
// ─────────────────────────────────────────────
if ($action === 'send_notification') {
    $title   = htmlspecialchars(strip_tags($data['title'] ?? ''), ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars(strip_tags($data['message'] ?? ''), ENT_QUOTES, 'UTF-8');
    if (empty($title) || empty($message)) respond(false, 'Title and message are required.');
    
    // Get all active users
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'customer'");
    $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($users as $uId) {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$uId, $title, $message]);
        sendPushNotification($pdo, $uId, $title, $message);
    }
    
    respond(true, 'Push notification sent to all users successfully!');
}

// ─────────────────────────────────────────────
// COUPONS
// ─────────────────────────────────────────────
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

if ($action === 'create_coupon') {
    $code         = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['code'] ?? ''));
    $type         = $data['discount_type'] ?? 'flat';
    $value        = (float)($data['discount_value'] ?? 0);
    $usageLimit   = isset($data['usage_limit']) && $data['usage_limit'] !== '' ? (int)$data['usage_limit'] : null;
    $perUserLimit = (int)($data['per_user_limit'] ?? 1);
    $minOrder     = (float)($data['min_order_amount'] ?? 0);
    $expiresAt    = !empty($data['expires_at']) ? $data['expires_at'] : null;

    if (empty($code) || $value <= 0 || !in_array($type, ['percentage', 'flat']))
        respond(false, 'Code, valid type and positive discount value are required.');
    if ($type === 'percentage' && $value > 100)
        respond(false, 'Percentage discount cannot exceed 100%.');

    try {
        $pdo->prepare("
            INSERT INTO coupons (code, discount_type, discount_value, usage_limit, per_user_limit, min_order_amount, expires_at, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ")->execute([$code, $type, $value, $usageLimit, $perUserLimit, $minOrder, $expiresAt]);
        respond(true, "Coupon '$code' created successfully!");
    } catch (\Exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate')) respond(false, 'A coupon with that code already exists.');
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

if ($action === 'toggle_coupon') {
    $id = (int)($data['coupon_id'] ?? 0);
    if (!$id) respond(false, 'Invalid coupon ID.');
    $pdo->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?")->execute([$id]);
    respond(true, 'Coupon status toggled.');
}

if ($action === 'delete_coupon') {
    $id = (int)($data['coupon_id'] ?? 0);
    if (!$id) respond(false, 'Invalid coupon ID.');
    $pdo->prepare("DELETE FROM coupon_usages WHERE coupon_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM coupons WHERE id = ?")->execute([$id]);
    respond(true, 'Coupon deleted.');
}

if ($action === 'get_coupon_usage') {
    $id = (int)($data['coupon_id'] ?? 0);
    if (!$id) respond(false, 'Invalid coupon ID.');
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
    $stmt->execute([$id]);
    respond(true, 'Usage fetched', ['usages' => $stmt->fetchAll()]);
}

respond(false, 'Invalid action: ' . $action);
