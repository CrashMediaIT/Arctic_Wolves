<!-- Accounting Dashboard View -->
<?php
// Fetch financial data from database
try {
    // Get total revenue (payments received)
    $stmt = $pdo->prepare("
        SELECT SUM(amount) as total_revenue
        FROM payments
        WHERE status = 'completed'
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
        WHERE p.status = 'completed'
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
    <p class="page-description">Financial overview and key metrics</p>
</div>

<div class="accounting-content">
    <!-- Financial Summary Cards -->
    <div class="financial-summary">
        <div class="finance-card">
            <div class="finance-icon revenue">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="finance-details">
                <h4>Total Revenue</h4>
                <p class="finance-value">$<?php echo number_format($revenue, 2); ?></p>
                <span class="finance-change">This month</span>
            </div>
        </div>
        <div class="finance-card">
            <div class="finance-icon expenses">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="finance-details">
                <h4>Total Expenses</h4>
                <p class="finance-value">$<?php echo number_format($expenses, 2); ?></p>
                <span class="finance-change">This month</span>
            </div>
        </div>
        <div class="finance-card">
            <div class="finance-icon profit">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="finance-details">
                <h4>Net Profit</h4>
                <p class="finance-value">$<?php echo number_format($net_profit, 2); ?></p>
                <span class="finance-change <?php echo $net_profit >= 0 ? 'positive' : 'negative'; ?>">
                    <?php echo $net_profit >= 0 ? 'Positive' : 'Negative'; ?>
                </span>
            </div>
        </div>
        <div class="finance-card">
            <div class="finance-icon outstanding">
                <i class="fas fa-clock"></i>
            </div>
            <div class="finance-details">
                <h4>Outstanding</h4>
                <p class="finance-value">$<?php echo number_format($outstandingData['total'] ?? 0, 2); ?></p>
                <span class="finance-change"><?php echo $outstandingData['count'] ?? 0; ?> invoices</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <button class="quick-action-btn" data-action="create-invoice" data-page="billing_dashboard">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Create Invoice</span>
                </button>
                <button class="quick-action-btn" data-action="record-payment" data-page="billing_dashboard">
                    <i class="fas fa-money-check"></i>
                    <span>Record Payment</span>
                </button>
                <button class="quick-action-btn" data-action="add-expense" data-page="expenses">
                    <i class="fas fa-receipt"></i>
                    <span>Add Expense</span>
                </button>
                <button class="quick-action-btn" data-action="generate-report" data-page="reports">
                    <i class="fas fa-chart-bar"></i>
                    <span>Generate Report</span>
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
.financial-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.finance-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
}

.finance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.finance-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0;
}

.finance-icon.revenue {
    background: linear-gradient(135deg, #10b981, #059669);
}

.finance-icon.expenses {
    background: linear-gradient(135deg, #ef4444, #dc2626);
}

.finance-icon.profit {
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
}

.finance-icon.outstanding {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.finance-details h4 {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

.finance-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 4px;
}

.finance-change {
    font-size: 12px;
    color: var(--text-dim);
}

.finance-change.positive {
    color: var(--success);
}

.finance-change.negative {
    color: var(--error);
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
}

.quick-action-btn {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    padding: 24px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: var(--text-white);
    font-family: 'Inter', sans-serif;
}

.quick-action-btn:hover {
    background: rgba(107, 70, 193, 0.1);
    border-color: var(--primary);
    transform: translateY(-2px);
}

.quick-action-btn i {
    font-size: 32px;
    color: var(--primary);
}

.quick-action-btn span {
    font-size: 14px;
    font-weight: 600;
}

.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: border-color 0.3s ease;
}

.transaction-item:hover {
    border-color: var(--primary);
}

.transaction-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.transaction-item.payment .transaction-icon {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
}

.transaction-item.expense .transaction-icon {
    background: rgba(239, 68, 68, 0.15);
    color: var(--error);
}

.transaction-details {
    flex: 1;
}

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
    font-size: 16px;
    font-weight: 700;
}

.transaction-amount.positive {
    color: var(--success);
}

.transaction-amount.negative {
    color: var(--error);
}

.chart-placeholder {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 60px 20px;
    background: var(--bg-main);
    border: 2px dashed var(--border);
    border-radius: 12px;
    color: var(--text-dim);
    text-align: center;
}

.chart-placeholder p {
    margin: 0;
    font-size: 14px;
}

@media (max-width: 768px) {
    .financial-summary {
        grid-template-columns: 1fr;
    }
    
    .quick-actions-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}
</style>
