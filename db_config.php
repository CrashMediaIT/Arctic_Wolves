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

if ($env_loaded) {
    // Use configuration from the env file set up during setup.php
    $host = $_ENV['DB_HOST'] ?? '';
    $db   = $_ENV['DB_NAME'] ?? '';
    $user = $_ENV['DB_USER'] ?? '';
    $pass = $_ENV['DB_PASS'] ?? '';
    
    // Validate that required configuration is present
    if (empty($host) || empty($db) || empty($user)) {
        $db_config_valid = false;
        $db_connected = false;
        $pdo = null;
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
}

// 4. CREATE PDO CONNECTION WITH COMPREHENSIVE ERROR HANDLING
$pdo = null;
$db_connected = false;
if (!isset($db_error)) {
    $db_error = '';
}

if ($db_config_valid) {
    try {
        // Create PDO instance with all recommended settings
        $dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_PERSISTENT => true,  // Connection pooling
            PDO::ATTR_TIMEOUT => 5  // 5 second timeout
        ];
        
        $pdo = new PDO($dsn, $user, $pass, $options);
        
        // Test the connection with a simple query
        $pdo->query("SELECT 1");
        
        // Connection successful
        $db_connected = true;
        
    } catch (PDOException $e) {
        // Connection failed - set safe defaults
        $db_connected = false;
        $pdo = null;
        $db_error = $e->getMessage();
        
        // Log error securely (don't expose to user)
        error_log("[DB ERROR] " . $e->getMessage());
        
        // Set user-friendly error message
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            // In debug mode, show detailed error
            $db_error_display = $e->getMessage();
        } else {
            // In production, show generic error
            $db_error_display = "Database connection failed. Please check your configuration.";
        }
    }
}

// 5. DEFINE GLOBAL CONSTANT FOR EASY CHECKING
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

// Configuration loaded successfully
?>