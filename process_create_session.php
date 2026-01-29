<?php
session_start();
require 'db_config.php';
require 'security.php';

if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'coach', 'health_coach', 'team_coach'])) {
    header("Location: dashboard.php"); exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

$action = $_POST['action'] ?? '';
$coach_id = $_SESSION['user_id'];

// =========================================================
// ACTION: CREATE PRIVATE SESSION (from coach_calendar.php)
// =========================================================
if ($action == 'create_private_session' && $_SERVER["REQUEST_METHOD"] == "POST") {
    require_once 'mailer.php';
    
    $session_type_id = intval($_POST['session_type_id'] ?? 0);
    $location_id = intval($_POST['location_id'] ?? 0);
    $session_date = $_POST['session_date'] ?? '';
    $session_time = $_POST['session_time'] ?? '';
    $duration_minutes = intval($_POST['duration_minutes'] ?? 60);
    $practice_plan_id = !empty($_POST['practice_plan_id']) ? intval($_POST['practice_plan_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $is_private = isset($_POST['is_private']) ? 1 : 0;
    $athlete_ids = isset($_POST['athlete_ids']) ? array_map('intval', $_POST['athlete_ids']) : [];
    
    // Validate required fields
    if (!$session_type_id || !$location_id || !$session_date || !$session_time) {
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
        
        // Get session type details for title and price
        $stmt = $pdo->prepare("SELECT name, price FROM session_types WHERE id = ?");
        $stmt->execute([$session_type_id]);
        $sessionType = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get location name
        $stmt = $pdo->prepare("SELECT name, city FROM locations WHERE id = ?");
        $stmt->execute([$location_id]);
        $location = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get coach name
        $stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $stmt->execute([$coach_id]);
        $coach = $stmt->fetch(PDO::FETCH_ASSOC);
        $coach_name = $coach['first_name'] . ' ' . $coach['last_name'];
        
        // Create the session
        $title = ($sessionType['name'] ?? 'Private Session') . ' with ' . $coach_name;
        $price = $sessionType['price'] ?? 0;
        
        $stmt = $pdo->prepare("
            INSERT INTO sessions (
                session_type_id, coach_id, location_id, title, description, 
                session_date, start_time, duration_minutes, 
                practice_plan_id, is_private, status, max_capacity, price,
                arena, city, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, ?, ?, ?, NOW())
        ");
        $maxCapacity = count($athlete_ids) > 0 ? count($athlete_ids) : 1;
        $stmt->execute([
            $session_type_id, $coach_id, $location_id, $title, $description,
            $session_date, $session_time, $duration_minutes,
            $practice_plan_id, $is_private, $maxCapacity, $price,
            $location['name'] ?? '', $location['city'] ?? ''
        ]);
        
        $session_id = $pdo->lastInsertId();
        
        // If athletes are assigned, create pending bookings and send email notifications
        if (!empty($athlete_ids)) {
            foreach ($athlete_ids as $athlete_id) {
                // Get athlete details
                $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                $stmt->execute([$athlete_id]);
                $athlete = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$athlete) continue;
                
                // Create a pending (unpaid) booking
                $stmt = $pdo->prepare("
                    INSERT INTO bookings (
                        user_id, session_id, status, payment_status, amount_due, created_at
                    ) VALUES (?, ?, 'pending', 'pending', ?, NOW())
                ");
                $stmt->execute([$athlete_id, $session_id, $price]);
                
                // Create in-app notification (critical - do this before email which can fail)
                $formattedDate = date('l, F j, Y', strtotime($session_date));
                $formattedTime = date('g:i A', strtotime($session_time));
                
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (user_id, type, title, message, link, created_at)
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
                                    "Session: " . ($sessionType['name'] ?? 'Private Session') . "\n" .
                                    "Date: $formattedDate at $formattedTime\n" .
                                    "Location: " . ($location['name'] ?? '') . "\n" .
                                    "Price: $" . number_format($price, 2) . "\n\n" .
                                    "Please log in to your dashboard to complete payment before the session.",
                        'link' => $base_url . '/dashboard.php?page=sessions_upcoming'
                    ]);
                } catch (Exception $e) {
                    error_log("Failed to send session assignment email to {$athlete['email']}: " . $e->getMessage());
                }
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        header("Location: dashboard.php?page=coach_calendar&status=session_created");
        exit();
        
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Create private session error: " . $e->getMessage());
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
                (session_type, age_group, title, description, session_plan, session_date, session_time, max_capacity, coaches, arena, city, country) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$type, $age_group, $title, $desc, $plan, $date, $time, $capacity, $coaches, $arena, $city, $country]);

        header("Location: dashboard.php?page=session_history&status=created");
        exit();
    } catch (PDOException $e) {
        die("Error: " . $e->getMessage());
    }
}
?>