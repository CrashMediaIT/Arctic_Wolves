<?php
/**
 * Admin System Notifications
 * Create global maintenance notifications for all users
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get all system notifications
$notifications_query = $pdo->query("
    SELECT 
        sn.*,
        CONCAT(u.first_name, ' ', u.last_name) as created_by_name
    FROM system_notifications sn
    LEFT JOIN users u ON sn.created_by = u.id
    ORDER BY sn.created_at DESC
");
$notifications = $notifications_query->fetchAll(PDO::FETCH_ASSOC);

// Count active notifications
$active_count = 0;
foreach ($notifications as $n) {
    if ($n['is_active']) $active_count++;
}

$csrf_token = generateCsrfToken();
?>

<style>
    /* System Notifications Enhanced Styles */
    :root {
        --primary: #7000a4;
    }
    
    .notifications-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid #1e293b;
    }
    
    .page-header-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .page-header-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--primary), #5a0080);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(112, 0, 164, 0.3);
    }
    
    .page-header-text h1 {
        font-size: 28px;
        font-weight: 800;
        margin: 0 0 4px 0;
        letter-spacing: -0.5px;
    }
    
    .page-header-text p {
        font-size: 14px;
        color: #94a3b8;
        margin: 0;
    }
    
    .page-header-stats {
        display: flex;
        gap: 20px;
    }
    
    .header-stat {
        text-align: center;
        padding: 12px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 12px;
        min-width: 90px;
    }
    
    .header-stat .stat-value {
        display: block;
        font-size: 24px;
        font-weight: 700;
        color: #a855f7;
    }
    
    .header-stat .stat-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 14px 28px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 12px rgba(112, 0, 164, 0.3);
    }
    
    .btn-create:hover {
        background: #5a0080;
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(112, 0, 164, 0.4);
    }
    
    .notifications-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
        gap: 20px;
    }
    
    .notification-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 14px;
        padding: 24px;
        transition: all 0.3s;
        position: relative;
        overflow: hidden;
    }
    
    .notification-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary);
        opacity: 0;
        transition: opacity 0.3s;
    }
    
    .notification-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.3);
    }
    
    .notification-card:hover::before {
        opacity: 1;
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 16px;
    }
    
    .notification-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 8px 0;
    }
    
    .notification-meta {
        font-size: 12px;
        color: #64748b;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .notification-meta i {
        color: var(--primary);
    }
    
    .notification-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .badge-maintenance {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
        border: 1px solid rgba(251, 191, 36, 0.3);
    }
    
    .badge-info {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    
    .badge-warning {
        background: rgba(245, 158, 11, 0.15);
        color: #f59e0b;
        border: 1px solid rgba(245, 158, 11, 0.3);
    }
    
    .badge-alert {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    
    .badge-active {
        background: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border: 1px solid rgba(0, 255, 136, 0.3);
    }
    
    .badge-inactive {
        background: rgba(156, 163, 175, 0.15);
        color: #9ca3af;
        border: 1px solid rgba(156, 163, 175, 0.3);
    }
    
    .notification-message {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 16px;
        padding: 16px;
        background: #06080b;
        border-radius: 10px;
        border-left: 3px solid var(--primary);
    }
    
    .notification-schedule {
        display: flex;
        gap: 16px;
        padding: 14px;
        background: #06080b;
        border-radius: 10px;
        font-size: 13px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }
    
    .schedule-item {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #64748b;
    }
    
    .schedule-item i {
        color: var(--primary);
    }
    
    .notification-actions {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding-top: 16px;
        border-top: 1px solid #1e293b;
    }
    
    .btn-icon {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 10px 14px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-icon:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .btn-icon.danger:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
    }
    
    /* Modal Enhanced */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.85);
        z-index: 10000;
        overflow-y: auto;
        padding: 20px;
        backdrop-filter: blur(4px);
    }
    
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 16px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
    }
    
    .modal-header {
        padding: 24px;
        border-bottom: 1px solid #1e293b;
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(180deg, rgba(112, 0, 164, 0.08) 0%, transparent 100%);
    }
    
    .modal-header h2 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .modal-header h2 i {
        color: var(--primary);
    }
    
    .modal-close {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
        width: 36px;
        height: 36px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 8px;
        transition: all 0.2s;
    }
    
    .modal-close:hover {
        background: rgba(255, 255, 255, 0.05);
        color: #fff;
    }
    
    .modal-body {
        padding: 24px;
    }
    
    .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid #1e293b;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    
    .form-group {
        margin-bottom: 24px;
    }
    
    .form-label {
        display: block;
        font-size: 13px;
        font-weight: 600;
        color: #94a3b8;
        margin-bottom: 10px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .form-label .required {
        color: #ef4444;
    }
    
    .form-input,
    .form-select,
    .form-textarea {
        width: 100%;
        padding: 14px 16px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 10px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
        transition: all 0.3s;
    }
    
    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(112, 0, 164, 0.2);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .help-text {
        font-size: 12px;
        color: #64748b;
        margin-top: 8px;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 16px;
        background: #06080b;
        border-radius: 10px;
        border: 1px solid #1e293b;
        transition: all 0.3s;
    }
    
    .checkbox-group:hover {
        border-color: var(--primary);
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
        accent-color: var(--primary);
    }
    
    .checkbox-group label {
        font-size: 14px;
        color: #fff;
        cursor: pointer;
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
        padding: 14px 28px;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #5a0080;
        transform: translateY(-2px);
    }
    
    .btn-secondary {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 14px 28px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .btn-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .empty-state {
        text-align: center;
        padding: 80px 24px;
        color: #64748b;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 16px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #1e293b;
        margin-bottom: 24px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        margin-bottom: 24px;
    }
    
    @media (max-width: 768px) {
        .notifications-page-header {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .page-header-content {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .page-header-stats {
            width: 100%;
            justify-content: space-between;
        }
        
        .notifications-grid {
            grid-template-columns: 1fr;
        }
        
        .notification-actions {
            flex-wrap: wrap;
        }
    }
</style>

<div class="notifications-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-bullhorn"></i>
        </div>
        <div class="page-header-text">
            <h1>System Notifications</h1>
            <p>Create and manage global maintenance notifications and alerts for all users</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= count($notifications) ?></span>
            <span class="stat-label">Total</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $active_count ?></span>
            <span class="stat-label">Active</span>
        </div>
    </div>
</div>

<div style="margin-bottom: 24px; text-align: right;">
    <button class="btn-create" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Notification
    </button>
</div>

<?php if (empty($notifications)): ?>
    <div class="empty-state">
        <i class="fas fa-bullhorn"></i>
        <h3>No System Notifications</h3>
        <p>Create your first system-wide notification</p>
        <button class="btn-create" onclick="openCreateModal()" style="margin-top: 12px;">
            <i class="fas fa-plus"></i> Create Notification
        </button>
    </div>
<?php else: ?>
    <div class="notifications-grid">
        <?php foreach ($notifications as $notif): ?>
            <div class="notification-card">
                <div class="notification-header">
                    <div>
                        <h3 class="notification-title"><?= htmlspecialchars($notif['title']) ?></h3>
                        <div class="notification-meta">
                            Created by <?= htmlspecialchars($notif['created_by_name']) ?> on 
                            <?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                        </div>
                    </div>
                    <div class="notification-badges">
                        <span class="badge badge-<?= htmlspecialchars($notif['notification_type']) ?>">
                            <?= htmlspecialchars($notif['notification_type']) ?>
                        </span>
                        <span class="badge badge-<?= $notif['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $notif['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </div>
                </div>
                
                <div class="notification-message">
                    <?= nl2br(htmlspecialchars($notif['message'])) ?>
                </div>
                
                <div class="notification-schedule">
                    <div class="schedule-item">
                        <i class="fas fa-clock"></i>
                        Start: <?= date('M j, Y g:i A', strtotime($notif['start_date'])) ?>
                    </div>
                    <?php if ($notif['end_date']): ?>
                        <div class="schedule-item">
                            <i class="fas fa-clock"></i>
                            End: <?= date('M j, Y g:i A', strtotime($notif['end_date'])) ?>
                        </div>
                    <?php else: ?>
                        <div class="schedule-item">
                            <i class="fas fa-infinity"></i>
                            No end time
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="notification-actions">
                    <button class="btn-icon" onclick="toggleActive(<?= $notif['id'] ?>)" title="Toggle Status">
                        <i class="fas fa-power-off"></i>
                    </button>
                    <button class="btn-icon" onclick="editNotification(<?= $notif['id'] ?>)" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn-icon danger" onclick="deleteNotification(<?= $notif['id'] ?>, '<?= htmlspecialchars($notif['title'], ENT_QUOTES) ?>')" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create/Edit Modal -->
<div id="notificationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Create System Notification</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal()">&times;</button>
        </div>
        <form id="notificationForm" method="POST" action="process_system_notifications.php" onsubmit="submitForm(event)">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" id="notificationId" name="id">
            <input type="hidden" id="formAction" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">
                        Title <span class="required">*</span>
                    </label>
                    <input type="text" id="notifTitle" name="title" class="form-input" required placeholder="e.g., Scheduled Maintenance">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Message <span class="required">*</span>
                    </label>
                    <textarea id="notifMessage" name="message" class="form-textarea" required placeholder="Enter the notification message..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Type <span class="required">*</span>
                    </label>
                    <select id="notifType" name="notification_type" class="form-select" required>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="alert">Alert</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        Start Time <span class="required">*</span>
                    </label>
                    <input type="datetime-local" id="notifStartTime" name="start_time" class="form-input" required>
                    <div class="help-text">When should this notification become active?</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">End Time</label>
                    <input type="datetime-local" id="notifEndTime" name="end_time" class="form-input">
                    <div class="help-text">Leave empty for no end time</div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notifSendEmail" name="send_email" value="1">
                        <label for="notifSendEmail">Send email notification to all users</label>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="notifIsActive" name="is_active" value="1" checked>
                        <label for="notifIsActive">Active</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-save"></i> Save Notification
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const notificationsData = <?= json_encode($notifications) ?>;
const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create System Notification';
    document.getElementById('formAction').value = 'create';
    document.getElementById('notificationForm').reset();
    document.getElementById('notificationId').value = '';
    document.getElementById('notifIsActive').checked = true;
    document.querySelector('input[name="csrf_token"]').value = csrfToken;
    document.getElementById('notificationModal').classList.add('show');
}

function editNotification(id) {
    const notif = notificationsData.find(n => n.id == id);
    if (!notif) return;
    
    document.getElementById('modalTitle').textContent = 'Edit System Notification';
    document.getElementById('formAction').value = 'update';
    document.getElementById('notificationId').value = notif.id;
    document.getElementById('notifTitle').value = notif.title;
    document.getElementById('notifMessage').value = notif.message;
    document.getElementById('notifType').value = notif.notification_type;
    
    // Convert timestamps to datetime-local format
    const startDate = new Date(notif.start_date);
    document.getElementById('notifStartTime').value = formatDateTimeLocal(startDate);
    
    if (notif.end_date) {
        const endDate = new Date(notif.end_date);
        document.getElementById('notifEndTime').value = formatDateTimeLocal(endDate);
    }
    
    document.getElementById('notifIsActive').checked = notif.is_active == 1;
    document.querySelector('input[name="csrf_token"]').value = csrfToken;
    
    document.getElementById('notificationModal').classList.add('show');
}

function formatDateTimeLocal(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

function closeModal() {
    document.getElementById('notificationModal').classList.remove('show');
}

// Toast notification helper
function showToast(message, type) {
    var toast = document.createElement('div');
    toast.className = 'toast toast-' + type;
    toast.textContent = message;
    toast.style.cssText = 'position: fixed; top: 20px; right: 20px; padding: 16px 24px; background: ' + 
        (type === 'success' ? '#10B981' : type === 'error' ? '#EF4444' : '#6B46C1') + 
        '; color: white; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); z-index: 10000; font-family: Inter, sans-serif; font-size: 14px;';
    document.body.appendChild(toast);
    setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity 0.3s ease';
        setTimeout(function() { toast.remove(); }, 300);
    }, 3000);
}

function submitForm(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch('process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Notification saved successfully', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(error => {
        showToast('Error: ' + error.message, 'error');
    });
}

function toggleActive(id) {
    if (!confirm('Toggle the status of this notification?')) return;
    
    const formData = new FormData();
    formData.append('action', 'toggle_active');
    formData.append('id', id);
    formData.append('csrf_token', csrfToken);
    
    fetch('process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast('Status updated', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Update failed'), 'error');
        }
    });
}

function deleteNotification(id, title) {
    if (!confirm(`Delete notification "${title}"?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    
    fetch('process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast('Notification deleted', 'success');
            window.location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Delete failed'), 'error');
        }
    });
}

// Close modal when clicking outside
document.getElementById('notificationModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>
