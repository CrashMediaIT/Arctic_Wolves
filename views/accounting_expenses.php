<?php
// Fetch expense categories
$categoriesQuery = "SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY display_order, name";
try {
    $categories = $pdo->query($categoriesQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Fetch payees
$payeesQuery = "SELECT * FROM payees WHERE is_active = 1 ORDER BY name";
try {
    $payees = $pdo->query($payeesQuery)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payees = [];
}

// Fetch expenses with enhanced fields
$expensesQuery = "SELECT e.*, p.name as payee_name
    FROM expenses e
    LEFT JOIN payees p ON e.payee_id = p.id
    ORDER BY e.expense_date DESC
    LIMIT 50";
try {
    $expenses = $pdo->query($expensesQuery);
} catch (PDOException $e) {
    $expenses = null;
}

// Fetch expense stats
$expenseStatsQuery = "SELECT 
    COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(CURDATE()) AND YEAR(expense_date) = YEAR(CURDATE()) THEN COALESCE(total_amount, amount) ELSE 0 END), 0) as this_month,
    COALESCE(SUM(CASE WHEN MONTH(expense_date) = MONTH(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) AND YEAR(expense_date) = YEAR(DATE_SUB(CURDATE(), INTERVAL 1 MONTH)) THEN COALESCE(total_amount, amount) ELSE 0 END), 0) as last_month,
    COALESCE(SUM(COALESCE(total_amount, amount)), 0) as total_all,
    COUNT(*) as total_count
    FROM expenses";
try {
    $statsResult = $pdo->query($expenseStatsQuery);
    $expenseStats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $expenseStats = ['this_month' => 0, 'last_month' => 0, 'total_all' => 0, 'total_count' => 0];
}

// Get available years for export
$activationQuery = "SELECT activation_year FROM system_activation ORDER BY id LIMIT 1";
try {
    $activation = $pdo->query($activationQuery)->fetch(PDO::FETCH_ASSOC);
    $startYear = $activation ? $activation['activation_year'] : 2026;
} catch (PDOException $e) {
    $startYear = 2026;
}
$currentYear = intval(date('Y'));

// Calculate month-over-month change
$monthChange = 0;
if ($expenseStats['last_month'] > 0) {
    $monthChange = (($expenseStats['this_month'] - $expenseStats['last_month']) / $expenseStats['last_month']) * 100;
}
?>
<!-- Accounting Expenses View - CRA Best Practices -->
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
    <p class="page-description">Track, manage, and categorize business expenses (CRA Best Practices)</p>
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

    <!-- Quick Actions -->
    <div class="quick-actions-bar">
        <button class="btn-primary" onclick="openAddExpenseModal()">
            <i class="fas fa-plus"></i> Add Expense
        </button>
        <button class="btn-secondary" onclick="openOCRModal()">
            <i class="fas fa-camera"></i> Scan Receipt (OCR)
        </button>
        <button class="btn-secondary" onclick="openExportModal()">
            <i class="fas fa-file-export"></i> Export Expenses
        </button>
    </div>

    <!-- Add Expense Form - Enhanced with CRA fields -->
    <div class="content-card" id="add-expense-card" style="display: none;">
        <div class="card-header">
            <h3><i class="fas fa-plus-circle"></i> Add New Expense</h3>
            <button class="btn-icon" onclick="document.getElementById('add-expense-card').style.display='none'">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="card-body">
            <form method="POST" action="process_expenses.php" enctype="multipart/form-data" class="expense-form" id="expenseForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="create">
                <input type="hidden" name="line_items" id="lineItemsJson" value="[]">
                
                <div class="form-row three-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Date *</label>
                        <input type="date" name="expense_date" id="expenseDate" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-store"></i> Vendor *</label>
                        <input type="text" name="vendor_name" id="vendorName" class="form-input" placeholder="Business name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-folder"></i> Category *</label>
                        <select name="category" id="expenseCategory" class="form-input" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ice_time">Ice Time Rental</option>
                            <option value="equipment">Equipment</option>
                            <option value="travel">Travel</option>
                            <option value="utilities">Utilities</option>
                            <option value="marketing">Marketing</option>
                            <option value="insurance">Insurance</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row three-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-dollar-sign"></i> Subtotal *</label>
                        <input type="number" name="subtotal" id="expenseSubtotal" class="form-input" placeholder="0.00" step="0.01" min="0" required onchange="calculateExpenseTotal()">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-percent"></i> Tax (GST/HST)</label>
                        <input type="number" name="tax_amount" id="expenseTax" class="form-input" placeholder="0.00" step="0.01" min="0" value="0" onchange="calculateExpenseTotal()">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calculator"></i> Total *</label>
                        <input type="number" name="total_amount" id="expenseTotal" class="form-input" placeholder="0.00" step="0.01" min="0" required readonly>
                    </div>
                </div>
                
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-credit-card"></i> Payment Method</label>
                        <select name="payment_method" id="paymentMethod" class="form-input">
                            <option value="">-- Select --</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit">Debit Card</option>
                            <option value="cash">Cash</option>
                            <option value="etransfer">E-Transfer</option>
                            <option value="cheque">Cheque</option>
                            <option value="stripe">Stripe Virtual Card</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-money-bill"></i> Currency</label>
                        <select name="currency" id="expenseCurrency" class="form-input">
                            <option value="CAD">CAD - Canadian Dollar</option>
                            <option value="USD">USD - US Dollar</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-user-tie"></i> Payee (Optional)</label>
                        <select name="payee_id" id="payeeSelect" class="form-input">
                            <option value="">-- No Payee --</option>
                            <?php foreach ($payees as $payee): ?>
                            <option value="<?= $payee['id'] ?>"><?= htmlspecialchars($payee['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-hashtag"></i> Reference #</label>
                        <input type="text" name="reference_number" id="referenceNumber" class="form-input" placeholder="Invoice/PO number">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="expenseDescription" class="form-input" rows="2" placeholder="Brief description of the expense"></textarea>
                </div>

                <!-- Line Items Section -->
                <div class="line-items-section">
                    <div class="section-header">
                        <h4><i class="fas fa-list"></i> Itemized List (Optional)</h4>
                        <button type="button" class="btn-small btn-secondary" onclick="addLineItem()">
                            <i class="fas fa-plus"></i> Add Item
                        </button>
                    </div>
                    <div id="lineItemsContainer"></div>
                </div>

                <div class="form-group">
                    <label class="form-label"><i class="fas fa-paperclip"></i> Receipt/Invoice</label>
                    <div class="file-upload-zone" data-upload="receipt" id="dropZone">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <p id="receiptFileLabel" class="upload-text">Drag & drop file here or click to browse</p>
                        <span class="upload-hint">Supports: JPG, PNG, PDF (Max 10MB) - Auto-uploads to Nextcloud</span>
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
                    <button type="button" class="btn-secondary" onclick="document.getElementById('add-expense-card').style.display='none'">
                        <i class="fas fa-times"></i> Cancel
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
                <select class="form-input-small" id="periodFilter" onchange="filterExpenses()">
                    <option value="this_month">This Month</option>
                    <option value="last_month">Last Month</option>
                    <option value="last_3_months">Last 3 Months</option>
                    <option value="this_year">This Year</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" data-table="expenses">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Vendor</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th>Subtotal</th>
                            <th>Tax</th>
                            <th>Total</th>
                            <th>Receipt</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($expenses && $expenses->rowCount() > 0): ?>
                            <?php while($expense = $expenses->fetch()): ?>
                            <tr>
                                <td><span class="expense-date"><?= date('M j, Y', strtotime($expense['expense_date'])) ?></span></td>
                                <td><strong><?= htmlspecialchars($expense['vendor_name'] ?? 'N/A') ?></strong></td>
                                <td><span class="category-badge"><?= htmlspecialchars($expense['category'] ?? 'N/A') ?></span></td>
                                <td class="description-cell"><?= htmlspecialchars($expense['description'] ?? '') ?></td>
                                <td>$<?= number_format($expense['subtotal'] ?? $expense['amount'], 2) ?></td>
                                <td>$<?= number_format($expense['tax_amount'] ?? 0, 2) ?></td>
                                <td><strong class="expense-amount">$<?= number_format($expense['total_amount'] ?? $expense['amount'], 2) ?></strong></td>
                                <td>
                                    <?php if($expense['receipt_url']): ?>
                                        <a href="<?= htmlspecialchars($expense['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="receipt-link">
                                            <i class="fas fa-paperclip"></i> View
                                        </a>
                                        <?php if(!empty($expense['nextcloud_path'])): ?>
                                        <span class="cloud-synced" title="Synced to Nextcloud"><i class="fas fa-cloud"></i></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="no-receipt">No receipt</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="Edit" onclick='editExpense(<?= htmlspecialchars(json_encode($expense)) ?>)'>
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" action="process_expenses.php" style="display: inline;" class="delete-expense-form">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="expense_id" value="<?= $expense['id'] ?>">
                                            <button type="submit" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this expense?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-receipt"></i>
                                        <p>No expenses recorded yet</p>
                                        <span>Add your first expense using the button above</span>
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

<!-- OCR Scanner Modal -->
<div id="ocr-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-camera"></i> Scan Receipt with OCR</h2>
            <button class="modal-close" onclick="closeModal('ocr-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 20px; color: var(--text-dim);">Upload or capture a receipt image. OCR will automatically extract vendor, date, and amounts.</p>
            
            <div class="file-upload-zone" id="ocrDropZone" style="margin-bottom: 20px;">
                <div class="upload-icon"><i class="fas fa-camera"></i></div>
                <p class="upload-text">Drop receipt image here or click to capture</p>
                <span class="upload-hint">JPG or PNG only for OCR processing</span>
                <input type="file" id="ocrFileInput" accept="image/jpeg,image/png" capture="environment" style="display: none;">
                <div class="upload-buttons">
                    <button type="button" class="btn-secondary btn-small" onclick="document.getElementById('ocrFileInput').click()">
                        <i class="fas fa-folder-open"></i> Browse
                    </button>
                    <button type="button" class="btn-primary btn-small" onclick="document.getElementById('ocrFileInput').setAttribute('capture', 'environment'); document.getElementById('ocrFileInput').click()">
                        <i class="fas fa-camera"></i> Take Photo
                    </button>
                </div>
            </div>
            
            <div id="ocrPreviewContainer" style="display: none; margin-bottom: 20px;">
                <img id="ocrPreviewImage" style="max-width: 100%; max-height: 300px; border-radius: 8px; border: 1px solid var(--border);">
            </div>
            
            <div id="ocrResultsContainer" style="display: none;">
                <h4><i class="fas fa-robot"></i> OCR Extracted Data</h4>
                <div class="ocr-results-grid">
                    <div class="ocr-result-item"><label>Vendor:</label><span id="ocrVendor">-</span></div>
                    <div class="ocr-result-item"><label>Date:</label><span id="ocrDate">-</span></div>
                    <div class="ocr-result-item"><label>Subtotal:</label><span id="ocrSubtotal">-</span></div>
                    <div class="ocr-result-item"><label>Tax:</label><span id="ocrTax">-</span></div>
                    <div class="ocr-result-item"><label>Total:</label><span id="ocrTotal">-</span></div>
                </div>
                <div id="ocrItemsContainer" style="margin-top: 15px;"></div>
            </div>
            
            <div id="ocrLoadingIndicator" style="display: none; text-align: center; padding: 30px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i>
                <p style="margin-top: 15px;">Processing receipt with OCR...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('ocr-modal')"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn-primary" id="useOcrDataBtn" onclick="useOCRData()" style="display: none;">
                <i class="fas fa-check"></i> Use This Data
            </button>
        </div>
    </div>
</div>

<!-- Export Modal -->
<div id="export-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-file-export"></i> Export Expenses</h2>
            <button class="modal-close" onclick="closeModal('export-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Export Period</label>
                <select id="exportType" class="form-input" onchange="updateExportOptions()">
                    <option value="week">This Week</option>
                    <option value="month" selected>This Month</option>
                    <option value="quarter">This Quarter</option>
                    <option value="year">Full Year</option>
                </select>
            </div>
            
            <div class="form-group" id="yearSelectGroup">
                <label class="form-label">Select Year</label>
                <select id="exportYear" class="form-input">
                    <?php for($y = $currentYear; $y >= $startYear; $y--): ?>
                    <option value="<?= $y ?>" <?= $y == $currentYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group" id="monthSelectGroup">
                <label class="form-label">Select Month</label>
                <select id="exportMonth" class="form-input">
                    <?php for($m = 1; $m <= 12; $m++): ?>
                    <option value="<?= $m ?>" <?= $m == date('m') ? 'selected' : '' ?>><?= date('F', mktime(0,0,0,$m,1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="form-group" id="quarterSelectGroup" style="display: none;">
                <label class="form-label">Select Quarter</label>
                <select id="exportQuarter" class="form-input">
                    <option value="1">Q1 (Jan-Mar)</option>
                    <option value="2">Q2 (Apr-Jun)</option>
                    <option value="3">Q3 (Jul-Sep)</option>
                    <option value="4">Q4 (Oct-Dec)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" id="includeReceipts" checked>
                    <span>Include Receipt Images</span>
                </label>
            </div>
            
            <div id="exportSummary" style="background: var(--bg-main); padding: 15px; border-radius: 8px; margin-top: 15px; display: none;">
                <h4 style="margin-bottom: 10px;"><i class="fas fa-chart-bar"></i> Export Summary</h4>
                <div id="exportSummaryContent"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('export-modal')"><i class="fas fa-times"></i> Cancel</button>
            <button type="button" class="btn-primary" onclick="exportExpenses()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
</div>

<!-- Edit Expense Modal -->
<div id="edit-expense-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 class="modal-title">Edit Expense</h2>
            <button class="modal-close" onclick="closeModal('edit-expense-modal')">&times;</button>
        </div>
        <form method="POST" action="process_expenses.php" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="expense_id" id="edit-expense-id">
            
            <div class="modal-body">
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calendar"></i> Date *</label>
                        <input type="date" name="expense_date" id="edit-expense-date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-store"></i> Vendor *</label>
                        <input type="text" name="vendor_name" id="edit-vendor-name" class="form-input" required>
                    </div>
                </div>
                <div class="form-row two-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-folder"></i> Category *</label>
                        <select name="category" id="edit-expense-category" class="form-input" required>
                            <option value="">-- Select Category --</option>
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                            <?php endforeach; ?>
                            <option value="ice_time">Ice Time Rental</option>
                            <option value="equipment">Equipment</option>
                            <option value="travel">Travel</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-credit-card"></i> Payment Method</label>
                        <select name="payment_method" id="edit-payment-method" class="form-input">
                            <option value="">-- Select --</option>
                            <option value="credit_card">Credit Card</option>
                            <option value="debit">Debit Card</option>
                            <option value="cash">Cash</option>
                            <option value="etransfer">E-Transfer</option>
                            <option value="cheque">Cheque</option>
                        </select>
                    </div>
                </div>
                <div class="form-row three-cols">
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-dollar-sign"></i> Subtotal *</label>
                        <input type="number" name="subtotal" id="edit-expense-subtotal" class="form-input" step="0.01" min="0" required onchange="calculateEditTotal()">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-percent"></i> Tax</label>
                        <input type="number" name="tax_amount" id="edit-expense-tax" class="form-input" step="0.01" min="0" onchange="calculateEditTotal()">
                    </div>
                    <div class="form-group">
                        <label class="form-label"><i class="fas fa-calculator"></i> Total *</label>
                        <input type="number" name="total_amount" id="edit-expense-total" class="form-input" step="0.01" min="0" required readonly>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label"><i class="fas fa-align-left"></i> Description</label>
                    <textarea name="description" id="edit-expense-description" class="form-input" rows="2"></textarea>
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

<style>
.expense-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 28px; }
.expense-stat-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 14px; padding: 22px; display: flex; align-items: center; gap: 18px; transition: all 0.3s ease; }
.expense-stat-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3); }
.expense-stat-card .stat-icon { width: 52px; height: 52px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; flex-shrink: 0; }
.expense-stat-card.current .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.expense-stat-card.last .stat-icon { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }
.expense-stat-card.change .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.expense-stat-card.change.up .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.expense-stat-card.change.down .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.expense-stat-card.total .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.expense-stat-card .stat-info { flex: 1; }
.expense-stat-card .stat-value { font-size: 26px; font-weight: 900; color: var(--text-white); display: block; margin-bottom: 4px; }
.expense-stat-card .stat-label { font-size: 12px; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
.quick-actions-bar { display: flex; gap: 12px; margin-bottom: 24px; flex-wrap: wrap; }
.form-row { display: grid; gap: 20px; margin-bottom: 16px; }
.form-row.two-cols { grid-template-columns: repeat(2, 1fr); }
.form-row.three-cols { grid-template-columns: repeat(3, 1fr); }
.line-items-section { background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; padding: 16px; margin-bottom: 16px; }
.line-items-section .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
.line-items-section h4 { margin: 0; font-size: 14px; color: var(--text-white); }
.line-item-row { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr auto; gap: 10px; margin-bottom: 8px; align-items: center; }
.line-item-row input { padding: 8px; font-size: 13px; }
.file-upload-zone { border: 2px dashed var(--border); border-radius: 12px; padding: 32px 24px; text-align: center; background: var(--bg-main); transition: all 0.3s; cursor: pointer; }
.file-upload-zone:hover, .file-upload-zone.drag-over { border-color: var(--primary); background: rgba(107, 70, 193, 0.05); }
.file-upload-zone .upload-icon { margin-bottom: 16px; }
.file-upload-zone .upload-icon i { font-size: 42px; color: var(--primary); opacity: 0.6; }
.file-upload-zone .upload-text { font-size: 15px; color: var(--text-white); font-weight: 600; margin-bottom: 6px; }
.file-upload-zone .upload-hint { font-size: 12px; color: var(--text-dim); display: block; margin-bottom: 16px; }
.upload-buttons { display: flex; gap: 12px; justify-content: center; }
.category-badge { display: inline-flex; background: rgba(107, 70, 193, 0.15); color: #8B5CF6; padding: 6px 12px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
.expense-date { color: var(--text-dim); }
.expense-amount { color: #ef4444; font-size: 15px; }
.description-cell { max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.receipt-link { background: none; border: none; color: var(--primary); font-size: 13px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; }
.receipt-link:hover { text-decoration: underline; }
.no-receipt { color: var(--text-dim); font-size: 13px; }
.cloud-synced { color: #10b981; margin-left: 8px; }
.btn-delete { color: #ef4444 !important; }
.btn-delete:hover { background: rgba(239, 68, 68, 0.15) !important; }
.empty-state { padding: 60px 20px !important; }
.empty-state-content { text-align: center; }
.empty-state-content i { font-size: 48px; color: var(--border); margin-bottom: 16px; }
.empty-state-content p { font-size: 16px; font-weight: 600; color: var(--text-white); margin-bottom: 8px; }
.empty-state-content span { font-size: 13px; color: var(--text-dim); }
.ocr-results-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 12px; background: var(--bg-main); padding: 15px; border-radius: 8px; margin-top: 10px; }
.ocr-result-item label { display: block; font-size: 11px; color: var(--text-dim); text-transform: uppercase; margin-bottom: 4px; }
.ocr-result-item span { font-size: 14px; color: var(--text-white); font-weight: 600; }
.checkbox-label { display: flex; align-items: center; gap: 10px; cursor: pointer; }
.checkbox-label input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--primary); }
.modal-large { max-width: 800px; }
.btn-small { padding: 8px 16px; font-size: 13px; }
@media (max-width: 768px) { .expense-stats { grid-template-columns: repeat(2, 1fr); } .form-row.three-cols, .form-row.two-cols { grid-template-columns: 1fr; } .upload-buttons { flex-direction: column; } .quick-actions-bar { flex-direction: column; } .line-item-row { grid-template-columns: 1fr; } }
@media (max-width: 480px) { .expense-stats { grid-template-columns: 1fr; } }
</style>

<script>
var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
var ocrData = null;

function openAddExpenseModal() { document.getElementById('add-expense-card').style.display = 'block'; document.getElementById('add-expense-card').scrollIntoView({ behavior: 'smooth' }); }
function openOCRModal() { document.getElementById('ocr-modal').classList.add('active'); }
function openExportModal() { document.getElementById('export-modal').classList.add('active'); updateExportOptions(); }
function closeModal(modalId) { var modal = document.getElementById(modalId); if (modal) { modal.classList.remove('active'); } }

function calculateExpenseTotal() { var subtotal = parseFloat(document.getElementById('expenseSubtotal').value) || 0; var tax = parseFloat(document.getElementById('expenseTax').value) || 0; document.getElementById('expenseTotal').value = (subtotal + tax).toFixed(2); }
function calculateEditTotal() { var subtotal = parseFloat(document.getElementById('edit-expense-subtotal').value) || 0; var tax = parseFloat(document.getElementById('edit-expense-tax').value) || 0; document.getElementById('edit-expense-total').value = (subtotal + tax).toFixed(2); }

function updateFileLabel(labelId, input) { var label = document.getElementById(labelId); if (input.files.length > 0) { label.textContent = input.files[0].name; label.style.color = '#10b981'; } else { label.textContent = 'Drag & drop file here or click to browse'; label.style.color = ''; } }

var lineItemCount = 0;
function addLineItem() { lineItemCount++; var container = document.getElementById('lineItemsContainer'); var row = document.createElement('div'); row.className = 'line-item-row'; row.id = 'lineItem' + lineItemCount; row.innerHTML = '<input type="text" placeholder="Item name" class="form-input line-item-name"><input type="number" placeholder="Qty" value="1" min="1" step="1" class="form-input line-item-qty" onchange="updateLineItems()"><input type="number" placeholder="Unit $" step="0.01" min="0" class="form-input line-item-price" onchange="updateLineItems()"><input type="number" placeholder="Total" step="0.01" class="form-input line-item-total" readonly><button type="button" class="btn-icon btn-delete" onclick="removeLineItem(' + lineItemCount + ')"><i class="fas fa-times"></i></button>'; container.appendChild(row); }
function removeLineItem(id) { var row = document.getElementById('lineItem' + id); if (row) row.remove(); updateLineItems(); }
function updateLineItems() { var items = []; document.querySelectorAll('.line-item-row').forEach(function(row) { var name = row.querySelector('.line-item-name').value; var qty = parseFloat(row.querySelector('.line-item-qty').value) || 1; var price = parseFloat(row.querySelector('.line-item-price').value) || 0; var total = qty * price; row.querySelector('.line-item-total').value = total.toFixed(2); if (name) { items.push({ item_name: name, quantity: qty, unit_price: price, total_price: total }); } }); document.getElementById('lineItemsJson').value = JSON.stringify(items); }

function editExpense(expense) { document.getElementById('edit-expense-id').value = expense.id; document.getElementById('edit-expense-date').value = expense.expense_date; document.getElementById('edit-vendor-name').value = expense.vendor_name || ''; document.getElementById('edit-expense-category').value = expense.category || ''; document.getElementById('edit-payment-method').value = expense.payment_method || ''; document.getElementById('edit-expense-subtotal').value = expense.subtotal || expense.amount; document.getElementById('edit-expense-tax').value = expense.tax_amount || 0; document.getElementById('edit-expense-total').value = expense.total_amount || expense.amount; document.getElementById('edit-expense-description').value = expense.description || ''; document.getElementById('edit-expense-modal').classList.add('active'); }

document.getElementById('ocrFileInput').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        var file = this.files[0];
        var reader = new FileReader();
        reader.onload = function(e) { document.getElementById('ocrPreviewImage').src = e.target.result; document.getElementById('ocrPreviewContainer').style.display = 'block'; };
        reader.readAsDataURL(file);
        document.getElementById('ocrLoadingIndicator').style.display = 'block';
        document.getElementById('ocrResultsContainer').style.display = 'none';
        document.getElementById('useOcrDataBtn').style.display = 'none';
        var formData = new FormData();
        formData.append('receipt_file', file);
        formData.append('action', 'ocr_scan');
        formData.append('csrf_token', csrfToken);
        fetch('process_expenses.php', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('ocrLoadingIndicator').style.display = 'none';
            if (data.success && data.ocr_data) {
                ocrData = data.ocr_data;
                document.getElementById('ocrVendor').textContent = ocrData.vendor || '-';
                document.getElementById('ocrDate').textContent = ocrData.date || '-';
                document.getElementById('ocrSubtotal').textContent = '$' + (ocrData.subtotal || 0).toFixed(2);
                document.getElementById('ocrTax').textContent = '$' + (ocrData.tax || 0).toFixed(2);
                document.getElementById('ocrTotal').textContent = '$' + (ocrData.total || 0).toFixed(2);
                document.getElementById('ocrResultsContainer').style.display = 'block';
                document.getElementById('useOcrDataBtn').style.display = 'inline-block';
            } else { alert('OCR processing failed: ' + (data.message || 'Unknown error')); }
        })
        .catch(function(error) { document.getElementById('ocrLoadingIndicator').style.display = 'none'; alert('Error processing receipt'); console.error(error); });
    }
});

function useOCRData() { if (!ocrData) return; closeModal('ocr-modal'); openAddExpenseModal(); document.getElementById('vendorName').value = ocrData.vendor || ''; document.getElementById('expenseDate').value = ocrData.date || ''; document.getElementById('expenseSubtotal').value = (ocrData.subtotal || 0).toFixed(2); document.getElementById('expenseTax').value = (ocrData.tax || 0).toFixed(2); document.getElementById('expenseTotal').value = (ocrData.total || 0).toFixed(2); }

function updateExportOptions() { var type = document.getElementById('exportType').value; document.getElementById('monthSelectGroup').style.display = (type === 'month') ? 'block' : 'none'; document.getElementById('quarterSelectGroup').style.display = (type === 'quarter') ? 'block' : 'none'; }

function exportExpenses() {
    var exportType = document.getElementById('exportType').value;
    var year = document.getElementById('exportYear').value;
    var month = document.getElementById('exportMonth').value;
    var quarter = document.getElementById('exportQuarter').value;
    var includeReceipts = document.getElementById('includeReceipts').checked;
    var formData = new FormData();
    formData.append('action', 'export_expenses');
    formData.append('export_type', exportType);
    formData.append('year', year);
    formData.append('month', month);
    formData.append('quarter', quarter);
    formData.append('include_receipts', includeReceipts);
    formData.append('csrf_token', csrfToken);
    fetch('process_expenses.php', { method: 'POST', body: formData })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var exp = data.export;
            var summary = '<p><strong>Period:</strong> ' + exp.period_start + ' to ' + exp.period_end + '</p>';
            summary += '<p><strong>Total Expenses:</strong> $' + parseFloat(exp.total_amount).toFixed(2) + '</p>';
            summary += '<p><strong>Number of Expenses:</strong> ' + exp.expense_count + '</p>';
            document.getElementById('exportSummaryContent').innerHTML = summary;
            document.getElementById('exportSummary').style.display = 'block';
            var csv = 'Date,Vendor,Category,Description,Subtotal,Tax,Total,Payment Method,Reference\n';
            exp.expenses.forEach(function(e) { csv += '"' + e.expense_date + '","' + (e.vendor_name || '').replace(/"/g, '""') + '","' + (e.category || '').replace(/"/g, '""') + '","' + (e.description || '').replace(/"/g, '""') + '",' + (e.subtotal || e.amount) + ',' + (e.tax_amount || 0) + ',' + (e.total_amount || e.amount) + ',"' + (e.payment_method || '') + '","' + (e.reference_number || '') + '"\n'; });
            var blob = new Blob([csv], { type: 'text/csv' });
            var url = window.URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'expenses_' + exp.period_start + '_to_' + exp.period_end + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
            showNotification('Export completed successfully!', 'success');
        } else { alert('Export failed: ' + (data.message || 'Unknown error')); }
    })
    .catch(function(error) { alert('Error exporting expenses'); console.error(error); });
}

function showNotification(message, type) { var existing = document.querySelector('.notification-widget'); if (existing) existing.remove(); var div = document.createElement('div'); div.className = 'notification-widget'; div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);'; if (type === 'success') { div.style.background = 'rgba(16, 185, 129, 0.95)'; div.style.color = '#fff'; } else { div.style.background = 'rgba(239, 68, 68, 0.95)'; div.style.color = '#fff'; } div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> <span>' + message + '</span>'; var closeBtn = document.createElement('button'); closeBtn.innerHTML = '&times;'; closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;'; closeBtn.onclick = function() { div.remove(); }; div.appendChild(closeBtn); document.body.appendChild(div); setTimeout(function() { if (div.parentElement) div.remove(); }, 5000); }

var dropZone = document.getElementById('dropZone');
var receiptFile = document.getElementById('receiptFile');
if (dropZone) {
    dropZone.addEventListener('dragover', function(e) { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', function() { dropZone.classList.remove('drag-over'); });
    dropZone.addEventListener('drop', function(e) { e.preventDefault(); dropZone.classList.remove('drag-over'); if (e.dataTransfer.files.length) { receiptFile.files = e.dataTransfer.files; updateFileLabel('receiptFileLabel', receiptFile); } });
    dropZone.addEventListener('click', function(e) { if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) { receiptFile.click(); } });
}

var ocrDropZone = document.getElementById('ocrDropZone');
var ocrFileInput = document.getElementById('ocrFileInput');
if (ocrDropZone) {
    ocrDropZone.addEventListener('dragover', function(e) { e.preventDefault(); ocrDropZone.classList.add('drag-over'); });
    ocrDropZone.addEventListener('dragleave', function() { ocrDropZone.classList.remove('drag-over'); });
    ocrDropZone.addEventListener('drop', function(e) { e.preventDefault(); ocrDropZone.classList.remove('drag-over'); if (e.dataTransfer.files.length) { ocrFileInput.files = e.dataTransfer.files; ocrFileInput.dispatchEvent(new Event('change')); } });
    ocrDropZone.addEventListener('click', function(e) { if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) { ocrFileInput.click(); } });
}

document.getElementById('expenseForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    var submitBtn = this.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    submitBtn.disabled = true;
    fetch(this.action, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
    .then(function(r) { return r.json(); })
    .then(function(data) { submitBtn.innerHTML = originalText; submitBtn.disabled = false; if (data.success) { showNotification(data.message || 'Expense added successfully!', 'success'); setTimeout(function() { location.reload(); }, 1500); } else { showNotification('Error: ' + (data.message || 'Failed to add expense'), 'error'); } })
    .catch(function() { submitBtn.innerHTML = originalText; submitBtn.disabled = false; showNotification('An error occurred', 'error'); });
});
</script>
