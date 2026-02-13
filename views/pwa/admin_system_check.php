<?php
/**
 * PWA Admin System Check - Mobile-native system health check
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$phpVersion = phpversion();
$dbOk = false;
try {
    $stmt = $pdo->prepare("SELECT 1");
    $stmt->execute();
    $dbOk = true;
} catch (PDOException $e) { $dbOk = false; }

$diskFree = @disk_free_space('/');
$diskTotal = @disk_total_space('/');
$diskPct = ($diskTotal > 0) ? round(($diskFree / $diskTotal) * 100) : 0;
$diskFreeGB = round($diskFree / 1073741824, 1);

$checks = [
    ['label' => 'PHP Version', 'value' => $phpVersion, 'ok' => version_compare($phpVersion, '8.0', '>='), 'icon' => 'fa-code'],
    ['label' => 'Database', 'value' => $dbOk ? 'Connected' : 'Error', 'ok' => $dbOk, 'icon' => 'fa-database'],
    ['label' => 'Disk Space', 'value' => $diskFreeGB . ' GB free (' . $diskPct . '%)', 'ok' => $diskPct > 10, 'icon' => 'fa-hdd'],
    ['label' => 'Memory Limit', 'value' => ini_get('memory_limit'), 'ok' => true, 'icon' => 'fa-memory'],
    ['label' => 'Max Upload', 'value' => ini_get('upload_max_filesize'), 'ok' => true, 'icon' => 'fa-file-upload'],
    ['label' => 'Timezone', 'value' => date_default_timezone_get(), 'ok' => true, 'icon' => 'fa-globe'],
];
?>
<style>
.m-syscheck { padding: 16px; font-family: Inter, sans-serif; }
.m-syscheck-header { margin-bottom: 16px; }
.m-syscheck-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-syscheck-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-syscheck-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-syscheck-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-syscheck-ok { background: rgba(16,185,129,0.15); color: #10B981; }
.m-syscheck-err { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-syscheck-body { flex: 1; min-width: 0; }
.m-syscheck-label { font-size: 13px; color: #A8A8B8; }
.m-syscheck-value { font-size: 14px; font-weight: 600; color: #fff; margin-top: 1px; }
.m-syscheck-status { flex-shrink: 0; font-size: 14px; }
</style>

<div class="m-syscheck">
    <div class="m-syscheck-header">
        <h2 class="m-syscheck-title">System Check</h2>
        <p class="m-syscheck-sub">Health & diagnostics</p>
    </div>

    <?php foreach ($checks as $c): ?>
    <div class="m-syscheck-card">
        <div class="m-syscheck-icon <?= $c['ok'] ? 'm-syscheck-ok' : 'm-syscheck-err' ?>">
            <i class="fas <?= $c['icon'] ?>"></i>
        </div>
        <div class="m-syscheck-body">
            <div class="m-syscheck-label"><?= htmlspecialchars($c['label']) ?></div>
            <div class="m-syscheck-value"><?= htmlspecialchars($c['value']) ?></div>
        </div>
        <div class="m-syscheck-status" style="color:<?= $c['ok'] ? '#10B981' : '#EF4444' ?>;">
            <i class="fas <?= $c['ok'] ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
        </div>
    </div>
    <?php endforeach; ?>
</div>
