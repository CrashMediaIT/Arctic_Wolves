<?php
/**
 * PWA Game Plan - Mobile-native video review hub
 * Purpose-built for mobile phones with full functionality.
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

// Build the app link helper
$appLink = function($gpPage) use ($gameplan_app_url) {
    if (!$gameplan_app_url) return '#';
    return htmlspecialchars(rtrim($gameplan_app_url, '/') . '/dashboard.php?page=' . urlencode($gpPage));
};
$appBase = $gameplan_app_url ? htmlspecialchars(rtrim($gameplan_app_url, '/') . '/dashboard.php') : '#';
?>
<style>
.m-gp { padding: 0; font-family: Inter, sans-serif; }
.m-gp-header {
    padding: 16px;
    display: flex; align-items: center; justify-content: space-between;
    flex-wrap: wrap; gap: 10px;
    border-bottom: 1px solid #2D2D3F;
}
.m-gp-title { font-size: 17px; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 8px; }
.m-gp-title i { color: #8B5CF6; }
.m-gp-status {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
}
.m-gp-status.online { background: rgba(16,185,129,.12); color: #10B981; }
.m-gp-status.offline { background: rgba(239,68,68,.12); color: #EF4444; }
.m-gp-status .dot { width: 7px; height: 7px; border-radius: 50%; }
.m-gp-status.online .dot { background: #10B981; }
.m-gp-status.offline .dot { background: #EF4444; }
.m-gp-body { padding: 16px; }
.m-gp-open-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 14px; margin-bottom: 16px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff;
    border: none; border-radius: 12px; font-size: 14px; font-weight: 600;
    text-decoration: none; min-height: 44px; font-family: Inter, sans-serif;
}
.m-gp-section-title {
    font-size: 13px; font-weight: 700; color: #A8A8B8; text-transform: uppercase;
    letter-spacing: 0.5px; margin: 0 0 10px; padding: 0 2px;
}
.m-gp-nav-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    text-decoration: none; color: #fff; min-height: 44px;
    transition: border-color .15s;
}
.m-gp-nav-card:active { border-color: rgba(107,70,193,.5); }
.m-gp-nav-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.m-gp-nav-label { font-size: 14px; font-weight: 600; }
.m-gp-nav-desc { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-gp-video-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 10px; overflow: hidden;
}
.m-gp-video-thumb {
    width: 100%; aspect-ratio: 16/9; background: #1a1a24;
    display: flex; align-items: center; justify-content: center;
    color: #6B6B7B; font-size: 28px; position: relative;
}
.m-gp-video-badge {
    position: absolute; top: 8px; right: 8px;
    padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700;
    text-transform: uppercase;
}
.m-gp-video-badge.pending { background: rgba(59,130,246,.15); color: #3B82F6; }
.m-gp-video-badge.reviewed { background: rgba(16,185,129,.15); color: #10B981; }
.m-gp-video-body {
    padding: 12px 14px; display: flex; align-items: center; justify-content: space-between;
}
.m-gp-video-info { flex: 1; min-width: 0; }
.m-gp-video-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-gp-video-meta { font-size: 11px; color: #A8A8B8; margin-top: 2px; display: flex; gap: 10px; align-items: center; }
.m-gp-video-del {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.m-gp-video-del:active { background: rgba(239,68,68,.15); color: #EF4444; }
.m-gp-alert {
    border-radius: 10px; padding: 12px 14px; margin-bottom: 12px;
    display: flex; align-items: flex-start; gap: 10px; font-size: 13px;
}
.m-gp-alert-warn { background: rgba(239,68,68,.08); border: 1px solid rgba(239,68,68,.2); }
.m-gp-alert-setup { background: linear-gradient(135deg, rgba(107,70,193,.08), rgba(139,92,246,.04)); border: 1px solid rgba(107,70,193,.2); }
.m-gp-setup-card { text-align: center; padding: 24px 16px; }
.m-gp-setup-card i { font-size: 32px; color: #8B5CF6; margin-bottom: 10px; }
.m-gp-setup-card h4 { font-size: 15px; font-weight: 700; color: #fff; margin: 0 0 6px; }
.m-gp-setup-card p { font-size: 12px; color: #A8A8B8; margin: 0 0 14px; line-height: 1.5; }
.m-gp-btn-sm {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 18px; border-radius: 10px; font-size: 13px; font-weight: 600;
    text-decoration: none; border: none; cursor: pointer;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-gp-empty { text-align: center; padding: 32px 16px; color: #6B6B7B; }
.m-gp-empty i { font-size: 32px; display: block; margin-bottom: 10px; color: #8B5CF6; opacity: .5; }
.m-gp-empty p { font-size: 14px; margin: 0 0 12px; }
.m-gp-empty a { color: #8B5CF6; text-decoration: none; font-weight: 600; font-size: 13px; }
</style>

<?= csrfTokenInput() ?>

<div class="m-gp">
    <div class="m-gp-header">
        <span class="m-gp-title"><i class="fas fa-chess-board"></i> Game Plan</span>
        <span class="m-gp-status <?= $companionOnline ? 'online' : 'offline' ?>">
            <span class="dot"></span>
            <?= $companionOnline ? 'Online' : 'Offline' ?>
        </span>
    </div>

    <div class="m-gp-body">
        <?php if ($gameplan_app_url): ?>
        <a href="<?= $appBase ?>" class="m-gp-open-btn" target="_blank" rel="noopener noreferrer">
            <i class="fas fa-external-link-alt"></i> Open Full App
        </a>
        <?php endif; ?>

        <?php if (!$gameplan_app_url): ?>
        <div class="m-gp-alert m-gp-alert-setup">
            <div class="m-gp-setup-card">
                <i class="fas fa-link"></i>
                <h4>App Not Configured</h4>
                <p>Set the Game Plan App URL to enable video review features.</p>
                <?php if ($isAdmin): ?>
                <a href="?page=gameplan_settings" class="m-gp-btn-sm"><i class="fas fa-cog"></i> Configure</a>
                <?php else: ?>
                <p style="font-size:11px;color:#6B6B7B;">Ask an admin to configure settings.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$companion_url): ?>
        <div class="m-gp-alert m-gp-alert-setup">
            <div class="m-gp-setup-card">
                <i class="fas fa-server"></i>
                <h4>Companion Not Configured</h4>
                <p>Configure the companion server for video processing.</p>
                <?php if ($isAdmin): ?>
                <a href="?page=gameplan_settings" class="m-gp-btn-sm"><i class="fas fa-cog"></i> Configure</a>
                <?php else: ?>
                <p style="font-size:11px;color:#6B6B7B;">Ask an admin to configure settings.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($companion_url && !$companionOnline): ?>
        <div class="m-gp-alert m-gp-alert-warn">
            <i class="fas fa-exclamation-triangle" style="color:#EF4444;font-size:16px;margin-top:1px;"></i>
            <div>
                <strong style="font-size:13px;">Companion Unreachable</strong>
                <p style="font-size:11px;color:#A8A8B8;margin:2px 0 0;">Video processing is unavailable.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Navigation Cards -->
        <div style="margin-bottom:20px;">
            <div class="m-gp-section-title">Navigation</div>
            <?php if ($isAnyCoach): ?>
            <a href="<?= $appLink('home') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(107,70,193,.12);color:#8B5CF6;"><i class="fas fa-house"></i></div>
                <div><div class="m-gp-nav-label">Dashboard</div><div class="m-gp-nav-desc">Overview &amp; stats</div></div>
            </a>
            <a href="<?= $appLink('calendar') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(59,130,246,.12);color:#3B82F6;"><i class="fas fa-calendar"></i></div>
                <div><div class="m-gp-nav-label">Calendar</div><div class="m-gp-nav-desc">Schedule &amp; games</div></div>
            </a>
            <a href="<?= $appLink('video_review') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(234,179,8,.12);color:#EAB308;"><i class="fas fa-film"></i></div>
                <div><div class="m-gp-nav-label">Video Review</div><div class="m-gp-nav-desc">Tag &amp; clip footage</div></div>
            </a>
            <a href="<?= $appLink('game_plan') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(16,185,129,.12);color:#10B981;"><i class="fas fa-chess-board"></i></div>
                <div><div class="m-gp-nav-label">Game Plan</div><div class="m-gp-nav-desc">Strategy &amp; line assignments</div></div>
            </a>
            <a href="<?= $appLink('film_room') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(239,68,68,.12);color:#EF4444;"><i class="fas fa-video"></i></div>
                <div><div class="m-gp-nav-label">Film Room</div><div class="m-gp-nav-desc">Play diagrams &amp; telestration</div></div>
            </a>
            <a href="<?= $appLink('review_sessions') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(168,85,247,.12);color:#A855F7;"><i class="fas fa-chalkboard-user"></i></div>
                <div><div class="m-gp-nav-label">Review Sessions</div><div class="m-gp-nav-desc">Team video presentations</div></div>
            </a>
            <?php else: ?>
            <a href="<?= $appLink('home') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(107,70,193,.12);color:#8B5CF6;"><i class="fas fa-house"></i></div>
                <div><div class="m-gp-nav-label">Dashboard</div><div class="m-gp-nav-desc">Your video overview</div></div>
            </a>
            <a href="<?= $appLink('video_review') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(234,179,8,.12);color:#EAB308;"><i class="fas fa-film"></i></div>
                <div><div class="m-gp-nav-label">Video Review</div><div class="m-gp-nav-desc">Watch game footage</div></div>
            </a>
            <a href="<?= $appLink('my_clips') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(16,185,129,.12);color:#10B981;"><i class="fas fa-scissors"></i></div>
                <div><div class="m-gp-nav-label">My Clips</div><div class="m-gp-nav-desc">Your saved video clips</div></div>
            </a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
            <a href="<?= $appLink('permissions') ?>" class="m-gp-nav-card" <?= $gameplan_app_url ? 'target="_blank" rel="noopener noreferrer"' : '' ?>>
                <div class="m-gp-nav-icon" style="background:rgba(251,146,60,.12);color:#FB923C;"><i class="fas fa-user-shield"></i></div>
                <div><div class="m-gp-nav-label">Permissions</div><div class="m-gp-nav-desc">Manage video access</div></div>
            </a>
            <a href="?page=gameplan_settings" class="m-gp-nav-card">
                <div class="m-gp-nav-icon" style="background:rgba(107,70,193,.12);color:#8B5CF6;"><i class="fas fa-cog"></i></div>
                <div><div class="m-gp-nav-label">Settings</div><div class="m-gp-nav-desc">Companion &amp; storage config</div></div>
            </a>
            <?php endif; ?>
        </div>

        <!-- Recent Videos -->
        <div style="margin-bottom:20px;">
            <div class="m-gp-section-title">Recent Videos</div>
            <?php if (empty($recentVideos)): ?>
            <div class="m-gp-empty">
                <i class="fas fa-video-slash"></i>
                <p>No videos yet</p>
                <a href="?page=video"><i class="fas fa-upload"></i> Go to Video Upload</a>
            </div>
            <?php else: ?>
                <?php foreach ($recentVideos as $video):
                    $canDelete = $isAnyCoach || $isAdmin || ((int)($video['athlete_id'] ?? 0) === (int)$user_id);
                ?>
                <div class="m-gp-video-card" id="mgv-<?= (int)$video['id'] ?>">
                    <div class="m-gp-video-thumb">
                        <i class="fas fa-play-circle"></i>
                        <span class="m-gp-video-badge <?= ($video['status'] ?? '') === 'reviewed' ? 'reviewed' : 'pending' ?>">
                            <?= htmlspecialchars($video['status'] ?? 'pending') ?>
                        </span>
                    </div>
                    <div class="m-gp-video-body">
                        <div class="m-gp-video-info">
                            <div class="m-gp-video-title"><?= htmlspecialchars($video['title'] ?? 'Untitled Video') ?></div>
                            <div class="m-gp-video-meta">
                                <?php if (!empty($video['athlete_first_name'])): ?>
                                <span><i class="fas fa-user"></i> <?= htmlspecialchars($video['athlete_first_name'] . ' ' . ($video['athlete_last_name'] ?? '')) ?></span>
                                <?php endif; ?>
                                <span><i class="fas fa-clock"></i> <?= date('M j', strtotime($video['created_at'])) ?></span>
                            </div>
                        </div>
                        <?php if ($canDelete): ?>
                        <button class="m-gp-video-del" onclick="mGpDeleteVideo(<?= (int)$video['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
var mGpCsrf = document.querySelector('[name="csrf_token"]') ? document.querySelector('[name="csrf_token"]').value : '';

function mGpDeleteVideo(id) {
    if (!mGpCsrf) { alert('Session expired. Please refresh.'); return; }
    if (!confirm('Delete this video?')) return;
    fetch('process_video.php', {
        method: 'POST',
        headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams({ action: 'delete_video', video_id: id, csrf_token: mGpCsrf })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) {
            var el = document.getElementById('mgv-' + id);
            if (el) el.remove();
        } else { alert(d.message || 'Error deleting video'); }
    }).catch(function() { alert('Network error'); });
}
</script>
