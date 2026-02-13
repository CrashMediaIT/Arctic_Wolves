<?php
/**
 * PWA Messages - Mobile-native conversation list
 * Purpose-built for mobile phones.
 */

$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.subject, c.updated_at,
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != ? AND m.read_at IS NULL) as unread_count,
               (SELECT m2.body FROM messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message
        FROM conversations c
        INNER JOIN conversation_participants cp ON cp.conversation_id = c.id
        WHERE cp.user_id = ?
        ORDER BY c.updated_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $conversations = []; }

function mMsgTimeAgo($datetime) {
    if (!$datetime) return '';
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Now';
    if ($diff < 3600) return floor($diff / 60) . 'm';
    if ($diff < 86400) return floor($diff / 3600) . 'h';
    if ($diff < 604800) return floor($diff / 86400) . 'd';
    return date('M j', $ts);
}
?>
<style>
.m-messages { padding: 0; font-family: Inter, sans-serif; }
.m-messages-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 16px 12px;
}
.m-messages-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-messages-count { font-size: 12px; color: #A8A8B8; }
.m-conv-list { padding: 0 16px 80px; }
.m-conv-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-conv-icon {
    width: 44px; height: 44px; border-radius: 50%;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: #8B5CF6; flex-shrink: 0;
}
.m-conv-body { flex: 1; min-width: 0; }
.m-conv-top { display: flex; justify-content: space-between; align-items: center; }
.m-conv-subject { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1; }
.m-conv-time { font-size: 11px; color: #6B6B7B; flex-shrink: 0; margin-left: 8px; }
.m-conv-preview { font-size: 12px; color: #A8A8B8; margin-top: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-conv-unread {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 10px; font-weight: 700;
    padding: 0 6px; flex-shrink: 0;
}
.m-fab {
    position: fixed; bottom: 80px; right: 20px;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border: none; color: #fff; font-size: 20px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    cursor: pointer; text-decoration: none; z-index: 20;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-messages">
    <div class="m-messages-header">
        <h2 class="m-messages-title">Messages</h2>
        <span class="m-messages-count"><?= count($conversations) ?> conversation<?= count($conversations) !== 1 ? 's' : '' ?></span>
    </div>

    <div class="m-conv-list">
        <?php if (empty($conversations)): ?>
            <div class="m-empty-state">
                <i class="fas fa-comment-slash"></i>
                <p>No conversations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($conversations as $conv):
                $unread = (int)($conv['unread_count'] ?? 0);
                $preview = $conv['last_message'] ? mb_substr(strip_tags($conv['last_message']), 0, 80) : 'No messages yet';
            ?>
            <a href="?page=messages&conversation_id=<?= (int)$conv['id'] ?>" class="m-conv-card">
                <div class="m-conv-icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="m-conv-body">
                    <div class="m-conv-top">
                        <span class="m-conv-subject"><?= htmlspecialchars($conv['subject'] ?? 'No Subject') ?></span>
                        <span class="m-conv-time"><?= mMsgTimeAgo($conv['updated_at']) ?></span>
                    </div>
                    <div class="m-conv-preview"><?= htmlspecialchars($preview) ?></div>
                </div>
                <?php if ($unread > 0): ?>
                <span class="m-conv-unread"><?= $unread ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <a href="?page=messages&action=new" class="m-fab">
        <i class="fas fa-pen"></i>
    </a>
</div>
