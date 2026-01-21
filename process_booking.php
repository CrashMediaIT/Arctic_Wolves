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

// 4. GET BOOKING DETAILS
$session_id = $_POST['session_id'];
$user_code  = isset($_POST['discount_code']) ? strtoupper(trim($_POST['discount_code'])) : '';
$user_id    = $_SESSION['user_id'];
$domain     = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']); 

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