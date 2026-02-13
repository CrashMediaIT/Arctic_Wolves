<?php
/**
 * PWA Refunds - Mobile-native refunds list
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$refunds = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.amount, r.status, r.reason, r.created_at,
               u.first_name, u.last_name
        FROM refunds r
        LEFT JOIN users u ON u.id = r.user_id
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $refunds = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $refunds = []; }

$totalRefunds = count($refunds);
?>
<style>
.m-refunds { padding: 16px; font-family: Inter, sans-serif; }
.m-refunds-header { margin-bottom: 16px; }
.m-refunds-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-refunds-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-refund-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-refund-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-refund-user { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-refund-amount { font-size: 15px; font-weight: 700; color: #EF4444; flex-shrink: 0; }
.m-refund-reason { font-size: 12px; color: #A8A8B8; margin: 4px 0 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-refund-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-refund-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-refund-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-refund-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-refund-badge-approved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-refund-badge-rejected { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-refund-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-refunds">
    <div class="m-refunds-header">
        <h2 class="m-refunds-title">Refunds</h2>
        <p class="m-refunds-sub"><?= $totalRefunds ?> refund<?= $totalRefunds !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($refunds)): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            <p>No refund records</p>
        </div>
    <?php else: ?>
        <?php foreach ($refunds as $r):
            $status = strtolower($r['status'] ?? 'pending');
            $badgeClass = match($status) {
                'approved', 'completed' => 'approved',
                'rejected', 'denied' => 'rejected',
                'pending' => 'pending',
                default => 'default',
            };
            $userName = htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Unknown');
        ?>
        <div class="m-refund-card">
            <div class="m-refund-top">
                <span class="m-refund-user"><?= $userName ?></span>
                <span class="m-refund-amount">$<?= number_format((float)($r['amount'] ?? 0), 2) ?></span>
            </div>
            <?php if (!empty($r['reason'])): ?>
            <div class="m-refund-reason"><?= htmlspecialchars($r['reason']) ?></div>
            <?php endif; ?>
            <div class="m-refund-bottom">
                <div class="m-refund-date">
                    <?php if (!empty($r['created_at'])): ?>
                    <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($r['created_at'])) ?>
                    <?php endif; ?>
                </div>
                <span class="m-refund-badge m-refund-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
