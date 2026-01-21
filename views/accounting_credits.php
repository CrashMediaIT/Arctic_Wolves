<!-- Accounting Credits View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-undo-alt"></i> Credits & Refunds
    </h1>
    <p class="page-description">Manage client credits and process refunds</p>
</div>

<div class="credits-content">
    <!-- Action Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search...">
            <select class="form-input-small">
                <option>All Types</option>
                <option>Credits</option>
                <option>Refunds</option>
            </select>
            <select class="form-input-small">
                <option>All Status</option>
                <option>Pending</option>
                <option>Approved</option>
                <option>Completed</option>
            </select>
        </div>
        <button class="btn-primary"><i class="fas fa-plus"></i> Issue Credit/Refund</button>
    </div>

    <!-- Credits & Refunds Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Credits & Refunds</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>#CR-001</strong></td>
                            <td>John Smith</td>
                            <td><span class="type-badge credit">Credit</span></td>
                            <td><strong>$75.00</strong></td>
                            <td>Session cancellation</td>
                            <td>Jan 15, 2024</td>
                            <td><span class="status-badge completed">Completed</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>#RF-002</strong></td>
                            <td>Sarah Johnson</td>
                            <td><span class="type-badge refund">Refund</span></td>
                            <td><strong>$150.00</strong></td>
                            <td>Package cancellation</td>
                            <td>Jan 14, 2024</td>
                            <td><span class="status-badge pending">Pending</span></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Approve"><i class="fas fa-check"></i></button>
                                    <button class="btn-icon" title="Reject"><i class="fas fa-times"></i></button>
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
.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.type-badge.credit {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.type-badge.refund {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}
</style>
