<?php
// =========================================================
// FINANCE DASHBOARD - Combined Accounting & Billing View
// Two tabs: Overview and Billing
// =========================================================

$tab = $_GET['tab'] ?? 'overview';
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-pie"></i> Finance Dashboard
    </h1>
    <p class="page-description">Financial overview, billing, and payment management</p>
</div>

<div class="tab-navigation" data-component="TabNavigation">
    <a href="?page=finance_dashboard&tab=overview" class="tab-link <?= $tab === 'overview' ? 'active' : '' ?>" data-tab="overview">
        <i class="fas fa-chart-line"></i> Overview
    </a>
    <a href="?page=finance_dashboard&tab=billing" class="tab-link <?= $tab === 'billing' ? 'active' : '' ?>" data-tab="billing">
        <i class="fas fa-file-invoice-dollar"></i> Billing
    </a>
</div>

<div class="page-tab-content">
    <?php
    if ($tab === 'overview') {
        include __DIR__ . '/finance_overview.php';
    } elseif ($tab === 'billing') {
        include __DIR__ . '/finance_billing.php';
    }
    ?>
</div>
