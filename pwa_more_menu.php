<?php
/**
 * PWA "More" Menu - Role-based navigation
 * Mirrors the ACWolvesApp MoreStackScreen navigation.
 * Included by pwa.php when page=pwa_more
 */

// These variables are available from pwa.php:
// $user_name, $user_role, $isAdmin, $isCoach, $isAnyCoach, $isHealthCoach,
// $isTeamCoach, $isTeamStaff, $isParent, $isFrontDesk, $isHR, $isAccounting,
// $canAccessPOS, $canAccessHealthManagement, $canAccessHR, $canAccessAccounting, $isStaff
?>

<!-- User Info -->
<div class="pwa-user-card">
    <div class="pwa-user-avatar"><?= strtoupper(substr($user_name, 0, 1)) ?></div>
    <div>
        <div class="pwa-user-name"><?= htmlspecialchars($user_name) ?></div>
        <div class="pwa-user-role"><?= str_replace('_', ' ', $user_role) ?></div>
    </div>
</div>

<!-- Main Features -->
<div class="pwa-menu-group">
    <div class="pwa-section-label">Main Menu</div>
    <ul class="pwa-menu-list">
        <a href="?page=stats" class="pwa-menu-item">
            <i class="fas fa-chart-line"></i> Performance Stats
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=messages" class="pwa-menu-item">
            <i class="fas fa-comments"></i> Messages
            <span id="nav-msg-badge" style="display:none;background:var(--primary);color:#fff;font-size:10px;font-weight:700;padding:1px 6px;border-radius:10px;margin-left:auto;margin-right:8px;"></span>
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=video" class="pwa-menu-item">
            <i class="fas fa-video"></i> Video
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=health" class="pwa-menu-item">
            <i class="fas fa-heart-pulse"></i> Health
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <?php if (isset($canAccessDevPrograms) && $canAccessDevPrograms): ?>
        <a href="?page=personal_development" class="pwa-menu-item">
            <i class="fas fa-hockey-puck"></i> Personal Development
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <?php endif; ?>
        <a href="?page=shop" class="pwa-menu-item">
            <i class="fas fa-store"></i> Shop
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=payment_history" class="pwa-menu-item">
            <i class="fas fa-receipt"></i> Purchase History
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=notifications" class="pwa-menu-item">
            <i class="fas fa-bell"></i> Notifications
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>

<!-- Team Section (Team Coaches) -->
<?php if ($isTeamStaff): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Team</div>
    <ul class="pwa-menu-list">
        <a href="?page=team_roster" class="pwa-menu-item">
            <i class="fas fa-users"></i> Roster
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- Coaches Corner -->
<?php if ($isAnyCoach): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Coaches Corner</div>
    <ul class="pwa-menu-list">
        <a href="?page=coach_calendar" class="pwa-menu-item">
            <i class="fas fa-calendar"></i> Calendar
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=drills" class="pwa-menu-item">
            <i class="fas fa-clipboard-list"></i> Drills
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=practice" class="pwa-menu-item">
            <i class="fas fa-file-lines"></i> Practice Plans
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=roster" class="pwa-menu-item">
            <i class="fas fa-users-gear"></i> Roster
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=coach_stopwatch" class="pwa-menu-item">
            <i class="fas fa-stopwatch"></i> Stopwatch
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=coach_shot_speed" class="pwa-menu-item">
            <i class="fas fa-hockey-puck"></i> Shot Speed
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=coach_video_reviews" class="pwa-menu-item">
            <i class="fas fa-video"></i> Video Reviews
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=travel" class="pwa-menu-item">
            <i class="fas fa-plane"></i> Travel
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=record_drill_video" class="pwa-menu-item">
            <i class="fas fa-video"></i> Video Recording
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=gameplan" class="pwa-menu-item">
            <i class="fas fa-chess-board"></i> Game Plan
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <?php if (isset($canAccessDevPrograms) && $canAccessDevPrograms): ?>
        <a href="?page=development_programs" class="pwa-menu-item">
            <i class="fas fa-hockey-puck"></i> Development Programs
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Health Management -->
<?php if ($canAccessHealthManagement): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Health</div>
    <ul class="pwa-menu-list">
        <a href="?page=library_workouts" class="pwa-menu-item">
            <i class="fas fa-dumbbell"></i> Strength & Conditioning
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=library_nutrition" class="pwa-menu-item">
            <i class="fas fa-utensils"></i> Nutrition
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=roster" class="pwa-menu-item">
            <i class="fas fa-users-gear"></i> Roster
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- Finance (Admin) -->
<?php if ($canAccessAccounting): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Accounting & Reports</div>
    <ul class="pwa-menu-list">
        <a href="?page=finance_dashboard" class="pwa-menu-item">
            <i class="fas fa-chart-pie"></i> Finance Dashboard
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=financial_reports" class="pwa-menu-item">
            <i class="fas fa-chart-pie"></i> Financial Reports Hub
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=reports_user" class="pwa-menu-item">
            <i class="fas fa-users-gear"></i> User Reports
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=credits_refunds" class="pwa-menu-item">
            <i class="fas fa-money-bill-transfer"></i> Credits & Refunds
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=expenses" class="pwa-menu-item">
            <i class="fas fa-receipt"></i> Expenses
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=products" class="pwa-menu-item">
            <i class="fas fa-box-open"></i> Products
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- POS (Admin + Front Desk) -->
<?php if ($canAccessPOS): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Point of Sale</div>
    <ul class="pwa-menu-list">
        <a href="?page=pos_terminal" class="pwa-menu-item">
            <i class="fas fa-cash-register"></i> POS Terminal
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=inventory_management" class="pwa-menu-item">
            <i class="fas fa-warehouse"></i> Inventory & Orders
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=pos_online_orders" class="pwa-menu-item">
            <i class="fas fa-shipping-fast"></i> Online Orders
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=pos_time_tracking" class="pwa-menu-item">
            <i class="fas fa-clock"></i> Time Tracking
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=pos_schedule" class="pwa-menu-item">
            <i class="fas fa-calendar-alt"></i> My Schedule
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=sip_settings" class="pwa-menu-item">
            <i class="fas fa-address-book"></i> Company Directory
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- HR (Admin + HR) -->
<?php if ($canAccessHR): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">HR</div>
    <ul class="pwa-menu-list">
        <a href="?page=admin_staff_scheduling" class="pwa-menu-item">
            <i class="fas fa-calendar-check"></i> Staff Scheduling
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=hr_time_tracking" class="pwa-menu-item">
            <i class="fas fa-clock"></i> Time Tracking
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=payroll" class="pwa-menu-item">
            <i class="fas fa-money-check-dollar"></i> Payroll
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=onboarding" class="pwa-menu-item">
            <i class="fas fa-user-plus"></i> Onboarding
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=employee_contracts" class="pwa-menu-item">
            <i class="fas fa-file-signature"></i> Contracts
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=complaints" class="pwa-menu-item">
            <i class="fas fa-exclamation-triangle"></i> Complaints
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=termination" class="pwa-menu-item">
            <i class="fas fa-user-slash"></i> Termination
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- Admin Section -->
<?php if ($isAdmin): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Administration</div>
    <ul class="pwa-menu-list">
        <a href="?page=all_users" class="pwa-menu-item">
            <i class="fas fa-users"></i> All Users
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=categories" class="pwa-menu-item">
            <i class="fas fa-layer-group"></i> Resource Management
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=eval_framework" class="pwa-menu-item">
            <i class="fas fa-clipboard-check"></i> Eval Framework
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=system_notification" class="pwa-menu-item">
            <i class="fas fa-bell"></i> System Notification
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=admin_security" class="pwa-menu-item">
            <i class="fas fa-shield-halved"></i> Security
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=system_tools" class="pwa-menu-item">
            <i class="fas fa-screwdriver-wrench"></i> System Tools
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <!-- Audit Log removed - available in Security Center -->
        <a href="?page=marketing" class="pwa-menu-item">
            <i class="fas fa-bullhorn"></i> Marketing
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?page=admin_wishlist" class="pwa-menu-item">
            <i class="fas fa-clipboard-list"></i> Wishlist
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- Parent Features -->
<?php if ($isParent): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Parent</div>
    <ul class="pwa-menu-list">
        <a href="?page=camp_checkin" class="pwa-menu-item">
            <i class="fas fa-check-circle"></i> Camp Check-in
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
<?php endif; ?>

<!-- Account -->
<div class="pwa-menu-group">
    <div class="pwa-section-label">Account</div>
    <ul class="pwa-menu-list">
        <?php if ($isStaff): ?>
        <a href="?page=sip_settings" class="pwa-menu-item">
            <i class="fas fa-address-book"></i> Company Directory
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <?php endif; ?>
        <a href="?page=profile" class="pwa-menu-item">
            <i class="fas fa-user-gear"></i> Profile Settings
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="?view=desktop" class="pwa-menu-item">
            <i class="fas fa-desktop"></i> Desktop View
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
        <a href="logout.php" class="pwa-menu-item" style="color:#ef4444;">
            <i class="fas fa-power-off" style="color:#ef4444;"></i> Sign Out
            <i class="fas fa-chevron-right menu-chevron"></i>
        </a>
    </ul>
</div>
