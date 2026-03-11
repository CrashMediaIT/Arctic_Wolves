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
require_once __DIR__ . '/../../lib/image_helper.php';

// ── Detect active device pair for telestration sync ───────────
$vr_active_pair_id = 0;
$vr_is_tv_viewer = !empty($_SESSION['tv_pair_id']);
if ($vr_is_tv_viewer) {
    $vr_active_pair_id = (int)$_SESSION['tv_pair_id'];
} elseif ($isAnyCoach) {
    try {
        $pairStmt = $pdo->prepare("
            SELECT id FROM vr_device_pairs
            WHERE status IN ('paired', 'active')
              AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
            LIMIT 1
        ");
        $pairStmt->execute([$user_id, $user_id]);
        $activePair = $pairStmt->fetch(PDO::FETCH_ASSOC);
        if ($activePair) $vr_active_pair_id = (int)$activePair['id'];
    } catch (PDOException $e) { /* ignore */ }
}

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
    $stmt = $pdo->prepare("SELECT id, name FROM teams WHERE is_active = 1 AND is_managed = 1 ORDER BY name");
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
                   vs.file_path AS source_path, vs.hls_url AS source_hls_url,
                   vs.hls_status AS source_hls_status, vs.dash_url AS source_dash_url,
                   vs.dash_manifest_url AS source_dash_manifest_url,
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
                       vs.file_path AS source_path, vs.hls_url AS source_hls_url,
                       vs.hls_status AS source_hls_status, vs.dash_url AS source_dash_url,
                       vs.dash_manifest_url AS source_dash_manifest_url,
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
    <?php foreach ($vr_clips as $clip):
        $clip_src_row = ['file_path' => $clip['source_path'] ?? '', 'hls_url' => $clip['source_hls_url'] ?? '', 'hls_status' => $clip['source_hls_status'] ?? '', 'dash_url' => $clip['source_dash_url'] ?? '', 'dash_manifest_url' => $clip['source_dash_manifest_url'] ?? ''];
        $clip_play_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($clip_src_row)) ?? '';
        $clip_fallback = '';
        if (preg_match('/\.m3u8(\?|&|$)/i', $clip_play_url)) {
            $orig = resolveRustfsUrl($pdo, $clip['source_path'] ?? '') ?? '';
            if ($orig && $orig !== $clip_play_url) $clip_fallback = $orig;
        } else {
            $clip_fallback = resolveRustfsUrl($pdo, $clip['source_hls_url'] ?? '') ?? '';
            if (empty($clip_fallback)) $clip_fallback = deriveFallbackUrl($clip_play_url);
        }
        $clip_dash_url = getDashUrl($clip_src_row);
        if ($clip_dash_url) $clip_dash_url = resolveRustfsUrl($pdo, $clip_dash_url) ?? '';
    ?>
    <div class="card vr-clip-playable" style="cursor:pointer;" data-clip-id="<?= (int)$clip['id'] ?>" data-source="<?= htmlspecialchars($clip_play_url) ?>"<?php if ($clip_fallback && $clip_fallback !== $clip_play_url): ?> data-fallback-url="<?= htmlspecialchars($clip_fallback) ?>"<?php endif; ?><?php if ($clip_dash_url): ?> data-dash-url="<?= htmlspecialchars($clip_dash_url) ?>"<?php endif; ?>>
        <div style="position: relative; background: var(--bg-card); border-bottom: 1px solid var(--border); aspect-ratio: 16/9; display: flex; align-items: center; justify-content: center; overflow: hidden; border-radius: 8px 8px 0 0;">
            <?php if (!empty($clip['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $clip['thumbnail_path'])) ?>" alt="Clip thumbnail" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
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
        <?php foreach ($group_clips as $clip):
            $clip_src_row = ['file_path' => $clip['source_path'] ?? '', 'hls_url' => $clip['source_hls_url'] ?? '', 'hls_status' => $clip['source_hls_status'] ?? '', 'dash_url' => $clip['source_dash_url'] ?? '', 'dash_manifest_url' => $clip['source_dash_manifest_url'] ?? ''];
            $clip_play_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($clip_src_row)) ?? '';
            $clip_fallback = '';
            if (preg_match('/\.m3u8(\?|&|$)/i', $clip_play_url)) {
                $orig = resolveRustfsUrl($pdo, $clip['source_path'] ?? '') ?? '';
                if ($orig && $orig !== $clip_play_url) $clip_fallback = $orig;
            } else {
                $clip_fallback = resolveRustfsUrl($pdo, $clip['source_hls_url'] ?? '') ?? '';
                if (empty($clip_fallback)) $clip_fallback = deriveFallbackUrl($clip_play_url);
            }
            $clip_dash_url = getDashUrl($clip_src_row);
            if ($clip_dash_url) $clip_dash_url = resolveRustfsUrl($pdo, $clip_dash_url) ?? '';
        ?>
        <div class="vr-clip-playable" data-clip-id="<?= (int)$clip['id'] ?>" data-source="<?= htmlspecialchars($clip_play_url) ?>"<?php if ($clip_fallback && $clip_fallback !== $clip_play_url): ?> data-fallback-url="<?= htmlspecialchars($clip_fallback) ?>"<?php endif; ?><?php if ($clip_dash_url): ?> data-dash-url="<?= htmlspecialchars($clip_dash_url) ?>"<?php endif; ?> style="display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 20px; border-bottom: 1px solid var(--border); transition: background .2s; cursor: pointer;" onmouseover="this.style.background='var(--bg-hover, rgba(255,255,255,0.02))'" onmouseout="this.style.background='transparent'">
            <div style="width: 80px; height: 56px; background: rgba(var(--primary-rgb, 107,70,193), 0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border);">
                <?php if (!empty($clip['thumbnail_path'])): ?>
                <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $clip['thumbnail_path'])) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
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
            <?php foreach ($cat_clips as $gc):
                $gc_src_row = ['file_path' => $gc['source_path'] ?? '', 'hls_url' => $gc['source_hls_url'] ?? '', 'hls_status' => $gc['source_hls_status'] ?? '', 'dash_url' => $gc['source_dash_url'] ?? '', 'dash_manifest_url' => $gc['source_dash_manifest_url'] ?? ''];
                $gc_play_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($gc_src_row)) ?? '';
                $gc_fallback = '';
                if (preg_match('/\.m3u8(\?|&|$)/i', $gc_play_url)) {
                    $orig = resolveRustfsUrl($pdo, $gc['source_path'] ?? '') ?? '';
                    if ($orig && $orig !== $gc_play_url) $gc_fallback = $orig;
                } else {
                    $gc_fallback = resolveRustfsUrl($pdo, $gc['source_hls_url'] ?? '') ?? '';
                    if (empty($gc_fallback)) $gc_fallback = deriveFallbackUrl($gc_play_url);
                }
                $gc_dash_url = getDashUrl($gc_src_row);
                if ($gc_dash_url) $gc_dash_url = resolveRustfsUrl($pdo, $gc_dash_url) ?? '';
            ?>
            <div class="vr-clip-playable" data-clip-id="<?= (int)$gc['id'] ?>" data-source="<?= htmlspecialchars($gc_play_url) ?>"<?php if ($gc_fallback && $gc_fallback !== $gc_play_url): ?> data-fallback-url="<?= htmlspecialchars($gc_fallback) ?>"<?php endif; ?><?php if ($gc_dash_url): ?> data-dash-url="<?= htmlspecialchars($gc_dash_url) ?>"<?php endif; ?> style="display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 20px; border-bottom: 1px solid var(--border); transition: background .2s; cursor: pointer;" onmouseover="this.style.background='var(--bg-hover, rgba(255,255,255,0.02))'" onmouseout="this.style.background='transparent'">
                <div style="width: 80px; height: 56px; background: rgba(var(--primary-rgb, 107,70,193), 0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border);">
                    <?php if (!empty($gc['thumbnail_path'])): ?>
                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $gc['thumbnail_path'])) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover; border-radius: 8px;">
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
                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $sc['thumbnail_path'])) ?>" alt="" loading="lazy" style="width: 100%; height: 100%; object-fit: cover;">
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
<?php /* end clips/by_game/scouting tabs */ endif; ?>

<?php if ($vr_tab === 'device_pair'): ?>
<!-- ── Device Pairing Tab ── -->
<?php
// Load existing device pairs for this coach (created by or joined as controller)
$vr_pairs = [];
try {
    $stmt = $pdo->prepare("
        SELECT dp.id, dp.pair_code, dp.status, dp.is_frozen, dp.created_at, dp.created_by,
               rs.title AS session_title
        FROM vr_device_pairs dp
        LEFT JOIN vr_review_sessions rs ON dp.session_id = rs.id
        WHERE dp.status IN ('waiting', 'paired', 'active')
        AND (dp.created_by = ? OR dp.id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
        ORDER BY dp.created_at DESC
    ");
    $stmt->execute([$user_id, $user_id]);
    $vr_pairs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('VR pairs: ' . $e->getMessage()); }

// Load additional controllers for each pair
$vr_pair_controllers = [];
try {
    if (!empty($vr_pairs)) {
        $pair_ids = array_map('intval', array_column($vr_pairs, 'id'));
        $placeholders = implode(',', array_fill(0, count($pair_ids), '?'));
        $stmt = $pdo->prepare("
            SELECT dpc.pair_id, dpc.user_id, u.first_name, u.last_name
            FROM vr_device_pair_controllers dpc
            LEFT JOIN users u ON dpc.user_id = u.id
            WHERE dpc.pair_id IN ($placeholders)
            ORDER BY dpc.joined_at ASC
        ");
        $stmt->execute($pair_ids);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) {
            $rows = decryptUserRows($rows);
        }
        foreach ($rows as $r) {
            $vr_pair_controllers[(int)$r['pair_id']][] = $r;
        }
    }
} catch (PDOException $e) { error_log('VR pair controllers: ' . $e->getMessage()); }

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
                As a controller, everything you do is cast to the paired TV display. Simply use Game Plan as normal — the TV automatically mirrors your current page. Use freeze to pause casting and navigate privately.
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
                        <input type="hidden" name="referrer_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
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
            <?php foreach ($vr_pairs as $pair):
                $is_owner = ((int)($pair['created_by'] ?? 0) === (int)$user_id);
                $extra_controllers = $vr_pair_controllers[(int)$pair['id']] ?? [];
                $pair_is_active = in_array($pair['status'], ['paired', 'active']);
            ?>
            <div class="card" style="margin-bottom: 12px; padding: 0; border: 1px solid var(--border, #333);">
                <div style="padding: 12px 16px;">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 44px; height: 44px; border-radius: 10px; background: <?= $pair['status'] === 'active' ? 'rgba(16,185,129,.12)' : ($pair['status'] === 'paired' ? 'rgba(59,130,246,.12)' : 'rgba(245,158,11,.12)') ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fas fa-<?= $pair['status'] === 'active' ? 'play-circle' : ($pair['status'] === 'paired' ? 'check-circle' : 'clock') ?>" style="color: <?= $pair['status'] === 'active' ? '#10B981' : ($pair['status'] === 'paired' ? '#3B82F6' : '#F59E0B') ?>; font-size: 18px;"></i>
                        </div>
                        <div style="flex: 1; min-width: 0;">
                            <div style="font-weight: 700; font-size: 18px; letter-spacing: 3px; color: var(--primary-light, #A78BFA); font-family: monospace;"><?= htmlspecialchars($pair['pair_code']) ?></div>
                            <div style="font-size: 11px; color: var(--text-muted, #888); margin-top: 2px;">
                                <?= ucfirst(htmlspecialchars($pair['status'])) ?>
                                <?php if (!$is_owner): ?>
                                 · <span style="color: var(--primary-light, #A78BFA);">Joined as controller</span>
                                <?php endif; ?>
                                <?php if (!empty($pair['session_title'])): ?>
                                 · <?= htmlspecialchars($pair['session_title']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="display: flex; gap: 4px; flex-shrink: 0;">
                            <?php if ($pair_is_active): ?>
                            <button type="button" class="btn btn-sm btn-<?= $pair['is_frozen'] ? 'warning' : 'secondary' ?>" onclick="toggleFreeze(<?= (int)$pair['id'] ?>)" title="<?= $pair['is_frozen'] ? 'Unfreeze — TV follows your navigation' : 'Freeze — navigate privately without updating TV' ?>" style="min-width: 90px;">
                                <i class="fas fa-<?= $pair['is_frozen'] ? 'play' : 'snowflake' ?>"></i>
                                <?= $pair['is_frozen'] ? 'Unfreeze' : 'Freeze' ?>
                            </button>
                            <?php endif; ?>
                            <?php if ($is_owner): ?>
                            <form method="POST" action="/process_video.php" style="display:inline;">
                                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                                <input type="hidden" name="action" value="end_device_pair">
                                <input type="hidden" name="pair_id" value="<?= (int)$pair['id'] ?>">
                                <input type="hidden" name="referrer_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
                                <button type="submit" class="btn btn-sm btn-danger" title="End pairing"><i class="fas fa-times"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($extra_controllers)): ?>
                    <div style="margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border, #333);">
                        <span style="font-size: 10px; font-weight: 700; color: var(--text-muted, #888); text-transform: uppercase; letter-spacing: .5px;">Additional Controllers</span>
                        <div style="display: flex; flex-wrap: wrap; gap: 6px; margin-top: 4px;">
                            <?php foreach ($extra_controllers as $ctrl): ?>
                            <span style="font-size: 11px; background: rgba(107,70,193,.1); color: var(--primary-light, #A78BFA); padding: 2px 8px; border-radius: 10px; display: inline-flex; align-items: center; gap: 4px;">
                                <i class="fas fa-gamepad" style="font-size: 9px;"></i> <?= htmlspecialchars(trim(($ctrl['first_name'] ?? '') . ' ' . ($ctrl['last_name'] ?? ''))) ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Casting Status — TV automatically follows controller navigation -->
                <?php if ($pair_is_active): ?>
                <div style="padding: 12px 16px; border-top: 1px solid var(--border, #333); background: rgba(107,70,193,.04);">
                    <div style="font-size: 10px; font-weight: 700; color: var(--text-muted, #888); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px;">
                        <i class="fas fa-broadcast-tower" style="margin-right: 4px;"></i> Casting
                        <?php if ($pair['is_frozen']): ?>
                        <span style="color: var(--warning, #F59E0B); margin-left: 6px;"><i class="fas fa-snowflake"></i> Paused — TV stays on current view</span>
                        <?php else: ?>
                        <span style="color: var(--success, #10B981); margin-left: 6px;"><i class="fas fa-circle" style="font-size: 7px; vertical-align: middle;"></i> Live</span>
                        <?php endif; ?>
                    </div>
                    <p style="font-size: 12px; color: var(--text-muted, #888); margin: 0;">
                        <?php if ($pair['is_frozen']): ?>
                        Casting is paused. Navigate freely without updating the TV. Press <strong>Unfreeze</strong> to resume casting.
                        <?php else: ?>
                        The TV is mirroring your navigation. Currently showing: <strong><?= htmlspecialchars(ucwords(str_replace('_', ' ', $pair['controller_page'] ?? 'home'))) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state" style="padding: 24px; text-align: center;">
                <i class="fas fa-link" style="font-size: 32px; color: var(--text-muted, #888); display: block; margin-bottom: 10px; opacity: .4;"></i>
                <p style="color: var(--text-muted, #888); font-size: 13px; margin: 0;">No active device pairs. Create one to get started.</p>
            </div>
            <?php endif; ?>

            <!-- Join Existing Pair as Controller -->
            <div class="card" style="margin-top: 16px; border: 1px solid var(--border, #333);">
                <div class="card-header" style="padding: 10px 16px;">
                    <h4 style="font-size: 13px; margin: 0; color: var(--primary, #6B46C1);"><i class="fas fa-gamepad"></i> Join as Additional Controller</h4>
                </div>
                <div class="card-body" style="padding: 16px;">
                    <p style="font-size: 12px; color: var(--text-muted, #888); margin: 0 0 12px;">Enter another coach's pair code to join as an additional controller. Multiple coaches can control the same TV viewer.</p>
                    <form method="POST" action="/process_video.php">
                        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                        <input type="hidden" name="action" value="join_as_controller">
                        <input type="hidden" name="referrer_url" value="<?= htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') ?>">
                        <div style="margin-bottom: 12px;">
                            <input type="text" name="pair_code" class="form-input" placeholder="Enter pair code" maxlength="10" required style="text-align: center; font-size: 18px; letter-spacing: 3px; font-family: monospace; text-transform: uppercase;">
                        </div>
                        <button type="submit" class="btn btn-secondary" style="width: 100%;"><i class="fas fa-gamepad"></i> Join as Controller</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Viewer Panel -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tv"></i> Viewer Device (TV)</h3>
        </div>
        <div class="card-body">
            <p style="font-size: 13px; color: var(--text-muted, #888); margin-bottom: 16px;">
                The TV viewer display requires pairing before showing any content. Once paired, everything you do on Game Plan is automatically cast to the TV — just like screen casting. Use the <strong>Game Plan TV</strong> app on your TV or go to <code style="background: rgba(107,70,193,.1); padding: 2px 6px; border-radius: 4px; font-size: 12px;">/gameplan_tv.php</code> on any large display.
            </p>

            <!-- Viewer Info: How It Works -->
            <div class="card" style="padding: 16px; border: 1px solid var(--border, #333);">
                <h4 style="font-size: 13px; font-weight: 700; margin: 0 0 10px; color: var(--text-white, #fff);"><i class="fas fa-info-circle" style="color: var(--primary-light, #A78BFA); margin-right: 6px;"></i>How It Works</h4>
                <div style="font-size: 12px; color: var(--text-muted, #888); line-height: 1.6;">
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">1</span>
                        <span><strong>Generate</strong> a pair code above — the TV must be paired before it shows anything</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">2</span>
                        <span>Open <strong>Game Plan TV</strong> on the TV and enter the pair code</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">3</span>
                        <span><strong>Use Game Plan as normal</strong> — everything you navigate to is automatically cast to the TV</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">4</span>
                        <span><strong>Freeze</strong> to pause casting — navigate privately without updating the TV. Unfreeze to resume</span>
                    </div>
                    <div style="display: flex; align-items: flex-start; gap: 8px;">
                        <span style="background: var(--primary, #6B46C1); color: #fff; width: 20px; height: 20px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 10px; font-weight: 700; flex-shrink: 0;">5</span>
                        <span>The first controller can <strong>invite additional controllers</strong> by sharing the pair code</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleFreeze(pairId) {
    var csrfEl = document.querySelector('input[name="csrf_token"]');
    if (!csrfEl || !csrfEl.value) { if (typeof persistToast === 'function') persistToast('Session expired. Please reload.', 'error'); return; }
    fetch('/process_video.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=toggle_freeze_pair&pair_id=' + pairId + '&csrf_token=' + encodeURIComponent(csrfEl.value)
    }).then(function(r) { return r.json(); }).then(function(data) {
        if (data.success) { persistToast('Viewer freeze state updated', 'success'); location.reload(); }
    });
}
</script>
<?php endif; ?>

<!-- Video Player Modal with Telestration Canvas -->
<div class="modal-overlay" id="vrPlayerModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.85);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:900px;">
        <div class="modal-header">
            <h3 id="vrPlayerTitle"><i class="fas fa-play-circle"></i> Clip</h3>
            <div style="display:flex;align-items:center;gap:8px;">
                <button type="button" class="btn btn-sm btn-secondary" id="vrTeleToggle" title="Toggle telestration" style="display:none;"><i class="fas fa-pencil"></i> Draw</button>
                <button type="button" class="modal-close" id="vrClosePlayer">&times;</button>
            </div>
        </div>
        <div class="modal-body" style="padding:0;">
            <div style="position:relative;background:#000;border-radius:0 0 8px 8px;overflow:hidden;" id="vrPlayerContainer">
                <video id="vrModalVideo" controls style="width:100%;max-height:500px;display:block;background:#000;">
                    <source id="vrModalSource" src="">
                </video>
                <canvas id="vrTeleCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none;cursor:crosshair;display:none;"></canvas>
            </div>
            <!-- Telestration Toolbar (hidden until Draw is toggled) -->
            <div id="vrTeleToolbar" style="display:none;padding:10px 16px;background:var(--bg-card,#16161F);border-top:1px solid var(--border,#333);">
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                    <div style="display:flex;gap:4px;border-right:1px solid var(--border);padding-right:10px;">
                        <button class="btn btn-sm btn-secondary vr-tele-tool active" data-tool="freehand" title="Freehand"><i class="fas fa-pencil"></i></button>
                        <button class="btn btn-sm btn-secondary vr-tele-tool" data-tool="line" title="Line"><i class="fas fa-minus"></i></button>
                        <button class="btn btn-sm btn-secondary vr-tele-tool" data-tool="arrow" title="Arrow"><i class="fas fa-arrow-right"></i></button>
                    </div>
                    <div style="display:flex;gap:4px;border-right:1px solid var(--border);padding-right:10px;">
                        <button class="btn btn-sm vr-tele-color" data-color="#EF4444" style="width:24px;height:24px;padding:0;background:#EF4444;border:2px solid #fff;border-radius:50%;"></button>
                        <button class="btn btn-sm vr-tele-color" data-color="#3B82F6" style="width:24px;height:24px;padding:0;background:#3B82F6;border:2px solid transparent;border-radius:50%;"></button>
                        <button class="btn btn-sm vr-tele-color" data-color="#10B981" style="width:24px;height:24px;padding:0;background:#10B981;border:2px solid transparent;border-radius:50%;"></button>
                        <button class="btn btn-sm vr-tele-color" data-color="#F59E0B" style="width:24px;height:24px;padding:0;background:#F59E0B;border:2px solid transparent;border-radius:50%;"></button>
                        <button class="btn btn-sm vr-tele-color" data-color="#FFFFFF" style="width:24px;height:24px;padding:0;background:#FFFFFF;border:2px solid transparent;border-radius:50%;"></button>
                    </div>
                    <input type="range" id="vrTeleLineWidth" min="1" max="8" value="3" style="width:80px;" title="Line width">
                    <button class="btn btn-sm btn-secondary" id="vrTeleClear" title="Clear drawings"><i class="fas fa-eraser"></i> Clear</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Video Player Modal (same pattern as gp_my_clips.php) ──
    var vrModal = document.getElementById('vrPlayerModal');
    var vrVideo = document.getElementById('vrModalVideo');
    var vrSource = document.getElementById('vrModalSource');
    var vrTitleEl = document.getElementById('vrPlayerTitle');
    var vrHls = null;
    var _vrFallbackUrl = '';
    var _vrFallbackTried = false;

    document.querySelectorAll('.vr-clip-playable').forEach(function(card) {
        card.addEventListener('click', function() {
            var src = card.dataset.source || '';
            _vrFallbackUrl = card.dataset.fallbackUrl || '';
            _vrFallbackTried = false;
            vrVideo._dashTried = false;
            var dashUrl = card.dataset.dashUrl || '';
            if (dashUrl) { vrVideo.setAttribute('data-dash-url', dashUrl); }
            else { vrVideo.removeAttribute('data-dash-url'); }
            var titleNode = card.querySelector('h4') || card.querySelector('[style*="font-weight:700"]');
            vrTitleEl.innerHTML = '<i class="fas fa-play-circle"></i> ' + (titleNode ? titleNode.textContent.trim() : 'Clip');
            if (vrHls) { vrHls.destroy(); vrHls = null; }
            if (src) {
                if (typeof window.awInitHlsPlayer === 'function') {
                    vrHls = window.awInitHlsPlayer(vrVideo, src);
                } else {
                    vrSource.src = src;
                    vrVideo.load();
                }
                vrVideo.style.display = 'block';
            } else {
                vrVideo.style.display = 'none';
            }
            vrModal.style.display = 'flex';
            resizeTeleCanvas();
        });
    });

    vrVideo.addEventListener('error', function(e) {
        if (e && e.target !== vrVideo) return;
        if (_vrFallbackUrl && !_vrFallbackTried) {
            _vrFallbackTried = true;
            if (vrHls) { vrHls.destroy(); vrHls = null; }
            if (typeof window.awInitHlsPlayer === 'function') {
                vrHls = window.awInitHlsPlayer(vrVideo, _vrFallbackUrl);
            }
        } else if (typeof window.awTryDashFallback === 'function' && vrVideo.getAttribute('data-dash-url') && !vrVideo._dashTried) {
            vrVideo._dashTried = true;
            window.awTryDashFallback(vrVideo);
        }
    }, true);

    function closeVrPlayerModal() {
        vrModal.style.display = 'none';
        if (vrHls) { vrHls.destroy(); vrHls = null; }
        if (vrVideo._awDash) { try { vrVideo._awDash.reset(); } catch(e){} vrVideo._awDash = null; }
        vrVideo.pause();
        vrVideo.removeAttribute('src');
        // Reset telestration
        if (teleDrawing) { teleDrawing = false; toggleTelestration(false); }
        var tc = document.getElementById('vrTeleCanvas');
        if (tc) { var ctx = tc.getContext('2d'); ctx.clearRect(0, 0, tc.width, tc.height); }
    }

    document.getElementById('vrClosePlayer').addEventListener('click', closeVrPlayerModal);
    vrModal.addEventListener('click', function(e) { if (e.target === vrModal) closeVrPlayerModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && vrModal.style.display === 'flex') closeVrPlayerModal(); });

    // ── Telestration Canvas on Video ──────────────────────────
    var teleCanvas = document.getElementById('vrTeleCanvas');
    var teleCtx = teleCanvas ? teleCanvas.getContext('2d') : null;
    var teleDrawing = false; // telestration mode active
    var teleIsDrawing = false; // currently drawing a stroke
    var teleTool = 'freehand';
    var teleColor = '#EF4444';
    var teleLineWidth = 3;
    var teleStartX, teleStartY;
    var teleHistory = [];
    var vrPairId = <?= (int)$vr_active_pair_id ?>;
    var vrIsTvViewer = <?= $vr_is_tv_viewer ? 'true' : 'false' ?>;

    function resizeTeleCanvas() {
        if (!teleCanvas) return;
        var container = document.getElementById('vrPlayerContainer');
        if (!container) return;
        var rect = container.getBoundingClientRect();
        teleCanvas.width = Math.round(rect.width);
        teleCanvas.height = Math.round(rect.height);
        redrawTeleHistory();
    }
    window.addEventListener('resize', function() { if (vrModal.style.display === 'flex') resizeTeleCanvas(); });

    function saveTeleHistory() {
        if (!teleCtx) return;
        teleHistory.push(teleCtx.getImageData(0, 0, teleCanvas.width, teleCanvas.height));
        if (teleHistory.length > 30) teleHistory.shift();
    }
    function redrawTeleHistory() {
        if (teleHistory.length > 0 && teleCtx) {
            var last = teleHistory[teleHistory.length - 1];
            var temp = document.createElement('canvas');
            temp.width = last.width; temp.height = last.height;
            temp.getContext('2d').putImageData(last, 0, 0);
            teleCtx.clearRect(0, 0, teleCanvas.width, teleCanvas.height);
            teleCtx.drawImage(temp, 0, 0, teleCanvas.width, teleCanvas.height);
        }
    }

    function toggleTelestration(on) {
        teleDrawing = on;
        var canvas = document.getElementById('vrTeleCanvas');
        var toolbar = document.getElementById('vrTeleToolbar');
        var toggleBtn = document.getElementById('vrTeleToggle');
        if (canvas) { canvas.style.display = on ? 'block' : 'none'; canvas.style.pointerEvents = on ? 'auto' : 'none'; }
        if (toolbar) toolbar.style.display = on ? 'block' : 'none';
        if (toggleBtn) { toggleBtn.classList.toggle('btn-primary', on); toggleBtn.classList.toggle('btn-secondary', !on); }
        if (on) resizeTeleCanvas();
    }

    // Show draw button for coaches with active pair
    var teleToggleBtn = document.getElementById('vrTeleToggle');
    if (teleToggleBtn) {
        teleToggleBtn.style.display = 'inline-flex';
        teleToggleBtn.addEventListener('click', function() { toggleTelestration(!teleDrawing); });
    }

    function getTelePos(e) {
        var rect = teleCanvas.getBoundingClientRect();
        var touch = e.touches ? e.touches[0] : e;
        return { x: (touch.clientX - rect.left) * (teleCanvas.width / rect.width), y: (touch.clientY - rect.top) * (teleCanvas.height / rect.height) };
    }

    function teleOnStart(e) {
        if (!teleDrawing || !teleCtx) return;
        e.preventDefault(); e.stopPropagation();
        teleIsDrawing = true;
        var pos = getTelePos(e);
        teleStartX = pos.x; teleStartY = pos.y;
        if (teleTool === 'freehand') {
            teleCtx.beginPath(); teleCtx.moveTo(pos.x, pos.y);
            teleCtx.strokeStyle = teleColor; teleCtx.lineWidth = teleLineWidth;
            teleCtx.lineCap = 'round'; teleCtx.lineJoin = 'round';
        }
    }
    function teleOnMove(e) {
        if (!teleIsDrawing || !teleCtx) return;
        e.preventDefault(); e.stopPropagation();
        var pos = getTelePos(e);
        if (teleTool === 'freehand') { teleCtx.lineTo(pos.x, pos.y); teleCtx.stroke(); }
        else { redrawTeleHistory(); drawTeleStraight(teleCtx, teleStartX, teleStartY, pos.x, pos.y, teleTool, teleColor, teleLineWidth); }
    }
    function teleOnEnd(e) {
        if (!teleIsDrawing || !teleCtx) return;
        teleIsDrawing = false;
        if (teleTool !== 'freehand') {
            var pos = e.changedTouches ? getTelePos(e.changedTouches[0]) : getTelePos(e);
            redrawTeleHistory();
            drawTeleStraight(teleCtx, teleStartX, teleStartY, pos.x, pos.y, teleTool, teleColor, teleLineWidth);
        }
        saveTeleHistory();
        vrBroadcastTelestration();
    }

    function drawTeleStraight(ctx, x1, y1, x2, y2, tool, color, width) {
        ctx.strokeStyle = color; ctx.lineWidth = width; ctx.lineCap = 'round'; ctx.setLineDash([]);
        ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
        if (tool === 'arrow') {
            var angle = Math.atan2(y2 - y1, x2 - x1);
            var headLen = width * 5;
            ctx.fillStyle = color; ctx.beginPath(); ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headLen * Math.cos(angle - Math.PI / 6), y2 - headLen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headLen * Math.cos(angle + Math.PI / 6), y2 - headLen * Math.sin(angle + Math.PI / 6));
            ctx.closePath(); ctx.fill();
        }
    }

    if (teleCanvas) {
        teleCanvas.addEventListener('mousedown', teleOnStart);
        teleCanvas.addEventListener('mousemove', teleOnMove);
        teleCanvas.addEventListener('mouseup', teleOnEnd);
        teleCanvas.addEventListener('mouseleave', teleOnEnd);
        teleCanvas.addEventListener('touchstart', teleOnStart, { passive: false });
        teleCanvas.addEventListener('touchmove', teleOnMove, { passive: false });
        teleCanvas.addEventListener('touchend', teleOnEnd);
    }

    // Tool/color selection
    document.querySelectorAll('.vr-tele-tool').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.vr-tele-tool').forEach(function(b) { b.classList.remove('active'); b.classList.remove('btn-primary'); b.classList.add('btn-secondary'); });
            btn.classList.add('active'); btn.classList.remove('btn-secondary'); btn.classList.add('btn-primary');
            teleTool = btn.dataset.tool;
        });
    });
    document.querySelectorAll('.vr-tele-color').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.vr-tele-color').forEach(function(b) { b.style.borderColor = 'transparent'; });
            btn.style.borderColor = '#fff';
            teleColor = btn.dataset.color;
        });
    });
    var teleWidthInput = document.getElementById('vrTeleLineWidth');
    if (teleWidthInput) teleWidthInput.addEventListener('input', function() { teleLineWidth = parseInt(this.value) || 3; });
    var teleClearBtn = document.getElementById('vrTeleClear');
    if (teleClearBtn) teleClearBtn.addEventListener('click', function() {
        teleHistory = [];
        if (teleCtx) teleCtx.clearRect(0, 0, teleCanvas.width, teleCanvas.height);
        vrBroadcastTelestration();
    });

    // ── Telestration Broadcast (Controller → TV) ──────────────
    var vrBroadcastTimer = null;
    function vrBroadcastTelestration() {
        if (!vrPairId || vrIsTvViewer || !teleCanvas) return;
        if (vrBroadcastTimer) clearTimeout(vrBroadcastTimer);
        vrBroadcastTimer = setTimeout(function() {
            var dataUrl = teleCanvas.toDataURL('image/png');
            var csrf = document.querySelector('input[name="csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
            var token = csrf ? (csrf.value || csrf.content || '') : '';
            if (!token) return;
            fetch('/process_video.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=broadcast_telestration&pair_id=' + vrPairId
                    + '&canvas_data=' + encodeURIComponent(dataUrl)
                    + '&csrf_token=' + encodeURIComponent(token)
            }).catch(function() { /* best-effort */ });
        }, 500);
    }

    // ── TV Viewer: receive telestration overlay ───────────────
    if (vrPairId && vrIsTvViewer && teleCanvas && teleCtx) {
        teleCanvas.style.display = 'block';
        teleCanvas.style.pointerEvents = 'none';
        var vrTeleSeq = 0;
        function vrPollTelestration() {
            fetch('/api_tv_pair_state.php?pair_id=' + vrPairId + '&include_telestration=1&_=' + Date.now())
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.telestration_seq && data.telestration_seq !== vrTeleSeq) {
                        vrTeleSeq = data.telestration_seq;
                        if (data.telestration_data) {
                            var img = new Image();
                            img.onload = function() {
                                teleCtx.clearRect(0, 0, teleCanvas.width, teleCanvas.height);
                                teleCtx.drawImage(img, 0, 0, teleCanvas.width, teleCanvas.height);
                            };
                            img.src = data.telestration_data;
                        } else {
                            teleCtx.clearRect(0, 0, teleCanvas.width, teleCanvas.height);
                        }
                    }
                }).catch(function() { /* retry next poll */ });
        }
        setInterval(vrPollTelestration, 2000);
    }
});
</script>
