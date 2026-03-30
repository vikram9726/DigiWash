<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header('Location: ../index.php'); exit;
}
$partnerName = $_SESSION['name'] ?? 'Partner';
$stmt = $pdo->prepare("SELECT is_online FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$isOnline = (int)$stmt->fetchColumn() === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash - Delivery Hub</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript" defer></script>
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
        .section-content.active { display: block; animation: fadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; will-change: transform, opacity; backface-visibility: hidden; }
        @keyframes fadeUp { from{opacity:0;transform:translate3d(0,8px,0)} to{opacity:1;transform:translate3d(0,0,0)} }
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
        .order-card { background: white; border-radius: 16px; padding: 1.4rem; margin-bottom: 1rem; border-left: 5px solid #10b981; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.2s cubic-bezier(0.16,1,0.3,1), box-shadow 0.2s ease; will-change: transform; backface-visibility: hidden; }
        .order-card:hover { transform: translate3d(0, -2px, 0); box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
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
        .btn-action:hover { filter: brightness(0.9); transform: translate3d(0, -1px, 0); }
        .btn-pickup  { background: #ede9fe; color: #6d28d9; }
        .btn-qr      { background: #dbeafe; color: #1d4ed8; }
        .btn-otp     { background: #dcfce7; color: #15803d; }
        .btn-bypass  { background: #fee2e2; color: #b91c1c; }
        .btn-cancel  { background: #fff1f2; color: #be123c; border: 1.5px solid #fecdd3; }
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
        .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; align-items: center; justify-content: center; backdrop-filter: blur(4px); }
        .modal-overlay.open { display: flex; }
        .modal-box { background: white; border-radius: 20px; padding: 2rem; width: 90%; max-width: 420px; position: relative; animation: fadeUp 0.3s cubic-bezier(0.16, 1, 0.3, 1); will-change: transform, opacity; backface-visibility: hidden; }
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

        <div style="padding:0 1rem 1rem;">
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; color:#94a3b8; font-size:0.85rem; font-weight:600;">
                <span style="flex:1">Accepting Orders</span>
                <input type="checkbox" id="onlineToggle" onchange="toggleOnline(this.checked)" <?= $isOnline ? 'checked' : '' ?> style="width:16px;height:16px;accent-color:#10b981;">
            </label>
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

<!-- MODAL: OTP -->
<div class="modal-overlay" id="otpModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal('otpModal')">✕</button>
        <div class="modal-title">🔐 Complete Delivery (PIN)</div>
        <div class="modal-sub">Ask the customer for the 6-digit PIN shown on their DigiWash dashboard.</div>
        <input type="hidden" id="otpOrderId">
        <div class="form-group">
            <label>Enter Customer PIN</label>
            <input type="text" id="otpInput" maxlength="6" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'')" placeholder="e.g. 123456" style="font-size:1.5rem; text-align:center; letter-spacing:8px; font-weight:800;">
        </div>
        <div class="modal-actions">
            <button class="btn-action btn-otp" style="flex:1; justify-content:center;" onclick="submitOTP()" id="btnSubmitOtp">✓ Verify & Complete</button>
            <button class="btn-action btn-ghost" onclick="closeModal('otpModal')">Cancel</button>
        </div>
        <div class="form-msg" id="otpMsg"></div>
    </div>
</div>

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

<!-- Custom Confirm Modal -->
<div class="modal-overlay" id="confirmModal" style="z-index: 10000;">
    <div class="modal-box" style="text-align:center;">
        <i class="material-icons-outlined" style="font-size:3.5rem; color:#f59e0b; margin-bottom:1rem;" id="confirmIcon">warning</i>
        <div class="modal-title" id="confirmTitle" style="font-size:1.4rem;">Confirm Action</div>
        <div class="modal-sub" id="confirmSub" style="font-size:1rem;color:#64748b;">Are you sure?</div>
        <div class="modal-actions" style="margin-top:1.5rem; justify-content:center; gap:10px;">
            <button class="btn-action btn-primary" style="flex:1;justify-content:center;font-size:1rem;padding:0.7rem;" id="btnConfirmYes">Yes, Proceed</button>
            <button class="btn-action btn-ghost" style="flex:1;justify-content:center;font-size:1rem;" onclick="closeModal('confirmModal')">Cancel</button>
        </div>
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
    
    if (id === 'marketplace') loadMarketplace();
    else loadSection(id);
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

// ── Globals ──
let currentSysMode = { pickups: 'wash', inprocess: 'wash', deliveries: 'wash', completed: 'wash', returns: 'wash' };

function customConfirm(title, msg, onYes, onNo = null) {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmSub').textContent = msg;
    const btnTrue = document.getElementById('btnConfirmYes');
    btnTrue.onclick = () => { closeModal('confirmModal'); onYes(); };
    openModal('confirmModal');
}

function setSysMode(type, mode) {
    currentSysMode[type] = mode;
    loadSection(type);
}

function renderSysTabs(type) {
    if (!['pickups', 'deliveries', 'completed'].includes(type)) return '';
    const washAct = currentSysMode[type] === 'wash' ? 'background:#10b981;color:white;box-shadow:0 4px 10px rgba(16,185,129,.3);' : 'background:transparent;color:#64748b;font-weight:600;';
    const mktAct = currentSysMode[type] === 'market' ? 'background:#10b981;color:white;box-shadow:0 4px 10px rgba(16,185,129,.3);' : 'background:transparent;color:#64748b;font-weight:600;';
    return `
        <div style="background:#e2e8f0; padding:6px; border-radius:12px; display:flex; gap:6px; margin-bottom:1.5rem;">
            <button style="flex:1; border:none; padding:10px; border-radius:10px; font-weight:700; font-size:.9rem; cursor:pointer; transition:.2s; ${washAct}" onclick="setSysMode('${type}','wash')">🧺 Laundry (DigiWash)</button>
            <button style="flex:1; border:none; padding:10px; border-radius:10px; font-weight:700; font-size:.9rem; cursor:pointer; transition:.2s; ${mktAct}" onclick="setSysMode('${type}','market')">🛍️ Marketplace (DigiMarket)</button>
        </div>
    `;
}

// ── Load Section ──
async function loadSection(type) {
    // Map tab id → API type
    const apiType = type === 'inprocess' ? 'in_process' : type;
    const containerId = type + 'Container';
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = (['pickups','deliveries','completed'].includes(type) ? renderSysTabs(type) : '') + '<div class="empty-state"><i class="material-icons-outlined">hourglass_empty</i><p>Loading…</p></div>';

    if (currentSysMode[type] === 'market' && ['pickups','deliveries','completed'].includes(type)) {
        await loadMarketDataForSection(type, container);
        return;
    }

    const d = await api('get_assignments', { type: apiType });
    
    // Maintain tabs inside innerHTML replacement
    const tabsHtml = ['pickups','deliveries','completed'].includes(type) ? renderSysTabs(type) : '';

    if (!d.success) { container.innerHTML = tabsHtml + '<div class="empty-state"><i class="material-icons-outlined">error_outline</i><p>' + d.message + '</p></div>'; return; }
    if (!d.assignments || !d.assignments.length) {
        const emptyMsgs = {
            pickups:   'No pending laundry pickups. Great — all collected!',
            inprocess: 'No orders in processing right now.',
            deliveries:'No laundry out for delivery.',
            completed: 'No completed deliveries yet.',
            returns:   'No approved return pickups assigned to you.',
        };
        container.innerHTML = tabsHtml + `<div class="empty-state"><i class="material-icons-outlined">inbox</i><p>${emptyMsgs[type]||'Nothing here.'}</p></div>`;
        return;
    }
    container.innerHTML = tabsHtml + d.assignments.map(o => renderCard(o, type)).join('');
}

// ── Render order card ──
function renderCard(o, type) {
    const name = o.customer_name || 'Customer';
    const phone = o.phone || o.customer_phone || '';
    const addr = o.pickup_address || o.shop_address || 'Address not provided';
    const date = new Date(o.created_at || o.return_date).toLocaleDateString('en-IN', { day:'2-digit', month:'short', year:'numeric' });
    const amount = o.total_amount ? `₹${o.total_amount}` : '';

    let cardClass = { pickups:'pickup', inprocess:'in-process', deliveries:'delivery', completed:'done', returns:'return' }[type] || '';
    let statusBadge = '';
    let actions = '';

    if (type === 'pickups') {
        if (o.status === 'assigned') {
            statusBadge = '<span class="badge b-blue">New Assignment</span>';
            actions = `
                <button class="btn-action btn-primary" onclick="acceptOrder(${o.id})"><i class="material-icons-outlined" style="font-size:16px">check_circle</i> Accept Order</button>
                <button class="btn-action btn-cancel" onclick="cancelPickup(${o.id})" style="margin-top:6px;"><i class="material-icons-outlined" style="font-size:16px">cancel</i> Cancel</button>
            `;
        } else {
            statusBadge = '<span class="badge b-amber">Pending Pickup</span>';
            actions = `
                <button class="btn-action btn-pickup" onclick="fulfillPickup(${o.id})"><i class="material-icons-outlined" style="font-size:16px">shopping_bag</i> Picked Up</button>
                <button class="btn-action btn-cancel" onclick="cancelPickup(${o.id})" style="margin-top:6px;"><i class="material-icons-outlined" style="font-size:16px">undo</i> Release Order</button>
            `;
        }
    } else if (type === 'inprocess') {
        statusBadge = '<span class="badge b-amber">In Processing</span>';
        actions = `<button class="btn-action btn-otp" onclick="openReadyModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">local_shipping</i> Mark Ready</button>`;
    } else if (type === 'deliveries') {
        statusBadge = '<span class="badge b-blue">Out for Delivery</span>';
        actions = `
            <button class="btn-action btn-qr" onclick="openQRModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">qr_code_scanner</i> Scan QR</button>
            <button class="btn-action btn-otp" onclick="openOTPModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">password</i> Verify PIN</button>
            <button class="btn-action btn-bypass" onclick="openBypassModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px">camera_alt</i> Bypass</button>
        `;
    } else if (type === 'completed') {
        statusBadge = '<span class="badge b-green">Delivered ✓</span>';
        actions = amount;
    } else if (type === 'returns') {
        statusBadge = '<span class="badge b-red">Return Pickup</span>';
        actions = `<div style="font-size:0.82rem;color:#64748b;max-width:200px">${o.reason||'No reason given'}</div>`;
    }

    const btnCall = phone ? `<a href="tel:${phone}" style="background:#e0e7ff; color:#4f46e5; text-decoration:none; padding:4px 8px; border-radius:6px; font-weight:600; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;"><i class="material-icons-outlined" style="font-size:14px">phone</i> Call Cust</a>` : '';
    const btnNav = o.lat && o.lng ? `<a href="https://www.google.com/maps/dir/?api=1&destination=${o.lat},${o.lng}" target="_blank" style="background:#dcfce7; color:#16a34a; text-decoration:none; padding:4px 8px; border-radius:6px; font-weight:600; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;"><i class="material-icons-outlined" style="font-size:14px">navigation</i> Navigate</a>` : '';
    const utilBtns = (btnCall || btnNav) ? `<div style="display:flex;gap:5px;margin-top:6px;margin-bottom:6px;">${btnCall}${btnNav}</div>` : '';

    return `
        <div class="order-card ${cardClass}">
            <div class="card-row">
                <div class="card-info">
                    <h4>Order #${o.id} — ${name}</h4>
                    <p><i class="material-icons-outlined">location_on</i> ${addr}</p>
                    <p><i class="material-icons-outlined">calendar_today</i> ${date}</p>
                    ${amount ? `<p><i class="material-icons-outlined">payments</i> <strong>${amount}</strong></p>` : ''}
                </div>
                <div class="card-actions">
                    ${statusBadge}
                    ${utilBtns}
                    ${actions}
                </div>
            </div>
        </div>
    `;
}

// ── Actions ──
async function toggleOnline(val) {
    const d = await api('toggle_online', { is_online: val });
    if (!d.success) { showToast('❌ ' + d.message, 'error'); document.getElementById('onlineToggle').checked = !val; }
    else { showToast('✅ Status updated', 'success'); }
}

async function acceptOrder(orderId) {
    const d = await api('accept_order', { order_id: orderId });
    if (d.success) { loadSection('pickups'); loadStats(); }
    else showToast('❌ ' + d.message, 'error');
}

async function fulfillPickup(orderId) {
    customConfirm('Mark Picked Up', 'Confirm you have physically collected the items for Order #' + orderId + '?', async () => {
        const d = await api('fulfill_pickup', { order_id: orderId });
        if (d.success) { 
            showToast('✅ Pickup confirmed! Order sent to processing.', 'success');
            loadSection('pickups'); 
            loadStats(); 
        } else {
            showToast('❌ ' + d.message, 'error');
        }
    });
}

async function cancelPickup(orderId) {
    customConfirm('Release Order', `Release Order #${orderId} back to the pool? You will be unassigned from this order.`, async () => {
        const d = await api('cancel_pickup', { order_id: orderId });
        if (d.success) {
            showToast('↩️ Order released back to pool.', 'info');
            loadSection('pickups');
            loadStats();
        } else {
            showToast('❌ ' + d.message, 'error');
        }
    });
}

// ── Inline Toast for Delivery Dashboard ──
function showToast(msg, type = 'info') {
    const colors = { success: '#10b981', error: '#ef4444', info: '#6366f1' };
    const el = document.createElement('div');
    el.style.cssText = `position:fixed;bottom:24px;right:24px;background:${colors[type]||'#334155'};color:white;padding:0.85rem 1.4rem;border-radius:12px;font-weight:700;font-size:0.9rem;z-index:9999;box-shadow:0 8px 24px rgba(0,0,0,0.2);animation:fadeUp .3s ease;`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 3500);
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
    } catch(e) { showToast('❌ Server error', 'error'); }
    btn.textContent = '✓ Mark Ready'; btn.disabled = false;
}



function openOTPModal(orderId, isMarket = false) {
    document.getElementById('otpOrderId').value = orderId;
    document.getElementById('otpInput').value = '';
    document.getElementById('otpMsg').style.display = 'none';
    
    let mktInput = document.getElementById('otpIsMarket');
    if (!mktInput) {
        mktInput = document.createElement('input');
        mktInput.type = 'hidden';
        mktInput.id = 'otpIsMarket';
        document.getElementById('otpModal').appendChild(mktInput);
    }
    mktInput.value = isMarket ? 'true' : 'false';
    
    openModal('otpModal');
}

async function submitOTP() {
    const orderId = document.getElementById('otpOrderId').value;
    const otp = document.getElementById('otpInput').value;
    const isMarketInput = document.getElementById('otpIsMarket');
    const isMarket = isMarketInput ? isMarketInput.value === 'true' : false;
    
    const btn = document.getElementById('btnSubmitOtp');
    const msg = document.getElementById('otpMsg');
    
    if (!otp) { msg.textContent = 'Please enter the PIN.'; msg.style.color = '#ef4444'; msg.style.display = 'block'; return; }
    
    btn.textContent = 'Verifying…'; btn.disabled = true; msg.style.display = 'none';
    
    if (isMarket) {
        try {
            const r = await fetch('../api/update_marketplace_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
                body: JSON.stringify({ action:'update_status', order_id: orderId, status: 'delivered', otp })
            });
            const d = await r.json();
            msg.textContent = d.message; msg.style.color = d.success ? '#10b981' : '#ef4444'; msg.style.display = 'block';
            if (d.success) setTimeout(()=>{ closeModal('otpModal'); loadSection('deliveries'); loadSection('completed'); loadStats(); }, 1200);
        } catch(e) { msg.textContent = 'Server Error'; msg.style.display = 'block'; }
    } else {
        const d = await api('complete_delivery_otp', { order_id: orderId, otp });
        msg.textContent = d.message; msg.style.color = d.success ? '#10b981' : '#ef4444'; msg.style.display = 'block';
        if (d.success) setTimeout(()=>{ closeModal('otpModal'); loadSection('deliveries'); loadSection('completed'); loadStats(); }, 1200);
    }
    
    btn.textContent = '✓ Verify & Complete'; btn.disabled = false;
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

async function loadMarketDataForSection(sectionType, container) {
    const tabsHtml = renderSysTabs(sectionType);
    try {
        const r = await fetch('../api/marketplace_orders.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'get_orders', csrf_token: csrf })
        });
        const d = await r.json();
        if (!d.success) { container.innerHTML = tabsHtml + '<div class="empty-state"><p>' + d.message + '</p></div>'; return; }
        
        // Filter orders by section type
        let filteredOrders = [];
        if (sectionType === 'pickups') {
            filteredOrders = d.orders.filter(o => o.status === 'assigned');
        } else if (sectionType === 'deliveries') {
            filteredOrders = d.orders.filter(o => o.status === 'picked_up' || o.status === 'out_for_delivery');
        } else if (sectionType === 'completed') {
            filteredOrders = d.orders.filter(o => o.status === 'delivered');
        }

        if (filteredOrders.length === 0) {
            container.innerHTML = tabsHtml + `<div class="empty-state"><i class="material-icons-outlined">inbox</i><p>No marketplace orders here.</p></div>`;
            return;
        }

        container.innerHTML = tabsHtml + filteredOrders.map(o => {
            const items = o.items.map(i => `${i.quantity}x ${i.name}`).join(', ');
            const statusMap = {
                'assigned': { lbl: 'Assigned', btn: 'Mark Picked Up', next: 'picked_up', icon: 'shopping_bag', color: 'b-blue' },
                'picked_up': { lbl: 'Picked Up', btn: 'Mark Out for Delivery', next: 'out_for_delivery', icon: 'local_shipping', color: 'b-purple' },
                'out_for_delivery': { lbl: 'Out for Delivery', btn: 'Mark Delivered ✓', next: 'delivered', icon: 'check_circle', color: 'b-purple' }
            };

            let actions = '';
            let sBadge = `<span class="badge ${statusMap[o.status] ? statusMap[o.status].color : 'b-gray'}">${statusMap[o.status] ? statusMap[o.status].lbl : o.status}</span>`;
            if (o.status === 'delivered') sBadge = '<span class="badge b-green">Delivered ✓</span>';
            else if (o.status === 'cancelled') sBadge = '<span class="badge b-red">Cancelled</span>';

            if (statusMap[o.status]) {
                const conf = statusMap[o.status];
                if (conf.next === 'delivered') {
                    actions = `<button class="btn-action btn-primary" style="background:#f59e0b; border-color:#d97706;" onclick="openOTPModal(${o.id}, true)"><i class="material-icons-outlined" style="font-size:16px">verified_user</i> Verify PIN to Deliver</button>`;
                } else {
                    actions = `<button class="btn-action btn-primary" onclick="updateMktStatus(${o.id}, '${conf.next}')"><i class="material-icons-outlined" style="font-size:16px">${conf.icon}</i> ${conf.btn}</button>`;
                }
            }

            const isDone = o.status === 'delivered' || o.status === 'cancelled';
            const btnCall = o.user_phone ? `<a href="tel:${o.user_phone}" style="background:#e0e7ff; color:#4f46e5; text-decoration:none; padding:4px 8px; border-radius:6px; font-weight:600; font-size:0.75rem; display:inline-flex; align-items:center; gap:4px;"><i class="material-icons-outlined" style="font-size:14px">phone</i> Call</a>` : '';
            
            const timeInfo = [];
            timeInfo.push(`<p><i class="material-icons-outlined">calendar_today</i> Placed: ${new Date(o.created_at).toLocaleDateString()}</p>`);
            if (o.picked_up_at) timeInfo.push(`<p><i class="material-icons-outlined">shopping_bag</i> Picked Up: ${new Date(o.picked_up_at).toLocaleString('en-US',{hour:'numeric',minute:'numeric',day:'numeric',month:'short'})}</p>`);
            if (o.delivered_at) timeInfo.push(`<p><i class="material-icons-outlined">check_circle</i> Delivered: ${new Date(o.delivered_at).toLocaleString('en-US',{hour:'numeric',minute:'numeric',day:'numeric',month:'short'})}</p>`);

            return `
                <div class="order-card ${isDone ? 'done' : 'pickup'}">
                    <div class="card-row">
                        <div class="card-info">
                            <h4>Mkt Order #${o.id} — ${o.user_name}</h4>
                            <p style="color:#0f172a;font-weight:600;margin-bottom:6px;">Items: ${items}</p>
                            <p><i class="material-icons-outlined">payments</i> <strong>₹${parseFloat(o.total_amount).toFixed(2)}</strong> (${o.payment_type.toUpperCase()})</p>
                            ${timeInfo.join('')}
                        </div>
                        <div class="card-actions">
                            ${sBadge}
                            ${btnCall ? `<div style="margin:4px 0">${btnCall}</div>` : ''}
                            ${actions}
                        </div>
                    </div>
                </div>
            `;
        }).join('');

    } catch(e) { container.innerHTML = tabsHtml + '<div class="empty-state"><p>Error connecting</p></div>'; }
}

async function updateMktStatus(id, newStatus) {
    customConfirm('Update Mkt Status', 'Update marketplace order status to ' + newStatus.replace(/_/g, ' ') + '?', async () => {
        try {
            const r = await fetch('../api/update_marketplace_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'update_status', order_id: id, status: newStatus, csrf_token: csrf })
            });
            const d = await r.json();
            if (d.success) {
                showToast('✅ ' + d.message, 'success');
                const activeTab = document.querySelector('.menu-item.active');
                if(activeTab) loadSection(activeTab.id.replace('nav-',''));
            } else {
                showToast('❌ ' + d.message, 'error');
            }
        } catch(e) { showToast('❌ Error updating order', 'error'); }
    });
}
</script>
</body>
</html>
