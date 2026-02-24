<?php
/**
 * PWA User Permissions - Mobile-native user permission detail
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$uid = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$userInfo = null;
if ($uid > 0) {
    try {
        $stmt = $pdo->prepare("SELECT id, first_name, last_name, email, role, is_active, created_at FROM users WHERE id = ?");
        $stmt->execute([$uid]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        $userInfo = decryptUserRow($userInfo);
    } catch (PDOException $e) { $userInfo = null; }
}
?>
<style>
.m-userperm { padding: 16px; font-family: Inter, sans-serif; }
.m-userperm-header { margin-bottom: 16px; }
.m-userperm-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-userperm-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-userperm-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 14px;
    padding: 20px; margin-bottom: 12px;
}
.m-userperm-avatar {
    width: 56px; height: 56px; border-radius: 50%; margin: 0 auto 12px;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; font-weight: 700; color: #fff;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
}
.m-userperm-name { font-size: 16px; font-weight: 700; color: #fff; text-align: center; }
.m-userperm-email { font-size: 13px; color: #A8A8B8; text-align: center; margin-top: 2px; }
.m-userperm-info { margin-top: 16px; }
.m-userperm-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid #2D2D3F;
}
.m-userperm-row:last-child { border-bottom: none; }
.m-userperm-label { font-size: 13px; color: #A8A8B8; }
.m-userperm-value { font-size: 13px; font-weight: 600; color: #fff; }
.m-userperm-badge { font-size: 10px; padding: 3px 10px; border-radius: 4px; font-weight: 600; }
.m-userperm-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-userperm-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-userperm-role { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-userperm-desktop {
    display: block; text-align: center; margin-top: 16px; padding: 12px;
    background: rgba(107,70,193,0.15); color: #8B5CF6; border-radius: 10px;
    font-size: 13px; font-weight: 600; text-decoration: none; min-height: 44px;
    line-height: 20px;
}
.m-userperm-back {
    display: inline-flex; align-items: center; gap: 6px;
    font-size: 13px; color: #8B5CF6; text-decoration: none; margin-bottom: 12px;
    min-height: 44px;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-userperm">
    <a href="?page=all_users" class="m-userperm-back"><i class="fas fa-arrow-left"></i> All Users</a>

    <div class="m-userperm-header">
        <h2 class="m-userperm-title">User Permissions</h2>
    </div>

    <?php if (!$userInfo): ?>
        <div class="m-empty-state">
            <i class="fas fa-user-slash"></i>
            <p>User not found</p>
        </div>
    <?php else:
        $initial = strtoupper(mb_substr($userInfo['first_name'] ?? '?', 0, 1));
        $fullName = htmlspecialchars(($userInfo['first_name'] ?? '') . ' ' . ($userInfo['last_name'] ?? ''));
        $active = (int)($userInfo['is_active'] ?? 0);
        $role = $userInfo['role'] ?? 'unknown';
    ?>
        <div class="m-userperm-card">
            <div class="m-userperm-avatar"><?= $initial ?></div>
            <div class="m-userperm-name"><?= $fullName ?></div>
            <div class="m-userperm-email"><?= htmlspecialchars($userInfo['email'] ?? '') ?></div>

            <div class="m-userperm-info">
                <div class="m-userperm-row">
                    <span class="m-userperm-label">Role</span>
                    <span class="m-userperm-badge m-userperm-role"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $role))) ?></span>
                </div>
                <div class="m-userperm-row">
                    <span class="m-userperm-label">Status</span>
                    <span class="m-userperm-badge <?= $active ? 'm-userperm-active' : 'm-userperm-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
                </div>
                <div class="m-userperm-row">
                    <span class="m-userperm-label">User ID</span>
                    <span class="m-userperm-value">#<?= (int)$userInfo['id'] ?></span>
                </div>
                <?php if (!empty($userInfo['created_at'])): ?>
                <div class="m-userperm-row">
                    <span class="m-userperm-label">Joined</span>
                    <span class="m-userperm-value"><?= htmlspecialchars(date('M j, Y', strtotime($userInfo['created_at']))) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <a href="?page=user_permissions&user_id=<?= (int)$userInfo['id'] ?>&desktop=1" class="m-userperm-desktop">
            <i class="fas fa-desktop"></i> Edit Permissions on Desktop
        </a>
    <?php endif; ?>
</div>
