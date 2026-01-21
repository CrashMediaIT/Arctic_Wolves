<?php
// =========================================================
// DASHBOARD CONTROLLER - COMPREHENSIVE VERSION WITH NEW NAVIGATION
// =========================================================

ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/db_config.php';

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

// Combined role checks for sections
$isAnyCoach    = ($isCoach || $isHealthCoach || $isAdmin);
$isTeamStaff   = ($isTeamCoach);

$page = $_GET['page'] ?? 'home';

// FULL ROUTING TABLE - PARENT AND CHILD PAGES
$allowed_pages = [
    // Main Menu
    'home'                    => 'views/home.php',
    'stats'                   => 'views/stats.php',
    
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
    
    'roster'                  => 'views/coach_roster.php',
    
    'travel'                  => 'views/travel.php',
    'mileage'                 => 'views/travel.php',
    
    // Accounting and Reports (Admin)
    'accounting_dashboard'    => 'views/accounting_dashboard.php',
    'billing_dashboard'       => 'views/accounting_billing.php',
    'reports'                 => 'views/accounting_reports.php',
    'schedules'               => 'views/accounting_schedules.php',
    'credits_refunds'         => 'views/accounting_credits.php',
    'expenses'                => 'views/accounting_expenses.php',
    'products'                => 'views/accounting_products.php',
    
    // HR (Admin)
    'termination'             => 'views/hr_termination.php',
    
    // Administration (Admin)
    'all_users'               => 'views/admin_users.php',
    'categories'              => 'views/admin_categories.php',
    'eval_framework'          => 'views/admin_eval_framework.php',
    'system_notification'     => 'views/admin_notifications.php',
    'audit_log'               => 'views/admin_audit_log.php',
    'cron_jobs'               => 'views/admin_cron_jobs.php',
    'system_tools'            => 'views/admin_system_tools.php',
    
    // Additional views
    'profile'                 => 'views/profile.php',
    'settings'                => 'views/settings.php'
];

$view_file = $allowed_pages[$page] ?? 'views/home.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Crash Hockey Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
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
        <img src="https://images.crashmedia.ca/images/2026/01/18/logo.png" alt="Logo">
        CRASH <span>HOCKEY</span>
    </a>
    
    <!-- MAIN MENU (For all users) -->
    <div class="nav-group">
        <span class="nav-label">Main Menu</span>
        <nav class="nav-menu">
            <a href="?page=home" class="nav-link <?= $page=='home'?'active':'' ?>">
                <i class="fa-solid fa-house icon"></i> Home
            </a>
            <a href="?page=stats" class="nav-link <?= $page=='stats'?'active':'' ?>">
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
            <a href="?page=practice" class="nav-link <?= in_array($page, ['practice','practice_library','create_practice'])?'active':'' ?>">
                <i class="fa-solid fa-file-lines icon"></i> Practice Plans
            </a>
            <a href="?page=roster" class="nav-link <?= $page=='roster'?'active':'' ?>">
                <i class="fa-solid fa-users-gear icon"></i> Roster
            </a>
            <a href="?page=travel" class="nav-link <?= in_array($page, ['travel','mileage'])?'active':'' ?>">
                <i class="fa-solid fa-plane icon"></i> Travel
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- ACCOUNTING AND REPORTS (Admins only) -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">Accounting & Reports</span>
        <nav class="nav-menu">
            <a href="?page=accounting_dashboard" class="nav-link <?= $page=='accounting_dashboard'?'active':'' ?>">
                <i class="fa-solid fa-chart-pie icon"></i> Accounting Dashboard
            </a>
            <a href="?page=billing_dashboard" class="nav-link <?= $page=='billing_dashboard'?'active':'' ?>">
                <i class="fa-solid fa-file-invoice-dollar icon"></i> Billing Dashboard
            </a>
            <a href="?page=reports" class="nav-link <?= $page=='reports'?'active':'' ?>">
                <i class="fa-solid fa-chart-bar icon"></i> Reports
            </a>
            <a href="?page=schedules" class="nav-link <?= $page=='schedules'?'active':'' ?>">
                <i class="fa-solid fa-calendar-days icon"></i> Schedules
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

    <!-- HR (Admins only) -->
    <?php if($isAdmin): ?>
    <div class="nav-group">
        <span class="nav-label">HR</span>
        <nav class="nav-menu">
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
            <a href="?page=cron_jobs" class="nav-link <?= $page=='cron_jobs'?'active':'' ?>">
                <i class="fa-solid fa-clock icon"></i> Cron Jobs
            </a>
            <a href="?page=system_tools" class="nav-link <?= $page=='system_tools'?'active':'' ?>">
                <i class="fa-solid fa-screwdriver-wrench icon"></i> System Tools
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
                $stmt = $conn->prepare("SELECT id, name FROM users WHERE parent_id = ? AND user_role = 'athlete'");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                while($athlete = $result->fetch_assoc()):
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