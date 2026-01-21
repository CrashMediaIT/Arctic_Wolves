<?php
/**
 * Process Goal-Based Evaluation Actions
 * Handles all evaluation-related CRUD operations and step management
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
 * Helper function to check if user can manage evaluation
 */
function canManageEvaluation($pdo, $eval_id, $user_id, $is_coach) {
    if (!$is_coach) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM goal_evaluations WHERE id = ?");
    $stmt->execute([$eval_id]);
    return $stmt->fetch() !== false;
}

/**
 * Helper function to check if user can view evaluation
 */
function canViewEvaluation($pdo, $eval_id, $user_id, $is_coach) {
    $stmt = $pdo->prepare("
        SELECT id FROM goal_evaluations 
        WHERE id = ? AND (athlete_id = ? OR ? = 1)
    ");
    $stmt->execute([$eval_id, $user_id, $is_coach ? 1 : 0]);
    return $stmt->fetch() !== false;
}

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_evaluation':
                $eval_id = intval($_GET['evaluation_id'] ?? 0);
                
                if (!canViewEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                // Get evaluation
                $stmt = $pdo->prepare("
                    SELECT ge.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
                           (SELECT COUNT(*) FROM goal_eval_steps WHERE goal_eval_id = ge.id) as total_steps,
                           (SELECT COUNT(*) FROM goal_eval_steps WHERE goal_eval_id = ge.id AND is_completed = 1) as completed_steps
                    FROM goal_evaluations ge
                    LEFT JOIN users u ON ge.created_by = u.id
                    WHERE ge.id = ?
                ");
                $stmt->execute([$eval_id]);
                $evaluation = $stmt->fetch();
                
                if (!$evaluation) {
                    echo json_encode(['success' => false, 'message' => 'Evaluation not found']);
                    exit;
                }
                
                // Get steps
                $steps_stmt = $pdo->prepare("
                    SELECT ges.*,
                           CONCAT(u1.first_name, ' ', u1.last_name) as completed_by_name,
                           CONCAT(u2.first_name, ' ', u2.last_name) as approved_by_name,
                           gea.status as approval_status,
                           gea.approval_note
                    FROM goal_eval_steps ges
                    LEFT JOIN users u1 ON ges.completed_by = u1.id
                    LEFT JOIN users u2 ON ges.approved_by = u2.id
                    LEFT JOIN goal_eval_approvals gea ON gea.goal_eval_step_id = ges.id AND gea.status IN ('pending', 'rejected')
                    WHERE ges.goal_eval_id = ?
                    ORDER BY ges.step_order, ges.created_at
                ");
                $steps_stmt->execute([$eval_id]);
                $steps = $steps_stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'evaluation' => $evaluation,
                    'steps' => $steps
                ]);
                break;
                
            case 'get_step_media':
                $step_id = intval($_GET['step_id'] ?? 0);
                
                $media_stmt = $pdo->prepare("
                    SELECT gep.*,
                           CONCAT(u.first_name, ' ', u.last_name) as user_name
                    FROM goal_eval_progress gep
                    LEFT JOIN users u ON gep.user_id = u.id
                    WHERE gep.goal_eval_step_id = ?
                    ORDER BY gep.created_at DESC
                ");
                $media_stmt->execute([$step_id]);
                $media = $media_stmt->fetchAll();
                
                echo json_encode([
                    'success' => true,
                    'media' => $media
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Error in process_eval_goals.php GET: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_evaluation':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can create evaluations']);
                    exit;
                }
                
                $athlete_id = intval($_POST['athlete_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'active';
                $is_public = isset($_POST['is_public']) ? 1 : 0;
                
                if (empty($title)) {
                    echo json_encode(['success' => false, 'message' => 'Title is required']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO goal_evaluations 
                    (athlete_id, created_by, title, description, status, is_public, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$athlete_id, $user_id, $title, $description, $status, $is_public]);
                
                $eval_id = $pdo->lastInsertId();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation created successfully',
                    'evaluation_id' => $eval_id
                ]);
                break;
                
            case 'update_evaluation':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can update evaluations']);
                    exit;
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'active';
                $is_public = isset($_POST['is_public']) ? 1 : 0;
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                if (empty($title)) {
                    echo json_encode(['success' => false, 'message' => 'Title is required']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE goal_evaluations 
                    SET title = ?, description = ?, status = ?, is_public = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $status, $is_public, $eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation updated successfully'
                ]);
                break;
                
            case 'delete_evaluation':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can delete evaluations']);
                    exit;
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                // Delete related records first
                $pdo->prepare("DELETE FROM goal_eval_approvals WHERE goal_eval_step_id IN (SELECT id FROM goal_eval_steps WHERE goal_eval_id = ?)")->execute([$eval_id]);
                $pdo->prepare("DELETE FROM goal_eval_progress WHERE goal_eval_step_id IN (SELECT id FROM goal_eval_steps WHERE goal_eval_id = ?)")->execute([$eval_id]);
                $pdo->prepare("DELETE FROM goal_eval_steps WHERE goal_eval_id = ?")->execute([$eval_id]);
                $pdo->prepare("DELETE FROM goal_evaluations WHERE id = ?")->execute([$eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation deleted successfully'
                ]);
                break;
                
            case 'add_step':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can add steps']);
                    exit;
                }
                
                $eval_id = intval($_POST['goal_eval_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description'] ?? '');
                $needs_approval = isset($_POST['needs_approval']) ? 1 : 0;
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                if (empty($title)) {
                    echo json_encode(['success' => false, 'message' => 'Step title is required']);
                    exit;
                }
                
                // Get next step order
                $order_stmt = $pdo->prepare("SELECT COALESCE(MAX(step_order), 0) + 1 as next_order FROM goal_eval_steps WHERE goal_eval_id = ?");
                $order_stmt->execute([$eval_id]);
                $next_order = $order_stmt->fetchColumn();
                
                $stmt = $pdo->prepare("
                    INSERT INTO goal_eval_steps 
                    (goal_eval_id, step_order, title, description, needs_approval, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$eval_id, $next_order, $title, $description, $needs_approval]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Step added successfully',
                    'step_id' => $pdo->lastInsertId()
                ]);
                break;
                
            case 'update_step':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can update steps']);
                    exit;
                }
                
                $step_id = intval($_POST['step_id']);
                $title = trim($_POST['title']);
                $description = trim($_POST['description'] ?? '');
                $needs_approval = isset($_POST['needs_approval']) ? 1 : 0;
                
                $stmt = $pdo->prepare("
                    UPDATE goal_eval_steps 
                    SET title = ?, description = ?, needs_approval = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $description, $needs_approval, $step_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Step updated successfully'
                ]);
                break;
                
            case 'check_step':
                $step_id = intval($_POST['step_id']);
                $is_checked = intval($_POST['is_checked']);
                
                // Get step info
                $step_stmt = $pdo->prepare("
                    SELECT ges.*, ge.athlete_id 
                    FROM goal_eval_steps ges
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    WHERE ges.id = ?
                ");
                $step_stmt->execute([$step_id]);
                $step = $step_stmt->fetch();
                
                if (!$step) {
                    echo json_encode(['success' => false, 'message' => 'Step not found']);
                    exit;
                }
                
                // Check permissions
                if (!$is_coach && $step['athlete_id'] != $user_id) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                if ($is_checked) {
                    // Marking as complete
                    if ($is_coach) {
                        // Coaches can complete and approve immediately
                        $update_stmt = $pdo->prepare("
                            UPDATE goal_eval_steps 
                            SET is_completed = 1, 
                                completed_at = NOW(), 
                                completed_by = ?,
                                is_approved = 1,
                                approved_at = NOW(),
                                approved_by = ?
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$user_id, $user_id, $step_id]);
                    } else {
                        // Athletes complete the step
                        $update_stmt = $pdo->prepare("
                            UPDATE goal_eval_steps 
                            SET is_completed = 1, 
                                completed_at = NOW(), 
                                completed_by = ?
                            WHERE id = ?
                        ");
                        $update_stmt->execute([$user_id, $step_id]);
                        
                        // If step needs approval, create approval request
                        if ($step['needs_approval']) {
                            $approval_stmt = $pdo->prepare("
                                INSERT INTO goal_eval_approvals 
                                (goal_eval_step_id, requested_by, status, created_at, updated_at)
                                VALUES (?, ?, 'pending', NOW(), NOW())
                            ");
                            $approval_stmt->execute([$step_id, $user_id]);
                            
                            // Send notification to coach (would integrate with notification system)
                            // TODO: Send notification
                        }
                    }
                } else {
                    // Unchecking - only coaches can do this
                    if (!$is_coach) {
                        echo json_encode(['success' => false, 'message' => 'Only coaches can uncheck steps']);
                        exit;
                    }
                    
                    $update_stmt = $pdo->prepare("
                        UPDATE goal_eval_steps 
                        SET is_completed = 0, 
                            completed_at = NULL, 
                            completed_by = NULL,
                            is_approved = 0,
                            approved_at = NULL,
                            approved_by = NULL
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$step_id]);
                    
                    // Delete any pending approval requests
                    $pdo->prepare("DELETE FROM goal_eval_approvals WHERE goal_eval_step_id = ?")->execute([$step_id]);
                }
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Step updated successfully',
                    'evaluation_id' => $step['goal_eval_id']
                ]);
                break;
                
            case 'add_media':
                $step_id = intval($_POST['step_id']);
                $progress_note = trim($_POST['progress_note'] ?? '');
                
                // Get step info
                $step_stmt = $pdo->prepare("
                    SELECT ges.*, ge.athlete_id 
                    FROM goal_eval_steps ges
                    JOIN goal_evaluations ge ON ges.goal_eval_id = ge.id
                    WHERE ges.id = ?
                ");
                $step_stmt->execute([$step_id]);
                $step = $step_stmt->fetch();
                
                if (!$step) {
                    echo json_encode(['success' => false, 'message' => 'Step not found']);
                    exit;
                }
                
                // Check permissions
                if (!$is_coach && $step['athlete_id'] != $user_id) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                // Handle file upload
                $media_url = null;
                $media_type = null;
                
                if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
                    $upload_dir = __DIR__ . '/uploads/eval_media/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }
                    
                    $file_ext = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
                    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
                    
                    if (!in_array($file_ext, $allowed_extensions)) {
                        echo json_encode(['success' => false, 'message' => 'Invalid file type']);
                        exit;
                    }
                    
                    $file_name = uniqid() . '.' . $file_ext;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['media_file']['tmp_name'], $file_path)) {
                        $media_url = 'uploads/eval_media/' . $file_name;
                        $media_type = in_array($file_ext, ['mp4', 'mov', 'avi']) ? 'video' : 'image';
                    }
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO goal_eval_progress 
                    (goal_eval_step_id, user_id, progress_note, media_url, media_type, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$step_id, $user_id, $progress_note, $media_url, $media_type]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Media added successfully'
                ]);
                break;
                
            case 'generate_share_link':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can generate share links']);
                    exit;
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                // Generate unique token
                $share_token = bin2hex(random_bytes(16));
                
                $stmt = $pdo->prepare("
                    UPDATE goal_evaluations 
                    SET share_token = ?, is_public = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$share_token, $eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Share link generated',
                    'share_token' => $share_token
                ]);
                break;
                
            case 'revoke_share_link':
                if (!$is_coach) {
                    echo json_encode(['success' => false, 'message' => 'Only coaches can revoke share links']);
                    exit;
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    echo json_encode(['success' => false, 'message' => 'Access denied']);
                    exit;
                }
                
                $stmt = $pdo->prepare("
                    UPDATE goal_evaluations 
                    SET share_token = NULL, is_public = 0, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Share link revoked'
                ]);
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
    } catch (Exception $e) {
        error_log("Error in process_eval_goals.php POST: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Method not allowed']);
