<?php
/**
 * Spezio Apartments Booking System
 * API: Calculate Price
 *
 * POST /api/calculate-price.php
 * Body: { "room_id": 1, "check_in": "2025-01-15", "check_out": "2025-01-20", "coupon_code": "WELCOME10" }
 */

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/security.php';

setCorsHeaders();
setSecurityHeaders();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('Method not allowed', 405);
}

// Rate limiting: 60 price calculations per minute per IP
rateLimit('calculate_price', 60, 60);

try {
    $data = getJsonInput();

    // Validate required fields
    $required = ['room_id', 'check_in', 'check_out'];
    $missing = validateRequired($data, $required);
    if (!empty($missing)) {
        jsonError('Missing required fields: ' . implode(', ', $missing));
    }

    $roomId = (int) $data['room_id'];
    $checkIn = $data['check_in'];
    $checkOut = $data['check_out'];
    $couponCode = $data['coupon_code'] ?? null;
    $extraBedCount = isset($data['extra_bed_count']) ? min(2, max(0, (int) $data['extra_bed_count'])) : 0;

    // Validate dates
    if (!isValidDate($checkIn) || !isValidDate($checkOut)) {
        jsonError('Invalid date format. Use YYYY-MM-DD');
    }

    // Get room info
    $room = getRoomById($roomId);
    if (!$room) {
        jsonError('Room not found');
    }

    $nights = calculateNights($checkIn, $checkOut);
    if ($nights < MIN_BOOKING_DAYS) {
        jsonError('Minimum booking is ' . MIN_BOOKING_DAYS . ' night(s)');
    }
    if ($nights > MAX_BOOKING_DAYS) {
        jsonError('Maximum booking is ' . MAX_BOOKING_DAYS . ' nights');
    }

    // Base rate is always the daily rate
    $baseRatePerNight = (float) $room['price_daily'];
    $roomSubtotal = $nights * $baseRatePerNight;

    // Calculate extra bed charges
    $extraBedData = calculateExtraBedCharge($nights, $extraBedCount);
    $extraBedCharge = $extraBedData['total_charge'];
    $extraBedPricePerNight = $extraBedData['price_per_night'];

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

    // Initialize coupon discount
    $discount = [
        'applied' => false,
        'coupon_id' => null,
        'coupon_code' => null,
        'discount_amount' => 0,
        'discount_text' => null
    ];

    // Apply coupon if provided - apply to amount after duration discount
    if ($couponCode) {
        $couponResult = validateCoupon(
            $couponCode,
            $roomId,
            $subtotalAfterDurationDiscount,
            $nights
        );

        if ($couponResult['valid']) {
            $discount = [
                'applied' => true,
                'coupon_id' => $couponResult['coupon_id'],
                'coupon_code' => $couponResult['coupon_code'],
                'discount_amount' => $couponResult['discount_amount'],
                'discount_text' => $couponResult['discount_text'],
                'description' => $couponResult['description']
            ];
        } else {
            // Include coupon error in response but don't fail
            $discount['error'] = $couponResult['error'];
        }
    }

    // Calculate final total
    $totalAmount = $subtotalAfterDurationDiscount - $discount['discount_amount'];
    $totalAmount = max(0, $totalAmount); // Ensure non-negative

    $response = [
        'room_id' => $roomId,
        'room_name' => $room['name'],
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'nights' => $nights,
        'pricing' => [
            'tier' => $tier,
            'tier_label' => $tier,
            'base_rate_per_night' => $baseRatePerNight,
            'rate_per_night' => $baseRatePerNight,
            'duration_discount_percent' => $durationDiscountPercent,
            'duration_discount_amount' => $durationDiscountAmount,
            'subtotal' => $subtotalAfterDurationDiscount
        ],
        'extra_bed' => [
            'count' => $extraBedCount,
            'price_per_night' => $extraBedPricePerNight,
            'total_charge' => $extraBedCharge
        ],
        'discount' => $discount,
        'total_amount' => round($totalAmount, 2),
        'currency' => CURRENCY,
        'currency_symbol' => CURRENCY_SYMBOL,
        'breakdown' => []
    ];

    // Add room cost to breakdown
    $response['breakdown'][] = [
        'label' => $nights . ' night(s) × ' . CURRENCY_SYMBOL . number_format($baseRatePerNight) . '/night',
        'amount' => $roomSubtotal
    ];

    // Add extra bed to breakdown BEFORE duration discount
    if ($extraBedCount > 0) {
        $bedLabel = $extraBedCount === 1 ? 'Extra Bed' : $extraBedCount . ' Extra Beds';
        $response['breakdown'][] = [
            'label' => $bedLabel . ' (' . $nights . ' night(s) × ' . CURRENCY_SYMBOL . number_format($extraBedPricePerNight) . ' each)',
            'amount' => $extraBedCharge
        ];
    }

    // Add duration discount to breakdown if applicable (applies to room + extra beds)
    if ($durationDiscountPercent > 0) {
        $response['breakdown'][] = [
            'label' => $tier . ' Discount (' . $durationDiscountPercent . '% off)',
            'amount' => -$durationDiscountAmount,
            'type' => 'discount'
        ];
    }

    // Add coupon discount to breakdown if applied
    if ($discount['applied']) {
        $response['breakdown'][] = [
            'label' => 'Coupon: ' . $discount['coupon_code'] . ' (' . $discount['discount_text'] . ')',
            'amount' => -$discount['discount_amount'],
            'type' => 'discount'
        ];
    }

    // Add total to breakdown
    $response['breakdown'][] = [
        'label' => 'Total',
        'amount' => $totalAmount,
        'type' => 'total'
    ];

    jsonSuccess($response, 'Price calculated successfully');

} catch (Exception $e) {
    error_log("Calculate price error: " . $e->getMessage());
    jsonError('Failed to calculate price', 500);
}
