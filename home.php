<?php require 'public_header.php'; ?>
<!-- HERO SECTION -->
<section class="hero-gradient py-20 lg:py-32">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col items-center text-center">
        <h1 class="text-5xl lg:text-7xl font-black text-slate-900 tracking-tight leading-tight mb-6">
            Laundry done <span class="text-indigo-600">effortlessly.</span>
        </h1>
        <p class="text-lg text-slate-600 mb-10 max-w-2xl">
            Schedule a pickup in seconds. We collect, clean, and deliver your garments and textiles with premium care. Perfect for busy households and local shopkeepers.
        </p>
        <div class="flex gap-4">
            <a href="index.php" class="bg-indigo-600 text-white px-8 py-4 rounded-xl font-bold text-lg shadow-xl shadow-indigo-600/20 hover:scale-105 transition transform">Book a Pickup</a>
            <a href="about.php" class="bg-white text-slate-700 px-8 py-4 rounded-xl font-bold text-lg shadow-sm border border-slate-200 hover:bg-slate-50 transition">Learn More</a>
        </div>
    </div>
</section>

<!-- FEATURES SECTION -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center mb-16">
            <h2 class="text-3xl font-black text-slate-900">Why choose DigiWash?</h2>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i class="material-icons-outlined text-3xl">local_shipping</i>
                </div>
                <h3 class="text-xl font-bold mb-3">Shop Pickup</h3>
                <p class="text-slate-600">Schedule a time that works for you. We pick up directly from your shop.</p>
            </div>
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i class="material-icons-outlined text-3xl">track_changes</i>
                </div>
                <h3 class="text-xl font-bold mb-3">Live Tracking</h3>
                <p class="text-slate-600">Track your order from pickup to delivery directly from your dashboard.</p>
            </div>
            <div class="text-center p-6">
                <div class="w-16 h-16 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center mx-auto mb-6">
                    <i class="material-icons-outlined text-3xl">verified_user</i>
                </div>
                <h3 class="text-xl font-bold mb-3">Secure Payments</h3>
                <p class="text-slate-600">Pay securely online via Razorpay or choose standard Cash on Delivery.</p>
            </div>
        </div>
    </div>
</section>
<?php require 'public_footer.php'; ?>
