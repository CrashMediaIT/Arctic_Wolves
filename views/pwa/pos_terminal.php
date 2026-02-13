<?php
/**
 * PWA POS Terminal - Mobile-native POS redirect with recent transactions
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access required</div>';
    return;
}

$recentPOS = [];
try {
    $stmt = $pdo->prepare("SELECT id, total_amount, payment_method, created_at FROM pos_transactions ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recentPOS = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentPOS = []; }
?>
<style>
.m-pos { padding: 16px; font-family: Inter, sans-serif; }
.m-pos-header { margin-bottom: 16px; }
.m-pos-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-pos-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-pos-notice {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 24px; text-align: center; margin-bottom: 20px;
}
.m-pos-notice-icon { font-size: 32px; color: #8B5CF6; margin-bottom: 12px; }
.m-pos-notice-text { font-size: 14px; color: #A8A8B8; margin-bottom: 16px; }
.m-pos-notice-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; padding: 12px 24px; border-radius: 10px;
    text-decoration: none; font-size: 14px; font-weight: 600;
    min-height: 44px;
}
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-pos-tx {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-pos-tx-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(16,185,129,0.15); color: #10B981;
}
.m-pos-tx-body { flex: 1; min-width: 0; }
.m-pos-tx-id { font-size: 13px; font-weight: 600; color: #fff; }
.m-pos-tx-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-pos-tx-amount { font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-pos">
    <div class="m-pos-header">
        <h2 class="m-pos-title">POS Terminal</h2>
        <p class="m-pos-sub">Point of Sale</p>
    </div>

    <div class="m-pos-notice">
        <div class="m-pos-notice-icon"><i class="fas fa-cash-register"></i></div>
        <div class="m-pos-notice-text">POS Terminal works best on tablet or desktop</div>
        <a href="/pos_kiosk.php" class="m-pos-notice-btn">
            <i class="fas fa-external-link-alt"></i> Open POS Terminal
        </a>
    </div>

    <h3 class="m-section-title">Recent Transactions</h3>
    <?php if (empty($recentPOS)): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            No recent POS transactions
        </div>
    <?php else: ?>
        <?php foreach ($recentPOS as $tx):
            $methodIcon = match(strtolower($tx['payment_method'] ?? '')) {
                'credit_card', 'card', 'stripe' => 'fa-credit-card',
                'cash' => 'fa-money-bill',
                default => 'fa-receipt',
            };
        ?>
        <div class="m-pos-tx">
            <div class="m-pos-tx-icon">
                <i class="fas <?= $methodIcon ?>"></i>
            </div>
            <div class="m-pos-tx-body">
                <div class="m-pos-tx-id">Transaction #<?= (int)$tx['id'] ?></div>
                <div class="m-pos-tx-meta">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['payment_method'] ?? 'N/A'))) ?>
                    · <?= date('M j, g:i A', strtotime($tx['created_at'])) ?>
                </div>
            </div>
            <div class="m-pos-tx-amount">$<?= number_format((float)$tx['total_amount'], 2) ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
