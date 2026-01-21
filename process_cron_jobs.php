<?php
/**
 * Process Cron Jobs
 * CRUD operations for cron job management
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
$action = $_POST['action'] ?? '';

try {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        throw new Exception('Invalid CSRF token');
    }
    
    switch ($action) {
        case 'create':
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $command = trim($_POST['command'] ?? '');
            $schedule = trim($_POST['schedule'] ?? '');
            $type = $_POST['type'] ?? 'admin';
            $status = $_POST['status'] ?? 'active';
            $parameters = trim($_POST['parameters'] ?? '');
            
            // Validate inputs
            if (empty($name)) throw new Exception('Job name is required');
            if (empty($command)) throw new Exception('Command is required');
            if (empty($schedule)) throw new Exception('Schedule is required');
            
            // Validate cron expression
            if (!validateCronExpression($schedule)) {
                throw new Exception('Invalid cron expression format');
            }
            
            // Validate parameters if provided
            if (!empty($parameters)) {
                $json_test = json_decode($parameters);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Parameters must be valid JSON');
                }
            }
            
            // Calculate next run time
            $next_run = calculateNextRun($schedule);
            
            // Insert cron job
            $stmt = $pdo->prepare("
                INSERT INTO cron_jobs (name, description, command, schedule, type, status, parameters, created_by, next_run)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$name, $description, $command, $schedule, $type, $status, $parameters, $user_id, $next_run]);
            
            logAction($pdo, $user_id, 'cron_job_created', 'Created cron job: ' . $name);
            
            echo json_encode(['success' => true, 'message' => 'Cron job created successfully']);
            break;
            
        case 'update':
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $command = trim($_POST['command'] ?? '');
            $schedule = trim($_POST['schedule'] ?? '');
            $type = $_POST['type'] ?? 'admin';
            $status = $_POST['status'] ?? 'active';
            $parameters = trim($_POST['parameters'] ?? '');
            
            if ($id <= 0) throw new Exception('Invalid job ID');
            if (empty($name)) throw new Exception('Job name is required');
            if (empty($command)) throw new Exception('Command is required');
            if (empty($schedule)) throw new Exception('Schedule is required');
            
            // Validate cron expression
            if (!validateCronExpression($schedule)) {
                throw new Exception('Invalid cron expression format');
            }
            
            // Validate parameters if provided
            if (!empty($parameters)) {
                $json_test = json_decode($parameters);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new Exception('Parameters must be valid JSON');
                }
            }
            
            // Calculate next run time
            $next_run = calculateNextRun($schedule);
            
            // Update cron job
            $stmt = $pdo->prepare("
                UPDATE cron_jobs 
                SET name = ?, description = ?, command = ?, schedule = ?, type = ?, status = ?, parameters = ?, next_run = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $description, $command, $schedule, $type, $status, $parameters, $next_run, $id]);
            
            logAction($pdo, $user_id, 'cron_job_updated', 'Updated cron job: ' . $name);
            
            echo json_encode(['success' => true, 'message' => 'Cron job updated successfully']);
            break;
            
        case 'delete':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) throw new Exception('Invalid job ID');
            
            // Get job name for logging
            $stmt = $pdo->prepare("SELECT name FROM cron_jobs WHERE id = ?");
            $stmt->execute([$id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) throw new Exception('Job not found');
            
            // Delete job
            $stmt = $pdo->prepare("DELETE FROM cron_jobs WHERE id = ?");
            $stmt->execute([$id]);
            
            logAction($pdo, $user_id, 'cron_job_deleted', 'Deleted cron job: ' . $job['name']);
            
            echo json_encode(['success' => true, 'message' => 'Cron job deleted successfully']);
            break;
            
        case 'toggle_status':
            $id = (int)($_POST['id'] ?? 0);
            
            if ($id <= 0) throw new Exception('Invalid job ID');
            
            // Get current status
            $stmt = $pdo->prepare("SELECT name, status FROM cron_jobs WHERE id = ?");
            $stmt->execute([$id]);
            $job = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$job) throw new Exception('Job not found');
            
            // Toggle status
            $new_status = ($job['status'] === 'active') ? 'inactive' : 'active';
            
            $stmt = $pdo->prepare("UPDATE cron_jobs SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $id]);
            
            logAction($pdo, $user_id, 'cron_job_toggled', 'Toggled cron job status: ' . $job['name'] . ' to ' . $new_status);
            
            echo json_encode(['success' => true, 'message' => 'Status updated to ' . $new_status]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Validate cron expression format
 */
function validateCronExpression($expression) {
    // Basic validation: 5 parts separated by spaces
    $parts = explode(' ', trim($expression));
    if (count($parts) !== 5) {
        return false;
    }
    
    // Each part should be numeric, *, or contain valid cron characters
    foreach ($parts as $part) {
        if (!preg_match('/^[\d\*\-\/,]+$/', $part)) {
            return false;
        }
    }
    
    return true;
}

/**
 * Calculate next run time based on cron expression
 */
function calculateNextRun($cron_expression) {
    // Parse cron expression
    $parts = explode(' ', trim($cron_expression));
    if (count($parts) !== 5) {
        return null;
    }
    
    list($minute, $hour, $day, $month, $weekday) = $parts;
    
    // Start from next minute
    $timestamp = strtotime('+1 minute');
    
    // Simple calculation for common patterns
    if ($cron_expression === '0 * * * *') {
        // Hourly at :00
        $next = strtotime(date('Y-m-d H:00:00', strtotime('+1 hour')));
    } elseif ($cron_expression === '0 0 * * *') {
        // Daily at midnight
        $next = strtotime('tomorrow midnight');
    } elseif ($cron_expression === '0 2 * * *') {
        // Daily at 2 AM
        $next = strtotime('tomorrow 02:00:00');
        if (time() < strtotime('today 02:00:00')) {
            $next = strtotime('today 02:00:00');
        }
    } elseif ($cron_expression === '0 0 * * 0') {
        // Weekly on Sunday
        $next = strtotime('next sunday midnight');
    } elseif ($cron_expression === '0 0 1 * *') {
        // Monthly on 1st
        $next = strtotime('first day of next month midnight');
    } elseif (preg_match('/^\d+ \d+ \* \* \*$/', $cron_expression)) {
        // Daily at specific time
        $time = sprintf('%02d:%02d:00', $hour, $minute);
        $next = strtotime('today ' . $time);
        if ($next <= time()) {
            $next = strtotime('tomorrow ' . $time);
        }
    } else {
        // Default: add 1 hour for unknown patterns
        $next = strtotime('+1 hour');
    }
    
    return date('Y-m-d H:i:s', $next);
}

/**
 * Log action to security logs
 */
function logAction($pdo, $user_id, $action, $details) {
    $stmt = $pdo->prepare("
        INSERT INTO security_logs (user_id, action, ip_address, details, created_at)
        VALUES (?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$user_id, $action, $_SERVER['REMOTE_ADDR'] ?? 'unknown', $details]);
}
?>
