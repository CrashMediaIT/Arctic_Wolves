<?php
/**
 * Message Processing API
 * Handles AJAX requests for the messaging system
 */

session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Require authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = intval($_SESSION['user_id']);
$user_role = $_SESSION['user_role'] ?? 'athlete';

define('MAX_MESSAGE_LENGTH', 5000);

header('Content-Type: application/json');

// Handle GET requests
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    switch ($action) {
        case 'get_conversations':
            getConversations($pdo, $user_id);
            break;
        case 'get_messages':
            $conversation_id = intval($_GET['conversation_id'] ?? 0);
            getMessages($pdo, $user_id, $conversation_id);
            break;
        case 'get_contacts':
            getContacts($pdo, $user_id, $user_role);
            break;
        case 'unread_count':
            getUnreadCount($pdo, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'send_message':
            sendMessage($pdo, $user_id);
            break;
        case 'mark_read':
            markRead($pdo, $user_id);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    exit;
}

/**
 * Get all conversations for a user
 */
function getConversations($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT c.id as conversation_id,
                   c.last_message_at,
                   CASE 
                       WHEN c.participant_one_id = ? THEN c.participant_two_id
                       ELSE c.participant_one_id
                   END as other_user_id,
                   u.first_name, u.last_name, u.role,
                   m.message_body as last_message,
                   m.from_user_id as last_message_from,
                   m.created_at as last_message_time,
                   (SELECT COUNT(*) FROM messages m2 
                    WHERE m2.conversation_id = c.id 
                    AND m2.to_user_id = ? 
                    AND m2.is_read = 0) as unread_count
            FROM conversations c
            JOIN users u ON u.id = CASE 
                WHEN c.participant_one_id = ? THEN c.participant_two_id
                ELSE c.participant_one_id
            END
            LEFT JOIN messages m ON m.id = (
                SELECT m3.id FROM messages m3 
                WHERE m3.conversation_id = c.id 
                ORDER BY m3.created_at DESC LIMIT 1
            )
            WHERE c.participant_one_id = ? OR c.participant_two_id = ?
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $conversations = decryptUserRows($conversations);
        // Decrypt encrypted message content (last_message is an alias for message_body)
        foreach ($conversations as &$conv) {
            if (!empty($conv['last_message'])) {
                $conv['last_message'] = FieldEncryption::decrypt($conv['last_message']);
            }
        }
        unset($conv);
        
        echo json_encode(['success' => true, 'conversations' => $conversations]);
    } catch (PDOException $e) {
        ErrorLogger::error("Messages - get conversations error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load conversations']);
    }
}

/**
 * Get messages in a conversation
 */
function getMessages($pdo, $user_id, $conversation_id) {
    try {
        // Verify user is a participant
        $check = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (participant_one_id = ? OR participant_two_id = ?)");
        $check->execute([$conversation_id, $user_id, $user_id]);
        if ($check->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        $stmt = $pdo->prepare("
            SELECT m.id, m.from_user_id, m.to_user_id, m.message_body, 
                   m.is_read, m.read_at, m.created_at,
                   u.first_name, u.last_name
            FROM messages m
            JOIN users u ON u.id = m.from_user_id
            WHERE m.conversation_id = ?
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$conversation_id]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $messages = decryptUserRows($messages);
        // Decrypt encrypted message content
        $messages = FieldEncryption::decryptRows($messages, FieldEncryption::MESSAGE_ENCRYPTED_FIELDS);
        
        // Mark messages as read
        $update = $pdo->prepare("
            UPDATE messages SET is_read = 1, read_at = NOW() 
            WHERE conversation_id = ? AND to_user_id = ? AND is_read = 0
        ");
        $update->execute([$conversation_id, $user_id]);
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    } catch (PDOException $e) {
        ErrorLogger::error("Messages - get messages error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load messages']);
    }
}

/**
 * Get available contacts for messaging
 * Athletes can message their assigned coaches
 * Coaches can message their assigned athletes
 * Admins can message anyone
 */
function getContacts($pdo, $user_id, $user_role) {
    try {
        $contacts = [];
        
        if ($user_role === 'admin') {
            // Admins can message all coaches and athletes
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, role, email
                FROM users 
                WHERE id != ? AND is_active = 1 AND role IN ('athlete', 'coach', 'health_coach', 'team_coach', 'admin')
                ORDER BY role, last_name, first_name
            ");
            $stmt->execute([$user_id]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $contacts = decryptUserRows($contacts);
        } elseif (in_array($user_role, ['coach', 'health_coach', 'team_coach'])) {
            // Coaches can message their assigned athletes and other coaches/admins
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, u.email
                FROM users u
                WHERE u.id != ? AND u.is_active = 1 AND (
                    u.id IN (SELECT athlete_id FROM managed_athletes WHERE coach_id = ?)
                    OR u.id IN (SELECT athlete_id FROM athlete_coaches WHERE coach_id = ? AND status = 'active')
                    OR u.assigned_coach_id = ?
                    OR u.role IN ('coach', 'health_coach', 'team_coach', 'admin')
                )
                ORDER BY u.role, u.last_name, u.first_name
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $contacts = decryptUserRows($contacts);
        } elseif ($user_role === 'athlete') {
            // Athletes can message their assigned coaches and their parents
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, u.email
                FROM users u
                WHERE u.id != ? AND u.is_active = 1 AND (
                    u.id IN (SELECT coach_id FROM managed_athletes WHERE athlete_id = ?)
                    OR u.id IN (SELECT coach_id FROM athlete_coaches WHERE athlete_id = ? AND status = 'active')
                    OR u.id = (SELECT assigned_coach_id FROM users WHERE id = ?)
                    OR u.id IN (SELECT parent_id FROM parent_athlete_relationships WHERE athlete_id = ?)
                )
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $contacts = decryptUserRows($contacts);
        } elseif ($user_role === 'parent') {
            // Parents can message their children and coaches assigned to their children
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.first_name, u.last_name, u.role, u.email
                FROM users u
                WHERE u.id != ? AND u.is_active = 1 AND (
                    u.id IN (SELECT athlete_id FROM parent_athlete_relationships WHERE parent_id = ?)
                    OR (u.role IN ('coach', 'health_coach', 'team_coach', 'admin') AND (
                        u.id IN (
                            SELECT DISTINCT ma.coach_id FROM managed_athletes ma
                            JOIN parent_athlete_relationships par ON ma.athlete_id = par.athlete_id
                            WHERE par.parent_id = ?
                        )
                        OR u.id IN (
                            SELECT DISTINCT ac.coach_id FROM athlete_coaches ac
                            JOIN parent_athlete_relationships par ON ac.athlete_id = par.athlete_id
                            WHERE par.parent_id = ? AND ac.status = 'active'
                        )
                        OR u.id IN (
                            SELECT DISTINCT child.assigned_coach_id FROM users child
                            JOIN parent_athlete_relationships par ON child.id = par.athlete_id
                            WHERE par.parent_id = ? AND child.assigned_coach_id IS NOT NULL
                        )
                    ))
                )
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
            $contacts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $contacts = decryptUserRows($contacts);
        }
        
        echo json_encode(['success' => true, 'contacts' => $contacts]);
    } catch (PDOException $e) {
        ErrorLogger::error("Messages - get contacts error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to load contacts']);
    }
}

/**
 * Send a message
 */
function sendMessage($pdo, $user_id) {
    $to_user_id = intval($_POST['to_user_id'] ?? 0);
    $message_body = trim($_POST['message_body'] ?? '');
    
    if (!$to_user_id || empty($message_body)) {
        echo json_encode(['success' => false, 'message' => 'Recipient and message are required']);
        return;
    }
    
    // Limit message length
    if (strlen($message_body) > MAX_MESSAGE_LENGTH) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max ' . MAX_MESSAGE_LENGTH . ' characters)']);
        return;
    }
    
    try {
        $pdo->beginTransaction();
        
        // Find or create conversation (always store smaller ID as participant_one)
        $p1 = min($user_id, $to_user_id);
        $p2 = max($user_id, $to_user_id);
        
        $conv = $pdo->prepare("SELECT id FROM conversations WHERE participant_one_id = ? AND participant_two_id = ?");
        $conv->execute([$p1, $p2]);
        $conversation = $conv->fetch();
        
        if ($conversation) {
            $conversation_id = $conversation['id'];
        } else {
            $insert_conv = $pdo->prepare("INSERT INTO conversations (participant_one_id, participant_two_id, last_message_at) VALUES (?, ?, NOW())");
            $insert_conv->execute([$p1, $p2]);
            $conversation_id = $pdo->lastInsertId();
        }
        
        // Insert message (encrypt message body for end-to-end encryption)
        $encrypted_body = FieldEncryption::encrypt($message_body);
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, from_user_id, to_user_id, message_body, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$conversation_id, $user_id, $to_user_id, $encrypted_body]);
        $message_id = $pdo->lastInsertId();
        
        // Update conversation last_message_at
        $update = $pdo->prepare("UPDATE conversations SET last_message_at = NOW() WHERE id = ?");
        $update->execute([$conversation_id]);
        
        $pdo->commit();
        
        Auditor::log($pdo, $user_id, 'create', 'messages', $message_id, ['action' => 'sent_message', 'to_user_id' => $to_user_id, 'conversation_id' => $conversation_id]);
        
        // Get sender info for response
        $sender = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $sender->execute([$user_id]);
        $sender_info = $sender->fetch();
        $sender_info = decryptUserRow($sender_info);
        
        echo json_encode([
            'success' => true, 
            'message_id' => $message_id,
            'conversation_id' => $conversation_id,
            'message' => [
                'id' => $message_id,
                'from_user_id' => $user_id,
                'to_user_id' => $to_user_id,
                'message_body' => $message_body,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'first_name' => $sender_info['first_name'],
                'last_name' => $sender_info['last_name']
            ]
        ]);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        ErrorLogger::error("Messages - send message error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to send message']);
    }
}

/**
 * Mark messages as read
 */
function markRead($pdo, $user_id) {
    $conversation_id = intval($_POST['conversation_id'] ?? 0);
    
    if (!$conversation_id) {
        echo json_encode(['success' => false, 'message' => 'Conversation ID required']);
        return;
    }
    
    try {
        // Verify user is a participant
        $check = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND (participant_one_id = ? OR participant_two_id = ?)");
        $check->execute([$conversation_id, $user_id, $user_id]);
        if ($check->rowCount() === 0) {
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        $stmt = $pdo->prepare("
            UPDATE messages SET is_read = 1, read_at = NOW() 
            WHERE conversation_id = ? AND to_user_id = ? AND is_read = 0
        ");
        $stmt->execute([$conversation_id, $user_id]);
        
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        ErrorLogger::error("Messages - mark read error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to mark as read']);
    }
}

/**
 * Get unread message count
 */
function getUnreadCount($pdo, $user_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM messages WHERE to_user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        $result = $stmt->fetch();
        echo json_encode(['success' => true, 'count' => intval($result['count'])]);
    } catch (PDOException $e) {
        ErrorLogger::error("Messages - unread count error: " . $e->getMessage());
        echo json_encode(['success' => true, 'count' => 0]);
    }
}
