<?php
/**
 * Process Workout Actions
 * Handles CRUD operations for exercises and workout plans
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

setSecurityHeaders();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

// Check permissions
$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['health_coach', 'coach', 'coach_plus', 'admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Validate CSRF
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'create_exercise':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $equipment_needed = trim($_POST['equipment_needed'] ?? '');
            $difficulty_level = trim($_POST['difficulty_level'] ?? '');
            $video_url = trim($_POST['video_url'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Exercise name is required');
            }
            
            // Handle image upload
            $image_url = null;
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/exercises/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('exercise_') . '.' . $ext;
                $uploadPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $image_url = $uploadPath;
                }
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO exercise_library (name, description, category, equipment_needed, difficulty_level, video_url, image_url, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description ?: null, $category ?: null, $equipment_needed ?: null, $difficulty_level ?: null, $video_url ?: null, $image_url, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Exercise created successfully']);
            break;
            
        case 'update_exercise':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $equipment_needed = trim($_POST['equipment_needed'] ?? '');
            $difficulty_level = trim($_POST['difficulty_level'] ?? '');
            $video_url = trim($_POST['video_url'] ?? '');
            
            if (empty($id) || empty($name)) {
                throw new Exception('Exercise ID and name are required');
            }
            
            // Handle image upload
            $image_sql = '';
            $params = [$name, $description ?: null, $category ?: null, $equipment_needed ?: null, $difficulty_level ?: null, $video_url ?: null];
            
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = 'uploads/exercises/';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
                $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('exercise_') . '.' . $ext;
                $uploadPath = $uploadDir . $filename;
                if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
                    $image_sql = ', image_url = ?';
                    $params[] = $uploadPath;
                }
            }
            
            $params[] = $id;
            
            $stmt = $pdo->prepare("
                UPDATE exercise_library 
                SET name = ?, description = ?, category = ?, equipment_needed = ?, difficulty_level = ?, video_url = ? $image_sql
                WHERE id = ?
            ");
            $stmt->execute($params);
            
            echo json_encode(['success' => true, 'message' => 'Exercise updated successfully']);
            break;
            
        case 'delete_exercise':
            $id = intval($_POST['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('Exercise ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM exercise_library WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Exercise deleted successfully']);
            break;
            
        case 'create_plan':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $difficulty_level = trim($_POST['difficulty_level'] ?? '');
            $exercises = $_POST['exercises'] ?? [];
            
            if (empty($name)) {
                throw new Exception('Plan name is required');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                INSERT INTO workout_plans (name, description, difficulty_level, created_by)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description ?: null, $difficulty_level ?: null, $user_id]);
            $plan_id = $pdo->lastInsertId();
            
            // Add exercises to plan
            if (!empty($exercises)) {
                $order = 0;
                foreach ($exercises as $ex) {
                    if (!empty($ex['id'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, sets, reps, rest_seconds, exercise_order)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $plan_id,
                            intval($ex['id']),
                            !empty($ex['sets']) ? intval($ex['sets']) : null,
                            !empty($ex['reps']) ? $ex['reps'] : null,
                            !empty($ex['rest']) ? intval($ex['rest']) : null,
                            $order++
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Workout plan created successfully']);
            break;
            
        case 'update_plan':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $exercises = $_POST['exercises'] ?? [];
            
            if (empty($id) || empty($name)) {
                throw new Exception('Plan ID and name are required');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE workout_plans SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description ?: null, $id]);
            
            // Remove existing exercises and re-add
            $pdo->prepare("DELETE FROM workout_plan_exercises WHERE workout_plan_id = ?")->execute([$id]);
            
            if (!empty($exercises)) {
                $order = 0;
                foreach ($exercises as $ex) {
                    if (!empty($ex['id'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO workout_plan_exercises (workout_plan_id, exercise_id, sets, reps, rest_seconds, exercise_order)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $id,
                            intval($ex['id']),
                            !empty($ex['sets']) ? intval($ex['sets']) : null,
                            !empty($ex['reps']) ? $ex['reps'] : null,
                            !empty($ex['rest']) ? intval($ex['rest']) : null,
                            $order++
                        ]);
                    }
                }
            }
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Workout plan updated successfully']);
            break;
            
        case 'delete_plan':
            $id = intval($_POST['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('Plan ID is required');
            }
            
            // Delete plan (cascade will handle related records)
            $stmt = $pdo->prepare("DELETE FROM workout_plans WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Workout plan deleted successfully']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
