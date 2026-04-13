# 🧺 DigiWash — Laundry Management Platform

> **A full-stack SaaS laundry management system** for local shopkeepers and customers in India. Built with PHP, MySQL, Firebase Auth, Razorpay Payments, and a modern responsive UI.

**Live Domain:** [https://digiwash.in](https://digiwash.in)

---

## ✨ Features

### 🔐 Authentication
- **Phone OTP Login** via Firebase Auth (reCAPTCHA verified)
- **Google OAuth** login for customers
- **Staff / Delivery Login** via admin-assigned PIN code
- Role-based access: `customer`, `delivery`, `admin`

### 👤 Customer Dashboard
- Place laundry orders with product selection and pricing tiers
- **Razorpay online payments** (live mode supported)
- **Pay Later** credit plans (4 / 8 / 12 order cycles)
- Real-time order tracking with status timeline
- Coupon code application at checkout
- Order cancellation with reason tracking
- Return requests with photo upload
- Push notifications via Firebase Cloud Messaging (FCM)
- PDF invoice generation and download (via DomPDF)

### 🚚 Delivery Partner Dashboard
- View and accept assigned orders
- **Pick Up → In Process → Out for Delivery → Delivered** workflow
- PIN-based delivery verification (customer confirms with OTP)
- Photo bypass for unattended deliveries
- Release / cancel assigned orders back to queue

### 🛠️ Admin Panel
- **Platform Overview** with revenue charts, order distribution, and trend analytics (Chart.js)
- **Customer Management** — search, view history, block/unblock, delete, manage Pay Later approvals
- **Order Management** — filter by status/market, assign delivery partners, update status, mark payments
- **Market Zones** — create and manage geographic service areas
- **Delivery Partners** — add, edit, view activity, assign to markets
- **Return Requests** — review with photo evidence, approve/decline
- **Product Catalog** — add/edit/delete laundry products with configurable pricing tiers
- **Invoices & Billing** — combined invoice tracker, receipt design settings (store name, GST, address)
- **Coupons & Notifications** — create coupons with usage limits, expiry, per-user caps; send push notifications to all users
- **📩 Customer Messages** — view, filter (New/Read/Resolved), mark read, resolve, and delete messages submitted via the public contact modal

### 🛒 Marketplace Module
- Separate **storefront product catalog** with per-meter pricing (e.g., bedsheets by length × width)
- Marketplace order placement with Razorpay checkout
- Admin management of marketplace products and orders
- Marketplace invoice generation

### 🌐 Public Website (Compliance-Ready)
Production-ready pages for **Google OAuth** and **Razorpay payment gateway** approval:

| Page | File | Purpose |
|---|---|---|
| Landing Page | `index.php` | Hero section, features, trust badges, CTA, auth modal |
| About Us | `about.php` | Company story, mission, and values |
| Contact Us | `contact.php` | Support details, operating hours, message modal |
| Privacy Policy | `privacy-policy.php` | Data collection, usage, and protection policies |
| Terms & Conditions | `terms.php` | Service terms, user responsibilities, liability |
| Refund & Cancellation | `refund-policy.php` | Refund eligibility, process, and timelines |

All public pages share a common header (`public_header.php`) and footer (`public_footer.php`) with:
- Responsive navigation linking back to `index.php`
- **"Contact Admin via Message"** popup modal on every page — submits to `contact_messages` DB table and appears in the admin dashboard

---

## 📁 Project Structure

```
DigiWash/
├── index.php                  # Landing page + Firebase Auth modal
├── config.php                 # DB connection, session, security headers, Firebase config
├── schema.sql                 # Full database schema
├── .env                       # Environment variables (DO NOT COMMIT)
├── cron.php                   # Scheduled tasks (auto-cancel stale orders)
│
├── api/                       # Backend REST API
│   ├── auth.php               # Firebase token verification + session creation
│   ├── admin.php              # Admin CRUD operations
│   ├── orders.php             # Order lifecycle management
│   ├── payments.php           # Razorpay payment verification
│   ├── delivery.php           # Delivery partner operations
│   ├── products.php           # Laundry product catalog CRUD
│   ├── invoice.php            # Invoice generation (DomPDF)
│   ├── contact.php            # Public message form + admin message management
│   ├── user.php               # User profile operations
│   ├── staff_requests.php     # Customer → staff communication
│   ├── marketplace_products.php
│   ├── marketplace_orders.php
│   ├── create_marketplace_order.php
│   ├── update_marketplace_status.php
│   └── marketplace_invoice.php
│
├── admin/                     # Admin dashboard
│   ├── dashboard.php          # Full admin panel (sidebar + all sections)
│   ├── marketplace_products.php
│   └── marketplace_orders.php
│
├── user/
│   ├── dashboard.php          # Customer dashboard
│   └── marketplace.php        # Customer marketplace view
│
├── delivery/
│   └── dashboard.php          # Delivery partner dashboard
│
├── Public Pages/
│   ├── home.php               # Alternate landing (optional)
│   ├── about.php              
│   ├── contact.php            
│   ├── privacy-policy.php     
│   ├── terms.php              
│   ├── refund-policy.php      
│   ├── public_header.php      # Shared nav for public pages
│   └── public_footer.php      # Shared footer + contact message modal
│
├── assets/css/                # Stylesheets
│   ├── landing.css            # Landing page styles
│   └── style.css              # Dashboard styles
│
├── uploads/                   # User-uploaded files (return photos, product images)
└── vendor/                    # Composer dependencies (DomPDF, Razorpay SDK)
```

---

## ⚙️ Tech Stack

| Layer | Technology |
|---|---|
| **Backend** | PHP 8.x (vanilla, no framework) |
| **Database** | MySQL 8.x (PDO with prepared statements) |
| **Auth** | Firebase Authentication (Phone OTP + Google OAuth) |
| **Payments** | Razorpay (checkout.js + server-side verification) |
| **Push Notifications** | Firebase Cloud Messaging v1 API |
| **PDF Generation** | DomPDF |
| **Frontend** | Tailwind CSS (CDN), Material Icons, GSAP animations |
| **Charts** | Chart.js |
| **Server** | Apache (XAMPP for dev, Nginx for production) |

---

## 🚀 Getting Started

### Prerequisites
- PHP 8.0+
- MySQL 8.0+
- Composer
- XAMPP / WAMP / LAMP / Nginx
- Firebase project with Phone Auth & Google Sign-In enabled
- Razorpay account (test or live keys)

### Installation

1. **Clone the repo**
   ```bash
   git clone https://github.com/vikram9726/DigiWash.git
   cd DigiWash
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Create the database**
   ```bash
   mysql -u root < schema.sql
   ```

4. **Set up the contact messages table**
   Open `http://localhost/dashboard/DigiWash/setup_contact.php` in your browser (one-time setup).

5. **Configure environment variables**
   Copy `.env.example` to `.env` and fill in your credentials:
   ```env
   DB_HOST=127.0.0.1
   DB_NAME=digiwash
   DB_USER=root
   DB_PASS=

   FIREBASE_API_KEY=your_key
   FIREBASE_AUTH_DOMAIN=your_project.firebaseapp.com
   FIREBASE_PROJECT_ID=your_project
   FIREBASE_STORAGE_BUCKET=your_project.appspot.com
   FIREBASE_MESSAGING_SENDER_ID=your_sender_id
   FIREBASE_APP_ID=your_app_id
   FIREBASE_SERVICE_ACCOUNT_JSON=your_service_account.json
   FIREBASE_VAPID_KEY=your_vapid_key

   RAZORPAY_KEY_ID=rzp_test_xxx
   RAZORPAY_KEY_SECRET=your_secret
   ```

6. **Add Firebase service account JSON** to the project root directory.

7. **Start the server**
   - XAMPP: Place in `htdocs/dashboard/DigiWash/`
   - Navigate to `http://localhost/dashboard/DigiWash/`

---

## 🔒 Security

- **Content Security Policy (CSP)** headers whitelisting only required domains
- **X-Frame-Options: DENY**, **X-Content-Type-Options: nosniff**, **XSS-Protection**
- **CSRF tokens** on all admin API endpoints
- **Prepared statements** (PDO) for all database queries — no raw SQL injection vectors
- **Session fixation** protection with `httponly` and `secure` cookie flags
- **Role-based access control** on every API endpoint and dashboard page
- **Firebase ID token verification** on the server side for authentication

---

## 📊 Database Tables

| Table | Purpose |
|---|---|
| `users` | All user accounts (customers, delivery, admin) |
| `orders` | Laundry order records with status tracking |
| `payments` | Payment records (Razorpay, COD, Pay Later) |
| `returns` | Return requests with photo evidence |
| `markets` | Geographic service zones |
| `coupons` | Discount codes with usage tracking |
| `notifications` | Push notification log |
| `contact_messages` | Public contact form submissions (Name, Phone, Message, Status) |

---

## 📋 Compliance

This project includes all pages required for:
- ✅ **Google OAuth Consent Screen** verification (Privacy Policy, Terms of Service)
- ✅ **Razorpay Payment Gateway** approval (Refund Policy, Contact details, business information)

---

## 👨‍💻 Author

**Vikram** — Full-stack developer  
📞 +91 9726232915  
🌐 [digiwash.in](https://digiwash.in)

---

## 📄 License

This project is proprietary software. All rights reserved.