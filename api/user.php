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
$action = $data['action'] ?? $_POST['action'] ?? '';
$userId = $_SESSION['user_id'];

// CSRF Protection Check
$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token. Request denied.');
}

if ($action === 'update_profile') {
    $name = filter_var($data['name'] ?? '', FILTER_SANITIZE_STRING);
    $email = filter_var($data['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $shopAddress = filter_var($data['shop_address'] ?? '', FILTER_SANITIZE_STRING);
    $altContact = filter_var($data['alt_contact'] ?? '', FILTER_SANITIZE_STRING);

    if (empty($name) || empty($shopAddress)) {
        respond(false, 'Name and Shop Address are required.');
    }

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

respond(false, 'Invalid action specified in api/user.php');
