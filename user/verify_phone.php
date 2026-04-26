<?php
require_once '../config.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) { header('Location: ../index.php'); exit; }
if ($_SESSION['role'] !== 'customer')  { header('Location: ../index.php'); exit; }

$stmt = $pdo->prepare("SELECT phone, phone_verified, firebase_uid, name, email FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$isPendingPhone = strpos($user['phone'] ?? '', 'GOOGLE_PENDING_') === 0;

// If phone already verified → go to dashboard
if (!$isPendingPhone && !empty($user['phone_verified'])) {
    header('Location: dashboard.php'); exit;
}

$csrfToken      = $_SESSION['csrf_token'] ?? '';
$firebaseConfig = getFirebaseConfigJs();
$turnstileSiteKey = getTurnstileSiteKey();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash — Verify Phone Number</title>
    <meta name="description" content="Verify your phone number to access DigiWash services.">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root {
            --primary: #6366f1; --primary-dark: #4f46e5;
            --success: #10b981; --danger: #ef4444;
            --text: #0f172a; --muted: #64748b; --border: #e2e8f0;
            --bg: #f8fafc; --white: #ffffff;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #1e1b4b 0%, #312e81 40%, #4c1d95 100%);
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 1.5rem;
        }
        .card {
            background: var(--white); border-radius: 24px;
            padding: 2.5rem 2rem; width: 100%; max-width: 440px;
            box-shadow: 0 25px 60px rgba(0,0,0,0.35);
            animation: slideUp 0.5s cubic-bezier(0.16,1,0.3,1);
        }
        @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
        .brand { display:flex; align-items:center; gap:10px; margin-bottom:1.75rem; }
        .brand-icon { width:44px; height:44px; background:linear-gradient(135deg,#6366f1,#4f46e5); border-radius:12px;
            display:flex; align-items:center; justify-content:center; color:white; font-size:1.3rem; }
        .brand-name { font-size:1.3rem; font-weight:800; color:var(--text); }
        .heading { font-size:1.5rem; font-weight:800; color:var(--text); margin-bottom:.5rem; }
        .subtext { font-size:.88rem; color:var(--muted); line-height:1.5; margin-bottom:1.75rem; }
        .info-banner {
            background:#f0fdf4; border:1.5px solid #bbf7d0; border-radius:12px;
            padding:1rem; margin-bottom:1.5rem; display:flex; gap:10px; align-items:flex-start;
        }
        .info-banner i { color:#16a34a; font-size:1.2rem; margin-top:1px; flex-shrink:0; }
        .info-banner p { font-size:.82rem; color:#166534; font-weight:600; line-height:1.4; }
        .form-group { margin-bottom:1.1rem; }
        .form-group label { display:block; font-size:.82rem; font-weight:700; color:var(--text); margin-bottom:.4rem; }
        .input-wrap { position:relative; }
        .input-prefix { position:absolute; left:12px; top:50%; transform:translateY(-50%);
            font-size:.9rem; font-weight:700; color:var(--muted); pointer-events:none; }
        .form-control {
            width:100%; padding:.7rem .9rem .7rem 2.2rem; border:1.5px solid var(--border);
            border-radius:10px; font-size:.95rem; font-family:'Inter',sans-serif;
            outline:none; transition:border-color .2s, box-shadow .2s;
        }
        .form-control:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,.15); }
        .form-control.no-prefix { padding-left:.9rem; }
        .btn {
            width:100%; padding:.85rem; border:none; border-radius:12px; font-size:1rem;
            font-weight:700; cursor:pointer; font-family:'Inter',sans-serif;
            display:flex; align-items:center; justify-content:center; gap:8px;
            transition:all .2s;
        }
        .btn-primary { background:linear-gradient(135deg,#6366f1,#4f46e5); color:white; }
        .btn-primary:hover:not(:disabled) { transform:translateY(-1px); box-shadow:0 8px 20px rgba(99,102,241,.4); }
        .btn-primary:disabled { opacity:.6; cursor:not-allowed; transform:none; }
        .btn-ghost { background:#f1f5f9; color:var(--muted); margin-top:.5rem; }
        .btn-ghost:hover { background:#e2e8f0; }
        .step { display:none; }
        .step.active { display:block; }
        .otp-grid { display:grid; grid-template-columns:repeat(6,1fr); gap:8px; margin-bottom:1rem; }
        .otp-box {
            text-align:center; padding:.75rem .25rem; border:1.5px solid var(--border);
            border-radius:10px; font-size:1.3rem; font-weight:800; color:var(--text);
            outline:none; transition:border-color .2s, box-shadow .2s;
        }
        .otp-box:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(99,102,241,.15); }
        .resend-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:1.25rem; }
        .resend-row span { font-size:.82rem; color:var(--muted); }
        .resend-btn { font-size:.82rem; font-weight:700; color:var(--primary); background:none; border:none; cursor:pointer; padding:0; }
        .resend-btn:disabled { color:var(--muted); cursor:not-allowed; }
        .msg { font-size:.84rem; font-weight:600; padding:.6rem .9rem; border-radius:8px; margin-bottom:.75rem; display:none; }
        .msg.success { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; display:block; }
        .msg.error   { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; display:block; }
        .progress-dots { display:flex; gap:6px; justify-content:center; margin-bottom:1.5rem; }
        .dot { width:8px; height:8px; border-radius:50%; background:var(--border); transition:background .3s; }
        .dot.active { background:var(--primary); }
        /* Turnstile widget overrides — keep it compact */
        .cf-turnstile { margin-bottom: .9rem; }
        .spinner { width:18px; height:18px; border:2px solid rgba(255,255,255,.4); border-top-color:white;
            border-radius:50%; animation:spin .7s linear infinite; display:inline-block; }
        @keyframes spin { to { transform:rotate(360deg); } }
        .logout-link { text-align:center; margin-top:1.25rem; }
        .logout-link a { font-size:.82rem; color:var(--muted); text-decoration:none; }
        .logout-link a:hover { color:var(--danger); }
        /* Hidden Firebase reCAPTCHA anchor — must exist in DOM but invisible */
        #recaptcha-container { display:none !important; }
    </style>
</head>
<body>
<div class="card">
    <div class="brand">
        <div class="brand-icon"><i class="material-icons-outlined">local_laundry_service</i></div>
        <span class="brand-name">DigiWash</span>
    </div>

    <div class="progress-dots">
        <div class="dot active" id="dot1"></div>
        <div class="dot" id="dot2"></div>
        <div class="dot" id="dot3"></div>
    </div>

    <div class="info-banner">
        <i class="material-icons-outlined">verified</i>
        <p>Phone verification is required for order updates &amp; delivery coordination. This number cannot be changed later.</p>
    </div>

    <!-- Step 1: Enter Phone -->
    <div class="step active" id="step1">
        <div class="heading">Verify Your Phone</div>
        <p class="subtext">Hello, <strong><?= htmlspecialchars($user['name'] ?: $user['email'] ?: 'there') ?></strong>! Enter your mobile number to receive an OTP.</p>
        <div id="msg1" class="msg"></div>
        <div class="form-group">
            <label>Mobile Number</label>
            <div class="input-wrap">
                <span class="input-prefix">+91</span>
                <input type="tel" id="phoneInput" class="form-control" placeholder="10-digit number"
                    maxlength="10" inputmode="numeric" oninput="this.value=this.value.replace(/\D/g,'').substring(0,10)">
            </div>
        </div>

        <!-- Cloudflare Turnstile widget — visible security check -->
        <div class="cf-turnstile" id="cfTurnstile"
             data-sitekey="<?= $turnstileSiteKey ?>"
             data-callback="onTurnstileSuccess"
             data-error-callback="onTurnstileError"
             data-expired-callback="onTurnstileExpired"
             data-theme="light"
             data-size="normal"></div>

        <!-- Hidden reCAPTCHA anchor (required by Firebase SDK — kept invisible) -->
        <div id="recaptcha-container"></div>

        <button class="btn btn-primary" id="sendOtpBtn" onclick="sendOTP()" disabled>
            <i class="material-icons-outlined" style="font-size:1.1rem;">lock</i> Verify &amp; Send OTP
        </button>
        <p style="font-size:.75rem;color:var(--muted);text-align:center;margin-top:.6rem;">
            Protected by Cloudflare Turnstile
        </p>
    </div>

    <!-- Step 2: Enter OTP -->
    <div class="step" id="step2">
        <div class="heading">Enter OTP</div>
        <p class="subtext" id="otpSubtext">Enter the 6-digit OTP sent to your number.</p>
        <div id="msg2" class="msg"></div>
        <div class="otp-grid" id="otpGrid">
            <input type="tel" class="otp-box" maxlength="1" inputmode="numeric" oninput="otpNav(this,0)">
            <input type="tel" class="otp-box" maxlength="1" inputmode="numeric" oninput="otpNav(this,1)">
            <input type="tel" class="otp-box" maxlength="1" inputmode="numeric" oninput="otpNav(this,2)">
            <input type="tel" class="otp-box" maxlength="1" inputmode="numeric" oninput="otpNav(this,3)">
            <input type="tel" class="otp-box" maxlength="1" inputmode="numeric" oninput="otpNav(this,4)">
            <input type="tel" class="otp-box" maxlength="1" inputmode="numeric" oninput="otpNav(this,5)">
        </div>
        <div class="resend-row">
            <span>Didn't get it? <span id="resendTimer"></span></span>
            <button class="resend-btn" id="resendBtn" disabled onclick="resendOTP()">Resend OTP</button>
        </div>
        <button class="btn btn-primary" id="verifyBtn" onclick="verifyOTP()">
            <i class="material-icons-outlined" style="font-size:1.1rem;">check_circle</i> Verify &amp; Continue
        </button>
        <button class="btn btn-ghost" onclick="goBackToStep1()">← Change Number</button>
    </div>

    <!-- Step 3: Success -->
    <div class="step" id="step3">
        <div style="text-align:center; padding:1rem 0;">
            <div style="width:72px;height:72px;background:#f0fdf4;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1.25rem;">
                <i class="material-icons-outlined" style="font-size:2.5rem;color:#16a34a;">verified</i>
            </div>
            <div class="heading" style="margin-bottom:.5rem;">Phone Verified! 🎉</div>
            <p class="subtext">Your number has been securely linked. Redirecting to your dashboard…</p>
            <div class="spinner" style="margin:1rem auto;border-color:rgba(99,102,241,.3);border-top-color:#6366f1;width:30px;height:30px;border-width:3px;"></div>
        </div>
    </div>

    <div class="logout-link">
        <a href="../api/auth.php" onclick="doLogout(event)">← Sign out and use a different account</a>
    </div>
</div>

<!-- Cloudflare Turnstile SDK -->
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

<!-- Firebase SDKs (loaded BEFORE our script so firebase is available) -->
<script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>

<script>
const csrfToken = "<?= $csrfToken ?>";
let confirmationResult = null;
let resendCountdown    = null;
let phoneNumber        = '';
let turnstileToken     = '';   // set by Cloudflare callback
let recaptchaVerifier  = null;

// ── Firebase ──
const firebaseConfig = <?= $firebaseConfig ?>;
firebase.initializeApp(firebaseConfig);
const auth = firebase.auth();

// Firebase invisible reCAPTCHA (required by Firebase Phone Auth internally — kept hidden)
function initFirebaseRecaptcha() {
    if (recaptchaVerifier) return;
    try {
        recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
            'size': 'invisible',
            'callback': () => {}
        });
        recaptchaVerifier.render();
    } catch(e) { console.error('Firebase reCAPTCHA init:', e); }
}
initFirebaseRecaptcha();

// ── Turnstile callbacks ──────────────────────────────────────────────────────
function onTurnstileSuccess(token) {
    turnstileToken = token;
    document.getElementById('sendOtpBtn').disabled = false;
}
function onTurnstileError() {
    turnstileToken = '';
    document.getElementById('sendOtpBtn').disabled = true;
    showMsg(document.getElementById('msg1'), 'error', 'Security check failed. Please refresh the page and try again.');
}
function onTurnstileExpired() {
    turnstileToken = '';
    document.getElementById('sendOtpBtn').disabled = true;
    showMsg(document.getElementById('msg1'), 'error', 'Security check expired. Please complete it again.');
    if (typeof turnstile !== 'undefined') turnstile.reset();
}

// ── Show message ──
function showMsg(el, type, text) {
    el.className = 'msg ' + type;
    el.textContent = text;
    el.style.display = 'block';
}
function clearMsg(el) { el.className = 'msg'; el.style.display = 'none'; }

// ── OTP box navigation ──
function otpNav(el, idx) {
    el.value = el.value.replace(/\D/g, '');
    const boxes = document.querySelectorAll('.otp-box');
    if (el.value && idx < 5) boxes[idx + 1].focus();
    const code = Array.from(boxes).map(b => b.value).join('');
    if (code.length === 6) verifyOTP();
}

document.getElementById('otpGrid').addEventListener('keydown', (e) => {
    const boxes = Array.from(document.querySelectorAll('.otp-box'));
    const idx   = boxes.indexOf(e.target);
    if (e.key === 'Backspace' && !e.target.value && idx > 0) boxes[idx - 1].focus();
});

// ── Send OTP ──────────────────────────────────────────────────────────────────
async function sendOTP() {
    const input   = document.getElementById('phoneInput').value.trim();
    const msgEl   = document.getElementById('msg1');
    const btn     = document.getElementById('sendOtpBtn');
    clearMsg(msgEl);

    if (input.length !== 10 || !/^[6-9]/.test(input)) {
        showMsg(msgEl, 'error', 'Please enter a valid 10-digit mobile number starting with 6–9.');
        return;
    }

    if (!turnstileToken) {
        showMsg(msgEl, 'error', 'Please complete the security check first.');
        return;
    }

    phoneNumber = '+91' + input;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Verifying security…';

    // ── Step A: Verify Turnstile token on backend + rate limit check ──
    try {
        const tsRes = await fetch('../api/verify_turnstile.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ cf_token: turnstileToken, phone: input })
        });
        const tsData = await tsRes.json();
        if (!tsData.success) {
            showMsg(msgEl, 'error', tsData.message || 'Bot verification failed. Please try again.');
            btn.innerHTML = '<i class="material-icons-outlined" style="font-size:1.1rem;">lock</i> Verify & Send OTP';
            btn.disabled = false;
            // Reset Turnstile widget so user can retry
            if (typeof turnstile !== 'undefined') { turnstile.reset(); turnstileToken = ''; }
            return;
        }
    } catch (err) {
        showMsg(msgEl, 'error', 'Network error during security check. Please try again.');
        btn.innerHTML = '<i class="material-icons-outlined" style="font-size:1.1rem;">lock</i> Verify & Send OTP';
        btn.disabled = false;
        return;
    }

    // ── Step B: Send OTP via Firebase ────────────────────────────────────────
    btn.innerHTML = '<span class="spinner"></span> Sending OTP…';
    try {
        confirmationResult = await auth.signInWithPhoneNumber(phoneNumber, recaptchaVerifier);
        goToStep2(input);
        startResendTimer(60);
    } catch (err) {
        console.error(err);
        let errMsg = 'Failed to send OTP. Try again.';
        if (err.code === 'auth/too-many-requests') errMsg = 'Too many attempts. Wait a few minutes.';
        if (err.code === 'auth/invalid-phone-number') errMsg = 'Invalid phone number format.';
        showMsg(msgEl, 'error', errMsg);
        // Reset Firebase reCAPTCHA and Turnstile
        if (recaptchaVerifier) { recaptchaVerifier.clear(); recaptchaVerifier = null; initFirebaseRecaptcha(); }
        if (typeof turnstile !== 'undefined') { turnstile.reset(); turnstileToken = ''; }
        btn.disabled = true; // re-disabled until Turnstile completes again
        btn.innerHTML = '<i class="material-icons-outlined" style="font-size:1.1rem;">lock</i> Verify & Send OTP';
    }
}

// ── Verify OTP ────────────────────────────────────────────────────────────────
async function verifyOTP() {
    const boxes = document.querySelectorAll('.otp-box');
    const code  = Array.from(boxes).map(b => b.value).join('');
    const msgEl = document.getElementById('msg2');
    const btn   = document.getElementById('verifyBtn');
    clearMsg(msgEl);

    if (code.length !== 6) {
        showMsg(msgEl, 'error', 'Enter all 6 digits.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner"></span> Verifying…';

    try {
        await confirmationResult.confirm(code);
        await savePhoneToBackend();
    } catch (err) {
        console.error(err);
        let errMsg = 'Incorrect OTP. Try again.';
        if (err.code === 'auth/code-expired') errMsg = 'OTP expired. Tap Resend to get a new one.';
        if (err.code === 'auth/invalid-verification-code') errMsg = 'Wrong OTP. Please check and retry.';
        showMsg(msgEl, 'error', errMsg);
        btn.disabled = false;
        btn.innerHTML = '<i class="material-icons-outlined" style="font-size:1.1rem;">check_circle</i> Verify & Continue';
    }
}

// ── Save verified phone to backend ───────────────────────────────────────────
async function savePhoneToBackend() {
    const cleanPhone = phoneNumber.replace('+91', '');
    try {
        const res = await fetch('../api/auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ action: 'save_verified_phone', phone: cleanPhone, csrf_token: csrfToken })
        });
        const json = await res.json();
        if (json.success) {
            goToStep3();
            setTimeout(() => { window.location.href = 'dashboard.php'; }, 2200);
        } else {
            showMsg(document.getElementById('msg2'), 'error', json.message || 'Could not save phone. Try again.');
            document.getElementById('verifyBtn').disabled = false;
            document.getElementById('verifyBtn').innerHTML = '<i class="material-icons-outlined" style="font-size:1.1rem;">check_circle</i> Verify & Continue';
        }
    } catch {
        showMsg(document.getElementById('msg2'), 'error', 'Network error. Please try again.');
        document.getElementById('verifyBtn').disabled = false;
        document.getElementById('verifyBtn').innerHTML = '<i class="material-icons-outlined" style="font-size:1.1rem;">check_circle</i> Verify & Continue';
    }
}

// ── Resend OTP ────────────────────────────────────────────────────────────────
async function resendOTP() {
    document.getElementById('resendBtn').disabled = true;
    try {
        confirmationResult = await auth.signInWithPhoneNumber(phoneNumber, recaptchaVerifier);
        startResendTimer(60);
        showMsg(document.getElementById('msg2'), 'success', 'New OTP sent!');
    } catch(err) {
        let errMsg = 'Failed to resend OTP. Try again in a moment.';
        if (err.code === 'auth/too-many-requests') errMsg = 'Too many requests. Please wait a few minutes.';
        showMsg(document.getElementById('msg2'), 'error', errMsg);
        document.getElementById('resendBtn').disabled = false;
    }
}

function startResendTimer(secs) {
    clearInterval(resendCountdown);
    document.getElementById('resendBtn').disabled = true;
    const timerEl = document.getElementById('resendTimer');
    let t = secs;
    timerEl.textContent = `(${t}s)`;
    resendCountdown = setInterval(() => {
        t--;
        timerEl.textContent = t > 0 ? `(${t}s)` : '';
        if (t <= 0) {
            clearInterval(resendCountdown);
            document.getElementById('resendBtn').disabled = false;
        }
    }, 1000);
}

// ── Step navigation ──
function goToStep2(input) {
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step2').classList.add('active');
    document.getElementById('otpSubtext').textContent = `OTP sent to +91 ${input}`;
    document.getElementById('dot1').classList.remove('active');
    document.getElementById('dot2').classList.add('active');
    document.querySelectorAll('.otp-box')[0].focus();
}
function goToStep3() {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step3').classList.add('active');
    document.getElementById('dot2').classList.remove('active');
    document.getElementById('dot3').classList.add('active');
}
function goBackToStep1() {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step1').classList.add('active');
    document.getElementById('dot2').classList.remove('active');
    document.getElementById('dot1').classList.add('active');
    document.querySelectorAll('.otp-box').forEach(b => b.value = '');
    clearInterval(resendCountdown);
}

// ── Logout ──
function doLogout(e) {
    e.preventDefault();
    fetch('../api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify({ action: 'logout', csrf_token: csrfToken })
    }).then(() => { window.location.href = '../index.php'; });
}
</script>
</body>
</html>
