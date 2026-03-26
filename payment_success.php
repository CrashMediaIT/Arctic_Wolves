<?php
// payment_success.php
session_start();
require 'db_config.php';
require 'mailer.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';
require_once __DIR__ . '/lib/invoice_helper.php';

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
$purchase_confirmed = null;

try {
    // 3. VERIFY PAYMENT WITH STRIPE API
    $checkout = \Stripe\Checkout\Session::retrieve($stripe_sid);

    if ($checkout->payment_status == 'paid') {
        
        if ($purchase_type === 'package' && isset($_SESSION['package_purchase'])) {
            // HANDLE CAMP / MULTI-WEEK PACKAGE PURCHASE
            $purchase_confirmed = 'package';
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

                    // Create invoice for the package purchase
                    $pkg_invoice_id = null;
                    try {
                        $purchaser_id = $_SESSION['user_id'] ?? ($athlete_ids[0] ?? 0);
                        $pkg_subtotal = $purchase['subtotal'] ?? $total;
                        $pkg_tax = $purchase['tax_amount'] ?? 0;
                        $invoice_items = [['description' => 'Package: ' . ($package['name'] ?? 'Package Purchase'), 'quantity' => count($athlete_ids), 'unit_price' => $amount_per_athlete]];
                        $pkg_invoice_id = createPurchaseInvoice($pdo, $purchaser_id, $invoice_items, $pkg_subtotal, $pkg_tax, $total, 'stripe', $stripe_sid, 'Package purchase: ' . ($package['name'] ?? ''));
                    } catch (\Throwable $invoiceErr) {
                        ErrorLogger::error("Package invoice creation failed: " . $invoiceErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'package_id' => $package_id]);
                    }

                    // Send confirmation email to the purchaser
                    try {
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
                                'trans_id'      => $stripe_sid,
                                'invoice_id'    => $pkg_invoice_id
                            ]);
                        }
                    } catch (\Throwable $emailErr) {
                        ErrorLogger::error("Package receipt email failed: " . $emailErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'package_id' => $package_id]);
                    }
                }
            }

            unset($_SESSION['package_purchase']);
        } elseif (isset($checkout->metadata->type) && $checkout->metadata->type === 'dev_program') {
            // HANDLE DEVELOPMENT PROGRAM ENROLLMENT (only after payment confirmed)
            $program_type = $checkout->metadata->program_type ?? '';
            $athlete_id = intval($checkout->metadata->athlete_id ?? 0);
            $template_id = intval($checkout->metadata->template_id ?? 0);
            $purchase_confirmed = 'dev_program';

            if (in_array($program_type, ['goalie_dev', 'player_dev']) && $athlete_id > 0) {
                // Idempotency: check if already enrolled in active program of same type+template from this payment
                $dup_check = $pdo->prepare("SELECT id FROM development_program_enrollments WHERE athlete_id = ? AND program_type = ? AND template_id = ? AND status = 'active'");
                $dup_check->execute([$athlete_id, $program_type, $template_id]);

                if (!$dup_check->fetch()) {
                    // Get duration_weeks and program name for auto-calculated dates
                    $duration_weeks = null;
                    $program_name = null;
                    try {
                        if ($template_id > 0) {
                            $dur_stmt = $pdo->prepare("SELECT name, duration_weeks FROM training_session_templates WHERE id = ? AND is_dev_program = 1");
                            $dur_stmt->execute([$template_id]);
                            $dur_row = $dur_stmt->fetch(PDO::FETCH_ASSOC);
                            $duration_weeks = $dur_row['duration_weeks'] ?? null;
                            $program_name = $dur_row['name'] ?? null;
                        }
                    } catch (PDOException $e) { /* column may not exist */ }
                    if (!$duration_weeks) {
                        try {
                            $dur_stmt2 = $pdo->prepare("SELECT program_duration_weeks FROM development_notification_templates WHERE program_type = ?");
                            $dur_stmt2->execute([$program_type]);
                            $dur_row2 = $dur_stmt2->fetch(PDO::FETCH_ASSOC);
                            $duration_weeks = $dur_row2['program_duration_weeks'] ?? null;
                        } catch (PDOException $e) { /* ignore */ }
                    }
                    
                    $start_date = date('Y-m-d');
                    $duration_weeks = $duration_weeks !== null ? max(1, min(52, intval($duration_weeks))) : null;
                    $end_date = $duration_weeks ? date('Y-m-d', strtotime("+{$duration_weeks} weeks")) : null;
                    
                    // CRITICAL: Create the enrollment record - this is the essential operation
                    $stmt = $pdo->prepare("INSERT INTO development_program_enrollments (athlete_id, program_type, program_name, template_id, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$athlete_id, $program_type, $program_name, $template_id ?: null, $start_date, $end_date]);
                    $enrollment_id = $pdo->lastInsertId();

                    try {
                        Auditor::log($pdo, $athlete_id, 'create', 'development_program_enrollments', $enrollment_id, [
                            'action' => 'register_dev_program', 'program_type' => $program_type, 'amount' => ($checkout->amount_total / 100)
                        ]);
                    } catch (\Throwable $auditErr) {
                        ErrorLogger::error("Dev program audit log failed: " . $auditErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'enrollment_id' => $enrollment_id]);
                    }

                    // NON-CRITICAL: Invoice, notifications, and emails below.
                    // Failures here should NOT prevent showing the success page since
                    // the enrollment was already created and the payment was confirmed.

                    // Create invoice for development program enrollment
                    $dev_invoice_id = null;
                    try {
                        $dev_total = $checkout->amount_total / 100;
                        $dev_program_label = $program_type === 'goalie_dev' ? 'Goalie Development Program' : 'Player Development Program';
                        $dev_items = [['description' => $dev_program_label . ($program_name ? ': ' . $program_name : ''), 'quantity' => 1, 'unit_price' => $dev_total]];
                        $dev_invoice_id = createPurchaseInvoice($pdo, $athlete_id, $dev_items, $dev_total, 0, $dev_total, 'stripe', $stripe_sid, 'Development program enrollment');
                    } catch (\Throwable $invoiceErr) {
                        ErrorLogger::error("Dev program invoice creation failed: " . $invoiceErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'enrollment_id' => $enrollment_id]);
                    }

                    // Notify dev coaches
                    try {
                        $coaches_stmt = $pdo->prepare("SELECT DISTINCT ur.user_id FROM user_roles ur WHERE ur.role = ?");
                        $coaches_stmt->execute([$program_type]);
                        $coach_ids = $coaches_stmt->fetchAll(PDO::FETCH_COLUMN);

                        $admin_stmt = $pdo->prepare("SELECT DISTINCT ur.user_id FROM user_roles ur WHERE ur.role = 'admin'");
                        $admin_stmt->execute();
                        $admin_ids = $admin_stmt->fetchAll(PDO::FETCH_COLUMN);
                        $notify_ids = array_unique(array_merge($coach_ids, $admin_ids));

                        $athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
                        $athlete_stmt->execute([$athlete_id]);
                        $athlete_info = $athlete_stmt->fetch(PDO::FETCH_ASSOC);
                        if ($athlete_info && function_exists('decryptUserRows')) {
                            $athlete_info = decryptUserRows([$athlete_info])[0];
                        }
                        $athlete_name = trim(($athlete_info['first_name'] ?? '') . ' ' . ($athlete_info['last_name'] ?? ''));
                        $notif_program_label = $program_type === 'goalie_dev' ? 'Goalie Development Program' : 'Player Development Program';

                        // Coach/admin in-app notifications (hardcoded text)
                        $notif_stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, title, message, link_url) VALUES (?, 'dev_program_registration', ?, ?, '?page=development_programs')");
                        foreach ($notify_ids as $nid) {
                            $notif_stmt->execute([$nid, 'New Development Program Registration', "Athlete: " . htmlspecialchars($athlete_name, ENT_QUOTES, 'UTF-8') . " has enrolled (paid) in the " . $notif_program_label . "."]);
                        }

                        // Send email to configured coach notification email address
                        try {
                            $tmpl_stmt = $pdo->prepare("SELECT subject, body, notification_email FROM development_notification_templates WHERE program_type = ?");
                            $tmpl_stmt->execute([$program_type]);
                            $tmpl = $tmpl_stmt->fetch(PDO::FETCH_ASSOC);
                            if (!empty($tmpl['notification_email']) && filter_var($tmpl['notification_email'], FILTER_VALIDATE_EMAIL)) {
                                if (function_exists('sendEmail')) {
                                    sendEmail($tmpl['notification_email'], 'notification', [
                                        'title' => 'New Development Program Registration',
                                        'message' => "Athlete: " . htmlspecialchars($athlete_name, ENT_QUOTES, 'UTF-8') . " has enrolled (paid) in the " . $notif_program_label . ".",
                                        'name' => 'Development Program Admin'
                                    ]);
                                }
                            }
                        } catch (\Throwable $e) {
                            ErrorLogger::error("Dev program notification email failed: " . $e->getMessage(), ['stripe_session_id' => $stripe_sid, 'program_type' => $program_type]);
                        }
                    } catch (\Throwable $ne) {
                        ErrorLogger::error("Dev program notification failed: " . $ne->getMessage(), ['stripe_session_id' => $stripe_sid, 'program_type' => $program_type, 'athlete_id' => $athlete_id]);
                    }

                    // Send payment receipt email to athlete
                    try {
                        $email_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
                        $email_stmt->execute([$athlete_id]);
                        $user_info = $email_stmt->fetch(PDO::FETCH_ASSOC);
                        $user_info = decryptUserRow($user_info);

                        $program_label = $program_type === 'goalie_dev' ? 'Goalie Development Program' : 'Player Development Program';

                        if ($user_info && !empty($user_info['email'])) {
                            sendEmail($user_info['email'], 'payment_receipt', [
                                'session_title' => $program_label,
                                'amount'        => number_format($checkout->amount_total / 100, 2),
                                'date'          => date('M j, Y'),
                                'trans_id'      => $stripe_sid,
                                'invoice_id'    => $dev_invoice_id
                            ]);
                            
                            // Send athlete welcome email using the template
                            try {
                                $tmpl_stmt2 = $pdo->prepare("SELECT subject, body FROM development_notification_templates WHERE program_type = ?");
                                $tmpl_stmt2->execute([$program_type]);
                                $athlete_tmpl = $tmpl_stmt2->fetch(PDO::FETCH_ASSOC);
                                if ($athlete_tmpl) {
                                    sendEmail($user_info['email'], 'notification', [
                                        'title' => $athlete_tmpl['subject'] ?? 'Welcome to Your Development Program!',
                                        'message' => $athlete_tmpl['body'] ?? 'You have been enrolled. Your coach will be in touch shortly.',
                                        'name' => $user_info['first_name'] ?? 'Athlete'
                                    ]);
                                }
                            } catch (\Throwable $e) {
                                ErrorLogger::error("Dev program welcome email failed: " . $e->getMessage(), ['stripe_session_id' => $stripe_sid, 'athlete_id' => $athlete_id]);
                            }
                        }
                    } catch (\Throwable $emailErr) {
                        ErrorLogger::error("Dev program receipt email failed: " . $emailErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'athlete_id' => $athlete_id]);
                    }
                }
            }
        } elseif (isset($checkout->metadata->type) && $checkout->metadata->type === 'template_session') {
            // HANDLE TEMPLATE SESSION REGISTRATION (only after payment confirmed)
            $session_date_id = intval($checkout->metadata->session_date_id ?? 0);
            $athlete_id = intval($checkout->metadata->athlete_id ?? 0);
            $purchase_confirmed = 'template_session';

            if ($session_date_id > 0 && $athlete_id > 0) {
                // Idempotency: check if already registered
                $dup_check = $pdo->prepare("SELECT id FROM session_date_athletes WHERE session_date_id = ? AND athlete_id = ?");
                $dup_check->execute([$session_date_id, $athlete_id]);

                if (!$dup_check->fetch()) {
                    $stmt = $pdo->prepare("INSERT INTO session_date_athletes (session_date_id, athlete_id) VALUES (?, ?)");
                    $stmt->execute([$session_date_id, $athlete_id]);

                    try {
                        Auditor::log($pdo, $athlete_id, 'create', 'session_date_athletes', $pdo->lastInsertId(), [
                            'action' => 'register_template_session', 'session_date_id' => $session_date_id, 'amount' => ($checkout->amount_total / 100)
                        ]);
                    } catch (\Throwable $auditErr) {
                        ErrorLogger::error("Template session audit log failed: " . $auditErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'session_date_id' => $session_date_id]);
                    }

                    // Create invoice for template session registration
                    $tpl_invoice_id = null;
                    try {
                        $tpl_total = $checkout->amount_total / 100;
                        $tpl_name_stmt = $pdo->prepare("SELECT t.name FROM training_session_dates td JOIN training_session_templates t ON td.template_id = t.id WHERE td.id = ?");
                        $tpl_name_stmt->execute([$session_date_id]);
                        $tpl_session_name = $tpl_name_stmt->fetchColumn() ?: 'Training Session';
                        $tpl_items = [['description' => 'Session: ' . $tpl_session_name, 'quantity' => 1, 'unit_price' => $tpl_total]];
                        $tpl_invoice_id = createPurchaseInvoice($pdo, $athlete_id, $tpl_items, $tpl_total, 0, $tpl_total, 'stripe', $stripe_sid, 'Session registration');
                    } catch (\Throwable $invoiceErr) {
                        ErrorLogger::error("Template session invoice creation failed: " . $invoiceErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'session_date_id' => $session_date_id]);
                    }

                    // Send confirmation email
                    try {
                        $email_stmt = $pdo->prepare("SELECT email, first_name FROM users WHERE id = ?");
                        $email_stmt->execute([$athlete_id]);
                        $user_info = $email_stmt->fetch(PDO::FETCH_ASSOC);
                        $user_info = decryptUserRow($user_info);

                        $tpl_stmt = $pdo->prepare("
                            SELECT t.name, td.session_date
                            FROM training_session_dates td
                            JOIN training_session_templates t ON td.template_id = t.id
                            WHERE td.id = ?
                        ");
                        $tpl_stmt->execute([$session_date_id]);
                        $tpl_info = $tpl_stmt->fetch(PDO::FETCH_ASSOC);

                        if ($user_info && !empty($user_info['email']) && $tpl_info) {
                            sendEmail($user_info['email'], 'payment_receipt', [
                                'session_title' => $tpl_info['name'],
                                'amount'        => number_format($checkout->amount_total / 100, 2),
                                'date'          => date('M j, Y', strtotime($tpl_info['session_date'])),
                                'trans_id'      => $stripe_sid,
                                'invoice_id'    => $tpl_invoice_id
                            ]);
                        }
                    } catch (\Throwable $emailErr) {
                        ErrorLogger::error("Template session receipt email failed: " . $emailErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'session_date_id' => $session_date_id]);
                    }
                }
            }
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
            $purchase_confirmed = 'booking';

            // Only process if payment hasn't been recorded yet
            if ($booking && $booking['payment_status'] !== 'paid') {
                
                // 5. MARK AS PAID IN DB (update payment_status, not status)
                // Use WHERE condition for idempotency to prevent duplicate payment processing
                $update_stmt = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ? AND payment_status != 'paid'");
                $update_stmt->execute([$booking['id']]);
                
                // Only send receipt if we actually updated the record (prevents duplicate emails)
                if ($update_stmt->rowCount() > 0) {
                    // Create invoice for the session booking
                    $booking_invoice_id = null;
                    try {
                        $booking_amount = floatval($booking['amount_paid'] ?? 0);
                        $booking_items = [['description' => 'Session: ' . ($booking['title'] ?? 'Training Session'), 'quantity' => 1, 'unit_price' => $booking_amount]];
                        $booking_invoice_id = createPurchaseInvoice($pdo, $booking['user_id'], $booking_items, $booking_amount, 0, $booking_amount, 'stripe', $stripe_sid, 'Session booking');
                    } catch (\Throwable $invoiceErr) {
                        ErrorLogger::error("Booking invoice creation failed: " . $invoiceErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'booking_id' => $booking['id']]);
                    }

                    // 6. SEND EMAIL RECEIPT
                    try {
                        $session_date = date('M j, Y', strtotime($booking['session_date']));
                        
                        sendEmail($booking['email'], 'payment_receipt', [
                            'session_title' => $booking['title'],
                            'amount'        => number_format($booking['amount_paid'], 2),
                            'date'          => $session_date,
                            'trans_id'      => $stripe_sid,
                            'invoice_id'    => $booking_invoice_id
                        ]);
                    } catch (\Throwable $emailErr) {
                        ErrorLogger::error("Booking receipt email failed: " . $emailErr->getMessage(), ['stripe_session_id' => $stripe_sid, 'booking_id' => $booking['id']]);
                    }
                }
            }
        }
    }
} catch (\Throwable $e) {
    ErrorLogger::error("Payment verification failed: " . $e->getMessage(), [
        'stripe_session_id' => $stripe_sid ?? '',
        'purchase_type' => $purchase_type ?? 'unknown',
        'file' => $e->getFile(),
        'line' => $e->getLine(),
    ]);
    $payment_error = "Payment verification encountered an error. Please contact support.";
}

$isPwa = !empty($_GET['pwa']);
$dashBase = $isPwa ? 'pwa.php' : 'dashboard.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Payment Success | Arctic Wolves</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="display:flex; justify-content:center; align-items:center; height:100vh; background:#06080b; color:#fff;">
    
    <div style="text-align:center; padding: 40px; border: 1px solid #1e293b; background: #0d1116; border-radius: 12px; max-width: 400px; margin: 20px;">
        <?php if (!empty($payment_error)): ?>
        <i class="fa-solid fa-circle-exclamation" style="font-size: 60px; color: #ff4444; margin-bottom: 20px;"></i>
        <h1 style="margin: 0 0 10px 0;">Payment Error</h1>
        <p style="color: #94a3b8; margin-bottom: 30px;"><?= htmlspecialchars($payment_error) ?></p>
        <?php else: ?>
        <i class="fa-solid fa-circle-check" style="font-size: 60px; color: #00ff88; margin-bottom: 20px;"></i>
        <?php if ($purchase_confirmed === 'dev_program'): ?>
        <h1 style="margin: 0 0 10px 0;">Enrollment Confirmed!</h1>
        <p style="color: #94a3b8; margin-bottom: 30px;">You have been enrolled in the development program. A receipt has been sent to your email.</p>
        <?php elseif ($purchase_confirmed === 'package'): ?>
        <h1 style="margin: 0 0 10px 0;">Registration Confirmed!</h1>
        <p style="color: #94a3b8; margin-bottom: 30px;">Your registration has been confirmed. A receipt has been sent to your email.</p>
        <?php else: ?>
        <h1 style="margin: 0 0 10px 0;">Booking Confirmed!</h1>
        <p style="color: #94a3b8; margin-bottom: 30px;">A receipt has been sent to your email.</p>
        <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($purchase_confirmed === 'dev_program'): ?>
        <a href="<?= $dashBase ?>?page=personal_development_my_program" class="btn-primary" style="text-decoration:none; padding:12px 30px; border-radius:6px; display:inline-block;">
            View My Program
        </a>
        <?php else: ?>
        <a href="<?= $dashBase ?>?page=upcoming_sessions" class="btn-primary" style="text-decoration:none; padding:12px 30px; border-radius:6px; display:inline-block;">
            Return to Upcoming Sessions
        </a>
        <?php endif; ?>
    </div>

</body>
</html>