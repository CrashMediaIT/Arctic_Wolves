<?php
/**
 * Arctic Wolves REST API - Main Entry Point
 * Domain: api.arcticwolves.ca
 *
 * Provides API access for external applications:
 *   - ACVideoReview (video review application)
 *   - ACWolvesAPP (Arctic Wolves mobile/web application)
 *
 * API Version: v1
 * Authentication: API Key via Authorization header or X-API-Key header
 */

// Prevent session-based output (API is stateless)
ini_set('session.use_cookies', '0');

// Set JSON response headers and CORS for external apps
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
header('Access-Control-Max-Age: 86400');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Parse the request
$request_uri = $_SERVER['REQUEST_URI'] ?? '/';
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Remove query string from URI for routing
$uri_path = parse_url($request_uri, PHP_URL_PATH);

// Strip /api prefix if present (when accessed via rewrite)
$uri_path = preg_replace('#^/api#', '', $uri_path);

// Remove leading/trailing slashes and split into segments
$uri_path = trim($uri_path, '/');
$segments = $uri_path ? explode('/', $uri_path) : [];

// Root API endpoint - no auth required
if (empty($segments) || (count($segments) === 1 && $segments[0] === '')) {
    echo json_encode([
        'success' => true,
        'name'    => 'Arctic Wolves API',
        'version' => 'v1',
        'domain'  => 'api.arcticwolves.ca',
        'documentation' => 'https://api.arcticwolves.ca/v1',
        'endpoints' => [
            'auth'          => '/v1/auth',
            'users'         => '/v1/users',
            'videos'        => '/v1/videos',
            'sessions'      => '/v1/sessions',
            'teams'         => '/v1/teams',
            'bookings'      => '/v1/bookings',
            'drills'        => '/v1/drills',
            'notifications' => '/v1/notifications',
        ],
    ]);
    exit;
}

// Version check
$version = $segments[0] ?? '';
if ($version !== 'v1') {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => 'API version not found. Use /v1/ prefix.',
    ]);
    exit;
}

// Extract resource and remaining path segments
$resource = $segments[1] ?? '';
$resource_id = $segments[2] ?? null;
$action = $segments[3] ?? null;

// Route to the appropriate handler
$handler_file = __DIR__ . '/v1/' . basename($resource) . '.php';

if (empty($resource)) {
    // /v1/ endpoint - list available resources
    echo json_encode([
        'success'   => true,
        'version'   => 'v1',
        'resources' => ['auth', 'users', 'videos', 'sessions', 'teams', 'bookings', 'drills', 'notifications'],
    ]);
    exit;
}

if (!file_exists($handler_file)) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error'   => "Resource '{$resource}' not found.",
    ]);
    exit;
}

// Make route info available to handlers
$GLOBALS['api_resource_id'] = $resource_id;
$GLOBALS['api_action'] = $action;
$GLOBALS['api_method'] = $request_method;
$GLOBALS['api_segments'] = array_slice($segments, 2);

/**
 * Helper: Get JSON request body
 *
 * @return array
 */
function getJsonBody() {
    $body = file_get_contents('php://input');
    if (empty($body)) {
        return [];
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : [];
}

/**
 * Helper: Send a JSON response with HTTP status code
 *
 * @param int   $status HTTP status code
 * @param array $data   Response data
 */
function apiResponse($status, $data) {
    http_response_code($status);
    echo json_encode($data);
    exit;
}

/**
 * Helper: Send a paginated response
 *
 * @param array $items   Array of items
 * @param int   $total   Total count
 * @param int   $page    Current page
 * @param int   $perPage Items per page
 */
function paginatedResponse($items, $total, $page, $perPage) {
    apiResponse(200, [
        'success' => true,
        'data'    => $items,
        'pagination' => [
            'total'    => $total,
            'page'     => $page,
            'per_page' => $perPage,
            'pages'    => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
        ],
    ]);
}

// Include the resource handler
require $handler_file;
