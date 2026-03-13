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
        SELECT so.id, so.order_number, so.total_amount, so.status, so.created_at,
               so.shipping_carrier, so.tracking_number, so.tracking_url, so.shipped_at,
               so.customer_name, so.customer_email
        FROM shop_orders so
        ORDER BY so.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        $stmt = $pdo->prepare("SELECT id, order_number, total_amount, status, created_at FROM orders ORDER BY created_at DESC LIMIT 20");
        $stmt->execute();
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) { $orders = []; }
}

$totalOrders = count($orders);
?>
<style>
.m-shoporders { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
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
.m-order-badge-shipped { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-order-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-order-expand {
    background: none; border: none; color: #6B6B7B; font-size: 11px;
    cursor: pointer; padding: 6px 0 0; display: flex; align-items: center; gap: 4px;
}
.m-order-detail {
    display: none; margin-top: 8px; padding-top: 8px; border-top: 1px solid #2D2D3F;
    font-size: 12px; color: #A8A8B8;
}
.m-order-detail.active { display: block; }
.m-order-detail-row { display: flex; justify-content: space-between; padding: 3px 0; }
.m-order-detail-row span:last-child { color: #fff; }
.m-order-detail-row a { color: #8B5CF6; text-decoration: none; }
.m-order-actions {
    display: flex; gap: 8px; margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F;
    align-items: center; flex-wrap: wrap;
}
.m-order-actions select {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 8px 10px; min-height: 36px; font-size: 12px;
    font-family: Inter, sans-serif; box-sizing: border-box; flex: 1; min-width: 0;
    appearance: auto;
}
.m-order-btn-update {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    min-height: 36px; padding: 0 14px; font-weight: 600; font-size: 12px;
    cursor: pointer; white-space: nowrap;
}
.m-order-btn-ship {
    background: rgba(59,130,246,0.15); color: #3B82F6; border: none; border-radius: 10px;
    min-height: 36px; padding: 0 14px; font-weight: 600; font-size: 12px;
    cursor: pointer; white-space: nowrap;
}
.m-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000;
    display: none;
}
.m-overlay.active { display: block; }
.m-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0;
    max-height: 85vh; overflow-y: auto; padding: 20px 16px 32px;
    transform: translateY(100%); transition: transform 0.3s ease;
}
.m-sheet.active { transform: translateY(0); }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-sheet-close {
    position: absolute; top: 16px; right: 16px; background: none; border: none;
    color: #A8A8B8; font-size: 18px; cursor: pointer;
}
.m-field { margin-bottom: 14px; }
.m-field label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; }
.m-field input, .m-field select, .m-field textarea {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; width: 100%;
    box-sizing: border-box; font-size: 14px; font-family: Inter, sans-serif;
}
.m-field select { appearance: auto; }
.m-field textarea { min-height: 60px; resize: vertical; }
.m-submit {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    min-height: 44px; font-weight: 600; width: 100%; font-size: 15px;
    cursor: pointer; margin-top: 8px;
}
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
                'completed', 'fulfilled', 'delivered' => 'completed',
                'pending', 'processing', 'paid' => 'pending',
                'shipped' => 'shipped',
                'cancelled', 'refunded' => 'cancelled',
                default => 'default',
            };
            $oId = (int)$o['id'];
            $canShip = in_array($status, ['paid', 'processing']);
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

            <button class="m-order-expand" onclick="toggleShopOrderDetail(<?= $oId ?>)">
                <i class="fas fa-chevron-down" id="mSOIcon-<?= $oId ?>"></i> View details
            </button>

            <div class="m-order-detail" id="mSODetail-<?= $oId ?>">
                <?php if (!empty($o['customer_name'])): ?>
                <div class="m-order-detail-row"><span>Customer</span><span><?= htmlspecialchars($o['customer_name']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($o['customer_email'])): ?>
                <div class="m-order-detail-row"><span>Email</span><span><?= htmlspecialchars($o['customer_email']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($o['shipping_carrier'])): ?>
                <div class="m-order-detail-row"><span>Carrier</span><span><?= htmlspecialchars($o['shipping_carrier']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($o['tracking_number'])): ?>
                <div class="m-order-detail-row"><span>Tracking #</span><span><?= htmlspecialchars($o['tracking_number']) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($o['tracking_url'])): ?>
                <div class="m-order-detail-row"><span>Tracking</span><span><a href="<?= htmlspecialchars($o['tracking_url']) ?>" target="_blank">View <i class="fas fa-external-link-alt" style="font-size:10px;"></i></a></span></div>
                <?php endif; ?>
                <?php if (!empty($o['shipped_at'])): ?>
                <div class="m-order-detail-row"><span>Shipped</span><span><?= date('M j, Y g:i A', strtotime($o['shipped_at'])) ?></span></div>
                <?php endif; ?>
            </div>

            <div class="m-order-actions">
                <select id="mSOStatus-<?= $oId ?>">
                    <?php foreach (['pending','paid','processing','shipped','delivered','cancelled','refunded'] as $s): ?>
                    <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="m-order-btn-update" onclick="updateShopOrderStatus(<?= $oId ?>)"><i class="fas fa-save"></i> Update</button>
                <?php if ($canShip): ?>
                <button class="m-order-btn-ship" onclick="openShipSheet(<?= $oId ?>)"><i class="fas fa-truck"></i> Ship</button>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Overlay -->
<div class="m-overlay" id="mSOOverlay" onclick="closeShipSheet()"></div>

<!-- Ship Order Sheet -->
<div class="m-sheet" id="mShipSheet">
    <button class="m-sheet-close" onclick="closeShipSheet()"><i class="fas fa-times"></i></button>
    <h3 class="m-sheet-title">Ship Order</h3>
    <form id="mShipForm" onsubmit="submitShipOrder(event)">
        <input type="hidden" name="order_id" id="mShipOrderId">
        <div class="m-field">
            <label>Shipping Carrier *</label>
            <select name="shipping_carrier" required>
                <option value="">Select Carrier</option>
                <option value="Stallion Express (Multi-Carrier)">Stallion Express (Multi-Carrier)</option>
                <option value="Canada Post">Canada Post</option>
                <option value="UPS">UPS</option>
                <option value="FedEx">FedEx</option>
                <option value="Purolator">Purolator</option>
                <option value="DHL">DHL</option>
                <option value="Pickup at Session">Pickup at Session</option>
                <option value="Local Pickup">Local Pickup</option>
                <option value="Other">Other</option>
            </select>
        </div>
        <div class="m-field"><label>Tracking Number</label><input type="text" name="tracking_number"></div>
        <div class="m-field"><label>Tracking URL</label><input type="url" name="tracking_url"></div>
        <div class="m-field"><label>Fulfillment Notes</label><textarea name="fulfillment_notes" rows="2"></textarea></div>
        <button type="submit" class="m-submit">Mark as Shipped</button>
    </form>
</div>

<script>
var mSOCsrf = '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES) ?>';

function toggleShopOrderDetail(id) {
    var el = document.getElementById('mSODetail-' + id);
    var icon = document.getElementById('mSOIcon-' + id);
    el.classList.toggle('active');
    icon.style.transform = el.classList.contains('active') ? 'rotate(180deg)' : '';
}

function updateShopOrderStatus(orderId) {
    var newStatus = document.getElementById('mSOStatus-' + orderId).value;
    fetch('pwa.php?page=shop_orders', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'update_status=1&order_id=' + orderId + '&new_status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(mSOCsrf)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
        else { showToast('Failed: ' + (data.message || 'Unknown error'), 'error'); }
    })
    .catch(function() { showToast('Request failed', 'error'); });
}

function openShipSheet(orderId) {
    document.getElementById('mShipOrderId').value = orderId;
    document.getElementById('mSOOverlay').classList.add('active');
    document.getElementById('mShipSheet').classList.add('active');
}

function closeShipSheet() {
    document.getElementById('mSOOverlay').classList.remove('active');
    document.getElementById('mShipSheet').classList.remove('active');
}

function submitShipOrder(e) {
    e.preventDefault();
    var form = document.getElementById('mShipForm');
    var fd = new FormData(form);
    fd.append('csrf_token', mSOCsrf);
    fetch('process_shop_checkout.php?action=ship_order', {
        method: 'POST',
        body: fd
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
        else { showToast('Failed: ' + (data.message || 'Unknown error'), 'error'); }
    })
    .catch(function() { showToast('Request failed', 'error'); });
}
</script>
