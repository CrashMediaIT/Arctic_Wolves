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
            
        default:
            throw new Exception('Invalid action');
    }
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
        // For coaches, they are their own coach for video uploads
        $stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();
        $coach_id = $athlete['assigned_coach_id'] ?? $user_id;
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
    
    $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
    $stmt->execute([$athlete_id]);
    $athlete = $stmt->fetch();
    if (!$athlete) {
        throw new Exception('Athlete not found');
    }
    $athlete_name = $athlete['name'];
    
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
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, email FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();
        $athlete_name = $athlete['name'] ?? 'An athlete';
        
        // Get coach email
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$coach_id]);
        $coach = $stmt->fetch();
        
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
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name FROM users WHERE id = ?");
        $stmt->execute([$coach_id]);
        $coach = $stmt->fetch();
        $coach_name = $coach['name'] ?? 'Your coach';
        
        // Get athlete email
        $stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete = $stmt->fetch();
        
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
