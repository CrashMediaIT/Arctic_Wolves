<?php
/**
 * Process system notifications - Create, update, delete system notifications
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Set JSON header
header('Content-Type: application/json');

// Check if user is admin
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

try {
    // Verify CSRF token
    checkCsrfToken();
    
    $action = $_POST['action'] ?? '';
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'create':
            // Note: Schema uses start_date/end_date (TIMESTAMP columns)
            // Form sends start_time/end_time which MySQL will accept as TIMESTAMP values
            // MySQL automatically handles datetime string conversion to TIMESTAMP
            $stmt = $pdo->prepare("
                INSERT INTO system_notifications 
                (title, message, notification_type, start_date, end_date, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $end_date = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            $send_email = isset($_POST['send_email']) ? 1 : 0;
            
            $stmt->execute([
                $_POST['title'],
                $_POST['message'],
                $_POST['notification_type'],
                $_POST['start_time'], // MySQL TIMESTAMP handles datetime strings
                $end_date,
                $is_active,
                $user_id
            ]);
            
            $notification_id = $pdo->lastInsertId();
            Auditor::log($pdo, $user_id, 'create', 'system_notifications', $notification_id, ['action' => 'System notification created']);
            
            // Send email to all users if send_email is checked
            $emails_sent = 0;
            if ($send_email && $is_active) {
                try {
                    // Get all users with email notifications enabled in batches to prevent memory issues
                    $batch_size = 100;
                    $offset = 0;
                    
                    do {
                        $users_stmt = $pdo->prepare("
                            SELECT id, email, first_name 
                            FROM users 
                            WHERE email_notifications = 1 
                            AND email IS NOT NULL 
                            AND email != ''
                            LIMIT ? OFFSET ?
                        ");
                        $users_stmt->execute([$batch_size, $offset]);
                        $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($users as $user) {
                            $result = sendEmail($user['email'], 'system_notification', [
                                'name' => $user['first_name'] ?? 'User',
                                'title' => $_POST['title'],
                                'message' => $_POST['message'],
                                'notification_type' => $_POST['notification_type']
                            ]);
                            if ($result) {
                                $emails_sent++;
                            }
                        }
                        
                        $offset += $batch_size;
                    } while (count($users) === $batch_size);
                } catch (Exception $e) {
                    ErrorLogger::error("System notification email error: " . $e->getMessage());
                }
            }
            
            $message = 'Notification created successfully';
            if ($send_email && $is_active) {
                $message .= ". Emails sent: $emails_sent";
            }
            
            echo json_encode(['success' => true, 'message' => $message]);
            break;
            
        case 'update':
            // Note: Schema uses start_date/end_date (TIMESTAMP columns)
            // Form sends start_time/end_time which MySQL will accept as TIMESTAMP values
            $stmt = $pdo->prepare("
                UPDATE system_notifications 
                SET title = ?, message = ?, notification_type = ?, start_date = ?, 
                    end_date = ?, is_active = ?
                WHERE id = ?
            ");
            
            $end_date = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt->execute([
                $_POST['title'],
                $_POST['message'],
                $_POST['notification_type'],
                $_POST['start_time'], // MySQL TIMESTAMP handles datetime strings
                $end_date,
                $is_active,
                $_POST['id']
            ]);
            
            Auditor::log($pdo, $user_id, 'update', 'system_notifications', intval($_POST['id']), ['action' => 'System notification updated']);
            
            echo json_encode(['success' => true, 'message' => 'Notification updated successfully']);
            break;
            
        case 'toggle_active':
            $stmt = $pdo->prepare("UPDATE system_notifications SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            Auditor::log($pdo, $user_id, 'update', 'system_notifications', intval($_POST['id']), ['action' => 'System notification toggled']);
            
            echo json_encode(['success' => true, 'message' => 'Notification status toggled']);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM system_notifications WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            Auditor::log($pdo, $user_id, 'delete', 'system_notifications', intval($_POST['id']), ['action' => 'System notification deleted']);
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    ErrorLogger::error("System notifications error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    ErrorLogger::error("System notifications error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
