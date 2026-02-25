<?php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

if (!isset($_SESSION['user_role'])|| !in_array($_SESSION['user_role'], ['admin', 'coach', 'health_coach', 'team_coach'])) {
    header("Location: dashboard.php"); exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$action = $_POST['action'] ?? '';
$coach_id = $_SESSION['user_id'];
$user_id = $_SESSION['user_id'] ?? 0;

// =========================================================
// ACTION: CREATE PRIVATE SESSION (from coach_calendar.php)
// =========================================================
if ($action == 'create_private_session' && $_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'mailer.php';
    
    // Template ID is the selected session product (Private Session or Semi-Private Session)
    $template_id = intval($_POST['template_id'] ?? 0);
    
    $location_id = intval($_POST['location_id'] ?? 0);
    $session_date = $_POST['session_date'] ?? '';
    $session_time = $_POST['session_time'] ?? '';
    $duration_minutes = intval($_POST['duration_minutes'] ?? 60);
    $practice_plan_id = !empty($_POST['practice_plan_id']) ? intval($_POST['practice_plan_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $athlete_ids = isset($_POST['athlete_ids']) ? array_map('intval', $_POST['athlete_ids']) : [];
    
    // Validate required fields
    if (!$template_id || !$location_id || !$session_date || !$session_time) {
        header("Location: dashboard.php?page=coach_calendar&error=missing_fields");
        exit();
    }
    
    try {
        // Start transaction for data consistency
        $pdo->beginTransaction();
        
        // Get base URL from settings
        $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        $base_url = $settings['base_url'] ?? '';
        if (empty($base_url)) {
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $base_url = $protocol . '://' . $_SERVER['HTTP_HOST'];
        }
        $base_url = rtrim($base_url, '/');
        
        // Get session template details — this determines private vs semi-private and hourly price
        $stmt = $pdo->prepare("SELECT * FROM training_session_templates WHERE id = ? AND is_active = 1");
        $stmt->execute([$template_id]);
        $templateData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$templateData) {
            header("Location: dashboard.php?page=coach_calendar&error=invalid_session_type");
            exit();
        }
        
        // Determine private vs semi-private from the template name
        $is_semi_private = (stripos($templateData['name'], 'semi') !== false) ? 1 : 0;
        $is_private = $is_semi_private ? 0 : 1;
        
        $session_type_id = $templateData['session_type_id'] ?? null;
        if (empty($practice_plan_id) && !empty($templateData['practice_plan_id'])) {
            $practice_plan_id = $templateData['practice_plan_id'];
        }
        
        // Get location name
        $stmt = $pdo->prepare("SELECT name, city FROM locations WHERE id = ?");
        $stmt->execute([$location_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get coach name
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$coach_id]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        $coach = decryptUserRow($coach);
        $coach_name = $coach['first_name'] . ' ' . $coach['last_name'];
        
        // Calculate price based on hourly rate × (duration / 60)
        $hourly_price = floatval($templateData['price'] ?? 0);
        $price = round($hourly_price * ($duration_minutes / 60), 2);
        
        // Build title
        $sessionName = $templateData['name'] ?? ($is_semi_private ? 'Semi-Private Session' : 'Private Session');
        $title = $sessionName . ' with ' . $coach_name;
        
        // Create the session record
        $stmt = $pdo->prepare("
            INSERT INTO sessions (
                session_type_id, coach_id, location_id, title, description, 
                session_date, session_time, duration_minutes, 
                practice_plan_id, is_private, is_semi_private, status, max_participants, price,
                arena, city, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, NOW())
        ");
        $maxCapacity = count($athlete_ids) > 0 ? count($athlete_ids) : ($is_semi_private ? 4 : 1);
        $stmt->execute([
            $session_type_id, $coach_id, $location_id, $title, $description,
            $session_date, $session_time, $duration_minutes,
            $practice_plan_id, $is_private, $is_semi_private, $maxCapacity, $price,
            $location['name'] ?? '', $location['city'] ?? ''
        ]);
        
        $session_id = $pdo->lastInsertId();
        
        // If a template was selected, add a date entry to training_session_dates linking back
        if ($template_id > 0) {
            $stmt = $pdo->prepare("
                INSERT INTO training_session_dates (template_id, session_id, session_date, max_participants, is_active)
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([
                $template_id, $session_id,
                $session_date . ' ' . $session_time,
                $maxCapacity
            ]);
        }
        
        // If athletes are assigned, create pending bookings and send email notifications
        if (!empty($athlete_ids)) {
            foreach ($athlete_ids as $athlete_id) {
                // Get athlete details
                $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                $stmt->execute([$athlete_id]);
                $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
                $athlete = decryptUserRow($athlete);
                
                if (!$athlete) continue;
                
                // Create a confirmed booking with pending payment
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (
                        user_id, session_id, status, payment_status, amount
                    ) VALUES (?, ?, 'confirmed', 'pending', ?)
                ");
                $stmt->execute([$athlete_id, $session_id, $price]);
                
                // Create in-app notification (critical - do this before email which can fail)
                $formattedDate = date('l, F j, Y', strtotime($session_date));
                $formattedTime = date('g:i A', strtotime($session_time));
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link_url, created_at)
                    VALUES (?, 'session_assigned', ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $athlete_id,
                    'Session Assigned - Payment Required',
                    "You have been assigned to '{$title}' on $formattedDate at $formattedTime. Please complete payment.",
                    "dashboard.php?page=sessions_upcoming"
                ]);
                
                // Send email notification (non-critical - logged if fails)
                try {
                    sendEmail($athlete['email'], 'notification', [
                        'name' => $athlete['first_name'],
                        'title' => 'Session Assigned - Payment Required',
                        'message' => "You have been assigned to a training session by $coach_name.\n\n" .
                                    "Session: " . $sessionName . "\n" .
                                    "Date: $formattedDate at $formattedTime\n" .
                                    "Duration: $duration_minutes minutes\n" .
                                    "Location: " . ($location['name'] ?? '') . "\n" .
                                    "Price: $" . number_format($price, 2) . "\n\n" .
                                    "Please log in to your dashboard to complete payment before the session.",
                        'link' => $base_url . '/dashboard.php?page=sessions_upcoming'
                    ]);
                } catch (Exception $e) {
                    ErrorLogger::error("Failed to send session assignment email to {$athlete['email']}: " . $e->getMessage());
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        Auditor::log($pdo, $user_id, 'create', 'sessions', $session_id, ['action' => 'private_session_created', 'title' => $title]);

        header("Location: dashboard.php?page=coach_calendar&status=session_created");
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ErrorLogger::error("Create private session error: " . $e->getMessage());
        header("Location: dashboard.php?page=coach_calendar&error=creation_failed");
        exit();
    }
}

// =========================================================
// LEGACY ACTION: CREATE SESSION (from create_session.php view)
// =========================================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && empty($action)) {
    $type      = $_POST['session_type'];
    $age_group = $_POST['age_group'];
    $title     = trim($_POST['title']);
    $desc      = trim($_POST['description']);
    $plan      = trim($_POST['session_plan']);
    $date      = $_POST['date'];
    $time      = $_POST['time'];
    $capacity  = $_POST['capacity'];
    $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
    
    // FETCH LOCATION DETAILS from ID
    $loc_id = $_POST['location_id'];
    $stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
    $stmt->execute([$loc_id]);
    $loc = $stmt->fetch();
    
    $arena   = $loc['name'];
    $city    = $loc['city'];
    $country = 'Canada'; // Default or add to locations table

    // Coaches
    $coaches = isset($_POST['coaches']) ? implode(", ", $_POST['coaches']) : "Staff";

    try {
        $sql = "INSERT INTO sessions 
                (session_type, age_group, title, description, session_plan, session_date, session_time, max_capacity, coaches, arena, city, country, enable_child_checkin) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $age_group, $title, $desc, $plan, $date, $time, $capacity, $coaches, $arena, $city, $country, $enable_child_checkin]);

        $legacy_session_id = $pdo->lastInsertId();
        Auditor::log($pdo, $user_id, 'create', 'sessions', $legacy_session_id, ['action' => 'session_created', 'title' => $title]);

        header("Location: dashboard.php?page=session_history&status=created");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>