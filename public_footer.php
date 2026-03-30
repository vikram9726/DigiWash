    <footer class="bg-slate-900 text-slate-300 py-12 mt-auto">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid grid-cols-1 md:grid-cols-4 gap-8">
            <div>
                <div class="flex items-center gap-2 mb-4">
                    <i class="material-icons-outlined text-indigo-400 text-3xl">local_laundry_service</i>
                    <span class="font-black text-xl text-white">DigiWash</span>
                </div>
                <p class="text-sm leading-relaxed mb-4">Premium laundry and textile care delivered directly to your shop. Trusted by households and local shopkeepers.</p>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Company</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="about.php" class="hover:text-indigo-400 transition">About Us</a></li>
                    <li><a href="contact.php" class="hover:text-indigo-400 transition">Contact Us</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Legal</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="privacy-policy.php" class="hover:text-indigo-400 transition">Privacy Policy</a></li>
                    <li><a href="terms.php" class="hover:text-indigo-400 transition">Terms & Conditions</a></li>
                    <li><a href="refund-policy.php" class="hover:text-indigo-400 transition">Refund & Cancellation</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-white font-bold mb-4">Contact</h3>
                <ul class="space-y-2 text-sm">
                    <li><a href="javascript:void(0)" onclick="openContactModal()" class="flex items-center gap-2 mb-2 hover:text-indigo-400 transition"><i class="material-icons-outlined text-base">message</i> Contact Admin via Message</a></li>
                    <li class="flex items-center gap-2 mb-2"><i class="material-icons-outlined text-base">phone</i> +91 9726232915</li>
                    <li class="flex items-center gap-2 items-start"><i class="material-icons-outlined text-base">location_on</i> India</li>
                </ul>
            </div>
        </div>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-12 pt-8 border-t border-slate-800 text-sm text-center">
            &copy; 2026 DigiWash. All rights reserved.
        </div>
    </footer>
    </footer>

    <!-- Global Contact Modal -->
    <div id="contactModal" class="fixed inset-0 z-[100] hidden bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl w-full max-w-md shadow-2xl overflow-hidden scale-95 transition-transform duration-300 relative" id="contactModalContent">
            <button onclick="closeContactModal()" class="absolute top-4 right-4 w-8 h-8 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-800 transition">
                <i class="material-icons-outlined text-lg">close</i>
            </button>
            <div class="p-6 md:p-8">
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center mb-6">
                    <i class="material-icons-outlined text-2xl">support_agent</i>
                </div>
                <h3 class="text-2xl font-bold text-slate-900 mb-2">Message Admin</h3>
                <p class="text-slate-500 text-sm mb-6">Have a question or suggestion? Send us a direct message and we'll get back to you.</p>

                <form id="contactForm" onsubmit="submitContactMessage(event)">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Your Name</label>
                            <input type="text" id="contactName" required autocomplete="off" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition" placeholder="John Doe">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Mobile Number</label>
                            <input type="tel" id="contactPhone" required pattern="[0-9]{10}" maxlength="10" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition" placeholder="10-digit number">
                        </div>
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-1">Message</label>
                            <textarea id="contactMessageText" required rows="3" class="w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 outline-none transition resize-none" placeholder="How can we help?"></textarea>
                        </div>
                    </div>
                    
                    <div id="contactFormError" class="mt-4 text-red-500 text-sm font-semibold hidden bg-red-50 p-3 rounded-lg text-center"></div>
                    <div id="contactFormSuccess" class="mt-4 text-emerald-600 text-sm font-semibold hidden bg-emerald-50 p-3 rounded-lg text-center"></div>

                    <button type="submit" id="contactSubmitBtn" class="w-full mt-6 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3.5 rounded-xl transition shadow-lg shadow-indigo-600/30 flex justify-center items-center gap-2">
                        <span>Send Message</span>
                        <i class="material-icons-outlined text-base">send</i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Contact Modal Script -->
    <script>
        const contactModal = document.getElementById('contactModal');
        const contactModalContent = document.getElementById('contactModalContent');
        const contactFormError = document.getElementById('contactFormError');
        const contactFormSuccess = document.getElementById('contactFormSuccess');
        const contactSubmitBtn = document.getElementById('contactSubmitBtn');

        function openContactModal() {
            contactModal.classList.remove('hidden');
            // Slight delay to trigger CSS transition
            setTimeout(() => {
                contactModal.classList.remove('opacity-0');
                contactModalContent.classList.remove('scale-95');
                contactModalContent.classList.add('scale-100');
            }, 10);
            
            // Reset form
            document.getElementById('contactForm').reset();
            contactFormError.classList.add('hidden');
            contactFormSuccess.classList.add('hidden');
            contactSubmitBtn.style.display = 'flex';
        }

        function closeContactModal() {
            contactModal.classList.add('opacity-0');
            contactModalContent.classList.remove('scale-100');
            contactModalContent.classList.add('scale-95');
            setTimeout(() => {
                contactModal.classList.add('hidden');
            }, 300);
        }

        async function submitContactMessage(e) {
            e.preventDefault();
            contactFormError.classList.add('hidden');
            
            const name = document.getElementById('contactName').value.trim();
            const phone = document.getElementById('contactPhone').value.trim();
            const message = document.getElementById('contactMessageText').value.trim();

            contactSubmitBtn.disabled = true;
            contactSubmitBtn.innerHTML = '<i class="material-icons-outlined animate-spin text-base">autorenew</i> Sending...';

            try {
                const response = await fetch('api/contact.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name, phone, message })
                });
                
                const result = await response.json();
                if (result.success) {
                    contactFormSuccess.innerText = result.message;
                    contactFormSuccess.classList.remove('hidden');
                    contactSubmitBtn.style.display = 'none';
                    setTimeout(() => closeContactModal(), 2500);
                } else {
                    contactFormError.innerText = result.message || 'Failed to send message.';
                    contactFormError.classList.remove('hidden');
                    resetBtn();
                }
            } catch (err) {
                contactFormError.innerText = 'Network error. Please check your connection.';
                contactFormError.classList.remove('hidden');
                resetBtn();
            }

            function resetBtn() {
                contactSubmitBtn.disabled = false;
                contactSubmitBtn.innerHTML = '<span>Send Message</span><i class="material-icons-outlined text-base">send</i>';
            }
        }
        
        // Close modal on outside click
        contactModal.addEventListener('click', (e) => {
            if (e.target === contactModal) closeContactModal();
        });
    </script>
</body>
</html>
