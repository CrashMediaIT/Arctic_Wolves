<?php
/**
 * Game Plan TV – 10-foot Viewer-Only PWA
 *
 * Designed for Smart TVs, set-top boxes, and large displays.
 * The TV acts as a viewer-only display that must be paired with a
 * controller device before showing any content. No navigation is
 * shown — all navigation is done from the controller device.
 *
 * Flow:
 *   1. TV shows pairing screen with a code entry form
 *   2. Controller generates a pair code (from gameplan.php or pwa.php)
 *   3. TV enters the code to pair as viewer
 *   4. Once paired, TV displays the page the controller navigates to
 *   5. Controller can freeze the viewer to navigate privately
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

// ── Pairing gate ──────────────────────────────────────────
// Check if this TV session has an active pair (stored in session)
$tv_pair_id   = $_SESSION['tv_pair_id'] ?? 0;
$tv_paired    = false;
$tv_pair_code = '';
$tv_is_frozen = false;
$tv_controller_page = 'home';

if ($tv_pair_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, pair_code, status, is_frozen, controller_page
            FROM vr_device_pairs WHERE id = ? AND status IN ('paired', 'active')
        ");
        $stmt->execute([$tv_pair_id]);
        $pair_row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($pair_row) {
            $tv_paired    = true;
            $tv_pair_code = $pair_row['pair_code'];
            $tv_is_frozen = (bool)$pair_row['is_frozen'];
            $tv_controller_page = $pair_row['controller_page'] ?? 'home';
        } else {
            // Pair ended or invalid — clear session
            unset($_SESSION['tv_pair_id']);
            $tv_pair_id = 0;
        }
    } catch (PDOException $e) { error_log('TV pair check: ' . $e->getMessage()); }
}

// Handle pair code submission (viewer joining)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tv_action'] ?? '') === 'join_viewer') {
    $join_code = strtoupper(trim($_POST['pair_code'] ?? ''));
    $submitted_token = $_POST['csrf_token'] ?? '';
    // Validate: alphanumeric only, max 10 chars
    if (!empty($join_code) && strlen($join_code) <= 10 && preg_match('/^[A-Z0-9]+$/', $join_code) && CSRFProtection::validateToken($submitted_token)) {
        $viewer_token = bin2hex(random_bytes(32));
        try {
            $stmt = $pdo->prepare("
                UPDATE vr_device_pairs SET viewer_token = ?, status = 'paired'
                WHERE pair_code = ? AND status = 'waiting'
            ");
            $stmt->execute([$viewer_token, $join_code]);
            if ($stmt->rowCount() > 0) {
                // Fetch the pair id to store in session
                $stmt2 = $pdo->prepare("SELECT id FROM vr_device_pairs WHERE pair_code = ? AND viewer_token = ?");
                $stmt2->execute([$join_code, $viewer_token]);
                $joined = $stmt2->fetch(PDO::FETCH_ASSOC);
                if ($joined) {
                    $_SESSION['tv_pair_id'] = (int)$joined['id'];
                    logSecurityEvent('tv_viewer_paired', "TV joined pair $join_code as viewer", $user_id);
                }
                header('Location: /gameplan_tv.php');
                exit;
            }
        } catch (PDOException $e) { error_log('TV join: ' . $e->getMessage()); }
    }
    // If we get here, pairing failed
    $tv_pair_error = 'Invalid or expired pair code. Please try again.';
}

// Handle unpair action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['tv_action'] ?? '') === 'unpair') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    if (CSRFProtection::validateToken($submitted_token)) {
        unset($_SESSION['tv_pair_id']);
        $tv_pair_id = 0;
        $tv_paired = false;
        header('Location: /gameplan_tv.php');
        exit;
    }
}

// ── Page routing (only used when paired) ──────────────────
// When paired and not frozen, use the controller's navigated page.
// When frozen, keep showing the last page.
$page = 'home';
if ($tv_paired) {
    if ($tv_is_frozen) {
        // Frozen: keep current page from session, don't follow controller
        $page = $_SESSION['tv_frozen_page'] ?? $tv_controller_page;
    } else {
        $page = $tv_controller_page;
        $_SESSION['tv_frozen_page'] = $page; // save for when frozen
    }
}

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

if ($isAdmin) {
    $allowed_pages['permissions'] = 'views/gameplan/gp_permissions.php';
}

$view_file = $allowed_pages[$page] ?? $allowed_pages['home'];

// Load recent videos (needed by sub-views)
$recentVideos = [];
if ($tv_paired) {
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
}

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

// ── Partial rendering for AJAX content updates ────────────
// When ?partial=1 is set, return only the view content (no header/footer)
// Used by the TV viewer's AJAX polling to swap content without full page reload
if (!empty($_GET['partial']) && $tv_paired) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('X-TV-Page: ' . htmlspecialchars($page));
    if (file_exists($view_file)) {
        include $view_file;
    } else {
        echo '<div class="tv-empty"><i class="fas fa-exclamation-triangle"></i><p>Module not available</p></div>';
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#6B46C1">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
    <title>Game Plan TV – Arctic Wolves</title>
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="manifest" href="manifest-gameplan-tv.json">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css?v=<?= filemtime(__DIR__ . '/css/style-guide.css') ?>">
    <link rel="stylesheet" href="css/components.css?v=<?= filemtime(__DIR__ . '/css/components.css') ?>">
    <link rel="stylesheet" href="views/shared_styles.css?v=<?= filemtime(__DIR__ . '/views/shared_styles.css') ?>">
    <link rel="stylesheet" href="css/gameplan-tv.css?v=<?= filemtime(__DIR__ . '/css/gameplan-tv.css') ?>">
</head>
<body class="tv-body">

<?php if (!$tv_paired): ?>
<!-- ══════════════════════════════════════════════════════════
     PAIRING SCREEN — shown until a controller pairs this TV
     ══════════════════════════════════════════════════════════ -->
<div class="tv-pair-screen">
    <div class="tv-pair-card">
        <div class="tv-pair-logo">
            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves">
        </div>
        <h1 class="tv-pair-title">
            <i class="fas fa-tv"></i> Game Plan TV
        </h1>
        <p class="tv-pair-subtitle">Enter the pair code from your controller device to connect this display.</p>

        <?php if (!empty($tv_pair_error)): ?>
        <div class="tv-pair-error">
            <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($tv_pair_error) ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="/gameplan_tv.php" class="tv-pair-form" autocomplete="off">
            <input type="hidden" name="tv_action" value="join_viewer">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="text" name="pair_code" class="tv-pair-input" placeholder="PAIR CODE" maxlength="10" required autofocus autocomplete="off">
            <button type="submit" class="tv-pair-btn">
                <i class="fas fa-link"></i> Connect Display
            </button>
        </form>

        <div class="tv-pair-steps">
            <div class="tv-pair-step">
                <span class="tv-pair-step-num">1</span>
                <span>Open <strong>Game Plan</strong> on your phone or laptop</span>
            </div>
            <div class="tv-pair-step">
                <span class="tv-pair-step-num">2</span>
                <span>Go to <strong>Video Review → Device Pairing</strong></span>
            </div>
            <div class="tv-pair-step">
                <span class="tv-pair-step-num">3</span>
                <span>Tap <strong>Generate Pair Code</strong> and enter it here</span>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ══════════════════════════════════════════════════════════
     PAIRED VIEWER — no sidebar, no navigation, content only
     ══════════════════════════════════════════════════════════ -->
<div class="tv-main tv-viewer-mode">
    <header class="tv-topbar">
        <div class="tv-topbar-title">
            <i class="fas fa-chess-board"></i>
            <span class="tv-page-label"><?= htmlspecialchars($current_label) ?></span>
            <?php if ($tv_is_frozen): ?>
            <span class="tv-frozen-badge"><i class="fas fa-snowflake"></i> Frozen</span>
            <?php endif; ?>
        </div>
        <div class="tv-topbar-actions">
            <span class="tv-pair-badge">
                <i class="fas fa-link"></i>
                <span class="tv-pair-badge-code"><?= htmlspecialchars($tv_pair_code) ?></span>
            </span>
            <form method="POST" action="/gameplan_tv.php" style="display:inline;">
                <input type="hidden" name="tv_action" value="unpair">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <button type="submit" class="tv-unpair-btn" title="Disconnect from controller">
                    <i class="fas fa-unlink"></i>
                </button>
            </form>
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
<?php endif; ?>

<!-- ── Scripts ─────────────────────────────────────────── -->
<script>
// Live clock in topbar – anchored to server time & configured timezone
(function() {
    var tvTimezone = '<?= htmlspecialchars(date_default_timezone_get(), ENT_QUOTES) ?>';
    var tvServerTs = <?= (int)appTime() ?>;
    var tvPageLoad = Date.now();

    function updateClock() {
        var elapsedMs = Date.now() - tvPageLoad;
        var corrected = new Date(tvServerTs * 1000 + elapsedMs);
        var str;
        try {
            str = corrected.toLocaleTimeString('en-US', {
                hour: 'numeric', minute: '2-digit',
                hour12: true, timeZone: tvTimezone
            });
        } catch (e) {
            // Fallback: apply server TZ offset manually
            var tzOff = <?= (int)date('Z') ?>;
            var adj = new Date(corrected.getTime() + tzOff * 1000);
            var h = adj.getUTCHours(), m = adj.getUTCMinutes();
            var ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            str = h + ':' + (m < 10 ? '0' : '') + m + ' ' + ampm;
        }
        var el = document.getElementById('tvClock');
        if (el) el.textContent = str;
    }
    updateClock();
    setInterval(updateClock, 10000);
})();

<?php if ($tv_paired): ?>
// Poll for controller page changes and freeze state
(function() {
    var pairId = <?= (int)$tv_pair_id ?>;
    var currentPage = '<?= htmlspecialchars($page, ENT_QUOTES) ?>';
    var isFrozen = <?= $tv_is_frozen ? 'true' : 'false' ?>;
    var tvTransitioning = false;

    // AJAX content swap — fetch partial HTML and replace .tv-content
    function swapContent(newPage) {
        if (tvTransitioning) return;
        tvTransitioning = true;
        var container = document.querySelector('.tv-content');
        if (!container) { tvTransitioning = false; window.location.reload(); return; }
        container.style.transition = 'opacity 0.2s ease';
        container.style.opacity = '0';
        setTimeout(function() {
            fetch('/gameplan_tv.php?partial=1&_=' + Date.now())
                .then(function(r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.text();
                })
                .then(function(html) {
                    container.innerHTML = html;
                    // innerHTML doesn't execute <script> tags — evaluate them manually
                    var scripts = container.querySelectorAll('script');
                    for (var i = 0; i < scripts.length; i++) {
                        var oldScript = scripts[i];
                        var newScript = document.createElement('script');
                        if (oldScript.src) {
                            newScript.src = oldScript.src;
                        } else {
                            newScript.textContent = oldScript.textContent;
                        }
                        oldScript.parentNode.replaceChild(newScript, oldScript);
                    }
                    container.style.opacity = '1';
                    currentPage = newPage;
                    tvTransitioning = false;
                    // Update page label in topbar
                    var label = document.querySelector('.tv-page-label');
                    if (label) {
                        var labels = <?= json_encode($page_labels) ?>;
                        label.textContent = labels[newPage] || 'Game Plan';
                    }
                })
                .catch(function() {
                    // Fallback to full reload on AJAX failure
                    window.location.reload();
                });
        }, 200);
    }

    function pollPairState() {
        fetch('/api_tv_pair_state.php?pair_id=' + pairId + '&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.active) {
                    // Pair ended — redirect to show pairing screen
                    window.location.href = '/gameplan_tv.php';
                    return;
                }
                // If not frozen and controller changed page, swap content via AJAX
                if (!data.is_frozen && data.controller_page && data.controller_page !== currentPage) {
                    swapContent(data.controller_page);
                }
                // If freeze state changed, update local state and UI
                if (data.is_frozen !== isFrozen) {
                    isFrozen = data.is_frozen;
                    var badge = document.querySelector('.tv-frozen-badge');
                    if (badge) {
                        badge.style.display = isFrozen ? 'inline-flex' : 'none';
                    } else if (isFrozen) {
                        var title = document.querySelector('.tv-topbar-title');
                        if (title) {
                            var newBadge = document.createElement('span');
                            newBadge.className = 'tv-frozen-badge';
                            newBadge.innerHTML = '<i class="fas fa-snowflake"></i> Frozen';
                            title.appendChild(newBadge);
                        }
                    }
                }
            })
            .catch(function() { /* retry on next poll */ });
    }

    setInterval(pollPairState, 3000);
    pollPairState();
})();
<?php endif; ?>

// Auto-uppercase pair code input
(function() {
    var input = document.querySelector('.tv-pair-input');
    if (input) {
        input.addEventListener('input', function() {
            this.value = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '');
        });
    }
})();

// Service worker registration
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('pwa-sw.js').catch(function() {});
}
</script>

<?php if ($tv_paired): ?>
<!-- ── Global Telestration Receive Overlay (TV Viewer) ──────── -->
<canvas id="tvTeleCanvas" style="position:fixed;top:0;left:0;width:100vw;height:100vh;z-index:9000;pointer-events:none;display:block;"></canvas>
<script>
(function() {
    var canvas = document.getElementById('tvTeleCanvas');
    if (!canvas) return;
    var pairId = <?= (int)$tv_pair_id ?>;
    var teleSeq = -1;

    function resizeCanvas() {
        var w = window.innerWidth, h = window.innerHeight;
        if (canvas.width !== w || canvas.height !== h) {
            canvas.width = w; canvas.height = h;
        }
    }
    resizeCanvas();
    window.addEventListener('resize', resizeCanvas);

    function pollTelestration() {
        fetch('/api_tv_pair_state.php?pair_id=' + pairId + '&include_telestration=1&_=' + Date.now())
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var seq = parseInt(data.telestration_seq, 10) || 0;
                if (seq !== teleSeq) {
                    teleSeq = seq;
                    if (data.telestration_data) {
                        var img = new Image();
                        img.onload = function() {
                            resizeCanvas();
                            // Get fresh context after potential resize (canvas resize resets state)
                            var ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                            ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                        };
                        img.onerror = function() {
                            var ctx = canvas.getContext('2d');
                            ctx.clearRect(0, 0, canvas.width, canvas.height);
                        };
                        img.src = data.telestration_data;
                    } else {
                        var ctx = canvas.getContext('2d');
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                    }
                }
            })
            .catch(function() { /* retry next poll */ });
    }

    setInterval(pollTelestration, 2000);
    pollTelestration();
})();
</script>
<?php endif; ?>

<script src="js/app.js?v=<?= filemtime(__DIR__ . '/js/app.js') ?>"></script>
</body>
</html>
