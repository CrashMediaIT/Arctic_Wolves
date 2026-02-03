<?php
/**
 * DocuSeal Webhook Handler
 * Receives webhook callbacks from DocuSeal when documents are signed
 */

require_once 'db_config.php';
require_once 'lib/docuseal.php';

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

// Get DocuSeal settings
$settings = getDocuSealSettings($pdo);

// Verify webhook signature if secret is configured
if (!empty($settings['docuseal_webhook_secret'])) {
    $signature = $_SERVER['HTTP_X_DOCUSEAL_SIGNATURE'] ?? '';
    $expectedSignature = hash_hmac('sha256', $rawInput, $settings['docuseal_webhook_secret']);
    
    if (!hash_equals($expectedSignature, $signature)) {
        error_log("DocuSeal webhook signature verification failed");
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Invalid signature']);
        exit;
    }
}

// Log the webhook event
error_log("DocuSeal webhook received: " . ($webhookData['event_type'] ?? 'unknown'));

// Process the webhook
$result = processDocuSealWebhook($pdo, $webhookData);

if ($result['success']) {
    echo json_encode(['success' => true, 'message' => 'Webhook processed']);
} else {
    http_response_code(400);
    echo json_encode($result);
}
