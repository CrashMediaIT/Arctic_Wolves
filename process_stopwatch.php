<?php
/**
 * Process Stopwatch Actions
 * Handles saving/loading stopwatch sessions and times
 */

session_start();
require_once 'db_config.php';
require_once 'csrf_protection.php';

header('Content-Type: application/json');

function sendJson($success, $message, $data = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// Verify user is logged in
if (!isset($_SESSION['user_id'])) {
    sendJson(false, 'Not authenticated');
}

$user_id = (int) $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// CSRF validation
$csrf = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if (empty($csrf) || $csrf !== ($_SESSION['csrf_token'] ?? '')) {
    sendJson(false, 'Invalid CSRF token');
}

try {
    switch ($action) {
        case 'save_session':
            $session_name = trim($_POST['session_name'] ?? '');
            $skill_id = !empty($_POST['skill_id']) ? (int) $_POST['skill_id'] : null;
            $laps_json = $_POST['laps'] ?? '[]';

            if (empty($session_name)) {
                sendJson(false, 'Session name is required');
            }

            $laps = json_decode($laps_json, true);
            if (!is_array($laps) || empty($laps)) {
                sendJson(false, 'No lap data to save');
            }

            // Validate skill_id if provided
            if ($skill_id) {
                $check = $pdo->prepare("SELECT id FROM eval_skills WHERE id = ? AND is_active = 1");
                $check->execute([$skill_id]);
                if (!$check->fetch()) {
                    $skill_id = null;
                }
            }

            $pdo->beginTransaction();

            // Create session
            $stmt = $pdo->prepare("
                INSERT INTO stopwatch_sessions (coach_id, skill_id, session_name, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user_id, $skill_id, $session_name]);
            $session_id = (int) $pdo->lastInsertId();

            // Insert lap times
            $stmt = $pdo->prepare("
                INSERT INTO stopwatch_times (session_id, athlete_id, lap_number, lap_time_ms, total_time_ms, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $perf_stmt = $pdo->prepare("
                INSERT INTO performance_stats 
                (athlete_id, stat_date, stat_type, stat_value, stat_unit, session_id, recorded_by, notes, created_at)
                VALUES (?, CURDATE(), 'lap_time', ?, 'seconds', ?, ?, ?, NOW())
            ");

            foreach ($laps as $lap) {
                $athlete_id = !empty($lap['athleteId']) ? (int) $lap['athleteId'] : null;
                $lap_number = (int) ($lap['number'] ?? 0);
                $lap_time_ms = (int) ($lap['lapTimeMs'] ?? 0);
                $total_time_ms = (int) ($lap['totalTimeMs'] ?? 0);

                if ($lap_number <= 0 || $lap_time_ms <= 0) continue;

                // Validate athlete_id if provided
                if ($athlete_id) {
                    $check = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
                    $check->execute([$athlete_id]);
                    if (!$check->fetch()) {
                        $athlete_id = null;
                    }
                }

                // Insert into stopwatch_times table
                $stmt->execute([$session_id, $athlete_id, $lap_number, $lap_time_ms, $total_time_ms]);
                
                // Also insert into performance_stats if athlete is assigned
                if ($athlete_id) {
                    $lap_time_seconds = $lap_time_ms / 1000.0; // Convert to seconds
                    $notes = "Lap $lap_number - $session_name";
                    $perf_stmt->execute([$athlete_id, $lap_time_seconds, $session_id, $user_id, $notes]);
                }
            }

            $pdo->commit();
            sendJson(true, 'Session saved with ' . count($laps) . ' lap(s)', ['session_id' => $session_id]);
            break;

        case 'get_session':
            $session_id = (int) ($_POST['session_id'] ?? $_GET['session_id'] ?? 0);
            if ($session_id <= 0) {
                sendJson(false, 'Invalid session ID');
            }

            // Get session info
            $stmt = $pdo->prepare("
                SELECT ss.*, es.name as skill_name
                FROM stopwatch_sessions ss
                LEFT JOIN eval_skills es ON ss.skill_id = es.id
                WHERE ss.id = ? AND ss.coach_id = ?
            ");
            $stmt->execute([$session_id, $user_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$session) {
                sendJson(false, 'Session not found');
            }

            // Get lap times
            $stmt = $pdo->prepare("
                SELECT st.*, u.first_name, u.last_name,
                       CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as athlete_name
                FROM stopwatch_times st
                LEFT JOIN users u ON st.athlete_id = u.id
                WHERE st.session_id = ?
                ORDER BY st.lap_number ASC
            ");
            $stmt->execute([$session_id]);
            $times = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJson(true, 'Session loaded', ['session' => $session, 'times' => $times]);
            break;

        case 'delete_session':
            $session_id = (int) ($_POST['session_id'] ?? 0);
            if ($session_id <= 0) {
                sendJson(false, 'Invalid session ID');
            }

            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM stopwatch_sessions WHERE id = ? AND coach_id = ?");
            $stmt->execute([$session_id, $user_id]);
            if (!$stmt->fetch()) {
                sendJson(false, 'Session not found or access denied');
            }

            $stmt = $pdo->prepare("DELETE FROM stopwatch_sessions WHERE id = ? AND coach_id = ?");
            $stmt->execute([$session_id, $user_id]);
            sendJson(true, 'Session deleted');
            break;

        default:
            sendJson(false, 'Unknown action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    sendJson(false, 'Error: ' . $e->getMessage());
}
