<?php
/**
 * Process Development Programs - AJAX Handler
 * Handles registration, drill management, messaging, and personal drill creation
 */

session_start();

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/file_upload_validator.php';

// Check AJAX request
if (empty($_SERVER['HTTP_X_REQUESTED_WITH']) || strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) !== 'xmlhttprequest') {
    header('Location: dashboard.php');
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

header('Content-Type: application/json');

$user_id = (int)$_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Get user roles list
$rolesStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
$rolesStmt->execute([$user_id]);
$user_roles_list = array_column($rolesStmt->fetchAll(PDO::FETCH_ASSOC), 'role');
$user_roles_list[] = $user_role;
$user_roles_list = array_unique($user_roles_list);

$isGoalieDev = in_array('goalie_dev', $user_roles_list);
$isPlayerDev = in_array('player_dev', $user_roles_list);
$isAdmin = in_array('admin', $user_roles_list);
$isAnyCoach = in_array('coach', $user_roles_list) || $isAdmin;
$canManageDevPrograms = ($isGoalieDev || $isPlayerDev || $isAdmin);

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Support FormData (multipart) requests for file uploads
if (empty($action) && !empty($_POST['action'])) {
    $action = $_POST['action'];
    $input = $_POST;
}

try {
    switch ($action) {
        case 'register':
            handleRegister($pdo, $user_id, $input);
            break;
        case 'add_drill':
            if (!$canManageDevPrograms) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleAddDrill($pdo, $user_id, $input);
            break;
        case 'remove_drill':
            if (!$canManageDevPrograms) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleRemoveDrill($pdo, $input);
            break;
        case 'update_drill_status':
            handleUpdateDrillStatus($pdo, $input);
            break;
        case 'send_message':
            handleSendMessage($pdo, $user_id, $input);
            break;
        case 'create_personal_drill':
            if (!$isAnyCoach && !$canManageDevPrograms) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleCreatePersonalDrill($pdo, $user_id, $input);
            break;
        case 'update_notification_template':
            if (!$isAdmin) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleUpdateNotificationTemplate($pdo, $user_id, $input);
            break;
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Development Programs Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'An internal error occurred']);
}

/**
 * Register athlete for a development program
 */
function handleRegister($pdo, $user_id, $input) {
    $program_type = $input['program_type'] ?? '';
    if (!in_array($program_type, ['goalie_dev', 'player_dev'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid program type']);
        return;
    }
    
    // Check if already enrolled
    $check = $pdo->prepare("SELECT id FROM development_program_enrollments WHERE athlete_id = ? AND program_type = ?");
    $check->execute([$user_id, $program_type]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Already enrolled in this program']);
        return;
    }
    
    // Create enrollment
    $stmt = $pdo->prepare("INSERT INTO development_program_enrollments (athlete_id, program_type) VALUES (?, ?)");
    $stmt->execute([$user_id, $program_type]);
    
    // Send notification to dev coaches with the matching role
    $role_to_notify = $program_type; // goalie_dev or player_dev
    $coaches_stmt = $pdo->prepare("
        SELECT DISTINCT ur.user_id 
        FROM user_roles ur 
        WHERE ur.role = ?
    ");
    $coaches_stmt->execute([$role_to_notify]);
    $coach_ids = $coaches_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Also notify admins
    $admin_stmt = $pdo->prepare("SELECT DISTINCT ur.user_id FROM user_roles ur WHERE ur.role = 'admin'");
    $admin_stmt->execute();
    $admin_ids = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
    $notify_ids = array_unique(array_merge($coach_ids, $admin_ids));
    
    // Get notification template
    $tmpl_stmt = $pdo->prepare("SELECT subject, body FROM development_notification_templates WHERE program_type = ?");
    $tmpl_stmt->execute([$program_type]);
    $template = $tmpl_stmt->fetch(PDO::FETCH_ASSOC);
    
    $notif_title = $template['subject'] ?? 'New Development Program Registration';
    $notif_body = $template['body'] ?? 'A new athlete has registered for a development program.';
    
    // Get athlete name for the notification
    $athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $athlete_stmt->execute([$user_id]);
    $athlete = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $athlete = decryptUserRows([$athlete])[0];
    }
    $athlete_name = htmlspecialchars(trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $notif_body .= "\n\nAthlete: " . $athlete_name;
    
    // Create notifications
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url) VALUES (?, 'dev_program_registration', ?, ?, '?page=development_programs')");
    foreach ($notify_ids as $nid) {
        $notif_stmt->execute([$nid, $notif_title, $notif_body]);
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Add a drill from the library to an athlete's program
 */
function handleAddDrill($pdo, $user_id, $input) {
    $enrollment_id = (int)($input['enrollment_id'] ?? 0);
    $drill_id = (int)($input['drill_id'] ?? 0);
    $coach_notes = trim($input['coach_notes'] ?? '');
    
    if (!$enrollment_id || !$drill_id) {
        echo json_encode(['success' => false, 'error' => 'Missing enrollment or drill ID']);
        return;
    }
    
    // Verify enrollment exists
    $check = $pdo->prepare("SELECT id FROM development_program_enrollments WHERE id = ?");
    $check->execute([$enrollment_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Enrollment not found']);
        return;
    }
    
    // Get max sort order
    $max_stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM development_program_drills WHERE enrollment_id = ?");
    $max_stmt->execute([$enrollment_id]);
    $sort_order = (int)$max_stmt->fetchColumn();
    
    $stmt = $pdo->prepare("INSERT INTO development_program_drills (enrollment_id, drill_id, assigned_by, sort_order, coach_notes) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $drill_id, $user_id, $sort_order, $coach_notes ?: null]);
    
    echo json_encode(['success' => true]);
}

/**
 * Remove a drill from an athlete's program
 */
function handleRemoveDrill($pdo, $input) {
    $drill_assignment_id = (int)($input['drill_assignment_id'] ?? 0);
    if (!$drill_assignment_id) {
        echo json_encode(['success' => false, 'error' => 'Missing drill assignment ID']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM development_program_drills WHERE id = ?");
    $stmt->execute([$drill_assignment_id]);
    
    echo json_encode(['success' => true]);
}

/**
 * Update drill status
 */
function handleUpdateDrillStatus($pdo, $input) {
    $drill_assignment_id = (int)($input['drill_assignment_id'] ?? 0);
    $status = $input['status'] ?? '';
    
    if (!$drill_assignment_id || !in_array($status, ['assigned', 'in_progress', 'completed'])) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE development_program_drills SET status = ? WHERE id = ?");
    $stmt->execute([$status, $drill_assignment_id]);
    
    echo json_encode(['success' => true]);
}

/**
 * Send a message in a development program chat
 */
function handleSendMessage($pdo, $user_id, $input) {
    $enrollment_id = (int)($input['enrollment_id'] ?? 0);
    $message = trim($input['message'] ?? '');
    $video_url = trim($input['video_url'] ?? '');
    
    if (!$enrollment_id || !$message) {
        echo json_encode(['success' => false, 'error' => 'Missing enrollment ID or message']);
        return;
    }
    
    // Verify enrollment exists and user has access
    $check = $pdo->prepare("SELECT athlete_id FROM development_program_enrollments WHERE id = ?");
    $check->execute([$enrollment_id]);
    $enrollment = $check->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) {
        echo json_encode(['success' => false, 'error' => 'Enrollment not found']);
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO development_program_messages (enrollment_id, sender_id, message, video_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $user_id, $message, $video_url ?: null]);
    
    echo json_encode(['success' => true]);
}

/**
 * Create a personal drill and add it to the drill library
 */
function handleCreatePersonalDrill($pdo, $user_id, $input) {
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $video_upload_path = null;
    
    if (!$title) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        return;
    }
    
    // Handle video file upload
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['video_file'];
        
        $validator = new FileUploadValidator();
        $validation = $validator->validateVideo($file);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'error' => $validation['error']]);
            return;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'personal_drill_' . bin2hex(random_bytes(16)) . '.' . $extension;
        
        $persist = persistUploadedFile($pdo, $file['tmp_name'], 'drills/videos', $filename, '', true);
        if (!$persist['success']) {
            echo json_encode(['success' => false, 'error' => 'Video upload failed. Please try again.']);
            return;
        }
        $video_upload_path = $persist['rustfs_url'] ?? null;
    }
    
    $pdo->beginTransaction();
    try {
        // Add to the main drill library first
        $drill_stmt = $pdo->prepare("INSERT INTO drills (title, description, video_upload_path, created_by) VALUES (?, ?, ?, ?)");
        $drill_stmt->execute([$title, $description ?: null, $video_upload_path, $user_id]);
        $drill_id = (int)$pdo->lastInsertId();
        
        // Create personal drill record referencing the library drill
        $pd_stmt = $pdo->prepare("INSERT INTO personal_drills (title, description, video_upload_path, created_by) VALUES (?, ?, ?, ?)");
        $pd_stmt->execute([$title, $description ?: null, $video_upload_path, $user_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'drill_id' => $drill_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Update a development notification template (admin only)
 */
function handleUpdateNotificationTemplate($pdo, $user_id, $input) {
    $template_id = (int)($input['template_id'] ?? 0);
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');
    
    if (!$template_id || !$subject || !$body) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE development_notification_templates SET subject = ?, body = ?, updated_by = ? WHERE id = ?");
    $stmt->execute([$subject, $body, $user_id, $template_id]);
    
    echo json_encode(['success' => true]);
}
