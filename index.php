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
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>body { font-family: 'Outfit', sans-serif; }</style>

    <!-- Firebase SDK (Compat version for simplicity) -->
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-app-compat.js"></script>
    <script src="https://www.gstatic.com/firebasejs/9.22.1/firebase-auth-compat.js"></script>
    
    <!-- GSAP for animations -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.2/ScrollTrigger.min.js"></script>
</head>
<body class="bg-slate-50 text-slate-900 font-sans selection:bg-indigo-500 selection:text-white overflow-x-hidden relative">

    <!-- Background Gradients -->
    <div class="fixed inset-0 overflow-hidden -z-10 pointer-events-none">
        <div class="absolute top-[-10%] left-[-10%] w-[500px] h-[500px] rounded-full bg-indigo-300/30 blur-[120px]"></div>
        <div class="absolute bottom-[-10%] right-[-10%] w-[600px] h-[600px] rounded-full bg-blue-300/20 blur-[120px]"></div>
    </div>

    <!-- Navbar -->
    <nav class="fixed top-0 w-full z-50 transition-all duration-300 bg-transparent py-6" id="navbar">
        <div class="max-w-7xl mx-auto px-6 md:px-12 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-500 to-blue-500 flex items-center justify-center text-white shadow-lg shadow-indigo-500/30">
                    <i class="material-icons-outlined">check_circle</i>
                </div>
                <span class="text-xl font-bold tracking-tight text-slate-800">DigiWash</span>
            </div>

            <div class="hidden md:flex items-center gap-8 font-medium text-slate-600">
                <a href="#how" class="hover:text-indigo-600 transition-colors">How it works</a>
                <a href="#features" class="hover:text-indigo-600 transition-colors">Features</a>
                <a href="#reviews" class="hover:text-indigo-600 transition-colors">Trust</a>
            </div>

            <div class="hidden md:flex">
                <button class="bg-slate-900 hover:bg-slate-800 text-white px-6 py-2.5 rounded-full font-medium transition-transform hover:scale-105 active:scale-95 shadow-lg shadow-slate-900/20" onclick="openAuthModal()">
                    Login / Signup
                </button>
            </div>
            
            <button class="md:hidden text-slate-800" onclick="openAuthModal()">
                <i class="material-icons-outlined">login</i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="relative pt-40 pb-20 md:pt-52 md:pb-32 px-6 md:px-12 max-w-7xl mx-auto flex flex-col items-center text-center">
        <!-- Floating elements -->
        <div class="gsap-float-1 absolute top-40 left-10 md:left-20 bg-white/60 backdrop-blur-lg p-4 rounded-2xl border border-white/40 shadow-xl hidden md:flex items-center gap-4">
            <div class="p-3 bg-indigo-100 text-indigo-600 rounded-full flex"><i class="material-icons-outlined">schedule</i></div>
            <div class="text-left">
                <p class="text-sm font-bold text-slate-800 m-0">Picked Up</p>
                <p class="text-xs text-slate-500 m-0">10 mins ago</p>
            </div>
        </div>

        <div class="gsap-float-2 absolute bottom-40 right-10 md:right-20 bg-white/60 backdrop-blur-lg p-4 rounded-2xl border border-white/40 shadow-xl hidden md:flex items-center gap-4">
            <div class="p-3 bg-green-100 text-green-600 rounded-full flex"><i class="material-icons-outlined">shield</i></div>
            <div class="text-left">
                <p class="text-sm font-bold text-slate-800 m-0">Washed & Ironed</p>
                <p class="text-xs text-slate-500 m-0">Ready for delivery</p>
            </div>
        </div>

        <div class="gsap-hero inline-flex items-center gap-2 px-4 py-2 rounded-full bg-indigo-100 text-indigo-700 font-semibold text-sm mb-6 border border-indigo-200">
            <span class="w-2 h-2 rounded-full bg-indigo-500 animate-pulse"></span> The Future of Laundry
        </div>

        <h1 class="gsap-hero text-5xl md:text-7xl font-extrabold tracking-tight leading-tight mb-6 max-w-4xl text-slate-900">
            Laundry <span class="text-transparent bg-clip-text bg-gradient-to-r from-indigo-600 to-blue-500">Reimagined.</span>
        </h1>

        <p class="gsap-hero text-lg md:text-xl text-slate-600 max-w-2xl mb-10 leading-relaxed">
            Schedule a pickup, track it live, and get your clothes back fresh, crisp, and ready to wear. All from your phone.
        </p>

        <div class="gsap-hero flex flex-col sm:flex-row gap-4">
            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-8 py-4 rounded-full font-semibold text-lg transition-all hover:scale-105 active:scale-95 shadow-xl shadow-indigo-600/30 flex items-center justify-center gap-2" onclick="openAuthModal()">
                Get Started Today <i class="material-icons-outlined">chevron_right</i>
            </button>
        </div>
    </section>

    <!-- How it Works Section -->
    <section id="how" class="py-24 px-6 md:px-12 bg-white relative rounded-3xl shadow-sm border border-slate-100 max-w-[95%] mx-auto mb-10">
        <div class="max-w-7xl mx-auto">
            <div class="gsap-fade-up text-center max-w-2xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-bold mb-4 text-slate-900">How it works</h2>
                <p class="text-slate-600 text-lg">Three simple steps to clean clothes without leaving your house.</p>
            </div>

            <div class="grid md:grid-cols-3 gap-12 relative">
                <div class="hidden md:block absolute top-[3rem] left-[16%] w-[68%] h-0.5 border-t-2 border-dashed border-slate-200"></div>
                
                <div class="gsap-stagger relative z-10 flex flex-col items-center text-center">
                    <div class="w-24 h-24 mb-6 rounded-full bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center border-[8px] border-white shadow-lg text-3xl font-bold text-indigo-600">1</div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900">Schedule</h3>
                    <p class="text-slate-600">Choose a time that works for you. Our partner will pick up your laundry right from your doorstep.</p>
                </div>
                
                <div class="gsap-stagger relative z-10 flex flex-col items-center text-center">
                    <div class="w-24 h-24 mb-6 rounded-full bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center border-[8px] border-white shadow-lg text-3xl font-bold text-indigo-600">2</div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900">We Clean</h3>
                    <p class="text-slate-600">Your clothes are professionally washed, ironed, and folded with the highest quality standards.</p>
                </div>
                
                <div class="gsap-stagger relative z-10 flex flex-col items-center text-center">
                    <div class="w-24 h-24 mb-6 rounded-full bg-gradient-to-br from-indigo-50 to-blue-50 flex items-center justify-center border-[8px] border-white shadow-lg text-3xl font-bold text-indigo-600">3</div>
                    <h3 class="text-2xl font-bold mb-3 text-slate-900">Delivered</h3>
                    <p class="text-slate-600">Track your delivery live. Fresh, pristine clothes delivered back to you in record time.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-24 px-6 md:px-12 max-w-7xl mx-auto">
        <div class="gsap-fade-up text-center max-w-2xl mx-auto mb-16">
            <h2 class="text-3xl md:text-4xl font-bold mb-4 text-slate-900">Why Choose DigiWash?</h2>
            <p class="text-slate-600 text-lg">Designed for busy professionals who value quality and time.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
            <div class="gsap-stagger-2 group bg-white rounded-3xl p-8 border border-slate-100 shadow-xl shadow-slate-200/40 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                    <i class="material-icons-outlined text-3xl">local_shipping</i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-900">Lightning Fast</h3>
                <p class="text-slate-600 leading-relaxed">Get your clothes back within 24-48 hours. No waiting.</p>
            </div>

            <div class="gsap-stagger-2 group bg-white rounded-3xl p-8 border border-slate-100 shadow-xl shadow-slate-200/40 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                    <i class="material-icons-outlined text-3xl">schedule</i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-900">Live Tracking</h3>
                <p class="text-slate-600 leading-relaxed">Know exactly where your clothes are with realtime GPS.</p>
            </div>

            <div class="gsap-stagger-2 group bg-white rounded-3xl p-8 border border-slate-100 shadow-xl shadow-slate-200/40 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                    <i class="material-icons-outlined text-3xl">shield</i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-900">Premium Care</h3>
                <p class="text-slate-600 leading-relaxed">Eco-friendly solvents and absolute precision handling.</p>
            </div>

            <div class="gsap-stagger-2 group bg-white rounded-3xl p-8 border border-slate-100 shadow-xl shadow-slate-200/40 hover:shadow-2xl hover:-translate-y-2 transition-all duration-300">
                <div class="w-16 h-16 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6 group-hover:bg-indigo-600 group-hover:text-white transition-colors duration-300">
                    <i class="material-icons-outlined text-3xl">credit_card</i>
                </div>
                <h3 class="text-xl font-bold mb-3 text-slate-900">Transparent</h3>
                <p class="text-slate-600 leading-relaxed">No hidden fees. Pay online or use customizable plans.</p>
            </div>
        </div>
    </section>

    <!-- Trust Section -->
    <section id="reviews" class="py-24 px-6 md:px-12 bg-indigo-900 text-white relative overflow-hidden rounded-[3rem] max-w-[95%] mx-auto mb-10">
        <div class="absolute top-0 right-0 w-full h-full bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-5"></div>
        <div class="max-w-7xl mx-auto relative z-10 flex flex-col items-center">
           <h2 class="gsap-fade-up text-3xl md:text-4xl font-bold mb-12 text-center">Loved by thousands of professionals.</h2>
           <div class="gsap-fade-up bg-white/10 backdrop-blur-md border border-white/20 p-8 md:p-12 rounded-3xl max-w-3xl text-center shadow-2xl">
               <div class="flex justify-center gap-1 text-yellow-400 mb-6">
                   <i class="material-icons-outlined">star</i><i class="material-icons-outlined">star</i><i class="material-icons-outlined">star</i><i class="material-icons-outlined">star</i><i class="material-icons-outlined">star</i>
               </div>
               <p class="text-xl md:text-2xl font-medium leading-relaxed mb-6">"DigiWash completely changed my routine. I haven't done laundry in six months and my clothes feel brand new every time they get delivered. The app is incredibly easy to use."</p>
               <p class="font-semibold text-indigo-200 text-lg">Sarah Jenkins</p>
               <p class="text-sm text-indigo-300/80">Marketing Director</p>
           </div>
        </div>
    </section>

    <!-- Call To Action -->
    <section class="py-24 px-6 md:px-12 max-w-5xl mx-auto">
        <div class="gsap-scale bg-gradient-to-br from-indigo-600 to-blue-600 rounded-[3rem] p-12 md:p-20 text-center text-white relative overflow-hidden shadow-2xl shadow-indigo-600/30">
            <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-white/10 rounded-full blur-3xl -translate-y-1/2 translate-x-1/2"></div>
            <h2 class="text-4xl md:text-5xl font-bold mb-6 relative z-10">Ready for a fresh start?</h2>
            <p class="text-xl text-indigo-100 mb-10 max-w-2xl mx-auto relative z-10">Join thousands of users who have automated their laundry with DigiWash today.</p>
            <button class="bg-white text-indigo-600 px-8 py-4 rounded-full font-bold text-lg hover:scale-105 active:scale-95 transition-transform shadow-xl relative z-10" onclick="openAuthModal()">
                Create Free Account
            </button>
        </div>
    </section>

    <!-- Minimal Footer -->
    <footer class="border-t border-slate-200 bg-white py-12 px-6">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="flex items-center gap-2">
                <i class="material-icons-outlined text-indigo-600">check_circle</i>
                <span class="text-xl font-bold text-slate-800">DigiWash Inc.</span>
            </div>
            <div class="text-slate-500 text-sm">
                &copy; <?= date('Y') ?> DigiWash. Premium SaaS Laundry.
            </div>
            <div class="flex gap-4">
                <a href="#" class="text-slate-400 hover:text-indigo-600 transition-colors font-medium text-sm">Privacy</a>
                <a href="#" class="text-slate-400 hover:text-indigo-600 transition-colors font-medium text-sm">Terms</a>
            </div>
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
            if(window.scrollY > 50) {
                document.getElementById('navbar').classList.add('bg-white/70', 'backdrop-blur-md', 'shadow-sm', 'border-b', 'border-slate-200');
                document.getElementById('navbar').classList.remove('bg-transparent', 'py-6');
                document.getElementById('navbar').classList.add('py-4');
            } else {
                document.getElementById('navbar').classList.remove('bg-white/70', 'backdrop-blur-md', 'shadow-sm', 'border-b', 'border-slate-200', 'py-4');
                document.getElementById('navbar').classList.add('bg-transparent', 'py-6');
            }
        });

        // GSAP Animations
        document.addEventListener('DOMContentLoaded', () => {
            gsap.registerPlugin(ScrollTrigger);

            // Hero animations
            const tl = gsap.timeline();
            tl.fromTo('.gsap-hero', {y: 30, opacity: 0}, {y: 0, opacity: 1, duration: 0.8, stagger: 0.1, ease: 'power3.out', delay: 0.2});
            
            // Floating cards
            gsap.to('.gsap-float-1', {y: -15, duration: 2.5, yoyo: true, repeat: -1, ease: 'sine.inOut'});
            gsap.to('.gsap-float-2', {y: 15, duration: 3, yoyo: true, repeat: -1, ease: 'sine.inOut', delay: 0.5});

            // Scroll animations
            gsap.utils.toArray('.gsap-fade-up').forEach(elem => {
                gsap.fromTo(elem, {y: 40, opacity: 0}, {
                    y: 0, opacity: 1, duration: 0.8, ease: 'power3.out',
                    scrollTrigger: { trigger: elem, start: 'top 85%' }
                });
            });

            gsap.fromTo('.gsap-stagger', {y: 30, opacity: 0}, {
                y: 0, opacity: 1, duration: 0.6, stagger: 0.15, ease: 'power3.out',
                scrollTrigger: { trigger: '#how', start: 'top 80%' }
            });

            gsap.fromTo('.gsap-stagger-2', {y: 30, opacity: 0}, {
                y: 0, opacity: 1, duration: 0.6, stagger: 0.1, ease: 'power3.out',
                scrollTrigger: { trigger: '#features', start: 'top 80%' }
            });

            gsap.fromTo('.gsap-scale', {scale: 0.95, opacity: 0}, {
                scale: 1, opacity: 1, duration: 0.8, ease: 'power2.out',
                scrollTrigger: { trigger: '.gsap-scale', start: 'top 85%' }
            });
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
        document.getElementById('phoneLoginForm').addEventListener('submit', (e) => {
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