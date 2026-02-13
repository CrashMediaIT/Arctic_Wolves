<?php
/**
 * PWA Finance Dashboard - Mobile-native finance overview
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$totalRevenue = 0;
$monthlyRevenue = 0;
$pendingPayments = 0;
$activeSubscriptions = 0;

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed'");
    $stmt->execute();
    $totalRevenue = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $totalRevenue = 0; }

try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'completed' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute();
    $monthlyRevenue = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $monthlyRevenue = 0; }

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM payments WHERE status = 'pending'");
    $stmt->execute();
    $pendingPayments = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $pendingPayments = 0; }

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM subscriptions WHERE status = 'active'");
    $stmt->execute();
    $activeSubscriptions = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $activeSubscriptions = 0; }

$recentTransactions = [];
try {
    $stmt = $pdo->prepare("SELECT id, amount, payment_method, status, created_at, description FROM payments ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentTransactions = []; }
?>
<style>
.m-finance { padding: 16px; font-family: Inter, sans-serif; }
.m-finance-header { margin-bottom: 16px; }
.m-finance-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-finance-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-finance-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-finance-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-finance-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-finance-stat-value { font-size: 24px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-finance-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-finance-quick {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-bottom: 20px;
}
.m-finance-quick-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 8px; text-decoration: none;
    min-height: 44px; min-width: 44px;
}
.m-finance-quick-btn i { font-size: 18px; color: #8B5CF6; }
.m-finance-quick-btn span { font-size: 10px; color: #A8A8B8; font-weight: 500; text-align: center; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-finance-tx {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-finance-tx-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-finance-tx-icon-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-finance-tx-icon-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-finance-tx-icon-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-finance-tx-icon-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-finance-tx-body { flex: 1; min-width: 0; }
.m-finance-tx-desc { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-finance-tx-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-finance-tx-right { text-align: right; flex-shrink: 0; }
.m-finance-tx-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-finance-tx-status {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    margin-top: 3px; display: inline-block;
}
.m-finance-tx-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-finance-tx-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-finance-tx-status-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-finance-tx-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-finance">
    <div class="m-finance-header">
        <h2 class="m-finance-title">Finance Dashboard</h2>
        <p class="m-finance-sub">Revenue &amp; payment overview</p>
    </div>

    <div class="m-finance-kpi">
        <div class="m-finance-stat">
            <div class="m-finance-stat-icon" style="color:#10B981;"><i class="fas fa-dollar-sign"></i></div>
            <div class="m-finance-stat-value">$<?= number_format($totalRevenue, 0) ?></div>
            <div class="m-finance-stat-label">Total Revenue</div>
        </div>
        <div class="m-finance-stat">
            <div class="m-finance-stat-icon" style="color:#8B5CF6;"><i class="fas fa-calendar"></i></div>
            <div class="m-finance-stat-value">$<?= number_format($monthlyRevenue, 0) ?></div>
            <div class="m-finance-stat-label">This Month</div>
        </div>
        <div class="m-finance-stat">
            <div class="m-finance-stat-icon" style="color:#F59E0B;"><i class="fas fa-clock"></i></div>
            <div class="m-finance-stat-value"><?= $pendingPayments ?></div>
            <div class="m-finance-stat-label">Pending</div>
        </div>
        <div class="m-finance-stat">
            <div class="m-finance-stat-icon" style="color:#3B82F6;"><i class="fas fa-sync"></i></div>
            <div class="m-finance-stat-value"><?= $activeSubscriptions ?></div>
            <div class="m-finance-stat-label">Subscriptions</div>
        </div>
    </div>

    <div class="m-finance-quick">
        <a href="?page=financial_reports" class="m-finance-quick-btn">
            <i class="fas fa-chart-bar"></i><span>Reports</span>
        </a>
        <a href="?page=accounting_credits" class="m-finance-quick-btn">
            <i class="fas fa-hand-holding-dollar"></i><span>Credits</span>
        </a>
        <a href="?page=accounting_expenses" class="m-finance-quick-btn">
            <i class="fas fa-file-invoice-dollar"></i><span>Expenses</span>
        </a>
    </div>

    <h3 class="m-section-title">Recent Transactions</h3>
    <?php if (empty($recentTransactions)): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            No transactions found
        </div>
    <?php else: ?>
        <?php foreach ($recentTransactions as $tx):
            $status = strtolower($tx['status'] ?? 'default');
            $statusClass = match($status) {
                'completed', 'paid', 'succeeded' => 'completed',
                'pending', 'processing' => 'pending',
                'failed', 'declined' => 'failed',
                default => 'default',
            };
            $methodIcon = match(strtolower($tx['payment_method'] ?? '')) {
                'credit_card', 'card', 'stripe' => 'fa-credit-card',
                'cash' => 'fa-money-bill',
                'bank', 'transfer' => 'fa-building-columns',
                default => 'fa-receipt',
            };
        ?>
        <div class="m-finance-tx">
            <div class="m-finance-tx-icon m-finance-tx-icon-<?= $statusClass ?>">
                <i class="fas <?= $methodIcon ?>"></i>
            </div>
            <div class="m-finance-tx-body">
                <div class="m-finance-tx-desc"><?= htmlspecialchars($tx['description'] ?: 'Payment') ?></div>
                <div class="m-finance-tx-meta">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['payment_method'] ?? 'N/A'))) ?>
                    · <?= date('M j, Y', strtotime($tx['created_at'])) ?>
                </div>
            </div>
            <div class="m-finance-tx-right">
                <div class="m-finance-tx-amount">$<?= number_format((float)$tx['amount'], 2) ?></div>
                <span class="m-finance-tx-status m-finance-tx-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
