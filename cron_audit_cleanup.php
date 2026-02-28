<?php
/**
 * Automated Audit Log Cleanup Cron Job
 * Removes old audit log entries to prevent database bloat
 * Retains logs based on retention policy in system_settings
 * Example: 0 3 * * 0 /usr/bin/php /path/to/cron_audit_cleanup.php
 */

require_once __DIR__ . '/db_config.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY');
    
    if (empty($expected_key) || !hash_equals($expected_key, $secret_key)) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Audit Cleanup Cron: Starting...\n";

try {
    // Get retention policy from system settings (default: 90 days)
    $stmt = $pdo->prepare("
        SELECT setting_value FROM system_settings 
        WHERE setting_key = 'audit_retention_days' 
        LIMIT 1
    ");
    $stmt->execute();
    $setting = $stmt->fetch(PDO::FETCH_ASSOC);
    $retention_days = $setting ? intval($setting['setting_value']) : 90;
    
    echo "Retention policy: $retention_days days\n";
    
    // Calculate cutoff date
    $cutoff_date = date('Y-m-d H:i:s', strtotime("-$retention_days days"));
    echo "Cutoff date: $cutoff_date\n";
    
    // Count records to be deleted
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM audit_logs 
        WHERE created_at < ?
    ");
    $stmt->execute([$cutoff_date]);
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($count === 0) {
        echo "No audit logs to clean up.\n";
        exit(0);
    }
    
    echo "Found $count audit log(s) to clean up.\n";
    
    // Delete old audit logs
    $stmt = $pdo->prepare("
        DELETE FROM audit_logs 
        WHERE created_at < ?
    ");
    $stmt->execute([$cutoff_date]);
    $deleted = $stmt->rowCount();
    
    echo "✓ Successfully deleted $deleted audit log record(s).\n";
    
    // Log the cleanup action with action_type for consistency
    $stmt = $pdo->prepare("
        INSERT INTO audit_logs (user_id, action_type, action, table_name, record_id, ip_address, created_at)
        VALUES (0, 'SYSTEM', 'cleanup', 'audit_logs', NULL, 'CRON', NOW())
    ");
    $stmt->execute();
    
    echo "[" . date('Y-m-d H:i:s') . "] Audit Cleanup Cron: Completed successfully.\n";
    exit(0);
    
} catch (PDOException $e) {
    error_log("Audit Cleanup Cron Error: " . $e->getMessage());
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
