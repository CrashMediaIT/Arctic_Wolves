<?php
/**
 * PWA Sessions - Mobile-native sessions list with tab switching,
 * session detail modal, booking form, calendar view, and filters.
 * Purpose-built for mobile phones.
 */

// Waitlist token handling - when user arrives from enrollment email purchase link
$waitlistToken = $_GET['waitlist_token'] ?? '';
$waitlistOffer = null;
if (!empty($waitlistToken)) {
    try {
        $wlTokenStmt = $pdo->prepare("
            SELECT w.id, w.session_id, w.package_id, w.template_id, w.status, w.token_expires_at,
                   s.title as session_title, s.price as session_price,
                   p.name as package_name, p.price as package_price,
                   tst.name as template_name, tst.price as template_price
            FROM waitlists w
            LEFT JOIN sessions s ON w.session_id = s.id
            LEFT JOIN packages p ON w.package_id = p.id
            LEFT JOIN training_session_templates tst ON w.template_id = tst.id
            WHERE w.waitlist_token = ? AND w.user_id = ? AND w.status = 'offered' AND w.token_expires_at > NOW()
        ");
        $wlTokenStmt->execute([$waitlistToken, $user_id]);
        $waitlistOffer = $wlTokenStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { /* Token lookup failed - continue normally */ }
}

// Filter params
$filterType = $_GET['filter_type'] ?? '';
$filterLocation = $_GET['filter_location'] ?? '';
$filterPeriod = $_GET['filter_period'] ?? 'all';
$filterSkill = $_GET['filter_skill'] ?? '';
$filterCoach = $_GET['filter_coach'] ?? '';
$showHistory = !empty($_GET['history']);

// Build filter conditions
$filterWhere = '';
$filterParams = [];
if ($filterType !== '') {
    $filterWhere .= ' AND s.session_type = ?';
    $filterParams[] = $filterType;
}
if ($filterLocation !== '') {
    $filterWhere .= ' AND s.arena = ?';
    $filterParams[] = $filterLocation;
}
if ($filterSkill !== '') {
    $filterWhere .= ' AND s.session_type_id = ?';
    $filterParams[] = $filterSkill;
}
if ($filterCoach !== '') {
    $filterWhere .= ' AND s.coach_id = ?';
    $filterParams[] = $filterCoach;
}
// Period filter
$periodWhere = '';
if ($filterPeriod === 'week') {
    $periodWhere = ' AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 WEEK)';
} elseif ($filterPeriod === 'month') {
    $periodWhere = ' AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)';
}

$dateStatusWhere = $showHistory
    ? "s.status IN ('scheduled','completed')"
    : "s.session_date >= CURDATE() AND s.status = 'scheduled'";

// Fetch filter options
$filterSessionTypes = [];
$filterLocations = [];
$filterSkillTypes = [];
$filterCoaches = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT session_type FROM sessions WHERE session_type IS NOT NULL AND session_type != '' ORDER BY session_type");
    $filterSessionTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT DISTINCT arena FROM sessions WHERE arena IS NOT NULL AND arena != '' ORDER BY arena");
    $filterLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT id, name FROM session_types ORDER BY name ASC");
    $filterSkillTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->query("SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u INNER JOIN sessions s ON s.coach_id = u.id WHERE u.is_active = 1 ORDER BY u.last_name, u.first_name");
    $filterCoaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $filterCoaches = decryptUserRows($filterCoaches);
} catch (PDOException $e) {}

// Upcoming sessions – expanded query with coach, location, description, practice plan
$upcomingSessions = [];
try {
    if ($isAnyCoach) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.description, s.session_date, s.session_time, s.duration_minutes,
                   s.status, s.arena, s.session_type, s.price, s.max_participants,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   l.name as location_name,
                   pp.name as practice_plan_name, pp.description as practice_plan_desc,
                   (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = 'confirmed') as attendee_count,
                   (SELECT COUNT(*) FROM waitlists wl WHERE wl.session_id = s.id AND wl.status IN ('waiting', 'offered')) as waitlist_count
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
            LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
            WHERE $dateStatusWhere $filterWhere $periodWhere
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 50
        ");
        $stmt->execute($filterParams);
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.title, s.description, s.session_date, s.session_time, s.duration_minutes,
                   s.status, s.arena, s.session_type, s.price, s.max_participants,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   l.name as location_name,
                   pp.name as practice_plan_name, pp.description as practice_plan_desc,
                   b.id as booking_id, b.status as booking_status,
                   (SELECT COUNT(*) FROM bookings b2 WHERE b2.session_id = s.id AND b2.status = 'confirmed') as attendee_count,
                   w.id as waitlist_id, w.position as waitlist_position
            FROM sessions s
            LEFT JOIN users c ON s.coach_id = c.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
            LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
            LEFT JOIN bookings b ON b.session_id = s.id AND b.user_id = ? AND b.status = 'confirmed'
            LEFT JOIN waitlists w ON w.session_id = s.id AND w.user_id = ? AND w.status IN ('waiting', 'offered')
            WHERE $dateStatusWhere $filterWhere $periodWhere
            ORDER BY s.session_date ASC, s.session_time ASC
            LIMIT 50
        ");
        // First $user_id for bookings JOIN, second for waitlists JOIN
        $stmt->execute(array_merge([$user_id, $user_id], $filterParams));
    }
    $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Decrypt coach PII
    foreach ($upcomingSessions as &$_s) {
        foreach (['coach_first_name', 'coach_last_name'] as $_f) {
            if (!empty($_s[$_f])) $_s[$_f] = FieldEncryption::decrypt($_s[$_f]);
        }
    }
    unset($_s);
} catch (PDOException $e) { $upcomingSessions = []; }

// Also fetch sessions from training session templates (registered sessions for athletes, all for coaches)
if (!$showHistory) {
    try {
        $tplParams = [];
        $tplJoin = '';
        $tplExtraCols = '';
        $tplWhere = 'tst.is_active = 1 AND tsd.is_active = 1 AND tsd.session_date >= CURDATE()';

        if ($isAnyCoach) {
            $tplExtraCols = "(SELECT COUNT(*) FROM session_date_athletes sda2 WHERE sda2.session_date_id = tsd.id) as attendee_count,
                   0 as waitlist_count";
        } else {
            // For athletes: only show template sessions they are registered for
            $tplJoin = "INNER JOIN session_date_athletes sda ON sda.session_date_id = tsd.id AND sda.athlete_id = ?";
            $tplParams[] = $user_id;
            $tplExtraCols = "sda.id as booking_id, 'confirmed' as booking_status,
                   (SELECT COUNT(*) FROM session_date_athletes sda2 WHERE sda2.session_date_id = tsd.id) as attendee_count,
                   NULL as waitlist_id, NULL as waitlist_position";
        }

        // Apply filters
        if ($filterPeriod === 'week') {
            $tplWhere .= ' AND tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 WEEK)';
        } elseif ($filterPeriod === 'month') {
            $tplWhere .= ' AND tsd.session_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)';
        }
        if ($filterSkill !== '') {
            $tplWhere .= ' AND tst.session_type_id = ?';
            $tplParams[] = $filterSkill;
        }
        if ($filterCoach !== '') {
            $tplWhere .= ' AND tst.coach_id = ?';
            $tplParams[] = $filterCoach;
        }

        $tplStmt = $pdo->prepare("
            SELECT tst.id, tst.name as title, tst.description,
                   DATE(tsd.session_date) as session_date, TIME(tsd.session_date) as session_time,
                   tst.duration_minutes, 'scheduled' as status, NULL as arena,
                   tst.session_type, tst.price, tst.max_participants,
                   c.first_name as coach_first_name, c.last_name as coach_last_name,
                   st.name as session_type_name,
                   l.name as location_name,
                   pp.name as practice_plan_name, pp.description as practice_plan_desc,
                   $tplExtraCols
            FROM training_session_templates tst
            INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
            $tplJoin
            LEFT JOIN users c ON tst.coach_id = c.id
            LEFT JOIN session_types st ON tst.session_type_id = st.id
            LEFT JOIN locations l ON tst.location_id = l.id
            LEFT JOIN practice_plans pp ON tst.practice_plan_id = pp.id
            WHERE $tplWhere
            ORDER BY tsd.session_date ASC, TIME(tsd.session_date) ASC
            LIMIT 50
        ");
        $tplStmt->execute($tplParams);
        $tplSessions = $tplStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tplSessions as &$_ts) {
            foreach (['coach_first_name', 'coach_last_name'] as $_f) {
                if (!empty($_ts[$_f])) $_ts[$_f] = FieldEncryption::decrypt($_ts[$_f]);
            }
        }
        unset($_ts);

        if (!empty($tplSessions)) {
            $upcomingSessions = array_merge($upcomingSessions, $tplSessions);
            usort($upcomingSessions, function($a, $b) {
                return strtotime(($a['session_date'] ?? '') . ' ' . ($a['session_time'] ?? '00:00'))
                     - strtotime(($b['session_date'] ?? '') . ' ' . ($b['session_time'] ?? '00:00'));
            });
            $upcomingSessions = array_slice($upcomingSessions, 0, 50);
        }
    } catch (PDOException $e) {
        // Template tables may not exist yet - continue with regular sessions
    }
}

// Build calendar date map (Y-m-d => count)
$calendarDates = [];
foreach ($upcomingSessions as $cs) {
    $d = date('Y-m-d', strtotime($cs['session_date']));
    $calendarDates[$d] = ($calendarDates[$d] ?? 0) + 1;
}

// Session types for booking tab
$sessionTypes = [];
try {
    $stmt = $pdo->query("SELECT id, name, description, default_price, duration_minutes FROM session_types ORDER BY name ASC");
    $sessionTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessionTypes = []; }

// Coaches for booking form
$bookingCoaches = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('coach','admin','team_coach') AND is_active = 1 ORDER BY last_name, first_name");
    $bookingCoaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $bookingCoaches = decryptUserRows($bookingCoaches);
} catch (PDOException $e) { $bookingCoaches = []; }

// Available sessions for booking tab (non-private, non-semi-private, upcoming)
$availableSessions = [];
$userBookedSessionIds = [];
$userBookedTemplateDateIds = [];
if (!$isAnyCoach) {
    try {
        $bkStmt = $pdo->prepare("SELECT session_id FROM bookings WHERE user_id = ? AND status IN ('confirmed','waitlisted')");
        $bkStmt->execute([$user_id]);
        $userBookedSessionIds = $bkStmt->fetchAll(PDO::FETCH_COLUMN);
        $tdStmt = $pdo->prepare("SELECT session_date_id FROM session_date_athletes WHERE athlete_id = ?");
        $tdStmt->execute([$user_id]);
        $userBookedTemplateDateIds = $tdStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}
try {
    $avStmt = $pdo->query("
        SELECT s.id, s.title, s.description, s.session_date, s.session_time, s.duration_minutes,
               COALESCE(s.price, st.default_price, 0) as price, s.max_participants, s.coach_id,
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               l.name as location_name, st.name as session_type_name,
               COUNT(DISTINCT b.id) as registered_count
        FROM sessions s
        LEFT JOIN users c ON s.coach_id = c.id
        LEFT JOIN session_types st ON s.session_type_id = st.id
        LEFT JOIN locations l ON s.location_id = l.id
        LEFT JOIN bookings b ON b.session_id = s.id AND b.status IN ('confirmed','waitlisted') AND b.payment_status = 'paid'
        WHERE s.session_date >= CURDATE() AND s.status = 'scheduled'
          AND (s.is_private = 0 OR s.is_private IS NULL)
          AND (s.is_semi_private = 0 OR s.is_semi_private IS NULL)
        GROUP BY s.id
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 50
    ");
    $availableSessions = $avStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($availableSessions as &$_av) {
        foreach (['coach_first_name', 'coach_last_name'] as $_f) {
            if (!empty($_av[$_f])) $_av[$_f] = FieldEncryption::decrypt($_av[$_f]);
        }
    }
    unset($_av);
} catch (PDOException $e) { $availableSessions = []; }
try {
    $avTplStmt = $pdo->query("
        SELECT td.id, t.name as title, t.description, DATE(td.session_date) as session_date,
               TIME(td.session_date) as session_time, t.duration_minutes, COALESCE(t.price, 0) as price,
               COALESCE(td.max_participants, t.max_participants) as max_participants, t.coach_id,
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               l.name as location_name, st.name as session_type_name,
               (SELECT COUNT(*) FROM session_date_athletes sda WHERE sda.session_date_id = td.id) as registered_count,
               td.id as date_id
        FROM training_session_templates t
        INNER JOIN training_session_dates td ON td.template_id = t.id
        LEFT JOIN users c ON t.coach_id = c.id
        LEFT JOIN session_types st ON t.session_type_id = st.id
        LEFT JOIN locations l ON t.location_id = l.id
        WHERE t.is_active = 1 AND td.is_active = 1
          AND (DATE(td.session_date) > CURDATE() OR (DATE(td.session_date) = CURDATE() AND TIME(td.session_date) > CURTIME()))
        ORDER BY td.session_date ASC
        LIMIT 50
    ");
    $tplAvail = $avTplStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tplAvail as &$_ta) {
        foreach (['coach_first_name', 'coach_last_name'] as $_f) {
            if (!empty($_ta[$_f])) $_ta[$_f] = FieldEncryption::decrypt($_ta[$_f]);
        }
    }
    unset($_ta);
    $availableSessions = array_merge($availableSessions, $tplAvail);
    usort($availableSessions, function($a, $b) {
        return strtotime(($a['session_date'] ?? '') . ' ' . ($a['session_time'] ?? '00:00'))
             - strtotime(($b['session_date'] ?? '') . ' ' . ($b['session_time'] ?? '00:00'));
    });
    $availableSessions = array_slice($availableSessions, 0, 50);
} catch (PDOException $e) { /* Template tables may not exist */ }

// Fetch active packages for the Packages section
$bookingPackages = [];
try {
    $pkgStmt = $pdo->query("
        SELECT p.id, p.name, p.description, p.price, p.credits, p.valid_days, p.package_type,
               p.camp_start_date, p.camp_end_date, p.daily_start_time, p.daily_end_time,
               p.waitlist_only
        FROM packages p
        WHERE p.is_active = 1
        ORDER BY p.price ASC
    ");
    $bookingPackages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $bookingPackages = []; }

// Separate camps (packages with camp dates) from regular packages
$regularPackages = [];
$campPackages = [];
foreach ($bookingPackages as $bp) {
    if (!empty($bp['camp_start_date']) && !empty($bp['camp_end_date'])) {
        $campPackages[] = $bp;
    } else {
        $regularPackages[] = $bp;
    }
}

// Fetch long-term development programs (training_session_templates with is_dev_program = 1)
$devPrograms = [];
try {
    $devStmt = $pdo->query("
        SELECT tst.id, tst.name, tst.description, tst.price, tst.duration_weeks,
               tst.session_type, tst.max_participants, tst.waitlist_only,
               c.first_name as coach_first_name, c.last_name as coach_last_name
        FROM training_session_templates tst
        LEFT JOIN users c ON tst.coach_id = c.id
        WHERE tst.is_active = 1 AND tst.is_dev_program = 1
        ORDER BY tst.name ASC
    ");
    $devPrograms = $devStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($devPrograms as &$_dp) {
        foreach (['coach_first_name', 'coach_last_name'] as $_f) {
            if (!empty($_dp[$_f])) $_dp[$_f] = FieldEncryption::decrypt($_dp[$_f]);
        }
    }
    unset($_dp);
} catch (PDOException $e) { $devPrograms = []; }

// Fetch non-dev training programs (training_session_templates that are NOT dev programs and NOT regular sessions)
$trainingPrograms = [];
try {
    $tpStmt = $pdo->query("
        SELECT tst.id, tst.name, tst.description, tst.price, tst.duration_weeks,
               tst.session_type, tst.max_participants, tst.duration_minutes, tst.waitlist_only,
               c.first_name as coach_first_name, c.last_name as coach_last_name
        FROM training_session_templates tst
        LEFT JOIN users c ON tst.coach_id = c.id
        WHERE tst.is_active = 1 AND (tst.is_dev_program = 0 OR tst.is_dev_program IS NULL)
              AND tst.duration_weeks IS NOT NULL AND tst.duration_weeks > 0
        ORDER BY tst.name ASC
    ");
    $trainingPrograms = $tpStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($trainingPrograms as &$_tp) {
        foreach (['coach_first_name', 'coach_last_name'] as $_f) {
            if (!empty($_tp[$_f])) $_tp[$_f] = FieldEncryption::decrypt($_tp[$_f]);
        }
    }
    unset($_tp);
} catch (PDOException $e) { $trainingPrograms = []; }

// Check waitlist_only status for available sessions (fetch session-level waitlist_only flag)
$waitlistOnlySessionIds = [];
try {
    $wloStmt = $pdo->query("SELECT id FROM sessions WHERE waitlist_only = 1");
    $waitlistOnlySessionIds = $wloStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* waitlist_only column may not exist yet */ }

// Check user's existing waitlist entries for packages and programs
$userWaitlistPackageIds = [];
$userWaitlistTemplateIds = [];
if (!$isAnyCoach) {
    try {
        $wpStmt = $pdo->prepare("SELECT package_id FROM waitlists WHERE user_id = ? AND package_id IS NOT NULL AND status IN ('waiting','offered')");
        $wpStmt->execute([$user_id]);
        $userWaitlistPackageIds = $wpStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
    try {
        $wtStmt = $pdo->prepare("SELECT template_id FROM waitlists WHERE user_id = ? AND template_id IS NOT NULL AND status IN ('waiting','offered')");
        $wtStmt->execute([$user_id]);
        $userWaitlistTemplateIds = $wtStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {}
}

// Waitlist management data for coaches
$waitlistManagement = [];
if ($isAnyCoach) {
    try {
        $wmStmt = $pdo->query("
            SELECT w.id as waitlist_id, w.session_id, w.package_id, w.template_id,
                   w.position, w.status, w.added_at, w.notified_at, w.token_expires_at,
                   u.first_name, u.last_name, u.email,
                   s.title as session_title,
                   p.name as package_name,
                   tst.name as template_name
            FROM waitlists w
            JOIN users u ON w.user_id = u.id
            LEFT JOIN sessions s ON w.session_id = s.id
            LEFT JOIN packages p ON w.package_id = p.id
            LEFT JOIN training_session_templates tst ON w.template_id = tst.id
            WHERE w.status IN ('waiting', 'offered')
            ORDER BY w.added_at ASC
            LIMIT 100
        ");
        $waitlistManagement = $wmStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($waitlistManagement as &$_wm) {
            foreach (['first_name', 'last_name', 'email'] as $_f) {
                if (!empty($_wm[$_f])) $_wm[$_f] = FieldEncryption::decrypt($_wm[$_f]);
            }
        }
        unset($_wm);
    } catch (PDOException $e) { $waitlistManagement = []; }
}
?>
<style>
.m-sessions { padding: 0; font-family: Inter, sans-serif; }
.m-segment-control {
    display: flex; background: #1E1E2E; border-radius: 12px; padding: 4px;
    margin: 0 16px 16px; position: relative; border: 1px solid #2D2D3F;
}
.m-segment {
    flex: 1; padding: 10px 12px; border: none; background: transparent;
    color: #A8A8B8; font-size: 13px; font-weight: 600; font-family: inherit;
    cursor: pointer; border-radius: 10px; display: flex; align-items: center;
    justify-content: center; gap: 6px; z-index: 1; transition: color 0.2s;
    min-height: 44px; -webkit-tap-highlight-color: transparent;
}
.m-segment i { font-size: 14px; }
.m-segment-active {
    color: #fff; background: #6B46C1;
    box-shadow: 0 2px 8px rgba(107,70,193,0.3);
}
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-sess-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; cursor: pointer;
}
.m-sess-date {
    min-width: 48px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px; flex-shrink: 0;
}
.m-sess-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-sess-date-day { font-size: 20px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-sess-body { flex: 1; min-width: 0; }
.m-sess-title { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-sess-detail { font-size: 12px; color: #A8A8B8; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.m-sess-detail i { font-size: 10px; }
.m-sess-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; flex-shrink: 0; }
.m-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-badge-upcoming { background: rgba(16,185,129,0.15); color: #10B981; }
.m-badge-booked { background: rgba(107,70,193,0.2); color: #8B5CF6; }
.m-badge-count { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-badge-type { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-badge-full { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-badge-waitlist { background: rgba(245,158,11,0.2); color: #F59E0B; }
.m-book-btn {
    display: inline-block; padding: 6px 14px; border-radius: 8px;
    font-size: 12px; font-weight: 600; text-decoration: none;
    min-height: 32px; min-width: 44px; text-align: center;
    line-height: 20px; border: none; cursor: pointer;
    font-family: Inter, sans-serif;
}
.m-book-btn-primary { background: #6B46C1; color: #fff; }
.m-book-btn-primary:active { background: #8B5CF6; }
.m-book-btn-danger { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-book-btn-secondary { background: #2D2D3F; color: #A8A8B8; }
.m-book-btn-warning { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-book-btn-warning:active { background: rgba(245,158,11,0.25); }
.m-type-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 10px;
}
.m-type-name { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 6px; }
.m-type-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; }
.m-type-meta {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 10px; border-top: 1px solid #2D2D3F;
}
.m-type-price { font-size: 16px; font-weight: 700; color: #10B981; }
.m-type-dur { font-size: 12px; color: #6B6B7B; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-filter-toggle {
    display: flex; align-items: center; justify-content: space-between;
    padding: 10px 16px; background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    cursor: pointer; min-height: 44px;
}
.m-filter-toggle span { font-size: 13px; color: #A8A8B8; font-weight: 600; }
.m-filter-toggle i { color: #6B6B7B; font-size: 12px; transition: transform 0.2s; }
.m-filter-toggle.m-filter-open i { transform: rotate(180deg); }
.m-filter-bar {
    display: none; padding: 12px 16px; background: #0A0A0F;
    border-bottom: 1px solid #2D2D3F; gap: 10px; flex-wrap: wrap;
}
.m-filter-bar.m-filter-visible { display: flex; }
.m-filter-select {
    flex: 1; min-width: 100px; padding: 10px 12px; border-radius: 8px;
    background: #16161F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 13px; font-family: Inter, sans-serif; min-height: 44px;
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-filter-select option { background: #16161F; color: #fff; }
.m-filter-check {
    display: flex; align-items: center; gap: 8px; min-height: 44px;
    font-size: 13px; color: #A8A8B8; cursor: pointer; white-space: nowrap;
}
.m-filter-check input { width: 18px; height: 18px; accent-color: #6B46C1; cursor: pointer; }
.m-view-toggle {
    display: flex; gap: 4px; padding: 10px 16px; background: #0A0A0F;
    border-bottom: 1px solid #2D2D3F;
}
.m-view-btn {
    flex: 1; padding: 8px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #16161F; color: #6B6B7B; font-size: 13px; font-weight: 600;
    text-align: center; cursor: pointer; min-height: 44px;
    display: flex; align-items: center; justify-content: center; gap: 6px;
    font-family: Inter, sans-serif; transition: all 0.2s;
}
.m-view-btn.m-view-active {
    background: rgba(107,70,193,0.15); color: #8B5CF6; border-color: #8B5CF6;
}
.m-calendar { padding: 0 16px 16px; }
.m-cal-header {
    display: flex; align-items: center; justify-content: space-between; padding: 12px 0;
}
.m-cal-title { font-size: 15px; font-weight: 700; color: #fff; }
.m-cal-nav {
    width: 36px; height: 36px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #16161F; color: #A8A8B8; font-size: 14px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-family: Inter, sans-serif;
}
.m-cal-nav:active { background: #2D2D3F; }
.m-cal-weekdays {
    display: grid; grid-template-columns: repeat(7,1fr); text-align: center;
    font-size: 11px; color: #6B6B7B; font-weight: 600; padding-bottom: 6px;
}
.m-cal-grid { display: grid; grid-template-columns: repeat(7,1fr); gap: 2px; }
.m-cal-day {
    aspect-ratio: 1; display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    font-size: 13px; color: #A8A8B8; border-radius: 8px;
    cursor: pointer; position: relative; border: none; background: none;
    font-family: Inter, sans-serif; min-height: 36px;
}
.m-cal-day:active { background: rgba(107,70,193,0.1); }
.m-cal-day.m-cal-today { color: #8B5CF6; font-weight: 700; }
.m-cal-day.m-cal-selected { background: #6B46C1; color: #fff; }
.m-cal-day.m-cal-other { color: #3A3A4A; }
.m-cal-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #8B5CF6; position: absolute; bottom: 3px;
}
.m-cal-day.m-cal-selected .m-cal-dot { background: #fff; }
.m-sheet-overlay {
    display: none; position: fixed; inset: 0; z-index: 200;
    background: rgba(0,0,0,0.6); opacity: 0; transition: opacity 0.25s;
}
.m-sheet-overlay.m-sheet-show { display: block; opacity: 1; }
.m-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 201;
    background: #16161F; border-radius: 16px 16px 0 0;
    max-height: 85vh; overflow-y: auto; transform: translateY(100%);
    transition: transform 0.3s cubic-bezier(0.32,0.72,0,1);
    padding: 0 0 env(safe-area-inset-bottom, 16px);
}
.m-sheet-overlay.m-sheet-show + .m-sheet,
.m-sheet.m-sheet-show { transform: translateY(0); }
.m-sheet-handle {
    width: 36px; height: 4px; border-radius: 2px; background: #3A3A4A;
    margin: 10px auto 0;
}
.m-sheet-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 16px 20px 12px; border-bottom: 1px solid #2D2D3F;
}
.m-sheet-title { font-size: 16px; font-weight: 700; color: #fff; }
.m-sheet-close {
    width: 36px; height: 36px; border-radius: 50%; border: none;
    background: #2D2D3F; color: #A8A8B8; font-size: 16px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    font-family: Inter, sans-serif;
}
.m-sheet-body { padding: 16px 20px; }
.m-detail-row {
    display: flex; align-items: flex-start; gap: 12px;
    padding: 10px 0; border-bottom: 1px solid rgba(45,45,63,0.5);
}
.m-detail-row:last-child { border-bottom: none; }
.m-detail-icon {
    width: 32px; height: 32px; border-radius: 8px;
    background: rgba(107,70,193,0.12); color: #8B5CF6;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; flex-shrink: 0;
}
.m-detail-label { font-size: 11px; color: #6B6B7B; font-weight: 600; text-transform: uppercase; letter-spacing: 0.3px; }
.m-detail-value { font-size: 14px; color: #fff; margin-top: 1px; }
.m-detail-actions {
    display: flex; gap: 10px; padding: 16px 0 0;
    border-top: 1px solid #2D2D3F; margin-top: 8px;
}
.m-detail-actions .m-book-btn { flex: 1; padding: 12px; font-size: 14px; min-height: 48px; border-radius: 10px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { font-size: 12px; color: #A8A8B8; font-weight: 600; margin-bottom: 6px; display: block; }
.m-form-label .m-required { color: #EF4444; }
.m-form-input {
    width: 100%; padding: 12px; border-radius: 8px;
    background: #0A0A0F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif; min-height: 44px;
    box-sizing: border-box;
}
.m-form-input:focus { border-color: #6B46C1; outline: none; }
.m-form-textarea { resize: vertical; min-height: 80px; }
.m-form-submit {
    width: 100%; padding: 14px; border-radius: 10px; border: none;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 700;
    cursor: pointer; min-height: 48px; font-family: Inter, sans-serif;
    margin-top: 6px;
}
.m-form-submit:active { background: #8B5CF6; }
.m-form-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; cursor: pointer; text-decoration: none;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    transition: background 0.2s;
}
.m-fab:active { background: #8B5CF6; }
.m-card-action { font-size: 11px; padding: 4px 10px; min-height: 28px; min-width: 36px; margin-top: 4px; }
.m-avail-section { margin-bottom: 20px; }
.m-avail-header { color: #fff; font-size: 15px; font-weight: 700; margin: 0 0 4px; }
.m-avail-sub { font-size: 12px; color: #6B6B7B; margin: 0 0 14px; }
.m-avail-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-avail-body { flex: 1; min-width: 0; }
.m-avail-title { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-avail-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; }
.m-avail-meta i { font-size: 10px; }
.m-avail-actions { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; flex-shrink: 0; }
.m-avail-price { font-size: 14px; font-weight: 700; color: #10B981; }
.m-avail-spots { font-size: 10px; color: #A8A8B8; }
.m-section-divider { border-top: 1px solid #2D2D3F; padding-top: 16px; margin-top: 16px; margin-bottom: 20px; }
.m-section-icon { color: #8B5CF6; }
.m-pkg-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 10px;
}
.m-pkg-name { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 4px; }
.m-pkg-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-pkg-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 10px; border-top: 1px solid #2D2D3F;
}
.m-pkg-meta { font-size: 11px; color: #6B6B7B; display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 8px; }
.m-pkg-meta i { font-size: 10px; color: #6B6B7B; }
.m-camp-dates { font-size: 11px; color: #F59E0B; margin-bottom: 6px; }
.m-camp-dates i { font-size: 10px; }
.m-dev-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; margin-bottom: 10px;
}
.m-dev-name { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 4px; }
.m-dev-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-dev-meta { font-size: 11px; color: #6B6B7B; display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px; }
.m-dev-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-waitlist-badge { font-size: 10px; padding: 2px 6px; border-radius: 4px; background: rgba(245,158,11,0.15); color: #F59E0B; font-weight: 600; }
.m-wl-section { margin-top: 24px; padding-top: 16px; border-top: 2px solid #2D2D3F; }
.m-wl-header { font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 4px; }
.m-wl-sub { font-size: 12px; color: #6B6B7B; margin: 0 0 14px; }
.m-wl-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 12px;
}
.m-wl-avatar {
    width: 40px; height: 40px; border-radius: 50%; background: rgba(107,70,193,0.15);
    color: #8B5CF6; display: flex; align-items: center; justify-content: center;
    font-size: 14px; font-weight: 700; flex-shrink: 0;
}
.m-wl-info { flex: 1; min-width: 0; }
.m-wl-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-wl-detail { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-wl-product { font-size: 11px; color: #6B6B7B; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-wl-actions-col { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; flex-shrink: 0; }
.m-wl-pos { font-size: 11px; font-weight: 600; color: #F59E0B; }
.m-wl-status-offered { color: #10B981; }
</style>

<div class="m-sessions">
    <!-- Tabs -->
    <div class="m-segment-control">
        <button class="m-segment m-segment-active" data-panel="upcoming" aria-pressed="true">
            <i class="fas fa-calendar"></i> Upcoming
        </button>
        <button class="m-segment" data-panel="booking" aria-pressed="false">
            <i class="fas fa-plus-circle"></i> Book
        </button>
        <?php if ($isAnyCoach && !empty($waitlistManagement)): ?>
        <button class="m-segment" data-panel="waitlist" aria-pressed="false">
            <i class="fas fa-clock"></i> Waitlist
        </button>
        <?php endif; ?>
        <div class="m-segment-slider"></div>
    </div>

    <?php if ($waitlistOffer): ?>
    <!-- Waitlist Offer Banner -->
    <div style="margin:0 16px 16px;padding:16px;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:12px;">
        <div style="font-size:14px;font-weight:700;color:#10B981;margin-bottom:6px;"><i class="fas fa-check-circle"></i> Spot Available!</div>
        <p style="font-size:13px;color:#A8A8B8;margin:0 0 12px;">You have been offered a spot for: <strong style="color:#fff;"><?= htmlspecialchars($waitlistOffer['session_title'] ?? $waitlistOffer['package_name'] ?? $waitlistOffer['template_name'] ?? '') ?></strong></p>
        <p style="font-size:11px;color:#F59E0B;margin:0 0 12px;"><i class="fas fa-clock"></i> Offer expires: <?= date('M j, Y g:i A', strtotime($waitlistOffer['token_expires_at'])) ?></p>
        <?php if (!empty($waitlistOffer['session_id'])): ?>
        <form method="POST" action="process_booking.php" style="display:inline;">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="book_session">
            <input type="hidden" name="session_id" value="<?= (int)$waitlistOffer['session_id'] ?>">
            <input type="hidden" name="waitlist_token" value="<?= htmlspecialchars($waitlistToken) ?>">
            <input type="hidden" name="pwa_context" value="1">
            <button type="submit" class="m-book-btn m-book-btn-primary" style="padding:12px 24px;font-size:14px;"><i class="fas fa-cart-plus"></i> Purchase Now</button>
        </form>
        <?php elseif (!empty($waitlistOffer['package_id'])): ?>
        <form action="process_purchase_package.php" method="POST" style="display:inline;">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="package_id" value="<?= (int)$waitlistOffer['package_id'] ?>">
            <input type="hidden" name="waitlist_token" value="<?= htmlspecialchars($waitlistToken) ?>">
            <input type="hidden" name="pwa_context" value="1">
            <button type="submit" class="m-book-btn m-book-btn-primary" style="padding:12px 24px;font-size:14px;"><i class="fas fa-cart-plus"></i> Purchase Now</button>
        </form>
        <?php elseif (!empty($waitlistOffer['template_id'])): ?>
        <form method="POST" action="process_booking.php" style="display:inline;">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="register_dev_program">
            <input type="hidden" name="template_id" value="<?= (int)$waitlistOffer['template_id'] ?>">
            <input type="hidden" name="program_type" value="player_dev">
            <input type="hidden" name="waitlist_token" value="<?= htmlspecialchars($waitlistToken) ?>">
            <input type="hidden" name="pwa_context" value="1">
            <button type="submit" class="m-book-btn m-book-btn-primary" style="padding:12px 24px;font-size:14px;"><i class="fas fa-plus"></i> Enroll Now</button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Filter Bar -->
    <div class="m-filter-toggle" onclick="mToggleFilters(this)" id="m-filter-toggle">
        <span><i class="fas fa-sliders"></i> Filters<?php
            $hasFilters = ($filterType !== '' || $filterLocation !== '' || $filterPeriod !== 'all' || $filterSkill !== '' || $filterCoach !== '' || $showHistory);
            if ($hasFilters): ?> (active)<?php endif;
        ?></span>
        <i class="fas fa-chevron-down"></i>
    </div>
    <form method="GET" id="m-filter-form" class="m-filter-bar">
        <input type="hidden" name="page" value="sessions">
        <select name="filter_period" class="m-filter-select" onchange="this.form.submit()">
            <option value="all"<?= $filterPeriod === 'all' ? ' selected' : '' ?>>All Time</option>
            <option value="week"<?= $filterPeriod === 'week' ? ' selected' : '' ?>>This Week</option>
            <option value="month"<?= $filterPeriod === 'month' ? ' selected' : '' ?>>This Month</option>
        </select>
        <select name="filter_type" class="m-filter-select" onchange="this.form.submit()">
            <option value="">All Types</option>
            <?php foreach ($filterSessionTypes as $ft): ?>
            <option value="<?= htmlspecialchars($ft) ?>"<?= $filterType === $ft ? ' selected' : '' ?>><?= htmlspecialchars($ft) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_skill" class="m-filter-select" onchange="this.form.submit()">
            <option value="">All Skills</option>
            <?php foreach ($filterSkillTypes as $sk): ?>
            <option value="<?= (int)$sk['id'] ?>"<?= $filterSkill == $sk['id'] ? ' selected' : '' ?>><?= htmlspecialchars($sk['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_coach" class="m-filter-select" onchange="this.form.submit()">
            <option value="">All Coaches</option>
            <?php foreach ($filterCoaches as $fc): ?>
            <option value="<?= (int)$fc['id'] ?>"<?= $filterCoach == $fc['id'] ? ' selected' : '' ?>><?= htmlspecialchars($fc['first_name'] . ' ' . $fc['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_location" class="m-filter-select" onchange="this.form.submit()">
            <option value="">All Locations</option>
            <?php foreach ($filterLocations as $fl): ?>
            <option value="<?= htmlspecialchars($fl) ?>"<?= $filterLocation === $fl ? ' selected' : '' ?>><?= htmlspecialchars($fl) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="m-filter-check">
            <input type="checkbox" name="history" value="1"<?= $showHistory ? ' checked' : '' ?> onchange="this.form.submit()">
            Past sessions
        </label>
    </form>

    <!-- View Toggle: List / Calendar -->
    <div class="m-view-toggle" id="m-view-toggle">
        <button class="m-view-btn m-view-active" type="button" onclick="mSetView('list', this)"><i class="fas fa-list"></i> List</button>
        <button class="m-view-btn" type="button" onclick="mSetView('calendar', this)"><i class="fas fa-calendar-alt"></i> Calendar</button>
    </div>

    <!-- Upcoming Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-upcoming">

        <!-- Calendar View -->
        <div id="m-cal-wrap" style="display:none;">
            <div class="m-calendar">
                <div class="m-cal-header">
                    <button class="m-cal-nav" type="button" onclick="mCalNav(-1)"><i class="fas fa-chevron-left"></i></button>
                    <span class="m-cal-title" id="m-cal-title"></span>
                    <button class="m-cal-nav" type="button" onclick="mCalNav(1)"><i class="fas fa-chevron-right"></i></button>
                </div>
                <div class="m-cal-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span>S</span></div>
                <div class="m-cal-grid" id="m-cal-grid"></div>
            </div>
        </div>

        <!-- List View -->
        <div id="m-list-wrap">
        <?php if (empty($upcomingSessions)): ?>
            <div class="m-empty-state">
                <i class="fas fa-calendar-xmark"></i>
                <p>No upcoming sessions scheduled</p>
            </div>
        <?php else: ?>
            <?php foreach ($upcomingSessions as $idx => $sess):
                $sDate = strtotime($sess['session_date']);
                $sTime = $sess['session_time'] ? date('g:i A', strtotime($sess['session_time'])) : '';
                $isBooked = !empty($sess['booking_id']);
                $isOnWaitlist = !empty($sess['waitlist_id']);
                $attendeeCount = (int)($sess['attendee_count'] ?? 0);
                $maxParticipants = (int)($sess['max_participants'] ?? 0);
                $isFull = ($maxParticipants > 0 && $attendeeCount >= $maxParticipants);
                $coachName = trim(($sess['coach_first_name'] ?? '') . ' ' . ($sess['coach_last_name'] ?? ''));
            ?>
            <div class="m-sess-card" tabindex="0" data-date="<?= date('Y-m-d', $sDate) ?>" onclick="mShowDetail(<?= $idx ?>)" onkeydown="if(event.key==='Enter')mShowDetail(<?= $idx ?>)">
                <div class="m-sess-date">
                    <span class="m-sess-date-month"><?= date('M', $sDate) ?></span>
                    <span class="m-sess-date-day"><?= date('j', $sDate) ?></span>
                </div>
                <div class="m-sess-body">
                    <div class="m-sess-title"><?= htmlspecialchars($sess['title']) ?></div>
                    <div class="m-sess-detail">
                        <?php if ($sTime): ?><span><i class="fas fa-clock"></i> <?= $sTime ?></span><?php endif; ?>
                        <?php if (!empty($sess['duration_minutes'])): ?><span>&middot; <?= (int)$sess['duration_minutes'] ?>min</span><?php endif; ?>
                        <?php if (!empty($sess['session_type'])): ?><span>&middot; <?= htmlspecialchars($sess['session_type']) ?></span><?php endif; ?>
                    </div>
                    <?php if ($coachName): ?>
                    <div style="font-size:11px;color:#6B6B7B;margin-top:2px;"><i class="fas fa-user-tie"></i> <?= htmlspecialchars($coachName) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($sess['arena']) || !empty($sess['location_name'])): ?>
                    <div style="font-size:11px;color:#6B6B7B;margin-top:2px;"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($sess['location_name'] ?? $sess['arena']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="m-sess-actions">
                    <?php if ($isAnyCoach): ?>
                        <span class="m-badge m-badge-count"><i class="fas fa-users"></i> <?= $attendeeCount ?><?php if ($maxParticipants): ?>/<?= $maxParticipants ?><?php endif; ?></span>
                        <?php if (!empty($sess['waitlist_count']) && (int)$sess['waitlist_count'] > 0): ?>
                        <span class="m-badge m-badge-waitlist"><i class="fas fa-clock"></i> <?= (int)$sess['waitlist_count'] ?> waitlisted</span>
                        <?php endif; ?>
                    <?php elseif ($isBooked): ?>
                        <span class="m-badge m-badge-booked"><i class="fas fa-check"></i> Booked</span>
                        <button type="button" class="m-book-btn m-book-btn-danger m-card-action" onclick="event.stopPropagation();mCancelBooking(<?= (int)$sess['booking_id'] ?>)"><i class="fas fa-times"></i> Cancel</button>
                    <?php elseif ($isOnWaitlist): ?>
                        <span class="m-badge m-badge-waitlist"><i class="fas fa-clock"></i> #<?= (int)$sess['waitlist_position'] ?></span>
                        <button type="button" class="m-book-btn m-book-btn-danger m-card-action" onclick="event.stopPropagation();mLeaveWaitlist(<?= (int)$sess['id'] ?>)"><i class="fas fa-times"></i> Leave</button>
                    <?php elseif ($isFull): ?>
                        <span class="m-badge m-badge-full"><i class="fas fa-ban"></i> Full</span>
                        <button type="button" class="m-book-btn m-book-btn-warning m-card-action" onclick="event.stopPropagation();mJoinWaitlist(<?= (int)$sess['id'] ?>)"><i class="fas fa-clock"></i> Waitlist</button>
                    <?php else: ?>
                        <span class="m-badge m-badge-upcoming">Open</span>
                        <?php
                            // Template sessions have no waitlist_count field (they came from training_session_templates query)
                            $isTemplateSession = !array_key_exists('waitlist_count', $sess);
                        ?>
                        <?php if ($isTemplateSession): ?>
                        <button type="button" class="m-book-btn m-book-btn-primary m-card-action" onclick="event.stopPropagation();mBookTemplateSession(<?= (int)$sess['id'] ?>)"><i class="fas fa-plus"></i> Register</button>
                        <?php else: ?>
                        <button type="button" class="m-book-btn m-book-btn-primary m-card-action" onclick="event.stopPropagation();mBookSession(<?= (int)$sess['id'] ?>)"><i class="fas fa-plus"></i> Register</button>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        </div>
    </div>

    <!-- Booking Tab -->
    <div class="m-tab-panel" id="m-panel-booking">
        <!-- Available Sessions to Register -->
        <div class="m-avail-section">
            <h4 class="m-avail-header"><i class="fas fa-calendar-day" style="color:#8B5CF6;"></i> Available Sessions</h4>
            <p class="m-avail-sub">Browse and register for upcoming group sessions</p>
            <?php if (empty($availableSessions)): ?>
                <div class="m-empty-state"><i class="fas fa-calendar-xmark"></i><p>No sessions available for booking</p></div>
            <?php else: ?>
                <?php foreach ($availableSessions as $avSess):
                    $avDate = strtotime($avSess['session_date']);
                    $avTime = !empty($avSess['session_time']) ? date('g:i A', strtotime($avSess['session_time'])) : '';
                    $avCoach = trim(($avSess['coach_first_name'] ?? '') . ' ' . ($avSess['coach_last_name'] ?? ''));
                    $avCount = (int)($avSess['registered_count'] ?? 0);
                    $avMax = (int)($avSess['max_participants'] ?? 0);
                    $avSpotsLeft = $avMax > 0 ? $avMax - $avCount : 999;
                    $avIsFull = ($avMax > 0 && $avCount >= $avMax);
                    $avIsBooked = !empty($avSess['date_id'])
                        ? in_array($avSess['date_id'], $userBookedTemplateDateIds)
                        : in_array($avSess['id'], $userBookedSessionIds);
                    $avPrice = (float)($avSess['price'] ?? 0);
                ?>
                <div class="m-avail-card">
                    <div class="m-sess-date">
                        <span class="m-sess-date-month"><?= date('M', $avDate) ?></span>
                        <span class="m-sess-date-day"><?= date('j', $avDate) ?></span>
                    </div>
                    <div class="m-avail-body">
                        <div class="m-avail-title"><?= htmlspecialchars($avSess['title'] ?? $avSess['session_type_name'] ?? 'Training Session') ?></div>
                        <div class="m-avail-meta">
                            <?php if ($avTime): ?><span><i class="fas fa-clock"></i> <?= $avTime ?></span><?php endif; ?>
                            <?php if (!empty($avSess['duration_minutes'])): ?><span>&middot; <?= (int)$avSess['duration_minutes'] ?>min</span><?php endif; ?>
                            <?php if ($avCoach): ?><span>&middot; <i class="fas fa-user-tie"></i> <?= htmlspecialchars($avCoach) ?></span><?php endif; ?>
                        </div>
                        <?php if (!empty($avSess['location_name'])): ?>
                        <div style="font-size:11px;color:#6B6B7B;margin-top:2px;"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($avSess['location_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-avail-actions">
                        <?php if ($avPrice > 0): ?><span class="m-avail-price">$<?= number_format($avPrice, 0) ?></span><?php endif; ?>
                        <?php if ($avMax > 0): ?><span class="m-avail-spots"><?= $avSpotsLeft > 0 ? $avSpotsLeft . ' spot' . ($avSpotsLeft !== 1 ? 's' : '') : 'Full' ?></span><?php endif; ?>
                        <?php if ($avIsBooked): ?>
                            <span class="m-badge m-badge-booked"><i class="fas fa-check"></i> Registered</span>
                        <?php elseif ($avIsFull): ?>
                            <?php if (!empty($avSess['date_id'])): ?>
                            <span class="m-badge m-badge-full"><i class="fas fa-ban"></i> Full</span>
                            <?php else: ?>
                            <button type="button" class="m-book-btn m-book-btn-warning m-card-action" onclick="mJoinWaitlist(<?= (int)$avSess['id'] ?>)"><i class="fas fa-clock"></i> Waitlist</button>
                            <?php endif; ?>
                        <?php elseif (!empty($avSess['date_id']) ? false : in_array($avSess['id'], $waitlistOnlySessionIds)): ?>
                            <span class="m-waitlist-badge">Waitlist Only</span>
                            <button type="button" class="m-book-btn m-book-btn-warning m-card-action" onclick="mJoinWaitlist(<?= (int)$avSess['id'] ?>)"><i class="fas fa-clock"></i> Join Waitlist</button>
                        <?php else: ?>
                            <?php if (!empty($avSess['date_id'])): ?>
                            <button type="button" class="m-book-btn m-book-btn-primary m-card-action" onclick="mBookTemplateSession(<?= (int)$avSess['date_id'] ?>)"><i class="fas fa-plus"></i> Register</button>
                            <?php else: ?>
                            <button type="button" class="m-book-btn m-book-btn-primary m-card-action" onclick="mBookSession(<?= (int)$avSess['id'] ?>)"><i class="fas fa-plus"></i> Register</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Book Private Session Form -->
        <div style="border-top:1px solid #2D2D3F;padding-top:16px;margin-bottom:20px;">
            <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0 0 4px;"><i class="fas fa-user-plus" style="color:#8B5CF6;"></i> Book Private Session</h4>
            <p style="font-size:12px;color:#6B6B7B;margin:0 0 14px;">Schedule a one-on-one session with a coach</p>
            <form method="POST" action="process_booking.php" id="m-private-form">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="book_private_session">
                <input type="hidden" name="pwa_context" value="1">
                <div class="m-form-group">
                    <label class="m-form-label">Session Type <span class="m-required">*</span></label>
                    <select name="session_type_id" class="m-form-input" required>
                        <option value="">Select type...</option>
                        <?php foreach ($sessionTypes as $type): ?>
                        <option value="<?= (int)$type['id'] ?>" data-price="<?= (float)$type['default_price'] ?>"><?= htmlspecialchars($type['name']) ?> &mdash; $<?= number_format((float)$type['default_price'], 0) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Coach <span class="m-required">*</span></label>
                    <select name="coach_id" class="m-form-input" required>
                        <option value="">Select coach...</option>
                        <?php foreach ($bookingCoaches as $bc): ?>
                        <option value="<?= (int)$bc['id'] ?>"><?= htmlspecialchars($bc['first_name'] . ' ' . $bc['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display:flex;gap:10px;">
                    <div class="m-form-group" style="flex:1;">
                        <label class="m-form-label">Date <span class="m-required">*</span></label>
                        <input type="date" name="session_date" class="m-form-input" required min="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="m-form-group" style="flex:1;">
                        <label class="m-form-label">Time <span class="m-required">*</span></label>
                        <select name="session_time" class="m-form-input" required>
                            <option value="">Time...</option>
                            <?php foreach (['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00','17:00','18:00','19:00','20:00'] as $t): ?>
                            <option value="<?= $t ?>"><?= date('g:i A', strtotime($t)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Notes (optional)</label>
                    <textarea name="notes" class="m-form-input m-form-textarea" rows="3" placeholder="Any goals or focus areas?"></textarea>
                </div>
                <button type="submit" class="m-form-submit"><i class="fas fa-check"></i> Book Session</button>
            </form>
        </div>

        <!-- Session Types List -->
        <div style="border-top:1px solid #2D2D3F;padding-top:16px;">
            <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0 0 12px;"><i class="fas fa-tag" style="color:#8B5CF6;"></i> Session Types</h4>
            <?php if (empty($sessionTypes)): ?>
                <div class="m-empty-state"><i class="fas fa-tag"></i><p>No session types available</p></div>
            <?php else: ?>
                <?php foreach ($sessionTypes as $type): ?>
                <div class="m-type-card">
                    <h4 class="m-type-name"><?= htmlspecialchars($type['name']) ?></h4>
                    <?php if (!empty($type['description'])): ?>
                    <p class="m-type-desc"><?= htmlspecialchars($type['description']) ?></p>
                    <?php endif; ?>
                    <div class="m-type-meta">
                        <div>
                            <span class="m-type-price">$<?= number_format((float)$type['default_price'], 2) ?></span>
                            <span class="m-type-dur"> &middot; <?= (int)$type['duration_minutes'] ?> min</span>
                        </div>
                        <a href="?page=sessions&type=<?= (int)$type['id'] ?>" class="m-book-btn m-book-btn-primary">View Sessions</a>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Training Packages Section -->
        <div class="m-section-divider">
            <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0 0 4px;"><i class="fas fa-box m-section-icon"></i> Training Packages</h4>
            <p style="font-size:12px;color:#6B6B7B;margin:0 0 14px;">Purchase session packages and save</p>
            <?php if (empty($regularPackages)): ?>
                <div class="m-empty-state"><i class="fas fa-box-open"></i><p>No packages available</p></div>
            <?php else: ?>
                <?php foreach ($regularPackages as $pkg):
                    $pkgPrice = (float)($pkg['price'] ?? 0);
                    $pkgCredits = (int)($pkg['credits'] ?? 0);
                    $pkgDays = (int)($pkg['valid_days'] ?? 0);
                    $isWaitlistOnly = !empty($pkg['waitlist_only']);
                    $isOnPkgWaitlist = in_array($pkg['id'], $userWaitlistPackageIds);
                ?>
                <div class="m-pkg-card">
                    <div class="m-pkg-name"><?= htmlspecialchars($pkg['name']) ?>
                        <?php if ($isWaitlistOnly): ?> <span class="m-waitlist-badge">Waitlist Only</span><?php endif; ?>
                    </div>
                    <?php if (!empty($pkg['description'])): ?>
                    <p class="m-pkg-desc"><?= htmlspecialchars($pkg['description']) ?></p>
                    <?php endif; ?>
                    <div class="m-pkg-meta">
                        <?php if ($pkgCredits > 0): ?><span><i class="fas fa-ticket"></i> <?= $pkgCredits ?> credit<?= $pkgCredits !== 1 ? 's' : '' ?></span><?php endif; ?>
                        <?php if ($pkgDays > 0): ?><span><i class="fas fa-clock"></i> <?= $pkgDays ?> day<?= $pkgDays !== 1 ? 's' : '' ?> valid</span><?php endif; ?>
                        <?php if (!empty($pkg['package_type']) && $pkg['package_type'] !== 'credits'): ?>
                        <span><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pkg['package_type']))) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="m-pkg-footer">
                        <span class="m-avail-price">$<?= number_format($pkgPrice, 2) ?></span>
                        <?php if ($isOnPkgWaitlist): ?>
                            <span class="m-badge m-badge-waitlist"><i class="fas fa-clock"></i> On Waitlist</span>
                        <?php elseif ($isWaitlistOnly): ?>
                            <button type="button" class="m-book-btn m-book-btn-warning" onclick="mJoinPackageWaitlist(<?= (int)$pkg['id'] ?>)"><i class="fas fa-clock"></i> Join Waitlist</button>
                        <?php else: ?>
                            <form action="process_purchase_package.php" method="POST" style="display:inline;">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="package_id" value="<?= (int)$pkg['id'] ?>">
                                <input type="hidden" name="pwa_context" value="1">
                                <button type="submit" class="m-book-btn m-book-btn-primary"><i class="fas fa-cart-plus"></i> Purchase</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Programs & Camps Section -->
        <div class="m-section-divider">
            <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0 0 4px;"><i class="fas fa-campground m-section-icon"></i> Programs &amp; Camps</h4>
            <p style="font-size:12px;color:#6B6B7B;margin:0 0 14px;">Multi-week training programs and camp sessions</p>
            <?php if (empty($campPackages) && empty($trainingPrograms)): ?>
                <div class="m-empty-state"><i class="fas fa-campground"></i><p>No programs or camps available</p></div>
            <?php else: ?>
                <?php foreach ($campPackages as $camp):
                    $campPrice = (float)($camp['price'] ?? 0);
                    $campStart = !empty($camp['camp_start_date']) ? date('M j', strtotime($camp['camp_start_date'])) : '';
                    $campEnd = !empty($camp['camp_end_date']) ? date('M j, Y', strtotime($camp['camp_end_date'])) : '';
                    $isWaitlistOnly = !empty($camp['waitlist_only']);
                    $isOnCampWaitlist = in_array($camp['id'], $userWaitlistPackageIds);
                ?>
                <div class="m-pkg-card">
                    <div class="m-pkg-name"><?= htmlspecialchars($camp['name']) ?>
                        <?php if ($isWaitlistOnly): ?> <span class="m-waitlist-badge">Waitlist Only</span><?php endif; ?>
                    </div>
                    <?php if (!empty($camp['description'])): ?>
                    <p class="m-pkg-desc"><?= htmlspecialchars($camp['description']) ?></p>
                    <?php endif; ?>
                    <?php if ($campStart && $campEnd): ?>
                    <div class="m-camp-dates"><i class="fas fa-calendar-range"></i> <?= $campStart ?> &ndash; <?= $campEnd ?></div>
                    <?php endif; ?>
                    <?php if (!empty($camp['daily_start_time']) && !empty($camp['daily_end_time'])): ?>
                    <div class="m-pkg-meta"><span><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($camp['daily_start_time'])) ?> &ndash; <?= date('g:i A', strtotime($camp['daily_end_time'])) ?></span></div>
                    <?php endif; ?>
                    <div class="m-pkg-footer">
                        <span class="m-avail-price">$<?= number_format($campPrice, 2) ?></span>
                        <?php if ($isOnCampWaitlist): ?>
                            <span class="m-badge m-badge-waitlist"><i class="fas fa-clock"></i> On Waitlist</span>
                        <?php elseif ($isWaitlistOnly): ?>
                            <button type="button" class="m-book-btn m-book-btn-warning" onclick="mJoinPackageWaitlist(<?= (int)$camp['id'] ?>)"><i class="fas fa-clock"></i> Join Waitlist</button>
                        <?php else: ?>
                            <form action="process_purchase_package.php" method="POST" style="display:inline;">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="package_id" value="<?= (int)$camp['id'] ?>">
                                <input type="hidden" name="pwa_context" value="1">
                                <button type="submit" class="m-book-btn m-book-btn-primary"><i class="fas fa-cart-plus"></i> Enroll</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php foreach ($trainingPrograms as $tp):
                    $tpPrice = (float)($tp['price'] ?? 0);
                    $tpWeeks = (int)($tp['duration_weeks'] ?? 0);
                    $tpCoach = trim(($tp['coach_first_name'] ?? '') . ' ' . ($tp['coach_last_name'] ?? ''));
                    $isWaitlistOnly = !empty($tp['waitlist_only']);
                    $isOnTpWaitlist = in_array($tp['id'], $userWaitlistTemplateIds);
                ?>
                <div class="m-dev-card">
                    <div class="m-dev-name"><?= htmlspecialchars($tp['name']) ?>
                        <?php if ($isWaitlistOnly): ?> <span class="m-waitlist-badge">Waitlist Only</span><?php endif; ?>
                    </div>
                    <?php if (!empty($tp['description'])): ?>
                    <p class="m-dev-desc"><?= htmlspecialchars($tp['description']) ?></p>
                    <?php endif; ?>
                    <div class="m-dev-meta">
                        <?php if ($tpWeeks > 0): ?><span><i class="fas fa-calendar-week"></i> <?= $tpWeeks ?> week<?= $tpWeeks !== 1 ? 's' : '' ?></span><?php endif; ?>
                        <?php if (!empty($tp['duration_minutes'])): ?><span><i class="fas fa-clock"></i> <?= (int)$tp['duration_minutes'] ?>min/session</span><?php endif; ?>
                        <?php if ($tpCoach): ?><span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($tpCoach) ?></span><?php endif; ?>
                    </div>
                    <div class="m-dev-footer">
                        <span class="m-avail-price"><?= $tpPrice > 0 ? '$' . number_format($tpPrice, 2) : 'Free' ?></span>
                        <?php if ($isOnTpWaitlist): ?>
                            <span class="m-badge m-badge-waitlist"><i class="fas fa-clock"></i> On Waitlist</span>
                        <?php elseif ($isWaitlistOnly): ?>
                            <button type="button" class="m-book-btn m-book-btn-warning" onclick="mJoinTemplateWaitlist(<?= (int)$tp['id'] ?>)"><i class="fas fa-clock"></i> Join Waitlist</button>
                        <?php else: ?>
                            <form method="POST" action="process_booking.php" style="display:inline;">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="register_dev_program">
                                <input type="hidden" name="template_id" value="<?= (int)$tp['id'] ?>">
                                <input type="hidden" name="program_type" value="player_dev">
                                <input type="hidden" name="pwa_context" value="1">
                                <button type="submit" class="m-book-btn m-book-btn-primary"><i class="fas fa-plus"></i> Enroll</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Long-Term Development Section -->
        <div class="m-section-divider">
            <h4 style="color:#fff;font-size:15px;font-weight:700;margin:0 0 4px;"><i class="fas fa-chart-line m-section-icon"></i> Long-Term Development</h4>
            <p style="font-size:12px;color:#6B6B7B;margin:0 0 14px;">Extended development programs for skill progression</p>
            <?php if (empty($devPrograms)): ?>
                <div class="m-empty-state"><i class="fas fa-chart-line"></i><p>No development programs available</p></div>
            <?php else: ?>
                <?php foreach ($devPrograms as $dp):
                    $dpPrice = (float)($dp['price'] ?? 0);
                    $dpWeeks = (int)($dp['duration_weeks'] ?? 0);
                    $dpCoach = trim(($dp['coach_first_name'] ?? '') . ' ' . ($dp['coach_last_name'] ?? ''));
                    $isWaitlistOnly = !empty($dp['waitlist_only']);
                    $isOnDpWaitlist = in_array($dp['id'], $userWaitlistTemplateIds);
                    // Map session_type to program_type: on_ice → goalie_dev, all others → player_dev
                    $dpType = ($dp['session_type'] === 'on_ice') ? 'goalie_dev' : 'player_dev';
                ?>
                <div class="m-dev-card">
                    <div class="m-dev-name"><?= htmlspecialchars($dp['name']) ?>
                        <?php if ($isWaitlistOnly): ?> <span class="m-waitlist-badge">Waitlist Only</span><?php endif; ?>
                    </div>
                    <?php if (!empty($dp['description'])): ?>
                    <p class="m-dev-desc"><?= htmlspecialchars($dp['description']) ?></p>
                    <?php endif; ?>
                    <div class="m-dev-meta">
                        <?php if ($dpWeeks > 0): ?><span><i class="fas fa-calendar-week"></i> <?= $dpWeeks ?> week<?= $dpWeeks !== 1 ? 's' : '' ?></span><?php endif; ?>
                        <?php if ($dpCoach): ?><span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($dpCoach) ?></span><?php endif; ?>
                        <?php if (!empty($dp['max_participants'])): ?><span><i class="fas fa-users"></i> Max <?= (int)$dp['max_participants'] ?></span><?php endif; ?>
                    </div>
                    <div class="m-dev-footer">
                        <span class="m-avail-price"><?= $dpPrice > 0 ? '$' . number_format($dpPrice, 2) : 'Free' ?></span>
                        <?php if ($isOnDpWaitlist): ?>
                            <span class="m-badge m-badge-waitlist"><i class="fas fa-clock"></i> On Waitlist</span>
                        <?php elseif ($isWaitlistOnly): ?>
                            <button type="button" class="m-book-btn m-book-btn-warning" onclick="mJoinTemplateWaitlist(<?= (int)$dp['id'] ?>)"><i class="fas fa-clock"></i> Join Waitlist</button>
                        <?php else: ?>
                            <form method="POST" action="process_booking.php" style="display:inline;">
                                <?= csrfTokenInput() ?>
                                <input type="hidden" name="action" value="register_dev_program">
                                <input type="hidden" name="template_id" value="<?= (int)$dp['id'] ?>">
                                <input type="hidden" name="program_type" value="<?= htmlspecialchars($dpType) ?>">
                                <input type="hidden" name="pwa_context" value="1">
                                <button type="submit" class="m-book-btn m-book-btn-primary"><i class="fas fa-plus"></i> Enroll</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Waitlist Management Tab (Coaches Only) -->
    <?php if ($isAnyCoach && !empty($waitlistManagement)): ?>
    <div class="m-tab-panel" id="m-panel-waitlist">
        <div class="m-wl-section" style="margin-top:0;padding-top:0;border-top:none;">
            <h4 class="m-wl-header"><i class="fas fa-clipboard-list" style="color:#F59E0B;"></i> Waitlist Management</h4>
            <p class="m-wl-sub"><?= count($waitlistManagement) ?> athlete<?= count($waitlistManagement) !== 1 ? 's' : '' ?> on waitlists</p>

            <?php foreach ($waitlistManagement as $wm):
                $wmName = trim(($wm['first_name'] ?? '') . ' ' . ($wm['last_name'] ?? ''));
                $wmInitials = strtoupper(substr($wm['first_name'] ?? '', 0, 1) . substr($wm['last_name'] ?? '', 0, 1));
                $wmProduct = $wm['session_title'] ?? $wm['package_name'] ?? $wm['template_name'] ?? 'Unknown';
                $wmType = $wm['session_id'] ? 'Session' : ($wm['package_id'] ? 'Package' : 'Program');
                $wmIsOffered = ($wm['status'] === 'offered');
                $wmExpires = !empty($wm['token_expires_at']) ? date('M j, g:i A', strtotime($wm['token_expires_at'])) : '';
            ?>
            <div class="m-wl-card">
                <div class="m-wl-avatar"><?= htmlspecialchars($wmInitials) ?></div>
                <div class="m-wl-info">
                    <div class="m-wl-name"><?= htmlspecialchars($wmName) ?></div>
                    <div class="m-wl-detail"><?= htmlspecialchars($wm['email'] ?? '') ?></div>
                    <div class="m-wl-product"><i class="fas fa-tag"></i> <?= htmlspecialchars($wmProduct) ?> (<?= $wmType ?>)</div>
                    <?php if ($wmIsOffered && $wmExpires): ?>
                    <div style="font-size:10px;color:#10B981;margin-top:2px;"><i class="fas fa-envelope"></i> Offered &mdash; Expires <?= $wmExpires ?></div>
                    <?php endif; ?>
                </div>
                <div class="m-wl-actions-col">
                    <span class="m-wl-pos <?= $wmIsOffered ? 'm-wl-status-offered' : '' ?>"><?= $wmIsOffered ? 'Offered' : '#' . (int)$wm['position'] ?></span>
                    <?php if (!$wmIsOffered): ?>
                    <button type="button" class="m-book-btn m-book-btn-primary m-card-action" onclick="mOfferSpot(<?= (int)$wm['waitlist_id'] ?>)" title="Send enrollment email"><i class="fas fa-envelope"></i> Offer</button>
                    <?php endif; ?>
                    <button type="button" class="m-book-btn m-book-btn-warning m-card-action" onclick="mEnrollFromWaitlist(<?= (int)$wm['waitlist_id'] ?>)" title="Enroll directly"><i class="fas fa-user-plus"></i> Enroll</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($isAnyCoach): ?>
    <a href="?page=create_session" class="m-fab" title="Create Session"><i class="fas fa-plus"></i></a>
    <?php endif; ?>
</div>

<!-- Session Detail Bottom Sheet -->
<div class="m-sheet-overlay" id="m-detail-overlay" onclick="mCloseDetail()"></div>
<div class="m-sheet" id="m-detail-sheet">
    <div class="m-sheet-handle"></div>
    <div class="m-sheet-header">
        <span class="m-sheet-title" id="m-detail-title">Session Details</span>
        <button class="m-sheet-close" type="button" onclick="mCloseDetail()"><i class="fas fa-times"></i></button>
    </div>
    <div class="m-sheet-body" id="m-detail-body"></div>
</div>

<!-- Hidden form for quick booking (uses form submission for Stripe redirect) -->
<form id="m-book-form" method="POST" action="process_booking.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="book_session">
    <input type="hidden" name="session_id" id="m-book-session-id" value="">
    <input type="hidden" name="pwa_context" value="1">
</form>

<!-- Hidden form for template session registration (uses form submission for Stripe redirect) -->
<form id="m-book-template-form" method="POST" action="process_booking.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="register_template_session">
    <input type="hidden" name="session_date_id" id="m-book-template-date-id" value="">
    <input type="hidden" name="pwa_context" value="1">
</form>

<script>
var mSessions = <?= json_encode(array_map(function($s) use ($isAnyCoach) {
    $coachName = trim(($s['coach_first_name'] ?? '') . ' ' . ($s['coach_last_name'] ?? ''));
    $attendeeCount = (int)($s['attendee_count'] ?? 0);
    $maxParticipants = (int)($s['max_participants'] ?? 0);
    $isTemplate = !array_key_exists('waitlist_count', $s);
    return [
        'id' => (int)$s['id'],
        'title' => $s['title'] ?? '',
        'type' => $s['session_type'] ?? $s['session_type_name'] ?? '',
        'date' => $s['session_date'] ? date('l, M j, Y', strtotime($s['session_date'])) : '',
        'dateRaw' => $s['session_date'] ? date('Y-m-d', strtotime($s['session_date'])) : '',
        'time' => $s['session_time'] ? date('g:i A', strtotime($s['session_time'])) : '',
        'duration' => (int)($s['duration_minutes'] ?? 0),
        'coach' => $coachName,
        'location' => $s['location_name'] ?? $s['arena'] ?? '',
        'description' => $s['description'] ?? '',
        'practicePlan' => $s['practice_plan_name'] ?? '',
        'practicePlanDesc' => $s['practice_plan_desc'] ?? '',
        'price' => (float)($s['price'] ?? 0),
        'bookingId' => (int)($s['booking_id'] ?? 0),
        'isBooked' => !empty($s['booking_id']),
        'isCoach' => $isAnyCoach,
        'isFuture' => strtotime($s['session_date']) >= strtotime('today'),
        'attendeeCount' => $attendeeCount,
        'maxParticipants' => $maxParticipants,
        'isFull' => ($maxParticipants > 0 && $attendeeCount >= $maxParticipants),
        'isOnWaitlist' => !empty($s['waitlist_id']),
        'waitlistPosition' => (int)($s['waitlist_position'] ?? 0),
        'waitlistCount' => (int)($s['waitlist_count'] ?? 0),
        'isTemplate' => $isTemplate,
    ];
}, $upcomingSessions), JSON_HEX_TAG | JSON_HEX_AMP) ?>;

var mCalDates = <?= json_encode($calendarDates, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
var mCalMonth = new Date().getMonth();
var mCalYear = new Date().getFullYear();

document.querySelectorAll('.m-segment-control .m-segment').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var control = this.closest('.m-segment-control');
        control.querySelectorAll('.m-segment').forEach(function(s) {
            s.classList.remove('m-segment-active');
            s.setAttribute('aria-pressed', 'false');
        });
        this.classList.add('m-segment-active');
        this.setAttribute('aria-pressed', 'true');
        var panelId = this.getAttribute('data-panel');
        document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
        var target = document.getElementById('m-panel-' + panelId);
        if (target) target.classList.add('m-tab-visible');
    });
});

function mToggleFilters(el) {
    var bar = document.getElementById('m-filter-form');
    bar.classList.toggle('m-filter-visible');
    el.classList.toggle('m-filter-open');
}

function mSetView(view, btn) {
    document.querySelectorAll('.m-view-btn').forEach(function(b) { b.classList.remove('m-view-active'); });
    if (btn) btn.classList.add('m-view-active');
    var listWrap = document.getElementById('m-list-wrap');
    var calWrap = document.getElementById('m-cal-wrap');
    if (view === 'calendar') {
        listWrap.style.display = 'none';
        calWrap.style.display = 'block';
        mRenderCalendar();
    } else {
        listWrap.style.display = 'block';
        calWrap.style.display = 'none';
        document.querySelectorAll('.m-sess-card').forEach(function(c) { c.style.display = ''; });
    }
}

function mCalNav(dir) {
    mCalMonth += dir;
    if (mCalMonth > 11) { mCalMonth = 0; mCalYear++; }
    if (mCalMonth < 0) { mCalMonth = 11; mCalYear--; }
    mRenderCalendar();
}

function mRenderCalendar() {
    var months = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('m-cal-title').textContent = months[mCalMonth] + ' ' + mCalYear;
    var grid = document.getElementById('m-cal-grid');
    grid.innerHTML = '';
    var first = new Date(mCalYear, mCalMonth, 1);
    var lastDay = new Date(mCalYear, mCalMonth + 1, 0).getDate();
    var startDow = first.getDay();
    var today = new Date(); today.setHours(0,0,0,0);
    for (var i = 0; i < startDow; i++) {
        var empty = document.createElement('div');
        empty.className = 'm-cal-day m-cal-other';
        grid.appendChild(empty);
    }
    for (var d = 1; d <= lastDay; d++) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'm-cal-day';
        btn.textContent = d;
        var dt = new Date(mCalYear, mCalMonth, d);
        var key = dt.getFullYear() + '-' + String(dt.getMonth()+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        if (dt.getTime() === today.getTime()) btn.classList.add('m-cal-today');
        if (mCalDates[key]) {
            var dot = document.createElement('span');
            dot.className = 'm-cal-dot';
            btn.appendChild(dot);
        }
        btn.setAttribute('data-date', key);
        btn.addEventListener('click', function() {
            document.querySelectorAll('.m-cal-day.m-cal-selected').forEach(function(s) { s.classList.remove('m-cal-selected'); });
            this.classList.add('m-cal-selected');
            mFilterByDate(this.getAttribute('data-date'));
        });
        grid.appendChild(btn);
    }
}

function mFilterByDate(dateStr) {
    var listWrap = document.getElementById('m-list-wrap');
    listWrap.style.display = 'block';
    var cards = document.querySelectorAll('.m-sess-card');
    cards.forEach(function(c) {
        c.style.display = (c.getAttribute('data-date') === dateStr) ? '' : 'none';
    });
}

function mShowDetail(idx) {
    var s = mSessions[idx];
    if (!s) return;
    document.getElementById('m-detail-title').textContent = s.title || 'Session Details';
    var html = '';
    if (s.type) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-tag"></i></div><div><div class="m-detail-label">Type</div><div class="m-detail-value">' + mEsc(s.type) + '</div></div></div>';
    }
    html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-calendar"></i></div><div><div class="m-detail-label">Date</div><div class="m-detail-value">' + mEsc(s.date) + '</div></div></div>';
    if (s.time) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-clock"></i></div><div><div class="m-detail-label">Time</div><div class="m-detail-value">' + mEsc(s.time) + (s.duration ? ' (' + s.duration + ' min)' : '') + '</div></div></div>';
    }
    if (s.coach) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-user-tie"></i></div><div><div class="m-detail-label">Coach</div><div class="m-detail-value">' + mEsc(s.coach) + '</div></div></div>';
    }
    if (s.location) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-location-dot"></i></div><div><div class="m-detail-label">Location</div><div class="m-detail-value">' + mEsc(s.location) + '</div></div></div>';
    }
    if (s.description) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-align-left"></i></div><div><div class="m-detail-label">Description</div><div class="m-detail-value">' + mEsc(s.description) + '</div></div></div>';
    }
    if (s.practicePlan) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-clipboard-list"></i></div><div><div class="m-detail-label">Practice Plan</div><div class="m-detail-value">' + mEsc(s.practicePlan);
        if (s.practicePlanDesc) html += '<br><span style="font-size:12px;color:#A8A8B8;">' + mEsc(s.practicePlanDesc) + '</span>';
        html += '</div></div></div>';
    }
    if (s.price > 0) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-dollar-sign"></i></div><div><div class="m-detail-label">Price</div><div class="m-detail-value">$' + s.price.toFixed(2) + '</div></div></div>';
    }
    // Show capacity info
    if (s.maxParticipants > 0) {
        var spotsLeft = s.maxParticipants - s.attendeeCount;
        var spotsColor = spotsLeft <= 0 ? '#EF4444' : (spotsLeft <= 3 ? '#F59E0B' : '#10B981');
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-users"></i></div><div><div class="m-detail-label">Availability</div><div class="m-detail-value">' + s.attendeeCount + '/' + s.maxParticipants + ' booked';
        if (spotsLeft <= 0) {
            html += ' &mdash; <span style="color:#EF4444;font-weight:600;">Full</span>';
        } else {
            html += ' &mdash; <span style="color:' + spotsColor + ';font-weight:600;">' + spotsLeft + ' spot' + (spotsLeft !== 1 ? 's' : '') + ' left</span>';
        }
        html += '</div></div></div>';
    }
    // Show waitlist info for coaches
    if (s.isCoach && s.waitlistCount > 0) {
        html += '<div class="m-detail-row"><div class="m-detail-icon"><i class="fas fa-clock" style="color:#F59E0B;"></i></div><div><div class="m-detail-label">Waitlist</div><div class="m-detail-value" style="color:#F59E0B;">' + s.waitlistCount + ' on waitlist</div></div></div>';
    }
    html += '<div class="m-detail-actions">';
    if (s.isCoach && s.isFuture) {
        html += '<button type="button" class="m-book-btn m-book-btn-danger" onclick="mCancelSession(' + s.id + ')"><i class="fas fa-ban"></i> Cancel Session</button>';
    } else if (!s.isCoach) {
        if (s.isBooked && s.isFuture) {
            html += '<button type="button" class="m-book-btn m-book-btn-danger" onclick="mCancelBooking(' + s.bookingId + ')"><i class="fas fa-times"></i> Cancel Booking</button>';
        } else if (s.isOnWaitlist && s.isFuture) {
            html += '<span style="display:block;text-align:center;color:#F59E0B;font-size:13px;font-weight:600;margin-bottom:8px;"><i class="fas fa-clock"></i> You are #' + s.waitlistPosition + ' on the waitlist</span>';
            html += '<button type="button" class="m-book-btn m-book-btn-danger" onclick="mLeaveWaitlist(' + s.id + ')"><i class="fas fa-times"></i> Leave Waitlist</button>';
        } else if (!s.isBooked && s.isFuture) {
            if (s.isFull) {
                html += '<span style="display:block;text-align:center;color:#EF4444;font-size:13px;font-weight:600;margin-bottom:8px;"><i class="fas fa-ban"></i> This session is full</span>';
                if (!s.isTemplate) {
                    html += '<button type="button" class="m-book-btn m-book-btn-warning" onclick="mJoinWaitlist(' + s.id + ')"><i class="fas fa-clock"></i> Join Waitlist</button>';
                }
            } else {
                if (s.isTemplate) {
                    html += '<button type="button" class="m-book-btn m-book-btn-primary" onclick="mBookTemplateSession(' + s.id + ')"><i class="fas fa-plus"></i> Register</button>';
                } else {
                    html += '<button type="button" class="m-book-btn m-book-btn-primary" onclick="mBookSession(' + s.id + ')"><i class="fas fa-plus"></i> Book Session</button>';
                }
            }
        }
    }
    html += '</div>';
    document.getElementById('m-detail-body').innerHTML = html;
    document.getElementById('m-detail-overlay').classList.add('m-sheet-show');
    document.getElementById('m-detail-sheet').classList.add('m-sheet-show');
}

function mCloseDetail() {
    document.getElementById('m-detail-overlay').classList.remove('m-sheet-show');
    document.getElementById('m-detail-sheet').classList.remove('m-sheet-show');
}

function mEsc(str) {
    if (!str) return '';
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}

async function mCancelBooking(bookingId) {
    if (!await showConfirmModal('Cancel this booking?')) return;
    var form = new FormData();
    form.append('action', 'cancel_booking');
    form.append('booking_id', bookingId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to cancel booking', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

async function mCancelSession(sessionId) {
    if (!await showConfirmModal('Cancel this session for all attendees?')) return;
    var form = new FormData();
    form.append('action', 'cancel_session');
    form.append('session_id', sessionId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_edit_session.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to cancel session', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

function mBookSession(sessionId) {
    // Use form submission (not fetch) so the browser follows the Stripe checkout redirect
    document.getElementById('m-book-session-id').value = sessionId;
    document.getElementById('m-book-form').submit();
}

function mBookTemplateSession(sessionDateId) {
    // Use form submission for template sessions — handles Stripe redirect for paid,
    // or server-side redirect for free sessions
    document.getElementById('m-book-template-date-id').value = sessionDateId;
    document.getElementById('m-book-template-form').submit();
}

function mJoinWaitlist(sessionId) {
    var form = new FormData();
    form.append('action', 'join_waitlist');
    form.append('session_id', sessionId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                persistToast(data.message || 'Added to waitlist!', 'success');
                location.reload();
            } else {
                showToast(data.message || 'Failed to join waitlist', 'error');
            }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

async function mLeaveWaitlist(sessionId) {
    if (!await showConfirmModal('Leave the waitlist for this session?')) return;
    var form = new FormData();
    form.append('action', 'leave_waitlist');
    form.append('session_id', sessionId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to leave waitlist', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

function mJoinPackageWaitlist(packageId) {
    var form = new FormData();
    form.append('action', 'join_waitlist');
    form.append('package_id', packageId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Added to waitlist!', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to join waitlist', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

function mJoinTemplateWaitlist(templateId) {
    var form = new FormData();
    form.append('action', 'join_waitlist');
    form.append('template_id', templateId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Added to waitlist!', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to join waitlist', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

async function mOfferSpot(waitlistId) {
    if (!await showConfirmModal('Send enrollment email to this athlete? They will have 48 hours to purchase.')) return;
    var form = new FormData();
    form.append('action', 'offer_waitlist_spot');
    form.append('waitlist_id', waitlistId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Enrollment email sent!', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to offer spot', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}

async function mEnrollFromWaitlist(waitlistId) {
    if (!await showConfirmModal('Directly enroll this athlete? This bypasses the payment requirement.')) return;
    var form = new FormData();
    form.append('action', 'enroll_from_waitlist');
    form.append('waitlist_id', waitlistId);
    form.append('csrf_token', document.querySelector('#m-book-form input[name="csrf_token"]').value);
    fetch('process_booking.php', { method: 'POST', body: form })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { persistToast(data.message || 'Athlete enrolled!', 'success'); location.reload(); }
            else { showToast(data.message || 'Failed to enroll athlete', 'error'); }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); });
}
</script>
