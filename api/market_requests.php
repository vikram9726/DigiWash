<?php
require_once '../config.php';
header('Content-Type: application/json');

function respond($ok, $msg, $data = []) {
    echo json_encode(array_merge(['success' => $ok, 'message' => $msg], $data));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(false, 'Invalid method.');

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── CSRF ──────────────────────────────────────────────────────
$headers   = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token']
    ?? $headers['x-csrf-token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? ($data['csrf_token'] ?? '');

if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

if (!isset($_SESSION['user_id'])) respond(false, 'Unauthorized.');

$userId = (int)$_SESSION['user_id'];
$role   = $_SESSION['role'] ?? '';

// ══════════════════════════════════════════════════════════════
// USER: Submit market request
// ══════════════════════════════════════════════════════════════
if ($action === 'submit_market_request') {
    $marketName = trim(htmlspecialchars(strip_tags($data['market_name'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $city       = trim(htmlspecialchars(strip_tags($data['city'] ?? ''), ENT_QUOTES, 'UTF-8'));
    $pincode    = preg_replace('/[^0-9]/', '', $data['pincode'] ?? '');
    $landmark   = trim(htmlspecialchars(strip_tags($data['landmark'] ?? ''), ENT_QUOTES, 'UTF-8'));

    if (empty($marketName) || empty($city) || empty($pincode))
        respond(false, 'Market name, city and pincode are required.');
    if (strlen($marketName) < 3)
        respond(false, 'Market name must be at least 3 characters.');
    if (strlen($pincode) !== 6)
        respond(false, 'Pincode must be exactly 6 digits.');

    // Check duplicate: same name+pincode already requested
    $dup = $pdo->prepare("SELECT id FROM market_requests WHERE LOWER(market_name) = LOWER(?) AND pincode = ? LIMIT 1");
    $dup->execute([$marketName, $pincode]);
    if ($dup->fetch())
        respond(false, 'A request for this area already exists. We\'ll review it soon!');

    // Check if already in active markets
    $exists = $pdo->prepare("SELECT id FROM markets WHERE LOWER(name) LIKE ? LIMIT 1");
    $exists->execute(['%' . strtolower($marketName) . '%']);
    if ($exists->fetch())
        respond(false, 'This area may already be listed — try searching the dropdown again.');

    // Check if this user already has a pending request for same area
    $userDup = $pdo->prepare("SELECT id FROM market_requests WHERE user_id = ? AND LOWER(market_name) = LOWER(?) AND status = 'pending' LIMIT 1");
    $userDup->execute([$userId, $marketName]);
    if ($userDup->fetch())
        respond(false, 'You already have a pending request for this area.');

    $stmt = $pdo->prepare("INSERT INTO market_requests (user_id, market_name, city, pincode, landmark) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$userId, $marketName, $city, $pincode, $landmark ?: null]);

    // Notify admin
    $admin = $pdo->query("SELECT id FROM users WHERE role = 'admin' LIMIT 1")->fetch();
    if ($admin) {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
            ->execute([$admin['id'], '📍 New Market Request', "User requested \"$marketName\" ($city, $pincode)"]);
    }

    respond(true, 'Request submitted! We\'ll notify you once it\'s reviewed.');
}

// ══════════════════════════════════════════════════════════════
// ADMIN: List requests
// ══════════════════════════════════════════════════════════════
if ($action === 'get_market_requests') {
    if ($role !== 'admin') respond(false, 'Unauthorized.');

    $status = $data['status'] ?? 'pending';
    if (!in_array($status, ['pending','approved','rejected','all'])) $status = 'pending';

    $where  = ($status === 'all') ? '' : 'WHERE mr.status = ?';
    $params = ($status === 'all') ? [] : [$status];

    $stmt = $pdo->prepare("
        SELECT mr.*, u.name AS user_name, u.phone AS user_phone
        FROM market_requests mr
        JOIN users u ON u.id = mr.user_id
        $where
        ORDER BY mr.created_at DESC
        LIMIT 200
    ");
    $stmt->execute($params);
    respond(true, 'OK', ['requests' => $stmt->fetchAll()]);
}

// ══════════════════════════════════════════════════════════════
// ADMIN: Approve → add to markets table
// ══════════════════════════════════════════════════════════════
if ($action === 'approve_market_request') {
    if ($role !== 'admin') respond(false, 'Unauthorized.');

    $reqId = (int)($data['request_id'] ?? 0);
    if (!$reqId) respond(false, 'Invalid request ID.');

    $req = $pdo->prepare("SELECT * FROM market_requests WHERE id = ?");
    $req->execute([$reqId]);
    $request = $req->fetch();
    if (!$request) respond(false, 'Request not found.');
    if ($request['status'] !== 'pending') respond(false, 'Request already processed.');

    // Insert into markets (lat/lng default 0,0 — admin can edit later)
    $pdo->prepare("INSERT INTO markets (name, lat, lng, radius_km) VALUES (?, 0, 0, 5.00)")
        ->execute([$request['market_name']]);

    $pdo->prepare("UPDATE market_requests SET status = 'approved' WHERE id = ?")->execute([$reqId]);

    // Notify user
    $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
        ->execute([
            $request['user_id'],
            '✅ Market Area Approved!',
            "Great news! \"{$request['market_name']}\" has been added to our service zones. Select it from the dropdown now."
        ]);

    sendPushNotification($pdo, $request['user_id'], '✅ Market Area Approved!', "\"{$request['market_name']}\" is now live in DigiWash!");

    respond(true, 'Market approved and added to service zones!');
}

// ══════════════════════════════════════════════════════════════
// ADMIN: Reject
// ══════════════════════════════════════════════════════════════
if ($action === 'reject_market_request') {
    if ($role !== 'admin') respond(false, 'Unauthorized.');

    $reqId = (int)($data['request_id'] ?? 0);
    if (!$reqId) respond(false, 'Invalid request ID.');

    $req = $pdo->prepare("SELECT * FROM market_requests WHERE id = ?");
    $req->execute([$reqId]);
    $request = $req->fetch();
    if (!$request) respond(false, 'Request not found.');
    if ($request['status'] !== 'pending') respond(false, 'Request already processed.');

    $pdo->prepare("UPDATE market_requests SET status = 'rejected' WHERE id = ?")->execute([$reqId]);

    $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")
        ->execute([
            $request['user_id'],
            '📍 Market Request Update',
            "We couldn't add \"{$request['market_name']}\" at this time. We're expanding soon — thanks for helping us grow!"
        ]);

    respond(true, 'Market request rejected.');
}

// ══════════════════════════════════════════════════════════════
// ADMIN: Get pending count (for badge)
// ══════════════════════════════════════════════════════════════
if ($action === 'get_market_requests_count') {
    if ($role !== 'admin') respond(false, 'Unauthorized.');
    $count = (int)$pdo->query("SELECT COUNT(*) FROM market_requests WHERE status = 'pending'")->fetchColumn();
    respond(true, 'OK', ['count' => $count]);
}

respond(false, 'Invalid action.');
