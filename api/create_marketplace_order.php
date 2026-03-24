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
$userId = (int)$_SESSION['user_id'];

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
$serverCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($serverCsrf) || !hash_equals($serverCsrf, $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

if ($action === 'create_order') {
    $cartItems     = $data['items'] ?? []; // [{product_id, quantity}]
    $paymentType   = $data['payment_type'] ?? 'online'; // 'online' or 'credit'

    if (empty($cartItems)) {
        respond(false, 'Cart is empty.');
    }

    if (!in_array($paymentType, ['online', 'credit'])) {
        respond(false, 'Invalid payment type.');
    }

    // 1. Check User Profile & Market
    $stmt = $pdo->prepare("SELECT market_id, lat, lng FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (empty($user['market_id']) || empty($user['lat']) || empty($user['lng'])) {
        respond(false, 'Please complete your profile details (location) to place marketplace orders.');
    }

    // 2. Pay Later Eligibility
    if ($paymentType === 'credit') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
        $stmt->execute([$userId]);
        $completedLaundryOrders = (int)$stmt->fetchColumn();

        if ($completedLaundryOrders < 4) {
            respond(false, 'Pay Later (Credit) is only available after completing 4 laundry orders.');
        }
    }

    try {
        $pdo->beginTransaction();

        $totalAmount = 0.0;
        $resolvedItems = [];

        // 3. Stock Check and Total Calc
        foreach ($cartItems as $item) {
            $pId = (int)($item['product_id'] ?? 0);
            $qty = max(1, (int)($item['quantity'] ?? 1));
            
            $stmt = $pdo->prepare("SELECT id, price, stock, status, name FROM marketplace_products WHERE id = ? FOR UPDATE");
            $stmt->execute([$pId]);
            $product = $stmt->fetch();

            if (!$product || $product['status'] !== 'active') {
                throw new Exception("Product ID $pId is not available.");
            }
            if ($product['stock'] < $qty) {
                throw new Exception("Insufficient stock for {$product['name']}. Available: {$product['stock']}");
            }

            $lineTotal = $product['price'] * $qty;
            $totalAmount += $lineTotal;
            $resolvedItems[] = [
                'product_id' => $pId,
                'price'      => $product['price'],
                'quantity'   => $qty
            ];
        }

        // 4. Wallet check for Credit
        if ($paymentType === 'credit') {
            $stmt = $pdo->prepare("SELECT credit_limit, used_credit FROM user_wallet WHERE user_id = ? FOR UPDATE");
            $stmt->execute([$userId]);
            $wallet = $stmt->fetch();

            if (!$wallet) {
                // Initialize wallet
                $pdo->prepare("INSERT INTO user_wallet (user_id, credit_limit, used_credit) VALUES (?, 2000.00, 0.00)")->execute([$userId]);
                $limit = 2000.00;
                $used = 0.00;
            } else {
                $limit = (float)$wallet['credit_limit'];
                $used  = (float)$wallet['used_credit'];
            }

            if (($used + $totalAmount) > $limit) {
                $available = max(0, $limit - $used);
                throw new Exception("Credit limit exceeded. You have ₹$available available credit.");
            }
        }

        // 5. Payment details (mocking razorpay for 'online')
        $paymentStatus = ($paymentType === 'online') ? 'paid' : 'pending';

        if ($paymentType === 'online') {
            $r_payId = $data['razorpay_payment_id'] ?? '';
            if (!$r_payId) {
                throw new Exception("Missing Razorpay payment ID for online payment.");
            }
            // Skipping strict razorpay signature check for MVP as it's similar to laundry's fallback logic
        }

        // 6. Delivery Assignment
        $deliveryId = null;
        $orderStatus = 'placed';
        
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
            $pdo->prepare("UPDATE users SET current_orders = current_orders + 1 WHERE id = ?")->execute([$deliveryId]);
        }

        // 7. Insert Order
        $stmt = $pdo->prepare("INSERT INTO marketplace_orders (user_id, delivery_id, total_amount, payment_type, payment_status, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $deliveryId, $totalAmount, $paymentType, $paymentStatus, $orderStatus]);
        $orderId = $pdo->lastInsertId();

        // 8. Insert Items and Reduce Stock
        $itmStmt = $pdo->prepare("INSERT INTO marketplace_order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        $stkStmt = $pdo->prepare("UPDATE marketplace_products SET stock = stock - ? WHERE id = ?");

        foreach ($resolvedItems as $it) {
            $itmStmt->execute([$orderId, $it['product_id'], $it['quantity'], $it['price']]);
            $stkStmt->execute([$it['quantity'], $it['product_id']]);
        }

        // 9. Update Wallet
        if ($paymentType === 'credit') {
            $pdo->prepare("UPDATE user_wallet SET used_credit = used_credit + ? WHERE user_id = ?")->execute([$totalAmount, $userId]);
        }

        $pdo->commit();

        respond(true, 'Marketplace order placed successfully.', ['order_id' => $orderId, 'status' => $orderStatus]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, $e->getMessage());
    }
}

// Check eligibility utility for frontend
if ($action === 'check_eligibility') {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
    $stmt->execute([$userId]);
    $completedLaundryOrders = (int)$stmt->fetchColumn();

    $isEligible = ($completedLaundryOrders >= 4);
    
    $wallet = ['credit_limit' => 2000, 'used_credit' => 0];
    if ($isEligible) {
        $stmt = $pdo->prepare("SELECT credit_limit, used_credit FROM user_wallet WHERE user_id = ?");
        $stmt->execute([$userId]);
        $w = $stmt->fetch();
        if ($w) {
            $wallet['credit_limit'] = (float)$w['credit_limit'];
            $wallet['used_credit'] = (float)$w['used_credit'];
        }
    }

    respond(true, 'Eligibility fetched', [
        'is_eligible' => $isEligible, 
        'laundry_orders' => $completedLaundryOrders,
        'wallet' => $wallet
    ]);
}

respond(false, 'Unknown action.');
