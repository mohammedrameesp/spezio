<?php
/**
 * Spezio Apartments Booking System
 * Configuration File
 *
 * IMPORTANT: Update these values with your actual credentials before deployment
 */

// =====================================================
// ENVIRONMENT DETECTION
// =====================================================
// Set to 'production' on live server, 'development' for local testing
// Can also be set via environment variable: SPEZIO_ENV=production
define('APP_ENV', getenv('SPEZIO_ENV') ?: (
    (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false ||
     strpos($_SERVER['HTTP_HOST'] ?? '', '127.0.0.1') !== false)
    ? 'development' : 'production'
));

// =====================================================
// ERROR REPORTING (Environment-based)
// =====================================================
if (APP_ENV === 'production') {
    // Production: Hide errors from users, log them
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', dirname(__DIR__) . '/logs/error.log');
} else {
    // Development: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    ini_set('log_errors', 1);
}

// Timezone
date_default_timezone_set('Asia/Kolkata');

// =====================================================
// DATABASE CONFIGURATION
// =====================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'u657371053_spezio_booking');
define('DB_USER', 'u657371053_spezio_user');
define('DB_PASS', 'Ramees12345');
define('DB_CHARSET', 'utf8mb4');

// =====================================================
// RAZORPAY CONFIGURATION
// =====================================================
define('RAZORPAY_KEY_ID', 'rzp_test_xxxxxxxxxxxxx');     // Update with your key
define('RAZORPAY_KEY_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxx'); // Update with your secret
define('RAZORPAY_WEBHOOK_SECRET', 'xxxxxxxxxxxxxxxxxxxx'); // Update with webhook secret

// =====================================================
// SITE CONFIGURATION
// =====================================================
define('SITE_NAME', 'Spezio Apartments');
define('SITE_URL', 'https://rosybrown-pelican-621447.hostingersite.com');
define('SITE_EMAIL', 'info@spezioapartments.com');
define('BOOKING_EMAIL', 'bookings@spezioapartments.com');
define('SITE_PHONE', '+91 9876543210');
define('WHATSAPP_NUMBER', '919876543210');

// =====================================================
// BOOKING CONFIGURATION
// =====================================================
define('CURRENCY', 'INR');
define('CURRENCY_SYMBOL', '₹');
define('CHECK_IN_TIME', '14:00');
define('CHECK_OUT_TIME', '12:00');
define('MIN_BOOKING_DAYS', 1);
define('MAX_BOOKING_DAYS', 90);
define('ADVANCE_BOOKING_DAYS', 180);
define('TOTAL_ROOMS', 18);  // Total physical rooms (shared pool for 1BHK & 2BHK)

// =====================================================
// PRICING TIERS (nights threshold)
// =====================================================
define('TIER_DAILY_MAX', 6);      // 1-6 nights = daily rate
define('TIER_WEEKLY_MAX', 29);    // 7-29 nights = weekly rate
// 30+ nights = monthly rate

// =====================================================
// SECURITY
// =====================================================
define('SECURE_KEY', 'Sp3z10Apt$2024!xK9mNpQr7vWz8yB4cD');
define('SESSION_LIFETIME', 3600); // 1 hour

// =====================================================
// EMAIL CONFIGURATION (SMTP)
// =====================================================
define('SMTP_ENABLED', false);    // Set to true to use SMTP
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_email@gmail.com');
define('SMTP_PASS', 'your_app_password');
define('SMTP_FROM_NAME', 'Spezio Apartments');

// =====================================================
// PATHS
// =====================================================
define('ROOT_PATH', dirname(__DIR__));
define('API_PATH', __DIR__);
define('ADMIN_PATH', ROOT_PATH . '/admin');

// =====================================================
// CORS HEADERS (for API)
// =====================================================
function setCorsHeaders() {
    header('Access-Control-Allow-Origin: ' . SITE_URL);
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Content-Type: application/json; charset=UTF-8');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

// =====================================================
// JSON RESPONSE HELPER
// =====================================================
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}

function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'error' => $message], $statusCode);
}

function jsonSuccess($data = [], $message = 'Success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}
