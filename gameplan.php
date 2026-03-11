<?php
/**
 * Game Plan - Standalone Dashboard Module
 *
 * Separate application for pre/post game planning, video tagging,
 * hockey lines, film room, and review sessions.
 * Mapped to gameplan.arcticwolves.ca
 *
 * Styled to match the main Arctic Wolves application using the same
 * design tokens, fonts, and component patterns.
 *
 * The companion server is optional – the dashboard works without it.
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Build extra CSP connect-src origins (e.g., RustFS endpoint for direct video uploads)
$extraConnectSrc = [];
try {
    require_once __DIR__ . '/cloud_config.php';
    $rustfs = getRustFSSettings($pdo);
    if (isRustFSConfigured($rustfs)) {
        $use_ssl = ($rustfs['rustfs_use_ssl'] ?? '1') === '1';
        $epUrl = rtrim($rustfs['rustfs_endpoint'], '/');
        if (strpos($epUrl, 'http://') !== 0 && strpos($epUrl, 'https://') !== 0) {
            $epUrl = ($use_ssl ? 'https://' : 'http://') . $epUrl;
        }
        $parsedEndpoint = parse_url($epUrl);
        if ($parsedEndpoint && !empty($parsedEndpoint['host'])) {
            $origin = ($parsedEndpoint['scheme'] ?? 'https') . '://' . $parsedEndpoint['host'];
            if (!empty($parsedEndpoint['port'])) $origin .= ':' . $parsedEndpoint['port'];
            $extraConnectSrc[] = $origin;
        }
        if (!empty($rustfs['rustfs_public_endpoint'])) {
            $pubUrl = rtrim($rustfs['rustfs_public_endpoint'], '/');
            if (strpos($pubUrl, 'http://') !== 0 && strpos($pubUrl, 'https://') !== 0) {
                $pubUrl = ($use_ssl ? 'https://' : 'http://') . $pubUrl;
            }
            $pubParsed = parse_url($pubUrl);
            if ($pubParsed && !empty($pubParsed['host'])) {
                $pubOrigin = ($pubParsed['scheme'] ?? 'https') . '://' . $pubParsed['host'];
                if (!empty($pubParsed['port'])) $pubOrigin .= ':' . $pubParsed['port'];
                if ($pubOrigin !== ($origin ?? '')) $extraConnectSrc[] = $pubOrigin;
            }
        }
    }
} catch (\Throwable $e) {
    error_log('CSP extraConnectSrc error (gameplan): ' . $e->getMessage());
}

// Set security headers including CSP (with RustFS origin if configured)
setSecurityHeaders($extraConnectSrc);

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

// User info
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

$isAdmin     = in_array('admin', $user_roles_list);
$isCoach     = in_array('coach', $user_roles_list);
$isTeamCoach = in_array('team_coach', $user_roles_list);
$isAnyCoach  = ($isCoach || $isAdmin || $isTeamCoach);

// Page routing
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['page']) : 'home';

$allowed_pages = [
    'home'             => 'views/gameplan/gp_home.php',
    'video_review'     => 'views/gameplan/gp_video_review.php',
    'calendar'         => 'views/gameplan/gp_calendar.php',
    'game_plan'        => 'views/gameplan/gp_game_plan.php',
    'film_room'        => 'views/gameplan/gp_film_room.php',
    'review_sessions'  => 'views/gameplan/gp_review_sessions.php',
    'my_clips'         => 'views/gameplan/gp_my_clips.php',
    'lines'            => 'views/gameplan/gp_lines.php',
    'roster'           => 'views/gameplan/gp_roster.php',
    'whiteboard'       => 'views/gameplan/gp_whiteboard.php',
];

// Admin-only pages
if ($isAdmin) {
    $allowed_pages['permissions'] = 'views/gameplan/gp_permissions.php';
}

$view_file = $allowed_pages[$page] ?? $allowed_pages['home'];

// Auto-cast: sync current page to any active unfrozen pairs this user controls.
// This makes TV pairing work like casting — the controller navigates normally and
// the TV automatically follows without needing dedicated navigation buttons.
if ($isAnyCoach && isset($allowed_pages[$page])) {
    try {
        $castStmt = $pdo->prepare("
            UPDATE vr_device_pairs
            SET controller_page = ?, status = 'active'
            WHERE is_frozen = 0
              AND status IN ('paired', 'active')
              AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
        ");
        $castStmt->execute([$page, $user_id, $user_id]);
    } catch (PDOException $e) {
        // Silently ignore — casting is best-effort
    }
}

// ── Detect active casting pair for global telestration overlay ─────
$gp_active_pair_id = 0;
if ($isAnyCoach) {
    try {
        $pairDetect = $pdo->prepare("
            SELECT id FROM vr_device_pairs
            WHERE status IN ('paired', 'active')
              AND (created_by = ? OR id IN (SELECT pair_id FROM vr_device_pair_controllers WHERE user_id = ?))
            LIMIT 1
        ");
        $pairDetect->execute([$user_id, $user_id]);
        $pairRow = $pairDetect->fetch(PDO::FETCH_ASSOC);
        if ($pairRow) $gp_active_pair_id = (int)$pairRow['id'];
    } catch (PDOException $e) { /* ignore */ }
}

// Load recent videos for the home page
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
    $recentVideos = decryptUserRows($recentVideos);
} catch (PDOException $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
    <meta name="theme-color" content="#6B46C1">
    <title>Game Plan – Arctic Wolves</title>
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="manifest" href="manifest.json">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dashjs@5.0.0/dist/dash.all.min.js"></script>
    <script src="js/hls-player.js"></script>
    <style>
        /* ── Standalone Game Plan Layout ─────────────────────────── */
        /* Uses the same design tokens as the main dashboard         */
        body {
            margin: 0;
            background: var(--bg-main);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: #fff;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* ── Sidebar ────────────────────────────────────────────── */
        .gp-sidebar {
            width: 270px;
            background: var(--sidebar);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            overflow-y: auto;
        }
        .gp-sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 20px 20px 10px;
            text-decoration: none;
            color: #fff;
            font-weight: 900;
            font-size: 18px;
            border-bottom: 1px solid var(--border);
            margin-bottom: 8px;
        }
        .gp-sidebar-brand img { height: 32px; }
        .gp-sidebar-brand .hl { color: var(--primary-light); }
        .gp-sidebar-brand small {
            display: block;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Nav items inside sidebar */
        .gp-nav-label {
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            color: var(--text-muted);
            padding: 16px 20px 6px;
        }
        .gp-nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            text-decoration: none;
            color: var(--text-secondary);
            font-size: 13px;
            font-weight: 600;
            transition: all 0.15s;
            margin: 0 8px 2px;
            border-radius: 8px;
        }
        .gp-nav-link:hover { background: rgba(107,70,193,0.08); color: #fff; }
        .gp-nav-link.active { background: rgba(107,70,193,0.15); color: var(--primary-light); }
        .gp-nav-link i { width: 18px; text-align: center; font-size: 14px; }

        .gp-sidebar-footer {
            margin-top: auto;
            padding: 12px 8px;
            border-top: 1px solid var(--border);
        }

        /* ── Main Area ──────────────────────────────────────────── */
        .gp-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }
        .gp-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 32px;
            background: var(--sidebar);
            border-bottom: 1px solid var(--border);
            min-height: 56px;
        }
        .gp-topbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .gp-topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .gp-topbar-link {
            color: var(--text-secondary);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            padding: 6px 12px;
            border-radius: 6px;
            transition: background 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .gp-topbar-link:hover { background: rgba(107,70,193,0.1); color: #fff; }
        .gp-topbar-user {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #fff;
            font-size: 13px;
            font-weight: 600;
        }
        .gp-topbar-user .avatar {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 900;
            color: #fff;
        }

        .gp-content {
            flex: 1;
            padding: 32px 40px;
            overflow-y: auto;
        }

        /* ── Mobile sidebar toggle ──────────────────────────────── */
        .gp-mobile-toggle { display: none; }
        .gp-overlay { display: none; }

        /* Hide topbar on desktop — its links duplicate the sidebar */
        .gp-topbar { display: none; }

        @media (max-width: 768px) {
            .gp-topbar { display: flex; }
            .gp-sidebar {
                position: fixed;
                left: -280px;
                top: 0;
                height: 100vh;
                z-index: 100;
                transition: left 0.25s;
            }
            .gp-sidebar.open { left: 0; }
            .gp-mobile-toggle {
                display: flex !important;
                align-items: center;
                justify-content: center;
                width: 36px;
                height: 36px;
                background: none;
                border: 1px solid var(--border);
                border-radius: 8px;
                color: #fff;
                font-size: 16px;
                cursor: pointer;
                padding: 0;
                min-height: auto;
            }
            .gp-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.6);
                z-index: 99;
            }
            .gp-sidebar.open ~ .gp-main .gp-overlay { display: block; }
            .gp-content { padding: 20px 16px; }
        }

        /* Custom scrollbar matching main dashboard */
        .gp-sidebar::-webkit-scrollbar, .gp-content::-webkit-scrollbar { width: 8px; }
        .gp-sidebar::-webkit-scrollbar-track, .gp-content::-webkit-scrollbar-track { background: var(--bg-main); }
        .gp-sidebar::-webkit-scrollbar-thumb, .gp-content::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .gp-sidebar::-webkit-scrollbar-thumb:hover, .gp-content::-webkit-scrollbar-thumb:hover { background: var(--border-light); }
        * { scrollbar-width: thin; scrollbar-color: var(--border) var(--bg-main); }
    </style>
</head>
<body>

<aside class="gp-sidebar" id="gpSidebar">
    <a href="/gameplan.php" class="gp-sidebar-brand">
        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo">
        <div>
            GAME <span class="hl">PLAN</span>
            <small>Arctic Wolves</small>
        </div>
    </a>

    <div class="gp-nav-label">Navigation</div>
    <a href="/gameplan.php?page=home" class="gp-nav-link <?= $page === 'home' ? 'active' : '' ?>">
        <i class="fas fa-house"></i> Dashboard
    </a>
    <a href="/gameplan.php?page=video_review" class="gp-nav-link <?= $page === 'video_review' ? 'active' : '' ?>">
        <i class="fas fa-film"></i> Video Review
    </a>
    <?php if ($isAnyCoach): ?>
    <a href="/gameplan.php?page=calendar" class="gp-nav-link <?= $page === 'calendar' ? 'active' : '' ?>">
        <i class="fas fa-calendar"></i> Calendar
    </a>
    <a href="/gameplan.php?page=game_plan" class="gp-nav-link <?= $page === 'game_plan' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> Game Plans
    </a>
    <a href="/gameplan.php?page=whiteboard" class="gp-nav-link <?= $page === 'whiteboard' ? 'active' : '' ?>">
        <i class="fas fa-chalkboard"></i> Whiteboard
    </a>
    <a href="/gameplan.php?page=lines" class="gp-nav-link <?= $page === 'lines' ? 'active' : '' ?>">
        <i class="fas fa-users-line"></i> Game Lines
    </a>
    <a href="/gameplan.php?page=roster" class="gp-nav-link <?= $page === 'roster' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> Roster
    </a>
    <a href="/gameplan.php?page=film_room" class="gp-nav-link <?= $page === 'film_room' ? 'active' : '' ?>">
        <i class="fas fa-video"></i> Film Room
    </a>
    <a href="/gameplan.php?page=review_sessions" class="gp-nav-link <?= $page === 'review_sessions' ? 'active' : '' ?>">
        <i class="fas fa-chalkboard-user"></i> Review Sessions
    </a>
    <?php else: ?>
    <a href="/gameplan.php?page=my_clips" class="gp-nav-link <?= $page === 'my_clips' ? 'active' : '' ?>">
        <i class="fas fa-scissors"></i> My Clips
    </a>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <div class="gp-nav-label">Admin</div>
    <a href="/gameplan.php?page=permissions" class="gp-nav-link <?= $page === 'permissions' ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i> Permissions
    </a>
    <a href="/dashboard.php?page=system_tools&tab=gameplan" class="gp-nav-link">
        <i class="fas fa-cog"></i> Settings
    </a>
    <?php endif; ?>

    <div class="gp-sidebar-footer">
        <a href="/dashboard.php" class="gp-nav-link">
            <i class="fas fa-arrow-left"></i> Main Dashboard
        </a>
        <a href="/logout.php" class="gp-nav-link" style="color: var(--error);">
            <i class="fas fa-power-off"></i> Sign Out
        </a>
        <div style="display:flex; align-items:center; gap:10px; padding:10px 12px; border-top:1px solid var(--border); margin-top:10px;">
            <div class="gp-topbar-user">
                <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            </div>
            <div style="font-size:12px;">
                <strong><?= htmlspecialchars($user_name) ?></strong><br>
                <span style="color:var(--text-secondary); text-transform:capitalize;"><?= str_replace('_', ' ', $user_role) ?></span>
            </div>
        </div>
    </div>
</aside>

<div class="gp-main">
    <div class="gp-overlay" onclick="document.getElementById('gpSidebar').classList.remove('open')"></div>
    <header class="gp-topbar">
        <div class="gp-topbar-left">
            <button class="gp-mobile-toggle" onclick="document.getElementById('gpSidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="gp-topbar-right">
            <a href="/dashboard.php" class="gp-topbar-link"><i class="fas fa-arrow-left"></i> Main Dashboard</a>
            <div class="gp-topbar-user">
                <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <?= htmlspecialchars($user_name) ?>
            </div>
        </div>
    </header>

    <div class="gp-content">
        <?php
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            include 'views/gameplan/gp_home.php';
        }
        ?>
    </div>
</div>

<script src="js/app.js"></script>

<?php if ($gp_active_pair_id > 0): ?>
<!-- ── Global Telestration Overlay (all gameplan views) ───────── -->
<canvas id="gpTeleCanvas" style="position:fixed;top:0;left:0;width:100%;height:100%;z-index:9000;pointer-events:none;"></canvas>

<!-- Floating telestration controls — always visible when casting -->
<div id="gpTeleControls" style="position:fixed;bottom:24px;right:24px;z-index:9010;display:flex;align-items:flex-end;gap:10px;flex-direction:column;">
    <!-- Toolbar: visible while Draw button is held or telestration is active -->
    <div id="gpTeleToolbar" style="display:none;background:var(--bg-card,#16161F);border:1px solid var(--border,#2D2D3F);border-radius:14px;padding:10px 14px;box-shadow:0 8px 32px rgba(0,0,0,.5);backdrop-filter:blur(12px);">
        <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;">
            <div style="display:flex;gap:4px;">
                <button class="gp-tele-tool active" data-tool="freehand" title="Freehand"><i class="fas fa-pencil"></i></button>
                <button class="gp-tele-tool" data-tool="line" title="Line"><i class="fas fa-minus"></i></button>
                <button class="gp-tele-tool" data-tool="arrow" title="Arrow"><i class="fas fa-arrow-right"></i></button>
            </div>
            <div style="width:1px;height:24px;background:var(--border,#2D2D3F);"></div>
            <div style="display:flex;gap:4px;">
                <button class="gp-tele-color active" data-color="#EF4444" style="background:#EF4444;"></button>
                <button class="gp-tele-color" data-color="#3B82F6" style="background:#3B82F6;"></button>
                <button class="gp-tele-color" data-color="#10B981" style="background:#10B981;"></button>
                <button class="gp-tele-color" data-color="#F59E0B" style="background:#F59E0B;"></button>
                <button class="gp-tele-color" data-color="#FFFFFF" style="background:#FFFFFF;"></button>
            </div>
            <div style="width:1px;height:24px;background:var(--border,#2D2D3F);"></div>
            <input type="range" id="gpTeleWidth" min="1" max="8" value="3" style="width:60px;accent-color:var(--primary,#6B46C1);" title="Line width">
            <button class="gp-tele-tool" id="gpTeleClear" title="Clear"><i class="fas fa-eraser"></i></button>
        </div>
    </div>
    <!-- Draw toggle button — tap to toggle drawing mode -->
    <button id="gpTeleDrawBtn" title="Toggle telestration drawing" style="width:56px;height:56px;border-radius:50%;border:2px solid var(--primary,#6B46C1);background:var(--bg-card,#16161F);color:var(--primary-light,#8B5CF6);font-size:20px;cursor:pointer;box-shadow:0 4px 20px rgba(0,0,0,.4);display:flex;align-items:center;justify-content:center;transition:all .2s;">
        <i class="fas fa-pencil"></i>
    </button>
</div>

<style>
.gp-tele-tool {
    width:32px;height:32px;border-radius:8px;border:1px solid var(--border,#2D2D3F);
    background:transparent;color:var(--text-secondary,#ccc);cursor:pointer;display:flex;
    align-items:center;justify-content:center;font-size:13px;transition:all .15s;padding:0;
}
.gp-tele-tool:hover { background:rgba(107,70,193,.15); color:#fff; }
.gp-tele-tool.active { background:var(--primary,#6B46C1);color:#fff;border-color:var(--primary,#6B46C1); }
.gp-tele-color {
    width:24px;height:24px;border-radius:50%;border:2px solid transparent;cursor:pointer;padding:0;
    transition:border-color .15s;
}
.gp-tele-color.active { border-color:#fff; }
#gpTeleDrawBtn.active {
    background:var(--primary,#6B46C1);color:#fff;border-color:var(--primary,#6B46C1);
    box-shadow:0 0 0 4px rgba(107,70,193,.3),0 4px 20px rgba(0,0,0,.4);
}
</style>

<script>
(function() {
    var canvas = document.getElementById('gpTeleCanvas');
    if (!canvas) return;
    var ctx = canvas.getContext('2d');
    var pairId = <?= (int)$gp_active_pair_id ?>;
    var toolbar = document.getElementById('gpTeleToolbar');
    var drawBtn = document.getElementById('gpTeleDrawBtn');
    var drawing = false; // telestration mode on/off
    var isDrawing = false; // active stroke in progress
    var tool = 'freehand', color = '#EF4444', lineWidth = 3;
    var startX, startY;
    var snapshot = null; // saved canvas state for line/arrow preview

    function resizeCanvas() {
        var w = window.innerWidth, h = window.innerHeight;
        if (canvas.width !== w || canvas.height !== h) {
            var temp = document.createElement('canvas');
            temp.width = canvas.width; temp.height = canvas.height;
            temp.getContext('2d').drawImage(canvas, 0, 0);
            canvas.width = w; canvas.height = h;
            ctx.drawImage(temp, 0, 0, temp.width, temp.height, 0, 0, w, h);
        }
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    // Toggle draw mode
    drawBtn.addEventListener('click', function() {
        drawing = !drawing;
        drawBtn.classList.toggle('active', drawing);
        toolbar.style.display = drawing ? 'block' : 'none';
        canvas.style.pointerEvents = drawing ? 'auto' : 'none';
        canvas.style.cursor = drawing ? 'crosshair' : 'default';
    });

    // Canvas coordinates helper
    function getPos(e) {
        var touch = e.touches ? e.touches[0] : (e.changedTouches ? e.changedTouches[0] : e);
        return { x: touch.clientX, y: touch.clientY };
    }

    function onStart(e) {
        if (!drawing) return;
        e.preventDefault();
        isDrawing = true;
        var pos = getPos(e);
        startX = pos.x; startY = pos.y;
        if (tool === 'freehand') {
            ctx.beginPath(); ctx.moveTo(pos.x, pos.y);
            ctx.strokeStyle = color; ctx.lineWidth = lineWidth;
            ctx.lineCap = 'round'; ctx.lineJoin = 'round';
        } else {
            snapshot = ctx.getImageData(0, 0, canvas.width, canvas.height);
        }
    }
    function onMove(e) {
        if (!isDrawing) return;
        e.preventDefault();
        var pos = getPos(e);
        if (tool === 'freehand') {
            ctx.lineTo(pos.x, pos.y); ctx.stroke();
        } else if (snapshot) {
            ctx.putImageData(snapshot, 0, 0);
            drawStraight(ctx, startX, startY, pos.x, pos.y, tool, color, lineWidth);
        }
    }
    function onEnd(e) {
        if (!isDrawing) return;
        isDrawing = false;
        if (tool !== 'freehand' && snapshot) {
            ctx.putImageData(snapshot, 0, 0);
            var pos = getPos(e);
            drawStraight(ctx, startX, startY, pos.x, pos.y, tool, color, lineWidth);
        }
        snapshot = null;
        broadcastTelestration();
    }

    function drawStraight(ctx, x1, y1, x2, y2, t, c, w) {
        ctx.strokeStyle = c; ctx.lineWidth = w; ctx.lineCap = 'round'; ctx.setLineDash([]);
        ctx.beginPath(); ctx.moveTo(x1, y1); ctx.lineTo(x2, y2); ctx.stroke();
        if (t === 'arrow') {
            var angle = Math.atan2(y2 - y1, x2 - x1), headLen = w * 5;
            ctx.fillStyle = c; ctx.beginPath(); ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headLen * Math.cos(angle - Math.PI / 6), y2 - headLen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headLen * Math.cos(angle + Math.PI / 6), y2 - headLen * Math.sin(angle + Math.PI / 6));
            ctx.closePath(); ctx.fill();
        }
    }

    canvas.addEventListener('mousedown', onStart);
    canvas.addEventListener('mousemove', onMove);
    canvas.addEventListener('mouseup', onEnd);
    canvas.addEventListener('mouseleave', onEnd);
    canvas.addEventListener('touchstart', onStart, { passive: false });
    canvas.addEventListener('touchmove', onMove, { passive: false });
    canvas.addEventListener('touchend', onEnd);

    // Tool selection
    document.querySelectorAll('.gp-tele-tool[data-tool]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.gp-tele-tool[data-tool]').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active'); tool = btn.dataset.tool;
        });
    });
    // Color selection
    document.querySelectorAll('.gp-tele-color').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.gp-tele-color').forEach(function(b) { b.classList.remove('active'); });
            btn.classList.add('active'); color = btn.dataset.color;
        });
    });
    // Line width
    var widthInput = document.getElementById('gpTeleWidth');
    if (widthInput) widthInput.addEventListener('input', function() { lineWidth = parseInt(this.value) || 3; });
    // Clear
    var clearBtn = document.getElementById('gpTeleClear');
    if (clearBtn) clearBtn.addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        broadcastTelestration();
    });

    // Broadcast to paired TV
    var broadcastTimer = null;
    function broadcastTelestration() {
        if (!pairId) return;
        if (broadcastTimer) clearTimeout(broadcastTimer);
        broadcastTimer = setTimeout(function() {
            var dataUrl = canvas.toDataURL('image/png');
            var csrf = document.querySelector('input[name="csrf_token"]') || document.querySelector('meta[name="csrf-token"]');
            var token = csrf ? (csrf.value || csrf.content || '') : '';
            if (!token) return;
            fetch('/process_video.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'action=broadcast_telestration&pair_id=' + pairId
                    + '&canvas_data=' + encodeURIComponent(dataUrl)
                    + '&csrf_token=' + encodeURIComponent(token)
            }).catch(function() { /* best-effort */ });
        }, 500);
    }
})();
</script>
<?php endif; ?>

</body>
</html>
