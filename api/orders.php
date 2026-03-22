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
    $instructions  = htmlspecialchars(strip_tags($data['instructions'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cartItems     = $data['items'] ?? [];    // [{product_price_id, quantity}]
    $weight        = (float)($data['weight'] ?? 0); // fallback (legacy)

    // Must have either cart items or weight
    if (empty($cartItems) && $weight <= 0) {
        respond(false, 'Please add at least one item or enter a weight.');
    }

    // 1. Check profile
    $stmt = $pdo->prepare("SELECT name, shop_address, pay_later_plan, pay_later_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (empty($user['name']) || empty($user['shop_address'])) {
        respond(false, 'Please complete your profile before creating an order.');
    }

    $paymentMode = in_array(strtoupper($data['payment_mode'] ?? 'COD'), ['COD', 'ONLINE', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12']) ? strtoupper($data['payment_mode']) : 'COD';

    // 2. Payment lock logic based on user's authorized mode
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p JOIN orders o ON p.order_id = o.id WHERE p.user_id = ? AND p.status = 'remaining' AND o.status = 'delivered'");
    $stmt->execute([$userId]);
    $unpaidCount = $stmt->fetchColumn();

    if (strpos($paymentMode, 'PAY_LATER') !== false) {
        if ($user['pay_later_status'] !== 'approved' || $user['pay_later_plan'] !== $paymentMode) {
            respond(false, "You are not approved for the $paymentMode plan. Defaulting to Cash on Delivery. Please request access from profile or select Pay Now/COD.");
        }
        $limit = (int)str_replace('PAY_LATER_', '', $paymentMode);
        
        if ($unpaidCount >= $limit) {
            respond(false, "You have reached your limit of $limit unpaid delivered orders. Please clear dues before creating new orders.");
        }
    } else {
        // Default limit of 4 on normal COD orders to prevent unlimited free stuff
        if ($unpaidCount >= 4 && $paymentMode === 'COD') {
            respond(false, "You have 4 unpaid delivered orders. Please clear dues before creating new Cash on Delivery orders.");
        }
    }

    try {
        $pdo->beginTransaction();

        // --- Compute base amount ---
        $baseAmount    = 0;
        $resolvedItems = [];

        if (!empty($cartItems)) {
            foreach ($cartItems as $item) {
                $ppId = (int)($item['product_price_id'] ?? 0);
                $qty  = max(1, (int)($item['quantity'] ?? 1));
                if (!$ppId) continue;

                $st = $pdo->prepare("
                    SELECT pp.id, pp.price, pp.size_label, pp.unit, p.name as product_name, p.id as product_id
                    FROM product_prices pp
                    JOIN products p ON pp.product_id = p.id
                    WHERE pp.id = ? AND p.is_active = 1
                ");
                $st->execute([$ppId]);
                $row = $st->fetch();
                if (!$row) continue;

                $lineTotal      = $row['price'] * $qty;
                $baseAmount    += $lineTotal;
                $resolvedItems[] = [
                    'product_id'       => $row['product_id'],
                    'product_price_id' => $ppId,
                    'product_name'     => $row['product_name'],
                    'size_label'       => $row['size_label'],
                    'price'            => $row['price'],
                    'quantity'         => $qty,
                    'line_total'       => $lineTotal,
                ];
            }
            if (empty($resolvedItems)) { $pdo->rollBack(); respond(false, 'No valid items found. Please check your cart.'); }
        } else {
            // Weight-based fallback
            $baseAmount = $weight * 50;
        }

        // --- Coupon ---
        $discount       = 0;
        $appliedCouponId = null;
        $couponCode     = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['coupon_code'] ?? ''));

        if (!empty($couponCode)) {
            $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND min_order_amount <= ?");
            $stmt->execute([$couponCode, $baseAmount]);
            $coupon = $stmt->fetch();
            if ($coupon) {
                if ($coupon['usage_limit'] !== null) {
                    $tu = $pdo->prepare("SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ?"); $tu->execute([$coupon['id']]);
                    if ($tu->fetchColumn() >= $coupon['usage_limit']) { $pdo->rollBack(); respond(false, 'Coupon has reached max usage.'); }
                }
                $uu = $pdo->prepare("SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?"); $uu->execute([$coupon['id'], $userId]);
                if ($uu->fetchColumn() >= $coupon['per_user_limit']) { $pdo->rollBack(); respond(false, 'You have already used this coupon the max times.'); }
                $discount        = $coupon['discount_type'] === 'percentage' ? $baseAmount * ($coupon['discount_value']/100) : $coupon['discount_value'];
                $appliedCouponId = $coupon['id'];
            }
        }

        $totalAmount = max(0, $baseAmount - $discount);

        // --- Insert order ---
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_status, instructions) VALUES (?, 'pending', ?, 'remaining', ?)");
        $stmt->execute([$userId, $totalAmount, $instructions]);
        $orderId = $pdo->lastInsertId();

        // --- Insert order items (if product-based) ---
        if (!empty($resolvedItems)) {
            $itmStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_price_id, product_name, size_label, price, quantity, line_total) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($resolvedItems as $it) {
                $itmStmt->execute([$orderId, $it['product_id'], $it['product_price_id'], $it['product_name'], $it['size_label'], $it['price'], $it['quantity'], $it['line_total']]);
            }
        }

        // --- Insert payment ---
        $pdo->prepare("INSERT INTO payments (user_id, order_id, payment_mode, status, amount) VALUES (?, ?, ?, 'remaining', ?)")->execute([$userId, $orderId, $paymentMode, $totalAmount]);

        // --- Coupon usage ---
        if ($appliedCouponId) {
            $pdo->prepare("INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount) VALUES (?,?,?,?)")->execute([$appliedCouponId, $userId, $orderId, $discount]);
        }

        $pdo->commit();
        respond(true, 'Order placed! A delivery partner will be assigned soon.', ['order_id' => $orderId]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Failed to create order: ' . $e->getMessage());
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

// --- VALIDATE COUPON ---
if ($action === 'validate_coupon') {
    $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $data['coupon_code'] ?? ''));
    $orderAmount = (float)($data['order_amount'] ?? 0);
    if (empty($code)) respond(false, 'Coupon code is required.');

    $stmt = $pdo->prepare("
        SELECT * FROM coupons 
        WHERE code = ? AND is_active = 1 
        AND (expires_at IS NULL OR expires_at > NOW())
    ");
    $stmt->execute([$code]);
    $coupon = $stmt->fetch();

    if (!$coupon) {
        respond(false, 'Invalid or expired coupon code.');
    }

    // Check minimum order amount
    if ($orderAmount > 0 && $orderAmount < $coupon['min_order_amount']) {
        respond(false, 'Minimum order amount of ₹' . $coupon['min_order_amount'] . ' required for this coupon.');
    }

    // Check total usage limit
    if ($coupon['usage_limit'] !== null) {
        $totalUsed = $pdo->prepare("SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ?");
        $totalUsed->execute([$coupon['id']]);
        if ($totalUsed->fetchColumn() >= $coupon['usage_limit']) {
            respond(false, 'This coupon has reached its maximum usage limit.');
        }
    }

    // Check per-user limit
    $userUsed = $pdo->prepare("SELECT COUNT(*) FROM coupon_usages WHERE coupon_id = ? AND user_id = ?");
    $userUsed->execute([$coupon['id'], $userId]);
    if ($userUsed->fetchColumn() >= $coupon['per_user_limit']) {
        respond(false, 'You have already used this coupon the maximum number of times allowed.');
    }

    $discount = $coupon['discount_type'] === 'percentage'
        ? ($orderAmount * ($coupon['discount_value'] / 100))
        : $coupon['discount_value'];

    respond(true, 'Coupon applied!', [
        'discount_type'   => $coupon['discount_type'],
        'discount_value'  => $coupon['discount_value'],
        'discount_amount' => round($discount, 2),
        'min_order'       => $coupon['min_order_amount']
    ]);
}

// --- SUBMIT RETURN REQUEST ---
if ($action === 'request_return') {
    $orderId = $_POST['order_id'] ?? 0;
    $reason = htmlspecialchars(strip_tags($_POST['reason'] ?? ''), ENT_QUOTES, 'UTF-8');

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

    // 1. File Size Limit (5MB)
    if ($_FILES['return_photo']['size'] > 5 * 1024 * 1024) {
        respond(false, 'File size exceeds 5MB limit.');
    }

    $uploadDir = '../uploads/returns/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    // 2. Strict MIME Type Validation using finfo (Not just string extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['return_photo']['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        respond(false, 'Invalid file content. Only JPG/PNG images are allowed.');
    }

    // Determine extension safely
    $fileExt = ($mimeType === 'image/png') ? 'png' : 'jpg';
    
    // 3. Secure Filename Generation
    $newFileName = 'return_' . $orderId . '_' . uniqid() . '.' . $fileExt;
    
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

// --- REQUEST PAY LATER PLAN ---
if ($action === 'request_pay_later_plan') {
    $stmt = $pdo->prepare("SELECT pay_later_status FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch();
    
    if ($currentUser['pay_later_status'] === 'approved') {
        respond(false, 'You already have an approved plan. Contact support to change it.');
    }
    
    $stmt = $pdo->prepare("UPDATE users SET pay_later_status = 'pending_approval' WHERE id = ?");
    $stmt->execute([$userId]);
    
    respond(true, 'Pay Later request submitted to admin for approval!');
}

respond(false, 'Invalid action specified in api/orders.php');
