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
    <div class="pwa-menu-list">
        <a href="?page=stats" class="pwa-menu-item">
            <i class="fas fa-chart-line"></i> Stats
        </a>
        <a href="?page=messages" class="pwa-menu-item pwa-menu-item-badged">
            <i class="fas fa-comments"></i> Messages
            <span id="nav-msg-badge" class="pwa-menu-badge"></span>
        </a>
        <a href="?page=video" class="pwa-menu-item">
            <i class="fas fa-video"></i> Video
        </a>
        <a href="?page=health" class="pwa-menu-item">
            <i class="fas fa-heart-pulse"></i> Health
        </a>
        <?php if (isset($canAccessDevPrograms) && $canAccessDevPrograms): ?>
        <a href="?page=personal_development" class="pwa-menu-item">
            <i class="fas fa-hockey-puck"></i> Development
        </a>
        <?php endif; ?>
        <a href="?page=shop" class="pwa-menu-item">
            <i class="fas fa-store"></i> Shop
        </a>
        <a href="?page=payment_history" class="pwa-menu-item">
            <i class="fas fa-receipt"></i> Purchases
        </a>
        <a href="?page=notifications" class="pwa-menu-item">
            <i class="fas fa-bell"></i> Notifications
        </a>
    </div>
</div>

<!-- Team Section (Team Coaches) -->
<?php if ($isTeamStaff): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Team</div>
    <div class="pwa-menu-list">
        <a href="?page=team_roster" class="pwa-menu-item">
            <i class="fas fa-users"></i> Roster
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Coaches Corner -->
<?php if ($isAnyCoach): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Coaches Corner</div>
    <div class="pwa-menu-list">
        <a href="?page=coach_calendar" class="pwa-menu-item">
            <i class="fas fa-calendar"></i> Calendar
        </a>
        <a href="?page=drills" class="pwa-menu-item">
            <i class="fas fa-clipboard-list"></i> Drills
        </a>
        <a href="?page=practice" class="pwa-menu-item">
            <i class="fas fa-file-lines"></i> Practice Plans
        </a>
        <a href="?page=roster" class="pwa-menu-item">
            <i class="fas fa-users-gear"></i> Roster
        </a>
        <a href="?page=coach_stopwatch" class="pwa-menu-item">
            <i class="fas fa-stopwatch"></i> Stopwatch
        </a>
        <a href="?page=coach_shot_speed" class="pwa-menu-item">
            <i class="fas fa-hockey-puck"></i> Shot Speed
        </a>
        <a href="?page=coach_video_reviews" class="pwa-menu-item">
            <i class="fas fa-video"></i> Video Reviews
        </a>
        <a href="?page=travel" class="pwa-menu-item">
            <i class="fas fa-plane"></i> Travel
        </a>
        <a href="?page=record_drill_video" class="pwa-menu-item">
            <i class="fas fa-video"></i> Record Video
        </a>
        <a href="?page=gameplan" class="pwa-menu-item">
            <i class="fas fa-chess-board"></i> Game Plan
        </a>
        <?php if (isset($canAccessDevPrograms) && $canAccessDevPrograms): ?>
        <a href="?page=development_programs" class="pwa-menu-item">
            <i class="fas fa-hockey-puck"></i> Dev Programs
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Health Management -->
<?php if ($canAccessHealthManagement): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Health</div>
    <div class="pwa-menu-list">
        <a href="?page=library_workouts" class="pwa-menu-item">
            <i class="fas fa-dumbbell"></i> Strength & Conditioning
        </a>
        <a href="?page=library_nutrition" class="pwa-menu-item">
            <i class="fas fa-utensils"></i> Nutrition
        </a>
        <a href="?page=roster" class="pwa-menu-item">
            <i class="fas fa-users-gear"></i> Roster
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Finance (Admin) -->
<?php if ($canAccessAccounting): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Accounting</div>
    <div class="pwa-menu-list">
        <a href="?page=finance_dashboard" class="pwa-menu-item">
            <i class="fas fa-chart-pie"></i> Finance
        </a>
        <a href="?page=credits_refunds" class="pwa-menu-item">
            <i class="fas fa-money-bill-transfer"></i> Credits & Refunds
        </a>
        <a href="?page=expenses" class="pwa-menu-item">
            <i class="fas fa-receipt"></i> Expenses
        </a>
        <a href="?page=products" class="pwa-menu-item">
            <i class="fas fa-box-open"></i> Products
        </a>
    </div>
</div>
<?php endif; ?>

<!-- POS (Admin + Front Desk) -->
<?php if ($canAccessPOS): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Point of Sale</div>
    <div class="pwa-menu-list">
        <a href="?page=pos_terminal" class="pwa-menu-item">
            <i class="fas fa-cash-register"></i> POS Terminal
        </a>
        <a href="?page=inventory_management" class="pwa-menu-item">
            <i class="fas fa-warehouse"></i> Inventory
        </a>
        <a href="?page=pos_online_orders" class="pwa-menu-item">
            <i class="fas fa-shipping-fast"></i> Online Orders
        </a>
        <a href="?page=pos_time_tracking" class="pwa-menu-item">
            <i class="fas fa-clock"></i> Time Tracking
        </a>
        <a href="?page=pos_schedule" class="pwa-menu-item">
            <i class="fas fa-calendar-alt"></i> My Schedule
        </a>
        <a href="?page=sip_settings" class="pwa-menu-item">
            <i class="fas fa-address-book"></i> Directory
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Admin Section -->
<?php if ($isAdmin): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Administration</div>
    <div class="pwa-menu-list">
        <a href="?page=all_users" class="pwa-menu-item">
            <i class="fas fa-users"></i> All Users
        </a>
        <a href="?page=categories" class="pwa-menu-item">
            <i class="fas fa-layer-group"></i> Resources
        </a>
        <a href="?page=system_notification" class="pwa-menu-item">
            <i class="fas fa-bell"></i> Notifications
        </a>
        <a href="?page=admin_security" class="pwa-menu-item">
            <i class="fas fa-shield-halved"></i> Security
        </a>
        <a href="?page=marketing" class="pwa-menu-item">
            <i class="fas fa-bullhorn"></i> Marketing
        </a>
        <a href="?page=admin_wishlist" class="pwa-menu-item">
            <i class="fas fa-clipboard-list"></i> Wishlist
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Parent Features -->
<?php if ($isParent): ?>
<div class="pwa-menu-group">
    <div class="pwa-section-label">Parent</div>
    <div class="pwa-menu-list">
        <a href="?page=camp_checkin" class="pwa-menu-item">
            <i class="fas fa-check-circle"></i> Camp Check-in
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Account -->
<div class="pwa-menu-group">
    <div class="pwa-section-label">Account</div>
    <div class="pwa-menu-list">
        <?php if ($isStaff): ?>
        <a href="?page=sip_settings" class="pwa-menu-item">
            <i class="fas fa-address-book"></i> Directory
        </a>
        <?php endif; ?>
        <a href="?page=profile" class="pwa-menu-item">
            <i class="fas fa-user-gear"></i> Profile
        </a>
        <a href="?view=desktop" class="pwa-menu-item">
            <i class="fas fa-desktop"></i> Desktop View
        </a>
        <a href="logout.php" class="pwa-menu-item pwa-menu-item-danger">
            <i class="fas fa-power-off"></i> Sign Out
        </a>
    </div>
</div>
