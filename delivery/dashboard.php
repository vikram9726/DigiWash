<?php
require_once '../config.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'delivery') {
    header('Location: ../index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash - Delivery Partner</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    <style>
        .container { max-width: 1400px; margin: 2rem auto; padding: 0 1rem; }
        .dashboard-grid { display: grid; grid-template-columns: 250px 1fr; gap: 2rem; }
        .sidebar { display: flex; flex-direction: column; gap: 0.5rem; height: max-content;}
        .menu-item { display: flex; align-items: center; padding: 1rem; border-radius: 12px; cursor: pointer; font-weight: 600; color: var(--dark); transition: all 0.3s; }
        .menu-item i { margin-right: 15px; }
        .menu-item:hover, .menu-item.active { background: var(--primary-gradient); color: white; box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3); }
        .section-content { display: none; animation: slideUp 0.4s ease forwards; }
        .section-content.active { display: block; }

        .order-card { 
            background: white; 
            border-radius: 12px; 
            padding: 1.5rem; 
            margin-bottom: 1rem;
            border-left: 5px solid var(--primary);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .order-details h4 { margin-bottom: 5px; }
        .order-details p { margin: 0; color: #64748b; font-size: 0.9rem; }

        /* Modals */
        .modal-overlay {
            display: none;
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center; justify-content: center;
        }
        .modal {
            background: white; padding: 2rem; border-radius: 12px; width: 90%; max-width: 400px;
            animation: slideUp 0.3s ease;
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">
            <span class="material-icons-outlined" style="font-size: 2rem; color: var(--secondary);">two_wheeler</span>
            <span>Delivery Hub</span>
        </div>
        <div class="nav-links">
            <a href="#" id="logoutBtn" style="color: var(--danger); display:flex; align-items:center;">
                <span class="material-icons-outlined" style="margin-right: 5px;">logout</span> Logout
            </a>
        </div>
    </nav>

    <div class="container dashboard-grid">
        <aside class="sidebar glass-panel">
            <div class="menu-item active" onclick="switchTab('pickups')"><i class="material-icons-outlined">hail</i><span>Pending Pickups</span></div>
            <div class="menu-item" onclick="switchTab('deliveries')"><i class="material-icons-outlined">local_shipping</i><span>Pending Deliveries</span></div>
            <div class="menu-item" onclick="switchTab('completed')"><i class="material-icons-outlined">task_alt</i><span>Completed</span></div>
            <div class="menu-item" onclick="switchTab('returns')"><i class="material-icons-outlined">assignment_return</i><span>Return Orders</span></div>
        </aside>

        <main class="content-area">
            <!-- Pickups Section -->
            <section id="pickups" class="section-content active">
                <h2 style="margin-bottom: 1.5rem;">Current Pickups</h2>
                <div class="glass-panel" id="pickupsContainer">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">Loading...</p>
                </div>
            </section>

            <!-- Deliveries Section -->
            <section id="deliveries" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Orders to Deliver</h2>
                <div class="glass-panel" id="deliveriesContainer">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">Loading...</p>
                </div>
            </section>

            <!-- Completed Section -->
            <section id="completed" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Completed Deliveries</h2>
                <div class="glass-panel" id="completedContainer">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">Loading...</p>
                </div>
            </section>

            <!-- Returns Section -->
            <section id="returns" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Return Orders to Fetch</h2>
                <div class="glass-panel">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">No return orders assigned to you.</p>
                </div>
            </section>
        </main>
    </div>

    <!-- Modals -->
    <div class="modal-overlay" id="qrModal">
        <div class="modal" style="display:flex; flex-direction:column; align-items:center;">
            <h3 style="margin-bottom: 1rem;">Scan Customer QR</h3>
            <p style="font-size: 0.9rem; margin-bottom: 1rem; text-align:center;">Scan the QR code on the customer's DigiWash dashboard to instantly confirm delivery.</p>
            <div id="qr-reader" style="width: 100%; max-width: 300px; margin-bottom: 1rem;"></div>
            <input type="hidden" id="qrOrderId">
            <button class="btn" onclick="closeQRModal()" style="background:#e2e8f0; color:#475569; margin-top: 0.5rem;">Cancel Scan</button>
            <p id="qrMsg" style="color:var(--danger); display:none; margin-top:0.5rem; text-align:center;"></p>
        </div>
    </div>

    <div class="modal-overlay" id="otpModal">
        <div class="modal">
            <h3 style="margin-bottom: 1rem;">Complete Delivery (OTP)</h3>
            <p style="font-size: 0.9rem; margin-bottom: 1rem;">Ask the customer for the 6-digit PIN sent to their phone.</p>
            <input type="text" id="otpInput" class="form-control" placeholder="Enter PIN" style="margin-bottom: 1rem;">
            <input type="hidden" id="otpOrderId">
            <button class="btn btn-primary" onclick="submitOTP()" id="btnSubmitOtp">Verify & Complete</button>
            <button class="btn" onclick="closeModals()" style="background:#e2e8f0; color:#475569; margin-top: 0.5rem;">Cancel</button>
            <p id="otpMsg" style="color:var(--danger); display:none; margin-top:0.5rem;"></p>
        </div>
    </div>

    <div class="modal-overlay" id="bypassModal">
        <div class="modal">
            <h3 style="margin-bottom: 1rem;">Bypass Delivery (Photo)</h3>
            <p style="font-size: 0.9rem; margin-bottom: 1rem; color:var(--danger);">Use only if customer is unavailable and authorizing staff.</p>
            <input type="hidden" id="bypassOrderId">
            
            <label style="font-size: 0.8rem; font-weight: 600;">Staff Contact Number</label>
            <input type="text" id="bypassStaffNum" class="form-control" placeholder="e.g. 9876543210" style="margin-bottom: 1rem;">
            
            <label style="font-size: 0.8rem; font-weight: 600;">Capture Staff/Shop Photo</label>
            <input type="file" id="bypassPhoto" accept="image/*" capture="environment" class="form-control" style="margin-bottom: 1rem;">
            
            <button class="btn btn-success" onclick="submitBypass()" id="btnSubmitBypass">Upload & Complete</button>
            <button class="btn" onclick="closeModals()" style="background:#e2e8f0; color:#475569; margin-top: 0.5rem;">Cancel</button>
            <p id="bypassMsg" style="color:var(--danger); display:none; margin-top:0.5rem;"></p>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadAssignments('pickups');
            loadAssignments('deliveries');
            loadAssignments('completed');
        });

        async function loadAssignments(type) {
            const container = document.getElementById(type + 'Container');
            if(!container) return;
            
            try {
                const res = await fetch('../api/delivery.php', {
                    method: 'POST', headers:{'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'get_assignments', type: type })
                });
                const data = await res.json();
                
                if(data.success && data.assignments.length > 0) {
                    container.innerHTML = data.assignments.map(o => `
                        <div class="order-card" style="border-left-color: ${type === 'deliveries' ? 'var(--secondary)' : 'var(--primary)'}">
                            <div class="order-details">
                                <h4>Order #${o.id} - ${o.customer_name}</h4>
                                <p><i class="material-icons-outlined" style="font-size:14px; vertical-align:middle;">location_on</i> ${o.shop_address}</p>
                                <p><i class="material-icons-outlined" style="font-size:14px; vertical-align:middle;">call</i> ${o.phone}</p>
                            </div>
                            <div>
                                ${type === 'pickups' ? `
                                    <button class="btn btn-primary" style="width:auto; padding:0.5rem 1rem;" onclick="fulfillPickup(${o.id})">Picked Up from Shop</button>
                                ` : type === 'deliveries' ? `
                                    <button class="btn btn-primary" style="width: auto; padding: 0.5rem 1rem; font-size: 0.9rem; margin-bottom: 5px;" onclick="openQRModal(${o.id})"><i class="material-icons-outlined" style="font-size:16px; vertical-align:middle;">qr_code_scanner</i> Scan QR</button>
                                    <br>
                                    <button class="btn btn-success" style="width: auto; padding: 0.5rem 1rem; font-size: 0.9rem;" onclick="openOTPModal(${o.id})">Complete (OTP)</button>
                                    <button class="btn" style="background:#e2e8f0; color:#475569; width: auto; padding: 0.5rem 1rem; font-size: 0.9rem; margin-top:5px;" onclick="openBypassModal(${o.id})">Bypass (Photo)</button>
                                ` : `
                                    <span style="color:var(--secondary); font-weight:600;"><i class="material-icons-outlined" style="vertical-align:middle;">check_circle</i> Done</span>
                                `}
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `<p style="text-align: center; color: #94a3b8; padding: 2rem 0;">No ${type} assigned to you.</p>`;
                }
            } catch(e) { container.innerHTML = 'Error loading.'; }
        }

        async function fulfillPickup(orderId) {
            if(!confirm("Confirm you have collected the items from the shop?")) return;
            try {
                const res = await fetch('../api/delivery.php', {
                    method: 'POST', headers:{'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'fulfill_pickup', order_id: orderId })
                });
                const data = await res.json();
                alert(data.message);
                if(data.success) {
                    loadAssignments('pickups');
                }
            } catch(e) { alert("Server Error"); }
        }

        // Modals Logic
        let html5QrCode = null;

        function closeModals() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
            document.getElementById('otpMsg').style.display = 'none';
            document.getElementById('bypassMsg').style.display = 'none';
            document.getElementById('qrMsg').style.display = 'none';
            closeQRModal();
        }

        function closeQRModal() {
            if (html5QrCode) {
                html5QrCode.stop().then(() => {
                    html5QrCode.clear();
                    html5QrCode = null;
                }).catch(err => console.error("Failed to stop scanner", err));
            }
            document.getElementById('qrModal').style.display = 'none';
        }

        function openQRModal(orderId) {
            document.getElementById('qrOrderId').value = orderId;
            document.getElementById('qrModal').style.display = 'flex';
            document.getElementById('qrMsg').style.display = 'none';

            if (!html5QrCode) {
                html5QrCode = new Html5Qrcode("qr-reader");
            }
            
            html5QrCode.start(
                { facingMode: "environment" }, // Use back camera
                { fps: 10, qrbox: { width: 250, height: 250 } },
                async (decodedText, decodedResult) => {
                    // Success callback
                    document.getElementById('qrMsg').style.color = 'var(--secondary)';
                    document.getElementById('qrMsg').innerText = "QR Scanned! Verifying...";
                    document.getElementById('qrMsg').style.display = 'block';
                    
                    // Stop scanning immediately after successful read
                    await html5QrCode.stop();
                    html5QrCode.clear();
                    html5QrCode = null;

                    submitQRHash(orderId, decodedText);
                },
                (errorMessage) => {
                    // Error callback (ignores continuous scan failures)
                })
            .catch((err) => {
                document.getElementById('qrMsg').style.color = 'var(--danger)';
                document.getElementById('qrMsg').innerText = "Camera access denied or unavailable.";
                document.getElementById('qrMsg').style.display = 'block';
            });
        }

        async function submitQRHash(orderId, hash) {
            try {
                const res = await fetch('../api/delivery.php', {
                    method: 'POST', headers:{'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'complete_delivery_qr', order_id: orderId, qr_hash: hash })
                });
                const data = await res.json();
                if(data.success) {
                    alert("Delivery successful via QR Scan!");
                    closeModals();
                    loadAssignments('deliveries');
                    loadAssignments('completed');
                } else {
                    document.getElementById('qrMsg').style.color = 'var(--danger)';
                    document.getElementById('qrMsg').innerText = data.message;
                    document.getElementById('qrMsg').style.display = 'block';
                }
            } catch(e) { 
                document.getElementById('qrMsg').style.color = 'var(--danger)';
                document.getElementById('qrMsg').innerText = "Error verifying QR code with server.";
                document.getElementById('qrMsg').style.display = 'block';
            }
        }

        function openOTPModal(orderId) { 
            document.getElementById('otpOrderId').value = orderId;
            document.getElementById('otpInput').value = '';
            document.getElementById('otpModal').style.display = 'flex'; 
        }

        function openBypassModal(orderId) { 
            document.getElementById('bypassOrderId').value = orderId;
            document.getElementById('bypassStaffNum').value = '';
            document.getElementById('bypassPhoto').value = '';
            document.getElementById('bypassModal').style.display = 'flex'; 
        }

        async function submitOTP() {
            const orderId = document.getElementById('otpOrderId').value;
            const otp = document.getElementById('otpInput').value;
            const btn = document.getElementById('btnSubmitOtp');
            const msg = document.getElementById('otpMsg');
            
            btn.innerHTML = 'Verifying...'; btn.disabled = true; msg.style.display = 'none';

            try {
                const res = await fetch('../api/delivery.php', {
                    method: 'POST', headers:{'Content-Type': 'application/json'},
                    body: JSON.stringify({ action: 'complete_delivery_otp', order_id: orderId, otp: otp })
                });
                const data = await res.json();
                if(data.success) {
                    alert("Delivery successful!");
                    closeModals();
                    loadAssignments('deliveries');
                    loadAssignments('completed');
                } else {
                    msg.innerText = data.message; msg.style.display = 'block';
                }
            } catch(e) { msg.innerText = "Error requesting server."; msg.style.display = 'block'; }
            
            btn.innerHTML = 'Verify & Complete'; btn.disabled = false;
        }

        async function submitBypass() {
            const orderId = document.getElementById('bypassOrderId').value;
            const staffNum = document.getElementById('bypassStaffNum').value;
            const photoFile = document.getElementById('bypassPhoto').files[0];
            const btn = document.getElementById('btnSubmitBypass');
            const msg = document.getElementById('bypassMsg');

            if(!photoFile) { msg.innerText = "Please capture a photo first."; msg.style.display = 'block'; return; }

            btn.innerHTML = 'Uploading...'; btn.disabled = true; msg.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'complete_delivery_bypass');
            formData.append('order_id', orderId);
            formData.append('staff_number', staffNum);
            formData.append('staff_photo', photoFile);

            try {
                const res = await fetch('../api/delivery.php', {
                    method: 'POST',
                    body: formData // No Content-Type header needed, fetch handles boundary for FormData
                });
                const data = await res.json();
                if(data.success) {
                    alert(data.message);
                    closeModals();
                    loadAssignments('deliveries');
                    loadAssignments('completed');
                } else {
                    msg.innerText = data.message; msg.style.display = 'block';
                }
            } catch(e) { msg.innerText = "Error uploading photo."; msg.style.display = 'block'; }
            
            btn.innerHTML = 'Upload & Complete'; btn.disabled = false;
        }

        function switchTab(tabId) {
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        }

        document.getElementById('logoutBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('../api/auth.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout' })
            });
            window.location.href = '../index.php';
        });
    </script>
</body>
</html>
