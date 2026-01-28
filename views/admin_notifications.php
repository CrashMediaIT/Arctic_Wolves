<!-- Admin System Notifications View - Rebuilt following Style Guide -->
<?php
// Fetch existing notifications from database
try {
    $notifications_stmt = $pdo->prepare("
        SELECT sn.*, u.first_name, u.last_name 
        FROM system_notifications sn
        LEFT JOIN users u ON sn.created_by = u.id
        ORDER BY sn.created_at DESC
    ");
    $notifications_stmt->execute();
    $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("System notifications fetch error: " . $e->getMessage());
    $notifications = [];
}

// Count active notifications
$active_count = 0;
$scheduled_count = 0;
$now = new DateTime();
foreach ($notifications as $n) {
    if ($n['is_active']) {
        $start = new DateTime($n['start_date']);
        $end = $n['end_date'] ? new DateTime($n['end_date']) : null;
        
        if ($start <= $now && (!$end || $end >= $now)) {
            $active_count++;
        } else if ($start > $now) {
            $scheduled_count++;
        }
    }
}
?>

<!-- Page Header with Stats -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="fas fa-bell"></i> System Notifications
        </h1>
        <p class="page-description">Create and manage system-wide notifications and alerts</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat stat-success">
            <span class="stat-value"><?php echo $active_count; ?></span>
            <span class="stat-label">Active</span>
        </div>
        <div class="header-stat stat-info">
            <span class="stat-value"><?php echo $scheduled_count; ?></span>
            <span class="stat-label">Scheduled</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?php echo count($notifications); ?></span>
            <span class="stat-label">Total</span>
        </div>
    </div>
</div>

<!-- Action Bar -->
<div class="action-bar">
    <div class="results-info">
        <span><?php echo count($notifications); ?> notification<?php echo count($notifications) !== 1 ? 's' : ''; ?> found</span>
    </div>
    <div class="view-controls">
        <button type="button" class="btn btn-primary" onclick="openCreateModal()">
            <i class="fas fa-plus"></i> Create Notification
        </button>
    </div>
</div>

<!-- Notifications List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> All Notifications</h3>
    </div>
    <div class="card-body">
        <?php if (count($notifications) > 0): ?>
            <div class="notifications-list">
                <?php foreach ($notifications as $notification): 
                    $type_class = $notification['notification_type'];
                    $is_currently_active = $notification['is_active'];
                    $start_date = new DateTime($notification['start_date']);
                    $end_date = $notification['end_date'] ? new DateTime($notification['end_date']) : null;
                    $is_live = $is_currently_active && $start_date <= $now && (!$end_date || $end_date >= $now);
                    $is_scheduled = $is_currently_active && $start_date > $now;
                    $is_expired = $end_date && $end_date < $now;
                    
                    // Icon mapping
                    $icons = [
                        'info' => 'fa-info-circle',
                        'warning' => 'fa-exclamation-triangle',
                        'alert' => 'fa-exclamation-circle',
                        'maintenance' => 'fa-tools'
                    ];
                    $icon = $icons[$notification['notification_type']] ?? 'fa-bell';
                ?>
                    <div class="notification-item <?php echo $type_class; ?> <?php echo $is_live ? 'live' : ''; ?>" data-id="<?php echo $notification['id']; ?>">
                        <div class="notification-icon">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="notification-content">
                            <div class="notification-header">
                                <h4><?php echo htmlspecialchars($notification['title']); ?></h4>
                                <div class="notification-badges">
                                    <?php if ($is_live): ?>
                                        <span class="badge badge-success"><i class="fas fa-circle"></i> Live</span>
                                    <?php elseif ($is_scheduled): ?>
                                        <span class="badge badge-primary"><i class="fas fa-clock"></i> Scheduled</span>
                                    <?php elseif ($is_expired): ?>
                                        <span class="badge badge-secondary"><i class="fas fa-history"></i> Expired</span>
                                    <?php elseif (!$is_currently_active): ?>
                                        <span class="badge badge-secondary"><i class="fas fa-pause"></i> Inactive</span>
                                    <?php endif; ?>
                                    <span class="badge badge-<?php echo $type_class; ?>"><?php echo ucfirst($notification['notification_type']); ?></span>
                                </div>
                            </div>
                            <p><?php echo htmlspecialchars($notification['message']); ?></p>
                            <div class="notification-meta">
                                <span><i class="fas fa-calendar-alt"></i> Starts: <?php echo $start_date->format('M j, Y g:i A'); ?></span>
                                <?php if ($end_date): ?>
                                    <span><i class="fas fa-calendar-check"></i> Ends: <?php echo $end_date->format('M j, Y g:i A'); ?></span>
                                <?php else: ?>
                                    <span><i class="fas fa-infinity"></i> No end date</span>
                                <?php endif; ?>
                                <span><i class="fas fa-user"></i> <?php echo htmlspecialchars($notification['first_name'] . ' ' . $notification['last_name']); ?></span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <button class="btn btn-secondary btn-sm" title="Toggle Active" onclick="toggleNotification(<?php echo $notification['id']; ?>)">
                                <i class="fas <?php echo $is_currently_active ? 'fa-pause' : 'fa-play'; ?>"></i>
                            </button>
                            <button class="btn btn-secondary btn-sm" title="Edit" onclick="editNotification(<?php echo $notification['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-danger btn-sm" title="Delete" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state-card">
                <i class="fas fa-bell-slash"></i>
                <h4>No Notifications</h4>
                <p>Create your first system notification to alert users about important updates. Use the "Create Notification" button above to get started.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Create/Edit Notification Modal -->
<div id="notification-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-bell"></i> <span id="modal-title-text">Create Notification</span></h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <form id="notification-form" method="POST" action="process_system_notifications.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" id="form-action" value="create">
            <input type="hidden" name="id" id="notification-id" value="">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group" style="flex: 2;">
                        <label class="form-label">Title <span class="required">*</span></label>
                        <input type="text" name="title" id="notification-title" class="form-input" placeholder="Notification title" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label class="form-label">Type <span class="required">*</span></label>
                        <select name="notification_type" id="notification-type" class="form-input" required>
                            <option value="info">Info</option>
                            <option value="warning">Warning</option>
                            <option value="alert">Alert</option>
                            <option value="maintenance">Maintenance</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Message <span class="required">*</span></label>
                    <textarea name="message" id="notification-message" class="form-input" rows="4" placeholder="Enter your notification message..." required></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Start Date/Time <span class="required">*</span></label>
                        <input type="datetime-local" name="start_time" id="notification-start" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date/Time</label>
                        <input type="datetime-local" name="end_time" id="notification-end" class="form-input">
                        <small class="form-help">Leave empty for no end date</small>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="toggle-group">
                            <input type="checkbox" name="is_active" id="notification-active" checked>
                            <span class="toggle-switch"><span class="toggle-slider"></span></span>
                            <span class="toggle-label">Active immediately</span>
                        </label>
                    </div>
                    <div class="form-group" id="email-option-group">
                        <label class="toggle-group">
                            <input type="checkbox" name="send_email" id="notification-email">
                            <span class="toggle-switch"><span class="toggle-slider"></span></span>
                            <span class="toggle-label">Send email to all users</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="submit-btn">
                    <i class="fas fa-paper-plane"></i> <span id="submit-btn-text">Create Notification</span>
                </button>
            </div>
        </form>
    </div>
</div>

<style>
/* Notifications List Styles - Following Style Guide */
.notifications-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-4, 16px);
}

.notification-item {
    display: flex;
    gap: var(--space-5, 20px);
    padding: var(--space-5, 20px);
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: var(--radius-lg, 8px);
    transition: all var(--transition-normal, 0.2s ease);
}

.notification-item:hover {
    border-color: var(--primary, #6B46C1);
}

.notification-item.live {
    border-left: 3px solid var(--success, #10B981);
}

/* Notification Icon */
.notification-icon {
    width: 48px;
    height: 48px;
    border-radius: var(--radius-xl, 10px);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.notification-item.info .notification-icon {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.notification-item.warning .notification-icon {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.notification-item.alert .notification-icon {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.notification-item.maintenance .notification-icon {
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
}

/* Notification Content */
.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: var(--space-3, 12px);
    margin-bottom: var(--space-2, 8px);
    flex-wrap: wrap;
}

.notification-content h4 {
    font-size: var(--font-size-md, 16px);
    font-weight: var(--font-weight-bold, 700);
    color: var(--text-white, #FFFFFF);
    margin: 0;
}

.notification-badges {
    display: flex;
    gap: var(--space-2, 8px);
    flex-wrap: wrap;
}

.notification-content p {
    font-size: var(--font-size-base, 14px);
    color: var(--text-secondary, #A8A8B8);
    line-height: 1.6;
    margin-bottom: var(--space-3, 12px);
}

.notification-meta {
    display: flex;
    gap: var(--space-5, 20px);
    font-size: var(--font-size-sm, 12px);
    color: var(--text-muted, #6B6B7B);
    flex-wrap: wrap;
}

.notification-meta i {
    color: var(--primary-light, #8B5CF6);
    margin-right: var(--space-1, 4px);
}

/* Notification Actions */
.notification-actions {
    display: flex;
    gap: var(--space-2, 8px);
    align-items: flex-start;
}

/* Badges */
.badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: var(--radius-md, 6px);
    font-size: var(--font-size-xs, 11px);
    font-weight: var(--font-weight-semibold, 600);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge i {
    font-size: 8px;
}

.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success, #10B981);
}

.badge-primary {
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light, #8B5CF6);
}

.badge-secondary {
    background: rgba(168, 168, 184, 0.15);
    color: var(--text-secondary, #A8A8B8);
}

.badge-info {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.badge-warning {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.badge-alert {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.badge-maintenance {
    background: rgba(139, 92, 246, 0.15);
    color: #8b5cf6;
}

/* Toggle Group */
.toggle-group {
    display: flex;
    align-items: center;
    gap: var(--space-3, 12px);
    cursor: pointer;
}

.toggle-group input[type="checkbox"] {
    display: none;
}

.toggle-switch {
    position: relative;
    width: 44px;
    height: 24px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    transition: all var(--transition-fast, 0.15s ease);
}

.toggle-slider {
    position: absolute;
    width: 18px;
    height: 18px;
    background: var(--text-muted, #6B6B7B);
    border-radius: 50%;
    top: 2px;
    left: 2px;
    transition: all var(--transition-fast, 0.15s ease);
}

.toggle-group input:checked + .toggle-switch {
    background: var(--primary, #6B46C1);
    border-color: var(--primary, #6B46C1);
}

.toggle-group input:checked + .toggle-switch .toggle-slider {
    transform: translateX(20px);
    background: white;
}

.toggle-label {
    font-size: var(--font-size-base, 14px);
    color: var(--text-secondary, #A8A8B8);
}

/* Form Help Text */
.form-help {
    font-size: var(--font-size-xs, 11px);
    color: var(--text-muted, #6B6B7B);
    margin-top: var(--space-1, 4px);
}

/* Empty State */
.empty-state-card {
    text-align: center;
    padding: var(--space-10, 40px) var(--space-6, 24px);
}

.empty-state-card i {
    font-size: 48px;
    color: var(--text-muted, #6B6B7B);
    margin-bottom: var(--space-4, 16px);
}

.empty-state-card h4 {
    font-size: var(--font-size-lg, 18px);
    font-weight: var(--font-weight-bold, 700);
    color: var(--text-white, #FFFFFF);
    margin-bottom: var(--space-2, 8px);
}

.empty-state-card p {
    font-size: var(--font-size-base, 14px);
    color: var(--text-secondary, #A8A8B8);
    margin-bottom: var(--space-5, 20px);
}

/* Action Bar */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-6, 24px);
    flex-wrap: wrap;
    gap: var(--space-4, 16px);
}

.results-info {
    font-size: var(--font-size-base, 14px);
    color: var(--text-secondary, #A8A8B8);
}

.view-controls {
    display: flex;
    gap: var(--space-3, 12px);
}

/* Responsive */
@media (max-width: 768px) {
    .notification-item {
        flex-direction: column;
    }
    
    .notification-actions {
        justify-content: flex-end;
    }
    
    .notification-header {
        flex-direction: column;
    }
    
    .notification-meta {
        flex-direction: column;
        gap: var(--space-2, 8px);
    }
    
    .form-row {
        flex-direction: column;
    }
}
</style>

<script>
// Store notifications data for editing
const notificationsData = <?php echo json_encode($notifications); ?>;

// Open Create Modal
function openCreateModal() {
    document.getElementById('modal-title-text').textContent = 'Create Notification';
    document.getElementById('submit-btn-text').textContent = 'Create Notification';
    document.getElementById('form-action').value = 'create';
    document.getElementById('notification-id').value = '';
    document.getElementById('notification-form').reset();
    
    // Set default start time to now
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('notification-start').value = now.toISOString().slice(0, 16);
    document.getElementById('notification-active').checked = true;
    
    // Show email option for new notifications
    document.getElementById('email-option-group').style.display = 'block';
    
    document.getElementById('notification-modal').classList.add('active');
}

// Edit Notification
function editNotification(id) {
    const notification = notificationsData.find(n => n.id == id);
    if (!notification) return;
    
    document.getElementById('modal-title-text').textContent = 'Edit Notification';
    document.getElementById('submit-btn-text').textContent = 'Update Notification';
    document.getElementById('form-action').value = 'update';
    document.getElementById('notification-id').value = id;
    
    document.getElementById('notification-title').value = notification.title;
    document.getElementById('notification-type').value = notification.notification_type;
    document.getElementById('notification-message').value = notification.message;
    
    // Format dates for datetime-local input
    if (notification.start_date) {
        const start = new Date(notification.start_date);
        start.setMinutes(start.getMinutes() - start.getTimezoneOffset());
        document.getElementById('notification-start').value = start.toISOString().slice(0, 16);
    }
    
    if (notification.end_date) {
        const end = new Date(notification.end_date);
        end.setMinutes(end.getMinutes() - end.getTimezoneOffset());
        document.getElementById('notification-end').value = end.toISOString().slice(0, 16);
    } else {
        document.getElementById('notification-end').value = '';
    }
    
    document.getElementById('notification-active').checked = notification.is_active == 1;
    
    // Hide email option when editing
    document.getElementById('email-option-group').style.display = 'none';
    
    document.getElementById('notification-modal').classList.add('active');
}

// Close Modal
function closeModal() {
    document.getElementById('notification-modal').classList.remove('active');
}

// Toggle Notification Active Status
function toggleNotification(id) {
    const formData = new FormData();
    formData.append('action', 'toggle_active');
    formData.append('id', id);
    formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>');
    
    fetch('process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to toggle notification'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the notification');
    });
}

// Delete Notification
function deleteNotification(id) {
    if (!confirm('Are you sure you want to delete this notification? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('id', id);
    formData.append('csrf_token', '<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES); ?>');
    
    fetch('process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to delete notification'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while deleting the notification');
    });
}

// Form Submit Handler
document.getElementById('notification-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    const submitBtn = document.getElementById('submit-btn');
    const originalText = submitBtn.innerHTML;
    
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch('process_system_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        
        if (data.success) {
            closeModal();
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to save notification'));
        }
    })
    .catch(error => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        console.error('Error:', error);
        alert('An error occurred while saving the notification');
    });
});

// Close modal when clicking outside
document.getElementById('notification-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>
