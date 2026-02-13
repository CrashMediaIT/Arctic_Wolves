<?php
/**
 * PWA Cron Jobs - Mobile-native cron jobs list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$jobs = [];
try {
    $stmt = $pdo->prepare("SELECT id, job_name, schedule, last_run, next_run, status FROM cron_jobs ORDER BY next_run ASC LIMIT 20");
    $stmt->execute();
    $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $jobs = []; }
?>
<style>
.m-cron { padding: 16px; font-family: Inter, sans-serif; }
.m-cron-header { margin-bottom: 16px; }
.m-cron-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-cron-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cron-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-cron-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-cron-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-cron-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; flex-shrink: 0; margin-left: 8px; }
.m-cron-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cron-error { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-cron-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-cron-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-cron-meta { display: flex; gap: 12px; flex-wrap: wrap; }
.m-cron-detail { font-size: 11px; color: #6B6B7B; display: inline-flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-cron">
    <div class="m-cron-header">
        <h2 class="m-cron-title">Cron Jobs</h2>
        <p class="m-cron-sub"><?= count($jobs) ?> job<?= count($jobs) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($jobs)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clock"></i>
            <p>No cron jobs found</p>
        </div>
    <?php else: ?>
        <?php foreach ($jobs as $j):
            $status = strtolower($j['status'] ?? 'unknown');
            $badgeClass = match($status) {
                'active', 'success', 'completed' => 'active',
                'error', 'failed' => 'error',
                'pending', 'queued' => 'pending',
                default => 'default',
            };
        ?>
        <div class="m-cron-card">
            <div class="m-cron-top">
                <div class="m-cron-name"><?= htmlspecialchars($j['job_name'] ?? '') ?></div>
                <span class="m-cron-badge m-cron-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-cron-meta">
                <?php if (!empty($j['schedule'])): ?>
                <span class="m-cron-detail"><i class="fas fa-redo"></i> <?= htmlspecialchars($j['schedule']) ?></span>
                <?php endif; ?>
                <?php if (!empty($j['last_run'])): ?>
                <span class="m-cron-detail"><i class="fas fa-history"></i> Last: <?= htmlspecialchars(date('M j, g:ia', strtotime($j['last_run']))) ?></span>
                <?php endif; ?>
                <?php if (!empty($j['next_run'])): ?>
                <span class="m-cron-detail"><i class="fas fa-forward"></i> Next: <?= htmlspecialchars(date('M j, g:ia', strtotime($j['next_run']))) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
