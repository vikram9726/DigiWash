<?php
// api/contact.php — handles public form submissions AND admin operations
require_once '../config.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

// ─── GET requests (admin read-only) ───────────────────────────────────────────
if ($method === 'GET') {
    // Only admins can read messages
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
    }

    if ($action === 'count_new') {
        $count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE status='new'")->fetchColumn();
        echo json_encode(['count' => (int)$count]); exit;
    }

    if ($action === 'list') {
        $status = $_GET['status'] ?? 'all';
        if ($status === 'all') {
            $stmt = $pdo->query("SELECT * FROM contact_messages ORDER BY created_at DESC");
        } else {
            $stmt = $pdo->prepare("SELECT * FROM contact_messages WHERE status=? ORDER BY created_at DESC");
            $stmt->execute([$status]);
        }
        echo json_encode(['success' => true, 'messages' => $stmt->fetchAll()]); exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']); exit;
}

// ─── POST requests ─────────────────────────────────────────────────────────────
if ($method !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']); exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? [];
$postAction = $data['action'] ?? null;

// Public form submission — no auth needed
if ($postAction === null || !isset($data['action'])) {
    // Treat as direct form submit
    $name    = trim($data['name'] ?? '');
    $phone   = trim($data['phone'] ?? '');
    $message = trim($data['message'] ?? '');

    if (empty($name) || empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, phone, message) VALUES (?, ?, ?)");
        $stmt->execute([$name, $phone, $message]);
        echo json_encode(['success' => true, 'message' => 'Message sent successfully! Our team will contact you soon.']);
    } catch (PDOException $e) {
        error_log("Contact Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    exit;
}

// Admin-only POST actions
if ($postAction === 'form_submit') {
    $name    = trim($data['name'] ?? '');
    $phone   = trim($data['phone'] ?? '');
    $message = trim($data['message'] ?? '');

    if (empty($name) || empty($phone) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']); exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO contact_messages (name, phone, message) VALUES (?, ?, ?)");
        $stmt->execute([$name, $phone, $message]);
        echo json_encode(['success' => true, 'message' => 'Message sent successfully! Our team will contact you soon.']);
    } catch (PDOException $e) {
        error_log("Contact Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    }
    exit;
}

// Admin-only actions beyond this point
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']); exit;
}

$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token']
    ?? $headers['x-csrf-token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? (is_array($data ?? null) ? ($data['csrf_token'] ?? '') : '')
    ?? $_POST['csrf_token']
    ?? '';

if (!isset($_SESSION['csrf_token']) || empty($csrfToken) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token. Request denied.']); exit;
}


if ($postAction === 'update_status') {
    $id     = (int)($data['id'] ?? 0);
    $status = $data['status'] ?? '';
    if (!$id || !in_array($status, ['new', 'read', 'resolved'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid input']); exit;
    }
    $pdo->prepare("UPDATE contact_messages SET status=? WHERE id=?")->execute([$status, $id]);
    echo json_encode(['success' => true]); exit;
}

if ($postAction === 'delete') {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { echo json_encode(['success' => false, 'message' => 'Invalid ID']); exit; }
    $pdo->prepare("DELETE FROM contact_messages WHERE id=?")->execute([$id]);
    echo json_encode(['success' => true]); exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
?>
