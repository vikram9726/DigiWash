<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../index.php'); exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();
$needsProfileSetup = empty($user['name']) || empty($user['shop_address']) || empty($user['market_id']);

$csrfToken  = $_SESSION['csrf_token'] ?? '';
$userName   = htmlspecialchars($user['name'] ?? 'User');
$userPhone  = htmlspecialchars($user['phone'] ?? '');

$payLaterPlan = $user['pay_later_plan'] ?? 'NONE';
$payLaterStatus = $user['pay_later_status'] ?? 'locked';

// Fetch number of completed laundry orders to check eligibility
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
$stmt->execute([$_SESSION['user_id']]);
$completedLaundryOrders = (int)$stmt->fetchColumn();
$isEligibleForPayLater = ($completedLaundryOrders >= 4);

$wallet = ['credit_limit' => 2000, 'used_credit' => 0];
if ($isEligibleForPayLater) {
    $stmt = $pdo->prepare("SELECT credit_limit, used_credit FROM user_wallet WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $w = $stmt->fetch();
    if ($w) {
        $wallet['credit_limit'] = (float)$w['credit_limit'];
        $wallet['used_credit'] = (float)$w['used_credit'];
    }
}
$availableCredit = max(0, $wallet['credit_limit'] - $wallet['used_credit']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash — Marketplace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        :root{
            --bg:#f8fafc;
            --sidebar-bg:#0f172a;
            --card:white;
            --primary:#ec4899; /* Pinkish primary for marketplace to distinguish from laundry */
            --primary-d:#be185d;
            --success:#10b981;
            --danger:#ef4444;
            --text:#0f172a;
            --muted:#64748b;
            --border:#e2e8f0;
            --sidebar-w:240px;
            --radius:16px;
        }
        body{font-family:'Inter',sans-serif;background:var(--bg);color:var(--text);min-height:100vh;}
        .app-wrap{display:grid;grid-template-columns:var(--sidebar-w) 1fr;min-height:100vh;}

        /* Sidebar */
        .sidebar{background:var(--sidebar-bg);display:flex;flex-direction:column;padding:1.5rem 1rem;position:sticky;top:0;height:100vh;overflow-y:auto;}
        .sidebar-brand{display:flex;align-items:center;gap:10px;color:white;font-weight:900;font-size:1.15rem;padding:0.5rem 0.75rem 1.25rem;border-bottom:1px solid rgba(255,255,255,0.08);margin-bottom:0.75rem;}
        .sidebar-brand i{color:var(--primary);font-size:1.8rem;}
        .user-chip{display:flex;align-items:center;gap:10px;padding:0.75rem;background:rgba(255,255,255,0.06);border-radius:12px;margin-bottom:1rem;}
        .user-av{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;color:white;font-weight:900;font-size:1rem;}
        .user-info-name{color:white;font-weight:700;font-size:0.85rem;}
        .user-info-phone{color:#64748b;font-size:0.72rem;}
        .nav-item{display:flex;align-items:center;gap:12px;padding:0.7rem 1rem;border-radius:10px;color:#94a3b8;font-weight:600;font-size:0.875rem;cursor:pointer;transition:all 0.18s; text-decoration:none; margin-bottom:5px;}
        .nav-item:hover{background:rgba(255,255,255,0.06);color:white;}
        .nav-item.active{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;box-shadow:0 4px 12px rgba(236,72,153,0.35);}
        .nav-item i{font-size:1.2rem;}

        .main{padding:2rem;overflow-y:auto;}
        .page-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1.75rem;flex-wrap:wrap;gap:1rem;}
        .page-title{font-size:1.6rem;font-weight:900;color:var(--text);}
        .page-title span{color:var(--primary);}

        .section{display:none;}
        .section.active{display:block;animation:slideUp 0.3s ease;}
        @keyframes slideUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

        /* Filters */
        .filter-bar{display:flex;gap:10px;margin-bottom:1.5rem;flex-wrap:wrap;}
        .filter-select{padding:.6rem 1rem;border:1.5px solid var(--border);border-radius:10px;font-family:inherit;font-weight:600;color:var(--text);outline:none;}
        
        /* Products Grid */
        .products-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1.5rem;margin-bottom:1.5rem;}
        .product-card{background:white;border-radius:14px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.05);border:1.5px solid transparent;transition:all .2s;display:flex;flex-direction:column;}
        .product-card:hover{transform:translateY(-3px);box-shadow:0 8px 25px rgba(0,0,0,0.08);border-color:var(--primary);}
        .prod-img{height:160px;background:#f8fafc;display:flex;align-items:center;justify-content:center;overflow:hidden;}
        .prod-img img{width:100%;height:100%;object-fit:cover;}
        .prod-img i{font-size:4rem;color:#cbd5e1;}
        .prod-body{padding:1.2rem;flex:1;display:flex;flex-direction:column;}
        .prod-cat{font-size:.7rem;font-weight:800;color:var(--primary);text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;}
        .prod-name{font-weight:800;font-size:1.05rem;color:var(--text);margin-bottom:4px;}
        .prod-size{font-size:.8rem;color:var(--muted);margin-bottom:10px;}
        .prod-price{font-size:1.2rem;font-weight:900;color:var(--text);margin-bottom:12px;}
        
        .btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;padding:.7rem 1.2rem;border-radius:10px;font-weight:700;font-size:0.9rem;cursor:pointer;border:none;transition:all .15s;}
        .btn-primary{background:linear-gradient(135deg,var(--primary),var(--primary-d));color:white;}
        .btn-outline{background:white;color:var(--primary);border:2px solid var(--primary);}
        .btn-success{background:var(--success);color:white;}
        .btn:hover:not([disabled]){filter:brightness(.92);transform:translateY(-1px);box-shadow:0 4px 12px rgba(0,0,0,0.1);}
        .btn[disabled]{opacity:.5;cursor:not-allowed;}

        /* Cart List */
        .cart-item{display:flex;align-items:center;justify-content:space-between;padding:1rem;background:white;border-radius:12px;margin-bottom:10px;border:1px solid var(--border);}
        .cart-item-info{flex:1;}
        .cart-item-name{font-weight:800;}
        .cart-item-meta{font-size:.8rem;color:var(--muted);margin-top:2px;}
        .cart-item-price{font-weight:900;color:var(--primary);font-size:1.1rem;}
        .qty-ctrl{display:flex;align-items:center;gap:10px;margin-left:15px;background:#f1f5f9;padding:5px;border-radius:8px;}
        .qty-btn{width:28px;height:28px;border:none;background:white;border-radius:6px;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 1px 3px rgba(0,0,0,.1);}
        .qty-val{font-weight:800;min-width:20px;text-align:center;}

        /* Order Box */
        .order-card{background:white;padding:1.5rem;border-radius:14px;margin-bottom:1rem;border:1px solid var(--border);}
        .order-header{display:flex;justify-content:space-between;border-bottom:1px solid var(--border);padding-bottom:.8rem;margin-bottom:.8rem;}
        .order-meta div{font-size:.85rem;color:var(--muted);}
        .order-status{font-weight:800;font-size:.85rem;padding:3px 10px;border-radius:20px;}
        .st-placed{background:#fef3c7;color:#b45309;}
        .st-assigned{background:#dbeafe;color:#1d4ed8;}
        .st-delivered{background:#dcfce7;color:#15803d;}

        #toast-wrap{position:fixed;top:1.5rem;right:1.5rem;z-index:99999;display:flex;flex-direction:column;gap:10px;}
        .toast{background:white;border-left:4px solid var(--primary);padding:1rem 1.2rem;border-radius:10px;box-shadow:0 8px 30px rgba(0,0,0,.15);animation:slideIn .3s ease;}
        @keyframes slideIn{from{transform:translateX(100%)}to{transform:translateX(0)}}

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
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="material-icons-outlined">storefront</i> DigiMarket
        </div>
        <div class="user-chip">
            <div class="user-av"><?= strtoupper(substr($userName,0,1)) ?></div>
            <div>
                <div class="user-info-name"><?= $userName ?></div>
                <div class="user-info-phone"><?= $userPhone ?></div>
            </div>
        </div>
        
        <a href="dashboard.php" class="nav-item">
            <i class="material-icons-outlined">arrow_back</i> Back to Laundry
        </a>
        <div class="nav-item active" onclick="switchView('shop')">
            <i class="material-icons-outlined">storefront</i> Shop
        </div>
        <div class="nav-item" onclick="switchView('cart')">
            <i class="material-icons-outlined">shopping_cart</i> My Cart <span id="cartBadge" style="background:var(--primary);color:white;padding:2px 8px;border-radius:10px;font-size:.7rem;margin-left:auto;display:none;">0</span>
        </div>
        <div class="nav-item" onclick="switchView('orders')">
            <i class="material-icons-outlined">inventory_2</i> My Orders
        </div>
    </aside>

    <main class="main">
        <?php if($needsProfileSetup): ?>
            <div style="background:#fef3c7;border:1px solid #fcd34d;padding:1rem;border-radius:12px;margin-bottom:1rem;color:#92400e;font-weight:600;display:flex;align-items:center;gap:10px;">
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
                    <option value="Bedsheet">Bedsheets</option>
                    <option value="Pillow">Pillows</option>
                    <option value="Towel">Towels</option>
                </select>
                <select id="sizeFilter" class="filter-select" onchange="renderProducts()">
                    <option value="all">All Sizes</option>
                    <option value="Single">Single</option>
                    <option value="Double">Double</option>
                    <option value="King">King</option>
                    <option value="Standard">Standard</option>
                </select>
            </div>

            <div id="loading" style="text-align:center;padding:3rem;color:var(--muted);font-weight:600;">Loading products...</div>
            <div class="products-grid" id="productsObj"></div>
        </section>

        <!-- CART SECTION -->
        <section id="cart" class="section">
            <div class="page-header">
                <div class="page-title">Your <span>Cart</span></div>
            </div>
            
            <div style="display:grid;grid-template-columns:1fr 350px;gap:2rem;" id="cartLayout">
                <div id="cartItemsList">
                    <!-- Cart items injected here -->
                </div>
                
                <div>
                    <div style="background:white;padding:1.5rem;border-radius:14px;border:1px solid var(--border);position:sticky;top:2rem;">
                        <h3 style="font-weight:900;margin-bottom:1rem;border-bottom:1px solid var(--border);padding-bottom:.8rem;">Order Summary</h3>
                        <div style="display:flex;justify-content:space-between;margin-bottom:.5rem;color:var(--muted);font-weight:600;">
                            <span>Subtotal</span>
                            <span id="cartSubtotal">₹0</span>
                        </div>
                        <div style="display:flex;justify-content:space-between;margin-bottom:1rem;font-weight:900;font-size:1.3rem;color:var(--text);border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem;">
                            <span>Total</span>
                            <span id="cartTotal">₹0</span>
                        </div>

                        <div style="margin-bottom:1.2rem;">
                            <label style="font-size:.8rem;font-weight:800;color:var(--muted);margin-bottom:5px;display:block;">PAYMENT METHOD</label>
                            <select id="paymentType" class="filter-select" style="width:100%;">
                                <option value="online">Pay Now (Online)</option>
                                <?php if($isEligibleForPayLater): ?>
                                    <option value="credit">Pay Later (Credit)</option>
                                <?php endif; ?>
                            </select>
                            
                            <?php if($isEligibleForPayLater): ?>
                                <div style="margin-top:8px;font-size:.75rem;padding:8px;background:#f8fafc;border-radius:8px;border:1px solid var(--border);">
                                    <span style="color:var(--muted);">Available Credit:</span> 
                                    <span style="font-weight:800;color:var(--primary);">₹<?= number_format($availableCredit, 2) ?></span>
                                </div>
                            <?php else: ?>
                                <div style="margin-top:8px;font-size:.7rem;color:var(--amber);font-weight:600;">
                                    <i class="material-icons-outlined" style="font-size:1rem;vertical-align:middle;">info</i> 
                                    Complete <?= max(0, 4 - $completedLaundryOrders) ?> more laundry orders to unlock Pay Later.
                                </div>
                            <?php endif; ?>
                        </div>

                        <button class="btn btn-primary" id="checkoutBtn" style="width:100%;font-size:1.05rem;" onclick="checkout()" <?= $needsProfileSetup ? 'disabled' : '' ?>>Place Order</button>
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

<script>
let allProducts = [];
let cart = JSON.parse(localStorage.getItem('mkt_cart')) || {}; 
const csrfToken = "<?= $csrfToken ?>";

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
    } catch (e) {
        return {success: false, message: 'Server connection failed.'};
    }
}

function showToast(msg, type='info') {
    const wrap = document.getElementById('toast-wrap');
    const t = document.createElement('div');
    t.className = 'toast';
    t.innerHTML = `<span style="font-weight:700;">${msg}</span>`;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 4000);
}

function switchView(id) {
    document.querySelectorAll('.section').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    
    document.querySelectorAll('.nav-item').forEach(n => {
        if(n.getAttribute('onclick')?.includes(id)) n.classList.add('active');
        else n.classList.remove('active');
    });

    if (id === 'cart') renderCart();
    if (id === 'orders') fetchOrders();
}

async function fetchProducts() {
    const res = await apiCall('../api/marketplace_products.php', 'get_products');
    document.getElementById('loading').style.display = 'none';
    if(res.success) {
        allProducts = res.products;
        renderProducts();
        updateCartBadge();
    }
}

function renderProducts() {
    const grid = document.getElementById('productsObj');
    const cat = document.getElementById('catFilter').value;
    const size = document.getElementById('sizeFilter').value;
    
    let filtered = allProducts.filter(p => {
        if(cat !== 'all' && p.category !== cat) return false;
        if(size !== 'all' && p.size !== size) return false;
        return true;
    });

    if(filtered.length === 0) {
        grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;color:var(--muted);padding:3rem;background:white;border-radius:14px;border:1px dashed var(--border);">No products found matching filters.</div>';
        return;
    }

    grid.innerHTML = filtered.map(p => {
        const img = p.image ? `<img src="../${p.image}" alt="">` : `<i class="material-icons-outlined">image</i>`;
        const actionBtn = p.stock > 0 
            ? `<button class="btn btn-outline" style="width:100%;margin-top:auto;" onclick="addToCart(${p.id})">Add to Cart</button>`
            : `<button class="btn btn-outline" style="width:100%;margin-top:auto;" disabled>Out of Stock</button>`;
        
        return `
            <div class="product-card">
                <div class="prod-img">${img}</div>
                <div class="prod-body">
                    <div class="prod-cat">${p.category}</div>
                    <div class="prod-name">${p.name}</div>
                    <div class="prod-size">Size: <span style="font-weight:700;color:var(--text);">${p.size}</span></div>
                    <div class="prod-price">₹${p.price}</div>
                    ${actionBtn}
                </div>
            </div>
        `;
    }).join('');
}

function addToCart(id) {
    const p = allProducts.find(x => x.id === id);
    if(!p) return;
    
    if(cart[id]) {
        if(cart[id].qty < p.stock) cart[id].qty++;
        else { showToast('Max stock reached!'); return; }
    } else {
        cart[id] = { id, name: p.name, price: p.price, image: p.image, size: p.size, stock: p.stock, qty: 1 };
    }
    
    saveCart();
    showToast(`${p.name} added to cart`);
}

function updateCartBadge() {
    const b = document.getElementById('cartBadge');
    const total = Object.values(cart).reduce((sum, item) => sum + item.qty, 0);
    if(total > 0) {
        b.textContent = total;
        b.style.display = 'inline-block';
    } else {
        b.style.display = 'none';
    }
}

function saveCart() {
    localStorage.setItem('mkt_cart', JSON.stringify(cart));
    updateCartBadge();
    if(document.getElementById('cart').classList.contains('active')) {
        renderCart();
    }
}

function renderCart() {
    const list = document.getElementById('cartItemsList');
    const items = Object.values(cart);
    
    if(items.length === 0) {
        document.getElementById('cartLayout').style.display = 'block';
        document.getElementById('cartLayout').innerHTML = `
            <div style="text-align:center;padding:4rem;background:white;border-radius:14px;border:1px dashed var(--border);">
                <i class="material-icons-outlined" style="font-size:4rem;color:var(--border);margin-bottom:1rem;">shopping_cart</i>
                <h3 style="margin-bottom:.5rem;">Your cart is empty</h3>
                <p style="color:var(--muted);margin-bottom:1.5rem;">Looks like you haven't added anything to your cart yet.</p>
                <button class="btn btn-primary" onclick="switchView('shop')">Start Shopping</button>
            </div>
        `;
        return;
    }

    let subtotal = 0;
    list.innerHTML = items.map(item => {
        subtotal += (item.qty * item.price);
        const img = item.image ? `<img src="../${item.image}" style="width:60px;height:60px;object-fit:cover;border-radius:10px;">` : `<div style="width:60px;height:60px;background:#f1f5f9;border-radius:10px;display:flex;align-items:center;justify-content:center;"><i class="material-icons-outlined">image</i></div>`;
        return `
            <div class="cart-item">
                <div style="display:flex;gap:12px;align-items:center;">
                    ${img}
                    <div>
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-meta">Size: ${item.size}</div>
                        <div class="cart-item-price">₹${item.price}</div>
                    </div>
                </div>
                <div class="qty-ctrl">
                    <button class="qty-btn" onclick="updateQty(${item.id}, -1)">-</button>
                    <span class="qty-val">${item.qty}</span>
                    <button class="qty-btn" onclick="updateQty(${item.id}, 1)">+</button>
                    <button class="qty-btn" style="background:#fee2e2;color:#dc2626;margin-left:5px;" onclick="updateQty(${item.id}, 'remove')"><i class="material-icons-outlined" style="font-size:1rem;">delete</i></button>
                </div>
            </div>
        `;
    }).join('');

    document.getElementById('cartSubtotal').textContent = `₹${subtotal.toFixed(2)}`;
    document.getElementById('cartTotal').textContent = `₹${subtotal.toFixed(2)}`;
}

function updateQty(id, delta) {
    if(!cart[id]) return;
    if(delta === 'remove') {
        delete cart[id];
    } else {
        const newQty = cart[id].qty + delta;
        if(newQty <= 0) delete cart[id];
        else if(newQty <= cart[id].stock) cart[id].qty = newQty;
        else showToast('Max stock limit reached', 'error');
    }
    saveCart();
}

async function checkout() {
    const items = Object.values(cart);
    if(items.length === 0) return;
    
    const paymentType = document.getElementById('paymentType').value;
    const btn = document.getElementById('checkoutBtn');
    
    btn.disabled = true;
    btn.textContent = 'Processing...';

    const payload = {
        items: items.map(i => ({product_id: i.id, quantity: i.qty})),
        payment_type: paymentType
    };

    if(paymentType === 'online') {
        // Mocking Razorpay for MVP logic similar to laundry
        payload.razorpay_payment_id = 'pay_' + Math.random().toString(36).substr(2, 9);
    }

    const res = await apiCall('../api/create_marketplace_order.php', 'create_order', payload);
    
    if(res.success) {
        cart = {};
        saveCart();
        showToast('Order placed successfully!', 'success');
        switchView('orders');
        btn.disabled = false;
        btn.textContent = 'Place Order';
    } else {
        showToast(res.message, 'error');
        btn.disabled = false;
        btn.textContent = 'Place Order';
    }
}

async function fetchOrders() {
    const wrap = document.getElementById('myOrdersWrap');
    const loading = document.getElementById('ordersListLoading');
    loading.style.display = 'block';
    wrap.innerHTML = '';

    const res = await apiCall('../api/marketplace_orders.php', 'get_orders');
    loading.style.display = 'none';

    if(res.success) {
        if(res.orders.length === 0) {
            wrap.innerHTML = '<div style="text-align:center;padding:3rem;color:var(--muted);background:white;border-radius:14px;">No marketplace orders found.</div>';
            return;
        }

        wrap.innerHTML = res.orders.map(o => {
            let statusClass = 'st-placed';
            if(['assigned','picked_up','out_for_delivery'].includes(o.status)) statusClass = 'st-assigned';
            if(o.status === 'delivered') statusClass = 'st-delivered';

            const itemsHtml = o.items.map(i => `
                <div style="display:flex;justify-content:space-between;margin-top:8px;font-size:.85rem;padding-bottom:8px;border-bottom:1px dashed var(--border);">
                    <div><span style="font-weight:700;">${i.quantity}x</span> ${i.name} (${i.size})</div>
                    <div style="font-weight:700;">₹${parseFloat(i.price) * parseInt(i.quantity)}</div>
                </div>
            `).join('');

            let delHtml = '';
            if(o.delivery_name) {
                delHtml = `<div style="margin-top:10px;padding:8px;background:#f8fafc;border-radius:8px;font-size:.8rem;">
                    <i class="material-icons-outlined" style="font-size:1rem;vertical-align:middle;color:var(--primary);">delivery_dining</i> 
                    Delivery Partner: <b>${o.delivery_name}</b> 
                </div>`;
            }

            return `
                <div class="order-card">
                    <div class="order-header">
                        <div class="order-meta">
                            <div style="font-weight:900;font-size:1rem;color:var(--text);margin-bottom:3px;">Order #${o.id}</div>
                            <div>${new Date(o.created_at).toLocaleString()}</div>
                        </div>
                        <div>
                            <span class="order-status ${statusClass}">${o.status.toUpperCase().replace('_',' ')}</span>
                        </div>
                    </div>
                    <div>${itemsHtml}</div>
                    <div style="display:flex;justify-content:space-between;margin-top:12px;font-weight:900;font-size:1.1rem;">
                        <span>Total Paid via ${o.payment_type.toUpperCase()}</span>
                        <span style="color:var(--primary);">₹${o.total_amount}</span>
                    </div>
                    ${delHtml}
                </div>
            `;
        }).join('');
    }
}

window.onload = () => {
    fetchProducts();
    const styleId = document.createElement('style');
    // Hide native layout
    if (window.innerWidth <= 768) {
        document.querySelector('.app-wrap').style.gridTemplateColumns = '1fr';
        document.querySelector('.sidebar').style.display = 'none';
        // Add a bottom nav or hamburger logic if requested later, keeping simple for MVP.
    }
};
</script>
</body>
</html>
