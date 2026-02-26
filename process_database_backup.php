<?php
/**
 * Process Database Backup Jobs
 * CRUD operations and manual backup triggers
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/cloud_config.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Ensure database connection is available
if (!$db_connected || $pdo === null) {
    echo json_encode(['success' => false, 'message' => 'Database connection not available. Please check your configuration.']);
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
            $nextcloud_folder = trim($_POST['nextcloud_folder'] ?? '/ArcticWolves/Backups/');
            $smb_path = trim($_POST['smb_path'] ?? '');
            $smb_username = trim($_POST['smb_username'] ?? '');
            $smb_password = trim($_POST['smb_password'] ?? '');
            $smb_domain = trim($_POST['smb_domain'] ?? '');
            $retention_days = (int)($_POST['retention_days'] ?? 30);
            $keep_count = max(1, (int)($_POST['keep_count'] ?? 3));
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
                 smb_username, smb_password, smb_domain, retention_days, keep_count, next_backup, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $name, $schedule, $backup_type, $destination_type, $nextcloud_folder,
                $smb_path, $smb_username, $encrypted_password, $smb_domain,
                $retention_days, $keep_count, $next_backup, $status, $user_id
            ]);
            $jobId = $pdo->lastInsertId();
            Auditor::log($pdo, $user_id, 'create', 'backup_jobs', $jobId, ['action' => 'Created backup job: ' . $name]);
            
            logAction($pdo, $user_id, 'backup_job_created', 'Created backup job: ' . $name);
            
            echo json_encode(['success' => true, 'message' => 'Backup job created successfully']);
            break;
            
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $schedule = trim($_POST['schedule'] ?? '');
            $destination_type = $_POST['destination_type'] ?? 'nextcloud';
            $nextcloud_folder = trim($_POST['nextcloud_folder'] ?? '/ArcticWolves/Backups/');
            $smb_path = trim($_POST['smb_path'] ?? '');
            $smb_username = trim($_POST['smb_username'] ?? '');
            $smb_password = trim($_POST['smb_password'] ?? '');
            $smb_domain = trim($_POST['smb_domain'] ?? '');
            $retention_days = (int)($_POST['retention_days'] ?? 30);
            $keep_count = max(1, (int)($_POST['keep_count'] ?? 3));
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
                    retention_days = ?, keep_count = ?, next_backup = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $name, $schedule, $destination_type, $nextcloud_folder,
                $smb_path, $smb_username, $encrypted_password, $smb_domain,
                $retention_days, $keep_count, $next_backup, $status, $id
            ]);
            Auditor::log($pdo, $user_id, 'update', 'backup_jobs', $id, ['action' => 'Updated backup job: ' . $name]);
            
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
            Auditor::log($pdo, $user_id, 'delete', 'backup_jobs', $id, ['action' => 'Deleted backup job: ' . $job['name']]);
            
            logAction($pdo, $user_id, 'backup_job_deleted', 'Deleted backup job: ' . $job['name']);
            
            echo json_encode(['success' => true, 'message' => 'Backup job deleted successfully']);
            break;
            
        case 'manual_backup':
            $id = (int)($_POST['id'] ?? 0);
            
            // Allow quick backup without a backup job ID
            if ($id <= 0) {
                // Create a temporary job config for quick backup
                $job = [
                    'id' => 0,
                    'name' => 'Quick Backup',
                    'destination_type' => 'both_nextcloud',
                    'nextcloud_folder' => '/Backups/',
                    'smb_path' => '',
                    'smb_username' => '',
                    'smb_password' => '',
                    'smb_domain' => '',
                    'retention_days' => 30
                ];
            } else {
                // Get job details
                $stmt = $pdo->prepare("SELECT * FROM backup_jobs WHERE id = ?");
                $stmt->execute([$id]);
                $job = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$job) throw new Exception('Backup job not found');
            }
            
            // Perform backup
            $result = performBackup($pdo, $job);
            
            if ($result['success']) {
                logAction($pdo, $user_id, 'manual_backup', 'Manual backup completed: ' . $job['name']);
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'force_nextcloud':
            // Force backup directly to both Nextcloud instances
            $nextcloud_folder = trim($_POST['nextcloud_folder'] ?? '/Backups/');
            
            $job = [
                'id' => 0,
                'name' => 'Force Nextcloud Backup',
                'destination_type' => 'both_nextcloud',
                'nextcloud_folder' => $nextcloud_folder,
                'smb_path' => '',
                'smb_username' => '',
                'smb_password' => '',
                'smb_domain' => '',
                'retention_days' => 30
            ];
            
            $result = performBackup($pdo, $job);
            
            if ($result['success']) {
                logAction($pdo, $user_id, 'force_nextcloud_backup', 'Forced backup to Nextcloud: ' . $nextcloud_folder);
                echo json_encode(['success' => true, 'message' => $result['message']]);
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'backup_to_file':
            // Create backup and return it as a downloadable file
            $job = [
                'id' => 0,
                'name' => 'Download Backup',
                'destination_type' => 'local',
                'nextcloud_folder' => '',
                'smb_path' => '',
                'smb_username' => '',
                'smb_password' => '',
                'smb_domain' => '',
                'retention_days' => 30
            ];
            
            $result = performBackup($pdo, $job);
            
            if ($result['success'] && !empty($result['file_path'])) {
                logAction($pdo, $user_id, 'backup_download', 'Downloaded backup file');
                
                // Send file as download
                $filepath = $result['file_path'];
                $filename = basename($filepath);
                $content_type = str_ends_with($filename, '.gz') ? 'application/gzip' : 'application/sql';
                
                header('Content-Type: ' . $content_type);
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Content-Length: ' . filesize($filepath));
                header('Cache-Control: no-cache, no-store, must-revalidate');
                
                readfile($filepath);
                exit;
            } else {
                throw new Exception($result['message'] ?? 'Backup failed');
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
            
        case 'repair_optimize':
            // Get all tables from database
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $results = [];
            $errors = [];
            
            foreach ($tables as $table) {
                // Validate table name - only alphanumeric characters and underscores allowed
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    $errors[] = "Skipped invalid table name: " . substr($table, 0, 50);
                    continue;
                }
                
                // Use prepared statement pattern with validated table name
                // Note: Table names cannot be parameterized, but we've validated the format above
                $safe_table = $pdo->quote($table);
                $safe_table = substr($safe_table, 1, -1); // Remove quotes added by PDO::quote
                
                // Check table
                $check = $pdo->query("CHECK TABLE `$safe_table`")->fetch(PDO::FETCH_ASSOC);
                $results[$table] = ['check' => $check['Msg_text'] ?? 'OK'];
                
                // Repair if needed
                if (($check['Msg_text'] ?? '') !== 'OK') {
                    $repair = $pdo->query("REPAIR TABLE `$safe_table`")->fetch(PDO::FETCH_ASSOC);
                    $results[$table]['repair'] = $repair['Msg_text'] ?? 'Unknown';
                }
                
                // Optimize table
                $optimize = $pdo->query("OPTIMIZE TABLE `$safe_table`")->fetch(PDO::FETCH_ASSOC);
                $results[$table]['optimize'] = $optimize['Msg_text'] ?? 'OK';
                
                // Analyze table
                $analyze = $pdo->query("ANALYZE TABLE `$safe_table`")->fetch(PDO::FETCH_ASSOC);
                $results[$table]['analyze'] = $analyze['Msg_text'] ?? 'OK';
            }
            
            logAction($pdo, $user_id, 'database_optimized', 'Repaired and optimized ' . count($results) . ' tables');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully repaired and optimized ' . count($results) . ' database tables.',
                'tables_processed' => count($results),
                'errors' => $errors
            ]);
            break;
            
        case 'clear_cache':
            $cache_cleared = [];
            
            // Clear file cache directory
            $cache_dir = __DIR__ . '/cache';
            if (is_dir($cache_dir)) {
                $files = glob($cache_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                        $cache_cleared[] = basename($file);
                    }
                }
            }
            
            // Clear tmp directory contents (non-essential)
            $tmp_dir = __DIR__ . '/tmp';
            if (is_dir($tmp_dir)) {
                $files = glob($tmp_dir . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                        $cache_cleared[] = 'tmp/' . basename($file);
                    }
                }
            }
            
            logAction($pdo, $user_id, 'cache_cleared', 'Cleared ' . count($cache_cleared) . ' cached files');
            
            echo json_encode([
                'success' => true, 
                'message' => 'Successfully cleared ' . count($cache_cleared) . ' cached files.',
                'files_cleared' => count($cache_cleared)
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    ErrorLogger::error('Database backup error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Perform database backup
 */
function performBackup($pdo, $job) {
    try {
        // Generate filename
        $filename = 'arctic_wolves_backup_' . date('Ymd_His') . '.sql.gz';
        $temp_dir = __DIR__ . '/tmp/';
        $backups_dir = __DIR__ . '/backups/';
        
        if (!is_dir($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }
        
        if (!is_dir($backups_dir)) {
            mkdir($backups_dir, 0755, true);
        }
        
        $sql_file = $temp_dir . 'backup_' . time() . '.sql';
        $gz_file = $sql_file . '.gz';
        
        // Get database credentials from environment variables (set by db_config.php)
        // Also try loading from env file directly as fallback for reliability
        $db_host = $_ENV['DB_HOST'] ?? null;
        $db_name = $_ENV['DB_NAME'] ?? null;
        $db_user = $_ENV['DB_USER'] ?? null;
        $db_pass = $_ENV['DB_PASS'] ?? '';
        
        // Fallback: if $_ENV is not populated, re-read from environment file
        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            $env_paths = [
                '/config/arctic_wolves.env',
                __DIR__ . '/arctic_wolves.env',
                __DIR__ . '/.env',
                '/var/www/html/arctic_wolves/.env'
            ];
            foreach ($env_paths as $env_path) {
                if (file_exists($env_path)) {
                    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($lines as $line) {
                        $line = trim($line);
                        if (strpos($line, '#') === 0 || empty($line)) continue;
                        $parts = explode('=', $line, 2);
                        if (count($parts) === 2) {
                            $name = trim($parts[0]);
                            $value = trim(trim($parts[1]), '"\'');
                            if ($name === 'DB_HOST') $db_host = $value;
                            if ($name === 'DB_NAME') $db_name = $value;
                            if ($name === 'DB_USER') $db_user = $value;
                            if ($name === 'DB_PASS') $db_pass = $value;
                        }
                    }
                    break;
                }
            }
        }
        
        // Validate required credentials are set
        if (empty($db_host) || empty($db_name) || empty($db_user)) {
            throw new Exception('Database credentials not configured. Please check your environment configuration.');
        }
        
        $dump_success = false;
        
        // Try mysqldump first
        $mysqldump_path = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
        if (!empty($mysqldump_path)) {
            // Build mysqldump command with proper argument formatting
            $cmd_parts = [
                escapeshellarg($mysqldump_path),
                '--host=' . escapeshellarg($db_host),
                '--user=' . escapeshellarg($db_user),
            ];
            
            if (!empty($db_pass)) {
                $cmd_parts[] = '--password=' . escapeshellarg($db_pass);
            }
            
            $cmd_parts[] = '--single-transaction';
            $cmd_parts[] = '--routines';
            $cmd_parts[] = '--triggers';
            $cmd_parts[] = escapeshellarg($db_name);
            
            $command = implode(' ', $cmd_parts) . ' > ' . escapeshellarg($sql_file) . ' 2>&1';
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($sql_file) && filesize($sql_file) > 0) {
                $dump_success = true;
            }
        }
        
        // PHP-based fallback if mysqldump is not available or failed
        if (!$dump_success) {
            $sql_content = "-- Arctic Wolves Database Backup\n";
            $sql_content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sql_content .= "-- Method: PHP PDO Export\n\n";
            $sql_content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            // Get all tables
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // Validate table name
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $table)) {
                    continue;
                }
                
                // Get CREATE TABLE statement
                $create_stmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $sql_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $sql_content .= $create_stmt['Create Table'] . ";\n\n";
                
                // Get all data
                $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
                
                if (!empty($rows)) {
                    $columns = array_keys($rows[0]);
                    $col_list = implode('`, `', $columns);
                    
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $values[] = 'NULL';
                            } else {
                                $values[] = $pdo->quote($value);
                            }
                        }
                        $sql_content .= "INSERT INTO `{$table}` (`{$col_list}`) VALUES (" . implode(', ', $values) . ");\n";
                    }
                    $sql_content .= "\n";
                }
            }
            
            $sql_content .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            
            if (file_put_contents($sql_file, $sql_content) === false) {
                throw new Exception('Failed to write backup file');
            }
            
            $dump_success = true;
        }
        
        if (!$dump_success) {
            throw new Exception('Database backup failed. Neither mysqldump nor PHP export succeeded.');
        }
        
        // Compress SQL file
        if (function_exists('gzopen')) {
            $gz = gzopen($gz_file, 'wb9');
            if ($gz) {
                $fp = fopen($sql_file, 'rb');
                while (!feof($fp)) {
                    gzwrite($gz, fread($fp, 8192));
                }
                fclose($fp);
                gzclose($gz);
                @unlink($sql_file);
            } else {
                // If gzip fails, use the uncompressed file
                $gz_file = $sql_file;
                $filename = str_replace('.sql.gz', '.sql', $filename);
            }
        } else {
            // Try command-line gzip
            exec('gzip -9 ' . escapeshellarg($sql_file), $gzip_output, $gzip_return);
            if ($gzip_return !== 0 || !file_exists($gz_file)) {
                // Fall back to uncompressed
                $gz_file = $sql_file;
                $filename = str_replace('.sql.gz', '.sql', $filename);
            }
        }
        
        $file_size = filesize($gz_file);
        $success_destinations = [];
        $errors = [];
        
        // Handle local backup (for quick backup from system tools)
        if ($job['destination_type'] === 'local') {
            $local_backup_path = $backups_dir . $filename;
            if (rename($gz_file, $local_backup_path)) {
                $success_destinations[] = 'Local: /backups/' . $filename;
                $gz_file = $local_backup_path; // Update path for potential cleanup later
            } else {
                $errors[] = 'Local: Failed to save backup file';
            }
        }
        
        // Read file content once for Nextcloud uploads
        $nc_file_content = null;
        if ($job['destination_type'] === 'nextcloud' || $job['destination_type'] === 'both' || $job['destination_type'] === 'both_nextcloud') {
            $nc_file_content = file_get_contents($gz_file);
            if ($nc_file_content === false) {
                $errors[] = 'Failed to read backup file for Nextcloud upload';
                $nc_file_content = null;
            }
        }
        
        // Upload to primary RustFS if configured
        if ($nc_file_content !== null && ($job['destination_type'] === 'nextcloud' || $job['destination_type'] === 'both' || $job['destination_type'] === 'both_nextcloud')) {
            try {
                $rustfs = getRustFSSettings($pdo);
                if (isRustFSConfigured($rustfs)) {
                    $object_key = 'Backups/' . $filename;
                    $result = uploadContentToRustFS($rustfs, $nc_file_content, $object_key, 'application/gzip');
                    
                    if ($result['success']) {
                        $success_destinations[] = 'RustFS: ' . $result['url'];
                    } else {
                        $errors[] = 'RustFS upload failed: ' . ($result['message'] ?? '');
                    }
                } else {
                    $errors[] = 'RustFS not configured';
                }
            } catch (Exception $e) {
                $errors[] = 'RustFS: ' . $e->getMessage();
            }
        }
        
        // Upload to secondary RustFS if both_nextcloud destination is selected
        if ($nc_file_content !== null && $job['destination_type'] === 'both_nextcloud') {
            try {
                // Secondary storage also goes to RustFS with a different prefix
                $rustfs = getRustFSSettings($pdo);
                if (isRustFSConfigured($rustfs)) {
                    $object_key2 = 'Backups/secondary/' . $filename;
                    $result2 = uploadContentToRustFS($rustfs, $nc_file_content, $object_key2, 'application/gzip');
                    
                    if ($result2['success']) {
                        $success_destinations[] = 'RustFS-secondary: ' . $result2['url'];
                    } else {
                        $errors[] = 'Secondary RustFS upload failed';
                    }
                }
            } catch (Exception $e) {
                $errors[] = 'Secondary RustFS: ' . $e->getMessage();
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
        
        // Clean up temp file (but keep local backups)
        if ($job['destination_type'] !== 'local' && file_exists($gz_file)) {
            @unlink($gz_file);
        }
        
        // Record backup history (only for scheduled jobs with ID)
        if ($job['id'] > 0) {
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
        }
        
        if (empty($errors)) {
            $dest_message = !empty($success_destinations) ? implode(', ', $success_destinations) : 'Backup created';
            return [
                'success' => true,
                'message' => 'Backup completed successfully. ' . $dest_message,
                'file_path' => $gz_file,
                'filename' => $filename
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Backup completed with errors: ' . implode('; ', $errors),
                'file_path' => file_exists($gz_file) ? $gz_file : null
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

// encryptPassword() and decryptPassword() are now defined in security.php

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
 * Calculate next run time.
 * Supports all schedule intervals used by Arctic Wolves backups.
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
        INSERT INTO security_logs (user_id, event_type, ip_address, description, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $details]);
}
?>
