<?php
require_once 'config.php';

// If logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') header('Location: admin/dashboard.php');
    elseif ($_SESSION['role'] === 'delivery') header('Location: delivery/dashboard.php');
    else header('Location: user/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash — Laundry Reimagined</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/landing.css">
    
    <!-- Firebase SDK (Compat version for simplicity) -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    
    <!-- GSAP for animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
</head>
<body class="landing-page">

    <div class="l-bg-blob top-left"></div>
    <div class="l-bg-blob bottom-right"></div>

    <nav class="l-nav" id="navbar">
        <a href="#" class="l-logo">
            <i class="material-icons-outlined" style="color:var(--l-primary)">local_laundry_service</i>
            <span>DigiWash</span>
        </a>
        <div class="l-nav-links">
            <a href="#how">How it works</a>
            <a href="#features">Features</a>
        </div>
        <div>
            <button class="l-btn l-btn-primary" onclick="openAuthModal()">Login / Signup</button>
        </div>
    </nav>

    <header class="l-hero">
        <div class="l-float-container">
            <div class="l-glass-card fc-1 gsap-float-1">
                <div class="fc-icon"><i class="material-icons-outlined">local_shipping</i></div>
                <div class="fc-title">Out for delivery</div>
                <div class="fc-sub">Arriving in 10 mins</div>
            </div>
            <div class="l-glass-card fc-2 gsap-float-2">
                <div class="fc-icon" style="color:#10b981; background:rgba(16,185,129,0.2)"><i class="material-icons-outlined">check_circle</i></div>
                <div class="fc-title">Washed & Ironed</div>
                <div class="fc-sub">Ready for pickup</div>
            </div>
        </div>

        <div class="l-badge gsap-hero">
            <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--l-primary);box-shadow:0 0 10px var(--l-primary)"></span>
            The Future of Laundry
        </div>
        <h1 class="gsap-hero">Laundry <br><span class="gradient-text">Reimagined.</span></h1>
        <p class="gsap-hero">Schedule a pickup, track it live, and get your clothes back fresh, crisp, and ready to wear. All from your phone.</p>
        <button class="l-btn l-btn-primary gsap-hero" style="font-size:1.1rem; padding:1rem 2.5rem" onclick="openAuthModal()">Get Started Today</button>
    </header>

    <section id="how" class="l-section">
        <div class="l-section-header gsap-fade-up">
            <h2>How it works</h2>
            <p>Three simple steps to clean clothes without leaving your house.</p>
        </div>
        <div class="l-steps">
            <div class="l-step gsap-stagger">
                <div class="l-step-num">1</div>
                <h3>Schedule</h3>
                <p>Choose a time that works for you. Our partner will pick up your laundry right from your doorstep.</p>
            </div>
            <div class="l-step gsap-stagger">
                <div class="l-step-num">2</div>
                <h3>We Clean</h3>
                <p>Your clothes are professionally washed, ironed, and folded with the highest quality standards.</p>
            </div>
            <div class="l-step gsap-stagger">
                <div class="l-step-num">3</div>
                <h3>Delivered</h3>
                <p>Track your delivery live. Fresh, pristine clothes delivered back to you in record time.</p>
            </div>
        </div>
    </section>

    <section id="features" class="l-section">
        <div class="l-section-header gsap-fade-up">
            <h2>Why Choose DigiWash?</h2>
            <p>Designed for busy professionals who value quality and time.</p>
        </div>
        <div class="l-features">
            <div class="l-feature gsap-stagger-2">
                <i class="material-icons-outlined l-feature-icon">bolt</i>
                <h3>Lightning Fast</h3>
                <p>Get your clothes back within 24-48 hours. No more waiting days for dry cleaning.</p>
            </div>
            <div class="l-feature gsap-stagger-2">
                <i class="material-icons-outlined l-feature-icon">phone_iphone</i>
                <h3>Live Tracking</h3>
                <p>Know exactly where your clothes are at all times with our real-time GPS tracking.</p>
            </div>
            <div class="l-feature gsap-stagger-2">
                <i class="material-icons-outlined l-feature-icon">shield</i>
                <h3>Premium Care</h3>
                <p>We use eco-friendly solvents and handle your delicate fabrics with absolute precision.</p>
            </div>
            <div class="l-feature gsap-stagger-2">
                <i class="material-icons-outlined l-feature-icon">payments</i>
                <h3>Transparent Pricing</h3>
                <p>No hidden fees. Pay seamlessly online or utilize our customizable Pay Later plans.</p>
            </div>
        </div>
    </section>

    <section class="l-section">
        <div class="l-cta gsap-scale">
            <h2>Ready for a fresh start?</h2>
            <p>Join thousands of users who have automated their laundry with DigiWash.</p>
            <button class="l-btn l-btn-primary" style="background:#fff;color:var(--l-bg);box-shadow:0 10px 25px rgba(0,0,0,0.2);padding:1rem 2.5rem;font-size:1.1rem" onclick="openAuthModal()">Create Free Account</button>
        </div>
    </section>

    <footer class="footer">
        &copy; <?= date('Y') ?> DigiWash Inc. All rights reserved. Premium SaaS Laundry.
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
            if(window.scrollY > 50) document.getElementById('navbar').classList.add('scrolled');
            else document.getElementById('navbar').classList.remove('scrolled');
        });

        // GSAP Animations
        document.addEventListener('DOMContentLoaded', () => {
            gsap.registerPlugin(ScrollTrigger);

            // Hero animations
            const tl = gsap.timeline();
            tl.fromTo('.gsap-hero', {y: 30, opacity: 0}, {y: 0, opacity: 1, duration: 0.8, stagger: 0.1, ease: 'power3.out', delay: 0.2});
            
            // Floating cards
            gsap.to('.gsap-float-1', {y: -20, duration: 2, yoyo: true, repeat: -1, ease: 'sine.inOut'});
            gsap.to('.gsap-float-2', {y: 20, duration: 2.5, yoyo: true, repeat: -1, ease: 'sine.inOut', delay: 0.5});

            // Scroll animations
            gsap.utils.toArray('.gsap-fade-up').forEach(elem => {
                gsap.fromTo(elem, {y: 40, opacity: 0}, {
                    y: 0, opacity: 1, duration: 0.8, ease: 'power3.out',
                    scrollTrigger: { trigger: elem, start: 'top 80%' }
                });
            });

            gsap.fromTo('.gsap-stagger', {y: 40, opacity: 0}, {
                y: 0, opacity: 1, duration: 0.6, stagger: 0.15, ease: 'power3.out',
                scrollTrigger: { trigger: '.l-steps', start: 'top 80%' }
            });

            gsap.fromTo('.gsap-stagger-2', {y: 40, opacity: 0}, {
                y: 0, opacity: 1, duration: 0.6, stagger: 0.1, ease: 'power3.out',
                scrollTrigger: { trigger: '.l-features', start: 'top 80%' }
            });

            gsap.fromTo('.gsap-scale', {scale: 0.95, opacity: 0}, {
                scale: 1, opacity: 1, duration: 0.8, ease: 'power2.out',
                scrollTrigger: { trigger: '.l-cta', start: 'top 85%' }
            });
        });
    </script>

    <!-- FIREBASE AUTH LOGIC -->
    <script>
        // Initialize Firebase
        const firebaseConfig = <?= getFirebaseConfigJs() ?>;
        firebase.initializeApp(firebaseConfig);
        const auth = firebase.auth();
        
        window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', { 'size': 'invisible' });
        const errorMsg = document.getElementById('errorMsg');
        function showError(text) { if(errorMsg) { errorMsg.innerText = text; errorMsg.style.display = 'block'; } else alert(text); }
        let confirmationResult = null;

        // Customer Phone Login
        document.getElementById('phoneLoginForm').addEventListener('submit', (e) => {
            e.preventDefault();
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

        // OTP Verify
        document.getElementById('otpForm').addEventListener('submit', (e) => {
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

        // Google SignIn
        function startGoogleLogin() {
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
        document.getElementById('staffLoginForm').addEventListener('submit', async (e) => {
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
    </script>
</body>
</html>