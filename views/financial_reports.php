<?php
/**
 * Financial Reports Hub - Combined Reports, Schedules & History
 * Unified interface for all financial reporting needs
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/image_helper.php';

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

$csrf_token = generateCsrfToken();

// Get current tab
$current_tab = $_GET['tab'] ?? 'reports';

// Report type configuration
$report_types = [
    'Financial Reports' => [
        'revenue_summary' => ['name' => 'Revenue Summary', 'icon' => 'fa-dollar-sign', 'color' => '#10b981', 'desc' => 'Total revenue breakdown by source and period'],
        'expense_report' => ['name' => 'Expense Report', 'icon' => 'fa-receipt', 'color' => '#ef4444', 'desc' => 'Complete expense tracking and categorization'],
        'profit_loss' => ['name' => 'Profit & Loss', 'icon' => 'fa-chart-line', 'color' => '#8B5CF6', 'desc' => 'Net income statement with comparisons'],
        'tax_summary' => ['name' => 'Tax Report', 'icon' => 'fa-calculator', 'color' => '#f59e0b', 'desc' => 'Tax-ready financial documentation'],
    ],
    'Performance Analytics' => [
        'session_analytics' => ['name' => 'Session Performance', 'icon' => 'fa-calendar-check', 'color' => '#3B82F6', 'desc' => 'Session attendance, revenue, and trends'],
        'package_performance' => ['name' => 'Package Analytics', 'icon' => 'fa-box', 'color' => '#06b6d4', 'desc' => 'Package sales and utilization metrics'],
        'client_billing' => ['name' => 'Client Billing', 'icon' => 'fa-users', 'color' => '#ec4899', 'desc' => 'Client billing history and balances'],
        'coach_payments' => ['name' => 'Coach Payments', 'icon' => 'fa-user-tie', 'color' => '#14b8a6', 'desc' => 'Coach session and payment summaries'],
    ],
    'Transaction Reports' => [
        'stripe_transactions' => ['name' => 'Stripe Transactions', 'icon' => 'fa-credit-card', 'color' => '#6366f1', 'desc' => 'All Stripe payment transactions'],
        'monthly_revenue' => ['name' => 'Monthly Revenue', 'icon' => 'fa-chart-bar', 'color' => '#22c55e', 'desc' => 'Month-by-month revenue breakdown'],
    ],
];

// Date filter options
$date_filters = [
    'today' => 'Today',
    'yesterday' => 'Yesterday',
    'this_week' => 'This Week',
    'last_week' => 'Last Week',
    'this_month' => 'This Month',
    'last_month' => 'Last Month',
    'this_quarter' => 'This Quarter',
    'last_quarter' => 'Last Quarter',
    'this_year' => 'This Year',
    'last_year' => 'Last Year',
    'custom' => 'Custom Range',
    'year_comparison' => 'Year vs Year',
];

// Frequency options
$frequency_options = [
    'daily' => 'Daily',
    'weekly' => 'Weekly',
    'monthly' => 'Monthly',
    'quarterly' => 'Quarterly',
    'annually' => 'Annually',
];

// Fetch user's scheduled reports
$stmt = $pdo->prepare("
    SELECT rs.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM reports r WHERE r.report_type = rs.report_type AND r.generated_by = rs.created_by) as run_count
    FROM report_schedules rs
    LEFT JOIN users u ON rs.created_by = u.id
    WHERE rs.created_by = ?
    ORDER BY rs.is_active DESC, rs.next_run ASC
");
$stmt->execute([$user_id]);
$scheduled_reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
$scheduled_reports = decryptUserRows($scheduled_reports);

// Calculate schedule stats
$active_schedules = count(array_filter($scheduled_reports, fn($s) => $s['is_active'] == 1));
$paused_schedules = count($scheduled_reports) - $active_schedules;

// Fetch report history
$stmt = $pdo->prepare("
    SELECT r.*, u.first_name, u.last_name
    FROM reports r
    LEFT JOIN users u ON r.generated_by = u.id
    ORDER BY r.generated_at DESC
    LIMIT 100
");
$stmt->execute();
$report_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
$report_history = decryptUserRows($report_history);

// Find next scheduled report
$next_scheduled = null;
foreach ($scheduled_reports as $schedule) {
    if ($schedule['is_active'] && $schedule['next_run']) {
        $next_scheduled = $schedule;
        break;
    }
}
?>

<style>
/* Tabs Navigation */
.reports-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.reports-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; }
.reports-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.reports-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.reports-tab i { font-size: 16px; }
.reports-tab .tab-badge { background: var(--primary); color: #fff; font-size: 11px; padding: 2px 8px; border-radius: 10px; font-weight: 700; }

/* Tab Content */
.tab-content { display: none; animation: fadeIn 0.3s ease; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

/* Stats Row */
.stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
.stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; display: flex; align-items: center; gap: 16px; transition: all 0.3s; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
.stat-icon { width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 20px; }
.stat-icon.green { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.stat-icon.yellow { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.stat-icon.blue { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.stat-icon.purple { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }
.stat-info h4 { font-size: 24px; font-weight: 800; color: var(--text-white); margin: 0; }
.stat-info p { font-size: 12px; color: var(--text-dim); margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 0.5px; }

/* Report Generator */
.report-generator { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }
.section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.section-title { font-size: 18px; font-weight: 700; color: var(--text-white); display: flex; align-items: center; gap: 10px; }
.section-title i { color: var(--primary); }

/* Report Type Grid */
.report-types-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 24px; }
.report-type-group { margin-bottom: 24px; }
.report-type-group h4 { font-size: 13px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border); }
.report-type-card { background: var(--bg-main); border: 2px solid var(--border); border-radius: 10px; padding: 16px; cursor: pointer; transition: all 0.3s; display: flex; align-items: flex-start; gap: 14px; }
.report-type-card:hover { border-color: var(--primary); background: rgba(139, 92, 246, 0.05); }
.report-type-card.selected { border-color: var(--primary); background: rgba(139, 92, 246, 0.1); }
.report-type-card input[type="radio"] { display: none; }
.report-type-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.report-type-info h5 { font-size: 14px; font-weight: 700; color: var(--text-white); margin: 0 0 4px 0; }
.report-type-info p { font-size: 12px; color: var(--text-dim); margin: 0; line-height: 1.4; }

/* Filter Section */
.filter-section { background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 24px; }
.filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.filter-group { display: flex; flex-direction: column; gap: 8px; }
.filter-label { font-size: 12px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; }
.filter-label i { color: var(--primary); }
.filter-input { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; color: var(--text-white); font-size: 14px; transition: border-color 0.3s; }
.filter-input:focus { outline: none; border-color: var(--primary); }

/* Custom Date Range */
.custom-date-row { display: none; margin-top: 16px; }
.custom-date-row.show { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.year-comparison-row { display: none; margin-top: 16px; }
.year-comparison-row.show { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

/* Export Options */
.export-options { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 24px; }
.export-option { display: flex; align-items: center; gap: 10px; background: var(--bg-main); border: 2px solid var(--border); border-radius: 8px; padding: 14px 20px; cursor: pointer; transition: all 0.3s; }
.export-option:hover { border-color: var(--primary); }
.export-option.selected { border-color: var(--primary); background: rgba(139, 92, 246, 0.1); }
.export-option input { display: none; }
.export-option i { font-size: 18px; }
.export-option.pdf i { color: #ef4444; }
.export-option.excel i { color: #10b981; }
.export-option.csv i { color: #3B82F6; }
.export-option span { font-size: 14px; font-weight: 600; color: var(--text-white); }

/* Additional Options */
.additional-options { display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 24px; }
.option-checkbox { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.option-checkbox input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); }
.option-checkbox span { font-size: 14px; color: var(--text-white); }

/* Schedule Section */
.schedule-section { background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 20px; margin-bottom: 24px; }
.schedule-toggle { display: flex; align-items: center; gap: 12px; margin-bottom: 16px; }
.schedule-toggle label { font-size: 14px; font-weight: 600; color: var(--text-white); cursor: pointer; }
.schedule-fields { display: none; }
.schedule-fields.show { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }

/* Action Buttons */
.action-buttons { display: flex; gap: 12px; justify-content: flex-end; padding-top: 20px; border-top: 1px solid var(--border); }

/* Schedules List */
.schedules-list { display: flex; flex-direction: column; gap: 12px; }
.schedule-item { background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 20px; display: flex; align-items: flex-start; gap: 16px; transition: all 0.3s; }
.schedule-item:hover { border-color: var(--primary); }
.schedule-item.paused { opacity: 0.6; }
.schedule-status-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.schedule-status-icon.active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.schedule-status-icon.paused { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.schedule-details { flex: 1; }
.schedule-details h4 { font-size: 16px; font-weight: 700; color: var(--text-white); margin: 0 0 8px 0; }
.schedule-meta { display: flex; flex-wrap: wrap; gap: 16px; font-size: 13px; color: var(--text-dim); }
.schedule-meta span { display: flex; align-items: center; gap: 6px; }
.schedule-meta i { color: var(--primary); }
.schedule-next { font-size: 13px; color: var(--text-dim); margin-top: 8px; }
.schedule-actions { display: flex; gap: 8px; }

/* History List */
.history-list { display: flex; flex-direction: column; gap: 10px; }
.history-item { background: var(--bg-main); border: 1px solid var(--border); border-radius: 10px; padding: 16px; display: flex; align-items: center; gap: 16px; transition: all 0.3s; }
.history-item:hover { border-color: var(--primary); }
.history-icon { width: 44px; height: 44px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.history-icon.pdf { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.history-icon.excel { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.history-icon.csv { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.history-details { flex: 1; }
.history-details h4 { font-size: 14px; font-weight: 700; color: var(--text-white); margin: 0 0 4px 0; }
.history-details .history-meta { font-size: 12px; color: var(--text-dim); }
.history-user { font-size: 12px; color: var(--primary); }
.history-actions { display: flex; gap: 8px; }

/* Modal Styles */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.8); z-index: 10000; justify-content: center; align-items: center; padding: 20px; }
.modal-overlay.show { display: flex; }
.modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; width: 100%; max-width: 600px; max-height: 90vh; overflow-y: auto; }
.modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.modal-header h3 { font-size: 18px; font-weight: 700; color: var(--text-white); margin: 0; }
.modal-close { background: none; border: none; color: var(--text-dim); font-size: 24px; cursor: pointer; transition: color 0.3s; }
.modal-close:hover { color: var(--text-white); }
.modal-body { padding: 24px; }
.modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }

/* Empty State */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state i { font-size: 64px; color: var(--border); margin-bottom: 20px; }
.empty-state h3 { font-size: 18px; font-weight: 700; color: var(--text-white); margin-bottom: 8px; }
.empty-state p { font-size: 14px; color: var(--text-dim); }

@media (max-width: 768px) {
    .reports-tabs { flex-direction: column; }
    .reports-tab { justify-content: flex-start; }
    .filter-row, .custom-date-row.show, .year-comparison-row.show { grid-template-columns: 1fr; }
    .export-options { flex-direction: column; }
    .schedule-item { flex-direction: column; }
    .action-buttons { flex-direction: column; }
}
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-pie"></i> Financial Reports Hub
    </h1>
    <p class="page-description">Generate, schedule, and track all financial reports</p>
</div>

<!-- Tabs Navigation -->
<div class="reports-tabs">
    <button class="reports-tab <?= $current_tab === 'reports' ? 'active' : '' ?>" onclick="switchTab('reports')">
        <i class="fas fa-chart-bar"></i> Generate Reports
    </button>
    <button class="reports-tab <?= $current_tab === 'schedules' ? 'active' : '' ?>" onclick="switchTab('schedules')">
        <i class="fas fa-clock"></i> Schedules
        <?php if ($active_schedules > 0): ?>
        <span class="tab-badge"><?= $active_schedules ?></span>
        <?php endif; ?>
    </button>
    <button class="reports-tab <?= $current_tab === 'history' ? 'active' : '' ?>" onclick="switchTab('history')">
        <i class="fas fa-history"></i> History
    </button>
</div>

<!-- TAB 1: Generate Reports -->
<div id="tab-reports" class="tab-content <?= $current_tab === 'reports' ? 'active' : '' ?>">
    <div class="report-generator">
        <form id="reportForm" method="POST" action="process_reports.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="generate_report">
            
            <!-- Report Type Selection -->
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-file-alt"></i> Select Report Type</h3>
            </div>
            
            <?php foreach ($report_types as $group_name => $types): ?>
            <div class="report-type-group">
                <h4><?= htmlspecialchars($group_name) ?></h4>
                <div class="report-types-grid">
                    <?php foreach ($types as $type_key => $type_info): ?>
                    <label class="report-type-card" data-type="<?= $type_key ?>">
                        <input type="radio" name="report_type" value="<?= $type_key ?>" required>
                        <div class="report-type-icon" style="background: <?= $type_info['color'] ?>20; color: <?= $type_info['color'] ?>;">
                            <i class="fas <?= $type_info['icon'] ?>"></i>
                        </div>
                        <div class="report-type-info">
                            <h5><?= htmlspecialchars($type_info['name']) ?></h5>
                            <p><?= htmlspecialchars($type_info['desc']) ?></p>
                        </div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Date Filters -->
            <div class="filter-section">
                <div class="section-header">
                    <h3 class="section-title"><i class="fas fa-calendar"></i> Date Range</h3>
                </div>
                <div class="filter-row">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-filter"></i> Quick Filter</label>
                        <select name="date_range" id="dateRangeSelect" class="filter-input" required>
                            <?php foreach ($date_filters as $value => $label): ?>
                            <option value="<?= $value ?>" <?= $value === 'this_month' ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="custom-date-row" id="customDateRow">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar-alt"></i> Start Date</label>
                        <input type="date" name="date_from" id="dateFrom" class="filter-input" value="<?= date('Y-m-01') ?>">
                    </div>
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar-alt"></i> End Date</label>
                        <input type="date" name="date_to" id="dateTo" class="filter-input" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                
                <div class="year-comparison-row" id="yearComparisonRow">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar"></i> Compare Year</label>
                        <select name="compare_year_1" class="filter-input">
                            <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-calendar"></i> With Year</label>
                        <select name="compare_year_2" class="filter-input">
                            <?php for ($y = date('Y') - 1; $y >= date('Y') - 5; $y--): ?>
                            <option value="<?= $y ?>"><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Export Format -->
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-download"></i> Export Format</h3>
            </div>
            <div class="export-options">
                <label class="export-option pdf selected">
                    <input type="radio" name="format" value="pdf" checked>
                    <i class="fas fa-file-pdf"></i>
                    <span>PDF Document</span>
                </label>
                <label class="export-option excel">
                    <input type="radio" name="format" value="excel">
                    <i class="fas fa-file-excel"></i>
                    <span>Excel Spreadsheet</span>
                </label>
                <label class="export-option csv">
                    <input type="radio" name="format" value="csv">
                    <i class="fas fa-file-csv"></i>
                    <span>CSV File</span>
                </label>
            </div>
            
            <!-- Additional Options -->
            <div class="section-header">
                <h3 class="section-title"><i class="fas fa-cog"></i> Additional Options</h3>
            </div>
            <div class="additional-options">
                <label class="option-checkbox">
                    <input type="checkbox" name="detailed_breakdown" value="1">
                    <span>Include detailed breakdown</span>
                </label>
                <label class="option-checkbox">
                    <input type="checkbox" name="show_charts" value="1">
                    <span>Include charts and graphs</span>
                </label>
                <label class="option-checkbox">
                    <input type="checkbox" name="compare_previous" value="1">
                    <span>Compare with previous period</span>
                </label>
            </div>
            
            <!-- Schedule Option -->
            <div class="schedule-section">
                <div class="schedule-toggle">
                    <input type="checkbox" id="enableSchedule" name="schedule" value="1">
                    <label for="enableSchedule"><i class="fas fa-clock"></i> Schedule this report for recurring delivery</label>
                </div>
                <div class="schedule-fields" id="scheduleFields">
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-sync"></i> Frequency</label>
                        <select name="frequency" class="filter-input">
                            <?php foreach ($frequency_options as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label"><i class="fas fa-clock"></i> Time</label>
                        <input type="time" name="schedule_time" class="filter-input" value="09:00">
                    </div>
                    <div class="filter-group" style="grid-column: 1 / -1;">
                        <label class="filter-label"><i class="fas fa-envelope"></i> Email Recipients</label>
                        <input type="text" name="email_recipients" class="filter-input" placeholder="email1@example.com, email2@example.com">
                        <small style="color: var(--text-dim); font-size: 12px; margin-top: 4px;">Separate multiple emails with commas</small>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button type="reset" class="btn-secondary">
                    <i class="fas fa-redo"></i> Reset
                </button>
                <button type="submit" class="btn-primary">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- TAB 2: Schedules -->
<div id="tab-schedules" class="tab-content <?= $current_tab === 'schedules' ? 'active' : '' ?>">
    <div class="report-generator">
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
                <div class="stat-info">
                    <h4><?= $active_schedules ?></h4>
                    <p>Active Schedules</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon yellow"><i class="fas fa-pause-circle"></i></div>
                <div class="stat-info">
                    <h4><?= $paused_schedules ?></h4>
                    <p>Paused</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue"><i class="fas fa-paper-plane"></i></div>
                <div class="stat-info">
                    <h4><?= count($report_history) ?></h4>
                    <p>Reports Generated</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple"><i class="fas fa-calendar-day"></i></div>
                <div class="stat-info">
                    <h4><?= $next_scheduled ? date('M j', strtotime($next_scheduled['next_run'])) : 'N/A' ?></h4>
                    <p>Next Scheduled</p>
                </div>
            </div>
        </div>
        
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-calendar-check"></i> Scheduled Reports</h3>
            <button class="btn-secondary btn-small" onclick="switchTab('reports')">
                <i class="fas fa-plus"></i> New Schedule
            </button>
        </div>
        
        <div class="schedules-list">
            <?php if (empty($scheduled_reports)): ?>
            <div class="empty-state">
                <i class="fas fa-calendar-times"></i>
                <h3>No Scheduled Reports</h3>
                <p>Create your first scheduled report from the Reports tab</p>
            </div>
            <?php else: ?>
                <?php foreach ($scheduled_reports as $schedule): 
                    $isActive = $schedule['is_active'] == 1;
                    $recipientCount = !empty($schedule['recipients']) ? count(explode(',', $schedule['recipients'])) : 0;
                    $reportTypeInfo = null;
                    foreach ($report_types as $group => $types) {
                        if (isset($types[$schedule['report_type']])) {
                            $reportTypeInfo = $types[$schedule['report_type']];
                            break;
                        }
                    }
                ?>
                <div class="schedule-item <?= !$isActive ? 'paused' : '' ?>" data-schedule-id="<?= $schedule['id'] ?>">
                    <div class="schedule-status-icon <?= $isActive ? 'active' : 'paused' ?>">
                        <i class="fas fa-<?= $isActive ? 'check' : 'pause' ?>-circle"></i>
                    </div>
                    <div class="schedule-details">
                        <h4><?= htmlspecialchars($schedule['report_name'] ?? ucwords(str_replace('_', ' ', $schedule['report_type']))) ?></h4>
                        <div class="schedule-meta">
                            <span><i class="fas fa-file-alt"></i> <?= htmlspecialchars($reportTypeInfo['name'] ?? ucwords(str_replace('_', ' ', $schedule['report_type']))) ?></span>
                            <span><i class="fas fa-sync"></i> <?= ucfirst($schedule['schedule_frequency'] ?? 'Weekly') ?></span>
                            <span><i class="fas fa-clock"></i> <?= $schedule['schedule_time'] ?? '09:00' ?></span>
                            <?php if ($recipientCount > 0): ?>
                            <span><i class="fas fa-envelope"></i> <?= $recipientCount ?> recipient<?= $recipientCount > 1 ? 's' : '' ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="schedule-next">
                            <?php if ($isActive && $schedule['next_run']): ?>
                            <strong>Next run:</strong> <?= date('M j, Y \a\t g:i A', strtotime($schedule['next_run'])) ?>
                            <?php else: ?>
                            <strong>Status:</strong> Paused
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="schedule-actions">
                        <button class="btn-icon" onclick="editSchedule(<?= htmlspecialchars(json_encode($schedule)) ?>)" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon <?= $isActive ? 'warning' : 'success' ?>" 
                                onclick="toggleSchedule(<?= $schedule['id'] ?>, <?= $isActive ? 0 : 1 ?>)" 
                                title="<?= $isActive ? 'Pause' : 'Resume' ?>">
                            <i class="fas fa-<?= $isActive ? 'pause' : 'play' ?>"></i>
                        </button>
                        <button class="btn-icon danger" onclick="deleteSchedule(<?= $schedule['id'] ?>, <?= json_encode($schedule['report_name'] ?? '') ?>)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TAB 3: History -->
<div id="tab-history" class="tab-content <?= $current_tab === 'history' ? 'active' : '' ?>">
    <div class="report-generator">
        <div class="section-header">
            <h3 class="section-title"><i class="fas fa-history"></i> Report History</h3>
            <span style="color: var(--text-dim); font-size: 13px;"><?= count($report_history) ?> reports</span>
        </div>
        
        <div class="history-list">
            <?php if (empty($report_history)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Reports Generated Yet</h3>
                <p>Generate your first report from the Reports tab</p>
            </div>
            <?php else: ?>
                <?php foreach ($report_history as $report): 
                    $fileExt = pathinfo($report['file_path'] ?? '', PATHINFO_EXTENSION);
                    if ($fileExt === 'html') $fileExt = 'pdf';
                    $iconClass = in_array($fileExt, ['xlsx', 'xls']) ? 'excel' : ($fileExt === 'csv' ? 'csv' : 'pdf');
                ?>
                <div class="history-item">
                    <div class="history-icon <?= $iconClass ?>">
                        <i class="fas fa-file-<?= $iconClass === 'csv' ? 'csv' : ($iconClass === 'excel' ? 'excel' : 'pdf') ?>"></i>
                    </div>
                    <div class="history-details">
                        <h4><?= htmlspecialchars($report['report_name'] ?? ucwords(str_replace('_', ' ', $report['report_type']))) ?></h4>
                        <span class="history-meta">
                            Generated on <?= date('M j, Y \a\t g:i A', strtotime($report['generated_at'])) ?>
                        </span>
                        <span class="history-user">
                            by <?= htmlspecialchars(($report['first_name'] ?? '') . ' ' . ($report['last_name'] ?? '')) ?>
                        </span>
                    </div>
                    <div class="history-actions">
                        <?php if (!empty($report['file_path'])): ?>
                        <a href="<?= htmlspecialchars(resolveRustfsUrl($pdo, $report['file_path'])) ?>" download class="btn-icon" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <a href="<?= htmlspecialchars(resolveRustfsUrl($pdo, $report['file_path'])) ?>" target="_blank" class="btn-icon" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php endif; ?>
                        <button class="btn-icon danger" onclick="deleteReport(<?= $report['id'] ?>)" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Schedule Modal -->
<div class="modal-overlay" id="editScheduleModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Schedule</h3>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal()">&times;</button>
        </div>
        <form id="editScheduleForm" method="POST" action="process_reports.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="action" value="schedule_update">
            <input type="hidden" name="schedule_id" id="editScheduleId">
            
            <div class="modal-body">
                <div class="filter-group" style="margin-bottom: 16px;">
                    <label class="filter-label">Schedule Name</label>
                    <input type="text" name="schedule_name" id="editScheduleName" class="filter-input" required>
                </div>
                
                <div class="filter-group" style="margin-bottom: 16px;">
                    <label class="filter-label">Report Type</label>
                    <select name="report_type" id="editReportType" class="filter-input" required>
                        <?php foreach ($report_types as $group => $types): ?>
                        <optgroup label="<?= htmlspecialchars($group) ?>">
                            <?php foreach ($types as $key => $info): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($info['name']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                    <div class="filter-group">
                        <label class="filter-label">Frequency</label>
                        <select name="frequency" id="editFrequency" class="filter-input" required>
                            <?php foreach ($frequency_options as $value => $label): ?>
                            <option value="<?= $value ?>"><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Time</label>
                        <input type="time" name="time" id="editTime" class="filter-input" value="09:00">
                    </div>
                </div>
                
                <div class="filter-group" style="margin-bottom: 16px;">
                    <label class="filter-label">Format</label>
                    <select name="format" id="editFormat" class="filter-input">
                        <option value="pdf">PDF</option>
                        <option value="excel">Excel</option>
                        <option value="csv">CSV</option>
                    </select>
                </div>
                
                <div class="filter-group" style="margin-bottom: 16px;">
                    <label class="filter-label">Email Recipients</label>
                    <input type="text" name="email_recipients" id="editRecipients" class="filter-input" placeholder="email@example.com">
                </div>
                
                <div class="filter-group">
                    <label class="option-checkbox">
                        <input type="checkbox" name="is_active" id="editIsActive" value="1">
                        <span>Schedule is active</span>
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
var csrfToken = '<?= htmlspecialchars($csrf_token) ?>';

// Tab switching
function switchTab(tab) {
    history.pushState({}, '', '?page=financial_reports&tab=' + tab);
    document.querySelectorAll('.reports-tab').forEach(btn => btn.classList.remove('active'));
    event.target.closest('.reports-tab').classList.add('active');
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    document.getElementById('tab-' + tab).classList.add('active');
}

// Report type selection
document.querySelectorAll('.report-type-card').forEach(card => {
    card.addEventListener('click', function() {
        document.querySelectorAll('.report-type-card').forEach(c => c.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input[type="radio"]').checked = true;
    });
});

// Export format selection
document.querySelectorAll('.export-option').forEach(option => {
    option.addEventListener('click', function() {
        document.querySelectorAll('.export-option').forEach(o => o.classList.remove('selected'));
        this.classList.add('selected');
        this.querySelector('input[type="radio"]').checked = true;
    });
});

// Date range toggle
document.getElementById('dateRangeSelect').addEventListener('change', function() {
    var customRow = document.getElementById('customDateRow');
    var yearRow = document.getElementById('yearComparisonRow');
    customRow.classList.remove('show');
    yearRow.classList.remove('show');
    if (this.value === 'custom') {
        customRow.classList.add('show');
    } else if (this.value === 'year_comparison') {
        yearRow.classList.add('show');
    }
});

// Schedule toggle
document.getElementById('enableSchedule').addEventListener('change', function() {
    document.getElementById('scheduleFields').classList.toggle('show', this.checked);
});

// Edit schedule
function editSchedule(schedule) {
    document.getElementById('editScheduleId').value = schedule.id;
    document.getElementById('editScheduleName').value = schedule.report_name || '';
    document.getElementById('editReportType').value = schedule.report_type || '';
    document.getElementById('editFrequency').value = schedule.schedule_frequency || 'weekly';
    document.getElementById('editTime').value = schedule.schedule_time || '09:00';
    document.getElementById('editFormat').value = schedule.format || 'pdf';
    document.getElementById('editRecipients').value = schedule.recipients || '';
    document.getElementById('editIsActive').checked = schedule.is_active == 1;
    document.getElementById('editScheduleModal').classList.add('show');
}

function closeModal() {
    document.getElementById('editScheduleModal').classList.remove('show');
}

// Toggle schedule
async function toggleSchedule(scheduleId, status) {
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'schedule_toggle');
    formData.append('schedule_id', scheduleId);
    formData.append('is_active', status);
    
    try {
        var response = await fetch('process_reports.php', { method: 'POST', body: formData });
        var result = await response.json();
        if (result.success) {
            persistToast('Schedule updated successfully', 'success');
            location.reload();
        } else {
            showNotification(result.message || 'Failed to update schedule', 'error');
        }
    } catch (error) {
        showNotification('An error occurred', 'error');
    }
}

// Delete schedule
async function deleteSchedule(scheduleId, name) {
    if (!await showConfirmModal('Are you sure you want to delete "' + name + '"?\n\nThis action cannot be undone.')) return;
    
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'schedule_delete');
    formData.append('schedule_id', scheduleId);
    
    try {
        var response = await fetch('process_reports.php', { method: 'POST', body: formData });
        var result = await response.json();
        if (result.success) {
            persistToast('Schedule deleted successfully', 'success');
            location.reload();
        } else {
            showNotification(result.message || 'Failed to delete schedule', 'error');
        }
    } catch (error) {
        showNotification('An error occurred', 'error');
    }
}

// Delete report
async function deleteReport(reportId) {
    if (!await showConfirmModal('Are you sure you want to delete this report?')) return;
    
    var formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'delete_report');
    formData.append('report_id', reportId);
    
    try {
        var response = await fetch('process_reports.php', { method: 'POST', body: formData });
        var result = await response.json();
        if (result.success) {
            persistToast('Report deleted successfully', 'success');
            location.reload();
        } else {
            showNotification(result.message || 'Failed to delete report', 'error');
        }
    } catch (error) {
        showNotification('An error occurred', 'error');
    }
}

// Edit schedule form submission
document.getElementById('editScheduleForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    
    try {
        var response = await fetch('process_reports.php', { method: 'POST', body: formData });
        var result = await response.json();
        if (result.success) {
            persistToast('Schedule updated successfully', 'success');
            closeModal();
            location.reload();
        } else {
            showNotification(result.message || 'Failed to update schedule', 'error');
        }
    } catch (error) {
        showNotification('An error occurred', 'error');
    }
});


// Notification helper
function showNotification(message, type) {
    var existing = document.querySelector('.notification-widget');
    if (existing) existing.remove();
    
    var div = document.createElement('div');
    div.className = 'notification-widget';
    div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10001; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
    
    if (type === 'success') {
        div.style.background = 'rgba(16, 185, 129, 0.95)';
        div.style.color = '#fff';
    } else {
        div.style.background = 'rgba(239, 68, 68, 0.95)';
        div.style.color = '#fff';
    }
    
    var textSpan = document.createElement('span');
    textSpan.textContent = message;
    div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ';
    div.appendChild(textSpan);
    
    var closeBtn = document.createElement('button');
    closeBtn.innerHTML = '&times;';
    closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
    closeBtn.onclick = function() { div.remove(); };
    div.appendChild(closeBtn);
    
    document.body.appendChild(div);
    setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
}
</script>
