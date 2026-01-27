<?php
/**
 * Process Health Plan Assignments
 * Handles assigning workout and nutrition plans to athletes
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

session_start();

// Check authentication
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Only allow health coaches and admins
if (!in_array($user_role, ['health_coach', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Verify CSRF token
if (!CSRFProtection::verifyToken($_POST['csrf_token'] ?? '')) {
    header('Location: dashboard.php?page=health_coach_roster&error=csrf_failed');
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'assign_workout') {
    $athlete_id = intval($_POST['athlete_id'] ?? 0);
    $workout_plan_id = intval($_POST['workout_plan_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($athlete_id <= 0 || $workout_plan_id <= 0) {
        header('Location: dashboard.php?page=health_coach_roster&error=assignment_failed');
        exit;
    }
    
    try {
        // Check if assignment already exists
        $check_stmt = $pdo->prepare("
            SELECT id FROM athlete_workout_assignments 
            WHERE athlete_id = ? AND workout_plan_id = ? AND status = 'active'
        ");
        $check_stmt->execute([$athlete_id, $workout_plan_id]);
        
        if ($check_stmt->fetch()) {
            // Update existing assignment
            $update_stmt = $pdo->prepare("
                UPDATE athlete_workout_assignments 
                SET start_date = ?, notes = ?, assigned_by_coach_id = ?, assigned_date = NOW()
                WHERE athlete_id = ? AND workout_plan_id = ? AND status = 'active'
            ");
            $update_stmt->execute([$start_date, $notes, $user_id, $athlete_id, $workout_plan_id]);
        } else {
            // Create new assignment
            $insert_stmt = $pdo->prepare("
                INSERT INTO athlete_workout_assignments 
                (athlete_id, workout_plan_id, assigned_by_coach_id, start_date, notes, status, assigned_date) 
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $insert_stmt->execute([$athlete_id, $workout_plan_id, $user_id, $start_date, $notes]);
        }
        
        header('Location: dashboard.php?page=health_coach_roster&status=plan_assigned');
        exit;
        
    } catch (PDOException $e) {
        error_log("Workout assignment error: " . $e->getMessage());
        header('Location: dashboard.php?page=health_coach_roster&error=assignment_failed');
        exit;
    }
    
} elseif ($action === 'assign_nutrition') {
    $athlete_id = intval($_POST['athlete_id'] ?? 0);
    $nutrition_plan_id = intval($_POST['nutrition_plan_id'] ?? 0);
    $start_date = $_POST['start_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    
    if ($athlete_id <= 0 || $nutrition_plan_id <= 0) {
        header('Location: dashboard.php?page=health_coach_roster&error=assignment_failed');
        exit;
    }
    
    try {
        // Check if assignment already exists
        $check_stmt = $pdo->prepare("
            SELECT id FROM athlete_nutrition_assignments 
            WHERE athlete_id = ? AND nutrition_plan_id = ? AND status = 'active'
        ");
        $check_stmt->execute([$athlete_id, $nutrition_plan_id]);
        
        if ($check_stmt->fetch()) {
            // Update existing assignment
            $update_stmt = $pdo->prepare("
                UPDATE athlete_nutrition_assignments 
                SET start_date = ?, notes = ?, assigned_by_coach_id = ?, assigned_date = NOW()
                WHERE athlete_id = ? AND nutrition_plan_id = ? AND status = 'active'
            ");
            $update_stmt->execute([$start_date, $notes, $user_id, $athlete_id, $nutrition_plan_id]);
        } else {
            // Create new assignment
            $insert_stmt = $pdo->prepare("
                INSERT INTO athlete_nutrition_assignments 
                (athlete_id, nutrition_plan_id, assigned_by_coach_id, start_date, notes, status, assigned_date) 
                VALUES (?, ?, ?, ?, ?, 'active', NOW())
            ");
            $insert_stmt->execute([$athlete_id, $nutrition_plan_id, $user_id, $start_date, $notes]);
        }
        
        header('Location: dashboard.php?page=health_coach_roster&status=plan_assigned');
        exit;
        
    } catch (PDOException $e) {
        error_log("Nutrition assignment error: " . $e->getMessage());
        header('Location: dashboard.php?page=health_coach_roster&error=assignment_failed');
        exit;
    }
    
} else {
    header('Location: dashboard.php?page=health_coach_roster');
    exit;
}
