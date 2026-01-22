<?php
/**
 * Process Evaluation Templates Actions
 * Handles creation and management of reusable evaluation templates
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

// Only coaches can manage templates
if (!$is_coach) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Only coaches can manage evaluation templates']));
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'No action specified']));
}

try {
    switch ($action) {
        case 'create':
            handleCreate($pdo, $user_id);
            break;
            
        case 'update':
            handleUpdate($pdo, $user_id);
            break;
            
        case 'delete':
            handleDelete($pdo, $user_id);
            break;
            
        case 'list':
            handleList($pdo, $user_id);
            break;
            
        case 'get':
            handleGet($pdo, $user_id);
            break;
            
        default:
            http_response_code(400);
            die(json_encode(['success' => false, 'message' => 'Invalid action']));
    }
} catch (PDOException $e) {
    error_log("Process Evaluation Templates Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database error occurred']));
}

/**
 * Create a new evaluation template
 */
function handleCreate($pdo, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($title)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template title is required']));
    }
    
    // Store as template evaluation
    $stmt = $pdo->prepare("
        INSERT INTO athlete_evaluations (
            athlete_id, evaluator_id, eval_date, notes, status, is_template, created_at
        ) VALUES (0, ?, NOW(), ?, 'template', 1, NOW())
    ");
    $stmt->execute([$user_id, $description]);
    $template_id = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation template created successfully',
        'template_id' => $template_id
    ]);
}

/**
 * Update an existing evaluation template
 */
function handleUpdate($pdo, $user_id) {
    $template_id = $_POST['template_id'] ?? '';
    $notes = trim($_POST['description'] ?? '');
    
    if (empty($template_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template ID is required']));
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT id FROM athlete_evaluations 
        WHERE id = ? AND evaluator_id = ? AND is_template = 1
    ");
    $stmt->execute([$template_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Template not found or access denied']));
    }
    
    // Update template
    if (!empty($notes)) {
        $stmt = $pdo->prepare("
            UPDATE athlete_evaluations 
            SET notes = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$notes, $template_id]);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation template updated successfully'
    ]);
}

/**
 * Delete an evaluation template
 */
function handleDelete($pdo, $user_id) {
    $template_id = $_POST['template_id'] ?? '';
    
    if (empty($template_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template ID is required']));
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT id FROM athlete_evaluations 
        WHERE id = ? AND evaluator_id = ? AND is_template = 1
    ");
    $stmt->execute([$template_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Template not found or access denied']));
    }
    
    // Delete template
    $stmt = $pdo->prepare("DELETE FROM athlete_evaluations WHERE id = ?");
    $stmt->execute([$template_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Evaluation template deleted successfully'
    ]);
}

/**
 * List all evaluation templates
 */
function handleList($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT id, eval_date, notes, created_at, updated_at
        FROM athlete_evaluations 
        WHERE is_template = 1 AND (evaluator_id = ? OR evaluator_id = 0)
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

/**
 * Get a specific evaluation template
 */
function handleGet($pdo, $user_id) {
    $template_id = $_GET['template_id'] ?? '';
    
    if (empty($template_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template ID is required']));
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM athlete_evaluations 
        WHERE id = ? AND is_template = 1
    ");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Template not found']));
    }
    
    echo json_encode([
        'success' => true,
        'template' => $template
    ]);
}
?>
