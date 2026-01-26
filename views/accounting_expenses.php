<?php
// Fetch expenses
$expensesQuery = "SELECT e.*, e.category as category_name
    FROM expenses e
    ORDER BY e.expense_date DESC
    LIMIT 20";
$expenses = $pdo->query($expensesQuery);

// Fetch expense stats
$expenseStatsQuery = "SELECT 
    COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE()) THEN amount ELSE 0 END), 0) as this_month,
    COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(expense_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN amount ELSE 0 END), 0) as last_month,
    COALESCE(SUM(amount), 0) as total_all,
    COUNT(*) as total_count
    FROM expenses";
try {
    $statsResult = $pdo->query($expenseStatsQuery);
    $expenseStats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expenseStats = ['this_month' => 0, 'last_month' => 0, 'total_all' => 0, 'total_count' => 0];
}

// Calculate month-over-month change
$monthChange = 0;
if ($expenseStats['last_month'] > 0) {
    $monthChange = (($expenseStats['this_month'] - $expenseStats['last_month']) / $expenseStats['last_month']) * 100;
}
?>
<!-- Accounting Expenses View -->
<?php if (isset($_GET['status']) && $_GET['status'] === 'success'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Operation completed successfully!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<?php if (isset($_GET['status']) && $_GET['status'] === 'error'): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;"><?= htmlspecialchars($_GET['message'] ?? 'An error occurred') ?></span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-receipt"></i> Expense Tracking
    </h1>
    <p class="page-description">Track, manage, and categorize business expenses</p>
</div>

<div class="expenses-content">
    <!-- Expense Stats -->
    <div class="expense-stats">
        <div class="expense-stat-card current">
            <div class="stat-icon"><i class="fas fa-calendar-day"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($expenseStats['this_month'], 2) ?></span>
                <span class="stat-label">This Month</span>
            </div>
        </div>
        <div class="expense-stat-card last">
            <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($expenseStats['last_month'], 2) ?></span>
                <span class="stat-label">Last Month</span>
            </div>
        </div>
        <div class="expense-stat-card change <?= $monthChange >= 0 ? 'up' : 'down' ?>">
            <div class="stat-icon"><i class="fas fa-<?= $monthChange >= 0 ? 'arrow-up' : 'arrow-down' ?>"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $monthChange >= 0 ? '+' : '' ?><?= number_format($monthChange, 1) ?>%</span>
                <span class="stat-label">vs Last Month</span>
            </div>
        </div>
        <div class="expense-stat-card total">
            <div class="stat-icon"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $expenseStats['total_count'] ?></span>
                <span class="stat-label">Total Expenses</span>
            </div>
        </div>
    </div>

    <!-- Add Expense Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Expense</h3>
            <span class="header-badge">Quick Entry</span>
        </div>
        <div class="card-body">
            <form method="POST" action="process_expenses.php" enctype="multipart/form-data" class="expense-form">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="create">
                
                <div class="form-row three-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Date *</label>
                        <input type="date" name="expense_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-folder"></i> Category *</label>
                        <select name="category" class="form-input" required>
                            <option value="">-- Select Category --</option>
                            <option value="ice_time">Ice Time Rental</option>
                            <option value="equipment">Equipment</option>
                            <option value="travel">Travel</option>
                            <option value="utilities">Utilities</option>
                            <option value="marketing">Marketing</option>
                            <option value="insurance">Insurance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-dollar-sign"></i> Amount *</label>
                        <input type="number" name="amount" class="form-input" placeholder="0.00" step="0.01" min="0" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <input type="text" name="description" class="form-input" placeholder="Brief description of the expense">
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Receipt/Invoice</label>
                    <div class="file-upload-zone" data-upload="receipt" id="dropZone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p id="receiptFileLabel" class="upload-text">Drag & drop file here or click to browse</p>
                        <span class="upload-hint">Supports: JPG, PNG, PDF (Max 10MB)</span>
                        <input type="file" name="receipt_file" id="receiptFile" accept="image/*,application/pdf" capture="environment" style="display: none;" onchange="updateFileLabel('receiptFileLabel', this)">
                        <div class="upload-buttons">
                            <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('receiptFile').click()">
                                <i class="fas fa-folder-open"></i> Choose File
                            </button>
                            <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('receiptFile').setAttribute('capture', 'environment'); document.getElementById('receiptFile').click()">
                                <i class="fas fa-camera"></i> Take Photo
                            </button>
                        </div>
                    </div>
                </div>

                <div class="form-actions">
                    <button type="reset" class="btn-secondary">
                        <i class="fas fa-redo"></i> Reset
                    </button>
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
            <h3><i class="fas fa-list"></i> Expense History</h3>
            <div class="filter-group">
                <select class="form-input-small" data-filter="period">
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="last_3_months">Last 3 Months</option>
                    <option>This Year</option>
                </select>
                <button class="btn-secondary" data-action="export" data-table="expenses"><i class="fas fa-file-export"></i> Export</button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" data-table="expenses">
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
                                <td><span class="expense-date"><?= date('M j, Y', strtotime($expense['expense_date'])) ?></span></td>
                                <td><span class="category-badge"><?= htmlspecialchars($expense['category_name'] ?? 'N/A') ?></span></td>
                                <td class="description-cell"><?= htmlspecialchars($expense['description'] ?? '') ?></td>
                                <td><strong class="expense-amount">$<?= number_format($expense['amount'], 2) ?></strong></td>
                                <td>
                                    <?php if($expense['receipt_url']): ?>
                                        <?php 
                                        // Validate receipt URL is safe (within uploads directory using realpath)
                                        $receipt_url = $expense['receipt_url'];
                                        $receipt_real_path = realpath($receipt_url);
                                        $uploads_real_path = realpath('uploads/receipts');
                                        $is_safe = $receipt_real_path && $uploads_real_path && 
                                                   strpos($receipt_real_path, $uploads_real_path) === 0;
                                        ?>
                                        <?php if($is_safe): ?>
                                            <a href="<?= htmlspecialchars($receipt_url) ?>" target="_blank" rel="noopener noreferrer" class="receipt-link">
                                                <i class="fas fa-paperclip"></i> View
                                            </a>
                                        <?php else: ?>
                                            <span class="no-receipt">Invalid</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-receipt">No receipt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="Edit" data-action="edit" data-modal="edit-expense-modal" data-id="<?= $expense['id'] ?>"
                                                data-category="<?= htmlspecialchars($expense['category'] ?? '') ?>"
                                                data-description="<?= htmlspecialchars($expense['description'] ?? '') ?>"
                                                data-amount="<?= $expense['amount'] ?>"
                                                data-date="<?= $expense['expense_date'] ?>">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="process_expenses.php" style="display: inline;" class="delete-expense-form">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                            <button type="submit" class="btn-icon" title="Delete" data-confirm="Are you sure you want to delete this expense?">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-receipt"></i>
                                        <p>No expenses recorded yet</p>
                                        <span>Add your first expense using the form above</span>
                                    </div>
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
/* Expense Stats */
.expense-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.expense-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
}

.expense-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.expense-stat-card .stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.expense-stat-card.current .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.expense-stat-card.last .stat-icon { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }
.expense-stat-card.change .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.expense-stat-card.change.up .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.expense-stat-card.change.down .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.expense-stat-card.total .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }

.expense-stat-card .stat-info { flex: 1; }

.expense-stat-card .stat-value {
    font-size: 26px;
    font-weight: 900;
    color: var(--text-white);
    display: block;
    margin-bottom: 4px;
}

.expense-stat-card .stat-label {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

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

.form-row.three-cols {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.form-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

/* File Upload Zone - Enhanced */
.file-upload-zone {
    border: 2px dashed var(--border);
    border-radius: 12px;
    padding: 32px 24px;
    text-align: center;
    background: var(--bg-main);
    transition: all 0.3s;
    cursor: pointer;
}

.file-upload-zone:hover,
.file-upload-zone.drag-over {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.file-upload-zone .upload-icon {
    margin-bottom: 16px;
}

.file-upload-zone .upload-icon i {
    font-size: 42px;
    color: var(--primary);
    opacity: 0.6;
}

.file-upload-zone .upload-text {
    font-size: 15px;
    color: var(--text-white);
    font-weight: 600;
    margin-bottom: 6px;
}

.file-upload-zone .upload-hint {
    font-size: 12px;
    color: var(--text-dim);
    display: block;
    margin-bottom: 16px;
}

.upload-buttons {
    display: flex;
    gap: 12px;
    justify-content: center;
}

/* Category Badge */
.category-badge {
    display: inline-flex;
    background: rgba(107, 70, 193, 0.15);
    color: #8B5CF6;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Table styling */
.expense-date {
    color: var(--text-dim);
}

.expense-amount {
    color: #ef4444;
    font-size: 15px;
}

.description-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.receipt-link {
    background: none;
    border: none;
    color: var(--primary);
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.receipt-link:hover {
    text-decoration: underline;
}

.no-receipt {
    color: var(--text-dim);
    font-size: 13px;
}

.btn-delete {
    color: #ef4444 !important;
}

.btn-delete:hover {
    background: rgba(239, 68, 68, 0.15) !important;
}

.empty-state {
    padding: 60px 20px !important;
}

.empty-state-content {
    text-align: center;
}

.empty-state-content i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state-content p {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 8px;
}

.empty-state-content span {
    font-size: 13px;
    color: var(--text-dim);
}

@media (max-width: 768px) {
    .expense-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .form-row.three-cols {
        grid-template-columns: 1fr;
    }
    
    .upload-buttons {
        flex-direction: column;
    }
}

@media (max-width: 480px) {
    .expense-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Drag and drop functionality
const dropZone = document.getElementById('dropZone');
const receiptFile = document.getElementById('receiptFile');

if (dropZone) {
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('drag-over');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        if (e.dataTransfer.files.length) {
            receiptFile.files = e.dataTransfer.files;
            updateFileLabel('receiptFileLabel', receiptFile);
        }
    });
    
    dropZone.addEventListener('click', (e) => {
        if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
            receiptFile.click();
        }
    });
}

function updateFileLabel(labelId, input) {
    const label = document.getElementById(labelId);
    if (input.files.length > 0) {
        label.textContent = input.files[0].name;
        label.style.color = '#10b981';
    } else {
        label.textContent = 'Drag & drop file here or click to browse';
        label.style.color = '';
    }
}

// Handle edit expense button clicks
document.querySelectorAll('[data-action="edit"][data-modal="edit-expense-modal"]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var id = this.getAttribute('data-id');
        var category = this.getAttribute('data-category');
        var description = this.getAttribute('data-description');
        var amount = this.getAttribute('data-amount');
        var date = this.getAttribute('data-date');
        
        document.getElementById('edit-expense-id').value = id;
        document.getElementById('edit-expense-category').value = category;
        document.getElementById('edit-expense-description').value = description;
        document.getElementById('edit-expense-amount').value = amount;
        document.getElementById('edit-expense-date').value = date;
        
        document.getElementById('edit-expense-modal').classList.add('active');
    });
});

// Handle delete buttons with single confirmation
document.querySelectorAll('.delete-expense-form').forEach(function(form) {
    form.addEventListener('submit', function(e) {
        var btn = form.querySelector('button[data-confirm]');
        var msg = btn ? btn.getAttribute('data-confirm') : 'Are you sure you want to delete this expense?';
        if (!confirm(msg)) {
            e.preventDefault();
        }
    });
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
    }
}
</script>

<!-- Edit Expense Modal -->
<div id="edit-expense-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Edit Expense</h2>
            <button class="modal-close" onclick="closeModal('edit-expense-modal')">&times;</button>
        </div>
        <form method="POST" action="process_expenses.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="expense_id" id="edit-expense-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-calendar"></i> Date *</label>
                    <input type="date" name="expense_date" id="edit-expense-date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-folder"></i> Category *</label>
                    <select name="category" id="edit-expense-category" class="form-input" required>
                        <option value="">-- Select Category --</option>
                        <option value="ice_time">Ice Time Rental</option>
                        <option value="equipment">Equipment</option>
                        <option value="travel">Travel</option>
                        <option value="utilities">Utilities</option>
                        <option value="marketing">Marketing</option>
                        <option value="insurance">Insurance</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-dollar-sign"></i> Amount *</label>
                    <input type="number" name="amount" id="edit-expense-amount" class="form-input" step="0.01" min="0" required>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <input type="text" name="description" id="edit-expense-description" class="form-input">
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Replace Receipt (optional)</label>
                    <input type="file" name="receipt_file" class="form-input" accept="image/*,application/pdf">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('edit-expense-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Update Expense</button>
            </div>
        </form>
    </div>
</div>
