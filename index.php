<?php
require_once 'config.php';

// If logged in, redirect
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') header('Location: admin/dashboard.php');
    elseif ($_SESSION['role'] === 'delivery') header('Location: delivery/dashboard.php');
    else header('Location: user/dashboard.php');
    exit;
}
require 'public_header.php';
?>
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

<?php require 'public_footer.php'; ?>
