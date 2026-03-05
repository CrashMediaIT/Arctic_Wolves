<?php
/**
 * PWA Dashboard - Mobile-optimized shell
 *
 * Provides a mobile-first interface with bottom tab navigation,
 * matching the ACWolvesApp React Native navigation structure.
 * Re-uses the same views/ includes as the desktop dashboard.php.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/site_branding.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/pwa_detect.php';

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
    error_log('CSP extraConnectSrc error (pwa): ' . $e->getMessage());
}

// Set security headers (with RustFS origin if configured)
setSecurityHeaders($extraConnectSrc);

// Generate CSRF token
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || $pdo === null) {
    die("Database connection failed.");
}

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Auth check
if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// Allow switch to desktop
if (isset($_GET['view']) && $_GET['view'] === 'desktop') {
    $_SESSION['pwa_view_override'] = 'desktop';
    header("Location: dashboard.php");
    exit();
}

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Update last_activity
if (!isset($_SESSION['last_activity_update']) || (time() - $_SESSION['last_activity_update']) > 60) {
    try {
        $pdo->prepare("UPDATE login_history SET last_activity = NOW() WHERE user_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1")->execute([$user_id]);
        $_SESSION['last_activity_update'] = time();
    } catch (PDOException $e) { /* ignore */ }
}

// All files are served from RustFS — no local file restoration needed

// Role checks (same as dashboard.php)
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
$isParent      = in_array('parent', $user_roles_list);
$isFrontDesk   = in_array('front_desk_staff', $user_roles_list);
$isHR          = in_array('hr', $user_roles_list);
$isAccounting  = in_array('accounting', $user_roles_list);
$isAnyCoach    = ($isCoach || $isAdmin);
$isTeamStaff   = ($isTeamCoach);
$canAccessPOS  = ($isAdmin || $isFrontDesk);
$canAccessHealthManagement = ($isHealthCoach || $isAdmin);
$canAccessHR   = ($isHR || $isAdmin);
$canAccessAccounting = ($isAccounting || $isAdmin);
$isStaff       = ($isAdmin || $isCoach || $isHealthCoach || $isFrontDesk || $isHR || $isAccounting);

// Persona mode
$isActualAdmin = (($user_role === 'admin') || (isset($_SESSION['persona_original_role']) && $_SESSION['persona_original_role'] === 'admin'));
$personaActive = !empty($_SESSION['persona_active']);

// Page routing (same routing table as dashboard.php)
$page = $_GET['page'] ?? 'home';

if ($page === 'home' && $isFrontDesk) {
    $page = 'front_desk_home';
}
if ($page === 'admin_settings') {
    header("Location: pwa.php?page=system_tools&tab=landing");
    exit();
}
if ($page === 'gameplan_settings') {
    header("Location: pwa.php?page=system_tools&tab=gameplan");
    exit();
}

// Full routing table (identical to dashboard.php)
$allowed_pages = [
    'home'                    => 'views/home.php',
    'stats'                   => 'views/stats.php',
    'goals'                   => 'views/goals.php',
    'messages'                => 'views/messages.php',
    'sessions'                => 'views/sessions.php',
    'upcoming_sessions'       => 'views/sessions.php',
    'booking'                 => 'views/sessions.php',
    'video'                   => 'views/video.php',
    'drill_review'            => 'views/video.php',
    'coaches_reviews'         => 'views/video.php',
    'record_video'            => 'views/video.php',
    'record_drill_video'      => 'views/video_record_drill.php',
    'health'                  => 'views/health.php',
    'strength_conditioning'   => 'views/health.php',
    'nutrition'               => 'views/health.php',
    'team_roster'             => 'views/team_roster.php',
    'coach_calendar'          => 'views/coach_calendar.php',
    'drills'                  => 'views/drills.php',
    'drill_library'           => 'views/drills.php',
    'create_drill'            => 'views/drills.php',
    'import_drill'            => 'views/drills.php',
    'export_import_drills'    => 'views/drills.php',
    'view_drill'              => 'views/view_drill.php',
    'view_practice_plan'      => 'views/view_practice_plan.php',
    'practice'                => 'views/practice.php',
    'practice_library'        => 'views/practice.php',
    'create_practice'         => 'views/practice.php',
    'practice_create'         => 'views/practice.php',
    'practice_import'         => 'views/practice.php',
    'export_import_plans'     => 'views/practice.php',
    'roster'                  => 'views/coach_roster.php',
    'coach_stopwatch'         => 'views/coach_stopwatch.php',
    'coach_shot_speed'        => 'views/coach_shot_speed.php',
    'travel'                  => 'views/travel.php',
    'mileage'                 => 'views/travel.php',
    'mileage_tracker'         => 'views/mileage_tracker.php',
    'finance_dashboard'       => 'views/finance_dashboard.php',
    'accounting_dashboard'    => 'views/finance_dashboard.php',
    'billing_dashboard'       => 'views/finance_dashboard.php',
    'reports'                 => 'views/accounting_reports.php',
    'schedules'               => 'views/accounting_schedules.php',
    'financial_reports'       => 'views/financial_reports.php',
    'credits_refunds'         => 'views/accounting_credits.php',
    'expenses'                => 'views/accounting_expenses.php',
    'products'                => 'views/accounting_products.php',
    'accounts_payable'        => 'views/accounts_payable.php',
    'merchandise_categories'  => 'views/merchandise_categories.php',
    'merchandise_products'    => 'views/merchandise_products.php',
    'pos_terminal'            => 'views/pos_terminal.php',
    'inventory_management'    => 'views/inventory_management.php',
    'pos_online_orders'       => 'views/pos_online_orders.php',
    'shop_orders'             => 'views/shop_orders.php',
    'pos_transactions'        => 'views/pos_transactions.php',
    'pos_time_tracking'       => 'views/pos_time_tracking.php',
    'pos_schedule'            => 'views/pos_schedule.php',
    'staff_time_history'      => 'views/staff_time_history.php',
    'front_desk_home'         => 'views/front_desk_home.php',
    'admin_staff_scheduling'  => 'views/admin_staff_scheduling.php',
    'termination'             => 'views/hr_termination.php',
    'payroll'                 => 'views/hr_payroll.php',
    'onboarding'              => 'views/hr_onboarding.php',
    'employee_contracts'      => 'views/hr_employee_contracts.php',
    'hr_time_tracking'        => 'views/hr_time_tracking.php',
    'complaints'              => 'views/hr_complaints.php',
    'phone_directory'         => 'views/phone_directory.php',
    'sip_settings'            => 'views/sip_settings.php',
    'all_users'               => 'views/admin_users.php',
    'categories'              => 'views/admin_categories.php',
    'admin_age_skill'         => 'views/admin_age_skill.php',
    'eval_framework'          => 'views/admin_eval_framework.php',
    'system_notification'     => 'views/admin_notifications.php',
    // audit_log removed - functionality is in admin_security.php (Audit Log tab)
    'cron_jobs'               => 'views/admin_cron_jobs.php',
    'system_tools'            => 'views/admin_system_tools.php',
    'admin_database_tools'    => 'views/admin_database_tools.php',
    'admin_database_backup'   => 'views/admin_database_backup.php',
    'admin_database_restore'  => 'views/admin_database_restore.php',
    'admin_system_check'      => 'views/admin_system_check.php',
    'admin_permissions'       => 'views/admin_permissions.php',
    'user_permissions'        => 'views/user_permissions.php',
    'admin_locations'         => 'views/admin_locations.php',
    'admin_team_coaches'      => 'views/admin_team_coaches.php',
    'admin_discounts'         => 'views/admin_discounts.php',
    'admin_session_types'     => 'views/admin_session_types.php',
    'admin_email_reports'     => 'views/email_logs.php',
    'marketing'               => 'views/admin_business_cards.php',
    'admin_packages'          => 'views/admin_packages.php',
    'admin_plan_categories'   => 'views/admin_plan_categories.php',
    'admin_coach_termination' => 'views/admin_coach_termination.php',
    'admin_feature_import'    => 'views/admin_feature_import.php',
    'admin_theme_settings'    => 'views/admin_theme_settings.php',
    'admin_security'          => 'views/admin_security.php',
    'admin_wishlist'          => 'views/admin_wishlist.php',
    'business_partners'       => 'views/admin_business_partners.php',
    'ihs_import'              => 'views/ihs_import.php',
    'session_templates'       => 'views/library_sessions.php',
    'athlete_evaluations'     => 'views/athlete_evaluations.php',
    'athlete_goals'           => 'views/athlete_goals.php',
    'coach_evaluations'       => 'views/coach_evaluations.php',
    'coach_goals'             => 'views/coach_goals.php',
    'manage_athletes'         => 'views/manage_athletes.php',
    'athletes'                => 'views/athletes.php',
    'coach_session_evaluations' => 'views/coach_session_evaluations.php',
    'session_evaluation_form'   => 'views/session_evaluation_form.php',
    'coach_pending_reviews'     => 'views/coach_video_reviews.php',
    'coach_video_reviews'       => 'views/coach_video_reviews.php',
    'video_review_detail'       => 'views/video_review_detail.php',
    'evaluations_goals'       => 'views/evaluations_goals.php',
    'evaluations_skills'      => 'views/evaluations_skills.php',
    'notifications'           => 'views/notifications.php',
    'reports_athlete'         => 'views/reports_athlete.php',
    'reports_user'            => 'views/reports_user.php',
    'reports_income'          => 'views/reports_income.php',
    'scheduled_reports'       => 'views/scheduled_reports.php',
    'report_view'             => 'views/report_view.php',
    'create_session'          => 'views/create_session.php',
    'session_history'         => 'views/session_history.php',
    'session_detail'          => 'views/session_detail.php',
    'packages'                => 'views/packages.php',
    'payment_history'         => 'views/payment_history.php',
    'session_payment'         => 'views/session_payment.php',
    'refunds'                 => 'views/refunds.php',
    'workouts'                => 'views/workouts.php',
    'testing'                 => 'views/testing.php',
    'parent_home'             => 'views/parent_home.php',
    'athlete_detail'          => 'views/athlete_detail.php',
    'camp_checkin'            => 'views/parent_camp_checkin.php',
    'library_workouts'        => 'views/library_workouts.php',
    'library_nutrition'       => 'views/library_nutrition.php',
    'health_coach_roster'     => 'views/health_coach_roster.php',
    'shop'                    => 'views/shop.php',
    'profile'                 => 'views/profile.php',
    'settings'                => 'views/settings.php',
    'gameplan'                => 'views/gameplan.php',
];

$view_file = $allowed_pages[$page] ?? 'views/home.php';

// Prefer mobile-native PWA views when available
// Skip PWA override for profile page when edit or change_password params are set
// so the full desktop profile form is used instead
$pwa_view_file = 'views/pwa/' . $page . '.php';
$skipPwaOverride = ($page === 'profile' && isset($_GET['tab']));
if (!$skipPwaOverride && file_exists(__DIR__ . '/' . $pwa_view_file)) {
    $view_file = $pwa_view_file;
}

// Determine active tab
$tab_home     = in_array($page, ['home', 'front_desk_home', 'parent_home']);
$tab_sessions = in_array($page, ['sessions', 'upcoming_sessions', 'booking', 'session_detail', 'create_session', 'session_history', 'session_payment', 'coach_calendar']);
$tab_athletes = in_array($page, ['roster', 'athletes', 'athlete_detail', 'manage_athletes', 'team_roster', 'coach_roster', 'health_coach_roster']);
$tab_more     = (!$tab_home && !$tab_sessions && !$tab_athletes);

// Show back button on detail/sub pages (not main menu pages)
$subPages = ['session_detail', 'create_session', 'session_history', 'session_payment',
             'athlete_detail', 'view_drill', 'view_practice_plan',
             'session_evaluation_form', 'report_view', 'record_drill_video',
             'mileage_tracker', 'staff_time_history'];
$showBackBtn = in_array($page, $subPages);

// Unread notification count
$unreadNotifCount = 0;
try {
    $nStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
    $nStmt->execute([$user_id]);
    $unreadNotifCount = (int) $nStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#6B46C1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Arctic Wolves</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <link rel="stylesheet" href="css/pwa.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    <script src="js/hls-player.js"></script>
    <script src="js/typeahead.js"></script>
    <style>
      /* Override desktop sidebar layout - PWA uses stacked layout */
      .sidebar { display: none !important; }
      .main-content { height: auto !important; overflow: visible !important; margin-left: 0 !important; width: 100% !important; max-width: 100vw !important; padding: 0 !important; }
      .content-area { padding: 0 !important; overflow: visible !important; max-width: 100% !important; }
      /* Ensure the top-bar (parent athlete selector) is mobile-friendly */
      .top-bar { padding: 10px 16px; }
      .athlete-selector { flex-wrap: wrap; }
      /* Prevent desktop layout from causing overflow */
      .dashboard-layout, .app-layout, .page-wrapper { max-width: 100vw !important; overflow-x: hidden !important; }
      /* Override any desktop nav that might leak through */
      .desktop-nav, .sidebar-nav, .nav-group { display: none !important; }
      /* Force all view content to respect mobile bounds */
      .pwa-content > div, .pwa-content > section, .pwa-content > form { max-width: 100% !important; overflow-x: hidden; box-sizing: border-box; }
      /* Prevent absolute/fixed positioned desktop elements from overlapping the tab bar */
      .pwa-content .floating-btn, .pwa-content .fab { position: absolute !important; bottom: auto !important; }
      /* Force inline flex rows to wrap on mobile to prevent collisions */
      .pwa-content [style*="display: flex"], .pwa-content [style*="display:flex"] { flex-wrap: wrap !important; gap: 8px !important; }
      /* Tame inline width declarations that cause horizontal overflow */
      .pwa-content [style*="min-width: 3"], .pwa-content [style*="min-width: 4"],
      .pwa-content [style*="min-width: 5"], .pwa-content [style*="min-width: 6"],
      .pwa-content [style*="min-width: 7"], .pwa-content [style*="min-width: 8"],
      .pwa-content [style*="min-width:3"], .pwa-content [style*="min-width:4"],
      .pwa-content [style*="min-width:5"], .pwa-content [style*="min-width:6"],
      .pwa-content [style*="min-width:7"], .pwa-content [style*="min-width:8"] { min-width: 0 !important; width: 100% !important; }
    </style>
</head>
<body class="pwa-body">

<!-- ── Top Header ──────────────────────────────────────── -->
<header class="pwa-header">
    <?php if ($showBackBtn): ?>
    <button onclick="history.back()" class="pwa-header-back" title="Go back">
        <i class="fas fa-arrow-left"></i>
    </button>
    <?php endif; ?>
    <a href="?page=home" class="pwa-header-logo">
        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo">
        ARCTIC <span class="brand-highlight">WOLVES</span>
    </a>
    <div class="pwa-header-actions">
        <a href="?page=notifications" class="pwa-header-btn" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if ($unreadNotifCount > 0): ?>
            <span class="badge"><?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?></span>
            <?php endif; ?>
        </a>
        <a href="?page=profile" class="pwa-header-btn" title="Profile">
            <i class="fas fa-user"></i>
        </a>
    </div>
</header>

<!-- ── Parent Athlete Selector (if applicable) ─────────── -->
<?php if($isParent): ?>
<div style="padding:8px 16px;background:var(--bg-secondary);border-bottom:1px solid var(--border);">
    <select id="pwa-athlete-select" onchange="switchAthlete(this.value)" style="width:100%;padding:10px;background:var(--bg-main);border:1px solid var(--border);border-radius:8px;color:#fff;font-size:14px;font-family:Inter,sans-serif;">
        <option value="">Select Athlete</option>
        <?php
        $stmt = $pdo->prepare("
            SELECT u.id, u.first_name, u.last_name
            FROM users u
            INNER JOIN parent_athlete_relationships par ON u.id = par.athlete_id
            WHERE par.parent_id = ? AND u.role = 'athlete'
        ");
        $stmt->execute([$user_id]);
        while($athlete = $stmt->fetch()):
            $athlete = decryptUserRow($athlete);
            $athlete['name'] = trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? ''));
            $selected = (isset($_SESSION['viewing_athlete_id']) && $_SESSION['viewing_athlete_id'] == $athlete['id']) ? 'selected' : '';
        ?>
        <option value="<?= $athlete['id'] ?>" <?= $selected ?>><?= htmlspecialchars($athlete['name']) ?></option>
        <?php endwhile; ?>
    </select>
</div>
<?php endif; ?>

<!-- ── Scrollable Content Area ─────────────────────────── -->
<div class="pwa-content" id="pwaContent">
    <?php if ($page === 'pwa_more'): ?>
        <!-- "More" menu page: show role-based navigation items -->
        <?php include __DIR__ . '/pwa_more_menu.php'; ?>
    <?php else: ?>
        <?php 
        if (file_exists($view_file)) { include $view_file; }
        else { echo "<div class='pwa-empty'><i class='fas fa-exclamation-triangle'></i><p>Module not available</p></div>"; }
        ?>
    <?php endif; ?>
</div>

<!-- ── PWA Install Banner ──────────────────────────────── -->
<div class="pwa-install-banner" id="installBanner">
    <i class="fas fa-download" style="font-size:20px;color:var(--primary-light);"></i>
    <p>Install Arctic Wolves for a better experience</p>
    <button class="pwa-install-btn" id="installBtn">Install</button>
    <button onclick="document.getElementById('installBanner').style.display='none'" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;padding:4px;">&times;</button>
</div>

<!-- ── Bottom Tab Bar ──────────────────────────────────── -->
<nav class="pwa-tab-bar">
    <a href="?page=home" class="pwa-tab <?= $tab_home ? 'active' : '' ?>">
        <i class="fas fa-house"></i>
        <span>Home</span>
    </a>
    <a href="?page=sessions" class="pwa-tab <?= $tab_sessions ? 'active' : '' ?>">
        <i class="fas fa-calendar-check"></i>
        <span>Sessions</span>
    </a>
    <?php if ($isAnyCoach || $isTeamStaff || $canAccessHealthManagement): ?>
    <a href="?page=roster" class="pwa-tab <?= $tab_athletes ? 'active' : '' ?>">
        <i class="fas fa-users"></i>
        <span>Athletes</span>
    </a>
    <?php endif; ?>
    <a href="?page=pwa_more" class="pwa-tab <?= $tab_more ? 'active' : '' ?>">
        <i class="fas fa-bars"></i>
        <span>More</span>
    </a>
</nav>

<script>
// Parent athlete switcher
function switchAthlete(athleteId) {
    if (athleteId) {
        fetch('process_switch_athlete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'athlete_id=' + athleteId
        })
        .then(r => r.json())
        .then(data => { if (data.success) location.reload(); });
    }
}

// Service worker registration (relative path for subdirectory deployments)
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('pwa-sw.js').catch(() => {});
}

// PWA install prompt
let deferredPrompt;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    document.getElementById('installBanner').classList.add('show');
});
document.getElementById('installBtn')?.addEventListener('click', () => {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then(() => {
            deferredPrompt = null;
            document.getElementById('installBanner').classList.remove('show');
        });
    }
});

// Request notification permission (only once per device)
if ('Notification' in window && Notification.permission === 'default' && !localStorage.getItem('aw_notif_prompted')) {
    setTimeout(() => {
        localStorage.setItem('aw_notif_prompted', '1');
        Notification.requestPermission();
    }, 5000);
}

// Camera access helper (used by video recording views)
window.pwaRequestCamera = function(videoElement) {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: true })
            .then(stream => {
                if (videoElement) {
                    videoElement.srcObject = stream;
                }
                return stream;
            });
    }
    return Promise.reject(new Error('Camera not available'));
};

// Unread message badge polling
(function() {
    function updateMsgBadge() {
        fetch('process_messages.php?action=unread_count')
            .then(r => r.json())
            .then(data => {
                const badge = document.getElementById('nav-msg-badge');
                if (badge && data.success) {
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(() => {});
    }
    updateMsgBadge();
    setInterval(updateMsgBadge, 30000);
})();
</script>

<!-- Main Application JavaScript -->
<script src="js/app.js"></script>

</body>
</html>
