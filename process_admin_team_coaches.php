<?php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Check if this is an AJAX request
$isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// CSRF protection - validate token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

// Only admins can manage team coaches
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Not authorized']);
        exit();
    }
    header("Location: dashboard.php");
    exit();
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Determine redirect target - if a redirect_page was specified, use it
$redirect_page = 'admin_team_coaches';
if (!empty($_POST['redirect_page']) && $_POST['redirect_page'] === 'categories') {
    $redirect_page = 'categories&tab=seasons';
}

// Helper function to respond with JSON or redirect
function respond($success, $message, $redirectPage = 'admin_team_coaches') {
    global $isAjax;
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'message' => $message]);
        exit();
    } else {
        $url = "dashboard.php?page=" . urlencode($redirectPage);
        if ($success) {
            $url .= "&msg=" . urlencode(str_replace(' ', '_', strtolower($message)));
        } else {
            $url .= "&error=" . urlencode($message);
        }
        header("Location: " . $url);
        exit();
    }
}

try {
    switch ($action) {
        case 'create_season':
            $name = trim($_POST['season_name']);
            $start_date = $_POST['start_date'];
            $end_date = $_POST['end_date'];
            $is_active = intval($_POST['is_active']);
            
            // If activating, deactivate all other seasons
            if ($is_active) {
                $pdo->exec("UPDATE seasons SET is_active = 0");
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO seasons (name, start_date, end_date, is_active)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $start_date, $end_date, $is_active]);
            Auditor::log($pdo, $user_id, 'create', 'seasons', $pdo->lastInsertId(), ['action' => "Created season: $name"]);
            
            respond(true, "Season '$name' created successfully!", $redirect_page);
            break;
            
        case 'activate_season':
            $season_id = intval($_POST['season_id']);
            
            // Deactivate all seasons
            $pdo->exec("UPDATE seasons SET is_active = 0");
            
            // Activate selected season
            $stmt = $pdo->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
            $stmt->execute([$season_id]);
            Auditor::log($pdo, $user_id, 'update', 'seasons', $season_id, ['action' => 'Season activated']);
            
            respond(true, 'Season activated successfully!', $redirect_page);
            break;
            
        case 'delete_season':
            $season_id = intval($_POST['season_id']);
            
            // Check if season has assignments
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM team_coach_assignments WHERE season_id = ?");
            $check_stmt->execute([$season_id]);
            $count = $check_stmt->fetchColumn();
            
            if ($count > 0) {
                respond(false, 'Cannot delete season with existing assignments', $redirect_page);
            }
            
            $stmt = $pdo->prepare("DELETE FROM seasons WHERE id = ?");
            $stmt->execute([$season_id]);
            Auditor::log($pdo, $user_id, 'delete', 'seasons', $season_id, ['action' => 'Season deleted']);
            
            respond(true, 'Season deleted successfully!', $redirect_page);
            break;
            
        case 'create_assignment':
            $coach_id = intval($_POST['coach_id']);
            $team_id = intval($_POST['team_id']);
            $season_id = intval($_POST['season_id']);
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO team_coach_assignments (coach_id, team_id, season_id)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$coach_id, $team_id, $season_id]);
            Auditor::log($pdo, $user_id, 'create', 'team_coach_assignments', $pdo->lastInsertId(), ['action' => 'Coach assignment created']);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=assignment_created");
            break;
            
        case 'delete_assignment':
            $assignment_id = intval($_POST['assignment_id']);
            
            $stmt = $pdo->prepare("DELETE FROM team_coach_assignments WHERE id = ?");
            $stmt->execute([$assignment_id]);
            Auditor::log($pdo, $user_id, 'delete', 'team_coach_assignments', $assignment_id, ['action' => 'Coach assignment deleted']);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=assignment_deleted");
            break;

        case 'add_team_season':
            $team_id = intval($_POST['team_id']);
            $season_id = intval($_POST['season_id']);
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO team_seasons (team_id, season_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$team_id, $season_id]);
            Auditor::log($pdo, $user_id, 'create', 'team_seasons', $pdo->lastInsertId(), ['action' => 'Team season added']);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=team_season_added");
            break;

        case 'remove_team_season':
            $team_season_id = intval($_POST['team_season_id']);
            
            // Get team_id and season_id before deleting so we can clean up roster
            $ts_stmt = $pdo->prepare("SELECT team_id, season_id FROM team_seasons WHERE id = ?");
            $ts_stmt->execute([$team_season_id]);
            $ts = $ts_stmt->fetch();
            
            if ($ts) {
                // Remove roster entries for this team/season
                $pdo->prepare("DELETE FROM team_roster WHERE team_id = ? AND season_id = ?")
                    ->execute([$ts['team_id'], $ts['season_id']]);
                // Remove coach assignments for this team/season
                $pdo->prepare("DELETE FROM team_coach_assignments WHERE team_id = ? AND season_id = ?")
                    ->execute([$ts['team_id'], $ts['season_id']]);
            }
            
            $stmt = $pdo->prepare("DELETE FROM team_seasons WHERE id = ?");
            $stmt->execute([$team_season_id]);
            Auditor::log($pdo, $user_id, 'delete', 'team_seasons', $team_season_id, ['action' => 'Team season removed']);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=team_season_removed");
            break;

        case 'add_roster_athlete':
            $team_id = intval($_POST['team_id']);
            $season_id = intval($_POST['season_id']);
            $athlete_id = intval($_POST['athlete_id']);
            $jersey_number = !empty($_POST['jersey_number']) ? intval($_POST['jersey_number']) : null;
            $position = !empty($_POST['position']) ? trim($_POST['position']) : null;
            
            $stmt = $pdo->prepare("
                INSERT IGNORE INTO team_roster (team_id, athlete_id, season_id, jersey_number, position)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$team_id, $athlete_id, $season_id, $jersey_number, $position]);
            Auditor::log($pdo, $user_id, 'create', 'team_roster', $pdo->lastInsertId(), ['action' => 'Roster athlete added', 'athlete_id' => $athlete_id]);
            
            // Auto-sync to athlete_teams so assignment appears on profile/stats
            $team_info = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
            $team_info->execute([$team_id]);
            $team_row = $team_info->fetch();
            $team_name = $team_row ? $team_row['name'] : '';
            
            $season_info = $pdo->prepare("SELECT name FROM seasons WHERE id = ?");
            $season_info->execute([$season_id]);
            $season_row = $season_info->fetch();
            $season_name = $season_row ? $season_row['name'] : '';
            
            // Check if athlete_teams entry already exists for this team/season
            $existing = $pdo->prepare("SELECT id FROM athlete_teams WHERE (athlete_id = ? OR user_id = ?) AND team_id = ? AND season = ?");
            $existing->execute([$athlete_id, $athlete_id, $team_id, $season_name]);
            if (!$existing->fetch()) {
                $at_stmt = $pdo->prepare("
                    INSERT INTO athlete_teams (athlete_id, user_id, team_id, team_name, season, jersey_number, position, status, is_current, start_date)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1, CURDATE())
                ");
                $at_stmt->execute([$athlete_id, $athlete_id, $team_id, $team_name, $season_name, $jersey_number, $position]);
            }
            
            // Determine redirect - check if we came from team roster page
            $redirect = "dashboard.php?page=admin_team_coaches&msg=athlete_added";
            if (!empty($_POST['redirect_page']) && $_POST['redirect_page'] === 'team_roster') {
                $redirect = "dashboard.php?page=team_roster&team_id=" . $team_id . "&msg=athlete_added";
            }
            header("Location: " . $redirect);
            break;

        case 'remove_roster_athlete':
            $roster_id = intval($_POST['roster_id']);
            
            // Get roster details before deleting for athlete_teams cleanup
            $roster_info = $pdo->prepare("
                SELECT tr.athlete_id, tr.team_id, t.name as team_name, s.name as season_name
                FROM team_roster tr
                INNER JOIN teams t ON tr.team_id = t.id
                LEFT JOIN seasons s ON tr.season_id = s.id
                WHERE tr.id = ?
            ");
            $roster_info->execute([$roster_id]);
            $roster_row = $roster_info->fetch();
            
            $stmt = $pdo->prepare("DELETE FROM team_roster WHERE id = ?");
            $stmt->execute([$roster_id]);
            Auditor::log($pdo, $user_id, 'delete', 'team_roster', $roster_id, ['action' => 'Roster athlete removed']);
            
            // Remove corresponding athlete_teams entry
            if ($roster_row) {
                $pdo->prepare("
                    DELETE FROM athlete_teams 
                    WHERE (athlete_id = ? OR user_id = ?) 
                    AND team_id = ? 
                    AND season = ?
                ")->execute([
                    $roster_row['athlete_id'], 
                    $roster_row['athlete_id'], 
                    $roster_row['team_id'], 
                    $roster_row['season_name']
                ]);
            }
            
            // Determine redirect
            $redirect = "dashboard.php?page=admin_team_coaches&msg=athlete_removed";
            if (!empty($_POST['redirect_page']) && $_POST['redirect_page'] === 'team_roster' && $roster_row) {
                $redirect = "dashboard.php?page=team_roster&team_id=" . $roster_row['team_id'] . "&msg=athlete_removed";
            }
            header("Location: " . $redirect);
            break;
            
        default:
            header("Location: dashboard.php?page=admin_team_coaches&error=invalid_action");
            break;
    }
} catch (PDOException $e) {
    ErrorLogger::error("Team coach management error: " . $e->getMessage());
    header("Location: dashboard.php?page=admin_team_coaches&error=database_error");
}
exit();
?>
