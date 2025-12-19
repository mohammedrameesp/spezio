<?php
/**
 * Spezio Apartments Booking System
 * API: Validate Coupon
 *
 * POST /api/validate-coupon.php
 * Body: { "code": "WELCOME10", "room_id": 1, "subtotal": 7500, "nights": 3, "csrf_token": "..." }
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

setCorsHeaders();
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting: 30 coupon validations per minute per IP
rateLimit('validate_coupon', 30, 60);

// CSRF protection
requireCSRF();

try {
    $data = getJsonInput();

    // Validate required fields
    $required = ['code', 'room_id', 'subtotal', 'nights'];
    $missing = validateRequired($data, $required);
    if (!empty($missing)) {
        jsonError('Missing required fields: ' . implode(', ', $missing));
    }

    $code = trim($data['code']);
    $roomId = (int) $data['room_id'];
    $subtotal = (float) $data['subtotal'];
    $nights = (int) $data['nights'];

    if (empty($code)) {
        jsonError('Please enter a coupon code');
    }

    // Validate coupon
    $result = validateCoupon($code, $roomId, $subtotal, $nights);

    if ($result['valid']) {
        jsonSuccess([
            'valid' => true,
            'coupon_id' => $result['coupon_id'],
            'coupon_code' => $result['coupon_code'],
            'discount_type' => $result['discount_type'],
            'discount_value' => $result['discount_value'],
            'discount_amount' => $result['discount_amount'],
            'discount_text' => $result['discount_text'],
            'description' => $result['description'],
            'new_total' => round($subtotal - $result['discount_amount'], 2)
        ], 'Coupon applied successfully');
    } else {
        jsonSuccess([
            'valid' => false,
            'error' => $result['error']
        ], $result['error']);
    }

} catch (Exception $e) {
    error_log("Validate coupon error: " . $e->getMessage());
    jsonError('Failed to validate coupon', 500);
}
