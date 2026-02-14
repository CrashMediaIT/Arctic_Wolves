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
    min-height: 44px; cursor: pointer; transition: border-color 0.2s;
}
.m-payment-card:active { border-color: #6B46C1; }
.m-payment-detail { display:none; background:#0A0A0F; border:1px solid #2D2D3F; border-radius:10px; padding:12px; margin:-4px 0 8px; font-size:13px; }
.m-payment-detail-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #1A1A2F; color:#A8A8B8; }
.m-payment-detail-row:last-child { border-bottom:none; }
.m-payment-detail-row span:last-child { color:#fff; font-weight:600; }
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

    <!-- Date Filter -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
        <div>
            <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">From</label>
            <input type="date" id="mPayDateFrom" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;">
        </div>
        <div>
            <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">To</label>
            <input type="date" id="mPayDateTo" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
    <button onclick="mPayFilter()" style="width:100%;background:#6B46C1;color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:600;min-height:44px;cursor:pointer;margin-bottom:16px;">
        <i class="fas fa-filter"></i> Filter Payments
    </button>

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
        <div class="m-payment-card" onclick="mPayToggle(this)" data-date="<?= date('Y-m-d', strtotime($p['created_at'])) ?>">
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
        <div class="m-payment-detail" id="mPayDetail<?= $p['id'] ?>">
            <div class="m-payment-detail-row"><span>Transaction ID</span><span>#<?= htmlspecialchars($p['id']) ?></span></div>
            <div class="m-payment-detail-row"><span>Date</span><span><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></span></div>
            <div class="m-payment-detail-row"><span>Method</span><span><?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['payment_method'] ?? 'N/A'))) ?></span></div>
            <div class="m-payment-detail-row"><span>Amount</span><span>$<?= number_format((float)$p['amount'], 2) ?></span></div>
            <div class="m-payment-detail-row"><span>Status</span><span><?= htmlspecialchars(ucfirst($status)) ?></span></div>
            <?php if ($statusClass === 'completed'): ?>
            <button onclick="event.stopPropagation();mPayReceipt(<?= (int)$p['id'] ?>)" style="width:100%;margin-top:10px;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:10px;font-size:13px;font-weight:600;min-height:44px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-download" style="color:#8B5CF6;"></i> Download Receipt
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function mPayToggle(card) {
    var detail = card.nextElementSibling;
    if (detail && detail.classList.contains('m-payment-detail')) {
        detail.style.display = detail.style.display === 'block' ? 'none' : 'block';
    }
}
function mPayFilter() {
    var from = document.getElementById('mPayDateFrom').value;
    var to = document.getElementById('mPayDateTo').value;
    var cards = document.querySelectorAll('.m-payment-card');
    cards.forEach(function(c) {
        var d = c.getAttribute('data-date');
        var detail = c.nextElementSibling;
        var show = true;
        if (from && d < from) show = false;
        if (to && d > to) show = false;
        c.style.display = show ? 'flex' : 'none';
        if (detail && detail.classList.contains('m-payment-detail')) {
            detail.style.display = 'none';
        }
    });
}
function mPayReceipt(id) {
    window.open('payment_success.php?payment_id=' + id + '&receipt=1', '_blank');
}
</script>
