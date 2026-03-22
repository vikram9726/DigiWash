<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../index.php'); exit;
}
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$needsProfileSetup = empty($user['name']) || empty($user['shop_address']);
$qrCodeHash = $user['qr_code_hash'] ?? '';
$csrfToken  = $_SESSION['csrf_token'] ?? '';
$userName   = htmlspecialchars($user['name'] ?? 'User');
$userPhone  = htmlspecialchars($user['phone'] ?? '');
$payLaterPlan = $user['pay_later_plan'] ?? 'NONE';
$payLaterStatus = $user['pay_later_status'] ?? 'locked';
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
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#f0f2f8;
            --sidebar-bg:#0f172a;
            --card:white;
            --primary:#6366f1;
            --primary-d:#4f46e5;
            --success:#10b981;
            --danger:#ef4444;
            --amber:#f59e0b;
            --blue:#3b82f6;
            --text:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --sidebar-w:240px;
            --radius:16px;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}

        /* ── Layout ── */
        .app-wrap{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh;}

        /* ── Sidebar ── */
        .sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:1.5rem 1rem;gap:4px;position:sticky;top:0;height:100vh;overflow-y:auto;}
        .sidebar-brand{display:flex;align-items:center;gap:10px;color:white;font-weight:900;font-size:1.15rem;padding:0.5rem 0.75rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;}
        .sidebar-brand i{color:var(--success);font-size:1.8rem;}
        .user-chip{display:flex;align-items:center;gap:10px;padding:0.75rem;background:rgba(255,255,255,0.06);border-radius:12px;margin-bottom:1rem;}
        .user-av{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,var(--primary),var(--primary-d));display:flex;align-items:center;justify-content:center;color:white;font-weight:900;font-size:1rem;flex-shrink:0;}
        .user-info-name{color:white;font-weight:700;font-size:0.85rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
        .user-info-phone{color:#64748b;font-size:0.72rem;}
        .nav-section{font-size:0.7rem;font-weight:700;color:#475569;letter-spacing:.08em;text-transform:uppercase;padding:0.75rem 0.75rem 0.3rem;margin-top:0.5rem;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:0.7rem 1rem;border-radius:10px;color:#94a3b8;font-weight:600;font-size:0.875rem;cursor:pointer;transition:all 0.18s;}
        .nav-item:hover{background:rgba(255,255,255,0.06);color:white;}
        .nav-item.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;box-shadow:0 4px 12px rgba(99,102,241,0.35);}
        .nav-item i{font-size:1.2rem;flex-shrink:0;}
        .nav-badge{background:var(--danger);color:white;border-radius:999px;font-size:0.68rem;padding:1px 7px;margin-left:auto;font-weight:800;}

        /* ── Main ── */
        .main{padding:2rem;overflow-y:auto;}
        .section{display:none;}
        .section.active{display:block;animation:slideUp 0.3s ease;}
        @keyframes slideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

        /* ── Page header ── */
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;}
        .page-title{font-size:1.6rem;font-weight:900;color:var(--text);}
        .page-title span{color:var(--primary);}

        /* ── Cards ── */
        .card{background:var(--card);border-radius:var(--radius);padding:1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.06);}
        .card-sm{background:var(--card);border-radius:14px;padding:1.2rem;box-shadow:0 1px 4px rgba(0,0,0,0.06);}

        /* ── Stats ── */
        .stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:1rem;margin-bottom:1.75rem;}
        .stat-box{background:var(--card);border-radius:14px;padding:1.25rem 1.5rem;box-shadow:0 1px 4px rgba(0,0,0,0.06);border-left:4px solid var(--primary);display:flex;flex-direction:column;gap:4px;}
        .stat-box.green{border-left-color:var(--success);}
        .stat-box.red{border-left-color:var(--danger);}
        .stat-box.amber{border-left-color:var(--amber);}
        .stat-lbl{font-size:0.78rem;font-weight:600;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;}
        .stat-val{font-size:2rem;font-weight:900;color:var(--text);line-height:1;}
        .stat-sub{font-size:0.75rem;color:var(--muted);}

        /* ── Alert banner ── */
        .alert-banner{display:flex;align-items:center;gap:12px;background:#fef3c7;border:1.5px solid #fcd34d;border-radius:12px;padding:1rem 1.25rem;margin-bottom:1.5rem;}
        .alert-banner i{color:#d97706;font-size:1.4rem;}
        .alert-banner p{font-size:0.9rem;font-weight:600;color:#92400e;flex:1;}
        .alert-banner button{background:#d97706;color:white;border:none;border-radius:8px;padding:.4rem .9rem;font-size:0.82rem;font-weight:700;cursor:pointer;white-space:nowrap;}

        /* ── Btn ── */
        .btn{display:inline-flex;align-items:center;gap:7px;padding:.6rem 1.2rem;border-radius:10px;font-weight:700;font-size:0.9rem;cursor:pointer;border:none;transition:all .15s;}
        .btn:hover{filter:brightness(.92);transform:translateY(-1px);}
        .btn-primary{background:var(--primary);color:white;}
        .btn-success{background:var(--success);color:white;}
        .btn-danger{background:var(--danger);color:white;}
        .btn-ghost{background:#f1f5f9;color:var(--muted);}
        .btn-outline{background:white;color:var(--primary);border:1.5px solid var(--primary);}
        .btn-sm{padding:.35rem .75rem;font-size:0.8rem;border-radius:8px;}
        .btn[disabled]{opacity:.5;cursor:not-allowed;transform:none;}

        /* ── Form controls ── */
        .form-group{margin-bottom:1rem;}
        .form-group label{display:block;font-size:0.82rem;font-weight:700;color:#475569;margin-bottom:5px;}
        .form-control{width:100%;padding:.6rem .9rem;border:1.5px solid var(--border);border-radius:10px;font-size:.9rem;font-family:inherit;outline:none;transition:border-color .2s;background:white;}
        .form-control:focus{border-color:var(--primary);}

        /* ── Product catalog ── */
        .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:1rem;margin-bottom:1.5rem;}
        .product-card{border:2px solid var(--border);border-radius:14px;overflow:hidden;background:white;transition:all .2s;cursor:default;}
        .product-card:hover{border-color:var(--primary);box-shadow:0 4px 16px rgba(99,102,241,.12);}
        .product-card.has-item{border-color:var(--success);background:#f0fdf4;}
        .prod-img{width:100%;height:120px;object-fit:cover;background:#f8fafc;display:flex;align-items:center;justify-content:center;}
        .prod-img img{width:100%;height:100%;object-fit:cover;}
        .prod-img i{font-size:2.8rem;color:#cbd5e1;}
        .prod-body{padding:.85rem;}
        .prod-name{font-weight:800;font-size:.9rem;color:var(--text);margin-bottom:3px;}
        .prod-desc{font-size:.75rem;color:var(--muted);margin-bottom:8px;}
        .price-chips{display:flex;flex-wrap:wrap;gap:5px;}
        .pc{background:#f1f5f9;border:1.5px solid var(--border);border-radius:7px;padding:3px 8px;font-size:.74rem;font-weight:600;color:#475569;cursor:pointer;transition:all .15s;}
        .pc:hover{border-color:var(--primary);color:var(--primary);}
        .pc.sel{background:var(--primary);color:white;border-color:var(--primary);}
        .qty-row{display:flex;align-items:center;gap:8px;margin-top:7px;}
        .qbtn{width:26px;height:26px;border-radius:50%;border:1.5px solid var(--border);background:white;cursor:pointer;font-size:1rem;font-weight:700;display:flex;align-items:center;justify-content:center;transition:all .15s;}
        .qbtn:hover{border-color:var(--primary);color:var(--primary);}
        .qval{font-weight:800;min-width:22px;text-align:center;font-size:.95rem;}
        .rm-btn{margin-left:auto;background:#fee2e2;color:#dc2626;border:none;border-radius:7px;padding:2px 8px;font-size:.75rem;font-weight:700;cursor:pointer;}

        /* ── Cart ── */
        .cart-box{background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:1.2rem;}
        .cart-row{display:flex;justify-content:space-between;padding:5px 0;font-size:.875rem;border-bottom:1px solid var(--border);}
        .cart-row:last-of-type{border:none;}
        .cart-total{display:flex;justify-content:space-between;font-size:1rem;font-weight:900;padding-top:8px;border-top:2px solid var(--border);margin-top:6px;}

        /* ── Order status timeline ── */
        .timeline{display:flex;justify-content:space-between;align-items:center;position:relative;padding:0 8px;margin:1rem 0;}
        .timeline::before{content:'';position:absolute;top:14px;left:20px;right:20px;height:2px;background:var(--border);z-index:0;}
        .tl-step{display:flex;flex-direction:column;align-items:center;gap:5px;z-index:1;}
        .tl-dot{width:28px;height:28px;border-radius:50%;background:var(--border);display:flex;align-items:center;justify-content:center;font-size:13px;color:#94a3b8;border:2px solid white;transition:all .3s;}
        .tl-step.done .tl-dot{background:var(--success);color:white;}
        .tl-step.current .tl-dot{background:var(--primary);color:white;box-shadow:0 0 0 3px rgba(99,102,241,.25);}
        .tl-lbl{font-size:.68rem;color:var(--muted);font-weight:600;white-space:nowrap;}
        .tl-step.done .tl-lbl,.tl-step.current .tl-lbl{color:var(--text);font-weight:700;}

        /* ── Order history card ── */
        .order-row{border:1.5px solid var(--border);border-radius:12px;padding:1rem 1.2rem;margin-bottom:.75rem;background:white;transition:box-shadow .2s;}
        .order-row:hover{box-shadow:0 4px 12px rgba(0,0,0,.08);}
        .order-row-top{display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:.5rem;}
        .order-id{font-weight:800;font-size:1rem;}
        .order-meta{font-size:.8rem;color:var(--muted);margin-top:3px;}

        /* ── Status badges ── */
        .badge{display:inline-flex;align-items:center;padding:.2rem .65rem;border-radius:999px;font-size:.74rem;font-weight:700;}
        .b-green{background:#dcfce7;color:#15803d;}
        .b-amber{background:#fef3c7;color:#b45309;}
        .b-blue{background:#dbeafe;color:#1d4ed8;}
        .b-red{background:#fee2e2;color:#dc2626;}
        .b-gray{background:#f1f5f9;color:#475569;}
        .b-purple{background:#ede9fe;color:#6d28d9;}

        /* ── Tabs ── */
        .tab-row{display:flex;gap:8px;margin-bottom:1.25rem;border-bottom:2px solid var(--border);padding-bottom:0;}
        .tab-btn{padding:.55rem 1.2rem;font-weight:700;font-size:.875rem;color:var(--muted);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;transition:all .18s;}
        .tab-btn.active{color:var(--primary);border-bottom-color:var(--primary);}
        .tab-btn:hover{color:var(--primary);}

        /* ── Modal ── */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:9000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:white;border-radius:20px;padding:2rem;width:90%;max-width:460px;position:relative;animation:slideUp .25s ease;max-height:90vh;overflow-y:auto;}
        .modal-title{font-size:1.1rem;font-weight:800;margin-bottom:.25rem;}
        .modal-sub{font-size:.85rem;color:var(--muted);margin-bottom:1.2rem;}
        .modal-close{position:absolute;top:1rem;right:1rem;background:#f1f5f9;border:none;border-radius:8px;width:30px;height:30px;cursor:pointer;color:var(--muted);font-size:1rem;}

        /* ── Toast ── */
        #toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;}
        .toast-item{display:flex;align-items:flex-start;gap:12px;background:white;border-radius:14px;padding:1rem 1.2rem;box-shadow:0 8px 30px rgba(0,0,0,.15);min-width:280px;max-width:380px;pointer-events:all;animation:toastIn .3s ease;border-left:4px solid var(--primary);}
        .toast-item.success{border-left-color:var(--success);}
        .toast-item.error{border-left-color:var(--danger);}
        .toast-item.info{border-left-color:var(--blue);}
        .toast-icon{font-size:1.3rem;flex-shrink:0;}
        .toast-body{flex:1;}
        .toast-ttl{font-weight:800;font-size:.9rem;color:var(--text);}
        .toast-msg{font-size:.8rem;color:var(--muted);margin-top:2px;}
        .toast-cls{background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1rem;}
        @keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}
        @keyframes toastOut{to{opacity:0;transform:translateX(40px)}}

        /* ── QR Code card ── */
        .qr-card{background:linear-gradient(135deg,#1e293b,#0f172a);border-radius:16px;padding:1.5rem;text-align:center;color:white;}
        .qr-card h4{font-size:.9rem;font-weight:700;color:#94a3b8;margin-bottom:.5rem;}
        .qr-card p{font-size:.78rem;color:#64748b;margin-top:.5rem;}

        /* ── Activity feed ── */
        .activity-item{display:flex;align-items:flex-start;gap:.75rem;padding:.75rem 0;border-bottom:1px solid var(--border);}
        .activity-item:last-child{border:none;}
        .act-dot{width:36px;height:36px;border-radius:10px;background:#f1f5f9;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        .act-dot i{font-size:1.1rem;color:var(--primary);}
        .act-text{font-size:.875rem;font-weight:600;}
        .act-sub{font-size:.78rem;color:var(--muted);}

        /* ── Profile form ── */
        .profile-header{display:flex;align-items:center;gap:1.25rem;padding:1.5rem;background:linear-gradient(135deg,var(--primary),var(--primary-d));border-radius:14px;margin-bottom:1.5rem;color:white;}
        .profile-av{width:60px;height:60px;border-radius:16px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:1.8rem;font-weight:900;}
        .profile-name{font-size:1.1rem;font-weight:800;}
        .profile-phone{font-size:.85rem;opacity:.8;}

        /* ── Payment due notice ── */
        .due-card{display:flex;align-items:center;justify-content:space-between;padding:1rem 1.25rem;border:1.5px solid var(--border);border-left:4px solid var(--danger);border-radius:12px;margin-bottom:.75rem;background:white;}
        .due-info{font-size:.875rem;font-weight:600;}
        .due-amount{font-size:1.1rem;font-weight:900;color:var(--danger);}

        @media(max-width:768px){
            .app-wrap{grid-template-columns:1fr;}
            .sidebar{display:none;}
            .main{padding:1rem;}
        }
    </style>
</head>
<body>
<div id="toast-wrap"></div>

<div class="app-wrap">
    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="material-icons-outlined">local_laundry_service</i> DigiWash
        </div>
        <div class="user-chip">
            <div class="user-av"><?= strtoupper(substr($userName,0,1)) ?></div>
            <div>
                <div class="user-info-name"><?= $userName ?></div>
                <div class="user-info-phone"><?= $userPhone ?></div>
            </div>
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

        <?php if($needsProfileSetup): ?>
        <div class="alert-banner">
            <i class="material-icons-outlined">warning_amber</i>
            <p>Complete your profile (Name + Address) before placing orders.</p>
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
                <div class="stat-box">
                    <div class="stat-lbl">Active Orders</div>
                    <div class="stat-val" id="sActive">—</div>
                    <div class="stat-sub">In pipeline</div>
                </div>
                <div class="stat-box green">
                    <div class="stat-lbl">Completed</div>
                    <div class="stat-val" id="sCompleted">—</div>
                    <div class="stat-sub">All time</div>
                </div>
                <div class="stat-box red">
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
                        <div style="font-size:.82rem;font-weight:600;color:var(--muted);">Your Delivery OTP</div>
                        <div style="font-size:2rem;font-weight:900;letter-spacing:8px;color:var(--primary);margin-top:4px;">
                            <?= htmlspecialchars($user['dummy_otp'] ?? '——') ?>
                        </div>
                        <div style="font-size:.75rem;color:var(--muted);margin-top:4px;">Share only with your delivery partner</div>
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

// ── Tab switching ───────────────────────────────────────────────
function switchTab(id, el) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id)?.classList.add('active');
    document.querySelectorAll('.nav-item').forEach(n => n.classList.remove('active'));
    el?.classList.add('active');
    if (id === 'home')     { fetchStats(); loadActivity(); }
    if (id === 'history')  loadOrders('ongoing');
    if (id === 'payments') loadPayments('remaining');
    if (id === 'order')    loadProductCatalog();
    if (id === 'profile')  renderQR();
}

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
                        ${o.status === 'pending' ? `<button class="btn btn-sm btn-outline" style="border-color:var(--danger);color:var(--danger);" onclick="cancelOrder(${o.id})">✕ Cancel</button>` : ''}
                    </div>
                </div>
                ${timeline}
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

// ── Profile form ──────────────────────────────────────────────
document.getElementById('profileForm')?.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('saveProfileBtn');
    const msg = document.getElementById('profileMsg');
    btn.innerHTML = 'Saving…'; btn.disabled = true;
    const d = await apiCall('../api/user.php','update_profile',{
        name: document.getElementById('p_name').value,
        email: document.getElementById('p_email').value,
        shop_address: document.getElementById('p_address').value,
        alt_contact: document.getElementById('p_alt').value
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
        const r = await fetch(url,{ method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrfToken}, body:JSON.stringify({action,...payload}) });
        const d = await r.json(); return d;
    } catch { return { success:false, message:'Network error.' }; }
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

// ── Init ─────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fetchStats();
    loadActivity();
    renderQR();
    
    // Disable pay later options in dropdown if not approved
    Array.from(document.getElementById('paymentMode')?.options || []).forEach(opt => {
        if(opt.value.startsWith('PAY_LATER') && (userPayLaterPlan !== opt.value || userPayLaterStatus !== 'approved')) {
            opt.disabled = true;
        }
    });
});
</script>
</body>
</html>