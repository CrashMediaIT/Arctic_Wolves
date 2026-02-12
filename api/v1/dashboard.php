<?php
/**
 * API v1 - Dashboard Endpoints
 * Provides dashboard data for ACWolvesAPP.
 *
 * Endpoints:
 *   GET /v1/dashboard/stats      - Get dashboard statistics
 *   GET /v1/dashboard/schedule   - Get upcoming schedule
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;

if ($method === 'GET' && $action === 'stats') {
    handleGetStats($auth);
} elseif ($method === 'GET' && $action === 'schedule') {
    handleGetSchedule($auth);
} elseif ($method === 'GET' && !$action) {
    handleGetStats($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Dashboard endpoint not found. Use: stats, schedule']);
}

/**
 * GET /v1/dashboard/stats
 */
function handleGetStats($auth) {
    global $pdo;

    try {
        $stats = [];

        if (in_array($auth['user_role'], ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'])) {
            // Upcoming sessions count
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE session_date >= CURDATE() AND status = 'scheduled'");
            $stmt->execute();
            $stats['upcoming_sessions'] = (int) $stmt->fetchColumn();

            // Total active athletes
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE role = 'athlete' AND is_active = 1");
            $stmt->execute();
            $stats['total_athletes'] = (int) $stmt->fetchColumn();

            // Active teams
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM teams WHERE is_active = 1");
            $stmt->execute();
            $stats['active_teams'] = (int) $stmt->fetchColumn();

            // Pending video reviews
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE status = 'pending_review'");
            $stmt->execute();
            $stats['pending_reviews'] = (int) $stmt->fetchColumn();

            // Unread notifications
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
            $stmt->execute([$auth['user_id']]);
            $stats['unread_notifications'] = (int) $stmt->fetchColumn();
        } elseif ($auth['user_role'] === 'athlete') {
            // Upcoming bookings
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM bookings b
                INNER JOIN sessions s ON b.session_id = s.id
                WHERE b.user_id = ? AND s.session_date >= CURDATE() AND b.status = 'confirmed'
            ");
            $stmt->execute([$auth['user_id']]);
            $stats['upcoming_sessions'] = (int) $stmt->fetchColumn();

            // Unread notifications
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
            $stmt->execute([$auth['user_id']]);
            $stats['unread_notifications'] = (int) $stmt->fetchColumn();

            // Pending video reviews
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE athlete_id = ? AND status = 'pending_review'");
            $stmt->execute([$auth['user_id']]);
            $stats['pending_reviews'] = (int) $stmt->fetchColumn();
        } else {
            // Parent / other
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
            $stmt->execute([$auth['user_id']]);
            $stats['unread_notifications'] = (int) $stmt->fetchColumn();
        }

        logApiAccess('dashboard_stats', 'Viewed dashboard stats', $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $stats]);
    } catch (PDOException $e) {
        error_log('[API DASHBOARD ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/dashboard/schedule
 */
function handleGetSchedule($auth) {
    global $pdo;

    try {
        $limit = min(50, max(1, (int) ($_GET['limit'] ?? 10)));

        if (in_array($auth['user_role'], ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'])) {
            $stmt = $pdo->prepare("
                SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                       s.arena, s.city, s.status, s.session_type,
                       c.first_name AS coach_first_name, c.last_name AS coach_last_name
                FROM sessions s
                LEFT JOIN users c ON s.coach_id = c.id
                WHERE s.session_date >= CURDATE() AND s.status = 'scheduled'
                ORDER BY s.session_date ASC, s.session_time ASC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
        } else {
            // Athletes and parents see their booked sessions
            $stmt = $pdo->prepare("
                SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                       s.arena, s.city, s.status, s.session_type,
                       c.first_name AS coach_first_name, c.last_name AS coach_last_name
                FROM sessions s
                INNER JOIN bookings b ON s.id = b.session_id
                LEFT JOIN users c ON s.coach_id = c.id
                WHERE b.user_id = ? AND s.session_date >= CURDATE() AND b.status = 'confirmed'
                ORDER BY s.session_date ASC, s.session_time ASC
                LIMIT ?
            ");
            $stmt->execute([$auth['user_id'], $limit]);
        }

        $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($sessions as &$session) {
            $session['coach_name'] = trim(
                FieldEncryption::decrypt($session['coach_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($session['coach_last_name'] ?? '')
            );
            unset($session['coach_first_name'], $session['coach_last_name']);
        }
        unset($session);

        logApiAccess('dashboard_schedule', 'Viewed dashboard schedule', $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $sessions]);
    } catch (PDOException $e) {
        error_log('[API DASHBOARD ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
