<?php
// Get filter parameters
$filter_period = $_GET['filter_period'] ?? 'all';
$filter_coach = $_GET['filter_coach'] ?? 'all';

// Build query for upcoming sessions
// For athletes, show sessions they're registered for
// For coaches/admins, show all sessions they're involved with
if ($user_role === 'athlete') {
    $sessions_query = "
        SELECT s.*, 
               CONCAT(c.first_name, ' ', c.last_name) as coach_name,
               st.name as session_type_name,
               l.name as location_name
        FROM sessions s
        LEFT JOIN users c ON s.coach_id = c.id
        LEFT JOIN session_types st ON s.session_type_id = st.id
        LEFT JOIN locations l ON s.location_id = l.id
        LEFT JOIN bookings b ON b.session_id = s.id AND b.user_id = ?
        WHERE b.user_id IS NOT NULL
          AND s.session_date >= NOW() 
          AND s.status = 'scheduled'
    ";
    $params = [$user_id];
} else {
    $sessions_query = "
        SELECT s.*, 
               CONCAT(c.first_name, ' ', c.last_name) as coach_name,
               st.name as session_type_name,
               l.name as location_name
        FROM sessions s
        LEFT JOIN users c ON s.coach_id = c.id
        LEFT JOIN session_types st ON s.session_type_id = st.id
        LEFT JOIN locations l ON s.location_id = l.id
        WHERE s.coach_id = ? 
          AND s.session_date >= NOW() 
          AND s.status = 'scheduled'
    ";
    $params = [$user_id];
}

// Apply period filter
if ($filter_period === 'week') {
    $sessions_query .= " AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 WEEK)";
} elseif ($filter_period === 'next_week') {
    $sessions_query .= " AND s.session_date > DATE_ADD(CURDATE(), INTERVAL 1 WEEK) AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 2 WEEK)";
} elseif ($filter_period === 'month') {
    $sessions_query .= " AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
}

// Apply coach filter
if ($filter_coach !== 'all') {
    $sessions_query .= " AND s.coach_id = ?";
    $params[] = $filter_coach;
}

$sessions_query .= " ORDER BY s.session_date LIMIT 50";

$sessions_stmt = $pdo->prepare($sessions_query);
$sessions_stmt->execute($params);
$sessions = $sessions_stmt->fetchAll();

// Get coaches for filter - based on user role
if ($user_role === 'athlete') {
    $coaches_query = "
        SELECT DISTINCT c.id, c.first_name, c.last_name
        FROM users c
        INNER JOIN sessions s ON s.coach_id = c.id
        INNER JOIN bookings b ON b.session_id = s.id
        WHERE b.user_id = ? AND c.is_active = 1
        ORDER BY c.last_name, c.first_name
    ";
    $coaches_stmt = $pdo->prepare($coaches_query);
    $coaches_stmt->execute([$user_id]);
} else {
    $coaches_query = "
        SELECT DISTINCT id, first_name, last_name
        FROM users
        WHERE role IN ('coach', 'admin', 'team_coach') AND is_active = 1
        ORDER BY last_name, first_name
    ";
    $coaches_stmt = $pdo->prepare($coaches_query);
    $coaches_stmt->execute([]);
}
$coaches = $coaches_stmt->fetchAll();

// Get current view mode
$view_mode = $_GET['view'] ?? 'list';

// Demo data for sessions if no real data exists
if (count($sessions) === 0) {
    // Use DateTime for reliable date handling
    $today = new DateTime();
    
    $demo_sessions = [
        [
            'id' => 'demo-1',
            'session_type_name' => 'Skating Skills',
            'session_date' => (clone $today)->modify('+1 day')->setTime(10, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'coach_name' => 'Coach Smith',
            'location_name' => 'Main Arena',
            'description' => 'Focus on edge work and crossovers'
        ],
        [
            'id' => 'demo-2',
            'session_type_name' => 'Power Skating',
            'session_date' => (clone $today)->modify('+2 days')->setTime(14, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 90,
            'coach_name' => 'Coach Johnson',
            'location_name' => 'Training Center',
            'description' => 'Building speed and acceleration'
        ],
        [
            'id' => 'demo-3',
            'session_type_name' => 'Stick Handling',
            'session_date' => (clone $today)->modify('+3 days')->setTime(9, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 60,
            'coach_name' => 'Coach Williams',
            'location_name' => 'Practice Rink',
            'description' => 'Puck control and deking techniques'
        ],
        [
            'id' => 'demo-4',
            'session_type_name' => 'Shooting Practice',
            'session_date' => (clone $today)->modify('+5 days')->setTime(16, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 75,
            'coach_name' => 'Coach Smith',
            'location_name' => 'Main Arena',
            'description' => 'Wrist shots and slap shots'
        ],
        [
            'id' => 'demo-5',
            'session_type_name' => 'Game Simulation',
            'session_date' => (clone $today)->modify('+7 days')->setTime(11, 0)->format('Y-m-d H:i:s'),
            'duration_minutes' => 120,
            'coach_name' => 'Coach Johnson',
            'location_name' => 'Main Arena',
            'description' => 'Full ice scrimmage with tactical focus'
        ]
    ];
    $sessions = $demo_sessions;
    $is_demo_data = true;
} else {
    $is_demo_data = false;
}
?>

<!-- Upcoming Sessions View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-alt"></i> Upcoming Sessions
    </h1>
    <p class="page-description">Your scheduled training sessions</p>
</div>

<?php if ($is_demo_data): ?>
<div class="demo-data-notice">
    <i class="fas fa-info-circle"></i>
    <span>Showing demo data. Book sessions to see your real schedule.</span>
</div>
<?php endif; ?>

<div class="sessions-content">
    <!-- Filter Box - Enhanced UI/UX -->
    <div class="filter-box">
        <div class="filter-box-header">
            <i class="fas fa-filter"></i> Filter Sessions
        </div>
        <div class="filter-box-content">
            <form method="GET" action="" class="filter-row">
                <input type="hidden" name="page" value="upcoming_sessions">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">
                <div class="filter-field">
                    <label>Time Period</label>
                    <select name="filter_period" class="form-select" id="filter-period">
                        <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>All Sessions</option>
                        <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>This Week</option>
                        <option value="next_week" <?= $filter_period === 'next_week' ? 'selected' : '' ?>>Next Week</option>
                        <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Coach</label>
                    <select name="filter_coach" class="form-select" id="filter-coach">
                        <option value="all">All Coaches</option>
                        <?php foreach ($coaches as $coach): ?>
                            <option value="<?= $coach['id'] ?>" <?= $filter_coach == $coach['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <a href="?page=upcoming_sessions&view=<?= htmlspecialchars($view_mode) ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="results-info">
            <span><?= count($sessions) ?> session<?= count($sessions) !== 1 ? 's' : '' ?> found</span>
        </div>
        <div class="view-controls">
            <div class="view-toggle">
                <a href="?page=upcoming_sessions&view=list&filter_period=<?= $filter_period ?>&filter_coach=<?= $filter_coach ?>" 
                   class="view-btn <?= $view_mode === 'list' ? 'active' : '' ?>" title="List View">
                    <i class="fas fa-list"></i>
                </a>
                <a href="?page=upcoming_sessions&view=calendar&filter_period=<?= $filter_period ?>&filter_coach=<?= $filter_coach ?>" 
                   class="view-btn <?= $view_mode === 'calendar' ? 'active' : '' ?>" title="Calendar View">
                    <i class="fas fa-calendar"></i>
                </a>
            </div>
            <a href="?page=booking" class="btn btn-primary"><i class="fas fa-plus"></i> Book Session</a>
        </div>
    </div>


    <!-- Sessions List/Calendar View -->
    <?php if ($view_mode === 'calendar'): ?>
        <!-- Calendar View -->
        <div class="sessions-calendar">
            <div class="calendar-header">
                <button class="btn-icon" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
                <h3 id="currentMonth"><?= date('F Y') ?></h3>
                <button class="btn-icon" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
            </div>
            <div class="calendar-grid" id="calendarGrid">
                <!-- Calendar grid will be populated by JavaScript -->
            </div>
        </div>
        
        <!-- Hidden session data for JavaScript -->
        <div id="sessionsData" style="display: none;">
            <?php foreach ($sessions as $session): 
                $session_datetime = strtotime($session['session_date']);
                // Format date in ISO format for JavaScript parsing
                $iso_date = date('Y-m-d', $session_datetime);
            ?>
            <div class="session-data" 
                 data-component="SessionCard"
                 data-session-id="<?= $session['id'] ?>"
                 data-date="<?= $iso_date ?>"
                 data-time="<?= date('g:i A', $session_datetime) ?>"
                 data-title="<?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?>"
                 data-coach="<?= htmlspecialchars($session['coach_name'] ?? 'TBD') ?>"
                 data-location="<?= htmlspecialchars($session['location_name'] ?? '') ?>">
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- List View -->
        <div class="sessions-list">
        <?php if (count($sessions) > 0): ?>
            <?php foreach ($sessions as $session): 
                $session_datetime = strtotime($session['session_date']);
                $session_end_time = $session_datetime + ($session['duration_minutes'] ?? 60) * 60;
                $is_demo = strpos($session['id'], 'demo-') === 0;
            ?>
            <div class="session-card" data-component="SessionCard" data-session-id="<?= $session['id'] ?>"
                 <?php if ($is_demo): ?>
                 data-is-demo="true"
                 data-session-title="<?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?>"
                 data-session-datetime="<?= date('l, F j, Y \a\t g:i A', $session_datetime) ?>"
                 data-session-end-time="<?= date('g:i A', $session_end_time) ?>"
                 data-session-duration="<?= $session['duration_minutes'] ?? 60 ?>"
                 data-session-coach="<?= htmlspecialchars($session['coach_name'] ?? 'TBD') ?>"
                 data-session-location="<?= htmlspecialchars($session['location_name'] ?? '') ?>"
                 data-session-description="<?= htmlspecialchars($session['description'] ?? '') ?>"
                 <?php endif; ?>>
                <div class="session-date">
                    <div class="date-box">
                        <span class="date-day"><?= date('d', $session_datetime) ?></span>
                        <span class="date-month"><?= strtoupper(date('M', $session_datetime)) ?></span>
                    </div>
                </div>
                <div class="session-details">
                    <h3 class="session-title"><?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?></h3>
                    <div class="session-meta">
                        <span><i class="fas fa-clock"></i> <?= date('g:i A', $session_datetime) ?> - <?= date('g:i A', $session_end_time) ?></span>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars($session['coach_name'] ?? 'TBD') ?></span>
                        <?php if ($session['location_name']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($session['description'])): ?>
                        <div class="session-description">
                            <p><?= htmlspecialchars(substr($session['description'], 0, 100)) ?><?= strlen($session['description']) > 100 ? '...' : '' ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="session-actions">
                    <button class="btn-secondary" data-action="view-session" data-session-id="<?= $session['id'] ?>"><i class="fas fa-eye"></i> View</button>
                    <?php if (strtotime($session['session_date']) > strtotime('+24 hours')): ?>
                        <button class="btn-danger" data-action="cancel-session" data-session-id="<?= $session['id'] ?>"><i class="fas fa-times"></i> Cancel</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-calendar placeholder-icon"></i>
                <p class="placeholder-text">No upcoming sessions found. Book a session to get started!</p>
            </div>
        <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<style>
/* Filter Box Styles */
.filter-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}

.filter-box-header {
    background: var(--bg-main);
    padding: 14px 20px;
    font-weight: 700;
    color: var(--text-white);
    font-size: 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-box-header i {
    color: var(--primary);
}

.filter-box-content {
    padding: 20px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    align-items: end;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-field .form-select {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    font-size: 14px;
}

.filter-field .form-select:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-actions {
    display: flex;
    flex-direction: row !important;
    gap: 8px !important;
    align-items: flex-end;
}

.filter-actions label {
    display: none;
}

/* Action Bar Styles */
.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.results-info {
    color: var(--text-dim);
    font-size: 14px;
}

.view-controls {
    display: flex;
    align-items: center;
    gap: 15px;
}

.view-toggle {
    display: flex;
    gap: 5px;
    background: var(--bg-main);
    border-radius: 8px;
    padding: 4px;
}

.view-btn {
    padding: 8px 12px;
    background: transparent;
    border: none;
    color: var(--text-dim);
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.3s;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover {
    color: var(--text-white);
    background: var(--bg-card);
}

.view-btn.active {
    background: var(--neon);
    color: white;
}

.filter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.filter-group label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
}

.sessions-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.session-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    display: flex;
    gap: 20px;
    align-items: center;
    transition: all 0.3s;
}

.session-card:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.date-box {
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 8px;
    padding: 16px;
    text-align: center;
    min-width: 80px;
}

.date-day {
    display: block;
    font-size: 28px;
    font-weight: 900;
    color: #fff;
    line-height: 1;
}

.date-month {
    display: block;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-top: 5px;
}

.session-details {
    flex: 1;
}

.session-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.session-meta {
    display: flex;
    gap: 20px;
    margin-bottom: 10px;
    flex-wrap: wrap;
}

.session-meta span {
    font-size: 14px;
    color: var(--text-dim);
}

.session-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.session-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tag {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
}

.session-actions {
    display: flex;
    gap: 10px;
}

.btn-danger {
    height: 45px;
    padding: 0 20px;
    background: transparent;
    border: 1px solid #ef4444;
    color: #ef4444;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-danger:hover {
    background: #ef4444;
    color: #fff;
}

.placeholder-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 60px 20px;
}

.sessions-calendar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
}

.calendar-grid {
    min-height: 400px;
}

.calendar-container {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 1px;
    background: var(--border);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
}

.calendar-day-header {
    background: var(--bg-main);
    padding: 12px;
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
}

.calendar-day {
    background: var(--bg-card);
    min-height: 100px;
    padding: 8px;
    position: relative;
    cursor: pointer;
    transition: all 0.3s;
}

.calendar-day:hover {
    background: rgba(107, 70, 193, 0.05);
}

.calendar-day.empty {
    background: var(--bg-main);
    cursor: default;
}

.calendar-day.today {
    background: rgba(107, 70, 193, 0.1);
    border: 2px solid var(--neon);
}

.calendar-day.has-sessions {
    background: rgba(107, 70, 193, 0.08);
}

.day-number {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.calendar-day.today .day-number {
    color: var(--neon);
}

.day-sessions {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.session-indicator {
    background: linear-gradient(135deg, var(--neon), var(--accent));
    color: white;
    padding: 4px 6px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    cursor: pointer;
    transition: all 0.3s;
}

.session-indicator:hover {
    transform: translateY(-2px);
    box-shadow: 0 2px 8px rgba(107, 70, 193, 0.4);
}

.session-indicator.more {
    background: var(--bg-main);
    color: var(--text-dim);
    cursor: default;
}

.btn-icon {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
    width: 40px;
    height: 40px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon:hover {
    background: var(--neon);
    border-color: var(--neon);
}

@media (max-width: 768px) {
    .calendar-container {
        font-size: 12px;
    }
    
    .calendar-day {
        min-height: 80px;
        padding: 4px;
    }
    
    .session-indicator {
        font-size: 9px;
        padding: 2px 4px;
    }
    
    .filter-row {
        grid-template-columns: 1fr !important;
    }
    
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .view-controls {
        justify-content: space-between;
    }
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 42px;
    padding: 0 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    border: none;
}

.btn-primary {
    background: var(--primary, #6B46C1);
    color: #fff;
}

.btn-primary:hover {
    background: var(--primary-hover, #7C3AED);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
}

.btn-secondary:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Demo Data Notice */
.demo-data-notice {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary-light);
    font-size: 14px;
}

.demo-data-notice i {
    font-size: 16px;
}

/* Session Detail Modal */
.session-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    backdrop-filter: blur(4px);
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.session-modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.session-modal {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    max-height: 80vh;
    overflow-y: auto;
    transform: scale(0.9) translateY(20px);
    transition: all 0.3s ease;
}

.session-modal-overlay.active .session-modal {
    transform: scale(1) translateY(0);
}

.session-modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.session-modal-header h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.session-modal-close {
    background: transparent;
    border: none;
    color: var(--text-dim);
    font-size: 24px;
    cursor: pointer;
    padding: 4px;
    line-height: 1;
    transition: color 0.3s;
}

.session-modal-close:hover {
    color: var(--text-white);
}

.session-modal-body {
    padding: 24px;
}

.session-modal-detail {
    margin-bottom: 16px;
}

.session-modal-detail label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.session-modal-detail span {
    display: block;
    font-size: 15px;
    color: var(--text-white);
}

.session-modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.demo-badge {
    background: linear-gradient(135deg, var(--neon), var(--accent));
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: 12px;
}
</style>

<!-- Session Detail Modal for Demo Data -->
<div class="session-modal-overlay" id="sessionDetailModal">
    <div class="session-modal">
        <div class="session-modal-header">
            <h2 id="modalSessionTitle">Session Details</h2>
            <button class="session-modal-close" onclick="closeSessionModal()">&times;</button>
        </div>
        <div class="session-modal-body">
            <div class="session-modal-detail">
                <label>Date & Time</label>
                <span id="modalSessionDateTime">-</span>
            </div>
            <div class="session-modal-detail">
                <label>Duration</label>
                <span id="modalSessionDuration">-</span>
            </div>
            <div class="session-modal-detail">
                <label>Coach</label>
                <span id="modalSessionCoach">-</span>
            </div>
            <div class="session-modal-detail">
                <label>Location</label>
                <span id="modalSessionLocation">-</span>
            </div>
            <div class="session-modal-detail">
                <label>Description</label>
                <span id="modalSessionDescription">-</span>
            </div>
        </div>
        <div class="session-modal-footer">
            <button class="btn-secondary" onclick="closeSessionModal()">Close</button>
        </div>
    </div>
</div>

<!-- Include calendar JavaScript -->
<script src="js/calendar.js"></script>

<script>
// Handle demo session view buttons
document.addEventListener('DOMContentLoaded', function() {
    // Add click handlers to all view-session buttons (capture phase for priority)
    document.querySelectorAll('[data-action="view-session"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const sessionId = this.getAttribute('data-session-id');
            const sessionCard = this.closest('.session-card');
            
            // Check if this is demo data (either by data-is-demo attribute or by ID format)
            const isDemo = (sessionCard && sessionCard.getAttribute('data-is-demo') === 'true') || 
                          (sessionId && String(sessionId).startsWith('demo-'));
            
            if (isDemo) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Populate modal with demo session data
                document.getElementById('modalSessionTitle').innerHTML = 
                    (sessionCard?.getAttribute('data-session-title') || 'Demo Session') + 
                    '<span class="demo-badge">Demo</span>';
                document.getElementById('modalSessionDateTime').textContent = 
                    (sessionCard?.getAttribute('data-session-datetime') || 'Demo Date') + 
                    (sessionCard?.getAttribute('data-session-end-time') ? ' - ' + sessionCard.getAttribute('data-session-end-time') : '');
                document.getElementById('modalSessionDuration').textContent = 
                    (sessionCard?.getAttribute('data-session-duration') || '60') + ' minutes';
                document.getElementById('modalSessionCoach').textContent = 
                    sessionCard?.getAttribute('data-session-coach') || 'Demo Coach';
                document.getElementById('modalSessionLocation').textContent = 
                    sessionCard?.getAttribute('data-session-location') || 'Not specified';
                document.getElementById('modalSessionDescription').textContent = 
                    sessionCard?.getAttribute('data-session-description') || 'No description available';
                
                // Show modal
                document.getElementById('sessionDetailModal').classList.add('active');
                return false;
            }
            // For non-demo data, let the default app.js handler take over
        }, true); // Use capture phase to run before app.js
    });
    
    // Add click handlers for cancel-session buttons on demo sessions
    document.querySelectorAll('[data-action="cancel-session"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            const sessionId = this.getAttribute('data-session-id');
            const sessionCard = this.closest('.session-card');
            
            // Check if this is demo data
            const isDemo = (sessionCard && sessionCard.getAttribute('data-is-demo') === 'true') || 
                          (sessionId && String(sessionId).startsWith('demo-'));
            
            if (isDemo) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Show info message for demo sessions using toast notification
                if (typeof window.showToast === 'function') {
                    window.showToast('This is a demo session. Book a real session to manage cancellations.', 'info');
                } else {
                    alert('This is a demo session. Book a real session to manage cancellations.');
                }
                return false;
            }
        }, true); // Use capture phase to run before app.js
    });
    
    // Close modal when clicking overlay
    document.getElementById('sessionDetailModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSessionModal();
        }
    });
    
    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && document.getElementById('sessionDetailModal').classList.contains('active')) {
            closeSessionModal();
        }
    });
});

function closeSessionModal() {
    document.getElementById('sessionDetailModal').classList.remove('active');
}
</script>
