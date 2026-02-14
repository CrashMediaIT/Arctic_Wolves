<?php
/**
 * PWA Tablet Dashboard – Hybrid layout
 *
 * Combines the desktop sidebar navigation with a touch-friendly
 * content area and PWA features (service worker, install banner,
 * camera access, notifications). Uses the same views/ includes
 * and routing table as dashboard.php.
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/pwa_detect.php';

// Set security headers
setSecurityHeaders();

// Generate CSRF token
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || $pdo === null) {
    die("Database connection failed.");
}

// Auth check
if (!isset($_SESSION['logged_in'])) {
    header("Location: index.php");
    exit();
}

// Allow switch to desktop or phone PWA
if (isset($_GET['view'])) {
    $v = strtolower(trim($_GET['view']));
    if ($v === 'desktop') {
        $_SESSION['pwa_view_override'] = 'desktop';
        header("Location: dashboard.php");
        exit();
    }
    if ($v === 'pwa') {
        $_SESSION['pwa_view_override'] = 'pwa';
        header("Location: pwa.php");
        exit();
    }
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

// Role checks (identical to dashboard.php)
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
$isAnyCoach    = ($isCoach || $isAdmin);
$isTeamStaff   = ($isTeamCoach);
$canAccessPOS  = ($isAdmin || $isFrontDesk);
$canAccessHealthManagement = ($isHealthCoach || $isAdmin);

// Persona mode
$isActualAdmin = (($user_role === 'admin') || (isset($_SESSION['persona_original_role']) && $_SESSION['persona_original_role'] === 'admin'));
$personaActive = !empty($_SESSION['persona_active']);

// Page routing (identical to dashboard.php)
$page = $_GET['page'] ?? 'home';

if ($page === 'home' && $isFrontDesk) {
    $page = 'front_desk_home';
}
if ($page === 'admin_settings') {
    header("Location: pwa_tablet.php?page=system_tools&tab=landing");
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
    'all_users'               => 'views/admin_users.php',
    'categories'              => 'views/admin_categories.php',
    'admin_age_skill'         => 'views/admin_age_skill.php',
    'eval_framework'          => 'views/admin_eval_framework.php',
    'system_notification'     => 'views/admin_notifications.php',
    'audit_log'               => 'views/admin_audit_logs.php',
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
    'evaluations_goals'       => 'views/evaluations_goals.php',
    'evaluations_skills'      => 'views/evaluations_skills.php',
    'notifications'           => 'views/notifications.php',
    'reports_athlete'         => 'views/reports_athlete.php',
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
    'gameplan_settings'       => 'views/gameplan_settings.php',
];

$view_file = $allowed_pages[$page] ?? 'views/home.php';
// Unread notification count
$unreadNotifCount = 0;
try {
    $nStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
    $nStmt->execute([$user_id]);
    $unreadNotifCount = (int) $nStmt->fetchColumn();
} catch (PDOException $e) { /* ignore */ }

// Check agreements (same as dashboard.php)
$showAgreementsModal = false;
$agreementTemplates = [];
try {
    $agreeCheck = $pdo->prepare("SELECT agreements_accepted FROM users WHERE id = ?");
    $agreeCheck->execute([$user_id]);
    $agreeRow = $agreeCheck->fetch(PDO::FETCH_ASSOC);
    if ($agreeRow && intval($agreeRow['agreements_accepted'] ?? 0) === 0) {
        $showAgreementsModal = true;
        $tplStmt = $pdo->query("
            SELECT t.* FROM agreement_templates t
            INNER JOIN (
                SELECT MAX(id) AS max_id
                FROM agreement_templates
                WHERE is_active = 1
                GROUP BY agreement_type
            ) latest ON t.id = latest.max_id
            ORDER BY t.agreement_type
        ");
        $agreementTemplates = $tplStmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) { /* ignore */ }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#6B46C1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <title>Arctic Wolves</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/pwa/icon-192.png">
    <link rel="apple-touch-icon" href="assets/pwa/icon-192.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <link rel="stylesheet" href="css/pwa-tablet.css">
    <script src="js/typeahead.js"></script>
</head>
<body class="pwa-tablet-body">

<!-- ── Sidebar Overlay (narrow tablets) ────────────────── -->
<div class="tablet-sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

<!-- ── Sidebar Navigation (same as desktop dashboard.php) ─ -->
<aside class="tablet-sidebar" id="tabletSidebar">
    <a href="?page=home" class="brand">
        <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo">
        ARCTIC <span>WOLVES</span>
    </a>

    <?php if ($isActualAdmin): ?>
    <!-- Persona Switcher -->
    <div class="tablet-persona-switcher" id="personaSwitcher">
        <?php if ($personaActive): ?>
        <div class="tablet-persona-banner">
            <i class="fas fa-mask"></i> <span>Persona Mode</span>
        </div>
        <?php endif; ?>
        <label class="persona-label">
            <i class="fas fa-user-secret"></i> View As Role
        </label>
        <select id="personaRoleSelect">
            <option value="admin" <?= $user_role === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="coach" <?= $user_role === 'coach' ? 'selected' : '' ?>>Coach</option>
            <option value="health_coach" <?= $user_role === 'health_coach' ? 'selected' : '' ?>>Health Coach</option>
            <option value="team_coach" <?= $user_role === 'team_coach' ? 'selected' : '' ?>>Team Coach</option>
            <option value="athlete" <?= $user_role === 'athlete' ? 'selected' : '' ?>>Athlete</option>
            <option value="parent" <?= $user_role === 'parent' ? 'selected' : '' ?>>Parent</option>
            <option value="front_desk_staff" <?= $user_role === 'front_desk_staff' ? 'selected' : '' ?>>Front Desk</option>
        </select>
        <?php if ($personaActive): ?>
        <button type="button" id="exitPersonaBtn" class="tablet-persona-exit-btn">
            <i class="fas fa-arrow-rotate-left"></i> Back to Admin
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- MAIN MENU -->
    <div class="nav-group">
        <span class="nav-label">Main Menu</span>
        <nav class="nav-menu">
            <a href="?page=home" class="nav-link <?= $page=='home'?'active':'' ?>"><i class="fa-solid fa-house"></i> Home</a>
            <a href="?page=stats" class="nav-link <?= in_array($page, ['stats', 'goals'])?'active':'' ?>"><i class="fa-solid fa-chart-line"></i> Performance Stats</a>
            <a href="?page=messages" class="nav-link <?= $page=='messages'?'active':'' ?>">
                <i class="fa-solid fa-comments"></i> Messages
                <span id="nav-msg-badge" style="display:none;background:var(--primary);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:auto;"></span>
            </a>
            <a href="?page=sessions" class="nav-link <?= in_array($page, ['sessions','upcoming_sessions','booking'])?'active':'' ?>"><i class="fa-solid fa-calendar-check"></i> Sessions</a>
            <a href="?page=video" class="nav-link <?= in_array($page, ['video','drill_review','coaches_reviews'])?'active':'' ?>"><i class="fa-solid fa-video"></i> Video</a>
            <a href="?page=health" class="nav-link <?= in_array($page, ['health','strength_conditioning','nutrition'])?'active':'' ?>"><i class="fa-solid fa-heart-pulse"></i> Health</a>
            <a href="?page=shop" class="nav-link <?= $page=='shop'?'active':'' ?>"><i class="fa-solid fa-store"></i> Shop</a>
            <a href="?page=payment_history" class="nav-link <?= $page=='payment_history'?'active':'' ?>"><i class="fa-solid fa-receipt"></i> Purchase History</a>
        </nav>
    </div>

    <!-- TEAM -->
    <?php if($isTeamStaff): ?>
    <div class="nav-group">
        <span class="nav-label">Team</span>
        <nav class="nav-menu">
            <a href="?page=team_roster" class="nav-link <?= $page=='team_roster'?'active':'' ?>"><i class="fa-solid fa-users"></i> Roster</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- COACHES CORNER -->
    <?php if($isAnyCoach): ?>
    <div class="nav-group">
        <span class="nav-label">Coaches Corner</span>
        <nav class="nav-menu">
            <a href="?page=coach_calendar" class="nav-link <?= $page=='coach_calendar'?'active':'' ?>"><i class="fa-solid fa-calendar"></i> Calendar</a>
            <a href="?page=drills" class="nav-link <?= in_array($page, ['drills','drill_library','create_drill','import_drill','export_import_drills'])?'active':'' ?>"><i class="fa-solid fa-clipboard-list"></i> Drills</a>
            <a href="?page=practice" class="nav-link <?= in_array($page, ['practice','practice_library','create_practice','practice_create','practice_import','export_import_plans'])?'active':'' ?>"><i class="fa-solid fa-file-lines"></i> Practice Plans</a>
            <a href="?page=roster" class="nav-link <?= $page=='roster'?'active':'' ?>"><i class="fa-solid fa-users-gear"></i> Roster</a>
            <a href="?page=coach_stopwatch" class="nav-link <?= $page=='coach_stopwatch'?'active':'' ?>"><i class="fa-solid fa-stopwatch"></i> Stopwatch</a>
            <a href="?page=coach_shot_speed" class="nav-link <?= $page=='coach_shot_speed'?'active':'' ?>"><i class="fa-solid fa-hockey-puck"></i> Shot Speed</a>
            <a href="?page=coach_session_evaluations" class="nav-link <?= in_array($page, ['coach_session_evaluations','session_evaluation_form'])?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Session Evaluations</a>
            <a href="?page=travel" class="nav-link <?= in_array($page, ['travel','mileage'])?'active':'' ?>"><i class="fa-solid fa-plane"></i> Travel</a>
            <a href="?page=record_drill_video" class="nav-link <?= $page=='record_drill_video'?'active':'' ?>"><i class="fa-solid fa-video"></i> Video Recording</a>
            <a href="/gameplan.php" class="nav-link"><i class="fa-solid fa-chess-board"></i> Game Plan</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- HEALTH MANAGEMENT -->
    <?php if($canAccessHealthManagement): ?>
    <div class="nav-group">
        <span class="nav-label">Health</span>
        <nav class="nav-menu">
            <a href="?page=library_workouts" class="nav-link <?= $page=='library_workouts'?'active':'' ?>"><i class="fa-solid fa-dumbbell"></i> Strength & Conditioning</a>
            <a href="?page=library_nutrition" class="nav-link <?= $page=='library_nutrition'?'active':'' ?>"><i class="fa-solid fa-utensils"></i> Nutrition</a>
            <a href="?page=roster" class="nav-link <?= $page=='roster' || $page=='health_coach_roster'?'active':'' ?>"><i class="fa-solid fa-users-gear"></i> Roster</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- ACCOUNTING & REPORTS -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">Accounting & Reports</span>
        <nav class="nav-menu">
            <a href="?page=finance_dashboard" class="nav-link <?= in_array($page, ['finance_dashboard', 'accounting_dashboard', 'billing_dashboard', 'pos_transactions', 'shop_orders'])?'active':'' ?>"><i class="fa-solid fa-chart-pie"></i> Finance Dashboard</a>
            <a href="?page=financial_reports" class="nav-link <?= in_array($page, ['reports', 'schedules', 'financial_reports'])?'active':'' ?>"><i class="fa-solid fa-chart-pie"></i> Financial Reports</a>
            <a href="?page=credits_refunds" class="nav-link <?= $page=='credits_refunds'?'active':'' ?>"><i class="fa-solid fa-money-bill-transfer"></i> Credits & Refunds</a>
            <a href="?page=expenses" class="nav-link <?= $page=='expenses'?'active':'' ?>"><i class="fa-solid fa-receipt"></i> Expenses</a>
            <a href="?page=products" class="nav-link <?= $page=='products'?'active':'' ?>"><i class="fa-solid fa-box-open"></i> Products</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- POS -->
    <?php if($canAccessPOS): ?>
    <div class="nav-group">
        <span class="nav-label">Point of Sale</span>
        <nav class="nav-menu">
            <a href="?page=pos_terminal" class="nav-link <?= $page=='pos_terminal'?'active':'' ?>"><i class="fa-solid fa-cash-register"></i> POS Terminal</a>
            <a href="?page=pos_online_orders" class="nav-link <?= $page=='pos_online_orders'?'active':'' ?>"><i class="fa-solid fa-shipping-fast"></i> Online Orders</a>
            <a href="?page=pos_time_tracking" class="nav-link <?= $page=='pos_time_tracking'?'active':'' ?>"><i class="fa-solid fa-clock"></i> Time Tracking</a>
            <a href="?page=pos_schedule" class="nav-link <?= $page=='pos_schedule'?'active':'' ?>"><i class="fa-solid fa-calendar-alt"></i> My Schedule</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- HR -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">HR</span>
        <nav class="nav-menu">
            <a href="?page=admin_staff_scheduling" class="nav-link <?= $page=='admin_staff_scheduling'?'active':'' ?>"><i class="fa-solid fa-calendar-check"></i> Staff Scheduling</a>
            <a href="?page=hr_time_tracking" class="nav-link <?= $page=='hr_time_tracking'?'active':'' ?>"><i class="fa-solid fa-clock"></i> Time Tracking</a>
            <a href="?page=payroll" class="nav-link <?= $page=='payroll'?'active':'' ?>"><i class="fa-solid fa-money-check-dollar"></i> Payroll</a>
            <a href="?page=onboarding" class="nav-link <?= $page=='onboarding'?'active':'' ?>"><i class="fa-solid fa-user-plus"></i> Onboarding</a>
            <a href="?page=employee_contracts" class="nav-link <?= $page=='employee_contracts'?'active':'' ?>"><i class="fa-solid fa-file-signature"></i> Contracts</a>
            <a href="?page=complaints" class="nav-link <?= $page=='complaints'?'active':'' ?>"><i class="fa-solid fa-exclamation-triangle"></i> Complaints</a>
            <a href="?page=termination" class="nav-link <?= $page=='termination'?'active':'' ?>"><i class="fa-solid fa-user-slash"></i> Termination</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- ADMINISTRATION -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">Administration</span>
        <nav class="nav-menu">
            <a href="?page=all_users" class="nav-link <?= $page=='all_users'?'active':'' ?>"><i class="fa-solid fa-users"></i> All Users</a>
            <a href="?page=categories" class="nav-link <?= $page=='categories'?'active':'' ?>"><i class="fa-solid fa-layer-group"></i> Resource Management</a>
            <a href="?page=eval_framework" class="nav-link <?= $page=='eval_framework'?'active':'' ?>"><i class="fa-solid fa-clipboard-check"></i> Eval Framework</a>
            <a href="?page=system_notification" class="nav-link <?= $page=='system_notification'?'active':'' ?>"><i class="fa-solid fa-bell"></i> System Notification</a>
            <a href="?page=admin_security" class="nav-link <?= $page=='admin_security'?'active':'' ?>"><i class="fa-solid fa-shield-halved"></i> Security</a>
            <a href="?page=system_tools" class="nav-link <?= $page=='system_tools'?'active':'' ?>"><i class="fa-solid fa-screwdriver-wrench"></i> System Tools</a>
            <a href="?page=audit_log" class="nav-link <?= $page=='audit_log'?'active':'' ?>"><i class="fa-solid fa-clipboard-list"></i> Audit Log</a>
            <a href="?page=gameplan_settings" class="nav-link <?= $page=='gameplan_settings'?'active':'' ?>"><i class="fa-solid fa-chess-board"></i> Game Plan Settings</a>
            <a href="?page=marketing" class="nav-link <?= $page=='marketing'?'active':'' ?>"><i class="fa-solid fa-bullhorn"></i> Marketing</a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- SIDEBAR FOOTER -->
    <div class="sidebar-footer">
        <a href="?page=profile" class="nav-link <?= $page=='profile'?'active':'' ?>"><i class="fa-solid fa-user-gear"></i> Profile Settings</a>
        <a href="?view=desktop" class="nav-link"><i class="fa-solid fa-desktop"></i> Desktop View</a>
        <a href="logout.php" class="nav-link" style="color:#ef4444;"><i class="fa-solid fa-power-off"></i> Sign Out</a>
        <div style="display:flex;align-items:center;gap:10px;padding:8px 4px;border-top:1px solid var(--border);margin-top:8px;">
            <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <div style="font-size:11px;">
                <strong><?= htmlspecialchars($user_name) ?></strong><br>
                <span style="color:var(--text-secondary);text-transform:capitalize;"><?= str_replace('_', ' ', $user_role) ?></span>
            </div>
        </div>
    </div>
</aside>

<!-- ── Main Content Area ───────────────────────────────── -->
<div class="tablet-main">
    <!-- Top Header Bar -->
    <header class="tablet-header">
        <div class="tablet-header-left">
            <button class="tablet-sidebar-toggle" id="sidebarToggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
                <i class="fas fa-bars"></i>
            </button>
            <span class="tablet-page-title"><?= ucwords(str_replace('_', ' ', $page)) ?></span>
        </div>
        <div class="tablet-header-right">
            <a href="?page=notifications" class="tablet-header-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($unreadNotifCount > 0): ?>
                <span class="badge"><?= $unreadNotifCount > 99 ? '99+' : $unreadNotifCount ?></span>
                <?php endif; ?>
            </a>
            <a href="?page=messages" class="tablet-header-btn" title="Messages">
                <i class="fas fa-comments"></i>
            </a>
            <a href="?page=profile" class="tablet-header-btn" title="Profile">
                <i class="fas fa-user"></i>
            </a>
        </div>
    </header>

    <!-- Install Banner -->
    <div class="tablet-install-banner" id="installBanner">
        <i class="fas fa-download" style="font-size:18px;color:var(--primary-light);"></i>
        <p>Install Arctic Wolves for the best tablet experience</p>
        <button class="tablet-install-btn" id="installBtn">Install</button>
        <button onclick="document.getElementById('installBanner').style.display='none'" style="background:none;border:none;color:var(--text-muted);font-size:18px;cursor:pointer;padding:4px;">&times;</button>
    </div>

    <!-- Parent Athlete Selector -->
    <?php if($isParent): ?>
    <div style="padding:10px 20px;background:var(--bg-secondary);border-bottom:1px solid var(--border);">
        <div style="display:flex;align-items:center;gap:10px;">
            <label style="font-size:13px;color:var(--text-secondary);font-weight:600;">Viewing as:</label>
            <select id="athlete-select" onchange="switchAthlete(this.value)" style="flex:1;padding:10px;background:var(--bg-main);border:1px solid var(--border);border-radius:8px;color:#fff;font-size:14px;font-family:Inter,sans-serif;">
                <option value="">Select Athlete</option>
                <?php
                $stmt = $pdo->prepare("
                    SELECT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
                    FROM users u
                    INNER JOIN parent_athlete_relationships par ON u.id = par.athlete_id
                    WHERE par.parent_id = ? AND u.role = 'athlete'
                ");
                $stmt->execute([$user_id]);
                while($athlete = $stmt->fetch()):
                    $selected = (isset($_SESSION['viewing_athlete_id']) && $_SESSION['viewing_athlete_id'] == $athlete['id']) ? 'selected' : '';
                ?>
                <option value="<?= $athlete['id'] ?>" <?= $selected ?>><?= htmlspecialchars($athlete['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>
    <?php endif; ?>

    <!-- Scrollable Content -->
    <div class="tablet-content">
        <?php
        if (file_exists($view_file)) { include $view_file; }
        else { echo "<h2 style='color:#ef4444;'>Module missing: $view_file</h2>"; }
        ?>
    </div>
</div>

<script>
// ── Sidebar Toggle ────────────────────────────────────────
function toggleSidebar() {
    var sidebar = document.getElementById('tabletSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var toggleIcon = document.querySelector('#sidebarToggle i');
    sidebar.classList.toggle('collapsed');
    overlay.classList.toggle('show');
    // Swap icon
    if (toggleIcon) {
        toggleIcon.className = sidebar.classList.contains('collapsed') ? 'fas fa-bars' : 'fas fa-times';
    }
    // Save preference
    sessionStorage.setItem('tabletSidebarCollapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
}

// Restore sidebar state
document.addEventListener('DOMContentLoaded', function() {
    var collapsed = sessionStorage.getItem('tabletSidebarCollapsed');
    var sidebar = document.getElementById('tabletSidebar');
    var toggleIcon = document.querySelector('#sidebarToggle i');
    // On narrow tablets, default to collapsed
    if (collapsed === '1' || (collapsed === null && window.innerWidth < 900)) {
        sidebar.classList.add('collapsed');
    }
    // Set initial icon state
    if (toggleIcon && sidebar.classList.contains('collapsed')) {
        toggleIcon.className = 'fas fa-bars';
    } else if (toggleIcon) {
        toggleIcon.className = 'fas fa-times';
    }

    // Persist sidebar scroll position
    var savedPos = sessionStorage.getItem('tabletSidebarScroll');
    if (savedPos && sidebar) sidebar.scrollTop = parseInt(savedPos, 10);
    
    // Scroll active link into view if needed
    var activeLink = sidebar.querySelector('.nav-link.active');
    if (activeLink && !savedPos) {
        activeLink.scrollIntoView({ block: 'center', behavior: 'instant' });
    }
    
    window.addEventListener('beforeunload', function() {
        sessionStorage.setItem('tabletSidebarScroll', sidebar.scrollTop);
    });
    
    // Auto-close sidebar on nav click for narrow tablets
    sidebar.querySelectorAll('.nav-link').forEach(function(link) {
        link.addEventListener('click', function() {
            sessionStorage.setItem('tabletSidebarScroll', sidebar.scrollTop);
            if (window.innerWidth < 900) {
                sidebar.classList.add('collapsed');
                document.getElementById('sidebarOverlay').classList.remove('show');
                if (toggleIcon) toggleIcon.className = 'fas fa-bars';
                sessionStorage.setItem('tabletSidebarCollapsed', '1');
            }
        });
    });
});

// ── Persona Switcher ──────────────────────────────────────
<?php if ($isActualAdmin): ?>
(function() {
    var personaSelect = document.getElementById('personaRoleSelect');
    var exitBtn = document.getElementById('exitPersonaBtn');
    var csrfToken = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

    if (personaSelect) {
        personaSelect.addEventListener('change', function() {
            fetch('process_persona_switch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=switch_role&role=' + encodeURIComponent(this.value) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) window.location.href = 'pwa_tablet.php?page=home'; });
        });
    }
    if (exitBtn) {
        exitBtn.addEventListener('click', function() {
            fetch('process_persona_switch.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=exit_persona&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) { if (data.success) window.location.href = 'pwa_tablet.php?page=home'; });
        });
    }
})();
<?php endif; ?>

// ── Athlete Switcher ──────────────────────────────────────
function switchAthlete(athleteId) {
    if (athleteId) {
        fetch('process_switch_athlete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'athlete_id=' + athleteId
        })
        .then(function(r) { return r.json(); })
        .then(function(data) { if (data.success) location.reload(); });
    }
}

// ── Service Worker Registration ───────────────────────────
if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('pwa-sw.js').catch(function() {});
}

// ── PWA Install Prompt ────────────────────────────────────
var deferredPrompt;
window.addEventListener('beforeinstallprompt', function(e) {
    e.preventDefault();
    deferredPrompt = e;
    document.getElementById('installBanner').classList.add('show');
});
var installBtn = document.getElementById('installBtn');
if (installBtn) {
    installBtn.addEventListener('click', function() {
        if (deferredPrompt) {
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then(function() {
                deferredPrompt = null;
                document.getElementById('installBanner').classList.remove('show');
            });
        }
    });
}

// ── Notification Permission ───────────────────────────────
if ('Notification' in window && Notification.permission === 'default') {
    setTimeout(function() { Notification.requestPermission(); }, 5000);
}

// ── Camera Access Helper ──────────────────────────────────
window.pwaRequestCamera = function(videoElement) {
    if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
        return navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: true })
            .then(function(stream) {
                if (videoElement) videoElement.srcObject = stream;
                return stream;
            });
    }
    return Promise.reject(new Error('Camera not available'));
};

// ── Unread Message Badge Polling ──────────────────────────
(function() {
    function updateMsgBadge() {
        fetch('process_messages.php?action=unread_count')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                var badge = document.getElementById('nav-msg-badge');
                if (badge && data.success) {
                    if (data.count > 0) {
                        badge.textContent = data.count > 99 ? '99+' : data.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
            })
            .catch(function() {});
    }
    updateMsgBadge();
    setInterval(updateMsgBadge, 30000);
})();
</script>

<!-- Main Application JavaScript -->
<script src="js/app.js"></script>
</body>
</html>
