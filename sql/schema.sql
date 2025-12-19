-- Spezio Apartments Booking System
-- Database Schema (Consolidated)
-- Version 2.0

CREATE TABLE IF NOT EXISTS rooms (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    price_daily DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_weekly DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_monthly DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_guests INT NOT NULL DEFAULT 2,
    bedrooms INT NOT NULL DEFAULT 1,
    bathrooms INT NOT NULL DEFAULT 1,
    size_sqft INT DEFAULT NULL,
    amenities JSON,
    images JSON,
    status ENUM("active", "inactive") DEFAULT "active",
    display_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS duration_discounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    min_nights INT NOT NULL,
    discount_percent DECIMAL(5,2) NOT NULL,
    label VARCHAR(50) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_min_nights (min_nights)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupons (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255),
    discount_type ENUM("percentage", "fixed") DEFAULT "percentage",
    discount_value DECIMAL(10,2) NOT NULL,
    min_nights INT DEFAULT 1,
    min_amount DECIMAL(10,2) DEFAULT 0,
    max_discount DECIMAL(10,2) DEFAULT NULL,
    valid_from DATE NOT NULL,
    valid_until DATE NOT NULL,
    usage_limit INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    room_ids JSON,
    status ENUM("active", "inactive") DEFAULT "active",
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id VARCHAR(20) NOT NULL UNIQUE,
    room_id INT NOT NULL,
    guest_name VARCHAR(100) NOT NULL,
    guest_email VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(20) NOT NULL,
    check_in DATE NOT NULL,
    check_out DATE NOT NULL,
    num_guests INT NOT NULL DEFAULT 1,
    num_adults INT NOT NULL DEFAULT 1,
    num_children INT NOT NULL DEFAULT 0,
    extra_bed TINYINT(1) NOT NULL DEFAULT 0,
    extra_bed_charge DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_nights INT NOT NULL,
    pricing_tier ENUM("daily", "weekly", "monthly") NOT NULL,
    rate_per_night DECIMAL(10,2) NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    duration_discount_percent DECIMAL(5,2) DEFAULT 0,
    duration_discount_amount DECIMAL(10,2) DEFAULT 0,
    coupon_id INT DEFAULT NULL,
    coupon_code VARCHAR(50) DEFAULT NULL,
    discount_amount DECIMAL(10,2) DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL,
    payment_status ENUM("pending", "paid", "failed", "refunded") DEFAULT "pending",
    booking_status ENUM("pending", "confirmed", "cancelled", "completed", "no_show") DEFAULT "pending",
    razorpay_order_id VARCHAR(100) DEFAULT NULL,
    razorpay_payment_id VARCHAR(100) DEFAULT NULL,
    razorpay_signature VARCHAR(255) DEFAULT NULL,
    special_requests TEXT,
    admin_notes TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL,
    INDEX idx_booking_id (booking_id),
    INDEX idx_check_in (check_in),
    INDEX idx_payment_status (payment_status),
    INDEX idx_booking_status (booking_status),
    INDEX idx_guest_email (guest_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blocked_dates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    blocked_date DATE NOT NULL,
    reason VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    UNIQUE KEY unique_room_date (room_id, blocked_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100),
    role ENUM("super_admin", "admin", "staff") DEFAULT "admin",
    last_login TIMESTAMP NULL,
    status ENUM("active", "inactive") DEFAULT "active",
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM("text", "number", "boolean", "json") DEFAULT "text",
    description VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default Data
INSERT INTO rooms (name, slug, description, price_daily, price_weekly, price_monthly, max_guests, bedrooms, bathrooms, amenities, images, display_order) VALUES
("1BHK Premium Apartment", "1bhk", "Cozy 1BHK apartment - ideal for couples or solo travelers.", 2300.00, 2000.00, 1500.00, 2, 1, 1, "[]", "[\"images/rooms/spezio-1bhk-apartment-preview.webp\"]", 1),
("2BHK Premium Apartment", "2bhk", "Spacious 2BHK apartment - perfect for families.", 3000.00, 2500.00, 2000.00, 4, 2, 2, "[]", "[\"images/rooms/spezio-2bhk-apartment-preview.webp\"]", 2);

INSERT INTO duration_discounts (min_nights, discount_percent, label) VALUES
(3, 5.00, "3+ Days"), (5, 7.50, "5+ Days"), (7, 10.00, "Weekly"), (15, 35.00, "15+ Days"), (30, 53.00, "Monthly");

INSERT INTO admin_users (username, password_hash, name, email, role) VALUES
("admin", "$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi", "Administrator", "admin@spezioapartments.com", "super_admin");

INSERT INTO settings (setting_key, setting_value, setting_type, description) VALUES
("site_name", "Spezio Apartments", "text", "Website name"),
("check_in_time", "14:00", "text", "Check-in time"),
("check_out_time", "12:00", "text", "Check-out time"),
("extra_bed_price", "600", "number", "Extra bed charge per night");
