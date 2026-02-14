<?php
/**
 * PWA POS Transactions - Mobile-native POS transaction history
 * Purpose-built for mobile phones.
 */

if (!$canAccessPOS) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">POS access required.</p>';
    echo '</div>';
    return;
}

$dateFilter = $_GET['date'] ?? '';
$paymentFilter = $_GET['payment'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = ["1=1"];
$params = [];

if ($dateFilter) {
    $where[] = "DATE(pt.created_at) = ?";
    $params[] = $dateFilter;
}
if ($paymentFilter) {
    $where[] = "pt.payment_method = ?";
    $params[] = $paymentFilter;
}
if ($statusFilter) {
    $where[] = "pt.status = ?";
    $params[] = $statusFilter;
}

$whereClause = implode(' AND ', $where);

$transactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT pt.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM pos_transaction_items WHERE transaction_id = pt.id) as item_count
        FROM pos_transactions pt
        LEFT JOIN users u ON pt.staff_id = u.id
        WHERE $whereClause
        ORDER BY pt.created_at DESC
        LIMIT 20
    ");
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $transactions = []; }

$totalTx = count($transactions);
$hasFilters = $dateFilter || $paymentFilter || $statusFilter;
?>
<style>
.m-postx { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-postx-header { margin-bottom: 16px; }
.m-postx-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-postx-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-postx-filters {
    display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap; align-items: center;
}
.m-postx-filters input, .m-postx-filters select {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 10px; min-height: 44px; font-size: 13px;
    font-family: Inter, sans-serif; box-sizing: border-box; flex: 1; min-width: 0;
}
.m-postx-filters select { appearance: auto; }
.m-postx-filter-btn {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    min-height: 44px; padding: 0 16px; font-weight: 600; font-size: 13px; cursor: pointer;
    white-space: nowrap;
}
.m-postx-clear-btn {
    background: rgba(239,68,68,0.15); color: #EF4444; border: none; border-radius: 10px;
    min-height: 44px; padding: 0 12px; font-weight: 600; font-size: 12px; cursor: pointer;
    white-space: nowrap; text-decoration: none; display: flex; align-items: center; gap: 4px;
}
.m-postx-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px; cursor: pointer;
}
.m-postx-card-top {
    display: flex; align-items: center; gap: 12px;
}
.m-postx-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(16,185,129,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #10B981; flex-shrink: 0;
}
.m-postx-icon-refunded { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-postx-icon-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-postx-icon-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-postx-info { flex: 1; min-width: 0; }
.m-postx-amount { font-size: 15px; font-weight: 700; color: #fff; }
.m-postx-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; gap: 8px; flex-wrap: wrap; }
.m-postx-right { text-align: right; flex-shrink: 0; }
.m-postx-method {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6; display: inline-block;
}
.m-postx-status {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    display: inline-block; margin-top: 4px;
}
.m-postx-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-postx-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-postx-status-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-postx-status-refunded { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-postx-detail {
    display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid #2D2D3F;
    font-size: 13px; color: #A8A8B8;
}
.m-postx-detail.active { display: block; }
.m-postx-detail-row { display: flex; justify-content: space-between; padding: 4px 0; }
.m-postx-detail-row span:last-child { color: #fff; font-weight: 600; }
.m-postx-detail-items { margin: 8px 0; padding: 8px; background: #0A0A0F; border-radius: 8px; font-size: 12px; }
.m-postx-detail-items div { padding: 3px 0; border-bottom: 1px solid #1a1a2a; }
.m-postx-detail-items div:last-child { border-bottom: none; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-postx">
    <div class="m-postx-header">
        <h2 class="m-postx-title">POS Transactions</h2>
        <p class="m-postx-sub"><?= $totalTx ?> transaction<?= $totalTx !== 1 ? 's' : '' ?></p>
    </div>

    <form method="GET" class="m-postx-filters">
        <input type="hidden" name="page" value="pos_transactions">
        <input type="date" name="date" value="<?= htmlspecialchars($dateFilter) ?>" placeholder="Date">
        <select name="payment">
            <option value="">All Payments</option>
            <option value="card" <?= $paymentFilter === 'card' ? 'selected' : '' ?>>Card</option>
            <option value="cash" <?= $paymentFilter === 'cash' ? 'selected' : '' ?>>Cash</option>
            <option value="mixed" <?= $paymentFilter === 'mixed' ? 'selected' : '' ?>>Mixed</option>
        </select>
        <select name="status">
            <option value="">All Status</option>
            <option value="completed" <?= $statusFilter === 'completed' ? 'selected' : '' ?>>Completed</option>
            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
        </select>
        <button type="submit" class="m-postx-filter-btn"><i class="fas fa-filter"></i> Filter</button>
        <?php if ($hasFilters): ?>
        <a href="?page=pos_transactions" class="m-postx-clear-btn"><i class="fas fa-times"></i> Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($transactions)): ?>
        <div class="m-empty-state">
            <i class="fas fa-cash-register"></i>
            <p>No transactions found</p>
        </div>
    <?php else: ?>
        <?php foreach ($transactions as $tx):
            $txStatus = strtolower($tx['status'] ?? 'completed');
            $txId = (int)$tx['id'];
        ?>
        <div class="m-postx-card" onclick="toggleTxDetail(<?= $txId ?>)">
            <div class="m-postx-card-top">
                <div class="m-postx-icon <?= $txStatus !== 'completed' ? 'm-postx-icon-' . $txStatus : '' ?>"><i class="fas fa-receipt"></i></div>
                <div class="m-postx-info">
                    <div class="m-postx-amount">$<?= number_format((float)($tx['total'] ?? $tx['total_amount'] ?? 0), 2) ?></div>
                    <div class="m-postx-meta">
                        <?php if (!empty($tx['transaction_number'])): ?>
                        <span style="font-family:monospace;font-size:11px;"><?= htmlspecialchars($tx['transaction_number']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($tx['created_at'])): ?>
                        <span><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, g:i A', strtotime($tx['created_at'])) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="m-postx-right">
                    <?php if (!empty($tx['payment_method'])): ?>
                    <span class="m-postx-method"><?= htmlspecialchars(ucfirst($tx['payment_method'])) ?></span>
                    <?php endif; ?>
                    <div class="m-postx-status m-postx-status-<?= $txStatus ?>"><?= ucfirst($txStatus) ?></div>
                </div>
            </div>
            <div class="m-postx-detail" id="mTxDetail-<?= $txId ?>">
                <div class="m-postx-detail-row"><span>Staff</span><span><?= htmlspecialchars(($tx['first_name'] ?? '') . ' ' . ($tx['last_name'] ?? '')) ?></span></div>
                <div class="m-postx-detail-row"><span>Items</span><span><?= (int)($tx['item_count'] ?? 0) ?></span></div>
                <div class="m-postx-detail-row"><span>Status</span><span><?= ucfirst($txStatus) ?></span></div>
                <?php if (!empty($tx['subtotal'])): ?>
                <div class="m-postx-detail-row"><span>Subtotal</span><span>$<?= number_format((float)$tx['subtotal'], 2) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($tx['tax'])): ?>
                <div class="m-postx-detail-row"><span>Tax</span><span>$<?= number_format((float)$tx['tax'], 2) ?></span></div>
                <?php endif; ?>
                <?php if (!empty($tx['discount'])): ?>
                <div class="m-postx-detail-row"><span>Discount</span><span>-$<?= number_format((float)$tx['discount'], 2) ?></span></div>
                <?php endif; ?>
                <div class="m-postx-detail-items" id="mTxItems-<?= $txId ?>">
                    <div style="color:#6B6B7B;text-align:center;">Tap to load items...</div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function toggleTxDetail(txId) {
    var detail = document.getElementById('mTxDetail-' + txId);
    var isActive = detail.classList.contains('active');
    document.querySelectorAll('.m-postx-detail.active').forEach(function(d) { d.classList.remove('active'); });
    if (!isActive) {
        detail.classList.add('active');
        loadTxItems(txId);
    }
}

function loadTxItems(txId) {
    var container = document.getElementById('mTxItems-' + txId);
    if (container.dataset.loaded) return;
    fetch('ajax_get_pos_transaction.php?id=' + txId)
        .then(function(r) { return r.text(); })
        .then(function(html) {
            container.innerHTML = html;
            container.dataset.loaded = '1';
        })
        .catch(function() {
            container.innerHTML = '<div style="color:#EF4444;">Failed to load details</div>';
        });
}
</script>
