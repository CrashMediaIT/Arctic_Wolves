<?php
// process_booking.php
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// 1. SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Validate CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrfToken();
}

// 2. LOAD STRIPE LIBRARY
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} elseif (file_exists('stripe-php/init.php')) {
    require 'stripe-php/init.php';
} else {
    die("Error: Stripe library not found in /stripe-php/ folder.");
}

// 3. LOAD KEYS FROM DB
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripe_secret = $settings['stripe_secret_key'] ?? '';
if (function_exists('decryptCredential')) { $stripe_secret = decryptCredential($stripe_secret); }
$currency = $settings['currency'] ?? 'CAD';

if (empty($stripe_secret)) { die("Stripe is not configured in Admin Settings."); }
\Stripe\Stripe::setApiKey($stripe_secret);

$user_id = $_SESSION['user_id'];
$domain  = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); 
$action  = $_POST['action'] ?? '';

// Detect PWA context from hidden form field to redirect back to pwa.php instead of dashboard.php
$isPwaContext = (($_POST['pwa_context'] ?? '') === '1');
$sessionsPage = $isPwaContext ? 'pwa.php?page=sessions' : 'dashboard.php?page=sessions';
$bookingPage = $isPwaContext ? 'pwa.php?page=sessions' : 'dashboard.php?page=booking';

// 4. HANDLE DIFFERENT BOOKING ACTIONS

// CANCEL BOOKING
if ($action === 'cancel_booking' || $action === 'cancel') {
    header('Content-Type: application/json');
    
    try {
        $booking_id = intval($_POST['booking_id'] ?? 0);
        
        if ($booking_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid booking ID']);
            exit();
        }
        
        // Get booking details and verify ownership
        $stmt = $pdo->prepare("
            SELECT b.*, s.session_date, s.title as session_title
            FROM bookings b
            JOIN sessions s ON b.session_id = s.id
            WHERE b.id = ? AND b.user_id = ?
        ");
        $stmt->execute([$booking_id, $user_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            echo json_encode(['success' => false, 'message' => 'Booking not found or access denied']);
            exit();
        }
        
        // Check if booking is already cancelled
        if ($booking['status'] === 'cancelled') {
            echo json_encode(['success' => false, 'message' => 'Booking is already cancelled']);
            exit();
        }
        
        // Check if session has already occurred
        $session_date = new DateTime($booking['session_date']);
        $now = new DateTime();
        
        if ($session_date < $now) {
            echo json_encode(['success' => false, 'message' => 'Cannot cancel past sessions']);
            exit();
        }
        
        // Check cancellation policy (sessions require 48 hours notice)
        $hours_until_session = ($session_date->getTimestamp() - $now->getTimestamp()) / 3600;
        $min_cancellation_hours = 48; // 48-hour cancellation policy for sessions
        
        if ($hours_until_session < $min_cancellation_hours) {
            // Allow cancellation but note that refund may not be available
            $refund_eligible = false;
            $message = 'Booking cancelled. Note: Cancellations within 48 hours of the session are not eligible for refund.';
        } else {
            $refund_eligible = true;
            $message = 'Booking cancelled successfully. You may request a refund if payment was made.';
        }
        
        // Update booking status to cancelled
        $stmt = $pdo->prepare("
            UPDATE bookings 
            SET status = 'cancelled', 
                payment_status = CASE 
                    WHEN payment_status = 'paid' THEN 'paid' 
                    ELSE 'cancelled' 
                END
            WHERE id = ? AND user_id = ?
        ");
        $stmt->execute([$booking_id, $user_id]);
        
        Auditor::log($pdo, $user_id, 'delete', 'bookings', $booking_id, ['action' => 'cancel_booking', 'session_title' => $booking['session_title']]);
        
        // Log the cancellation
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('booking_cancelled', 
                "User cancelled booking ID: $booking_id for session: {$booking['session_title']}", 
                $user_id
            );
        }
        
        // Notify the next person on the waitlist that a spot opened up
        try {
            $wStmt = $pdo->prepare("
                SELECT w.id, w.user_id, w.position 
                FROM waitlists w 
                WHERE w.session_id = ? AND w.status = 'waiting' 
                ORDER BY w.position ASC 
                LIMIT 1
            ");
            $wStmt->execute([$booking['session_id']]);
            $nextWaitlisted = $wStmt->fetch(PDO::FETCH_ASSOC);
            if ($nextWaitlisted) {
                $pdo->prepare("UPDATE waitlists SET status = 'offered', notified_at = NOW() WHERE id = ?")
                    ->execute([$nextWaitlisted['id']]);
                // Create a notification for the waitlisted user
                try {
                    $pdo->prepare("
                        INSERT INTO notifications (user_id, type, title, message, created_at) 
                        VALUES (?, 'session', 'Spot Available!', ?, NOW())
                    ")->execute([
                        $nextWaitlisted['user_id'],
                        "A spot opened up for session: {$booking['session_title']}. Book now before it fills up!"
                    ]);
                } catch (PDOException $ne) { /* notifications table may not exist */ }
            }
        } catch (PDOException $we) { /* waitlists table may not exist yet */ }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'refund_eligible' => $refund_eligible,
            'booking_id' => $booking_id
        ]);
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Booking cancellation error: " . $e->getMessage(), ['booking_id' => $booking_id ?? 0, 'user_id' => $user_id]);
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking: ' . $e->getMessage()]);
        exit();
    }
}

// REGISTER FOR DEVELOPMENT PROGRAM (goalie_dev or player_dev — payment required)
if ($action === 'register_dev_program') {
    try {
        $program_type = $_POST['program_type'] ?? '';
        $template_id = intval($_POST['template_id'] ?? 0);

        if (!in_array($program_type, ['goalie_dev', 'player_dev'])) {
            die("Invalid program type.");
        }
        if ($template_id <= 0) {
            die("Invalid template ID.");
        }

        // Check if already enrolled in an ACTIVE program of same type and template
        $dup_check = $pdo->prepare("SELECT id FROM development_program_enrollments WHERE athlete_id = ? AND program_type = ? AND template_id = ? AND status = 'active'");
        $dup_check->execute([$user_id, $program_type, $template_id]);
        if ($dup_check->fetch()) {
            $devPage = $isPwaContext ? 'pwa.php?page=personal_development_programs' : 'dashboard.php?page=personal_development_programs';
            header("Location: $devPage&error=already_enrolled");
            exit();
        }

        // Get template price
        $tpl_stmt = $pdo->prepare("SELECT id, name, price FROM training_session_templates WHERE id = ? AND is_active = 1");
        $tpl_stmt->execute([$template_id]);
        $template = $tpl_stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            die("Development program session product not found.");
        }

        $final_price = floatval($template['price'] ?? 0);

        if ($final_price > 0) {
            // Create Stripe checkout session for paid development program
            $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $email_stmt->execute([$user_id]);
            $customer_email = $email_stmt->fetchColumn();

            $stripe_params = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => round($final_price * 100),
                        'product_data' => [
                            'name' => $template['name'],
                            'description' => 'Long-term development program enrollment',
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&type=dev_program' . ($isPwaContext ? '&pwa=1' : ''),
                'cancel_url'  => $domain . '/' . $bookingPage . '&error=cancelled',
                'client_reference_id' => $user_id,
                'metadata' => [
                    'type' => 'dev_program',
                    'program_type' => $program_type,
                    'template_id' => $template_id,
                    'athlete_id' => $user_id,
                ],
            ];
            if (!empty($customer_email)) {
                $stripe_params['customer_email'] = $customer_email;
            }
            $checkout_session = \Stripe\Checkout\Session::create($stripe_params);

            header("Location: " . $checkout_session->url);
            exit();
        } else {
            // Free program — enroll directly with auto-calculated dates
            $duration_weeks = null;
            try {
                $dur_stmt = $pdo->prepare("SELECT duration_weeks FROM training_session_templates WHERE id = ? AND is_dev_program = 1");
                $dur_stmt->execute([$template_id]);
                $dur_row = $dur_stmt->fetch(PDO::FETCH_ASSOC);
                $duration_weeks = $dur_row['duration_weeks'] ?? null;
            } catch (PDOException $e) { /* column may not exist */ }
            if (!$duration_weeks) {
                try {
                    $dur_stmt2 = $pdo->prepare("SELECT program_duration_weeks FROM development_notification_templates WHERE program_type = ?");
                    $dur_stmt2->execute([$program_type]);
                    $dur_row2 = $dur_stmt2->fetch(PDO::FETCH_ASSOC);
                    $duration_weeks = $dur_row2['program_duration_weeks'] ?? null;
                } catch (PDOException $e) { /* ignore */ }
            }
            
            $start_date = date('Y-m-d');
            $duration_weeks = $duration_weeks !== null ? max(1, min(52, intval($duration_weeks))) : null;
            $end_date = $duration_weeks ? date('Y-m-d', strtotime("+{$duration_weeks} weeks")) : null;
            
            $stmt = $pdo->prepare("INSERT INTO development_program_enrollments (athlete_id, program_type, program_name, template_id, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $program_type, $template['name'] ?? null, $template_id, $start_date, $end_date]);

            Auditor::log($pdo, $user_id, 'create', 'development_program_enrollments', $pdo->lastInsertId(), [
                'action' => 'register_dev_program', 'program_type' => $program_type, 'amount' => 0
            ]);

            $devPage = $isPwaContext ? 'pwa.php?page=personal_development_programs' : 'dashboard.php?page=personal_development_programs';
            header("Location: $devPage&status=enrolled");
            exit();
        }
    } catch (Exception $e) {
        ErrorLogger::error("Dev program registration error: " . $e->getMessage(), ['program_type' => $program_type ?? '', 'user_id' => $user_id]);
        $devPage = $isPwaContext ? 'pwa.php?page=personal_development_programs' : 'dashboard.php?page=personal_development_programs';
        header("Location: $devPage&error=registration_failed");
        exit();
    }
}

// REGISTER FOR TEMPLATE SESSION (from training_session_templates)
if ($action === 'register_template_session') {
    try {
        $session_date_id = intval($_POST['session_date_id'] ?? 0);
        
        if ($session_date_id <= 0) {
            die("Invalid session date ID.");
        }
        
        // Fetch session date and template info
        $stmt = $pdo->prepare("
            SELECT td.id as session_date_id, td.template_id, td.session_date, td.max_participants,
                   t.name, t.price, t.max_participants as template_max_participants, t.duration_minutes
            FROM training_session_dates td
            JOIN training_session_templates t ON td.template_id = t.id
            WHERE td.id = ? AND td.is_active = 1 AND t.is_active = 1
        ");
        $stmt->execute([$session_date_id]);
        $session_date = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session_date) {
            die("Session not found or is no longer available.");
        }
        
        // Check if user is already registered
        $dup_check = $pdo->prepare("SELECT id FROM session_date_athletes WHERE session_date_id = ? AND athlete_id = ?");
        $dup_check->execute([$session_date_id, $user_id]);
        if ($dup_check->fetch()) {
            header("Location: $sessionsPage&error=already_booked");            exit();
        }
        
        // Check capacity
        $max_participants = $session_date['max_participants'] ?? $session_date['template_max_participants'];
        if (!empty($max_participants)) {
            $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM session_date_athletes WHERE session_date_id = ?");
            $count_stmt->execute([$session_date_id]);
            $registered_count = (int)$count_stmt->fetchColumn();
            if ($registered_count >= (int)$max_participants) {
                header("Location: $sessionsPage&error=session_full");                exit();
            }
        }
        
        $original_price = $session_date['price'] ?? 0;
        $final_price = $original_price;
        
        if ($final_price > 0) {
            // Create Stripe checkout session for paid template sessions
            $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $email_stmt->execute([$user_id]);
            $customer_email = $email_stmt->fetchColumn();
            
            $stripe_params = [
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => $currency,
                        'unit_amount' => round($final_price * 100),
                        'product_data' => [
                            'name' => 'Training Session: ' . $session_date['name'],
                            'description' => 'Session on ' . date('M j, Y', strtotime($session_date['session_date'])),
                        ],
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&type=template_session' . ($isPwaContext ? '&pwa=1' : ''),
                'cancel_url'  => $domain . '/' . $bookingPage . '&error=cancelled',
                'client_reference_id' => $user_id,
                'metadata' => [
                    'type' => 'template_session',
                    'session_date_id' => $session_date_id,
                    'athlete_id' => $user_id,
                ],
            ];
            if (!empty($customer_email)) {
                $stripe_params['customer_email'] = $customer_email;
            }
            $checkout_session = \Stripe\Checkout\Session::create($stripe_params);
            
            // Do NOT register the athlete here — registration happens in payment_success.php
            // after Stripe confirms payment. This prevents marking sessions as registered
            // when the user clicks Register but then navigates back without paying.
            
            header("Location: " . $checkout_session->url);
            exit();
        } else {
            // Free session - register directly
            $stmt = $pdo->prepare("INSERT INTO session_date_athletes (session_date_id, athlete_id) VALUES (?, ?)");
            $stmt->execute([$session_date_id, $user_id]);
            
            Auditor::log($pdo, $user_id, 'create', 'session_date_athletes', $pdo->lastInsertId(), [
                'action' => 'register_template_session', 'session_date_id' => $session_date_id, 'amount' => 0
            ]);
            
            header("Location: $sessionsPage&status=booked");
            exit();
        }
    } catch (Exception $e) {
        ErrorLogger::error("Template session registration error: " . $e->getMessage(), ['session_date_id' => $session_date_id ?? 0, 'user_id' => $user_id]);
        header("Location: $sessionsPage&error=registration_failed");
        exit();
    }
}

// JOIN WAITLIST (supports sessions, packages, and templates/programs)
if ($action === 'join_waitlist') {
    header('Content-Type: application/json');
    
    try {
        $session_id = intval($_POST['session_id'] ?? 0);
        $package_id = intval($_POST['package_id'] ?? 0);
        $template_id = intval($_POST['template_id'] ?? 0);
        
        // Determine which product type
        $product_name = '';
        $wl_session_id = null;
        $wl_package_id = null;
        $wl_template_id = null;
        
        if ($session_id > 0) {
            $stmt = $pdo->prepare("SELECT id, title FROM sessions WHERE id = ?");
            $stmt->execute([$session_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) { echo json_encode(['success' => false, 'message' => 'Session not found']); exit(); }
            $product_name = $product['title'];
            $wl_session_id = $session_id;
            
            // Check if user already on waitlist for this session
            $stmt = $pdo->prepare("SELECT id FROM waitlists WHERE session_id = ? AND user_id = ? AND status IN ('waiting', 'offered')");
            $stmt->execute([$session_id, $user_id]);
            if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'You are already on the waitlist']); exit(); }
            
            // Check if user already has a confirmed booking
            $stmt = $pdo->prepare("SELECT id FROM bookings WHERE session_id = ? AND user_id = ? AND status = 'confirmed'");
            $stmt->execute([$session_id, $user_id]);
            if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'You already have a confirmed booking']); exit(); }
            
        } elseif ($package_id > 0) {
            $stmt = $pdo->prepare("SELECT id, name FROM packages WHERE id = ? AND is_active = 1");
            $stmt->execute([$package_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) { echo json_encode(['success' => false, 'message' => 'Package not found']); exit(); }
            $product_name = $product['name'];
            $wl_package_id = $package_id;
            
            // Check if user already on waitlist for this package
            $stmt = $pdo->prepare("SELECT id FROM waitlists WHERE package_id = ? AND user_id = ? AND status IN ('waiting', 'offered')");
            $stmt->execute([$package_id, $user_id]);
            if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'You are already on the waitlist']); exit(); }
            
        } elseif ($template_id > 0) {
            $stmt = $pdo->prepare("SELECT id, name FROM training_session_templates WHERE id = ? AND is_active = 1");
            $stmt->execute([$template_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$product) { echo json_encode(['success' => false, 'message' => 'Program not found']); exit(); }
            $product_name = $product['name'];
            $wl_template_id = $template_id;
            
            // Check if user already on waitlist for this template
            $stmt = $pdo->prepare("SELECT id FROM waitlists WHERE template_id = ? AND user_id = ? AND status IN ('waiting', 'offered')");
            $stmt->execute([$template_id, $user_id]);
            if ($stmt->fetch()) { echo json_encode(['success' => false, 'message' => 'You are already on the waitlist']); exit(); }
            
        } else {
            echo json_encode(['success' => false, 'message' => 'No product specified']);
            exit();
        }
        
        // Get next position on the waitlist (use transaction to prevent race condition)
        $pdo->beginTransaction();
        try {
            // Build position query based on product type
            if ($wl_session_id) {
                $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM waitlists WHERE session_id = ? FOR UPDATE");
                $posStmt->execute([$wl_session_id]);
            } elseif ($wl_package_id) {
                $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM waitlists WHERE package_id = ? FOR UPDATE");
                $posStmt->execute([$wl_package_id]);
            } else {
                $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM waitlists WHERE template_id = ? FOR UPDATE");
                $posStmt->execute([$wl_template_id]);
            }
            $next_position = (int)$posStmt->fetchColumn();
            
            // Add to waitlist
            $stmt = $pdo->prepare("INSERT INTO waitlists (session_id, package_id, template_id, user_id, position, status) VALUES (?, ?, ?, ?, ?, 'waiting')");
            $stmt->execute([$wl_session_id, $wl_package_id, $wl_template_id, $user_id, $next_position]);
            $waitlist_id = $pdo->lastInsertId();
            $pdo->commit();
            
            Auditor::log($pdo, $user_id, 'create', 'waitlists', $waitlist_id, [
                'action' => 'join_waitlist', 'session_id' => $wl_session_id,
                'package_id' => $wl_package_id, 'template_id' => $wl_template_id,
                'position' => $next_position
            ]);
        } catch (Exception $txe) {
            $pdo->rollBack();
            throw $txe;
        }
        
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent('waitlist_joined', 
                "User joined waitlist for: {$product_name} (position: $next_position)", 
                $user_id
            );
        }
        
        echo json_encode([
            'success' => true,
            'message' => "You've been added to the waitlist (position #$next_position)",
            'position' => $next_position
        ]);
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Waitlist join error: " . $e->getMessage(), ['user_id' => $user_id]);
        echo json_encode(['success' => false, 'message' => 'Failed to join waitlist']);
        exit();
    }
}

// LEAVE WAITLIST
if ($action === 'leave_waitlist') {
    header('Content-Type: application/json');
    
    try {
        $session_id = intval($_POST['session_id'] ?? 0);
        
        if ($session_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
            exit();
        }
        
        $stmt = $pdo->prepare("DELETE FROM waitlists WHERE session_id = ? AND user_id = ? AND status IN ('waiting', 'offered')");
        $stmt->execute([$session_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            Auditor::log($pdo, $user_id, 'delete', 'waitlists', null, ['action' => 'leave_waitlist', 'session_id' => $session_id]);
            
            // Recalculate positions to close gaps
            $pdo->exec("SET @pos = 0");
            $pdo->prepare("
                UPDATE waitlists SET position = (@pos := @pos + 1)
                WHERE session_id = ? AND status IN ('waiting', 'offered')
                ORDER BY position ASC
            ")->execute([$session_id]);
            
            echo json_encode(['success' => true, 'message' => 'You have been removed from the waitlist']);
        } else {
            echo json_encode(['success' => false, 'message' => 'You are not on the waitlist for this session']);
        }
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Waitlist leave error: " . $e->getMessage(), ['session_id' => $session_id ?? 0, 'user_id' => $user_id]);
        echo json_encode(['success' => false, 'message' => 'Failed to leave waitlist']);
        exit();
    }
}

// OFFER WAITLIST SPOT (Coach/Admin: send enrollment email with purchase link)
if ($action === 'offer_waitlist_spot') {
    header('Content-Type: application/json');
    
    try {
        // Verify coach/admin role
        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $roleStmt->execute([$user_id]);
        $userRole = $roleStmt->fetchColumn();
        $allowedRoles = ['admin', 'coach', 'team_coach', 'goalie_dev', 'player_dev'];
        if (!in_array($userRole, $allowedRoles)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        $waitlist_id = intval($_POST['waitlist_id'] ?? 0);
        if ($waitlist_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid waitlist entry']);
            exit();
        }
        
        // Get waitlist entry with user info
        $stmt = $pdo->prepare("
            SELECT w.*, u.first_name, u.last_name, u.email,
                   s.title as session_title, s.price as session_price,
                   p.name as package_name, p.price as package_price,
                   tst.name as template_name, tst.price as template_price
            FROM waitlists w
            JOIN users u ON w.user_id = u.id
            LEFT JOIN sessions s ON w.session_id = s.id
            LEFT JOIN packages p ON w.package_id = p.id
            LEFT JOIN training_session_templates tst ON w.template_id = tst.id
            WHERE w.id = ? AND w.status = 'waiting'
        ");
        $stmt->execute([$waitlist_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$entry) {
            echo json_encode(['success' => false, 'message' => 'Waitlist entry not found or already offered']);
            exit();
        }
        
        // Generate secure token for purchase link
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+48 hours'));
        
        // Update waitlist entry with token and offered status
        $pdo->prepare("UPDATE waitlists SET status = 'offered', notified_at = NOW(), waitlist_token = ?, token_expires_at = ? WHERE id = ?")
            ->execute([$token, $expiresAt, $waitlist_id]);
        
        // Determine product name and build purchase link
        $productName = $entry['session_title'] ?? $entry['package_name'] ?? $entry['template_name'] ?? 'Training Product';
        $purchaseLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/pwa.php?page=sessions&waitlist_token=" . urlencode($token);
        
        // Decrypt user PII for email
        $firstName = !empty($entry['first_name']) ? FieldEncryption::decrypt($entry['first_name']) : 'Athlete';
        $lastName = !empty($entry['last_name']) ? FieldEncryption::decrypt($entry['last_name']) : '';
        $email = !empty($entry['email']) ? FieldEncryption::decrypt($entry['email']) : '';
        
        if (empty($email)) {
            echo json_encode(['success' => false, 'message' => 'No email address found for this athlete']);
            exit();
        }
        
        // Send enrollment email using the notification type
        if (function_exists('sendEmail')) {
            sendEmail($email, 'notification', [
                'title' => 'A Spot Is Available!',
                'name' => trim($firstName . ' ' . $lastName),
                'message' => "Great news! A spot has opened up for: $productName. You have 48 hours to complete your enrollment. Click the button below to secure your spot.",
                'link' => $purchaseLink,
            ]);
        }
        
        // Create in-app notification
        try {
            $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, link_url, created_at) 
                VALUES (?, 'session', 'Spot Available!', ?, ?, NOW())
            ")->execute([
                $entry['user_id'],
                "A spot opened up for: $productName. You have 48 hours to enroll!",
                $purchaseLink,
            ]);
        } catch (PDOException $ne) { /* notifications table may not exist */ }
        
        Auditor::log($pdo, $user_id, 'update', 'waitlists', $waitlist_id, [
            'action' => 'offer_waitlist_spot', 'athlete_id' => $entry['user_id'], 'product' => $productName
        ]);
        
        echo json_encode(['success' => true, 'message' => "Enrollment email sent to " . trim($firstName . ' ' . $lastName)]);
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Offer waitlist spot error: " . $e->getMessage(), ['waitlist_id' => $waitlist_id ?? 0, 'user_id' => $user_id]);
        echo json_encode(['success' => false, 'message' => 'Failed to offer spot']);
        exit();
    }
}

// ENROLL FROM WAITLIST (Coach/Admin: directly enroll athlete, bypassing payment)
if ($action === 'enroll_from_waitlist') {
    header('Content-Type: application/json');
    
    try {
        // Verify coach/admin role
        $roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $roleStmt->execute([$user_id]);
        $userRole = $roleStmt->fetchColumn();
        $allowedRoles = ['admin', 'coach', 'team_coach', 'goalie_dev', 'player_dev'];
        if (!in_array($userRole, $allowedRoles)) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit();
        }
        
        $waitlist_id = intval($_POST['waitlist_id'] ?? 0);
        if ($waitlist_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid waitlist entry']);
            exit();
        }
        
        // Get waitlist entry
        $stmt = $pdo->prepare("SELECT * FROM waitlists WHERE id = ? AND status IN ('waiting', 'offered')");
        $stmt->execute([$waitlist_id]);
        $entry = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$entry) {
            echo json_encode(['success' => false, 'message' => 'Waitlist entry not found']);
            exit();
        }
        
        $pdo->beginTransaction();
        try {
            if (!empty($entry['session_id'])) {
                // Enroll in session - create booking with paid status (coach override)
                $pdo->prepare("
                    INSERT INTO bookings (session_id, user_id, payment_status, status, notes) 
                    VALUES (?, ?, 'paid', 'confirmed', 'Enrolled from waitlist by coach')
                ")->execute([$entry['session_id'], $entry['user_id']]);
                
            } elseif (!empty($entry['package_id'])) {
                // Enroll in package - create user_package with paid status
                $pkgStmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
                $pkgStmt->execute([$entry['package_id']]);
                $pkg = $pkgStmt->fetch(PDO::FETCH_ASSOC);
                if ($pkg) {
                    $pdo->prepare("
                        INSERT INTO user_packages (user_id, package_id, credits_remaining, payment_status, amount_paid, expiry_date) 
                        VALUES (?, ?, ?, 'paid', 0, ?)
                    ")->execute([
                        $entry['user_id'], $entry['package_id'],
                        $pkg['credits'] ?? 0,
                        $pkg['valid_days'] ? date('Y-m-d', strtotime('+' . (int)$pkg['valid_days'] . ' days')) : null,
                    ]);
                }
                
            } elseif (!empty($entry['template_id'])) {
                // Enroll in program - create development program enrollment
                $tplStmt = $pdo->prepare("SELECT * FROM training_session_templates WHERE id = ?");
                $tplStmt->execute([$entry['template_id']]);
                $tpl = $tplStmt->fetch(PDO::FETCH_ASSOC);
                if ($tpl) {
                    $programType = ($tpl['session_type'] === 'on_ice') ? 'goalie_dev' : 'player_dev';
                    $startDate = date('Y-m-d');
                    $endDate = !empty($tpl['duration_weeks']) ? date('Y-m-d', strtotime("+{$tpl['duration_weeks']} weeks")) : null;
                    $pdo->prepare("
                        INSERT INTO development_program_enrollments (athlete_id, program_type, program_name, template_id, start_date, end_date) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ")->execute([$entry['user_id'], $programType, $tpl['name'] ?? '', $entry['template_id'], $startDate, $endDate]);
                }
            }
            
            // Mark waitlist entry as accepted
            $pdo->prepare("UPDATE waitlists SET status = 'accepted' WHERE id = ?")->execute([$waitlist_id]);
            
            $pdo->commit();
        } catch (Exception $txe) {
            $pdo->rollBack();
            throw $txe;
        }
        
        Auditor::log($pdo, $user_id, 'update', 'waitlists', $waitlist_id, [
            'action' => 'enroll_from_waitlist', 'athlete_id' => $entry['user_id']
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Athlete enrolled successfully']);
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Enroll from waitlist error: " . $e->getMessage(), ['waitlist_id' => $waitlist_id ?? 0, 'user_id' => $user_id]);
        echo json_encode(['success' => false, 'message' => 'Failed to enroll athlete']);
        exit();
    }
}

if ($action === 'book_private_session') {
    // Book a new private session (create session + booking)
    try {
        // Validate required fields
        $session_type_id = $_POST['session_type_id'] ?? null;
        $coach_id = $_POST['coach_id'] ?? null;
        $session_date = $_POST['session_date'] ?? null;
        $session_time = $_POST['session_time'] ?? null;
        $notes = $_POST['notes'] ?? '';
        
        if (!$session_type_id || !$coach_id || !$session_date || !$session_time) {
            die("Missing required fields for private session booking.");
        }
        
        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $session_date)) {
            die("Invalid date format. Expected YYYY-MM-DD.");
        }
        
        // Validate time format (HH:MM)
        if (!preg_match('/^\d{2}:\d{2}$/', $session_time)) {
            die("Invalid time format. Expected HH:MM.");
        }
        
        // Get session type details for pricing
        $stmt = $pdo->prepare("SELECT * FROM session_types WHERE id = ?");
        $stmt->execute([$session_type_id]);
        $session_type = $stmt->fetch();
        if (!$session_type) { die("Session type not found."); }
        
        // Combine date and time using proper date validation
        $session_datetime = $session_date . ' ' . $session_time . ':00';
        $dt = DateTime::createFromFormat('Y-m-d H:i:s', $session_datetime);
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $session_datetime) {
            die("Invalid date/time combination.");
        }
        
        // Create the private session
        $stmt = $pdo->prepare("
            INSERT INTO sessions 
            (session_type_id, coach_id, title, description, session_date, duration_minutes, price, max_participants, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, 'scheduled')
        ");
        $stmt->execute([
            $session_type_id,
            $coach_id,
            'Private Session: ' . $session_type['name'],
            $notes,
            $session_datetime,
            $session_type['duration'] ?? 60,
            $session_type['price']
        ]);
        
        $new_session_id = $pdo->lastInsertId();
        
        // Now proceed with booking this new session
        $session_id = $new_session_id;
        $original_price = $session_type['price'];
        $final_price = $original_price;
        
        // Get user email for Stripe checkout pre-fill
        $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $email_stmt->execute([$user_id]);
        $customer_email = $email_stmt->fetchColumn();
        
        // Create Stripe checkout session
        $stripe_params = [
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => round($final_price * 100),
                    'product_data' => [
                        'name' => 'Private Session: ' . $session_type['name'],
                        'description' => 'Private training session on ' . $dt->format('M d, Y'),
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&type=booking' . ($isPwaContext ? '&pwa=1' : ''),
            'cancel_url'  => $domain . '/' . $bookingPage . '&error=cancelled',
            'client_reference_id' => $user_id,
        ];
        if (!empty($customer_email)) {
            $stripe_params['customer_email'] = $customer_email;
        }
        $checkout_session = \Stripe\Checkout\Session::create($stripe_params);
        
        // Save booking with confirmed status, payment_status='pending' until Stripe confirms payment
        $stmt = $pdo->prepare("
            INSERT INTO bookings (session_id, user_id, stripe_session_id, amount_paid, payment_status, status, notes) 
            VALUES (?, ?, ?, ?, 'pending', 'confirmed', ?)
        ");
        $stmt->execute([$session_id, $user_id, $checkout_session->id, $final_price, $notes]);
        $new_booking_id = $pdo->lastInsertId();
        
        Auditor::log($pdo, $user_id, 'create', 'bookings', $new_booking_id, ['action' => 'book_private_session', 'session_id' => $session_id, 'amount' => $final_price]);
        
        // Redirect to Stripe
        header("Location: " . $checkout_session->url);
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Private session booking error: " . $e->getMessage(), ['user_id' => $user_id]);
        header("Location: $sessionsPage&error=booking_failed");
        exit();
    }
}

// 5. HANDLE EXISTING SESSION BOOKING
$session_id = $_POST['session_id'] ?? null;
if (!$session_id) {
    die("No session specified for booking.");
}

$user_code  = isset($_POST['discount_code']) ? strtoupper(trim($_POST['discount_code'])) : '';

// Fetch Session Info
$stmt = $pdo->prepare("SELECT * FROM sessions WHERE id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch();
if (!$session) { die("Session not found."); }

// Block direct booking if session is waitlist-only (must use waitlist enrollment flow)
if (!empty($session['waitlist_only'])) {
    // Check if user has a valid waitlist token (offered status)
    $tokenParam = $_POST['waitlist_token'] ?? $_GET['waitlist_token'] ?? '';
    $hasValidOffer = false;
    if (!empty($tokenParam)) {
        $offerStmt = $pdo->prepare("SELECT id FROM waitlists WHERE session_id = ? AND user_id = ? AND status = 'offered' AND waitlist_token = ? AND token_expires_at > NOW()");
        $offerStmt->execute([$session_id, $user_id, $tokenParam]);
        $hasValidOffer = (bool)$offerStmt->fetch();
    }
    if (!$hasValidOffer) {
        header("Location: $sessionsPage&error=waitlist_only");
        exit();
    }
}

// Check for duplicate booking — prevent re-ordering an already paid session
$dup_check = $pdo->prepare("SELECT id, payment_status FROM bookings WHERE session_id = ? AND user_id = ? AND status IN ('confirmed', 'waitlisted')");
$dup_check->execute([$session_id, $user_id]);
$existing_booking = $dup_check->fetch(PDO::FETCH_ASSOC);
if ($existing_booking && $existing_booking['payment_status'] === 'paid') {
    header("Location: $sessionsPage&error=already_booked&session_id=" . urlencode($session_id));
    exit();
}

// Check capacity — if session is full, prevent booking (only count paid bookings)
if (!empty($session['max_participants'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE session_id = ? AND status = 'confirmed' AND payment_status = 'paid'");
    $stmt->execute([$session_id]);
    $confirmed_count = (int)$stmt->fetchColumn();
    if ($confirmed_count >= (int)$session['max_participants']) {
        // Session is full — redirect back with message
        header("Location: $sessionsPage&error=session_full&session_id=" . urlencode($session_id));
        exit();
    }
}

// 5. CALCULATE PRICE (Discount Logic)
$original_price = $session['price'];
$final_price    = $original_price;
$applied_code   = null;

if (!empty($user_code)) {
    $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE code = ?");
    $stmt->execute([$user_code]);
    $discount = $stmt->fetch();

    if ($discount) {
        $now = date('Y-m-d');
        $is_expired = ($discount['expiry_date'] && $discount['expiry_date'] < $now);
        $is_limit_reached = ($discount['usage_limit'] > 0 && $discount['times_used'] >= $discount['usage_limit']);

        if (!$is_expired && !$is_limit_reached) {
            if ($discount['type'] == 'percent') {
                $deduction = $original_price * ($discount['value'] / 100);
            } else {
                $deduction = $discount['value'];
            }
            $final_price = max(0, $original_price - $deduction);
            $applied_code = $user_code;
            
            // Increment usage
            $pdo->prepare("UPDATE discount_codes SET times_used = times_used + 1 WHERE id = ?")->execute([$discount['id']]);
        }
    }
}

// 6. CREATE STRIPE SESSION
try {
    // Get user email for Stripe checkout pre-fill
    $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $email_stmt->execute([$user_id]);
    $customer_email = $email_stmt->fetchColumn();
    
    $stripe_params = [
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => $currency,
                'unit_amount' => round($final_price * 100), // Convert to cents
                'product_data' => [
                    'name' => 'Training Session: ' . $session['title'],
                    'description' => $applied_code ? "Discount '$applied_code' applied" : 'Regular Rate',
                ],
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&type=booking' . ($isPwaContext ? '&pwa=1' : ''),
        'cancel_url'  => $domain . '/' . $bookingPage . '&error=cancelled',
        'client_reference_id' => $user_id,
    ];
    if (!empty($customer_email)) {
        $stripe_params['customer_email'] = $customer_email;
    }
    $checkout_session = \Stripe\Checkout\Session::create($stripe_params);

    // 7. SAVE BOOKING IN DB (status='confirmed', payment_status tracks payment state separately)
    // If there's an existing pending booking for this session, update it instead of creating a duplicate
    if ($existing_booking && $existing_booking['payment_status'] === 'pending') {
        $stmt = $pdo->prepare("UPDATE bookings SET stripe_session_id = ?, amount_paid = ?, original_price = ?, discount_code = ?, booking_date = NOW() WHERE id = ?");
        $stmt->execute([$checkout_session->id, $final_price, $original_price, $applied_code, $existing_booking['id']]);
        $new_booking_id = $existing_booking['id'];
    } else {
        $stmt = $pdo->prepare("INSERT INTO bookings (user_id, session_id, stripe_session_id, amount_paid, original_price, discount_code, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'pending')");
        $stmt->execute([$user_id, $session_id, $checkout_session->id, $final_price, $original_price, $applied_code]);
        $new_booking_id = $pdo->lastInsertId();
    }

    Auditor::log($pdo, $user_id, 'create', 'bookings', $new_booking_id, ['action' => 'book_session', 'session_id' => $session_id, 'amount' => $final_price, 'discount_code' => $applied_code]);

    // Redirect user to Stripe
    header("Location: " . $checkout_session->url);
    exit();

} catch (Exception $e) {
    ErrorLogger::error("Stripe booking error: " . $e->getMessage(), ['user_id' => $user_id, 'session_id' => $session_id ?? '']);
    header("Location: $sessionsPage&error=payment_failed");
    exit();
}
?>