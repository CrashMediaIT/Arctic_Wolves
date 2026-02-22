<?php
/**
 * Process Shop Checkout
 * Creates order and redirects to Stripe checkout
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Handle ship_order action (from admin orders page)
if (isset($_GET['action']) && $_GET['action'] === 'ship_order') {
    header('Content-Type: application/json');
    
    // Check CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    // Check admin access
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'front_desk_staff'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $orderId = intval($_POST['order_id'] ?? 0);
    $carrier = trim($_POST['shipping_carrier'] ?? '');
    $trackingNumber = trim($_POST['tracking_number'] ?? '');
    $trackingUrl = trim($_POST['tracking_url'] ?? '');
    $fulfillmentNotes = trim($_POST['fulfillment_notes'] ?? '');
    
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit();
    }
    
    if (empty($carrier)) {
        echo json_encode(['success' => false, 'message' => 'Shipping carrier is required']);
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Update order with shipping info
        $stmt = $pdo->prepare("
            UPDATE shop_orders 
            SET status = 'shipped', 
                shipping_carrier = ?, 
                tracking_number = ?, 
                tracking_url = ?,
                shipped_at = NOW(),
                fulfillment_notes = ?
            WHERE id = ?
        ");
        $stmt->execute([$carrier, $trackingNumber ?: null, $trackingUrl ?: null, $fulfillmentNotes ?: null, $orderId]);
        
        // Record stock movements for each order item
        $itemsStmt = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $movementStmt = $pdo->prepare("
            INSERT INTO merchandise_stock_movements (product_id, size_id, movement_type, quantity_before, quantity_change, quantity_after, reference, notes, created_by)
            VALUES (?, ?, 'sale', ?, ?, ?, ?, ?, ?)
        ");
        
        // Fetch the order number for reference
        $orderStmt = $pdo->prepare("SELECT order_number FROM shop_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $orderNumber = $orderStmt->fetchColumn();
        
        foreach ($items as $item) {
            if (!empty($item['size'])) {
                // Get size_id and current quantity
                $sizeStmt = $pdo->prepare("SELECT id, quantity FROM merchandise_product_sizes WHERE product_id = ? AND size = ?");
                $sizeStmt->execute([$item['product_id'], $item['size']]);
                $sizeData = $sizeStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($sizeData) {
                    // Stock was already deducted at payment time (shop_success.php).
                    // Reconstruct pre-deduction quantity for the movement record.
                    $qtyBeforeSale = $sizeData['quantity'] + $item['quantity'];
                    $movementStmt->execute([
                        $item['product_id'],
                        $sizeData['id'],
                        $qtyBeforeSale,
                        -$item['quantity'],
                        $sizeData['quantity'],
                        'Order #' . $orderNumber,
                        'Shipped via ' . $carrier,
                        $_SESSION['user_id']
                    ]);
                }
            }
        }
        
        $pdo->commit();
        $ship_user_id = $_SESSION['user_id'] ?? 0;
        Auditor::log($pdo, $ship_user_id, 'update', 'shop_orders', $orderId, ['action' => 'order_shipped']);
        echo json_encode(['success' => true, 'message' => 'Order marked as shipped successfully!']);
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ErrorLogger::error("Ship order error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error shipping order: ' . $e->getMessage()]);
    }
    exit();
}

// Handle create_stallion_label action (create shipping label via Stallion Express)
if (isset($_GET['action']) && $_GET['action'] === 'create_stallion_label') {
    header('Content-Type: application/json');
    
    // Check CSRF
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    // Check access
    if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin', 'front_desk_staff'])) {
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit();
    }
    
    $orderId = intval($_POST['order_id'] ?? 0);
    
    if ($orderId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit();
    }
    
    try {
        require_once __DIR__ . '/lib/stallion_express.php';
        
        // Get Stallion settings
        $stallionSettings = getStallionSettings($pdo);
        
        if (!isStallionConfigured($stallionSettings)) {
            echo json_encode(['success' => false, 'message' => 'Stallion Express is not configured. Please set up the integration in System Tools.']);
            exit();
        }
        
        // Get order data
        $orderStmt = $pdo->prepare("SELECT * FROM shop_orders WHERE id = ?");
        $orderStmt->execute([$orderId]);
        $order = $orderStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$order) {
            echo json_encode(['success' => false, 'message' => 'Order not found']);
            exit();
        }
        
        // Decrypt customer data if encryption is available
        if (function_exists('decryptUserRows')) {
            $orderArr = decryptUserRows([$order]);
            $order = $orderArr[0];
        }
        
        // Get order items
        $itemsStmt = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
        $itemsStmt->execute([$orderId]);
        $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build overrides from form
        $overrides = [];
        if (!empty($_POST['weight'])) $overrides['weight'] = floatval($_POST['weight']);
        if (!empty($_POST['length'])) $overrides['length'] = floatval($_POST['length']);
        if (!empty($_POST['width'])) $overrides['width'] = floatval($_POST['width']);
        if (!empty($_POST['height'])) $overrides['height'] = floatval($_POST['height']);
        
        // Create shipment
        $result = createStallionShipment($pdo, $stallionSettings, $order, $items, $overrides);
        
        if ($result['success']) {
            // Update order status to processing
            $updateStmt = $pdo->prepare("UPDATE shop_orders SET status = 'processing' WHERE id = ? AND status IN ('paid', 'pending')");
            $updateStmt->execute([$orderId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Shipping label created successfully!',
                'tracking_number' => $result['tracking_number'] ?? '',
                'label_url' => $result['label_url'] ?? ''
            ]);
        } else {
            echo json_encode($result);
        }
    } catch (Exception $e) {
        ErrorLogger::error("Create Stallion label error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Error creating label: ' . $e->getMessage()]);
    }
    exit();
}

// Handle mark_label_printed action
if (isset($_GET['action']) && $_GET['action'] === 'mark_label_printed') {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    $labelId = intval($_POST['label_id'] ?? 0);
    
    if ($labelId > 0) {
        try {
            $stmt = $pdo->prepare("UPDATE stallion_shipping_labels SET status = 'printed' WHERE id = ? AND status = 'created'");
            $stmt->execute([$labelId]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid label ID']);
    }
    exit();
}

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
if (function_exists('decryptCredential')) { $stripeSecret = decryptCredential($stripeSecret); }
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
    
    // Encrypt customer PII fields before storing
    $enc_firstName = FieldEncryption::encrypt($firstName);
    $enc_lastName = FieldEncryption::encrypt($lastName);
    $enc_phone = $phone ? FieldEncryption::encrypt($phone) : null;
    $enc_billingAddress1 = $billingAddress1 ? FieldEncryption::encrypt($billingAddress1) : null;
    $enc_billingAddress2 = $billingAddress2 ? FieldEncryption::encrypt($billingAddress2) : null;
    $enc_billingCity = $billingCity ? FieldEncryption::encrypt($billingCity) : null;
    $enc_shippingAddress1 = $shippingAddress1 ? FieldEncryption::encrypt($shippingAddress1) : null;
    $enc_shippingAddress2 = $shippingAddress2 ? FieldEncryption::encrypt($shippingAddress2) : null;
    $enc_shippingCity = $shippingCity ? FieldEncryption::encrypt($shippingCity) : null;

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
        $enc_firstName,
        $enc_lastName,
        $enc_phone,
        $enc_billingAddress1,
        $enc_billingAddress2,
        $enc_billingCity,
        $billingState,
        $billingPostal,
        $billingCountry,
        $enc_shippingAddress1,
        $enc_shippingAddress2,
        $enc_shippingCity,
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
    
    // Create Stripe checkout session with secure protocol detection
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $domain = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
    
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
    
    $checkout_user_id = $_SESSION['user_id'] ?? 0;
    Auditor::log($pdo, $checkout_user_id, 'create', 'shop_orders', $orderId, ['action' => 'shop_order_created']);
    
    // Redirect to Stripe checkout
    header("Location: " . $checkoutSession->url);
    exit();
    
} catch (\Stripe\Exception\ApiErrorException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ErrorLogger::error("Stripe API error during shop checkout: " . $e->getMessage());
    header('Location: shop_checkout.php?error=payment_failed');
    exit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ErrorLogger::error("Shop checkout error: " . $e->getMessage());
    header('Location: shop_checkout.php?error=checkout_failed');
    exit();
}
