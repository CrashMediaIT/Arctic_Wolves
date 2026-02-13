<?php
/**
 * Game Plan - Video Review Interface
 *
 * Entry point for the gameplan.arcticwolves.ca subdomain.
 * Provides a video review dashboard that communicates with the
 * Video Companion Server via PHP backend (curl).
 *
 * Follows the same authentication & routing pattern as the main dashboard.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

// Set security headers
setSecurityHeaders();

// Generate CSRF token
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || $pdo === null) {
    die("Database connection failed.");
}

// Auth check - redirect to main site login if not logged in
if (!isset($_SESSION['logged_in'])) {
    header("Location: /login.php");
    exit();
}

// Only coaches and admins can access Game Plan
$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$user_name = $_SESSION['user_name'] ?? 'Guest';

$user_roles_list = [$user_role];
try {
    $rolesStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $rolesStmt->execute([$user_id]);
    $extraRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($extraRoles) {
        $user_roles_list = array_unique(array_merge($user_roles_list, $extraRoles));
    }
} catch (PDOException $e) { /* ignore */ }

$isAdmin  = in_array('admin', $user_roles_list);
$isCoach  = in_array('coach', $user_roles_list);
$isTeamCoach = in_array('team_coach', $user_roles_list);
$isAnyCoach = ($isCoach || $isAdmin || $isTeamCoach);

if (!$isAnyCoach) {
    header("Location: /dashboard.php");
    exit();
}

// Load companion server settings
$companion_url = '';
$companion_api_key = '';
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('gameplan_companion_url', 'gameplan_companion_api_key')");
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if ($row['setting_key'] === 'gameplan_companion_url') $companion_url = $row['setting_value'];
        if ($row['setting_key'] === 'gameplan_companion_api_key') $companion_api_key = $row['setting_value'];
    }
} catch (PDOException $e) { /* ignore */ }

// Load recent videos for the review interface
$recentVideos = [];
try {
    $stmt = $pdo->prepare("
        SELECT v.id, v.title, v.filename, v.file_path, v.duration, v.status,
               v.created_at, v.athlete_id,
               u.first_name as athlete_first_name, u.last_name as athlete_last_name
        FROM videos v
        LEFT JOIN users u ON v.athlete_id = u.id
        ORDER BY v.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $recentVideos = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* ignore */ }

// Check companion server status
$companionOnline = false;
if ($companion_url) {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#6B46C1">
    <title>Game Plan – Arctic Wolves</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <style>
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: #0A0A0F; color: #fff; margin: 0; }
        .gp-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: rgba(19,19,26,.95); border-bottom: 1px solid rgba(107,70,193,.15); }
        .gp-header-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; color: #fff; font-weight: 900; font-size: 18px; }
        .gp-header-logo img { height: 32px; }
        .gp-header-logo .hl { color: #8B5CF6; }
        .gp-header-actions { display: flex; align-items: center; gap: 12px; }
        .gp-header-actions a { color: #A8A8B8; text-decoration: none; font-size: 13px; font-weight: 600; padding: 8px 16px; border-radius: 8px; transition: background .2s; }
        .gp-header-actions a:hover { background: rgba(107,70,193,.12); color: #fff; }
        .gp-main { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .gp-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 8px; font-size: 12px; font-weight: 600; }
        .gp-status.online { background: rgba(16,185,129,.12); color: #10B981; }
        .gp-status.offline { background: rgba(239,68,68,.12); color: #EF4444; }
        .gp-status .dot { width: 8px; height: 8px; border-radius: 50%; }
        .gp-status.online .dot { background: #10B981; }
        .gp-status.offline .dot { background: #EF4444; }
        .gp-section { margin-bottom: 32px; }
        .gp-section-title { font-size: 18px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .gp-section-title i { color: #8B5CF6; }
        .gp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .gp-card { background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px; overflow: hidden; transition: border-color .2s, transform .15s; }
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
        .gp-btn-secondary { background: rgba(107,70,193,.12); border: 1px solid rgba(107,70,193,.2); color: #fff; }
        @media (max-width: 640px) {
            .gp-main { padding: 16px; }
            .gp-header { padding: 12px 16px; }
            .gp-grid { grid-template-columns: 1fr; }
            .gp-header-logo { font-size: 15px; }
            .gp-header-logo img { height: 26px; }
        }
    </style>
</head>
<body>

<header class="gp-header">
    <a href="/gameplan.php" class="gp-header-logo">
        <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo">
        GAME <span class="hl">PLAN</span>
    </a>
    <div class="gp-header-actions">
        <span class="gp-status <?= $companionOnline ? 'online' : 'offline' ?>">
            <span class="dot"></span>
            <?= $companionOnline ? 'Companion Online' : 'Companion Offline' ?>
        </span>
        <a href="/dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
        <a href="/logout.php"><i class="fas fa-power-off"></i> Sign Out</a>
    </div>
</header>

<main class="gp-main">

    <?php if (!$companion_url): ?>
    <!-- No companion configured -->
    <div class="gp-section">
        <div class="gp-setup-card">
            <i class="fas fa-server"></i>
            <h3>Companion Server Not Configured</h3>
            <p>The Video Companion Server handles video encoding and clip extraction.<br>
               Configure it in the admin settings to enable video processing.</p>
            <?php if ($isAdmin): ?>
            <a href="/dashboard.php?page=gameplan_settings" class="gp-btn gp-btn-primary">
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

    <!-- Video Library -->
    <div class="gp-section">
        <div class="gp-section-title"><i class="fas fa-film"></i> Video Library</div>

        <?php if (empty($recentVideos)): ?>
        <div class="gp-empty">
            <i class="fas fa-video-slash"></i>
            <p>No videos yet. Upload videos from the main dashboard or record them in the app.</p>
            <a href="/dashboard.php?page=video"><i class="fas fa-upload"></i> Go to Video Upload</a>
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

</main>

<script src="js/app.js"></script>
</body>
</html>
