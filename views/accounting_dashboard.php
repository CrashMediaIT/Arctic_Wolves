<!-- Accounting Dashboard View -->
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
                <p class="finance-value">$125,450</p>
                <span class="finance-change positive"><i class="fas fa-arrow-up"></i> 12% vs last month</span>
            </div>
        </div>
        <div class="finance-card">
            <div class="finance-icon expenses">
                <i class="fas fa-receipt"></i>
            </div>
            <div class="finance-details">
                <h4>Total Expenses</h4>
                <p class="finance-value">$42,320</p>
                <span class="finance-change negative"><i class="fas fa-arrow-up"></i> 5% vs last month</span>
            </div>
        </div>
        <div class="finance-card">
            <div class="finance-icon profit">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="finance-details">
                <h4>Net Profit</h4>
                <p class="finance-value">$83,130</p>
                <span class="finance-change positive"><i class="fas fa-arrow-up"></i> 18% vs last month</span>
            </div>
        </div>
        <div class="finance-card">
            <div class="finance-icon outstanding">
                <i class="fas fa-clock"></i>
            </div>
            <div class="finance-details">
                <h4>Outstanding</h4>
                <p class="finance-value">$8,950</p>
                <span class="finance-change">12 invoices</span>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        </div>
        <div class="card-body">
            <div class="quick-actions-grid">
                <button class="quick-action-btn">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span>Create Invoice</span>
                </button>
                <button class="quick-action-btn">
                    <i class="fas fa-money-check"></i>
                    <span>Record Payment</span>
                </button>
                <button class="quick-action-btn">
                    <i class="fas fa-receipt"></i>
                    <span>Add Expense</span>
                </button>
                <button class="quick-action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <span>Generate Report</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            <button class="btn-secondary">View All</button>
        </div>
        <div class="card-body">
            <div class="transactions-list">
                <div class="transaction-item income">
                    <div class="transaction-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="transaction-details">
                        <h4>Session Payment - John Smith</h4>
                        <span class="transaction-date">Jan 15, 2024 at 3:42 PM</span>
                    </div>
                    <div class="transaction-amount positive">+$75.00</div>
                </div>
                <div class="transaction-item expense">
                    <div class="transaction-icon">
                        <i class="fas fa-arrow-up"></i>
                    </div>
                    <div class="transaction-details">
                        <h4>Ice Time Rental</h4>
                        <span class="transaction-date">Jan 15, 2024 at 10:00 AM</span>
                    </div>
                    <div class="transaction-amount negative">-$250.00</div>
                </div>
                <div class="transaction-item income">
                    <div class="transaction-icon">
                        <i class="fas fa-arrow-down"></i>
                    </div>
                    <div class="transaction-details">
                        <h4>Package Purchase - Sarah Johnson</h4>
                        <span class="transaction-date">Jan 14, 2024 at 5:15 PM</span>
                    </div>
                    <div class="transaction-amount positive">+$549.00</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Revenue Chart -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-chart-area"></i> Revenue Overview</h3>
            <select class="form-input-small">
                <option>Last 7 Days</option>
                <option>Last 30 Days</option>
                <option>Last 90 Days</option>
                <option>This Year</option>
            </select>
        </div>
        <div class="card-body">
            <div class="chart-placeholder">
                <i class="fas fa-chart-area"></i>
                <p>Revenue chart will be displayed here</p>
            </div>
        </div>
    </div>
</div>

<style>
.financial-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.finance-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
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
    background: linear-gradient(135deg, var(--neon), var(--accent));
}

.finance-icon.outstanding {
    background: linear-gradient(135deg, #f59e0b, #d97706);
}

.finance-details h4 {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 8px;
}

.finance-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 5px;
}

.finance-change {
    font-size: 12px;
    color: var(--text-dim);
}

.finance-change.positive {
    color: #10b981;
}

.finance-change.negative {
    color: #ef4444;
}

.finance-change i {
    font-size: 10px;
}

.quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
}

.quick-action-btn {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    transition: all 0.3s;
    color: var(--text-white);
}

.quick-action-btn:hover {
    border-color: var(--neon);
    background: rgba(255, 77, 0, 0.05);
}

.quick-action-btn i {
    font-size: 32px;
    color: var(--neon);
}

.quick-action-btn span {
    font-size: 14px;
    font-weight: 700;
    text-align: center;
}

.transactions-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.transaction-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.transaction-item:hover {
    border-color: var(--neon);
}

.transaction-icon {
    width: 45px;
    height: 45px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}

.transaction-item.income .transaction-icon {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.transaction-item.expense .transaction-icon {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.transaction-details {
    flex: 1;
}

.transaction-details h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 4px;
}

.transaction-date {
    font-size: 12px;
    color: var(--text-dim);
}

.transaction-amount {
    font-size: 18px;
    font-weight: 900;
}

.transaction-amount.positive {
    color: #10b981;
}

.transaction-amount.negative {
    color: #ef4444;
}

.chart-placeholder {
    min-height: 300px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: var(--bg-main);
    border: 1px dashed var(--border);
    border-radius: 8px;
}

.chart-placeholder i {
    font-size: 48px;
    color: var(--neon);
    opacity: 0.3;
    margin-bottom: 15px;
}

.chart-placeholder p {
    font-size: 14px;
    color: var(--text-dim);
}
</style>
