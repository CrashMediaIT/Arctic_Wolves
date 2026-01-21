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

$csrf_token = generateCsrfToken();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    
    .notifications-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .notifications-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin: 0;
    }
    
    .notifications-header p {
        color: #94a3b8;
        font-size: 14px;
        margin: 5px 0 0 0;
    }
    
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-create:hover {
        background: #5a0080;
    }
    
    .notifications-grid {
        display: grid;
        gap: 20px;
    }
    
    .notification-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        transition: all 0.2s;
    }
    
    .notification-card:hover {
        border-color: var(--primary);
    }
    
    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 15px;
    }
    
    .notification-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 5px 0;
    }
    
    .notification-meta {
        font-size: 12px;
        color: #64748b;
    }
    
    .notification-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .badge-maintenance {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
        border: 1px solid #fbbf24;
    }
    
    .badge-update {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid #3b82f6;
    }
    
    .badge-alert {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    
    .badge-active {
        background: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border: 1px solid #00ff88;
    }
    
    .badge-inactive {
        background: rgba(156, 163, 175, 0.15);
        color: #9ca3af;
        border: 1px solid #9ca3af;
    }
    
    .notification-message {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 15px;
    }
    
    .notification-schedule {
        display: flex;
        gap: 20px;
        padding: 10px;
        background: #06080b;
        border-radius: 4px;
        font-size: 13px;
        margin-bottom: 15px;
    }
    
    .schedule-item {
        display: flex;
        align-items: center;
        gap: 5px;
        color: #64748b;
    }
    
    .notification-actions {
        display: flex;
        gap: 8px;
        justify-content: flex-end;
    }
    
    .btn-icon {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
    }
    
    .btn-icon:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-icon.danger:hover {
        border-color: #ef4444;
        color: #ef4444;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        overflow-y: auto;
        padding: 20px;
    }
    
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        width: 100%;
        max-width: 600px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #1e293b;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h2 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }
    
    .modal-close {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #1e293b;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
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
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
    }
    
    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    .help-text {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
    }
    
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    
    .checkbox-group label {
        font-size: 14px;
        color: #fff;
        cursor: pointer;
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background: #5a0080;
    }
    
    .btn-secondary {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 12px 24px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .btn-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #1e293b;
        margin-bottom: 20px;
    }
</style>

<div class="notifications-header">
    <div>
        <h1><i class="fas fa-bullhorn"></i> System Notifications</h1>
        <p>Create global maintenance notifications and alerts for all users</p>
    </div>
    <button class="btn-create" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Notification
    </button>
</div>

<?php if (empty($notifications)): ?>
    <div class="empty-state">
        <i class="fas fa-bullhorn"></i>
        <h3>No System Notifications</h3>
        <p>Create your first system-wide notification</p>
        <button class="btn-create" onclick="openCreateModal()" style="margin-top: 15px;">
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
                        Start: <?= date('M j, Y g:i A', strtotime($notif['start_time'])) ?>
                    </div>
                    <?php if ($notif['end_time']): ?>
                        <div class="schedule-item">
                            <i class="fas fa-clock"></i>
                            End: <?= date('M j, Y g:i A', strtotime($notif['end_time'])) ?>
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
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="notificationForm" onsubmit="submitForm(event)">
            <input type="hidden" name="csrf_token" value="<?= csrfTokenInput() ?>">
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
                        <option value="maintenance">Maintenance</option>
                        <option value="update">Update</option>
                        <option value="alert">Alert</option>
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

function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create System Notification';
    document.getElementById('formAction').value = 'create';
    document.getElementById('notificationForm').reset();
    document.getElementById('notificationId').value = '';
    document.getElementById('notifIsActive').checked = true;
    document.querySelector('input[name="csrf_token"]').value = '<?= generateCSRFToken() ?>';
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
    const startDate = new Date(notif.start_time);
    document.getElementById('notifStartTime').value = formatDateTimeLocal(startDate);
    
    if (notif.end_time) {
        const endDate = new Date(notif.end_time);
        document.getElementById('notifEndTime').value = formatDateTimeLocal(endDate);
    }
    
    document.getElementById('notifIsActive').checked = notif.is_active == 1;
    document.querySelector('input[name="csrf_token"]').value = '<?= generateCSRFToken() ?>';
    
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

function submitForm(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    
    fetch('../process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function toggleActive(id) {
    if (!confirm('Toggle the status of this notification?')) return;
    
    const formData = new FormData();
    formData.append('action', 'toggle_active');
    formData.append('id', id);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('../process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function deleteNotification(id, title) {
    if (!confirm(`Delete notification "${title}"?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?= generateCSRFToken() ?>');
    
    fetch('../process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
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
