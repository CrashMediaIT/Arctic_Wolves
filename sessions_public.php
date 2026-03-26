<?php
/**
 * Public Sessions Page
 * Displays sessions and packages available for registration
 * Visitors can view sessions and click Register to sign up
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Start session for potential redirect after login
session_start();

// Handle registration intent token creation
if (isset($_GET['register'])) {
    $intentType = $_GET['type'] ?? 'session';
    $intentId = intval($_GET['id'] ?? 0);
    
    if ($intentId > 0 && $db_connected) {
        // Generate a unique token
        try {
            $token = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            error_log("Token generation failed: " . $e->getMessage());
            header("Location: login.php");
            exit();
        }
        $expiresAt = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        try {
            // Store the intent - support both session table and template-based sessions
            $sessionId = null;
            $packageId = null;
            $templateId = null;
            $sessionDateId = null;
            
            if ($intentType === 'session') {
                $sessionId = $intentId;
            } elseif ($intentType === 'package') {
                $packageId = $intentId;
            } elseif ($intentType === 'dev_program') {
                $templateId = $intentId;
            } elseif ($intentType === 'template_date') {
                $sessionDateId = $intentId;
                // Look up the template_id from the session date
                $tdStmt = $pdo->prepare("SELECT template_id FROM training_session_dates WHERE id = ?");
                $tdStmt->execute([$intentId]);
                $templateId = $tdStmt->fetchColumn() ?: null;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO session_registration_intents 
                (session_id, package_id, template_id, session_date_id, intent_token, expires_at) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([$sessionId, $packageId, $templateId, $sessionDateId, $token, $expiresAt]);
            
            // Redirect to login with token
            header("Location: login.php?session_intent=" . $token);
            exit();
        } catch (PDOException $e) {
            error_log("Session intent error: " . $e->getMessage());
            // Fall through to normal page load
        }
    }
    
    // If we couldn't create intent, just redirect to login
    header("Location: login.php");
    exit();
}

// Fetch public sessions (all upcoming scheduled sessions)
$sessions = [];
$packages = [];

if ($db_connected) {
    // Fetch upcoming sessions from the sessions table
    try {
        $sessionsStmt = $pdo->query("
            SELECT s.id, 
                   s.title as name,
                   s.description,
                   s.session_date,
                   s.session_time,
                   s.duration_minutes,
                   s.price,
                   s.max_participants,
                   s.session_type_id,
                   s.location_id,
                   s.coach_id,
                   CONCAT(s.session_date, ' ', COALESCE(s.session_time, '00:00:00')) as next_date,
                   u.first_name as coach_first_name, u.last_name as coach_last_name,
                   l.name as location_name,
                   1 as total_dates,
                   'session' as source_type,
                   NULL as template_id,
                   NULL as session_date_id
            FROM sessions s
            LEFT JOIN users u ON s.coach_id = u.id
            LEFT JOIN locations l ON s.location_id = l.id
            WHERE s.status = 'scheduled'
              AND (s.session_date > CURDATE() OR (s.session_date = CURDATE() AND COALESCE(s.session_time, '00:00:00') > CURTIME()))
            ORDER BY s.session_date ASC, s.session_time ASC
        ");
        $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);
        $sessions = decryptUserRows($sessions);
        foreach ($sessions as &$s) {
            $s['coach_name'] = trim(($s['coach_first_name'] ?? '') . ' ' . ($s['coach_last_name'] ?? ''));
        }
        unset($s);
    } catch (PDOException $e) {
        error_log("Public sessions fetch error: " . $e->getMessage());
        $sessions = [];
    }
    
    // Fetch upcoming sessions from training_session_templates + training_session_dates
    // Exclude dev programs (is_dev_program = 1) since they display in their own section
    try {
        $templateStmt = $pdo->query("
            SELECT t.id as template_id,
                   td.id as session_date_id,
                   t.name,
                   t.description,
                   DATE(td.session_date) as session_date,
                   TIME(td.session_date) as session_time,
                   t.duration_minutes,
                   t.price,
                   COALESCE(td.max_participants, t.max_participants) as max_participants,
                   t.session_type_id,
                   t.location_id,
                   t.coach_id,
                   td.session_date as next_date,
                   u.first_name as coach_first_name, u.last_name as coach_last_name,
                   l.name as location_name,
                   1 as total_dates,
                   'session' as source_type,
                   NULL as id,
                   t.waitlist_only
            FROM training_session_templates t
            INNER JOIN training_session_dates td ON td.template_id = t.id
            LEFT JOIN users u ON t.coach_id = u.id
            LEFT JOIN locations l ON t.location_id = l.id
            WHERE t.is_active = 1
              AND td.is_active = 1
              AND (t.is_dev_program = 0 OR t.is_dev_program IS NULL)
              AND (DATE(td.session_date) > CURDATE() OR (DATE(td.session_date) = CURDATE() AND TIME(td.session_date) > CURTIME()))
            ORDER BY td.session_date ASC
        ");
        $templateSessions = $templateStmt->fetchAll(PDO::FETCH_ASSOC);
        $templateSessions = decryptUserRows($templateSessions);
        foreach ($templateSessions as &$ts) {
            $ts['coach_name'] = trim(($ts['coach_first_name'] ?? '') . ' ' . ($ts['coach_last_name'] ?? ''));
        }
        unset($ts);
        
        // Merge and sort all sessions by date
        $sessions = array_merge($sessions, $templateSessions);
        usort($sessions, function($a, $b) {
            return strtotime($a['next_date']) - strtotime($b['next_date']);
        });
    } catch (PDOException $e) {
        error_log("Template sessions fetch error: " . $e->getMessage());
        // Keep sessions from the first query
    }
    
    // Fetch active packages for landing page (credits, bundled, dollar_value)
    try {
        $packagesStmt = $pdo->query("
            SELECT * FROM packages 
            WHERE is_active = 1 AND (package_type IS NULL OR package_type NOT IN ('camp', 'multi_week'))
            ORDER BY price ASC
        ");
        $packages = $packagesStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Public packages fetch error: " . $e->getMessage());
        $packages = [];
    }
    
    // Fetch camps and multi-week programs (all active ones show on landing page)
    $camps_programs = [];
    try {
        $cpStmt = $pdo->query("
            SELECT p.*, 
                   ag.name as age_group_name,
                   sl.name as skill_level_name
            FROM packages p
            LEFT JOIN age_groups ag ON p.age_group_id = ag.id
            LEFT JOIN skill_levels sl ON p.skill_level_id = sl.id
            WHERE p.is_active = 1 AND p.package_type IN ('camp', 'multi_week')
            ORDER BY p.package_type, p.camp_start_date ASC, p.price ASC
        ");
        $camps_programs = $cpStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Public camps/programs fetch error: " . $e->getMessage());
        $camps_programs = [];
    }
    
    // Fetch long-term development programs
    $devPrograms = [];
    try {
        $dpStmt = $pdo->query("
            SELECT tst.id, tst.name, tst.description, tst.price, tst.duration_weeks,
                   tst.session_type, tst.max_participants, tst.waitlist_only,
                   u.first_name as coach_first_name, u.last_name as coach_last_name
            FROM training_session_templates tst
            LEFT JOIN users u ON tst.coach_id = u.id
            WHERE tst.is_active = 1 AND tst.is_dev_program = 1
            ORDER BY tst.name ASC
        ");
        $devPrograms = $dpStmt->fetchAll(PDO::FETCH_ASSOC);
        $devPrograms = decryptUserRows($devPrograms);
    } catch (PDOException $e) {
        // Column may not exist on older installations — try adding it once, then retry
        try {
            $pdo->exec("ALTER TABLE `training_session_templates` ADD COLUMN IF NOT EXISTS `is_dev_program` TINYINT(1) DEFAULT 0");
            $pdo->exec("ALTER TABLE `training_session_templates` ADD COLUMN IF NOT EXISTS `duration_weeks` INT DEFAULT NULL");
            $dpStmt = $pdo->query("
                SELECT tst.id, tst.name, tst.description, tst.price, tst.duration_weeks,
                       tst.session_type, tst.max_participants, tst.waitlist_only,
                       u.first_name as coach_first_name, u.last_name as coach_last_name
                FROM training_session_templates tst
                LEFT JOIN users u ON tst.coach_id = u.id
                WHERE tst.is_active = 1 AND tst.is_dev_program = 1
                ORDER BY tst.name ASC
            ");
            $devPrograms = $dpStmt->fetchAll(PDO::FETCH_ASSOC);
            $devPrograms = decryptUserRows($devPrograms);
        } catch (PDOException $e2) {
            error_log("Public dev programs fetch error: " . $e2->getMessage());
            $devPrograms = [];
        }
    }
}

// Get current view mode
$viewMode = $_GET['view'] ?? 'list';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>Training Sessions | Arctic Wolves</title>
    <meta name="description" content="View and register for Arctic Wolves training sessions.">
    
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="style.css">
    
    <style>
        .sessions-page-content {
            padding: 40px 0 80px;
            min-height: 70vh;
        }
        
        .page-header-section {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-header-section h1 {
            font-size: clamp(2rem, 4vw, 3rem);
            margin-bottom: 10px;
            color: #fff;
        }
        
        .page-header-section p {
            color: var(--text-dim);
            font-size: 1.1rem;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .view-tabs {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 40px;
        }
        
        .view-tab {
            padding: 12px 24px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: var(--text-dim);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .view-tab:hover, .view-tab.active {
            background: var(--primary);
            border-color: var(--primary);
            color: #fff;
        }
        
        /* Products Row - Side by Side Layout */
        .products-row {
            display: flex;
            gap: 24px;
            margin-bottom: 60px;
        }
        
        .product-column {
            flex: 1;
            min-width: 0;
        }
        
        .scroll-wrapper {
            position: relative;
        }
        
        .scroll-container {
            display: flex;
            gap: 20px;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            padding: 16px 0;
        }
        
        .scroll-container::-webkit-scrollbar {
            display: none;
        }
        
        .scroll-container > * {
            min-width: 280px;
            flex-shrink: 0;
        }
        
        .scroll-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: 1px solid var(--border);
            background: var(--bg-card);
            color: #fff;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        
        .scroll-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .scroll-btn.scroll-left {
            left: -12px;
        }
        
        .scroll-btn.scroll-right {
            right: -12px;
        }
        
        .scroll-btn.hidden {
            display: none;
        }
        
        @media (max-width: 992px) {
            .products-row {
                flex-direction: column;
            }
        }
        
        /* Packages Section */
        .packages-section {
            margin-bottom: 0;
        }
        
        .section-title {
            font-size: 1.3rem;
            color: #fff;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .packages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 380px));
            gap: 24px;
            justify-content: center;
        }
        
        .package-card {
            background: var(--bg-card);
            border: 2px solid var(--primary);
            border-radius: 16px;
            padding: 28px;
            position: relative;
            transition: all 0.3s;
        }
        
        .package-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(107, 70, 193, 0.3);
        }
        
        .package-badge {
            position: absolute;
            top: -12px;
            left: 24px;
            background: var(--primary);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
        }
        
        .package-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            margin: 12px 0 8px;
        }
        
        .package-price {
            font-size: 2.5rem;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 16px;
        }
        
        .package-details {
            margin-bottom: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        
        .package-details p {
            color: var(--text-dim);
            padding: 6px 0;
            font-size: 14px;
        }
        
        .package-details i {
            color: var(--primary);
            margin-right: 10px;
            width: 20px;
        }
        
        /* Camps & Programs Section */
        .camps-section {
            margin-bottom: 0;
        }
        
        .camps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 400px));
            gap: 24px;
            justify-content: center;
        }
        
        .camp-card {
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            position: relative;
            transition: all 0.3s;
        }
        
        .camp-card.camp-type {
            border-color: rgba(16, 185, 129, 0.4);
        }
        
        .camp-card.program-type {
            border-color: rgba(245, 158, 11, 0.4);
        }
        
        .camp-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.3);
        }
        
        .camp-card.camp-type:hover {
            box-shadow: 0 12px 24px rgba(16, 185, 129, 0.2);
        }
        
        .camp-card.program-type:hover {
            box-shadow: 0 12px 24px rgba(245, 158, 11, 0.2);
        }
        
        .camp-badge {
            position: absolute;
            top: -12px;
            left: 24px;
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .camp-name {
            font-size: 1.4rem;
            font-weight: 700;
            color: #fff;
            margin: 12px 0 8px;
        }
        
        .camp-price {
            font-size: 2.5rem;
            font-weight: 900;
            color: #10b981;
            margin-bottom: 16px;
        }
        
        .camp-card.program-type .camp-price {
            color: #f59e0b;
        }
        
        .camp-details {
            margin-bottom: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        
        .camp-details p {
            color: var(--text-dim);
            padding: 6px 0;
            font-size: 14px;
        }
        
        .camp-details i {
            color: var(--primary);
            margin-right: 10px;
            width: 20px;
        }
        
        .camp-register-btn {
            background: #10b981 !important;
        }
        
        .camp-card.program-type .camp-register-btn {
            background: #f59e0b !important;
        }
        
        /* Sessions Section */
        .sessions-list {
            display: grid;
            gap: 20px;
        }
        
        .session-card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 24px;
            align-items: center;
            transition: all 0.3s;
        }
        
        .session-card:hover {
            border-color: var(--primary);
            transform: translateX(4px);
        }
        
        .session-date-box {
            text-align: center;
            background: var(--bg-main);
            padding: 16px 20px;
            border-radius: 10px;
            min-width: 80px;
        }
        
        .session-date-box .month {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary);
            text-transform: uppercase;
        }
        
        .session-date-box .day {
            font-size: 28px;
            font-weight: 900;
            color: #fff;
            line-height: 1.2;
        }
        
        .session-date-box .weekday {
            font-size: 11px;
            color: var(--text-dim);
        }
        
        .session-info h3 {
            font-size: 1.2rem;
            color: #fff;
            margin-bottom: 8px;
        }
        
        .session-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .session-meta span {
            color: var(--text-dim);
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .session-meta i {
            color: var(--primary);
        }
        
        .session-price {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
        }
        
        .register-btn {
            background: var(--primary);
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-block;
            margin-top: 12px;
        }
        
        .register-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(107, 70, 193, 0.3);
        }
        
        /* Waitlist button */
        .waitlist-btn {
            background: #f59e0b;
            color: #fff;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            transition: all 0.3s;
            display: inline-block;
            margin-top: 12px;
        }
        
        .waitlist-btn:hover {
            background: #d97706;
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(245, 158, 11, 0.3);
        }
        
        .waitlist-badge {
            display: inline-block;
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            margin-left: 8px;
            text-transform: uppercase;
        }
        
        /* Dev Programs Section */
        .dev-programs-section {
            margin-bottom: 48px;
        }
        
        .dev-programs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
        }
        
        .dev-card {
            background: var(--bg-card);
            border: 2px solid rgba(59, 130, 246, 0.4);
            border-radius: 16px;
            padding: 28px;
            position: relative;
            transition: all 0.3s;
        }
        
        .dev-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(59, 130, 246, 0.2);
        }
        
        .dev-badge {
            position: absolute;
            top: -12px;
            left: 24px;
            background: #3b82f6;
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .dev-card .camp-name {
            color: #fff;
        }
        
        .dev-card .camp-price {
            color: #3b82f6;
        }
        
        .dev-card .camp-register-btn {
            background: #3b82f6 !important;
        }
        
        .dev-card .camp-register-btn:hover {
            background: #2563eb !important;
        }
        
        /* Calendar View */
        .calendar-container {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .calendar-nav {
            display: flex;
            gap: 8px;
        }
        
        .calendar-nav-btn {
            width: 36px;
            height: 36px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--text-dim);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .calendar-nav-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        .calendar-month-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
        }
        
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        
        .calendar-day-header {
            text-align: center;
            padding: 12px;
            color: var(--text-dim);
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
        }
        
        .calendar-day {
            aspect-ratio: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 8px;
            border-radius: 8px;
            background: var(--bg-main);
            min-height: 80px;
        }
        
        .calendar-day.other-month {
            opacity: 0.3;
        }
        
        .calendar-day.today {
            border: 2px solid var(--primary);
        }
        
        .calendar-day.has-session {
            background: rgba(107, 70, 193, 0.1);
        }
        
        .calendar-day .day-number {
            font-weight: 700;
            color: #fff;
            margin-bottom: 4px;
        }
        
        .calendar-session-dot {
            width: 8px;
            height: 8px;
            background: var(--primary);
            border-radius: 50%;
            margin: 2px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-dim);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            color: var(--border);
        }
        
        @media (max-width: 768px) {
            .session-card {
                grid-template-columns: 1fr;
                text-align: center;
            }
            
            .session-date-box {
                justify-self: center;
            }
            
            .session-meta {
                justify-content: center;
            }
            
            .session-actions {
                justify-self: center;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container nav-flex">
            <a href="index.php" class="logo-area" style="display: flex; align-items: center; gap: 15px; text-decoration: none;">
                <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves Logo" style="height: 40px; width: auto;">
                
                <div>
                    <div class="logo-text">ARCTIC<span>WOLVES</span></div>
                    <div class="header-affiliation">Player Development</div>
                </div>
            </a>
            
            <div class="nav-menu">
                <a href="index.php">Home</a>
                <a href="sessions_public.php" style="color: var(--primary);">Sessions</a>
                <a href="shop.php">Shop</a>
                <a href="shop_cart.php" style="position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                </a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <section class="sessions-page-content">
        <div class="container">
            
            <!-- Products Row: Packages and Camps & Programs side by side -->
            <div class="products-row">
                <!-- Packages Column -->
                <div class="product-column">
                    <div class="packages-section">
                        <h2 class="section-title"><i class="fas fa-box"></i> Packages</h2>
                        <?php if (!empty($packages)): ?>
                        <div class="scroll-wrapper">
                            <button class="scroll-btn scroll-left hidden" onclick="scrollSection(this, -1)" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
                            <div class="scroll-container" data-scroll-container>
                                <?php foreach ($packages as $package): 
                                    $storeCredit = $package['store_credit'] ?? 0;
                                    $pkgWaitlistOnly = !empty($package['waitlist_only']);
                                ?>
                                <div class="package-card">
                                    <div class="package-badge"><i class="fas fa-star"></i> Package</div>
                                    <h3 class="package-name"><?= htmlspecialchars($package['name']) ?></h3>
                                    <div class="package-price">$<?= number_format($package['price'], 2) ?></div>
                                    <div class="package-details">
                                        <?php if ($package['credits'] > 0): ?>
                                        <p><i class="fas fa-calendar-check"></i> <?= $package['credits'] ?> sessions included</p>
                                        <?php endif; ?>
                                        <?php if ($storeCredit > 0): ?>
                                        <p><i class="fas fa-wallet"></i> $<?= number_format($storeCredit, 2) ?> store credit</p>
                                        <?php endif; ?>
                                        <?php if (!empty($package['valid_days'])): ?>
                                        <p><i class="fas fa-clock"></i> Valid for <?= $package['valid_days'] ?> days</p>
                                        <?php endif; ?>
                                        <?php if (!empty($package['description'])): ?>
                                        <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars($package['description']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($pkgWaitlistOnly): ?>
                                        <p><span class="waitlist-badge"><i class="fas fa-clock"></i> Waitlist Only</span></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($pkgWaitlistOnly): ?>
                                    <a href="?register=1&type=package&id=<?= $package['id'] ?>" class="waitlist-btn">
                                        <i class="fas fa-clock"></i> Join Waitlist
                                    </a>
                                    <?php else: ?>
                                    <a href="?register=1&type=package&id=<?= $package['id'] ?>" class="register-btn">
                                        <i class="fas fa-user-plus"></i> Register Now
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="scroll-btn scroll-right hidden" onclick="scrollSection(this, 1)" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <?php else: ?>
                        <p style="color: var(--text-dim); font-size: 14px;">No packages available.</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Camps & Programs Column -->
                <div class="product-column">
                    <div class="camps-section">
                        <h2 class="section-title"><i class="fas fa-campground"></i> Camps & Programs</h2>
                        <?php if (!empty($camps_programs)): ?>
                        <div class="scroll-wrapper">
                            <button class="scroll-btn scroll-left hidden" onclick="scrollSection(this, -1)" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
                            <div class="scroll-container" data-scroll-container>
                                <?php foreach ($camps_programs as $cp):
                                    $cpWaitlistOnly = !empty($cp['waitlist_only']);
                                ?>
                                <div class="camp-card <?= $cp['package_type'] === 'camp' ? 'camp-type' : 'program-type' ?>">
                                    <div class="camp-badge" style="background: <?= $cp['package_type'] === 'camp' ? '#10b981' : '#f59e0b' ?>;">
                                        <i class="fas fa-<?= $cp['package_type'] === 'camp' ? 'campground' : 'calendar-alt' ?>"></i>
                                        <?= $cp['package_type'] === 'camp' ? 'Camp' : 'Weekly Program' ?>
                                    </div>
                                    <h3 class="camp-name"><?= htmlspecialchars($cp['name']) ?></h3>
                                    <div class="camp-price">$<?= number_format($cp['price'], 2) ?></div>
                                    <div class="camp-details">
                                        <?php if ($cp['package_type'] === 'camp' && $cp['camp_start_date'] && $cp['camp_end_date']): ?>
                                        <p><i class="fas fa-calendar-day"></i> <?= date('M j', strtotime($cp['camp_start_date'])) ?> – <?= date('M j, Y', strtotime($cp['camp_end_date'])) ?></p>
                                        <?php endif; ?>
                                        <?php if ($cp['daily_start_time'] && $cp['daily_end_time']): ?>
                                        <p><i class="fas fa-clock"></i> <?= date('g:i A', strtotime($cp['daily_start_time'])) ?> – <?= date('g:i A', strtotime($cp['daily_end_time'])) ?></p>
                                        <?php endif; ?>
                                        <?php if ($cp['package_type'] === 'multi_week'): 
                                            try {
                                                $mwCount = $pdo->prepare("SELECT COUNT(*) FROM multiweek_program_dates WHERE package_id = ?");
                                                $mwCount->execute([$cp['id']]);
                                                $sessionCount = $mwCount->fetchColumn();
                                            } catch (PDOException $e) { $sessionCount = 0; }
                                        ?>
                                        <p><i class="fas fa-list-ol"></i> <?= $sessionCount ?> sessions over multiple weeks</p>
                                        <?php if ($cp['allow_individual_sessions']): ?>
                                        <p style="color: #10b981;"><i class="fas fa-check-circle"></i> Individual sessions available</p>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        <?php if (!empty($cp['age_group_name'])): ?>
                                        <p><i class="fas fa-users"></i> <?= htmlspecialchars($cp['age_group_name']) ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($cp['description'])): ?>
                                        <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars($cp['description']) ?></p>
                                        <?php endif; ?>
                                        <?php if ($cp['enable_child_checkin']): ?>
                                        <p style="color: #8B5CF6;"><i class="fas fa-child"></i> Child pickup enabled</p>
                                        <?php endif; ?>
                                        <?php if ($cpWaitlistOnly): ?>
                                        <p><span class="waitlist-badge"><i class="fas fa-clock"></i> Waitlist Only</span></p>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($cpWaitlistOnly): ?>
                                    <a href="?register=1&type=package&id=<?= $cp['id'] ?>" class="waitlist-btn">
                                        <i class="fas fa-clock"></i> Join Waitlist
                                    </a>
                                    <?php else: ?>
                                    <a href="?register=1&type=package&id=<?= $cp['id'] ?>" class="register-btn camp-register-btn">
                                        <i class="fas fa-user-plus"></i> <?= $cp['package_type'] === 'camp' ? 'Register for Camp' : 'Enroll Now' ?>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <button class="scroll-btn scroll-right hidden" onclick="scrollSection(this, 1)" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        <?php else: ?>
                        <p style="color: var(--text-dim); font-size: 14px;">No camps or programs available.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Long-Term Development Programs (above Training Sessions) -->
            <?php if (!empty($devPrograms)): ?>
            <div class="dev-programs-section">
                <h2 class="section-title"><i class="fas fa-chart-line"></i> Long-Term Development Programs</h2>
                <div class="dev-programs-grid">
                    <?php foreach ($devPrograms as $dp):
                        $dpCoach = trim(($dp['coach_first_name'] ?? '') . ' ' . ($dp['coach_last_name'] ?? ''));
                        $dpWaitlistOnly = !empty($dp['waitlist_only']);
                    ?>
                    <div class="dev-card">
                        <div class="dev-badge">
                            <i class="fas fa-<?= stripos($dp['name'], 'goalie') !== false ? 'shield-alt' : 'hockey-puck' ?>"></i>
                            Development Program
                        </div>
                        <h3 class="camp-name"><?= htmlspecialchars($dp['name']) ?></h3>
                        <div class="camp-price">$<?= number_format($dp['price'] ?? 0, 2) ?></div>
                        <div class="camp-details">
                            <?php if (!empty($dp['duration_weeks'])): ?>
                            <p><i class="fas fa-clock"></i> <?= (int)$dp['duration_weeks'] ?> week program</p>
                            <?php endif; ?>
                            <?php if (!empty($dpCoach)): ?>
                            <p><i class="fas fa-user-tie"></i> Coach: <?= htmlspecialchars($dpCoach) ?></p>
                            <?php endif; ?>
                            <?php if (!empty($dp['max_participants'])): ?>
                            <p><i class="fas fa-users"></i> Max <?= $dp['max_participants'] ?> participants</p>
                            <?php endif; ?>
                            <?php if (!empty($dp['description'])): ?>
                            <p><i class="fas fa-info-circle"></i> <?= htmlspecialchars($dp['description']) ?></p>
                            <?php endif; ?>
                            <?php if ($dpWaitlistOnly): ?>
                            <p><span class="waitlist-badge"><i class="fas fa-clock"></i> Waitlist Only</span></p>
                            <?php endif; ?>
                        </div>
                        <?php if ($dpWaitlistOnly): ?>
                        <a href="?register=1&type=dev_program&id=<?= $dp['id'] ?>" class="waitlist-btn">
                            <i class="fas fa-clock"></i> Join Waitlist
                        </a>
                        <?php else: ?>
                        <a href="?register=1&type=dev_program&id=<?= $dp['id'] ?>" class="register-btn camp-register-btn" style="background: #3b82f6 !important;">
                            <i class="fas fa-user-plus"></i> Enroll Now
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Training Sessions Header + View Toggle (below products) -->
            <div class="page-header-section">
                <h1><i class="fas fa-calendar-check" style="color: var(--primary);"></i> Training Sessions</h1>
                <p>Browse our upcoming training sessions and packages. Register to secure your spot!</p>
            </div>
            
            <!-- View Toggle -->
            <div class="view-tabs">
                <a href="?view=list" class="view-tab <?= $viewMode === 'list' ? 'active' : '' ?>">
                    <i class="fas fa-list"></i> List View
                </a>
                <a href="?view=calendar" class="view-tab <?= $viewMode === 'calendar' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i> Calendar View
                </a>
            </div>
            
            <!-- Sessions Section -->
            <div class="sessions-section">
                <h2 class="section-title"><i class="fas fa-calendar-day"></i> Upcoming Sessions</h2>
                
                <?php if ($viewMode === 'list'): ?>
                    <!-- List View -->
                    <?php if (!empty($sessions)): ?>
                    <div class="sessions-list">
                        <?php 
                        foreach ($sessions as $session): 
                            $sessionDate = strtotime($session['next_date']);
                            $sessWaitlistOnly = !empty($session['waitlist_only']);
                        ?>
                        <div class="session-card">
                            <div class="session-date-box">
                                <span class="month"><?= date('M', $sessionDate) ?></span>
                                <span class="day"><?= date('j', $sessionDate) ?></span>
                                <span class="weekday"><?= date('D', $sessionDate) ?></span>
                            </div>
                            <div class="session-info">
                                <h3><?= htmlspecialchars($session['name']) ?><?php if ($sessWaitlistOnly): ?><span class="waitlist-badge"><i class="fas fa-clock"></i> Waitlist Only</span><?php endif; ?></h3>
                                <div class="session-meta">
                                    <span><i class="fas fa-clock"></i> <?= date('g:i A', $sessionDate) ?></span>
                                    <span><i class="fas fa-hourglass-half"></i> <?= $session['duration_minutes'] ?> min</span>
                                    <?php if (!empty($session['coach_name'])): ?>
                                    <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($session['coach_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($session['location_name'])): ?>
                                    <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['location_name']) ?></span>
                                    <?php endif; ?>
                                    <?php if (!empty($session['max_participants'])): ?>
                                    <span><i class="fas fa-users"></i> Max <?= $session['max_participants'] ?> participants</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="session-actions" style="text-align: right;">
                                <div class="session-price">$<?= number_format($session['price'], 2) ?></div>
                                <?php if ($sessWaitlistOnly): ?>
                                <a href="?register=1&type=<?= !empty($session['session_date_id']) ? 'template_date' : 'session' ?>&id=<?= !empty($session['session_date_id']) ? $session['session_date_id'] : $session['id'] ?>" class="waitlist-btn">
                                    <i class="fas fa-clock"></i> Join Waitlist
                                </a>
                                <?php else: ?>
                                <a href="?register=1&type=<?= !empty($session['session_date_id']) ? 'template_date' : 'session' ?>&id=<?= !empty($session['session_date_id']) ? $session['session_date_id'] : $session['id'] ?>" class="register-btn">
                                    <i class="fas fa-user-plus"></i> Register
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No upcoming sessions available at this time.</p>
                        <p style="margin-top: 10px;">Check back soon or <a href="register.php" style="color: var(--primary);">register</a> to be notified of new sessions!</p>
                    </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <!-- Calendar View -->
                    <div class="calendar-container">
                        <div class="calendar-header">
                            <button class="calendar-nav-btn" onclick="changeMonth(-1)"><i class="fas fa-chevron-left"></i></button>
                            <h3 class="calendar-month-title" id="calendar-month-title">Loading...</h3>
                            <button class="calendar-nav-btn" onclick="changeMonth(1)"><i class="fas fa-chevron-right"></i></button>
                        </div>
                        
                        <div class="calendar-grid" id="calendar-grid">
                            <div class="calendar-day-header">Sun</div>
                            <div class="calendar-day-header">Mon</div>
                            <div class="calendar-day-header">Tue</div>
                            <div class="calendar-day-header">Wed</div>
                            <div class="calendar-day-header">Thu</div>
                            <div class="calendar-day-header">Fri</div>
                            <div class="calendar-day-header">Sat</div>
                        </div>
                        
                        <!-- Session Details Panel -->
                        <div id="session-details-panel" style="display: none; margin-top: 24px; padding: 20px; background: var(--bg-main); border-radius: 10px;">
                            <h4 style="color: #fff; margin-bottom: 12px;">Sessions on <span id="selected-date">-</span></h4>
                            <div id="selected-sessions-list"></div>
                        </div>
                    </div>
                    
                    <script>
                    // Session data from PHP
                    var sessionsData = <?= json_encode($sessions) ?>;
                    var currentDate = new Date();
                    
                    function renderCalendar() {
                        var year = currentDate.getFullYear();
                        var month = currentDate.getMonth();
                        
                        var monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                                         'July', 'August', 'September', 'October', 'November', 'December'];
                        document.getElementById('calendar-month-title').textContent = monthNames[month] + ' ' + year;
                        
                        var firstDay = new Date(year, month, 1).getDay();
                        var daysInMonth = new Date(year, month + 1, 0).getDate();
                        var today = new Date();
                        
                        // Build sessions by date
                        var sessionsByDate = {};
                        sessionsData.forEach(function(session) {
                            var date = new Date(session.next_date);
                            var dateKey = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
                            if (!sessionsByDate[dateKey]) sessionsByDate[dateKey] = [];
                            sessionsByDate[dateKey].push(session);
                        });
                        
                        var html = '';
                        // Day headers are already in HTML
                        
                        // Empty cells before first day
                        for (var i = 0; i < firstDay; i++) {
                            html += '<div class="calendar-day other-month"></div>';
                        }
                        
                        // Days of month
                        for (var day = 1; day <= daysInMonth; day++) {
                            var dateKey = year + '-' + (month + 1) + '-' + day;
                            var isToday = (today.getDate() === day && today.getMonth() === month && today.getFullYear() === year);
                            var hasSessions = sessionsByDate[dateKey] && sessionsByDate[dateKey].length > 0;
                            
                            var classes = 'calendar-day';
                            if (isToday) classes += ' today';
                            if (hasSessions) classes += ' has-session';
                            
                            html += '<div class="' + classes + '" onclick="showDaySessions(\'' + dateKey + '\')" style="cursor: ' + (hasSessions ? 'pointer' : 'default') + ';">';
                            html += '<span class="day-number">' + day + '</span>';
                            
                            if (hasSessions) {
                                html += '<div style="display: flex; flex-wrap: wrap; justify-content: center;">';
                                sessionsByDate[dateKey].forEach(function() {
                                    html += '<div class="calendar-session-dot"></div>';
                                });
                                html += '</div>';
                            }
                            
                            html += '</div>';
                        }
                        
                        document.getElementById('calendar-grid').innerHTML = 
                            '<div class="calendar-day-header">Sun</div>' +
                            '<div class="calendar-day-header">Mon</div>' +
                            '<div class="calendar-day-header">Tue</div>' +
                            '<div class="calendar-day-header">Wed</div>' +
                            '<div class="calendar-day-header">Thu</div>' +
                            '<div class="calendar-day-header">Fri</div>' +
                            '<div class="calendar-day-header">Sat</div>' + html;
                    }
                    
                    function changeMonth(delta) {
                        currentDate.setMonth(currentDate.getMonth() + delta);
                        renderCalendar();
                        document.getElementById('session-details-panel').style.display = 'none';
                    }
                    
                    function showDaySessions(dateKey) {
                        var sessionsByDate = {};
                        sessionsData.forEach(function(session) {
                            var date = new Date(session.next_date);
                            var dk = date.getFullYear() + '-' + (date.getMonth() + 1) + '-' + date.getDate();
                            if (!sessionsByDate[dk]) sessionsByDate[dk] = [];
                            sessionsByDate[dk].push(session);
                        });
                        
                        var daySessions = sessionsByDate[dateKey];
                        if (!daySessions || daySessions.length === 0) return;
                        
                        var panel = document.getElementById('session-details-panel');
                        var parts = dateKey.split('-');
                        var displayDate = new Date(parts[0], parts[1] - 1, parts[2]);
                        document.getElementById('selected-date').textContent = displayDate.toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
                        
                        var html = '';
                        daySessions.forEach(function(session) {
                            var sessionDate = new Date(session.next_date);
                            html += '<div style="padding: 16px; background: var(--bg-card); border-radius: 8px; margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">';
                            html += '<div>';
                            html += '<h4 style="color: #fff; margin-bottom: 4px;">' + session.name + '</h4>';
                            html += '<p style="color: var(--text-dim); font-size: 13px;">';
                            html += '<i class="fas fa-clock" style="color: var(--primary);"></i> ' + sessionDate.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
                            html += ' &bull; ' + session.duration_minutes + ' min';
                            if (session.coach_name) html += ' &bull; ' + session.coach_name;
                            html += '</p>';
                            html += '</div>';
                            html += '<div style="text-align: right;">';
                            html += '<div style="font-size: 1.2rem; font-weight: 800; color: var(--primary);">$' + parseFloat(session.price).toFixed(2) + '</div>';
                            var regType = session.session_date_id ? 'template_date' : 'session';
                            var regId = session.session_date_id ? session.session_date_id : session.id;
                            html += '<a href="?register=1&type=' + regType + '&id=' + regId + '" class="register-btn" style="padding: 8px 16px; font-size: 12px; margin-top: 8px;"><i class="fas fa-user-plus"></i> Register</a>';
                            html += '</div>';
                            html += '</div>';
                        });
                        
                        document.getElementById('selected-sessions-list').innerHTML = html;
                        panel.style.display = 'block';
                    }
                    
                    // Initialize calendar
                    renderCalendar();
                    </script>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <script>
    // Horizontal scroll button functionality
    function scrollSection(btn, direction) {
        var wrapper = btn.closest('.scroll-wrapper');
        var container = wrapper.querySelector('.scroll-container');
        var scrollAmount = 300;
        container.scrollBy({ left: direction * scrollAmount, behavior: 'smooth' });
    }

    function updateScrollButtons() {
        document.querySelectorAll('.scroll-wrapper').forEach(function(wrapper) {
            var container = wrapper.querySelector('.scroll-container');
            var leftBtn = wrapper.querySelector('.scroll-left');
            var rightBtn = wrapper.querySelector('.scroll-right');
            if (!container || !leftBtn || !rightBtn) return;

            var atStart = container.scrollLeft <= 0;
            var atEnd = container.scrollLeft + container.clientWidth >= container.scrollWidth - 1;

            leftBtn.classList.toggle('hidden', atStart);
            rightBtn.classList.toggle('hidden', atEnd);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.scroll-container').forEach(function(container) {
            container.addEventListener('scroll', updateScrollButtons);
        });
        updateScrollButtons();
        // Re-check after fonts/images load
        window.addEventListener('load', updateScrollButtons);
    });
    </script>

    <footer class="site-footer">
        <div class="container footer-flex">
            <div class="footer-left">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" style="height: 30px; opacity: 0.8;">
                    <div class="logo-text" style="font-size: 1.2rem;">ARCTIC<span>WOLVES</span></div>
                </div>
                
                <p class="footer-desc">High-performance athletic development.</p>
                
                <div class="social-tray">
                    <a href="https://www.instagram.com/arcticwolveshockey/" target="_blank" rel="noopener noreferrer" class="social-icon"><i class="fab fa-instagram"></i></a>
                </div>
            </div>
            <div class="footer-right">
                <div class="footer-col">
                    <h4>Direct Contact</h4>
                    <a href="mailto:info@arcticwolves.ca" class="footer-email-link">info@arcticwolves.ca</a>
                </div>
                <div class="footer-col">
                    <h4>Account</h4>
                    <a href="login.php">Athlete Portal</a>
                    <a href="register.php">Registration</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container footer-bottom-flex">
                <p>&copy; 2026 Arctic Wolves Player Development. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
