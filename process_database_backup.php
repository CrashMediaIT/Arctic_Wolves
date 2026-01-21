<?php
/**
 * Process Database Backup Jobs
 * CRUD operations and manual backup triggers
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? '';

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $schedule = trim($_POST['schedule'] ?? '');
            $backup_type = $_POST['backup_type'] ?? 'scheduled';
            $destination_type = $_POST['destination_type'] ?? 'nextcloud';
            $nextcloud_folder = trim($_POST['nextcloud_folder'] ?? '/CrashHockey/Backups/');
            $smb_path = trim($_POST['smb_path'] ?? '');
            $smb_username = trim($_POST['smb_username'] ?? '');
            $smb_password = trim($_POST['smb_password'] ?? '');
            $smb_domain = trim($_POST['smb_domain'] ?? '');
            $retention_days = (int)($_POST['retention_days'] ?? 30);
            $status = $_POST['status'] ?? 'active';
            
            if (empty($name)) throw new Exception('Backup job name is required');
            if (empty($schedule)) throw new Exception('Schedule is required');
            
            // Validate cron expression
            if (!validateCronExpression($schedule)) {
                throw new Exception('Invalid cron expression format');
            }
            
            // Validate destination settings
            if ($destination_type === 'smb' || $destination_type === 'both') {
                if (empty($smb_path) || empty($smb_username) || empty($smb_password)) {
                    throw new Exception('SMB credentials are required for SMB backup');
                }
            }
            
            // Encrypt SMB password
            $encrypted_password = '';
            if (!empty($smb_password)) {
                $encrypted_password = encryptPassword($smb_password);
            }
            
            // Calculate next backup time
            $next_backup = calculateNextRun($schedule);
            
            // Insert backup job
            $stmt = $pdo->prepare("
                INSERT INTO backup_jobs 
                (name, schedule, backup_type, destination_type, nextcloud_folder, smb_path, 
                 smb_username, smb_password, smb_domain, retention_days, next_backup, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $schedule, $backup_type, $destination_type, $nextcloud_folder,
                $smb_path, $smb_username, $encrypted_password, $smb_domain,
                $retention_days, $next_backup, $status, $user_id
            ]);
            
            logAction($pdo, $user_id, 'backup_job_created', 'Created backup job: ' . $name);
            
            echo json_encode(['success' => true, 'message' => 'Backup job created successfully']);
            break;
            
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $schedule = trim($_POST['schedule'] ?? '');
            $destination_type = $_POST['destination_type'] ?? 'nextcloud';
            $nextcloud_folder = trim($_POST['nextcloud_folder'] ?? '/CrashHockey/Backups/');
            $smb_path = trim($_POST['smb_path'] ?? '');
            $smb_username = trim($_POST['smb_username'] ?? '');
            $smb_password = trim($_POST['smb_password'] ?? '');
            $smb_domain = trim($_POST['smb_domain'] ?? '');
            $retention_days = (int)($_POST['retention_days'] ?? 30);
            $status = $_POST['status'] ?? 'active';
            
            if ($id <= 0) throw new Exception('Invalid backup job ID');
            if (empty($name)) throw new Exception('Backup job name is required');
            if (empty($schedule)) throw new Exception('Schedule is required');
            
            // Validate cron expression
            if (!validateCronExpression($schedule)) {
                throw new Exception('Invalid cron expression format');
            }
            
            // Get existing password if new one not provided
            $encrypted_password = '';
            if (!empty($smb_password)) {
                $encrypted_password = encryptPassword($smb_password);
            } else {
                $stmt = $pdo->prepare("SELECT smb_password FROM backup_jobs WHERE id = ?");
                $stmt->execute([$id]);
                $encrypted_password = $stmt->fetchColumn();
            }
            
            // Calculate next backup time
            $next_backup = calculateNextRun($schedule);
            
            // Update backup job
            $stmt = $pdo->prepare("
                UPDATE backup_jobs 
                SET name = ?, schedule = ?, destination_type = ?, nextcloud_folder = ?,
                    smb_path = ?, smb_username = ?, smb_password = ?, smb_domain = ?,
                    retention_days = ?, next_backup = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $schedule, $destination_type, $nextcloud_folder,
                $smb_path, $smb_username, $encrypted_password, $smb_domain,
                $retention_days, $next_backup, $status, $id
            ]);
            
            logAction($pdo, $user_id, 'backup_job_updated', 'Updated backup job: ' . $name);
            
            echo json_encode(['success' => true, 'message' => 'Backup job updated successfully']);
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) throw new Exception('Invalid backup job ID');
            
            // Get job name for logging
            $stmt = $pdo->prepare("SELECT name FROM backup_jobs WHERE id = ?");
            $stmt->execute([$id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) throw new Exception('Backup job not found');
            
            // Delete job (history records will remain via ON DELETE SET NULL)
            $stmt = $pdo->prepare("DELETE FROM backup_jobs WHERE id = ?");
            $stmt->execute([$id]);
            
            logAction($pdo, $user_id, 'backup_job_deleted', 'Deleted backup job: ' . $job['name']);
            
            echo json_encode(['success' => true, 'message' => 'Backup job deleted successfully']);
            break;
            
        case 'manual_backup':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) throw new Exception('Invalid backup job ID');
            
            // Get job details
            $stmt = $pdo->prepare("SELECT * FROM backup_jobs WHERE id = ?");
            $stmt->execute([$id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) throw new Exception('Backup job not found');
            
            // Perform backup
            $result = performBackup($pdo, $job);
            
            if ($result['success']) {
                logAction($pdo, $user_id, 'manual_backup', 'Manual backup completed: ' . $job['name']);
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'test_smb':
            $smb_path = trim($_POST['smb_path'] ?? '');
            $smb_username = trim($_POST['smb_username'] ?? '');
            $smb_password = trim($_POST['smb_password'] ?? '');
            $smb_domain = trim($_POST['smb_domain'] ?? '');
            
            if (empty($smb_path) || empty($smb_username) || empty($smb_password)) {
                throw new Exception('SMB credentials are required');
            }
            
            // Test SMB connection
            $result = testSMBConnection($smb_path, $smb_username, $smb_password, $smb_domain);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Perform database backup
 */
function performBackup($pdo, $job) {
    try {
        // Generate filename
        $filename = 'crashhockey_backup_' . date('Ymd_His') . '.sql.gz';
        $temp_dir = __DIR__ . '/tmp/';
        
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        $sql_file = $temp_dir . 'backup_' . time() . '.sql';
        $gz_file = $sql_file . '.gz';
        
        // Get database credentials from db_config.php
        require __DIR__ . '/db_config.php';
        $db_host = DB_HOST;
        $db_name = DB_NAME;
        $db_user = DB_USER;
        $db_pass = DB_PASS;
        
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
        
        // Upload to Nextcloud if configured
        if ($job['destination_type'] === 'nextcloud' || $job['destination_type'] === 'both') {
            try {
                $nc_settings = getNextcloudSettings($pdo);
                $connection = connectNextcloud($nc_settings);
                
                $remote_path = rtrim($job['nextcloud_folder'], '/') . '/' . $filename;
                $result = uploadToNextcloud($connection, $gz_file, $remote_path);
                
                if ($result) {
                    $success_destinations[] = 'Nextcloud: ' . $remote_path;
                } else {
                    $errors[] = 'Nextcloud upload failed';
                }
            } catch (Exception $e) {
                $errors[] = 'Nextcloud: ' . $e->getMessage();
            }
        }
        
        // Upload to SMB if configured
        if ($job['destination_type'] === 'smb' || $job['destination_type'] === 'both') {
            try {
                $password = decryptPassword($job['smb_password']);
                $result = uploadToSMB($gz_file, $filename, $job['smb_path'], $job['smb_username'], $password, $job['smb_domain']);
                
                if ($result['success']) {
                    $success_destinations[] = 'SMB: ' . $job['smb_path'] . '/' . $filename;
                } else {
                    $errors[] = 'SMB: ' . $result['message'];
                }
            } catch (Exception $e) {
                $errors[] = 'SMB: ' . $e->getMessage();
            }
        }
        
        // Clean up temp file
        @unlink($gz_file);
        
        // Record backup history
        $backup_status = empty($errors) ? 'success' : 'failed';
        $destinations = implode(', ', $success_destinations);
        $error_msg = empty($errors) ? null : implode('; ', $errors);
        
        $stmt = $pdo->prepare("
            INSERT INTO backup_history (backup_job_id, filename, file_size, destination, status, error_message)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$job['id'], $filename, $file_size, $destinations, $backup_status, $error_msg]);
        
        // Update last_backup time
        $stmt = $pdo->prepare("UPDATE backup_jobs SET last_backup = NOW() WHERE id = ?");
        $stmt->execute([$job['id']]);
        
        // Clean old backups based on retention
        cleanOldBackups($pdo, $job);
        
        if (empty($errors)) {
            return [
                'success' => true,
                'message' => 'Backup completed successfully. Destinations: ' . $destinations
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
 * Upload file to Nextcloud
 */
function uploadToNextcloud($connection, $local_file, $remote_path) {
    $webdav_url = $connection['url'] . '/remote.php/dav/files/' . $connection['username'] . $remote_path;
    
    $ch = curl_init($webdav_url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $connection['username'] . ':' . $connection['password']);
    curl_setopt($ch, CURLOPT_INFILE, fopen($local_file, 'r'));
    curl_setopt($ch, CURLOPT_INFILESIZE, filesize($local_file));
    curl_setopt($ch, CURLOPT_UPLOAD, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ($http_code === 201 || $http_code === 204);
}

/**
 * Upload file to SMB share
 */
function uploadToSMB($local_file, $filename, $smb_path, $username, $password, $domain = '') {
    // Use smbclient command
    $remote_path = rtrim($smb_path, '/') . '/' . $filename;
    
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
 * Test SMB connection
 */
function testSMBConnection($smb_path, $username, $password, $domain = '') {
    $domain_part = !empty($domain) ? '-W ' . escapeshellarg($domain) . ' ' : '';
    
    $command = sprintf(
        'smbclient %s -U %s%%%s %s -c "ls" 2>&1',
        escapeshellarg($smb_path),
        escapeshellarg($username),
        escapeshellarg($password),
        $domain_part
    );
    
    exec($command, $output, $return_var);
    
    if ($return_var === 0) {
        return [
            'success' => true,
            'message' => 'SMB connection successful. Share is accessible.'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'SMB connection failed: ' . implode("\n", $output)
        ];
    }
}

/**
 * Clean old backups based on retention policy
 */
function cleanOldBackups($pdo, $job) {
    $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $job['retention_days'] . ' days'));
    
    // Get old backups
    $stmt = $pdo->prepare("
        SELECT id, filename, destination 
        FROM backup_history 
        WHERE backup_job_id = ? AND backup_date < ? AND status = 'success'
    ");
    $stmt->execute([$job['id'], $cutoff_date]);
    $old_backups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($old_backups as $backup) {
        // Note: Actual file deletion from Nextcloud/SMB would be implemented here
        // For now, just mark as cleaned in database
        $stmt = $pdo->prepare("DELETE FROM backup_history WHERE id = ?");
        $stmt->execute([$backup['id']]);
    }
}

/**
 * Encrypt password using AES-256
 */
function encryptPassword($password) {
    $key = getenv('ENCRYPTION_KEY') ?: 'change_this_encryption_key_in_production';
    $iv = openssl_random_pseudo_bytes(16);
    $encrypted = openssl_encrypt($password, 'AES-256-CBC', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

/**
 * Decrypt password
 */
function decryptPassword($encrypted_password) {
    $key = getenv('ENCRYPTION_KEY') ?: 'change_this_encryption_key_in_production';
    $data = base64_decode($encrypted_password);
    $iv = substr($data, 0, 16);
    $encrypted = substr($data, 16);
    return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
}

/**
 * Validate cron expression
 */
function validateCronExpression($expression) {
    $parts = explode(' ', trim($expression));
    if (count($parts) !== 5) {
        return false;
    }
    foreach ($parts as $part) {
        if (!preg_match('/^[\d\*\-\/,]+$/', $part)) {
            return false;
        }
    }
    return true;
}

/**
 * Calculate next run time
 */
function calculateNextRun($cron_expression) {
    $parts = explode(' ', trim($cron_expression));
    if (count($parts) !== 5) {
        return null;
    }
    
    list($minute, $hour, $day, $month, $weekday) = $parts;
    
    if ($cron_expression === '0 * * * *') {
        $next = strtotime(date('Y-m-d H:00:00', strtotime('+1 hour')));
    } elseif ($cron_expression === '0 0 * * *') {
        $next = strtotime('tomorrow midnight');
    } elseif ($cron_expression === '0 2 * * *') {
        $next = strtotime('tomorrow 02:00:00');
        if (time() < strtotime('today 02:00:00')) {
            $next = strtotime('today 02:00:00');
        }
    } elseif ($cron_expression === '0 0 * * 0') {
        $next = strtotime('next sunday midnight');
    } elseif ($cron_expression === '0 0 1 * *') {
        $next = strtotime('first day of next month midnight');
    } elseif (preg_match('/^\d+ \d+ \* \* \*$/', $cron_expression)) {
        $time = sprintf('%02d:%02d:00', $hour, $minute);
        $next = strtotime('today ' . $time);
        if ($next <= time()) {
            $next = strtotime('tomorrow ' . $time);
        }
    } else {
        $next = strtotime('+1 day');
    }
    
    return date('Y-m-d H:i:s', $next);
}

/**
 * Log action
 */
function logAction($pdo, $user_id, $action, $details) {
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (user_id, action, ip_address, details, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $details]);
}
?>
