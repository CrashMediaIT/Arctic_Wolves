<?php
/**
 * Process Session Evaluations Actions
 * Handles assigning evaluations to sessions, managing athletes, and CSV imports
 */

session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Helper function to check if this is an AJAX request
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// Set JSON content type for AJAX requests early
if (isAjaxRequest()) {
    header('Content-Type: application/json');
}

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Not authenticated']));
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Only coaches and admins can manage session evaluations
$is_coach = in_array($user_role, ['coach', 'coach_plus', 'admin']);
if (!$is_coach) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Coach access required']));
}

// Helper function to send response
function sendResponse($success, $message, $data = []) {
    if (isAjaxRequest()) {
        header('Content-Type: application/json');
        echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    } else {
        $status = $success ? 'success' : 'error';
        header("Location: dashboard.php?page=coach_session_evaluations&status={$status}&message=" . urlencode($message));
    }
    exit;
}

// Helper function to safely parse date strings
function parseDateSafely($dateStr) {
    if (empty($dateStr)) {
        return null;
    }
    $timestamp = strtotime($dateStr);
    if ($timestamp === false) {
        return null;
    }
    return date('Y-m-d', $timestamp);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'assign_evaluation_to_session':
                $session_id_input = $_POST['session_id'] ?? '';
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                
                if (empty($session_id_input)) {
                    throw new Exception('Please select a valid session');
                }
                
                // Check if this is a template-based session (format: template_{template_id}_{date_id})
                if (strpos($session_id_input, 'template_') === 0) {
                    // Extract template_id and date_id from the session_id
                    $parts = explode('_', $session_id_input);
                    if (count($parts) !== 3) {
                        throw new Exception('Invalid template session format');
                    }
                    $template_id = intval($parts[1]);
                    $date_id = intval($parts[2]);
                    
                    // Get template and date information
                    $stmt = $pdo->prepare("
                        SELECT tst.*, tsd.session_date, tsd.team_id,
                               COALESCE(tsd.max_participants, tst.max_participants) as max_participants
                        FROM training_session_templates tst
                        INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id
                        WHERE tst.id = ? AND tsd.id = ? AND tst.is_active = 1 AND tsd.is_active = 1
                    ");
                    $stmt->execute([$template_id, $date_id]);
                    $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$template_data) {
                        throw new Exception('Template session not found or inactive');
                    }
                    
                    // Create a session record from the template
                    $stmt = $pdo->prepare("
                        INSERT INTO sessions (
                            session_type_id, coach_id, location_id, title, description,
                            session_date, session_time, duration_minutes, price, max_participants,
                            team_id, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                    ");
                    
                    // Parse session_date and validate
                    $timestamp = strtotime($template_data['session_date']);
                    if ($timestamp === false) {
                        throw new Exception('Invalid session date format');
                    }
                    $session_date = date('Y-m-d', $timestamp);
                    $session_time = date('H:i:s', $timestamp);
                    
                    $stmt->execute([
                        $template_data['session_type_id'],
                        $template_data['coach_id'],
                        $template_data['location_id'],
                        $template_data['name'],
                        $template_data['description'],
                        $session_date,
                        $session_time,
                        $template_data['duration_minutes'],
                        $template_data['price'],
                        $template_data['max_participants'],
                        $template_data['team_id']
                    ]);
                    
                    $session_id = $pdo->lastInsertId();
                    
                    // Link the training_session_date to this new session
                    $stmt = $pdo->prepare("UPDATE training_session_dates SET session_id = ? WHERE id = ?");
                    $stmt->execute([$session_id, $date_id]);
                } else {
                    // Regular session
                    $session_id = intval($session_id_input);
                    
                    if ($session_id <= 0) {
                        throw new Exception('Please select a valid session');
                    }
                    
                    // Verify session exists
                    $stmt = $pdo->prepare("SELECT id FROM sessions WHERE id = ?");
                    $stmt->execute([$session_id]);
                    if (!$stmt->fetch()) {
                        throw new Exception('Session not found');
                    }
                }
                
                // Check if evaluation already exists for this session
                $stmt = $pdo->prepare("SELECT id FROM session_evaluations WHERE session_id = ?");
                $stmt->execute([$session_id]);
                if ($stmt->fetch()) {
                    throw new Exception('An evaluation is already assigned to this session');
                }
                
                // Create session evaluation
                $stmt = $pdo->prepare("
                    INSERT INTO session_evaluations (session_id, name, description, status, created_by, created_at)
                    VALUES (?, ?, ?, 'draft', ?, NOW())
                ");
                $stmt->execute([$session_id, $name, $description, $user_id]);
                $evaluation_id = $pdo->lastInsertId();
                
                Auditor::log($pdo, $user_id, 'create', 'session_evaluations', $evaluation_id, ['action' => 'session_evaluation_created']);
                
                sendResponse(true, 'Evaluation assigned to session successfully', ['evaluation_id' => $evaluation_id]);
                break;
                
            case 'update_session_evaluation':
                $evaluation_id = intval($_POST['evaluation_id'] ?? 0);
                $name = trim($_POST['name'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $status = $_POST['status'] ?? 'draft';
                
                if ($evaluation_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                if (!in_array($status, ['draft', 'active', 'completed'])) {
                    $status = 'draft';
                }
                
                $stmt = $pdo->prepare("
                    UPDATE session_evaluations 
                    SET name = ?, description = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$name, $description, $status, $evaluation_id]);
                
                Auditor::log($pdo, $user_id, 'update', 'session_evaluations', $evaluation_id, ['action' => 'session_evaluation_updated']);
                
                sendResponse(true, 'Evaluation updated successfully');
                break;
                
            case 'delete_session_evaluation':
                $evaluation_id = intval($_POST['evaluation_id'] ?? 0);
                
                if ($evaluation_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                $stmt = $pdo->prepare("DELETE FROM session_evaluations WHERE id = ?");
                $stmt->execute([$evaluation_id]);
                
                Auditor::log($pdo, $user_id, 'delete', 'session_evaluations', $evaluation_id, ['action' => 'session_evaluation_deleted']);
                
                sendResponse(true, 'Evaluation deleted successfully');
                break;
                
            case 'add_athlete':
                $evaluation_id = intval($_POST['evaluation_id'] ?? 0);
                $user_athlete_id = intval($_POST['user_id'] ?? 0);
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $date_of_birth = $_POST['date_of_birth'] ?? null;
                $notes = trim($_POST['notes'] ?? '');
                
                if ($evaluation_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                // Track whether data is already encrypted (from existing user lookup)
                $already_encrypted = false;
                
                // If selecting existing user, get their info (already encrypted in DB)
                if ($user_athlete_id > 0) {
                    $stmt = $pdo->prepare("SELECT first_name, last_name, email, date_of_birth FROM users WHERE id = ?");
                    $stmt->execute([$user_athlete_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($user) {
                        $first_name = $user['first_name'];
                        $last_name = $user['last_name'];
                        $email = $user['email'];
                        $date_of_birth = $user['date_of_birth'];
                        $already_encrypted = true;
                    }
                }
                
                // For validation, decrypt if needed
                $plain_fn = $already_encrypted ? FieldEncryption::decrypt($first_name) : $first_name;
                $plain_ln = $already_encrypted ? FieldEncryption::decrypt($last_name) : $last_name;
                
                if (empty($plain_fn) || empty($plain_ln)) {
                    throw new Exception('First name and last name are required');
                }
                
                // Check if athlete already added to this evaluation
                if ($user_athlete_id > 0) {
                    $stmt = $pdo->prepare("SELECT id FROM session_evaluation_athletes WHERE session_evaluation_id = ? AND user_id = ?");
                    $stmt->execute([$evaluation_id, $user_athlete_id]);
                    if ($stmt->fetch()) {
                        throw new Exception('This athlete is already added to the evaluation');
                    }
                }
                
                // If email provided and user doesn't exist, create user account
                $new_user_id = $user_athlete_id;
                if ($user_athlete_id <= 0 && !empty($email)) {
                    // Check if user with this email already exists
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $existing = $stmt->fetch();
                    
                    if ($existing) {
                        $new_user_id = $existing['id'];
                        
                        // Check if this user is already added to the evaluation
                        $stmt = $pdo->prepare("SELECT id FROM session_evaluation_athletes WHERE session_evaluation_id = ? AND user_id = ?");
                        $stmt->execute([$evaluation_id, $new_user_id]);
                        if ($stmt->fetch()) {
                            throw new Exception('This athlete is already added to the evaluation');
                        }
                    } else {
                        // Create new user as athlete
                        $temp_password = bin2hex(random_bytes(8));
                        $enc_fn = FieldEncryption::encrypt($first_name);
                        $enc_ln = FieldEncryption::encrypt($last_name);
                        $plain_dob = !empty($date_of_birth) ? $date_of_birth : null;
                        $enc_dob = $plain_dob ? FieldEncryption::encrypt($plain_dob) : null;
                        $stmt = $pdo->prepare("
                            INSERT INTO users (first_name, last_name, email, password, role, date_of_birth, is_active, created_at)
                            VALUES (?, ?, ?, ?, 'athlete', ?, 1, NOW())
                        ");
                        $stmt->execute([
                            $enc_fn, 
                            $enc_ln, 
                            $email, 
                            password_hash($temp_password, PASSWORD_DEFAULT),
                            $enc_dob
                        ]);
                        $new_user_id = $pdo->lastInsertId();
                        
                        // TODO: Send welcome email with temp password
                    }
                }
                
                // Add athlete to evaluation - encrypt only if not already encrypted
                if ($already_encrypted) {
                    $enc_eval_fn = $first_name;
                    $enc_eval_ln = $last_name;
                    $enc_eval_dob = $date_of_birth;
                } else {
                    $enc_eval_fn = FieldEncryption::encrypt($first_name);
                    $enc_eval_ln = FieldEncryption::encrypt($last_name);
                    $enc_eval_dob = !empty($date_of_birth) ? FieldEncryption::encrypt($date_of_birth) : null;
                }
                $stmt = $pdo->prepare("
                    INSERT INTO session_evaluation_athletes 
                    (session_evaluation_id, user_id, first_name, last_name, email, date_of_birth, notes, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $evaluation_id,
                    $new_user_id > 0 ? $new_user_id : null,
                    $enc_eval_fn,
                    $enc_eval_ln,
                    $email ?: null,
                    $enc_eval_dob,
                    $notes ?: null
                ]);
                $athlete_id = $pdo->lastInsertId();
                
                Auditor::log($pdo, $user_id, 'create', 'session_evaluation_athletes', $athlete_id, ['action' => 'evaluation_athlete_added']);
                
                sendResponse(true, 'Athlete added successfully', ['athlete_id' => $athlete_id]);
                break;
                
            case 'update_athlete':
                $athlete_id = intval($_POST['athlete_id'] ?? 0);
                $first_name = trim($_POST['first_name'] ?? '');
                $last_name = trim($_POST['last_name'] ?? '');
                $email = trim($_POST['email'] ?? '');
                $date_of_birth = $_POST['date_of_birth'] ?? null;
                $notes = trim($_POST['notes'] ?? '');
                
                if ($athlete_id <= 0) {
                    throw new Exception('Invalid athlete ID');
                }
                
                if (empty($first_name) || empty($last_name)) {
                    throw new Exception('First name and last name are required');
                }
                
                $enc_upd_fn = FieldEncryption::encrypt($first_name);
                $enc_upd_ln = FieldEncryption::encrypt($last_name);
                $enc_upd_dob = $date_of_birth ? FieldEncryption::encrypt($date_of_birth) : null;

                $stmt = $pdo->prepare("
                    UPDATE session_evaluation_athletes 
                    SET first_name = ?, last_name = ?, email = ?, date_of_birth = ?, notes = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$enc_upd_fn, $enc_upd_ln, $email ?: null, $enc_upd_dob, $notes ?: null, $athlete_id]);
                
                Auditor::log($pdo, $user_id, 'update', 'session_evaluation_athletes', $athlete_id, ['action' => 'evaluation_athlete_updated']);
                
                sendResponse(true, 'Athlete updated successfully');
                break;
                
            case 'remove_athlete':
                $athlete_id = intval($_POST['athlete_id'] ?? 0);
                
                if ($athlete_id <= 0) {
                    throw new Exception('Invalid athlete ID');
                }
                
                $stmt = $pdo->prepare("DELETE FROM session_evaluation_athletes WHERE id = ?");
                $stmt->execute([$athlete_id]);
                
                Auditor::log($pdo, $user_id, 'delete', 'session_evaluation_athletes', $athlete_id, ['action' => 'evaluation_athlete_removed']);
                
                sendResponse(true, 'Athlete removed successfully');
                break;
                
            case 'import_athletes_csv':
                $evaluation_id = intval($_POST['evaluation_id'] ?? 0);
                
                if ($evaluation_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
                    throw new Exception('Please upload a valid CSV file');
                }
                
                $file = $_FILES['csv_file']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if ($handle === false) {
                    throw new Exception('Could not open the CSV file');
                }
                
                // Read header row
                $header = fgetcsv($handle);
                if ($header === false) {
                    fclose($handle);
                    throw new Exception('CSV file is empty');
                }
                
                // Normalize header names
                $header = array_map(function($col) {
                    return strtolower(trim($col));
                }, $header);
                
                // Required columns
                $required = ['first_name', 'last_name'];
                foreach ($required as $col) {
                    if (!in_array($col, $header)) {
                        fclose($handle);
                        throw new Exception("Missing required column: $col");
                    }
                }
                
                $imported = 0;
                $errors = [];
                $row_num = 1;
                
                while (($row = fgetcsv($handle)) !== false) {
                    $row_num++;
                    
                    if (count($row) < count($header)) {
                        $errors[] = "Row $row_num: Incomplete data";
                        continue;
                    }
                    
                    $data = array_combine($header, $row);
                    
                    $first_name = trim($data['first_name'] ?? '');
                    $last_name = trim($data['last_name'] ?? '');
                    $email = trim($data['email'] ?? '');
                    $date_of_birth = trim($data['date_of_birth'] ?? $data['dob'] ?? '');
                    $notes = trim($data['notes'] ?? '');
                    
                    if (empty($first_name) || empty($last_name)) {
                        $errors[] = "Row $row_num: First name and last name are required";
                        continue;
                    }
                    
                    // Process email and create user if needed
                    $new_user_id = null;
                    if (!empty($email)) {
                        // Check if user with this email exists
                        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                        $stmt->execute([$email]);
                        $existing = $stmt->fetch();
                        
                        if ($existing) {
                            $new_user_id = $existing['id'];
                        } else {
                            // Create new user
                            $temp_password = bin2hex(random_bytes(8));
                            $enc_fn2 = FieldEncryption::encrypt($first_name);
                            $enc_ln2 = FieldEncryption::encrypt($last_name);
                            $dob = parseDateSafely($date_of_birth);
                            $enc_dob2 = $dob ? FieldEncryption::encrypt($dob) : null;
                            $stmt = $pdo->prepare("
                                INSERT INTO users (first_name, last_name, email, password, role, date_of_birth, is_active, created_at)
                                VALUES (?, ?, ?, ?, 'athlete', ?, 1, NOW())
                            ");
                            $stmt->execute([
                                $enc_fn2,
                                $enc_ln2,
                                $email,
                                password_hash($temp_password, PASSWORD_DEFAULT),
                                $enc_dob2
                            ]);
                            $new_user_id = $pdo->lastInsertId();
                        }
                    }
                    
                    // Check if athlete already added (by user_id or by name if no user_id)
                    if ($new_user_id) {
                        $stmt = $pdo->prepare("SELECT id FROM session_evaluation_athletes WHERE session_evaluation_id = ? AND user_id = ?");
                        $stmt->execute([$evaluation_id, $new_user_id]);
                        if ($stmt->fetch()) {
                            $errors[] = "Row $row_num: Athlete already exists in this evaluation";
                            continue;
                        }
                    }
                    
                    // Add athlete (encrypt PII)
                    $enc_csv_fn = FieldEncryption::encrypt($first_name);
                    $enc_csv_ln = FieldEncryption::encrypt($last_name);
                    $dob = parseDateSafely($date_of_birth);
                    $enc_csv_dob = $dob ? FieldEncryption::encrypt($dob) : null;
                    $stmt = $pdo->prepare("
                        INSERT INTO session_evaluation_athletes 
                        (session_evaluation_id, user_id, first_name, last_name, email, date_of_birth, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $evaluation_id,
                        $new_user_id,
                        $enc_csv_fn,
                        $enc_csv_ln,
                        $email ?: null,
                        $enc_csv_dob,
                        $notes ?: null
                    ]);
                    $imported++;
                }
                
                fclose($handle);
                
                $message = "Imported $imported athletes successfully";
                if (!empty($errors)) {
                    $message .= ". " . count($errors) . " rows had errors.";
                }
                
                sendResponse(true, $message, ['imported' => $imported, 'errors' => $errors]);
                break;
                
            case 'save_evaluation_scores':
                $evaluation_id = intval($_POST['evaluation_id'] ?? 0);
                $athlete_id = intval($_POST['athlete_id'] ?? 0);
                $scores = $_POST['scores'] ?? [];
                
                if ($evaluation_id <= 0 || $athlete_id <= 0) {
                    throw new Exception('Invalid evaluation or athlete ID');
                }
                
                if (!is_array($scores)) {
                    throw new Exception('Invalid scores data');
                }
                
                foreach ($scores as $skill_id => $score_data) {
                    $skill_id = intval($skill_id);
                    $rating = isset($score_data['rating']) ? intval($score_data['rating']) : null;
                    $notes = trim($score_data['notes'] ?? '');
                    
                    if ($skill_id <= 0) continue;
                    
                    // Upsert score
                    $stmt = $pdo->prepare("
                        INSERT INTO session_evaluation_scores 
                        (session_evaluation_id, athlete_id, skill_id, rating, notes, evaluator_id, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ON DUPLICATE KEY UPDATE 
                        rating = VALUES(rating), 
                        notes = VALUES(notes), 
                        evaluator_id = VALUES(evaluator_id),
                        updated_at = NOW()
                    ");
                    $stmt->execute([
                        $evaluation_id,
                        $athlete_id,
                        $skill_id,
                        $rating,
                        $notes ?: null,
                        $user_id
                    ]);
                }
                
                Auditor::log($pdo, $user_id, 'update', 'session_evaluation_scores', $evaluation_id, ['action' => 'evaluation_scores_saved']);
                
                sendResponse(true, 'Evaluation scores saved successfully');
                break;
                
            default:
                throw new Exception('Invalid action');
        }
        
    } catch (Exception $e) {
        sendResponse(false, $e->getMessage());
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    try {
        switch ($action) {
            case 'get_sessions_with_evaluations':
                // Get all sessions that have evaluations assigned
                $stmt = $pdo->prepare("
                    SELECT s.id, s.title, s.session_date, s.duration_minutes, s.status as session_status,
                           se.id as evaluation_id, se.name as evaluation_name, se.status as evaluation_status,
                           se.created_at as evaluation_created,
                           (SELECT COUNT(*) FROM session_evaluation_athletes WHERE session_evaluation_id = se.id) as athlete_count
                    FROM sessions s
                    INNER JOIN session_evaluations se ON s.id = se.session_id
                    ORDER BY s.session_date DESC
                ");
                $stmt->execute();
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'sessions' => $sessions]);
                break;
                
            case 'get_evaluation_details':
                $evaluation_id = intval($_GET['evaluation_id'] ?? 0);
                
                if ($evaluation_id <= 0) {
                    throw new Exception('Invalid evaluation ID');
                }
                
                // Get evaluation with session info
                $stmt = $pdo->prepare("
                    SELECT se.*, s.title as session_title, s.session_date, s.duration_minutes
                    FROM session_evaluations se
                    INNER JOIN sessions s ON se.session_id = s.id
                    WHERE se.id = ?
                ");
                $stmt->execute([$evaluation_id]);
                $evaluation = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$evaluation) {
                    throw new Exception('Evaluation not found');
                }
                
                // Get athletes
                $stmt = $pdo->prepare("
                    SELECT * FROM session_evaluation_athletes 
                    WHERE session_evaluation_id = ?
                    ORDER BY last_name, first_name
                ");
                $stmt->execute([$evaluation_id]);
                $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $athletes = decryptUserRows($athletes);
                
                // Get categories and skills via junction table
                $stmt = $pdo->prepare("
                    SELECT 
                        c.id as category_id, c.name as category_name, c.description as category_description,
                        s.id as skill_id, s.name as skill_name, s.description as skill_description
                    FROM eval_categories c
                    LEFT JOIN eval_skill_categories esc ON c.id = esc.category_id
                    LEFT JOIN eval_skills s ON esc.skill_id = s.id
                    ORDER BY c.display_order ASC, esc.display_order ASC
                ");
                $stmt->execute();
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $categories = [];
                foreach ($rows as $row) {
                    $catId = $row['category_id'];
                    if (!isset($categories[$catId])) {
                        $categories[$catId] = [
                            'id' => $catId,
                            'name' => $row['category_name'],
                            'description' => $row['category_description'],
                            'skills' => []
                        ];
                    }
                    if ($row['skill_id']) {
                        $categories[$catId]['skills'][] = [
                            'id' => $row['skill_id'],
                            'name' => $row['skill_name'],
                            'description' => $row['skill_description']
                        ];
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'evaluation' => $evaluation,
                    'athletes' => $athletes,
                    'categories' => array_values($categories)
                ]);
                break;
                
            case 'get_athlete_scores':
                header('Content-Type: application/json');
                $evaluation_id = intval($_GET['evaluation_id'] ?? 0);
                $athlete_id = intval($_GET['athlete_id'] ?? 0);
                
                if ($evaluation_id <= 0 || $athlete_id <= 0) {
                    throw new Exception('Invalid evaluation or athlete ID');
                }
                
                $stmt = $pdo->prepare("
                    SELECT skill_id, rating, notes
                    FROM session_evaluation_scores
                    WHERE session_evaluation_id = ? AND athlete_id = ?
                ");
                $stmt->execute([$evaluation_id, $athlete_id]);
                $scores = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Index by skill_id
                $scores_indexed = [];
                foreach ($scores as $score) {
                    $scores_indexed[$score['skill_id']] = $score;
                }
                
                echo json_encode(['success' => true, 'scores' => $scores_indexed]);
                exit;
                
            case 'download_csv_template':
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="athlete_import_template.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['first_name', 'last_name', 'email', 'date_of_birth', 'notes']);
                fputcsv($output, ['John', 'Doe', 'john.doe@example.com', '2010-05-15', 'Sample notes']);
                fclose($output);
                exit;
                
            case 'get_existing_users':
                // Get all existing users that can be added to evaluations
                // All users default to being selectable as athletes
                $role_filter = $_GET['role'] ?? '';
                $evaluation_id = intval($_GET['evaluation_id'] ?? 0);
                
                $sql = "SELECT id, first_name, last_name, email, role, date_of_birth 
                        FROM users 
                        WHERE is_active = 1";
                $params = [];
                
                // Filter by role if specified
                if ($role_filter) {
                    $sql .= " AND role = ?";
                    $params[] = $role_filter;
                }
                // No default role filter - show all users so any can be evaluated as athletes
                
                $sql .= " ORDER BY last_name, first_name";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Decrypt user PII fields
                $users = decryptUserRows($users);
                
                // If evaluation_id provided, mark users already added
                if ($evaluation_id > 0) {
                    $stmt = $pdo->prepare("SELECT user_id FROM session_evaluation_athletes WHERE session_evaluation_id = ? AND user_id IS NOT NULL");
                    $stmt->execute([$evaluation_id]);
                    $added_user_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    foreach ($users as &$user) {
                        $user['already_added'] = in_array($user['id'], $added_user_ids);
                    }
                }
                
                echo json_encode(['success' => true, 'users' => $users]);
                break;
                
            case 'get_available_sessions':
                // Get sessions that don't have evaluations yet
                // Include both regular sessions and template-based sessions from products catalog
                $stmt = $pdo->prepare("
                    SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
                           COALESCE(l.name, 'TBD') as location_name,
                           'session' as source_type, NULL as template_id, NULL as date_id
                    FROM sessions s
                    LEFT JOIN locations l ON s.location_id = l.id
                    WHERE s.id NOT IN (SELECT session_id FROM session_evaluations WHERE session_id IS NOT NULL)
                    AND s.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    
                    UNION ALL
                    
                    SELECT CONCAT('template_', tst.id, '_', tsd.id) as id, 
                           tst.name as title, 
                           DATE(tsd.session_date) as session_date, 
                           TIME(tsd.session_date) as session_time, 
                           tst.duration_minutes,
                           COALESCE(l.name, 'TBD') as location_name,
                           'template' as source_type, tst.id as template_id, tsd.id as date_id
                    FROM training_session_templates tst
                    INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
                    LEFT JOIN locations l ON tst.location_id = l.id
                    WHERE tst.is_active = 1
                    AND tsd.session_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND tsd.session_id IS NULL
                    
                    ORDER BY session_date ASC
                ");
                $stmt->execute();
                $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'sessions' => $sessions]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
