<?php
require_once '../config.php';

header('Content-Type: application/json');

// Helper function to return JSON and exit
function respond($success, $message, $data = []) {
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

if ($action === 'login') {
    $phone = filter_var($data['phone'] ?? '', FILTER_SANITIZE_STRING);
    
    // Basic validation
    if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
        respond(false, 'Please enter a valid 10-digit mobile number.');
    }

    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, role, name, shop_address, qr_code_hash FROM users WHERE phone = ?");
    $stmt->execute([$phone]);
    $user = $stmt->fetch();

    $isNewUser = false;

    if (!$user) {
        // Create new Customer
        $insert = $pdo->prepare("INSERT INTO users (phone, role) VALUES (?, 'customer')");
        try {
            $insert->execute([$phone]);
            $userId = $pdo->lastInsertId();
            $role = 'customer';
            $isNewUser = true;

            // Generate unique QR hash for the new user
            $qrHash = hash('sha256', $userId . $phone . bin2hex(random_bytes(10)));
            $pdo->prepare("UPDATE users SET qr_code_hash = ? WHERE id = ?")->execute([$qrHash, $userId]);

        } catch (\Exception $e) {
            respond(false, 'Failed to create account. Please try again later.');
        }
    } else {
        $userId = $user['id'];
        $role = $user['role'];
        
        // Retroactively generate QR hash for old users if missing
        if (empty($user['qr_code_hash']) && $role === 'customer') {
            $qrHash = hash('sha256', $userId . $phone . bin2hex(random_bytes(10)));
            $pdo->prepare("UPDATE users SET qr_code_hash = ? WHERE id = ?")->execute([$qrHash, $userId]);
        }
    }

    // Set Sessions
    $_SESSION['user_id'] = $userId;
    $_SESSION['role'] = $role;
    $_SESSION['phone'] = $phone;

    // Determine redirect based on role
    $redirect = '';
    if ($role === 'admin') {
        $redirect = 'admin/dashboard.php';
    } elseif ($role === 'delivery') {
        $redirect = 'delivery/dashboard.php';
    } else {
        // Customer
        $redirect = 'user/dashboard.php';
        // If they are a new user or missing details, we might redirect them to a profile setup page later
        // For now, straight to dashboard which will prompt them if needed
    }

    respond(true, 'Login successful!', ['redirect' => $redirect, 'is_new' => $isNewUser]);
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
