<?php
/**
 * Process Game Plan Settings
 * Handles saving companion server, hardware acceleration, and video storage settings.
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

setSecurityHeaders();

// Admin-only
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'error' => 'Access denied']));
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// JSON actions return JSON, others redirect
$json_actions = ['test_companion'];
$is_json = in_array($action, $json_actions);

if ($is_json) {
    header('Content-Type: application/json');
}

try {
    checkCsrfToken();
} catch (Exception $e) {
    if ($is_json) {
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
    header('Location: dashboard.php?page=system_tools&tab=gameplan&error=' . urlencode('Invalid CSRF token'));
    exit;
}

/**
 * Upsert a system setting.
 */
function upsertGameplanSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES (:key, :value, NOW())
        ON DUPLICATE KEY UPDATE setting_value = :value2, updated_at = NOW()
    ");
    $stmt->execute([':key' => $key, ':value' => $value, ':value2' => $value]);
}

try {
    switch ($action) {
        case 'save_companion':
            $companion_url = trim($_POST['companion_url'] ?? '');
            $companion_api_key = trim($_POST['companion_api_key'] ?? '');
            $gameplan_app_url = trim($_POST['gameplan_app_url'] ?? '');

            // Validate URL format
            if ($companion_url !== '' && !filter_var($companion_url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid companion server URL format');
            }
            if ($gameplan_app_url !== '' && !filter_var($gameplan_app_url, FILTER_VALIDATE_URL)) {
                throw new Exception('Invalid Game Plan app URL format');
            }

            // Strip trailing slash
            $companion_url = rtrim($companion_url, '/');
            $gameplan_app_url = rtrim($gameplan_app_url, '/');

            upsertGameplanSetting($pdo, 'gameplan_companion_url', $companion_url);
            upsertGameplanSetting($pdo, 'gameplan_companion_api_key', $companion_api_key);
            upsertGameplanSetting($pdo, 'gameplan_app_url', $gameplan_app_url);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'Updated companion server settings']);

            header('Location: dashboard.php?page=system_tools&tab=gameplan&success=settings_saved');
            exit;

        case 'save_hw_accel':
            $hw_enabled = isset($_POST['hw_accel_enabled']) ? '1' : '0';
            $hw_method = $_POST['hw_accel_method'] ?? 'auto';

            $allowed_methods = ['auto', 'nvenc', 'qsv', 'vaapi', 'amf', 'none'];
            if (!in_array($hw_method, $allowed_methods, true)) {
                $hw_method = 'auto';
            }

            upsertGameplanSetting($pdo, 'gameplan_hw_accel_enabled', $hw_enabled);
            upsertGameplanSetting($pdo, 'gameplan_hw_accel_method', $hw_method);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'Updated hardware acceleration settings']);

            header('Location: dashboard.php?page=system_tools&tab=gameplan&success=settings_saved');
            exit;

        case 'save_video_storage':
            $storage_type = $_POST['video_storage_type'] ?? 'local';
            $storage_path = trim($_POST['video_storage_path'] ?? '/videos');

            $allowed_types = ['local', 'nfs', 'smb'];
            if (!in_array($storage_type, $allowed_types, true)) {
                $storage_type = 'local';
            }

            // Validate path is not empty and doesn't contain suspicious characters
            if (empty($storage_path) || preg_match('/[;&|`$]/', $storage_path)) {
                throw new Exception('Invalid video storage path');
            }

            upsertGameplanSetting($pdo, 'gameplan_video_storage_type', $storage_type);
            upsertGameplanSetting($pdo, 'gameplan_video_storage_path', $storage_path);

            // NFS settings
            if ($storage_type === 'nfs') {
                $nfs_server = trim($_POST['nfs_server'] ?? '');
                $nfs_export = trim($_POST['nfs_export'] ?? '');
                $nfs_options = trim($_POST['nfs_options'] ?? 'rw,sync,no_subtree_check');

                upsertGameplanSetting($pdo, 'gameplan_nfs_server', $nfs_server);
                upsertGameplanSetting($pdo, 'gameplan_nfs_export', $nfs_export);
                upsertGameplanSetting($pdo, 'gameplan_nfs_options', $nfs_options);
            }

            // SMB settings
            if ($storage_type === 'smb') {
                $smb_server = trim($_POST['smb_server'] ?? '');
                $smb_share = trim($_POST['smb_share'] ?? '');
                $smb_username = trim($_POST['smb_username'] ?? '');
                $smb_domain = trim($_POST['smb_domain'] ?? '');

                upsertGameplanSetting($pdo, 'gameplan_smb_server', $smb_server);
                upsertGameplanSetting($pdo, 'gameplan_smb_share', $smb_share);
                upsertGameplanSetting($pdo, 'gameplan_smb_username', $smb_username);
                upsertGameplanSetting($pdo, 'gameplan_smb_domain', $smb_domain);
            }

            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'Updated video storage settings', 'storage_type' => $storage_type]);
            header('Location: dashboard.php?page=system_tools&tab=gameplan&success=settings_saved');
            exit;

        case 'test_companion':
            $url = trim($_POST['companion_url'] ?? '');
            $api_key = trim($_POST['companion_api_key'] ?? '');

            if (empty($url)) {
                echo json_encode(['success' => false, 'error' => 'Companion URL is required']);
                exit;
            }

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
                exit;
            }

            // Call the companion server health endpoint
            $health_url = rtrim($url, '/') . '/api/health';

            $ch = curl_init($health_url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER => [
                    'X-API-Key: ' . $api_key,
                    'Accept: application/json',
                ],
            ]);
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $curl_error]);
                exit;
            }

            if ($http_code === 401) {
                echo json_encode(['success' => false, 'error' => 'Authentication failed - check API key']);
                exit;
            }

            if ($http_code !== 200) {
                echo json_encode(['success' => false, 'error' => 'Server returned HTTP ' . $http_code]);
                exit;
            }

            $data = json_decode($response, true);
            if (!$data || ($data['status'] ?? '') !== 'ok') {
                echo json_encode(['success' => false, 'error' => 'Unexpected server response']);
                exit;
            }

            echo json_encode([
                'success' => true,
                'version' => $data['version'] ?? 'unknown',
                'hw_accel' => $data['hw_accel'] ?? null,
                'video_base_accessible' => $data['video_base_accessible'] ?? false,
            ]);
            exit;

        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    ErrorLogger::error('Gameplan settings error: ' . $e->getMessage());
    if ($is_json) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
    header('Location: dashboard.php?page=system_tools&tab=gameplan&error=' . urlencode($e->getMessage()));
    exit;
}