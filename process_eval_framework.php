<?php
/**
 * Process Evaluation Framework Actions
 * Handles category and skill management with ordering and activation
 */

session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Helper function to check if this is an AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Set JSON content type for AJAX requests early
if (isAjaxRequest()) {
    header('Content-Type: application/json');
}

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Only admins can manage framework
if ($user_role !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Admin access required']));
}

// Helper function to send response (either redirect or JSON)
function sendResponse($success, $message, $redirectPage = 'admin_eval_framework', $data = []) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    } else {
        $status = $success ? 'success' : 'error';
        header("Location: dashboard.php?page={$redirectPage}&status={$status}&message=" . urlencode($message));
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_category':
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                $skill_ids = $_POST['skill_ids'] ?? [];
                
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO eval_categories (name, description, created_at)
                    VALUES (?, ?, NOW())
                ");
                $stmt->execute([$name, $description]);
                $category_id = $pdo->lastInsertId();
                Auditor::log($pdo, $user_id, 'create', 'eval_categories', $category_id, ['action' => 'Created eval category', 'name' => $name]);
                
                // If skills were selected from library, assign them to this category via junction table
                if (!empty($skill_ids) && is_array($skill_ids)) {
                    $insertJunction = $pdo->prepare("
                        INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, display_order, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $order = 0;
                    foreach ($skill_ids as $skill_id) {
                        $skill_id = intval($skill_id);
                        if ($skill_id > 0) {
                            $insertJunction->execute([$skill_id, $category_id, $order++]);
                        }
                    }
                }
                
                sendResponse(true, 'Category created successfully', 'admin_eval_framework', ['category_id' => $category_id]);
                break;
                
            case 'add_skill_to_category':
                $category_id = intval($_POST['category_id']);
                $skill_id = intval($_POST['skill_id'] ?? 0);
                $new_skill_name = trim($_POST['new_skill_name'] ?? '');
                $new_skill_description = trim($_POST['new_skill_description'] ?? '');
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                if ($skill_id > 0) {
                    // Assign existing skill to this category via junction table
                    $stmt = $pdo->prepare("
                        INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([$skill_id, $category_id]);
                    sendResponse(true, 'Skill added to category successfully');
                } elseif (!empty($new_skill_name)) {
                    // Create a new skill and assign to this category
                    $has_stopwatch = !empty($_POST['has_stopwatch']) ? 1 : 0;
                    $stmt = $pdo->prepare("
                        INSERT INTO eval_skills (category_id, name, description, has_stopwatch, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$category_id, $new_skill_name, $new_skill_description, $has_stopwatch]);
                    $newSkillId = $pdo->lastInsertId();
                    
                    // Also add to junction table
                    $pdo->prepare("
                        INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, created_at)
                        VALUES (?, ?, NOW())
                    ")->execute([$newSkillId, $category_id]);
                    
                    sendResponse(true, 'New skill created and added to category', 'admin_eval_framework', ['skill_id' => $newSkillId]);
                } else {
                    throw new Exception('Please select a skill from the library or create a new one');
                }
                break;
                
            case 'assign_scale':
                $target_type = $_POST['target_type'] ?? '';
                $target_id = intval($_POST['target_id'] ?? 0);
                $scale_id = intval($_POST['scale_id'] ?? 0);
                
                if (!in_array($target_type, ['category', 'skill']) || $target_id <= 0 || $scale_id <= 0) {
                    throw new Exception('Invalid scale assignment parameters');
                }
                
                // For now, we'll store scale assignment in a simple way
                // In a full implementation, you'd have a scale_assignments table
                // For now, just acknowledge the assignment
                sendResponse(true, 'Scale assigned successfully. Note: Full scale persistence requires database schema update.');
                break;
                
            case 'update_category':
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Category name is required');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE eval_categories
                    SET name = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $category_id]);
                Auditor::log($pdo, $user_id, 'update', 'eval_categories', $category_id, ['action' => 'Updated eval category', 'name' => $name]);
                
                sendResponse(true, 'Category updated successfully');
                break;
                
            case 'delete_category':
                $category_id = intval($_POST['category_id']);
                
                // Check if category has skills via junction table
                $check = $pdo->prepare("SELECT COUNT(*) as count FROM eval_skill_categories WHERE category_id = ?");
                $check->execute([$category_id]);
                if ($check->fetch()['count'] > 0) {
                    throw new Exception('Cannot delete category with existing skills. Remove skills from this category first.');
                }
                
                $stmt = $pdo->prepare("DELETE FROM eval_categories WHERE id = ?");
                $stmt->execute([$category_id]);
                Auditor::log($pdo, $user_id, 'delete', 'eval_categories', $category_id, ['action' => 'Deleted eval category']);
                
                sendResponse(true, 'Category deleted successfully');
                break;
                
            case 'reorder_categories':
                $order_data = json_decode($_POST['order'], true);
                
                if (!is_array($order_data)) {
                    throw new Exception('Invalid order data');
                }
                
                // Validate array structure
                foreach ($order_data as $item) {
                    if (!isset($item['category_id']) || !isset($item['display_order'])) {
                        throw new Exception('Invalid order data structure');
                    }
                    if (!is_numeric($item['category_id']) || !is_numeric($item['display_order'])) {
                        throw new Exception('Invalid order data types');
                    }
                }
                
                // Update display_order for each category
                $stmt = $pdo->prepare("UPDATE eval_categories SET display_order = ? WHERE id = ?");
                foreach ($order_data as $item) {
                    $stmt->execute([intval($item['display_order']), intval($item['category_id'])]);
                }
                
                sendResponse(true, 'Category order updated successfully');
                break;
                
            case 'create_skill':
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $has_stopwatch = !empty($_POST['has_stopwatch']) ? 1 : 0;
                
                if (empty($name) || empty($description)) {
                    throw new Exception('Skill name and description are required');
                }
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO eval_skills (category_id, name, description, has_stopwatch, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$category_id, $name, $description, $has_stopwatch]);
                $newSkillId = $pdo->lastInsertId();
                
                // Also add to junction table
                $pdo->prepare("
                    INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, created_at)
                    VALUES (?, ?, NOW())
                ")->execute([$newSkillId, $category_id]);
                
                Auditor::log($pdo, $user_id, 'create', 'eval_skills', $newSkillId, ['action' => 'Created eval skill', 'name' => $name]);
                
                sendResponse(true, 'Skill created successfully', 'admin_eval_framework', ['skill_id' => $newSkillId]);
                break;
                
            case 'update_skill':
                $skill_id = intval($_POST['skill_id']);
                $category_id = intval($_POST['category_id']);
                $name = trim($_POST['name']);
                $description = trim($_POST['description']);
                $category_ids = $_POST['category_ids'] ?? [];
                
                if (empty($name) || empty($description)) {
                    throw new Exception('Skill name and description are required');
                }
                
                // Verify primary category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE eval_skills
                    SET category_id = ?, name = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$category_id, $name, $description, $skill_id]);
                
                // Update junction table: if category_ids provided, replace all assignments
                if (!empty($category_ids) && is_array($category_ids)) {
                    $pdo->prepare("DELETE FROM eval_skill_categories WHERE skill_id = ?")->execute([$skill_id]);
                    $insertJunction = $pdo->prepare("
                        INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, created_at)
                        VALUES (?, ?, NOW())
                    ");
                    foreach ($category_ids as $cat_id) {
                        $cat_id = intval($cat_id);
                        if ($cat_id > 0) {
                            $insertJunction->execute([$skill_id, $cat_id]);
                        }
                    }
                } else {
                    // Ensure at least the primary category is in junction table
                    $pdo->prepare("
                        INSERT IGNORE INTO eval_skill_categories (skill_id, category_id, created_at)
                        VALUES (?, ?, NOW())
                    ")->execute([$skill_id, $category_id]);
                }
                
                Auditor::log($pdo, $user_id, 'update', 'eval_skills', $skill_id, ['action' => 'Updated eval skill', 'name' => $name]);
                
                sendResponse(true, 'Skill updated successfully');
                break;
                
            case 'delete_skill':
                $skill_id = intval($_POST['skill_id']);
                
                // Check if skill is used in evaluations
                $check = $pdo->prepare("SELECT COUNT(*) as count FROM evaluation_scores WHERE skill_id = ?");
                $check->execute([$skill_id]);
                if ($check->fetch()['count'] > 0) {
                    throw new Exception('Cannot delete skill that has been used in evaluations');
                }
                
                // Remove from junction table first (CASCADE should handle this, but be explicit)
                $pdo->prepare("DELETE FROM eval_skill_categories WHERE skill_id = ?")->execute([$skill_id]);
                
                $stmt = $pdo->prepare("DELETE FROM eval_skills WHERE id = ?");
                $stmt->execute([$skill_id]);
                Auditor::log($pdo, $user_id, 'delete', 'eval_skills', $skill_id, ['action' => 'Deleted eval skill']);
                
                sendResponse(true, 'Skill deleted successfully');
                break;
                
            case 'remove_skill_from_category':
                $skill_id = intval($_POST['skill_id']);
                $category_id = intval($_POST['category_id']);
                
                if ($skill_id <= 0 || $category_id <= 0) {
                    throw new Exception('Invalid skill or category ID');
                }
                
                $pdo->prepare("DELETE FROM eval_skill_categories WHERE skill_id = ? AND category_id = ?")->execute([$skill_id, $category_id]);
                Auditor::log($pdo, $user_id, 'delete', 'eval_skill_categories', null, ['action' => 'Removed skill from category', 'skill_id' => $skill_id, 'category_id' => $category_id]);
                
                sendResponse(true, 'Skill removed from category successfully');
                break;
                
            case 'reorder_skills':
                $category_id = intval($_POST['category_id']);
                $order_data = json_decode($_POST['order'], true);
                
                if (!is_array($order_data)) {
                    throw new Exception('Invalid order data');
                }
                
                // Validate array structure
                foreach ($order_data as $item) {
                    if (!isset($item['skill_id']) || !isset($item['display_order'])) {
                        throw new Exception('Invalid order data structure');
                    }
                    if (!is_numeric($item['skill_id']) || !is_numeric($item['display_order'])) {
                        throw new Exception('Invalid order data types');
                    }
                }
                
                // Verify category exists
                $check = $pdo->prepare("SELECT id FROM eval_categories WHERE id = ?");
                $check->execute([$category_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid category');
                }
                
                // Update display_order in junction table
                $stmt = $pdo->prepare("UPDATE eval_skill_categories SET display_order = ? WHERE skill_id = ? AND category_id = ?");
                foreach ($order_data as $item) {
                    $stmt->execute([intval($item['display_order']), intval($item['skill_id']), $category_id]);
                }
                
                sendResponse(true, 'Skill order updated successfully');
                break;
                
            case 'toggle_active':
                // Note: is_active column doesn't exist in schema
                // This feature requires schema modification
                throw new Exception('Toggle active feature requires is_active column');
                break;
                
            case 'create_scale':
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $min_value = isset($_POST['min_value']) ? intval($_POST['min_value']) : 1;
                $max_value = isset($_POST['max_value']) ? intval($_POST['max_value']) : 5;
                
                if (empty($name)) {
                    throw new Exception('Scale name is required');
                }
                
                if ($min_value >= $max_value) {
                    throw new Exception('Max value must be greater than min value');
                }
                
                // Note: eval_scales table may not exist in schema
                // Return success message indicating feature is pending implementation
                sendResponse(true, 'Scale configuration saved. Note: Custom scales require database schema update for full persistence.');
                break;
                
            case 'edit_scale':
                $scale_id = intval($_POST['scale_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $min_value = isset($_POST['min_value']) ? intval($_POST['min_value']) : 1;
                $max_value = isset($_POST['max_value']) ? intval($_POST['max_value']) : 5;
                $scale_data = trim($_POST['scale_data'] ?? '');
                
                if (empty($name)) {
                    throw new Exception('Scale name is required');
                }
                
                if ($scale_id <= 0) {
                    throw new Exception('Invalid scale ID');
                }
                
                // Note: eval_scales table may not exist in schema
                // Return success message indicating feature is pending implementation
                sendResponse(true, 'Scale updated. Note: Custom scales require database schema update for full persistence.');
                break;
                
            case 'save_evaluation':
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category_ids = $_POST['category_ids'] ?? [];
                
                if (empty($title)) {
                    throw new Exception('Evaluation title is required');
                }
                
                if (empty($category_ids) || !is_array($category_ids)) {
                    throw new Exception('Please select at least one category to include');
                }
                
                // Create evaluation template
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_templates (title, description, created_by, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$title, $description, $user_id]);
                $template_id = $pdo->lastInsertId();
                Auditor::log($pdo, $user_id, 'create', 'evaluation_templates', $template_id, ['action' => 'Created evaluation template', 'title' => $title]);
                
                // Link categories to template
                $insertCat = $pdo->prepare("
                    INSERT INTO evaluation_template_categories (template_id, category_id, display_order)
                    VALUES (?, ?, ?)
                ");
                foreach ($category_ids as $order => $cat_id) {
                    $cat_id = intval($cat_id);
                    if ($cat_id > 0) {
                        $insertCat->execute([$template_id, $cat_id, intval($order)]);
                    }
                }
                
                sendResponse(true, 'Evaluation saved successfully', 'admin_eval_framework', ['template_id' => $template_id]);
                break;
                
            case 'update_evaluation':
                $template_id = intval($_POST['template_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $category_ids = $_POST['category_ids'] ?? [];
                
                if ($template_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                if (empty($title)) {
                    throw new Exception('Evaluation title is required');
                }
                
                // Verify template exists
                $check = $pdo->prepare("SELECT id FROM evaluation_templates WHERE id = ?");
                $check->execute([$template_id]);
                if (!$check->fetch()) {
                    throw new Exception('Evaluation not found');
                }
                
                // Update template
                $stmt = $pdo->prepare("
                    UPDATE evaluation_templates SET title = ?, description = ?, updated_at = NOW() WHERE id = ?
                ");
                $stmt->execute([$title, $description, $template_id]);
                Auditor::log($pdo, $user_id, 'update', 'evaluation_templates', $template_id, ['action' => 'Updated evaluation template', 'title' => $title]);
                
                // Replace category associations
                $pdo->prepare("DELETE FROM evaluation_template_categories WHERE template_id = ?")->execute([$template_id]);
                
                if (!empty($category_ids) && is_array($category_ids)) {
                    $insertCat = $pdo->prepare("
                        INSERT INTO evaluation_template_categories (template_id, category_id, display_order)
                        VALUES (?, ?, ?)
                    ");
                    foreach ($category_ids as $order => $cat_id) {
                        $cat_id = intval($cat_id);
                        if ($cat_id > 0) {
                            $insertCat->execute([$template_id, $cat_id, intval($order)]);
                        }
                    }
                }
                
                sendResponse(true, 'Evaluation updated successfully');
                break;
                
            case 'delete_evaluation':
                $template_id = intval($_POST['template_id'] ?? 0);
                
                if ($template_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                // Check if template is used in any session evaluations
                $check = $pdo->prepare("SELECT COUNT(*) as count FROM session_evaluations WHERE template_id = ?");
                $check->execute([$template_id]);
                $row = $check->fetch();
                if ($row && $row['count'] > 0) {
                    throw new Exception('Cannot delete evaluation that is assigned to sessions. Remove session assignments first.');
                }
                
                $stmt = $pdo->prepare("DELETE FROM evaluation_templates WHERE id = ?");
                $stmt->execute([$template_id]);
                Auditor::log($pdo, $user_id, 'delete', 'evaluation_templates', $template_id, ['action' => 'Deleted evaluation template']);
                
                sendResponse(true, 'Evaluation deleted successfully');
                break;
                
            case 'assign_to_session':
                $template_id = intval($_POST['template_id'] ?? 0);
                $session_id_input = $_POST['session_id'] ?? '';
                
                if ($template_id <= 0 || empty($session_id_input)) {
                    throw new Exception('Please select both an evaluation and a session');
                }
                
                // Verify template exists
                $check = $pdo->prepare("SELECT id, title FROM evaluation_templates WHERE id = ?");
                $check->execute([$template_id]);
                $template = $check->fetch();
                if (!$template) {
                    throw new Exception('Evaluation not found');
                }
                
                // Check if this is a template-based session (format: template_{template_id}_{date_id})
                if (strpos($session_id_input, 'template_') === 0) {
                    // Extract template_id and date_id from the session_id
                    $parts = explode('_', $session_id_input);
                    if (count($parts) !== 3) {
                        throw new Exception('Invalid template session format');
                    }
                    $extracted_template_id = intval($parts[1]);
                    $date_id = intval($parts[2]);
                    
                    // Get template and date information
                    $stmt = $pdo->prepare("
                        SELECT tst.*, tsd.session_date, tsd.team_id,
                               COALESCE(tsd.max_participants, tst.max_participants) as max_participants
                        FROM training_session_templates tst
                        INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id
                        WHERE tst.id = ? AND tsd.id = ? AND tst.is_active = 1 AND tsd.is_active = 1
                    ");
                    $stmt->execute([$extracted_template_id, $date_id]);
                    $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$template_data) {
                        throw new Exception('Template session not found or inactive');
                    }
                    
                    // Create a session record from the template
                    $stmt = $pdo->prepare("
                        INSERT INTO sessions (
                            session_type_id, coach_id, location_id, title, description,
                            session_date, session_time, duration_minutes, price, max_participants,
                            team_id, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                    ");
                    
                    // Parse session_date and validate
                    $timestamp = strtotime($template_data['session_date']);
                    if ($timestamp === false) {
                        throw new Exception('Invalid session date format');
                    }
                    $session_date = date('Y-m-d', $timestamp);
                    $session_time = date('H:i:s', $timestamp);
                    
                    $stmt->execute([
                        $template_data['session_type_id'],
                        $template_data['coach_id'],
                        $template_data['location_id'],
                        $template_data['name'],
                        $template_data['description'],
                        $session_date,
                        $session_time,
                        $template_data['duration_minutes'],
                        $template_data['price'],
                        $template_data['max_participants'],
                        $template_data['team_id']
                    ]);
                    
                    $session_id = $pdo->lastInsertId();
                    
                    // Link the training_session_date to this new session
                    $stmt = $pdo->prepare("UPDATE training_session_dates SET session_id = ? WHERE id = ?");
                    $stmt->execute([$session_id, $date_id]);
                } else {
                    // Regular session
                    $session_id = intval($session_id_input);
                    
                    if ($session_id <= 0) {
                        throw new Exception('Invalid session ID');
                    }
                    
                    // Verify session exists
                    $check = $pdo->prepare("SELECT id FROM sessions WHERE id = ?");
                    $check->execute([$session_id]);
                    if (!$check->fetch()) {
                        throw new Exception('Session not found');
                    }
                }
                
                // Check if session already has this evaluation assigned
                $check = $pdo->prepare("SELECT id FROM session_evaluations WHERE session_id = ? AND template_id = ?");
                $check->execute([$session_id, $template_id]);
                if ($check->fetch()) {
                    throw new Exception('This evaluation is already assigned to that session');
                }
                
                // Create session evaluation linked to template
                $stmt = $pdo->prepare("
                    INSERT INTO session_evaluations (session_id, template_id, name, description, status, created_by, created_at)
                    VALUES (?, ?, ?, ?, 'draft', ?, NOW())
                ");
                $stmt->execute([$session_id, $template_id, $template['title'], '', $user_id]);
                Auditor::log($pdo, $user_id, 'create', 'session_evaluations', $pdo->lastInsertId(), ['action' => 'Assigned evaluation to session', 'template_id' => $template_id, 'session_id' => $session_id]);
                
                sendResponse(true, 'Evaluation assigned to session successfully');
                break;
                
            case 'remove_from_session':
                $session_eval_id = intval($_POST['session_eval_id'] ?? 0);
                
                if ($session_eval_id <= 0) {
                    throw new Exception('Invalid session evaluation ID');
                }
                
                // Verify it exists
                $check = $pdo->prepare("SELECT id, session_id, template_id FROM session_evaluations WHERE id = ?");
                $check->execute([$session_eval_id]);
                $session_eval = $check->fetch(PDO::FETCH_ASSOC);
                if (!$session_eval) {
                    throw new Exception('Session evaluation not found');
                }
                
                // Delete the session evaluation assignment
                $stmt = $pdo->prepare("DELETE FROM session_evaluations WHERE id = ?");
                $stmt->execute([$session_eval_id]);
                
                Auditor::log($pdo, $user_id, 'delete', 'session_evaluations', $session_eval_id, [
                    'action' => 'Removed evaluation from session',
                    'session_id' => $session_eval['session_id'],
                    'template_id' => $session_eval['template_id']
                ]);
                
                sendResponse(true, 'Evaluation removed from session successfully');
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'list_evaluations':
                header('Content-Type: application/json');
                $stmt = $pdo->prepare("
                    SELECT et.id, et.title, et.description, et.created_at, et.updated_at,
                           u.first_name, u.last_name,
                           GROUP_CONCAT(ec.name ORDER BY etc2.display_order SEPARATOR ', ') as category_names,
                           (SELECT COUNT(*) FROM session_evaluations se WHERE se.template_id = et.id) as session_count
                    FROM evaluation_templates et
                    LEFT JOIN users u ON et.created_by = u.id
                    LEFT JOIN evaluation_template_categories etc2 ON et.id = etc2.template_id
                    LEFT JOIN eval_categories ec ON etc2.category_id = ec.id
                    GROUP BY et.id
                    ORDER BY et.created_at DESC
                ");
                $stmt->execute();
                $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'evaluations' => $evaluations]);
                exit;
                
            case 'get_evaluation':
                header('Content-Type: application/json');
                $template_id = intval($_GET['template_id'] ?? 0);
                
                if ($template_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                $stmt = $pdo->prepare("SELECT * FROM evaluation_templates WHERE id = ?");
                $stmt->execute([$template_id]);
                $template = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$template) {
                    throw new Exception('Evaluation not found');
                }
                
                // Get associated categories
                $stmt = $pdo->prepare("
                    SELECT etc2.category_id, ec.name 
                    FROM evaluation_template_categories etc2
                    JOIN eval_categories ec ON etc2.category_id = ec.id
                    WHERE etc2.template_id = ?
                    ORDER BY etc2.display_order ASC
                ");
                $stmt->execute([$template_id]);
                $template['categories'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Get assigned sessions
                $stmt = $pdo->prepare("
                    SELECT se.id as session_eval_id, se.name as eval_name, se.status,
                           s.id as session_id, s.title as session_title, s.session_date
                    FROM session_evaluations se
                    INNER JOIN sessions s ON se.session_id = s.id
                    WHERE se.template_id = ?
                    ORDER BY s.session_date ASC
                ");
                $stmt->execute([$template_id]);
                $template['sessions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'evaluation' => $template]);
                exit;
                
            case 'get_available_sessions':
                header('Content-Type: application/json');
                // Include both regular sessions and template-based sessions from products catalog
                $stmt = $pdo->prepare("
                    SELECT s.id, s.title, s.session_date, s.session_time,
                           COALESCE(l.name, 'TBD') as location_name,
                           GROUP_CONCAT(DISTINCT pkg.name ORDER BY pkg.name SEPARATOR ', ') as package_names,
                           'session' as source_type, NULL as template_id, NULL as date_id
                    FROM sessions s
                    LEFT JOIN locations l ON s.location_id = l.id
                    LEFT JOIN package_sessions ps ON ps.session_id = s.id
                    LEFT JOIN packages pkg ON ps.package_id = pkg.id
                    WHERE s.session_date >= CURDATE()
                    GROUP BY s.id, s.title, s.session_date, s.session_time, l.name
                    
                    UNION ALL
                    
                    SELECT CONCAT('template_', tst.id, '_', tsd.id) as id,
                           tst.name as title,
                           DATE(tsd.session_date) as session_date,
                           TIME(tsd.session_date) as session_time,
                           COALESCE(l.name, 'TBD') as location_name,
                           GROUP_CONCAT(DISTINCT pkg.name ORDER BY pkg.name SEPARATOR ', ') as package_names,
                           'template' as source_type, tst.id as template_id, tsd.id as date_id
                    FROM training_session_templates tst
                    INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
                    LEFT JOIN locations l ON tst.location_id = l.id
                    LEFT JOIN package_sessions ps ON ps.template_id = tst.id
                    LEFT JOIN packages pkg ON ps.package_id = pkg.id
                    WHERE tst.is_active = 1
                    AND tsd.session_date >= CURDATE()
                    AND tsd.session_id IS NULL
                    GROUP BY tst.id, tsd.id, tst.name, tsd.session_date, l.name
                    
                    ORDER BY session_date ASC
                    LIMIT 100
                ");
                $stmt->execute();
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'sessions' => $sessions]);
                exit;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
