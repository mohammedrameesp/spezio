<?php
/**
 * Spezio Apartments Booking System
 * API: Create Razorpay Order
 *
 * POST /api/create-order.php
 * Body: {
 *   "room_id": 1,
 *   "check_in": "2025-01-15",
 *   "check_out": "2025-01-20",
 *   "guest_name": "John Doe",
 *   "guest_email": "john@example.com",
 *   "guest_phone": "9876543210",
 *   "num_guests": 2,
 *   "coupon_code": "WELCOME10",
 *   "special_requests": "",
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

// Rate limiting: 10 order creations per minute per IP
rateLimit('create_order', 10, 60);

// CSRF protection
requireCSRF();

try {
    $data = getJsonInput();

    // Validate required fields
    $required = ['room_id', 'check_in', 'check_out', 'guest_name', 'guest_email', 'guest_phone', 'num_adults'];
    $missing = validateRequired($data, $required);
    if (!empty($missing)) {
        jsonError('Missing required fields: ' . implode(', ', $missing));
    }

    $roomId = (int) $data['room_id'];
    $checkIn = $data['check_in'];
    $checkOut = $data['check_out'];
    $guestName = sanitize($data['guest_name']);
    $guestEmail = sanitize($data['guest_email']);
    $guestPhone = sanitize($data['guest_phone']);
    $numAdults = (int) $data['num_adults'];
    $numChildren = isset($data['num_children']) ? (int) $data['num_children'] : 0;
    $numGuests = $numAdults + $numChildren; // Total guests
    $extraBedCount = isset($data['extra_bed_count']) ? min(2, max(0, (int) $data['extra_bed_count'])) : 0;
    $couponCode = $data['coupon_code'] ?? null;
    $specialRequests = $data['special_requests'] ?? '';

    // Validate email
    if (!isValidEmail($guestEmail)) {
        jsonError('Invalid email address');
    }

    // Validate phone
    if (!isValidPhone($guestPhone)) {
        jsonError('Invalid phone number');
    }

    // Validate dates
    if (!isValidDate($checkIn) || !isValidDate($checkOut)) {
        jsonError('Invalid date format');
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

    // Check room exists
    $room = getRoomById($roomId);
    if (!$room) {
        jsonError('Room not found', 404);
    }

    // Determine max adults and children based on room type
    // 1BHK: 2 adults + 2 children, 2BHK: 4 adults + 4 children
    $roomName = strtolower($room['name']);
    if (strpos($roomName, '2bhk') !== false || strpos($roomName, '2 bhk') !== false) {
        $maxAdults = 4;
        $maxChildren = 4;
    } else {
        // Default for 1BHK
        $maxAdults = 2;
        $maxChildren = 2;
    }

    // Extra beds add adult capacity
    $maxAdults += $extraBedCount;

    if ($numAdults > $maxAdults) {
        jsonError('Maximum ' . $maxAdults . ' adults allowed' . ($extraBedCount > 0 ? ' with ' . $extraBedCount . ' extra bed(s)' : ''));
    }
    if ($numChildren > $maxChildren) {
        jsonError('Maximum ' . $maxChildren . ' children allowed for this room');
    }

    // Check availability
    $availability = checkAvailability($roomId, $checkIn, $checkOut);
    if (!$availability['available']) {
        jsonError($availability['reason']);
    }

    // Calculate nights
    $nights = calculateNights($checkIn, $checkOut);
    if ($nights < MIN_BOOKING_DAYS) {
        jsonError('Minimum booking is ' . MIN_BOOKING_DAYS . ' night(s)');
    }
    if ($nights > MAX_BOOKING_DAYS) {
        jsonError('Maximum booking is ' . MAX_BOOKING_DAYS . ' nights');
    }

    // Base rate
    $baseRatePerNight = (float) $room['price_daily'];
    $roomSubtotal = $nights * $baseRatePerNight;

    // Calculate extra bed charges
    $extraBedData = calculateExtraBedCharge($nights, $extraBedCount);
    $extraBedCharge = $extraBedData['total_charge'];

    // Total before duration discount = room + extra beds
    $subtotalBeforeDiscount = $roomSubtotal + $extraBedCharge;

    // Get applicable duration discount and apply to TOTAL (room + extra beds)
    $durationDiscount = getApplicableDurationDiscount($nights);
    $durationDiscountPercent = 0;
    $durationDiscountAmount = 0;
    $tier = 'Standard';

    if ($durationDiscount) {
        $durationDiscountPercent = (float) $durationDiscount['discount_percent'];
        $durationDiscountAmount = round($subtotalBeforeDiscount * ($durationDiscountPercent / 100), 2);
        $tier = $durationDiscount['label'];
    }

    // Subtotal after duration discount
    $subtotalAfterDurationDiscount = $subtotalBeforeDiscount - $durationDiscountAmount;

    // Apply coupon if provided - apply to amount after duration discount
    $couponId = null;
    $discountAmount = 0;

    if ($couponCode) {
        $couponResult = validateCoupon(
            $couponCode,
            $roomId,
            $subtotalAfterDurationDiscount,
            $nights
        );

        if ($couponResult['valid']) {
            $couponId = $couponResult['coupon_id'];
            $couponCode = $couponResult['coupon_code'];
            $discountAmount = $couponResult['discount_amount'];
        }
    }

    // Calculate final amount
    $totalAmount = $subtotalAfterDurationDiscount - $discountAmount;
    $totalAmount = max(0, $totalAmount);

    // Amount in paise for Razorpay
    $amountInPaise = (int) ($totalAmount * 100);

    // Handle zero-amount bookings (100% discount) - skip Razorpay
    if ($amountInPaise === 0) {
        dbBeginTransaction();

        try {
            $bookingData = [
                'room_id' => $roomId,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'guest_phone' => $guestPhone,
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'num_adults' => $numAdults,
                'num_children' => $numChildren,
                'num_guests' => $numGuests,
                'extra_bed' => $extraBedCount,
                'extra_bed_charge' => $extraBedCharge,
                'total_nights' => $nights,
                'pricing_tier' => $tier,
                'rate_per_night' => $baseRatePerNight,
                'subtotal' => $subtotalAfterDurationDiscount,
                'coupon_id' => $couponId,
                'coupon_code' => $couponCode,
                'discount_amount' => $discountAmount,
                'total_amount' => $totalAmount,
                'razorpay_order_id' => null,
                'special_requests' => $specialRequests
            ];

            $booking = createBooking($bookingData);

            // Mark as paid directly (no payment needed)
            updateBookingPayment($booking['booking_id'], 'FREE_BOOKING', 'no_signature_required', 'paid');

            // Increment coupon usage if used
            if ($couponId) {
                incrementCouponUsage($couponId);
            }

            dbCommit();

            // Fetch the complete booking for email
            $completeBooking = getBookingByBookingId($booking['booking_id']);

            // Send confirmation email (async - don't fail if email fails)
            try {
                require_once __DIR__ . '/send-email.php';
                sendBookingConfirmationEmail($completeBooking);
                sendAdminNotificationEmail($completeBooking);
            } catch (Exception $emailError) {
                error_log("Email sending failed for free booking {$booking['booking_id']}: " . $emailError->getMessage());
            }

            // Return success with free booking flag
            jsonSuccess([
                'booking_id' => $booking['booking_id'],
                'free_booking' => true,
                'amount' => 0,
                'currency' => CURRENCY,
                'booking' => [
                    'room_name' => $room['name'],
                    'check_in' => $checkIn,
                    'check_out' => $checkOut,
                    'total_nights' => $nights,
                    'total_amount' => 0
                ]
            ], 'Booking confirmed successfully');

        } catch (Exception $e) {
            dbRollback();
            throw $e;
        }
        exit;
    }

    // Create Razorpay order for non-zero amounts
    $razorpayOrderData = [
        'amount' => $amountInPaise,
        'currency' => CURRENCY,
        'receipt' => 'spz_' . time(),
        'notes' => [
            'room_id' => $roomId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail
        ]
    ];

    $ch = curl_init('https://api.razorpay.com/v1/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($razorpayOrderData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_USERPWD, RAZORPAY_KEY_ID . ':' . RAZORPAY_KEY_SECRET);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        error_log("Razorpay order creation failed: " . $response);
        jsonError('Failed to create payment order. Please try again.', 500);
    }

    $razorpayOrder = json_decode($response, true);

    if (!isset($razorpayOrder['id'])) {
        error_log("Razorpay order response invalid: " . $response);
        jsonError('Failed to create payment order. Please try again.', 500);
    }

    // Create booking in database with pending status
    dbBeginTransaction();

    try {
        $bookingData = [
            'room_id' => $roomId,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'guest_phone' => $guestPhone,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'num_adults' => $numAdults,
            'num_children' => $numChildren,
            'num_guests' => $numGuests,
            'extra_bed' => $extraBedCount,
            'extra_bed_charge' => $extraBedCharge,
            'total_nights' => $nights,
            'pricing_tier' => $tier,
            'rate_per_night' => $baseRatePerNight,
            'subtotal' => $subtotalAfterDurationDiscount,
            'coupon_id' => $couponId,
            'coupon_code' => $couponCode,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'razorpay_order_id' => $razorpayOrder['id'],
            'special_requests' => $specialRequests
        ];

        $booking = createBooking($bookingData);

        dbCommit();

        // Return order details for frontend
        jsonSuccess([
            'booking_id' => $booking['booking_id'],
            'razorpay_order_id' => $razorpayOrder['id'],
            'razorpay_key_id' => RAZORPAY_KEY_ID,
            'amount' => $amountInPaise,
            'currency' => CURRENCY,
            'prefill' => [
                'name' => $guestName,
                'email' => $guestEmail,
                'contact' => $guestPhone
            ],
            'notes' => [
                'booking_id' => $booking['booking_id']
            ]
        ], 'Order created successfully');

    } catch (Exception $e) {
        dbRollback();
        throw $e;
    }

} catch (Exception $e) {
    error_log("Create order error: " . $e->getMessage());
    jsonError('Failed to create order. Please try again.', 500);
}
