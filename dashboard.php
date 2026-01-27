<?php
// =========================================================
// DASHBOARD CONTROLLER - COMPREHENSIVE VERSION WITH NEW NAVIGATION
// =========================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

// Set security headers including CSP
setSecurityHeaders();

// Generate CSRF token for this session
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || $pdo === null) {
    die("Database connection failed. Please check your configuration. Error: " . ($db_error ?? 'Unknown error'));
}

if (!isset($_SESSION['logged_in'])) { header("Location: login.php"); exit(); }

$user_id   = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';
$user_name = $_SESSION['user_name'] ?? 'Guest';

// Role checks including new roles
$isAdmin       = ($user_role === 'admin');
$isCoach       = ($user_role === 'coach');
$isHealthCoach = ($user_role === 'health_coach');
$isTeamCoach   = ($user_role === 'team_coach');
$isParent      = ($user_role === 'parent');
$isFrontDesk   = ($user_role === 'front_desk_staff');

// Combined role checks for sections
$isAnyCoach    = ($isCoach || $isHealthCoach || $isAdmin);
$isTeamStaff   = ($isTeamCoach);
$canAccessPOS  = ($isAdmin || $isFrontDesk);
$canAccessHealthManagement = ($isHealthCoach || $isAdmin);

$page = $_GET['page'] ?? 'home';

// Redirect front desk staff to their special dashboard if on home page
if ($page === 'home' && $isFrontDesk) {
    $page = 'front_desk_home';
}

// FULL ROUTING TABLE - PARENT AND CHILD PAGES
$allowed_pages = [
    // Main Menu
    'home'                    => 'views/home.php',
    'stats'                   => 'views/stats.php',
    'goals'                   => 'views/goals.php',
    
    // Sessions - Parent page with tabs
    'sessions'                => 'views/sessions.php',
    'upcoming_sessions'       => 'views/sessions.php',
    'booking'                 => 'views/sessions.php',
    
    // Video - Parent page with tabs
    'video'                   => 'views/video.php',
    'drill_review'            => 'views/video.php',
    'coaches_reviews'         => 'views/video.php',
    
    // Health - Parent page with tabs
    'health'                  => 'views/health.php',
    'strength_conditioning'   => 'views/health.php',
    'nutrition'               => 'views/health.php',
    
    // Team (Team Coaches)
    'team_roster'             => 'views/team_roster.php',
    
    // Coaches Corner - Parent pages with tabs
    'drills'                  => 'views/drills.php',
    'drill_library'           => 'views/drills.php',
    'create_drill'            => 'views/drills.php',
    'import_drill'            => 'views/drills.php',
    
    'practice'                => 'views/practice.php',
    'practice_library'        => 'views/practice.php',
    'create_practice'         => 'views/practice.php',
    'practice_create'         => 'views/practice.php',
    
    'roster'                  => 'views/coach_roster.php',
    
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
    'hr_time_tracking'        => 'views/hr_time_tracking.php',
    
    // Administration (Admin)
    'all_users'               => 'views/admin_users.php',
    'categories'              => 'views/admin_categories.php',
    'admin_age_skill'         => 'views/admin_age_skill.php',
    'eval_framework'          => 'views/admin_eval_framework.php',
    'system_notification'     => 'views/admin_system_notifications.php',
    'audit_log'               => 'views/admin_audit_logs.php',
    'cron_jobs'               => 'views/admin_cron_jobs.php',
    'system_tools'            => 'views/admin_system_tools.php',
    'admin_settings'          => 'views/admin_settings.php',
    'admin_database_tools'    => 'views/admin_database_tools.php',
    'admin_database_backup'   => 'views/admin_database_backup.php',
    'admin_database_restore'  => 'views/admin_database_restore.php',
    'admin_system_check'      => 'views/admin_system_check.php',
    'admin_permissions'       => 'views/admin_permissions.php',
    'admin_locations'         => 'views/admin_locations.php',
    'admin_team_coaches'      => 'views/admin_team_coaches.php',
    'admin_discounts'         => 'views/admin_discounts.php',
    'admin_session_types'     => 'views/admin_session_types.php',
    'admin_email_reports'     => 'views/email_logs.php',
    'business_cards'          => 'views/admin_business_cards.php',
    'admin_packages'          => 'views/admin_packages.php',
    'admin_plan_categories'   => 'views/admin_plan_categories.php',
    'admin_coach_termination' => 'views/admin_coach_termination.php',
    'admin_feature_import'    => 'views/admin_feature_import.php',
    'admin_theme_settings'    => 'views/admin_theme_settings.php',
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
    'coach_session_evaluations' => 'views/coach_session_evaluations.php',
    'session_evaluation_form'   => 'views/session_evaluation_form.php',
    
    // Evaluations
    'evaluations_goals'       => 'views/evaluations_goals.php',
    'evaluations_skills'      => 'views/evaluations_skills.php',
    
    // Notifications
    'notifications'           => 'views/notifications.php',
    
    // Reports
    'reports_athlete'         => 'views/reports_athlete.php',
    'reports_income'          => 'views/reports_income.php',
    'scheduled_reports'       => 'views/scheduled_reports.php',
    
    // Sessions
    'create_session'          => 'views/create_session.php',
    'session_history'         => 'views/session_history.php',
    'session_detail'          => 'views/session_detail.php',
    
    // Packages and Payments
    'packages'                => 'views/packages.php',
    'payment_history'         => 'views/payment_history.php',
    'refunds'                 => 'views/refunds.php',
    
    // Other
    'workouts'                => 'views/workouts.php',
    'testing'                 => 'views/testing.php',
    'parent_home'             => 'views/parent_home.php',
    'athlete_detail'          => 'views/athlete_detail.php',
    
    // Health Management (Health Coaches & Admins)
    'library_workouts'        => 'views/library_workouts.php',
    'library_nutrition'       => 'views/library_nutrition.php',
    'health_coach_roster'     => 'views/health_coach_roster.php',
    
    // Profile and Settings
    'profile'                 => 'views/profile.php',
    'settings'                => 'views/settings.php'
];

$view_file = $allowed_pages[$page] ?? 'views/home.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arctic Wolves Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/components.css">
    <link rel="stylesheet" href="views/shared_styles.css">
    <style>
        :root { 
            --primary: #6B46C1; 
            --primary-hover: #7C3AED; 
            --primary-light: #8B5CF6;
            --bg: #0A0A0F; 
            --bg-secondary: #13131A;
            --sidebar: #0D0D14; 
            --border: #2D2D3F; 
            --border-light: #3A3A4F;
            --text: #A8A8B8; 
            --text-muted: #6B6B7B;
            --card-bg: #16161F;
        }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); font-family: 'Inter', sans-serif; color: #fff; display: flex; height: 100vh; overflow: hidden; }
        
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
        .nav-link i { width: 18px; text-align: center; }
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
        .content-area { flex: 1; padding: 40px; overflow-y: auto; }
        
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
        <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo">
        ARCTIC <span>WOLVES</span>
    </a>
    
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
            <a href="?page=sessions" class="nav-link <?= in_array($page, ['sessions','upcoming_sessions','booking'])?'active':'' ?>">
                <i class="fa-solid fa-calendar-check icon"></i> Sessions
            </a>
            <a href="?page=video" class="nav-link <?= in_array($page, ['video','drill_review','coaches_reviews'])?'active':'' ?>">
                <i class="fa-solid fa-video icon"></i> Video
            </a>
            <a href="?page=health" class="nav-link <?= in_array($page, ['health','strength_conditioning','nutrition'])?'active':'' ?>">
                <i class="fa-solid fa-heart-pulse icon"></i> Health
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

    <!-- COACHES CORNER (Coaches, Health Coaches, and Admins) -->
    <?php if($isAnyCoach): ?>
    <div class="nav-group">
        <span class="nav-label">Coaches Corner</span>
        <nav class="nav-menu">
            <a href="?page=drills" class="nav-link <?= in_array($page, ['drills','drill_library','create_drill','import_drill'])?'active':'' ?>">
                <i class="fa-solid fa-clipboard-list icon"></i> Drills
            </a>
            <a href="?page=practice" class="nav-link <?= in_array($page, ['practice','practice_library','create_practice','practice_create'])?'active':'' ?>">
                <i class="fa-solid fa-file-lines icon"></i> Practice Plans
            </a>
            <a href="?page=roster" class="nav-link <?= $page=='roster'?'active':'' ?>">
                <i class="fa-solid fa-users-gear icon"></i> Roster
            </a>
            <a href="?page=coach_session_evaluations" class="nav-link <?= in_array($page, ['coach_session_evaluations','session_evaluation_form'])?'active':'' ?>">
                <i class="fa-solid fa-clipboard-check icon"></i> Session Evaluations
            </a>
            <a href="?page=coach_evaluations" class="nav-link <?= $page=='coach_evaluations'?'active':'' ?>">
                <i class="fa-solid fa-chart-line icon"></i> Athlete Evaluations
            </a>
            <a href="?page=travel" class="nav-link <?= in_array($page, ['travel','mileage'])?'active':'' ?>">
                <i class="fa-solid fa-plane icon"></i> Travel
            </a>
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
            <a href="?page=health_coach_roster" class="nav-link <?= $page=='health_coach_roster'?'active':'' ?>">
                <i class="fa-solid fa-users-gear icon"></i> My Athletes
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- ACCOUNTING AND REPORTS (Admins only) -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">Accounting & Reports</span>
        <nav class="nav-menu">
            <a href="?page=finance_dashboard" class="nav-link <?= in_array($page, ['finance_dashboard', 'accounting_dashboard', 'billing_dashboard'])?'active':'' ?>">
                <i class="fa-solid fa-chart-pie icon"></i> Finance Dashboard
            </a>
            <a href="?page=financial_reports" class="nav-link <?= in_array($page, ['reports', 'schedules', 'financial_reports'])?'active':'' ?>">
                <i class="fa-solid fa-chart-pie icon"></i> Financial Reports Hub
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

    <!-- MERCHANDISE (Admins only) -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">Merchandise</span>
        <nav class="nav-menu">
            <a href="?page=merchandise_categories" class="nav-link <?= $page=='merchandise_categories'?'active':'' ?>">
                <i class="fa-solid fa-tags icon"></i> Categories
            </a>
            <a href="?page=merchandise_products" class="nav-link <?= $page=='merchandise_products'?'active':'' ?>">
                <i class="fa-solid fa-tshirt icon"></i> Products
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
            <a href="?page=pos_time_tracking" class="nav-link <?= $page=='pos_time_tracking'?'active':'' ?>">
                <i class="fa-solid fa-clock icon"></i> Time Tracking
            </a>
            <a href="?page=pos_schedule" class="nav-link <?= $page=='pos_schedule'?'active':'' ?>">
                <i class="fa-solid fa-calendar-alt icon"></i> My Schedule
            </a>
            <a href="?page=shop_orders" class="nav-link <?= $page=='shop_orders'?'active':'' ?>">
                <i class="fa-solid fa-shopping-bag icon"></i> Shop Orders
            </a>
            <a href="?page=pos_transactions" class="nav-link <?= $page=='pos_transactions'?'active':'' ?>">
                <i class="fa-solid fa-receipt icon"></i> POS Transactions
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- HR (Admins only) -->
    <?php if($isAdmin): ?>
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
                <i class="fa-solid fa-folder-tree icon"></i> Categories
            </a>
            <a href="?page=eval_framework" class="nav-link <?= $page=='eval_framework'?'active':'' ?>">
                <i class="fa-solid fa-clipboard-check icon"></i> Eval Framework
            </a>
            <a href="?page=system_notification" class="nav-link <?= $page=='system_notification'?'active':'' ?>">
                <i class="fa-solid fa-bell icon"></i> System Notification
            </a>
            <a href="?page=audit_log" class="nav-link <?= $page=='audit_log'?'active':'' ?>">
                <i class="fa-solid fa-list-check icon"></i> Audit Log
            </a>
            <a href="?page=system_tools" class="nav-link <?= $page=='system_tools'?'active':'' ?>">
                <i class="fa-solid fa-screwdriver-wrench icon"></i> System Tools
            </a>
            <a href="?page=business_cards" class="nav-link <?= $page=='business_cards'?'active':'' ?>">
                <i class="fa-solid fa-id-card icon"></i> Business Cards
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <div class="sidebar-footer">
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

// Persist submenu state on page load based on active page
document.addEventListener('DOMContentLoaded', function() {
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
                alert('Failed to switch athlete view');
            }
        });
    }
}
</script>

<!-- Main Application JavaScript -->
<script src="js/app.js"></script>

</body>
</html>