<?php
/**
 * OpenSign Webhook Handler
 * Receives webhook callbacks from OpenSign when documents are signed
 */

require_once 'db_config.php';
require_once 'lib/opensign.php';

// Set JSON response header
header('Content-Type: application/json');

// Get the raw POST data
$rawInput = file_get_contents('php://input');
$webhookData = json_decode($rawInput, true);

if (!$webhookData) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON payload']);
    exit;
}

// Get OpenSign settings
$settings = getOpenSignSettings($pdo);

// Verify webhook signature if secret is configured
if (!empty($settings['opensign_webhook_secret'])) {
    $signature = $_SERVER['HTTP_X_OPENSIGN_SIGNATURE'] ?? '';
    $expectedSignature = hash_hmac('sha256', $rawInput, $settings['opensign_webhook_secret']);
    
    if (!hash_equals($expectedSignature, $signature)) {
        error_log("OpenSign webhook signature verification failed");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid signature']);
        exit;
    }
}

// Log the webhook event
error_log("OpenSign webhook received: " . ($webhookData['event_type'] ?? 'unknown'));

// Process the webhook
$result = processOpenSignWebhook($pdo, $webhookData);

if ($result['success']) {
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
} else {
    http_response_code(400);
    echo json_encode($result);
}
