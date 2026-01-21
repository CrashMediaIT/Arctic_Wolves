<!-- Travel Mileage Tracking View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-route"></i> Mileage Tracking
    </h1>
    <p class="page-description">Track and manage your travel mileage for reimbursement</p>
</div>

<div class="mileage-content">
    <!-- Summary Cards -->
    <div class="mileage-summary">
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-car"></i>
            </div>
            <div class="summary-details">
                <h4>This Month</h4>
                <p class="summary-value">245 miles</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="summary-details">
                <h4>Estimated Amount</h4>
                <p class="summary-value">$159.25</p>
            </div>
        </div>
        <div class="summary-card">
            <div class="summary-icon">
                <i class="fas fa-calendar-alt"></i>
            </div>
            <div class="summary-details">
                <h4>Total Trips</h4>
                <p class="summary-value">18</p>
            </div>
        </div>
    </div>

    <!-- Add Mileage Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add Mileage Entry</h3>
        </div>
        <div class="card-body">
            <form class="mileage-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Purpose *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Purpose --</option>
                            <option>Training Session</option>
                            <option>Team Practice</option>
                            <option>Game/Tournament</option>
                            <option>Meeting</option>
                            <option>Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>From Location *</label>
                        <input type="text" class="form-input" placeholder="Starting location" required>
                    </div>
                    <div class="form-group">
                        <label>To Location *</label>
                        <input type="text" class="form-input" placeholder="Destination" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Distance (miles) *</label>
                        <input type="number" class="form-input" placeholder="0.0" step="0.1" min="0" required>
                    </div>
                    <div class="form-group">
                        <label>Rate per Mile</label>
                        <input type="number" class="form-input" value="0.65" step="0.01" min="0" readonly>
                    </div>
                    <div class="form-group">
                        <label>Total Amount</label>
                        <input type="text" class="form-input" value="$0.00" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea class="form-textarea" rows="2" placeholder="Additional notes (optional)"></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Add Entry</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Mileage Log -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Mileage Log</h3>
            <div class="filter-group">
                <select class="form-input-small">
                    <option>This Month</option>
                    <option>Last Month</option>
                    <option>Last 3 Months</option>
                    <option>Last 6 Months</option>
                    <option>This Year</option>
                    <option>Custom Range</option>
                </select>
                <button class="btn-secondary"><i class="fas fa-file-export"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div class="mileage-table-container">
                <table class="mileage-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Purpose</th>
                            <th>Route</th>
                            <th>Distance</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Jan 15, 2024</td>
                            <td>Training Session</td>
                            <td>
                                <div class="route-info">
                                    <span class="route-from">Home</span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="route-to">Ice Arena</span>
                                </div>
                            </td>
                            <td>12.5 mi</td>
                            <td>$8.13</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Jan 14, 2024</td>
                            <td>Team Practice</td>
                            <td>
                                <div class="route-info">
                                    <span class="route-from">Home</span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="route-to">Training Center</span>
                                </div>
                            </td>
                            <td>18.2 mi</td>
                            <td>$11.83</td>
                            <td><span class="status-badge approved">Approved</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Jan 12, 2024</td>
                            <td>Game/Tournament</td>
                            <td>
                                <div class="route-info">
                                    <span class="route-from">Home</span>
                                    <i class="fas fa-arrow-right"></i>
                                    <span class="route-to">Regional Arena</span>
                                </div>
                            </td>
                            <td>32.8 mi</td>
                            <td>$21.32</td>
                            <td><span class="status-badge approved">Approved</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
.mileage-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 25px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.summary-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0;
}

.summary-details h4 {
    font-size: 14px;
    color: var(--text-dim);
    margin-bottom: 5px;
}

.summary-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.mileage-form .form-actions {
    display: flex;
    justify-content: flex-end;
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

.mileage-table-container {
    overflow-x: auto;
}

.mileage-table {
    width: 100%;
    border-collapse: collapse;
}

.mileage-table thead {
    background: var(--bg-main);
}

.mileage-table th {
    padding: 15px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.mileage-table td {
    padding: 15px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-white);
}

.mileage-table tbody tr {
    transition: all 0.3s;
}

.mileage-table tbody tr:hover {
    background: var(--bg-main);
}

.route-info {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
}

.route-from,
.route-to {
    color: var(--text-dim);
}

.route-info i {
    color: var(--neon);
    font-size: 10px;
}
</style>
