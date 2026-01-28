<?php
// process_booking.php
session_start();
require 'db_config.php';
require 'security.php';

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
$settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripe_secret = $settings['stripe_secret_key'] ?? '';
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
        
        // Check cancellation policy (e.g., must cancel 24 hours before)
        $hours_until_session = ($session_date->getTimestamp() - $now->getTimestamp()) / 3600;
        $min_cancellation_hours = 24; // Can be made configurable
        
        if ($hours_until_session < $min_cancellation_hours) {
            // Allow cancellation but note that refund may not be available
            $refund_eligible = false;
            $message = 'Booking cancelled. Note: Cancellations within 24 hours may not be eligible for refund.';
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
        
        // Log the cancellation
        if (function_exists('logSecurityEvent')) {
            logSecurityEvent($pdo, 'booking_cancelled', 
                "User cancelled booking ID: $booking_id for session: {$booking['session_title']}", 
                $user_id
            );
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'refund_eligible' => $refund_eligible,
            'booking_id' => $booking_id
        ]);
        exit();
        
    } catch (Exception $e) {
        error_log("Booking cancellation error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to cancel booking: ' . $e->getMessage()]);
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
        
        // Create Stripe checkout session
        $checkout_session = \Stripe\Checkout\Session::create([
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
        ]);
        
        // Save booking with pending status until payment confirmed
        // Note: payment_status='pending' until Stripe webhook confirms payment
        $stmt = $pdo->prepare("
            INSERT INTO bookings (session_id, user_id, amount, payment_status, status, notes) 
            VALUES (?, ?, ?, 'pending', 'pending', ?)
        ");
        $stmt->execute([$session_id, $user_id, $final_price, $notes]);
        
        // Redirect to Stripe
        header("Location: " . $checkout_session->url);
        exit();
        
    } catch (Exception $e) {
        error_log("Private session booking error: " . $e->getMessage());
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
    $checkout_session = \Stripe\Checkout\Session::create([
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
    ]);

    // 7. SAVE PENDING BOOKING IN DB
    $stmt = $pdo->prepare("INSERT INTO bookings (user_id, session_id, stripe_session_id, amount_paid, original_price, discount_code, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
    $stmt->execute([$user_id, $session_id, $checkout_session->id, $final_price, $original_price, $applied_code]);

    // Redirect user to Stripe
    header("Location: " . $checkout_session->url);
    exit();

} catch (Exception $e) {
    die("Stripe Error: " . $e->getMessage());
}
?>