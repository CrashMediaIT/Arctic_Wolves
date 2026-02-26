<?php
/**
 * Application Logger Library
 * Centralized logging for errors, warnings, and information
 */

class Logger {
    
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_INFO = 'INFO';
    const LEVEL_DEBUG = 'DEBUG';
    
    private static $log_dir = __DIR__ . '/../logs/';
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
            global $pdo;
            if (isset($pdo) && $pdo instanceof PDO) {
                $stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'timezone' LIMIT 1");
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
     * Log an error message
     */
    public static function error($message, $context = []) {
        return self::log(self::LEVEL_ERROR, $message, $context);
    }
    
    /**
     * Log a warning message
     */
    public static function warning($message, $context = []) {
        return self::log(self::LEVEL_WARNING, $message, $context);
    }
    
    /**
     * Log an info message
     */
    public static function info($message, $context = []) {
        return self::log(self::LEVEL_INFO, $message, $context);
    }
    
    /**
     * Log a debug message
     */
    public static function debug($message, $context = []) {
        return self::log(self::LEVEL_DEBUG, $message, $context);
    }
    
    /**
     * Core logging function
     */
    private static function log($level, $message, $context = []) {
        // Ensure timezone is set from system settings
        self::ensureTimezone();
        
        // Create logs directory if it doesn't exist
        if (!is_dir(self::$log_dir)) {
            mkdir(self::$log_dir, 0755, true);
        }
        
        // Format the log entry
        $timestamp = date('Y-m-d H:i:s');
        $context_str = !empty($context) ? ' | Context: ' . json_encode($context) : '';
        $log_entry = "[{$timestamp}] [{$level}] {$message}{$context_str}\n";
        
        // Determine log file based on date
        $log_file = self::$log_dir . 'app-' . date('Y-m-d') . '.log';
        
        // Write to log file
        $result = file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        // Also log to PHP error log for ERROR level
        if ($level === self::LEVEL_ERROR) {
            error_log("[Arctic Wolves] {$message}" . ($context ? ' | ' . json_encode($context) : ''));
        }
        
        return $result !== false;
    }
    
    /**
     * Log an exception
     */
    public static function logException($exception, $context = []) {
        $message = sprintf(
            'Exception: %s in %s:%d',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine()
        );
        
        $context['trace'] = $exception->getTraceAsString();
        
        return self::error($message, $context);
    }
    
    /**
     * Log a database error
     */
    public static function logDatabaseError($error, $query = null, $params = []) {
        $context = [
            'error' => $error,
            'query' => $query,
            'params' => $params
        ];
        
        return self::error('Database Error', $context);
    }
    
    /**
     * Log a security event
     */
    public static function logSecurityEvent($event, $details = []) {
        self::ensureTimezone();
        
        $context = [
            'event' => $event,
            'details' => $details,
            'ip' => self::resolveClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        // Write to separate security log
        $log_file = self::$log_dir . 'security-' . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[{$timestamp}] [SECURITY] {$event} | " . json_encode($context) . "\n";
        file_put_contents($log_file, $log_entry, FILE_APPEND);
        
        return self::warning("Security Event: {$event}", $context);
    }
    
    /**
     * Clean up old log files
     */
    public static function cleanupOldLogs($days = 30) {
        $cutoff = time() - ($days * 24 * 60 * 60);
        $deleted = 0;
        
        if (!is_dir(self::$log_dir)) {
            return $deleted;
        }
        
        $files = glob(self::$log_dir . '*.log');
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff) {
                if (unlink($file)) {
                    $deleted++;
                }
            }
        }
        
        return $deleted;
    }
    
    /**
     * Get log entries for a specific date
     */
    public static function getLogsForDate($date, $level = null) {
        $log_file = self::$log_dir . 'app-' . $date . '.log';
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $logs = file($log_file, FILE_IGNORE_NEW_LINES);
        
        if ($level !== null) {
            $logs = array_filter($logs, function($line) use ($level) {
                return strpos($line, "[{$level}]") !== false;
            });
        }
        
        return $logs;
    }
}
?>
