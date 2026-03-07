<?php
/**
 * Scoreboard – In-Arena Display Controller
 *
 * Designed for Android TV / large arena displays at scoreboard.arcticwolves.ca.
 * Provides a professional hockey scoreboard with:
 *   - Goal, shot, penalty, and hit tracking
 *   - Buzzer / horn with wireless speaker integration
 *   - Spotify & Subsonic music integration + mic system
 *   - Full scoresheet entry (syncs to Game Plan game results & player stats)
 *   - Video board mode (pregame promos, in-arena cam, browser video)
 *
 * Access: Staff accounts only (admin, coach, health_coach, front_desk_staff, hr, accounting).
 * Added to POS IP restriction list for location-based access control.
 * NOT linked from the main application navigation.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Set security headers
setSecurityHeaders();

// Generate CSRF token
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || $pdo === null) {
    die("Database connection failed.");
}

// Auth check – redirect to main site login if not logged in
if (!isset($_SESSION['logged_in'])) {
    $redirect = 'https://scoreboard.arcticwolves.ca/scoreboard.php';
    header("Location: /login.php?redirect=" . urlencode($redirect));
    exit();
}

// User info
$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Multi-role support
$user_roles_list = [$user_role];
try {
    $rolesStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $rolesStmt->execute([$user_id]);
    $extraRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($extraRoles) {
        $user_roles_list = array_unique(array_merge($user_roles_list, $extraRoles));
    }
} catch (PDOException $e) { /* ignore */ }

$isAdmin       = in_array('admin', $user_roles_list);
$isCoach       = in_array('coach', $user_roles_list);
$isHealthCoach = in_array('health_coach', $user_roles_list);
$isTeamCoach   = in_array('team_coach', $user_roles_list);
$isFrontDesk   = in_array('front_desk_staff', $user_roles_list);
$isHR          = in_array('hr', $user_roles_list);
$isAccounting  = in_array('accounting', $user_roles_list);
$isStaff       = ($isAdmin || $isCoach || $isHealthCoach || $isFrontDesk || $isHR || $isAccounting);
$isAnyCoach    = ($isCoach || $isAdmin || $isTeamCoach);

// Staff-only access gate
if (!$isStaff) {
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="background:#0A0A0F;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Inter,sans-serif;"><div style="text-align:center;"><h1 style="font-size:48px;margin-bottom:16px;">🔒</h1><h2>Access Denied</h2><p style="color:#888;">Scoreboard access is restricted to staff accounts.</p></div></body></html>';
    exit();
}

// POS IP restriction check (same whitelist as POS terminal)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('scoreboard_ip_blocked', 'Scoreboard access denied from unauthorized IP', ['ip' => getClientIP(), 'page' => 'scoreboard']);
    echo '<!DOCTYPE html><html><head><title>Access Denied</title></head><body style="background:#0A0A0F;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh;font-family:Inter,sans-serif;"><div style="text-align:center;"><h1 style="font-size:48px;margin-bottom:16px;">🚫</h1><h2>Access Denied</h2><p style="color:#888;">Scoreboard access is not available from this location.</p></div></body></html>';
    exit();
}

// ── View mode ─────────────────────────────────────────────
$view = $_GET['view'] ?? 'scoreboard';
$allowed_views = ['scoreboard', 'scoresheet', 'video_board'];
if (!in_array($view, $allowed_views)) {
    $view = 'scoreboard';
}

// ── Load active game (if any) ─────────────────────────────
$active_game = null;
$home_roster = [];
$away_roster = [];
$game_goals = [];
$game_penalties = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM scoreboard_games
        WHERE status IN ('warmup', 'in_progress', 'intermission')
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
    $active_game = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($active_game) {
        $gid = (int)$active_game['id'];
        // Goals
        $stmt = $pdo->prepare("SELECT * FROM scoreboard_goals WHERE game_id = ? ORDER BY period ASC, game_time_seconds ASC");
        $stmt->execute([$gid]);
        $game_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // Penalties
        $stmt = $pdo->prepare("SELECT * FROM scoreboard_penalties WHERE game_id = ? ORDER BY period ASC, game_time_seconds ASC");
        $stmt->execute([$gid]);
        $game_penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log('Scoreboard game load: ' . $e->getMessage());
}

// ── Fetch teams for game setup ────────────────────────────
$teams = [];
try {
    $stmt = $pdo->query("SELECT id, team_name FROM teams WHERE status = 'active' ORDER BY team_name");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist */ }

// ── Music settings ────────────────────────────────────────
$spotify_configured = false;
$subsonic_configured = false;
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('spotify_client_id', 'subsonic_url')");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        if ($s['setting_key'] === 'spotify_client_id' && !empty($s['setting_value'])) $spotify_configured = true;
        if ($s['setting_key'] === 'subsonic_url' && !empty($s['setting_value'])) $subsonic_configured = true;
    }
} catch (PDOException $e) { /* ignore */ }

$home_score = 0;
$away_score = 0;
$home_shots = 0;
$away_shots = 0;
if ($active_game) {
    $home_score = (int)($active_game['home_score'] ?? 0);
    $away_score = (int)($active_game['away_score'] ?? 0);
    $home_shots = (int)($active_game['home_shots'] ?? 0);
    $away_shots = (int)($active_game['away_shots'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0A0A0F">
    <title>Scoreboard – Arctic Wolves</title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/scoreboard.css">
</head>
<body class="sb-body">

<?php if ($view === 'video_board'): ?>
<!-- ══════════════════════════════════════════════════════════
     VIDEO BOARD MODE – Full-screen video/presentation display
     ══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/views/scoreboard/video_board.php'; ?>

<?php elseif ($view === 'scoresheet'): ?>
<!-- ══════════════════════════════════════════════════════════
     SCORESHEET MODE – Full game sheet data entry
     ══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/views/scoreboard/scoresheet.php'; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     SCOREBOARD MODE – Primary arena display
     ══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/views/scoreboard/scoreboard_display.php'; ?>

<?php endif; ?>

<!-- ── Scripts ─────────────────────────────────────────── -->
<script>
// CSRF token for AJAX requests
const CSRF_TOKEN = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';
const ACTIVE_GAME_ID = <?= $active_game ? (int)$active_game['id'] : 'null' ?>;

// Live clock in topbar
(function() {
    function updateClock() {
        var d = new Date();
        var h = d.getHours(), m = d.getMinutes(), s = d.getSeconds();
        var ampm = h >= 12 ? 'PM' : 'AM';
        h = h % 12 || 12;
        var str = h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' ' + ampm;
        var el = document.getElementById('sbClock');
        if (el) el.textContent = str;
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>
<script src="js/scoreboard.js"></script>
</body>
</html>
