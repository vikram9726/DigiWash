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
    try {
        // Active orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status IN ('pending', 'assigned', 'picked_up', 'in_process', 'out_for_delivery')");
        $stmt->execute([$userId]);
        $activeOrders = $stmt->fetchColumn();

        // Completed orders
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
        $stmt->execute([$userId]);
        $completedOrders = $stmt->fetchColumn();

        // Pending payment general sum
        $stmt = $pdo->prepare("SELECT SUM(amount) FROM payments WHERE user_id = ? AND status = 'remaining'");
        $stmt->execute([$userId]);
        $pendingPayment = $stmt->fetchColumn() ?: 0.00;

        // Unpaid Pay Later count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'remaining' AND payment_mode LIKE 'PAY_LATER%'");
        $stmt->execute([$userId]);
        $unpaidPayLater = $stmt->fetchColumn() ?: 0;

        // Unpaid COD count
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'remaining' AND payment_mode = 'COD'");
        $stmt->execute([$userId]);
        $unpaidCod = $stmt->fetchColumn() ?: 0;

        // Auto Order Frequency
        $autoFreq = 'NONE';
        try {
            $stmt = $pdo->prepare("SELECT auto_order_frequency FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $autoFreq = $stmt->fetchColumn() ?: 'NONE';
        } catch (\Exception $e) {}

        // Recent Order (For Quick Reorder)
        $recentOrder = null;
        try {
            $recentStmt = $pdo->prepare("SELECT id, total_amount FROM orders WHERE user_id = ? AND status != 'cancelled' ORDER BY created_at DESC LIMIT 1");
            $recentStmt->execute([$userId]);
            $recentOrder = $recentStmt->fetch(PDO::FETCH_ASSOC);
            if ($recentOrder) {
                $itStmt = $pdo->prepare("SELECT product_price_id, product_name, size_label, price, quantity FROM order_items WHERE order_id = ?");
                $itStmt->execute([$recentOrder['id']]);
                $recentOrder['items'] = $itStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (\Exception $e) {}

        respond(true, 'Stats fetched', [
            'active_orders'    => $activeOrders,
            'completed_orders' => $completedOrders,
            'pending_payment'  => $pendingPayment,
            'unpaid_pay_later' => $unpaidPayLater,
            'unpaid_cod'       => $unpaidCod,
            'auto_order_freq'  => $autoFreq,
            'recent_order'     => $recentOrder
        ]);
    } catch (\Exception $e) {
        respond(false, 'Stats error: ' . $e->getMessage());
    }
}

// --- CREATE ORDER ---
if ($action === 'create_order') {
    $instructions  = htmlspecialchars(strip_tags($data['instructions'] ?? ''), ENT_QUOTES, 'UTF-8');
    $cartItems     = $data['items'] ?? [];    // [{product_price_id, quantity}]
    $weight        = (float)($data['weight'] ?? 0); // fallback (legacy)
    $isQuickPickup = filter_var($data['quick_pickup'] ?? false, FILTER_VALIDATE_BOOLEAN);

    // Must have either cart items, weight, or be a quick pickup request
    if (!$isQuickPickup && empty($cartItems) && $weight <= 0) {
        respond(false, 'Please add at least one item or enter a weight.');
    }

    // 1. Check profile
    $stmt = $pdo->prepare("SELECT name, shop_address, pay_later_plan, pay_later_status, market_id, lat, lng FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (empty($user['name']) || empty($user['shop_address']) || empty($user['market_id']) || empty($user['lat']) || empty($user['lng'])) {
        respond(false, 'Please complete your profile and "Detect Location" to set your precise coordinates before creating an order.');
    }

    $paymentMode = in_array(strtoupper($data['payment_mode'] ?? 'COD'), ['COD', 'ONLINE', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12']) ? strtoupper($data['payment_mode']) : 'COD';

    // 2. Payment lock logic based on user's authorized mode
    if (strpos($paymentMode, 'PAY_LATER') !== false) {
        if ($user['pay_later_status'] !== 'approved' || $user['pay_later_plan'] !== $paymentMode) {
            respond(false, "You are not approved for the $paymentMode plan. Defaulting to Cash on Delivery. Please request access from profile or select Pay Now/COD.");
        }
        $limit = (int)str_replace('PAY_LATER_', '', $paymentMode);
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'remaining' AND payment_mode LIKE 'PAY_LATER%'");
        $stmt->execute([$userId]);
        $unpaidLaterCount = $stmt->fetchColumn() ?: 0;

        if ($unpaidLaterCount >= $limit) {
            respond(false, "You have reached your maximum limit of $limit unpaid Pay Later orders. Please clear your dues before creating new Pay Later orders.");
        }
    } elseif ($paymentMode === 'COD') {
        // Default limit of 4 on normal COD orders to prevent unlimited free stuff
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'remaining' AND payment_mode = 'COD'");
        $stmt->execute([$userId]);
        $unpaidCodCount = $stmt->fetchColumn() ?: 0;

        if ($unpaidCodCount >= 4) {
            respond(false, "You have 4 unpaid Cash on Delivery orders. Please clear your dues before creating new Cash on Delivery orders.");
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
            // ISSUE-007 FIX: Lock the coupon row to prevent concurrent overdraft usage
            $stmt = $pdo->prepare("SELECT * FROM coupons WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW()) AND min_order_amount <= ? FOR UPDATE");
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

        $paymentState = 'remaining';
        $transactionId = null;

        if ($paymentMode === 'ONLINE') {
            $r_payId = $data['razorpay_payment_id'] ?? '';
            $r_ordId = $data['razorpay_order_id'] ?? '';
            $r_sign  = $data['razorpay_signature'] ?? '';

            if (!$r_payId) {
                $pdo->rollBack(); respond(false, 'Missing Razorpay payment gateway hash.');
            }

            if ($r_sign) {
                $keySecret = getenv('RAZORPAY_KEY_SECRET');
                $expectedSig = hash_hmac('sha256', $r_ordId . '|' . $r_payId, $keySecret);
                if (!hash_equals($expectedSig, $r_sign)) {
                    $pdo->rollBack(); respond(false, 'Payment verification failed (Invalid offline signature).');
                }
            } else {
                // Fallback: Directly pull payment metadata from Razorpay Core API over cURL 
                $rID = getenv('RAZORPAY_KEY_ID');
                $rSec = getenv('RAZORPAY_KEY_SECRET');
                $ch = curl_init("https://api.razorpay.com/v1/payments/$r_payId");
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERPWD, $rID . ':' . $rSec);
                $rzpData = json_decode(curl_exec($ch), true);
                curl_close($ch);

                if (!isset($rzpData['status']) || !in_array($rzpData['status'], ['authorized', 'captured'])) {
                    $pdo->rollBack(); respond(false, 'Payment transaction was not authorized or captured at Razorpay.');
                }
                if (isset($rzpData['amount']) && round($totalAmount * 100) != $rzpData['amount']) {
                    $pdo->rollBack(); respond(false, 'Payment parameter mismatch error.');
                }
            }
            
            $paymentState = 'completed';
            $transactionId = $r_payId;
        }

        // --- Fetch best delivery boy ---
        $deliveryId = null;
        $orderStatus = 'pending';
        
        $dbStmt = $pdo->prepare("
            SELECT id FROM users 
            WHERE role = 'delivery' AND is_online = 1 AND market_id = ? 
            ORDER BY current_orders ASC, id ASC LIMIT 1
        ");
        $dbStmt->execute([$user['market_id']]);
        $bestBoy = $dbStmt->fetch();
        
        if ($bestBoy) {
            $deliveryId = $bestBoy['id'];
            $orderStatus = 'assigned';
            
            // Increment the boy's load
            $pdo->prepare("UPDATE users SET current_orders = current_orders + 1 WHERE id = ?")->execute([$deliveryId]);
        }

        // --- Insert order ---
        $stmt = $pdo->prepare("INSERT INTO orders (user_id, market_id, delivery_id, status, total_amount, payment_status, instructions, lat, lng, pickup_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $user['market_id'], $deliveryId, $orderStatus, $totalAmount, $paymentState, $instructions, $user['lat'], $user['lng'], $user['shop_address']]);
        $orderId = $pdo->lastInsertId();

        // --- Insert order items (if product-based) ---
        if (!empty($resolvedItems)) {
            $itmStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, product_price_id, product_name, size_label, price, quantity, line_total) VALUES (?,?,?,?,?,?,?,?)");
            foreach ($resolvedItems as $it) {
                $itmStmt->execute([$orderId, $it['product_id'], $it['product_price_id'], $it['product_name'], $it['size_label'], $it['price'], $it['quantity'], $it['line_total']]);
            }
        }

        // --- Insert payment ---
        $pdo->prepare("INSERT INTO payments (user_id, order_id, payment_mode, status, amount) VALUES (?, ?, ?, ?, ?)")->execute([$userId, $orderId, $paymentMode, $paymentState, $totalAmount]);

        // --- Coupon usage ---
        if ($appliedCouponId) {
            $pdo->prepare("INSERT INTO coupon_usages (coupon_id, user_id, order_id, discount_amount) VALUES (?,?,?,?)")->execute([$appliedCouponId, $userId, $orderId, $discount]);
        }

        $pdo->commit();
        
        // Asynchronously triggering auto-generation via fast cURL 
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
        $url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/invoice.php";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['action' => 'auto_generate']));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        @curl_exec($ch);
        @curl_close($ch);

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
        $stmt = $pdo->prepare("
            SELECT o.*, p.payment_mode, d.name as delivery_guy_name, d.phone as delivery_guy_phone 
            FROM orders o 
            LEFT JOIN payments p ON p.order_id = o.id 
            LEFT JOIN users d ON o.delivery_id = d.id
            WHERE o.user_id = ? AND o.status != 'delivered' AND o.status != 'cancelled' 
            ORDER BY o.created_at DESC
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT o.*, p.payment_mode, d.name as delivery_guy_name, d.phone as delivery_guy_phone 
            FROM orders o 
            LEFT JOIN payments p ON p.order_id = o.id 
            LEFT JOIN users d ON o.delivery_id = d.id
            WHERE o.user_id = ? AND (o.status = 'delivered' OR o.status = 'cancelled') 
            ORDER BY o.created_at DESC
        ");
    }
    
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($orders)) {
        $orderIds = array_column($orders, 'id');
        $inQuery = str_repeat('?,', count($orderIds) - 1) . '?';
        $itStmt = $pdo->prepare("SELECT order_id, product_name, size_label, quantity FROM order_items WHERE order_id IN ($inQuery)");
        $itStmt->execute($orderIds);
        $allItems = $itStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $itemsByOrder = [];
        foreach ($allItems as $item) {
            $itemsByOrder[$item['order_id']][] = [
                'product_name' => $item['product_name'],
                'size_label'   => $item['size_label'],
                'quantity'     => $item['quantity']
            ];
        }
        
        foreach ($orders as &$order) {
            $order['items'] = $itemsByOrder[$order['id']] ?? [];
        }
    }

    respond(true, 'Orders fetched', ['orders' => $orders]);
}

// --- FETCH NOTIFICATIONS ---
if ($action === 'get_notifications') {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 30");
    $stmt->execute([$userId]);
    $notifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $unreadCount = 0;
    foreach($notifs as $n) { if ($n['is_read'] == 0) $unreadCount++; }
    respond(true, 'OK', ['notifications' => $notifs, 'unread' => $unreadCount]);
}

// --- MARK NOTIFICATIONS READ ---
if ($action === 'mark_notifications_read') {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$userId]);
    respond(true, 'All marked as read.');
}

// --- CANCEL ORDER ---
if ($action === 'cancel_order') {
    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) respond(false, 'Invalid order ID.');

    $stmt = $pdo->prepare("SELECT status, delivery_id FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();

    if (!$order) {
        respond(false, 'Order not found.');
    }

    // Allow cancel only if not yet physically picked up
    $cancellableStatuses = ['pending', 'assigned'];
    if (!in_array($order['status'], $cancellableStatuses)) {
        respond(false, 'Cannot cancel — your order has already been picked up by the delivery partner.');
    }

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE orders SET status = 'cancelled', delivery_id = NULL WHERE id = ?")->execute([$orderId]);

        // If a delivery partner was assigned, release their load counter
        if ($order['delivery_id']) {
            $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$order['delivery_id']]);
        }

        // Remove active payment dues to prevent blocking new orders
        try { $pdo->prepare("DELETE FROM payments WHERE order_id = ? AND status = 'remaining'")->execute([$orderId]); } catch (\Exception $e) {}

        // Release coupon usage
        try { $pdo->prepare("DELETE FROM coupon_usages WHERE order_id = ?")->execute([$orderId]); } catch (\Exception $e) {}

        $pdo->commit();
        respond(true, 'Your order has been successfully cancelled.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- FETCH PAYMENTS ---
if ($action === 'get_payments') {
    $type = $data['type'] ?? 'remaining'; // 'remaining' or 'completed'

    $stmt = $pdo->prepare("SELECT p.*, o.total_amount, o.status AS order_status, o.invoice_id FROM payments p JOIN orders o ON p.order_id = o.id WHERE p.user_id = ? AND p.status = ? ORDER BY p.created_at DESC");
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

// --- GET QR CONFIG ---
if ($action === 'get_qr_config') {
    $stmt = $pdo->prepare("SELECT qr_code_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $hash = $stmt->fetchColumn();
    
    if (!$hash) {
        $hash = bin2hex(random_bytes(32));
        $pdo->prepare("UPDATE users SET qr_code_hash = ? WHERE id = ?")->execute([$hash, $userId]);
    }
    
    respond(true, 'QR config retrieved', ['qr_code_hash' => $hash]);
}

// --- SAVE AUTO ORDER SCHEDULE ---
if ($action === 'save_auto_order') {
    $freq = $data['frequency'] ?? 'NONE';
    if (!in_array($freq, ['NONE', 'MONDAYS'])) {
        respond(false, 'Invalid schedule frequency.');
    }
    
    $nextDate = null;
    if ($freq === 'MONDAYS') {
        $nextDate = date('Y-m-d', strtotime('next monday'));
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET auto_order_frequency = ?, auto_order_next_date = ? WHERE id = ?");
        $stmt->execute([$freq, $nextDate, $userId]);
        respond(true, "Auto-pickup schedule updated to: " . str_replace('_', ' ', $freq));
    } catch (\Exception $e) {
        respond(false, 'Failed to update schedule: ' . $e->getMessage());
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
    
    respond(true, 'Pay Later plan request submitted to admin for approval!');
}

respond(false, 'Invalid action specified in api/orders.php');
