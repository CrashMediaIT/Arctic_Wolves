<?php
// payment_success.php
session_start();
require 'db_config.php';
require 'mailer.php';

// 1. LOAD STRIPE
if (file_exists('vendor/autoload.php')) { require 'vendor/autoload.php'; } 
elseif (file_exists('stripe-php/init.php')) { require 'stripe-php/init.php'; }

// 2. GET KEYS
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
require_once __DIR__ . '/security.php';
\Stripe\Stripe::setApiKey(function_exists('decryptCredential') ? decryptCredential($settings['stripe_secret_key']) : $settings['stripe_secret_key']);

$stripe_sid = $_GET['session_id'] ?? '';
if (!$stripe_sid) { header("Location: dashboard.php"); exit(); }

$purchase_type = $_GET['type'] ?? 'booking';

try {
    // 3. VERIFY PAYMENT WITH STRIPE API
    $checkout = \Stripe\Checkout\Session::retrieve($stripe_sid);

    if ($checkout->payment_status == 'paid') {
        
        if ($purchase_type === 'package' && isset($_SESSION['package_purchase'])) {
            // HANDLE CAMP / MULTI-WEEK PACKAGE PURCHASE
            $purchase = $_SESSION['package_purchase'];
            $package_id = intval($purchase['package_id']);
            $athlete_ids = $purchase['athlete_ids'] ?? [];
            $total = $purchase['total'] ?? 0;
            $selected_addons = $purchase['selected_addons'] ?? [];

            // Get package details
            $pkg_stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
            $pkg_stmt->execute([$package_id]);
            $package = $pkg_stmt->fetch(PDO::FETCH_ASSOC);

            if ($package && !empty($athlete_ids)) {
                // Check if already processed (idempotency) - check ANY athlete
                $athlete_placeholders = implode(',', array_fill(0, count($athlete_ids), '?'));
                $dup_stmt = $pdo->prepare("SELECT id FROM user_packages WHERE package_id = ? AND stripe_session_id = ? AND user_id IN ($athlete_placeholders)");
                $dup_stmt->execute(array_merge([$package_id, $stripe_sid], array_map('intval', $athlete_ids)));
                $already_processed = $dup_stmt->fetch();

                if (!$already_processed) {
                    $pdo->beginTransaction();
                    try {
                        // Get sessions linked to this package
                        $sess_stmt = $pdo->prepare("SELECT session_id FROM package_sessions WHERE package_id = ? AND session_id IS NOT NULL");
                        $sess_stmt->execute([$package_id]);
                        $linked_session_ids = $sess_stmt->fetchAll(PDO::FETCH_COLUMN);

                        $amount_per_athlete = $total / count($athlete_ids);

                        foreach ($athlete_ids as $athlete_id) {
                            $athlete_id = intval($athlete_id);

                            // Create user_packages record
                            $up_stmt = $pdo->prepare("
                                INSERT INTO user_packages (user_id, package_id, credits_remaining, payment_status, amount_paid, stripe_session_id)
                                VALUES (?, ?, ?, 'paid', ?, ?)
                            ");
                            $up_stmt->execute([$athlete_id, $package_id, $package['credits'], $amount_per_athlete, $stripe_sid]);
                            $user_package_id = $pdo->lastInsertId();

                            // Save selected add-ons
                            if (!empty($selected_addons)) {
                                $addon_stmt = $pdo->prepare("INSERT INTO camp_registration_add_ons (user_package_id, add_on_id, opted_in) VALUES (?, ?, 1)");
                                foreach ($selected_addons as $addon_id) {
                                    try { $addon_stmt->execute([$user_package_id, intval($addon_id)]); } catch (PDOException $ae) { /* ignore duplicates */ }
                                }
                            }

                            // Create bookings for each linked session
                            $per_session_amount = count($linked_session_ids) > 0 ? round($amount_per_athlete / count($linked_session_ids), 2) : 0;
                            foreach ($linked_session_ids as $session_id) {
                                $bk_stmt = $pdo->prepare("
                                    INSERT INTO bookings (session_id, user_id, stripe_session_id, amount_paid, status, payment_status)
                                    VALUES (?, ?, ?, ?, 'confirmed', 'paid')
                                ");
                                $bk_stmt->execute([intval($session_id), $athlete_id, $stripe_sid, $per_session_amount]);
                            }
                        }

                        $pdo->commit();
                    } catch (Exception $txe) {
                        $pdo->rollBack();
                        throw $txe;
                    }

                    // Send confirmation email to the purchaser
                    $user_id = $_SESSION['user_id'] ?? ($athlete_ids[0] ?? 0);
                    $email_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
                    $email_stmt->execute([$user_id]);
                    $user_info = $email_stmt->fetch(PDO::FETCH_ASSOC);
                    $user_info = decryptUserRow($user_info);
                    if ($user_info && !empty($user_info['email'])) {
                        sendEmail($user_info['email'], 'payment_receipt', [
                            'session_title' => $package['name'],
                            'amount'        => number_format($total, 2),
                            'date'          => date('M j, Y'),
                            'trans_id'      => $stripe_sid
                        ]);
                    }
                }
            }

            unset($_SESSION['package_purchase']);
        } else {
            // HANDLE REGULAR SESSION BOOKING
            // 4. FIND THE PENDING BOOKING
            $stmt = $pdo->prepare("
                SELECT b.*, s.title, s.session_date, s.session_time, u.email, u.first_name 
                FROM bookings b
                JOIN sessions s ON b.session_id = s.id
                JOIN users u ON b.user_id = u.id
                WHERE b.stripe_session_id = ?
            ");
            $stmt->execute([$stripe_sid]);
            $booking = $stmt->fetch();
            $booking = decryptUserRow($booking);

            // Only process if payment hasn't been recorded yet
            if ($booking && $booking['payment_status'] !== 'paid') {
                
                // 5. MARK AS PAID IN DB (update payment_status, not status)
                // Use WHERE condition for idempotency to prevent duplicate payment processing
                $update_stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'");
                $update_stmt->execute([$booking['id']]);
                
                // Only send receipt if we actually updated the record (prevents duplicate emails)
                if ($update_stmt->rowCount() > 0) {
                    // 6. SEND EMAIL RECEIPT
                    $session_date = date('M j, Y', strtotime($booking['session_date']));
                    
                    sendEmail($booking['email'], 'payment_receipt', [
                        'session_title' => $booking['title'],
                        'amount'        => number_format($booking['amount_paid'], 2),
                        'date'          => $session_date,
                        'trans_id'      => $stripe_sid
                    ]);
                }
            }
        }
    }
} catch (Exception $e) {
    die("Payment Verification Failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment Success | Arctic Wolves</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:#06080b; color:#fff;">
    
    <div style="text-align:center; padding: 40px; border: 1px solid #1e293b; background: #0d1116; border-radius: 12px; max-width: 400px; margin: 20px;">
        <i class="fa-solid fa-circle-check" style="font-size: 60px; color: #00ff88; margin-bottom: 20px;"></i>
        <h1 style="margin: 0 0 10px 0;">Booking Confirmed!</h1>
        <p style="color: #94a3b8; margin-bottom: 30px;">A receipt has been sent to your email.</p>
        
        <a href="dashboard.php?page=schedule" class="btn-primary" style="text-decoration:none; padding:12px 30px; border-radius:6px; display:inline-block;">
            Return to Schedule
        </a>
    </div>

</body>
</html>