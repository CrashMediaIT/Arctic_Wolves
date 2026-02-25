<?php
/**
 * Coach Calendar View
 * Displays all company sessions for coaches
 * Allows adding practice plans and navigating to drill recording
 * Supports creating private sessions for assigned athletes
 */

// Only coaches and admins can access this page
$allowed_roles = ['coach', 'coach_plus', 'health_coach', 'team_coach', 'admin'];
if (!in_array($user_role, $allowed_roles)) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get filter parameters
$filter_coach = $_GET['filter_coach'] ?? 'all';
$filter_location = $_GET['filter_location'] ?? 'all';
$view_mode = $_GET['view'] ?? 'calendar';

// Get all sessions for the company (includes sessions linked from packages)
$sessions_query = "
    SELECT s.*, 
           c.first_name as coach_first_name, c.last_name as coach_last_name,
           c.id as coach_user_id,
           st.name as session_type_name,
           l.name as location_name,
           pp.name as practice_plan_name,
           pp.id as practice_plan_id,
           COUNT(DISTINCT b.id) as registered_count,
           MAX(CASE WHEN sc.coach_id = ? THEN 1 ELSE 0 END) as is_assigned_coach,
           GROUP_CONCAT(DISTINCT pkg.name ORDER BY pkg.name SEPARATOR ', ') as package_names
    FROM sessions s
    LEFT JOIN users c ON s.coach_id = c.id
    LEFT JOIN session_coaches sc ON sc.session_id = s.id
    LEFT JOIN session_types st ON s.session_type_id = st.id
    LEFT JOIN locations l ON s.location_id = l.id
    LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
    LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
    LEFT JOIN bookings b ON b.session_id = s.id
    LEFT JOIN package_sessions ps ON ps.session_id = s.id
    LEFT JOIN packages pkg ON ps.package_id = pkg.id
    WHERE s.session_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
      AND s.status = 'scheduled'
";
$params = [$user_id];

if ($filter_coach !== 'all') {
    $sessions_query .= " AND (s.coach_id = ? OR sc.coach_id = ?)";
    $params[] = $filter_coach;
    $params[] = $filter_coach;
}
if ($filter_location !== 'all') {
    $sessions_query .= " AND s.location_id = ?";
    $params[] = $filter_location;
}

$sessions_query .= " GROUP BY s.id ORDER BY s.session_date LIMIT 100";
$sessions_stmt = $pdo->prepare($sessions_query);
$sessions_stmt->execute($params);
$sessions = $sessions_stmt->fetchAll();
$sessions = decryptUserRows($sessions);

// Also fetch sessions from training session templates (created in products area)
$template_sessions_query = "
    SELECT tsd.id as id,
           tsd.session_date as session_date,
           TIME(tsd.session_date) as session_time,
           tst.name as title,
           tst.description,
           tst.duration_minutes,
           tst.price,
           tst.max_participants,
           tst.coach_id,
           tst.location_id,
           tst.session_type_id,
           tst.practice_plan_id,
           tst.id as template_id,
           c.first_name as coach_first_name, c.last_name as coach_last_name,
           c.id as coach_user_id,
           st.name as session_type_name,
           l.name as location_name,
           pp.name as practice_plan_name,
           pp.id as plan_id,
           0 as registered_count,
           0 as is_assigned_coach,
           NULL as package_names,
           'scheduled' as status
    FROM training_session_dates tsd
    INNER JOIN training_session_templates tst ON tsd.template_id = tst.id
    LEFT JOIN users c ON tst.coach_id = c.id
    LEFT JOIN session_types st ON tst.session_type_id = st.id
    LEFT JOIN locations l ON tst.location_id = l.id
    LEFT JOIN practice_plans pp ON tst.practice_plan_id = pp.id
    WHERE tsd.session_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
      AND tsd.is_active = 1
      AND tst.is_active = 1
      AND tsd.session_id IS NULL /* Only include template dates not yet linked to an actual session record */
";
$template_params = [];

if ($filter_coach !== 'all') {
    $template_sessions_query .= " AND tst.coach_id = ?";
    $template_params[] = $filter_coach;
}
if ($filter_location !== 'all') {
    $template_sessions_query .= " AND tst.location_id = ?";
    $template_params[] = $filter_location;
}

$template_sessions_query .= " ORDER BY tsd.session_date LIMIT 100";

try {
    $template_stmt = $pdo->prepare($template_sessions_query);
    $template_stmt->execute($template_params);
    $template_sessions = $template_stmt->fetchAll();
    $template_sessions = decryptUserRows($template_sessions);

    // Mark template sessions and merge with regular sessions
    foreach ($template_sessions as &$ts) {
        $ts['is_template_session'] = true;
        $ts['practice_plan_id'] = $ts['plan_id'] ?? $ts['practice_plan_id'] ?? null;
    }
    unset($ts);

    $sessions = array_merge($sessions, $template_sessions);

    // Sort merged sessions by date
    usort($sessions, function($a, $b) {
        return strtotime($a['session_date']) - strtotime($b['session_date']);
    });
} catch (PDOException $e) {
    // If training_session_templates/dates tables don't exist yet, just continue with regular sessions
    error_log("Template sessions fetch note: " . $e->getMessage());
}

// Also load game_schedules events for teams assigned to this coach
try {
    $gp_events_query = "
        SELECT gs.id, gs.opponent_team as title, gs.game_date as session_date,
               TIME(gs.game_date) as session_time, 60 as duration_minutes,
               gs.game_type, gs.status, gs.notes as description,
               t.name as team_name, l.name as location_name,
               gs.is_home_game
        FROM game_schedules gs
        INNER JOIN teams t ON gs.team_id = t.id
        LEFT JOIN locations l ON gs.location_id = l.id
        LEFT JOIN team_coach_assignments tca ON tca.team_id = gs.team_id
        WHERE gs.game_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
          AND gs.status IN ('scheduled', 'in_progress')
          AND (tca.coach_id = ? OR t.coach_id = ?)
        GROUP BY gs.id
        ORDER BY gs.game_date
        LIMIT 100
    ";
    $gp_stmt = $pdo->prepare($gp_events_query);
    $gp_stmt->execute([$user_id, $user_id]);
    $gp_events = $gp_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($gp_events as &$ge) {
        $ge['is_gameplan_event'] = true;
        $ge['session_type_name'] = ucfirst($ge['game_type'] ?? 'regular');
        $ge['coach_first_name'] = '';
        $ge['coach_last_name'] = '';
        $ge['registered_count'] = 0;
        $ge['is_assigned_coach'] = 1;
        $ge['package_names'] = null;
        $ge['practice_plan_name'] = null;
        $ge['practice_plan_id'] = null;
        $ge['title'] = ($ge['game_type'] === 'practice')
            ? ($ge['team_name'] . ' Practice')
            : ($ge['team_name'] . ' vs ' . ($ge['title'] ?? 'TBD'));
        $ge['arena'] = $ge['location_name'] ?? '';
    }
    unset($ge);

    $sessions = array_merge($sessions, $gp_events);
    usort($sessions, function($a, $b) {
        return strtotime($a['session_date']) - strtotime($b['session_date']);
    });
} catch (PDOException $e) {
    error_log("Game plan events fetch note: " . $e->getMessage());
}

// Get coaches, locations, practice plans, session types
$coaches = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('coach', 'coach_plus', 'admin', 'team_coach', 'health_coach') AND is_active = 1 ORDER BY last_name, first_name")->fetchAll();
$coaches = decryptUserRows($coaches);
$locations = $pdo->query("SELECT * FROM locations WHERE is_active = 1 ORDER BY name")->fetchAll();
$practice_plans = $pdo->query("SELECT id, name FROM practice_plans ORDER BY created_at DESC LIMIT 50")->fetchAll();
$session_types = $pdo->query("SELECT * FROM session_types ORDER BY name")->fetchAll();

// Fetch admin-created private/semi-private session templates (from Products > Sessions)
$private_templates = [];
try {
    $pt_stmt = $pdo->query("
        SELECT tst.id, tst.name, tst.description, tst.duration_minutes, tst.price,
               tst.max_participants, tst.session_type_id, tst.practice_plan_id,
               'private' as privacy_type
        FROM training_session_templates tst
        WHERE tst.is_active = 1
        ORDER BY tst.name
    ");
    $private_templates = $pt_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Private templates fetch note: " . $e->getMessage());
}

// Get users assigned to this coach (any role can receive session assignments)
$assigned_athletes_stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.role FROM users u WHERE u.is_active = 1 AND (u.assigned_coach_id = ? OR u.created_by_coach_id = ?) ORDER BY u.last_name, u.first_name");
$assigned_athletes_stmt->execute([$user_id, $user_id]);
$assigned_athletes = $assigned_athletes_stmt->fetchAll();
$assigned_athletes = decryptUserRows($assigned_athletes);

// No demo data - show actual data from database only
$is_demo_data = false;
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-calendar"></i> Calendar</h1>
    <p class="page-description">View and manage all company sessions</p>
</div>

<div class="calendar-content">
    <div class="filter-box">
        <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Sessions</div>
        <div class="filter-box-content">
            <form method="GET" action="" class="filter-row">
                <input type="hidden" name="page" value="coach_calendar">
                <input type="hidden" name="view" value="<?= htmlspecialchars($view_mode) ?>">
                <div class="filter-field">
                    <label>Coach</label>
                    <select name="filter_coach" class="form-select">
                        <option value="all">All Coaches</option>
                        <?php foreach ($coaches as $coach): ?>
                            <option value="<?= $coach['id'] ?>" <?= $filter_coach == $coach['id'] ? 'selected' : '' ?>><?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Location</label>
                    <select name="filter_location" class="form-select">
                        <option value="all">All Locations</option>
                        <?php foreach ($locations as $location): ?>
                            <option value="<?= $location['id'] ?>" <?= $filter_location == $location['id'] ? 'selected' : '' ?>><?= htmlspecialchars($location['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                    <a href="?page=coach_calendar&view=<?= htmlspecialchars($view_mode) ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <div class="action-bar">
        <div class="results-info"><span><?= count($sessions) ?> session<?= count($sessions) !== 1 ? 's' : '' ?></span></div>
        <div class="view-controls">
            <div class="view-toggle">
                <a href="?page=coach_calendar&view=list&filter_coach=<?= $filter_coach ?>&filter_location=<?= $filter_location ?>" class="view-btn <?= $view_mode === 'list' ? 'active' : '' ?>"><i class="fas fa-list"></i></a>
                <a href="?page=coach_calendar&view=calendar&filter_coach=<?= $filter_coach ?>&filter_location=<?= $filter_location ?>" class="view-btn <?= $view_mode === 'calendar' ? 'active' : '' ?>"><i class="fas fa-calendar"></i></a>
            </div>
            <button class="btn btn-primary" onclick="openPrivateSessionModal()"><i class="fas fa-plus"></i> Create Private Session</button>
        </div>
    </div>

    <?php if ($view_mode === 'calendar'): ?>
    <div class="sessions-calendar">
        <div class="calendar-header">
            <button class="btn-icon" id="prevMonth"><i class="fas fa-chevron-left"></i></button>
            <h3 id="currentMonth"><?= date('F Y') ?></h3>
            <button class="btn-icon" id="nextMonth"><i class="fas fa-chevron-right"></i></button>
        </div>
        <div class="calendar-weekdays"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
        <div class="calendar-grid" id="calendarGrid"></div>
    </div>
    <div id="sessionsData" style="display: none;">
        <?php foreach ($sessions as $session):
            $dt = strtotime($session['session_date']);
            $sessionTimeVal = !empty($session['session_time']) ? $session['session_time'] : (!empty($session['start_time']) ? $session['start_time'] : null);
            $timeStr = $sessionTimeVal ? date('g:i A', strtotime($sessionTimeVal)) : '';
            if ($sessionTimeVal) {
                $startTs = strtotime(date('Y-m-d', $dt) . ' ' . $sessionTimeVal);
            } else {
                $startTs = $dt;
            }
            $end = $startTs + ($session['duration_minutes'] ?? 60) * 60;
            $is_mine_cal = ($session['coach_user_id'] == $user_id || ($session['is_assigned_coach'] ?? 0) > 0);
        ?>
        <div class="session-data" data-session-id="<?= $session['id'] ?>" data-date="<?= date('Y-m-d', $dt) ?>" data-time="<?= $timeStr ?>" data-title="<?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?>" data-coach="<?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: '') ?>" data-location="<?= htmlspecialchars($session['location_name'] ?? '') ?>" data-datetime="<?= date('l, F j, Y \a\t g:i A', $startTs) ?>" data-end-time="<?= date('g:i A', $end) ?>" data-duration="<?= $session['duration_minutes'] ?? 60 ?>" data-description="<?= htmlspecialchars($session['description'] ?? '') ?>" data-practice-plan="<?= htmlspecialchars($session['practice_plan_name'] ?? '') ?>" data-practice-plan-id="<?= $session['practice_plan_id'] ?? '' ?>" data-is-mine="<?= $is_mine_cal ? '1' : '0' ?>"></div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="sessions-list">
        <?php if (count($sessions) > 0): ?>
            <?php foreach ($sessions as $session): 
                $dt = strtotime($session['session_date']);
                $sessionTimeVal = !empty($session['session_time']) ? $session['session_time'] : (!empty($session['start_time']) ? $session['start_time'] : null);
                if ($sessionTimeVal) {
                    $startTs = strtotime(date('Y-m-d', $dt) . ' ' . $sessionTimeVal);
                } else {
                    $startTs = $dt;
                }
                $end = $startTs + ($session['duration_minutes'] ?? 60) * 60;
                $is_mine = ($session['coach_user_id'] == $user_id || ($session['is_assigned_coach'] ?? 0) > 0);
            ?>
            <div class="session-card <?= $is_mine ? 'my-session' : '' ?>" data-session-id="<?= $session['id'] ?>" data-session-title="<?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?>" data-session-datetime="<?= date('l, F j, Y \a\t g:i A', $startTs) ?>" data-session-end-time="<?= date('g:i A', $end) ?>" data-session-duration="<?= $session['duration_minutes'] ?? 60 ?>" data-session-coach="<?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: 'TBD') ?>" data-session-location="<?= htmlspecialchars($session['location_name'] ?? '') ?>" data-session-description="<?= htmlspecialchars($session['description'] ?? '') ?>" data-session-practice-plan="<?= htmlspecialchars($session['practice_plan_name'] ?? '') ?>" data-session-practice-plan-id="<?= $session['practice_plan_id'] ?? '' ?>" data-is-mine="<?= $is_mine ? '1' : '0' ?>">
                <div class="session-date">
                    <div class="date-box <?= $is_mine ? 'my-session-badge' : '' ?>">
                        <span class="date-day"><?= date('d', $dt) ?></span>
                        <span class="date-month"><?= strtoupper(date('M', $dt)) ?></span>
                    </div>
                    <?php if ($is_mine): ?><span class="my-session-label">Your Session</span><?php endif; ?>
                </div>
                <div class="session-details">
                    <h3 class="session-title"><?= htmlspecialchars($session['session_type_name'] ?? $session['title'] ?? 'Session') ?></h3>
                    <div class="session-meta">
                        <span><i class="fas fa-clock"></i> <?= date('g:i A', $startTs) ?> - <?= date('g:i A', $end) ?></span>
                        <span><i class="fas fa-user"></i> <?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: 'TBD') ?></span>
                        <?php if (!empty($session['location_name'])): ?><span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span><?php endif; ?>
                        <span><i class="fas fa-users"></i> <?= $session['registered_count'] ?? 0 ?> registered</span>
                    </div>
                    <div class="session-tags">
                        <?php if (!empty($session['practice_plan_name'])): ?>
                            <span class="tag plan-tag"><i class="fas fa-clipboard-list"></i> <?= htmlspecialchars($session['practice_plan_name']) ?></span>
                        <?php else: ?>
                            <span class="tag no-plan-tag"><i class="fas fa-exclamation-circle"></i> No Practice Plan</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="session-actions">
                    <button class="btn btn-secondary" onclick="openSessionDetailModal(this.closest('.session-card'))"><i class="fas fa-eye"></i> View</button>
                    <?php if ($is_mine || $user_role === 'admin'): ?>
                    <button class="btn btn-secondary" onclick="openAssignPlanModal('<?= $session['id'] ?>', '<?= $session['practice_plan_id'] ?? '' ?>')"><i class="fas fa-clipboard-list"></i> <?= !empty($session['practice_plan_name']) ? 'Change' : 'Add' ?> Plan</button>
                    <a href="?page=coach_session_evaluations&session_id=<?= intval($session['id']) ?>" class="btn btn-secondary"><i class="fas fa-clipboard-check"></i> Evaluation</a>
                    <?php endif; ?>
                    <a href="?page=record_drill_video&session_id=<?= $session['id'] ?>" class="btn btn-secondary"><i class="fas fa-video"></i> Record</a>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container"><i class="fas fa-calendar placeholder-icon"></i><p class="placeholder-text">No sessions found.</p></div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modals -->
<div class="session-modal-overlay" id="sessionDetailModal">
    <div class="session-modal">
        <div class="session-modal-header"><h2 id="modalSessionTitle">Session Details</h2><button class="session-modal-close" onclick="closeSessionDetailModal()">&times;</button></div>
        <div class="session-modal-body">
            <div class="session-modal-detail"><label>Date & Time</label><span id="modalSessionDateTime">-</span></div>
            <div class="session-modal-detail"><label>Duration</label><span id="modalSessionDuration">-</span></div>
            <div class="session-modal-detail"><label>Coach</label><span id="modalSessionCoach">-</span></div>
            <div class="session-modal-detail"><label>Location</label><span id="modalSessionLocation">-</span></div>
            <div class="session-modal-detail"><label>Description</label><span id="modalSessionDescription">-</span></div>
            <div class="practice-plan-section" id="modalPracticePlanSection" style="display: none;"><h4><i class="fas fa-clipboard-list"></i> Practice Plan</h4><p id="modalPracticePlanName">-</p></div>
        </div>
        <div class="session-modal-footer">
            <button class="btn btn-secondary" onclick="closeSessionDetailModal()">Close</button>
            <button class="btn btn-secondary" id="modalAssignPlanBtn" style="display: none;" onclick="closeSessionDetailModal()"><i class="fas fa-clipboard-list"></i> <span id="modalAssignPlanLabel">Add Plan</span></button>
            <a href="#" id="modalEvaluationLink" class="btn btn-secondary" style="display: none;"><i class="fas fa-clipboard-check"></i> Session Evaluation</a>
            <a href="#" id="modalRecordLink" class="btn btn-secondary"><i class="fas fa-video"></i> Record Drill</a>
        </div>
    </div>
</div>

<div class="session-modal-overlay" id="assignPlanModal">
    <div class="session-modal">
        <div class="session-modal-header"><h2>Assign Practice Plan</h2><button class="session-modal-close" onclick="closeAssignPlanModal()">&times;</button></div>
        <div class="session-modal-body">
            <form id="assignPlanForm" method="POST" action="process_edit_session.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="assign_practice_plan">
                <input type="hidden" name="session_id" id="assignPlanSessionId" value="">
                <div class="form-group">
                    <label>Select Practice Plan</label>
                    <select name="practice_plan_id" id="assignPlanSelect" class="form-select" required>
                        <option value="">-- Select Plan --</option>
                        <?php foreach ($practice_plans as $plan): ?><option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions"><button type="button" class="btn btn-secondary" onclick="closeAssignPlanModal()">Cancel</button><button type="submit" class="btn btn-primary">Assign Plan</button></div>
            </form>
        </div>
    </div>
</div>

<div class="session-modal-overlay" id="privateSessionModal">
    <div class="session-modal session-modal-large">
        <div class="session-modal-header"><h2><i class="fas fa-user-lock"></i> Create Private Session</h2><button class="session-modal-close" onclick="closePrivateSessionModal()">&times;</button></div>
        <div class="session-modal-body">
            <p class="modal-description">Create a private or semi-private session for your assigned athletes.</p>
            <form id="privateSessionForm" method="POST" action="process_create_session.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create_private_session">
                <div class="form-row">
                    <div class="form-group"><label>Session Privacy <span class="required">*</span></label>
                        <select name="privacy_type" id="privacyTypeSelect" class="form-select" required>
                            <option value="">-- Select Type --</option>
                            <option value="private">Private (1-on-1)</option>
                            <option value="semi_private">Semi-Private (Small Group)</option>
                        </select>
                    </div>
                    <div class="form-group"><label>Session Product <span class="required">*</span></label>
                        <select name="template_id" id="templateSelect" class="form-select" required>
                            <option value="">-- Select Session --</option>
                            <?php foreach ($private_templates as $tpl): ?>
                            <option value="<?= $tpl['id'] ?>" data-price="<?= $tpl['price'] ?? 0 ?>" data-duration="<?= $tpl['duration_minutes'] ?? 60 ?>" data-max="<?= $tpl['max_participants'] ?? '' ?>" data-session-type="<?= $tpl['session_type_id'] ?? '' ?>" data-plan="<?= $tpl['practice_plan_id'] ?? '' ?>"><?= htmlspecialchars($tpl['name']) ?> ($<?= number_format($tpl['price'] ?? 0, 2) ?>)</option>
                            <?php endforeach; ?>
                            <?php if (empty($private_templates)): ?>
                            <?php foreach ($session_types as $type): ?>
                            <option value="st_<?= $type['id'] ?>" data-price="<?= $type['price'] ?? $type['default_price'] ?? 0 ?>" data-duration="60"><?= htmlspecialchars($type['name']) ?> ($<?= number_format($type['price'] ?? $type['default_price'] ?? 0, 2) ?>)</option>
                            <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Location <span class="required">*</span></label><select name="location_id" class="form-select" required><option value="">-- Select --</option><?php foreach ($locations as $loc): ?><option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Date <span class="required">*</span></label><input type="date" name="session_date" class="form-input" required min="<?= date('Y-m-d') ?>"></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Time <span class="required">*</span></label><select name="session_time" class="form-select" required><option value="">-- Select --</option><?php for ($h = 6; $h <= 21; $h++): ?><option value="<?= sprintf('%02d:00', $h) ?>"><?= date('g:i A', strtotime("$h:00")) ?></option><?php endfor; ?></select></div>
                    <div class="form-group"><label>Duration</label><select name="duration_minutes" id="durationSelect" class="form-select"><option value="30">30 min</option><option value="45">45 min</option><option value="60" selected>60 min</option><option value="90">90 min</option></select></div>
                </div>
                <div class="form-row">
                    <div class="form-group"><label>Practice Plan</label><select name="practice_plan_id" class="form-select"><option value="">-- No Plan --</option><?php foreach ($practice_plans as $plan): ?><option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"></div>
                </div>
                <div class="form-group"><label>Select Athletes (Optional)</label>
                    <p class="help-text" style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px;">Optionally pre-assign athletes to this session. Leave empty for an open session.</p>
                    <div class="athlete-selection-grid">
                        <?php if (count($assigned_athletes) > 0): foreach ($assigned_athletes as $athlete): ?>
                            <label class="athlete-checkbox"><input type="checkbox" name="athlete_ids[]" value="<?= $athlete['id'] ?>"><span><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></span></label>
                        <?php endforeach; else: ?><p class="no-athletes-notice">No athletes assigned to you yet. This session will be available for any athlete to book.</p><?php endif; ?>
                    </div>
                </div>
                <div class="form-group"><label>Description</label><textarea name="description" class="form-textarea" rows="2"></textarea></div>
                <div class="form-actions"><button type="button" class="btn btn-secondary" onclick="closePrivateSessionModal()">Cancel</button><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Session</button></div>
            </form>
        </div>
    </div>
</div>

<style>
.calendar-content { padding: 0; }
.filter-box { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 24px; overflow: hidden; }
.filter-box-header { background: var(--bg-main); padding: 14px 20px; font-weight: 700; color: var(--text-white); font-size: 14px; border-bottom: 1px solid var(--border); display: flex; align-items: center; gap: 10px; }
.filter-box-header i { color: var(--primary); }
.filter-box-content { padding: 20px; }
.filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end; }
.filter-field { display: flex; flex-direction: column; gap: 8px; }
.filter-field label { font-size: 12px; font-weight: 600; color: var(--text-dim); text-transform: uppercase; }
.filter-actions { display: flex; flex-direction: row !important; gap: 8px !important; }
.action-bar { display: flex; justify-content: space-between; align-items: center; gap: 20px; margin-bottom: 24px; flex-wrap: wrap; }
.results-info { color: var(--text-dim); font-size: 14px; }
.view-controls { display: flex; align-items: center; gap: 15px; }
.view-toggle { display: flex; gap: 5px; background: var(--bg-main); border-radius: 8px; padding: 4px; }
.view-btn { padding: 8px 12px; background: transparent; border: none; color: var(--text-dim); cursor: pointer; border-radius: 6px; transition: all 0.3s; text-decoration: none; display: flex; align-items: center; justify-content: center; }
.view-btn:hover { color: var(--text-white); background: var(--bg-card); }
.view-btn.active { background: var(--primary); color: white; }
.sessions-list { display: flex; flex-direction: column; gap: 15px; }
.session-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 20px; display: flex; gap: 20px; align-items: center; transition: all 0.3s; }
.session-card:hover { border-color: var(--primary); }
.session-card.my-session { border-left: 4px solid var(--primary); }
.date-box { background: linear-gradient(135deg, var(--primary), var(--primary-light)); border-radius: 8px; padding: 16px; text-align: center; min-width: 80px; }
.date-box.my-session-badge { background: linear-gradient(135deg, #10B981, #059669); }
.date-day { display: block; font-size: 28px; font-weight: 900; color: #fff; line-height: 1; }
.date-month { display: block; font-size: 14px; font-weight: 700; color: #fff; margin-top: 5px; }
.my-session-label { font-size: 10px; font-weight: 700; color: #10B981; text-transform: uppercase; margin-top: 8px; display: block; text-align: center; }
.session-details { flex: 1; }
.session-title { font-size: 18px; font-weight: 700; color: var(--text-white); margin-bottom: 10px; }
.session-meta { display: flex; gap: 20px; margin-bottom: 10px; flex-wrap: wrap; }
.session-meta span { font-size: 14px; color: var(--text-dim); }
.session-meta i { color: var(--primary); margin-right: 5px; }
.session-tags { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
.tag { padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; }
.plan-tag { background: rgba(16, 185, 129, 0.15); color: #10B981; }
.no-plan-tag { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
.session-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.sessions-calendar { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
.calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.calendar-header h3 { font-size: 20px; font-weight: 700; color: var(--text-white); margin: 0; }
.btn-icon { width: 40px; height: 40px; background: transparent; border: 1px solid var(--border); border-radius: 8px; color: var(--text-white); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.3s; }
.btn-icon:hover { background: var(--primary); border-color: var(--primary); }
.calendar-weekdays { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 8px; }
.calendar-weekdays span { text-align: center; font-size: 12px; font-weight: 700; color: var(--text-dim); padding: 8px 0; text-transform: uppercase; }
.calendar-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; }
.calendar-day { min-height: 100px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 8px; cursor: pointer; transition: all 0.3s; }
.calendar-day:hover { border-color: var(--primary); }
.calendar-day.other-month { opacity: 0.4; }
.calendar-day.today { border-color: var(--primary); background: rgba(107, 70, 193, 0.1); }
.calendar-day.has-sessions { background: rgba(107, 70, 193, 0.08); }
.day-number { font-size: 14px; font-weight: 700; color: var(--text-white); margin-bottom: 8px; }
.session-indicator { background: linear-gradient(135deg, var(--primary), var(--primary-light)); color: white; padding: 4px 6px; border-radius: 4px; font-size: 11px; font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.session-modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); backdrop-filter: blur(4px); z-index: 1000; display: flex; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: all 0.3s ease; }
.session-modal-overlay.active { opacity: 1; visibility: visible; }
.session-modal { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; width: 90%; max-width: 500px; max-height: 80vh; overflow-y: auto; transform: scale(0.9) translateY(20px); transition: all 0.3s ease; }
.session-modal-large { max-width: 700px; }
.session-modal-overlay.active .session-modal { transform: scale(1) translateY(0); }
.session-modal-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; }
.session-modal-header h2 { font-size: 20px; font-weight: 700; color: var(--text-white); margin: 0; display: flex; align-items: center; gap: 10px; }
.session-modal-header h2 i { color: var(--primary); }
.session-modal-close { background: transparent; border: none; color: var(--text-dim); font-size: 24px; cursor: pointer; }
.session-modal-close:hover { color: var(--text-white); }
.session-modal-body { padding: 24px; }
.modal-description { font-size: 14px; color: var(--text-dim); margin-bottom: 20px; }
.session-modal-detail { margin-bottom: 16px; }
.session-modal-detail label { display: block; font-size: 12px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; margin-bottom: 6px; }
.session-modal-detail span { display: block; font-size: 15px; color: var(--text-white); }
.practice-plan-section { background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 16px; margin-top: 16px; }
.practice-plan-section h4 { font-size: 14px; font-weight: 700; color: #10B981; margin: 0 0 8px 0; display: flex; align-items: center; gap: 8px; }
.session-modal-footer { padding: 16px 24px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 12px; }
.form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; margin-bottom: 20px; }
.form-group { margin-bottom: 16px; }
.form-group label { display: block; font-size: 13px; font-weight: 600; color: var(--text-dim); margin-bottom: 8px; }
.form-group label .required { color: #EF4444; }
.form-select, .form-input, .form-textarea { width: 100%; padding: 12px 16px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white); font-size: 14px; }
.form-select:focus, .form-input:focus, .form-textarea:focus { outline: none; border-color: var(--primary); }
.form-textarea { resize: vertical; min-height: 60px; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border); }
.athlete-selection-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px; max-height: 150px; overflow-y: auto; padding: 12px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; }
.athlete-checkbox { display: flex; align-items: center; gap: 10px; padding: 8px 12px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 6px; cursor: pointer; }
.athlete-checkbox:hover { border-color: var(--primary); }
.athlete-checkbox input[type="checkbox"] { width: 18px; height: 18px; }
.athlete-checkbox span { font-size: 14px; color: var(--text-white); }
.no-athletes-notice { color: var(--text-dim); font-size: 14px; text-align: center; padding: 20px; }
.btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; height: 42px; padding: 0 20px; border-radius: 8px; font-size: 14px; font-weight: 600; text-decoration: none; cursor: pointer; border: none; }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-hover); }
.btn-secondary { background: transparent; border: 1px solid var(--border); color: var(--text-white); }
.btn-secondary:hover { border-color: var(--primary); color: var(--primary); }
.demo-data-notice { background: rgba(107, 70, 193, 0.1); border: 1px solid rgba(107, 70, 193, 0.3); border-radius: 8px; padding: 12px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; color: var(--primary-light); font-size: 14px; }
.placeholder-container { background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 60px 20px; text-align: center; }
.placeholder-icon { font-size: 48px; color: var(--primary); opacity: 0.4; margin-bottom: 16px; }
.placeholder-text { color: var(--text-dim); font-size: 16px; }
@media (max-width: 768px) { .filter-row, .form-row { grid-template-columns: 1fr; } .session-card { flex-direction: column; text-align: center; } .session-actions { justify-content: center; } }
</style>

<script>
var isAdmin = <?= ($user_role === 'admin') ? 'true' : 'false' ?>;
document.addEventListener('DOMContentLoaded', function() {
    let currentMonth = new Date().getMonth(), currentYear = new Date().getFullYear();
    const sessionsData = [];
    document.querySelectorAll('#sessionsData .session-data').forEach(el => {
        sessionsData.push({
            id: el.dataset.sessionId,
            date: el.dataset.date,
            time: el.dataset.time,
            title: el.dataset.title,
            coach: el.dataset.coach || 'TBD',
            location: el.dataset.location || '',
            datetime: el.dataset.datetime || '',
            endTime: el.dataset.endTime || '',
            duration: el.dataset.duration || '60',
            description: el.dataset.description || '',
            practicePlan: el.dataset.practicePlan || '',
            practicePlanId: el.dataset.practicePlanId || '',
            isMine: el.dataset.isMine === '1'
        });
    });
    
    function renderCalendar(month, year) {
        const grid = document.getElementById('calendarGrid'), titleEl = document.getElementById('currentMonth');
        if (!grid || !titleEl) return;
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        titleEl.textContent = monthNames[month] + ' ' + year;
        grid.innerHTML = '';
        const firstDay = new Date(year, month, 1).getDay(), daysInMonth = new Date(year, month + 1, 0).getDate();
        const today = new Date(), todayStr = today.toISOString().split('T')[0];
        const sessionDates = {};
        sessionsData.forEach(s => { if (!sessionDates[s.date]) sessionDates[s.date] = []; sessionDates[s.date].push(s); });
        const prevMonthDays = new Date(year, month, 0).getDate();
        for (let i = firstDay - 1; i >= 0; i--) grid.appendChild(createDayElement(prevMonthDays - i, true, false, []));
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(day).padStart(2, '0');
            grid.appendChild(createDayElement(day, false, dateStr === todayStr, sessionDates[dateStr] || []));
        }
        const remaining = 42 - grid.children.length;
        for (let i = 1; i <= remaining; i++) grid.appendChild(createDayElement(i, true, false, []));
    }
    
    function createDayElement(dayNum, isOtherMonth, isToday, sessions) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day' + (isOtherMonth ? ' other-month' : '') + (isToday ? ' today' : '') + (sessions.length > 0 ? ' has-sessions' : '');
        dayEl.innerHTML = '<div class="day-number">' + dayNum + '</div>';
        sessions.slice(0, 3).forEach(s => {
            const indicator = document.createElement('div');
            indicator.className = 'session-indicator';
            indicator.textContent = s.time + ' ' + s.title;
            indicator.style.cursor = 'pointer';
            indicator.addEventListener('click', function(e) {
                e.stopPropagation();
                openCalendarSessionModal(s);
            });
            dayEl.appendChild(indicator);
        });
        if (sessions.length > 3) dayEl.innerHTML += '<div class="session-indicator" style="background:var(--bg-card);color:var(--text-dim)">+' + (sessions.length - 3) + ' more</div>';
        return dayEl;
    }
    
    renderCalendar(currentMonth, currentYear);
    document.getElementById('prevMonth')?.addEventListener('click', () => { currentMonth--; if (currentMonth < 0) { currentMonth = 11; currentYear--; } renderCalendar(currentMonth, currentYear); });
    document.getElementById('nextMonth')?.addEventListener('click', () => { currentMonth++; if (currentMonth > 11) { currentMonth = 0; currentYear++; } renderCalendar(currentMonth, currentYear); });
});

function openSessionDetailModal(cardEl) {
    var sessionId = cardEl.dataset.sessionId;
    var isMine = cardEl.dataset.isMine === '1';
    var canManage = isMine || isAdmin;
    document.getElementById('modalSessionTitle').textContent = cardEl.dataset.sessionTitle || 'Session';
    document.getElementById('modalSessionDateTime').textContent = (cardEl.dataset.sessionDatetime || '') + (cardEl.dataset.sessionEndTime ? ' - ' + cardEl.dataset.sessionEndTime : '');
    document.getElementById('modalSessionDuration').textContent = (cardEl.dataset.sessionDuration || '60') + ' minutes';
    document.getElementById('modalSessionCoach').textContent = cardEl.dataset.sessionCoach || 'TBD';
    document.getElementById('modalSessionLocation').textContent = cardEl.dataset.sessionLocation || 'Not specified';
    document.getElementById('modalSessionDescription').textContent = cardEl.dataset.sessionDescription || 'No description';
    var planSection = document.getElementById('modalPracticePlanSection'), planName = cardEl.dataset.sessionPracticePlan;
    planSection.style.display = planName ? 'block' : 'none';
    if (planName) document.getElementById('modalPracticePlanName').textContent = planName;
    document.getElementById('modalRecordLink').href = '?page=record_drill_video&session_id=' + sessionId;
    var assignPlanBtn = document.getElementById('modalAssignPlanBtn');
    var evalLink = document.getElementById('modalEvaluationLink');
    if (canManage) {
        assignPlanBtn.style.display = '';
        assignPlanBtn.onclick = function() { closeSessionDetailModal(); openAssignPlanModal(sessionId, cardEl.dataset.sessionPracticePlanId || ''); };
        document.getElementById('modalAssignPlanLabel').textContent = planName ? 'Change Plan' : 'Add Plan';
        evalLink.style.display = '';
        evalLink.href = '?page=coach_session_evaluations&session_id=' + sessionId;
    } else {
        assignPlanBtn.style.display = 'none';
        evalLink.style.display = 'none';
    }
    document.getElementById('sessionDetailModal').classList.add('active');
}

function openCalendarSessionModal(s) {
    var canManage = s.isMine || isAdmin;
    document.getElementById('modalSessionTitle').textContent = s.title || 'Session';
    document.getElementById('modalSessionDateTime').textContent = (s.datetime || '') + (s.endTime ? ' - ' + s.endTime : '');
    document.getElementById('modalSessionDuration').textContent = (s.duration || '60') + ' minutes';
    document.getElementById('modalSessionCoach').textContent = s.coach || 'TBD';
    document.getElementById('modalSessionLocation').textContent = s.location || 'Not specified';
    document.getElementById('modalSessionDescription').textContent = s.description || 'No description';
    var planSection = document.getElementById('modalPracticePlanSection');
    planSection.style.display = s.practicePlan ? 'block' : 'none';
    if (s.practicePlan) document.getElementById('modalPracticePlanName').textContent = s.practicePlan;
    document.getElementById('modalRecordLink').href = '?page=record_drill_video&session_id=' + s.id;
    var assignPlanBtn = document.getElementById('modalAssignPlanBtn');
    var evalLink = document.getElementById('modalEvaluationLink');
    if (canManage) {
        assignPlanBtn.style.display = '';
        assignPlanBtn.onclick = function() { closeSessionDetailModal(); openAssignPlanModal(s.id, s.practicePlanId || ''); };
        document.getElementById('modalAssignPlanLabel').textContent = s.practicePlan ? 'Change Plan' : 'Add Plan';
        evalLink.style.display = '';
        evalLink.href = '?page=coach_session_evaluations&session_id=' + s.id;
    } else {
        assignPlanBtn.style.display = 'none';
        evalLink.style.display = 'none';
    }
    document.getElementById('sessionDetailModal').classList.add('active');
}
function closeSessionDetailModal() { document.getElementById('sessionDetailModal').classList.remove('active'); }
function openAssignPlanModal(sessionId, currentPlanId) {
    // Validate sessionId is numeric to prevent XSS
    if (!/^\d+$/.test(sessionId)) {
        alert('Invalid session ID.');
        return;
    }
    document.getElementById('assignPlanSessionId').value = sessionId;
    document.getElementById('assignPlanSelect').value = currentPlanId || '';
    document.getElementById('assignPlanModal').classList.add('active');
}
function closeAssignPlanModal() { document.getElementById('assignPlanModal').classList.remove('active'); }
function openPrivateSessionModal() { document.getElementById('privateSessionModal').classList.add('active'); }
function closePrivateSessionModal() { document.getElementById('privateSessionModal').classList.remove('active'); }

// Auto-fill duration when a template is selected
document.getElementById('templateSelect')?.addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (opt && opt.dataset.duration) {
        var durSel = document.getElementById('durationSelect');
        if (durSel) {
            for (var i = 0; i < durSel.options.length; i++) {
                if (durSel.options[i].value === opt.dataset.duration) {
                    durSel.selectedIndex = i;
                    break;
                }
            }
        }
    }
});


document.addEventListener('keydown', function(e) { if (e.key === 'Escape') document.querySelectorAll('.session-modal-overlay.active').forEach(m => m.classList.remove('active')); });
document.getElementById('assignPlanForm')?.addEventListener('submit', function(e) {
    const sessionId = document.getElementById('assignPlanSessionId').value;
    // Validate sessionId is numeric
    if (!/^\d+$/.test(sessionId)) { e.preventDefault(); alert('Invalid session ID.'); return; }
});
document.getElementById('privateSessionForm')?.addEventListener('submit', function(e) {
    // Form validation is handled by HTML5 required attributes
});
</script>