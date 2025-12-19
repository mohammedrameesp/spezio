<?php
/**
 * Spezio Apartments Booking System
 * API: Get CSRF Token
 *
 * GET /api/get-csrf-token.php
 * Returns a CSRF token for use in subsequent POST requests
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/security.php';

// Set headers
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
setSecurityHeaders();

// Apply rate limiting (100 requests per minute)
rateLimit('csrf_token', 100, 60);

// Generate and return token
$token = getCSRFToken();

echo json_encode([
    'success' => true,
    'csrf_token' => $token
]);
