<?php
/**
 * PWA Admin Permissions - Mobile-native permissions management
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

// Fetch permissions from DB grouped by category
$dbPermissions = [];
try {
    $dbPermissions = $pdo->query("SELECT * FROM permissions ORDER BY category, permission_name")->fetchAll(PDO::FETCH_GROUP);
} catch (PDOException $e) { $dbPermissions = []; }

$dbRoles = ['athlete', 'coach', 'coach_plus', 'admin'];
$role_perms = [];
foreach ($dbRoles as $dbRole) {
    try {
        $stmt = $pdo->prepare("SELECT p.permission_key, rp.granted FROM permissions p LEFT JOIN role_permissions rp ON p.id = rp.permission_id AND rp.role = ?");
        $stmt->execute([$dbRole]);
        $role_perms[$dbRole] = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) { $role_perms[$dbRole] = []; }
}
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
.m-perms-tabs { display: flex; gap: 8px; margin-bottom: 16px; }
.m-perms-tab {
    flex: 1; padding: 10px 8px; background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #A8A8B8; font-size: 12px; font-weight: 600;
    text-align: center; cursor: pointer; min-height: 44px;
    display: flex; align-items: center; justify-content: center; gap: 4px;
    font-family: Inter, sans-serif;
}
.m-perms-tab.m-active { background: rgba(107,70,193,0.2); color: #8B5CF6; border-color: #6B46C1; }
.m-perms-panel { display: none; }
.m-perms-panel.m-active { display: block; }
.m-perms-cat-title {
    font-size: 13px; font-weight: 600; color: #8B5CF6; margin: 16px 0 8px;
    text-transform: uppercase; letter-spacing: 0.5px;
}
.m-perms-row {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; margin-bottom: 6px;
}
.m-perms-row-name { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-perms-row-desc { font-size: 11px; color: #A8A8B8; margin-bottom: 8px; }
.m-perms-checks { display: flex; gap: 12px; flex-wrap: wrap; }
.m-perms-check-label {
    display: flex; align-items: center; gap: 6px; font-size: 11px; color: #A8A8B8;
    cursor: pointer; min-height: 32px;
}
.m-perms-check-label input { width: 18px; height: 18px; accent-color: #6B46C1; cursor: pointer; }
.m-perms-save-bar {
    position: sticky; bottom: 0; padding: 12px 0; margin-top: 12px;
    background: #0A0A0F; border-top: 1px solid #2D2D3F; z-index: 10;
}
.m-perms-save-btn {
    width: 100%; padding: 12px; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; font-size: 14px; font-weight: 600; min-height: 44px;
    cursor: pointer; font-family: Inter, sans-serif;
}
.m-perms-alert {
    padding: 10px 12px; border-radius: 10px; margin-bottom: 12px; font-size: 12px;
    background: rgba(16,185,129,0.15); color: #10B981; border: 1px solid rgba(16,185,129,0.3);
}
</style>

<div class="m-perms">
    <div class="m-perms-header">
        <h2 class="m-perms-title">Permissions & Roles</h2>
        <p class="m-perms-sub"><?= count($roles) ?> roles defined</p>
    </div>

    <?php if (isset($_GET['status']) && $_GET['status'] === 'permissions_updated'): ?>
    <div class="m-perms-alert"><i class="fas fa-check-circle"></i> Permissions updated successfully!</div>
    <?php endif; ?>

    <div class="m-perms-tabs">
        <button class="m-perms-tab m-active" onclick="mPermsTab('roles')"><i class="fas fa-user-tag"></i> Roles</button>
        <button class="m-perms-tab" onclick="mPermsTab('manage')"><i class="fas fa-sliders-h"></i> Manage</button>
    </div>

    <div id="m-perms-roles" class="m-perms-panel m-active">
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
    </div>

    <div id="m-perms-manage" class="m-perms-panel">
        <?php if (empty($dbPermissions)): ?>
            <div style="text-align:center;padding:30px 20px;color:#6B6B7B;">
                <i class="fas fa-shield-halved" style="font-size:28px;display:block;margin-bottom:10px;"></i>
                <p style="font-size:13px;margin:0;">No permissions configured yet.</p>
            </div>
        <?php else: ?>
        <form method="POST" action="process_permissions.php">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_role_permissions">

            <?php foreach ($dbPermissions as $category => $perms): ?>
            <div class="m-perms-cat-title"><i class="fas fa-folder"></i> <?= htmlspecialchars(ucwords($category)) ?></div>
            <?php foreach ($perms as $perm): ?>
            <div class="m-perms-row">
                <div class="m-perms-row-name"><?= htmlspecialchars($perm['permission_name']) ?></div>
                <?php if ($perm['description']): ?>
                <div class="m-perms-row-desc"><?= htmlspecialchars($perm['description']) ?></div>
                <?php endif; ?>
                <div class="m-perms-checks">
                    <?php foreach ($dbRoles as $dbRole):
                        $is_checked = isset($role_perms[$dbRole][$perm['permission_key']]) && $role_perms[$dbRole][$perm['permission_key']];
                        $is_disabled = $dbRole === 'admin';
                    ?>
                    <label class="m-perms-check-label">
                        <input type="checkbox" name="perms[<?= $dbRole ?>][<?= $perm['permission_key'] ?>]" value="1" <?= $is_checked ? 'checked' : '' ?> <?= $is_disabled ? 'disabled' : '' ?>>
                        <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $dbRole))) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>

            <div class="m-perms-save-bar">
                <button type="submit" class="m-perms-save-btn"><i class="fas fa-save"></i> Save Permissions</button>
            </div>
        </form>
        <?php endif; ?>
    </div>

    <a href="?page=admin_permissions&desktop=1" class="m-perms-desktop">
        <i class="fas fa-desktop"></i> Full Management on Desktop
    </a>
</div>

<script>
function mPermsTab(tab) {
    document.querySelectorAll('.m-perms-tab').forEach(function(t) { t.classList.remove('m-active'); });
    document.querySelectorAll('.m-perms-panel').forEach(function(p) { p.classList.remove('m-active'); });
    document.getElementById('m-perms-' + tab).classList.add('m-active');
    event.currentTarget.classList.add('m-active');
}
</script>
