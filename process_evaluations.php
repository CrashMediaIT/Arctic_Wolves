<?php
/**
 * Process Evaluations Actions
 * Handles creation, updating, and viewing of athlete evaluations
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

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No action specified']));
}

try {
    switch ($action) {
        case 'create':
            handleCreate($pdo, $user_id, $is_coach);
            break;
            
        case 'update':
            handleUpdate($pdo, $user_id, $is_coach);
            break;
            
        case 'delete':
            handleDelete($pdo, $user_id, $is_coach);
            break;
            
        case 'get':
            handleGet($pdo, $user_id, $is_coach);
            break;
            
        default:
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid action']));
    }
} catch (PDOException $e) {
    error_log("Process Evaluations Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database error occurred']));
}

/**
 * Create a new evaluation
 */
function handleCreate($pdo, $user_id, $is_coach) {
    if (!$is_coach) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Only coaches can create evaluations']));
    }
    
    $athlete_id = $_POST['athlete_id'] ?? '';
    $eval_date = $_POST['eval_date'] ?? date('Y-m-d');
    $notes = $_POST['notes'] ?? '';
    
    if (empty($athlete_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Athlete ID is required']));
    }
    
    // Create evaluation
    $stmt = $pdo->prepare("
        INSERT INTO athlete_evaluations (
            athlete_id, evaluator_id, eval_date, notes, status, created_at
        ) VALUES (?, ?, ?, ?, 'draft', NOW())
    ");
    $stmt->execute([$athlete_id, $user_id, $eval_date, $notes]);
    $eval_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation created successfully',
        'evaluation_id' => $eval_id
    ]);
}

/**
 * Update an existing evaluation
 */
function handleUpdate($pdo, $user_id, $is_coach) {
    if (!$is_coach) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Only coaches can update evaluations']));
    }
    
    $eval_id = $_POST['eval_id'] ?? '';
    $notes = $_POST['notes'] ?? '';
    $status = $_POST['status'] ?? '';
    
    if (empty($eval_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Evaluation ID is required']));
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT id FROM athlete_evaluations 
        WHERE id = ? AND evaluator_id = ?
    ");
    $stmt->execute([$eval_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Evaluation not found or access denied']));
    }
    
    // Update evaluation
    $updates = [];
    $params = [];
    
    if (!empty($notes)) {
        $updates[] = "notes = ?";
        $params[] = $notes;
    }
    
    if (!empty($status) && in_array($status, ['draft', 'published', 'archived'])) {
        $updates[] = "status = ?";
        $params[] = $status;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'No valid fields to update']));
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $eval_id;
    
    $sql = "UPDATE athlete_evaluations SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation updated successfully'
    ]);
}

/**
 * Delete an evaluation
 */
function handleDelete($pdo, $user_id, $is_coach) {
    if (!$is_coach) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Only coaches can delete evaluations']));
    }
    
    $eval_id = $_POST['eval_id'] ?? '';
    
    if (empty($eval_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Evaluation ID is required']));
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT id FROM athlete_evaluations 
        WHERE id = ? AND evaluator_id = ?
    ");
    $stmt->execute([$eval_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Evaluation not found or access denied']));
    }
    
    // Delete evaluation
    $stmt = $pdo->prepare("DELETE FROM athlete_evaluations WHERE id = ?");
    $stmt->execute([$eval_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation deleted successfully'
    ]);
}

/**
 * Get evaluation details
 */
function handleGet($pdo, $user_id, $is_coach) {
    $eval_id = $_GET['eval_id'] ?? '';
    
    if (empty($eval_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Evaluation ID is required']));
    }
    
    // Get evaluation
    $stmt = $pdo->prepare("
        SELECT ae.*, 
               u.first_name, u.last_name,
               e.first_name as evaluator_first_name, e.last_name as evaluator_last_name
        FROM athlete_evaluations ae
        JOIN users u ON ae.athlete_id = u.id
        JOIN users e ON ae.evaluator_id = e.id
        WHERE ae.id = ?
    ");
    $stmt->execute([$eval_id]);
    $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$evaluation) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Evaluation not found']));
    }
    
    // Check access
    if (!$is_coach && $evaluation['athlete_id'] != $user_id) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Access denied']));
    }
    
    echo json_encode([
        'success' => true,
        'evaluation' => $evaluation
    ]);
}
?>
