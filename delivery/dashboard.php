<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header('Location: ../index.php'); exit;
}
$partnerName = $_SESSION['name'] ?? 'Partner';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash - Delivery Hub</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        :root { --sidebar-w: 230px; }
        body { background: #f1f5f9; }
        .hub-wrap { display: grid; grid-template-columns: var(--sidebar-w) 1fr; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar { background: #0f172a; padding: 1.5rem 1rem; display: flex; flex-direction: column; gap: 0.2rem; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
        .sidebar-brand { display: flex; align-items: center; gap: 10px; padding: 0.5rem 0.75rem 1rem; color: white; font-size: 1.1rem; font-weight: 800; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 0.75rem; }
        .sidebar-brand i { color: #10b981; font-size: 1.8rem; }
        .partner-chip { display: flex; align-items: center; gap: 10px; padding: 0.75rem; background: rgba(255,255,255,0.06); border-radius: 12px; margin-bottom: 1rem; }
        .partner-chip .av { width: 36px; height: 36px; background: linear-gradient(135deg,#10b981,#059669); border-radius: 10px; display: flex; align-items:center; justify-content:center; color:white; font-weight:800; font-size:1rem; flex-shrink:0; }
        .partner-chip .info { min-width:0; }
        .partner-chip .name { color:white; font-weight:700; font-size:0.85rem; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .partner-chip .role { color:#64748b; font-size:0.72rem; }
        .menu-item { display: flex; align-items: center; gap: 12px; padding: 0.7rem 1rem; border-radius: 10px; color: #94a3b8; font-weight: 600; font-size: 0.875rem; cursor: pointer; transition: all 0.2s; position: relative; }
        .menu-item:hover { background: rgba(255,255,255,0.06); color: white; }
        .menu-item.active { background: linear-gradient(135deg,#10b981,#059669); color: white; box-shadow: 0 4px 12px rgba(16,185,129,0.3); }
        .menu-item i { font-size: 1.2rem; }
        .menu-badge { background: #ef4444; color: white; border-radius: 999px; font-size: 0.68rem; padding: 1px 6px; margin-left: auto; font-weight: 700; }

        /* ── Main ── */
        .main { padding: 2rem; }
        .section-content { display: none; }
        .section-content.active { display: block; animation: fadeUp 0.3s ease; }
        @keyframes fadeUp { from{opacity:0;transform:translateY(8px)} to{opacity:1;transform:translateY(0)} }
        .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.5rem; font-weight: 800; color: #0f172a; }

        /* ── Stats bar ── */
        .stats-row { display: grid; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-chip { background: white; border-radius: 14px; padding: 1.2rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.07); }
        .stat-chip .num { font-size: 2rem; font-weight: 800; color: #0f172a; line-height: 1; }
        .stat-chip .lbl { font-size: 0.75rem; color: #64748b; font-weight: 600; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-chip.green .num { color: #059669; }
        .stat-chip.amber .num { color: #d97706; }
        .stat-chip.blue .num { color: #2563eb; }

        /* ── Order card ── */
        .order-card { background: white; border-radius: 16px; padding: 1.4rem; margin-bottom: 1rem; border-left: 5px solid #10b981; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.15s, box-shadow 0.15s; }
        .order-card:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
        .order-card.pickup { border-left-color: #6366f1; }
        .order-card.in-process { border-left-color: #f59e0b; }
        .order-card.delivery { border-left-color: #10b981; }
        .order-card.done { border-left-color: #94a3b8; }
        .order-card.return { border-left-color: #ef4444; }

        .card-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap; }
        .card-info h4 { margin: 0 0 6px; font-size: 1rem; font-weight: 700; color: #0f172a; }
        .card-info p { margin: 3px 0; font-size: 0.85rem; color: #64748b; display: flex; align-items: center; gap: 6px; }
        .card-info p i { font-size: 15px; color: #94a3b8; }
        .card-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; flex-shrink: 0; }
        .card-amount { font-size: 1.2rem; font-weight: 800; color: #0f172a; }

        /* ── Buttons ── */
        .btn-action { display: inline-flex; align-items: center; gap: 6px; padding: 0.5rem 1rem; border-radius: 10px; border: none; font-weight: 700; font-size: 0.85rem; cursor: pointer; transition: all 0.15s; white-space: nowrap; }
        .btn-action:hover { filter: brightness(0.9); transform: translateY(-1px); }
        .btn-pickup  { background: #ede9fe; color: #6d28d9; }
        .btn-qr      { background: #dbeafe; color: #1d4ed8; }
        .btn-otp     { background: #dcfce7; color: #15803d; }
        .btn-bypass  { background: #fee2e2; color: #b91c1c; }
        .btn-primary { background: #6366f1; color: white; }
        .btn-ghost   { background: #f1f5f9; color: #475569; }

        /* ── Badge ── */
        .badge { display: inline-flex; align-items: center; padding: 0.22rem 0.65rem; border-radius: 999px; font-size: 0.75rem; font-weight: 700; }
        .b-green { background:#dcfce7; color:#16a34a; }
        .b-amber { background:#fef3c7; color:#d97706; }
        .b-blue  { background:#dbeafe; color:#2563eb; }
        .b-red   { background:#fee2e2; color:#dc2626; }
        .b-gray  { background:#f1f5f9; color:#64748b; }

        /* ── Empty state ── */
        .empty-state { text-align: center; padding: 3rem 1rem; color: #94a3b8; }
        .empty-state i { font-size: 3.5rem; display: block; margin-bottom: 0.75rem; color: #cbd5e1; }
        .empty-state p { font-size: 1rem; font-weight: 600; }

        /* ── Modal ── */
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 2rem; width: 90%; max-width: 420px; position: relative; animation: fadeUp 0.25s ease; }
        .modal-title { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 0.5rem; }
        .modal-sub { font-size: 0.85rem; color: #64748b; margin-bottom: 1.2rem; }
        .modal-close { position: absolute; top: 1rem; right: 1rem; background: #f1f5f9; border: none; border-radius: 8px; width: 30px; height: 30px; cursor: pointer; color: #64748b; font-size: 1rem; }
        .form-group { margin-bottom: 1rem; }
        .form-group label { display: block; font-size: 0.8rem; font-weight: 700; color: #475569; margin-bottom: 5px; }
        .form-group input { width: 100%; padding: 0.6rem 0.9rem; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 0.9rem; outline: none; box-sizing: border-box; transition: border-color 0.2s; font-family: inherit; }
        .form-group input:focus { border-color: #10b981; }
        .modal-actions { display: flex; gap: 0.75rem; margin-top: 1.2rem; }
        .form-msg { font-size: 0.85rem; font-weight: 600; margin-top: 0.75rem; display: none; }

        /* ── QR reader ── */
        #qr-reader { width: 100%; max-width: 300px; margin: 0 auto 1rem; border-radius: 12px; overflow: hidden; }

        @media(max-width: 768px) {
            .hub-wrap { grid-template-columns: 1fr; }
            .sidebar { display: none; }
        }
    </style>
</head>
<body>
<div class="hub-wrap">
    <!-- ── SIDEBAR ── -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <i class="material-icons-outlined">two_wheeler</i> Delivery Hub
        </div>

        <div class="partner-chip">
            <div class="av"><?= strtoupper(substr($partnerName,0,1)) ?></div>
            <div class="info">
                <div class="name"><?= htmlspecialchars($partnerName) ?></div>
                <div class="role">Delivery Partner</div>
            </div>
        </div>

        <div class="menu-item active" id="nav-pickups" onclick="switchTab('pickups',this)">
            <i class="material-icons-outlined">hail</i> Pickups
            <span class="menu-badge" id="badgePickups" style="display:none">0</span>
        </div>
        <div class="menu-item" id="nav-inprocess" onclick="switchTab('inprocess',this)">
            <i class="material-icons-outlined">local_laundry_service</i> In Process
            <span class="menu-badge" id="badgeInprocess" style="display:none">0</span>
        </div>
        <div class="menu-item" id="nav-deliveries" onclick="switchTab('deliveries',this)">
            <i class="material-icons-outlined">local_shipping</i> Out for Delivery
            <span class="menu-badge" id="badgeDeliveries" style="display:none">0</span>
        </div>
        <div class="menu-item" id="nav-completed" onclick="switchTab('completed',this)">
            <i class="material-icons-outlined">task_alt</i> Completed
        </div>
        <div class="menu-item" id="nav-returns" onclick="switchTab('returns',this)">
            <i class="material-icons-outlined">assignment_return</i> Return Pickups
        </div>

        <div style="margin-top:auto; padding-top:1.5rem;">
            <div class="menu-item" id="logoutBtn" style="color:#ef4444;">
                <i class="material-icons-outlined">logout</i> Logout
            </div>
        </div>
    </aside>

    <!-- ── MAIN ── -->
    <main class="main">

        <!-- Stats always visible at top -->
        <div class="stats-row" id="statsRow">
            <div class="stat-chip"><div class="num" id="sPickups">0</div><div class="lbl">Pending Pickups</div></div>
            <div class="stat-chip amber"><div class="num" id="sInprocess">0</div><div class="lbl">In Processing</div></div>
            <div class="stat-chip blue"><div class="num" id="sOutDelivery">0</div><div class="lbl">Out for Delivery</div></div>
            <div class="stat-chip green"><div class="num" id="sTodayDone">0</div><div class="lbl">Completed Today</div></div>
            <div class="stat-chip"><div class="num" id="sTotalDone">0</div><div class="lbl">All Time Done</div></div>
        </div>

        <!-- ══ PICKUPS ══ -->
        <section id="pickups" class="section-content active">
            <div class="page-header">
                <div class="page-title">🛍️ Pending Pickups</div>
                <button class="btn-action btn-ghost" onclick="loadSection('pickups')">↻ Refresh</button>
            </div>
            <div id="pickupsContainer"><div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div></div>
        </section>

        <!-- ══ IN PROCESS ══ -->
        <section id="inprocess" class="section-content">
            <div class="page-header">
                <div class="page-title">🧺 In Processing</div>
                <button class="btn-action btn-ghost" onclick="loadSection('inprocess')">↻ Refresh</button>
            </div>
            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1.5rem;">These orders are at the laundry facility. Mark them "Out for Delivery" once ready.</p>
            <div id="inprocessContainer"><div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div></div>
        </section>

        <!-- ══ DELIVERIES ══ -->
        <section id="deliveries" class="section-content">
            <div class="page-header">
                <div class="page-title">🚚 Out for Delivery</div>
                <button class="btn-action btn-ghost" onclick="loadSection('deliveries')">↻ Refresh</button>
            </div>
            <div id="deliveriesContainer"><div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div></div>
        </section>

        <!-- ══ COMPLETED ══ -->
        <section id="completed" class="section-content">
            <div class="page-header">
                <div class="page-title">✅ Completed Deliveries</div>
                <button class="btn-action btn-ghost" onclick="loadSection('completed')">↻ Refresh</button>
            </div>
            <div id="completedContainer"><div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div></div>
        </section>

        <!-- ══ RETURNS ══ -->
        <section id="returns" class="section-content">
            <div class="page-header">
                <div class="page-title">↩️ Return Pickups</div>
                <button class="btn-action btn-ghost" onclick="loadSection('returns')">↻ Refresh</button>
            </div>
            <p style="color:#64748b; font-size:0.85rem; margin-bottom:1.5rem;">Approved return requests — pick these up from the customer's location.</p>
            <div id="returnsContainer"><div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div></div>
        </section>

    </main>
</div>

<!-- ══════════ MODALS ══════════ -->



<!-- QR Modal -->
<div class="modal-overlay" id="qrModal">
    <div class="modal-box" style="text-align:center;">
        <button class="modal-close" onclick="closeQRModal()">✕</button>
        <div class="modal-title">📱 Scan Customer QR</div>
        <div class="modal-sub">Point camera at the QR code on the customer's DigiWash dashboard.</div>
        <div id="qr-reader"></div>
        <input type="hidden" id="qrOrderId">
        <button class="btn-action btn-ghost" onclick="closeQRModal()" style="margin: 0 auto;">Cancel Scan</button>
        <div class="form-msg" id="qrMsg" style="margin-top:0.75rem;"></div>
    </div>
</div>

<!-- Bypass Modal -->
<div class="modal-overlay" id="bypassModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('bypassModal')">✕</button>
        <div class="modal-title">⚠️ Bypass Delivery (Photo)</div>
        <div class="modal-sub" style="color:#ef4444;">Use ONLY if customer is unavailable and shop staff is authorizing.</div>
        <input type="hidden" id="bypassOrderId">
        <div class="form-group">
            <label>Staff Contact Number (10 digits)</label>
            <input type="tel" id="bypassStaffNum" maxlength="10" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)" placeholder="e.g. 9876543210">
        </div>
        <div class="form-group">
            <label>Capture Photo of Staff / Shop</label>
            <input type="file" id="bypassPhoto" accept="image/*" capture="environment">
        </div>
        <div class="modal-actions">
            <button class="btn-action btn-bypass" style="flex:1; justify-content:center;" onclick="submitBypass()" id="btnSubmitBypass">📷 Upload & Complete</button>
            <button class="btn-action btn-ghost" onclick="closeModal('bypassModal')">Cancel</button>
        </div>
        <div class="form-msg" id="bypassMsg"></div>
    </div>
</div>

<!-- Mark Ready Modal (in_process → out_for_delivery) -->
<div class="modal-overlay" id="readyModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('readyModal')">✕</button>
        <div class="modal-title">📦 Mark as Ready for Delivery</div>
        <div class="modal-sub">Confirm the laundry for this order is cleaned and packed. This will move it to "Out for Delivery".</div>
        <input type="hidden" id="readyOrderId">
        <div class="modal-actions">
            <button class="btn-action btn-otp" style="flex:1; justify-content:center;" onclick="submitReady()" id="btnReady">✓ Mark Ready</button>
            <button class="btn-action btn-ghost" onclick="closeModal('readyModal')">Cancel</button>
        </div>
        <div class="form-msg" id="readyMsg"></div>
    </div>
</div>

<script>
const csrf  = "<?= $_SESSION['csrf_token'] ?? '' ?>";
const dApi  = '../api/delivery.php';

// ── API ──
async function api(action, payload = {}) {
    try {
        const r = await fetch(dApi, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ action, ...payload })
        });
        return await r.json();
    } catch(e) { return { success: false, message: 'Server error' }; }
}

// ── Modal ──
function openModal(id)  { document.getElementById(id).classList.add('open'); }
function closeModal(id) { document.getElementById(id).classList.remove('open'); }

// ── Tab ──
function switchTab(id, el) {
    document.querySelectorAll('.section-content').forEach(s => s.classList.remove('active'));
    document.getElementById(id).classList.add('active');
    document.querySelectorAll('.menu-item').forEach(m => m.classList.remove('active'));
    (el || document.getElementById('nav-'+id)).classList.add('active');
    loadSection(id);
}

// ── Stats ──
async function loadStats() {
    const d = await api('get_stats');
    if (!d.success) return;
    document.getElementById('sPickups').textContent   = d.pickups;
    document.getElementById('sInprocess').textContent = d.in_process;
    document.getElementById('sOutDelivery').textContent = d.out_for_delivery;
    document.getElementById('sTodayDone').textContent  = d.today_done;
    document.getElementById('sTotalDone').textContent  = d.total_done;
    // Sidebar badges
    setBadge('badgePickups',   d.pickups);
    setBadge('badgeInprocess', d.in_process);
    setBadge('badgeDeliveries',d.out_for_delivery);
}
function setBadge(id, count) {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = count;
    el.style.display = count > 0 ? 'inline' : 'none';
}

// ── Load Section ──
async function loadSection(type) {
    // Map tab id → API type
    const apiType = type === 'inprocess' ? 'in_process' : type;
    const containerId = type + 'Container';
    const container = document.getElementById(containerId);
    if (!container) return;
    container.innerHTML = '<div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div>';
    const d = await api('get_assignments', { type: apiType });
    if (!d.success) { container.innerHTML = '<div class="empty-state"><i class="material-icons-outlined">error_outline</i><p>' + d.message + '</p></div>'; return; }
    if (!d.assignments || !d.assignments.length) {
        const emptyMsgs = {
            pickups:   'No pending pickups. Great — all collected!',
            inprocess: 'No orders in processing right now.',
            deliveries:'No orders out for delivery.',
            completed: 'No completed deliveries yet.',
            returns:   'No approved return pickups assigned to you.',
        };
        container.innerHTML = `<div class="empty-state"><i class="material-icons-outlined">inbox</i><p>${emptyMsgs[type]||'Nothing here.'}</p></div>`;
        return;
    }
    container.innerHTML = d.assignments.map(o => renderCard(o, type)).join('');
}

// ── Render order card ──
function renderCard(o, type) {
    const name = o.customer_name || 'Customer';
    const phone = o.phone || o.customer_phone || '—';
    const addr = o.shop_address || '—';
    const date = new Date(o.created_at || o.return_date).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
    const amount = o.total_amount ? `₹${o.total_amount}` : '';

    let cardClass = { pickups:'pickup', inprocess:'in-process', deliveries:'delivery', completed:'done', returns:'return' }[type] || '';
    let statusBadge = '';
    let actions = '';

    if (type === 'pickups') {
        statusBadge = '<span class="badge b-amber">Pending Pickup</span>';
        actions = `<button class="btn-action btn-pickup" onclick="fulfillPickup(${o.id})"><i class="material-icons-outlined" style="font-size:16px">shopping_bag</i> Picked Up</button>`;
    } else if (type === 'inprocess') {
        statusBadge = '<span class="badge b-amber">In Processing</span>';
        actions = `<button class="btn-action btn-otp" onclick="openReadyModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">local_shipping</i> Mark Ready</button>`;
    } else if (type === 'deliveries') {
        statusBadge = '<span class="badge b-blue">Out for Delivery</span>';
        actions = `
            <button class="btn-action btn-qr" onclick="openQRModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">qr_code_scanner</i> Scan QR</button>
            <button class="btn-action btn-bypass" onclick="openBypassModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">camera_alt</i> Bypass</button>
        `;
    } else if (type === 'completed') {
        statusBadge = '<span class="badge b-green">Delivered ✓</span>';
        actions = amount;
    } else if (type === 'returns') {
        statusBadge = '<span class="badge b-red">Return Pickup</span>';
        actions = `<div style="font-size:0.82rem;color:#64748b;max-width:200px">${o.reason||'No reason given'}</div>`;
    }

    return `
        <div class="order-card ${cardClass}">
            <div class="card-row">
                <div class="card-info">
                    <h4>Order #${o.id} — ${name}</h4>
                    <p><i class="material-icons-outlined">call</i> ${phone}</p>
                    <p><i class="material-icons-outlined">location_on</i> ${addr}</p>
                    <p><i class="material-icons-outlined">calendar_today</i> ${date}</p>
                    ${amount ? `<p><i class="material-icons-outlined">payments</i> <strong>${amount}</strong></p>` : ''}
                </div>
                <div class="card-actions">
                    ${statusBadge}
                    ${actions}
                </div>
            </div>
        </div>
    `;
}

// ── Actions ──
async function fulfillPickup(orderId) {
    if (!confirm('Confirm you have collected the items from the shop?')) return;
    const d = await api('fulfill_pickup', { order_id: orderId });
    if (d.success) { loadSection('pickups'); loadStats(); }
    else alert(d.message);
}

function openReadyModal(orderId) {
    document.getElementById('readyOrderId').value = orderId;
    document.getElementById('readyMsg').style.display = 'none';
    openModal('readyModal');
}
async function submitReady() {
    const orderId = document.getElementById('readyOrderId').value;
    const btn = document.getElementById('btnReady');
    btn.textContent = 'Updating…'; btn.disabled = true;
    // Call admin API to change status to out_for_delivery
    try {
        const r = await fetch('../api/delivery.php', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-CSRF-Token': csrf },
            body: JSON.stringify({ action: 'mark_ready', order_id: orderId })
        });
        const d = await r.json();
        const msg = document.getElementById('readyMsg');
        msg.textContent = d.message; msg.style.color = d.success ? '#10b981' : '#ef4444'; msg.style.display = 'block';
        if (d.success) setTimeout(()=>{ closeModal('readyModal'); loadSection('inprocess'); loadSection('deliveries'); loadStats(); }, 1200);
    } catch(e) { alert('Server error'); }
    btn.textContent = '✓ Mark Ready'; btn.disabled = false;
}



function openBypassModal(orderId) {
    document.getElementById('bypassOrderId').value = orderId;
    document.getElementById('bypassStaffNum').value = '';
    document.getElementById('bypassPhoto').value = '';
    document.getElementById('bypassMsg').style.display = 'none';
    openModal('bypassModal');
}
async function submitBypass() {
    const orderId  = document.getElementById('bypassOrderId').value;
    const staffNum = document.getElementById('bypassStaffNum').value;
    const photoFile = document.getElementById('bypassPhoto').files[0];
    const btn = document.getElementById('btnSubmitBypass');
    const msg = document.getElementById('bypassMsg');
    if (staffNum.length !== 10) { msg.textContent = 'Staff number must be 10 digits.'; msg.style.color='#ef4444'; msg.style.display='block'; return; }
    if (!photoFile) { msg.textContent = 'Please capture a photo.'; msg.style.color='#ef4444'; msg.style.display='block'; return; }
    btn.textContent = 'Uploading…'; btn.disabled = true; msg.style.display = 'none';
    const fd = new FormData();
    fd.append('action','complete_delivery_bypass');
    fd.append('order_id', orderId);
    fd.append('staff_number', staffNum);
    fd.append('staff_photo', photoFile);
    try {
        const r = await fetch(dApi, { method:'POST', headers:{'X-CSRF-Token':csrf}, body: fd });
        const d = await r.json();
        msg.textContent = d.message; msg.style.color = d.success?'#10b981':'#ef4444'; msg.style.display='block';
        if (d.success) setTimeout(()=>{ closeModal('bypassModal'); loadSection('deliveries'); loadSection('completed'); loadStats(); }, 1200);
    } catch(e) { msg.textContent = 'Upload failed.'; msg.style.color='#ef4444'; msg.style.display='block'; }
    btn.textContent = '📷 Upload & Complete'; btn.disabled = false;
}

// ── QR Scanner ──
let qrScanner = null;
function openQRModal(orderId) {
    document.getElementById('qrOrderId').value = orderId;
    document.getElementById('qrMsg').style.display = 'none';
    openModal('qrModal');
    if (!qrScanner) qrScanner = new Html5Qrcode('qr-reader');
    qrScanner.start(
        { facingMode: 'environment' },
        { fps: 10, qrbox: { width: 250, height: 250 } },
        async (text) => {
            const msg = document.getElementById('qrMsg');
            msg.textContent = 'QR Scanned! Verifying…'; msg.style.color='#059669'; msg.style.display='block';
            await qrScanner.stop(); qrScanner.clear(); qrScanner = null;
            const d = await api('complete_delivery_qr', { order_id: orderId, qr_hash: text });
            msg.textContent = d.message; msg.style.color = d.success?'#059669':'#ef4444';
            if (d.success) setTimeout(()=>{ closeModal('qrModal'); loadSection('deliveries'); loadSection('completed'); loadStats(); }, 1200);
        },
        () => {}
    ).catch(() => {
        const msg = document.getElementById('qrMsg');
        msg.textContent = 'Camera access denied.'; msg.style.color='#ef4444'; msg.style.display='block';
    });
}
function closeQRModal() {
    closeModal('qrModal');
    if (qrScanner) { 
        try {
            qrScanner.stop().catch(()=>{}).finally(()=>{ 
                try { qrScanner.clear(); } catch(e){} 
                qrScanner=null; 
            }); 
        } catch(e) {
            try { qrScanner.clear(); } catch(err){}
            qrScanner=null;
        }
    }
}

// ── Close on overlay click ──
document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => { if(e.target===m) { if(m.id==='qrModal') closeQRModal(); else m.classList.remove('open'); } });
});

// ── Logout ──
document.getElementById('logoutBtn').addEventListener('click', async () => {
    await fetch('../api/auth.php', { method:'POST', headers:{'Content-Type':'application/json','X-CSRF-Token':csrf}, body:JSON.stringify({action:'logout'}) });
    window.location.href = '../index.php';
});

// ── Init ──
document.addEventListener('DOMContentLoaded', () => {
    loadStats();
    loadSection('pickups');
});
</script>
</body>
</html>
