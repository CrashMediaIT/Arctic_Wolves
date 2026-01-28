<?php
// =========================================================
// FINANCE DASHBOARD - Combined Accounting & Billing View
// Tabs: Overview, Billing, POS Transactions, Shop Orders
// =========================================================

$tab = $_GET['tab'] ?? 'overview';
?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-chart-pie"></i> Finance Dashboard</h1>
    <p class="page-description">Financial overview, billing, transactions, and payment management</p>
</div>

<!-- Tabs Navigation -->
<div class="page-tabs">
    <a href="?page=finance_dashboard&tab=overview" class="page-tab <?= $tab === 'overview' ? 'active' : '' ?>">
        <i class="fas fa-chart-line"></i> Overview
    </a>
    <a href="?page=finance_dashboard&tab=billing" class="page-tab <?= $tab === 'billing' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i> Billing
    </a>
    <a href="?page=finance_dashboard&tab=pos_transactions" class="page-tab <?= $tab === 'pos_transactions' ? 'active' : '' ?>">
        <i class="fas fa-receipt"></i> POS Transactions
    </a>
    <a href="?page=finance_dashboard&tab=shop_orders" class="page-tab <?= $tab === 'shop_orders' ? 'active' : '' ?>">
        <i class="fas fa-shopping-bag"></i> Shop Orders
    </a>
</div>

<div class="page-tab-content">
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
