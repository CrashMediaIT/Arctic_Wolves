<?php
/**
 * Process Skills Evaluation Actions
 * Handles all skills evaluation operations including scoring, notes, and media
 */

session_start();
require 'db_config.php';
require 'security.php';

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$is_coach = ($user_role === 'coach' || $user_role === 'coach_plus' || $user_role === 'admin');

function canManageEvaluation($pdo, $eval_id, $user_id, $is_coach) {
    if (!$is_coach) {
        return false;
    }
    
    $stmt = $pdo->prepare("SELECT id FROM athlete_evaluations WHERE id = ?");
    $stmt->execute([$eval_id]);
    return $stmt->fetch() !== false;
}

function canViewEvaluation($pdo, $eval_id, $user_id, $is_coach) {
    $stmt = $pdo->prepare("
        SELECT id FROM athlete_evaluations 
        WHERE id = ? AND (athlete_id = ? OR ? = 1)
    ");
    $stmt->execute([$eval_id, $user_id, $is_coach ? 1 : 0]);
    return $stmt->fetch() !== false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_evaluation':
                if (!$is_coach) {
                    throw new Exception('Only coaches can create evaluations');
                }
                
                $athlete_id = intval($_POST['athlete_id']);
                $evaluation_date = $_POST['evaluation_date'];
                $title = trim($_POST['title'] ?? '');
                
                // Validate date
                if (!strtotime($evaluation_date)) {
                    throw new Exception('Invalid date');
                }
                
                // Create evaluation
                $stmt = $pdo->prepare("
                    INSERT INTO athlete_evaluations (athlete_id, created_by, evaluation_date, title, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, 'draft', NOW(), NOW())
                ");
                $stmt->execute([$athlete_id, $user_id, $evaluation_date, $title]);
                $eval_id = $pdo->lastInsertId();
                
                // Create evaluation_scores for all active skills
                $skills = $pdo->query("
                    SELECT id FROM eval_skills WHERE is_active = 1
                ")->fetchAll(PDO::FETCH_COLUMN);
                
                if (!empty($skills)) {
                    $placeholders = implode(',', array_fill(0, count($skills), '(?, ?, NOW(), NOW())'));
                    $values = [];
                    foreach ($skills as $skill_id) {
                        $values[] = $eval_id;
                        $values[] = $skill_id;
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO evaluation_scores (evaluation_id, skill_id, created_at, updated_at)
                        VALUES $placeholders
                    ");
                    $stmt->execute($values);
                }
                
                echo json_encode([
                    'success' => true,
                    'evaluation_id' => $eval_id,
                    'message' => 'Evaluation created successfully'
                ]);
                break;
                
            case 'update_evaluation':
                if (!$is_coach) {
                    throw new Exception('Only coaches can update evaluations');
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    throw new Exception('Permission denied');
                }
                
                $title = trim($_POST['title'] ?? '');
                $evaluation_date = $_POST['evaluation_date'];
                
                if (!strtotime($evaluation_date)) {
                    throw new Exception('Invalid date');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE athlete_evaluations
                    SET title = ?, evaluation_date = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $evaluation_date, $eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation updated successfully'
                ]);
                break;
                
            case 'delete_evaluation':
                if (!$is_coach) {
                    throw new Exception('Only coaches can delete evaluations');
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    throw new Exception('Permission denied');
                }
                
                // Delete media files first
                $media = $pdo->prepare("SELECT media_url FROM evaluation_media WHERE evaluation_id = ?");
                $media->execute([$eval_id]);
                foreach ($media->fetchAll() as $row) {
                    if (file_exists($row['media_url'])) {
                        unlink($row['media_url']);
                    }
                }
                
                // Delete in order (foreign keys)
                $pdo->prepare("DELETE FROM evaluation_media WHERE evaluation_id = ?")->execute([$eval_id]);
                $pdo->prepare("DELETE FROM evaluation_scores WHERE evaluation_id = ?")->execute([$eval_id]);
                $pdo->prepare("DELETE FROM athlete_evaluations WHERE id = ?")->execute([$eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation deleted successfully'
                ]);
                break;
                
            case 'save_score':
                if (!$is_coach) {
                    throw new Exception('Only coaches can save scores');
                }
                
                $score_id = intval($_POST['score_id']);
                $score = $_POST['score'] ?? null;
                
                // Validate score (1-10 or NULL)
                if ($score !== null && $score !== '') {
                    $score = intval($score);
                    if ($score < 1 || $score > 10) {
                        throw new Exception('Score must be between 1 and 10');
                    }
                } else {
                    $score = null;
                }
                
                // Verify permission
                $check = $pdo->prepare("
                    SELECT ae.id 
                    FROM evaluation_scores es
                    JOIN athlete_evaluations ae ON es.evaluation_id = ae.id
                    WHERE es.id = ?
                ");
                $check->execute([$score_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid score ID');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE evaluation_scores
                    SET score = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$score, $score_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Score saved'
                ]);
                break;
                
            case 'save_notes':
                if (!$is_coach) {
                    throw new Exception('Only coaches can save notes');
                }
                
                $score_id = intval($_POST['score_id']);
                $note_type = $_POST['note_type']; // 'public' or 'private'
                $notes = trim($_POST['notes'] ?? '');
                
                if (!in_array($note_type, ['public', 'private'])) {
                    throw new Exception('Invalid note type');
                }
                
                // Verify permission
                $check = $pdo->prepare("
                    SELECT ae.id 
                    FROM evaluation_scores es
                    JOIN athlete_evaluations ae ON es.evaluation_id = ae.id
                    WHERE es.id = ?
                ");
                $check->execute([$score_id]);
                if (!$check->fetch()) {
                    throw new Exception('Invalid score ID');
                }
                
                $field = $note_type === 'public' ? 'public_notes' : 'private_notes';
                $stmt = $pdo->prepare("
                    UPDATE evaluation_scores
                    SET $field = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$notes, $score_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Notes saved'
                ]);
                break;
                
            case 'upload_media':
                if (!$is_coach) {
                    throw new Exception('Only coaches can upload media');
                }
                
                $score_id = intval($_POST['score_id']);
                
                if (!isset($_FILES['media'])) {
                    throw new Exception('No file uploaded');
                }
                
                // Verify permission and get evaluation_id
                $check = $pdo->prepare("
                    SELECT es.evaluation_id
                    FROM evaluation_scores es
                    JOIN athlete_evaluations ae ON es.evaluation_id = ae.id
                    WHERE es.id = ?
                ");
                $check->execute([$score_id]);
                $eval = $check->fetch();
                if (!$eval) {
                    throw new Exception('Invalid score ID');
                }
                
                $file = $_FILES['media'];
                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                
                // Validate file type
                $allowed_images = ['jpg', 'jpeg', 'png', 'gif'];
                $allowed_videos = ['mp4', 'mov', 'avi'];
                
                if (in_array($file_ext, $allowed_images)) {
                    $media_type = 'image';
                } elseif (in_array($file_ext, $allowed_videos)) {
                    $media_type = 'video';
                } else {
                    throw new Exception('Invalid file type. Allowed: jpg, png, gif, mp4, mov');
                }
                
                // Create upload directory if needed
                $upload_dir = 'uploads/evaluations/' . $eval['evaluation_id'];
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                // Generate unique filename with random bytes
                $filename = bin2hex(random_bytes(16)) . '.' . $file_ext;
                $filepath = $upload_dir . '/' . $filename;
                
                // Move file
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    throw new Exception('Failed to upload file');
                }
                
                // Save to database
                $stmt = $pdo->prepare("
                    INSERT INTO evaluation_media (evaluation_id, score_id, media_url, media_type, uploaded_by, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$eval['evaluation_id'], $score_id, $filepath, $media_type, $user_id]);
                
                echo json_encode([
                    'success' => true,
                    'media_id' => $pdo->lastInsertId(),
                    'media_url' => $filepath,
                    'media_type' => $media_type,
                    'message' => 'Media uploaded successfully'
                ]);
                break;
                
            case 'delete_media':
                if (!$is_coach) {
                    throw new Exception('Only coaches can delete media');
                }
                
                $media_id = intval($_POST['media_id']);
                
                // Get media info
                $stmt = $pdo->prepare("SELECT media_url FROM evaluation_media WHERE id = ?");
                $stmt->execute([$media_id]);
                $media = $stmt->fetch();
                
                if (!$media) {
                    throw new Exception('Media not found');
                }
                
                // Delete file
                if (file_exists($media['media_url'])) {
                    unlink($media['media_url']);
                }
                
                // Delete from database
                $pdo->prepare("DELETE FROM evaluation_media WHERE id = ?")->execute([$media_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Media deleted successfully'
                ]);
                break;
                
            case 'complete_evaluation':
                if (!$is_coach) {
                    throw new Exception('Only coaches can complete evaluations');
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    throw new Exception('Permission denied');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE athlete_evaluations
                    SET status = 'completed', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation marked as completed'
                ]);
                break;
                
            case 'archive_evaluation':
                if (!$is_coach) {
                    throw new Exception('Only coaches can archive evaluations');
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    throw new Exception('Permission denied');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE athlete_evaluations
                    SET status = 'archived', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Evaluation archived'
                ]);
                break;
                
            case 'generate_share_link':
                if (!$is_coach) {
                    throw new Exception('Only coaches can generate share links');
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    throw new Exception('Permission denied');
                }
                
                // Generate unique token
                $share_token = bin2hex(random_bytes(32));
                
                $stmt = $pdo->prepare("
                    UPDATE athlete_evaluations
                    SET share_token = ?, is_public = 1, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$share_token, $eval_id]);
                
                $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
                $share_url = $base_url . '/dashboard.php?page=view_shared_eval&token=' . $share_token;
                
                echo json_encode([
                    'success' => true,
                    'share_token' => $share_token,
                    'share_url' => $share_url,
                    'message' => 'Share link generated'
                ]);
                break;
                
            case 'revoke_share_link':
                if (!$is_coach) {
                    throw new Exception('Only coaches can revoke share links');
                }
                
                $eval_id = intval($_POST['evaluation_id']);
                
                if (!canManageEvaluation($pdo, $eval_id, $user_id, $is_coach)) {
                    throw new Exception('Permission denied');
                }
                
                $stmt = $pdo->prepare("
                    UPDATE athlete_evaluations
                    SET share_token = NULL, is_public = 0, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$eval_id]);
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Share link revoked'
                ]);
                break;
            
            case 'save_team_evaluation':
                if (!$is_coach) {
                    throw new Exception('Only coaches can save team evaluations');
                }
                
                $athlete_id = intval($_POST['athlete_id'] ?? 0);
                $add_to_database = ($_POST['add_to_database'] ?? '0') === '1';
                $scores_json = $_POST['scores'] ?? '[]';
                $scores = json_decode($scores_json, true);
                
                if (empty($scores)) {
                    throw new Exception('No scores provided');
                }
                
                // Check if athlete exists, create if requested
                $athlete_check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
                $athlete_check->execute([$athlete_id]);
                $athlete_exists = $athlete_check->fetch();
                
                if (!$athlete_exists && $add_to_database) {
                    throw new Exception('Athlete creation not implemented in this handler');
                }
                
                if (!$athlete_exists) {
                    throw new Exception('Athlete does not exist in database');
                }
                
                $pdo->beginTransaction();
                
                try {
                    // Create evaluation record
                    $eval_stmt = $pdo->prepare("
                        INSERT INTO athlete_evaluations (
                            athlete_id, created_by, evaluation_date, 
                            title, status, created_at
                        ) VALUES (?, ?, CURDATE(), ?, 'completed', NOW())
                    ");
                    $eval_stmt->execute([
                        $athlete_id,
                        $user_id,
                        'Team Evaluation - ' . date('Y-m-d')
                    ]);
                    
                    $evaluation_id = $pdo->lastInsertId();
                    
                    // Insert scores
                    $score_stmt = $pdo->prepare("
                        INSERT INTO evaluation_scores (
                            evaluation_id, skill_id, score, created_at
                        ) VALUES (?, ?, ?, NOW())
                    ");
                    
                    foreach ($scores as $score_data) {
                        $score_stmt->execute([
                            $evaluation_id,
                            intval($score_data['skill_id']),
                            intval($score_data['score'])
                        ]);
                    }
                    
                    $pdo->commit();
                    
                    echo json_encode([
                        'success' => true,
                        'message' => 'Team evaluation saved successfully',
                        'evaluation_id' => $evaluation_id
                    ]);
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    throw $e;
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    
} else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);
}
