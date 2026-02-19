<?php
/**
 * Process Workout Actions
 * Handles CRUD operations for exercises and workout plans
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
            
            $exercise_id = $pdo->lastInsertId();
            Auditor::log($pdo, $user_id, 'create', 'exercise_library', $exercise_id, ['action' => 'Exercise created']);
            
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
            
            Auditor::log($pdo, $user_id, 'update', 'exercise_library', $id, ['action' => 'Exercise updated']);
            
            echo json_encode(['success' => true, 'message' => 'Exercise updated successfully']);
            break;
            
        case 'delete_exercise':
            $id = intval($_POST['id'] ?? 0);
            
            if (empty($id)) {
                throw new Exception('Exercise ID is required');
            }
            
            $stmt = $pdo->prepare("DELETE FROM exercise_library WHERE id = ?");
            $stmt->execute([$id]);
            
            Auditor::log($pdo, $user_id, 'delete', 'exercise_library', $id, ['action' => 'Exercise deleted']);
            
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
            
            Auditor::log($pdo, $user_id, 'create', 'workout_plans', $plan_id, ['action' => 'Workout plan created']);
            
            echo json_encode(['success' => true, 'message' => 'Workout plan created successfully']);
            break;
            
        case 'update_plan':
            $id = intval($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $duration_weeks = !empty($_POST['duration_weeks']) ? intval($_POST['duration_weeks']) : null;
            $difficulty_level = trim($_POST['difficulty_level'] ?? '');
            $exercises = $_POST['exercises'] ?? [];
            
            if (empty($id) || empty($name)) {
                throw new Exception('Plan ID and name are required');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE workout_plans SET name = ?, description = ?, duration_weeks = ?, difficulty_level = ? WHERE id = ?");
            $stmt->execute([$name, $description ?: null, $duration_weeks, $difficulty_level ?: null, $id]);
            
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
            
            Auditor::log($pdo, $user_id, 'update', 'workout_plans', $id, ['action' => 'Workout plan updated']);
            
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
            
            Auditor::log($pdo, $user_id, 'delete', 'workout_plans', $id, ['action' => 'Workout plan deleted']);
            
            echo json_encode(['success' => true, 'message' => 'Workout plan deleted successfully']);
            break;
            
        case 'assign_athletes':
            $workout_plan_id = intval($_POST['workout_plan_id'] ?? 0);
            $athlete_ids = isset($_POST['athlete_ids']) && is_array($_POST['athlete_ids']) ? array_map('intval', array_filter($_POST['athlete_ids'])) : [];
            $start_date = $_POST['start_date'] ?? date('Y-m-d');
            $notes = trim($_POST['notes'] ?? '');
            $exercises = $_POST['exercises'] ?? [];
            
            if (empty($workout_plan_id)) {
                throw new Exception('Workout plan ID is required');
            }
            
            if (empty($athlete_ids)) {
                throw new Exception('Please select at least one athlete');
            }
            
            // Verify workout plan exists
            $plan_check = $pdo->prepare("SELECT id FROM workout_plans WHERE id = ?");
            $plan_check->execute([$workout_plan_id]);
            if (!$plan_check->fetch()) {
                throw new Exception('Invalid workout plan');
            }
            
            // Verify users exist and are active (allow any role to receive workout assignments)
            $placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
            $verify_stmt = $pdo->prepare("SELECT id FROM users WHERE id IN ($placeholders) AND is_active = 1");
            $verify_stmt->execute($athlete_ids);
            $valid_athletes = $verify_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Filter to only valid user IDs
            $athlete_ids = array_intersect($athlete_ids, $valid_athletes);
            if (empty($athlete_ids)) {
                throw new Exception('No valid users selected');
            }
            
            // Validate date format
            if (!empty($start_date)) {
                $d = DateTime::createFromFormat('Y-m-d', $start_date);
                if (!$d || $d->format('Y-m-d') !== $start_date) {
                    $start_date = date('Y-m-d');
                }
            }
            
            $pdo->beginTransaction();
            
            foreach ($athlete_ids as $athlete_id) {
                // Check if assignment already exists
                $check_stmt = $pdo->prepare("
                    SELECT id FROM athlete_workout_assignments 
                    WHERE athlete_id = ? AND workout_plan_id = ?
                ");
                $check_stmt->execute([$athlete_id, $workout_plan_id]);
                $existing = $check_stmt->fetch();
                
                if ($existing) {
                    // Update existing assignment
                    $update_stmt = $pdo->prepare("
                        UPDATE athlete_workout_assignments 
                        SET status = 'active', start_date = ?, notes = ?, assigned_by = ?, assigned_date = NOW()
                        WHERE id = ?
                    ");
                    $update_stmt->execute([$start_date, $notes, $user_id, $existing['id']]);
                    $assignment_id = $existing['id'];
                } else {
                    // Create new assignment
                    $insert_stmt = $pdo->prepare("
                        INSERT INTO athlete_workout_assignments 
                        (athlete_id, workout_plan_id, assigned_by, start_date, notes, status, assigned_date) 
                        VALUES (?, ?, ?, ?, ?, 'active', NOW())
                    ");
                    $insert_stmt->execute([$athlete_id, $workout_plan_id, $user_id, $start_date, $notes]);
                    $assignment_id = $pdo->lastInsertId();
                }
                
                // Save custom exercise settings if provided
                if (!empty($exercises)) {
                    foreach ($exercises as $ex) {
                        $exercise_id = intval($ex['exercise_id'] ?? 0);
                        if ($exercise_id <= 0) continue;
                        
                        $custom_sets = !empty($ex['custom_sets']) ? intval($ex['custom_sets']) : null;
                        $custom_reps = !empty($ex['custom_reps']) ? trim($ex['custom_reps']) : null;
                        $custom_weight = !empty($ex['custom_weight']) ? floatval($ex['custom_weight']) : null;
                        $custom_weight_unit = in_array($ex['custom_weight_unit'] ?? '', ['lbs', 'kg']) ? $ex['custom_weight_unit'] : 'lbs';
                        
                        // Only save if at least one custom value is provided
                        if ($custom_sets !== null || $custom_reps !== null || $custom_weight !== null) {
                            $settings_stmt = $pdo->prepare("
                                INSERT INTO athlete_exercise_settings 
                                (assignment_id, exercise_id, custom_sets, custom_reps, custom_weight, custom_weight_unit)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE 
                                custom_sets = VALUES(custom_sets),
                                custom_reps = VALUES(custom_reps),
                                custom_weight = VALUES(custom_weight),
                                custom_weight_unit = VALUES(custom_weight_unit),
                                updated_at = NOW()
                            ");
                            $settings_stmt->execute([$assignment_id, $exercise_id, $custom_sets, $custom_reps, $custom_weight, $custom_weight_unit]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            Auditor::log($pdo, $user_id, 'create', 'athlete_workout_assignments', null, ['action' => 'Workout plan assigned', 'plan_id' => $workout_plan_id, 'athletes' => count($athlete_ids)]);
            
            $count = count($athlete_ids);
            echo json_encode(['success' => true, 'message' => "Workout plan assigned to {$count} athlete(s) successfully"]);
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
