<?php
/**
 * Process Game Plan Settings
 * Handles saving companion server connection settings.
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
$json_actions = ['test_companion', 'push_rustfs_to_companion'];
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
            ]);
            exit;

        case 'push_rustfs_to_companion':
            // Push the main app's RustFS settings to the companion server
            // so it can access S3 for video downloads/uploads.
            $companion_url = '';
            $companion_key = '';
            $rustfs = [];
            $app_url = '';

            // Load companion settings
            $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'gameplan_%' OR setting_key LIKE 'rustfs_%'");
            $stmt->execute();
            while ($row = $stmt->fetch()) {
                $k = $row['setting_key'];
                $v = $row['setting_value'] ?? '';
                if ($k === 'gameplan_companion_url') $companion_url = $v;
                elseif ($k === 'gameplan_companion_api_key') $companion_key = $v;
                elseif ($k === 'gameplan_app_url') $app_url = $v;
                elseif (str_starts_with($k, 'rustfs_')) $rustfs[$k] = $v;
            }

            if (empty($companion_url)) {
                echo json_encode(['success' => false, 'error' => 'Companion server URL is not configured']);
                exit;
            }

            // Decrypt secret key if encrypted
            $secret_key = $rustfs['rustfs_secret_key'] ?? '';
            if (!empty($secret_key) && function_exists('decryptPassword')) {
                $dec = decryptPassword($secret_key);
                if (!empty($dec)) $secret_key = $dec;
            }

            // Build the config payload for the companion
            $config_payload = [
                's3_endpoint'   => $rustfs['rustfs_endpoint'] ?? '',
                's3_access_key' => $rustfs['rustfs_access_key'] ?? '',
                's3_secret_key' => $secret_key,
                's3_bucket'     => $rustfs['rustfs_bucket'] ?? '',
                's3_region'     => $rustfs['rustfs_region'] ?? 'us-east-1',
                's3_use_ssl'    => ($rustfs['rustfs_use_ssl'] ?? '1') === '1' ? 'true' : 'false',
                's3_verify_ssl' => 'false',
                'main_app_url'  => $app_url,
            ];

            $ch = curl_init(rtrim($companion_url, '/') . '/api/config');
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => json_encode($config_payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'X-API-Key: ' . $companion_key,
                ],
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response  = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                echo json_encode(['success' => false, 'error' => 'Connection failed: ' . $curl_error]);
                exit;
            }

            if ($http_code === 401) {
                echo json_encode(['success' => false, 'error' => 'Authentication failed — check the API key']);
                exit;
            }

            if ($http_code !== 200) {
                echo json_encode(['success' => false, 'error' => 'Companion returned HTTP ' . $http_code]);
                exit;
            }

            $result = json_decode($response, true);
            Auditor::log($pdo, $user_id, 'update', 'system_settings', null, ['action' => 'Pushed RustFS settings to companion']);
            echo json_encode(['success' => true, 'updated' => $result['updated'] ?? []]);
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