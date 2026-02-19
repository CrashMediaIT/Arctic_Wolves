<?php
/**
 * Process Goal Templates Actions
 * Handles creation and management of reusable goal templates
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

// Validate CSRF token for POST requests (state-changing operations)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$is_coach = ($user_role === 'coach' || $user_role === 'coach_plus' || $user_role === 'admin');

// Only coaches can manage templates
if (!$is_coach) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Only coaches can manage goal templates']));
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
    ErrorLogger::error("Process Goal Templates Error: " . $e->getMessage());
    http_response_code(500);
    die(json_encode(['success' => false, 'message' => 'Database error occurred']));
}

/**
 * Create a new goal template
 * 
 * NOTE: This implementation assumes an 'is_template' column exists in the goals table.
 * If the column doesn't exist, it should be added via migration:
 * ALTER TABLE goals ADD COLUMN is_template TINYINT(1) DEFAULT 0;
 */
function handleCreate($pdo, $user_id) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = trim($_POST['category'] ?? 'general');
    
    if (empty($title)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template title is required']));
    }
    
    // Note: This assumes a goal_templates table exists or will be created
    // If not, this should store in goals table with a template flag
    $stmt = $pdo->prepare("
        INSERT INTO goals (
            user_id, title, description, goal_type, status, is_template, created_at
        ) VALUES (?, ?, ?, ?, 'template', 1, NOW())
    ");
    $stmt->execute([$user_id, $title, $description, $category]);
    $template_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'goals', $template_id, ['action' => 'Created goal template', 'title' => $title]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Goal template created successfully',
        'template_id' => $template_id
    ]);
}

/**
 * Update an existing goal template
 */
function handleUpdate($pdo, $user_id) {
    $template_id = $_POST['template_id'] ?? '';
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($template_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template ID is required']));
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT id FROM goals 
        WHERE id = ? AND user_id = ? AND is_template = 1
    ");
    $stmt->execute([$template_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Template not found or access denied']));
    }
    
    // Update template
    $updates = [];
    $params = [];
    
    if (!empty($title)) {
        $updates[] = "title = ?";
        $params[] = $title;
    }
    
    if (!empty($description)) {
        $updates[] = "description = ?";
        $params[] = $description;
    }
    
    if (empty($updates)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'No valid fields to update']));
    }
    
    $updates[] = "updated_at = NOW()";
    $params[] = $template_id;
    
    $sql = "UPDATE goals SET " . implode(', ', $updates) . " WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    Auditor::log($pdo, $user_id, 'update', 'goals', $template_id, ['action' => 'Updated goal template']);

    echo json_encode([
        'success' => true,
        'message' => 'Goal template updated successfully'
    ]);
}

/**
 * Delete a goal template
 */
function handleDelete($pdo, $user_id) {
    $template_id = $_POST['template_id'] ?? '';
    
    if (empty($template_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template ID is required']));
    }
    
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT id FROM goals 
        WHERE id = ? AND user_id = ? AND is_template = 1
    ");
    $stmt->execute([$template_id, $user_id]);
    
    if (!$stmt->fetch()) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Template not found or access denied']));
    }
    
    // Delete template
    $stmt = $pdo->prepare("DELETE FROM goals WHERE id = ?");
    $stmt->execute([$template_id]);
    Auditor::log($pdo, $user_id, 'delete', 'goals', $template_id, ['action' => 'Deleted goal template']);

    echo json_encode([
        'success' => true,
        'message' => 'Goal template deleted successfully'
    ]);
}

/**
 * List all goal templates
 */
function handleList($pdo, $user_id) {
    $stmt = $pdo->prepare("
        SELECT id, title, description, goal_type, created_at, updated_at
        FROM goals 
        WHERE is_template = 1 AND (user_id = ? OR user_id = 0)
        ORDER BY title ASC
    ");
    $stmt->execute([$user_id]);
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'templates' => $templates
    ]);
}

/**
 * Get a specific goal template
 */
function handleGet($pdo, $user_id) {
    $template_id = $_GET['template_id'] ?? '';
    
    if (empty($template_id)) {
        http_response_code(400);
        die(json_encode(['success' => false, 'message' => 'Template ID is required']));
    }
    
    $stmt = $pdo->prepare("
        SELECT * FROM goals 
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
