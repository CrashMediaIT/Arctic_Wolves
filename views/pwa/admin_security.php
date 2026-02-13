<?php
/**
 * PWA Admin Security - Mobile-native security overview
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$totalLogins = 0;
$failedLogins = 0;
$recentFailed = [];
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM login_history WHERE login_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $totalLogins = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM login_history WHERE success = 0 AND login_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stmt->execute();
    $failedLogins = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, username, ip_address, login_time FROM login_history WHERE success = 0 ORDER BY login_time DESC LIMIT 10");
    $stmt->execute();
    $recentFailed = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* silent */ }

function mSecTimeAgo($datetime) {
    $ts = strtotime($datetime);
    $diff = time() - $ts;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    return date('M j, g:ia', $ts);
}
?>
<style>
.m-security { padding: 16px; font-family: Inter, sans-serif; }
.m-security-header { margin-bottom: 16px; }
.m-security-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-security-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-security-kpis { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-security-kpi {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-security-kpi-val { font-size: 28px; font-weight: 700; margin: 0; }
.m-security-kpi-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; }
.m-security-section { font-size: 13px; font-weight: 600; color: #EF4444; margin: 16px 0 8px; }
.m-security-fail {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 14px; margin-bottom: 6px; min-height: 44px;
}
.m-security-fail-icon {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(239,68,68,0.15); color: #EF4444; font-size: 13px; flex-shrink: 0;
}
.m-security-fail-body { flex: 1; min-width: 0; }
.m-security-fail-user { font-size: 13px; font-weight: 600; color: #fff; }
.m-security-fail-meta { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-security">
    <div class="m-security-header">
        <h2 class="m-security-title">Security Overview</h2>
        <p class="m-security-sub">Last 24 hours</p>
    </div>

    <div class="m-security-kpis">
        <div class="m-security-kpi">
            <div class="m-security-kpi-val" style="color:#10B981;"><?= $totalLogins ?></div>
            <div class="m-security-kpi-label">Total Logins</div>
        </div>
        <div class="m-security-kpi">
            <div class="m-security-kpi-val" style="color:#EF4444;"><?= $failedLogins ?></div>
            <div class="m-security-kpi-label">Failed Attempts</div>
        </div>
    </div>

    <div class="m-security-section"><i class="fas fa-exclamation-triangle"></i> Recent Failed Logins</div>

    <?php if (empty($recentFailed)): ?>
        <div class="m-empty-state">
            <i class="fas fa-shield-alt"></i>
            <p>No failed login attempts</p>
        </div>
    <?php else: ?>
        <?php foreach ($recentFailed as $f): ?>
        <div class="m-security-fail">
            <div class="m-security-fail-icon"><i class="fas fa-times"></i></div>
            <div class="m-security-fail-body">
                <div class="m-security-fail-user"><?= htmlspecialchars($f['username'] ?? 'Unknown') ?></div>
                <div class="m-security-fail-meta">
                    <?= htmlspecialchars($f['ip_address'] ?? '') ?> · <?= mSecTimeAgo($f['login_time']) ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
