<?php
/**
 * Scheduled Reports - User Interface
 * Allows users to view and manage their own scheduled reports
 */

require_once __DIR__ . '/../security.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in'])) {
    header('Location: ../login.php');
    exit;
}

// Check if user has report access (coach, coach_plus, admin, team_coach)
if (!in_array($user_role, ['coach', 'coach_plus', 'admin', 'team_coach'])) {
    header('Location: ../dashboard.php?page=home');
    exit;
}

// Get user's scheduled reports
$stmt = $pdo->prepare("
    SELECT 
        id,
        report_type,
        frequency,
        format,
        email_recipients,
        parameters,
        last_run,
        next_run,
        is_active,
        created_at
    FROM report_schedules
    WHERE user_id = ?
    ORDER BY next_run ASC, created_at DESC
");
$stmt->execute([$user_id]);
$scheduled_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$csrf_token = generateCsrfToken();

// Report type labels
$report_types = [
    'income_report' => 'Income Report',
    'athlete_report' => 'Athlete Report',
    'session_report' => 'Session Report',
    'attendance_report' => 'Attendance Report',
    'goal_progress_report' => 'Goal Progress Report',
    'evaluation_report' => 'Evaluation Report',
    'team_roster' => 'Team Roster',
    'financial_summary' => 'Financial Summary'
];

// Frequency labels
$frequency_labels = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly'
];
?>

<style>
    :root {
        --primary: #7000a4;
    }
    
    .page-header {
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .page-title {
        font-size: 32px;
        font-weight: 900;
        color: #fff;
        margin: 0;
    }
    
    .page-subtitle {
        color: #94a3b8;
        font-size: 14px;
        margin-top: 5px;
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
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary:hover {
        background: #5a0080;
        transform: translateY(-2px);
    }
    
    .reports-table-container {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .reports-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .reports-table thead {
        background: #06080b;
    }
    
    .reports-table th {
        padding: 15px 20px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #1e293b;
    }
    
    .reports-table td {
        padding: 15px 20px;
        color: #fff;
        font-size: 14px;
        border-bottom: 1px solid #1e293b;
    }
    
    .reports-table tbody tr:hover {
        background: rgba(112, 0, 164, 0.05);
    }
    
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .status-badge.active {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    
    .status-badge.inactive {
        background: rgba(148, 163, 184, 0.1);
        border: 1px solid #64748b;
        color: #64748b;
    }
    
    .format-badge {
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        background: rgba(112, 0, 164, 0.1);
        color: var(--primary);
        border: 1px solid var(--primary);
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon {
        width: 32px;
        height: 32px;
        background: #1e293b;
        border: 1px solid #1e293b;
        border-radius: 4px;
        color: #94a3b8;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
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
    
    .btn-icon.success:hover {
        border-color: #00ff88;
        color: #00ff88;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #1e293b;
        margin-bottom: 20px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #94a3b8;
        font-size: 14px;
        margin-bottom: 25px;
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
    
    .modal-header h3 {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    
    .close {
        font-size: 28px;
        font-weight: 700;
        color: #94a3b8;
        cursor: pointer;
        transition: color 0.2s;
        line-height: 1;
    }
    
    .close:hover {
        color: #fff;
    }
    
    .modal-body {
        padding: 25px;
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
    }
    
    .form-input:focus,
    .form-select:focus,
    .form-textarea:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .form-textarea {
        resize: vertical;
        min-height: 80px;
        font-family: monospace;
    }
    
    .help-text {
        font-size: 12px;
        color: #64748b;
        margin-top: 5px;
        line-height: 1.4;
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
        margin: 0;
        color: #fff;
        font-size: 14px;
        cursor: pointer;
    }
    
    .modal-footer {
        padding: 20px 25px;
        border-top: 1px solid #1e293b;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
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
    
    .alert {
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideIn 0.3s ease-out;
    }
    
    .alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    
    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .frequency-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
    }
    
    .frequency-option {
        padding: 12px;
        background: #06080b;
        border: 2px solid #1e293b;
        border-radius: 6px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        font-weight: 600;
        font-size: 13px;
    }
    
    .frequency-option:hover {
        border-color: var(--primary);
    }
    
    .frequency-option.selected {
        background: rgba(112, 0, 164, 0.1);
        border-color: var(--primary);
        color: var(--primary);
    }
    
    @media (max-width: 768px) {
        .reports-table {
            font-size: 12px;
        }
        
        .reports-table th,
        .reports-table td {
            padding: 10px;
        }
        
        .page-header {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="page-header">
    <div>
        <h1 class="page-title"><i class="fas fa-calendar-alt"></i> Scheduled Reports</h1>
        <p class="page-subtitle">Manage your automated report schedules</p>
    </div>
    <button class="btn-primary" onclick="openCreateModal()">
        <i class="fas fa-plus"></i> Create Schedule
    </button>
</div>

<div id="alertContainer"></div>

<div class="reports-table-container">
    <?php if (empty($scheduled_reports)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Scheduled Reports</h3>
            <p>You haven't created any scheduled reports yet. Click "Create Schedule" to get started.</p>
        </div>
    <?php else: ?>
        <table class="reports-table">
            <thead>
                <tr>
                    <th>Report Type</th>
                    <th>Frequency</th>
                    <th>Format</th>
                    <th>Email Recipients</th>
                    <th>Last Run</th>
                    <th>Next Run</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($scheduled_reports as $report): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($report_types[$report['report_type']] ?? $report['report_type']) ?></strong>
                    </td>
                    <td><?= htmlspecialchars($frequency_labels[$report['frequency']] ?? $report['frequency']) ?></td>
                    <td>
                        <span class="format-badge"><?= htmlspecialchars(strtoupper($report['format'])) ?></span>
                    </td>
                    <td>
                        <?php 
                        $emails = explode(',', $report['email_recipients']);
                        $email_count = count($emails);
                        echo htmlspecialchars($emails[0]);
                        if ($email_count > 1) {
                            echo " <span style='color: #94a3b8;'>+".($email_count - 1)." more</span>";
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($report['last_run']): ?>
                            <?= date('M j, Y g:ia', strtotime($report['last_run'])) ?>
                        <?php else: ?>
                            <span style="color: #64748b;">Never</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($report['is_active']): ?>
                            <?= date('M j, Y g:ia', strtotime($report['next_run'])) ?>
                        <?php else: ?>
                            <span style="color: #64748b;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge <?= $report['is_active'] ? 'active' : 'inactive' ?>">
                            <i class="fas fa-circle" style="font-size: 6px;"></i>
                            <?= $report['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button 
                                class="btn-icon" 
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($report)) ?>)"
                                title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button 
                                class="btn-icon <?= $report['is_active'] ? 'danger' : 'success' ?>" 
                                onclick="toggleSchedule(<?= $report['id'] ?>, <?= $report['is_active'] ? 'false' : 'true' ?>)"
                                title="<?= $report['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                <i class="fas fa-<?= $report['is_active'] ? 'pause' : 'play' ?>"></i>
                            </button>
                            <button 
                                class="btn-icon danger" 
                                onclick="deleteSchedule(<?= $report['id'] ?>, '<?= htmlspecialchars($report_types[$report['report_type']] ?? $report['report_type']) ?>')"
                                title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Create/Edit Modal -->
<div id="scheduleModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modalTitle">Create Schedule</h3>
            <span class="close" onclick="closeModal()">&times;</span>
        </div>
        <form id="scheduleForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" id="formAction" value="schedule_create">
            <input type="hidden" name="schedule_id" id="scheduleId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select name="report_type" id="reportType" class="form-select" required>
                        <option value="">Select report type...</option>
                        <?php foreach ($report_types as $key => $label): ?>
                            <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Frequency</label>
                    <div class="frequency-grid">
                        <div class="frequency-option" data-frequency="daily">
                            <i class="fas fa-calendar-day"></i> Daily
                        </div>
                        <div class="frequency-option" data-frequency="weekly">
                            <i class="fas fa-calendar-week"></i> Weekly
                        </div>
                        <div class="frequency-option" data-frequency="monthly">
                            <i class="fas fa-calendar-alt"></i> Monthly
                        </div>
                    </div>
                    <input type="hidden" name="frequency" id="frequencyInput" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Format</label>
                    <select name="format" id="format" class="form-select" required>
                        <option value="">Select format...</option>
                        <option value="pdf">PDF</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email Recipients</label>
                    <input 
                        type="text" 
                        name="email_recipients" 
                        id="emailRecipients" 
                        class="form-input" 
                        placeholder="email1@example.com, email2@example.com"
                        required>
                    <div class="help-text">Comma-separated email addresses</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date Range (Optional)</label>
                    <div style="display: grid; grid-template-columns: 1fr auto 1fr; gap: 10px; align-items: center;">
                        <input 
                            type="month" 
                            id="dateFrom" 
                            class="form-input" 
                            placeholder="From">
                        <span style="color: #64748b;">to</span>
                        <input 
                            type="month" 
                            id="dateTo" 
                            class="form-input" 
                            placeholder="To">
                    </div>
                    <div class="help-text">Select date range for report data (e.g., Dec 2023 to Jan 2026). Leave empty for all-time data.</div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Additional Parameters (JSON)</label>
                    <textarea 
                        name="parameters" 
                        id="parameters" 
                        class="form-textarea" 
                        placeholder='{"include_charts": true, "group_by": "month"}'></textarea>
                    <div class="help-text">Optional JSON object for additional report-specific parameters (date range is auto-added above)</div>
                </div>
                
                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_active" id="isActive" value="1" checked>
                        <label for="isActive">Active (schedule will run automatically)</label>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="submitBtn">
                    <i class="fas fa-check"></i> Create Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Frequency selection
document.querySelectorAll('.frequency-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.frequency-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        document.getElementById('frequencyInput').value = this.dataset.frequency;
    });
});

// Open create modal
function openCreateModal() {
    document.getElementById('modalTitle').textContent = 'Create Schedule';
    document.getElementById('formAction').value = 'schedule_create';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check"></i> Create Schedule';
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleId').value = '';
    document.querySelectorAll('.frequency-option').forEach(o => o.classList.remove('selected'));
    document.getElementById('scheduleModal').classList.add('show');
}

// Open edit modal
function openEditModal(report) {
    document.getElementById('modalTitle').textContent = 'Edit Schedule';
    document.getElementById('formAction').value = 'schedule_update';
    document.getElementById('submitBtn').innerHTML = '<i class="fas fa-check"></i> Update Schedule';
    document.getElementById('scheduleId').value = report.id;
    document.getElementById('reportType').value = report.report_type;
    document.getElementById('format').value = report.format;
    document.getElementById('emailRecipients').value = report.email_recipients;
    document.getElementById('parameters').value = report.parameters || '';
    document.getElementById('isActive').checked = report.is_active == 1;
    
    // Set frequency
    document.querySelectorAll('.frequency-option').forEach(o => o.classList.remove('selected'));
    const freqOption = document.querySelector(`.frequency-option[data-frequency="${report.frequency}"]`);
    if (freqOption) {
        freqOption.classList.add('selected');
        document.getElementById('frequencyInput').value = report.frequency;
    }
    
    document.getElementById('scheduleModal').classList.add('show');
}

// Close modal
function closeModal() {
    document.getElementById('scheduleModal').classList.remove('show');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('scheduleModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Submit form
document.getElementById('scheduleForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    // Get date range values
    const dateFrom = document.getElementById('dateFrom').value;
    const dateTo = document.getElementById('dateTo').value;
    
    // Get existing parameters
    let params = {};
    const paramsText = formData.get('parameters');
    if (paramsText && paramsText.trim()) {
        try {
            params = JSON.parse(paramsText);
        } catch (e) {
            showAlert('Invalid JSON in parameters field', 'error');
            return;
        }
    }
    
    // Add date range if provided
    if (dateFrom || dateTo) {
        params.date_range = {
            from: dateFrom || null,
            to: dateTo || null
        };
    }
    
    // Update parameters in form data
    formData.set('parameters', JSON.stringify(params));
    
    try {
        const response = await fetch('../process_reports.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message || 'Schedule saved successfully', 'success');
            closeModal();
            setTimeout(() => location.reload(), 1500);
        } else {
            showAlert(result.message || 'Failed to save schedule', 'error');
        }
    } catch (error) {
        showAlert('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    }
});

// Toggle schedule active status
async function toggleSchedule(scheduleId, activate) {
    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
    formData.append('action', 'schedule_toggle');
    formData.append('schedule_id', scheduleId);
    formData.append('is_active', activate ? '1' : '0');
    
    try {
        const response = await fetch('../process_reports.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message || 'Schedule updated successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Failed to update schedule', 'error');
        }
    } catch (error) {
        showAlert('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    }
}

// Delete schedule
async function deleteSchedule(scheduleId, reportName) {
    if (!confirm(`Are you sure you want to delete the scheduled report "${reportName}"?\n\nThis action cannot be undone.`)) {
        return;
    }
    
    const formData = new FormData();
    formData.append('csrf_token', '<?= htmlspecialchars($csrf_token) ?>');
    formData.append('action', 'schedule_delete');
    formData.append('schedule_id', scheduleId);
    
    try {
        const response = await fetch('../process_reports.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showAlert(result.message || 'Schedule deleted successfully', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(result.message || 'Failed to delete schedule', 'error');
        }
    } catch (error) {
        showAlert('An error occurred. Please try again.', 'error');
        console.error('Error:', error);
    }
}

// Show alert message
function showAlert(message, type) {
    const alertContainer = document.getElementById('alertContainer');
    const alert = document.createElement('div');
    alert.className = `alert alert-${type}`;
    alert.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i>
        <span>${message}</span>
    `;
    alertContainer.appendChild(alert);
    
    setTimeout(() => {
        alert.remove();
    }, 5000);
}
</script>
