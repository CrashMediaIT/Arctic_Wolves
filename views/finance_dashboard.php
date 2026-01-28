<?php
// =========================================================
// FINANCE DASHBOARD - Combined Accounting & Billing View
// Two tabs: Overview and Billing
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
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-pie"></i> Finance Dashboard
    </h1>
    <p class="page-description">Financial overview, billing, and payment management</p>
</div>

<!-- Tabs Navigation -->
<div class="finance-tabs">
    <a href="?page=finance_dashboard&tab=overview" class="finance-tab <?= $tab === 'overview' ? 'active' : '' ?>">
        <i class="fas fa-chart-line"></i> Overview
    </a>
    <a href="?page=finance_dashboard&tab=billing" class="finance-tab <?= $tab === 'billing' ? 'active' : '' ?>">
        <i class="fas fa-file-invoice-dollar"></i> Billing
    </a>
</div>

<div class="finance-tab-content">
    <?php
    if ($tab === 'overview') {
        include __DIR__ . '/finance_overview.php';
    } elseif ($tab === 'billing') {
        include __DIR__ . '/finance_billing.php';
    } else {
        // Default to overview for invalid tab values
        include __DIR__ . '/finance_overview.php';
    }
    ?>
</div>
