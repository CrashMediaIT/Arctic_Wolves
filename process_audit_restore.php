<?php
/**
 * Process Audit Log Restore Operations
 * Handles restoration of data from audit logs
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
$action = $_POST['action'] ?? '';

try {
    if ($action === 'restore') {
        $log_id = intval($_POST['log_id']);
        
        // Get the audit log
        $log_stmt = $pdo->prepare("
            SELECT * FROM audit_logs WHERE id = ?
        ");
        $log_stmt->execute([$log_id]);
        $log = $log_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$log) {
            throw new Exception('Audit log not found');
        }
        
        // Only allow restoration of UPDATE and DELETE actions
        if (!in_array($log['action_type'], ['UPDATE', 'DELETE'])) {
            throw new Exception('Cannot restore INSERT actions');
        }
        
        if (!$log['old_values']) {
            throw new Exception('No old values to restore');
        }
        
        $old_values = json_decode($log['old_values'], true);
        $table_name = $log['table_name'];
        $record_id = $log['record_id'];
        
        // Validate table name (whitelist approach for security)
        $allowed_tables = [
            'users', 'sessions', 'bookings', 'packages', 'goals', 'goal_steps',
            'athlete_evaluations', 'eval_skills', 'eval_categories', 'expenses',
            'mileage_logs', 'refunds', 'discount_codes', 'managed_athletes',
            'athlete_teams', 'athlete_stats', 'testing_results', 'notifications',
            'goal_evaluations', 'goal_eval_steps', 'practice_plans', 'session_types',
            'age_groups', 'skill_levels', 'expense_categories', 'user_credits',
            'cron_jobs', 'backup_jobs', 'report_schedules'
        ];
        
        if (!in_array($table_name, $allowed_tables)) {
            throw new Exception('Cannot restore this table type');
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            if ($log['action_type'] === 'DELETE') {
                // For DELETE, we need to re-insert the record
                $columns = array_keys($old_values);
                $placeholders = array_fill(0, count($columns), '?');
                $values = array_values($old_values);
                
                $insert_sql = sprintf(
                    "INSERT INTO `%s` (`%s`) VALUES (%s)",
                    $table_name,
                    implode('`, `', $columns),
                    implode(', ', $placeholders)
                );
                
                $insert_stmt = $pdo->prepare($insert_sql);
                $insert_stmt->execute($values);
                
                $message = "Record restored (re-inserted) from deletion";
                
            } else if ($log['action_type'] === 'UPDATE') {
                // For UPDATE, restore old values
                $set_clauses = [];
                $values = [];
                
                foreach ($old_values as $column => $value) {
                    if ($column !== 'id') { // Don't update the ID
                        $set_clauses[] = "`$column` = ?";
                        $values[] = $value;
                    }
                }
                
                $values[] = $record_id;
                
                $update_sql = sprintf(
                    "UPDATE `%s` SET %s WHERE id = ?",
                    $table_name,
                    implode(', ', $set_clauses)
                );
                
                $update_stmt = $pdo->prepare($update_sql);
                $update_stmt->execute($values);
                
                $message = "Record restored to previous state";
            }
            
            // Create audit log for the restore action
            $audit_stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, 'RESTORE', ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $current_values = null;
            if ($log['action_type'] === 'UPDATE') {
                // Get current values before restore
                $current_stmt = $pdo->prepare("SELECT * FROM `$table_name` WHERE id = ?");
                $current_stmt->execute([$record_id]);
                $current_values = json_encode($current_stmt->fetch(PDO::FETCH_ASSOC));
            }
            
            $audit_stmt->execute([
                $user_id,
                $table_name,
                $record_id,
                $current_values, // old (current state before restore)
                $log['old_values'], // new (restored state)
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            $pdo->commit();
            
            echo json_encode([
                'success' => true,
                'message' => $message
            ]);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } else {
        throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
