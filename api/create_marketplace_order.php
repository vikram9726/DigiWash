<?php
require_once '../config.php';
header('Content-Type: application/json');

function respond($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.');
if (!isset($_SESSION['user_id'])) respond(false, 'Unauthorized. Please log in.');

$body   = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $body['action'] ?? '';
$userId = (int)$_SESSION['user_id'];

$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token']
    ?? $headers['x-csrf-token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? (is_array($data ?? null) ? ($data['csrf_token'] ?? '') : '')
    ?? (is_array($body ?? null) ? ($body['csrf_token'] ?? '') : '')
    ?? $_POST['csrf_token']
    ?? '';
$serverCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($serverCsrf) || !hash_equals($serverCsrf, $csrfToken)) respond(false, 'Invalid CSRF token.');

$RZP_KEY_ID     = getenv('RAZORPAY_KEY_ID')     ?: '';
$RZP_KEY_SECRET = getenv('RAZORPAY_KEY_SECRET') ?: '';

// ─── CREATE RAZORPAY ORDER (called BEFORE checkout) ─────────────────────────
if ($action === 'create_razorpay_order') {
    $amountPaise = (int)round((float)($body['amount'] ?? 0) * 100);
    if ($amountPaise < 100) respond(false, 'Amount too small.');

    $payload = json_encode([
        'amount'   => $amountPaise,
        'currency' => 'INR',
        'receipt'  => 'mkt_' . $userId . '_' . time(),
    ]);

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_USERPWD        => "$RZP_KEY_ID:$RZP_KEY_SECRET",
        CURLOPT_TIMEOUT        => 15,
    ]);
    $res  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch);

    if ($code !== 200) respond(false, 'Razorpay error. Please try again.');
    $rzp = json_decode($res, true);
    respond(true, 'Order created', ['rzp_order_id' => $rzp['id'], 'key' => $RZP_KEY_ID, 'amount' => $amountPaise]);
}

// ─── CREATE ORDER (after payment verified or credit) ─────────────────────────
if ($action === 'create_order') {
    $cartItems        = $body['items'] ?? [];
    $paymentType      = $body['payment_type'] ?? 'online';
    $rzpPaymentId     = $body['razorpay_payment_id'] ?? '';
    $rzpOrderId       = $body['razorpay_order_id']   ?? '';
    $rzpSignature     = $body['razorpay_signature']   ?? '';

    if (empty($cartItems)) respond(false, 'Cart is empty.');
    if (!in_array($paymentType, ['online', 'credit'])) respond(false, 'Invalid payment type.');

    // Verify Razorpay signature for online payments
    if ($paymentType === 'online') {
        if (!$rzpPaymentId || !$rzpOrderId || !$rzpSignature) {
            respond(false, 'Missing payment verification data.');
        }
        $expectedSig = hash_hmac('sha256', $rzpOrderId . '|' . $rzpPaymentId, $RZP_KEY_SECRET);
        if (!hash_equals($expectedSig, $rzpSignature)) {
            respond(false, 'Payment verification failed. Possible tampering detected.');
        }
    }

    // User profile check
    $stmt = $pdo->prepare("SELECT name, phone, shop_address, market_id, lat, lng FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (empty($user['market_id'])) respond(false, 'Please complete your profile to place marketplace orders.');

    // Pay Later eligibility
    if ($paymentType === 'credit') {
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
        $cnt->execute([$userId]);
        if ((int)$cnt->fetchColumn() < 4) respond(false, 'Pay Later is only available after 4 delivered laundry orders.');
    }

    try {
        $pdo->beginTransaction();

        $totalAmount   = 0.0;
        $resolvedItems = [];

        foreach ($cartItems as $item) {
            $pId         = (int)($item['product_id'] ?? 0);
            $qty         = max(1, (int)($item['quantity'] ?? 1));
            $clientPrice = (float)($item['price'] ?? 0);   // user-calculated price (per-meter × length)
            $widthLabel  = $item['width_label']   ?? null;
            $lenM        = isset($item['length_meters']) ? (float)$item['length_meters'] : null;

            $stmt = $pdo->prepare("SELECT id, price, stock, status, name FROM marketplace_products WHERE id = ? FOR UPDATE");
            $stmt->execute([$pId]);
            $product = $stmt->fetch();

            if (!$product || $product['status'] !== 'active') throw new Exception("Product #$pId not available.");
            if ($product['stock'] < $qty) throw new Exception("Insufficient stock for {$product['name']}.");

            // If per-meter product, use client-calculated price (already = pricePerM × length)
            $linePrice = ($widthLabel && $lenM > 0) ? $clientPrice : ($product['price'] * $qty);
            $totalAmount += $linePrice;

            $resolvedItems[] = [
                'product_id'   => $pId,
                'price'        => $linePrice,
                'quantity'     => $qty,
                'width_label'  => $widthLabel,
                'length_meters'=> $lenM,
            ];
        }

        // Order count check for Pay Later
        if ($paymentType === 'credit') {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE user_id = ? AND payment_type = 'credit' AND payment_status = 'pending' FOR UPDATE");
            $stmt->execute([$userId]);
            $unpaid = (int)$stmt->fetchColumn();
            if ($unpaid >= 4) {
                throw new Exception("You have exhausted your 4 Pay Later orders. Please pay your pending dues to unlock more.");
            }
        }

        $paymentStatus = ($paymentType === 'online') ? 'paid' : 'pending';

        // Auto-assign delivery partner
        $deliveryId  = null;
        $orderStatus = 'placed';
        $dbStmt = $pdo->prepare("SELECT id FROM users WHERE role='delivery' AND is_online=1 AND market_id=? ORDER BY current_orders ASC, id ASC LIMIT 1");
        $dbStmt->execute([$user['market_id']]);
        $partner = $dbStmt->fetch();
        if ($partner) {
            $deliveryId  = $partner['id'];
            $orderStatus = 'assigned';
            $pdo->prepare("UPDATE users SET current_orders = current_orders + 1 WHERE id=?")->execute([$deliveryId]);
        }

        // Insert order
        $stmt = $pdo->prepare("INSERT INTO marketplace_orders (user_id, delivery_id, total_amount, payment_type, payment_status, status, razorpay_order_id, razorpay_payment_id) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([$userId, $deliveryId, $totalAmount, $paymentType, $paymentStatus, $orderStatus, $rzpOrderId ?: null, $rzpPaymentId ?: null]);
        $orderId = (int)$pdo->lastInsertId();

        // Insert items with width/length
        $itmStmt = $pdo->prepare("INSERT INTO marketplace_order_items (order_id, product_id, quantity, price, width_label, length_meters) VALUES (?,?,?,?,?,?)");
        $stkStmt = $pdo->prepare("UPDATE marketplace_products SET stock = stock - ? WHERE id = ?");
        foreach ($resolvedItems as $it) {
            $itmStmt->execute([$orderId, $it['product_id'], $it['quantity'], $it['price'], $it['width_label'], $it['length_meters']]);
            $stkStmt->execute([$it['quantity'], $it['product_id']]);
        }

        // No user_wallet to update

        // Generate invoice number and store
        $invoiceNo = 'MKT-' . strtoupper(substr(md5($orderId . time()), 0, 8));
        $pdo->prepare("UPDATE marketplace_orders SET invoice_no = ? WHERE id = ?")->execute([$invoiceNo, $orderId]);

        $pdo->commit();

        // Push notification
        try {
            sendPushNotification($pdo, $userId, '🛍️ Order Placed!', "Your DigiMarket order #{$orderId} (₹" . number_format($totalAmount,2) . ") has been placed.");
        } catch (\Throwable $t) {}

        respond(true, 'Marketplace order placed!', [
            'order_id'    => $orderId,
            'status'      => $orderStatus,
            'invoice_no'  => $invoiceNo,
            'total_amount'=> $totalAmount
        ]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, $e->getMessage());
    }
}

// ─── CHECK ELIGIBILITY ───────────────────────────────────────────────────────
if ($action === 'check_eligibility') {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
    $cnt->execute([$userId]);
    $done      = (int)$cnt->fetchColumn();
    $eligible  = $done >= 4;
    $availableOrders = 0;
    if ($eligible) {
        $w = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE user_id = ? AND payment_type = 'credit' AND payment_status = 'pending'");
        $w->execute([$userId]);
        $unpaidCreditOrders = (int)$w->fetchColumn();
        $availableOrders = max(0, 4 - $unpaidCreditOrders);
    }
    respond(true, 'OK', ['is_eligible' => $eligible, 'laundry_orders' => $done, 'available_orders' => $availableOrders]);
}

// ─── GET MARKETPLACE CREDIT DUES (for combined billing in DigiWash) ──────────
if ($action === 'get_credit_dues') {
    $stmt = $pdo->prepare("
        SELECT mo.id, mo.total_amount, mo.payment_type, mo.payment_status, mo.status,
               mo.created_at, mo.invoice_no
        FROM marketplace_orders mo
        WHERE mo.user_id = ? AND mo.payment_type = 'credit' AND mo.payment_status = 'pending' AND mo.status != 'cancelled'
        ORDER BY mo.created_at DESC
    ");
    $stmt->execute([$userId]);
    $dues = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = array_sum(array_column($dues, 'total_amount'));
    respond(true, 'OK', ['dues' => $dues, 'total_due' => (float)$total]);
}

respond(false, 'Unknown action.');
