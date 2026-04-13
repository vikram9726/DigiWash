-- ════════════════════════════════════════════════════════════
-- DigiWash — Production Schema (Hostinger Deployment)
-- Extracted from live XAMPP DB: 2026-04-12
-- MariaDB 10.4 / MySQL 8.0 compatible
-- Import via: phpMyAdmin > Import, or mysql CLI
-- ════════════════════════════════════════════════════════════

-- Note: Database creation and USE statements removed for shared hosting compatibility

SET FOREIGN_KEY_CHECKS = 0;

-- ── markets ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `markets` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `lat`        DECIMAL(10,8) NOT NULL,
  `lng`        DECIMAL(11,8) NOT NULL,
  `radius_km`  DECIMAL(5,2) NOT NULL DEFAULT 5.00,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── users ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
  `id`                   INT(11) NOT NULL AUTO_INCREMENT,
  `phone`                VARCHAR(15) NOT NULL,
  `firebase_uid`         VARCHAR(128) DEFAULT NULL,
  `role`                 ENUM('admin','delivery','customer') DEFAULT 'customer',
  `name`                 VARCHAR(100) DEFAULT NULL,
  `shop_address`         TEXT DEFAULT NULL,
  `email`                VARCHAR(100) DEFAULT NULL,
  `alt_contact`          VARCHAR(15) DEFAULT NULL,
  `market_id`            INT(11) DEFAULT NULL,
  `lat`                  DECIMAL(10,8) DEFAULT NULL,
  `lng`                  DECIMAL(11,8) DEFAULT NULL,
  `fcm_token`            TEXT DEFAULT NULL,
  `qr_code_hash`         VARCHAR(128) DEFAULT NULL,
  `dummy_otp`            VARCHAR(6) DEFAULT NULL,
  `current_orders`       INT(11) NOT NULL DEFAULT 0,
  `is_online`            TINYINT(1) NOT NULL DEFAULT 1,
  `is_blocked`           TINYINT(1) DEFAULT 0,
  `pay_later_plan`       ENUM('NONE','PAY_LATER_4','PAY_LATER_8','PAY_LATER_12') DEFAULT 'NONE',
  `pay_later_status`     ENUM('locked','pending_approval','approved','declined') DEFAULT 'locked',
  `auto_order_frequency` ENUM('NONE','7_DAYS','14_DAYS','MONDAYS') DEFAULT 'NONE',
  `auto_order_next_date` DATE DEFAULT NULL,
  `created_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`),
  UNIQUE KEY `firebase_uid` (`firebase_uid`),
  KEY `role` (`role`),
  KEY `idx_auto_order` (`auto_order_next_date`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`market_id`) REFERENCES `markets` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Default admin user
INSERT INTO `users` (`phone`, `role`, `name`)
VALUES ('9726232915', 'admin', 'System Admin')
ON DUPLICATE KEY UPDATE `name` = 'System Admin';

-- ── otp_attempts (rate limiting) ──────────────────────────
-- IMPORTANT: This table was MISSING from original schema but is
-- referenced in api/auth.php for OTP rate limiting (5/10min rule).
CREATE TABLE IF NOT EXISTS `otp_attempts` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `phone`      VARCHAR(15) NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_phone_time` (`phone`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── products ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `name`        VARCHAR(150) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `image_url`   VARCHAR(255) DEFAULT NULL,
  `is_active`   TINYINT(1) DEFAULT 1,
  `sort_order`  INT(11) DEFAULT 0,
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── product_prices ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `product_prices` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `product_id` INT(11) NOT NULL,
  `size_label` VARCHAR(50) NOT NULL COMMENT 'e.g. Small, Medium, Large, Per Kg',
  `price`      DECIMAL(10,2) NOT NULL,
  `unit`       VARCHAR(30) DEFAULT 'per piece' COMMENT 'per piece, per kg, per set',
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `product_prices_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── coupons ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupons` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `code`             VARCHAR(50) NOT NULL,
  `discount_type`    ENUM('percentage','flat') NOT NULL,
  `discount_value`   DECIMAL(10,2) NOT NULL,
  `is_active`        TINYINT(1) DEFAULT 1,
  `usage_limit`      INT(11) DEFAULT NULL COMMENT 'Max total uses (NULL = unlimited)',
  `per_user_limit`   INT(11) DEFAULT 1 COMMENT 'Max uses per user',
  `min_order_amount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Minimum order amount to apply',
  `expires_at`       DATETIME DEFAULT NULL,
  `created_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── invoices ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `invoices` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11) NOT NULL,
  `invoice_no`     VARCHAR(50) DEFAULT NULL,
  `description`    VARCHAR(255) NOT NULL,
  `amount`         DECIMAL(10,2) NOT NULL,
  `tax`            DECIMAL(10,2) DEFAULT 0.00,
  `status`         ENUM('unpaid','paid') DEFAULT 'unpaid',
  `rzp_order_id`   VARCHAR(100) DEFAULT NULL,
  `rzp_payment_id` VARCHAR(100) DEFAULT NULL,
  `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `invoices_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── orders ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `orders` (
  `id`                  INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`             INT(11) NOT NULL,
  `market_id`           INT(11) DEFAULT NULL,
  `delivery_id`         INT(11) DEFAULT NULL,
  `invoice_id`          INT(11) DEFAULT NULL,
  `lat`                 DECIMAL(10,8) DEFAULT NULL,
  `lng`                 DECIMAL(11,8) DEFAULT NULL,
  `pickup_address`      TEXT DEFAULT NULL,
  `status`              ENUM('pending','assigned','picked_up','in_process','out_for_delivery','delivered','cancelled') DEFAULT 'pending',
  `total_amount`        DECIMAL(10,2) DEFAULT 0.00,
  `payment_status`      ENUM('remaining','completed') DEFAULT 'remaining',
  `instructions`        TEXT DEFAULT NULL,
  `cancellation_reason` TEXT DEFAULT NULL,
  `delivery_otp`        VARCHAR(6) DEFAULT NULL,
  `bypass_photo_url`    VARCHAR(255) DEFAULT NULL,
  `picked_up_at`        TIMESTAMP NULL DEFAULT NULL,
  `delivered_at`        TIMESTAMP NULL DEFAULT NULL,
  `created_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `status` (`status`),
  KEY `user_id` (`user_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_ibfk_2` FOREIGN KEY (`delivery_id`) REFERENCES `users` (`id`),
  CONSTRAINT `orders_ibfk_3` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── order_items ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `order_items` (
  `id`               INT(11) NOT NULL AUTO_INCREMENT,
  `order_id`         INT(11) NOT NULL,
  `product_id`       INT(11) NOT NULL,
  `product_price_id` INT(11) NOT NULL,
  `product_name`     VARCHAR(150) NOT NULL,
  `size_label`       VARCHAR(50) NOT NULL,
  `price`            DECIMAL(10,2) NOT NULL,
  `quantity`         INT(11) NOT NULL DEFAULT 1,
  `line_total`       DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── payments ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `payments` (
  `id`             INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`        INT(11) NOT NULL,
  `order_id`       INT(11) NOT NULL,
  `payment_mode`   ENUM('COD','ONLINE','PAY_LATER_4','PAY_LATER_8','PAY_LATER_12') DEFAULT 'COD',
  `status`         ENUM('remaining','completed') DEFAULT 'remaining',
  `amount`         DECIMAL(10,2) NOT NULL,
  `rzp_order_id`   VARCHAR(100) DEFAULT NULL,
  `rzp_payment_id` VARCHAR(100) DEFAULT NULL,
  `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `order_id` (`order_id`),
  KEY `status` (`status`),
  CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── coupon_usages ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `coupon_usages` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `coupon_id`       INT(11) NOT NULL,
  `user_id`         INT(11) NOT NULL,
  `order_id`        INT(11) NOT NULL,
  `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `used_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `coupon_id` (`coupon_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `coupon_usages_ibfk_1` FOREIGN KEY (`coupon_id`) REFERENCES `coupons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `coupon_usages_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `coupon_usages_ibfk_3` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── returns ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `returns` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `order_id`     INT(11) NOT NULL,
  `user_id`      INT(11) NOT NULL,
  `photo_url`    VARCHAR(255) NOT NULL,
  `reason`       TEXT DEFAULT NULL,
  `admin_status` ENUM('pending','approved','declined') DEFAULT 'pending',
  `created_at`   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `returns_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`),
  CONSTRAINT `returns_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── notifications ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`    INT(11) NOT NULL,
  `title`      VARCHAR(255) NOT NULL,
  `message`    TEXT NOT NULL,
  `is_read`    TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── staff_requests ───────────────────────────────────────
CREATE TABLE IF NOT EXISTS `staff_requests` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`     INT(11) NOT NULL,
  `delivery_id` INT(11) DEFAULT NULL,
  `message`     TEXT DEFAULT NULL,
  `admin_note`  TEXT DEFAULT NULL,
  `status`      ENUM('pending','seen','resolved') DEFAULT 'pending',
  `created_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `delivery_id` (`delivery_id`),
  CONSTRAINT `staff_requests_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `staff_requests_ibfk_2` FOREIGN KEY (`delivery_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── contact_messages ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `phone`      VARCHAR(15) NOT NULL,
  `message`    TEXT NOT NULL,
  `status`     ENUM('new','read','resolved') DEFAULT 'new',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── user_wallet ──────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `user_wallet` (
  `user_id`      INT(11) NOT NULL,
  `credit_limit` DECIMAL(10,2) DEFAULT 2000.00,
  `used_credit`  DECIMAL(10,2) DEFAULT 0.00,
  PRIMARY KEY (`user_id`),
  CONSTRAINT `user_wallet_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── marketplace_products ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `marketplace_products` (
  `id`         INT(11) NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(255) NOT NULL,
  `category`   VARCHAR(100) NOT NULL,
  `size`       VARCHAR(50) NOT NULL,
  `price`      DECIMAL(10,2) NOT NULL,
  `image`      VARCHAR(255) DEFAULT NULL,
  `stock`      INT(11) NOT NULL DEFAULT 0,
  `status`     ENUM('active','inactive') DEFAULT 'active',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── marketplace_product_widths ───────────────────────────
CREATE TABLE IF NOT EXISTS `marketplace_product_widths` (
  `id`              INT(11) NOT NULL AUTO_INCREMENT,
  `product_id`      INT(11) NOT NULL,
  `label`           VARCHAR(100) NOT NULL,
  `price_per_meter` DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `marketplace_product_widths_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `marketplace_products` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── marketplace_orders ───────────────────────────────────
CREATE TABLE IF NOT EXISTS `marketplace_orders` (
  `id`                 INT(11) NOT NULL AUTO_INCREMENT,
  `user_id`            INT(11) NOT NULL,
  `delivery_id`        INT(11) DEFAULT NULL,
  `total_amount`       DECIMAL(10,2) NOT NULL,
  `payment_type`       ENUM('online','credit','COD') DEFAULT 'online',
  `payment_status`     ENUM('pending','paid') DEFAULT 'pending',
  `status`             ENUM('placed','assigned','picked_up','out_for_delivery','delivered','cancelled') DEFAULT 'placed',
  `razorpay_order_id`  VARCHAR(100) DEFAULT NULL,
  `razorpay_payment_id`VARCHAR(100) DEFAULT NULL,
  `invoice_no`         VARCHAR(30) DEFAULT NULL,
  `picked_up_at`       TIMESTAMP NULL DEFAULT NULL,
  `delivered_at`       TIMESTAMP NULL DEFAULT NULL,
  `cancelled_at`       TIMESTAMP NULL DEFAULT NULL,
  `created_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `delivery_id` (`delivery_id`),
  KEY `idx_user_status` (`user_id`, `status`),
  CONSTRAINT `marketplace_orders_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `marketplace_orders_ibfk_2` FOREIGN KEY (`delivery_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ── marketplace_order_items ──────────────────────────────
CREATE TABLE IF NOT EXISTS `marketplace_order_items` (
  `id`           INT(11) NOT NULL AUTO_INCREMENT,
  `order_id`     INT(11) NOT NULL,
  `product_id`   INT(11) NOT NULL,
  `quantity`     INT(11) NOT NULL,
  `price`        DECIMAL(10,2) NOT NULL,
  `length_meters`DECIMAL(6,2) DEFAULT NULL,
  `width_label`  VARCHAR(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`),
  CONSTRAINT `marketplace_order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `marketplace_orders` (`id`) ON DELETE CASCADE,
  CONSTRAINT `marketplace_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `marketplace_products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;