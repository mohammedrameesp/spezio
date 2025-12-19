<?php
/**
 * Spezio Apartments Booking System
 * API: Check Availability
 *
 * POST /api/check-availability.php
 * Body: { "room_id": 1, "check_in": "2025-01-15", "check_out": "2025-01-20" }
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

setCorsHeaders();
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting: 60 availability checks per minute per IP
rateLimit('check_availability', 60, 60);

try {
    $data = getJsonInput();

    $roomId = isset($data['room_id']) ? (int) $data['room_id'] : 0;

    // Check if this is a request for booked dates (for calendar)
    if (isset($data['get_dates']) && $data['get_dates'] === true) {
        if (!$roomId) {
            jsonError('Room ID is required');
        }

        $startDate = $data['check_in'] ?? date('Y-m-d');
        $endDate = $data['check_out'] ?? date('Y-m-d', strtotime('+' . ADVANCE_BOOKING_DAYS . ' days'));

        $bookedDates = getBookedDates($roomId, $startDate, $endDate);

        jsonSuccess([
            'room_id' => $roomId,
            'booked_dates' => $bookedDates
        ], 'Booked dates retrieved');
    }

    // Validate required fields for availability check
    $required = ['room_id', 'check_in', 'check_out'];
    $missing = validateRequired($data, $required);
    if (!empty($missing)) {
        jsonError('Missing required fields: ' . implode(', ', $missing));
    }

    $checkIn = $data['check_in'];
    $checkOut = $data['check_out'];

    // Validate dates
    if (!isValidDate($checkIn) || !isValidDate($checkOut)) {
        jsonError('Invalid date format. Use YYYY-MM-DD');
    }

    // Check date logic
    $checkInDate = new DateTime($checkIn);
    $checkOutDate = new DateTime($checkOut);
    $today = new DateTime('today');

    if ($checkInDate < $today) {
        jsonError('Check-in date cannot be in the past');
    }

    if ($checkOutDate <= $checkInDate) {
        jsonError('Check-out date must be after check-in date');
    }

    // Check advance booking limit
    $maxDate = new DateTime('+' . ADVANCE_BOOKING_DAYS . ' days');
    if ($checkInDate > $maxDate) {
        jsonError('Bookings can only be made up to ' . ADVANCE_BOOKING_DAYS . ' days in advance');
    }

    // Check nights
    $nights = calculateNights($checkIn, $checkOut);
    if ($nights < MIN_BOOKING_DAYS) {
        jsonError('Minimum booking is ' . MIN_BOOKING_DAYS . ' night(s)');
    }
    if ($nights > MAX_BOOKING_DAYS) {
        jsonError('Maximum booking is ' . MAX_BOOKING_DAYS . ' nights');
    }

    // Check room exists
    $room = getRoomById($roomId);
    if (!$room) {
        jsonError('Room not found', 404);
    }

    // Check availability
    $availability = checkAvailability($roomId, $checkIn, $checkOut);

    if ($availability['available']) {
        jsonSuccess([
            'available' => true,
            'room_id' => $roomId,
            'room_name' => $room['name'],
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'nights' => $nights
        ], 'Room is available');
    } else {
        jsonSuccess([
            'available' => false,
            'reason' => $availability['reason']
        ], 'Room is not available');
    }

} catch (Exception $e) {
    error_log("Check availability error: " . $e->getMessage());
    jsonError('Failed to check availability', 500);
}
