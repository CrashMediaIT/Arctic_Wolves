<?php
/**
 * PWA Accounting Credits - Mobile-native credits & refunds list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$credits = [];
try {
    $stmt = $pdo->prepare("
        SELECT uc.id, uc.amount, uc.credit_type, uc.status, uc.created_at, uc.description,
               u.first_name, u.last_name
        FROM user_credits uc
        LEFT JOIN users u ON u.id = uc.user_id
        ORDER BY uc.created_at DESC LIMIT 30
    ");
    $stmt->execute();
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $credits = []; }
?>
<style>
.m-credits { padding: 16px; font-family: Inter, sans-serif; }
.m-credits-header { margin-bottom: 16px; }
.m-credits-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-credits-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-credit-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-credit-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(139,92,246,0.15); color: #8B5CF6;
}
.m-credit-body { flex: 1; min-width: 0; }
.m-credit-user { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-credit-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-credit-right { text-align: right; flex-shrink: 0; }
.m-credit-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-credit-meta { display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap; justify-content: flex-end; }
.m-credit-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-credit-type-credit { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-credit-type-refund { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-credit-type-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-credit-status-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-credit-status-used { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-credit-status-expired { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-credit-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-credit-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-credit-date { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-credits">
    <div class="m-credits-header">
        <h2 class="m-credits-title">Credits &amp; Refunds</h2>
        <p class="m-credits-sub"><?= count($credits) ?> record<?= count($credits) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($credits)): ?>
        <div class="m-empty-state">
            <i class="fas fa-hand-holding-dollar"></i>
            No credits or refunds found
        </div>
    <?php else: ?>
        <?php foreach ($credits as $c):
            $type = strtolower($c['credit_type'] ?? 'default');
            $typeClass = match($type) {
                'credit' => 'credit',
                'refund' => 'refund',
                default => 'default',
            };
            $status = strtolower($c['status'] ?? 'default');
            $statusClass = match($status) {
                'active' => 'active',
                'used', 'redeemed' => 'used',
                'expired' => 'expired',
                'pending' => 'pending',
                default => 'default',
            };
            $userName = htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Unknown User');
        ?>
        <div class="m-credit-card">
            <div class="m-credit-icon">
                <i class="fas <?= $type === 'refund' ? 'fa-rotate-left' : 'fa-coins' ?>"></i>
            </div>
            <div class="m-credit-body">
                <div class="m-credit-user"><?= $userName ?></div>
                <div class="m-credit-desc"><?= htmlspecialchars($c['description'] ?: 'No description') ?></div>
                <div class="m-credit-date"><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, Y', strtotime($c['created_at'])) ?></div>
            </div>
            <div class="m-credit-right">
                <div class="m-credit-amount">$<?= number_format((float)$c['amount'], 2) ?></div>
                <div class="m-credit-meta">
                    <span class="m-credit-badge m-credit-type-<?= $typeClass ?>"><?= htmlspecialchars(ucfirst($type)) ?></span>
                    <span class="m-credit-badge m-credit-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
