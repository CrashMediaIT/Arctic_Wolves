<?php
/**
 * Process Shop Checkout
 * Creates order and redirects to Stripe checkout
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

// Set security headers
setSecurityHeaders();

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    die('Invalid security token. Please try again.');
}

// Check if cart is empty
if (empty($_SESSION['shop_cart'])) {
    header('Location: shop.php');
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

// Load settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripeSecret = $settings['stripe_secret_key'] ?? '';
$currency = strtolower($settings['currency'] ?? 'cad');
$taxRate = floatval($settings['tax_rate'] ?? 13.00);
$taxName = $settings['tax_name'] ?? 'HST';

if (empty($stripeSecret)) {
    die("Stripe is not configured. Please contact administrator.");
}

\Stripe\Stripe::setApiKey($stripeSecret);

// Validate form data
$firstName = trim($_POST['first_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$phone = trim($_POST['phone'] ?? '');

$billingAddress1 = trim($_POST['billing_address1'] ?? '');
$billingAddress2 = trim($_POST['billing_address2'] ?? '');
$billingCity = trim($_POST['billing_city'] ?? '');
$billingState = trim($_POST['billing_state'] ?? '');
$billingPostal = trim($_POST['billing_postal'] ?? '');
$billingCountry = trim($_POST['billing_country'] ?? 'CA');

$shippingSame = isset($_POST['shipping_same']);
$shippingAddress1 = $shippingSame ? $billingAddress1 : trim($_POST['shipping_address1'] ?? '');
$shippingAddress2 = $shippingSame ? $billingAddress2 : trim($_POST['shipping_address2'] ?? '');
$shippingCity = $shippingSame ? $billingCity : trim($_POST['shipping_city'] ?? '');
$shippingState = $shippingSame ? $billingState : trim($_POST['shipping_state'] ?? '');
$shippingPostal = $shippingSame ? $billingPostal : trim($_POST['shipping_postal'] ?? '');
$shippingCountry = $shippingSame ? $billingCountry : trim($_POST['shipping_country'] ?? 'CA');

// Basic validation
if (empty($firstName) || empty($lastName) || empty($email)) {
    header('Location: shop_checkout.php?error=missing_info');
    exit();
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: shop_checkout.php?error=invalid_email');
    exit();
}

if (empty($billingAddress1) || empty($billingCity) || empty($billingState) || empty($billingPostal)) {
    header('Location: shop_checkout.php?error=missing_billing');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Calculate totals
    $subtotal = 0;
    foreach ($_SESSION['shop_cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    $taxAmount = round($subtotal * ($taxRate / 100), 2);
    $total = $subtotal + $taxAmount;
    
    // Generate order number
    $orderNumber = 'AW-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
    
    // Create order in database
    $stmt = $pdo->prepare("
        INSERT INTO shop_orders (
            order_number, user_id, customer_email, customer_first_name, customer_last_name, customer_phone,
            billing_address_line1, billing_address_line2, billing_city, billing_state, billing_postal_code, billing_country,
            shipping_address_line1, shipping_address_line2, shipping_city, shipping_state, shipping_postal_code, shipping_country,
            shipping_same_as_billing, subtotal, tax_amount, total, status, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending')
    ");
    
    $userId = $_SESSION['user_id'] ?? null;
    
    $stmt->execute([
        $orderNumber,
        $userId,
        $email,
        $firstName,
        $lastName,
        $phone,
        $billingAddress1,
        $billingAddress2,
        $billingCity,
        $billingState,
        $billingPostal,
        $billingCountry,
        $shippingAddress1,
        $shippingAddress2,
        $shippingCity,
        $shippingState,
        $shippingPostal,
        $shippingCountry,
        $shippingSame ? 1 : 0,
        $subtotal,
        $taxAmount,
        $total
    ]);
    
    $orderId = $pdo->lastInsertId();
    
    // Create order items
    $itemStmt = $pdo->prepare("
        INSERT INTO shop_order_items (order_id, product_id, product_name, product_sku, size, quantity, unit_price, total_price)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $lineItems = [];
    
    foreach ($_SESSION['shop_cart'] as $item) {
        // Fetch product SKU
        $skuStmt = $pdo->prepare("SELECT sku FROM merchandise_products WHERE id = ?");
        $skuStmt->execute([$item['product_id']]);
        $sku = $skuStmt->fetchColumn();
        
        $itemStmt->execute([
            $orderId,
            $item['product_id'],
            $item['name'],
            $sku,
            $item['size'],
            $item['quantity'],
            $item['price'],
            $item['price'] * $item['quantity']
        ]);
        
        // Build Stripe line item
        $productDescription = $item['name'];
        if (!empty($item['size'])) {
            $productDescription .= ' - Size: ' . $item['size'];
        }
        
        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => $item['name'],
                    'description' => !empty($item['size']) ? 'Size: ' . $item['size'] : null,
                ],
                'unit_amount' => intval($item['price'] * 100),
            ],
            'quantity' => $item['quantity'],
        ];
    }
    
    // Add tax line item
    if ($taxAmount > 0) {
        $lineItems[] = [
            'price_data' => [
                'currency' => $currency,
                'product_data' => [
                    'name' => $taxName,
                ],
                'unit_amount' => intval($taxAmount * 100),
            ],
            'quantity' => 1,
        ];
    }
    
    // Store order ID in session for callback
    $_SESSION['pending_shop_order_id'] = $orderId;
    
    // Create Stripe checkout session
    $domain = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    
    $checkoutSession = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => $lineItems,
        'mode' => 'payment',
        'success_url' => $domain . '/shop_success.php?session_id={CHECKOUT_SESSION_ID}',
        'cancel_url' => $domain . '/shop_cart.php?status=cancelled',
        'customer_email' => $email,
        'client_reference_id' => strval($orderId),
        'metadata' => [
            'order_id' => $orderId,
            'order_number' => $orderNumber,
        ],
    ]);
    
    // Update order with Stripe session ID
    $updateStmt = $pdo->prepare("UPDATE shop_orders SET stripe_session_id = ? WHERE id = ?");
    $updateStmt->execute([$checkoutSession->id, $orderId]);
    
    $pdo->commit();
    
    // Redirect to Stripe checkout
    header("Location: " . $checkoutSession->url);
    exit();
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Stripe API error during shop checkout: " . $e->getMessage());
    header('Location: shop_checkout.php?error=payment_failed');
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Shop checkout error: " . $e->getMessage());
    header('Location: shop_checkout.php?error=checkout_failed');
    exit();
}
