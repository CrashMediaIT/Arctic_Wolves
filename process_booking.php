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
            logSecurityEvent($pdo, 'booking_cancelled', 
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

// JOIN WAITLIST
if ($action === 'join_waitlist') {
    header('Content-Type: application/json');
    
    try {
        $session_id = intval($_POST['session_id'] ?? 0);
        
        if ($session_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid session ID']);
            exit();
        }
        
        // Verify session exists
        $stmt = $pdo->prepare("SELECT id, title FROM sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$session) {
            echo json_encode(['success' => false, 'message' => 'Session not found']);
            exit();
        }
        
        // Check if user is already on waitlist
        $stmt = $pdo->prepare("SELECT id FROM waitlists WHERE session_id = ? AND user_id = ? AND status IN ('waiting', 'offered')");
        $stmt->execute([$session_id, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You are already on the waitlist for this session']);
            exit();
        }
        
        // Check if user already has a confirmed booking
        $stmt = $pdo->prepare("SELECT id FROM bookings WHERE session_id = ? AND user_id = ? AND status = 'confirmed'");
        $stmt->execute([$session_id, $user_id]);
        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You already have a confirmed booking for this session']);
            exit();
        }
        
        // Get next position on the waitlist (use transaction to prevent race condition)
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 as next_pos FROM waitlists WHERE session_id = ? FOR UPDATE");
            $stmt->execute([$session_id]);
            $next_position = (int)$stmt->fetchColumn();
            
            // Add to waitlist
            $stmt = $pdo->prepare("INSERT INTO waitlists (session_id, user_id, position, status) VALUES (?, ?, ?, 'waiting')");
            $stmt->execute([$session_id, $user_id, $next_position]);
            $waitlist_id = $pdo->lastInsertId();
            $pdo->commit();
            
            Auditor::log($pdo, $user_id, 'create', 'waitlists', $waitlist_id, ['action' => 'join_waitlist', 'session_id' => $session_id, 'position' => $next_position]);
        } catch (Exception $txe) {
            $pdo->rollBack();
            throw $txe;
        }
        
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent($pdo, 'waitlist_joined', 
                "User joined waitlist for session: {$session['title']} (position: $next_position)", 
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
        ErrorLogger::error("Waitlist join error: " . $e->getMessage(), ['session_id' => $session_id ?? 0, 'user_id' => $user_id]);
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
            'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => $domain . '/dashboard.php?page=booking&error=cancelled',
            'client_reference_id' => $user_id,
        ];
        if (!empty($customer_email)) {
            $stripe_params['customer_email'] = $customer_email;
        }
        $checkout_session = \Stripe\Checkout\Session::create($stripe_params);
        
        // Save booking with confirmed status, payment_status='pending' until Stripe confirms payment
        $stmt = $pdo->prepare("
            INSERT INTO bookings (session_id, user_id, amount, payment_status, status, notes) 
            VALUES (?, ?, ?, 'pending', 'confirmed', ?)
        ");
        $stmt->execute([$session_id, $user_id, $final_price, $notes]);
        $new_booking_id = $pdo->lastInsertId();
        
        Auditor::log($pdo, $user_id, 'create', 'bookings', $new_booking_id, ['action' => 'book_private_session', 'session_id' => $session_id, 'amount' => $final_price]);
        
        // Redirect to Stripe
        header("Location: " . $checkout_session->url);
        exit();
        
    } catch (Exception $e) {
        ErrorLogger::error("Private session booking error: " . $e->getMessage(), ['user_id' => $user_id]);
        die("Error creating private session: " . $e->getMessage());
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

// Check for duplicate booking — prevent re-ordering an already booked session
$dup_check = $pdo->prepare("SELECT id FROM bookings WHERE session_id = ? AND user_id = ? AND status IN ('confirmed', 'waitlisted') AND payment_status IN ('pending', 'paid')");
$dup_check->execute([$session_id, $user_id]);
if ($dup_check->fetch()) {
    header("Location: dashboard.php?page=sessions&error=already_booked&session_id=" . urlencode($session_id));
    exit();
}

// Check capacity — if session is full, prevent booking
if (!empty($session['max_participants'])) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE session_id = ? AND status = 'confirmed'");
    $stmt->execute([$session_id]);
    $confirmed_count = (int)$stmt->fetchColumn();
    if ($confirmed_count >= (int)$session['max_participants']) {
        // Session is full — redirect back with message
        header("Location: dashboard.php?page=sessions&error=session_full&session_id=" . urlencode($session_id));
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
        'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url'  => $domain . '/dashboard.php?page=schedule&error=cancelled',
        'client_reference_id' => $user_id,
    ];
    if (!empty($customer_email)) {
        $stripe_params['customer_email'] = $customer_email;
    }
    $checkout_session = \Stripe\Checkout\Session::create($stripe_params);

    // 7. SAVE BOOKING IN DB (status='confirmed', payment_status tracks payment state separately)
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, session_id, stripe_session_id, amount_paid, original_price, discount_code, status, payment_status) VALUES (?, ?, ?, ?, ?, ?, 'confirmed', 'pending')");
    $stmt->execute([$user_id, $session_id, $checkout_session->id, $final_price, $original_price, $applied_code]);
    $new_booking_id = $pdo->lastInsertId();

    Auditor::log($pdo, $user_id, 'create', 'bookings', $new_booking_id, ['action' => 'book_session', 'session_id' => $session_id, 'amount' => $final_price, 'discount_code' => $applied_code]);

    // Redirect user to Stripe
    header("Location: " . $checkout_session->url);
    exit();

} catch (Exception $e) {
    die("Stripe Error: " . $e->getMessage());
}
?>