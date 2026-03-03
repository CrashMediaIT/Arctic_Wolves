<?php
/**
 * Game Plan - Film Room View (Coach Only)
 * Three tabs: Upload & Manage / Clip Editor / Multi-Camera
 */
require_once __DIR__ . '/../../lib/image_helper.php';

if (!$isAnyCoach) {
    echo '<div class="gp-empty"><i class="fas fa-lock"></i><p>Coach access required to use the Film Room.</p></div>';
    return;
}

$vr_tab = isset($_GET['tab']) ? preg_replace('/[^a-z_]/', '', $_GET['tab']) : 'upload';
if (!in_array($vr_tab, ['upload', 'editor', 'multicam'])) $vr_tab = 'upload';

// ── Load teams ────────────────────────────────────────────────
$vr_teams = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, division FROM teams WHERE is_active = 1 AND is_managed = 1 ORDER BY name");
    $stmt->execute();
    $vr_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('FR teams: ' . $e->getMessage()); }

// ── Load games for assignment ─────────────────────────────────
$vr_games = [];
try {
    $stmt = $pdo->prepare("
        SELECT gs.id, gs.opponent_team, gs.game_date, t.name AS team_name
        FROM game_schedules gs
        LEFT JOIN teams t ON gs.team_id = t.id
        ORDER BY gs.game_date DESC LIMIT 50
    ");
    $stmt->execute();
    $vr_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { error_log('FR games: ' . $e->getMessage()); }

// ── Upload tab: existing sources ──────────────────────────────
$vr_sources = [];
$ndi_cameras = [];
if ($vr_tab === 'upload') {
    try {
        $stmt = $pdo->prepare("
            SELECT vs.id, vs.filename, vs.file_path, vs.camera_angle, vs.duration,
                   vs.file_size, vs.uploaded_by, vs.created_at,
                   gs.opponent_team, gs.game_date, t.name AS team_name
            FROM vr_video_sources vs
            LEFT JOIN game_schedules gs ON vs.game_id = gs.id
            LEFT JOIN teams t ON vs.team_id = t.id
            ORDER BY vs.created_at DESC LIMIT 50
        ");
        $stmt->execute();
        $vr_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('FR sources: ' . $e->getMessage()); }

    try {
        $stmt = $pdo->prepare("SELECT id, name, ip_address, port, ndi_name, location, is_active FROM ndi_cameras ORDER BY name ASC");
        $stmt->execute();
        $ndi_cameras = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('FR ndi cameras: ' . $e->getMessage()); }
}

// ── Editor tab: source video & roster ─────────────────────────
$vr_edit_source = null;
$vr_source_id = isset($_GET['source_id']) ? (int)$_GET['source_id'] : 0;
$vr_roster = [];
$vr_all_tags = [];
$vr_source_clips = [];

if ($vr_tab === 'editor') {
    try {
        $stmt = $pdo->prepare("SELECT id, name, category, color FROM vr_tags ORDER BY category, name");
        $stmt->execute();
        $vr_all_tags = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('FR tags: ' . $e->getMessage()); }

    if ($vr_source_id > 0) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM vr_video_sources WHERE id = ?");
            $stmt->execute([$vr_source_id]);
            $vr_edit_source = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('FR source: ' . $e->getMessage()); }

        try {
            $stmt = $pdo->prepare("
                SELECT u.id, u.first_name, u.last_name
                FROM users u WHERE u.is_active = 1 AND u.role = 'athlete'
                ORDER BY u.last_name, u.first_name
            ");
            $stmt->execute();
            $vr_roster = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $vr_roster = decryptUserRows($vr_roster);
        } catch (PDOException $e) { error_log('FR roster: ' . $e->getMessage()); }

        try {
            $stmt = $pdo->prepare("
                SELECT c.id, c.title, c.start_time, c.end_time, c.created_at
                FROM vr_video_clips c WHERE c.source_id = ?
                ORDER BY c.start_time
            ");
            $stmt->execute([$vr_source_id]);
            $vr_source_clips = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('FR clips: ' . $e->getMessage()); }
    }

    // List sources for selection
    if (empty($vr_sources)) {
        try {
            $stmt = $pdo->prepare("SELECT id, filename, camera_angle, duration, created_at FROM vr_video_sources ORDER BY created_at DESC LIMIT 50");
            $stmt->execute();
            $vr_sources = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { error_log('FR sources list: ' . $e->getMessage()); }
    }
}

// ── Multi-Camera tab ──────────────────────────────────────────
$vr_mc_game_id = isset($_GET['mc_game']) ? (int)$_GET['mc_game'] : 0;
$vr_mc_angles = [];
if ($vr_tab === 'multicam' && $vr_mc_game_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, filename, file_path, camera_angle, duration
            FROM vr_video_sources WHERE game_id = ?
            ORDER BY camera_angle
        ");
        $stmt->execute([$vr_mc_game_id]);
        $vr_mc_angles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { error_log('FR multicam: ' . $e->getMessage()); }
}

if (!function_exists('vr_format_bytes')) {
    function vr_format_bytes($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        if ($bytes < 1073741824) return round($bytes / 1048576, 1) . ' MB';
        return round($bytes / 1073741824, 2) . ' GB';
    }
}
if (!function_exists('vr_format_duration')) {
    function vr_format_duration($start, $end) {
        $dur = abs((float)$end - (float)$start);
        return sprintf('%d:%02d', floor($dur / 60), floor($dur % 60));
    }
}
?>

<!-- Page header -->
<div class="gp-page-header">
    <h1 class="gp-page-title"><i class="fas fa-video"></i> Film Room</h1>
    <p class="gp-page-desc">Upload video sources, create clips, and manage multi-camera angles</p>
</div>

<!-- Tabs -->
<div class="vr-tabs-bar">
    <div class="vr-tabs">
        <a class="vr-tab <?= $vr_tab === 'upload' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=film_room&tab=upload">
            <i class="fas fa-cloud-upload-alt"></i> Upload &amp; Manage
        </a>
        <a class="vr-tab <?= $vr_tab === 'editor' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=film_room&tab=editor">
            <i class="fas fa-scissors"></i> Clip Editor
        </a>
        <a class="vr-tab <?= $vr_tab === 'multicam' ? 'vr-tab-active' : '' ?>" href="/gameplan.php?page=film_room&tab=multicam">
            <i class="fas fa-layer-group"></i> Multi-Camera
        </a>
    </div>
</div>

<?php if ($vr_tab === 'upload'): ?>
<!-- ── Upload & Manage Tab ── -->
<div class="vr-upload-card">
    <h3><i class="fas fa-cloud-upload-alt"></i> Upload Video Source</h3>
    <form method="POST" action="/process_video.php" enctype="multipart/form-data" class="vr-upload-form" id="vrUploadForm">
        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
        <input type="hidden" name="action" value="upload_video_source">

        <div class="vr-form-row">
            <div class="vr-form-group">
                <label>Camera Angle <span class="vr-req">*</span></label>
                <select name="camera_angle" class="vr-input" required>
                    <option value="">— Select —</option>
                    <option value="wide">Wide Shot</option>
                    <option value="tight">Tight / Close-up</option>
                    <option value="goal_cam">Goal Camera</option>
                    <option value="overhead">Overhead</option>
                    <option value="bench">Bench View</option>
                    <option value="ndi">NDI Camera</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="vr-form-group">
                <label>Assign to Game</label>
                <select name="game_id" class="vr-input">
                    <option value="">— No Game —</option>
                    <?php foreach ($vr_games as $g): ?>
                    <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j', strtotime($g['game_date']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="vr-form-group">
            <label>Team</label>
            <select name="team_id" class="vr-input">
                <option value="">— Select Team —</option>
                <?php foreach ($vr_teams as $tm): ?>
                <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?><?= !empty($tm['division']) ? ' (' . htmlspecialchars($tm['division']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="vr-form-group">
            <label>Video File <span class="vr-req">*</span></label>
            <div class="vr-file-area" id="vrFileArea">
                <i class="fas fa-cloud-upload-alt"></i>
                <p>Drag &amp; drop video file here or click to browse</p>
                <span class="vr-file-hint">Supported: MP4, MKV, MOV, AVI, WebM (max 10 GB)</span>
                <input type="file" name="video_file" accept="video/*" id="vrFileInput" style="display:none;" required>
            </div>
            <div class="vr-selected-file" id="vrSelectedFile" style="display:none;">
                <i class="fas fa-file-video"></i>
                <span id="vrFileName"></span>
                <button type="button" class="vr-btn-remove" id="vrRemoveFile"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="vr-form-actions">
            <button type="submit" class="vr-btn-primary" id="vrUploadSubmitBtn"><i class="fas fa-upload"></i> Upload Source</button>
        </div>
        <!-- Upload progress overlay -->
        <div id="vrUploadProgressOverlay" style="display:none; margin-top:16px; padding:16px; border-radius:10px; background:var(--gp-card, #1a1a2e); border:1px solid var(--gp-border, #333);">
            <div style="font-weight:600; margin-bottom:8px;" id="vrUploadProgressStatus">Uploading video...</div>
            <div style="width:100%; background:rgba(255,255,255,0.1); border-radius:8px; overflow:hidden; height:20px;">
                <div id="vrUploadProgressBar" style="width:0%; height:100%; background:var(--gp-primary-light, #6B46C1); border-radius:8px; transition:width 0.3s;"></div>
            </div>
            <div id="vrUploadProgressPercent" style="text-align:right; font-size:12px; margin-top:4px; color:var(--gp-text-dim, #888);">0%</div>
            <button type="button" class="btn btn-danger" id="vrCancelUploadBtn" style="margin-top: 10px; font-size: 13px;">
                <i class="fas fa-times"></i> Cancel Upload
            </button>
        </div>
    </form>
</div>

<!-- NDI Recording Panel -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-broadcast-tower"></i> NDI Camera Recording</h3>
    </div>
    <div class="card-body">
        <?php if (empty($ndi_cameras)): ?>
        <div style="display: flex; align-items: center; gap: 10px; font-size: 13px;">
            <span style="width: 10px; height: 10px; border-radius: 50%; background: var(--text-muted, #888); display: inline-block;"></span>
            <span>No NDI cameras configured. <a href="/dashboard.php?page=system_tools&tab=ndi_cameras" style="color:var(--primary-light, #8B5CF6);text-decoration:none;font-weight:600;">Configure cameras</a> in System Tools, or check the <a href="/gameplan.php?page=home" style="color:var(--primary-light, #8B5CF6);text-decoration:none;font-weight:600;">companion server status</a>.</span>
        </div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
            <?php foreach ($ndi_cameras as $cam): ?>
            <div class="card" style="margin-bottom: 0; padding: 14px 16px; display: flex; align-items: center; gap: 12px;">
                <div style="width: 40px; height: 40px; border-radius: 10px; background: <?= $cam['is_active'] ? 'rgba(16,185,129,.12)' : 'rgba(239,68,68,.12)' ?>; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <i class="fas fa-video" style="color: <?= $cam['is_active'] ? 'var(--success, #10B981)' : 'var(--danger, #EF4444)' ?>; font-size: 16px;"></i>
                </div>
                <div style="min-width: 0; flex: 1;">
                    <div style="font-weight: 600; font-size: 13px; color: var(--text-white, #fff);"><?= htmlspecialchars($cam['name']) ?></div>
                    <div style="font-size: 11px; color: var(--text-muted, #888); display: flex; gap: 10px; flex-wrap: wrap; margin-top: 2px;">
                        <span><code style="font-size: 10px;"><?= htmlspecialchars($cam['ip_address']) ?>:<?= (int)$cam['port'] ?></code></span>
                        <?php if (!empty($cam['location'])): ?>
                        <span><i class="fas fa-map-marker-alt" style="margin-right: 3px;"></i><?= htmlspecialchars($cam['location']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <span class="badge badge-<?= $cam['is_active'] ? 'success' : 'error' ?>" style="font-size: 10px; flex-shrink: 0;">
                    <?= $cam['is_active'] ? 'Active' : 'Disabled' ?>
                </span>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top: 12px; font-size: 12px; color: var(--text-muted, #888);">
            <a href="/dashboard.php?page=system_tools&tab=ndi_cameras">Manage cameras in System Tools</a>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Uploaded Sources List -->
<h3 class="vr-section-title">Uploaded Sources (<?= count($vr_sources) ?>)</h3>
<?php if (empty($vr_sources)): ?>
<div class="gp-empty"><i class="fas fa-video-slash"></i><p>No video sources uploaded yet.</p></div>
<?php else: ?>
<?php foreach ($vr_sources as $src): ?>
<div class="vr-video-row">
    <div class="vr-thumb"><i class="fas fa-film"></i></div>
    <div class="vr-details">
        <h4><?= htmlspecialchars($src['filename'] ?? 'Source Video') ?></h4>
        <div class="vr-meta">
            <?php if (!empty($src['camera_angle'])): ?>
            <span><i class="fas fa-video"></i> <?= htmlspecialchars(ucfirst($src['camera_angle'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($src['duration'])): ?>
            <span><i class="fas fa-clock"></i> <?= gmdate('H:i:s', (int)$src['duration']) ?></span>
            <?php endif; ?>
            <?php if (!empty($src['file_size'])): ?>
            <span><i class="fas fa-database"></i> <?= vr_format_bytes((int)$src['file_size']) ?></span>
            <?php endif; ?>
            <span><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($src['created_at'])) ?></span>
            <?php if (!empty($src['opponent_team'])): ?>
            <span><i class="fas fa-hockey-puck"></i> vs <?= htmlspecialchars($src['opponent_team']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="vr-actions">
        <a href="/gameplan.php?page=film_room&tab=editor&source_id=<?= (int)$src['id'] ?>" class="vr-btn-sm" title="Create Clips">
            <i class="fas fa-scissors"></i> Clip
        </a>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php elseif ($vr_tab === 'editor'): ?>
<!-- ── Clip Editor Tab ── -->
<?php if (!$vr_edit_source): ?>
<h3 class="vr-section-title">Select a Source Video</h3>
<?php if (empty($vr_sources)): ?>
<div class="gp-empty"><i class="fas fa-video-slash"></i><p>No video sources available. <a href="/gameplan.php?page=film_room&tab=upload">Upload one first.</a></p></div>
<?php else: ?>
<div class="gp-grid">
    <?php foreach ($vr_sources as $src): ?>
    <a href="/gameplan.php?page=film_room&tab=editor&source_id=<?= (int)$src['id'] ?>" class="gp-card" style="text-decoration:none;color:inherit">
        <div class="gp-card-thumb"><i class="fas fa-film"></i></div>
        <div class="gp-card-body">
            <div class="gp-card-title"><?= htmlspecialchars($src['filename'] ?? 'Source') ?></div>
            <div class="gp-card-meta">
                <?php if (!empty($src['camera_angle'])): ?>
                <span><i class="fas fa-video"></i> <?= htmlspecialchars(ucfirst($src['camera_angle'])) ?></span>
                <?php endif; ?>
                <span><i class="fas fa-calendar"></i> <?= date('M j', strtotime($src['created_at'])) ?></span>
            </div>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php else: ?>
<!-- Source selected: show editor -->
<div class="vr-editor-layout">
    <div class="vr-editor-player">
        <div class="vr-player-placeholder">
            <i class="fas fa-play-circle"></i>
            <p><?= htmlspecialchars($vr_edit_source['filename'] ?? 'Source Video') ?></p>
            <?php if (!empty($vr_edit_source['file_path'])): ?>
            <video id="vrVideoPlayer" controls preload="metadata" style="width:100%;max-height:400px;border-radius:8px;display:none">
                <source src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $vr_edit_source['file_path'])) ?>">
            </video>
            <?php endif; ?>
            <span class="vr-file-hint"><?= htmlspecialchars(ucfirst($vr_edit_source['camera_angle'] ?? '')) ?> · <?= !empty($vr_edit_source['duration']) ? gmdate('H:i:s', (int)$vr_edit_source['duration']) : 'Unknown duration' ?></span>
        </div>
    </div>

    <div class="vr-editor-sidebar">
        <h3 class="vr-section-title">Create Clip</h3>
        <form method="POST" action="/process_video.php" id="vrClipForm">
            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_clip">
            <input type="hidden" name="source_id" value="<?= (int)$vr_edit_source['id'] ?>">

            <div class="vr-form-group">
                <label>Clip Title <span class="vr-req">*</span></label>
                <input type="text" name="title" class="vr-input" placeholder="e.g., Power Play Setup" required>
            </div>
            <div class="vr-form-row">
                <div class="vr-form-group">
                    <label>Start Time (sec) <span class="vr-req">*</span></label>
                    <input type="number" name="start_time" class="vr-input" min="0" step="0.1" placeholder="0.0" required>
                </div>
                <div class="vr-form-group">
                    <label>End Time (sec) <span class="vr-req">*</span></label>
                    <input type="number" name="end_time" class="vr-input" min="0" step="0.1" placeholder="30.0" required>
                </div>
            </div>
            <div class="vr-form-group">
                <label>Tags</label>
                <select name="tag_ids[]" class="vr-input" multiple size="4">
                    <?php foreach ($vr_all_tags as $tag): ?>
                    <option value="<?= (int)$tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?> (<?= htmlspecialchars($tag['category']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <span class="vr-file-hint">Hold Ctrl/Cmd to select multiple</span>
            </div>
            <div class="vr-form-group">
                <label>Tag Athletes</label>
                <select name="athlete_ids[]" class="vr-input" multiple size="4">
                    <?php foreach ($vr_roster as $ath): ?>
                    <option value="<?= (int)$ath['id'] ?>"><?= htmlspecialchars(($ath['first_name'] ?? '') . ' ' . ($ath['last_name'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vr-form-group">
                <label>Description</label>
                <textarea name="description" class="vr-input vr-textarea" rows="3" placeholder="Optional notes…"></textarea>
            </div>
            <div class="vr-form-actions">
                <button type="submit" class="vr-btn-primary"><i class="fas fa-scissors"></i> Create Clip</button>
            </div>
        </form>
    </div>
</div>

<!-- Existing clips from this source -->
<?php if (!empty($vr_source_clips)): ?>
<h3 class="vr-section-title" style="margin-top:28px">Clips from this Source (<?= count($vr_source_clips) ?>)</h3>
<?php foreach ($vr_source_clips as $ec): ?>
<div class="vr-video-row">
    <div class="vr-thumb"><i class="fas fa-scissors"></i></div>
    <div class="vr-details">
        <h4><?= htmlspecialchars($ec['title'] ?? 'Clip') ?></h4>
        <div class="vr-meta">
            <span><i class="fas fa-clock"></i> <?= vr_format_duration($ec['start_time'] ?? 0, $ec['end_time'] ?? 0) ?></span>
            <span><i class="fas fa-play"></i> <?= number_format((float)($ec['start_time'] ?? 0), 1) ?>s – <?= number_format((float)($ec['end_time'] ?? 0), 1) ?>s</span>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($vr_tab === 'multicam'): ?>
<!-- ── Multi-Camera Tab ── -->
<div class="vr-filter-bar">
    <form method="GET" class="vr-filters">
        <input type="hidden" name="page" value="film_room">
        <input type="hidden" name="tab" value="multicam">
        <select name="mc_game" class="vr-input vr-select" onchange="this.form.submit()">
            <option value="">— Select a Game —</option>
            <?php foreach ($vr_games as $g): ?>
            <option value="<?= (int)$g['id'] ?>" <?= $vr_mc_game_id === (int)$g['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j, Y', strtotime($g['game_date']))) ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<?php if ($vr_mc_game_id === 0): ?>
<div class="gp-empty"><i class="fas fa-layer-group"></i><p>Select a game to view multi-camera angles.</p></div>
<?php elseif (empty($vr_mc_angles)): ?>
<div class="gp-empty"><i class="fas fa-video-slash"></i><p>No camera angles uploaded for this game. <a href="/gameplan.php?page=film_room&tab=upload">Upload sources.</a></p></div>
<?php else: ?>
<h3 class="vr-section-title">Camera Angles (<?= count($vr_mc_angles) ?>)</h3>
<div class="vr-multicam-grid">
    <?php foreach ($vr_mc_angles as $angle): ?>
    <div class="vr-multicam-card">
        <div class="vr-multicam-player">
            <i class="fas fa-video"></i>
            <span><?= htmlspecialchars(ucfirst($angle['camera_angle'] ?? 'Unknown')) ?></span>
        </div>
        <div class="vr-multicam-info">
            <span><?= htmlspecialchars($angle['filename'] ?? 'Video') ?></span>
            <?php if (!empty($angle['duration'])): ?>
            <span class="vr-meta"><i class="fas fa-clock"></i> <?= gmdate('H:i:s', (int)$angle['duration']) ?></span>
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
.vr-input { background: var(--gp-bg); border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 13px; padding: 9px 14px; font-family: 'Inter', sans-serif; height: 40px; box-sizing: border-box; width: 100%; }
.vr-input:focus { border-color: var(--gp-primary-light); outline: none; }
.vr-textarea { height: auto; min-height: 80px; resize: vertical; }
.vr-select { min-width: 130px; }
.vr-req { color: #EF4444; }

.vr-section-title { font-size: 16px; font-weight: 700; margin-bottom: 18px; padding-bottom: 14px; border-bottom: 1px solid var(--gp-border); display: flex; align-items: center; gap: 10px; color: var(--gp-text); }
.vr-section-title::before { content: ''; width: 4px; height: 20px; background: linear-gradient(180deg, var(--gp-primary), var(--gp-primary-light)); border-radius: 2px; }

.vr-upload-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 28px; margin-bottom: 24px; }
.vr-upload-card h3 { font-size: 18px; font-weight: 700; margin: 0 0 24px; display: flex; align-items: center; gap: 10px; color: var(--gp-text); }
.vr-upload-card h3 i { color: var(--gp-primary-light); }
.vr-form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.vr-form-group { margin-bottom: 18px; }
.vr-form-group label { display: block; font-size: 11px; font-weight: 600; color: var(--gp-text-muted); margin-bottom: 6px; text-transform: uppercase; letter-spacing: .5px; }
.vr-file-area { border: 2px dashed var(--gp-border); border-radius: 12px; padding: 40px 24px; text-align: center; cursor: pointer; transition: border-color .2s; }
.vr-file-area:hover { border-color: var(--gp-primary-light); }
.vr-file-area i { font-size: 40px; color: var(--gp-primary-light); opacity: .4; display: block; margin-bottom: 12px; }
.vr-file-area p { color: var(--gp-text-muted); font-size: 13px; margin: 0 0 6px; }
.vr-file-hint { font-size: 11px; color: var(--gp-text-dim); display: block; margin-top: 4px; }
.vr-selected-file { display: flex; align-items: center; gap: 10px; padding: 14px; background: rgba(107,70,193,.08); border: 1px solid var(--gp-primary); border-radius: 10px; }
.vr-selected-file i { font-size: 20px; color: var(--gp-primary-light); }
.vr-selected-file span { flex: 1; color: var(--gp-text); font-weight: 500; font-size: 13px; }
.vr-btn-remove { background: transparent; border: none; color: var(--gp-text-dim); cursor: pointer; padding: 4px 6px; font-family: 'Inter', sans-serif; }
.vr-btn-remove:hover { color: #EF4444; }
.vr-form-actions { display: flex; justify-content: flex-end; gap: 10px; padding-top: 20px; border-top: 1px solid var(--gp-border); margin-top: 24px; }
.vr-btn-primary { padding: 10px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light)); border: none; color: #fff; display: inline-flex; align-items: center; gap: 7px; font-size: 13px; font-family: 'Inter', sans-serif; transition: opacity .2s; }
.vr-btn-primary:hover { opacity: .9; }

.vr-video-row { display: grid; grid-template-columns: 80px 1fr auto; align-items: center; gap: 16px; padding: 14px 18px; background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; margin-bottom: 10px; transition: border-color .2s; }
.vr-video-row:hover { border-color: rgba(107,70,193,.4); }
.vr-thumb { width: 80px; height: 56px; background: rgba(107,70,193,.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px; color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.2); }
.vr-details { min-width: 0; }
.vr-details h4 { font-size: 14px; font-weight: 700; color: var(--gp-text); margin: 0 0 6px; }
.vr-meta { display: flex; flex-wrap: wrap; gap: 14px; }
.vr-meta span { display: inline-flex; align-items: center; gap: 5px; font-size: 12px; color: var(--gp-text-muted); }
.vr-meta i { color: var(--gp-primary-light); font-size: 11px; }
.vr-actions { display: flex; gap: 6px; }
.vr-btn-sm { padding: 6px 14px; border-radius: 7px; font-size: 12px; font-weight: 600; background: rgba(107,70,193,.12); color: var(--gp-primary-light); border: 1px solid rgba(107,70,193,.25); text-decoration: none; display: inline-flex; align-items: center; gap: 5px; transition: all .2s; }
.vr-btn-sm:hover { background: var(--gp-primary); color: #fff; border-color: var(--gp-primary); }

.vr-ndi-panel { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 20px; margin-bottom: 24px; }
.vr-ndi-status { display: flex; align-items: center; gap: 10px; font-size: 13px; color: var(--gp-text-muted); }
.vr-ndi-status a { color: var(--gp-primary-light); }
.vr-ndi-indicator { width: 10px; height: 10px; border-radius: 50%; background: var(--gp-text-dim); flex-shrink: 0; }

.vr-editor-layout { display: grid; grid-template-columns: 1fr 350px; gap: 24px; margin-bottom: 24px; }
.vr-editor-player { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; overflow: hidden; }
.vr-player-placeholder { display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; padding: 40px; text-align: center; color: var(--gp-text-muted); }
.vr-player-placeholder i { font-size: 48px; color: var(--gp-primary-light); opacity: .3; margin-bottom: 12px; }
.vr-player-placeholder p { font-size: 14px; font-weight: 600; margin: 0 0 8px; color: var(--gp-text); }
.vr-editor-sidebar { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; padding: 24px; }

.vr-multicam-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px; }
.vr-multicam-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 12px; overflow: hidden; }
.vr-multicam-player { display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; background: rgba(107,70,193,.06); color: var(--gp-primary-light); font-size: 14px; font-weight: 600; gap: 8px; }
.vr-multicam-player i { font-size: 32px; opacity: .4; }
.vr-multicam-info { padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--gp-text-muted); }

@media (max-width: 768px) {
    .vr-editor-layout { grid-template-columns: 1fr; }
    .vr-form-row { grid-template-columns: 1fr; }
    .vr-multicam-grid { grid-template-columns: 1fr; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileArea = document.getElementById('vrFileArea');
    var fileInput = document.getElementById('vrFileInput');
    var selectedFile = document.getElementById('vrSelectedFile');
    var fileName = document.getElementById('vrFileName');
    var removeBtn = document.getElementById('vrRemoveFile');
    var MAX_SIZE = 10 * 1024 * 1024 * 1024;

    function frToast(msg) {
        var t = document.createElement('div');
        t.textContent = msg;
        t.style.cssText = 'position:fixed;bottom:30px;left:50%;transform:translateX(-50%);padding:10px 22px;border-radius:10px;font-size:13px;font-weight:600;z-index:300;font-family:Inter,sans-serif;background:rgba(239,68,68,.92);color:#fff;';
        document.body.appendChild(t);
        setTimeout(function() { t.remove(); }, 3000);
    }

    if (fileArea && fileInput) {
        fileArea.addEventListener('click', function() { fileInput.click(); });
        fileArea.addEventListener('dragover', function(e) { e.preventDefault(); fileArea.style.borderColor = 'var(--gp-primary-light)'; });
        fileArea.addEventListener('dragleave', function() { fileArea.style.borderColor = ''; });
        fileArea.addEventListener('drop', function(e) {
            e.preventDefault(); fileArea.style.borderColor = '';
            if (e.dataTransfer.files.length && e.dataTransfer.files[0].type.startsWith('video/')) {
                if (e.dataTransfer.files[0].size <= MAX_SIZE) { fileInput.files = e.dataTransfer.files; showFile(e.dataTransfer.files[0]); }
                else frToast('File exceeds 10 GB limit');
            }
        });
        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                if (this.files[0].size <= MAX_SIZE) showFile(this.files[0]);
                else { frToast('File exceeds 10 GB limit'); this.value = ''; }
            }
        });
    }
    if (removeBtn) {
        removeBtn.addEventListener('click', function() { fileInput.value = ''; selectedFile.style.display = 'none'; fileArea.style.display = 'block'; });
    }
    function showFile(f) {
        var mb = (f.size / 1048576).toFixed(1);
        fileName.textContent = f.name + ' (' + mb + ' MB)';
        selectedFile.style.display = 'flex';
        fileArea.style.display = 'none';
    }

    // AJAX upload handler — direct-to-RustFS via presigned URL (3-step flow)
    var uploadForm = document.getElementById('vrUploadForm');
    var vrCurrentUploadXhr = null;
    if (uploadForm) {
        var vrCancelBtn = document.getElementById('vrCancelUploadBtn');
        if (vrCancelBtn) {
            vrCancelBtn.addEventListener('click', function() {
                if (vrCurrentUploadXhr) {
                    vrCurrentUploadXhr.abort();
                    vrCurrentUploadXhr = null;
                }
                document.getElementById('vrUploadProgressOverlay').style.display = 'none';
                document.getElementById('vrUploadSubmitBtn').disabled = false;
                frToast('Upload cancelled.');
            });
        }

        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var submitBtn = document.getElementById('vrUploadSubmitBtn');
            var overlay = document.getElementById('vrUploadProgressOverlay');
            var bar = document.getElementById('vrUploadProgressBar');
            var percent = document.getElementById('vrUploadProgressPercent');
            var status = document.getElementById('vrUploadProgressStatus');

            if (!fileInput || !fileInput.files.length) {
                frToast('Please select a video file.');
                return;
            }

            var videoFile = fileInput.files[0];
            submitBtn.disabled = true;
            overlay.style.display = 'block';
            bar.style.width = '0%';
            percent.textContent = '0%';
            status.textContent = 'Requesting upload URL...';

            var csrfInput = uploadForm.querySelector('[name="csrf_token"]');
            var csrfToken = csrfInput ? csrfInput.value : '';
            var formMeta = new FormData();
            formMeta.append('action', 'get_video_upload_url');
            formMeta.append('upload_type', 'video_source');
            formMeta.append('csrf_token', csrfToken);
            formMeta.append('file_name', videoFile.name);
            formMeta.append('file_size', videoFile.size);
            formMeta.append('file_type', videoFile.type || 'video/mp4');
            var camAngle = uploadForm.querySelector('[name="camera_angle"]');
            if (camAngle) formMeta.append('camera_angle', camAngle.value);
            var gameId = uploadForm.querySelector('[name="game_id"]');
            if (gameId && gameId.value) formMeta.append('game_id', gameId.value);
            var teamId = uploadForm.querySelector('[name="team_id"]');
            if (teamId && teamId.value) formMeta.append('team_id', teamId.value);

            // Shared state across upload steps
            var uploadNonce = null;
            var proxyUploadUrl = null;
            var proxyToken = null;
            var contentType = null;

            fetch('/process_video.php', { method: 'POST', body: formMeta })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success) throw new Error(data.error || 'Failed to get upload URL');
                    var presignedUrl = data.presigned_url;
                    contentType = data.content_type || videoFile.type || 'application/octet-stream';
                    uploadNonce = data.upload_nonce;
                    proxyUploadUrl = data.proxy_upload_url || null;
                    proxyToken = data.proxy_token || null;

                    // Try proxy upload first (same-origin, avoids CORS/network issues with direct S3)
                    if (proxyUploadUrl && proxyToken) {
                        status.textContent = 'Uploading video...';
                        return new Promise(function(resolve, reject) {
                            var xhr = new XMLHttpRequest();
                            vrCurrentUploadXhr = xhr;
                            xhr.open('PUT', proxyUploadUrl, true);
                            xhr.setRequestHeader('Content-Type', contentType);
                            xhr.setRequestHeader('X-Upload-Token', proxyToken);
                            var uploadStarted = false;
                            var connTimer = setTimeout(function() {
                                if (!uploadStarted) { xhr.abort(); reject(new Error('Upload connection timed out')); }
                            }, 30000);
                            xhr.upload.onprogress = function(ev) {
                                if (!uploadStarted) { uploadStarted = true; clearTimeout(connTimer); }
                                if (ev.lengthComputable) {
                                    var pct = Math.round((ev.loaded / ev.total) * 100);
                                    bar.style.width = pct + '%';
                                    percent.textContent = pct + '%';
                                    status.textContent = pct < 100 ? 'Uploading video... ' + pct + '%' : 'Finalizing upload...';
                                }
                            };
                            xhr.onload = function() {
                                clearTimeout(connTimer);
                                if (xhr.status >= 200 && xhr.status < 300) resolve();
                                else reject(new Error('Upload failed (HTTP ' + xhr.status + ')'));
                            };
                            xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during upload')); };
                            xhr.send(videoFile);
                        });
                    }

                    // Fall back to direct S3 presigned URL if proxy is not available
                    status.textContent = 'Uploading to cloud storage...';
                    return new Promise(function(resolve, reject) {
                        var xhr = new XMLHttpRequest();
                        vrCurrentUploadXhr = xhr;
                        xhr.open('PUT', presignedUrl, true);
                        xhr.setRequestHeader('Content-Type', contentType);
                        var uploadStarted = false;
                        var connTimer = setTimeout(function() {
                            if (!uploadStarted) { xhr.abort(); reject(new Error('Cloud storage connection timed out — check that the S3/RustFS endpoint is reachable from this browser')); }
                        }, 30000);
                        xhr.upload.onprogress = function(ev) {
                            if (!uploadStarted) { uploadStarted = true; clearTimeout(connTimer); }
                            if (ev.lengthComputable) {
                                var pct = Math.round((ev.loaded / ev.total) * 100);
                                bar.style.width = pct + '%';
                                percent.textContent = pct + '%';
                                status.textContent = pct < 100 ? 'Uploading to cloud storage... ' + pct + '%' : 'Finalizing upload...';
                            }
                        };
                        xhr.onload = function() {
                            clearTimeout(connTimer);
                            if (xhr.status >= 200 && xhr.status < 300) resolve();
                            else reject(new Error('Cloud upload failed (HTTP ' + xhr.status + ')'));
                        };
                        xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during upload — ensure the S3/RustFS endpoint is accessible')); };
                        xhr.send(videoFile);
                    });
                })
                .catch(function(uploadErr) {
                    // Primary upload failed — try direct S3 presigned URL as fallback
                    if (!proxyUploadUrl || !proxyToken) throw uploadErr;
                    console.warn('Proxy upload failed:', uploadErr.message, '— trying direct S3');
                    status.textContent = 'Retrying via direct cloud upload...';
                    bar.style.width = '0%';
                    percent.textContent = '0%';

                    return new Promise(function(resolve, reject) {
                        var xhr = new XMLHttpRequest();
                        vrCurrentUploadXhr = xhr;
                        // Fetch a fresh presigned URL — the original may have expired during the failed proxy attempt
                        fetch('/process_video.php', { method: 'POST', body: formMeta })
                            .then(function(r) { return r.json(); })
                            .then(function(data2) {
                                if (!data2.success || !data2.presigned_url) { reject(uploadErr); return; }
                                uploadNonce = data2.upload_nonce;
                                xhr.open('PUT', data2.presigned_url, true);
                                xhr.setRequestHeader('Content-Type', contentType);
                                var uploadStarted = false;
                                var connTimer = setTimeout(function() {
                                    if (!uploadStarted) { xhr.abort(); reject(new Error('Direct cloud upload timed out')); }
                                }, 30000);
                                xhr.upload.onprogress = function(ev) {
                                    if (!uploadStarted) { uploadStarted = true; clearTimeout(connTimer); }
                                    if (ev.lengthComputable) {
                                        var pct = Math.round((ev.loaded / ev.total) * 100);
                                        bar.style.width = pct + '%';
                                        percent.textContent = pct + '%';
                                        status.textContent = pct < 100 ? 'Uploading to cloud... ' + pct + '%' : 'Finalizing upload...';
                                    }
                                };
                                xhr.onload = function() {
                                    clearTimeout(connTimer);
                                    if (xhr.status >= 200 && xhr.status < 300) resolve();
                                    else reject(new Error('Direct upload failed (HTTP ' + xhr.status + ')'));
                                };
                                xhr.onerror = function() { clearTimeout(connTimer); reject(new Error('Network error during direct upload')); };
                                xhr.send(videoFile);
                            })
                            .catch(function() { reject(uploadErr); });
                    });
                })
                .then(function() {
                    status.textContent = 'Confirming upload...';
                    var confirmData = new FormData();
                    confirmData.append('action', 'confirm_video_upload');
                    confirmData.append('csrf_token', csrfToken);
                    confirmData.append('upload_nonce', uploadNonce);
                    return fetch('/process_video.php', { method: 'POST', body: confirmData })
                        .then(function(r) { return r.json(); });
                })
                .then(function(result) {
                    if (result.success) {
                        bar.style.width = '100%';
                        percent.textContent = '100%';
                        status.textContent = 'Upload complete! Redirecting...';
                        window.location.href = result.redirect || '/gameplan.php?page=film_room&tab=upload&success=source_uploaded';
                    } else {
                        throw new Error(result.error || 'Confirmation failed');
                    }
                })
                .catch(function(err) {
                    // Fall back to legacy server-side upload if both direct and proxy fail
                    console.warn('Direct + proxy upload failed, falling back to legacy upload:', err.message);
                    status.textContent = 'Retrying via server...';
                    bar.style.width = '0%';
                    percent.textContent = '0%';
                    var legacyData = new FormData(uploadForm);
                    var legacyXhr = new XMLHttpRequest();
                    vrCurrentUploadXhr = legacyXhr;
                    legacyXhr.open('POST', uploadForm.getAttribute('action'), true);
                    legacyXhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
                    legacyXhr.upload.onprogress = function(ev) {
                        if (ev.lengthComputable) {
                            var pct = Math.round((ev.loaded / ev.total) * 100);
                            bar.style.width = pct + '%';
                            percent.textContent = pct + '%';
                            status.textContent = pct < 100 ? 'Uploading video... ' + pct + '%' : 'Processing upload...';
                        }
                    };
                    legacyXhr.onload = function() {
                        try {
                            var resp = JSON.parse(legacyXhr.responseText);
                            if (resp.success) {
                                bar.style.width = '100%';
                                percent.textContent = '100%';
                                status.textContent = 'Upload complete! Redirecting...';
                                window.location.href = resp.redirect || '/gameplan.php?page=film_room&tab=upload&success=source_uploaded';
                            } else {
                                status.textContent = 'Upload failed: ' + (resp.error || 'Unknown error');
                                submitBtn.disabled = false;
                            }
                        } catch (parseErr) {
                            status.textContent = 'Upload failed: Server error.';
                            submitBtn.disabled = false;
                        }
                    };
                    legacyXhr.onerror = function() {
                        status.textContent = 'Upload failed: Network error.';
                        submitBtn.disabled = false;
                    };
                    legacyXhr.send(legacyData);
                });
        });
    }

    // Video player toggle with HLS support
    var player = document.getElementById('vrVideoPlayer');
    var placeholder = document.querySelector('.vr-player-placeholder > i');
    var filmHls = null;
    if (player && placeholder) {
        placeholder.style.cursor = 'pointer';
        placeholder.addEventListener('click', function() {
            player.style.display = 'block';
            placeholder.style.display = 'none';
            var src = player.querySelector('source')?.src;
            if (src && typeof window.awInitHlsPlayer === 'function' && !filmHls) {
                filmHls = window.awInitHlsPlayer(player, src);
            }
        });
    }
});
</script>
