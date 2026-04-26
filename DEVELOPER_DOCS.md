# DigiWash — Developer Documentation
> Version 2.0 | Stack: PHP 8.x · MySQL · Firebase · Razorpay · Cloudflare Turnstile

---

## Table of Contents
1. [Project Overview](#1-project-overview)
2. [Architecture](#2-architecture)
3. [File Structure](#3-file-structure)
4. [Authentication System](#4-authentication-system)
5. [Module Reference](#5-module-reference)
6. [API Reference](#6-api-reference)
7. [Database Schema](#7-database-schema)
8. [System Flows](#8-system-flows)
9. [Local Setup Guide](#9-local-setup-guide)
10. [Hostinger Deployment](#10-hostinger-deployment)
11. [Testing Guide](#11-testing-guide)
12. [Security Model](#12-security-model)
13. [Environment Variables](#13-environment-variables)

---

## 1. Project Overview

DigiWash is a **B2C SaaS laundry platform** connecting customers with premium laundry services. Customers place orders; a delivery network picks up, processes, and returns laundry. Fully managed through dedicated web dashboards for customers, delivery staff, and administrators.

### Key Features
| Feature | Description |
|---|---|
| Phone OTP Login | Firebase Phone Auth + Cloudflare Turnstile bot protection |
| Google Sign-In | OAuth2 via Firebase, with mandatory phone verification gate |
| Multi-step Order Wizard | Service type → product selection → add-ons → checkout |
| Razorpay Payments | Live payment gateway with HMAC webhook verification |
| Automated Refunds | Admin-approved refunds via Razorpay Refund API with ARN tracking |
| Delivery Tracking | QR-code based pickup/delivery confirmation |
| Marketplace | Customers browse and order from partner shops |
| Market Requests | Community-suggested new service zones with admin approval |
| Admin Dashboard | Full operations panel — orders, users, markets, refunds, invoices |
| Push Notifications | Firebase Cloud Messaging (FCM) for real-time alerts |

---

## 2. Architecture

```
Browser / Client (Vanilla JS + Firebase SDK + Razorpay Checkout)
        │ HTTPS
PHP Application Layer
├── Public pages  (index.php, about.php, contact.php ...)
├── user/         (Customer portal)
├── admin/        (Admin portal)
├── delivery/     (Delivery agent portal)
└── api/          (JSON API endpoints — acts as controller layer)
        │
   ┌────┴──────────────┬────────────────┐
   MySQL (PDO)    Firebase Auth+FCM   Razorpay Payments
```

**Pattern:** Procedural PHP with a thin JSON API layer. Each `api/*.php` acts as a controller; sessions provide stateful auth. No framework — keeps deployment minimal for shared hosting.

---

## 3. File Structure

```
DigiWash/
├── .env                          # All secrets (never committed)
├── config.php                    # DB, session, CSP headers, helper functions
├── public_header.php             # Shared nav + script tags (Firebase, Turnstile)
├── public_footer.php             # Shared footer + unified Auth modal
├── schema.sql                    # Full production database schema
├── migration_v2.sql              # Incremental migration (market_requests, otp_requests)
├── cron.php                      # Scheduled tasks (cleanup, expiry)
│
├── api/
│   ├── auth.php                  # Login, logout, Google OAuth, phone verify
│   ├── orders.php                # Order lifecycle CRUD
│   ├── payments.php              # Razorpay order create, verify, refund, track
│   ├── delivery.php              # Delivery agent actions (pickup, complete)
│   ├── admin.php                 # Admin CRUD (users, stats, coupons)
│   ├── products.php              # Service catalog management
│   ├── market_requests.php       # Area request workflow
│   ├── invoice.php               # PDF invoice generation
│   ├── webhook.php               # Razorpay webhook (HMAC verified)
│   ├── verify_turnstile.php      # Cloudflare Turnstile + OTP rate limiting
│   ├── contact.php               # Customer support messages
│   ├── user.php                  # Profile + notification preferences
│   ├── staff_requests.php        # Delivery partner applications
│   ├── marketplace_orders.php    # Partner marketplace orders
│   ├── marketplace_products.php  # Partner product management
│   ├── create_marketplace_order.php
│   ├── update_marketplace_status.php
│   └── file.php                  # File upload handler
│
├── user/
│   ├── dashboard.php             # Customer portal
│   ├── marketplace.php           # Customer marketplace browser
│   └── verify_phone.php          # Phone verification for Google users
│
├── admin/
│   ├── dashboard.php             # Full admin operations panel
│   ├── marketplace_products.php
│   └── marketplace_orders.php
│
├── delivery/
│   └── dashboard.php             # Delivery agent portal (QR scan, status)
│
└── assets/
    └── css/
        ├── style.css             # Dashboard styles
        └── landing.css           # Public page styles
```

---

## 4. Authentication System

### Unified Login — Single Entry Point
All roles (customer, admin, delivery) log in through the same modal in `public_footer.php`. Role-based redirection happens server-side after token verification in `api/auth.php`.

### Flow A: Phone OTP (Firebase)
```
Phone input → Cloudflare Turnstile check (frontend)
           → api/verify_turnstile.php (Turnstile API + DB rate limit)
           → firebase.signInWithPhoneNumber() → OTP via SMS
           → confirmationResult.confirm(code) → Firebase ID token
           → api/auth.php [firebase_login] → $_SESSION created
           → redirect by role
```

### Flow B: Google Sign-In
```
signInWithPopup(GoogleAuthProvider) → ID token
    → api/auth.php [firebase_login]
    → phone is GOOGLE_PENDING_? → redirect to user/verify_phone.php
    → After phone OTP: api/auth.php [save_verified_phone]
    → redirect to user/dashboard.php
```

### Flow C: Staff (Dummy OTP)
Staff (admin/delivery) use a pre-set 6-digit PIN (`dummy_otp` in DB). No Firebase charge for internal logins.

### Session Keys
```php
$_SESSION['user_id']      // DB primary key
$_SESSION['role']         // 'customer' | 'admin' | 'delivery'
$_SESSION['phone']        // 10-digit number
$_SESSION['firebase_uid'] // Firebase UID
$_SESSION['csrf_token']   // 32-byte hex, regenerated on login
```

### CSRF Protection
All state-changing API calls require `X-CSRF-Token` header or `csrf_token` in the JSON body. Token is per-session, regenerated on each login.

---

## 5. Module Reference

### Order Management (`api/orders.php`)
**Status flow:** `pending` → `confirmed` → `picked_up` → `processing` → `ready` → `out_for_delivery` → `delivered`

| Action | Role | Description |
|---|---|---|
| `place_order` | Customer | Creates order, validates market/product |
| `get_my_orders` | Customer | Paginated order list with status filter |
| `cancel_order` | Customer | Cancels; queues refund if payment made |
| `get_all_orders` | Admin | All orders with filters and search |
| `assign_delivery` | Admin | Assigns delivery agent to order |
| `update_order_status` | Admin | Advances order through lifecycle |

### Payment Integration (`api/payments.php`)
- `create_razorpay_order` — Calls Razorpay API; returns `order_id` for frontend checkout
- `verify_payment` — HMAC signature verification before marking as `paid`
- `initiate_refund` (admin) — Calls Razorpay Refund API; stores refund ID + ARN
- `track_refund` (customer) — Proxies Razorpay refund status

### Delivery System (`api/delivery.php`)
Agents use a mobile-friendly portal to scan QR codes for pickup/delivery confirmation and update order status.

### Market Requests (`api/market_requests.php`)
Customers request new service zones. Admins approve/reject. Approval auto-inserts into `markets` table and sends FCM notification to the user.

---

## 6. API Reference

All endpoints: `POST /api/{file}.php` · `Content-Type: application/json`
Protected endpoints require `X-CSRF-Token` header.

### auth.php
| Action | Auth | Key Parameters | Returns |
|---|---|---|---|
| `firebase_login` | None | `idToken, phone?, email?, name?` | `{success, redirect}` |
| `dummy_login` | None | `phone, otp` | `{success, redirect}` |
| `logout` | CSRF | — | `{success}` |
| `save_verified_phone` | CSRF | `phone` | `{success}` |

### orders.php
| Action | Auth | Key Parameters | Returns |
|---|---|---|---|
| `place_order` | Customer+CSRF | `market_id, product_id, service_type, addons[], quantity, pickup_date` | `{success, order_id}` |
| `get_my_orders` | Customer | `page?, status?` | `{success, orders[], total}` |
| `cancel_order` | Customer+CSRF | `order_id` | `{success, message}` |
| `get_all_orders` | Admin | `page?, status?, search?` | `{success, orders[], total}` |
| `assign_delivery` | Admin+CSRF | `order_id, delivery_user_id` | `{success}` |

### payments.php
| Action | Auth | Key Parameters | Returns |
|---|---|---|---|
| `create_razorpay_order` | Customer+CSRF | `order_id, amount` | `{success, rzp_order_id, key}` |
| `verify_payment` | Customer+CSRF | `razorpay_order_id, razorpay_payment_id, razorpay_signature, order_id` | `{success}` |
| `initiate_refund` | Admin+CSRF | `order_id, amount?, reason?` | `{success, refund_id}` |
| `track_refund` | Customer | `order_id` | `{success, status, speed, arn}` |

### market_requests.php
| Action | Auth | Key Parameters | Returns |
|---|---|---|---|
| `submit_market_request` | Customer+CSRF | `market_name, city, pincode, landmark?` | `{success, message}` |
| `get_market_requests_count` | Admin | — | `{success, count}` |
| `get_market_requests` | Admin | `status?` | `{success, requests[]}` |
| `approve_market_request` | Admin+CSRF | `request_id` | `{success}` |
| `reject_market_request` | Admin+CSRF | `request_id` | `{success}` |

### verify_turnstile.php
| Method | Auth | Parameters | Returns |
|---|---|---|---|
| POST | None | `cf_token, phone` | `{success, message}` |

**Rate limits:** 3 OTPs/phone/10min · 5 OTPs/IP/15min

---

## 7. Database Schema

### `users`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `firebase_uid` | VARCHAR(128) UNIQUE | Indexed |
| `phone` | VARCHAR(15) | `GOOGLE_PENDING_{uid}` for unverified |
| `phone_verified` | TINYINT(1) | 0=no, 1=yes |
| `email` | VARCHAR(255) | |
| `name` | VARCHAR(255) | |
| `role` | ENUM | `customer`, `admin`, `delivery` |
| `dummy_otp` | VARCHAR(10) | Staff PIN only |
| `qr_code_hash` | VARCHAR(64) UNIQUE | For QR scanning |
| `is_blocked` | TINYINT(1) | Admin-blocked users |
| `fcm_token` | TEXT | Push notification token |
| `created_at` | TIMESTAMP | |

### `orders`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `user_id` | INT FK→users | |
| `market_id` | INT FK→markets | |
| `product_id` | INT FK→products | |
| `service_type` | VARCHAR(50) | |
| `order_details` | JSON | Add-ons, quantities |
| `amount` | DECIMAL(10,2) | |
| `status` | ENUM | Full lifecycle |
| `pickup_date` | DATE | |
| `assigned_to` | INT FK→users | Delivery agent |
| `created_at` | TIMESTAMP | |

### `payments`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `order_id` | INT FK→orders | |
| `rzp_order_id` | VARCHAR(100) | |
| `rzp_payment_id` | VARCHAR(100) | |
| `amount` | DECIMAL(10,2) | |
| `status` | ENUM | `pending`, `paid`, `failed` |
| `paid_at` | TIMESTAMP | |

### `refunds`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `order_id` | INT FK→orders | |
| `rzp_refund_id` | VARCHAR(100) | |
| `arn` | VARCHAR(100) | Bank tracking number |
| `amount` | DECIMAL(10,2) | |
| `status` | ENUM | `requested`, `approved`, `rejected` |
| `reason` | TEXT | |
| `created_at` | TIMESTAMP | |

### `markets`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `name` | VARCHAR(255) | Zone name |
| `city` | VARCHAR(100) | |
| `pincode` | VARCHAR(10) | |
| `is_active` | TINYINT(1) | |

### `market_requests`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `user_id` | INT FK→users | Requester |
| `market_name` | VARCHAR(255) | |
| `city` / `pincode` | VARCHAR | |
| `landmark` | VARCHAR(255) | Optional |
| `status` | ENUM | `pending`, `approved`, `rejected` |
| `created_at` | TIMESTAMP | |

### `otp_requests`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `phone` | VARCHAR(15) | Indexed |
| `otp_hash` | VARCHAR(255) | SHA-256 hash |
| `expires_at` | DATETIME | 5-minute window |
| `ip_address` | VARCHAR(45) | For IP rate limiting |
| `created_at` | TIMESTAMP | |

### `notifications`
| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `user_id` | INT FK→users | NULL = broadcast to admins |
| `title` | VARCHAR(255) | |
| `body` | TEXT | |
| `type` | VARCHAR(50) | `order_update`, `refund`, `market_request` |
| `is_read` | TINYINT(1) | |
| `created_at` | TIMESTAMP | |

---

## 8. System Flows

### Customer Order Flow
```
Login → Select market zone → 3-step wizard:
  Step 1: Service type (Wash / Dry Clean / Iron)
  Step 2: Product + quantity
  Step 3: Add-ons + review total
→ Razorpay checkout → webhook confirms payment
→ Admin confirms → assigns delivery agent
→ Agent QR scans pickup → processing → out for delivery
→ Agent QR scans delivery → FCM notification to customer
```

### Refund Flow
```
Customer cancels paid order
→ Refund record created (status: requested)
→ Admin reviews Refunds tab → approves
→ Razorpay Refund API called → ARN stored
→ Customer tracks via "Track Refund" button
```

### Market Request Flow
```
Customer submits area request (name, city, pincode)
→ Duplicate check → saved as pending → admins notified
→ Admin approves → area added to markets table → user notified
→ Admin rejects → user notified
```

---

## 9. Local Setup Guide

**Prerequisites:** XAMPP (PHP 8.1+, MySQL), Composer, Firebase project, Razorpay test account

```bash
# 1. Clone
git clone <repo> c:/xampp/htdocs/dashboard/DigiWash

# 2. Install dependencies
composer install

# 3. Create .env (see §13 for all variables)
# Use Cloudflare Turnstile test keys for local dev:
# CF_TURNSTILE_SITE_KEY=1x00000000000000000000AA
# CF_TURNSTILE_SECRET=1x0000000000000000000000000000000AA

# 4. Create DB and import schema
# phpMyAdmin: CREATE DATABASE digiwash
# Import: schema.sql, then migration_v2.sql

# 5. Create admin user
# INSERT INTO users (name,phone,role,dummy_otp,phone_verified) VALUES ('Admin','9999999999','admin','123456',1);

# 6. Start XAMPP → visit http://localhost/dashboard/DigiWash/
```

---

## 10. Hostinger Deployment

1. Upload all files to `public_html/` (FTP or Git deploy)
2. Set env vars in Hostinger hPanel → PHP Config
3. Move Firebase Admin SDK JSON **outside** `public_html/`, update `FIREBASE_SA_PATH`
4. Run `schema.sql` + `migration_v2.sql` via phpMyAdmin
5. Set Razorpay webhook URL: `https://yourdomain.com/api/webhook.php`
6. Add cron job: `curl -s "https://yourdomain.com/cron.php?token=CRON_SECRET"`

---

## 11. Testing Guide

### Login Scenarios
| Scenario | Expected Result |
|---|---|
| New phone OTP | Account created, redirect to dashboard |
| Existing phone OTP | Login, role-based redirect |
| Google (new user) | Redirect to verify_phone.php |
| Google (returning) | Redirect to dashboard |
| Staff dummy OTP | Redirect to admin/delivery dashboard |
| 4+ OTPs same phone/10min | "Too many attempts" blocked |

### Payment Testing
- Razorpay test card: `4111 1111 1111 1111` · CVV: `123` · Expiry: any future
- Test UPI: `success@razorpay`
- Webhook: Test via Razorpay dashboard test events

### Order + Refund Testing
1. Customer places order → completes Razorpay checkout
2. Admin confirms → assigns delivery agent
3. Delivery agent marks picked up (QR scan)
4. Admin marks delivered → customer receives FCM notification
5. Cancel paid order → Admin approves refund → customer tracks ARN

---

## 12. Security Model

| Layer | Implementation |
|---|---|
| Authentication | Firebase ID token verified via Identity Toolkit REST API |
| Sessions | HTTPOnly, SameSite cookies; regenerated on login |
| CSRF | Per-session token on all state-changing requests |
| SQL Injection | 100% PDO prepared statements |
| XSS | `htmlspecialchars()` on all output |
| Bot Protection | Cloudflare Turnstile (replaces Google reCAPTCHA) |
| Rate Limiting | 3 OTP/phone/10min · 5 OTP/IP/15min (server-side DB) |
| Webhook Integrity | HMAC-SHA256 verified (Razorpay) |
| CSP | Restrictive Content-Security-Policy on all pages |
| Sensitive Keys | `.env` only — never hardcoded |
| Firebase Admin SDK | Stored outside `public_html` in production |

---

## 13. Environment Variables

| Variable | Required | Description |
|---|---|---|
| `DB_HOST` | ✅ | MySQL host |
| `DB_NAME` | ✅ | Database name |
| `DB_USER` | ✅ | DB username |
| `DB_PASS` | ✅ | DB password |
| `FIREBASE_API_KEY` | ✅ | Firebase Web API key |
| `FIREBASE_AUTH_DOMAIN` | ✅ | |
| `FIREBASE_PROJECT_ID` | ✅ | |
| `FIREBASE_STORAGE_BUCKET` | ✅ | |
| `FIREBASE_MESSAGING_SENDER_ID` | ✅ | |
| `FIREBASE_APP_ID` | ✅ | |
| `FIREBASE_VAPID_KEY` | ✅ | Web push VAPID key |
| `FIREBASE_SERVICE_ACCOUNT_JSON` | ✅ | Admin SDK JSON path |
| `FIREBASE_SA_PATH` | Prod | Absolute path outside public_html |
| `CF_TURNSTILE_SITE_KEY` | ✅ | Cloudflare Turnstile site key |
| `CF_TURNSTILE_SECRET` | ✅ | Cloudflare Turnstile secret |
| `RAZORPAY_KEY_ID` | ✅ | Razorpay API key |
| `RAZORPAY_KEY_SECRET` | ✅ | Razorpay API secret |
| `RAZORPAY_WEBHOOK_SECRET` | ✅ | Webhook HMAC secret |
| `FAST2SMS_API_KEY` | Optional | SMS fallback |
| `CRON_SECRET` | ✅ | Protects cron.php endpoint |
