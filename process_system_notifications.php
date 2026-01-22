<?php
/**
 * Process system notifications - Create, update, delete system notifications
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

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
            // Note: Schema uses start_date/end_date, not start_time/end_time
            // Schema doesn't have send_email column
            $stmt = $pdo->prepare("
                INSERT INTO system_notifications 
                (title, message, notification_type, start_date, end_date, is_active, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $end_date = !empty($_POST['end_time']) ? $_POST['end_time'] : null;
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            $stmt->execute([
                $_POST['title'],
                $_POST['message'],
                $_POST['notification_type'],
                $_POST['start_time'], // Will be converted to start_date
                $end_date,
                $is_active,
                $user_id
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Notification created successfully']);
            break;
            
        case 'update':
            // Note: Schema uses start_date/end_date, not start_time/end_time
            // Schema doesn't have send_email or updated_at columns
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
                $_POST['start_time'],
                $end_date,
                $is_active,
                $_POST['id']
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Notification updated successfully']);
            break;
            
        case 'toggle_active':
            $stmt = $pdo->prepare("UPDATE system_notifications SET is_active = NOT is_active WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Notification status toggled']);
            break;
            
        case 'delete':
            $stmt = $pdo->prepare("DELETE FROM system_notifications WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            echo json_encode(['success' => true, 'message' => 'Notification deleted successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("System notifications error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("System notifications error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
