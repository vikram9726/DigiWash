# DigiWash — Complete Manual Test Suite 🧪

As a Senior QA Engineer, I have analyzed the architecture, dependencies (Razorpay, Firebase), and logic (Pay Later, Delivery PINs) of the **DigiWash B2B SaaS Platform**. 

Below is a structured, bug-focused manual testing suite designed specifically for real-world scenarios in PHP/MySQL applications.

---

## 🏗️ Module Coverage Summary

1. **AUTH:** Registration, OTP Login, JWT Sync, RBAC, and Security validations.
2. **CUSTOMER:** Profile completion, Laundry service orders, Cart validation, Dashboard UI/UX.
3. **COMMERCE:** Marketplace orders, "Pay Later" limits, Bulk checkout, Coupon application.
4. **DELIVERY:** Live assignment, PIN/QR proof-of-delivery, Route bypasses, Offline resilience.
5. **ADMIN:** System controls, Analytics, Order overrides, Delivery assignments.

---

## 1. Authentication & Onboarding (AUTH)
*Focus: Session fixation, Rate limiting, Invalid inputs.*

| Test ID | Module | Scenario | Test Steps | Test Data | Expected Result | Actual | Status | Priority |
|---|---|---|---|---|---|---|---|---|
| TC_AUTH_01 | Reg/Login | Standard user login (Positive) | 1. Enter valid phone number<br>2. Click Send OTP<br>3. Enter OTP | Phone: `9876543210`<br>OTP: `Valid Code` | User successfully logs in and redirects to `user/dashboard.php`. Session initializes. | | | High |
| TC_AUTH_02 | Reg/Login | OTP Rate Limit (Negative/Sec) | 1. Rapidly click "Send OTP" 6 times within 5 minutes | Phone: `9876543210` | 6th attempt blocked. Error: "Too many OTP requests. Please wait 10 minutes." | | | High |
| TC_AUTH_03 | Reg/Login | Invalid Phone Format (Negative) | 1. Enter phone number with letters/symbols | Phone: `+ABCD123!@` | Form blocked. Native HTML5 regex fails. "Enter 10-digit number". | | | Low |
| TC_AUTH_04 | Reg/Login | Blocked User Login attempt | 1. Login with a phone number blocked by Admin | Phone: `(Blocked #)` | OTP verifies, but backend rejects login. Error: "Your account is blocked." | | | High |
| TC_AUTH_05 | Profile | SQL Injection Profile Update | 1. Go to Profile<br>2. Edit Name with SQLi payload | Name: `' OR 1=1; DROP TABLE users;--` | Input sanitized/parameterized. Value saves exactly as typed, DB intact. | | | Critical |
| TC_AUTH_06 | Staff Auth | Delivery Dummy PIN verification | 1. Go to Staff Login<br>2. Enter assigned phone & dummy PIN | Phone: `Delivery #`<br>PIN: `0000` | Redirects to `delivery/dashboard.php`. Nav is correctly restricted. | | | High |

---

## 2. Customer Dashboard & Orders (CUSTOMER)
*Focus: Form manipulation, State machine limits, File uploads.*

| Test ID | Module | Scenario | Test Steps | Test Data | Expected Result | Actual | Status | Priority |
|---|---|---|---|---|---|---|---|---|
| TC_CUST_01 | Orders | Placing standard COD laundry order | 1. Add 2 shirts<br>2. Select COD<br>3. Submit | Items: `2x Shirt` | Order created. Status = `pending`. Toast notification appears. | | | High |
| TC_CUST_02 | Orders | COD Limit restriction | 1. Attempt to place COD order with 4 unpaid orders existing | Existing Orders: `4 pending COD` | Button disabled / Backend rejects. Error: "Settle previous dues first." | | | Medium |
| TC_CUST_03 | Coupon | Race Condition (Coupon Limit) | 1. Open 2 tabs for checkout<br>2. Apply single-use coupon simultaneously globally | Coupon: `FIRSTORDER` (Limit: 1) | Request 1 succeeds. Request 2 fails (`SELECT FOR UPDATE` lock). | | | High |
| TC_CUST_04 | Uploads | Return Dispute Upload (Malicious) | 1. Initiate return for delivered order<br>2. Upload `.php` shell disguised as `.jpg` | File: `shell.php.jpg` (fake MIME) | `finfo()` detects invalid MIME. Upload rejected. "Invalid image format". | | | Critical |
| TC_CUST_05 | UI/UX | Mobile Navigation Rendering | 1. Reduce browser width to 375px (iPhone SE)<br>2. Expand sidebar nav | viewport: `375px` | Sidebar transitions smoothly. Tables shrink with scrolling (`overflow-x`). | | | Medium |
| TC_CUST_06 | Orders | Order Cancel timing | 1. Cancel order in `pending` state<br>2. Cancel order in `picked_up` state | Order ID: `12` | 1 succeeds. 2 fails (Backend locks cancellation once picked up). | | | High |

---

## 3. Commerce & Payments (COMMERCE)
*Focus: Transaction atomicity, Razorpay webhooks, Price manipulation.*

| Test ID | Module | Scenario | Test Steps | Test Data | Expected Result | Actual | Status | Priority |
|---|---|---|---|---|---|---|---|---|
| TC_COM_01 | Market | Pay Later Limit Exhaustion | 1. Place 5th marketplace order on a 4-order Pay Later plan | Plan: `PAY_LATER_4` | Order rejected. Error: "Credit limit reached. Pay dues first." | | | High |
| TC_COM_02 | Market | Price Tampering (Negative) | 1. Intercept `create_order` API call<br>2. Change `total_amount` to `1.00` | Amount: `1.00` | Backend recalculates cart total directly from DB. Order totals reflect DB prices, not payload. | | | Critical |
| TC_COM_03 | Payment | Bulk Checkout Razorpay Initiation | 1. Go to Payments<br>2. Click 'Pay All Now'<br>3. Verify total sum | Laundry: `₹500`, Market: `₹300` | Razorpay modal opens with exactly `₹800.00`. | | | High |
| TC_COM_04 | Payment | Razorpay Success Webhook | 1. Pay via Razorpay Success flow<br>2. Close modal | RZP: `pay_abc123` | Server verifies `hmac_sha256` signature. `status` universally updates to 'completed'. PDF generates. | | | High |
| TC_COM_05 | Payment | Razorpay Interruption (Edge Case) | 1. Open Razorpay modal<br>2. Force close tab without payment | RZP Modal | Order remains 'remaining'. No database updates occur. | | | Medium |

---

## 4. Delivery Operations (DELIVERY)
*Focus: Geo-location logic, PIN validation, Workflow integrity.*

| Test ID | Module | Scenario | Test Steps | Test Data | Expected Result | Actual | Status | Priority |
|---|---|---|---|---|---|---|---|---|
| TC_DEL_01 | Status | Update 'Assigned' to 'Picked Up' | 1. Swipe 'Mark Picked Up' on assigned card | Order ID: `89` | Order moves to 'In Process' tab. Customer gets "Picked Up" push notification. | | | High |
| TC_DEL_02 | Deliver | Marketplace PIN Verification | 1. Click 'Verify PIN to Deliver'<br>2. Enter correct 6-digit PIN | Input PIN matches Customer Dash PIN | PIN validates. Order marked 'delivered'. UI removes card. | | | High |
| TC_DEL_03 | Deliver | Invalid PIN Attempt | 1. Enter random PIN | PIN: `999999` | Error: "Invalid Delivery PIN." Delivery state remains `out_for_delivery`. | | | High |
| TC_DEL_04 | Deliver | PIN Time-Window Tolerance (Edge) | 1. Use Customer PIN generated 29 mins ago | Old PIN string | Graceful acceptance (30-min window constraint validation). | | | Medium |
| TC_DEL_05 | Geo | Maps Integration | 1. Click "Navigate to Shop"<br>2. Verify URI intent | Coordinates | Opens Google Maps App natively handling parameters `?q=lat,lng`. | | | Low |
| TC_DEL_06 | UI | Offline Fallback Simulation | 1. Go Offline (Chrome Dev Tools)<br>2. Mark Picked up | Network: `Offline` | PWA Service-worker caches request. UI updates optimistically. Resyncs when online. | | | High |

---

## 5. Admin Controls (ADMIN)
*Focus: Data pagination, RBAC, Safe deletion.*

| Test ID | Module | Scenario | Test Steps | Test Data | Expected Result | Actual | Status | Priority |
|---|---|---|---|---|---|---|---|---|
| TC_ADM_01 | View | Verify Orders Pagination | 1. Go to Admin Orders<br>2. Add 20 orders<br>3. Click page 2 | Orders > `limit` | Orders list slices exactly at Offset. `LIMIT / OFFSET` SQL query fires perfectly. | | | High |
| TC_ADM_02 | Access | Direct URL Access Bypass | 1. As Customer, navigate to `/admin/dashboard.php` manually | User: `customer` | Immediate `header('Location: ../index.php')` redirect. 403 Forbidden. | | | Critical |
| TC_ADM_03 | Actions | Admin Order Override Cancellation | 1. Cancel an 'in_process' order<br>2. Verify DB states | Order in wash | Status = 'cancelled'. Delivery partner load `-1`. Coupon usages reverted if applicable. | | | High |
| TC_ADM_04 | UI/UX | Dashboard Analytics Rendering | 1. Load Admin Panel on iPad size<br>2. Verify Revenue chart | Viewport: `768px` | Chart.js renders correctly without overlapping table containers. | | | Medium |

---

## 💡 Risky Areas & Bug-Hunting Recommendations for QA

1. **The "Pay All Now" Lock Condition:** Verify what happens if the user presses "Pay All Now" exactly as the delivery partner marks the order as "delivered" (Network race condition). The payment gateway must serialize DB checks.
2. **File Permissions (`cPanel` specificity):** Because XAMPP handles file permissions differently than Ubuntu/cPanel, strictly verify the `api/file.php` proxy. Test directory traversal payload: `GET /api/file.php?path=../../.env` (It *should* return 403 Forbidden or 404).
3. **FCM Timeout Latency:** If Firebase goes down, ensure the `create_order` API call does not hang for 30s. Background queues should catch the timeout and proceed.
4. **Proxy Caching:** Ensure the `api/orders.php` endpoint responds with proper headers (`Cache-Control: no-cache`) so customers aren't seeing old status boards from their browser cash.
