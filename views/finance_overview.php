<?php
// =========================================================
// FINANCE OVERVIEW TAB
// Revenue, Expenses, Net Profit, Outstanding Payments
// Charts with time filters and Year-over-Year comparison
// =========================================================

// Load Stripe configuration
$stripeSettingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('stripe_publishable_key', 'stripe_secret_key', 'currency', 'tax_rate', 'tax_name')";
$stripeSettings = $pdo->query($stripeSettingsQuery)->fetchAll(PDO::FETCH_KEY_PAIR);
// Decrypt Stripe keys (may be stored encrypted)
if (function_exists('decryptCredential')) {
    if (!empty($stripeSettings['stripe_secret_key'])) $stripeSettings['stripe_secret_key'] = decryptCredential($stripeSettings['stripe_secret_key']);
    if (!empty($stripeSettings['stripe_publishable_key'])) $stripeSettings['stripe_publishable_key'] = decryptCredential($stripeSettings['stripe_publishable_key']);
}
$stripeConfigured = !empty($stripeSettings['stripe_publishable_key']) && !empty($stripeSettings['stripe_secret_key']);
$currency = $stripeSettings['currency'] ?? 'CAD';
$taxRate = floatval($stripeSettings['tax_rate'] ?? 13.00);
$taxName = $stripeSettings['tax_name'] ?? 'HST';

// Initialize Stripe balance data
$stripeBalance = null;
$stripeRecentCharges = [];
$stripePendingTransactions = [];

// If Stripe is configured, try to fetch balance
if ($stripeConfigured) {
    try {
        $stripeLibLoaded = false;
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $stripeLibLoaded = true;
        } elseif (file_exists(__DIR__ . '/../stripe-php/init.php')) {
            require_once __DIR__ . '/../stripe-php/init.php';
            $stripeLibLoaded = true;
        }
        
        if ($stripeLibLoaded) {
            \Stripe\Stripe::setApiKey($stripeSettings['stripe_secret_key']);
            $stripeBalance = \Stripe\Balance::retrieve();

            // Fetch pending balance transactions from Stripe
            $pendingTxns = \Stripe\BalanceTransaction::all([
                'status' => 'pending',
                'limit' => 10,
            ]);
            if ($pendingTxns && !empty($pendingTxns->data)) {
                $stripePendingTransactions = $pendingTxns->data;
            }
        }
    } catch (Exception $e) {
        error_log("Stripe API error: " . $e->getMessage());
    }
}

// Get filter period
$filterPeriod = $_GET['period'] ?? 'month';
$chartPeriod = $_GET['chart_period'] ?? '30';

// Calculate date ranges based on filter
switch ($filterPeriod) {
    case 'day':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        $periodLabel = 'Today';
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        $periodLabel = 'This Week';
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $periodLabel = 'This Month';
        break;
    case 'quarter':
        $quarter = ceil(date('n') / 3);
        $startMonth = ($quarter - 1) * 3 + 1;
        $startDate = date('Y-' . str_pad($startMonth, 2, '0', STR_PAD_LEFT) . '-01');
        $endDate = date('Y-m-t', strtotime($startDate . ' +2 months'));
        $periodLabel = 'This Quarter (Q' . $quarter . ')';
        break;
    case 'year':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        $periodLabel = 'This Year';
        break;
    default:
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        $periodLabel = 'This Month';
}

// Fetch financial data
try {
    // Get total revenue from payments
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_revenue
        FROM payments
        WHERE payment_status = 'completed'
        AND DATE(payment_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $revenue = $revenueData['total_revenue'] ?? 0;
    
    // Get revenue from shop orders
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as shop_revenue
        FROM shop_orders
        WHERE payment_status = 'paid'
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $shopRevenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $shopRevenue = $shopRevenueData['shop_revenue'] ?? 0;
    
    // Get revenue from POS transactions
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(total), 0) as pos_revenue
        FROM pos_transactions
        WHERE status = 'completed'
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $posRevenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $posRevenue = $posRevenueData['pos_revenue'] ?? 0;
    
    // Get revenue from session bookings (Stripe payments)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as booking_revenue
        FROM bookings
        WHERE payment_status = 'paid'
        AND DATE(booking_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $bookingRevenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $bookingRevenue = $bookingRevenueData['booking_revenue'] ?? 0;
    
    // Get revenue from package purchases (camps, multi-week, credits)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount_paid), 0) as package_revenue
        FROM user_packages
        WHERE payment_status = 'paid'
        AND DATE(purchase_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $packageRevenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $packageRevenue = $packageRevenueData['package_revenue'] ?? 0;
    
    // Add all revenue sources to total
    $revenue += $shopRevenue + $posRevenue + $bookingRevenue + $packageRevenue;
    
    // Get total expenses
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(amount), 0) as total_expenses
        FROM expenses
        WHERE DATE(expense_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $expenseData = $stmt->fetch(PDO::FETCH_ASSOC);
    $expenses = $expenseData['total_expenses'] ?? 0;
    
    // Get outstanding payments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, COALESCE(SUM(total_amount), 0) as total
        FROM invoices
        WHERE status IN ('sent', 'draft', 'overdue')
    ");
    $stmt->execute();
    $outstandingData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $net_profit = $revenue - $expenses;
    
    // Get recent transactions for the activity feed
    $stmt = $pdo->prepare("
        SELECT 'payment' as type, p.id, p.amount, p.payment_date as trans_date, 
               u.first_name, u.last_name, p.payment_method
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.payment_status = 'completed'
        ORDER BY p.payment_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recentPayments = decryptUserRows($recentPayments);
    
    // Get recent shop orders
    $stmt = $pdo->prepare("
        SELECT 'shop_order' as type, id, total as amount, created_at as trans_date,
               customer_first_name as first_name, customer_last_name as last_name, order_number
        FROM shop_orders
        WHERE payment_status = 'paid'
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentShopOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recentShopOrders = decryptUserRows($recentShopOrders);
    
    // Get recent POS transactions
    $stmt = $pdo->prepare("
        SELECT 'pos' as type, pt.id, pt.total as amount, pt.created_at as trans_date,
               u.first_name, u.last_name, pt.payment_method, pt.transaction_number
        FROM pos_transactions pt
        LEFT JOIN users u ON pt.staff_id = u.id
        WHERE pt.status = 'completed'
        ORDER BY pt.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentPOSTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $recentPOSTransactions = decryptUserRows($recentPOSTransactions);
    
    $stmt = $pdo->prepare("
        SELECT 'expense' as type, e.id, e.amount, e.expense_date as trans_date,
               e.description, e.vendor_name
        FROM expenses e
        ORDER BY e.expense_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Merge and sort by date
    $transactions = array_merge($recentPayments, $recentShopOrders, $recentPOSTransactions, $recentExpenses);
    usort($transactions, function($a, $b) {
        return strtotime($b['trans_date']) - strtotime($a['trans_date']);
    });
    $transactions = array_slice($transactions, 0, 10);
    
    // Get revenue data for chart (based on chart period)
    // Combines payments, POS transactions (Stripe + cash), shop orders, bookings, and package purchases
    $chartDays = intval($chartPeriod);
    $stmt = $pdo->prepare("
        SELECT date, SUM(daily_revenue) as daily_revenue FROM (
            SELECT DATE(payment_date) as date, SUM(amount) as daily_revenue
            FROM payments
            WHERE payment_status = 'completed'
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(payment_date)
            UNION ALL
            SELECT DATE(created_at) as date, SUM(total) as daily_revenue
            FROM pos_transactions
            WHERE status = 'completed'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(created_at) as date, SUM(total) as daily_revenue
            FROM shop_orders
            WHERE payment_status = 'paid'
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(created_at)
            UNION ALL
            SELECT DATE(booking_date) as date, SUM(amount_paid) as daily_revenue
            FROM bookings
            WHERE payment_status = 'paid'
            AND booking_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(booking_date)
            UNION ALL
            SELECT DATE(purchase_date) as date, SUM(amount_paid) as daily_revenue
            FROM user_packages
            WHERE payment_status = 'paid'
            AND purchase_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
            GROUP BY DATE(purchase_date)
        ) AS combined_revenue
        GROUP BY date
        ORDER BY date ASC
    ");
    $stmt->execute([$chartDays, $chartDays, $chartDays, $chartDays, $chartDays]);
    $revenueChartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get expense data for chart
    $stmt = $pdo->prepare("
        SELECT DATE(expense_date) as date, SUM(amount) as daily_expense
        FROM expenses
        WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
        GROUP BY DATE(expense_date)
        ORDER BY date ASC
    ");
    $stmt->execute([$chartDays]);
    $expenseChartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Year-over-year data (current year vs last year monthly)
    // Combines payments, POS transactions (Stripe + cash), shop orders, bookings, and packages
    $currentYear = date('Y');
    $lastYear = $currentYear - 1;
    
    // Query to get yearly revenue data from all sources
    // Note: The year parameter must be passed 5 times (once per UNION clause)
    $getYearlyRevenueQuery = "
        SELECT month, SUM(monthly_revenue) as monthly_revenue FROM (
            SELECT MONTH(payment_date) as month, SUM(amount) as monthly_revenue
            FROM payments
            WHERE payment_status = 'completed'
            AND YEAR(payment_date) = ?
            GROUP BY MONTH(payment_date)
            UNION ALL
            SELECT MONTH(created_at) as month, SUM(total) as monthly_revenue
            FROM pos_transactions
            WHERE status = 'completed'
            AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at)
            UNION ALL
            SELECT MONTH(created_at) as month, SUM(total) as monthly_revenue
            FROM shop_orders
            WHERE payment_status = 'paid'
            AND YEAR(created_at) = ?
            GROUP BY MONTH(created_at)
            UNION ALL
            SELECT MONTH(booking_date) as month, SUM(amount_paid) as monthly_revenue
            FROM bookings
            WHERE payment_status = 'paid'
            AND YEAR(booking_date) = ?
            GROUP BY MONTH(booking_date)
            UNION ALL
            SELECT MONTH(purchase_date) as month, SUM(amount_paid) as monthly_revenue
            FROM user_packages
            WHERE payment_status = 'paid'
            AND YEAR(purchase_date) = ?
            GROUP BY MONTH(purchase_date)
        ) AS combined_yearly_revenue
        GROUP BY month
        ORDER BY month ASC
    ";
    
    $stmt = $pdo->prepare($getYearlyRevenueQuery);
    $stmt->execute([$currentYear, $currentYear, $currentYear, $currentYear, $currentYear]);
    $currentYearData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $stmt->execute([$lastYear, $lastYear, $lastYear, $lastYear, $lastYear]);
    $lastYearData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Calculate projection for remaining months (simple linear projection)
    $currentMonth = intval(date('n'));
    $totalCurrentYearRevenue = array_sum($currentYearData);
    $avgMonthlyRevenue = $currentMonth > 0 ? $totalCurrentYearRevenue / $currentMonth : 0;
    $projectedYearTotal = $avgMonthlyRevenue * 12;
    
} catch (PDOException $e) {
    error_log("Finance overview data fetch error: " . $e->getMessage());
    $revenue = 0;
    $expenses = 0;
    $net_profit = 0;
    $outstandingData = ['count' => 0, 'total' => 0];
    $transactions = [];
    $revenueChartData = [];
    $expenseChartData = [];
    $currentYearData = [];
    $lastYearData = [];
    $projectedYearTotal = 0;
}
?>

<!-- Stripe Integration Status -->
<div class="stripe-status-bar <?= $stripeConfigured ? 'configured' : 'not-configured' ?>">
    <div class="stripe-status-icon">
        <i class="fab fa-stripe-s"></i>
    </div>
    <div class="stripe-status-info">
        <?php if ($stripeConfigured): ?>
            <strong>Stripe Payment Processing Active</strong>
            <?php if ($stripeBalance && !empty($stripeBalance->available)): 
                $availableBalance = $stripeBalance->available[0] ?? null;
                $pendingBalance = $stripeBalance->pending[0] ?? null;
            ?>
                <span>Available Balance: <?= strtoupper($availableBalance->currency ?? $currency) ?> $<?= number_format(($availableBalance->amount ?? 0) / 100, 2) ?> | Pending: $<?= number_format(($pendingBalance->amount ?? 0) / 100, 2) ?></span>
            <?php else: ?>
                <span>Currency: <?= htmlspecialchars($currency) ?> | Tax: <?= htmlspecialchars($taxName) ?> (<?= number_format($taxRate, 2) ?>%)</span>
            <?php endif; ?>
        <?php else: ?>
            <strong>Stripe Not Configured</strong>
            <span>Configure Stripe in <a href="?page=system_tools&tab=payments">System Tools → Payments</a> to enable online payments</span>
        <?php endif; ?>
    </div>
    <?php if ($stripeConfigured): ?>
    <div class="stripe-status-badge active">
        <i class="fas fa-check-circle"></i> Active
    </div>
    <?php else: ?>
    <div class="stripe-status-badge inactive">
        <i class="fas fa-times-circle"></i> Inactive
    </div>
    <?php endif; ?>
</div>

<!-- Period Filter -->
<div class="filter-bar overview-filter">
    <div class="filter-label">
        <i class="fas fa-filter"></i> Viewing: <strong><?= htmlspecialchars($periodLabel) ?></strong>
    </div>
    <div class="period-filters">
        <a href="?page=finance_dashboard&tab=overview&period=day" class="filter-btn <?= $filterPeriod === 'day' ? 'active' : '' ?>">Day</a>
        <a href="?page=finance_dashboard&tab=overview&period=week" class="filter-btn <?= $filterPeriod === 'week' ? 'active' : '' ?>">Week</a>
        <a href="?page=finance_dashboard&tab=overview&period=month" class="filter-btn <?= $filterPeriod === 'month' ? 'active' : '' ?>">Month</a>
        <a href="?page=finance_dashboard&tab=overview&period=quarter" class="filter-btn <?= $filterPeriod === 'quarter' ? 'active' : '' ?>">Quarter</a>
        <a href="?page=finance_dashboard&tab=overview&period=year" class="filter-btn <?= $filterPeriod === 'year' ? 'active' : '' ?>">Year</a>
    </div>
</div>

<div class="overview-content">
    <!-- Stripe Balance Card (if configured) -->
    <?php if ($stripeConfigured && $stripeBalance && !empty($stripeBalance->available)): 
        $availableBalance = $stripeBalance->available[0] ?? null;
        $pendingBalance = $stripeBalance->pending[0] ?? null;
    ?>
    <div class="stripe-balance-section">
        <div class="stripe-balance-card">
            <div class="stripe-balance-header">
                <i class="fab fa-stripe"></i>
                <h3>Stripe Balance</h3>
            </div>
            <div class="stripe-balance-grid">
                <div class="balance-item available">
                    <span class="balance-label">Available</span>
                    <span class="balance-value">$<?= number_format(($availableBalance->amount ?? 0) / 100, 2) ?></span>
                    <span class="balance-currency"><?= strtoupper($availableBalance->currency ?? $currency) ?></span>
                </div>
                <div class="balance-item pending">
                    <span class="balance-label">Pending</span>
                    <span class="balance-value">$<?= number_format(($pendingBalance->amount ?? 0) / 100, 2) ?></span>
                    <span class="balance-currency"><?= strtoupper($pendingBalance->currency ?? $currency) ?></span>
                </div>
            </div>
            <a href="https://dashboard.stripe.com/balance/overview" target="_blank" class="stripe-dashboard-link">
                <i class="fas fa-external-link-alt"></i> View in Stripe Dashboard
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- Stripe Pending Transactions -->
    <?php if ($stripeConfigured && !empty($stripePendingTransactions)): ?>
    <div class="stripe-pending-section">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hourglass-half"></i> Stripe Pending Transactions</h3>
                <span class="pending-count-badge"><?= count($stripePendingTransactions) ?> pending</span>
            </div>
            <div class="card-body">
                <div class="pending-transactions-list">
                    <?php foreach ($stripePendingTransactions as $ptxn):
                        $ptxnAmount = ($ptxn->amount ?? 0) / 100;
                        $ptxnNet = ($ptxn->net ?? 0) / 100;
                        $ptxnFee = ($ptxn->fee ?? 0) / 100;
                        $ptxnCurrency = strtoupper($ptxn->currency ?? $currency);
                        $ptxnType = ucwords(str_replace('_', ' ', $ptxn->type ?? 'unknown'));
                        $ptxnCreated = date('M d, Y \a\t g:i A', $ptxn->created ?? time());
                        $ptxnAvailable = date('M d, Y', $ptxn->available_on ?? time());
                        $ptxnDesc = $ptxn->description ?? '';
                    ?>
                        <div class="pending-txn-item">
                            <div class="pending-txn-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="pending-txn-details">
                                <h4><?= htmlspecialchars($ptxnType) ?></h4>
                                <?php if ($ptxnDesc): ?>
                                    <span class="pending-txn-desc"><?= htmlspecialchars($ptxnDesc) ?></span>
                                <?php endif; ?>
                                <span class="pending-txn-date">Created: <?= $ptxnCreated ?></span>
                                <span class="pending-txn-available">Available: <?= $ptxnAvailable ?></span>
                            </div>
                            <div class="pending-txn-amounts">
                                <span class="pending-txn-gross">$<?= number_format($ptxnAmount, 2) ?> <small><?= $ptxnCurrency ?></small></span>
                                <?php if ($ptxnFee > 0): ?>
                                    <span class="pending-txn-fee">Fee: $<?= number_format($ptxnFee, 2) ?></span>
                                <?php endif; ?>
                                <span class="pending-txn-net">Net: $<?= number_format($ptxnNet, 2) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="https://dashboard.stripe.com/balance/overview" target="_blank" class="stripe-dashboard-link" style="margin-top: 16px;">
                    <i class="fas fa-external-link-alt"></i> View All in Stripe Dashboard
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Financial Summary Cards -->
    <div class="financial-summary">
        <div class="finance-card revenue-card">
            <div class="finance-icon revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="finance-details">
                <h4>Total Revenue</h4>
                <p class="finance-value">$<?= number_format($revenue, 2) ?></p>
                <span class="finance-change positive">
                    <i class="fas fa-arrow-up"></i> <?= $periodLabel ?>
                </span>
            </div>
        </div>
        <div class="finance-card expenses-card">
            <div class="finance-icon expenses">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="finance-details">
                <h4>Total Expenses</h4>
                <p class="finance-value">$<?= number_format($expenses, 2) ?></p>
                <span class="finance-change">
                    <i class="fas fa-minus"></i> <?= $periodLabel ?>
                </span>
            </div>
        </div>
        <div class="finance-card profit-card">
            <div class="finance-icon profit">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="finance-details">
                <h4>Net Profit</h4>
                <p class="finance-value <?= $net_profit >= 0 ? 'text-success' : 'text-danger' ?>">$<?= number_format($net_profit, 2) ?></p>
                <span class="finance-change <?= $net_profit >= 0 ? 'positive' : 'negative' ?>">
                    <i class="fas fa-<?= $net_profit >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i>
                    <?= $net_profit >= 0 ? 'Profit' : 'Loss' ?>
                </span>
            </div>
        </div>
        <div class="finance-card outstanding-card">
            <div class="finance-icon outstanding">
                <i class="fas fa-clock"></i>
            </div>
            <div class="finance-details">
                <h4>Outstanding</h4>
                <p class="finance-value">$<?= number_format($outstandingData['total'] ?? 0, 2) ?></p>
                <span class="finance-change warning">
                    <i class="fas fa-exclamation-circle"></i> <?= $outstandingData['count'] ?? 0 ?> pending
                </span>
            </div>
        </div>
    </div>

    <!-- Charts Section -->
    <div class="charts-grid">
        <!-- Revenue & Expense Chart -->
        <div class="card chart-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-area"></i> Revenue & Expenses</h3>
                <select class="form-input" style="width: auto;" id="revenueTimeframe" onchange="updateChartPeriod(this.value)">
                    <option value="7" <?= $chartPeriod == '7' ? 'selected' : '' ?>>1 Week</option>
                    <option value="30" <?= $chartPeriod == '30' ? 'selected' : '' ?>>1 Month</option>
                    <option value="90" <?= $chartPeriod == '90' ? 'selected' : '' ?>>Quarter</option>
                    <option value="180" <?= $chartPeriod == '180' ? 'selected' : '' ?>>6 Months</option>
                    <option value="365" <?= $chartPeriod == '365' ? 'selected' : '' ?>>1 Year</option>
                </select>
            </div>
            <div class="card-body">
                <canvas id="revenueExpenseChart" style="max-height: 300px;"></canvas>
            </div>
        </div>

        <!-- Year over Year Chart -->
        <div class="card chart-card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Year-over-Year Comparison</h3>
                <div class="projection-badge">
                    <i class="fas fa-lightbulb"></i> Projected: $<?= number_format($projectedYearTotal, 0) ?>
                </div>
            </div>
            <div class="card-body">
                <canvas id="yearOverYearChart" style="max-height: 300px;"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Activity</h3>
            <a href="?page=finance_dashboard&tab=billing" class="btn btn-secondary"><i class="fas fa-list"></i> View All</a>
        </div>
        <div class="card-body">
            <?php if (count($transactions) > 0): ?>
                <div class="transactions-list">
                    <?php foreach ($transactions as $trans): 
                        $isIncome = in_array($trans['type'], ['payment', 'shop_order', 'pos']);
                        $iconMap = [
                            'payment' => 'credit-card',
                            'shop_order' => 'shopping-bag',
                            'pos' => 'cash-register',
                            'expense' => 'receipt'
                        ];
                        $icon = $iconMap[$trans['type']] ?? 'money-bill';
                    ?>
                        <div class="transaction-item <?= $trans['type'] ?>">
                            <div class="transaction-icon">
                                <i class="fas fa-<?= $icon ?>"></i>
                            </div>
                            <div class="transaction-details">
                                <h4>
                                    <?php 
                                    if ($trans['type'] === 'payment') {
                                        echo 'Payment - ' . htmlspecialchars(($trans['first_name'] ?? '') . ' ' . ($trans['last_name'] ?? ''));
                                    } elseif ($trans['type'] === 'shop_order') {
                                        echo 'Shop Order #' . htmlspecialchars($trans['order_number'] ?? $trans['id']);
                                    } elseif ($trans['type'] === 'pos') {
                                        echo 'POS Sale - ' . htmlspecialchars($trans['transaction_number'] ?? '');
                                    } else {
                                        echo 'Expense - ' . htmlspecialchars($trans['description'] ?? $trans['vendor_name'] ?? 'Expense');
                                    }
                                    ?>
                                </h4>
                                <span class="transaction-date">
                                    <?= date('M d, Y \a\t g:i A', strtotime($trans['trans_date'])) ?>
                                </span>
                            </div>
                            <div class="transaction-amount <?= $isIncome ? 'positive' : 'negative' ?>">
                                <?= $isIncome ? '+' : '-' ?>$<?= number_format($trans['amount'], 2) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="placeholder-text">No recent transactions</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Revenue & Expense Chart Data
    const revenueData = <?= json_encode($revenueChartData) ?>;
    const expenseData = <?= json_encode($expenseChartData) ?>;
    const chartDays = <?= $chartDays ?>;
    
    // Prepare data arrays
    const labels = [];
    const revData = [];
    const expData = [];
    
    // Fill in days
    for (let i = chartDays - 1; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        labels.push(date.toLocaleDateString('en-US', { timeZone: window.APP_TIMEZONE, month: 'short', day: 'numeric' }));
        
        const dayRevenue = revenueData.find(d => d.date === dateStr);
        const dayExpense = expenseData.find(d => d.date === dateStr);
        revData.push(dayRevenue ? parseFloat(dayRevenue.daily_revenue) : 0);
        expData.push(dayExpense ? parseFloat(dayExpense.daily_expense) : 0);
    }
    
    // Revenue & Expense Chart
    const reCtx = document.getElementById('revenueExpenseChart');
    if (reCtx) {
        new Chart(reCtx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Revenue',
                        data: revData,
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Expenses',
                        data: expData,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        labels: { color: '#8B92A7' }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#8B92A7',
                            callback: function(value) { return '$' + value.toFixed(0); }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#8B92A7', maxRotation: 45, minRotation: 45 },
                        grid: { display: false }
                    }
                }
            }
        });
    }
    
    // Year-over-Year Chart
    const currentYearData = <?= json_encode($currentYearData) ?>;
    const lastYearData = <?= json_encode($lastYearData) ?>;
    const currentMonth = <?= $currentMonth ?>;
    const avgMonthlyRevenue = <?= $avgMonthlyRevenue ?>;
    
    const monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const currentYearValues = [];
    const lastYearValues = [];
    const projectionValues = [];
    
    for (let i = 1; i <= 12; i++) {
        currentYearValues.push(currentYearData[i] || 0);
        lastYearValues.push(lastYearData[i] || 0);
        // Projection: actual for past months, average for future
        if (i <= currentMonth) {
            projectionValues.push(null);
        } else {
            projectionValues.push(avgMonthlyRevenue);
        }
    }
    
    const yoyCtx = document.getElementById('yearOverYearChart');
    if (yoyCtx) {
        new Chart(yoyCtx, {
            type: 'bar',
            data: {
                labels: monthLabels,
                datasets: [
                    {
                        label: '<?= $currentYear ?>',
                        data: currentYearValues,
                        backgroundColor: 'rgba(107, 70, 193, 0.8)',
                        borderColor: '#6B46C1',
                        borderWidth: 1
                    },
                    {
                        label: '<?= $lastYear ?>',
                        data: lastYearValues,
                        backgroundColor: 'rgba(139, 92, 246, 0.4)',
                        borderColor: '#8B5CF6',
                        borderWidth: 1
                    },
                    {
                        label: 'Projection',
                        data: projectionValues,
                        backgroundColor: 'rgba(245, 158, 11, 0.5)',
                        borderColor: '#f59e0b',
                        borderWidth: 1,
                        borderDash: [5, 5]
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: true,
                        labels: { color: '#8B92A7' }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        callbacks: {
                            label: function(context) {
                                if (context.parsed.y === null) return '';
                                return context.dataset.label + ': $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#8B92A7',
                            callback: function(value) { return '$' + value.toFixed(0); }
                        },
                        grid: { color: 'rgba(255, 255, 255, 0.05)' }
                    },
                    x: {
                        ticks: { color: '#8B92A7' },
                        grid: { display: false }
                    }
                }
            }
        });
    }
});

function updateChartPeriod(days) {
    const url = new URL(window.location.href);
    url.searchParams.set('chart_period', days);
    window.location.href = url.toString();
}
</script>

<style>
/* Filter Bar */
.overview-filter {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 24px;
}

.filter-label {
    font-size: 14px;
    color: var(--text-dim);
}

.filter-label strong {
    color: var(--text-white);
}

.period-filters {
    display: flex;
    gap: 8px;
}

.filter-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dim);
    background: var(--bg-main);
    border: 1px solid var(--border);
    text-decoration: none;
    transition: all 0.2s;
}

.filter-btn:hover {
    border-color: var(--primary);
    color: var(--text-white);
}

.filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

/* Charts Grid */
.charts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
    gap: 24px;
    margin-bottom: 24px;
}

.chart-card {
    min-height: 400px;
}

.projection-badge {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 14px;
    background: rgba(245, 158, 11, 0.15);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #f59e0b;
}

/* Stripe Status Bar */
.stripe-status-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid;
}

.stripe-status-bar.configured {
    background: rgba(99, 91, 255, 0.1);
    border-color: rgba(99, 91, 255, 0.3);
}

.stripe-status-bar.not-configured {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
}

.stripe-status-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stripe-status-bar.configured .stripe-status-icon {
    background: rgba(99, 91, 255, 0.2);
    color: #635BFF;
}

.stripe-status-bar.not-configured .stripe-status-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.stripe-status-info {
    flex: 1;
}

.stripe-status-info strong {
    display: block;
    font-size: 14px;
    color: var(--text-white);
    margin-bottom: 4px;
}

.stripe-status-info span {
    font-size: 12px;
    color: var(--text-dim);
}

.stripe-status-info a {
    color: #8B5CF6;
    text-decoration: none;
}

.stripe-status-info a:hover {
    text-decoration: underline;
}

.stripe-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

.stripe-status-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.stripe-status-badge.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

/* Stripe Balance Section */
.stripe-balance-section {
    margin-bottom: 24px;
}

.stripe-balance-card {
    background: linear-gradient(135deg, rgba(99, 91, 255, 0.15), rgba(139, 92, 246, 0.1));
    border: 1px solid rgba(99, 91, 255, 0.3);
    border-radius: 16px;
    padding: 24px;
}

.stripe-balance-header {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.stripe-balance-header i {
    font-size: 28px;
    color: #635BFF;
}

.stripe-balance-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.stripe-balance-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 16px;
}

.balance-item {
    background: var(--bg-card);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    border: 1px solid var(--border);
}

.balance-item.available {
    border-color: rgba(16, 185, 129, 0.3);
}

.balance-item.pending {
    border-color: rgba(245, 158, 11, 0.3);
}

.balance-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.balance-value {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: var(--text-white);
    margin-bottom: 4px;
}

.balance-item.available .balance-value {
    color: #10b981;
}

.balance-item.pending .balance-value {
    color: #f59e0b;
}

.balance-currency {
    font-size: 12px;
    color: var(--text-dim);
}

.stripe-dashboard-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #635BFF;
    font-size: 13px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.2s;
}

.stripe-dashboard-link:hover {
    color: #8B5CF6;
    text-decoration: underline;
}

.overview-content {
    max-width: 1400px;
    margin: 0 auto;
}

.financial-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.finance-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 16px;
    padding: 28px;
    display: flex;
    align-items: center;
    gap: 24px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.finance-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    border-radius: 16px 16px 0 0;
}

.finance-card.revenue-card::before { background: linear-gradient(90deg, #10b981, #34d399); }
.finance-card.expenses-card::before { background: linear-gradient(90deg, #ef4444, #f87171); }
.finance-card.profit-card::before { background: linear-gradient(90deg, #6B46C1, #8B5CF6); }
.finance-card.outstanding-card::before { background: linear-gradient(90deg, #f59e0b, #fbbf24); }

.finance-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.4);
    border-color: rgba(107, 70, 193, 0.3);
}

.finance-icon {
    width: 64px;
    height: 64px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    color: #fff;
    flex-shrink: 0;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.finance-icon.revenue { background: linear-gradient(135deg, #10b981, #059669); }
.finance-icon.expenses { background: linear-gradient(135deg, #ef4444, #dc2626); }
.finance-icon.profit { background: linear-gradient(135deg, var(--primary), var(--primary-hover)); }
.finance-icon.outstanding { background: linear-gradient(135deg, #f59e0b, #d97706); }

.finance-details { flex: 1; }

.finance-details h4 {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 700;
}

.finance-value {
    font-size: 32px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 6px;
    line-height: 1;
}

.finance-value.text-success { color: #10b981; }
.finance-value.text-danger { color: #ef4444; }

.finance-change {
    font-size: 13px;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    gap: 6px;
}

.finance-change i { font-size: 11px; }
.finance-change.positive { color: #10b981; }
.finance-change.negative { color: #ef4444; }
.finance-change.warning { color: #f59e0b; }

/* Transactions */
.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.transaction-item:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.transaction-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 18px;
}

.transaction-item.payment .transaction-icon {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.transaction-item.expense .transaction-icon {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.transaction-details { flex: 1; }

.transaction-details h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 4px;
}

.transaction-date {
    font-size: 12px;
    color: var(--text-dim);
}

.transaction-amount {
    font-size: 17px;
    font-weight: 800;
}

.transaction-amount.positive { color: #10b981; }
.transaction-amount.negative { color: #ef4444; }

.placeholder-text {
    color: var(--text-dim);
    text-align: center;
    padding: 40px 20px;
    font-size: 14px;
}

@media (max-width: 992px) {
    .financial-summary {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .charts-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .financial-summary {
        grid-template-columns: 1fr;
    }
    
    .overview-filter {
        flex-direction: column;
        gap: 16px;
        align-items: stretch;
    }
    
    .period-filters {
        flex-wrap: wrap;
        justify-content: center;
    }
    
    .finance-card {
        padding: 20px;
    }
    
    .finance-value {
        font-size: 26px;
    }
}

/* Stripe Pending Transactions */
.stripe-pending-section {
    margin-bottom: 24px;
}

.pending-count-badge {
    display: inline-flex;
    align-items: center;
    padding: 6px 12px;
    background: rgba(245, 158, 11, 0.15);
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
    color: #f59e0b;
}

.pending-transactions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pending-txn-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 18px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    border-left: 4px solid #f59e0b;
    transition: all 0.3s ease;
}

.pending-txn-item:hover {
    border-color: rgba(245, 158, 11, 0.5);
    background: rgba(245, 158, 11, 0.05);
}

.pending-txn-icon {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 18px;
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
}

.pending-txn-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.pending-txn-details h4 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white);
    margin: 0 0 2px 0;
}

.pending-txn-desc {
    font-size: 12px;
    color: var(--text-dim);
}

.pending-txn-date,
.pending-txn-available {
    font-size: 11px;
    color: var(--text-dim);
}

.pending-txn-available {
    color: #f59e0b;
}

.pending-txn-amounts {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    flex-shrink: 0;
}

.pending-txn-gross {
    font-size: 16px;
    font-weight: 800;
    color: var(--text-white);
}

.pending-txn-fee {
    font-size: 11px;
    color: #ef4444;
}

.pending-txn-net {
    font-size: 13px;
    font-weight: 700;
    color: #f59e0b;
}

@media (max-width: 768px) {
    .pending-txn-item {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .pending-txn-amounts {
        align-items: flex-start;
        flex-direction: row;
        gap: 12px;
        flex-wrap: wrap;
    }
}
</style>
