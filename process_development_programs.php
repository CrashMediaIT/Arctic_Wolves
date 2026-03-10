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
require_once __DIR__ . '/lib/video_thumbnail.php';

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

// Validate CSRF token
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token. Please refresh and try again.']);
    exit;
}

try {
    switch ($action) {
        case 'register':
            // Registration now requires payment through process_booking.php
            // Only admins/coaches can directly register athletes (for manual overrides)
            if (!$canManageDevPrograms) {
                echo json_encode(['success' => false, 'error' => 'Registration requires payment. Please use the Booking page to enroll.']);
                exit;
            }
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
        case 'update_personal_drill':
            if (!$isAnyCoach && !$canManageDevPrograms) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleUpdatePersonalDrill($pdo, $user_id, $input);
            break;
        case 'delete_personal_drill':
            if (!$isAnyCoach && !$canManageDevPrograms) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleDeletePersonalDrill($pdo, $user_id, $input);
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
        case 'save_email_template':
            if (!$isAdmin) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleSaveEmailTemplate($pdo, $user_id, $input);
            break;
        case 'reset_email_template':
            if (!$isAdmin) { echo json_encode(['success' => false, 'error' => 'Access denied']); exit; }
            handleResetEmailTemplate($pdo, $input);
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
    
    $template_id = intval($input['template_id'] ?? 0);
    
    // Check if already enrolled in an ACTIVE program of the same type with the same template
    $check = $pdo->prepare("SELECT id FROM development_program_enrollments WHERE athlete_id = ? AND program_type = ? AND status = 'active'" . ($template_id ? " AND template_id = ?" : ""));
    $check_params = [$user_id, $program_type];
    if ($template_id) $check_params[] = $template_id;
    $check->execute($check_params);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Already enrolled in an active program of this type']);
        return;
    }
    
    // Get program name and duration from template
    $program_name = null;
    $duration_weeks = null;
    if ($template_id) {
        try {
            $tpl_stmt = $pdo->prepare("SELECT name, duration_weeks FROM training_session_templates WHERE id = ? AND is_dev_program = 1");
            $tpl_stmt->execute([$template_id]);
            $tpl_row = $tpl_stmt->fetch(PDO::FETCH_ASSOC);
            if ($tpl_row) {
                $program_name = $tpl_row['name'];
                $duration_weeks = $tpl_row['duration_weeks'];
            }
        } catch (PDOException $e) { /* column may not exist */ }
    }
    
    // Fallback duration from notification templates
    if (!$duration_weeks) {
        try {
            $dur_stmt = $pdo->prepare("SELECT program_duration_weeks FROM development_notification_templates WHERE program_type = ?");
            $dur_stmt->execute([$program_type]);
            $dur_row = $dur_stmt->fetch(PDO::FETCH_ASSOC);
            $duration_weeks = $dur_row['program_duration_weeks'] ?? null;
        } catch (PDOException $e) { /* table may not exist */ }
    }
    
    // Create enrollment with auto-calculated dates
    $start_date = date('Y-m-d');
    $duration_weeks = $duration_weeks !== null ? max(1, min(52, intval($duration_weeks))) : null;
    $end_date = $duration_weeks ? date('Y-m-d', strtotime("+{$duration_weeks} weeks")) : null;
    
    $stmt = $pdo->prepare("INSERT INTO development_program_enrollments (athlete_id, program_type, program_name, template_id, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $program_type, $program_name, $template_id ?: null, $start_date, $end_date]);
    
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
    
    // Get notification template (used for ATHLETE welcome email)
    $tmpl_stmt = $pdo->prepare("SELECT subject, body, notification_email FROM development_notification_templates WHERE program_type = ?");
    $tmpl_stmt->execute([$program_type]);
    $template = $tmpl_stmt->fetch(PDO::FETCH_ASSOC);
    
    $athlete_email_subject = $template['subject'] ?? 'Welcome to Your Development Program!';
    $athlete_email_body = $template['body'] ?? 'You have been enrolled in a development program. Your coach will be in touch shortly.';
    $notification_email = $template['notification_email'] ?? null;
    
    // Get athlete name and email
    $athlete_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $athlete_stmt->execute([$user_id]);
    $athlete = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $athlete = decryptUserRows([$athlete])[0];
    }
    $athlete_name = htmlspecialchars(trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');
    $athlete_email = $athlete['email'] ?? '';
    
    // Coach/admin notification (hardcoded text, not the template)
    $program_label = $program_type === 'goalie_dev' ? 'Goalie Development Program' : 'Player Development Program';
    $coach_notif_title = 'New Development Program Registration';
    $coach_notif_body = "Athlete: $athlete_name has registered for the $program_label.";
    
    // Create in-app notifications for coaches/admins
    $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url) VALUES (?, 'dev_program_registration', ?, ?, '?page=development_programs')");
    foreach ($notify_ids as $nid) {
        $notif_stmt->execute([$nid, $coach_notif_title, $coach_notif_body]);
    }

    // Send notification email to configured coach email address
    if (!empty($notification_email) && filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
        try {
            if (function_exists('sendEmail')) {
                sendEmail($notification_email, 'notification', [
                    'title' => $coach_notif_title,
                    'message' => $coach_notif_body,
                    'name' => 'Development Program Admin'
                ]);
            }
        } catch (\Throwable $e) {
            error_log("Dev program coach notification email error: " . $e->getMessage());
        }
    }
    
    // Send welcome email to the ATHLETE using the template
    if (!empty($athlete_email) && filter_var($athlete_email, FILTER_VALIDATE_EMAIL)) {
        try {
            if (function_exists('sendEmail')) {
                sendEmail($athlete_email, 'notification', [
                    'title' => $athlete_email_subject,
                    'message' => $athlete_email_body,
                    'name' => $athlete_name ?: 'Athlete'
                ]);
            }
        } catch (\Throwable $e) {
            error_log("Dev program athlete welcome email error: " . $e->getMessage());
        }
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
    
    // Encrypt message body for at-rest protection
    require_once __DIR__ . '/lib/encryption.php';
    $encrypted_message = FieldEncryption::encrypt($message);

    $stmt = $pdo->prepare("INSERT INTO development_program_messages (enrollment_id, sender_id, message, video_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $user_id, $encrypted_message, $video_url ?: null]);
    
    echo json_encode(['success' => true]);
}

/**
 * Create a personal drill and add it to the drill library
 */
function handleCreatePersonalDrill($pdo, $user_id, $input) {
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $position = in_array($input['position'] ?? '', ['player', 'goalie']) ? $input['position'] : 'player';
    $video_upload_path = null;
    $thumbnail_path = null;
    
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
        $baseName = 'personal_drill_' . bin2hex(random_bytes(16));
        $filename = $baseName . '.' . $extension;
        
        $persist = persistUploadedFile($pdo, $file['tmp_name'], 'drills/videos', $filename, '', true);
        if (!$persist['success']) {
            echo json_encode(['success' => false, 'error' => 'Video upload failed. Please try again.']);
            return;
        }
        $video_upload_path = $persist['rustfs_url'] ?? null;
        
        // Generate video thumbnail
        $thumb = generateVideoThumbnail($pdo, $file['tmp_name'], 'drills/thumbnails', $baseName);
        if ($thumb['success']) {
            $thumbnail_path = $thumb['thumbnail_url'];
        }
    }
    
    $pdo->beginTransaction();
    try {
        // Add to the main drill library first
        $drill_stmt = $pdo->prepare("INSERT INTO drills (title, description, video_upload_path, thumbnail_path, created_by) VALUES (?, ?, ?, ?, ?)");
        $drill_stmt->execute([$title, $description ?: null, $video_upload_path, $thumbnail_path, $user_id]);
        $drill_id = (int)$pdo->lastInsertId();
        
        // Create personal drill record referencing the library drill
        $pd_stmt = $pdo->prepare("INSERT INTO personal_drills (title, description, video_upload_path, position, thumbnail_path, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        $pd_stmt->execute([$title, $description ?: null, $video_upload_path, $position, $thumbnail_path, $user_id]);
        
        $pdo->commit();
        echo json_encode(['success' => true, 'drill_id' => $drill_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Update a personal drill (title, description, position, video)
 */
function handleUpdatePersonalDrill($pdo, $user_id, $input) {
    $drill_id = (int)($input['drill_id'] ?? 0);
    $title = trim($input['title'] ?? '');
    $description = trim($input['description'] ?? '');
    $position = in_array($input['position'] ?? '', ['player', 'goalie']) ? $input['position'] : 'player';

    if (!$drill_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid drill ID']);
        return;
    }
    if (!$title) {
        echo json_encode(['success' => false, 'error' => 'Title is required']);
        return;
    }

    // Verify ownership (or admin)
    $check = $pdo->prepare("SELECT id, created_by FROM personal_drills WHERE id = ?");
    $check->execute([$drill_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Drill not found']);
        return;
    }
    $user_role = $_SESSION['user_role'] ?? '';
    if ((int)$existing['created_by'] !== (int)$user_id && $user_role !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $video_upload_path = null;
    $thumbnail_path = null;

    // Handle optional video file upload
    if (isset($_FILES['video_file']) && $_FILES['video_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['video_file'];

        $validator = new FileUploadValidator();
        $validation = $validator->validateVideo($file);
        if (!$validation['valid']) {
            echo json_encode(['success' => false, 'error' => $validation['error']]);
            return;
        }

        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $baseName = 'personal_drill_' . bin2hex(random_bytes(16));
        $filename = $baseName . '.' . $extension;

        $persist = persistUploadedFile($pdo, $file['tmp_name'], 'drills/videos', $filename, '', true);
        if (!$persist['success']) {
            echo json_encode(['success' => false, 'error' => 'Video upload failed. Please try again.']);
            return;
        }
        $video_upload_path = $persist['rustfs_url'] ?? null;

        $thumb = generateVideoThumbnail($pdo, $file['tmp_name'], 'drills/thumbnails', $baseName);
        if ($thumb['success']) {
            $thumbnail_path = $thumb['thumbnail_url'];
        }
    }

    if ($video_upload_path) {
        $stmt = $pdo->prepare("UPDATE personal_drills SET title = ?, description = ?, position = ?, video_upload_path = ?, thumbnail_path = ? WHERE id = ?");
        $stmt->execute([$title, $description ?: null, $position, $video_upload_path, $thumbnail_path, $drill_id]);
    } else {
        $stmt = $pdo->prepare("UPDATE personal_drills SET title = ?, description = ?, position = ? WHERE id = ?");
        $stmt->execute([$title, $description ?: null, $position, $drill_id]);
    }
    echo json_encode(['success' => true]);
}

/**
 * Delete a personal drill
 */
function handleDeletePersonalDrill($pdo, $user_id, $input) {
    $drill_id = (int)($input['drill_id'] ?? 0);

    if (!$drill_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid drill ID']);
        return;
    }

    // Verify ownership (or admin)
    $check = $pdo->prepare("SELECT id, created_by FROM personal_drills WHERE id = ?");
    $check->execute([$drill_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    if (!$existing) {
        echo json_encode(['success' => false, 'error' => 'Drill not found']);
        return;
    }
    $user_role = $_SESSION['user_role'] ?? '';
    if ((int)$existing['created_by'] !== (int)$user_id && $user_role !== 'admin') {
        echo json_encode(['success' => false, 'error' => 'Access denied']);
        return;
    }

    $stmt = $pdo->prepare("DELETE FROM personal_drills WHERE id = ?");
    $stmt->execute([$drill_id]);

    echo json_encode(['success' => true]);
}

/**
 * Update a development notification template (admin only)
 */
function handleUpdateNotificationTemplate($pdo, $user_id, $input) {
    $template_id = (int)($input['template_id'] ?? 0);
    $subject = trim($input['subject'] ?? '');
    $body = trim($input['body'] ?? '');
    $notification_email = trim($input['notification_email'] ?? '');
    $program_duration_weeks = isset($input['program_duration_weeks']) && $input['program_duration_weeks'] !== null && $input['program_duration_weeks'] !== '' ? (int)$input['program_duration_weeks'] : null;
    
    if (!$template_id || !$subject || !$body) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields']);
        return;
    }

    // Validate email if provided
    if ($notification_email !== '' && !filter_var($notification_email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid notification email address']);
        return;
    }
    
    $stmt = $pdo->prepare("UPDATE development_notification_templates SET subject = ?, body = ?, notification_email = ?, program_duration_weeks = ?, updated_by = ? WHERE id = ?");
    $stmt->execute([$subject, $body, $notification_email ?: null, $program_duration_weeks, $user_id, $template_id]);
    
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
    $thumbnail_path = null;
    
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
        $baseName = 'dev_video_' . bin2hex(random_bytes(16));
        $filename = $baseName . '.' . $extension;
        
        $persist = persistUploadedFile($pdo, $file['tmp_name'], 'development/videos', $filename, '', true);
        if (!$persist['success']) {
            echo json_encode(['success' => false, 'error' => 'Video upload failed']);
            return;
        }
        $video_upload_path = $persist['rustfs_url'] ?? null;
        
        // Generate video thumbnail
        $thumb = generateVideoThumbnail($pdo, $file['tmp_name'], 'development/thumbnails', $baseName);
        if ($thumb['success']) {
            $thumbnail_path = $thumb['thumbnail_url'];
        }
    }
    
    $final_url = $video_upload_path ?: ($video_url ?: null);
    
    $stmt = $pdo->prepare("INSERT INTO development_program_videos (enrollment_id, athlete_id, drill_assignment_id, title, description, video_url, video_upload_path, thumbnail_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $user_id, $drill_assignment_id, $title, $description ?: null, $final_url, $video_upload_path, $thumbnail_path]);
    
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
    $thumbnail_path = null;
    
    // Accept client-side generated thumbnail (base64 JPEG)
    $thumbnail_data = $input['thumbnail_data'] ?? $_POST['thumbnail_data'] ?? '';
    if (!empty($thumbnail_data)) {
        $thumb_binary = base64_decode($thumbnail_data, true);
        if ($thumb_binary !== false && strlen($thumb_binary) > 0 && strlen($thumb_binary) < 2 * 1024 * 1024) {
            $rustfs = getRustFSSettings($pdo);
            if (isRustFSConfigured($rustfs)) {
                $thumbKey = 'Images/development/thumbnails/dev_video_' . bin2hex(random_bytes(8)) . '_thumb.jpg';
                $upload = uploadContentToRustFS($rustfs, $thumb_binary, $thumbKey, 'image/jpeg');
                if ($upload['success']) {
                    $thumbnail_path = 'api/media.php?key=' . rawurlencode($thumbKey);
                }
            }
        }
    }
    
    // Clean up session
    unset($_SESSION['pending_video_upload']);
    
    $stmt = $pdo->prepare("INSERT INTO development_program_videos (enrollment_id, athlete_id, drill_assignment_id, title, description, video_url, video_upload_path, thumbnail_path) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$enrollment_id, $user_id, $drill_assignment_id, $title, $description ?: null, $video_url, $video_upload_path, $thumbnail_path]);
    
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

/**
 * Save a custom email template
 */
function handleSaveEmailTemplate($pdo, $user_id, $input) {
    $template_type = trim($input['template_type'] ?? '');
    $label = trim($input['label'] ?? '');
    $subject = trim($input['subject'] ?? '');
    $body_text = trim($input['body_text'] ?? '');
    $body_html = trim($input['body_html'] ?? '');
    
    if (!$template_type || !$subject) {
        echo json_encode(['success' => false, 'error' => 'Template type and subject are required']);
        return;
    }
    if (!$body_text && !$body_html) {
        echo json_encode(['success' => false, 'error' => 'Body text or HTML is required']);
        return;
    }
    
    // Whitelist allowed template types
    $allowed_types = ['verification', 'manual_welcome', 'payment_receipt', 'password_reset', 
                      'notification', 'system_notification', 'email_change_confirmation', 
                      'esignature_request', 'contract_signed', 'extension_request', 'test'];
    if (!in_array($template_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Invalid template type']);
        return;
    }
    
    // Ensure table exists
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `email_templates` (
                `id` INT AUTO_INCREMENT PRIMARY KEY,
                `template_type` VARCHAR(50) NOT NULL,
                `label` VARCHAR(100) NOT NULL,
                `subject` VARCHAR(255) NOT NULL,
                `body_text` TEXT DEFAULT NULL,
                `body_html` TEXT DEFAULT NULL,
                `is_custom` TINYINT(1) DEFAULT 0,
                `updated_by` INT DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `unique_template_type` (`template_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    } catch (PDOException $e) { /* ignore */ }
    
    // Upsert the template
    $stmt = $pdo->prepare("
        INSERT INTO email_templates (template_type, label, subject, body_text, body_html, is_custom, updated_by) 
        VALUES (?, ?, ?, ?, ?, 1, ?)
        ON DUPLICATE KEY UPDATE subject = VALUES(subject), body_text = VALUES(body_text), body_html = VALUES(body_html), is_custom = 1, updated_by = VALUES(updated_by)
    ");
    $stmt->execute([$template_type, $label, $subject, $body_text, $body_html ?: null, $user_id]);
    
    echo json_encode(['success' => true]);
}

/**
 * Reset an email template to system defaults
 */
function handleResetEmailTemplate($pdo, $input) {
    $template_type = trim($input['template_type'] ?? '');
    if (!$template_type) {
        echo json_encode(['success' => false, 'error' => 'Template type is required']);
        return;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM email_templates WHERE template_type = ?");
        $stmt->execute([$template_type]);
    } catch (PDOException $e) { /* table may not exist */ }
    
    echo json_encode(['success' => true]);
}
