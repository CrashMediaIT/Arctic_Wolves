<?php
/**
 * PWA Scheduled Reports - Mobile-native scheduled reports list
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$schedules = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, frequency, next_run, status
        FROM report_schedules
        ORDER BY next_run ASC
        LIMIT 20
    ");
    $stmt->execute();
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $schedules = []; }

$totalSchedules = count($schedules);
?>
<style>
.m-schedrpt { padding: 16px; font-family: Inter, sans-serif; }
.m-schedrpt-header { margin-bottom: 16px; }
.m-schedrpt-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-schedrpt-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-schedrpt-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-schedrpt-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-schedrpt-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-schedrpt-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-schedrpt-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-schedrpt-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-schedrpt-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-schedrpt-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 12px; flex-wrap: wrap; }
.m-schedrpt-meta i { font-size: 10px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-schedrpt">
    <div class="m-schedrpt-header">
        <h2 class="m-schedrpt-title">Scheduled Reports</h2>
        <p class="m-schedrpt-sub"><?= $totalSchedules ?> schedule<?= $totalSchedules !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($schedules)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            <p>No scheduled reports configured</p>
        </div>
    <?php else: ?>
        <?php foreach ($schedules as $s):
            $status = strtolower($s['status'] ?? 'active');
            $badgeClass = match($status) {
                'active' => 'active',
                'paused', 'inactive' => 'paused',
                default => 'default',
            };
        ?>
        <div class="m-schedrpt-card">
            <div class="m-schedrpt-top">
                <span class="m-schedrpt-name"><?= htmlspecialchars($s['name'] ?? 'Unnamed') ?></span>
                <span class="m-schedrpt-badge m-schedrpt-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-schedrpt-meta">
                <?php if (!empty($s['frequency'])): ?>
                <span><i class="fas fa-sync"></i> <?= htmlspecialchars(ucfirst($s['frequency'])) ?></span>
                <?php endif; ?>
                <?php if (!empty($s['next_run'])): ?>
                <span><i class="fas fa-calendar"></i> Next: <?= date('M j, Y', strtotime($s['next_run'])) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
