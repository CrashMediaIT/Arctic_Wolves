<!-- Accounting Reports View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-bar"></i> Financial Reports
    </h1>
    <p class="page-description">Generate comprehensive financial reports and insights</p>
</div>

<div class="reports-content">
    <!-- Report Generator -->
    <div class="content-card report-generator-card">
        <div class="card-header">
            <h3><i class="fas fa-magic"></i> Report Generator</h3>
            <span class="header-badge">Custom Reports</span>
        </div>
        <div class="card-body">
            <form class="report-form" method="POST" action="process_reports.php">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="generate_report">
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-file-alt"></i> Report Type *</label>
                        <select name="report_type" class="form-input" required>
                            <option value="">-- Select Report Type --</option>
                            <option value="revenue_summary">Revenue Summary</option>
                            <option value="expense_report">Expense Report</option>
                            <option value="profit_loss">Profit & Loss</option>
                            <option value="tax_summary">Tax Summary</option>
                            <option value="client_billing">Client Billing Summary</option>
                            <option value="coach_payments">Coach Payments</option>
                            <option value="session_analytics">Session Analytics</option>
                            <option value="package_sales">Package Sales</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Date Range *</label>
                        <select name="date_range" class="form-input" required id="dateRangeSelect">
                            <option value="">-- Select Range --</option>
                            <option value="today">Today</option>
                            <option value="this_week">This Week</option>
                            <option value="this_month">This Month</option>
                            <option value="last_month">Last Month</option>
                            <option value="this_quarter">This Quarter</option>
                            <option value="last_quarter">Last Quarter</option>
                            <option value="this_year">This Year</option>
                            <option value="last_year">Last Year</option>
                            <option value="custom">Custom Range</option>
                        </select>
                    </div>
                </div>

                <div class="form-row custom-date-row" id="customDateRange" style="display: none;">
                    <div class="form-group">
                        <label class="form-label">From Date</label>
                        <input type="date" name="from_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date</label>
                        <input type="date" name="to_date" class="form-input">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-download"></i> Output Format *</label>
                    <div class="format-options">
                        <label class="radio-option format-pdf">
                            <input type="radio" name="format" value="pdf" checked>
                            <span><i class="fas fa-file-pdf"></i> PDF</span>
                        </label>
                        <label class="radio-option format-excel">
                            <input type="radio" name="format" value="excel">
                            <span><i class="fas fa-file-excel"></i> Excel</span>
                        </label>
                        <label class="radio-option format-csv">
                            <input type="radio" name="format" value="csv">
                            <span><i class="fas fa-file-csv"></i> CSV</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-cog"></i> Additional Options</label>
                    <div class="checkbox-options">
                        <label class="checkbox-option">
                            <input type="checkbox" name="detailed_breakdown" value="1">
                            <span>Include detailed breakdown</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="show_charts" value="1">
                            <span>Include charts and graphs</span>
                        </label>
                        <label class="checkbox-option">
                            <input type="checkbox" name="compare_previous" value="1">
                            <span>Compare with previous period</span>
                        </label>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-chart-bar"></i> Generate Report
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Pre-built Reports -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-folder-open"></i> Quick Reports</h3>
            <span class="header-badge">One-Click Generation</span>
        </div>
        <div class="card-body">
            <div class="reports-grid">
                <div class="report-card">
                    <div class="report-icon revenue">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <h4>Monthly Revenue</h4>
                    <p>Revenue breakdown by source, category, and date</p>
                    <div class="report-meta">
                        <span><i class="fas fa-clock"></i> ~30 sec</span>
                    </div>
                    <form method="POST" action="process_reports.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="generate_quick_report">
                        <input type="hidden" name="report_type" value="monthly_revenue">
                        <button type="submit" class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                    </form>
                </div>

                <div class="report-card">
                    <div class="report-icon clients">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Client Summary</h4>
                    <p>Client billing history and outstanding balances</p>
                    <div class="report-meta">
                        <span><i class="fas fa-clock"></i> ~45 sec</span>
                    </div>
                    <form method="POST" action="process_reports.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="generate_quick_report">
                        <input type="hidden" name="report_type" value="client_summary">
                        <button type="submit" class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                    </form>
                </div>

                <div class="report-card">
                    <div class="report-icon profit">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <h4>Profit & Loss</h4>
                    <p>Complete P&L statement with comparisons</p>
                    <div class="report-meta">
                        <span><i class="fas fa-clock"></i> ~1 min</span>
                    </div>
                    <form method="POST" action="process_reports.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="generate_quick_report">
                        <input type="hidden" name="report_type" value="profit_loss">
                        <button type="submit" class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                    </form>
                </div>

                <div class="report-card">
                    <div class="report-icon tax">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h4>Tax Report</h4>
                    <p>Tax-ready financial summary and documentation</p>
                    <div class="report-meta">
                        <span><i class="fas fa-clock"></i> ~2 min</span>
                    </div>
                    <form method="POST" action="process_reports.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="generate_quick_report">
                        <input type="hidden" name="report_type" value="tax_report">
                        <button type="submit" class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                    </form>
                </div>

                <div class="report-card">
                    <div class="report-icon sessions">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4>Session Analytics</h4>
                    <p>Session attendance, revenue, and trends</p>
                    <div class="report-meta">
                        <span><i class="fas fa-clock"></i> ~30 sec</span>
                    </div>
                    <form method="POST" action="process_reports.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="generate_quick_report">
                        <input type="hidden" name="report_type" value="session_analytics">
                        <button type="submit" class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                    </form>
                </div>

                <div class="report-card">
                    <div class="report-icon packages">
                        <i class="fas fa-box"></i>
                    </div>
                    <h4>Package Performance</h4>
                    <p>Package sales analysis and utilization rates</p>
                    <div class="report-meta">
                        <span><i class="fas fa-clock"></i> ~30 sec</span>
                    </div>
                    <form method="POST" action="process_reports.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="generate_quick_report">
                        <input type="hidden" name="report_type" value="package_performance">
                        <button type="submit" class="btn-secondary btn-small"><i class="fas fa-play"></i> Generate</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reports -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recent Reports</h3>
            <button class="btn-secondary btn-small"><i class="fas fa-trash-alt"></i> Clear History</button>
        </div>
        <div class="card-body">
            <div class="recent-reports-list">
                <div class="report-item">
                    <div class="report-file-icon pdf">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="report-info">
                        <h4>Monthly Revenue Summary - December 2025</h4>
                        <span class="report-meta-text">Generated on Jan 5, 2026 • 245 KB</span>
                    </div>
                    <div class="report-actions">
                        <button class="btn-icon" data-action="download-report" data-id="1" data-file="reports/monthly_revenue_dec_2023.pdf" title="Download"><i class="fas fa-download"></i></button>
                        <button class="btn-icon" data-action="view-report" data-id="1" data-file="reports/monthly_revenue_dec_2023.pdf" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" data-action="delete" data-id="1" data-type="report" data-action-url="process_reports.php" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>

                <div class="report-item">
                    <div class="report-file-icon excel">
                        <i class="fas fa-file-excel"></i>
                    </div>
                    <div class="report-info">
                        <h4>Client Billing Summary - Q4 2025</h4>
                        <span class="report-meta-text">Generated on Dec 28, 2025 • 128 KB</span>
                    </div>
                    <div class="report-actions">
                        <button class="btn-icon" data-action="download-report" data-id="2" data-file="reports/client_billing_q4_2025.xlsx" title="Download"><i class="fas fa-download"></i></button>
                        <button class="btn-icon" data-action="view-report" data-id="2" data-file="reports/client_billing_q4_2025.xlsx" title="View"><i class="fas fa-eye"></i></button>
                        <button class="btn-icon" data-action="delete" data-id="2" data-type="report" data-action-url="process_reports.php" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const dateRangeSelect = document.getElementById('dateRangeSelect');
    const customDateRange = document.getElementById('customDateRange');
    
    if (dateRangeSelect) {
        dateRangeSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.style.display = 'flex';
            } else {
                customDateRange.style.display = 'none';
            }
        });
    }
});
</script>

<style>
/* Header badge */
.header-badge {
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Form labels with icons */
.form-label i {
    margin-right: 8px;
    color: var(--primary);
}

/* Format options */
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
    border-radius: 8px;
    padding: 14px 20px;
    cursor: pointer;
    transition: all 0.3s;
}

.radio-option:hover,
.checkbox-option:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.radio-option:has(input:checked),
.checkbox-option:has(input:checked) {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

.radio-option input,
.checkbox-option input {
    margin-right: 10px;
}

.radio-option span,
.checkbox-option span {
    font-size: 14px;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 8px;
}

.radio-option i {
    font-size: 18px;
}

.format-pdf i { color: #ef4444; }
.format-excel i { color: #10b981; }
.format-csv i { color: #3B82F6; }

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.custom-date-row {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.report-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 28px;
    text-align: center;
    transition: all 0.3s;
    position: relative;
}

.report-card:hover {
    border-color: var(--primary);
    transform: translateY(-4px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
}

.report-icon {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 18px;
    font-size: 26px;
    color: #fff;
}

.report-icon.revenue { background: linear-gradient(135deg, #10b981, #059669); }
.report-icon.clients { background: linear-gradient(135deg, #3B82F6, #2563EB); }
.report-icon.profit { background: linear-gradient(135deg, #8B5CF6, #6B46C1); }
.report-icon.tax { background: linear-gradient(135deg, #f59e0b, #d97706); }
.report-icon.sessions { background: linear-gradient(135deg, #ec4899, #be185d); }
.report-icon.packages { background: linear-gradient(135deg, #06b6d4, #0891b2); }

.report-card h4 {
    font-size: 17px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.report-card p {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 16px;
    min-height: 40px;
}

.report-card .report-meta {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 16px;
}

.report-card .report-meta i {
    color: var(--primary);
    margin-right: 4px;
}

.recent-reports-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.report-item {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s;
}

.report-item:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.report-file-icon {
    width: 54px;
    height: 54px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.report-file-icon.pdf {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

.report-file-icon.excel {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.report-info {
    flex: 1;
    min-width: 0;
}

.report-info h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 6px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.report-meta-text {
    font-size: 12px;
    color: var(--text-dim);
}

.report-actions {
    display: flex;
    gap: 8px;
}

@media (max-width: 768px) {
    .format-options,
    .checkbox-options {
        flex-direction: column;
    }
    
    .reports-grid {
        grid-template-columns: 1fr;
    }
    
    .form-actions {
        flex-direction: column;
    }
    
    .report-item {
        flex-direction: column;
        text-align: center;
    }
    
    .report-actions {
        width: 100%;
        justify-content: center;
    }
}
</style>
