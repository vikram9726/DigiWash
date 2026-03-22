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
$action = $data['action'] ?? '';
$userId = $_SESSION['user_id'];
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? '';

// CSRF Protection
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

$razorpayKeyId = getenv('RAZORPAY_KEY_ID');
$razorpayKeySecret = getenv('RAZORPAY_KEY_SECRET');

if (empty($razorpayKeyId) || strpos($razorpayKeyId, 'replace_this') !== false) {
    respond(false, 'Razorpay is not configured on the server yet.');
}

// --- CREATE RAZORPAY ORDER ---
if ($action === 'create_rzp_order') {
    $orderId = $data['order_id'] ?? 0;
    
    // Fetch order details to get the amount
    $stmt = $pdo->prepare("SELECT total_amount, status, payment_status FROM orders WHERE id = ? AND user_id = ?");
    $stmt->execute([$orderId, $userId]);
    $order = $stmt->fetch();

    if (!$order) respond(false, 'Order not found.');
    if ($order['payment_status'] === 'completed') respond(false, 'Order already paid.');

    $amountInPaise = round($order['total_amount'] * 100);

    // Create order in Razorpay via REST API
    $api_url = "https://api.razorpay.com/v1/orders";
    $auth = base64_encode("$razorpayKeyId:$razorpayKeySecret");

    $payload = [
        "amount" => $amountInPaise,
        "currency" => "INR",
        "receipt" => "rcpt_" . $orderId,
        "notes" => ["digiwash_order_id" => $orderId]
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Basic $auth"
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resData = json_decode($response, true);

    if ($httpCode === 200 && isset($resData['id'])) {
        try {
            // Save rzp_order_id to DB for tracking purpose
            $stmt = $pdo->prepare("UPDATE payments SET rzp_order_id = ? WHERE order_id = ?");
            $stmt->execute([$resData['id'], $orderId]);
        } catch (\Exception $e) { }

        respond(true, 'Razorpay order created', ['rzp_order_id' => $resData['id'], 'amount' => $amountInPaise, 'key' => $razorpayKeyId]);
    } else {
        error_log("Razorpay Order Error: " . $response);
        respond(false, 'Failed to initiate gateway. ' . ($resData['error']['description'] ?? ''));
    }
}

// --- CREATE BULK RAZORPAY ORDER ---
if ($action === 'create_bulk_rzp_order') {
    $stmt = $pdo->prepare("
        SELECT p.order_id, p.amount 
        FROM payments p 
        JOIN orders o ON p.order_id = o.id 
        WHERE p.user_id = ? AND p.status = 'remaining' AND p.payment_mode LIKE 'PAY_LATER%' AND o.status != 'cancelled'
    ");
    $stmt->execute([$userId]);
    $pendingPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($pendingPayments)) {
        respond(false, 'No pending Pay Later dues found to settle.');
    }

    $totalAmount = 0;
    foreach ($pendingPayments as $p) {
        $totalAmount += (float)$p['amount'];
    }

    $amountInPaise = round($totalAmount * 100);

    $api_url = "https://api.razorpay.com/v1/orders";
    $auth = base64_encode("$razorpayKeyId:$razorpayKeySecret");
    $payload = [
        "amount" => $amountInPaise,
        "currency" => "INR",
        "receipt" => "rcpt_bulk_" . $userId . "_" . time(),
        "notes" => ["digiwash_bulk_pay" => "true"]
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Basic $auth"]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resData = json_decode($response, true);

    if ($httpCode === 200 && isset($resData['id'])) {
        try {
            $pdo->beginTransaction();
            $rzp_id = $resData['id'];
            foreach ($pendingPayments as $p) {
                $pdo->prepare("UPDATE payments SET rzp_order_id = ? WHERE order_id = ? AND status = 'remaining'")
                    ->execute([$rzp_id, $p['order_id']]);
            }
            $pdo->commit();
            respond(true, 'Razorpay bulk order created', ['rzp_order_id' => $rzp_id, 'amount' => $amountInPaise, 'key' => $razorpayKeyId]);
        } catch (\Exception $e) { $pdo->rollBack(); respond(false, 'DB Error: ' . $e->getMessage()); }
    } else {
        respond(false, 'Failed to initiate gateway.');
    }
}

// --- VERIFY PAYMENT SIGNATURE ---
if ($action === 'verify_payment') {
    $rzpPaymentId = $data['razorpay_payment_id'] ?? '';
    $rzpOrderId = $data['razorpay_order_id'] ?? '';
    $rzpSignature = $data['razorpay_signature'] ?? '';
    $localOrderId = $data['local_order_id'] ?? 0;

    if (empty($rzpPaymentId) || empty($rzpSignature)) {
        respond(false, 'Payment verification failed. Missing parameters.');
    }

    // Manual signature verification (as per Razorpay docs)
    // signature = hmac_sha256(order_id + "|" + payment_id, secret)
    $expectedSignature = hash_hmac('sha256', $rzpOrderId . "|" . $rzpPaymentId, $razorpayKeySecret);

    if (hash_equals($expectedSignature, $rzpSignature)) {
        try {
            $pdo->beginTransaction();

            if ($localOrderId === 'BULK') {
                $stmt = $pdo->prepare("SELECT order_id FROM payments WHERE rzp_order_id = ? AND user_id = ? AND status = 'remaining'");
                $stmt->execute([$rzpOrderId, $userId]);
                $orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($orderIds)) {
                    $in = str_repeat('?,', count($orderIds) - 1) . '?';
                    $orderParams = array_merge($orderIds, [$userId]);
                    
                    // Update orders matching the returned order IDs
                    $stmtOrder = $pdo->prepare("UPDATE orders SET payment_status = 'completed', updated_at = NOW() WHERE id IN ($in) AND user_id = ?");
                    $stmtOrder->execute($orderParams);

                    // Update payments universally matching the RZP order ID
                    $stmtPayment = $pdo->prepare("UPDATE payments SET status = 'completed', rzp_payment_id = ?, updated_at = NOW() WHERE rzp_order_id = ?");
                    $stmtPayment->execute([$rzpPaymentId, $rzpOrderId]);
                }
            } else {
                // 1. Update single order status
                $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'completed', updated_at = NOW() WHERE id = ? AND user_id = ?");
                $stmt->execute([$localOrderId, $userId]);

                // 2. Update single payment record
                $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', rzp_payment_id = ?, updated_at = NOW() WHERE order_id = ?");
                $stmt->execute([$rzpPaymentId, $localOrderId]);
            }

            $pdo->commit();
            respond(true, 'Payment verified and captured successfully!');
        } catch (\Exception $e) {
            $pdo->rollBack();
            respond(false, 'Database Error: ' . $e->getMessage());
        }
    } else {
        respond(false, 'Invalid payment signature. Security alert.');
    }
}

respond(false, 'Invalid action.');
