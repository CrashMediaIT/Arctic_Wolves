<?php
/**
 * PWA Audit Log - Mobile-native audit log viewer
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$logs = [];
try {
    $stmt = $pdo->prepare("SELECT id, action, description, user_id, ip_address, created_at FROM audit_logs ORDER BY created_at DESC LIMIT 30");
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $logs = []; }

function mAuditTimeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', $ts);
}
?>
<style>
.m-audit { padding: 16px; font-family: Inter, sans-serif; }
.m-audit-header { margin-bottom: 16px; }
.m-audit-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-audit-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-audit-card {
    display: flex; align-items: flex-start; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-audit-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(59,130,246,0.15); color: #3B82F6; font-size: 14px; flex-shrink: 0;
}
.m-audit-body { flex: 1; min-width: 0; }
.m-audit-action { font-size: 13px; font-weight: 600; color: #fff; }
.m-audit-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-audit-meta { font-size: 11px; color: #6B6B7B; margin-top: 4px; display: flex; gap: 10px; flex-wrap: wrap; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-audit">
    <div class="m-audit-header">
        <h2 class="m-audit-title">Audit Log</h2>
        <p class="m-audit-sub">Last <?= count($logs) ?> entr<?= count($logs) !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($logs)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No audit logs found</p>
        </div>
    <?php else: ?>
        <?php foreach ($logs as $log): ?>
        <div class="m-audit-card">
            <div class="m-audit-icon"><i class="fas fa-shield-alt"></i></div>
            <div class="m-audit-body">
                <div class="m-audit-action"><?= htmlspecialchars($log['action'] ?? '') ?></div>
                <?php if (!empty($log['description'])): ?>
                <div class="m-audit-desc"><?= htmlspecialchars($log['description']) ?></div>
                <?php endif; ?>
                <div class="m-audit-meta">
                    <span><i class="fas fa-clock"></i> <?= mAuditTimeAgo($log['created_at']) ?></span>
                    <?php if (!empty($log['ip_address'])): ?>
                    <span><i class="fas fa-globe"></i> <?= htmlspecialchars($log['ip_address']) ?></span>
                    <?php endif; ?>
                    <span><i class="fas fa-user"></i> #<?= (int)$log['user_id'] ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
