<!-- Accounting Billing View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-file-invoice-dollar"></i> Billing & Invoices
    </h1>
    <p class="page-description">Manage invoices and billing history</p>
</div>

<div class="billing-content">
    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search invoices...">
            <select class="form-input-small">
                <option>All Status</option>
                <option>Paid</option>
                <option>Pending</option>
                <option>Overdue</option>
                <option>Draft</option>
            </select>
            <select class="form-input-small">
                <option>This Month</option>
                <option>Last Month</option>
                <option>Last 3 Months</option>
                <option>This Year</option>
                <option>Custom Range</option>
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-plus"></i> Create Invoice</button>
    </div>

    <!-- Invoices Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Invoices</h3>
            <div class="header-actions">
                <button class="btn-secondary"><i class="fas fa-file-export"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Date</th>
                            <th>Due Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>INV-2024-001</strong></td>
                            <td>
                                <div class="client-info">
                                    <div class="client-avatar">JS</div>
                                    <span>John Smith</span>
                                </div>
                            </td>
                            <td>Jan 15, 2024</td>
                            <td>Jan 30, 2024</td>
                            <td><strong>$549.00</strong></td>
                            <td><span class="status-badge paid">Paid</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                                    <button class="btn-icon" title="Email"><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>INV-2024-002</strong></td>
                            <td>
                                <div class="client-info">
                                    <div class="client-avatar">SJ</div>
                                    <span>Sarah Johnson</span>
                                </div>
                            </td>
                            <td>Jan 14, 2024</td>
                            <td>Jan 29, 2024</td>
                            <td><strong>$299.00</strong></td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                                    <button class="btn-icon" title="Email"><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>INV-2024-003</strong></td>
                            <td>
                                <div class="client-info">
                                    <div class="client-avatar">MW</div>
                                    <span>Mike Williams</span>
                                </div>
                            </td>
                            <td>Jan 10, 2024</td>
                            <td>Jan 25, 2024</td>
                            <td><strong>$150.00</strong></td>
                            <td><span class="status-badge overdue">Overdue</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                    <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                                    <button class="btn-icon" title="Send Reminder"><i class="fas fa-bell"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payment History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-credit-card"></i> Recent Payments</h3>
        </div>
        <div class="card-body">
            <div class="payments-list">
                <div class="payment-item">
                    <div class="payment-icon">
                        <i class="fas fa-credit-card"></i>
                    </div>
                    <div class="payment-details">
                        <h4>Payment Received - INV-2024-001</h4>
                        <p>John Smith • Visa ending in 4242</p>
                        <span class="payment-date">Jan 15, 2024 at 3:42 PM</span>
                    </div>
                    <div class="payment-amount">$549.00</div>
                </div>
                <div class="payment-item">
                    <div class="payment-icon">
                        <i class="fas fa-money-check"></i>
                    </div>
                    <div class="payment-details">
                        <h4>Payment Received - INV-2023-152</h4>
                        <p>Emily Davis • Bank Transfer</p>
                        <span class="payment-date">Jan 13, 2024 at 11:20 AM</span>
                    </div>
                    <div class="payment-amount">$299.00</div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.header-actions {
    display: flex;
    gap: 10px;
}

.table-container {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead {
    background: var(--bg-main);
}

.data-table th {
    padding: 15px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.data-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-white);
}

.data-table tbody tr {
    transition: all 0.3s;
}

.data-table tbody tr:hover {
    background: var(--bg-main);
}

.client-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.client-avatar {
    width: 35px;
    height: 35px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 900;
    color: #fff;
}

.status-badge.paid {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.pending {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.overdue {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-badge.draft {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-dim);
}

.payments-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.payment-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.payment-icon {
    width: 50px;
    height: 50px;
    background: rgba(16, 185, 129, 0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #10b981;
    flex-shrink: 0;
}

.payment-details {
    flex: 1;
}

.payment-details h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 4px;
}

.payment-details p {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 4px;
}

.payment-date {
    font-size: 12px;
    color: var(--text-dim);
}

.payment-amount {
    font-size: 20px;
    font-weight: 900;
    color: #10b981;
}
</style>
