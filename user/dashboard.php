<?php
require_once '../config.php';

// Check if logged in & is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'customer') {
    header('Location: ../index.php');
    exit;
}

// Fetch user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$needsProfileSetup = empty($user['name']) || empty($user['shop_address']);
$qrCodeHash = $user['qr_code_hash'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DigiWash - User Dashboard</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Outlined" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrious/4.0.2/qrious.min.js"></script>
    <style>
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .dashboard-grid {
            display: grid;
            grid-template-columns: 250px 1fr;
            gap: 2rem;
        }
        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        .menu-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 12px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .menu-item i {
            margin-right: 15px;
        }
        .menu-item:hover, .menu-item.active {
            background: var(--primary-gradient);
            color: white;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            padding: 1.5rem;
            text-align: center;
        }
        .stat-card h3 { margin-bottom: 0.5rem; font-size: 1.1rem; color: #475569; }
        .stat-card .value { font-size: 2.5rem; font-weight: 700; color: var(--primary); }
        
        /* Sections */
        .section-content { display: none; animation: slideUp 0.4s ease forwards; }
        .section-content.active { display: block; }

        @media (max-width: 768px) {
            .dashboard-grid { grid-template-columns: 1fr; }
            .sidebar { flex-direction: row; overflow-x: auto; padding-bottom: 10px; }
            .menu-item { flex-shrink: 0; padding: 0.8rem 1rem; }
            .menu-item span { display: none; } /* Hide text on mobile nav, icons only */
            .menu-item i { margin-right: 0; }
        }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">
            <span class="material-icons-outlined" style="font-size: 2rem;">local_laundry_service</span>
            <span>DigiWash</span>
        </div>
        <div class="nav-links">
            <a href="#" id="logoutBtn" style="color: var(--danger); display:flex; align-items:center;">
                <span class="material-icons-outlined" style="margin-right: 5px;">logout</span> Logout
            </a>
        </div>
    </nav>

    <div class="container dashboard-grid">
        <!-- Sidebar Navigation -->
        <aside class="sidebar glass-panel" style="padding: 1.5rem; height: max-content;">
            <div class="menu-item active" onclick="switchTab('dashboard')">
                <i class="material-icons-outlined">dashboard</i>
                <span>Dashboard</span>
            </div>
            <div class="menu-item" onclick="switchTab('orders')">
                <i class="material-icons-outlined">add_circle</i>
                <span>New Order</span>
            </div>
            <div class="menu-item" onclick="switchTab('history')">
                <i class="material-icons-outlined">receipt_long</i>
                <span>History</span>
            </div>
            <div class="menu-item" onclick="switchTab('payments')">
                <i class="material-icons-outlined">account_balance_wallet</i>
                <span>Payments</span>
            </div>
            <div class="menu-item" onclick="switchTab('profile')">
                <i class="material-icons-outlined">person</i>
                <span>Profile</span>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="content-area">

            <?php if ($needsProfileSetup): ?>
            <!-- Profile Setup Alert -->
            <div class="glass-panel" style="background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); margin-bottom: 2rem;">
                <h3 style="color: var(--danger); display: flex; align-items: center;">
                    <span class="material-icons-outlined" style="margin-right:10px;">warning</span> Action Required
                </h3>
                <p>Please complete your profile details (Name, Shop Address, Email) to start creating orders.</p>
                <button class="btn btn-primary" style="width: auto;" onclick="switchTab('profile')">Update Profile Now</button>
            </div>
            <?php endif; ?>

            <!-- Dashboard Section -->
            <section id="dashboard" class="section-content active">
                <h2 style="margin-bottom: 2rem;">Ongoing Status</h2>
                
                <div class="stats-grid">
                    <div class="glass-panel stat-card">
                        <h3>Active Orders</h3>
                        <div class="value" id="val_active">0</div>
                    </div>
                    <div class="glass-panel stat-card">
                        <h3>Total Completed</h3>
                        <div class="value" id="val_completed">0</div>
                    </div>
                    <div class="glass-panel stat-card">
                        <h3>Pending Payment</h3>
                        <div class="value" id="val_pending">₹0</div>
                    </div>
                </div>

                <div class="glass-panel">
                    <h3>Recent Activity</h3>
                    <p id="recentActivityText" style="text-align: center; color: #94a3b8; padding: 2rem 0;">No recent orders found. You're all caught up!</p>
                </div>
            </section>

            <!-- Create Order Section -->
            <section id="orders" class="section-content">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                    <h2>Create New Order</h2>
                </div>

                <div class="glass-panel">
                    <?php if ($needsProfileSetup): ?>
                        <p style="color: var(--danger);">Please finish your profile setup before placing an order.</p>
                    <?php else: ?>
                    <form id="orderForm">
                        <div class="form-group">
                            <label>Approximate Weight (in Kg) - ₹50/Kg</label>
                            <input type="number" id="orderWeight" class="form-control" placeholder="e.g. 5" required min="1">
                        </div>
                        <div class="form-group">
                            <label>Special Instructions (Optional)</label>
                            <textarea id="orderInstr" class="form-control" rows="3" placeholder="e.g. Please use fabric softener."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary" id="submitOrderBtn">Request Pickup</button>
                        <p id="orderMsg" style="margin-top: 1rem; font-weight: 600; display: none;"></p>
                    </form>
                    <?php endif; ?>
                </div>
            </section>
            
            <!-- History Section -->
            <section id="history" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Order History</h2>
                
                <div class="tabs">
                    <div class="tab active" onclick="loadHistories('ongoing', this)">Ongoing</div>
                    <div class="tab" onclick="loadHistories('completed', this)">Completed</div>
                </div>

                <div class="glass-panel" id="historyContainer">
                    <p style="text-align: center; color: #94a3b8; padding: 2rem 0;">Loading orders...</p>
                </div>
            </section>

            <!-- Payments Section -->
            <section id="payments" class="section-content">
                <h2 style="margin-bottom: 1.5rem;">Payment Gateway</h2>
                
                <div class="tabs">
                    <div class="tab active" style="color: var(--danger);" onclick="loadPayments('remaining', this)">Remaining Dues</div>
                    <div class="tab" style="color: var(--secondary);" onclick="loadPayments('completed', this)">Completed</div>
                </div>

                <div class="glass-panel text-center" id="paymentContainer">
                    <span class="material-icons-outlined" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;">check_circle</span>
                    <h3>All Clear!</h3>
                    <p>You have no pending invoices to pay.</p>
                </div>
            </section>

            <!-- Profile Section -->
            <section id="profile" class="section-content">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
                    <h2>My Shop Profile</h2>
                </div>
                
                <div class="glass-panel" style="display: flex; gap: 2rem; flex-wrap: wrap;">
                    
                    <!-- QR Code Identity Card -->
                    <div style="flex: 1; min-width: 250px; background: rgba(255,255,255,0.5); padding: 1.5rem; border-radius: 12px; text-align: center; display:flex; flex-direction:column; justify-content:center; align-items:center; border: 1px solid var(--glass-border);">
                        <h3 style="font-size: 1.1rem; margin-bottom: 0.5rem; color: var(--primary);">Delivery QR Code</h3>
                        <p style="font-size: 0.8rem; margin-bottom: 1rem;">Show this to the delivery partner to securely complete deliveries.</p>
                        <canvas id="userQrCode"></canvas>
                        <script>
                            // Generate QR lazily once the section is visible
                            document.addEventListener('DOMContentLoaded', () => {
                                const qrHash = "<?= htmlspecialchars($qrCodeHash) ?>";
                                if(qrHash) {
                                    new QRious({
                                        element: document.getElementById('userQrCode'),
                                        value: qrHash,
                                        size: 180,
                                        level: 'M',
                                        foreground: '#1e293b',
                                        background: 'transparent'
                                    });
                                }
                            });
                        </script>
                    </div>

                    <!-- Profile Edit Form -->
                    <div style="flex: 2; min-width: 300px;">
                        <form id="profileForm">
                        <div class="form-group">
                            <label>Phone Number (Unchangeable after Payment Complete)</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" readonly style="background: #e2e8f0; cursor: not-allowed;">
                        </div>
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" id="p_name" class="form-control" value="<?= htmlspecialchars($user['name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" id="p_email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Shop Address</label>
                            <textarea id="p_address" class="form-control" rows="3" required><?= htmlspecialchars($user['shop_address'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label>Alternate Contact (For Staff)</label>
                            <input type="text" id="p_alt" class="form-control" value="<?= htmlspecialchars($user['alt_contact'] ?? '') ?>">
                        </div>
                        
                        <button type="submit" class="btn btn-primary" id="saveProfileBtn">Save Details</button>
                        <p id="profileMsg" style="margin-top: 1rem; font-weight: 600; display: none;"></p>
                    </form>
                    </div> <!-- End Profile Form Wrapper -->
                </div> <!-- End Flex Container -->
            </section>

        </main>
    </div>

    <!-- Return Request Modal -->
    <div class="modal-overlay" id="returnModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal glass-panel" style="background:white; padding:2rem; border-radius:12px; width:90%; max-width:400px;">
            <h3 style="margin-bottom: 1rem; color:var(--danger);">Request a Return</h3>
            <p style="font-size: 0.9rem; margin-bottom: 1rem;">Please provide details and a clear photo of the issue.</p>
            <input type="hidden" id="returnOrderId">
            
            <div class="form-group">
                <label style="font-size: 0.8rem; font-weight: 600;">Reason for Return</label>
                <textarea id="returnReason" class="form-control" rows="2" placeholder="e.g. Item torn or stain not removed" required></textarea>
            </div>
            
            <div class="form-group">
                <label style="font-size: 0.8rem; font-weight: 600;">Photo Evidence</label>
                <input type="file" id="returnPhoto" accept="image/*" class="form-control" required style="margin-bottom: 1rem;">
            </div>
            
            <button class="btn btn-danger" onclick="submitReturn()" id="btnSubmitReturn">Submit Request</button>
            <button class="btn" onclick="closeReturnModal()" style="background:#e2e8f0; color:#475569; margin-top: 0.5rem;">Cancel</button>
            <p id="returnMsg" style="color:var(--danger); display:none; margin-top:0.5rem;"></p>
        </div>
    </div>

    <script>
        // Tab Switching Logic
        function switchTab(tabId) {
            // Update Sidebar UI
            document.querySelectorAll('.menu-item').forEach(el => el.classList.remove('active'));
            event.currentTarget.classList.add('active');

            // Update Content Visibility
            document.querySelectorAll('.section-content').forEach(el => el.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
        }

        // Logout
        document.getElementById('logoutBtn').addEventListener('click', async (e) => {
            e.preventDefault();
            await fetch('../api/auth.php', {
                method: 'POST',
                headers:{ 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'logout' })
            });
            window.location.href = '../index.php';
        });

        // Initialization and Data Loading
        document.addEventListener('DOMContentLoaded', () => {
            fetchStats();
            loadHistories('ongoing');
            loadPayments('remaining');
        });

        async function fetchStats() {
            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST', headers:{ 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_dashboard_stats' })
                });
                const data = await res.json();
                if(data.success) {
                    document.getElementById('val_active').innerText = data.active_orders;
                    document.getElementById('val_completed').innerText = data.completed_orders;
                    document.getElementById('val_pending').innerText = '₹' + data.pending_payment;
                    if(data.active_orders > 0) {
                        document.getElementById('recentActivityText').innerText = 'Your clothes are currently being processed.';
                    }
                }
            } catch (e) { console.error(e); }
        }

        async function loadHistories(type, tabElement = null) {
            if(tabElement){
                tabElement.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
                tabElement.classList.add('active');
            }
            const container = document.getElementById('historyContainer');
            container.innerHTML = 'Loading...';

            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST', headers:{ 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_orders', type: type })
                });
                const data = await res.json();
                if(data.success && data.orders.length > 0) {
                    container.innerHTML = data.orders.map(o => `
                        <div style="border-bottom:1px solid #e2e8f0; padding:1rem 0; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <strong>Order #${o.id}</strong> - Status: <span style="color:var(--primary);">${o.status.toUpperCase()}</span>
                                <br><small>Amount: ₹${o.total_amount} | Created: ${new Date(o.created_at).toLocaleString()}</small>
                            </div>
                            ${o.status === 'delivered' ? `
                                <button class="btn btn-danger" style="width:auto; padding:0.3rem 0.8rem; font-size:0.8rem;" onclick="openReturnModal(${o.id})">Request Return</button>
                            ` : ''}
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = `<p style="text-align: center; color: #94a3b8; padding: 2rem 0;">No ${type} orders.</p>`;
                }
            } catch(e) { container.innerHTML = 'Error Loading.'; }
        }

        async function loadPayments(type, tabElement = null) {
            if(tabElement){
                tabElement.parentElement.querySelectorAll('.tab').forEach(t=>t.classList.remove('active'));
                tabElement.classList.add('active');
            }
            const container = document.getElementById('paymentContainer');
            container.innerHTML = 'Loading...';

            try {
                const res = await fetch('../api/orders.php', {
                    method: 'POST', headers:{ 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_payments', type: type })
                });
                const data = await res.json();
                if(data.success && data.payments.length > 0) {
                    container.style.textAlign = 'left';
                    container.innerHTML = data.payments.map(o => `
                        <div style="border-bottom:1px solid #e2e8f0; padding:1rem 0;">
                            <strong>Order #${o.order_id}</strong> - <span style="color:${type === 'remaining' ? 'var(--danger)' : 'var(--secondary)'};">${type.toUpperCase()}</span>
                            <br><small>Amount: ₹${o.amount} | Due for Payment via: ${o.payment_mode}</small>
                            ${type === 'remaining' ? `<br><button class="btn btn-primary" style="padding:0.3rem 0.8rem; font-size:0.8rem; width:auto; margin-top:0.5rem;">Pay Now</button>` : ''}
                        </div>
                    `).join('');
                } else {
                    container.style.textAlign = 'center';
                    container.innerHTML = `
                        <span class="material-icons-outlined" style="font-size: 4rem; color: #cbd5e1; margin-bottom: 1rem;">check_circle</span>
                        <h3>All Clear!</h3>
                        <p>No ${type} invoices found.</p>
                    `;
                }
            } catch(e) { container.innerHTML = 'Error Loading.'; }
        }

        // Create Order Submit Logic
        const orderForm = document.getElementById('orderForm');
        if(orderForm) {
            orderForm.addEventListener('submit', async(e) => {
                e.preventDefault();
                const btn = document.getElementById('submitOrderBtn');
                const msg = document.getElementById('orderMsg');
                btn.innerHTML = 'Creating...'; btn.disabled = true; msg.style.display = 'none';

                try {
                    const res = await fetch('../api/orders.php', {
                        method: 'POST', headers:{ 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'create_order', weight: document.getElementById('orderWeight').value, instructions: document.getElementById('orderInstr').value })
                    });
                    const result = await res.json();
                    
                    msg.innerText = result.message;
                    msg.style.display = 'block';
                    msg.style.color = result.success ? 'var(--secondary)' : 'var(--danger)';
                    btn.innerHTML = 'Request Pickup'; btn.disabled = false;

                    if(result.success) {
                        fetchStats();
                        loadHistories('ongoing');
                        loadPayments('remaining');
                        switchTab('history');
                        orderForm.reset();
                    }
                } catch(e) {
                    msg.innerText = "Error requesting server."; msg.style.color = "var(--danger)"; msg.style.display = 'block';
                    btn.innerHTML = 'Request Pickup'; btn.disabled = false;
                }
            });
        }

        // Real Save Profile Logic
        document.getElementById('profileForm').addEventListener('submit', async(e) => {
            e.preventDefault();
            const btn = document.getElementById('saveProfileBtn');
            const msg = document.getElementById('profileMsg');
            btn.innerHTML = 'Saving...';
            btn.disabled = true;
            msg.style.display = 'none';

            try {
                const res = await fetch('../api/user.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_profile',
                        name: document.getElementById('p_name').value,
                        email: document.getElementById('p_email').value,
                        shop_address: document.getElementById('p_address').value,
                        alt_contact: document.getElementById('p_alt').value
                    })
                });
                
                const result = await res.json();
                
                msg.innerText = result.message;
                msg.style.display = 'block';
                msg.style.color = result.success ? 'var(--secondary)' : 'var(--danger)';
                btn.innerHTML = 'Save Details';
                btn.disabled = false;

                if (result.success) {
                    setTimeout(() => window.location.reload(), 1500);
                }
            } catch (error) {
                msg.innerText = "Error requesting server."; 
                msg.style.color = "var(--danger)"; 
                msg.style.display = 'block';
                btn.innerHTML = 'Save Details'; 
                btn.disabled = false;
            }
        });

        // Return Request Logic
        function openReturnModal(orderId) {
            document.getElementById('returnOrderId').value = orderId;
            document.getElementById('returnReason').value = '';
            document.getElementById('returnPhoto').value = '';
            document.getElementById('returnModal').style.display = 'flex';
        }

        function closeReturnModal() {
            document.getElementById('returnModal').style.display = 'none';
            document.getElementById('returnMsg').style.display = 'none';
        }

        async function submitReturn() {
            const orderId = document.getElementById('returnOrderId').value;
            const reason = document.getElementById('returnReason').value;
            const photoFile = document.getElementById('returnPhoto').files[0];
            const btn = document.getElementById('btnSubmitReturn');
            const msg = document.getElementById('returnMsg');

            if(!reason || !photoFile) { msg.innerText = "Reason and Photo are required."; msg.style.display = 'block'; return; }

            btn.innerHTML = 'Uploading...'; btn.disabled = true; msg.style.display = 'none';

            const formData = new FormData();
            formData.append('action', 'request_return');
            formData.append('order_id', orderId);
            formData.append('reason', reason);
            formData.append('return_photo', photoFile);

            try {
                const res = await fetch('../api/orders.php', { method: 'POST', body: formData });
                const data = await res.json();
                
                if(data.success) {
                    alert(data.message);
                    closeReturnModal();
                } else {
                    msg.innerText = data.message; msg.style.display = 'block';
                }
            } catch(e) { msg.innerText = "Error submitting request."; msg.style.display = 'block'; }
            
            btn.innerHTML = 'Submit Request'; btn.disabled = false;
        }
    </script>
</body>
</html>