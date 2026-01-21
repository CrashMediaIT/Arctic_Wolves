<?php
/**
 * Process Database Restore
 * Restore database from backup file
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) && !isset($_GET['csrf_token'])) {
        throw new Exception('CSRF token required');
    }
    
    $csrf_token = $_POST['csrf_token'] ?? $_GET['csrf_token'];
    if (!validateCSRFToken($csrf_token)) {
        throw new Exception('Invalid CSRF token');
    }
    
    switch ($action) {
        case 'upload':
            // Handle file upload
            if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('File upload failed');
            }
            
            $file = $_FILES['backup_file'];
            $filename = $file['name'];
            $tmp_path = $file['tmp_name'];
            
            // Validate file extension
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($ext, ['sql', 'gz'])) {
                throw new Exception('Invalid file type. Only .sql or .sql.gz files are allowed.');
            }
            
            // Create uploads directory if not exists
            $upload_dir = __DIR__ . '/tmp/restore/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Generate unique filename
            $restore_filename = 'restore_' . time() . '_' . $filename;
            $restore_path = $upload_dir . $restore_filename;
            
            // Move uploaded file
            if (!move_uploaded_file($tmp_path, $restore_path)) {
                throw new Exception('Failed to save uploaded file');
            }
            
            // If gzipped, decompress
            if ($ext === 'gz') {
                $sql_path = $upload_dir . 'restore_' . time() . '.sql';
                
                $gz = gzopen($restore_path, 'rb');
                if (!$gz) {
                    throw new Exception('Failed to open gzip file');
                }
                
                $sql = fopen($sql_path, 'wb');
                if (!$sql) {
                    throw new Exception('Failed to create SQL file');
                }
                
                while (!gzeof($gz)) {
                    fwrite($sql, gzread($gz, 4096));
                }
                
                gzclose($gz);
                fclose($sql);
                
                @unlink($restore_path);
                $restore_path = $sql_path;
            }
            
            // Validate SQL file
            $validation = validateSQLFile($restore_path);
            
            if (!$validation['valid']) {
                @unlink($restore_path);
                throw new Exception('Invalid SQL file: ' . $validation['error']);
            }
            
            // Store path in session for next step
            $_SESSION['restore_file'] = $restore_path;
            $_SESSION['restore_stats'] = $validation['stats'];
            
            echo json_encode([
                'success' => true,
                'message' => 'File validated successfully',
                'stats' => $validation['stats']
            ]);
            break;
            
        case 'restore':
            if (!isset($_SESSION['restore_file'])) {
                throw new Exception('No file uploaded. Please upload a backup file first.');
            }
            
            $restore_path = $_SESSION['restore_file'];
            
            if (!file_exists($restore_path)) {
                throw new Exception('Backup file not found');
            }
            
            // Perform restore
            $result = performRestore($pdo, $restore_path, $user_id);
            
            // Clean up
            @unlink($restore_path);
            unset($_SESSION['restore_file']);
            unset($_SESSION['restore_stats']);
            
            if ($result['success']) {
                logAction($pdo, $user_id, 'database_restored', 'Database restored from backup');
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'details' => $result['details']
                ]);
            } else {
                throw new Exception($result['message']);
            }
            break;
            
        case 'cancel':
            // Cancel restore and clean up
            if (isset($_SESSION['restore_file'])) {
                @unlink($_SESSION['restore_file']);
                unset($_SESSION['restore_file']);
                unset($_SESSION['restore_stats']);
            }
            
            echo json_encode(['success' => true, 'message' => 'Restore cancelled']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Validate SQL file
 */
function validateSQLFile($file_path) {
    try {
        $content = file_get_contents($file_path);
        
        if (empty($content)) {
            return ['valid' => false, 'error' => 'File is empty'];
        }
        
        // Check for SQL keywords
        if (stripos($content, 'CREATE TABLE') === false && stripos($content, 'INSERT INTO') === false) {
            return ['valid' => false, 'error' => 'File does not contain valid SQL statements'];
        }
        
        // Count CREATE TABLE statements
        $create_count = substr_count(strtoupper($content), 'CREATE TABLE');
        
        // Count INSERT statements
        $insert_count = substr_count(strtoupper($content), 'INSERT INTO');
        
        // Check file size
        $file_size = filesize($file_path);
        $file_size_mb = round($file_size / 1024 / 1024, 2);
        
        return [
            'valid' => true,
            'stats' => [
                'tables' => $create_count,
                'inserts' => $insert_count,
                'size_mb' => $file_size_mb
            ]
        ];
        
    } catch (Exception $e) {
        return ['valid' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Perform database restore
 */
function performRestore($pdo, $file_path, $user_id) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Read SQL file
        $sql_content = file_get_contents($file_path);
        
        if (empty($sql_content)) {
            throw new Exception('SQL file is empty');
        }
        
        // Split into statements
        $statements = splitSQLStatements($sql_content);
        
        $successful = 0;
        $failed = 0;
        $errors = [];
        
        // Disable foreign key checks temporarily
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            
            if (empty($statement) || $statement === ';') {
                continue;
            }
            
            try {
                $pdo->exec($statement);
                $successful++;
            } catch (Exception $e) {
                $failed++;
                $errors[] = [
                    'statement' => substr($statement, 0, 100) . '...',
                    'error' => $e->getMessage()
                ];
                
                // If too many errors, stop
                if ($failed > 10) {
                    throw new Exception('Too many errors during restore. Aborting.');
                }
            }
        }
        
        // Re-enable foreign key checks
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        
        // Verify critical tables exist
        $critical_tables = ['users', 'sessions', 'system_settings'];
        $missing_tables = [];
        
        foreach ($critical_tables as $table) {
            $result = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($result->rowCount() === 0) {
                $missing_tables[] = $table;
            }
        }
        
        if (!empty($missing_tables)) {
            $pdo->rollBack();
            throw new Exception('Critical tables missing after restore: ' . implode(', ', $missing_tables));
        }
        
        // Commit transaction
        $pdo->commit();
        
        return [
            'success' => true,
            'message' => 'Database restored successfully',
            'details' => [
                'successful_statements' => $successful,
                'failed_statements' => $failed,
                'errors' => $errors
            ]
        ];
        
    } catch (Exception $e) {
        // Rollback on error
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Re-enable foreign key checks
        try {
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        } catch (Exception $e2) {
            // Ignore
        }
        
        return [
            'success' => false,
            'message' => 'Restore failed: ' . $e->getMessage()
        ];
    }
}

/**
 * Split SQL content into individual statements
 */
function splitSQLStatements($sql_content) {
    // Remove comments
    $sql_content = preg_replace('/^--.*$/m', '', $sql_content);
    $sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);
    
    // Split by semicolon (but not within strings)
    $statements = [];
    $current_statement = '';
    $in_string = false;
    $string_char = '';
    
    for ($i = 0; $i < strlen($sql_content); $i++) {
        $char = $sql_content[$i];
        
        // Toggle string state
        if (($char === '"' || $char === "'") && ($i === 0 || $sql_content[$i-1] !== '\\')) {
            if (!$in_string) {
                $in_string = true;
                $string_char = $char;
            } elseif ($char === $string_char) {
                $in_string = false;
            }
        }
        
        // Split on semicolon if not in string
        if ($char === ';' && !$in_string) {
            $statements[] = $current_statement;
            $current_statement = '';
        } else {
            $current_statement .= $char;
        }
    }
    
    // Add final statement if not empty
    if (!empty(trim($current_statement))) {
        $statements[] = $current_statement;
    }
    
    return $statements;
}

/**
 * Log action
 */
function logAction($pdo, $user_id, $action, $details) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO security_logs (user_id, action, ip_address, details, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $details]);
    } catch (Exception $e) {
        // Ignore logging errors
    }
}
?>
