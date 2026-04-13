<?php require 'public_header.php'; ?>
<main class="max-w-4xl mx-auto px-4 py-8">
    <div class="text-center mb-12">
        <h1 class="text-4xl font-black text-slate-900 mb-4">Contact Us</h1>
        <p class="text-lg text-slate-600">Have a question? We are here to help you.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-12 bg-white p-8 rounded-2xl border border-slate-200 shadow-sm">
        
        <!-- Contact Details -->
        <div class="space-y-8">
            <div>
                <h3 class="font-bold text-slate-900 text-lg mb-2 flex items-center gap-2"><i class="material-icons-outlined text-indigo-600">support_agent</i> Help & Support</h3>
                <p class="text-slate-600">Reach out to us anytime. We aim to respond within 24 hours.</p>
                <button onclick="openContactModal()" class="mt-2 inline-flex items-center gap-2 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-md shadow-indigo-600/30">
                    <i class="material-icons-outlined text-base">message</i> Contact Admin via Message
                </button>
            </div>
            
            <div>
                <h3 class="font-bold text-slate-900 text-lg mb-2 flex items-center gap-2"><i class="material-icons-outlined text-indigo-600">phone</i> Call Us</h3>
                <p class="text-slate-600">Available Monday to Saturday (9 AM - 6 PM)</p>
                <div class="text-slate-900 font-bold">+91 9726232915</div>
            </div>

            <div>
                <h3 class="font-bold text-slate-900 text-lg mb-2 flex items-center gap-2"><i class="material-icons-outlined text-indigo-600">location_on</i> Headquarters</h3>
                <p class="text-slate-600 leading-relaxed">
                    DigiWash Corporate Office<br>
                    Gujarat, India
                </p>
            </div>
        </div>

        <!-- Working Hours / Trust Box -->
        <div class="bg-slate-50 p-8 rounded-xl border border-slate-100 flex flex-col justify-center text-center">
            <i class="material-icons-outlined text-5xl text-indigo-400 mb-4">watch_later</i>
            <h3 class="font-bold text-xl text-slate-900 mb-2">Operating Hours</h3>
            <p class="text-slate-600 mb-2">Monday - Saturday</p>
            <p class="text-indigo-600 font-bold text-lg mb-6">09:00 AM - 08:00 PM</p>
            <p class="text-sm text-slate-500">All pickups and deliveries operate within these designated hours.</p>
        </div>

    </div>
</main>
<?php require 'public_footer.php'; ?>
