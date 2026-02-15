<?php
/**
 * Game Plan - Video Review View (Restyled)
 * Three tabs: Clips / By Game / Opponent Scouting
 * Athletes see clips they're tagged in; coaches see all clips.
 *
 * Uses site standard classes: filter-box, card, btn, form-select, etc.
 * Variables $isAnyCoach, $user_id, $pdo are already available.
 * Rendered inside standalone gameplan.php shell.
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

<!-- Sub-tabs -->
<div class="page-tabs page-tabs-secondary" style="flex-wrap: wrap; margin-top: 8px; padding-top: 8px; border-top: 1px solid var(--border);">
    <a href="/gameplan.php?page=video_review&tab=clips" class="page-tab <?= $vr_tab === 'clips' ? 'active' : '' ?>">
        <i class="fas fa-scissors"></i> Clips
    </a>
    <a href="/gameplan.php?page=video_review&tab=by_game" class="page-tab <?= $vr_tab === 'by_game' ? 'active' : '' ?>">
        <i class="fas fa-hockey-puck"></i> By Game
    </a>
    <?php if ($isAnyCoach): ?>
    <a href="/gameplan.php?page=video_review&tab=scouting" class="page-tab <?= $vr_tab === 'scouting' ? 'active' : '' ?>">
        <i class="fas fa-binoculars"></i> Opponent Scouting
    </a>
    <?php endif; ?>
</div>

<?php if ($vr_tab === 'clips'): ?>
<!-- ── Clips Tab ── -->
<div class="filter-box" style="margin-top: 20px;">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Clips</div>
    <div class="filter-box-content">
        <form method="GET" action="">
            <input type="hidden" name="page" value="video_review">
            <input type="hidden" name="tab" value="clips">
            <div class="filter-row">
                <div class="filter-field" style="grid-column: span 2;">
                    <label>Search</label>
                    <div class="search-input-wrapper">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-input" placeholder="Search clips…" value="<?= htmlspecialchars($vr_search) ?>">
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
                <a href="/gameplan.php?page=video_review&tab=clips" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                <a href="/gameplan.php?page=video_review&tab=clips&view=<?= $vr_view_mode === 'grid' ? 'list' : 'grid' ?><?= $vr_search !== '' ? '&search=' . urlencode($vr_search) : '' ?><?= $vr_tag_cat !== '' ? '&tag_cat=' . urlencode($vr_tag_cat) : '' ?><?= $vr_tag_id > 0 ? '&tag_id=' . $vr_tag_id : '' ?><?= $vr_date_from !== '' ? '&date_from=' . urlencode($vr_date_from) : '' ?><?= $vr_date_to !== '' ? '&date_to=' . urlencode($vr_date_to) : '' ?>" class="btn btn-secondary" title="Toggle view">
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
                <?php $colors = explode(',', $clip['tag_colors'] ?? ''); $color = trim($colors[$i] ?? '#6B46C1'); if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) $color = '#6B46C1'; ?>
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
<!-- ── By Game Tab ── -->
<?php if (empty($vr_games)): ?>
<div class="empty-state" style="margin-top: 20px;">
    <i class="fas fa-calendar-xmark"></i>
    <h3>No Games Found</h3>
    <p>No games found in the schedule.</p>
</div>
<?php else: ?>
<div style="display: flex; flex-direction: column; gap: 12px; margin-top: 20px;">
    <?php foreach ($vr_games as $game): ?>
    <div class="card">
        <a href="/gameplan.php?page=video_review&tab=by_game&game_id=<?= (int)$game['id'] ?>" style="display: flex; justify-content: space-between; align-items: center; padding: 16px 20px; text-decoration: none; color: var(--text-primary); gap: 16px;">
            <div style="display: flex; flex-direction: column; gap: 4px;">
                <span style="font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fas fa-calendar" style="color: var(--primary);"></i> <?= date('M j, Y – g:ia', strtotime($game['game_date'])) ?>
                </span>
                <span style="font-size: 15px; font-weight: 700;">
                    <?= htmlspecialchars($game['team_name'] ?? 'Team') ?>
                    <?php if ($game['status'] === 'completed' && $game['home_score'] !== null): ?>
                        <strong><?= (int)$game['home_score'] ?> – <?= (int)$game['away_score'] ?></strong>
                    <?php else: ?>
                        vs
                    <?php endif; ?>
                    <?= htmlspecialchars($game['opponent_team']) ?>
                </span>
            </div>
            <div style="display: flex; align-items: center; gap: 12px; flex-shrink: 0;">
                <?php
                    $badge_class = 'inactive';
                    if ($game['status'] === 'completed') $badge_class = 'active';
                    elseif ($game['status'] === 'in_progress') $badge_class = 'active';
                ?>
                <span class="status-badge <?= $badge_class ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $game['status']))) ?></span>
                <span style="font-size: 12px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px;">
                    <i class="fas fa-scissors" style="color: var(--primary);"></i> <?= (int)$game['clip_count'] ?> clips
                </span>
            </div>
        </a>
        <?php if ($vr_game_id === (int)$game['id']): ?>
        <div class="card-body" style="padding: 0; border-top: 1px solid var(--border);">
            <?php if (!empty($vr_game_clips)): ?>
            <?php foreach ($vr_game_clips as $gc): ?>
            <div style="display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 20px; border-bottom: 1px solid var(--border);">
                <div style="width: 80px; height: 56px; background: rgba(var(--primary-rgb, 107,70,193), 0.12); border-radius: 8px; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border);">
                    <i class="fas fa-play-circle" style="font-size: 22px; color: var(--primary);"></i>
                </div>
                <div style="min-width: 0;">
                    <h4 style="font-size: 14px; font-weight: 700; color: var(--text-primary); margin: 0 0 6px;"><?= htmlspecialchars($gc['title'] ?? 'Clip') ?></h4>
                    <div style="display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-muted);">
                        <span><i class="fas fa-clock" style="margin-right: 4px; color: var(--primary);"></i><?= vr_format_duration($gc['start_time'] ?? 0, $gc['end_time'] ?? 0) ?></span>
                        <?php if (!empty($gc['camera_angle'])): ?>
                        <span><i class="fas fa-video" style="margin-right: 4px; color: var(--primary);"></i><?= htmlspecialchars(ucfirst($gc['camera_angle'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($gc['tag_names'])): ?>
                        <span><i class="fas fa-tags" style="margin-right: 4px; color: var(--primary);"></i><?= htmlspecialchars($gc['tag_names']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php else: ?>
            <div class="empty-state" style="padding: 30px; border-radius: 0;">
                <i class="fas fa-film" style="font-size: 32px;"></i>
                <p>No clips for this game yet.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php elseif ($vr_tab === 'scouting'): ?>
<!-- ── Opponent Scouting Tab ── -->
<div class="filter-box" style="margin-top: 20px;">
    <div class="filter-box-header"><i class="fas fa-binoculars"></i> Select Opponent</div>
    <div class="filter-box-content">
        <form method="GET" action="">
            <input type="hidden" name="page" value="video_review">
            <input type="hidden" name="tab" value="scouting">
            <div class="filter-row">
                <div class="filter-field" style="grid-column: span 2;">
                    <label>Opponent Team</label>
                    <select name="opponent" class="form-select" onchange="this.form.submit()">
                        <option value="">— Select Opponent —</option>
                        <?php foreach ($vr_opponents as $opp): ?>
                        <option value="<?= htmlspecialchars($opp) ?>" <?= $vr_opponent === $opp ? 'selected' : '' ?>><?= htmlspecialchars($opp) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> View Clips</button>
                <a href="/gameplan.php?page=video_review&tab=scouting" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
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
<?php endif; ?>
