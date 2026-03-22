<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');

function respond($s, $m, $d=[]) { echo json_encode(array_merge(['success'=>$s,'message'=>$m], $d)); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request method.');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

$userId = $_SESSION['user_id'] ?? null;
$adminId = $_SESSION['admin_id'] ?? null;

if (!$userId && !$adminId) respond(false, 'Unauthorized.');

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? '';
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

// ── ADMIN: Create Invoice ──
if ($action === 'create_invoice' && $adminId) {
    if (empty($data['user_id']) || empty($data['amount']) || empty($data['description'])) {
        respond(false, 'Please provide User, Amount, and Description.');
    }
    $invNo = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));
    $stmt = $pdo->prepare("INSERT INTO invoices (user_id, invoice_no, description, amount) VALUES (?, ?, ?, ?)");
    try {
        $stmt->execute([$data['user_id'], $invNo, $data['description'], $data['amount']]);
        respond(true, 'Invoice ' . $invNo . ' generated successfully!');
    } catch (Exception $e) {
        respond(false, 'DB Error: ' . $e->getMessage());
    }
}

// ── GET INVOICES ──
if ($action === 'get_invoices') {
    if ($adminId) {
        $stmt = $pdo->prepare("SELECT i.*, u.name as user_name, u.phone FROM invoices i JOIN users u ON i.user_id = u.id ORDER BY i.created_at DESC");
        $stmt->execute();
        respond(true, '', ['invoices' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    } elseif ($userId) {
        $stmt = $pdo->prepare("SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        respond(true, '', ['invoices' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}

// ── USER: Init Payment ──
if ($action === 'initiate_payment' && $userId) {
    $invId = $data['invoice_id'] ?? 0;
    
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ? AND status = 'unpaid'");
    $stmt->execute([$invId, $userId]);
    $inv = $stmt->fetch();
    
    if (!$inv) respond(false, 'Invoice not found or already paid.');

    $rzpId = getenv('RAZORPAY_KEY_ID');
    $rzpSec = getenv('RAZORPAY_KEY_SECRET');
    if (!$rzpId || strpos($rzpId, 'replace_this') !== false) respond(false, 'Razorpay is not configured on your server.');

    $amountInPaise = round($inv['amount'] * 100);

    $api_url = "https://api.razorpay.com/v1/orders";
    $auth = base64_encode("$rzpId:$rzpSec");
    $payload = [
        "amount" => $amountInPaise,
        "currency" => "INR",
        "receipt" => $inv['invoice_no'],
        "notes" => ["digiwash_invoice_id" => $invId]
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
        $pdo->prepare("UPDATE invoices SET rzp_order_id = ? WHERE id = ?")->execute([$resData['id'], $invId]);
        respond(true, 'Payment Gateway Ready', ['rzp_order_id' => $resData['id'], 'amount' => $amountInPaise, 'key' => $rzpId]);
    } else {
        respond(false, 'Failed to launch gateway: ' . ($resData['error']['description'] ?? ''));
    }
}

// ── USER: Verify Payment ──
if ($action === 'verify_payment' && $userId) {
    $rzpPaymentId = $data['razorpay_payment_id'] ?? '';
    $rzpOrderId = $data['razorpay_order_id'] ?? '';
    $rzpSignature = $data['razorpay_signature'] ?? '';
    $invId = $data['invoice_id'] ?? 0;

    $rzpSec = getenv('RAZORPAY_KEY_SECRET');
    $expected = hash_hmac('sha256', $rzpOrderId . "|" . $rzpPaymentId, $rzpSec);

    if (hash_equals($expected, $rzpSignature)) {
        try {
            $pdo->prepare("UPDATE invoices SET status = 'paid', rzp_payment_id = ?, updated_at = NOW() WHERE id = ? AND rzp_order_id = ?")
                ->execute([$rzpPaymentId, $invId, $rzpOrderId]);
            respond(true, 'Invoice Paid Successfully!');
        } catch (Exception $e) {
            respond(false, 'Database Error: ' . $e->getMessage());
        }
    } else {
        respond(false, 'Payment signature verification failed.');
    }
}

respond(false, 'Invalid action specified in Invoices API.');
