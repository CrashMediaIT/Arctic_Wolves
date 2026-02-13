<?php
/**
 * PWA Admin Permissions - Mobile-native permissions overview
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$roles = [
    ['role' => 'admin', 'label' => 'Administrator', 'desc' => 'Full system access, user management, settings', 'icon' => 'fa-crown', 'color' => '#8B5CF6'],
    ['role' => 'coach', 'label' => 'Head Coach', 'desc' => 'Session management, evaluations, drills', 'icon' => 'fa-whistle', 'color' => '#3B82F6'],
    ['role' => 'team_coach', 'label' => 'Team Coach', 'desc' => 'Team sessions, athlete stats, practice plans', 'icon' => 'fa-users', 'color' => '#10B981'],
    ['role' => 'health_coach', 'label' => 'Health Coach', 'desc' => 'Health assessments, nutrition, wellness', 'icon' => 'fa-heartbeat', 'color' => '#EF4444'],
    ['role' => 'athlete', 'label' => 'Athlete', 'desc' => 'View sessions, stats, goals, bookings', 'icon' => 'fa-running', 'color' => '#F59E0B'],
    ['role' => 'parent', 'label' => 'Parent', 'desc' => 'View child progress, bookings, payments', 'icon' => 'fa-user-friends', 'color' => '#6B6B7B'],
    ['role' => 'front_desk', 'label' => 'Front Desk', 'desc' => 'Check-ins, POS, scheduling', 'icon' => 'fa-desktop', 'color' => '#3B82F6'],
];
?>
<style>
.m-perms { padding: 16px; font-family: Inter, sans-serif; }
.m-perms-header { margin-bottom: 16px; }
.m-perms-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-perms-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-perm-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-perm-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.m-perm-body { flex: 1; min-width: 0; }
.m-perm-label { font-size: 14px; font-weight: 600; color: #fff; }
.m-perm-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-perms-desktop {
    display: block; text-align: center; margin-top: 16px; padding: 12px;
    background: rgba(107,70,193,0.15); color: #8B5CF6; border-radius: 10px;
    font-size: 13px; font-weight: 600; text-decoration: none; min-height: 44px;
    line-height: 20px;
}
</style>

<div class="m-perms">
    <div class="m-perms-header">
        <h2 class="m-perms-title">Permissions & Roles</h2>
        <p class="m-perms-sub"><?= count($roles) ?> roles defined</p>
    </div>

    <?php foreach ($roles as $r): ?>
    <div class="m-perm-card">
        <div class="m-perm-icon" style="background:<?= $r['color'] ?>20;color:<?= $r['color'] ?>;">
            <i class="fas <?= $r['icon'] ?>"></i>
        </div>
        <div class="m-perm-body">
            <div class="m-perm-label"><?= htmlspecialchars($r['label']) ?></div>
            <div class="m-perm-desc"><?= htmlspecialchars($r['desc']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <a href="?page=admin_permissions&desktop=1" class="m-perms-desktop">
        <i class="fas fa-desktop"></i> Full Management on Desktop
    </a>
</div>
