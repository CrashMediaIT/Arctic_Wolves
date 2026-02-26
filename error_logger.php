<?php
/**
 * Error Logger
 * Centralized error logging system
 */

class ErrorLogger {
    
    private static $logPath = __DIR__ . '/logs/';
    private static $initialized = false;
    private static $pdo = null;
    private static $dbLogging = false;
    private static $timezone_set = false;
    
    /**
     * Ensure the timezone is set from system settings
     */
    private static function ensureTimezone() {
        if (self::$timezone_set) {
            return;
        }
        self::$timezone_set = true;
        
        try {
            if (self::$pdo !== null) {
                $stmt = self::$pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'timezone' LIMIT 1");
                $tz = $stmt->fetchColumn();
                if (!empty($tz) && in_array($tz, timezone_identifiers_list())) {
                    date_default_timezone_set($tz);
                }
            }
        } catch (Exception $e) {
            // Silently fail - use default timezone
        }
    }
    
    /**
     * Resolve the real client IP behind a reverse proxy.
     * Checks standard proxy headers before falling back to REMOTE_ADDR.
     */
    private static function resolveClientIP() {
        $proxyHeaders = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
        ];
        foreach ($proxyHeaders as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    /**
     * Initialize error handling
     */
    public static function init() {
        if (self::$initialized) {
            return;
        }
        
        // Ensure logs directory exists
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
        
        // Configure PHP error handling
        error_reporting(E_ALL);
        ini_set('display_errors', 0);
        ini_set('log_errors', 1);
        ini_set('error_log', self::$logPath . 'php-error.log');
        
        // Set custom error and exception handlers
        set_error_handler([self::class, 'errorHandler']);
        set_exception_handler([self::class, 'exceptionHandler']);
        register_shutdown_function([self::class, 'shutdownHandler']);
        
        self::$initialized = true;
    }
    
    /**
     * Set database connection for database-backed logging
     * Call this after establishing PDO connection to enable error_logs table writes
     */
    public static function setDatabase($pdo) {
        if ($pdo instanceof PDO) {
            self::$pdo = $pdo;
            self::$dbLogging = true;
            self::ensureTimezone();
        }
    }
    
    /**
     * Log message to file
     */
    public static function log($message, $level = 'INFO', $file = 'application.log') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        $logFile = self::$logPath . $file;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
        
        // Also log to database if connection is available
        self::logToDatabase($message, $level);
    }
    
    /**
     * Write log entry to database error_logs table
     */
    private static function logToDatabase($message, $level, $sourceFile = null, $sourceLine = null, $stackTrace = null, $context = null) {
        if (!self::$dbLogging || self::$pdo === null) {
            return;
        }
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO error_logs (error_level, message, file, line, stack_trace, user_id, url, ip_address, user_agent, context, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
            $url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null;
            $ip = self::resolveClientIP();
            $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : null;
            
            $stmt->execute([
                $level,
                $message,
                $sourceFile,
                $sourceLine,
                $stackTrace,
                $userId,
                $url,
                $ip,
                $userAgent,
                $context
            ]);
        } catch (PDOException $e) {
            // Fallback to file-only logging to avoid infinite recursion
            $fallbackMsg = "[" . date('Y-m-d H:i:s') . "] [ERROR] Failed to write to error_logs table: " . $e->getMessage() . "\n";
            file_put_contents(self::$logPath . 'error.log', $fallbackMsg, FILE_APPEND);
        }
    }
    
    /**
     * Log error
     */
    public static function error($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $contextJson = !empty($context) ? json_encode($context) : null;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : (isset($trace[0]) ? $trace[0] : []);
        $file = isset($caller['file']) ? $caller['file'] : null;
        $line = isset($caller['line']) ? $caller['line'] : null;
        
        // Write to file
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [ERROR] {$message}{$contextStr}\n";
        file_put_contents(self::$logPath . 'error.log', $logMessage, FILE_APPEND);
        
        // Write to database
        self::logToDatabase($message, 'ERROR', $file, $line, null, $contextJson);
    }
    
    /**
     * Log warning
     */
    public static function warning($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $contextJson = !empty($context) ? json_encode($context) : null;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : (isset($trace[0]) ? $trace[0] : []);
        $file = isset($caller['file']) ? $caller['file'] : null;
        $line = isset($caller['line']) ? $caller['line'] : null;
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [WARNING] {$message}{$contextStr}\n";
        file_put_contents(self::$logPath . 'warning.log', $logMessage, FILE_APPEND);
        
        self::logToDatabase($message, 'WARNING', $file, $line, null, $contextJson);
    }
    
    /**
     * Log info
     */
    public static function info($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $contextJson = !empty($context) ? json_encode($context) : null;
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [INFO] {$message}{$contextStr}\n";
        file_put_contents(self::$logPath . 'application.log', $logMessage, FILE_APPEND);
        
        self::logToDatabase($message, 'INFO', null, null, null, $contextJson);
    }
    
    /**
     * Log security event
     */
    public static function security($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $contextJson = !empty($context) ? json_encode($context) : null;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = isset($trace[1]) ? $trace[1] : (isset($trace[0]) ? $trace[0] : []);
        $file = isset($caller['file']) ? $caller['file'] : null;
        $line = isset($caller['line']) ? $caller['line'] : null;
        
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [SECURITY] {$message}{$contextStr}\n";
        file_put_contents(self::$logPath . 'security.log', $logMessage, FILE_APPEND);
        
        self::logToDatabase($message, 'SECURITY', $file, $line, null, $contextJson);
    }
    
    /**
     * Log database query
     */
    public static function query($query, $params = []) {
        $paramsStr = !empty($params) ? ' | Params: ' . json_encode($params) : '';
        self::log($query . $paramsStr, 'QUERY', 'database.log');
    }
    
    /**
     * Custom error handler
     */
    public static function errorHandler($errno, $errstr, $errfile, $errline) {
        $message = "Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
        
        // Write to file
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [ERROR] {$message}\n";
        file_put_contents(self::$logPath . 'error.log', $logMessage, FILE_APPEND);
        
        // Write to database with file/line info
        self::logToDatabase($errstr, 'ERROR', $errfile, $errline);
        
        // Don't execute PHP internal error handler
        return true;
    }
    
    /**
     * Custom exception handler
     */
    public static function exceptionHandler($exception) {
        $message = "Uncaught Exception: " . $exception->getMessage() . 
                   " in " . $exception->getFile() . 
                   " on line " . $exception->getLine() . 
                   "\nStack trace:\n" . $exception->getTraceAsString();
        
        // Write to file
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [ERROR] {$message}\n";
        file_put_contents(self::$logPath . 'error.log', $logMessage, FILE_APPEND);
        
        // Write to database with full exception details
        self::logToDatabase(
            $exception->getMessage(),
            'ERROR',
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
        
        // Display user-friendly error in production
        if (!self::isDebugMode()) {
            http_response_code(500);
            echo "An error occurred. Please try again later.";
        }
    }
    
    /**
     * Shutdown handler to catch fatal errors
     */
    public static function shutdownHandler() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            $message = "Fatal Error [{$error['type']}]: {$error['message']} in {$error['file']} on line {$error['line']}";
            
            // Write to file
            $timestamp = date('Y-m-d H:i:s');
            $logMessage = "[{$timestamp}] [ERROR] {$message}\n";
            file_put_contents(self::$logPath . 'error.log', $logMessage, FILE_APPEND);
            
            // Write to database
            self::logToDatabase($error['message'], 'ERROR', $error['file'], $error['line']);
        }
    }
    
    /**
     * Check if debug mode is enabled
     */
    private static function isDebugMode() {
        return defined('DEBUG_MODE') && DEBUG_MODE === true;
    }
    
    /**
     * Rotate log files
     */
    public static function rotateLogs($maxSize = 10485760) { // 10MB default
        $logFiles = glob(self::$logPath . '*.log');
        
        foreach ($logFiles as $logFile) {
            if (filesize($logFile) > $maxSize) {
                $backupFile = $logFile . '.' . date('Y-m-d-His');
                rename($logFile, $backupFile);
                
                // Keep only last 10 rotated files
                $pattern = $logFile . '.*';
                $rotated = glob($pattern);
                if (count($rotated) > 10) {
                    usort($rotated, function($a, $b) {
                        return filemtime($a) - filemtime($b);
                    });
                    unlink($rotated[0]);
                }
            }
        }
    }
}

// Initialize error logger
ErrorLogger::init();

