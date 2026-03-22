<?php
require_once __DIR__ . '/config.php';

// This file is designed to be executed via a system chron job daily (e.g. at 7:00 AM)
// It identifies users scheduled for Auto Pickup today and creates their dummy order
// strictly enforcing their custom Pay Later bounds or COD limits.

try {
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT id, auto_order_frequency, auto_order_next_date, pay_later_plan, pay_later_status FROM users WHERE auto_order_next_date IS NOT NULL AND auto_order_next_date <= ?");
    $stmt->execute([$today]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $ran = 0;
    foreach ($users as $u) {
        $uid = $u['id'];
        $freq = $u['auto_order_frequency'];
        $payMode = 'COD';
        
        // Match their active limit plan
        if ($u['pay_later_status'] === 'approved' && $u['pay_later_plan'] !== 'NONE') {
            $payMode = $u['pay_later_plan'];
        }
        
        $limitReached = false;
        if (strpos($payMode, 'PAY_LATER') !== false) {
            $limit = (int)str_replace('PAY_LATER_', '', $payMode);
            $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'remaining' AND payment_mode LIKE 'PAY_LATER%'");
            $chk->execute([$uid]);
            if ($chk->fetchColumn() >= $limit) $limitReached = true;
        } else {
            $chk = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE user_id = ? AND status = 'remaining' AND payment_mode = 'COD'");
            $chk->execute([$uid]);
            if ($chk->fetchColumn() >= 4) $limitReached = true;
        }
        
        // If they still have limits available, execute the subscription order
        if (!$limitReached) {
            $pdo->beginTransaction();
            $ins = $pdo->prepare("INSERT INTO orders (user_id, status, total_amount, payment_status, instructions) VALUES (?, 'pending', 0, 'remaining', 'SUBSCRIPTION_AUTO_PICKUP')");
            $ins->execute([$uid]);
            $orderId = $pdo->lastInsertId();
            
            $pdo->prepare("INSERT INTO payments (user_id, order_id, payment_mode, status, amount) VALUES (?, ?, ?, 'remaining', 0)")->execute([$uid, $orderId, $payMode]);
            
            $nextDate = null;
            if ($freq === 'MONDAYS') {
                $nextDate = date('Y-m-d', strtotime('next monday'));
            }
            
            $pdo->prepare("UPDATE users SET auto_order_next_date = ? WHERE id = ?")->execute([$nextDate, $uid]);
            $pdo->commit();
            $ran++;
        } else {
            // Limits hit! Cannot auto-order. We will slide the date +1 day to retry.
            // Or wait exactly cycle time. For safety, slide +1 day so it tries again tomorrow.
            $nextDate = date('Y-m-d', strtotime('+1 day'));
            $pdo->prepare("UPDATE users SET auto_order_next_date = ? WHERE id = ?")->execute([$nextDate, $uid]);
        }
    }
    
    echo "CRON SUCCESS: Auto orders correctly evaluated and deployed ($ran completed).\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "CRON ERROR: " . $e->getMessage() . "\n";
}
