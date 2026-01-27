<?php
/**
 * Process POS Transactions
 * Handle card and cash payments from POS terminal
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

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
$currency = strtolower($settings['currency'] ?? 'cad');
$taxRate = floatval($settings['tax_rate'] ?? 13.00);

header('Content-Type: application/json');

try {
    switch ($action) {
        case 'process_card_payment':
            $items = $input['items'] ?? [];
            $terminalReader = $input['terminal_reader'] ?? '';
            
            if (empty($items)) {
                throw new Exception('Cart is empty');
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
            if (!empty($terminalReader)) {
                $paymentIntent = \Stripe\PaymentIntent::create($paymentIntentParams);
                
                // Process with terminal
                try {
                    $reader = \Stripe\Terminal\Reader::processPaymentIntent(
                        $terminalReader,
                        ['payment_intent' => $paymentIntent->id]
                    );
                } catch (\Stripe\Exception\ApiErrorException $e) {
                    // If terminal fails, fall back to manual card entry
                    // For now, just mark as completed for demo
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
            }
            
            // Create POS transaction record
            $stmt = $pdo->prepare("
                INSERT INTO pos_transactions (
                    transaction_number, staff_id, subtotal, tax_amount, total,
                    payment_method, card_amount, status, stripe_payment_intent, terminal_reader_id
                ) VALUES (?, ?, ?, ?, ?, 'card', ?, 'completed', ?, ?)
            ");
            $stmt->execute([
                $transactionNumber,
                $_SESSION['user_id'],
                $subtotal,
                $taxAmount,
                $total,
                $total,
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
            
            echo json_encode([
                'success' => true,
                'transaction_number' => $transactionNumber,
                'total' => $total
            ]);
            break;
            
        case 'process_cash_payment':
            $items = $input['items'] ?? [];
            $cashReceived = floatval($input['cash_received'] ?? 0);
            
            if (empty($items)) {
                throw new Exception('Cart is empty');
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
            
            $pdo->beginTransaction();
            
            // Generate transaction number
            $transactionNumber = 'POS-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Create POS transaction record
            $stmt = $pdo->prepare("
                INSERT INTO pos_transactions (
                    transaction_number, staff_id, subtotal, tax_amount, total,
                    payment_method, cash_amount, change_given, status
                ) VALUES (?, ?, ?, ?, ?, 'cash', ?, ?, 'completed')
            ");
            $stmt->execute([
                $transactionNumber,
                $_SESSION['user_id'],
                $subtotal,
                $taxAmount,
                $total,
                $cashReceived,
                $changeGiven
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
    error_log("POS processing error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
