<!-- Accounting Expenses View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-receipt"></i> Expense Tracking
    </h1>
    <p class="page-description">Track and manage business expenses</p>
</div>

<div class="expenses-content">
    <!-- Add Expense Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add Expense</h3>
        </div>
        <div class="card-body">
            <form class="expense-form">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select class="form-input" required>
                            <option value="">-- Select Category --</option>
                            <option>Ice Time Rental</option>
                            <option>Equipment</option>
                            <option>Travel</option>
                            <option>Utilities</option>
                            <option>Marketing</option>
                            <option>Insurance</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Amount *</label>
                        <input type="number" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <input type="text" class="form-input" placeholder="Brief description of the expense">
                </div>

                <div class="form-group">
                    <label>Receipt/Invoice</label>
                    <div class="file-upload-zone">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop file or click to browse</p>
                        <input type="file" style="display: none;">
                        <button type="button" class="btn-secondary">Choose File</button>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary"><i class="fas fa-plus"></i> Add Expense</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Recent Expenses</h3>
            <div class="filter-group">
                <select class="form-input-small">
                    <option>This Month</option>
                    <option>Last Month</option>
                    <option>Last 3 Months</option>
                    <option>This Year</option>
                </select>
                <button class="btn-secondary"><i class="fas fa-file-export"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Receipt</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Jan 15, 2024</td>
                            <td><span class="category-badge">Ice Time</span></td>
                            <td>Main rink rental - 2 hours</td>
                            <td><strong>$250.00</strong></td>
                            <td><button class="btn-link"><i class="fas fa-paperclip"></i> View</button></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <td>Jan 12, 2024</td>
                            <td><span class="category-badge">Equipment</span></td>
                            <td>Training cones and pucks</td>
                            <td><strong>$85.50</strong></td>
                            <td><button class="btn-link"><i class="fas fa-paperclip"></i> View</button></td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Edit"><i class="fas fa-edit"></i></button>
                                    <button class="btn-icon" title="Delete"><i class="fas fa-trash"></i></button>
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
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: 8px;
    padding: 30px;
    text-align: center;
    background: var(--bg-main);
    transition: all 0.3s;
}

.file-upload-zone:hover {
    border-color: var(--neon);
}

.file-upload-zone i {
    font-size: 36px;
    color: var(--neon);
    opacity: 0.5;
    display: block;
    margin-bottom: 10px;
}

.file-upload-zone p {
    color: var(--text-dim);
    margin-bottom: 15px;
}

.category-badge {
    display: inline-block;
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.btn-link {
    background: none;
    border: none;
    color: var(--neon);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    padding: 0;
}

.btn-link:hover {
    text-decoration: underline;
}

.btn-link i {
    margin-right: 5px;
}
</style>
