<?php
/**
 * Rate Limiter Library
 * Prevents abuse by limiting request rates per IP/user
 */

class RateLimiter {
    
    private $pdo;
    private static $cache = [];
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Check if request should be allowed
     * 
     * @param string $identifier Unique identifier (IP, user ID, etc.)
     * @param string $action Action being performed
     * @param int $max_requests Maximum requests allowed
     * @param int $window_seconds Time window in seconds
     * @return bool True if allowed, false if rate limit exceeded
     */
    public function isAllowed($identifier, $action, $max_requests = 60, $window_seconds = 60) {
        try {
            $cache_key = "{$identifier}:{$action}";
            
            // Check cache first
            if (isset(self::$cache[$cache_key])) {
                $data = self::$cache[$cache_key];
                if (time() - $data['timestamp'] < $window_seconds) {
                    if ($data['count'] >= $max_requests) {
                        return false;
                    }
                    self::$cache[$cache_key]['count']++;
                    return true;
                }
            }
            
            // Check database for existing rate limit record
            $window_start = date('Y-m-d H:i:s', time() - $window_seconds);
            
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM security_logs
                WHERE request_uri = ?
                AND event_type = ?
                AND created_at > ?
            ");
            $stmt->execute([$identifier, $action, $window_start]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $current_count = $result ? intval($result['count']) : 0;
            
            if ($current_count >= $max_requests) {
                // Log rate limit violation
                $this->logViolation($identifier, $action, $current_count, $max_requests);
                return false;
            }
            
            // Record this request
            $this->recordRequest($identifier, $action);
            
            // Update cache
            self::$cache[$cache_key] = [
                'count' => $current_count + 1,
                'timestamp' => time()
            ];
            
            return true;
            
        } catch (PDOException $e) {
            error_log("RateLimiter Error: " . $e->getMessage());
            // On error, allow the request (fail open)
            return true;
        }
    }
    
    /**
     * Check if IP is allowed
     */
    public function isIPAllowed($action = 'general', $max_requests = 100, $window_seconds = 60) {
        $ip = $this->getClientIP();
        return $this->isAllowed($ip, $action, $max_requests, $window_seconds);
    }
    
    /**
     * Check if user is allowed
     */
    public function isUserAllowed($user_id, $action = 'general', $max_requests = 60, $window_seconds = 60) {
        return $this->isAllowed("user:{$user_id}", $action, $max_requests, $window_seconds);
    }
    
    /**
     * Record a request
     */
    private function recordRequest($identifier, $action) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_logs (
                    request_uri, event_type, ip_address, user_agent, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $identifier,
                $action,
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
            ]);
        } catch (PDOException $e) {
            error_log("RateLimiter::recordRequest Error: " . $e->getMessage());
        }
    }
    
    /**
     * Log a rate limit violation
     */
    private function logViolation($identifier, $action, $current_count, $max_requests) {
        error_log("Rate limit exceeded: {$identifier} - {$action} ({$current_count}/{$max_requests})");
        
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO security_logs (
                    request_uri, event_type, ip_address, user_agent, description, created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $details = json_encode([
                'violation' => 'rate_limit_exceeded',
                'current_count' => $current_count,
                'max_requests' => $max_requests
            ]);
            
            $stmt->execute([
                $identifier,
                "rate_limit_violation:{$action}",
                $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                $details
            ]);
        } catch (PDOException $e) {
            error_log("RateLimiter::logViolation Error: " . $e->getMessage());
        }
    }
    
    /**
     * Get client IP address
     */
    private function getClientIP() {
        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Reset rate limit for identifier
     */
    public function reset($identifier, $action) {
        try {
            $stmt = $this->pdo->prepare("
                DELETE FROM security_logs
                WHERE identifier = ? AND action = ?
            ");
            $stmt->execute([$identifier, $action]);
            
            // Clear cache
            $cache_key = "{$identifier}:{$action}";
            unset(self::$cache[$cache_key]);
            
            return true;
        } catch (PDOException $e) {
            error_log("RateLimiter::reset Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Clean up old rate limit records
     */
    public function cleanup($older_than_hours = 24) {
        try {
            $cutoff = date('Y-m-d H:i:s', time() - ($older_than_hours * 3600));
            
            $stmt = $this->pdo->prepare("
                DELETE FROM security_logs
                WHERE created_at < ?
                AND action NOT LIKE 'rate_limit_violation:%'
            ");
            $stmt->execute([$cutoff]);
            
            return $stmt->rowCount();
        } catch (PDOException $e) {
            error_log("RateLimiter::cleanup Error: " . $e->getMessage());
            return 0;
        }
    }
}
?>
