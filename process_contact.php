<?php
/**
 * Process Contact Messages
 * Handles sending messages between athletes/parents and coaches
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/mailer.php';

// Set security headers
setSecurityHeaders();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Validate CSRF token
checkCsrfToken();

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

try {
    switch ($action) {
        case 'send_message':
            $subject = trim($_POST['subject'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $priority = $_POST['priority'] ?? 'normal';
            $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
            
            // Validate required fields
            if (empty($subject) || empty($message)) {
                throw new Exception('Subject and message are required');
            }
            
            // Validate priority
            $valid_priorities = ['normal', 'high', 'urgent'];
            if (!in_array($priority, $valid_priorities)) {
                $priority = 'normal';
            }
            
            // Get sender info
            $sender_stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
            $sender_stmt->execute([$user_id]);
            $sender = $sender_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$sender) {
                throw new Exception('User not found');
            }
            
            $sender_name = $sender['first_name'] . ' ' . $sender['last_name'];
            
            // Determine recipient(s)
            $recipients = [];
            
            if ($recipient_id > 0) {
                // Specific recipient
                $rec_stmt = $pdo->prepare("SELECT id, first_name, last_name, email FROM users WHERE id = ?");
                $rec_stmt->execute([$recipient_id]);
                $rec = $rec_stmt->fetch(PDO::FETCH_ASSOC);
                if ($rec) {
                    $recipients[] = $rec;
                }
            } elseif ($user_role === 'athlete') {
                // Athlete sending to their coach - get assigned coaches
                $coach_stmt = $pdo->prepare("
                    SELECT u.id, u.first_name, u.last_name, u.email 
                    FROM users u
                    INNER JOIN managed_athletes ma ON u.id = ma.parent_id
                    WHERE ma.athlete_id = ? AND u.role IN ('coach', 'coach_plus', 'health_coach')
                ");
                $coach_stmt->execute([$user_id]);
                $recipients = $coach_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // If no assigned coach, get all active coaches
                if (empty($recipients)) {
                    $coach_stmt = $pdo->prepare("
                        SELECT id, first_name, last_name, email 
                        FROM users 
                        WHERE role IN ('coach', 'coach_plus', 'health_coach') 
                        AND is_active = 1 
                        LIMIT 5
                    ");
                    $coach_stmt->execute();
                    $recipients = $coach_stmt->fetchAll(PDO::FETCH_ASSOC);
                }
            } elseif ($user_role === 'parent') {
                // Parent sending to coach - get children's coaches
                $coach_stmt = $pdo->prepare("
                    SELECT DISTINCT u.id, u.first_name, u.last_name, u.email 
                    FROM users u
                    INNER JOIN managed_athletes ma ON u.id = ma.parent_id
                    INNER JOIN parent_athlete_relationships par ON ma.athlete_id = par.athlete_id
                    WHERE par.parent_id = ? AND u.role IN ('coach', 'coach_plus', 'health_coach')
                ");
                $coach_stmt->execute([$user_id]);
                $recipients = $coach_stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            if (empty($recipients)) {
                throw new Exception('No recipient found. Please contact support.');
            }
            
            // Store message in database (encrypt subject and message for end-to-end encryption)
            $encrypted_subject = FieldEncryption::encrypt($subject);
            $encrypted_message = FieldEncryption::encrypt($message);
            $msg_stmt = $pdo->prepare("
                INSERT INTO messages (sender_id, recipient_id, subject, message, priority, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            // Create notification for each recipient
            $notif_stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, type, title, message, read_status, created_at)
                VALUES (?, 'message', ?, ?, 0, NOW())
            ");
            
            $sent_count = 0;
            foreach ($recipients as $recipient) {
                // Store message (use encrypted values for database storage)
                $msg_stmt->execute([
                    $user_id,
                    $recipient['id'],
                    $encrypted_subject,
                    $encrypted_message,
                    $priority
                ]);
                
                // Create notification
                $notif_title = "New message from $sender_name";
                $notif_message = "Subject: $subject";
                $notif_stmt->execute([$recipient['id'], $notif_title, $notif_message]);
                
                // Send email notification
                try {
                    $email_data = [
                        'sender_name' => $sender_name,
                        'subject' => $subject,
                        'message' => $message,
                        'priority' => $priority,
                        'recipient_name' => $recipient['first_name'] . ' ' . $recipient['last_name']
                    ];
                    
                    sendEmail($recipient['email'], 'contact_message', $email_data);
                } catch (Exception $e) {
                    // Log but don't fail on email error
                    error_log("Failed to send email notification: " . $e->getMessage());
                }
                
                $sent_count++;
            }
            
            // Redirect back with success
            $redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php?page=home';
            $separator = strpos($redirect, '?') !== false ? '&' : '?';
            header("Location: $redirect{$separator}message_sent=$sent_count");
            exit;
            
        case 'mark_read':
            $message_id = intval($_POST['message_id'] ?? 0);
            
            if ($message_id > 0) {
                $stmt = $pdo->prepare("
                    UPDATE messages SET read_at = NOW() 
                    WHERE id = ? AND recipient_id = ? AND read_at IS NULL
                ");
                $stmt->execute([$message_id, $user_id]);
            }
            
            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            exit;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Contact form error: " . $e->getMessage());
    
    // Check if JSON response expected
    if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        $redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php?page=home';
        $separator = strpos($redirect, '?') !== false ? '&' : '?';
        header("Location: $redirect{$separator}error=" . urlencode($e->getMessage()));
    }
    exit;
}
