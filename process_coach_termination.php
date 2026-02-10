<?php
/**
 * Process Coach Termination
 * Comprehensive coach termination with automatic backup and data transfer
 * Also handles HR termination form submissions with Nextcloud document upload
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once 'cloud_config.php';

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

// Handle HR Termination Form (create action)
if ($action === 'create') {
    try {
        $staff_user_id = intval($_POST['user_id']);
        $termination_date = trim($_POST['termination_date']);
        $termination_type = trim($_POST['termination_type']);
        $reason_category = trim($_POST['reason_category']);
        $notes = trim($_POST['notes']);
        $notice_period = !empty($_POST['notice_period']) ? intval($_POST['notice_period']) : null;
        $checklist = isset($_POST['checklist']) ? $_POST['checklist'] : [];
        $final_comments = trim($_POST['final_comments'] ?? '');
        
        // Validation
        if (empty($staff_user_id)) {
            throw new Exception('Staff member must be selected');
        }
        
        if (empty($termination_date)) {
            throw new Exception('Termination date is required');
        }
        
        if (empty($termination_type)) {
            throw new Exception('Termination type is required');
        }
        
        if (empty($notes)) {
            throw new Exception('Detailed reason/notes is required');
        }
        
        // Verify staff member exists and is eligible for termination (admin, coach, health_coach, team_coach)
        $staff_stmt = $pdo->prepare("
            SELECT id, first_name, last_name, role, email 
            FROM users 
            WHERE id = ? AND role IN ('admin', 'coach', 'health_coach', 'team_coach') AND is_active = 1
        ");
        $staff_stmt->execute([$staff_user_id]);
        $staff_member = $staff_stmt->fetch(PDO::FETCH_ASSOC);
        $staff_member = decryptUserRow($staff_member);
        if ($staff_member) {
            $staff_member['name'] = $staff_member['first_name'] . ' ' . $staff_member['last_name'];
        }
        
        if (!$staff_member) {
            throw new Exception('Staff member not found or not eligible for termination');
        }
        
        // Determine status based on termination date
        $term_date = new DateTime($termination_date);
        $today = new DateTime();
        $status = ($term_date > $today) ? 'scheduled' : 'pending';
        
        // Start transaction
        $pdo->beginTransaction();
        
        try {
            // Insert termination record
            $insert_stmt = $pdo->prepare("
                INSERT INTO employee_terminations 
                (user_id, termination_date, termination_type, reason_category, reason, notice_period_days, 
                 offboarding_checklist, final_comments, processed_by, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $insert_stmt->execute([
                $staff_user_id,
                $termination_date,
                $termination_type,
                $reason_category,
                $notes,
                $notice_period,
                json_encode($checklist),
                $final_comments,
                $user_id,
                $status
            ]);
            
            $termination_id = $pdo->lastInsertId();
            
            // Handle Nextcloud upload
            $nextcloud_folder = null;
            $uploaded_docs = [];
            
            try {
                $nc_settings = getNextcloudSettings($pdo);
                
                if (!empty($nc_settings['nextcloud_url']) && !empty($nc_settings['nextcloud_username'])) {
                    // Upload documents to Nextcloud
                    if (!empty($_FILES['documents']) && !empty($_FILES['documents']['name'][0])) {
                        $upload_result = uploadTerminationDocuments(
                            $pdo, 
                            $nc_settings, 
                            $staff_member['name'], 
                            $termination_date, 
                            $_FILES['documents']
                        );
                        
                        if ($upload_result['success']) {
                            $nextcloud_folder = $upload_result['folder_path'];
                            $uploaded_docs = $upload_result['uploaded_files'];
                            
                            // Save document records
                            foreach ($uploaded_docs as $doc) {
                                $doc_stmt = $pdo->prepare("
                                    INSERT INTO termination_documents 
                                    (termination_id, document_name, document_type, nextcloud_path, file_size, uploaded_by, created_at)
                                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                                ");
                                $doc_stmt->execute([
                                    $termination_id,
                                    $doc['original_name'],
                                    $doc['content_type'],
                                    $doc['remote_path'],
                                    $doc['file_size'],
                                    $user_id
                                ]);
                            }
                        }
                    }
                    
                    // Export termination data to Nextcloud
                    $termination_data = [
                        'employee_name' => $staff_member['name'],
                        'email' => $staff_member['email'],
                        'role' => $staff_member['role'],
                        'termination_date' => $termination_date,
                        'termination_type' => $termination_type,
                        'reason_category' => $reason_category,
                        'notes' => $notes,
                        'notice_period' => $notice_period,
                        'checklist' => $checklist,
                        'final_comments' => $final_comments,
                        'processed_by' => $user_id,
                        'termination_id' => $termination_id
                    ];
                    
                    $export_result = exportTerminationData(
                        $pdo, 
                        $nc_settings, 
                        $termination_data, 
                        $staff_member['name'], 
                        $termination_date
                    );
                    
                    if ($export_result['success'] && empty($nextcloud_folder)) {
                        $nextcloud_folder = $export_result['folder_path'];
                    }
                }
            } catch (Exception $nc_error) {
                error_log("Nextcloud upload error: " . $nc_error->getMessage());
                // Continue without Nextcloud - not critical
            }
            
            // Update termination record with Nextcloud folder path
            if ($nextcloud_folder) {
                $update_stmt = $pdo->prepare("UPDATE employee_terminations SET nextcloud_folder = ? WHERE id = ?");
                $update_stmt->execute([$nextcloud_folder, $termination_id]);
            }
            
            // Create audit log
            $audit_data = [
                'action' => 'EMPLOYEE_TERMINATION_CREATED',
                'staff_id' => $staff_user_id,
                'staff_name' => $staff_member['name'],
                'termination_type' => $termination_type,
                'reason_category' => $reason_category,
                'termination_date' => $termination_date,
                'nextcloud_folder' => $nextcloud_folder,
                'documents_uploaded' => count($uploaded_docs),
                'created_by' => $user_id,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $audit_stmt = $pdo->prepare("
                INSERT INTO audit_logs 
                (user_id, action_type, table_name, record_id, old_values, new_values, ip_address, user_agent, created_at)
                VALUES (?, 'CREATE', 'employee_terminations', ?, NULL, ?, ?, ?, NOW())
            ");
            
            $audit_stmt->execute([
                $user_id,
                $termination_id,
                json_encode($audit_data),
                $_SERVER['REMOTE_ADDR'] ?? null,
                $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Commit transaction
            $pdo->commit();
            
            // Redirect back to termination page with success message
            $_SESSION['flash_message'] = 'Termination record created successfully for ' . $staff_member['name'];
            $_SESSION['flash_type'] = 'success';
            header('Location: dashboard.php?page=termination');
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_message'] = 'Error: ' . $e->getMessage();
        $_SESSION['flash_type'] = 'error';
        header('Location: dashboard.php?page=termination');
        exit;
    }
}

// Original coach-to-coach termination logic (for admin_coach_termination.php)
// Only run if coach termination fields are provided
if (empty($_POST['coach_to_terminate']) && empty($_POST['transfer_to_coach'])) {
    // No coach termination data, exit
    exit;
}

try {
    $coach_to_terminate = intval($_POST['coach_to_terminate'] ?? 0);
    $transfer_to_coach = intval($_POST['transfer_to_coach'] ?? 0);
    $termination_reason = trim($_POST['termination_reason'] ?? '');
    
    // Validation
    if (empty($coach_to_terminate) || empty($transfer_to_coach)) {
        throw new Exception('Both coaches must be selected');
    }
    
    if ($coach_to_terminate === $transfer_to_coach) {
        throw new Exception('Cannot transfer to the same coach. Please select a different coach to receive the athletes and data.');
    }
    
    if (empty($termination_reason)) {
        throw new Exception('Termination reason is required');
    }
    
    // Verify both coaches exist
    $coach_stmt = $pdo->prepare("
        SELECT id, first_name, last_name, role 
        FROM users 
        WHERE id IN (?, ?) AND role IN ('coach', 'coach_plus', 'team_coach')
    ");
    $coach_stmt->execute([$coach_to_terminate, $transfer_to_coach]);
    $coaches = $coach_stmt->fetchAll(PDO::FETCH_ASSOC);
    $coaches = decryptUserRows($coaches);
    foreach ($coaches as &$c) {
        $c['name'] = $c['first_name'] . ' ' . $c['last_name'];
    }
    unset($c);
    
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
        $db_name = getenv('DB_NAME') ?: 'arctic_wolves';
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
