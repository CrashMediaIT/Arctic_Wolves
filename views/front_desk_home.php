<?php
/**
 * Front Desk Staff Dashboard
 * Special dashboard for front desk staff with Schedule and Time Tracker options
 */

// Check access - this view is for front desk staff only
if (!isset($_SESSION['user_id']) || !$isFrontDesk) {
    // Redirect non-front desk staff to regular home
    include 'home.php';
    return;
}

$currentUserId = $_SESSION['user_id'];

// Fetch current shift status
$activeShift = null;
$todaySchedule = null;
$weekStats = null;

try {
    // Get active shift
    $shiftStmt = $pdo->prepare("
        SELECT * FROM staff_shifts 
        WHERE staff_id = ? AND shift_date = CURDATE() AND status = 'active'
    ");
    $shiftStmt->execute([$currentUserId]);
    $activeShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get today's scheduled shift
    $scheduleStmt = $pdo->prepare("
        SELECT * FROM staff_schedules 
        WHERE staff_id = ? AND schedule_date = CURDATE()
        LIMIT 1
    ");
    $scheduleStmt->execute([$currentUserId]);
    $todaySchedule = $scheduleStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get this week's stats
    $statsStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_hours), 0) as total_hours,
            COUNT(*) as shift_count
        FROM staff_shifts 
        WHERE staff_id = ? 
        AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        AND status = 'completed'
    ");
    $statsStmt->execute([$currentUserId]);
    $weekStats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Get upcoming shifts count
    $upcomingStmt = $pdo->prepare("
        SELECT COUNT(*) as count FROM staff_schedules 
        WHERE staff_id = ? AND schedule_date > CURDATE()
    ");
    $upcomingStmt->execute([$currentUserId]);
    $upcomingShifts = $upcomingStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
} catch (PDOException $e) {
    error_log("Front desk dashboard error: " . $e->getMessage());
}
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-home"></i> Dashboard
    </h1>
    <p class="page-description">Welcome back, <?= htmlspecialchars($user_name) ?>! Here's your overview.</p>
</div>

<style>
    .fd-dashboard {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 25px;
    }
    
    .fd-card {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
    }
    
    .fd-card:hover {
        border-color: var(--primary);
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(107, 70, 193, 0.2);
    }
    
    .fd-card-icon {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        border-radius: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    
    .fd-card-icon i {
        font-size: 36px;
        color: #fff;
    }
    
    .fd-card h3 {
        font-size: 22px;
        margin-bottom: 8px;
    }
    
    .fd-card p {
        color: var(--text-dim);
        font-size: 14px;
        margin-bottom: 20px;
    }
    
    .fd-card-stats {
        display: flex;
        justify-content: center;
        gap: 30px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
    
    .fd-stat {
        text-align: center;
    }
    
    .fd-stat .value {
        font-size: 24px;
        font-weight: 900;
        color: var(--primary);
    }
    
    .fd-stat .label {
        font-size: 11px;
        color: var(--text-dim);
        text-transform: uppercase;
        margin-top: 4px;
    }
    
    /* Status Banner */
    .status-banner {
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 20px 25px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .status-banner.active {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.1);
    }
    
    .status-banner.lunch {
        border-color: #f59e0b;
        background: rgba(245, 158, 11, 0.1);
    }
    
    .status-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .status-dot {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #ef4444;
    }
    
    .status-dot.active {
        background: #10b981;
        animation: pulse 2s infinite;
    }
    
    .status-dot.lunch {
        background: #f59e0b;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .status-text h4 {
        font-size: 16px;
        margin-bottom: 4px;
    }
    
    .status-text p {
        font-size: 13px;
        color: var(--text-dim);
    }
    
    .status-action {
        padding: 12px 24px;
        background: var(--primary);
        border: none;
        border-radius: 8px;
        color: #fff;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
    }
    
    .status-action:hover {
        background: var(--primary-hover);
    }
    
    /* Today's Schedule Card */
    .today-schedule {
        grid-column: span 2;
        display: flex;
        justify-content: space-between;
        align-items: center;
        text-align: left;
        cursor: default;
    }
    
    .today-schedule:hover {
        transform: none;
        box-shadow: none;
    }
    
    .schedule-info h4 {
        font-size: 16px;
        margin-bottom: 5px;
        color: var(--text-dim);
    }
    
    .schedule-info h3 {
        font-size: 24px;
    }
    
    .schedule-time {
        background: var(--bg);
        padding: 15px 25px;
        border-radius: 10px;
        text-align: center;
    }
    
    .schedule-time .hours {
        font-size: 28px;
        font-weight: 900;
        color: var(--primary);
    }
    
    .schedule-time .label {
        font-size: 12px;
        color: var(--text-dim);
    }
    
    @media (max-width: 800px) {
        .fd-dashboard {
            grid-template-columns: 1fr;
        }
        
        .today-schedule {
            grid-column: span 1;
            flex-direction: column;
            text-align: center;
            gap: 20px;
        }
    }
</style>

<!-- Current Shift Status Banner -->
<?php if ($activeShift): 
    $onLunch = $activeShift['lunch_start'] && !$activeShift['lunch_end'];
?>
<div class="status-banner <?= $onLunch ? 'lunch' : 'active' ?>">
    <div class="status-info">
        <div class="status-dot <?= $onLunch ? 'lunch' : 'active' ?>"></div>
        <div class="status-text">
            <h4><?= $onLunch ? 'On Lunch Break' : 'Currently Clocked In' ?></h4>
            <p>Since <?= date('g:i A', strtotime($activeShift['clock_in'])) ?></p>
        </div>
    </div>
    <a href="?page=pos_time_tracking" class="status-action">
        <i class="fas fa-clock"></i> View Time Tracker
    </a>
</div>
<?php endif; ?>

<div class="fd-dashboard">
    <!-- Schedule Card -->
    <a href="?page=pos_schedule" class="fd-card">
        <div class="fd-card-icon">
            <i class="fas fa-calendar-alt"></i>
        </div>
        <h3>Schedule</h3>
        <p>View your upcoming shifts and work schedule</p>
        <div class="fd-card-stats">
            <div class="fd-stat">
                <div class="value"><?= $upcomingShifts ?? 0 ?></div>
                <div class="label">Upcoming Shifts</div>
            </div>
            <div class="fd-stat">
                <div class="value"><?= $todaySchedule ? date('g:i A', strtotime($todaySchedule['start_time'])) : 'None' ?></div>
                <div class="label">Today's Start</div>
            </div>
        </div>
    </a>
    
    <!-- Time Tracker Card -->
    <a href="?page=staff_time_history" class="fd-card">
        <div class="fd-card-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h3>Time Tracker</h3>
        <p>View your shift history and hours worked</p>
        <div class="fd-card-stats">
            <div class="fd-stat">
                <div class="value"><?= number_format($weekStats['total_hours'] ?? 0, 1) ?></div>
                <div class="label">Hours This Week</div>
            </div>
            <div class="fd-stat">
                <div class="value"><?= $weekStats['shift_count'] ?? 0 ?></div>
                <div class="label">Shifts Completed</div>
            </div>
        </div>
    </a>
    
    <!-- Today's Schedule Info -->
    <?php if ($todaySchedule): ?>
    <div class="fd-card today-schedule">
        <div class="schedule-info">
            <h4>Today's Scheduled Shift</h4>
            <h3>
                <?= date('g:i A', strtotime($todaySchedule['start_time'])) ?> - 
                <?= date('g:i A', strtotime($todaySchedule['end_time'])) ?>
            </h3>
            <?php if ($todaySchedule['location']): ?>
                <p><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($todaySchedule['location']) ?></p>
            <?php endif; ?>
        </div>
        <div class="schedule-time">
            <?php 
            $start = new DateTime($todaySchedule['start_time']);
            $end = new DateTime($todaySchedule['end_time']);
            $duration = $start->diff($end);
            $hours = $duration->h + ($duration->i / 60);
            ?>
            <div class="hours"><?= number_format($hours, 1) ?></div>
            <div class="label">Scheduled Hours</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Quick Actions -->
<div style="margin-top: 30px; display: flex; gap: 15px; justify-content: center;">
    <a href="?page=pos_terminal" class="status-action" style="background: rgba(107, 70, 193, 0.1); color: var(--primary);">
        <i class="fas fa-cash-register"></i> Open POS Terminal
    </a>
    <?php if (!$activeShift): ?>
    <a href="?page=pos_time_tracking" class="status-action" style="background: #10b981;">
        <i class="fas fa-play-circle"></i> Clock In
    </a>
    <?php endif; ?>
</div>
