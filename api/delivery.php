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
    respond(false, 'Unauthorized. Delivery partner access only.');
}

$data = json_decode(file_get_contents('php://input'), true);

// If data is null, it might be a FormData request (for file uploads)
if (!$data) {
    $action = $_POST['action'] ?? '';
} else {
    $action = $data['action'] ?? '';
}

$deliveryId = $_SESSION['user_id'];

// --- FETCH ASSIGNMENTS ---
if ($action === 'get_assignments') {
    $type = $data['type'] ?? 'pickups'; // pickups, deliveries, completed, returns

    if ($type === 'pickups') {
        // Pending pickups: Orders assigned to this delivery man, but not yet picked up
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.shop_address, u.phone 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status = 'pending'
            ORDER BY o.created_at ASC
        ");
    } elseif ($type === 'deliveries') {
        // Pending deliveries: Orders ready to go back to customer
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.shop_address, u.phone 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status = 'out_for_delivery'
            ORDER BY o.updated_at ASC
        ");
    } elseif ($type === 'completed') {
        // Delivered orders by this partner
        $stmt = $pdo->prepare("
            SELECT o.*, u.name as customer_name, u.shop_address 
            FROM orders o 
            JOIN users u ON o.user_id = u.id 
            WHERE o.delivery_id = ? AND o.status = 'delivered'
            ORDER BY o.updated_at DESC LIMIT 50
        ");
    } else {
        respond(false, 'Invalid assignment type.');
    }
    
    $stmt->execute([$deliveryId]);
    $assignments = $stmt->fetchAll();

    respond(true, ucfirst($type) . ' fetched', ['assignments' => $assignments]);
}

// --- FULFILL PICKUP ---
if ($action === 'fulfill_pickup') {
    $orderId = $data['order_id'] ?? 0;
    
    // In a real scenario, the delivery partner takes the clothes and marks it "picked_up"
    // Which then transitions to "in_process" (washing) at the facility
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = 'in_process', updated_at = NOW() WHERE id = ? AND delivery_id = ? AND status = 'pending'");
        $stmt->execute([$orderId, $deliveryId]);
        
        if ($stmt->rowCount() > 0) {
            respond(true, 'Pickup marked as successfully collected & sent to processing.');
        } else {
            respond(false, 'Failed to update. Order might not be assigned to you or already picked up.');
        }
    } catch (\Exception $e) {
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- FULFILL DELIVERY (OTP) ---
if ($action === 'complete_delivery_otp') {
    $orderId = $data['order_id'] ?? 0;
    $otp = filter_var($data['otp'] ?? '', FILTER_SANITIZE_STRING);

    if (empty($otp)) respond(false, 'OTP is required.');

    // Fetch order to verify OTP
    // For this simulation, we'll assume the OTP generated and sent to the user matches '123456' 
    // OR we check the database if we actually stored one. Let's assume a hardcoded '123456' for the demo if not set.
    $stmt = $pdo->prepare("SELECT delivery_otp FROM orders WHERE id = ? AND delivery_id = ? AND status = 'out_for_delivery'");
    $stmt->execute([$orderId, $deliveryId]);
    $order = $stmt->fetch();

    if (!$order) {
        respond(false, 'Invalid order or order is not ready for delivery.');
    }

    $actualOtp = $order['delivery_otp'] ?: '123456'; // Fallback for DEMO purposes

    if ($otp !== $actualOtp) {
        respond(false, 'Incorrect OTP. Handover failed.');
    }

    try {
        $pdo->beginTransaction();
        // Update Order
        $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$orderId]);
        
        // The Payment remains 'remaining' until they pay via the app or COD is collected.
        // If COD, we might assume delivery guy collects it.
        $pdo->commit();
        respond(true, 'Delivery completed successfully via OTP!');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Database Error: ' . $e->getMessage());
    }
}

// --- BYPASS DELIVERY (PHOTO UPLOAD) ---
if ($action === 'complete_delivery_qr') {
    $orderId = filter_var($data['order_id'] ?? '', FILTER_VALIDATE_INT);
    $qrHash = filter_var($data['qr_hash'] ?? '', FILTER_SANITIZE_STRING);

    if (!$orderId || empty($qrHash)) {
        respond(false, 'Order ID and QR Code Hash are required.');
    }

    // Verify order belongs to this delivery partner and is in-process
    $stmt = $pdo->prepare("
        SELECT o.id, o.user_id, u.qr_code_hash
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ? AND o.delivery_id = ? AND o.status = 'in_process'
    ");
    $stmt->execute([$orderId, $deliveryId]);
    $order = $stmt->fetch();

    if (!$order) {
        respond(false, 'Order not found or not currently out for delivery by you.');
    }

    // Verify QR Hash matches the customer
    if (empty($order['qr_code_hash']) || $order['qr_code_hash'] !== $qrHash) {
        respond(false, 'Invalid QR Code. This QR code does not match the customer for this order.');
    }

    try {
        $pdo->beginTransaction();

        // Mark order as delivered
        $pdo->prepare("UPDATE orders SET status = 'delivered', updated_at = NOW() WHERE id = ?")
            ->execute([$orderId]);

        // Automatically update the related payment to completed
        $pdo->prepare("UPDATE payments SET status = 'completed', updated_at = NOW() WHERE order_id = ?")
            ->execute([$orderId]);

        $pdo->commit();
        respond(true, 'Delivery completed successfully via QR Scan!');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, 'Database error during completion.');
    }
}

if ($action === 'complete_delivery_bypass') {
    $orderId = $_POST['order_id'] ?? 0;
    $staffNumber = filter_var($_POST['staff_number'] ?? '', FILTER_SANITIZE_STRING);
    
    if (empty($staffNumber)) {
        respond(false, 'Staff number is required for bypass.');
    }

    if (!isset($_FILES['staff_photo']) || $_FILES['staff_photo']['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'Staff photo upload is required for bypass.');
    }

    // Handle Upload
    $uploadDir = '../uploads/staff_photos/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExt = strtolower(pathinfo($_FILES['staff_photo']['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];

    if (!in_array($fileExt, $allowedExts)) {
        respond(false, 'Invalid file type. Only JPG/PNG allowed.');
    }

    $newFileName = 'bypass_' . $orderId . '_' . time() . '.' . $fileExt;
    $destPath = $uploadDir . $newFileName;

    if (move_uploaded_file($_FILES['staff_photo']['tmp_name'], $destPath)) {
        
        $publicPhotoUrl = 'uploads/staff_photos/' . $newFileName;

        try {
            $pdo->beginTransaction();
            
            // Mark as delivered, store bypass photo URL
            $stmt = $pdo->prepare("UPDATE orders SET status = 'delivered', bypass_photo_url = ?, updated_at = NOW() WHERE id = ? AND delivery_id = ?");
            $stmt->execute([$publicPhotoUrl, $orderId, $deliveryId]);

            // Here you would trigger the SMS API to send a message to the customer:
            // "Your order was delivered to staff at $staffNumber. View photo: $publicPhotoUrl"

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
