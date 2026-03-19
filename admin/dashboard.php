<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash - Admin Panel</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --sidebar-w: 240px; }
        body { background: #f1f5f9; }
        .admin-wrap { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar { background: #1e293b; padding: 1.5rem 1rem; display: flex; flex-direction: column; gap: 0.25rem; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 0.5rem 0.75rem 1.5rem; color: white; font-size: 1.2rem; font-weight: 800; }
        .sidebar-brand i { color: #6366f1; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 0.75rem 1rem; border-radius: 10px; color: #94a3b8; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.2s; }
        .menu-item:hover { background: rgba(255,255,255,0.07); color: white; }
        .menu-item.active { background: linear-gradient(135deg,#6366f1,#4f46e5); color: white; box-shadow: 0 4px 15px rgba(99,102,241,0.4); }
        .menu-item i { font-size: 1.2rem; }
        .sidebar-section { font-size: 0.7rem; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 1px; padding: 1rem 1rem 0.25rem; }
        .badge-count { background: #ef4444; color: white; border-radius: 999px; font-size: 0.7rem; padding: 1px 6px; margin-left: auto; }

        /* ── Main Content ── */
        .main-content { padding: 2rem; overflow-x: hidden; }
        .top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        .page-title { font-size: 1.6rem; font-weight: 800; color: #1e293b; }
        .page-title span { color: #6366f1; }

        /* ── Section ── */
        .section-content { display: none; }
        .section-content.active { display: block; animation: fadeUp 0.3s ease; }
        @keyframes fadeUp { from { opacity:0; transform:translateY(10px); } to { opacity:1; transform:translateY(0); } }

        /* ── Stats ── */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1.2rem; margin-bottom: 2rem; }
        .stat-card { background: white; border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); border-left: 4px solid #6366f1; }
        .stat-card .label { font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-card .value { font-size: 2rem; font-weight: 800; color: #1e293b; }
        .stat-card .sub { font-size: 0.78rem; color: #94a3b8; }
        .stat-card.green { border-color: #10b981; } .stat-card.green .value { color: #059669; }
        .stat-card.red { border-color: #ef4444; } .stat-card.red .value { color: #dc2626; }
        .stat-card.amber { border-color: #f59e0b; } .stat-card.amber .value { color: #d97706; }

        /* ── Panel ── */
        .panel { background: white; border-radius: 16px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.08); margin-bottom: 1.5rem; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.2rem; flex-wrap: wrap; gap: 0.75rem; }
        .panel-title { font-size: 1.1rem; font-weight: 700; color: #1e293b; }
        .search-bar { display: flex; gap: 0.5rem; align-items: center; flex-wrap: wrap; }
        .search-bar input, .search-bar select { padding: 0.5rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 0.875rem; outline: none; transition: border-color 0.2s; }
        .search-bar input:focus, .search-bar select:focus { border-color: #6366f1; }

        /* ── Table ── */
        .tbl-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
        th { background: #f8fafc; color: #64748b; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; padding: 0.75rem 1rem; text-align: left; border-bottom: 1.5px solid #e2e8f0; }
        td { padding: 0.875rem 1rem; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
        tr:hover td { background: #f8fafc; }

        /* ── Badge ── */
        .badge { display: inline-flex; align-items: center; padding: 0.2rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .b-green { background:#dcfce7; color:#16a34a; }
        .b-red { background:#fee2e2; color:#dc2626; }
        .b-amber { background:#fef3c7; color:#d97706; }
        .b-blue { background:#dbeafe; color:#2563eb; }
        .b-purple { background:#ede9fe; color:#7c3aed; }
        .b-gray { background:#f1f5f9; color:#64748b; }

        /* ── Buttons ── */
        .btn-sm { padding: 0.3rem 0.7rem; font-size: 0.78rem; border-radius: 7px; border: none; cursor: pointer; font-weight: 600; transition: all 0.15s; white-space: nowrap; }
        .btn-sm:hover { filter: brightness(0.9); }
        .btn-primary { background: #6366f1; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-amber { background: #f59e0b; color: white; }
        .btn-outline { background: white; color: #6366f1; border: 1.5px solid #6366f1 !important; }
        .btn-ghost { background: #f1f5f9; color: #475569; }
        .btn-lg { padding: 0.7rem 1.5rem; font-size: 0.9rem; border-radius: 10px; }
        .action-btns { display: flex; gap: 5px; flex-wrap: wrap; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.55); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 2rem; width: 90%; max-width: 480px; max-height: 90vh; overflow-y: auto; position: relative; animation: fadeUp 0.25s ease; }
        .modal-box.lg { max-width: 800px; }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: #f1f5f9; border: none; border-radius: 8px; width: 32px; height: 32px; cursor: pointer; font-size: 1rem; color: #64748b; }
        .modal-title { font-size: 1.2rem; font-weight: 800; color: #1e293b; margin-bottom: 1.2rem; }

        /* ── Form ── */
        .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px,1fr)); gap: 1rem; margin-bottom: 0.5rem; }
        .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 0.75rem; }
        .form-group label { font-size: 0.8rem; font-weight: 700; color: #475569; }
        .form-group input, .form-group select, .form-group textarea, .form-control { padding: 0.55rem 0.8rem; border: 1.5px solid #e2e8f0; border-radius: 9px; font-size: 0.875rem; outline: none; font-family: inherit; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus, .form-control:focus { border-color: #6366f1; }
        .form-msg { font-size: 0.85rem; font-weight: 600; padding: 0.5rem 0; display: none; }

        /* ── Charts ── */
        .charts-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 2rem; }
        @media (max-width: 900px) { .charts-grid { grid-template-columns: 1fr; } .admin-wrap { grid-template-columns: 1fr; } .sidebar { display: none; } }

        /* ── Timeline chips ── */
        .filter-chips { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1rem; }
        .chip { padding: 0.3rem 0.85rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; border: 1.5px solid #e2e8f0; cursor: pointer; background: white; color: #64748b; transition: all 0.15s; }
        .chip.active { background: #6366f1; color: white; border-color: #6366f1; }

        /* ── No data ── */
        .no-data { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .no-data i { font-size: 3rem; display: block; margin-bottom: 0.5rem; }

        /* ── Partner card ── */
        .partner-card { display: flex; align-items: center; justify-content: space-between; padding: 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; margin-bottom: 0.75rem; transition: box-shadow 0.2s; }
        .partner-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        .partner-avatar { width: 44px; height: 44px; border-radius: 12px; background: linear-gradient(135deg,#6366f1,#4f46e5); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.3rem; font-weight: 800; flex-shrink: 0; }
        .partner-info { flex: 1; margin-left: 1rem; }
        .partner-info h4 { margin: 0; font-size: 0.95rem; font-weight: 700; }
        .partner-info p { margin: 2px 0 0; font-size: 0.8rem; color: #64748b; }
        .partner-stats span { font-size: 0.78rem; background: #f1f5f9; border-radius: 6px; padding: 3px 8px; font-weight: 600; color: #475569; margin-right: 5px; }
    </style>
</head>
<body>
<div class="admin-wrap">
    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="material-icons-outlined">local_laundry_service</i> DigiWash
        </div>
        <div class="sidebar-section">Dashboard</div>
        <div class="menu-item active" id="nav-overview" onclick="switchTab('overview',this)">
            <i class="material-icons-outlined">insights</i> Overview
        </div>
        <div class="sidebar-section">Management</div>
        <div class="menu-item" id="nav-users" onclick="switchTab('users',this)">
            <i class="material-icons-outlined">people</i> Customers
        </div>
        <div class="menu-item" id="nav-orders" onclick="switchTab('orders',this)">
            <i class="material-icons-outlined">assignment</i> Orders
        </div>
        <div class="menu-item" id="nav-partners" onclick="switchTab('partners',this)">
            <i class="material-icons-outlined">local_shipping</i> Delivery Partners
        </div>
        <div class="menu-item" id="nav-returns" onclick="switchTab('returns',this)">
            <i class="material-icons-outlined">assignment_return</i> Returns
            <span class="badge-count" id="returnsBadge" style="display:none">0</span>
        </div>
        <div class="menu-item" id="nav-products" onclick="switchTab('products',this)">
            <i class="material-icons-outlined">inventory_2</i> Products
        </div>
        <div class="sidebar-section">Marketing</div>
        <div class="menu-item" id="nav-marketing" onclick="switchTab('marketing',this)">
            <i class="material-icons-outlined">campaign</i> Coupons & Notifs
        </div>
        <div style="margin-top:auto; padding-top:2rem;">
            <div class="menu-item" id="logoutBtn" style="color:#ef4444;">
                <i class="material-icons-outlined">logout</i> Logout
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main-content">

        <!-- ══ OVERVIEW ══ -->
        <section id="overview" class="section-content active">
            <div class="top-bar">
                <div class="page-title">Platform <span>Overview</span></div>
                <button class="btn-sm btn-outline btn-lg" onclick="refreshAll()">↻ Refresh</button>
            </div>
            <div class="stats-grid">
                <div class="stat-card green"><div class="label">Total Revenue</div><div class="value" id="sRevenue">₹0</div><div class="sub">Collected payments</div></div>
                <div class="stat-card amber"><div class="label">Pending Dues</div><div class="value" id="sPending">₹0</div><div class="sub">Outstanding balance</div></div>
                <div class="stat-card"><div class="label">Active Orders</div><div class="value" id="sOrders">0</div><div class="sub">In progress</div></div>
                <div class="stat-card"><div class="label">Total Orders</div><div class="value" id="sTotalOrders">0</div><div class="sub">All time</div></div>
                <div class="stat-card"><div class="label">Customers</div><div class="value" id="sUsers">0</div><div class="sub">Registered</div></div>
                <div class="stat-card"><div class="label">Partners</div><div class="value" id="sPartners">0</div><div class="sub">Delivery staff</div></div>
                <div class="stat-card red"><div class="label">Pending Returns</div><div class="value" id="sReturns">0</div><div class="sub">Awaiting review</div></div>
            </div>
            <div class="charts-grid">
                <div class="panel"><div class="panel-title" style="margin-bottom:1rem;">Revenue Trend (Last 6 Months)</div><canvas id="revenueChart" height="200"></canvas></div>
                <div class="panel"><div class="panel-title" style="margin-bottom:1rem;">Order Distribution</div><canvas id="distChart" height="200"></canvas></div>
            </div>
            <div class="panel"><div class="panel-title" style="margin-bottom:1rem;">Orders Per Month</div><canvas id="ordersChart" height="100"></canvas></div>
        </section>

        <!-- ══ CUSTOMERS ══ -->
        <section id="users" class="section-content">
            <div class="top-bar"><div class="page-title">Customer <span>Management</span></div></div>
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">All Customers</div>
                    <div class="search-bar">
                        <input type="text" id="userSearch" placeholder="Search name / phone / email…" oninput="loadUsers()">
                    </div>
                </div>
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Orders</th><th>Spent</th><th>Joined</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="usersBody"><tr><td colspan="9"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ══ ORDERS ══ -->
        <section id="orders" class="section-content">
            <div class="top-bar"><div class="page-title">Order <span>Management</span></div></div>
            <div class="panel">
                <div class="panel-header">
                    <div class="panel-title">All Orders</div>
                    <div class="search-bar">
                        <input type="text" id="orderSearch" placeholder="Search order # / customer…" oninput="loadOrders()">
                        <select id="orderFilter" onchange="loadOrders()">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="pending">Pending</option>
                            <option value="in_process">In Process</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Customer</th><th>Amount</th><th>Status</th><th>Partner</th><th>Date</th><th>Actions</th></tr></thead>
                        <tbody id="ordersBody"><tr><td colspan="7"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ══ PARTNERS ══ -->
        <section id="partners" class="section-content">
            <div class="top-bar">
                <div class="page-title">Delivery <span>Partners</span></div>
                <button class="btn-sm btn-primary btn-lg" onclick="openModal('addPartnerModal')">+ Add Partner</button>
            </div>
            <div class="panel">
                <div class="panel-header"><div class="panel-title">All Delivery Partners</div></div>
                <div id="partnersContainer"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></div>
            </div>
        </section>

        <!-- ══ RETURNS ══ -->
        <section id="returns" class="section-content">
            <div class="top-bar"><div class="page-title">Return <span>Requests</span></div></div>
            <div class="filter-chips">
                <div class="chip active" onclick="loadReturns('all',this)">All</div>
                <div class="chip" onclick="loadReturns('pending',this)">Pending</div>
                <div class="chip" onclick="loadReturns('approved',this)">Approved</div>
                <div class="chip" onclick="loadReturns('declined',this)">Declined</div>
            </div>
            <div class="panel">
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>#</th><th>Customer</th><th>Order</th><th>Reason</th><th>Photo</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="returnsBody"><tr><td colspan="8"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></td></tr></tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ══ MARKETING ══ -->
        <section id="marketing" class="section-content">
            <div class="top-bar">
                <div class="page-title">Coupons <span>&amp; Notifications</span></div>
                <button class="btn-sm btn-primary btn-lg" onclick="openModal('addCouponModal')">+ New Coupon</button>
            </div>
            <div id="couponStatsBar" class="stats-grid" style="grid-template-columns:repeat(4,1fr);"></div>
            <div class="panel">
                <div class="panel-header"><div class="panel-title">🎟️ Coupons</div></div>
                <div class="tbl-wrap">
                    <table>
                        <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Min Order</th><th>Uses/Limit</th><th>Per User</th><th>Discount Given</th><th>Expires</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="couponsBody"><tr><td colspan="10"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></td></tr></tbody>
                    </table>
                </div>
            </div>
            <div class="panel" style="max-width:600px;">
                <div class="panel-title" style="margin-bottom:1.2rem;">📢 Send Push Notification</div>
                <form id="pushForm">
                    <div class="form-group"><label>Title</label><input type="text" id="pushTitle" required placeholder="e.g. Weekend 20% Off!"></div>
                    <div class="form-group"><label>Message</label><textarea id="pushMessage" rows="3" required placeholder="Your message here…"></textarea></div>
                    <div style="display:flex;align-items:center;gap:1rem;">
                        <button type="submit" class="btn-sm btn-success btn-lg" id="btnPush">Send Notification</button>
                        <div class="form-msg" id="pushMsg"></div>
                    </div>
                </form>
            </div>
        </section>

        <!-- ══ PRODUCTS ══ -->
        <section id="products" class="section-content">
            <div class="top-bar">
                <div class="page-title">Product <span>Catalog</span></div>
                <button class="btn-sm btn-primary" onclick="openModal('addProductModal')">+ Add Product</button>
            </div>
            <div id="productsGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:1.2rem;margin-top:1rem;"></div>
        </section>

    </main>
</div>

<!-- ══════════════ MODALS ══════════════ -->

<!-- User Orders Modal -->
<div class="modal-overlay" id="userOrdersModal">
    <div class="modal-box lg">
        <button class="modal-close" onclick="closeModal('userOrdersModal')">✕</button>
        <div class="modal-title" id="userOrdersTitle">Customer Orders</div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>#</th><th>Amount</th><th>Status</th><th>Partner</th><th>Date</th></tr></thead>
            <tbody id="userOrdersBody"></tbody>
        </table></div>
    </div>
</div>

<!-- Add Partner Modal -->
<div class="modal-overlay" id="addPartnerModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('addPartnerModal')">✕</button>
        <div class="modal-title">Add Delivery Partner</div>
        <form id="addPartnerForm">
            <div class="form-group"><label>Full Name *</label><input type="text" id="pName" required placeholder="e.g. Rahul Kumar"></div>
            <div class="form-group"><label>Phone (10 digits) *</label><input type="tel" id="pPhone" required maxlength="10" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)" placeholder="e.g. 9876543210"></div>
            <div class="form-group"><label>Login OTP (min 4 digits) *</label><input type="text" id="pOtp" required maxlength="6" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')" placeholder="e.g. 123456"></div>
            <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                <button type="submit" class="btn-sm btn-success btn-lg" id="btnAddPartner">Save Partner</button>
                <button type="button" class="btn-sm btn-ghost btn-lg" onclick="closeModal('addPartnerModal')">Cancel</button>
            </div>
            <div class="form-msg" id="addPartnerMsg"></div>
        </form>
    </div>
</div>

<!-- Edit Partner Modal -->
<div class="modal-overlay" id="editPartnerModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('editPartnerModal')">✕</button>
        <div class="modal-title">Edit Partner</div>
        <form id="editPartnerForm">
            <input type="hidden" id="editPartnerId">
            <div class="form-group"><label>Full Name *</label><input type="text" id="editPName" required></div>
            <div class="form-group"><label>New OTP (leave blank to keep)</label><input type="text" id="editPOtp" maxlength="6" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')" placeholder="Leave blank = unchanged"></div>
            <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                <button type="submit" class="btn-sm btn-primary btn-lg">Save Changes</button>
                <button type="button" class="btn-sm btn-ghost btn-lg" onclick="closeModal('editPartnerModal')">Cancel</button>
            </div>
            <div class="form-msg" id="editPartnerMsg"></div>
        </form>
    </div>
</div>

<!-- Partner Activity Modal -->
<div class="modal-overlay" id="partnerActivityModal">
    <div class="modal-box lg">
        <button class="modal-close" onclick="closeModal('partnerActivityModal')">✕</button>
        <div class="modal-title" id="partnerActivityTitle">Partner Activity</div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>#</th><th>Customer</th><th>Phone</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody id="partnerActivityBody"></tbody>
        </table></div>
    </div>
</div>

<!-- Add Coupon Modal -->
<div class="modal-overlay" id="addCouponModal">
    <div class="modal-box lg">
        <button class="modal-close" onclick="closeModal('addCouponModal')">✕</button>
        <div class="modal-title">Create New Coupon</div>
        <form id="addCouponForm">
            <div class="form-row">
                <div class="form-group"><label>Coupon Code *</label><input type="text" id="cCode" required placeholder="e.g. SAVE20" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')"></div>
                <div class="form-group"><label>Discount Type *</label><select id="cType"><option value="percentage">Percentage (%)</option><option value="flat">Flat (₹)</option></select></div>
                <div class="form-group"><label>Value *</label><input type="number" id="cValue" required min="1" placeholder="e.g. 20"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>Min Order Amount (₹)</label><input type="number" id="cMinOrder" min="0" value="0"></div>
                <div class="form-group"><label>Total Usage Limit</label><input type="number" id="cUsageLimit" min="1" placeholder="Unlimited"></div>
                <div class="form-group"><label>Per User Limit</label><input type="number" id="cPerUser" min="1" value="1"></div>
                <div class="form-group"><label>Expiry Date</label><input type="datetime-local" id="cExpiry"></div>
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                <button type="submit" class="btn-sm btn-success btn-lg" id="btnCreateCoupon">Create Coupon</button>
                <button type="button" class="btn-sm btn-ghost btn-lg" onclick="closeModal('addCouponModal')">Cancel</button>
            </div>
            <div class="form-msg" id="addCouponMsg"></div>
        </form>
    </div>
</div>

<!-- Coupon Usage Modal -->
<div class="modal-overlay" id="couponUsageModal">
    <div class="modal-box lg">
        <button class="modal-close" onclick="closeModal('couponUsageModal')">✕</button>
        <div class="modal-title" id="couponUsageTitle">Coupon Usage History</div>
        <div class="tbl-wrap"><table>
            <thead><tr><th>#</th><th>User</th><th>Phone</th><th>Email</th><th>Order</th><th>Order Total</th><th>Discount</th><th>Date</th></tr></thead>
            <tbody id="couponUsageBody"></tbody>
        </table></div>
    </div>
</div>

<!-- ══ PRODUCTS section moved inside main above ══ -->


<!-- Add Product Modal -->
<div class="modal-overlay" id="addProductModal">
    <div class="modal-box" style="max-width:500px;">
        <button class="modal-close" onclick="closeModal('addProductModal')">✕</button>
        <div class="modal-title">Add New Product</div>
        <form id="addProductForm" enctype="multipart/form-data">
            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" id="prdName" class="form-control" required placeholder="e.g. Shirt, Jeans, Bedsheet">
            </div>
            <div class="form-group">
                <label>Description (optional)</label>
                <input type="text" id="prdDesc" class="form-control" placeholder="e.g. Dry-cleaned and pressed">
            </div>
            <div class="form-group">
                <label>Product Image (optional)</label>
                <input type="file" id="prdImage" accept="image/*" onchange="previewImg(this,'prdPreview')">
                <img id="prdPreview" style="display:none;max-height:120px;margin-top:8px;border-radius:10px;object-fit:cover;">
            </div>
            <div class="form-group">
                <label>Pricing Tiers * <button type="button" class="btn-sm btn-outline" onclick="addPricingRow()" style="margin-left:8px;padding:2px 10px;font-size:0.78rem">+ Add Tier</button></label>
                <div id="pricingRows">
                    <div class="pricing-row" style="display:grid;grid-template-columns:1fr 100px 110px auto;gap:6px;align-items:center;margin-bottom:6px;">
                        <input type="text" class="form-control pr-size" placeholder="Size / Label (e.g. Small)" style="font-size:0.85rem">
                        <input type="number" class="form-control pr-price" placeholder="Price" min="1" style="font-size:0.85rem">
                        <select class="form-control pr-unit" style="font-size:0.85rem;padding:0.4rem">
                            <option>per piece</option><option>per kg</option><option>per set</option><option>per pair</option>
                        </select>
                        <button type="button" onclick="this.closest('.pricing-row').remove()" style="background:#fee2e2;border:none;border-radius:8px;padding:0.4rem 0.7rem;cursor:pointer;color:#dc2626;font-weight:700;flex-shrink:0;">✕</button>
                    </div>
                </div>
            </div>
            <div style="display:flex;gap:0.75rem;margin-top:1rem;">
                <button type="submit" class="btn-sm btn-primary btn-lg" id="btnSaveProd" style="flex:1;">Save Product</button>
                <button type="button" class="btn-sm btn-ghost btn-lg" onclick="closeModal('addProductModal')">Cancel</button>
            </div>
            <div class="form-msg" id="addProductMsg"></div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal-overlay" id="editProductModal">
    <div class="modal-box" style="max-width:480px;">
        <button class="modal-close" onclick="closeModal('editProductModal')">✕</button>
        <div class="modal-title">Edit Product — <span id="editPrdTitle"></span></div>
        <input type="hidden" id="editPrdId">
        <div class="form-group">
            <label>Product Name</label>
            <input type="text" id="editPrdName" class="form-control">
        </div>
        <div class="form-group">
            <label>Description</label>
            <input type="text" id="editPrdDesc" class="form-control">
        </div>
        <div class="form-group">
            <label>Pricing Tiers <button type="button" class="btn-sm btn-outline" onclick="addEditPricingRow()" style="margin-left:8px;padding:2px 10px;font-size:0.78rem">+ Add Tier</button></label>
            <div id="editPricingRows"></div>
        </div>
        <div style="display:flex;gap:0.75rem;margin-top:1rem;">
            <button class="btn-sm btn-primary btn-lg" style="flex:1;" onclick="saveProductEdit()">Save Changes</button>
            <button class="btn-sm btn-ghost btn-lg" onclick="closeModal('editProductModal')">Cancel</button>
        </div>
        <div class="form-msg" id="editProductMsg"></div>
    </div>
</div>

<script>
const csrf = "<?= $_SESSION['csrf_token'] ?? '' ?>";

// ── API helper ──
async function api(action, payload = {}) {
    try {
        const r = await fetch('../api/admin.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ action, ...payload })
        });
        return await r.json();
    } catch(e) { return { success: false, message: 'Server error' }; }
}

// ── Modal helpers ──
function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Tab switching ──
function switchTab(id, el) {
    document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
    (el || document.getElementById('nav-'+id)).classList.add('active');
    if (id === 'marketing') loadCoupons();
    if (id === 'orders') loadOrders();
    if (id === 'users') loadUsers();
    if (id === 'partners') loadPartners();
    if (id === 'returns') loadReturns('all');
    if (id === 'products') loadProducts();
}

// ── Toast ──
function toast(type, title, msg, dur=4000) {
    const icons = {success:'✅',error:'❌',info:'ℹ️'};
    const c = document.getElementById('toast-container') || (() => {
        const el = document.createElement('div');
        el.id = 'toast-container';
        Object.assign(el.style, {position:'fixed',top:'1.5rem',right:'1.5rem',zIndex:99999,display:'flex',flexDirection:'column',gap:'10px',pointerEvents:'none'});
        document.body.appendChild(el); return el;
    })();
    const t = document.createElement('div');
    t.style.cssText = 'display:flex;align-items:flex-start;gap:12px;background:white;border-radius:14px;padding:1rem 1.2rem;box-shadow:0 8px 30px rgba(0,0,0,0.15);min-width:280px;max-width:380px;pointer-events:all;animation:toastIn 0.3s ease;border-left:4px solid '+(type==='success'?'#10b981':type==='error'?'#ef4444':'#3b82f6');
    t.innerHTML = `<span style="font-size:1.3rem">${icons[type]||'🔔'}</span><div style="flex:1"><b style="font-size:0.9rem;color:#0f172a">${title}</b>${msg?`<div style="font-size:0.82rem;color:#64748b;margin-top:2px">${msg}</div>`:''}</div><button onclick="this.parentElement.remove()" style="background:none;border:none;cursor:pointer;color:#94a3b8;font-size:1rem;">✕</button>`;
    c.appendChild(t);
    if (!document.getElementById('toast-style')) { const s=document.createElement('style'); s.id='toast-style'; s.textContent='@keyframes toastIn{from{opacity:0;transform:translateX(40px)}to{opacity:1;transform:translateX(0)}}@keyframes toastOut{to{opacity:0;transform:translateX(40px)}}'; document.head.appendChild(s); }
    setTimeout(()=>{t.style.animation='toastOut 0.3s ease forwards';setTimeout(()=>t.remove(),300);},dur);
}

// ── Status badge ──
function statusBadge(s) {
    const map = { pending:'b-amber', in_process:'b-blue', picked_up:'b-blue',
                  out_for_delivery:'b-purple', delivered:'b-green', cancelled:'b-red' };
    return `<span class="badge ${map[s]||'b-gray'}">${s.replace(/_/g,' ').toUpperCase()}</span>`;
}

// ─────────────────────────────
// OVERVIEW
// ─────────────────────────────
async function loadStats() {
    const d = await api('get_stats');
    if (!d.success) return;
    document.getElementById('sRevenue').textContent      = '₹' + d.total_revenue;
    document.getElementById('sPending').textContent      = '₹' + d.revenue;
    document.getElementById('sOrders').textContent       = d.orders;
    document.getElementById('sTotalOrders').textContent  = d.total_orders;
    document.getElementById('sUsers').textContent        = d.users;
    document.getElementById('sPartners').textContent     = d.partners;
    document.getElementById('sReturns').textContent      = d.pending_returns;

    const rb = document.getElementById('returnsBadge');
    if (d.pending_returns > 0) { rb.textContent = d.pending_returns; rb.style.display = 'inline'; }
    else rb.style.display = 'none';
}

let revChart, distChart, ordChart;
async function loadAnalytics() {
    const d = await api('get_analytics');
    if (!d.success) return;

    if (revChart) revChart.destroy();
    revChart = new Chart(document.getElementById('revenueChart'), {
        type: 'line',
        data: {
            labels: d.revenue.map(x => x.month),
            datasets: [{ label: 'Revenue (₹)', data: d.revenue.map(x => x.total),
                borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.4, pointRadius: 5 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    if (distChart) distChart.destroy();
    const cols = ['#6366f1','#10b981','#f59e0b','#ef4444','#8b5cf6','#06b6d4'];
    distChart = new Chart(document.getElementById('distChart'), {
        type: 'doughnut',
        data: { labels: d.distribution.map(x => x.status.replace(/_/g,' ')), datasets: [{ data: d.distribution.map(x => x.count), backgroundColor: cols }] },
        options: { responsive: true, cutout: '65%' }
    });

    if (ordChart) ordChart.destroy();
    ordChart = new Chart(document.getElementById('ordersChart'), {
        type: 'bar',
        data: {
            labels: d.order_trends.map(x => x.month),
            datasets: [{ label: 'Orders', data: d.order_trends.map(x => x.count),
                backgroundColor: 'rgba(99,102,241,0.7)', borderRadius: 6 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } } }
    });
}

function refreshAll() { loadStats(); loadAnalytics(); }

// ─────────────────────────────
// CUSTOMERS
// ─────────────────────────────
async function loadUsers() {
    const tbody = document.getElementById('usersBody');
    const search = document.getElementById('userSearch').value;
    tbody.innerHTML = '<tr><td colspan="9"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></td></tr>';
    const d = await api('get_users', { search });
    if (!d.success || !d.users.length) {
        tbody.innerHTML = '<tr><td colspan="9"><div class="no-data"><i class="material-icons-outlined">people_outline</i>No customers found.</div></td></tr>';
        return;
    }
    tbody.innerHTML = d.users.map(u => `
        <tr>
            <td><strong>#${u.id}</strong></td>
            <td>${u.name || '<span style="color:#94a3b8">—</span>'}</td>
            <td>${u.phone}</td>
            <td style="font-size:0.82rem">${u.email || '—'}</td>
            <td><strong>${u.total_orders}</strong></td>
            <td>₹${parseFloat(u.total_spent||0).toFixed(0)}</td>
            <td style="font-size:0.8rem">${new Date(u.created_at).toLocaleDateString()}</td>
            <td>${u.is_blocked ? '<span class="badge b-red">Blocked</span>' : '<span class="badge b-green">Active</span>'}</td>
            <td>
                <div class="action-btns">
                    <button class="btn-sm btn-outline" onclick="viewUserOrders(${u.id},'${(u.name||'User').replace(/'/g,'')}')" title="View Orders">📋 Orders</button>
                    <button class="btn-sm ${u.is_blocked ? 'btn-success' : 'btn-amber'}" onclick="toggleBlockUser(${u.id})">${u.is_blocked ? 'Unblock' : 'Block'}</button>
                    <button class="btn-sm btn-danger" onclick="deleteUser(${u.id},'${(u.name||'User').replace(/'/g,'')}')">🗑 Delete</button>
                </div>
            </td>
        </tr>
    `).join('');
}

async function viewUserOrders(userId, name) {
    document.getElementById('userOrdersTitle').textContent = `Orders — ${name}`;
    document.getElementById('userOrdersBody').innerHTML = '<tr><td colspan="5">Loading…</td></tr>';
    openModal('userOrdersModal');
    const d = await api('get_user_orders', { user_id: userId });
    if (!d.success || !d.orders.length) {
        document.getElementById('userOrdersBody').innerHTML = '<tr><td colspan="5"><div class="no-data">No orders found.</div></td></tr>';
        return;
    }
    document.getElementById('userOrdersBody').innerHTML = d.orders.map(o => `
        <tr><td>#${o.id}</td><td>₹${o.total_amount}</td><td>${statusBadge(o.status)}</td><td>${o.delivery_name||'—'}</td><td>${new Date(o.created_at).toLocaleDateString()}</td></tr>
    `).join('');
}

async function toggleBlockUser(userId) {
    const d = await api('toggle_block_user', { user_id: userId });
    if (d.success) loadUsers(); else alert(d.message);
}

async function deleteUser(userId, name) {
    if (!confirm(`PERMANENTLY delete customer "${name}" and ALL their orders & data?\n\nThis cannot be undone.`)) return;
    const d = await api('delete_user', { user_id: userId });
    if (d.success) { loadUsers(); loadStats(); } else alert(d.message);
}

// ─────────────────────────────
// ORDERS
// ─────────────────────────────
async function loadOrders() {
    const tbody = document.getElementById('ordersBody');
    const search = document.getElementById('orderSearch').value;
    const filter = document.getElementById('orderFilter').value;
    tbody.innerHTML = '<tr><td colspan="7"><div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div></td></tr>';
    const d = await api('get_all_orders', { search, filter });
    if (!d.success || !d.orders.length) {
        tbody.innerHTML = '<tr><td colspan="7"><div class="no-data"><i class="material-icons-outlined">inbox</i>No orders found.</div></td></tr>';
        return;
    }
    const partners = d.delivery_partners;
    const makePartnerOpts = (selectedId) => '<option value="">Assign partner…</option>' +
        partners.map(p => `<option value="${p.id}" ${p.id == selectedId ? 'selected' : ''}>${p.name}</option>`).join('');

    tbody.innerHTML = d.orders.map(o => {
        const assignable = !['delivered','cancelled'].includes(o.status);
        const allStatuses = ['pending','picked_up','in_process','out_for_delivery','delivered','cancelled'];
        const statusOpts = allStatuses
            .map(s => `<option value="${s}" ${o.status===s?'selected':''}>${s.replace(/_/g,' ')}</option>`).join('');
        return `<tr>
            <td><strong>#${o.id}</strong></td>
            <td><div style="font-weight:600">${o.customer_name||'N/A'}</div><div style="font-size:0.78rem;color:#64748b">${o.customer_phone||''}</div></td>
            <td>₹${o.total_amount}</td>
            <td>${statusBadge(o.status)}</td>
            <td>
                ${assignable ? `<select data-current="${o.delivery_id||''}" onchange="assignOrder(${o.id},this.value)" style="font-size:0.8rem;padding:4px 8px;border:1.5px solid #e2e8f0;border-radius:8px;cursor:pointer;">
                    ${makePartnerOpts(o.delivery_id)}
                </select>` : (o.delivery_name||'—')}
            </td>
            <td style="font-size:0.8rem">${new Date(o.created_at).toLocaleDateString()}</td>
            <td>
                <div class="action-btns">
                    ${assignable ? `<select data-orderid="${o.id}" data-current="${o.status}" onchange="changeStatus(${o.id},this)" style="font-size:0.78rem;padding:3px 6px;border:1.5px solid #e2e8f0;border-radius:7px;">${statusOpts}</select>` : ''}
                    ${!['delivered','cancelled'].includes(o.status) ? `<button class="btn-sm btn-danger" onclick="cancelOrder(${o.id})">Cancel</button>` : ''}
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function assignOrder(orderId, deliveryId) {
    if (!deliveryId) return;
    const d = await api('assign_order', { order_id: orderId, delivery_id: deliveryId });
    if (!d.success) alert(d.message);
    loadOrders();
}

async function changeStatus(orderId, sel) {
    const newStatus = sel.value;
    const prev = sel.getAttribute('data-current');
    if (newStatus === prev) return; // no change
    if (!confirm(`Change order #${orderId} to "${newStatus.replace(/_/g,' ')}"?`)) {
        sel.value = prev; // revert
        return;
    }
    sel.setAttribute('data-current', newStatus);
    const d = await api('update_order_status', { order_id: orderId, status: newStatus });
    if (!d.success) { alert(d.message); sel.value = prev; sel.setAttribute('data-current', prev); }
    else loadStats();
}

async function cancelOrder(orderId) {
    if (!confirm(`Cancel Order #${orderId}? This cannot be undone.`)) return;
    const d = await api('cancel_order', { order_id: orderId });
    if (d.success) { loadOrders(); loadStats(); } else alert(d.message);
}

// ─────────────────────────────
// PARTNERS
// ─────────────────────────────
async function loadPartners() {
    const c = document.getElementById('partnersContainer');
    c.innerHTML = '<div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading…</div>';
    const d = await api('get_partners');
    if (!d.success || !d.partners.length) {
        c.innerHTML = '<div class="no-data"><i class="material-icons-outlined">local_shipping</i>No partners yet. Add one!</div>';
        return;
    }
    c.innerHTML = d.partners.map(p => `
        <div class="partner-card">
            <div class="partner-avatar">${(p.name||'?')[0].toUpperCase()}</div>
            <div class="partner-info">
                <h4>${p.name}</h4>
                <p>📞 ${p.phone} &nbsp;|&nbsp; OTP: <code style="background:#f1f5f9;padding:1px 5px;border-radius:4px">${p.dummy_otp||'—'}</code></p>
            </div>
            <div class="partner-stats">
                <span>📦 ${p.total_assignments} assignments</span>
                <span>✅ ${p.completed} delivered</span>
                <span>🔄 ${p.active} active</span>
            </div>
            <div class="action-btns" style="margin-left:1rem;">
                <button class="btn-sm btn-outline" onclick="viewPartnerActivity(${p.id},'${p.name.replace(/'/g,'')}')">Activity</button>
                <button class="btn-sm btn-amber" onclick="openEditPartner(${p.id},'${p.name.replace(/'/g,'')}')">Edit</button>
                <button class="btn-sm btn-danger" onclick="deletePartner(${p.id},'${p.name.replace(/'/g,'')}')">Delete</button>
            </div>
        </div>
    `).join('');
}

function openEditPartner(id, name) {
    document.getElementById('editPartnerId').value = id;
    document.getElementById('editPName').value = name;
    document.getElementById('editPOtp').value = '';
    openModal('editPartnerModal');
}

async function viewPartnerActivity(id, name) {
    document.getElementById('partnerActivityTitle').textContent = `Activity — ${name}`;
    document.getElementById('partnerActivityBody').innerHTML = '<tr><td colspan="6">Loading…</td></tr>';
    openModal('partnerActivityModal');
    const d = await api('get_partner_stats', { partner_id: id });
    if (!d.success || !d.orders.length) {
        document.getElementById('partnerActivityBody').innerHTML = '<tr><td colspan="6"><div class="no-data">No activity yet.</div></td></tr>';
        return;
    }
    document.getElementById('partnerActivityBody').innerHTML = d.orders.map(o => `
        <tr><td>#${o.id}</td><td>${o.customer_name}</td><td>${o.phone}</td><td>₹${o.total_amount}</td><td>${statusBadge(o.status)}</td><td>${new Date(o.created_at).toLocaleDateString()}</td></tr>
    `).join('');
}

async function deletePartner(id, name) {
    if (!confirm(`Remove delivery partner "${name}"?\nTheir existing order records will be preserved.`)) return;
    const d = await api('delete_delivery_partner', { partner_id: id });
    if (d.success) { loadPartners(); loadStats(); } else alert(d.message);
}

document.getElementById('addPartnerForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btnAddPartner');
    const msg = document.getElementById('addPartnerMsg');
    const phone = document.getElementById('pPhone').value.replace(/\D/g,'');
    if (phone.length !== 10) { msg.textContent='Phone must be 10 digits'; msg.style.color='#ef4444'; msg.style.display='block'; return; }
    btn.textContent = 'Saving…'; btn.disabled = true;
    const d = await api('create_delivery_partner', { name: document.getElementById('pName').value, phone, otp: document.getElementById('pOtp').value });
    msg.textContent = d.message; msg.style.color = d.success ? '#10b981' : '#ef4444'; msg.style.display = 'block';
    btn.textContent = 'Save Partner'; btn.disabled = false;
    if (d.success) { setTimeout(() => { closeModal('addPartnerModal'); e.target.reset(); msg.style.display='none'; loadPartners(); loadStats(); }, 1500); }
});

document.getElementById('editPartnerForm').addEventListener('submit', async e => {
    e.preventDefault();
    const msg = document.getElementById('editPartnerMsg');
    const d = await api('update_delivery_partner', { partner_id: document.getElementById('editPartnerId').value, name: document.getElementById('editPName').value, otp: document.getElementById('editPOtp').value });
    msg.textContent = d.message; msg.style.color = d.success ? '#10b981' : '#ef4444'; msg.style.display = 'block';
    if (d.success) setTimeout(() => { closeModal('editPartnerModal'); msg.style.display='none'; loadPartners(); }, 1200);
});

// ─────────────────────────────
// RETURNS
// ─────────────────────────────
async function loadReturns(filter = 'all', chipEl = null) {
    if (chipEl) { document.querySelectorAll('.filter-chips .chip').forEach(c => c.classList.remove('active')); chipEl.classList.add('active'); }
    const tbody = document.getElementById('returnsBody');
    tbody.innerHTML = '<tr><td colspan="8"><div class="no-data">Loading…</div></td></tr>';
    const d = await api('get_returns', { filter });
    if (!d.success || !d.returns.length) {
        tbody.innerHTML = `<tr><td colspan="8"><div class="no-data"><i class="material-icons-outlined">assignment_turned_in</i>No ${filter === 'all' ? '' : filter} returns.</div></td></tr>`;
        return;
    }
    const statusMap = { pending: 'b-amber', approved: 'b-green', declined: 'b-red' };
    tbody.innerHTML = d.returns.map(r => `
        <tr>
            <td>#${r.id}</td>
            <td><strong>${r.customer_name}</strong><br><small>${r.phone}</small></td>
            <td>Order #${r.order_id}<br><small>₹${r.total_amount}</small></td>
            <td style="max-width:200px;font-size:0.82rem">${r.reason}</td>
            <td>${r.photo_url ? `<a href="../${r.photo_url}" target="_blank" class="btn-sm btn-outline">📷 View</a>` : '—'}</td>
            <td style="font-size:0.8rem">${new Date(r.created_at).toLocaleDateString()}</td>
            <td><span class="badge ${statusMap[r.admin_status]||'b-gray'}">${(r.admin_status||'pending').toUpperCase()}</span></td>
            <td>
                ${r.admin_status === 'pending' ? `
                    <div class="action-btns">
                        <button class="btn-sm btn-success" onclick="handleReturn(${r.id},'approved')">✓ Approve</button>
                        <button class="btn-sm btn-danger" onclick="handleReturn(${r.id},'declined')">✗ Decline</button>
                    </div>` : '—'}
            </td>
        </tr>
    `).join('');
}

async function handleReturn(id, status) {
    if (!confirm(`${status === 'approved' ? 'Approve' : 'Decline'} this return request?`)) return;
    const d = await api('handle_return', { return_id: id, status });
    if (d.success) { loadReturns('all'); loadStats(); } else alert(d.message);
}

// ─────────────────────────────
// COUPONS
// ─────────────────────────────
async function loadCoupons() {
    const tbody = document.getElementById('couponsBody');
    const statsBar = document.getElementById('couponStatsBar');
    const d = await api('get_coupons');
    if (!d.success) return;
    const cs = d.coupons;
    const active = cs.filter(c=>c.is_active==1).length;
    const uses   = cs.reduce((s,c)=>s+parseInt(c.total_used||0),0);
    const saved  = cs.reduce((s,c)=>s+parseFloat(c.total_discount_given||0),0);
    statsBar.innerHTML = `
        <div class="stat-card"><div class="label">Active Coupons</div><div class="value">${active}</div></div>
        <div class="stat-card"><div class="label">Total Coupons</div><div class="value">${cs.length}</div></div>
        <div class="stat-card green"><div class="label">Redemptions</div><div class="value">${uses}</div></div>
        <div class="stat-card red"><div class="label">Discount Given</div><div class="value">₹${saved.toFixed(0)}</div></div>
    `;
    if (!cs.length) { tbody.innerHTML = '<tr><td colspan="10"><div class="no-data">No coupons yet.</div></td></tr>'; return; }
    const now = new Date();
    tbody.innerHTML = cs.map(c => {
        const expired = c.expires_at && new Date(c.expires_at) < now;
        return `<tr>
            <td><strong style="font-family:monospace;letter-spacing:1px">${c.code}</strong></td>
            <td><span class="badge ${c.discount_type==='percentage'?'b-blue':'b-purple'}">${c.discount_type==='percentage'?'%':'₹'} ${c.discount_type==='percentage'?'Percent':'Flat'}</span></td>
            <td><strong>${c.discount_type==='percentage'?c.discount_value+'%':'₹'+c.discount_value}</strong></td>
            <td>${c.min_order_amount>0?'₹'+c.min_order_amount:'—'}</td>
            <td>
                <strong>${c.total_used}</strong> / ${c.usage_limit||'∞'}
                ${c.total_used>0?`<br><a href="javascript:void(0)" onclick="viewCouponUsage(${c.id},'${c.code}')" style="font-size:0.78rem;color:#6366f1">View history</a>`:''}
            </td>
            <td>${c.per_user_limit}x</td>
            <td style="color:${parseFloat(c.total_discount_given)>0?'#dc2626':'#94a3b8'}">₹${parseFloat(c.total_discount_given||0).toFixed(2)}</td>
            <td style="${expired?'color:#dc2626':''}">${c.expires_at?new Date(c.expires_at).toLocaleDateString()+(expired?' <span class="badge b-red">Expired</span>':''):' —'}</td>
            <td><span class="badge ${c.is_active==1?'b-green':'b-gray'}">${c.is_active==1?'Active':'Inactive'}</span></td>
            <td>
                <div class="action-btns">
                    <button class="btn-sm ${c.is_active==1?'btn-ghost':'btn-success'}" onclick="toggleCoupon(${c.id})">${c.is_active==1?'Deactivate':'Activate'}</button>
                    <button class="btn-sm btn-danger" onclick="deleteCoupon(${c.id},'${c.code}')">Delete</button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

async function toggleCoupon(id) { const d = await api('toggle_coupon',{coupon_id:id}); if(d.success) loadCoupons(); }
async function deleteCoupon(id, code) {
    if (!confirm(`Delete coupon "${code}" and all its usage history?`)) return;
    const d = await api('delete_coupon',{coupon_id:id}); if(d.success) loadCoupons(); else alert(d.message);
}
async function viewCouponUsage(id, code) {
    document.getElementById('couponUsageTitle').textContent = `Usage: ${code}`;
    document.getElementById('couponUsageBody').innerHTML = '<tr><td colspan="8">Loading…</td></tr>';
    openModal('couponUsageModal');
    const d = await api('get_coupon_usage',{coupon_id:id});
    if (!d.success||!d.usages.length) { document.getElementById('couponUsageBody').innerHTML='<tr><td colspan="8"><div class="no-data">No usages recorded.</div></td></tr>'; return; }
    document.getElementById('couponUsageBody').innerHTML = d.usages.map((u,i)=>`
        <tr><td>${i+1}</td><td>${u.user_name||'—'}</td><td>${u.user_phone}</td><td>${u.user_email||'—'}</td>
        <td>#${u.order_id}</td><td>₹${parseFloat(u.order_total).toFixed(2)}</td>
        <td style="color:#dc2626;font-weight:700">−₹${parseFloat(u.discount_amount).toFixed(2)}</td>
        <td>${new Date(u.used_at).toLocaleString()}</td></tr>
    `).join('');
}

document.getElementById('addCouponForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btnCreateCoupon');
    const msg = document.getElementById('addCouponMsg');
    btn.textContent = 'Creating…'; btn.disabled = true;
    const d = await api('create_coupon', {
        code: document.getElementById('cCode').value,
        discount_type: document.getElementById('cType').value,
        discount_value: document.getElementById('cValue').value,
        min_order_amount: document.getElementById('cMinOrder').value||0,
        usage_limit: document.getElementById('cUsageLimit').value||'',
        per_user_limit: document.getElementById('cPerUser').value||1,
        expires_at: document.getElementById('cExpiry').value||''
    });
    msg.textContent = d.message; msg.style.color = d.success?'#10b981':'#ef4444'; msg.style.display='block';
    btn.textContent = 'Create Coupon'; btn.disabled = false;
    if (d.success) setTimeout(()=>{ closeModal('addCouponModal'); e.target.reset(); msg.style.display='none'; loadCoupons(); }, 1500);
});

document.getElementById('pushForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btnPush');
    const msg = document.getElementById('pushMsg');
    btn.textContent = 'Sending…'; btn.disabled = true;
    const d = await api('send_notification', { title: document.getElementById('pushTitle').value, message: document.getElementById('pushMessage').value });
    msg.textContent = d.message; msg.style.color = d.success?'#10b981':'#ef4444'; msg.style.display='block';
    btn.textContent = 'Send Notification'; btn.disabled = false;
    if (d.success) { e.target.reset(); setTimeout(()=>msg.style.display='none', 3000); }
});

// ─────────────────────────────
// PRODUCTS
// ─────────────────────────────
async function prodApi(action, payload = {}) {
    try {
        const r = await fetch('../api/products.php', {
            method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf},
            body: JSON.stringify({ action, ...payload })
        });
        return await r.json();
    } catch(e) { return { success:false, message:'Server error' }; }
}

async function loadProducts() {
    const grid = document.getElementById('productsGrid');
    grid.innerHTML = '<div class="no-data"><i class="material-icons-outlined">hourglass_empty</i>Loading...</div>';
    const d = await prodApi('get_products', { active_only: false });
    if (!d.success || !d.products.length) {
        grid.innerHTML = '<div class="no-data" style="grid-column:1/-1"><i class="material-icons-outlined">inventory_2</i>No products yet. Click "Add Product" to start.</div>';
        return;
    }
    grid.innerHTML = d.products.map(p => {
        const imgHtml = p.image_url
            ? `<img src="../${p.image_url}" style="width:100%;height:150px;object-fit:cover;border-radius:12px 12px 0 0;">`
            : `<div style="width:100%;height:120px;background:#f1f5f9;border-radius:12px 12px 0 0;display:flex;align-items:center;justify-content:center;"><i class="material-icons-outlined" style="font-size:3rem;color:#cbd5e1;">local_laundry_service</i></div>`;
        const priceList = p.prices.map(pp =>
            `<span style="background:#f1f5f9;border-radius:6px;padding:2px 8px;font-size:0.78rem;font-weight:600;color:#475569;">${pp.size_label} — ₹${pp.price} <em style="color:#94a3b8;font-style:normal">${pp.unit}</em></span>`
        ).join('');
        return `<div style="border:1.5px solid #e2e8f0;border-radius:14px;overflow:hidden;background:white;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
            ${imgHtml}
            <div style="padding:1rem;">
                <div style="font-weight:800;font-size:1rem;color:#0f172a;margin-bottom:4px;">${p.name} <span class="badge ${p.is_active?'b-green':'b-gray'}" style="font-size:0.7rem;">${p.is_active?'Active':'Inactive'}</span></div>
                ${p.description?`<div style="font-size:0.82rem;color:#64748b;margin-bottom:8px;">${p.description}</div>`:''}
                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-bottom:1rem;">${priceList||'<span style="color:#94a3b8;font-size:0.82rem;">No prices set</span>'}</div>
                <div class="action-btns">
                    <button class="btn-sm btn-outline" onclick="openEditProduct(${p.id},'${p.name.replace(/'/g,'')}','${(p.description||'').replace(/'/g,'')}', ${JSON.stringify(p.prices)})">Edit</button>
                    <button class="btn-sm ${p.is_active?'btn-ghost':'btn-success'}" onclick="toggleProduct(${p.id})">${p.is_active?'Deactivate':'Activate'}</button>
                    <button class="btn-sm btn-danger" onclick="deleteProduct(${p.id},'${p.name.replace(/'/g,'')}')">Delete</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function previewImg(input, previewId) {
    const file = input.files[0]; if (!file) return;
    const reader = new FileReader();
    reader.onload = e => { const img = document.getElementById(previewId); img.src = e.target.result; img.style.display='block'; };
    reader.readAsDataURL(file);
}

function addPricingRow(container = 'pricingRows', size = '', price = '', unit = 'per piece') {
    const row = document.createElement('div');
    row.className = 'pricing-row';
    row.style.cssText = 'display:grid;grid-template-columns:1fr 100px 110px auto;gap:6px;align-items:center;margin-bottom:6px;';
    row.innerHTML = `
        <input type="text" class="form-control pr-size" placeholder="Size / Label" value="${size}" style="font-size:0.85rem">
        <input type="number" class="form-control pr-price" placeholder="Price" min="1" value="${price}" style="font-size:0.85rem">
        <select class="form-control pr-unit" style="font-size:0.85rem;padding:0.4rem">
            ${['per piece','per kg','per set','per pair'].map(u=>`<option ${u===unit?'selected':''}>${u}</option>`).join('')}
        </select>
        <button type="button" onclick="this.closest('.pricing-row').remove()" style="background:#fee2e2;border:none;border-radius:8px;padding:0.4rem 0.7rem;cursor:pointer;color:#dc2626;font-weight:700;">✕</button>
    `;
    document.getElementById(container).appendChild(row);
}

function addEditPricingRow() { addPricingRow('editPricingRows'); }

document.getElementById('addProductForm').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = document.getElementById('btnSaveProd');
    const msg = document.getElementById('addProductMsg');
    const rows = document.querySelectorAll('#pricingRows .pricing-row');
    if (!rows.length) { msg.textContent='Add at least one pricing tier.'; msg.style.color='#ef4444'; msg.style.display='block'; return; }

    const prices = [];
    for (const row of rows) {
        const size = row.querySelector('.pr-size').value.trim();
        const price = parseFloat(row.querySelector('.pr-price').value);
        const unit = row.querySelector('.pr-unit').value;
        if (!size || !price) { msg.textContent='Fill in all pricing fields.'; msg.style.color='#ef4444'; msg.style.display='block'; return; }
        prices.push({ size_label: size, price, unit });
    }

    btn.textContent = 'Saving...'; btn.disabled = true;
    const fd = new FormData();
    fd.append('action','create_product');
    fd.append('name', document.getElementById('prdName').value);
    fd.append('description', document.getElementById('prdDesc').value);
    fd.append('prices', JSON.stringify(prices));
    const imgFile = document.getElementById('prdImage').files[0];
    if (imgFile) fd.append('image', imgFile);

    try {
        const r = await fetch('../api/products.php', { method:'POST', headers:{'X-CSRF-Token':csrf}, body: fd });
        const d = await r.json();
        msg.textContent = d.message; msg.style.color = d.success?'#10b981':'#ef4444'; msg.style.display='block';
        if (d.success) {
            toast('success','Product Created',d.message);
            setTimeout(()=>{ closeModal('addProductModal'); e.target.reset(); document.getElementById('prdPreview').style.display='none'; msg.style.display='none'; document.getElementById('pricingRows').innerHTML=''; addPricingRow(); loadProducts(); }, 1400);
        }
    } catch(err) { msg.textContent='Upload error.'; msg.style.color='#ef4444'; msg.style.display='block'; }
    btn.textContent='Save Product'; btn.disabled=false;
});

function openEditProduct(id, name, desc, prices) {
    document.getElementById('editPrdId').value = id;
    document.getElementById('editPrdTitle').textContent = name;
    document.getElementById('editPrdName').value = name;
    document.getElementById('editPrdDesc').value = desc;
    const container = document.getElementById('editPricingRows');
    container.innerHTML = '';
    (prices||[]).forEach(pp => addPricingRow('editPricingRows', pp.size_label, pp.price, pp.unit));
    openModal('editProductModal');
}

async function saveProductEdit() {
    const id   = document.getElementById('editPrdId').value;
    const name = document.getElementById('editPrdName').value.trim();
    const desc = document.getElementById('editPrdDesc').value.trim();
    const msg  = document.getElementById('editProductMsg');
    if (!name) { msg.textContent='Name required.'; msg.style.color='#ef4444'; msg.style.display='block'; return; }

    // Update product meta
    const d = await prodApi('update_product',{product_id:id,name,description:desc});
    if (!d.success) { msg.textContent=d.message; msg.style.color='#ef4444'; msg.style.display='block'; return; }

    // Handle new pricing rows (those without data-priceid attr)
    const rows = document.querySelectorAll('#editPricingRows .pricing-row');
    for (const row of rows) {
        const size = row.querySelector('.pr-size').value.trim();
        const price = parseFloat(row.querySelector('.pr-price').value);
        const unit = row.querySelector('.pr-unit').value;
        if (!size || !price) continue;
        const priceId = row.getAttribute('data-priceid');
        await prodApi('upsert_price',{price_id:priceId||0, product_id:id, size_label:size, price, unit});
    }
    msg.textContent='Product updated!'; msg.style.color='#10b981'; msg.style.display='block';
    toast('success','Product Updated','');
    setTimeout(()=>{ closeModal('editProductModal'); msg.style.display='none'; loadProducts(); }, 1200);
}

async function toggleProduct(id) {
    const d = await prodApi('toggle_product',{product_id:id});
    if (!d.success) { toast('error','Error',d.message); return; }
    loadProducts();
}

async function deleteProduct(id, name) {
    if (!confirm(`Delete product "${name}"? This will also remove its pricing tiers.`)) return;
    const d = await prodApi('delete_product',{product_id:id});
    if (d.success) { toast('success','Deleted',`"${name}" removed.`); loadProducts(); }
    else toast('error','Error',d.message);
}

// ── Logout ──
document.getElementById('logoutBtn').addEventListener('click', async () => {
    await fetch('../api/auth.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body: JSON.stringify({action:'logout'}) });
    window.location.href = '../index.php';
});

// ── Close modals on overlay click ──
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if (e.target === m) m.classList.remove('open'); });
});

// ── Initial Load ──
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadAnalytics();
});
</script>
</body>
</html>
