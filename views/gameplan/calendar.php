<?php
/**
 * Game Plan - Calendar & Schedule View (Coach Only)
 * Calendar/List view toggle with team filter. Import modal.
 */

if (!$isAnyCoach) {
    echo '<div class="gp-empty"><i class="fas fa-lock"></i><p>Coach access required to view the calendar.</p></div>';
    return;
}

// ── Parameters ────────────────────────────────────────────────
$cal_view   = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'calendar';
$cal_team   = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$cal_month  = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('n');
$cal_year   = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

if ($cal_month < 1) { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1; $cal_year++; }

$cal_start = sprintf('%04d-%02d-01', $cal_year, $cal_month);
$cal_end   = date('Y-m-t', strtotime($cal_start));

// ── Load teams ────────────────────────────────────────────────
$cal_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division FROM teams WHERE is_active = 1 AND is_managed = 1 ORDER BY name");
    $stmt->execute();
    $cal_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Cal teams: ' . $e->getMessage()); }

// ── Load games ────────────────────────────────────────────────
$cal_games = [];
try {
    $q = "
        SELECT gs.id, gs.opponent_team, gs.game_date, gs.game_type, gs.status,
               gs.home_score, gs.away_score, gs.is_home_game, gs.notes,
               t.name AS team_name, l.name AS location_name,
               (SELECT COUNT(*) FROM vr_video_sources vs WHERE vs.game_id = gs.id) AS video_count
        FROM game_schedules gs
        LEFT JOIN teams t ON gs.team_id = t.id
        LEFT JOIN locations l ON gs.location_id = l.id
        WHERE gs.game_date BETWEEN ? AND ?
    ";
    $params = [$cal_start . ' 00:00:00', $cal_end . ' 23:59:59'];

    if ($cal_team > 0) {
        $q .= " AND gs.team_id = ?";
        $params[] = $cal_team;
    }
    $q .= " ORDER BY gs.game_date ASC";

    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $cal_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Cal games: ' . $e->getMessage()); }

// Group games by day for calendar view
$games_by_day = [];
foreach ($cal_games as $g) {
    $day = (int)date('j', strtotime($g['game_date']));
    $games_by_day[$day][] = $g;
}

$prev_month = $cal_month - 1;
$prev_year  = $cal_year;
if ($prev_month < 1) { $prev_month = 12; $prev_year--; }
$next_month = $cal_month + 1;
$next_year  = $cal_year;
if ($next_month > 12) { $next_month = 1; $next_year++; }

$first_day_of_week = (int)date('w', strtotime($cal_start)); // 0=Sun
$days_in_month = (int)date('t', strtotime($cal_start));
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-calendar"></i> Calendar &amp; Schedule</h1>
    <p class="gp-page-desc">Game schedule, practices, and video review timeline</p>
</div>

<!-- Controls -->
<div class="vr-tabs-bar">
    <div class="vr-tabs">
        <a class="vr-tab <?= $cal_view === 'calendar' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=calendar&view=calendar&month=<?= $cal_month ?>&year=<?= $cal_year ?>&team_id=<?= $cal_team ?>">
            <i class="fas fa-calendar-days"></i> Calendar
        </a>
        <a class="vr-tab <?= $cal_view === 'list' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=calendar&view=list&month=<?= $cal_month ?>&year=<?= $cal_year ?>&team_id=<?= $cal_team ?>">
            <i class="fas fa-list"></i> List
        </a>
    </div>
    <div class="vr-filters" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <select class="vr-input vr-select" onchange="location.href='/gameplan.php?page=calendar&view=<?= $cal_view ?>&month=<?= $cal_month ?>&year=<?= $cal_year ?>&team_id='+this.value">
            <option value="0">All Teams</option>
            <?php foreach ($cal_teams as $tm): ?>
            <option value="<?= (int)$tm['id'] ?>" <?= $cal_team === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <button type="button" class="vr-btn-primary" id="vrImportBtn"><i class="fas fa-file-import"></i> Import</button>
    </div>
</div>

<?php if ($cal_view === 'calendar'): ?>
<!-- ── Calendar View ── -->
<div class="vr-calendar-nav">
    <a href="/gameplan.php?page=calendar&view=calendar&month=<?= $prev_month ?>&year=<?= $prev_year ?>&team_id=<?= $cal_team ?>" class="vr-cal-arrow"><i class="fas fa-chevron-left"></i></a>
    <h3 class="vr-cal-month"><?= date('F Y', strtotime($cal_start)) ?></h3>
    <a href="/gameplan.php?page=calendar&view=calendar&month=<?= $next_month ?>&year=<?= $next_year ?>&team_id=<?= $cal_team ?>" class="vr-cal-arrow"><i class="fas fa-chevron-right"></i></a>
</div>

<div class="vr-calendar">
    <div class="vr-cal-header"><span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span><span>Thu</span><span>Fri</span><span>Sat</span></div>
    <div class="vr-cal-grid">
        <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
        <div class="vr-cal-day vr-cal-empty"></div>
        <?php endfor; ?>
        <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
        <?php $today = ($d === (int)date('j') && $cal_month === (int)date('n') && $cal_year === (int)date('Y')); ?>
        <div class="vr-cal-day <?= $today ? 'vr-cal-today' : '' ?> <?= !empty($games_by_day[$d]) ? 'vr-cal-has-event' : '' ?>">
            <span class="vr-cal-num"><?= $d ?></span>
            <?php if (!empty($games_by_day[$d])): ?>
            <?php foreach ($games_by_day[$d] as $ev): ?>
            <div class="vr-cal-event vr-cal-evt-<?= htmlspecialchars($ev['game_type'] ?? 'regular') ?>">
                <span class="vr-cal-evt-time"><?= date('g:ia', strtotime($ev['game_date'])) ?></span>
                <span class="vr-cal-evt-text">vs <?= htmlspecialchars(substr($ev['opponent_team'], 0, 15)) ?></span>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<?php else: ?>
<!-- ── List View ── -->
<?php if (empty($cal_games)): ?>
<div class="gp-empty">
    <i class="fas fa-calendar-xmark"></i>
    <p>No games scheduled for <?= date('F Y', strtotime($cal_start)) ?>.</p>
</div>
<?php else: ?>
<div class="vr-calendar-nav">
    <a href="/gameplan.php?page=calendar&view=list&month=<?= $prev_month ?>&year=<?= $prev_year ?>&team_id=<?= $cal_team ?>" class="vr-cal-arrow"><i class="fas fa-chevron-left"></i></a>
    <h3 class="vr-cal-month"><?= date('F Y', strtotime($cal_start)) ?></h3>
    <a href="/gameplan.php?page=calendar&view=list&month=<?= $next_month ?>&year=<?= $next_year ?>&team_id=<?= $cal_team ?>" class="vr-cal-arrow"><i class="fas fa-chevron-right"></i></a>
</div>

<?php foreach ($cal_games as $game): ?>
<div class="vr-game-card">
    <div class="vr-game-header-list">
        <div class="vr-game-info">
            <span class="vr-game-date"><i class="fas fa-calendar"></i> <?= date('D, M j – g:ia', strtotime($game['game_date'])) ?></span>
            <span class="vr-game-matchup">
                <?= htmlspecialchars($game['team_name'] ?? 'Team') ?>
                <?php if ($game['status'] === 'completed' && $game['home_score'] !== null): ?>
                    <strong><?= (int)$game['home_score'] ?> – <?= (int)$game['away_score'] ?></strong>
                <?php else: ?>
                    vs
                <?php endif; ?>
                <?= htmlspecialchars($game['opponent_team']) ?>
            </span>
        </div>
        <div class="vr-game-details">
            <?php if (!empty($game['location_name'])): ?>
            <span class="vr-meta-item"><i class="fas fa-location-dot"></i> <?= htmlspecialchars($game['location_name']) ?></span>
            <?php endif; ?>
            <span class="vr-meta-item"><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($game['game_type'] ?? 'regular')) ?></span>
            <?php if ((int)$game['video_count'] > 0): ?>
            <span class="vr-meta-item"><i class="fas fa-video"></i> <?= (int)$game['video_count'] ?> videos</span>
            <?php endif; ?>
            <span class="vr-status-badge vr-badge-<?= htmlspecialchars($game['status']) ?>"><?= htmlspecialchars(ucfirst($game['status'])) ?></span>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<!-- Import Calendar Modal -->
<div class="vr-modal-overlay" id="vrImportModal">
    <div class="vr-modal-sheet">
        <div class="vr-modal-header">
            <span class="vr-modal-title">Import Calendar</span>
            <button type="button" class="vr-modal-close" id="vrCloseImport">&times;</button>
        </div>
        <form method="POST" action="/process_video.php" enctype="multipart/form-data">
            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="import_calendar">
            <div class="vr-form-group">
                <label>Import Source</label>
                <select name="import_type" class="vr-input">
                    <option value="ical">iCal / ICS File</option>
                    <option value="csv">CSV File</option>
                    <option value="teamlinkt">TeamLinkt URL</option>
                </select>
            </div>
            <div class="vr-form-group">
                <label>Team</label>
                <select name="team_id" class="vr-input">
                    <?php foreach ($cal_teams as $tm): ?>
                    <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vr-form-group">
                <label>File or URL</label>
                <input type="file" name="calendar_file" class="vr-input" accept=".ics,.csv">
                <input type="url" name="calendar_url" class="vr-input" placeholder="https://teamlinkt.com/..." style="margin-top:8px">
            </div>
            <div class="vr-form-actions">
                <button type="submit" class="vr-btn-primary"><i class="fas fa-file-import"></i> Import</button>
            </div>
        </form>
    </div>
</div>

<style>
.vr-tabs-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.vr-tabs { display: flex; gap: 4px; background: rgba(10,10,15,.6); padding: 5px; border-radius: 10px; border: 1px solid rgba(45,45,63,.5); }
.vr-tab { padding: 10px 18px; background: transparent; border: none; color: var(--gp-text-dim); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 7px; font-family: 'Inter', sans-serif; text-decoration: none; }
.vr-tab:hover { color: var(--gp-text); background: rgba(107,70,193,.12); }
.vr-tab.vr-tab-active { color: #fff; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); }
.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; width: 100%; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-select { min-width: 150px; width: auto; }
.vr-btn-primary { padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); border: none; color: #fff; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-family: 'Inter', sans-serif; transition: opacity .2s; }
.vr-btn-primary:hover { opacity: .9; }

.vr-calendar-nav { display: flex; align-items: center; justify-content: center; gap: 24px; margin-bottom: 20px; }
.vr-cal-month { font-size: 20px; font-weight: 700; color: var(--gp-text); margin: 0; min-width: 200px; text-align: center; }
.vr-cal-arrow { width: 36px; height: 36px; border-radius: 8px; border: 1px solid var(--gp-border); background: var(--gp-card); color: var(--gp-text-muted); display: flex; align-items: center; justify-content: center; text-decoration: none; transition: all .2s; }
.vr-cal-arrow:hover { border-color: var(--gp-primary-light); color: var(--gp-primary-light); }

.vr-calendar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; overflow: hidden; }
.vr-cal-header { display: grid; grid-template-columns: repeat(7, 1fr); border-bottom: 1px solid var(--gp-border); }
.vr-cal-header span { padding: 12px; text-align: center; font-size: 11px; font-weight: 700; color: var(--gp-text-dim); text-transform: uppercase; letter-spacing: .5px; }
.vr-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); }
.vr-cal-day { min-height: 100px; padding: 8px; border-right: 1px solid var(--gp-border); border-bottom: 1px solid var(--gp-border); position: relative; }
.vr-cal-day:nth-child(7n) { border-right: none; }
.vr-cal-empty { background: rgba(10,10,15,.3); }
.vr-cal-today { background: rgba(107,70,193,.06); }
.vr-cal-today .vr-cal-num { background: var(--gp-primary); color: #fff; border-radius: 50%; width: 24px; height: 24px; display: inline-flex; align-items: center; justify-content: center; }
.vr-cal-num { font-size: 12px; font-weight: 600; color: var(--gp-text-muted); margin-bottom: 4px; display: inline-block; }
.vr-cal-event { padding: 3px 6px; border-radius: 4px; font-size: 10px; margin-top: 2px; cursor: default; }
.vr-cal-evt-regular { background: rgba(59,130,246,.12); color: #3B82F6; }
.vr-cal-evt-playoff { background: rgba(245,158,11,.12); color: #F59E0B; }
.vr-cal-evt-tournament { background: rgba(168,85,247,.12); color: #A855F7; }
.vr-cal-evt-exhibition { background: rgba(16,185,129,.12); color: #10B981; }
.vr-cal-evt-practice { background: rgba(107,70,193,.12); color: var(--gp-primary-light); }
.vr-cal-evt-time { font-weight: 700; }
.vr-cal-evt-text { display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

.vr-game-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; margin-bottom: 10px; transition: border-color .2s; }
.vr-game-card:hover { border-color: rgba(107,70,193,.4); }
.vr-game-header-list { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; gap: 16px; }
.vr-game-info { display: flex; flex-direction: column; gap: 4px; }
.vr-game-date { font-size: 12px; color: var(--gp-text-muted); display: inline-flex; align-items: center; gap: 6px; }
.vr-game-date i { color: var(--gp-primary-light); }
.vr-game-matchup { font-size: 15px; font-weight: 700; color: var(--gp-text); }
.vr-game-details { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; flex-shrink: 0; }
.vr-meta-item { font-size: 12px; color: var(--gp-text-muted); display: inline-flex; align-items: center; gap: 5px; }
.vr-meta-item i { color: var(--gp-primary-light); font-size: 11px; }
.vr-status-badge { display: inline-flex; align-items: center; padding: 4px 10px; border-radius: 16px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
.vr-badge-scheduled { background: rgba(59,130,246,.1); color: #3B82F6; border: 1px solid rgba(59,130,246,.2); }
.vr-badge-completed { background: rgba(16,185,129,.1); color: #10B981; border: 1px solid rgba(16,185,129,.2); }
.vr-badge-in_progress { background: rgba(245,158,11,.1); color: #F59E0B; border: 1px solid rgba(245,158,11,.2); }
.vr-badge-cancelled { background: rgba(239,68,68,.1); color: #EF4444; border: 1px solid rgba(239,68,68,.2); }
.vr-badge-postponed { background: rgba(168,168,184,.1); color: var(--gp-text-muted); border: 1px solid rgba(168,168,184,.2); }

.vr-modal-overlay { display: none; position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,.65); align-items: center; justify-content: center; }
.vr-modal-overlay.vr-modal-open { display: flex; }
.vr-modal-sheet { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 16px; width: 90%; max-width: 520px; padding: 24px; animation: vrSlideIn .25s ease-out; }
@keyframes vrSlideIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.vr-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.vr-modal-title { font-size: 16px; font-weight: 700; color: var(--gp-text); }
.vr-modal-close { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--gp-border); background: transparent; color: var(--gp-text-muted); font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; font-family: 'Inter', sans-serif; }
.vr-modal-close:hover { background: var(--gp-primary); border-color: var(--gp-primary); color: #fff; }
.vr-form-group { margin-bottom: 18px; }
.vr-form-group label { display: block; font-size: 11px; font-weight: 600; color: var(--gp-text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .5px; }
.vr-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--gp-border); margin-top: 24px; }

@media (max-width: 768px) {
    .vr-tabs-bar { flex-direction: column; align-items: stretch; }
    .vr-cal-header span { font-size: 9px; padding: 8px 2px; }
    .vr-cal-day { min-height: 60px; padding: 4px; }
    .vr-cal-event { font-size: 8px; }
    .vr-game-header-list { flex-direction: column; align-items: flex-start; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('vrImportModal');
    document.getElementById('vrImportBtn').addEventListener('click', function() { modal.classList.add('vr-modal-open'); });
    document.getElementById('vrCloseImport').addEventListener('click', function() { modal.classList.remove('vr-modal-open'); });
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.classList.remove('vr-modal-open'); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') modal.classList.remove('vr-modal-open'); });
});
</script>
