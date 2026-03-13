<?php
/**
 * PWA Coach Calendar - Mobile-native session calendar for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

// Filter params
$filterLocation = $_GET['filter_location'] ?? '';
$filterCoach = $_GET['filter_coach'] ?? '';
$filterRange = $_GET['filter_range'] ?? 'all';

// Fetch distinct locations for filter dropdown
$filterLocations = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT arena FROM sessions WHERE arena IS NOT NULL AND arena != '' ORDER BY arena");
    $filterLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Fetch coaches for filter dropdown
$filterCoaches = [];
try {
    $stmt = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role IN ('coach', 'coach_plus', 'admin', 'team_coach', 'health_coach') AND is_active = 1 ORDER BY last_name, first_name");
    $filterCoaches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $filterCoaches = decryptUserRows($filterCoaches);
    }
} catch (PDOException $e) {}

// Build query conditions
$filterWhere = '';
$filterParams = [$user_id];
if ($filterLocation !== '') {
    $filterWhere .= ' AND s.arena = ?';
    $filterParams[] = $filterLocation;
}
if ($filterCoach !== '') {
    $filterWhere .= ' AND s.coach_id = ?';
    $filterParams[] = $filterCoach;
}
$dateCondition = 's.session_date >= CURDATE()';
if ($filterRange === 'week') {
    $dateCondition = 's.session_date >= CURDATE() AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)';
} elseif ($filterRange === 'month') {
    $dateCondition = 's.session_date >= CURDATE() AND s.session_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)';
}

$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
               s.status, s.arena, s.session_type,
               pp.name as practice_plan_name, pp.id as practice_plan_id,
               se.id as evaluation_id, se.name as evaluation_name, se.status as evaluation_status,
               (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = 'confirmed') as athlete_count
        FROM sessions s
        LEFT JOIN session_practice_plans spp ON spp.session_id = s.id
        LEFT JOIN practice_plans pp ON spp.practice_plan_id = pp.id
        LEFT JOIN session_evaluations se ON se.session_id = s.id
        WHERE s.coach_id = ? AND $dateCondition $filterWhere
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 50
    ");
    $stmt->execute($filterParams);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessions = []; }

// Fetch practice plans for the Assign Plan bottom sheet
$calPracticePlans = [];
try {
    $calPracticePlans = $pdo->query("SELECT id, COALESCE(title, name) as name FROM practice_plans ORDER BY created_at DESC LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch evaluation templates for the Start Evaluation bottom sheet
$calEvalTemplates = [];
try {
    $et_stmt = $pdo->query("
        SELECT et.id, et.title,
               GROUP_CONCAT(ec.name ORDER BY etc2.display_order SEPARATOR ', ') as category_names
        FROM evaluation_templates et
        LEFT JOIN evaluation_template_categories etc2 ON et.id = etc2.template_id
        LEFT JOIN eval_categories ec ON etc2.category_id = ec.id
        GROUP BY et.id
        ORDER BY et.title
    ");
    $calEvalTemplates = $et_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// Fetch athletes assigned to this coach
$calAssignedAthletes = [];
try {
    $aa_stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name FROM users u WHERE u.is_active = 1 AND u.role = 'athlete' AND (u.assigned_coach_id = ? OR u.created_by_coach_id = ?) ORDER BY u.last_name, u.first_name");
    $aa_stmt->execute([$user_id, $user_id]);
    $calAssignedAthletes = $aa_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $calAssignedAthletes = decryptUserRows($calAssignedAthletes);
    }
} catch (PDOException $e) {}

// Fetch template sessions (training_session_dates not yet linked to actual sessions)
try {
    $tplQuery = "
        SELECT tsd.id,
               tsd.session_date,
               TIME(tsd.session_date) as session_time,
               tst.name as title,
               tst.duration_minutes,
               tst.coach_id,
               'scheduled' as status,
               NULL as arena,
               NULL as session_type,
               0 as athlete_count,
               1 as is_template_session
        FROM training_session_dates tsd
        INNER JOIN training_session_templates tst ON tsd.template_id = tst.id
        WHERE tsd.session_date >= CURDATE()
          AND tsd.is_active = 1
          AND tst.is_active = 1
          AND tsd.session_id IS NULL
    ";
    $tplParams = [];
    if ($filterCoach !== '') {
        $tplQuery .= " AND tst.coach_id = ?";
        $tplParams[] = $filterCoach;
    }
    $tplQuery .= " ORDER BY tsd.session_date LIMIT 50";
    $tplStmt = $pdo->prepare($tplQuery);
    $tplStmt->execute($tplParams);
    $templateSessions = $tplStmt->fetchAll(PDO::FETCH_ASSOC);

    $sessions = array_merge($sessions, $templateSessions);
    usort($sessions, function ($a, $b) {
        return strtotime($a['session_date']) - strtotime($b['session_date']);
    });
} catch (PDOException $e) {
    // training_session_templates/dates tables may not exist yet
}

// Fetch development appointments for this coach
try {
    $devApptQuery = "
        SELECT da.id, da.title, da.appointment_date as session_date,
               da.appointment_time as session_time, da.duration_minutes,
               da.appointment_type, da.location as arena, da.status,
               da.athlete_id,
               u.first_name as athlete_first, u.last_name as athlete_last,
               dpe.program_type,
               0 as athlete_count,
               1 as is_dev_appointment
        FROM development_appointments da
        JOIN users u ON da.athlete_id = u.id
        JOIN development_program_enrollments dpe ON da.enrollment_id = dpe.id
        WHERE da.coach_id = ? AND da.status = 'scheduled'
          AND da.appointment_date >= CURDATE()
        ORDER BY da.appointment_date, da.appointment_time
        LIMIT 20
    ";
    $devApptStmt = $pdo->prepare($devApptQuery);
    $devApptStmt->execute([$user_id]);
    $devAppointments = $devApptStmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows') && !empty($devAppointments)) {
        $devAppointments = decryptUserRows($devAppointments);
    }
    $sessions = array_merge($sessions, $devAppointments);
    usort($sessions, function ($a, $b) {
        return strtotime($a['session_date']) - strtotime($b['session_date']);
    });
} catch (PDOException $e) {
    // development_appointments table may not exist yet
}

// Group sessions by date
$grouped = [];
foreach ($sessions as $s) {
    $dateKey = $s['session_date'];
    $grouped[$dateKey][] = $s;
}
?>
<style>
.m-calendar { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-calendar-header { margin-bottom: 12px; }
.m-calendar-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-calendar-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cal-filter-bar {
    display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 16px;
}
.m-cal-filter-select {
    flex: 1; min-width: 120px; padding: 10px 12px; border-radius: 8px;
    background: #16161F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 13px; font-family: Inter, sans-serif; min-height: 44px;
    appearance: none; -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-cal-filter-select option { background: #16161F; color: #fff; }
.m-cal-range-group {
    display: flex; border-radius: 8px; overflow: hidden; border: 1px solid #2D2D3F; width: 100%;
}
.m-cal-range-btn {
    flex: 1; padding: 9px 0; font-size: 12px; font-weight: 600;
    background: #16161F; color: #A8A8B8; border: none; cursor: pointer;
    font-family: Inter, sans-serif; min-height: 38px;
    border-right: 1px solid #2D2D3F; transition: background 0.15s, color 0.15s;
}
.m-cal-range-btn:last-child { border-right: none; }
.m-cal-range-btn.m-range-active { background: #6B46C1; color: #fff; }
.m-date-group { margin-bottom: 20px; }
.m-date-label {
    font-size: 13px; font-weight: 600; color: #8B5CF6;
    margin: 0 0 10px; padding: 0 4px;
    display: flex; align-items: center; gap: 6px;
}
.m-date-label-today {
    font-size: 10px; background: rgba(107,70,193,0.2); color: #8B5CF6;
    padding: 2px 8px; border-radius: 6px; font-weight: 600;
}
.m-cal-card {
    display: block; background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px; text-decoration: none;
}
.m-cal-card-top {
    display: flex; align-items: center; gap: 12px; min-height: 44px;
}
.m-cal-time {
    min-width: 52px; text-align: center;
    background: rgba(107,70,193,0.1); border-radius: 10px;
    padding: 8px 6px;
}
.m-cal-time-value { font-size: 13px; font-weight: 700; color: #fff; display: block; }
.m-cal-time-period { font-size: 10px; color: #A8A8B8; display: block; }
.m-cal-info { flex: 1; min-width: 0; }
.m-cal-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-cal-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.m-cal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-cal-badge-scheduled { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-cal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-cal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-cal-actions {
    display: flex; gap: 8px; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #2D2D3F;
}
.m-cal-act-btn {
    flex: 1; display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 10px; border-radius: 8px; font-size: 12px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
    min-height: 36px; transition: opacity 0.15s;
}
.m-cal-act-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.m-cal-act-complete { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cal-act-complete:active:not(:disabled) { background: rgba(16,185,129,0.25); }
.m-cal-act-cancel { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-cal-act-cancel:active:not(:disabled) { background: rgba(239,68,68,0.25); }
.m-cal-act-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-cal-act-edit:active:not(:disabled) { background: rgba(107,70,193,0.25); }
.m-cal-tpl-badge {
    font-size: 9px; padding: 2px 6px; border-radius: 4px; font-weight: 700;
    background: rgba(251,191,36,0.15); color: #FBBF24; margin-left: 4px; vertical-align: middle;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-cal-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; cursor: pointer; text-decoration: none;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    transition: background 0.2s;
}
.m-cal-fab:active { background: #8B5CF6; }
/* Session action bar (Assign Plan, Evaluate, Record) */
.m-cal-coach-actions {
    display: flex; gap: 6px; margin-top: 8px; padding-top: 8px;
    border-top: 1px solid #2D2D3F; flex-wrap: wrap;
}
.m-cal-coach-btn {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 7px 10px; border-radius: 8px; font-size: 11px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif;
    min-height: 34px; text-decoration: none; color: #8B5CF6;
    background: rgba(107,70,193,0.1);
    -webkit-tap-highlight-color: transparent;
}
.m-cal-coach-btn:active { background: rgba(107,70,193,0.2); }
.m-cal-plan-tag {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 11px; padding: 2px 8px; border-radius: 5px; font-weight: 500; margin-top: 4px;
}
.m-cal-plan-tag.has-plan { background: rgba(16,185,129,0.12); color: #10B981; }
.m-cal-plan-tag.no-plan { background: rgba(251,191,36,0.12); color: #FBBF24; }
.m-cal-eval-tag { background: rgba(59,130,246,0.12); color: #3B82F6; }
/* Bottom sheet modal */
.m-cal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 1001; -webkit-tap-highlight-color: transparent; }
.m-cal-overlay.m-active { display: block; }
.m-cal-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1002;
    background: #1A1A2E; border-radius: 16px 16px 0 0;
    padding: 20px 16px 32px; padding-bottom: calc(32px + env(safe-area-inset-bottom, 0px));
    transform: translateY(100%); transition: transform .3s ease;
    max-height: 80vh; overflow-y: auto;
}
.m-cal-overlay.m-active .m-cal-sheet { transform: translateY(0); }
.m-cal-sheet-handle { width: 36px; height: 4px; background: #3D3D4F; border-radius: 2px; margin: 0 auto 16px; }
.m-cal-sheet-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px; text-align: center; }
.m-cal-sheet-field { margin-bottom: 14px; }
.m-cal-sheet-field label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-cal-sheet-field select, .m-cal-sheet-field input, .m-cal-sheet-field textarea {
    width: 100%; padding: 12px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; min-height: 44px;
}
.m-cal-sheet-field select { appearance: none; -webkit-appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6B7B' d='M6 8L1 3h10z'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 12px center; }
.m-cal-athlete-grid { display: flex; flex-direction: column; gap: 6px; max-height: 200px; overflow-y: auto; }
.m-cal-athlete-check {
    display: flex; align-items: center; gap: 10px; padding: 10px 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    font-size: 14px; color: #fff; cursor: pointer; min-height: 44px;
}
.m-cal-athlete-check input[type="checkbox"] { width: 20px; height: 20px; accent-color: #6B46C1; flex-shrink: 0; }
.m-cal-sheet-actions { display: flex; gap: 10px; margin-top: 16px; }
.m-cal-sheet-actions button { flex: 1; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; border: none; font-family: Inter, sans-serif; min-height: 48px; }
.m-cal-sheet-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-cal-sheet-save { background: #6B46C1; color: #fff; }
.m-cal-sheet-save:disabled { opacity: 0.5; }
</style>

<div class="m-calendar">
    <div class="m-calendar-header">
        <h2 class="m-calendar-title">My Schedule</h2>
        <p class="m-calendar-sub"><?= count($sessions) ?> upcoming session<?= count($sessions) !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Filters -->
    <form method="GET" class="m-cal-filter-bar" id="m-cal-filter-form">
        <input type="hidden" name="page" value="coach_calendar">
        <input type="hidden" name="filter_range" id="m-cal-range-input" value="<?= htmlspecialchars($filterRange) ?>">
        <select name="filter_coach" class="m-cal-filter-select" onchange="this.form.submit()">
            <option value="">All Coaches</option>
            <?php foreach ($filterCoaches as $fc): ?>
            <option value="<?= (int)$fc['id'] ?>"<?= $filterCoach == $fc['id'] ? ' selected' : '' ?>><?= htmlspecialchars($fc['first_name'] . ' ' . $fc['last_name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="filter_location" class="m-cal-filter-select" onchange="this.form.submit()">
            <option value="">All Locations</option>
            <?php foreach ($filterLocations as $loc): ?>
            <option value="<?= htmlspecialchars($loc) ?>"<?= $filterLocation === $loc ? ' selected' : '' ?>><?= htmlspecialchars($loc) ?></option>
            <?php endforeach; ?>
        </select>
        <div class="m-cal-range-group">
            <button type="button" class="m-cal-range-btn<?= $filterRange === 'week' ? ' m-range-active' : '' ?>" onclick="mCalSetRange('week')">This Week</button>
            <button type="button" class="m-cal-range-btn<?= $filterRange === 'month' ? ' m-range-active' : '' ?>" onclick="mCalSetRange('month')">This Month</button>
            <button type="button" class="m-cal-range-btn<?= $filterRange === 'all' ? ' m-range-active' : '' ?>" onclick="mCalSetRange('all')">All</button>
        </div>
    </form>

    <?php if (empty($sessions)): ?>
        <div class="m-empty-state">
            <i class="fas fa-calendar-xmark"></i>
            <p>No upcoming sessions</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $dateKey => $daySessions):
            $dateTs = strtotime($dateKey);
            $isToday = ($dateKey === date('Y-m-d'));
            $dateLabel = $isToday ? 'Today' : date('l, M j', $dateTs);
        ?>
        <div class="m-date-group">
            <h3 class="m-date-label">
                <?= htmlspecialchars($dateLabel) ?>
                <?php if ($isToday): ?><span class="m-date-label-today">TODAY</span><?php endif; ?>
            </h3>
            <?php foreach ($daySessions as $sess):
                $sTime = $sess['session_time'] ? date('g:i', strtotime($sess['session_time'])) : '--';
                $sPeriod = $sess['session_time'] ? date('A', strtotime($sess['session_time'])) : '';
                $status = strtolower($sess['status'] ?? 'scheduled');
                $badgeClass = match($status) {
                    'completed' => 'completed',
                    'cancelled' => 'cancelled',
                    'scheduled' => 'scheduled',
                    default => 'default',
                };
            ?>
            <div class="m-cal-card" id="m-cal-card-<?= (int)$sess['id'] ?>">
                <?php if (!empty($sess['is_dev_appointment'])): ?>
                <div class="m-cal-card-top" style="border-left:3px solid <?= ($sess['appointment_type'] ?? '') === 'call' ? '#10b981' : (($sess['appointment_type'] ?? '') === 'video_call' ? '#3b82f6' : '#f59e0b') ?>;">
                    <div class="m-cal-time">
                        <span class="m-cal-time-value"><?= $sTime ?></span>
                        <span class="m-cal-time-period"><?= $sPeriod ?></span>
                    </div>
                    <div class="m-cal-info">
                        <div class="m-cal-title">
                            <?= htmlspecialchars($sess['title']) ?>
                            <span class="m-cal-tpl-badge" style="background:rgba(107,70,193,0.2);color:#a855f7;">DEV</span>
                        </div>
                        <div class="m-cal-meta">
                            <span><i class="fas fa-<?= ($sess['appointment_type'] ?? '') === 'call' ? 'phone' : (($sess['appointment_type'] ?? '') === 'video_call' ? 'video' : 'map-marker-alt') ?>"></i> <?= str_replace('_', ' ', ucfirst($sess['appointment_type'] ?? '')) ?></span>
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars(trim(($sess['athlete_first'] ?? '') . ' ' . ($sess['athlete_last'] ?? ''))) ?></span>
                            <?php if ($sess['arena']): ?><span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($sess['arena']) ?></span><?php endif; ?>
                            <?php if ($sess['duration_minutes']): ?><span><?= (int)$sess['duration_minutes'] ?>min</span><?php endif; ?>
                        </div>
                    </div>
                    <span class="m-cal-badge m-cal-badge-scheduled"><?= $sess['program_type'] === 'goalie_dev' ? 'Goalie' : 'Player' ?></span>
                </div>
                <?php else: ?>
                <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-cal-card-top" style="text-decoration:none;">
                    <div class="m-cal-time">
                        <span class="m-cal-time-value"><?= $sTime ?></span>
                        <span class="m-cal-time-period"><?= $sPeriod ?></span>
                    </div>
                    <div class="m-cal-info">
                        <div class="m-cal-title">
                            <?= htmlspecialchars($sess['title']) ?>
                            <?php if (!empty($sess['is_template_session'])): ?><span class="m-cal-tpl-badge">TEMPLATE</span><?php endif; ?>
                        </div>
                        <div class="m-cal-meta">
                            <span><i class="fas fa-users"></i> <?= (int)$sess['athlete_count'] ?></span>
                            <?php if ($sess['arena']): ?><span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($sess['arena']) ?></span><?php endif; ?>
                            <?php if ($sess['duration_minutes']): ?><span><?= (int)$sess['duration_minutes'] ?>min</span><?php endif; ?>
                        </div>
                    </div>
                    <span class="m-cal-badge m-cal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </a>
                <?php endif; ?>
                <?php if (empty($sess['is_template_session']) && empty($sess['is_dev_appointment'])): ?>
                <!-- Plan & Evaluation tags -->
                <div style="display:flex;gap:6px;flex-wrap:wrap;margin-top:6px;padding:0 2px;">
                    <?php if (!empty($sess['practice_plan_name'])): ?>
                    <span class="m-cal-plan-tag has-plan"><i class="fas fa-clipboard-list"></i> <?= htmlspecialchars($sess['practice_plan_name']) ?></span>
                    <?php else: ?>
                    <span class="m-cal-plan-tag no-plan"><i class="fas fa-exclamation-circle"></i> No Plan</span>
                    <?php endif; ?>
                    <?php if (!empty($sess['evaluation_id'])): ?>
                    <span class="m-cal-plan-tag m-cal-eval-tag"><i class="fas fa-clipboard-check"></i> <?= htmlspecialchars($sess['evaluation_name'] ?: 'Evaluation') ?> (<?= ucfirst($sess['evaluation_status'] ?? 'draft') ?>)</span>
                    <?php endif; ?>
                </div>
                <!-- Coach action buttons -->
                <div class="m-cal-coach-actions">
                    <button type="button" class="m-cal-coach-btn" onclick="mCalOpenAssignPlan(<?= (int)$sess['id'] ?>, <?= htmlspecialchars(json_encode($sess['practice_plan_id'] ?? ''), ENT_QUOTES) ?>)">
                        <i class="fas fa-clipboard-list"></i> <?= !empty($sess['practice_plan_name']) ? 'Change Plan' : 'Add Plan' ?>
                    </button>
                    <?php if (!empty($sess['evaluation_id'])): ?>
                    <a href="?page=session_evaluation_form&evaluation_id=<?= (int)$sess['evaluation_id'] ?>" class="m-cal-coach-btn" style="text-decoration:none;">
                        <i class="fas fa-clipboard-check"></i> Continue Eval
                    </a>
                    <?php else: ?>
                    <button type="button" class="m-cal-coach-btn" onclick="mCalOpenStartEval(<?= (int)$sess['id'] ?>)">
                        <i class="fas fa-clipboard-check"></i> Evaluate
                    </button>
                    <?php endif; ?>
                    <a href="?page=record_drill_video&session_id=<?= (int)$sess['id'] ?>" class="m-cal-coach-btn" style="text-decoration:none;">
                        <i class="fas fa-video"></i> Record
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($status === 'scheduled' && empty($sess['is_template_session']) && empty($sess['is_dev_appointment'])): ?>
                <div class="m-cal-actions">
                    <a href="?page=create_session&edit_id=<?= (int)$sess['id'] ?>" class="m-cal-act-btn m-cal-act-edit" style="text-decoration:none;">
                        <i class="fas fa-pen"></i> Edit
                    </a>
                    <button type="button" class="m-cal-act-btn m-cal-act-complete" onclick="mCalComplete(<?= (int)$sess['id'] ?>, this)">
                        <i class="fas fa-check-circle"></i> Complete
                    </button>
                    <button type="button" class="m-cal-act-btn m-cal-act-cancel" onclick="mCalCancel(<?= (int)$sess['id'] ?>, this)">
                        <i class="fas fa-xmark"></i> Cancel
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Create Session FAB -->
    <a href="?page=create_session" class="m-cal-fab" title="Create Session"><i class="fas fa-plus"></i></a>
</div>

<!-- Assign Practice Plan Bottom Sheet -->
<div class="m-cal-overlay" id="mCalAssignPlanOverlay" onclick="mCalClosePlanSheet()">
    <div class="m-cal-sheet" onclick="event.stopPropagation()">
        <div class="m-cal-sheet-handle"></div>
        <h3 class="m-cal-sheet-title">Assign Practice Plan</h3>
        <form id="mCalAssignPlanForm" onsubmit="return mCalSubmitAssignPlan(event)">
            <input type="hidden" name="action" value="assign_practice_plan">
            <input type="hidden" name="session_id" id="mCalPlanSessionId" value="">
            <div class="m-cal-sheet-field">
                <label>Practice Plan</label>
                <select name="practice_plan_id" id="mCalPlanSelect" required>
                    <option value="">— Select Plan —</option>
                    <?php foreach ($calPracticePlans as $plan): ?>
                    <option value="<?= (int)$plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-cal-sheet-actions">
                <button type="button" class="m-cal-sheet-cancel" onclick="mCalClosePlanSheet()">Cancel</button>
                <button type="submit" class="m-cal-sheet-save" id="mCalPlanSaveBtn">Assign Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Start Evaluation Bottom Sheet -->
<div class="m-cal-overlay" id="mCalStartEvalOverlay" onclick="mCalCloseEvalSheet()">
    <div class="m-cal-sheet" onclick="event.stopPropagation()">
        <div class="m-cal-sheet-handle"></div>
        <h3 class="m-cal-sheet-title">Start Evaluation</h3>
        <input type="hidden" id="mCalEvalSessionId" value="">
        <div class="m-cal-sheet-field">
            <label>Evaluation Template</label>
            <select id="mCalEvalTemplateSelect">
                <option value="">— No Template (all categories) —</option>
                <?php foreach ($calEvalTemplates as $tpl): ?>
                <option value="<?= (int)$tpl['id'] ?>"><?= htmlspecialchars($tpl['title']) ?><?= !empty($tpl['category_names']) ? ' (' . htmlspecialchars($tpl['category_names']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-cal-sheet-field">
            <label>Evaluation Name (optional)</label>
            <input type="text" id="mCalEvalName" placeholder="Auto-generated if blank">
        </div>
        <?php if (!empty($calAssignedAthletes)): ?>
        <div class="m-cal-sheet-field">
            <label>Select Athletes</label>
            <div class="m-cal-athlete-grid" id="mCalEvalAthleteGrid">
                <?php foreach ($calAssignedAthletes as $ath): ?>
                <label class="m-cal-athlete-check">
                    <input type="checkbox" name="eval_athlete_ids[]" value="<?= (int)$ath['id'] ?>">
                    <?= htmlspecialchars(($ath['first_name'] ?? '') . ' ' . ($ath['last_name'] ?? '')) ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div class="m-cal-sheet-actions">
            <button type="button" class="m-cal-sheet-cancel" onclick="mCalCloseEvalSheet()">Cancel</button>
            <button type="button" class="m-cal-sheet-save" id="mCalEvalStartBtn" onclick="mCalSubmitStartEval()">Start Evaluation</button>
        </div>
    </div>
</div>

<!-- Hidden CSRF token source -->
<form id="m-cal-csrf-form" style="display:none;">
    <?= csrfTokenInput() ?>
</form>

<script>
function mCalSetRange(range) {
    document.getElementById('m-cal-range-input').value = range;
    document.getElementById('m-cal-filter-form').submit();
}

async function mCalComplete(sessionId, btn) {
    if (!await showConfirmModal('Mark this session as completed?')) return;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'update_status');
    form.append('session_id', sessionId);
    form.append('status', 'completed');
    form.append('csrf_token', document.querySelector('#m-cal-csrf-form input[name="csrf_token"]').value);
    fetch('process_edit_session.php', { method: 'POST', body: form })
        .then(function(r) { return r.text(); })
        .then(function() {
            var card = document.getElementById('m-cal-card-' + sessionId);
            if (card) {
                var badge = card.querySelector('.m-cal-badge');
                if (badge) { badge.className = 'm-cal-badge m-cal-badge-completed'; badge.textContent = 'Completed'; }
                var actions = card.querySelector('.m-cal-actions');
                if (actions) actions.remove();
            }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); btn.disabled = false; });
}

async function mCalCancel(sessionId, btn) {
    if (!await showConfirmModal('Cancel this session? This cannot be undone.')) return;
    btn.disabled = true;
    var form = new FormData();
    form.append('action', 'cancel_session');
    form.append('session_id', sessionId);
    form.append('csrf_token', document.querySelector('#m-cal-csrf-form input[name="csrf_token"]').value);
    fetch('process_edit_session.php', { method: 'POST', body: form })
        .then(function(r) { return r.text(); })
        .then(function() {
            var card = document.getElementById('m-cal-card-' + sessionId);
            if (card) {
                var badge = card.querySelector('.m-cal-badge');
                if (badge) { badge.className = 'm-cal-badge m-cal-badge-cancelled'; badge.textContent = 'Cancelled'; }
                var actions = card.querySelector('.m-cal-actions');
                if (actions) actions.remove();
            }
        })
        .catch(function() { showToast('Network error. Please try again.', 'error'); btn.disabled = false; });
}

/* ── Assign Practice Plan ── */
function mCalGetCsrf() {
    return document.querySelector('#m-cal-csrf-form input[name="csrf_token"]').value;
}

function mCalOpenAssignPlan(sessionId, currentPlanId) {
    document.getElementById('mCalPlanSessionId').value = sessionId;
    var sel = document.getElementById('mCalPlanSelect');
    if (currentPlanId) { sel.value = currentPlanId; } else { sel.selectedIndex = 0; }
    document.getElementById('mCalAssignPlanOverlay').classList.add('m-active');
}

function mCalClosePlanSheet() {
    document.getElementById('mCalAssignPlanOverlay').classList.remove('m-active');
}

function mCalSubmitAssignPlan(e) {
    e.preventDefault();
    var btn = document.getElementById('mCalPlanSaveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    var form = new FormData();
    form.append('action', 'assign_practice_plan');
    form.append('session_id', document.getElementById('mCalPlanSessionId').value);
    form.append('practice_plan_id', document.getElementById('mCalPlanSelect').value);
    form.append('csrf_token', mCalGetCsrf());
    fetch('process_edit_session.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: form
    })
    .then(function(r) { return r.text(); })
    .then(function() {
        mCalClosePlanSheet();
        location.reload();
    })
    .catch(function() {
        showToast('Failed to assign plan. Try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Assign Plan';
    });
    return false;
}

/* ── Start Evaluation ── */
function mCalOpenStartEval(sessionId) {
    document.getElementById('mCalEvalSessionId').value = sessionId;
    document.getElementById('mCalEvalName').value = '';
    var tplSel = document.getElementById('mCalEvalTemplateSelect');
    if (tplSel) tplSel.selectedIndex = 0;
    var grid = document.getElementById('mCalEvalAthleteGrid');
    if (grid) grid.querySelectorAll('input[type="checkbox"]').forEach(function(cb) { cb.checked = false; });
    document.getElementById('mCalStartEvalOverlay').classList.add('m-active');
}

function mCalCloseEvalSheet() {
    document.getElementById('mCalStartEvalOverlay').classList.remove('m-active');
}

function mCalSubmitStartEval() {
    var sessionId = document.getElementById('mCalEvalSessionId').value;
    if (!sessionId) return;
    var btn = document.getElementById('mCalEvalStartBtn');
    btn.disabled = true;
    btn.textContent = 'Starting...';
    var templateId = document.getElementById('mCalEvalTemplateSelect').value;
    var name = document.getElementById('mCalEvalName').value.trim();
    var athleteIds = [];
    var grid = document.getElementById('mCalEvalAthleteGrid');
    if (grid) grid.querySelectorAll('input[type="checkbox"]:checked').forEach(function(cb) { athleteIds.push(cb.value); });
    var form = new FormData();
    form.append('action', 'start_evaluation');
    form.append('session_id', sessionId);
    form.append('csrf_token', mCalGetCsrf());
    if (templateId) form.append('template_id', templateId);
    if (name) form.append('name', name);
    athleteIds.forEach(function(id) { form.append('athlete_ids[]', id); });
    fetch('process_session_evaluations.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: form
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success && data.evaluation_id) {
            window.location.href = '?page=session_evaluation_form&evaluation_id=' + data.evaluation_id;
        } else {
            showToast(data.message || 'Failed to start evaluation', 'error');
            btn.disabled = false;
            btn.textContent = 'Start Evaluation';
        }
    })
    .catch(function() {
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Start Evaluation';
    });
}
</script>
