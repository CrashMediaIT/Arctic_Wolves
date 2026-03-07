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

// Validate CSRF
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!CSRFProtection::validateToken($csrfToken)) {
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

        default:
            echo json_encode(['success' => false, 'message' => 'Unknown action']);
    }

} catch (PDOException $e) {
    error_log('Scoreboard process error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
