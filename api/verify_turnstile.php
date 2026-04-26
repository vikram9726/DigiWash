<?php
/**
 * verify_turnstile.php
 * Validates Cloudflare Turnstile token + enforces OTP rate limits.
 * Called BEFORE the frontend triggers Firebase OTP.
 */
require_once '../config.php';
header('Content-Type: application/json');

// Only POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$token  = $input['cf_token'] ?? '';
$phone  = preg_replace('/[^0-9]/', '', $input['phone'] ?? '');
$ip     = $_SERVER['HTTP_CF_CONNECTING_IP']
       ?? $_SERVER['HTTP_X_FORWARDED_FOR']
       ?? $_SERVER['REMOTE_ADDR']
       ?? '';
// Normalise IP (take first in case of comma-separated list)
$ip = trim(explode(',', $ip)[0]);

// ── 1. Turnstile token must be present ──────────────────────────────────────
if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Bot verification token missing.']);
    exit;
}

// ── 2. Verify with Cloudflare ────────────────────────────────────────────────
$secret = getenv('CF_TURNSTILE_SECRET');
if (empty($secret)) {
    // Secret not configured — fail safe
    error_log('CF_TURNSTILE_SECRET not set in .env');
    echo json_encode(['success' => false, 'message' => 'Security configuration error. Contact admin.']);
    exit;
}

$ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'secret'   => $secret,
        'response' => $token,
        'remoteip' => $ip,
    ]),
    CURLOPT_TIMEOUT        => 8,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
]);
$cfResponse = curl_exec($ch);
$curlError  = curl_error($ch);
curl_close($ch);

if ($curlError || !$cfResponse) {
    error_log('Turnstile curl error: ' . $curlError);
    echo json_encode(['success' => false, 'message' => 'Bot verification failed. Please retry.']);
    exit;
}

$cfData = json_decode($cfResponse, true);
if (!($cfData['success'] ?? false)) {
    $codes = implode(', ', $cfData['error-codes'] ?? []);
    error_log('Turnstile failed: ' . $codes);
    echo json_encode(['success' => false, 'message' => 'Bot verification failed. Please retry.']);
    exit;
}

// ── 3. Phone validation ──────────────────────────────────────────────────────
if (strlen($phone) !== 10 || !preg_match('/^[6-9]\d{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Invalid phone number.']);
    exit;
}

// ── 4. Rate limiting ─────────────────────────────────────────────────────────
try {
    // a) Per-phone: max 3 OTP sends per 10 minutes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM otp_requests
        WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
    ");
    $stmt->execute([$phone]);
    if ((int)$stmt->fetchColumn() >= 3) {
        echo json_encode(['success' => false, 'message' => 'Too many OTP requests for this number. Try again in 10 minutes.']);
        exit;
    }

    // b) Per-IP: max 5 OTP sends per 15 minutes
    $stmt2 = $pdo->prepare("
        SELECT COUNT(*) FROM otp_requests
        WHERE ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt2->execute([$ip]);
    if ((int)$stmt2->fetchColumn() >= 5) {
        echo json_encode(['success' => false, 'message' => 'Too many attempts from your network. Try again later.']);
        exit;
    }

    // ── 5. Log the request ──────────────────────────────────────────────────
    // otp_hash and expires_at are placeholders here (Firebase handles actual OTP delivery).
    // We store a dummy hash so the rate limit row exists.
    $dummyHash = hash('sha256', $phone . $ip . microtime());
    $stmt3 = $pdo->prepare("
        INSERT INTO otp_requests (phone, otp_hash, expires_at, ip_address)
        VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?)
    ");
    $stmt3->execute([$phone, $dummyHash, $ip]);

} catch (\Exception $e) {
    // Table may not exist yet on fresh install — non-fatal, let the request through
    error_log('OTP rate limit DB error: ' . $e->getMessage());
}

// ── 6. All checks passed ─────────────────────────────────────────────────────
echo json_encode(['success' => true, 'message' => 'Verified. Proceed with OTP.']);
