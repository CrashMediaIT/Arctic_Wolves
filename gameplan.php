<?php
/**
 * Game Plan - Standalone Dashboard Module
 *
 * Standalone video review & game planning dashboard.
 * Structured as its own dashboard module (like ACVideoReview) with
 * its own navigation sidebar and page routing.
 *
 * The companion server is optional – the dashboard works without it.
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

$isAdmin  = in_array('admin', $user_roles_list);
$isCoach  = in_array('coach', $user_roles_list);
$isTeamCoach = in_array('team_coach', $user_roles_list);
$isAnyCoach = ($isCoach || $isAdmin || $isTeamCoach);

// Page routing
$page = isset($_GET['page']) ? preg_replace('/[^a-z0-9_]/', '', $_GET['page']) : 'home';

$allowed_pages = [
    'home'             => 'views/gameplan/home.php',
    'video_review'     => 'views/gameplan/video_review.php',
    'calendar'         => 'views/gameplan/calendar.php',
    'game_plan'        => 'views/gameplan/game_plan.php',
    'film_room'        => 'views/gameplan/film_room.php',
    'review_sessions'  => 'views/gameplan/review_sessions.php',
    'my_clips'         => 'views/gameplan/my_clips.php',
];

// Admin-only pages
if ($isAdmin) {
    $allowed_pages['permissions'] = 'views/gameplan/permissions.php';
    $allowed_pages['settings']    = 'views/gameplan_settings.php';
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
} catch (PDOException $e) { /* ignore */ }
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
        :root {
            --gp-bg: #0A0A0F;
            --gp-sidebar: #13131A;
            --gp-card: #16161F;
            --gp-border: #2D2D3F;
            --gp-primary: #6B46C1;
            --gp-primary-light: #8B5CF6;
            --gp-text: #ffffff;
            --gp-text-muted: #A8A8B8;
            --gp-text-dim: #6B6B7B;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif; background: var(--gp-bg); color: var(--gp-text); margin: 0; }
        .gp-layout { display: flex; height: 100vh; overflow: hidden; }
        .gp-sidebar {
            width: 260px; background: var(--gp-sidebar); border-right: 1px solid var(--gp-border);
            display: flex; flex-direction: column; flex-shrink: 0; overflow-y: auto;
        }
        .gp-sidebar-logo {
            display: flex; align-items: center; gap: 12px; padding: 20px 20px 16px;
            text-decoration: none; color: var(--gp-text); font-weight: 900; font-size: 18px;
            border-bottom: 1px solid var(--gp-border);
        }
        .gp-sidebar-logo img { height: 32px; }
        .gp-sidebar-logo .hl { color: var(--gp-primary-light); }
        .gp-sidebar-nav { padding: 12px 10px; flex: 1; }
        .gp-nav-label {
            font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;
            color: var(--gp-text-dim); padding: 12px 10px 6px; margin-top: 4px;
        }
        .gp-nav-link {
            display: flex; align-items: center; gap: 10px; padding: 10px 14px;
            border-radius: 8px; text-decoration: none; color: var(--gp-text-muted);
            font-size: 13px; font-weight: 600; transition: all .15s; margin-bottom: 2px;
        }
        .gp-nav-link:hover { background: rgba(107,70,193,.08); color: var(--gp-text); }
        .gp-nav-link.active { background: rgba(107,70,193,.15); color: var(--gp-primary-light); }
        .gp-nav-link i { width: 18px; text-align: center; font-size: 14px; }
        .gp-sidebar-footer {
            padding: 12px 10px; border-top: 1px solid var(--gp-border);
        }
        .gp-main-area { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .gp-topbar {
            display: flex; align-items: center; justify-content: flex-end; gap: 12px;
            padding: 12px 24px; background: var(--gp-sidebar); border-bottom: 1px solid var(--gp-border);
        }
        .gp-topbar-link {
            color: var(--gp-text-muted); text-decoration: none; font-size: 13px; font-weight: 600;
            padding: 6px 12px; border-radius: 6px; transition: background .15s;
        }
        .gp-topbar-link:hover { background: rgba(107,70,193,.1); color: var(--gp-text); }
        .gp-topbar-user {
            display: flex; align-items: center; gap: 8px; color: var(--gp-text); font-size: 13px; font-weight: 600;
        }
        .gp-topbar-user .avatar {
            width: 30px; height: 30px; border-radius: 50%;
            background: linear-gradient(135deg, var(--gp-primary), var(--gp-primary-light));
            display: flex; align-items: center; justify-content: center;
            font-size: 12px; font-weight: 700; color: #fff;
        }
        .gp-content { flex: 1; padding: 32px; overflow-y: auto; }

        /* Shared view styles */
        .gp-page-header { margin-bottom: 28px; }
        .gp-page-title { font-size: 22px; font-weight: 800; margin: 0 0 6px; display: flex; align-items: center; gap: 10px; }
        .gp-page-title i { color: var(--gp-primary-light); }
        .gp-page-desc { font-size: 13px; color: var(--gp-text-muted); margin: 0; }
        .gp-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .gp-card { background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px; overflow: hidden; transition: border-color .2s, transform .15s; }
        .gp-card:hover { border-color: rgba(107,70,193,.4); transform: translateY(-2px); }
        .gp-card-thumb { width: 100%; aspect-ratio: 16/9; background: #1a1a24; display: flex; align-items: center; justify-content: center; color: var(--gp-text-dim); font-size: 32px; position: relative; }
        .gp-card-badge { position: absolute; top: 8px; right: 8px; padding: 3px 8px; border-radius: 6px; font-size: 10px; font-weight: 700; text-transform: uppercase; }
        .gp-card-badge.pending { background: rgba(59,130,246,.15); color: #3B82F6; }
        .gp-card-badge.reviewed { background: rgba(16,185,129,.15); color: #10B981; }
        .gp-card-body { padding: 14px 16px; }
        .gp-card-title { font-size: 14px; font-weight: 700; margin-bottom: 4px; }
        .gp-card-meta { font-size: 12px; color: var(--gp-text-muted); display: flex; align-items: center; gap: 12px; }
        .gp-empty { text-align: center; padding: 60px 24px; color: var(--gp-text-dim); }
        .gp-empty i { font-size: 48px; margin-bottom: 16px; display: block; color: var(--gp-primary-light); opacity: .5; }
        .gp-empty p { font-size: 15px; margin-bottom: 20px; }
        .gp-empty a { color: var(--gp-primary-light); text-decoration: none; font-weight: 600; }
        .gp-section { margin-bottom: 32px; }
        .gp-section-title { font-size: 18px; font-weight: 800; margin-bottom: 16px; display: flex; align-items: center; gap: 10px; }
        .gp-section-title i { color: var(--gp-primary-light); }
        .gp-placeholder-card {
            background: var(--gp-card); border: 1px solid var(--gp-border); border-radius: 14px;
            padding: 48px 24px; text-align: center;
        }
        .gp-placeholder-card i { font-size: 48px; color: var(--gp-primary-light); opacity: .4; margin-bottom: 16px; }
        .gp-placeholder-card h3 { font-size: 18px; font-weight: 700; margin: 0 0 8px; }
        .gp-placeholder-card p { font-size: 13px; color: var(--gp-text-muted); margin: 0; line-height: 1.6; }

        /* Mobile sidebar toggle */
        .gp-mobile-toggle { display: none; }
        @media (max-width: 768px) {
            .gp-sidebar { position: fixed; left: -280px; top: 0; height: 100vh; z-index: 100; transition: left .25s; }
            .gp-sidebar.open { left: 0; }
            .gp-mobile-toggle { display: flex; align-items: center; justify-content: center; width: 36px; height: 36px; background: none; border: 1px solid var(--gp-border); border-radius: 8px; color: var(--gp-text); font-size: 16px; cursor: pointer; }
            .gp-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.6); z-index: 99; }
            .gp-sidebar.open ~ .gp-main-area .gp-overlay { display: block; }
            .gp-content { padding: 16px; }
        }
    </style>
</head>
<body>

<div class="gp-layout">
    <!-- Sidebar Navigation -->
    <aside class="gp-sidebar" id="gpSidebar">
        <a href="/gameplan.php" class="gp-sidebar-logo">
            <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo">
            GAME <span class="hl">PLAN</span>
        </a>

        <div class="gp-sidebar-nav">
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
                <i class="fas fa-chess-board"></i> Game Plan
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
            <a href="/gameplan.php?page=settings" class="gp-nav-link <?= $page === 'settings' ? 'active' : '' ?>">
                <i class="fas fa-cog"></i> Settings
            </a>
            <?php endif; ?>
        </div>

        <div class="gp-sidebar-footer">
            <a href="/dashboard.php" class="gp-nav-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="gp-main-area">
        <div class="gp-overlay" onclick="document.getElementById('gpSidebar').classList.remove('open')"></div>
        <header class="gp-topbar">
            <button class="gp-mobile-toggle" onclick="document.getElementById('gpSidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </button>
            <div style="flex:1;"></div>
            <a href="/dashboard.php" class="gp-topbar-link"><i class="fas fa-arrow-left"></i> Main Dashboard</a>
            <a href="/logout.php" class="gp-topbar-link"><i class="fas fa-power-off"></i> Sign Out</a>
            <div class="gp-topbar-user">
                <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
                <?= htmlspecialchars($user_name) ?>
            </div>
        </header>

        <div class="gp-content">
            <?php
            if (file_exists($view_file)) {
                include $view_file;
            } else {
                // Default home view rendered inline
                include $allowed_pages['home'] ?? __DIR__ . '/views/gameplan/home.php';
            }
            ?>
        </div>
    </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
