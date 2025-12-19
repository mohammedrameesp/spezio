<?php
/**
 * Spezio Apartments Booking System
 * API: Get Public Settings
 *
 * GET /api/get-settings.php - Get public settings for frontend
 */

require_once __DIR__ . '/functions.php';

setCorsHeaders();

try {
    // List of settings that are safe to expose to frontend
    $publicSettings = [
        'extra_bed_price',
        'currency',
        'currency_symbol',
        'check_in_time',
        'check_out_time',
        'min_booking_days',
        'max_booking_days',
        'site_phone',
        'whatsapp_number'
    ];

    $settings = [];

    foreach ($publicSettings as $key) {
        $value = getSetting($key);
        if ($value !== null) {
            $settings[$key] = $value;
        }
    }

    // Add defaults if not in database
    if (!isset($settings['extra_bed_price'])) {
        $settings['extra_bed_price'] = '600';
    }
    if (!isset($settings['currency'])) {
        $settings['currency'] = CURRENCY;
    }
    if (!isset($settings['currency_symbol'])) {
        $settings['currency_symbol'] = CURRENCY_SYMBOL;
    }

    jsonSuccess($settings, 'Settings retrieved successfully');

} catch (Exception $e) {
    error_log("Get settings error: " . $e->getMessage());
    jsonError('Failed to fetch settings', 500);
}
