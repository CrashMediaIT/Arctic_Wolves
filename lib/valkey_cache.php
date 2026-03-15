<?php
/**
 * Valkey Cache Library - Redis-compatible in-memory caching
 * 
 * Provides optional Valkey/Redis caching for Arctic Wolves.
 * When enabled, caches frequently-accessed data to reduce database load.
 * When disabled or unavailable, the application falls back to direct DB queries.
 *
 * Cached data categories:
 *  - System Settings: All key-value pairs from system_settings table
 *  - User Sessions & Roles: Role lookups cached per user
 *  - Rate Limiting: Distributed rate limit counters (replaces in-memory cache)
 *  - Dashboard Stats: Aggregated statistics with short TTL
 */

class ValkeyCache {
    /** @var \Redis|null */
    private static $connection = null;

    /** @var bool Whether caching is enabled */
    private static $enabled = false;

    /** @var bool Whether we already attempted to connect this request */
    private static $initialized = false;

    /** @var string Cache key prefix to avoid collisions */
    private static $prefix = 'aw:';

    /** Default TTL values (seconds) */
    const TTL_SETTINGS      = 300;   // 5 minutes
    const TTL_USER_ROLE     = 600;   // 10 minutes
    const TTL_RATE_LIMIT    = 3600;  // 1 hour
    const TTL_DASHBOARD     = 120;   // 2 minutes

    /**
     * Initialize the Valkey connection using settings from system_settings or env.
     * Safe to call multiple times — only connects once per request.
     *
     * @param PDO|null $pdo  Database connection for loading settings
     * @return bool  Whether caching is available
     */
    public static function init($pdo = null) {
        if (self::$initialized) {
            return self::$enabled;
        }
        self::$initialized = true;

        // Check if the Redis extension is available
        if (!class_exists('Redis')) {
            self::$enabled = false;
            return false;
        }

        // Load Valkey configuration from environment or database
        $config = self::loadConfig($pdo);

        if (empty($config['enabled']) || $config['enabled'] === '0') {
            self::$enabled = false;
            return false;
        }

        $host     = $config['host'] ?? '127.0.0.1';
        $port     = (int)($config['port'] ?? 6379);
        $password = $config['password'] ?? '';
        $database = (int)($config['database'] ?? 0);
        $prefix   = $config['prefix'] ?? 'aw:';

        self::$prefix = $prefix;

        try {
            $redis = new \Redis();
            // Connect with a 2-second timeout to avoid blocking if Valkey is down
            $connected = $redis->connect($host, $port, 2.0);
            if (!$connected) {
                self::$enabled = false;
                return false;
            }

            if (!empty($password)) {
                $redis->auth($password);
            }

            if ($database > 0) {
                $redis->select($database);
            }

            // Verify connection with a PING
            $pong = $redis->ping();
            if ($pong !== true && $pong !== '+PONG') {
                self::$enabled = false;
                return false;
            }

            self::$connection = $redis;
            self::$enabled = true;
            return true;
        } catch (\Exception $e) {
            error_log('[VALKEY] Connection failed: ' . $e->getMessage());
            self::$connection = null;
            self::$enabled = false;
            return false;
        }
    }

    /**
     * Load Valkey configuration from system_settings table or environment.
     *
     * @param PDO|null $pdo
     * @return array
     */
    private static function loadConfig($pdo = null) {
        $config = [
            'enabled'  => $_ENV['VALKEY_ENABLED'] ?? '0',
            'host'     => $_ENV['VALKEY_HOST'] ?? '127.0.0.1',
            'port'     => $_ENV['VALKEY_PORT'] ?? '6379',
            'password' => $_ENV['VALKEY_PASSWORD'] ?? '',
            'database' => $_ENV['VALKEY_DATABASE'] ?? '0',
            'prefix'   => $_ENV['VALKEY_PREFIX'] ?? 'aw:',
        ];

        // Try to load from database (overrides env values)
        if ($pdo) {
            try {
                $stmt = $pdo->prepare(
                    "SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'valkey_%'"
                );
                $stmt->execute();
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $short_key = str_replace('valkey_', '', $row['setting_key']);
                    $config[$short_key] = $row['setting_value'];
                }
            } catch (\PDOException $e) {
                // Table may not exist yet — use env/defaults
            }
        }

        return $config;
    }

    /**
     * Check whether Valkey caching is enabled and connected.
     *
     * @return bool
     */
    public static function isEnabled() {
        return self::$enabled && self::$connection !== null;
    }

    /**
     * Get the underlying Redis connection (for advanced usage).
     *
     * @return \Redis|null
     */
    public static function getConnection() {
        return self::$connection;
    }

    // ---------------------------------------------------------------
    //  Core Cache Operations
    // ---------------------------------------------------------------

    /**
     * Get a cached value.
     *
     * @param string $key
     * @return mixed|null  Returns null on miss or if caching is disabled
     */
    public static function get($key) {
        if (!self::$enabled || !self::$connection) return null;
        try {
            $value = self::$connection->get(self::$prefix . $key);
            if ($value === false) return null;
            $decoded = json_decode($value, true);
            return ($decoded !== null) ? $decoded : $value;
        } catch (\Exception $e) {
            error_log('[VALKEY] GET error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set a cached value with optional TTL.
     *
     * @param string $key
     * @param mixed  $value  Will be JSON-encoded if not a string
     * @param int    $ttl    Time-to-live in seconds (0 = no expiry)
     * @return bool
     */
    public static function set($key, $value, $ttl = 0) {
        if (!self::$enabled || !self::$connection) return false;
        try {
            $encoded = is_string($value) ? $value : json_encode($value);
            if ($ttl > 0) {
                return self::$connection->setex(self::$prefix . $key, $ttl, $encoded);
            }
            return self::$connection->set(self::$prefix . $key, $encoded);
        } catch (\Exception $e) {
            error_log('[VALKEY] SET error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Delete one or more cached keys.
     *
     * @param string|array $keys
     * @return int  Number of keys deleted
     */
    public static function delete($keys) {
        if (!self::$enabled || !self::$connection) return 0;
        try {
            if (is_array($keys)) {
                $prefixed = array_map(function ($k) { return self::$prefix . $k; }, $keys);
                return self::$connection->del($prefixed);
            }
            return self::$connection->del(self::$prefix . $keys);
        } catch (\Exception $e) {
            error_log('[VALKEY] DEL error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Flush all keys with our prefix (safe — does not flush other apps' keys).
     * Uses SCAN instead of KEYS to avoid blocking the server.
     *
     * @return int  Number of keys deleted
     */
    public static function flushPrefix() {
        if (!self::$enabled || !self::$connection) return 0;
        try {
            $deleted = 0;
            $iterator = null;
            $pattern = self::$prefix . '*';
            while (true) {
                $keys = self::$connection->scan($iterator, $pattern, 100);
                if ($keys === false) break;
                if (!empty($keys)) {
                    $deleted += self::$connection->del($keys);
                }
                if ($iterator === 0) break;
            }
            return $deleted;
        } catch (\Exception $e) {
            error_log('[VALKEY] FLUSH error: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Increment a counter (useful for rate limiting).
     *
     * @param string $key
     * @param int    $ttl   Set TTL only if key is new
     * @return int|false  The new counter value
     */
    public static function increment($key, $ttl = 0) {
        if (!self::$enabled || !self::$connection) return false;
        try {
            $fullKey = self::$prefix . $key;
            $val = self::$connection->incr($fullKey);
            // Set TTL only when the counter was just created (value = 1)
            if ($ttl > 0 && $val === 1) {
                self::$connection->expire($fullKey, $ttl);
            }
            return $val;
        } catch (\Exception $e) {
            error_log('[VALKEY] INCR error: ' . $e->getMessage());
            return false;
        }
    }

    // ---------------------------------------------------------------
    //  Domain-Specific Helpers
    // ---------------------------------------------------------------

    /**
     * Get all system settings from cache, or load from DB and cache them.
     *
     * @param PDO $pdo
     * @return array  Associative array of setting_key => setting_value
     */
    public static function getSystemSettings($pdo) {
        if (self::$enabled && self::$connection) {
            $cached = self::get('system_settings');
            if ($cached !== null && is_array($cached)) {
                return $cached;
            }
        }

        // Load from database
        try {
            $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
            $settings = [];
            while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (\PDOException $e) {
            return [];
        }

        // Cache the result
        self::set('system_settings', $settings, self::TTL_SETTINGS);

        return $settings;
    }

    /**
     * Invalidate the system settings cache (call after any setting update).
     *
     * @return void
     */
    public static function invalidateSettings() {
        self::delete('system_settings');
    }

    /**
     * Cache a user's role for quick lookup.
     *
     * @param int    $userId
     * @param string $role
     */
    public static function setUserRole($userId, $role) {
        self::set('user_role:' . $userId, $role, self::TTL_USER_ROLE);
    }

    /**
     * Get a cached user role.
     *
     * @param int $userId
     * @return string|null
     */
    public static function getUserRole($userId) {
        return self::get('user_role:' . $userId);
    }

    /**
     * Rate-limit check using Valkey counter.
     * Returns true if the request is within the limit, false if exceeded.
     *
     * @param string $identifier  e.g. IP address or user ID
     * @param string $action      e.g. 'login', 'api'
     * @param int    $maxAttempts
     * @param int    $windowSeconds
     * @return bool  true = allowed, false = rate-limited
     */
    public static function rateLimit($identifier, $action, $maxAttempts, $windowSeconds) {
        if (!self::$enabled || !self::$connection) return true; // Allow if cache unavailable
        $key = 'rl:' . $action . ':' . $identifier;
        $count = self::increment($key, $windowSeconds);
        if ($count === false) return true; // Allow on error
        return $count <= $maxAttempts;
    }

    /**
     * Cache dashboard statistics.
     *
     * @param string $dashboardKey  Unique identifier for the dashboard view
     * @param array  $stats
     */
    public static function setDashboardStats($dashboardKey, $stats) {
        self::set('dash:' . $dashboardKey, $stats, self::TTL_DASHBOARD);
    }

    /**
     * Get cached dashboard statistics.
     *
     * @param string $dashboardKey
     * @return array|null
     */
    public static function getDashboardStats($dashboardKey) {
        return self::get('dash:' . $dashboardKey);
    }

    /**
     * Test connection with provided parameters (used by the settings UI).
     *
     * @param string $host
     * @param int    $port
     * @param string $password
     * @param int    $database
     * @return array  ['success' => bool, 'message' => string, 'info' => array]
     */
    public static function testConnection($host, $port, $password = '', $database = 0) {
        if (!class_exists('Redis')) {
            return [
                'success' => false,
                'message' => 'PHP Redis extension is not installed. Install php-redis to use Valkey caching.',
                'info'    => []
            ];
        }

        try {
            $redis = new \Redis();
            $connected = $redis->connect($host, $port, 3.0);
            if (!$connected) {
                return [
                    'success' => false,
                    'message' => "Could not connect to Valkey at $host:$port",
                    'info'    => []
                ];
            }

            if (!empty($password)) {
                $redis->auth($password);
            }

            if ($database > 0) {
                $redis->select($database);
            }

            $pong = $redis->ping();
            if ($pong !== true && $pong !== '+PONG') {
                return [
                    'success' => false,
                    'message' => 'Valkey PING failed — server may not be responding correctly.',
                    'info'    => []
                ];
            }

            // Gather server info
            $info = $redis->info('server');
            $memInfo = $redis->info('memory');
            $keyspaceInfo = $redis->info('keyspace');

            $serverInfo = [
                'version'     => $info['redis_version'] ?? ($info['valkey_version'] ?? 'Unknown'),
                'uptime'      => isset($info['uptime_in_seconds']) ? self::formatUptime((int)$info['uptime_in_seconds']) : 'Unknown',
                'memory_used' => $memInfo['used_memory_human'] ?? 'Unknown',
                'memory_peak' => $memInfo['used_memory_peak_human'] ?? 'Unknown',
                'connected_clients' => $info['connected_clients'] ?? 'Unknown',
                'total_keys'  => self::countKeys($keyspaceInfo),
            ];

            $redis->close();

            return [
                'success' => true,
                'message' => "Connected to Valkey at $host:$port",
                'info'    => $serverInfo
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Connection failed: ' . $e->getMessage(),
                'info'    => []
            ];
        }
    }

    /**
     * Format uptime seconds to human-readable string.
     */
    private static function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $mins = floor(($seconds % 3600) / 60);
        if ($days > 0) return "{$days}d {$hours}h {$mins}m";
        if ($hours > 0) return "{$hours}h {$mins}m";
        return "{$mins}m";
    }

    /**
     * Count total keys from keyspace info.
     */
    private static function countKeys($keyspaceInfo) {
        $total = 0;
        foreach ($keyspaceInfo as $key => $value) {
            if (strpos($key, 'db') === 0 && is_string($value)) {
                if (preg_match('/keys=(\d+)/', $value, $m)) {
                    $total += (int)$m[1];
                }
            }
        }
        return $total;
    }

    /**
     * Get cache statistics for the admin dashboard.
     * Uses SCAN instead of KEYS to avoid blocking the server.
     *
     * @return array|null
     */
    public static function getStats() {
        if (!self::$enabled || !self::$connection) return null;
        try {
            $info = self::$connection->info();
            // Count app keys using SCAN (non-blocking)
            $appKeyCount = 0;
            $iterator = null;
            $pattern = self::$prefix . '*';
            while (true) {
                $keys = self::$connection->scan($iterator, $pattern, 100);
                if ($keys === false) break;
                $appKeyCount += count($keys);
                if ($iterator === 0) break;
            }
            return [
                'connected'        => true,
                'version'          => $info['redis_version'] ?? ($info['valkey_version'] ?? 'Unknown'),
                'memory_used'      => $info['used_memory_human'] ?? 'Unknown',
                'uptime'           => isset($info['uptime_in_seconds']) ? self::formatUptime((int)$info['uptime_in_seconds']) : 'Unknown',
                'app_keys'         => $appKeyCount,
                'hit_rate'         => isset($info['keyspace_hits'], $info['keyspace_misses'])
                    ? round($info['keyspace_hits'] / max(1, $info['keyspace_hits'] + $info['keyspace_misses']) * 100, 1) . '%'
                    : 'N/A',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
}
