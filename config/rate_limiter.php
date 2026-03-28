<?php
/**
 * Rate Limiting Middleware
 * 
 * Implements rate limiting for API endpoints to prevent:
 * - Denial of service attacks
 * - Brute force attacks
 * - Resource exhaustion
 * 
 * Usage: Call check_rate_limit() at the beginning of API endpoints
 */

/**
 * Check rate limit for an endpoint
 * 
 * @param string $endpoint Identifier for the endpoint (e.g., 'login', 'api')
 * @param int $limit Maximum number of requests allowed
 * @param int $window Time window in seconds
 * @return void
 */
function check_rate_limit($endpoint, $limit = 100, $window = 60) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit:{$endpoint}:{$ip}";
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    // Reset counter if window has expired
    if (time() > $_SESSION[$key]['reset']) {
        $_SESSION[$key] = ['count' => 0, 'reset' => time() + $window];
    }
    
    $_SESSION[$key]['count']++;
    
    // Check if rate limit exceeded
    if ($_SESSION[$key]['count'] > $limit) {
        http_response_code(429);
        header('Content-Type: application/json');
        header('Retry-After: ' . ($_SESSION[$key]['reset'] - time()));
        die(json_encode([
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'retry_after' => $_SESSION[$key]['reset'] - time()
        ]));
    }
}

/**
 * Get remaining requests for an endpoint
 * 
 * @param string $endpoint Identifier for the endpoint
 * @param int $limit Maximum number of requests allowed
 * @return int Number of remaining requests
 */
function get_remaining_requests($endpoint, $limit = 100) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit:{$endpoint}:{$ip}";
    
    if (!isset($_SESSION[$key]) || time() > $_SESSION[$key]['reset']) {
        return $limit;
    }
    
    return max(0, $limit - $_SESSION[$key]['count']);
}
