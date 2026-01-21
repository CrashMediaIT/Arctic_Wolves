<?php
/**
 * Comprehensive Reporting System
 * Generate, schedule, and share various reports
 */

require_once __DIR__ . '/../security.php';

// Check permissions
if (!in_array($user_role, ['coach', 'coach_plus', 'admin', 'team_coach'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get user's athletes for filtering
$athletes_list = [];
if (in_array($user_role, ['coach', 'coach_plus'])) {
    $athletes_stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE assigned_coach_id = ? AND role = 'athlete' ORDER BY last_name, first_name");
    $athletes_stmt->execute([$user_id]);
    $athletes_list = $athletes_stmt->fetchAll();
}

// Get teams for filtering
$teams_list = [];
if ($user_role === 'admin' || $user_role === 'team_coach') {
    $teams_stmt = $pdo->query("SELECT id, name FROM athlete_teams ORDER BY name");
    $teams_list = $teams_stmt->fetchAll();
}

// Get user's recent reports
$reports_stmt = $pdo->prepare("SELECT * FROM reports WHERE generated_by = ? ORDER BY created_at DESC LIMIT 20");
$reports_stmt->execute([$user_id]);
$recent_reports = $reports_stmt->fetchAll();

// Get scheduled reports
$schedules_stmt = $pdo->prepare("SELECT * FROM report_schedules WHERE user_id = ? AND is_active = 1 ORDER BY next_run ASC");
$schedules_stmt->execute([$user_id]);
$scheduled_reports = $schedules_stmt->fetchAll();

$csrf_token = generateCsrfToken();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .reports-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-bottom: 30px;
    }
    @media (max-width: 1200px) {
        .reports-container {
            grid-template-columns: 1fr;
        }
    }
    .report-section {
        background: #0a0f14;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 25px;
    }
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .section-title i {
        color: var(--primary);
    }
    .report-type-grid {
        display: grid;
        gap: 15px;
    }
    .report-type-card {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        transition: all 0.2s;
        cursor: pointer;
    }
    .report-type-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .report-type-card.selected {
        background: rgba(112, 0, 164, 0.1);
        border-color: var(--primary);
    }
    .report-type-title {
        font-weight: 700;
        font-size: 15px;
        color: #fff;
        margin-bottom: 5px;
    }
    .report-type-desc {
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.5;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 8px;
    }
    .form-control {
        width: 100%;
        padding: 12px 15px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
    }
    .btn-group {
        display: flex;
        gap: 10px;
        margin-top: 20px;
    }
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    .btn-primary:hover {
        background: #5a0085;
    }
    .btn-secondary {
        background: #334155;
        color: #fff;
    }
    .btn-secondary:hover {
        background: #475569;
    }
    .recent-reports-list {
        display: grid;
        gap: 10px;
    }
    .report-item {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .report-info {
        flex: 1;
    }
    .report-name {
        font-weight: 700;
        font-size: 14px;
        color: #fff;
        margin-bottom: 4px;
    }
    .report-meta {
        font-size: 11px;
        color: #64748b;
    }
    .report-actions {
        display: flex;
        gap: 8px;
    }
    .icon-btn {
        width: 32px;
        height: 32px;
        background: #1e293b;
        border: none;
        border-radius: 4px;
        color: #94a3b8;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .icon-btn:hover {
        background: var(--primary);
        color: #fff;
    }
    .format-options {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }
    .format-option {
        flex: 1;
        padding: 15px;
        background: #06080b;
        border: 2px solid #1e293b;
        border-radius: 8px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
    }
    .format-option:hover {
        border-color: var(--primary);
    }
    .format-option.selected {
        background: rgba(112, 0, 164, 0.1);
        border-color: var(--primary);
    }
    .format-option i {
        font-size: 24px;
        margin-bottom: 8px;
        display: block;
        color: var(--primary);
    }
    .format-option-label {
        font-weight: 700;
        font-size: 13px;
    }
    .checkbox-group {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 15px;
    }
    .checkbox-group input[type="checkbox"] {
        width: 18px;
        height: 18px;
        cursor: pointer;
    }
    .checkbox-group label {
        font-size: 14px;
        color: #94a3b8;
        cursor: pointer;
    }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
</div>

<div class="reports-container">
    <!-- Generate New Report -->
    <div class="report-section">
        <h2 class="section-title"><i class="fas fa-file-pdf"></i> Generate Report</h2>
        
        <form id="generateReportForm" action="process_reports.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="generate">
            <input type="hidden" name="report_type" id="selected_report_type" value="">
            
            <div class="form-group">
                <label class="form-label">Report Type</label>
                <div class="report-type-grid" id="reportTypeGrid">
                    <?php if (in_array($user_role, ['coach', 'coach_plus'])): ?>
                    <div class="report-type-card" data-type="athlete_progress">
                        <div class="report-type-title"><i class="fas fa-user-check"></i> Athlete Progress</div>
                        <div class="report-type-desc">Progress report for your athletes including goals, evaluations, and session attendance</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (in_array($user_role, ['coach', 'coach_plus', 'team_coach', 'admin'])): ?>
                    <div class="report-type-card" data-type="team_roster">
                        <div class="report-type-title"><i class="fas fa-users"></i> Team Roster</div>
                        <div class="report-type-desc">Complete team roster with athlete details and statistics</div>
                    </div>
                    <div class="report-type-card" data-type="session_attendance">
                        <div class="report-type-title"><i class="fas fa-calendar-check"></i> Session Attendance</div>
                        <div class="report-type-desc">Attendance tracking and session participation data</div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($user_role === 'admin'): ?>
                    <div class="report-type-card" data-type="all_athletes">
                        <div class="report-type-title"><i class="fas fa-users-cog"></i> All Athletes</div>
                        <div class="report-type-desc">Complete database of all athletes with comprehensive data</div>
                    </div>
                    <div class="report-type-card" data-type="all_teams">
                        <div class="report-type-title"><i class="fas fa-sitemap"></i> All Teams</div>
                        <div class="report-type-desc">Overview of all teams, rosters, and team coaches</div>
                    </div>
                    <div class="report-type-card" data-type="packages_discounts">
                        <div class="report-type-title"><i class="fas fa-tags"></i> Packages & Discounts</div>
                        <div class="report-type-desc">Financial report on packages purchased and discount usage</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div id="reportFilters" style="display: none;">
                <div class="form-group" id="athleteFilter" style="display: none;">
                    <label class="form-label">Select Athletes</label>
                    <select name="athlete_ids[]" class="form-control" multiple size="5">
                        <?php foreach ($athletes_list as $athlete): ?>
                        <option value="<?= $athlete['id'] ?>"><?= htmlspecialchars($athlete['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="teamFilter" style="display: none;">
                    <label class="form-label">Select Team(s)</label>
                    <select name="team_ids[]" class="form-control" multiple size="5">
                        <option value="all">All Teams</option>
                        <?php foreach ($teams_list as $team): ?>
                        <option value="<?= $team['id'] ?>"><?= htmlspecialchars($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Date Range</label>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <input type="date" name="date_from" class="form-control" value="<?= date('Y-m-d', strtotime('-30 days')) ?>">
                        <input type="date" name="date_to" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Format</label>
                    <div class="format-options">
                        <div class="format-option selected" data-format="pdf">
                            <i class="fas fa-file-pdf"></i>
                            <div class="format-option-label">PDF</div>
                        </div>
                        <div class="format-option" data-format="csv">
                            <i class="fas fa-file-csv"></i>
                            <div class="format-option-label">CSV</div>
                        </div>
                    </div>
                    <input type="hidden" name="format" id="selected_format" value="pdf">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="schedule" id="schedule_report" value="1">
                    <label for="schedule_report">Schedule this report</label>
                </div>
                
                <div id="scheduleOptions" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <select name="frequency" class="form-control">
                            <option value="weekly">Weekly</option>
                            <option value="monthly">Monthly</option>
                            <option value="daily">Daily</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Email Recipients (comma-separated)</label>
                        <input type="text" name="email_recipients" class="form-control" placeholder="email1@example.com, email2@example.com">
                    </div>
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-file-export"></i> Generate Report
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                        <i class="fas fa-undo"></i> Reset
                    </button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Recent Reports -->
    <div class="report-section">
        <h2 class="section-title"><i class="fas fa-history"></i> Recent Reports</h2>
        
        <?php if (empty($recent_reports)): ?>
        <p style="color: #64748b; text-align: center; padding: 40px 0;">No reports generated yet. Create your first report!</p>
        <?php else: ?>
        <div class="recent-reports-list">
            <?php foreach ($recent_reports as $report): ?>
            <div class="report-item">
                <div class="report-info">
                    <div class="report-name">
                        <i class="fas fa-file-<?= $report['format'] === 'pdf' ? 'pdf' : 'csv' ?>"></i>
                        <?= htmlspecialchars(ucwords(str_replace('_', ' ', $report['report_type']))) ?>
                    </div>
                    <div class="report-meta">
                        Generated <?= date('M j, Y g:i A', strtotime($report['created_at'])) ?>
                        <?php if ($report['scheduled']): ?>
                        <span style="color: var(--primary);"><i class="fas fa-clock"></i> Scheduled</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="report-actions">
                    <?php if ($report['file_path'] && file_exists(__DIR__ . '/../' . $report['file_path'])): ?>
                    <a href="<?= htmlspecialchars($report['file_path']) ?>" class="icon-btn" title="Download" download>
                        <i class="fas fa-download"></i>
                    </a>
                    <?php endif; ?>
                    <?php if ($report['share_token']): ?>
                    <button class="icon-btn" onclick="copyShareLink('<?= htmlspecialchars($report['share_token']) ?>')" title="Copy Share Link">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <?php endif; ?>
                    <button class="icon-btn" onclick="deleteReport(<?= $report['id'] ?>)" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Scheduled Reports -->
<?php if (!empty($scheduled_reports)): ?>
<div class="report-section" style="margin-top: 20px;">
    <h2 class="section-title"><i class="fas fa-clock"></i> Scheduled Reports</h2>
    <div class="recent-reports-list">
        <?php foreach ($scheduled_reports as $schedule): ?>
        <div class="report-item">
            <div class="report-info">
                <div class="report-name">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $schedule['report_type']))) ?>
                </div>
                <div class="report-meta">
                    <?= ucfirst($schedule['frequency']) ?> | Format: <?= strtoupper($schedule['format']) ?>
                    | Next run: <?= $schedule['next_run'] ? date('M j, Y g:i A', strtotime($schedule['next_run'])) : 'Not scheduled' ?>
                </div>
            </div>
            <div class="report-actions">
                <button class="icon-btn" onclick="toggleSchedule(<?= $schedule['id'] ?>, 0)" title="Pause">
                    <i class="fas fa-pause"></i>
                </button>
                <button class="icon-btn" onclick="deleteSchedule(<?= $schedule['id'] ?>)" title="Delete">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const reportTypeCards = document.querySelectorAll('.report-type-card');
    const reportFilters = document.getElementById('reportFilters');
    const selectedReportType = document.getElementById('selected_report_type');
    const athleteFilter = document.getElementById('athleteFilter');
    const teamFilter = document.getElementById('teamFilter');
    const scheduleCheckbox = document.getElementById('schedule_report');
    const scheduleOptions = document.getElementById('scheduleOptions');
    const formatOptions = document.querySelectorAll('.format-option');
    const selectedFormat = document.getElementById('selected_format');
    
    // Report type selection
    reportTypeCards.forEach(card => {
        card.addEventListener('click', function() {
            reportTypeCards.forEach(c => c.classList.remove('selected'));
            this.classList.add('selected');
            
            const reportType = this.dataset.type;
            selectedReportType.value = reportType;
            reportFilters.style.display = 'block';
            
            // Show/hide filters based on report type
            athleteFilter.style.display = reportType === 'athlete_progress' ? 'block' : 'none';
            teamFilter.style.display = ['team_roster', 'session_attendance'].includes(reportType) ? 'block' : 'none';
        });
    });
    
    // Format selection
    formatOptions.forEach(option => {
        option.addEventListener('click', function() {
            formatOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            selectedFormat.value = this.dataset.format;
        });
    });
    
    // Schedule toggle
    scheduleCheckbox.addEventListener('change', function() {
        scheduleOptions.style.display = this.checked ? 'block' : 'none';
    });
});

function resetForm() {
    document.getElementById('generateReportForm').reset();
    document.querySelectorAll('.report-type-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.format-option').forEach(o => o.classList.remove('selected'));
    document.querySelector('.format-option[data-format="pdf"]').classList.add('selected');
    document.getElementById('reportFilters').style.display = 'none';
    document.getElementById('scheduleOptions').style.display = 'none';
}

function copyShareLink(token) {
    const url = window.location.origin + '/dashboard.php?page=report_view&token=' + token;
    navigator.clipboard.writeText(url).then(() => {
        alert('Share link copied to clipboard!');
    });
}

function deleteReport(reportId) {
    if (confirm('Are you sure you want to delete this report?')) {
        fetch('process_reports.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete&report_id=' + reportId + '&csrf_token=<?= $csrf_token ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function deleteSchedule(scheduleId) {
    if (confirm('Are you sure you want to delete this scheduled report?')) {
        fetch('process_reports.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=delete_schedule&schedule_id=' + scheduleId + '&csrf_token=<?= $csrf_token ?>'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        });
    }
}

function toggleSchedule(scheduleId, status) {
    fetch('process_reports.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=toggle_schedule&schedule_id=' + scheduleId + '&status=' + status + '&csrf_token=<?= $csrf_token ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}
</script>
