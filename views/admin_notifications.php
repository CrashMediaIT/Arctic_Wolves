<!-- Admin Notifications View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-bell"></i> System Notifications
    </h1>
    <p class="page-description">Manage system-wide notifications and alerts</p>
</div>

<div class="notifications-content">
    <!-- Create Notification -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Create Notification</h3>
        </div>
        <div class="card-body">
            <form class="notification-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" class="form-input" placeholder="Notification title" required>
                    </div>
                    <div class="form-group">
                        <label>Type *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Type --</option>
                            <option>Info</option>
                            <option>Warning</option>
                            <option>Success</option>
                            <option>Error</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea class="form-textarea" rows="3" placeholder="Notification message" required></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Target Audience *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Audience --</option>
                            <option>All Users</option>
                            <option>Admins Only</option>
                            <option>Coaches Only</option>
                            <option>Athletes Only</option>
                            <option>Parents Only</option>
                            <option>Custom</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select class="form-input">
                            <option>Low</option>
                            <option selected>Normal</option>
                            <option>High</option>
                            <option>Urgent</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>Schedule</label>
                    <div class="schedule-options">
                        <label class="radio-option">
                            <input type="radio" name="schedule" value="now" checked>
                            <span>Send Now</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="schedule" value="later">
                            <span>Schedule for Later</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Notification</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Notifications -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Active Notifications</h3>
        </div>
        <div class="card-body">
            <div class="notifications-list">
                <div class="notification-item info">
                    <div class="notification-icon">
                        <i class="fas fa-info-circle"></i>
                    </div>
                    <div class="notification-content">
                        <h4>System Maintenance Scheduled</h4>
                        <p>System will be down for maintenance on Jan 20, 2024 from 2-4 AM.</p>
                        <div class="notification-meta">
                            <span><i class="fas fa-users"></i> All Users</span>
                            <span><i class="fas fa-clock"></i> Sent 2 hours ago</span>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="notification-item success">
                    <div class="notification-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="notification-content">
                        <h4>New Features Available</h4>
                        <p>Check out the new video review tools in your dashboard!</p>
                        <div class="notification-meta">
                            <span><i class="fas fa-users"></i> Coaches</span>
                            <span><i class="fas fa-clock"></i> Sent yesterday</span>
                        </div>
                    </div>
                    <div class="notification-actions">
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.schedule-options {
    display: flex;
    gap: 15px;
}

.notifications-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.notification-item {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.notification-item:hover {
    border-color: var(--neon);
}

.notification-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.notification-item.info .notification-icon {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.notification-item.success .notification-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.notification-item.warning .notification-icon {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.notification-item.error .notification-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.notification-content {
    flex: 1;
}

.notification-content h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.notification-content p {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 10px;
}

.notification-meta {
    display: flex;
    gap: 20px;
    font-size: 12px;
    color: var(--text-dim);
}

.notification-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.notification-actions {
    display: flex;
    gap: 8px;
}
</style>
