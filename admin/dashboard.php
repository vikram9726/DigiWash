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

            <!-- Marketing Section -->
            <section id="marketing" class="section-content">
                <h2>Push Notifications & Coupons</h2>
                <div class="glass-panel" style="margin-top: 1.5rem; max-width: 600px;">
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
        </main>
    </div>

    <script>
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
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout' })
            });
            window.location.href = '../index.php';
        });
        document.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadUsers();
            loadOrders();
            loadReturns();
        });

        async function apiCall(action, data = {}) {
            try {
                const res = await fetch('../api/admin.php', {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
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
