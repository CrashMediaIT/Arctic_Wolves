<?php
// =========================================================
// DASHBOARD CONTROLLER - COMPREHENSIVE VERSION WITH NEW NAVIGATION
// =========================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/pwa_detect.php';
require_once __DIR__ . '/lib/site_branding.php';

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
    error_log('CSP extraConnectSrc error (dashboard): ' . $e->getMessage());
}

// Set security headers including CSP (with RustFS origin if configured)
setSecurityHeaders($extraConnectSrc);

// PWA: redirect mobile phones to PWA dashboard
redirectToPwaIfMobile('pwa.php', 'pwa_tablet.php');

// Generate CSRF token for this session
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || $pdo === null) {
    die("Database connection failed. Please check your configuration. Error: " . ($db_error ?? 'Unknown error'));
}

if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit(); }

// Load site branding from theme_settings (logo, favicon)
$site_logo_url = getSiteLogoUrl($pdo);
$site_favicon_url = getSiteFaviconUrl($pdo);

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Update last_activity for online status tracking (at most once per minute)
if (!isset($_SESSION['last_activity_update']) || (time() - $_SESSION['last_activity_update']) > 60) {
    try {
        $pdo->prepare("UPDATE login_history SET last_activity = NOW() WHERE user_id = ? AND logout_time IS NULL ORDER BY login_time DESC LIMIT 1")->execute([$user_id]);
        $_SESSION['last_activity_update'] = time();
    } catch (PDOException $e) {
        // Silently fail - column may not exist yet
    }
}

// Persona mode: check if admin is impersonating another role
$isActualAdmin = (($user_role === 'admin') || (isset($_SESSION['persona_original_role']) && $_SESSION['persona_original_role'] === 'admin'));
$personaActive = !empty($_SESSION['persona_active']);
$personaOriginalRole = $_SESSION['persona_original_role'] ?? null;

// All files are served from RustFS — no local file restoration needed

// Check if user needs to accept agreements (for users created by admin/coach)
$showAgreementsModal = false;
$agreementTemplates = [];
try {
    $agreeCheck = $pdo->prepare("SELECT agreements_accepted FROM users WHERE id = ?");
    $agreeCheck->execute([$user_id]);
    $agreeRow = $agreeCheck->fetch(PDO::FETCH_ASSOC);
    if ($agreeRow && intval($agreeRow['agreements_accepted'] ?? 0) === 0) {
        $showAgreementsModal = true;
        // Fetch active agreement templates (use latest id per type to avoid duplicates)
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
} catch (PDOException $e) {
    // Table may not exist yet - silently continue
}

// Role checks including new roles - support multiple roles from user_roles table
$user_roles_list = [$user_role]; // primary role always included
try {
    $rolesStmt = $pdo->prepare("SELECT role FROM user_roles WHERE user_id = ?");
    $rolesStmt->execute([$user_id]);
    $extraRoles = $rolesStmt->fetchAll(PDO::FETCH_COLUMN);
    if ($extraRoles) {
        $user_roles_list = array_unique(array_merge($user_roles_list, $extraRoles));
    }
} catch (PDOException $e) {
    // user_roles table may not exist yet
}

$isAdmin       = in_array('admin', $user_roles_list);
$isCoach       = in_array('coach', $user_roles_list);
$isHealthCoach = in_array('health_coach', $user_roles_list);
$isTeamCoach   = in_array('team_coach', $user_roles_list);
$isParent      = in_array('parent', $user_roles_list);
$isFrontDesk   = in_array('front_desk_staff', $user_roles_list);
$isHR          = in_array('hr', $user_roles_list);
$isAccounting  = in_array('accounting', $user_roles_list);

// Combined role checks for sections
$isAnyCoach    = ($isCoach || $isAdmin);
$isTeamStaff   = ($isTeamCoach);
$canAccessPOS  = ($isAdmin || $isFrontDesk);
$canAccessHealthManagement = ($isHealthCoach || $isAdmin);
$canAccessHR   = ($isHR || $isAdmin);
$canAccessAccounting = ($isAccounting || $isAdmin);
$isStaff       = ($isAdmin || $isCoach || $isHealthCoach || $isFrontDesk || $isHR || $isAccounting);
$isGoalieDev   = in_array('goalie_dev', $user_roles_list);
$isPlayerDev   = in_array('player_dev', $user_roles_list);
$canAccessDevPrograms = ($isGoalieDev || $isPlayerDev || $isAdmin);

$page = $_GET['page'] ?? 'home';

// Redirect front desk staff to their special dashboard if on home page
if ($page === 'home' && $isFrontDesk) {
    $page = 'front_desk_home';
}

// Redirect old admin_settings route to system_tools with landing tab
if ($page === 'admin_settings') {
    header("Location: dashboard.php?page=system_tools&tab=landing");
    exit();
}

// Redirect old gameplan_settings route to system_tools with gameplan tab
if ($page === 'gameplan_settings') {
    header("Location: dashboard.php?page=system_tools&tab=gameplan");
    exit();
}

// FULL ROUTING TABLE - PARENT AND CHILD PAGES
$allowed_pages = [
    // Main Menu
    'home'                    => 'views/home.php',
    'stats'                   => 'views/stats.php',
    'goals'                   => 'views/goals.php',
    'messages'                => 'views/messages.php',
    
    // Sessions - Parent page with tabs
    'sessions'                => 'views/sessions.php',
    'upcoming_sessions'       => 'views/sessions.php',
    'booking'                 => 'views/sessions.php',
    
    // Video - Parent page with tabs
    'video'                   => 'views/video.php',
    'drill_review'            => 'views/video.php',
    'coaches_reviews'         => 'views/video.php',
    'record_video'            => 'views/video.php',
    'record_drill_video'      => 'views/video_record_drill.php',
    
    // Health - Parent page with tabs
    'health'                  => 'views/health.php',
    'strength_conditioning'   => 'views/health.php',
    'nutrition'               => 'views/health.php',
    
    // Team (Team Coaches)
    'team_roster'             => 'views/team_roster.php',
    
    // Coaches Corner - Parent pages with tabs
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
    
    // Accounting and Reports (Admin)
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
    
    // Merchandise (Admin)
    'merchandise_categories'  => 'views/merchandise_categories.php',
    'merchandise_products'    => 'views/merchandise_products.php',
    
    // POS System (Admin and Front Desk Staff)
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
    
    // HR (Admin)
    'termination'             => 'views/hr_termination.php',
    'payroll'                 => 'views/hr_payroll.php',
    'onboarding'              => 'views/hr_onboarding.php',
    'employee_contracts'      => 'views/hr_employee_contracts.php',
    'hr_time_tracking'        => 'views/hr_time_tracking.php',
    'complaints'              => 'views/hr_complaints.php',
    'phone_directory'         => 'views/phone_directory.php',
    'sip_settings'            => 'views/sip_settings.php',
    
    // Administration (Admin)
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
    
    // Athlete/Coach Views
    'athlete_evaluations'     => 'views/athlete_evaluations.php',
    'athlete_goals'           => 'views/athlete_goals.php',
    'coach_evaluations'       => 'views/coach_evaluations.php',
    'coach_goals'             => 'views/coach_goals.php',
    'manage_athletes'         => 'views/manage_athletes.php',
    'athletes'                => 'views/athletes.php',
    
    // Session Evaluations (Coaches Corner)
    'session_evaluation_form'   => 'views/session_evaluation_form.php',
    'coach_pending_reviews'     => 'views/coach_video_reviews.php',
    'coach_video_reviews'       => 'views/coach_video_reviews.php',
    'video_review_detail'       => 'views/video_review_detail.php',
    
    // Evaluations
    'evaluations_goals'       => 'views/evaluations_goals.php',
    'evaluations_skills'      => 'views/evaluations_skills.php',
    
    // Notifications
    'notifications'           => 'views/notifications.php',
    
    // Reports
    'reports_athlete'         => 'views/reports_athlete.php',
    'reports_user'            => 'views/reports_user.php',
    'reports_income'          => 'views/reports_income.php',
    'scheduled_reports'       => 'views/scheduled_reports.php',
    'report_view'             => 'views/report_view.php',
    
    // Sessions
    'create_session'          => 'views/create_session.php',
    'session_history'         => 'views/session_history.php',
    'session_detail'          => 'views/session_detail.php',
    
    // Packages and Payments
    'packages'                => 'views/packages.php',
    'payment_history'         => 'views/payment_history.php',
    'session_payment'         => 'views/session_payment.php',
    'refunds'                 => 'views/refunds.php',
    
    // Other
    'workouts'                => 'views/workouts.php',
    'testing'                 => 'views/testing.php',
    'parent_home'             => 'views/parent_home.php',
    'athlete_detail'          => 'views/athlete_detail.php',
    'athlete_notes'           => 'views/athlete_notes.php',
    'camp_checkin'            => 'views/parent_camp_checkin.php',
    
    // Health Management (Health Coaches & Admins)
    'library_workouts'        => 'views/library_workouts.php',
    'library_nutrition'       => 'views/library_nutrition.php',
    'health_coach_roster'     => 'views/health_coach_roster.php',
    
    // Personal Development (All Users)
    'personal_development'              => 'views/personal_development.php',
    'personal_development_programs'     => 'views/personal_development.php',
    'personal_development_my_program'   => 'views/personal_development.php',
    'dev_drill_detail'        => 'views/dev_drill_detail.php',
    
    // Development Programs (Coaches Corner - goalie_dev/player_dev)
    'development_programs'    => 'views/development_programs.php',
    
    // Personal Drills (Drill tab)
    'personal_drills'         => 'views/drills.php',
    
    // Shop
    'shop'                    => 'views/shop.php',
    
    // Profile and Settings
    'profile'                 => 'views/profile.php',
    'settings'                => 'views/settings.php',
    'gameplan'                => 'views/gameplan.php',
];

$view_file = $allowed_pages[$page] ?? 'views/home.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>">
    <title>Arctic Wolves Dashboard</title>
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6B46C1">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <!-- Unified Style Guide - Authoritative stylesheet based on Upcoming Sessions and Bookings -->
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.17/dist/hls.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dashjs@5.0.0/dist/dash.all.min.js"></script>
    <script src="js/hls-player.js"></script>
    <script src="js/typeahead.js"></script>
    <script>window.APP_TIMEZONE = <?= json_encode(date_default_timezone_get()) ?>;</script>
    <style>
        /* Dashboard-specific layout styles */
        body { margin: 0; background: var(--bg-main); font-family: 'Inter', sans-serif; color: #fff; display: flex; height: 100vh; overflow: hidden; }
        
        /* Sidebar */
        .sidebar { width: 280px; background: var(--sidebar); border-right: 1px solid var(--border); display: flex; flex-direction: column; padding: 25px; overflow-y: auto; }
        .brand { font-size: 22px; font-weight: 900; margin-bottom: 40px; letter-spacing: -1px; display: flex; align-items: center; gap: 10px; text-decoration: none; color: #fff; }
        .brand span { color: var(--primary); }
        .brand img { height: 35px; width: auto; }
        
        /* Navigation Groups */
        .nav-group { margin-bottom: 25px; }
        .nav-label { font-size: 10px; text-transform: uppercase; color: #475569; font-weight: 800; margin-bottom: 12px; display: block; letter-spacing: 1.5px; }
        .nav-menu { list-style: none; padding: 0; margin: 0; }
        .nav-link { display: flex; align-items: center; gap: 14px; padding: 10px 15px; color: var(--text); text-decoration: none; border-radius: 8px; font-size: 13px; font-weight: 600; transition: 0.2s; margin-bottom: 2px; cursor: pointer; }
        .nav-link i { width: 18px; text-align: center; color: #fff; }
        .nav-link:hover, .nav-link.active { background: rgba(107, 70, 193, 0.1); color: var(--primary-light); }
        
        /* TAB NAVIGATION FOR PARENT PAGES */
        .tab-navigation {
            display: flex;
            gap: 8px;
            border-bottom: 2px solid var(--border);
            margin-bottom: 30px;
            padding-bottom: 0;
        }
        .tab-link {
            padding: 12px 24px;
            color: var(--text);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        .tab-link:hover {
            color: var(--primary-light);
            background: rgba(107, 70, 193, 0.05);
        }
        .tab-link.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }
        
        /* Page Headers */
        .page-header {
            margin-bottom: 30px;
        }
        .page-header h1 {
            font-size: 28px;
            font-weight: 900;
            margin-bottom: 8px;
        }
        .page-header p {
            color: var(--text);
            font-size: 14px;
        }
        
        /* Main Content */
        .main-content { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        
        /* Top Bar for Parent Selector */
        .top-bar { display: flex; justify-content: flex-end; padding: 20px 40px; border-bottom: 1px solid var(--border); background: var(--sidebar); }
        .athlete-selector { display: flex; align-items: center; gap: 10px; }
        .athlete-selector label { font-size: 13px; color: var(--text); font-weight: 600; }
        
        /* MODERN SELECT STYLING */
        .athlete-selector select, select {
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
            padding: 12px 45px 12px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 600;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%2224%22%20height%3D%2224%22%20viewBox%3D%220%200%2024%2024%22%3E%3Cpath%20fill%3D%22%236B46C1%22%20d%3D%22M7%2010l5%205%205-5z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
            min-height: 45px;
        }
        .athlete-selector select:hover, select:hover { 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
        }
        .athlete-selector select:focus, select:focus { 
            outline: none; 
            border-color: var(--primary); 
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.2);
            background-color: var(--bg-secondary);
        }
        
        /* MODERN INPUT & TEXTAREA STYLING */
        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"],
        input[type="date"],
        input[type="time"],
        input[type="tel"],
        input[type="url"],
        textarea {
            appearance: none;
            -webkit-appearance: none;
            padding: 12px 16px;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 400;
            font-family: 'Inter', sans-serif;
            transition: all 0.2s ease;
            min-height: 45px;
            width: 100%;
        }
        textarea {
            min-height: 120px;
            resize: vertical;
            line-height: 1.5;
        }
        input[type="text"]:hover,
        input[type="email"]:hover,
        input[type="password"]:hover,
        input[type="number"]:hover,
        input[type="date"]:hover,
        input[type="time"]:hover,
        input[type="tel"]:hover,
        input[type="url"]:hover,
        textarea:hover {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
        }
        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus,
        input[type="date"]:focus,
        input[type="time"]:focus,
        input[type="tel"]:focus,
        input[type="url"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.2);
            background-color: var(--bg-secondary);
        }
        input::placeholder,
        textarea::placeholder {
            color: var(--text-muted);
            font-weight: 400;
        }
        
        /* MODERN CHECKBOX & RADIO STYLING */
        input[type="checkbox"],
        input[type="radio"] {
            appearance: none;
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid var(--border);
            border-radius: 4px;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
            background: var(--bg);
            vertical-align: middle;
            margin-right: 8px;
        }
        input[type="radio"] {
            border-radius: 50%;
        }
        input[type="checkbox"]:hover,
        input[type="radio"]:hover {
            border-color: var(--primary);
        }
        input[type="checkbox"]:checked,
        input[type="radio"]:checked {
            background: var(--primary);
            border-color: var(--primary);
        }
        input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            color: #fff;
            font-size: 14px;
            font-weight: bold;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        input[type="radio"]:checked::after {
            content: '';
            position: absolute;
            width: 8px;
            height: 8px;
            background: #fff;
            border-radius: 50%;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        /* MODERN BUTTON STYLING */
        button,
        input[type="submit"],
        input[type="button"],
        .btn {
            appearance: none;
            -webkit-appearance: none;
            padding: 12px 24px;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            font-weight: 700;
            font-family: 'Inter', sans-serif;
            cursor: pointer;
            transition: all 0.2s ease;
            min-height: 45px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        button:hover,
        input[type="submit"]:hover,
        input[type="button"]:hover,
        .btn:hover {
            background: var(--primary-hover);
            box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
            transform: translateY(-1px);
        }
        button:active,
        input[type="submit"]:active,
        input[type="button"]:active,
        .btn:active {
            transform: translateY(0);
        }
        button:disabled,
        input[type="submit"]:disabled,
        input[type="button"]:disabled,
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        /* Button Variants */
        .btn-secondary {
            background: var(--bg-secondary);
            border: 1px solid var(--border);
        }
        .btn-secondary:hover {
            background: var(--border);
            box-shadow: none;
        }
        .btn-success {
            background: #10b981;
        }
        .btn-success:hover {
            background: #059669;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }
        .btn-danger {
            background: #ef4444;
        }
        .btn-danger:hover {
            background: #dc2626;
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
        
        /* Primary Button - Explicit Styling for Visibility */
        .btn-primary {
            background: var(--primary) !important;
            color: #fff !important;
            border: none !important;
        }
        .btn-primary:hover {
            background: var(--primary-hover) !important;
            box-shadow: 0 4px 12px rgba(107, 70, 193, 0.4);
        }
        .btn-primary i {
            color: #fff !important;
        }
        
        /* Small button variant */
        .btn-sm {
            padding: 8px 16px;
            font-size: 12px;
            min-height: 36px;
        }
        
        /* Content Area */
        .content-area { flex: 1; padding: 40px; overflow-y: auto; background: var(--bg-main); }
        
        /* Sidebar Footer */
        .sidebar-footer { margin-top: auto; padding-top: 20px; border-top: 1px solid var(--border); }
        .avatar { width: 35px; height: 35px; background: linear-gradient(135deg, var(--primary), var(--primary-light)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 900; }
        
        /* Custom Scrollbar */
        .sidebar::-webkit-scrollbar, .content-area::-webkit-scrollbar { width: 8px; }
        .sidebar::-webkit-scrollbar-track, .content-area::-webkit-scrollbar-track { background: var(--bg); }
        .sidebar::-webkit-scrollbar-thumb, .content-area::-webkit-scrollbar-thumb { background: var(--border); border-radius: 4px; }
        .sidebar::-webkit-scrollbar-thumb:hover, .content-area::-webkit-scrollbar-thumb:hover { background: var(--border-light); }
        * { scrollbar-width: thin; scrollbar-color: var(--border) var(--bg); }
    </style>
    <link rel="stylesheet" href="views/shared_styles.css">
</head>
<body>

<aside class="sidebar">
    <a href="?page=home" class="brand">
        <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo">
        ARCTIC <span>WOLVES</span>
    </a>
    
    <?php if ($isActualAdmin): ?>
    <!-- Admin Persona Switcher -->
    <div class="persona-switcher" id="personaSwitcher">
        <?php if ($personaActive): ?>
        <div class="persona-banner">
            <i class="fas fa-mask"></i>
            <span>Persona Mode</span>
        </div>
        <?php endif; ?>
        <label class="persona-label">
            <i class="fas fa-user-secret"></i> View As Role
        </label>
        <select id="personaRoleSelect" class="persona-select">
            <option value="admin" <?= $user_role === 'admin' ? 'selected' : '' ?>>Admin</option>
            <option value="coach" <?= $user_role === 'coach' ? 'selected' : '' ?>>Coach</option>
            <option value="health_coach" <?= $user_role === 'health_coach' ? 'selected' : '' ?>>Health Coach</option>
            <option value="team_coach" <?= $user_role === 'team_coach' ? 'selected' : '' ?>>Team Coach</option>
            <option value="athlete" <?= $user_role === 'athlete' ? 'selected' : '' ?>>Athlete</option>
            <option value="parent" <?= $user_role === 'parent' ? 'selected' : '' ?>>Parent</option>
            <option value="front_desk_staff" <?= $user_role === 'front_desk_staff' ? 'selected' : '' ?>>Front Desk</option>
        </select>
        <?php if ($personaActive): ?>
        <button type="button" id="exitPersonaBtn" class="persona-exit-btn">
            <i class="fas fa-arrow-rotate-left"></i> Back to Admin
        </button>
        <?php endif; ?>
    </div>
    <style>
        .persona-switcher { padding: 12px; margin-bottom: 15px; background: rgba(107, 70, 193, 0.08); border: 1px solid rgba(107, 70, 193, 0.25); border-radius: 10px; }
        .persona-banner { background: rgba(245, 158, 11, 0.15); color: #F59E0B; padding: 6px 10px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 6px; margin-bottom: 10px; border: 1px solid rgba(245, 158, 11, 0.3); }
        .persona-label { display: flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .persona-select { width: 100%; padding: 8px 12px; background: var(--bg, #0a0a0f); border: 1px solid var(--border, #2D2D3F); border-radius: 6px; color: #fff; font-size: 13px; font-weight: 600; cursor: pointer; min-height: 36px; }
        .persona-select:focus { outline: none; border-color: var(--primary, #6B46C1); }
        .persona-exit-btn { width: 100%; margin-top: 8px; padding: 8px 12px; background: rgba(245, 158, 11, 0.15); color: #F59E0B; border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 6px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all 0.2s; }
        .persona-exit-btn:hover { background: rgba(245, 158, 11, 0.25); }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var personaSelect = document.getElementById('personaRoleSelect');
        var exitBtn = document.getElementById('exitPersonaBtn');
        var csrfToken = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
        
        if (personaSelect) {
            personaSelect.addEventListener('change', function() {
                var selectedRole = this.value;
                fetch('process_persona_switch.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                    body: 'action=switch_role&role=' + encodeURIComponent(selectedRole) + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) { window.location.href = 'dashboard.php?page=home'; }
                    else { showToast('Error: ' + (data.message || 'Failed to switch role'), 'error'); }
                })
                .catch(function() { showToast('An error occurred. Please try again.', 'error'); });
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
                .then(function(data) {
                    if (data.success) { window.location.href = 'dashboard.php?page=home'; }
                })
                .catch(function() { showToast('An error occurred. Please try again.', 'error'); });
            });
        }
    });
    </script>
    <?php endif; ?>
    
    <!-- MAIN MENU (For all users) -->
    <div class="nav-group">
        <span class="nav-label">Main Menu</span>
        <nav class="nav-menu">
            <a href="?page=home" class="nav-link <?= $page=='home'?'active':'' ?>">
                <i class="fa-solid fa-house icon"></i> Home
            </a>
            <a href="?page=stats" class="nav-link <?= in_array($page, ['stats', 'goals'])?'active':'' ?>">
                <i class="fa-solid fa-chart-line icon"></i> Performance Stats
            </a>
            <a href="?page=messages" class="nav-link <?= $page=='messages'?'active':'' ?>">
                <i class="fa-solid fa-comments icon"></i> Messages
                <span id="nav-msg-badge" style="display:none; background:var(--primary,#6B46C1); color:#fff; font-size:10px; font-weight:700; padding:1px 6px; border-radius:10px; margin-left:auto;"></span>
            </a>
            <a href="?page=sessions" class="nav-link <?= in_array($page, ['sessions','upcoming_sessions','booking'])?'active':'' ?>">
                <i class="fa-solid fa-calendar-check icon"></i> Training
            </a>
            <a href="?page=video" class="nav-link <?= in_array($page, ['video','drill_review','coaches_reviews','record_video','video_review_detail'])?'active':'' ?>">
                <i class="fa-solid fa-video icon"></i> Video
            </a>
            <a href="?page=health" class="nav-link <?= in_array($page, ['health','strength_conditioning','nutrition'])?'active':'' ?>">
                <i class="fa-solid fa-heart-pulse icon"></i> Health
            </a>
            <?php if ($canAccessDevPrograms): ?>
            <a href="?page=personal_development" class="nav-link <?= in_array($page, ['personal_development','personal_development_programs','personal_development_my_program','dev_drill_detail'])?'active':'' ?>">
                <i class="fa-solid fa-hockey-puck icon"></i> Personal Development
            </a>
            <?php endif; ?>
            <a href="?page=shop" class="nav-link <?= $page=='shop'?'active':'' ?>">
                <i class="fa-solid fa-store icon"></i> Shop
            </a>
            <a href="?page=payment_history" class="nav-link <?= $page=='payment_history'?'active':'' ?>">
                <i class="fa-solid fa-receipt icon"></i> Purchase History
            </a>
        </nav>
    </div>

    <!-- TEAM (Team Coaches only) -->
    <?php if($isTeamStaff): ?>
    <div class="nav-group">
        <span class="nav-label">Team</span>
        <nav class="nav-menu">
            <a href="?page=team_roster" class="nav-link <?= $page=='team_roster'?'active':'' ?>">
                <i class="fa-solid fa-users icon"></i> Roster
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- COACHES CORNER (On-ice Coaches and Admins only, not Health Coaches) -->
    <?php if($isAnyCoach): ?>
    <div class="nav-group">
        <span class="nav-label">Coaches Corner</span>
        <nav class="nav-menu">
            <a href="?page=coach_calendar" class="nav-link <?= $page=='coach_calendar'?'active':'' ?>">
                <i class="fa-solid fa-calendar icon"></i> Calendar
            </a>
            <a href="?page=drills" class="nav-link <?= in_array($page, ['drills','drill_library','create_drill','import_drill','export_import_drills','personal_drills'])?'active':'' ?>">
                <i class="fa-solid fa-clipboard-list icon"></i> Drills
            </a>
            <a href="?page=practice" class="nav-link <?= in_array($page, ['practice','practice_library','create_practice','practice_create','practice_import','export_import_plans'])?'active':'' ?>">
                <i class="fa-solid fa-file-lines icon"></i> Practice Plans
            </a>
            <a href="?page=roster" class="nav-link <?= $page=='roster'?'active':'' ?>">
                <i class="fa-solid fa-users-gear icon"></i> Roster
            </a>
            <a href="?page=coach_stopwatch" class="nav-link <?= $page=='coach_stopwatch'?'active':'' ?>">
                <i class="fa-solid fa-stopwatch icon"></i> Stopwatch
            </a>
            <a href="?page=coach_shot_speed" class="nav-link <?= $page=='coach_shot_speed'?'active':'' ?>">
                <i class="fa-solid fa-hockey-puck icon"></i> Shot Speed
            </a>
            <a href="?page=coach_video_reviews" class="nav-link <?= in_array($page, ['coach_video_reviews','coach_pending_reviews'])?'active':'' ?>">
                <i class="fa-solid fa-video icon"></i> Video Reviews
            </a>
            <a href="?page=travel" class="nav-link <?= in_array($page, ['travel','mileage'])?'active':'' ?>">
                <i class="fa-solid fa-plane icon"></i> Travel
            </a>
            <a href="?page=record_drill_video" class="nav-link <?= $page=='record_drill_video'?'active':'' ?>">
                <i class="fa-solid fa-video icon"></i> Video Recording
            </a>
            <a href="/gameplan.php" class="nav-link">
                <i class="fa-solid fa-chess-board icon"></i> Game Plan
            </a>
            <?php if($canAccessDevPrograms): ?>
            <a href="?page=development_programs" class="nav-link <?= $page=='development_programs'?'active':'' ?>">
                <i class="fa-solid fa-hockey-puck icon"></i> Development Programs
            </a>
            <?php endif; ?>
        </nav>
    </div>
    <?php endif; ?>

    <!-- HEALTH MANAGEMENT (Health Coaches and Admins) -->
    <?php if($canAccessHealthManagement): ?>
    <div class="nav-group">
        <span class="nav-label">Health</span>
        <nav class="nav-menu">
            <a href="?page=library_workouts" class="nav-link <?= $page=='library_workouts'?'active':'' ?>">
                <i class="fa-solid fa-dumbbell icon"></i> Strength & Conditioning
            </a>
            <a href="?page=library_nutrition" class="nav-link <?= $page=='library_nutrition'?'active':'' ?>">
                <i class="fa-solid fa-utensils icon"></i> Nutrition
            </a>
            <a href="?page=roster" class="nav-link <?= $page=='roster' || $page=='health_coach_roster'?'active':'' ?>">
                <i class="fa-solid fa-users-gear icon"></i> Roster
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- ACCOUNTING AND REPORTS (Admins and Accounting) -->
    <?php if($canAccessAccounting): ?>
    <div class="nav-group">
        <span class="nav-label">Accounting & Reports</span>
        <nav class="nav-menu">
            <a href="?page=finance_dashboard" class="nav-link <?= in_array($page, ['finance_dashboard', 'accounting_dashboard', 'billing_dashboard', 'pos_transactions', 'shop_orders'])?'active':'' ?>">
                <i class="fa-solid fa-chart-pie icon"></i> Finance Dashboard
            </a>
            <a href="?page=financial_reports" class="nav-link <?= in_array($page, ['reports', 'schedules', 'financial_reports'])?'active':'' ?>">
                <i class="fa-solid fa-chart-pie icon"></i> Financial Reports Hub
            </a>
            <a href="?page=reports_user" class="nav-link <?= $page=='reports_user'?'active':'' ?>">
                <i class="fa-solid fa-users-gear icon"></i> User Reports
            </a>
            <a href="?page=credits_refunds" class="nav-link <?= $page=='credits_refunds'?'active':'' ?>">
                <i class="fa-solid fa-money-bill-transfer icon"></i> Credits & Refunds
            </a>
            <a href="?page=expenses" class="nav-link <?= $page=='expenses'?'active':'' ?>">
                <i class="fa-solid fa-receipt icon"></i> Expenses
            </a>
            <a href="?page=products" class="nav-link <?= $page=='products'?'active':'' ?>">
                <i class="fa-solid fa-box-open icon"></i> Products
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- POS SYSTEM (Admins and Front Desk Staff) -->
    <?php if($canAccessPOS): ?>
    <div class="nav-group">
        <span class="nav-label">Point of Sale</span>
        <nav class="nav-menu">
            <a href="?page=pos_terminal" class="nav-link <?= $page=='pos_terminal'?'active':'' ?>">
                <i class="fa-solid fa-cash-register icon"></i> POS Terminal
            </a>
            <a href="?page=inventory_management" class="nav-link <?= $page=='inventory_management'?'active':'' ?>">
                <i class="fa-solid fa-warehouse icon"></i> Inventory & Orders
            </a>
            <a href="?page=pos_online_orders" class="nav-link <?= $page=='pos_online_orders'?'active':'' ?>">
                <i class="fa-solid fa-shipping-fast icon"></i> Online Orders
            </a>
            <a href="?page=pos_time_tracking" class="nav-link <?= $page=='pos_time_tracking'?'active':'' ?>">
                <i class="fa-solid fa-clock icon"></i> Time Tracking
            </a>
            <a href="?page=pos_schedule" class="nav-link <?= $page=='pos_schedule'?'active':'' ?>">
                <i class="fa-solid fa-calendar-alt icon"></i> My Schedule
            </a>
            <a href="?page=sip_settings" class="nav-link <?= $page=='sip_settings'?'active':'' ?>">
                <i class="fa-solid fa-address-book icon"></i> Company Directory
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- HR (Admins and HR) -->
    <?php if($canAccessHR): ?>
    <div class="nav-group">
        <span class="nav-label">HR</span>
        <nav class="nav-menu">
            <a href="?page=admin_staff_scheduling" class="nav-link <?= $page=='admin_staff_scheduling'?'active':'' ?>">
                <i class="fa-solid fa-calendar-check icon"></i> Staff Scheduling
            </a>
            <a href="?page=hr_time_tracking" class="nav-link <?= $page=='hr_time_tracking'?'active':'' ?>">
                <i class="fa-solid fa-clock icon"></i> Time Tracking
            </a>
            <a href="?page=payroll" class="nav-link <?= $page=='payroll'?'active':'' ?>">
                <i class="fa-solid fa-money-check-dollar icon"></i> Payroll
            </a>
            <a href="?page=onboarding" class="nav-link <?= $page=='onboarding'?'active':'' ?>">
                <i class="fa-solid fa-user-plus icon"></i> Onboarding
            </a>
            <a href="?page=employee_contracts" class="nav-link <?= $page=='employee_contracts'?'active':'' ?>">
                <i class="fa-solid fa-file-signature icon"></i> Contracts
            </a>
            <a href="?page=complaints" class="nav-link <?= $page=='complaints'?'active':'' ?>">
                <i class="fa-solid fa-exclamation-triangle icon"></i> Complaints
            </a>
            <a href="?page=termination" class="nav-link <?= $page=='termination'?'active':'' ?>">
                <i class="fa-solid fa-user-slash icon"></i> Termination
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- ADMINISTRATION (Admins only) -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">Administration</span>
        <nav class="nav-menu">
            <a href="?page=all_users" class="nav-link <?= $page=='all_users'?'active':'' ?>">
                <i class="fa-solid fa-users icon"></i> All Users
            </a>
            <a href="?page=categories" class="nav-link <?= $page=='categories'?'active':'' ?>">
                <i class="fa-solid fa-layer-group icon"></i> Resource Management
            </a>
            <a href="?page=eval_framework" class="nav-link <?= $page=='eval_framework'?'active':'' ?>">
                <i class="fa-solid fa-clipboard-check icon"></i> Eval Framework
            </a>
            <a href="?page=system_notification" class="nav-link <?= $page=='system_notification'?'active':'' ?>">
                <i class="fa-solid fa-bell icon"></i> System Notification
            </a>
            <a href="?page=admin_security" class="nav-link <?= $page=='admin_security'?'active':'' ?>">
                <i class="fa-solid fa-shield-halved icon"></i> Security
            </a>
            <a href="?page=system_tools" class="nav-link <?= $page=='system_tools'?'active':'' ?>">
                <i class="fa-solid fa-screwdriver-wrench icon"></i> System Tools
            </a>
            <!-- Audit Log removed - available in Security Center -->
            <a href="?page=marketing" class="nav-link <?= $page=='marketing'?'active':'' ?>">
                <i class="fa-solid fa-bullhorn icon"></i> Marketing
            </a>
            <a href="?page=admin_wishlist" class="nav-link <?= $page=='admin_wishlist'?'active':'' ?>">
                <i class="fa-solid fa-clipboard-list icon"></i> Wishlist
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- PARENT FEATURES -->
    <?php if($isParent): ?>
    <div class="nav-group">
        <span class="nav-label">Parent</span>
        <nav class="nav-menu">
            <a href="?page=camp_checkin" class="nav-link <?= $page=='camp_checkin'?'active':'' ?>">
                <i class="fa-solid fa-check-circle icon"></i> Camp Check-in
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
        <?php if($isStaff): ?>
        <a href="?page=sip_settings" class="nav-link <?= $page=='sip_settings'?'active':'' ?>">
            <i class="fa-solid fa-address-book"></i> Company Directory
        </a>
        <?php endif; ?>
        <a href="?page=profile" class="nav-link <?= $page=='profile'?'active':'' ?>">
            <i class="fa-solid fa-user-gear"></i> Profile Settings
        </a>
        <a href="logout.php" class="nav-link" style="color:#ef4444;">
            <i class="fa-solid fa-power-off"></i> Sign Out
        </a>
        <div style="display:flex; align-items:center; gap:12px; padding:10px; border-top:1px solid #1e293b; margin-top:10px;">
            <div class="avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
            <div style="font-size:12px;">
                <strong><?= htmlspecialchars($user_name) ?></strong><br>
                <span style="color:var(--text); text-transform:capitalize;"><?= str_replace('_', ' ', $user_role) ?></span>
            </div>
        </div>
    </div>
</aside>

<main class="main-content">
    <!-- Top Bar with Parent Athlete Selector -->
    <?php if($isParent): ?>
    <div class="top-bar">
        <div class="athlete-selector">
            <label for="athlete-select">Viewing as:</label>
            <select id="athlete-select" onchange="switchAthlete(this.value)">
                <option value="">Select Athlete</option>
                <?php
                // Fetch parent's children/athletes from database
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
    </div>
    <?php endif; ?>
    
    <div class="content-area">
        <?php 
        if (file_exists($view_file)) { include $view_file; } 
        else { echo "<h2 style='color:#ef4444;'>Module missing: $view_file</h2>"; }
        ?>
    </div>
</main>

<script>
// Toggle submenu expansion
function toggleSubmenu(element) {
    element.classList.toggle('expanded');
    const submenu = element.nextElementSibling;
    if (submenu && submenu.classList.contains('nav-submenu')) {
        submenu.classList.toggle('expanded');
    }
}

// Persist sidebar scroll position across page loads
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.querySelector('.sidebar');
    
    // Restore scroll position from sessionStorage
    const savedScrollPos = sessionStorage.getItem('sidebarScrollPos');
    if (savedScrollPos && sidebar) {
        sidebar.scrollTop = parseInt(savedScrollPos, 10);
    }
    
    // Also scroll to active nav link if it's not visible
    const activeLink = sidebar?.querySelector('.nav-link.active');
    if (activeLink && sidebar) {
        // Check if active link is in view
        const sidebarRect = sidebar.getBoundingClientRect();
        const linkRect = activeLink.getBoundingClientRect();
        
        // If active link is not visible, scroll to it (but keep some context)
        if (linkRect.top < sidebarRect.top || linkRect.bottom > sidebarRect.bottom) {
            // Only scroll if we didn't have a saved position
            if (!savedScrollPos) {
                activeLink.scrollIntoView({ block: 'center', behavior: 'instant' });
            }
        }
    }
    
    // Save scroll position before page unload
    window.addEventListener('beforeunload', function() {
        if (sidebar) {
            sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
        }
    });
    
    // Also save on nav link click (backup)
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (sidebar) {
                sessionStorage.setItem('sidebarScrollPos', sidebar.scrollTop);
            }
        });
    });
    
    // Find all active nav links in submenus
    const activeSubLinks = document.querySelectorAll('.nav-submenu .nav-link.active');
    activeSubLinks.forEach(link => {
        const submenu = link.closest('.nav-submenu');
        const parent = submenu?.previousElementSibling;
        if (submenu && parent) {
            submenu.classList.add('expanded');
            parent.classList.add('expanded');
        }
    });
});

// Switch athlete for parent view
function switchAthlete(athleteId) {
    if (athleteId) {
        fetch('process_switch_athlete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'athlete_id=' + athleteId
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                showToast('Failed to switch athlete view', 'error');
            }
        });
    }
}
</script>

<!-- Main Application JavaScript -->
<script src="js/app.js"></script>

<!-- Messenger Widget (Facebook Messenger-style) -->
<div id="messengerWidget" class="messenger-widget<?php if ($page === 'messages') echo ' messenger-widget-hidden'; ?>">
    <button id="messengerToggle" class="messenger-toggle" title="Messages">
        <i class="fas fa-comment-dots"></i>
        <span id="messengerBadge" class="messenger-badge" style="display:none;">0</span>
    </button>
    <div id="messengerPanel" class="messenger-panel" style="display:none;">
        <div class="messenger-panel-header">
            <h4><i class="fas fa-comments"></i> Messages</h4>
            <div class="messenger-panel-actions">
                <button id="messengerNewBtn" class="messenger-icon-btn" title="New Message"><i class="fas fa-edit"></i></button>
                <button id="messengerCloseBtn" class="messenger-icon-btn" title="Close"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div id="messengerListView">
            <div class="messenger-search">
                <input type="text" id="messengerSearch" placeholder="Search conversations..." autocomplete="off">
            </div>
            <div id="messengerConversations" class="messenger-conversations">
                <div class="messenger-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
            </div>
        </div>
        <div id="messengerChatView" style="display:none;">
            <div class="messenger-chat-header">
                <button id="messengerBackBtn" class="messenger-icon-btn"><i class="fas fa-arrow-left"></i></button>
                <span id="messengerChatName">User</span>
                <div class="messenger-size-toggle" id="messengerSizeToggle">
                    <button class="messenger-size-btn active" data-size="default" onclick="setWidgetSize('default')" title="Compact view">
                        <i class="fas fa-compress"></i>
                    </button>
                    <button class="messenger-size-btn" data-size="half" onclick="setWidgetSize('half')" title="Half size">
                        <i class="fas fa-up-right-and-down-left-from-center"></i>
                    </button>
                    <button class="messenger-size-btn" data-size="full" onclick="setWidgetSize('full')" title="Full size">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
                <button id="messengerMinimizeBtn" class="messenger-icon-btn" title="Minimize"><i class="fas fa-minus"></i></button>
            </div>
            <div id="messengerMessages" class="messenger-messages"></div>
            <div class="messenger-typing-indicator" id="widgetTypingIndicator" style="display:none;">
                <div class="typing-dots"><span></span><span></span><span></span></div>
                <span>typing...</span>
            </div>
            <div class="messenger-input-area">
                <div class="messenger-input-toolbar">
                    <div class="widget-emoji-picker-container">
                        <button class="messenger-toolbar-btn" onclick="toggleWidgetEmojiPicker()" title="Emoji" aria-label="Insert emoji">
                            <i class="far fa-face-smile"></i>
                        </button>
                        <div class="widget-emoji-picker-panel" id="widgetEmojiPicker">
                            <div class="widget-emoji-picker-search">
                                <input type="text" id="widgetEmojiSearch" placeholder="Search emoji...">
                            </div>
                            <div class="widget-emoji-picker-categories" id="widgetEmojiCategories"></div>
                            <div class="widget-emoji-picker-grid" id="widgetEmojiGrid"></div>
                        </div>
                    </div>
                    <button class="messenger-toolbar-btn" onclick="document.getElementById('messengerFileInput').click()" title="Attach file" aria-label="Attach file or image">
                        <i class="fas fa-paperclip"></i>
                    </button>
                    <input type="file" id="messengerFileInput" multiple accept="image/*,.pdf,.doc,.docx,.txt,.xls,.xlsx,.csv" style="display:none">
                </div>
                <textarea id="messengerInput" placeholder="Type a message..." rows="1"></textarea>
                <button id="messengerSendBtn" class="messenger-send-btn"><i class="fas fa-paper-plane"></i></button>
            </div>
        </div>
        <div id="messengerNewView" style="display:none;">
            <div class="messenger-chat-header">
                <button id="messengerNewBackBtn" class="messenger-icon-btn"><i class="fas fa-arrow-left"></i></button>
                <span>New Message</span>
            </div>
            <div class="messenger-new-search">
                <input type="text" id="messengerContactSearch" placeholder="Search contacts..." autocomplete="off">
            </div>
            <div id="messengerContacts" class="messenger-contacts"></div>
        </div>
    </div>
</div>

<style>
.messenger-widget { position: fixed; bottom: 24px; right: 24px; z-index: 9990; font-family: inherit; }
.messenger-widget-hidden { display: none; }
.messenger-toggle { width: 56px; height: 56px; border-radius: 50%; background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6)); border: none; color: #fff; font-size: 24px; cursor: pointer; box-shadow: 0 4px 16px rgba(107,70,193,0.4); transition: transform 0.2s, box-shadow 0.2s; position: relative; display: flex; align-items: center; justify-content: center; }
.messenger-toggle:hover { transform: scale(1.08); box-shadow: 0 6px 24px rgba(107,70,193,0.5); }
.messenger-badge { position: absolute; top: -4px; right: -4px; background: #EF4444; color: #fff; font-size: 11px; font-weight: 700; min-width: 20px; height: 20px; border-radius: 10px; display: flex; align-items: center; justify-content: center; padding: 0 5px; }
.messenger-panel { position: absolute; bottom: 70px; right: 0; width: 360px; max-height: 520px; background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 16px; box-shadow: 0 12px 40px rgba(0,0,0,0.5); display: flex; flex-direction: column; overflow: hidden; }
.messenger-panel-header { display: flex; align-items: center; justify-content: space-between; padding: 16px 20px; border-bottom: 1px solid var(--border, #2D2D3F); }
.messenger-panel-header h4 { margin: 0; font-size: 16px; font-weight: 700; color: var(--text-white, #fff); display: flex; align-items: center; gap: 8px; }
.messenger-panel-header h4 i { color: var(--primary, #6B46C1); }
.messenger-panel-actions { display: flex; gap: 4px; }
.messenger-icon-btn { width: 34px; height: 34px; background: transparent; border: 1px solid var(--border, #2D2D3F); color: var(--text-dim, #94a3b8); border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; }
.messenger-icon-btn:hover { background: var(--primary, #6B46C1); border-color: var(--primary, #6B46C1); color: #fff; }
.messenger-search { padding: 10px 16px; }
.messenger-search input, .messenger-new-search input { width: 100%; padding: 10px 14px; background: var(--bg-main, #0a0a0f); border: 1px solid var(--border, #2D2D3F); border-radius: 8px; color: #fff; font-size: 13px; outline: none; box-sizing: border-box; }
.messenger-search input:focus, .messenger-new-search input:focus { border-color: var(--primary, #6B46C1); }
.messenger-new-search { padding: 10px 16px; }
.messenger-conversations { flex: 1; overflow-y: auto; max-height: 360px; }
.messenger-conv-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; border-bottom: 1px solid rgba(45,45,63,0.3); transition: background 0.15s; }
.messenger-conv-item:hover { background: rgba(107,70,193,0.1); }
.messenger-conv-item.unread { background: rgba(107,70,193,0.08); }
.messenger-conv-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6)); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 14px; flex-shrink: 0; }
.messenger-conv-info { flex: 1; min-width: 0; }
.messenger-conv-name { font-size: 14px; font-weight: 600; color: var(--text-white, #fff); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.messenger-conv-preview { font-size: 12px; color: var(--text-dim, #94a3b8); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.messenger-conv-unread-dot { width: 10px; height: 10px; background: var(--primary, #6B46C1); border-radius: 50%; flex-shrink: 0; }
.messenger-loading { text-align: center; padding: 32px; color: var(--text-dim, #94a3b8); font-size: 13px; }
#messengerChatView, #messengerNewView { flex-direction: column; }
.messenger-chat-header { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-bottom: 1px solid var(--border, #2D2D3F); }
.messenger-chat-header span { flex: 1; font-size: 15px; font-weight: 600; color: var(--text-white, #fff); }
.messenger-messages { flex: 1; overflow-y: auto; padding: 16px; max-height: 320px; min-height: 200px; display: flex; flex-direction: column; gap: 8px; }
.messenger-messages .msg-bubble { max-width: 80%; padding: 10px 14px; border-radius: 16px; font-size: 13px; line-height: 1.5; word-break: break-word; }
.messenger-messages .msg-bubble.sent { align-self: flex-end; background: linear-gradient(135deg, var(--primary, #6B46C1), var(--accent, #8B5CF6)); color: #fff; border-bottom-right-radius: 4px; }
.messenger-messages .msg-bubble.received { align-self: flex-start; background: var(--bg-main, #0a0a0f); color: var(--text-white, #fff); border: 1px solid var(--border, #2D2D3F); border-bottom-left-radius: 4px; }
.messenger-messages .msg-bubble .msg-time { font-size: 10px; opacity: 0.6; margin-top: 4px; display: block; }
.messenger-input-area { display: flex; align-items: flex-end; gap: 8px; padding: 12px 16px; border-top: 1px solid var(--border, #2D2D3F); }
.messenger-input-area textarea { flex: 1; padding: 10px 14px; background: var(--bg-main, #0a0a0f); border: 1px solid var(--border, #2D2D3F); border-radius: 20px; color: #fff; font-size: 13px; resize: none; outline: none; max-height: 80px; font-family: inherit; box-sizing: border-box; }
.messenger-input-area textarea:focus { border-color: var(--primary, #6B46C1); }
.messenger-send-btn { width: 38px; height: 38px; border-radius: 50%; background: var(--primary, #6B46C1); border: none; color: #fff; cursor: pointer; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: background 0.2s; }
.messenger-send-btn:hover { background: var(--accent, #8B5CF6); }
.messenger-contacts { flex: 1; overflow-y: auto; max-height: 380px; }
.messenger-contact-item { display: flex; align-items: center; gap: 12px; padding: 12px 16px; cursor: pointer; border-bottom: 1px solid rgba(45,45,63,0.3); transition: background 0.15s; }
.messenger-contact-item:hover { background: rgba(107,70,193,0.1); }
.messenger-contact-avatar { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, #3B82F6, #6366F1); display: flex; align-items: center; justify-content: center; color: #fff; font-weight: 700; font-size: 13px; flex-shrink: 0; }
.messenger-contact-name { font-size: 14px; font-weight: 600; color: var(--text-white, #fff); }
.messenger-contact-role { font-size: 11px; color: var(--text-dim, #94a3b8); text-transform: capitalize; }
.messenger-empty { text-align: center; padding: 32px 16px; color: var(--text-dim); font-size: 13px; }
.messenger-size-toggle { display: flex; align-items: center; gap: 3px; margin-left: auto; margin-right: 4px; }
.messenger-size-btn { padding: 3px 8px; background: rgba(255,255,255,0.06); border: 1px solid var(--border, #2D2D3F); color: #8b949e; font-size: 11px; font-weight: 600; border-radius: 6px; cursor: pointer; transition: background 0.2s, color 0.2s, border-color 0.2s; white-space: nowrap; }
.messenger-size-btn:hover { background: rgba(107, 70, 193, 0.15); color: #c4b5fd; }
.messenger-size-btn.active { background: var(--primary, #6B46C1); color: #fff; border-color: var(--primary, #6B46C1); }
.messenger-input-toolbar { display: none; flex-direction: column; gap: 4px; padding: 0; flex-shrink: 0; }
.messenger-toolbar-btn { width: 32px; height: 32px; background: rgba(255,255,255,0.06); border: 1px solid var(--border, #2D2D3F); color: #8b949e; border-radius: 8px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.2s; font-size: 14px; }
.messenger-toolbar-btn:hover { background: rgba(107, 70, 193, 0.15); color: #c4b5fd; }
.messenger-panel.widget-size-default .messenger-input-toolbar { display: none; }
.messenger-panel.widget-size-half { width: 540px; max-height: 75vh; }
.messenger-panel.widget-size-half .messenger-messages { max-height: calc(75vh - 160px); }
.messenger-panel.widget-size-half .messenger-conversations { max-height: calc(75vh - 120px); }
.messenger-panel.widget-size-half .messenger-input-toolbar { display: flex; }
.messenger-panel.widget-size-full { width: 680px; max-height: 95vh; }
.messenger-panel.widget-size-full .messenger-messages { max-height: calc(95vh - 160px); }
.messenger-panel.widget-size-full .messenger-conversations { max-height: calc(95vh - 120px); }
.messenger-panel.widget-size-full .messenger-input-toolbar { display: flex; }
@media (max-width: 480px) {
    .messenger-panel { width: calc(100vw - 32px); right: -8px; bottom: 64px; max-height: 70vh; }
}
/* Widget Emoji Picker */
.widget-emoji-picker-container { position: relative; }
.widget-emoji-picker-panel { display: none; position: absolute; bottom: 44px; left: 0; width: 320px; max-height: 400px; background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.4); z-index: 100; overflow: hidden; flex-direction: column; }
.widget-emoji-picker-panel.show { display: flex; }
.widget-emoji-picker-search { padding: 10px 12px; border-bottom: 1px solid var(--border, #2D2D3F); }
.widget-emoji-picker-search input { width: 100%; padding: 8px 10px; background: var(--bg-main, #0a0a0f); border: 1px solid var(--border, #2D2D3F); border-radius: 6px; color: #fff; font-size: 13px; outline: none; box-sizing: border-box; }
.widget-emoji-picker-categories { display: flex; gap: 4px; padding: 8px 12px; border-bottom: 1px solid var(--border, #2D2D3F); justify-content: space-between; }
.widget-emoji-cat-btn { padding: 6px 8px; background: none; border: none; font-size: 18px; cursor: pointer; border-radius: 6px; transition: background 0.15s; flex-shrink: 0; }
.widget-emoji-cat-btn:hover, .widget-emoji-cat-btn.active { background: rgba(107, 70, 193, 0.2); }
.widget-emoji-picker-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px; padding: 10px; overflow-y: auto; flex: 1; max-height: 300px; }
.widget-emoji-btn { display: flex; align-items: center; justify-content: center; padding: 6px; background: none; border: none; font-size: 22px; cursor: pointer; border-radius: 6px; transition: background 0.12s; line-height: 1; }
.widget-emoji-btn:hover { background: rgba(107, 70, 193, 0.2); }
/* Widget Typing Indicator */
.messenger-typing-indicator { padding: 4px 16px 2px; display: flex; align-items: center; gap: 8px; color: var(--text-dim, #94a3b8); font-size: 12px; }
.messenger-typing-indicator .typing-dots { display: flex; gap: 3px; align-items: center; }
.messenger-typing-indicator .typing-dots span { width: 5px; height: 5px; background: var(--primary, #6B46C1); border-radius: 50%; animation: widgetTypingBounce 1.4s infinite both; }
.messenger-typing-indicator .typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.messenger-typing-indicator .typing-dots span:nth-child(3) { animation-delay: 0.4s; }
@keyframes widgetTypingBounce { 0%, 80%, 100% { transform: scale(0.6); opacity: 0.4; } 40% { transform: scale(1); opacity: 1; } }
</style>

<script>
// Messenger Widget Controller
(function() {
    var currentUserId = <?= (int)$user_id ?>;
    var csrfToken = '<?= htmlspecialchars($_SESSION["csrf_token"] ?? "", ENT_QUOTES) ?>';
    var panel = document.getElementById('messengerPanel');
    var toggle = document.getElementById('messengerToggle');
    var badge = document.getElementById('messengerBadge');
    var listView = document.getElementById('messengerListView');
    var chatView = document.getElementById('messengerChatView');
    var newView = document.getElementById('messengerNewView');
    var currentConvId = null;
    var currentToUserId = null;
    var chatPollInterval = null;

    function getInitials(first, last) {
        return ((first || '')[0] || '') + ((last || '')[0] || '');
    }

    function escapeHtml(text) {
        var d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }

    function formatTime(dateStr) {
        if (!dateStr) return '';
        var d = new Date(dateStr);
        var now = new Date();
        if (d.toDateString() === now.toDateString()) {
            return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
        return d.toLocaleDateString([], { month: 'short', day: 'numeric' });
    }

    toggle.addEventListener('click', function() {
        if (panel.style.display === 'none') {
            panel.style.display = 'flex';
            showListView();
            loadConversations();
        } else {
            panel.style.display = 'none';
            stopChatPoll();
        }
    });

    document.getElementById('messengerCloseBtn').addEventListener('click', function() {
        panel.style.display = 'none';
        stopChatPoll();
    });

    document.getElementById('messengerMinimizeBtn').addEventListener('click', function() {
        panel.style.display = 'none';
        stopChatPoll();
    });

    function showListView() {
        listView.style.display = 'block';
        chatView.style.display = 'none';
        newView.style.display = 'none';
        stopChatPoll();
    }

    function showChatView() {
        listView.style.display = 'none';
        chatView.style.display = 'flex';
        newView.style.display = 'none';
    }

    function showNewView() {
        listView.style.display = 'none';
        chatView.style.display = 'none';
        newView.style.display = 'flex';
    }

    document.getElementById('messengerBackBtn').addEventListener('click', function() {
        showListView();
        loadConversations();
    });
    document.getElementById('messengerNewBackBtn').addEventListener('click', function() {
        showListView();
    });

    document.getElementById('messengerNewBtn').addEventListener('click', function() {
        showNewView();
        loadContacts();
    });

    function loadConversations(silent) {
        var container = document.getElementById('messengerConversations');
        if (!silent) {
            container.innerHTML = '<div class="messenger-loading"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
        }
        fetch('process_messages.php?action=get_conversations')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.conversations || data.conversations.length === 0) {
                    container.innerHTML = '<div class="messenger-empty"><i class="fas fa-inbox" style="font-size:32px; display:block; margin-bottom:12px; opacity:0.3;"></i>No conversations yet</div>';
                    return;
                }
                // Check for new unread messages in non-active conversations
                if (silent && widgetInitialLoadDone) {
                    data.conversations.forEach(function(c) {
                        var convId = c.conversation_id;
                        var unread = parseInt(c.unread_count || 0);
                        var prevUnread = widgetPrevUnreadCounts[convId] || 0;
                        if (unread > prevUnread && parseInt(convId) !== currentConvId) {
                            playWidgetNotificationSound();
                        }
                    });
                }
                widgetPrevUnreadCounts = {};
                data.conversations.forEach(function(c) {
                    widgetPrevUnreadCounts[c.conversation_id] = parseInt(c.unread_count || 0);
                });
                widgetInitialLoadDone = true;
                var html = '';
                data.conversations.forEach(function(c) {
                    var initials = getInitials(c.first_name, c.last_name);
                    var name = escapeHtml((c.first_name || '') + ' ' + (c.last_name || ''));
                    var preview = escapeHtml(c.last_message || 'No messages yet');
                    if (preview.length > 40) preview = preview.substring(0, 40) + '...';
                    var unread = parseInt(c.unread_count || 0) > 0;
                    html += '<div class="messenger-conv-item' + (unread ? ' unread' : '') + '" data-conv-id="' + c.conversation_id + '" data-user-id="' + c.other_user_id + '" data-name="' + escapeHtml(name) + '">';
                    html += '<div class="messenger-conv-avatar">' + escapeHtml(initials).toUpperCase() + '</div>';
                    html += '<div class="messenger-conv-info"><div class="messenger-conv-name">' + name + '</div><div class="messenger-conv-preview">' + preview + '</div></div>';
                    if (unread) html += '<div class="messenger-conv-unread-dot"></div>';
                    html += '</div>';
                });
                if (silent && container.innerHTML === html) return;
                container.innerHTML = html;
                container.querySelectorAll('.messenger-conv-item').forEach(function(item) {
                    item.addEventListener('click', function() {
                        currentConvId = parseInt(this.dataset.convId);
                        currentToUserId = parseInt(this.dataset.userId);
                        document.getElementById('messengerChatName').textContent = this.dataset.name;
                        showChatView();
                        loadMessages(currentConvId);
                        startChatPoll();
                    });
                });
            })
            .catch(function() {
                if (!silent) {
                    container.innerHTML = '<div class="messenger-empty">Failed to load conversations</div>';
                }
            });
    }

    document.getElementById('messengerSearch').addEventListener('input', function() {
        var query = this.value.toLowerCase();
        document.querySelectorAll('.messenger-conv-item').forEach(function(item) {
            var name = (item.dataset.name || '').toLowerCase();
            item.style.display = name.indexOf(query) !== -1 ? 'flex' : 'none';
        });
    });

    function loadMessages(convId, silent) {
        var container = document.getElementById('messengerMessages');
        if (!silent) {
            container.innerHTML = '<div class="messenger-loading"><i class="fas fa-spinner fa-spin"></i></div>';
        }
        fetch('process_messages.php?action=get_messages&conversation_id=' + convId)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success) {
                    if (!silent) { container.innerHTML = '<div class="messenger-empty">Failed to load messages</div>'; }
                    return;
                }
                renderMessages(data.messages || [], silent);
            })
            .catch(function() {
                if (!silent) {
                    container.innerHTML = '<div class="messenger-empty">Error loading messages</div>';
                }
            });
    }

    function renderMessages(messages, silent) {
        var container = document.getElementById('messengerMessages');
        if (messages.length === 0) {
            container.innerHTML = '<div class="messenger-empty">No messages yet. Start the conversation!</div>';
            return;
        }
        var html = '';
        messages.forEach(function(m) {
            var isSent = parseInt(m.from_user_id) === currentUserId;
            html += '<div class="msg-bubble ' + (isSent ? 'sent' : 'received') + '">';
            html += escapeHtml(m.message_body);
            html += '<span class="msg-time">' + formatTime(m.created_at) + '</span>';
            html += '</div>';
        });
        if (silent && container.innerHTML === html) return;
        container.innerHTML = html;
        container.scrollTop = container.scrollHeight;
    }

    function sendMsg() {
        var input = document.getElementById('messengerInput');
        var body = input.value.trim();
        if (!body || !currentToUserId) return;
        input.value = '';

        var formData = new FormData();
        formData.append('action', 'send_message');
        formData.append('to_user_id', currentToUserId);
        formData.append('message_body', body);
        formData.append('csrf_token', csrfToken);

        fetch('process_messages.php', { method: 'POST', body: formData })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.message) {
                    if (!currentConvId && data.conversation_id) currentConvId = data.conversation_id;
                    var container = document.getElementById('messengerMessages');
                    var emptyMsg = container.querySelector('.messenger-empty');
                    if (emptyMsg) emptyMsg.remove();
                    var div = document.createElement('div');
                    div.className = 'msg-bubble sent';
                    div.innerHTML = escapeHtml(data.message.message_body) + '<span class="msg-time">' + formatTime(data.message.created_at) + '</span>';
                    container.appendChild(div);
                    container.scrollTop = container.scrollHeight;
                }
            });
    }

    document.getElementById('messengerSendBtn').addEventListener('click', sendMsg);
    document.getElementById('messengerInput').addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMsg();
        }
    });

    function startChatPoll() {
        stopChatPoll();
        chatPollInterval = setInterval(function() {
            if (currentConvId) loadMessages(currentConvId, true);
        }, 10000);
        startTypingPoll();
    }

    function stopChatPoll() {
        if (chatPollInterval) { clearInterval(chatPollInterval); chatPollInterval = null; }
        stopTypingPoll();
    }

    // === Typing Indicator ===
    var widgetTypingSendTimeout = null;
    document.getElementById('messengerInput').addEventListener('input', function() {
        if (currentConvId) {
            if (widgetTypingSendTimeout) return;
            widgetTypingSendTimeout = setTimeout(function() { widgetTypingSendTimeout = null; }, 2000);
            var formData = new FormData();
            formData.append('action', 'set_typing');
            formData.append('conversation_id', currentConvId);
            formData.append('csrf_token', csrfToken);
            fetch('process_messages.php', { method: 'POST', body: formData }).catch(function() {});
        }
    });

    var widgetTypingPollInterval = null;
    function startTypingPoll() {
        stopTypingPoll();
        widgetTypingPollInterval = setInterval(function() {
            if (currentConvId) {
                fetch('process_messages.php?action=get_typing_status&conversation_id=' + currentConvId)
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var indicator = document.getElementById('widgetTypingIndicator');
                        indicator.style.display = (data.success && data.typing) ? 'flex' : 'none';
                    })
                    .catch(function() {});
            }
        }, 3000);
    }
    function stopTypingPoll() {
        if (widgetTypingPollInterval) { clearInterval(widgetTypingPollInterval); widgetTypingPollInterval = null; }
        document.getElementById('widgetTypingIndicator').style.display = 'none';
    }

    // === Notification Sound ===
    function playWidgetNotificationSound() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            osc.frequency.setValueAtTime(660, ctx.currentTime + 0.1);
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) { /* ignore audio errors */ }
    }

    var widgetPrevUnreadCounts = {};
    var widgetInitialLoadDone = false;

    function loadContacts() {
        var container = document.getElementById('messengerContacts');
        container.innerHTML = '<div class="messenger-loading"><i class="fas fa-spinner fa-spin"></i> Loading contacts...</div>';
        fetch('process_messages.php?action=get_contacts')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.success || !data.contacts || data.contacts.length === 0) {
                    container.innerHTML = '<div class="messenger-empty">No contacts available</div>';
                    return;
                }
                var html = '';
                data.contacts.forEach(function(c) {
                    var initials = getInitials(c.first_name, c.last_name);
                    var name = escapeHtml((c.first_name || '') + ' ' + (c.last_name || ''));
                    var role = escapeHtml(c.role || '');
                    html += '<div class="messenger-contact-item" data-user-id="' + c.id + '" data-name="' + escapeHtml(name) + '">';
                    html += '<div class="messenger-contact-avatar">' + escapeHtml(initials).toUpperCase() + '</div>';
                    html += '<div><div class="messenger-contact-name">' + name + '</div><div class="messenger-contact-role">' + role + '</div></div>';
                    html += '</div>';
                });
                container.innerHTML = html;
                container.querySelectorAll('.messenger-contact-item').forEach(function(item) {
                    item.addEventListener('click', function() {
                        currentToUserId = parseInt(this.dataset.userId);
                        currentConvId = null;
                        document.getElementById('messengerChatName').textContent = this.dataset.name;
                        showChatView();
                        fetch('process_messages.php?action=get_conversations')
                            .then(function(r) { return r.json(); })
                            .then(function(data) {
                                if (data.success && data.conversations) {
                                    var existing = data.conversations.find(function(c) { return parseInt(c.other_user_id) === currentToUserId; });
                                    if (existing) {
                                        currentConvId = parseInt(existing.conversation_id);
                                        loadMessages(currentConvId);
                                        startChatPoll();
                                    } else {
                                        document.getElementById('messengerMessages').innerHTML = '<div class="messenger-empty">Start a new conversation!</div>';
                                    }
                                }
                            });
                    });
                });
            })
            .catch(function() {
                container.innerHTML = '<div class="messenger-empty">Failed to load contacts</div>';
            });
    }

    document.getElementById('messengerContactSearch').addEventListener('input', function() {
        var query = this.value.toLowerCase();
        document.querySelectorAll('.messenger-contact-item').forEach(function(item) {
            var name = (item.dataset.name || '').toLowerCase();
            item.style.display = name.indexOf(query) !== -1 ? 'flex' : 'none';
        });
    });

    function updateWidgetBadge() {
        fetch('process_messages.php?action=unread_count')
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var count = parseInt(data.count) || 0;
                    if (count > 0) {
                        badge.textContent = count > 99 ? '99+' : count;
                        badge.style.display = 'flex';
                    } else {
                        badge.style.display = 'none';
                    }
                    var navBadge = document.getElementById('nav-msg-badge');
                    if (navBadge) {
                        if (count > 0) {
                            navBadge.textContent = count > 99 ? '99+' : count;
                            navBadge.style.display = 'inline-block';
                        } else {
                            navBadge.style.display = 'none';
                        }
                    }
                }
            })
            .catch(function() {});
        // Also check for new messages to trigger notification sound
        loadConversations(true);
    }
    updateWidgetBadge();
    setInterval(updateWidgetBadge, 30000);
})();

// === Widget Size Toggle ===
function setWidgetSize(size) {
    var panel = document.getElementById('messengerPanel');
    panel.classList.remove('widget-size-default', 'widget-size-half', 'widget-size-full');
    panel.classList.add('widget-size-' + size);
    document.querySelectorAll('.messenger-size-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.dataset.size === size);
    });
}

// === Widget Emoji Picker ===
var widgetEmojiData = {
    'Smileys': ['😀','😃','😄','😁','😆','😅','🤣','😂','🙂','🙃','😉','😊','😇','🥰','😍','🤩','😘','😗','😚','😙','🥲','😋','😛','😜','🤪','😝','🤑','🤗','🤭','🤫','🤔','😐','😑','😶','😏','😒','🙄','😬','🤥','😌','😔','😪','🤤','😴','😷','🤒','🤕','🤢','🤮','🥴','😵','🤯','🥳','🥸','😎','🤓','🧐'],
    'Gestures': ['👍','👎','👊','✊','🤛','🤜','👏','🙌','👐','🤲','🤝','🙏','✌️','🤞','🤟','🤘','👌','🤌','🤏','👈','👉','👆','👇','☝️','✋','🤚','🖐️','🖖','👋','🤙','💪','🦾','🖕'],
    'Hearts': ['❤️','🧡','💛','💚','💙','💜','🖤','🤍','🤎','💔','❣️','💕','💞','💓','💗','💖','💘','💝','💟','♥️','🫶','🥹'],
    'Sports': ['⚽','🏀','🏈','⚾','🎾','🏐','🏉','🎱','🏓','🏸','🏒','🥅','⛳','🏹','🎣','⛸️','🥊','🎿','⛷️','🏋️','🤸','🏊','🚴','🏃','🧗'],
    'Objects': ['📎','📁','📂','📄','📊','📈','📉','📝','✏️','📌','📍','🔗','📷','📸','🎥','📹','💻','📱','⌚','🔔','🔒','🔑','🏆','🎯','🎉','🎊','🎁'],
    'Nature': ['🌟','⭐','🌙','☀️','🌈','🔥','💧','❄️','🍀','🌸','🌻','🌺','🌲','🌊','🐺','🐻','🦅','🐾','🦁','🐯'],
    'Food': ['☕','🍕','🍔','🍟','🌭','🍿','🧁','🍩','🍪','🎂','🍫','🍬','🍭','🍎','🍊','🍋','🍌','🍉','🍇','🍓']
};

(function initWidgetEmojiPicker() {
    var catContainer = document.getElementById('widgetEmojiCategories');
    var categories = Object.keys(widgetEmojiData);

    catContainer.innerHTML = categories.map(function(cat, i) {
        var icon = widgetEmojiData[cat][0];
        return '<button class="widget-emoji-cat-btn ' + (i === 0 ? 'active' : '') + '" onclick="showWidgetEmojiCategory(\'' + cat + '\', this)" title="' + cat + '">' + icon + '</button>';
    }).join('');

    showWidgetEmojiCategory(categories[0]);

    document.getElementById('widgetEmojiSearch').addEventListener('input', function() {
        var q = this.value.toLowerCase();
        if (!q) {
            showWidgetEmojiCategory(categories[0]);
            return;
        }
        var results = [];
        for (var cat in widgetEmojiData) {
            if (cat.toLowerCase().indexOf(q) !== -1) {
                results = results.concat(widgetEmojiData[cat]);
            }
        }
        if (results.length === 0) {
            for (var c in widgetEmojiData) {
                results = results.concat(widgetEmojiData[c]);
            }
        }
        renderWidgetEmojiGrid(results);
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.widget-emoji-picker-container')) {
            document.getElementById('widgetEmojiPicker').classList.remove('show');
        }
    });
})();

function showWidgetEmojiCategory(cat, btn) {
    document.querySelectorAll('.widget-emoji-cat-btn').forEach(function(b) { b.classList.remove('active'); });
    if (btn) btn.classList.add('active');
    else { var first = document.querySelector('.widget-emoji-cat-btn'); if (first) first.classList.add('active'); }
    renderWidgetEmojiGrid(widgetEmojiData[cat] || []);
}

function renderWidgetEmojiGrid(emojis) {
    document.getElementById('widgetEmojiGrid').innerHTML = emojis.map(function(e) {
        return '<button class="widget-emoji-btn" onclick="insertWidgetEmoji(\'' + e + '\')" title="' + e + '">' + e + '</button>';
    }).join('');
}

function toggleWidgetEmojiPicker() {
    var picker = document.getElementById('widgetEmojiPicker');
    picker.classList.toggle('show');
    if (picker.classList.contains('show')) {
        document.getElementById('widgetEmojiSearch').focus();
    }
}

function insertWidgetEmoji(emoji) {
    var input = document.getElementById('messengerInput');
    var start = input.selectionStart;
    var end = input.selectionEnd;
    input.value = input.value.substring(0, start) + emoji + input.value.substring(end);
    input.focus();
    input.selectionStart = input.selectionEnd = start + emoji.length;
}

// Handle paste events on messenger widget input
document.getElementById('messengerInput').addEventListener('paste', function(e) {
    var items = (e.clipboardData || e.originalEvent.clipboardData).items;
    for (var i = 0; i < items.length; i++) {
        if (items[i].type.indexOf('image') !== -1) {
            e.preventDefault();
            var blob = items[i].getAsFile();
            if (!blob) continue;
            if (blob.size > 25 * 1024 * 1024) {
                showToast('Cannot paste image: file size exceeds 25MB limit', 'error');
                return;
            }
            showToast('Image pasted - full file upload coming soon', 'info');
            break;
        }
    }
});
</script>

<?php if ($showAgreementsModal): ?>
<!-- First Sign-In Agreements Modal -->
<div class="modal active" id="agreementsOverlay" style="z-index:99999; background:rgba(0,0,0,0.9);">
    <div class="modal-content" style="max-width:750px; max-height:90vh; overflow-y:auto;">
        <div class="modal-header" style="flex-direction:column; align-items:center; text-align:center; border-bottom:1px solid var(--border);">
            <i class="fas fa-file-signature" style="font-size:48px; color:var(--primary); margin-bottom:15px;"></i>
            <h2>Welcome! Please Review & Accept Agreements</h2>
            <p style="color:var(--text-dim); font-size:14px; margin-top:8px;">Before accessing the dashboard, please review and accept the following agreements.</p>
        </div>

        <div class="modal-body">
            <form id="dashboardAgreementsForm" method="POST" action="process_agreements.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                <input type="hidden" name="action" value="accept_agreements">

                <?php foreach ($agreementTemplates as $tpl): ?>
                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3><i class="fas <?= $tpl['agreement_type'] === 'waiver' ? 'fa-shield-halved' : 'fa-lock' ?>"></i> <?= htmlspecialchars($tpl['title']) ?></h3>
                    </div>
                    <div class="card-body">
                        <div style="color:var(--text-dim); font-size:13px; line-height:1.8; max-height:200px; overflow-y:auto; padding:16px; background:var(--bg-main); border:1px solid var(--border); border-radius:8px; margin-bottom:16px;">
                            <?= $tpl['content'] ?>
                        </div>
                        <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; padding:12px; background:rgba(107,70,193,0.05); border:1px solid var(--border); border-radius:8px;">
                            <input type="checkbox" name="agree_<?= htmlspecialchars($tpl['agreement_type']) ?>" required>
                            <span style="color:var(--text-white); font-size:13px;">I have read, understood, and agree to the <strong><?= htmlspecialchars($tpl['title']) ?></strong></span>
                        </label>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-share-alt"></i> Evaluation Sharing Preferences</h3>
                    </div>
                    <div class="card-body">
                        <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; padding:12px; background:rgba(107,70,193,0.05); border:1px solid var(--border); border-radius:8px;">
                            <input type="checkbox" name="share_evaluations_potential_teams">
                            <span style="color:var(--text-dim); font-size:13px;"><strong style="color:var(--text-white);">Share evaluations with potential teams</strong><br>Allow your evaluations to be shared with teams you may play for in the future.</span>
                        </label>
                    </div>
                </div>

                <div class="card" style="margin-bottom:20px;">
                    <div class="card-header">
                        <h3><i class="fas fa-camera"></i> Promotional Material</h3>
                    </div>
                    <div class="card-body">
                        <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer; padding:12px; background:rgba(107,70,193,0.05); border:1px solid var(--border); border-radius:8px;">
                            <input type="checkbox" name="promotional_opt_in" checked>
                            <span style="color:var(--text-dim); font-size:13px;"><strong style="color:var(--text-white);">Allow use in promotional materials</strong><br>I consent to photos and videos being used in promotional materials. Uncheck to opt out (training analysis will still use technology).</span>
                        </label>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="width:100%; font-size:16px; padding:16px 24px;">
                    <i class="fas fa-check-circle"></i> Accept & Continue to Dashboard
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

</body>
</html>