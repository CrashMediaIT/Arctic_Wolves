<!-- Accounting Schedules View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clock"></i> Scheduled Reports
    </h1>
    <p class="page-description">Automate report generation and delivery</p>
</div>

<div class="schedules-content">
    <!-- Create Schedule -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Create Report Schedule</h3>
        </div>
        <div class="card-body">
            <form class="schedule-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Schedule Name *</label>
                        <input type="text" class="form-input" placeholder="e.g., Monthly Revenue Report" required>
                    </div>
                    <div class="form-group">
                        <label>Report Type *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Report --</option>
                            <option>Revenue Summary</option>
                            <option>Expense Report</option>
                            <option>Profit & Loss</option>
                            <option>Client Billing</option>
                            <option>Session Analytics</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Frequency *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Frequency --</option>
                            <option>Daily</option>
                            <option>Weekly</option>
                            <option>Monthly</option>
                            <option>Quarterly</option>
                            <option>Annually</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Day of Week/Month</label>
                        <select class="form-input">
                            <option>1st of month</option>
                            <option>15th of month</option>
                            <option>Last day of month</option>
                            <option>Monday</option>
                            <option>Friday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" class="form-input" value="09:00">
                    </div>
                </div>

                <div class="form-group">
                    <label>Email Recipients</label>
                    <input type="text" class="form-input" placeholder="email1@example.com, email2@example.com">
                    <small class="form-hint">Separate multiple emails with commas</small>
                </div>

                <div class="form-group">
                    <label>Format</label>
                    <div class="format-options">
                        <label class="radio-option">
                            <input type="radio" name="format" value="pdf" checked>
                            <span><i class="fas fa-file-pdf"></i> PDF</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="format" value="excel">
                            <span><i class="fas fa-file-excel"></i> Excel</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Create Schedule</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Schedules -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-check"></i> Active Schedules</h3>
        </div>
        <div class="card-body">
            <div class="schedules-list">
                <div class="schedule-item">
                    <div class="schedule-status active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="schedule-details">
                        <h4>Monthly Revenue Summary</h4>
                        <div class="schedule-meta">
                            <span><i class="fas fa-calendar-alt"></i> Monthly on 1st</span>
                            <span><i class="fas fa-clock"></i> 9:00 AM</span>
                            <span><i class="fas fa-envelope"></i> 3 recipients</span>
                        </div>
                        <div class="schedule-next">
                            <strong>Next run:</strong> Feb 1, 2024 at 9:00 AM
                        </div>
                    </div>
                    <div class="schedule-actions">
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Pause"><i class="fas fa-pause"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="schedule-item">
                    <div class="schedule-status active">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="schedule-details">
                        <h4>Weekly Session Analytics</h4>
                        <div class="schedule-meta">
                            <span><i class="fas fa-calendar-alt"></i> Weekly on Monday</span>
                            <span><i class="fas fa-clock"></i> 8:00 AM</span>
                            <span><i class="fas fa-envelope"></i> 2 recipients</span>
                        </div>
                        <div class="schedule-next">
                            <strong>Next run:</strong> Jan 22, 2024 at 8:00 AM
                        </div>
                    </div>
                    <div class="schedule-actions">
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Pause"><i class="fas fa-pause"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="schedule-item paused">
                    <div class="schedule-status paused">
                        <i class="fas fa-pause-circle"></i>
                    </div>
                    <div class="schedule-details">
                        <h4>Quarterly Profit & Loss</h4>
                        <div class="schedule-meta">
                            <span><i class="fas fa-calendar-alt"></i> Quarterly</span>
                            <span><i class="fas fa-clock"></i> 10:00 AM</span>
                            <span><i class="fas fa-envelope"></i> 5 recipients</span>
                        </div>
                        <div class="schedule-next">
                            <strong>Status:</strong> Paused
                        </div>
                    </div>
                    <div class="schedule-actions">
                        <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" title="Resume"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Executions</h3>
        </div>
        <div class="card-body">
            <div class="history-list">
                <div class="history-item success">
                    <div class="history-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="history-details">
                        <h4>Monthly Revenue Summary</h4>
                        <span class="history-date">Executed on Jan 1, 2024 at 9:00 AM</span>
                    </div>
                    <span class="history-status success">Success</span>
                </div>

                <div class="history-item success">
                    <div class="history-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="history-details">
                        <h4>Weekly Session Analytics</h4>
                        <span class="history-date">Executed on Jan 15, 2024 at 8:00 AM</span>
                    </div>
                    <span class="history-status success">Success</span>
                </div>

                <div class="history-item failed">
                    <div class="history-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="history-details">
                        <h4>Client Billing Summary</h4>
                        <span class="history-date">Failed on Jan 10, 2024 at 9:30 AM</span>
                    </div>
                    <span class="history-status failed">Failed</span>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.form-hint {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    margin-top: 5px;
}

.schedules-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.schedule-item {
    display: flex;
    align-items: start;
    gap: 20px;
    padding: 25px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.schedule-item:hover {
    border-color: var(--neon);
}

.schedule-item.paused {
    opacity: 0.6;
}

.schedule-status {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.schedule-status.active {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.schedule-status.paused {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.schedule-details {
    flex: 1;
}

.schedule-details h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.schedule-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    margin-bottom: 10px;
}

.schedule-meta span {
    font-size: 13px;
    color: var(--text-dim);
}

.schedule-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.schedule-next {
    font-size: 13px;
    color: var(--text-dim);
}

.schedule-next strong {
    color: var(--text-white);
}

.schedule-actions {
    display: flex;
    gap: 8px;
}

.history-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.history-icon {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.history-item.success .history-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.history-item.failed .history-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.history-details {
    flex: 1;
}

.history-details h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 4px;
}

.history-date {
    font-size: 12px;
    color: var(--text-dim);
}

.history-status {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
}

.history-status.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.history-status.failed {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}
</style>
