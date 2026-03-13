<?php
/**
 * PWA Finance Dashboard - Mobile-native finance overview
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessAccounting) {
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

// Billing tab data
$recentInvoices = [];
try {
    $stmt = $pdo->prepare("SELECT i.*, u.first_name, u.last_name FROM invoices i LEFT JOIN users u ON i.user_id = u.id ORDER BY i.invoice_date DESC LIMIT 15");
    $stmt->execute();
    $recentInvoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) { $recentInvoices = decryptUserRows($recentInvoices); }
} catch (PDOException $e) { $recentInvoices = []; }

$invoiceStats = ['total_invoices' => 0, 'invoice_paid' => 0, 'total_pending' => 0, 'total_overdue' => 0];
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total_invoices, COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as invoice_paid, COALESCE(SUM(CASE WHEN status IN ('sent', 'pending') THEN total_amount ELSE 0 END), 0) as total_pending, COALESCE(SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END), 0) as total_overdue FROM invoices");
    $invoiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* keep defaults */ }

// POS Transactions tab data
$recentPOS = [];
try {
    $stmt = $pdo->prepare("
        SELECT pt.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM pos_transaction_items WHERE transaction_id = pt.id) as item_count
        FROM pos_transactions pt
        LEFT JOIN users u ON pt.staff_id = u.id
        ORDER BY pt.created_at DESC LIMIT 15
    ");
    $stmt->execute();
    $recentPOS = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) { $recentPOS = decryptUserRows($recentPOS); }
} catch (PDOException $e) { $recentPOS = []; }

// Shop Orders tab data
$recentShopOrders = [];
try {
    $stmt = $pdo->prepare("
        SELECT so.*, 
               (SELECT COUNT(*) FROM shop_order_items WHERE order_id = so.id) as item_count
        FROM shop_orders so
        ORDER BY so.created_at DESC LIMIT 15
    ");
    $stmt->execute();
    $recentShopOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) { $recentShopOrders = decryptUserRows($recentShopOrders); }
} catch (PDOException $e) { $recentShopOrders = []; }
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

/* Tab navigation */
.m-finance-tabs {
    display: flex; gap: 4px; margin-bottom: 20px;
    overflow-x: auto; -webkit-overflow-scrolling: touch;
    scrollbar-width: none; padding-bottom: 2px;
}
.m-finance-tabs::-webkit-scrollbar { display: none; }
.m-finance-tab {
    flex-shrink: 0; padding: 10px 16px; border-radius: 10px;
    background: #16161F; border: 1px solid #2D2D3F;
    color: #A8A8B8; font-size: 12px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer;
    display: flex; align-items: center; gap: 6px;
    min-height: 44px; white-space: nowrap;
    text-decoration: none; transition: all 0.2s;
}
.m-finance-tab.active {
    background: #6B46C1; border-color: #6B46C1; color: #fff;
}
.m-finance-tab i { font-size: 12px; }
.m-finance-tab-content { display: none; }
.m-finance-tab-content.active { display: block; }

/* Billing card styles */
.m-invoice-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-invoice-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-invoice-num { font-size: 13px; font-weight: 600; color: #fff; font-family: monospace; }
.m-invoice-amount { font-size: 15px; font-weight: 700; color: #fff; flex-shrink: 0; }
.m-invoice-customer { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; }
.m-invoice-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-invoice-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-invoice-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600; white-space: nowrap;
}
.m-invoice-badge-paid { background: rgba(16,185,129,0.15); color: #10B981; }
.m-invoice-badge-sent { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-invoice-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-invoice-badge-overdue { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-invoice-badge-draft { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-invoice-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }

/* POS card styles (reuse from pwa/pos_transactions.php patterns) */
.m-pos-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-pos-card-top { display: flex; align-items: center; gap: 12px; }
.m-pos-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; flex-shrink: 0;
}
.m-pos-icon-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-pos-icon-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-pos-icon-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-pos-icon-refunded { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-pos-info { flex: 1; min-width: 0; }
.m-pos-amount { font-size: 15px; font-weight: 700; color: #fff; }
.m-pos-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-pos-right { text-align: right; flex-shrink: 0; }
.m-pos-method {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6; display: inline-block;
}
.m-pos-status {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    display: inline-block; margin-top: 4px;
}
.m-pos-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-pos-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-pos-status-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-pos-status-refunded { background: rgba(139,92,246,0.15); color: #8B5CF6; }

/* Shop order card styles */
.m-so-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px; min-height: 44px;
}
.m-so-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-so-num { font-size: 14px; font-weight: 600; color: #fff; }
.m-so-amount { font-size: 15px; font-weight: 700; color: #10B981; flex-shrink: 0; }
.m-so-customer { font-size: 12px; color: #A8A8B8; margin-bottom: 6px; }
.m-so-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-so-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-so-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600; white-space: nowrap;
}
.m-so-badge-paid { background: rgba(16,185,129,0.15); color: #10B981; }
.m-so-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-so-badge-processing { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-so-badge-shipped { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-so-badge-delivered { background: rgba(16,185,129,0.15); color: #10B981; }
.m-so-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-so-badge-refunded { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-so-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-so-items { font-size: 11px; color: #6B6B7B; }

/* Tab KPI row */
.m-tab-kpi { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; }
.m-tab-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; text-align: center;
}
.m-tab-stat-value { font-size: 20px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-tab-stat-label { font-size: 10px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }

/* View all link */
.m-view-all {
    display: block; text-align: center; padding: 14px;
    color: #8B5CF6; font-size: 13px; font-weight: 600;
    text-decoration: none; min-height: 44px;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
</style>

<div class="m-finance">
    <div class="m-finance-header">
        <h2 class="m-finance-title">Finance Dashboard</h2>
        <p class="m-finance-sub">Revenue &amp; payment overview</p>
    </div>

    <!-- Tab Navigation -->
    <div class="m-finance-tabs">
        <button class="m-finance-tab active" onclick="switchFinanceTab('overview', this)">
            <i class="fas fa-chart-line"></i> Overview
        </button>
        <button class="m-finance-tab" onclick="switchFinanceTab('billing', this)">
            <i class="fas fa-file-invoice-dollar"></i> Billing
        </button>
        <button class="m-finance-tab" onclick="switchFinanceTab('pos', this)">
            <i class="fas fa-receipt"></i> POS
        </button>
        <button class="m-finance-tab" onclick="switchFinanceTab('orders', this)">
            <i class="fas fa-shopping-bag"></i> Orders
        </button>
    </div>

    <!-- Overview Tab -->
    <div class="m-finance-tab-content active" id="mFinTab-overview">
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
    </div><!-- /overview tab -->

    <!-- Billing Tab -->
    <div class="m-finance-tab-content" id="mFinTab-billing">
        <div class="m-tab-kpi">
            <div class="m-tab-stat">
                <div class="m-tab-stat-value" style="color:#10B981;">$<?= number_format((float)($invoiceStats['invoice_paid'] ?? 0), 0) ?></div>
                <div class="m-tab-stat-label">Paid</div>
            </div>
            <div class="m-tab-stat">
                <div class="m-tab-stat-value" style="color:#F59E0B;">$<?= number_format((float)($invoiceStats['total_pending'] ?? 0), 0) ?></div>
                <div class="m-tab-stat-label">Pending</div>
            </div>
            <div class="m-tab-stat">
                <div class="m-tab-stat-value" style="color:#EF4444;">$<?= number_format((float)($invoiceStats['total_overdue'] ?? 0), 0) ?></div>
                <div class="m-tab-stat-label">Overdue</div>
            </div>
            <div class="m-tab-stat">
                <div class="m-tab-stat-value"><?= (int)($invoiceStats['total_invoices'] ?? 0) ?></div>
                <div class="m-tab-stat-label">Total Invoices</div>
            </div>
        </div>

        <h3 class="m-section-title">Recent Invoices</h3>
        <?php if (empty($recentInvoices)): ?>
            <div class="m-empty-state">
                <i class="fas fa-file-invoice-dollar"></i>
                No invoices found
            </div>
        <?php else: ?>
            <?php foreach ($recentInvoices as $inv):
                $invStatus = strtolower($inv['status'] ?? 'draft');
                $invBadge = match($invStatus) {
                    'paid' => 'paid',
                    'sent' => 'sent',
                    'pending' => 'pending',
                    'overdue' => 'overdue',
                    'draft' => 'draft',
                    'cancelled' => 'cancelled',
                    default => 'draft',
                };
            ?>
            <div class="m-invoice-card">
                <div class="m-invoice-top">
                    <span class="m-invoice-num"><?= htmlspecialchars($inv['invoice_number'] ?? '#' . $inv['id']) ?></span>
                    <span class="m-invoice-amount">$<?= number_format((float)($inv['total_amount'] ?? 0), 2) ?></span>
                </div>
                <?php if (!empty($inv['first_name']) || !empty($inv['last_name'])): ?>
                <div class="m-invoice-customer"><i class="fas fa-user" style="font-size:10px;"></i> <?= htmlspecialchars(trim(($inv['first_name'] ?? '') . ' ' . ($inv['last_name'] ?? ''))) ?></div>
                <?php endif; ?>
                <div class="m-invoice-bottom">
                    <div class="m-invoice-date">
                        <i class="fas fa-calendar"></i>
                        <?= !empty($inv['invoice_date']) ? date('M j, Y', strtotime($inv['invoice_date'])) : 'N/A' ?>
                        <?php if (!empty($inv['due_date'])): ?>
                        · Due <?= date('M j', strtotime($inv['due_date'])) ?>
                        <?php endif; ?>
                    </div>
                    <span class="m-invoice-badge m-invoice-badge-<?= $invBadge ?>"><?= ucfirst($invStatus) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="?page=billing_dashboard" class="m-view-all">View All Billing <i class="fas fa-arrow-right"></i></a>
    </div><!-- /billing tab -->

    <!-- POS Transactions Tab -->
    <div class="m-finance-tab-content" id="mFinTab-pos">
        <h3 class="m-section-title">Recent POS Transactions</h3>
        <?php if (empty($recentPOS)): ?>
            <div class="m-empty-state">
                <i class="fas fa-cash-register"></i>
                No POS transactions found
            </div>
        <?php else: ?>
            <?php foreach ($recentPOS as $pos):
                $posStatus = strtolower($pos['status'] ?? 'completed');
                $posIconClass = match($posStatus) {
                    'completed' => 'completed',
                    'pending' => 'pending',
                    'cancelled' => 'cancelled',
                    'refunded' => 'refunded',
                    default => 'completed',
                };
            ?>
            <div class="m-pos-card">
                <div class="m-pos-card-top">
                    <div class="m-pos-icon m-pos-icon-<?= $posIconClass ?>"><i class="fas fa-receipt"></i></div>
                    <div class="m-pos-info">
                        <div class="m-pos-amount">$<?= number_format((float)($pos['total'] ?? 0), 2) ?></div>
                        <div class="m-pos-meta">
                            <?php if (!empty($pos['transaction_number'])): ?>
                            <span style="font-family:monospace;font-size:11px;"><?= htmlspecialchars($pos['transaction_number']) ?></span> ·
                            <?php endif; ?>
                            <?php if (!empty($pos['first_name'])): ?>
                            <?= htmlspecialchars(($pos['first_name'] ?? '') . ' ' . ($pos['last_name'] ?? '')) ?> ·
                            <?php endif; ?>
                            <?= !empty($pos['created_at']) ? date('M j, g:i A', strtotime($pos['created_at'])) : '' ?>
                        </div>
                    </div>
                    <div class="m-pos-right">
                        <?php if (!empty($pos['payment_method'])): ?>
                        <span class="m-pos-method"><?= ucfirst($pos['payment_method']) ?></span>
                        <?php endif; ?>
                        <div class="m-pos-status m-pos-status-<?= $posStatus ?>"><?= ucfirst($posStatus) ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="?page=pos_transactions" class="m-view-all">View All POS Transactions <i class="fas fa-arrow-right"></i></a>
    </div><!-- /pos tab -->

    <!-- Shop Orders Tab -->
    <div class="m-finance-tab-content" id="mFinTab-orders">
        <h3 class="m-section-title">Recent Shop Orders</h3>
        <?php if (empty($recentShopOrders)): ?>
            <div class="m-empty-state">
                <i class="fas fa-shopping-bag"></i>
                No shop orders found
            </div>
        <?php else: ?>
            <?php foreach ($recentShopOrders as $so):
                $soStatus = strtolower($so['status'] ?? 'pending');
                $soBadge = match($soStatus) {
                    'paid' => 'paid',
                    'pending' => 'pending',
                    'processing' => 'processing',
                    'shipped' => 'shipped',
                    'delivered' => 'delivered',
                    'cancelled' => 'cancelled',
                    'refunded' => 'refunded',
                    default => 'default',
                };
            ?>
            <div class="m-so-card">
                <div class="m-so-top">
                    <span class="m-so-num">#<?= htmlspecialchars($so['order_number'] ?? $so['id']) ?></span>
                    <span class="m-so-amount">$<?= number_format((float)($so['total'] ?? 0), 2) ?></span>
                </div>
                <?php if (!empty($so['customer_first_name']) || !empty($so['customer_last_name'])): ?>
                <div class="m-so-customer"><i class="fas fa-user" style="font-size:10px;"></i> <?= htmlspecialchars(trim(($so['customer_first_name'] ?? '') . ' ' . ($so['customer_last_name'] ?? ''))) ?></div>
                <?php endif; ?>
                <div class="m-so-bottom">
                    <div class="m-so-date">
                        <i class="fas fa-calendar"></i>
                        <?= !empty($so['created_at']) ? date('M j, Y', strtotime($so['created_at'])) : 'N/A' ?>
                        <?php if (!empty($so['item_count'])): ?>
                        · <span class="m-so-items"><?= (int)$so['item_count'] ?> item<?= (int)$so['item_count'] !== 1 ? 's' : '' ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="m-so-badge m-so-badge-<?= $soBadge ?>"><?= ucfirst($soStatus) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
        <a href="?page=shop_orders" class="m-view-all">View All Shop Orders <i class="fas fa-arrow-right"></i></a>
    </div><!-- /orders tab -->
</div>

<script>
function switchFinanceTab(tabId, btn) {
    document.querySelectorAll('.m-finance-tab-content').forEach(function(el) { el.classList.remove('active'); });
    document.querySelectorAll('.m-finance-tab').forEach(function(el) { el.classList.remove('active'); });
    document.getElementById('mFinTab-' + tabId).classList.add('active');
    btn.classList.add('active');
    btn.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
}
</script>
