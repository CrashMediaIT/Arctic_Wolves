<?php
/**
 * Process Scheduled Reports
 * Handle CRUD operations for scheduled reports
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Check if user has report access
if (!in_array($user_role, ['coach', 'coach_plus', 'admin', 'team_coach'])) {
    header('Location: dashboard.php?page=home&error=access_denied');
    exit;
}

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'schedule_create':
            $report_type = trim($_POST['report_type'] ?? '');
            $report_name = trim($_POST['report_name'] ?? '');
            if (empty($report_name)) {
                $report_name = !empty($report_type) ? ucwords(str_replace('_', ' ', $report_type)) . ' Report' : 'Scheduled Report';
            }
            $frequency = trim($_POST['frequency']);
            $schedule_day = !empty($_POST['schedule_day']) ? intval($_POST['schedule_day']) : null;
            $schedule_time = !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : '08:00:00';
            $format = trim($_POST['format'] ?? 'pdf');
            $recipients = trim($_POST['email_recipients'] ?? $_POST['recipients'] ?? '');
            $parameters = !empty($_POST['parameters']) ? json_encode($_POST['parameters']) : null;
            
            if (empty($report_type) || empty($frequency)) {
                throw new Exception('Report type and frequency are required');
            }
            
            // Calculate next run time
            $next_run = calculateNextRun($frequency, $schedule_day, $schedule_time);
            
            $stmt = $pdo->prepare("
                INSERT INTO report_schedules 
                (report_name, report_type, schedule_frequency, schedule_day, schedule_time, 
                 recipients, parameters, is_active, next_run, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ");
            $stmt->execute([
                $report_name,
                $report_type,
                $frequency,
                $schedule_day,
                $schedule_time,
                $recipients,
                $parameters,
                $next_run,
                $user_id
            ]);
            
            header('Location: dashboard.php?page=scheduled_reports&status=created');
            exit;
            
        case 'schedule_update':
            $schedule_id = intval($_POST['schedule_id']);
            $report_type = trim($_POST['report_type'] ?? '');
            $report_name = trim($_POST['report_name'] ?? '');
            if (empty($report_name)) {
                $report_name = !empty($report_type) ? ucwords(str_replace('_', ' ', $report_type)) . ' Report' : 'Scheduled Report';
            }
            $frequency = trim($_POST['frequency']);
            $schedule_day = !empty($_POST['schedule_day']) ? intval($_POST['schedule_day']) : null;
            $schedule_time = !empty($_POST['schedule_time']) ? $_POST['schedule_time'] : '08:00:00';
            $format = trim($_POST['format'] ?? 'pdf');
            $recipients = trim($_POST['email_recipients'] ?? $_POST['recipients'] ?? '');
            $parameters = !empty($_POST['parameters']) ? json_encode($_POST['parameters']) : null;
            
            if (empty($report_type) || empty($frequency) || $schedule_id <= 0) {
                throw new Exception('Invalid schedule data');
            }
            
            // Calculate next run time
            $next_run = calculateNextRun($frequency, $schedule_day, $schedule_time);
            
            $stmt = $pdo->prepare("
                UPDATE report_schedules 
                SET report_name = ?, report_type = ?, schedule_frequency = ?, schedule_day = ?,
                    schedule_time = ?, recipients = ?, parameters = ?, next_run = ?
                WHERE id = ? AND created_by = ?
            ");
            $stmt->execute([
                $report_name,
                $report_type,
                $frequency,
                $schedule_day,
                $schedule_time,
                $recipients,
                $parameters,
                $next_run,
                $schedule_id,
                $user_id
            ]);
            
            header('Location: dashboard.php?page=scheduled_reports&status=updated');
            exit;
            
        case 'schedule_toggle':
            header('Content-Type: application/json');
            
            $schedule_id = intval($_POST['schedule_id']);
            
            if ($schedule_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
                exit;
            }
            
            // Get current status
            $stmt = $pdo->prepare("SELECT is_active FROM report_schedules WHERE id = ? AND created_by = ?");
            $stmt->execute([$schedule_id, $user_id]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$schedule) {
                echo json_encode(['success' => false, 'message' => 'Schedule not found']);
                exit;
            }
            
            $new_status = $schedule['is_active'] ? 0 : 1;
            $stmt = $pdo->prepare("UPDATE report_schedules SET is_active = ? WHERE id = ? AND created_by = ?");
            $stmt->execute([$new_status, $schedule_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Schedule status updated', 'is_active' => $new_status]);
            exit;
            
        case 'schedule_delete':
            header('Content-Type: application/json');
            
            $schedule_id = intval($_POST['schedule_id']);
            
            if ($schedule_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid schedule ID']);
                exit;
            }
            
            $stmt = $pdo->prepare("DELETE FROM report_schedules WHERE id = ? AND created_by = ?");
            $stmt->execute([$schedule_id, $user_id]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Schedule deleted']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Schedule not found or not authorized']);
            }
            exit;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Scheduled reports error: " . $e->getMessage());
    
    // Check if this was an AJAX request
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        header('Location: dashboard.php?page=scheduled_reports&error=' . urlencode($e->getMessage()));
    }
    exit;
}

/**
 * Calculate the next run time based on frequency
 */
function calculateNextRun($frequency, $schedule_day, $schedule_time) {
    $now = new DateTime();
    $time_parts = explode(':', $schedule_time);
    $hour = intval($time_parts[0] ?? 8);
    $minute = intval($time_parts[1] ?? 0);
    
    switch ($frequency) {
        case 'daily':
            $next = new DateTime();
            $next->setTime($hour, $minute, 0);
            if ($next <= $now) {
                $next->modify('+1 day');
            }
            break;
            
        case 'weekly':
            $next = new DateTime();
            $next->setTime($hour, $minute, 0);
            $target_day = $schedule_day ?? 1; // Default to Monday
            $current_day = (int)$next->format('N'); // 1=Monday, 7=Sunday
            
            if ($current_day < $target_day) {
                $next->modify('+' . ($target_day - $current_day) . ' days');
            } elseif ($current_day > $target_day || ($current_day == $target_day && $next <= $now)) {
                $next->modify('+' . (7 - $current_day + $target_day) . ' days');
            }
            break;
            
        case 'monthly':
            $next = new DateTime();
            $target_day = min($schedule_day ?? 1, 28); // Max 28 to avoid month-end issues
            $next->setDate((int)$next->format('Y'), (int)$next->format('m'), $target_day);
            $next->setTime($hour, $minute, 0);
            
            if ($next <= $now) {
                $next->modify('+1 month');
            }
            break;
            
        default:
            $next = new DateTime();
            $next->modify('+1 day');
            $next->setTime($hour, $minute, 0);
    }
    
    return $next->format('Y-m-d H:i:s');
}
?>
