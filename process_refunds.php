<?php
// process_refunds.php - Handle refund operations
session_start();
require 'db_config.php';
require 'security.php';
require 'notifications.php';
require 'mailer.php';

setSecurityHeaders();

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
            $stmt = $pdo->query("
                SELECT id, title, session_date, session_time
                FROM sessions
                WHERE session_date >= CURDATE()
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
                       CONCAT(bf.first_name, ' ', bf.last_name) as athlete_name
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
                
                // Validate exchange session exists
                $session_check = $pdo->prepare("SELECT title, price FROM sessions WHERE id = ?");
                $session_check->execute([$exchange_session_id]);
                $exchange_session = $session_check->fetch();
                
                if (!$exchange_session) {
                    throw new Exception('Exchange session not found');
                }
                
                // Create new booking for exchange session
                $exchange_booking = $pdo->prepare("
                    INSERT INTO bookings (user_id, session_id, amount_paid, original_price, tax_amount, status, booked_for_user_id, created_at)
                    VALUES (?, ?, ?, ?, ?, 'paid', ?, NOW())
                ");
                $exchange_booking->execute([
                    $booking['user_id'],
                    $exchange_session_id,
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
                $exchange_session_id,
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
            
            $stmt = $pdo->prepare("
                SELECT r.*, u.email, u.first_name, u.last_name,
                       s.title as session_name, s.session_date,
                       CONCAT(admin.first_name, ' ', admin.last_name) as processed_by_name
                FROM refunds r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN bookings b ON r.booking_id = b.id
                LEFT JOIN sessions s ON b.session_id = s.id
                LEFT JOIN users admin ON r.refunded_by = admin.id
                WHERE DATE(r.refund_date) BETWEEN ? AND ?
                ORDER BY r.refund_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $refunds = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'refunds' => $refunds]);
            break;
            
        case 'export_refunds':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            
            $stmt = $pdo->prepare("
                SELECT r.*, u.email, u.first_name, u.last_name,
                       s.title as session_name, s.session_date,
                       CONCAT(admin.first_name, ' ', admin.last_name) as processed_by_name
                FROM refunds r
                JOIN users u ON r.user_id = u.id
                LEFT JOIN bookings b ON r.booking_id = b.id
                LEFT JOIN sessions s ON b.session_id = s.id
                LEFT JOIN users admin ON r.refunded_by = admin.id
                WHERE DATE(r.refund_date) BETWEEN ? AND ?
                ORDER BY r.refund_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $refunds = $stmt->fetchAll();
            
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
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Process Stripe refund
 */
function processStripeRefund($payment_intent_id, $amount, $secret_key) {
    $url = 'https://api.stripe.com/v1/refunds';
    
    $data = [
        'payment_intent' => $payment_intent_id,
        'amount' => intval($amount * 100) // Convert to cents
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secret_key
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($http_code === 200 && isset($result['id'])) {
        return ['success' => true, 'refund_id' => $result['id']];
    } else {
        $error = $result['error']['message'] ?? 'Unknown error';
        return ['success' => false, 'message' => $error];
    }
}

/**
 * Send refund email to customer
 */
function sendRefundEmail($to_email, $name, $refund_amount, $credit_amount, $session_name, $reason, $method, $expiry_date = '', $exchange_session_name = '') {
    if ($method === 'refund') {
        $subject = 'Refund Processed - Crash Hockey';
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
            <p>Best regards,<br>Crash Hockey Team</p>
        </body>
        </html>
        ";
    } elseif ($method === 'credit') {
        $subject = 'Store Credit Issued - Crash Hockey';
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
            <p>Best regards,<br>Crash Hockey Team</p>
        </body>
        </html>
        ";
    } elseif ($method === 'exchange') {
        $subject = 'Booking Exchange Completed - Crash Hockey';
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
            <p>Best regards,<br>Crash Hockey Team</p>
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
