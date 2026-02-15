<?php
/**
 * Process Video Operations
 * Handles video upload, update, and deletion operations
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once 'csrf_protection.php';
require_once 'lib/file_upload_validator.php';
require_once 'notifications.php';
require_once 'mailer.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload_video':
            handleVideoUpload();
            break;
        
        case 'athlete_upload_video':
            handleAthleteVideoUpload();
            break;
        
        case 'upload_drill_video':
            handleDrillVideoUpload();
            break;
            
        case 'update_video':
            handleVideoUpdate();
            break;
            
        case 'delete_video':
            handleVideoDelete();
            break;
            
        case 'review_video':
            handleVideoReview();
            break;

        // ── Game Plan Module Actions ──────────────────────────────
        case 'create_game_plan':
            handleCreateGamePlan();
            break;

        case 'save_hockey_lines':
            handleSaveHockeyLines();
            break;

        case 'upload_video_source':
            handleUploadVideoSource();
            break;

        case 'create_clip':
            handleCreateClip();
            break;

        case 'create_review_session':
            handleCreateReviewSession();
            break;

        case 'update_video_permissions':
            handleUpdateVideoPermissions();
            break;

        case 'import_calendar':
            handleImportCalendar();
            break;

        case 'sync_calendar':
            handleSyncCalendar();
            break;

        case 'add_roster_player':
            handleAddRosterPlayer();
            break;

        case 'update_roster_player':
            handleUpdateRosterPlayer();
            break;

        case 'remove_roster_player':
            handleRemoveRosterPlayer();
            break;

        case 'link_roster_player':
            handleLinkRosterPlayer();
            break;

        case 'add_calendar_event':
            handleAddCalendarEvent();
            break;

        case 'create_device_pair':
            handleCreateDevicePair();
            break;

        case 'join_device_pair':
            handleJoinDevicePair();
            break;

        case 'join_as_controller':
            handleJoinAsController();
            break;

        case 'end_device_pair':
            handleEndDevicePair();
            break;

        case 'toggle_freeze_pair':
            handleToggleFreezePair();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (PDOException $e) {
    logSecurityEvent($pdo, 'video_error', $e->getMessage(), $user_id);
    error_log('process_video PDO error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again.']);
    exit;
} catch (Exception $e) {
    logSecurityEvent($pdo, 'video_error', $e->getMessage(), $user_id);
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}

/**
 * Handle video file upload (by coach)
 */
function handleVideoUpload() {
    global $pdo, $user_id, $user_role;
    
    // Only coaches, health coaches, team coaches, coach_plus and admins can upload review videos
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('You do not have permission to upload review videos');
    }
    
    // Validate required fields
    $athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT);
    $session_date = $_POST['session_date'] ?? null;
    $drill_type = $_POST['drill_type'] ?? null;
    $drill_name = $_POST['drill_name'] ?? null;
    $comments = $_POST['comments'] ?? '';
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    
    if (!$athlete_id || !$session_date || !$drill_type || !$drill_name) {
        throw new Exception('Missing required fields: athlete, session date, drill type, and drill name are required');
    }
    
    // Validate file upload
    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Video file upload failed');
    }
    
    $file = $_FILES['video_file'];
    
    // Use FileUploadValidator for security validation
    $validator = new FileUploadValidator();
    $validation = $validator->validateVideoUpload($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Create videos directory if it doesn't exist
    $upload_dir = __DIR__ . '/videos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_filename = uniqid('video_', true) . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $unique_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save video file');
    }
    
    // Insert video record into database
    $stmt = $pdo->prepare("
        INSERT INTO videos (
            athlete_id, coach_id, title, description, video_url,
            video_type, video_category, status, coach_notes, upload_date
        ) VALUES (
            ?, ?, ?, ?, ?,
            'coach_review', 'drill', 'pending_review', ?, NOW()
        )
    ");
    
    $video_url = 'videos/' . $unique_filename;
    $title = $drill_name . ' - ' . $drill_type;
    $description = 'Session Date: ' . $session_date . ' | Drill Type: ' . $drill_type;
    
    $stmt->execute([
        $athlete_id,
        $user_id,
        $title,
        $description,
        $video_url,
        $comments
    ]);
    
    $video_id = $pdo->lastInsertId();
    
    // Log the action
    logSecurityEvent($pdo, 'video_upload', "Video uploaded for athlete ID: $athlete_id", $user_id);
    
    // Send notification to athlete
    sendVideoNotification($pdo, $athlete_id, $user_id, $video_id, 'new_video');
    
    // Redirect back to coach reviews page
    header('Location: dashboard.php?page=coaches_reviews&success=video_uploaded');
    exit;
}

/**
 * Handle athlete video upload for coach review
 */
function handleAthleteVideoUpload() {
    global $pdo, $user_id, $user_role;
    
    // Get user's assigned coach
    $coach_id = filter_input(INPUT_POST, 'coach_id', FILTER_VALIDATE_INT);
    
    // If user is a coach uploading for an athlete
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    $is_coach = in_array($user_role, $allowed_roles);
    
    // Get athlete_id from POST (auto-assigned to current user on the frontend)
    $athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT);
    
    // Default to current user if no athlete_id provided
    if (!$athlete_id) {
        $athlete_id = $user_id;
    }
    
    // Get the coach for this video
    if ($is_coach) {
        // For coaches uploading for themselves, they are their own reviewer
        $coach_id = $user_id;
    } else {
        // For athletes, validate that they have an assigned coach
        if (!$coach_id) {
            $stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $coach_id = $user['assigned_coach_id'] ?? null;
        }
        
        if (!$coach_id) {
            throw new Exception('You do not have an assigned coach. Please contact an administrator.');
        }
    }
    
    // Validate required fields
    $title = trim($_POST['title'] ?? '');
    $video_category = $_POST['video_category'] ?? 'drill';
    $description = $_POST['description'] ?? '';
    
    if (empty($title)) {
        throw new Exception('Video title is required');
    }
    
    if (!in_array($video_category, ['drill', 'game'])) {
        throw new Exception('Invalid video type');
    }
    
    // Game-specific fields
    $game_date = null;
    $team_played_on = null;
    $opponent_team = null;
    
    if ($video_category === 'game') {
        $game_date = $_POST['game_date'] ?? null;
        $team_played_on = trim($_POST['team_played_on'] ?? '');
        $opponent_team = trim($_POST['opponent_team'] ?? '');
        
        if (empty($game_date) || empty($team_played_on) || empty($opponent_team)) {
            throw new Exception('Game date, team played on, and opponent team are required for game videos');
        }
    }
    
    // Drill-specific fields
    $drill_type = $_POST['drill_type'] ?? null;
    $session_date = $_POST['session_date'] ?? date('Y-m-d');
    
    // Validate file upload
    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'File exceeds server maximum upload size',
            UPLOAD_ERR_FORM_SIZE => 'File exceeds form maximum upload size',
            UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file was uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
        ];
        $error_code = $_FILES['video_file']['error'] ?? UPLOAD_ERR_NO_FILE;
        throw new Exception($error_messages[$error_code] ?? 'Video file upload failed');
    }
    
    $file = $_FILES['video_file'];
    
    // Use FileUploadValidator for security validation
    $validator = new FileUploadValidator();
    $validation = $validator->validateVideoUpload($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Create videos directory if it doesn't exist
    $upload_dir = __DIR__ . '/videos/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_filename = uniqid('athlete_video_', true) . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $unique_filename;
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save video file');
    }
    
    // Insert video record into database
    $stmt = $pdo->prepare("
        INSERT INTO videos (
            athlete_id, coach_id, title, description, video_url,
            video_type, video_category, game_date, team_played_on, opponent_team,
            status, upload_date
        ) VALUES (
            ?, ?, ?, ?, ?,
            'uploaded_by_athlete', ?, ?, ?, ?,
            'pending_review', NOW()
        )
    ");
    
    $video_url = 'videos/' . $unique_filename;
    
    $stmt->execute([
        $athlete_id,
        $coach_id,
        $title,
        $description,
        $video_url,
        $video_category,
        $game_date,
        $team_played_on,
        $opponent_team
    ]);
    
    $video_id = $pdo->lastInsertId();
    
    // Log the action
    logSecurityEvent($pdo, 'athlete_video_upload', "Athlete video uploaded for review, ID: $video_id", $athlete_id);
    
    // Send notification and email to coach
    sendVideoUploadNotificationToCoach($pdo, $coach_id, $athlete_id, $video_id, $title);
    
    // Redirect back to coach reviews page
    header('Location: dashboard.php?page=coaches_reviews&success=video_uploaded');
    exit;
}

/**
 * Handle drill video upload from coach recording interface
 * Uploads to Nextcloud with folder structure: Year/Month/Day
 * Naming: SessionName-DrillName-AthleteName-Rep#
 */
function handleDrillVideoUpload() {
    global $pdo, $user_id, $user_role;
    
    // Only coaches can upload drill videos
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('You do not have permission to upload drill videos');
    }
    
    // Validate required fields
    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
    $drill_id = filter_input(INPUT_POST, 'drill_id', FILTER_VALIDATE_INT);
    $athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT);
    $rep_number = filter_input(INPUT_POST, 'rep_number', FILTER_VALIDATE_INT) ?: 1;
    
    if (!$session_id || !$drill_id || !$athlete_id) {
        throw new Exception('Missing required fields: session, drill, and athlete are required');
    }
    
    // Get session, drill, and athlete names for file naming
    $stmt = $pdo->prepare("SELECT title, session_date, date FROM sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    if (!$session) {
        throw new Exception('Session not found');
    }
    $session_name = $session['title'] ?? 'Session';
    $session_date = $session['session_date'] ?? $session['date'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("SELECT title FROM drills WHERE id = ?");
    $stmt->execute([$drill_id]);
    $drill = $stmt->fetch();
    if (!$drill) {
        throw new Exception('Drill not found');
    }
    $drill_name = $drill['title'];
    
    $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt->execute([$athlete_id]);
    $athlete = $stmt->fetch();
    $athlete = decryptUserRow($athlete);
    if (!$athlete) {
        throw new Exception('Athlete not found');
    }
    $athlete_name = $athlete['first_name'] . ' ' . $athlete['last_name'];
    
    // Validate file upload
    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Video file upload failed');
    }
    
    $file = $_FILES['video_file'];
    
    // Use FileUploadValidator for security validation
    $validator = new FileUploadValidator();
    $validation = $validator->validateVideoUpload($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Create local videos directory if it doesn't exist
    $upload_dir = __DIR__ . '/videos/drills/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate filename based on naming convention
    $safe_session = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $session_name));
    $safe_drill = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $drill_name));
    $safe_athlete = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $athlete_name));
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $filename = sprintf('%s-%s-%s-Rep%d.%s', $safe_session, $safe_drill, $safe_athlete, $rep_number, $file_extension);
    $upload_path = $upload_dir . $filename;
    
    // Move uploaded file locally first
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save video file');
    }
    
    $local_path = 'videos/drills/' . $filename;
    $nextcloud_path = null;
    $is_uploaded_to_cloud = 0;
    
    // Try to upload to Nextcloud
    try {
        require_once __DIR__ . '/cloud_config.php';
        $nc_settings = getNextcloudSettings($pdo);
        
        if (!empty($nc_settings['nextcloud_url']) && !empty($nc_settings['nextcloud_username'])) {
            $upload_result = uploadDrillVideo(
                $pdo,
                $nc_settings,
                $session_name,
                $drill_name,
                $athlete_name,
                $rep_number,
                ['name' => $filename, 'tmp_name' => $upload_path],
                $session_date
            );
            
            if ($upload_result['success']) {
                $nextcloud_path = $upload_result['remote_path'];
                $is_uploaded_to_cloud = 1;
            }
        }
    } catch (Exception $e) {
        // Log error but continue - file is saved locally
        error_log('Nextcloud upload failed: ' . $e->getMessage());
    }
    
    // Insert video record into database
    $title = sprintf('%s - %s - %s (Rep %d)', $session_name, $drill_name, $athlete_name, $rep_number);
    
    $stmt = $pdo->prepare("
        INSERT INTO videos (
            athlete_id, coach_id, title, description, video_url,
            video_type, video_category, drill_id, session_id, rep_number,
            nextcloud_path, local_path, is_uploaded_to_cloud,
            status, upload_date
        ) VALUES (
            ?, ?, ?, ?, ?,
            'drill_review', 'drill', ?, ?, ?,
            ?, ?, ?,
            'reviewed', NOW()
        )
    ");
    
    $description = sprintf('Drill video recorded during session on %s', date('M d, Y', strtotime($session_date)));
    
    $stmt->execute([
        $athlete_id,
        $user_id,
        $title,
        $description,
        $local_path,
        $drill_id,
        $session_id,
        $rep_number,
        $nextcloud_path,
        $local_path,
        $is_uploaded_to_cloud
    ]);
    
    $video_id = $pdo->lastInsertId();
    
    // Log the action
    logSecurityEvent($pdo, 'drill_video_upload', "Drill video uploaded: $title (ID: $video_id)", $user_id);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Video uploaded successfully',
        'video_id' => $video_id,
        'uploaded_to_cloud' => $is_uploaded_to_cloud,
        'nextcloud_path' => $nextcloud_path
    ]);
}

/**
 * Handle video update (notes, rating)
 */
function handleVideoUpdate() {
    global $pdo, $user_id, $user_role;
    
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    $comments = $_POST['comments'] ?? '';
    
    if (!$video_id) {
        throw new Exception('Invalid video ID');
    }
    
    // Verify user owns this video (coach) or is the athlete
    $stmt = $pdo->prepare("
        SELECT * FROM videos WHERE id = ? AND (coach_id = ? OR athlete_id = ?)
    ");
    $stmt->execute([$video_id, $user_id, $user_id]);
    $video = $stmt->fetch();
    
    if (!$video) {
        throw new Exception('Video not found or access denied');
    }
    
    // Update video
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (in_array($user_role, $allowed_roles)) {
        $stmt = $pdo->prepare("
            UPDATE videos 
            SET coach_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$comments, $video_id]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE videos 
            SET athlete_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$comments, $video_id]);
    }
    
    logSecurityEvent($pdo, 'video_update', "Video ID: $video_id updated", $user_id);
    
    echo json_encode(['success' => true, 'message' => 'Video updated successfully']);
}

/**
 * Handle video deletion
 */
function handleVideoDelete() {
    global $pdo, $user_id, $user_role;
    
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    
    if (!$video_id) {
        throw new Exception('Invalid video ID');
    }
    
    // Get video details - allow deletion by coach or athlete who uploaded
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND (coach_id = ? OR athlete_id = ?)");
    $stmt->execute([$video_id, $user_id, $user_id]);
    $video = $stmt->fetch();
    
    if (!$video) {
        throw new Exception('Video not found or access denied');
    }
    
    // Delete physical file
    $file_path = __DIR__ . '/' . $video['video_url'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    
    // Delete database record
    $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    
    logSecurityEvent($pdo, 'video_delete', "Video ID: $video_id deleted", $user_id);
    
    echo json_encode(['success' => true, 'message' => 'Video deleted successfully']);
}

/**
 * Handle video review submission
 */
function handleVideoReview() {
    global $pdo, $user_id, $user_role;
    
    // Only coaches, health coaches, team coaches, coach_plus and admins can review videos
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('You do not have permission to review videos');
    }
    
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    $coach_notes = $_POST['coach_notes'] ?? $_POST['review_comments'] ?? '';
    
    if (!$video_id) {
        throw new Exception('Invalid video ID');
    }
    
    if (empty(trim($coach_notes))) {
        throw new Exception('Review notes are required');
    }
    
    // Update video status - allow review if coach is assigned to athlete or is the video's coach
    $stmt = $pdo->prepare("
        UPDATE videos v
        LEFT JOIN users u ON v.athlete_id = u.id
        SET v.status = 'reviewed',
            v.coach_id = ?,
            v.coach_notes = ?,
            v.reviewed_at = NOW()
        WHERE v.id = ? AND (v.coach_id = ? OR v.coach_id IS NULL OR u.assigned_coach_id = ?)
    ");
    $stmt->execute([$user_id, $coach_notes, $video_id, $user_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Video not found or access denied');
    }
    
    // Get athlete ID and video details for notification
    $stmt = $pdo->prepare("SELECT athlete_id, title FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if ($video) {
        sendVideoReviewNotificationToAthlete($pdo, $video['athlete_id'], $user_id, $video_id, $video['title']);
    }
    
    logSecurityEvent($pdo, 'video_review', "Video ID: $video_id reviewed", $user_id);
    
    // Check if this is an AJAX request or form submission
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        echo json_encode(['success' => true, 'message' => 'Video reviewed successfully']);
    } else {
        header('Location: dashboard.php?page=coaches_reviews&success=video_reviewed');
        exit;
    }
}

/**
 * Send notification for video events (legacy)
 */
function sendVideoNotification($pdo, $athlete_id, $coach_id, $video_id, $type) {
    try {
        $message = '';
        $title = '';
        
        switch ($type) {
            case 'new_video':
                $title = 'New Review Video';
                $message = 'Your coach has uploaded a new review video for you';
                break;
            case 'video_reviewed':
                $title = 'Video Reviewed';
                $message = 'Your coach has reviewed your video';
                break;
        }
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, notification_type, title, message, link_url, created_at)
            VALUES (?, 'video', ?, ?, ?, NOW())
        ");
        $link = 'dashboard.php?page=coaches_reviews';
        $stmt->execute([$athlete_id, $title, $message, $link]);
    } catch (Exception $e) {
        error_log('Failed to send video notification: ' . $e->getMessage());
    }
}

/**
 * Send notification and email to coach when athlete uploads a video
 */
function sendVideoUploadNotificationToCoach($pdo, $coach_id, $athlete_id, $video_id, $video_title) {
    try {
        // Get athlete name
        $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();
        $athlete = decryptUserRow($athlete);
        $athlete_name = trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? '')) ?: 'An athlete';
        
        // Get coach email
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$coach_id]);
        $coach = $stmt->fetch();
        $coach = decryptUserRow($coach);
        
        $title = 'New Video for Review';
        $message = $athlete_name . ' has uploaded a video for your review: "' . $video_title . '"';
        $link = 'dashboard.php?page=coaches_reviews';
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, notification_type, title, message, link_url, created_at)
            VALUES (?, 'video_review_request', ?, ?, ?, NOW())
        ");
        $stmt->execute([$coach_id, $title, $message, $link]);
        
        // Send email notification
        if ($coach && !empty($coach['email'])) {
            try {
                // Get SMTP settings
                $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
                $smtp_settings = [];
                while ($row = $settings_stmt->fetch()) {
                    $smtp_settings[$row['setting_key']] = $row['setting_value'];
                }
                
                if (!empty($smtp_settings['smtp_host'])) {
                    // Get base URL from settings or use default
                    $base_url_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'site_base_url'");
                    $base_url = $base_url_stmt->fetchColumn() ?: 'https://arcticwolves.com';
                    $base_url = rtrim($base_url, '/');
                    
                    $mailer = new SmtpMailer();
                    $email_body = "
                        <h2>New Video for Review</h2>
                        <p>Hi {$coach['first_name']},</p>
                        <p>{$message}</p>
                        <p>Please log in to review the video and provide feedback.</p>
                        <p><a href='{$base_url}/{$link}'>Review Video</a></p>
                        <p>Thank you,<br>Arctic Wolves System</p>
                    ";
                    $mailer->send($coach['email'], 'New Video for Review - Arctic Wolves', $email_body, $smtp_settings);
                }
            } catch (Exception $email_error) {
                error_log('Failed to send email to coach: ' . $email_error->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log('Failed to send video upload notification to coach: ' . $e->getMessage());
    }
}

/**
 * Send notification and email to athlete when coach reviews their video
 */
function sendVideoReviewNotificationToAthlete($pdo, $athlete_id, $coach_id, $video_id, $video_title) {
    try {
        // Get coach name
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$coach_id]);
        $coach = $stmt->fetch();
        $coach = decryptUserRow($coach);
        $coach_name = trim(($coach['first_name'] ?? '') . ' ' . ($coach['last_name'] ?? '')) ?: 'Your coach';
        
        // Get athlete email
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();
        $athlete = decryptUserRow($athlete);
        
        $title = 'Video Reviewed';
        $message = $coach_name . ' has reviewed your video: "' . $video_title . '"';
        $link = 'dashboard.php?page=coaches_reviews';
        
        // Insert notification
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, notification_type, title, message, link_url, created_at)
            VALUES (?, 'video_reviewed', ?, ?, ?, NOW())
        ");
        $stmt->execute([$athlete_id, $title, $message, $link]);
        
        // Send email notification
        if ($athlete && !empty($athlete['email'])) {
            try {
                // Get SMTP settings
                $settings_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
                $smtp_settings = [];
                while ($row = $settings_stmt->fetch()) {
                    $smtp_settings[$row['setting_key']] = $row['setting_value'];
                }
                
                if (!empty($smtp_settings['smtp_host'])) {
                    // Get base URL from settings or use default
                    $base_url_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'site_base_url'");
                    $base_url = $base_url_stmt->fetchColumn() ?: 'https://arcticwolves.com';
                    $base_url = rtrim($base_url, '/');
                    
                    $mailer = new SmtpMailer();
                    $email_body = "
                        <h2>Your Video Has Been Reviewed</h2>
                        <p>Hi {$athlete['first_name']},</p>
                        <p>{$message}</p>
                        <p>Log in to see the feedback from your coach.</p>
                        <p><a href='{$base_url}/{$link}'>View Feedback</a></p>
                        <p>Keep up the great work!<br>Arctic Wolves System</p>
                    ";
                    $mailer->send($athlete['email'], 'Video Reviewed - Arctic Wolves', $email_body, $smtp_settings);
                }
            } catch (Exception $email_error) {
                error_log('Failed to send email to athlete: ' . $email_error->getMessage());
            }
        }
    } catch (Exception $e) {
        error_log('Failed to send video review notification to athlete: ' . $e->getMessage());
    }
}

// =========================================================
// GAME PLAN MODULE HANDLERS
// =========================================================

/**
 * Create a game plan (pre-game, post-game, or practice)
 */
function handleCreateGamePlan() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to create game plans');
    }

    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        throw new Exception('Plan title is required');
    }

    $game_id = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT) ?: null;
    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT) ?: null;
    $plan_type = $_POST['plan_type'] ?? 'pre_game';
    $status = $_POST['status'] ?? 'draft';
    $description = trim($_POST['description'] ?? '');
    $offensive_system = trim($_POST['offensive_system'] ?? '');
    $defensive_system = trim($_POST['defensive_system'] ?? '');
    $powerplay_system = trim($_POST['powerplay_system'] ?? '');
    $penalty_kill_system = trim($_POST['penalty_kill_system'] ?? '');
    $key_players_notes = trim($_POST['key_players_notes'] ?? '');
    $strategy_notes = trim($_POST['strategy_notes'] ?? '');

    $valid_types = ['pre_game', 'post_game', 'practice'];
    if (!in_array($plan_type, $valid_types)) $plan_type = 'pre_game';

    $valid_statuses = ['draft', 'active', 'completed', 'archived'];
    if (!in_array($status, $valid_statuses)) $status = 'draft';

    // Validate team_id FK if provided (optional field)
    if ($team_id !== null) {
        $stmt = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        if (!$stmt->fetch()) {
            $team_id = null; // Clear invalid FK reference
        }
    }

    // Validate game_id FK if provided (optional field)
    if ($game_id !== null) {
        $stmt = $pdo->prepare("SELECT id FROM game_schedules WHERE id = ?");
        $stmt->execute([$game_id]);
        if (!$stmt->fetch()) {
            $game_id = null; // Clear invalid FK reference
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO vr_game_plans (coach_id, game_id, team_id, title, description, plan_type, status,
                                       offensive_system, defensive_system, powerplay_system, penalty_kill_system,
                                       key_players_notes, strategy_notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $game_id, $team_id, $title, $description, $plan_type, $status,
            $offensive_system ?: null, $defensive_system ?: null, $powerplay_system ?: null,
            $penalty_kill_system ?: null, $key_players_notes ?: null, $strategy_notes ?: null
        ]);
    } catch (PDOException $e) {
        error_log('Create game plan failed: ' . $e->getMessage());
        throw new Exception('Failed to create game plan. Please try again.');
    }

    logSecurityEvent($pdo, 'game_plan_created', "Game plan created: $title", $user_id);
    header('Location: /gameplan.php?page=game_plan&tab=' . urlencode($plan_type) . '&success=plan_created');
    exit;
}

/**
 * Save hockey lines (depth chart)
 */
function handleSaveHockeyLines() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to manage lines');
    }

    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    $game_id = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT) ?: null;
    $tab = $_POST['tab'] ?? 'forwards';
    $valid_tabs = ['forwards', 'defense', 'special', 'goalies'];
    if (!in_array($tab, $valid_tabs)) $tab = 'forwards';
    if (!$team_id) throw new Exception('Team is required');

    // Validate team exists
    $stmt = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid team selected');
    }

    // Validate game exists if game_id provided
    if ($game_id) {
        $stmt = $pdo->prepare("SELECT id FROM game_schedules WHERE id = ?");
        $stmt->execute([$game_id]);
        if (!$stmt->fetch()) {
            throw new Exception('Invalid game selected');
        }
    }

    $lines = $_POST['lines'] ?? [];
    if (!is_array($lines)) throw new Exception('Invalid lines data');

    // Collect all athlete IDs and roster player IDs separately
    $all_athlete_ids = [];
    $all_roster_player_ids = [];
    // Build a parsed map: [line_name][pos] => ['type' => 'user'|'roster_player', 'id' => int]
    $parsed_lines = [];
    foreach ($lines as $line_name => $positions) {
        if (!is_array($positions)) continue;
        foreach ($positions as $pos => $raw_value) {
            $raw_value = trim((string)$raw_value);
            if ($raw_value === '') continue;
            if (strpos($raw_value, 'rp_') === 0) {
                $rp_id = (int)substr($raw_value, 3);
                if ($rp_id > 0) {
                    $all_roster_player_ids[] = $rp_id;
                    $parsed_lines[$line_name][$pos] = ['type' => 'roster_player', 'id' => $rp_id];
                }
            } else {
                $athlete_id = (int)$raw_value;
                if ($athlete_id > 0) {
                    $all_athlete_ids[] = $athlete_id;
                    $parsed_lines[$line_name][$pos] = ['type' => 'user', 'id' => $athlete_id];
                }
            }
        }
    }

    // Validate user athlete IDs
    $valid_athlete_ids = [];
    if (!empty($all_athlete_ids)) {
        $unique_ids = array_unique($all_athlete_ids);
        $placeholders = implode(',', array_fill(0, count($unique_ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
        $stmt->execute(array_values($unique_ids));
        $valid_athlete_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Validate roster player IDs
    $valid_roster_player_ids = [];
    if (!empty($all_roster_player_ids)) {
        $unique_rp_ids = array_unique($all_roster_player_ids);
        $placeholders = implode(',', array_fill(0, count($unique_rp_ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM roster_players WHERE id IN ($placeholders)");
        $stmt->execute(array_values($unique_rp_ids));
        $valid_roster_player_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $pdo->beginTransaction();
    try {
        // Delete existing lines for this team+game and tab's line names
        $line_names = array_keys($lines);
        if (!empty($line_names)) {
            $placeholders = implode(',', array_fill(0, count($line_names), '?'));
            if ($game_id) {
                $stmt = $pdo->prepare("DELETE FROM vr_game_plan_lines WHERE team_id = ? AND game_id = ? AND line_name IN ($placeholders)");
                $stmt->execute(array_merge([$team_id, $game_id], $line_names));
            } else {
                $stmt = $pdo->prepare("DELETE FROM vr_game_plan_lines WHERE team_id = ? AND game_id IS NULL AND line_name IN ($placeholders)");
                $stmt->execute(array_merge([$team_id], $line_names));
            }
        }

        // Insert new lines (only for validated IDs)
        $stmt = $pdo->prepare("INSERT INTO vr_game_plan_lines (team_id, game_id, line_name, position, athlete_id, roster_player_id) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($parsed_lines as $line_name => $positions) {
            foreach ($positions as $pos => $entry) {
                if ($entry['type'] === 'roster_player' && in_array($entry['id'], $valid_roster_player_ids)) {
                    $stmt->execute([$team_id, $game_id, $line_name, $pos, null, $entry['id']]);
                } elseif ($entry['type'] === 'user' && in_array($entry['id'], $valid_athlete_ids)) {
                    $stmt->execute([$team_id, $game_id, $line_name, $pos, $entry['id'], null]);
                }
            }
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Save hockey lines failed: ' . $e->getMessage());
        throw new Exception('Failed to save lines. Please try again.');
    }

    $game_param = $game_id ? '&game_id=' . $game_id : '';
    logSecurityEvent($pdo, 'hockey_lines_saved', "Hockey lines saved for team $team_id" . ($game_id ? " game $game_id" : " (default)"), $user_id);
    header('Location: /gameplan.php?page=lines&team_id=' . $team_id . '&tab=' . urlencode($tab) . $game_param . '&success=lines_saved');
    exit;
}

/**
 * Upload a video source to the Film Room
 */
function handleUploadVideoSource() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to upload video sources');
    }

    $camera_angle = $_POST['camera_angle'] ?? '';
    $game_id = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT) ?: null;
    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT) ?: null;

    if (empty($camera_angle)) throw new Exception('Camera angle is required');

    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Video file upload failed');
    }

    $file = $_FILES['video_file'];

    $validator = new FileUploadValidator();
    $validation = $validator->validateVideoUpload($file);
    if (!$validation['valid']) throw new Exception($validation['error']);

    // Upload to separate gameplan video location
    $upload_dir = __DIR__ . '/videos/gameplan/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_name = 'gp_source_' . uniqid('', true) . '_' . time() . '.' . $ext;
    $upload_path = $upload_dir . $unique_name;

    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        throw new Exception('Failed to save video file');
    }

    $stmt = $pdo->prepare("
        INSERT INTO vr_video_sources (filename, file_path, camera_angle, file_size, game_id, team_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $file['name'],
        'videos/gameplan/' . $unique_name,
        $camera_angle,
        $file['size'],
        $game_id,
        $team_id,
        $user_id
    ]);

    logSecurityEvent($pdo, 'video_source_uploaded', "Video source uploaded: " . $file['name'], $user_id);
    header('Location: /gameplan.php?page=film_room&tab=upload&success=source_uploaded');
    exit;
}

/**
 * Create a video clip from a source
 */
function handleCreateClip() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to create clips');
    }

    $source_id = filter_input(INPUT_POST, 'source_id', FILTER_VALIDATE_INT);
    $title = trim($_POST['title'] ?? '');
    $start_time = (float)($_POST['start_time'] ?? 0);
    $end_time = (float)($_POST['end_time'] ?? 0);
    $description = trim($_POST['description'] ?? '');

    if (!$source_id) throw new Exception('Source video is required');
    if (empty($title)) throw new Exception('Clip title is required');
    if ($end_time <= $start_time) throw new Exception('End time must be after start time');

    // Get source's game_id
    $stmt = $pdo->prepare("SELECT game_id FROM vr_video_sources WHERE id = ?");
    $stmt->execute([$source_id]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    $game_id = $source ? $source['game_id'] : null;

    $stmt = $pdo->prepare("
        INSERT INTO vr_video_clips (source_id, game_id, title, description, start_time, end_time, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$source_id, $game_id, $title, $description, $start_time, $end_time, $user_id]);
    $clip_id = $pdo->lastInsertId();

    // Add tags
    $tag_ids = $_POST['tag_ids'] ?? [];
    if (is_array($tag_ids)) {
        $tag_stmt = $pdo->prepare("INSERT IGNORE INTO vr_clip_tags (clip_id, tag_id) VALUES (?, ?)");
        foreach ($tag_ids as $tag_id) {
            $tag_id = (int)$tag_id;
            if ($tag_id > 0) $tag_stmt->execute([$clip_id, $tag_id]);
        }
    }

    // Add athletes (validate they exist first)
    $athlete_ids = $_POST['athlete_ids'] ?? [];
    if (is_array($athlete_ids)) {
        $valid_ids = [];
        $int_ids = [];
        foreach ($athlete_ids as $aid) {
            $aid = (int)$aid;
            if ($aid > 0) $int_ids[] = $aid;
        }
        if (!empty($int_ids)) {
            $placeholders = implode(',', array_fill(0, count($int_ids), '?'));
            $check_stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
            $check_stmt->execute($int_ids);
            $valid_ids = $check_stmt->fetchAll(PDO::FETCH_COLUMN);
        }
        $ath_stmt = $pdo->prepare("INSERT IGNORE INTO vr_clip_athletes (clip_id, athlete_id) VALUES (?, ?)");
        foreach ($valid_ids as $ath_id) {
            $ath_stmt->execute([$clip_id, $ath_id]);
        }
    }

    logSecurityEvent($pdo, 'clip_created', "Clip created: $title from source $source_id", $user_id);
    header('Location: /gameplan.php?page=film_room&tab=editor&source_id=' . $source_id . '&success=clip_created');
    exit;
}

/**
 * Create a review session
 */
function handleCreateReviewSession() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to create review sessions');
    }

    $title = trim($_POST['title'] ?? '');
    if (empty($title)) throw new Exception('Session title is required');

    $scheduled_date = $_POST['scheduled_date'] ?? '';
    if (empty($scheduled_date)) throw new Exception('Scheduled date is required');

    $description = trim($_POST['description'] ?? '');
    $session_type = $_POST['session_type'] ?? 'pre_game';
    $game_id = filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT) ?: null;

    $stmt = $pdo->prepare("
        INSERT INTO vr_review_sessions (coach_id, game_id, title, description, session_type, scheduled_date)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$user_id, $game_id, $title, $description, $session_type, $scheduled_date]);
    $session_id = $pdo->lastInsertId();

    // Add clips to session
    $clip_ids = $_POST['clip_ids'] ?? [];
    if (is_array($clip_ids)) {
        $clip_stmt = $pdo->prepare("INSERT IGNORE INTO vr_review_session_clips (session_id, clip_id, sort_order) VALUES (?, ?, ?)");
        $order = 0;
        foreach ($clip_ids as $clip_id) {
            $clip_id = (int)$clip_id;
            if ($clip_id > 0) {
                $clip_stmt->execute([$session_id, $clip_id, $order++]);
            }
        }
    }

    logSecurityEvent($pdo, 'review_session_created', "Review session created: $title", $user_id);
    header('Location: /gameplan.php?page=review_sessions&success=session_created');
    exit;
}

/**
 * Update video permissions
 */
function handleUpdateVideoPermissions() {
    global $pdo, $user_id, $user_role;

    if ($user_role !== 'admin') {
        throw new Exception('Admin access required to manage permissions');
    }

    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    if (!$team_id) throw new Exception('Team is required');

    // Validate team exists
    $stmt = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    if (!$stmt->fetch()) {
        throw new Exception('Invalid team selected');
    }

    $perms = $_POST['perms'] ?? [];
    if (!is_array($perms)) throw new Exception('Invalid permissions data');

    // Collect and validate user IDs
    $user_ids = [];
    foreach ($perms as $uid => $user_perms) {
        $uid = (int)$uid;
        if ($uid > 0) $user_ids[] = $uid;
    }

    $valid_user_ids = [];
    if (!empty($user_ids)) {
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders)");
        $stmt->execute($user_ids);
        $valid_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    $pdo->beginTransaction();
    try {
        // Delete existing permissions for this team
        $stmt = $pdo->prepare("DELETE FROM vr_video_permissions WHERE team_id = ?");
        $stmt->execute([$team_id]);

        $stmt = $pdo->prepare("
            INSERT INTO vr_video_permissions (user_id, team_id, can_upload, can_clip, can_tag, can_publish, can_delete)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($perms as $uid => $user_perms) {
            $uid = (int)$uid;
            if ($uid <= 0 || !in_array($uid, $valid_user_ids)) continue;
            $stmt->execute([
                $uid, $team_id,
                !empty($user_perms['can_upload']) ? 1 : 0,
                !empty($user_perms['can_clip']) ? 1 : 0,
                !empty($user_perms['can_tag']) ? 1 : 0,
                !empty($user_perms['can_publish']) ? 1 : 0,
                !empty($user_perms['can_delete']) ? 1 : 0,
            ]);
        }

        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        error_log('Update video permissions failed: ' . $e->getMessage());
        throw new Exception('Failed to update permissions. Please try again.');
    }

    logSecurityEvent($pdo, 'video_permissions_updated', "Video permissions updated for team $team_id", $user_id);
    header('Location: /gameplan.php?page=permissions&team_id=' . $team_id . '&success=permissions_saved');
    exit;
}

/**
 * Import calendar (ICS/CSV/URL)
 */
function handleImportCalendar() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to import calendars');
    }

    $import_type = $_POST['import_type'] ?? 'ical';
    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    $season_id = filter_input(INPUT_POST, 'season_id', FILTER_VALIDATE_INT) ?: null;
    if (!$team_id) throw new Exception('Team is required');

    $imported = 0;
    $updated = 0;
    $teams_created = 0;

    // Get our team name for parsing "Team A vs Team B" patterns
    $own_team_name = '';
    try {
        $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $own_team_name = $row['name'] ?? '';
    } catch (PDOException $e) { /* ignore */ }

    if ($import_type === 'csv' && isset($_FILES['calendar_file']) && $_FILES['calendar_file']['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($_FILES['calendar_file']['tmp_name'], 'r');
        if ($handle) {
            $header = fgetcsv($handle);
            $stmt = $pdo->prepare("
                INSERT INTO game_schedules (team_id, opponent_team, game_date, game_type, is_home_game, season_id)
                VALUES (?, ?, ?, 'regular', 1, ?)
            ");
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) >= 2) {
                    $opponent = trim($row[0] ?? '');
                    $date = trim($row[1] ?? '');
                    if (!empty($opponent) && !empty($date)) {
                        $stmt->execute([$team_id, $opponent, $date, $season_id]);
                        $imported++;
                        // Auto-create opponent team if it doesn't exist
                        $teams_created += autoCreateTeamIfNeeded($pdo, $opponent, $season_id);
                    }
                }
            }
            fclose($handle);
        }
    } elseif (in_array($import_type, ['ical', 'teamlinkt'])) {
        // Get iCal content from file upload or URL
        $ical_content = false;

        if (isset($_FILES['calendar_file']) && $_FILES['calendar_file']['error'] === UPLOAD_ERR_OK) {
            $ical_content = file_get_contents($_FILES['calendar_file']['tmp_name']);
        } elseif (!empty($_POST['calendar_url'])) {
            $url = filter_var($_POST['calendar_url'], FILTER_VALIDATE_URL);
            if (!$url) {
                throw new Exception('Invalid calendar URL');
            }
            // Only allow http/https schemes
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (!in_array($scheme, ['http', 'https'])) {
                throw new Exception('Only HTTP and HTTPS URLs are supported');
            }
            $ctx = stream_context_create([
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'ArcticWolves/1.0 Calendar Import',
                    'follow_location' => 1,
                    'max_redirects' => 5,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $ical_content = file_get_contents($url, false, $ctx);
            if ($ical_content === false) {
                $err = error_get_last();
                error_log('Calendar URL fetch failed: ' . ($err['message'] ?? 'unknown error') . ' URL: ' . $url);
                throw new Exception('Could not fetch calendar from URL. Please check the URL and try again.');
            }
        }

        if ($ical_content !== false && !empty($ical_content)) {
            $events = parseICalEvents($ical_content);

            // Prepare UPSERT statement: if ical_uid matches for this team, update; otherwise insert
            $stmt_upsert = $pdo->prepare("
                INSERT INTO game_schedules (team_id, opponent_team, game_date, game_type, is_home_game, notes, season_id, ical_uid)
                VALUES (?, ?, ?, ?, 1, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    opponent_team = VALUES(opponent_team),
                    game_date = VALUES(game_date),
                    game_type = VALUES(game_type),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
            ");
            // Fallback INSERT for events without UID (no upsert possible)
            $stmt_insert = $pdo->prepare("
                INSERT INTO game_schedules (team_id, opponent_team, game_date, game_type, is_home_game, notes, season_id)
                VALUES (?, ?, ?, ?, 1, ?, ?)
            ");

            $updated = 0;
            foreach ($events as $event) {
                if (!empty($event['summary']) && !empty($event['dtstart'])) {
                    $raw_summary = $event['summary'];

                    // Skip TBD/TBA events – these are placeholders without confirmed opponents
                    if (preg_match('/^\s*TBD\s*$/i', $raw_summary) || preg_match('/^\s*TBA\s*$/i', $raw_summary)) {
                        continue;
                    }

                    $game_type = 'regular';
                    // Detect game type from summary
                    $lower = strtolower($raw_summary);
                    if (strpos($lower, 'practice') !== false || strpos($lower, 'training') !== false) {
                        $game_type = 'practice';
                    } elseif (strpos($lower, 'tournament') !== false) {
                        $game_type = 'tournament';
                    } elseif (strpos($lower, 'playoff') !== false) {
                        $game_type = 'playoff';
                    } elseif (strpos($lower, 'exhibition') !== false || strpos($lower, 'scrimmage') !== false) {
                        $game_type = 'exhibition';
                    }

                    // Parse the opponent name from summary (handles "Team A vs Team B" and "Team A at Team B" formats)
                    $opponent = parseOpponentFromSummary($raw_summary, $own_team_name);
                    // If parsing returned empty (e.g. "Team - Tournament" format), use raw summary for the schedule record
                    if (empty($opponent)) {
                        $opponent = $raw_summary;
                    }

                    // Skip if parsed opponent is TBD/TBA
                    if (preg_match('/^\s*(TBD|TBA)\s*$/i', $opponent)) {
                        continue;
                    }

                    $event_uid = !empty($event['uid']) ? $event['uid'] : null;
                    $description = $event['description'] ?? '';

                    if ($event_uid) {
                        // UPSERT: insert or update based on ical_uid + team_id unique key
                        $stmt_upsert->execute([$team_id, $opponent, $event['dtstart'], $game_type, $description, $season_id, $event_uid]);
                        if ($stmt_upsert->rowCount() === 2) {
                            // rowCount=2 means ON DUPLICATE KEY UPDATE was triggered (existing row updated)
                            $updated++;
                        } else {
                            $imported++;
                        }
                    } else {
                        // No UID — simple insert (legacy behavior, can't deduplicate)
                        $stmt_insert->execute([$team_id, $opponent, $event['dtstart'], $game_type, $description, $season_id]);
                        $imported++;
                    }

                    // Only auto-create team for actual matchups (not practices/tournaments without a parsed opponent)
                    if ($game_type !== 'practice') {
                        $parsed_team = parseOpponentFromSummary($raw_summary, $own_team_name);
                        if (!empty($parsed_team) && !preg_match('/^\s*(TBD|TBA)\s*$/i', $parsed_team)) {
                            $teams_created += autoCreateTeamIfNeeded($pdo, $parsed_team, $season_id);
                        }
                    }
                }
            }

            // Store iCal URL on team for future re-sync
            $calendar_url = filter_var($_POST['calendar_url'] ?? '', FILTER_VALIDATE_URL);
            if ($calendar_url) {
                try {
                    $stmt = $pdo->prepare("UPDATE teams SET ical_url = ? WHERE id = ?");
                    $stmt->execute([$calendar_url, $team_id]);
                } catch (PDOException $e) {
                    error_log('Store ical_url: ' . $e->getMessage());
                }
            }
        }
    }

    $msg = "Calendar imported: $imported new, $updated updated, $teams_created teams created for team $team_id";
    logSecurityEvent($pdo, 'calendar_imported', $msg, $user_id);
    $success_msg = 'imported_' . $imported . ($updated > 0 ? '_updated_' . $updated : '');
    header('Location: /gameplan.php?page=calendar&success=' . $success_msg);
    exit;
}

/**
 * Re-sync a team's calendar from its stored iCal URL.
 * Fetches the iCal feed and uses the same UPSERT logic as import.
 */
function handleSyncCalendar() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to sync calendars');
    }

    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    if (!$team_id) throw new Exception('Team is required');

    // Load the team's stored iCal URL
    $stmt = $pdo->prepare("SELECT name, ical_url FROM teams WHERE id = ? AND is_managed = 1");
    $stmt->execute([$team_id]);
    $team = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$team || empty($team['ical_url'])) {
        throw new Exception('No calendar URL stored for this team. Import a calendar first using a URL.');
    }

    $url = $team['ical_url'];
    $own_team_name = $team['name'];

    // Fetch the iCal feed
    $scheme = parse_url($url, PHP_URL_SCHEME);
    if (!in_array($scheme, ['http', 'https'])) {
        throw new Exception('Only HTTP and HTTPS URLs are supported');
    }
    $ctx = stream_context_create([
        'http' => [
            'timeout' => 30,
            'user_agent' => 'ArcticWolves/1.0 Calendar Sync',
            'follow_location' => 1,
            'max_redirects' => 5,
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $ical_content = file_get_contents($url, false, $ctx);
    if ($ical_content === false) {
        throw new Exception('Could not fetch calendar from stored URL. The URL may have changed.');
    }

    // Get season_id from the most recent import for this team
    $season_id = null;
    try {
        $stmt = $pdo->prepare("SELECT season_id FROM game_schedules WHERE team_id = ? AND season_id IS NOT NULL ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$team_id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) $season_id = (int)$row['season_id'];
    } catch (PDOException $e) { /* use null */ }

    $events = parseICalEvents($ical_content);
    $imported = 0;
    $updated = 0;
    $teams_created = 0;

    $stmt_upsert = $pdo->prepare("
        INSERT INTO game_schedules (team_id, opponent_team, game_date, game_type, is_home_game, notes, season_id, ical_uid)
        VALUES (?, ?, ?, ?, 1, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            opponent_team = VALUES(opponent_team),
            game_date = VALUES(game_date),
            game_type = VALUES(game_type),
            notes = VALUES(notes),
            updated_at = CURRENT_TIMESTAMP
    ");
    $stmt_insert = $pdo->prepare("
        INSERT INTO game_schedules (team_id, opponent_team, game_date, game_type, is_home_game, notes, season_id)
        VALUES (?, ?, ?, ?, 1, ?, ?)
    ");

    foreach ($events as $event) {
        if (!empty($event['summary']) && !empty($event['dtstart'])) {
            $raw_summary = $event['summary'];

            if (preg_match('/^\s*TBD\s*$/i', $raw_summary) || preg_match('/^\s*TBA\s*$/i', $raw_summary)) {
                continue;
            }

            $game_type = 'regular';
            $lower = strtolower($raw_summary);
            if (strpos($lower, 'practice') !== false || strpos($lower, 'training') !== false) {
                $game_type = 'practice';
            } elseif (strpos($lower, 'tournament') !== false) {
                $game_type = 'tournament';
            } elseif (strpos($lower, 'playoff') !== false) {
                $game_type = 'playoff';
            } elseif (strpos($lower, 'exhibition') !== false || strpos($lower, 'scrimmage') !== false) {
                $game_type = 'exhibition';
            }

            $opponent = parseOpponentFromSummary($raw_summary, $own_team_name);
            if (empty($opponent)) {
                $opponent = $raw_summary;
            }
            if (preg_match('/^\s*(TBD|TBA)\s*$/i', $opponent)) {
                continue;
            }

            $event_uid = !empty($event['uid']) ? $event['uid'] : null;
            $description = $event['description'] ?? '';

            if ($event_uid) {
                $stmt_upsert->execute([$team_id, $opponent, $event['dtstart'], $game_type, $description, $season_id, $event_uid]);
                if ($stmt_upsert->rowCount() === 2) {
                    $updated++;
                } else {
                    $imported++;
                }
            } else {
                $stmt_insert->execute([$team_id, $opponent, $event['dtstart'], $game_type, $description, $season_id]);
                $imported++;
            }

            if ($game_type !== 'practice') {
                $parsed_team = parseOpponentFromSummary($raw_summary, $own_team_name);
                if (!empty($parsed_team) && !preg_match('/^\s*(TBD|TBA)\s*$/i', $parsed_team)) {
                    $teams_created += autoCreateTeamIfNeeded($pdo, $parsed_team, $season_id);
                }
            }
        }
    }

    logSecurityEvent($pdo, 'calendar_synced', "Calendar synced: $imported new, $updated updated, $teams_created teams created for team $team_id", $user_id);
    $success_msg = 'synced_' . $imported . '_updated_' . $updated;
    header('Location: /gameplan.php?page=calendar&success=' . $success_msg);
    exit;
}

/**
 * Parse iCal/ICS file content into an array of events.
 * Handles RFC 5545 line folding (continuation lines starting with space/tab).
 */
function parseICalEvents($content) {
    $events = [];

    // Unfold lines per RFC 5545 section 3.1: lines starting with a space or tab
    // are continuations of the previous line
    $content = preg_replace('/\r?\n[ \t]/', '', $content);

    $lines = preg_split('/\r?\n/', $content);
    $current = null;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === 'BEGIN:VEVENT') {
            $current = ['summary' => '', 'dtstart' => '', 'description' => '', 'location' => '', 'uid' => ''];
        } elseif ($line === 'END:VEVENT' && $current !== null) {
            $events[] = $current;
            $current = null;
        } elseif ($current !== null) {
            if (strpos($line, 'SUMMARY:') === 0 || strpos($line, 'SUMMARY;') === 0) {
                $current['summary'] = trim(substr($line, strpos($line, ':') + 1));
            } elseif (strpos($line, 'UID:') === 0 || strpos($line, 'UID;') === 0) {
                $current['uid'] = trim(substr($line, strpos($line, ':') + 1));
            } elseif (strpos($line, 'DTSTART') === 0) {
                $val = trim(substr($line, strpos($line, ':') + 1));
                // Convert iCal date format to MySQL datetime
                $val = str_replace(['T', 'Z'], [' ', ''], $val);
                if (strlen($val) >= 8 && ctype_digit(substr($val, 0, 8))) {
                    $year = (int)substr($val, 0, 4);
                    $month = (int)substr($val, 4, 2);
                    $day = (int)substr($val, 6, 2);
                    if (!checkdate($month, $day, $year)) continue;
                    $time = '00:00:00';
                    if (strlen($val) >= 15) {
                        $hh = (int)substr($val, 9, 2);
                        $mm = (int)substr($val, 11, 2);
                        $ss = (int)substr($val, 13, 2);
                        if ($hh >= 0 && $hh <= 23 && $mm >= 0 && $mm <= 59 && $ss >= 0 && $ss <= 59) {
                            $time = sprintf('%02d:%02d:%02d', $hh, $mm, $ss);
                        }
                    }
                    $current['dtstart'] = sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time);
                }
            } elseif (strpos($line, 'DESCRIPTION:') === 0 || strpos($line, 'DESCRIPTION;') === 0) {
                $current['description'] = trim(substr($line, strpos($line, ':') + 1));
            } elseif (strpos($line, 'LOCATION:') === 0 || strpos($line, 'LOCATION;') === 0) {
                $current['location'] = trim(substr($line, strpos($line, ':') + 1));
            }
        }
    }
    return $events;
}

/**
 * Parse opponent team name from a calendar event summary.
 * Handles common formats:
 *   "Team A vs Team B"  → returns Team B (opponent)
 *   "Team A vs. Team B" → returns Team B
 *   "Team A v Team B"   → returns Team B
 *   "Team A at Team B"  → returns Team B (away game)
 *   "Team A At Team B"  → returns Team B (away game)
 *   "Team -"            → tournament/non-matchup event, returns empty string
 * If the summary doesn't match a known pattern, returns the cleaned summary as-is.
 */
function parseOpponentFromSummary($summary, $ownTeamName = '') {
    $summary = trim($summary);
    if (empty($summary)) return '';

    // "Team - something" pattern (tournament events) → not a matchup
    if (preg_match('/^.+\s+-\s*$/i', $summary) || preg_match('/^.+\s+-\s+/i', $summary)) {
        // Check if it's "Team - Event Name" style (not a matchup)
        // Only skip if there's no "vs" or "at" separator in it
        if (stripos($summary, ' vs ') === false && stripos($summary, ' vs. ') === false && stripos($summary, ' v ') === false && stripos($summary, ' at ') === false) {
            return '';
        }
    }

    // "Team A vs Team B" or "Team A vs. Team B" or "Team A v Team B"
    // Also handles "Team A at Team B" and "Team A At Team B"
    if (preg_match('/^(.+?)\s+(?:vs\.?|v|at)\s+(.+)$/i', $summary, $m)) {
        $teamA = trim($m[1]);
        $teamB = trim($m[2]);

        // If we know our own team name, return the other team
        if (!empty($ownTeamName)) {
            $ownLower = strtolower($ownTeamName);
            // Check which side is our team (partial match to handle abbreviations)
            if (stripos($teamA, $ownTeamName) !== false || stripos($ownTeamName, $teamA) !== false
                || similar_text(strtolower($teamA), $ownLower, $pctA) && $pctA > 60) {
                return $teamB;
            }
            if (stripos($teamB, $ownTeamName) !== false || stripos($ownTeamName, $teamB) !== false
                || similar_text(strtolower($teamB), $ownLower, $pctB) && $pctB > 60) {
                return $teamA;
            }
        }
        // If we can't determine which is ours, return the second team (conventional: "Us vs Them" / "Us at Them")
        return $teamB;
    }

    // Remove common prefixes
    $cleaned = preg_replace('/^(vs\.?\s+|@\s+)/i', '', $summary);
    return trim($cleaned);
}

/**
 * Auto-create a team from an opponent name if a similar team doesn't already exist.
 * Returns 1 if a team was created, 0 otherwise.
 */
function autoCreateTeamIfNeeded($pdo, $teamName, $season_id = null) {
    $teamName = trim($teamName);
    if (empty($teamName)) return 0;

    // Remove common prefixes like "vs ", "vs. ", "@ "
    $cleanName = preg_replace('/^(vs\.?\s+|@\s+)/i', '', $teamName);
    $cleanName = trim($cleanName);
    if (empty($cleanName)) return 0;

    // Check for existing team with similar name (case-insensitive)
    try {
        $stmt = $pdo->prepare("SELECT id FROM teams WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$cleanName]);
        if ($stmt->fetch()) return 0; // Team already exists

        // Also check for fuzzy match (contains)
        $stmt = $pdo->prepare("SELECT id FROM teams WHERE LOWER(name) LIKE LOWER(?) LIMIT 1");
        $stmt->execute(['%' . $cleanName . '%']);
        if ($stmt->fetch()) return 0;

        // Create the team
        $season = '';
        if ($season_id) {
            $stmt = $pdo->prepare("SELECT name FROM seasons WHERE id = ?");
            $stmt->execute([$season_id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $season = $row['name'] ?? '';
        }

        $stmt = $pdo->prepare("INSERT INTO teams (name, season, is_active, is_managed) VALUES (?, ?, 1, 0)");
        $stmt->execute([$cleanName, $season]);
        return 1;
    } catch (PDOException $e) {
        error_log('Auto-create team: ' . $e->getMessage());
        return 0;
    }
}

function handleAddRosterPlayer() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $team_id = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    if (!$team_id) {
        header('Location: /gameplan.php?page=roster&error=invalid_team');
        exit;
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    if (empty($first_name) || empty($last_name)) {
        header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&error=missing_fields');
        exit;
    }

    $user_link_id  = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT) ?: null;
    $jersey_number = filter_input(INPUT_POST, 'jersey_number', FILTER_VALIDATE_INT) ?: null;
    $position      = trim($_POST['position'] ?? '');
    $email         = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
    $phone         = trim($_POST['phone'] ?? '') ?: null;
    $dob           = trim($_POST['date_of_birth'] ?? '') ?: null;
    $parent_name   = trim($_POST['parent_name'] ?? '') ?: null;
    $parent_email  = filter_input(INPUT_POST, 'parent_email', FILTER_VALIDATE_EMAIL) ?: null;
    $parent_phone  = trim($_POST['parent_phone'] ?? '') ?: null;
    $notes         = trim($_POST['notes'] ?? '') ?: null;
    $season_id     = filter_input(INPUT_POST, 'season_id', FILTER_VALIDATE_INT) ?: null;

    // Validate team exists
    $stmt = $pdo->prepare("SELECT id FROM teams WHERE id = ?");
    $stmt->execute([$team_id]);
    if (!$stmt->fetch()) {
        header('Location: /gameplan.php?page=roster&error=invalid_team');
        exit;
    }

    // Validate user_id FK if provided
    if ($user_link_id !== null) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$user_link_id]);
        if (!$stmt->fetch()) {
            $user_link_id = null; // Clear invalid FK reference
        }
    }

    // Validate season_id FK if provided
    if ($season_id !== null) {
        $stmt = $pdo->prepare("SELECT id FROM seasons WHERE id = ?");
        $stmt->execute([$season_id]);
        if (!$stmt->fetch()) {
            $season_id = null; // Clear invalid FK reference
        }
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO roster_players (team_id, user_id, first_name, last_name, email, phone,
                jersey_number, position, date_of_birth, parent_name, parent_email, parent_phone,
                notes, season_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $team_id, $user_link_id, $first_name, $last_name, $email, $phone,
            $jersey_number, $position, $dob, $parent_name, $parent_email, $parent_phone,
            $notes, $season_id
        ]);
    } catch (PDOException $e) {
        error_log('Add roster player failed: ' . $e->getMessage());
        header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&error=save_failed');
        exit;
    }

    logSecurityEvent($pdo, 'roster_player_added', "Added $first_name $last_name to team $team_id", $user_id);
    header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&success=player_added');
    exit;
}

function handleUpdateRosterPlayer() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $player_id = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);
    $team_id   = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    if (!$player_id || !$team_id) {
        header('Location: /gameplan.php?page=roster&error=player_not_found');
        exit;
    }

    $first_name = trim($_POST['first_name'] ?? '');
    $last_name  = trim($_POST['last_name'] ?? '');
    if (empty($first_name) || empty($last_name)) {
        header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&error=missing_fields');
        exit;
    }

    $jersey_number = filter_input(INPUT_POST, 'jersey_number', FILTER_VALIDATE_INT) ?: null;
    $position      = trim($_POST['position'] ?? '');
    $email         = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL) ?: null;
    $phone         = trim($_POST['phone'] ?? '') ?: null;
    $dob           = trim($_POST['date_of_birth'] ?? '') ?: null;
    $parent_name   = trim($_POST['parent_name'] ?? '') ?: null;
    $parent_email  = filter_input(INPUT_POST, 'parent_email', FILTER_VALIDATE_EMAIL) ?: null;
    $parent_phone  = trim($_POST['parent_phone'] ?? '') ?: null;
    $notes         = trim($_POST['notes'] ?? '') ?: null;
    $season_id     = filter_input(INPUT_POST, 'season_id', FILTER_VALIDATE_INT) ?: null;

    // Validate season_id FK if provided
    if ($season_id !== null) {
        $stmt = $pdo->prepare("SELECT id FROM seasons WHERE id = ?");
        $stmt->execute([$season_id]);
        if (!$stmt->fetch()) {
            $season_id = null;
        }
    }

    try {
        $stmt = $pdo->prepare("
            UPDATE roster_players SET
                first_name = ?, last_name = ?, email = ?, phone = ?,
                jersey_number = ?, position = ?, date_of_birth = ?,
                parent_name = ?, parent_email = ?, parent_phone = ?,
                notes = ?, season_id = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $first_name, $last_name, $email, $phone,
            $jersey_number, $position, $dob,
            $parent_name, $parent_email, $parent_phone,
            $notes, $season_id, $player_id
        ]);
    } catch (PDOException $e) {
        error_log('Update roster player failed: ' . $e->getMessage());
        header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&error=save_failed');
        exit;
    }

    logSecurityEvent($pdo, 'roster_player_updated', "Updated roster player $player_id", $user_id);
    header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&success=player_updated');
    exit;
}

function handleRemoveRosterPlayer() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $player_id = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);
    $team_id   = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    if (!$player_id) {
        header('Location: /gameplan.php?page=roster&error=player_not_found');
        exit;
    }

    // Soft delete: set status to archived (preserves data for future account linking)
    $stmt = $pdo->prepare("UPDATE roster_players SET status = 'archived' WHERE id = ?");
    $stmt->execute([$player_id]);

    logSecurityEvent($pdo, 'roster_player_removed', "Archived roster player $player_id", $user_id);
    header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&success=player_removed');
    exit;
}

function handleLinkRosterPlayer() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $player_id    = filter_input(INPUT_POST, 'player_id', FILTER_VALIDATE_INT);
    $link_user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
    $team_id      = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);

    if (!$player_id || !$link_user_id) {
        header('Location: /gameplan.php?page=roster&team_id=' . ($team_id ?: 0) . '&error=missing_fields');
        exit;
    }

    // Verify user exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
    $stmt->execute([$link_user_id]);
    if (!$stmt->fetch()) {
        header('Location: /gameplan.php?page=roster&team_id=' . ($team_id ?: 0) . '&error=player_not_found');
        exit;
    }

    // Link the roster player to the user account
    $stmt = $pdo->prepare("UPDATE roster_players SET user_id = ? WHERE id = ?");
    $stmt->execute([$link_user_id, $player_id]);

    logSecurityEvent($pdo, 'roster_player_linked', "Linked roster player $player_id to user $link_user_id", $user_id);
    header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&success=player_linked');
    exit;
}

function handleAddCalendarEvent() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $team_id   = filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT);
    if (!$team_id) throw new Exception('Team is required');

    $opponent  = trim($_POST['opponent_team'] ?? '');
    $game_date = trim($_POST['game_date'] ?? '');
    $game_time = trim($_POST['game_time'] ?? '');
    $game_type = trim($_POST['game_type'] ?? 'regular');
    $is_home   = isset($_POST['is_home_game']) ? 1 : 0;
    $notes     = trim($_POST['notes'] ?? '') ?: null;
    $season_id = filter_input(INPUT_POST, 'season_id', FILTER_VALIDATE_INT) ?: null;
    $location_id = filter_input(INPUT_POST, 'location_id', FILTER_VALIDATE_INT) ?: null;

    if (empty($game_date)) throw new Exception('Date is required');

    // Build datetime
    $datetime = $game_date;
    if (!empty($game_time)) {
        $datetime .= ' ' . $game_time;
    } else {
        $datetime .= ' 00:00:00';
    }

    // For practices, opponent can be empty - use team name
    if (empty($opponent) && $game_type === 'practice') {
        $stmt = $pdo->prepare("SELECT name FROM teams WHERE id = ?");
        $stmt->execute([$team_id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        $opponent = ($team['name'] ?? 'Team') . ' Practice';
    } elseif (empty($opponent)) {
        throw new Exception('Opponent team is required for games');
    }

    $valid_types = ['regular', 'playoff', 'tournament', 'exhibition', 'practice'];
    if (!in_array($game_type, $valid_types)) $game_type = 'regular';

    $stmt = $pdo->prepare("
        INSERT INTO game_schedules (team_id, opponent_team, game_date, game_type, is_home_game, notes, location_id, season_id)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$team_id, $opponent, $datetime, $game_type, $is_home, $notes, $location_id, $season_id]);

    logSecurityEvent($pdo, 'calendar_event_added', "Added $game_type event for team $team_id on $game_date", $user_id);
    header('Location: /gameplan.php?page=calendar&success=event_added');
    exit;
}

function handleCreateDevicePair() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT) ?: null;

    // Generate a unique 6-character pair code with collision retry
    $controller_token = bin2hex(random_bytes(32));
    $max_attempts = 5;
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $pair_code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        try {
            $stmt = $pdo->prepare("
                INSERT INTO vr_device_pairs (pair_code, session_id, controller_token, status, created_by)
                VALUES (?, ?, ?, 'waiting', ?)
            ");
            $stmt->execute([$pair_code, $session_id, $controller_token, $user_id]);
            break;
        } catch (PDOException $e) {
            if ($attempt === $max_attempts - 1) throw $e;
        }
    }

    logSecurityEvent($pdo, 'device_pair_created', "Created device pair $pair_code", $user_id);
    header('Location: /gameplan.php?page=video_review&tab=device_pair&success=pair_created');
    exit;
}

function handleJoinDevicePair() {
    global $pdo, $user_id;

    $pair_code = strtoupper(trim($_POST['pair_code'] ?? ''));
    if (empty($pair_code) || strlen($pair_code) > 10) {
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=invalid_code');
        exit;
    }

    $viewer_token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        UPDATE vr_device_pairs SET viewer_token = ?, status = 'paired'
        WHERE pair_code = ? AND status = 'waiting'
    ");
    $stmt->execute([$viewer_token, $pair_code]);

    if ($stmt->rowCount() === 0) {
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=pair_not_found');
        exit;
    }

    logSecurityEvent($pdo, 'device_pair_joined', "Joined device pair $pair_code as viewer", $user_id);
    header('Location: /gameplan.php?page=video_review&tab=device_pair&success=pair_joined');
    exit;
}

function handleJoinAsController() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $pair_code = strtoupper(trim($_POST['pair_code'] ?? ''));
    if (empty($pair_code) || strlen($pair_code) > 10) {
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=invalid_code');
        exit;
    }

    // Find the active/paired/waiting pair
    $stmt = $pdo->prepare("SELECT id, created_by FROM vr_device_pairs WHERE pair_code = ? AND status IN ('waiting', 'paired', 'active')");
    $stmt->execute([$pair_code]);
    $pair = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pair) {
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=pair_not_found');
        exit;
    }

    if ((int)$pair['created_by'] === (int)$user_id) {
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=already_owner');
        exit;
    }

    $controller_token = bin2hex(random_bytes(32));

    try {
        $stmt = $pdo->prepare("
            INSERT INTO vr_device_pair_controllers (pair_id, user_id, controller_token)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE controller_token = ?, joined_at = CURRENT_TIMESTAMP
        ");
        $stmt->execute([(int)$pair['id'], $user_id, $controller_token, $controller_token]);
    } catch (PDOException $e) {
        error_log('Join as controller: ' . $e->getMessage());
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=join_failed');
        exit;
    }

    logSecurityEvent($pdo, 'device_pair_controller_joined', "Joined device pair $pair_code as additional controller", $user_id);
    header('Location: /gameplan.php?page=video_review&tab=device_pair&success=controller_joined');
    exit;
}

function handleEndDevicePair() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required');
    }

    $pair_id = filter_input(INPUT_POST, 'pair_id', FILTER_VALIDATE_INT);
    if (!$pair_id) {
        header('Location: /gameplan.php?page=video_review&tab=device_pair&error=invalid_pair');
        exit;
    }

    $stmt = $pdo->prepare("UPDATE vr_device_pairs SET status = 'ended' WHERE id = ? AND created_by = ?");
    $stmt->execute([$pair_id, $user_id]);

    logSecurityEvent($pdo, 'device_pair_ended', "Ended device pair $pair_id", $user_id);
    header('Location: /gameplan.php?page=video_review&tab=device_pair&success=pair_ended');
    exit;
}

function handleToggleFreezePair() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        echo json_encode(['success' => false, 'error' => 'Coach access required']);
        exit;
    }

    $pair_id = filter_input(INPUT_POST, 'pair_id', FILTER_VALIDATE_INT);
    if (!$pair_id) {
        echo json_encode(['success' => false, 'error' => 'Invalid pair']);
        exit;
    }

    // Allow the pair creator or any joined controller to toggle freeze
    $stmt = $pdo->prepare("
        SELECT id FROM vr_device_pairs WHERE id = ? AND status = 'active'
        AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
    ");
    $stmt->execute([$pair_id, $user_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Not authorized or pair not active']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE vr_device_pairs SET is_frozen = NOT is_frozen WHERE id = ? AND status = 'active'");
    $stmt->execute([$pair_id]);

    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}
