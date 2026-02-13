<?php
/**
 * PWA Shop Orders - Mobile-native order management
 * Purpose-built for mobile phones.
 */

if (!$canAccessPOS && !$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Access restricted.</p>';
    echo '</div>';
    return;
}

$orders = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, order_number, total_amount, status, created_at
        FROM orders
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $orders = []; }

$totalOrders = count($orders);
?>
<style>
.m-shoporders { padding: 16px; font-family: Inter, sans-serif; }
.m-shoporders-header { margin-bottom: 16px; }
.m-shoporders-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-shoporders-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-order-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-order-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-order-num { font-size: 14px; font-weight: 600; color: #fff; }
.m-order-amount { font-size: 15px; font-weight: 700; color: #10B981; flex-shrink: 0; }
.m-order-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-order-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-order-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-order-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-order-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-order-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-order-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-shoporders">
    <div class="m-shoporders-header">
        <h2 class="m-shoporders-title">Shop Orders</h2>
        <p class="m-shoporders-sub"><?= $totalOrders ?> order<?= $totalOrders !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($orders)): ?>
        <div class="m-empty-state">
            <i class="fas fa-shopping-bag"></i>
            <p>No orders yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($orders as $o):
            $status = strtolower($o['status'] ?? 'pending');
            $badgeClass = match($status) {
                'completed', 'fulfilled' => 'completed',
                'pending', 'processing' => 'pending',
                'cancelled', 'refunded' => 'cancelled',
                default => 'default',
            };
        ?>
        <div class="m-order-card">
            <div class="m-order-top">
                <span class="m-order-num">#<?= htmlspecialchars($o['order_number'] ?? $o['id']) ?></span>
                <span class="m-order-amount">$<?= number_format((float)($o['total_amount'] ?? 0), 2) ?></span>
            </div>
            <div class="m-order-bottom">
                <div class="m-order-date">
                    <?php if (!empty($o['created_at'])): ?>
                    <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($o['created_at'])) ?>
                    <?php endif; ?>
                </div>
                <span class="m-order-badge m-order-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
