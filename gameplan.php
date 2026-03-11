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
</body>
</html>
