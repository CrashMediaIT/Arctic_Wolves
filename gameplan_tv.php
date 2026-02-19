<?php
/**
 * Game Plan TV – 10-foot UI PWA
 *
 * Designed for Smart TVs, set-top boxes, and large displays.
 * Uses the same game plan sub-views (views/gameplan/gp_*.php) as the
 * desktop and mobile apps but wrapped in a TV-optimised shell with
 * large fonts, high-contrast elements, and D-pad / remote friendly
 * navigation.
 *
 * Install as a PWA on Android TV, Fire TV, LG webOS, Samsung Tizen,
 * or any Chromium-based TV browser.
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

$isAdmin     = in_array('admin', $user_roles_list);
$isCoach     = in_array('coach', $user_roles_list);
$isTeamCoach = in_array('team_coach', $user_roles_list);
$isAnyCoach  = ($isCoach || $isAdmin || $isTeamCoach);

// Page routing – same sub-pages as gameplan.php
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
} catch (PDOException $e) { /* ignore */ }

// Page labels for topbar
$page_labels = [
    'home'            => 'Dashboard',
    'video_review'    => 'Video Review',
    'calendar'        => 'Calendar',
    'game_plan'       => 'Game Plans',
    'film_room'       => 'Film Room',
    'review_sessions' => 'Review Sessions',
    'my_clips'        => 'My Clips',
    'lines'           => 'Game Lines',
    'roster'          => 'Roster',
    'whiteboard'      => 'Whiteboard',
    'permissions'     => 'Permissions',
];
$current_label = $page_labels[$page] ?? 'Game Plan';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#6B46C1">
    <title>Game Plan TV – Arctic Wolves</title>
    <link rel="icon" type="image/png" href="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png">
    <link rel="apple-touch-icon" href="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png">
    <link rel="manifest" href="manifest-gameplan-tv.json">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <link rel="stylesheet" href="css/gameplan-tv.css">
</head>
<body class="tv-body">

<!-- ── Sidebar ──────────────────────────────────────────── -->
<aside class="tv-sidebar">
    <a href="/gameplan_tv.php" class="brand">
        <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo">
        <div>
            GAME <span>PLAN</span>
            <small>Arctic Wolves</small>
        </div>
    </a>

    <span class="nav-label">Navigation</span>
    <a href="/gameplan_tv.php?page=home" class="nav-link <?= $page === 'home' ? 'active' : '' ?>">
        <i class="fas fa-house"></i> Dashboard
    </a>
    <a href="/gameplan_tv.php?page=video_review" class="nav-link <?= $page === 'video_review' ? 'active' : '' ?>">
        <i class="fas fa-film"></i> Video Review
    </a>
    <?php if ($isAnyCoach): ?>
    <a href="/gameplan_tv.php?page=calendar" class="nav-link <?= $page === 'calendar' ? 'active' : '' ?>">
        <i class="fas fa-calendar"></i> Calendar
    </a>
    <a href="/gameplan_tv.php?page=game_plan" class="nav-link <?= $page === 'game_plan' ? 'active' : '' ?>">
        <i class="fas fa-clipboard-list"></i> Game Plans
    </a>
    <a href="/gameplan_tv.php?page=whiteboard" class="nav-link <?= $page === 'whiteboard' ? 'active' : '' ?>">
        <i class="fas fa-chalkboard"></i> Whiteboard
    </a>
    <a href="/gameplan_tv.php?page=lines" class="nav-link <?= $page === 'lines' ? 'active' : '' ?>">
        <i class="fas fa-users-line"></i> Game Lines
    </a>
    <a href="/gameplan_tv.php?page=roster" class="nav-link <?= $page === 'roster' ? 'active' : '' ?>">
        <i class="fas fa-id-card"></i> Roster
    </a>
    <a href="/gameplan_tv.php?page=film_room" class="nav-link <?= $page === 'film_room' ? 'active' : '' ?>">
        <i class="fas fa-video"></i> Film Room
    </a>
    <a href="/gameplan_tv.php?page=review_sessions" class="nav-link <?= $page === 'review_sessions' ? 'active' : '' ?>">
        <i class="fas fa-chalkboard-user"></i> Review Sessions
    </a>
    <?php else: ?>
    <a href="/gameplan_tv.php?page=my_clips" class="nav-link <?= $page === 'my_clips' ? 'active' : '' ?>">
        <i class="fas fa-scissors"></i> My Clips
    </a>
    <?php endif; ?>

    <?php if ($isAdmin): ?>
    <span class="nav-label" style="margin-top: 16px;">Admin</span>
    <a href="/gameplan_tv.php?page=permissions" class="nav-link <?= $page === 'permissions' ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i> Permissions
    </a>
    <?php endif; ?>

    <div class="tv-sidebar-footer">
        <a href="/gameplan.php" class="nav-link">
            <i class="fas fa-desktop"></i> Desktop View
        </a>
        <a href="/dashboard.php" class="nav-link">
            <i class="fas fa-arrow-left"></i> Main Dashboard
        </a>
        <a href="/logout.php" class="nav-link" style="color: var(--error, #EF4444);">
            <i class="fas fa-power-off"></i> Sign Out
        </a>
        <div class="tv-sidebar-user">
            <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <div class="user-info">
                <strong><?= htmlspecialchars($user_name) ?></strong>
                <small><?= str_replace('_', ' ', $user_role) ?></small>
            </div>
        </div>
    </div>
</aside>

<!-- ── Main Content ────────────────────────────────────── -->
<div class="tv-main">
    <header class="tv-topbar">
        <div class="tv-topbar-title">
            <button class="tv-sidebar-toggle" id="tvSidebarToggle" onclick="toggleTvSidebar()" aria-label="Toggle navigation">
                <i class="fas fa-times" id="tvToggleIcon"></i>
            </button>
            <i class="fas fa-chess-board"></i>
            <?= htmlspecialchars($current_label) ?>
        </div>
        <div class="tv-topbar-actions">
            <span class="clock" id="tvClock"></span>
        </div>
    </header>

    <div class="tv-content">
        <?php
        if (file_exists($view_file)) {
            include $view_file;
        } else {
            echo '<div class="tv-empty">';
            echo '<i class="fas fa-exclamation-triangle"></i>';
            echo '<p>Module not available</p>';
            echo '</div>';
        }
        ?>
    </div>
</div>

<!-- ── Scripts ─────────────────────────────────────────── -->
<script>
// Sidebar toggle
function toggleTvSidebar() {
    var sidebar = document.querySelector('.tv-sidebar');
    var icon = document.getElementById('tvToggleIcon');
    if (!sidebar) return;
    sidebar.classList.toggle('collapsed');
    if (icon) {
        icon.className = sidebar.classList.contains('collapsed') ? 'fas fa-bars' : 'fas fa-times';
    }
    sessionStorage.setItem('tvSidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}
// Restore sidebar state on load
(function() {
    var sidebar = document.querySelector('.tv-sidebar');
    var icon = document.getElementById('tvToggleIcon');
    var state = sessionStorage.getItem('tvSidebarCollapsed');
    if (state === '1' && sidebar) {
        sidebar.classList.add('collapsed');
        if (icon) icon.className = 'fas fa-bars';
    }
})();

// Live clock in topbar
(function() {
    function updateClock() {
        var d = new Date();
        var h = d.getHours(), m = d.getMinutes();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var str = h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
        var el = document.getElementById('tvClock');
        if (el) el.textContent = str;
    }
    updateClock();
    setInterval(updateClock, 10000);
})();

// Service worker registration
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('pwa-sw.js').catch(function() {});
}
</script>
<script src="js/app.js"></script>
</body>
</html>
