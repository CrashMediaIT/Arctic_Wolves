<?php
/**
 * API v1 - TV Pair Endpoints
 * Provides device-pairing operations for the Android TV native app.
 *
 * Endpoints:
 *   POST   /v1/tv/pair          - Join as a viewer by submitting a pair code
 *   GET    /v1/tv/pair/{id}     - Poll current pair state (page, frozen flag)
 *   DELETE /v1/tv/pair/{id}     - Disconnect / unpair the viewer
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method   = $GLOBALS['api_method'];
$segments = $GLOBALS['api_segments'] ?? [];

$sub_resource    = $segments[0] ?? null;   // "pair"
$pair_id         = $segments[1] ?? null;   // numeric id
$sub_action      = $segments[2] ?? null;

// Only the "pair" sub-resource is supported
if ($sub_resource !== 'pair') {
    apiResponse(404, ['success' => false, 'error' => 'TV endpoint not found. Use /v1/tv/pair']);
}

// ── Route ──────────────────────────────────────────────────
if ($method === 'POST' && !$pair_id) {
    handleTvJoinPair($auth);
} elseif ($method === 'GET' && $pair_id) {
    handleTvGetPairState($auth, (int) $pair_id);
} elseif ($method === 'DELETE' && $pair_id) {
    handleTvUnpair($auth, (int) $pair_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'TV pair endpoint not found']);
}

// ── Handlers ───────────────────────────────────────────────

/**
 * POST /v1/tv/pair
 * Body: { "pair_code": "ABC123" }
 * Joins the requesting device as the viewer for the given pair code.
 */
function handleTvJoinPair($auth) {
    global $pdo;

    $body = getJsonBody();
    $pair_code = strtoupper(trim($body['pair_code'] ?? ''));

    if (empty($pair_code) || strlen($pair_code) > 10 || !preg_match('/^[A-Z0-9]+$/', $pair_code)) {
        apiResponse(400, ['success' => false, 'error' => 'Invalid pair code format. Must be 1-10 alphanumeric characters.']);
    }

    try {
        $viewer_token = bin2hex(random_bytes(32));

        $stmt = $pdo->prepare("
            UPDATE vr_device_pairs SET viewer_token = ?, status = 'paired'
            WHERE pair_code = ? AND status = 'waiting'
        ");
        $stmt->execute([$viewer_token, $pair_code]);

        if ($stmt->rowCount() === 0) {
            apiResponse(404, ['success' => false, 'error' => 'Pair code not found or already paired']);
        }

        $stmt2 = $pdo->prepare("SELECT id, controller_page, is_frozen FROM vr_device_pairs WHERE pair_code = ? AND viewer_token = ?");
        $stmt2->execute([$pair_code, $viewer_token]);
        $pair = $stmt2->fetch(PDO::FETCH_ASSOC);

        if (!$pair) {
            apiResponse(500, ['success' => false, 'error' => 'Failed to retrieve pair after joining']);
        }

        logApiAccess('tv_pair_join', "TV viewer joined pair $pair_code", $auth['user_id']);

        apiResponse(200, [
            'success'         => true,
            'pair_id'         => (int) $pair['id'],
            'viewer_token'    => $viewer_token,
            'controller_page' => $pair['controller_page'] ?? 'home',
            'is_frozen'       => (bool) $pair['is_frozen'],
        ]);
    } catch (PDOException $e) {
        error_log('[API TV] join pair: ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Server error while joining pair']);
    }
}

/**
 * GET /v1/tv/pair/{id}
 * Returns current pair state for the TV viewer to poll.
 */
function handleTvGetPairState($auth, $pair_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT status, is_frozen, controller_page
            FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
        ");
        $stmt->execute([$pair_id]);
        $pair = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pair) {
            apiResponse(200, [
                'success' => true,
                'active'  => false,
            ]);
        }

        apiResponse(200, [
            'success'         => true,
            'active'          => true,
            'is_frozen'       => (bool) $pair['is_frozen'],
            'controller_page' => $pair['controller_page'] ?? 'home',
        ]);
    } catch (PDOException $e) {
        error_log('[API TV] get pair state: ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Server error while fetching pair state']);
    }
}

/**
 * DELETE /v1/tv/pair/{id}
 * Ends/disconnects the viewer from the pair.
 */
function handleTvUnpair($auth, $pair_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            UPDATE vr_device_pairs SET status = 'ended', viewer_token = NULL
            WHERE id = ? AND status IN ('paired', 'active')
        ");
        $stmt->execute([$pair_id]);

        logApiAccess('tv_pair_disconnect', "TV viewer disconnected pair #$pair_id", $auth['user_id']);

        apiResponse(200, ['success' => true, 'message' => 'Viewer disconnected']);
    } catch (PDOException $e) {
        error_log('[API TV] unpair: ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Server error while disconnecting']);
    }
}
