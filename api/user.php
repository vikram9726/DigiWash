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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    respond(false, 'Unauthorized');
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

// CSRF Protection Check
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token. Request denied.');
}

if ($action === 'update_profile') {
    $name = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $shopAddress = htmlspecialchars(strip_tags($data['shop_address'] ?? ''), ENT_QUOTES, 'UTF-8');
    $altContact = htmlspecialchars(strip_tags($data['alt_contact'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($name) || empty($shopAddress)) {
        respond(false, 'Name and Shop Address are required.');
    }

    // Orders with Customer info
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$userId]);
    $orders = $stmt->fetchAll();

    // Latest Profile Info from users table
    $stmt = $pdo->prepare("SELECT name, phone, email, shop_address, qr_code_hash FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    // Check if the user is allowed to edit address (No ongoing payments logic - simplified here)
    // We update the data
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, shop_address = ?, alt_contact = ? WHERE id = ?");
        $stmt->execute([$name, $email, $shopAddress, $altContact, $userId]);
        
        respond(true, 'Profile updated successfully!');
    } catch (\Exception $e) {
        respond(false, 'Failed to update profile. Error: ' . $e->getMessage());
    }
}

if ($action === 'save_fcm_token') {
    $token = $data['fcm_token'] ?? '';
    if (empty($token)) respond(false, 'Token missing');

    try {
        $stmt = $pdo->prepare("UPDATE users SET fcm_token = ? WHERE id = ?");
        $stmt->execute([$token, $userId]);
        respond(true, 'Token saved');
    } catch (\Exception $e) {
        respond(false, 'Failed to save token.');
    }
}

respond(false, 'Invalid action specified in api/user.php');
