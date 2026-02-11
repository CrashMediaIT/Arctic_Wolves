<?php
// process_refunds.php - Handle refund operations
session_start();
require 'db_config.php';
require 'security.php';
require 'notifications.php';
require 'mailer.php';

setSecurityHeaders();

/**
 * Fetch refunds with optional filters
 * @param PDO $pdo Database connection
 * @param string $start_date Start date for filter
 * @param string $end_date End date for filter
 * @param string $user_search Optional user search term
 * @return array Array of refund records
 */
function fetchRefundsWithFilters($pdo, $start_date, $end_date, $user_search = '') {
    $query = "
        SELECT r.*, u.email, u.first_name, u.last_name,
               s.title as session_name, s.session_date,
               admin.first_name as admin_first_name, admin.last_name as admin_last_name
        FROM refunds r
        JOIN users u ON r.user_id = u.id
        LEFT JOIN bookings b ON r.booking_id = b.id
        LEFT JOIN sessions s ON b.session_id = s.id
        LEFT JOIN users admin ON r.refunded_by = admin.id
        WHERE DATE(r.refund_date) BETWEEN ? AND ?
    ";
    $params = [$start_date, $end_date];
    
    // Add user search filter if provided (search by email only since names are encrypted)
    if (!empty($user_search)) {
        $query .= " AND u.email LIKE ?";
        $search_param = "%$user_search%";
        array_push($params, $search_param);
    }
    
    $query .= " ORDER BY r.refund_date DESC";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $results = decryptUserRows($stmt->fetchAll());
    // Build processed_by_name from decrypted fields
    foreach ($results as &$row) {
        $row['processed_by_name'] = (!empty($row['admin_first_name'])) ? $row['admin_first_name'] . ' ' . $row['admin_last_name'] : null;
    }
    unset($row);
    return $results;
}

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_upcoming_sessions':
            // Include both regular sessions and template-based sessions from products catalog
            $stmt = $pdo->query("
                SELECT id, title, session_date, session_time,
                       'session' as source_type, NULL as template_id, NULL as date_id
                FROM sessions
                WHERE session_date >= CURDATE()
                
                UNION ALL
                
                SELECT CONCAT('template_', tst.id, '_', tsd.id) as id,
                       tst.name as title,
                       DATE(tsd.session_date) as session_date,
                       TIME(tsd.session_date) as session_time,
                       'template' as source_type, tst.id as template_id, tsd.id as date_id
                FROM training_session_templates tst
                INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
                WHERE tst.is_active = 1
                AND tsd.session_date >= CURDATE()
                AND tsd.session_id IS NULL
                
                ORDER BY session_date ASC, session_time ASC
                LIMIT 100
            ");
            $sessions = $stmt->fetchAll();
            echo json_encode(['success' => true, 'sessions' => $sessions]);
            break;
            
        case 'search_bookings':
            $email = $_GET['email'] ?? '';
            $session_id = $_GET['session_id'] ?? '';
            $start_date = $_GET['start_date'] ?? '';
            $end_date = $_GET['end_date'] ?? '';
            
            $query = "
                SELECT b.*, u.email, u.first_name, u.last_name,
                       s.session_name, s.session_date, s.session_time,
                       bf.first_name as athlete_first_name, bf.last_name as athlete_last_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN sessions s ON b.session_id = s.id
                LEFT JOIN users bf ON b.booked_for_user_id = bf.id
                WHERE b.status = 'paid'
            ";
            
            $params = [];
            
            if ($email) {
                $query .= " AND u.email LIKE ?";
                $params[] = "%$email%";
            }
            
            if ($session_id) {
                $query .= " AND b.session_id = ?";
                $params[] = $session_id;
            }
            
            if ($start_date && $end_date) {
                $query .= " AND s.session_date BETWEEN ? AND ?";
                $params[] = $start_date;
                $params[] = $end_date;
            }
            
            $query .= " ORDER BY s.session_date DESC LIMIT 100";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            $bookings = $stmt->fetchAll();
            $bookings = decryptUserRows($bookings);
            // Build athlete_name from decrypted fields
            foreach ($bookings as &$b) {
                $b['athlete_name'] = (!empty($b['athlete_first_name'])) ? $b['athlete_first_name'] . ' ' . $b['athlete_last_name'] : null;
            }
            unset($b);
            
            echo json_encode(['success' => true, 'bookings' => $bookings]);
            break;
            
        case 'process_refund':
            checkCsrfToken();
            
            $booking_id = intval($_POST['booking_id']);
            $refund_amount = floatval($_POST['refund_amount']);
            $reason = trim($_POST['reason']);
            $method = $_POST['method'] ?? 'refund'; // 'refund', 'credit', or 'exchange'
            $exchange_session_id = isset($_POST['exchange_session_id']) ? intval($_POST['exchange_session_id']) : null;
            
            // Get booking details
            $booking_stmt = $pdo->prepare("
                SELECT b.*, u.email, u.first_name, s.title as session_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                LEFT JOIN sessions s ON b.session_id = s.id
                WHERE b.id = ? AND b.status = 'paid'
            ");
            $booking_stmt->execute([$booking_id]);
            $booking = $booking_stmt->fetch();
            $booking = decryptUserRow($booking);
            
            if (!$booking) {
                throw new Exception('Booking not found or already refunded');
            }
            
            if ($refund_amount > $booking['amount_paid']) {
                throw new Exception('Amount cannot exceed paid amount');
            }
            
            $stripe_refund_id = null;
            $credit_amount = 0;
            
            // Handle different refund methods
            if ($method === 'refund') {
                // Standard Stripe refund
                $stripe_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'stripe_secret_key'");
                $stripe_secret = $stripe_stmt->fetchColumn();
                
                if (empty($stripe_secret)) {
                    throw new Exception('Stripe not configured');
                }
                
                // Process Stripe refund
                if (!empty($booking['stripe_session_id'])) {
                    $refund_result = processStripeRefund($booking['stripe_session_id'], $refund_amount, $stripe_secret);
                    
                    if (!$refund_result['success']) {
                        throw new Exception('Stripe refund failed: ' . $refund_result['message']);
                    }
                    
                    $stripe_refund_id = $refund_result['refund_id'];
                }
                
            } elseif ($method === 'credit') {
                // Issue store credit instead of refund
                $credit_amount = $refund_amount;
                
                // Get credit expiry setting
                $expiry_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'credit_expiry_days'");
                $expiry_days = intval($expiry_stmt->fetchColumn() ?: 365);
                $expiry_date = date('Y-m-d', strtotime("+$expiry_days days"));
                
                // Create user credit
                $credit_stmt = $pdo->prepare("
                    INSERT INTO user_credits (user_id, credit_amount, credit_source, remaining_amount, expiry_date, notes, created_at)
                    VALUES (?, ?, 'refund', ?, ?, ?, NOW())
                ");
                $credit_stmt->execute([
                    $booking['user_id'],
                    $credit_amount,
                    $credit_amount,
                    $expiry_date,
                    "Credit issued for booking #$booking_id: $reason"
                ]);
                
            } elseif ($method === 'exchange') {
                // Exchange for different session
                if (empty($exchange_session_id)) {
                    throw new Exception('Exchange session not specified');
                }
                
                $actual_session_id = $exchange_session_id;
                
                // Check if this is a template-based session (format: template_{template_id}_{date_id})
                if (strpos($exchange_session_id, 'template_') === 0) {
                    // Extract template_id and date_id
                    $parts = explode('_', $exchange_session_id);
                    if (count($parts) !== 3) {
                        throw new Exception('Invalid template session format');
                    }
                    $template_id = intval($parts[1]);
                    $date_id = intval($parts[2]);
                    
                    // Get template and date information
                    $stmt = $pdo->prepare("
                        SELECT tst.*, tsd.session_date, tsd.team_id,
                               COALESCE(tsd.max_participants, tst.max_participants) as max_participants
                        FROM training_session_templates tst
                        INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id
                        WHERE tst.id = ? AND tsd.id = ? AND tst.is_active = 1 AND tsd.is_active = 1
                    ");
                    $stmt->execute([$template_id, $date_id]);
                    $template_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$template_data) {
                        throw new Exception('Template session not found or inactive');
                    }
                    
                    // Create a session record from the template
                    $stmt = $pdo->prepare("
                        INSERT INTO sessions (
                            session_type_id, coach_id, location_id, title, description,
                            session_date, session_time, duration_minutes, price, max_participants,
                            team_id, status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'scheduled', NOW())
                    ");
                    
                    // Parse session_date and validate
                    $timestamp = strtotime($template_data['session_date']);
                    if ($timestamp === false) {
                        throw new Exception('Invalid session date format');
                    }
                    $session_date = date('Y-m-d', $timestamp);
                    $session_time = date('H:i:s', $timestamp);
                    
                    $stmt->execute([
                        $template_data['session_type_id'],
                        $template_data['coach_id'],
                        $template_data['location_id'],
                        $template_data['name'],
                        $template_data['description'],
                        $session_date,
                        $session_time,
                        $template_data['duration_minutes'],
                        $template_data['price'],
                        $template_data['max_participants'],
                        $template_data['team_id']
                    ]);
                    
                    $actual_session_id = $pdo->lastInsertId();
                    
                    // Link the training_session_date to this new session
                    $stmt = $pdo->prepare("UPDATE training_session_dates SET session_id = ? WHERE id = ?");
                    $stmt->execute([$actual_session_id, $date_id]);
                    
                    $exchange_session = [
                        'title' => $template_data['name'],
                        'price' => $template_data['price']
                    ];
                } else {
                    // Regular session - validate it exists
                    $session_check = $pdo->prepare("SELECT title, price FROM sessions WHERE id = ?");
                    $session_check->execute([$actual_session_id]);
                    $exchange_session = $session_check->fetch();
                    
                    if (!$exchange_session) {
                        throw new Exception('Exchange session not found');
                    }
                }
                
                // Create new booking for exchange session
                $exchange_booking = $pdo->prepare("
                    INSERT INTO bookings (user_id, session_id, amount_paid, original_price, tax_amount, status, booked_for_user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, 'paid', ?, NOW())
                ");
                $exchange_booking->execute([
                    $booking['user_id'],
                    $actual_session_id,
                    0, // No new payment
                    $exchange_session['price'],
                    0,
                    $booking['booked_for_user_id']
                ]);
            }
            
            // Create refund record
            $stmt = $pdo->prepare("
                INSERT INTO refunds (booking_id, user_id, refunded_by, refund_type, original_amount, refund_amount, credit_amount, exchange_session_id, refund_reason, stripe_refund_id, status, refund_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed', NOW())
            ");
            $stmt->execute([
                $booking_id,
                $booking['user_id'],
                $user_id,
                $method,
                $booking['amount_paid'],
                $method === 'refund' ? $refund_amount : 0,
                $credit_amount,
                $method === 'exchange' ? $actual_session_id : null,
                $reason,
                $stripe_refund_id
            ]);
            
            $refund_id = $pdo->lastInsertId();
            
            // Update booking status
            $pdo->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?")->execute([$booking_id]);
            
            // Link refund to user credit if applicable
            if ($method === 'credit') {
                $pdo->prepare("UPDATE user_credits SET refund_id = ? WHERE user_id = ? AND refund_id IS NULL ORDER BY id DESC LIMIT 1")
                    ->execute([$refund_id, $booking['user_id']]);
            }
            
            // If package credit purchase, handle appropriately
            if ($booking['payment_type'] === 'package') {
                $package_stmt = $pdo->prepare("SELECT * FROM user_package_credits WHERE booking_id = ?");
                $package_stmt->execute([$booking_id]);
                $package_credit = $package_stmt->fetch();
                
                if ($package_credit && $method === 'refund') {
                    // Remove unused credits on full refund
                    $pdo->prepare("DELETE FROM user_package_credits WHERE booking_id = ?")->execute([$booking_id]);
                }
            }
            
            // Send notification based on method
            $expiry_text = isset($expiry_date) ? date('M j, Y', strtotime($expiry_date)) : '';
            $notification_messages = [
                'refund' => "Your refund of $" . number_format($refund_amount, 2) . " has been processed.",
                'credit' => "You have been issued $" . number_format($credit_amount, 2) . " in store credit" . ($expiry_text ? " (expires $expiry_text)" : "") . ".",
                'exchange' => "Your booking has been exchanged for a different session."
            ];
            
            createNotification(
                $pdo,
                $booking['user_id'],
                'refund',
                ucfirst($method) . ' Processed',
                $notification_messages[$method] . " Reason: $reason",
                $method === 'credit' ? "dashboard.php?page=user_credits" : "dashboard.php?page=payment_history",
                false
            );
            
            // Send email
            $exchange_session_name = '';
            if ($method === 'exchange' && isset($exchange_session)) {
                $exchange_session_name = $exchange_session['title'];
            }
            sendRefundEmail(
                $booking['email'], 
                $booking['first_name'], 
                $refund_amount, 
                $credit_amount, 
                $booking['session_name'], 
                $reason, 
                $method,
                $expiry_text,
                $exchange_session_name
            );
            
            echo json_encode([
                'success' => true, 
                'message' => ucfirst($method) . ' processed successfully', 
                'refund_id' => $refund_id,
                'method' => $method
            ]);
            break;
            
        case 'list_refunds':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            $user_search = trim($_GET['user_search'] ?? '');
            
            $refunds = fetchRefundsWithFilters($pdo, $start_date, $end_date, $user_search);
            
            echo json_encode(['success' => true, 'refunds' => $refunds]);
            break;
            
        case 'export_refunds':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            $user_search = trim($_GET['user_search'] ?? '');
            
            $refunds = fetchRefundsWithFilters($pdo, $start_date, $end_date, $user_search);
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="refunds_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Customer', 'Email', 'Session', 'Original Amount', 'Refund Amount', 'Type', 'Reason', 'Processed By']);
            
            foreach ($refunds as $refund) {
                fputcsv($output, [
                    date('Y-m-d', strtotime($refund['refund_date'])),
                    $refund['first_name'] . ' ' . $refund['last_name'],
                    $refund['email'],
                    $refund['session_name'] ?: 'N/A',
                    '$' . number_format($refund['original_amount'], 2),
                    '$' . number_format($refund['refund_amount'], 2),
                    ucfirst($refund['status']),
                    $refund['refund_reason'],
                    $refund['processed_by_name']
                ]);
            }
            
            fclose($output);
            exit;
            
        case 'create':
            // Create a new credit or refund entry
            checkCsrfToken();
            
            $target_user_id = intval($_POST['user_id']);
            $type = $_POST['type'] ?? 'credit'; // 'credit' or 'refund'
            $amount = floatval($_POST['amount']);
            $reason = trim($_POST['reason']);
            $booking_id = !empty($_POST['booking_id']) ? intval($_POST['booking_id']) : null;
            $auto_approve = isset($_POST['auto_approve']) && $_POST['auto_approve'] == '1';
            
            if ($target_user_id <= 0 || $amount <= 0 || empty($reason)) {
                throw new Exception('Please fill in all required fields');
            }
            
            // Verify user exists
            $user_check = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $user_check->execute([$target_user_id]);
            if (!$user_check->fetch()) {
                throw new Exception('Invalid user selected');
            }
            
            // Generate reference number using cryptographically secure random
            $reference_number = strtoupper($type[0]) . 'R-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if reference exists, regenerate if needed
            $ref_check = $pdo->prepare("SELECT id FROM credits_refunds WHERE reference_number = ?");
            $ref_check->execute([$reference_number]);
            while ($ref_check->fetch()) {
                $reference_number = strtoupper($type[0]) . 'R-' . date('Ymd') . '-' . str_pad(random_int(1, 9999), 4, '0', STR_PAD_LEFT);
                $ref_check->execute([$reference_number]);
            }
            
            // Determine initial status
            $status = $auto_approve ? 'completed' : 'pending';
            
            // Insert the credit/refund record
            $stmt = $pdo->prepare("
                INSERT INTO credits_refunds (user_id, transaction_type, amount, reason, booking_id, reference_number, status, processed_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $target_user_id,
                $type,
                $amount,
                $reason,
                $booking_id,
                $reference_number,
                $status,
                $user_id
            ]);
            
            // If auto-approved and it's a credit, add to user's credit balance
            if ($auto_approve && $type === 'credit') {
                // Get credit expiry setting
                $expiry_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'credit_expiry_days'");
                $expiry_days = intval($expiry_stmt->fetchColumn() ?: 365);
                $expiry_date = date('Y-m-d', strtotime("+$expiry_days days"));
                
                // Insert into user_credits
                $credit_stmt = $pdo->prepare("
                    INSERT INTO user_credits (user_id, credit_amount, credit_source, remaining_amount, expiry_date, notes, created_at)
                    VALUES (?, ?, 'manual', ?, ?, ?, NOW())
                ");
                $credit_stmt->execute([
                    $target_user_id,
                    $amount,
                    $amount,
                    $expiry_date,
                    "Manual credit: $reason"
                ]);
            }
            
            // Check if this is an AJAX request
            $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
            
            if ($isAjax) {
                echo json_encode(['success' => true, 'message' => ucfirst($type) . ' issued successfully!']);
                exit;
            }
            
            // Redirect back to credits page with success message
            header("Location: dashboard.php?page=accounting_credits&status=success");
            exit;
        
        case 'approve':
            checkCsrfToken();
            $credit_id = intval($_POST['id']);
            
            if ($credit_id <= 0) {
                throw new Exception('Invalid credit/refund ID');
            }
            
            // Get the credit/refund details
            $stmt = $pdo->prepare("SELECT * FROM credits_refunds WHERE id = ?");
            $stmt->execute([$credit_id]);
            $credit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$credit) {
                throw new Exception('Credit/refund not found');
            }
            
            if ($credit['status'] !== 'pending') {
                throw new Exception('This credit/refund has already been processed');
            }
            
            // Update status to completed
            $update_stmt = $pdo->prepare("UPDATE credits_refunds SET status = 'completed', processed_at = NOW() WHERE id = ?");
            $update_stmt->execute([$credit_id]);
            
            // If it's a credit, add to user's credit balance
            if ($credit['transaction_type'] === 'credit') {
                $expiry_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'credit_expiry_days'");
                $expiry_days = intval($expiry_stmt->fetchColumn() ?: 365);
                $expiry_date = date('Y-m-d', strtotime("+$expiry_days days"));
                
                $credit_stmt = $pdo->prepare("
                    INSERT INTO user_credits (user_id, credit_amount, credit_source, remaining_amount, expiry_date, notes, created_at)
                    VALUES (?, ?, 'approved_request', ?, ?, ?, NOW())
                ");
                $credit_stmt->execute([
                    $credit['user_id'],
                    $credit['amount'],
                    $credit['amount'],
                    $expiry_date,
                    "Approved credit: " . $credit['reason']
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Credit/refund approved successfully!']);
            break;
        
        case 'reject':
            checkCsrfToken();
            $credit_id = intval($_POST['id']);
            
            if ($credit_id <= 0) {
                throw new Exception('Invalid credit/refund ID');
            }
            
            // Verify the credit/refund exists and is pending
            $stmt = $pdo->prepare("SELECT id, status FROM credits_refunds WHERE id = ?");
            $stmt->execute([$credit_id]);
            $credit = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$credit) {
                throw new Exception('Credit/refund not found');
            }
            
            if ($credit['status'] !== 'pending') {
                throw new Exception('This credit/refund has already been processed');
            }
            
            // Update status to rejected
            $update_stmt = $pdo->prepare("UPDATE credits_refunds SET status = 'rejected', processed_at = NOW() WHERE id = ?");
            $update_stmt->execute([$credit_id]);
            
            echo json_encode(['success' => true, 'message' => 'Credit/refund rejected!']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    // Check if this is a form submission (not AJAX)
    if (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') === false && isset($_POST['action']) && $_POST['action'] === 'create') {
        header("Location: dashboard.php?page=accounting_credits&status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Process Stripe refund
 */
function processStripeRefund($payment_intent_id, $amount, $secret_key) {
    // Load Stripe library
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
    } elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
        require_once __DIR__ . '/stripe-php/init.php';
    } else {
        return ['success' => false, 'message' => 'Stripe library not found'];
    }
    
    try {
        \Stripe\Stripe::setApiKey($secret_key);
        
        // Create refund through Stripe API
        $refund = \Stripe\Refund::create([
            'payment_intent' => $payment_intent_id,
            'amount' => intval($amount * 100) // Convert to cents
        ]);
        
        return ['success' => true, 'refund_id' => $refund->id];
        
    } catch (\Stripe\Exception\ApiErrorException $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Send refund email to customer
 */
function sendRefundEmail($to_email, $name, $refund_amount, $credit_amount, $session_name, $reason, $method, $expiry_date = '', $exchange_session_name = '') {
    if ($method === 'refund') {
        $subject = 'Refund Processed - Arctic Wolves';
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #7000a4;'>Refund Processed</h2>
            <p>Hi $name,</p>
            <p>Your refund has been processed successfully.</p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <strong>Refund Amount:</strong> $" . number_format($refund_amount, 2) . "<br>
                <strong>Session:</strong> " . htmlspecialchars($session_name) . "<br>
                <strong>Reason:</strong> " . htmlspecialchars($reason) . "
            </div>
            <p>The refund will appear in your account within 5-10 business days.</p>
            <p>If you have any questions, please contact us.</p>
            <p>Best regards,<br>Arctic Wolves Team</p>
        </body>
        </html>
        ";
    } elseif ($method === 'credit') {
        $subject = 'Store Credit Issued - Arctic Wolves';
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #7000a4;'>Store Credit Issued</h2>
            <p>Hi $name,</p>
            <p>You have been issued store credit instead of a refund.</p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <strong>Credit Amount:</strong> $" . number_format($credit_amount, 2) . "<br>
                <strong>Original Session:</strong> " . htmlspecialchars($session_name) . "<br>
                " . ($expiry_date ? "<strong>Expiry Date:</strong> $expiry_date<br>" : "") . "
                <strong>Reason:</strong> " . htmlspecialchars($reason) . "
            </div>
            <p>This credit can be applied to any future booking. It will be automatically available at checkout.</p>
            <p><a href='https://" . $_SERVER['HTTP_HOST'] . "/dashboard.php?page=user_credits' style='background: #7000a4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 10px;'>View My Credits</a></p>
            <p>If you have any questions, please contact us.</p>
            <p>Best regards,<br>Arctic Wolves Team</p>
        </body>
        </html>
        ";
    } elseif ($method === 'exchange') {
        $subject = 'Booking Exchange Completed - Arctic Wolves';
        $body = "
        <html>
        <body style='font-family: Arial, sans-serif; color: #333;'>
            <h2 style='color: #7000a4;'>Booking Exchange Completed</h2>
            <p>Hi $name,</p>
            <p>Your booking has been successfully exchanged.</p>
            <div style='background: #f5f5f5; padding: 15px; border-radius: 8px; margin: 20px 0;'>
                <strong>Original Session:</strong> " . htmlspecialchars($session_name) . "<br>
                " . ($exchange_session_name ? "<strong>New Session:</strong> " . htmlspecialchars($exchange_session_name) . "<br>" : "") . "
                <strong>Reason:</strong> " . htmlspecialchars($reason) . "
            </div>
            <p>Your new booking is confirmed. You can view it in your dashboard.</p>
            <p><a href='https://" . $_SERVER['HTTP_HOST'] . "/dashboard.php?page=session_history' style='background: #7000a4; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; margin-top: 10px;'>View My Bookings</a></p>
            <p>If you have any questions, please contact us.</p>
            <p>Best regards,<br>Arctic Wolves Team</p>
        </body>
        </html>
        ";
    }
    
    try {
        sendEmail($to_email, strtolower(str_replace(' ', '_', $method)), [
            'name' => $name,
            'amount' => number_format($refund_amount ?: $credit_amount, 2),
            'session' => $session_name,
            'reason' => $reason
        ]);
    } catch (Exception $e) {
        error_log("Failed to send refund email: " . $e->getMessage());
    }
}
?>
