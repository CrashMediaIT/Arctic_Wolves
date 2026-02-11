<?php
/**
 * API v1 - Booking Endpoints
 *
 * Endpoints:
 *   GET  /v1/bookings           - List user's bookings
 *   GET  /v1/bookings/{id}      - Get booking details
 *   POST /v1/bookings           - Create a booking
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$booking_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$booking_id) {
    handleListBookings($auth);
} elseif ($method === 'GET' && $booking_id && !$action) {
    handleGetBooking($auth, (int) $booking_id);
} elseif ($method === 'POST' && !$booking_id) {
    handleCreateBooking($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Booking endpoint not found']);
}

/**
 * GET /v1/bookings
 */
function handleListBookings($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_bookings')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    // Role-based: non-admin users see only their own bookings
    if ($auth['user_role'] !== 'admin') {
        $where[] = 'b.user_id = ?';
        $params[] = $auth['user_id'];
    }

    if (!empty($_GET['status'])) {
        $where[] = 'b.status = ?';
        $params[] = $_GET['status'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings b $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT b.id, b.user_id, b.session_id, b.status, b.payment_status,
                   b.amount_paid, b.booking_date, b.created_at,
                   s.title AS session_title, s.session_date, s.session_time,
                   s.arena, s.city
            FROM bookings b
            LEFT JOIN sessions s ON b.session_id = s.id
            $where_sql
            ORDER BY b.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiAccess('list_bookings', "Listed bookings (page $page)", $auth['user_id']);
        paginatedResponse($bookings, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API BOOKINGS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/bookings/{id}
 */
function handleGetBooking($auth, $booking_id) {
    global $pdo;

    if (!hasApiPermission($auth, 'read_bookings')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        $stmt = $pdo->prepare("
            SELECT b.*, s.title AS session_title, s.session_date, s.session_time,
                   s.arena, s.city, s.duration_minutes
            FROM bookings b
            LEFT JOIN sessions s ON b.session_id = s.id
            WHERE b.id = ?
        ");
        $stmt->execute([$booking_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$booking) {
            apiResponse(404, ['success' => false, 'error' => 'Booking not found']);
        }

        // Access control: users can only see their own bookings
        if ($auth['user_role'] !== 'admin' && $booking['user_id'] != $auth['user_id']) {
            apiResponse(403, ['success' => false, 'error' => 'Access denied']);
        }

        logApiAccess('get_booking', "Viewed booking ID: $booking_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $booking]);
    } catch (PDOException $e) {
        error_log('[API BOOKINGS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/bookings
 */
function handleCreateBooking($auth) {
    global $pdo;

    if (!hasApiPermission($auth, 'write_bookings')) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();
    $session_id = (int) ($body['session_id'] ?? 0);

    if (!$session_id) {
        apiResponse(400, ['success' => false, 'error' => 'session_id is required']);
    }

    try {
        // Check session exists and has capacity
        $stmt = $pdo->prepare("SELECT id, max_participants, status FROM sessions WHERE id = ? AND status = 'scheduled'");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$session) {
            apiResponse(404, ['success' => false, 'error' => 'Session not found or not available']);
        }

        // Check if already booked
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE user_id = ? AND session_id = ? AND status != 'cancelled'");
        $stmt->execute([$auth['user_id'], $session_id]);
        if ($stmt->fetch()) {
            apiResponse(409, ['success' => false, 'error' => 'Already booked for this session']);
        }

        // Check capacity
        if ($session['max_participants']) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE session_id = ? AND status != 'cancelled'");
            $stmt->execute([$session_id]);
            $current = (int) $stmt->fetchColumn();
            if ($current >= $session['max_participants']) {
                apiResponse(409, ['success' => false, 'error' => 'Session is full']);
            }
        }

        // Create booking
        $stmt = $pdo->prepare("
            INSERT INTO bookings (user_id, session_id, status, booking_date, created_at)
            VALUES (?, ?, 'confirmed', NOW(), NOW())
        ");
        $stmt->execute([$auth['user_id'], $session_id]);
        $booking_id = $pdo->lastInsertId();

        logApiAccess('create_booking', "Created booking ID: $booking_id for session ID: $session_id", $auth['user_id']);
        apiResponse(201, [
            'success' => true,
            'message' => 'Booking created successfully',
            'data'    => ['id' => (int) $booking_id, 'session_id' => $session_id, 'status' => 'confirmed'],
        ]);
    } catch (PDOException $e) {
        error_log('[API BOOKINGS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
