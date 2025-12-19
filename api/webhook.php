<?php
/**
 * Spezio Apartments Booking System
 * API: Razorpay Webhook Handler
 *
 * POST /api/webhook.php
 *
 * Configure this URL in Razorpay Dashboard:
 * Settings > Webhooks > Add New Webhook
 * Events: payment.captured, payment.failed, refund.created
 */

require_once __DIR__ . '/functions.php';

// Don't set CORS headers for webhooks
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

try {
    // Get raw POST data
    $payload = file_get_contents('php://input');

    // Verify webhook signature
    $webhookSignature = $_SERVER['HTTP_X_RAZORPAY_SIGNATURE'] ?? '';

    if (empty($webhookSignature)) {
        error_log("Webhook: Missing signature");
        http_response_code(400);
        echo json_encode(['error' => 'Missing signature']);
        exit;
    }

    $expectedSignature = hash_hmac('sha256', $payload, RAZORPAY_WEBHOOK_SECRET);

    if (!hash_equals($expectedSignature, $webhookSignature)) {
        error_log("Webhook: Invalid signature");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid signature']);
        exit;
    }

    // Parse payload
    $event = json_decode($payload, true);

    if (!$event || !isset($event['event'])) {
        error_log("Webhook: Invalid payload");
        http_response_code(400);
        echo json_encode(['error' => 'Invalid payload']);
        exit;
    }

    $eventType = $event['event'];
    $paymentEntity = $event['payload']['payment']['entity'] ?? null;

    error_log("Webhook received: " . $eventType);

    switch ($eventType) {
        case 'payment.captured':
            handlePaymentCaptured($paymentEntity);
            break;

        case 'payment.failed':
            handlePaymentFailed($paymentEntity);
            break;

        case 'refund.created':
            $refundEntity = $event['payload']['refund']['entity'] ?? null;
            handleRefundCreated($refundEntity);
            break;

        default:
            // Log unhandled events but return success
            error_log("Webhook: Unhandled event type: " . $eventType);
    }

    // Return success
    http_response_code(200);
    echo json_encode(['status' => 'ok']);

} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}

/**
 * Handle payment.captured event
 */
function handlePaymentCaptured($payment) {
    if (!$payment || !isset($payment['order_id'])) {
        error_log("Webhook payment.captured: Invalid payment data");
        return;
    }

    $orderId = $payment['order_id'];
    $paymentId = $payment['id'];

    // Find booking by order ID
    $booking = dbFetchOne(
        "SELECT * FROM bookings WHERE razorpay_order_id = ?",
        [$orderId]
    );

    if (!$booking) {
        error_log("Webhook payment.captured: Booking not found for order " . $orderId);
        return;
    }

    // Skip if already paid
    if ($booking['payment_status'] === 'paid') {
        error_log("Webhook payment.captured: Booking already marked as paid - " . $booking['booking_id']);
        return;
    }

    // Update booking status
    dbUpdate(
        'bookings',
        [
            'razorpay_payment_id' => $paymentId,
            'payment_status' => 'paid',
            'booking_status' => 'confirmed'
        ],
        'id = ?',
        ['id' => $booking['id']]
    );

    // Increment coupon usage if applicable
    if ($booking['coupon_id']) {
        incrementCouponUsage($booking['coupon_id']);
    }

    error_log("Webhook payment.captured: Booking confirmed - " . $booking['booking_id']);

    // Send confirmation email
    try {
        require_once __DIR__ . '/send-email.php';
        $updatedBooking = getBookingByBookingId($booking['booking_id']);
        sendBookingConfirmationEmail($updatedBooking);
        sendAdminNotificationEmail($updatedBooking);
    } catch (Exception $e) {
        error_log("Webhook: Email sending failed - " . $e->getMessage());
    }
}

/**
 * Handle payment.failed event
 */
function handlePaymentFailed($payment) {
    if (!$payment || !isset($payment['order_id'])) {
        error_log("Webhook payment.failed: Invalid payment data");
        return;
    }

    $orderId = $payment['order_id'];
    $paymentId = $payment['id'];
    $errorCode = $payment['error_code'] ?? 'unknown';
    $errorDescription = $payment['error_description'] ?? 'Payment failed';

    // Find booking by order ID
    $booking = dbFetchOne(
        "SELECT * FROM bookings WHERE razorpay_order_id = ?",
        [$orderId]
    );

    if (!$booking) {
        error_log("Webhook payment.failed: Booking not found for order " . $orderId);
        return;
    }

    // Update booking status
    dbUpdate(
        'bookings',
        [
            'razorpay_payment_id' => $paymentId,
            'payment_status' => 'failed',
            'admin_notes' => "Payment failed: {$errorCode} - {$errorDescription}"
        ],
        'id = ?',
        ['id' => $booking['id']]
    );

    error_log("Webhook payment.failed: Booking payment failed - " . $booking['booking_id'] . " - " . $errorDescription);
}

/**
 * Handle refund.created event
 */
function handleRefundCreated($refund) {
    if (!$refund || !isset($refund['payment_id'])) {
        error_log("Webhook refund.created: Invalid refund data");
        return;
    }

    $paymentId = $refund['payment_id'];
    $refundId = $refund['id'];
    $refundAmount = $refund['amount'] / 100; // Convert from paise

    // Find booking by payment ID
    $booking = dbFetchOne(
        "SELECT * FROM bookings WHERE razorpay_payment_id = ?",
        [$paymentId]
    );

    if (!$booking) {
        error_log("Webhook refund.created: Booking not found for payment " . $paymentId);
        return;
    }

    // Update booking status
    dbUpdate(
        'bookings',
        [
            'payment_status' => 'refunded',
            'booking_status' => 'cancelled',
            'admin_notes' => ($booking['admin_notes'] ? $booking['admin_notes'] . "\n" : '') .
                "Refund processed: {$refundId} - Amount: " . CURRENCY_SYMBOL . number_format($refundAmount, 2)
        ],
        'id = ?',
        ['id' => $booking['id']]
    );

    error_log("Webhook refund.created: Booking refunded - " . $booking['booking_id']);
}
