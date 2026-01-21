<?php
/**
 * Process Coach Termination
 * Comprehensive coach termination with automatic backup and data transfer
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

// Set security headers
setSecurityHeaders();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Validate CSRF token
checkCsrfToken();

$user_id = $_SESSION['user_id'];

try {
    $coach_to_terminate = intval($_POST['coach_to_terminate']);
    $transfer_to_coach = intval($_POST['transfer_to_coach']);
    $termination_reason = trim($_POST['termination_reason']);
    
    // Validation
    if ($coach_to_terminate === $transfer_to_coach) {
        throw new Exception('Cannot transfer to the same coach');
    }
    
    if (empty($termination_reason)) {
        throw new Exception('Termination reason is required');
    }
    
    // Verify both coaches exist
    $coach_stmt = $pdo->prepare("
        SELECT id, CONCAT(first_name, ' ', last_name) as name, role 
        FROM users 
        WHERE id IN (?, ?) AND role IN ('coach', 'coach_plus', 'team_coach')
    ");
    $coach_stmt->execute([$coach_to_terminate, $transfer_to_coach]);
    $coaches = $coach_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($coaches) !== 2) {
        throw new Exception('One or both coaches not found');
    }
    
    $terminated_coach = null;
    $new_coach = null;
    foreach ($coaches as $coach) {
        if ($coach['id'] == $coach_to_terminate) {
            $terminated_coach = $coach;
        } else {
            $new_coach = $coach;
        }
    }
    
    // Step 1: Create automatic database backup
    $backup_file = null;
    try {
        $backup_dir = __DIR__ . '/cache/termination_backups/';
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }
        
        $backup_file = sprintf(
            'termination_backup_%s_%s.sql',
            date('Y-m-d_H-i-s'),
            $coach_to_terminate
        );
        $backup_path = $backup_dir . $backup_file;
        
        // Get database credentials
        $db_host = getenv('DB_HOST') ?: 'localhost';
        $db_name = getenv('DB_NAME') ?: 'crashhockey';
        $db_user = getenv('DB_USER');
        $db_pass = getenv('DB_PASS');
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s %s > %s 2>&1',
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name),
            escapeshellarg($backup_path)
        );
        
        exec($command, $output, $return_code);
        
        if ($return_code !== 0 || !file_exists($backup_path)) {
            throw new Exception('Backup creation failed: ' . implode("\n", $output));
        }
    } catch (Exception $e) {
        error_log("Backup creation warning: " . $e->getMessage());
        // Continue anyway - backup is a safety measure but not critical
        $backup_file = 'Backup creation skipped: ' . $e->getMessage();
    }
    
    // Step 2: Start transaction for data transfer
    $pdo->beginTransaction();
    
    try {
        // Transfer managed athletes
        $transfer_athletes = $pdo->prepare("
            UPDATE managed_athletes 
            SET parent_id = ? 
            WHERE parent_id = ?
        ");
        $athletes_transferred = $transfer_athletes->execute([$transfer_to_coach, $coach_to_terminate]);
        $athletes_count = $transfer_athletes->rowCount();
        
        // Transfer goals (created_by)
        $transfer_goals = $pdo->prepare("
            UPDATE goals 
            SET created_by = ? 
            WHERE created_by = ?
        ");
        $transfer_goals->execute([$transfer_to_coach, $coach_to_terminate]);
        $goals_count = $transfer_goals->rowCount();
        
        // Transfer athlete evaluations
        $transfer_evals = $pdo->prepare("
            UPDATE athlete_evaluations 
            SET coach_id = ? 
            WHERE coach_id = ?
        ");
        $transfer_evals->execute([$transfer_to_coach, $coach_to_terminate]);
        $evals_count = $transfer_evals->rowCount();
        
        // Transfer goal evaluations
        $transfer_goal_evals = $pdo->prepare("
            UPDATE goal_evaluations 
            SET created_by = ? 
            WHERE created_by = ?
        ");
        $transfer_goal_evals->execute([$transfer_to_coach, $coach_to_terminate]);
        $goal_evals_count = $transfer_goal_evals->rowCount();
        
        // Transfer practice plans
        $transfer_plans = $pdo->prepare("
            UPDATE practice_plans 
            SET created_by = ? 
            WHERE created_by = ?
        ");
        $transfer_plans->execute([$transfer_to_coach, $coach_to_terminate]);
        $plans_count = $transfer_plans->rowCount();
        
        // Transfer sessions created by coach
        $transfer_sessions = $pdo->prepare("
            UPDATE sessions 
            SET created_by = ? 
            WHERE created_by = ?
        ");
        $transfer_sessions->execute([$transfer_to_coach, $coach_to_terminate]);
        $sessions_count = $transfer_sessions->rowCount();
        
        // Soft delete the coach user
        $delete_coach = $pdo->prepare("
            UPDATE users 
            SET is_deleted = 1, 
                deleted_at = NOW(), 
                deleted_by = ?,
                email = CONCAT(email, '_DELETED_', id)
            WHERE id = ?
        ");
        $delete_coach->execute([$user_id, $coach_to_terminate]);
        
        // Create comprehensive audit log
        $audit_data = [
            'action' => 'COACH_TERMINATION',
            'terminated_coach_id' => $coach_to_terminate,
            'terminated_coach_name' => $terminated_coach['name'],
            'transfer_to_coach_id' => $transfer_to_coach,
            'transfer_to_coach_name' => $new_coach['name'],
            'termination_reason' => $termination_reason,
            'athletes_transferred' => $athletes_count,
            'goals_transferred' => $goals_count,
            'evaluations_transferred' => $evals_count,
            'goal_evaluations_transferred' => $goal_evals_count,
            'practice_plans_transferred' => $plans_count,
            'sessions_transferred' => $sessions_count,
            'backup_file' => $backup_file,
            'terminated_by' => $user_id,
            'terminated_at' => date('Y-m-d H:i:s')
        ];
        
        $audit_stmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
            VALUES (?, 'TERMINATE', 'users', ?, NULL, ?, ?, ?, NOW())
        ");
        
        $audit_stmt->execute([
            $user_id,
            $coach_to_terminate,
            json_encode($audit_data),
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
        
        // Create notification for the new coach
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, read_status, created_at)
            VALUES (?, 'admin_action', 'Athletes Transferred', ?, 0, NOW())
        ");
        
        $notification_message = sprintf(
            'You have been assigned %d athlete(s) from %s (account terminated). Please review your athlete roster.',
            $athletes_count,
            $terminated_coach['name']
        );
        
        $notification_stmt->execute([$transfer_to_coach, $notification_message]);
        
        // Commit transaction
        $pdo->commit();
        
        $success_message = sprintf(
            'Coach %s has been successfully terminated. ' .
            'Transferred: %d athlete(s), %d goal(s), %d evaluation(s), %d practice plan(s), %d session(s) to %s',
            $terminated_coach['name'],
            $athletes_count,
            $goals_count,
            $evals_count,
            $plans_count,
            $sessions_count,
            $new_coach['name']
        );
        
        echo json_encode([
            'success' => true,
            'message' => $success_message,
            'backup_file' => $backup_file,
            'transfers' => [
                'athletes' => $athletes_count,
                'goals' => $goals_count,
                'evaluations' => $evals_count,
                'goal_evaluations' => $goal_evals_count,
                'practice_plans' => $plans_count,
                'sessions' => $sessions_count
            ]
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
