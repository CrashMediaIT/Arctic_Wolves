<?php
// process_purchase_package.php - Handle Stripe checkout for package purchases
session_start();
require 'db_config.php';
require 'security.php';
require 'notifications.php';

// Set security headers
setSecurityHeaders();

// Validate CSRF token
checkCsrfToken();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Load Stripe library
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} elseif (file_exists('stripe-php/init.php')) {
    require 'stripe-php/init.php';
} else {
    die("Error: Stripe library not found.");
}

// Load Stripe settings
$settings = $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripe_secret = $settings['stripe_secret_key'] ?? '';
$currency = $settings['currency'] ?? 'CAD';
$tax_rate = floatval($settings['tax_rate'] ?? 13.00);
$tax_name = $settings['tax_name'] ?? 'HST';

if (empty($stripe_secret)) {
    die("Stripe is not configured. Please contact administrator.");
}

\Stripe\Stripe::setApiKey($stripe_secret);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$package_id = intval($_POST['package_id']);
$domain = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);

try {
    // Get package details
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ? AND is_active = 1");
    $stmt->execute([$package_id]);
    $package = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$package) {
        throw new Exception('Package not found or inactive');
    }
    
    // Handle multi-athlete purchase for parents
    $athlete_ids = [];
    if ($user_role === 'parent' && isset($_POST['athlete_ids']) && is_array($_POST['athlete_ids'])) {
        $athlete_ids = array_map('intval', $_POST['athlete_ids']);
        
        // Verify parent can book for these athletes
        $placeholders = str_repeat('?,', count($athlete_ids) - 1) . '?';
        $verify_stmt = $pdo->prepare("
            SELECT athlete_id FROM managed_athletes 
            WHERE parent_id = ? AND athlete_id IN ($placeholders) AND can_book = 1
        ");
        $verify_stmt->execute(array_merge([$user_id], $athlete_ids));
        $verified_athletes = $verify_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($verified_athletes) !== count($athlete_ids)) {
            throw new Exception('Invalid athlete selection');
        }
    } else {
        // Purchase for self
        $athlete_ids = [$user_id];
    }
    
    $num_purchases = count($athlete_ids);
    
    // Calculate pricing
    $subtotal = $package['price'] * $num_purchases;
    $tax_amount = round($subtotal * ($tax_rate / 100), 2);
    $total = $subtotal + $tax_amount;
    
    // Create line items for Stripe
    $line_items = [[
        'price_data' => [
            'currency' => strtolower($currency),
            'product_data' => [
                'name' => $package['name'],
                'description' => $package['description'] ?: 'Session package',
            ],
            'unit_amount' => intval($package['price'] * 100),
        ],
        'quantity' => $num_purchases,
    ]];
    
    // Add tax line item
    if ($tax_amount > 0) {
        $line_items[] = [
            'price_data' => [
                'currency' => strtolower($currency),
                'product_data' => [
                    'name' => $tax_name,
                ],
                'unit_amount' => intval($tax_amount * 100),
            ],
            'quantity' => 1,
        ];
    }
    
    // Store purchase intent in session for callback
    $_SESSION['package_purchase'] = [
        'package_id' => $package_id,
        'athlete_ids' => $athlete_ids,
        'subtotal' => $subtotal,
        'tax_amount' => $tax_amount,
        'total' => $total
    ];
    
    // Create Stripe checkout session
    $checkout_session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $line_items,
        'mode' => 'payment',
        'success_url' => $domain . '/payment_success.php?session_id={CHECKOUT_SESSION_ID}&type=package',
        'cancel_url' => $domain . '/dashboard.php?page=packages&status=cancelled',
        'client_reference_id' => $user_id,
        'metadata' => [
            'package_id' => $package_id,
            'user_id' => $user_id,
            'athlete_ids' => implode(',', $athlete_ids),
        ]
    ]);
    
    // Redirect to Stripe checkout
    header("Location: " . $checkout_session->url);
    exit();
    
} catch (Exception $e) {
    error_log("Package purchase error: " . $e->getMessage());
    header("Location: dashboard.php?page=packages&error=purchase_failed");
    exit();
}
?>
