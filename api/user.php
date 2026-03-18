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
    $name       = htmlspecialchars(strip_tags($data['name'] ?? ''), ENT_QUOTES, 'UTF-8');
    $email      = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $shopAddress = htmlspecialchars(strip_tags($data['shop_address'] ?? ''), ENT_QUOTES, 'UTF-8');
    $altContact = preg_replace('/[^0-9]/', '', $data['alt_contact'] ?? ''); // digits only

    if (empty($name)) {
        respond(false, 'Full name is required.');
    }
    if (empty($shopAddress)) {
        respond(false, 'Shop address is required.');
    }
    // Validate alt_contact only if provided
    if (!empty($altContact) && strlen($altContact) !== 10) {
        respond(false, 'Alternate contact must be exactly 10 digits.');
    }

    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, shop_address = ?, alt_contact = ? WHERE id = ?");
        $stmt->execute([$name, $email, $shopAddress, $altContact ?: null, $userId]);
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
