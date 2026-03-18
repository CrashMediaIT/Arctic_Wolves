<?php
// Get available packages (regular packages only - not camps/programs)
$packages_query = "
    SELECT p.*
    FROM packages p
    WHERE p.is_active = 1
      AND (p.package_type IS NULL OR p.package_type NOT IN ('camp', 'multi_week'))
    ORDER BY p.price
";
$packages = $pdo->query($packages_query)->fetchAll();

// Get active camp and multi-week program packages
$programs_query = "
    SELECT p.*, ag.name as age_group_name, sl.name as skill_level_name
    FROM packages p
    LEFT JOIN age_groups ag ON p.age_group_id = ag.id
    LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
    WHERE p.is_active = 1
      AND p.package_type IN ('camp', 'multi_week')
    ORDER BY p.camp_start_date ASC, p.price
";
$programs = $pdo->query($programs_query)->fetchAll();

// Fetch daily schedules for camp packages
$camp_schedules = [];
$program_dates = [];
foreach ($programs as $prog) {
    if ($prog['package_type'] === 'camp') {
        $sched_stmt = $pdo->prepare("SELECT * FROM camp_daily_schedules WHERE package_id = ? ORDER BY schedule_date");
        $sched_stmt->execute([$prog['id']]);
        $camp_schedules[$prog['id']] = $sched_stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($prog['package_type'] === 'multi_week') {
        $dates_stmt = $pdo->prepare("SELECT * FROM multiweek_program_dates WHERE package_id = ? ORDER BY session_date");
        $dates_stmt->execute([$prog['id']]);
        $program_dates[$prog['id']] = $dates_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get tax settings for programs display
$tax_settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('tax_rate', 'tax_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
$booking_tax_rate = floatval($tax_settings['tax_rate'] ?? 13.00);
$booking_tax_name = $tax_settings['tax_name'] ?? 'HST';

// Auto-create Private and Semi-Private session templates in Sessions tab if they don't exist
$default_private_price = 150.00;
$default_semi_private_price = 100.00;
$admin_id = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY id LIMIT 1")->fetchColumn();
if (!$admin_id) $admin_id = $_SESSION['user_id'];
try {
    $existing = $pdo->query("SELECT name FROM training_session_templates WHERE name IN ('Private Session', 'Semi-Private Session')")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('Private Session', $existing)) {
        $pdo->prepare("INSERT INTO training_session_templates (name, description, price, duration_minutes, max_participants, session_type, is_active, created_by) VALUES (?, ?, ?, 60, 1, 'on_ice', 1, ?)")
            ->execute(['Private Session', 'One-on-one private training session with a coach — price is per hour', $default_private_price, $admin_id]);
    }
    if (!in_array('Semi-Private Session', $existing)) {
        $pdo->prepare("INSERT INTO training_session_templates (name, description, price, duration_minutes, max_participants, session_type, is_active, created_by) VALUES (?, ?, ?, 60, 4, 'on_ice', 1, ?)")
            ->execute(['Semi-Private Session', 'Small group semi-private training session with a coach — price is per hour', $default_semi_private_price, $admin_id]);
    }
} catch (PDOException $e) { /* Templates may already exist */ }

// Auto-create Development Program session templates in Sessions tab if they don't exist
$default_goalie_dev_price = 0.00;
$default_player_dev_price = 0.00;
try {
    $existing_dev = $pdo->query("SELECT name FROM training_session_templates WHERE name IN ('Goalie Development Program', 'Player Development Program')")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('Goalie Development Program', $existing_dev)) {
        $pdo->prepare("INSERT INTO training_session_templates (name, description, price, duration_minutes, max_participants, session_type, is_active, show_on_landing, created_by) VALUES (?, ?, ?, 60, 1, 'on_ice', 1, 1, ?)")
            ->execute(['Goalie Development Program', 'Long-term goalie development program — personalized drill programs, video feedback, and 1-on-1 coaching', $default_goalie_dev_price, $admin_id]);
    }
    if (!in_array('Player Development Program', $existing_dev)) {
        $pdo->prepare("INSERT INTO training_session_templates (name, description, price, duration_minutes, max_participants, session_type, is_active, show_on_landing, created_by) VALUES (?, ?, ?, 60, 1, 'on_ice', 1, 1, ?)")
            ->execute(['Player Development Program', 'Long-term player development program — personalized skating, shooting, and skills coaching with video analysis', $default_player_dev_price, $admin_id]);
    }
} catch (PDOException $e) { /* Templates may already exist */ }

// Get development program products from session templates (all dev programs)
$dev_products = [];
try {
    $dev_products_stmt = $pdo->query("SELECT id, name, description, price, duration_weeks, is_dev_program FROM training_session_templates WHERE is_dev_program = 1 AND is_active = 1 ORDER BY name");
    $dev_products = $dev_products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* column may not exist */ }

// Backward compat: keep individual pricing for existing code
$goalie_dev_tpl = $pdo->query("SELECT id, price FROM training_session_templates WHERE name = 'Goalie Development Program' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$player_dev_tpl = $pdo->query("SELECT id, price FROM training_session_templates WHERE name = 'Player Development Program' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$goalie_dev_price = $goalie_dev_tpl['price'] ?? $default_goalie_dev_price;
$player_dev_price = $player_dev_tpl['price'] ?? $default_player_dev_price;
$goalie_dev_template_id = $goalie_dev_tpl['id'] ?? 0;
$player_dev_template_id = $player_dev_tpl['id'] ?? 0;

// Check current user's development program enrollment status (by template_id for active enrollments)
$dev_enrolled_types = [];
$dev_active_template_ids = [];
try {
    $dev_enroll_stmt = $pdo->prepare("SELECT program_type, template_id FROM development_program_enrollments WHERE athlete_id = ? AND status = 'active'");
    $dev_enroll_stmt->execute([intval($_SESSION['user_id'])]);
    $dev_enroll_rows = $dev_enroll_stmt->fetchAll(PDO::FETCH_ASSOC);
    $dev_enrolled_types = array_column($dev_enroll_rows, 'program_type');
    $dev_active_template_ids = array_filter(array_column($dev_enroll_rows, 'template_id'));
} catch (PDOException $e) { /* table may not exist yet */ }

// Get hourly pricing from session templates for private/semi-private sessions
$private_tpl = $pdo->query("SELECT price FROM training_session_templates WHERE name = 'Private Session' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$semi_private_tpl = $pdo->query("SELECT price FROM training_session_templates WHERE name = 'Semi-Private Session' AND is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$private_session_price = $private_tpl['price'] ?? $default_private_price;
$semi_private_session_price = $semi_private_tpl['price'] ?? $default_semi_private_price;

// Get available private and semi-private sessions (created by coaches)
// Coaches and admins see all; athletes only see sessions from their assigned coach
$current_user_id = intval($_SESSION['user_id']);
$current_user_role = $user_role ?? '';

$private_sessions_query = "
    SELECT s.id, s.title as session_type_name, s.description, 
           s.session_date, s.session_time,
           s.duration_minutes, s.price as session_price,
           s.max_participants, s.is_private, s.is_semi_private, s.coach_id,
           c.first_name as coach_first_name, c.last_name as coach_last_name,
           l.name as location_name,
           COUNT(DISTINCT b.id) as registered_count
    FROM sessions s
    LEFT JOIN users c ON s.coach_id = c.id
    LEFT JOIN locations l ON s.location_id = l.id
    LEFT JOIN bookings b ON b.session_id = s.id AND b.status IN ('confirmed', 'waitlisted') AND b.payment_status = 'paid'
    WHERE s.session_date >= CURDATE() 
      AND s.status = 'scheduled'
      AND (s.is_private = 1 OR s.is_semi_private = 1)
";

// Filter: athletes and parents only see sessions from their assigned coach or where they have a booking
if (!in_array($current_user_role, ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'])) {
    if ($current_user_role === 'parent') {
        // Parents see sessions where their managed athletes' coaches match
        $private_sessions_query .= "
          AND (
              s.coach_id IN (SELECT u2.assigned_coach_id FROM managed_athletes ma2 JOIN users u2 ON u2.id = ma2.athlete_id WHERE ma2.parent_id = ? AND u2.assigned_coach_id IS NOT NULL)
              OR s.coach_id IN (SELECT u3.created_by_coach_id FROM managed_athletes ma3 JOIN users u3 ON u3.id = ma3.athlete_id WHERE ma3.parent_id = ? AND u3.created_by_coach_id IS NOT NULL)
              OR s.id IN (SELECT bk.session_id FROM bookings bk JOIN managed_athletes ma4 ON bk.user_id = ma4.athlete_id WHERE ma4.parent_id = ? AND bk.status IN ('confirmed', 'waitlisted'))
              OR s.id IN (SELECT bk2.session_id FROM bookings bk2 WHERE bk2.user_id = ? AND bk2.status IN ('confirmed', 'waitlisted'))
          )
        ";
    } else {
        // Athletes see sessions from their assigned coach or where they have a booking
        $private_sessions_query .= "
          AND (
              s.coach_id IN (SELECT u2.assigned_coach_id FROM users u2 WHERE u2.id = ? AND u2.assigned_coach_id IS NOT NULL)
              OR s.coach_id IN (SELECT u3.created_by_coach_id FROM users u3 WHERE u3.id = ? AND u3.created_by_coach_id IS NOT NULL)
              OR s.id IN (SELECT bk.session_id FROM bookings bk WHERE bk.user_id = ? AND bk.status IN ('confirmed', 'waitlisted'))
          )
        ";
    }
}

$private_sessions_query .= "
    GROUP BY s.id
    HAVING registered_count < COALESCE(s.max_participants, 1)
    ORDER BY s.session_date ASC, s.session_time ASC
";

if (!in_array($current_user_role, ['admin', 'coach', 'coach_plus', 'health_coach', 'team_coach'])) {
    $ps_stmt = $pdo->prepare($private_sessions_query);
    if ($current_user_role === 'parent') {
        $ps_stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id]);
    } else {
        $ps_stmt->execute([$current_user_id, $current_user_id, $current_user_id]);
    }
    $private_sessions = $ps_stmt->fetchAll();
} else {
    $private_sessions = $pdo->query($private_sessions_query)->fetchAll();
}
foreach ($private_sessions as &$ps) {
    foreach (['coach_first_name', 'coach_last_name'] as $f) {
        if (!empty($ps[$f])) $ps[$f] = FieldEncryption::decrypt($ps[$f]);
    }
}
unset($ps);

// Get the current user's existing bookings to check for duplicates
$user_booked_sessions = [];
$booked_stmt = $pdo->prepare("SELECT session_id FROM bookings WHERE user_id = ? AND status IN ('confirmed', 'waitlisted') AND payment_status = 'paid'");
$booked_stmt->execute([$_SESSION['user_id']]);
$user_booked_sessions = $booked_stmt->fetchAll(PDO::FETCH_COLUMN);

// For parents, also check their managed athletes' bookings
if (($user_role ?? '') === 'parent') {
    $child_booked_stmt = $pdo->prepare("
        SELECT DISTINCT bk.session_id FROM bookings bk
        INNER JOIN managed_athletes ma ON bk.user_id = ma.athlete_id AND ma.parent_id = ?
        WHERE bk.status IN ('confirmed', 'waitlisted') AND bk.payment_status = 'paid'
    ");
    $child_booked_stmt->execute([$_SESSION['user_id']]);
    $user_booked_sessions = array_unique(array_merge($user_booked_sessions, $child_booked_stmt->fetchAll(PDO::FETCH_COLUMN)));
}

// Get already purchased package IDs for the current user (and their athletes) to show registration status
$booking_purchased_ids = [];
$booking_check_ids = [intval($_SESSION['user_id'])];
if (($user_role ?? '') === 'parent') {
    $bp_athletes_stmt = $pdo->prepare("
        SELECT DISTINCT athlete_id FROM managed_athletes WHERE parent_id = ?
    ");
    $bp_athletes_stmt->execute([$_SESSION['user_id']]);
    $booking_check_ids = array_merge($booking_check_ids, array_map('intval', $bp_athletes_stmt->fetchAll(PDO::FETCH_COLUMN)));
}
$bp_placeholders = implode(',', array_fill(0, count($booking_check_ids), '?'));
$bp_stmt = $pdo->prepare("SELECT DISTINCT package_id FROM user_packages WHERE user_id IN ($bp_placeholders) AND payment_status = 'paid'");
$bp_stmt->execute($booking_check_ids);
$booking_purchased_ids = $bp_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get available sessions for booking
$available_sessions_query = "
    SELECT CONCAT('session_', s.id) as unique_id, s.id, s.title as session_type_name, s.description, 
           s.session_date, s.session_time,
           s.duration_minutes, COALESCE(s.price, st.default_price, 0) as session_price,
           s.max_participants, 'session' as source_type, NULL as date_id, s.coach_id,
           c.first_name as coach_first_name, c.last_name as coach_last_name,
           l.name as location_name,
           COUNT(DISTINCT b.id) as registered_count
    FROM sessions s
    LEFT JOIN users c ON s.coach_id = c.id
    LEFT JOIN session_types st ON s.session_type_id = st.id
    LEFT JOIN locations l ON s.location_id = l.id
    LEFT JOIN bookings b ON b.session_id = s.id AND b.status IN ('confirmed', 'waitlisted') AND b.payment_status = 'paid'
    WHERE s.session_date >= CURDATE() 
      AND s.status = 'scheduled'
      AND (s.is_private = 0 OR s.is_private IS NULL)
      AND (s.is_semi_private = 0 OR s.is_semi_private IS NULL)
    GROUP BY s.id
    ORDER BY s.session_date ASC, s.session_time ASC
";
$available_sessions = $pdo->query($available_sessions_query)->fetchAll();
// Decrypt coach PII fields in session rows
foreach ($available_sessions as &$s) {
    foreach (['coach_first_name', 'coach_last_name'] as $f) {
        if (!empty($s[$f])) $s[$f] = FieldEncryption::decrypt($s[$f]);
    }
}
unset($s);

// Also fetch sessions from training_session_templates + training_session_dates
$template_sessions_query = "
    SELECT CONCAT('template_', td.id) as unique_id, td.id as id, t.name as session_type_name, t.description,
           DATE(td.session_date) as session_date, TIME(td.session_date) as session_time,
           t.duration_minutes, COALESCE(t.price, 0) as session_price,
           COALESCE(td.max_participants, t.max_participants) as max_participants, 'session' as source_type, td.id as date_id, t.coach_id,
           c.first_name as coach_first_name, c.last_name as coach_last_name,
           l.name as location_name,
           (SELECT COUNT(*) FROM session_date_athletes sda WHERE sda.session_date_id = td.id) as registered_count
    FROM training_session_templates t
    INNER JOIN training_session_dates td ON td.template_id = t.id
    LEFT JOIN users c ON t.coach_id = c.id
    LEFT JOIN locations l ON t.location_id = l.id
    WHERE t.is_active = 1
      AND td.is_active = 1
      AND (DATE(td.session_date) > CURDATE() OR (DATE(td.session_date) = CURDATE() AND TIME(td.session_date) > CURTIME()))
    ORDER BY td.session_date ASC
";
$template_sessions = $pdo->query($template_sessions_query)->fetchAll();
foreach ($template_sessions as &$ts) {
    foreach (['coach_first_name', 'coach_last_name'] as $f) {
        if (!empty($ts[$f])) $ts[$f] = FieldEncryption::decrypt($ts[$f]);
    }
}
unset($ts);

// Merge and sort all sessions by date
$available_sessions = array_merge($available_sessions, $template_sessions);
usort($available_sessions, function($a, $b) {
    $dateA = $a['session_date'] . ' ' . ($a['session_time'] ?? '00:00:00');
    $dateB = $b['session_date'] . ' ' . ($b['session_time'] ?? '00:00:00');
    return strtotime($dateA) - strtotime($dateB);
});

// No demo data - show empty state when no real data exists
$is_demo_packages = false;
$is_demo_sessions = false;

// Get template session date IDs the current user (and their athletes) are already registered for
$user_booked_template_dates = [];
$tpl_booked_ids = [intval($_SESSION['user_id'])];
if (($user_role ?? '') === 'parent') {
    $tpl_parent_stmt = $pdo->prepare("
        SELECT DISTINCT athlete_id FROM managed_athletes WHERE parent_id = ?
    ");
    $tpl_parent_stmt->execute([$_SESSION['user_id']]);
    $tpl_booked_ids = array_merge($tpl_booked_ids, array_map('intval', $tpl_parent_stmt->fetchAll(PDO::FETCH_COLUMN)));
}
$tpl_placeholders = implode(',', array_fill(0, count($tpl_booked_ids), '?'));
$tpl_booked_stmt = $pdo->prepare("SELECT session_date_id FROM session_date_athletes WHERE athlete_id IN ($tpl_placeholders)");
$tpl_booked_stmt->execute($tpl_booked_ids);
$user_booked_template_dates = $tpl_booked_stmt->fetchAll(PDO::FETCH_COLUMN);
?>

<?= csrfTokenInput() ?>

<!-- Session Booking View - Two Section Layout -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-calendar-plus"></i> Book a Session
    </h1>
    <p class="page-description">Browse individual sessions or purchase training packages</p>
</div>

<?php if ((isset($is_demo_packages) && $is_demo_packages) || (isset($is_demo_sessions) && $is_demo_sessions)): ?>
<div class="demo-data-notice">
    <i class="fas fa-info-circle"></i>
    <span>Showing demo data. Contact admin to set up real sessions and packages.</span>
</div>
<?php endif; ?>

<div class="booking-content">

    <!-- ============================================
         SECTION 1: INDIVIDUAL SESSIONS (Upper Section)
         ============================================ -->
    <div class="booking-section sessions-section">
        <div class="section-header-bar">
            <div class="section-title-group">
                <h2 class="section-title"><i class="fas fa-calendar-day"></i> Individual Sessions</h2>
                <p class="section-subtitle">Register for upcoming group sessions or book a private lesson</p>
            </div>
            <div class="view-toggle">
                <button class="view-btn active" data-view="list" title="List View">
                    <i class="fas fa-list"></i>
                </button>
                <button class="view-btn" data-view="calendar" title="Calendar View">
                    <i class="fas fa-calendar-alt"></i>
                </button>
            </div>
        </div>
        
        <!-- List View -->
        <div class="sessions-view active" id="list-view">
            <?php if (count($available_sessions) > 0): ?>
            <div class="sessions-list-grid">
                <?php foreach ($available_sessions as $session): 
                    $session_datetime = strtotime($session['session_date'] . ' ' . ($session['session_time'] ?? '00:00:00'));
                    $spots_left = ($session['max_participants'] ?? 10) - ($session['registered_count'] ?? 0);
                    $is_almost_full = $spots_left > 0 && $spots_left <= 3;
                    $is_full = $spots_left <= 0 && !empty($session['max_participants']);
                    $already_booked = empty($session['date_id']) ? in_array($session['id'], $user_booked_sessions) : in_array($session['date_id'], $user_booked_template_dates);
                    $is_session_coach = !empty($session['coach_id']) && intval($session['coach_id']) === intval($_SESSION['user_id']);
                ?>
                <div class="session-list-card" data-session-id="<?= $session['id'] ?>" data-source-type="<?= $session['source_type'] ?>" data-date-id="<?= $session['date_id'] ?? '' ?>" data-date="<?= date('Y-m-d', $session_datetime) ?>" data-booked="<?= $already_booked ? '1' : '0' ?>" data-full="<?= $is_full ? '1' : '0' ?>" data-spots="<?= $spots_left ?>" data-coach="<?= $is_session_coach ? '1' : '0' ?>">
                    <div class="session-date-column">
                        <div class="date-badge">
                            <span class="date-month"><?= date('M', $session_datetime) ?></span>
                            <span class="date-day"><?= date('j', $session_datetime) ?></span>
                            <span class="date-weekday"><?= date('D', $session_datetime) ?></span>
                        </div>
                        <span class="session-time"><?= date('g:i A', $session_datetime) ?></span>
                    </div>
                    <div class="session-details-column">
                        <h4 class="session-title"><?= htmlspecialchars($session['session_type_name'] ?? 'Training Session') ?></h4>
                        <div class="session-meta">
                            <span class="meta-item"><i class="fas fa-user-tie"></i> <?= htmlspecialchars(trim(($session['coach_first_name'] ?? '') . ' ' . ($session['coach_last_name'] ?? '')) ?: 'TBD') ?></span>
                            <?php if (!empty($session['location_name'])): ?>
                            <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span>
                            <?php endif; ?>
                            <span class="meta-item"><i class="fas fa-clock"></i> <?= $session['duration_minutes'] ?? 60 ?> min</span>
                        </div>
                        <?php if (!empty($session['description'])): ?>
                        <p class="session-description"><?= htmlspecialchars(substr($session['description'], 0, 120)) ?><?= strlen($session['description'] ?? '') > 120 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="session-action-column">
                        <?php if ($is_session_coach): ?>
                        <div class="spots-indicator">
                            <span class="spots-number" style="color:#6B46C1;"><i class="fas fa-whistle"></i></span>
                            <span class="spots-text">coaching</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($session['session_price'] ?? 0, 0) ?></div>
                        <button class="btn-register" disabled style="background:rgba(107,70,193,0.15);color:#6B46C1;cursor:default;opacity:0.8;">
                            <i class="fas fa-user-shield"></i> You're Coaching
                        </button>
                        <?php elseif ($already_booked): ?>
                        <div class="spots-indicator">
                            <span class="spots-number" style="color:#00ff88;"><i class="fas fa-check"></i></span>
                            <span class="spots-text">registered</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($session['session_price'] ?? 0, 0) ?></div>
                        <button class="btn-register" disabled style="background:rgba(0,255,136,0.1);color:#00ff88;cursor:default;opacity:0.8;">
                            <i class="fas fa-check-circle"></i> Registered
                        </button>
                        <?php elseif ($is_full): ?>
                        <div class="spots-indicator almost-full">
                            <span class="spots-number" style="color:#EF4444;">0</span>
                            <span class="spots-text">spots left</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($session['session_price'] ?? 0, 0) ?></div>
                        <button class="btn-register" data-action="join-waitlist" data-session-id="<?= $session['id'] ?>" data-source-type="<?= $session['source_type'] ?>" data-date-id="<?= $session['date_id'] ?? '' ?>" style="background:rgba(245,158,11,0.15);color:#F59E0B;">
                            <i class="fas fa-clock"></i> Join Waitlist
                        </button>
                        <?php else: ?>
                        <div class="spots-indicator <?= $is_almost_full ? 'almost-full' : '' ?>">
                            <span class="spots-number"><?= $spots_left ?></span>
                            <span class="spots-text">spots left</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($session['session_price'] ?? 0, 0) ?></div>
                        <button class="btn-register" data-action="register-session" data-session-id="<?= $session['id'] ?>" data-source-type="<?= $session['source_type'] ?>" data-date-id="<?= $session['date_id'] ?? '' ?>" data-price="<?= $session['session_price'] ?? 0 ?>">
                            <i class="fas fa-plus-circle"></i> Register
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state-card">
                <i class="fas fa-calendar-times"></i>
                <h4>No Upcoming Sessions</h4>
                <p>Check back soon for new training sessions.</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Calendar View -->
        <div class="sessions-view" id="calendar-view">
            <div class="calendar-container">
                <div class="calendar-header">
                    <button class="calendar-nav-btn" id="prev-month"><i class="fas fa-chevron-left"></i></button>
                    <h3 class="calendar-month-title" id="calendar-title"><?= date('F Y') ?></h3>
                    <button class="calendar-nav-btn" id="next-month"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="calendar-weekdays">
                    <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span>
                </div>
                <div class="calendar-grid" id="calendar-grid">
                    <!-- Calendar days will be populated by JavaScript -->
                </div>
            </div>
            <div class="calendar-sessions-panel" id="calendar-sessions-panel">
                <h4 class="panel-title"><i class="fas fa-calendar-check"></i> <span id="selected-date-title">Select a date</span></h4>
                <div class="panel-sessions-list" id="panel-sessions-list">
                    <p class="no-sessions-msg">Click on a date to see available sessions</p>
                </div>
            </div>
        </div>
        
        <!-- Private & Semi-Private Sessions -->
        <div class="private-session-form-container">
            <div class="form-section-divider">
                <span>PRIVATE & SEMI-PRIVATE SESSIONS</span>
            </div>
            <?php if (count($private_sessions) > 0): ?>
            <div class="sessions-list-grid">
                <?php foreach ($private_sessions as $ps): 
                    $ps_datetime = strtotime($ps['session_date']);
                    $ps_spots_left = ($ps['max_participants'] ?? 1) - ($ps['registered_count'] ?? 0);
                    $ps_already_booked = in_array($ps['id'], $user_booked_sessions);
                    $ps_is_coach = !empty($ps['coach_id']) && intval($ps['coach_id']) === intval($_SESSION['user_id']);
                    $ps_label = !empty($ps['is_private']) ? 'Private' : 'Semi-Private';
                    $ps_price = !empty($ps['is_private']) ? $private_session_price : $semi_private_session_price;
                    // Use session price if coach set one, otherwise fall back to product price
                    if (!empty($ps['session_price']) && $ps['session_price'] > 0) {
                        $ps_price = $ps['session_price'];
                    }
                ?>
                <div class="session-list-card" data-session-id="<?= $ps['id'] ?>" data-date="<?= date('Y-m-d', $ps_datetime) ?>" data-booked="<?= $ps_already_booked ? '1' : '0' ?>" data-full="0" data-spots="<?= $ps_spots_left ?>">
                    <div class="session-date-column">
                        <div class="date-badge">
                            <span class="date-month"><?= date('M', $ps_datetime) ?></span>
                            <span class="date-day"><?= date('j', $ps_datetime) ?></span>
                            <span class="date-weekday"><?= date('D', $ps_datetime) ?></span>
                        </div>
                        <span class="session-time"><?= !empty($ps['session_time']) ? date('g:i A', strtotime($ps['session_time'])) : 'TBD' ?></span>
                    </div>
                    <div class="session-details-column">
                        <h4 class="session-title"><?= htmlspecialchars($ps['session_type_name'] ?? $ps_label . ' Session') ?></h4>
                        <div class="session-meta">
                            <span class="meta-item" style="color:#6B46C1;font-weight:600;"><i class="fas fa-<?= !empty($ps['is_private']) ? 'user' : 'user-friends' ?>"></i> <?= $ps_label ?></span>
                            <span class="meta-item"><i class="fas fa-user-tie"></i> <?= htmlspecialchars(trim(($ps['coach_first_name'] ?? '') . ' ' . ($ps['coach_last_name'] ?? '')) ?: 'TBD') ?></span>
                            <?php if (!empty($ps['location_name'])): ?>
                            <span class="meta-item"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($ps['location_name']) ?></span>
                            <?php endif; ?>
                            <span class="meta-item"><i class="fas fa-clock"></i> <?= $ps['duration_minutes'] ?? 60 ?> min</span>
                        </div>
                        <?php if (!empty($ps['description'])): ?>
                        <p class="session-description"><?= htmlspecialchars(substr($ps['description'], 0, 120)) ?><?= strlen($ps['description'] ?? '') > 120 ? '...' : '' ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="session-action-column">
                        <?php if ($ps_is_coach): ?>
                        <div class="spots-indicator">
                            <span class="spots-number" style="color:#6B46C1;"><i class="fas fa-whistle"></i></span>
                            <span class="spots-text">coaching</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($ps_price, 0) ?></div>
                        <button class="btn-register" disabled style="background:rgba(107,70,193,0.15);color:#6B46C1;cursor:default;opacity:0.8;">
                            <i class="fas fa-user-shield"></i> You're Coaching
                        </button>
                        <?php elseif ($ps_already_booked): ?>
                        <div class="spots-indicator">
                            <span class="spots-number" style="color:#00ff88;"><i class="fas fa-check"></i></span>
                            <span class="spots-text">registered</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($ps_price, 0) ?></div>
                        <button class="btn-register" disabled style="background:rgba(0,255,136,0.1);color:#00ff88;cursor:default;opacity:0.8;">
                            <i class="fas fa-check-circle"></i> Registered
                        </button>
                        <?php else: ?>
                        <div class="spots-indicator">
                            <span class="spots-number"><?= $ps_spots_left ?></span>
                            <span class="spots-text">spots left</span>
                        </div>
                        <div class="session-price-tag">$<?= number_format($ps_price, 0) ?></div>
                        <button class="btn-register" data-action="register-session" data-session-id="<?= $ps['id'] ?>" data-price="<?= $ps_price ?>">
                            <i class="fas fa-plus-circle"></i> Book
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state-card">
                <i class="fas fa-user-lock"></i>
                <h4>No Private Sessions Available</h4>
                <p>No private or semi-private sessions are currently scheduled. Check back soon!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ============================================
         SECTION 2: PACKAGES (Lower Section - Card Style)
         ============================================ -->
    <div class="booking-section packages-section">
        <div class="section-header-bar">
            <div class="section-title-group">
                <h2 class="section-title"><i class="fas fa-box-open"></i> Training Packages</h2>
                <p class="section-subtitle">Save money with our bundled session packages</p>
            </div>
        </div>
        
        <?php if (count($packages) > 0): ?>
        <div class="packages-cards-grid" data-component="PackageGrid">
            <?php foreach ($packages as $idx => $package): 
                $is_popular = $idx === 1; // Mark middle package as popular
            ?>
            <div class="package-card <?= $is_popular ? 'featured' : '' ?>" data-component="PackageCard" data-package-id="<?= $package['id'] ?>">
                <?php if ($is_popular): ?>
                <div class="package-badge">Most Popular</div>
                <?php endif; ?>
                <div class="package-card-header">
                    <div class="package-icon">
                        <i class="fas fa-<?= $idx === 0 ? 'rocket' : ($idx === 1 ? 'star' : 'trophy') ?>"></i>
                    </div>
                    <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                </div>
                <div class="package-card-body">
                    <div class="package-pricing">
                        <span class="package-price">$<?= number_format($package['price'], 0) ?></span>
                        <span class="package-credits"><?= $package['credits'] ?> sessions</span>
                    </div>
                    <div class="package-per-session">
                        <span>$<?= number_format($package['price'] / max(1, $package['credits']), 2) ?> per session</span>
                    </div>
                    <p class="package-description"><?= htmlspecialchars($package['description'] ?? '') ?></p>
                    <ul class="package-features">
                        <li><i class="fas fa-check"></i> <?= $package['credits'] ?> training sessions</li>
                        <?php if ($package['valid_days']): ?>
                        <li><i class="fas fa-check"></i> Valid for <?= $package['valid_days'] ?> days</li>
                        <?php endif; ?>
                        <li><i class="fas fa-check"></i> Flexible scheduling</li>
                        <li><i class="fas fa-check"></i> All session types included</li>
                    </ul>
                </div>
                <div class="package-card-footer">
                    <button class="btn-purchase <?= $is_popular ? 'btn-purchase-featured' : '' ?>" data-action="purchase-package" data-package-id="<?= $package['id'] ?>">
                        <i class="fas fa-shopping-cart"></i> Purchase Package
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state-card">
            <i class="fas fa-box-open"></i>
            <h4>No Packages Available</h4>
            <p>Check back soon for training package offers.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- ============================================
         SECTION 3: PROGRAMS & CAMPS
         ============================================ -->
    <?php if (count($programs) > 0): ?>
    <div class="booking-section programs-section">
        <div class="section-header-bar">
            <div class="section-title-group">
                <h2 class="section-title"><i class="fas fa-campground"></i> Programs & Camps</h2>
                <p class="section-subtitle">Register for our camps and multi-week training programs</p>
            </div>
        </div>
        
        <div class="programs-cards-grid">
            <?php foreach ($programs as $prog): 
                $is_camp = $prog['package_type'] === 'camp';
                $schedules = $is_camp ? ($camp_schedules[$prog['id']] ?? []) : ($program_dates[$prog['id']] ?? []);
                $day_count = count($schedules);
                $tax_amount = round($prog['price'] * ($booking_tax_rate / 100), 2);
            ?>
            <div class="program-card" data-program-id="<?= $prog['id'] ?>">
                <div class="program-type-badge <?= $is_camp ? 'camp' : 'multi-week' ?>">
                    <i class="fas fa-<?= $is_camp ? 'campground' : 'calendar-week' ?>"></i>
                    <?= $is_camp ? 'Camp' : 'Multi-Week Program' ?>
                </div>
                <div class="program-card-header">
                    <h3 class="program-name"><?= htmlspecialchars($prog['name']) ?></h3>
                    <?php if (!empty($prog['camp_start_date']) && !empty($prog['camp_end_date'])): ?>
                    <span class="program-dates">
                        <i class="fas fa-calendar"></i>
                        <?= date('M j', strtotime($prog['camp_start_date'])) ?> – <?= date('M j, Y', strtotime($prog['camp_end_date'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="program-card-body">
                    <?php if (!empty($prog['description'])): ?>
                    <p class="program-description"><?= htmlspecialchars($prog['description']) ?></p>
                    <?php endif; ?>
                    <ul class="program-details-list">
                        <?php if ($day_count > 0): ?>
                        <li><i class="fas fa-list-ol"></i> <?= $day_count ?> <?= $is_camp ? 'days' : 'sessions' ?></li>
                        <?php endif; ?>
                        <?php if (!empty($prog['daily_start_time']) && !empty($prog['daily_end_time'])): ?>
                        <li><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($prog['daily_start_time'])) ?> – <?= date('g:i A', strtotime($prog['daily_end_time'])) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($prog['age_group_name'])): ?>
                        <li><i class="fas fa-users"></i> <?= htmlspecialchars($prog['age_group_name']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($prog['skill_level_name'])): ?>
                        <li><i class="fas fa-signal"></i> <?= htmlspecialchars($prog['skill_level_name']) ?></li>
                        <?php endif; ?>
                        <?php if (!empty($prog['enable_child_checkin'])): ?>
                        <li><i class="fas fa-child"></i> Child Pickup Enabled</li>
                        <?php endif; ?>
                    </ul>
                    <?php if ($day_count > 0): ?>
                    <div class="program-schedule-preview">
                        <strong>Schedule:</strong>
                        <div class="schedule-dates">
                            <?php foreach (array_slice($schedules, 0, 5) as $sched): 
                                $sched_date = $is_camp ? ($sched['schedule_date'] ?? '') : ($sched['session_date'] ?? '');
                            ?>
                            <span class="schedule-date-badge">
                                <?= !empty($sched_date) ? date('M j', strtotime($sched_date)) : '' ?>
                                <?php if (!empty($sched['title'])): ?> – <?= htmlspecialchars($sched['title']) ?><?php endif; ?>
                            </span>
                            <?php endforeach; ?>
                            <?php if ($day_count > 5): ?>
                            <span class="schedule-date-badge more">+<?= $day_count - 5 ?> more</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="program-card-footer">
                    <div class="program-pricing">
                        <span class="program-price">$<?= number_format($prog['price'], 0) ?></span>
                        <span class="program-tax">+ $<?= number_format($tax_amount, 2) ?> <?= htmlspecialchars($booking_tax_name) ?></span>
                    </div>
                    <?php if (in_array($prog['id'], $booking_purchased_ids)): ?>
                    <button type="button" class="btn-register-program" disabled style="background:rgba(0,255,136,0.1);color:#00ff88;cursor:default;opacity:0.8;">
                        <i class="fas fa-check-circle"></i> Registered
                    </button>
                    <?php else: ?>
                    <form method="POST" action="process_purchase_package.php" style="display:inline;">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="package_id" value="<?= $prog['id'] ?>">
                        <button type="submit" class="btn-register-program" data-action="register-program" data-program-id="<?= $prog['id'] ?>">
                            <i class="fas fa-<?= $is_camp ? 'campground' : 'calendar-plus' ?>"></i>
                            <?= $is_camp ? 'Register for Camp' : 'Enroll in Program' ?>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- ============================================
         SECTION 4: LONG TERM DEVELOPMENT PROGRAMS
         ============================================ -->
    <div class="booking-section dev-programs-section">
        <div class="section-header-bar">
            <div class="section-title-group">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Long Term Development Programs</h2>
                <p class="section-subtitle">Personalized multi-week coaching programs with dedicated development coaches</p>
            </div>
        </div>
        
        <?php if (count($dev_products) > 0): ?>
        <div class="programs-cards-grid">
            <?php foreach ($dev_products as $dp):
                $dp_is_goalie = stripos($dp['name'], 'goalie') !== false;
                $dp_type = $dp_is_goalie ? 'goalie_dev' : 'player_dev';
                $dp_enrolled = in_array((int)$dp['id'], $dev_active_template_ids);
                $dp_icon_class = $dp_is_goalie ? 'icon-hockey-goalie' : 'icon-hockey-player';
            ?>
            <div class="program-card dev-product-card" data-program-id="dev-<?= (int)$dp['id'] ?>">
                <div class="program-type-badge dev-type-badge">
                    <span class="<?= $dp_icon_class ?>"></span> Long Term Development
                </div>
                <div class="program-card-header">
                    <h3 class="program-name"><?= htmlspecialchars($dp['name']) ?></h3>
                    <?php if (!empty($dp['duration_weeks'])): ?>
                    <span class="program-dates">
                        <i class="fas fa-clock"></i> <?= (int)$dp['duration_weeks'] ?> week program
                    </span>
                    <?php endif; ?>
                </div>
                <div class="program-card-body">
                    <?php if (!empty($dp['description'])): ?>
                    <p class="program-description"><?= htmlspecialchars($dp['description']) ?></p>
                    <?php endif; ?>
                    <ul class="program-details-list">
                        <li><i class="fas fa-clipboard-list"></i> Personalized drill programs</li>
                        <li><i class="fas fa-video"></i> Video analysis & feedback</li>
                        <li><i class="fas fa-calendar-check"></i> 1-on-1 coaching sessions</li>
                    </ul>
                </div>
                <div class="program-card-footer">
                    <div class="program-pricing">
                        <span class="program-price"><?= $dp['price'] > 0 ? '$' . number_format($dp['price'], 2) : 'Free' ?></span>
                    </div>
                    <?php if ($dp_enrolled): ?>
                    <button type="button" class="btn-register-program btn-enrolled-program" disabled>
                        <i class="fas fa-check-circle"></i> Currently Enrolled
                    </button>
                    <?php else: ?>
                    <form method="POST" action="process_booking.php" class="dev-enroll-form">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="register_dev_program">
                        <input type="hidden" name="program_type" value="<?= htmlspecialchars($dp_type) ?>">
                        <input type="hidden" name="template_id" value="<?= (int)$dp['id'] ?>">
                        <button type="submit" class="btn-register-program">
                            <i class="fas fa-chart-line"></i> Enroll in Program
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-state-card">
            <i class="fas fa-chart-line"></i>
            <h4>No Development Programs Available</h4>
            <p>Long term development programs will appear here when available. Check back soon!</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<style>
/* ============================================
   TWO-SECTION BOOKING PAGE STYLES
   ============================================ */

/* Section Layout */
.booking-section {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    padding: 28px;
    margin-bottom: 32px;
}

.section-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.section-title-group {
    flex: 1;
}

.section-title {
    font-size: 22px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-title i {
    color: var(--primary, #6B46C1);
    font-size: 24px;
}

.section-subtitle {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin: 0;
}

/* View Toggle Buttons */
.view-toggle {
    display: flex;
    gap: 4px;
    background: var(--bg-main, #0A0A0F);
    padding: 4px;
    border-radius: 8px;
    border: 1px solid var(--border, #2D2D3F);
}

.view-btn {
    width: 40px;
    height: 36px;
    border: none;
    background: transparent;
    color: var(--text-dim, #A8A8B8);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.view-btn:hover {
    color: var(--text-white, #FFFFFF);
    background: rgba(107, 70, 193, 0.1);
}

.view-btn.active {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
}

/* Sessions View Container */
.sessions-view {
    display: none;
}

.sessions-view.active {
    display: block;
}

/* ============================================
   LIST VIEW STYLES
   ============================================ */
.sessions-list-grid {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.session-list-card {
    display: grid;
    grid-template-columns: 100px 1fr 180px;
    gap: 24px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
    transition: all 0.3s ease;
    align-items: center;
}

.session-list-card:hover {
    border-color: var(--primary, #6B46C1);
    transform: translateX(4px);
    box-shadow: 0 4px 20px rgba(107, 70, 193, 0.15);
}

.session-date-column {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}

.date-badge {
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    border-radius: 10px;
    padding: 12px 16px;
    text-align: center;
    min-width: 70px;
}

.date-month {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: rgba(255, 255, 255, 0.9);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.date-day {
    display: block;
    font-size: 26px;
    font-weight: 900;
    color: #FFFFFF;
    line-height: 1.1;
}

.date-weekday {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: rgba(255, 255, 255, 0.8);
}

.session-time {
    font-size: 13px;
    font-weight: 700;
    color: var(--primary, #6B46C1);
}

.session-details-column {
    flex: 1;
}

.session-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 10px 0;
}

.session-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 8px;
}

.meta-item {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    display: flex;
    align-items: center;
    gap: 6px;
}

.meta-item i {
    color: var(--primary, #6B46C1);
    font-size: 12px;
}

.session-description {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    margin: 8px 0 0 0;
    line-height: 1.5;
}

.session-action-column {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 12px;
}

.spots-indicator {
    text-align: right;
}

.spots-indicator.almost-full .spots-number {
    color: #EF4444;
}

.spots-number {
    font-size: 24px;
    font-weight: 900;
    color: var(--primary, #6B46C1);
    display: block;
    line-height: 1;
}

.spots-text {
    font-size: 11px;
    color: var(--text-dim, #A8A8B8);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.session-price-tag {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
}

.btn-register {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
    border: none;
    padding: 10px 20px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-register:hover {
    background: var(--primary-hover, #7C3AED);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
}

/* ============================================
   CALENDAR VIEW STYLES
   ============================================ */
#calendar-view {
    display: none;
}

#calendar-view.active {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 24px;
}

.calendar-container {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
}

.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.calendar-month-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0;
}

.calendar-nav-btn {
    width: 36px;
    height: 36px;
    border: 1px solid var(--border, #2D2D3F);
    background: transparent;
    color: var(--text-dim, #A8A8B8);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.calendar-nav-btn:hover {
    border-color: var(--primary, #6B46C1);
    color: var(--primary, #6B46C1);
}

.calendar-weekdays {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    margin-bottom: 8px;
}

.calendar-weekdays span {
    text-align: center;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim, #A8A8B8);
    padding: 8px 0;
    text-transform: uppercase;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
}

.calendar-day {
    aspect-ratio: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-card, #16161F);
    border: 1px solid transparent;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.calendar-day:hover {
    border-color: var(--primary, #6B46C1);
    background: rgba(107, 70, 193, 0.1);
}

.calendar-day.other-month {
    opacity: 0.3;
}

.calendar-day.today {
    border-color: var(--primary, #6B46C1);
}

.calendar-day.has-sessions {
    background: rgba(107, 70, 193, 0.15);
}

.calendar-day.has-sessions::after {
    content: '';
    position: absolute;
    bottom: 6px;
    width: 6px;
    height: 6px;
    background: var(--primary, #6B46C1);
    border-radius: 50%;
}

.calendar-day.selected {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
}

.calendar-day.selected::after {
    background: #FFFFFF;
}

.calendar-day-number {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white, #FFFFFF);
}

/* Calendar Sessions Panel */
.calendar-sessions-panel {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 20px;
    max-height: 500px;
    overflow-y: auto;
}

.panel-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.panel-title i {
    color: var(--primary, #6B46C1);
}

.panel-sessions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.no-sessions-msg {
    color: var(--text-dim, #A8A8B8);
    font-size: 14px;
    text-align: center;
    padding: 20px 0;
}

.panel-session-item {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    padding: 14px;
    transition: all 0.2s ease;
}

.panel-session-item:hover {
    border-color: var(--primary, #6B46C1);
}

/* ============================================
   PRIVATE SESSION FORM STYLES
   ============================================ */
.private-session-form-container {
    margin-top: 32px;
}

.form-section-divider {
    text-align: center;
    position: relative;
    margin: 32px 0;
}

.form-section-divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: var(--border, #2D2D3F);
}

.form-section-divider span {
    position: relative;
    background: var(--bg-card, #16161F);
    padding: 0 20px;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim, #A8A8B8);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.booking-form-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 24px;
}

.form-card-header {
    display: flex;
    align-items: center;
    gap: 16px;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.form-card-header i {
    font-size: 28px;
    color: var(--primary, #6B46C1);
}

.form-card-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 4px 0;
}

.form-card-header p {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    margin: 0;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dim, #A8A8B8);
    margin-bottom: 8px;
}

.form-group label .required {
    color: #EF4444;
}

.form-input {
    height: 44px;
    padding: 0 14px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 6px;
    color: var(--text-white, #FFFFFF);
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}

.form-textarea {
    width: 100%;
    padding: 12px 14px;
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 6px;
    color: var(--text-white, #FFFFFF);
    font-size: 14px;
    resize: vertical;
    font-family: 'Inter', sans-serif;
    transition: all 0.2s ease;
}

.form-textarea:focus {
    outline: none;
    border-color: var(--primary, #6B46C1);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.15);
}

.form-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border, #2D2D3F);
}

.session-price-display {
    display: flex;
    align-items: baseline;
    gap: 10px;
}

.price-label {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
}

.price-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--primary, #6B46C1);
}

.btn-book-session {
    height: 48px;
    padding: 0 32px;
}

/* ============================================
   PACKAGES SECTION STYLES (Card Style)
   ============================================ */
.packages-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
}

.package-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    position: relative;
    display: flex;
    flex-direction: column;
}

.package-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 12px 40px rgba(107, 70, 193, 0.2);
    border-color: var(--primary, #6B46C1);
}

.package-card.featured {
    border: 2px solid var(--primary, #6B46C1);
    transform: scale(1.02);
}

.package-card.featured:hover {
    transform: scale(1.02) translateY(-8px);
}

.package-badge {
    position: absolute;
    top: 0;
    right: 20px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    color: #FFFFFF;
    padding: 8px 16px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-radius: 0 0 8px 8px;
}

.package-card-header {
    padding: 28px 24px 20px;
    text-align: center;
    border-bottom: 1px solid var(--border, #2D2D3F);
}

.package-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6));
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.package-icon i {
    font-size: 26px;
    color: #FFFFFF;
}

.package-name {
    font-size: 22px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
    margin: 0;
}

.package-card-body {
    padding: 24px;
    flex: 1;
}

.package-pricing {
    text-align: center;
    margin-bottom: 8px;
}

.package-price {
    font-size: 48px;
    font-weight: 900;
    color: var(--primary, #6B46C1);
    line-height: 1;
}

.package-credits {
    display: block;
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin-top: 4px;
}

.package-per-session {
    text-align: center;
    margin-bottom: 20px;
}

.package-per-session span {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    background: rgba(107, 70, 193, 0.1);
    padding: 4px 12px;
    border-radius: 20px;
}

.package-description {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    text-align: center;
    margin-bottom: 20px;
    line-height: 1.5;
}

.package-features {
    list-style: none;
    padding: 0;
    margin: 0;
}

.package-features li {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid var(--border, #2D2D3F);
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
}

.package-features li:last-child {
    border-bottom: none;
}

.package-features i {
    color: #10B981;
    font-size: 12px;
}

.package-card-footer {
    padding: 20px 24px 24px;
}

.btn-purchase {
    width: 100%;
    height: 48px;
    background: var(--bg-card, #16161F);
    border: 2px solid var(--primary, #6B46C1);
    color: var(--primary, #6B46C1);
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.btn-purchase:hover {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(107, 70, 193, 0.4);
}

.btn-purchase-featured {
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
}

.btn-purchase-featured:hover {
    background: var(--primary-hover, #7C3AED);
}

/* ============================================
   EMPTY STATE & NOTICE STYLES
   ============================================ */
.empty-state-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 10px;
    padding: 48px 24px;
    text-align: center;
}

.empty-state-card i {
    font-size: 48px;
    color: var(--primary, #6B46C1);
    opacity: 0.4;
    margin-bottom: 16px;
}

.empty-state-card h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 8px 0;
}

.empty-state-card p {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin: 0;
}

.demo-data-notice {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 14px 20px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    color: var(--accent, #8B5CF6);
    font-size: 14px;
}

.demo-data-notice i {
    font-size: 18px;
}

/* ============================================
   RESPONSIVE STYLES
   ============================================ */
@media (max-width: 1024px) {
    #calendar-view.active {
        grid-template-columns: 1fr;
    }
    
    .calendar-sessions-panel {
        max-height: 300px;
    }
}

@media (max-width: 768px) {
    .booking-section {
        padding: 20px;
    }
    
    .section-header-bar {
        flex-direction: column;
        gap: 16px;
    }
    
    .view-toggle {
        width: 100%;
        justify-content: center;
    }
    
    .session-list-card {
        grid-template-columns: 1fr;
        gap: 16px;
        text-align: center;
    }
    
    .session-date-column {
        flex-direction: row;
        justify-content: center;
        gap: 16px;
    }
    
    .session-action-column {
        align-items: center;
        flex-direction: row;
        justify-content: space-between;
        width: 100%;
        padding-top: 16px;
        border-top: 1px solid var(--border, #2D2D3F);
    }
    
    .spots-indicator {
        text-align: left;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
        gap: 16px;
    }
    
    .btn-book-session {
        width: 100%;
    }
    
    .packages-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .package-card.featured {
        transform: none;
    }
    
    .package-card.featured:hover {
        transform: translateY(-8px);
    }
}

/* ============================================
   PROGRAMS & CAMPS SECTION
   ============================================ */
.programs-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap: 24px;
}

.program-card {
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 16px;
    overflow: hidden;
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
}

.program-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(107, 70, 193, 0.15);
    border-color: rgba(107, 70, 193, 0.4);
}

.program-type-badge {
    padding: 8px 16px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.program-type-badge.camp {
    background: rgba(245, 158, 11, 0.15);
    color: #F59E0B;
}

.program-type-badge.multi-week {
    background: rgba(59, 130, 246, 0.15);
    color: #3B82F6;
}

.program-type-badge.dev-type-badge {
    background: rgba(107, 70, 193, 0.15);
    color: var(--accent);
}

.program-card-header {
    padding: 20px 20px 0;
}

.program-name {
    font-size: 20px;
    font-weight: 800;
    color: var(--text-white, #FFFFFF);
    margin: 0 0 8px 0;
}

.program-dates {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    display: flex;
    align-items: center;
    gap: 6px;
}

.program-card-body {
    padding: 16px 20px;
    flex: 1;
}

.program-description {
    font-size: 14px;
    color: var(--text-dim, #A8A8B8);
    margin: 0 0 16px 0;
    line-height: 1.5;
}

.program-details-list {
    list-style: none;
    padding: 0;
    margin: 0 0 16px 0;
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.program-details-list li {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
    display: flex;
    align-items: center;
    gap: 6px;
    background: rgba(107, 70, 193, 0.08);
    padding: 4px 10px;
    border-radius: 6px;
}

.program-details-list li i {
    color: var(--primary, #6B46C1);
    font-size: 12px;
}

.program-schedule-preview {
    font-size: 13px;
    color: var(--text-dim, #A8A8B8);
}

.program-schedule-preview strong {
    color: var(--text-white, #FFFFFF);
    display: block;
    margin-bottom: 6px;
}

.schedule-dates {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.schedule-date-badge {
    font-size: 11px;
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.2);
    padding: 2px 8px;
    border-radius: 4px;
    color: var(--text-dim, #A8A8B8);
}

.schedule-date-badge.more {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.2);
    color: #F59E0B;
}

.program-card-footer {
    padding: 16px 20px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-top: 1px solid var(--border, #2D2D3F);
}

.program-pricing {
    display: flex;
    flex-direction: column;
}

.program-price {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white, #FFFFFF);
}

.program-tax {
    font-size: 12px;
    color: var(--text-dim, #A8A8B8);
}

.btn-register-program {
    padding: 10px 20px;
    background: var(--primary, #6B46C1);
    color: #FFFFFF;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-register-program:hover {
    background: var(--primary-dark, #553C9A);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.btn-register-program.btn-enrolled-program {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success);
    cursor: default;
    opacity: 0.8;
}

.dev-enroll-form {
    display: inline;
}

@media (max-width: 768px) {
    .programs-cards-grid {
        grid-template-columns: 1fr;
    }
    
    .program-card-footer {
        flex-direction: column;
        gap: 12px;
        align-items: stretch;
    }
    
    .btn-register-program {
        justify-content: center;
    }
}
</style>

<script>
// Booking page functionality - Two Section Layout with Calendar
document.addEventListener('DOMContentLoaded', function() {
    // ============================================
    // VIEW TOGGLE (List/Calendar)
    // ============================================
    const viewBtns = document.querySelectorAll('.view-btn');
    const sessionViews = document.querySelectorAll('.sessions-view');
    
    viewBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const viewType = this.dataset.view;
            
            // Update active button
            viewBtns.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            
            // Update active view
            sessionViews.forEach(view => {
                view.classList.remove('active');
                if (view.id === viewType + '-view') {
                    view.classList.add('active');
                }
            });
            
            // Initialize calendar if switching to calendar view
            if (viewType === 'calendar') {
                initCalendar();
            }
        });
    });
    
    // ============================================
    // CALENDAR FUNCTIONALITY
    // ============================================
    let currentMonth = new Date().getMonth();
    let currentYear = new Date().getFullYear();
    let selectedDate = null;
    
    // Session data for calendar (gathered from the list)
    const sessionData = [];
    document.querySelectorAll('.session-list-card').forEach(card => {
        sessionData.push({
            id: card.dataset.sessionId,
            sourceType: card.dataset.sourceType || 'session',
            dateId: card.dataset.dateId || '',
            date: card.dataset.date,
            booked: card.dataset.booked === '1',
            full: card.dataset.full === '1',
            spots: parseInt(card.dataset.spots, 10) || 0,
            isCoach: card.dataset.coach === '1',
            element: card
        });
    });
    
    function initCalendar() {
        renderCalendar(currentMonth, currentYear);
    }
    
    function renderCalendar(month, year) {
        const calendarGrid = document.getElementById('calendar-grid');
        const calendarTitle = document.getElementById('calendar-title');
        
        if (!calendarGrid || !calendarTitle) return;
        
        const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                           'July', 'August', 'September', 'October', 'November', 'December'];
        
        calendarTitle.textContent = `${monthNames[month]} ${year}`;
        
        // Clear existing days
        calendarGrid.innerHTML = '';
        
        // Get first day of month and total days
        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();
        
        // Today's date for comparison
        const today = new Date();
        const todayStr = today.toISOString().split('T')[0];
        
        // Get dates that have sessions
        const sessionDates = sessionData.map(s => s.date);
        
        // Previous month days
        for (let i = firstDay - 1; i >= 0; i--) {
            const dayNum = daysInPrevMonth - i;
            const dayEl = createDayElement(dayNum, true, false, false);
            calendarGrid.appendChild(dayEl);
        }
        
        // Current month days
        for (let day = 1; day <= daysInMonth; day++) {
            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
            const isToday = dateStr === todayStr;
            const hasSessions = sessionDates.includes(dateStr);
            const dayEl = createDayElement(day, false, isToday, hasSessions, dateStr);
            calendarGrid.appendChild(dayEl);
        }
        
        // Next month days to fill grid
        const totalCells = calendarGrid.children.length;
        const remainingCells = 42 - totalCells; // 6 rows * 7 days
        for (let i = 1; i <= remainingCells; i++) {
            const dayEl = createDayElement(i, true, false, false);
            calendarGrid.appendChild(dayEl);
        }
    }
    
    function createDayElement(dayNum, isOtherMonth, isToday, hasSessions, dateStr = null) {
        const dayEl = document.createElement('div');
        dayEl.className = 'calendar-day';
        
        if (isOtherMonth) dayEl.classList.add('other-month');
        if (isToday) dayEl.classList.add('today');
        if (hasSessions) dayEl.classList.add('has-sessions');
        
        const dayNumEl = document.createElement('span');
        dayNumEl.className = 'calendar-day-number';
        dayNumEl.textContent = dayNum;
        dayEl.appendChild(dayNumEl);
        
        if (dateStr && !isOtherMonth) {
            dayEl.dataset.date = dateStr;
            dayEl.addEventListener('click', function() {
                selectDate(dateStr);
            });
        }
        
        return dayEl;
    }
    
    function selectDate(dateStr) {
        // Update selected state
        document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
        const selectedDay = document.querySelector(`.calendar-day[data-date="${dateStr}"]`);
        if (selectedDay) selectedDay.classList.add('selected');
        
        selectedDate = dateStr;
        
        // Update panel
        const dateTitle = document.getElementById('selected-date-title');
        const sessionsList = document.getElementById('panel-sessions-list');
        
        const dateObj = new Date(dateStr + 'T00:00:00');
        const options = { timeZone: window.APP_TIMEZONE, weekday: 'long', month: 'long', day: 'numeric' };
        dateTitle.textContent = dateObj.toLocaleDateString('en-US', options);
        
        // Find sessions for this date
        const daySessions = sessionData.filter(s => s.date === dateStr);
        
        if (daySessions.length > 0) {
            sessionsList.innerHTML = '';
            daySessions.forEach(session => {
                const card = session.element;
                const title = card.querySelector('.session-title')?.textContent || 'Training Session';
                const time = card.querySelector('.session-time')?.textContent || '';
                const price = card.querySelector('.session-price-tag')?.textContent || '';
                const spots = card.querySelector('.spots-number')?.textContent || '';
                
                // Create elements safely to prevent XSS
                const itemEl = document.createElement('div');
                itemEl.className = 'panel-session-item';
                
                // Header row
                const headerDiv = document.createElement('div');
                headerDiv.style.cssText = 'display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;';
                
                const titleEl = document.createElement('h5');
                titleEl.style.cssText = 'margin: 0; font-size: 15px; font-weight: 700; color: #fff;';
                titleEl.textContent = title;
                
                const priceEl = document.createElement('span');
                priceEl.style.cssText = 'font-weight: 800; color: var(--primary, #6B46C1);';
                priceEl.textContent = price;
                
                headerDiv.appendChild(titleEl);
                headerDiv.appendChild(priceEl);
                
                // Details row
                const detailsDiv = document.createElement('div');
                detailsDiv.style.cssText = 'font-size: 13px; color: #A8A8B8; margin-bottom: 10px;';
                detailsDiv.innerHTML = '<i class="fas fa-clock" style="color: var(--primary, #6B46C1); margin-right: 6px;"></i>';
                const timeSpan = document.createElement('span');
                timeSpan.textContent = time;
                detailsDiv.appendChild(timeSpan);
                
                const spotsContainer = document.createElement('span');
                spotsContainer.style.marginLeft = '12px';
                spotsContainer.innerHTML = '<i class="fas fa-users" style="color: var(--primary, #6B46C1); margin-right: 6px;"></i>';
                const spotsSpan = document.createElement('span');
                if (session.booked) {
                    spotsSpan.textContent = 'registered';
                } else if (session.isCoach) {
                    spotsSpan.textContent = 'coaching';
                } else {
                    spotsSpan.textContent = spots + ' spots left';
                }
                spotsContainer.appendChild(spotsSpan);
                detailsDiv.appendChild(spotsContainer);
                
                // Action button - respect booking/full status
                const registerBtn = document.createElement('button');
                registerBtn.className = 'btn-register';
                registerBtn.style.cssText = 'width: 100%; justify-content: center;';
                
                if (session.isCoach) {
                    registerBtn.disabled = true;
                    registerBtn.style.cssText += 'background:rgba(107,70,193,0.15);color:#6B46C1;cursor:default;opacity:0.8;';
                    registerBtn.innerHTML = '<i class="fas fa-user-shield"></i> You\'re Coaching';
                } else if (session.booked) {
                    registerBtn.disabled = true;
                    registerBtn.style.cssText += 'background:rgba(0,255,136,0.1);color:#00ff88;cursor:default;opacity:0.8;';
                    registerBtn.innerHTML = '<i class="fas fa-check-circle"></i> Registered';
                } else if (session.full) {
                    registerBtn.setAttribute('data-action', 'join-waitlist');
                    registerBtn.setAttribute('data-session-id', session.id);
                    registerBtn.setAttribute('data-source-type', session.sourceType);
                    registerBtn.setAttribute('data-date-id', session.dateId);
                    registerBtn.style.cssText += 'background:rgba(245,158,11,0.15);color:#F59E0B;';
                    registerBtn.innerHTML = '<i class="fas fa-clock"></i> Join Waitlist';
                } else {
                    registerBtn.setAttribute('data-action', 'register-session');
                    registerBtn.setAttribute('data-session-id', session.id);
                    registerBtn.setAttribute('data-source-type', session.sourceType);
                    registerBtn.setAttribute('data-date-id', session.dateId);
                    registerBtn.innerHTML = '<i class="fas fa-plus-circle"></i> Register';
                }
                
                itemEl.appendChild(headerDiv);
                itemEl.appendChild(detailsDiv);
                itemEl.appendChild(registerBtn);
                sessionsList.appendChild(itemEl);
            });
        } else {
            sessionsList.innerHTML = '<p class="no-sessions-msg">No sessions available on this date</p>';
        }
    }
    
    // Calendar navigation
    const prevMonthBtn = document.getElementById('prev-month');
    const nextMonthBtn = document.getElementById('next-month');
    
    if (prevMonthBtn) {
        prevMonthBtn.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            renderCalendar(currentMonth, currentYear);
        });
    }
    
    if (nextMonthBtn) {
        nextMonthBtn.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            renderCalendar(currentMonth, currentYear);
        });
    }
    
    // ============================================
    // PACKAGE PURCHASE FUNCTIONALITY
    // ============================================
    document.querySelectorAll('[data-action="purchase-package"]').forEach(btn => {
        btn.addEventListener('click', function() {
            const packageId = this.dataset.packageId;
            const packageCard = this.closest('.package-card');
            const packageName = packageCard?.querySelector('.package-name')?.textContent || 'Package';
            
            if (packageId.startsWith('demo-')) {
                showBookingNotification('Demo Mode: This is a demo package. Contact admin to set up real packages for purchase.', 'info');
            } else {
                // Validate CSRF token exists
                const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
                if (!csrfToken) {
                    showBookingNotification('Security token missing. Please refresh the page and try again.', 'error');
                    return;
                }
                
                // Validate packageId is a valid numeric ID
                if (!/^\d+$/.test(packageId)) {
                    showBookingNotification('Invalid package ID.', 'error');
                    return;
                }
                
                // Submit via form for Stripe checkout with CSRF protection
                // Use DOM methods instead of innerHTML to prevent XSS
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'process_purchase_package.php';
                
                const packageInput = document.createElement('input');
                packageInput.type = 'hidden';
                packageInput.name = 'package_id';
                packageInput.value = packageId;
                form.appendChild(packageInput);
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = csrfToken;
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    });
    
    // ============================================
    // SESSION REGISTRATION FUNCTIONALITY
    // ============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="register-session"]');
        if (!btn) return;
        
        const sessionId = btn.dataset.sessionId;
        const price = btn.dataset.price;
        const sourceType = btn.dataset.sourceType || 'session';
        const dateId = btn.dataset.dateId || '';
        
        if (sessionId.startsWith('demo-')) {
            showBookingNotification('Demo Mode: This is a demo session. Book real sessions when they become available.', 'info');
        } else {
            // Validate CSRF token exists
            const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
            if (!csrfToken) {
                showBookingNotification('Security token missing. Please refresh the page and try again.', 'error');
                return;
            }
            
            // Validate sessionId is a valid numeric ID  
            if (!/^\d+$/.test(sessionId)) {
                showBookingNotification('Invalid session ID.', 'error');
                return;
            }
            
            // Submit registration - use DOM methods instead of innerHTML to prevent XSS
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'process_booking.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = dateId ? 'register_template_session' : 'register_session';
            form.appendChild(actionInput);
            
            if (dateId) {
                const dateIdInput = document.createElement('input');
                dateIdInput.type = 'hidden';
                dateIdInput.name = 'session_date_id';
                dateIdInput.value = dateId;
                form.appendChild(dateIdInput);
            } else {
                const sessionInput = document.createElement('input');
                sessionInput.type = 'hidden';
                sessionInput.name = 'session_id';
                sessionInput.value = sessionId;
                form.appendChild(sessionInput);
            }
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = csrfToken;
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        }
    });
    
    // ============================================
    // DEVELOPMENT PROGRAM ENROLLMENT (via payment)
    // ============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="register-dev-program"]');
        if (!btn) return;

        const programType = btn.dataset.programType;
        const templateId = btn.dataset.templateId;

        if (!programType || !templateId || !/^\d+$/.test(templateId)) {
            showBookingNotification('Invalid program. Please refresh and try again.', 'error');
            return;
        }

        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
        if (!csrfToken) {
            showBookingNotification('Security token missing. Please refresh the page and try again.', 'error');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_booking.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'register_dev_program';
        form.appendChild(actionInput);

        const typeInput = document.createElement('input');
        typeInput.type = 'hidden';
        typeInput.name = 'program_type';
        typeInput.value = programType;
        form.appendChild(typeInput);

        const tplInput = document.createElement('input');
        tplInput.type = 'hidden';
        tplInput.name = 'template_id';
        tplInput.value = templateId;
        form.appendChild(tplInput);

        const csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = csrfToken;
        form.appendChild(csrfInput);

        document.body.appendChild(form);
        form.submit();
    });

    // ============================================
    // WAITLIST FUNCTIONALITY
    // ============================================
    document.addEventListener('click', function(e) {
        const btn = e.target.closest('[data-action="join-waitlist"]');
        if (!btn) return;
        
        const sessionId = btn.dataset.sessionId;
        const csrfToken = document.querySelector('input[name="csrf_token"]')?.value;
        if (!csrfToken) {
            showBookingNotification('Security token missing. Please refresh the page.', 'error');
            return;
        }
        if (!/^\d+$/.test(sessionId)) {
            showBookingNotification('Invalid session ID.', 'error');
            return;
        }
        
        const form = new FormData();
        form.append('action', 'join_waitlist');
        form.append('session_id', sessionId);
        form.append('csrf_token', csrfToken);
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Joining...';
        
        fetch('process_booking.php', { method: 'POST', body: form })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    showBookingNotification(data.message || 'Added to waitlist!', 'info');
                    btn.innerHTML = '<i class="fas fa-check"></i> On Waitlist';
                    btn.style.background = 'rgba(16,185,129,0.15)';
                    btn.style.color = '#10B981';
                } else {
                    showBookingNotification(data.message || 'Failed to join waitlist', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-clock"></i> Join Waitlist';
                }
            })
            .catch(function() {
                showBookingNotification('Network error. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-clock"></i> Join Waitlist';
            });
    });
});

// Notification helper
function showBookingNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'booking-notification';
    
    let icon = 'info-circle';
    let bgColor = 'rgba(107, 70, 193, 0.9)';
    
    if (type === 'error') {
        icon = 'exclamation-circle';
        bgColor = 'rgba(239, 68, 68, 0.9)';
    } else if (type === 'success') {
        icon = 'check-circle';
        bgColor = 'rgba(16, 185, 129, 0.9)';
    }
    
    alertDiv.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 300px;
        max-width: 500px;
        padding: 16px 20px;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 600;
        background: ${bgColor};
        color: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
        animation: slideInRight 0.3s ease;
        box-shadow: 0 8px 32px rgba(0,0,0,0.4);
    `;
    
    // Add animation keyframes
    if (!document.getElementById('booking-notification-styles')) {
        const styleSheet = document.createElement('style');
        styleSheet.id = 'booking-notification-styles';
        styleSheet.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        document.head.appendChild(styleSheet);
    }
    
    document.body.appendChild(alertDiv);
    setTimeout(() => {
        alertDiv.style.animation = 'slideInRight 0.3s ease reverse';
        setTimeout(() => alertDiv.remove(), 300);
    }, 4500);
}
</script>
