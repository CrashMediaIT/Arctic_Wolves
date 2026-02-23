<?php
// process_packages.php - Handle package CRUD operations
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check admin access
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    // Allow staff roles for specific registration management actions
    $staff_roles = ['admin', 'coach', 'coach_plus', 'team_coach', 'front_desk_staff'];
    $staff_actions = ['get_registrations', 'cancel_registration', 'get_session_registrations'];
    // Allow any logged-in user to cancel their own package registration
    $user_actions = ['user_cancel_package'];
    $current_action = $_GET['action'] ?? $_POST['action'] ?? '';
    
    if (in_array($_SESSION['user_role'] ?? '', $staff_roles) && in_array($current_action, $staff_actions)) {
        // Allow staff access for registration management
    } elseif (isset($_SESSION['user_id']) && in_array($current_action, $user_actions)) {
        // Allow any logged-in user for self-service cancellation (ownership verified later)
    } else {
        if ($isAjax) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Admin access required']);
            exit();
        }
        http_response_code(403);
        die('Access denied.');
    }
}

// Handle GET request for retrieving registered users for a package (AJAX - Staff)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_registrations') {
    header('Content-Type: application/json');
    $package_id = intval($_GET['package_id'] ?? 0);
    
    if ($package_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
        exit();
    }
    
    try {
        // Get registered users (paid)
        $reg_stmt = $pdo->prepare("
            SELECT up.id as user_package_id, up.user_id, up.amount_paid, up.purchase_date,
                   u.first_name, u.last_name, u.email
            FROM user_packages up
            JOIN users u ON up.user_id = u.id
            WHERE up.package_id = ? AND up.payment_status = 'paid'
            ORDER BY up.purchase_date DESC
        ");
        $reg_stmt->execute([$package_id]);
        $registered = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
        $registered = decryptUserRows($registered);
        
        $registered_result = array_map(function($u) {
            return [
                'user_package_id' => (int)$u['user_package_id'],
                'user_id' => (int)$u['user_id'],
                'name' => ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''),
                'email' => $u['email'] ?? '',
                'amount_paid' => $u['amount_paid'],
                'purchase_date' => $u['purchase_date']
            ];
        }, $registered);
        
        // Get waitlisted users (from bookings on linked sessions)
        $wait_stmt = $pdo->prepare("
            SELECT DISTINCT u.id as user_id, u.first_name, u.last_name, u.email
            FROM bookings b
            JOIN package_sessions ps ON b.session_id = ps.session_id
            JOIN users u ON b.user_id = u.id
            WHERE ps.package_id = ? AND b.status = 'waitlisted'
        ");
        $wait_stmt->execute([$package_id]);
        $waitlisted = $wait_stmt->fetchAll(PDO::FETCH_ASSOC);
        $waitlisted = decryptUserRows($waitlisted);
        
        $waitlisted_result = array_map(function($u) {
            return [
                'user_id' => (int)$u['user_id'],
                'name' => ($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''),
                'email' => $u['email'] ?? ''
            ];
        }, $waitlisted);
        
        echo json_encode(['success' => true, 'registered' => $registered_result, 'waitlisted' => $waitlisted_result]);
    } catch (PDOException $e) {
        error_log("Registration fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load registrations']);
    }
    exit();
}

// Handle GET request for retrieving registered users for a session template (AJAX - Staff)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_session_registrations') {
    header('Content-Type: application/json');
    $session_template_id = intval($_GET['session_template_id'] ?? 0);
    
    if ($session_template_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid session template ID']);
        exit();
    }
    
    try {
        // Get users booked for sessions linked to this template (via training_session_dates)
        $reg_stmt = $pdo->prepare("
            SELECT DISTINCT u.first_name, u.last_name, u.email,
                   DATE_FORMAT(tsd.session_date, '%b %e, %Y') as session_date
            FROM bookings b
            JOIN sessions s ON b.session_id = s.id
            JOIN training_session_dates tsd ON DATE(tsd.session_date) = DATE(s.session_date)
            JOIN training_session_templates tst ON tsd.template_id = tst.id
            JOIN users u ON b.user_id = u.id
            WHERE tst.id = ?
              AND b.status IN ('confirmed', 'waitlisted')
              AND b.payment_status IN ('pending', 'paid')
            ORDER BY tsd.session_date, u.last_name
        ");
        $reg_stmt->execute([$session_template_id]);
        $registered = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
        $registered = decryptUserRows($registered);
        
        // Also check direct bookings on sessions matching this template
        $direct_stmt = $pdo->prepare("
            SELECT DISTINCT u.first_name, u.last_name, u.email,
                   DATE_FORMAT(s.session_date, '%b %e, %Y') as session_date
            FROM bookings b
            JOIN sessions s ON b.session_id = s.id
            JOIN users u ON b.user_id = u.id
            WHERE s.session_type_id = (SELECT session_type_id FROM training_session_templates WHERE id = ?)
              AND b.status IN ('confirmed', 'waitlisted')
              AND b.payment_status IN ('pending', 'paid')
              AND s.session_date >= CURDATE()
            ORDER BY s.session_date, u.last_name
        ");
        $direct_stmt->execute([$session_template_id]);
        $direct_registered = $direct_stmt->fetchAll(PDO::FETCH_ASSOC);
        $direct_registered = decryptUserRows($direct_registered);
        
        // Merge and deduplicate
        $all_users = array_merge($registered, $direct_registered);
        $seen = [];
        $result = [];
        foreach ($all_users as $u) {
            $key = ($u['email'] ?? '') . '_' . ($u['session_date'] ?? '');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $result[] = [
                    'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
                    'email' => $u['email'] ?? '',
                    'session_date' => $u['session_date'] ?? ''
                ];
            }
        }
        
        echo json_encode(['success' => true, 'users' => $result]);
    } catch (PDOException $e) {
        error_log("Session registration fetch error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load registrations']);
    }
    exit();
}

// Handle POST for cancelling a registration with auto-refund (Staff)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel_registration') {
    header('Content-Type: application/json');
    checkCsrfToken();
    
    $user_package_id = intval($_POST['user_package_id'] ?? 0);
    
    if ($user_package_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid registration ID']);
        exit();
    }
    
    try {
        // Get the user_package details
        $up_stmt = $pdo->prepare("SELECT * FROM user_packages WHERE id = ? AND payment_status = 'paid'");
        $up_stmt->execute([$user_package_id]);
        $user_package = $up_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_package) {
            echo json_encode(['success' => false, 'message' => 'Registration not found or already cancelled']);
            exit();
        }
        
        $pdo->beginTransaction();
        
        // Update user_packages to refunded
        $pdo->prepare("UPDATE user_packages SET payment_status = 'refunded' WHERE id = ?")->execute([$user_package_id]);
        
        // Cancel all related bookings
        $pdo->prepare("
            UPDATE bookings SET status = 'cancelled', payment_status = 'refunded'
            WHERE user_id = ? AND stripe_session_id = ? AND status = 'confirmed'
        ")->execute([$user_package['user_id'], $user_package['stripe_session_id']]);
        
        // Create refund record
        $refund_amount = $user_package['amount_paid'] ?? 0;
        $pdo->prepare("
            INSERT INTO credits_refunds (user_id, transaction_type, amount, reason, status, processed_by, processed_at)
            VALUES (?, 'refund', ?, 'Staff cancelled registration', 'completed', ?, NOW())
        ")->execute([$user_package['user_id'], $refund_amount, $_SESSION['user_id']]);
        
        // Process Stripe refund if applicable
        if (!empty($user_package['stripe_session_id']) && $refund_amount > 0) {
            try {
                if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
                elseif (file_exists(__DIR__ . '/stripe-php/init.php')) { require_once __DIR__ . '/stripe-php/init.php'; }
                
                $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                $stripe_secret = $settings['stripe_secret_key'] ?? '';
                if (function_exists('decryptCredential')) { $stripe_secret = decryptCredential($stripe_secret); }
                \Stripe\Stripe::setApiKey($stripe_secret);
                
                $checkout = \Stripe\Checkout\Session::retrieve($user_package['stripe_session_id']);
                if (!empty($checkout->payment_intent)) {
                    \Stripe\Refund::create([
                        'payment_intent' => $checkout->payment_intent,
                        'amount' => intval($refund_amount * 100)
                    ]);
                }
            } catch (Exception $stripeErr) {
                error_log("Stripe refund error for user_package $user_package_id: " . $stripeErr->getMessage());
            }
        }
        
        $pdo->commit();
        
        Auditor::log($pdo, $_SESSION['user_id'], 'update', 'user_packages', $user_package_id, [
            'action' => 'staff_cancel_registration',
            'user_id' => $user_package['user_id'],
            'refund_amount' => $refund_amount
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Registration cancelled and refund initiated']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("Cancel registration error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to cancel registration']);
    }
    exit();
}

// Handle POST for user self-service cancellation with refund policy enforcement
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'user_cancel_package') {
    header('Content-Type: application/json');
    checkCsrfToken();
    
    $user_package_id = intval($_POST['user_package_id'] ?? 0);
    $cancel_user_id = $_SESSION['user_id'];
    
    if ($user_package_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid registration ID']);
        exit();
    }
    
    try {
        // Get the user_package details with package info
        $up_stmt = $pdo->prepare("
            SELECT up.*, p.package_type, p.name as package_name, p.camp_start_date, p.camp_end_date, p.price
            FROM user_packages up
            JOIN packages p ON up.package_id = p.id
            WHERE up.id = ? AND up.payment_status = 'paid'
        ");
        $up_stmt->execute([$user_package_id]);
        $user_package = $up_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_package) {
            echo json_encode(['success' => false, 'message' => 'Registration not found or already cancelled']);
            exit();
        }
        
        // Verify ownership (user cancelling their own or their child's registration)
        $is_own = ($user_package['user_id'] == $cancel_user_id);
        $is_parent = false;
        if (!$is_own) {
            $parent_check = $pdo->prepare("SELECT 1 FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
            $parent_check->execute([$cancel_user_id, $user_package['user_id']]);
            $is_parent = (bool)$parent_check->fetch();
        }
        
        if (!$is_own && !$is_parent) {
            echo json_encode(['success' => false, 'message' => 'You do not have permission to cancel this registration']);
            exit();
        }
        
        $now = new DateTime();
        $refund_amount = 0;
        $policy_message = '';
        
        if ($user_package['package_type'] === 'camp') {
            // CAMP CANCELLATION POLICY: Must cancel 14 days before camp starts
            $camp_start = new DateTime($user_package['camp_start_date']);
            $diff = $now->diff($camp_start);
            $days_until_camp = $diff->days * ($diff->invert ? -1 : 1);
            
            if ($days_until_camp < 14) {
                $msg = ($days_until_camp < 0) 
                    ? 'This camp has already started. Cancellations are no longer available.'
                    : 'Camp cancellations must be made at least 14 days before the camp start date (' . $camp_start->format('M j, Y') . '). You are ' . $days_until_camp . ' day(s) from the start.';
                echo json_encode(['success' => false, 'message' => $msg]);
                exit();
            }
            $refund_amount = $user_package['amount_paid'] ?? 0;
            $policy_message = 'Camp registration cancelled. Full refund will be processed.';
            
        } elseif ($user_package['package_type'] === 'multi_week') {
            // PROGRAM CANCELLATION POLICY: Refund remaining sessions; no refund for sessions <48hrs away
            $sessions_stmt = $pdo->prepare("
                SELECT mpd.session_date FROM multiweek_program_dates mpd
                WHERE mpd.package_id = ?
                ORDER BY mpd.session_date
            ");
            $sessions_stmt->execute([$user_package['package_id']]);
            $program_dates = $sessions_stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $total_sessions = count($program_dates);
            $refundable_sessions = 0;
            $cutoff = (clone $now)->modify('+48 hours');
            
            foreach ($program_dates as $session_date) {
                $sd = new DateTime($session_date);
                if ($sd > $cutoff) {
                    $refundable_sessions++;
                }
            }
            
            if ($total_sessions > 0 && $refundable_sessions > 0) {
                $per_session = ($user_package['amount_paid'] ?? 0) / $total_sessions;
                $refund_amount = round($per_session * $refundable_sessions, 2);
            }
            
            $non_refundable = $total_sessions - $refundable_sessions;
            $policy_message = "Program cancelled. Refund for $refundable_sessions of $total_sessions sessions ($" . number_format($refund_amount, 2) . ").";
            if ($non_refundable > 0) {
                $policy_message .= " $non_refundable session(s) within 48 hours are not eligible for refund.";
            }
        } else {
            // Default: full refund for regular packages
            $refund_amount = $user_package['amount_paid'] ?? 0;
            $policy_message = 'Registration cancelled. Refund will be processed.';
        }
        
        $pdo->beginTransaction();
        
        // Update user_packages to refunded
        $pdo->prepare("UPDATE user_packages SET payment_status = 'refunded' WHERE id = ?")->execute([$user_package_id]);
        
        // Cancel all related bookings
        $pdo->prepare("
            UPDATE bookings SET status = 'cancelled', payment_status = 'refunded'
            WHERE user_id = ? AND stripe_session_id = ? AND status = 'confirmed'
        ")->execute([$user_package['user_id'], $user_package['stripe_session_id']]);
        
        // Create refund record
        if ($refund_amount > 0) {
            $pdo->prepare("
                INSERT INTO credits_refunds (user_id, transaction_type, amount, reason, status, processed_by, processed_at)
                VALUES (?, 'refund', ?, ?, 'completed', ?, NOW())
            ")->execute([$user_package['user_id'], $refund_amount, 'Self-service cancellation: ' . $user_package['package_name'], $cancel_user_id]);
            
            // Process Stripe refund if applicable
            if (!empty($user_package['stripe_session_id'])) {
                try {
                    if (file_exists(__DIR__ . '/vendor/autoload.php')) { require_once __DIR__ . '/vendor/autoload.php'; }
                    elseif (file_exists(__DIR__ . '/stripe-php/init.php')) { require_once __DIR__ . '/stripe-php/init.php'; }
                    
                    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
                    $stripe_secret = $settings['stripe_secret_key'] ?? '';
                    if (function_exists('decryptCredential')) { $stripe_secret = decryptCredential($stripe_secret); }
                    \Stripe\Stripe::setApiKey($stripe_secret);
                    
                    $checkout = \Stripe\Checkout\Session::retrieve($user_package['stripe_session_id']);
                    if (!empty($checkout->payment_intent)) {
                        \Stripe\Refund::create([
                            'payment_intent' => $checkout->payment_intent,
                            'amount' => intval($refund_amount * 100)
                        ]);
                    }
                } catch (Exception $stripeErr) {
                    error_log("Stripe refund error for user_package $user_package_id: " . $stripeErr->getMessage());
                }
            }
        }
        
        $pdo->commit();
        
        Auditor::log($pdo, $cancel_user_id, 'update', 'user_packages', $user_package_id, [
            'action' => 'user_cancel_package',
            'package_type' => $user_package['package_type'],
            'refund_amount' => $refund_amount
        ]);
        
        echo json_encode(['success' => true, 'message' => $policy_message]);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log("User cancel package error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to process cancellation']);
    }
    exit();
}

// Handle GET request for retrieving package sessions (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_sessions') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT session_id FROM package_sessions WHERE package_id = ?");
    $stmt->execute([$package_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode($sessions);
    exit();
}

// Handle GET request for retrieving camp schedules (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_camp_schedules') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
    $stmt->execute([$package_id]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($schedules);
    exit();
}

// Handle GET request for retrieving camp add-ons (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_camp_addons') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM camp_add_ons WHERE package_id = ? ORDER BY display_order");
    $stmt->execute([$package_id]);
    $addons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($addons);
    exit();
}

// Handle GET request for retrieving multi-week program dates (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_program_dates') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT * FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
    $stmt->execute([$package_id]);
    $dates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($dates);
    exit();
}

// Handle GET request for retrieving package coaches (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'get_package_coaches') {
    $package_id = intval($_GET['package_id'] ?? 0);
    
    $stmt = $pdo->prepare("SELECT coach_id FROM package_coaches WHERE package_id = ?");
    $stmt->execute([$package_id]);
    $coach_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    header('Content-Type: application/json');
    echo json_encode($coach_ids);
    exit();
}

// Validate CSRF token for POST requests
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

try {
    switch ($action) {
        case 'create':
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $credits = intval($_POST['credits'] ?? intval($_POST['session_count'] ?? 0));
            $valid_days = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : (!empty($_POST['validity_days']) ? intval($_POST['validity_days']) : null);
            $age_group = trim($_POST['age_group'] ?? '');
            $skill_level = trim($_POST['skill_level'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $package_type = trim($_POST['package_type'] ?? 'credits');
            $store_credit = floatval($_POST['store_credit'] ?? 0);
            $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
            
            // Camp-specific fields
            $camp_start_date = !empty($_POST['camp_start_date']) ? $_POST['camp_start_date'] : null;
            $camp_end_date = !empty($_POST['camp_end_date']) ? $_POST['camp_end_date'] : null;
            $daily_start_time = !empty($_POST['daily_start_time']) ? $_POST['daily_start_time'] : null;
            $daily_end_time = !empty($_POST['daily_end_time']) ? $_POST['daily_end_time'] : null;
            $age_group_id = !empty($_POST['age_group_id']) ? intval($_POST['age_group_id']) : null;
            $skill_level_id = !empty($_POST['skill_level_id']) ? intval($_POST['skill_level_id']) : null;
            $allow_individual_sessions = isset($_POST['allow_individual_sessions']) ? 1 : 0;
            
            if (empty($name) || $price < 0) {
                throw new Exception('Invalid package data: name is required and price must be positive');
            }
            
            // Validate camp dates - allow calendar-selected dates (program_dates) as alternative
            if ($package_type === 'camp' && (!$camp_start_date || !$camp_end_date) && empty($_POST['program_dates'])) {
                throw new Exception('Camp packages require start and end dates');
            }
            
            $pdo->beginTransaction();
            
            // Insert package with all fields
            $stmt = $pdo->prepare("
                INSERT INTO packages (name, description, price, credits, valid_days, 
                                     age_group, skill_level, is_active, package_type, store_credit, enable_child_checkin,
                                     camp_start_date, camp_end_date, daily_start_time, daily_end_time,
                                     age_group_id, skill_level_id, allow_individual_sessions)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $description, $price, $credits, $valid_days,
                $age_group ?: null, $skill_level ?: null, $is_active, $package_type, $store_credit, $enable_child_checkin,
                $camp_start_date, $camp_end_date, $daily_start_time, $daily_end_time,
                $age_group_id, $skill_level_id, $allow_individual_sessions
            ]);
            
            $package_id = $pdo->lastInsertId();
            
            // Save package-level coaches
            if (!empty($_POST['coach_ids']) && is_array($_POST['coach_ids'])) {
                $coach_stmt = $pdo->prepare("INSERT INTO package_coaches (package_id, coach_id) VALUES (?, ?)");
                foreach ($_POST['coach_ids'] as $cid) {
                    $cid = intval($cid);
                    if ($cid > 0) {
                        $coach_stmt->execute([$package_id, $cid]);
                    }
                }
            }
            
            // Save camp daily schedules if provided (flat array format from admin_packages)
            if ($package_type === 'camp' && !empty($_POST['schedule_dates'])) {
                $sched_stmt = $pdo->prepare("
                    INSERT INTO camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location, coach_ids)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                foreach ($_POST['schedule_dates'] as $i => $date) {
                    if (empty($date)) continue;
                    $s_start = $_POST['schedule_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                    $s_end = $_POST['schedule_end_times'][$i] ?? ($daily_end_time ?: '17:00');
                    $s_title = $_POST['schedule_titles'][$i] ?? '';
                    $s_desc = $_POST['schedule_descriptions'][$i] ?? '';
                    $s_location = $_POST['schedule_locations'][$i] ?? '';
                    $s_coaches = $_POST['schedule_coach_ids'][$i] ?? '';
                    $sched_stmt->execute([$package_id, $date, $s_start, $s_end, $s_title ?: null, $s_desc ?: null, $s_location ?: null, $s_coaches ?: null]);
                }
            }
            
            // Save camp daily schedules from calendar picker (nested array format)
            if ($package_type === 'camp' && !empty($_POST['program_dates'])) {
                $sched_stmt = $pdo->prepare("
                    INSERT INTO camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location, coach_ids)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $all_camp_dates = [];
                foreach ($_POST['program_dates'] as $i => $entry) {
                    if (is_array($entry)) {
                        $date = $entry['date'] ?? '';
                        if (empty($date)) continue;
                        $s_start = $entry['start_time'] ?? ($daily_start_time ?: '09:00');
                        $s_end = $entry['end_time'] ?? ($daily_end_time ?: '17:00');
                        $s_location = $entry['location_id'] ?? '';
                        $s_coaches = !empty($entry['coach_ids']) ? (is_array($entry['coach_ids']) ? implode(',', array_map('intval', $entry['coach_ids'])) : $entry['coach_ids']) : null;
                        $sched_stmt->execute([$package_id, $date, $s_start, $s_end, null, null, $s_location ?: null, $s_coaches]);
                        $all_camp_dates[] = $date;
                    }
                }
                // Auto-derive camp_start_date and camp_end_date from selected dates
                if (!empty($all_camp_dates)) {
                    $pdo->prepare("UPDATE packages SET camp_start_date = ?, camp_end_date = ? WHERE id = ?")
                        ->execute([min($all_camp_dates), max($all_camp_dates), $package_id]);
                }
            }
            
            // Save camp add-ons if provided
            if (($package_type === 'camp' || $package_type === 'multi_week') && !empty($_POST['addon_names'])) {
                $addon_stmt = $pdo->prepare("
                    INSERT INTO camp_add_ons (package_id, name, description, price, is_default, display_order)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($_POST['addon_names'] as $i => $addon_name) {
                    if (empty($addon_name)) continue;
                    $addon_desc = $_POST['addon_descriptions'][$i] ?? '';
                    $addon_price = floatval($_POST['addon_prices'][$i] ?? 0);
                    $addon_default = isset($_POST['addon_defaults'][$i]) ? 1 : 0;
                    $addon_stmt->execute([$package_id, $addon_name, $addon_desc ?: null, $addon_price, $addon_default, $i]);
                }
            }
            
            // For multi-week programs, save program dates and auto-create sessions
            if ($package_type === 'multi_week' && !empty($_POST['program_dates'])) {
                $prog_stmt = $pdo->prepare("
                    INSERT INTO multiweek_program_dates (package_id, session_date, start_time, end_time, title, individual_price, auto_session_id, location, coach_ids)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $session_stmt = $pdo->prepare("
                    INSERT INTO sessions (title, description, session_date, session_time, duration_minutes, price, age_group, skill_level, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
                ");
                
                foreach ($_POST['program_dates'] as $i => $pdate) {
                    // Handle both nested array format (from ArcticCalendar) and flat format (from admin_packages)
                    if (is_array($pdate)) {
                        $date_val = $pdate['date'] ?? '';
                        if (empty($date_val)) continue;
                        $p_start = $pdate['start_time'] ?? ($daily_start_time ?: '09:00');
                        $p_end = $pdate['end_time'] ?? ($daily_end_time ?: '10:00');
                        $p_title = $pdate['title'] ?? '';
                        $p_ind_price = !empty($pdate['individual_price']) ? floatval($pdate['individual_price']) : null;
                        $p_location = $pdate['location_id'] ?? '';
                        $p_coaches = !empty($pdate['coach_ids']) ? (is_array($pdate['coach_ids']) ? implode(',', array_map('intval', $pdate['coach_ids'])) : $pdate['coach_ids']) : null;
                    } else {
                        $date_val = $pdate;
                        if (empty($date_val)) continue;
                        $p_start = $_POST['program_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                        $p_end = $_POST['program_end_times'][$i] ?? ($daily_end_time ?: '10:00');
                        $p_title = $_POST['program_titles'][$i] ?? '';
                        $p_ind_price = !empty($_POST['program_individual_prices'][$i]) ? floatval($_POST['program_individual_prices'][$i]) : null;
                        $p_location = $_POST['program_locations'][$i] ?? '';
                        $p_coaches = $_POST['program_coach_ids'][$i] ?? null;
                    }
                    
                    $auto_session_id = null;
                    // Auto-create individual sessions if individual purchase is allowed
                    if ($allow_individual_sessions && $p_ind_price !== null) {
                        $session_title = !empty($p_title) ? $p_title : ($name . ' - ' . date('M j, Y', strtotime($date_val)));
                        $start_dt = new DateTime($date_val . ' ' . $p_start);
                        $end_dt = new DateTime($date_val . ' ' . $p_end);
                        $duration = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;
                        $session_stmt->execute([
                            $session_title, $description, $date_val, $p_start,
                            max(1, intval($duration)), $p_ind_price,
                            $age_group ?: null, $skill_level ?: null
                        ]);
                        $auto_session_id = $pdo->lastInsertId();
                        
                        // Assign coaches to auto-created session
                        if (!empty($p_coaches)) {
                            $sc_stmt = $pdo->prepare("INSERT INTO session_coaches (session_id, coach_id) VALUES (?, ?)");
                            foreach (explode(',', $p_coaches) as $sc_id) {
                                $sc_id = intval(trim($sc_id));
                                if ($sc_id > 0) {
                                    $sc_stmt->execute([$auto_session_id, $sc_id]);
                                }
                            }
                        }
                    }
                    
                    $prog_stmt->execute([$package_id, $date_val, $p_start, $p_end, $p_title ?: null, $p_ind_price, $auto_session_id, $p_location ?: null, $p_coaches]);
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'CREATE', 'packages', $package_id, ['action' => 'Created package', 'name' => $name]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package created successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'update':
            $package_id = intval($_POST['package_id']);
            $name = trim($_POST['name']);
            $description = trim($_POST['description'] ?? '');
            $price = floatval($_POST['price']);
            $credits = intval($_POST['credits'] ?? 0);
            $valid_days = !empty($_POST['valid_days']) ? intval($_POST['valid_days']) : (!empty($_POST['validity_days']) ? intval($_POST['validity_days']) : null);
            $age_group = trim($_POST['age_group'] ?? '');
            $skill_level = trim($_POST['skill_level'] ?? '');
            $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
            $package_type = trim($_POST['package_type'] ?? 'credits');
            $store_credit = floatval($_POST['store_credit'] ?? 0);
            $enable_child_checkin = isset($_POST['enable_child_checkin']) ? 1 : 0;
            
            // Camp-specific fields
            $camp_start_date = !empty($_POST['camp_start_date']) ? $_POST['camp_start_date'] : null;
            $camp_end_date = !empty($_POST['camp_end_date']) ? $_POST['camp_end_date'] : null;
            $daily_start_time = !empty($_POST['daily_start_time']) ? $_POST['daily_start_time'] : null;
            $daily_end_time = !empty($_POST['daily_end_time']) ? $_POST['daily_end_time'] : null;
            $age_group_id = !empty($_POST['age_group_id']) ? intval($_POST['age_group_id']) : null;
            $skill_level_id = !empty($_POST['skill_level_id']) ? intval($_POST['skill_level_id']) : null;
            $allow_individual_sessions = isset($_POST['allow_individual_sessions']) ? 1 : 0;
            
            if (empty($name) || $price < 0 || $package_id <= 0) {
                throw new Exception('Invalid package data');
            }
            
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("
                UPDATE packages 
                SET name = ?, description = ?, price = ?, credits = ?, 
                    valid_days = ?, age_group = ?, skill_level = ?, is_active = ?, package_type = ?, store_credit = ?, enable_child_checkin = ?,
                    camp_start_date = ?, camp_end_date = ?, daily_start_time = ?, daily_end_time = ?,
                    age_group_id = ?, skill_level_id = ?, allow_individual_sessions = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $description, $price, $credits, $valid_days,
                $age_group ?: null, $skill_level ?: null, $is_active, $package_type, $store_credit, $enable_child_checkin,
                $camp_start_date, $camp_end_date, $daily_start_time, $daily_end_time,
                $age_group_id, $skill_level_id, $allow_individual_sessions, $package_id
            ]);
            
            // Update package-level coaches
            $pdo->prepare("DELETE FROM package_coaches WHERE package_id = ?")->execute([$package_id]);
            if (!empty($_POST['coach_ids']) && is_array($_POST['coach_ids'])) {
                $coach_stmt = $pdo->prepare("INSERT INTO package_coaches (package_id, coach_id) VALUES (?, ?)");
                foreach ($_POST['coach_ids'] as $cid) {
                    $cid = intval($cid);
                    if ($cid > 0) {
                        $coach_stmt->execute([$package_id, $cid]);
                    }
                }
            }
            
            // Update camp daily schedules
            if ($package_type === 'camp') {
                // Remove old schedules and re-insert
                $pdo->prepare("DELETE FROM camp_daily_schedules WHERE package_id = ?")->execute([$package_id]);
                
                if (!empty($_POST['schedule_dates'])) {
                    $sched_stmt = $pdo->prepare("
                        INSERT INTO camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location, coach_ids)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($_POST['schedule_dates'] as $i => $date) {
                        if (empty($date)) continue;
                        $s_start = $_POST['schedule_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                        $s_end = $_POST['schedule_end_times'][$i] ?? ($daily_end_time ?: '17:00');
                        $s_title = $_POST['schedule_titles'][$i] ?? '';
                        $s_desc = $_POST['schedule_descriptions'][$i] ?? '';
                        $s_location = $_POST['schedule_locations'][$i] ?? '';
                        $s_coaches = $_POST['schedule_coach_ids'][$i] ?? '';
                        $sched_stmt->execute([$package_id, $date, $s_start, $s_end, $s_title ?: null, $s_desc ?: null, $s_location ?: null, $s_coaches ?: null]);
                    }
                }
                
                // Handle calendar picker dates (nested array format)
                if (!empty($_POST['program_dates'])) {
                    $sched_stmt = $pdo->prepare("
                        INSERT INTO camp_daily_schedules (package_id, schedule_date, start_time, end_time, title, description, location, coach_ids)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $all_camp_dates = [];
                    foreach ($_POST['program_dates'] as $i => $entry) {
                        if (is_array($entry)) {
                            $date = $entry['date'] ?? '';
                            if (empty($date)) continue;
                            $s_start = $entry['start_time'] ?? ($daily_start_time ?: '09:00');
                            $s_end = $entry['end_time'] ?? ($daily_end_time ?: '17:00');
                            $s_location = $entry['location_id'] ?? '';
                            $s_coaches = !empty($entry['coach_ids']) ? (is_array($entry['coach_ids']) ? implode(',', array_map('intval', $entry['coach_ids'])) : $entry['coach_ids']) : null;
                            $sched_stmt->execute([$package_id, $date, $s_start, $s_end, null, null, $s_location ?: null, $s_coaches]);
                            $all_camp_dates[] = $date;
                        }
                    }
                    // Auto-derive camp_start_date and camp_end_date from selected dates
                    if (!empty($all_camp_dates)) {
                        $pdo->prepare("UPDATE packages SET camp_start_date = ?, camp_end_date = ? WHERE id = ?")
                            ->execute([min($all_camp_dates), max($all_camp_dates), $package_id]);
                    }
                }
            }
            
            // Update add-ons (for camp and multi-week)
            if ($package_type === 'camp' || $package_type === 'multi_week') {
                $pdo->prepare("DELETE FROM camp_add_ons WHERE package_id = ?")->execute([$package_id]);
                
                if (!empty($_POST['addon_names'])) {
                    $addon_stmt = $pdo->prepare("
                        INSERT INTO camp_add_ons (package_id, name, description, price, is_default, display_order)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    foreach ($_POST['addon_names'] as $i => $addon_name) {
                        if (empty($addon_name)) continue;
                        $addon_desc = $_POST['addon_descriptions'][$i] ?? '';
                        $addon_price = floatval($_POST['addon_prices'][$i] ?? 0);
                        $addon_default = isset($_POST['addon_defaults'][$i]) ? 1 : 0;
                        $addon_stmt->execute([$package_id, $addon_name, $addon_desc ?: null, $addon_price, $addon_default, $i]);
                    }
                }
            }
            
            // Update multi-week program dates
            if ($package_type === 'multi_week') {
                // Delete auto-created sessions that are no longer needed
                $old_sessions = $pdo->prepare("SELECT auto_session_id FROM multiweek_program_dates WHERE package_id = ? AND auto_session_id IS NOT NULL");
                $old_sessions->execute([$package_id]);
                $old_session_ids = $old_sessions->fetchAll(PDO::FETCH_COLUMN);
                
                $pdo->prepare("DELETE FROM multiweek_program_dates WHERE package_id = ?")->execute([$package_id]);
                
                // Clean up auto-created sessions (only if no bookings exist)
                if (!empty($old_session_ids)) {
                    $placeholders = str_repeat('?,', count($old_session_ids) - 1) . '?';
                    // Only delete sessions that have no bookings
                    $pdo->prepare("DELETE FROM sessions WHERE id IN ($placeholders) AND id NOT IN (SELECT session_id FROM session_bookings WHERE session_id IN ($placeholders))")->execute(array_merge($old_session_ids, $old_session_ids));
                }
                
                if (!empty($_POST['program_dates'])) {
                    $prog_stmt = $pdo->prepare("
                        INSERT INTO multiweek_program_dates (package_id, session_date, start_time, end_time, title, individual_price, auto_session_id, location, coach_ids)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $session_stmt = $pdo->prepare("
                        INSERT INTO sessions (title, description, session_date, session_time, duration_minutes, price, age_group, skill_level, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'scheduled')
                    ");
                    
                    foreach ($_POST['program_dates'] as $i => $pdate) {
                        // Handle both nested array format (from ArcticCalendar) and flat format (from admin_packages)
                        if (is_array($pdate)) {
                            $date_val = $pdate['date'] ?? '';
                            if (empty($date_val)) continue;
                            $p_start = $pdate['start_time'] ?? ($daily_start_time ?: '09:00');
                            $p_end = $pdate['end_time'] ?? ($daily_end_time ?: '10:00');
                            $p_title = $pdate['title'] ?? '';
                            $p_ind_price = !empty($pdate['individual_price']) ? floatval($pdate['individual_price']) : null;
                            $p_location = $pdate['location_id'] ?? '';
                            $p_coaches = !empty($pdate['coach_ids']) ? (is_array($pdate['coach_ids']) ? implode(',', array_map('intval', $pdate['coach_ids'])) : $pdate['coach_ids']) : null;
                        } else {
                            $date_val = $pdate;
                            if (empty($date_val)) continue;
                            $p_start = $_POST['program_start_times'][$i] ?? ($daily_start_time ?: '09:00');
                            $p_end = $_POST['program_end_times'][$i] ?? ($daily_end_time ?: '10:00');
                            $p_title = $_POST['program_titles'][$i] ?? '';
                            $p_ind_price = !empty($_POST['program_individual_prices'][$i]) ? floatval($_POST['program_individual_prices'][$i]) : null;
                            $p_location = $_POST['program_locations'][$i] ?? '';
                            $p_coaches = $_POST['program_coach_ids'][$i] ?? null;
                        }
                        
                        $auto_session_id = null;
                        if ($allow_individual_sessions && $p_ind_price !== null) {
                            $session_title = !empty($p_title) ? $p_title : ($name . ' - ' . date('M j, Y', strtotime($date_val)));
                            $start_dt = new DateTime($date_val . ' ' . $p_start);
                            $end_dt = new DateTime($date_val . ' ' . $p_end);
                            $duration = ($end_dt->getTimestamp() - $start_dt->getTimestamp()) / 60;
                            $session_stmt->execute([
                                $session_title, $description, $date_val, $p_start,
                                max(1, intval($duration)), $p_ind_price,
                                $age_group ?: null, $skill_level ?: null
                            ]);
                            $auto_session_id = $pdo->lastInsertId();
                            
                            // Assign coaches to auto-created session
                            if (!empty($p_coaches)) {
                                $sc_stmt = $pdo->prepare("INSERT INTO session_coaches (session_id, coach_id) VALUES (?, ?)");
                                foreach (explode(',', $p_coaches) as $sc_id) {
                                    $sc_id = intval(trim($sc_id));
                                    if ($sc_id > 0) {
                                        $sc_stmt->execute([$auto_session_id, $sc_id]);
                                    }
                                }
                            }
                        }
                        
                        $prog_stmt->execute([$package_id, $date_val, $p_start, $p_end, $p_title ?: null, $p_ind_price, $auto_session_id, $p_location ?: null, $p_coaches]);
                    }
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'UPDATE', 'packages', $package_id, ['action' => 'Updated package', 'name' => $name]);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'delete':
            $package_id = intval($_POST['package_id']);
            
            // Check if package has been purchased (check both tables for safety)
            $check = $pdo->prepare("SELECT COUNT(*) FROM user_packages WHERE package_id = ?");
            $check->execute([$package_id]);
            
            if ($check->fetchColumn() > 0) {
                throw new Exception('Cannot delete package with existing purchases');
            }
            
            // Delete package
            $stmt = $pdo->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            Auditor::log($pdo, $user_id, 'DELETE', 'packages', $package_id, ['action' => 'Deleted package']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package deleted successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success&action=delete");
            exit();
            
        case 'update_sessions':
            // Save session assignments for a bundled package
            $package_id = intval($_POST['package_id'] ?? 0);
            $session_ids = $_POST['session_ids'] ?? [];
            
            if ($package_id <= 0) {
                throw new Exception('Invalid package ID');
            }
            
            // Verify package exists
            $check = $pdo->prepare("SELECT id FROM packages WHERE id = ?");
            $check->execute([$package_id]);
            if (!$check->fetch()) {
                throw new Exception('Package not found');
            }
            
            $pdo->beginTransaction();
            
            // Remove existing session links
            $delete_stmt = $pdo->prepare("DELETE FROM package_sessions WHERE package_id = ?");
            $delete_stmt->execute([$package_id]);
            
            // Insert new session links
            if (!empty($session_ids) && is_array($session_ids)) {
                $insert_stmt = $pdo->prepare("INSERT INTO package_sessions (package_id, session_id) VALUES (?, ?)");
                foreach ($session_ids as $sid) {
                    $sid = intval($sid);
                    if ($sid > 0) {
                        // Verify session exists before inserting
                        $verify = $pdo->prepare("SELECT id FROM sessions WHERE id = ?");
                        $verify->execute([$sid]);
                        if ($verify->fetch()) {
                            $insert_stmt->execute([$package_id, $sid]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            Auditor::log($pdo, $user_id, 'UPDATE', 'package_sessions', $package_id, ['action' => 'Updated package sessions']);
            
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'Package sessions updated successfully!']);
                exit();
            }
            header("Location: dashboard.php?page=products&tab=packages&status=success");
            exit();
            
        case 'toggle_status':
            header('Content-Type: application/json');
            $package_id = intval($_POST['id'] ?? $_POST['package_id'] ?? 0);
            
            if ($package_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid package ID']);
                exit();
            }
            
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$package) {
                echo json_encode(['success' => false, 'message' => 'Package not found']);
                exit();
            }
            
            $new_status = $package['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE packages SET is_active = ? WHERE id = ?");
            $stmt->execute([$new_status, $package_id]);
            Auditor::log($pdo, $user_id, 'UPDATE', 'packages', $package_id, ['action' => 'Toggled package status', 'new_status' => $new_status]);
            
            echo json_encode(['success' => true, 'message' => 'Package status updated']);
            exit();
            
        case 'email_registered_users':
            $package_id = intval($_POST['package_id'] ?? 0);
            $email_subject = trim($_POST['subject'] ?? '');
            $email_message = trim($_POST['message'] ?? '');
            
            if ($package_id <= 0) throw new Exception('Invalid package ID');
            if (empty($email_subject)) throw new Exception('Email subject is required');
            if (empty($email_message)) throw new Exception('Email message is required');
            
            // Get package name
            $pkg_stmt = $pdo->prepare("SELECT name FROM packages WHERE id = ?");
            $pkg_stmt->execute([$package_id]);
            $pkg = $pkg_stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pkg) throw new Exception('Package not found');
            
            // Get registered users with emails
            $reg_stmt = $pdo->prepare("
                SELECT u.first_name, u.last_name, u.email
                FROM user_packages up
                JOIN users u ON up.user_id = u.id
                WHERE up.package_id = ? AND up.payment_status = 'paid'
            ");
            $reg_stmt->execute([$package_id]);
            $recipients = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
            $recipients = decryptUserRows($recipients);
            
            if (empty($recipients)) throw new Exception('No registered users with email addresses found');
            
            require_once __DIR__ . '/mailer.php';
            
            $sent = 0;
            $failed = 0;
            foreach ($recipients as $r) {
                $email = $r['email'] ?? '';
                if (empty($email)) { $failed++; continue; }
                $name = trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Athlete';
                try {
                    sendEmail($email, 'notification', [
                        'title' => $email_subject,
                        'message' => $email_message,
                        'name' => $name
                    ]);
                    $sent++;
                } catch (Exception $mailErr) {
                    error_log("Email to $email failed: " . $mailErr->getMessage());
                    $failed++;
                }
            }
            
            Auditor::log($pdo, $user_id, 'email', 'packages', $package_id, ['action' => 'email_registered_users', 'sent' => $sent, 'failed' => $failed, 'subject' => $email_subject]);
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => "Emails sent: $sent" . ($failed > 0 ? ", failed: $failed" : '')]);
            exit();
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    ErrorLogger::error("Package processing error: " . $e->getMessage());
    
    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit();
    }
    header("Location: dashboard.php?page=products&status=error");
    exit();
}
?>
