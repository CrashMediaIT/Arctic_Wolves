<?php
// db_config.php - Enhanced and Bulletproof Database Configuration
// Version: 2.0 - 100% Reliable

// 1. ENVIRONMENT LOADER FUNCTION
// Wrapped to prevent "Cannot redeclare" crashes
if (!function_exists('loadEnv')) {
    function loadEnv($path) {
        if (!file_exists($path)) { return false; }
        
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            // Skip comments
            if (strpos($line, '#') === 0 || empty($line)) continue;
            
            // Parse Key=Value
            $parts = explode('=', $line, 2);
            if (count($parts) === 2) {
                $name = trim($parts[0]);
                $value = trim($parts[1]);
                $value = trim($value, '"\''); // Remove quotes
                $_ENV[$name] = $value;
            }
        }
        return true;
    }
}

// 2. LOAD ENVIRONMENT FILE
// Multiple fallback paths for maximum compatibility
$possible_paths = [
    '/config/arctic_wolves.env',      // Production path
    __DIR__ . '/arctic_wolves.env',   // Local path
    __DIR__ . '/.env',               // Standard .env
    '/var/www/html/arctic_wolves/.env' // Docker path
];

$env_loaded = false;
foreach ($possible_paths as $path) {
    if (file_exists($path) && loadEnv($path)) {
        $env_loaded = true;
        break;
    }
}

// 3. DB CONNECTION PARAMETERS
// Configuration must be loaded from environment file created during setup
// Only use fallback defaults if no env file exists (pre-setup state)
$db_config_valid = true;

// Load field-level encryption library (PII at rest)
require_once __DIR__ . '/lib/encryption.php';

if ($env_loaded) {
    // Use configuration from the env file set up during setup.php
    $host = $_ENV['DB_HOST'] ?? '';
    $db   = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    
    // Validate that required configuration is present
    if (empty($host) || empty($db) || empty($user)) {
        $db_config_valid = false;
        $db_error = "Database configuration incomplete. Please run setup.php to configure the database.";
        error_log("[DB CONFIG ERROR] Environment file found but missing required DB_HOST, DB_NAME, or DB_USER");
    }
} else {
    // No environment file found - system not set up yet
    // Use minimal defaults that will likely fail, prompting user to run setup
    $host = 'localhost';
    $db   = 'arctic_wolves';
    $user = 'root';
    $pass = '';
    
    // Also set $_ENV so that other scripts can access credentials consistently
    $_ENV['DB_HOST'] = $host;
    $_ENV['DB_NAME'] = $db;
    $_ENV['DB_USER'] = $user;
    $_ENV['DB_PASS'] = $pass;
}

// 4. CREATE PDO CONNECTION WITH COMPREHENSIVE ERROR HANDLING
$pdo = null;
$db_connected = false;
if (!isset($db_error)) {
    $db_error = '';
}

if ($db_config_valid) {
    // Build candidate host list: cluster mode tries all nodes in order with automatic failover;
    // single mode uses the single configured host.
    $db_mode           = $_ENV['DB_MODE'] ?? 'single';
    $db_cluster_nodes  = $_ENV['DB_CLUSTER_NODES'] ?? '';
    
    if ($db_mode === 'cluster' && !empty($db_cluster_nodes)) {
        $candidate_hosts = array_map('trim', explode(',', $db_cluster_nodes));
        // Ensure the primary host is tried first only if not already present
        if (!in_array($host, $candidate_hosts)) {
            array_unshift($candidate_hosts, $host);
        }
    } else {
        $candidate_hosts = [$host];
    }
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        // Disable persistent connections in cluster mode to avoid stale connections to dead nodes
        PDO::ATTR_PERSISTENT => ($db_mode !== 'cluster'),
        PDO::ATTR_TIMEOUT => 5  // 5 second timeout
    ];
    
    $last_exception = null;
    foreach ($candidate_hosts as $candidate) {
        // Support host:port notation
        $node_host = $candidate;
        $node_port = 3306;
        if (strpos($candidate, ':') !== false) {
            [$node_host, $node_port] = explode(':', $candidate, 2);
            $node_port = (int)$node_port;
        }
        
        try {
            $dsn = "mysql:host=$node_host;port=$node_port;dbname=$db;charset=utf8mb4";
            $pdo = new PDO($dsn, $user, $pass, $options);
            $pdo->query("SELECT 1");
            $db_connected = true;
            // Track which node we are actually connected to
            $_ENV['DB_CONNECTED_HOST'] = $candidate;
            break;
        } catch (PDOException $e) {
            $last_exception = $e;
            $pdo = null;
            if ($db_mode === 'cluster') {
                error_log("[DB CLUSTER] Node $candidate unavailable: " . $e->getMessage());
            }
        }
    }
    
    if (!$db_connected && $last_exception !== null) {
        $db_connected = false;
        $pdo = null;
        $db_error = $last_exception->getMessage();
        error_log("[DB ERROR] " . $last_exception->getMessage());
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $db_error_display = $last_exception->getMessage();
        } else {
            $db_error_display = "Database connection failed. Please check your configuration.";
        }
    }
    
    // Enable database-backed error logging once connected
    if ($db_connected && class_exists('ErrorLogger')) {
        ErrorLogger::setDatabase($pdo);
    }
}

// 5. APPLY TIMEZONE FROM SYSTEM SETTINGS
// Load the configured timezone early so ALL PHP date/time functions use it,
// not just the Logger / ErrorLogger classes.
if ($db_connected && $pdo) {
    try {
        $tz_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'timezone' LIMIT 1");
        $tz_value = $tz_stmt->fetchColumn();
        if (!empty($tz_value) && in_array($tz_value, timezone_identifiers_list())) {
            date_default_timezone_set($tz_value);
        }
    } catch (Exception $e) {
        // Silently fail — table may not exist yet (pre-setup)
    }
}

// 6. DEFINE GLOBAL CONSTANT FOR EASY CHECKING
if (!defined('DB_CONNECTED')) {
    define('DB_CONNECTED', $db_connected);
}

// 6. HELPER FUNCTION FOR SAFE QUERIES (optional but recommended)
if (!function_exists('dbQuery')) {
    function dbQuery($sql, $params = []) {
        global $pdo, $db_connected;
        
        if (!$db_connected || !$pdo) {
            return false;
        }
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("[DB QUERY ERROR] " . $e->getMessage());
            return false;
        }
    }
}

// 7. PII DECRYPTION HELPERS
// These functions transparently decrypt encrypted PII fields in query results.
// They are safe to call on unencrypted data (returns value unchanged).
if (!function_exists('decryptUserRow')) {
    /**
     * Decrypt PII fields in a single user row from the database.
     * Safe to call on already-decrypted or plain-text data.
     *
     * @param array|null $row A single database row (associative array)
     * @return array|null The row with PII fields decrypted
     */
    function decryptUserRow($row) {
        if (!$row || !is_array($row)) return $row;
        $piiFields = ['first_name', 'last_name', 'phone', 'birth_date', 'date_of_birth',
                       'street_address', 'city', 'emergency_contact_name', 'emergency_contact_phone',
                       'customer_first_name', 'customer_last_name', 'customer_phone',
                       'billing_address_line1', 'billing_address_line2', 'billing_city',
                       'shipping_address_line1', 'shipping_address_line2', 'shipping_city',
                       'complainant_name', 'complainant_contact', 'respondent_name',
                       'contact_name', 'contact_phone',
                       'complainant_first', 'complainant_last',
                       'respondent_first', 'respondent_last',
                       'assigned_first', 'assigned_last',
                       'created_first', 'created_last',
                       'coach_first_name', 'coach_last_name',
                       'asst_first_name', 'asst_last_name',
                       'processor_first', 'processor_last',
                       'creator_first_name', 'creator_last_name',
                       'athlete_first_name', 'athlete_last_name',
                       'admin_first_name', 'admin_last_name',
                       'staff_first_name', 'staff_last_name',
                       'requested_by_first_name', 'requested_by_last_name',
                       'completed_by_first_name', 'completed_by_last_name',
                       'approved_by_first_name', 'approved_by_last_name',
                       'user_first_name', 'user_last_name'];
        foreach ($piiFields as $field) {
            if (isset($row[$field]) && $row[$field] !== '') {
                $row[$field] = FieldEncryption::decrypt($row[$field]);
            }
        }
        return $row;
    }

    /**
     * Decrypt PII fields in multiple user rows from the database.
     *
     * @param array $rows Array of database rows
     * @return array Rows with PII fields decrypted
     */
    function decryptUserRows($rows) {
        if (!$rows || !is_array($rows)) return $rows;
        foreach ($rows as &$row) {
            $row = decryptUserRow($row);
        }
        unset($row);
        return $rows;
    }
}

/**
 * Format a phone number for display as xxx.xxx.xxxx.
 * Strips non-digit characters (except leading +), then formats:
 *  - 10 digits: xxx.xxx.xxxx
 *  - 11 digits starting with 1: x.xxx.xxx.xxxx
 *  - Shorter numbers (extensions): returned as-is
 *
 * @param string|null $phone Raw phone number
 * @return string Formatted phone number or original value
 */
if (!function_exists('formatPhone')) {
    function formatPhone($phone) {
        if ($phone === null || $phone === '') return $phone;
        $digits = preg_replace('/[^0-9]/', '', $phone);
        if (strlen($digits) === 10) {
            return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 4);
        }
        if (strlen($digits) === 11 && $digits[0] === '1') {
            return $digits[0] . '.' . substr($digits, 1, 3) . '.' . substr($digits, 4, 3) . '.' . substr($digits, 7, 4);
        }
        return $phone;
    }
}

// Configuration loaded successfully
?>