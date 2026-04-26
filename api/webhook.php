<?php
/**
 * DigiWash — Razorpay Webhook Listener
 * Endpoint: /api/webhook.php
 *
 * Handles:
 *   - refund.processed  → Updates `arn` in `refunds` table
 *
 * Configure on Razorpay Dashboard:
 *   URL: https://yourdomain.com/api/webhook.php
 *   Active Events: refund.processed
 *   Secret: Set RAZORPAY_WEBHOOK_SECRET in .env
 */

require_once '../config.php';
header('Content-Type: application/json');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');

// ── Verify Webhook Signature ─────────────────────────────────────────────────
$webhookSecret = getenv('RAZORPAY_WEBHOOK_SECRET') ?: '';
if ($webhookSecret) {
    $receivedSig = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';
    $expectedSig = hash_hmac('sha256', $rawBody, $webhookSecret);
    if (!hash_equals($expectedSig, $receivedSig)) {
        http_response_code(401);
        error_log("Razorpay Webhook: Invalid signature received.");
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }
}

$payload = json_decode($rawBody, true);
$event   = $payload['event'] ?? '';

// ── Handle refund.processed ──────────────────────────────────────────────────
if ($event === 'refund.processed') {
    $refundData = $payload['payload']['refund']['entity'] ?? null;
    if (!$refundData) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing refund entity']);
        exit;
    }

    $rzpRefundId = $refundData['id'] ?? '';
    $arn         = $refundData['acquirer_data']['arn'] ?? null;
    $status      = $refundData['status'] ?? '';

    error_log("Razorpay Webhook: refund.processed — rzp_refund_id=$rzpRefundId, arn=$arn, status=$status");

    if ($rzpRefundId) {
        try {
            // Update ARN and status in refunds table
            if ($arn) {
                $pdo->prepare("UPDATE refunds SET arn = ?, status = 'processed' WHERE rzp_refund_id = ?")
                    ->execute([$arn, $rzpRefundId]);
            } else {
                $pdo->prepare("UPDATE refunds SET status = 'processed' WHERE rzp_refund_id = ?")
                    ->execute([$rzpRefundId]);
            }

            // Notify the user if ARN just arrived
            if ($arn) {
                $stmt = $pdo->prepare("SELECT user_id, order_id, refund_amount FROM refunds WHERE rzp_refund_id = ?");
                $stmt->execute([$rzpRefundId]);
                $refund = $stmt->fetch();
                if ($refund) {
                    $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, 'Refund Confirmed 🏦', ?)")
                        ->execute([
                            $refund['user_id'],
                            "Your refund of ₹{$refund['refund_amount']} for Order #{$refund['order_id']} has been confirmed by the bank. ARN: $arn"
                        ]);
                    sendPushNotification($pdo, $refund['user_id'],
                        'Refund Confirmed 🏦',
                        "Refund of ₹{$refund['refund_amount']} confirmed. ARN: $arn"
                    );
                }
            }

            http_response_code(200);
            echo json_encode(['success' => true, 'message' => "ARN updated: $arn"]);

        } catch (\Exception $e) {
            error_log("Razorpay Webhook DB Error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'DB error: ' . $e->getMessage()]);
        }

        exit;
    }
}

// ── payment.captured (optional future hook) ──────────────────────────────────
if ($event === 'payment.captured') {
    // Reserved for future use — e.g. auto-mark payment as completed
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'Acknowledged']);
    exit;
}

// All other events — acknowledge but ignore
http_response_code(200);
echo json_encode(['success' => true, 'message' => "Event '$event' acknowledged and ignored"]);
