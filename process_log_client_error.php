<?php
/**
 * Client-Side Error Logging Endpoint
 *
 * Receives error reports from browser JavaScript (e.g. video playback
 * failures) and writes them to the error_logs table via ErrorLogger so
 * they appear in the admin Security view.
 *
 * POST parameters:
 *   csrf_token  – required CSRF token
 *   message     – short human-readable error description
 *   context     – JSON-encoded context object (optional)
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json; charset=utf-8');

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Require authenticated session
if (empty($_SESSION['logged_in']) || empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// Validate CSRF (returns JSON 403 for AJAX automatically)
checkCsrfToken();

$user_id = (int) $_SESSION['user_id'];
$message = trim($_POST['message'] ?? '');

if ($message === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Message is required']);
    exit;
}

// Cap message length to prevent abuse
if (mb_strlen($message) > 1000) {
    $message = mb_substr($message, 0, 1000) . '…';
}

// Parse optional context
$context = [];
$raw_context = $_POST['context'] ?? '';
if ($raw_context !== '' && mb_strlen($raw_context) <= 5000) {
    $decoded = json_decode($raw_context, true);
    if (is_array($decoded)) {
        $context = $decoded;
    }
}

// Tag as a client-side report so admins can distinguish from server errors
$context['source'] = 'client';

// Initialize ErrorLogger with database if available
if (isset($pdo)) {
    ErrorLogger::setDatabase($pdo);
}

ErrorLogger::warning("Video playback: $message", $context);

echo json_encode(['success' => true]);
