<?php
/**
 * Front Desk Schedule View
 * Calendar and list view of upcoming shifts for front desk staff
 */

// Check access
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

$currentUserId = $_SESSION['user_id'];

// Fetch upcoming schedules for the next month
$schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT ss.*, u.first_name as created_by_name
        FROM staff_schedules ss
        LEFT JOIN users u ON ss.created_by = u.id
        WHERE ss.staff_id = ? AND ss.schedule_date >= CURDATE()
        ORDER BY ss.schedule_date ASC, ss.start_time ASC
        LIMIT 50
    ");
    $stmt->execute([$currentUserId]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Schedule fetch error: " . $e->getMessage());
}

// Group schedules by week for calendar view
$schedulesByDate = [];
foreach ($schedules as $schedule) {
    $date = $schedule['schedule_date'];
    if (!isset($schedulesByDate[$date])) {
        $schedulesByDate[$date] = [];
    }
    $schedulesByDate[$date][] = $schedule;
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-calendar-alt"></i> My Schedule</h1>
        <p class="page-description">View your upcoming shifts</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= count($schedules) ?></span>
            <span class="stat-label">Upcoming Shifts</span>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <button type="button" class="page-tab active" onclick="switchScheduleView('list');">
        <i class="fas fa-list"></i> List View
    </button>
    <button type="button" class="page-tab" onclick="switchScheduleView('calendar');">
        <i class="fas fa-calendar"></i> Calendar View
    </button>
</div>

<div class="page-tab-content">

<style>
    .schedule-filters {
        display: flex;
        gap: 12px;
        margin-bottom: 25px;
    }
    
    .filter-btn {
        padding: 10px 20px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: var(--text);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .filter-btn:hover,
    .filter-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    
    /* List View Styles */
    .schedule-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    
    .schedule-item {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s;
    }
    
    .schedule-item:hover {
        border-color: var(--primary);
    }
    
    .schedule-item.today {
        border-left: 4px solid var(--primary);
    }
    
    .schedule-date-col {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    
    .date-badge {
        background: var(--bg);
        border-radius: 10px;
        padding: 12px 16px;
        text-align: center;
        min-width: 70px;
    }
    
    .date-badge .day {
        font-size: 24px;
        font-weight: 900;
        color: #fff;
    }
    
    .date-badge .month {
        font-size: 11px;
        text-transform: uppercase;
        color: var(--text-dim);
        font-weight: 700;
    }
    
    .date-badge .weekday {
        font-size: 12px;
        color: var(--primary);
        font-weight: 600;
        margin-top: 4px;
    }
    
    .schedule-details h3 {
        font-size: 16px;
        margin-bottom: 6px;
    }
    
    .schedule-details .time {
        color: var(--text);
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .schedule-details .time i {
        color: var(--primary);
    }
    
    .schedule-details .location {
        color: var(--text-dim);
        font-size: 13px;
        margin-top: 4px;
    }
    
    .schedule-duration {
        background: rgba(107, 70, 193, 0.1);
        color: var(--primary);
        padding: 8px 16px;
        border-radius: 20px;
        font-weight: 700;
        font-size: 14px;
    }
    
    /* Calendar View Styles */
    .calendar-container {
        display: none;
    }
    
    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    
    .calendar-title {
        font-size: 20px;
        font-weight: 700;
    }
    
    .calendar-nav {
        display: flex;
        gap: 8px;
    }
    
    .calendar-nav button {
        padding: 10px 15px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        cursor: pointer;
    }
    
    .calendar-nav button:hover {
        border-color: var(--primary);
    }
    
    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 2px;
        background: var(--border);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .calendar-day-header {
        background: var(--bg-secondary);
        padding: 12px;
        text-align: center;
        font-size: 12px;
        font-weight: 700;
        color: var(--text-dim);
        text-transform: uppercase;
    }
    
    .calendar-day {
        background: var(--bg);
        min-height: 100px;
        padding: 10px;
    }
    
    .calendar-day.other-month {
        opacity: 0.4;
    }
    
    .calendar-day.today {
        background: rgba(107, 70, 193, 0.1);
    }
    
    .calendar-day-number {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
    }
    
    .calendar-day.today .calendar-day-number {
        color: var(--primary);
    }
    
    .calendar-event {
        background: var(--primary);
        color: #fff;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        margin-bottom: 4px;
        cursor: pointer;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .calendar-event:hover {
        background: var(--primary-hover);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-secondary);
        border-radius: 16px;
        border: 1px solid var(--border);
    }
    
    .empty-state i {
        font-size: 48px;
        color: var(--text-dim);
        margin-bottom: 15px;
    }
    
    .empty-state h3 {
        font-size: 18px;
        margin-bottom: 8px;
    }
    
    .empty-state p {
        color: var(--text-dim);
        font-size: 14px;
    }
</style>

<!-- Filter Buttons -->
<div class="schedule-filters" id="list-filters">
    <button class="filter-btn active" onclick="filterSchedule('all')">All Upcoming</button>
    <button class="filter-btn" onclick="filterSchedule('week')">This Week</button>
    <button class="filter-btn" onclick="filterSchedule('month')">This Month</button>
</div>

<!-- List View -->
<div class="schedule-list" id="list-view">
    <?php if (empty($schedules)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Upcoming Shifts</h3>
            <p>You don't have any scheduled shifts at the moment.</p>
        </div>
    <?php else: ?>
        <?php 
        $today = date('Y-m-d');
        foreach ($schedules as $schedule): 
            $isToday = $schedule['schedule_date'] === $today;
            $dateObj = new DateTime($schedule['schedule_date']);
            $startTime = new DateTime($schedule['start_time']);
            $endTime = new DateTime($schedule['end_time']);
            $duration = $startTime->diff($endTime);
            $durationHours = $duration->h + ($duration->i / 60);
            $lunchBreak = isset($schedule['lunch_break_minutes']) ? $schedule['lunch_break_minutes'] : 30;
        ?>
            <div class="schedule-item <?= $isToday ? 'today' : '' ?>" 
                 data-date="<?= $schedule['schedule_date'] ?>">
                <div class="schedule-date-col">
                    <div class="date-badge">
                        <div class="day"><?= $dateObj->format('j') ?></div>
                        <div class="month"><?= $dateObj->format('M') ?></div>
                        <div class="weekday"><?= $dateObj->format('D') ?></div>
                    </div>
                    <div class="schedule-details">
                        <h3><?= $isToday ? "Today's Shift" : ($dateObj->format('l') . "'s Shift") ?></h3>
                        <div class="time">
                            <i class="fas fa-clock"></i>
                            <?= date('g:i A', strtotime($schedule['start_time'])) ?> - 
                            <?= date('g:i A', strtotime($schedule['end_time'])) ?>
                        </div>
                        <?php if ($lunchBreak > 0): ?>
                            <div class="location" style="margin-top: 4px;">
                                <i class="fas fa-utensils"></i>
                                <?= $lunchBreak ?> min lunch break
                            </div>
                        <?php endif; ?>
                        <?php if ($schedule['location']): ?>
                            <div class="location">
                                <i class="fas fa-map-marker-alt"></i>
                                <?= htmlspecialchars($schedule['location']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="schedule-duration">
                    <?= number_format($durationHours, 1) ?> hrs
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Calendar View -->
<div class="calendar-container" id="calendar-view">
    <div class="calendar-header">
        <h3 class="calendar-title" id="calendar-month-title">Loading...</h3>
        <div class="calendar-nav">
            <button onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
            <button onclick="goToToday()">Today</button>
            <button onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
    
    <div class="calendar-grid" id="calendar-grid">
        <!-- Calendar days will be generated by JavaScript -->
    </div>
</div>

<script>
// Schedule data from PHP
const scheduleData = <?= json_encode($schedulesByDate) ?>;
let currentDate = new Date();
let currentView = 'list';

// Updated function for new tab design
function switchScheduleView(view) {
    currentView = view;
    
    // Update tab active state
    document.querySelectorAll('.schedule-tab').forEach(tab => tab.classList.remove('active'));
    event.target.closest('.schedule-tab').classList.add('active');
    
    // Show/hide views
    if (view === 'list') {
        document.getElementById('list-view').style.display = 'flex';
        document.getElementById('list-filters').style.display = 'flex';
        document.getElementById('calendar-view').style.display = 'none';
    } else {
        document.getElementById('list-view').style.display = 'none';
        document.getElementById('list-filters').style.display = 'none';
        document.getElementById('calendar-view').style.display = 'block';
        renderCalendar();
    }
}

// Legacy function for backward compatibility
function switchView(view) {
    switchScheduleView(view);
}

function filterSchedule(filter) {
    // Update filter button states
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    event.target.classList.add('active');
    
    const today = new Date();
    const items = document.querySelectorAll('.schedule-item');
    
    items.forEach(item => {
        const itemDate = new Date(item.dataset.date);
        let show = true;
        
        if (filter === 'week') {
            const weekEnd = new Date(today);
            weekEnd.setDate(today.getDate() + 7);
            show = itemDate <= weekEnd;
        } else if (filter === 'month') {
            const monthEnd = new Date(today);
            monthEnd.setMonth(today.getMonth() + 1);
            show = itemDate <= monthEnd;
        }
        
        item.style.display = show ? 'flex' : 'none';
    });
}

function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    // Update title
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    document.getElementById('calendar-month-title').textContent = monthNames[month] + ' ' + year;
    
    // Generate calendar
    const firstDay = new Date(year, month, 1);
    const lastDay = new Date(year, month + 1, 0);
    const startDay = firstDay.getDay();
    const daysInMonth = lastDay.getDate();
    
    let html = `
        <div class="calendar-day-header">Sun</div>
        <div class="calendar-day-header">Mon</div>
        <div class="calendar-day-header">Tue</div>
        <div class="calendar-day-header">Wed</div>
        <div class="calendar-day-header">Thu</div>
        <div class="calendar-day-header">Fri</div>
        <div class="calendar-day-header">Sat</div>
    `;
    
    const today = new Date();
    const todayStr = today.toISOString().split('T')[0];
    
    // Previous month days
    const prevMonth = new Date(year, month, 0);
    for (let i = startDay - 1; i >= 0; i--) {
        const day = prevMonth.getDate() - i;
        html += `<div class="calendar-day other-month"><div class="calendar-day-number">${day}</div></div>`;
    }
    
    // Current month days
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const isToday = dateStr === todayStr;
        const events = scheduleData[dateStr] || [];
        
        html += `<div class="calendar-day ${isToday ? 'today' : ''}">
            <div class="calendar-day-number">${day}</div>`;
        
        events.forEach(event => {
            const startTime = event.start_time.substring(0, 5);
            html += `<div class="calendar-event" title="${startTime} - ${event.end_time.substring(0, 5)}">
                ${startTime} Shift
            </div>`;
        });
        
        html += '</div>';
    }
    
    // Next month days
    const totalCells = startDay + daysInMonth;
    const remainingCells = (7 - (totalCells % 7)) % 7;
    for (let day = 1; day <= remainingCells; day++) {
        html += `<div class="calendar-day other-month"><div class="calendar-day-number">${day}</div></div>`;
    }
    
    document.getElementById('calendar-grid').innerHTML = html;
}

function changeMonth(delta) {
    currentDate.setMonth(currentDate.getMonth() + delta);
    renderCalendar();
}

function goToToday() {
    currentDate = new Date();
    renderCalendar();
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    // If calendar view is visible, render it
    if (currentView === 'calendar') {
        renderCalendar();
    }
});
</script>

</div><!-- End page-tab-content -->
