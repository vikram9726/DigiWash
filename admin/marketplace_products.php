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
        
        /* Sidebar */
        .sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:1.5rem 1rem;position:sticky;top:0;height:100vh;overflow-y:auto;}
        .sidebar-brand{display:flex;align-items:center;gap:10px;padding:0.5rem 0.75rem 1.5rem;color:white;font-size:1.2rem;font-weight:800;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;}
        .sidebar-brand i{color:#6366f1;}
        .menu-item{display:flex;align-items:center;gap:12px;padding:0.75rem 1rem;border-radius:10px;color:#94a3b8;font-weight:600;font-size:0.9rem;cursor:pointer;transition:all 0.2s;text-decoration:none;margin-bottom:5px;}
        .menu-item:hover{background:rgba(255,255,255,0.07);color:white;}
        .menu-item.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;box-shadow:0 4px 15px rgba(236,72,153,0.4);}
        .menu-item i{font-size:1.2rem;}

        /* Main */
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
        .btn-sm{padding:0.4rem 0.8rem;font-size:0.8rem;}
        .btn:hover{filter:brightness(0.92);transform:translateY(-1px);}

        /* Table */
        .tbl-wrap{overflow-x:auto;}
        table{width:100%;border-collapse:collapse;font-size:0.875rem;}
        th{background:#f8fafc;color:#64748b;font-weight:700;font-size:0.75rem;text-transform:uppercase;padding:0.75rem 1rem;text-align:left;border-bottom:1.5px solid var(--border);}
        td{padding:0.875rem 1rem;border-bottom:1px solid #f1f5f9;vertical-align:middle;}
        tr:hover td{background:#f8fafc;}

        /* Badge */
        .badge{display:inline-flex;align-items:center;padding:0.2rem 0.65rem;border-radius:999px;font-size:0.75rem;font-weight:700;}
        .b-green{background:#dcfce7;color:#16a34a;}
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
        .form-group input,.form-group select,.form-group textarea{width:100%;padding:0.55rem 0.8rem;border:1.5px solid var(--border);border-radius:9px;font-size:0.875rem;outline:none;font-family:inherit;transition:0.2s;}
        .form-group input:focus{border-color:var(--primary);}
        
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
        
        <div class="menu-item active">
            <i class="material-icons-outlined">storefront</i> Store Products
        </div>
        <a href="marketplace_orders.php" class="menu-item">
            <i class="material-icons-outlined">shopping_cart_checkout</i> Store Orders
        </a>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="page-title">Marketplace <span>Products</span></div>
            <button class="btn btn-primary" onclick="openModal('prodModal')">+ Add Product</button>
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
                            <th>Size</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="pList">
                        <tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">Loading products...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="prodModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('prodModal')">✕</button>
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
                    <select id="p_category" required>
                        <option value="Bedsheet">Bedsheet</option>
                        <option value="Pillow">Pillow</option>
                        <option value="Towel">Towel</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Size *</label>
                    <select id="p_size" required>
                        <option value="Single">Single</option>
                        <option value="Double">Double</option>
                        <option value="King">King</option>
                        <option value="Standard">Standard</option>
                        <option value="Free Size">Free Size</option>
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                <div class="form-group">
                    <label>Price (₹) *</label>
                    <input type="number" id="p_price" required min="1" step="0.01">
                </div>
                <div class="form-group">
                    <label>Stock Quantity *</label>
                    <input type="number" id="p_stock" required min="0" value="10">
                </div>
            </div>
            <div class="form-group">
                <label>Product Image (Optional)</label>
                <input type="file" id="p_image" accept="image/*">
                <div id="imgPreviewCont" style="margin-top:8px;display:none;">
                    <img id="p_preview" style="height:80px;border-radius:8px;object-fit:cover;">
                </div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:1.5rem;">
                <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;" id="btnSave">Save Product</button>
                <button type="button" class="btn btn-ghost" onclick="closeModal('prodModal')">Cancel</button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = "<?= $csrfToken ?>";
let products = [];

function showToast(msg) {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span style="font-weight:700;">${msg}</span>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

function openModal(id) { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); document.getElementById('prodForm').reset(); document.getElementById('p_id').value = ''; document.getElementById('imgPreviewCont').style.display='none'; document.getElementById('modalTitle').textContent='Add Marketplace Product'; }

async function fetchProducts() {
    try {
        const res = await fetch('../api/marketplace_products.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'get_products', active_only: false, csrf_token: csrfToken })
        });
        const data = await res.json();
        if(data.success) {
            products = data.products;
            renderProducts();
        }
    } catch(e) {
        showToast('Failed to load products');
    }
}

function renderProducts() {
    const tbody = document.getElementById('pList');
    if(products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:2rem;">No products found.</td></tr>';
        return;
    }
    
    tbody.innerHTML = products.map(p => {
        const img = p.image ? `<img src="../${p.image}" style="width:50px;height:50px;object-fit:cover;border-radius:8px;">` : `<div style="width:50px;height:50px;background:#f1f5f9;border-radius:8px;display:flex;align-items:center;justify-content:center;color:var(--muted);"><i class="material-icons-outlined">image</i></div>`;
        const statusBadge = p.status === 'active' ? '<span class="badge b-green">Active</span>' : '<span class="badge b-red">Inactive</span>';
        
        return `
            <tr>
                <td><b>#${p.id}</b></td>
                <td>${img}</td>
                <td><b style="color:var(--text);">${p.name}</b></td>
                <td><span style="background:#f1f5f9;padding:3px 8px;border-radius:6px;font-size:.75rem;font-weight:700;color:var(--muted);">${p.category}</span></td>
                <td>${p.size}</td>
                <td><b>₹${p.price}</b></td>
                <td>${p.stock > 0 ? `<span style="color:var(--success);font-weight:800;">${p.stock}</span>` : `<span style="color:var(--danger);font-weight:800;">0 (Out)</span>`}</td>
                <td>${statusBadge}</td>
                <td>
                    <div style="display:flex;gap:5px;">
                        <button class="btn-sm btn-outline" onclick="editProduct(${p.id})">Edit</button>
                        <button class="btn-sm ${p.status==='active'?'btn-danger':'btn-success'}" onclick="toggleStatus(${p.id})">${p.status==='active'?'Disable':'Enable'}</button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');
}

document.getElementById('prodForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const id = document.getElementById('p_id').value;
    const name = document.getElementById('p_name').value;
    const category = document.getElementById('p_category').value;
    const size = document.getElementById('p_size').value;
    const price = document.getElementById('p_price').value;
    const stock = document.getElementById('p_stock').value;
    const imageInput = document.getElementById('p_image');

    const formData = new FormData();
    formData.append('action', id ? 'update_product' : 'create_product');
    formData.append('csrf_token', csrfToken);
    if(id) formData.append('id', id);
    formData.append('name', name);
    formData.append('category', category);
    formData.append('size', size);
    formData.append('price', price);
    formData.append('stock', stock);
    
    if(imageInput.files.length > 0) {
        formData.append('image', imageInput.files[0]);
    }

    const btn = document.getElementById('btnSave');
    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const res = await fetch('../api/marketplace_products.php', {
            method: 'POST',
            body: formData // sending as FormData due to file upload
        });
        const data = await res.json();
        if(data.success) {
            showToast('Product saved successfully');
            closeModal('prodModal');
            fetchProducts();
        } else {
            showToast(data.message || 'Error saving product');
        }
    } catch(e) {
        showToast('Server error');
    }
    btn.disabled = false;
    btn.textContent = 'Save Product';
});

function editProduct(id) {
    const p = products.find(x => x.id === id);
    if(!p) return;
    document.getElementById('modalTitle').textContent = 'Edit Product';
    document.getElementById('p_id').value = p.id;
    document.getElementById('p_name').value = p.name;
    document.getElementById('p_category').value = p.category;
    document.getElementById('p_size').value = p.size;
    document.getElementById('p_price').value = p.price;
    document.getElementById('p_stock').value = p.stock;
    
    if(p.image) {
        const c = document.getElementById('imgPreviewCont');
        c.style.display = 'block';
        document.getElementById('p_preview').src = '../' + p.image;
    }
    openModal('prodModal');
}

async function toggleStatus(id) {
    try {
        const res = await fetch('../api/marketplace_products.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'toggle_product', id, csrf_token: csrfToken })
        });
        const data = await res.json();
        if(data.success) {
            showToast('Product status updated');
            fetchProducts();
        } else {
            showToast(data.message);
        }
    } catch(e) {
        showToast('Server error');
    }
}

window.onload = () => {
    fetchProducts();
};
</script>
</body>
</html>
