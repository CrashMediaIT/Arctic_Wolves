<?php
/**
 * API v1 - Report Endpoints
 * Provides report access for ACWolvesAPP.
 *
 * Endpoints:
 *   GET  /v1/reports             - List available/scheduled reports
 *   POST /v1/reports/generate    - Generate a report
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;

if ($method === 'GET' && !$action) {
    handleListReports($auth);
} elseif ($method === 'POST' && $action === 'generate') {
    handleGenerateReport($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Report endpoint not found. Use: GET /reports, POST /reports/generate']);
}

/**
 * GET /v1/reports
 */
function handleListReports($auth) {
    global $pdo;

    $allowed = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
    if (!in_array($auth['user_role'], $allowed)) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    try {
        // List scheduled reports
        $stmt = $pdo->prepare("
            SELECT sr.id, sr.report_name, sr.schedule_frequency, sr.is_active, sr.last_run_at, sr.created_at,
                   u.first_name AS creator_first_name, u.last_name AS creator_last_name
            FROM scheduled_reports sr
            LEFT JOIN users u ON sr.created_by = u.id
            ORDER BY sr.created_at DESC
        ");
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as &$report) {
            $report['created_by_name'] = trim(
                FieldEncryption::decrypt($report['creator_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($report['creator_last_name'] ?? '')
            );
            unset($report['creator_first_name'], $report['creator_last_name']);
        }
        unset($report);

        // Also include available report types
        $available_types = [
            ['type' => 'session_attendance', 'name' => 'Session Attendance'],
            ['type' => 'athlete_progress', 'name' => 'Athlete Progress'],
            ['type' => 'financial_summary', 'name' => 'Financial Summary'],
            ['type' => 'team_roster', 'name' => 'Team Roster'],
            ['type' => 'evaluation_summary', 'name' => 'Evaluation Summary'],
        ];

        logApiAccess('list_reports', 'Listed reports', $auth['user_id']);
        apiResponse(200, [
            'success' => true,
            'data' => [
                'scheduled' => $reports,
                'available_types' => $available_types,
            ],
        ]);
    } catch (PDOException $e) {
        error_log('[API REPORTS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/reports/generate
 */
function handleGenerateReport($auth) {
    global $pdo;

    $allowed = ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'];
    if (!in_array($auth['user_role'], $allowed)) {
        apiResponse(403, ['success' => false, 'error' => 'Insufficient permissions']);
    }

    $body = getJsonBody();
    $report_type = $body['type'] ?? '';

    if (empty($report_type)) {
        apiResponse(400, ['success' => false, 'error' => 'Report type is required']);
    }

    try {
        $data = [];

        switch ($report_type) {
            case 'session_attendance':
                $date_from = $body['date_from'] ?? date('Y-m-01');
                $date_to = $body['date_to'] ?? date('Y-m-d');
                $stmt = $pdo->prepare("
                    SELECT s.id, s.title, s.session_date, COUNT(b.id) AS bookings_count
                    FROM sessions s
                    LEFT JOIN bookings b ON s.id = b.session_id AND b.status = 'confirmed'
                    WHERE s.session_date BETWEEN ? AND ?
                    GROUP BY s.id
                    ORDER BY s.session_date DESC
                ");
                $stmt->execute([$date_from, $date_to]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'athlete_progress':
                $stmt = $pdo->prepare("
                    SELECT ae.athlete_id, AVG(ae.rating) AS avg_rating, COUNT(*) AS eval_count,
                           MAX(ae.evaluation_date) AS last_eval_date
                    FROM athlete_evaluations ae
                    WHERE ae.status = 'completed'
                    GROUP BY ae.athlete_id
                    ORDER BY avg_rating DESC
                ");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'financial_summary':
                if ($auth['user_role'] !== 'admin') {
                    apiResponse(403, ['success' => false, 'error' => 'Admin only']);
                }
                $stmt = $pdo->prepare("
                    SELECT transaction_type, COUNT(*) AS count, SUM(total_amount) AS total
                    FROM transactions
                    WHERE transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY transaction_type
                ");
                $stmt->execute();
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                break;

            case 'team_roster':
                $team_id = (int) ($body['team_id'] ?? 0);
                if ($team_id) {
                    $stmt = $pdo->prepare("
                        SELECT u.id, u.first_name, u.last_name, u.email, u.position
                        FROM team_roster tr
                        INNER JOIN users u ON tr.athlete_id = u.id
                        WHERE tr.team_id = ?
                        ORDER BY u.last_name
                    ");
                    $stmt->execute([$team_id]);
                    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($data as &$row) {
                        $row['first_name'] = FieldEncryption::decrypt($row['first_name'] ?? '');
                        $row['last_name'] = FieldEncryption::decrypt($row['last_name'] ?? '');
                    }
                    unset($row);
                }
                break;

            default:
                apiResponse(400, ['success' => false, 'error' => "Unknown report type: $report_type"]);
        }

        logApiAccess('generate_report', "Generated report: $report_type", $auth['user_id']);
        apiResponse(200, [
            'success' => true,
            'data' => [
                'type' => $report_type,
                'generated_at' => date('Y-m-d\TH:i:s\Z'),
                'results' => $data,
            ],
        ]);
    } catch (PDOException $e) {
        error_log('[API REPORTS ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
