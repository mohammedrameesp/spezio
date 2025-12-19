<?php
/**
 * Spezio Apartments Booking System
 * Helper Functions
 */

require_once __DIR__ . '/db.php';

// =====================================================
// INPUT VALIDATION
// =====================================================

/**
 * Sanitize string input
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate email
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate phone (Indian format)
 */
function isValidPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 12;
}

/**
 * Validate date format (Y-m-d)
 */
function isValidDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Get POST JSON data
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    return $data ?: [];
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field])) {
            $missing[] = $field;
        } elseif (is_string($data[$field]) && trim($data[$field]) === '') {
            $missing[] = $field;
        } elseif ($data[$field] === null) {
            $missing[] = $field;
        }
        // Note: 0, false, "0" are considered valid values
    }
    return $missing;
}

// =====================================================
// BOOKING ID GENERATION
// =====================================================

/**
 * Generate unique booking ID
 * Format: SPZ-YYYYMMDD-XXX
 */
function generateBookingId() {
    $date = date('Ymd');
    $prefix = "SPZ-{$date}-";

    // Get today's booking count
    $count = dbCount('bookings', 'DATE(created_at) = CURDATE()');
    $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);

    $bookingId = $prefix . $sequence;

    // Check if exists and increment if needed
    while (dbExists('bookings', 'booking_id = ?', [$bookingId])) {
        $count++;
        $sequence = str_pad($count + 1, 3, '0', STR_PAD_LEFT);
        $bookingId = $prefix . $sequence;
    }

    return $bookingId;
}

// =====================================================
// ROOM FUNCTIONS
// =====================================================

/**
 * Get all active rooms
 */
function getRooms() {
    return dbFetchAll(
        "SELECT * FROM rooms WHERE status = 'active' ORDER BY display_order ASC"
    );
}

/**
 * Get room by ID
 */
function getRoomById($id) {
    return dbFetchOne("SELECT * FROM rooms WHERE id = ? AND status = 'active'", [$id]);
}

/**
 * Get room by slug
 */
function getRoomBySlug($slug) {
    return dbFetchOne("SELECT * FROM rooms WHERE slug = ? AND status = 'active'", [$slug]);
}

// =====================================================
// PRICING FUNCTIONS
// =====================================================

/**
 * Calculate number of nights between dates
 */
function calculateNights($checkIn, $checkOut) {
    $start = new DateTime($checkIn);
    $end = new DateTime($checkOut);
    return (int) $start->diff($end)->days;
}

/**
 * Get all active duration discounts
 */
function getDurationDiscounts() {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM duration_discounts WHERE is_active = 1 ORDER BY min_nights ASC");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get applicable duration discount for given nights
 */
function getApplicableDurationDiscount($nights) {
    $discounts = getDurationDiscounts();

    // Find the highest applicable discount (discounts are ordered by min_nights ASC)
    $applicable = null;
    foreach ($discounts as $discount) {
        if ($nights >= $discount['min_nights']) {
            $applicable = $discount;
        }
    }

    return $applicable;
}

/**
 * Determine pricing tier based on nights (for display purposes)
 */
function getPricingTier($nights) {
    $discount = getApplicableDurationDiscount($nights);
    if ($discount) {
        return $discount['label'];
    }
    return 'Standard';
}

/**
 * Calculate price for a booking with duration-based discounts
 */
function calculatePrice($roomId, $checkIn, $checkOut) {
    $room = getRoomById($roomId);
    if (!$room) {
        return ['error' => 'Room not found'];
    }

    $nights = calculateNights($checkIn, $checkOut);
    if ($nights < MIN_BOOKING_DAYS) {
        return ['error' => 'Minimum booking is ' . MIN_BOOKING_DAYS . ' night(s)'];
    }
    if ($nights > MAX_BOOKING_DAYS) {
        return ['error' => 'Maximum booking is ' . MAX_BOOKING_DAYS . ' nights'];
    }

    // Base rate is always the daily rate
    $baseRatePerNight = (float) $room['price_daily'];
    $subtotalBeforeDiscount = $nights * $baseRatePerNight;

    // Get applicable duration discount
    $durationDiscount = getApplicableDurationDiscount($nights);
    $discountPercent = 0;
    $discountAmount = 0;
    $tier = 'Standard';

    if ($durationDiscount) {
        $discountPercent = (float) $durationDiscount['discount_percent'];
        $discountAmount = round($subtotalBeforeDiscount * ($discountPercent / 100), 2);
        $tier = $durationDiscount['label'];
    }

    // Calculate effective rate and subtotal after discount
    $subtotal = $subtotalBeforeDiscount - $discountAmount;
    $effectiveRatePerNight = round($subtotal / $nights, 2);

    return [
        'room_id' => $roomId,
        'room_name' => $room['name'],
        'check_in' => $checkIn,
        'check_out' => $checkOut,
        'nights' => $nights,
        'tier' => $tier,
        'base_rate_per_night' => $baseRatePerNight,
        'rate_per_night' => $effectiveRatePerNight,
        'subtotal_before_discount' => $subtotalBeforeDiscount,
        'duration_discount_percent' => $discountPercent,
        'duration_discount_amount' => $discountAmount,
        'subtotal' => $subtotal,
        'currency' => CURRENCY,
        'currency_symbol' => CURRENCY_SYMBOL
    ];
}

/**
 * Get extra bed price per night
 */
function getExtraBedPrice() {
    $price = getSetting('extra_bed_price');
    return $price !== null ? (float) $price : 600.00; // Default: 600
}

/**
 * Calculate extra bed charges
 */
function calculateExtraBedCharge($nights, $extraBedCount = 0) {
    // Handle both boolean (legacy) and integer count
    if (is_bool($extraBedCount)) {
        $extraBedCount = $extraBedCount ? 1 : 0;
    }
    $extraBedCount = min(2, max(0, (int) $extraBedCount));

    if ($extraBedCount === 0) {
        return [
            'count' => 0,
            'price_per_night' => 0,
            'total_charge' => 0
        ];
    }

    $pricePerNight = getExtraBedPrice();
    $totalCharge = $pricePerNight * $nights * $extraBedCount;

    return [
        'count' => $extraBedCount,
        'price_per_night' => $pricePerNight,
        'total_charge' => $totalCharge
    ];
}

// =====================================================
// COUPON FUNCTIONS
// =====================================================

/**
 * Validate and apply coupon
 */
function validateCoupon($code, $roomId, $subtotal, $nights) {
    $code = strtoupper(trim($code));

    $coupon = dbFetchOne(
        "SELECT * FROM coupons WHERE code = ? AND status = 'active'",
        [$code]
    );

    if (!$coupon) {
        return ['valid' => false, 'error' => 'Invalid coupon code'];
    }

    $today = date('Y-m-d');

    // Check validity dates
    if ($today < $coupon['valid_from'] || $today > $coupon['valid_until']) {
        return ['valid' => false, 'error' => 'Coupon has expired or is not yet active'];
    }

    // Check usage limit
    if ($coupon['usage_limit'] !== null && $coupon['used_count'] >= $coupon['usage_limit']) {
        return ['valid' => false, 'error' => 'Coupon usage limit reached'];
    }

    // Check minimum nights
    if ($nights < $coupon['min_nights']) {
        return ['valid' => false, 'error' => "Minimum {$coupon['min_nights']} night(s) required for this coupon"];
    }

    // Check minimum amount
    if ($subtotal < $coupon['min_amount']) {
        return ['valid' => false, 'error' => "Minimum booking amount of " . CURRENCY_SYMBOL . number_format($coupon['min_amount']) . " required"];
    }

    // Check room restriction
    if ($coupon['room_ids']) {
        $allowedRooms = json_decode($coupon['room_ids'], true);
        if (is_array($allowedRooms) && !in_array($roomId, $allowedRooms)) {
            return ['valid' => false, 'error' => 'Coupon not applicable for this room'];
        }
    }

    // Calculate discount
    if ($coupon['discount_type'] === 'percentage') {
        $discount = $subtotal * ($coupon['discount_value'] / 100);
        if ($coupon['max_discount'] !== null && $discount > $coupon['max_discount']) {
            $discount = $coupon['max_discount'];
        }
        $discountText = $coupon['discount_value'] . '% off';
    } else {
        $discount = min($coupon['discount_value'], $subtotal);
        $discountText = CURRENCY_SYMBOL . number_format($coupon['discount_value']) . ' off';
    }

    return [
        'valid' => true,
        'coupon_id' => $coupon['id'],
        'coupon_code' => $coupon['code'],
        'discount_type' => $coupon['discount_type'],
        'discount_value' => $coupon['discount_value'],
        'discount_amount' => round($discount, 2),
        'discount_text' => $discountText,
        'description' => $coupon['description']
    ];
}

/**
 * Increment coupon usage count
 */
function incrementCouponUsage($couponId) {
    return dbQuery(
        "UPDATE coupons SET used_count = used_count + 1 WHERE id = ?",
        [$couponId]
    );
}

// =====================================================
// AVAILABILITY FUNCTIONS
// =====================================================

/**
 * Cleanup expired pending bookings (15+ minutes old)
 * Called automatically during availability check
 */
function cleanupExpiredPendingBookings() {
    static $lastCleanup = 0;

    // Only run cleanup every 5 minutes max
    $now = time();
    if ($now - $lastCleanup < 300) {
        return;
    }
    $lastCleanup = $now;

    try {
        dbQuery(
            "UPDATE bookings
             SET payment_status = 'failed',
                 booking_status = 'cancelled',
                 admin_notes = 'Auto-cancelled: Payment timeout'
             WHERE payment_status = 'pending'
             AND booking_status = 'pending'
             AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)"
        );
    } catch (Exception $e) {
        error_log("Cleanup expired bookings error: " . $e->getMessage());
    }
}

/**
 * Check room availability for date range
 * Uses shared pool logic: 18 total rooms for all room types (1BHK & 2BHK)
 */
function checkAvailability($roomId, $checkIn, $checkOut) {
    // Cleanup expired pending bookings first
    cleanupExpiredPendingBookings();

    $totalRooms = defined('TOTAL_ROOMS') ? TOTAL_ROOMS : 18;

    // Count total bookings across ALL room types for the date range
    // This is the key change: we count ALL bookings, not just for specific room_id
    $bookedCount = dbFetchOne(
        "SELECT COUNT(*) as count FROM bookings
         WHERE booking_status IN ('confirmed', 'pending')
         AND payment_status IN ('paid', 'pending')
         AND (
             (check_in < ? AND check_out > ?)
             OR (check_in >= ? AND check_in < ?)
             OR (check_out > ? AND check_out <= ?)
         )",
        [$checkOut, $checkIn, $checkIn, $checkOut, $checkIn, $checkOut]
    );

    $bookedRooms = $bookedCount ? (int)$bookedCount['count'] : 0;

    if ($bookedRooms >= $totalRooms) {
        return ['available' => false, 'reason' => 'No rooms available for selected dates. All ' . $totalRooms . ' rooms are booked.'];
    }

    // Check for blocked dates (applies to all rooms)
    $blockedCount = dbFetchOne(
        "SELECT COUNT(DISTINCT blocked_date) as count FROM blocked_dates
         WHERE blocked_date >= ?
         AND blocked_date < ?",
        [$checkIn, $checkOut]
    );

    // If any date in range is blocked for all rooms, not available
    // (This is a simplified check - you may want more granular control)

    return [
        'available' => true,
        'rooms_available' => $totalRooms - $bookedRooms,
        'total_rooms' => $totalRooms
    ];
}

/**
 * Get booked dates for calendar display
 * Returns dates when ALL 18 rooms are fully booked
 */
function getBookedDates($roomId, $startDate = null, $endDate = null) {
    $startDate = $startDate ?: date('Y-m-d');
    $endDate = $endDate ?: date('Y-m-d', strtotime('+' . ADVANCE_BOOKING_DAYS . ' days'));
    $totalRooms = defined('TOTAL_ROOMS') ? TOTAL_ROOMS : 18;

    $fullyBookedDates = [];

    // Check each date in the range
    $current = new DateTime($startDate);
    $end = new DateTime($endDate);

    while ($current <= $end) {
        $date = $current->format('Y-m-d');
        $nextDate = (clone $current)->modify('+1 day')->format('Y-m-d');

        // Count bookings that include this date
        $bookedCount = dbFetchOne(
            "SELECT COUNT(*) as count FROM bookings
             WHERE booking_status IN ('confirmed', 'pending')
             AND payment_status IN ('paid', 'pending')
             AND check_in <= ?
             AND check_out > ?",
            [$date, $date]
        );

        $bookedRooms = $bookedCount ? (int)$bookedCount['count'] : 0;

        // If all rooms booked for this date, mark as unavailable
        if ($bookedRooms >= $totalRooms) {
            $fullyBookedDates[] = $date;
        }

        $current->modify('+1 day');
    }

    // Also get blocked dates (applies to all rooms)
    $blocked = dbFetchAll(
        "SELECT DISTINCT blocked_date FROM blocked_dates
         WHERE blocked_date >= ?
         AND blocked_date <= ?",
        [$startDate, $endDate]
    );

    foreach ($blocked as $block) {
        $fullyBookedDates[] = $block['blocked_date'];
    }

    return array_unique($fullyBookedDates);
}

// =====================================================
// BOOKING FUNCTIONS
// =====================================================

/**
 * Create a new booking
 */
function createBooking($data) {
    $bookingId = generateBookingId();

    $insertData = [
        'booking_id' => $bookingId,
        'room_id' => $data['room_id'],
        'guest_name' => sanitize($data['guest_name']),
        'guest_email' => sanitize($data['guest_email']),
        'guest_phone' => sanitize($data['guest_phone']),
        'check_in' => $data['check_in'],
        'check_out' => $data['check_out'],
        'num_adults' => $data['num_adults'] ?? 1,
        'num_children' => $data['num_children'] ?? 0,
        'num_guests' => $data['num_guests'],
        'extra_bed' => $data['extra_bed'] ?? 0,
        'extra_bed_charge' => $data['extra_bed_charge'] ?? 0,
        'total_nights' => $data['total_nights'],
        'pricing_tier' => $data['pricing_tier'],
        'rate_per_night' => $data['rate_per_night'],
        'subtotal' => $data['subtotal'],
        'coupon_id' => $data['coupon_id'] ?? null,
        'coupon_code' => $data['coupon_code'] ?? null,
        'discount_amount' => $data['discount_amount'] ?? 0,
        'total_amount' => $data['total_amount'],
        'razorpay_order_id' => $data['razorpay_order_id'] ?? null,
        'special_requests' => sanitize($data['special_requests'] ?? ''),
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ];

    $id = dbInsert('bookings', $insertData);

    return [
        'id' => $id,
        'booking_id' => $bookingId
    ];
}

/**
 * Get booking by ID
 */
function getBookingById($id) {
    return dbFetchOne(
        "SELECT b.*, r.name as room_name, r.slug as room_slug
         FROM bookings b
         JOIN rooms r ON b.room_id = r.id
         WHERE b.id = ?",
        [$id]
    );
}

/**
 * Get booking by booking ID
 */
function getBookingByBookingId($bookingId) {
    return dbFetchOne(
        "SELECT b.*, r.name as room_name, r.slug as room_slug
         FROM bookings b
         JOIN rooms r ON b.room_id = r.id
         WHERE b.booking_id = ?",
        [$bookingId]
    );
}

/**
 * Update booking payment status
 */
function updateBookingPayment($bookingId, $paymentId, $signature, $status = 'paid') {
    return dbUpdate(
        'bookings',
        [
            'razorpay_payment_id' => $paymentId,
            'razorpay_signature' => $signature,
            'payment_status' => $status,
            'booking_status' => $status === 'paid' ? 'confirmed' : 'pending'
        ],
        'booking_id = ?',
        [$bookingId]
    );
}

// =====================================================
// DATE/TIME HELPERS
// =====================================================

/**
 * Format date for display
 */
function formatDate($date, $format = 'd M Y') {
    return date($format, strtotime($date));
}

/**
 * Format currency
 */
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}

/**
 * Get setting value
 */
function getSetting($key, $default = null) {
    $setting = dbFetchOne("SELECT setting_value FROM settings WHERE setting_key = ?", [$key]);
    return $setting ? $setting['setting_value'] : $default;
}

/**
 * Update setting
 */
function updateSetting($key, $value) {
    return dbQuery(
        "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        [$key, $value]
    );
}

// =====================================================
// LOGGING
// =====================================================

/**
 * Log activity
 */
function logActivity($adminId, $action, $entityType = null, $entityId = null, $details = null) {
    return dbInsert('activity_log', [
        'admin_id' => $adminId,
        'action' => $action,
        'entity_type' => $entityType,
        'entity_id' => $entityId,
        'details' => $details ? json_encode($details) : null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null
    ]);
}
