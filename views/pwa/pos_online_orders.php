<?php
/**
 * PWA POS Online Orders - Mobile-native order management
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access required</div>';
    return;
}

$orders = [];
try {
    $stmt = $pdo->prepare("SELECT id, order_number, customer_name, total_amount, status, created_at FROM online_orders ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $orders = []; }
?>
<style>
.m-orders { padding: 16px; font-family: Inter, sans-serif; }
.m-orders-header { margin-bottom: 16px; }
.m-orders-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-orders-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-order-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-order-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-order-icon-new { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-order-icon-processing { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-order-icon-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-order-icon-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-order-icon-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-order-body { flex: 1; min-width: 0; }
.m-order-number { font-size: 13px; font-weight: 600; color: #fff; }
.m-order-customer { font-size: 12px; color: #A8A8B8; margin-top: 1px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-order-right { text-align: right; flex-shrink: 0; }
.m-order-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-order-status {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    margin-top: 3px; display: inline-block;
}
.m-order-status-new { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-order-status-processing { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-order-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-order-status-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-order-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-order-date { font-size: 11px; color: #6B6B7B; margin-top: 2px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-orders">
    <div class="m-orders-header">
        <h2 class="m-orders-title">Online Orders</h2>
        <p class="m-orders-sub"><?= count($orders) ?> order<?= count($orders) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($orders)): ?>
        <div class="m-empty-state">
            <i class="fas fa-shopping-bag"></i>
            No online orders found
        </div>
    <?php else: ?>
        <?php foreach ($orders as $order):
            $status = strtolower($order['status'] ?? 'default');
            $statusClass = match($status) {
                'new', 'placed' => 'new',
                'processing', 'preparing' => 'processing',
                'completed', 'delivered' => 'completed',
                'cancelled', 'refunded' => 'cancelled',
                default => 'default',
            };
            $statusIcon = match($statusClass) {
                'new' => 'fa-circle-dot',
                'processing' => 'fa-spinner',
                'completed' => 'fa-check-circle',
                'cancelled' => 'fa-times-circle',
                default => 'fa-shopping-bag',
            };
        ?>
        <div class="m-order-card">
            <div class="m-order-icon m-order-icon-<?= $statusClass ?>">
                <i class="fas <?= $statusIcon ?>"></i>
            </div>
            <div class="m-order-body">
                <div class="m-order-number">#<?= htmlspecialchars($order['order_number'] ?? $order['id']) ?></div>
                <div class="m-order-customer"><?= htmlspecialchars($order['customer_name'] ?: 'Guest') ?></div>
                <div class="m-order-date"><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, g:i A', strtotime($order['created_at'])) ?></div>
            </div>
            <div class="m-order-right">
                <div class="m-order-amount">$<?= number_format((float)$order['total_amount'], 2) ?></div>
                <span class="m-order-status m-order-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
