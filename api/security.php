<?php
/**
 * Spezio Apartments Booking System
 * Security Functions: CSRF Protection & Rate Limiting
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// =====================================================
// CSRF PROTECTION
// =====================================================

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token (generates if needed)
 */
function getCSRFToken() {
    return generateCSRFToken();
}

/**
 * Validate CSRF token
 * @param string $token Token from request
 * @param int $maxAge Maximum token age in seconds (default: 1 hour)
 * @return bool
 */
function validateCSRFToken($token, $maxAge = 3600) {
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }

    // Check token match
    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        return false;
    }

    // Check token age
    if (isset($_SESSION['csrf_token_time'])) {
        if (time() - $_SESSION['csrf_token_time'] > $maxAge) {
            // Token expired, regenerate
            unset($_SESSION['csrf_token']);
            unset($_SESSION['csrf_token_time']);
            return false;
        }
    }

    return true;
}

/**
 * Validate CSRF from request headers or body
 * Looks for token in X-CSRF-Token header or csrf_token body field
 */
function validateCSRFRequest() {
    // Get token from header first
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;

    // If not in header, check request body
    if (!$token) {
        $input = json_decode(file_get_contents('php://input'), true);
        $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? null;
    }

    return validateCSRFToken($token);
}

/**
 * Require valid CSRF token or return error
 */
function requireCSRF() {
    if (!validateCSRFRequest()) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'Invalid or expired security token. Please refresh the page and try again.'
        ]);
        exit();
    }
}

// =====================================================
// RATE LIMITING
// =====================================================

/**
 * Simple file-based rate limiter
 * In production, use Redis or database for better performance
 */
class RateLimiter {
    private $storageDir;
    private $maxRequests;
    private $windowSeconds;

    public function __construct($maxRequests = 60, $windowSeconds = 60) {
        $this->storageDir = sys_get_temp_dir() . '/spezio_rate_limit/';
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;

        if (!is_dir($this->storageDir)) {
            @mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Get client identifier (IP address)
     */
    private function getClientId() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
              $_SERVER['HTTP_X_REAL_IP'] ??
              $_SERVER['REMOTE_ADDR'] ??
              'unknown';

        // Handle comma-separated IPs from proxies
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }

        return md5($ip);
    }

    /**
     * Get rate limit file path for client
     */
    private function getFilePath($clientId, $endpoint = 'global') {
        $endpoint = preg_replace('/[^a-z0-9_-]/i', '_', $endpoint);
        return $this->storageDir . $clientId . '_' . $endpoint . '.json';
    }

    /**
     * Check if request is allowed
     * @param string $endpoint Optional endpoint identifier for per-endpoint limits
     * @return array ['allowed' => bool, 'remaining' => int, 'reset' => int]
     */
    public function check($endpoint = 'global') {
        $clientId = $this->getClientId();
        $filePath = $this->getFilePath($clientId, $endpoint);
        $now = time();

        $data = ['requests' => [], 'window_start' => $now];

        if (file_exists($filePath)) {
            $content = @file_get_contents($filePath);
            if ($content) {
                $data = json_decode($content, true) ?: $data;
            }
        }

        // Clean old requests outside window
        $windowStart = $now - $this->windowSeconds;
        $data['requests'] = array_filter($data['requests'], function($timestamp) use ($windowStart) {
            return $timestamp > $windowStart;
        });

        $requestCount = count($data['requests']);
        $remaining = max(0, $this->maxRequests - $requestCount);
        $resetTime = $now + $this->windowSeconds;

        if ($requestCount >= $this->maxRequests) {
            return [
                'allowed' => false,
                'remaining' => 0,
                'reset' => $resetTime,
                'retry_after' => $this->windowSeconds
            ];
        }

        // Add current request
        $data['requests'][] = $now;

        // Save data
        @file_put_contents($filePath, json_encode($data), LOCK_EX);

        return [
            'allowed' => true,
            'remaining' => $remaining - 1,
            'reset' => $resetTime
        ];
    }

    /**
     * Apply rate limiting and return error if exceeded
     * @param string $endpoint Optional endpoint identifier
     * @param int $maxRequests Override max requests for this check
     * @param int $windowSeconds Override window for this check
     */
    public function enforce($endpoint = 'global', $maxRequests = null, $windowSeconds = null) {
        if ($maxRequests !== null) {
            $this->maxRequests = $maxRequests;
        }
        if ($windowSeconds !== null) {
            $this->windowSeconds = $windowSeconds;
        }

        $result = $this->check($endpoint);

        // Set rate limit headers
        header('X-RateLimit-Limit: ' . $this->maxRequests);
        header('X-RateLimit-Remaining: ' . $result['remaining']);
        header('X-RateLimit-Reset: ' . $result['reset']);

        if (!$result['allowed']) {
            header('Retry-After: ' . $result['retry_after']);
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Too many requests. Please wait before trying again.',
                'retry_after' => $result['retry_after']
            ]);
            exit();
        }

        return $result;
    }

    /**
     * Clean up old rate limit files
     */
    public function cleanup() {
        if (!is_dir($this->storageDir)) {
            return;
        }

        $files = glob($this->storageDir . '*.json');
        $now = time();

        foreach ($files as $file) {
            if ($now - filemtime($file) > $this->windowSeconds * 2) {
                @unlink($file);
            }
        }
    }
}

// Global rate limiter instance
$rateLimiter = new RateLimiter();

/**
 * Apply default rate limiting
 * @param string $endpoint Endpoint identifier
 * @param int $maxRequests Max requests per window
 * @param int $windowSeconds Window in seconds
 */
function rateLimit($endpoint = 'global', $maxRequests = 60, $windowSeconds = 60) {
    global $rateLimiter;
    return $rateLimiter->enforce($endpoint, $maxRequests, $windowSeconds);
}

// =====================================================
// SECURITY HEADERS
// =====================================================

/**
 * Set security headers for API responses
 */
function setSecurityHeaders() {
    // Prevent clickjacking
    header('X-Frame-Options: DENY');

    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');

    // XSS protection
    header('X-XSS-Protection: 1; mode=block');

    // Referrer policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// =====================================================
// INPUT SANITIZATION HELPERS
// =====================================================

/**
 * Sanitize and validate IP address
 */
function sanitizeIP($ip) {
    return filter_var($ip, FILTER_VALIDATE_IP) ?: null;
}

/**
 * Get client IP address safely
 */
function getClientIP() {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ??
          $_SERVER['HTTP_X_REAL_IP'] ??
          $_SERVER['REMOTE_ADDR'] ??
          null;

    if ($ip && strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }

    return sanitizeIP($ip);
}
