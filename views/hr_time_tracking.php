<?php
/**
 * HR Time Tracking & Reports View
 * Admin view for time tracking reports and payroll integration
 */

// Check access - admins only
if (!isset($_SESSION['user_id']) || !$isAdmin) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Get active tab
$activeTab = $_GET['tab'] ?? 'overview';

// Fetch all front desk staff for selection
$staffMembers = [];
try {
    $staffStmt = $pdo->query("
        SELECT u.id, u.first_name, u.last_name, u.email,
               (SELECT COUNT(*) FROM staff_shifts ss WHERE ss.staff_id = u.id) as total_shifts,
               (SELECT COALESCE(SUM(total_hours), 0) FROM staff_shifts ss WHERE ss.staff_id = u.id AND ss.status = 'completed') as total_hours
        FROM users u
        WHERE u.role = 'front_desk_staff' AND u.is_active = 1
        ORDER BY u.first_name, u.last_name
    ");
    $staffMembers = $staffStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Time tracking staff fetch error: " . $e->getMessage());
}

// Get selected staff ID (default to first staff or 'all')
$selectedStaffId = $_GET['staff_id'] ?? 'all';
$selectedPeriod = $_GET['period'] ?? 'month';

// Calculate date ranges for different periods
$today = new DateTime();
$dateRanges = [
    'day' => [
        'start' => $today->format('Y-m-d'),
        'end' => $today->format('Y-m-d'),
        'label' => 'Today (' . $today->format('M j, Y') . ')'
    ],
    'week' => [
        'start' => (clone $today)->modify('monday this week')->format('Y-m-d'),
        'end' => (clone $today)->modify('sunday this week')->format('Y-m-d'),
        'label' => 'This Week'
    ],
    'pay_period' => [
        'start' => $today->format('d') <= 15 
            ? $today->format('Y-m-01') 
            : $today->format('Y-m-16'),
        'end' => $today->format('d') <= 15 
            ? $today->format('Y-m-15')
            : $today->format('Y-m-t'),
        'label' => 'Current Pay Period'
    ],
    'month' => [
        'start' => $today->format('Y-m-01'),
        'end' => $today->format('Y-m-t'),
        'label' => $today->format('F Y')
    ],
    'year' => [
        'start' => $today->format('Y-01-01'),
        'end' => $today->format('Y-12-31'),
        'label' => $today->format('Y')
    ],
    'last_year' => [
        'start' => ((int)$today->format('Y') - 1) . '-01-01',
        'end' => ((int)$today->format('Y') - 1) . '-12-31',
        'label' => (int)$today->format('Y') - 1
    ]
];

$currentRange = $dateRanges[$selectedPeriod] ?? $dateRanges['month'];

// Fetch report data based on selection
$reportData = [];
$summaryData = [];
try {
    // Build query based on staff selection
    $staffCondition = $selectedStaffId === 'all' ? '' : 'AND ss.staff_id = :staff_id';
    
    $reportQuery = "
        SELECT ss.*, u.first_name, u.last_name
        FROM staff_shifts ss
        JOIN users u ON ss.staff_id = u.id
        WHERE ss.shift_date BETWEEN :start_date AND :end_date
        AND ss.status = 'completed'
        $staffCondition
        ORDER BY ss.shift_date DESC, u.last_name, u.first_name
    ";
    
    $reportStmt = $pdo->prepare($reportQuery);
    $params = [
        ':start_date' => $currentRange['start'],
        ':end_date' => $currentRange['end']
    ];
    if ($selectedStaffId !== 'all') {
        $params[':staff_id'] = $selectedStaffId;
    }
    $reportStmt->execute($params);
    $reportData = $reportStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summary
    $summaryQuery = "
        SELECT 
            u.id as staff_id,
            u.first_name,
            u.last_name,
            COUNT(ss.id) as shift_count,
            COALESCE(SUM(ss.total_hours), 0) as total_hours,
            COUNT(CASE WHEN ss.lunch_start IS NOT NULL AND ss.lunch_end IS NOT NULL THEN 1 END) as lunch_breaks_taken
        FROM users u
        LEFT JOIN staff_shifts ss ON u.id = ss.staff_id 
            AND ss.shift_date BETWEEN :start_date AND :end_date
            AND ss.status = 'completed'
        WHERE u.role = 'front_desk_staff' AND u.is_active = 1
        " . ($selectedStaffId !== 'all' ? 'AND u.id = :staff_id' : '') . "
        GROUP BY u.id, u.first_name, u.last_name
        ORDER BY u.last_name, u.first_name
    ";
    
    $summaryStmt = $pdo->prepare($summaryQuery);
    $summaryStmt->execute($params);
    $summaryData = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Time tracking report error: " . $e->getMessage());
}

// Calculate totals
$totalHours = array_sum(array_column($summaryData, 'total_hours'));
$totalShifts = array_sum(array_column($summaryData, 'shift_count'));
?>

<div class="page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-chart-line"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Time Tracking Reports</h1>
            <p class="page-description">View and generate time tracking reports for payroll integration</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= number_format($totalHours, 1) ?></span>
            <span class="stat-label">Total Hours</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= $totalShifts ?></span>
            <span class="stat-label">Total Shifts</span>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="tab-navigation">
    <a href="?page=hr_time_tracking&tab=overview" class="tab-link <?= $activeTab === 'overview' ? 'active' : '' ?>">
        <i class="fas fa-tachometer-alt"></i> Overview
    </a>
    <a href="?page=hr_time_tracking&tab=reports" class="tab-link <?= $activeTab === 'reports' ? 'active' : '' ?>">
        <i class="fas fa-file-alt"></i> Generate Reports
    </a>
    <a href="?page=hr_time_tracking&tab=payroll" class="tab-link <?= $activeTab === 'payroll' ? 'active' : '' ?>">
        <i class="fas fa-money-check"></i> Payroll Integration
    </a>
</div>

<style>
    .report-filters {
        display: flex;
        gap: 20px;
        margin-bottom: 25px;
        flex-wrap: wrap;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    .filter-group label {
        font-size: 12px;
        font-weight: 600;
        color: var(--text-dim);
        text-transform: uppercase;
    }
    
    .filter-group select {
        padding: 10px 15px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
        min-width: 200px;
    }
    
    .filter-group select:focus {
        border-color: var(--primary);
        outline: none;
    }
    
    .period-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .period-btn {
        padding: 10px 16px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .period-btn:hover,
    .period-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .summary-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
    }
    
    .summary-card h4 {
        font-size: 14px;
        color: var(--text-dim);
        margin-bottom: 12px;
    }
    
    .summary-card .value {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    
    .summary-card .value.highlight {
        color: var(--primary);
    }
    
    .summary-card .subtext {
        font-size: 12px;
        color: var(--text-dim);
        margin-top: 4px;
    }
    
    .staff-summary-table {
        width: 100%;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        border-collapse: collapse;
    }
    
    .staff-summary-table th,
    .staff-summary-table td {
        padding: 14px 16px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }
    
    .staff-summary-table th {
        background: var(--bg);
        font-size: 12px;
        text-transform: uppercase;
        color: var(--text-dim);
        font-weight: 700;
    }
    
    .staff-summary-table tr:last-child td {
        border-bottom: none;
    }
    
    .staff-summary-table tr:hover {
        background: rgba(255, 255, 255, 0.02);
    }
    
    .staff-summary-table .hours-cell {
        font-weight: 700;
        color: var(--primary);
    }
    
    .report-actions {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .report-actions button {
        padding: 12px 20px;
        font-size: 14px;
    }
    
    .shift-details-table {
        width: 100%;
        background: var(--bg-secondary);
        border-radius: 12px;
        overflow: hidden;
        border-collapse: collapse;
        margin-top: 20px;
    }
    
    .shift-details-table th,
    .shift-details-table td {
        padding: 12px 14px;
        text-align: left;
        border-bottom: 1px solid var(--border);
    }
    
    .shift-details-table th {
        background: var(--bg);
        font-size: 11px;
        text-transform: uppercase;
        color: var(--text-dim);
        font-weight: 700;
    }
    
    .payroll-integration-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 20px;
    }
    
    .payroll-integration-card h3 {
        font-size: 18px;
        margin-bottom: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .payroll-integration-card h3 i {
        color: var(--primary);
    }
    
    .integration-form {
        display: grid;
        gap: 15px;
    }
    
    .integration-form .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .integration-form .form-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    
    .integration-form label {
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
    }
    
    .integration-form input,
    .integration-form select {
        padding: 10px 14px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        font-size: 14px;
    }
    
    .hours-preview {
        background: var(--bg);
        border-radius: 8px;
        padding: 20px;
        margin-top: 15px;
    }
    
    .hours-preview h4 {
        font-size: 14px;
        color: var(--text-dim);
        margin-bottom: 10px;
    }
    
    .hours-preview .total {
        font-size: 36px;
        font-weight: 900;
        color: var(--primary);
    }
    
    .hours-preview .breakdown {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid var(--border);
    }
    
    .hours-preview .breakdown-item {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        font-size: 13px;
    }
    
    .export-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .export-btn:hover {
        background: var(--primary-hover);
    }
    
    .export-btn.secondary {
        background: var(--bg);
        border: 1px solid var(--border);
    }
    
    .export-btn.secondary:hover {
        border-color: var(--primary);
    }
</style>

<?php if ($activeTab === 'overview'): ?>
<!-- Overview Tab -->
<div class="report-filters">
    <div class="filter-group">
        <label>Staff Member</label>
        <select id="staff-filter" onchange="applyFilters()">
            <option value="all" <?= $selectedStaffId === 'all' ? 'selected' : '' ?>>All Staff</option>
            <?php foreach ($staffMembers as $staff): ?>
                <option value="<?= $staff['id'] ?>" <?= $selectedStaffId == $staff['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label>Time Period</label>
        <div class="period-buttons">
            <?php foreach ($dateRanges as $key => $range): ?>
                <button class="period-btn <?= $selectedPeriod === $key ? 'active' : '' ?>" 
                        onclick="changePeriod('<?= $key ?>')">
                    <?= $range['label'] ?>
                </button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Summary Cards -->
<div class="summary-grid">
    <div class="summary-card">
        <h4>Total Hours Worked</h4>
        <div class="value highlight"><?= number_format($totalHours, 1) ?></div>
        <div class="subtext"><?= $currentRange['label'] ?></div>
    </div>
    <div class="summary-card">
        <h4>Total Shifts</h4>
        <div class="value"><?= $totalShifts ?></div>
        <div class="subtext">Completed shifts</div>
    </div>
    <div class="summary-card">
        <h4>Average Hours/Shift</h4>
        <div class="value"><?= $totalShifts > 0 ? number_format($totalHours / $totalShifts, 1) : '0' ?></div>
        <div class="subtext">Per completed shift</div>
    </div>
    <div class="summary-card">
        <h4>Staff Members</h4>
        <div class="value"><?= count(array_filter($summaryData, function($s) { return $s['shift_count'] > 0; })) ?></div>
        <div class="subtext">With shifts in period</div>
    </div>
</div>

<!-- Staff Summary Table -->
<h3 style="margin-bottom: 15px;"><i class="fas fa-users" style="color: var(--primary); margin-right: 10px;"></i> Staff Hours Summary</h3>
<table class="staff-summary-table">
    <thead>
        <tr>
            <th>Staff Member</th>
            <th>Shifts Worked</th>
            <th>Total Hours</th>
            <th>Avg Hours/Shift</th>
            <th>Lunch Breaks</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($summaryData)): ?>
            <tr>
                <td colspan="6" style="text-align: center; padding: 40px; color: var(--text-dim);">
                    No data available for selected period
                </td>
            </tr>
        <?php else: ?>
            <?php foreach ($summaryData as $summary): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($summary['first_name'] . ' ' . $summary['last_name']) ?></strong>
                    </td>
                    <td><?= $summary['shift_count'] ?></td>
                    <td class="hours-cell"><?= number_format($summary['total_hours'], 1) ?> hrs</td>
                    <td><?= $summary['shift_count'] > 0 ? number_format($summary['total_hours'] / $summary['shift_count'], 1) : '0' ?> hrs</td>
                    <td><?= $summary['lunch_breaks_taken'] ?></td>
                    <td>
                        <button class="btn-secondary btn-sm" onclick="viewStaffDetail(<?= $summary['staff_id'] ?>)">
                            <i class="fas fa-eye"></i> View Details
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
    </tbody>
</table>

<?php if (!empty($reportData)): ?>
<!-- Detailed Shift Table -->
<h3 style="margin: 30px 0 15px;"><i class="fas fa-clock" style="color: var(--primary); margin-right: 10px;"></i> Shift Details</h3>
<table class="shift-details-table">
    <thead>
        <tr>
            <th>Date</th>
            <th>Staff Member</th>
            <th>Clock In</th>
            <th>Clock Out</th>
            <th>Lunch Break</th>
            <th>Total Hours</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($reportData as $shift): ?>
            <tr>
                <td><?= date('M j, Y', strtotime($shift['shift_date'])) ?></td>
                <td><?= htmlspecialchars($shift['first_name'] . ' ' . $shift['last_name']) ?></td>
                <td><?= date('g:i A', strtotime($shift['clock_in'])) ?></td>
                <td><?= $shift['clock_out'] ? date('g:i A', strtotime($shift['clock_out'])) : '--' ?></td>
                <td>
                    <?php if ($shift['lunch_start'] && $shift['lunch_end']): 
                        $lunchStart = new DateTime($shift['lunch_start']);
                        $lunchEnd = new DateTime($shift['lunch_end']);
                        $lunchMins = ($lunchEnd->getTimestamp() - $lunchStart->getTimestamp()) / 60;
                    ?>
                        <?= round($lunchMins) ?> mins
                    <?php else: ?>
                        --
                    <?php endif; ?>
                </td>
                <td class="hours-cell"><?= number_format($shift['total_hours'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php elseif ($activeTab === 'reports'): ?>
<!-- Reports Tab -->
<div class="payroll-integration-card">
    <h3><i class="fas fa-file-export"></i> Generate Time Report</h3>
    <p style="color: var(--text-dim); margin-bottom: 20px;">Generate detailed time reports for individual staff members or all staff.</p>
    
    <form id="report-form" class="integration-form" onsubmit="generateReport(event)">
        <div class="form-row">
            <div class="form-group">
                <label>Staff Member</label>
                <select name="staff_id" id="report-staff">
                    <option value="all">All Staff</option>
                    <?php foreach ($staffMembers as $staff): ?>
                        <option value="<?= $staff['id'] ?>">
                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Report Period</label>
                <select name="period" id="report-period">
                    <option value="day">Today</option>
                    <option value="week">This Week</option>
                    <option value="pay_period">Current Pay Period</option>
                    <option value="month" selected>This Month</option>
                    <option value="year">This Year</option>
                    <option value="last_year">Last Year</option>
                    <option value="custom">Custom Date Range</option>
                </select>
            </div>
        </div>
        
        <div class="form-row" id="custom-dates" style="display: none;">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" name="start_date" id="report-start-date">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" name="end_date" id="report-end-date">
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Report Format</label>
                <select name="format" id="report-format">
                    <option value="view">View on Screen</option>
                    <option value="csv">Download CSV</option>
                    <option value="pdf">Download PDF</option>
                </select>
            </div>
            <div class="form-group">
                <label>Include Details</label>
                <select name="detail_level" id="report-detail">
                    <option value="summary">Summary Only</option>
                    <option value="detailed" selected>Detailed (with shifts)</option>
                    <option value="full">Full (with lunch breaks)</option>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 15px;">
            <button type="submit" class="export-btn">
                <i class="fas fa-file-alt"></i> Generate Report
            </button>
        </div>
    </form>
</div>

<div class="payroll-integration-card">
    <h3><i class="fas fa-history"></i> Previous Years Reports</h3>
    <p style="color: var(--text-dim); margin-bottom: 20px;">Generate historical reports for previous calendar years.</p>
    
    <div class="form-row" style="display: flex; gap: 15px; align-items: flex-end;">
        <div class="form-group">
            <label>Year</label>
            <select id="history-year" style="padding: 10px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: 14px;">
                <?php for ($y = date('Y') - 1; $y >= date('Y') - 5; $y--): ?>
                    <option value="<?= $y ?>"><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Staff Member</label>
            <select id="history-staff" style="padding: 10px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 8px; color: #fff; font-size: 14px;">
                <option value="all">All Staff</option>
                <?php foreach ($staffMembers as $staff): ?>
                    <option value="<?= $staff['id'] ?>">
                        <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <button class="export-btn" onclick="generateHistoricalReport()">
            <i class="fas fa-download"></i> Generate
        </button>
    </div>
</div>

<?php elseif ($activeTab === 'payroll'): ?>
<!-- Payroll Integration Tab -->
<div class="payroll-integration-card">
    <h3><i class="fas fa-link"></i> Payroll Integration</h3>
    <p style="color: var(--text-dim); margin-bottom: 20px;">Link time tracking data to payroll for hourly employees. Select a pay period to calculate hours for payroll processing.</p>
    
    <form id="payroll-form" class="integration-form" onsubmit="calculatePayrollHours(event)">
        <div class="form-row">
            <div class="form-group">
                <label>Pay Period Start</label>
                <input type="date" name="period_start" id="payroll-start" required>
            </div>
            <div class="form-group">
                <label>Pay Period End</label>
                <input type="date" name="period_end" id="payroll-end" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Staff Member</label>
                <select name="staff_id" id="payroll-staff">
                    <option value="all">All Hourly Staff</option>
                    <?php foreach ($staffMembers as $staff): ?>
                        <option value="<?= $staff['id'] ?>">
                            <?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div style="margin-top: 15px;">
            <button type="submit" class="export-btn">
                <i class="fas fa-calculator"></i> Calculate Hours
            </button>
            <button type="button" class="export-btn secondary" onclick="syncToPayroll()" style="margin-left: 10px;">
                <i class="fas fa-sync"></i> Sync to Payroll
            </button>
        </div>
    </form>
    
    <div class="hours-preview" id="hours-preview" style="display: none;">
        <h4>Hours Calculation Preview</h4>
        <div class="total" id="preview-total">0.00 hours</div>
        <div class="breakdown" id="preview-breakdown">
            <!-- Populated by JavaScript -->
        </div>
    </div>
</div>

<div class="payroll-integration-card">
    <h3><i class="fas fa-info-circle"></i> Integration Notes</h3>
    <ul style="color: var(--text-dim); margin-left: 20px; line-height: 1.8;">
        <li>Time tracking data is automatically synced with payroll when running payroll for hourly employees</li>
        <li>Hours are calculated based on completed shifts only</li>
        <li>Lunch breaks are automatically deducted from total hours</li>
        <li>You can manually adjust hours in the payroll run if needed</li>
        <li>Historical time data is preserved for audit purposes</li>
    </ul>
</div>
<?php endif; ?>

<script>
const csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function applyFilters() {
    const staffId = document.getElementById('staff-filter').value;
    const currentPeriod = <?= json_encode($selectedPeriod, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    window.location.href = `?page=hr_time_tracking&tab=overview&staff_id=${staffId}&period=${currentPeriod}`;
}

function changePeriod(period) {
    const staffId = document.getElementById('staff-filter')?.value || 'all';
    window.location.href = `?page=hr_time_tracking&tab=overview&staff_id=${staffId}&period=${period}`;
}

function viewStaffDetail(staffId) {
    const currentPeriod = '<?= $selectedPeriod ?>';
    window.location.href = `?page=hr_time_tracking&tab=overview&staff_id=${staffId}&period=${currentPeriod}`;
}

// Report generation
document.getElementById('report-period')?.addEventListener('change', function() {
    document.getElementById('custom-dates').style.display = this.value === 'custom' ? 'flex' : 'none';
});

function generateReport(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    const params = new URLSearchParams();
    
    for (let [key, value] of formData.entries()) {
        params.append(key, value);
    }
    
    const format = formData.get('format');
    
    if (format === 'view') {
        // Redirect to overview with filters
        const staffId = formData.get('staff_id');
        const period = formData.get('period');
        window.location.href = `?page=hr_time_tracking&tab=overview&staff_id=${staffId}&period=${period}`;
    } else {
        // Download report
        fetch('process_time_tracking.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'generate_report',
                ...Object.fromEntries(formData),
                csrf_token: csrfToken
            })
        })
        .then(response => {
            if (format === 'csv') {
                return response.blob();
            }
            return response.json();
        })
        .then(data => {
            if (format === 'csv' && data instanceof Blob) {
                const url = window.URL.createObjectURL(data);
                const a = document.createElement('a');
                a.href = url;
                a.download = `time_report_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
            } else if (data.success) {
                alert('Report generated successfully');
            } else {
                alert(data.message || 'Failed to generate report');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while generating the report');
        });
    }
}

function generateHistoricalReport() {
    const year = document.getElementById('history-year').value;
    const staffId = document.getElementById('history-staff').value;
    window.location.href = `?page=hr_time_tracking&tab=overview&staff_id=${staffId}&period=year&year=${year}`;
}

// Payroll integration
function calculatePayrollHours(e) {
    e.preventDefault();
    
    const startDate = document.getElementById('payroll-start').value;
    const endDate = document.getElementById('payroll-end').value;
    const staffId = document.getElementById('payroll-staff').value;
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'calculate_payroll_hours',
            start_date: startDate,
            end_date: endDate,
            staff_id: staffId,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('hours-preview').style.display = 'block';
            document.getElementById('preview-total').textContent = data.total_hours.toFixed(2) + ' hours';
            
            let breakdownHtml = '';
            data.staff_breakdown.forEach(staff => {
                breakdownHtml += `
                    <div class="breakdown-item">
                        <span>${staff.name}</span>
                        <span>${staff.hours.toFixed(2)} hrs (${staff.shifts} shifts)</span>
                    </div>
                `;
            });
            document.getElementById('preview-breakdown').innerHTML = breakdownHtml;
        } else {
            alert(data.message || 'Failed to calculate hours');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function syncToPayroll() {
    const startDate = document.getElementById('payroll-start').value;
    const endDate = document.getElementById('payroll-end').value;
    const staffId = document.getElementById('payroll-staff').value;
    
    if (!startDate || !endDate) {
        alert('Please select a pay period first');
        return;
    }
    
    if (!confirm('This will update the payroll system with time tracking data for the selected period. Continue?')) {
        return;
    }
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'sync_to_payroll',
            start_date: startDate,
            end_date: endDate,
            staff_id: staffId,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Time tracking data synced to payroll successfully');
        } else {
            alert(data.message || 'Failed to sync to payroll');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}
</script>
