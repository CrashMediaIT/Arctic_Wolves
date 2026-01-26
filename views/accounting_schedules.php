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
            <?php
            // Fetch schedules from database
            try {
                $schedulesStmt = $pdo->prepare("
                    SELECT rs.*, u.first_name, u.last_name
                    FROM report_schedules rs
                    LEFT JOIN users u ON rs.created_by = u.id
                    WHERE rs.created_by = ?
                    ORDER BY rs.is_active DESC, rs.next_run ASC
                ");
                $schedulesStmt->execute([$user_id]);
                $activeSchedules = $schedulesStmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Schedules fetch error: " . $e->getMessage());
                $activeSchedules = [];
            }
            ?>
            <span class="active-count"><?= count($activeSchedules) ?> schedules</span>
        </div>
        <div class="card-body">
            <div class="schedules-list">
                <?php if (!empty($activeSchedules)): ?>
                    <?php foreach ($activeSchedules as $schedule): 
                        $isActive = $schedule['is_active'] == 1;
                        $recipientCount = !empty($schedule['recipients']) ? count(explode(',', $schedule['recipients'])) : 0;
                        $nextRun = $schedule['next_run'] ? date('M j, Y \a\t g:i A', strtotime($schedule['next_run'])) : 'Not scheduled';
                    ?>
                    <div class="schedule-item <?= !$isActive ? 'paused' : '' ?>">
                        <div class="schedule-status <?= $isActive ? 'active' : 'paused' ?>">
                            <i class="fas fa-<?= $isActive ? 'check' : 'pause' ?>-circle"></i>
                        </div>
                        <div class="schedule-details">
                            <h4><?= htmlspecialchars($schedule['report_name'] ?? ucwords(str_replace('_', ' ', $schedule['report_type']))) ?></h4>
                            <div class="schedule-meta">
                                <span><i class="fas fa-calendar-alt"></i> <?= ucfirst($schedule['schedule_frequency'] ?? 'Weekly') ?></span>
                                <span><i class="fas fa-clock"></i> <?= $schedule['schedule_time'] ?? '09:00' ?></span>
                                <?php if ($recipientCount > 0): ?>
                                <span><i class="fas fa-envelope"></i> <?= $recipientCount ?> recipient<?= $recipientCount > 1 ? 's' : '' ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="schedule-next">
                                <?php if ($isActive): ?>
                                <strong>Next run:</strong> <?= $nextRun ?>
                                <?php else: ?>
                                <strong>Status:</strong> Paused
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="schedule-actions">
                            <button class="btn-icon schedule-edit-btn" data-schedule-id="<?= $schedule['id'] ?>" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="btn-icon schedule-toggle-btn" data-schedule-id="<?= $schedule['id'] ?>" data-active="<?= $isActive ? '1' : '0' ?>" title="<?= $isActive ? 'Pause' : 'Resume' ?>"><i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i></button>
                            <button class="btn-icon schedule-delete-btn" data-schedule-id="<?= $schedule['id'] ?>" title="Delete"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text" style="text-align: center; padding: 40px;">No scheduled reports yet. Create your first schedule using the form above.</p>
                <?php endif; ?>
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

<!-- Edit Schedule Modal -->
<div id="edit-schedule-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Schedule</h2>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form method="POST" action="process_reports.php" id="editScheduleForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="schedule_update">
            <input type="hidden" name="schedule_id" id="editScheduleId">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Schedule Name *</label>
                        <input type="text" name="schedule_name" id="editScheduleName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Report Type *</label>
                        <select name="report_type" id="editReportType" class="form-input" required>
                            <option value="">-- Select Report --</option>
                            <option value="revenue_summary">Revenue Summary</option>
                            <option value="expense_report">Expense Report</option>
                            <option value="profit_loss">Profit & Loss</option>
                            <option value="client_billing">Client Billing</option>
                            <option value="session_analytics">Session Analytics</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Frequency *</label>
                        <select name="frequency" id="editFrequency" class="form-input" required>
                            <option value="daily">Daily</option>
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annually">Annually</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" name="time" id="editTime" class="form-input" value="09:00">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Recipients</label>
                    <input type="text" name="email_recipients" id="editRecipients" class="form-input" placeholder="email1@example.com, email2@example.com">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Output Format</label>
                    <div class="format-options">
                        <label class="radio-option format-pdf">
                            <input type="radio" name="format" id="editFormatPdf" value="pdf">
                            <span><i class="fas fa-file-pdf"></i> PDF</span>
                        </label>
                        <label class="radio-option format-excel">
                            <input type="radio" name="format" id="editFormatExcel" value="excel">
                            <span><i class="fas fa-file-excel"></i> Excel</span>
                        </label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Schedule</button>
            </div>
        </form>
    </div>
</div>

<?php
// Encode schedules data for JavaScript
$schedulesJson = json_encode($activeSchedules);
?>

<script>
// Make schedules data available to JavaScript
var schedulesData = <?= $schedulesJson ?>;

document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    
    // Show notification
    function showNotification(message, type) {
        var alertDiv = document.createElement('div');
        alertDiv.className = type === 'success' ? 'success-alert' : 'error-alert';
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px; border-radius: 8px; display: flex; align-items: center; gap: 12px;';
        if (type === 'success') {
            alertDiv.style.background = 'rgba(16, 185, 129, 0.95)';
            alertDiv.style.color = '#fff';
        } else {
            alertDiv.style.background = 'rgba(239, 68, 68, 0.95)';
            alertDiv.style.color = '#fff';
        }
        alertDiv.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
        document.body.appendChild(alertDiv);
        setTimeout(function() { alertDiv.remove(); }, 3000);
    }
    
    // Toggle schedule (pause/resume)
    document.querySelectorAll('.schedule-toggle-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var scheduleId = this.getAttribute('data-schedule-id');
            var isActive = this.getAttribute('data-active') === '1';
            
            fetch('process_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=toggle_schedule&schedule_id=' + scheduleId + '&is_active=' + (isActive ? '0' : '1') + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification('Schedule ' + (isActive ? 'paused' : 'resumed') + ' successfully!', 'success');
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    showNotification(data.message || 'Failed to update schedule', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
            });
        });
    });
    
    // Delete schedule
    document.querySelectorAll('.schedule-delete-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var scheduleId = this.getAttribute('data-schedule-id');
            
            if (!confirm('Are you sure you want to delete this schedule?')) {
                return;
            }
            
            fetch('process_reports.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=delete_schedule&schedule_id=' + scheduleId + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification('Schedule deleted successfully!', 'success');
                    setTimeout(function() { window.location.reload(); }, 1000);
                } else {
                    showNotification(data.message || 'Failed to delete schedule', 'error');
                }
            })
            .catch(function(error) {
                console.error('Error:', error);
                showNotification('An error occurred', 'error');
            });
        });
    });
    
    // Edit schedule - open modal with data
    document.querySelectorAll('.schedule-edit-btn').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var scheduleId = this.getAttribute('data-schedule-id');
            
            // Find schedule data
            var schedule = schedulesData.find(function(s) { return s.id == scheduleId; });
            
            if (schedule) {
                // Populate modal fields
                document.getElementById('editScheduleId').value = schedule.id;
                document.getElementById('editScheduleName').value = schedule.report_name || '';
                document.getElementById('editReportType').value = schedule.report_type || '';
                document.getElementById('editFrequency').value = schedule.schedule_frequency || 'weekly';
                document.getElementById('editTime').value = schedule.schedule_time || '09:00';
                document.getElementById('editRecipients').value = schedule.recipients || '';
                
                // Set format
                if (schedule.format === 'excel') {
                    document.getElementById('editFormatExcel').checked = true;
                } else {
                    document.getElementById('editFormatPdf').checked = true;
                }
                
                // Show modal
                document.getElementById('edit-schedule-modal').classList.add('active');
            } else {
                showNotification('Schedule not found', 'error');
            }
        });
    });
    
    // Handle edit form submission via AJAX
    var editForm = document.getElementById('editScheduleForm');
    if (editForm) {
        editForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var submitBtn = this.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            submitBtn.disabled = true;
            
            fetch('process_reports.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showNotification(data.message || 'Schedule updated!', 'success');
                    closeEditModal();
                    setTimeout(function() { window.location.reload(); }, 1500);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to update'), 'error');
                }
            })
            .catch(function(error) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('An error occurred', 'error');
            });
        });
    }
});

function closeEditModal() {
    document.getElementById('edit-schedule-modal').classList.remove('active');
}

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.remove('active');
        }
    });
});
</script>
