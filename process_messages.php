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
require_once __DIR__ . '/lib/file_upload_validator.php';
require_once __DIR__ . '/lib/rustfs_storage.php';

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
        case 'get_typing_status':
            $conversation_id = intval($_GET['conversation_id'] ?? 0);
            getTypingStatus($pdo, $user_id, $conversation_id);
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
        case 'set_typing':
            setTyping($pdo, $user_id);
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
        
        // Load attachments for all messages in this conversation
        $msg_ids = array_column($messages, 'id');
        $attachments_map = [];
        if (!empty($msg_ids)) {
            $placeholders = implode(',', array_fill(0, count($msg_ids), '?'));
            $att_stmt = $pdo->prepare("
                SELECT id, message_id, filename, file_path, file_size, mime_type
                FROM message_attachments
                WHERE message_id IN ($placeholders)
                ORDER BY id ASC
            ");
            $att_stmt->execute($msg_ids);
            while ($att = $att_stmt->fetch(PDO::FETCH_ASSOC)) {
                // Decrypt the stored filename
                $att['filename'] = FieldEncryption::decrypt($att['filename']);
                $attachments_map[$att['message_id']][] = $att;
            }
        }
        
        // Attach attachment data to each message
        foreach ($messages as &$msg) {
            $msg['attachments'] = $attachments_map[$msg['id']] ?? [];
        }
        unset($msg);
        
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
                    OR u.id IN (SELECT parent_id FROM managed_athletes WHERE athlete_id = ? AND parent_id IS NOT NULL)
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
                    u.id IN (SELECT athlete_id FROM managed_athletes WHERE parent_id = ?)
                    OR (u.role IN ('coach', 'health_coach', 'team_coach', 'admin') AND (
                        u.id IN (
                            SELECT DISTINCT ma2.coach_id FROM managed_athletes ma2
                            JOIN managed_athletes mp ON ma2.athlete_id = mp.athlete_id
                            WHERE mp.parent_id = ? AND ma2.coach_id IS NOT NULL
                        )
                        OR u.id IN (
                            SELECT DISTINCT ac.coach_id FROM athlete_coaches ac
                            JOIN managed_athletes mp ON ac.athlete_id = mp.athlete_id
                            WHERE mp.parent_id = ? AND ac.status = 'active'
                        )
                        OR u.id IN (
                            SELECT DISTINCT child.assigned_coach_id FROM users child
                            JOIN managed_athletes mp ON child.id = mp.athlete_id
                            WHERE mp.parent_id = ? AND child.assigned_coach_id IS NOT NULL
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
 * Send a message (with optional file attachments)
 */
function sendMessage($pdo, $user_id) {
    $to_user_id = intval($_POST['to_user_id'] ?? 0);
    $message_body = trim($_POST['message_body'] ?? '');
    
    // Allow empty message body if attachments are present
    $has_attachments = !empty($_FILES['attachments']['name'][0]);
    
    if (!$to_user_id || (empty($message_body) && !$has_attachments)) {
        echo json_encode(['success' => false, 'message' => 'Recipient and message (or attachment) are required']);
        return;
    }
    
    // Limit message length
    if (strlen($message_body) > MAX_MESSAGE_LENGTH) {
        echo json_encode(['success' => false, 'message' => 'Message is too long (max ' . MAX_MESSAGE_LENGTH . ' characters)']);
        return;
    }
    
    // Validate attachments before starting transaction
    $validated_files = [];
    if ($has_attachments) {
        $file_count = count($_FILES['attachments']['name']);
        if ($file_count > 5) {
            echo json_encode(['success' => false, 'message' => 'Maximum 5 attachments per message']);
            return;
        }
        for ($i = 0; $i < $file_count; $i++) {
            if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $file = [
                'name'     => $_FILES['attachments']['name'][$i],
                'type'     => $_FILES['attachments']['type'][$i],
                'tmp_name' => $_FILES['attachments']['tmp_name'][$i],
                'error'    => $_FILES['attachments']['error'][$i],
                'size'     => $_FILES['attachments']['size'][$i],
            ];
            // Validate: accept images and common document types
            $validation = FileUploadValidator::validate(
                $file,
                ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'csv'],
                25 * 1024 * 1024, // 25 MB per file
                null // skip mime check, rely on extension validation
            );
            if (!$validation['valid']) {
                echo json_encode(['success' => false, 'message' => 'Attachment "' . basename($file['name']) . '": ' . $validation['error']]);
                return;
            }
            $validated_files[] = $file;
        }
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
        
        // If only attachments and no text, set a placeholder body
        $display_body = $message_body;
        if (empty($message_body) && !empty($validated_files)) {
            $message_body = '[Attachment]';
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
        
        // Upload attachments outside transaction (storage operations)
        $attachment_results = [];
        if (!empty($validated_files)) {
            $rustfs_settings = getRustFSSettings($pdo);
            $rustfs_configured = isRustFSConfigured($rustfs_settings);
            
            foreach ($validated_files as $file) {
                $safe_filename = FileUploadValidator::sanitizeFilename($file['name']);
                $unique_name = FileUploadValidator::generateUniqueFilename($safe_filename);
                $object_key = 'Messages/attachments/' . $conversation_id . '/' . $unique_name;
                
                $file_path = $object_key; // default to object key
                if ($rustfs_configured) {
                    $upload_result = uploadToRustFS($rustfs_settings, $file['tmp_name'], $object_key);
                    if ($upload_result['success']) {
                        $file_path = $object_key;
                    } else {
                        ErrorLogger::error("Message attachment upload failed: " . ($upload_result['message'] ?? 'Unknown error'));
                        // Save locally as fallback
                        $local_dir = __DIR__ . '/uploads/messages/' . $conversation_id;
                        if (!is_dir($local_dir)) {
                            mkdir($local_dir, 0755, true);
                        }
                        $local_path = $local_dir . '/' . $unique_name;
                        move_uploaded_file($file['tmp_name'], $local_path);
                        $file_path = 'uploads/messages/' . $conversation_id . '/' . $unique_name;
                    }
                } else {
                    // No RustFS - save locally
                    $local_dir = __DIR__ . '/uploads/messages/' . $conversation_id;
                    if (!is_dir($local_dir)) {
                        mkdir($local_dir, 0755, true);
                    }
                    $local_path = $local_dir . '/' . $unique_name;
                    move_uploaded_file($file['tmp_name'], $local_path);
                    $file_path = 'uploads/messages/' . $conversation_id . '/' . $unique_name;
                }
                
                // Encrypt the original filename for E2E privacy
                $encrypted_filename = FieldEncryption::encrypt($safe_filename);
                
                // Detect MIME type from actual file content if still available
                $mime_type = $file['type'] ?: 'application/octet-stream';
                
                $att_stmt = $pdo->prepare("
                    INSERT INTO message_attachments (message_id, filename, file_path, file_size, mime_type)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $att_stmt->execute([$message_id, $encrypted_filename, $file_path, $file['size'], $mime_type]);
                
                $attachment_results[] = [
                    'id' => $pdo->lastInsertId(),
                    'filename' => $safe_filename,
                    'file_path' => $file_path,
                    'file_size' => $file['size'],
                    'mime_type' => $mime_type,
                ];
            }
        }
        
        Auditor::log($pdo, $user_id, 'create', 'messages', $message_id, [
            'action' => 'sent_message',
            'to_user_id' => $to_user_id,
            'conversation_id' => $conversation_id,
            'attachment_count' => count($attachment_results)
        ]);
        
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
                'message_body' => $display_body !== '' ? $display_body : $message_body,
                'is_read' => 0,
                'read_at' => null,
                'created_at' => date('Y-m-d H:i:s'),
                'first_name' => $sender_info['first_name'],
                'last_name' => $sender_info['last_name'],
                'attachments' => $attachment_results
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

/**
 * Set typing status for a conversation (uses a lightweight file-based approach)
 */
function setTyping($pdo, $user_id) {
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
        
        // Store typing status in a temp file (lightweight, no DB table needed)
        $typing_dir = __DIR__ . '/logs/typing';
        if (!is_dir($typing_dir)) {
            mkdir($typing_dir, 0755, true);
        }
        $typing_file = $typing_dir . '/conv_' . $conversation_id . '_user_' . $user_id . '.tmp';
        file_put_contents($typing_file, time());
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to set typing status']);
    }
}

/**
 * Get typing status for a conversation
 */
function getTypingStatus($pdo, $user_id, $conversation_id) {
    if (!$conversation_id) {
        echo json_encode(['success' => true, 'typing' => false]);
        return;
    }
    
    try {
        // Verify user is a participant and find the other user
        $check = $pdo->prepare("
            SELECT participant_one_id, participant_two_id FROM conversations 
            WHERE id = ? AND (participant_one_id = ? OR participant_two_id = ?)
        ");
        $check->execute([$conversation_id, $user_id, $user_id]);
        $conv = $check->fetch();
        if (!$conv) {
            echo json_encode(['success' => true, 'typing' => false]);
            return;
        }
        
        $other_user_id = ($conv['participant_one_id'] == $user_id) ? $conv['participant_two_id'] : $conv['participant_one_id'];
        
        $typing_file = __DIR__ . '/logs/typing/conv_' . $conversation_id . '_user_' . $other_user_id . '.tmp';
        $is_typing = false;
        if (file_exists($typing_file)) {
            $last_typed = intval(file_get_contents($typing_file));
            // Consider typing if within last 5 seconds
            if (time() - $last_typed < 5) {
                $is_typing = true;
            } else {
                // Clean up stale file
                @unlink($typing_file);
            }
        }
        
        echo json_encode(['success' => true, 'typing' => $is_typing]);
    } catch (Exception $e) {
        echo json_encode(['success' => true, 'typing' => false]);
    }
}
