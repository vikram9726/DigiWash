#!/usr/bin/env python3
"""patch_staff_otp.py — Replace dummy PIN login with real Firebase Phone OTP for staff."""
import sys, os

FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'public_footer.php')

with open(FILE, 'r', encoding='utf-8') as f:
    src = f.read()

# ── PATCH 1: Staff HTML block (lines 101-127) ─────────────────────────────
OLD_HTML = (
    '            <!-- STAFF LOGIN -->\n'
    '            <div id="staffStep" class="auth-view">\n'
    '                <div style="text-align:center; margin-bottom:1.5rem;">\n'
    '                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a">Staff Portal</h2>\n'
    '                    <p style="margin-bottom:0; font-size:0.9rem; color:#64748b">Enter your assigned access details.</p>\n'
    '                </div>\n'
    '\n'
    '                <form id="staffLoginForm">\n'
    '                    <div class="form-group">\n'
    '                        <label>Registered Phone</label>\n'
    '                        <div class="phone-input-wrap">\n'
    '                            <input type="tel" id="staffPhone" placeholder="10-digit phone" required maxlength="10" pattern="[0-9]{10}" style="padding-left:1.2rem;">\n'
    '                        </div>\n'
    '                    </div>\n'
    '                    <div class="form-group">\n'
    '                        <label>Access Code / PIN</label>\n'
    '                        <div class="phone-input-wrap">\n'
    '                            <input type="password" id="staffOtp" placeholder="Enter Dummy OTP" required style="padding-left:1.2rem;">\n'
    '                        </div>\n'
    '                    </div>\n'
    '                    \n'
    '                    <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Verify & Enter</button>\n'
    '                    <button type="button" class="btn btn-ghost" onclick="toggleStaffLogin()" style="margin-top:0.5rem;">\u2190 Back</button>\n'
    '                    <p id="staffError" style="color: #ef4444; margin-top: 1rem; display: none; font-weight: 600; text-align:center; font-size:0.85rem; background:#fee2e2; padding:0.5rem; border-radius:8px;"></p>\n'
    '                </form>\n'
    '            </div>'
)

NEW_HTML = (
    '            <!-- STAFF LOGIN -->\n'
    '            <div id="staffStep" class="auth-view">\n'
    '                <div style="text-align:center; margin-bottom:1.5rem;">\n'
    '                    <i class="material-icons-outlined" style="font-size:2.5rem; color:#6366f1; background:rgba(99,102,241,0.1); padding:0.8rem; border-radius:20px;">badge</i>\n'
    '                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a; margin-top:0.8rem;">Staff Portal</h2>\n'
    '                    <p style="margin-bottom:0; font-size:0.9rem; color:#64748b">Enter your registered phone to receive a real OTP.</p>\n'
    '                </div>\n'
    '\n'
    '                <form id="staffLoginForm">\n'
    '                    <div class="form-group">\n'
    '                        <label>Registered Mobile Number</label>\n'
    '                        <div class="phone-input-wrap">\n'
    '                            <span class="prefix">+91</span>\n'
    '                            <input type="tel" id="staffPhone" placeholder="10-digit number" required maxlength="10" pattern="[0-9]{10}" inputmode="numeric"\n'
    '                                oninput="this.value=this.value.replace(/[^0-9]/g,\'\').substring(0,10)">\n'
    '                        </div>\n'
    '                    </div>\n'
    '\n'
    '                    <div id="staff-recaptcha-container" style="margin-bottom:1rem;"></div>\n'
    '\n'
    '                    <button type="submit" class="btn btn-primary" id="staffSendOtpBtn" style="margin-top:0.5rem;">\n'
    '                        Send OTP <i class="material-icons-outlined" style="font-size:1.1rem;">send</i>\n'
    '                    </button>\n'
    '                    <button type="button" class="btn btn-ghost" onclick="toggleStaffLogin()" style="margin-top:0.5rem;">&#8592; Back</button>\n'
    '                    <p id="staffError" style="color: #ef4444; margin-top: 1rem; display: none; font-weight: 600; text-align:center; font-size:0.85rem; background:#fee2e2; padding:0.5rem; border-radius:8px;"></p>\n'
    '                </form>\n'
    '            </div>\n'
    '\n'
    '            <!-- STAFF OTP STEP -->\n'
    '            <div id="staffOtpStep" class="auth-view">\n'
    '                <div style="text-align:center; margin-bottom:1.5rem;">\n'
    '                    <i class="material-icons-outlined" style="font-size:3rem; color:#6366f1; margin-bottom:0.5rem;">admin_panel_settings</i>\n'
    '                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a;">Staff Verification</h2>\n'
    '                    <p id="staffOtpSubtext" style="margin-bottom:0; font-size:0.9rem; color:#64748b">Enter the 6-digit code sent to your phone.</p>\n'
    '                </div>\n'
    '\n'
    '                <form id="staffOtpForm">\n'
    '                    <div class="form-group">\n'
    '                        <input type="text" id="staffOtpInput" placeholder="Enter 6-digit OTP" required maxlength="6" pattern="[0-9]{6}" inputmode="numeric"\n'
    '                            style="width:100%; border:2px solid #e2e8f0; border-radius:12px; text-align:center; font-size:1.5rem; letter-spacing:8px; font-weight:800; padding:1rem; outline:none; transition:border-color 0.3s; color:#0f172a;"\n'
    '                            onfocus="this.style.borderColor=\'#6366f1\'" onblur="this.style.borderColor=\'#e2e8f0\'"\n'
    '                            oninput="this.value=this.value.replace(/[^0-9]/g,\'\').substring(0,6)">\n'
    '                    </div>\n'
    '                    <button type="submit" class="btn btn-success" id="staffVerifyOtpBtn">Verify &amp; Enter Portal</button>\n'
    '                    <button type="button" class="btn btn-ghost" onclick="showView(\'staffStep\')" style="margin-top:0.5rem;">&#8592; Change Number</button>\n'
    '                    <p id="staffOtpError" style="color:#ef4444; margin-top:1rem; display:none; font-weight:600; text-align:center; font-size:0.85rem; background:#fee2e2; padding:0.5rem; border-radius:8px;"></p>\n'
    '                </form>\n'
    '            </div>'
)

# ── PATCH 2: Dummy JS block (lines 335-357) ───────────────────────────────
OLD_JS = (
    "        // Staff Dummy Login\n"
    "        const staffLoginForm = document.getElementById('staffLoginForm');\n"
    "        if (staffLoginForm) {\n"
    "            staffLoginForm.addEventListener('submit', async (e) => {\n"
    "                e.preventDefault();\n"
    "                const btn = e.target.querySelector('button[type=\"submit\"]');\n"
    "                const err = document.getElementById('staffError');\n"
    "                btn.innerHTML = 'Verifying...'; btn.disabled = true; err.style.display = 'none';\n"
    "\n"
    "                try {\n"
    "                    const res = await fetch('api/auth.php', {\n"
    "                        method: 'POST',\n"
    "                        headers: { 'Content-Type': 'application/json' },\n"
    "                        body: JSON.stringify({ action: 'dummy_login', phone: document.getElementById('staffPhone').value, otp: document.getElementById('staffOtp').value })\n"
    "                    });\n"
    "                    const result = await res.json();\n"
    "                    if (result.success) window.location.href = result.redirect;\n"
    "                    else { err.innerText = result.message; err.style.display = 'block'; btn.innerHTML = 'Verify & Enter'; btn.disabled = false; }\n"
    "                } catch (e) {\n"
    "                    err.innerText = 'Server connection failed.'; err.style.display = 'block'; btn.innerHTML = 'Verify & Enter'; btn.disabled = false;\n"
    "                }\n"
    "            });\n"
    "        }"
)

NEW_JS = (
    "        // \u2500\u2500 STAFF FIREBASE PHONE OTP LOGIN \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\n"
    "        let staffConfirmationResult = null;\n"
    "        let staffRecaptchaVerifier  = null;\n"
    "\n"
    "        function initStaffRecaptcha() {\n"
    "            if (staffRecaptchaVerifier || !auth) return;\n"
    "            try {\n"
    "                staffRecaptchaVerifier = new firebase.auth.RecaptchaVerifier('staff-recaptcha-container', { 'size': 'invisible' });\n"
    "            } catch(e) { console.error('Staff reCAPTCHA init error:', e); }\n"
    "        }\n"
    "\n"
    "        // Init reCAPTCHA when staff panel opens\n"
    "        const _origToggleStaff = toggleStaffLogin;\n"
    "        toggleStaffLogin = function() {\n"
    "            _origToggleStaff();\n"
    "            if (document.getElementById('staffStep').classList.contains('active')) {\n"
    "                initStaffRecaptcha();\n"
    "            }\n"
    "        };\n"
    "\n"
    "        // Step 1: Send real OTP to staff phone\n"
    "        const staffLoginForm = document.getElementById('staffLoginForm');\n"
    "        if (staffLoginForm) {\n"
    "            staffLoginForm.addEventListener('submit', (e) => {\n"
    "                e.preventDefault();\n"
    "                const err = document.getElementById('staffError');\n"
    "                err.style.display = 'none';\n"
    "                if (!auth) {\n"
    "                    err.innerText = 'System error: Firebase not configured. Contact admin.';\n"
    "                    err.style.display = 'block'; return;\n"
    "                }\n"
    "                const rawPhone = document.getElementById('staffPhone').value.replace(/\\D/g, '');\n"
    "                if (rawPhone.length !== 10 || !/^[6-9]/.test(rawPhone)) {\n"
    "                    err.innerText = 'Please enter a valid 10-digit number starting with 6-9.';\n"
    "                    err.style.display = 'block'; return;\n"
    "                }\n"
    "                const phone = '+91' + rawPhone;\n"
    "                const btn   = document.getElementById('staffSendOtpBtn');\n"
    "                const orig  = btn.innerHTML;\n"
    "                btn.innerHTML = 'Sending OTP...'; btn.disabled = true;\n"
    "                initStaffRecaptcha();\n"
    "\n"
    "                auth.signInWithPhoneNumber(phone, staffRecaptchaVerifier)\n"
    "                    .then((result) => {\n"
    "                        staffConfirmationResult = result;\n"
    "                        document.getElementById('staffOtpSubtext').innerText =\n"
    "                            'Code sent to +91 ' + rawPhone + '. Enter it below.';\n"
    "                        showView('staffOtpStep');\n"
    "                        btn.innerHTML = orig; btn.disabled = false;\n"
    "                    })\n"
    "                    .catch((error) => {\n"
    "                        console.error('Staff SMS Error:', error);\n"
    "                        let msg = error.message || 'Failed to send SMS. Try again.';\n"
    "                        if (error.code === 'auth/too-many-requests')    msg = 'Too many attempts. Wait a few minutes.';\n"
    "                        if (error.code === 'auth/invalid-phone-number') msg = 'This number is not registered as staff.';\n"
    "                        err.innerText = msg; err.style.display = 'block';\n"
    "                        if (staffRecaptchaVerifier) {\n"
    "                            staffRecaptchaVerifier.render().then(w => grecaptcha.reset(w)).catch(() => {});\n"
    "                        }\n"
    "                        btn.innerHTML = orig; btn.disabled = false;\n"
    "                    });\n"
    "            });\n"
    "        }\n"
    "\n"
    "        // Step 2: Verify OTP \u2192 firebase_login \u2192 role-based redirect (admin / delivery)\n"
    "        const staffOtpForm = document.getElementById('staffOtpForm');\n"
    "        if (staffOtpForm) {\n"
    "            staffOtpForm.addEventListener('submit', (e) => {\n"
    "                e.preventDefault();\n"
    "                const otpCode = document.getElementById('staffOtpInput').value.trim();\n"
    "                const btn = document.getElementById('staffVerifyOtpBtn');\n"
    "                const err = document.getElementById('staffOtpError');\n"
    "                err.style.display = 'none';\n"
    "                if (!staffConfirmationResult) {\n"
    "                    err.innerText = 'Session expired. Go back and resend the OTP.';\n"
    "                    err.style.display = 'block'; return;\n"
    "                }\n"
    "                btn.innerHTML = 'Verifying...'; btn.disabled = true;\n"
    "                staffConfirmationResult.confirm(otpCode)\n"
    "                    .then((result) => {\n"
    "                        btn.innerHTML = 'Authorizing...';\n"
    "                        return result.user.getIdToken().then(idToken =>\n"
    "                            sendTokenToBackend(idToken, result.user.phoneNumber, null, null, btn)\n"
    "                        );\n"
    "                    })\n"
    "                    .catch((error) => {\n"
    "                        console.error('Staff OTP verify error:', error);\n"
    "                        let msg = 'Invalid OTP code. Please try again.';\n"
    "                        if (error.code === 'auth/code-expired') msg = 'OTP expired. Go back and request a new one.';\n"
    "                        err.innerText = msg; err.style.display = 'block';\n"
    "                        btn.innerHTML = 'Verify &amp; Enter Portal'; btn.disabled = false;\n"
    "                    });\n"
    "            });\n"
    "        }\n"
    "        // \u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500\u2500"
)

# ── Verify and apply ──────────────────────────────────────────────────────
p1_ok = OLD_HTML in src
p2_ok = OLD_JS   in src

if not p1_ok:
    print("❌ PATCH 1 (HTML) not found.")
    sys.exit(1)
if not p2_ok:
    print("❌ PATCH 2 (JS) not found.")
    sys.exit(1)

src = src.replace(OLD_HTML, NEW_HTML, 1)
src = src.replace(OLD_JS,   NEW_JS,   1)

with open(FILE, 'w', encoding='utf-8', newline='') as f:
    f.write(src)

print("✅ PATCH 1 (Staff HTML)  — applied. PIN field removed, OTP flow added.")
print("✅ PATCH 2 (Staff JS)    — applied. dummy_login replaced with Firebase Phone OTP.")
print("✅ Done! Staff login now uses real Firebase Phone OTP.")
