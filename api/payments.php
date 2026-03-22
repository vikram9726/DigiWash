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

            // 1. Update order status
            $stmt = $pdo->prepare("UPDATE orders SET payment_status = 'completed', updated_at = NOW() WHERE id = ? AND user_id = ?");
            $stmt->execute([$localOrderId, $userId]);

            // 2. Update payment record
            $stmt = $pdo->prepare("UPDATE payments SET status = 'completed', rzp_payment_id = ?, updated_at = NOW() WHERE order_id = ?");
            $stmt->execute([$rzpPaymentId, $localOrderId]);

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
