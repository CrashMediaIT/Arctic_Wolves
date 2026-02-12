<?php
/**
 * API v1 - Message Endpoints
 * Provides messaging for ACWolvesAPP.
 *
 * Endpoints:
 *   GET  /v1/messages              - List messages
 *   GET  /v1/messages/{id}         - Get message details
 *   POST /v1/messages              - Send a message
 *   PUT  /v1/messages/{id}/read    - Mark message as read
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$message_id = $GLOBALS['api_resource_id'] ?? null;
$action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && !$message_id) {
    handleListMessages($auth);
} elseif ($method === 'GET' && $message_id && !$action) {
    handleGetMessage($auth, (int) $message_id);
} elseif ($method === 'POST' && !$message_id) {
    handleSendMessage($auth);
} elseif ($method === 'PUT' && $message_id && $action === 'read') {
    handleMarkRead($auth, (int) $message_id);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Message endpoint not found']);
}

/**
 * GET /v1/messages
 */
function handleListMessages($auth) {
    global $pdo;

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    try {
        $count_stmt = $pdo->prepare("
            SELECT COUNT(*) FROM messages WHERE from_user_id = ? OR to_user_id = ?
        ");
        $count_stmt->execute([$auth['user_id'], $auth['user_id']]);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT m.id, m.from_user_id, m.to_user_id, m.subject, m.message_body,
                   m.is_read, m.created_at,
                   sf.first_name AS sender_first_name, sf.last_name AS sender_last_name,
                   rt.first_name AS recipient_first_name, rt.last_name AS recipient_last_name
            FROM messages m
            LEFT JOIN users sf ON m.from_user_id = sf.id
            LEFT JOIN users rt ON m.to_user_id = rt.id
            WHERE m.from_user_id = ? OR m.to_user_id = ?
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$auth['user_id'], $auth['user_id'], $per_page, $offset]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($messages as &$msg) {
            $msg['sender_name'] = trim(
                FieldEncryption::decrypt($msg['sender_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($msg['sender_last_name'] ?? '')
            );
            $msg['recipient_name'] = trim(
                FieldEncryption::decrypt($msg['recipient_first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($msg['recipient_last_name'] ?? '')
            );
            unset($msg['sender_first_name'], $msg['sender_last_name']);
            unset($msg['recipient_first_name'], $msg['recipient_last_name']);
        }
        unset($msg);

        logApiAccess('list_messages', "Listed messages (page $page)", $auth['user_id']);
        paginatedResponse($messages, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API MESSAGES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/messages/{id}
 */
function handleGetMessage($auth, $message_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT m.*, 
                   sf.first_name AS sender_first_name, sf.last_name AS sender_last_name,
                   rt.first_name AS recipient_first_name, rt.last_name AS recipient_last_name
            FROM messages m
            LEFT JOIN users sf ON m.from_user_id = sf.id
            LEFT JOIN users rt ON m.to_user_id = rt.id
            WHERE m.id = ?
        ");
        $stmt->execute([$message_id]);
        $msg = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$msg) {
            apiResponse(404, ['success' => false, 'error' => 'Message not found']);
        }

        // Access control
        if ($msg['from_user_id'] != $auth['user_id'] && $msg['to_user_id'] != $auth['user_id'] && $auth['user_role'] !== 'admin') {
            apiResponse(403, ['success' => false, 'error' => 'Access denied']);
        }

        $msg['sender_name'] = trim(
            FieldEncryption::decrypt($msg['sender_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($msg['sender_last_name'] ?? '')
        );
        $msg['recipient_name'] = trim(
            FieldEncryption::decrypt($msg['recipient_first_name'] ?? '') . ' ' .
            FieldEncryption::decrypt($msg['recipient_last_name'] ?? '')
        );
        unset($msg['sender_first_name'], $msg['sender_last_name']);
        unset($msg['recipient_first_name'], $msg['recipient_last_name']);

        // Mark as read if recipient is viewing
        if ($msg['to_user_id'] == $auth['user_id'] && !$msg['is_read']) {
            $update = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ?");
            $update->execute([$message_id]);
        }

        logApiAccess('get_message', "Viewed message ID: $message_id", $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $msg]);
    } catch (PDOException $e) {
        error_log('[API MESSAGES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * POST /v1/messages
 */
function handleSendMessage($auth) {
    global $pdo;

    $body = getJsonBody();
    $to_user_id = (int) ($body['recipientId'] ?? $body['to_user_id'] ?? 0);
    $subject = trim($body['subject'] ?? '');
    $message_body = trim($body['body'] ?? $body['message_body'] ?? '');

    if (!$to_user_id || empty($message_body)) {
        apiResponse(400, ['success' => false, 'error' => 'recipientId and body are required']);
    }

    try {
        // Verify recipient exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND is_active = 1");
        $stmt->execute([$to_user_id]);
        if (!$stmt->fetch()) {
            apiResponse(404, ['success' => false, 'error' => 'Recipient not found']);
        }

        $stmt = $pdo->prepare("
            INSERT INTO messages (from_user_id, to_user_id, subject, message_body)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$auth['user_id'], $to_user_id, $subject, $message_body]);
        $new_id = (int) $pdo->lastInsertId();

        logApiAccess('send_message', "Sent message ID: $new_id to user $to_user_id", $auth['user_id']);
        apiResponse(201, [
            'success' => true,
            'message' => 'Message sent successfully',
            'data' => ['id' => $new_id],
        ]);
    } catch (PDOException $e) {
        error_log('[API MESSAGES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * PUT /v1/messages/{id}/read
 */
function handleMarkRead($auth, $message_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE messages SET is_read = 1, read_at = NOW() WHERE id = ? AND to_user_id = ?");
        $stmt->execute([$message_id, $auth['user_id']]);

        if ($stmt->rowCount() === 0) {
            apiResponse(404, ['success' => false, 'error' => 'Message not found']);
        }

        apiResponse(200, ['success' => true, 'message' => 'Message marked as read']);
    } catch (PDOException $e) {
        error_log('[API MESSAGES ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
