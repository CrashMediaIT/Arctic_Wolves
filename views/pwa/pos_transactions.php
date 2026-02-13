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

$transactions = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, total_amount, payment_method, created_at
        FROM pos_transactions
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $transactions = []; }

$totalTx = count($transactions);
?>
<style>
.m-postx { padding: 16px; font-family: Inter, sans-serif; }
.m-postx-header { margin-bottom: 16px; }
.m-postx-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-postx-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-postx-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-postx-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(16,185,129,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #10B981; flex-shrink: 0;
}
.m-postx-info { flex: 1; min-width: 0; }
.m-postx-amount { font-size: 15px; font-weight: 700; color: #fff; }
.m-postx-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; gap: 8px; }
.m-postx-method {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6;
    flex-shrink: 0; align-self: center;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-postx">
    <div class="m-postx-header">
        <h2 class="m-postx-title">POS Transactions</h2>
        <p class="m-postx-sub"><?= $totalTx ?> transaction<?= $totalTx !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($transactions)): ?>
        <div class="m-empty-state">
            <i class="fas fa-cash-register"></i>
            <p>No transactions recorded</p>
        </div>
    <?php else: ?>
        <?php foreach ($transactions as $tx): ?>
        <div class="m-postx-card">
            <div class="m-postx-icon"><i class="fas fa-receipt"></i></div>
            <div class="m-postx-info">
                <div class="m-postx-amount">$<?= number_format((float)($tx['total_amount'] ?? 0), 2) ?></div>
                <div class="m-postx-meta">
                    <?php if (!empty($tx['created_at'])): ?>
                    <span><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, g:i A', strtotime($tx['created_at'])) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($tx['payment_method'])): ?>
            <span class="m-postx-method"><?= htmlspecialchars(ucfirst($tx['payment_method'])) ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
