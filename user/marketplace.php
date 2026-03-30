<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../index.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$needsProfileSetup = empty($user['name']) || empty($user['shop_address']) || empty($user['market_id']);

$csrfToken = $_SESSION['csrf_token'] ?? '';
$userName = htmlspecialchars($user['name'] ?? 'User');
$userPhone = htmlspecialchars($user['phone'] ?? '');

$payLaterPlan = $user['pay_later_plan'] ?? 'NONE';
$payLaterStatus = $user['pay_later_status'] ?? 'locked';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
$stmt->execute([$_SESSION['user_id']]);
$completedLaundryOrders = (int) $stmt->fetchColumn();
$isEligibleForPayLater = ($completedLaundryOrders >= 4);

$availableOrders = 0;
if ($isEligibleForPayLater) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM marketplace_orders WHERE user_id = ? AND payment_type = 'credit' AND payment_status = 'pending'");
    $stmt->execute([$_SESSION['user_id']]);
    $unpaidCreditOrders = (int) $stmt->fetchColumn();
    $availableOrders = max(0, 4 - $unpaidCreditOrders);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash — Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0
        }

        :root {
            --bg: #f8fafc;
            --sidebar-bg: #0f172a;
            --card: white;
            --primary: #ec4899;
            --primary-d: #be185d;
            --success: #10b981;
            --danger: #ef4444;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --sidebar-w: 240px;
            --radius: 16px;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .app-wrap {
            display: grid;
            grid-template-columns: var(--sidebar-w) 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: var(--sidebar-bg);
            display: flex;
            flex-direction: column;
            padding: 1.5rem 1rem;
            position: sticky;
            top: 0;
            height: 100vh;
            overflow-y: auto;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            color: white;
            font-weight: 900;
            font-size: 1.15rem;
            padding: 0.5rem 0.75rem 1.25rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
            margin-bottom: 0.75rem;
        }

        .sidebar-brand i {
            color: var(--primary);
            font-size: 1.8rem;
        }

        .user-chip {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0.75rem;
            background: rgba(255, 255, 255, 0.06);
            border-radius: 12px;
            margin-bottom: 1rem;
        }

        .user-av {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #6366f1, #4f46e5);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 900;
            font-size: 1rem;
        }

        .user-info-name {
            color: white;
            font-weight: 700;
            font-size: 0.85rem;
        }

        .user-info-phone {
            color: #64748b;
            font-size: 0.72rem;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0.7rem 1rem;
            border-radius: 10px;
            color: #94a3b8;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.18s;
            text-decoration: none;
            margin-bottom: 5px;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.06);
            color: white;
        }

        .nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--primary-d));
            color: white;
            box-shadow: 0 4px 12px rgba(236, 72, 153, 0.35);
        }

        .nav-item i {
            font-size: 1.2rem;
        }

        /* Staff rep button in sidebar */
        .staff-rep-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: .75rem 1rem;
            border-radius: 12px;
            background: rgba(99, 102, 241, 0.12);
            border: 1px solid rgba(99, 102, 241, 0.25);
            color: #a5b4fc;
            font-weight: 700;
            font-size: .85rem;
            cursor: pointer;
            transition: all .2s;
            margin-top: .5rem;
            width: 100%;
            text-align: left;
        }

        .staff-rep-btn:hover {
            background: rgba(99, 102, 241, 0.22);
            color: white;
        }

        .staff-rep-btn i {
            font-size: 1.3rem;
            color: #818cf8;
        }

        .main {
            padding: 2rem;
            overflow-y: auto;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--text);
        }

        .page-title span {
            color: var(--primary);
        }

        .section {
            display: none;
        }

        .section.active {
            display: block;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(12px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .filter-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 1.5rem;
            flex-wrap: wrap;
        }

        .filter-select {
            padding: .6rem 1rem;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-weight: 600;
            color: var(--text);
            outline: none;
            background: white;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .product-card {
            background: white;
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            border: 1.5px solid transparent;
            transition: all .2s;
            display: flex;
            flex-direction: column;
        }

        .product-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.08);
            border-color: var(--primary);
        }

        .prod-img {
            height: 160px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .prod-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .prod-img i {
            font-size: 4rem;
            color: #cbd5e1;
        }

        .prod-body {
            padding: 1.2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .prod-cat {
            font-size: .7rem;
            font-weight: 800;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .prod-name {
            font-weight: 800;
            font-size: 1.05rem;
            color: var(--text);
            margin-bottom: 4px;
        }

        .prod-size {
            font-size: .8rem;
            color: var(--muted);
            margin-bottom: 6px;
        }

        .prod-price {
            font-size: 1.1rem;
            font-weight: 900;
            color: var(--text);
            margin-bottom: 10px;
        }

        /* Width chips on card */
        .width-chips {
            display: flex;
            flex-wrap: wrap;
            gap: 5px;
            margin-bottom: 10px;
        }

        .width-chip {
            padding: 3px 8px;
            border-radius: 6px;
            font-size: .72rem;
            font-weight: 800;
            background: #ede9fe;
            color: #6d28d9;
            cursor: pointer;
            border: 1.5px solid transparent;
            transition: .15s;
        }

        .width-chip.selected {
            border-color: #6d28d9;
            background: #6d28d9;
            color: white;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: .7rem 1.2rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            cursor: pointer;
            border: none;
            transition: all .15s;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-d));
            color: white;
        }

        .btn-outline {
            background: white;
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-ghost {
            background: #f1f5f9;
            color: var(--muted);
        }

        .btn:hover:not([disabled]) {
            filter: brightness(.92);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .btn[disabled] {
            opacity: .5;
            cursor: not-allowed;
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            background: white;
            border-radius: 12px;
            margin-bottom: 10px;
            border: 1px solid var(--border);
        }

        .cart-item-name {
            font-weight: 800;
        }

        .cart-item-meta {
            font-size: .8rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .cart-item-price {
            font-weight: 900;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .qty-ctrl {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 15px;
            background: #f1f5f9;
            padding: 5px;
            border-radius: 8px;
        }

        .qty-btn {
            width: 28px;
            height: 28px;
            border: none;
            background: white;
            border-radius: 6px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .1);
        }

        .qty-val {
            font-weight: 800;
            min-width: 20px;
            text-align: center;
        }

        .order-card {
            background: white;
            padding: 1.5rem;
            border-radius: 14px;
            margin-bottom: 1rem;
            border: 1px solid var(--border);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            padding-bottom: .8rem;
            margin-bottom: .8rem;
        }

        .order-meta div {
            font-size: .85rem;
            color: var(--muted);
        }

        .order-status {
            font-weight: 800;
            font-size: .85rem;
            padding: 3px 10px;
            border-radius: 20px;
        }

        .st-placed {
            background: #fef3c7;
            color: #b45309;
        }

        .st-assigned {
            background: #dbeafe;
            color: #1d4ed8;
        }

        .st-delivered {
            background: #dcfce7;
            color: #15803d;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.55);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            width: 90%;
            max-width: 480px;
            max-height: 90vh;
            overflow-y: auto;
            animation: fadeUp .25s ease;
            position: relative;
        }

        @keyframes fadeUp {
            from {
                opacity: 0;
                transform: translateY(10px)
            }

            to {
                opacity: 1;
                transform: translateY(0)
            }
        }

        .modal-close {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: #f1f5f9;
            border: none;
            border-radius: 8px;
            width: 32px;
            height: 32px;
            cursor: pointer;
            font-size: 1rem;
            color: var(--muted);
        }

        .modal-title {
            font-size: 1.15rem;
            font-weight: 900;
            margin-bottom: 1.2rem;
        }

        /* Timeline */
        .timeline {
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            margin: 1.5rem 0 1rem;
            padding: 0 10px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 20px;
            right: 20px;
            height: 3px;
            background: var(--border);
            z-index: 1;
        }

        .tl-step {
            position: relative;
            z-index: 2;
            display: flex;
            flex-direction: column;
            align-items: center;
            background: white;
            padding: 0 5px;
        }

        .tl-dot {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: var(--border);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            font-weight: 900;
            margin-bottom: 6px;
            transition: .3s;
        }

        .tl-lbl {
            font-size: .65rem;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
            text-align: center;
        }

        .tl-step.done .tl-dot {
            background: var(--primary);
        }

        .tl-step.done .tl-lbl {
            color: var(--text);
        }

        .tl-step.current .tl-dot {
            background: white;
            border: 3px solid var(--primary);
            color: var(--primary);
            box-shadow: 0 0 0 3px rgba(236, 72, 153, 0.2);
        }

        .tl-step.current .tl-lbl {
            color: var(--primary);
        }

        #toast-wrap {
            position: fixed;
            top: 1.5rem;
            right: 1.5rem;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toast {
            background: white;
            border-left: 4px solid var(--primary);
            padding: 1rem 1.2rem;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, .15);
            animation: slideIn .3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%)
            }

            to {
                transform: translateX(0)
            }
        }

        /* Length input */
        .length-input-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
        }

        .length-input-row input {
            flex: 1;
            padding: .45rem .7rem;
            border: 1.5px solid var(--border);
            border-radius: 8px;
            font-family: inherit;
            font-size: .88rem;
            outline: none;
        }

        .length-input-row input:focus {
            border-color: var(--primary);
        }

        .length-input-row span {
            font-size: .82rem;
            color: var(--muted);
            font-weight: 700;
            white-space: nowrap;
        }

        @media(max-width:768px) {
            .app-wrap {
                grid-template-columns: 1fr;
            }

            .sidebar {
                display: none;
            }

            .main {
                padding: 1rem;
            }
        }
    </style>
</head>

<body>
    <div id="toast-wrap"></div>

    <div class="app-wrap">
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="material-icons-outlined">storefront</i> DigiMarket
            </div>
            <div class="user-chip">
                <div class="user-av"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                <div>
                    <div class="user-info-name"><?= $userName ?></div>
                    <div class="user-info-phone"><?= $userPhone ?></div>
                </div>
            </div>

            <a href="dashboard.php" class="nav-item">
                <i class="material-icons-outlined">arrow_back</i> Back to Laundry
            </a>
            <div class="nav-item active" id="nav-shop" onclick="switchView('shop',this)">
                <i class="material-icons-outlined">storefront</i> Shop
            </div>
            <div class="nav-item" id="nav-cart" onclick="switchView('cart',this)">
                <i class="material-icons-outlined">shopping_cart</i> My Cart
                <span id="cartBadge"
                    style="background:var(--primary);color:white;padding:2px 8px;border-radius:10px;font-size:.7rem;margin-left:auto;display:none;">0</span>
            </div>
            <div class="nav-item" id="nav-orders" onclick="switchView('orders',this)">
                <i class="material-icons-outlined">inventory_2</i> My Orders
            </div>

            <div style="margin-top:auto;padding-top:1rem;border-top:1px solid rgba(255,255,255,0.08);">
                <button class="staff-rep-btn" onclick="openStaffModal()">
                    <i class="material-icons-outlined">support_agent</i>
                    Ask Staff Representative
                </button>
            </div>
        </aside>

        <main class="main">
            <?php if ($needsProfileSetup): ?>
                <div
                    style="background:#fef3c7;border:1px solid #fcd34d;padding:1rem;border-radius:12px;margin-bottom:1rem;color:#92400e;font-weight:600;display:flex;align-items:center;gap:10px;">
                    <i class="material-icons-outlined">warning</i>
                    Please complete your profile (Address/Market) in the Laundry Dashboard to place marketplace orders.
                </div>
            <?php endif; ?>

            <!-- SHOP SECTION -->
            <section id="shop" class="section active">
                <div class="page-header">
                    <div class="page-title">DigiWash <span>Marketplace</span></div>
                    <div style="color:var(--muted);font-weight:600;">Essentials for your home.</div>
                </div>

                <div class="filter-bar">
                    <select id="catFilter" class="filter-select" onchange="renderProducts()">
                        <option value="all">All Categories</option>
                    </select>
                </div>

                <div id="loading" style="text-align:center;padding:3rem;color:var(--muted);font-weight:600;">Loading
                    products...</div>
                <div class="products-grid" id="productsObj"></div>
            </section>

            <!-- CART SECTION -->
            <section id="cart" class="section">
                <div class="page-header">
                    <div class="page-title">Your <span>Cart</span></div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 350px;gap:2rem;" id="cartLayout">
                    <div id="cartItemsList"></div>
                    <div>
                        <div
                            style="background:white;padding:1.5rem;border-radius:14px;border:1px solid var(--border);position:sticky;top:2rem;">
                            <h3
                                style="font-weight:900;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:.8rem;">
                                Order Summary</h3>
                            <div
                                style="display:flex;justify-content:space-between;margin-bottom:.5rem;color:var(--muted);font-weight:600;">
                                <span>Subtotal</span><span id="cartSubtotal">₹0</span>
                            </div>
                            <div
                                style="display:flex;justify-content:space-between;margin-bottom:1rem;font-weight:900;font-size:1.3rem;color:var(--text);border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem;">
                                <span>Total</span><span id="cartTotal">₹0</span>
                            </div>
                            <div style="margin-bottom:1.2rem;">
                                <label
                                    style="font-size:.8rem;font-weight:800;color:var(--muted);margin-bottom:5px;display:block;">PAYMENT
                                    METHOD</label>
                                <select id="paymentType" class="filter-select" style="width:100%;"
                                    onchange="togglePayLaterInfo()">
                                    <option value="online">Pay Now (Online)</option>
                                    <?php if ($isEligibleForPayLater): ?>
                                        <option value="credit">Pay Later (Credit)</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($isEligibleForPayLater): ?>
                                    <div id="payLaterInfo"
                                        style="margin-top:8px;font-size:.75rem;padding:8px;background:#f8fafc;border-radius:8px;border:1px solid var(--border); display:none;">
                                        <div style="color:var(--muted);margin-bottom:4px;font-weight:600;">Pay after 4
                                            orders</div>
                                        <span style="color:var(--muted);">Available Orders:</span>
                                        <span style="font-weight:800;color:var(--primary);"><?= $availableOrders ?></span>
                                    </div>
                                <?php else: ?>
                                    <div style="margin-top:8px;font-size:.7rem;color:#b45309;font-weight:600;">
                                        <i class="material-icons-outlined"
                                            style="font-size:1rem;vertical-align:middle;">info</i>
                                        Complete <?= max(0, 4 - $completedLaundryOrders) ?> more laundry orders to unlock
                                        Pay Later.
                                    </div>
                                <?php endif; ?>
                            </div>
                            <button class="btn btn-primary" id="checkoutBtn" style="width:100%;font-size:1.05rem;"
                                onclick="checkout()" <?= $needsProfileSetup ? 'disabled' : '' ?>>Place Order</button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ORDERS SECTION -->
            <section id="orders" class="section">
                <div class="page-header">
                    <div class="page-title">Marketplace <span>Orders</span></div>
                </div>
                <div id="ordersListLoading" style="text-align:center;padding:2rem;">Loading orders...</div>
                <div id="myOrdersWrap"></div>
            </section>
        </main>
    </div>

    <!-- Add to Cart Modal (for per-meter products with widths) -->
    <div class="modal-overlay" id="configModal">
        <div class="modal-box">
            <button class="modal-close"
                onclick="document.getElementById('configModal').classList.remove('open')">✕</button>
            <div class="modal-title" id="configProdName">Configure Product</div>
            <input type="hidden" id="configProdId">

            <!-- Width selector -->
            <div id="widthSection" style="margin-bottom:1rem;">
                <div style="font-size:.82rem;font-weight:800;color:#475569;margin-bottom:8px;">SELECT WIDTH</div>
                <div id="widthChipsModal" class="width-chips"></div>
                <div id="widthError" style="color:var(--danger);font-size:.78rem;margin-top:4px;display:none;">Please
                    select a width.</div>
            </div>

            <!-- Length input -->
            <div id="lengthSection" style="margin-bottom:1.2rem;">
                <div style="font-size:.82rem;font-weight:800;color:#475569;margin-bottom:8px;">ENTER LENGTH (meters)
                </div>
                <div class="length-input-row">
                    <input type="number" id="configLength" min="0.1" step="0.1" placeholder="e.g. 2.5">
                    <span>meters</span>
                </div>
                <div id="lengthHint" style="font-size:.75rem;color:var(--muted);"></div>
                <div id="lengthError" style="color:var(--danger);font-size:.78rem;display:none;">Please enter a valid
                    length.</div>
            </div>

            <!-- Quantity input -->
            <div id="qtySection" style="margin-bottom:1.2rem;">
                <div style="font-size:.82rem;font-weight:800;color:#475569;margin-bottom:8px;">NUMBER OF PIECES</div>
                <div class="length-input-row" style="max-width: 150px;">
                    <input type="number" id="configQty" min="1" step="1" value="1">
                </div>
                <div id="qtyError" style="color:var(--danger);font-size:.78rem;display:none;">Please enter a valid
                    quantity.</div>
            </div>

            <!-- Price preview -->
            <div id="priceSummary"
                style="background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:1.2rem;display:none;">
                <div style="font-size:.8rem;color:var(--muted);margin-bottom:4px;">Price Preview</div>
                <div style="font-size:1.3rem;font-weight:900;color:var(--primary);" id="pricePreviewVal">₹0</div>
                <div style="font-size:.75rem;color:var(--muted);" id="pricePreviewSub"></div>
            </div>

            <button class="btn btn-primary" style="width:100%;font-size:1rem;" onclick="confirmAddToCart()">
                <i class="material-icons-outlined">add_shopping_cart</i> Add to Cart
            </button>
        </div>
    </div>

    <!-- Staff Representative Modal -->
    <div class="modal-overlay" id="staffModal">
        <div class="modal-box">
            <button class="modal-close"
                onclick="document.getElementById('staffModal').classList.remove('open')">✕</button>
            <div style="text-align:center;margin-bottom:1.2rem;">
                <i class="material-icons-outlined"
                    style="font-size:3rem;color:#6366f1;display:block;margin-bottom:.5rem;">support_agent</i>
                <div class="modal-title" style="margin-bottom:.3rem;">Ask a Staff Representative</div>
                <div style="font-size:.85rem;color:var(--muted);">Our team will review and respond to your request
                    quickly.</div>
            </div>

            <div style="margin-bottom:1rem;">
                <label style="font-size:.8rem;font-weight:800;color:#475569;display:block;margin-bottom:6px;">YOUR
                    MESSAGE *</label>
                <textarea id="staffMessage" rows="4"
                    style="width:100%;padding:.7rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-size:.9rem;resize:vertical;outline:none;"
                    placeholder="Describe what you need help with (e.g. 'I need help choosing the right bedsheet width for my shop entrance...')"></textarea>
            </div>

            <button class="btn btn-primary" style="width:100%;justify-content:center;font-size:1rem;"
                id="staffSubmitBtn" onclick="submitStaffRequest()">
                <i class="material-icons-outlined">send</i> Submit Request
            </button>

            <div style="margin-top:1.5rem;" id="myRequestsWrap">
                <div
                    style="font-size:.82rem;font-weight:800;color:#475569;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
                    Previous Requests
                    <button onclick="loadMyRequests()"
                        style="background:none;border:none;color:var(--primary);font-size:.75rem;font-weight:700;cursor:pointer;">Refresh</button>
                </div>
                <div id="myRequestsList" style="font-size:.82rem;color:var(--muted);text-align:center;padding:.5rem 0;">
                    Loading...</div>
            </div>
        </div>
    </div>

    <!-- Custom Confirmation Modal -->
    <div class="modal-overlay" id="confirmMktModal" style="z-index: 10000;">
        <div class="modal-box" style="text-align:center;">
            <i class="material-icons-outlined" style="font-size:3.5rem; color:#ef4444; margin-bottom:1rem;"
                id="confirmMktIcon">warning</i>
            <div class="modal-title" id="confirmMktTitle" style="font-size:1.4rem;">Confirm Action</div>
            <div class="modal-sub" id="confirmMktSub" style="font-size:1rem;color:#64748b;">Are you sure?</div>
            <div class="modal-actions" style="margin-top:1.5rem; display:flex; justify-content:center; gap:10px;">
                <button class="btn btn-primary"
                    style="flex:1;justify-content:center;font-size:1rem;padding:0.7rem;background:#ef4444;"
                    id="btnMktConfirmYes">Yes, Proceed</button>
                <button class="btn btn-outline" style="flex:1;justify-content:center;font-size:1rem;"
                    onclick="closeModal('confirmMktModal')">Go Back</button>
            </div>
        </div>
    </div>

    <script>
        let allProducts = [];
        let cart = JSON.parse(localStorage.getItem('mkt_cart')) || {};
        const csrfToken = "<?= $csrfToken ?>";

        // Current config modal state
        let configProduct = null;
        let selectedWidthIdx = null;

        async function apiCall(endpoint, action, payload = {}) {
            payload.action = action;
            payload.csrf_token = csrfToken;
            try {
                const res = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) });
                return await res.json();
            } catch (e) { return { success: false, message: 'Server connection failed.' }; }
        }

        function showToast(msg, type = 'info') {
            const wrap = document.getElementById('toast-wrap');
            const t = document.createElement('div');
            t.className = 'toast';
            t.innerHTML = `<span style="font-weight:700;">${msg}</span>`;
            wrap.appendChild(t);
            setTimeout(() => t.remove(), 4000);
        }

        function customConfirmMkt(title, msg, onYes) {
            document.getElementById('confirmMktTitle').textContent = title;
            document.getElementById('confirmMktSub').textContent = msg;
            const btnTrue = document.getElementById('btnMktConfirmYes');
            btnTrue.onclick = () => { document.getElementById('confirmMktModal').classList.remove('open'); onYes(); };
            document.getElementById('confirmMktModal').classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function switchView(id, el) {
            document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
            document.getElementById(id).classList.add('active');
            document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
            if (el) el.classList.add('active');
            if (id === 'cart') renderCart();
            if (id === 'orders') fetchOrders();
        }

        // ─── PRODUCTS ────────────────────────────────────────────────────────────────
        async function fetchProducts() {
            const res = await apiCall('../api/marketplace_products.php', 'get_products');
            document.getElementById('loading').style.display = 'none';
            if (res.success) {
                allProducts = res.products;
                buildCatFilter();
                renderProducts();
                updateCartBadge();
            }
        }

        function buildCatFilter() {
            const cats = [...new Set(allProducts.map(p => p.category))];
            const sel = document.getElementById('catFilter');
            sel.innerHTML = '<option value="all">All Categories</option>' +
                cats.map(c => `<option value="${c}">${c}</option>`).join('');
        }

        function renderProducts() {
            const grid = document.getElementById('productsObj');
            const cat = document.getElementById('catFilter').value;
            let filtered = allProducts.filter(p => cat === 'all' || p.category === cat);

            if (filtered.length === 0) {
                grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:3rem;background:white;border-radius:14px;border:1px dashed var(--border);">No products found.</div>';
                return;
            }

            grid.innerHTML = filtered.map(p => {
                const img = p.image ? `<img src="../${p.image}" alt="">` : `<i class="material-icons-outlined">image</i>`;
                const hasWidths = p.widths && p.widths.length > 0;

                let priceHtml = '';
                if (hasWidths) {
                    priceHtml = `<div class="prod-price" style="font-size:.92rem;">From ₹${Math.min(...p.widths.map(w => w.price_per_meter))}<span style="font-size:.7rem;font-weight:600;color:var(--muted);">/meter</span></div>
            <div class="width-chips">
                ${p.widths.map(w => `<span class="width-chip">${w.label}</span>`).join('')}
            </div>`;
                } else {
                    priceHtml = `<div class="prod-price">₹${p.price}</div>`;
                }

                const actionBtn = p.stock > 0
                    ? `<button class="btn btn-outline" style="width:100%;margin-top:auto;" onclick="openConfigModal(${p.id})">
                <i class="material-icons-outlined">add_shopping_cart</i> ${hasWidths ? 'Select & Add' : 'Add to Cart'}
               </button>`
                    : `<button class="btn btn-outline" style="width:100%;margin-top:auto;" disabled>Out of Stock</button>`;

                return `
            <div class="product-card">
                <div class="prod-img">${img}</div>
                <div class="prod-body">
                    <div class="prod-cat">${p.category}</div>
                    <div class="prod-name">${p.name}</div>
                    <div class="prod-size">${p.size}</div>
                    ${priceHtml}
                    ${actionBtn}
                </div>
            </div>`;
            }).join('');
        }

        // ─── CONFIG MODAL (width + length selection) ─────────────────────────────────
        function openConfigModal(id) {
            const p = allProducts.find(x => x.id === id);
            if (!p) return;
            configProduct = p;
            selectedWidthIdx = null;

            document.getElementById('configProdId').value = p.id;
            document.getElementById('configProdName').textContent = p.name;
            document.getElementById('configLength').value = '';
            document.getElementById('configQty').value = '1';
            document.getElementById('priceSummary').style.display = 'none';
            document.getElementById('widthError').style.display = 'none';
            document.getElementById('lengthError').style.display = 'none';
            document.getElementById('qtyError').style.display = 'none';

            const hasWidths = p.widths && p.widths.length > 0;

            // Show/hide sections
            document.getElementById('widthSection').style.display = hasWidths ? 'block' : 'none';
            document.getElementById('lengthSection').style.display = hasWidths ? 'block' : 'none';

            if (hasWidths) {
                document.getElementById('widthChipsModal').innerHTML = p.widths.map((w, idx) =>
                    `<span class="width-chip" onclick="selectWidth(${idx})" id="wchip-${idx}">${w.label} — ₹${w.price_per_meter}/m</span>`
                ).join('');
                document.getElementById('configLength').addEventListener('input', updatePricePreview);
            }

            // Listen to quantity changes for both types
            document.getElementById('configQty').addEventListener('input', updatePricePreview);

            document.getElementById('configModal').classList.add('open');

            // Initial preview for simple products
            updatePricePreview();
        }

        function selectWidth(idx) {
            selectedWidthIdx = idx;
            document.querySelectorAll('#widthChipsModal .width-chip').forEach((c, i) => {
                c.classList.toggle('selected', i === idx);
            });
            document.getElementById('widthError').style.display = 'none';
            updatePricePreview();
        }

        function updatePricePreview() {
            if (!configProduct) return;
            const qty = parseInt(document.getElementById('configQty').value) || 1;
            const hasWidths = configProduct.widths && configProduct.widths.length > 0;

            if (hasWidths) {
                if (selectedWidthIdx === null) return;
                const w = configProduct.widths[selectedWidthIdx];
                const len = parseFloat(document.getElementById('configLength').value);
                if (!w || isNaN(len) || len <= 0) {
                    document.getElementById('priceSummary').style.display = 'none';
                    return;
                }
                const total = (w.price_per_meter * len * qty).toFixed(2);
                document.getElementById('pricePreviewVal').textContent = `₹${total}`;
                document.getElementById('pricePreviewSub').textContent = `${w.label} × ${len}m × ${qty} pcs @ ₹${w.price_per_meter}/m`;
                document.getElementById('priceSummary').style.display = 'block';
            } else {
                const total = (configProduct.price * qty).toFixed(2);
                document.getElementById('pricePreviewVal').textContent = `₹${total}`;
                document.getElementById('pricePreviewSub').textContent = `${qty} piece(s) @ ₹${configProduct.price} each`;
                document.getElementById('priceSummary').style.display = 'block';
            }
        }

        function confirmAddToCart() {
            if (!configProduct) return;
            const hasWidths = configProduct.widths && configProduct.widths.length > 0;
            const qty = parseInt(document.getElementById('configQty').value);

            if (isNaN(qty) || qty < 1) {
                document.getElementById('qtyError').style.display = 'block';
                return;
            }
            document.getElementById('qtyError').style.display = 'none';

            if (qty > configProduct.stock) {
                showToast('Quantity exceeds available stock!');
                return;
            }

            if (hasWidths) {
                if (selectedWidthIdx === null) {
                    document.getElementById('widthError').style.display = 'block';
                    return;
                }
                const len = parseFloat(document.getElementById('configLength').value);
                if (isNaN(len) || len < 0.1) {
                    document.getElementById('lengthError').style.display = 'block';
                    return;
                }

                const w = configProduct.widths[selectedWidthIdx];
                const total = parseFloat((w.price_per_meter * len).toFixed(2));
                const key = `${configProduct.id}_${w.label}_${len}`;

                if (cart[key]) {
                    if (cart[key].qty + qty <= configProduct.stock) cart[key].qty += qty;
                    else { showToast('Max stock reached!'); return; }
                } else {
                    cart[key] = {
                        key,
                        id: configProduct.id,
                        name: configProduct.name,
                        price: total,
                        pricePerM: w.price_per_meter,
                        widthLabel: w.label,
                        lengthMeters: len,
                        image: configProduct.image,
                        stock: configProduct.stock,
                        qty: qty,
                        isPerMeter: true
                    };
                }
                saveCart();
                showToast(`${configProduct.name} (${w.label}, ${len}m) × ${qty} added to cart`);
                document.getElementById('configModal').classList.remove('open');
            } else {
                const key = String(configProduct.id);
                if (cart[key]) {
                    if (cart[key].qty + qty <= configProduct.stock) cart[key].qty += qty;
                    else { showToast('Max stock reached!'); return; }
                } else {
                    cart[key] = { key, id: configProduct.id, name: configProduct.name, price: configProduct.price, image: configProduct.image, size: configProduct.size, stock: configProduct.stock, qty: qty, isPerMeter: false };
                }
                saveCart();
                showToast(`${configProduct.name} × ${qty} added to cart`);
                document.getElementById('configModal').classList.remove('open');
            }
        }

        function addToCartSimple(p) {
            const key = String(p.id);
            if (cart[key]) {
                if (cart[key].qty < p.stock) cart[key].qty++;
                else { showToast('Max stock reached!'); return; }
            } else {
                cart[key] = { key, id: p.id, name: p.name, price: p.price, image: p.image, size: p.size, stock: p.stock, qty: 1, isPerMeter: false };
            }
            saveCart();
            showToast(`${p.name} added to cart`);
        }

        // ─── CART ─────────────────────────────────────────────────────────────────────
        function updateCartBadge() {
            const b = document.getElementById('cartBadge');
            const total = Object.values(cart).reduce((sum, i) => sum + i.qty, 0);
            if (total > 0) { b.textContent = total; b.style.display = 'inline-block'; }
            else b.style.display = 'none';
        }

        function saveCart() {
            localStorage.setItem('mkt_cart', JSON.stringify(cart));
            updateCartBadge();
            if (document.getElementById('cart').classList.contains('active')) renderCart();
        }

        function renderCart() {
            const list = document.getElementById('cartItemsList');
            const items = Object.values(cart);

            if (items.length === 0) {
                document.getElementById('cartLayout').style.display = 'block';
                document.getElementById('cartLayout').innerHTML = `
            <div style="text-align:center;padding:4rem;background:white;border-radius:14px;border:1px dashed var(--border);">
                <i class="material-icons-outlined" style="font-size:4rem;color:var(--border);margin-bottom:1rem;">shopping_cart</i>
                <h3 style="margin-bottom:.5rem;">Your cart is empty</h3>
                <p style="color:var(--muted);margin-bottom:1.5rem;">Browse the store and add some items!</p>
                <button class="btn btn-primary" onclick="switchView('shop', document.getElementById('nav-shop'))">Start Shopping</button>
            </div>`;
                return;
            }

            let subtotal = 0;
            list.innerHTML = items.map(item => {
                const lineTotal = item.price * item.qty;
                subtotal += lineTotal;
                const img = item.image
                    ? `<img src="../${item.image}" style="width:60px;height:60px;object-fit:cover;border-radius:10px;">`
                    : `<div style="width:60px;height:60px;background:#f1f5f9;border-radius:10px;display:flex;align-items:center;justify-content:center;"><i class="material-icons-outlined">image</i></div>`;
                const metaStr = item.isPerMeter
                    ? `${item.widthLabel}, ${item.lengthMeters}m — ₹${item.pricePerM}/m`
                    : `Size: ${item.size || ''}`;
                return `
            <div class="cart-item">
                <div style="display:flex;gap:12px;align-items:center;">
                    ${img}
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta">${metaStr}</div>
                        <div class="cart-item-price">₹${lineTotal.toFixed(2)}</div>
                    </div>
                </div>
                <div class="qty-ctrl">
                    ${!item.isPerMeter ? `<button class="qty-btn" onclick="updateQty('${item.key}',-1)">-</button>
                    <span class="qty-val">${item.qty}</span>
                    <button class="qty-btn" onclick="updateQty('${item.key}',1)">+</button>` : `<span style="font-size:.8rem;color:var(--muted);">qty 1</span>`}
                    <button class="qty-btn" style="background:#fee2e2;color:#dc2626;margin-left:5px;" onclick="updateQty('${item.key}','remove')">
                        <i class="material-icons-outlined" style="font-size:.9rem;">delete</i>
                    </button>
                </div>
            </div>`;
            }).join('');

            document.getElementById('cartSubtotal').textContent = `₹${subtotal.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `₹${subtotal.toFixed(2)}`;
            if (typeof togglePayLaterInfo === 'function') togglePayLaterInfo();
        }

        function togglePayLaterInfo() {
            const pt = document.getElementById('paymentType');
            const info = document.getElementById('payLaterInfo');
            const btn = document.getElementById('checkoutBtn');

            if (info && pt) {
                info.style.display = (pt.value === 'credit') ? 'block' : 'none';

                const availableOrders = <?= $availableOrders ?? 0 ?>;
                const isProfileSetup = <?= $needsProfileSetup ? 'true' : 'false' ?>;

                if (pt.value === 'credit' && availableOrders <= 0) {
                    btn.disabled = true;
                    btn.textContent = 'Limit Exceeded';
                } else {
                    btn.disabled = isProfileSetup;
                    btn.textContent = 'Place Order';
                }
            }
        }

        function updateQty(key, delta) {
            if (!cart[key]) return;
            if (delta === 'remove') { delete cart[key]; }
            else {
                const newQty = cart[key].qty + delta;
                if (newQty <= 0) delete cart[key];
                else if (newQty <= cart[key].stock) cart[key].qty = newQty;
                else showToast('Max stock reached');
            }
            saveCart();
        }

        // ─── CHECKOUT ─────────────────────────────────────────────────────────────────
        async function checkout() {
            const items = Object.values(cart);
            if (!items.length) return;
            const paymentType = document.getElementById('paymentType').value;
            const btn = document.getElementById('checkoutBtn');

            const cartPayload = {
                items: items.map(i => ({
                    product_id: i.id,
                    quantity: i.qty,
                    price: i.price,
                    width_label: i.widthLabel || null,
                    length_meters: i.lengthMeters || null
                })),
                payment_type: paymentType
            };

            if (paymentType === 'credit') {
                // Pay Later — create order directly
                btn.disabled = true; btn.textContent = 'Processing...';
                const res = await apiCall('../api/create_marketplace_order.php', 'create_order', cartPayload);
                if (res.success) {
                    cart = {}; saveCart();
                    showToast('✅ Order placed on Pay Later credit!');
                    setTimeout(() => switchView('orders', document.getElementById('nav-orders')), 800);
                } else {
                    showToast('❌ ' + res.message);
                }
                btn.disabled = false; btn.textContent = 'Place Order';
                return;
            }

            // Online — real Razorpay flow
            const totalAmount = items.reduce((s, i) => s + i.price * i.qty, 0);
            btn.disabled = true; btn.textContent = 'Initiating Payment...';

            try {
                const initRes = await apiCall('../api/create_marketplace_order.php', 'create_razorpay_order', { amount: totalAmount });
                if (!initRes.success) { showToast('❌ ' + initRes.message); btn.disabled = false; btn.textContent = 'Place Order'; return; }

                const options = {
                    key: initRes.key,
                    amount: initRes.amount,
                    currency: 'INR',
                    name: 'DigiMarket',
                    description: `${items.length} item(s) — ₹${totalAmount.toFixed(2)}`,
                    order_id: initRes.rzp_order_id,
                    image: '/assets/img/logo.png',
                    handler: async (rzpRes) => {
                        btn.textContent = 'Verifying...';
                        const finalRes = await apiCall('../api/create_marketplace_order.php', 'create_order', {
                            ...cartPayload,
                            razorpay_payment_id: rzpRes.razorpay_payment_id,
                            razorpay_order_id: rzpRes.razorpay_order_id,
                            razorpay_signature: rzpRes.razorpay_signature
                        });
                        if (finalRes.success) {
                            cart = {}; saveCart();
                            showToast('✅ Payment successful! Order #' + finalRes.order_id + ' placed.');
                            setTimeout(() => switchView('orders', document.getElementById('nav-orders')), 800);
                        } else {
                            showToast('❌ ' + finalRes.message);
                        }
                        btn.disabled = false; btn.textContent = 'Place Order';
                    },
                    prefill: { name: '<?= $userName ?>', contact: '<?= $userPhone ?>' },
                    theme: { color: '#ec4899' },
                    modal: {
                        ondismiss: () => { btn.disabled = false; btn.textContent = 'Place Order'; }
                    }
                };
                new Razorpay(options).open();
            } catch (e) {
                showToast('❌ Could not initiate payment.');
                btn.disabled = false; btn.textContent = 'Place Order';
            }
        }

        // ─── ORDERS ───────────────────────────────────────────────────────────────────
        async function fetchOrders() {
            const wrap = document.getElementById('myOrdersWrap');
            const loading = document.getElementById('ordersListLoading');
            loading.style.display = 'block'; wrap.innerHTML = '';
            const res = await apiCall('../api/marketplace_orders.php', 'get_orders');
            loading.style.display = 'none';
            if (!res.success || !res.orders.length) {
                wrap.innerHTML = `<div style="text-align:center;padding:3rem;color:var(--muted);background:white;border-radius:14px;border:1px dashed var(--border);">
            <i class="material-icons-outlined" style="font-size:3rem;color:var(--border);display:block;margin-bottom:.5rem;">inventory_2</i>
            No marketplace orders yet.
        </div>`;
                return;
            }

            const statusLabels = { placed: 'Placed', assigned: 'Assigned', picked_up: 'Picked Up', out_for_delivery: 'Out for Delivery', delivered: 'Delivered', cancelled: 'Cancelled' };
            const steps = ['placed', 'assigned', 'picked_up', 'out_for_delivery', 'delivered'];

            wrap.innerHTML = res.orders.map(o => {
                let sc = 'st-placed';
                if (['assigned', 'picked_up', 'out_for_delivery'].includes(o.status)) sc = 'st-assigned';
                if (o.status === 'delivered') sc = 'st-delivered';
                if (o.status === 'cancelled') sc = '';

                const stepIdx = steps.indexOf(o.status);
                const timelineHtml = o.status !== 'cancelled' ? `
            <div class="timeline">${steps.map((s, i) => `
                <div class="tl-step ${i < stepIdx ? 'done' : i === stepIdx ? 'current' : ''}">
                    <div class="tl-dot">${i < stepIdx ? '✓' : i + 1}</div>
                    <div class="tl-lbl">${statusLabels[s] || s}</div>
                </div>`).join('')}
            </div>` : '';

                const itemsHtml = o.items.map(i => {
                    const meta = (i.width_label || i.length_meters)
                        ? `<b>${i.width_label}</b> × ${i.length_meters}m &nbsp;·&nbsp; ${i.name}`
                        : `<b>${i.quantity}×</b> ${i.name} <span style="color:var(--muted);">(${i.size || 'Std'})</span>`;
                    const lineTotal = parseFloat(i.price);
                    return `<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px dashed #f1f5f9;font-size:.85rem;">
                <div>${meta}</div>
                <div style="font-weight:800;color:var(--text);">₹${lineTotal.toFixed(2)}</div>
            </div>`;
                }).join('');

                const payBadge = o.payment_status === 'paid'
                    ? `<span style="background:#dcfce7;color:#16a34a;padding:2px 8px;border-radius:8px;font-size:.72rem;font-weight:800;">PAID</span>`
                    : `<span style="background:#fef3c7;color:#b45309;padding:2px 8px;border-radius:8px;font-size:.72rem;font-weight:800;">PAY LATER — DUE</span>`;

                const deliveryTimes = [];
                if (o.picked_up_at) deliveryTimes.push(`<tr><td style="color:#64748b;padding:2px 6px 2px 0;">Picked Up:</td><td style="font-weight:600;font-size:.75rem;">${new Date(o.picked_up_at).toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', day: 'numeric', month: 'short' })}</td></tr>`);
                if (o.delivered_at) deliveryTimes.push(`<tr><td style="color:#64748b;padding:2px 6px 2px 0;">Delivered:</td><td style="font-weight:600;font-size:.75rem;">${new Date(o.delivered_at).toLocaleString('en-US', { hour: 'numeric', minute: 'numeric', day: 'numeric', month: 'short' })}</td></tr>`);
                const timesTable = deliveryTimes.length ? `<table style="margin-top:5px;">${deliveryTimes.join('')}</table>` : '';

                const deliveryHtml = o.delivery_name
                    ? `<div style="margin-top:10px;padding:8px 12px;background:#f0f9ff;border-radius:10px;font-size:.8rem;display:flex;align-items:flex-start;gap:8px;">
                <i class="material-icons-outlined" style="color:var(--primary);font-size:1.1rem;margin-top:2px;">delivery_dining</i>
                <div>
                    <div><b>${o.delivery_name}</b>${o.delivery_phone ? ' · <a href="tel:' + o.delivery_phone + '" style="color:var(--primary);">' + o.delivery_phone + '</a>' : ''}</div>
                    ${timesTable}
                </div>
               </div>` : '';

                const invoiceBtn = o.invoice_no
                    ? `<a href="../api/marketplace_invoice.php?order_id=${o.id}" target="_blank" class="btn btn-outline" style="padding:4px 10px;font-size:.75rem;margin-top:8px;display:inline-flex;align-items:center;gap:4px;"><i class="material-icons-outlined" style="font-size:.9rem;">picture_as_pdf</i> Invoice</a>`
                    : '';

                const cancelBtn = (o.status === 'placed' || o.status === 'assigned')
                    ? `<button onclick="cancelMarketOrder(${o.id})" class="btn btn-outline" style="padding:4px 10px;font-size:.75rem;margin-top:8px;display:inline-flex;align-items:center;gap:4px;color:#ef4444;border-color:#ef4444;"><i class="material-icons-outlined" style="font-size:.9rem;">cancel</i> Cancel</button>`
                    : '';

                return `
            <div class="order-card">
                <div class="order-header">
                    <div class="order-meta">
                        <div style="font-weight:900;font-size:1rem;color:var(--text);">Order #${o.id} &nbsp; ${payBadge}</div>
                        <div style="margin-top:2px;">${new Date(o.created_at).toLocaleString()}</div>
                    </div>
                </div>
                ${timelineHtml}
                <div style="margin:4px 0;">${itemsHtml}</div>
                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:12px;padding-top:10px;border-top:1px solid var(--border);">
                    <div>
                        <div style="font-size:.78rem;color:var(--muted);font-weight:600;">${o.payment_type === 'credit' ? '🏦 Pay Later (Credit)' : '💳 Online Payment'}</div>
                        <div style="display:flex;gap:8px;">
                            ${invoiceBtn}
                            ${cancelBtn}
                        </div>
                    </div>
                    <div style="font-size:1.3rem;font-weight:900;color:var(--primary);">₹${parseFloat(o.total_amount).toFixed(2)}</div>
                </div>
                ${deliveryHtml}
            </div>`;
            }).join('');
        }

        window.cancelMarketOrder = function (orderId) {
            customConfirmMkt('Cancel Order', 'Are you sure you want to cancel this marketplace order? All pending items will be reverted.', async () => {
                const res = await apiCall('../api/update_marketplace_status.php', 'user_cancel_order', { order_id: orderId });
                if (res.success) {
                    showToast('✅ ' + res.message, 'success');
                    fetchOrders();
                    if (typeof checkEligibility === 'function') checkEligibility(); // Refresh available orders if on Pay Later tab limit
                } else {
                    showToast('❌ ' + res.message, 'error');
                }
            });
        }

        // ─── STAFF REQUESTS ───────────────────────────────────────────────────────────
        function openStaffModal() {
            document.getElementById('staffMessage').value = '';
            document.getElementById('staffModal').classList.add('open');
            loadMyRequests();
        }

        async function submitStaffRequest() {
            const msg = document.getElementById('staffMessage').value.trim();
            if (!msg) { showToast('Please enter a message.'); return; }
            const btn = document.getElementById('staffSubmitBtn');
            btn.disabled = true; btn.textContent = 'Sending...';
            const res = await apiCall('../api/staff_requests.php', 'submit_request', { message: msg });
            if (res.success) {
                showToast('✅ ' + res.message);
                document.getElementById('staffMessage').value = '';
                loadMyRequests();
            } else {
                showToast('❌ ' + res.message);
            }
            btn.disabled = false; btn.innerHTML = '<i class="material-icons-outlined">send</i> Submit Request';
        }

        async function loadMyRequests() {
            const list = document.getElementById('myRequestsList');
            list.innerHTML = '<div style="text-align:center;padding:.5rem;">Loading...</div>';
            const res = await apiCall('../api/staff_requests.php', 'get_my_requests');
            if (!res.success || !res.requests.length) {
                list.innerHTML = '<div style="text-align:center;color:var(--muted);">No requests yet.</div>';
                return;
            }
            const statusColors = { pending: 'background:#fef3c7;color:#92400e', seen: 'background:#dbeafe;color:#1e40af', resolved: 'background:#dcfce7;color:#166534' };
            list.innerHTML = res.requests.map(r => `
        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:.75rem;margin-bottom:8px;">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                <div style="font-size:.82rem;font-weight:600;color:var(--text);flex:1;">${r.message}</div>
                <span style="${statusColors[r.status]};padding:2px 8px;border-radius:20px;font-size:.7rem;font-weight:800;flex-shrink:0;">${r.status.toUpperCase()}</span>
            </div>
            ${r.admin_note ? `<div style="margin-top:6px;background:#f0fdf4;border-left:2px solid #10b981;padding:5px 8px;border-radius:5px;font-size:.78rem;color:#166534;"><b>Staff reply:</b> ${r.admin_note}</div>` : ''}
            ${r.delivery_name ? `<div style="margin-top:6px;background:#eff6ff;border-left:2px solid #3b82f6;padding:5px 8px;border-radius:5px;font-size:.78rem;color:#1e40af;"><i class="material-icons-outlined" style="font-size:.9rem;vertical-align:middle;">delivery_dining</i> Assigned: <b>${r.delivery_name}</b>${r.delivery_phone ? ' · ' + r.delivery_phone : ''}</div>` : ''}
            <div style="font-size:.72rem;color:var(--muted);margin-top:4px;">${new Date(r.created_at).toLocaleString()}</div>
        </div>`).join('');
        }

        window.onload = () => { fetchProducts(); };
    </script>
</body>

</html>