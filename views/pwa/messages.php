<?php
/**
 * PWA Messages - Mobile-native conversation list
 * Purpose-built for mobile phones.
 */

$conversations = [];
try {
    $stmt = $pdo->prepare("
        SELECT c.id, c.last_message_at,
               CASE WHEN c.participant_one_id = ? THEN c.participant_two_id ELSE c.participant_one_id END as other_user_id,
               u.first_name, u.last_name, u.role,
               (SELECT m2.message_body FROM messages m2 WHERE m2.conversation_id = c.id ORDER BY m2.created_at DESC LIMIT 1) as last_message,
               (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.to_user_id = ? AND m.is_read = 0) as unread_count
        FROM conversations c
        JOIN users u ON u.id = CASE WHEN c.participant_one_id = ? THEN c.participant_two_id ELSE c.participant_one_id END
        WHERE c.participant_one_id = ? OR c.participant_two_id = ?
        ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id, $user_id, $user_id, $user_id, $user_id]);
    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $conversations = decryptUserRows($conversations);
    }
    foreach ($conversations as &$_c) {
        if (!empty($_c['last_message']) && class_exists('FieldEncryption')) {
            $_c['last_message'] = FieldEncryption::decrypt($_c['last_message']);
        }
    }
    unset($_c);
} catch (PDOException $e) { $conversations = []; }

if (!function_exists('mMsgTimeAgo')) {
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
}
?>
<style>
.m-messages { padding: 0; font-family: Inter, sans-serif; display: flex; flex-direction: column; }
.m-messages.m-chat-active {
    position: fixed; top: 0; left: 0; right: 0; bottom: 0;
    z-index: 99; margin: 0; padding: 0; width: 100%;
    background: var(--bg-main, #0A0A0F);
    display: flex; flex-direction: column;
}
.m-messages-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 16px 12px;
}
.m-messages-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-messages-count { font-size: 12px; color: #A8A8B8; }

/* Search bar */
.m-msg-search-wrap { padding: 0 16px 8px; }
.m-msg-search {
    width: 100%; box-sizing: border-box; padding: 10px 14px 10px 36px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; outline: none;
}
.m-msg-search::placeholder { color: #6B6B7B; }
.m-msg-search:focus { border-color: #6B46C1; }
.m-msg-search-wrap { position: relative; }
.m-msg-search-wrap i {
    position: absolute; left: 28px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 13px; pointer-events: none;
}

/* Unread/All toggle */
.m-msg-filters { display: flex; gap: 8px; padding: 0 16px 10px; }
.m-msg-filter-btn {
    padding: 6px 14px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: transparent; color: #A8A8B8; font-size: 12px; font-weight: 600;
    cursor: pointer; transition: all 0.2s;
}
.m-msg-filter-btn.active {
    background: rgba(107,70,193,0.2); border-color: #6B46C1; color: #8B5CF6;
}

.m-conv-list { padding: 0 16px 80px; }
.m-conv-card {
    display: flex; align-items: center; gap: 12px; position: relative;
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
.m-conv-subject {
    font-size: 14px; font-weight: 600; color: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; flex: 1;
}
.m-conv-time { font-size: 11px; color: #6B6B7B; flex-shrink: 0; margin-left: 8px; }
.m-conv-preview {
    font-size: 12px; color: #A8A8B8; margin-top: 3px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.m-conv-unread {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 10px; font-weight: 700;
    padding: 0 6px; flex-shrink: 0;
}

/* Delete button */
.m-conv-delete {
    position: absolute; top: 6px; right: 6px;
    width: 24px; height: 24px; border-radius: 50%; border: none;
    background: transparent; color: #6B6B7B; font-size: 11px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; opacity: 0; transition: opacity 0.2s, color 0.2s;
    z-index: 2;
}
.m-conv-card:hover .m-conv-delete,
.m-conv-delete:focus { opacity: 1; }
.m-conv-delete:active { color: #EF4444; }
@media (hover: none) {
    .m-conv-delete { opacity: 0.7; }
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

/* New conversation modal */
.m-new-msg-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 100; display: none; align-items: flex-end; justify-content: center;
}
.m-new-msg-overlay.m-modal-open { display: flex; }
.m-new-msg-panel {
    width: 100%; max-width: 480px; max-height: 85vh;
    background: #16161F; border-top-left-radius: 20px; border-top-right-radius: 20px;
    padding: 20px 16px 24px; animation: mSlideUp 0.25s ease-out;
    display: flex; flex-direction: column;
}
@keyframes mSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-new-msg-handle {
    width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-new-msg-title {
    font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px;
}
.m-new-msg-label {
    font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px;
}
.m-new-msg-recipient-wrap { position: relative; margin-bottom: 14px; }
.m-new-msg-recipient-input {
    width: 100%; box-sizing: border-box; padding: 10px 14px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; outline: none;
}
.m-new-msg-recipient-input:focus { border-color: #6B46C1; }
.m-new-msg-recipient-input::placeholder { color: #6B6B7B; }
.m-new-msg-dropdown {
    position: absolute; left: 0; right: 0; top: 100%; z-index: 10;
    background: #1E1E2A; border: 1px solid #2D2D3F; border-radius: 10px;
    max-height: 180px; overflow-y: auto; display: none; margin-top: 4px;
}
.m-new-msg-dropdown.m-dropdown-open { display: block; }
.m-new-msg-dd-item {
    padding: 10px 14px; cursor: pointer; color: #A8A8B8; font-size: 13px;
    display: flex; justify-content: space-between; align-items: center;
}
.m-new-msg-dd-item:hover, .m-new-msg-dd-item.m-dd-active {
    background: rgba(107,70,193,0.15); color: #fff;
}
.m-new-msg-dd-role {
    font-size: 10px; background: rgba(107,70,193,0.2); color: #8B5CF6;
    padding: 2px 6px; border-radius: 4px; text-transform: capitalize;
}
.m-new-msg-textarea {
    width: 100%; box-sizing: border-box; min-height: 100px; padding: 10px 14px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; resize: vertical; outline: none;
    font-family: Inter, sans-serif; margin-bottom: 14px;
}
.m-new-msg-textarea:focus { border-color: #6B46C1; }
.m-new-msg-textarea::placeholder { color: #6B6B7B; }
.m-new-msg-actions { display: flex; gap: 10px; }
.m-new-msg-cancel {
    flex: 1; padding: 12px; border-radius: 10px; border: 1px solid #2D2D3F;
    background: transparent; color: #A8A8B8; font-size: 14px; font-weight: 600;
    cursor: pointer;
}
.m-new-msg-send {
    flex: 2; padding: 12px; border-radius: 10px; border: none;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 14px; font-weight: 600; cursor: pointer;
}
.m-new-msg-send:disabled { opacity: 0.5; cursor: not-allowed; }
.m-new-msg-feedback {
    font-size: 12px; margin-top: 8px; text-align: center; min-height: 16px;
}
.m-new-msg-feedback.m-msg-ok { color: #10B981; }
.m-new-msg-feedback.m-msg-err { color: #EF4444; }

/* ---- Chat View ---- */
.m-messages.m-chat-active .m-messages-header,
.m-messages.m-chat-active .m-msg-search-wrap,
.m-messages.m-chat-active .m-msg-filters,
.m-messages.m-chat-active .m-conv-list,
.m-messages.m-chat-active .m-fab { display: none !important; }
.m-messages.m-chat-active .m-chat-view { display: flex !important; }

.m-chat-view {
    display: none; flex-direction: column;
    flex: 1; min-height: 0; width: 100%;
}
.m-chat-header {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 16px; background: #16161F;
    border-bottom: 1px solid #2D2D3F; flex-shrink: 0;
}
.m-chat-back {
    width: 36px; height: 36px; border-radius: 50%;
    background: transparent; border: 1px solid #2D2D3F;
    color: #fff; font-size: 14px; display: flex;
    align-items: center; justify-content: center; cursor: pointer;
    flex-shrink: 0;
}
.m-chat-back:active { background: rgba(107,70,193,0.2); }
.m-chat-header-info { flex: 1; min-width: 0; }
.m-chat-header-name { font-size: 15px; font-weight: 700; color: #fff; display: block; }
.m-chat-header-role { font-size: 11px; color: #A8A8B8; text-transform: capitalize; }

.m-chat-body {
    flex: 1; overflow-y: auto; padding: 12px 16px;
    display: flex; flex-direction: column; gap: 4px;
    -webkit-overflow-scrolling: touch;
    min-height: 0; width: 100%; box-sizing: border-box;
}
.m-chat-bubble-row { display: flex; max-width: 82%; margin-bottom: 2px; }
.m-chat-bubble-row.m-sent { align-self: flex-end; }
.m-chat-bubble-row.m-received { align-self: flex-start; }
.m-chat-bubble {
    padding: 9px 14px; border-radius: 18px;
    font-size: 14px; line-height: 1.45; word-wrap: break-word;
}
.m-chat-bubble-row.m-sent .m-chat-bubble {
    background: #6B46C1; color: #fff; border-bottom-right-radius: 4px;
}
.m-chat-bubble-row.m-received .m-chat-bubble {
    background: #1E1E2A; color: #E2E8F0; border-bottom-left-radius: 4px;
}
.m-chat-bubble-meta {
    font-size: 10px; color: #6B6B7B; margin-top: 2px;
    display: flex; align-items: center; gap: 4px;
}
.m-chat-bubble-row.m-sent .m-chat-bubble-meta { justify-content: flex-end; }
.m-chat-read-icon { font-size: 10px; }
.m-chat-read-icon.m-read { color: #60a5fa; }
.m-chat-read-icon.m-unread { color: #6B6B7B; }

.m-chat-date-divider {
    text-align: center; margin: 12px 0; position: relative;
}
.m-chat-date-divider span {
    background: #0A0A0F; padding: 0 10px;
    font-size: 11px; color: #6B6B7B; position: relative; z-index: 1;
}
.m-chat-date-divider::before {
    content: ''; position: absolute; top: 50%; left: 0; right: 0;
    height: 1px; background: #2D2D3F;
}

.m-chat-input-area {
    display: flex; gap: 8px; align-items: flex-end;
    padding: 10px 12px; background: #16161F;
    border-top: 1px solid #2D2D3F; flex-shrink: 0;
    padding-bottom: max(10px, env(safe-area-inset-bottom));
    width: 100%; box-sizing: border-box;
}
.m-chat-input {
    flex: 1; padding: 12px 16px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 20px;
    color: #fff; font-size: 15px; resize: none; outline: none;
    max-height: 120px; min-height: 44px; line-height: 1.4; font-family: Inter, sans-serif;
}
.m-chat-input:focus { border-color: #6B46C1; box-shadow: 0 0 0 2px rgba(107,70,193,0.2); }
.m-chat-input::placeholder { color: #6B6B7B; }
.m-chat-send-btn {
    width: 44px; height: 44px; min-width: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border: none; color: #fff; font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.m-chat-send-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.m-chat-send-btn:active:not(:disabled) { transform: scale(0.95); }

.m-chat-empty {
    flex: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: #6B6B7B; text-align: center; padding: 20px;
}
.m-chat-empty i { font-size: 28px; margin-bottom: 10px; color: #8B5CF6; opacity: 0.5; }
.m-chat-empty p { font-size: 13px; margin: 4px 0; }

/* Avatar initials */
.m-conv-icon-initials {
    width: 44px; height: 44px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #a78bfa);
    display: flex; align-items: center; justify-content: center;
    font-weight: 900; font-size: 15px; color: #fff; flex-shrink: 0;
    letter-spacing: 0.5px;
}
.m-chat-header .m-conv-icon-initials { width: 38px; height: 38px; font-size: 13px; }

/* Role badge colors */
.m-role-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600; text-transform: capitalize; }
.m-role-badge.m-role-coach { background: rgba(59,130,246,0.2); color: #60a5fa; }
.m-role-badge.m-role-athlete { background: rgba(16,185,129,0.2); color: #34d399; }
.m-role-badge.m-role-admin { background: rgba(239,68,68,0.2); color: #f87171; }
.m-role-badge.m-role-parent { background: rgba(251,191,36,0.2); color: #fbbf24; }
.m-role-badge.m-role-default { background: rgba(107,70,193,0.2); color: #8B5CF6; }

/* E2E Encryption badge */
.m-e2e-badge {
    display: flex; align-items: center; gap: 4px;
    font-size: 10px; color: #34d399; margin-left: auto;
    padding: 3px 7px; background: rgba(16,185,129,0.1); border-radius: 6px;
    flex-shrink: 0;
}
.m-e2e-badge i { font-size: 9px; }

/* Typing indicator */
.m-typing-indicator {
    padding: 4px 16px 2px; display: none; align-items: center; gap: 8px;
    color: #A8A8B8; font-size: 11px; background: #16161F; flex-shrink: 0;
}
.m-typing-dots { display: flex; gap: 3px; align-items: center; }
.m-typing-dots span {
    width: 5px; height: 5px; background: #6B46C1; border-radius: 50%;
    animation: mTypingBounce 1.4s infinite both;
}
.m-typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.m-typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes mTypingBounce {
    0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; }
    40% { transform: scale(1); opacity: 1; }
}

/* Toolbar buttons */
.m-chat-toolbar {
    display: flex; align-items: center; gap: 2px; flex-shrink: 0;
    flex-wrap: nowrap !important;
}
.m-toolbar-btn {
    width: 34px; height: 34px; display: flex; align-items: center; justify-content: center;
    background: none; border: none; color: #6B6B7B; cursor: pointer;
    border-radius: 50%; font-size: 15px; transition: color 0.2s, background 0.2s;
    position: relative; flex-shrink: 0;
}
.m-toolbar-btn:active { color: #8B5CF6; background: rgba(107,70,193,0.15); }

/* Emoji picker (mobile) */
.m-emoji-picker-wrap { position: relative; }
.m-emoji-picker {
    display: none; position: absolute; bottom: 42px; left: -4px;
    width: min(320px, calc(100vw - 40px)); max-height: 320px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.5); z-index: 100;
    overflow: hidden; flex-direction: column;
}
.m-emoji-picker.m-emoji-open { display: flex; }
.m-emoji-search-wrap { padding: 8px 10px; border-bottom: 1px solid #2D2D3F; }
.m-emoji-search-wrap input {
    width: 100%; padding: 7px 10px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 6px; color: #fff; font-size: 13px; outline: none; box-sizing: border-box;
}
.m-emoji-cats { display: flex; gap: 2px; padding: 6px 8px; border-bottom: 1px solid #2D2D3F; overflow-x: auto; }
.m-emoji-cat-btn {
    padding: 4px 6px; background: none; border: none; font-size: 16px;
    cursor: pointer; border-radius: 6px; transition: background 0.15s; flex-shrink: 0;
}
.m-emoji-cat-btn:active, .m-emoji-cat-btn.m-ecat-active { background: rgba(107,70,193,0.2); }
.m-emoji-grid {
    display: grid; grid-template-columns: repeat(7, 1fr); gap: 2px;
    padding: 8px; overflow-y: auto; flex: 1; max-height: 200px;
    -webkit-overflow-scrolling: touch;
}
.m-emoji-btn {
    display: flex; align-items: center; justify-content: center; padding: 5px;
    background: none; border: none; font-size: 20px; cursor: pointer;
    border-radius: 6px; line-height: 1;
}
.m-emoji-btn:active { background: rgba(107,70,193,0.2); }

/* Pending attachments bar */
.m-pending-attachments {
    display: flex; flex-wrap: wrap; gap: 6px;
    padding: 6px 16px; border-top: 1px solid #2D2D3F;
    background: #16161F; flex-shrink: 0;
    width: 100%; box-sizing: border-box;
}
.m-pending-attachments:empty { display: none; }
.m-pending-file {
    display: flex; align-items: center; gap: 5px;
    padding: 5px 8px; background: rgba(107,70,193,0.15);
    border-radius: 8px; font-size: 11px; color: #E2E8F0; max-width: 180px;
}
.m-pending-file span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.m-pending-file .m-remove-file {
    cursor: pointer; color: #f87171; font-size: 13px; margin-left: 2px; flex-shrink: 0;
}
.m-pending-file .m-remove-file:active { color: #ef4444; }

/* Message attachment display */
.m-msg-attachments { display: flex; flex-wrap: wrap; gap: 6px; margin-top: 6px; }
.m-msg-attach-img {
    max-width: 180px; max-height: 180px; border-radius: 8px; cursor: pointer;
    transition: opacity 0.15s;
}
.m-msg-attach-img:active { opacity: 0.8; }
.m-msg-attach-file {
    display: flex; align-items: center; gap: 5px; padding: 5px 8px;
    background: rgba(255,255,255,0.08); border-radius: 8px;
    font-size: 11px; color: #E2E8F0; text-decoration: none;
    max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.m-msg-attach-file:active { background: rgba(255,255,255,0.14); }
.m-msg-attach-file i { font-size: 13px; flex-shrink: 0; }

/* Contact list avatar in new message modal */
.m-new-msg-dd-item { gap: 10px; }
.m-contact-avatar {
    width: 32px; height: 32px; min-width: 32px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #a78bfa);
    display: flex; align-items: center; justify-content: center;
    font-weight: 800; font-size: 12px; color: #fff; flex-shrink: 0;
}
.m-contact-info { flex: 1; min-width: 0; }
.m-contact-info .m-contact-name { font-size: 13px; font-weight: 600; color: #fff; }
</style>

<div class="m-messages">
    <div class="m-messages-header">
        <h2 class="m-messages-title">Messages</h2>
        <span class="m-messages-count" id="mMsgCount"><?= count($conversations) ?> conversation<?= count($conversations) !== 1 ? 's' : '' ?></span>
    </div>

    <!-- Search bar -->
    <div class="m-msg-search-wrap">
        <i class="fas fa-search"></i>
        <input type="text" class="m-msg-search" id="mMsgSearch" placeholder="Search conversations…" autocomplete="off">
    </div>

    <!-- Unread/All toggle -->
    <div class="m-msg-filters">
        <button class="m-msg-filter-btn active" data-filter="all" onclick="mMsgFilterToggle('all')">All</button>
        <button class="m-msg-filter-btn" data-filter="unread" onclick="mMsgFilterToggle('unread')">Unread</button>
    </div>

    <div class="m-conv-list" id="mConvList">
        <?php if (empty($conversations)): ?>
            <div class="m-empty-state" id="mEmptyState">
                <i class="fas fa-comment-slash"></i>
                <p>No conversations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($conversations as $conv):
                $unread = (int)($conv['unread_count'] ?? 0);
                $preview = $conv['last_message'] ? mb_substr(strip_tags($conv['last_message']), 0, 80) : 'No messages yet';
                $name = htmlspecialchars(trim(($conv['first_name'] ?? '') . ' ' . ($conv['last_name'] ?? '')) ?: 'Unknown');
                $initials = htmlspecialchars(strtoupper(mb_substr($conv['first_name'] ?? '', 0, 1) . mb_substr($conv['last_name'] ?? '', 0, 1)));
            ?>
            <div class="m-conv-card" data-name="<?= strtolower($name) ?>" data-unread="<?= $unread ?>" data-id="<?= (int)$conv['id'] ?>" data-other-uid="<?= (int)$conv['other_user_id'] ?>" data-display-name="<?= $name ?>" data-role="<?= htmlspecialchars($conv['role'] ?? '') ?>" data-initials="<?= $initials ?>">
                <a href="javascript:void(0)" onclick="mOpenChat(<?= (int)$conv['id'] ?>)" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">
                    <div class="m-conv-icon-initials"><?= $initials ?></div>
                    <div class="m-conv-body">
                        <div class="m-conv-top">
                            <span class="m-conv-subject"><?= $name ?></span>
                            <span class="m-conv-time"><?= mMsgTimeAgo($conv['last_message_at']) ?></span>
                        </div>
                        <div class="m-conv-preview"><?= htmlspecialchars($preview) ?></div>
                    </div>
                    <?php if ($unread > 0): ?>
                    <span class="m-conv-unread"><?= $unread ?></span>
                    <?php endif; ?>
                </a>
                <button class="m-conv-delete" title="Delete conversation" onclick="mDeleteConv(event, <?= (int)$conv['id'] ?>)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- FAB opens new conversation modal -->
    <button class="m-fab" id="mNewMsgFab" type="button" aria-label="New message">
        <i class="fas fa-pen"></i>
    </button>

    <!-- Chat view (shown when a conversation is opened) -->
    <div class="m-chat-view" id="mChatView">
        <div class="m-chat-header">
            <button class="m-chat-back" type="button" id="mChatBack" aria-label="Back"><i class="fas fa-arrow-left"></i></button>
            <div class="m-conv-icon-initials" id="mChatAvatar"></div>
            <div class="m-chat-header-info">
                <span class="m-chat-header-name" id="mChatName"></span>
                <span class="m-chat-header-role" id="mChatRole"></span>
            </div>
            <div class="m-e2e-badge" title="End-to-end encrypted"><i class="fas fa-lock"></i> Encrypted</div>
        </div>
        <div class="m-chat-body" id="mChatBody"></div>
        <div class="m-typing-indicator" id="mTypingIndicator">
            <div class="m-typing-dots"><span></span><span></span><span></span></div>
            <span>typing…</span>
        </div>
        <div class="m-pending-attachments" id="mPendingAttachments"></div>
        <div class="m-chat-input-area">
            <div class="m-chat-toolbar">
                <div class="m-emoji-picker-wrap">
                    <button class="m-toolbar-btn" type="button" id="mEmojiBtn" aria-label="Emoji"><i class="far fa-face-smile"></i></button>
                    <div class="m-emoji-picker" id="mEmojiPicker">
                        <div class="m-emoji-search-wrap"><input type="text" id="mEmojiSearch" placeholder="Search emoji…"></div>
                        <div class="m-emoji-cats" id="mEmojiCats"></div>
                        <div class="m-emoji-grid" id="mEmojiGrid"></div>
                    </div>
                </div>
                <button class="m-toolbar-btn" type="button" id="mAttachBtn" aria-label="Attach file"><i class="fas fa-paperclip"></i></button>
                <input type="file" id="mFileInput" multiple accept="image/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.csv" style="display:none">
            </div>
            <textarea class="m-chat-input" id="mChatInput" rows="1" placeholder="Type a message…" maxlength="5000"></textarea>
            <button class="m-chat-send-btn" type="button" id="mChatSendBtn" disabled aria-label="Send"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
</div>

<!-- New conversation modal -->
<div class="m-new-msg-overlay" id="mNewMsgOverlay">
    <div class="m-new-msg-panel">
        <div class="m-new-msg-handle"></div>
        <h3 class="m-new-msg-title">New Message</h3>

        <div class="m-new-msg-label">To</div>
        <div class="m-new-msg-recipient-wrap">
            <input type="text" class="m-new-msg-recipient-input" id="mRecipientSearch" placeholder="Search for a recipient…" autocomplete="off">
            <div class="m-new-msg-dropdown" id="mRecipientDropdown"></div>
            <input type="hidden" id="mRecipientId" value="">
        </div>

        <div class="m-new-msg-label">Message</div>
        <textarea class="m-new-msg-textarea" id="mNewMsgBody" placeholder="Type your message…" maxlength="5000"></textarea>

        <?= csrfTokenInput() ?>

        <div class="m-new-msg-actions">
            <button class="m-new-msg-cancel" type="button" id="mNewMsgCancel">Cancel</button>
            <button class="m-new-msg-send" type="button" id="mNewMsgSend">Send</button>
        </div>
        <div class="m-new-msg-feedback" id="mNewMsgFeedback"></div>
    </div>
</div>

<script>
(function() {
    var csrfToken = (document.querySelector('.m-new-msg-panel input[name="csrf_token"]') || {}).value || '';
    var currentUserId = <?php echo json_encode($user_id); ?>;
    var currentFilter = 'all';
    var contacts = null;
    var selectedRecipientId = '';
    var pollTimer = null;
    var activeConvId = null;
    var activeOtherUserId = null;
    var chatPollTimer = null;
    var lastMessageCount = 0;
    var pendingFiles = [];
    var typingSendTimeout = null;
    var typingPollTimer = null;
    var MAX_ATTACHMENTS = 5;
    var MAX_FILE_SIZE = 25 * 1024 * 1024;

    var emojiData = {
        'Smileys': ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥴','😵','🤯','🥳','🥸','😎','🤓','🧐'],
        'Gestures': ['👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✌️','🤞','🤟','🤘','👌','🤌','🤏','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤙','💪','🦾','🖕'],
        'Hearts': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️','🫶','🥹'],
        'Sports': ['⚽','🏀','🏈','⚾','🎾','🏐','🏉','🎱','🏓','🏸','🏒','🥅','⛳','🏹','🎣','⛸️','🥊','🎿','⛷️','🏋️','🤸','🏊','🚴','🏃','🧗'],
        'Objects': ['📎','📁','📂','📄','📊','📈','📉','📝','✏️','📌','📍','🔗','📷','📸','🎥','📹','💻','📱','⌚','🔔','🔒','🔑','🏆','🎯','🎉','🎊','🎁'],
        'Nature': ['🌟','⭐','🌙','☀️','🌈','🔥','💧','❄️','🍀','🌸','🌻','🌺','🌲','🌊','🐺','🐻','🦅','🐾','🦁','🐯'],
        'Food': ['☕','🍕','🍔','🍟','🌭','🍿','🧁','🍩','🍪','🎂','🍫','🍬','🍭','🍎','🍊','🍋','🍌','🍉','🍇','🍓']
    };

    function getCsrf() { return csrfToken; }

    function getInitials(firstName, lastName) {
        return ((firstName || '').charAt(0) + (lastName || '').charAt(0)).toUpperCase();
    }

    function getRoleBadgeClass(role) {
        if (!role) return 'm-role-default';
        if (role === 'admin') return 'm-role-admin';
        if (role.indexOf('coach') !== -1) return 'm-role-coach';
        if (role === 'parent') return 'm-role-parent';
        if (role === 'athlete') return 'm-role-athlete';
        return 'm-role-default';
    }

    function getFileIcon(mimeType) {
        if (!mimeType) return 'fa-file';
        if (mimeType.indexOf('pdf') !== -1) return 'fa-file-pdf';
        if (mimeType.indexOf('word') !== -1 || mimeType.indexOf('document') !== -1) return 'fa-file-word';
        if (mimeType.indexOf('spreadsheet') !== -1 || mimeType.indexOf('excel') !== -1 || mimeType.indexOf('csv') !== -1) return 'fa-file-excel';
        if (mimeType.indexOf('text') !== -1) return 'fa-file-lines';
        return 'fa-file';
    }

    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return Math.round(bytes / 1024) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }

    /* ---- Search filter ---- */
    var searchInput = document.getElementById('mMsgSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() { applyFilters(); });
    }

    /* ---- Unread / All toggle ---- */
    window.mMsgFilterToggle = function(filter) {
        currentFilter = filter;
        document.querySelectorAll('.m-msg-filter-btn').forEach(function(b) {
            b.classList.toggle('active', b.getAttribute('data-filter') === filter);
        });
        applyFilters();
    };

    function applyFilters() {
        var q = (searchInput ? searchInput.value : '').toLowerCase().trim();
        var cards = document.querySelectorAll('.m-conv-card');
        var visible = 0;
        cards.forEach(function(card) {
            var name = card.getAttribute('data-name') || '';
            var unread = parseInt(card.getAttribute('data-unread') || '0', 10);
            var matchSearch = !q || name.indexOf(q) !== -1;
            var matchFilter = currentFilter === 'all' || unread > 0;
            var show = matchSearch && matchFilter;
            card.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        var empty = document.getElementById('mEmptyState');
        if (empty) empty.style.display = (visible === 0 && cards.length > 0) ? '' : 'none';
    }

    /* ---- Delete conversation ---- */
    window.mDeleteConv = async function(e, convId) {
        e.preventDefault();
        e.stopPropagation();
        if (!await showConfirmModal('Delete this conversation?')) return;
        var body = new URLSearchParams();
        body.set('action', 'delete_conversation');
        body.set('conversation_id', convId);
        body.set('csrf_token', getCsrf());
        fetch('process_messages.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var card = document.querySelector('.m-conv-card[data-id="' + convId + '"]');
                    if (card) card.remove();
                    updateCount();
                } else {
                    showToast(data.message || 'Failed to delete conversation', 'error');
                }
            })
            .catch(function() { showToast('Failed to delete conversation', 'error'); });
    };

    function updateCount() {
        var count = document.querySelectorAll('.m-conv-card').length;
        var el = document.getElementById('mMsgCount');
        if (el) el.textContent = count + ' conversation' + (count !== 1 ? 's' : '');
    }

    /* ---- Open Chat View ---- */
    window.mOpenChat = function(convId) {
        var card = document.querySelector('.m-conv-card[data-id="' + convId + '"]');
        if (!card) return;
        activeConvId = convId;
        activeOtherUserId = card.getAttribute('data-other-uid');
        var displayName = card.getAttribute('data-display-name') || 'Unknown';
        var role = (card.getAttribute('data-role') || '').replace(/_/g, ' ');
        var initials = card.getAttribute('data-initials') || displayName.charAt(0).toUpperCase();

        document.getElementById('mChatName').textContent = displayName;
        document.getElementById('mChatRole').textContent = role;
        document.getElementById('mChatAvatar').textContent = initials;
        document.getElementById('mChatBody').innerHTML = '<div class="m-chat-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>';
        document.getElementById('mChatInput').value = '';
        document.getElementById('mChatSendBtn').disabled = true;
        document.getElementById('mTypingIndicator').style.display = 'none';
        pendingFiles = [];
        renderPendingAttachments();
        lastMessageCount = 0;

        document.querySelector('.m-messages').classList.add('m-chat-active');
        /* Hide FAB + tab bar — FAB may have been moved out by the elevator script */
        var fabEl = document.getElementById('mNewMsgFab');
        if (fabEl) fabEl.style.display = 'none';
        var tabBar = document.querySelector('.pwa-tab-bar');
        if (tabBar) tabBar.style.display = 'none';
        var pwaHeader = document.querySelector('.pwa-header');
        if (pwaHeader) pwaHeader.style.display = 'none';
        loadChatMessages(convId, false);
        startChatPoll();
        startTypingPoll();
    };

    /* ---- Close Chat (back to list) ---- */
    window.mCloseChat = function() {
        activeConvId = null;
        activeOtherUserId = null;
        pendingFiles = [];
        renderPendingAttachments();
        document.querySelector('.m-messages').classList.remove('m-chat-active');
        /* Restore FAB + tab bar */
        var fabEl = document.getElementById('mNewMsgFab');
        if (fabEl) fabEl.style.display = '';
        var tabBar = document.querySelector('.pwa-tab-bar');
        if (tabBar) tabBar.style.display = '';
        var pwaHeader = document.querySelector('.pwa-header');
        if (pwaHeader) pwaHeader.style.display = '';
        stopChatPoll();
        stopTypingPoll();
        pollConversations();
    };

    document.getElementById('mChatBack').addEventListener('click', function() { mCloseChat(); });

    /* ---- Load & Render Messages ---- */
    function loadChatMessages(convId, silent) {
        fetch('process_messages.php?action=get_messages&conversation_id=' + convId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && convId === activeConvId) {
                    var msgs = data.messages || [];
                    if (!silent || msgs.length !== lastMessageCount) {
                        renderChatMessages(msgs);
                        lastMessageCount = msgs.length;
                    }
                    if (!silent) pollConversations();
                }
            })
            .catch(function() {
                if (!silent) {
                    document.getElementById('mChatBody').innerHTML = '<div class="m-chat-empty"><i class="fas fa-exclamation-triangle"></i><p>Failed to load messages</p></div>';
                }
            });
    }

    function renderChatMessages(messages) {
        var body = document.getElementById('mChatBody');
        if (!messages || messages.length === 0) {
            body.innerHTML = '<div class="m-chat-empty"><i class="fas fa-paper-plane"></i><p>No messages yet</p><p>Send a message to start the conversation.</p></div>';
            return;
        }
        var html = '';
        var lastDate = '';
        messages.forEach(function(msg) {
            var msgDate = new Date(msg.created_at.replace(/-/g, '/')).toLocaleDateString([], {timeZone: window.APP_TIMEZONE});
            if (msgDate !== lastDate) {
                html += '<div class="m-chat-date-divider"><span>' + formatDateDivider(msg.created_at) + '</span></div>';
                lastDate = msgDate;
            }
            var isSent = (msg.from_user_id == currentUserId);
            var readIcon = '';
            if (isSent) {
                readIcon = msg.is_read == 1
                    ? '<span class="m-chat-read-icon m-read" title="Read"><i class="fas fa-check-double"></i></span>'
                    : '<span class="m-chat-read-icon m-unread" title="Sent"><i class="fas fa-check"></i></span>';
            }
            var bodyText = (msg.message_body === '[Attachment]') ? '' : escHtml(msg.message_body);
            var attachHtml = renderAttachments(msg.attachments || []);
            html += '<div class="m-chat-bubble-row ' + (isSent ? 'm-sent' : 'm-received') + '">'
                + '<div><div class="m-chat-bubble">' + bodyText + attachHtml + '</div>'
                + '<div class="m-chat-bubble-meta">' + formatMsgTime(msg.created_at) + ' '
                + '<span class="m-chat-read-icon" title="Encrypted"><i class="fas fa-lock" style="font-size:8px;"></i></span> '
                + readIcon + '</div></div></div>';
        });
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
    }

    function renderAttachments(attachments) {
        if (!attachments || attachments.length === 0) return '';
        var html = '<div class="m-msg-attachments">';
        attachments.forEach(function(att) {
            var isImage = (att.mime_type || '').indexOf('image/') === 0;
            if (isImage) {
                var src = encodeURI(att.file_path);
                html += '<a href="' + src + '" target="_blank" rel="noopener noreferrer">'
                    + '<img class="m-msg-attach-img" src="' + src + '" alt="' + escHtml(att.filename || 'image') + '" loading="lazy">'
                    + '</a>';
            } else {
                var src = encodeURI(att.file_path);
                var icon = getFileIcon(att.mime_type);
                var sizeStr = att.file_size ? ' (' + formatFileSize(att.file_size) + ')' : '';
                html += '<a class="m-msg-attach-file" href="' + src + '" target="_blank" rel="noopener noreferrer" download="' + escHtml(att.filename || 'file') + '">'
                    + '<i class="fas ' + icon + '"></i>'
                    + '<span>' + escHtml(att.filename || 'file') + sizeStr + '</span></a>';
            }
        });
        html += '</div>';
        return html;
    }

    function formatDateDivider(dateStr) {
        var date = new Date(dateStr.replace(/-/g, '/'));
        var now = new Date();
        var diff = Math.floor((now - date) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        return date.toLocaleDateString(undefined, { timeZone: window.APP_TIMEZONE, weekday: 'short', month: 'short', day: 'numeric' });
    }

    function formatMsgTime(dateStr) {
        var date = new Date(dateStr.replace(/-/g, '/'));
        return date.toLocaleTimeString([], { timeZone: window.APP_TIMEZONE, hour: '2-digit', minute: '2-digit' });
    }

    /* ---- Send Message from Chat View ---- */
    var chatInput = document.getElementById('mChatInput');
    var chatSendBtn = document.getElementById('mChatSendBtn');

    if (chatInput) {
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            updateSendBtn();
            if (activeConvId) sendTypingStatus(activeConvId);
        });
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim() || pendingFiles.length > 0) sendChatMessage();
            }
        });
    }
    if (chatSendBtn) {
        chatSendBtn.addEventListener('click', function() { sendChatMessage(); });
    }

    function updateSendBtn() {
        chatSendBtn.disabled = !(chatInput.value.trim() || pendingFiles.length > 0);
    }

    function sendChatMessage() {
        var text = (chatInput.value || '').trim();
        if (!text && pendingFiles.length === 0) return;
        if (!activeOtherUserId) return;
        chatSendBtn.disabled = true;
        chatInput.value = '';
        chatInput.style.height = 'auto';

        var formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('to_user_id', activeOtherUserId);
        formData.append('message_body', text);
        formData.append('csrf_token', getCsrf());
        pendingFiles.forEach(function(file) {
            formData.append('attachments[]', file);
        });
        pendingFiles = [];
        renderPendingAttachments();

        fetch('process_messages.php', { method: 'POST', body: formData, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.conversation_id) activeConvId = data.conversation_id;
                    loadChatMessages(activeConvId, false);
                } else {
                    showToast(data.message || 'Failed to send', 'error');
                    chatInput.value = text;
                }
                updateSendBtn();
            })
            .catch(function() {
                showToast('Network error', 'error');
                chatInput.value = text;
                updateSendBtn();
            });
    }

    /* ---- Chat polling (10s) ---- */
    function startChatPoll() {
        stopChatPoll();
        chatPollTimer = setInterval(function() {
            if (activeConvId) loadChatMessages(activeConvId, true);
        }, 10000);
    }
    function stopChatPoll() {
        if (chatPollTimer) { clearInterval(chatPollTimer); chatPollTimer = null; }
    }

    /* ---- New message modal ---- */
    var overlay = document.getElementById('mNewMsgOverlay');
    var fab = document.getElementById('mNewMsgFab');
    var recipientSearch = document.getElementById('mRecipientSearch');
    var recipientDropdown = document.getElementById('mRecipientDropdown');
    var recipientIdInput = document.getElementById('mRecipientId');
    var msgBody = document.getElementById('mNewMsgBody');
    var sendBtn = document.getElementById('mNewMsgSend');
    var cancelBtn = document.getElementById('mNewMsgCancel');
    var feedback = document.getElementById('mNewMsgFeedback');

    if (fab) fab.addEventListener('click', function() { openNewMsgModal(); });
    if (cancelBtn) cancelBtn.addEventListener('click', function() { closeNewMsgModal(); });
    if (overlay) overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeNewMsgModal();
    });

    function openNewMsgModal() {
        if (overlay) overlay.classList.add('m-modal-open');
        recipientSearch.value = '';
        recipientIdInput.value = '';
        selectedRecipientId = '';
        msgBody.value = '';
        feedback.textContent = '';
        feedback.className = 'm-new-msg-feedback';
        sendBtn.disabled = false;
        loadContacts();
    }
    function closeNewMsgModal() {
        if (overlay) overlay.classList.remove('m-modal-open');
        if (recipientDropdown) recipientDropdown.classList.remove('m-dropdown-open');
    }

    function loadContacts() {
        if (contacts !== null) return;
        fetch('process_messages.php?action=get_contacts', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) contacts = data.contacts || [];
            })
            .catch(function() { contacts = []; });
    }

    if (recipientSearch) {
        recipientSearch.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            if (!contacts || q.length < 1) {
                recipientDropdown.classList.remove('m-dropdown-open');
                return;
            }
            var matches = contacts.filter(function(c) {
                var full = ((c.first_name || '') + ' ' + (c.last_name || '')).toLowerCase();
                return full.indexOf(q) !== -1;
            }).slice(0, 15);
            if (matches.length === 0) {
                recipientDropdown.classList.remove('m-dropdown-open');
                return;
            }
            recipientDropdown.innerHTML = matches.map(function(c) {
                var displayName = (c.first_name || '') + ' ' + (c.last_name || '');
                var role = (c.role || '').replace(/_/g, ' ');
                var ini = getInitials(c.first_name, c.last_name);
                var badgeCls = getRoleBadgeClass(c.role || '');
                return '<div class="m-new-msg-dd-item" data-uid="' + c.id + '">'
                    + '<div class="m-contact-avatar">' + escHtml(ini) + '</div>'
                    + '<div class="m-contact-info"><div class="m-contact-name">' + escHtml(displayName.trim()) + '</div></div>'
                    + '<span class="m-role-badge ' + badgeCls + '">' + escHtml(role) + '</span></div>';
            }).join('');
            recipientDropdown.classList.add('m-dropdown-open');
        });

        recipientSearch.addEventListener('blur', function() {
            setTimeout(function() { recipientDropdown.classList.remove('m-dropdown-open'); }, 200);
        });
    }

    if (recipientDropdown) {
        recipientDropdown.addEventListener('click', function(e) {
            var item = e.target.closest('.m-new-msg-dd-item');
            if (!item) return;
            var uid = item.getAttribute('data-uid');
            var name = item.querySelector('span').textContent;
            recipientSearch.value = name;
            recipientIdInput.value = uid;
            selectedRecipientId = uid;
            recipientDropdown.classList.remove('m-dropdown-open');
        });
    }

    if (sendBtn) {
        sendBtn.addEventListener('click', function() {
            var toId = recipientIdInput.value;
            var msg = (msgBody.value || '').trim();
            feedback.textContent = '';
            feedback.className = 'm-new-msg-feedback';
            if (!toId) { feedback.textContent = 'Please select a recipient'; feedback.className = 'm-new-msg-feedback m-msg-err'; return; }
            if (!msg) { feedback.textContent = 'Please enter a message'; feedback.className = 'm-new-msg-feedback m-msg-err'; return; }
            sendBtn.disabled = true;
            var body = new URLSearchParams();
            body.set('action', 'send_message');
            body.set('to_user_id', toId);
            body.set('message_body', msg);
            body.set('csrf_token', getCsrf());
            fetch('process_messages.php', { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        feedback.textContent = 'Message sent!';
                        feedback.className = 'm-new-msg-feedback m-msg-ok';
                        setTimeout(function() {
                            closeNewMsgModal();
                            if (data.conversation_id) {
                                activeConvId = data.conversation_id;
                                activeOtherUserId = toId;
                                document.getElementById('mChatName').textContent = recipientSearch.value;
                                document.getElementById('mChatRole').textContent = '';
                                var parts = recipientSearch.value.trim().split(/\s+/);
                                document.getElementById('mChatAvatar').textContent = getInitials(parts[0] || '', parts[1] || '');
                                document.getElementById('mChatBody').innerHTML = '';
                                document.getElementById('mTypingIndicator').style.display = 'none';
                                pendingFiles = [];
                                renderPendingAttachments();
                                document.querySelector('.m-messages').classList.add('m-chat-active');
                                /* Hide FAB + tab bar + header for full-screen chat */
                                var _fab = document.getElementById('mNewMsgFab');
                                if (_fab) _fab.style.display = 'none';
                                var _tab = document.querySelector('.pwa-tab-bar');
                                if (_tab) _tab.style.display = 'none';
                                var _hdr = document.querySelector('.pwa-header');
                                if (_hdr) _hdr.style.display = 'none';
                                loadChatMessages(data.conversation_id, false);
                                startChatPoll();
                                startTypingPoll();
                                pollConversations();
                            } else {
                                window.location.reload();
                            }
                        }, 600);
                    } else {
                        feedback.textContent = data.message || 'Failed to send';
                        feedback.className = 'm-new-msg-feedback m-msg-err';
                        sendBtn.disabled = false;
                    }
                })
                .catch(function() {
                    feedback.textContent = 'Network error';
                    feedback.className = 'm-new-msg-feedback m-msg-err';
                    sendBtn.disabled = false;
                });
        });
    }

    /* ---- Auto-refresh polling (15s) for conversation list ---- */
    function pollConversations() {
        fetch('process_messages.php?action=get_conversations', { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.conversations) return;
                refreshConvList(data.conversations);
            })
            .catch(function() { /* silent */ });
    }

    function refreshConvList(convs) {
        var list = document.getElementById('mConvList');
        if (!list) return;
        if (convs.length === 0) {
            list.innerHTML = '<div class="m-empty-state" id="mEmptyState"><i class="fas fa-comment-slash"></i><p>No conversations yet</p></div>';
            updateCount();
            return;
        }
        var html = '';
        convs.forEach(function(c) {
            var name = ((c.first_name || '') + ' ' + (c.last_name || '')).trim() || 'Unknown';
            var safeName = escHtml(name);
            var unread = parseInt(c.unread_count || 0, 10);
            var preview = c.last_message ? escHtml(c.last_message.substring(0, 80)) : 'No messages yet';
            var time = timeAgo(c.last_message_time || c.last_message_at || '');
            var cid = c.conversation_id || c.id;
            var otherUid = c.other_user_id || '';
            var role = c.role || '';
            var ini = getInitials(c.first_name, c.last_name);
            html += '<div class="m-conv-card" data-name="' + safeName.toLowerCase() + '" data-unread="' + unread + '" data-id="' + cid + '" data-other-uid="' + otherUid + '" data-display-name="' + safeName + '" data-role="' + escHtml(role) + '" data-initials="' + escHtml(ini) + '">'
                + '<a href="javascript:void(0)" onclick="mOpenChat(' + cid + ')" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">'
                + '<div class="m-conv-icon-initials">' + escHtml(ini) + '</div>'
                + '<div class="m-conv-body"><div class="m-conv-top"><span class="m-conv-subject">' + safeName + '</span>'
                + '<span class="m-conv-time">' + time + '</span></div>'
                + '<div class="m-conv-preview">' + preview + '</div></div>'
                + (unread > 0 ? '<span class="m-conv-unread">' + unread + '</span>' : '')
                + '</a>'
                + '<button class="m-conv-delete" title="Delete conversation" onclick="mDeleteConv(event,' + cid + ')"><i class="fas fa-times"></i></button>'
                + '</div>';
        });
        list.innerHTML = html;
        updateCount();
        applyFilters();
    }

    function timeAgo(dt) {
        if (!dt) return '';
        var ts = new Date(dt.replace(/-/g, '/')).getTime();
        var diff = Math.floor((Date.now() - ts) / 1000);
        if (diff < 60) return 'Now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd';
        var d = new Date(ts);
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate();
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.appendChild(document.createTextNode(s));
        return d.innerHTML;
    }

    /* ---- Emoji Picker ---- */
    function initEmojiPicker() {
        var catContainer = document.getElementById('mEmojiCats');
        var grid = document.getElementById('mEmojiGrid');
        var categories = Object.keys(emojiData);
        catContainer.innerHTML = categories.map(function(cat, i) {
            var icon = emojiData[cat][0];
            return '<button class="m-emoji-cat-btn' + (i === 0 ? ' m-ecat-active' : '') + '" data-cat="' + cat + '" title="' + cat + '">' + icon + '</button>';
        }).join('');
        catContainer.addEventListener('click', function(e) {
            var btn = e.target.closest('.m-emoji-cat-btn');
            if (!btn) return;
            catContainer.querySelectorAll('.m-emoji-cat-btn').forEach(function(b) { b.classList.remove('m-ecat-active'); });
            btn.classList.add('m-ecat-active');
            showEmojiCategory(btn.getAttribute('data-cat'));
        });
        showEmojiCategory(categories[0]);
        var searchInput = document.getElementById('mEmojiSearch');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                var q = this.value.toLowerCase();
                if (!q) { showEmojiCategory(categories[0]); return; }
                var results = [];
                for (var cat in emojiData) {
                    if (cat.toLowerCase().indexOf(q) !== -1) {
                        results = results.concat(emojiData[cat]);
                    }
                }
                if (results.length === 0) {
                    for (var cat2 in emojiData) { results = results.concat(emojiData[cat2]); }
                }
                renderEmojiGrid(results);
            });
        }
    }
    function showEmojiCategory(cat) { renderEmojiGrid(emojiData[cat] || []); }
    function renderEmojiGrid(emojis) {
        document.getElementById('mEmojiGrid').innerHTML = emojis.map(function(e) {
            return '<button class="m-emoji-btn" data-emoji="' + e + '" type="button">' + e + '</button>';
        }).join('');
    }
    document.getElementById('mEmojiBtn').addEventListener('click', function(e) {
        e.stopPropagation();
        var picker = document.getElementById('mEmojiPicker');
        picker.classList.toggle('m-emoji-open');
        if (picker.classList.contains('m-emoji-open')) {
            var s = document.getElementById('mEmojiSearch');
            if (s) s.focus();
        }
    });
    document.getElementById('mEmojiGrid').addEventListener('click', function(e) {
        var btn = e.target.closest('.m-emoji-btn');
        if (!btn) return;
        var emoji = btn.getAttribute('data-emoji');
        var input = document.getElementById('mChatInput');
        var start = input.selectionStart;
        var end = input.selectionEnd;
        var text = input.value;
        input.value = text.substring(0, start) + emoji + text.substring(end);
        input.selectionStart = input.selectionEnd = start + emoji.length;
        input.focus();
        updateSendBtn();
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.m-emoji-picker-wrap')) {
            document.getElementById('mEmojiPicker').classList.remove('m-emoji-open');
        }
    });
    initEmojiPicker();

    /* ---- File Attachment ---- */
    var attachBtn = document.getElementById('mAttachBtn');
    var fileInput = document.getElementById('mFileInput');
    if (attachBtn && fileInput) {
        attachBtn.addEventListener('click', function() { fileInput.click(); });
        fileInput.addEventListener('change', function() {
            var files = Array.prototype.slice.call(this.files);
            if (pendingFiles.length + files.length > MAX_ATTACHMENTS) {
                showToast('Maximum ' + MAX_ATTACHMENTS + ' attachments per message', 'error');
                this.value = '';
                return;
            }
            for (var i = 0; i < files.length; i++) {
                if (files[i].size > MAX_FILE_SIZE) {
                    showToast('File "' + files[i].name + '" exceeds 25MB limit', 'error');
                    continue;
                }
                pendingFiles.push(files[i]);
            }
            this.value = '';
            renderPendingAttachments();
            updateSendBtn();
        });
    }

    function renderPendingAttachments() {
        var container = document.getElementById('mPendingAttachments');
        if (!container) return;
        if (pendingFiles.length === 0) { container.innerHTML = ''; return; }
        container.innerHTML = pendingFiles.map(function(file, i) {
            var icon = file.type.indexOf('image/') === 0 ? 'fa-image' : 'fa-file';
            var sizeKB = Math.round(file.size / 1024);
            var sizeStr = sizeKB > 1024 ? (sizeKB / 1024).toFixed(1) + ' MB' : sizeKB + ' KB';
            return '<div class="m-pending-file">'
                + '<i class="fas ' + icon + '"></i>'
                + '<span>' + escHtml(file.name) + ' (' + sizeStr + ')</span>'
                + '<span class="m-remove-file" data-idx="' + i + '" title="Remove">&times;</span>'
                + '</div>';
        }).join('');
    }
    document.getElementById('mPendingAttachments').addEventListener('click', function(e) {
        var rm = e.target.closest('.m-remove-file');
        if (!rm) return;
        var idx = parseInt(rm.getAttribute('data-idx'), 10);
        pendingFiles.splice(idx, 1);
        renderPendingAttachments();
        updateSendBtn();
    });

    /* ---- Typing Indicator ---- */
    function sendTypingStatus(convId) {
        if (typingSendTimeout) return;
        typingSendTimeout = setTimeout(function() { typingSendTimeout = null; }, 2000);
        var fd = new FormData();
        fd.append('action', 'set_typing');
        fd.append('conversation_id', convId);
        fd.append('csrf_token', getCsrf());
        fetch('process_messages.php', { method: 'POST', body: fd, credentials: 'same-origin' }).catch(function() {});
    }
    function checkTypingStatus(convId) {
        fetch('process_messages.php?action=get_typing_status&conversation_id=' + convId, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var indicator = document.getElementById('mTypingIndicator');
                if (data.success && data.typing) {
                    indicator.style.display = 'flex';
                } else {
                    indicator.style.display = 'none';
                }
            })
            .catch(function() {});
    }
    function startTypingPoll() {
        stopTypingPoll();
        typingPollTimer = setInterval(function() {
            if (activeConvId) checkTypingStatus(activeConvId);
        }, 3000);
    }
    function stopTypingPoll() {
        if (typingPollTimer) { clearInterval(typingPollTimer); typingPollTimer = null; }
    }

    pollTimer = setInterval(pollConversations, 15000);

    /* Stop/resume polling on visibility change */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(pollTimer);
            stopChatPoll();
            stopTypingPoll();
        } else {
            pollConversations();
            pollTimer = setInterval(pollConversations, 15000);
            if (activeConvId) {
                loadChatMessages(activeConvId, true);
                startChatPoll();
                startTypingPoll();
            }
        }
    });

    /* Auto-open conversation from URL parameter */
    var urlParams = new URLSearchParams(window.location.search);
    var urlConvId = urlParams.get('conversation_id');
    if (urlConvId) {
        setTimeout(function() {
            var card = document.querySelector('.m-conv-card[data-id="' + urlConvId + '"]');
            if (card) mOpenChat(parseInt(urlConvId, 10));
        }, 100);
    }
})();
</script>
