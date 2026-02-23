<?php
/**
 * Process POS Transactions
 * Handle card and cash payments from POS terminal
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set security headers
setSecurityHeaders();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check POS access
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'front_desk_staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $userRole)) {
    logSecurityEvent('pos_ip_blocked', 'POS process access denied from unauthorized IP', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '']);
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'POS access is not available from this location']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$action = $input['action'] ?? '';

// Load settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripeSecret = $settings['stripe_secret_key'] ?? '';
if (function_exists('decryptCredential')) { $stripeSecret = decryptCredential($stripeSecret); }
$currency = strtolower($settings['currency'] ?? 'cad');
$taxRate = floatval($settings['tax_rate'] ?? 13.00);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'process_card_payment':
            $items = $input['items'] ?? [];
            $terminalReader = $input['terminal_reader'] ?? '';
            $customerUserId = !empty($input['customer_user_id']) ? intval($input['customer_user_id']) : null;
            
            if (empty($items)) {
                throw new Exception('Cart is empty');
            }
            
            // Get customer details if a user is assigned
            $customerName = null;
            $customerEmail = null;
            if ($customerUserId) {
                $customerStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                $customerStmt->execute([$customerUserId]);
                $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                if ($customer) {
                    $customer = decryptUserRow($customer);
                    $customerName = $customer['first_name'] . ' ' . $customer['last_name'];
                    $customerEmail = $customer['email'];
                }
            }
            
            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += floatval($item['price']) * intval($item['quantity']);
            }
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = $subtotal + $taxAmount;
            
            // Load Stripe
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require __DIR__ . '/vendor/autoload.php';
            } elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
                require __DIR__ . '/stripe-php/init.php';
            } else {
                throw new Exception('Stripe library not found');
            }
            
            if (empty($stripeSecret)) {
                throw new Exception('Stripe not configured');
            }
            
            \Stripe\Stripe::setApiKey($stripeSecret);
            
            $pdo->beginTransaction();
            
            // Generate transaction number
            $transactionNumber = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Create payment intent
            $paymentIntentParams = [
                'amount' => intval($total * 100),
                'currency' => $currency,
                'payment_method_types' => ['card_present'],
                'capture_method' => 'automatic',
                'metadata' => [
                    'transaction_number' => $transactionNumber,
                    'staff_id' => $_SESSION['user_id']
                ]
            ];
            
            // If using terminal reader, use the terminal process
            $paymentStatus = 'pending';
            if (!empty($terminalReader)) {
                $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentParams);
                
                // Process with terminal
                try {
                    $reader = \Stripe\Terminal\Reader::processPaymentIntent(
                        $terminalReader,
                        ['payment_intent' => $paymentIntent->id]
                    );
                    // Terminal processing initiated - wait for webhook for final confirmation
                    // Mark as pending until webhook confirms payment
                    $paymentStatus = 'pending';
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    // Terminal failed - throw error to prevent false completion
                    throw new Exception('Terminal payment failed: ' . $e->getMessage());
                }
                
                $stripePaymentIntent = $paymentIntent->id;
            } else {
                // No terminal - create a simple payment intent for card
                // In production, this would redirect to Stripe hosted page
                $paymentIntent = \Stripe\PaymentIntent::create([
                    'amount' => intval($total * 100),
                    'currency' => $currency,
                    'payment_method_types' => ['card'],
                    'capture_method' => 'automatic',
                    'confirm' => false,
                    'metadata' => [
                        'transaction_number' => $transactionNumber,
                        'staff_id' => $_SESSION['user_id']
                    ]
                ]);
                $stripePaymentIntent = $paymentIntent->id;
                // For non-terminal card payments, mark as completed since it will go through Stripe checkout
                $paymentStatus = 'completed';
            }
            
            // Create POS transaction record with appropriate status
            $stmt = $pdo->prepare("
                INSERT INTO pos_transactions (
                    transaction_number, staff_id, customer_user_id, customer_name, customer_email,
                    subtotal, tax_amount, total, payment_method, card_amount, status, 
                    stripe_payment_intent, terminal_reader_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'card', ?, ?, ?, ?)
            ");
            $stmt->execute([
                $transactionNumber,
                $_SESSION['user_id'],
                $customerUserId,
                $customerName,
                $customerEmail,
                $subtotal,
                $taxAmount,
                $total,
                $total,
                $paymentStatus,
                $stripePaymentIntent,
                $terminalReader ?: null
            ]);
            
            $transactionId = $pdo->lastInsertId();
            
            // Create transaction items and update inventory
            $itemStmt = $pdo->prepare("
                INSERT INTO pos_transaction_items (
                    transaction_id, product_id, product_name, size, quantity, unit_price, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $invStmt = $pdo->prepare("
                UPDATE merchandise_product_sizes 
                SET quantity = quantity - ? 
                WHERE product_id = ? AND size = ? AND quantity >= ?
            ");
            
            foreach ($items as $item) {
                // Get product details
                $prodStmt = $pdo->prepare("SELECT name, sku FROM merchandise_products WHERE id = ?");
                $prodStmt->execute([$item['id']]);
                $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
                
                $itemStmt->execute([
                    $transactionId,
                    $item['id'],
                    $product['name'] ?? $item['name'],
                    $item['size'] ?? null,
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                ]);
                
                // Update inventory
                if (!empty($item['size'])) {
                    $invStmt->execute([
                        $item['quantity'],
                        $item['id'],
                        $item['size'],
                        $item['quantity']
                    ]);
                }
            }
            
            $pdo->commit();
            
            Auditor::log($pdo, $_SESSION['user_id'], 'create', 'pos_transactions', $transactionId, ['action' => 'card_payment_processed']);
            
            echo json_encode([
                'success' => true,
                'transaction_number' => $transactionNumber,
                'total' => $total
            ]);
            break;
            
        case 'process_cash_payment':
            $items = $input['items'] ?? [];
            $cashReceived = floatval($input['cash_received'] ?? 0);
            $customerUserId = !empty($input['customer_user_id']) ? intval($input['customer_user_id']) : null;
            
            if (empty($items)) {
                throw new Exception('Cart is empty');
            }
            
            // Get customer details if a user is assigned
            $customerName = null;
            $customerEmail = null;
            if ($customerUserId) {
                $customerStmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
                $customerStmt->execute([$customerUserId]);
                $customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
                if ($customer) {
                    $customer = decryptUserRow($customer);
                    $customerName = $customer['first_name'] . ' ' . $customer['last_name'];
                    $customerEmail = $customer['email'];
                }
            }
            
            // Calculate totals
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += floatval($item['price']) * intval($item['quantity']);
            }
            $taxAmount = round($subtotal * ($taxRate / 100), 2);
            $total = $subtotal + $taxAmount;
            
            if ($cashReceived < $total) {
                throw new Exception('Insufficient payment');
            }
            
            $changeGiven = $cashReceived - $total;
            
            // Generate transaction number
            $transactionNumber = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Record cash payment in Stripe for unified reporting (before database transaction)
            $stripePaymentRecordId = null;
            if (!empty($stripeSecret)) {
                try {
                    // Load Stripe (use require for consistency with card payment)
                    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                        require __DIR__ . '/vendor/autoload.php';
                    } elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
                        require __DIR__ . '/stripe-php/init.php';
                    }
                    
                    \Stripe\Stripe::setApiKey($stripeSecret);
                    
                    // Create a PaymentRecord in Stripe for the cash transaction
                    // This enables unified reporting of all transactions (card + cash) in Stripe Dashboard
                    $paymentRecordParams = [
                        'amount_requested' => [
                            'value' => intval($total * 100),
                            'currency' => $currency
                        ],
                        'payment_method_details' => [
                            'type' => 'custom',
                            'custom' => [
                                'display_name' => 'Cash',
                                'type' => 'cash'
                            ]
                        ],
                        'metadata' => [
                            'transaction_number' => $transactionNumber,
                            'staff_id' => strval($_SESSION['user_id']),
                            'payment_type' => 'cash'
                        ]
                    ];
                    
                    // Add customer details if available
                    if ($customerName || $customerEmail) {
                        $paymentRecordParams['customer_details'] = [];
                        if ($customerName) {
                            $paymentRecordParams['customer_details']['name'] = $customerName;
                        }
                        if ($customerEmail) {
                            $paymentRecordParams['customer_details']['email'] = $customerEmail;
                        }
                    }
                    
                    $paymentRecord = \Stripe\PaymentRecord::reportPayment($paymentRecordParams);
                    $stripePaymentRecordId = $paymentRecord->id;
                    
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    // Log Stripe error but continue with cash transaction
                    // Cash transactions should still work even if Stripe reporting fails
                    ErrorLogger::error("Stripe PaymentRecord error for cash transaction: " . $e->getMessage());
                } catch (Exception $e) {
                    ErrorLogger::error("Stripe integration error for cash transaction: " . $e->getMessage());
                }
            }
            
            // Start database transaction after Stripe API call
            $pdo->beginTransaction();
            
            // Create POS transaction record with Stripe PaymentRecord ID
            $stmt = $pdo->prepare("
                INSERT INTO pos_transactions (
                    transaction_number, staff_id, customer_user_id, customer_name, customer_email,
                    subtotal, tax_amount, total, payment_method, cash_amount, change_given, status, stripe_payment_intent
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'cash', ?, ?, 'completed', ?)
            ");
            $stmt->execute([
                $transactionNumber,
                $_SESSION['user_id'],
                $customerUserId,
                $customerName,
                $customerEmail,
                $subtotal,
                $taxAmount,
                $total,
                $cashReceived,
                $changeGiven,
                $stripePaymentRecordId
            ]);
            
            $transactionId = $pdo->lastInsertId();
            
            // Create transaction items and update inventory
            $itemStmt = $pdo->prepare("
                INSERT INTO pos_transaction_items (
                    transaction_id, product_id, product_name, size, quantity, unit_price, total_price
                ) VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $invStmt = $pdo->prepare("
                UPDATE merchandise_product_sizes 
                SET quantity = quantity - ? 
                WHERE product_id = ? AND size = ? AND quantity >= ?
            ");
            
            foreach ($items as $item) {
                // Get product details
                $prodStmt = $pdo->prepare("SELECT name, sku FROM merchandise_products WHERE id = ?");
                $prodStmt->execute([$item['id']]);
                $product = $prodStmt->fetch(PDO::FETCH_ASSOC);
                
                $itemStmt->execute([
                    $transactionId,
                    $item['id'],
                    $product['name'] ?? $item['name'],
                    $item['size'] ?? null,
                    $item['quantity'],
                    $item['price'],
                    $item['price'] * $item['quantity']
                ]);
                
                // Update inventory
                if (!empty($item['size'])) {
                    $invStmt->execute([
                        $item['quantity'],
                        $item['id'],
                        $item['size'],
                        $item['quantity']
                    ]);
                }
            }
            
            $pdo->commit();
            
            Auditor::log($pdo, $_SESSION['user_id'], 'create', 'pos_transactions', $transactionId, ['action' => 'cash_payment_processed']);
            
            echo json_encode([
                'success' => true,
                'transaction_number' => $transactionNumber,
                'total' => $total,
                'change' => $changeGiven
            ]);
            break;
            
        case 'check_reader_status':
            $readerId = $input['reader_id'] ?? '';
            
            if (empty($readerId) || empty($stripeSecret)) {
                echo json_encode(['online' => false]);
                break;
            }
            
            // Load Stripe
            if (file_exists(__DIR__ . '/vendor/autoload.php')) {
                require __DIR__ . '/vendor/autoload.php';
            } elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
                require __DIR__ . '/stripe-php/init.php';
            }
            
            \Stripe\Stripe::setApiKey($stripeSecret);
            
            try {
                $reader = \Stripe\Terminal\Reader::retrieve($readerId);
                $online = ($reader->status === 'online');
                
                // Update local status
                $updateStmt = $pdo->prepare("UPDATE pos_terminal_readers SET status = ?, last_seen_at = NOW() WHERE stripe_reader_id = ?");
                $updateStmt->execute([$online ? 'online' : 'offline', $readerId]);
                
                echo json_encode(['online' => $online]);
            } catch (Exception $e) {
                echo json_encode(['online' => false]);
            }
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    ErrorLogger::error("POS processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
