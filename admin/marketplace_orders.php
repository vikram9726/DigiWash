<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php'); exit;
}
$csrfToken = $_SESSION['csrf_token'] ?? '';

// Fetch active delivery partners for assignment dropdowns
$stmt = $pdo->query("SELECT id, name, current_orders FROM users WHERE role = 'delivery' AND is_blocked = 0 ORDER BY name ASC");
$partners = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Orders - Admin</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#f1f5f9;
            --sidebar-bg:#1e293b;
            --card:white;
            --primary:#ec4899;
            --primary-d:#be185d;
            --success:#10b981;
            --danger:#ef4444;
            --text:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --sidebar-w:240px;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);height:100vh;overflow:hidden;}
        .admin-wrap{display:grid;grid-template-columns:var(--sidebar-w) 1fr;height:100vh;overflow:hidden;}
        
        /* Sidebar */
        .sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:1.5rem 1rem;height:100vh;overflow-y:auto;}
        .sidebar-brand{display:flex;align-items:center;gap:10px;padding:0.5rem 0.75rem 1.5rem;color:white;font-size:1.2rem;font-weight:800;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;}
        .sidebar-brand i{color:#6366f1;}
        .menu-item{display:flex;align-items:center;gap:12px;padding:0.75rem 1rem;border-radius:10px;color:#94a3b8;font-weight:600;font-size:0.9rem;cursor:pointer;transition:all 0.2s;text-decoration:none;margin-bottom:5px;}
        .menu-item:hover{background:rgba(255,255,255,0.07);color:white;}
        .menu-item.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;box-shadow:0 4px 15px rgba(236,72,153,0.4);}
        .menu-item i{font-size:1.2rem;}

        /* Main */
        .main-content{padding:2rem;height:100vh;overflow-y:auto;}
        .top-bar{display:flex;justify-content:space-between;align-items:center;margin-bottom:2rem;flex-wrap:wrap;gap:1rem;}
        .page-title{font-size:1.6rem;font-weight:800;color:var(--text);}
        .page-title span{color:var(--primary);}

        .panel{background:white;border-radius:16px;padding:1.5rem;box-shadow:0 1px 3px rgba(0,0,0,0.08);margin-bottom:1.5rem;}
        
        .btn{display:inline-flex;align-items:center;gap:7px;padding:.6rem 1.2rem;border-radius:10px;font-weight:700;font-size:0.9rem;cursor:pointer;border:none;transition:all .15s;}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;}
        .btn-success{background:var(--success);color:white;}
        .btn-danger{background:var(--danger);color:white;}
        .btn-ghost{background:#f1f5f9;color:var(--muted);}
        .btn-outline{background:white;color:var(--primary);border:2px solid var(--primary);}
        .btn-sm{padding:0.4rem 0.8rem;font-size:0.8rem;}
        .btn:hover:not([disabled]){filter:brightness(0.92);transform:translateY(-1px);}

        /* Table */
        .tbl-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:0.875rem;}
        th{background:#f8fafc;color:#64748b;font-weight:700;font-size:0.75rem;text-transform:uppercase;padding:0.75rem 1rem;text-align:left;border-bottom:1.5px solid var(--border);}
        td{padding:0.875rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:top;}
        tr:hover td{background:#f8fafc;}

        /* Badge */
        .badge{display:inline-flex;align-items:center;padding:0.2rem 0.65rem;border-radius:999px;font-size:0.75rem;font-weight:700;}
        .b-green{background:#dcfce7;color:#16a34a;}
        .b-amber{background:#fef3c7;color:#b45309;}
        .b-purple{background:#ede9fe;color:#7c3aed;}
        .b-blue{background:#dbeafe;color:#2563eb;}
        .b-red{background:#fee2e2;color:#dc2626;}
        
        /* Modal */
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:white;border-radius:20px;padding:2rem;width:90%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative;animation:fadeUp 0.25s ease;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .modal-close{position:absolute;top:1rem;right:1rem;background:#f1f5f9;border:none;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:1rem;color:var(--muted);}
        .modal-title{font-size:1.2rem;font-weight:800;color:var(--text);margin-bottom:1.2rem;}

        .form-group{margin-bottom:0.75rem;}
        .form-group label{font-size:0.8rem;font-weight:700;color:#475569;display:block;margin-bottom:5px;}
        .form-group select{width:100%;padding:0.6rem 0.8rem;border:1.5px solid var(--border);border-radius:9px;font-size:0.875rem;outline:none;font-family:inherit;transition:0.2s;}
        .form-group select:focus{border-color:var(--primary);}
        
        .filter-bar{display:flex;gap:10px;margin-bottom:1rem;align-items:center;}
        .filter-bar select{padding:0.6rem 0.8rem;border:1.5px solid var(--border);border-radius:9px;font-size:0.875rem;outline:none;font-family:inherit;cursor:pointer;}

        #toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:10px;}
        .toast{background:white;border-left:4px solid var(--primary);padding:1rem 1.2rem;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.15);animation:slideIn .3s ease;}
        @keyframes slideIn{from{transform:translateX(100%)}to{transform:translateX(0)}}
    </style>
</head>
<body>
<div id="toast-wrap"></div>

<div class="admin-wrap">
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="material-icons-outlined">local_laundry_service</i> DigiWash
        </div>
        <a href="dashboard.php" class="menu-item">
            <i class="material-icons-outlined">arrow_back</i> Back to Dashboard
        </a>
        <div style="margin:1rem 0;border-top:1px solid rgba(255,255,255,0.08);"></div>
        
        <a href="marketplace_products.php" class="menu-item">
            <i class="material-icons-outlined">storefront</i> Store Products
        </a>
        <div class="menu-item active">
            <i class="material-icons-outlined">shopping_cart_checkout</i> Store Orders
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">Marketplace <span>Orders</span></div>
            <button class="btn btn-outline" onclick="fetchOrders()">↻ Refresh</button>
        </div>

        <div class="panel">
            <div class="filter-bar">
                <span style="font-weight:700;color:var(--text);font-size:.9rem;">Filter by Status:</span>
                <select id="statusFilter" onchange="fetchOrders()">
                    <option value="all">All Orders</option>
                    <option value="active">Active (Pending/Assigned/Delivery)</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            
            <div class="tbl-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Payment</th>
                            <th>Assign To</th>
                            <th>Order Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="oList">
                        <tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem;">Loading orders...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Assign Partner Modal -->
<div class="modal-overlay" id="assignModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('assignModal')">✕</button>
        <div class="modal-title">Assign Delivery Partner</div>
        <input type="hidden" id="assignOrderId">
        <div class="form-group">
            <label>Select Partner for Order #<span id="lblOrderId"></span></label>
            <select id="assignPartnerId">
                <option value="">-- Choose Partner --</option>
                <?php foreach($partners as $p): ?>
                    <option value="<?= $p['id'] ?>">
                        <?= htmlspecialchars($p['name']) ?> 
                        (<?= $p['current_orders'] ?> active orders)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display:flex;gap:10px;margin-top:1.5rem;">
            <button class="btn btn-primary" style="flex:1;justify-content:center;" onclick="submitAssignment()">Assign Order</button>
            <button class="btn btn-ghost" onclick="closeModal('assignModal')">Cancel</button>
        </div>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal-overlay" id="statusModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('statusModal')">✕</button>
        <div class="modal-title">Update Order Status</div>
        <input type="hidden" id="statusOrderId">
        <div class="form-group">
            <label>New Status for Order #<span id="lblStatusOrderId"></span></label>
            <select id="newStatusSelect">
                <option value="placed">Placed (Pending Assignment)</option>
                <option value="assigned">Assigned</option>
                <option value="picked_up">Items Picked Up</option>
                <option value="out_for_delivery">Out For Delivery</option>
                <option value="delivered">Delivered</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div style="display:flex;gap:10px;margin-top:1.5rem;">
            <button class="btn btn-primary" style="flex:1;justify-content:center;" onclick="submitStatusUpdate()">Update Status</button>
            <button class="btn btn-ghost" onclick="closeModal('statusModal')">Cancel</button>
        </div>
    </div>
</div>


<script>
const csrfToken = "<?= $csrfToken ?>";

function showToast(msg) {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span style="font-weight:700;">${msg}</span>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

function getStatusBadge(status) {
    const map = { 
        placed: 'b-amber', 
        assigned: 'b-blue', 
        picked_up: 'b-purple', 
        out_for_delivery: 'b-purple', 
        delivered: 'b-green', 
        cancelled: 'b-red' 
    };
    return `<span class="badge ${map[status] || 'b-amber'}">${status.toUpperCase().replace(/_/g,' ')}</span>`;
}

async function apiCall(endpoint, action, payload = {}) {
    payload.action = action;
    payload.csrf_token = csrfToken;
    try {
        const res = await fetch(endpoint, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        return await res.json();
    } catch(e) {
        return { success: false, message: 'Server error' };
    }
}

async function fetchOrders() {
    const status = document.getElementById('statusFilter').value;
    const tbody = document.getElementById('oList');
    tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem;">Loading orders...</td></tr>';
    
    const data = await apiCall('../api/marketplace_orders.php', 'get_orders', { filter: status });
    
    if(data.success) {
        if(data.orders.length === 0) {
            tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;color:var(--muted);padding:2rem;">No marketplace orders found.</td></tr>';
            return;
        }

        tbody.innerHTML = data.orders.map(o => {
            const items = o.items.map(i => `<div style="font-size:.8rem;color:var(--muted);margin-bottom:3px;">${i.quantity}x ${i.name} (${i.size}) - ₹${i.price}</div>`).join('');
            const payStatus = o.payment_status === 'paid' ? '<span style="color:var(--success);font-weight:800;">Paid</span>' : '<span style="color:var(--amber);font-weight:800;">Pending</span>';
            const payType = `<div style="font-size:.75rem;background:#f8fafc;padding:3px;border-radius:4px;display:inline-block;font-weight:600;border:1px solid var(--border);">${o.payment_type.toUpperCase()}</div>`;
            const isCompleted = o.status === 'delivered' || o.status === 'cancelled';
            
            const dpName = o.delivery_name || '<i style="color:#94a3b8;">Unassigned</i>';

            return `
                <tr>
                    <td><b>#${o.id}</b><div style="font-size:.7rem;color:var(--muted);margin-top:3px;">${new Date(o.created_at).toLocaleDateString()}</div></td>
                    <td><b>${o.user_name}</b><div style="font-size:.75rem;color:var(--muted);margin-top:2px;">${o.user_phone}</div></td>
                    <td>${items}</td>
                    <td><b>₹${o.total_amount}</b></td>
                    <td>${payStatus}<div style="margin-top:4px;">${payType}</div></td>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px;font-size:.85rem;font-weight:600;">
                            ${dpName}
                            ${!isCompleted ? `<button class="btn-sm btn-ghost" title="Reassign" onclick="openAssignModal(${o.id}, ${o.delivery_id || 'null'})"><i class="material-icons-outlined" style="font-size:1rem;">edit</i></button>` : ''}
                        </div>
                    </td>
                    <td>${getStatusBadge(o.status)}</td>
                    <td>
                        <button class="btn-sm btn-outline" onclick="openStatusModal(${o.id}, '${o.status}')">Update Status</button>
                    </td>
                </tr>
            `;
        }).join('');
    } else {
        showToast(data.message);
    }
}

function openAssignModal(orderId, currentDeliveryId) {
    document.getElementById('assignOrderId').value = orderId;
    document.getElementById('lblOrderId').textContent = orderId;
    document.getElementById('assignPartnerId').value = currentDeliveryId || '';
    openModal('assignModal');
}

async function submitAssignment() {
    const orderId = document.getElementById('assignOrderId').value;
    const deliveryId = document.getElementById('assignPartnerId').value;
    
    if(!deliveryId) { showToast('Please select a partner'); return; }

    const data = await apiCall('../api/update_marketplace_status.php', 'assign_delivery', {
        order_id: orderId,
        delivery_id: deliveryId
    });

    if(data.success) {
        showToast('Assigned successfully');
        closeModal('assignModal');
        fetchOrders();
    } else {
        showToast(data.message);
    }
}

function openStatusModal(orderId, currentStatus) {
    document.getElementById('statusOrderId').value = orderId;
    document.getElementById('lblStatusOrderId').textContent = orderId;
    document.getElementById('newStatusSelect').value = currentStatus;
    openModal('statusModal');
}

async function submitStatusUpdate() {
    const orderId = document.getElementById('statusOrderId').value;
    const newStatus = document.getElementById('newStatusSelect').value;

    const data = await apiCall('../api/update_marketplace_status.php', 'update_status', {
        order_id: orderId,
        status: newStatus
    });

    if(data.success) {
        showToast('Status updated successfully');
        closeModal('statusModal');
        fetchOrders();
    } else {
        showToast(data.message);
    }
}

window.onload = () => {
    fetchOrders();
};
</script>
</body>
</html>
