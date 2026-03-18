<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
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
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }
        .dashboard-grid { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; }
        .sidebar { display: flex; flex-direction: column; gap: 0.5rem; height: max-content;}
        .menu-item { display: flex; align-items: center; padding: 1rem; border-radius: 12px; cursor: pointer; font-weight: 600; color: var(--dark); transition: all 0.3s; }
        .menu-item i { margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: var(--primary-gradient); color: white; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); }
        .section-content { display: none; animation: slideUp 0.4s ease forwards; }
        .section-content.active { display: block; }

        /* Stats & Layout */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stat-card { padding: 1.5rem; text-align: center; }
        .stat-card h3 { font-size: 1.1rem; color: #475569; }
        .stat-card .value { font-size: 2.5rem; font-weight: 700; color: var(--primary); }

        /* Tables */
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { font-weight: 600; color: #475569; background: #f8fafc; }
        tr:hover { background: #f8fafc; }
        
        .badge { padding: 0.25rem 0.75rem; border-radius: 999px; font-size: 0.85rem; font-weight: 600; }
        .badge-pending { background: #fef3c7; color: #d97706; }
        .badge-success { background: #dcfce7; color: #16a34a; }
        .badge-danger { background: #fee2e2; color: #dc2626; }
        .badge-info { background: #dbeafe; color: #2563eb; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">
            <span class="material-icons-outlined" style="font-size: 2rem; color: var(--danger);">admin_panel_settings</span>
            <span>Admin Center</span>
        </div>
        <div class="nav-links">
            <a href="#" id="logoutBtn" style="color: var(--danger); display:flex; align-items:center;">
                <span class="material-icons-outlined" style="margin-right: 5px;">logout</span> Logout
            </a>
        </div>
    </nav>

    <div class="container dashboard-grid">
        <aside class="sidebar glass-panel">
            <div class="menu-item active" onclick="switchTab('overview')"><i class="material-icons-outlined">insights</i><span>Overview</span></div>
            <div class="menu-item" onclick="switchTab('users')"><i class="material-icons-outlined">people</i><span>Users & Delivery</span></div>
            <div class="menu-item" onclick="switchTab('orders')"><i class="material-icons-outlined">assignment</i><span>Manage Orders</span></div>
            <div class="menu-item" onclick="switchTab('partners')"><i class="material-icons-outlined">local_shipping</i><span>Delivery Partners</span></div>
            <div class="menu-item" onclick="switchTab('returns')"><i class="material-icons-outlined">assignment_return</i><span>Return Requests</span></div>
            <div class="menu-item" onclick="switchTab('marketing')"><i class="material-icons-outlined">campaign</i><span>Marketing & Coupons</span></div>
        </aside>

        <main class="content-area">
            <!-- Overview Section -->
            <section id="overview" class="section-content active">
                <h2 style="margin-bottom: 1.5rem;">Platform Overview</h2>
                <div class="stats-grid">
                    <div class="glass-panel stat-card"><h3>Total Revenue</h3><div class="value" id="statRevenue">₹0</div></div>
                    <div class="glass-panel stat-card"><h3>Active Orders</h3><div class="value" id="statOrders">0</div></div>
                    <div class="glass-panel stat-card"><h3>Total Users</h3><div class="value" id="statUsers">0</div></div>
                    <div class="glass-panel stat-card"><h3>Delivery Partners</h3><div class="value" id="statPartners">0</div></div>
                </div>

                <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; margin-top: 2rem;">
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <h3 style="margin-bottom:1rem;">Revenue Trends (Last 6 Months)</h3>
                        <canvas id="revenueChart"></canvas>
                    </div>
                    <div class="glass-panel" style="padding: 1.5rem;">
                        <h3 style="margin-bottom:1rem;">Order Status Distribution</h3>
                        <canvas id="distributionChart"></canvas>
                    </div>
                </div>
            </section>

            <!-- Users Section -->
            <section id="users" class="section-content">
                <h2>User Management</h2>
                <div class="glass-panel" style="margin-top: 1.5rem; overflow-x: auto;">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Email</th><th>Address</th><th>Joined</th></tr></thead>
                        <tbody id="usersTableBody"><tr><td colspan="6" style="text-align:center;">Loading...</td></tr></tbody>
                    </table>
                </div>
            </section>

            <!-- Orders & Routing Section -->
            <section id="orders" class="section-content">
                <h2>Live Orders & Routing</h2>
                <div class="glass-panel" style="margin-top: 1.5rem; overflow-x: auto;">
                    <table>
                        <thead><tr><th>Order ID</th><th>Customer</th><th>Status</th><th>Partner Assigned</th><th>Actions</th></tr></thead>
                        <tbody id="ordersTableBody"><tr><td colspan="5" style="text-align:center;">Loading...</td></tr></tbody>
                    </table>
                </div>
            </section>

            <!-- Returns Section -->
            <section id="returns" class="section-content">
                <h2>Return Requests</h2>
                <div class="glass-panel" style="margin-top: 1.5rem; overflow-x: auto;">
                    <table>
                        <thead><tr><th>Return ID</th><th>Order Info</th><th>Customer</th><th>Reason/Photo</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody id="returnsTableBody"><tr><td colspan="6" style="text-align:center;">Loading...</td></tr></tbody>
                    </table>
                </div>
            </section>

            <!-- Delivery Partners Section -->
            <section id="partners" class="section-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
                    <h2>Delivery Partners</h2>
                    <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('partnerModal').style.display='flex'">Add Partner</button>
                </div>
                <div class="glass-panel" style="overflow-x: auto;">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Phone</th><th>Dummy OTP</th><th>Date Joined</th></tr></thead>
                        <tbody id="partnersTableBody"><tr><td colspan="5" style="text-align:center;">Loading...</td></tr></tbody>
                    </table>
                </div>
            </section>            <!-- Marketing & Coupons Section -->
            <section id="marketing" class="section-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem; flex-wrap:wrap; gap:1rem;">
                    <div>
                        <h2 style="margin:0;">🎟️ Coupon Management</h2>
                        <p style="color:#64748b; margin:0.25rem 0 0;">Create, monitor and control all discount coupons.</p>
                    </div>
                    <button class="btn btn-primary" style="width:auto;" onclick="document.getElementById('couponFormPanel').style.display = document.getElementById('couponFormPanel').style.display === 'none' ? 'block' : 'none'">
                        ＋ Create New Coupon
                    </button>
                </div>

                <!-- Create Coupon Form -->
                <div id="couponFormPanel" class="glass-panel" style="display:none; padding:1.5rem; margin-bottom:2rem;">
                    <h3 style="margin-bottom:1.2rem;">New Coupon</h3>
                    <form id="couponForm">
                        <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:1rem;">
                            <div class="form-group">
                                <label>Coupon Code <span style="color:var(--danger)">*</span></label>
                                <input type="text" id="couponCode" class="form-control" required placeholder="e.g. SAVE20" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase().replace(/[^A-Z0-9]/g,'')">
                            </div>
                            <div class="form-group">
                                <label>Discount Type <span style="color:var(--danger)">*</span></label>
                                <select id="couponType" class="form-control">
                                    <option value="percentage">Percentage (%)</option>
                                    <option value="flat">Flat Amount (₹)</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Discount Value <span style="color:var(--danger)">*</span></label>
                                <input type="number" id="couponValue" class="form-control" required min="1" placeholder="e.g. 20">
                            </div>
                            <div class="form-group">
                                <label>Min. Order Amount (₹)</label>
                                <input type="number" id="couponMinOrder" class="form-control" min="0" placeholder="0 = No minimum">
                            </div>
                            <div class="form-group">
                                <label>Total Usage Limit</label>
                                <input type="number" id="couponUsageLimit" class="form-control" min="1" placeholder="Leave blank = Unlimited">
                            </div>
                            <div class="form-group">
                                <label>Per User Limit</label>
                                <input type="number" id="couponPerUser" class="form-control" min="1" value="1" placeholder="e.g. 1">
                            </div>
                            <div class="form-group">
                                <label>Expiry Date (Optional)</label>
                                <input type="datetime-local" id="couponExpiry" class="form-control">
                            </div>
                        </div>
                        <div style="display:flex; gap:1rem; margin-top:1rem; align-items:center;">
                            <button type="submit" class="btn btn-success" id="btnCreateCoupon" style="width:auto;">Create Coupon</button>
                            <button type="button" class="btn btn-outline" style="width:auto;" onclick="document.getElementById('couponFormPanel').style.display='none'">Cancel</button>
                            <p id="couponFormMsg" style="margin:0; font-weight:600; display:none;"></p>
                        </div>
                    </form>
                </div>

                <!-- Coupon Stats Bar -->
                <div id="couponStats" style="display:grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap:1rem; margin-bottom:1.5rem;"></div>

                <!-- Coupons Table -->
                <div class="glass-panel" style="overflow-x:auto; padding:0;">
                    <table id="couponsTable">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Type</th>
                                <th>Value</th>
                                <th>Min Order</th>
                                <th>Uses / Limit</th>
                                <th>Per User</th>
                                <th>Total Saved (₹)</th>
                                <th>Expires</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="couponsTableBody">
                            <tr><td colspan="10" style="text-align:center; padding:2rem;">Loading coupons...</td></tr>
                        </tbody>
                    </table>
                </div>

                <!-- Push Notifications (below) -->
                <h3 style="margin:2rem 0 1rem;">📢 Send Push Notification</h3>
                <div class="glass-panel" style="max-width:600px; padding:1.5rem;">
                    <form id="pushForm">
                        <div class="form-group">
                            <label>Notification Title</label>
                            <input type="text" id="pushTitle" class="form-control" required placeholder="e.g. 50% Off Weekend Sale!">
                        </div>
                        <div class="form-group">
                            <label>Message Body</label>
                            <textarea id="pushMessage" class="form-control" rows="3" required placeholder="Get 50% off on all dry cleaning today only."></textarea>
                        </div>
                        <button type="submit" class="btn btn-success" id="btnPush">Send Push Notification</button>
                    </form>
                    <p id="pushResponse" style="margin-top:1rem; font-weight:600; display:none;"></p>
                </div>
            </section>

            <!-- Coupon Usage History Drawer (full-width panel below table) -->
            <div id="usageDrawer" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; align-items:center; justify-content:center;">
                <div class="glass-panel" style="width:90%; max-width:900px; max-height:85vh; overflow-y:auto; padding:2rem; position:relative;">
                    <button onclick="document.getElementById('usageDrawer').style.display='none'" style="position:absolute;top:1rem;right:1rem;background:none;border:none;font-size:1.5rem;cursor:pointer;color:#64748b;">✕</button>
                    <h3 id="usageDrawerTitle" style="margin-bottom:0.5rem;">Coupon Usage History</h3>
                    <p id="usageDrawerMeta" style="color:#64748b; margin-bottom:1.5rem;"></p>
                    <div style="overflow-x:auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>User Name</th>
                                    <th>Phone</th>
                                    <th>Email</th>
                                    <th>Order #</th>
                                    <th>Order Total (₹)</th>
                                    <th>Discount Given (₹)</th>
                                    <th>Used At</th>
                                </tr>
                            </thead>
                            <tbody id="usageTableBody">
                                <tr><td colspan="8" style="text-align:center;">Loading...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Add Partner Modal -->
    <div id="partnerModal" class="modal" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="glass-panel" style="width:400px; padding:2rem;">
            <h3>Add Delivery Partner</h3>
            <form id="partnerForm" style="margin-top:1rem;">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="partnerName" class="form-control" required placeholder="e.g. John Doe">
                </div>
                <div class="form-group">
                    <label>Phone Number (with 91)</label>
                    <input type="text" id="partnerPhone" class="form-control" required placeholder="e.g. 919876543210">
                </div>
                <div class="form-group">
                    <label>Dummy OTP for Testing</label>
                    <input type="text" id="partnerOtp" class="form-control" required placeholder="e.g. 123456" maxlength="6">
                </div>
                <div style="display:flex; gap:10px; margin-top:1.5rem;">
                    <button type="submit" class="btn btn-success">Save Partner</button>
                    <button type="button" class="btn btn-outline" onclick="document.getElementById('partnerModal').style.display='none'">Cancel</button>
                </div>
            </form>
            <p id="partnerMsg" style="margin-top:1rem; font-weight:600; display:none;"></p>
        </div>
    </div>
    
    <script>
        const csrfToken = "<?= $_SESSION['csrf_token'] ?? '' ?>";

        function switchTab(tabId) {
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        }

        document.getElementById('logoutBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('../api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ action: 'logout' })
            });
            window.location.href = '../index.php';
        });
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadUsers();
            loadOrders();
            loadPartners();
            loadReturns();
            loadAnalytics();
        });

        async function loadAnalytics() {
            const data = await apiCall('get_analytics');
            if (data.success) {
                renderRevenueChart(data.revenue);
                renderDistributionChart(data.distribution);
            }
        }

        function renderRevenueChart(revenueData) {
            const ctx = document.getElementById('revenueChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: revenueData.map(d => d.month),
                    datasets: [{
                        label: 'Revenue (₹)',
                        data: revenueData.map(d => d.total),
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: { responsive: true, plugins: { legend: { display: false } } }
            });
        }

        function renderDistributionChart(distData) {
            const ctx = document.getElementById('distributionChart').getContext('2d');
            const colors = ['#4f46e5', '#10b981', '#f59e0b', '#ef4444', '#6366f1', '#8b5cf6'];
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: distData.map(d => d.status.toUpperCase()),
                    datasets: [{
                        data: distData.map(d => d.count),
                        backgroundColor: colors.slice(0, distData.length)
                    }]
                },
                options: { responsive: true, cutout: '70%' }
            });
        }

        async function loadPartners() {
            const tbody = document.getElementById('partnersTableBody');
            const data = await apiCall('get_partners');
            if (data.success && data.partners.length > 0) {
                tbody.innerHTML = data.partners.map(p => `
                    <tr>
                        <td>#${p.id}</td>
                        <td>${p.name}</td>
                        <td>${p.phone}</td>
                        <td><code>${p.dummy_otp || 'N/A'}</code></td>
                        <td>${new Date(p.created_at).toLocaleDateString()}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No delivery partners found.</td></tr>';
            }
        }

        document.getElementById('partnerForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const msg = document.getElementById('partnerMsg');
            btn.innerHTML = 'Saving...'; btn.disabled = true;

            const res = await apiCall('create_delivery_partner', {
                name: document.getElementById('partnerName').value,
                phone: document.getElementById('partnerPhone').value,
                otp: document.getElementById('partnerOtp').value
            });

            msg.innerText = res.message;
            msg.style.display = 'block';
            msg.style.color = res.success ? 'var(--secondary)' : 'var(--danger)';
            btn.innerHTML = 'Save Partner'; btn.disabled = false;

            if(res.success) {
                setTimeout(() => {
                    document.getElementById('partnerModal').style.display = 'none';
                    msg.style.display = 'none';
                    e.target.reset();
                    loadPartners();
                    loadStats();
                }, 1500);
            }
        });

        async function apiCall(action, data = {}) {
            try {
                const res = await fetch('../api/admin.php', {
                    method: 'POST', 
                    headers: { 
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({ action, ...data })
                });
                return await res.json();
            } catch (e) { console.error(e); return { success: false, message: 'Server error' }; }
        }

        async function loadStats() {
            const data = await apiCall('get_stats');
            if (data.success) {
                document.getElementById('statRevenue').innerText = '₹' + data.revenue;
                document.getElementById('statOrders').innerText = data.orders;
                document.getElementById('statUsers').innerText = data.users;
                document.getElementById('statPartners').innerText = data.partners;
            }
        }

        async function loadUsers() {
            const tbody = document.getElementById('usersTableBody');
            const data = await apiCall('get_users');
            if (data.success && data.users.length > 0) {
                tbody.innerHTML = data.users.map(u => `
                    <tr>
                        <td>#${u.id}</td>
                        <td>${u.name || '-'}</td>
                        <td>${u.phone}</td>
                        <td>${u.email || '-'}</td>
                        <td>${u.shop_address || '-'}</td>
                        <td>${new Date(u.created_at).toLocaleDateString()}</td>
                    </tr>
                `).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No customers found.</td></tr>';
            }
        }

        async function loadOrders() {
            const tbody = document.getElementById('ordersTableBody');
            const data = await apiCall('get_all_orders');
            if (data.success && data.orders.length > 0) {
                const partners = data.delivery_partners;
                let partnerOptions = '<option value="">Select Partner...</option>';
                partners.forEach(p => partnerOptions += `<option value="${p.id}">${p.name}</option>`);

                tbody.innerHTML = data.orders.map(o => {
                    let statusBadge = o.status === 'delivered' ? 'badge-success' : (o.status === 'cancelled' ? 'badge-danger' : 'badge-pending');
                    return `
                    <tr>
                        <td>#${o.id}</td>
                        <td>${o.customer_name || 'User ' + o.user_id}</td>
                        <td><span class="badge ${statusBadge}">${o.status.replace('_', ' ').toUpperCase()}</span></td>
                        <td>
                            ${o.status === 'pending' || o.status === 'in_process' ? `
                                <select class="form-control" style="padding:0.3rem;" onchange="assignOrder(${o.id}, this.value)">
                                    ${partnerOptions.replace(`value="${o.delivery_id}"`, `value="${o.delivery_id}" selected`)}
                                </select>
                            ` : (o.delivery_name || 'Unassigned')}
                        </td>
                        <td>
                            ${o.status !== 'cancelled' && o.status !== 'delivered' ? `
                                <button class="btn btn-danger" style="padding:0.3rem 0.5rem; font-size:0.8rem; width:auto;" onclick="cancelOrder(${o.id})">Cancel</button>
                            ` : '-'}
                        </td>
                    </tr>
                `}).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No orders found.</td></tr>';
            }
        }

        async function assignOrder(orderId, deliveryId) {
            if (!deliveryId) return;
            const data = await apiCall('assign_order', { order_id: orderId, delivery_id: deliveryId });
            alert(data.message);
            loadOrders();
        }

        async function cancelOrder(orderId) {
            if (confirm('Are you sure you want to cancel this order?')) {
                const data = await apiCall('cancel_order', { order_id: orderId });
                alert(data.message);
                loadOrders();
                loadStats();
            }
        }

        async function loadReturns() {
            const tbody = document.getElementById('returnsTableBody');
            const data = await apiCall('get_returns');
            if (data.success && data.returns.length > 0) {
                tbody.innerHTML = data.returns.map(r => {
                    let statusBadge = r.status === 'approved' ? 'badge-success' : (r.status === 'rejected' ? 'badge-danger' : 'badge-pending');
                    return `
                    <tr>
                        <td>#${r.id}</td>
                        <td>Order #${r.order_id} <br><small>₹${r.total_amount}</small></td>
                        <td>${r.customer_name} <br><small>${r.phone}</small></td>
                        <td>${r.reason} <br><a href="../${r.photo_url}" target="_blank" style="color:var(--primary); font-size:0.8rem;">View Photo</a></td>
                        <td><span class="badge ${statusBadge}">${r.status.toUpperCase()}</span></td>
                        <td>
                            ${r.status === 'pending' ? `
                                <button class="btn btn-success" style="padding:0.3rem 0.5rem; font-size:0.8rem; width:auto;" onclick="handleReturn(${r.id}, 'approved')">Approve</button>
                                <button class="btn btn-danger" style="padding:0.3rem 0.5rem; font-size:0.8rem; width:auto; margin-top:5px;" onclick="handleReturn(${r.id}, 'rejected')">Reject</button>
                            ` : '-'}
                        </td>
                    </tr>
                `}).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No return requests.</td></tr>';
            }
        }

        async function handleReturn(returnId, status) {
            if (confirm(`Are you sure you want to ${status} this request?`)) {
                const data = await apiCall('handle_return', { return_id: returnId, status: status });
                alert(data.message);
                loadReturns();
            }
        }

        // ====================== COUPON MANAGEMENT ======================

        async function loadCoupons() {
            const tbody = document.getElementById('couponsTableBody');
            const statsBar = document.getElementById('couponStats');
            const data = await apiCall('get_coupons');
            if (!data.success) return;

            const coupons = data.coupons;

            // Stats bar
            const totalActive = coupons.filter(c => c.is_active == 1).length;
            const totalUsages = coupons.reduce((s, c) => s + parseInt(c.total_used || 0), 0);
            const totalSaved  = coupons.reduce((s, c) => s + parseFloat(c.total_discount_given || 0), 0);
            statsBar.innerHTML = `
                <div class="glass-panel stat-card"><h3>Active Coupons</h3><div class="value" style="font-size:2rem;">${totalActive}</div></div>
                <div class="glass-panel stat-card"><h3>Total Redemptions</h3><div class="value" style="font-size:2rem;">${totalUsages}</div></div>
                <div class="glass-panel stat-card"><h3>Total Discount Given</h3><div class="value" style="font-size:2rem;">₹${totalSaved.toFixed(2)}</div></div>
                <div class="glass-panel stat-card"><h3>Total Coupons</h3><div class="value" style="font-size:2rem;">${coupons.length}</div></div>
            `;

            if (coupons.length === 0) {
                tbody.innerHTML = '<tr><td colspan="10" style="text-align:center; padding:2rem; color:#94a3b8;">No coupons yet. Click "+ Create New Coupon" to add one.</td></tr>';
                return;
            }

            tbody.innerHTML = coupons.map(c => {
                const isActive = c.is_active == 1;
                const usageDisplay = c.usage_limit ? `${c.total_used} / ${c.usage_limit}` : `${c.total_used} / ∞`;
                const expiryDisplay = c.expires_at ? new Date(c.expires_at).toLocaleString() : '—';
                const expiryWarning = c.expires_at && new Date(c.expires_at) < new Date() ? ' style="color:var(--danger)"' : '';
                const discountDisplay = c.discount_type === 'percentage' ? `${c.discount_value}%` : `₹${c.discount_value}`;
                const minDisplay = c.min_order_amount > 0 ? `₹${c.min_order_amount}` : 'None';

                return `<tr>
                    <td><strong style="font-family:monospace; font-size:1rem; letter-spacing:1px;">${c.code}</strong></td>
                    <td><span class="badge ${c.discount_type === 'percentage' ? 'badge-info' : 'badge-success'}">${c.discount_type === 'percentage' ? 'Percent' : 'Flat'}</span></td>
                    <td><strong>${discountDisplay}</strong></td>
                    <td>${minDisplay}</td>
                    <td>
                        <span style="font-weight:600;">${usageDisplay}</span>
                        ${c.total_used > 0 ? `<br><small><a href="javascript:void(0)" onclick="viewCouponUsage(${c.id}, '${c.code}')" style="color:var(--primary);">View History</a></small>` : ''}
                    </td>
                    <td>${c.per_user_limit}x</td>
                    <td style="color: ${parseFloat(c.total_discount_given) > 0 ? 'var(--danger)' : '#94a3b8'};">₹${parseFloat(c.total_discount_given || 0).toFixed(2)}</td>
                    <td${expiryWarning}>${expiryDisplay}</td>
                    <td>
                        <span class="badge ${isActive ? 'badge-success' : 'badge-danger'}">${isActive ? 'Active' : 'Inactive'}</span>
                    </td>
                    <td style="white-space:nowrap;">
                        <button class="btn ${isActive ? 'btn-outline' : 'btn-success'}" style="padding:0.3rem 0.6rem; font-size:0.78rem; width:auto;" onclick="toggleCoupon(${c.id})">
                            ${isActive ? 'Deactivate' : 'Activate'}
                        </button>
                        <button class="btn btn-danger" style="padding:0.3rem 0.6rem; font-size:0.78rem; width:auto; margin-top:3px;" onclick="deleteCoupon(${c.id}, '${c.code}')">
                            Delete
                        </button>
                    </td>
                </tr>`;
            }).join('');
        }

        async function toggleCoupon(couponId) {
            const res = await apiCall('toggle_coupon', { coupon_id: couponId });
            if (res.success) loadCoupons();
            else alert(res.message);
        }

        async function deleteCoupon(couponId, code) {
            if (!confirm(`Delete coupon "${code}"? This will also remove all usage history.`)) return;
            const res = await apiCall('delete_coupon', { coupon_id: couponId });
            if (res.success) loadCoupons();
            else alert(res.message);
        }

        async function viewCouponUsage(couponId, code) {
            document.getElementById('usageDrawerTitle').innerText = `Usage History: ${code}`;
            document.getElementById('usageDrawerMeta').innerText = 'Loading...';
            document.getElementById('usageTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; padding:1.5rem;">Loading...</td></tr>';
            document.getElementById('usageDrawer').style.display = 'flex';

            const res = await apiCall('get_coupon_usage', { coupon_id: couponId });
            if (!res.success) {
                document.getElementById('usageDrawerMeta').innerText = res.message;
                return;
            }

            const usages = res.usages;
            document.getElementById('usageDrawerMeta').innerText = `${usages.length} redemption(s) found for coupon "${code}"`;

            if (usages.length === 0) {
                document.getElementById('usageTableBody').innerHTML = '<tr><td colspan="8" style="text-align:center; color:#94a3b8; padding:2rem;">No usages recorded yet.</td></tr>';
                return;
            }

            document.getElementById('usageTableBody').innerHTML = usages.map((u, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${u.user_name || '—'}</strong></td>
                    <td>${u.user_phone}</td>
                    <td>${u.user_email || '—'}</td>
                    <td>#${u.order_id}</td>
                    <td>₹${parseFloat(u.order_total).toFixed(2)}</td>
                    <td style="color:var(--danger); font-weight:600;">−₹${parseFloat(u.discount_amount).toFixed(2)}</td>
                    <td>${new Date(u.used_at).toLocaleString()}</td>
                </tr>
            `).join('');
        }

        document.getElementById('couponForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnCreateCoupon');
            const msg = document.getElementById('couponFormMsg');
            btn.innerHTML = 'Creating...'; btn.disabled = true;
            msg.style.display = 'none';

            const res = await apiCall('create_coupon', {
                code:             document.getElementById('couponCode').value,
                discount_type:    document.getElementById('couponType').value,
                discount_value:   document.getElementById('couponValue').value,
                min_order_amount: document.getElementById('couponMinOrder').value || 0,
                usage_limit:      document.getElementById('couponUsageLimit').value || '',
                per_user_limit:   document.getElementById('couponPerUser').value || 1,
                expires_at:       document.getElementById('couponExpiry').value || ''
            });

            msg.innerText = res.message;
            msg.style.display = 'block';
            msg.style.color = res.success ? 'var(--secondary)' : 'var(--danger)';
            btn.innerHTML = 'Create Coupon'; btn.disabled = false;

            if (res.success) {
                setTimeout(() => {
                    document.getElementById('couponFormPanel').style.display = 'none';
                    document.getElementById('couponForm').reset();
                    msg.style.display = 'none';
                    loadCoupons();
                }, 1500);
            }
        });

        // Load coupons when marketing tab is opened
        const originalSwitchTab = window.switchTab;
        function switchTab(tabId) {
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            if (tabId === 'marketing') loadCoupons();
        }

        document.getElementById('pushForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = document.getElementById('btnPush');
            const msg = document.getElementById('pushResponse');
            btn.innerHTML = 'Sending...'; btn.disabled = true; msg.style.display = 'none';

            const data = await apiCall('send_notification', {
                title: document.getElementById('pushTitle').value,
                message: document.getElementById('pushMessage').value
            });

            msg.innerText = data.message;
            msg.style.display = 'block';
            msg.style.color = data.success ? 'var(--secondary)' : 'var(--danger)';
            btn.innerHTML = 'Send Push Notification'; btn.disabled = false;

            if(data.success) document.getElementById('pushForm').reset();
        });
    </script>
</body>
</html>
