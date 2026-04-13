    <?php if (!$isIndex): ?>
        </div> <!-- End of content wrapper for non-index pages -->
    <?php endif; ?>

    <!-- Full Footer -->
    <footer class="bg-slate-900 text-slate-300 py-12">
        <div class="max-w-7xl mx-auto px-6 md:px-12 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-blue-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30">
                        <i class="material-icons-outlined">local_laundry_service</i>
                    </div>
                    <span class="font-black text-xl text-white">DigiWash</span>
                </div>
                <p class="text-sm leading-relaxed mb-4 text-slate-400">Premium laundry and textile care delivered directly to your shop. Trusted by households and local shopkeepers.</p>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Company</h3>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li><a href="about.php" class="hover:text-indigo-400 transition">About Us</a></li>
                    <li><a href="contact.php" class="hover:text-indigo-400 transition">Contact Us</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Legal</h3>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li><a href="privacy-policy.php" class="hover:text-indigo-400 transition">Privacy Policy</a></li>
                    <li><a href="terms.php" class="hover:text-indigo-400 transition">Terms & Conditions</a></li>
                    <li><a href="refund-policy.php" class="hover:text-indigo-400 transition">Refund & Cancellation</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Contact</h3>
                <ul class="space-y-2 text-sm text-slate-400">
                    <li><a href="javascript:void(0)" onclick="openContactModal()" class="flex items-center gap-2 mb-2 hover:text-indigo-400 transition"><i class="material-icons-outlined text-base">message</i> Contact Admin via Message</a></li>
                    <li class="flex items-center gap-2 mb-2"><i class="material-icons-outlined text-base">phone</i> +91 9726232915</li>
                    <li class="flex items-start gap-2"><i class="material-icons-outlined text-base">location_on</i> India</li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-6 md:px-12 mt-12 pt-8 border-t border-slate-800 text-sm text-center text-slate-500">
            &copy; 2026 DigiWash. All rights reserved.
        </div>
    </footer>

    <!-- Auth Modal -->
    <div class="auth-modal-overlay" id="authModalOverlay">
        <div class="auth-modal-box">
            <button class="auth-modal-close" onclick="closeAuthModal()">
                <i class="material-icons-outlined">close</i>
            </button>
            
            <div style="text-align:center; margin-bottom:1.5rem;">
                <i class="material-icons-outlined" style="font-size: 2.5rem; color: #6366f1; background: rgba(99, 102, 241, 0.1); padding: 0.8rem; border-radius: 20px;">local_laundry_service</i>
            </div>

            <!-- CUSTOMER LOGIN -->
            <div id="loginStep" class="auth-view active">
                <div style="text-align:center; margin-bottom:1.5rem;">
                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a">Welcome Back</h2>
                    <p style="margin-bottom:0; font-size:0.9rem; color:#64748b">Log in or create a new account.</p>
                </div>

                <button class="google-btn" onclick="startGoogleLogin()">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48" width="20" height="20" style="margin-right:12px;flex-shrink:0;">
                        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
                        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
                        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.18 1.48-4.97 2.31-8.16 2.31-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                    </svg>
                    Continue with Google
                </button>

                <div class="divider">or</div>

                <form id="phoneLoginForm">
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <div class="phone-input-wrap">
                            <span class="prefix">+91</span>
                            <input type="tel" id="phone" name="phone" placeholder="Enter 10-digit number"
                                required maxlength="10" pattern="[0-9]{10}" inputmode="numeric"
                                oninput="this.value=this.value.replace(/[^0-9]/g,'').substring(0,10)">
                        </div>
                    </div>
                    
                    <div id="recaptcha-container" style="margin-bottom: 1rem;"></div>
                    
                    <button type="submit" class="btn btn-primary" id="loginBtn">
                        Send OTP <i class="material-icons-outlined" style="font-size:1.1rem;">arrow_forward</i>
                    </button>
                    <p id="errorMsg" style="color: #ef4444; margin-top: 1rem; display: none; font-weight: 600; text-align:center; font-size:0.85rem; background:#fee2e2; padding:0.5rem; border-radius:8px;"></p>
                    
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <span style="color:var(--l-text-muted); font-size:0.85rem;">Are you a team member?</span><br>
                        <a href="javascript:void(0)" onclick="toggleStaffLogin()" style="color: #6366f1; font-weight: 700; font-size: 0.85rem; text-decoration:none;">Staff / Delivery Login →</a>
                    </div>
                </form>
            </div>

            <!-- STAFF LOGIN -->
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
                    
                    <button type="submit" class="btn btn-primary" style="margin-top:0.5rem;">Verify & Enter</button>
                    <button type="button" class="btn btn-ghost" onclick="toggleStaffLogin()" style="margin-top:0.5rem;">← Back</button>
                    <p id="staffError" style="color: #ef4444; margin-top: 1rem; display: none; font-weight: 600; text-align:center; font-size:0.85rem; background:#fee2e2; padding:0.5rem; border-radius:8px;"></p>
                </form>
            </div>

            <!-- OTP VERIFICATION -->
            <div id="otpStep" class="auth-view">
                <div style="text-align:center; margin-bottom:1.5rem;">
                    <i class="material-icons-outlined" style="font-size:3rem; color:#6366f1; margin-bottom:0.5rem;">phonelink_lock</i>
                    <h2 style="font-size: 1.5rem; margin-bottom:0.2rem; color:#0f172a">Verify Phone</h2>
                    <p style="margin-bottom:0; font-size:0.9rem; color:#64748b">Enter the 6-digit code sent via SMS.</p>
                </div>

                <form id="otpForm">
                    <div class="form-group">
                        <input type="text" id="otpInput" placeholder="Enter 6-digit OTP" required pattern="[0-9]{6}" style="width: 100%; border: 2px solid #e2e8f0; border-radius: 12px; text-align:center; font-size:1.5rem; letter-spacing:8px; font-weight:800; padding:1rem; outline:none; transition:border-color 0.3s; color:#0f172a;" onfocus="this.style.borderColor='#6366f1'" onblur="this.style.borderColor='#e2e8f0'">
                    </div>
                    
                    <button type="submit" class="btn btn-success" id="verifyOtpBtn">Secure Login</button>
                    <button type="button" class="btn btn-ghost" onclick="location.reload()" style="margin-top:0.5rem;">Cancel</button>
                </form>
            </div>
        </div>
    </div>

    <!-- GSAP Animations & Modal Logic -->
    <script>
        // Modal Logic
        function openAuthModal() { document.getElementById('authModalOverlay').classList.add('open'); }
        function closeAuthModal() { document.getElementById('authModalOverlay').classList.remove('open'); }
        
        function showView(id) {
            document.querySelectorAll('.auth-view').forEach(v => v.classList.remove('active'));
            document.getElementById(id).classList.add('active');
        }
        function toggleStaffLogin() {
            const isStaffVisible = document.getElementById('staffStep').classList.contains('active');
            showView(isStaffVisible ? 'loginStep' : 'staffStep');
        }

        // Navbar Scroll Effect
        window.addEventListener('scroll', () => {
            const nav = document.getElementById('navbar');
            if (!nav) return;
            // Only apply scroll styling on index page where we start transparent
            if (<?= $isIndex ? 'true' : 'false' ?>) {
                if(window.scrollY > 50) {
                    nav.classList.add('bg-white/80', 'backdrop-blur-md', 'shadow-sm', 'border-b', 'border-slate-200');
                    nav.classList.remove('bg-transparent', 'py-6');
                    nav.classList.add('py-4');
                } else {
                    nav.classList.remove('bg-white/80', 'backdrop-blur-md', 'shadow-sm', 'border-b', 'border-slate-200', 'py-4');
                    nav.classList.add('bg-transparent', 'py-6');
                }
            }
        });

        // GSAP Animations
        document.addEventListener('DOMContentLoaded', () => {
            if (typeof gsap !== 'undefined' && typeof ScrollTrigger !== 'undefined') {
                gsap.registerPlugin(ScrollTrigger);

                // Hero animations (only if elements exist)
                if (document.querySelector('.gsap-hero')) {
                    const tl = gsap.timeline();
                    tl.fromTo('.gsap-hero', {y: 30, opacity: 0}, {y: 0, opacity: 1, duration: 0.8, stagger: 0.1, ease: 'power3.out', delay: 0.2});
                }
                
                // Floating cards
                if (document.querySelector('.gsap-float-1')) {
                    gsap.to('.gsap-float-1', {y: -15, duration: 2.5, yoyo: true, repeat: -1, ease: 'sine.inOut'});
                    gsap.to('.gsap-float-2', {y: 15, duration: 3, yoyo: true, repeat: -1, ease: 'sine.inOut', delay: 0.5});
                }

                // Scroll animations
                gsap.utils.toArray('.gsap-fade-up').forEach(elem => {
                    gsap.fromTo(elem, {y: 40, opacity: 0}, {
                        y: 0, opacity: 1, duration: 0.8, ease: 'power3.out',
                        scrollTrigger: { trigger: elem, start: 'top 85%' }
                    });
                });

                if (document.querySelector('.gsap-stagger')) {
                    gsap.fromTo('.gsap-stagger', {y: 30, opacity: 0}, {
                        y: 0, opacity: 1, duration: 0.6, stagger: 0.15, ease: 'power3.out',
                        scrollTrigger: { trigger: '#how', start: 'top 80%' }
                    });
                }

                if (document.querySelector('.gsap-stagger-2')) {
                    gsap.fromTo('.gsap-stagger-2', {y: 30, opacity: 0}, {
                        y: 0, opacity: 1, duration: 0.6, stagger: 0.1, ease: 'power3.out',
                        scrollTrigger: { trigger: '#features', start: 'top 80%' }
                    });
                }

                if (document.querySelector('.gsap-scale')) {
                    gsap.fromTo('.gsap-scale', {scale: 0.95, opacity: 0}, {
                        scale: 1, opacity: 1, duration: 0.8, ease: 'power2.out',
                        scrollTrigger: { trigger: '.gsap-scale', start: 'top 85%' }
                    });
                }
            }
        });
    </script>

    <!-- FIREBASE AUTH LOGIC -->
    <script>
        // Initialize Firebase
        const firebaseConfig = <?= getFirebaseConfigJs() ?>;
        let auth = null;
        try {
            if (firebaseConfig && firebaseConfig.apiKey) {
                firebase.initializeApp(firebaseConfig);
                auth = firebase.auth();
                window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', { 'size': 'invisible' });
            } else {
                console.error("Firebase config is missing API Key. Check your .env file.");
            }
        } catch(e) {
            console.error("Firebase Init Error:", e);
        }
        
        const errorMsg = document.getElementById('errorMsg');
        function showError(text) { if(errorMsg) { errorMsg.innerText = text; errorMsg.style.display = 'block'; } else alert(text); }
        let confirmationResult = null;

        // Customer Phone Login
        const phoneLoginForm = document.getElementById('phoneLoginForm');
        if (phoneLoginForm) {
            phoneLoginForm.addEventListener('submit', (e) => {
                e.preventDefault();
                if (!auth) {
                    showError("System configuration error (Firebase missing). Contact Admin.");
                    return;
                }
                const rawPhone = document.getElementById('phone').value.replace(/\D/g, '');
                if (rawPhone.length !== 10 || !/^[6-9]/.test(rawPhone)) {
                    showError('Please enter a valid 10-digit mobile number starting with 6-9.');
                    return;
                }
                const phone = '+91' + rawPhone;
                const btnElement = document.getElementById('loginBtn');
                const originalText = btnElement.innerHTML;
                btnElement.innerHTML = 'Sending...'; btnElement.disabled = true;
                if(errorMsg) errorMsg.style.display = 'none';

                auth.signInWithPhoneNumber(phone, window.recaptchaVerifier)
                    .then((result) => {
                        confirmationResult = result;
                        showView('otpStep');
                    }).catch((error) => {
                        console.error("SMS Error", error);
                        showError(error.message || "Failed to send SMS. Refresh and try again.");
                        window.recaptchaVerifier.render().then(w => grecaptcha.reset(w));
                        btnElement.innerHTML = originalText; btnElement.disabled = false;
                    });
            });
        }

        // OTP Verify
        const otpForm = document.getElementById('otpForm');
        if (otpForm) {
            otpForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const otpCode = document.getElementById('otpInput').value;
                const btnElement = document.getElementById('verifyOtpBtn');
                btnElement.innerHTML = 'Verifying...'; btnElement.disabled = true;

                confirmationResult.confirm(otpCode).then((result) => {
                    result.user.getIdToken().then(idToken => sendTokenToBackend(idToken, result.user.phoneNumber, null, null, btnElement));
                }).catch((error) => {
                    showError("Invalid OTP code.");
                    btnElement.innerHTML = 'Secure Login'; btnElement.disabled = false;
                });
            });
        }

        // Google SignIn
        function startGoogleLogin() {
            if (!auth) {
                showError("System configuration error (Firebase missing). Contact Admin.");
                return;
            }
            const provider = new firebase.auth.GoogleAuthProvider();
            auth.signInWithPopup(provider).then((result) => {
                const user = result.user;
                user.getIdToken().then(idToken => sendTokenToBackend(idToken, user.phoneNumber, user.email, user.displayName, document.querySelector('.google-btn')));
            }).catch((error) => showError(error.message));
        }

        // Backend Sync
        async function sendTokenToBackend(idToken, phone, email, name, btnElement) {
            if(btnElement) btnElement.innerHTML = 'Authorizing...';
            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'firebase_login', idToken, phone, email, name })
                });
                const result = await response.json();
                if (result.success) window.location.href = result.redirect;
                else {
                    showError(result.message || 'An error occurred server-side');
                    if(btnElement) { btnElement.innerHTML = 'Retry'; btnElement.disabled = false; }
                }
            } catch (error) {
                showError('Server error. Please try again.');
                if(btnElement) { btnElement.innerHTML = 'Retry'; btnElement.disabled = false; }
            }
        }

        // Staff Dummy Login
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
                    else { err.innerText = result.message; err.style.display = 'block'; btn.innerHTML = 'Verify & Enter'; btn.disabled = false; }
                } catch (e) {
                    err.innerText = 'Server connection failed.'; err.style.display = 'block'; btn.innerHTML = 'Verify & Enter'; btn.disabled = false;
                }
            });
        }
    </script>

    <!-- Contact Modal -->
    <div id="contactModal" class="fixed inset-0 z-[200] hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl scale-95 transition-transform duration-300 relative" id="contactModalContent">
            <button onclick="closeContactModal()" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 transition">
                <i class="material-icons-outlined text-lg">close</i>
            </button>
            <div class="p-6 md:p-8">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-5">
                    <i class="material-icons-outlined text-2xl">support_agent</i>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 mb-1">Message Admin</h3>
                <p class="text-slate-500 text-sm mb-6">Have a question or suggestion? We'll get back to you soon.</p>
                <form id="contactFormMain" onsubmit="submitContactMain(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Your Name</label>
                            <input type="text" id="cName" required autocomplete="off" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition" placeholder="John Doe">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Mobile Number</label>
                            <input type="tel" id="cPhone" required pattern="[0-9]{10}" maxlength="10" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition" placeholder="10-digit number">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Message</label>
                            <textarea id="cMsg" required rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition resize-none" placeholder="How can we help?"></textarea>
                        </div>
                    </div>
                    <div id="cError" class="mt-4 text-red-500 text-sm font-semibold hidden bg-red-50 p-3 rounded-lg text-center"></div>
                    <div id="cSuccess" class="mt-4 text-emerald-600 text-sm font-semibold hidden bg-emerald-50 p-3 rounded-lg text-center"></div>
                    <button type="submit" id="cSubmitBtn" class="w-full mt-5 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 rounded-xl flex justify-center items-center gap-2 transition shadow-lg shadow-indigo-600/30">
                        <span>Send Message</span><i class="material-icons-outlined text-base">send</i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const cModal = document.getElementById('contactModal');
        const cModalBox = document.getElementById('contactModalContent');
        function openContactModal() {
            cModal.classList.remove('hidden');
            setTimeout(() => { cModal.classList.remove('opacity-0'); cModalBox.classList.remove('scale-95'); cModalBox.classList.add('scale-100'); }, 10);
            const form = document.getElementById('contactFormMain');
            if (form) form.reset();
            const cErr = document.getElementById('cError');
            if (cErr) cErr.classList.add('hidden');
            const cSucc = document.getElementById('cSuccess');
            if (cSucc) cSucc.classList.add('hidden');
            const btn = document.getElementById('cSubmitBtn');
            if (btn) btn.style.display = 'flex';
        }
        function closeContactModal() {
            cModal.classList.add('opacity-0'); cModalBox.classList.remove('scale-100'); cModalBox.classList.add('scale-95');
            setTimeout(() => cModal.classList.add('hidden'), 300);
        }
        async function submitContactMain(e) {
            e.preventDefault();
            const cErr = document.getElementById('cError'), cOk = document.getElementById('cSuccess'), cBtn = document.getElementById('cSubmitBtn');
            cErr.classList.add('hidden');
            cBtn.disabled = true; cBtn.innerHTML = '<i class="material-icons-outlined" style="animation:spin 1s linear infinite">autorenew</i> Sending...';
            try {
                const res = await fetch('api/contact.php', {
                    method:'POST', headers:{'Content-Type':'application/json'},
                    body: JSON.stringify({ name: document.getElementById('cName').value.trim(), phone: document.getElementById('cPhone').value.trim(), message: document.getElementById('cMsg').value.trim() })
                });
                const r = await res.json();
                if (r.success) { cOk.innerText = r.message; cOk.classList.remove('hidden'); cBtn.style.display='none'; setTimeout(closeContactModal, 2500); }
                else { cErr.innerText = r.message || 'Failed.'; cErr.classList.remove('hidden'); cBtn.disabled=false; cBtn.innerHTML='<span>Send Message</span><i class="material-icons-outlined text-base">send</i>'; }
            } catch(err) { cErr.innerText='Network error.'; cErr.classList.remove('hidden'); cBtn.disabled=false; cBtn.innerHTML='<span>Send Message</span><i class="material-icons-outlined text-base">send</i>'; }
        }
        cModal.addEventListener('click', e => { if(e.target === cModal) closeContactModal(); });
        // Spinner keyframe
        if (!document.querySelector('style[data-spinner]')) {
            const _s = document.createElement('style'); _s.setAttribute('data-spinner', 'true'); _s.textContent='@keyframes spin{to{transform:rotate(360deg)}}'; document.head.appendChild(_s);
        }
    </script>
</body>
</html>
