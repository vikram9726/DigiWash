<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php'); exit;
}
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marketplace Products - Admin</title>
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
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;overflow-x:hidden;}
        .admin-wrap{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh;}
        
        .sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:1.5rem 1rem;position:sticky;top:0;height:100vh;overflow-y:auto;}
        .sidebar-brand{display:flex;align-items:center;gap:10px;padding:0.5rem 0.75rem 1.5rem;color:white;font-size:1.2rem;font-weight:800;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;}
        .sidebar-brand i{color:#6366f1;}
        .menu-item{display:flex;align-items:center;gap:12px;padding:0.75rem 1rem;border-radius:10px;color:#94a3b8;font-weight:600;font-size:0.9rem;cursor:pointer;transition:all 0.2s;text-decoration:none;margin-bottom:5px;}
        .menu-item:hover{background:rgba(255,255,255,0.07);color:white;}
        .menu-item.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;box-shadow:0 4px 15px rgba(236,72,153,0.4);}
        .menu-item i{font-size:1.2rem;}

        .main-content{padding:2rem;}
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
        .btn-sm{padding:0.35rem 0.7rem;font-size:0.78rem;}
        .btn:hover{filter:brightness(0.92);transform:translateY(-1px);}

        .tbl-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:0.875rem;}
        th{background:#f8fafc;color:#64748b;font-weight:700;font-size:0.75rem;text-transform:uppercase;padding:0.75rem 1rem;text-align:left;border-bottom:1.5px solid var(--border);}
        td{padding:0.875rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tr:hover td{background:#f8fafc;}

        .badge{display:inline-flex;align-items:center;padding:0.2rem 0.65rem;border-radius:999px;font-size:0.75rem;font-weight:700;}
        .b-green{background:#dcfce7;color:#16a34a;}
        .b-red{background:#fee2e2;color:#dc2626;}
        
        .modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1000;align-items:center;justify-content:center;}
        .modal-overlay.open{display:flex;}
        .modal-box{background:white;border-radius:20px;padding:2rem;width:90%;max-width:620px;max-height:92vh;overflow-y:auto;position:relative;animation:fadeUp 0.25s ease;}
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        .modal-close{position:absolute;top:1rem;right:1rem;background:#f1f5f9;border:none;border-radius:8px;width:32px;height:32px;cursor:pointer;font-size:1rem;color:var(--muted);}
        .modal-title{font-size:1.2rem;font-weight:800;color:var(--text);margin-bottom:1.2rem;}

        .form-group{margin-bottom:0.9rem;}
        .form-group label{font-size:0.8rem;font-weight:700;color:#475569;display:block;margin-bottom:5px;}
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:0.55rem 0.8rem;border:1.5px solid var(--border);border-radius:9px;font-size:0.875rem;outline:none;font-family:inherit;transition:0.2s;}
        .form-group input:focus{border-color:var(--primary);}
        
        /* Width builder */
        .width-section{background:#f8fafc;border:1.5px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:1rem;}
        .width-section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;}
        .width-section-title{font-weight:800;font-size:.85rem;color:var(--text);}
        .width-row{display:grid;grid-template-columns:1fr 1fr auto;gap:8px;align-items:center;margin-bottom:6px;background:white;padding:8px 10px;border-radius:8px;border:1px solid var(--border);}
        .width-row input{border:1px solid var(--border);border-radius:7px;padding:.4rem .6rem;font-size:.82rem;font-family:inherit;outline:none;}
        .width-row input:focus{border-color:var(--primary);}
        .remove-width-btn{width:28px;height:28px;border:none;background:#fee2e2;color:#dc2626;border-radius:6px;cursor:pointer;font-weight:900;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
        
        #toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:10px;}
        .toast{background:white;border-left:4px solid var(--primary);padding:1rem 1.2rem;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.15);animation:slideIn .3s ease;font-family:'Inter',sans-serif;}
        @keyframes slideIn{from{transform:translateX(100%)}to{transform:translateX(0)}}

        /* Staff Requests Panel */
        .request-card{background:#f8fafc;border:1px solid var(--border);border-radius:12px;padding:1rem;margin-bottom:10px;}
        .request-meta{font-size:.8rem;color:var(--muted);margin-top:4px;}
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
        
        <div class="menu-item active" onclick="showTab('products')">
            <i class="material-icons-outlined">storefront</i> Store Products
        </div>
        <a href="marketplace_orders.php" class="menu-item">
            <i class="material-icons-outlined">shopping_cart_checkout</i> Store Orders
        </a>
        <div class="menu-item" onclick="showTab('requests')">
            <i class="material-icons-outlined">support_agent</i> Staff Requests
            <span id="reqBadge" style="margin-left:auto;background:#ef4444;color:white;padding:1px 7px;border-radius:10px;font-size:.7rem;display:none;"></span>
        </div>
    </aside>

    <main class="main-content">
        <!-- PRODUCTS TAB -->
        <div id="tab-products">
            <div class="top-bar">
                <div class="page-title">Marketplace <span>Products</span></div>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="material-icons-outlined">add</i> Add Product</button>
            </div>

            <div class="panel">
                <div class="tbl-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Product Info</th>
                                <th>Category</th>
                                <th>Base Size</th>
                                <th>Base Price</th>
                                <th>Widths</th>
                                <th>Stock</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="pList">
                            <tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem;">Loading products...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- STAFF REQUESTS TAB -->
        <div id="tab-requests" style="display:none;">
            <div class="top-bar">
                <div class="page-title">Staff <span>Requests</span></div>
                <button class="btn btn-ghost" onclick="loadRequests()"><i class="material-icons-outlined">refresh</i> Refresh</button>
            </div>
            <div class="panel" id="requestsContainer">
                <div style="text-align:center;color:var(--muted);padding:2rem;">Loading...</div>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Product Modal -->
<div class="modal-overlay" id="prodModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <div class="modal-title" id="modalTitle">Add Marketplace Product</div>
        <form id="prodForm">
            <input type="hidden" id="p_id">

            <div class="form-group">
                <label>Product Name *</label>
                <input type="text" id="p_name" required placeholder="e.g. Premium Cotton Bedsheet">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="form-group">
                    <label>Category *</label>
                    <input type="text" id="p_category" required placeholder="e.g. Bedsheet, Towel…" list="catOptions">
                    <datalist id="catOptions">
                        <option value="Bedsheet">
                        <option value="Pillow">
                        <option value="Towel">
                        <option value="Detergent">
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Base Size / Unit *</label>
                    <input type="text" id="p_size" required placeholder="e.g. Per Meter, King…" list="sizeOptions">
                    <datalist id="sizeOptions">
                        <option value="Single">
                        <option value="Double">
                        <option value="King">
                        <option value="Standard">
                        <option value="Free Size">
                        <option value="Per Meter">
                        <option value="Per Kg">
                        <option value="Per Piece">
                    </datalist>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="form-group">
                    <label>Base Price (₹) <small style="color:var(--muted);font-weight:500;">(used if no widths)</small></label>
                    <input type="number" id="p_price" min="0" step="0.01" value="0" placeholder="0">
                </div>
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" id="p_stock" required min="0" value="10">
                </div>
            </div>

            <!-- Width Options Builder -->
            <div class="width-section">
                <div class="width-section-header">
                    <div>
                        <div class="width-section-title">📐 Width Options (e.g. for Per-Meter pricing)</div>
                        <div style="font-size:.75rem;color:var(--muted);margin-top:2px;">Add multiple widths — user selects one and enters their desired length.</div>
                    </div>
                    <button type="button" class="btn btn-primary btn-sm" onclick="addWidthRow()">
                        <i class="material-icons-outlined" style="font-size:.9rem;">add</i> Add Width
                    </button>
                </div>
                <div id="widthRows">
                    <!-- Width rows injected here -->
                </div>
                <div id="widthEmptyMsg" style="text-align:center;color:var(--muted);font-size:.8rem;padding:.5rem 0;">No width options added. Product will use base price.</div>
            </div>

            <div class="form-group">
                <label>Product Image (Optional)</label>
                <input type="file" id="p_image" accept="image/*" onchange="previewImage(this)">
                <div id="imgPreviewCont" style="margin-top:8px;display:none;">
                    <img id="p_preview" style="height:80px;border-radius:8px;object-fit:cover;">
                </div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;" id="btnSave">Save Product</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal()">Cancel</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirm Modal -->
<div class="modal-overlay" id="deleteModal">
    <div class="modal-box" style="text-align:center;max-width:400px;padding:2.5rem 2rem;">
        <i class="material-icons-outlined" style="font-size:3.5rem;color:var(--danger);margin-bottom:1rem;">delete_forever</i>
        <div class="modal-title" style="font-size:1.3rem;">Delete Product?</div>
        <div style="font-size:.9rem;color:var(--muted);margin-top:.5rem;">Delete "<b id="delPrdName"></b>"? This also removes its width options. Cannot be undone.</div>
        <div style="display:flex;gap:.75rem;margin-top:1.5rem;">
            <button class="btn btn-danger" style="flex:1;justify-content:center;" onclick="confirmDelete()">Yes, Delete</button>
            <button class="btn btn-ghost" style="flex:1;justify-content:center;" onclick="document.getElementById('deleteModal').classList.remove('open')">Cancel</button>
        </div>
    </div>
</div>

<!-- Reply to Staff Request Modal -->
<div class="modal-overlay" id="replyModal">
    <div class="modal-box" style="max-width:460px;">
        <button class="modal-close" onclick="document.getElementById('replyModal').classList.remove('open')">✕</button>
        <div class="modal-title">Reply to Staff Request</div>
        <input type="hidden" id="replyReqId">
        <div class="form-group">
            <label>Admin Note / Response</label>
            <textarea id="replyNote" rows="4" style="width:100%;padding:.6rem .8rem;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;resize:vertical;" placeholder="Type your response..."></textarea>
        </div>
        <div class="form-group">
            <label>Update Status</label>
            <select id="replyStatus" class="form-group" style="width:100%;padding:.55rem .8rem;border:1.5px solid var(--border);border-radius:9px;font-family:inherit;">
                <option value="seen">Seen (In Progress)</option>
                <option value="resolved">Resolved</option>
            </select>
        </div>
        <button class="btn btn-primary" style="width:100%;justify-content:center;" onclick="sendReply()">Send Reply</button>
    </div>
</div>

<script>
const csrfToken = "<?= $csrfToken ?>";
let products = [];
let pendingDeleteId = null;

function showToast(msg) {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span style="font-weight:700;">${msg}</span>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

function showTab(tab) {
    document.getElementById('tab-products').style.display = tab === 'products' ? 'block' : 'none';
    document.getElementById('tab-requests').style.display = tab === 'requests' ? 'block' : 'none';
    document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
    if (tab === 'requests') {
        document.querySelectorAll('.menu-item')[2].classList.add('active');
        loadRequests();
    } else {
        document.querySelectorAll('.menu-item')[0].classList.add('active');
    }
}

// ─── WIDTH ROWS ───────────────────────────────────────────────────────────────
function addWidthRow(label = '', pricePerMeter = '') {
    document.getElementById('widthEmptyMsg').style.display = 'none';
    const row = document.createElement('div');
    row.className = 'width-row';
    row.innerHTML = `
        <input type="text" placeholder="Width label (e.g. 36 inch, 60 inch)" value="${label}" class="wr-label">
        <input type="number" placeholder="Price/meter (₹)" min="0.01" step="0.01" value="${pricePerMeter}" class="wr-price">
        <button type="button" class="remove-width-btn" onclick="removeWidthRow(this)">✕</button>
    `;
    document.getElementById('widthRows').appendChild(row);
}

function removeWidthRow(btn) {
    btn.closest('.width-row').remove();
    if (document.getElementById('widthRows').children.length === 0) {
        document.getElementById('widthEmptyMsg').style.display = 'block';
    }
}

function getWidths() {
    const rows = document.querySelectorAll('#widthRows .width-row');
    const widths = [];
    rows.forEach(row => {
        const label = row.querySelector('.wr-label').value.trim();
        const price = parseFloat(row.querySelector('.wr-price').value);
        if (label && price > 0) widths.push({ label, price_per_meter: price });
    });
    return widths;
}

function clearWidths() {
    document.getElementById('widthRows').innerHTML = '';
    document.getElementById('widthEmptyMsg').style.display = 'block';
}

// ─── MODAL ────────────────────────────────────────────────────────────────────
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add Marketplace Product';
    document.getElementById('prodForm').reset();
    document.getElementById('p_id').value = '';
    document.getElementById('imgPreviewCont').style.display = 'none';
    document.getElementById('p_stock').value = '10';
    document.getElementById('p_price').value = '0';
    clearWidths();
    document.getElementById('prodModal').classList.add('open');
}

function closeModal() {
    document.getElementById('prodModal').classList.remove('open');
}

function previewImage(input) {
    if (!input.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        const img = document.getElementById('p_preview');
        img.src = e.target.result;
        document.getElementById('imgPreviewCont').style.display = 'block';
    };
    reader.readAsDataURL(input.files[0]);
}

// ─── FETCH PRODUCTS ───────────────────────────────────────────────────────────
async function fetchProducts() {
    try {
        const res = await fetch('../api/marketplace_products.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_products', active_only: false, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
            products = data.products;
            renderProducts();
        }
    } catch(e) {
        showToast('Failed to load products');
    }
}

function renderProducts() {
    const tbody = document.getElementById('pList');
    if (products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="10" style="text-align:center;color:var(--muted);padding:2rem;">No products found.</td></tr>';
        return;
    }
    tbody.innerHTML = products.map(p => {
        const img = p.image
            ? `<img src="../${p.image}" style="width:50px;height:50px;object-fit:cover;border-radius:8px;">`
            : `<div style="width:50px;height:50px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--muted);"><i class="material-icons-outlined">image</i></div>`;
        const statusBadge = p.status === 'active'
            ? '<span class="badge b-green">Active</span>'
            : '<span class="badge b-red">Inactive</span>';
        const widthsHtml = p.widths && p.widths.length
            ? p.widths.map(w => `<span style="background:#ede9fe;color:#6d28d9;padding:2px 6px;border-radius:5px;font-size:.72rem;font-weight:700;display:inline-block;margin:1px;">${w.label} — ₹${w.price_per_meter}/m</span>`).join('')
            : '<span style="color:var(--muted);font-size:.78rem;">—</span>';
        return `
            <tr>
                <td><b>#${p.id}</b></td>
                <td>${img}</td>
                <td><b style="color:var(--text);">${p.name}</b></td>
                <td><span style="background:#f1f5f9;padding:3px 8px;border-radius:6px;font-size:.75rem;font-weight:700;color:var(--muted);">${p.category}</span></td>
                <td>${p.size}</td>
                <td><b>${p.price > 0 ? '₹' + p.price : '—'}</b></td>
                <td style="max-width:200px;">${widthsHtml}</td>
                <td>${p.stock > 0 ? `<span style="color:var(--success);font-weight:800;">${p.stock}</span>` : `<span style="color:var(--danger);font-weight:800;">0 (Out)</span>`}</td>
                <td>${statusBadge}</td>
                <td>
                    <div style="display:flex;gap:5px;flex-wrap:wrap;">
                        <button class="btn btn-sm btn-outline" onclick="editProduct(${p.id})">Edit</button>
                        <button class="btn btn-sm ${p.status === 'active' ? 'btn-ghost' : 'btn-success'}" onclick="toggleStatus(${p.id})">${p.status === 'active' ? 'Disable' : 'Enable'}</button>
                        <button class="btn btn-sm btn-danger" onclick="openDelete(${p.id}, '${p.name.replace(/'/g, '')}')">Delete</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

function editProduct(id) {
    const p = products.find(x => x.id === id);
    if (!p) return;
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_name').value = p.name;
    document.getElementById('p_category').value = p.category;
    document.getElementById('p_size').value = p.size;
    document.getElementById('p_price').value = p.price;
    document.getElementById('p_stock').value = p.stock;
    clearWidths();
    (p.widths || []).forEach(w => addWidthRow(w.label, w.price_per_meter));
    if (p.image) {
        const c = document.getElementById('imgPreviewCont');
        c.style.display = 'block';
        document.getElementById('p_preview').src = '../' + p.image;
    } else {
        document.getElementById('imgPreviewCont').style.display = 'none';
    }
    document.getElementById('prodModal').classList.add('open');
}

// ─── FORM SUBMIT ──────────────────────────────────────────────────────────────
document.getElementById('prodForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id       = document.getElementById('p_id').value;
    const name     = document.getElementById('p_name').value;
    const category = document.getElementById('p_category').value;
    const size     = document.getElementById('p_size').value;
    const price    = document.getElementById('p_price').value;
    const stock    = document.getElementById('p_stock').value;
    const widths   = getWidths();

    const formData = new FormData();
    formData.append('action', id ? 'update_product' : 'create_product');
    formData.append('csrf_token', csrfToken);
    if (id) formData.append('product_id', id);
    formData.append('name', name);
    formData.append('category', category);
    formData.append('size', size);
    formData.append('price', price);
    formData.append('stock', stock);
    formData.append('widths', JSON.stringify(widths));

    const imgFile = document.getElementById('p_image').files[0];
    if (imgFile) formData.append('image', imgFile);

    const btn = document.getElementById('btnSave');
    btn.disabled = true; btn.textContent = 'Saving...';

    try {
        const res  = await fetch('../api/marketplace_products.php', { method: 'POST', body: formData });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Product saved successfully');
            closeModal();
            fetchProducts();
        } else {
            showToast('❌ ' + (data.message || 'Error saving product'));
        }
    } catch (e) {
        showToast('❌ Server error');
    }
    btn.disabled = false; btn.textContent = 'Save Product';
});

// ─── TOGGLE / DELETE ──────────────────────────────────────────────────────────
async function toggleStatus(id) {
    try {
        const res  = await fetch('../api/marketplace_products.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'toggle_product', product_id: id, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) { showToast('Product status updated'); fetchProducts(); }
        else showToast(data.message);
    } catch (e) { showToast('Server error'); }
}

function openDelete(id, name) {
    pendingDeleteId = id;
    document.getElementById('delPrdName').textContent = name;
    document.getElementById('deleteModal').classList.add('open');
}

async function confirmDelete() {
    document.getElementById('deleteModal').classList.remove('open');
    if (!pendingDeleteId) return;
    try {
        const res  = await fetch('../api/marketplace_products.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_product', product_id: pendingDeleteId, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) { showToast('🗑️ Product deleted'); fetchProducts(); }
        else showToast('❌ ' + data.message);
    } catch (e) { showToast('Server error'); }
    pendingDeleteId = null;
}

// ─── STAFF REQUESTS ───────────────────────────────────────────────────────────
let deliveryPartners = [];

async function loadPartners() {
    if (deliveryPartners.length) return;
    try {
        const res  = await fetch('../api/staff_requests.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_partners_for_assign', csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) deliveryPartners = data.partners || [];
    } catch(e) {}
}

async function loadRequests() {
    const container = document.getElementById('requestsContainer');
    container.innerHTML = '<div style="text-align:center;color:var(--muted);padding:2rem;">Loading...</div>';
    await loadPartners();
    try {
        const res  = await fetch('../api/staff_requests.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_requests', csrf_token: csrfToken })
        });
        const data = await res.json();
        if (!data.success || !data.requests.length) {
            container.innerHTML = `<div style="text-align:center;color:var(--muted);padding:3rem;">
                <i class="material-icons-outlined" style="font-size:3rem;display:block;margin-bottom:.5rem;color:#cbd5e1;">support_agent</i>
                No staff requests yet.</div>`;
            document.getElementById('reqBadge').style.display = 'none';
            return;
        }
        const pending = data.requests.filter(r => r.status === 'pending').length;
        const badge   = document.getElementById('reqBadge');
        if (pending > 0) { badge.textContent = pending; badge.style.display = 'inline-block'; }
        else badge.style.display = 'none';

        const partnerOptions = deliveryPartners.map(p =>
            `<option value="${p.id}">${p.name} (${p.phone || '—'}) — ${p.is_online ? '🟢 Online' : '⚫ Offline'} · ${p.current_orders} active</option>`
        ).join('');

        container.innerHTML = data.requests.map(r => {
            const sc = { pending:'background:#fef3c7;color:#92400e', seen:'background:#dbeafe;color:#1e40af', resolved:'background:#dcfce7;color:#166534' }[r.status] || 'background:#f1f5f9;color:#64748b';
            return `
            <div class="request-card">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;">
                    <!-- Left: User info + message -->
                    <div style="flex:1;min-width:220px;">
                        <div style="background:#f8fafc;border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:10px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;color:white;font-weight:900;font-size:1rem;flex-shrink:0;">${(r.user_name||'?').charAt(0).toUpperCase()}</div>
                                <div>
                                    <div style="font-weight:800;font-size:.95rem;color:var(--text);">${r.user_name || 'Unknown'}</div>
                                    <div style="font-size:.78rem;color:var(--muted);">${r.user_phone || ''}${r.user_email ? ' · ' + r.user_email : ''}</div>
                                </div>
                            </div>
                            ${r.shop_address ? `<div style="margin-top:7px;font-size:.78rem;color:var(--muted);padding-top:7px;border-top:1px solid var(--border);"><i class="material-icons-outlined" style="font-size:.85rem;vertical-align:middle;">place</i> ${r.shop_address}${r.market_name ? ' · <b>' + r.market_name + '</b>' : ''}</div>` : ''}
                        </div>

                        <div style="font-size:.88rem;color:var(--text);line-height:1.6;background:white;border:1px solid var(--border);border-radius:8px;padding:10px 12px;margin-bottom:8px;">
                            <b style="color:var(--muted);font-size:.75rem;text-transform:uppercase;letter-spacing:.5px;">Request</b><br>
                            ${r.message || '(No message)'}
                        </div>

                        ${r.admin_note ? `<div style="background:#f0fdf4;border-left:3px solid var(--success);padding:7px 10px;border-radius:6px;font-size:.82rem;color:#166534;margin-bottom:6px;"><i class="material-icons-outlined" style="font-size:.9rem;vertical-align:middle;">chat</i> <b>Your reply:</b> ${r.admin_note}</div>` : ''}
                        ${r.delivery_name ? `<div style="background:#eff6ff;border-left:3px solid #3b82f6;padding:7px 10px;border-radius:6px;font-size:.82rem;color:#1e40af;margin-bottom:6px;"><i class="material-icons-outlined" style="font-size:.9rem;vertical-align:middle;">delivery_dining</i> <b>Assigned:</b> ${r.delivery_name} · ${r.delivery_phone || ''}</div>` : ''}

                        <div class="request-meta">Submitted: ${new Date(r.created_at).toLocaleString()}</div>
                    </div>

                    <!-- Right: Status + Actions -->
                    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;min-width:165px;">
                        <span style="background:${sc};padding:3px 10px;border-radius:20px;font-size:.75rem;font-weight:800;">${r.status.toUpperCase()}</span>
                        <button class="btn btn-sm btn-primary" onclick="openReply(${r.id})"><i class="material-icons-outlined" style="font-size:.9rem;">chat</i> Reply</button>

                        ${partnerOptions ? `<div style="width:100%;">
                            <select id="pSel-${r.id}" style="width:100%;font-size:.75rem;padding:.35rem .5rem;border:1.5px solid var(--border);border-radius:8px;font-family:inherit;outline:none;">
                                <option value="">Assign delivery partner…</option>
                                ${partnerOptions}
                            </select>
                            <button class="btn btn-sm btn-success" style="width:100%;justify-content:center;margin-top:5px;" onclick="assignDelivery(${r.id})">
                                <i class="material-icons-outlined" style="font-size:.85rem;">delivery_dining</i> Assign
                            </button>
                        </div>` : ''}
                    </div>
                </div>
            </div>`;
        }).join('');
    } catch(e) {
        container.innerHTML = '<div style="color:var(--danger);padding:1rem;">Error loading requests.</div>';
    }
}

function openReply(id) {
    document.getElementById('replyReqId').value  = id;
    document.getElementById('replyNote').value   = '';
    document.getElementById('replyStatus').value = 'seen';
    document.getElementById('replyModal').classList.add('open');
}

async function sendReply() {
    const id     = document.getElementById('replyReqId').value;
    const note   = document.getElementById('replyNote').value.trim();
    const status = document.getElementById('replyStatus').value;
    if (!note) { showToast('Please type a reply.'); return; }
    try {
        const res  = await fetch('../api/staff_requests.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'reply_request', request_id: id, admin_note: note, status, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) {
            showToast('✅ Reply sent & user notified');
            document.getElementById('replyModal').classList.remove('open');
            loadRequests();
        } else showToast('❌ ' + data.message);
    } catch(e) { showToast('Server error'); }
}

async function assignDelivery(rid) {
    const sel        = document.getElementById('pSel-' + rid);
    const deliveryId = sel ? sel.value : '';
    if (!deliveryId) { showToast('Please select a delivery partner first.'); return; }
    try {
        const res  = await fetch('../api/staff_requests.php', {
            method: 'POST', headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'assign_delivery', request_id: rid, delivery_id: deliveryId, csrf_token: csrfToken })
        });
        const data = await res.json();
        if (data.success) { showToast('✅ Delivery partner assigned — user notified!'); loadRequests(); }
        else showToast('❌ ' + data.message);
    } catch(e) { showToast('Server error'); }
}

window.onload = () => {
    fetchProducts();
    fetch('../api/staff_requests.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'get_requests', csrf_token: csrfToken })
    }).then(r => r.json()).then(data => {
        if (data.success) {
            const pending = (data.requests || []).filter(r => r.status === 'pending').length;
            if (pending > 0) {
                const badge = document.getElementById('reqBadge');
                badge.textContent = pending;
                badge.style.display = 'inline-block';
            }
        }
    }).catch(() => {});
};

</script>
</body>
</html>
