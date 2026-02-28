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
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Allow unlimited execution time for large video uploads
set_time_limit(0);

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

/**
 * Build redirect URL for device pair operations.
 * Uses the referrer_url POST field to detect PWA context, otherwise defaults to gameplan.php.
 * Only whitelisted internal paths are accepted to prevent open redirect.
 */
function devicePairRedirect($suffix) {
    $base = '/gameplan.php?page=video_review&tab=device_pair';
    $referrer = $_POST['referrer_url'] ?? '';
    // Only allow known internal PWA path — prevents open redirect
    if (!empty($referrer) && preg_match('#^/pwa\.php\b#', $referrer)) {
        $base = '/pwa.php?page=gameplan&gp=video_review&tab=device_pair';
    }
    return $base . '&' . $suffix;
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

        case 'get_athlete_upload_url':
            handleGetAthleteUploadUrl();
            break;

        case 'confirm_athlete_upload':
            handleConfirmAthleteUpload();
            break;

        case 'get_video_upload_url':
            handleGetVideoUploadUrl();
            break;

        case 'confirm_video_upload':
            handleConfirmVideoUpload();
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

        case 'resolve_import':
            handleResolveImport();
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

        case 'navigate_pair':
            handleNavigatePair();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (PDOException $e) {
    try { logSecurityEvent('video_error', $e->getMessage(), $user_id); } catch (Exception $le) {}
    ErrorLogger::error('process_video PDO error: ' . $e->getMessage());
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'A database error occurred. Please try again.']);
    exit;
} catch (Exception $e) {
    try { logSecurityEvent('video_error', $e->getMessage(), $user_id); } catch (Exception $le) {}
    header('Content-Type: application/json');
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
    $validation = $validator->validateVideo($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_filename = uniqid('video_', true) . '_' . time() . '.' . $file_extension;
    
    // Upload to RustFS
    $persist = persistUploadedFile($pdo, $file['tmp_name'], 'videos/coach', $unique_filename, '', true);
    if (!$persist['success']) {
        throw new Exception('Video upload to storage failed. Please try again.');
    }
    $db_video_url = $persist['rustfs_url'] ?? null;
    
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
    
    $title = $drill_name . ' - ' . $drill_type;
    $description = 'Session Date: ' . $session_date . ' | Drill Type: ' . $drill_type;
    
    $stmt->execute([
        $athlete_id,
        $user_id,
        $title,
        $description,
        $db_video_url,
        $comments
    ]);
    
    $video_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Coach video uploaded']);
    
    // Store Nextcloud path for persistent recovery
    if (!empty($persist['nextcloud_path'])) {
        $pdo->prepare("UPDATE videos SET nextcloud_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $video_id]);
    }
    
    // Trigger HLS transcoding via companion server (fire-and-forget)
    if (!empty($persist['object_key'])) {
        triggerHlsTranscode($pdo, $video_id, $persist['object_key']);
    }
    
    // Log and notify — wrapped in try-catch so failures don't break the upload response
    try {
        logSecurityEvent('video_upload', "Video uploaded for athlete ID: $athlete_id", $user_id);
    } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
    
    try {
        sendVideoNotification($pdo, $athlete_id, $user_id, $video_id, 'new_video');
    } catch (Exception $e) { error_log("sendVideoNotification failed: " . $e->getMessage()); }
    
    // Return JSON for XHR requests, redirect for standard form submissions
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'video_id' => $video_id, 'redirect' => 'dashboard.php?page=coaches_reviews&success=video_uploaded']);
        exit;
    }

    // Redirect back to coach reviews page
    header('Location: dashboard.php?page=coaches_reviews&success=video_uploaded');
    exit;
}

/**
 * Handle athlete video upload for coach review
 */
function handleAthleteVideoUpload() {
    global $pdo, $user_id, $user_role;
    
    // Get user's assigned coach from POST or look it up from the database.
    // All users (regardless of role) can have an assigned coach and upload
    // videos for review as an athlete.
    $coach_id = filter_input(INPUT_POST, 'coach_id', FILTER_VALIDATE_INT);
    
    // Get athlete_id from POST (auto-assigned to current user on the frontend)
    $athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT);
    
    // Default to current user if no athlete_id provided
    if (!$athlete_id) {
        $athlete_id = $user_id;
    }
    
    // Look up assigned coach if not provided via POST
    if (!$coach_id) {
        $stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        $coach_id = $user['assigned_coach_id'] ?? null;
    }
    // Allow upload even without an assigned coach — coach_id will be NULL
    
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
    $validation = $validator->validateVideo($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Generate unique filename
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_filename = uniqid('athlete_video_', true) . '_' . time() . '.' . $file_extension;
    
    // Look up athlete name for folder structure
    $athlete_folder = 'athlete_' . $athlete_id;
    $stmt_name = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt_name->execute([$athlete_id]);
    $athlete_row = $stmt_name->fetch();
    if ($athlete_row) {
        $athlete_row = decryptUserRow($athlete_row);
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim(($athlete_row['first_name'] ?? '') . '_' . ($athlete_row['last_name'] ?? '')));
        if (!empty($safe_name) && $safe_name !== '_') {
            $athlete_folder = $safe_name;
        }
    }
    
    // Upload to RustFS — folder: videos/athlete/{AthleteName}/
    $persist = persistUploadedFile($pdo, $file['tmp_name'], 'videos/athlete/' . $athlete_folder, $unique_filename, '', true);
    if (!$persist['success']) {
        throw new Exception('Video upload to storage failed. Please try again.');
    }
    $db_video_url = $persist['rustfs_url'] ?? null;
    
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
    
    $stmt->execute([
        $athlete_id,
        $coach_id,
        $title,
        $description,
        $db_video_url,
        $video_category,
        $game_date,
        $team_played_on,
        $opponent_team
    ]);
    
    $video_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Athlete video uploaded']);
    
    // Store Nextcloud path for persistent recovery
    if (!empty($persist['nextcloud_path'])) {
        $pdo->prepare("UPDATE videos SET nextcloud_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $video_id]);
    }
    
    // Trigger HLS transcoding via companion server (fire-and-forget)
    if (!empty($persist['object_key'])) {
        triggerHlsTranscode($pdo, $video_id, $persist['object_key']);
    }
    
    // Log and notify — wrapped in try-catch so failures don't break the upload response
    try {
        logSecurityEvent('athlete_video_upload', "Athlete video uploaded for review, ID: $video_id", $athlete_id);
    } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
    
    if ($coach_id) {
        try {
            sendVideoUploadNotificationToCoach($pdo, $coach_id, $athlete_id, $video_id, $title);
        } catch (Exception $e) { error_log("sendVideoUploadNotificationToCoach failed: " . $e->getMessage()); }
    }
    
    // Always return JSON — matches the working drill video upload pattern
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Video uploaded successfully',
        'video_id' => $video_id,
        'redirect' => 'dashboard.php?page=coaches_reviews&success=video_uploaded'
    ]);
    exit;
}

/**
 * Generate a presigned RustFS URL so the browser can upload a video directly
 * to object storage, bypassing the PHP server for the file transfer.
 * Returns the presigned PUT URL along with the object key and a session nonce.
 */
function handleGetAthleteUploadUrl() {
    global $pdo, $user_id, $user_role;

    header('Content-Type: application/json');

    // Validate coach assignment (same logic as handleAthleteVideoUpload)
    $coach_id = filter_input(INPUT_POST, 'coach_id', FILTER_VALIDATE_INT);
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    $is_coach = in_array($user_role, $allowed_roles);

    if ($is_coach) {
        $coach_id = $user_id;
    } else {
        if (!$coach_id) {
            $stmt = $pdo->prepare("SELECT assigned_coach_id FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            $coach_id = $user['assigned_coach_id'] ?? null;
        }
        // Allow upload even without an assigned coach — coach_id will be NULL
    }

    // Validate required fields
    $title = trim($_POST['title'] ?? '');
    if (empty($title)) {
        throw new Exception('Video title is required');
    }

    $video_category = $_POST['video_category'] ?? 'drill';
    if (!in_array($video_category, ['drill', 'game'])) {
        throw new Exception('Invalid video type');
    }

    // Validate file metadata sent from the client
    $file_name = $_POST['file_name'] ?? '';
    $file_size = filter_input(INPUT_POST, 'file_size', FILTER_VALIDATE_INT);
    $file_type = $_POST['file_type'] ?? 'video/mp4';

    if (empty($file_name) || !$file_size) {
        throw new Exception('File information is required');
    }

    // 10 GB limit
    if ($file_size > 10 * 1024 * 1024 * 1024) {
        throw new Exception('File size exceeds the maximum limit of 10GB');
    }

    // Validate extension
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['mp4', 'mkv', 'mov', 'avi', 'webm'];
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Invalid video file type. Allowed: ' . implode(', ', $allowed_extensions));
    }

    // Validate MIME type
    $allowed_mimes = ['video/mp4', 'video/x-matroska', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/avi'];
    if (!in_array($file_type, $allowed_mimes)) {
        // Fall back to a safe default based on extension
        $ext_to_mime = [
            'mp4' => 'video/mp4', 'mkv' => 'video/x-matroska', 'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo', 'webm' => 'video/webm',
        ];
        $file_type = $ext_to_mime[$file_extension] ?? 'application/octet-stream';
    }

    // Generate unique filename and object key with athlete name subfolder
    $unique_filename = uniqid('athlete_video_', true) . '_' . time() . '.' . $file_extension;

    // Look up athlete name for folder structure
    $presign_athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT) ?: $user_id;
    $athlete_folder = 'athlete_' . $presign_athlete_id;
    $stmt_name = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
    $stmt_name->execute([$presign_athlete_id]);
    $athlete_row = $stmt_name->fetch();
    if ($athlete_row) {
        $athlete_row = decryptUserRow($athlete_row);
        $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim(($athlete_row['first_name'] ?? '') . '_' . ($athlete_row['last_name'] ?? '')));
        if (!empty($safe_name) && $safe_name !== '_') {
            $athlete_folder = $safe_name;
        }
    }

    $object_key = 'Images/videos/athlete/' . $athlete_folder . '/' . $unique_filename;

    // Generate presigned URL
    $rustfs = getRustFSSettings($pdo);
    if (!isRustFSConfigured($rustfs)) {
        throw new Exception('Cloud storage is not configured. Please contact an administrator.');
    }

    $presigned = generatePresignedUploadUrl($rustfs, $object_key, $file_type, 3600);
    if (!$presigned['success']) {
        throw new Exception('Failed to generate upload URL: ' . ($presigned['message'] ?? 'Unknown error'));
    }

    // Store pending upload in session for confirmation step
    $upload_nonce = bin2hex(random_bytes(16));
    $_SESSION['pending_video_upload'] = [
        'nonce'          => $upload_nonce,
        'object_key'     => $object_key,
        'filename'       => $unique_filename,
        'content_type'   => $file_type,
        'coach_id'       => $coach_id,
        'athlete_id'     => filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT) ?: $user_id,
        'title'          => $title,
        'description'    => $_POST['description'] ?? '',
        'video_category' => $video_category,
        'game_date'      => $_POST['game_date'] ?? null,
        'team_played_on' => trim($_POST['team_played_on'] ?? ''),
        'opponent_team'  => trim($_POST['opponent_team'] ?? ''),
        'created_at'     => time(),
    ];

    echo json_encode([
        'success'       => true,
        'presigned_url' => $presigned['url'],
        'object_key'    => $object_key,
        'content_type'  => $file_type,
        'upload_nonce'  => $upload_nonce,
    ]);
    exit;
}

/**
 * Confirm that a direct-to-RustFS upload completed and create the video
 * database record.  The client calls this after the presigned PUT succeeds.
 */
function handleConfirmAthleteUpload() {
    global $pdo, $user_id;

    header('Content-Type: application/json');

    $upload_nonce = $_POST['upload_nonce'] ?? '';
    $pending = $_SESSION['pending_video_upload'] ?? null;

    if (!$pending || !hash_equals($pending['nonce'], $upload_nonce)) {
        throw new Exception('Invalid or expired upload session. Please try again.');
    }

    // Expire sessions older than 2 hours
    if ((time() - $pending['created_at']) > 7200) {
        unset($_SESSION['pending_video_upload']);
        throw new Exception('Upload session expired. Please try again.');
    }

    $object_key     = $pending['object_key'];
    $coach_id       = $pending['coach_id'];
    $athlete_id     = $pending['athlete_id'];
    $title          = $pending['title'];
    $description    = $pending['description'];
    $video_category = $pending['video_category'];
    $game_date      = $pending['game_date'] ?: null;
    $team_played_on = $pending['team_played_on'] ?: null;
    $opponent_team  = $pending['opponent_team'] ?: null;

    // Verify the object actually exists in RustFS
    $rustfs = getRustFSSettings($pdo);
    if (!rustfsObjectExists($rustfs, $object_key)) {
        throw new Exception('Video file was not found in storage. The upload may have failed — please try again.');
    }

    // Build the proxy URL
    $proxy_url = 'api/media.php?key=' . rawurlencode($object_key);

    // Insert the video record
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
    $stmt->execute([
        $athlete_id, $coach_id, $title, $description, $proxy_url,
        $video_category, $game_date, $team_played_on, $opponent_team,
    ]);

    $video_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Athlete video uploaded (direct)']);

    // Store the nextcloud_path (proxy URL) for persistent recovery
    $pdo->prepare("UPDATE videos SET nextcloud_path = ? WHERE id = ?")->execute([$proxy_url, $video_id]);

    // Trigger HLS transcoding via companion server (fire-and-forget)
    if (!empty($object_key)) {
        triggerHlsTranscode($pdo, $video_id, $object_key);
    }

    // Clean up the session
    unset($_SESSION['pending_video_upload']);

    // Log and notify
    try { logSecurityEvent('athlete_video_upload', "Athlete video uploaded (direct) for review, ID: $video_id", $athlete_id); } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
    try { sendVideoUploadNotificationToCoach($pdo, $coach_id, $athlete_id, $video_id, $title); } catch (Exception $e) { error_log("sendVideoUploadNotificationToCoach failed: " . $e->getMessage()); }

    echo json_encode([
        'success'  => true,
        'video_id' => $video_id,
        'redirect' => 'dashboard.php?page=coaches_reviews&success=video_uploaded',
    ]);
    exit;
}

/**
 * General-purpose presigned URL generator for all video upload types.
 * Supports: athlete_video, coach_video, drill_video, video_source.
 * The browser uploads directly to S3/RustFS, bypassing PHP for the file transfer.
 */
function handleGetVideoUploadUrl() {
    global $pdo, $user_id, $user_role;

    header('Content-Type: application/json');

    $upload_type = $_POST['upload_type'] ?? 'athlete_video';
    $allowed_types = ['athlete_video', 'coach_video', 'drill_video', 'video_source'];
    if (!in_array($upload_type, $allowed_types)) {
        throw new Exception('Invalid upload type');
    }

    // Role checks
    $coach_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (in_array($upload_type, ['coach_video', 'drill_video', 'video_source'])) {
        if (!in_array($user_role, $coach_roles)) {
            throw new Exception('You do not have permission for this upload type');
        }
    }

    // Validate file metadata
    $file_name = $_POST['file_name'] ?? '';
    $file_size = filter_input(INPUT_POST, 'file_size', FILTER_VALIDATE_INT);
    $file_type = $_POST['file_type'] ?? 'video/mp4';

    if (empty($file_name) || !$file_size) {
        throw new Exception('File information is required');
    }

    if ($file_size > 10 * 1024 * 1024 * 1024) {
        throw new Exception('File size exceeds the maximum limit of 10GB');
    }

    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
    $allowed_extensions = ['mp4', 'mkv', 'mov', 'avi', 'webm'];
    if (!in_array($file_extension, $allowed_extensions)) {
        throw new Exception('Invalid video file type. Allowed: ' . implode(', ', $allowed_extensions));
    }

    $allowed_mimes = ['video/mp4', 'video/x-matroska', 'video/quicktime', 'video/x-msvideo', 'video/webm', 'video/avi'];
    if (!in_array($file_type, $allowed_mimes)) {
        $ext_to_mime = [
            'mp4' => 'video/mp4', 'mkv' => 'video/x-matroska', 'mov' => 'video/quicktime',
            'avi' => 'video/x-msvideo', 'webm' => 'video/webm',
        ];
        $file_type = $ext_to_mime[$file_extension] ?? 'application/octet-stream';
    }

    // Build the S3 object key based on upload type
    $unique_suffix = uniqid('', true) . '_' . time() . '.' . $file_extension;

    if ($upload_type === 'drill_video') {
        // Drill videos: validate session/drill/athlete and build naming convention
        $session_id = filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT);
        $drill_id = filter_input(INPUT_POST, 'drill_id', FILTER_VALIDATE_INT);
        $athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT);
        $rep_number = filter_input(INPUT_POST, 'rep_number', FILTER_VALIDATE_INT) ?: 1;
        if (!$session_id || !$drill_id || !$athlete_id) {
            throw new Exception('Missing required fields: session, drill, and athlete are required');
        }
        $stmt = $pdo->prepare("SELECT title, session_date FROM sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        $session_row = $stmt->fetch();
        if (!$session_row) throw new Exception('Session not found');
        $stmt = $pdo->prepare("SELECT title FROM drills WHERE id = ?");
        $stmt->execute([$drill_id]);
        $drill_row = $stmt->fetch();
        if (!$drill_row) throw new Exception('Drill not found');
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete_row = $stmt->fetch();
        $athlete_row = decryptUserRow($athlete_row);
        if (!$athlete_row) throw new Exception('Athlete not found');
        $safe_session = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $session_row['title'] ?? 'Session'));
        $safe_drill = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $drill_row['title']));
        $safe_athlete = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', ($athlete_row['first_name'] ?? '') . ' ' . ($athlete_row['last_name'] ?? '')));
        $filename = sprintf('%s-%s-%s-Rep%d.%s', $safe_session, $safe_drill, $safe_athlete, $rep_number, $file_extension);
        $object_key = 'Images/DrillVideos/' . $filename;
    } elseif ($upload_type === 'video_source') {
        $filename = 'gp_source_' . $unique_suffix;
        $object_key = 'Images/videos/gameplan/' . $filename;
    } elseif ($upload_type === 'coach_video') {
        $filename = 'video_' . $unique_suffix;
        $object_key = 'Images/videos/coach/' . $filename;
    } else {
        // athlete_video
        $presign_athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT) ?: $user_id;
        $athlete_folder = 'athlete_' . $presign_athlete_id;
        $stmt_name = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt_name->execute([$presign_athlete_id]);
        $arow = $stmt_name->fetch();
        if ($arow) {
            $arow = decryptUserRow($arow);
            $safe_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', trim(($arow['first_name'] ?? '') . '_' . ($arow['last_name'] ?? '')));
            if (!empty($safe_name) && $safe_name !== '_') {
                $athlete_folder = $safe_name;
            }
        }
        $filename = 'athlete_video_' . $unique_suffix;
        $object_key = 'Images/videos/athlete/' . $athlete_folder . '/' . $filename;
    }

    // Generate presigned URL
    $rustfs = getRustFSSettings($pdo);
    if (!isRustFSConfigured($rustfs)) {
        throw new Exception('Cloud storage is not configured. Please contact an administrator.');
    }

    $presigned = generatePresignedUploadUrl($rustfs, $object_key, $file_type, 3600);
    if (!$presigned['success']) {
        throw new Exception('Failed to generate upload URL: ' . ($presigned['message'] ?? 'Unknown error'));
    }

    // Store pending upload metadata in session
    $upload_nonce = bin2hex(random_bytes(16));
    $_SESSION['pending_video_upload_general'] = [
        'nonce'          => $upload_nonce,
        'upload_type'    => $upload_type,
        'object_key'     => $object_key,
        'original_name'  => $file_name,
        'filename'       => $filename,
        'content_type'   => $file_type,
        'file_size'      => $file_size,
        'created_at'     => time(),
        // Type-specific metadata
        'coach_id'       => filter_input(INPUT_POST, 'coach_id', FILTER_VALIDATE_INT),
        'athlete_id'     => filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT) ?: $user_id,
        'title'          => trim($_POST['title'] ?? ''),
        'description'    => $_POST['description'] ?? '',
        'video_category' => $_POST['video_category'] ?? 'drill',
        'game_date'      => $_POST['game_date'] ?? null,
        'team_played_on' => trim($_POST['team_played_on'] ?? ''),
        'opponent_team'  => trim($_POST['opponent_team'] ?? ''),
        // Drill-specific
        'session_id'     => filter_input(INPUT_POST, 'session_id', FILTER_VALIDATE_INT),
        'drill_id'       => filter_input(INPUT_POST, 'drill_id', FILTER_VALIDATE_INT),
        'rep_number'     => filter_input(INPUT_POST, 'rep_number', FILTER_VALIDATE_INT) ?: 1,
        // Video source-specific
        'camera_angle'   => $_POST['camera_angle'] ?? '',
        'game_id'        => filter_input(INPUT_POST, 'game_id', FILTER_VALIDATE_INT),
        'team_id'        => filter_input(INPUT_POST, 'team_id', FILTER_VALIDATE_INT),
        // Coach video-specific
        'session_date'   => $_POST['session_date'] ?? null,
        'drill_type'     => $_POST['drill_type'] ?? null,
        'drill_name'     => $_POST['drill_name'] ?? null,
        'rating'         => filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT),
    ];

    echo json_encode([
        'success'       => true,
        'presigned_url' => $presigned['url'],
        'object_key'    => $object_key,
        'content_type'  => $file_type,
        'upload_nonce'  => $upload_nonce,
    ]);
    exit;
}

/**
 * Confirm that a direct-to-S3 upload completed and create the appropriate
 * database record based on upload_type.  Called after the presigned PUT succeeds.
 */
function handleConfirmVideoUpload() {
    global $pdo, $user_id;

    header('Content-Type: application/json');

    $upload_nonce = $_POST['upload_nonce'] ?? '';
    $pending = $_SESSION['pending_video_upload_general'] ?? null;

    if (!$pending || !hash_equals($pending['nonce'], $upload_nonce)) {
        throw new Exception('Invalid or expired upload session. Please try again.');
    }

    if ((time() - $pending['created_at']) > 7200) {
        unset($_SESSION['pending_video_upload_general']);
        throw new Exception('Upload session expired. Please try again.');
    }

    $object_key  = $pending['object_key'];
    $upload_type = $pending['upload_type'];

    // Verify the object actually exists in RustFS
    $rustfs = getRustFSSettings($pdo);
    if (!rustfsObjectExists($rustfs, $object_key)) {
        throw new Exception('Video file was not found in storage. The upload may have failed — please try again.');
    }

    $proxy_url = 'api/media.php?key=' . rawurlencode($object_key);
    $video_id = null;
    $source_id = null;
    $redirect = '';

    if ($upload_type === 'video_source') {
        // Game Plan video source
        $stmt = $pdo->prepare("
            INSERT INTO vr_video_sources (filename, file_path, camera_angle, file_size, game_id, team_id, uploaded_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $pending['original_name'],
            $proxy_url,
            $pending['camera_angle'],
            $pending['file_size'],
            $pending['game_id'],
            $pending['team_id'],
            $user_id
        ]);
        $source_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'vr_video_sources', $source_id, ['action' => 'Video source uploaded (direct)']);
        $pdo->prepare("UPDATE vr_video_sources SET nextcloud_path = ? WHERE id = ?")->execute([$proxy_url, $source_id]);
        try { logSecurityEvent('video_source_uploaded', "Video source uploaded (direct): " . $pending['original_name'], $user_id); } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
        $redirect = '/gameplan.php?page=film_room&tab=upload&success=source_uploaded';

    } elseif ($upload_type === 'drill_video') {
        // Drill video
        $session_id  = $pending['session_id'];
        $drill_id    = $pending['drill_id'];
        $athlete_id  = $pending['athlete_id'];
        $rep_number  = $pending['rep_number'];

        $stmt = $pdo->prepare("SELECT title, session_date FROM sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        $session_row = $stmt->fetch();
        $session_name = $session_row['title'] ?? 'Session';
        $session_date = $session_row['session_date'] ?? date('Y-m-d');

        $stmt = $pdo->prepare("SELECT title FROM drills WHERE id = ?");
        $stmt->execute([$drill_id]);
        $drill_row = $stmt->fetch();
        $drill_name = $drill_row['title'] ?? 'Drill';

        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$athlete_id]);
        $athlete_row = $stmt->fetch();
        $athlete_row = decryptUserRow($athlete_row);
        $athlete_name = ($athlete_row['first_name'] ?? '') . ' ' . ($athlete_row['last_name'] ?? '');

        $title = sprintf('%s - %s - %s (Rep %d)', $session_name, $drill_name, $athlete_name, $rep_number);
        $description = sprintf('Drill video recorded during session on %s', date('M d, Y', strtotime($session_date)));

        $stmt = $pdo->prepare("
            INSERT INTO videos (
                athlete_id, coach_id, title, description, video_url,
                video_type, video_category, drill_id, session_id, rep_number,
                nextcloud_path, local_path, is_uploaded_to_cloud,
                status, upload_date
            ) VALUES (
                ?, ?, ?, ?, ?,
                'drill_review', 'drill', ?, ?, ?,
                ?, ?, 1,
                'reviewed', NOW()
            )
        ");
        $stmt->execute([
            $athlete_id, $user_id, $title, $description, $proxy_url,
            $drill_id, $session_id, $rep_number,
            $proxy_url, $proxy_url
        ]);
        $video_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Drill video uploaded (direct)']);
        if (!empty($object_key)) { triggerHlsTranscode($pdo, $video_id, $object_key); }
        try { logSecurityEvent('drill_video_upload', "Drill video uploaded (direct): $title (ID: $video_id)", $user_id); } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
        $redirect = '';

    } elseif ($upload_type === 'coach_video') {
        // Coach review video
        $athlete_id  = $pending['athlete_id'];
        $drill_type  = $pending['drill_type'] ?? '';
        $drill_name  = $pending['drill_name'] ?? '';
        $session_date = $pending['session_date'] ?? date('Y-m-d');
        $comments    = $pending['description'] ?? '';

        $title = $drill_name . ' - ' . $drill_type;
        $description = 'Session Date: ' . $session_date . ' | Drill Type: ' . $drill_type;

        $stmt = $pdo->prepare("
            INSERT INTO videos (
                athlete_id, coach_id, title, description, video_url,
                video_type, video_category, status, coach_notes, upload_date
            ) VALUES (
                ?, ?, ?, ?, ?,
                'coach_review', 'drill', 'pending_review', ?, NOW()
            )
        ");
        $stmt->execute([$athlete_id, $user_id, $title, $description, $proxy_url, $comments]);
        $video_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Coach video uploaded (direct)']);
        $pdo->prepare("UPDATE videos SET nextcloud_path = ? WHERE id = ?")->execute([$proxy_url, $video_id]);
        if (!empty($object_key)) { triggerHlsTranscode($pdo, $video_id, $object_key); }
        try { logSecurityEvent('video_upload', "Video uploaded (direct) for athlete ID: $athlete_id", $user_id); } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
        try { sendVideoNotification($pdo, $athlete_id, $user_id, $video_id, 'new_video'); } catch (Exception $e) { error_log("sendVideoNotification failed: " . $e->getMessage()); }
        $redirect = 'dashboard.php?page=coaches_reviews&success=video_uploaded';

    } else {
        // athlete_video (default)
        $coach_id    = $pending['coach_id'];
        $athlete_id  = $pending['athlete_id'];
        $title       = $pending['title'];
        $description = $pending['description'];
        $video_category = $pending['video_category'];
        $game_date   = $pending['game_date'] ?: null;
        $team_played_on = $pending['team_played_on'] ?: null;
        $opponent_team  = $pending['opponent_team'] ?: null;

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
        $stmt->execute([
            $athlete_id, $coach_id, $title, $description, $proxy_url,
            $video_category, $game_date, $team_played_on, $opponent_team,
        ]);
        $video_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Athlete video uploaded (direct)']);
        $pdo->prepare("UPDATE videos SET nextcloud_path = ? WHERE id = ?")->execute([$proxy_url, $video_id]);
        if (!empty($object_key)) { triggerHlsTranscode($pdo, $video_id, $object_key); }
        try { logSecurityEvent('athlete_video_upload', "Athlete video uploaded (direct) for review, ID: $video_id", $athlete_id); } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
        try { sendVideoUploadNotificationToCoach($pdo, $coach_id, $athlete_id, $video_id, $title); } catch (Exception $e) { error_log("sendVideoUploadNotificationToCoach failed: " . $e->getMessage()); }
        $redirect = 'dashboard.php?page=coaches_reviews&success=video_uploaded';
    }

    unset($_SESSION['pending_video_upload_general']);

    $response = ['success' => true, 'redirect' => $redirect];
    if ($video_id) $response['video_id'] = $video_id;
    if ($source_id) $response['source_id'] = $source_id;
    echo json_encode($response);
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
    $stmt = $pdo->prepare("SELECT title, session_date FROM sessions WHERE id = ?");
    $stmt->execute([$session_id]);
    $session = $stmt->fetch();
    if (!$session) {
        throw new Exception('Session not found');
    }
    $session_name = $session['title'] ?? 'Session';
    $session_date = $session['session_date'] ?? date('Y-m-d');
    
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
    $validation = $validator->validateVideo($file);
    
    if (!$validation['valid']) {
        throw new Exception($validation['error']);
    }
    
    // Generate filename based on naming convention
    $safe_session = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $session_name));
    $safe_drill = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $drill_name));
    $safe_athlete = str_replace(' ', '_', preg_replace('/[^a-zA-Z0-9\-_\s]/', '', $athlete_name));
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $filename = sprintf('%s-%s-%s-Rep%d.%s', $safe_session, $safe_drill, $safe_athlete, $rep_number, $file_extension);
    $nextcloud_path = null;
    $is_uploaded_to_cloud = 0;
    
    // Upload to RustFS
    $persist = persistUploadedFile($pdo, $file['tmp_name'], 'DrillVideos', $filename, '', true);
    if (!$persist['success']) {
        throw new Exception('Video upload to storage failed. Please try again.');
    }
    if (!empty($persist['nextcloud_path'])) {
        $nextcloud_path = $persist['nextcloud_path'];
        $is_uploaded_to_cloud = 1;
    }
    $db_local_path = $persist['rustfs_url'] ?? null;
    
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
        $db_local_path,
        $drill_id,
        $session_id,
        $rep_number,
        $nextcloud_path,
        $db_local_path,
        $is_uploaded_to_cloud
    ]);
    
    $video_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'videos', $video_id, ['action' => 'Drill video uploaded']);
    
    // Trigger HLS transcoding via companion server (fire-and-forget)
    if (!empty($persist['object_key'])) {
        triggerHlsTranscode($pdo, $video_id, $persist['object_key']);
    }
    
    // Log the action - wrapped to not break upload response
    try {
        logSecurityEvent('drill_video_upload', "Drill video uploaded: $title (ID: $video_id)", $user_id);
    } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }
    
    header('Content-Type: application/json');
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
    $title = isset($_POST['title']) ? trim($_POST['title']) : null;
    $description = isset($_POST['description']) ? trim($_POST['description']) : null;
    
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
    
    // Update video notes using distinct field names
    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (in_array($user_role, $allowed_roles) && isset($_POST['coach_notes'])) {
        $stmt = $pdo->prepare("
            UPDATE videos 
            SET coach_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$_POST['coach_notes'], $video_id]);
    }
    
    // Athlete notes: athletes update their own notes, fallback to 'comments' for backwards compat
    $athlete_notes = $_POST['athlete_notes'] ?? $_POST['comments'] ?? null;
    if ($athlete_notes !== null && !in_array($user_role, $allowed_roles)) {
        $stmt = $pdo->prepare("
            UPDATE videos 
            SET athlete_notes = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$athlete_notes, $video_id]);

        // If the video was already reviewed and the athlete is replying with new content, move it back to pending
        $previous_notes = $video['athlete_notes'] ?? '';
        if (($video['status'] ?? '') === 'reviewed' && trim($athlete_notes) !== '' && trim($athlete_notes) !== trim($previous_notes)) {
            $stmt = $pdo->prepare("
                UPDATE videos SET status = 'pending_review', updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$video_id]);
        }
    }

    // Update title and description if provided (allowed for the athlete who uploaded or any coach)
    if ($title !== null && $title !== '') {
        $can_edit_meta = (int)$video['athlete_id'] === (int)$user_id || in_array($user_role, $allowed_roles);
        if ($can_edit_meta) {
            $stmt = $pdo->prepare("UPDATE videos SET title = ?, description = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$title, $description ?? '', $video_id]);
        }
    }
    
    logSecurityEvent('video_update', "Video ID: $video_id updated", $user_id);
    Auditor::log($pdo, $user_id, 'update', 'videos', $video_id, ['action' => 'Video updated']);
    
    header('Content-Type: application/json');
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
    
    // Athletes can only delete videos they uploaded themselves, not coach-recorded videos
    $is_coach_role = in_array($user_role, ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin']);
    if (!$is_coach_role && ($video['video_type'] ?? '') !== 'uploaded_by_athlete') {
        throw new Exception('You can only delete videos you uploaded yourself');
    }
    
    // Delete file from storage
    $video_url = $video['video_url'] ?? '';
    if (!empty($video_url)) {
        if (strpos($video_url, 'api/media.php?key=') !== false) {
            // RustFS proxy URL — extract the object key and delete from RustFS
            try {
                $parsed = [];
                parse_str(parse_url($video_url, PHP_URL_QUERY) ?? '', $parsed);
                $object_key = $parsed['key'] ?? '';
                if ($object_key !== '') {
                    $rustfs = getRustFSSettings($pdo);
                    if (isRustFSConfigured($rustfs)) {
                        deleteFromRustFS($rustfs, $object_key);
                    }
                }
            } catch (Exception $e) {
                error_log("Failed to delete RustFS object for video $video_id: " . $e->getMessage());
            }
        } elseif (!preg_match('#^https?://#', $video_url)) {
            // Local file path
            $file_path = __DIR__ . '/' . $video_url;
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
    }
    
    // Delete database record
    $stmt = $pdo->prepare("DELETE FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    Auditor::log($pdo, $user_id, 'delete', 'videos', $video_id, ['action' => 'Video deleted']);
    
    logSecurityEvent('video_delete', "Video ID: $video_id deleted", $user_id);
    
    header('Content-Type: application/json');
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
    Auditor::log($pdo, $user_id, 'update', 'videos', $video_id, ['action' => 'Video reviewed']);
    
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
    
    logSecurityEvent('video_review', "Video ID: $video_id reviewed", $user_id);
    
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
        ErrorLogger::error('Failed to send video notification: ' . $e->getMessage());
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
                ErrorLogger::error('Failed to send email to coach: ' . $email_error->getMessage());
            }
        }
    } catch (Exception $e) {
        ErrorLogger::error('Failed to send video upload notification to coach: ' . $e->getMessage());
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
                ErrorLogger::error('Failed to send email to athlete: ' . $email_error->getMessage());
            }
        }
    } catch (Exception $e) {
        ErrorLogger::error('Failed to send video review notification to athlete: ' . $e->getMessage());
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
        $plan_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'vr_game_plans', $plan_id, ['action' => 'Game plan created']);
    } catch (PDOException $e) {
        ErrorLogger::error('Create game plan failed: ' . $e->getMessage());
        throw new Exception('Failed to create game plan. Please try again.');
    }

    logSecurityEvent('game_plan_created', "Game plan created: $title", $user_id);
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
        ErrorLogger::error('Save hockey lines failed: ' . $e->getMessage());
        throw new Exception('Failed to save lines. Please try again.');
    }

    $game_param = $game_id ? '&game_id=' . $game_id : '';
    logSecurityEvent('hockey_lines_saved', "Hockey lines saved for team $team_id" . ($game_id ? " game $game_id" : " (default)"), $user_id);
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
    $validation = $validator->validateVideo($file);
    if (!$validation['valid']) throw new Exception($validation['error']);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $unique_name = 'gp_source_' . uniqid('', true) . '_' . time() . '.' . $ext;

    // Upload to RustFS
    $persist = persistUploadedFile($pdo, $file['tmp_name'], 'videos/gameplan', $unique_name, '', true);
    if (!$persist['success']) {
        throw new Exception('Video upload to storage failed. Please try again.');
    }
    $db_file_path = $persist['rustfs_url'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO vr_video_sources (filename, file_path, camera_angle, file_size, game_id, team_id, uploaded_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $file['name'],
        $db_file_path,
        $camera_angle,
        $file['size'],
        $game_id,
        $team_id,
        $user_id
    ]);
    $source_id_new = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'vr_video_sources', $source_id_new, ['action' => 'Video source uploaded']);

    // Store Nextcloud path for persistent recovery
    if (!empty($persist['nextcloud_path'])) {
        $pdo->prepare("UPDATE vr_video_sources SET nextcloud_path = ? WHERE id = ?")->execute([$persist['nextcloud_path'], $source_id_new]);
    }

    try {
        logSecurityEvent('video_source_uploaded', "Video source uploaded: " . $file['name'], $user_id);
    } catch (Exception $e) { error_log("logSecurityEvent failed: " . $e->getMessage()); }

    // Return JSON for XHR requests, redirect for standard form submissions
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'source_id' => $source_id_new, 'redirect' => '/gameplan.php?page=film_room&tab=upload&success=source_uploaded']);
        exit;
    }

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
    Auditor::log($pdo, $user_id, 'create', 'vr_video_clips', $clip_id, ['action' => 'Video clip created']);

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

    logSecurityEvent('clip_created', "Clip created: $title from source $source_id", $user_id);
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
    Auditor::log($pdo, $user_id, 'create', 'vr_review_sessions', $session_id, ['action' => 'Review session created']);

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

    logSecurityEvent('review_session_created', "Review session created: $title", $user_id);
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
        ErrorLogger::error('Update video permissions failed: ' . $e->getMessage());
        throw new Exception('Failed to update permissions. Please try again.');
    }

    logSecurityEvent('video_permissions_updated', "Video permissions updated for team $team_id", $user_id);
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
                ErrorLogger::error('Calendar URL fetch failed: ' . ($err['message'] ?? 'unknown error') . ' URL: ' . $url);
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
            $ambiguous_events = []; // Events that couldn't be confidently parsed
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

                    // Check if this event is ambiguous (couldn't determine opponent)
                    $is_ambiguous = false;
                    if (empty($opponent) && $game_type !== 'practice') {
                        $is_ambiguous = true;
                        $opponent = $raw_summary;
                    } elseif (!empty($opponent) && $opponent === $raw_summary && $game_type !== 'practice') {
                        // Opponent is the raw summary - no parsing was possible
                        $is_ambiguous = true;
                    }

                    // Skip if parsed opponent is TBD/TBA
                    if (preg_match('/^\s*(TBD|TBA)\s*$/i', $opponent)) {
                        continue;
                    }

                    // If ambiguous, store for manual review
                    if ($is_ambiguous) {
                        $ambiguous_events[] = [
                            'summary' => $raw_summary,
                            'dtstart' => $event['dtstart'],
                            'game_type' => $game_type,
                            'description' => $event['description'] ?? '',
                            'uid' => $event['uid'] ?? '',
                            'parsed_opponent' => $opponent,
                        ];
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
                    ErrorLogger::error('Store ical_url: ' . $e->getMessage());
                }
            }
            // Store ambiguous events in session for manual resolution
            if (!empty($ambiguous_events)) {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['import_ambiguous'] = [
                    'team_id' => $team_id,
                    'season_id' => $season_id,
                    'events' => $ambiguous_events,
                ];
            }
        }
    }

    $msg = "Calendar imported: $imported new, $updated updated, $teams_created teams created for team $team_id";
    logSecurityEvent('calendar_imported', $msg, $user_id);
    $success_msg = 'imported_' . $imported . ($updated > 0 ? '_updated_' . $updated : '');
    $ambiguous_count = count($ambiguous_events ?? []);
    if ($ambiguous_count > 0) {
        $success_msg .= '&review=' . $ambiguous_count;
    }
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

    logSecurityEvent('calendar_synced', "Calendar synced: $imported new, $updated updated, $teams_created teams created for team $team_id", $user_id);
    $success_msg = 'synced_' . $imported . '_updated_' . $updated;
    header('Location: /gameplan.php?page=calendar&success=' . $success_msg);
    exit;
}

/**
 * Handle manual resolution of ambiguous events from calendar import.
 * User provides opponent team names for events that couldn't be auto-parsed.
 */
function handleResolveImport() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        throw new Exception('Coach access required to resolve imports');
    }

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $stored = $_SESSION['import_ambiguous'] ?? null;
    if (!$stored || empty($stored['events'])) {
        header('Location: /gameplan.php?page=calendar');
        exit;
    }

    $team_id = (int)$stored['team_id'];
    $season_id = $stored['season_id'] ? (int)$stored['season_id'] : null;
    $events = $stored['events'];

    $resolved = $_POST['resolved'] ?? [];
    if (!is_array($resolved)) {
        throw new Exception('Invalid resolution data');
    }

    $imported = 0;
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

    foreach ($events as $idx => $event) {
        $opponent = trim($resolved[$idx] ?? '');
        if (empty($opponent) || preg_match('/^\s*(TBD|TBA|skip)\s*$/i', $opponent)) {
            continue; // User chose to skip this event
        }

        $event_uid = !empty($event['uid']) ? $event['uid'] : null;
        $description = $event['description'] ?? '';

        if ($event_uid) {
            $stmt_upsert->execute([$team_id, $opponent, $event['dtstart'], $event['game_type'], $description, $season_id, $event_uid]);
            $imported++;
        } else {
            $stmt_insert->execute([$team_id, $opponent, $event['dtstart'], $event['game_type'], $description, $season_id]);
            $imported++;
        }

        $teams_created += autoCreateTeamIfNeeded($pdo, $opponent, $season_id);
    }

    // Clear the session data
    unset($_SESSION['import_ambiguous']);

    logSecurityEvent('import_resolved', "Import resolution: $imported events resolved for team $team_id", $user_id);
    header('Location: /gameplan.php?page=calendar&success=resolved_' . $imported);
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
            $teamALower = strtolower($teamA);
            $teamBLower = strtolower($teamB);

            // Priority 1: Exact match (case-insensitive)
            if ($teamALower === $ownLower) return $teamB;
            if ($teamBLower === $ownLower) return $teamA;

            // Priority 2: Partial containment with length-proximity check
            // Allow a tolerance of 2 chars for minor variations (e.g., trailing spaces, punctuation)
            // but NOT for different team numbers (e.g., "Nats U7B" vs "Nats U7B2")
            if (stripos($teamA, $ownTeamName) !== false || stripos($ownTeamName, $teamA) !== false) {
                if (abs(strlen($teamA) - strlen($ownTeamName)) <= 2) {
                    return $teamB;
                }
            }
            if (stripos($teamB, $ownTeamName) !== false || stripos($ownTeamName, $teamB) !== false) {
                if (abs(strlen($teamB) - strlen($ownTeamName)) <= 2) {
                    return $teamA;
                }
            }

            // Priority 3: Fuzzy match using similar_text percentage
            // High confidence (>80%) = accept unconditionally
            // Medium confidence (>60%) = accept only if the other side doesn't also match
            similar_text($teamALower, $ownLower, $pctA);
            similar_text($teamBLower, $ownLower, $pctB);
            if ($pctA > 80) return $teamB;
            if ($pctB > 80) return $teamA;
            if ($pctA > 60 && $pctB <= 60) return $teamB;
            if ($pctB > 60 && $pctA <= 60) return $teamA;
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
        // Exact name match (case-insensitive) - prevents duplicates
        $stmt = $pdo->prepare("SELECT id FROM teams WHERE LOWER(name) = LOWER(?) LIMIT 1");
        $stmt->execute([$cleanName]);
        if ($stmt->fetch()) return 0; // Team already exists with exact name

        // Create the team as unmanaged (opponent)
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
        ErrorLogger::error('Auto-create team: ' . $e->getMessage());
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
        $new_player_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'roster_players', $new_player_id, ['action' => 'Roster player added']);
    } catch (PDOException $e) {
        ErrorLogger::error('Add roster player failed: ' . $e->getMessage());
        header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&error=save_failed');
        exit;
    }

    logSecurityEvent('roster_player_added', "Added $first_name $last_name to team $team_id", $user_id);
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
        Auditor::log($pdo, $user_id, 'update', 'roster_players', $player_id, ['action' => 'Roster player updated']);
    } catch (PDOException $e) {
        ErrorLogger::error('Update roster player failed: ' . $e->getMessage());
        header('Location: /gameplan.php?page=roster&team_id=' . $team_id . '&error=save_failed');
        exit;
    }

    logSecurityEvent('roster_player_updated', "Updated roster player $player_id", $user_id);
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
    Auditor::log($pdo, $user_id, 'update', 'roster_players', $player_id, ['action' => 'Roster player archived']);

    logSecurityEvent('roster_player_removed', "Archived roster player $player_id", $user_id);
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
    Auditor::log($pdo, $user_id, 'update', 'roster_players', $player_id, ['action' => 'Roster player linked to user']);

    logSecurityEvent('roster_player_linked', "Linked roster player $player_id to user $link_user_id", $user_id);
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
    $event_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'game_schedules', $event_id, ['action' => 'Calendar event added']);

    logSecurityEvent('calendar_event_added', "Added $game_type event for team $team_id on $game_date", $user_id);
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
            Auditor::log($pdo, $user_id, 'create', 'vr_device_pairs', $pdo->lastInsertId(), ['action' => 'Device pair created']);
            break;
        } catch (PDOException $e) {
            if ($attempt === $max_attempts - 1) throw $e;
        }
    }

    logSecurityEvent('device_pair_created', "Created device pair $pair_code", $user_id);
    header('Location: ' . devicePairRedirect('success=pair_created'));
    exit;
}

function handleJoinDevicePair() {
    global $pdo, $user_id;

    $pair_code = strtoupper(trim($_POST['pair_code'] ?? ''));
    if (empty($pair_code) || strlen($pair_code) > 10) {
        header('Location: ' . devicePairRedirect('error=invalid_code'));
        exit;
    }

    $viewer_token = bin2hex(random_bytes(32));

    $stmt = $pdo->prepare("
        UPDATE vr_device_pairs SET viewer_token = ?, status = 'paired'
        WHERE pair_code = ? AND status = 'waiting'
    ");
    $stmt->execute([$viewer_token, $pair_code]);

    if ($stmt->rowCount() === 0) {
        header('Location: ' . devicePairRedirect('error=pair_not_found'));
        exit;
    }

    logSecurityEvent('device_pair_joined', "Joined device pair $pair_code as viewer", $user_id);
    header('Location: ' . devicePairRedirect('success=pair_joined'));
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
        header('Location: ' . devicePairRedirect('error=invalid_code'));
        exit;
    }

    // Find the active/paired/waiting pair
    $stmt = $pdo->prepare("SELECT id, created_by FROM vr_device_pairs WHERE pair_code = ? AND status IN ('waiting', 'paired', 'active')");
    $stmt->execute([$pair_code]);
    $pair = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pair) {
        header('Location: ' . devicePairRedirect('error=pair_not_found'));
        exit;
    }

    if ((int)$pair['created_by'] === (int)$user_id) {
        header('Location: ' . devicePairRedirect('error=already_owner'));
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
        ErrorLogger::error('Join as controller: ' . $e->getMessage());
        header('Location: ' . devicePairRedirect('error=join_failed'));
        exit;
    }

    logSecurityEvent('device_pair_controller_joined', "Joined device pair $pair_code as additional controller", $user_id);
    header('Location: ' . devicePairRedirect('success=controller_joined'));
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
        header('Location: ' . devicePairRedirect('error=invalid_pair'));
        exit;
    }

    $stmt = $pdo->prepare("UPDATE vr_device_pairs SET status = 'ended' WHERE id = ? AND created_by = ?");
    $stmt->execute([$pair_id, $user_id]);
    Auditor::log($pdo, $user_id, 'update', 'vr_device_pairs', $pair_id, ['action' => 'Device pair ended']);

    logSecurityEvent('device_pair_ended', "Ended device pair $pair_id", $user_id);
    header('Location: ' . devicePairRedirect('success=pair_ended'));
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
        SELECT id FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
        AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
    ");
    $stmt->execute([$pair_id, $user_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Not authorized or pair not active']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE vr_device_pairs SET is_frozen = NOT is_frozen WHERE id = ? AND status IN ('paired', 'active')");
    $stmt->execute([$pair_id]);

    echo json_encode(['success' => $stmt->rowCount() > 0]);
    exit;
}

/**
 * Handle controller navigating to a page (syncs to TV viewer)
 */
function handleNavigatePair() {
    global $pdo, $user_id, $user_role;

    $allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
    if (!in_array($user_role, $allowed_roles)) {
        echo json_encode(['success' => false, 'error' => 'Coach access required']);
        exit;
    }

    $pair_id = filter_input(INPUT_POST, 'pair_id', FILTER_VALIDATE_INT);
    $target_page = preg_replace('/[^a-z0-9_]/', '', $_POST['target_page'] ?? '');
    if (!$pair_id || empty($target_page)) {
        echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
        exit;
    }

    // Validate page name
    $valid_pages = ['home', 'video_review', 'calendar', 'game_plan', 'film_room',
                    'review_sessions', 'my_clips', 'lines', 'roster', 'whiteboard', 'permissions'];
    if (!in_array($target_page, $valid_pages)) {
        echo json_encode(['success' => false, 'error' => 'Invalid page']);
        exit;
    }

    // Only the creator or joined controllers can navigate
    $stmt = $pdo->prepare("
        SELECT id FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
        AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
    ");
    $stmt->execute([$pair_id, $user_id, $user_id]);
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Not authorized']);
        exit;
    }

    $stmt = $pdo->prepare("UPDATE vr_device_pairs SET controller_page = ?, status = 'active' WHERE id = ?");
    $stmt->execute([$target_page, $pair_id]);

    echo json_encode(['success' => $stmt->rowCount() > 0, 'page' => $target_page]);
    exit;
}


/**
 * Trigger HLS transcoding via the companion server.
 * The main app controls storage locations — it tells the companion where the
 * source file is (source_key) and where to put the HLS output (output_prefix).
 * The companion calls back to /api/v1/companion/callback when done.
 * Non-blocking: if the companion is unavailable the upload still succeeds.
 */
function triggerHlsTranscode($pdo, $video_id, $object_key) {
    try {
        // Load companion settings from system_settings (correct key names)
        $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gameplan_companion_url', 'gameplan_companion_api_key', 'gameplan_app_url')");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'] ?? '';
        }
        $companion_url = $settings['gameplan_companion_url'] ?? '';
        $companion_key = $settings['gameplan_companion_api_key'] ?? '';
        $app_url       = $settings['gameplan_app_url'] ?? '';

        if (empty($companion_url)) {
            return; // Companion not configured – skip
        }

        $companion_url = rtrim($companion_url, "/");
        $hls_prefix = pathinfo($object_key, PATHINFO_FILENAME);
        $hls_dir    = pathinfo($object_key, PATHINFO_DIRNAME);
        $output_prefix = $hls_dir . "/" . $hls_prefix . "/hls";

        // Build callback URL so the companion can notify us when done
        $callback_url = '';
        if (!empty($app_url)) {
            $callback_url = rtrim($app_url, '/') . '/api/v1/companion/callback';
        }

        // Pre-build the HLS URL so the frontend can show a pending player
        $hls_manifest_url = "api/media.php?key=" . rawurlencode($output_prefix . "/master.m3u8");

        $payload = json_encode([
            "source_key"      => $object_key,
            "output_prefix"   => $output_prefix,
            "delete_original" => true,
            "video_id"        => $video_id,
            "callback_url"    => $callback_url,
        ]);

        // Mark video as pending HLS transcode and pre-set the expected HLS URL
        $pdo->prepare("UPDATE videos SET hls_status = 'pending', hls_url = ?, hls_master_url = ?, hls_segments_path = ? WHERE id = ?")
            ->execute([$hls_manifest_url, $output_prefix . "/master.m3u8", $output_prefix, $video_id]);

        // POST to companion /api/hls
        $ch = curl_init($companion_url . "/api/hls");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-API-Key: " . $companion_key,
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($http_code === 202 && $response) {
            $data = json_decode($response, true);
            if (!empty($data["id"])) {
                // Store the companion job ID so the callback can match it
                $pdo->prepare("UPDATE videos SET hls_job_id = ?, hls_status = 'processing' WHERE id = ?")
                    ->execute([$data["id"], $video_id]);
            }
        }
    } catch (Exception $e) {
        error_log("triggerHlsTranscode failed for video $video_id: " . $e->getMessage());
        // Non-fatal: the upload still succeeds
    }
}
