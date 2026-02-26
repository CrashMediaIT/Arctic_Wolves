<?php
/**
 * Automated Database Backup Cron Job
 * Run via cron to execute scheduled backups
 * Example: 0 2 * * * /usr/bin/php /path/to/cron_database_backup.php
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY') ?: 'change_this_in_production';
    
    if ($secret_key !== $expected_key) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Database Backup Cron: Starting...\n";

try {
    // Get all active backup jobs that are due
    $stmt = $pdo->prepare("
        SELECT * FROM backup_jobs 
        WHERE status = 'active' 
        AND (next_backup IS NULL OR next_backup <= NOW())
        ORDER BY next_backup ASC
    ");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($jobs)) {
        echo "No backup jobs due at this time.\n";
        exit(0);
    }
    
    echo "Found " . count($jobs) . " backup job(s) to process.\n";
    
    foreach ($jobs as $job) {
        echo "\nProcessing: " . $job['name'] . "\n";
        
        try {
            $result = performBackup($pdo, $job);
            
            if ($result['success']) {
                echo "✓ " . $result['message'] . "\n";
            } else {
                echo "✗ " . $result['message'] . "\n";
            }
            
            // Update next_backup time
            $next_backup = calculateNextRun($job['schedule']);
            $stmt = $pdo->prepare("UPDATE backup_jobs SET next_backup = ? WHERE id = ?");
            $stmt->execute([$next_backup, $job['id']]);
            
        } catch (Exception $e) {
            echo "✗ Error: " . $e->getMessage() . "\n";
            
            // Log error in backup history
            $stmt = $pdo->prepare("
                INSERT INTO backup_history (backup_job_id, filename, status, error_message)
                VALUES (?, ?, 'failed', ?)
            ");
            $stmt->execute([$job['id'], 'backup_' . date('Ymd_His') . '_failed.sql.gz', $e->getMessage()]);
        }
    }
    
    echo "\n[" . date('Y-m-d H:i:s') . "] Database Backup Cron: Completed\n";
    
} catch (Exception $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Perform database backup
 */
function performBackup($pdo, $job) {
    try {
        // Generate filename
        $filename = 'arctic_wolves_backup_' . date('Ymd_His') . '.sql.gz';
        $temp_dir = __DIR__ . '/tmp/';
        
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        $sql_file = $temp_dir . 'backup_' . time() . '.sql';
        $gz_file = $sql_file . '.gz';
        
        // Get database credentials from environment variables (set by db_config.php)
        $db_host = $_ENV['DB_HOST'] ?? null;
        $db_name = $_ENV['DB_NAME'] ?? null;
        $db_user = $_ENV['DB_USER'] ?? null;
        $db_pass = $_ENV['DB_PASS'] ?? '';
        
        // Validate required credentials are set
        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            throw new Exception('Database credentials not configured. Please check your environment configuration.');
        }
        
        // Create mysqldump command
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s 2>&1',
            escapeshellarg($db_host),
            escapeshellarg($db_user),
            escapeshellarg($db_pass),
            escapeshellarg($db_name),
            escapeshellarg($sql_file)
        );
        
        // Execute dump
        exec($command, $output, $return_var);
        
        if ($return_var !== 0 || !file_exists($sql_file)) {
            throw new Exception('Database dump failed: ' . implode("\n", $output));
        }
        
        // Compress SQL file
        exec('gzip -9 ' . escapeshellarg($sql_file), $output, $return_var);
        
        if ($return_var !== 0 || !file_exists($gz_file)) {
            throw new Exception('Compression failed');
        }
        
        $file_size = filesize($gz_file);
        $success_destinations = [];
        $errors = [];
        
        // Upload to primary RustFS if configured
        $rustfs_url = null;
        if ($job['destination_type'] === 's3' || $job['destination_type'] === 'both') {
            try {
                $rustfs = getRustFSSettings($pdo);
                if (isRustFSConfigured($rustfs)) {
                    $nc_file_content = file_get_contents($gz_file);
                    if ($nc_file_content === false) {
                        throw new Exception('Failed to read backup file for RustFS upload');
                    }
                    $object_key = 'Backups/' . $filename;
                    $result = uploadContentToRustFS($rustfs, $nc_file_content, $object_key, 'application/gzip');
                    
                    if ($result['success']) {
                        $rustfs_url = $result['url'];
                        $success_destinations[] = 'RustFS: ' . $result['url'];
                        echo "  ✓ Uploaded to RustFS\n";
                    } else {
                        $errors[] = 'RustFS upload failed: ' . ($result['message'] ?? '');
                        echo "  ✗ RustFS upload failed\n";
                    }
                } else {
                    $errors[] = 'RustFS not configured';
                    echo "  ⚠ RustFS not configured – skipping\n";
                }
            } catch (Exception $e) {
                $errors[] = 'RustFS: ' . $e->getMessage();
                echo "  ✗ RustFS error: " . $e->getMessage() . "\n";
            }
        }
        
        // Upload to SMB if configured
        if ($job['destination_type'] === 'smb' || $job['destination_type'] === 'both') {
            try {
                $password = decryptPassword($job['smb_password']);
                $result = uploadToSMB($gz_file, $filename, $job['smb_path'], $job['smb_username'], $password, $job['smb_domain']);
                
                if ($result['success']) {
                    $success_destinations[] = 'SMB: ' . $job['smb_path'] . '/' . $filename;
                    echo "  ✓ Uploaded to SMB\n";
                } else {
                    $errors[] = 'SMB: ' . $result['message'];
                    echo "  ✗ SMB upload failed: " . $result['message'] . "\n";
                }
            } catch (Exception $e) {
                $errors[] = 'SMB: ' . $e->getMessage();
                echo "  ✗ SMB error: " . $e->getMessage() . "\n";
            }
        }
        
        // Clean up temp file
        @unlink($gz_file);
        
        // Record backup history
        $backup_status = empty($errors) ? 'success' : 'failed';
        $destinations = implode(', ', $success_destinations);
        $error_msg = empty($errors) ? null : implode('; ', $errors);
        
        // Determine file_path: prefer RustFS URL
        $backup_file_path = $rustfs_url;
        
        $stmt = $pdo->prepare("
            INSERT INTO backup_history (backup_job_id, filename, file_path, file_size, destination, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$job['id'], $filename, $backup_file_path, $file_size, $destinations, $backup_status, $error_msg]);
        
        // Update last_backup time
        $stmt = $pdo->prepare("UPDATE backup_jobs SET last_backup = NOW() WHERE id = ?");
        $stmt->execute([$job['id']]);
        
        // Prune old backups: keep only the most recent keep_count successful copies
        cleanOldBackups($pdo, $job);
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Backup completed successfully. Size: ' . round($file_size / 1024 / 1024, 2) . ' MB'
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Backup completed with errors: ' . implode('; ', $errors)
            ];
        }
        
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

/**
 * Upload file to SMB share
 */
function uploadToSMB($local_file, $filename, $smb_path, $username, $password, $domain = '') {
    $domain_part = !empty($domain) ? '-W ' . escapeshellarg($domain) . ' ' : '';
    
    $command = sprintf(
        'smbclient %s -U %s%%%s %s -c "put %s %s" 2>&1',
        escapeshellarg($smb_path),
        escapeshellarg($username),
        escapeshellarg($password),
        $domain_part,
        escapeshellarg($local_file),
        escapeshellarg($filename)
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        return ['success' => true, 'message' => 'Upload successful'];
    } else {
        return ['success' => false, 'message' => 'Upload failed: ' . implode("\n", $output)];
    }
}

/**
 * Prune backup history: keep only the most recent keep_count successful
 * backups for the given job.  Older records are deleted.
 * Falls back to the legacy retention_days approach when keep_count is 0.
 */
function cleanOldBackups($pdo, $job) {
    $keep_count = isset($job['keep_count']) ? (int)$job['keep_count'] : 3;

    if ($keep_count > 0 && $job['id'] > 0) {
        // Delete successful backups beyond the keep_count most recent ones
        $stmt = $pdo->prepare("
            DELETE FROM backup_history
            WHERE backup_job_id = ?
              AND status = 'success'
              AND id NOT IN (
                  SELECT id FROM (
                      SELECT id FROM backup_history
                      WHERE backup_job_id = ? AND status = 'success'
                      ORDER BY backup_date DESC
                      LIMIT ?
                  ) AS latest_backups
              )
        ");
        $stmt->execute([$job['id'], $job['id'], $keep_count]);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) {
            echo "  ✓ Pruned $deleted old backup record(s) (keeping $keep_count)\n";
        }
        return;
    }

    // Legacy path: retention_days fallback
    $retention_days = isset($job['retention_days']) ? (int)$job['retention_days'] : 30;
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days'));

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM backup_history 
        WHERE backup_job_id = ? AND backup_date < ? AND status = 'success'
    ");
    $stmt->execute([$job['id'], $cutoff_date]);
    $old_count = $stmt->fetchColumn();
    
    if ($old_count > 0) {
        $stmt = $pdo->prepare("
            DELETE FROM backup_history 
            WHERE backup_job_id = ? AND backup_date < ? AND status = 'success'
        ");
        $stmt->execute([$job['id'], $cutoff_date]);
        echo "  ✓ Cleaned $old_count old backup record(s)\n";
    }
}

// decryptPassword() is now defined in security.php

/**
 * Calculate next run time from a cron expression.
 * Supports all standard schedule intervals used by Arctic Wolves backups:
 *   */5 * * * *   – every 5 minutes
 *   0 * * * *    – every hour
 *   0 */6 * * *  – every 6 hours
 *   0 */12 * * * – every 12 hours
 *   0 0 * * *    – every 24 hours (daily)
 *   0 0 * * 0    – every week (Sunday midnight)
 *   0 0 1 * *    – every month (1st day)
 */
function calculateNextRun($cron_expression) {
    $parts = explode(' ', trim($cron_expression));
    if (count($parts) !== 5) {
        return null;
    }
    
    list($minute, $hour, $day, $month, $weekday) = $parts;
    
    // Every N minutes: */5 * * * *
    if (preg_match('/^\*\/(\d+)$/', $minute, $m) && $hour === '*') {
        $interval = (int)$m[1];
        $next = ceil(time() / ($interval * 60)) * ($interval * 60);
        return date('Y-m-d H:i:s', $next);
    }
    
    // Every N hours on the hour: 0 */N * * *
    if ($minute === '0' && preg_match('/^\*\/(\d+)$/', $hour, $m) && $day === '*') {
        $interval = (int)$m[1];
        $current_hour = (int)date('G');
        $next_hour = (int)(ceil(($current_hour + 1) / $interval) * $interval);
        if ($next_hour >= 24) {
            $next_hour -= 24;
            $next = strtotime('tomorrow ' . sprintf('%02d:00:00', $next_hour));
        } else {
            $next = strtotime('today ' . sprintf('%02d:00:00', $next_hour));
            if ($next <= time()) {
                $next += $interval * 3600;
            }
        }
        return date('Y-m-d H:i:s', $next);
    }
    
    // Every hour: 0 * * * *
    if ($cron_expression === '0 * * * *') {
        $next = strtotime(date('Y-m-d H:00:00', strtotime('+1 hour')));
        return date('Y-m-d H:i:s', $next);
    }
    
    // Daily at midnight: 0 0 * * *
    if ($cron_expression === '0 0 * * *') {
        $next = strtotime('tomorrow midnight');
        return date('Y-m-d H:i:s', $next);
    }
    
    // Weekly (Sunday midnight): 0 0 * * 0
    if ($cron_expression === '0 0 * * 0') {
        $next = strtotime('next sunday midnight');
        return date('Y-m-d H:i:s', $next);
    }
    
    // Monthly (1st at midnight): 0 0 1 * *
    if ($cron_expression === '0 0 1 * *') {
        $next = strtotime('first day of next month midnight');
        return date('Y-m-d H:i:s', $next);
    }
    
    // Generic: specific hour/minute, every day
    if (preg_match('/^\d+ \d+ \* \* \*$/', $cron_expression)) {
        $time = sprintf('%02d:%02d:00', $hour, $minute);
        $next = strtotime('today ' . $time);
        if ($next <= time()) {
            $next = strtotime('tomorrow ' . $time);
        }
        return date('Y-m-d H:i:s', $next);
    }
    
    // Fallback
    return date('Y-m-d H:i:s', strtotime('+1 day'));
}
?>
