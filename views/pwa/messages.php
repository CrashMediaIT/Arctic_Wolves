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
.m-messages { padding: 0; font-family: Inter, sans-serif; }
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
    height: calc(100vh - 64px);
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
    display: flex; gap: 10px; align-items: flex-end;
    padding: 10px 16px; background: #16161F;
    border-top: 1px solid #2D2D3F; flex-shrink: 0;
    padding-bottom: max(10px, env(safe-area-inset-bottom));
}
.m-chat-input {
    flex: 1; padding: 10px 14px;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 20px;
    color: #fff; font-size: 14px; resize: none; outline: none;
    max-height: 100px; line-height: 1.4; font-family: Inter, sans-serif;
}
.m-chat-input:focus { border-color: #6B46C1; }
.m-chat-input::placeholder { color: #6B6B7B; }
.m-chat-send-btn {
    width: 40px; height: 40px; min-width: 40px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border: none; color: #fff; font-size: 15px; cursor: pointer;
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
            ?>
            <div class="m-conv-card" data-name="<?= strtolower($name) ?>" data-unread="<?= $unread ?>" data-id="<?= (int)$conv['id'] ?>" data-other-uid="<?= (int)$conv['other_user_id'] ?>" data-display-name="<?= $name ?>" data-role="<?= htmlspecialchars($conv['role'] ?? '') ?>">
                <a href="javascript:void(0)" onclick="mOpenChat(<?= (int)$conv['id'] ?>)" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">
                    <div class="m-conv-icon"><i class="fas fa-comment-dots"></i></div>
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
            <div class="m-conv-icon"><i class="fas fa-comment-dots"></i></div>
            <div class="m-chat-header-info">
                <span class="m-chat-header-name" id="mChatName"></span>
                <span class="m-chat-header-role" id="mChatRole"></span>
            </div>
        </div>
        <div class="m-chat-body" id="mChatBody"></div>
        <div class="m-chat-input-area">
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

    function getCsrf() { return csrfToken; }

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

        document.getElementById('mChatName').textContent = displayName;
        document.getElementById('mChatRole').textContent = role;
        document.getElementById('mChatBody').innerHTML = '<div class="m-chat-empty"><i class="fas fa-spinner fa-spin"></i><p>Loading…</p></div>';
        document.getElementById('mChatInput').value = '';
        document.getElementById('mChatSendBtn').disabled = true;
        lastMessageCount = 0;

        document.querySelector('.m-messages').classList.add('m-chat-active');
        loadChatMessages(convId, false);
        startChatPoll();
    };

    /* ---- Close Chat (back to list) ---- */
    window.mCloseChat = function() {
        activeConvId = null;
        activeOtherUserId = null;
        document.querySelector('.m-messages').classList.remove('m-chat-active');
        stopChatPoll();
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
            var msgDate = new Date(msg.created_at.replace(/-/g, '/')).toLocaleDateString();
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
            html += '<div class="m-chat-bubble-row ' + (isSent ? 'm-sent' : 'm-received') + '">'
                + '<div><div class="m-chat-bubble">' + escHtml(msg.message_body) + '</div>'
                + '<div class="m-chat-bubble-meta">' + formatMsgTime(msg.created_at) + ' ' + readIcon + '</div></div></div>';
        });
        body.innerHTML = html;
        body.scrollTop = body.scrollHeight;
    }

    function formatDateDivider(dateStr) {
        var date = new Date(dateStr.replace(/-/g, '/'));
        var now = new Date();
        var diff = Math.floor((now - date) / 86400000);
        if (diff === 0) return 'Today';
        if (diff === 1) return 'Yesterday';
        return date.toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' });
    }

    function formatMsgTime(dateStr) {
        var date = new Date(dateStr.replace(/-/g, '/'));
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    /* ---- Send Message from Chat View ---- */
    var chatInput = document.getElementById('mChatInput');
    var chatSendBtn = document.getElementById('mChatSendBtn');

    if (chatInput) {
        chatInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 100) + 'px';
            chatSendBtn.disabled = !this.value.trim();
        });
        chatInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (this.value.trim()) sendChatMessage();
            }
        });
    }
    if (chatSendBtn) {
        chatSendBtn.addEventListener('click', function() { sendChatMessage(); });
    }

    function sendChatMessage() {
        var text = (chatInput.value || '').trim();
        if (!text || !activeOtherUserId) return;
        chatSendBtn.disabled = true;
        chatInput.value = '';
        chatInput.style.height = 'auto';
        var body = new URLSearchParams();
        body.set('action', 'send_message');
        body.set('to_user_id', activeOtherUserId);
        body.set('message_body', text);
        body.set('csrf_token', getCsrf());
        fetch('process_messages.php', { method: 'POST', body: body, credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    if (data.conversation_id) activeConvId = data.conversation_id;
                    loadChatMessages(activeConvId, false);
                } else {
                    showToast(data.message || 'Failed to send', 'error');
                    chatInput.value = text;
                    chatSendBtn.disabled = false;
                }
            })
            .catch(function() {
                showToast('Network error', 'error');
                chatInput.value = text;
                chatSendBtn.disabled = false;
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
                return '<div class="m-new-msg-dd-item" data-uid="' + c.id + '">'
                    + '<span>' + escHtml(displayName.trim()) + '</span>'
                    + '<span class="m-new-msg-dd-role">' + escHtml(role) + '</span></div>';
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
                                document.getElementById('mChatBody').innerHTML = '';
                                document.querySelector('.m-messages').classList.add('m-chat-active');
                                loadChatMessages(data.conversation_id, false);
                                startChatPoll();
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
            html += '<div class="m-conv-card" data-name="' + safeName.toLowerCase() + '" data-unread="' + unread + '" data-id="' + cid + '" data-other-uid="' + otherUid + '" data-display-name="' + safeName + '" data-role="' + escHtml(role) + '">'
                + '<a href="javascript:void(0)" onclick="mOpenChat(' + cid + ')" style="display:flex;align-items:center;gap:12px;flex:1;min-width:0;text-decoration:none;color:inherit;">'
                + '<div class="m-conv-icon"><i class="fas fa-comment-dots"></i></div>'
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

    pollTimer = setInterval(pollConversations, 15000);

    /* Stop/resume polling on visibility change */
    document.addEventListener('visibilitychange', function() {
        if (document.hidden) {
            clearInterval(pollTimer);
            stopChatPoll();
        } else {
            pollConversations();
            pollTimer = setInterval(pollConversations, 15000);
            if (activeConvId) {
                loadChatMessages(activeConvId, true);
                startChatPoll();
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
