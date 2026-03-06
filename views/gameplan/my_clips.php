<?php
/**
 * Game Plan - My Clips View (Athlete Only)
 * Personal clips the athlete is tagged in, with filters and player modal.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

if ($isAnyCoach) {
    echo '<div class="gp-empty"><i class="fas fa-info-circle"></i><p>This page is for athletes. Visit <a href="/gameplan.php?page=video_review">Video Review</a> to see all clips.</p></div>';
    return;
}

// ── Filters ───────────────────────────────────────────────────
$mc_search    = $_GET['search'] ?? '';
$mc_tag_id    = isset($_GET['tag_id']) ? (int)$_GET['tag_id'] : 0;
$mc_game_id   = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
$mc_date_from = isset($_GET['date_from']) ? preg_replace('/[^0-9-]/', '', $_GET['date_from']) : '';
$mc_date_to   = isset($_GET['date_to']) ? preg_replace('/[^0-9-]/', '', $_GET['date_to']) : '';

// ── Load tags for filter ──────────────────────────────────────
$mc_tags = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, category FROM vr_tags ORDER BY category, name");
    $stmt->execute();
    $mc_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('MC tags: ' . $e->getMessage()); }

// ── Load games for filter ─────────────────────────────────────
$mc_games = [];
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT gs.id, gs.opponent_team, gs.game_date
        FROM game_schedules gs
        JOIN vr_video_clips c ON c.game_id = gs.id
        JOIN vr_clip_athletes ca ON ca.clip_id = c.id
        WHERE ca.athlete_id = ?
        ORDER BY gs.game_date DESC
    ");
    $stmt->execute([$user_id]);
    $mc_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('MC games: ' . $e->getMessage()); }

// ── Load my clips ─────────────────────────────────────────────
$mc_clips = [];
try {
    $q = "
        SELECT c.id, c.title, c.description, c.start_time, c.end_time,
               c.thumbnail_path, c.created_at,
               vs.filename AS source_filename, vs.file_path AS source_path,
               vs.camera_angle, vs.duration AS source_duration,
               vs.hls_url AS source_hls_url, vs.hls_status AS source_hls_status,
               GROUP_CONCAT(DISTINCT t.name ORDER BY t.name SEPARATOR ', ') AS tag_names,
               gs.opponent_team, gs.game_date
        FROM vr_clip_athletes ca
        JOIN vr_video_clips c ON ca.clip_id = c.id
        LEFT JOIN vr_video_sources vs ON c.source_id = vs.id
        LEFT JOIN vr_clip_tags ct ON ct.clip_id = c.id
        LEFT JOIN vr_tags t ON ct.tag_id = t.id
        LEFT JOIN game_schedules gs ON c.game_id = gs.id
        WHERE ca.athlete_id = ?
    ";
    $params = [$user_id];

    if ($mc_tag_id > 0) {
        $q .= " AND ct.tag_id = ?";
        $params[] = $mc_tag_id;
    }
    if ($mc_game_id > 0) {
        $q .= " AND c.game_id = ?";
        $params[] = $mc_game_id;
    }
    if ($mc_date_from !== '') {
        $q .= " AND c.created_at >= ?";
        $params[] = $mc_date_from . ' 00:00:00';
    }
    if ($mc_date_to !== '') {
        $q .= " AND c.created_at <= ?";
        $params[] = $mc_date_to . ' 23:59:59';
    }
    if ($mc_search !== '') {
        $q .= " AND (c.title LIKE ? OR c.description LIKE ?)";
        $search_param = '%' . $mc_search . '%';
        $params[] = $search_param;
        $params[] = $search_param;
    }
    $q .= " GROUP BY c.id ORDER BY c.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($q);
    $stmt->execute($params);
    $mc_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('MC clips: ' . $e->getMessage()); }

if (!function_exists('vr_format_duration')) {
    function vr_format_duration($start, $end) {
        $dur = abs((float)$end - (float)$start);
        return sprintf('%d:%02d', floor($dur / 60), floor($dur % 60));
    }
}
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-scissors"></i> My Clips</h1>
    <p class="gp-page-desc">Video clips you've been tagged in by your coaches</p>
</div>

<!-- Filters -->
<div class="vr-filter-bar">
    <form method="GET" action="" class="vr-filters">
        <input type="hidden" name="page" value="my_clips">
        <div class="vr-search-wrap">
            <input type="text" name="search" class="vr-input" placeholder="Search clips…" value="<?= htmlspecialchars($mc_search) ?>">
            <button type="submit" class="vr-search-btn"><i class="fas fa-search"></i></button>
        </div>
        <select name="tag_id" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="0">All Tags</option>
            <?php foreach ($mc_tags as $tag): ?>
            <option value="<?= (int)$tag['id'] ?>" <?= $mc_tag_id === (int)$tag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tag['name']) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="game_id" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="0">All Games</option>
            <?php foreach ($mc_games as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= $mc_game_id === (int)$g['id'] ? 'selected' : '' ?>>vs <?= htmlspecialchars($g['opponent_team']) ?> – <?= date('M j', strtotime($g['game_date'])) ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="date_from" class="vr-input vr-date" value="<?= htmlspecialchars($mc_date_from) ?>" title="From" onchange="this.form.submit()">
        <input type="date" name="date_to" class="vr-input vr-date" value="<?= htmlspecialchars($mc_date_to) ?>" title="To" onchange="this.form.submit()">
    </form>
</div>

<!-- Stats -->
<div class="vr-stats-row">
    <div class="vr-stat-card">
        <i class="fas fa-scissors"></i>
        <div class="vr-stat-value"><?= count($mc_clips) ?></div>
        <div class="vr-stat-label">Total Clips</div>
    </div>
    <div class="vr-stat-card">
        <i class="fas fa-hockey-puck"></i>
        <div class="vr-stat-value"><?= count($mc_games) ?></div>
        <div class="vr-stat-label">Games</div>
    </div>
    <div class="vr-stat-card">
        <i class="fas fa-tags"></i>
        <div class="vr-stat-value"><?= count($mc_tags) ?></div>
        <div class="vr-stat-label">Tag Types</div>
    </div>
</div>

<!-- Clips Grid -->
<?php if (empty($mc_clips)): ?>
<div class="gp-empty">
    <i class="fas fa-scissors"></i>
    <p>No clips yet. Your coaches will tag you in clips as they review game footage.</p>
</div>
<?php else: ?>
<div class="gp-grid">
    <?php foreach ($mc_clips as $clip):
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
    <div class="gp-card vr-clip-card" data-clip-id="<?= (int)$clip['id'] ?>" data-source="<?= htmlspecialchars($clip_play_url) ?>"<?php if ($clip_fallback && $clip_fallback !== $clip_play_url): ?> data-fallback-url="<?= htmlspecialchars($clip_fallback) ?>"<?php endif; ?><?php if ($clip_dash_url): ?> data-dash-url="<?= htmlspecialchars($clip_dash_url) ?>"<?php endif; ?>>
        <div class="gp-card-thumb">
            <?php if (!empty($clip['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $clip['thumbnail_path'])) ?>" alt="" loading="lazy">
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
                <?php if (!empty($clip['opponent_team'])): ?>
                <span><i class="fas fa-hockey-puck"></i> vs <?= htmlspecialchars($clip['opponent_team']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($clip['tag_names'])): ?>
            <div class="vr-clip-tags-row">
                <?php foreach (explode(', ', $clip['tag_names']) as $tname): ?>
                <span class="vr-tag-pill"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Video Player Modal -->
<div class="vr-modal-overlay" id="vrPlayerModal">
    <div class="vr-modal-sheet vr-modal-lg">
        <div class="vr-modal-header">
            <span class="vr-modal-title" id="vrPlayerTitle">Clip</span>
            <button type="button" class="vr-modal-close" id="vrClosePlayer">&times;</button>
        </div>
        <div class="vr-modal-body">
            <video id="vrModalVideo" controls style="width:100%;max-height:500px;border-radius:8px;background:#000">
                <source id="vrModalSource" src="">
            </video>
        </div>
    </div>
</div>

<style>
.vr-filter-bar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; padding: 14px 18px; margin-bottom: 20px; }
.vr-filters { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.vr-search-wrap { display: flex; }
.vr-search-wrap .vr-input { border-radius: 8px 0 0 8px; min-width: 180px; }
.vr-search-btn { padding: 0 14px; height: 40px; background: var(--gp-primary); border: none; border-radius: 0 8px 8px 0; color: #fff; cursor: pointer; font-family: 'Inter', sans-serif; }
.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-select { min-width: 130px; }
.vr-date { width: 140px; }

.vr-stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; margin-bottom: 24px; }
.vr-stat-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; padding: 18px; text-align: center; }
.vr-stat-card i { font-size: 20px; color: var(--gp-primary-light); margin-bottom: 8px; }
.vr-stat-value { font-size: 24px; font-weight: 900; color: var(--gp-text); }
.vr-stat-label { font-size: 11px; color: var(--gp-text-dim); text-transform: uppercase; letter-spacing: .5px; margin-top: 4px; }

.vr-clip-card { cursor: pointer; transition: transform .15s, border-color .2s; }
.vr-clip-card:hover { transform: translateY(-3px); border-color: rgba(107,70,193,.5); }
.vr-clip-tags-row { display: flex; flex-wrap: wrap; gap: 4px; margin-top: 6px; }
.vr-tag-pill { padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600; background: rgba(107,70,193,.12); color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.25); }

.vr-modal-overlay { display: none; position: fixed; inset: 0; z-index: 200; background: rgba(0,0,0,.75); align-items: center; justify-content: center; }
.vr-modal-overlay.vr-modal-open { display: flex; }
.vr-modal-sheet { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 16px; width: 90%; max-width: 800px; padding: 24px; animation: vrSlideIn .25s ease-out; }
.vr-modal-lg { max-width: 800px; }
@keyframes vrSlideIn { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.vr-modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.vr-modal-title { font-size: 16px; font-weight: 700; color: var(--gp-text); }
.vr-modal-close { width: 34px; height: 34px; border-radius: 8px; border: 1px solid var(--gp-border); background: transparent; color: var(--gp-text-muted); font-size: 18px; cursor: pointer; display: flex; align-items: center; justify-content: center; padding: 0; font-family: 'Inter', sans-serif; }
.vr-modal-close:hover { background: var(--gp-primary); border-color: var(--gp-primary); color: #fff; }

@media (max-width: 768px) {
    .vr-filters { flex-direction: column; width: 100%; }
    .vr-search-wrap { width: 100%; }
    .vr-search-wrap .vr-input { width: 100%; }
    .vr-select, .vr-date { width: 100%; }
    .vr-stats-row { grid-template-columns: 1fr 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('vrPlayerModal');
    var video = document.getElementById('vrModalVideo');
    var source = document.getElementById('vrModalSource');
    var titleEl = document.getElementById('vrPlayerTitle');
    var clipsHls = null;
    var _clipFallbackUrl = '';
    var _clipFallbackTried = false;

    document.querySelectorAll('.vr-clip-card').forEach(function(card) {
        card.addEventListener('click', function() {
            var src = card.dataset.source || '';
            _clipFallbackUrl = card.dataset.fallbackUrl || '';
            _clipFallbackTried = false;
            video._dashTried = false;
            var dashUrl = card.dataset.dashUrl || '';
            if (dashUrl) { video.setAttribute('data-dash-url', dashUrl); }
            else { video.removeAttribute('data-dash-url'); }
            var title = card.querySelector('.gp-card-title');
            titleEl.textContent = title ? title.textContent : 'Clip';
            if (clipsHls) { clipsHls.destroy(); clipsHls = null; }
            if (src) {
                if (typeof window.awInitHlsPlayer === 'function') {
                    clipsHls = window.awInitHlsPlayer(video, src);
                } else {
                    source.src = src;
                    video.load();
                }
                video.style.display = 'block';
            } else {
                video.style.display = 'none';
            }
            modal.classList.add('vr-modal-open');
        });
    });

    // Fallback: if the primary source fails (e.g. 502 because companion
    // deleted the original after HLS transcode), retry with the HLS URL.
    video.addEventListener('error', function(e) {
        if (e && e.target !== video) return;
        if (_clipFallbackUrl && !_clipFallbackTried) {
            _clipFallbackTried = true;
            if (clipsHls) { clipsHls.destroy(); clipsHls = null; }
            if (typeof window.awInitHlsPlayer === 'function') {
                clipsHls = window.awInitHlsPlayer(video, _clipFallbackUrl);
            }
        } else if (typeof window.awTryDashFallback === 'function' && video.getAttribute('data-dash-url') && !video._dashTried) {
            video._dashTried = true;
            window.awTryDashFallback(video);
        }
    }, true);

    function closeClipModal() {
        modal.classList.remove('vr-modal-open');
        if (clipsHls) { clipsHls.destroy(); clipsHls = null; }
        video.pause();
        video.removeAttribute('src');
    }

    document.getElementById('vrClosePlayer').addEventListener('click', closeClipModal);
    modal.addEventListener('click', function(e) {
        if (e.target === modal) closeClipModal();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.classList.contains('vr-modal-open')) closeClipModal();
    });
});
</script>
