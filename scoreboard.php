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

// Handle PIN login for scoreboard
$scoreboard_login_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scoreboard_pin_login') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $scoreboard_login_error = "Invalid request. Please refresh and try again.";
    } else {
        $pin = $_POST['pin'] ?? '';
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            $scoreboard_login_error = "Please enter a valid 4-digit PIN.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.first_name, u.last_name, u.role, sp.pin_hash
                    FROM users u
                    INNER JOIN staff_pins sp ON u.id = sp.user_id
                    WHERE u.role IN ('admin', 'coach', 'health_coach', 'front_desk_staff', 'hr', 'accounting')
                    AND u.is_active = 1
                    AND sp.is_active = 1
                ");
                $stmt->execute();
                $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $matchedStaff = null;
                foreach ($staffMembers as $staff) {
                    if (password_verify($pin, $staff['pin_hash'])) {
                        $matchedStaff = $staff;
                        break;
                    }
                }

                if ($matchedStaff) {
                    require_once __DIR__ . '/lib/encryption.php';
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $matchedStaff['id'];
                    $_SESSION['user_role'] = $matchedStaff['role'];
                    $_SESSION['user_name'] = FieldEncryption::decrypt($matchedStaff['first_name']);
                    $_SESSION['scoreboard_mode'] = true;
                    header("Location: scoreboard.php");
                    exit();
                } else {
                    $scoreboard_login_error = "Invalid PIN. Please try again.";
                }
            } catch (PDOException $e) {
                error_log("Scoreboard PIN login error: " . $e->getMessage());
                $scoreboard_login_error = "Login error. Please try again.";
            }
        }
    }
}

// Handle regular login for scoreboard
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'scoreboard_user_login') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $scoreboard_login_error = "Invalid request. Please refresh and try again.";
    } else {
        require_once __DIR__ . '/lib/encryption.php';
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        if (empty($email) || empty($password)) {
            $scoreboard_login_error = "Email and password are required.";
        } else {
            try {
                $stmt = $pdo->prepare("SELECT id, first_name, password, role, is_active, is_verified, email FROM users");
                $stmt->execute();
                $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                $matchedUser = null;
                foreach ($allUsers as $u) {
                    $decryptedEmail = FieldEncryption::decrypt($u['email']);
                    if (strcasecmp($decryptedEmail, $email) === 0) {
                        $matchedUser = $u;
                        break;
                    }
                }
                if ($matchedUser && $matchedUser['is_active'] && password_verify($password, $matchedUser['password'])) {
                    if (isset($matchedUser['is_verified']) && $matchedUser['is_verified'] === 0) {
                        $scoreboard_login_error = "Account pending verification.";
                    } else {
                        $_SESSION['logged_in'] = true;
                        $_SESSION['user_id'] = $matchedUser['id'];
                        $_SESSION['user_role'] = $matchedUser['role'];
                        $_SESSION['user_name'] = FieldEncryption::decrypt($matchedUser['first_name']);
                        $_SESSION['scoreboard_mode'] = true;
                        header("Location: scoreboard.php");
                        exit();
                    }
                } else {
                    $scoreboard_login_error = "Invalid email or password.";
                }
            } catch (PDOException $e) {
                error_log("Scoreboard user login error: " . $e->getMessage());
                $scoreboard_login_error = "Login error. Please try again.";
            }
        }
    }
}

// Auth check – show scoreboard login page if not logged in
if (!isset($_SESSION['logged_in'])) {
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Scoreboard Login</title>';
    echo '<link rel="icon" type="image/x-icon" href="' . htmlspecialchars($site_favicon_url) . '">';
    echo '<style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0A0A0F; color: #fff; font-family: Inter, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .login-container { width: 100%; max-width: 420px; padding: 40px; }
        .login-logo { text-align: center; margin-bottom: 32px; }
        .login-logo img { max-height: 60px; }
        .login-logo h1 { font-size: 24px; margin-top: 16px; color: #e2e8f0; }
        .login-logo p { color: #64748b; font-size: 14px; margin-top: 8px; }
        .login-tabs { display: flex; gap: 0; margin-bottom: 24px; border-radius: 8px; overflow: hidden; border: 1px solid #2d2d44; }
        .login-tab { flex: 1; padding: 12px; text-align: center; cursor: pointer; background: #1a1a2e; color: #64748b; font-weight: 600; font-size: 14px; border: none; transition: all 0.2s; }
        .login-tab.active { background: #6B46C1; color: #fff; }
        .login-panel { display: none; }
        .login-panel.active { display: block; }
        .pin-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; max-width: 280px; margin: 0 auto; }
        .pin-btn { background: #1a1a2e; border: 1px solid #2d2d44; color: #e2e8f0; font-size: 24px; font-weight: 700; padding: 20px; border-radius: 12px; cursor: pointer; transition: all 0.15s; }
        .pin-btn:hover { background: #2d2d44; border-color: #6B46C1; }
        .pin-btn:active { transform: scale(0.95); }
        .pin-btn.clear { font-size: 14px; color: #ef4444; }
        .pin-btn.enter { font-size: 14px; background: #6B46C1; border-color: #6B46C1; color: #fff; }
        .pin-display { display: flex; justify-content: center; gap: 16px; margin-bottom: 24px; }
        .pin-dot { width: 16px; height: 16px; border-radius: 50%; border: 2px solid #2d2d44; transition: all 0.2s; }
        .pin-dot.filled { background: #6B46C1; border-color: #6B46C1; }
        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; color: #94a3b8; margin-bottom: 6px; }
        .form-group input { width: 100%; padding: 12px 16px; background: #1a1a2e; border: 1px solid #2d2d44; border-radius: 8px; color: #e2e8f0; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #6B46C1; }
        .btn-login { width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none; border-radius: 8px; font-weight: 700; font-size: 14px; cursor: pointer; margin-top: 8px; }
        .btn-login:hover { background: #5a37a8; }
        .error-msg { background: rgba(239,68,68,0.15); color: #ef4444; padding: 10px 16px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; text-align: center; }
    </style></head><body>
    <div class="login-container">
        <div class="login-logo">';
    if (!empty($site_logo_url)) {
        echo '<img src="' . htmlspecialchars($site_logo_url) . '" alt="Logo">';
    }
    echo '<h1>Scoreboard Login</h1><p>Sign in to access the arena scoreboard</p></div>';
    if (!empty($scoreboard_login_error)) {
        echo '<div class="error-msg"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($scoreboard_login_error) . '</div>';
    }
    echo '<div class="login-tabs">
            <button class="login-tab active" onclick="switchTab(\'pin\')"><i class="fas fa-key"></i> PIN Login</button>
            <button class="login-tab" onclick="switchTab(\'user\')"><i class="fas fa-user"></i> User Login</button>
        </div>
        <div class="login-panel active" id="panel-pin">
            <form method="POST" id="pin-form">
                <input type="hidden" name="action" value="scoreboard_pin_login">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">
                <input type="hidden" name="pin" id="pin-input" value="">
                <div class="pin-display">
                    <div class="pin-dot" id="dot-0"></div>
                    <div class="pin-dot" id="dot-1"></div>
                    <div class="pin-dot" id="dot-2"></div>
                    <div class="pin-dot" id="dot-3"></div>
                </div>
                <div class="pin-grid">
                    <button type="button" class="pin-btn" onclick="addPin(1)">1</button>
                    <button type="button" class="pin-btn" onclick="addPin(2)">2</button>
                    <button type="button" class="pin-btn" onclick="addPin(3)">3</button>
                    <button type="button" class="pin-btn" onclick="addPin(4)">4</button>
                    <button type="button" class="pin-btn" onclick="addPin(5)">5</button>
                    <button type="button" class="pin-btn" onclick="addPin(6)">6</button>
                    <button type="button" class="pin-btn" onclick="addPin(7)">7</button>
                    <button type="button" class="pin-btn" onclick="addPin(8)">8</button>
                    <button type="button" class="pin-btn" onclick="addPin(9)">9</button>
                    <button type="button" class="pin-btn clear" onclick="clearPin()">Clear</button>
                    <button type="button" class="pin-btn" onclick="addPin(0)">0</button>
                    <button type="button" class="pin-btn enter" onclick="submitPin()">Enter</button>
                </div>
            </form>
        </div>
        <div class="login-panel" id="panel-user">
            <form method="POST">
                <input type="hidden" name="action" value="scoreboard_user_login">
                <input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '') . '">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Enter your password" required>
                </div>
                <button type="submit" class="btn-login">Sign In</button>
            </form>
        </div>
    </div>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script>
        let pin = "";
        function addPin(n) {
            if (pin.length >= 4) return;
            pin += n;
            updateDots();
            if (pin.length === 4) setTimeout(submitPin, 200);
        }
        function clearPin() { pin = ""; updateDots(); }
        function updateDots() {
            for (let i = 0; i < 4; i++) {
                document.getElementById("dot-" + i).classList.toggle("filled", i < pin.length);
            }
        }
        function submitPin() {
            if (pin.length !== 4) return;
            document.getElementById("pin-input").value = pin;
            document.getElementById("pin-form").submit();
        }
        function switchTab(tab) {
            document.querySelectorAll(".login-tab").forEach(t => t.classList.remove("active"));
            document.querySelectorAll(".login-panel").forEach(p => p.classList.remove("active"));
            document.querySelector(".login-tab:nth-child(" + (tab === "pin" ? "1" : "2") + ")").classList.add("active");
            document.getElementById("panel-" + tab).classList.add("active");
        }
    </script></body></html>';
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
$allowed_views = ['scoreboard', 'scoresheet', 'video_board', 'settings'];
if (!in_array($view, $allowed_views)) {
    $view = 'scoreboard';
}
// Settings view is admin-only
if ($view === 'settings' && !$isAdmin) {
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
    $stmt = $pdo->query("SELECT id, name AS team_name, logo_url FROM teams WHERE is_active = 1 ORDER BY name");
    $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist */ }

// ── Fetch opponent teams from game schedules (gameplan module) ──
$opponent_teams = [];
try {
    $stmt = $pdo->query("
        SELECT DISTINCT gs.opponent_team
        FROM game_schedules gs
        WHERE gs.opponent_team IS NOT NULL AND gs.opponent_team != ''
        ORDER BY gs.opponent_team
    ");
    $opponent_teams = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* table may not exist */ }

// ── Resolve team logos for active game ────────────────────
$home_logo_url = '';
$away_logo_url = '';
if ($active_game) {
    $homeTeamId = (int)($active_game['home_team_id'] ?? 0);
    $awayTeamId = (int)($active_game['away_team_id'] ?? 0);
    foreach ($teams as $t) {
        if ($homeTeamId && (int)$t['id'] === $homeTeamId && !empty($t['logo_url'])) {
            $home_logo_url = $t['logo_url'];
        }
        if ($awayTeamId && (int)$t['id'] === $awayTeamId && !empty($t['logo_url'])) {
            $away_logo_url = $t['logo_url'];
        }
    }
}

// ── Music & audio settings ────────────────────────────────
$spotify_configured = false;
$subsonic_configured = false;
$apple_music_configured = false;
$custom_buzzer_url = '';
$custom_horn_url = '';
$network_speakers = [];
try {
    $stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('spotify_client_id', 'spotify_client_secret', 'subsonic_url', 'subsonic_username', 'subsonic_password', 'apple_music_token', 'scoreboard_buzzer_url', 'scoreboard_horn_url', 'scoreboard_network_speakers', 'scoreboard_buzzer_library', 'scoreboard_horn_library')");
    $stmt->execute();
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $s) {
        if ($s['setting_key'] === 'spotify_client_id' && !empty($s['setting_value'])) $spotify_configured = true;
        if ($s['setting_key'] === 'subsonic_url' && !empty($s['setting_value'])) $subsonic_configured = true;
        if ($s['setting_key'] === 'apple_music_token' && !empty($s['setting_value'])) $apple_music_configured = true;
        if ($s['setting_key'] === 'scoreboard_buzzer_url') $custom_buzzer_url = $s['setting_value'] ?? '';
        if ($s['setting_key'] === 'scoreboard_horn_url') $custom_horn_url = $s['setting_value'] ?? '';
        if ($s['setting_key'] === 'scoreboard_network_speakers') $network_speakers = json_decode($s['setting_value'] ?? '[]', true) ?: [];
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
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css?v=<?= filemtime(__DIR__ . '/css/style-guide.css') ?>">
    <link rel="stylesheet" href="css/scoreboard.css?v=<?= filemtime(__DIR__ . '/css/scoreboard.css') ?>">
    <script>
    // Constants must be defined before scoreboard.js executes (defer preserves order)
    var CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
    var ACTIVE_GAME_ID = <?= $active_game ? (int)$active_game['id'] : 'null' ?>;
    var CUSTOM_BUZZER_URL = <?= json_encode($custom_buzzer_url) ?>;
    var CUSTOM_HORN_URL = <?= json_encode($custom_horn_url) ?>;
    var IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    var SB_STAT_TRACKING = <?= ($active_game && !empty($active_game['stat_tracking_enabled'])) ? 'true' : 'false' ?>;
    var SB_HOME_TEAM_NAME = <?= json_encode($active_game['home_team_name'] ?? 'Home') ?>;
    var SB_AWAY_TEAM_NAME = <?= json_encode($active_game['away_team_name'] ?? 'Away') ?>;
    </script>
    <script defer src="js/scoreboard.js?v=<?= filemtime(__DIR__ . '/js/scoreboard.js') ?>"></script>
</head>
<body class="sb-body">
<script>document.documentElement.classList.add('sb-html');</script>

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

<?php elseif ($view === 'settings' && $isAdmin): ?>
<!-- ══════════════════════════════════════════════════════════
     SETTINGS MODE – Admin-only audio & scoreboard configuration
     ══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/views/scoreboard/scoreboard_settings.php'; ?>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     SCOREBOARD MODE – Primary arena display
     ══════════════════════════════════════════════════════════ -->
<?php include __DIR__ . '/views/scoreboard/scoreboard_display.php'; ?>

<?php endif; ?>

<!-- ── Scripts ─────────────────────────────────────────── -->
<script>
// Live clock in topbar – anchored to server time & configured timezone
(function() {
    var sbTimezone = <?= json_encode(date_default_timezone_get()) ?>;
    var sbServerTs = <?= (int)appTime() ?>;
    var sbPageLoad = Date.now();

    function updateClock() {
        var elapsedMs = Date.now() - sbPageLoad;
        var corrected = new Date(sbServerTs * 1000 + elapsedMs);
        var str;
        try {
            str = corrected.toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit', second: '2-digit',
                hour12: true, timeZone: sbTimezone
            });
        } catch (e) {
            // Fallback: apply server TZ offset manually
            var tzOff = <?= (int)date('Z') ?>;
            var adj = new Date(corrected.getTime() + tzOff * 1000);
            var h = adj.getUTCHours(), m = adj.getUTCMinutes(), s = adj.getUTCSeconds();
            var ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            str = h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' ' + ampm;
        }
        var el = document.getElementById('sbClock');
        if (el) el.textContent = str;
    }
    updateClock();
    setInterval(updateClock, 1000);
})();
</script>
</body>
</html>
