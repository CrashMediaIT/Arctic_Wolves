<?php
/**
 * API: TV Pair State Polling Endpoint
 *
 * Returns the current state of a device pair for the TV viewer to poll.
 * Lightweight JSON response: { active, is_frozen, controller_page, telestration_seq }
 * Pass ?include_telestration=1 to also receive the full telestration_data (canvas drawing).
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
    $include_telestration = !empty($_GET['include_telestration']);
    if ($include_telestration) {
        $stmt = $pdo->prepare("
            SELECT status, is_frozen, controller_page, telestration_seq, telestration_data
            FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
        ");
    } else {
        $stmt = $pdo->prepare("
            SELECT status, is_frozen, controller_page, telestration_seq
            FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
        ");
    }
    $stmt->execute([$pair_id]);
    $pair = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pair) {
        echo json_encode(['active' => false]);
        exit;
    }

    $response = [
        'active'            => true,
        'is_frozen'         => (bool)$pair['is_frozen'],
        'controller_page'   => $pair['controller_page'] ?? 'home',
        'telestration_seq'  => (int)($pair['telestration_seq'] ?? 0),
    ];
    if ($include_telestration && isset($pair['telestration_data'])) {
        $response['telestration_data'] = $pair['telestration_data'];
    }
    echo json_encode($response);
} catch (PDOException $e) {
    error_log('tv_pair_state: ' . $e->getMessage());
    echo json_encode(['active' => false]);
}
