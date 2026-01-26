<!-- Accounting Schedules View -->
<?php if (isset($_GET['success'])): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;"><?= htmlspecialchars(urldecode($_GET['success'])) ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['error'])): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;"><?= htmlspecialchars(urldecode($_GET['error'])) ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clock"></i> Scheduled Reports
    </h1>
    <p class="page-description">Automate report generation and delivery to save time</p>
</div>

<div class="schedules-content">
    <!-- Schedule Stats -->
    <div class="schedule-stats">
        <div class="schedule-stat-card active">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value">2</span>
                <span class="stat-label">Active Schedules</span>
            </div>
        </div>
        <div class="schedule-stat-card paused">
            <div class="stat-icon"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value">1</span>
                <span class="stat-label">Paused</span>
            </div>
        </div>
        <div class="schedule-stat-card success">
            <div class="stat-icon"><i class="fas fa-paper-plane"></i></div>
            <div class="stat-info">
                <span class="stat-value">24</span>
                <span class="stat-label">Reports Sent</span>
            </div>
        </div>
        <div class="schedule-stat-card next">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <span class="stat-value">Feb 1</span>
                <span class="stat-label">Next Scheduled</span>
            </div>
        </div>
    </div>

    <!-- Create Schedule -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Create Report Schedule</h3>
            <span class="header-badge">New Schedule</span>
        </div>
        <div class="card-body">
            <form class="schedule-form" method="POST" action="process_reports.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="schedule_create">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-tag"></i> Schedule Name *</label>
                        <input type="text" name="schedule_name" class="form-input" placeholder="e.g., Monthly Revenue Report" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-file-alt"></i> Report Type *</label>
                        <select name="report_type" class="form-input" required>
                            <option value="">-- Select Report --</option>
                            <option value="revenue_summary">Revenue Summary</option>
                            <option value="expense_report">Expense Report</option>
                            <option value="profit_loss">Profit & Loss</option>
                            <option value="client_billing">Client Billing</option>
                            <option value="session_analytics">Session Analytics</option>
                        </select>
                    </div>
                </div>

                <div class="form-row three-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-sync"></i> Frequency *</label>
                        <select name="frequency" class="form-input" required>
                            <option value="">-- Select Frequency --</option>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annually">Annually</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar-day"></i> Day of Week/Month</label>
                        <select name="day_of_period" class="form-input">
                            <option value="1">1st of month</option>
                            <option value="15">15th of month</option>
                            <option value="last">Last day of month</option>
                            <option value="monday">Monday</option>
                            <option value="friday">Friday</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-clock"></i> Time</label>
                        <input type="time" name="time" class="form-input" value="09:00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-envelope"></i> Email Recipients</label>
                    <input type="text" name="email_recipients" class="form-input" placeholder="email1@example.com, email2@example.com">
                    <small class="form-hint">Separate multiple emails with commas. Leave empty to only save the report.</small>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-download"></i> Output Format</label>
                    <div class="format-options">
                        <label class="radio-option format-pdf">
                            <input type="radio" name="format" value="pdf" checked>
                            <span><i class="fas fa-file-pdf"></i> PDF</span>
                        </label>
                        <label class="radio-option format-excel">
                            <input type="radio" name="format" value="excel">
                            <span><i class="fas fa-file-excel"></i> Excel</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-calendar-plus"></i> Create Schedule
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Schedules -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-check"></i> Active Schedules</h3>
            <span class="active-count">3 schedules</span>
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
                        <button class="btn-icon" data-action="edit" data-id="1" data-type="schedule" data-modal="edit-schedule-modal" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" data-action="toggle" data-id="1" data-type="schedule" title="Pause"><i class="fas fa-pause"></i></button>
                        <button class="btn-icon" data-action="delete" data-id="1" data-type="schedule" data-action-url="process_reports.php" title="Delete"><i class="fas fa-trash"></i></button>
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
                        <button class="btn-icon" data-action="edit" data-id="2" data-type="schedule" data-modal="edit-schedule-modal" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" data-action="toggle" data-id="2" data-type="schedule" title="Pause"><i class="fas fa-pause"></i></button>
                        <button class="btn-icon" data-action="delete" data-id="2" data-type="schedule" data-action-url="process_reports.php" title="Delete"><i class="fas fa-trash"></i></button>
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
                        <button class="btn-icon" data-action="edit" data-id="3" data-type="schedule" data-modal="edit-schedule-modal" title="Edit"><i class="fas fa-edit"></i></button>
                        <button class="btn-icon" data-action="toggle" data-id="3" data-type="schedule" title="Resume"><i class="fas fa-play"></i></button>
                        <button class="btn-icon" data-action="delete" data-id="3" data-type="schedule" data-action-url="process_reports.php" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Schedule History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Execution History</h3>
            <button class="btn-secondary btn-small"><i class="fas fa-filter"></i> Filter</button>
        </div>
        <div class="card-body">
            <div class="history-list">
                <div class="history-item success">
                    <div class="history-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="history-details">
                        <h4>Monthly Revenue Summary</h4>
                        <span class="history-date">Executed on Jan 1, 2026 at 9:00 AM</span>
                    </div>
                    <div class="history-actions">
                        <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                        <span class="history-status success">Success</span>
                    </div>
                </div>

                <div class="history-item success">
                    <div class="history-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="history-details">
                        <h4>Weekly Session Analytics</h4>
                        <span class="history-date">Executed on Jan 15, 2026 at 8:00 AM</span>
                    </div>
                    <div class="history-actions">
                        <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                        <span class="history-status success">Success</span>
                    </div>
                </div>

                <div class="history-item failed">
                    <div class="history-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="history-details">
                        <h4>Client Billing Summary</h4>
                        <span class="history-date">Failed on Jan 10, 2026 at 9:30 AM</span>
                        <span class="history-error">Error: Email delivery failed</span>
                    </div>
                    <div class="history-actions">
                        <button class="btn-icon" title="Retry"><i class="fas fa-redo"></i></button>
                        <span class="history-status failed">Failed</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* Schedule Stats */
.schedule-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.schedule-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
}

.schedule-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.schedule-stat-card .stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.schedule-stat-card.active .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.schedule-stat-card.paused .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.schedule-stat-card.success .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.schedule-stat-card.next .stat-icon { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }

.schedule-stat-card .stat-info { flex: 1; }

.schedule-stat-card .stat-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    display: block;
    margin-bottom: 4px;
}

.schedule-stat-card .stat-label {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Header badge */
.header-badge {
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.active-count {
    font-size: 13px;
    color: var(--text-dim);
    font-weight: 500;
}

/* Form labels with icons */
.form-label i {
    margin-right: 8px;
    color: var(--primary);
}

.form-row.three-cols {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.form-hint {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    margin-top: 6px;
}

.format-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.radio-option {
    display: flex;
    align-items: center;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 14px 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.radio-option:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.radio-option input { margin-right: 10px; }

.radio-option span {
    font-size: 14px;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 8px;
}

.format-pdf i { color: #ef4444; }
.format-excel i { color: #10b981; }

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.schedules-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.schedule-item {
    display: flex;
    align-items: start;
    gap: 20px;
    padding: 24px;
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
    gap: 14px;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 18px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.history-item:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.03);
}

.history-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.history-item.success .history-icon {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.history-item.failed .history-icon {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.history-details {
    flex: 1;
    min-width: 0;
}

.history-details h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.history-date {
    font-size: 12px;
    color: var(--text-dim);
    display: block;
}

.history-error {
    font-size: 11px;
    color: #ef4444;
    display: block;
    margin-top: 4px;
}

.history-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.history-status {
    padding: 6px 14px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.history-status.success {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.history-status.failed {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

@media (max-width: 768px) {
    .schedule-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row.three-cols {
        grid-template-columns: 1fr;
    }
    
    .schedule-item {
        flex-direction: column;
        align-items: stretch;
    }
    
    .schedule-actions {
        justify-content: flex-end;
    }
    
    .history-item {
        flex-direction: column;
        text-align: center;
    }
    
    .history-actions {
        width: 100%;
        justify-content: center;
    }
}

@media (max-width: 480px) {
    .schedule-stats {
        grid-template-columns: 1fr;
    }
}
</style>
