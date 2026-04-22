<?php
/**
 * Invoice Module
 * Handles auto-generation of combined invoices and Razorpay integration.
 */
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

function respond($s, $m, $d = [])
{
    echo json_encode(array_merge(['success' => $s, 'message' => $m], $d));
    exit;
}

$data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$action = $data['action'] ?? ($_GET['action'] ?? '');

// Native browser print will be used instead of DomPDF

/**
 * Save / Get Receipt Header Configurations
 */
if ($action === 'get_receipt_settings') {
    $file = __DIR__ . '/receipt_settings.json';
    $defaults = [
        'store_name' => 'DigiWash',
        'tagline' => 'Premium Dry Cleaning Services',
        'address' => '123 Clean Street, Model Town',
        'phone' => '+91 9726232915',
        'email' => 'vikramvarma9726@gmail.com',
        'gst_no' => '',
        'footer_note' => 'Thank you for choosing DigiWash. For support, please contact us.'
    ];
    $sets = file_exists($file) ? json_decode(file_get_contents($file), true) : $defaults;
    respond(true, 'Settings', ['settings' => array_merge($defaults, $sets)]);
}
if ($action === 'save_receipt_settings') {
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$adminId)
        respond(false, 'Unauthorized');
    $file = __DIR__ . '/receipt_settings.json';
    file_put_contents($file, json_encode($data['settings'] ?? [], JSON_PRETTY_PRINT));
    respond(true, 'Receipt format updated successfully!');
}

/**
 * Automatically check and generate invoices for users with 4+ unpaid orders.
 * Can be called natively after every order creation, or run via Cron.
 */
if ($action === 'auto_generate') {
    // 1. Fetch users with 4 or more unpaid un-invoiced orders
    $stmt = $pdo->query("
        SELECT o.user_id, COUNT(o.id) as order_count, SUM(p.amount) as total_due
        FROM orders o
        JOIN payments p ON o.id = p.order_id
        WHERE p.status = 'remaining' AND o.invoice_id IS NULL
        GROUP BY o.user_id
        HAVING order_count >= 4
    ");
    $usersToInvoice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $generated = 0;
    foreach ($usersToInvoice as $u) {
        $userId = $u['user_id'];

        // Fetch the 4 oldest unpaid orders for this user
        $stmtOrders = $pdo->prepare("
            SELECT o.id, p.amount 
            FROM orders o JOIN payments p ON o.id = p.order_id 
            WHERE o.user_id = ? AND p.status = 'remaining' AND o.invoice_id IS NULL 
            ORDER BY o.created_at ASC LIMIT 4
        ");
        $stmtOrders->execute([$userId]);
        $orders = $stmtOrders->fetchAll(PDO::FETCH_ASSOC);

        if (count($orders) === 4) {
            $totalAmt = array_sum(array_column($orders, 'amount'));
            $invNo = 'INV-' . strtoupper(substr(md5(uniqid()), 0, 8));

            // Generate Invoice Record
            $insInv = $pdo->prepare("INSERT INTO invoices (user_id, invoice_no, description, amount, status) VALUES (?, ?, 'Combined Billing (4 Orders)', ?, 'unpaid')");
            $insInv->execute([$userId, $invNo, $totalAmt]);
            $invoiceId = $pdo->lastInsertId();

            // Link Orders to Invoice
            $updOrder = $pdo->prepare("UPDATE orders SET invoice_id = ? WHERE id = ?");
            foreach ($orders as $ord) {
                $updOrder->execute([$invoiceId, $ord['id']]);
            }
            $generated++;
        }
    }
    respond(true, "Auto-generation complete. Created $generated invoices.");
}

/**
 * Get all invoices for a user (or admin)
 */
if ($action === 'get_invoices') {
    $userId = $_SESSION['user_id'] ?? null;
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$userId && !$adminId)
        respond(false, 'Unauthorized');

    if ($adminId) {
        $stmt = $pdo->query("SELECT i.*, u.name as user_name, u.phone FROM invoices i JOIN users u ON i.user_id = u.id ORDER BY i.created_at DESC");
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        $stmt = $pdo->prepare("SELECT i.*, u.name as user_name, u.phone FROM invoices i JOIN users u ON i.user_id = u.id WHERE i.user_id = ? ORDER BY i.created_at DESC");
        $stmt->execute([$userId]);
        $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    foreach ($invoices as &$inv) {
        $stm = $pdo->prepare("SELECT o.id, o.created_at, o.status, p.amount FROM orders o LEFT JOIN payments p ON o.id = p.order_id WHERE o.invoice_id = ? ORDER BY o.created_at ASC");
        $stm->execute([$inv['id']]);
        $inv['orders'] = $stm->fetchAll(PDO::FETCH_ASSOC);
    }

    respond(true, 'Invoices fetched', ['invoices' => $invoices]);
}

/**
 * Download Invoice as PDF using DomPDF
 */
if ($action === 'download_pdf') {
    $invoiceId = $_GET['id'] ?? 0;
    $userId = $_SESSION['user_id'] ?? null;
    $adminId = $_SESSION['admin_id'] ?? null;
    if (!$userId && !$adminId)
        die("Unauthorized");

    // Fetch Invoice & User
    $stmt = $pdo->prepare("SELECT i.*, u.name as customer_name, u.phone, u.shop_address FROM invoices i JOIN users u ON i.user_id = u.id WHERE i.id = ?");
    $stmt->execute([$invoiceId]);
    $inv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$inv)
        die("Invoice not found.");
    if (!$adminId && $inv['user_id'] != $userId)
        die("Unauthorized Access");

    // Fetch linked orders
    $stmtOrd = $pdo->prepare("SELECT o.id as order_id, o.created_at, p.amount 
                              FROM orders o JOIN payments p ON o.id = p.order_id 
                              WHERE o.invoice_id = ?");
    $stmtOrd->execute([$invoiceId]);
    $orders = $stmtOrd->fetchAll(PDO::FETCH_ASSOC);

    $gstAmt = $inv['amount'] * 0.18; // Optional 18% GST Logic if needed
    $payStatus = strtoupper($inv['status']);
    $dateStr = date('d M Y', strtotime($inv['created_at']));

    // Load store context
    $file = __DIR__ . '/receipt_settings.json';
    $store = file_exists($file) ? json_decode(file_get_contents($file), true) : [
        'store_name' => 'DigiWash',
        'tagline' => 'Premium Dry Cleaning Services',
        'address' => '',
        'phone' => '+91 9726232915',
        'email' => 'vikramvarma9726@gmail.com',
        'gst_no' => '',
        'footer_note' => 'Thank you for choosing DigiWash.'
    ];

    // Build HTML for DomPDF
    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Helvetica', sans-serif; color: #333; }
            .header { border-bottom: 2px solid #6366f1; padding-bottom: 10px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #6366f1; }
            .details { width: 100%; margin-bottom: 20px; }
            .details td { vertical-align: top; }
            table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            table.items th, table.items td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            table.items th { background: #f8fafc; }
            .total { text-align: right; font-size: 18px; font-weight: bold; }
            .badge { padding: 5px 10px; border-radius: 4px; color: white; display: inline-block; font-size:12px; }
            .b-paid { background: #10b981; }
            .b-unpaid { background: #ef4444; }
        </style>
    </head>
    <body>
        <div class='header'>
            <table width='100%'>
                <tr>
                    <td>
                        <div class='logo'>{$store['store_name']}</div>
                        <div style='font-size:12px;color:#666;margin-top:4px;'>
                            {$store['tagline']}<br>
                            " . nl2br($store['address']) . "<br>
                            {$store['phone']} | {$store['email']} <br>
                            <b>GSTIN:</b> {$store['gst_no']}
                        </div>
                    </td>
                    <td align='right'>
                        <h2>INVOICE</h2>
                        <strong>No:</strong> {$inv['invoice_no']}<br>
                        <strong>Date:</strong> {$dateStr}
                    </td>
                </tr>
            </table>
        </div>
        
        <table class='details'>
            <tr>
                <td width='50%'>
                    <strong>Billed To:</strong><br>
                    {$inv['customer_name']}<br>
                    {$inv['phone']}<br>
                    {$inv['shop_address']}
                </td>
                <td width='50%' align='right'>
                    <strong>Status:</strong><br>
                    <span class='badge " . ($payStatus === 'PAID' ? 'b-paid' : 'b-unpaid') . "'>{$payStatus}</span>
                </td>
            </tr>
        </table>

        <table class='items'>
            <thead>
                <tr>
                    <th>Order #</th>
                    <th>Date</th>
                    <th>Description</th>
                    <th align='right'>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>";

    foreach ($orders as $o) {
        // We could fetch specific order items here and nest them, but grouping by order is cleaner for combined bills.
        $html .= "<tr>
                            <td>Order #{$o['order_id']}</td>
                            <td>" . date('d M Y', strtotime($o['created_at'])) . "</td>
                            <td>Laundry Service</td>
                            <td align='right'>" . number_format($o['amount'], 2) . "</td>
                          </tr>";
    }

    $html .= "
            </tbody>
        </table>
        
        <div class='total'>
            <p style='font-size:14px;font-weight:normal;margin:5px 0;'>Subtotal: ₹" . number_format($inv['amount'], 2) . "</p>
            <hr style='border:0;border-top:1px solid #ddd;margin:5px 0;'>
            Grand Total: ₹" . number_format($inv['amount'], 2) . "
        </div>
        
        <div style='margin-top:50px; font-size:12px; color:#666; text-align:center;'>
            Thank you for choosing DigiWash. For support, contact support@digiwash.com
        </div>
    </body>
    </html>";

    $html .= "<script>window.onload = function() { window.print(); setTimeout(function(){ window.close(); }, 500); }</script>";
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/**
 * Download Invoice as PDF for a single Standard Order
 */
if ($action === 'download_order_pdf') {
    $orderId = $_GET['order_id'] ?? 0;
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId && !isset($_SESSION['admin_id']))
        die("Unauthorized");

    // Fetch Order & User
    $stmt = $pdo->prepare("SELECT o.*, u.name as customer_name, u.phone, u.shop_address, p.payment_mode, p.status as payment_status 
                           FROM orders o 
                           JOIN users u ON o.user_id = u.id 
                           LEFT JOIN payments p ON o.id = p.order_id 
                           WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $ord = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ord)
        die("Order not found.");
    if (!isset($_SESSION['admin_id']) && $ord['user_id'] != $userId)
        die("Unauthorized Access");

    // Fetch Items
    $stmtItm = $pdo->prepare("SELECT product_name, size_label, quantity, price, line_total FROM order_items WHERE order_id = ?");
    $stmtItm->execute([$orderId]);
    $items = $stmtItm->fetchAll(PDO::FETCH_ASSOC);

    $payStatus = strtoupper($ord['payment_status'] === 'completed' ? 'PAID' : 'UNPAID');
    $ordStatus = strtoupper(str_replace('_', ' ', $ord['status']));
    $dateStr = date('d M Y, h:i A', strtotime($ord['created_at']));

    // Load store context
    $file = __DIR__ . '/receipt_settings.json';
    $store = file_exists($file) ? json_decode(file_get_contents($file), true) : [
        'store_name' => 'DigiWash Laundry',
        'tagline' => '',
        'address' => '',
        'phone' => '',
        'email' => '',
        'gst_no' => '',
        'footer_note' => 'Thank you for choosing DigiWash.'
    ];

    $html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: 'Helvetica', sans-serif; color: #333; }
            .header { border-bottom: 2px solid #6366f1; padding-bottom: 10px; margin-bottom: 20px; }
            .logo { font-size: 24px; font-weight: bold; color: #6366f1; }
            .details { width: 100%; margin-bottom: 20px; }
            .details td { vertical-align: top; }
            table.items { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
            table.items th, table.items td { border: 1px solid #ddd; padding: 10px; text-align: left; }
            table.items th { background: #f8fafc; }
            .total { text-align: right; font-size: 18px; font-weight: bold; }
            .badge { padding: 5px 10px; border-radius: 4px; color: white; display: inline-block; font-size:12px; }
            .b-paid { background: #10b981; }
            .b-unpaid { background: #ef4444; }
        </style>
    </head>
    <body>
        <div class='header'>
            <table width='100%'>
                <tr>
                    <td>
                        <div class='logo'>{$store['store_name']}</div>
                        <div style='font-size:12px;color:#666;margin-top:4px;'>
                            {$store['tagline']}<br>
                            " . nl2br($store['address']) . "<br>
                            {$store['phone']} | {$store['email']} <br>
                            <b>GSTIN:</b> {$store['gst_no']}
                        </div>
                    </td>
                    <td align='right'>
                        <h2>RECEIPT</h2>
                        <strong>Order #:</strong> {$ord['id']}<br>
                        <strong>Date:</strong> {$dateStr}<br><br>
                        <strong>Order Status:</strong><br>
                        <span class='badge' style='background:#64748b;'>{$ordStatus}</span>
                    </td>
                </tr>
            </table>
        </div>
        
        <table class='details'>
            <tr>
                <td width='50%'>
                    <strong>Billed To:</strong><br>
                    {$ord['customer_name']}<br>
                    {$ord['phone']}<br>
                    {$ord['shop_address']}
                </td>
                <td width='50%' align='right'>
                    <strong>Status:</strong><br>
                    <span class='badge " . ($payStatus === 'PAID' ? 'b-paid' : 'b-unpaid') . "'>{$payStatus}</span><br><br>
                    <strong>Payment Mode:</strong><br>
                    " . str_replace('_', ' ', $ord['payment_mode'] ?? 'N/A') . "
                </td>
            </tr>
        </table>

        <table class='items'>
            <thead>
                <tr>
                    <th>Item Description</th>
                    <th>Qty</th>
                    <th align='right'>Price (₹)</th>
                    <th align='right'>Total (₹)</th>
                </tr>
            </thead>
            <tbody>";

    if (empty($items)) {
        $html .= "<tr><td colspan='4'>Laundry Service (Requested)</td></tr>";
    } else {
        foreach ($items as $item) {
            $html .= "<tr>
                                <td>{$item['product_name']} ({$item['size_label']})</td>
                                <td>{$item['quantity']}</td>
                                <td align='right'>{$item['price']}</td>
                                <td align='right'>{$item['line_total']}</td>
                              </tr>";
        }
    }

    $html .= "
            </tbody>
        </table>
        
        <div class='total'>
            <p style='font-size:14px;font-weight:normal;margin:5px 0;'>Subtotal: ₹" . number_format($ord['total_amount'], 2) . "</p>
            <hr style='border:0;border-top:1px solid #ddd;margin:5px 0;'>
            Grand Total: ₹" . number_format($ord['total_amount'], 2) . "
        </div>
        
        <div style='margin-top:50px; font-size:12px; color:#666; text-align:center;'>
            Thank you for choosing DigiWash. For support, contact support@digiwash.com
        </div>
    </body>
    </html>";

    $html .= "<script>window.onload = function() { window.print(); setTimeout(function(){ window.close(); }, 500); }</script>";
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
    exit;
}

/**
 * Initiate Razorpay payment link for an Invoice
 */
if ($action === 'initiate_payment') {
    $invId = $data['invoice_id'] ?? 0;
    $userId = $_SESSION['user_id'] ?? null;
    if (!$userId)
        respond(false, 'Unauthorized');

    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND user_id = ? AND status = 'unpaid'");
    $stmt->execute([$invId, $userId]);
    $inv = $stmt->fetch();

    if (!$inv)
        respond(false, 'Invoice not found or already paid.');

    $rzpId = getenv('RAZORPAY_KEY_ID');
    $rzpSec = getenv('RAZORPAY_KEY_SECRET');
    if (!$rzpId)
        respond(false, 'Razorpay not configured.');

    $amountInPaise = round($inv['amount'] * 100);

    $api_url = "https://api.razorpay.com/v1/orders";
    $auth = base64_encode("$rzpId:$rzpSec");
    $payload = [
        "amount" => $amountInPaise,
        "currency" => "INR",
        "receipt" => $inv['invoice_no'],
        "notes" => ["invoice_id" => $invId]
    ];

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json", "Authorization: Basic $auth"]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    // curl_close($ch); // Deprecated in PHP >= 8.0

    $resData = json_decode($response, true);
    if ($httpCode === 200 && isset($resData['id'])) {
        $pdo->prepare("UPDATE invoices SET rzp_order_id = ? WHERE id = ?")->execute([$resData['id'], $invId]);
        respond(true, 'Ready', ['rzp_order_id' => $resData['id'], 'amount' => $amountInPaise, 'key' => $rzpId]);
    } else {
        respond(false, 'Failed to launch gateway: ' . ($resData['error']['description'] ?? ''));
    }
}

/**
 * Update payment status after successful payment
 */
if ($action === 'verify_payment') {
    $rzpPaymentId = $data['razorpay_payment_id'] ?? '';
    $rzpOrderId = $data['razorpay_order_id'] ?? '';
    $rzpSignature = $data['razorpay_signature'] ?? '';
    $invId = $data['invoice_id'] ?? 0;

    $rzpSec = getenv('RAZORPAY_KEY_SECRET');
    $expected = hash_hmac('sha256', $rzpOrderId . "|" . $rzpPaymentId, $rzpSec);

    if (hash_equals($expected, $rzpSignature)) {
        try {
            $pdo->beginTransaction();
            // 1. Mark invoice paid
            $pdo->prepare("UPDATE invoices SET status = 'paid', rzp_payment_id = ?, updated_at = NOW() WHERE id = ? AND rzp_order_id = ?")
                ->execute([$rzpPaymentId, $invId, $rzpOrderId]);

            // 2. Mark associated orders' payments as paid
            $pdo->prepare("UPDATE payments p JOIN orders o ON p.order_id = o.id SET p.status = 'completed' WHERE o.invoice_id = ?")
                ->execute([$invId]);

            $pdo->commit();
            respond(true, 'Invoice Paid Successfully!');
        } catch (Exception $e) {
            $pdo->rollBack();
            respond(false, 'Database Error: ' . $e->getMessage());
        }
    } else {
        respond(false, 'Signature verification failed.');
    }
}

respond(false, 'Invalid action.');
