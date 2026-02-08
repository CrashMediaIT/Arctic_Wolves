<?php
session_start();
require 'db_config.php';
require 'security.php';

// CSRF protection - validate token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

// Only admins can manage team coaches
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$action = $_POST['action'] ?? '';

// Determine redirect target - if coming from Resource Management (categories page), redirect back there
$referer = $_SERVER['HTTP_REFERER'] ?? '';
$redirect_page = 'admin_team_coaches';
if (strpos($referer, 'page=categories') !== false) {
    $redirect_page = 'categories&tab=seasons';
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
            
            header("Location: dashboard.php?page=$redirect_page&msg=season_created");
            break;
            
        case 'activate_season':
            $season_id = intval($_POST['season_id']);
            
            // Deactivate all seasons
            $pdo->exec("UPDATE seasons SET is_active = 0");
            
            // Activate selected season
            $stmt = $pdo->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
            $stmt->execute([$season_id]);
            
            header("Location: dashboard.php?page=$redirect_page&msg=season_activated");
            break;
            
        case 'delete_season':
            $season_id = intval($_POST['season_id']);
            
            // Check if season has assignments
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM team_coach_assignments WHERE season_id = ?");
            $check_stmt->execute([$season_id]);
            $count = $check_stmt->fetchColumn();
            
            if ($count > 0) {
                header("Location: dashboard.php?page=$redirect_page&error=season_has_assignments");
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM seasons WHERE id = ?");
            $stmt->execute([$season_id]);
            
            header("Location: dashboard.php?page=$redirect_page&msg=season_deleted");
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
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=assignment_created");
            break;
            
        case 'delete_assignment':
            $assignment_id = intval($_POST['assignment_id']);
            
            $stmt = $pdo->prepare("DELETE FROM team_coach_assignments WHERE id = ?");
            $stmt->execute([$assignment_id]);
            
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
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=athlete_added");
            break;

        case 'remove_roster_athlete':
            $roster_id = intval($_POST['roster_id']);
            
            $stmt = $pdo->prepare("DELETE FROM team_roster WHERE id = ?");
            $stmt->execute([$roster_id]);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=athlete_removed");
            break;
            
        default:
            header("Location: dashboard.php?page=admin_team_coaches&error=invalid_action");
            break;
    }
} catch (PDOException $e) {
    error_log("Team coach management error: " . $e->getMessage());
    header("Location: dashboard.php?page=admin_team_coaches&error=database_error");
}
exit();
?>
