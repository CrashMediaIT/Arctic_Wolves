<?php
/**
 * Process Goals Actions
 * Handles all goal-related CRUD operations and progress tracking
 */

session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
 * Helper function to log goal history
 */
function logGoalHistory($pdo, $goal_id, $action, $user_id, $changes = null) {
    $changes_json = null;
    if ($changes !== null) {
        $changes_json = json_encode($changes);
        if ($changes_json === false) {
            ErrorLogger::error("Failed to encode changes for goal $goal_id action $action");
            $changes_json = json_encode(['error' => 'Failed to encode changes']);
        }
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO goal_history (goal_id, action, user_id, changes, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$goal_id, $action, $user_id, $changes_json]);
}

/**
 * Helper function to recalculate goal completion percentage
 */
function recalculateGoalProgress($pdo, $goal_id) {
    // Get step completion stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_steps,
            SUM(CASE WHEN is_completed = 1 THEN 1 ELSE 0 END) as completed_steps
        FROM goal_steps
        WHERE goal_id = ?
    ");
    $stmt->execute([$goal_id]);
    $stats = $stmt->fetch();
    
    $percentage = 0;
    if ($stats['total_steps'] > 0) {
        $percentage = ($stats['completed_steps'] / $stats['total_steps']) * 100;
    }
    
    // Update goal
    $update = $pdo->prepare("
        UPDATE goals 
        SET completion_percentage = ?, updated_at = NOW()
        WHERE id = ?
    ");
    $update->execute([$percentage, $goal_id]);
    
    return $percentage;
}

/**
 * Helper function to check if user can manage goal
 */
function canManageGoal($pdo, $goal_id, $user_id, $is_coach) {
    // Coaches can manage any goal
    if ($is_coach) {
        return true;
    }
    
    // Athletes can manage their own goals
    $stmt = $pdo->prepare("SELECT athlete_id FROM goals WHERE id = ?");
    $stmt->execute([$goal_id]);
    $goal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $goal && $goal['athlete_id'] == $user_id;
}

// Handle GET requests (for AJAX data retrieval)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_goal':
                $goal_id = intval($_GET['goal_id'] ?? 0);
                
                // Get goal
                $stmt = $pdo->prepare("SELECT * FROM goals WHERE id = ?");
                $stmt->execute([$goal_id]);
                $goal = $stmt->fetch();
                
                if (!$goal) {
                    throw new Exception('Goal not found');
                }
                
                // Get steps
                $steps_stmt = $pdo->prepare("
                    SELECT * FROM goal_steps 
                    WHERE goal_id = ? 
                    ORDER BY step_order
                ");
                $steps_stmt->execute([$goal_id]);
                $goal['steps'] = $steps_stmt->fetchAll();
                
                header('Content-Type: application/json');
                echo json_encode($goal);
                exit();
                
            case 'get_goal_detail':
                $goal_id = intval($_GET['goal_id'] ?? 0);
                
                // Get goal with creator info
                $stmt = $pdo->prepare("
                    SELECT g.*,
                           u.first_name as creator_first_name, u.last_name as creator_last_name
                    FROM goals g
                    LEFT JOIN users u ON g.created_by = u.id
                    WHERE g.id = ?
                ");
                $stmt->execute([$goal_id]);
                $goal = $stmt->fetch();
                $goal = decryptUserRow($goal);
                if ($goal) {
                    $goal['creator_name'] = trim(($goal['creator_first_name'] ?? '') . ' ' . ($goal['creator_last_name'] ?? ''));
                }
                
                if (!$goal) {
                    throw new Exception('Goal not found');
                }
                
                // Get steps
                $steps_stmt = $pdo->prepare("
                    SELECT gs.*,
                           u.first_name as completed_by_first_name, u.last_name as completed_by_last_name
                    FROM goal_steps gs
                    LEFT JOIN users u ON gs.completed_by = u.id
                    WHERE gs.goal_id = ?
                    ORDER BY gs.step_order
                ");
                $steps_stmt->execute([$goal_id]);
                $goal['steps'] = $steps_stmt->fetchAll();
                $goal['steps'] = decryptUserRows($goal['steps']);
                foreach ($goal['steps'] as &$s) {
                    $s['completed_by_name'] = trim(($s['completed_by_first_name'] ?? '') . ' ' . ($s['completed_by_last_name'] ?? ''));
                }
                unset($s);
                
                // Get progress history
                $progress_stmt = $pdo->prepare("
                    SELECT gp.*,
                           u.first_name as user_first_name, u.last_name as user_last_name
                    FROM goal_progress gp
                    LEFT JOIN users u ON gp.user_id = u.id
                    WHERE gp.goal_id = ?
                    ORDER BY gp.created_at DESC
                ");
                $progress_stmt->execute([$goal_id]);
                $goal['progress'] = $progress_stmt->fetchAll();
                $goal['progress'] = decryptUserRows($goal['progress']);
                foreach ($goal['progress'] as &$p) {
                    $p['user_name'] = trim(($p['user_first_name'] ?? '') . ' ' . ($p['user_last_name'] ?? ''));
                }
                unset($p);
                
                header('Content-Type: application/json');
                echo json_encode($goal);
                exit();
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
}

// Validate CSRF token for POST requests
checkCsrfToken();

$action = $_POST['action'] ?? '';

try {
    $pdo->beginTransaction();
    
    switch ($action) {
        case 'create_goal':
            // Both coaches and athletes can create goals
            // Coaches can create for any athlete, athletes can only create for themselves
            $athlete_id = intval($_POST['athlete_id']);
            
            if (!$is_coach && $athlete_id != $user_id) {
                throw new Exception('You can only create goals for yourself');
            }
            
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
            
            if (empty($title)) {
                throw new Exception('Title is required');
            }
            
            // Create goal
            $stmt = $pdo->prepare("
                INSERT INTO goals (
                    athlete_id, created_by, title, description, category, tags,
                    target_date, status, completion_percentage, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 0, NOW(), NOW())
            ");
            $stmt->execute([
                $athlete_id, $user_id, $title, $description, $category, $tags, $target_date
            ]);
            
            $goal_id = $pdo->lastInsertId();
            
            // Add steps if provided
            if (isset($_POST['steps']) && is_array($_POST['steps'])) {
                foreach ($_POST['steps'] as $step) {
                    if (!empty($step['title'])) {
                        $step_stmt = $pdo->prepare("
                            INSERT INTO goal_steps (
                                goal_id, step_order, title, description, is_completed, created_at
                            ) VALUES (?, ?, ?, ?, 0, NOW())
                        ");
                        $step_stmt->execute([
                            $goal_id,
                            intval($step['order']),
                            trim($step['title']),
                            trim($step['description'] ?? '')
                        ]);
                    }
                }
            }
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'created', $user_id, [
                'title' => $title,
                'category' => $category
            ]);
            
            Auditor::log($pdo, $user_id, 'create', 'goals', $goal_id, ['action' => 'goal_created', 'title' => $title]);

            $pdo->commit();
            header("Location: dashboard.php?page=stats&tab=goals&athlete_id=$athlete_id&msg=created");
            exit();
            
        case 'update_goal':
            $goal_id = intval($_POST['goal_id']);
            
            // Use canManageGoal to check permissions (coaches or athlete's own goal)
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $tags = trim($_POST['tags'] ?? '');
            $target_date = !empty($_POST['target_date']) ? $_POST['target_date'] : null;
            $target_value = isset($_POST['target_value']) && $_POST['target_value'] !== '' ? floatval($_POST['target_value']) : null;
            $current_value = isset($_POST['current_value']) && $_POST['current_value'] !== '' ? floatval($_POST['current_value']) : null;
            $status = trim($_POST['status'] ?? '');
            
            if (empty($title)) {
                throw new Exception('Title is required');
            }
            
            // Get current goal for history
            $current = $pdo->prepare("SELECT * FROM goals WHERE id = ?");
            $current->execute([$goal_id]);
            $old_goal = $current->fetch();
            
            // Build dynamic update query
            $updateFields = 'title = ?, description = ?, category = ?, tags = ?, target_date = ?';
            $updateParams = [$title, $description, $category, $tags, $target_date];
            
            if ($target_value !== null) {
                $updateFields .= ', target_value = ?';
                $updateParams[] = $target_value;
            }
            if ($current_value !== null) {
                $updateFields .= ', current_value = ?';
                $updateParams[] = $current_value;
            }
            if (!empty($status)) {
                $updateFields .= ', status = ?';
                $updateParams[] = $status;
                if ($status === 'completed') {
                    $updateFields .= ', completed_at = NOW(), completion_percentage = 100';
                }
            }
            $updateFields .= ', updated_at = NOW()';
            $updateParams[] = $goal_id;
            
            // Update goal
            $stmt = $pdo->prepare("
                UPDATE goals 
                SET $updateFields
                WHERE id = ?
            ");
            $stmt->execute($updateParams);
            
            // Update steps
            if (isset($_POST['steps']) && is_array($_POST['steps'])) {
                // Get existing steps
                $existing_steps = $pdo->prepare("SELECT id FROM goal_steps WHERE goal_id = ?");
                $existing_steps->execute([$goal_id]);
                $existing_ids = $existing_steps->fetchAll(PDO::FETCH_COLUMN);
                
                $updated_ids = [];
                
                foreach ($_POST['steps'] as $step) {
                    if (!empty($step['title'])) {
                        if (!empty($step['id'])) {
                            // Update existing step
                            $step_stmt = $pdo->prepare("
                                UPDATE goal_steps 
                                SET title = ?, step_order = ?
                                WHERE id = ? AND goal_id = ?
                            ");
                            $step_stmt->execute([
                                trim($step['title']),
                                intval($step['order']),
                                intval($step['id']),
                                $goal_id
                            ]);
                            $updated_ids[] = intval($step['id']);
                        } else {
                            // Create new step
                            $step_stmt = $pdo->prepare("
                                INSERT INTO goal_steps (
                                    goal_id, step_order, title, description, is_completed, created_at
                                ) VALUES (?, ?, ?, ?, 0, NOW())
                            ");
                            $step_stmt->execute([
                                $goal_id,
                                intval($step['order']),
                                trim($step['title']),
                                trim($step['description'] ?? '')
                            ]);
                            $updated_ids[] = $pdo->lastInsertId();
                        }
                    }
                }
                
                // Delete removed steps
                $to_delete = array_diff($existing_ids, $updated_ids);
                if (!empty($to_delete) && count($to_delete) > 0) {
                    $placeholders = str_repeat('?,', count($to_delete) - 1) . '?';
                    $delete_stmt = $pdo->prepare("
                        DELETE FROM goal_steps 
                        WHERE goal_id = ? AND id IN ($placeholders)
                    ");
                    $delete_stmt->execute(array_merge([$goal_id], array_values($to_delete)));
                }
            }
            
            // Recalculate progress
            recalculateGoalProgress($pdo, $goal_id);
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'updated', $user_id, [
                'old' => $old_goal,
                'new' => ['title' => $title, 'category' => $category]
            ]);
            
            Auditor::log($pdo, $user_id, 'update', 'goals', $goal_id, ['action' => 'goal_updated', 'title' => $title]);

            $pdo->commit();
            header("Location: dashboard.php?page=stats&tab=goals&athlete_id={$old_goal['athlete_id']}&msg=updated");
            exit();
            
        case 'delete_goal':
        case 'archive_goal':
            if (!$is_coach) {
                throw new Exception('Only coaches can archive goals');
            }
            
            $goal_id = intval($_POST['goal_id']);
            
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            // Soft delete - mark as archived
            $stmt = $pdo->prepare("
                UPDATE goals 
                SET status = 'archived', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$goal_id]);
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'archived', $user_id);
            
            Auditor::log($pdo, $user_id, 'delete', 'goals', $goal_id, ['action' => 'goal_archived']);

            $pdo->commit();
            header("Location: dashboard.php?page=stats&tab=goals&msg=archived");
            exit();
            
        case 'add_step':
            if (!$is_coach) {
                throw new Exception('Only coaches can add steps');
            }
            
            $goal_id = intval($_POST['goal_id']);
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            if (empty($title)) {
                throw new Exception('Step title is required');
            }
            
            // Get max order
            $max_order = $pdo->prepare("SELECT MAX(step_order) FROM goal_steps WHERE goal_id = ?");
            $max_order->execute([$goal_id]);
            $order = ($max_order->fetchColumn() ?? 0) + 1;
            
            // Add step
            $stmt = $pdo->prepare("
                INSERT INTO goal_steps (
                    goal_id, step_order, title, description, is_completed, created_at
                ) VALUES (?, ?, ?, ?, 0, NOW())
            ");
            $stmt->execute([$goal_id, $order, $title, $description]);
            
            // Recalculate progress
            recalculateGoalProgress($pdo, $goal_id);
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'step_added', $user_id, ['title' => $title]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit();
            
        case 'update_step':
            if (!$is_coach) {
                throw new Exception('Only coaches can update steps');
            }
            
            $step_id = intval($_POST['step_id']);
            $goal_id = intval($_POST['goal_id']);
            $title = trim($_POST['title']);
            $description = trim($_POST['description'] ?? '');
            
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            if (empty($title)) {
                throw new Exception('Step title is required');
            }
            
            // Update step
            $stmt = $pdo->prepare("
                UPDATE goal_steps 
                SET title = ?, description = ?
                WHERE id = ? AND goal_id = ?
            ");
            $stmt->execute([$title, $description, $step_id, $goal_id]);
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'step_updated', $user_id, ['step_id' => $step_id]);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit();
            
        case 'complete_step':
            if (!$is_coach) {
                throw new Exception('Only coaches can mark steps complete');
            }
            
            $step_id = intval($_POST['step_id']);
            $goal_id = intval($_POST['goal_id']);
            $is_completed = intval($_POST['is_completed']);
            
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            // Update step
            if ($is_completed) {
                $stmt = $pdo->prepare("
                    UPDATE goal_steps 
                    SET is_completed = 1, completed_at = NOW(), completed_by = ?
                    WHERE id = ? AND goal_id = ?
                ");
                $stmt->execute([$user_id, $step_id, $goal_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE goal_steps 
                    SET is_completed = 0, completed_at = NULL, completed_by = NULL
                    WHERE id = ? AND goal_id = ?
                ");
                $stmt->execute([$step_id, $goal_id]);
            }
            
            // Recalculate progress
            $new_percentage = recalculateGoalProgress($pdo, $goal_id);
            
            // Log history
            logGoalHistory($pdo, $goal_id, $is_completed ? 'step_completed' : 'step_uncompleted', $user_id, [
                'step_id' => $step_id,
                'new_percentage' => $new_percentage
            ]);
            
            $pdo->commit();
            echo json_encode(['success' => true, 'percentage' => $new_percentage]);
            exit();
            
        case 'update_progress':
            if (!$is_coach) {
                throw new Exception('Only coaches can add progress notes');
            }
            
            $goal_id = intval($_POST['goal_id']);
            $progress_note = trim($_POST['progress_note']);
            $progress_percentage = !empty($_POST['progress_percentage']) ? floatval($_POST['progress_percentage']) : null;
            
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            if (empty($progress_note)) {
                throw new Exception('Progress note is required');
            }
            
            // Get current percentage if not provided
            if ($progress_percentage === null) {
                $current = $pdo->prepare("SELECT completion_percentage FROM goals WHERE id = ?");
                $current->execute([$goal_id]);
                $progress_percentage = $current->fetchColumn();
            }
            
            // Add progress entry
            $stmt = $pdo->prepare("
                INSERT INTO goal_progress (
                    goal_id, user_id, progress_note, progress_percentage, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$goal_id, $user_id, $progress_note, $progress_percentage]);
            
            // Update goal if percentage was manually set
            if (!empty($_POST['progress_percentage'])) {
                $update = $pdo->prepare("
                    UPDATE goals 
                    SET completion_percentage = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $update->execute([$progress_percentage, $goal_id]);
            }
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'progress_updated', $user_id, [
                'note' => substr($progress_note, 0, 100),
                'percentage' => $progress_percentage
            ]);
            
            $pdo->commit();
            
            // Get athlete_id for redirect
            $goal = $pdo->prepare("SELECT athlete_id FROM goals WHERE id = ?");
            $goal->execute([$goal_id]);
            $athlete_id = $goal->fetchColumn();
            
            header("Location: dashboard.php?page=stats&tab=goals&athlete_id=$athlete_id&msg=progress_added");
            exit();
            
        case 'complete_goal':
            if (!$is_coach) {
                throw new Exception('Only coaches can complete goals');
            }
            
            $goal_id = intval($_POST['goal_id']);
            
            if (!canManageGoal($pdo, $goal_id, $user_id, $is_coach)) {
                throw new Exception('Permission denied');
            }
            
            // Mark goal as completed
            $stmt = $pdo->prepare("
                UPDATE goals 
                SET status = 'completed', 
                    completion_percentage = 100,
                    completed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$goal_id]);
            
            // Log history
            logGoalHistory($pdo, $goal_id, 'completed', $user_id);
            
            $pdo->commit();
            echo json_encode(['success' => true]);
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Handle JSON responses
    if (isset($_POST['json']) || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    
    // Handle form submissions
    header("Location: dashboard.php?page=stats&tab=goals&error=" . urlencode($e->getMessage()));
    exit();
}
