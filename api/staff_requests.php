<?php
require_once '../config.php';
header('Content-Type: application/json');

function respond($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid request.');

$ct     = $_SERVER['CONTENT_TYPE'] ?? '';
$isJson = str_contains($ct, 'application/json');
$body   = $isJson ? (json_decode(file_get_contents('php://input'), true) ?? []) : [];
$action = $body['action'] ?? $_POST['action'] ?? '';

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

$userId = $_SESSION['user_id'] ?? 0;
$role   = $_SESSION['role']    ?? '';

// ─── USER: Submit request ──────────────────────────────────────────────────
if ($action === 'submit_request') {
    if (!$userId || $role !== 'customer') respond(false, 'Login required.');
    $message = trim(htmlspecialchars(strip_tags($body['message'] ?? ''), ENT_QUOTES, 'UTF-8'));
    if (empty($message)) respond(false, 'Please enter a message.');

    $cnt = $pdo->prepare("SELECT COUNT(*) FROM staff_requests WHERE user_id=? AND status='pending'");
    $cnt->execute([$userId]);
    if ($cnt->fetchColumn() >= 3) respond(false, 'You have 3 pending requests already. Please wait for a reply.');

    $pdo->prepare("INSERT INTO staff_requests (user_id, message) VALUES (?,?)")->execute([$userId, $message]);
    respond(true, 'Request submitted! Our team will reply soon.');
}

// ─── USER: Get their own requests ─────────────────────────────────────────
if ($action === 'get_my_requests') {
    if (!$userId || $role !== 'customer') respond(false, 'Login required.');
    $stmt = $pdo->prepare("
        SELECT sr.*, u.name as delivery_name, u.phone as delivery_phone
        FROM staff_requests sr
        LEFT JOIN users u ON sr.delivery_id = u.id
        WHERE sr.user_id = ?
        ORDER BY sr.created_at DESC LIMIT 10
    ");
    $stmt->execute([$userId]);
    respond(true, 'OK', ['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ─── ADMIN: Get all requests with full user details ────────────────────────
if ($action === 'get_requests') {
    if ($role !== 'admin') respond(false, 'Admin only.');
    $stmt = $pdo->prepare("
        SELECT sr.*,
               u.name as user_name, u.phone as user_phone, u.shop_address, u.email as user_email,
               m.name as market_name,
               d.name as delivery_name, d.phone as delivery_phone
        FROM staff_requests sr
        LEFT JOIN users u ON sr.user_id = u.id
        LEFT JOIN markets m ON u.market_id = m.id
        LEFT JOIN users d ON sr.delivery_id = d.id
        ORDER BY FIELD(sr.status,'pending','seen','resolved'), sr.created_at DESC
    ");
    $stmt->execute();
    respond(true, 'OK', ['requests' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

// ─── ADMIN: Reply/update status ────────────────────────────────────────────
if ($action === 'reply_request') {
    if ($role !== 'admin') respond(false, 'Admin only.');
    $rid    = (int)($body['request_id'] ?? 0);
    $note   = trim(htmlspecialchars(strip_tags($body['admin_note'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $status = in_array($body['status'] ?? '', ['seen', 'resolved']) ? $body['status'] : 'seen';
    if (!$rid) respond(false, 'Invalid request ID.');

    $pdo->prepare("UPDATE staff_requests SET admin_note=?, status=? WHERE id=?")->execute([$note, $status, $rid]);

    // Notify user
    $req = $pdo->prepare("SELECT user_id FROM staff_requests WHERE id=?");
    $req->execute([$rid]);
    $targetUserId = $req->fetchColumn();
    if ($targetUserId) {
        try { sendPushNotification($pdo, $targetUserId, '💬 Staff Reply', 'A staff member has replied to your request: ' . substr($note, 0, 60)); } catch(\Throwable $t) {}
    }

    respond(true, 'Reply sent.');
}

// ─── ADMIN: Assign delivery partner to a staff request ────────────────────
if ($action === 'assign_delivery') {
    if ($role !== 'admin') respond(false, 'Admin only.');
    $rid        = (int)($body['request_id']  ?? 0);
    $deliveryId = (int)($body['delivery_id'] ?? 0);
    if (!$rid || !$deliveryId) respond(false, 'Invalid request or delivery partner.');

    $pdo->prepare("UPDATE staff_requests SET delivery_id=?, status='seen' WHERE id=?")->execute([$deliveryId, $rid]);

    // Notify the requestor
    $req = $pdo->prepare("SELECT sr.user_id, d.name as dn FROM staff_requests sr LEFT JOIN users d ON d.id=? WHERE sr.id=?");
    $req->execute([$deliveryId, $rid]);
    $info = $req->fetch();
    if ($info) {
        try {
            sendPushNotification($pdo, $info['user_id'], '🚴 Staff Assigned', "A delivery representative ({$info['dn']}) has been assigned to assist you.");
        } catch(\Throwable $t) {}
    }

    respond(true, 'Delivery partner assigned.');
}

// ─── ADMIN: Get delivery partners for assignment dropdown ─────────────────
if ($action === 'get_partners_for_assign') {
    if ($role !== 'admin') respond(false, 'Admin only.');
    $stmt = $pdo->prepare("SELECT id, name, phone, current_orders, is_online FROM users WHERE role='delivery' ORDER BY is_online DESC, current_orders ASC");
    $stmt->execute();
    respond(true, 'OK', ['partners' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
}

respond(false, 'Unknown action.');
