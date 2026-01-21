<?php
/**
 * Process Goal-Based Evaluation Approval Workflow
 * Handles approval requests, notifications, and status updates
 */

session_start();
require 'db_config.php';
require 'security.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$is_coach = ($user_role === 'coach' || $user_role === 'coach_plus' || $user_role === 'admin');

/**
 * Send notification email
 */
function sendApprovalNotification($pdo, $to_user_id, $type, $details) {
    try {
        // Get user email
        $user_stmt = $pdo->prepare("SELECT email, first_name, email_notifications FROM users WHERE id = ?");
        $user_stmt->execute([$to_user_id]);
        $user = $user_stmt->fetch();
        
        if (!$user || !$user['email_notifications']) {
            return false;
        }
        
        // Create notification in database
        $notification_stmt = $pdo->prepare("
            INSERT INTO notifications 
            (user_id, type, title, message, read_status, created_at)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        
        $message = '';
        $title = '';
        $step_title = htmlspecialchars($details['step_title'], ENT_QUOTES, 'UTF-8');
        $note = isset($details['note']) ? htmlspecialchars($details['note'], ENT_QUOTES, 'UTF-8') : '';
        
        switch ($type) {
            case 'approval_requested':
                $title = 'Approval Request';
                $message = "New approval request for: {$step_title}";
                break;
            case 'approval_approved':
                $title = 'Step Approved';
                $message = "Your step '{$step_title}' has been approved!";
                break;
            case 'approval_rejected':
                $title = 'Step Not Approved';
                $message = "Your step '{$step_title}' was not approved. " . $note;
                break;
        }
        
        $notification_stmt->execute([$to_user_id, $type, $title, $message]);
        
        // Send email using mailer.php
        require_once 'mailer.php';
        
        $email_body = "
            <h2>{$title}</h2>
            <p>Hello {$user['first_name']},</p>
            <p>{$message}</p>
            <hr>
            <p><strong>Step:</strong> {$step_title}</p>
        ";
        
        if ($note) {
            $email_body .= "<p><strong>Note:</strong> {$note}</p>";
        }
        
        $email_body .= "
            <p>Please log in to your account to view more details.</p>
            <p><a href='" . ($_SERVER['REQUEST_SCHEME'] ?? 'https') . "://" . ($_SERVER['HTTP_HOST'] ?? 'crashhockey.com') . "/dashboard.php?page=evaluations_goals'>View Evaluations</a></p>
        ";
        
        sendEmail($user['email'], $title, $email_body);
        
        return true;
    } catch (Exception $e) {
        error_log("Error sending notification: " . $e->getMessage());
        return false;
    }
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'request_approval':
                $step_id = intval($_POST['step_id']);
                
                // Get step and evaluation info
                $step_stmt = $pdo->prepare("
                    SELECT ges.*, ge.athlete_id, ge.created_by,
                           CONCAT(u.first_name, ' ', u.last_name) as athlete_name
                    FROM goal_eval_steps ges
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    JOIN users u ON ge.athlete_id = u.id
                    WHERE ges.id = ?
                ");
                $step_stmt->execute([$step_id]);
                $step = $step_stmt->fetch();
                
                if (!$step) {
                    echo json_encode(['success' => false, 'message' => 'Step not found']);
                    exit;
                }
                
                // Check if user is the athlete
                if ($step['athlete_id'] != $user_id) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                // Check if step is completed
                if (!$step['is_completed']) {
                    echo json_encode(['success' => false, 'message' => 'Step must be completed before requesting approval']);
                    exit;
                }
                
                // Check if approval request already exists
                $existing_stmt = $pdo->prepare("
                    SELECT id FROM goal_eval_approvals 
                    WHERE goal_eval_step_id = ? AND status = 'pending'
                ");
                $existing_stmt->execute([$step_id]);
                
                if ($existing_stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Approval request already pending']);
                    exit;
                }
                
                // Create approval request
                $approval_stmt = $pdo->prepare("
                    INSERT INTO goal_eval_approvals 
                    (goal_eval_step_id, requested_by, status, created_at, updated_at)
                    VALUES (?, ?, 'pending', NOW(), NOW())
                ");
                $approval_stmt->execute([$step_id, $user_id]);
                
                // Send notification to coach who created the evaluation
                sendApprovalNotification($pdo, $step['created_by'], 'approval_requested', [
                    'step_title' => $step['title'],
                    'athlete_name' => $step['athlete_name']
                ]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Approval requested successfully'
                ]);
                break;
                
            case 'approve_step':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can approve steps']);
                    exit;
                }
                
                $step_id = intval($_POST['step_id']);
                $approval_note = trim($_POST['approval_note'] ?? '');
                
                // Get step and approval info
                $step_stmt = $pdo->prepare("
                    SELECT ges.*, ge.athlete_id, gea.id as approval_id, gea.requested_by
                    FROM goal_eval_steps ges
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    LEFT JOIN goal_eval_approvals gea ON gea.goal_eval_step_id = ges.id AND gea.status = 'pending'
                    WHERE ges.id = ?
                ");
                $step_stmt->execute([$step_id]);
                $step = $step_stmt->fetch();
                
                if (!$step) {
                    echo json_encode(['success' => false, 'message' => 'Step not found']);
                    exit;
                }
                
                // Update step as approved
                $update_stmt = $pdo->prepare("
                    UPDATE goal_eval_steps 
                    SET is_approved = 1, 
                        approved_by = ?, 
                        approved_at = NOW()
                    WHERE id = ?
                ");
                $update_stmt->execute([$user_id, $step_id]);
                
                // Update approval request if exists
                if ($step['approval_id']) {
                    $approval_update = $pdo->prepare("
                        UPDATE goal_eval_approvals 
                        SET status = 'approved', 
                            approved_by = ?, 
                            approval_note = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $approval_update->execute([$user_id, $approval_note, $step['approval_id']]);
                    
                    // Send notification to athlete
                    if ($step['requested_by']) {
                        sendApprovalNotification($pdo, $step['requested_by'], 'approval_approved', [
                            'step_title' => $step['title'],
                            'note' => $approval_note
                        ]);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Step approved successfully'
                ]);
                break;
                
            case 'reject_step':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can reject steps']);
                    exit;
                }
                
                $step_id = intval($_POST['step_id']);
                $rejection_note = trim($_POST['rejection_note'] ?? '');
                
                // Get step and approval info
                $step_stmt = $pdo->prepare("
                    SELECT ges.*, ge.athlete_id, gea.id as approval_id, gea.requested_by
                    FROM goal_eval_steps ges
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    LEFT JOIN goal_eval_approvals gea ON gea.goal_eval_step_id = ges.id AND gea.status = 'pending'
                    WHERE ges.id = ?
                ");
                $step_stmt->execute([$step_id]);
                $step = $step_stmt->fetch();
                
                if (!$step) {
                    echo json_encode(['success' => false, 'message' => 'Step not found']);
                    exit;
                }
                
                // Uncheck the step
                $update_stmt = $pdo->prepare("
                    UPDATE goal_eval_steps 
                    SET is_completed = 0,
                        completed_at = NULL,
                        completed_by = NULL,
                        is_approved = 0,
                        approved_by = NULL,
                        approved_at = NULL
                    WHERE id = ?
                ");
                $update_stmt->execute([$step_id]);
                
                // Update approval request if exists
                if ($step['approval_id']) {
                    $approval_update = $pdo->prepare("
                        UPDATE goal_eval_approvals 
                        SET status = 'rejected', 
                            approved_by = ?, 
                            approval_note = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $approval_update->execute([$user_id, $rejection_note, $step['approval_id']]);
                    
                    // Send notification to athlete
                    if ($step['requested_by']) {
                        sendApprovalNotification($pdo, $step['requested_by'], 'approval_rejected', [
                            'step_title' => $step['title'],
                            'note' => $rejection_note
                        ]);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Step rejected'
                ]);
                break;
                
            case 'cancel_approval_request':
                $step_id = intval($_POST['step_id']);
                
                // Get step info
                $step_stmt = $pdo->prepare("
                    SELECT ges.*, ge.athlete_id, gea.id as approval_id
                    FROM goal_eval_steps ges
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    LEFT JOIN goal_eval_approvals gea ON gea.goal_eval_step_id = ges.id AND gea.status = 'pending'
                    WHERE ges.id = ?
                ");
                $step_stmt->execute([$step_id]);
                $step = $step_stmt->fetch();
                
                if (!$step) {
                    echo json_encode(['success' => false, 'message' => 'Step not found']);
                    exit;
                }
                
                // Check if user is the athlete
                if ($step['athlete_id'] != $user_id) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                if ($step['approval_id']) {
                    $pdo->prepare("DELETE FROM goal_eval_approvals WHERE id = ?")->execute([$step['approval_id']]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Approval request cancelled'
                ]);
                break;
                
            case 'get_pending_approvals':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can view pending approvals']);
                    exit;
                }
                
                // Get all pending approvals
                $approvals_stmt = $pdo->prepare("
                    SELECT gea.*,
                           ges.title as step_title,
                           ges.description as step_description,
                           ge.title as eval_title,
                           CONCAT(u1.first_name, ' ', u1.last_name) as requested_by_name,
                           CONCAT(u2.first_name, ' ', u2.last_name) as athlete_name
                    FROM goal_eval_approvals gea
                    JOIN goal_eval_steps ges ON gea.goal_eval_step_id = ges.id
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    JOIN users u1 ON gea.requested_by = u1.id
                    JOIN users u2 ON ge.athlete_id = u2.id
                    WHERE gea.status = 'pending'
                    ORDER BY gea.created_at DESC
                ");
                $approvals_stmt->execute();
                $approvals = $approvals_stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'approvals' => $approvals
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Error in process_eval_goal_approval.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_pending_approvals':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can view pending approvals']);
                    exit;
                }
                
                // Get all pending approvals
                $approvals_stmt = $pdo->prepare("
                    SELECT gea.*,
                           ges.title as step_title,
                           ges.description as step_description,
                           ge.title as eval_title,
                           CONCAT(u1.first_name, ' ', u1.last_name) as requested_by_name,
                           CONCAT(u2.first_name, ' ', u2.last_name) as athlete_name
                    FROM goal_eval_approvals gea
                    JOIN goal_eval_steps ges ON gea.goal_eval_step_id = ges.id
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    JOIN users u1 ON gea.requested_by = u1.id
                    JOIN users u2 ON ge.athlete_id = u2.id
                    WHERE gea.status = 'pending'
                    ORDER BY gea.created_at DESC
                ");
                $approvals_stmt->execute();
                $approvals = $approvals_stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'approvals' => $approvals
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Error in process_eval_goal_approval.php GET: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
