<?php
// process_settings.php - Handle system settings updates
session_start();
require 'db_config.php';
require 'security.php';
require 'cloud_config.php';

setSecurityHeaders();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

$action = $_POST['action'] ?? '';

// Determine if we should return JSON or redirect
$json_actions = ['test_nextcloud', 'test_smtp'];
$is_json = in_array($action, $json_actions);

if ($is_json) {
    header('Content-Type: application/json');
}

try {
    checkCsrfToken();
    
    switch ($action) {
        case 'update_general':
            $site_name = trim($_POST['site_name']);
            $timezone = trim($_POST['timezone']);
            $language = trim($_POST['language']);
            
            updateSetting($pdo, 'site_name', $site_name);
            updateSetting($pdo, 'timezone', $timezone);
            updateSetting($pdo, 'language', $language);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_smtp':
            $smtp_host = trim($_POST['smtp_host']);
            $smtp_port = trim($_POST['smtp_port']);
            $smtp_encryption = trim($_POST['smtp_encryption']);
            $smtp_user = trim($_POST['smtp_user']);
            $smtp_pass = trim($_POST['smtp_pass']);
            $smtp_from_email = trim($_POST['smtp_from_email']);
            $smtp_from_name = trim($_POST['smtp_from_name']);
            
            updateSetting($pdo, 'smtp_host', $smtp_host);
            updateSetting($pdo, 'smtp_port', $smtp_port);
            updateSetting($pdo, 'smtp_encryption', $smtp_encryption);
            updateSetting($pdo, 'smtp_user', $smtp_user);
            if (!empty($smtp_pass)) {
                updateSetting($pdo, 'smtp_pass', $smtp_pass);
            }
            updateSetting($pdo, 'smtp_from_email', $smtp_from_email);
            updateSetting($pdo, 'smtp_from_name', $smtp_from_name);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'test_smtp':
            $test_email = trim($_POST['test_email']);
            require_once __DIR__ . '/mailer.php';
            
            $result = sendEmail($test_email, 'test', []);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Test email sent successfully']);
            } else {
                $stmt = $pdo->prepare("SELECT error_message FROM email_logs WHERE recipient = ? AND status = 'FAILED' ORDER BY sent_at DESC LIMIT 1");
                $stmt->execute([$test_email]);
                $error = $stmt->fetchColumn();
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to send test email']);
            }
            exit;
            
        case 'update_nextcloud':
            $url = trim($_POST['nextcloud_url']);
            $username = trim($_POST['nextcloud_username']);
            $password = trim($_POST['nextcloud_password']);
            $folder = trim($_POST['nextcloud_receipt_folder']);
            $webdav_path = trim($_POST['nextcloud_webdav_path']);
            $ocr_enabled = isset($_POST['nextcloud_ocr_enabled']) ? '1' : '0';
            
            updateSetting($pdo, 'nextcloud_url', $url);
            updateSetting($pdo, 'nextcloud_username', $username);
            if (!empty($password)) {
                // Encrypt password before storing
                $encrypted_password = encryptPassword($password);
                updateSetting($pdo, 'nextcloud_password', $encrypted_password);
            }
            updateSetting($pdo, 'nextcloud_receipt_folder', $folder);
            updateSetting($pdo, 'nextcloud_webdav_path', $webdav_path);
            updateSetting($pdo, 'nextcloud_ocr_enabled', $ocr_enabled);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'test_nextcloud':
            $settings = [
                'nextcloud_url' => trim($_POST['nextcloud_url']),
                'nextcloud_username' => trim($_POST['nextcloud_username']),
                'nextcloud_password' => trim($_POST['nextcloud_password']),
                'nextcloud_receipt_folder' => trim($_POST['nextcloud_receipt_folder']),
                'nextcloud_webdav_path' => trim($_POST['nextcloud_webdav_path'])
            ];
            
            $result = testNextcloudConnection($settings);
            echo json_encode($result);
            exit;
            
        case 'update_payments':
            $tax_name = trim($_POST['tax_name']);
            $tax_rate = floatval($_POST['tax_rate']);
            
            updateSetting($pdo, 'tax_name', $tax_name);
            updateSetting($pdo, 'tax_rate', $tax_rate);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_security':
            $session_timeout = intval($_POST['session_timeout_minutes']);
            
            updateSetting($pdo, 'session_timeout_minutes', $session_timeout);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_advanced':
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $debug_mode = isset($_POST['debug_mode']) ? '1' : '0';
            
            updateSetting($pdo, 'maintenance_mode', $maintenance_mode);
            updateSetting($pdo, 'debug_mode', $debug_mode);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_google_maps':
            $api_key = trim($_POST['google_maps_api_key']);
            updateSetting($pdo, 'google_maps_api_key', $api_key);
            
            header('Location: dashboard.php?page=settings&success=1');
            exit;
            
        case 'update_mileage_rates':
            $rate_km = floatval($_POST['mileage_rate_per_km']);
            $rate_mile = floatval($_POST['mileage_rate_per_mile']);
            
            updateSetting($pdo, 'mileage_rate_per_km', $rate_km);
            updateSetting($pdo, 'mileage_rate_per_mile', $rate_mile);
            
            header('Location: dashboard.php?page=settings&success=1');
            exit;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    if ($is_json) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header('Location: dashboard.php?page=admin_settings&error=' . urlencode($e->getMessage()));
        exit;
    }
}

/**
 * Update or insert a system setting
 */
function updateSetting($pdo, $key, $value) {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value)
        VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = ?
    ");
    $stmt->execute([$key, $value, $value]);
}

/**
 * Encrypt password using AES-256-CBC
 */
function encryptPassword($password) {
    // Use a key from environment or generate a persistent one
    $key_file = __DIR__ . '/.nextcloud_key';
    if (!file_exists($key_file)) {
        $key = bin2hex(openssl_random_bytes(32));
        file_put_contents($key_file, $key);
        chmod($key_file, 0600);
    } else {
        $key = file_get_contents($key_file);
    }
    
    $key_hash = hash('sha256', $key, true);
    $iv = openssl_random_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key_hash, 0, $iv);
    return base64_encode($iv . '::' . $encrypted);
}

/**
 * Decrypt password
 */
function decryptPassword($encrypted_data) {
    $key_file = __DIR__ . '/.nextcloud_key';
    if (!file_exists($key_file)) {
        return '';
    }
    
    $key = file_get_contents($key_file);
    $key_hash = hash('sha256', $key, true);
    $parts = explode('::', base64_decode($encrypted_data), 2);
    if (count($parts) === 2) {
        $iv = $parts[0];
        $encrypted = $parts[1];
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key_hash, 0, $iv);
    }
    return '';
}
?>
