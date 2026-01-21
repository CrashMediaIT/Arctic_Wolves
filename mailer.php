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
        // Remove 'ssl://' or 'tls://' if accidentally typed in Host field
        $raw_host = $config['smtp_host'] ?? 'smtp.neo.space';
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
        $fromName  = !empty($config['smtp_from_name'])  ? $config['smtp_from_name']  : 'Crash Hockey System';

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
        return $pdo->query("SELECT * FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) { return []; }
}

/**
 * Log email attempts to DB.
 * Now supports saving the $data payload for Resend functionality.
 */
function logEmailAttempt($to, $subject, $type, $status, $errorMsg = null, $data = []) {
    global $pdo;
    try {
        $payload = json_encode($data); // Save data for resending
        
        $stmt = $pdo->prepare("INSERT INTO email_logs (recipient, subject, log_data, template_type, status, error_message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$to, $subject, $payload, $type, $status, $errorMsg]);
    } catch (Exception $e) { /* Silent fail if DB issue */ }
}

/**
 * Main function to send transactional emails
 */
function sendEmail($to, $type, $data) {
    $config = getEmailConfig();
    $year = date('Y');
    
    // --- TEMPLATE LOGIC ---
    $subject = "Crash Hockey Notification"; 
    $body = "";
    
    // Common Footer
    $footer = "
    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #333; text-align: center; color: #555; font-size: 11px;'>
        &copy; $year Crash Hockey Performance. All rights reserved.<br>
        <a href='https://crashhockey.ca' style='color: #555; text-decoration: none;'>crashhockey.ca</a>
    </div>";

    // 1. VERIFICATION CODE (Self-Registration)
    if ($type == 'verification') {
        $subject = "Verify Your Account";
        $code = $data['code'] ?? 'Error';
        $name = htmlspecialchars($data['name'] ?? 'Athlete');
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: #06080b; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #00ff88; margin-top: 0;'>Welcome, $name!</h2>
            <p style='color: #ccc;'>Please verify your email address to activate your account.</p>
            <div style='background: #1e293b; padding: 20px; text-align: center; margin: 30px 0; border-radius: 6px; border: 1px solid #333;'>
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
        
        $body = "
        <div style='font-family: Arial, sans-serif; background: #06080b; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #3b82f6; margin-top: 0;'>Welcome to the Team, $name!</h2>
            <p style='color: #ccc;'>Your account has been created. Please login with the details below:</p>
            <div style='background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; padding: 20px; margin: 20px 0;'>
                <p style='margin: 0 0 10px 0;'><strong>Email:</strong> $email</p>
                <p style='margin: 0;'><strong>Password:</strong> $pass</p>
            </div>
            <p style='font-size:12px; color:#999;'>You will be asked to change this password on first login.</p>
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
        <div style='font-family: Arial, sans-serif; background: #06080b; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #00ff88; margin-top: 0;'>Payment Confirmed</h2>
            <p style='color: #ccc;'>Thank you. Your booking has been secured.</p>
            
            <div style='background: #1e293b; padding: 20px; border-radius: 6px; margin: 20px 0; border: 1px solid #333;'>
                <table style='width: 100%; border-collapse: collapse; color: #fff;'>
                    <tr>
                        <td style='padding: 8px 0; color: #94a3b8; font-size:13px;'>Session</td>
                        <td style='padding: 8px 0; text-align: right; font-weight: bold;'>{$data['session_title']}</td>
                    </tr>
                    <tr>
                        <td style='padding: 8px 0; color: #94a3b8; font-size:13px;'>Date</td>
                        <td style='padding: 8px 0; text-align: right;'>$date</td>
                    </tr>
                    <tr style='border-top: 1px solid #475569;'>
                        <td style='padding: 15px 0 0 0; color: #fff; font-weight: bold;'>Total Paid</td>
                        <td style='padding: 15px 0 0 0; text-align: right; font-weight: bold; color: #00ff88; font-size: 18px;'>$$amount</td>
                    </tr>
                </table>
            </div>
            
            <p style='font-size: 11px; color: #64748b; text-align: center;'>Transaction ID: $trans_id</p>
            $footer
        </div>";
    }

    // 4. PASSWORD RESET
    elseif ($type == 'password_reset') {
        $subject = "Reset Password";
        $code = $data['code'] ?? '---';
        $body = "
        <div style='font-family: Arial, sans-serif; background: #06080b; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #6B46C1; margin-top: 0;'>Password Reset</h2>
            <p style='color:#ccc;'>Use this code to reset your password:</p>
            <div style='background: #1e293b; padding: 20px; text-align: center; margin: 20px 0; border-radius: 6px; border: 1px solid #6B46C1;'>
                <span style='font-size: 28px; font-weight: 800; color: #fff;'>$code</span>
            </div>
            $footer
        </div>";
    } 
    
    // 5. SMTP DIAGNOSTIC TEST
    elseif ($type == 'test') {
        $subject = "SMTP Connection Test";
        $body = "
        <div style='font-family: Arial, sans-serif; background: #06080b; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #00ff88; margin-top: 0;'>✔ Connection Successful</h2>
            <p style='color: #ccc;'>Your email system is configured correctly.</p>
            <div style='background: rgba(255,255,255,0.05); padding: 15px; border-radius: 6px; margin: 20px 0; font-family: monospace; font-size: 12px; color: #94a3b8;'>
                <strong>Timestamp:</strong> " . date('Y-m-d H:i:s') . "
            </div>
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