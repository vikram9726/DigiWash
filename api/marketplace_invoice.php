<?php
require_once '../config.php';

$orderId = (int)($_GET['order_id'] ?? 0);
$userId  = $_SESSION['user_id'] ?? 0;
$role    = $_SESSION['role']    ?? '';

if (!$userId) { http_response_code(401); die('Login required.'); }
if (!$orderId) { http_response_code(400); die('Order ID required.'); }

// Fetch order
$stmt = $pdo->prepare("
    SELECT mo.*, 
           u.name as user_name, u.phone as user_phone, u.shop_address, u.email as user_email,
           m.name as market_name,
           d.name as delivery_name, d.phone as delivery_phone
    FROM marketplace_orders mo
    JOIN users u ON mo.user_id = u.id
    LEFT JOIN markets m ON u.market_id = m.id
    LEFT JOIN users d ON mo.delivery_id = d.id
    WHERE mo.id = ?
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) { http_response_code(404); die('Order not found.'); }

// Access control: user can only see their own orders, admin sees all
if ($role === 'customer' && (int)$order['user_id'] !== (int)$userId) {
    http_response_code(403); die('Access denied.');
}

// Fetch items
$iStmt = $pdo->prepare("
    SELECT i.*, p.name as product_name, p.category, p.size
    FROM marketplace_order_items i
    JOIN marketplace_products p ON i.product_id = p.id
    WHERE i.order_id = ?
");
$iStmt->execute([$orderId]);
$items = $iStmt->fetchAll();

$invoiceNo  = $order['invoice_no'] ?: 'MKT-' . strtoupper(substr(md5($orderId), 0, 8));
$orderDate  = date('d M Y, h:i A', strtotime($order['created_at']));
$payType    = strtoupper($order['payment_type']);
$payStatus  = strtoupper($order['payment_status']);
$orderStatus = strtoupper(str_replace('_', ' ', $order['status']));
$total      = number_format((float)$order['total_amount'], 2);

// Build items rows
$itemRows = '';
$subtotal = 0;
foreach ($items as $item) {
    $desc    = $item['product_name'] . ' (' . $item['category'] . ')';
    $detail  = '';
    if (!empty($item['width_label']) && !empty($item['length_meters'])) {
        $detail = '<br><small style="color:#64748b;">' . htmlspecialchars($item['width_label']) . ' × ' . $item['length_meters'] . 'm</small>';
    } elseif (!empty($item['size'])) {
        $detail = '<br><small style="color:#64748b;">Size: ' . htmlspecialchars($item['size']) . '</small>';
    }
    $lineTotal = (float)$item['price'];
    $subtotal += $lineTotal;
    $itemRows .= "<tr>
        <td style='padding:10px 14px;border-bottom:1px solid #f1f5f9;'>" . htmlspecialchars($desc) . $detail . "</td>
        <td style='padding:10px 14px;border-bottom:1px solid #f1f5f9;text-align:center;'>{$item['quantity']}</td>
        <td style='padding:10px 14px;border-bottom:1px solid #f1f5f9;text-align:right;'>₹" . number_format($lineTotal, 2) . "</td>
    </tr>";
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{font-family:DejaVu Sans,sans-serif;font-size:13px;color:#1e293b;background:#fff;}
  .wrap{padding:40px;}
  .header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;}
  .brand{font-size:22px;font-weight:700;color:#ec4899;letter-spacing:-0.5px;}
  .brand-sub{font-size:11px;color:#94a3b8;margin-top:2px;}
  .inv-meta{text-align:right;font-size:12px;color:#64748b;}
  .inv-meta .inv-num{font-size:18px;font-weight:700;color:#0f172a;margin-bottom:4px;}
  .divider{border:none;border-top:2px solid #ec4899;margin:0 0 24px;}
  .parties{display:table;width:100%;margin-bottom:28px;}
  .party-box{display:table-cell;width:50%;vertical-align:top;padding-right:20px;}
  .party-box:last-child{padding-right:0;padding-left:20px;text-align:right;}
  .party-lbl{font-size:10px;font-weight:700;text-transform:uppercase;color:#94a3b8;letter-spacing:1px;margin-bottom:6px;}
  .party-name{font-weight:700;font-size:15px;color:#0f172a;margin-bottom:3px;}
  .party-detail{font-size:12px;color:#64748b;line-height:1.6;}
  table{width:100%;border-collapse:collapse;margin-bottom:16px;}
  thead tr{background:#0f172a;color:white;}
  thead th{padding:10px 14px;text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
  thead th:last-child{text-align:right;}
  thead th:nth-child(2){text-align:center;}
  .totals{float:right;width:220px;}
  .totals table{font-size:13px;}
  .totals td{padding:5px 10px;}
  .totals .grand{background:#ec4899;color:white;font-weight:700;font-size:15px;border-radius:6px;}
  .totals .grand td{padding:8px 10px;}
  .status-row{margin-top:16px;clear:both;display:table;width:100%;}
  .status-chip{display:inline-block;padding:4px 12px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.5px;}
  .paid-chip{background:#dcfce7;color:#16a34a;}
  .due-chip{background:#fef3c7;color:#b45309;}
  .footer{margin-top:36px;padding-top:18px;border-top:1px solid #e2e8f0;display:table;width:100%;}
  .footer-l{display:table-cell;font-size:11px;color:#94a3b8;}
  .footer-r{display:table-cell;text-align:right;font-size:11px;color:#94a3b8;}
</style>
</head>
<body>
<div class="wrap">
  <div class="header">
    <div>
      <div class="brand">🧺 DigiMarket</div>
      <div class="brand-sub">DigiWash Marketplace · Laundry & Essentials Platform</div>
    </div>
    <div class="inv-meta">
      <div class="inv-num">Invoice {$invoiceNo}</div>
      <div>Date: {$orderDate}</div>
      <div>Order ID: #{$orderId}</div>
    </div>
  </div>

  <hr class="divider">

  <div class="parties">
    <div class="party-box">
      <div class="party-lbl">Billed To</div>
      <div class="party-name">{$order['user_name']}</div>
      <div class="party-detail">
        {$order['user_phone']}<br>
        {$order['shop_address']}<br>
        {$order['market_name']}
      </div>
    </div>
    <div class="party-box">
      <div class="party-lbl">Payment Details</div>
      <div class="party-detail">
        <b>Method:</b> {$payType}<br>
        <b>Status:</b> {$payStatus}<br>
        <b>Order Status:</b> {$orderStatus}
HTML;
if ($order['razorpay_payment_id']) {
    $html .= '<br><b>RZP ID:</b> ' . htmlspecialchars($order['razorpay_payment_id']);
}
$html .= <<<HTML
      </div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:60%;">Item Description</th>
        <th style="width:15%;">Qty</th>
        <th style="width:25%;">Amount</th>
      </tr>
    </thead>
    <tbody>
      {$itemRows}
    </tbody>
  </table>

  <div class="totals">
    <table>
      <tr><td style="color:#64748b;">Subtotal</td><td style="text-align:right;">₹{$total}</td></tr>
      <tr><td style="color:#64748b;">Delivery</td><td style="text-align:right;color:#10b981;font-weight:700;">FREE</td></tr>
      <tr class="grand"><td>Total</td><td style="text-align:right;">₹{$total}</td></tr>
    </table>
  </div>

  <div class="status-row">
    <span class="status-chip {$payChip}">{$payStatus}</span>
  </div>

  <div class="footer">
    <div class="footer-l">Thank you for shopping at DigiMarket!<br>For queries, contact your nearest DigiWash centre.</div>
    <div class="footer-r">Generated by DigiWash Platform<br>{$orderDate}</div>
  </div>
</div>
</body>
</html>
HTML;

// Replace placeholder
$payChipClass = ($order['payment_status'] === 'paid') ? 'paid-chip' : 'due-chip';
$html = str_replace('{$payChip}', $payChipClass, $html);

// Render directly to browser for Native HTML Printing
$html .= "<script>window.onload = function() { window.print(); setTimeout(function(){ window.close(); }, 500); }</script>";
header('Content-Type: text/html; charset=utf-8');
echo $html;
exit;
