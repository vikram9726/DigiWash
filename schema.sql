CREATE DATABASE IF NOT EXISTS digiwash;
USE digiwash;

-- Markets / Service Areas
CREATE TABLE IF NOT EXISTS markets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    lat DECIMAL(10, 8) NOT NULL,
    lng DECIMAL(11, 8) NOT NULL,
    radius_km DECIMAL(5, 2) NOT NULL DEFAULT 5.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Users table handling Customers, Delivery Staff, and Admins
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(15) UNIQUE NOT NULL,
    firebase_uid VARCHAR(128) UNIQUE DEFAULT NULL,
    role ENUM('admin', 'delivery', 'customer') DEFAULT 'customer',
    name VARCHAR(100),
    shop_address TEXT,
    email VARCHAR(100),
    alt_contact VARCHAR(15),
    market_id INT DEFAULT NULL,
    lat DECIMAL(10, 8) DEFAULT NULL,
    lng DECIMAL(11, 8) DEFAULT NULL,
    fcm_token TEXT,
    qr_code_hash VARCHAR(128),
    dummy_otp VARCHAR(6),
    current_orders INT NOT NULL DEFAULT 0,
    is_online TINYINT(1) NOT NULL DEFAULT 1,
    pay_later_plan ENUM('NONE', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12') DEFAULT 'NONE',
    pay_later_status ENUM('locked', 'pending_approval', 'approved', 'declined') DEFAULT 'locked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (market_id) REFERENCES markets(id),
    INDEX (role)
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    market_id INT DEFAULT NULL,
    delivery_id INT DEFAULT NULL,
    status ENUM('pending', 'assigned', 'picked_up', 'in_process', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('remaining', 'completed') DEFAULT 'remaining',
    instructions TEXT,
    cancellation_reason TEXT,
    delivery_otp VARCHAR(6),
    bypass_photo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (delivery_id) REFERENCES users(id),
    INDEX (status),
    INDEX (user_id),
    INDEX (delivery_id)
);

-- Payments tracking table (Crucial for the 4-order delayed payment logic)
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    payment_mode ENUM('COD', 'ONLINE', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12') DEFAULT 'COD',
    status ENUM('remaining', 'completed') DEFAULT 'remaining',
    amount DECIMAL(10, 2) NOT NULL,
    rzp_order_id VARCHAR(100) DEFAULT NULL,
    rzp_payment_id VARCHAR(100) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id),
    INDEX (status)
);

-- Returns table
CREATE TABLE IF NOT EXISTS returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    photo_url VARCHAR(255) NOT NULL,
    reason TEXT,
    admin_status ENUM('pending', 'approved', 'declined') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Coupons table
CREATE TABLE IF NOT EXISTS coupons (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    discount_type ENUM('percentage', 'flat') NOT NULL,
    discount_value DECIMAL(10, 2) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Notifications table
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX (user_id)
);

-- Default Admin User (Password is typically not needed if using strict phone/OTP login, but let's keep it simple with phone login for now)
INSERT INTO users (phone, role, name) VALUES ('9726232915', 'admin', 'System Admin') ON DUPLICATE KEY UPDATE name='System Admin';
