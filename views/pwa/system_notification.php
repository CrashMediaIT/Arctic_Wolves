<?php
/**
 * PWA System Notifications - Mobile-native system notification management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$sysNotifs = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, message, notification_type, is_active, start_date, end_date FROM system_notifications ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $sysNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sysNotifs = []; }
?>
<style>
.m-sysnotif { padding: 16px; font-family: Inter, sans-serif; }
.m-sysnotif-header { margin-bottom: 16px; }
.m-sysnotif-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sysnotif-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sysnotif-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-sysnotif-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-sysnotif-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-sysnotif-badge {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; flex-shrink: 0; margin-left: 8px;
}
.m-sysnotif-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-sysnotif-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-sysnotif-type {
    font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6; display: inline-block; margin-bottom: 6px;
}
.m-sysnotif-msg { font-size: 12px; color: #A8A8B8; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-sysnotif-dates { font-size: 11px; color: #6B6B7B; margin-top: 6px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sysnotif">
    <div class="m-sysnotif-header">
        <h2 class="m-sysnotif-title">System Notifications</h2>
        <p class="m-sysnotif-sub"><?= count($sysNotifs) ?> notification<?= count($sysNotifs) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($sysNotifs)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullhorn"></i>
            <p>No system notifications</p>
        </div>
    <?php else: ?>
        <?php foreach ($sysNotifs as $n):
            $active = (int)($n['is_active'] ?? 0);
        ?>
        <div class="m-sysnotif-card">
            <div class="m-sysnotif-top">
                <div class="m-sysnotif-name"><?= htmlspecialchars($n['title'] ?? '') ?></div>
                <span class="m-sysnotif-badge <?= $active ? 'm-sysnotif-active' : 'm-sysnotif-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
            </div>
            <?php if (!empty($n['notification_type'])): ?>
            <span class="m-sysnotif-type"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $n['notification_type']))) ?></span>
            <?php endif; ?>
            <?php if (!empty($n['message'])): ?>
            <div class="m-sysnotif-msg"><?= htmlspecialchars($n['message']) ?></div>
            <?php endif; ?>
            <?php if (!empty($n['start_date']) || !empty($n['end_date'])): ?>
            <div class="m-sysnotif-dates">
                <?php if (!empty($n['start_date'])): ?>From <?= htmlspecialchars(date('M j, Y', strtotime($n['start_date']))) ?><?php endif; ?>
                <?php if (!empty($n['end_date'])): ?> — Until <?= htmlspecialchars(date('M j, Y', strtotime($n['end_date']))) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
