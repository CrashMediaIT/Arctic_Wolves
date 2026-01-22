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
?>

<!-- Upcoming Sessions View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-alt"></i> Upcoming Sessions
    </h1>
    <p class="page-description">Your scheduled training sessions</p>
</div>

<div class="sessions-content">
    <!-- Filter Bar -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="page" value="sessions_upcoming">
            <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">
            <label>Filter by:</label>
            <select name="filter_period" class="form-input-small" data-action="auto-submit">
                <option value="all" <?= $filter_period === 'all' ? 'selected' : '' ?>>All Sessions</option>
                <option value="week" <?= $filter_period === 'week' ? 'selected' : '' ?>>This Week</option>
                <option value="next_week" <?= $filter_period === 'next_week' ? 'selected' : '' ?>>Next Week</option>
                <option value="month" <?= $filter_period === 'month' ? 'selected' : '' ?>>This Month</option>
            </select>
            <select name="filter_coach" class="form-input-small" data-action="auto-submit">
                <option value="all">All Coaches</option>
                <?php foreach ($coaches as $coach): ?>
                    <option value="<?= $coach['id'] ?>" <?= $filter_coach == $coach['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="view-controls">
            <div class="view-toggle">
                <a href="?page=sessions_upcoming&view=list&filter_period=<?= $filter_period ?>&filter_coach=<?= $filter_coach ?>" 
                   class="view-btn <?= $view_mode === 'list' ? 'active' : '' ?>" title="List View">
                    <i class="fas fa-list"></i>
                </a>
                <a href="?page=sessions_upcoming&view=calendar&filter_period=<?= $filter_period ?>&filter_coach=<?= $filter_coach ?>" 
                   class="view-btn <?= $view_mode === 'calendar' ? 'active' : '' ?>" title="Calendar View">
                    <i class="fas fa-calendar"></i>
                </a>
            </div>
            <a href="?page=sessions_booking" class="btn-primary"><i class="fas fa-plus"></i> Book Session</a>
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
            ?>
            <div class="session-data" 
                 data-component="SessionCard"
                 data-session-id="<?= $session['id'] ?>"
                 data-date="<?= date('Y-m-d', $session_datetime) ?>"
                 data-time="<?= date('g:i A', $session_datetime) ?>"
                 data-title="<?= htmlspecialchars($session['session_type_name']) ?>"
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
            ?>
            <div class="session-card" data-component="SessionCard" data-session-id="<?= $session['id'] ?>">
                <div class="session-date">
                    <div class="date-box">
                        <span class="date-day"><?= date('d', $session_datetime) ?></span>
                        <span class="date-month"><?= strtoupper(date('M', $session_datetime)) ?></span>
                    </div>
                </div>
                <div class="session-details">
                    <h3 class="session-title"><?= htmlspecialchars($session['session_type_name']) ?></h3>
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
.filter-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
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
    padding: 15px;
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
}
</style>

<!-- Include calendar JavaScript -->
<script src="js/calendar.js"></script>
