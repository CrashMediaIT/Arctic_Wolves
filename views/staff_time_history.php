<?php
/**
 * Staff Time History View
 * Full time tracking history and analytics for front desk staff
 */

// Check access
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

$currentUserId = $_SESSION['user_id'];

// Fetch shift history
$shifts = [];
$summary = [];
try {
    // Get recent shifts
    $stmt = $pdo->prepare("
        SELECT * FROM staff_shifts 
        WHERE staff_id = ? 
        ORDER BY shift_date DESC, clock_in DESC
        LIMIT 100
    ");
    $stmt->execute([$currentUserId]);
    $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate summaries - periods are hardcoded constants, safe for SQL interpolation
    $allowedPeriods = ['week', 'month', 'quarter', 'year'];
    $periods = [
        'week' => 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
        'month' => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
        'quarter' => 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)',
        'year' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR)'
    ];
    
    foreach ($periods as $period => $dateExpr) {
        if (!in_array($period, $allowedPeriods)) continue; // Whitelist check for defense in depth
        $summaryStmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_hours), 0) as total_hours, COUNT(*) as shift_count
            FROM staff_shifts 
            WHERE staff_id = ? AND shift_date >= $dateExpr AND status = 'completed'
        ");
        $summaryStmt->execute([$currentUserId]);
        $summary[$period] = $summaryStmt->fetch(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Time history fetch error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-history"></i> Time History</h1>
        <p class="page-description">View your complete shift history and hours worked</p>
    </div>
</div>

<style>
    .history-grid {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 25px;
    }
    
    .summary-cards {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }
    
    .summary-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        text-align: center;
    }
    
    .summary-card .value {
        font-size: 32px;
        font-weight: 900;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .summary-card .label {
        font-size: 13px;
        color: var(--text-dim);
    }
    
    .summary-card.highlight {
        border-color: var(--primary);
        background: rgba(107, 70, 193, 0.1);
    }
    
    .summary-card.highlight .value {
        color: var(--primary);
    }
    
    .history-panel {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        overflow: hidden;
    }
    
    .panel-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .panel-header h3 {
        font-size: 16px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .panel-header h3 i {
        color: var(--primary);
    }
    
    .filter-select {
        padding: 8px 30px 8px 12px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        font-size: 13px;
    }
    
    .history-list {
        max-height: 500px;
        overflow-y: auto;
    }
    
    .history-item {
        padding: 16px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .history-item:hover {
        background: rgba(255, 255, 255, 0.02);
    }
    
    .history-item:last-child {
        border-bottom: none;
    }
    
    .history-item.incomplete {
        opacity: 0.6;
    }
    
    .history-date {
        font-weight: 600;
        margin-bottom: 4px;
    }
    
    .history-times {
        font-size: 13px;
        color: var(--text-dim);
    }
    
    .history-hours {
        text-align: right;
    }
    
    .history-hours .value {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
    }
    
    .history-hours .status {
        font-size: 11px;
        text-transform: uppercase;
        margin-top: 2px;
    }
    
    .history-hours .status.completed {
        color: #10b981;
    }
    
    .history-hours .status.incomplete {
        color: #f59e0b;
    }
    
    .history-hours .status.active {
        color: var(--primary);
    }
    
    /* Chart Panel */
    .chart-panel {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 20px;
    }
    
    .chart-panel h3 {
        font-size: 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .chart-panel h3 i {
        color: var(--primary);
    }
    
    .chart-container {
        height: 250px;
        position: relative;
    }
    
    .bar-chart {
        display: flex;
        align-items: flex-end;
        justify-content: space-between;
        height: 200px;
        padding: 0 10px;
        border-bottom: 1px solid var(--border);
    }
    
    .bar-wrapper {
        flex: 1;
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 8px;
    }
    
    .bar {
        width: 30px;
        background: linear-gradient(to top, var(--primary), var(--primary-light));
        border-radius: 4px 4px 0 0;
        transition: height 0.5s ease;
        position: relative;
    }
    
    .bar:hover {
        background: var(--primary-hover);
    }
    
    .bar-label {
        font-size: 11px;
        color: var(--text-dim);
        text-align: center;
    }
    
    .bar-value {
        position: absolute;
        top: -25px;
        left: 50%;
        transform: translateX(-50%);
        font-size: 11px;
        font-weight: 700;
        color: #fff;
        white-space: nowrap;
    }
    
    .quick-nav {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
    
    .quick-nav h4 {
        font-size: 13px;
        color: var(--text-dim);
        margin-bottom: 12px;
    }
    
    .nav-btn {
        display: block;
        width: 100%;
        padding: 12px 16px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        text-decoration: none;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        transition: all 0.2s;
    }
    
    .nav-btn:hover {
        border-color: var(--primary);
        background: rgba(107, 70, 193, 0.1);
    }
    
    .nav-btn i {
        margin-right: 10px;
        color: var(--primary);
    }
    
    @media (max-width: 1000px) {
        .history-grid {
            grid-template-columns: 1fr;
        }
        
        .summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card highlight">
        <div class="value"><?= number_format($summary['week']['total_hours'] ?? 0, 1) ?></div>
        <div class="label">Hours This Week</div>
    </div>
    <div class="summary-card">
        <div class="value"><?= number_format($summary['month']['total_hours'] ?? 0, 1) ?></div>
        <div class="label">Hours This Month</div>
    </div>
    <div class="summary-card">
        <div class="value"><?= number_format($summary['quarter']['total_hours'] ?? 0, 1) ?></div>
        <div class="label">Hours This Quarter</div>
    </div>
    <div class="summary-card">
        <div class="value"><?= number_format($summary['year']['total_hours'] ?? 0, 1) ?></div>
        <div class="label">Hours This Year</div>
    </div>
</div>

<div class="history-grid">
    <!-- History List -->
    <div class="history-panel">
        <div class="panel-header">
            <h3><i class="fas fa-list"></i> Shift History</h3>
            <select class="filter-select" id="history-filter" onchange="filterHistory(this.value)">
                <option value="all">All Time</option>
                <option value="week">This Week</option>
                <option value="month" selected>This Month</option>
                <option value="quarter">This Quarter</option>
                <option value="year">This Year</option>
            </select>
        </div>
        
        <div class="history-list" id="history-list">
            <?php if (empty($shifts)): ?>
                <div style="text-align: center; padding: 40px; color: var(--text-dim);">
                    <i class="fas fa-clock" style="font-size: 32px; margin-bottom: 15px;"></i>
                    <p>No shift history yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($shifts as $shift): 
                    $shiftDate = new DateTime($shift['shift_date']);
                    $clockIn = $shift['clock_in'] ? new DateTime($shift['clock_in']) : null;
                    $clockOut = $shift['clock_out'] ? new DateTime($shift['clock_out']) : null;
                ?>
                    <div class="history-item <?= $shift['status'] !== 'completed' ? 'incomplete' : '' ?>"
                         data-date="<?= $shift['shift_date'] ?>">
                        <div>
                            <div class="history-date"><?= $shiftDate->format('l, F j, Y') ?></div>
                            <div class="history-times">
                                <i class="fas fa-sign-in-alt"></i> 
                                <?= $clockIn ? $clockIn->format('g:i A') : '--:--' ?>
                                &nbsp;→&nbsp;
                                <i class="fas fa-sign-out-alt"></i>
                                <?= $clockOut ? $clockOut->format('g:i A') : '--:--' ?>
                                <?php if ($shift['lunch_start'] && $shift['lunch_end']): ?>
                                    &nbsp;•&nbsp;
                                    <i class="fas fa-utensils"></i> Lunch taken
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="history-hours">
                            <div class="value"><?= $shift['total_hours'] ? number_format($shift['total_hours'], 1) . ' hrs' : '--' ?></div>
                            <div class="status <?= $shift['status'] ?>"><?= ucfirst($shift['status']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Side Panel -->
    <div>
        <!-- Chart -->
        <div class="chart-panel">
            <h3><i class="fas fa-chart-bar"></i> Weekly Hours</h3>
            <div class="chart-container">
                <div class="bar-chart" id="hours-chart">
                    <!-- Bars will be generated by JavaScript -->
                </div>
            </div>
        </div>
        
        <!-- Quick Navigation -->
        <div class="quick-nav" style="margin-top: 20px;">
            <h4>Quick Navigation</h4>
            <a href="?page=pos_time_tracking" class="nav-btn">
                <i class="fas fa-clock"></i> Current Shift
            </a>
            <a href="?page=pos_schedule" class="nav-btn">
                <i class="fas fa-calendar-alt"></i> View Schedule
            </a>
            <a href="?page=pos_terminal" class="nav-btn">
                <i class="fas fa-cash-register"></i> POS Terminal
            </a>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

document.addEventListener('DOMContentLoaded', function() {
    loadWeeklyChart();
    filterHistory('month'); // Default to this month
});

function loadWeeklyChart() {
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'get_hours_summary',
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.weekly_data) {
            renderChart(data.weekly_data);
        }
    })
    .catch(error => console.error('Error loading chart:', error));
}

function renderChart(weeklyData) {
    const chartContainer = document.getElementById('hours-chart');
    
    // Find max hours for scaling
    const maxHours = Math.max(...weeklyData.map(w => parseFloat(w.total_hours) || 0), 40);
    
    // Generate last 8 weeks if data is sparse
    const weeks = weeklyData.slice(-8);
    
    if (weeks.length === 0) {
        chartContainer.innerHTML = '<div style="text-align: center; color: var(--text-dim); padding: 80px 20px;">No data available</div>';
        return;
    }
    
    let html = '';
    weeks.forEach(week => {
        const hours = parseFloat(week.total_hours) || 0;
        const heightPercent = (hours / maxHours) * 100;
        const weekStart = new Date(week.week_start);
        const label = weekStart.toLocaleDateString('en-US', { timeZone: window.APP_TIMEZONE, month: 'short', day: 'numeric' });
        
        html += `
            <div class="bar-wrapper">
                <div class="bar" style="height: ${Math.max(heightPercent, 2)}%;">
                    <div class="bar-value">${hours.toFixed(1)}h</div>
                </div>
                <div class="bar-label">${label}</div>
            </div>
        `;
    });
    
    chartContainer.innerHTML = html;
}

function filterHistory(filter) {
    const items = document.querySelectorAll('.history-item');
    const today = new Date();
    
    items.forEach(item => {
        const itemDate = new Date(item.dataset.date);
        let show = true;
        
        switch (filter) {
            case 'week':
                const weekAgo = new Date(today);
                weekAgo.setDate(today.getDate() - 7);
                show = itemDate >= weekAgo;
                break;
            case 'month':
                const monthAgo = new Date(today);
                monthAgo.setMonth(today.getMonth() - 1);
                show = itemDate >= monthAgo;
                break;
            case 'quarter':
                const quarterAgo = new Date(today);
                quarterAgo.setMonth(today.getMonth() - 3);
                show = itemDate >= quarterAgo;
                break;
            case 'year':
                const yearAgo = new Date(today);
                yearAgo.setFullYear(today.getFullYear() - 1);
                show = itemDate >= yearAgo;
                break;
            default:
                show = true;
        }
        
        item.style.display = show ? 'flex' : 'none';
    });
}
</script>
