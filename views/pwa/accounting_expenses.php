<?php
/**
 * PWA Accounting Expenses - Mobile-native expense tracking
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$monthlyTotal = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')");
    $stmt->execute();
    $monthlyTotal = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $monthlyTotal = 0; }

$expenses = [];
try {
    $stmt = $pdo->prepare("SELECT id, description, amount, category, expense_date, status, receipt_url FROM expenses ORDER BY expense_date DESC LIMIT 30");
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $expenses = []; }
?>
<style>
.m-expenses { padding: 16px; font-family: Inter, sans-serif; }
.m-expenses-header { margin-bottom: 16px; }
.m-expenses-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-expenses-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-expenses-summary {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 16px;
    text-align: center;
}
.m-expenses-summary-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.m-expenses-summary-value { font-size: 28px; font-weight: 700; color: #fff; margin-top: 4px; }
.m-expense-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-expense-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(239,68,68,0.15); color: #EF4444;
}
.m-expense-body { flex: 1; min-width: 0; }
.m-expense-desc { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-expense-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.m-expense-right { text-align: right; flex-shrink: 0; }
.m-expense-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-expense-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-expense-cat { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-expense-status-approved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-expense-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-expense-status-rejected { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-expense-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-expenses">
    <div class="m-expenses-header">
        <h2 class="m-expenses-title">Expenses</h2>
        <p class="m-expenses-sub"><?= count($expenses) ?> expense<?= count($expenses) !== 1 ? 's' : '' ?></p>
    </div>

    <div class="m-expenses-summary">
        <div class="m-expenses-summary-label">This Month's Expenses</div>
        <div class="m-expenses-summary-value">$<?= number_format($monthlyTotal, 2) ?></div>
    </div>

    <?php if (empty($expenses)): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            No expenses recorded
        </div>
    <?php else: ?>
        <?php foreach ($expenses as $exp):
            $status = strtolower($exp['status'] ?? 'default');
            $statusClass = match($status) {
                'approved' => 'approved',
                'pending' => 'pending',
                'rejected', 'denied' => 'rejected',
                default => 'default',
            };
        ?>
        <div class="m-expense-card">
            <div class="m-expense-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="m-expense-body">
                <div class="m-expense-desc"><?= htmlspecialchars($exp['description'] ?: 'Expense') ?></div>
                <div class="m-expense-meta">
                    <?php if (!empty($exp['category'])): ?>
                        <span class="m-expense-badge m-expense-cat"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $exp['category']))) ?></span>
                    <?php endif; ?>
                    <span><?= date('M j, Y', strtotime($exp['expense_date'])) ?></span>
                    <span class="m-expense-badge m-expense-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
            </div>
            <div class="m-expense-right">
                <div class="m-expense-amount">$<?= number_format((float)$exp['amount'], 2) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
