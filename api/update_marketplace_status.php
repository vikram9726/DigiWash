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
    respond(false, 'Unauthorized.');
}

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? $_POST['action'] ?? '';
$role = $_SESSION['role'];
$userId = (int)$_SESSION['user_id'];

$headers = getallheaders();
$csrfToken = $headers['X-CSRF-Token'] ?? $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
$serverCsrf = $_SESSION['csrf_token'] ?? '';
if (empty($serverCsrf) || !hash_equals($serverCsrf, $csrfToken)) {
    respond(false, 'Invalid CSRF token.');
}

// ─── UPDATE STATUS ────────────────────────────────────────────────────────────
if ($action === 'update_status') {
    $orderId = (int)($data['order_id'] ?? 0);
    $newStatus = $data['status'] ?? '';
    
    if (!$orderId || !$newStatus) {
        respond(false, 'Order ID and New Status are required.');
    }

    $validStatuses = ['placed', 'assigned', 'picked_up', 'out_for_delivery', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        respond(false, 'Invalid status update requested.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT user_id, delivery_id, status FROM marketplace_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) {
            throw new Exception('Order not found.');
        }

        if ($role === 'delivery') {
            if ($order['delivery_id'] != $userId) {
                throw new Exception('You are not assigned to this order.');
            }
            // Add basic progression logic or just let them select any forward status
            if ($newStatus === 'cancelled' || $newStatus === 'placed' || $newStatus === 'assigned') {
                throw new Exception('Delivery partners cannot revert orders to this status.');
            }
        } else if ($role !== 'admin') {
            throw new Exception('Unauthorized role for this action.');
        }

        // If order was already delivered or cancelled, we don't want to double-process some effects.
        if (in_array($order['status'], ['delivered', 'cancelled']) && $order['status'] !== $newStatus) {
            throw new Exception('Cannot change status of a delivered or cancelled order.');
        }

        // Decrement delivery boy's load if it transitions to a terminal state
        if (!in_array($order['status'], ['delivered', 'cancelled']) && in_array($newStatus, ['delivered', 'cancelled'])) {
            if ($order['delivery_id']) {
                $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")
                    ->execute([$order['delivery_id']]);
            }
            
            // Notification on completion
            $title = ($newStatus === 'delivered') ? "Marketplace Order Delivered!" : "Marketplace Order Cancelled";
            $msg = ($newStatus === 'delivered') ? "Your marketplace order #$orderId has been delivered. Enjoy!" : "Your marketplace order #$orderId was cancelled.";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$order['user_id'], $title, $msg]);
            sendPushNotification($pdo, $order['user_id'], $title, $msg);
        }

        // Notification for out for delivery
        if ($order['status'] !== 'out_for_delivery' && $newStatus === 'out_for_delivery') {
            $title = "Marketplace Order Out For Delivery";
            $msg = "Your marketplace items (Order #$orderId) are out for delivery now!";
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)")->execute([$order['user_id'], $title, $msg]);
            sendPushNotification($pdo, $order['user_id'], $title, $msg);
        }

        if (!in_array($order['status'], ['delivered', 'cancelled']) && $newStatus === 'cancelled') {
            $items = $pdo->prepare("SELECT product_id, quantity FROM marketplace_order_items WHERE order_id = ?");
            $items->execute([$orderId]);
            foreach ($items->fetchAll() as $item) {
                $pdo->prepare("UPDATE marketplace_products SET stock = stock + ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
            }
        }

        if ($newStatus === 'delivered' && $role === 'delivery') {
            $otp = trim((string)($data['otp'] ?? ''));
            if (empty($otp)) throw new Exception("Delivery PIN is required from customer.");
            
            $otpSalt = "digiwash_delivery_otp_sec";
            $isValid = false;
            foreach ([floor(time() / 1800), floor(time() / 1800) - 1] as $win) {
                $hashValue = abs(crc32($order['user_id'] . $otpSalt . $win)) % 1000000;
                $expectedOtp = str_pad($hashValue, 6, '0', STR_PAD_LEFT);
                if ($otp === $expectedOtp) { $isValid = true; break; }
            }
            if (!$isValid) throw new Exception("Invalid Delivery PIN. Check the customer's dashboard.");
        }

        $timeUpdate = '';
        if ($newStatus === 'picked_up') $timeUpdate = 'picked_up_at = CURRENT_TIMESTAMP,';
        if ($newStatus === 'delivered') $timeUpdate = 'delivered_at = CURRENT_TIMESTAMP,';
        if ($newStatus === 'cancelled') $timeUpdate = 'cancelled_at = CURRENT_TIMESTAMP,';

        $pdo->prepare("UPDATE marketplace_orders SET {$timeUpdate} status = ? WHERE id = ?")->execute([$newStatus, $orderId]);

        $pdo->commit();
        respond(true, 'Order status updated successfully.', ['status' => $newStatus]);
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, $e->getMessage());
    }
}

// ─── ASSIGN DELIVERY ────────────────────────────────────────────────────────
// Only admins can manually assign or re-assign delivery partners
if ($action === 'assign_delivery') {
    if ($role !== 'admin') respond(false, 'Only admins can assign delivery.');
    
    $orderId = (int)($data['order_id'] ?? 0);
    $newDeliveryId = (int)($data['delivery_id'] ?? 0);

    if (!$orderId || !$newDeliveryId) respond(false, 'Order ID and Delivery ID required.');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT delivery_id, status FROM marketplace_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) throw new Exception('Order not found.');
        if (in_array($order['status'], ['delivered', 'cancelled'])) throw new Exception('Cannot reassign completed orders.');

        $oldDeliveryId = $order['delivery_id'];
        
        if ($oldDeliveryId !== $newDeliveryId) {
            // Decrement old
            if ($oldDeliveryId) {
                $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$oldDeliveryId]);
            }
            // Increment new
            $pdo->prepare("UPDATE users SET current_orders = current_orders + 1 WHERE id = ?")->execute([$newDeliveryId]);

            // Automatically set status to assigned if it was placed
            $newStatus = ($order['status'] === 'placed') ? 'assigned' : $order['status'];
            $pdo->prepare("UPDATE marketplace_orders SET delivery_id = ?, status = ? WHERE id = ?")->execute([$newDeliveryId, $newStatus, $orderId]);
        }

        $pdo->commit();
        respond(true, 'Delivery partner assigned successfully.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, $e->getMessage());
    }
}

// ─── USER CANCEL ORDER ────────────────────────────────────────────────────────
if ($action === 'user_cancel_order') {
    if ($role !== 'customer') respond(false, 'Unauthorized.');
    
    $orderId = (int)($data['order_id'] ?? 0);
    if (!$orderId) respond(false, 'Order ID required.');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("SELECT user_id, status, delivery_id FROM marketplace_orders WHERE id = ? FOR UPDATE");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();

        if (!$order) throw new Exception('Order not found.');
        if ($order['user_id'] != $userId) throw new Exception('Unauthorized to cancel this order.');
        if (!in_array($order['status'], ['placed', 'assigned'])) throw new Exception('Order can no longer be cancelled as processing has started.');

        // Restore stock
        $items = $pdo->prepare("SELECT product_id, quantity FROM marketplace_order_items WHERE order_id = ?");
        $items->execute([$orderId]);
        foreach ($items->fetchAll() as $item) {
            $pdo->prepare("UPDATE marketplace_products SET stock = stock + ? WHERE id = ?")->execute([$item['quantity'], $item['product_id']]);
        }

        // Decrement delivery 
        if ($order['delivery_id']) {
            $pdo->prepare("UPDATE users SET current_orders = GREATEST(0, current_orders - 1) WHERE id = ?")->execute([$order['delivery_id']]);
        }

        $pdo->prepare("UPDATE marketplace_orders SET status = 'cancelled', cancelled_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$orderId]);

        $pdo->commit();
        respond(true, 'Order cancelled successfully.');
    } catch (\Exception $e) {
        $pdo->rollBack();
        respond(false, $e->getMessage());
    }
}

respond(false, 'Unknown action.');
