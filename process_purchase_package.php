<?php
// process_purchase_package.php - Handle Stripe checkout for package purchases
session_start();
require 'db_config.php';
require 'security.php';
require 'notifications.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripe_secret = $settings['stripe_secret_key'] ?? '';
if (function_exists('decryptCredential')) { $stripe_secret = decryptCredential($stripe_secret); }
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
    
    // Check for duplicate purchases — prevent re-ordering the same package
    $dup_placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
    $dup_check_stmt = $pdo->prepare("
        SELECT user_id FROM user_packages 
        WHERE package_id = ? AND user_id IN ($dup_placeholders) AND payment_status IN ('pending', 'paid')
    ");
    $dup_check_stmt->execute(array_merge([$package_id], $athlete_ids));
    $already_purchased = $dup_check_stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($already_purchased)) {
        // Redirect back to programs page — the inline "Already Registered" badge
        // replaces the register button automatically based on purchased_package_ids
        header("Location: dashboard.php?page=programs_camps&package_id=" . urlencode($package_id));
        exit();
    }
    
    // Calculate pricing
    $subtotal = $package['price'] * $num_purchases;
    
    // Calculate add-on costs for camp/multi-week packages
    $addon_total = 0;
    $selected_addon_ids = [];
    if (in_array($package['package_type'], ['camp', 'multi_week']) && isset($_POST['selected_addons']) && is_array($_POST['selected_addons'])) {
        $selected_addon_ids = array_map('intval', $_POST['selected_addons']);
        if (!empty($selected_addon_ids)) {
            $addon_placeholders = str_repeat('?,', count($selected_addon_ids) - 1) . '?';
            $addon_stmt = $pdo->prepare("SELECT id, price FROM camp_add_ons WHERE id IN ($addon_placeholders) AND package_id = ?");
            $addon_stmt->execute(array_merge($selected_addon_ids, [$package_id]));
            $addons = $addon_stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($addons as $addon) {
                $addon_total += $addon['price'] * $num_purchases;
            }
        }
    }
    
    $subtotal += $addon_total;
    $tax_amount = round($subtotal * ($tax_rate / 100), 2);
    $total = $subtotal + $tax_amount;
    
    // Build line item description
    $item_desc = $package['description'] ?: 'Session package';
    if (!empty($selected_addon_ids)) {
        $addon_names_stmt = $pdo->prepare("SELECT name FROM camp_add_ons WHERE id IN (" . str_repeat('?,', count($selected_addon_ids) - 1) . '?' . ") AND package_id = ?");
        $addon_names_stmt->execute(array_merge($selected_addon_ids, [$package_id]));
        $addon_names = $addon_names_stmt->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($addon_names)) {
            $item_desc .= ' (includes: ' . implode(', ', $addon_names) . ')';
        }
    }
    
    // Create line items for Stripe
    $line_items = [[
        'price_data' => [
            'currency' => strtolower($currency),
            'product_data' => [
                'name' => $package['name'],
                'description' => substr($item_desc, 0, 500),
            ],
            'unit_amount' => intval($package['price'] * 100),
        ],
        'quantity' => $num_purchases,
    ]];
    
    // Add add-on line items
    if (!empty($selected_addon_ids) && isset($addons)) {
        foreach ($addons as $addon) {
            if ($addon['price'] > 0) {
                $addon_name_stmt = $pdo->prepare("SELECT name FROM camp_add_ons WHERE id = ?");
                $addon_name_stmt->execute([$addon['id']]);
                $addon_name = $addon_name_stmt->fetchColumn();
                $line_items[] = [
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $addon_name ?: 'Add-on',
                        ],
                        'unit_amount' => intval($addon['price'] * 100),
                    ],
                    'quantity' => $num_purchases,
                ];
            }
        }
    }
    
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
        'total' => $total,
        'selected_addons' => $selected_addon_ids
    ];
    
    // Get user email for Stripe checkout pre-fill
    $email_stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $email_stmt->execute([$user_id]);
    $customer_email = $email_stmt->fetchColumn();
    
    // Create Stripe checkout session
    $stripe_params = [
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
            'selected_addons' => implode(',', $selected_addon_ids),
        ]
    ];
    if (!empty($customer_email)) {
        $stripe_params['customer_email'] = $customer_email;
    }
    $checkout_session = \Stripe\Checkout\Session::create($stripe_params);
    
    // Redirect to Stripe checkout
    header("Location: " . $checkout_session->url);
    exit();
    
} catch (Exception $e) {
    ErrorLogger::error("Package purchase error: " . $e->getMessage());
    header("Location: dashboard.php?page=packages&error=purchase_failed");
    exit();
}
?>
