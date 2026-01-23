<?php
// process_booking.php
session_start();
require 'db_config.php';

// 1. SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
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