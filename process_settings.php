<?php
// process_settings.php - Handle system settings updates
session_start();
require 'db_config.php';
require 'security.php';
require 'cloud_config.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/lib/blocklist.php';

setSecurityHeaders();

if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// Determine if we should return JSON or redirect
$json_actions = ['test_nextcloud', 'test_smtp', 'test_github', 'check_updates', 'apply_updates', 'test_nextcloud_backup', 'sync_to_backup', 'check_stripe_updates', 'update_stripe_library', 'test_docuseal', 'test_google_maps', 'create_restriction', 'remove_restriction', 'add_blocklist_entry', 'remove_blocklist_entry', 'add_pos_whitelist_entry', 'remove_pos_whitelist_entry', 'toggle_pos_whitelist_entry'];
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
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_general',
                'settings' => ['site_name' => $site_name, 'timezone' => $timezone, 'language' => $language]
            ]);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;

        case 'toggle_pii_encryption':
            $pii_enabled = isset($_POST['pii_encryption_enabled']) ? '1' : '0';
            updateSetting($pdo, 'pii_encryption_enabled', $pii_enabled);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'toggle_pii_encryption',
                'pii_encryption_enabled' => $pii_enabled
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=encryption&success=1');
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
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_smtp',
                'settings' => ['smtp_host' => $smtp_host, 'smtp_port' => $smtp_port, 'smtp_encryption' => $smtp_encryption, 'smtp_user' => $smtp_user, 'smtp_from_email' => $smtp_from_email, 'smtp_from_name' => $smtp_from_name]
            ]);
            
            // Redirect back to the appropriate page
            $redirect_page = isset($_POST['redirect_page']) ? $_POST['redirect_page'] : 'admin_settings';
            if ($redirect_page === 'system_tools') {
                header('Location: dashboard.php?page=system_tools&tab=smtp&success=1');
            } else {
                header('Location: dashboard.php?page=admin_settings&success=1');
            }
            exit;
            
        case 'test_smtp':
            $test_email = trim($_POST['test_email']);
            require_once __DIR__ . '/mailer.php';
            
            $result = sendEmail($test_email, 'test', []);
            if ($result) {
                echo json_encode(['success' => true, 'message' => 'Test email sent successfully']);
            } else {
                $stmt = $pdo->prepare("SELECT error_message FROM email_logs WHERE to_email = ? AND status = 'failed' ORDER BY created_at DESC LIMIT 1");
                $stmt->execute([$test_email]);
                $error = $stmt->fetchColumn();
                echo json_encode(['success' => false, 'message' => $error ?: 'Failed to send test email']);
            }
            exit;
            
        case 'update_nextcloud':
            $url = trim($_POST['nextcloud_url']);
            $username = trim($_POST['nextcloud_username']);
            $password = trim($_POST['nextcloud_password']);
            $folder = trim($_POST['nextcloud_receipt_folder'] ?? $_POST['nextcloud_folder'] ?? '');
            $webdav_path = trim($_POST['nextcloud_webdav_path'] ?? '');
            $ocr_enabled = isset($_POST['nextcloud_ocr_enabled']) ? '1' : '0';
            $auto_sync = isset($_POST['nextcloud_auto_sync']) ? '1' : '0';
            
            // Directory settings
            $backups_dir = trim($_POST['nextcloud_backups_dir'] ?? '/Arctic_Wolves/Backups');
            $videos_dir = trim($_POST['nextcloud_videos_dir'] ?? '/Arctic_Wolves/Videos');
            $receipts_dir = trim($_POST['nextcloud_receipts_dir'] ?? '/Arctic_Wolves/Receipts');
            $documents_dir = trim($_POST['nextcloud_documents_dir'] ?? '/Arctic_Wolves/Documents');
            $hr_dir = trim($_POST['nextcloud_hr_dir'] ?? '/Arctic_Wolves/HR');
            $terminations_dir = trim($_POST['nextcloud_terminations_dir'] ?? '/Arctic_Wolves/HR/Terminations');
            $contracts_dir = trim($_POST['nextcloud_contracts_dir'] ?? '/accounting/contracts');
            
            // Sync options
            $sync_backups = isset($_POST['sync_backups']) ? '1' : '0';
            $sync_videos = isset($_POST['sync_videos']) ? '1' : '0';
            $sync_receipts = isset($_POST['sync_receipts']) ? '1' : '0';
            $sync_documents = isset($_POST['sync_documents']) ? '1' : '0';
            $sync_hr = isset($_POST['sync_hr']) ? '1' : '0';
            $sync_terminations = isset($_POST['sync_terminations']) ? '1' : '0';
            $sync_contracts = isset($_POST['sync_contracts']) ? '1' : '0';
            
            updateSetting($pdo, 'nextcloud_url', $url);
            updateSetting($pdo, 'nextcloud_username', $username);
            // Only update password if a new one is provided
            if (!empty($password)) {
                // Encrypt password before storing
                $encrypted_password = encryptPassword($password);
                updateSetting($pdo, 'nextcloud_password', $encrypted_password);
            }
            updateSetting($pdo, 'nextcloud_receipt_folder', $folder);
            updateSetting($pdo, 'nextcloud_webdav_path', $webdav_path);
            updateSetting($pdo, 'nextcloud_ocr_enabled', $ocr_enabled);
            updateSetting($pdo, 'nextcloud_auto_sync', $auto_sync);
            
            // Save directory settings
            updateSetting($pdo, 'nextcloud_backups_dir', $backups_dir);
            updateSetting($pdo, 'nextcloud_videos_dir', $videos_dir);
            updateSetting($pdo, 'nextcloud_receipts_dir', $receipts_dir);
            updateSetting($pdo, 'nextcloud_documents_dir', $documents_dir);
            updateSetting($pdo, 'nextcloud_hr_dir', $hr_dir);
            updateSetting($pdo, 'nextcloud_terminations_dir', $terminations_dir);
            updateSetting($pdo, 'nextcloud_contracts_dir', $contracts_dir);
            
            // Save sync options
            updateSetting($pdo, 'sync_backups', $sync_backups);
            updateSetting($pdo, 'sync_videos', $sync_videos);
            updateSetting($pdo, 'sync_receipts', $sync_receipts);
            updateSetting($pdo, 'sync_documents', $sync_documents);
            updateSetting($pdo, 'sync_hr', $sync_hr);
            updateSetting($pdo, 'sync_terminations', $sync_terminations);
            updateSetting($pdo, 'sync_contracts', $sync_contracts);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_nextcloud',
                'settings' => ['nextcloud_url' => $url, 'nextcloud_username' => $username, 'nextcloud_ocr_enabled' => $ocr_enabled, 'nextcloud_auto_sync' => $auto_sync]
            ]);
            
            // Redirect back to the appropriate page
            $redirect_page = isset($_POST['redirect_page']) ? $_POST['redirect_page'] : 'admin_settings';
            if ($redirect_page === 'system_tools') {
                header('Location: dashboard.php?page=system_tools&tab=nextcloud&success=1');
            } else {
                header('Location: dashboard.php?page=admin_settings&success=1');
            }
            exit;
            
        case 'test_nextcloud':
            $password = trim($_POST['nextcloud_password']);
            // If no password provided, use the stored encrypted password from database
            if (empty($password)) {
                $stored_pass_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'nextcloud_password'");
                $stored_pass_stmt->execute();
                $encrypted_pass = $stored_pass_stmt->fetchColumn();
                if (!empty($encrypted_pass)) {
                    $password = decryptPassword($encrypted_pass);
                }
            }
            $settings = [
                'nextcloud_url' => trim($_POST['nextcloud_url']),
                'nextcloud_username' => trim($_POST['nextcloud_username']),
                'nextcloud_password' => $password,
                'nextcloud_receipt_folder' => trim($_POST['nextcloud_receipt_folder']),
                'nextcloud_webdav_path' => trim($_POST['nextcloud_webdav_path'])
            ];
            
            $result = testNextcloudConnection($settings);
            echo json_encode($result);
            exit;
            
        case 'update_payments':
            // Stripe settings
            $stripe_publishable_key = trim($_POST['stripe_publishable_key']);
            $stripe_secret_key = trim($_POST['stripe_secret_key']);
            $currency = trim($_POST['currency']);
            
            // Tax settings
            $tax_name = trim($_POST['tax_name']);
            $tax_rate = floatval($_POST['tax_rate']);
            
            // Validate Stripe keys format
            if (!empty($stripe_publishable_key) && !preg_match('/^pk_(test|live)_[a-zA-Z0-9]+$/', $stripe_publishable_key)) {
                throw new Exception('Invalid Stripe publishable key format. Must start with pk_test_ or pk_live_');
            }
            if (!empty($stripe_secret_key) && !preg_match('/^sk_(test|live)_[a-zA-Z0-9]+$/', $stripe_secret_key)) {
                throw new Exception('Invalid Stripe secret key format. Must start with sk_test_ or sk_live_');
            }
            
            // Validate currency
            if (!in_array($currency, ['CAD', 'USD', 'EUR', 'GBP'])) {
                throw new Exception('Invalid currency code');
            }
            
            // Validate tax rate
            if ($tax_rate < 0 || $tax_rate > 100) {
                throw new Exception('Tax rate must be between 0 and 100');
            }
            
            // Update Stripe settings
            updateSetting($pdo, 'stripe_publishable_key', $stripe_publishable_key);
            // Only update secret key if a new one is provided
            if (!empty($stripe_secret_key)) {
                updateSetting($pdo, 'stripe_secret_key', $stripe_secret_key);
            }
            updateSetting($pdo, 'currency', $currency);
            
            // Update tax settings
            updateSetting($pdo, 'tax_name', $tax_name);
            updateSetting($pdo, 'tax_rate', $tax_rate);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_payments',
                'settings' => ['currency' => $currency, 'tax_name' => $tax_name, 'tax_rate' => $tax_rate, 'stripe_key_updated' => !empty($stripe_secret_key)]
            ]);
            
            // Redirect back to the appropriate page
            $redirect_page = isset($_POST['redirect_page']) ? $_POST['redirect_page'] : 'admin_settings';
            if ($redirect_page === 'system_tools') {
                header('Location: dashboard.php?page=system_tools&tab=payments&success=1');
            } else {
                header('Location: dashboard.php?page=admin_settings&success=1');
            }
            exit;
            
        case 'update_security':
            $session_timeout = intval($_POST['session_timeout_minutes']);
            
            updateSetting($pdo, 'session_timeout_minutes', $session_timeout);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_security',
                'settings' => ['session_timeout_minutes' => $session_timeout]
            ]);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_advanced':
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $debug_mode = isset($_POST['debug_mode']) ? '1' : '0';
            
            updateSetting($pdo, 'maintenance_mode', $maintenance_mode);
            updateSetting($pdo, 'debug_mode', $debug_mode);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_advanced',
                'settings' => ['maintenance_mode' => $maintenance_mode, 'debug_mode' => $debug_mode]
            ]);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_settings':
            // Handle general settings from system tools page
            $site_title = trim($_POST['site_title'] ?? 'Arctic Wolves');
            $site_email = trim($_POST['site_email'] ?? '');
            $session_duration = intval($_POST['session_duration'] ?? 60);
            $notifications_enabled = isset($_POST['notifications_enabled']) ? '1' : '0';
            $maintenance_mode = isset($_POST['maintenance_mode']) ? '1' : '0';
            $province = trim($_POST['province'] ?? 'ON');
            
            // Validate province code
            $valid_provinces = ['AB','BC','MB','NB','NL','NS','NT','NU','ON','PE','QC','SK','YT'];
            if (!in_array($province, $valid_provinces)) {
                $province = 'ON';
            }
            
            updateSetting($pdo, 'site_title', $site_title);
            updateSetting($pdo, 'site_email', $site_email);
            updateSetting($pdo, 'session_duration', $session_duration);
            updateSetting($pdo, 'notifications_enabled', $notifications_enabled);
            updateSetting($pdo, 'maintenance_mode', $maintenance_mode);
            updateSetting($pdo, 'province', $province);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_settings',
                'settings' => ['site_title' => $site_title, 'site_email' => $site_email, 'session_duration' => $session_duration, 'notifications_enabled' => $notifications_enabled, 'maintenance_mode' => $maintenance_mode, 'province' => $province]
            ]);
            
            header('Location: dashboard.php?page=system_tools&success=1');
            exit;
            
        case 'update_theme':
            // Handle theme settings from system tools page
            $primary_color = trim($_POST['primary_color'] ?? '#6B46C1');
            $accent_color = trim($_POST['accent_color'] ?? '#8B5CF6');
            $bg_color = trim($_POST['bg_color'] ?? '#06080b');
            
            updateSetting($pdo, 'primary_color', $primary_color);
            updateSetting($pdo, 'accent_color', $accent_color);
            updateSetting($pdo, 'background_color', $bg_color);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_theme',
                'settings' => ['primary_color' => $primary_color, 'accent_color' => $accent_color, 'bg_color' => $bg_color]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=theme&success=1');
            exit;
            
        case 'update_google_maps':
            $api_key = trim($_POST['google_maps_api_key']);
            updateSetting($pdo, 'google_maps_api_key', $api_key);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_google_maps',
                'settings' => ['google_maps_api_key' => '***updated***']
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=mileage&success=1');
            exit;
            
        case 'test_google_maps':
            $api_key = trim($_POST['google_maps_api_key'] ?? '');
            
            if (empty($api_key)) {
                echo json_encode(['success' => false, 'message' => 'Please enter a Google Maps API key first.']);
                exit;
            }
            
            // Test by sending a request to Google's Geocoding API server-side
            $test_url = 'https://maps.googleapis.com/maps/api/geocode/json?address=' . urlencode('Toronto,Canada') . '&key=' . urlencode($api_key);
            
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'ignore_errors' => true
                ]
            ]);
            
            $response = file_get_contents($test_url, false, $context);
            
            if ($response === false) {
                $error = error_get_last();
                $error_message = $error ? $error['message'] : 'Unknown error';
                error_log("Google Maps API test failed: " . $error_message);
                echo json_encode(['success' => false, 'message' => 'Failed to connect to Google Maps API. Please check your server network connection.']);
                exit;
            }
            
            $data = json_decode($response, true);
            
            if (!$data) {
                echo json_encode(['success' => false, 'message' => 'Invalid response from Google Maps API.']);
                exit;
            }
            
            $status = $data['status'] ?? 'UNKNOWN';
            
            if ($status === 'OK' || $status === 'ZERO_RESULTS') {
                echo json_encode(['success' => true, 'message' => 'Google Maps API key is valid and working correctly.']);
            } elseif ($status === 'REQUEST_DENIED') {
                $error_msg = $data['error_message'] ?? 'Please check your API key and ensure the Geocoding API is enabled.';
                echo json_encode(['success' => false, 'message' => 'API Key Invalid or Restricted: ' . $error_msg]);
            } else {
                $error_msg = $data['error_message'] ?? 'Please check your API key configuration.';
                echo json_encode(['success' => false, 'message' => 'API Test Result: ' . $status . ' - ' . $error_msg]);
            }
            exit;
            
        case 'update_mileage_rates':
            $rate_km = floatval($_POST['mileage_rate_per_km']);
            $rate_after_5000_km = floatval($_POST['mileage_rate_after_5000_per_km']);
            $rate_mile = floatval($_POST['mileage_rate_per_mile']);
            $mileage_unit = trim($_POST['mileage_unit'] ?? 'km');
            
            // Validate mileage unit
            if (!in_array($mileage_unit, ['km', 'miles'])) {
                $mileage_unit = 'km';
            }
            
            updateSetting($pdo, 'mileage_rate_per_km', $rate_km);
            updateSetting($pdo, 'mileage_rate_after_5000_per_km', $rate_after_5000_km);
            updateSetting($pdo, 'mileage_rate_per_mile', $rate_mile);
            updateSetting($pdo, 'mileage_unit', $mileage_unit);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_mileage_rates',
                'settings' => ['mileage_rate_per_km' => $rate_km, 'mileage_rate_after_5000_per_km' => $rate_after_5000_km, 'mileage_rate_per_mile' => $rate_mile, 'mileage_unit' => $mileage_unit]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=mileage&success=1');
            exit;
            
        case 'update_github_settings':
            $github_token = trim($_POST['github_token']);
            updateSetting($pdo, 'github_token', $github_token);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_github_settings',
                'settings' => ['github_token' => '***updated***']
            ]);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'test_github':
            require_once __DIR__ . '/lib/github_updater.php';
            $updater = new GitHubUpdater($pdo);
            $result = $updater->testGitHubConnection();
            echo json_encode($result);
            exit;
            
        case 'check_updates':
            require_once __DIR__ . '/lib/github_updater.php';
            $updater = new GitHubUpdater($pdo);
            $result = $updater->checkForUpdates();
            echo json_encode($result);
            exit;
            
        case 'apply_updates':
            require_once __DIR__ . '/lib/github_updater.php';
            $updater = new GitHubUpdater($pdo);
            $result = $updater->applyUpdates();
            echo json_encode($result);
            exit;
            
        case 'update_nextcloud_backup':
            $backup_enabled = isset($_POST['nextcloud_backup_enabled']) ? '1' : '0';
            $backup_url = trim($_POST['nextcloud_backup_url'] ?? '');
            $backup_username = trim($_POST['nextcloud_backup_username'] ?? '');
            $backup_password = trim($_POST['nextcloud_backup_password'] ?? '');
            $failover_timeout = intval($_POST['nextcloud_failover_timeout'] ?? 300);
            $sync_interval = intval($_POST['nextcloud_sync_interval'] ?? 60);
            
            updateSetting($pdo, 'nextcloud_backup_enabled', $backup_enabled);
            updateSetting($pdo, 'nextcloud_backup_url', $backup_url);
            updateSetting($pdo, 'nextcloud_backup_username', $backup_username);
            if (!empty($backup_password)) {
                $encrypted_password = encryptPassword($backup_password);
                updateSetting($pdo, 'nextcloud_backup_password', $encrypted_password);
            }
            updateSetting($pdo, 'nextcloud_failover_timeout', $failover_timeout);
            updateSetting($pdo, 'nextcloud_sync_interval', $sync_interval);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_nextcloud_backup',
                'settings' => ['nextcloud_backup_enabled' => $backup_enabled, 'nextcloud_backup_url' => $backup_url, 'nextcloud_backup_username' => $backup_username]
            ]);
            
            header('Location: dashboard.php?page=system_tools&tab=nextcloud&success=1');
            exit;
            
        case 'test_nextcloud_backup':
            $settings = [
                'nextcloud_url' => trim($_POST['nextcloud_backup_url'] ?? ''),
                'nextcloud_username' => trim($_POST['nextcloud_backup_username'] ?? ''),
                'nextcloud_password' => trim($_POST['nextcloud_backup_password'] ?? ''),
                'nextcloud_receipt_folder' => '/Arctic_Wolves',
                'nextcloud_webdav_path' => ''
            ];
            
            $result = testNextcloudConnection($settings);
            echo json_encode($result);
            exit;
            
        case 'sync_to_backup':
            // This would sync files from primary to backup - placeholder for now
            echo json_encode(['success' => true, 'message' => 'Sync to backup initiated. Files will be synchronized in the background.']);
            exit;
            
        case 'check_stripe_updates':
            // Check latest Stripe release via server-side request
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Arctic-Wolves-Updater',
                    'timeout' => 30
                ]
            ]);
            
            $release_info = @file_get_contents('https://api.github.com/repos/stripe/stripe-php/releases/latest', false, $context);
            if ($release_info === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch Stripe release info']);
                exit;
            }
            
            $release = json_decode($release_info, true);
            if (!isset($release['tag_name'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid response from GitHub']);
                exit;
            }
            
            echo json_encode([
                'success' => true,
                'tag_name' => $release['tag_name'],
                'name' => $release['name'] ?? 'Stripe PHP Library',
                'published_at' => $release['published_at'] ?? ''
            ]);
            exit;
            
        case 'update_stripe_library':
            // Update Stripe PHP library from GitHub
            $stripe_path = realpath(__DIR__ . '/stripe-php');
            if (!$stripe_path || strpos($stripe_path, realpath(__DIR__)) !== 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid stripe-php path']);
                exit;
            }
            $temp_path = sys_get_temp_dir() . '/stripe-php-' . time();
            
            // Get latest release info
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => 'User-Agent: Arctic-Wolves-Updater',
                    'timeout' => 30
                ]
            ]);
            
            $release_info = @file_get_contents('https://api.github.com/repos/stripe/stripe-php/releases/latest', false, $context);
            if ($release_info === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch latest Stripe release info']);
                exit;
            }
            
            $release = json_decode($release_info, true);
            if (!isset($release['zipball_url'])) {
                echo json_encode(['success' => false, 'message' => 'Could not find download URL for latest release']);
                exit;
            }
            
            // Download the release
            $zip_url = $release['zipball_url'];
            $zip_content = @file_get_contents($zip_url, false, $context);
            if ($zip_content === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to download Stripe library']);
                exit;
            }
            
            // Save and extract
            $zip_file = $temp_path . '.zip';
            if (file_put_contents($zip_file, $zip_content) === false) {
                echo json_encode(['success' => false, 'message' => 'Failed to save downloaded file']);
                exit;
            }
            
            $zip = new ZipArchive();
            if ($zip->open($zip_file) !== true) {
                unlink($zip_file);
                echo json_encode(['success' => false, 'message' => 'Failed to extract Stripe library']);
                exit;
            }
            
            // Extract to temp directory
            mkdir($temp_path, 0755, true);
            $zip->extractTo($temp_path);
            $zip->close();
            unlink($zip_file);
            
            // Find the extracted folder - validate it matches the expected naming pattern
            $extracted_dirs = glob($temp_path . '/stripe-stripe-php-*');
            $extracted_dir = null;
            foreach ($extracted_dirs as $dir) {
                // Validate directory name matches expected pattern (alphanumeric hash)
                $dir_name = basename($dir);
                if (preg_match('/^stripe-stripe-php-[a-f0-9]+$/i', $dir_name) && is_dir($dir)) {
                    $extracted_dir = $dir;
                    break;
                }
            }
            if (!$extracted_dir) {
                echo json_encode(['success' => false, 'message' => 'Could not find extracted files with valid naming pattern']);
                exit;
            }
            
            // Backup current stripe-php if exists
            if (is_dir($stripe_path)) {
                $backup_path = $stripe_path . '.backup-' . date('Y-m-d-His');
                rename($stripe_path, $backup_path);
            }
            
            // Move new files to stripe-php
            rename($extracted_dir, $stripe_path);
            
            // Cleanup temp directory
            if (is_dir($temp_path)) {
                rmdir($temp_path);
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'Stripe library updated to version ' . ($release['tag_name'] ?? 'latest')
            ]);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_stripe_library',
                'settings' => ['stripe_version' => $release['tag_name'] ?? 'latest']
            ]);
            
            exit;
            
        case 'update_landing':
            // Update Programs settings (4 programs)
            for ($i = 1; $i <= 4; $i++) {
                $prefix = "landing_program_{$i}_";
                $title = trim($_POST[$prefix . 'title'] ?? '');
                $image = trim($_POST[$prefix . 'image'] ?? '');
                $tags = trim($_POST[$prefix . 'tags'] ?? '');
                $description = trim($_POST[$prefix . 'description'] ?? '');
                
                // Validate image URL if provided - must be http or https
                if (!empty($image)) {
                    if (!filter_var($image, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid URL for Program $i image");
                    }
                    $parsed = parse_url($image);
                    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
                        throw new Exception("Program $i image URL must use http or https scheme");
                    }
                }
                
                updateSetting($pdo, $prefix . 'title', $title);
                updateSetting($pdo, $prefix . 'image', $image);
                updateSetting($pdo, $prefix . 'tags', $tags);
                updateSetting($pdo, $prefix . 'description', $description);
            }
            
            // Update Standards settings (4 standards)
            for ($i = 1; $i <= 4; $i++) {
                $prefix = "landing_standard_{$i}_";
                $label = trim($_POST[$prefix . 'label'] ?? '');
                $value = trim($_POST[$prefix . 'value'] ?? '');
                
                updateSetting($pdo, $prefix . 'label', $label);
                updateSetting($pdo, $prefix . 'value', $value);
            }
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_landing',
                'settings' => ['programs_and_standards_updated' => true]
            ]);
            
            header('Location: dashboard.php?page=admin_settings&success=1');
            exit;
            
        case 'update_docuseal':
            $docuseal_enabled = isset($_POST['docuseal_enabled']) ? '1' : '0';
            $docuseal_url = trim($_POST['docuseal_url'] ?? '');
            $docuseal_api_key = trim($_POST['docuseal_api_key'] ?? '');
            $docuseal_webhook_secret = trim($_POST['docuseal_webhook_secret'] ?? '');
            $docuseal_auto_confirm = isset($_POST['docuseal_auto_confirm']) ? '1' : '0';
            $docuseal_verify_ssl = isset($_POST['docuseal_verify_ssl']) ? '1' : '0';
            
            // Validate URL if provided
            if (!empty($docuseal_url) && !filter_var($docuseal_url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid DocuSeal URL format');
            }
            
            updateSetting($pdo, 'docuseal_enabled', $docuseal_enabled);
            updateSetting($pdo, 'docuseal_url', $docuseal_url);
            if (!empty($docuseal_api_key)) {
                updateSetting($pdo, 'docuseal_api_key', $docuseal_api_key);
            }
            updateSetting($pdo, 'docuseal_webhook_secret', $docuseal_webhook_secret);
            updateSetting($pdo, 'docuseal_auto_confirm', $docuseal_auto_confirm);
            updateSetting($pdo, 'docuseal_verify_ssl', $docuseal_verify_ssl);
            
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, [
                'action' => 'update_docuseal',
                'settings' => ['docuseal_enabled' => $docuseal_enabled, 'docuseal_url' => $docuseal_url, 'docuseal_api_key' => '***updated***']
            ]);
            
            // Redirect back to the appropriate page
            $redirect_page = isset($_POST['redirect_page']) ? $_POST['redirect_page'] : 'admin_settings';
            if ($redirect_page === 'system_tools') {
                header('Location: dashboard.php?page=system_tools&tab=docuseal&success=1');
            } else {
                header('Location: dashboard.php?page=admin_settings&success=1');
            }
            exit;
            
        case 'test_docuseal':
            require_once __DIR__ . '/lib/docuseal.php';
            
            $settings = [
                'docuseal_url' => trim($_POST['docuseal_url'] ?? ''),
                'docuseal_api_key' => trim($_POST['docuseal_api_key'] ?? ''),
                'docuseal_verify_ssl' => '1'
            ];
            
            $result = testDocuSealConnection($settings);
            echo json_encode($result);
            exit;
            
        case 'create_restriction':
            $title = trim($_POST['title'] ?? '');
            if (empty($title)) {
                echo json_encode(['success' => false, 'message' => 'Restriction title is required']);
                exit;
            }
            $restrictionId = Blocklist::createRestriction($pdo, $title, $user_id);
            if ($restrictionId) {
                Auditor::log($pdo, $user_id, 'create', 'registration_restrictions', $restrictionId, [
                    'title' => $title
                ]);
                echo json_encode(['success' => true, 'message' => 'Restriction created successfully', 'restriction_id' => $restrictionId]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to create restriction']);
            }
            exit;

        case 'remove_restriction':
            $restriction_id = intval($_POST['restriction_id'] ?? 0);
            if ($restriction_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid restriction ID']);
                exit;
            }
            $result = Blocklist::removeRestriction($pdo, $restriction_id);
            if ($result) {
                Auditor::log($pdo, $user_id, 'delete', 'registration_restrictions', $restriction_id);
                echo json_encode(['success' => true, 'message' => 'Restriction removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Restriction not found or already removed']);
            }
            exit;

        case 'add_blocklist_entry':
            $restriction_id = intval($_POST['restriction_id'] ?? 0);
            $block_type = trim($_POST['block_type'] ?? '');
            $block_value = trim($_POST['block_value'] ?? '');

            if ($restriction_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid restriction ID']);
                exit;
            }
            if (!in_array($block_type, ['email', 'name', 'ip'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid block type']);
                exit;
            }
            if (empty($block_value)) {
                echo json_encode(['success' => false, 'message' => 'Block value is required']);
                exit;
            }
            // Normalize before validation: lowercase email/name but not IP
            if ($block_type !== 'ip') {
                $block_value = strtolower($block_value);
            }
            if ($block_type === 'email' && !filter_var($block_value, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email address format']);
                exit;
            }
            if ($block_type === 'ip' && !filter_var($block_value, FILTER_VALIDATE_IP)) {
                echo json_encode(['success' => false, 'message' => 'Invalid IP address format']);
                exit;
            }

            $result = Blocklist::addEntry($pdo, $restriction_id, $block_type, $block_value);
            if ($result) {
                Auditor::log($pdo, $user_id, 'create', 'registration_blocklist', null, [
                    'restriction_id' => $restriction_id,
                    'block_type' => $block_type,
                    'block_value' => $block_value
                ]);
                echo json_encode(['success' => true, 'message' => 'Entry added successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to add entry. It may already exist.']);
            }
            exit;

        case 'remove_blocklist_entry':
            $entry_id = intval($_POST['entry_id'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
                exit;
            }

            $result = Blocklist::removeEntry($pdo, $entry_id);
            if ($result) {
                Auditor::log($pdo, $user_id, 'delete', 'registration_blocklist', $entry_id);
                echo json_encode(['success' => true, 'message' => 'Entry removed']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Entry not found or already removed']);
            }
            exit;

        case 'add_pos_whitelist_entry':
            $ip_address = trim($_POST['ip_address'] ?? '');
            $label = trim($_POST['label'] ?? '');

            if (empty($ip_address)) {
                echo json_encode(['success' => false, 'message' => 'IP address is required']);
                exit;
            }
            if (!filter_var($ip_address, FILTER_VALIDATE_IP)) {
                echo json_encode(['success' => false, 'message' => 'Invalid IP address format']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO pos_allowed_ips (ip_address, label, is_active, created_by)
                    VALUES (?, ?, 1, ?)
                ");
                $stmt->execute([$ip_address, $label ?: null, $user_id]);
                Auditor::log($pdo, $user_id, 'create', 'pos_allowed_ips', null, [
                    'ip_address' => $ip_address,
                    'label' => $label
                ]);
                echo json_encode(['success' => true, 'message' => 'POS IP whitelist entry added successfully']);
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                    echo json_encode(['success' => false, 'message' => 'This IP address is already in the whitelist']);
                } else {
                    error_log("Add POS whitelist error: " . $e->getMessage());
                    echo json_encode(['success' => false, 'message' => 'Failed to add entry']);
                }
            }
            exit;

        case 'remove_pos_whitelist_entry':
            $entry_id = intval($_POST['entry_id'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("DELETE FROM pos_allowed_ips WHERE id = ?");
                $stmt->execute([$entry_id]);
                if ($stmt->rowCount() > 0) {
                    Auditor::log($pdo, $user_id, 'delete', 'pos_allowed_ips', $entry_id);
                    echo json_encode(['success' => true, 'message' => 'POS IP whitelist entry removed']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Entry not found or already removed']);
                }
            } catch (PDOException $e) {
                error_log("Remove POS whitelist error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to remove entry']);
            }
            exit;

        case 'toggle_pos_whitelist_entry':
            $entry_id = intval($_POST['entry_id'] ?? 0);
            $is_active = intval($_POST['is_active'] ?? 0);
            if ($entry_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid entry ID']);
                exit;
            }

            try {
                $stmt = $pdo->prepare("UPDATE pos_allowed_ips SET is_active = ? WHERE id = ?");
                $stmt->execute([$is_active ? 1 : 0, $entry_id]);
                if ($stmt->rowCount() > 0) {
                    Auditor::log($pdo, $user_id, 'update', 'pos_allowed_ips', $entry_id, [
                        'is_active' => $is_active ? 1 : 0
                    ]);
                    echo json_encode(['success' => true, 'message' => 'POS IP whitelist entry ' . ($is_active ? 'enabled' : 'disabled')]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Entry not found or no change needed']);
                }
            } catch (PDOException $e) {
                error_log("Toggle POS whitelist error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Failed to update entry']);
            }
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
        $key = bin2hex(random_bytes(32));
        file_put_contents($key_file, $key);
        chmod($key_file, 0600);
    } else {
        $key = file_get_contents($key_file);
    }
    
    $key_hash = hash('sha256', $key, true);
    $iv = random_bytes(16);
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
