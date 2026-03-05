<?php
/**
 * Game Plan - My Clips View (Athlete Only)
 * Restyled with site-standard classes: card, btn, form-input, form-select, filter-box.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

if ($isAnyCoach) {
    echo '<div class="empty-state" style="text-align:center;padding:40px"><i class="fas fa-info-circle" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px"></i><h3>Athlete View</h3><p style="color:var(--text-muted)">This page is for athletes. Visit <a href="/gameplan.php?page=video_review">Video Review</a> to see all clips.</p></div>';
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

if (!function_exists('gp_format_duration')) {
    function gp_format_duration($start, $end) {
        $dur = abs((float)$end - (float)$start);
        return sprintf('%d:%02d', floor($dur / 60), floor($dur % 60));
    }
}
?>

<!-- Page header -->
<div class="page-header">
    <h1><i class="fas fa-scissors"></i> My Clips</h1>
    <p>Video clips you've been tagged in by your coaches</p>
</div>

<!-- Filters -->
<div class="filter-box" style="margin-bottom: 20px;">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Clips</div>
    <div class="filter-box-content">
        <form method="GET" action="" class="filter-row">
            <input type="hidden" name="page" value="my_clips">
            <div class="filter-field">
                <label>Search</label>
                <input type="text" name="search" class="form-input" placeholder="Search clips…" value="<?= htmlspecialchars($mc_search) ?>">
            </div>
            <div class="filter-field">
                <label>Tag</label>
                <select name="tag_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Tags</option>
                    <?php foreach ($mc_tags as $tag): ?>
                    <option value="<?= (int)$tag['id'] ?>" <?= $mc_tag_id === (int)$tag['id'] ? 'selected' : '' ?>><?= htmlspecialchars($tag['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Game</label>
                <select name="game_id" class="form-select" onchange="this.form.submit()">
                    <option value="0">All Games</option>
                    <?php foreach ($mc_games as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= $mc_game_id === (int)$g['id'] ? 'selected' : '' ?>>vs <?= htmlspecialchars($g['opponent_team']) ?> – <?= date('M j', strtotime($g['game_date'])) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>From</label>
                <input type="date" name="date_from" class="form-input" value="<?= htmlspecialchars($mc_date_from) ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-field">
                <label>To</label>
                <input type="date" name="date_to" class="form-input" value="<?= htmlspecialchars($mc_date_to) ?>" onchange="this.form.submit()">
            </div>
            <div class="filter-field filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="/gameplan.php?page=my_clips" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Stats Row -->
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;margin-bottom:24px;">
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="text-align:center;padding:18px;">
            <i class="fas fa-scissors" style="font-size:20px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
            <div style="font-size:24px;font-weight:900;"><?= count($mc_clips) ?></div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Total Clips</div>
        </div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="text-align:center;padding:18px;">
            <i class="fas fa-hockey-puck" style="font-size:20px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
            <div style="font-size:24px;font-weight:900;"><?= count($mc_games) ?></div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Games</div>
        </div>
    </div>
    <div class="card" style="margin-bottom:0;">
        <div class="card-body" style="text-align:center;padding:18px;">
            <i class="fas fa-tags" style="font-size:20px;color:var(--primary-light);margin-bottom:8px;display:block;"></i>
            <div style="font-size:24px;font-weight:900;"><?= count($mc_tags) ?></div>
            <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-top:4px;">Tag Types</div>
        </div>
    </div>
</div>

<!-- Clips Grid -->
<?php if (empty($mc_clips)): ?>
<div class="card">
    <div class="card-body">
        <div class="empty-state" style="text-align:center;padding:40px;">
            <i class="fas fa-scissors" style="font-size:40px;color:var(--text-muted);display:block;margin-bottom:16px;"></i>
            <h3 style="color:var(--text-secondary);">No Clips Yet</h3>
            <p style="color:var(--text-muted);">Your coaches will tag you in clips as they review game footage.</p>
        </div>
    </div>
</div>
<?php else: ?>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
    <?php foreach ($mc_clips as $clip):
        $clip_src_row = ['file_path' => $clip['source_path'] ?? '', 'hls_url' => $clip['source_hls_url'] ?? '', 'hls_status' => $clip['source_hls_status'] ?? ''];
        $clip_play_url = resolveRustfsUrl($pdo, getPreferredVideoUrl($clip_src_row)) ?? '';
        $clip_hls_fallback = !empty($clip['source_hls_url']) ? (resolveRustfsUrl($pdo, $clip['source_hls_url']) ?? '') : '';
    ?>
    <div class="card gp-clip-item" style="margin-bottom:0;cursor:pointer;transition:transform .15s,border-color .2s;" data-clip-id="<?= (int)$clip['id'] ?>" data-source="<?= htmlspecialchars($clip_play_url) ?>"<?php if ($clip_hls_fallback && $clip_hls_fallback !== $clip_play_url): ?> data-hls-url="<?= htmlspecialchars($clip_hls_fallback) ?>"<?php endif; ?>>
        <div style="position:relative;background:#0a0a0f;border-radius:12px 12px 0 0;aspect-ratio:16/9;display:flex;align-items:center;justify-content:center;overflow:hidden;">
            <?php if (!empty($clip['thumbnail_path'])): ?>
            <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $clip['thumbnail_path'])) ?>" alt="" loading="lazy" style="width:100%;height:100%;object-fit:cover;">
            <?php else: ?>
            <i class="fas fa-play-circle" style="font-size:40px;color:var(--text-muted);"></i>
            <?php endif; ?>
            <span style="position:absolute;bottom:8px;right:8px;background:rgba(0,0,0,.75);color:#fff;padding:2px 8px;border-radius:6px;font-size:11px;font-weight:700;">
                <?= gp_format_duration($clip['start_time'] ?? 0, $clip['end_time'] ?? 0) ?>
            </span>
        </div>
        <div class="card-body" style="padding:14px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:6px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                <?= htmlspecialchars($clip['title'] ?? 'Untitled Clip') ?>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:6px;">
                <?php if (!empty($clip['camera_angle'])): ?>
                <span style="font-size:11px;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-video" style="color:var(--primary-light);"></i> <?= htmlspecialchars(ucfirst($clip['camera_angle'])) ?></span>
                <?php endif; ?>
                <span style="font-size:11px;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-calendar" style="color:var(--primary-light);"></i> <?= date('M j, Y', strtotime($clip['created_at'])) ?></span>
                <?php if (!empty($clip['opponent_team'])): ?>
                <span style="font-size:11px;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;"><i class="fas fa-hockey-puck" style="color:var(--primary-light);"></i> vs <?= htmlspecialchars($clip['opponent_team']) ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($clip['tag_names'])): ?>
            <div style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px;">
                <?php foreach (explode(', ', $clip['tag_names']) as $tname): ?>
                <span style="padding:2px 8px;border-radius:12px;font-size:10px;font-weight:600;background:rgba(107,70,193,.12);color:var(--primary-light);border:1px solid rgba(107,70,193,.25);"><?= htmlspecialchars($tname) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Video Player Modal -->
<div class="modal-overlay" id="gpPlayerModal" style="display:none;position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.75);align-items:center;justify-content:center;">
    <div class="modal-content" style="width:90%;max-width:800px;">
        <div class="modal-header">
            <h3 id="gpPlayerTitle"><i class="fas fa-play-circle"></i> Clip</h3>
            <button type="button" class="modal-close" id="gpClosePlayer">&times;</button>
        </div>
        <div class="modal-body">
            <video id="gpModalVideo" controls style="width:100%;max-height:500px;border-radius:8px;background:#000;">
                <source id="gpModalSource" src="">
            </video>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('gpPlayerModal');
    var video = document.getElementById('gpModalVideo');
    var source = document.getElementById('gpModalSource');
    var titleEl = document.getElementById('gpPlayerTitle');
    var gpClipsHls = null;
    var _gpClipHlsFallbackUrl = '';
    var _gpClipHlsFallbackTried = false;

    document.querySelectorAll('.gp-clip-item').forEach(function(card) {
        card.addEventListener('click', function() {
            var src = card.dataset.source || '';
            _gpClipHlsFallbackUrl = card.dataset.hlsUrl || '';
            _gpClipHlsFallbackTried = false;
            var titleNode = card.querySelector('[style*="font-weight:700"]');
            titleEl.innerHTML = '<i class="fas fa-play-circle"></i> ' + (titleNode ? titleNode.textContent.trim() : 'Clip');
            if (gpClipsHls) { gpClipsHls.destroy(); gpClipsHls = null; }
            if (src) {
                if (typeof window.awInitHlsPlayer === 'function') {
                    gpClipsHls = window.awInitHlsPlayer(video, src);
                } else {
                    source.src = src;
                    video.load();
                }
                video.style.display = 'block';
            } else {
                video.style.display = 'none';
            }
            modal.style.display = 'flex';
        });
    });

    // Fallback: if the primary source fails (e.g. 502 because companion
    // deleted the original after HLS transcode), retry with the HLS URL.
    video.addEventListener('error', function() {
        if (_gpClipHlsFallbackUrl && !_gpClipHlsFallbackTried) {
            _gpClipHlsFallbackTried = true;
            if (gpClipsHls) { gpClipsHls.destroy(); gpClipsHls = null; }
            if (typeof window.awInitHlsPlayer === 'function') {
                gpClipsHls = window.awInitHlsPlayer(video, _gpClipHlsFallbackUrl);
            }
        }
    }, true);

    function closeGpClipModal() {
        modal.style.display = 'none';
        if (gpClipsHls) { gpClipsHls.destroy(); gpClipsHls = null; }
        video.pause();
        video.removeAttribute('src');
    }

    document.getElementById('gpClosePlayer').addEventListener('click', closeGpClipModal);
    modal.addEventListener('click', function(e) { if (e.target === modal) closeGpClipModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape' && modal.style.display === 'flex') closeGpClipModal(); });
});
</script>
