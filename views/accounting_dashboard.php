<!-- Accounting Dashboard View -->
<?php
// Load Stripe configuration
$stripeSettingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('stripe_publishable_key', 'stripe_secret_key', 'currency', 'tax_rate', 'tax_name')";
$stripeSettings = $pdo->query($stripeSettingsQuery)->fetchAll(PDO::FETCH_KEY_PAIR);
$stripeConfigured = !empty($stripeSettings['stripe_publishable_key']) && !empty($stripeSettings['stripe_secret_key']);
$currency = $stripeSettings['currency'] ?? 'CAD';
$taxRate = floatval($stripeSettings['tax_rate'] ?? 13.00);
$taxName = $stripeSettings['tax_name'] ?? 'HST';

// Initialize Stripe balance data
$stripeBalance = null;
$stripeRecentCharges = [];

// If Stripe is configured, try to fetch balance and recent charges
if ($stripeConfigured) {
    try {
        // Load Stripe library
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
            
            // Get Stripe balance
            $stripeBalance = \Stripe\Balance::retrieve();
            
            // Get recent successful charges from Stripe
            $charges = \Stripe\Charge::all([
                'limit' => 10,
                'created' => [
                    'gte' => strtotime('-30 days')
                ]
            ]);
            $stripeRecentCharges = $charges->data;
        }
        
    } catch (Exception $e) {
        error_log("Stripe API error: " . $e->getMessage());
        // Continue with local data if Stripe fails
    }
}

// Fetch financial data from database
try {
    // Get total revenue (payments received) - try both status column names for compatibility
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_revenue
        FROM payments
        WHERE (payment_status = 'completed' OR status = 'completed')
        AND MONTH(payment_date) = MONTH(CURDATE())
        AND YEAR(payment_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $revenueData = $stmt->fetch(PDO::FETCH_ASSOC);
    $revenue = $revenueData['total_revenue'] ?? 0;
    
    // Get total expenses
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_expenses
        FROM expenses
        WHERE MONTH(expense_date) = MONTH(CURDATE())
        AND YEAR(expense_date) = YEAR(CURDATE())
    ");
    $stmt->execute();
    $expenseData = $stmt->fetch(PDO::FETCH_ASSOC);
    $expenses = $expenseData['total_expenses'] ?? 0;
    
    // Get outstanding payments
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count, SUM(amount) as total
        FROM payments
        WHERE status IN ('pending', 'processing')
    ");
    $stmt->execute();
    $outstandingData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $net_profit = $revenue - $expenses;
    
    // Get recent transactions
    $stmt = $pdo->prepare("
        SELECT 'payment' as type, p.*, u.first_name, u.last_name, p.amount, p.payment_date as trans_date
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.payment_status = 'completed' OR p.status = 'completed'
        UNION ALL
        SELECT 'expense' as type, e.*, NULL as first_name, NULL as last_name, e.amount, e.expense_date as trans_date
        FROM expenses e
        ORDER BY trans_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get revenue data for last 30 days for chart
    $stmt = $pdo->prepare("
        SELECT DATE(payment_date) as date, SUM(amount) as daily_revenue
        FROM payments
        WHERE status = 'completed'
        AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        GROUP BY DATE(payment_date)
        ORDER BY date ASC
    ");
    $stmt->execute();
    $revenueChartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Accounting data fetch error: " . $e->getMessage());
    $revenue = 0;
    $expenses = 0;
    $net_profit = 0;
    $outstandingData = ['count' => 0, 'total' => 0];
    $transactions = [];
    $revenueChartData = [];
}
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-pie"></i> Accounting Dashboard
    </h1>
    <p class="page-description">Financial overview and key metrics for your organization</p>
</div>

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
            <span>Configure Stripe in <a href="?page=system_tools&tab=payments">System Tools → Payments</a> to enable online payments and real-time balance tracking</span>
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

<div class="accounting-content">
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
    
    <!-- Financial Summary Cards -->
    <div class="financial-summary">
        <div class="finance-card revenue-card">
            <div class="finance-icon revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="finance-details">
                <h4>Total Revenue</h4>
                <p class="finance-value">$<?php echo number_format($revenue, 2); ?></p>
                <span class="finance-change positive">
                    <i class="fas fa-arrow-up"></i> This month
                </span>
            </div>
        </div>
        <div class="finance-card expenses-card">
            <div class="finance-icon expenses">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="finance-details">
                <h4>Total Expenses</h4>
                <p class="finance-value">$<?php echo number_format($expenses, 2); ?></p>
                <span class="finance-change">
                    <i class="fas fa-minus"></i> This month
                </span>
            </div>
        </div>
        <div class="finance-card profit-card">
            <div class="finance-icon profit">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="finance-details">
                <h4>Net Profit</h4>
                <p class="finance-value <?php echo $net_profit >= 0 ? 'text-success' : 'text-danger'; ?>">$<?php echo number_format($net_profit, 2); ?></p>
                <span class="finance-change <?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                    <i class="fas fa-<?php echo $net_profit >= 0 ? 'arrow-up' : 'arrow-down'; ?>"></i>
                    <?php echo $net_profit >= 0 ? 'Profit' : 'Loss'; ?>
                </span>
            </div>
        </div>
        <div class="finance-card outstanding-card">
            <div class="finance-icon outstanding">
                <i class="fas fa-clock"></i>
            </div>
            <div class="finance-details">
                <h4>Outstanding</h4>
                <p class="finance-value">$<?php echo number_format($outstandingData['total'] ?? 0, 2); ?></p>
                <span class="finance-change warning">
                    <i class="fas fa-exclamation-circle"></i> <?php echo $outstandingData['count'] ?? 0; ?> pending
                </span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card quick-actions-card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <button class="quick-action-btn" data-action="create-invoice" data-page="billing_dashboard">
                    <div class="quick-action-icon invoice">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <span>Create Invoice</span>
                    <small>Bill your clients</small>
                </button>
                <button class="quick-action-btn" data-action="record-payment" data-page="billing_dashboard">
                    <div class="quick-action-icon payment">
                        <i class="fas fa-money-check"></i>
                    </div>
                    <span>Record Payment</span>
                    <small>Log a transaction</small>
                </button>
                <button class="quick-action-btn" data-action="add-expense" data-page="expenses">
                    <div class="quick-action-icon expense">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <span>Add Expense</span>
                    <small>Track spending</small>
                </button>
                <button class="quick-action-btn" data-action="generate-report" data-page="reports">
                    <div class="quick-action-icon report">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <span>Generate Report</span>
                    <small>Financial insights</small>
                </button>
                <button class="quick-action-btn" data-action="issue-credit" data-page="credits_refunds">
                    <div class="quick-action-icon credit">
                        <i class="fas fa-undo-alt"></i>
                    </div>
                    <span>Issue Credit</span>
                    <small>Credits & refunds</small>
                </button>
                <button class="quick-action-btn" data-action="view-products" data-page="products">
                    <div class="quick-action-icon products">
                        <i class="fas fa-box-open"></i>
                    </div>
                    <span>Products</span>
                    <small>Pricing & packages</small>
                </button>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            <button class="btn btn-secondary" data-action="view-all" data-page="billing_dashboard"><i class="fas fa-list"></i> View All</button>
        </div>
        <div class="card-body">
            <?php if (count($transactions) > 0): ?>
                <div class="transactions-list">
                    <?php foreach ($transactions as $trans): ?>
                        <div class="transaction-item <?php echo $trans['type']; ?>">
                            <div class="transaction-icon">
                                <i class="fas fa-<?php echo $trans['type'] === 'payment' ? 'arrow-down' : 'arrow-up'; ?>"></i>
                            </div>
                            <div class="transaction-details">
                                <h4>
                                    <?php 
                                    if ($trans['type'] === 'payment') {
                                        echo 'Payment - ' . htmlspecialchars($trans['first_name'] ?? '') . ' ' . htmlspecialchars($trans['last_name'] ?? '');
                                    } else {
                                        echo 'Expense - ' . htmlspecialchars($trans['description'] ?? 'Expense');
                                    }
                                    ?>
                                </h4>
                                <span class="transaction-date">
                                    <?php echo date('M d, Y \a\t g:i A', strtotime($trans['trans_date'])); ?>
                                </span>
                            </div>
                            <div class="transaction-amount <?php echo $trans['type'] === 'payment' ? 'positive' : 'negative'; ?>">
                                <?php echo $trans['type'] === 'payment' ? '+' : '-'; ?>$<?php echo number_format($trans['amount'], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="placeholder-text">No recent transactions</p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Revenue Chart -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-area"></i> Revenue Overview</h3>
            <select class="form-input" style="width: auto;" id="revenueTimeframe" onchange="updateRevenueChart(this.value)">
                <option value="7">1 Week</option>
                <option value="30" selected>1 Month</option>
                <option value="90">This Quarter</option>
                <option value="180">6 Months</option>
                <option value="365">1 Year</option>
            </select>
        </div>
        <div class="card-body">
            <canvas id="revenueChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- Load Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Prepare chart data from PHP
    const chartData = <?php echo json_encode($revenueChartData); ?>;
    
    // Create labels and data arrays
    const labels = [];
    const data = [];
    
    // Fill in last 30 days
    for (let i = 29; i >= 0; i--) {
        const date = new Date();
        date.setDate(date.getDate() - i);
        const dateStr = date.toISOString().split('T')[0];
        labels.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
        
        // Find revenue for this date
        const dayData = chartData.find(d => d.date === dateStr);
        data.push(dayData ? parseFloat(dayData.daily_revenue) : 0);
    }
    
    // Create chart
    const ctx = document.getElementById('revenueChart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Daily Revenue',
                    data: data,
                    borderColor: '#6B46C1',
                    backgroundColor: 'rgba(107, 70, 193, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#6B46C1',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        padding: 12,
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#6B46C1',
                        borderWidth: 1,
                        displayColors: false,
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: $' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#8B92A7',
                            callback: function(value) {
                                return '$' + value.toFixed(0);
                            }
                        },
                        grid: {
                            color: 'rgba(255, 255, 255, 0.05)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#8B92A7',
                            maxRotation: 45,
                            minRotation: 45
                        },
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });
    }
});

function updateRevenueChart(days) {
    // This would typically require server-side data fetch
    // For now, just reload the page with parameter
    window.location.href = '?page=accounting_dashboard&days=' + days;
}
</script>

<style>
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

.accounting-content {
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

/* Quick Actions Enhanced */
.quick-actions-card .card-body { padding: 20px; }

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 20px 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-white);
    font-family: 'Inter', sans-serif;
    text-align: center;
    min-height: 130px;
    overflow: hidden;
}

.quick-action-btn:hover {
    background: rgba(107, 70, 193, 0.15);
    border-color: var(--primary);
    transform: translateY(-3px);
    box-shadow: 0 8px 16px rgba(107, 70, 193, 0.2);
}

.quick-action-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.quick-action-icon.invoice { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }
.quick-action-icon.payment { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.quick-action-icon.expense { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.quick-action-icon.report { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.quick-action-icon.credit { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.quick-action-icon.products { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }

.quick-action-btn span {
    font-size: 13px;
    font-weight: 700;
    color: var(--text-white);
    line-height: 1.2;
    word-wrap: break-word;
}

.quick-action-btn small {
    font-size: 10px;
    color: var(--text-dim);
    font-weight: 500;
    line-height: 1.2;
}

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

.placeholder-text i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 16px;
    display: block;
}

@media (max-width: 992px) {
    .financial-summary {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .financial-summary {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .finance-card {
        padding: 20px;
    }
    
    .finance-value {
        font-size: 26px;
    }
}

@media (max-width: 480px) {
    .quick-actions-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Handle quick action button clicks
document.querySelectorAll('.quick-action-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var action = this.getAttribute('data-action');
        var page = this.getAttribute('data-page');
        
        if (page) {
            var url = 'dashboard.php?page=' + page;
            
            // Add action-specific parameters
            switch(action) {
                case 'create-invoice':
                    url += '&action=create';
                    break;
                case 'record-payment':
                    url += '&action=record';
                    break;
                case 'add-expense':
                    url += '&action=add';
                    break;
                case 'generate-report':
                    url += '&action=generate';
                    break;
                case 'issue-credit':
                    url += '&action=issue';
                    break;
            }
            
            window.location.href = url;
        }
    });
});

// Handle view-all button clicks
document.querySelectorAll('[data-action="view-all"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var page = this.getAttribute('data-page');
        if (page) {
            window.location.href = 'dashboard.php?page=' + page;
        }
    });
});
</script>
