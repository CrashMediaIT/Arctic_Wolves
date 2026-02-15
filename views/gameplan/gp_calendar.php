<?php
/**
 * Game Plan - Calendar & Schedule View (Coach Only)
 * Restyled with site-standard classes: card, btn, form-select, filter-box, page-tabs.
 */

if (!$isAnyCoach) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-lock" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Coach Access Required</h3><p style="color:var(--text-muted)">You need coach access to view the calendar.</p></div>';
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

// ── Load seasons ──────────────────────────────────────────────
$cal_seasons = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, is_active FROM seasons ORDER BY start_date DESC");
    $stmt->execute();
    $cal_seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Cal seasons: ' . $e->getMessage()); }

// ── Load locations ────────────────────────────────────────────
$cal_locations = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM locations ORDER BY name");
    $stmt->execute();
    $cal_locations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('Cal locations: ' . $e->getMessage()); }

// ── Load games ────────────────────────────────────────────────
$cal_games = [];
try {
    $q = "
        SELECT gs.id, gs.team_id, gs.opponent_team, gs.game_date, gs.game_type, gs.status,
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

$first_day_of_week = (int)date('w', strtotime($cal_start));
$days_in_month = (int)date('t', strtotime($cal_start));
?>

<!-- Page header -->
<div class="page-header">
    <h1><i class="fas fa-calendar"></i> Calendar &amp; Schedule</h1>
    <p>Game schedule, practices, and video review timeline</p>
</div>

<!-- View Toggle Tabs -->
<div class="page-tabs page-tabs-secondary" style="margin-bottom: 20px;">
    <a class="page-tab <?= $cal_view === 'calendar' ? 'active' : '' ?>" href="/gameplan.php?page=calendar&view=calendar&month=<?= $cal_month ?>&year=<?= $cal_year ?>&team_id=<?= $cal_team ?>">
        <i class="fas fa-calendar-days"></i> Calendar
    </a>
    <a class="page-tab <?= $cal_view === 'list' ? 'active' : '' ?>" href="/gameplan.php?page=calendar&view=list&month=<?= $cal_month ?>&year=<?= $cal_year ?>&team_id=<?= $cal_team ?>">
        <i class="fas fa-list"></i> List
    </a>
</div>

<!-- Team Filter -->
<div class="filter-box" style="margin-bottom: 20px;">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filters</div>
    <div class="filter-box-content">
        <div class="filter-row">
            <div class="filter-field">
                <label>Team</label>
                <select class="form-select" onchange="location.href='/gameplan.php?page=calendar&view=<?= $cal_view ?>&month=<?= $cal_month ?>&year=<?= $cal_year ?>&team_id='+this.value">
                    <option value="0">All Teams</option>
                    <?php foreach ($cal_teams as $tm): ?>
                    <option value="<?= (int)$tm['id'] ?>" <?= $cal_team === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field filter-actions">
                <button type="button" class="btn btn-secondary" id="gpAddEventBtn"><i class="fas fa-plus"></i> Add Event</button>
                <button type="button" class="btn btn-primary" id="gpImportBtn"><i class="fas fa-file-import"></i> Import</button>
            </div>
        </div>
    </div>
</div>

<!-- Month Navigation -->
<div style="display:flex;align-items:center;justify-content:center;gap:20px;margin-bottom:20px;">
    <a href="/gameplan.php?page=calendar&view=<?= $cal_view ?>&month=<?= $prev_month ?>&year=<?= $prev_year ?>&team_id=<?= $cal_team ?>" class="btn btn-secondary" style="height:36px;width:36px;padding:0;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-chevron-left"></i></a>
    <h3 style="margin:0;min-width:200px;text-align:center;"><?= date('F Y', strtotime($cal_start)) ?></h3>
    <a href="/gameplan.php?page=calendar&view=<?= $cal_view ?>&month=<?= $next_month ?>&year=<?= $next_year ?>&team_id=<?= $cal_team ?>" class="btn btn-secondary" style="height:36px;width:36px;padding:0;display:inline-flex;align-items:center;justify-content:center;"><i class="fas fa-chevron-right"></i></a>
</div>

<?php if ($cal_view === 'calendar'): ?>
<!-- ── Calendar Grid View ── -->
<div class="card">
    <div class="card-body" style="padding:0;overflow:hidden;">
        <div style="display:grid;grid-template-columns:repeat(7,1fr);border-bottom:1px solid var(--border);">
            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dn): ?>
            <span style="padding:12px;text-align:center;font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;"><?= $dn ?></span>
            <?php endforeach; ?>
        </div>
        <div style="display:grid;grid-template-columns:repeat(7,1fr);">
            <?php for ($i = 0; $i < $first_day_of_week; $i++): ?>
            <div style="min-height:100px;padding:8px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);background:rgba(0,0,0,.15);"></div>
            <?php endfor; ?>
            <?php for ($d = 1; $d <= $days_in_month; $d++): ?>
            <?php $today = ($d === (int)date('j') && $cal_month === (int)date('n') && $cal_year === (int)date('Y')); ?>
            <div style="min-height:100px;padding:8px;border-right:1px solid var(--border);border-bottom:1px solid var(--border);<?= $today ? 'background:rgba(107,70,193,.06);' : '' ?>">
                <span style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:4px;display:inline-block;<?= $today ? 'background:var(--primary);color:#fff;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;' : '' ?>"><?= $d ?></span>
                <?php if (!empty($games_by_day[$d])): ?>
                <?php foreach ($games_by_day[$d] as $ev):
                    $type = $ev['game_type'] ?? 'regular';
                    $colors = ['regular' => '#3B82F6', 'playoff' => '#F59E0B', 'tournament' => '#A855F7', 'exhibition' => '#10B981', 'practice' => '#6B46C1'];
                    $c = $colors[$type] ?? '#3B82F6';
                    $is_past = strtotime($ev['game_date']) < time();
                ?>
                <a href="#" class="gp-cal-game-link" data-game-id="<?= (int)$ev['id'] ?>" data-team-id="<?= (int)$ev['team_id'] ?>" data-opponent="<?= htmlspecialchars($ev['opponent_team']) ?>" data-is-past="<?= $is_past ? '1' : '0' ?>" style="display:block;padding:3px 6px;border-radius:4px;font-size:10px;margin-top:2px;background:<?= $c ?>1f;color:<?= $c ?>;text-decoration:none;cursor:pointer;transition:opacity .15s;" title="<?= htmlspecialchars($ev['opponent_team']) ?> – Click for options">
                    <span style="font-weight:700;"><?= date('g:ia', strtotime($ev['game_date'])) ?></span>
                    <span style="display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">vs <?= htmlspecialchars(substr($ev['opponent_team'], 0, 15)) ?></span>
                </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endfor; ?>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ── List View ── -->
<?php if (empty($cal_games)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="text-align:center;padding:40px;">
            <i class="fas fa-calendar-xmark" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
            <h3 style="color:var(--text-secondary);">No Games Scheduled</h3>
            <p style="color:var(--text-muted);">No games found for <?= date('F Y', strtotime($cal_start)) ?>.</p>
        </div>
    </div>
</div>
<?php else: ?>
<?php foreach ($cal_games as $game):
    $game_is_past = strtotime($game['game_date']) < time();
?>
<div class="card" style="margin-bottom:10px;">
    <div class="card-body" style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;gap:16px;flex-wrap:wrap;">
        <div>
            <div style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                <i class="fas fa-calendar" style="color:var(--primary-light);"></i>
                <?= date('D, M j – g:ia', strtotime($game['game_date'])) ?>
            </div>
            <div style="font-size:15px;font-weight:700;">
                <?= htmlspecialchars($game['team_name'] ?? 'Team') ?>
                <?php if ($game['status'] === 'completed' && $game['home_score'] !== null): ?>
                    <strong><?= (int)$game['home_score'] ?> – <?= (int)$game['away_score'] ?></strong>
                <?php else: ?>
                    vs
                <?php endif; ?>
                <?= htmlspecialchars($game['opponent_team']) ?>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
            <?php if (!empty($game['location_name'])): ?>
            <span style="font-size:12px;color:var(--text-muted);display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-location-dot" style="color:var(--primary-light);font-size:11px;"></i> <?= htmlspecialchars($game['location_name']) ?></span>
            <?php endif; ?>
            <span style="font-size:12px;color:var(--text-muted);display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-tag" style="color:var(--primary-light);font-size:11px;"></i> <?= htmlspecialchars(ucfirst($game['game_type'] ?? 'regular')) ?></span>
            <?php if ((int)$game['video_count'] > 0): ?>
            <span style="font-size:12px;color:var(--text-muted);display:inline-flex;align-items:center;gap:5px;"><i class="fas fa-video" style="color:var(--primary-light);font-size:11px;"></i> <?= (int)$game['video_count'] ?> videos</span>
            <?php endif; ?>
            <?php
                $status_colors = ['scheduled' => ['bg' => 'rgba(59,130,246,.1)', 'color' => '#3B82F6', 'border' => 'rgba(59,130,246,.2)'], 'completed' => ['bg' => 'rgba(16,185,129,.1)', 'color' => '#10B981', 'border' => 'rgba(16,185,129,.2)'], 'in_progress' => ['bg' => 'rgba(245,158,11,.1)', 'color' => '#F59E0B', 'border' => 'rgba(245,158,11,.2)'], 'cancelled' => ['bg' => 'rgba(239,68,68,.1)', 'color' => '#EF4444', 'border' => 'rgba(239,68,68,.2)']];
                $sc = $status_colors[$game['status']] ?? $status_colors['scheduled'];
            ?>
            <span style="display:inline-flex;align-items:center;padding:4px 10px;border-radius:16px;font-size:10px;font-weight:700;text-transform:uppercase;background:<?= $sc['bg'] ?>;color:<?= $sc['color'] ?>;border:1px solid <?= $sc['border'] ?>;"><?= htmlspecialchars(ucfirst($game['status'])) ?></span>
            <!-- Game action buttons -->
            <a href="/gameplan.php?page=lines&game_id=<?= (int)$game['id'] ?>&team_id=<?= (int)($game['team_id'] ?? 0) ?>" class="btn btn-secondary" style="height:30px;padding:0 12px;font-size:11px;display:inline-flex;align-items:center;gap:5px;" title="<?= $game_is_past ? 'View' : 'Set' ?> game lines"><i class="fas fa-users-line"></i> Lines</a>
            <a href="/gameplan.php?page=game_plan&tab=pre_game&game_id=<?= (int)$game['id'] ?>" class="btn btn-<?= $game_is_past ? 'secondary' : 'primary' ?>" style="height:30px;padding:0 12px;font-size:11px;display:inline-flex;align-items:center;gap:5px;" title="<?= $game_is_past ? 'View' : 'Create' ?> pre-game plan"><i class="fas fa-clipboard-list"></i> Pre-Game</a>
            <a href="/gameplan.php?page=game_plan&tab=post_game&game_id=<?= (int)$game['id'] ?>" class="btn btn-secondary" style="height:30px;padding:0 12px;font-size:11px;display:inline-flex;align-items:center;gap:5px;" title="<?= $game_is_past ? 'View' : 'Create' ?> post-game review"><i class="fas fa-chart-line"></i> Post-Game</a>
            <?php if ((int)$game['video_count'] > 0): ?>
            <a href="/gameplan.php?page=film_room&tab=clips&game_id=<?= (int)$game['id'] ?>" class="btn btn-secondary" style="height:30px;padding:0 12px;font-size:11px;display:inline-flex;align-items:center;gap:5px;" title="View game clips"><i class="fas fa-film"></i> Clips</a>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<!-- Import Calendar Modal -->
<div class="modal-overlay" id="gpImportModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:520px;">
        <div class="modal-header">
            <h3><i class="fas fa-file-import"></i> Import Calendar</h3>
            <button type="button" class="modal-close" id="gpCloseImport">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="/process_video.php" enctype="multipart/form-data">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="import_calendar">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Import Source</label>
                    <select name="import_type" class="form-select">
                        <option value="ical">iCal / ICS File</option>
                        <option value="csv">CSV File</option>
                        <option value="teamlinkt">TeamLinkt URL</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Team</label>
                    <select name="team_id" class="form-select">
                        <?php foreach ($cal_teams as $tm): ?>
                        <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Season</label>
                    <select name="season_id" class="form-select">
                        <option value="">No Season</option>
                        <?php foreach ($cal_seasons as $cs): ?>
                        <option value="<?= (int)$cs['id'] ?>" <?= $cs['is_active'] ? 'selected' : '' ?>><?= htmlspecialchars($cs['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">File or URL</label>
                    <input type="file" name="calendar_file" class="form-input" accept=".ics,.csv">
                    <input type="url" name="calendar_url" class="form-input" placeholder="https://teamlinkt.com/..." style="margin-top:8px;">
                </div>
                <div style="display:flex;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border);margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-file-import"></i> Import</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Event Modal -->
<div class="modal-overlay" id="gpAddEventModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:520px;max-height:90vh;overflow-y:auto;">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Game or Practice</h3>
            <button type="button" class="modal-close" id="gpCloseAddEvent">&times;</button>
        </div>
        <div class="modal-body">
            <form method="POST" action="/process_video.php">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="add_calendar_event">
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Event Type</label>
                    <select name="game_type" class="form-select" id="gpEventType">
                        <option value="regular">Regular Season Game</option>
                        <option value="playoff">Playoff Game</option>
                        <option value="tournament">Tournament Game</option>
                        <option value="exhibition">Exhibition / Scrimmage</option>
                        <option value="practice">Practice</option>
                    </select>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Team</label>
                    <select name="team_id" class="form-select">
                        <?php foreach ($cal_teams as $tm): ?>
                        <option value="<?= (int)$tm['id'] ?>" <?= $cal_team === (int)$tm['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tm['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom:16px;" id="gpOpponentField">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Opponent Team</label>
                    <input type="text" name="opponent_team" class="form-input" placeholder="e.g. Rockland Nats U9">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Date *</label>
                        <input type="date" name="game_date" class="form-input" required>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Time</label>
                        <input type="time" name="game_time" class="form-input">
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px;">
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Location</label>
                        <select name="location_id" class="form-select">
                            <option value="">No Location</option>
                            <?php foreach ($cal_locations as $loc): ?>
                            <option value="<?= (int)$loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="display:block;font-weight:600;margin-bottom:6px;">Season</label>
                        <select name="season_id" class="form-select">
                            <option value="">No Season</option>
                            <?php foreach ($cal_seasons as $cs): ?>
                            <option value="<?= (int)$cs['id'] ?>" <?= $cs['is_active'] ? 'selected' : '' ?>><?= htmlspecialchars($cs['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:flex;align-items:center;gap:8px;font-weight:600;cursor:pointer;">
                        <input type="checkbox" name="is_home_game" value="1" checked> Home Game
                    </label>
                </div>
                <div style="margin-bottom:16px;">
                    <label style="display:block;font-weight:600;margin-bottom:6px;">Notes</label>
                    <textarea name="notes" class="form-input" rows="2" style="resize:vertical;"></textarea>
                </div>
                <div style="display:flex;justify-content:flex-end;padding-top:16px;border-top:1px solid var(--border);margin-top:20px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('gpImportModal');
    document.getElementById('gpImportBtn').addEventListener('click', function() { modal.style.display = 'flex'; });
    document.getElementById('gpCloseImport').addEventListener('click', function() { modal.style.display = 'none'; });
    modal.addEventListener('click', function(e) { if (e.target === modal) modal.style.display = 'none'; });

    var addModal = document.getElementById('gpAddEventModal');
    document.getElementById('gpAddEventBtn').addEventListener('click', function() { addModal.style.display = 'flex'; });
    document.getElementById('gpCloseAddEvent').addEventListener('click', function() { addModal.style.display = 'none'; });
    addModal.addEventListener('click', function(e) { if (e.target === addModal) addModal.style.display = 'none'; });

    // Toggle opponent field based on event type
    document.getElementById('gpEventType').addEventListener('change', function() {
        var opponentField = document.getElementById('gpOpponentField');
        opponentField.style.display = this.value === 'practice' ? 'none' : 'block';
    });

    // Game options popup on calendar click
    var gameOptionsModal = document.getElementById('gpGameOptionsModal');
    document.querySelectorAll('.gp-cal-game-link').forEach(function(link) {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            var gameId = this.dataset.gameId;
            var teamId = this.dataset.teamId;
            var opponent = this.dataset.opponent;
            var isPast = this.dataset.isPast === '1';
            document.getElementById('gpGameOptionsTitle').textContent = (isPast ? 'View' : 'Plan') + ': vs ' + opponent;
            document.getElementById('gpGameOptLines').href = '/gameplan.php?page=lines&game_id=' + gameId + '&team_id=' + teamId;
            document.getElementById('gpGameOptPreGame').href = '/gameplan.php?page=game_plan&tab=pre_game&game_id=' + gameId;
            document.getElementById('gpGameOptPostGame').href = '/gameplan.php?page=game_plan&tab=post_game&game_id=' + gameId;
            document.getElementById('gpGameOptLinesLabel').textContent = isPast ? 'View Game Lines' : 'Set Game Lines';
            document.getElementById('gpGameOptPreGameLabel').textContent = isPast ? 'View Pre-Game Plan' : 'Create Pre-Game Plan';
            document.getElementById('gpGameOptPostGameLabel').textContent = isPast ? 'View Post-Game Review' : 'Create Post-Game Review';
            gameOptionsModal.style.display = 'flex';
        });
    });
    document.getElementById('gpCloseGameOptions').addEventListener('click', function() { gameOptionsModal.style.display = 'none'; });
    gameOptionsModal.addEventListener('click', function(e) { if (e.target === gameOptionsModal) gameOptionsModal.style.display = 'none'; });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            modal.style.display = 'none';
            addModal.style.display = 'none';
            gameOptionsModal.style.display = 'none';
        }
    });
});
</script>

<!-- Game Options Modal -->
<div class="modal-overlay" id="gpGameOptionsModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.65);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:400px;">
        <div class="modal-header">
            <h3 id="gpGameOptionsTitle"><i class="fas fa-hockey-puck"></i> Game Options</h3>
            <button type="button" class="modal-close" id="gpCloseGameOptions">&times;</button>
        </div>
        <div class="modal-body" style="padding:0;">
            <a href="#" id="gpGameOptLines" style="display:flex;align-items:center;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text-white);transition:background .15s;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(59,130,246,.1);color:#3B82F6;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;"><i class="fas fa-users-line"></i></div>
                <div>
                    <div style="font-weight:700;font-size:14px;" id="gpGameOptLinesLabel">Set Game Lines</div>
                    <div style="font-size:12px;color:var(--text-muted);">Modify lines for this specific game</div>
                </div>
                <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--text-muted);font-size:12px;"></i>
            </a>
            <a href="#" id="gpGameOptPreGame" style="display:flex;align-items:center;gap:14px;padding:16px 20px;border-bottom:1px solid var(--border);text-decoration:none;color:var(--text-white);transition:background .15s;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(107,70,193,.1);color:var(--primary-light);display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;"><i class="fas fa-clipboard-list"></i></div>
                <div>
                    <div style="font-weight:700;font-size:14px;" id="gpGameOptPreGameLabel">Create Pre-Game Plan</div>
                    <div style="font-size:12px;color:var(--text-muted);">Strategy, systems, and key players</div>
                </div>
                <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--text-muted);font-size:12px;"></i>
            </a>
            <a href="#" id="gpGameOptPostGame" style="display:flex;align-items:center;gap:14px;padding:16px 20px;text-decoration:none;color:var(--text-white);transition:background .15s;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(16,185,129,.1);color:#10B981;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;"><i class="fas fa-chart-line"></i></div>
                <div>
                    <div style="font-weight:700;font-size:14px;" id="gpGameOptPostGameLabel">Create Post-Game Review</div>
                    <div style="font-size:12px;color:var(--text-muted);">Score, notes, and performance review</div>
                </div>
                <i class="fas fa-chevron-right" style="margin-left:auto;color:var(--text-muted);font-size:12px;"></i>
            </a>
        </div>
    </div>
</div>
<style>
#gpGameOptionsModal a:hover { background: rgba(107,70,193,.06); }
</style>
