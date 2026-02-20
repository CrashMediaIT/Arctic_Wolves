<?php
/**
 * Audit Logger Library
 * Centralized audit logging for security and compliance tracking
 */

class Auditor {
    
    /**
     * Log an audit event
     * 
     * @param PDO $pdo Database connection
     * @param int $user_id User ID performing the action
     * @param string $action Action performed (create, update, delete, view, etc.)
     * @param string $table_name Table affected
     * @param int|null $record_id ID of affected record
     * @param array|null $changes Changes made (old vs new values)
     * @param string|null $ip_address IP address of user
     * @return bool Success status
     */
    public static function log($pdo, $user_id, $action, $table_name, $record_id = null, $changes = null, $ip_address = null) {
        try {
            // Get IP address if not provided
            if ($ip_address === null) {
                $ip_address = self::getClientIP();
            }
            
            // Encode changes as JSON if provided
            $changes_json = null;
            $new_values_json = null;
            if ($changes !== null) {
                $changes_json = json_encode($changes);
                if ($changes_json === false) {
                    error_log("Auditor: Failed to encode changes for $table_name:$record_id");
                    $changes_json = json_encode(['error' => 'Failed to encode changes']);
                }
                // Extract meaningful field values (skip 'action' key) into new_values
                $field_values = [];
                foreach ($changes as $key => $val) {
                    if ($key === 'action') continue;
                    if (is_array($val)) {
                        foreach ($val as $k => $v) {
                            $field_values[$k] = $v;
                        }
                    } else {
                        $field_values[$key] = $val;
                    }
                }
                if (!empty($field_values)) {
                    $new_values_json = json_encode($field_values);
                }
            }
            
            // Map action to action_type for consistency
            // Default to uppercase action name if no specific mapping
            $action_type = strtoupper($action);
            
            // Handle common action patterns
            if (in_array($action, ['create', 'insert'])) {
                $action_type = 'INSERT';
            } elseif (in_array($action, ['update', 'edit', 'modify'])) {
                $action_type = 'UPDATE';
            } elseif (in_array($action, ['delete', 'remove'])) {
                $action_type = 'DELETE';
            } elseif (in_array($action, ['view', 'read', 'access'])) {
                $action_type = 'VIEW';
            } elseif (in_array($action, ['login_success', 'login_failed', 'logout'])) {
                $action_type = 'AUTH';
            } elseif (in_array($action, ['cleanup', 'maintenance', 'cron'])) {
                $action_type = 'SYSTEM';
            }
            
            // Insert audit log with both action and action_type for compatibility
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    user_id, action_type, action, table_name, record_id, changes, new_values, ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $action_type,
                $action,
                $table_name,
                $record_id,
                $changes_json,
                $new_values_json,
                $ip_address
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Auditor Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log a user login event
     */
    public static function logLogin($pdo, $user_id, $success = true) {
        return self::log(
            $pdo,
            $user_id,
            $success ? 'login_success' : 'login_failed',
            'users',
            $user_id
        );
    }
    
    /**
     * Log a user logout event
     */
    public static function logLogout($pdo, $user_id) {
        return self::log($pdo, $user_id, 'logout', 'users', $user_id);
    }
    
    /**
     * Log a data access event
     */
    public static function logAccess($pdo, $user_id, $table_name, $record_id) {
        return self::log($pdo, $user_id, 'view', $table_name, $record_id);
    }
    
    /**
     * Log a data modification event
     */
    public static function logModification($pdo, $user_id, $action, $table_name, $record_id, $changes) {
        return self::log($pdo, $user_id, $action, $table_name, $record_id, $changes);
    }
    
    /**
     * Log a change with automatic old-value capture
     * Fetches the current record before update/delete and stores both old and new values
     *
     * @param PDO $pdo Database connection
     * @param int $user_id User ID performing the action
     * @param string $action Action performed (update, delete)
     * @param string $table_name Table affected
     * @param int $record_id ID of affected record
     * @param array $new_values New values being set (field => value pairs)
     * @param string|null $ip_address IP address of user
     * @return bool Success status
     */
    public static function logChange($pdo, $user_id, $action, $table_name, $record_id, $new_values = [], $ip_address = null) {
        try {
            if ($ip_address === null) {
                $ip_address = self::getClientIP();
            }
            
            // Fetch old values from the record before modification
            $old_values_json = null;
            if ($record_id && in_array($action, ['update', 'edit', 'modify', 'delete', 'remove'])) {
                try {
                    $old_stmt = $pdo->prepare("SELECT * FROM `$table_name` WHERE id = ? LIMIT 1");
                    $old_stmt->execute([$record_id]);
                    $old_record = $old_stmt->fetch(PDO::FETCH_ASSOC);
                    if ($old_record) {
                        // Only keep fields that are being changed (for updates)
                        if (!empty($new_values) && in_array($action, ['update', 'edit', 'modify'])) {
                            $relevant_old = [];
                            foreach ($new_values as $key => $val) {
                                if ($key === 'action') continue;
                                if (array_key_exists($key, $old_record)) {
                                    $relevant_old[$key] = $old_record[$key];
                                }
                            }
                            $old_values_json = !empty($relevant_old) ? json_encode($relevant_old) : json_encode($old_record);
                        } else {
                            $old_values_json = json_encode($old_record);
                        }
                    }
                } catch (PDOException $e) {
                    // Silently continue if we can't fetch old values
                    error_log("Auditor::logChange - Could not fetch old values: " . $e->getMessage());
                }
            }
            
            // Build changes and new_values JSON
            $changes_json = null;
            $new_values_json = null;
            if (!empty($new_values)) {
                $changes_json = json_encode($new_values);
                // Extract field values (skip 'action' key)
                $field_values = [];
                foreach ($new_values as $key => $val) {
                    if ($key === 'action') continue;
                    if (is_array($val)) {
                        foreach ($val as $k => $v) {
                            $field_values[$k] = $v;
                        }
                    } else {
                        $field_values[$key] = $val;
                    }
                }
                if (!empty($field_values)) {
                    $new_values_json = json_encode($field_values);
                }
            }
            
            // Map action to action_type
            $action_type = strtoupper($action);
            if (in_array($action, ['update', 'edit', 'modify'])) {
                $action_type = 'UPDATE';
            } elseif (in_array($action, ['delete', 'remove'])) {
                $action_type = 'DELETE';
            } elseif (in_array($action, ['create', 'insert'])) {
                $action_type = 'INSERT';
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO audit_logs (
                    user_id, action_type, action, table_name, record_id, changes, old_values, new_values, ip_address, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $user_id,
                $action_type,
                $action,
                $table_name,
                $record_id,
                $changes_json,
                $old_values_json,
                $new_values_json,
                $ip_address
            ]);
            
            return true;
            
        } catch (PDOException $e) {
            error_log("Auditor::logChange Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get client IP address
     */
    private static function getClientIP() {
        // Check for proxy headers
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
                // Handle multiple IPs in X-Forwarded-For
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP address
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Get audit logs for a specific record
     */
    public static function getLogsForRecord($pdo, $table_name, $record_id, $limit = 50) {
        try {
            $stmt = $pdo->prepare("
                SELECT al.*, u.username, u.first_name, u.last_name
                FROM audit_logs al
                LEFT JOIN users u ON al.user_id = u.id
                WHERE al.table_name = ? AND al.record_id = ?
                ORDER BY al.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$table_name, $record_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Auditor::getLogsForRecord Error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get audit logs for a specific user
     */
    public static function getLogsForUser($pdo, $user_id, $limit = 100) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM audit_logs
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$user_id, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Auditor::getLogsForUser Error: " . $e->getMessage());
            return [];
        }
    }
}
?>
