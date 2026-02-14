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
    $stmt = $pdo->prepare("SELECT id, order_number, customer_name, customer_email, total_amount, status, shipping_address, tracking_number, created_at FROM shop_orders ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT id, order_number, customer_name, total_amount, status, created_at FROM online_orders ORDER BY created_at DESC LIMIT 20");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $orders = []; }
}
?>
<style>
.m-orders { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-orders-header { margin-bottom: 16px; }
.m-orders-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-orders-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-order-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-order-card-top {
    display: flex; align-items: center; gap: 12px;
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
.m-order-actions {
    display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F;
    align-items: center;
}
.m-order-actions select {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 8px 10px; min-height: 36px; font-size: 12px;
    font-family: Inter, sans-serif; box-sizing: border-box; flex: 1;
    appearance: auto;
}
.m-order-actions button {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    min-height: 36px; padding: 0 14px; font-weight: 600; font-size: 12px;
    cursor: pointer; white-space: nowrap;
}
.m-order-detail {
    display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F;
    font-size: 12px; color: #A8A8B8;
}
.m-order-detail.active { display: block; }
.m-order-detail-row { display: flex; justify-content: space-between; padding: 3px 0; }
.m-order-detail-row span:last-child { color: #fff; }
.m-order-expand {
    background: none; border: none; color: #6B6B7B; font-size: 11px;
    cursor: pointer; padding: 4px 0; display: flex; align-items: center; gap: 4px;
}
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
                'new', 'placed', 'pending' => 'new',
                'processing', 'preparing', 'paid' => 'processing',
                'completed', 'delivered', 'shipped' => 'completed',
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
            $orderId = (int)$order['id'];
        ?>
        <div class="m-order-card">
            <div class="m-order-card-top">
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

            <button class="m-order-expand" onclick="toggleOnlineOrderDetail(<?= $orderId ?>)">
                <i class="fas fa-chevron-down" id="mOOIcon-<?= $orderId ?>"></i> View details
            </button>

            <div class="m-order-detail" id="mOODetail-<?= $orderId ?>">
                <?php if (!empty($order['customer_email'])): ?>
                <div class="m-order-detail-row"><span>Email</span><span><?= htmlspecialchars($order['customer_email']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($order['shipping_address'])): ?>
                <div class="m-order-detail-row"><span>Shipping</span><span><?= htmlspecialchars($order['shipping_address']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($order['tracking_number'])): ?>
                <div class="m-order-detail-row"><span>Tracking</span><span><?= htmlspecialchars($order['tracking_number']) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="m-order-actions">
                <select id="mOOStatus-<?= $orderId ?>">
                    <?php foreach (['pending','paid','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button onclick="updateOnlineOrderStatus(<?= $orderId ?>)"><i class="fas fa-save"></i> Update</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
var mOOCsrf = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';

function toggleOnlineOrderDetail(id) {
    var el = document.getElementById('mOODetail-' + id);
    var icon = document.getElementById('mOOIcon-' + id);
    el.classList.toggle('active');
    icon.style.transform = el.classList.contains('active') ? 'rotate(180deg)' : '';
}

function updateOnlineOrderStatus(orderId) {
    var newStatus = document.getElementById('mOOStatus-' + orderId).value;
    fetch('dashboard.php?page=pos_online_orders', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'update_status=1&order_id=' + orderId + '&new_status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(mOOCsrf)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { location.reload(); }
        else { alert('Failed: ' + (data.message || 'Unknown error')); }
    })
    .catch(function() { alert('Request failed'); });
}
</script>
