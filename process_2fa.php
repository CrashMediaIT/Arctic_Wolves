<?php
/**
 * Two-Factor Authentication (2FA) Processor
 * Supports TOTP-based app authentication (Google Authenticator, Authy, etc.)
 * and hardware security keys via the same TOTP standard
 */

session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/error_logger.php';

/**
 * Generate a random Base32 secret for TOTP
 */
function generateTOTPSecret($length = 20) {
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = '';
    $random = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $chars[ord($random[$i]) % 32];
    }
    return $secret;
}

/**
 * Decode a Base32 encoded string
 */
function base32Decode($input) {
    $map = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $input = strtoupper(trim($input));
    $input = str_replace(['=', ' ', '-'], '', $input);
    
    $buffer = 0;
    $bitsLeft = 0;
    $output = '';
    
    for ($i = 0; $i < strlen($input); $i++) {
        $val = strpos($map, $input[$i]);
        if ($val === false) continue;
        $buffer = ($buffer << 5) | $val;
        $bitsLeft += 5;
        if ($bitsLeft >= 8) {
            $bitsLeft -= 8;
            $output .= chr(($buffer >> $bitsLeft) & 0xFF);
        }
    }
    return $output;
}

/**
 * Generate a TOTP code for a given secret and time
 */
function generateTOTP($secret, $timeSlice = null) {
    if ($timeSlice === null) {
        $timeSlice = floor(time() / 30);
    }
    
    $secretKey = base32Decode($secret);
    $time = pack('N*', 0) . pack('N*', $timeSlice);
    $hash = hash_hmac('sha1', $time, $secretKey, true);
    $offset = ord($hash[strlen($hash) - 1]) & 0x0F;
    $code = (
        ((ord($hash[$offset]) & 0x7F) << 24) |
        ((ord($hash[$offset + 1]) & 0xFF) << 16) |
        ((ord($hash[$offset + 2]) & 0xFF) << 8) |
        (ord($hash[$offset + 3]) & 0xFF)
    ) % 1000000;
    
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Verify a TOTP code (checks current and adjacent time windows)
 */
function verifyTOTP($secret, $code, $window = 1) {
    $code = trim($code);
    if (strlen($code) !== 6 || !ctype_digit($code)) {
        return false;
    }
    
    $currentTimeSlice = floor(time() / 30);
    
    for ($i = -$window; $i <= $window; $i++) {
        $expected = generateTOTP($secret, $currentTimeSlice + $i);
        if (hash_equals($expected, $code)) {
            return true;
        }
    }
    return false;
}

/**
 * Generate otpauth URI for QR code generation
 */
function generateOTPAuthURI($secret, $email, $issuer = 'Arctic Wolves') {
    return 'otpauth://totp/' . rawurlencode($issuer) . ':' . rawurlencode($email) 
        . '?secret=' . $secret 
        . '&issuer=' . rawurlencode($issuer) 
        . '&digits=6&period=30&algorithm=SHA1';
}

/**
 * Generate backup codes
 */
function generateBackupCodes($count = 8) {
    $codes = [];
    for ($i = 0; $i < $count; $i++) {
        $codes[] = strtoupper(bin2hex(random_bytes(4)));
    }
    return $codes;
}

// Only handle API requests when called directly
if (basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
    header('Content-Type: application/json');
    
    // Require authentication
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Not authenticated']);
        exit;
    }
    
    $user_id = intval($_SESSION['user_id']);
    
    // Handle GET requests
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'get_status') {
            try {
                $stmt = $pdo->prepare("SELECT is_enabled, method, verified_at FROM two_factor_auth WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $tfa = $stmt->fetch(PDO::FETCH_ASSOC);
                
                echo json_encode([
                    'success' => true,
                    'enabled' => $tfa ? (bool)$tfa['is_enabled'] : false,
                    'method' => $tfa['method'] ?? null,
                    'verified_at' => $tfa['verified_at'] ?? null
                ]);
            } catch (PDOException $e) {
                echo json_encode(['success' => true, 'enabled' => false, 'method' => null]);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    // Handle POST requests
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate CSRF token
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCSRFToken($token)) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        
        if ($action === 'setup') {
            // Generate new secret and show setup info
            $method = $_POST['method'] ?? 'app';
            if (!in_array($method, ['app', 'hardware'])) {
                $method = 'app';
            }
            
            try {
                $secret = generateTOTPSecret();
                $email = $_SESSION['user_email'] ?? '';
                $uri = generateOTPAuthURI($secret, $email);
                $backupCodes = generateBackupCodes();
                
                // Store pending setup in database (not enabled yet)
                $stmt = $pdo->prepare("
                    INSERT INTO two_factor_auth (user_id, secret, method, is_enabled, backup_codes)
                    VALUES (?, ?, ?, 0, ?)
                    ON DUPLICATE KEY UPDATE secret = VALUES(secret), method = VALUES(method), 
                    is_enabled = 0, backup_codes = VALUES(backup_codes), verified_at = NULL
                ");
                $hashedCodes = json_encode(array_map(function($code) {
                    return password_hash($code, PASSWORD_DEFAULT);
                }, $backupCodes));
                $stmt->execute([$user_id, $secret, $method, $hashedCodes]);
                
                echo json_encode([
                    'success' => true,
                    'secret' => $secret,
                    'otpauth_uri' => $uri,
                    'backup_codes' => $backupCodes,
                    'method' => $method
                ]);
            } catch (PDOException $e) {
                error_log("2FA setup error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to setup 2FA']);
            }
            exit;
        }
        
        if ($action === 'verify_setup') {
            // Verify the TOTP code to confirm setup
            $code = trim($_POST['code'] ?? '');
            
            try {
                $stmt = $pdo->prepare("SELECT secret FROM two_factor_auth WHERE user_id = ? AND is_enabled = 0");
                $stmt->execute([$user_id]);
                $tfa = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$tfa) {
                    echo json_encode(['success' => false, 'message' => 'No pending 2FA setup found']);
                    exit;
                }
                
                if (verifyTOTP($tfa['secret'], $code)) {
                    $stmt = $pdo->prepare("UPDATE two_factor_auth SET is_enabled = 1, verified_at = NOW() WHERE user_id = ?");
                    $stmt->execute([$user_id]);
                    
                    ErrorLogger::security("2FA enabled", ['user_id' => $user_id]);
                    logSecurityEvent('2fa_enabled', 'User enabled two-factor authentication', ['user_id' => $user_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Two-factor authentication enabled successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Invalid verification code. Please try again.']);
                }
            } catch (PDOException $e) {
                error_log("2FA verify error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Verification failed']);
            }
            exit;
        }
        
        if ($action === 'verify_login') {
            // Verify TOTP during login
            $code = trim($_POST['code'] ?? '');
            $verify_user_id = intval($_SESSION['2fa_pending_user_id'] ?? 0);
            
            if (!$verify_user_id) {
                echo json_encode(['success' => false, 'message' => 'No pending 2FA verification']);
                exit;
            }
            
            try {
                $stmt = $pdo->prepare("SELECT secret, backup_codes FROM two_factor_auth WHERE user_id = ? AND is_enabled = 1");
                $stmt->execute([$verify_user_id]);
                $tfa = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$tfa) {
                    echo json_encode(['success' => false, 'message' => '2FA not configured']);
                    exit;
                }
                
                $verified = false;
                
                // Try TOTP code first
                if (verifyTOTP($tfa['secret'], $code)) {
                    $verified = true;
                } else {
                    // Try backup codes
                    $storedCodes = json_decode($tfa['backup_codes'], true);
                    if (is_array($storedCodes)) {
                        foreach ($storedCodes as $idx => $hashedCode) {
                            if (password_verify($code, $hashedCode)) {
                                $verified = true;
                                // Remove used backup code
                                unset($storedCodes[$idx]);
                                $stmt = $pdo->prepare("UPDATE two_factor_auth SET backup_codes = ? WHERE user_id = ?");
                                $stmt->execute([json_encode(array_values($storedCodes)), $verify_user_id]);
                                break;
                            }
                        }
                    }
                }
                
                if ($verified) {
                    // Complete login - restore full session
                    $_SESSION['user_id'] = $_SESSION['2fa_pending_user_id'];
                    $_SESSION['user_name'] = $_SESSION['2fa_pending_user_name'];
                    $_SESSION['user_role'] = $_SESSION['2fa_pending_user_role'];
                    $_SESSION['user_email'] = $_SESSION['2fa_pending_user_email'];
                    $_SESSION['logged_in'] = true;
                    $_SESSION['2fa_verified'] = true;
                    
                    // Clean up pending state
                    unset($_SESSION['2fa_pending_user_id']);
                    unset($_SESSION['2fa_pending_user_name']);
                    unset($_SESSION['2fa_pending_user_role']);
                    unset($_SESSION['2fa_pending_user_email']);
                    unset($_SESSION['2fa_pending']);
                    
                    ErrorLogger::security("2FA verification successful", ['user_id' => $_SESSION['user_id']]);
                    
                    // PWA-aware redirect after 2FA
                    require_once __DIR__ . '/pwa_detect.php';
                    $pref = getPwaViewPreference();
                    if ($pref === 'pwa') {
                        $redirectTarget = 'pwa.php';
                    } elseif ($pref === 'pwa_tablet') {
                        $redirectTarget = 'pwa_tablet.php';
                    } else {
                        $redirectTarget = 'dashboard.php';
                    }
                    echo json_encode(['success' => true, 'redirect' => $redirectTarget]);
                } else {
                    ErrorLogger::security("2FA verification failed", ['user_id' => $verify_user_id]);
                    echo json_encode(['success' => false, 'message' => 'Invalid code. Please try again.']);
                }
            } catch (PDOException $e) {
                error_log("2FA login verify error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Verification failed']);
            }
            exit;
        }
        
        if ($action === 'disable') {
            // Disable 2FA
            $code = trim($_POST['code'] ?? '');
            
            try {
                $stmt = $pdo->prepare("SELECT secret FROM two_factor_auth WHERE user_id = ? AND is_enabled = 1");
                $stmt->execute([$user_id]);
                $tfa = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$tfa) {
                    echo json_encode(['success' => false, 'message' => '2FA not enabled']);
                    exit;
                }
                
                if (!verifyTOTP($tfa['secret'], $code)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid code']);
                    exit;
                }
                
                $stmt = $pdo->prepare("DELETE FROM two_factor_auth WHERE user_id = ?");
                $stmt->execute([$user_id]);
                
                ErrorLogger::security("2FA disabled", ['user_id' => $user_id]);
                logSecurityEvent('2fa_disabled', 'User disabled two-factor authentication', ['user_id' => $user_id]);
                
                echo json_encode(['success' => true, 'message' => 'Two-factor authentication disabled']);
            } catch (PDOException $e) {
                error_log("2FA disable error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to disable 2FA']);
            }
            exit;
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
        exit;
    }
    
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
}
