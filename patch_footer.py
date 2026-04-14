import re

with open('public_footer.php', 'r', encoding='utf-8') as f:
    content = f.read()

# ─── PATCH 1: Replace the staff HTML form with Firebase OTP form ───────────
old_html = '''            <!-- STAFF LOGIN -->
            <div id="staffStep" class="auth-view">
                <div style="text-align:center; margin-bottom:1.5rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a">Staff Portal</h2>
                    <p style="margin-bottom:0; font-size:0.9rem; color:#64748b">Enter your assigned access details.</p>
                </div>

                <form id="staffLoginForm">
                    <div class="form-group">
                        <label>Registered Phone</label>
                        <div class="phone-input-wrap">
                            <input type="tel" id="staffPhone" placeholder="10-digit phone" required maxlength="10" pattern="[0-9]{10}" style="padding-left:1.2rem;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Access Code / PIN</label>
                        <div class="phone-input-wrap">
                            <input type="password" id="staffOtp" placeholder="Enter Dummy OTP" required style="padding-left:1.2rem;">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Verify &amp; Enter</button>
                    <button type="button" class="btn btn-ghost" onclick="toggleStaffLogin()" style="margin-top:0.5rem;">← Back</button>
                    <p id="staffError" style="color: #ef4444; margin-top: 1rem; display: none; font-weight: 600; text-align:center; font-size:0.85rem; background:#fee2e2; padding:0.5rem; border-radius:8px;"></p>
                </form>
            </div>'''

new_html = '''            <!-- STAFF LOGIN -->
            <div id="staffStep" class="auth-view">
                <div style="text-align:center; margin-bottom:1.5rem;">
                    <i class="material-icons-outlined" style="font-size:2.5rem; color:#6366f1; margin-bottom:0.5rem;">badge</i>
                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a">Staff Portal</h2>
                    <p style="margin-bottom:0; font-size:0.9rem; color:#64748b">Enter your registered phone to receive OTP via SMS.</p>
                </div>

                <div class="form-group">
                    <label>Registered Phone</label>
                    <div class="phone-input-wrap">
                        <span style="padding:0 0.75rem;color:#64748b;font-weight:700;">+91</span>
                        <input type="tel" id="staffPhone" placeholder="10-digit phone" maxlength="10" inputmode="numeric" style="padding-left:0.25rem;">
                    </div>
                </div>

                <div id="staffRecaptcha" style="margin-bottom:0.5rem;"></div>

                <button type="button" id="staffSendOtpBtn" class="btn btn-primary" style="margin-bottom:0.75rem;">
                    Send OTP <i class="material-icons-outlined" style="font-size:1.1rem;vertical-align:middle;">arrow_forward</i>
                </button>
                <button type="button" class="btn btn-ghost" onclick="toggleStaffLogin()">&#8592; Back</button>
                <p id="staffError" style="color:#ef4444;margin-top:1rem;display:none;font-weight:600;text-align:center;font-size:0.85rem;background:#fee2e2;padding:0.5rem;border-radius:8px;"></p>
            </div>'''

if old_html in content:
    content = content.replace(old_html, new_html, 1)
    print('PATCH 1: HTML replaced OK')
else:
    print('PATCH 1 FAILED: old HTML not found')

# ─── PATCH 2: Replace dummy_login JS with Firebase OTP handler ────────────
old_js = '''        // Staff Dummy Login
        const staffLoginForm = document.getElementById('staffLoginForm');
        if (staffLoginForm) {
            staffLoginForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const btn = e.target.querySelector('button[type="submit"]');
                const err = document.getElementById('staffError');
                btn.innerHTML = 'Verifying...'; btn.disabled = true; err.style.display = 'none';

                try {
                    const res = await fetch('api/auth.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'dummy_login', phone: document.getElementById('staffPhone').value, otp: document.getElementById('staffOtp').value })
                    });
                    const result = await res.json();
                    if (result.success) window.location.href = result.redirect;
                    else { err.innerText = result.message; err.style.display = 'block'; btn.innerHTML = 'Verify &amp; Enter'; btn.disabled = false; }
                } catch (e) {
                    err.innerText = 'Server connection failed.'; err.style.display = 'block'; btn.innerHTML = 'Verify &amp; Enter'; btn.disabled = false;
                }
            });
        }'''

new_js = '''        // Staff Firebase OTP Login
        const staffSendOtpBtn = document.getElementById('staffSendOtpBtn');
        let staffConfirmationResult = null;
        let staffRecaptchaVerifier = null;

        if (staffSendOtpBtn) {
            function initStaffRecaptcha() {
                if (staffRecaptchaVerifier) return;
                try { staffRecaptchaVerifier = new firebase.auth.RecaptchaVerifier('staffRecaptcha', { 'size': 'invisible' }); }
                catch(e) { console.error('Staff recaptcha init error', e); }
            }

            staffSendOtpBtn.addEventListener('click', () => {
                if (!auth) {
                    const err = document.getElementById('staffError');
                    err.innerText = 'Firebase not ready. Refresh the page.';
                    err.style.display = 'block'; return;
                }
                initStaffRecaptcha();
                const rawPhone = document.getElementById('staffPhone').value.replace(/\\D/g, '');
                const staffErr = document.getElementById('staffError');
                staffErr.style.display = 'none';
                if (rawPhone.length !== 10 || !/^[6-9]/.test(rawPhone)) {
                    staffErr.innerText = 'Enter a valid 10-digit mobile number starting with 6-9.';
                    staffErr.style.display = 'block'; return;
                }
                const phone = '+91' + rawPhone;
                staffSendOtpBtn.innerHTML = 'Sending\u2026'; staffSendOtpBtn.disabled = true;

                auth.signInWithPhoneNumber(phone, staffRecaptchaVerifier)
                    .then((result) => {
                        staffConfirmationResult = result;
                        window._staffOtpMode = true;
                        showView('otpStep');
                        staffSendOtpBtn.innerHTML = 'Send OTP'; staffSendOtpBtn.disabled = false;
                    }).catch((error) => {
                        console.error('Staff SMS Error', error);
                        staffErr.innerText = error.message || 'Failed to send OTP. Try again.';
                        staffErr.style.display = 'block';
                        staffSendOtpBtn.innerHTML = 'Send OTP'; staffSendOtpBtn.disabled = false;
                        if (staffRecaptchaVerifier) { staffRecaptchaVerifier.render().then(w => grecaptcha.reset(w)).catch(()=>{}); }
                    });
            });
        }'''

if old_js in content:
    content = content.replace(old_js, new_js, 1)
    print('PATCH 2: JS replaced OK')
else:
    print('PATCH 2 FAILED: old JS not found')

with open('public_footer.php', 'w', encoding='utf-8') as f:
    f.write(content)

print('Done.')
