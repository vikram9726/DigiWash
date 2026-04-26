# DigiWash — Code Audit Report
> Conducted: April 2026 | Auditor: Antigravity AI

---

## Summary

| Category | Status | Priority |
|---|---|---|
| Debug files in production | ✅ Fixed | Critical |
| `.gitignore` coverage | ✅ Fixed | High |
| CSP headers (reCAPTCHA remnants) | ✅ Fixed | High |
| Bot protection (Turnstile migration) | ✅ Fixed | High |
| OTP rate limiting | ✅ Fixed | High |
| Firebase SDK duplication | ⚠️ Note | Medium |
| Inline JS in PHP files | ⚠️ Known | Medium |
| Dead code / temp files | ✅ Fixed | Medium |
| Documentation completeness | ✅ Fixed | Medium |
| `.env` key coverage | ✅ Fixed | Medium |

---

## 🔴 Critical Issues (Fixed)

### 1. `temp_debug.php` — Raw SQL query exposed publicly
**Risk:** Any visitor could access `http://domain.com/temp_debug.php` and see order/payment/refund data for order #13.
**Fix:** File deleted from codebase and pattern added to `.gitignore`.

### 2. Google reCAPTCHA → Cloudflare Turnstile Migration
**Risk:** The old `RecaptchaVerifier` was visible to users and could be bypassed; no server-side OTP rate limiting existed.
**Fix:** Replaced with Cloudflare Turnstile + `api/verify_turnstile.php` with:
- Cloudflare API verification on every OTP send
- 3 OTP requests/phone/10 minutes
- 5 OTP requests/IP/15 minutes
- `otp_requests` DB table for tracking

---

## 🟡 Medium Issues (Recommendations)

### 3. All JS is Inline in PHP Files
**Impact:** No browser caching; harder to lint/test JavaScript independently.
**Recommendation:** Extract repeated JS utilities (e.g. `showToast()`, `api()` fetch wrapper, `formatCurrency()`) into `assets/js/utils.js`. Dashboard-specific JS can remain inline for now due to PHP variable interpolation requirements.

### 4. Firebase SDK Loaded Twice in `user/verify_phone.php`
**Impact:** Minor — both `public_header.php` (for public pages) and `verify_phone.php` (standalone page) load Firebase independently. Not a bug, but worth noting.
**Recommendation:** `verify_phone.php` loads its own scripts (it doesn't use `public_header.php`), so this is correct by design. No action needed.

### 5. `admin/dashboard.php` and `user/dashboard.php` are Very Large Files
**Size:** `admin/dashboard.php` ~117KB, `user/dashboard.php` ~116KB
**Impact:** Hard to maintain; slow IDE parsing. These files combine PHP data-fetching, HTML structure, CSS, and JS in one file.
**Recommendation (Future):** Extract to a modular structure:
```
admin/
├── dashboard.php          # Entry point (session check + includes)
├── partials/
│   ├── orders_tab.php
│   ├── users_tab.php
│   └── markets_tab.php
└── js/
    └── admin_dashboard.js
```

### 6. No `assets/js/` Directory — All JS is Inline
**Recommendation:** Create at minimum:
- `assets/js/utils.js` — `showToast()`, `formatCurrency()`, `api()` fetch wrapper
- `assets/js/notifications.js` — FCM push notification setup

### 7. `schema.sql` + `migration_v2.sql` Are Separate
**Impact:** Developers must remember to run both on fresh install.
**Recommendation:** Either merge `migration_v2.sql` into `schema.sql`, or create an `install.sql` that runs both.

### 8. `digiwash-9c738-firebase-adminsdk-*.json` in Project Root
**Risk:** Firebase service account private key committed to repository (if `.gitignore` was ever misconfigured).
**Fix in `.gitignore`:** Pattern `*firebase-adminsdk*.json` added.
**Recommended:** Move this file outside `public_html/` on Hostinger and reference via `FIREBASE_SA_PATH` env var.

### 9. `repomix-output.xml` (767KB) Was Untracked
**Impact:** 767KB XML blob not gitignored, could be accidentally committed.
**Fix:** Added to `.gitignore`.

---

## 🟢 What's Already Well Done

| Area | Notes |
|---|---|
| SQL Injection prevention | 100% PDO prepared statements across all `api/` files |
| CSRF protection | Implemented on all state-changing endpoints |
| XSS prevention | `htmlspecialchars()` consistently applied on output |
| Razorpay webhook | HMAC-SHA256 signature verified before processing |
| Session security | HTTPOnly + SameSite cookies; CSRF token regenerated on login |
| Role-based access | All dashboards check `$_SESSION['role']` at the top |
| Environment variables | All secrets in `.env`, not hardcoded |
| CSP headers | Properly configured (updated for Turnstile) |
| Firebase token verification | ID tokens verified via Google Identity Toolkit API |
| FCM push notifications | Properly implemented with VAPID key |
| Refund ARN tracking | ARN stored and trackable by customer |
| Error logging | `error_log()` used instead of exposing raw errors |

---

## 📦 Changes Made During This Audit

| File | Change |
|---|---|
| `temp_debug.php` | **Deleted** — raw debug query, production security risk |
| `.gitignore` | Updated — blocks debug files, repomix output, Firebase admin JSON |
| `config.php` | CSP updated for Cloudflare Turnstile; added `getTurnstileSiteKey()` helper |
| `public_header.php` | Added Cloudflare Turnstile SDK script tag |
| `public_footer.php` | Full Turnstile integration: widget, disabled button until verified, Turnstile pre-check before Firebase OTP, resend timer, removed `grecaptcha.reset()` |
| `user/verify_phone.php` | Full rewrite: Turnstile widget, 2-step (Turnstile→Firebase), resend cooldown |
| `api/verify_turnstile.php` | **New file** — Turnstile API verification + DB rate limiting |
| `schema.sql` | Appended `market_requests` and `otp_requests` tables, eliminating need for `migration_v2.sql` |
| `assets/js/utils.js` | **New file** — Extracted shared JS helpers (`showToast`, `formatCurrency`, `api`) |
| `.env` | Added `CF_TURNSTILE_SITE_KEY` and `CF_TURNSTILE_SECRET` |
| `DEVELOPER_DOCS.md` | **New** — complete developer documentation |

---

## 🚀 Recommended Next Steps (For Production Host)

1. **Deploy `schema.sql` to Hostinger** — it now contains all required tables, including rate-limiting tables.
2. **Get production Cloudflare Turnstile keys** — replace test keys in `.env` on Hostinger.
3. **Move Firebase Admin SDK JSON outside `public_html`** on Hostinger and update `.env` `FIREBASE_SA_PATH`.
4. **Consider splitting admin/user dashboards** into tab partials for maintainability in future releases.

---

## 🗂️ Recommended Future Folder Structure (For Scaling)

```
DigiWash/
├── config/
│   └── config.php                # Move from root
├── app/
│   ├── helpers/
│   │   ├── Auth.php              # Auth checks, role validation
│   │   ├── Response.php          # Standardized JSON response helpers
│   │   ├── RazorpayHelper.php    # Razorpay API wrapper
│   │   └── FirebaseHelper.php    # Firebase Admin SDK wrapper
│   └── middleware/
│       └── CsrfMiddleware.php    # CSRF validation
├── api/                          # (keep as-is, refactor controllers over time)
├── views/                        # Extract PHP HTML templates
│   ├── admin/
│   ├── user/
│   └── delivery/
└── assets/
    ├── css/
    └── js/
        ├── utils.js              # Shared: showToast, api(), formatCurrency
        ├── notifications.js      # FCM setup
        └── dashboard.js          # Shared dashboard JS
```
