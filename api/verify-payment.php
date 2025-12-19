<?php
/**
 * Spezio Apartments Booking System
 * API: Verify Payment
 *
 * POST /api/verify-payment.php
 * Body: {
 *   "razorpay_order_id": "order_xxx",
 *   "razorpay_payment_id": "pay_xxx",
 *   "razorpay_signature": "xxx",
 *   "booking_id": "SPZ-20250115-001",
 *   "csrf_token": "..."
 * }
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

setCorsHeaders();
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting: 20 verifications per minute per IP
rateLimit('verify_payment', 20, 60);

// CSRF protection
requireCSRF();

try {
    $data = getJsonInput();

    // Validate required fields
    $required = ['razorpay_order_id', 'razorpay_payment_id', 'razorpay_signature', 'booking_id'];
    $missing = validateRequired($data, $required);
    if (!empty($missing)) {
        jsonError('Missing required fields: ' . implode(', ', $missing));
    }

    $orderId = $data['razorpay_order_id'];
    $paymentId = $data['razorpay_payment_id'];
    $signature = $data['razorpay_signature'];
    $bookingId = $data['booking_id'];

    // Verify signature
    $expectedSignature = hash_hmac(
        'sha256',
        $orderId . '|' . $paymentId,
        RAZORPAY_KEY_SECRET
    );

    if (!hash_equals($expectedSignature, $signature)) {
        error_log("Payment verification failed - signature mismatch for booking: " . $bookingId);
        jsonError('Payment verification failed. Please contact support.', 400);
    }

    // Get booking
    $booking = getBookingByBookingId($bookingId);
    if (!$booking) {
        jsonError('Booking not found', 404);
    }

    // Verify order ID matches
    if ($booking['razorpay_order_id'] !== $orderId) {
        error_log("Order ID mismatch for booking: " . $bookingId);
        jsonError('Payment verification failed. Please contact support.', 400);
    }

    // Update booking status
    dbBeginTransaction();

    try {
        updateBookingPayment($bookingId, $paymentId, $signature, 'paid');

        // Increment coupon usage if applicable
        if ($booking['coupon_id']) {
            incrementCouponUsage($booking['coupon_id']);
        }

        dbCommit();

        // Fetch updated booking
        $booking = getBookingByBookingId($bookingId);

        // Send confirmation email (async - don't fail if email fails)
        try {
            require_once __DIR__ . '/send-email.php';
            sendBookingConfirmationEmail($booking);
            sendAdminNotificationEmail($booking);
        } catch (Exception $emailError) {
            error_log("Email sending failed for booking {$bookingId}: " . $emailError->getMessage());
        }

        jsonSuccess([
            'booking_id' => $bookingId,
            'payment_id' => $paymentId,
            'status' => 'confirmed',
            'booking' => [
                'room_name' => $booking['room_name'],
                'check_in' => $booking['check_in'],
                'check_out' => $booking['check_out'],
                'guest_name' => $booking['guest_name'],
                'guest_email' => $booking['guest_email'],
                'total_amount' => $booking['total_amount'],
                'total_nights' => $booking['total_nights']
            ]
        ], 'Payment verified successfully. Your booking is confirmed!');

    } catch (Exception $e) {
        dbRollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Verify payment error: " . $e->getMessage());
    jsonError('Payment verification failed. Please contact support.', 500);
}
