<?php
/**
 * Messages View
 * Facebook Messenger-style messaging between athletes and coaches
 */

require_once __DIR__ . '/../security.php';

$csrf_token = $_SESSION['csrf_token'] ?? generateCSRFToken();

// Check if starting a conversation with a specific user
$start_with_user = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
?>

<style>
    .messages-container {
        display: flex;
        height: calc(100vh - 120px);
        background: var(--bg, #0a0a0f);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border, #2D2D3F);
    }
    
    /* Sidebar - Conversation List */
    .msg-sidebar {
        width: 320px;
        min-width: 320px;
        border-right: 1px solid var(--border, #2D2D3F);
        display: flex;
        flex-direction: column;
        background: var(--card-bg, #16161F);
    }
    .msg-sidebar-header {
        padding: 20px;
        border-bottom: 1px solid var(--border, #2D2D3F);
    }
    .msg-sidebar-header h2 {
        margin: 0 0 12px 0;
        font-size: 20px;
        font-weight: 900;
        color: #fff;
    }
    .msg-search {
        width: 100%;
        padding: 10px 14px;
        background: var(--bg, #0a0a0f);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s;
        box-sizing: border-box;
    }
    .msg-search:focus {
        border-color: var(--primary, #6B46C1);
    }
    .msg-new-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 12px 16px;
        background: var(--primary, #6B46C1);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        margin-top: 10px;
        transition: background 0.2s;
    }
    .msg-new-btn:hover {
        background: var(--primary-hover, #7C3AED);
    }
    
    .conversation-list {
        flex: 1;
        overflow-y: auto;
    }
    .conversation-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 20px;
        cursor: pointer;
        border-bottom: 1px solid var(--border, #1e293b);
        transition: background 0.15s;
    }
    .conversation-item:hover {
        background: rgba(107, 70, 193, 0.1);
    }
    .conversation-item.active {
        background: rgba(107, 70, 193, 0.15);
        border-left: 3px solid var(--primary, #6B46C1);
    }
    .conversation-item.unread {
        background: rgba(107, 70, 193, 0.08);
    }
    .conv-avatar {
        width: 44px;
        height: 44px;
        min-width: 44px;
        background: linear-gradient(135deg, var(--primary, #6B46C1), #a78bfa);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 16px;
        color: #fff;
    }
    .conv-info {
        flex: 1;
        min-width: 0;
    }
    .conv-name {
        font-weight: 700;
        font-size: 14px;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .conv-role-badge {
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
    }
    .conv-role-badge.coach { background: rgba(59, 130, 246, 0.2); color: #60a5fa; }
    .conv-role-badge.athlete { background: rgba(16, 185, 129, 0.2); color: #34d399; }
    .conv-role-badge.admin { background: rgba(239, 68, 68, 0.2); color: #f87171; }
    .conv-role-badge.parent { background: rgba(251, 191, 36, 0.2); color: #fbbf24; }
    .conv-preview {
        font-size: 13px;
        color: #8b949e;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        margin-top: 3px;
    }
    .conv-meta {
        text-align: right;
        min-width: 50px;
    }
    .conv-time {
        font-size: 11px;
        color: #8b949e;
    }
    .conv-unread-badge {
        display: inline-block;
        background: var(--primary, #6B46C1);
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        min-width: 20px;
        height: 20px;
        line-height: 20px;
        text-align: center;
        border-radius: 10px;
        padding: 0 6px;
        margin-top: 4px;
    }
    
    /* Main Chat Area */
    .msg-main {
        flex: 1;
        display: flex;
        flex-direction: column;
        background: var(--bg, #0a0a0f);
    }
    .msg-main-header {
        padding: 16px 24px;
        border-bottom: 1px solid var(--border, #2D2D3F);
        display: flex;
        align-items: center;
        gap: 12px;
        background: var(--card-bg, #16161F);
    }
    .msg-main-header .conv-avatar {
        width: 38px;
        height: 38px;
        min-width: 38px;
        font-size: 14px;
    }
    .msg-main-header .header-info h3 {
        margin: 0;
        font-size: 16px;
        font-weight: 700;
        color: #fff;
    }
    .msg-main-header .header-info p {
        margin: 2px 0 0;
        font-size: 12px;
        color: #8b949e;
        text-transform: capitalize;
    }
    
    .msg-body {
        flex: 1;
        overflow-y: auto;
        padding: 20px 24px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .msg-empty-state {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        color: #8b949e;
        text-align: center;
        padding: 40px;
    }
    .msg-empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        color: var(--primary, #6B46C1);
        opacity: 0.5;
    }
    .msg-empty-state h3 {
        color: #fff;
        margin: 0 0 8px;
    }
    
    .msg-bubble-row {
        display: flex;
        max-width: 70%;
    }
    .msg-bubble-row.sent {
        align-self: flex-end;
    }
    .msg-bubble-row.received {
        align-self: flex-start;
    }
    .msg-bubble {
        padding: 10px 16px;
        border-radius: 18px;
        font-size: 14px;
        line-height: 1.5;
        word-wrap: break-word;
        position: relative;
    }
    .msg-bubble-row.sent .msg-bubble {
        background: var(--primary, #6B46C1);
        color: #fff;
        border-bottom-right-radius: 4px;
    }
    .msg-bubble-row.received .msg-bubble {
        background: var(--card-bg, #1e293b);
        color: #e2e8f0;
        border-bottom-left-radius: 4px;
    }
    .msg-bubble-time {
        font-size: 11px;
        color: #8b949e;
        margin-top: 4px;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .msg-bubble-row.sent .msg-bubble-time {
        justify-content: flex-end;
    }
    .msg-read-receipt {
        font-size: 11px;
        color: #60a5fa;
    }
    .msg-read-receipt.unread {
        color: #8b949e;
    }
    
    .msg-date-divider {
        text-align: center;
        margin: 16px 0;
        position: relative;
    }
    .msg-date-divider span {
        background: var(--bg, #0a0a0f);
        padding: 0 12px;
        font-size: 12px;
        color: #8b949e;
        position: relative;
        z-index: 1;
    }
    .msg-date-divider::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 0;
        right: 0;
        height: 1px;
        background: var(--border, #2D2D3F);
    }
    
    /* Input Area */
    .msg-input-area {
        padding: 16px 24px;
        border-top: 1px solid var(--border, #2D2D3F);
        background: var(--card-bg, #16161F);
        display: flex;
        gap: 12px;
        align-items: flex-end;
    }
    .msg-input {
        flex: 1;
        padding: 12px 16px;
        background: var(--bg, #0a0a0f);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: 24px;
        color: #fff;
        font-size: 14px;
        resize: none;
        outline: none;
        max-height: 120px;
        line-height: 1.4;
        font-family: inherit;
        transition: border-color 0.2s;
    }
    .msg-input:focus {
        border-color: var(--primary, #6B46C1);
    }
    .msg-send-btn {
        width: 44px;
        height: 44px;
        min-width: 44px;
        background: var(--primary, #6B46C1);
        color: #fff;
        border: none;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        transition: background 0.2s, transform 0.1s;
    }
    .msg-send-btn:hover {
        background: var(--primary-hover, #7C3AED);
        transform: scale(1.05);
    }
    .msg-send-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        transform: none;
    }
    
    /* New Conversation Modal */
    .msg-modal-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0,0,0,0.6);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    .msg-modal-overlay.show {
        display: flex;
    }
    .msg-modal {
        background: var(--card-bg, #16161F);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: 12px;
        width: 420px;
        max-height: 500px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .msg-modal-header {
        padding: 20px;
        border-bottom: 1px solid var(--border, #2D2D3F);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .msg-modal-header h3 {
        margin: 0;
        color: #fff;
        font-size: 18px;
    }
    .msg-modal-close {
        background: none;
        border: none;
        color: #8b949e;
        font-size: 20px;
        cursor: pointer;
        padding: 4px 8px;
    }
    .msg-modal-close:hover {
        color: #fff;
    }
    .msg-modal-body {
        padding: 16px 20px;
        overflow-y: auto;
        flex: 1;
    }
    .msg-contact-search {
        width: 100%;
        padding: 10px 14px;
        background: var(--bg, #0a0a0f);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
        outline: none;
        margin-bottom: 12px;
        box-sizing: border-box;
    }
    .msg-contact-list {
        display: flex;
        flex-direction: column;
        gap: 4px;
    }
    .msg-contact-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s;
    }
    .msg-contact-item:hover {
        background: rgba(107, 70, 193, 0.15);
    }
    .msg-contact-item .conv-avatar {
        width: 36px;
        height: 36px;
        min-width: 36px;
        font-size: 13px;
    }
    .msg-contact-name {
        font-weight: 600;
        font-size: 14px;
        color: #fff;
    }
    .msg-contact-role {
        font-size: 12px;
        color: #8b949e;
        text-transform: capitalize;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .messages-container {
            flex-direction: column;
            height: calc(100vh - 80px);
        }
        .msg-sidebar {
            width: 100%;
            min-width: 100%;
            max-height: 40%;
        }
        .msg-sidebar.hidden {
            display: none;
        }
        .msg-main.hidden {
            display: none;
        }
        .msg-back-btn {
            display: inline-flex !important;
        }
        .msg-bubble-row {
            max-width: 85%;
        }
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-comments"></i> Messages
    </h1>
    <p class="page-description">Send and receive messages with your coaches and athletes.</p>
</div>

<div class="messages-container" id="messagesContainer">
    <!-- Sidebar -->
    <div class="msg-sidebar" id="msgSidebar">
        <div class="msg-sidebar-header">
            <h2><i class="fas fa-inbox"></i> Conversations</h2>
            <input type="text" class="msg-search" id="convSearch" placeholder="Search conversations...">
            <button class="msg-new-btn" onclick="openNewConversation()">
                <i class="fas fa-pen-to-square"></i> New Message
            </button>
        </div>
        <div class="conversation-list" id="conversationList">
            <div class="msg-empty-state" style="padding: 30px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 24px;"></i>
                <p>Loading conversations...</p>
            </div>
        </div>
    </div>
    
    <!-- Main Chat -->
    <div class="msg-main" id="msgMain">
        <div class="msg-empty-state" id="noChatSelected">
            <i class="fas fa-comments"></i>
            <h3>Select a Conversation</h3>
            <p>Choose a conversation from the list or start a new one.</p>
        </div>
        
        <div id="chatView" style="display: none; flex-direction: column; height: 100%;">
            <div class="msg-main-header" id="chatHeader">
                <button class="msg-modal-close msg-back-btn" onclick="backToList()" style="display:none;" title="Back">
                    <i class="fas fa-arrow-left"></i>
                </button>
                <div class="conv-avatar" id="chatAvatar"></div>
                <div class="header-info">
                    <h3 id="chatName"></h3>
                    <p id="chatRole"></p>
                </div>
            </div>
            <div class="msg-body" id="chatBody"></div>
            <div class="msg-input-area">
                <textarea class="msg-input" id="msgInput" rows="1" placeholder="Type a message..." maxlength="5000" aria-label="Message input"></textarea>
                <button class="msg-send-btn" id="sendBtn" onclick="sendMessage()" disabled title="Send" aria-label="Send message">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- New Conversation Modal -->
<div class="msg-modal-overlay" id="newConvModal">
    <div class="msg-modal">
        <div class="msg-modal-header">
            <h3>New Message</h3>
            <button class="msg-modal-close" onclick="closeNewConversation()">&times;</button>
        </div>
        <div class="msg-modal-body">
            <input type="text" class="msg-contact-search" id="contactSearch" placeholder="Search contacts...">
            <div class="msg-contact-list" id="contactList">
                <div style="text-align: center; padding: 20px; color: #8b949e;">
                    <i class="fas fa-spinner fa-spin"></i> Loading contacts...
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const currentUserId = <?php echo json_encode($user_id); ?>;
const csrfToken = <?php echo json_encode($csrf_token); ?>;
let activeConversationId = null;
let conversations = [];
let contacts = [];
let pollInterval = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    loadConversations();
    
    // Auto-resize textarea
    const input = document.getElementById('msgInput');
    input.addEventListener('input', function() {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
        document.getElementById('sendBtn').disabled = !this.value.trim();
    });
    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (this.value.trim()) sendMessage();
        }
    });
    
    // Search conversations
    document.getElementById('convSearch').addEventListener('input', function() {
        filterConversations(this.value);
    });
    
    // Search contacts
    document.getElementById('contactSearch').addEventListener('input', function() {
        filterContacts(this.value);
    });
    
    // Start a conversation if user_id is provided
    const startWith = <?php echo json_encode($start_with_user); ?>;
    if (startWith > 0) {
        startConversationWith(startWith);
    }
    
    // Poll for new messages every 10 seconds
    pollInterval = setInterval(function() {
        loadConversations(true);
        if (activeConversationId) {
            loadMessages(activeConversationId, true);
        }
    }, 10000);
});

function escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatTime(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    const days = Math.floor(diff / 86400000);
    
    if (days === 0) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    } else if (days === 1) {
        return 'Yesterday';
    } else if (days < 7) {
        return date.toLocaleDateString([], { weekday: 'short' });
    } else {
        return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }
}

function formatFullTime(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleString([], { 
        month: 'short', day: 'numeric', year: 'numeric',
        hour: '2-digit', minute: '2-digit' 
    });
}

function getInitials(firstName, lastName) {
    return ((firstName || '').charAt(0) + (lastName || '').charAt(0)).toUpperCase();
}

function getRoleBadge(role) {
    const label = (role || '').replace(/_/g, ' ');
    let cls = 'athlete';
    if (role === 'admin') cls = 'admin';
    else if (role && role.includes('coach')) cls = 'coach';
    else if (role === 'parent') cls = 'parent';
    return `<span class="conv-role-badge ${cls}">${escapeHtml(label)}</span>`;
}

// Load conversations
function loadConversations(silent) {
    fetch('process_messages.php?action=get_conversations')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                conversations = data.conversations;
                renderConversations();
            }
        })
        .catch(err => {
            if (!silent) console.error('Failed to load conversations:', err);
        });
}

function renderConversations() {
    const list = document.getElementById('conversationList');
    const searchVal = (document.getElementById('convSearch').value || '').toLowerCase();
    
    let filtered = conversations;
    if (searchVal) {
        filtered = conversations.filter(c => 
            ((c.first_name || '') + ' ' + (c.last_name || '')).toLowerCase().includes(searchVal)
        );
    }
    
    if (filtered.length === 0) {
        list.innerHTML = `
            <div class="msg-empty-state" style="padding: 30px;">
                <i class="fas fa-inbox" style="font-size: 32px;"></i>
                <p>${searchVal ? 'No matching conversations' : 'No conversations yet'}</p>
                <p style="font-size: 13px;">Click "New Message" to start one.</p>
            </div>`;
        return;
    }
    
    list.innerHTML = filtered.map(c => {
        const isActive = activeConversationId == c.conversation_id;
        const hasUnread = c.unread_count > 0;
        const preview = c.last_message ? 
            (c.last_message_from == currentUserId ? 'You: ' : '') + c.last_message.substring(0, 50) + (c.last_message.length > 50 ? '...' : '')
            : 'No messages yet';
        
        return `
            <div class="conversation-item ${isActive ? 'active' : ''} ${hasUnread ? 'unread' : ''}" 
                 onclick="openConversation(${c.conversation_id}, ${c.other_user_id}, '${escapeHtml(c.first_name)}', '${escapeHtml(c.last_name)}', '${escapeHtml(c.role)}')">
                <div class="conv-avatar">${getInitials(c.first_name, c.last_name)}</div>
                <div class="conv-info">
                    <div class="conv-name">
                        ${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}
                        ${getRoleBadge(c.role)}
                    </div>
                    <div class="conv-preview">${escapeHtml(preview)}</div>
                </div>
                <div class="conv-meta">
                    <div class="conv-time">${c.last_message_time ? formatTime(c.last_message_time) : ''}</div>
                    ${hasUnread ? `<div class="conv-unread-badge">${c.unread_count}</div>` : ''}
                </div>
            </div>`;
    }).join('');
}

function filterConversations(query) {
    renderConversations();
}

// Open a conversation
function openConversation(convId, otherUserId, firstName, lastName, role) {
    activeConversationId = convId;
    
    document.getElementById('noChatSelected').style.display = 'none';
    document.getElementById('chatView').style.display = 'flex';
    document.getElementById('chatAvatar').textContent = getInitials(firstName, lastName);
    document.getElementById('chatName').textContent = (firstName || '') + ' ' + (lastName || '');
    document.getElementById('chatRole').innerHTML = (role || '').replace(/_/g, ' ');
    document.getElementById('chatView').dataset.otherUserId = otherUserId;
    
    // Highlight active conversation
    renderConversations();
    
    // Load messages
    loadMessages(convId);
    
    // Mobile: hide sidebar, show chat
    if (window.innerWidth <= 768) {
        document.getElementById('msgSidebar').classList.add('hidden');
        document.getElementById('msgMain').classList.remove('hidden');
    }
    
    // Focus input
    document.getElementById('msgInput').focus();
}

function loadMessages(convId, silent) {
    fetch(`process_messages.php?action=get_messages&conversation_id=${convId}`)
        .then(r => r.json())
        .then(data => {
            if (data.success && convId == activeConversationId) {
                renderMessages(data.messages);
                if (!silent) {
                    loadConversations(true); // Refresh unread counts
                }
            }
        })
        .catch(err => {
            if (!silent) console.error('Failed to load messages:', err);
        });
}

function renderMessages(messages) {
    const body = document.getElementById('chatBody');
    if (messages.length === 0) {
        body.innerHTML = `
            <div class="msg-empty-state">
                <i class="fas fa-paper-plane"></i>
                <h3>Start the Conversation</h3>
                <p>Send a message to get started.</p>
            </div>`;
        return;
    }
    
    let html = '';
    let lastDate = '';
    
    messages.forEach(msg => {
        const msgDate = new Date(msg.created_at).toLocaleDateString();
        if (msgDate !== lastDate) {
            html += `<div class="msg-date-divider"><span>${formatDateDivider(msg.created_at)}</span></div>`;
            lastDate = msgDate;
        }
        
        const isSent = msg.from_user_id == currentUserId;
        const readIcon = isSent ? 
            (msg.is_read == 1 ? 
                `<span class="msg-read-receipt" title="Read ${msg.read_at ? formatFullTime(msg.read_at) : ''}"><i class="fas fa-check-double"></i></span>` :
                `<span class="msg-read-receipt unread" title="Sent"><i class="fas fa-check"></i></span>`)
            : '';
        
        html += `
            <div class="msg-bubble-row ${isSent ? 'sent' : 'received'}">
                <div>
                    <div class="msg-bubble">${escapeHtml(msg.message_body)}</div>
                    <div class="msg-bubble-time">
                        ${formatTime(msg.created_at)}
                        ${readIcon}
                    </div>
                </div>
            </div>`;
    });
    
    body.innerHTML = html;
    body.scrollTop = body.scrollHeight;
}

function formatDateDivider(dateStr) {
    const date = new Date(dateStr);
    const now = new Date();
    const diff = Math.floor((now - date) / 86400000);
    
    if (diff === 0) return 'Today';
    if (diff === 1) return 'Yesterday';
    return date.toLocaleDateString([], { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
}

// Send message
function sendMessage() {
    const input = document.getElementById('msgInput');
    const text = input.value.trim();
    if (!text) return;
    
    const otherUserId = document.getElementById('chatView').dataset.otherUserId;
    if (!otherUserId) return;
    
    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('to_user_id', otherUserId);
    formData.append('message_body', text);
    formData.append('csrf_token', csrfToken);
    
    // Disable send button
    document.getElementById('sendBtn').disabled = true;
    input.value = '';
    input.style.height = 'auto';
    
    fetch('process_messages.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // If this is a new conversation, update the active ID
            if (!activeConversationId || activeConversationId !== data.conversation_id) {
                activeConversationId = data.conversation_id;
            }
            loadMessages(activeConversationId);
            loadConversations(true);
        } else {
            alert(data.message || 'Failed to send message');
            input.value = text;
        }
        document.getElementById('sendBtn').disabled = !input.value.trim();
    })
    .catch(err => {
        console.error('Send error:', err);
        alert('Failed to send message. Please try again.');
        input.value = text;
        document.getElementById('sendBtn').disabled = false;
    });
}

// New conversation
function openNewConversation() {
    document.getElementById('newConvModal').classList.add('show');
    loadContacts();
    document.getElementById('contactSearch').value = '';
    document.getElementById('contactSearch').focus();
}

function closeNewConversation() {
    document.getElementById('newConvModal').classList.remove('show');
}

function loadContacts() {
    fetch('process_messages.php?action=get_contacts')
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                contacts = data.contacts;
                renderContacts();
            }
        })
        .catch(err => console.error('Failed to load contacts:', err));
}

function renderContacts() {
    const list = document.getElementById('contactList');
    const searchVal = (document.getElementById('contactSearch').value || '').toLowerCase();
    
    let filtered = contacts;
    if (searchVal) {
        filtered = contacts.filter(c =>
            ((c.first_name || '') + ' ' + (c.last_name || '')).toLowerCase().includes(searchVal)
        );
    }
    
    if (filtered.length === 0) {
        list.innerHTML = '<div style="text-align: center; padding: 20px; color: #8b949e;">No contacts found</div>';
        return;
    }
    
    list.innerHTML = filtered.map(c => `
        <div class="msg-contact-item" onclick="startConversationWith(${c.id})">
            <div class="conv-avatar">${getInitials(c.first_name, c.last_name)}</div>
            <div>
                <div class="msg-contact-name">${escapeHtml(c.first_name)} ${escapeHtml(c.last_name)}</div>
                <div class="msg-contact-role">${(c.role || '').replace(/_/g, ' ')}</div>
            </div>
        </div>
    `).join('');
}

function filterContacts(query) {
    renderContacts();
}

function startConversationWith(userId) {
    closeNewConversation();
    
    // Check if conversation already exists
    const existing = conversations.find(c => c.other_user_id == userId);
    if (existing) {
        openConversation(existing.conversation_id, existing.other_user_id, 
            existing.first_name, existing.last_name, existing.role);
        return;
    }
    
    // Fetch user info and prepare new conversation view
    fetch(`process_messages.php?action=get_contacts`)
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const contact = data.contacts.find(c => c.id == userId);
                if (contact) {
                    activeConversationId = null;
                    document.getElementById('noChatSelected').style.display = 'none';
                    document.getElementById('chatView').style.display = 'flex';
                    document.getElementById('chatAvatar').textContent = getInitials(contact.first_name, contact.last_name);
                    document.getElementById('chatName').textContent = contact.first_name + ' ' + contact.last_name;
                    document.getElementById('chatRole').innerHTML = (contact.role || '').replace(/_/g, ' ');
                    document.getElementById('chatView').dataset.otherUserId = userId;
                    document.getElementById('chatBody').innerHTML = `
                        <div class="msg-empty-state">
                            <i class="fas fa-paper-plane"></i>
                            <h3>Start the Conversation</h3>
                            <p>Send a message to ${escapeHtml(contact.first_name)} to get started.</p>
                        </div>`;
                    document.getElementById('msgInput').focus();
                    
                    if (window.innerWidth <= 768) {
                        document.getElementById('msgSidebar').classList.add('hidden');
                        document.getElementById('msgMain').classList.remove('hidden');
                    }
                }
            }
        });
}

// Mobile back button
function backToList() {
    document.getElementById('msgSidebar').classList.remove('hidden');
    if (window.innerWidth <= 768) {
        document.getElementById('msgMain').classList.add('hidden');
    }
    activeConversationId = null;
    renderConversations();
}
</script>
