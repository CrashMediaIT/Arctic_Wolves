<?php
/**
 * API: TV Pair State Polling Endpoint
 *
 * Returns the current state of a device pair for the TV viewer to poll.
 * Lightweight JSON response: { active, is_frozen, controller_page }
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

setSecurityHeaders();

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');

if (!isset($_SESSION['logged_in'])) {
    echo json_encode(['active' => false]);
    exit;
}

if (!$db_connected || $pdo === null) {
    echo json_encode(['active' => false]);
    exit;
}

$pair_id = filter_input(INPUT_GET, 'pair_id', FILTER_VALIDATE_INT);
if (!$pair_id) {
    echo json_encode(['active' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT status, is_frozen, controller_page
        FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
    ");
    $stmt->execute([$pair_id]);
    $pair = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pair) {
        echo json_encode(['active' => false]);
        exit;
    }

    echo json_encode([
        'active'          => true,
        'is_frozen'       => (bool)$pair['is_frozen'],
        'controller_page' => $pair['controller_page'] ?? 'home',
    ]);
} catch (PDOException $e) {
    error_log('tv_pair_state: ' . $e->getMessage());
    echo json_encode(['active' => false]);
}
