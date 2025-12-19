<?php
/**
 * Spezio Apartments Booking System
 * API: Get Rooms
 *
 * GET /api/get-rooms.php - Get all rooms
 * GET /api/get-rooms.php?id=1 - Get room by ID
 * GET /api/get-rooms.php?slug=1bhk - Get room by slug
 */

require_once __DIR__ . '/functions.php';

setCorsHeaders();

try {
    // Get single room by ID
    if (isset($_GET['id'])) {
        $room = getRoomById((int) $_GET['id']);
        if (!$room) {
            jsonError('Room not found', 404);
        }

        // Parse JSON fields
        $room['amenities'] = json_decode($room['amenities'], true) ?: [];
        $room['images'] = json_decode($room['images'], true) ?: [];

        // Add pricing tiers info
        $room['pricing'] = [
            'daily' => [
                'rate' => (float) $room['price_daily'],
                'description' => '1-6 nights'
            ],
            'weekly' => [
                'rate' => (float) $room['price_weekly'],
                'description' => '7-29 nights'
            ],
            'monthly' => [
                'rate' => (float) $room['price_monthly'],
                'description' => '30+ nights'
            ]
        ];

        jsonSuccess(['room' => $room]);
    }

    // Get single room by slug
    if (isset($_GET['slug'])) {
        $room = getRoomBySlug(sanitize($_GET['slug']));
        if (!$room) {
            jsonError('Room not found', 404);
        }

        $room['amenities'] = json_decode($room['amenities'], true) ?: [];
        $room['images'] = json_decode($room['images'], true) ?: [];

        $room['pricing'] = [
            'daily' => [
                'rate' => (float) $room['price_daily'],
                'description' => '1-6 nights'
            ],
            'weekly' => [
                'rate' => (float) $room['price_weekly'],
                'description' => '7-29 nights'
            ],
            'monthly' => [
                'rate' => (float) $room['price_monthly'],
                'description' => '30+ nights'
            ]
        ];

        jsonSuccess(['room' => $room]);
    }

    // Get all rooms
    $rooms = getRooms();

    foreach ($rooms as &$room) {
        $room['amenities'] = json_decode($room['amenities'], true) ?: [];
        $room['images'] = json_decode($room['images'], true) ?: [];

        $room['pricing'] = [
            'daily' => [
                'rate' => (float) $room['price_daily'],
                'description' => '1-6 nights'
            ],
            'weekly' => [
                'rate' => (float) $room['price_weekly'],
                'description' => '7-29 nights'
            ],
            'monthly' => [
                'rate' => (float) $room['price_monthly'],
                'description' => '30+ nights'
            ]
        ];
    }

    jsonSuccess([
        'rooms' => $rooms,
        'currency' => CURRENCY,
        'currency_symbol' => CURRENCY_SYMBOL
    ]);

} catch (Exception $e) {
    error_log("Get rooms error: " . $e->getMessage());
    jsonError('Failed to fetch rooms', 500);
}
