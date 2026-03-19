<?php
require_once '../config.php';

// Check if logged in & is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../index.php');
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$needsProfileSetup = empty($user['name']) || empty($user['shop_address']);
$qrCodeHash = $user['qr_code_hash'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash - User Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .menu-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 12px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .menu-item i {
            margin-right: 15px;
        }
        .menu-item:hover, .menu-item.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            padding: 1.5rem;
            text-align: center;
        }
        .stat-card h3 { margin-bottom: 0.5rem; font-size: 1.1rem; color: #475569; }
        .stat-card .value { font-size: 2.5rem; font-weight: 700; color: var(--primary); }
        
        /* Sections */
        .section-content { display: none; animation: slideUp 0.4s ease forwards; }
        .section-content.active { display: block; }

        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .sidebar { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
            .menu-item { flex-shrink: 0; padding: 0.8rem 1rem; }
            .menu-item span { display: none; } /* Hide text on mobile nav, icons only */
            .menu-item i { margin-right: 0; }
        }
        /* Stepper Styles for Order Timeline */
        .stepper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 1.5rem 0;
            position: relative;
            padding: 0 10px;
        }
        .stepper::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 20px;
            right: 20px;
            height: 2px;
            background: #e2e8f0;
            z-index: 1;
        }
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            background: white;
            padding: 0 5px;
        }
        .step-circle {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #64748b;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 5px;
            font-size: 14px;
            transition: all 0.3s ease;
            border: 2px solid white;
        }
        .step.active .step-circle {
            background: var(--primary);
            color: white;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.2);
        }
        .step.completed .step-circle {
            background: #10b981;
            color: white;
        }
        .step-label {
            font-size: 10px;
            color: #64748b;
            font-weight: 500;
            white-space: nowrap;
        }
        .step.active .step-label {
            color: var(--primary);
            font-weight: 700;
        }
        /* ── Toast Notification ── */
        #toast-container { position:fixed; top:1.5rem; right:1.5rem; z-index:99999; display:flex; flex-direction:column; gap:10px; pointer-events:none; }
        .toast { display:flex; align-items:flex-start; gap:12px; background:white; border-radius:14px; padding:1rem 1.2rem; box-shadow:0 8px 30px rgba(0,0,0,0.15); min-width:280px; max-width:380px; pointer-events:all; animation:toastIn 0.3s ease; border-left:4px solid #6366f1; }
        .toast.success { border-left-color:#10b981; }
        .toast.error   { border-left-color:#ef4444; }
        .toast.info    { border-left-color:#3b82f6; }
        .toast-icon { font-size:1.4rem; flex-shrink:0; margin-top:1px; }
        .toast-body { flex:1; }
        .toast-title { font-weight:800; font-size:0.9rem; color:#0f172a; }
        .toast-msg   { font-size:0.82rem; color:#64748b; margin-top:2px; }
        .toast-close { background:none; border:none; cursor:pointer; color:#94a3b8; font-size:1rem; padding:0; flex-shrink:0; }
        .toast-progress { height:3px; border-radius:2px; margin-top:8px; }
        .toast.success .toast-progress { background:#10b981; }
        .toast.error   .toast-progress { background:#ef4444; }
        .toast.info    .toast-progress { background:#3b82f6; }
        @keyframes toastIn { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
        @keyframes toastOut { to{opacity:0;transform:translateX(40px)} }

        /* ── Product Catalog ── */
        .product-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:1rem; margin-bottom:1.5rem; }
        .product-card { border:2px solid #e2e8f0; border-radius:14px; overflow:hidden; cursor:pointer; transition:all 0.2s; background:white; }
        .product-card:hover { border-color:#6366f1; box-shadow:0 4px 16px rgba(99,102,241,0.15); }
        .product-card.has-items { border-color:#10b981; background:#f0fdf4; }
        .product-img { width:100%; height:130px; object-fit:cover; background:#f1f5f9; display:flex; align-items:center; justify-content:center; }
        .product-img img { width:100%; height:100%; object-fit:cover; }
        .product-img i { font-size:3rem; color:#cbd5e1; }
        .product-info { padding:0.8rem; }
        .product-name { font-weight:700; font-size:0.9rem; color:#0f172a; margin-bottom:4px; }
        .product-prices { display:flex; flex-wrap:wrap; gap:5px; margin-top:6px; }
        .price-chip { background:#f1f5f9; border:1.5px solid #e2e8f0; border-radius:8px; padding:3px 8px; font-size:0.75rem; font-weight:600; color:#475569; cursor:pointer; transition:all 0.15s; display:flex; align-items:center; gap:4px; }
        .price-chip:hover { border-color:#6366f1; color:#6366f1; }
        .price-chip.selected { background:#6366f1; color:white; border-color:#6366f1; }
        .qty-ctrl { display:flex; align-items:center; gap:8px; margin-top:6px; }
        .qty-btn { width:26px; height:26px; border-radius:50%; border:1.5px solid #e2e8f0; background:white; cursor:pointer; font-size:1rem; font-weight:700; display:flex; align-items:center; justify-content:center; transition:all 0.15s; }
        .qty-btn:hover { border-color:#6366f1; color:#6366f1; }
        .qty-val { font-weight:700; min-width:20px; text-align:center; }

        /* ── Cart Summary ── */
        .cart-summary { background:#f8fafc; border:1.5px solid #e2e8f0; border-radius:12px; padding:1rem; margin-bottom:1rem; }
        .cart-item-row { display:flex; justify-content:space-between; align-items:center; padding:5px 0; font-size:0.875rem; border-bottom:1px solid #e2e8f0; }
        .cart-item-row:last-child { border:none; }
        .cart-total { display:flex; justify-content:space-between; font-size:1rem; font-weight:800; color:#0f172a; margin-top:8px; padding-top:8px; border-top:2px solid #e2e8f0; }
    </style>
</head>
<body>
<div id="toast-container"></div>

    <nav class="navbar">
        <div class="logo">
            <span class="material-icons-outlined" style="font-size: 2rem;">local_laundry_service</span>
            <span>DigiWash</span>
        </div>
        <div class="nav-links">
            <a href="#" id="logoutBtn" style="color: var(--danger); display:flex; align-items:center;">
                <span class="material-icons-outlined" style="margin-right: 5px;">logout</span> Logout
            </a>
        </div>
    </nav>

    <div class="container dashboard-grid">
        <!-- Sidebar Navigation -->
        <aside class="sidebar glass-panel" style="padding: 1.5rem; height: max-content;">
            <div class="menu-item active" onclick="switchTab('dashboard')">
                <i class="material-icons-outlined">dashboard</i>
                <span>Dashboard</span>
            </div>
            <div class="menu-item" onclick="switchTab('orders')">
                <i class="material-icons-outlined">add_circle</i>
                <span>New Order</span>
            </div>
            <div class="menu-item" onclick="switchTab('history')">
                <i class="material-icons-outlined">receipt_long</i>
                <span>History</span>
            </div>
            <div class="menu-item" onclick="switchTab('payments')">
                <i class="material-icons-outlined">account_balance_wallet</i>
                <span>Payments</span>
            </div>
            <div class="menu-item" onclick="switchTab('profile')">
                <i class="material-icons-outlined">person</i>
                <span>Profile</span>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="content-area">

            <?php if ($needsProfileSetup): ?>
            <!-- Profile Setup Alert -->
            <div class="glass-panel" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); margin-bottom: 2rem;">
                <h3 style="color: var(--danger); display: flex; align-items: center;">
                    <span class="material-icons-outlined" style="margin-right:10px;">warning</span> Action Required
                </h3>
                <p>Please complete your profile details (Name, Shop Address, Email) to start creating orders.</p>
                <button class="btn btn-primary" style="width: auto;" onclick="switchTab('profile')">Update Profile Now</button>
            </div>
            <?php endif; ?>

            <!-- Dashboard Section -->
            <section id="dashboard" class="section-content active">
                <h2 style="margin-bottom: 2rem;">Ongoing Status</h2>
                
                <div class="stats-grid">
                    <div class="glass-panel stat-card">
                        <h3>Active Orders</h3>
                        <div class="value" id="val_active">0</div>
                    </div>
                    <div class="glass-panel stat-card">
                        <h3>Total Completed</h3>
                        <div class="value" id="val_completed">0</div>
                    </div>
                    <div class="glass-panel stat-card">
                        <h3>Pending Payment</h3>
                        <div class="value" id="val_pending">₹0</div>
                    </div>
                </div>

                <div class="glass-panel">
                    <h3>Recent Activity</h3>
                    <p id="recentActivityText" style="text-align: center; color: #94a3b8; padding: 2rem 0;">No recent orders found. You're all caught up!</p>
                </div>
            </section>

            <!-- Create Order Section -->
            <section id="orders" class="section-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2>New Order</h2>
                </div>

                <div class="glass-panel">
                    <?php if ($needsProfileSetup): ?>
                        <p style="color: var(--danger);">Please finish your profile setup before placing an order.</p>
                    <?php else: ?>
                    <!-- Product Catalog -->
                    <div id="productCatalogSection">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                            <h3 style="margin:0;">Select Services</h3>
                            <span id="catalogLoading" style="font-size:0.85rem; color:#94a3b8;">Loading...</span>
                        </div>
                        <div class="product-grid" id="productGrid"></div>

                        <!-- Cart Summary -->
                        <div id="cartSummary" style="display:none;">
                            <h4 style="margin-bottom:0.75rem;">🛒 Your Cart</h4>
                            <div class="cart-summary">
                                <div id="cartItems"></div>
                                <div class="cart-total"><span>Total</span><span id="cartTotal">₹0</span></div>
                            </div>
                        </div>

                        <form id="orderForm">
                            <div class="form-group">
                                <label>Special Instructions (Optional)</label>
                                <textarea id="orderInstr" class="form-control" rows="2" placeholder="e.g. Use fabric softener on shirts."></textarea>
                            </div>
                            <div class="form-group">
                                <label>Coupon Code (Optional)</label>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" id="couponCode" class="form-control" placeholder="e.g. SAVE10" style="text-transform:uppercase;">
                                    <button type="button" class="btn btn-outline" style="width:auto; padding:0.5rem 1rem;" id="applyCouponBtn">Apply</button>
                                </div>
                                <small id="couponFeedback" style="display:block; margin-top:5px; font-weight:600;"></small>
                            </div>
                            <button type="submit" class="btn btn-primary" id="submitOrderBtn" disabled>Request Pickup</button>
                            <p id="orderMsg" style="margin-top: 1rem; font-weight: 600; display: none;"></p>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- History Section -->
            <section id="history" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Order History</h2>
                
                <div class="tabs">
                    <div class="tab active" onclick="loadHistories('ongoing', this)">Ongoing</div>
                    <div class="tab" onclick="loadHistories('completed', this)">Completed</div>
                </div>

                <div class="glass-panel" id="historyContainer">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">Loading orders...</p>
                </div>
            </section>

            <!-- Payments Section -->
            <section id="payments" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Payment Gateway</h2>
                
                <div class="tabs">
                    <div class="tab active" style="color: var(--danger);" onclick="loadPayments('remaining', this)">Remaining Dues</div>
                    <div class="tab" style="color: var(--secondary);" onclick="loadPayments('completed', this)">Completed</div>
                </div>

                <div class="glass-panel text-center" id="paymentContainer">
                    <span class="material-icons-outlined" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;">check_circle</span>
                    <h3>All Clear!</h3>
                    <p>You have no pending invoices to pay.</p>
                </div>
            </section>

            <!-- Profile Section -->
            <section id="profile" class="section-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
                    <h2>My Shop Profile</h2>
                </div>
                
                <div class="glass-panel" style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    
                    <!-- QR Code Identity Card -->
                    <div style="flex: 1; min-width: 250px; background: rgba(255,255,255,0.5); padding: 1.5rem; border-radius: 12px; text-align: center; display:flex; flex-direction:column; justify-content:center; align-items:center; border: 1px solid var(--glass-border);">
                        <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--primary);">Delivery QR Code</h3>
                        <p style="font-size: 0.8rem; margin-bottom: 1rem;">Show this to the delivery partner to securely complete deliveries.</p>
                        <canvas id="userQrCode"></canvas>
                        <script>
                            // Generate QR lazily once the section is visible
                            document.addEventListener('DOMContentLoaded', () => {
                                const qrHash = "<?= htmlspecialchars($qrCodeHash) ?>";
                                if(qrHash) {
                                    new QRious({
                                        element: document.getElementById('userQrCode'),
                                        value: qrHash,
                                        size: 180,
                                        level: 'M',
                                        foreground: '#1e293b',
                                        background: 'transparent'
                                    });
                                }
                            });
                        </script>
                    </div>

                    <!-- Profile Edit Form -->
                    <div style="flex: 2; min-width: 300px;">
                        <form id="profileForm">
                        <div class="form-group">
                            <label>Phone Number (Unchangeable after Payment Complete)</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly style="background: #e2e8f0; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" id="p_name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" id="p_email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Shop Address</label>
                            <textarea id="p_address" class="form-control" rows="3" required><?= htmlspecialchars($user['shop_address'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Alternate Contact <small style="color:#94a3b8;">(Optional, 10 digits)</small></label>
                            <input type="tel" id="p_alt" class="form-control"
                                value="<?= htmlspecialchars($user['alt_contact'] ?? '') ?>"
                                maxlength="10"
                                inputmode="numeric"
                                placeholder="e.g. 9876543210"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'').substring(0,10)">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="saveProfileBtn">Save Details</button>
                        <p id="profileMsg" style="margin-top: 1rem; font-weight: 600; display: none;"></p>
                    </form>
                    </div> <!-- End Profile Form Wrapper -->
                </div> <!-- End Flex Container -->
            </section>

        </main>
    </div>

    <!-- Return Request Modal -->
    <div class="modal-overlay" id="returnModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal glass-panel" style="background:white; padding:2rem; border-radius:12px; width:90%; max-width:400px;">
            <h3 style="margin-bottom: 1rem; color:var(--danger);">Request a Return</h3>
            <p style="font-size: 0.9rem; margin-bottom: 1rem;">Please provide details and a clear photo of the issue.</p>
            <input type="hidden" id="returnOrderId">
            
            <div class="form-group">
                <label style="font-size: 0.8rem; font-weight: 600;">Reason for Return</label>
                <textarea id="returnReason" class="form-control" rows="2" placeholder="e.g. Item torn or stain not removed" required></textarea>
            </div>
            
            <div class="form-group">
                <label style="font-size: 0.8rem; font-weight: 600;">Photo Evidence</label>
                <input type="file" id="returnPhoto" accept="image/*" class="form-control" required style="margin-bottom: 1rem;">
            </div>
            
            <button class="btn btn-danger" onclick="submitReturn()" id="btnSubmitReturn">Submit Request</button>
            <button class="btn" onclick="closeReturnModal()" style="background:#e2e8f0; color:#475569; margin-top: 0.5rem;">Cancel</button>
            <p id="returnMsg" style="color:var(--danger); display:none; margin-top:0.5rem;"></p>
        </div>
    </div>

    <script>
        // Tab Switching Logic
        const csrfToken = "<?= $_SESSION['csrf_token'] ?? '' ?>";

        function switchTab(tabId) {
            // Update Sidebar UI
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Update Content Visibility
            document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        }

        // Logout
        document.getElementById('logoutBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('../api/auth.php', {
                method: 'POST',
                headers:{ 
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': csrfToken
                },
                body: JSON.stringify({ action: 'logout' })
            });
            window.location.href = '../index.php';
        });

        async function initiatePayment(orderId, amount) {
            try {
                // 1. Create Order on our backend (which calls Razorpay)
                const res = await fetch('../api/payments.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ action: 'create_rzp_order', order_id: orderId })
                });
                const data = await res.json();
                
                if (!data.success) {
                    alert(data.message);
                    return;
                }

                // 2. Open Razorpay Checkout
                const options = {
                    "key": data.key,
                    "amount": data.amount,
                    "currency": "INR",
                    "name": "DigiWash",
                    "description": "Laundry Service Payment",
                    "order_id": data.rzp_order_id,
                    "handler": async function (response) {
                        // 3. Verify payment on our backend
                        const verifyRes = await fetch('../api/payments.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify({
                                action: 'verify_payment',
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_signature: response.razorpay_signature,
                                local_order_id: orderId
                            })
                        });
                        const verifyData = await verifyRes.json();
                        alert(verifyData.message);
                        if (verifyData.success) {
                            window.location.reload();
                        }
                    },
                    "prefill": {
                        "name": "<?= htmlspecialchars($user['name'] ?? '') ?>",
                        "email": "<?= htmlspecialchars($user['email'] ?? '') ?>",
                        "contact": "<?= htmlspecialchars($user['phone'] ?? '') ?>"
                    },
                    "theme": { "color": "#4f46e5" }
                };
                const rzp = new Razorpay(options);
                rzp.open();
            } catch (e) {
                console.error(e);
                alert("Payment initiation failed. Please try again.");
            }
        }

        // Initialization and Data Loading
        document.addEventListener('DOMContentLoaded', () => {
            fetchStats();
            loadOrders('ongoing');
            loadOrders('completed');
            loadPayments('remaining');
            loadPayments('completed');
            loadProductCatalog();

            // Initialize FCM if supported
            if ('serviceWorker' in navigator && typeof firebase !== 'undefined') {
                requestNotificationPermission();
            }
        });

        async function requestNotificationPermission() {
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    const messaging = firebase.messaging();
                    // Note: Use your own VAPID Public Key here from Firebase Console
                    const token = await messaging.getToken().catch(e => console.warn("Token fetch failed:", e));
                    if (token) {
                        saveFcmToken(token);
                    }
                }
            } catch (err) {
                console.warn('FCM Permission Error:', err);
            }
        }

        async function saveFcmToken(token) {
            await fetch('../api/user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'save_fcm_token', fcm_token: token })
            });
        }

        // Coupon Validation
        document.getElementById('applyCouponBtn')?.addEventListener('click', async () => {
            const code = document.getElementById('couponCode').value;
            const weight = parseFloat(document.getElementById('orderWeight').value) || 0;
            const orderAmount = weight * 50; // ₹50/kg
            const feedback = document.getElementById('couponFeedback');
            if(!code) { feedback.innerText = 'Please enter a coupon code.'; feedback.style.color = 'var(--danger)'; return; }
            
            feedback.innerText = 'Validating...'; feedback.style.color = '#94a3b8';
            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ action: 'validate_coupon', coupon_code: code, order_amount: orderAmount })
                });
                const data = await res.json();
                feedback.innerText = data.message;
                feedback.style.color = data.success ? 'var(--secondary)' : 'var(--danger)';
                if (data.success && data.discount_amount > 0) {
                    feedback.innerText = `✓ ${data.message} — You save ₹${data.discount_amount}`;
                }
            } catch(e) {
                feedback.innerText = 'Error connecting to server. Please try again.';
                feedback.style.color = 'var(--danger)';
            }
        });

        // Create Order Submit Logic

        async function fetchStats() {
            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST', 
                    headers:{ 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ action: 'get_dashboard_stats' })
                });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('val_active').innerText = data.active_orders;
                    document.getElementById('val_completed').innerText = data.completed_orders;
                    document.getElementById('val_pending').innerText = '₹' + data.pending_payment;
                    if(data.active_orders > 0) {
                        document.getElementById('recentActivityText').innerText = 'Your clothes are currently being processed.';
                    }
                }
            } catch (e) { console.error(e); }
        }

        async function loadHistories(type, tabElement = null) {
            if(tabElement){
                tabElement.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
                tabElement.classList.add('active');
            }
            const container = document.getElementById('historyContainer');
            container.innerHTML = 'Loading...';

            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST', 
                    headers:{ 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ action: 'get_orders', type: type })
                });
                const data = await res.json();
                if(data.success && data.orders.length > 0) {
                    container.innerHTML = data.orders.map(o => `
                        <div style="border-bottom:1px solid #e2e8f0; padding:1rem 0; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>Order #${o.id}</strong> - Status: <span style="color:var(--primary);">${o.status.toUpperCase()}</span>
                                <br><small>Amount: ₹${o.total_amount} | Created: ${new Date(o.created_at).toLocaleString()}</small>
                            </div>
                            ${o.status === 'delivered' ? `
                                <button class="btn btn-danger" style="width:auto; padding:0.3rem 0.8rem; font-size:0.8rem;" onclick="openReturnModal(${o.id})">Request Return</button>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `<p style="text-align: center; color: #94a3b8; padding: 2rem 0;">No ${type} orders.</p>`;
                }
            } catch(e) { container.innerHTML = 'Error Loading.'; }
        }

        async function loadPayments(type, tabElement = null) {
            if(tabElement){
                tabElement.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
                tabElement.classList.add('active');
            }
            const container = document.getElementById('paymentContainer');
            container.innerHTML = 'Loading...';

            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST', 
                    headers:{ 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ action: 'get_payments', type: type })
                });
                const data = await res.json();
                if(data.success && data.payments.length > 0) {
                    container.style.textAlign = 'left';
                    container.innerHTML = data.payments.map(o => `
                        <div style="border-bottom:1px solid #e2e8f0; padding:1rem 0;">
                            <strong>Order #${o.order_id}</strong> - <span style="color:${type === 'remaining' ? 'var(--danger)' : 'var(--secondary)'};">${type.toUpperCase()}</span>
                            <br><small>Amount: ₹${o.amount} | Due for Payment via: ${o.payment_mode}</small>
                            ${type === 'remaining' ? `<br><button class="btn btn-primary" style="padding:0.3rem 0.8rem; font-size:0.8rem; width:auto; margin-top:0.5rem;" onclick="initiatePayment(${o.order_id}, ${o.amount})">Pay Now (Razorpay)</button>` : ''}
                        </div>
                    `).join('');
                } else {
                    container.style.textAlign = 'center';
                    container.innerHTML = `
                        <span class="material-icons-outlined" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;">check_circle</span>
                        <h3>All Clear!</h3>
                        <p>No ${type} invoices found.</p>
                    `;
                }
            } catch(e) { container.innerHTML = 'Error Loading.'; }
        }

        // Product Catalog & Cart State
        let cart = {}; // { product_price_id: { product_name, size_label, price, quantity } }
        let appliedDiscount = 0;

        // ── Toast Notification System ──────────────────────────────────────────
        function toast(type, title, message, duration = 4000) {
            const iconMap = { success: '✅', error: '❌', info: 'ℹ️' };
            const c = document.getElementById('toast-container');
            const el = document.createElement('div');
            el.className = `toast ${type}`;
            el.innerHTML = `
                <div class="toast-icon">${iconMap[type]||'🔔'}</div>
                <div class="toast-body">
                    <div class="toast-title">${title}</div>
                    ${message ? `<div class="toast-msg">${message}</div>` : ''}
                    <div class="toast-progress" style="width:100%; transition:width ${duration}ms linear;"></div>
                </div>
                <button class="toast-close" onclick="this.closest('.toast').remove()">✕</button>
            `;
            c.appendChild(el);
            setTimeout(() => { const bar = el.querySelector('.toast-progress'); if(bar) bar.style.width='0'; }, 50);
            setTimeout(() => { el.style.animation='toastOut 0.3s ease forwards'; setTimeout(()=>el.remove(), 300); }, duration);
        }

        // ── Load product catalog ──────────────────────────────────────────────
        async function loadProductCatalog() {
            const grid = document.getElementById('productGrid');
            const loading = document.getElementById('catalogLoading');
            if (!grid) return;
            try {
                const res = await fetch('../api/products.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ action: 'get_products', active_only: true })
                });
                const d = await res.json();
                loading.style.display = 'none';
                if (!d.success || !d.products.length) {
                    grid.innerHTML = '<p style="color:#94a3b8; grid-column:1/-1;">No services available yet.</p>';
                    return;
                }
                grid.innerHTML = d.products.map(p => {
                    const imgHtml = p.image_url
                        ? `<img src="../${p.image_url}" style="width:100%;height:130px;object-fit:cover;">`
                        : `<i class="material-icons-outlined" style="font-size:3rem;color:#cbd5e1;">local_laundry_service</i>`;
                    const priceChips = p.prices.map(pp => `
                        <div class="price-chip" data-ppid="${pp.id}" data-price="${pp.price}" data-product="${p.id}" data-pname="${p.name.replace(/"/g,'&quot;')}" data-size="${pp.size_label}" onclick="selectPrice(this, ${pp.id}, '${p.name.replace(/'/g,'')}', '${pp.size_label}', ${pp.price})">
                            ${pp.size_label} — ₹${pp.price}
                        </div>
                    `).join('');
                    return `
                        <div class="product-card" id="pcard-${p.id}">
                            <div class="product-img">${imgHtml}</div>
                            <div class="product-info">
                                <div class="product-name">${p.name}</div>
                                ${p.description ? `<div style="font-size:0.78rem;color:#64748b;margin-bottom:4px">${p.description}</div>` : ''}
                                <div class="product-prices" id="prices-${p.id}">${priceChips}</div>
                                <div class="qty-ctrl" id="qty-${p.id}" style="display:none">
                                    <button type="button" class="qty-btn" onclick="changeQty(${p.id}, -1)">−</button>
                                    <span class="qty-val" id="qtyval-${p.id}">1</span>
                                    <button type="button" class="qty-btn" onclick="changeQty(${p.id}, 1)">+</button>
                                    <button type="button" class="qty-btn" style="margin-left:auto;color:#ef4444;border-color:#ef4444;font-size:0.8rem;width:auto;padding:0 8px;border-radius:8px;" onclick="removeFromCart(${p.id})">Remove</button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            } catch(e) {
                loading.textContent = 'Failed to load services. Please refresh.';
            }
        }

        function selectPrice(chip, ppId, productName, sizeLabel, price) {
            const productId = parseInt(chip.getAttribute('data-product'));
            // Deselect other chips in same product
            document.querySelectorAll(`#prices-${productId} .price-chip`).forEach(c => c.classList.remove('selected'));
            chip.classList.add('selected');
            // Update cart
            cart[productId] = { product_price_id: ppId, product_name: productName, size_label: sizeLabel, price: parseFloat(price), quantity: parseInt(document.getElementById('qtyval-'+productId)?.textContent||1) };
            document.getElementById('qty-'+productId).style.display = 'flex';
            document.getElementById('pcard-'+productId).classList.add('has-items');
            updateCartUI();
        }

        function changeQty(productId, delta) {
            if (!cart[productId]) return;
            const newQty = Math.max(1, cart[productId].quantity + delta);
            cart[productId].quantity = newQty;
            document.getElementById('qtyval-'+productId).textContent = newQty;
            updateCartUI();
        }

        function removeFromCart(productId) {
            delete cart[productId];
            document.getElementById('qtyval-'+productId).textContent = 1;
            document.getElementById('qty-'+productId).style.display = 'none';
            document.getElementById('pcard-'+productId).classList.remove('has-items');
            document.querySelectorAll(`#prices-${productId} .price-chip`).forEach(c => c.classList.remove('selected'));
            updateCartUI();
        }

        function updateCartUI() {
            const items = Object.values(cart);
            const summaryEl = document.getElementById('cartSummary');
            const submitBtn = document.getElementById('submitOrderBtn');
            if (!items.length) { summaryEl.style.display='none'; submitBtn.disabled=true; return; }
            summaryEl.style.display = 'block';
            submitBtn.disabled = false;
            let subtotal = 0;
            document.getElementById('cartItems').innerHTML = items.map(it => {
                const line = it.price * it.quantity;
                subtotal += line;
                return `<div class="cart-item-row"><span>${it.product_name} (${it.size_label}) × ${it.quantity}</span><span style="font-weight:700;">₹${line.toFixed(2)}</span></div>`;
            }).join('');
            const totalAfterDiscount = Math.max(0, subtotal - appliedDiscount);
            if (appliedDiscount > 0) {
                document.getElementById('cartItems').innerHTML += `<div class="cart-item-row" style="color:#10b981"><span>Coupon Discount</span><span>−₹${appliedDiscount.toFixed(2)}</span></div>`;
            }
            document.getElementById('cartTotal').textContent = '₹' + totalAfterDiscount.toFixed(2);
        }

        // Create Order Submit
        const orderForm = document.getElementById('orderForm');
        if (orderForm) {
            orderForm.addEventListener('submit', async(e) => {
                e.preventDefault();
                const btn = document.getElementById('submitOrderBtn');
                const items = Object.values(cart);
                if (!items.length) { toast('error','Empty Cart','Please select at least one service.'); return; }

                btn.innerHTML = 'Placing Order…'; btn.disabled = true;

                const payload = {
                    action: 'create_order',
                    items: items.map(it => ({ product_price_id: it.product_price_id, quantity: it.quantity })),
                    instructions: document.getElementById('orderInstr').value,
                    coupon_code: document.getElementById('couponCode').value
                };

                const res = await fetch('../api/orders.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify(payload)
                });

                let result;
                try { result = await res.json(); }
                catch { result = { success: false, message: 'Server returned an invalid response.' }; }

                if (result.success) {
                    toast('success', 'Order Placed! 🎉', result.message);
                    // Reset
                    cart = {}; appliedDiscount = 0;
                    document.querySelectorAll('.price-chip').forEach(c => c.classList.remove('selected'));
                    document.querySelectorAll('.product-card').forEach(c => c.classList.remove('has-items'));
                    document.querySelectorAll('.qty-ctrl').forEach(c => c.style.display='none');
                    document.querySelectorAll('.qty-val').forEach(c => c.textContent='1');
                    document.getElementById('cartSummary').style.display='none';
                    document.getElementById('couponCode').value='';
                    document.getElementById('couponFeedback').textContent='';
                    orderForm.reset();
                    updateCartUI();
                    fetchStats();
                    loadHistories('ongoing');
                    switchTab('history');
                } else {
                    toast('error', 'Order Failed', result.message);
                }
                btn.innerHTML = 'Request Pickup'; btn.disabled = false;
            });
        }

        // Real Save Profile Logic
        document.getElementById('profileForm').addEventListener('submit', async(e) => {
            e.preventDefault();
            const btn = document.getElementById('saveProfileBtn');
            const msg = document.getElementById('profileMsg');
            btn.innerHTML = 'Saving...';
            btn.disabled = true;
            msg.style.display = 'none';

            try {
                const res = await fetch('../api/user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({
                        action: 'update_profile',
                        name: document.getElementById('p_name').value,
                        email: document.getElementById('p_email').value,
                        shop_address: document.getElementById('p_address').value,
                        alt_contact: document.getElementById('p_alt').value
                    })
                });
                
                const result = await res.json();
                
                msg.innerText = result.message;
                msg.style.display = 'block';
                msg.style.color = result.success ? 'var(--secondary)' : 'var(--danger)';
                btn.innerHTML = 'Save Details';
                btn.disabled = false;

                if (result.success) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (error) {
                msg.innerText = "Error requesting server."; 
                msg.style.color = "var(--danger)"; 
                msg.style.display = 'block';
                btn.innerHTML = 'Save Details'; 
                btn.disabled = false;
            }
        });

        // Return Request Logic
        function openReturnModal(orderId) {
            document.getElementById('returnOrderId').value = orderId;
            document.getElementById('returnReason').value = '';
            document.getElementById('returnPhoto').value = '';
            document.getElementById('returnModal').style.display = 'flex';
        }

        function closeReturnModal() {
            document.getElementById('returnModal').style.display = 'none';
            document.getElementById('returnMsg').style.display = 'none';
        }

        async function submitReturn() {
            const orderId = document.getElementById('returnOrderId').value;
            const reason = document.getElementById('returnReason').value;
            const photoFile = document.getElementById('returnPhoto').files[0];
            const btn = document.getElementById('btnSubmitReturn');
            const msg = document.getElementById('returnMsg');

            if(!reason || !photoFile) { msg.innerText = "Reason and Photo are required."; msg.style.display = 'block'; return; }

            btn.innerHTML = 'Uploading...'; btn.disabled = true; msg.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'request_return');
            formData.append('order_id', orderId);
            formData.append('reason', reason);
            formData.append('return_photo', photoFile);

            try {
                const res = await fetch('../api/orders.php', { 
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrfToken },
                    body: formData 
                });
                const data = await res.json();
                if(data.success) {
                    toast('success','Return Submitted',data.message);
                    closeReturnModal();
                } else {
                    msg.innerText = data.message; msg.style.display = 'block';
                }
            } catch(e) { msg.innerText = "Error submitting request."; msg.style.display = 'block'; }
            btn.innerHTML = 'Submit Request'; btn.disabled = false;
        }
    </script>
</body>
</html>