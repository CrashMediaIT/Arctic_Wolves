<?php
/**
 * POS Time Tracking View
 * Time clock interface for front desk staff within the POS system
 */

// Check access
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS access denied from unauthorized IP', ['ip' => getClientIP(), 'page' => 'pos_time_tracking']);
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>POS access is not available from this location. Please contact an administrator.</p></div>';
    return;
}

// Get current user info
$currentUserId = $_SESSION['user_id'];
$isKioskMode = isset($_SESSION['kiosk_mode']) && $_SESSION['kiosk_mode'];

// Fetch current active shift
$activeShift = null;
try {
    $stmt = $pdo->prepare("
        SELECT * FROM staff_shifts 
        WHERE staff_id = ? AND shift_date = CURDATE() AND status = 'active'
    ");
    $stmt->execute([$currentUserId]);
    $activeShift = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Time tracking fetch error: " . $e->getMessage());
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-clock"></i> Time Tracking</h1>
        <p class="page-description">Manage your shift and breaks</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value" id="current-time-display">--:--</span>
            <span class="stat-label">Current Time</span>
        </div>
    </div>
</div>

<style>
    .time-tracking-container {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 25px;
    }
    
    .shift-card {
        background: var(--bg-secondary);
        border-radius: 16px;
        padding: 30px;
        border: 1px solid var(--border);
    }
    
    .shift-status {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }
    
    .status-indicator {
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background: #ef4444;
    }
    
    .status-indicator.active {
        background: #10b981;
        animation: pulse 2s infinite;
    }
    
    .status-indicator.lunch {
        background: #f59e0b;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }
    
    .shift-timer {
        text-align: center;
        padding: 40px 0;
    }
    
    .timer-display {
        font-size: 72px;
        font-weight: 700;
        font-family: 'JetBrains Mono', 'Roboto Mono', 'Courier New', monospace;
        color: #fff;
        margin-bottom: 10px;
        letter-spacing: 2px;
        text-shadow: 0 0 20px rgba(107, 70, 193, 0.3);
        position: relative;
    }
    
    .timer-display.active::after {
        content: '';
        position: absolute;
        bottom: -5px;
        left: 50%;
        transform: translateX(-50%);
        width: 8px;
        height: 8px;
        background: #10b981;
        border-radius: 50%;
        animation: timerPulse 1s infinite;
    }
    
    @keyframes timerPulse {
        0%, 100% { opacity: 1; transform: translateX(-50%) scale(1); }
        50% { opacity: 0.5; transform: translateX(-50%) scale(1.2); }
    }
    
    .timer-label {
        color: var(--text-dim);
        font-size: 14px;
    }
    
    .timer-label.paused {
        color: #f59e0b;
    }
    
    .shift-info {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
        padding: 20px 0;
        border-top: 1px solid var(--border);
        margin-top: 20px;
    }
    
    .info-item {
        text-align: center;
    }
    
    .info-value {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
    }
    
    .info-label {
        font-size: 12px;
        color: var(--text-dim);
        margin-top: 4px;
    }
    
    .shift-actions {
        display: flex;
        flex-direction: column;
        gap: 12px;
        margin-top: 25px;
    }
    
    .action-btn {
        padding: 16px 24px;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .action-btn.lunch {
        background: rgba(245, 158, 11, 0.1);
        color: #f59e0b;
    }
    
    .action-btn.lunch:hover {
        background: rgba(245, 158, 11, 0.2);
    }
    
    .action-btn.end-lunch {
        background: #f59e0b;
        color: #000;
    }
    
    .action-btn.end-lunch:hover {
        background: #d97706;
    }
    
    .action-btn.logout {
        background: rgba(107, 70, 193, 0.1);
        color: var(--primary);
    }
    
    .action-btn.logout:hover {
        background: rgba(107, 70, 193, 0.2);
    }
    
    .action-btn.end-shift {
        background: #ef4444;
        color: #fff;
    }
    
    .action-btn.end-shift:hover {
        background: #dc2626;
    }
    
    .action-btn.clock-in {
        background: #10b981;
        color: #fff;
    }
    
    .action-btn.clock-in:hover {
        background: #059669;
    }
    
    /* Quick Stats Panel */
    .quick-stats {
        background: var(--bg-secondary);
        border-radius: 16px;
        padding: 25px;
        border: 1px solid var(--border);
    }
    
    .quick-stats h3 {
        font-size: 16px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .quick-stats h3 i {
        color: var(--primary);
    }
    
    .stat-row {
        display: flex;
        justify-content: space-between;
        padding: 12px 0;
        border-bottom: 1px solid var(--border);
    }
    
    .stat-row:last-child {
        border-bottom: none;
    }
    
    .stat-row .label {
        color: var(--text-dim);
        font-size: 13px;
    }
    
    .stat-row .value {
        font-weight: 700;
        color: #fff;
    }
    
    .nav-shortcuts {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
    }
    
    .nav-shortcuts h4 {
        font-size: 13px;
        color: var(--text-dim);
        margin-bottom: 12px;
    }
    
    .shortcut-btn {
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
    
    .shortcut-btn:hover {
        border-color: var(--primary);
        background: rgba(107, 70, 193, 0.1);
    }
    
    .shortcut-btn i {
        margin-right: 10px;
        color: var(--primary);
    }
    
    .not-clocked-in {
        text-align: center;
        padding: 60px 20px;
        background: var(--bg-card, #16161F);
        border-radius: 16px;
        border: 1px solid var(--border, #2D2D3F);
    }
    
    .not-clocked-in i.fa-clock {
        font-size: 48px;
        color: var(--primary, #6B46C1);
        margin-bottom: 24px;
        display: block;
    }
    
    .not-clocked-in h3 {
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 12px;
        color: var(--text-white);
    }
    
    .not-clocked-in p {
        color: var(--text-dim);
        margin-bottom: 30px;
        font-size: 15px;
    }
    
    .not-clocked-in .action-btn.clock-in {
        background: var(--primary, #6B46C1);
        color: #fff;
        padding: 16px 40px;
        font-size: 15px;
        display: inline-flex;
        width: auto;
    }
    
    .not-clocked-in .action-btn.clock-in:hover {
        background: var(--primary-hover, #7C3AED);
    }
    
    .not-clocked-in .action-btn.clock-in i {
        font-size: 15px;
    }
    
    @media (max-width: 900px) {
        .time-tracking-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="time-tracking-container">
    <!-- Main Shift Panel -->
    <div class="shift-card">
        <div class="shift-status">
            <span class="status-indicator <?= $activeShift ? ($activeShift['lunch_start'] && !$activeShift['lunch_end'] ? 'lunch' : 'active') : '' ?>" id="status-dot"></span>
            <span id="status-text"><?= $activeShift ? ($activeShift['lunch_start'] && !$activeShift['lunch_end'] ? 'On Lunch Break' : 'Clocked In') : 'Not Clocked In' ?></span>
        </div>
        
        <div id="shift-content">
            <?php if ($activeShift): ?>
            <div class="shift-timer">
                <div class="timer-display" id="shift-timer">00:00:00</div>
                <div class="timer-label" id="timer-label">Time Worked Today</div>
            </div>
            
            <div class="shift-info">
                <div class="info-item">
                    <div class="info-value" id="clock-in-time"><?= date('g:i A', strtotime($activeShift['clock_in'])) ?></div>
                    <div class="info-label">Clock In</div>
                </div>
                <div class="info-item">
                    <div class="info-value" id="lunch-time"><?= $activeShift['lunch_start'] ? ($activeShift['lunch_end'] ? 'Completed' : 'In Progress') : 'Not Taken' ?></div>
                    <div class="info-label">Lunch Break</div>
                </div>
            </div>
            
            <div class="shift-actions">
                <?php if (!$activeShift['lunch_start']): ?>
                    <button class="action-btn lunch" onclick="startLunch()">
                        <i class="fas fa-utensils"></i> Start Lunch Break
                    </button>
                <?php elseif (!$activeShift['lunch_end']): ?>
                    <button class="action-btn end-lunch" onclick="endLunch()">
                        <i class="fas fa-play"></i> End Lunch Break
                    </button>
                <?php endif; ?>
                
                <button class="action-btn logout" onclick="logoutOnly()">
                    <i class="fas fa-sign-out-alt"></i> Log Out (Keep Shift Running)
                </button>
                
                <button class="action-btn end-shift" onclick="endShift()">
                    <i class="fas fa-stop-circle"></i> End Shift & Log Out
                </button>
            </div>
            <?php else: ?>
            <div class="not-clocked-in">
                <i class="fas fa-clock"></i>
                <h3>Not Clocked In</h3>
                <p>Click the button below to start your shift</p>
                <button class="action-btn clock-in" onclick="clockIn()">
                    <i class="fas fa-play-circle"></i> Clock In
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Quick Stats Panel -->
    <div class="quick-stats">
        <h3><i class="fas fa-chart-bar"></i> This Week</h3>
        
        <div class="stat-row">
            <span class="label">Total Hours</span>
            <span class="value" id="week-hours">--</span>
        </div>
        <div class="stat-row">
            <span class="label">Shifts Worked</span>
            <span class="value" id="week-shifts">--</span>
        </div>
        <div class="stat-row">
            <span class="label">This Month</span>
            <span class="value" id="month-hours">--</span>
        </div>
        
        <div class="nav-shortcuts">
            <h4>Quick Navigation</h4>
            <a href="?page=pos_terminal" class="shortcut-btn">
                <i class="fas fa-cash-register"></i> POS Terminal
            </a>
            <a href="?page=pos_schedule" class="shortcut-btn">
                <i class="fas fa-calendar-alt"></i> View Schedule
            </a>
            <a href="?page=staff_time_history" class="shortcut-btn">
                <i class="fas fa-history"></i> Full Time History
            </a>
        </div>
    </div>
</div>

<script>
const csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

// Shift data for API calls
const shiftData = <?= $activeShift ? json_encode(['id' => $activeShift['id']]) : 'null' ?>;

// Debug: Output raw values to help diagnose issues
<?php if ($activeShift): ?>
// Raw shift data for debugging
console.log('POS Time Tracking: Active shift detected');
console.log('clock_in raw:', '<?= addslashes($activeShift['clock_in'] ?? '') ?>');
<?php endif; ?>

// Store clock_in time as a timestamp from PHP for reliable parsing
<?php
$clockInTs = null;
$lunchStartTs = null;
$lunchEndTs = null;

if ($activeShift && !empty($activeShift['clock_in'])) {
    $clockInTs = strtotime($activeShift['clock_in']);
    if ($clockInTs === false) $clockInTs = null;
}
if ($activeShift && !empty($activeShift['lunch_start'])) {
    $lunchStartTs = strtotime($activeShift['lunch_start']);
    if ($lunchStartTs === false) $lunchStartTs = null;
}
if ($activeShift && !empty($activeShift['lunch_end'])) {
    $lunchEndTs = strtotime($activeShift['lunch_end']);
    if ($lunchEndTs === false) $lunchEndTs = null;
}
?>
const clockInTimestamp = <?= json_encode($clockInTs !== null ? ($clockInTs * 1000) : null) ?>;
const lunchStartTimestamp = <?= json_encode($lunchStartTs !== null ? ($lunchStartTs * 1000) : null) ?>;
const lunchEndTimestamp = <?= json_encode($lunchEndTs !== null ? ($lunchEndTs * 1000) : null) ?>;
const hasActiveShift = <?= json_encode((bool)$activeShift) ?>;

console.log('POS Timer Debug:', { clockInTimestamp, lunchStartTimestamp, lunchEndTimestamp, hasActiveShift });

let timerInterval = null;
let animationFrameId = null;

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    console.log('POS Time Tracking: DOM loaded, initializing...');
    
    // Start the current time display
    updateCurrentTime();
    setInterval(updateCurrentTime, 1000);
    
    // Start the shift timer if there's an active shift
    if (hasActiveShift && clockInTimestamp) {
        console.log('POS Timer: Starting live timer with clockInTimestamp:', clockInTimestamp);
        startLiveTimer();
    } else {
        console.log('POS Timer: Not starting timer. hasActiveShift:', hasActiveShift, 'clockInTimestamp:', clockInTimestamp);
    }
    
    loadWeeklyStats();
});

function updateCurrentTime() {
    const now = new Date();
    const el = document.getElementById('current-time-display');
    if (el) {
        el.textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
    }
}

function startLiveTimer() {
    const timerElement = document.getElementById('shift-timer');
    const timerLabelElement = document.getElementById('timer-label');
    
    console.log('startLiveTimer: Looking for elements...');
    console.log('  timerElement:', timerElement);
    console.log('  timerLabelElement:', timerLabelElement);
    
    if (!timerElement) {
        console.error('Timer: shift-timer element not found');
        return;
    }
    
    if (!clockInTimestamp || isNaN(clockInTimestamp)) {
        console.error('Timer: Invalid clock in timestamp:', clockInTimestamp);
        return;
    }
    
    console.log('startLiveTimer: clockInTimestamp is valid:', clockInTimestamp, 'Date:', new Date(clockInTimestamp));
    
    // Clear any existing intervals
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
    if (animationFrameId) {
        cancelAnimationFrame(animationFrameId);
        animationFrameId = null;
    }
    
    // Check lunch status
    const isOnLunch = lunchStartTimestamp && !lunchEndTimestamp;
    const lunchCompleted = lunchStartTimestamp && lunchEndTimestamp;
    
    // Calculate lunch duration if completed
    let lunchDurationMs = 0;
    if (lunchCompleted) {
        lunchDurationMs = lunchEndTimestamp - lunchStartTimestamp;
    }
    
    console.log('startLiveTimer: isOnLunch:', isOnLunch, 'lunchCompleted:', lunchCompleted, 'lunchDurationMs:', lunchDurationMs);
    
    // Update the label and visual indicator based on lunch status
    if (timerLabelElement) {
        if (isOnLunch) {
            timerLabelElement.textContent = 'Paused - On Lunch Break';
            timerLabelElement.classList.add('paused');
            timerElement.classList.remove('active');
        } else {
            timerLabelElement.textContent = 'Time Worked Today';
            timerLabelElement.classList.remove('paused');
            timerElement.classList.add('active');
        }
    } else if (!isOnLunch) {
        timerElement.classList.add('active');
    }
    
    // Track update count for debugging
    let updateCount = 0;
    
    function updateTimerDisplay() {
        const now = Date.now();
        let elapsedMs;
        
        if (isOnLunch) {
            // Paused - show time up until lunch started
            elapsedMs = lunchStartTimestamp - clockInTimestamp;
        } else {
            // Calculate elapsed time, subtracting lunch duration if applicable
            elapsedMs = now - clockInTimestamp - lunchDurationMs;
        }
        
        // Ensure non-negative
        if (elapsedMs < 0) elapsedMs = 0;
        
        const totalSeconds = Math.floor(elapsedMs / 1000);
        
        const hours = Math.floor(totalSeconds / 3600);
        const minutes = Math.floor((totalSeconds % 3600) / 60);
        const seconds = totalSeconds % 60;
        
        // Format as HH:MM:SS
        const timeStr = String(hours).padStart(2, '0') + ':' + 
            String(minutes).padStart(2, '0') + ':' + 
            String(seconds).padStart(2, '0');
        
        // Always update DOM to ensure timer is working
        timerElement.textContent = timeStr;
        
        // Log first few updates for debugging
        if (updateCount < 5) {
            console.log('Timer update #' + updateCount + ':', timeStr, 'elapsed:', elapsedMs);
            updateCount++;
        }
        
        // Continue the animation loop if not on lunch
        if (!isOnLunch) {
            animationFrameId = requestAnimationFrame(updateTimerDisplay);
        }
    }
    
    // Start immediately
    console.log('startLiveTimer: Calling updateTimerDisplay for the first time...');
    updateTimerDisplay();
    
    // Also use setInterval as a fallback (in case requestAnimationFrame pauses when tab is inactive)
    if (!isOnLunch) {
        timerInterval = setInterval(function() {
            // This ensures timer updates even when tab is in background
            const now = Date.now();
            let elapsedMs = now - clockInTimestamp - lunchDurationMs;
            if (elapsedMs < 0) elapsedMs = 0;
            
            const totalSeconds = Math.floor(elapsedMs / 1000);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            const seconds = totalSeconds % 60;
            
            const timeStr = String(hours).padStart(2, '0') + ':' + 
                String(minutes).padStart(2, '0') + ':' + 
                String(seconds).padStart(2, '0');
            
            timerElement.textContent = timeStr;
        }, 1000);
    }
}

function loadWeeklyStats() {
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
        if (data.success) {
            document.getElementById('week-hours').textContent = data.summary.week.total_hours + ' hrs';
            document.getElementById('week-shifts').textContent = data.summary.week.shift_count;
            document.getElementById('month-hours').textContent = data.summary.month.total_hours + ' hrs';
        }
    })
    .catch(error => console.error('Error loading stats:', error));
}

function clockIn() {
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'clock_in',
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            alert(data.message || 'Failed to clock in');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function startLunch() {
    if (!confirm('Start your lunch break?')) return;
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'start_lunch',
            shift_id: shiftData?.id,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            alert(data.message || 'Failed to start lunch break');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function endLunch() {
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'end_lunch',
            shift_id: shiftData?.id,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            alert(data.message || 'Failed to end lunch break');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}

function logoutOnly() {
    if (!confirm('Log out but keep your shift running? Use this if you need to restart the POS system.')) return;
    
    window.location.href = 'logout.php?keep_shift=1';
}

function endShift() {
    if (!confirm('End your shift and log out? This will stop your time tracking for today.')) return;
    
    fetch('process_time_tracking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'end_shift',
            shift_id: shiftData?.id,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Shift ended. Total hours: ' + data.total_hours);
            window.location.href = 'logout.php';
        } else {
            alert(data.message || 'Failed to end shift');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
}
</script>
