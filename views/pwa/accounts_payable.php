<?php
/**
 * PWA Accounts Payable - Mobile-native AP management
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$payables = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, vendor_name, amount, due_date, status
        FROM accounts_payable
        ORDER BY due_date ASC
        LIMIT 20
    ");
    $stmt->execute();
    $payables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $payables = []; }

$totalPayables = count($payables);
?>
<style>
.m-ap { padding: 16px; font-family: Inter, sans-serif; }
.m-ap-header { margin-bottom: 16px; }
.m-ap-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ap-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ap-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-ap-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-ap-vendor { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-ap-amount { font-size: 15px; font-weight: 700; color: #EF4444; flex-shrink: 0; }
.m-ap-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-ap-due { font-size: 12px; color: #A8A8B8; display: flex; align-items: center; gap: 4px; }
.m-ap-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-ap-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-ap-badge-paid { background: rgba(16,185,129,0.15); color: #10B981; }
.m-ap-badge-overdue { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-ap-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-ap">
    <div class="m-ap-header">
        <h2 class="m-ap-title">Accounts Payable</h2>
        <p class="m-ap-sub"><?= $totalPayables ?> record<?= $totalPayables !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($payables)): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            <p>No accounts payable records</p>
        </div>
    <?php else: ?>
        <?php foreach ($payables as $p):
            $status = strtolower($p['status'] ?? 'pending');
            $badgeClass = match($status) {
                'paid' => 'paid',
                'overdue' => 'overdue',
                'pending' => 'pending',
                default => 'default',
            };
        ?>
        <div class="m-ap-card">
            <div class="m-ap-top">
                <span class="m-ap-vendor"><?= htmlspecialchars($p['vendor_name'] ?? 'Unknown Vendor') ?></span>
                <span class="m-ap-amount">$<?= number_format((float)($p['amount'] ?? 0), 2) ?></span>
            </div>
            <div class="m-ap-bottom">
                <div class="m-ap-due">
                    <?php if (!empty($p['due_date'])): ?>
                    <i class="fas fa-calendar"></i> Due: <?= date('M j, Y', strtotime($p['due_date'])) ?>
                    <?php endif; ?>
                </div>
                <span class="m-ap-badge m-ap-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
