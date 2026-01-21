<?php
session_start();
require 'db_config.php';
require 'security.php';

// CSRF protection
if (!isset($_POST['csrf_token']) || !csrfTokenValidate($_POST['csrf_token'])) {
    die("CSRF token validation failed");
}

// Only admins can manage team coaches
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

$action = $_POST['action'] ?? '';

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
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=season_created");
            break;
            
        case 'activate_season':
            $season_id = intval($_POST['season_id']);
            
            // Deactivate all seasons
            $pdo->exec("UPDATE seasons SET is_active = 0");
            
            // Activate selected season
            $stmt = $pdo->prepare("UPDATE seasons SET is_active = 1 WHERE id = ?");
            $stmt->execute([$season_id]);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=season_activated");
            break;
            
        case 'delete_season':
            $season_id = intval($_POST['season_id']);
            
            // Check if season has assignments
            $check_stmt = $pdo->prepare("SELECT COUNT(*) FROM team_coach_assignments WHERE season_id = ?");
            $check_stmt->execute([$season_id]);
            $count = $check_stmt->fetchColumn();
            
            if ($count > 0) {
                header("Location: dashboard.php?page=admin_team_coaches&error=season_has_assignments");
                exit();
            }
            
            $stmt = $pdo->prepare("DELETE FROM seasons WHERE id = ?");
            $stmt->execute([$season_id]);
            
            header("Location: dashboard.php?page=admin_team_coaches&msg=season_deleted");
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
