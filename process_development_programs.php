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
        case 'get_drill_details':
            handleGetDrillDetails($pdo, $user_id, $input);
            break;
        case 'upload_dev_video':
            handleUploadDevVideo($pdo, $user_id, $input);
            break;
        case 'create_appointment':
            if (!$canManageDevPrograms) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleCreateAppointment($pdo, $user_id, $input);
            break;
        case 'cancel_appointment':
            handleCancelAppointment($pdo, $user_id, $input, $canManageDevPrograms);
            break;
        case 'confirm_dev_video_upload':
            handleConfirmDevVideoUpload($pdo, $user_id, $input);
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

/**
 * Get full drill details for athlete drill detail view
 */
function handleGetDrillDetails($pdo, $user_id, $input) {
    $drill_assignment_id = (int)($input['drill_assignment_id'] ?? 0);
    if (!$drill_assignment_id) {
        echo json_encode(['success' => false, 'error' => 'Missing drill assignment ID']);
        return;
    }
    
    // Get drill details with assignment info
    $stmt = $pdo->prepare("
        SELECT dpd.id as assignment_id, dpd.status, dpd.coach_notes, dpd.sort_order,
               d.id as drill_id, d.title, d.description, d.setup, d.coaching_points, d.progression,
               d.video_url, d.custom_image, d.diagram_data,
               u.first_name as coach_first, u.last_name as coach_last,
               dpe.athlete_id, dpe.program_type
        FROM development_program_drills dpd
        JOIN drills d ON dpd.drill_id = d.id
        JOIN users u ON dpd.assigned_by = u.id
        JOIN development_program_enrollments dpe ON dpd.enrollment_id = dpe.id
        WHERE dpd.id = ? AND dpe.athlete_id = ?
    ");
    $stmt->execute([$drill_assignment_id, $user_id]);
    $drill = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$drill) {
        echo json_encode(['success' => false, 'error' => 'Drill not found or access denied']);
        return;
    }
    
    if (function_exists('decryptUserRows')) {
        $coach_row = decryptUserRows([['first_name' => $drill['coach_first'], 'last_name' => $drill['coach_last']]])[0];
        $drill['coach_first'] = $coach_row['first_name'];
        $drill['coach_last'] = $coach_row['last_name'];
    }
    
    // Get videos submitted for this drill
    $videos_stmt = $pdo->prepare("
        SELECT id, title, video_url, video_upload_path, status, coach_feedback, created_at
        FROM development_program_videos
        WHERE drill_assignment_id = ? AND athlete_id = ?
        ORDER BY created_at DESC
    ");
    $videos_stmt->execute([$drill_assignment_id, $user_id]);
    $drill['videos'] = $videos_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode(['success' => true, 'drill' => $drill]);
}

/**
 * Upload a development video for coach review
 */
function handleUploadDevVideo($pdo, $user_id, $input) {
    $enrollment_id = (int)($input['enrollment_id'] ?? 0);
    $drill_assignment_id = !empty($input['drill_assignment_id']) ? (int)$input['drill_assignment_id'] : null;
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $video_url = trim($input['video_url'] ?? '');
    
    if (!$enrollment_id || !$title) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Verify enrollment belongs to this athlete
    $check = $pdo->prepare("SELECT id, program_type FROM development_program_enrollments WHERE id = ? AND athlete_id = ?");
    $check->execute([$enrollment_id, $user_id]);
    $enrollment = $check->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) {
        echo json_encode(['success' => false, 'error' => 'Enrollment not found']);
        return;
    }
    
    $video_upload_path = null;
    
    // Handle video file upload if provided
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['video_file'];
        
        $validator = new FileUploadValidator();
        $validation = $validator->validateVideo($file);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'error' => $validation['error']]);
            return;
        }
        
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = 'dev_video_' . bin2hex(random_bytes(16)) . '.' . $extension;
        
        $persist = persistUploadedFile($pdo, $file['tmp_name'], 'development/videos', $filename, '', true);
        if (!$persist['success']) {
            echo json_encode(['success' => false, 'error' => 'Video upload failed']);
            return;
        }
        $video_upload_path = $persist['rustfs_url'] ?? null;
    }
    
    $final_url = $video_upload_path ?: ($video_url ?: null);
    
    $stmt = $pdo->prepare("INSERT INTO development_program_videos (enrollment_id, athlete_id, drill_assignment_id, title, description, video_url, video_upload_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $user_id, $drill_assignment_id, $title, $description ?: null, $final_url, $video_upload_path]);
    
    // If this is for a specific drill, update its status to in_progress
    if ($drill_assignment_id) {
        $pdo->prepare("UPDATE development_program_drills SET status = 'in_progress' WHERE id = ? AND status = 'assigned'")->execute([$drill_assignment_id]);
    }
    
    // Notify coaches about the new video
    $role_to_notify = $enrollment['program_type'];
    $coaches_stmt = $pdo->prepare("SELECT DISTINCT ur.user_id FROM user_roles ur WHERE ur.role IN (?, 'admin')");
    $coaches_stmt->execute([$role_to_notify]);
    $notify_ids = $coaches_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $athlete_stmt->execute([$user_id]);
    $athlete = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $athlete = decryptUserRows([$athlete])[0];
    }
    $athlete_name = trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? ''));
    $safe_athlete_name = htmlspecialchars($athlete_name, ENT_QUOTES, 'UTF-8');
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url) VALUES (?, 'dev_video_upload', ?, ?, '?page=development_programs')");
    foreach ($notify_ids as $nid) {
        if ((int)$nid !== $user_id) {
            $notif_stmt->execute([$nid, 'New Development Video', $safe_athlete_name . ' has uploaded a new development video: ' . $safe_title]);
        }
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Confirm a development video upload after presigned URL direct upload to RustFS.
 * This matches the application's standard video upload confirmation pattern.
 * Validates the upload nonce stored in session, creates the DB record, and notifies coaches.
 */
function handleConfirmDevVideoUpload($pdo, $user_id, $input) {
    $upload_nonce = trim($input['upload_nonce'] ?? $_POST['upload_nonce'] ?? '');
    $enrollment_id = (int)($input['enrollment_id'] ?? $_POST['enrollment_id'] ?? 0);
    $drill_assignment_id = !empty($input['drill_assignment_id'] ?? $_POST['drill_assignment_id'] ?? '') ? (int)($input['drill_assignment_id'] ?? $_POST['drill_assignment_id'] ?? 0) : null;
    $title = trim($input['title'] ?? $_POST['title'] ?? '');
    $description = trim($input['description'] ?? $_POST['description'] ?? '');
    
    if (!$upload_nonce || !$enrollment_id || !$title) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    
    // Validate nonce from session
    $pending = $_SESSION['pending_video_upload'] ?? null;
    if (!$pending || !hash_equals($pending['nonce'] ?? '', $upload_nonce)) {
        echo json_encode(['success' => false, 'error' => 'Invalid or expired upload session']);
        return;
    }
    
    // Verify enrollment belongs to this athlete
    $check = $pdo->prepare("SELECT id, program_type FROM development_program_enrollments WHERE id = ? AND athlete_id = ?");
    $check->execute([$enrollment_id, $user_id]);
    $enrollment = $check->fetch(PDO::FETCH_ASSOC);
    if (!$enrollment) {
        echo json_encode(['success' => false, 'error' => 'Enrollment not found']);
        return;
    }
    
    // Build video URL from pending upload metadata
    $video_url = $pending['rustfs_url'] ?? $pending['public_url'] ?? null;
    $video_upload_path = $pending['object_key'] ?? null;
    
    // Clean up session
    unset($_SESSION['pending_video_upload']);
    
    $stmt = $pdo->prepare("INSERT INTO development_program_videos (enrollment_id, athlete_id, drill_assignment_id, title, description, video_url, video_upload_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $user_id, $drill_assignment_id, $title, $description ?: null, $video_url, $video_upload_path]);
    
    // If this is for a specific drill, update its status to in_progress
    if ($drill_assignment_id) {
        $pdo->prepare("UPDATE development_program_drills SET status = 'in_progress' WHERE id = ? AND status = 'assigned'")->execute([$drill_assignment_id]);
    }
    
    // Notify coaches about the new video
    $role_to_notify = $enrollment['program_type'];
    $coaches_stmt = $pdo->prepare("SELECT DISTINCT ur.user_id FROM user_roles ur WHERE ur.role IN (?, 'admin')");
    $coaches_stmt->execute([$role_to_notify]);
    $notify_ids = $coaches_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $athlete_stmt->execute([$user_id]);
    $athlete = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $athlete = decryptUserRows([$athlete])[0];
    }
    $athlete_name = trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? ''));
    $safe_athlete_name = htmlspecialchars($athlete_name, ENT_QUOTES, 'UTF-8');
    $safe_title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url) VALUES (?, 'dev_video_upload', ?, ?, '?page=development_programs')");
    foreach ($notify_ids as $nid) {
        if ((int)$nid !== $user_id) {
            $notif_stmt->execute([$nid, 'New Development Video', $safe_athlete_name . ' has uploaded a new development video: ' . $safe_title]);
        }
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Create a development appointment (coach schedules with athlete)
 */
function handleCreateAppointment($pdo, $user_id, $input) {
    $min_duration = 5;    // Minimum appointment duration in minutes
    $max_duration = 480;  // Maximum appointment duration in minutes (8 hours)

    $enrollment_id = (int)($input['enrollment_id'] ?? 0);
    $athlete_id = (int)($input['athlete_id'] ?? 0);
    $appointment_type = $input['appointment_type'] ?? '';
    $title = trim($input['title'] ?? '');
    $appointment_date = $input['appointment_date'] ?? '';
    $appointment_time = $input['appointment_time'] ?? '';
    $duration_minutes = (int)($input['duration_minutes'] ?? 30);
    $location = trim($input['location'] ?? '');
    $meeting_url = trim($input['meeting_url'] ?? '');
    $phone_number = trim($input['phone_number'] ?? '');
    $description = trim($input['description'] ?? '');
    
    if (!$enrollment_id || !$athlete_id || !in_array($appointment_type, ['call', 'video_call', 'in_person'])) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }
    if (!$title || !$appointment_date || !$appointment_time) {
        echo json_encode(['success' => false, 'error' => 'Title, date and time are required']);
        return;
    }
    if ($duration_minutes < $min_duration || $duration_minutes > $max_duration) {
        echo json_encode(['success' => false, 'error' => "Duration must be between $min_duration and $max_duration minutes"]);
        return;
    }
    
    // Verify enrollment
    $check = $pdo->prepare("SELECT id FROM development_program_enrollments WHERE id = ? AND athlete_id = ?");
    $check->execute([$enrollment_id, $athlete_id]);
    if (!$check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Enrollment not found']);
        return;
    }
    
    $stmt = $pdo->prepare("INSERT INTO development_appointments (enrollment_id, coach_id, athlete_id, appointment_type, title, description, appointment_date, appointment_time, duration_minutes, location, meeting_url, phone_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $enrollment_id, $user_id, $athlete_id, $appointment_type,
        $title, $description ?: null, $appointment_date, $appointment_time,
        $duration_minutes, $location ?: null, $meeting_url ?: null, $phone_number ?: null
    ]);
    
    // Notify the athlete
    $coach_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $coach_stmt->execute([$user_id]);
    $coach = $coach_stmt->fetch(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $coach = decryptUserRows([$coach])[0];
    }
    $coach_name = trim(($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? ''));
    
    $type_label = str_replace('_', ' ', $appointment_type);
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url) VALUES (?, 'dev_appointment', ?, ?, '?page=personal_development_my_program')");
    $notif_stmt->execute([
        $athlete_id,
        'New Development Appointment',
        htmlspecialchars($coach_name, ENT_QUOTES, 'UTF-8') . ' has scheduled a ' . $type_label . ': ' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . ' on ' . date('M j, Y', strtotime($appointment_date)) . ' at ' . date('g:i A', strtotime($appointment_time))
    ]);
    
    echo json_encode(['success' => true]);
}

/**
 * Cancel a development appointment
 */
function handleCancelAppointment($pdo, $user_id, $input, $canManageDevPrograms) {
    $appointment_id = (int)($input['appointment_id'] ?? 0);
    if (!$appointment_id) {
        echo json_encode(['success' => false, 'error' => 'Missing appointment ID']);
        return;
    }
    
    // Verify user has access (coach who created it, the athlete, or admin)
    $check = $pdo->prepare("SELECT id, coach_id, athlete_id FROM development_appointments WHERE id = ?");
    $check->execute([$appointment_id]);
    $appt = $check->fetch(PDO::FETCH_ASSOC);
    if (!$appt) {
        echo json_encode(['success' => false, 'error' => 'Appointment not found']);
        return;
    }
    
    if ((int)$appt['coach_id'] !== $user_id && (int)$appt['athlete_id'] !== $user_id && !$canManageDevPrograms) {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE development_appointments SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$appointment_id]);
    
    echo json_encode(['success' => true]);
}
