<?php
/**
 * Game Plan - Video Review View
 * Three tabs: Clips / By Game / Opponent Scouting
 * Athletes see clips they're tagged in; coaches see all clips.
 */

// ── Tab & Filter parameters ───────────────────────────────────
$vr_tab = isset($_GET['tab']) ? preg_replace('/[^a-z_]/', '', $_GET['tab']) : 'clips';
if (!in_array($vr_tab, ['clips', 'by_game', 'scouting'])) $vr_tab = 'clips';

$vr_tag_cat   = isset($_GET['tag_cat']) ? preg_replace('/[^a-z0-9_-]/', '', $_GET['tag_cat']) : '';
$vr_tag_id    = isset($_GET['tag_id']) ? (int)$_GET['tag_id'] : 0;
$vr_date_from = isset($_GET['date_from']) ? preg_replace('/[^0-9-]/', '', $_GET['date_from']) : '';
$vr_date_to   = isset($_GET['date_to']) ? preg_replace('/[^0-9-]/', '', $_GET['date_to']) : '';
$vr_search    = $_GET['search'] ?? '';
$vr_view_mode = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'grid';
$vr_game_id   = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
$vr_opponent  = isset($_GET['opponent']) ? preg_replace('/[^a-zA-Z0-9 _-]/', '', $_GET['opponent']) : '';

// ── Load tag categories & tags ────────────────────────────────
$vr_tag_categories = [];
$vr_all_tags = [];
try {
    $stmt = $pdo->prepare("SELECT DISTINCT category FROM vr_tags ORDER BY category");
    $stmt->execute();
    $vr_tag_categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt2 = $pdo->prepare("SELECT id, name, category, color FROM vr_tags ORDER BY category, name");
    $stmt2->execute();
    $vr_all_tags = $stmt2->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('VR tags: ' . $e->getMessage()); }

// ── Clips Tab Data ────────────────────────────────────────────
$vr_clips = [];
if ($vr_tab === 'clips') {
    try {
        $q = "
            SELECT c.id, c.title, c.description, c.start_time, c.end_time,
                   c.thumbnail_path, c.created_at,
                   vs.filename AS source_filename, vs.camera_angle, vs.duration AS source_duration,
                   GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') AS tag_names,
                   GROUP_CONCAT(DISTINCT t.category ORDER BY t.category SEPARATOR ', ') AS tag_categories,
                   GROUP_CONCAT(DISTINCT t.color ORDER BY t.category, t.name SEPARATOR ',') AS tag_colors,
                   gs.opponent_team, gs.game_date
            FROM vr_video_clips c
            LEFT JOIN vr_video_sources vs ON c.source_id = vs.id
            LEFT JOIN vr_clip_tags ct ON ct.clip_id = c.id
            LEFT JOIN vr_tags t ON ct.tag_id = t.id
            LEFT JOIN game_schedules gs ON c.game_id = gs.id
        ";

        $where = [];
        $params = [];

        if (!$isAnyCoach) {
            $where[] = "c.id IN (SELECT clip_id FROM vr_clip_athletes WHERE athlete_id = ?)";
            $params[] = $user_id;
        }
        if ($vr_tag_cat !== '') {
            $where[] = "t.category = ?";
            $params[] = $vr_tag_cat;
        }
        if ($vr_tag_id > 0) {
            $where[] = "ct.tag_id = ?";
            $params[] = $vr_tag_id;
        }
        if ($vr_date_from !== '') {
            $where[] = "c.created_at >= ?";
            $params[] = $vr_date_from . ' 00:00:00';
        }
        if ($vr_date_to !== '') {
            $where[] = "c.created_at <= ?";
            $params[] = $vr_date_to . ' 23:59:59';
        }
        if ($vr_search !== '') {
            $where[] = "(c.title LIKE ? OR c.description LIKE ? OR t.name LIKE ?)";
            $search_param = '%' . $vr_search . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
        }

        if (!empty($where)) $q .= " WHERE " . implode(" AND ", $where);
        $q .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT 100";

        $stmt = $pdo->prepare($q);
        $stmt->execute($params);
        $vr_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('VR clips: ' . $e->getMessage()); }
}

// ── By Game Tab Data ──────────────────────────────────────────
$vr_games = [];
$vr_game_clips = [];
if ($vr_tab === 'by_game') {
    try {
        $stmt = $pdo->prepare("
            SELECT gs.id, gs.opponent_team, gs.game_date, gs.game_type, gs.status,
                   gs.home_score, gs.away_score, gs.is_home_game,
                   t.name AS team_name,
                   (SELECT COUNT(*) FROM vr_video_clips vc WHERE vc.game_id = gs.id) AS clip_count
            FROM game_schedules gs
            LEFT JOIN teams t ON gs.team_id = t.id
            ORDER BY gs.game_date DESC
            LIMIT 50
        ");
        $stmt->execute();
        $vr_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('VR games: ' . $e->getMessage()); }

    if ($vr_game_id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.title, c.start_time, c.end_time, c.thumbnail_path, c.created_at,
                       vs.camera_angle,
                       GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS tag_names
                FROM vr_video_clips c
                LEFT JOIN vr_video_sources vs ON c.source_id = vs.id
                LEFT JOIN vr_clip_tags ct ON ct.clip_id = c.id
                LEFT JOIN vr_tags t ON ct.tag_id = t.id
                WHERE c.game_id = ?
                GROUP BY c.id ORDER BY c.start_time
            ");
            $stmt->execute([$vr_game_id]);
            $vr_game_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('VR game clips: ' . $e->getMessage()); }
    }
}

// ── Opponent Scouting Tab Data ────────────────────────────────
$vr_opponents = [];
$vr_scout_clips = [];
if ($vr_tab === 'scouting') {
    try {
        $stmt = $pdo->prepare("SELECT DISTINCT opponent_team FROM game_schedules WHERE opponent_team IS NOT NULL ORDER BY opponent_team");
        $stmt->execute();
        $vr_opponents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { error_log('VR opponents: ' . $e->getMessage()); }

    if ($vr_opponent !== '') {
        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.title, c.start_time, c.end_time, c.thumbnail_path, c.created_at,
                       vs.camera_angle, gs.game_date, gs.opponent_team,
                       GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS tag_names
                FROM vr_video_clips c
                JOIN game_schedules gs ON c.game_id = gs.id
                LEFT JOIN vr_video_sources vs ON c.source_id = vs.id
                LEFT JOIN vr_clip_tags ct ON ct.clip_id = c.id
                LEFT JOIN vr_tags t ON ct.tag_id = t.id
                WHERE gs.opponent_team = ?
                GROUP BY c.id ORDER BY gs.game_date DESC, c.start_time
            ");
            $stmt->execute([$vr_opponent]);
            $vr_scout_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('VR scout: ' . $e->getMessage()); }
    }
}

if (!function_exists('vr_format_duration')) {
    function vr_format_duration($start, $end) {
        $dur = abs((float)$end - (float)$start);
        $m = floor($dur / 60);
        $s = floor($dur % 60);
        return sprintf('%d:%02d', $m, $s);
    }
}
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-film"></i> Video Review</h1>
    <p class="gp-page-desc">
        <?php if ($isAnyCoach): ?>
            Browse clips, review game footage, and scout opponents
        <?php else: ?>
            View your tagged clips and game highlights
        <?php endif; ?>
    </p>
</div>

<!-- Tabs -->
<div class="vr-tabs-bar">
    <div class="vr-tabs">
        <a class="vr-tab <?= $vr_tab === 'clips' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=video_review&tab=clips">
            <i class="fas fa-scissors"></i> Clips
        </a>
        <a class="vr-tab <?= $vr_tab === 'by_game' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=video_review&tab=by_game">
            <i class="fas fa-hockey-puck"></i> By Game
        </a>
        <?php if ($isAnyCoach): ?>
        <a class="vr-tab <?= $vr_tab === 'scouting' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=video_review&tab=scouting">
            <i class="fas fa-binoculars"></i> Opponent Scouting
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($vr_tab === 'clips'): ?>
<!-- ── Clips Tab ── -->
<div class="vr-filter-bar">
    <form method="GET" action="" class="vr-filters">
        <input type="hidden" name="page" value="video_review">
        <input type="hidden" name="tab" value="clips">
        <div class="vr-search-wrap">
            <input type="text" name="search" class="vr-input" placeholder="Search clips…" value="<?= htmlspecialchars($vr_search) ?>">
            <button type="submit" class="vr-search-btn"><i class="fas fa-search"></i></button>
        </div>
        <select name="tag_cat" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($vr_tag_categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $vr_tag_cat === $cat ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($cat)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="tag_id" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="0">All Tags</option>
            <?php foreach ($vr_all_tags as $tag): ?>
            <option value="<?= (int)$tag['id'] ?>" <?= $vr_tag_id === (int)$tag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tag['name']) ?> (<?= htmlspecialchars($tag['category']) ?>)</option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" class="vr-input" value="<?= htmlspecialchars($vr_date_from) ?>" title="From date" onchange="this.form.submit()">
        <input type="date" name="date_to" class="vr-input" value="<?= htmlspecialchars($vr_date_to) ?>" title="To date" onchange="this.form.submit()">
        <a href="/gameplan.php?page=video_review&tab=clips&view=<?= $vr_view_mode === 'grid' ? 'list' : 'grid' ?>" class="vr-view-toggle" title="Toggle view">
            <i class="fas <?= $vr_view_mode === 'grid' ? 'fa-list' : 'fa-grip' ?>"></i>
        </a>
    </form>
</div>

<?php if (empty($vr_clips)): ?>
<div class="gp-empty">
    <i class="fas fa-film"></i>
    <p>No clips found. <?= $isAnyCoach ? 'Create clips in the Film Room.' : 'Ask your coach to tag you in clips.' ?></p>
</div>
<?php elseif ($vr_view_mode === 'grid'): ?>
<div class="gp-grid">
    <?php foreach ($vr_clips as $clip): ?>
    <div class="gp-card" data-clip-id="<?= (int)$clip['id'] ?>">
        <div class="gp-card-thumb">
            <?php if (!empty($clip['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars($clip['thumbnail_path']) ?>" alt="Clip thumbnail" loading="lazy">
            <?php else: ?>
            <i class="fas fa-play-circle"></i>
            <?php endif; ?>
            <span class="gp-card-badge"><?= vr_format_duration($clip['start_time'] ?? 0, $clip['end_time'] ?? 0) ?></span>
        </div>
        <div class="gp-card-body">
            <div class="gp-card-title"><?= htmlspecialchars($clip['title'] ?? 'Untitled Clip') ?></div>
            <div class="gp-card-meta">
                <?php if (!empty($clip['camera_angle'])): ?>
                <span><i class="fas fa-video"></i> <?= htmlspecialchars(ucfirst($clip['camera_angle'])) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($clip['created_at'])) ?></span>
            </div>
            <?php if (!empty($clip['tag_names'])): ?>
            <div class="vr-clip-tags-row">
                <?php foreach (explode(', ', $clip['tag_names']) as $i => $tname): ?>
                <?php $colors = explode(',', $clip['tag_colors'] ?? ''); $color = trim($colors[$i] ?? '#6B46C1'); ?>
                <span class="vr-tag-pill" style="background:<?= htmlspecialchars($color) ?>20;color:<?= htmlspecialchars($color) ?>;border:1px solid <?= htmlspecialchars($color) ?>40"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<!-- List View grouped by tag category -->
<?php
$grouped = [];
foreach ($vr_clips as $clip) {
    $cats = $clip['tag_categories'] ?? 'Uncategorized';
    $first_cat = explode(', ', $cats)[0] ?: 'Uncategorized';
    $grouped[$first_cat][] = $clip;
}
ksort($grouped);
?>
<?php foreach ($grouped as $group_name => $group_clips): ?>
<div class="vr-list-group">
    <h3 class="vr-section-title"><?= htmlspecialchars(ucfirst($group_name)) ?> (<?= count($group_clips) ?>)</h3>
    <?php foreach ($group_clips as $clip): ?>
    <div class="vr-video-row" data-clip-id="<?= (int)$clip['id'] ?>">
        <div class="vr-thumb">
            <?php if (!empty($clip['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars($clip['thumbnail_path']) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;border-radius:10px">
            <?php else: ?>
            <i class="fas fa-play-circle"></i>
            <?php endif; ?>
        </div>
        <div class="vr-details">
            <h4><?= htmlspecialchars($clip['title'] ?? 'Untitled Clip') ?></h4>
            <div class="vr-meta">
                <span><i class="fas fa-clock"></i> <?= vr_format_duration($clip['start_time'] ?? 0, $clip['end_time'] ?? 0) ?></span>
                <?php if (!empty($clip['camera_angle'])): ?>
                <span><i class="fas fa-video"></i> <?= htmlspecialchars(ucfirst($clip['camera_angle'])) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($clip['created_at'])) ?></span>
                <?php if (!empty($clip['opponent_team'])): ?>
                <span><i class="fas fa-hockey-puck"></i> vs <?= htmlspecialchars($clip['opponent_team']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($clip['tag_names'])): ?>
            <div class="vr-clip-tags-row" style="margin-top:6px">
                <?php foreach (explode(', ', $clip['tag_names']) as $tname): ?>
                <span class="vr-tag-pill"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($vr_tab === 'by_game'): ?>
<!-- ── By Game Tab ── -->
<?php if (empty($vr_games)): ?>
<div class="gp-empty">
    <i class="fas fa-calendar-xmark"></i>
    <p>No games found in the schedule.</p>
</div>
<?php else: ?>
<div class="vr-game-list">
    <?php foreach ($vr_games as $game): ?>
    <div class="vr-game-card <?= $vr_game_id === (int)$game['id'] ? 'vr-game-expanded' : '' ?>">
        <a href="/gameplan.php?page=video_review&tab=by_game&game_id=<?= (int)$game['id'] ?>" class="vr-game-header">
            <div class="vr-game-info">
                <span class="vr-game-date"><i class="fas fa-calendar"></i> <?= date('M j, Y – g:ia', strtotime($game['game_date'])) ?></span>
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
            <div class="vr-game-meta">
                <span class="vr-status-badge vr-badge-<?= htmlspecialchars($game['status']) ?>"><?= htmlspecialchars(ucfirst($game['status'])) ?></span>
                <span class="vr-clip-count"><i class="fas fa-scissors"></i> <?= (int)$game['clip_count'] ?> clips</span>
            </div>
        </a>
        <?php if ($vr_game_id === (int)$game['id']): ?>
        <div class="vr-game-clips">
            <?php if (!empty($vr_game_clips)): ?>
            <?php foreach ($vr_game_clips as $gc): ?>
            <div class="vr-video-row">
                <div class="vr-thumb"><i class="fas fa-play-circle"></i></div>
                <div class="vr-details">
                    <h4><?= htmlspecialchars($gc['title'] ?? 'Clip') ?></h4>
                    <div class="vr-meta">
                        <span><i class="fas fa-clock"></i> <?= vr_format_duration($gc['start_time'] ?? 0, $gc['end_time'] ?? 0) ?></span>
                        <?php if (!empty($gc['camera_angle'])): ?>
                        <span><i class="fas fa-video"></i> <?= htmlspecialchars(ucfirst($gc['camera_angle'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($gc['tag_names'])): ?>
                        <span><i class="fas fa-tags"></i> <?= htmlspecialchars($gc['tag_names']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="gp-empty" style="padding:20px"><i class="fas fa-film"></i><p>No clips for this game yet.</p></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($vr_tab === 'scouting'): ?>
<!-- ── Opponent Scouting Tab ── -->
<div class="vr-filter-bar">
    <form method="GET" class="vr-filters">
        <input type="hidden" name="page" value="video_review">
        <input type="hidden" name="tab" value="scouting">
        <select name="opponent" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="">— Select Opponent —</option>
            <?php foreach ($vr_opponents as $opp): ?>
            <option value="<?= htmlspecialchars($opp) ?>" <?= $vr_opponent === $opp ? 'selected' : '' ?>><?= htmlspecialchars($opp) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($vr_opponent === ''): ?>
<div class="gp-empty">
    <i class="fas fa-binoculars"></i>
    <p>Select an opponent team above to view scouting clips.</p>
</div>
<?php elseif (empty($vr_scout_clips)): ?>
<div class="gp-empty">
    <i class="fas fa-film"></i>
    <p>No clips found for games against <?= htmlspecialchars($vr_opponent) ?>.</p>
</div>
<?php else: ?>
<h3 class="vr-section-title">Clips vs <?= htmlspecialchars($vr_opponent) ?> (<?= count($vr_scout_clips) ?>)</h3>
<div class="gp-grid">
    <?php foreach ($vr_scout_clips as $sc): ?>
    <div class="gp-card">
        <div class="gp-card-thumb">
            <?php if (!empty($sc['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars($sc['thumbnail_path']) ?>" alt="" loading="lazy">
            <?php else: ?>
            <i class="fas fa-play-circle"></i>
            <?php endif; ?>
            <span class="gp-card-badge"><?= vr_format_duration($sc['start_time'] ?? 0, $sc['end_time'] ?? 0) ?></span>
        </div>
        <div class="gp-card-body">
            <div class="gp-card-title"><?= htmlspecialchars($sc['title'] ?? 'Clip') ?></div>
            <div class="gp-card-meta">
                <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($sc['game_date'])) ?></span>
                <?php if (!empty($sc['camera_angle'])): ?>
                <span><i class="fas fa-video"></i> <?= htmlspecialchars(ucfirst($sc['camera_angle'])) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($sc['tag_names'])): ?>
            <div class="vr-clip-tags-row">
                <?php foreach (explode(', ', $sc['tag_names']) as $tname): ?>
                <span class="vr-tag-pill"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.vr-tabs-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 16px 20px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; }
.vr-tabs { display: flex; gap: 4px; background: rgba(10,10,15,.6); padding: 5px; border-radius: 10px; border: 1px solid rgba(45,45,63,.5); }
.vr-tab { padding: 10px 18px; background: transparent; border: none; color: var(--gp-text-dim); border-radius: 7px; font-size: 13px; font-weight: 600; cursor: pointer; transition: all .2s; display: inline-flex; align-items: center; gap: 7px; font-family: 'Inter', sans-serif; text-decoration: none; }
.vr-tab:hover { color: var(--gp-text); background: rgba(107,70,193,.12); }
.vr-tab.vr-tab-active { color: #fff; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); }

.vr-filter-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; }
.vr-filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.vr-search-wrap { display: flex; }
.vr-search-wrap .vr-input { border-radius: 8px 0 0 8px; min-width: 180px; }
.vr-search-btn { padding: 0 14px; height: 40px; background: var(--gp-primary); border: none; border-radius: 0 8px 8px 0; color: #fff; cursor: pointer; font-family: 'Inter', sans-serif; }
.vr-select { min-width: 130px; }
.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-view-toggle { width: 40px; height: 40px; background: var(--gp-bg); border: 1px solid var(--gp-border); color: var(--gp-text-muted); border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; transition: all .2s; }
.vr-view-toggle:hover { border-color: var(--gp-primary-light); color: var(--gp-primary-light); }

.vr-section-title { font-size: 16px; font-weight: 700; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--gp-border); display: flex; align-items: center; gap: 10px; color: var(--gp-text); }
.vr-section-title::before { content: ''; width: 4px; height: 20px; background: linear-gradient(180deg, var(--gp-primary), var(--gp-primary-light)); border-radius: 2px; }

.vr-clip-tags-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 4px; }
.vr-tag-pill { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; background: rgba(107,70,193,.12); color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.25); }

.vr-video-row { display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 18px; background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; margin-bottom: 10px; transition: border-color .2s, transform .15s; }
.vr-video-row:hover { border-color: rgba(107,70,193,.4); transform: translateY(-2px); }
.vr-thumb { width: 80px; height: 56px; background: rgba(107,70,193,.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.2); overflow: hidden; }
.vr-details { min-width: 0; }
.vr-details h4 { font-size: 14px; font-weight: 700; color: var(--gp-text); margin: 0 0 6px; }
.vr-meta { display: flex; flex-wrap: wrap; gap: 14px; }
.vr-meta span { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--gp-text-muted); }
.vr-meta i { color: var(--gp-primary-light); font-size: 11px; }
.vr-list-group { margin-bottom: 28px; }

.vr-game-list { display: flex; flex-direction: column; gap: 10px; }
.vr-game-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; overflow: hidden; transition: border-color .2s; }
.vr-game-card:hover, .vr-game-expanded { border-color: rgba(107,70,193,.4); }
.vr-game-header { display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; text-decoration: none; color: var(--gp-text); gap: 16px; }
.vr-game-info { display: flex; flex-direction: column; gap: 4px; }
.vr-game-date { font-size: 12px; color: var(--gp-text-muted); display: inline-flex; align-items: center; gap: 6px; }
.vr-game-date i { color: var(--gp-primary-light); }
.vr-game-matchup { font-size: 15px; font-weight: 700; }
.vr-game-meta { display: flex; align-items: center; gap: 12px; flex-shrink: 0; }
.vr-clip-count { font-size: 12px; color: var(--gp-text-muted); display: inline-flex; align-items: center; gap: 5px; }
.vr-clip-count i { color: var(--gp-primary-light); }
.vr-game-clips { padding: 0 20px 16px; border-top: 1px solid var(--gp-border); }

.vr-status-badge { display: inline-flex; align-items: center; gap: 5px; padding: 4px 10px; border-radius: 16px; font-size: 10px; font-weight: 700; text-transform: uppercase; white-space: nowrap; }
.vr-badge-scheduled { background: rgba(59,130,246,.1); color: #3B82F6; border: 1px solid rgba(59,130,246,.2); }
.vr-badge-completed { background: rgba(16,185,129,.1); color: #10B981; border: 1px solid rgba(16,185,129,.2); }
.vr-badge-in_progress { background: rgba(245,158,11,.1); color: #F59E0B; border: 1px solid rgba(245,158,11,.2); }
.vr-badge-cancelled { background: rgba(239,68,68,.1); color: #EF4444; border: 1px solid rgba(239,68,68,.2); }
.vr-badge-postponed { background: rgba(168,168,184,.1); color: var(--gp-text-muted); border: 1px solid rgba(168,168,184,.2); }

@media (max-width: 768px) {
    .vr-tabs-bar { flex-direction: column; align-items: stretch; padding: 14px; }
    .vr-filters { flex-direction: column; width: 100%; }
    .vr-search-wrap { width: 100%; }
    .vr-search-wrap .vr-input { width: 100%; }
    .vr-select { width: 100%; }
    .vr-video-row { grid-template-columns: 1fr; }
    .vr-thumb { display: none; }
    .vr-game-header { flex-direction: column; align-items: flex-start; }
}
</style>
