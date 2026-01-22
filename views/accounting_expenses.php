<?php
// Fetch expenses
$expensesQuery = "SELECT e.*, e.category as category_name
    FROM expenses e
    ORDER BY e.expense_date DESC
    LIMIT 20";
$expenses = $pdo->query($expensesQuery);
?>
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
            <form method="POST" action="process_expenses.php" enctype="multipart/form-data" class="expense-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="create">
                <div class="form-row">
                    <div class="form-group">
                        <label>Date *</label>
                        <input type="date" name="expense_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label>Category *</label>
                        <select name="category" class="form-input" required>
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
                        <input type="number" name="amount" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-input" placeholder="Brief description of the expense">
                </div>

                <div class="form-group">
                    <label>Receipt/Invoice</label>
                    <div class="file-upload-zone" data-upload="receipt">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop file or click to browse</p>
                        <input type="file" name="receipt_file" id="receiptFile" accept="image/*,application/pdf" capture="environment" style="display: none;">
                        <div class="upload-buttons">
                            <button type="button" class="btn-secondary" onclick="document.getElementById('receiptFile').click()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <button type="button" class="btn-secondary" onclick="document.getElementById('receiptFile').setAttribute('capture', 'environment'); document.getElementById('receiptFile').click()">
                                <i class="fas fa-camera"></i> Take Photo
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-plus"></i> Add Expense
                    </button>
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
                        <?php if($expenses && $expenses->rowCount() > 0): ?>
                            <?php while($expense = $expenses->fetch()): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($expense['expense_date'])) ?></td>
                                <td><span class="category-badge"><?= htmlspecialchars($expense['category_name'] ?? 'N/A') ?></span></td>
                                <td><?= htmlspecialchars($expense['description'] ?? '') ?></td>
                                <td><strong>$<?= number_format($expense['amount'], 2) ?></strong></td>
                                <td>
                                    <?php if($expense['receipt_url']): ?>
                                        <?php 
                                        // Validate receipt URL is safe (within uploads directory)
                                        $receipt_url = htmlspecialchars($expense['receipt_url']);
                                        $is_safe = strpos($receipt_url, 'uploads/receipts/') === 0;
                                        ?>
                                        <?php if($is_safe): ?>
                                            <a href="<?= $receipt_url ?>" target="_blank" rel="noopener noreferrer" class="btn-link">
                                                <i class="fas fa-paperclip"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-dim">Invalid receipt</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-dim">No receipt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <form method="POST" action="process_expenses.php" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                            <button type="submit" class="btn-icon" title="Delete" onclick="return confirm('Are you sure you want to delete this expense?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <p class="placeholder-text">No expenses recorded yet.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
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

.upload-buttons {
    display: flex;
    gap: 10px;
    justify-content: center;
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
