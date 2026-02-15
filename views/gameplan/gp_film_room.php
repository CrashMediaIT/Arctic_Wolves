<?php
/**
 * Game Plan - Film Room View (Coach Only)
 * Three tabs: Upload & Manage / Clip Editor / Multi-Camera
 */

if (!$isAnyCoach) {
    echo '<div class="empty-state"><i class="fas fa-lock"></i><h3>Access Restricted</h3><p>Coach access required to use the Film Room.</p></div>';
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
<div class="page-header">
    <h1><i class="fas fa-video"></i> Film Room</h1>
    <p>Upload video sources, create clips, and manage multi-camera angles</p>
</div>

<!-- Tabs -->
<div class="page-tabs page-tabs-secondary" style="margin-bottom: 20px;">
    <a class="page-tab <?= $vr_tab === 'upload' ? 'active' : '' ?>" href="/gameplan.php?page=film_room&tab=upload">
        <i class="fas fa-cloud-upload-alt"></i> Upload &amp; Manage
    </a>
    <a class="page-tab <?= $vr_tab === 'editor' ? 'active' : '' ?>" href="/gameplan.php?page=film_room&tab=editor">
        <i class="fas fa-scissors"></i> Clip Editor
    </a>
    <a class="page-tab <?= $vr_tab === 'multicam' ? 'active' : '' ?>" href="/gameplan.php?page=film_room&tab=multicam">
        <i class="fas fa-layer-group"></i> Multi-Camera
    </a>
</div>

<?php if ($vr_tab === 'upload'): ?>
<!-- ── Upload & Manage Tab ── -->
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-cloud-upload-alt"></i> Upload Video Source</h3>
    </div>
    <div class="card-body">
        <form method="POST" action="/process_video.php" enctype="multipart/form-data" id="vrUploadForm">
            <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="upload_video_source">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Camera Angle <span style="color:#EF4444;">*</span></label>
                    <select name="camera_angle" class="form-select" required>
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
                <div style="margin-bottom: 16px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Assign to Game</label>
                    <select name="game_id" class="form-select">
                        <option value="">— No Game —</option>
                        <?php foreach ($vr_games as $g): ?>
                        <option value="<?= (int)$g['id'] ?>"><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j', strtotime($g['game_date']))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; margin-bottom:6px;">Team</label>
                <select name="team_id" class="form-select">
                    <option value="">— Select Team —</option>
                    <?php foreach ($vr_teams as $tm): ?>
                    <option value="<?= (int)$tm['id'] ?>"><?= htmlspecialchars($tm['name']) ?><?= !empty($tm['division']) ? ' (' . htmlspecialchars($tm['division']) . ')' : '' ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="margin-bottom: 16px;">
                <label style="display:block; font-weight:600; margin-bottom:6px;">Video File <span style="color:#EF4444;">*</span></label>
                <div id="vrFileArea" style="border: 2px dashed var(--border-color, #ccc); border-radius: 12px; padding: 40px 24px; text-align: center; cursor: pointer;">
                    <i class="fas fa-cloud-upload-alt" style="font-size: 40px; opacity: 0.4; display: block; margin-bottom: 12px;"></i>
                    <p style="margin: 0 0 6px;">Drag &amp; drop video file here or click to browse</p>
                    <small style="color: var(--text-muted, #888);">Supported: MP4, MOV, AVI, WebM (max 500 MB)</small>
                    <input type="file" name="video_file" accept="video/*" id="vrFileInput" style="display:none;" required>
                </div>
                <div id="vrSelectedFile" style="display:none; align-items: center; gap: 10px; padding: 14px; border: 1px solid var(--border-color, #ccc); border-radius: 10px; margin-top: 8px;">
                    <i class="fas fa-file-video" style="font-size: 20px;"></i>
                    <span id="vrFileName" style="flex: 1; font-weight: 500;"></span>
                    <button type="button" class="btn btn-secondary" id="vrRemoveFile" style="padding: 4px 8px;"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; padding-top: 16px; border-top: 1px solid var(--border-color, #eee); margin-top: 16px;">
                <button type="submit" class="btn btn-primary"><i class="fas fa-upload"></i> Upload Source</button>
            </div>
        </form>
    </div>
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
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-film"></i> Uploaded Sources (<?= count($vr_sources) ?>)</h3>
    </div>
    <div class="card-body">
        <?php if (empty($vr_sources)): ?>
        <div class="empty-state"><i class="fas fa-video-slash"></i><h3>No Sources</h3><p>No video sources uploaded yet.</p></div>
        <?php else: ?>
        <?php foreach ($vr_sources as $src): ?>
        <div class="card" style="display: grid; grid-template-columns: 80px 1fr auto; align-items: center; gap: 16px; padding: 14px 18px; margin-bottom: 10px;">
            <div style="width: 80px; height: 56px; background: rgba(107,70,193,.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                <i class="fas fa-film"></i>
            </div>
            <div style="min-width: 0;">
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 6px;"><?= htmlspecialchars($src['filename'] ?? 'Source Video') ?></h4>
                <div style="display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-muted, #888);">
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
            <div>
                <a href="/gameplan.php?page=film_room&tab=editor&source_id=<?= (int)$src['id'] ?>" class="btn btn-secondary" style="font-size: 12px;" title="Create Clips">
                    <i class="fas fa-scissors"></i> Clip
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($vr_tab === 'editor'): ?>
<!-- ── Clip Editor Tab ── -->
<?php if (!$vr_edit_source): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-film"></i> Select a Source Video</h3>
    </div>
    <div class="card-body">
        <?php if (empty($vr_sources)): ?>
        <div class="empty-state"><i class="fas fa-video-slash"></i><h3>No Sources</h3><p>No video sources available. <a href="/gameplan.php?page=film_room&tab=upload">Upload one first.</a></p></div>
        <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px;">
            <?php foreach ($vr_sources as $src): ?>
            <a href="/gameplan.php?page=film_room&tab=editor&source_id=<?= (int)$src['id'] ?>" class="card" style="text-decoration:none; color:inherit; padding: 16px; display: block;">
                <div style="text-align: center; padding: 20px 0; font-size: 32px; opacity: 0.4; margin-bottom: 10px;">
                    <i class="fas fa-film"></i>
                </div>
                <div>
                    <div style="font-weight: 700; margin-bottom: 6px;"><?= htmlspecialchars($src['filename'] ?? 'Source') ?></div>
                    <div style="font-size: 12px; color: var(--text-muted, #888); display: flex; gap: 12px;">
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
    </div>
</div>
<?php else: ?>
<!-- Source selected: show editor -->
<div style="display: grid; grid-template-columns: 1fr 350px; gap: 24px; margin-bottom: 24px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-play-circle"></i> <?= htmlspecialchars($vr_edit_source['filename'] ?? 'Source Video') ?></h3>
        </div>
        <div class="card-body" style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 300px; text-align: center;">
            <i class="fas fa-play-circle" id="vrPlayerPlaceholder" style="font-size: 48px; opacity: 0.3; margin-bottom: 12px; cursor: pointer;"></i>
            <?php if (!empty($vr_edit_source['file_path'])): ?>
            <video id="vrVideoPlayer" controls preload="metadata" style="width:100%; max-height:400px; border-radius:8px; display:none;">
                <source src="<?= htmlspecialchars($vr_edit_source['file_path']) ?>">
            </video>
            <?php endif; ?>
            <small style="color: var(--text-muted, #888);"><?= htmlspecialchars(ucfirst($vr_edit_source['camera_angle'] ?? '')) ?> · <?= !empty($vr_edit_source['duration']) ? gmdate('H:i:s', (int)$vr_edit_source['duration']) : 'Unknown duration' ?></small>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-scissors"></i> Create Clip</h3>
        </div>
        <div class="card-body">
            <form method="POST" action="/process_video.php" id="vrClipForm">
                <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="create_clip">
                <input type="hidden" name="source_id" value="<?= (int)$vr_edit_source['id'] ?>">

                <div style="margin-bottom: 14px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Clip Title <span style="color:#EF4444;">*</span></label>
                    <input type="text" name="title" class="form-input" placeholder="e.g., Power Play Setup" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:6px;">Start Time (sec) <span style="color:#EF4444;">*</span></label>
                        <input type="number" name="start_time" class="form-input" min="0" step="0.1" placeholder="0.0" required>
                    </div>
                    <div>
                        <label style="display:block; font-weight:600; margin-bottom:6px;">End Time (sec) <span style="color:#EF4444;">*</span></label>
                        <input type="number" name="end_time" class="form-input" min="0" step="0.1" placeholder="30.0" required>
                    </div>
                </div>
                <div style="margin-bottom: 14px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Tags</label>
                    <select name="tag_ids[]" class="form-select" multiple size="4">
                        <?php foreach ($vr_all_tags as $tag): ?>
                        <option value="<?= (int)$tag['id'] ?>"><?= htmlspecialchars($tag['name']) ?> (<?= htmlspecialchars($tag['category']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <small style="color: var(--text-muted, #888);">Hold Ctrl/Cmd to select multiple</small>
                </div>
                <div style="margin-bottom: 14px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Tag Athletes</label>
                    <select name="athlete_ids[]" class="form-select" multiple size="4">
                        <?php foreach ($vr_roster as $ath): ?>
                        <option value="<?= (int)$ath['id'] ?>"><?= htmlspecialchars(($ath['first_name'] ?? '') . ' ' . ($ath['last_name'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="margin-bottom: 14px;">
                    <label style="display:block; font-weight:600; margin-bottom:6px;">Description</label>
                    <textarea name="description" class="form-input" rows="3" placeholder="Optional notes…" style="resize: vertical;"></textarea>
                </div>
                <div style="display: flex; justify-content: flex-end; padding-top: 14px; border-top: 1px solid var(--border-color, #eee);">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-scissors"></i> Create Clip</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Existing clips from this source -->
<?php if (!empty($vr_source_clips)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-scissors"></i> Clips from this Source (<?= count($vr_source_clips) ?>)</h3>
    </div>
    <div class="card-body">
        <?php foreach ($vr_source_clips as $ec): ?>
        <div class="card" style="display: grid; grid-template-columns: 80px 1fr; align-items: center; gap: 16px; padding: 14px 18px; margin-bottom: 10px;">
            <div style="width: 80px; height: 56px; background: rgba(107,70,193,.12); border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 22px;">
                <i class="fas fa-scissors"></i>
            </div>
            <div style="min-width: 0;">
                <h4 style="font-size: 14px; font-weight: 700; margin: 0 0 6px;"><?= htmlspecialchars($ec['title'] ?? 'Clip') ?></h4>
                <div style="display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: var(--text-muted, #888);">
                    <span><i class="fas fa-clock"></i> <?= vr_format_duration($ec['start_time'] ?? 0, $ec['end_time'] ?? 0) ?></span>
                    <span><i class="fas fa-play"></i> <?= number_format((float)($ec['start_time'] ?? 0), 1) ?>s – <?= number_format((float)($ec['end_time'] ?? 0), 1) ?>s</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php elseif ($vr_tab === 'multicam'): ?>
<!-- ── Multi-Camera Tab ── -->
<div class="filter-box" style="margin-bottom: 20px;">
    <div class="filter-box-header">
        <i class="fas fa-filter"></i> Select Game
    </div>
    <div class="filter-box-content">
        <form method="GET" class="filter-row">
            <input type="hidden" name="page" value="film_room">
            <input type="hidden" name="tab" value="multicam">
            <div class="filter-field" style="flex: 2;">
                <label>Game</label>
                <select name="mc_game" class="form-select" onchange="this.form.submit()">
                    <option value="">— Select a Game —</option>
                    <?php foreach ($vr_games as $g): ?>
                    <option value="<?= (int)$g['id'] ?>" <?= $vr_mc_game_id === (int)$g['id'] ? 'selected' : '' ?>><?= htmlspecialchars(($g['team_name'] ?? '') . ' vs ' . $g['opponent_team'] . ' – ' . date('M j, Y', strtotime($g['game_date']))) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
</div>

<?php if ($vr_mc_game_id === 0): ?>
<div class="empty-state"><i class="fas fa-layer-group"></i><h3>No Game Selected</h3><p>Select a game to view multi-camera angles.</p></div>
<?php elseif (empty($vr_mc_angles)): ?>
<div class="empty-state"><i class="fas fa-video-slash"></i><h3>No Angles</h3><p>No camera angles uploaded for this game. <a href="/gameplan.php?page=film_room&tab=upload">Upload sources.</a></p></div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-layer-group"></i> Camera Angles (<?= count($vr_mc_angles) ?>)</h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 16px;">
            <?php foreach ($vr_mc_angles as $angle): ?>
            <div class="card" style="overflow: hidden;">
                <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; height: 180px; background: rgba(107,70,193,.06); font-size: 14px; font-weight: 600; gap: 8px;">
                    <i class="fas fa-video" style="font-size: 32px; opacity: 0.4;"></i>
                    <span><?= htmlspecialchars(ucfirst($angle['camera_angle'] ?? 'Unknown')) ?></span>
                </div>
                <div style="padding: 12px 16px; display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--text-muted, #888);">
                    <span><?= htmlspecialchars($angle['filename'] ?? 'Video') ?></span>
                    <?php if (!empty($angle['duration'])): ?>
                    <span><i class="fas fa-clock"></i> <?= gmdate('H:i:s', (int)$angle['duration']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var fileArea = document.getElementById('vrFileArea');
    var fileInput = document.getElementById('vrFileInput');
    var selectedFile = document.getElementById('vrSelectedFile');
    var fileName = document.getElementById('vrFileName');
    var removeBtn = document.getElementById('vrRemoveFile');
    var MAX_SIZE = 500 * 1024 * 1024;

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
                else frToast('File exceeds 500 MB limit');
            }
        });
        fileInput.addEventListener('change', function() {
            if (this.files.length) {
                if (this.files[0].size <= MAX_SIZE) showFile(this.files[0]);
                else { frToast('File exceeds 500 MB limit'); this.value = ''; }
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

    // Video player toggle
    var player = document.getElementById('vrVideoPlayer');
    var placeholder = document.getElementById('vrPlayerPlaceholder');
    if (player && placeholder) {
        placeholder.addEventListener('click', function() { player.style.display = 'block'; placeholder.style.display = 'none'; });
    }
});
</script>
