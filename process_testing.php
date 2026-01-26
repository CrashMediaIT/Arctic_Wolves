<?php
/**
 * Process Testing Operations
 * Handles running system tests and recording test results
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

// Set security headers
setSecurityHeaders();

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];

/**
 * Record a test result in the database
 */
function recordTestResult($pdo, $test_name, $status, $message = '', $details = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO test_results (test_name, status, message, details, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $test_name,
            $status,
            $message,
            $details ? json_encode($details) : null
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Failed to record test: " . $e->getMessage());
        return false;
    }
}

/**
 * Run database connection test
 */
function testDatabaseConnection($pdo) {
    try {
        $result = $pdo->query("SELECT 1");
        if ($result) {
            return ['status' => 'passed', 'message' => 'Database connection successful'];
        }
        return ['status' => 'failed', 'message' => 'Database query returned no result'];
    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/**
 * Run email configuration test
 */
function testEmailConfig($pdo) {
    try {
        // Check if SMTP settings exist
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'smtp_%'");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        if (empty($settings['smtp_host'])) {
            return ['status' => 'failed', 'message' => 'SMTP host not configured'];
        }
        
        if (empty($settings['smtp_user'])) {
            return ['status' => 'failed', 'message' => 'SMTP user not configured'];
        }
        
        return ['status' => 'passed', 'message' => 'Email configuration present (host: ' . $settings['smtp_host'] . ')'];
    } catch (Exception $e) {
        return ['status' => 'failed', 'message' => 'Error checking email config: ' . $e->getMessage()];
    }
}

/**
 * Run file permissions test
 */
function testFilePermissions() {
    $dirs_to_check = [
        __DIR__ . '/uploads' => 'Uploads directory',
        __DIR__ . '/cache' => 'Cache directory',
        __DIR__ . '/logs' => 'Logs directory',
        __DIR__ . '/tmp' => 'Temp directory'
    ];
    
    $issues = [];
    foreach ($dirs_to_check as $dir => $label) {
        if (!is_dir($dir)) {
            $issues[] = "$label does not exist";
        } elseif (!is_writable($dir)) {
            $issues[] = "$label is not writable";
        }
    }
    
    if (empty($issues)) {
        return ['status' => 'passed', 'message' => 'All directories have correct permissions'];
    }
    
    return ['status' => 'failed', 'message' => implode('; ', $issues)];
}

/**
 * Run API endpoints test
 */
function testAPIEndpoints() {
    $endpoints = [
        'process_login.php',
        'process_settings.php',
        'process_goals.php'
    ];
    
    $missing = [];
    foreach ($endpoints as $endpoint) {
        if (!file_exists(__DIR__ . '/' . $endpoint)) {
            $missing[] = $endpoint;
        }
    }
    
    if (empty($missing)) {
        return ['status' => 'passed', 'message' => 'All critical API endpoints exist'];
    }
    
    return ['status' => 'failed', 'message' => 'Missing endpoints: ' . implode(', ', $missing)];
}

/**
 * Run security headers test
 */
function testSecurityHeaders() {
    // Check if security headers function exists
    if (!function_exists('setSecurityHeaders')) {
        return ['status' => 'failed', 'message' => 'Security headers function not found'];
    }
    
    // Check if CSRF functions exist
    if (!function_exists('generateCSRFToken') && !function_exists('generateCsrfToken')) {
        return ['status' => 'failed', 'message' => 'CSRF protection functions not found'];
    }
    
    return ['status' => 'passed', 'message' => 'Security functions available'];
}

/**
 * Run session handling test
 */
function testSessionHandling() {
    // Check if session is properly started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return ['status' => 'failed', 'message' => 'Session not active'];
    }
    
    // Check session configuration
    $secure_cookie = ini_get('session.cookie_secure');
    $http_only = ini_get('session.cookie_httponly');
    $same_site = ini_get('session.cookie_samesite');
    
    $issues = [];
    if ($_SERVER['HTTPS'] ?? false && !$secure_cookie) {
        $issues[] = 'Secure cookie not enabled for HTTPS';
    }
    if (!$http_only) {
        $issues[] = 'HttpOnly cookie not enabled';
    }
    
    if (empty($issues)) {
        return ['status' => 'passed', 'message' => 'Session handling configured correctly'];
    }
    
    return ['status' => 'failed', 'message' => implode('; ', $issues)];
}

try {
    switch ($action) {
        case 'run_tests':
            $tests_to_run = $_POST['tests'] ?? [];
            
            if (empty($tests_to_run)) {
                throw new Exception('No tests selected');
            }
            
            $results = [];
            
            foreach ($tests_to_run as $test) {
                switch ($test) {
                    case 'database_connection':
                        $result = testDatabaseConnection($pdo);
                        break;
                    case 'email_config':
                        $result = testEmailConfig($pdo);
                        break;
                    case 'file_permissions':
                        $result = testFilePermissions();
                        break;
                    case 'api_endpoints':
                        $result = testAPIEndpoints();
                        break;
                    case 'security_headers':
                        $result = testSecurityHeaders();
                        break;
                    case 'session_handling':
                        $result = testSessionHandling();
                        break;
                    default:
                        $result = ['status' => 'failed', 'message' => 'Unknown test type'];
                }
                
                // Record the result
                recordTestResult($pdo, ucwords(str_replace('_', ' ', $test)), $result['status'], $result['message']);
                $results[$test] = $result;
            }
            
            header('Location: dashboard.php?page=testing&tests_run=' . count($results));
            exit;
            
        case 'record_test':
            $test_name = trim($_POST['test_name'] ?? '');
            $status = $_POST['status'] ?? 'failed';
            $message = trim($_POST['message'] ?? '');
            
            if (empty($test_name)) {
                throw new Exception('Test name is required');
            }
            
            // Validate status
            if (!in_array($status, ['passed', 'failed'])) {
                $status = 'failed';
            }
            
            recordTestResult($pdo, $test_name, $status, $message);
            
            header('Location: dashboard.php?page=testing&test_recorded=1');
            exit;
            
        case 'clear_results':
            // Clear old test results (keep last 100)
            $pdo->exec("
                DELETE FROM test_results 
                WHERE id NOT IN (
                    SELECT id FROM (
                        SELECT id FROM test_results ORDER BY created_at DESC LIMIT 100
                    ) as t
                )
            ");
            
            header('Location: dashboard.php?page=testing&cleared=1');
            exit;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Testing error: " . $e->getMessage());
    header('Location: dashboard.php?page=testing&error=' . urlencode($e->getMessage()));
    exit;
}
