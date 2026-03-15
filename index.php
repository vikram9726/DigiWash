<?php
session_start();
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

                <!-- Simulated Google Auth Button for Desktop -->
                <!-- In a real app, this would use Google Identity Services SDK -->
                <button class="google-btn" onclick="simulateGoogleLogin()">
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
                    <button type="submit" class="btn btn-primary" id="loginBtn" style="padding: 1rem;">Continue <span class="material-icons-outlined" style="vertical-align: middle;">arrow_forward</span></button>
                    <p id="errorMsg" style="color: var(--danger); margin-top: 1rem; display: none; font-weight: 600;"></p>
                </form>
            </div>

            <!-- Phone Collection Step (Post Google Login) -->
            <div id="phoneStep">
                <h1 style="font-size: 1.8rem;">Just one more step</h1>
                <p class="subtext" style="font-size: 0.9rem;">We need your mobile number to coordinate deliveries.</p>
                <form id="googlePhoneForm">
                    <input type="hidden" id="g_email" value="">
                    <input type="hidden" id="g_name" value="">
                    <div class="form-group" style="text-align: left;">
                        <div style="display: flex; align-items: center; border: 2px solid #cbd5e1; border-radius: 12px; background: rgba(255, 255, 255, 0.9); overflow: hidden; transition: border-color 0.3s ease;">
                            <span style="padding: 1rem 1.2rem; background: #f8fafc; border-right: 2px solid #cbd5e1; font-weight: 700; color: #475569; font-size: 1.1rem;">+91</span>
                            <input type="tel" id="g_phone" placeholder="Enter Mobile Number" required pattern="[0-9]{10}" style="flex: 1; border: none; padding: 1rem 1.2rem; font-size: 1.1rem; outline: none; background: transparent; min-width: 0;">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success" id="linkPhoneBtn" style="padding: 1rem;">Complete Profile</button>
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
        // Normal Phone Login
        document.getElementById('phoneLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const phone = document.getElementById('phone').value;
            submitLogin(phone, null, null, document.getElementById('loginBtn'));
        });

        // Simulating Google OAuth Flow mapping to our requirement
        function simulateGoogleLogin() {
            // In reality, Google OAuth opens a popup. We simulate the returned payload:
            const mockGoogleResponse = {
                email: "customer@example.com",
                name: "John Google",
                picture: "url..."
                // NOTE: Google does NOT return phone numbers
            };

            // Switch UI to ask for Phone Number
            document.getElementById('loginStep').style.display = 'none';
            document.getElementById('phoneStep').style.display = 'block';
            
            // Store hidden data
            document.getElementById('g_email').value = mockGoogleResponse.email;
            document.getElementById('g_name').value = mockGoogleResponse.name;
        }

        // Post-Google Phone submission
        document.getElementById('googlePhoneForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const phone = document.getElementById('g_phone').value;
            const email = document.getElementById('g_email').value;
            const name = document.getElementById('g_name').value;
            
            submitLogin(phone, email, name, document.getElementById('linkPhoneBtn'));
        });

        async function submitLogin(phone, email, name, btnElement) {
            const originalText = btnElement.innerHTML;
            btnElement.innerHTML = 'Processing...';
            btnElement.disabled = true;
            
            const errorMsg = document.getElementById('errorMsg');
            if(errorMsg) errorMsg.style.display = 'none';

            try {
                const response = await fetch('api/auth.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', phone: phone, email: email, name: name })
                });

                const result = await response.json();

                if (result.success) {
                    window.location.href = result.redirect;
                } else {
                    if(errorMsg) {
                        errorMsg.innerText = result.message || 'An error occurred';
                        errorMsg.style.display = 'block';
                    } else {
                        alert(result.message);
                    }
                    btnElement.innerHTML = originalText;
                    btnElement.disabled = false;
                }

            } catch (error) {
                console.error('Error:', error);
                if(errorMsg) {
                    errorMsg.innerText = 'Server error. Please try again.';
                    errorMsg.style.display = 'block';
                }
                btnElement.innerHTML = originalText;
                btnElement.disabled = false;
            }
        }
    </script>
</body>
</html>