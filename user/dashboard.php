<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../index.php'); exit;
}
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$needsProfileSetup = empty($user['name']) || empty($user['shop_address']) || empty($user['market_id']);
$qrCodeHash = $user['qr_code_hash'] ?? '';
$csrfToken  = $_SESSION['csrf_token'] ?? '';
$userName   = htmlspecialchars($user['name'] ?? 'User');
$userPhone  = htmlspecialchars($user['phone'] ?? '');
$payLaterPlan = $user['pay_later_plan'] ?? 'NONE';
$payLaterStatus = $user['pay_later_status'] ?? 'locked';

$markets = $pdo->query("SELECT id, name FROM markets ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Delivery OTP Generator (30 Min Rolling Window)
$otpSalt = "digiwash_delivery_otp_sec";
$timeWindow = floor(time() / 1800); // 30 minutes
$hashValue = abs(crc32($_SESSION['user_id'] . $otpSalt . $timeWindow)) % 1000000;
$userDeliveryOtp = str_pad($hashValue, 6, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash — My Dashboard</title>
    <meta name="description" content="Manage your laundry orders, payments and profile on DigiWash.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <!-- Firebase SDKs for Push Notifications -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-messaging-compat.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div id="toast-wrap"></div>

<div class="app-wrap">
    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="material-icons-outlined">local_laundry_service</i> DigiWash
        </div>


        <div class="security-widget">
            <div class="security-lbl">Delivery Verify PIN</div>
            <div class="security-val"><?= $userDeliveryOtp ?></div>
            <div class="security-sub">PIN auto-refreshes every 30 mins</div>
        </div>

        <div class="nav-section">Menu</div>
        <div class="nav-item active" id="nav-home" onclick="switchTab('home',this)">
            <i class="material-icons-outlined">dashboard</i> Home
        </div>
        <div class="nav-item" id="nav-order" onclick="switchTab('order',this)">
            <i class="material-icons-outlined">add_shopping_cart</i> New Order
        </div>
        <div class="nav-item" id="nav-history" onclick="switchTab('history',this)">
            <i class="material-icons-outlined">receipt_long</i> My Orders
        </div>
        <div class="nav-section">Account</div>
        <div class="nav-item" id="nav-payments" onclick="switchTab('payments',this)">
            <i class="material-icons-outlined">account_balance_wallet</i> Payments
        </div>
        <div class="nav-item" id="nav-profile" onclick="switchTab('profile',this)">
            <i class="material-icons-outlined">manage_accounts</i> Profile
        </div>
        <div style="margin-top:auto;padding-top:1.5rem;">
            <div class="nav-item" id="logoutBtn" style="color:#ef4444;">
                <i class="material-icons-outlined">logout</i> Logout
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main">

        <!-- GLOBAL TOP NAV -->
        <div class="header-actions">
            <!-- Marketplace Button -->
            <a href="marketplace.php" class="btn-marketplace">
                <i class="material-icons-outlined">storefront</i>
                <span>Marketplace</span>
            </a>
            
            <div class="nav-bell hover-lift" id="notifBellBtn">
                <i class="material-icons-outlined">notifications</i>
                <span class="nav-badge" id="notifBadge" style="position:absolute; top:-6px; right:-6px; display:none;">0</span>
            </div>
            <!-- Notif Dropdown -->
            <div id="notifDropdown" class="notif-drop">
                <div style="padding:1.2rem; border-bottom:1px solid var(--border); font-weight:900; font-size:.95rem; display:flex; justify-content:space-between; align-items:center;">
                    Alerts <button onclick="markNotifsRead()" style="background:none;border:none;color:var(--primary);font-size:.78rem;font-weight:800;cursor:pointer;">Mark all read</button>
                </div>
                <div id="notifList" style="max-height:380px; overflow-y:auto;">
                    <div style="padding:2rem 1rem;text-align:center;color:var(--muted);font-size:.85rem;">Checking alerts...</div>
                </div>
            </div>
        </div>

        <?php if($needsProfileSetup): ?>
        <div class="alert-banner">
            <i class="material-icons-outlined">warning_amber</i>
            <p>Complete your profile (Name, Address & Market) before placing orders.</p>
            <button onclick="switchTab('profile',document.getElementById('nav-profile'))">Fix Now →</button>
        </div>
        <?php endif; ?>

        <!-- ════ HOME ════ -->
        <section id="home" class="section active">
            <div class="page-header">
                <div>
                    <div class="page-title">Welcome back, <span><?= $userName ?></span>!</div>
                    <p style="color:var(--muted);font-size:.9rem;margin-top:2px;">Here's your laundry overview</p>
                </div>
                <button class="btn btn-primary" onclick="switchTab('order',document.getElementById('nav-order'))">
                    <i class="material-icons-outlined" style="font-size:1rem;">add</i> New Order
                </button>
            </div>

            <div class="stats-row">
                <div class="stat-box hover-lift">
                    <div class="stat-lbl">Active Orders</div>
                    <div class="stat-val" id="sActive">—</div>
                    <div class="stat-sub">In pipeline</div>
                </div>
                <div class="stat-box green hover-lift">
                    <div class="stat-lbl">Completed</div>
                    <div class="stat-val" id="sCompleted">—</div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-box red hover-lift">
                    <div class="stat-lbl">Pending Dues</div>
                    <div class="stat-val" id="sDues">₹—</div>
                    <div class="stat-sub">To be paid</div>
                </div>
            </div>

            <div class="card">
                <div style="font-weight:800;font-size:1rem;margin-bottom:1rem;">📦 Recent Activity</div>
                <div id="recentActivity">
                    <p style="color:var(--muted);text-align:center;padding:2rem;">Loading activity…</p>
                </div>
            </div>
        </section>

        <!-- ════ NEW ORDER ════ -->
        <section id="order" class="section">
            <div class="page-header">
                <div class="page-title">New <span>Order</span></div>
            </div>

            <?php if($needsProfileSetup): ?>
            <div class="card" style="text-align:center;padding:3rem;">
                <i class="material-icons-outlined" style="font-size:3rem;color:#cbd5e1;">person_off</i>
                <p style="font-weight:700;margin-top:1rem;">Please complete your profile before placing orders.</p>
                <button class="btn btn-primary" style="margin-top:1rem;" onclick="switchTab('profile',document.getElementById('nav-profile'))">Complete Profile</button>
            </div>
            <?php else: ?>

            <!-- Subscription Section -->
            <div class="card" style="margin-bottom:1.25rem; border:1px solid #e0e7ff; background:#f8faff;">
                <div style="font-weight:800;font-size:1rem;margin-bottom:1rem;display:flex;align-items:center;gap:8px;">
                    <i class="material-icons-outlined" style="color:var(--primary);">autorenew</i> Weekly Subscriptions
                </div>
                <div style="font-size:.85rem;color:var(--muted);margin-bottom:1.25rem;">Set a schedule and we'll automatically create a pickup request for you. Normal limits apply.</div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                    <button class="btn btn-outline subs-btn" id="sub-NONE" onclick="saveAutoOrder('NONE')" style="font-size:.8rem;padding:.6rem .25rem;justify-content:center;">No Auto-pickup</button>
                    <button class="btn btn-outline subs-btn" id="sub-MONDAYS" onclick="saveAutoOrder('MONDAYS')" style="font-size:.8rem;padding:.6rem .25rem;justify-content:center;">Every Monday</button>
                </div>
            </div>

            <!-- Product grid -->
            <div class="card" style="margin-bottom:1.25rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <div style="font-weight:800;font-size:1rem;">🧺 Select Services</div>
                    <span id="catalogStatus" style="font-size:.82rem;color:var(--muted);">Loading…</span>
                </div>
                <div class="products-grid" id="productGrid"></div>
            </div>

            <!-- Cart + Form -->
            <div class="card">
                <div id="cartWrap" style="display:none;margin-bottom:1.25rem;">
                    <div style="font-weight:800;font-size:1rem;margin-bottom:.75rem;">🛒 Your Cart</div>
                    <div class="cart-box">
                        <div id="cartLines"></div>
                        <div class="cart-total"><span>Total</span><span id="cartGrand">₹0</span></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Special Instructions <span style="color:var(--muted);font-weight:500;">(Optional)</span></label>
                    <textarea id="orderInstr" class="form-control" rows="2" placeholder="e.g. Use fabric softener, handle silk gently…"></textarea>
                </div>

                <div class="form-group">
                    <label>Coupon Code <span style="color:var(--muted);font-weight:500;">(Optional)</span></label>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="couponCode" class="form-control" placeholder="e.g. SAVE10" style="text-transform:uppercase;">
                        <button class="btn btn-outline" id="applyCouponBtn">Apply</button>
                    </div>
                    <div id="couponFeedback" style="font-size:.82rem;font-weight:600;margin-top:5px;"></div>
                </div>

                <div class="form-group">
                    <label>Payment Mode</label>
                    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                        <select id="paymentMode" class="form-control" style="flex:1;">
                            <option value="ONLINE">Pay Now (Online)</option>
                            <option value="COD">Cash on Delivery</option>
                            <?php if($payLaterStatus === 'approved' && $payLaterPlan !== 'NONE'): ?>
                                <option value="<?= $payLaterPlan ?>">Pay Later (<?= str_replace('PAY_LATER_','', $payLaterPlan) ?> Orders)</option>
                            <?php endif; ?>
                        </select>
                        <?php if($payLaterStatus === 'locked' || $payLaterStatus === 'declined' || $payLaterPlan === 'NONE'): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="requestPayLater()" style="white-space:nowrap; padding:.6rem 1rem;"><i class="material-icons-outlined" style="font-size:1rem;vertical-align:middle;">security_update_good</i> Request Pay Later</button>
                        <?php elseif($payLaterStatus === 'pending_approval'): ?>
                            <button type="button" class="btn btn-ghost btn-sm" disabled style="white-space:nowrap; padding:.6rem 1rem;">Pending Admin Approval</button>
                        <?php endif; ?>
                    </div>
                </div>

                <button class="btn btn-primary" id="submitOrderBtn" disabled style="width:100%;justify-content:center;padding:.8rem;">
                    <i class="material-icons-outlined" style="font-size:1rem;">local_shipping</i> Request Pickup
                </button>
            </div>
            <?php endif; ?>
        </section>

        <!-- ════ MY ORDERS ════ -->
        <section id="history" class="section">
            <div class="page-header">
                <div class="page-title">My <span>Orders</span></div>
                <button class="btn btn-ghost btn-sm" onclick="loadOrders(currentOrderTab)">↻ Refresh</button>
            </div>
            <div class="tab-row">
                <div class="tab-btn active" id="tab-ongoing" onclick="loadOrders('ongoing',this)">Ongoing</div>
                <div class="tab-btn" id="tab-completed" onclick="loadOrders('completed',this)">Completed</div>
            </div>
            <div id="ordersList"></div>
        </section>

        <!-- ════ PAYMENTS ════ -->
        <section id="payments" class="section">
            <div class="page-header">
                <div class="page-title">Payments <span>& Dues</span></div>
            </div>
            <div class="tab-row">
                <div class="tab-btn active" id="ptab-remaining" onclick="loadPayments('remaining',this)">💳 Remaining Dues</div>
                <div class="tab-btn" id="ptab-completed" onclick="loadPayments('completed',this)">✅ Paid</div>
            </div>
            <div id="paymentsList"></div>
        </section>

        <!-- ════ PROFILE ════ -->
        <section id="profile" class="section">
            <div class="page-header"><div class="page-title">My <span>Profile</span></div></div>

            <div class="profile-header">
                <div class="profile-av"><?= strtoupper(substr($userName,0,1)) ?></div>
                <div>
                    <div class="profile-name"><?= $userName ?></div>
                    <div class="profile-phone">📞 <?= $userPhone ?></div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 280px;gap:1.5rem;flex-wrap:wrap;" class="profile-grid">
                <!-- Edit form -->
                <div class="card">
                    <div style="font-weight:800;font-size:1rem;margin-bottom:1.25rem;">Edit Details</div>
                    <form id="profileForm">
                        <div class="form-group">
                            <label>Phone (read-only)</label>
                            <input class="form-control" value="<?= $userPhone ?>" readonly style="background:#f1f5f9;cursor:not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" id="p_name" class="form-control" value="<?= htmlspecialchars($user['name']??'') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" id="p_email" class="form-control" value="<?= htmlspecialchars($user['email']??'') ?>">
                        </div>
                        <div class="form-group">
                            <label>Shop / Pickup Address *</label>
                            <textarea id="p_address" class="form-control" rows="3" required><?= htmlspecialchars($user['shop_address']??'') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Service Market Zone *</label>
                            <div style="display:flex;gap:10px;">
                                <select id="p_market" class="form-control" style="flex:1;" required>
                                    <option value="">Select your area...</option>
                                    <?php foreach($markets as $m): ?>
                                        <option value="<?= $m['id'] ?>" <?= ($user['market_id']==$m['id'])?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" class="btn btn-outline" onclick="detectLocation()" style="padding:0 1rem;" title="Auto-detect via GPS">
                                    <i class="material-icons-outlined" style="font-size:1.1rem;vertical-align:middle;">my_location</i>
                                </button>
                            </div>
                            <div id="locStatus" style="font-size:0.75rem;color:var(--muted);margin-top:5px;"></div>
                            <input type="hidden" id="p_lat" value="<?= htmlspecialchars($user['lat']??'') ?>">
                            <input type="hidden" id="p_lng" value="<?= htmlspecialchars($user['lng']??'') ?>">
                        </div>
                        <div class="form-group">
                            <label>Alternate Contact <span style="color:var(--muted);font-weight:500;">(Optional, 10 digits)</span></label>
                            <input type="tel" id="p_alt" class="form-control" value="<?= htmlspecialchars($user['alt_contact']??'') ?>" maxlength="10" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)">
                        </div>
                        <button type="submit" class="btn btn-primary" id="saveProfileBtn" style="width:100%;justify-content:center;">
                            <i class="material-icons-outlined" style="font-size:1rem;">save</i> Save Details
                        </button>
                        <div id="profileMsg" style="font-size:.85rem;font-weight:600;margin-top:.75rem;display:none;"></div>
                    </form>
                </div>

                <!-- QR Code -->
                <div>
                    <div class="qr-card">
                        <h4>🔲 Delivery QR Code</h4>
                        <canvas id="userQrCode" style="border-radius:8px;"></canvas>
                        <p>Show this to the delivery partner to securely complete your delivery.</p>
                    </div>
                    <div class="card-sm" style="margin-top:1rem;text-align:center;">
                        <div style="font-size:.82rem;font-weight:600;color:var(--muted);"><i class="material-icons-outlined" style="vertical-align:middle; font-size:1.1rem; color:var(--primary);">verified_user</i> DigiWash Standard Security</div>
                        <div style="font-size:.7rem;color:var(--muted);margin-top:6px; line-height:1.4;">Active Deliveries will prompt for verifying your session.</div>
                    </div>
                </div>
            </div>
        </section>

    </main>
</div>

<!-- ── Return Modal ── -->
<div class="modal-overlay" id="returnModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('returnModal')">✕</button>
        <div class="modal-title">↩️ Request Return</div>
        <div class="modal-sub">Provide a reason and photo. Admin will review within 24h.</div>
        <input type="hidden" id="returnOrderId">
        <div class="form-group">
            <label>Reason for Return *</label>
            <textarea id="returnReason" class="form-control" rows="3" placeholder="Describe the issue…"></textarea>
        </div>
        <div class="form-group">
            <label>Photo Evidence *</label>
            <input type="file" id="returnPhoto" accept="image/*" class="form-control">
        </div>
        <div style="display:flex;gap:.75rem;margin-top:1rem;">
            <button class="btn btn-danger" style="flex:1;justify-content:center;" onclick="submitReturn()" id="btnSubmitReturn">Submit Return</button>
            <button class="btn btn-ghost" onclick="closeModal('returnModal')">Cancel</button>
        </div>
        <div id="returnMsg" style="font-size:.85rem;font-weight:600;margin-top:.75rem;display:none;"></div>
    </div>
</div>

<!-- ── Payment Limit Modal ── -->
<div class="modal-overlay" id="limitModal">
    <div class="modal-box" style="text-align:center; padding: 2.5rem 2rem; max-width:400px;">
        <i class="material-icons-outlined" style="font-size:3.5rem; color:var(--danger); margin-bottom:1rem;">credit_score</i>
        <div class="modal-title" style="font-size:1.3rem;">Service Paused</div>
        <div class="modal-sub" style="font-size:.9rem; margin-top:.5rem;">You have reached your ordering limit. Please clear your outstanding dues to resume booking laundry services.</div>
        
        <div style="background:#fef2f2; border:1.5px solid #fecaca; border-radius:12px; padding:1.25rem; margin:1.5rem 0;">
            <div style="font-size:.8rem; font-weight:700; color:#991b1b; text-transform:uppercase;">Outstanding Dues</div>
            <div style="font-size:2rem; font-weight:900; color:var(--danger); margin-top:4px;" id="limitModalDues">₹0</div>
        </div>

        <button class="btn btn-primary" style="width:100%; justify-content:center; padding:.85rem; font-size:1rem;" onclick="closeModal('limitModal'); switchTab('payments', document.getElementById('nav-payments'))">Pay Now to Continue</button>
        <button class="btn btn-ghost" style="width:100%; justify-content:center; margin-top:10px;" onclick="closeModal('limitModal')">Dismiss</button>
    </div>
</div>

<div class="mobile-bottom-nav">
    <a href="javascript:void(0)" class="mobile-nav-item active" id="mob-nav-home" onclick="switchTab('home', document.getElementById('nav-home'))">
        <i class="material-icons-outlined">dashboard</i>
        Home
    </a>
    <a href="javascript:void(0)" class="mobile-nav-item" id="mob-nav-order" onclick="switchTab('order', document.getElementById('nav-order'))">
        <i class="material-icons-outlined">add_shopping_cart</i>
        Order
    </a>
    <a href="javascript:void(0)" class="mobile-nav-item" id="mob-nav-history" onclick="switchTab('history', document.getElementById('nav-history'))">
        <i class="material-icons-outlined">receipt_long</i>
        History
    </a>
    <a href="javascript:void(0)" class="mobile-nav-item" id="mob-nav-payments" onclick="switchTab('payments', document.getElementById('nav-payments'))">
        <i class="material-icons-outlined">account_balance_wallet</i>
        Payments
    </a>
    <a href="javascript:void(0)" class="mobile-nav-item" id="mob-nav-profile" onclick="switchTab('profile', document.getElementById('nav-profile'))">
        <i class="material-icons-outlined">manage_accounts</i>
        Profile
    </a>
</div>

<script>
const csrfToken = "<?= $csrfToken ?>";
const userPayLaterPlan = "<?= $payLaterPlan ?>";
const userPayLaterStatus = "<?= $payLaterStatus ?>";
let cart = {};
let appliedDiscount = 0;
let currentOrderTab = 'ongoing';
let unpaidPayLaterCount = 0;
let unpaidCodCount = 0;
let lastOrderData = null;

// ── Core API Helper — defined once at bottom of file ──────────

// ── Toast ──────────────────────────────────────────────────────
function toast(type, title, msg = '', dur = 4000) {
    const icons = { success:'✅', error:'❌', info:'ℹ️', warn:'⚠️' };
    const wrap = document.getElementById('toast-wrap');
    const el = document.createElement('div');
    el.className = `toast-item ${type}`;
    el.innerHTML = `
        <div class="toast-icon">${icons[type]||'🔔'}</div>
        <div class="toast-body">
            <div class="toast-ttl">${title}</div>
            ${msg ? `<div class="toast-msg">${msg}</div>` : ''}
        </div>
        <button class="toast-cls" onclick="this.closest('.toast-item').remove()">✕</button>
    `;
    wrap.appendChild(el);
    setTimeout(() => { el.style.animation='toastOut .3s ease forwards'; setTimeout(()=>el.remove(), 300); }, dur);
}

// ── Tab switching & Hash Routing ──────────────────────────────
function switchTab(id, el, skipHistory = false) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id)?.classList.add('active');
    
    // Desktop Nav
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    if(el && el.classList.contains('nav-item')) el.classList.add('active');
    else if(document.getElementById('nav-' + id)) document.getElementById('nav-' + id).classList.add('active');

    // Mobile Nav updates
    document.querySelectorAll('.mobile-nav-item').forEach(n => n.classList.remove('active'));
    if (document.getElementById('mob-nav-' + id)) {
        document.getElementById('mob-nav-' + id).classList.add('active');
    }

    if (!skipHistory) {
        window.history.pushState(null, null, '#' + id);
    }

    if (id === 'home')     { fetchStats(); loadActivity(); }
    if (id === 'history')  loadOrders('ongoing');
    if (id === 'payments') loadPayments('remaining');
    if (id === 'order')    loadProductCatalog();
    if (id === 'profile')  renderQR();
}

window.addEventListener('hashchange', () => {
    let hash = window.location.hash.substring(1);
    if (!hash || !document.getElementById(hash)) hash = 'home';
    switchTab(hash, document.getElementById('nav-' + hash), true);
});

// ── Dropdown and Modals Toggle ──────────────────────────────────
document.getElementById('notifBellBtn')?.addEventListener('click', (e) => {
    e.stopPropagation();
    document.getElementById('notifDropdown').classList.toggle('open');
});
window.addEventListener('click', (e) => {
    const dropdown = document.getElementById('notifDropdown');
    const bellBtn = document.getElementById('notifBellBtn');
    if (dropdown && dropdown.classList.contains('open') && e.target !== dropdown && e.target !== bellBtn && !dropdown.contains(e.target)) {
        dropdown.classList.remove('open');
    }
});

function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id)  { document.getElementById(id).classList.add('open'); }

document.querySelectorAll('.modal-overlay').forEach(m =>
    m.addEventListener('click', e => { if(e.target===m) m.classList.remove('open'); })
);

// ── Render QR ──────────────────────────────────────────────────
function renderQR() {
    const hash = "<?= htmlspecialchars($qrCodeHash) ?>";
    if (!hash) return;
    new QRious({ element: document.getElementById('userQrCode'), value: hash, size: 180, level:'M', foreground:'#e2e8f0', background:'#0f172a' });
}

// ── Stats ──────────────────────────────────────────────────────
async function fetchStats() {
    const d = await apiCall('../api/orders.php', 'get_dashboard_stats');
    if (!d.success) return;
    document.getElementById('sActive').textContent    = d.active_orders;
    document.getElementById('sCompleted').textContent = d.completed_orders;
    document.getElementById('sDues').textContent      = '₹' + parseFloat(d.pending_payment||0).toFixed(0);
    unpaidPayLaterCount = parseInt(d.unpaid_pay_later || 0);
    unpaidCodCount = parseInt(d.unpaid_cod || 0);
    lastOrderData = d.recent_order || null;
    
    // Highlight active subscription tab
    document.querySelectorAll('.subs-btn').forEach(b => {
        b.classList.remove('btn-primary');
        b.classList.add('btn-outline');
    });
    const activeSub = document.getElementById('sub-' + (d.auto_order_freq || 'NONE'));
    if(activeSub) {
        activeSub.classList.remove('btn-outline');
        activeSub.classList.add('btn-primary');
    }
}

// ── Activity ─────────────────────────────────────────────────
async function loadActivity() {
    const el = document.getElementById('recentActivity');
    const d = await apiCall('../api/orders.php', 'get_orders', { type: 'ongoing' });
    if (!d.success || !d.orders?.length) {
        el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem 0;">No recent activity. <a href="javascript:void(0)" onclick="switchTab(\'order\',document.getElementById(\'nav-order\'))">Place your first order →</a></p>';
        return;
    }
    const statusIcon = { pending:'🕐', picked_up:'🛍️', in_process:'🧺', out_for_delivery:'🚚', delivered:'✅', cancelled:'❌' };
    el.innerHTML = d.orders.slice(0,5).map(o => `
        <div class="activity-item">
            <div class="act-dot"><i class="material-icons-outlined">receipt</i></div>
            <div style="flex:1">
                <div class="act-text">Order #${o.id} — ${statusIcon[o.status]||''} ${o.status.replace(/_/g,' ').toUpperCase()}</div>
                <div class="act-sub">₹${o.total_amount} · ${new Date(o.created_at).toLocaleDateString('en-IN',{day:'2-digit',month:'short'})}</div>
            </div>
            ${statusBadge(o.status)}
        </div>
    `).join('');
}

function statusBadge(s) {
    const m = { pending:'b-amber', picked_up:'b-blue', in_process:'b-blue', out_for_delivery:'b-purple', delivered:'b-green', cancelled:'b-red' };
    return `<span class="badge ${m[s]||'b-gray'}">${s.replace(/_/g,' ')}</span>`;
}

// ── Product Catalog ───────────────────────────────────────────
async function loadProductCatalog() {
    const grid = document.getElementById('productGrid');
    const status = document.getElementById('catalogStatus');
    if (!grid) return;
    if (grid.children.length > 0) { status.style.display='none'; return; } // cached
    try {
        const d = await apiCall('../api/products.php', 'get_products', { active_only: true });
        status.style.display = 'none';
        if (!d.success || !d.products?.length) {
            grid.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:2rem;">No services available yet. Check back soon!</p>';
            return;
        }
        grid.innerHTML = d.products.map(p => {
            const imgHtml = p.image_url
                ? `<img src="../${p.image_url}" alt="${p.name}">`
                : `<i class="material-icons-outlined">local_laundry_service</i>`;
            const chips = p.prices.map(pp => `
                <div class="pc" data-ppid="${pp.id}" data-price="${pp.price}" data-prod="${p.id}" data-pname="${p.name.replace(/"/g,'&quot;')}" data-size="${pp.size_label}"
                     onclick="selectPrice(this,${pp.id},'${p.name.replace(/'/g,'')}','${pp.size_label}',${pp.price},${p.id})">
                    ${pp.size_label} — ₹${pp.price}
                </div>
            `).join('');
            return `
                <div class="product-card" id="pc-${p.id}">
                    <div class="prod-img">${imgHtml}</div>
                    <div class="prod-body">
                        <div class="prod-name">${p.name}</div>
                        ${p.description ? `<div class="prod-desc">${p.description}</div>` : ''}
                        <div class="price-chips" id="chips-${p.id}">${chips}</div>
                        <div class="qty-row" id="qty-${p.id}" style="display:none">
                            <button type="button" class="qbtn" onclick="changeQty(${p.id},-1)">−</button>
                            <span class="qval" id="qv-${p.id}">1</span>
                            <button type="button" class="qbtn" onclick="changeQty(${p.id},1)">+</button>
                            <button type="button" class="rm-btn" onclick="removeFromCart(${p.id})">Remove</button>
                        </div>
                    </div>
                </div>`;
        }).join('');
    } catch(e) {
        status.textContent = 'Failed to load. Refresh page.';
    }
}

function selectPrice(chip, ppId, pName, sizeLabel, price, productId) {
    document.querySelectorAll(`#chips-${productId} .pc`).forEach(c => c.classList.remove('sel'));
    chip.classList.add('sel');
    cart[productId] = { product_price_id: ppId, product_name: pName, size_label: sizeLabel, price: parseFloat(price), quantity: parseInt(document.getElementById('qv-'+productId)?.textContent||1) };
    document.getElementById('qty-'+productId).style.display = 'flex';
    document.getElementById('pc-'+productId).classList.add('has-item');
    updateCart();
}

function changeQty(productId, delta) {
    if (!cart[productId]) return;
    const nq = Math.max(1, cart[productId].quantity + delta);
    cart[productId].quantity = nq;
    document.getElementById('qv-'+productId).textContent = nq;
    updateCart();
}

function removeFromCart(productId) {
    delete cart[productId];
    document.getElementById('qv-'+productId).textContent = 1;
    document.getElementById('qty-'+productId).style.display = 'none';
    document.getElementById('pc-'+productId).classList.remove('has-item');
    document.querySelectorAll(`#chips-${productId} .pc`).forEach(c => c.classList.remove('sel'));
    updateCart();
}

function updateCart() {
    const items = Object.values(cart);
    const wrap = document.getElementById('cartWrap');
    const btn = document.getElementById('submitOrderBtn');
    if (!items.length) { wrap.style.display='none'; btn.disabled=true; return; }
    wrap.style.display = 'block';
    btn.disabled = false;
    let sub = 0;
    let html = items.map(it => {
        const line = it.price * it.quantity;
        sub += line;
        return `<div class="cart-row"><span>${it.product_name} (${it.size_label}) × ${it.quantity}</span><span style="font-weight:700;">₹${line.toFixed(2)}</span></div>`;
    }).join('');
    const total = Math.max(0, sub - appliedDiscount);
    if (appliedDiscount > 0) html += `<div class="cart-row" style="color:var(--success)"><span>Coupon Discount</span><span>−₹${appliedDiscount.toFixed(2)}</span></div>`;
    document.getElementById('cartLines').innerHTML = html;
    document.getElementById('cartGrand').textContent = '₹' + total.toFixed(2);
}

// ── Coupon ────────────────────────────────────────────────────
document.getElementById('applyCouponBtn')?.addEventListener('click', async () => {
    const code  = document.getElementById('couponCode').value.trim();
    const items = Object.values(cart);
    const sub   = items.reduce((s, it) => s + it.price * it.quantity, 0);
    const fb    = document.getElementById('couponFeedback');
    if (!code) { fb.textContent='Enter a coupon code.'; fb.style.color='var(--danger)'; return; }
    fb.textContent='Checking…'; fb.style.color='var(--muted)';
    const d = await apiCall('../api/orders.php','validate_coupon',{ coupon_code: code, order_amount: sub });
    if (d.success) {
        appliedDiscount = parseFloat(d.discount_amount||0);
        fb.textContent = `✓ Applied — You save ₹${appliedDiscount.toFixed(2)}!`;
        fb.style.color = 'var(--success)';
        updateCart();
    } else {
        appliedDiscount = 0;
        fb.textContent = d.message; fb.style.color = 'var(--danger)';
        updateCart();
    }
});

// ── Submit Order ──────────────────────────────────────────────
document.getElementById('submitOrderBtn')?.addEventListener('click', async () => {
    const pMode = document.getElementById('paymentMode').value;
    
    if (pMode.startsWith('PAY_LATER')) {
        const limit = parseInt(userPayLaterPlan.replace('PAY_LATER_', '')) || 4;
        if (unpaidPayLaterCount >= limit) {
            document.querySelector('#limitModal .modal-sub').textContent = `You have reached your limit of ${limit} unpaid Pay Later orders. Clear dues to use Pay Later, or select Online/COD.`;
            document.getElementById('limitModalDues').textContent = document.getElementById('sDues').textContent;
            openModal('limitModal');
            return;
        }
    } else if (pMode === 'COD') {
        if (unpaidCodCount >= 4) {
            document.querySelector('#limitModal .modal-sub').textContent = `You have 4 unpaid Cash on Delivery orders. Clear dues to use COD, or select Online Payment.`;
            document.getElementById('limitModalDues').textContent = document.getElementById('sDues').textContent;
            openModal('limitModal');
            return;
        }
    }

    const items = Object.values(cart);
    if (!items.length) { toast('error','Empty Cart','Select at least one service.'); return; }
    const btn = document.getElementById('submitOrderBtn');
    btn.innerHTML = 'Placing…'; btn.disabled = true;

    const payload = {
        items: items.map(it => ({ product_price_id: it.product_price_id, quantity: it.quantity })),
        instructions: document.getElementById('orderInstr').value,
        coupon_code: document.getElementById('couponCode').value,
        payment_mode: document.getElementById('paymentMode').value
    };

    const processOrderSuccess = (d) => {
        toast('success','Order Placed! 🎉', d.message);
        cart = {}; appliedDiscount = 0;
        document.querySelectorAll('.pc').forEach(c => c.classList.remove('sel'));
        document.querySelectorAll('.product-card').forEach(c => c.classList.remove('has-item'));
        document.querySelectorAll('[id^="qty-"]').forEach(c => c.style.display='none');
        document.querySelectorAll('[id^="qv-"]').forEach(c => c.textContent='1');
        document.getElementById('cartWrap').style.display='none';
        document.getElementById('couponCode').value='';
        document.getElementById('couponFeedback').textContent='';
        document.getElementById('orderInstr').value='';
        btn.innerHTML='<i class="material-icons-outlined" style="font-size:1rem;">local_shipping</i> Request Pickup';
        btn.disabled = false;
        fetchStats();
        switchTab('history', document.getElementById('nav-history'));
    };

    if (payload.payment_mode === 'ONLINE') {
        const amt = parseFloat(document.getElementById('cartGrand').textContent.replace('₹',''));
        const initRes = await apiCall('../api/payments.php', 'create_rzp_precheckout_order', { amount: amt });
        if (!initRes.success) {
            btn.innerHTML='<i class="material-icons-outlined" style="font-size:1rem;">local_shipping</i> Request Pickup';
            btn.disabled = false;
            return toast('error', 'Gateway Error', initRes.message);
        }
        
        const rzpOpts = {
            key: initRes.key,
            amount: initRes.amount,
            order_id: initRes.rzp_order_id,
            name: 'DigiWash Laundry',
            description: 'Online Payment pre-checkout',
            handler: async (res) => {
                btn.innerHTML = 'Verifying...';
                payload.razorpay_payment_id = res.razorpay_payment_id;
                payload.razorpay_order_id = res.razorpay_order_id;
                payload.razorpay_signature = res.razorpay_signature;
                
                const d = await apiCall('../api/orders.php','create_order', payload);
                if (d.success) processOrderSuccess(d);
                else { toast('error','Transaction Verified, Order Failed', d.message); btn.disabled=false; btn.innerHTML='<i class="material-icons-outlined" style="font-size:1rem;">local_shipping</i> Request Pickup'; }
            },
            prefill: { name:'<?= $userName ?>', contact:'<?= $userPhone ?>' },
            theme: { color:'#6366f1' }
        };
        const rzp = new Razorpay(rzpOpts);
        rzp.on('payment.failed', function (r){
            btn.innerHTML='<i class="material-icons-outlined" style="font-size:1rem;">local_shipping</i> Request Pickup';
            btn.disabled=false;
            toast('error', 'Payment Cancelled', 'Please try again or use another payment method.');
        });
        rzp.open();
        return;
    }

    const d = await apiCall('../api/orders.php','create_order', payload);
    if (d.success) processOrderSuccess(d);
    else {
        toast('error','Order Failed', d.message);
        btn.innerHTML='<i class="material-icons-outlined" style="font-size:1rem;">local_shipping</i> Request Pickup';
        btn.disabled = false;
    }
});

// ── Orders List ───────────────────────────────────────────────
async function loadOrders(type, tabEl) {
    currentOrderTab = type;
    if (tabEl) {
        document.querySelectorAll('.tab-btn[id^="tab-"]').forEach(t => t.classList.remove('active'));
        tabEl.classList.add('active');
    }
    const el = document.getElementById('ordersList');
    el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem;">Loading…</p>';
    const d = await apiCall('../api/orders.php','get_orders',{ type });
    if (!d.success || !d.orders?.length) {
        el.innerHTML = `<div class="card" style="text-align:center;padding:3rem;"><i class="material-icons-outlined" style="font-size:3rem;color:#cbd5e1;">receipt</i><p style="margin-top:1rem;color:var(--muted);">No ${type} orders found.</p></div>`;
        return;
    }
    const steps = ['pending','picked_up','in_process','out_for_delivery','delivered'];
    el.innerHTML = d.orders.map(o => {
        const stepIdx = steps.indexOf(o.status);
        const timeline = o.status !== 'cancelled' ? `
            <div class="timeline">${steps.map((s,i) => `
                <div class="tl-step ${i < stepIdx ? 'done' : i === stepIdx ? 'current' : ''}">
                    <div class="tl-dot">${i < stepIdx ? '✓' : i+1}</div>
                    <div class="tl-lbl">${s.replace(/_/g,' ')}</div>
                </div>`).join('')}
            </div>` : '';

        let devInfo = '';
        if (o.delivery_guy_name) {
            let statusText = 'Assigned for task';
            if (['picked_up', 'in_process', 'out_for_delivery'].includes(o.status)) {
                statusText = `Picked up: ${o.picked_up_at ? new Date(o.picked_up_at).toLocaleString('en-IN', {hour:'2-digit',minute:'2-digit'}) : 'Processing...'}`;
            } else if (o.status === 'delivered') {
                statusText = `Delivered: ${o.delivered_at ? new Date(o.delivered_at).toLocaleString('en-IN', {hour:'2-digit',minute:'2-digit',day:'2-digit',month:'short',year:'numeric'}) : 'Completed'}`;
            }
            
            let phoneStr = ['out_for_delivery', 'delivered'].includes(o.status) ? `📞 ${o.delivery_guy_phone || 'N/A'}` : `📞 [Hidden pending dispatch]`;
            
            let bypassUi = '';
            if (o.status === 'delivered' && o.bypass_photo_url) {
                bypassUi = `
                    <div style="margin-top:8px; padding-top:8px; border-top:1.5px dashed #bbf7d0;">
                        <div style="font-size:0.75rem; font-weight:800; color:#d97706; margin-bottom:4px;"><i class="material-icons-outlined" style="font-size:1rem; vertical-align:middle;">warning</i> Delivered via Staff Bypass</div>
                        <a href="../${o.bypass_photo_url}" target="_blank" style="font-size:0.8rem; color:#2563eb; font-weight:700; text-decoration:none;"><i class="material-icons-outlined" style="font-size:1rem; vertical-align:middle;">photo_camera</i> View Proof Photo</a>
                    </div>
                `;
            }

            devInfo = `
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:10px; padding:0.8rem; margin-top:0.75rem; display:flex; gap:12px;">
                    <div style="width:36px; height:36px; border-radius:10px; background:#22c55e; color:white; font-weight:800; display:flex; align-items:center; justify-content:center; font-size:1.1rem; flex-shrink:0;">
                        ${o.delivery_guy_name.substring(0,1).toUpperCase()}
                    </div>
                    <div style="flex:1;">
                        <div style="font-size:0.85rem; font-weight:800; color:#166534;">${o.delivery_guy_name}</div>
                        <div style="font-size:0.75rem; color:#15803d; margin-top:3px; font-weight:600;">${phoneStr} <span style="margin:0 4px">•</span> ${statusText}</div>
                        ${bypassUi}
                    </div>
                </div>
            `;
        }

        return `
            <div class="order-row">
                <div class="order-row-top">
                    <div>
                        <div class="order-id">Order #${o.id} <span style="font-size:0.7rem;color:var(--muted);font-weight:600;background:#f1f5f9;padding:2px 6px;border-radius:4px;margin-left:5px;">💳 ${o.payment_mode ? o.payment_mode.replace(/_/g,' ') : 'Unknown'}</span></div>
                        <div class="order-meta">₹${o.total_amount} · ${new Date(o.created_at).toLocaleString('en-IN',{day:'2-digit',month:'short',hour:'2-digit',minute:'2-digit'})}</div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                        <a href="../api/invoice.php?action=download_order_pdf&order_id=${o.id}" target="_blank" class="btn btn-sm btn-ghost" style="border:1px solid #cbd5e1;"><i class="material-icons-outlined" style="font-size:.9rem;margin-right:4px;">receipt_long</i> Invoice</a>
                        ${statusBadge(o.status)}
                        ${o.status === 'delivered' ? `<button class="btn btn-sm btn-danger" onclick="openReturnModal(${o.id})">↩ Return</button>` : ''}
                        ${['pending','assigned'].includes(o.status) ? `<button class="btn btn-sm btn-outline" style="border-color:var(--danger);color:var(--danger);gap:4px;" onclick="cancelOrder(${o.id})"><i class="material-icons-outlined" style="font-size:.9rem;">cancel</i> Cancel Order</button>` : ''}
                    </div>
                </div>
                ${timeline}
                ${devInfo}
                ${o.items && o.items.length ? `
                    <div style="background:#f8fafc;padding:0.7rem 0.85rem;border-radius:8px;margin-top:0.75rem;font-size:0.8rem;color:#475569;border:1px solid var(--border);">
                        <strong>Items:</strong> ${o.items.map(it => `${it.product_name} (${it.size_label}) × ${it.quantity}`).join(', ')}
                    </div>
                ` : ''}
            </div>`;
    }).join('');
}

async function cancelOrder(orderId) {
    if(!confirm("Are you sure you want to cancel this order?")) return;
    const d = await apiCall('../api/orders.php', 'cancel_order', { order_id: orderId });
    toast(d.success?'success':'error', 'Order Cancellation', d.message);
    if(d.success) {
        fetchStats();
        loadActivity();
        loadOrders(currentOrderTab);
    }
}

// ── Payments ─────────────────────────────────────────────────
async function loadPayments(type, tabEl) {
    if (tabEl) {
        document.querySelectorAll('.tab-btn[id^="ptab-"]').forEach(t => t.classList.remove('active'));
        tabEl.classList.add('active');
    }
    const el = document.getElementById('paymentsList');
    el.innerHTML = '<p style="color:var(--muted);text-align:center;padding:2rem;">Loading…</p>';
    
    // Concurrently fetch standard checkout orders and admin generated custom invoices
    const [d, invRes] = await Promise.all([
        apiCall('../api/orders.php','get_payments',{ type }),
        apiCall('../api/invoice.php','get_invoices',{})
    ]);

    const myPayments = (d.success && d.payments) ? d.payments.filter(p => !p.invoice_id) : [];
    const myInvoices = (invRes.success && invRes.invoices) ? invRes.invoices.filter(i => (type==='remaining'?i.status==='unpaid':i.status==='paid')) : [];

    if (myPayments.length === 0 && myInvoices.length === 0) {
        el.innerHTML = `<div class="card" style="text-align:center;padding:3rem;"><i class="material-icons-outlined" style="font-size:3rem;color:#cbd5e1;">check_circle</i><p style="margin-top:1rem;color:var(--muted);">No ${type === 'remaining' ? 'pending dues' : 'payment records'} found.</p></div>`;
        return;
    }

    let html = '';

    // 1. Custom Invoices Top Block
    if (myInvoices.length > 0) {
        html += `<div style="font-weight:800;font-size:0.9rem;margin-bottom:10px;text-transform:uppercase;color:var(--muted);letter-spacing:1px;margin-top:5px;">Combined Billing Invoices</div>`;
        html += myInvoices.map(i => {
            const isPayable = (i.orders||[]).every(o => ['delivered', 'cancelled'].includes(o.status));
            return `
            <div class="due-card" style="border-left:4px solid #f59e0b;flex-direction:column;align-items:stretch;">
                <div style="display:flex;justify-content:space-between;align-items:center;width:100%;">
                    <div>
                        <div class="due-info">${i.invoice_no}</div>
                        <div style="font-size:.78rem;color:var(--muted);">${i.description} · ${new Date(i.created_at).toLocaleDateString('en-IN')}</div>
                    </div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                        <div class="due-amount" style="color:#b45309;">₹${parseFloat(i.amount).toFixed(2)}</div>
                        <a href="../api/invoice.php?action=download_pdf&id=${i.id}" target="_blank" class="btn btn-sm btn-outline" style="padding:2px 8px;"><i class="material-icons-outlined" style="font-size:1.1rem;margin-right:2px;">picture_as_pdf</i> PDF</a>
                        ${type === 'remaining' ? (isPayable ? `<button class="btn btn-sm btn-primary" style="background:#f59e0b;border-color:#f59e0b;padding:2px 10px;" onclick="payInvoice(${i.id})">Pay</button>` : `<span class="badge" style="background:#64748b;font-size:.65rem;color:#fff;">Wait for Delivery</span>`) : `<span class="badge b-green">Paid</span>`}
                        <button class="btn btn-sm btn-ghost" style="padding:4px;" onclick="const e=document.getElementById('inv-ords-${i.id}'); e.style.display=e.style.display==='none'?'block':'none'"><i class="material-icons-outlined" style="font-size:1.2rem;">expand_more</i></button>
                    </div>
                </div>
                <div id="inv-ords-${i.id}" style="display:none;margin-top:12px;padding-top:12px;border-top:1px dashed #cbd5e1;font-size:0.8rem;">
                    <div style="font-weight:700;margin-bottom:8px;color:#475569;">Orders Included:</div>
                    ${(i.orders||[]).map(o => `
                        <div style="display:flex;justify-content:space-between;background:#f8fafc;padding:6px 10px;border-radius:6px;margin-bottom:6px;border:1px solid #e2e8f0;">
                            <div><b>Order #${o.id}</b> <span style="color:#94a3b8;margin-left:6px;">${new Date(o.created_at).toLocaleDateString()}</span></div>
                            <div style="display:flex;gap:10px;">
                                <span style="color:#10b981;font-weight:800;">₹${parseFloat(o.amount||0).toFixed(2)}</span>
                                <span class="badge" style="background:#cbd5e1;color:#334155;font-size:.65rem;padding:2px 6px;">${o.status.replace(/_/g,' ').toUpperCase()}</span>
                            </div>
                        </div>
                    `).join('')}
                </div>
            </div>
        `;}).join('');
    }

    // 2. Main Order Balances
    if (type === 'remaining' && myPayments.length > 0) {
        const plPayments = myPayments.filter(p => p.payment_mode.startsWith('PAY_LATER'));
        if (plPayments.length > 0) {
            const hasInTransit = plPayments.some(p => p.order_status !== 'delivered' && p.order_status !== 'cancelled');
            const payLaterTotal = plPayments.reduce((s, p) => s + parseFloat(p.amount), 0);
            const bulkBtn = hasInTransit
                ? `<button class="btn" style="background:#e2e8f0; color:#94a3b8; padding:.8rem 1.5rem; font-weight:800; font-size:1rem; cursor:not-allowed;" disabled title="All Pay Later orders must be delivered first">Delivery Pending</button>`
                : `<button class="btn" style="background:white; color:var(--primary); padding:.8rem 1.5rem; font-weight:800; font-size:1rem;" onclick="initiateBulkPayment()">Pay All Now</button>`;
                
            html += `
            <div style="background:linear-gradient(135deg,var(--primary),var(--primary-d)); border-radius:14px; padding:1.5rem; color:white; margin-bottom:1.5rem; margin-top:1.5rem; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; box-shadow:0 8px 20px rgba(99,102,241,0.25);">
                <div>
                    <div style="font-size:0.85rem; font-weight:700; opacity:0.9; text-transform:uppercase; letter-spacing:1px;">Pay Later Credit Due</div>
                    <div style="font-size:2.2rem; font-weight:900; margin-top:4px;">₹${payLaterTotal.toFixed(2)}</div>
                    <div style="font-size:0.8rem; margin-top:2px; opacity:0.8;">${plPayments.length} unpaid Pay Later orders in queue</div>
                </div>
                ${bulkBtn}
            </div>
            `;
        }
    }

    if (myPayments.length > 0) {
        html += `<div style="font-weight:800;font-size:0.9rem;margin-bottom:10px;text-transform:uppercase;color:var(--muted);letter-spacing:1px;margin-top:1rem;">Order Dues</div>`;
        const plPaymentsRef = myPayments.filter(p => p.payment_mode.startsWith('PAY_LATER'));
        const anyInTransit = plPaymentsRef.some(p => p.order_status !== 'delivered' && p.order_status !== 'cancelled');

        html += myPayments.map(p => {
            if (type === 'remaining') {
                const isDirectOnline = p.payment_mode === 'ONLINE';
                if (isDirectOnline || ['delivered', 'cancelled'].includes(p.order_status)) {
                    btnHtml = `<button class="btn btn-sm btn-primary" onclick="initiatePayment(${p.order_id},${p.amount})">Pay</button>`;
                } else {
                    btnHtml = `<button class="btn btn-sm" style="background:#e2e8f0;color:#94a3b8;cursor:not-allowed;" disabled>Wait for Delivery</button>`;
                }
            } else {
                btnHtml = `<span class="badge b-green">Paid</span>`;
            }
            
            return `
            <div class="due-card">
                <div>
                    <div class="due-info">Order #${p.order_id} · ${p.payment_mode.replace(/_/g,' ')}</div>
                    <div style="font-size:.78rem;color:var(--muted);">${new Date(p.created_at).toLocaleDateString('en-IN')}</div>
                </div>
                <div style="display:flex;align-items:center;gap:1rem;">
                    <div class="due-amount">₹${parseFloat(p.amount).toFixed(2)}</div>
                    ${btnHtml}
                </div>
            </div>
            `;
        }).join('');
    }

    el.innerHTML = html;
}

async function initiateBulkPayment() {
    try {
        const initRes = await apiCall('../api/payments.php','create_bulk_rzp_order',{});
        if (!initRes.success) { toast('error','Payment Error',initRes.message); return; }
        const opts = {
            key: initRes.key,
            amount: initRes.amount,
            currency: 'INR',
            name: 'DigiWash',
            description: 'Bulk Pay Later Settlement',
            order_id: initRes.rzp_order_id,
            handler: async (res) => {
                const vd = await apiCall('../api/payments.php','verify_payment',{ razorpay_payment_id:res.razorpay_payment_id, razorpay_order_id:res.razorpay_order_id, razorpay_signature:res.razorpay_signature, local_order_id:'BULK' });
                toast(vd.success?'success':'error', vd.success?'Payment Successful':'Verification Failed', vd.message);
                if (vd.success) { fetchStats(); loadPayments('remaining'); }
            },
            prefill: { name:'<?= $userName ?>', contact:'<?= $userPhone ?>' },
            theme: { color:'#6366f1' }
        };
        new Razorpay(opts).open();
    } catch(e) { toast('error','Payment Error','Could not initiate payment.'); }
}

// ── Razorpay: Invoice ──────────────────────────────────────────
async function payInvoice(invId) {
    try {
        const init = await apiCall('../api/invoice.php','initiate_payment',{invoice_id:invId});
        if(!init.success) { toast('error','Error',init.message); return; }
        const opts = {
            key: init.key,
            amount: init.amount,
            currency: 'INR',
            name: 'DigiWash Invoice',
            description: 'Custom Billing',
            order_id: init.rzp_order_id,
            handler: async (res) => {
                const vd = await apiCall('../api/invoice.php','verify_payment',{ 
                    invoice_id: invId,
                    razorpay_payment_id: res.razorpay_payment_id, 
                    razorpay_order_id: res.razorpay_order_id, 
                    razorpay_signature: res.razorpay_signature 
                });
                toast(vd.success?'success':'error', vd.success?'Invoice Paid!':'Verification Failed', vd.message);
                if(vd.success) { fetchStats(); loadPayments('remaining'); }
            },
            prefill: { name:'<?= $userName ?>', contact:'<?= $userPhone ?>' },
            theme: { color:'#f59e0b' }
        };
        new Razorpay(opts).open();
    } catch(e) { toast('error','Error','Could not launch gateway.'); }
}

// ── Razorpay: Order ──────────────────────────────────────────
async function initiatePayment(orderId, amount) {
    try {
        const initRes = await apiCall('../api/payments.php','create_rzp_order',{ order_id:orderId });
        if (!initRes.success) { toast('error','Payment Error',initRes.message); return; }
        const opts = {
            key: initRes.key,
            amount: initRes.amount,
            currency: 'INR',
            name: 'DigiWash',
            description: 'Order #' + orderId,
            order_id: initRes.rzp_order_id,
            handler: async (res) => {
                const vd = await apiCall('../api/payments.php','verify_payment',{ razorpay_payment_id:res.razorpay_payment_id, razorpay_order_id:res.razorpay_order_id, razorpay_signature:res.razorpay_signature, local_order_id:orderId });
                toast(vd.success?'success':'error', vd.success?'Payment Successful':'Verification Failed', vd.message);
                if (vd.success) { fetchStats(); loadPayments('remaining'); }
            },
            prefill: { name:'<?= $userName ?>', contact:'<?= $userPhone ?>' },
            theme: { color:'#6366f1' }
        };
        new Razorpay(opts).open();
    } catch(e) { toast('error','Payment Error','Could not initiate payment.'); }
}

// ── Profile form & Location ───────────────────────────────────
function detectLocation() {
    const locStatus = document.getElementById('locStatus');
    const latInp = document.getElementById('p_lat');
    const lngInp = document.getElementById('p_lng');
    const marketSel = document.getElementById('p_market');

    if (!navigator.geolocation) {
        locStatus.textContent = "Geolocation is not supported by your browser.";
        locStatus.style.color = "var(--danger)";
        return;
    }

    locStatus.textContent = "Locating… Please allow access.";
    locStatus.style.color = "var(--primary)";

    navigator.geolocation.getCurrentPosition(async (position) => {
        const lat = position.coords.latitude;
        const lng = position.coords.longitude;
        latInp.value = lat;
        lngInp.value = lng;
        locStatus.textContent = "Location attached successfully! (" + lat.toFixed(4) + ", " + lng.toFixed(4) + ")";
        locStatus.style.color = "var(--success)";

    }, (err) => {
        let errStr = err.message;
        if(err.code === err.PERMISSION_DENIED) errStr = "Permission denied. Please enable location services.";
        locStatus.textContent = "Failed to get location: " + errStr;
        locStatus.style.color = "var(--danger)";
    }, {
        enableHighAccuracy: true,
        timeout: 15000,
        maximumAge: 0
    });
}

document.getElementById('profileForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const msg = document.getElementById('profileMsg');
    btn.innerHTML = 'Saving…'; btn.disabled = true;
    const d = await apiCall('../api/user.php','update_profile',{
        name: document.getElementById('p_name').value,
        email: document.getElementById('p_email').value,
        shop_address: document.getElementById('p_address').value,
        alt_contact: document.getElementById('p_alt').value,
        market_id: document.getElementById('p_market').value,
        lat: document.getElementById('p_lat').value,
        lng: document.getElementById('p_lng').value
    });
    msg.textContent = d.message; msg.style.display='block';
    msg.style.color = d.success?'var(--success)':'var(--danger)';
    btn.innerHTML = 'Save Details'; btn.disabled = false;
    if (d.success) { toast('success','Profile Saved',''); setTimeout(()=>location.reload(), 1500); }
});

// ── Return modal ──────────────────────────────────────────────
function openReturnModal(orderId) {
    document.getElementById('returnOrderId').value = orderId;
    document.getElementById('returnReason').value = '';
    document.getElementById('returnPhoto').value = '';
    document.getElementById('returnMsg').style.display = 'none';
    openModal('returnModal');
}

async function submitReturn() {
    const orderId = document.getElementById('returnOrderId').value;
    const reason  = document.getElementById('returnReason').value;
    const photo   = document.getElementById('returnPhoto').files[0];
    const btn = document.getElementById('btnSubmitReturn');
    const msg = document.getElementById('returnMsg');
    if (!reason || !photo) { msg.textContent='Reason and photo are required.'; msg.style.display='block'; return; }
    btn.textContent='Uploading…'; btn.disabled=true; msg.style.display='none';
    const fd = new FormData();
    fd.append('action','request_return'); fd.append('order_id',orderId);
    fd.append('reason',reason); fd.append('return_photo',photo);
    try {
        const r = await fetch('../api/orders.php',{ method:'POST', headers:{'X-CSRF-Token':csrfToken}, body:fd });
        const d = await r.json();
        if (d.success) { toast('success','Return Submitted',d.message); closeModal('returnModal'); }
        else { msg.textContent=d.message; msg.style.display='block'; }
    } catch { msg.textContent='Upload failed.'; msg.style.display='block'; }
    btn.textContent='Submit Return'; btn.disabled=false;
}

// ── Logout ───────────────────────────────────────────────────
document.getElementById('logoutBtn')?.addEventListener('click', async () => {
    await fetch('../api/auth.php',{ method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken}, body:JSON.stringify({action:'logout'}) });
    location.href = '../index.php';
});

// ── API helper ────────────────────────────────────────────────
async function apiCall(url, action, payload = {}) {
    try {
        const r = await fetch(url, { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken}, body:JSON.stringify({action,...payload}) });
        if (!r.ok) return { success:false, message:`Server error (HTTP ${r.status})` };
        return await r.json();
    } catch(e) { console.error('apiCall error:', e); return { success:false, message:'Network error. Please check your connection.' }; }
}

// ── Auto Order ────────────────────────────────────────────────
async function saveAutoOrder(freq) {
    const d = await apiCall('../api/orders.php', 'save_auto_order', { frequency: freq });
    toast(d.success?'success':'error', 'Auto Order', d.message);
    if(d.success) fetchStats();
}

// ── Pay Later Request ─────────────────────────────────────────
async function requestPayLater() {
    const d = await apiCall('../api/orders.php','request_pay_later_plan', {});
    toast(d.success?'success':'error', 'Pay Later Request', d.message);
    if(d.success) setTimeout(()=>location.reload(), 1500);
}

// ══════════════════════════════════════════════════════════════
// ── FIREBASE CLOUD MESSAGING (Push Notifications) ────────────
// ══════════════════════════════════════════════════════════════
const firebaseConfig = <?= getFirebaseConfigJs() ?>;
firebase.initializeApp(firebaseConfig);
let messaging = null;

async function initFCM() {
    try {
        // Check browser support
        if (!('Notification' in window) || !('serviceWorker' in navigator)) {
            console.log('FCM: Browser does not support notifications');
            return;
        }

        // Register service worker
        const swReg = await navigator.serviceWorker.register('../firebase-messaging-sw.js');
        messaging = firebase.messaging();

        // Request permission
        const permission = await Notification.requestPermission();
        if (permission !== 'granted') {
            console.log('FCM: Notification permission denied');
            return;
        }

        // Get FCM token (VAPID key from Firebase Console)
        const vapidKey = '<?= getenv("FIREBASE_VAPID_KEY") ?: "" ?>';
        const tokenOptions = { serviceWorkerRegistration: swReg };
        if (vapidKey) tokenOptions.vapidKey = vapidKey;
        
        const fcmToken = await messaging.getToken(tokenOptions);
        if (fcmToken) {
            console.log('FCM: Token obtained');
            // Save to backend
            await apiCall('../api/user.php', 'save_fcm_token', { fcm_token: fcmToken });
        }

        // Handle foreground messages
        messaging.onMessage((payload) => {
            console.log('FCM: Foreground message', payload);
            const title = payload.notification?.title || 'DigiWash';
            const body = payload.notification?.body || '';
            
            // Show toast
            toast('info', title, body, 6000);
            
            // Refresh notification bell
            loadNotifications();
            
            // Refresh relevant data
            fetchStats();
            loadActivity();
        });

    } catch(err) {
        console.error('FCM: Init error', err);
    }
}

// ══════════════════════════════════════════════════════════════
// ── NOTIFICATION BELL ────────────────────────────────────────
// ══════════════════════════════════════════════════════════════
async function loadNotifications() {
    try {
        const d = await apiCall('../api/orders.php', 'get_notifications', {});
        const badge = document.getElementById('notifBadge');
        const list = document.getElementById('notifList');
        
        if (!d.success) {
            list.innerHTML = '<div style="padding:2rem 1rem;text-align:center;color:var(--muted);font-size:.85rem;">Could not load alerts.</div>';
            return;
        }
        
        // Update badge
        const unread = parseInt(d.unread || 0);
        if (unread > 0) {
            badge.textContent = unread > 99 ? '99+' : unread;
            badge.style.display = 'inline';
        } else {
            badge.style.display = 'none';
        }
        
        // Render list
        const notifs = d.notifications || [];
        if (notifs.length === 0) {
            list.innerHTML = '<div style="padding:2rem 1rem;text-align:center;color:var(--muted);font-size:.85rem;">No notifications yet.</div>';
            return;
        }
        
        list.innerHTML = notifs.map(n => {
            const time = new Date(n.created_at);
            const ago = getTimeAgo(time);
            return `
                <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}">
                    <div class="notif-item-title">${escHtml(n.title)}</div>
                    <div class="notif-item-msg">${escHtml(n.message)}</div>
                    <div class="notif-item-time">${ago}</div>
                </div>
            `;
        }).join('');
    } catch(e) {
        console.error('Notifications load error', e);
    }
}

async function markNotifsRead() {
    await apiCall('../api/orders.php', 'mark_notifications_read', {});
    loadNotifications();
}

function getTimeAgo(date) {
    const now = new Date();
    const diff = Math.floor((now - date) / 1000);
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
    return date.toLocaleDateString('en-IN', { day:'2-digit', month:'short' });
}

function escHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fetchStats();
    loadActivity();
    renderQR();
    loadNotifications();
    initFCM();
    
    // Disable pay later options in dropdown if not approved
    Array.from(document.getElementById('paymentMode')?.options || []).forEach(opt => {
        if(opt.value.startsWith('PAY_LATER') && (userPayLaterPlan !== opt.value || userPayLaterStatus !== 'approved')) {
            opt.disabled = true;
        }
    });

    // Refresh notifications every 60 seconds
    setInterval(loadNotifications, 60000);

    // Initial load route based on Hash 
    let initialHash = window.location.hash.substring(1);
    if (!initialHash || !document.getElementById(initialHash)) initialHash = 'home';
    switchTab(initialHash, document.getElementById('nav-' + initialHash), true);
});
</script>
</body>
</html>