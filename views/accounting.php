<?php
// views/accounting.php - Main accounting dashboard
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

// Get current year or selected year
$selected_year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
$start_date = "$selected_year-01-01";
$end_date = "$selected_year-12-31";

// Calculate income (from bookings)
$income_stmt = $pdo->prepare("
    SELECT 
        SUM(original_price) as subtotal,
        SUM(tax_amount) as tax,
        SUM(amount_paid) as total,
        COUNT(*) as booking_count
    FROM bookings 
    WHERE status = 'paid' AND created_at BETWEEN ? AND ?
");
$income_stmt->execute([$start_date, $end_date . ' 23:59:59']);
$income = $income_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate expenses
$expense_stmt = $pdo->prepare("
    SELECT 
        SUM(amount) as subtotal,
        SUM(tax_amount) as tax,
        SUM(total_amount) as total,
        COUNT(*) as expense_count
    FROM expenses 
    WHERE expense_date BETWEEN ? AND ?
");
$expense_stmt->execute([$start_date, $end_date]);
$expenses = $expense_stmt->fetch(PDO::FETCH_ASSOC);

// Calculate profit
$profit = ($income['total'] ?? 0) - ($expenses['total'] ?? 0);
$profit_margin = $income['total'] > 0 ? ($profit / $income['total']) * 100 : 0;

// Monthly breakdown
$monthly_stmt = $pdo->prepare("
    SELECT 
        MONTH(created_at) as month,
        SUM(original_price) as income_subtotal,
        SUM(amount_paid) as income_total
    FROM bookings 
    WHERE status = 'paid' AND YEAR(created_at) = ?
    GROUP BY MONTH(created_at)
    ORDER BY month
");
$monthly_stmt->execute([$selected_year]);
$monthly_income = $monthly_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$monthly_expense_stmt = $pdo->prepare("
    SELECT 
        MONTH(expense_date) as month,
        SUM(total_amount) as expense_total
    FROM expenses 
    WHERE YEAR(expense_date) = ?
    GROUP BY MONTH(expense_date)
    ORDER BY month
");
$monthly_expense_stmt->execute([$selected_year]);
$monthly_expenses = $monthly_expense_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Recent transactions
$recent_income = $pdo->prepare("
    SELECT b.*, s.title as session_title, u.first_name, u.last_name, p.name as package_name
    FROM bookings b
    LEFT JOIN sessions s ON b.session_id = s.id
    LEFT JOIN packages p ON b.package_id = p.id
    LEFT JOIN users u ON b.booked_for_user_id = u.id
    WHERE b.status = 'paid' AND b.created_at BETWEEN ? AND ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$recent_income->execute([$start_date, $end_date . ' 23:59:59']);
$recent_transactions = $recent_income->fetchAll(PDO::FETCH_ASSOC);

$recent_expenses = $pdo->prepare("
    SELECT e.*, ec.name as category_name, u.first_name as created_by_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    JOIN users u ON e.created_by = u.id
    WHERE e.expense_date BETWEEN ? AND ?
    ORDER BY e.expense_date DESC
    LIMIT 10
");
$recent_expenses->execute([$start_date, $end_date]);
$recent_expense_list = $recent_expenses->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="accounting-dashboard">
    <div class="page-header">
        <div>
            <h2><i class="fas fa-chart-line"></i> Accounting Dashboard</h2>
            <p class="subtitle">Financial overview for <?php echo $selected_year; ?></p>
        </div>
        <div class="header-actions">
            <select id="yearSelector" onchange="changeYear(this.value)" class="year-select">
                <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y == $selected_year ? 'selected' : ''; ?>>
                        <?php echo $y; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="summary-cards">
        <div class="summary-card income">
            <div class="card-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="card-content">
                <div class="card-label">Total Income</div>
                <div class="card-value">$<?php echo number_format($income['total'] ?? 0, 2); ?></div>
                <div class="card-details">
                    <small>Subtotal: $<?php echo number_format($income['subtotal'] ?? 0, 2); ?></small>
                    <small>Tax: $<?php echo number_format($income['tax'] ?? 0, 2); ?></small>
                    <small><?php echo $income['booking_count'] ?? 0; ?> bookings</small>
                </div>
            </div>
        </div>

        <div class="summary-card expense">
            <div class="card-icon">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="card-content">
                <div class="card-label">Total Expenses</div>
                <div class="card-value">$<?php echo number_format($expenses['total'] ?? 0, 2); ?></div>
                <div class="card-details">
                    <small>Subtotal: $<?php echo number_format($expenses['subtotal'] ?? 0, 2); ?></small>
                    <small>Tax: $<?php echo number_format($expenses['tax'] ?? 0, 2); ?></small>
                    <small><?php echo $expenses['expense_count'] ?? 0; ?> expenses</small>
                </div>
            </div>
        </div>

        <div class="summary-card profit <?php echo $profit >= 0 ? 'positive' : 'negative'; ?>">
            <div class="card-icon">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="card-content">
                <div class="card-label">Net Profit</div>
                <div class="card-value">$<?php echo number_format($profit, 2); ?></div>
                <div class="card-details">
                    <small>Margin: <?php echo number_format($profit_margin, 1); ?>%</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <a href="?page=reports_income" class="action-btn">
            <i class="fas fa-file-invoice-dollar"></i> Income Reports
        </a>
        <a href="?page=reports_athlete" class="action-btn">
            <i class="fas fa-user-tag"></i> Athlete Billing
        </a>
        <a href="?page=accounts_payable" class="action-btn">
            <i class="fas fa-upload"></i> Add Expense
        </a>
        <a href="?page=expense_categories" class="action-btn">
            <i class="fas fa-tags"></i> Expense Categories
        </a>
    </div>

    <!-- Monthly Chart -->
    <div class="chart-container">
        <h3>Monthly Overview</h3>
        <canvas id="monthlyChart"></canvas>
    </div>

    <!-- Recent Transactions -->
    <div class="recent-section">
        <div class="recent-column">
            <h3><i class="fas fa-arrow-down"></i> Recent Income</h3>
            <div class="transaction-list">
                <?php foreach ($recent_transactions as $txn): ?>
                <div class="transaction-item income">
                    <div class="txn-info">
                        <strong>
                            <?php echo htmlspecialchars($txn['session_title'] ?? $txn['package_name'] ?? 'Payment'); ?>
                        </strong>
                        <small>
                            <?php echo htmlspecialchars($txn['first_name'] . ' ' . $txn['last_name']); ?> • 
                            <?php echo date('M j, Y', strtotime($txn['created_at'])); ?>
                        </small>
                    </div>
                    <div class="txn-amount">
                        $<?php echo number_format($txn['amount_paid'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recent_transactions)): ?>
                    <p class="empty-state">No recent income</p>
                <?php endif; ?>
            </div>
        </div>

        <div class="recent-column">
            <h3><i class="fas fa-arrow-up"></i> Recent Expenses</h3>
            <div class="transaction-list">
                <?php foreach ($recent_expense_list as $exp): ?>
                <div class="transaction-item expense">
                    <div class="txn-info">
                        <strong><?php echo htmlspecialchars($exp['vendor_name']); ?></strong>
                        <small>
                            <?php echo htmlspecialchars($exp['category_name']); ?> • 
                            <?php echo date('M j, Y', strtotime($exp['expense_date'])); ?>
                        </small>
                    </div>
                    <div class="txn-amount">
                        $<?php echo number_format($exp['total_amount'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (empty($recent_expense_list)): ?>
                    <p class="empty-state">No recent expenses</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.accounting-dashboard {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.page-header h2 {
    margin: 0;
    color: #fff;
}

.subtitle {
    color: #94a3b8;
    margin: 5px 0 0 0;
}

.year-select {
    padding: 8px 15px;
    background: #020305;
    border: 1px solid #334155;
    color: #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
}

.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
    border-radius: 12px;
    padding: 25px;
    display: flex;
    gap: 20px;
    border: 1px solid #334155;
}

.summary-card.income {
    border-left: 4px solid #10b981;
}

.summary-card.expense {
    border-left: 4px solid #ef4444;
}

.summary-card.profit.positive {
    border-left: 4px solid #3b82f6;
}

.summary-card.profit.negative {
    border-left: 4px solid #f59e0b;
}

.card-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    background: rgba(255, 77, 0, 0.1);
    color: var(--primary, #7000a4);
}

.card-content {
    flex: 1;
}

.card-label {
    color: #94a3b8;
    font-size: 14px;
    margin-bottom: 8px;
}

.card-value {
    font-size: 32px;
    font-weight: bold;
    color: #fff;
    margin-bottom: 10px;
}

.card-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.card-details small {
    color: #64748b;
    font-size: 12px;
}

.quick-actions {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 30px;
}

.action-btn {
    background: #1e293b;
    border: 1px solid #334155;
    color: #e2e8f0;
    padding: 15px 20px;
    border-radius: 8px;
    text-decoration: none;
    text-align: center;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}

.action-btn:hover {
    background: var(--primary, #7000a4);
    color: white;
    border-color: var(--primary, #7000a4);
}

.chart-container {
    background: #0a0f16;
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    border: 1px solid #334155;
}

.chart-container h3 {
    margin: 0 0 20px 0;
    color: #e2e8f0;
}

.recent-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 20px;
}

.recent-column {
    background: #0a0f16;
    border-radius: 12px;
    padding: 25px;
    border: 1px solid #334155;
}

.recent-column h3 {
    margin: 0 0 20px 0;
    color: #e2e8f0;
    font-size: 18px;
}

.transaction-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.transaction-item {
    display: flex;
    justify-content: space-between;
    padding: 15px;
    background: #020305;
    border-radius: 8px;
    border-left: 3px solid transparent;
}

.transaction-item.income {
    border-left-color: #10b981;
}

.transaction-item.expense {
    border-left-color: #ef4444;
}

.txn-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.txn-info strong {
    color: #e2e8f0;
}

.txn-info small {
    color: #64748b;
    font-size: 12px;
}

.txn-amount {
    font-size: 18px;
    font-weight: bold;
    color: #fff;
}

.empty-state {
    text-align: center;
    color: #64748b;
    padding: 40px;
}

@media (max-width: 768px) {
    .recent-section {
        grid-template-columns: 1fr;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function changeYear(year) {
    window.location.href = '?page=accounting&year=' + year;
}

// Monthly chart
const monthlyData = {
    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
    datasets: [
        {
            label: 'Income',
            data: [
                <?php for ($m = 1; $m <= 12; $m++) {
                    echo ($monthly_income[$m] ?? 0) . ',';
                } ?>
            ],
            backgroundColor: 'rgba(16, 185, 129, 0.2)',
            borderColor: 'rgba(16, 185, 129, 1)',
            borderWidth: 2
        },
        {
            label: 'Expenses',
            data: [
                <?php for ($m = 1; $m <= 12; $m++) {
                    echo ($monthly_expenses[$m] ?? 0) . ',';
                } ?>
            ],
            backgroundColor: 'rgba(239, 68, 68, 0.2)',
            borderColor: 'rgba(239, 68, 68, 1)',
            borderWidth: 2
        }
    ]
};

const ctx = document.getElementById('monthlyChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: monthlyData,
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                labels: { color: '#94a3b8' }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { color: '#94a3b8' },
                grid: { color: '#1e293b' }
            },
            x: {
                ticks: { color: '#94a3b8' },
                grid: { color: '#1e293b' }
            }
        }
    }
});
</script>
