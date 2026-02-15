<?php
/**
 * Game Plan - Video Review View
 * Three tabs: Clips / By Game (default) / Opponent Scouting
 * Hierarchy: Select Team -> See games -> Select game -> See clips by tag
 * Athletes see clips they're tagged in; coaches see all clips.
 *
 * Uses site standard classes: filter-box, card, btn, form-select, etc.
 * Variables $isAnyCoach, $user_id, $pdo are already available.
 * Rendered inside standalone gameplan.php shell.
 */

// -- Tab & Filter parameters -----------------------------------------------
$vr_tab = isset($_GET['tab']) ? preg_replace('/[^a-z_]/', '', $_GET['tab']) : 'by_game';
if (!in_array($vr_tab, ['clips', 'by_game', 'scouting', 'device_pair'])) $vr_tab = 'by_game';

$vr_tag_cat   = isset($_GET['tag_cat']) ? preg_replace('/[^a-z0-9_-]/', '', $_GET['tag_cat']) : '';
$vr_tag_id    = isset($_GET['tag_id']) ? (int)$_GET['tag_id'] : 0;
$vr_date_from = isset($_GET['date_from']) ? preg_replace('/[^0-9-]/', '', $_GET['date_from']) : '';
$vr_date_to   = isset($_GET['date_to']) ? preg_replace('/[^0-9-]/', '', $_GET['date_to']) : '';
$vr_search    = $_GET['search'] ?? '';
$vr_view_mode = isset($_GET['view']) && $_GET['view'] === 'list' ? 'list' : 'grid';
$vr_game_id   = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
$vr_opponent  = isset($_GET['opponent']) ? preg_replace('/[^a-zA-Z0-9 _-]/', '', $_GET['opponent']) : '';
$vr_team_id   = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$vr_month     = isset($_GET['month']) ? preg_replace('/[^0-9-]/', '', $_GET['month']) : '';
$vr_sort      = isset($_GET['sort']) ? preg_replace('/[^a-z_]/', '', $_GET['sort']) : 'date_desc';
if (!in_array($vr_sort, ['date_desc', 'date_asc', 'opponent_asc', 'opponent_desc'])) $vr_sort = 'date_desc';
$vr_clip_tag_filter = isset($_GET['clip_tag']) ? (int)$_GET['clip_tag'] : 0;

// -- Load teams for the team selector --------------------------------------
$vr_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $vr_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('VR teams: ' . $e->getMessage()); }

// -- Load tag categories & tags --------------------------------------------
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

// -- Clips Tab Data --------------------------------------------------------
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
        if ($vr_team_id > 0) {
            $where[] = "gs.team_id = ?";
            $params[] = $vr_team_id;
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

// -- By Game Tab Data ------------------------------------------------------
$vr_games = [];
$vr_game_clips = [];
$vr_game_info = null;
$vr_game_opponents = [];
if ($vr_tab === 'by_game') {
    // Load distinct opponents for the filter dropdown (scoped to selected team)
    try {
        $opp_q = "SELECT DISTINCT gs.opponent_team FROM game_schedules gs WHERE gs.opponent_team IS NOT NULL";
        $opp_params = [];
        if ($vr_team_id > 0) {
            $opp_q .= " AND gs.team_id = ?";
            $opp_params[] = $vr_team_id;
        }
        $opp_q .= " ORDER BY gs.opponent_team";
        $stmt = $pdo->prepare($opp_q);
        $stmt->execute($opp_params);
        $vr_game_opponents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { error_log('VR game opponents: ' . $e->getMessage()); }

    // Build games query with filters
    try {
        $q = "
            SELECT gs.id, gs.opponent_team, gs.game_date, gs.game_type, gs.status,
                   gs.home_score, gs.away_score, gs.is_home_game,
                   t.name AS team_name,
                   (SELECT COUNT(*) FROM vr_video_clips vc WHERE vc.game_id = gs.id) AS clip_count
            FROM game_schedules gs
            LEFT JOIN teams t ON gs.team_id = t.id
        ";
        $where = [];
        $params = [];

        if ($vr_team_id > 0) {
            $where[] = "gs.team_id = ?";
            $params[] = $vr_team_id;
        }
        if ($vr_opponent !== '') {
            $where[] = "gs.opponent_team = ?";
            $params[] = $vr_opponent;
        }
        if ($vr_month !== '') {
            $where[] = "DATE_FORMAT(gs.game_date, '%Y-%m') = ?";
            $params[] = $vr_month;
        }

        if (!empty($where)) $q .= " WHERE " . implode(" AND ", $where);

        switch ($vr_sort) {
            case 'date_asc':      $q .= " ORDER BY gs.game_date ASC"; break;
            case 'opponent_asc':  $q .= " ORDER BY gs.opponent_team ASC, gs.game_date DESC"; break;
            case 'opponent_desc': $q .= " ORDER BY gs.opponent_team DESC, gs.game_date DESC"; break;
            default:              $q .= " ORDER BY gs.game_date DESC"; break;
        }
        $q .= " LIMIT 100";

        $stmt = $pdo->prepare($q);
        $stmt->execute($params);
        $vr_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('VR games: ' . $e->getMessage()); }

    // Load clips for selected game, grouped by tag
    if ($vr_game_id > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT gs.id, gs.opponent_team, gs.game_date, gs.game_type, gs.status,
                       gs.home_score, gs.away_score, gs.is_home_game,
                       t.name AS team_name
                FROM game_schedules gs
                LEFT JOIN teams t ON gs.team_id = t.id
                WHERE gs.id = ?
            ");
            $stmt->execute([$vr_game_id]);
            $vr_game_info = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('VR game info: ' . $e->getMessage()); }

        try {
            $clip_q = "
                SELECT c.id, c.title, c.description, c.start_time, c.end_time,
                       c.thumbnail_path, c.created_at,
                       vs.camera_angle,
                       GROUP_CONCAT(DISTINCT t.id ORDER BY t.category, t.name SEPARATOR ',') AS tag_ids,
                       GROUP_CONCAT(DISTINCT t.name ORDER BY t.category, t.name SEPARATOR ', ') AS tag_names,
                       GROUP_CONCAT(DISTINCT t.category ORDER BY t.category SEPARATOR ', ') AS tag_categories,
                       GROUP_CONCAT(DISTINCT t.color ORDER BY t.category, t.name SEPARATOR ',') AS tag_colors
                FROM vr_video_clips c
                LEFT JOIN vr_video_sources vs ON c.source_id = vs.id
                LEFT JOIN vr_clip_tags ct ON ct.clip_id = c.id
                LEFT JOIN vr_tags t ON ct.tag_id = t.id
                WHERE c.game_id = ?
            ";
            $clip_params = [$vr_game_id];

            if ($vr_clip_tag_filter > 0) {
                $clip_q .= " AND c.id IN (SELECT ct2.clip_id FROM vr_clip_tags ct2 WHERE ct2.tag_id = ?)";
                $clip_params[] = $vr_clip_tag_filter;
            }
            if (!$isAnyCoach) {
                $clip_q .= " AND c.id IN (SELECT ca.clip_id FROM vr_clip_athletes ca WHERE ca.athlete_id = ?)";
                $clip_params[] = $user_id;
            }

            $clip_q .= " GROUP BY c.id ORDER BY c.start_time";

            $stmt = $pdo->prepare($clip_q);
            $stmt->execute($clip_params);
            $vr_game_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('VR game clips: ' . $e->getMessage()); }
    }
}

// -- Opponent Scouting Tab Data --------------------------------------------
$vr_opponents = [];
$vr_scout_clips = [];
if ($vr_tab === 'scouting') {
    try {
        $opp_q = "SELECT DISTINCT opponent_team FROM game_schedules WHERE opponent_team IS NOT NULL";
        $opp_params = [];
        if ($vr_team_id > 0) {
            $opp_q .= " AND team_id = ?";
            $opp_params[] = $vr_team_id;
        }
        $opp_q .= " ORDER BY opponent_team";
        $stmt = $pdo->prepare($opp_q);
        $stmt->execute($opp_params);
        $vr_opponents = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) { error_log('VR opponents: ' . $e->getMessage()); }

    if ($vr_opponent !== '') {
        try {
            $scout_q = "
                SELECT c.id, c.title, c.start_time, c.end_time, c.thumbnail_path, c.created_at,
                       vs.camera_angle, gs.game_date, gs.opponent_team,
                       GROUP_CONCAT(DISTINCT t.name SEPARATOR ', ') AS tag_names
                FROM vr_video_clips c
                JOIN game_schedules gs ON c.game_id = gs.id
                LEFT JOIN vr_video_sources vs ON c.source_id = vs.id
                LEFT JOIN vr_clip_tags ct ON ct.clip_id = c.id
                LEFT JOIN vr_tags t ON ct.tag_id = t.id
                WHERE gs.opponent_team = ?
            ";
            $scout_params = [$vr_opponent];
            if ($vr_team_id > 0) {
                $scout_q .= " AND gs.team_id = ?";
                $scout_params[] = $vr_team_id;
            }
            $scout_q .= " GROUP BY c.id ORDER BY gs.game_date DESC, c.start_time";

            $stmt = $pdo->prepare($scout_q);
            $stmt->execute($scout_params);
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
if (!function_exists('vr_safe_color')) {
    function vr_safe_color($color, $fallback = '#6B46C1') {
        $color = trim($color);
        return preg_match('/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $color) ? $color : $fallback;
    }
}
?>

<!-- Team Selector (global across all tabs) -->
<div class="filter-box" style="margin-top: 8px;">
    <div class="filter-box-header"><i class="fas fa-users"></i> Team</div>
    <div class="filter-box-content">
        <form method="GET" action="" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <input type="hidden" name="page" value="video_review">
            <input type="hidden" name="tab" value="<?= htmlspecialchars($vr_tab) ?>">
            <div class="filter-field" style="flex: 1; min-width: 200px;">
                <select name="team_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Teams</option>
                    <?php foreach ($vr_teams as $team): ?>
                    <option value="<?= (int)$team['id'] ?>" <?= $vr_team_id === (int)$team['id'] ? 'selected' : '' ?>><?= htmlspecialchars($team['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<!-- Sub-tabs -->
<div class="page-tabs page-tabs-secondary" style="flex-wrap: wrap; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border);">
    <a href="/gameplan.php?page=video_review&tab=clips<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="page-tab <?= $vr_tab === 'clips' ? 'active' : '' ?>">
        <i class="fas fa-scissors"></i> Clips
    </a>
    <a href="/gameplan.php?page=video_review&tab=by_game<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="page-tab <?= $vr_tab === 'by_game' ? 'active' : '' ?>">
        <i class="fas fa-hockey-puck"></i> By Game
    </a>
    <?php if ($isAnyCoach): ?>
    <a href="/gameplan.php?page=video_review&tab=scouting<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="page-tab <?= $vr_tab === 'scouting' ? 'active' : '' ?>">
        <i class="fas fa-binoculars"></i> Opponent Scouting
    </a>
    <a href="/gameplan.php?page=video_review&tab=device_pair<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="page-tab <?= $vr_tab === 'device_pair' ? 'active' : '' ?>">
        <i class="fas fa-link"></i> Device Pairing
    </a>
    <?php endif; ?>
</div>

<?php if ($vr_tab === 'clips'): ?>
<!-- -- Clips Tab -- -->
<div class="filter-box" style="margin-top: 20px;">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Clips</div>
    <div class="filter-box-content">
        <form method="GET" action="">
            <input type="hidden" name="page" value="video_review">
            <input type="hidden" name="tab" value="clips">
            <?php if ($vr_team_id > 0): ?><input type="hidden" name="team_id" value="<?= $vr_team_id ?>"><?php endif; ?>
            <div class="filter-row">
                <div class="filter-field" style="grid-column: span 2;">
                    <label>Search</label>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search clips..." value="<?= htmlspecialchars($vr_search) ?>">
                    </div>
                </div>
                <div class="filter-field">
                    <label>Category</label>
                    <select name="tag_cat" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($vr_tag_categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $vr_tag_cat === $cat ? 'selected' : '' ?>><?= htmlspecialchars(ucfirst($cat)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Tag</label>
                    <select name="tag_id" class="form-select">
                        <option value="0">All Tags</option>
                        <?php foreach ($vr_all_tags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>" <?= $vr_tag_id === (int)$tag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tag['name']) ?> (<?= htmlspecialchars($tag['category']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>From Date</label>
                    <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($vr_date_from) ?>">
                </div>
                <div class="filter-field">
                    <label>To Date</label>
                    <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($vr_date_to) ?>">
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="/gameplan.php?page=video_review&tab=clips<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                <a href="/gameplan.php?page=video_review&tab=clips&view=<?= $vr_view_mode === 'grid' ? 'list' : 'grid' ?><?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?><?= $vr_search !== '' ? '&search=' . urlencode($vr_search) : '' ?><?= $vr_tag_cat !== '' ? '&tag_cat=' . urlencode($vr_tag_cat) : '' ?><?= $vr_tag_id > 0 ? '&tag_id=' . $vr_tag_id : '' ?><?= $vr_date_from !== '' ? '&date_from=' . urlencode($vr_date_from) : '' ?><?= $vr_date_to !== '' ? '&date_to=' . urlencode($vr_date_to) : '' ?>" class="btn btn-secondary" title="Toggle view">
                    <i class="fas <?= $vr_view_mode === 'grid' ? 'fa-list' : 'fa-grip' ?>"></i> <?= $vr_view_mode === 'grid' ? 'List View' : 'Grid View' ?>
                </a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($vr_clips)): ?>
<div class="empty-state">
    <i class="fas fa-film"></i>
    <h3>No Clips Found</h3>
    <p><?= $isAnyCoach ? 'Create clips in the Film Room.' : 'Ask your coach to tag you in clips.' ?></p>
</div>
<?php elseif ($vr_view_mode === 'grid'): ?>
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; margin-top: 20px;">
    <?php foreach ($vr_clips as $clip): ?>
    <div class="card" data-clip-id="<?= (int)$clip['id'] ?>">
        <div style="position: relative; background: var(--bg-card); border-bottom: 1px solid var(--border); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 8px 8px 0 0;">
            <?php if (!empty($clip['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars($clip['thumbnail_path']) ?>" alt="Clip thumbnail" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
            <?php else: ?>
            <i class="fas fa-play-circle" style="font-size: 36px; color: var(--text-muted); opacity: 0.4;"></i>
            <?php endif; ?>
            <span class="status-badge" style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.75); color: #fff; border: none;"><?= vr_format_duration($clip['start_time'] ?? 0, $clip['end_time'] ?? 0) ?></span>
        </div>
        <div class="card-body" style="padding: 14px;">
            <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 8px; color: var(--text-primary);"><?= htmlspecialchars($clip['title'] ?? 'Untitled Clip') ?></h4>
            <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--text-muted);">
                <?php if (!empty($clip['camera_angle'])): ?>
                <span><i class="fas fa-video" style="margin-right: 4px; color: var(--primary);"></i><?= htmlspecialchars(ucfirst($clip['camera_angle'])) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar" style="margin-right: 4px; color: var(--primary);"></i><?= date('M j, Y', strtotime($clip['created_at'])) ?></span>
            </div>
            <?php if (!empty($clip['tag_names'])): ?>
            <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px;">
                <?php foreach (explode(', ', $clip['tag_names']) as $i => $tname): ?>
                <?php $colors = explode(',', $clip['tag_colors'] ?? ''); $color = vr_safe_color($colors[$i] ?? ''); ?>
                <span class="status-badge" style="font-size: 10px; padding: 2px 8px; background: <?= htmlspecialchars($color) ?>20; color: <?= htmlspecialchars($color) ?>; border: 1px solid <?= htmlspecialchars($color) ?>40;"><?= htmlspecialchars($tname) ?></span>
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
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-tag"></i> <?= htmlspecialchars(ucfirst($group_name)) ?></h3>
        <span class="status-badge active"><?= count($group_clips) ?> clips</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <?php foreach ($group_clips as $clip): ?>
        <div data-clip-id="<?= (int)$clip['id'] ?>" style="display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 20px; border-bottom: 1px solid var(--border); transition: background .2s; cursor: pointer;" onmouseover="this.style.background='var(--bg-hover, rgba(255,255,255,0.02))'" onmouseout="this.style.background='transparent'">
            <div style="width: 80px; height: 56px; background: rgba(var(--primary-rgb, 107,70,193), 0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border);">
                <?php if (!empty($clip['thumbnail_path'])): ?>
                <img src="<?= htmlspecialchars($clip['thumbnail_path']) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                <?php else: ?>
                <i class="fas fa-play-circle" style="font-size: 22px; color: var(--primary);"></i>
                <?php endif; ?>
            </div>
            <div style="min-width: 0;">
                <h4 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0 0 6px;"><?= htmlspecialchars($clip['title'] ?? 'Untitled Clip') ?></h4>
                <div style="display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-muted);">
                    <span><i class="fas fa-clock" style="margin-right: 4px; color: var(--primary);"></i><?= vr_format_duration($clip['start_time'] ?? 0, $clip['end_time'] ?? 0) ?></span>
                    <?php if (!empty($clip['camera_angle'])): ?>
                    <span><i class="fas fa-video" style="margin-right: 4px; color: var(--primary);"></i><?= htmlspecialchars(ucfirst($clip['camera_angle'])) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-calendar" style="margin-right: 4px; color: var(--primary);"></i><?= date('M j, Y', strtotime($clip['created_at'])) ?></span>
                    <?php if (!empty($clip['opponent_team'])): ?>
                    <span><i class="fas fa-hockey-puck" style="margin-right: 4px; color: var(--primary);"></i>vs <?= htmlspecialchars($clip['opponent_team']) ?></span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($clip['tag_names'])): ?>
                <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px;">
                    <?php foreach (explode(', ', $clip['tag_names']) as $tname): ?>
                    <span class="status-badge" style="font-size: 10px; padding: 2px 8px;"><?= htmlspecialchars($tname) ?></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($vr_tab === 'by_game'): ?>
<!-- -- By Game Tab -- -->

<?php if ($vr_game_id > 0 && $vr_game_info): ?>
<!-- Game Detail View: clips grouped by tag category -->
<div style="margin-top: 20px;">
    <a href="/gameplan.php?page=video_review&tab=by_game<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?><?= $vr_opponent !== '' ? '&opponent=' . urlencode($vr_opponent) : '' ?><?= $vr_month !== '' ? '&month=' . urlencode($vr_month) : '' ?><?= $vr_sort !== 'date_desc' ? '&sort=' . urlencode($vr_sort) : '' ?>" class="btn btn-secondary" style="margin-bottom: 16px;">
        <i class="fas fa-arrow-left"></i> Back to Games
    </a>

    <div class="card">
        <div class="card-header">
            <h3>
                <i class="fas fa-hockey-puck" style="margin-right: 8px; color: var(--primary);"></i>
                <?= htmlspecialchars($vr_game_info['team_name'] ?? 'Team') ?>
                <?php if ($vr_game_info['status'] === 'completed' && $vr_game_info['home_score'] !== null): ?>
                    <strong><?= (int)$vr_game_info['home_score'] ?> &ndash; <?= (int)$vr_game_info['away_score'] ?></strong>
                <?php else: ?>
                    vs
                <?php endif; ?>
                <?= htmlspecialchars($vr_game_info['opponent_team'] ?? '') ?>
            </h3>
            <span style="font-size: 12px; color: var(--text-muted);">
                <i class="fas fa-calendar" style="margin-right: 4px; color: var(--primary);"></i>
                <?= date('M j, Y', strtotime($vr_game_info['game_date'])) ?>
            </span>
        </div>
    </div>

    <!-- Tag filter for clips within the game -->
    <?php
    // Collect all tags used across this game's clips (before filtering) for the filter bar
    $game_tags_used = [];
    // Re-query without clip_tag filter to get all available tags for filter buttons
    try {
        $tag_q = "
            SELECT DISTINCT t.id, t.name
            FROM vr_clip_tags ct
            JOIN vr_tags t ON ct.tag_id = t.id
            JOIN vr_video_clips c ON ct.clip_id = c.id
            WHERE c.game_id = ?
            ORDER BY t.name
        ";
        $stmt = $pdo->prepare($tag_q);
        $stmt->execute([$vr_game_id]);
        $tag_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tag_rows as $tr) {
            $game_tags_used[(int)$tr['id']] = $tr['name'];
        }
    } catch (PDOException $e) { error_log('VR game tag list: ' . $e->getMessage()); }
    ?>
    <?php if (!empty($game_tags_used)): ?>
    <div class="filter-box" style="margin-top: 12px;">
        <div class="filter-box-header"><i class="fas fa-filter"></i> Filter by Tag</div>
        <div class="filter-box-content">
            <div style="display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
                <a href="/gameplan.php?page=video_review&tab=by_game&game_id=<?= $vr_game_id ?><?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="btn <?= $vr_clip_tag_filter === 0 ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 12px; padding: 4px 12px;">All</a>
                <?php foreach ($game_tags_used as $gtid => $gtname): ?>
                <a href="/gameplan.php?page=video_review&tab=by_game&game_id=<?= $vr_game_id ?>&clip_tag=<?= $gtid ?><?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="btn <?= $vr_clip_tag_filter === $gtid ? 'btn-primary' : 'btn-secondary' ?>" style="font-size: 12px; padding: 4px 12px;"><?= htmlspecialchars($gtname) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (empty($vr_game_clips)): ?>
    <div class="empty-state" style="margin-top: 20px;">
        <i class="fas fa-film" style="font-size: 32px;"></i>
        <h3>No Clips</h3>
        <p>No clips found for this game<?= $vr_clip_tag_filter > 0 ? ' with the selected tag filter' : '' ?>.</p>
    </div>
    <?php else: ?>
    <?php
    // Group clips by tag category
    $clips_by_category = [];
    foreach ($vr_game_clips as $gc) {
        $first_cat = explode(', ', $gc['tag_categories'] ?? '')[0] ?: 'Uncategorized';
        $clips_by_category[$first_cat][] = $gc;
    }
    ksort($clips_by_category);
    ?>
    <?php foreach ($clips_by_category as $cat_name => $cat_clips): ?>
    <div class="card" style="margin-top: 16px;">
        <div class="card-header">
            <h3><i class="fas fa-tag" style="margin-right: 8px; color: var(--primary);"></i> <?= htmlspecialchars(ucfirst($cat_name)) ?></h3>
            <span class="status-badge active"><?= count($cat_clips) ?> clip<?= count($cat_clips) !== 1 ? 's' : '' ?></span>
        </div>
        <div class="card-body" style="padding: 0;">
            <?php foreach ($cat_clips as $gc): ?>
            <div data-clip-id="<?= (int)$gc['id'] ?>" style="display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 20px; border-bottom: 1px solid var(--border); transition: background .2s; cursor: pointer;" onmouseover="this.style.background='var(--bg-hover, rgba(255,255,255,0.02))'" onmouseout="this.style.background='transparent'">
                <div style="width: 80px; height: 56px; background: rgba(var(--primary-rgb, 107,70,193), 0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border);">
                    <?php if (!empty($gc['thumbnail_path'])): ?>
                    <img src="<?= htmlspecialchars($gc['thumbnail_path']) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
                    <?php else: ?>
                    <i class="fas fa-play-circle" style="font-size: 22px; color: var(--primary);"></i>
                    <?php endif; ?>
                </div>
                <div style="min-width: 0;">
                    <h4 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0 0 6px;"><?= htmlspecialchars($gc['title'] ?? 'Clip') ?></h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-muted);">
                        <span><i class="fas fa-clock" style="margin-right: 4px; color: var(--primary);"></i><?= vr_format_duration($gc['start_time'] ?? 0, $gc['end_time'] ?? 0) ?></span>
                        <?php if (!empty($gc['camera_angle'])): ?>
                        <span><i class="fas fa-video" style="margin-right: 4px; color: var(--primary);"></i><?= htmlspecialchars(ucfirst($gc['camera_angle'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($gc['tag_names'])): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px;">
                        <?php
                        $gc_tag_names = explode(', ', $gc['tag_names']);
                        $gc_tag_colors = explode(',', $gc['tag_colors'] ?? '');
                        foreach ($gc_tag_names as $ti => $tname):
                            $tcolor = vr_safe_color($gc_tag_colors[$ti] ?? '');
                        ?>
                        <span class="status-badge" style="font-size: 10px; padding: 2px 8px; background: <?= htmlspecialchars($tcolor) ?>20; color: <?= htmlspecialchars($tcolor) ?>; border: 1px solid <?= htmlspecialchars($tcolor) ?>40;"><?= htmlspecialchars($tname) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Game List View with filters -->
<div class="filter-box" style="margin-top: 20px;">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Games</div>
    <div class="filter-box-content">
        <form method="GET" action="">
            <input type="hidden" name="page" value="video_review">
            <input type="hidden" name="tab" value="by_game">
            <?php if ($vr_team_id > 0): ?><input type="hidden" name="team_id" value="<?= $vr_team_id ?>"><?php endif; ?>
            <div class="filter-row">
                <div class="filter-field">
                    <label>Opponent</label>
                    <select name="opponent" class="form-select">
                        <option value="">All Opponents</option>
                        <?php foreach ($vr_game_opponents as $opp): ?>
                        <option value="<?= htmlspecialchars($opp) ?>" <?= $vr_opponent === $opp ? 'selected' : '' ?>><?= htmlspecialchars($opp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-field">
                    <label>Month</label>
                    <input type="month" name="month" class="form-input" value="<?= htmlspecialchars($vr_month) ?>">
                </div>
                <div class="filter-field">
                    <label>Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="date_desc" <?= $vr_sort === 'date_desc' ? 'selected' : '' ?>>Date (Newest First)</option>
                        <option value="date_asc" <?= $vr_sort === 'date_asc' ? 'selected' : '' ?>>Date (Oldest First)</option>
                        <option value="opponent_asc" <?= $vr_sort === 'opponent_asc' ? 'selected' : '' ?>>Opponent (A-Z)</option>
                        <option value="opponent_desc" <?= $vr_sort === 'opponent_desc' ? 'selected' : '' ?>>Opponent (Z-A)</option>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="/gameplan.php?page=video_review&tab=by_game<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<?php if (empty($vr_games)): ?>
<div class="empty-state" style="margin-top: 20px;">
    <i class="fas fa-calendar-xmark"></i>
    <h3>No Games Found</h3>
    <p><?= $vr_team_id > 0 ? 'No games found for this team with the current filters.' : 'Select a team above or adjust filters.' ?></p>
</div>
<?php else: ?>
<div style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
    <?php foreach ($vr_games as $game): ?>
    <div class="card">
        <a href="/gameplan.php?page=video_review&tab=by_game&game_id=<?= (int)$game['id'] ?><?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?><?= $vr_opponent !== '' ? '&opponent=' . urlencode($vr_opponent) : '' ?><?= $vr_month !== '' ? '&month=' . urlencode($vr_month) : '' ?><?= $vr_sort !== 'date_desc' ? '&sort=' . urlencode($vr_sort) : '' ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; text-decoration: none; color: var(--text-primary); gap: 16px;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <span style="font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-calendar" style="color: var(--primary);"></i> <?= date('M j, Y', strtotime($game['game_date'])) ?>
                </span>
                <span style="font-size: 15px; font-weight: 700;">
                    <?= htmlspecialchars($game['team_name'] ?? 'Team') ?>
                    <?php if ($game['status'] === 'completed' && $game['home_score'] !== null): ?>
                        <strong><?= (int)$game['home_score'] ?> &ndash; <?= (int)$game['away_score'] ?></strong>
                    <?php else: ?>
                        vs
                    <?php endif; ?>
                    <?= htmlspecialchars($game['opponent_team'] ?? '') ?>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
                <?php
                    $badge_class = 'inactive';
                    if ($game['status'] === 'completed') $badge_class = 'active';
                    elseif ($game['status'] === 'in_progress') $badge_class = 'active';
                ?>
                <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $game['status'] ?? 'scheduled'))) ?></span>
                <span style="font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fas fa-scissors" style="color: var(--primary);"></i> <?= (int)$game['clip_count'] ?> clips
                </span>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($vr_tab === 'scouting'): ?>
<!-- -- Opponent Scouting Tab -- -->
<div class="filter-box" style="margin-top: 20px;">
    <div class="filter-box-header"><i class="fas fa-binoculars"></i> Select Opponent</div>
    <div class="filter-box-content">
        <form method="GET" action="">
            <input type="hidden" name="page" value="video_review">
            <input type="hidden" name="tab" value="scouting">
            <?php if ($vr_team_id > 0): ?><input type="hidden" name="team_id" value="<?= $vr_team_id ?>"><?php endif; ?>
            <div class="filter-row">
                <div class="filter-field" style="grid-column: span 2;">
                    <label>Opponent Team</label>
                    <select name="opponent" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Select Opponent --</option>
                        <?php foreach ($vr_opponents as $opp): ?>
                        <option value="<?= htmlspecialchars($opp) ?>" <?= $vr_opponent === $opp ? 'selected' : '' ?>><?= htmlspecialchars($opp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> View Clips</button>
                <a href="/gameplan.php?page=video_review&tab=scouting<?= $vr_team_id > 0 ? '&team_id=' . $vr_team_id : '' ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<?php if ($vr_opponent === ''): ?>
<div class="empty-state">
    <i class="fas fa-binoculars"></i>
    <h3>Select an Opponent</h3>
    <p>Select an opponent team above to view scouting clips.</p>
</div>
<?php elseif (empty($vr_scout_clips)): ?>
<div class="empty-state">
    <i class="fas fa-film"></i>
    <h3>No Clips Found</h3>
    <p>No clips found for games against <?= htmlspecialchars($vr_opponent) ?>.</p>
</div>
<?php else: ?>
<div class="card" style="margin-top: 20px;">
    <div class="card-header">
        <h3><i class="fas fa-binoculars"></i> Clips vs <?= htmlspecialchars($vr_opponent) ?></h3>
        <span class="status-badge active"><?= count($vr_scout_clips) ?> clips</span>
    </div>
    <div class="card-body" style="padding: 0;">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 20px; padding: 20px;">
            <?php foreach ($vr_scout_clips as $sc): ?>
            <div class="card">
                <div style="position: relative; background: var(--bg-card); border-bottom: 1px solid var(--border); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 8px 8px 0 0;">
                    <?php if (!empty($sc['thumbnail_path'])): ?>
                    <img src="<?= htmlspecialchars($sc['thumbnail_path']) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                    <i class="fas fa-play-circle" style="font-size: 36px; color: var(--text-muted); opacity: 0.4;"></i>
                    <?php endif; ?>
                    <span class="status-badge" style="position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.75); color: #fff; border: none;"><?= vr_format_duration($sc['start_time'] ?? 0, $sc['end_time'] ?? 0) ?></span>
                </div>
                <div class="card-body" style="padding: 14px;">
                    <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 8px; color: var(--text-primary);"><?= htmlspecialchars($sc['title'] ?? 'Clip') ?></h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; font-size: 12px; color: var(--text-muted);">
                        <span><i class="fas fa-calendar" style="margin-right: 4px; color: var(--primary);"></i><?= date('M j, Y', strtotime($sc['game_date'])) ?></span>
                        <?php if (!empty($sc['camera_angle'])): ?>
                        <span><i class="fas fa-video" style="margin-right: 4px; color: var(--primary);"></i><?= htmlspecialchars(ucfirst($sc['camera_angle'])) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($sc['tag_names'])): ?>
                    <div style="display: flex; flex-wrap: wrap; gap: 4px; margin-top: 8px;">
                        <?php foreach (explode(', ', $sc['tag_names']) as $tname): ?>
                        <span class="status-badge" style="font-size: 10px; padding: 2px 8px;"><?= htmlspecialchars($tname) ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php /* end scouting tab */ endif; ?>

<?php if ($vr_tab === 'device_pair'): ?>
<!-- ── Device Pairing Tab ── -->
<?php
// Load existing device pairs for this coach
$vr_pairs = [];
try {
    $stmt = $pdo->prepare("
        SELECT dp.id, dp.pair_code, dp.status, dp.is_frozen, dp.created_at,
               rs.title AS session_title
        FROM vr_device_pairs dp
        LEFT JOIN vr_review_sessions rs ON dp.session_id = rs.id
        WHERE dp.created_by = ? AND dp.status IN ('waiting', 'paired', 'active')
        ORDER BY dp.created_at DESC
    ");
    $stmt->execute([$user_id]);
    $vr_pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('VR pairs: ' . $e->getMessage()); }

// Load review sessions for assignment
$vr_pair_sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, scheduled_date, status
        FROM vr_review_sessions
        WHERE coach_id = ? AND status IN ('scheduled', 'in_progress')
        ORDER BY scheduled_date DESC LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $vr_pair_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('VR pair sessions: ' . $e->getMessage()); }
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-top: 20px;">
    <!-- Controller Panel -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-gamepad"></i> Controller Device</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 13px; color: var(--text-muted, #888); margin-bottom: 16px;">
                The controller device manages video playback, can freeze the viewer display, and enables telestration drawing during review sessions.
            </p>

            <!-- Create New Pair -->
            <div class="card" style="margin-bottom: 16px; border: 1px solid var(--border, #333);">
                <div class="card-header" style="padding: 10px 16px;">
                    <h4 style="font-size: 13px; margin: 0; color: var(--primary, #6B46C1);"><i class="fas fa-plus-circle"></i> Create Pairing Session</h4>
                </div>
                <div class="card-body" style="padding: 16px;">
                    <form method="POST" action="/process_video.php" id="createPairForm">
                        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="create_device_pair">
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-weight: 600; font-size: 12px; color: var(--text-muted, #888); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px;">Review Session</label>
                            <select name="session_id" class="form-select">
                                <option value="">— No Session —</option>
                                <?php foreach ($vr_pair_sessions as $ps): ?>
                                <option value="<?= (int)$ps['id'] ?>"><?= htmlspecialchars($ps['title']) ?> (<?= date('M j', strtotime($ps['scheduled_date'])) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-link"></i> Generate Pair Code</button>
                    </form>
                </div>
            </div>

            <!-- Active Pairs -->
            <?php if (!empty($vr_pairs)): ?>
            <h4 style="font-size: 13px; font-weight: 700; margin-bottom: 10px; color: var(--text-white, #fff);">Active Pairs</h4>
            <?php foreach ($vr_pairs as $pair): ?>
            <div class="card" style="margin-bottom: 8px; padding: 12px 16px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 44px; height: 44px; border-radius: 10px; background: <?= $pair['status'] === 'active' ? 'rgba(16,185,129,.12)' : ($pair['status'] === 'paired' ? 'rgba(59,130,246,.12)' : 'rgba(245,158,11,.12)') ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-<?= $pair['status'] === 'active' ? 'play-circle' : ($pair['status'] === 'paired' ? 'check-circle' : 'clock') ?>" style="color: <?= $pair['status'] === 'active' ? '#10B981' : ($pair['status'] === 'paired' ? '#3B82F6' : '#F59E0B') ?>; font-size: 18px;"></i>
                </div>
                <div style="flex: 1; min-width: 0;">
                    <div style="font-weight: 700; font-size: 18px; letter-spacing: 3px; color: var(--primary-light, #A78BFA); font-family: monospace;"><?= htmlspecialchars($pair['pair_code']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted, #888); margin-top: 2px;">
                        <?= ucfirst(htmlspecialchars($pair['status'])) ?>
                        <?php if (!empty($pair['session_title'])): ?>
                         · <?= htmlspecialchars($pair['session_title']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display: flex; gap: 4px; flex-shrink: 0;">
                    <?php if ($pair['status'] === 'active'): ?>
                    <button type="button" class="btn btn-sm btn-<?= $pair['is_frozen'] ? 'warning' : 'secondary' ?>" onclick="toggleFreeze(<?= (int)$pair['id'] ?>)" title="<?= $pair['is_frozen'] ? 'Unfreeze viewer' : 'Freeze viewer' ?>">
                        <i class="fas fa-<?= $pair['is_frozen'] ? 'play' : 'pause' ?>"></i>
                    </button>
                    <?php endif; ?>
                    <form method="POST" action="/process_video.php" style="display:inline;">
                        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="end_device_pair">
                        <input type="hidden" name="pair_id" value="<?= (int)$pair['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger" title="End pairing"><i class="fas fa-times"></i></button>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state" style="padding: 24px; text-align: center;">
                <i class="fas fa-link" style="font-size: 32px; color: var(--text-muted, #888); display: block; margin-bottom: 10px; opacity: .4;"></i>
                <p style="color: var(--text-muted, #888); font-size: 13px; margin: 0;">No active device pairs. Create one to get started.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Viewer Panel -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tv"></i> Viewer Device</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 13px; color: var(--text-muted, #888); margin-bottom: 16px;">
                The viewer device displays the currently selected video view. It mirrors the controller's selected clip and playback position with 100% time sync. When frozen, telestration can be drawn on the controller and displayed here.
            </p>

            <!-- Join Pair -->
            <div class="card" style="margin-bottom: 16px; border: 1px solid var(--border, #333);">
                <div class="card-header" style="padding: 10px 16px;">
                    <h4 style="font-size: 13px; margin: 0; color: var(--primary, #6B46C1);"><i class="fas fa-sign-in-alt"></i> Join as Viewer</h4>
                </div>
                <div class="card-body" style="padding: 16px;">
                    <form method="POST" action="/process_video.php" id="joinPairForm">
                        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="join_device_pair">
                        <div style="margin-bottom: 12px;">
                            <label style="display: block; font-weight: 600; font-size: 12px; color: var(--text-muted, #888); margin-bottom: 4px; text-transform: uppercase; letter-spacing: .5px;">Pair Code</label>
                            <input type="text" name="pair_code" class="form-input" placeholder="Enter pair code" maxlength="10" required style="text-align: center; font-size: 18px; letter-spacing: 3px; font-family: monospace; text-transform: uppercase;">
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;"><i class="fas fa-tv"></i> Connect as Viewer</button>
                    </form>
                </div>
            </div>

            <!-- Viewer Info -->
            <div class="card" style="padding: 16px; border: 1px solid var(--border, #333);">
                <h4 style="font-size: 13px; font-weight: 700; margin: 0 0 10px; color: var(--text-white, #fff);"><i class="fas fa-info-circle" style="color: var(--primary-light, #A78BFA); margin-right: 6px;"></i>How It Works</h4>
                <div style="font-size: 12px; color: var(--text-muted, #888); line-height: 1.6;">
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">1</span>
                        <span>The <strong>Controller</strong> generates a pair code and controls playback</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">2</span>
                        <span>The <strong>Viewer</strong> enters the code to connect and display the video</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">3</span>
                        <span>The controller can <strong>freeze</strong> the viewer to pause on a frame for telestration</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">4</span>
                        <span>Telestration drawings are synced to the viewer in real-time with <strong>100% time sync</strong></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFreeze(pairId) {
    fetch('/process_video.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_freeze_pair&pair_id=' + pairId + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]')?.value || '')
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) location.reload();
    });
}
</script>
<?php endif; ?>
