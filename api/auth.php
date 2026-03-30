<?php
require_once '../config.php';

header('Content-Type: application/json');

// Helper function to return JSON and exit
function respond($success, $message, $data = [])
{
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Ensure it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// CSRF Protection Check for authenticated actions (like logout)
if ($action !== 'firebase_login' && $action !== 'login' && $action !== 'dummy_login') {
    $headers = getallheaders();
    $csrfToken = $headers['X-CSRF-Token'] ?? (is_array($data) ? ($data['csrf_token'] ?? '') : '') ?? $_POST['csrf_token'] ?? '';

    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        respond(false, 'Invalid CSRF token. Request denied.');
    }
}

if ($action === 'firebase_login') {
    $idToken = $data['idToken'] ?? '';
    if (empty($idToken)) {
        respond(false, 'Missing authentication token.');
    }

    // Verify token using Google Identity Toolkit REST API
    $apiKey = getenv('FIREBASE_API_KEY');
    if (empty($apiKey) || $apiKey === 'YOUR_FIREBASE_API_KEY') {
        respond(false, 'Server missing Firebase Configuration.');
    }

    $url = "https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=" . $apiKey;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
        respond(false, 'Invalid or expired Firebase token.');
    }

    $fbData = json_decode($response, true);
    if (!isset($fbData['users'][0])) {
        respond(false, 'Firebase Identity Verification failed.');
    }

    $fbUser = $fbData['users'][0];
    $uid = $fbUser['localId'];

    // Some google accounts don't have phone numbers tied, so we fallback to parsing the payload or standardizing later
    $phone = isset($fbUser['phoneNumber']) ? 
        str_replace('+', '', $fbUser['phoneNumber']) : // Strip + if present
        htmlspecialchars(strip_tags($data['phone'] ?? ''), ENT_QUOTES, 'UTF-8'); // Fallback to provided payload

    $email = $fbUser['email'] ?? filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $name = $fbUser['displayName'] ?? htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($phone) && empty($email)) {
        respond(false, 'Unable to extract phone or email to tie account. Please try again.');
    }

    // Check if user exists in the single users table
    $stmt = $pdo->prepare("SELECT id, role, name, phone, firebase_uid FROM users WHERE firebase_uid = ? OR (phone = ? AND phone != 'NOPHONEMATCH') LIMIT 1");
    $stmt->execute([$uid, $phone ?: 'NOPHONEMATCH']);
    $user = $stmt->fetch();

    $isNewUser = false;

    if (!$user) {
        // Create new Customer by default
        $insert = $pdo->prepare("INSERT INTO users (firebase_uid, phone, email, name, role) VALUES (?, ?, ?, ?, 'customer')");
        try {
            $insertPhone = $phone ?: ('GOOGLE_PENDING_' . substr($uid, 0, 5));
            $insert->execute([$uid, $insertPhone, $email, $name]);
            $userId = $pdo->lastInsertId();
            $role = 'customer';
            $isNewUser = true;

            $qrHash = hash('sha256', $userId . $uid . bin2hex(random_bytes(10)));
            $pdo->prepare("UPDATE users SET qr_code_hash = ? WHERE id = ?")->execute([$qrHash, $userId]);
        }
        catch (\Exception $e) {
            respond(false, 'Failed to create account. ' . $e->getMessage());
        }
    }
    else {
        $userId = $user['id'];
        $role = $user['role'];

        // Update firebase_uid if missing
        if (empty($user['firebase_uid'])) {
            $pdo->prepare("UPDATE users SET firebase_uid = ? WHERE id = ?")->execute([$uid, $userId]);
        }
        $phone = $user['phone'];
    }

    // ---------------------------------
    // SECURE SESSION CREATION
    // ---------------------------------
    session_regenerate_id(true); // Prevents session fixation

    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['phone'] = $phone;
    $_SESSION['firebase_uid'] = $uid;

    $redirect = '';
    if ($role === 'admin') {
        $redirect = 'admin/dashboard.php';
    }
    elseif ($role === 'delivery') {
        $redirect = 'delivery/dashboard.php';
    }
    else {
        $redirect = 'user/dashboard.php';
    }

    respond(true, 'Login successful!', ['redirect' => $redirect, 'is_new' => $isNewUser]);
}

if ($action === 'dummy_login') {
    $phone = preg_replace('/[^0-9]/', '', $data['phone'] ?? '');
    $otp   = preg_replace('/[^0-9A-Za-z]/', '', $data['otp'] ?? '');

    if (empty($phone) || empty($otp)) {
        respond(false, 'Phone and OTP are required.');
    }
    if (strlen($phone) !== 10) {
        respond(false, 'Phone number must be exactly 10 digits.');
    }

    // ISSUE-002 FIX: OTP rate limiting — max 5 attempts per 10 minutes per phone
    try {
        $rateSql = $pdo->prepare("
            SELECT COUNT(*) FROM otp_attempts
            WHERE phone = ? AND created_at > DATE_SUB(NOW(), INTERVAL 10 MINUTE)
        ");
        $rateSql->execute([$phone]);
        if ((int)$rateSql->fetchColumn() >= 5) {
            respond(false, 'Too many login attempts. Please wait 10 minutes before trying again.');
        }
        $pdo->prepare("INSERT INTO otp_attempts (phone) VALUES (?)")->execute([$phone]);
    } catch (\Exception $e) {
        // Table may not exist yet — non-fatal, continue
        error_log("OTP rate limit check failed: " . $e->getMessage());
    }

    $user = null;
    $role = '';

    // Check Role and Dummy OTP in users table
    $stmt = $pdo->prepare("SELECT id, role, phone, is_blocked FROM users WHERE phone = ? AND dummy_otp = ? LIMIT 1");
    $stmt->execute([$phone, $otp]);
    $user = $stmt->fetch();

    if (!$user) {
        respond(false, 'Invalid Phone or Dummy OTP.');
    }

    // ISSUE-004 FIX: Blocked user check
    if (!empty($user['is_blocked'])) {
        respond(false, 'Your account has been blocked. Please contact support.');
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['phone'] = $user['phone'];

    $redirect = '';
    if ($user['role'] === 'admin')
        $redirect = 'admin/dashboard.php';
    elseif ($user['role'] === 'delivery')
        $redirect = 'delivery/dashboard.php';
    else
        $redirect = 'user/dashboard.php';

    respond(true, 'Login successful!', ['redirect' => $redirect]);
}

if ($action === 'logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
    respond(true, 'Logged out forcefully', ['redirect' => '../index.php']);
}

respond(false, 'Invalid action specified.');
