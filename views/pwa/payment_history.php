<?php
/**
 * PWA Payment History - Mobile-native payment list
 * Purpose-built for mobile phones.
 */

$payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.payment_method, p.status, p.created_at, p.description
        FROM payments p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id]);
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $payments = []; }

$totalPaid = 0;
foreach ($payments as $p) {
    if (($p['status'] ?? '') === 'completed') {
        $totalPaid += (float)($p['amount'] ?? 0);
    }
}
?>
<style>
.m-payments { padding: 16px; font-family: Inter, sans-serif; }
.m-payments-header { margin-bottom: 16px; }
.m-payments-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-payments-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-payment-summary {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 16px;
    text-align: center;
}
.m-payment-summary-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.m-payment-summary-value { font-size: 28px; font-weight: 700; color: #fff; margin-top: 4px; }
.m-payment-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    min-height: 44px;
}
.m-payment-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-payment-icon-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-payment-icon-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-payment-icon-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-payment-icon-refunded { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-payment-icon-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-payment-body { flex: 1; min-width: 0; }
.m-payment-desc { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-payment-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-payment-right { text-align: right; flex-shrink: 0; }
.m-payment-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-payment-status {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    margin-top: 3px; display: inline-block;
}
.m-payment-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-payment-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-payment-status-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-payment-status-refunded { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-payment-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-payments">
    <div class="m-payments-header">
        <h2 class="m-payments-title">Payment History</h2>
        <p class="m-payments-sub"><?= count($payments) ?> transaction<?= count($payments) !== 1 ? 's' : '' ?></p>
    </div>

    <div class="m-payment-summary">
        <div class="m-payment-summary-label">Total Paid</div>
        <div class="m-payment-summary-value">$<?= number_format($totalPaid, 2) ?></div>
    </div>

    <?php if (empty($payments)): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            <p>No payment history</p>
        </div>
    <?php else: ?>
        <?php foreach ($payments as $p):
            $status = strtolower($p['status'] ?? 'default');
            $statusClass = match($status) {
                'completed', 'paid', 'succeeded' => 'completed',
                'pending', 'processing' => 'pending',
                'failed', 'declined' => 'failed',
                'refunded' => 'refunded',
                default => 'default',
            };
            $methodIcon = match(strtolower($p['payment_method'] ?? '')) {
                'credit_card', 'card', 'stripe' => 'fa-credit-card',
                'cash' => 'fa-money-bill',
                'bank', 'transfer' => 'fa-building-columns',
                'paypal' => 'fa-paypal',
                default => 'fa-receipt',
            };
        ?>
        <div class="m-payment-card">
            <div class="m-payment-icon m-payment-icon-<?= $statusClass ?>">
                <i class="fas <?= $methodIcon ?>"></i>
            </div>
            <div class="m-payment-body">
                <div class="m-payment-desc"><?= htmlspecialchars($p['description'] ?: 'Payment') ?></div>
                <div class="m-payment-meta">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['payment_method'] ?? 'N/A'))) ?>
                    · <?= date('M j, Y', strtotime($p['created_at'])) ?>
                </div>
            </div>
            <div class="m-payment-right">
                <div class="m-payment-amount">$<?= number_format((float)$p['amount'], 2) ?></div>
                <span class="m-payment-status m-payment-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
