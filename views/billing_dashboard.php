<?php
// views/billing_dashboard.php - Quick financial overview
require_once __DIR__ . '/../security.php';

if ($_SESSION['user_role'] !== 'admin') {
    die('Access denied');
}

// Get date range
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// Get current month income
$income_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_bookings,
        SUM(amount_paid) as total_income
    FROM bookings
    WHERE status = 'paid'
    AND DATE(created_at) BETWEEN ? AND ?
");
$income_stmt->execute([$start_date, $end_date]);
$income_data = $income_stmt->fetch();

// Get current month expenses
$expenses_stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_expenses,
        SUM(total_amount) as total_expenses_amount
    FROM expenses
    WHERE DATE(expense_date) BETWEEN ? AND ?
");
$expenses_stmt->execute([$start_date, $end_date]);
$expenses_data = $expenses_stmt->fetch();

// Calculate net profit
$total_income = floatval($income_data['total_income'] ?? 0);
$total_expenses = floatval($expenses_data['total_expenses_amount'] ?? 0);
$net_profit = $total_income - $total_expenses;

// Get outstanding refunds
$refunds_stmt = $pdo->query("
    SELECT COUNT(*) as pending_refunds, SUM(refund_amount) as pending_amount
    FROM refunds
    WHERE refund_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$refunds_data = $refunds_stmt->fetch();

// Get last 6 months data for chart
$chart_data_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        SUM(amount_paid) as income
    FROM bookings
    WHERE status = 'paid'
    AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month
");
$income_by_month = $chart_data_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$chart_expenses_stmt = $pdo->query("
    SELECT 
        DATE_FORMAT(expense_date, '%Y-%m') as month,
        SUM(total_amount) as expenses
    FROM expenses
    WHERE expense_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
    ORDER BY month
");
$expenses_by_month = $chart_expenses_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get expense breakdown by category
$category_stmt = $pdo->prepare("
    SELECT ec.name as category_name, SUM(e.total_amount) as total
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE DATE(e.expense_date) BETWEEN ? AND ?
    GROUP BY e.category_id
    ORDER BY total DESC
");
$category_stmt->execute([$start_date, $end_date]);
$expense_categories = $category_stmt->fetchAll();

// Get recent income
$recent_income_stmt = $pdo->prepare("
    SELECT b.*, u.first_name, u.last_name, s.session_name
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    LEFT JOIN sessions s ON b.session_id = s.id
    WHERE b.status = 'paid'
    AND DATE(b.created_at) BETWEEN ? AND ?
    ORDER BY b.created_at DESC
    LIMIT 10
");
$recent_income_stmt->execute([$start_date, $end_date]);
$recent_income = $recent_income_stmt->fetchAll();

// Get recent expenses
$recent_expenses_stmt = $pdo->prepare("
    SELECT e.*, ec.name as category_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    WHERE DATE(e.expense_date) BETWEEN ? AND ?
    ORDER BY e.expense_date DESC
    LIMIT 10
");
$recent_expenses_stmt->execute([$start_date, $end_date]);
$recent_expenses = $recent_expenses_stmt->fetchAll();

// Get pending refunds
$pending_refunds_stmt = $pdo->query("
    SELECT r.*, u.first_name, u.last_name, s.session_name
    FROM refunds r
    JOIN users u ON r.user_id = u.id
    LEFT JOIN bookings b ON r.booking_id = b.id
    LEFT JOIN sessions s ON b.session_id = s.id
    WHERE r.refund_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ORDER BY r.refund_date DESC
    LIMIT 10
");
$pending_refunds = $pending_refunds_stmt->fetchAll();
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<style>
    .billing-container {
        max-width: 1600px;
        margin: 0 auto;
    }
    
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 25px;
        margin: 30px 0;
    }
    
    .summary-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 25px;
        position: relative;
        overflow: hidden;
    }
    
    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 4px;
        height: 100%;
        background: var(--primary);
    }
    
    .summary-label {
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.9rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .summary-label i {
        color: var(--primary);
    }
    
    .summary-value {
        font-size: 2.5rem;
        font-weight: 900;
        color: white;
        line-height: 1;
        margin-bottom: 10px;
    }
    
    .summary-value.positive {
        color: #10b981;
    }
    
    .summary-value.negative {
        color: #ef4444;
    }
    
    .summary-change {
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.6);
    }
    
    .chart-grid {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 25px;
        margin: 30px 0;
    }
    
    .chart-card {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 25px;
    }
    
    .chart-card h3 {
        color: white;
        font-size: 1.2rem;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .chart-card h3 i {
        color: var(--primary);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
        background: rgba(255, 255, 255, 0.03);
        border-radius: 12px;
        overflow: hidden;
    }
    
    .data-table th {
        background: rgba(255, 77, 0, 0.1);
        color: var(--primary);
        padding: 12px;
        text-align: left;
        font-weight: 600;
        border-bottom: 2px solid var(--primary);
        font-size: 0.9rem;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        color: rgba(255, 255, 255, 0.9);
        font-size: 0.9rem;
    }
    
    .data-table tr:hover {
        background: rgba(255, 255, 255, 0.03);
    }
    
    .date-filter {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: end;
        gap: 15px;
    }
    
    .form-group {
        flex: 1;
    }
    
    .form-group label {
        display: block;
        color: rgba(255, 255, 255, 0.8);
        font-size: 0.9rem;
        margin-bottom: 8px;
        font-weight: 600;
    }
    
    .form-group input {
        width: 100%;
        padding: 12px;
        background: rgba(0, 0, 0, 0.3);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 6px;
        color: white;
        font-size: 1rem;
    }
    
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        font-size: 0.95rem;
    }
    
    .btn-primary {
        background: var(--primary);
        color: white;
    }
    
    .btn-primary:hover {
        background: #e64500;
    }
    
    .btn-secondary {
        background: rgba(255, 255, 255, 0.1);
        color: white;
    }
    
    .btn-secondary:hover {
        background: rgba(255, 255, 255, 0.15);
    }
    
    @media (max-width: 1200px) {
        .chart-grid {
            grid-template-columns: 1fr;
        }
    }
    
    @media (max-width: 768px) {
        .summary-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="dash-content billing-container">
    <div class="dash-header">
        <h2><i class="fas fa-chart-pie"></i> Billing Dashboard</h2>
        <p style="color: rgba(255, 255, 255, 0.6);">Financial overview and analytics</p>
    </div>
    
    <!-- Date Range Filter -->
    <form method="GET" class="date-filter">
        <input type="hidden" name="page" value="billing_dashboard">
        <div class="form-group">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" required>
        </div>
        <div class="form-group">
            <label>End Date</label>
            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" required>
        </div>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Apply
        </button>
        <button type="button" class="btn btn-secondary" onclick="exportAllData()">
            <i class="fas fa-download"></i> Export
        </button>
    </form>
    
    <!-- Summary Cards -->
    <div class="summary-grid">
        <div class="summary-card">
            <div class="summary-label">
                <i class="fas fa-arrow-trend-up"></i> Total Income
            </div>
            <div class="summary-value positive">
                $<?= number_format($total_income, 2) ?>
            </div>
            <div class="summary-change">
                <?= $income_data['total_bookings'] ?> bookings
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-label">
                <i class="fas fa-arrow-trend-down"></i> Total Expenses
            </div>
            <div class="summary-value negative">
                $<?= number_format($total_expenses, 2) ?>
            </div>
            <div class="summary-change">
                <?= $expenses_data['total_expenses'] ?> expenses
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-label">
                <i class="fas fa-chart-line"></i> Net Profit
            </div>
            <div class="summary-value <?= $net_profit >= 0 ? 'positive' : 'negative' ?>">
                $<?= number_format($net_profit, 2) ?>
            </div>
            <div class="summary-change">
                <?= $net_profit >= 0 ? 'Profit' : 'Loss' ?>
            </div>
        </div>
        
        <div class="summary-card">
            <div class="summary-label">
                <i class="fas fa-undo"></i> Recent Refunds
            </div>
            <div class="summary-value">
                $<?= number_format(floatval($refunds_data['pending_amount'] ?? 0), 2) ?>
            </div>
            <div class="summary-change">
                <?= $refunds_data['pending_refunds'] ?> refunds (30 days)
            </div>
        </div>
    </div>
    
    <!-- Charts -->
    <div class="chart-grid">
        <div class="chart-card">
            <h3><i class="fas fa-chart-line"></i> Income vs Expenses (Last 6 Months)</h3>
            <canvas id="incomeExpensesChart" style="max-height: 300px;"></canvas>
        </div>
        
        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Expense Breakdown</h3>
            <canvas id="expenseCategoriesChart" style="max-height: 300px;"></canvas>
        </div>
    </div>
    
    <!-- Recent Income -->
    <div class="chart-card" style="margin-top: 25px;">
        <h3><i class="fas fa-dollar-sign"></i> Recent Income</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Session</th>
                        <th>Amount</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_income)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: rgba(255, 255, 255, 0.5); padding: 30px;">
                                No income records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_income as $income): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($income['created_at'])) ?></td>
                                <td><?= htmlspecialchars($income['first_name'] . ' ' . $income['last_name']) ?></td>
                                <td><?= htmlspecialchars($income['session_name'] ?? 'N/A') ?></td>
                                <td style="font-weight: 700; color: #10b981;">
                                    $<?= number_format($income['amount_paid'], 2) ?>
                                </td>
                                <td>
                                    <span style="padding: 4px 10px; background: #10b981; color: white; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                        <?= ucfirst($income['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Recent Expenses -->
    <div class="chart-card" style="margin-top: 25px;">
        <h3><i class="fas fa-receipt"></i> Recent Expenses</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Vendor</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_expenses)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; color: rgba(255, 255, 255, 0.5); padding: 30px;">
                                No expense records found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recent_expenses as $expense): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($expense['expense_date'])) ?></td>
                                <td><?= htmlspecialchars($expense['vendor_name']) ?></td>
                                <td><?= htmlspecialchars($expense['category_name']) ?></td>
                                <td style="font-weight: 700; color: #ef4444;">
                                    $<?= number_format($expense['total_amount'], 2) ?>
                                </td>
                                <td>
                                    <?php if ($expense['receipt_file']): ?>
                                        <a href="uploads/receipts/<?= htmlspecialchars($expense['receipt_file']) ?>" 
                                           target="_blank" 
                                           style="color: var(--primary); text-decoration: none;">
                                            <i class="fas fa-file"></i> View
                                        </a>
                                    <?php else: ?>
                                        <span style="color: rgba(255, 255, 255, 0.3);">N/A</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pending Refunds -->
    <?php if (!empty($pending_refunds)): ?>
    <div class="chart-card" style="margin-top: 25px;">
        <h3><i class="fas fa-undo"></i> Recent Refunds (Last 30 Days)</h3>
        <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Session</th>
                        <th>Amount</th>
                        <th>Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pending_refunds as $refund): ?>
                        <tr>
                            <td><?= date('M d, Y', strtotime($refund['refund_date'])) ?></td>
                            <td><?= htmlspecialchars($refund['first_name'] . ' ' . $refund['last_name']) ?></td>
                            <td><?= htmlspecialchars($refund['session_name'] ?? 'N/A') ?></td>
                            <td style="font-weight: 700; color: #f59e0b;">
                                $<?= number_format($refund['refund_amount'], 2) ?>
                            </td>
                            <td>
                                <span style="padding: 4px 10px; background: #3b82f6; color: white; border-radius: 12px; font-size: 0.8rem; font-weight: 600;">
                                    <?= ucfirst($refund['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
    
    <div style="margin-top: 30px; text-align: center; padding: 20px; background: rgba(255, 255, 255, 0.03); border-radius: 12px;">
        <p style="color: rgba(255, 255, 255, 0.6); margin-bottom: 15px;">
            View detailed reports
        </p>
        <div style="display: flex; gap: 15px; justify-content: center; flex-wrap: wrap;">
            <a href="?page=reports_income" class="btn btn-secondary">
                <i class="fas fa-file-invoice-dollar"></i> Income Reports
            </a>
            <a href="?page=accounts_payable" class="btn btn-secondary">
                <i class="fas fa-receipt"></i> All Expenses
            </a>
            <a href="?page=refunds" class="btn btn-secondary">
                <i class="fas fa-undo"></i> All Refunds
            </a>
        </div>
    </div>
</div>

<script>
// Prepare data for Income vs Expenses chart
const months = [];
const incomeData = [];
const expensesData = [];

// Get last 6 months
for (let i = 5; i >= 0; i--) {
    const d = new Date();
    d.setMonth(d.getMonth() - i);
    const monthKey = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    const monthLabel = d.toLocaleString('default', { month: 'short', year: 'numeric' });
    months.push(monthLabel);
    
    incomeData.push(<?= json_encode($income_by_month) ?>[monthKey] || 0);
    expensesData.push(<?= json_encode($expenses_by_month) ?>[monthKey] || 0);
}

// Income vs Expenses Chart
const incomeExpensesCtx = document.getElementById('incomeExpensesChart').getContext('2d');
new Chart(incomeExpensesCtx, {
    type: 'line',
    data: {
        labels: months,
        datasets: [
            {
                label: 'Income',
                data: incomeData,
                borderColor: '#10b981',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
                tension: 0.4,
                fill: true
            },
            {
                label: 'Expenses',
                data: expensesData,
                borderColor: '#ef4444',
                backgroundColor: 'rgba(239, 68, 68, 0.1)',
                tension: 0.4,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                labels: { color: 'rgba(255, 255, 255, 0.8)' }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    color: 'rgba(255, 255, 255, 0.6)',
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            },
            x: {
                ticks: { color: 'rgba(255, 255, 255, 0.6)' },
                grid: { color: 'rgba(255, 255, 255, 0.1)' }
            }
        }
    }
});

// Expense Categories Chart
const categoryLabels = <?= json_encode(array_column($expense_categories, 'category_name')) ?>;
const categoryValues = <?= json_encode(array_column($expense_categories, 'total')) ?>;

const expenseCategoriesCtx = document.getElementById('expenseCategoriesChart').getContext('2d');
new Chart(expenseCategoriesCtx, {
    type: 'doughnut',
    data: {
        labels: categoryLabels,
        datasets: [{
            data: categoryValues,
            backgroundColor: [
                '#7000a4',
                '#f59e0b',
                '#10b981',
                '#3b82f6',
                '#7000a4',
                '#ec4899'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom',
                labels: { 
                    color: 'rgba(255, 255, 255, 0.8)',
                    padding: 15
                }
            }
        }
    }
});

function exportAllData() {
    const startDate = '<?= $start_date ?>';
    const endDate = '<?= $end_date ?>';
    alert('Export functionality would download a comprehensive CSV with all financial data from ' + startDate + ' to ' + endDate);
}
</script>
