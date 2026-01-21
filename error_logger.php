<?php
/**
 * Error Logger
 * Centralized error logging system
 */

class ErrorLogger {
    
    private static $logPath = __DIR__ . '/logs/';
    private static $initialized = false;
    
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
     * Log message to file
     */
    public static function log($message, $level = 'INFO', $file = 'application.log') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        
        $logFile = self::$logPath . $file;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Log error
     */
    public static function error($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        self::log($message . $contextStr, 'ERROR', 'error.log');
    }
    
    /**
     * Log warning
     */
    public static function warning($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        self::log($message . $contextStr, 'WARNING', 'warning.log');
    }
    
    /**
     * Log info
     */
    public static function info($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        self::log($message . $contextStr, 'INFO', 'application.log');
    }
    
    /**
     * Log security event
     */
    public static function security($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        self::log($message . $contextStr, 'SECURITY', 'security.log');
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
        self::error($message);
        
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
        self::error($message);
        
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
            self::error($message);
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
