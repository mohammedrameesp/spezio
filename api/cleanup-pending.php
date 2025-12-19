<?php
/**
 * Spezio Apartments Booking System
 * API: Cleanup Pending Bookings
 *
 * This script should be run via cron job every 5 minutes:
 * */5 * * * * php /path/to/spezio/api/cleanup-pending.php
 *
 * Or call via webhook: GET /api/cleanup-pending.php?key=YOUR_SECURE_KEY
 */

require_once __DIR__ . '/functions.php';

// Security check - only allow cron or authenticated requests
$isCli = php_sapi_name() === 'cli';
$hasValidKey = isset($_GET['key']) && $_GET['key'] === SECURE_KEY;

if (!$isCli && !$hasValidKey) {
    http_response_code(403);
    echo "Access denied";
    exit;
}

// Configuration
$pendingTimeout = 15; // Minutes before pending booking expires

try {
    // Find pending bookings older than timeout
    $expiredBookings = dbFetchAll(
        "SELECT id, booking_id, guest_email, guest_name
         FROM bookings
         WHERE payment_status = 'pending'
         AND booking_status = 'pending'
         AND created_at < DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [$pendingTimeout]
    );

    $cleanedCount = 0;

    foreach ($expiredBookings as $booking) {
        // Mark as expired/cancelled
        dbUpdate(
            'bookings',
            [
                'payment_status' => 'failed',
                'booking_status' => 'cancelled',
                'admin_notes' => 'Auto-cancelled: Payment timeout after ' . $pendingTimeout . ' minutes'
            ],
            'id = ?',
            [$booking['id']]
        );

        $cleanedCount++;

        // Log the cleanup
        error_log("Cleaned up expired booking: " . $booking['booking_id']);
    }

    // Output for cron log
    $message = date('Y-m-d H:i:s') . " - Cleaned up $cleanedCount expired pending booking(s)";

    if ($isCli) {
        echo $message . PHP_EOL;
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'cleaned_count' => $cleanedCount,
            'message' => $message
        ]);
    }

} catch (Exception $e) {
    error_log("Cleanup error: " . $e->getMessage());

    if ($isCli) {
        echo "Error: " . $e->getMessage() . PHP_EOL;
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cleanup failed']);
    }
}
