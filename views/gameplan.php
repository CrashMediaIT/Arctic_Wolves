<?php
/**
 * Game Plan View - Video Review Hub
 *
 * Embeddable view for the Game Plan / Video Review dashboard, rendered inside
 * dashboard.php / pwa.php / pwa_tablet.php navigation shells.
 *
 * Provides navigation to the full ACVideoReview application features:
 *  - Video Review, Calendar, Game Plan, Film Room, Review Sessions (coaches)
 *  - Video Review, My Clips (athletes)
 *  - Permissions (admins)
 *  - Companion Server status and Video Library overview
 */

// Load Game Plan / Video Review app URL and companion settings
$gp_settings = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'gameplan_%'");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $gp_settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) { /* ignore */ }

$gameplan_app_url  = $gp_settings['gameplan_app_url'] ?? '';
$companion_url     = $gp_settings['gameplan_companion_url'] ?? '';
$companion_api_key = $gp_settings['gameplan_companion_api_key'] ?? '';

// Check companion server status
$companionOnline = false;
if ($companion_url && $companion_api_key && !preg_match('/[\r\n]/', $companion_api_key)) {
    $ch = curl_init(rtrim($companion_url, '/') . '/api/health');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['X-API-Key: ' . $companion_api_key, 'Accept: application/json'],
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $data = json_decode($resp, true);
        $companionOnline = (($data['status'] ?? '') === 'ok');
    }
}

// Load recent videos
$recentVideos = [];
try {
    $videoWhere = '';
    $videoParams = [];
    if (!$isAnyCoach) {
        $videoWhere = 'WHERE v.athlete_id = ?';
        $videoParams[] = $user_id;
    }
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.filename, v.file_path, v.duration, v.status,
               v.created_at, v.athlete_id,
               u.first_name as athlete_first_name, u.last_name as athlete_last_name
        FROM videos v
        LEFT JOIN users u ON v.athlete_id = u.id
        $videoWhere
        ORDER BY v.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($videoParams);
    $recentVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }

// Build the app link helper — appends ?page= to the configured app URL
$appLink = function($gpPage) use ($gameplan_app_url) {
    if (!$gameplan_app_url) return '#';
    return htmlspecialchars(rtrim($gameplan_app_url, '/') . '/dashboard.php?page=' . urlencode($gpPage));
};
$appBase = $gameplan_app_url ? htmlspecialchars(rtrim($gameplan_app_url, '/') . '/dashboard.php') : '#';
?>

<style>
    .gp-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
    .gp-status.online { background: rgba(16,185,129,.12); color: #10B981; }
    .gp-status.offline { background: rgba(239,68,68,.12); color: #EF4444; }
    .gp-status .dot { width: 8px; height: 8px; border-radius: 50%; }
    .gp-status.online .dot { background: #10B981; }
    .gp-status.offline .dot { background: #EF4444; }
    .gp-section { margin-bottom: 32px; }
    .gp-section-title { font-size: 18px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
    .gp-section-title i { color: #8B5CF6; }
    .gp-nav-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 14px; margin-bottom: 32px; }
    .gp-nav-card { display: flex; align-items: center; gap: 14px; background: var(--bg-secondary, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 12px; padding: 16px 18px; text-decoration: none; color: #fff; transition: border-color .2s, transform .15s; }
    .gp-nav-card:hover { border-color: rgba(107,70,193,.5); transform: translateY(-2px); color: #fff; }
    .gp-nav-card .gp-nav-icon { width: 42px; height: 42px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
    .gp-nav-card .gp-nav-label { font-size: 14px; font-weight: 700; }
    .gp-nav-card .gp-nav-desc { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
    .gp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .gp-card { background: var(--bg-secondary, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 14px; overflow: hidden; transition: border-color .2s, transform .15s; }
    .gp-card:hover { border-color: rgba(107,70,193,.4); transform: translateY(-2px); }
    .gp-card-thumb { width: 100%; aspect-ratio: 16/9; background: #1a1a24; display: flex; align-items: center; justify-content: center; color: #6B6B7B; font-size: 32px; position: relative; }
    .gp-card-thumb video, .gp-card-thumb img { width: 100%; height: 100%; object-fit: cover; }
    .gp-card-badge { position: absolute; top: 8px; right: 8px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
    .gp-card-badge.pending { background: rgba(59,130,246,.15); color: #3B82F6; }
    .gp-card-badge.reviewed { background: rgba(16,185,129,.15); color: #10B981; }
    .gp-card-body { padding: 14px 16px; }
    .gp-card-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
    .gp-card-meta { font-size: 12px; color: #A8A8B8; display: flex; align-items: center; gap: 12px; }
    .gp-empty { text-align: center; padding: 60px 24px; color: #6B6B7B; }
    .gp-empty i { font-size: 48px; margin-bottom: 16px; display: block; color: #8B5CF6; opacity: .5; }
    .gp-empty p { font-size: 15px; margin-bottom: 20px; }
    .gp-empty a { color: #8B5CF6; text-decoration: none; font-weight: 600; }
    .gp-setup-card { background: linear-gradient(135deg, rgba(107,70,193,.08), rgba(139,92,246,.04)); border: 1px solid rgba(107,70,193,.2); border-radius: 14px; padding: 24px; text-align: center; }
    .gp-setup-card i { font-size: 36px; color: #8B5CF6; margin-bottom: 12px; }
    .gp-setup-card h3 { font-size: 16px; font-weight: 700; margin-bottom: 8px; }
    .gp-setup-card p { font-size: 13px; color: #A8A8B8; margin-bottom: 16px; line-height: 1.5; }
    .gp-btn { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: opacity .2s; }
    .gp-btn-primary { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
    @media (max-width: 640px) {
        .gp-nav-grid { grid-template-columns: 1fr; }
        .gp-grid { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header">
    <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
        <h1 class="page-title"><i class="fas fa-chess-board"></i> Game Plan</h1>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
            <span class="gp-status <?= $companionOnline ? 'online' : 'offline' ?>">
                <span class="dot"></span>
                <?= $companionOnline ? 'Companion Online' : 'Companion Offline' ?>
            </span>
            <?php if ($gameplan_app_url): ?>
            <a href="<?= $appBase ?>" class="gp-btn gp-btn-primary" target="_blank" rel="noopener noreferrer">
                <i class="fas fa-external-link-alt"></i> Open Full App
            </a>
            <?php endif; ?>
        </div>
    </div>
    <p class="page-description">Video review &amp; game planning tools for coaches and athletes</p>
</div>

<!-- Navigation Cards — mirrors ACVideoReview sidebar -->
<div class="gp-section">
    <div class="gp-nav-grid">
        <?php if ($isAnyCoach): ?>
        <a href="<?= $appLink('home') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(107,70,193,.12);color:#8B5CF6;">
                <i class="fas fa-house"></i>
            </div>
            <div>
                <div class="gp-nav-label">Dashboard</div>
                <div class="gp-nav-desc">Video review overview &amp; stats</div>
            </div>
        </a>
        <a href="<?= $appLink('calendar') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(59,130,246,.12);color:#3B82F6;">
                <i class="fas fa-calendar"></i>
            </div>
            <div>
                <div class="gp-nav-label">Calendar</div>
                <div class="gp-nav-desc">Schedule &amp; game calendar</div>
            </div>
        </a>
        <a href="<?= $appLink('video_review') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(234,179,8,.12);color:#EAB308;">
                <i class="fas fa-film"></i>
            </div>
            <div>
                <div class="gp-nav-label">Video Review</div>
                <div class="gp-nav-desc">Review, tag &amp; clip game footage</div>
            </div>
        </a>
        <a href="<?= $appLink('game_plan') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(16,185,129,.12);color:#10B981;">
                <i class="fas fa-chess-board"></i>
            </div>
            <div>
                <div class="gp-nav-label">Game Plan</div>
                <div class="gp-nav-desc">Strategy builder &amp; line assignments</div>
            </div>
        </a>
        <a href="<?= $appLink('film_room') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(239,68,68,.12);color:#EF4444;">
                <i class="fas fa-video"></i>
            </div>
            <div>
                <div class="gp-nav-label">Film Room</div>
                <div class="gp-nav-desc">Play diagrams &amp; telestration</div>
            </div>
        </a>
        <a href="<?= $appLink('review_sessions') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(168,85,247,.12);color:#A855F7;">
                <i class="fas fa-chalkboard-user"></i>
            </div>
            <div>
                <div class="gp-nav-label">Review Sessions</div>
                <div class="gp-nav-desc">Team video review presentations</div>
            </div>
        </a>
        <?php else: ?>
        <!-- Athlete navigation -->
        <a href="<?= $appLink('home') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(107,70,193,.12);color:#8B5CF6;">
                <i class="fas fa-house"></i>
            </div>
            <div>
                <div class="gp-nav-label">Dashboard</div>
                <div class="gp-nav-desc">Your video review overview</div>
            </div>
        </a>
        <a href="<?= $appLink('video_review') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(234,179,8,.12);color:#EAB308;">
                <i class="fas fa-film"></i>
            </div>
            <div>
                <div class="gp-nav-label">Video Review</div>
                <div class="gp-nav-desc">Watch &amp; review game footage</div>
            </div>
        </a>
        <a href="<?= $appLink('my_clips') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(16,185,129,.12);color:#10B981;">
                <i class="fas fa-scissors"></i>
            </div>
            <div>
                <div class="gp-nav-label">My Clips</div>
                <div class="gp-nav-desc">Your saved video clips</div>
            </div>
        </a>
        <?php endif; ?>

        <?php if ($isAdmin): ?>
        <a href="<?= $appLink('permissions') ?>" class="gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
            <div class="gp-nav-icon" style="background:rgba(251,146,60,.12);color:#FB923C;">
                <i class="fas fa-user-shield"></i>
            </div>
            <div>
                <div class="gp-nav-label">Permissions</div>
                <div class="gp-nav-desc">Manage video editing access</div>
            </div>
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!$gameplan_app_url): ?>
<!-- App not configured -->
<div class="gp-section">
    <div class="gp-setup-card">
        <i class="fas fa-link"></i>
        <h3>Video Review App Not Configured</h3>
        <p>Set the Game Plan App URL in settings to enable quick access to the full video review application.</p>
        <?php if ($isAdmin): ?>
        <a href="?page=gameplan_settings" class="gp-btn gp-btn-primary">
            <i class="fas fa-cog"></i> Configure Settings
        </a>
        <?php else: ?>
        <p style="font-size:12px;color:#6B6B7B;">Ask an administrator to configure the Game Plan app URL.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!$companion_url): ?>
<div class="gp-section">
    <div class="gp-setup-card">
        <i class="fas fa-server"></i>
        <h3>Companion Server Not Configured</h3>
        <p>The Video Companion Server handles video encoding and clip extraction.<br>
           Configure it in the admin settings to enable video processing.</p>
        <?php if ($isAdmin): ?>
        <a href="?page=gameplan_settings" class="gp-btn gp-btn-primary">
            <i class="fas fa-cog"></i> Configure Companion
        </a>
        <?php else: ?>
        <p style="font-size:12px;color:#6B6B7B;">Ask an administrator to configure the companion server.</p>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($companion_url && !$companionOnline): ?>
<div style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:16px;margin-bottom:24px;display:flex;align-items:center;gap:12px;">
    <i class="fas fa-exclamation-triangle" style="color:#EF4444;font-size:18px;"></i>
    <div>
        <strong style="font-size:13px;">Companion Server Unreachable</strong>
        <p style="font-size:12px;color:#A8A8B8;margin-top:2px;">Video processing features are unavailable. The companion server at <code style="background:rgba(255,255,255,.06);padding:1px 4px;border-radius:3px;font-size:11px;"><?= htmlspecialchars($companion_url) ?></code> is not responding.</p>
    </div>
</div>
<?php endif; ?>

<!-- Recent Videos -->
<div class="gp-section">
    <div class="gp-section-title"><i class="fas fa-film"></i> Recent Videos</div>

    <?php if (empty($recentVideos)): ?>
    <div class="gp-empty">
        <i class="fas fa-video-slash"></i>
        <p>No videos yet. Upload videos from the video page or record them in the app.</p>
        <a href="?page=video"><i class="fas fa-upload"></i> Go to Video Upload</a>
    </div>
    <?php else: ?>
    <div class="gp-grid">
        <?php foreach ($recentVideos as $video): ?>
        <div class="gp-card">
            <div class="gp-card-thumb">
                <i class="fas fa-play-circle"></i>
                <span class="gp-card-badge <?= ($video['status'] ?? '') === 'reviewed' ? 'reviewed' : 'pending' ?>">
                    <?= htmlspecialchars($video['status'] ?? 'pending') ?>
                </span>
            </div>
            <div class="gp-card-body">
                <div class="gp-card-title"><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></div>
                <div class="gp-card-meta">
                    <?php if (!empty($video['athlete_first_name'])): ?>
                    <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['athlete_first_name'] . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-clock"></i> <?= date('M j, Y', strtotime($video['created_at'])) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
