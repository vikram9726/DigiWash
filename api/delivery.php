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

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    respond(false, 'Unauthorized. Delivery access only.');
}

$data = json_decode(file_get_contents('php://input'), true);

// If data is null, it might be a FormData request (for file uploads)
if (!$data) {
    $action = $_POST['action'] ?? '';
} else {
    $action = $data['action'] ?? '';
}
$deliveryId = $_SESSION['user_id'];

// CSRF Protection Check
$headers = function_exists('getallheaders') ? getallheaders() : [];
$csrfToken = $headers['X-CSRF-Token']
    ?? $headers['x-csrf-token']
    ?? $_SERVER['HTTP_X_CSRF_TOKEN']
    ?? (is_array($data ?? null) ? ($data['csrf_token'] ?? '') : '')
    ?? (is_array($body ?? null) ? ($body['csrf_token'] ?? '') : '')
    ?? $_POST['csrf_token']
    ?? '';
if (!hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    respond(false, 'Invalid CSRF token. Request denied.');
}

// --- FETCH ASSIGNMENTS ---
if ($action === 'get_assignments') {
    $type = $data['type'] ?? 'pickups';

    if ($type === 'pickups') {
        // Pending: assigned but not yet picked up
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.shop_address, u.phone, u.lat, u.lng 
            FROM orders o JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status IN ('assigned', 'pending')
            ORDER BY o.created_at ASC
        ");
    } elseif ($type === 'in_process') {
        // In laundry facility after pickup
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.shop_address, u.phone, u.lat, u.lng 
            FROM orders o JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status IN ('picked_up','in_process')
            ORDER BY o.updated_at ASC
        ");
    } elseif ($type === 'deliveries') {
        // Ready to return to customer
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.shop_address, u.phone, u.lat, u.lng 
            FROM orders o JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status = 'out_for_delivery'
            ORDER BY o.updated_at ASC
        ");
    } elseif ($type === 'completed') {
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.phone as customer_phone, u.shop_address, u.lat, u.lng 
            FROM orders o JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status = 'delivered'
            ORDER BY o.updated_at DESC LIMIT 50
        ");
    } elseif ($type === 'returns') {
        // Approved returns — partner must pick up from customer
        $stmt = $pdo->prepare("
            SELECT r.id as return_id, r.reason, r.photo_url, r.created_at as return_date,
                   o.id, o.total_amount, u.name as customer_name, u.phone, u.shop_address, u.lat, u.lng
            FROM returns r
            JOIN orders o ON r.order_id = o.id
            JOIN users u ON r.user_id = u.id
            WHERE o.delivery_id = ? AND r.admin_status = 'approved'
            ORDER BY r.created_at DESC
        ");
    } else {
        respond(false, 'Invalid assignment type.');
    }

    $stmt->execute([$deliveryId]);
    $assignments = $stmt->fetchAll();
    respond(true, ucfirst($type) . ' fetched', ['assignments' => $assignments]);
}

// --- DELIVERY STATS ---
if ($action === 'get_stats') {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_id = ? AND status IN ('assigned', 'pending')");
    $stmt->execute([$deliveryId]); $pickups = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_id = ? AND status IN ('picked_up','in_process')");
    $stmt->execute([$deliveryId]); $inProcess = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_id = ? AND status = 'out_for_delivery'");
    $stmt->execute([$deliveryId]); $outForDelivery = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_id = ? AND status = 'delivered' AND DATE(updated_at) = ?");
    $stmt->execute([$deliveryId, $today]); $todayDone = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE delivery_id = ? AND status = 'delivered'");
    $stmt->execute([$deliveryId]); $totalDone = $stmt->fetchColumn();

    respond(true, 'Stats', [
        'pickups'         => $pickups,
        'in_process'      => $inProcess,
        'out_for_delivery'=> $outForDelivery,
        'today_done'      => $todayDone,
        'total_done'      => $totalDone,
    ]);
}

// --- TOGGLE ONLINE ---
if ($action === 'toggle_online') {
    $isOnline = filter_var($data['is_online'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    $pdo->prepare("UPDATE users SET is_online = ? WHERE id = ?")->execute([$isOnline, $deliveryId]);
    respond(true, 'Status updated', ['is_online' => $isOnline]);
}

// --- ACCEPT ORDER ---
if ($action === 'accept_order') {
    $orderId = (int)($data['order_id'] ?? 0);
    $stmt = $pdo->prepare("UPDATE orders SET status = 'pending' WHERE id = ? AND delivery_id = ? AND status = 'assigned'");
    $stmt->execute([$orderId, $deliveryId]);
    if ($stmt->rowCount() > 0) {
        respond(true, 'Order accepted.');
    } else {
        respond(false, 'Order not found or already accepted.');
    }
}

// --- FULFILL PICKUP ---
if ($action === 'fulfill_pickup') {
    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) respond(false, 'Invalid order ID.');

    try {
        // Accept both 'assigned' and 'pending' statuses so the button always works
        $stmt = $pdo->prepare("UPDATE orders SET status = 'in_process', picked_up_at = NOW(), updated_at = NOW() WHERE id = ? AND delivery_id = ? AND status IN ('assigned', 'pending')");
        $stmt->execute([$orderId, $deliveryId]);

        if ($stmt->rowCount() > 0) {
            $stmtUser = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmtUser->execute([$orderId]);
            $ownerId = $stmtUser->fetchColumn();
            $title = "Clothes Picked Up 🛍️";
            $msg = "Your clothes have been successfully collected and are now heading to our laundry facility!";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title, $msg]);
            sendPushNotification($pdo, $ownerId, $title, $msg);
            respond(true, 'Pickup confirmed! Order is now in processing.');
        } else {
            respond(false, 'Could not update. The order may already be picked up, or not assigned to you.');
        }
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- CANCEL / RELEASE PICKUP ---
if ($action === 'cancel_pickup') {
    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) respond(false, 'Invalid order ID.');

    try {
        // Only allow cancelling orders in 'assigned' or 'pending' stage (not yet picked up)
        $stmt = $pdo->prepare("UPDATE orders SET status = 'pending', delivery_id = NULL, updated_at = NOW() WHERE id = ? AND delivery_id = ? AND status IN ('assigned', 'pending')");
        $stmt->execute([$orderId, $deliveryId]);

        if ($stmt->rowCount() > 0) {
            // Decrement partner's active order count
            $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$deliveryId]);
            respond(true, 'Order released back to the pool successfully.');
        } else {
            respond(false, 'Cannot cancel. Order may already be picked up or not assigned to you.');
        }
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- MARK ORDER READY FOR DELIVERY ---
if ($action === 'mark_ready') {
    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) respond(false, 'Invalid order ID.');

    try {
        $stmt = $pdo->prepare("
            UPDATE orders SET status = 'out_for_delivery', updated_at = NOW()
            WHERE id = ? AND delivery_id = ? AND status IN ('picked_up', 'in_process')
        ");
        $stmt->execute([$orderId, $deliveryId]);

        if ($stmt->rowCount() > 0) {
            // Notify customer
            $stmtUser = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $stmtUser->execute([$orderId]);
            $ownerId = $stmtUser->fetchColumn();
            $title = "Out for Delivery 🚚";
            $msg = "Your laundry order #$orderId is cleaned, packed, and out for delivery!";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title, $msg]);
            sendPushNotification($pdo, $ownerId, $title, $msg);
            respond(true, 'Order marked as out for delivery.');
        } else {
            respond(false, 'Could not update. Order may not be yours or not in the right stage.');
        }
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}



// --- FULFILL DELIVERY (OTP) ---
if ($action === 'complete_delivery_otp') {
    $orderId = $data['order_id'] ?? 0;
    $otp = htmlspecialchars(strip_tags($data['otp'] ?? ''), ENT_QUOTES, 'UTF-8');

    if (empty($otp)) respond(false, 'PIN is required.');

    $stmt = $pdo->prepare("SELECT user_id, delivery_id FROM orders WHERE id = ? AND status = 'out_for_delivery'");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order || $order['delivery_id'] != $deliveryId) {
        respond(false, 'Invalid order or order is not ready for delivery.');
    }

    $salt = "digiwash_delivery_otp_sec";
    $timeWindow = floor(time() / 1800); // 30 minutes
    $expectedOtp = str_pad(abs(crc32($order['user_id'] . $salt . $timeWindow)) % 1000000, 6, '0', STR_PAD_LEFT);
    $prevOtp = str_pad(abs(crc32($order['user_id'] . $salt . ($timeWindow - 1))) % 1000000, 6, '0', STR_PAD_LEFT);

    if ($otp !== $expectedOtp && $otp !== $prevOtp) {
        respond(false, 'Incorrect PIN. Handover denied. Please request the active PIN from the customer\'s dashboard.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);
        
        // Decrement active orders load
        $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$deliveryId]);

        $ownerId = $order['user_id'];
        
        // Notification
        $title = "Order Delivered";
        $msg = "Your laundry order #$orderId has been successfully delivered. Thank you for choosing DigiWash!";
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title, $msg]);
        sendPushNotification($pdo, $ownerId, $title, $msg);

        $pdo->commit();
        respond(true, 'Delivery completed successfully via PIN!');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- DELIVER VIA ENCRYPTED ORDER QR ---
if ($action === 'complete_delivery_qr') {
    $encToken = $data['qr_token'] ?? '';
    if (empty($encToken)) respond(false, 'QR token is required.');

    $orderId = decrypt_order_token($encToken);
    if (!$orderId) respond(false, 'Invalid or tampered QR code.');

    $stmt = $pdo->prepare("SELECT o.id, o.user_id FROM orders o WHERE o.id = ? AND o.delivery_id = ? AND o.status = 'out_for_delivery'");
    $stmt->execute([$orderId, $deliveryId]);
    $order = $stmt->fetch();
    if (!$order) respond(false, 'Order not found, not assigned to you, or not ready for delivery.');

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW(), updated_at = NOW() WHERE id = ?")
            ->execute([$orderId]);
        $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")
            ->execute([$deliveryId]);
        $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE order_id = ?")
            ->execute([$orderId]);
        $ownerId = $order['user_id'];
        $title = 'Delivery Verified via QR \u2705';
        $msg   = "Order #$orderId scanned and delivered. Thank you for choosing DigiWash!";
        $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title, $msg]);
        sendPushNotification($pdo, $ownerId, $title, $msg);
        $pdo->commit();
        respond(true, 'Delivery completed via QR Scan!');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Database error.');
    }
}


if ($action === 'complete_delivery_bypass') {
    $orderId = $_POST['order_id'] ?? 0;
    $staffNumber = htmlspecialchars(strip_tags($_POST['staff_number'] ?? ''), ENT_QUOTES, 'UTF-8');
    
    if (empty($staffNumber)) {
        respond(false, 'Staff number is required for bypass.');
    }

    if (!isset($_FILES['staff_photo']) || $_FILES['staff_photo']['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'Staff photo upload is required for bypass.');
    }

    // 1. Enforce 5MB File Size Limit
    if ($_FILES['staff_photo']['size'] > 5 * 1024 * 1024) {
        respond(false, 'File size exceeds 5MB limit.');
    }

    // Handle Upload
    $uploadDir = '../uploads/staff_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // 2. Strict MIME Type Validation (Not just extension)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $_FILES['staff_photo']['tmp_name']);
    finfo_close($finfo);

    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (!in_array($mimeType, $allowedMimeTypes)) {
        respond(false, 'Invalid file content. Only JPG/PNG images are allowed.');
    }
    
    // Safely determine extension based on valid MIME
    $fileExt = ($mimeType === 'image/png') ? 'png' : 'jpg';

    // 3. Generate Secure Unique Filename
    $newFileName = 'bypass_' . $orderId . '_' . uniqid() . '.' . $fileExt;
    $destPath = $uploadDir . $newFileName;

    if (move_uploaded_file($_FILES['staff_photo']['tmp_name'], $destPath)) {
        
        $publicPhotoUrl = 'uploads/staff_photos/' . $newFileName;

        try {
            $pdo->beginTransaction();
            
            // Mark as delivered, store bypass photo URL
            $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', delivered_at = NOW(), bypass_photo_url = ?, updated_at = NOW() WHERE id = ? AND delivery_id = ?");
            $stmt->execute([$publicPhotoUrl, $orderId, $deliveryId]);

            // Decrement active orders load
            $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$deliveryId]);

            // Track user ID
            $uStmt = $pdo->prepare("SELECT user_id FROM orders WHERE id = ?");
            $uStmt->execute([$orderId]);
            $ownerId = $uStmt->fetchColumn();

            $title = "Bypass Delivery Note";
            $msg = "Order #$orderId was securely delivered to staff at contact: $staffNumber because you were unavailable.";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$ownerId, $title, $msg]);
            sendPushNotification($pdo, $ownerId, $title, $msg);

            $pdo->commit();
            respond(true, 'Delivery bypassed and marked complete. Notice sent to customer.');
        } catch (\Exception $e) {
            $pdo->rollBack();
            respond(false, 'Database Error: ' . $e->getMessage());
        }
    } else {
        respond(false, 'Failed to save the uploaded photo.');
    }
}

respond(false, 'Invalid action specified in api/delivery.php');
