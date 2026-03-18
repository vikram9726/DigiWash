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
    <title>DigiWash - Premium Laundry Service</title>
    <!-- Firebase SDK (Compat version for simplicity) -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        /* Swiggy-like Split Screen Layout */
        body {
            margin: 0;
            padding: 0;
            overflow: hidden; /* Prevent scroll on landing if possible */
            background: #fff;
        }

        .split-container {
            display: flex;
            height: 100vh;
            width: 100vw;
        }

        /* Left Side: Login / Auth Panel */
        .auth-panel {
            width: 40%;
            padding: 3rem 4rem;
            background: #ffffff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            position: relative;
            z-index: 10;
            box-shadow: 10px 0 30px rgba(0,0,0,0.05);
        }

        .logo-container {
            margin-bottom: 3rem;
            display: flex;
            align-items: center;
            font-size: 2rem;
            font-weight: 800;
        }

        .logo-container span {
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-container i { color: var(--primary); margin-right: 10px; font-size: 2.5rem; }

        .auth-panel h1 {
            font-size: 2.2rem;
            color: var(--dark);
            margin-bottom: 0.5rem;
            background: none;
            -webkit-text-fill-color: initial;
        }

        .auth-panel p.subtext {
            color: #64748b;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .google-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            color: #3c4043;
            border: 1px solid #dadce0;
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
        }

        .google-btn img {
            width: 24px;
            height: 24px;
            margin-right: 12px;
        }

        .google-btn:hover {
            background: #f8f9fa;
            box-shadow: 0 1px 3px rgba(60,64,67,0.3);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #94a3b8;
            margin: 1.5rem 0;
            font-size: 0.9rem;
        }

        .divider::before, .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #e2e8f0;
        }
        .divider::before { margin-right: .5em; }
        .divider::after { margin-left: .5em; }

        /* Right Side: Hero Banner */
        .hero-panel {
            width: 60%;
            background: linear-gradient(135deg, rgba(79, 70, 229, 0.9) 0%, rgba(124, 58, 237, 0.9) 100%), url('https://images.unsplash.com/photo-1517677208171-0bc6725a3e60?q=80&w=2070&auto=format&fit=crop') center/cover;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        .hero-content {
            text-align: center;
            color: white;
            padding: 3rem;
            max-width: 600px;
            animation: slideUp 0.8s ease forwards;
        }

        .hero-content h2 {
            font-size: 3.5rem;
            color: white;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .hero-content p {
            font-size: 1.2rem;
            color: rgba(255,255,255,0.9);
        }

        /* Phone Step (Hidden initially) */
        #phoneStep { display: none; margin-top: 1rem; }

        @media (max-width: 900px) {
            .split-container { flex-direction: column-reverse; height: auto; min-height: 100vh; overflow-y: auto;}
            .hero-panel { width: 100%; height: 40vh; }
            .auth-panel { width: 100%; padding: 2rem; border-radius: 30px 30px 0 0; margin-top: -30px; }
            .hero-content h2 { font-size: 2.5rem; }
        }
    </style>
</head>
<body>

    <div class="split-container">
        <!-- Auth Side -->
        <div class="auth-panel">
            <div class="logo-container">
                <i class="material-icons-outlined">local_laundry_service</i>
                <span>DigiWash</span>
            </div>

            <div id="loginStep">
                <h1>Login / Sign up</h1>
                <p class="subtext">Get your laundry sparkling clean today.</p>

                <button class="google-btn" onclick="startGoogleLogin()">
                    <img src="https://upload.wikimedia.org/wikipedia/commons/5/53/Google_%22G%22_Logo.svg" alt="Google">
                    Continue with Google
                </button>

                <div class="divider">or</div>

                <form id="phoneLoginForm">
                    <div class="form-group" style="text-align: left;">
                        <div style="display: flex; align-items: center; border: 2px solid #cbd5e1; border-radius: 12px; background: rgba(255, 255, 255, 0.9); overflow: hidden; transition: border-color 0.3s ease;">
                            <span style="padding: 1rem 1.2rem; background: #f8fafc; border-right: 2px solid #cbd5e1; font-weight: 700; color: #475569; font-size: 1.1rem;">+91</span>
                            <input type="tel" id="phone" name="phone" placeholder="Enter Mobile Number" required pattern="[0-9]{10}" title="Please enter a valid 10-digit number" style="flex: 1; border: none; padding: 1rem 1.2rem; font-size: 1.1rem; outline: none; background: transparent; min-width: 0;">
                        </div>
                    </div>
                    <!-- Firebase Recaptcha Container -->
                    <div id="recaptcha-container" style="margin-bottom: 1rem;"></div>
                    
                    <button type="submit" class="btn btn-primary" id="loginBtn" style="padding: 1rem;">Send OTP <span class="material-icons-outlined" style="vertical-align: middle;">arrow_forward</span></button>
                    <p id="errorMsg" style="color: var(--danger); margin-top: 1rem; display: none; font-weight: 600;"></p>
                    <div style="margin-top: 1.5rem; text-align: center;">
                        <a href="javascript:void(0)" onclick="toggleStaffLogin()" id="staffToggleLink" style="color: var(--primary); font-weight: 600; font-size: 0.9rem;">Staff / Delivery Login</a>
                    </div>
                </form>
            </div>

            <!-- Staff/Dummy Login Step -->
            <div id="staffStep" style="display: none;">
                <h1>Staff Login</h1>
                <p class="subtext">Use your assigned dummy OTP to access.</p>
                <form id="staffLoginForm">
                    <div class="form-group">
                        <input type="tel" id="staffPhone" placeholder="Enter 10-digit phone (e.g. 9726232915)" required maxlength="10" pattern="[0-9]{10}" title="Please enter a valid 10-digit phone number" class="form-control" style="border: 2px solid #cbd5e1; border-radius:12px; padding:1rem;">
                    </div>
                    <div class="form-group">
                        <input type="text" id="staffOtp" placeholder="Enter Dummy OTP" required class="form-control" style="border: 2px solid #cbd5e1; border-radius:12px; padding:1rem;">
                    </div>
                    <button type="submit" class="btn btn-primary" style="padding: 1rem;">Verify & Enter</button>
                    <button type="button" class="btn" onclick="toggleStaffLogin()" style="background:#e2e8f0; color:#475569; padding: 1rem; margin-top:0.5rem; width:100%;">Back to Customer Login</button>
                    <p id="staffError" style="color: var(--danger); margin-top: 1rem; display: none; font-weight: 600;"></p>
                </form>
            </div>

            <!-- OTP Verification Step -->
            <div id="otpStep" style="display: none;">
                <h1 style="font-size: 1.8rem;">Verify Phone</h1>
                <p class="subtext" style="font-size: 0.9rem;">Enter the 6-digit code sent via SMS.</p>
                <form id="otpForm">
                    <div class="form-group" style="text-align: left;">
                        <input type="text" id="otpInput" placeholder="Enter 6-digit OTP" required pattern="[0-9]{6}" style="width: 100%; border: 2px solid #cbd5e1; border-radius: 12px; padding: 1rem 1.2rem; font-size: 1.1rem; outline: none; transition: border-color 0.3s ease;">
                    </div>
                    <button type="submit" class="btn btn-success" id="verifyOtpBtn" style="padding: 1rem;">Verify & Login</button>
                    <button type="button" class="btn" onclick="location.reload()" style="background:#e2e8f0; color:#475569; padding: 1rem; margin-top:0.5rem;">Cancel</button>
                </form>
            </div>

            <div style="margin-top: auto; padding-top: 2rem; font-size: 0.8rem; color: #94a3b8;">
                By continuing, you agree to our Terms of Service & Privacy Policy.
            </div>
        </div>

        <!-- Visual / Hero Side -->
        <div class="hero-panel">
            <div class="hero-content">
                <h2>Fresh Clothes.<br>Zero Hassle.</h2>
                <p>Schedule a pickup from your shop. Track it live. Delivered fresh & ironed right back to you.</p>
            </div>
        </div>
    </div>

    <script>
        // Initialize Firebase Backend Variables (Passed from config.php)
        const firebaseConfig = <?= getFirebaseConfigJs() ?>;
        firebase.initializeApp(firebaseConfig);
        const auth = firebase.auth();
        
        // Setup Recaptcha
        window.recaptchaVerifier = new firebase.auth.RecaptchaVerifier('recaptcha-container', {
            'size': 'invisible', // Can be 'normal' if you want it explicitly visible
            'callback': (response) => {
                // reCAPTCHA solved, allow signInWithPhoneNumber.
            }
        });

        const errorMsg = document.getElementById('errorMsg');
        
        function showError(text) {
            if(errorMsg) {
                errorMsg.innerText = text;
                errorMsg.style.display = 'block';
            } else {
                alert(text);
            }
        }

        let confirmationResult = null; // Stores Firebase response after sending SMS

        // Phone Auth - Send OTP
        document.getElementById('phoneLoginForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const phone = '+91' + document.getElementById('phone').value;
            const btnElement = document.getElementById('loginBtn');
            const originalText = btnElement.innerHTML;
            
            btnElement.innerHTML = 'Sending...';
            btnElement.disabled = true;
            if(errorMsg) errorMsg.style.display = 'none';

            auth.signInWithPhoneNumber(phone, window.recaptchaVerifier)
                .then((result) => {
                    // SMS sent
                    confirmationResult = result;
                    document.getElementById('loginStep').style.display = 'none';
                    document.getElementById('otpStep').style.display = 'block';
                }).catch((error) => {
                    console.error("SMS Error", error);
                    showError(error.message || "Failed to send SMS. Refresh and try again.");
                    // Reset recaptcha on bad request
                    window.recaptchaVerifier.render().then(function(widgetId) {
                        grecaptcha.reset(widgetId);
                    });
                    btnElement.innerHTML = originalText;
                    btnElement.disabled = false;
                });
        });

        // Phone Auth - Verify OTP
        document.getElementById('otpForm').addEventListener('submit', (e) => {
            e.preventDefault();
            const otpCode = document.getElementById('otpInput').value;
            const btnElement = document.getElementById('verifyOtpBtn');
            
            btnElement.innerHTML = 'Verifying...';
            btnElement.disabled = true;

            confirmationResult.confirm(otpCode).then((result) => {
                // Success! User is signed in via Firebase.
                // Now pass the token to backend.
                result.user.getIdToken().then(idToken => {
                    sendTokenToBackend(idToken, result.user.phoneNumber, null, null, btnElement);
                });
            }).catch((error) => {
                showError("Invalid OTP code.");
                btnElement.innerHTML = 'Verify & Login';
                btnElement.disabled = false;
            });
        });

        // Google SignIn
        function startGoogleLogin() {
            const provider = new firebase.auth.GoogleAuthProvider();
            auth.signInWithPopup(provider).then((result) => {
                const user = result.user;
                user.getIdToken().then(idToken => {
                    // Note: Google Auth might not return phone number, so we pass email/name.
                    // The backend will handle linking or prompting profile completion in the dashboard.
                    sendTokenToBackend(idToken, user.phoneNumber, user.email, user.displayName, document.querySelector('.google-btn'));
                });
            }).catch((error) => {
                showError(error.message);
            });
        }

        // Common Backend Call Step
        async function sendTokenToBackend(idToken, phone, email, name, btnElement) {
            btnElement.innerHTML = 'Authorizing...';
            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        action: 'firebase_login', 
                        idToken: idToken,
                        phone: phone, // Can be null for pure Google auth
                        email: email, 
                        name: name 
                    })
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    showError(result.message || 'An error occurred server-side');
                    btnElement.innerHTML = 'Retry';
                    btnElement.disabled = false;
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Server error. Please try again.');
                btnElement.innerHTML = 'Retry';
                btnElement.disabled = false;
            }
        }

        // Staff Login Toggle
        function toggleStaffLogin() {
            const loginStep = document.getElementById('loginStep');
            const staffStep = document.getElementById('staffStep');
            if (staffStep.style.display === 'none') {
                loginStep.style.display = 'none';
                staffStep.style.display = 'block';
            } else {
                loginStep.style.display = 'block';
                staffStep.style.display = 'none';
            }
        }

        document.getElementById('staffLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const err = document.getElementById('staffError');
            btn.innerHTML = 'Verifying...'; btn.disabled = true;
            err.style.display = 'none';

            try {
                const res = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'dummy_login',
                        phone: document.getElementById('staffPhone').value,
                        otp: document.getElementById('staffOtp').value
                    })
                });
                const result = await res.json();
                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    err.innerText = result.message;
                    err.style.display = 'block';
                    btn.innerHTML = 'Verify & Enter'; btn.disabled = false;
                }
            } catch (e) {
                err.innerText = 'Server connection failed.';
                err.style.display = 'block';
                btn.innerHTML = 'Verify & Enter'; btn.disabled = false;
            }
        });
    </script>
</body>
</html>