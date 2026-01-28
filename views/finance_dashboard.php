<?php
// =========================================================
// FINANCE DASHBOARD - Combined Accounting & Billing View
// Tabs: Overview, Billing, POS Transactions, Shop Orders
// =========================================================

$tab = $_GET['tab'] ?? 'overview';
?>

<style>
/* Finance Dashboard Tabs Navigation - Financial Reports Hub Style */
.finance-tabs { display: flex; gap: 0; background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px 12px 0 0; overflow: hidden; margin-bottom: -1px; }
.finance-tab { flex: 1; padding: 18px 24px; background: transparent; border: none; border-bottom: 3px solid transparent; color: var(--text-dim); font-size: 14px; font-weight: 600; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; justify-content: center; gap: 10px; text-decoration: none; }
.finance-tab:hover { background: rgba(139, 92, 246, 0.05); color: var(--text-white); }
.finance-tab.active { background: rgba(139, 92, 246, 0.1); color: var(--primary); border-bottom-color: var(--primary); }
.finance-tab i { font-size: 16px; }

/* Tab Content Container */
.finance-tab-content { background: var(--bg-card); border: 1px solid var(--border); border-radius: 0 0 12px 12px; padding: 24px; }

/* Finance Page Header - Financial Reports Hub Style */
.finance-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.finance-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.finance-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.finance-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.finance-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
</style>

<div class="finance-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-chart-pie"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Finance Dashboard</h1>
            <p class="page-description">Financial overview, billing, transactions, and payment management</p>
        </div>
    </div>
</div>

<!-- Tabs Navigation -->
<div class="finance-tabs">
    <a href="?page=finance_dashboard&tab=overview" class="finance-tab <?= $tab === 'overview' ? 'active' : '' ?>">
        <i class="fas fa-chart-line"></i> Overview
    </a>
    <a href="?page=finance_dashboard&tab=billing" class="finance-tab <?= $tab === 'billing' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i> Billing
    </a>
    <a href="?page=finance_dashboard&tab=pos_transactions" class="finance-tab <?= $tab === 'pos_transactions' ? 'active' : '' ?>">
        <i class="fas fa-receipt"></i> POS Transactions
    </a>
    <a href="?page=finance_dashboard&tab=shop_orders" class="finance-tab <?= $tab === 'shop_orders' ? 'active' : '' ?>">
        <i class="fas fa-shopping-bag"></i> Shop Orders
    </a>
</div>

<div class="finance-tab-content">
    <?php
    if ($tab === 'overview') {
        include __DIR__ . '/finance_overview.php';
    } elseif ($tab === 'billing') {
        include __DIR__ . '/finance_billing.php';
    } elseif ($tab === 'pos_transactions') {
        include __DIR__ . '/pos_transactions.php';
    } elseif ($tab === 'shop_orders') {
        include __DIR__ . '/shop_orders.php';
    } else {
        // Default to overview for invalid tab values
        include __DIR__ . '/finance_overview.php';
    }
    ?>
</div>
