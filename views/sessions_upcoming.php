<?php
// Get filter parameters
$filter_period = $_GET['filter_period'] ?? 'all';
$filter_coach = $_GET['filter_coach'] ?? 'all';
$filter_skill = $_GET['filter_skill'] ?? 'all';
$filter_location = $_GET['filter_location'] ?? 'all';
// Strict validation: only accept '1' as true
$show_history = isset($_GET['history']) && $_GET['history'] === '1';

// Get skill types (session types) for filter
$skills_query = "SELECT * FROM session_types ORDER BY name";
$skills = $pdo->query($skills_query)->fetchAll();

// Get locations for filter
$locations_query = "SELECT * FROM locations WHERE is_active = 1 ORDER BY name";
$locations = $pdo->query($locations_query)->fetchAll();

// Build query for upcoming sessions or session history
// Date and status conditions are hardcoded based on boolean $show_history - not user input
// For athletes, show sessions they're registered for
// For coaches/admins, show all sessions they're involved with
if ($user_role === 'athlete') {
    if ($show_history) {
        $sessions_query = "
            SELECT s.*, 
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   st.id as skill_id,
                   l.name as location_name,
                   pp.name as practice_plan_name,
                   pp.description as practice_plan_description,
                   spp.practice_plan_id,
                   b.id as booking_id,
                   b.status as booking_status
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
            LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
            LEFT JOIN bookings b ON b.session_id = s.id AND b.user_id = ?
            WHERE b.user_id IS NOT NULL
              AND s.session_date < NOW()
              AND s.status IN ('scheduled', 'completed')
        ";
    } else {
        $sessions_query = "
            SELECT s.*, 
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   st.id as skill_id,
                   l.name as location_name,
                   pp.name as practice_plan_name,
                   pp.description as practice_plan_description,
                   spp.practice_plan_id,
                   b.id as booking_id,
                   b.status as booking_status
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
            LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
            LEFT JOIN bookings b ON b.session_id = s.id AND b.user_id = ?
            WHERE b.user_id IS NOT NULL
              AND s.session_date >= NOW()
              AND s.status = 'scheduled'
              AND b.status != 'cancelled'
        ";
    }
    $params = [$user_id];
} else {
    if ($show_history) {
        $sessions_query = "
            SELECT s.*, 
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   st.id as skill_id,
                   l.name as location_name,
                   pp.name as practice_plan_name,
                   pp.description as practice_plan_description,
                   spp.practice_plan_id
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
            LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
            WHERE s.coach_id = ? 
              AND s.session_date < NOW()
              AND s.status IN ('scheduled', 'completed')
        ";
    } else {
        $sessions_query = "
            SELECT s.*, 
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   st.id as skill_id,
                   l.name as location_name,
                   pp.name as practice_plan_name,
                   pp.description as practice_plan_description,
                   spp.practice_plan_id
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
            LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
            WHERE s.coach_id = ? 
              AND s.session_date >= NOW()
              AND s.status = 'scheduled'
        ";
    }
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

// Apply skill/session type filter
if ($filter_skill !== 'all') {
    $sessions_query .= " AND s.session_type_id = ?";
    $params[] = $filter_skill;
}

// Apply location filter
if ($filter_location !== 'all') {
    $sessions_query .= " AND s.location_id = ?";
    $params[] = $filter_location;
}

// Apply ordering and limit
// For history mode, apply final limit since we don't merge with templates
// For upcoming mode, we'll apply limit after merging with template sessions
$sessions_query .= $show_history ? " ORDER BY s.session_date DESC LIMIT 50" : " ORDER BY s.session_date";

$sessions_stmt = $pdo->prepare($sessions_query);
$sessions_stmt->execute($params);
$sessions = $sessions_stmt->fetchAll();

// Also fetch sessions from training_session_templates that have show_on_landing = 1
// These are sessions created via the Products tab
if (!$show_history) {
    $template_sessions_query = "
        SELECT 
            tst.id,
            tst.name as title,
            tst.description,
            tst.session_type_id,
            tst.duration_minutes,
            tst.price,
            tst.max_participants,
            tst.coach_id,
            tst.location_id,
            tst.session_type,
            tsd.session_date as session_date,
            TIME(tsd.session_date) as session_time,
            tsd.id as template_date_id,
            tsd.team_id,
            c.first_name as coach_first_name, c.last_name as coach_last_name,
            st.name as session_type_name,
            st.id as skill_id,
            l.name as location_name,
            pp.name as practice_plan_name,
            pp.description as practice_plan_description,
            tst.practice_plan_id,
            'scheduled' as status,
            'template' as source_type
        FROM training_session_templates tst
        INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
        LEFT JOIN users c ON tst.coach_id = c.id
        LEFT JOIN session_types st ON tst.session_type_id = st.id
        LEFT JOIN locations l ON tst.location_id = l.id
        LEFT JOIN practice_plans pp ON tst.practice_plan_id = pp.id
        WHERE tst.is_active = 1
          AND tst.show_on_landing = 1
          AND tsd.session_date >= NOW()
    ";
    
    $template_params = [];
    
    // Apply period filter for template sessions
    if ($filter_period === 'week') {
        $template_sessions_query .= " AND tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 WEEK)";
    } elseif ($filter_period === 'next_week') {
        $template_sessions_query .= " AND tsd.session_date > DATE_ADD(CURDATE(), INTERVAL 1 WEEK) AND tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 2 WEEK)";
    } elseif ($filter_period === 'month') {
        $template_sessions_query .= " AND tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
    }
    
    // Apply coach filter for template sessions
    if ($filter_coach !== 'all') {
        $template_sessions_query .= " AND tst.coach_id = ?";
        $template_params[] = $filter_coach;
    }
    
    // Apply skill/session type filter for template sessions
    if ($filter_skill !== 'all') {
        $template_sessions_query .= " AND tst.session_type_id = ?";
        $template_params[] = $filter_skill;
    }
    
    // Apply location filter for template sessions
    if ($filter_location !== 'all') {
        $template_sessions_query .= " AND tst.location_id = ?";
        $template_params[] = $filter_location;
    }
    
    $template_sessions_query .= " ORDER BY tsd.session_date";
    
    $template_stmt = $pdo->prepare($template_sessions_query);
    $template_stmt->execute($template_params);
    $template_sessions = $template_stmt->fetchAll();
    
    // Merge template sessions with regular sessions
    $sessions = array_merge($sessions, $template_sessions);
    
    // Sort combined sessions by date using strtotime for reliable chronological ordering
    usort($sessions, function($a, $b) {
        $dateA = strtotime($a['session_date'] ?? '');
        $dateB = strtotime($b['session_date'] ?? '');
        return $dateA - $dateB;
    });
    
    // Limit to 50 total
    $sessions = array_slice($sessions, 0, 50);
}

// Decrypt coach PII fields in session rows
foreach ($sessions as &$s) {
    foreach (['coach_first_name', 'coach_last_name'] as $f) {
        if (!empty($s[$f])) $s[$f] = FieldEncryption::decrypt($s[$f]);
    }
}
unset($s);

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
$coaches = decryptUserRows($coaches);

// Get current view mode
$view_mode = $_GET['view'] ?? 'list';

// No demo data for sessions - show empty state when no real data exists
$is_demo_data = false;

// Use empty arrays for skills and locations if none exist (no demo data)
?>

<!-- Upcoming Sessions View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-<?= $show_history ? 'history' : 'calendar-alt' ?>"></i> 
        <?= $show_history ? 'Session History' : 'Upcoming Sessions' ?>
    </h1>
    <p class="page-description"><?= $show_history ? 'Your past training sessions' : 'Your scheduled training sessions' ?></p>
</div>

<?php if ($is_demo_data && !$show_history): ?>
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
                <?php if ($show_history): ?>
                <input type="hidden" name="history" value="1">
                <?php endif; ?>
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
                    <label>Skill Focus</label>
                    <select name="filter_skill" class="form-select" id="filter-skill">
                        <option value="all">All Skills</option>
                        <?php foreach ($skills as $skill): ?>
                            <option value="<?= $skill['id'] ?>" <?= $filter_skill == $skill['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($skill['name']) ?>
                            </option>
                        <?php endforeach; ?>
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
                <div class="filter-field">
                    <label>Location</label>
                    <select name="filter_location" class="form-select" id="filter-location">
                        <option value="all">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>" <?= $filter_location == $location['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($location['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <a href="?page=upcoming_sessions&view=<?= htmlspecialchars($view_mode) ?><?= $show_history ? '&history=1' : '' ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
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
            <div class="history-toggle">
                <?php if ($show_history): ?>
                    <a href="?page=upcoming_sessions&view=<?= htmlspecialchars($view_mode) ?>" class="btn btn-secondary">
                        <i class="fas fa-calendar-alt"></i> Upcoming
                    </a>
                <?php else: ?>
                    <a href="?page=upcoming_sessions&view=<?= htmlspecialchars($view_mode) ?>&history=1" class="btn btn-secondary">
                        <i class="fas fa-history"></i> History
                    </a>
                <?php endif; ?>
            </div>
            <div class="view-toggle">
                <a href="?page=upcoming_sessions&view=list&filter_period=<?= $filter_period ?>&filter_coach=<?= $filter_coach ?>&filter_skill=<?= $filter_skill ?>&filter_location=<?= $filter_location ?><?= $show_history ? '&history=1' : '' ?>" 
                   class="view-btn <?= $view_mode === 'list' ? 'active' : '' ?>" title="List View">
                    <i class="fas fa-list"></i>
                </a>
                <a href="?page=upcoming_sessions&view=calendar&filter_period=<?= $filter_period ?>&filter_coach=<?= $filter_coach ?>&filter_skill=<?= $filter_skill ?>&filter_location=<?= $filter_location ?><?= $show_history ? '&history=1' : '' ?>" 
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
                 data-coach="<?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: 'TBD') ?>"
                 data-location="<?= htmlspecialchars($session['location_name'] ?? '') ?>"
                 data-practice-plan="<?= htmlspecialchars($session['practice_plan_name'] ?? '') ?>">
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
                 <?php endif; ?>
                 data-session-title="<?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?>"
                 data-session-datetime="<?= date('l, F j, Y \a\t g:i A', $session_datetime) ?>"
                 data-session-end-time="<?= date('g:i A', $session_end_time) ?>"
                 data-session-duration="<?= $session['duration_minutes'] ?? 60 ?>"
                 data-session-coach="<?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: 'TBD') ?>"
                 data-session-location="<?= htmlspecialchars($session['location_name'] ?? '') ?>"
                 data-session-description="<?= htmlspecialchars($session['description'] ?? '') ?>"
                 data-session-skill="<?= htmlspecialchars($session['session_type_name'] ?? '') ?>"
                 data-session-practice-plan="<?= htmlspecialchars($session['practice_plan_name'] ?? '') ?>"
                 data-session-practice-plan-desc="<?= htmlspecialchars($session['practice_plan_description'] ?? '') ?>">
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
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: 'TBD') ?></span>
                        <?php if ($session['location_name']): ?>
                            <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($session['session_type_name']) || !empty($session['practice_plan_name'])): ?>
                    <div class="session-tags">
                        <?php if (!empty($session['session_type_name'])): ?>
                            <span class="tag skill-tag"><i class="fas fa-bullseye"></i> <?= htmlspecialchars($session['session_type_name']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($session['practice_plan_name'])): ?>
                            <span class="tag plan-tag"><i class="fas fa-clipboard-list"></i> <?= htmlspecialchars($session['practice_plan_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($session['description'])): ?>
                        <div class="session-description">
                            <p><?= htmlspecialchars(substr($session['description'], 0, 100)) ?><?= strlen($session['description']) > 100 ? '...' : '' ?></p>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="session-actions">
                    <button class="btn-secondary" data-action="view-session" data-session-id="<?= $session['id'] ?>"><i class="fas fa-eye"></i> View</button>
                    <?php if (!$show_history && strtotime($session['session_date']) > strtotime('+24 hours') && !empty($session['booking_id']) && $session['booking_status'] !== 'cancelled'): ?>
                        <button class="btn-danger" data-action="cancel-session" data-session-id="<?= $session['id'] ?>" data-booking-id="<?= $session['booking_id'] ?>"><i class="fas fa-times"></i> Cancel</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-<?= $show_history ? 'history' : 'calendar' ?> placeholder-icon"></i>
                <p class="placeholder-text"><?= $show_history ? 'No session history found.' : 'No upcoming sessions found. Book a session to get started!' ?></p>
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
    background: var(--primary);
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
    border-color: var(--primary);
    box-shadow: 0 4px 20px rgba(107, 70, 193, 0.1);
}

.date-box {
    background: linear-gradient(135deg, var(--primary), var(--accent));
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
    color: var(--primary);
    margin-right: 5px;
}

.session-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.tag {
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary);
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
    display: block;
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
    padding: 0;
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
    border: 2px solid var(--primary);
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
    color: var(--primary);
}

.day-sessions {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.session-indicator {
    background: linear-gradient(135deg, var(--primary), var(--accent));
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
    background: var(--primary);
    border-color: var(--primary);
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
    background: linear-gradient(135deg, var(--primary), var(--accent));
    color: white;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: 12px;
}

.skill-tag {
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
}

.plan-tag {
    background: rgba(16, 185, 129, 0.15);
    color: #10B981;
}

.history-toggle {
    margin-right: 10px;
}

.session-tags {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.session-tags .tag {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.session-tags .tag i {
    font-size: 10px;
}

.practice-plan-section {
    background: rgba(16, 185, 129, 0.05);
    border: 1px solid rgba(16, 185, 129, 0.2);
    border-radius: 8px;
    padding: 16px;
    margin-top: 16px;
}

.practice-plan-section h4 {
    font-size: 14px;
    font-weight: 700;
    color: #10B981;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.practice-plan-section p {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
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
                <label>Skill Focus</label>
                <span id="modalSessionSkill">-</span>
            </div>
            <div class="session-modal-detail">
                <label>Description</label>
                <span id="modalSessionDescription">-</span>
            </div>
            <div class="practice-plan-section" id="modalPracticePlanSection" style="display: none;">
                <h4><i class="fas fa-clipboard-list"></i> Practice Plan</h4>
                <p id="modalPracticePlanName">-</p>
                <p id="modalPracticePlanDesc" style="margin-top: 8px; font-size: 13px;">-</p>
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
            
            // Check if we have session card data available
            const hasCardData = sessionCard && sessionCard.getAttribute('data-session-title');
            const isDemo = (sessionCard && sessionCard.getAttribute('data-is-demo') === 'true') || 
                          (sessionId && String(sessionId).startsWith('demo-'));
            
            if (hasCardData) {
                e.preventDefault();
                e.stopPropagation();
                e.stopImmediatePropagation();
                
                // Populate modal with session data - use textContent to prevent XSS
                const titleEl = document.getElementById('modalSessionTitle');
                const title = sessionCard?.getAttribute('data-session-title') || 'Session';
                // Clear and rebuild title safely
                titleEl.textContent = title;
                if (isDemo) {
                    const badge = document.createElement('span');
                    badge.className = 'demo-badge';
                    badge.textContent = 'Demo';
                    titleEl.appendChild(badge);
                }
                
                document.getElementById('modalSessionDateTime').textContent = 
                    (sessionCard?.getAttribute('data-session-datetime') || 'Date') + 
                    (sessionCard?.getAttribute('data-session-end-time') ? ' - ' + sessionCard.getAttribute('data-session-end-time') : '');
                document.getElementById('modalSessionDuration').textContent = 
                    (sessionCard?.getAttribute('data-session-duration') || '60') + ' minutes';
                document.getElementById('modalSessionCoach').textContent = 
                    sessionCard?.getAttribute('data-session-coach') || 'TBD';
                document.getElementById('modalSessionLocation').textContent = 
                    sessionCard?.getAttribute('data-session-location') || 'Not specified';
                document.getElementById('modalSessionSkill').textContent = 
                    sessionCard?.getAttribute('data-session-skill') || 'Not specified';
                document.getElementById('modalSessionDescription').textContent = 
                    sessionCard?.getAttribute('data-session-description') || 'No description available';
                
                // Handle practice plan section
                const practicePlanSection = document.getElementById('modalPracticePlanSection');
                const practicePlanName = sessionCard?.getAttribute('data-session-practice-plan');
                const practicePlanDesc = sessionCard?.getAttribute('data-session-practice-plan-desc');
                
                if (practicePlanName) {
                    practicePlanSection.style.display = 'block';
                    document.getElementById('modalPracticePlanName').textContent = practicePlanName;
                    document.getElementById('modalPracticePlanDesc').textContent = practicePlanDesc || '';
                } else {
                    practicePlanSection.style.display = 'none';
                }
                
                // Show modal
                document.getElementById('sessionDetailModal').classList.add('active');
                return false;
            }
            // For non-demo data, let the default app.js handler take over
        }, true); // Use capture phase to run before app.js
    });
    
    // Add click handlers for cancel-session buttons
    document.querySelectorAll('[data-action="cancel-session"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            
            const sessionId = this.getAttribute('data-session-id');
            const bookingId = this.getAttribute('data-booking-id');
            const sessionCard = this.closest('.session-card');
            
            // Check if this is demo data
            const isDemo = (sessionCard && sessionCard.getAttribute('data-is-demo') === 'true') || 
                          (sessionId && String(sessionId).startsWith('demo-'));
            
            if (isDemo) {
                // Show info message for demo sessions
                if (typeof window.showToast === 'function') {
                    window.showToast('This is a demo session. Book a real session to manage cancellations.', 'info');
                } else {
                    alert('This is a demo session. Book a real session to manage cancellations.');
                }
                return false;
            }
            
            // Real booking - confirm cancellation
            if (!bookingId) {
                if (typeof window.showToast === 'function') {
                    window.showToast('Unable to cancel: Booking ID not found', 'error');
                } else {
                    alert('Unable to cancel: Booking ID not found');
                }
                return false;
            }
            
            // Get session title for confirmation message
            const sessionTitle = sessionCard ? 
                (sessionCard.querySelector('.session-title')?.textContent || 'this session') : 
                'this session';
            
            if (!confirm(`Are you sure you want to cancel ${sessionTitle}?\n\nCancellations within 24 hours of the session may not be eligible for refund.`)) {
                return false;
            }
            
            // Disable button and show loading state
            const originalHtml = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Cancelling...';
            
            // Get CSRF token
            const csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
            
            // Send cancellation request
            fetch('process_booking.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=cancel_booking&booking_id=${bookingId}&csrf_token=${encodeURIComponent(csrfToken)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message, 'success');
                    } else {
                        alert(data.message);
                    }
                    
                    // Remove the session card from the list after a short delay
                    setTimeout(() => {
                        if (sessionCard) {
                            sessionCard.style.opacity = '0';
                            sessionCard.style.transform = 'scale(0.95)';
                            setTimeout(() => {
                                sessionCard.remove();
                                
                                // Check if there are no more sessions
                                const remainingSessions = document.querySelectorAll('.session-card').length;
                                if (remainingSessions === 0) {
                                    // Reload to show empty state
                                    window.location.reload();
                                }
                            }, 300);
                        }
                    }, 1000);
                } else {
                    // Show error message
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || 'Failed to cancel booking', 'error');
                    } else {
                        alert(data.message || 'Failed to cancel booking');
                    }
                    
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = originalHtml;
                }
            })
            .catch(error => {
                console.error('Error cancelling booking:', error);
                
                if (typeof window.showToast === 'function') {
                    window.showToast('An error occurred while cancelling the booking', 'error');
                } else {
                    alert('An error occurred while cancelling the booking');
                }
                
                // Re-enable button
                this.disabled = false;
                this.innerHTML = originalHtml;
            });
        }, true); // Use capture phase
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
