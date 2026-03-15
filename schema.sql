CREATE DATABASE IF NOT EXISTS digiwash;
USE digiwash;

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    delivery_id INT DEFAULT NULL,
    status ENUM('pending', 'picked_up', 'in_process', 'out_for_delivery', 'delivered', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10, 2) DEFAULT 0.00,
    payment_status ENUM('remaining', 'completed') DEFAULT 'remaining',
    cancellation_reason TEXT,
    delivery_otp VARCHAR(6),
    bypass_photo_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (delivery_id) REFERENCES users(id)
);

-- Payments tracking table (Crucial for the 4-order delayed payment logic)
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    order_id INT NOT NULL,
    payment_mode ENUM('COD', 'PAY_LATER_4', 'PAY_LATER_8', 'PAY_LATER_12') DEFAULT 'COD',
    status ENUM('remaining', 'completed') DEFAULT 'remaining',
    amount DECIMAL(10, 2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
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

-- Default Admin User (Password is typically not needed if using strict phone/OTP login, but let's keep it simple with phone login for now)
INSERT INTO users (phone, role, name) VALUES ('9999999999', 'admin', 'System Admin') ON DUPLICATE KEY UPDATE name='System Admin';
