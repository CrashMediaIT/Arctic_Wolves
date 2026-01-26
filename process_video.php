<?php
/**
 * Process Video Operations
 * Handles video upload, update, and deletion operations
 */

require_once 'db_config.php';
require_once 'security.php';
require_once 'csrf_protection.php';
require_once 'lib/file_upload_validator.php';

// Validate session and user
validateSession();
$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['role'] ?? null;

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized access']);
    exit;
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validateCSRFToken();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'upload_video':
            handleVideoUpload();
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
 * Handle video file upload
 */
function handleVideoUpload() {
    global $pdo, $user_id, $user_role;
    
    // Only coaches can upload review videos
    if ($user_role !== 'coach') {
        throw new Exception('Only coaches can upload review videos');
    }
    
    // Validate required fields
    $athlete_id = filter_input(INPUT_POST, 'athlete_id', FILTER_VALIDATE_INT);
    $session_date = $_POST['session_date'] ?? null;
    $drill_type = $_POST['drill_type'] ?? null;
    $drill_name = $_POST['drill_name'] ?? null;
    $comments = $_POST['comments'] ?? '';
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    
    if (!$athlete_id || !$session_date || !$drill_type || !$drill_name) {
        throw new Exception('Missing required fields');
    }
    
    // Validate file upload
    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Video file upload failed');
    }
    
    $file = $_FILES['video_file'];
    
    // Use FileUploadValidator for security validation
    $validation = FileUploadValidator::validateVideo($file);
    
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
            video_type, status, coach_notes, drill_name, drill_type, rating, upload_date
        ) VALUES (
            ?, ?, ?, ?, ?,
            'coach_review', 'pending_review', ?, ?, ?, ?, NOW()
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
        $comments,
        $drill_name,
        $drill_type,
        $rating
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
    if ($user_role === 'coach') {
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
    
    // Get video details
    $stmt = $pdo->prepare("SELECT * FROM videos WHERE id = ? AND coach_id = ?");
    $stmt->execute([$video_id, $user_id]);
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
    
    if ($user_role !== 'coach') {
        throw new Exception('Only coaches can review videos');
    }
    
    $video_id = filter_input(INPUT_POST, 'video_id', FILTER_VALIDATE_INT);
    $review_comments = $_POST['review_comments'] ?? '';
    $rating = filter_input(INPUT_POST, 'rating', FILTER_VALIDATE_INT);
    
    if (!$video_id) {
        throw new Exception('Invalid video ID');
    }
    
    // Update video status
    $stmt = $pdo->prepare("
        UPDATE videos 
        SET status = 'reviewed',
            coach_notes = ?,
            reviewed_at = NOW()
        WHERE id = ? AND coach_id = ?
    ");
    $stmt->execute([$review_comments, $video_id, $user_id]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Video not found or access denied');
    }
    
    // Get athlete ID for notification
    $stmt = $pdo->prepare("SELECT athlete_id FROM videos WHERE id = ?");
    $stmt->execute([$video_id]);
    $video = $stmt->fetch();
    
    if ($video) {
        sendVideoNotification($pdo, $video['athlete_id'], $user_id, $video_id, 'video_reviewed');
    }
    
    logSecurityEvent($pdo, 'video_review', "Video ID: $video_id reviewed", $user_id);
    
    echo json_encode(['success' => true, 'message' => 'Video reviewed successfully']);
}

/**
 * Send notification for video events
 */
function sendVideoNotification($pdo, $athlete_id, $coach_id, $video_id, $type) {
    try {
        $message = '';
        
        switch ($type) {
            case 'new_video':
                $message = 'Your coach has uploaded a new review video for you';
                break;
            case 'video_reviewed':
                $message = 'Your coach has reviewed your video';
                break;
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, message, related_id, created_at)
            VALUES (?, 'video', ?, ?, NOW())
        ");
        $stmt->execute([$athlete_id, $message, $video_id]);
    } catch (Exception $e) {
        // Don't fail the main operation if notification fails
        error_log('Failed to send video notification: ' . $e->getMessage());
    }
}
