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
$filterRange = $_GET['filter_range'] ?? 'all';

// Fetch distinct locations for filter dropdown
$filterLocations = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT arena FROM sessions WHERE arena IS NOT NULL AND arena != '' ORDER BY arena");
    $filterLocations = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {}

// Build query conditions
$filterWhere = '';
$filterParams = [$user_id];
if ($filterLocation !== '') {
    $filterWhere .= ' AND s.arena = ?';
    $filterParams[] = $filterLocation;
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
               (SELECT COUNT(*) FROM bookings b WHERE b.session_id = s.id AND b.status = 'confirmed') as athlete_count
        FROM sessions s
        WHERE s.coach_id = ? AND $dateCondition $filterWhere
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 50
    ");
    $stmt->execute($filterParams);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sessions = []; }

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
                <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-cal-card-top" style="text-decoration:none;">
                    <div class="m-cal-time">
                        <span class="m-cal-time-value"><?= $sTime ?></span>
                        <span class="m-cal-time-period"><?= $sPeriod ?></span>
                    </div>
                    <div class="m-cal-info">
                        <div class="m-cal-title"><?= htmlspecialchars($sess['title']) ?></div>
                        <div class="m-cal-meta">
                            <span><i class="fas fa-users"></i> <?= (int)$sess['athlete_count'] ?></span>
                            <?php if ($sess['arena']): ?><span><i class="fas fa-location-dot"></i> <?= htmlspecialchars($sess['arena']) ?></span><?php endif; ?>
                            <?php if ($sess['duration_minutes']): ?><span><?= (int)$sess['duration_minutes'] ?>min</span><?php endif; ?>
                        </div>
                    </div>
                    <span class="m-cal-badge m-cal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </a>
                <?php if ($status === 'scheduled'): ?>
                <div class="m-cal-actions">
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

<!-- Hidden CSRF token source -->
<form id="m-cal-csrf-form" style="display:none;">
    <?= csrfTokenInput() ?>
</form>

<script>
function mCalSetRange(range) {
    document.getElementById('m-cal-range-input').value = range;
    document.getElementById('m-cal-filter-form').submit();
}

function mCalComplete(sessionId, btn) {
    if (!confirm('Mark this session as completed?')) return;
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
        .catch(function() { alert('Network error. Please try again.'); btn.disabled = false; });
}

function mCalCancel(sessionId, btn) {
    if (!confirm('Cancel this session? This cannot be undone.')) return;
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
        .catch(function() { alert('Network error. Please try again.'); btn.disabled = false; });
}
</script>
