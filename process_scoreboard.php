<?php
/**
 * Process Scoreboard Actions
 * Handles AJAX requests for the scoreboard module:
 * - Game CRUD (create, update status, end)
 * - Goals, shots, penalties tracking
 * - Scoresheet sync to Game Plan game results + player stats
 * - Music/audio integration endpoints
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/rustfs_storage.php';

// Set security headers
setSecurityHeaders();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

// Staff-only access
$user_roles_list = [$user_role];
try {
    $rolesStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $rolesStmt->execute([$user_id]);
    $extraRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($extraRoles) {
        $user_roles_list = array_unique(array_merge($user_roles_list, $extraRoles));
    }
} catch (PDOException $e) { /* ignore */ }

$isAdmin       = in_array('admin', $user_roles_list);
$isCoach       = in_array('coach', $user_roles_list);
$isHealthCoach = in_array('health_coach', $user_roles_list);
$isFrontDesk   = in_array('front_desk_staff', $user_roles_list);
$isHR          = in_array('hr', $user_roles_list);
$isAccounting  = in_array('accounting', $user_roles_list);
$isStaff       = ($isAdmin || $isCoach || $isHealthCoach || $isFrontDesk || $isHR || $isAccounting);

if (!$isStaff) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Staff access required']);
    exit();
}

// POS IP restriction
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('scoreboard_ip_blocked', 'Scoreboard process access denied from unauthorized IP', ['ip' => getClientIP()]);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied from this location']);
    exit();
}

// AJAX check
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

if (!isAjaxRequest()) {
    header("Location: scoreboard.php");
    exit();
}

// Validate CSRF – check header first, then POST body
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit();
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

try {
    switch ($action) {

        // ── Start new game ────────────────────────────────
        case 'start_game':
            $home_name = trim($_POST['home_team_name'] ?? '');
            $away_name = trim($_POST['away_team_name'] ?? '');
            $home_team_id = !empty($_POST['home_team_id']) ? (int)$_POST['home_team_id'] : null;
            $away_team_id = !empty($_POST['away_team_id']) ? (int)$_POST['away_team_id'] : null;
            $is_aw_game = !empty($_POST['is_arctic_wolves_game']) ? 1 : 0;

            if (empty($home_name) || empty($away_name)) {
                echo json_encode(['success' => false, 'message' => 'Team names required']);
                exit();
            }

            $stmt = $pdo->prepare("
                INSERT INTO scoreboard_games (home_team_name, away_team_name, home_team_id, away_team_id,
                    is_arctic_wolves_game, status, current_period, created_by)
                VALUES (?, ?, ?, ?, ?, 'warmup', 1, ?)
            ");
            $stmt->execute([$home_name, $away_name, $home_team_id, $away_team_id, $is_aw_game, $user_id]);
            $game_id = (int)$pdo->lastInsertId();

            Auditor::log($pdo, $user_id, 'create', 'scoreboard_games', $game_id, ['action' => 'Game started', 'home' => $home_name, 'away' => $away_name]);

            echo json_encode(['success' => true, 'game_id' => $game_id]);
            break;

        // ── Update game status ────────────────────────────
        case 'update_status':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $status = $_POST['status'] ?? '';
            $allowed_statuses = ['warmup', 'in_progress', 'intermission', 'final'];
            if (!in_array($status, $allowed_statuses) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid status or game ID']);
                exit();
            }
            $pdo->prepare("UPDATE scoreboard_games SET status = ? WHERE id = ?")->execute([$status, $game_id]);
            echo json_encode(['success' => true]);
            break;

        // ── Update period ─────────────────────────────────
        case 'update_period':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $period = $_POST['period'] ?? '';
            $allowed_periods = ['1', '2', '3', 'OT', 'SO'];
            if (!in_array($period, $allowed_periods) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid period']);
                exit();
            }
            $pdo->prepare("UPDATE scoreboard_games SET current_period = ? WHERE id = ?")->execute([$period, $game_id]);
            echo json_encode(['success' => true]);
            break;

        // ── Add goal (quick) ──────────────────────────────
        case 'add_goal':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $team = $_POST['team'] ?? '';
            if (!in_array($team, ['home', 'away']) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team or game']);
                exit();
            }
            $col = ($team === 'home') ? 'home_score' : 'away_score';
            $pdo->prepare("UPDATE scoreboard_games SET $col = $col + 1 WHERE id = ?")->execute([$game_id]);
            // Fetch updated scores
            $stmt = $pdo->prepare("SELECT home_score, away_score FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $scores = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'home_score' => (int)$scores['home_score'], 'away_score' => (int)$scores['away_score']]);
            break;

        // ── Undo goal ─────────────────────────────────────
        case 'undo_goal':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $team = $_POST['team'] ?? '';
            if (!in_array($team, ['home', 'away']) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team or game']);
                exit();
            }
            $col = ($team === 'home') ? 'home_score' : 'away_score';
            $pdo->prepare("UPDATE scoreboard_games SET $col = GREATEST($col - 1, 0) WHERE id = ?")->execute([$game_id]);
            $stmt = $pdo->prepare("SELECT home_score, away_score FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $scores = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'home_score' => (int)$scores['home_score'], 'away_score' => (int)$scores['away_score']]);
            break;

        // ── Add shot ──────────────────────────────────────
        case 'add_shot':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $team = $_POST['team'] ?? '';
            if (!in_array($team, ['home', 'away']) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team or game']);
                exit();
            }
            $col = ($team === 'home') ? 'home_shots' : 'away_shots';
            $pdo->prepare("UPDATE scoreboard_games SET $col = $col + 1 WHERE id = ?")->execute([$game_id]);
            $stmt = $pdo->prepare("SELECT home_shots, away_shots FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $shots = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'home_shots' => (int)$shots['home_shots'], 'away_shots' => (int)$shots['away_shots']]);
            break;

        // ── Add penalty ───────────────────────────────────
        case 'add_penalty':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $team = $_POST['team'] ?? '';
            $player_number = trim($_POST['player_number'] ?? '');
            $player_name = trim($_POST['player_name'] ?? '');
            $infraction = trim($_POST['infraction'] ?? '');
            $duration = (int)($_POST['duration_minutes'] ?? 2);

            if (!in_array($team, ['home', 'away']) || $game_id <= 0 || empty($infraction)) {
                echo json_encode(['success' => false, 'message' => 'Invalid penalty data']);
                exit();
            }

            // Get current period from game
            $stmt = $pdo->prepare("SELECT current_period FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $gm = $stmt->fetch(PDO::FETCH_ASSOC);
            $period = $gm['current_period'] ?? '1';

            $stmt = $pdo->prepare("
                INSERT INTO scoreboard_penalties (game_id, team, period, player_number, player_name, infraction, duration_minutes)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$game_id, $team, $period, $player_number, $player_name, $infraction, $duration]);

            echo json_encode(['success' => true, 'penalty_id' => (int)$pdo->lastInsertId()]);
            break;

        // ── Clear / delete penalty ────────────────────────
        case 'clear_penalty':
            $penalty_id = (int)($_POST['penalty_id'] ?? 0);
            $game_id = (int)($_POST['game_id'] ?? 0);
            if ($penalty_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid penalty ID']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM scoreboard_penalties WHERE id = ? AND game_id = ?");
            $stmt->execute([$penalty_id, $game_id]);
            Auditor::log($pdo, $user_id, 'delete', 'scoreboard_penalties', $penalty_id, ['action' => 'Penalty cleared']);
            echo json_encode(['success' => true]);
            break;

        // ── Add goal detail (scoresheet) ──────────────────
        case 'add_goal_detail':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $period = $_POST['period'] ?? '1';
            $game_time = trim($_POST['game_time'] ?? '');
            $team = $_POST['team'] ?? 'home';
            $scorer_number = trim($_POST['scorer_number'] ?? '');
            $scorer_name = trim($_POST['scorer_name'] ?? '');
            $assist1_number = trim($_POST['assist1_number'] ?? '');
            $assist1_name = trim($_POST['assist1_name'] ?? '');
            $assist2_number = trim($_POST['assist2_number'] ?? '');
            $assist2_name = trim($_POST['assist2_name'] ?? '');
            $goal_type = trim($_POST['goal_type'] ?? 'Even Strength');

            if ($game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid game']);
                exit();
            }

            $stmt = $pdo->prepare("
                INSERT INTO scoreboard_goals (game_id, period, game_time, team, scorer_number, scorer_name,
                    assist1_number, assist1_name, assist2_number, assist2_name, goal_type)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$game_id, $period, $game_time, $team, $scorer_number, $scorer_name,
                $assist1_number, $assist1_name, $assist2_number, $assist2_name, $goal_type]);

            echo json_encode(['success' => true, 'goal_id' => (int)$pdo->lastInsertId()]);
            break;

        // ── End game ──────────────────────────────────────
        case 'end_game':
            $game_id = (int)($_POST['game_id'] ?? 0);
            if ($game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid game']);
                exit();
            }
            $pdo->prepare("UPDATE scoreboard_games SET status = 'final', ended_at = NOW() WHERE id = ?")->execute([$game_id]);
            Auditor::log($pdo, $user_id, 'update', 'scoreboard_games', $game_id, ['action' => 'Game ended']);
            echo json_encode(['success' => true]);
            break;

        // ── Sync to Game Plan (Arctic Wolves games only) ──
        case 'sync_to_gameplan':
            $game_id = (int)($_POST['game_id'] ?? 0);
            if ($game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid game']);
                exit();
            }

            // Fetch game
            $stmt = $pdo->prepare("SELECT * FROM scoreboard_games WHERE id = ? AND is_arctic_wolves_game = 1");
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$game) {
                echo json_encode(['success' => false, 'message' => 'Game not found or not an Arctic Wolves game']);
                exit();
            }

            // Fetch goals for stat updates
            $stmt = $pdo->prepare("SELECT * FROM scoreboard_goals WHERE game_id = ?");
            $stmt->execute([$game_id]);
            $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch penalties for PIM
            $stmt = $pdo->prepare("SELECT * FROM scoreboard_penalties WHERE game_id = ?");
            $stmt->execute([$game_id]);
            $penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $aw_team_id = $game['home_team_id'] ?: $game['away_team_id'];

            // Try to create game result in vr_game_plans (post_game type)
            $resultCreated = false;
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO vr_game_plans (coach_id, plan_type, game_date, opponent, notes, created_at)
                    VALUES (?, 'post_game', ?, ?, ?, NOW())
                ");
                $opponent = ($game['home_team_id'] ? $game['away_team_name'] : $game['home_team_name']);
                $notes = "Final Score: {$game['home_team_name']} {$game['home_score']} - {$game['away_team_name']} {$game['away_score']}. Auto-synced from Scoreboard.";
                $stmt->execute([$user_id, date('Y-m-d', strtotime($game['created_at'])), $opponent, $notes]);
                $resultCreated = true;
            } catch (PDOException $e) {
                error_log('Scoreboard sync game plan: ' . $e->getMessage());
            }

            // Update player stats for goals and assists
            $statsUpdated = 0;
            if ($aw_team_id) {
                foreach ($goals as $g) {
                    // Only update stats for goals scored by the AW team side
                    $isAWGoal = ($game['home_team_id'] && $g['team'] === 'home') || ($game['away_team_id'] && $g['team'] === 'away');
                    if (!$isAWGoal) continue;

                    // Update scorer goals stat (match by jersey number)
                    if (!empty($g['scorer_number'])) {
                        try {
                            $pdo->prepare("
                                UPDATE athlete_stats SET goals = goals + 1, points = points + 1
                                WHERE team_id = ? AND user_id IN (
                                    SELECT id FROM users WHERE jersey_number = ? AND id IN (
                                        SELECT user_id FROM athlete_stats WHERE team_id = ?
                                    )
                                )
                            ")->execute([$aw_team_id, $g['scorer_number'], $aw_team_id]);
                            $statsUpdated++;
                        } catch (PDOException $e) { error_log('Stat update scorer: ' . $e->getMessage()); }
                    }

                    // Update assist 1
                    if (!empty($g['assist1_number'])) {
                        try {
                            $pdo->prepare("
                                UPDATE athlete_stats SET assists = assists + 1, points = points + 1
                                WHERE team_id = ? AND user_id IN (
                                    SELECT id FROM users WHERE jersey_number = ? AND id IN (
                                        SELECT user_id FROM athlete_stats WHERE team_id = ?
                                    )
                                )
                            ")->execute([$aw_team_id, $g['assist1_number'], $aw_team_id]);
                            $statsUpdated++;
                        } catch (PDOException $e) { error_log('Stat update assist1: ' . $e->getMessage()); }
                    }

                    // Update assist 2
                    if (!empty($g['assist2_number'])) {
                        try {
                            $pdo->prepare("
                                UPDATE athlete_stats SET assists = assists + 1, points = points + 1
                                WHERE team_id = ? AND user_id IN (
                                    SELECT id FROM users WHERE jersey_number = ? AND id IN (
                                        SELECT user_id FROM athlete_stats WHERE team_id = ?
                                    )
                                )
                            ")->execute([$aw_team_id, $g['assist2_number'], $aw_team_id]);
                            $statsUpdated++;
                        } catch (PDOException $e) { error_log('Stat update assist2: ' . $e->getMessage()); }
                    }
                }

                // Update PIM for penalties
                foreach ($penalties as $p) {
                    $isAWPenalty = ($game['home_team_id'] && $p['team'] === 'home') || ($game['away_team_id'] && $p['team'] === 'away');
                    if (!$isAWPenalty || empty($p['player_number'])) continue;
                    try {
                        $pdo->prepare("
                            UPDATE athlete_stats SET penalty_minutes = penalty_minutes + ?
                            WHERE team_id = ? AND user_id IN (
                                SELECT id FROM users WHERE jersey_number = ? AND id IN (
                                    SELECT user_id FROM athlete_stats WHERE team_id = ?
                                )
                            )
                        ")->execute([(int)$p['duration_minutes'], $aw_team_id, $p['player_number'], $aw_team_id]);
                    } catch (PDOException $e) { error_log('Stat update PIM: ' . $e->getMessage()); }
                }
            }

            // Mark game as synced
            $pdo->prepare("UPDATE scoreboard_games SET synced_to_gameplan = 1 WHERE id = ?")->execute([$game_id]);
            Auditor::log($pdo, $user_id, 'update', 'scoreboard_games', $game_id, ['action' => 'Synced to Game Plan', 'stats_updated' => $statsUpdated]);

            echo json_encode(['success' => true, 'result_created' => $resultCreated, 'stats_updated' => $statsUpdated]);
            break;

        // ── Set score directly ────────────────────────────
        case 'set_score':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $team = $_POST['team'] ?? '';
            $score = (int)($_POST['score'] ?? 0);
            if (!in_array($team, ['home', 'away']) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team or game']);
                exit();
            }
            $score = max(0, $score);
            $col = ($team === 'home') ? 'home_score' : 'away_score';
            $pdo->prepare("UPDATE scoreboard_games SET $col = ? WHERE id = ?")->execute([$score, $game_id]);
            $stmt = $pdo->prepare("SELECT home_score, away_score FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $scores = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'home_score' => (int)$scores['home_score'], 'away_score' => (int)$scores['away_score']]);
            break;

        // ── Set shots directly ────────────────────────────
        case 'set_shots':
            $game_id = (int)($_POST['game_id'] ?? 0);
            $team = $_POST['team'] ?? '';
            $shots = (int)($_POST['shots'] ?? 0);
            if (!in_array($team, ['home', 'away']) || $game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team or game']);
                exit();
            }
            $shots = max(0, $shots);
            $col = ($team === 'home') ? 'home_shots' : 'away_shots';
            $pdo->prepare("UPDATE scoreboard_games SET $col = ? WHERE id = ?")->execute([$shots, $game_id]);
            $stmt = $pdo->prepare("SELECT home_shots, away_shots FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $shotsData = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'home_shots' => (int)$shotsData['home_shots'], 'away_shots' => (int)$shotsData['away_shots']]);
            break;

        // ── Get game state (for polling) ──────────────────
        case 'get_state':
            $game_id = (int)($_POST['game_id'] ?? 0);
            if ($game_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid game']);
                exit();
            }
            $stmt = $pdo->prepare("SELECT * FROM scoreboard_games WHERE id = ?");
            $stmt->execute([$game_id]);
            $game = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$game) {
                echo json_encode(['success' => false, 'message' => 'Game not found']);
                exit();
            }
            echo json_encode(['success' => true, 'game' => $game]);
            break;

        // ── Save settings (admin-only) ────────────────────
        case 'save_settings':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $section = $_POST['section'] ?? '';
            $settingsToSave = [];

            switch ($section) {
                case 'spotify':
                    $settingsToSave['spotify_client_id'] = trim($_POST['spotify_client_id'] ?? '');
                    $settingsToSave['spotify_client_secret'] = trim($_POST['spotify_client_secret'] ?? '');
                    break;
                case 'apple_music':
                    $settingsToSave['apple_music_token'] = trim($_POST['apple_music_token'] ?? '');
                    $settingsToSave['apple_music_team_id'] = trim($_POST['apple_music_team_id'] ?? '');
                    break;
                case 'subsonic':
                    $settingsToSave['subsonic_url'] = trim($_POST['subsonic_url'] ?? '');
                    $settingsToSave['subsonic_username'] = trim($_POST['subsonic_username'] ?? '');
                    $settingsToSave['subsonic_password'] = trim($_POST['subsonic_password'] ?? '');
                    break;
                case 'network_speakers':
                    $names = $_POST['speaker_name'] ?? [];
                    $types = $_POST['speaker_type'] ?? [];
                    $hosts = $_POST['speaker_host'] ?? [];
                    $ports = $_POST['speaker_port'] ?? [];
                    $speakers = [];
                    for ($i = 0; $i < count($names); $i++) {
                        if (!empty(trim($names[$i]))) {
                            $speakers[] = [
                                'name' => trim($names[$i]),
                                'type' => $types[$i] ?? 'browser',
                                'host' => trim($hosts[$i] ?? ''),
                                'port' => (int)($ports[$i] ?? 11000)
                            ];
                        }
                    }
                    $settingsToSave['scoreboard_network_speakers'] = json_encode($speakers);
                    break;
                default:
                    echo json_encode(['success' => false, 'message' => 'Unknown settings section']);
                    exit();
            }

            foreach ($settingsToSave as $key => $value) {
                // Encrypt sensitive credentials before storage
                $settingValue = $value;
                if (in_array($key, ['spotify_client_secret', 'apple_music_token', 'subsonic_password']) && !empty($value)) {
                    require_once __DIR__ . '/lib/encryption.php';
                    if (FieldEncryption::isConfigured()) {
                        $settingValue = encryptPassword($value);
                    }
                }
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$key, $settingValue, $settingValue]);
            }
            Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'Scoreboard settings updated', 'section' => $section]);
            echo json_encode(['success' => true]);
            break;

        // ── Upload custom buzzer sound (admin-only, multiselect) ────────
        case 'upload_buzzer':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            // Support multiselect: buzzer_file may be an array of files
            $files = [];
            if (isset($_FILES['buzzer_file'])) {
                if (is_array($_FILES['buzzer_file']['name'])) {
                    for ($fi = 0; $fi < count($_FILES['buzzer_file']['name']); $fi++) {
                        if ($_FILES['buzzer_file']['error'][$fi] === UPLOAD_ERR_OK) {
                            $files[] = [
                                'name' => $_FILES['buzzer_file']['name'][$fi],
                                'tmp_name' => $_FILES['buzzer_file']['tmp_name'][$fi],
                                'size' => $_FILES['buzzer_file']['size'][$fi],
                            ];
                        }
                    }
                } elseif ($_FILES['buzzer_file']['error'] === UPLOAD_ERR_OK) {
                    $files[] = $_FILES['buzzer_file'];
                }
            }
            if (empty($files)) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                exit();
            }
            $allowedMimes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3', 'audio/x-wav'];
            $libStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'scoreboard_buzzer_library'");
            $libStmt->execute();
            $buzzerLibrary = json_decode($libStmt->fetchColumn() ?: '[]', true) ?: [];
            $lastUrl = '';
            $uploadedCount = 0;
            foreach ($files as $file) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes)) continue;
                if ($file['size'] > 10 * 1024 * 1024) continue;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp3';
                $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
                $filename = 'buzzer_' . time() . '_' . $uploadedCount . '.' . $ext;
                // Upload to RustFS
                $persist = persistUploadedFile($pdo, $file['tmp_name'], 'scoreboard/buzzer', $filename);
                if ($persist['success'] && !empty($persist['rustfs_url'])) {
                    $buzzerUrl = $persist['rustfs_url'];
                } else {
                    // Fallback to local storage
                    $uploadDir = __DIR__ . '/uploads/scoreboard/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $dest = $uploadDir . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $dest)) continue;
                    $buzzerUrl = '/uploads/scoreboard/' . $filename;
                }
                $buzzerLibrary[] = ['name' => basename($file['name'], '.' . $ext), 'url' => $buzzerUrl];
                $lastUrl = $buzzerUrl;
                $uploadedCount++;
            }
            if ($uploadedCount === 0) {
                echo json_encode(['success' => false, 'message' => 'No valid audio files uploaded']);
                exit();
            }
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_buzzer_library', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([json_encode($buzzerLibrary), json_encode($buzzerLibrary)]);
            // Set most recent upload as active buzzer
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_buzzer_url', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$lastUrl, $lastUrl]);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'Buzzer sound uploaded', 'count' => $uploadedCount]);
            echo json_encode(['success' => true, 'url' => $lastUrl, 'count' => $uploadedCount]);
            break;

        // ── Remove custom buzzer sound (admin-only) ────────
        case 'remove_buzzer':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'scoreboard_buzzer_url'");
            $stmt->execute();
            Auditor::log($pdo, $user_id, 'delete', 'system_settings', 0, ['action' => 'Buzzer sound removed']);
            echo json_encode(['success' => true]);
            break;

        // ── Upload custom goal horn sound (admin-only, multiselect) ────────
        case 'upload_horn':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            // Support multiselect: horn_file may be an array of files
            $files = [];
            if (isset($_FILES['horn_file'])) {
                if (is_array($_FILES['horn_file']['name'])) {
                    for ($fi = 0; $fi < count($_FILES['horn_file']['name']); $fi++) {
                        if ($_FILES['horn_file']['error'][$fi] === UPLOAD_ERR_OK) {
                            $files[] = [
                                'name' => $_FILES['horn_file']['name'][$fi],
                                'tmp_name' => $_FILES['horn_file']['tmp_name'][$fi],
                                'size' => $_FILES['horn_file']['size'][$fi],
                            ];
                        }
                    }
                } elseif ($_FILES['horn_file']['error'] === UPLOAD_ERR_OK) {
                    $files[] = $_FILES['horn_file'];
                }
            }
            if (empty($files)) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                exit();
            }
            $allowedMimes = ['audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/mp3', 'audio/x-wav'];
            $libStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'scoreboard_horn_library'");
            $libStmt->execute();
            $hornLibrary = json_decode($libStmt->fetchColumn() ?: '[]', true) ?: [];
            $lastUrl = '';
            $uploadedCount = 0;
            foreach ($files as $file) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                if (!in_array($mime, $allowedMimes)) continue;
                if ($file['size'] > 10 * 1024 * 1024) continue;
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'mp3';
                $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
                $filename = 'horn_' . time() . '_' . $uploadedCount . '.' . $ext;
                // Upload to RustFS
                $persist = persistUploadedFile($pdo, $file['tmp_name'], 'scoreboard/horn', $filename);
                if ($persist['success'] && !empty($persist['rustfs_url'])) {
                    $hornUrl = $persist['rustfs_url'];
                } else {
                    // Fallback to local storage
                    $uploadDir = __DIR__ . '/uploads/scoreboard/';
                    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                    $dest = $uploadDir . $filename;
                    if (!move_uploaded_file($file['tmp_name'], $dest)) continue;
                    $hornUrl = '/uploads/scoreboard/' . $filename;
                }
                $hornLibrary[] = ['name' => basename($file['name'], '.' . $ext), 'url' => $hornUrl];
                $lastUrl = $hornUrl;
                $uploadedCount++;
            }
            if ($uploadedCount === 0) {
                echo json_encode(['success' => false, 'message' => 'No valid audio files uploaded']);
                exit();
            }
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_horn_library', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([json_encode($hornLibrary), json_encode($hornLibrary)]);
            // Set most recent upload as active horn
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_horn_url', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$lastUrl, $lastUrl]);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'Goal horn sound uploaded', 'count' => $uploadedCount]);
            echo json_encode(['success' => true, 'url' => $lastUrl, 'count' => $uploadedCount]);
            break;

        // ── Remove custom goal horn sound (admin-only) ────────
        case 'remove_horn':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $stmt = $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'scoreboard_horn_url'");
            $stmt->execute();
            Auditor::log($pdo, $user_id, 'delete', 'system_settings', 0, ['action' => 'Goal horn sound removed']);
            echo json_encode(['success' => true]);
            break;

        // ── Select active sound from buzzer library (admin-only) ────────
        case 'select_buzzer':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $url = trim($_POST['url'] ?? '');
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'No sound URL provided']);
                exit();
            }
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_buzzer_url', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$url, $url]);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'Buzzer sound selected from library', 'url' => $url]);
            echo json_encode(['success' => true]);
            break;

        // ── Select active sound from horn library (admin-only) ────────
        case 'select_horn':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $url = trim($_POST['url'] ?? '');
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'No sound URL provided']);
                exit();
            }
            $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_horn_url', ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$url, $url]);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', 0, ['action' => 'Goal horn selected from library', 'url' => $url]);
            echo json_encode(['success' => true]);
            break;

        // ── Remove item from buzzer library (admin-only) ────────
        case 'remove_buzzer_library_item':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $url = trim($_POST['url'] ?? '');
            $libStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'scoreboard_buzzer_library'");
            $libStmt->execute();
            $buzzerLibrary = json_decode($libStmt->fetchColumn() ?: '[]', true) ?: [];
            $buzzerLibrary = array_values(array_filter($buzzerLibrary, function($item) use ($url) { return ($item['url'] ?? '') !== $url; }));
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_buzzer_library', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([json_encode($buzzerLibrary), json_encode($buzzerLibrary)]);
            // If active buzzer was removed, clear it
            $activeStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'scoreboard_buzzer_url'");
            $activeStmt->execute();
            if ($activeStmt->fetchColumn() === $url) {
                $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'scoreboard_buzzer_url'")->execute();
            }
            Auditor::log($pdo, $user_id, 'delete', 'system_settings', 0, ['action' => 'Buzzer library item removed', 'url' => $url]);
            echo json_encode(['success' => true]);
            break;

        // ── Remove item from horn library (admin-only) ────────
        case 'remove_horn_library_item':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $url = trim($_POST['url'] ?? '');
            $libStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'scoreboard_horn_library'");
            $libStmt->execute();
            $hornLibrary = json_decode($libStmt->fetchColumn() ?: '[]', true) ?: [];
            $hornLibrary = array_values(array_filter($hornLibrary, function($item) use ($url) { return ($item['url'] ?? '') !== $url; }));
            $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('scoreboard_horn_library', ?) ON DUPLICATE KEY UPDATE setting_value = ?")
                ->execute([json_encode($hornLibrary), json_encode($hornLibrary)]);
            // If active horn was removed, clear it
            $activeStmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'scoreboard_horn_url'");
            $activeStmt->execute();
            if ($activeStmt->fetchColumn() === $url) {
                $pdo->prepare("DELETE FROM system_settings WHERE setting_key = 'scoreboard_horn_url'")->execute();
            }
            Auditor::log($pdo, $user_id, 'delete', 'system_settings', 0, ['action' => 'Horn library item removed', 'url' => $url]);
            echo json_encode(['success' => true]);
            break;

        // ── Upload team logo (admin-only) ──────────────────
        case 'upload_team_logo':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            if (!isset($_FILES['logo_file']) || $_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
                echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
                exit();
            }
            $file = $_FILES['logo_file'];
            $allowedMimes = ['image/png', 'image/jpeg', 'image/svg+xml', 'image/webp'];
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            if (!in_array($mime, $allowedMimes)) {
                echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: PNG, JPG, SVG, WebP']);
                exit();
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                echo json_encode(['success' => false, 'message' => 'File too large (max 5MB)']);
                exit();
            }
            $uploadDir = __DIR__ . '/uploads/team_logos/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION) ?: 'png';
            $ext = preg_replace('/[^a-z0-9]/i', '', $ext);
            $teamId = $_POST['team_id'] ?? '';
            // Create new team if requested
            if ($teamId === 'new') {
                $newName = trim($_POST['new_team_name'] ?? '');
                if (empty($newName)) {
                    echo json_encode(['success' => false, 'message' => 'Team name required']);
                    exit();
                }
                $stmt = $pdo->prepare("INSERT INTO teams (team_name, status, created_at) VALUES (?, 'active', NOW())");
                $stmt->execute([$newName]);
                $teamId = (int)$pdo->lastInsertId();
                // Link to Game Plan if table exists
                try {
                    $checkTable = $pdo->query("SHOW TABLES LIKE 'vr_game_plans'");
                    if ($checkTable->rowCount() > 0) {
                        $pdo->prepare("INSERT INTO vr_game_plans (coach_id, plan_type, game_date, opponent, notes, created_at) VALUES (?, 'roster', NOW(), ?, ?, NOW())")
                            ->execute([$user_id, $newName, "Team created from Scoreboard settings. Auto-linked to Game Plan."]);
                    }
                } catch (PDOException $e) { error_log('Team gameplan link: ' . $e->getMessage()); }
            } else {
                $teamId = (int)$teamId;
            }
            if ($teamId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team']);
                exit();
            }
            $filename = 'team_' . $teamId . '_' . time() . '.' . $ext;
            // Upload to RustFS
            $persist = persistUploadedFile($pdo, $file['tmp_name'], 'team_logos', $filename);
            if ($persist['success'] && !empty($persist['rustfs_url'])) {
                $logoUrl = $persist['rustfs_url'];
            } else {
                // Fallback to local storage
                $uploadDir = __DIR__ . '/uploads/team_logos/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $dest = $uploadDir . $filename;
                if (!move_uploaded_file($file['tmp_name'], $dest)) {
                    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
                    exit();
                }
                $logoUrl = '/uploads/team_logos/' . $filename;
            }
            $pdo->prepare("UPDATE teams SET logo_url = ? WHERE id = ?")->execute([$logoUrl, $teamId]);
            Auditor::log($pdo, $user_id, 'update', 'teams', $teamId, ['action' => 'Logo uploaded', 'file' => $filename]);
            echo json_encode(['success' => true, 'url' => $logoUrl, 'team_id' => $teamId]);
            break;

        // ── Delete team logo (admin-only) ──────────────────
        case 'delete_team_logo':
            if (!$isAdmin) {
                http_response_code(403);
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                exit();
            }
            $teamId = (int)($_POST['team_id'] ?? 0);
            if ($teamId <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid team']);
                exit();
            }
            $pdo->prepare("UPDATE teams SET logo_url = NULL WHERE id = ?")->execute([$teamId]);
            Auditor::log($pdo, $user_id, 'update', 'teams', $teamId, ['action' => 'Team logo removed']);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (PDOException $e) {
    error_log('Scoreboard process error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
