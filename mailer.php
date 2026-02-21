<?php
// mailer.php
require_once 'db_config.php';

/**
 * Custom SMTP Class to handle direct server communication.
 * Features: Manual Handshake, STARTTLS support, and robust Authentication sync.
 */
class SmtpMailer {
    private $conn;

    public function send($to, $subject, $body, $config) {
        // 1. CONFIGURATION & SANITIZATION
        // Require SMTP host from configuration - no hardcoded fallbacks
        if (empty($config['smtp_host'])) {
            throw new Exception("SMTP host not configured. Please configure SMTP settings in System Tools.");
        }
        // Remove 'ssl://' or 'tls://' if accidentally typed in Host field
        $raw_host = $config['smtp_host'];
        $host     = preg_replace('/^ssl:\/\/|^tls:\/\//', '', trim($raw_host));
        
        $port = $config['smtp_port'] ?? '465';
        $enc  = $config['smtp_encryption'] ?? 'ssl'; 
        
        // Ensure strings to prevent PHP 8 Fatal Errors on null
        $user = trim((string)($config['smtp_user'] ?? ''));
        $pass = trim((string)($config['smtp_pass'] ?? ''));
        
        // 2. DETERMINE PROTOCOL
        // SSL connects securely immediately. TLS starts plain and upgrades later.
        $protocol = ($enc == 'ssl') ? 'ssl://' : '';
        
        // 3. CONNECT
        $this->conn = fsockopen($protocol . $host, $port, $errno, $errstr, 15);
        if (!$this->conn) {
            throw new Exception("Connection Failed: $errstr ($errno)");
        }
        $this->readResponse(); // Initial 220 banner

        // 4. HANDSHAKE
        $this->sendCommand("EHLO " . $_SERVER['SERVER_NAME']);

        // 5. STARTTLS (If Encryption is TLS)
        if ($enc == 'tls') {
            $this->sendCommand("STARTTLS");
            if (!stream_socket_enable_crypto($this->conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception("TLS Handshake Failed");
            }
            $this->sendCommand("EHLO " . $_SERVER['SERVER_NAME']);
        }

        // 6. AUTHENTICATION (Manual Step-by-Step Sync)
        if (!empty($user) && !empty($pass)) {
            fputs($this->conn, "AUTH LOGIN\r\n");
            $this->expectCode(334, "Auth Request Failed");

            fputs($this->conn, base64_encode($user) . "\r\n");
            $this->expectCode(334, "Auth Username Failed");

            fputs($this->conn, base64_encode($pass) . "\r\n");
            $this->expectCode(235, "Auth Password Failed");
        }

        // 7. ENVELOPE
        $fromEmail = !empty($config['smtp_from_email']) ? $config['smtp_from_email'] : $user;
        $fromName  = !empty($config['smtp_from_name'])  ? $config['smtp_from_name']  : 'Arctic Wolves System';

        $this->sendCommand("MAIL FROM: <$user>"); // Neo often requires Envelope From to match Login
        $this->sendCommand("RCPT TO: <$to>");
        $this->sendCommand("DATA");

        // 8. HEADERS & BODY
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: $fromName <$fromEmail>\r\n";
        $headers .= "To: <$to>\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date("r") . "\r\n";
        $headers .= "Sender: $user\r\n"; // Helps deliverability
        
        $this->sendCommand($headers . "\r\n" . $body . "\r\n.\r\n");
        $this->sendCommand("QUIT");
        
        fclose($this->conn);
        return true;
    }

    private function readResponse() {
        $response = "";
        while ($str = fgets($this->conn, 515)) {
            $response .= $str;
            if (substr($str, 3, 1) == " ") { break; }
        }
        return $response;
    }

    private function sendCommand($cmd) {
        fputs($this->conn, $cmd . "\r\n");
        $this->readResponse();
    }
    
    private function expectCode($code, $errorMsg) {
        $response = $this->readResponse();
        if (substr($response, 0, 3) != $code) {
            throw new Exception("$errorMsg (Server said: $response)");
        }
    }
}

// === HELPER FUNCTIONS ===

function getEmailConfig() {
    global $pdo;
    try {
        return $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) { return []; }
}

/**
 * Fetch theme settings (logo, colors) from the theme_settings table.
 * Used to brand emails consistently with the website design.
 */
function getThemeSettings() {
    global $pdo;
    $defaults = [
        'logo_url' => '',
        'primary_color' => '#7000a4',
        'secondary_color' => '#c0c0c0',
        'background_color' => '#06080b',
        'card_background_color' => '#0d1117',
        'text_color' => '#ffffff',
        'text_muted_color' => '#94a3b8',
        'border_color' => '#1e293b',
        'success_color' => '#22c55e',
        'error_color' => '#ef4444',
        'warning_color' => '#f59e0b'
    ];
    try {
        $rows = $pdo->query("SELECT setting_name, setting_value FROM theme_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge($defaults, $rows);
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Log email attempts to DB.
 * Now supports saving the $data payload for Resend functionality.
 */
function logEmailAttempt($to, $subject, $type, $status, $errorMsg = null, $data = []) {
    global $pdo;
    try {
        $payload = json_encode($data); // Save data for resending
        // Schema uses: to_email, from_email, subject, body, status, error_message, sent_at
        $config = getEmailConfig();
        $from = $config['smtp_from_email'] ?? $config['smtp_user'] ?? 'noreply@arcticwolves.ca';
        $stmt = $pdo->prepare("INSERT INTO email_logs (to_email, from_email, subject, body, status, error_message, sent_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$to, $from, $subject, $payload, $status, $errorMsg]);
    } catch (Exception $e) { /* Silent fail if DB issue */ }
}

/**
 * Main function to send transactional emails
 */
function sendEmail($to, $type, $data) {
    $config = getEmailConfig();
    $theme = getThemeSettings();
    $year = date('Y');
    
    // Brand colors from theme settings
    $primary    = htmlspecialchars($theme['primary_color'], ENT_QUOTES, 'UTF-8');
    $bg         = htmlspecialchars($theme['background_color'], ENT_QUOTES, 'UTF-8');
    $cardBg     = htmlspecialchars($theme['card_background_color'], ENT_QUOTES, 'UTF-8');
    $textMuted  = htmlspecialchars($theme['text_muted_color'], ENT_QUOTES, 'UTF-8');
    $borderClr  = htmlspecialchars($theme['border_color'], ENT_QUOTES, 'UTF-8');
    $successClr = htmlspecialchars($theme['success_color'], ENT_QUOTES, 'UTF-8');
    $errorClr   = htmlspecialchars($theme['error_color'], ENT_QUOTES, 'UTF-8');
    $warningClr = htmlspecialchars($theme['warning_color'], ENT_QUOTES, 'UTF-8');
    $logoUrl    = $theme['logo_url'];
    
    // --- TEMPLATE LOGIC ---
    $subject = "Arctic Wolves Notification"; 
    $body = "";
    
    // Common Logo Header
    $logoHtml = '';
    if (!empty($logoUrl)) {
        $safeLogo = htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8');
        $logoHtml = "<img src='$safeLogo' alt='Arctic Wolves Performance Logo' style='max-height: 60px; max-width: 200px;'>";
    }
    $header = "
    <div style='text-align: center; padding-bottom: 20px; margin-bottom: 20px; border-bottom: 1px solid $borderClr;'>
        $logoHtml
    </div>";
    
    // Common Footer
    $footer = "
    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid $borderClr; text-align: center; color: $textMuted; font-size: 11px;'>
        &copy; $year Arctic Wolves Performance. All rights reserved.<br>
        <a href='https://arcticwolves.ca' style='color: $textMuted; text-decoration: none;'>arcticwolves.ca</a>
    </div>";

    // 1. VERIFICATION CODE (Self-Registration)
    if ($type == 'verification') {
        $subject = "Verify Your Account";
        $code = $data['code'] ?? 'Error';
        $name = htmlspecialchars($data['name'] ?? 'Athlete');
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $successClr; margin-top: 0;'>Welcome, $name!</h2>
            <p style='color: #ccc;'>Please verify your email address to activate your account.</p>
            <div style='background: $cardBg; padding: 20px; text-align: center; margin: 30px 0; border-radius: 6px; border: 1px solid $borderClr;'>
                <span style='font-size: 32px; font-weight: 800; letter-spacing: 5px; color: #fff;'>$code</span>
            </div>
            $footer
        </div>";
    }
    
    // 2. WELCOME CREDENTIALS (Coach Created Athlete)
    elseif ($type == 'manual_welcome') {
        $subject = "Your Account Details";
        $name  = htmlspecialchars($data['name'] ?? '');
        $email = htmlspecialchars($data['email'] ?? '');
        $pass  = htmlspecialchars($data['password'] ?? '');
        
        // Build login URL from APP_URL env or fallback
        $appUrl = getenv('APP_URL') ?: 'https://arcticwolves.ca';
        $loginUrl = rtrim($appUrl, '/') . '/login.php';
        $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $primary; margin-top: 0;'>Welcome to the Team, $name!</h2>
            <p style='color: #ccc;'>Your account has been created. Please login with the details below:</p>
            <div style='background: $cardBg; border-left: 4px solid $primary; padding: 20px; margin: 20px 0; border-radius: 6px;'>
                <p style='margin: 0 0 10px 0;'><strong>Email:</strong> $email</p>
                <p style='margin: 0;'><strong>Password:</strong> $pass</p>
            </div>
            <p style='color: #ccc;'>You can log in at: <a href='$safeLoginUrl' style='color: $primary; text-decoration: underline;'>$safeLoginUrl</a></p>
            <p style='margin-top: 20px; text-align: center;'>
                <a href='$safeLoginUrl' style='display: inline-block; background: $primary; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Log In Now</a>
            </p>
            <p style='font-size:12px; color: $textMuted;'>You will be asked to change this password on first login.</p>
            $footer
        </div>";
    }
    
    // 3. PAYMENT RECEIPT (Stripe Success)
    elseif ($type == 'payment_receipt') {
        $subject = "Receipt: " . ($data['session_title'] ?? 'Booking');
        $amount = $data['amount'] ?? '0.00';
        $date = $data['date'] ?? date('Y-m-d');
        $trans_id = $data['trans_id'] ?? 'N/A';
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $successClr; margin-top: 0;'>Payment Confirmed</h2>
            <p style='color: #ccc;'>Thank you. Your booking has been secured.</p>
            
            <div style='background: $cardBg; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid $borderClr;'>
                <table style='width: 100%; border-collapse: collapse; color: #fff;'>
                    <tr>
                        <td style='padding: 8px 0; color: $textMuted; font-size:13px;'>Session</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold;'>{$data['session_title']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: $textMuted; font-size:13px;'>Date</td>
                        <td style='padding: 8px 0; text-align: right;'>$date</td>
                    </tr>
                    <tr style='border-top: 1px solid $borderClr;'>
                        <td style='padding: 15px 0 0 0; color: #fff; font-weight: bold;'>Total Paid</td>
                        <td style='padding: 15px 0 0 0; text-align: right; font-weight: bold; color: $successClr; font-size: 18px;'>$$amount</td>
                    </tr>
                </table>
            </div>
            
            <p style='font-size: 11px; color: $textMuted; text-align: center;'>Transaction ID: $trans_id</p>
            $footer
        </div>";
    }

    // 4. PASSWORD RESET
    elseif ($type == 'password_reset') {
        $subject = "Reset Password";
        $code = $data['code'] ?? '---';
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $primary; margin-top: 0;'>Password Reset</h2>
            <p style='color:#ccc;'>Use this code to reset your password:</p>
            <div style='background: $cardBg; padding: 20px; text-align: center; margin: 20px 0; border-radius: 6px; border: 1px solid $primary;'>
                <span style='font-size: 28px; font-weight: 800; color: #fff;'>$code</span>
            </div>
            $footer
        </div>";
    } 
    
    // 4B. EXTENSION REQUEST (Onboarding → IT)
    elseif ($type == 'extension_request') {
        $subject = "Phone Extension Request — New Staff";
        $staffName = htmlspecialchars($data['staff_name'] ?? '');
        $staffEmail = htmlspecialchars($data['email'] ?? '');
        $staffRole = htmlspecialchars($data['role'] ?? '');
        $staffTitle = htmlspecialchars($data['job_title'] ?? '');
        $staffStart = htmlspecialchars($data['start_date'] ?? '');
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $primary; margin-top: 0;'>Phone Extension Request</h2>
            <p style='color: #ccc;'>A phone extension has been requested for a new staff member during onboarding.</p>
            <div style='background: $cardBg; border-left: 4px solid $primary; padding: 20px; margin: 20px 0;'>
                <p style='margin: 0 0 10px 0;'><strong>Name:</strong> $staffName</p>
                <p style='margin: 0 0 10px 0;'><strong>Email:</strong> $staffEmail</p>
                <p style='margin: 0 0 10px 0;'><strong>Role:</strong> $staffRole</p>
                <p style='margin: 0 0 10px 0;'><strong>Job Title:</strong> $staffTitle</p>
                <p style='margin: 0;'><strong>Start Date:</strong> $staffStart</p>
            </div>
            <p style='color: #ccc;'>Please provision a phone extension and update their SIP settings in the system.</p>
            $footer
        </div>";
    }
    
    // 5. SMTP DIAGNOSTIC TEST
    elseif ($type == 'test') {
        $subject = "SMTP Connection Test";
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $successClr; margin-top: 0;'>✔ Connection Successful</h2>
            <p style='color: #ccc;'>Your email system is configured correctly.</p>
            <div style='background: $cardBg; padding: 15px; border-radius: 6px; margin: 20px 0; font-family: monospace; font-size: 12px; color: $textMuted;'>
                <strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "
            </div>
            $footer
        </div>";
    }
    
    // 6. SYSTEM NOTIFICATION (Maintenance, promotions, announcements)
    elseif ($type == 'system_notification') {
        $notif_type = $data['notification_type'] ?? 'info';
        $title = htmlspecialchars($data['title'] ?? 'System Notification');
        $message = htmlspecialchars($data['message'] ?? '');
        $name = htmlspecialchars($data['name'] ?? 'Athlete');
        
        // Set subject and color based on notification type (whitelist approach for safety)
        $subject = "Arctic Wolves: " . $title;
        $color_map = [
            'info' => $primary,
            'maintenance' => $warningClr,
            'warning' => $warningClr,
            'alert' => $errorClr
        ];
        $icon_map = [
            'info' => '&#9432;',
            'maintenance' => '&#9881;',
            'warning' => '&#9888;',
            'alert' => '&#10071;'
        ];
        $color = isset($color_map[$notif_type]) ? $color_map[$notif_type] : $color_map['info'];
        $icon = isset($icon_map[$notif_type]) ? $icon_map[$notif_type] : $icon_map['info'];
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $color; margin-top: 0;'>$icon $title</h2>
            <p style='color: #ccc;'>Hi $name,</p>
            <div style='background: $cardBg; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid $color;'>
                <p style='color: #e2e8f0; margin: 0; line-height: 1.6;'>$message</p>
            </div>
            <p style='color: $textMuted; font-size: 13px;'>This is an automated system notification from Arctic Wolves Performance.</p>
            $footer
        </div>";
    }
    
    // 7. GENERAL NOTIFICATION (For createNotification function)
    elseif ($type == 'notification') {
        $title = htmlspecialchars($data['title'] ?? 'Notification');
        $message = htmlspecialchars($data['message'] ?? '');
        $name = htmlspecialchars($data['name'] ?? 'Athlete');
        $link = $data['link'] ?? null;
        
        $subject = "Arctic Wolves: " . $title;
        
        $linkHtml = '';
        if ($link) {
            $linkHtml = "<p style='margin-top: 20px;'><a href='" . htmlspecialchars($link) . "' style='display: inline-block; background: $primary; color: #fff; padding: 12px 24px; border-radius: 6px; text-decoration: none; font-weight: bold;'>View Details</a></p>";
        }
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $primary; margin-top: 0;'>$title</h2>
            <p style='color: #ccc;'>Hi $name,</p>
            <div style='background: $cardBg; padding: 20px; margin: 20px 0; border-radius: 6px; border-left: 4px solid $primary;'>
                <p style='color: #e2e8f0; margin: 0; line-height: 1.6;'>$message</p>
            </div>
            $linkHtml
            $footer
        </div>";
    }
    
    // 8. EMAIL CHANGE CONFIRMATION
    elseif ($type == 'email_change_confirmation') {
        $subject = "Confirm Email Address Change";
        $name = htmlspecialchars($data['name'] ?? 'User');
        $old_email = htmlspecialchars($data['old_email'] ?? '');
        $new_email = htmlspecialchars($data['new_email'] ?? '');
        $confirm_link = $data['confirm_link'] ?? '#';
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $primary; margin-top: 0;'>Email Change Request</h2>
            <p style='color: #ccc;'>Hi $name,</p>
            <p style='color: #ccc;'>We received a request to change your email address from:</p>
            <div style='background: $cardBg; padding: 20px; margin: 20px 0; border-radius: 6px; border: 1px solid $borderClr;'>
                <p style='margin: 0 0 10px 0; color: $textMuted;'>Current email: <strong style='color: #fff;'>$old_email</strong></p>
                <p style='margin: 0; color: $textMuted;'>New email: <strong style='color: $primary;'>$new_email</strong></p>
            </div>
            <p style='color: #ccc;'>If you made this request, please click the button below to confirm the change:</p>
            <p style='margin-top: 25px; text-align: center;'>
                <a href='$confirm_link' style='display: inline-block; background: $primary; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Confirm Email Change</a>
            </p>
            <p style='color: $errorClr; font-size: 13px; margin-top: 25px;'>⚠️ If you did not request this change, please ignore this email. Your email address will remain unchanged.</p>
            <p style='color: #666; font-size: 12px; margin-top: 20px;'>This link will expire in 24 hours.</p>
            $footer
        </div>";
    }
    
    // 9. E-SIGNATURE REQUEST
    elseif ($type == 'esignature_request') {
        $name = htmlspecialchars($data['name'] ?? 'Employee');
        $signing_url = $data['signing_url'] ?? '#';
        $contract_title = htmlspecialchars($data['contract_title'] ?? 'Employment Contract');
        
        $subject = "Action Required: Sign Your " . $contract_title;
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $primary; margin-top: 0;'>📝 Contract Ready for Signature</h2>
            <p style='color: #ccc;'>Hi $name,</p>
            <p style='color: #ccc;'>Your <strong style='color: #fff;'>$contract_title</strong> is ready for your electronic signature.</p>
            <div style='background: $cardBg; padding: 20px; margin: 25px 0; border-radius: 6px; border-left: 4px solid $primary;'>
                <p style='color: #e2e8f0; margin: 0 0 15px 0;'>Please review and sign your contract by clicking the button below:</p>
                <a href='$signing_url' style='display: inline-block; background: $primary; color: #fff; padding: 14px 32px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Review & Sign Contract</a>
            </div>
            <p style='color: $textMuted; font-size: 13px;'>This signing link will expire in 7 days. If you have any questions, please contact HR.</p>
            <p style='color: $errorClr; font-size: 12px; margin-top: 20px;'>⚠️ If you did not expect this email, please contact us immediately.</p>
            $footer
        </div>";
    }
    
    // 10. CONTRACT SIGNED CONFIRMATION
    elseif ($type == 'contract_signed') {
        $name = htmlspecialchars($data['name'] ?? 'Employee');
        $contract_title = htmlspecialchars($data['contract_title'] ?? 'Employment Contract');
        
        $subject = "✅ Contract Signed: " . $contract_title;
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: $bg; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            $header
            <h2 style='color: $successClr; margin-top: 0;'>✔ Contract Successfully Signed</h2>
            <p style='color: #ccc;'>Hi $name,</p>
            <p style='color: #ccc;'>Thank you! Your <strong style='color: #fff;'>$contract_title</strong> has been successfully signed.</p>
            <div style='background: $cardBg; padding: 20px; margin: 25px 0; border-radius: 6px; border: 1px solid $borderClr;'>
                <p style='color: #e2e8f0; margin: 0;'><strong>Contract:</strong> $contract_title</p>
                <p style='color: #e2e8f0; margin: 10px 0 0 0;'><strong>Signed On:</strong> " . date('F j, Y') . "</p>
            </div>
            <p style='color: $textMuted; font-size: 13px;'>A copy of your signed contract has been securely stored. You can access it through your employee portal or contact HR for a copy.</p>
            $footer
        </div>";
    }
    
    // --- SENDING ---
    $mailer = new SmtpMailer();
    try {
        $mailer->send($to, $subject, $body, $config);
        // SUCCESS: Log with payload
        logEmailAttempt($to, $subject, $type, 'SUCCESS', null, $data);
        return true;
    } catch (Exception $e) {
        // FAIL: Log with error and payload
        logEmailAttempt($to, $subject, $type, 'FAILED', $e->getMessage(), $data);
        return false;
    }
}
?>