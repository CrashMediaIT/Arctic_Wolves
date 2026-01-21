<!-- Accounting Reports View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-bar"></i> Reports
    </h1>
    <p class="page-description">Generate financial reports and analytics</p>
</div>

<div class="reports-content">
    <!-- Report Generator -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-file-alt"></i> Generate Report</h3>
        </div>
        <div class="card-body">
            <form class="report-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Report Type *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Report Type --</option>
                            <option>Revenue Summary</option>
                            <option>Expense Report</option>
                            <option>Profit & Loss</option>
                            <option>Tax Summary</option>
                            <option>Client Billing Summary</option>
                            <option>Coach Payments</option>
                            <option>Session Analytics</option>
                            <option>Package Sales</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Date Range *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Range --</option>
                            <option>Today</option>
                            <option>This Week</option>
                            <option>This Month</option>
                            <option>Last Month</option>
                            <option>This Quarter</option>
                            <option>Last Quarter</option>
                            <option>This Year</option>
                            <option>Last Year</option>
                            <option>Custom Range</option>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="customDateRange" style="display: none;">
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label>Format *</label>
                    <div class="format-options">
                        <label class="radio-option">
                            <input type="radio" name="format" value="pdf" checked>
                            <span><i class="fas fa-file-pdf"></i> PDF</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="format" value="excel">
                            <span><i class="fas fa-file-excel"></i> Excel</span>
                        </label>
                        <label class="radio-option">
                            <input type="radio" name="format" value="csv">
                            <span><i class="fas fa-file-csv"></i> CSV</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label>Additional Options</label>
                    <div class="checkbox-options">
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Include detailed breakdown</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Show charts and graphs</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox">
                            <span>Compare with previous period</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-chart-bar"></i> Generate Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pre-built Reports -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-folder-open"></i> Pre-built Reports</h3>
        </div>
        <div class="card-body">
            <div class="reports-grid">
                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h4>Monthly Revenue</h4>
                    <p>Comprehensive revenue breakdown by source and category</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Client Summary</h4>
                    <p>Client billing history and outstanding balances</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h4>Profit & Loss</h4>
                    <p>Complete P&L statement with comparisons</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h4>Tax Report</h4>
                    <p>Tax-ready financial summary and documentation</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4>Session Analytics</h4>
                    <p>Session attendance, revenue, and trends</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                </div>

                <div class="report-card">
                    <div class="report-icon">
                        <i class="fas fa-box"></i>
                    </div>
                    <h4>Package Performance</h4>
                    <p>Package sales analysis and utilization rates</p>
                    <button class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Reports</h3>
        </div>
        <div class="card-body">
            <div class="recent-reports-list">
                <div class="report-item">
                    <div class="report-file-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="report-info">
                        <h4>Monthly Revenue Summary - December 2023</h4>
                        <span class="report-meta">Generated on Jan 5, 2024 • 245 KB</span>
                    </div>
                    <div class="report-actions">
                        <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                        <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="report-item">
                    <div class="report-file-icon excel">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="report-info">
                        <h4>Client Billing Summary - Q4 2023</h4>
                        <span class="report-meta">Generated on Dec 28, 2023 • 128 KB</span>
                    </div>
                    <div class="report-actions">
                        <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                        <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.format-options,
.checkbox-options {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
}

.radio-option,
.checkbox-option {
    display: flex;
    align-items: center;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
    padding: 12px 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.radio-option:hover,
.checkbox-option:hover {
    border-color: var(--neon);
}

.radio-option input,
.checkbox-option input {
    margin-right: 10px;
}

.radio-option span,
.checkbox-option span {
    font-size: 14px;
    color: var(--text-white);
}

.radio-option i {
    margin-right: 8px;
    color: var(--neon);
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.report-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
    text-align: center;
    transition: all 0.3s;
}

.report-card:hover {
    border-color: var(--neon);
    transform: translateY(-3px);
}

.report-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 24px;
    color: #fff;
}

.report-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.report-card p {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.5;
    margin-bottom: 20px;
}

.recent-reports-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.report-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.report-item:hover {
    border-color: var(--neon);
}

.report-file-icon {
    width: 50px;
    height: 50px;
    background: rgba(239, 68, 68, 0.1);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #ef4444;
    flex-shrink: 0;
}

.report-file-icon.excel {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.report-info {
    flex: 1;
}

.report-info h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.report-meta {
    font-size: 12px;
    color: var(--text-dim);
}

.report-actions {
    display: flex;
    gap: 5px;
}
</style>
