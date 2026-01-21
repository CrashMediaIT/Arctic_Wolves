<?php
// views/accounts_payable.php - Upload receipts and create expenses
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

// Get expense categories
$categories = $pdo->query("
    SELECT * FROM expense_categories 
    WHERE is_active = 1 
    ORDER BY display_order
")->fetchAll(PDO::FETCH_ASSOC);

// Get recent expenses
$recent_expenses = $pdo->query("
    SELECT e.*, ec.name as category_name, u.first_name as created_by_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.id
    JOIN users u ON e.created_by = u.id
    ORDER BY e.expense_date DESC, e.created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="accounts-payable">
    <div class="page-header">
        <h2><i class="fas fa-receipt"></i> Accounts Payable</h2>
        <button onclick="openExpenseModal()" class="btn-primary">
            <i class="fas fa-plus"></i> Add Expense
        </button>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-<?php echo $_GET['status'] === 'success' ? 'success' : 'error'; ?>">
            <?php 
            if ($_GET['status'] === 'success') {
                echo 'Expense saved successfully!';
            } else {
                echo htmlspecialchars($_GET['message'] ?? 'An error occurred.', ENT_QUOTES, 'UTF-8');
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Recent Expenses -->
    <div class="expenses-table">
        <h3>Recent Expenses</h3>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Vendor</th>
                    <th>Category</th>
                    <th>Description</th>
                    <th>Amount</th>
                    <th>Tax</th>
                    <th>Total</th>
                    <th>Receipt</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recent_expenses as $expense): ?>
                <tr>
                    <td><?php echo date('M j, Y', strtotime($expense['expense_date'])); ?></td>
                    <td><?php echo htmlspecialchars($expense['vendor_name']); ?></td>
                    <td>
                        <span class="category-badge">
                            <?php echo htmlspecialchars($expense['category_name']); ?>
                        </span>
                    </td>
                    <td>
                        <?php echo htmlspecialchars(substr($expense['description'], 0, 50)); ?>
                        <?php if (strlen($expense['description']) > 50) echo '...'; ?>
                    </td>
                    <td>$<?php echo number_format($expense['amount'], 2); ?></td>
                    <td>$<?php echo number_format($expense['tax_amount'], 2); ?></td>
                    <td><strong>$<?php echo number_format($expense['total_amount'], 2); ?></strong></td>
                    <td>
                        <?php if ($expense['receipt_file']): ?>
                            <a href="uploads/receipts/<?php echo htmlspecialchars($expense['receipt_file']); ?>" 
                               target="_blank" class="btn-icon" title="View Receipt">
                                <i class="fas fa-file-image"></i>
                            </a>
                        <?php else: ?>
                            <span style="color: #64748b;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <button onclick="viewExpense(<?php echo $expense['id']; ?>)" 
                                class="btn-icon" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button onclick="editExpense(<?php echo htmlspecialchars(json_encode($expense)); ?>)" 
                                class="btn-icon" title="Edit">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteExpense(<?php echo $expense['id']; ?>)" 
                                class="btn-icon btn-danger" title="Delete">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($recent_expenses)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 40px;">
                        No expenses recorded yet.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Expense Modal -->
<div id="expenseModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeExpenseModal()">&times;</span>
        <h3 id="modalTitle">Add Expense</h3>
        
        <form action="process_expenses.php" method="POST" enctype="multipart/form-data" id="expenseForm">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create" id="formAction">
            <input type="hidden" name="expense_id" id="expenseId">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Vendor Name <span class="required">*</span></label>
                    <input type="text" name="vendor_name" id="vendorName" required>
                </div>
                
                <div class="form-group">
                    <label>Expense Date <span class="required">*</span></label>
                    <input type="date" name="expense_date" id="expenseDate" required value="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="category_id" id="categoryId" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>">
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" id="paymentMethod">
                        <option value="">Select Method</option>
                        <option value="Credit Card">Credit Card</option>
                        <option value="Debit">Debit</option>
                        <option value="Cash">Cash</option>
                        <option value="E-Transfer">E-Transfer</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="expenseDescription" rows="3"></textarea>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Amount (before tax) <span class="required">*</span></label>
                    <input type="number" name="amount" id="expenseAmount" step="0.01" min="0" required onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label>Tax Amount</label>
                    <input type="number" name="tax_amount" id="taxAmount" step="0.01" min="0" value="0" onchange="calculateTotal()">
                </div>
                
                <div class="form-group">
                    <label>Total Amount <span class="required">*</span></label>
                    <input type="number" name="total_amount" id="totalAmount" step="0.01" min="0" required readonly>
                </div>
            </div>
            
            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" id="referenceNumber" placeholder="Invoice #, PO #, etc.">
            </div>
            
            <div class="form-group">
                <label>Upload Receipt</label>
                <input type="file" name="receipt_file" id="receiptFile" accept="image/*,.pdf" onchange="previewReceipt(this)">
                <small style="color: #64748b;">Supported: Images (JPG, PNG) and PDF. OCR will extract data automatically.</small>
            </div>
            
            <div id="receiptPreview" style="display: none; margin-top: 15px;">
                <img id="previewImage" style="max-width: 100%; max-height: 300px; border-radius: 8px;">
            </div>
            
            <div id="ocrResults" style="display: none; margin-top: 15px;">
                <div class="ocr-notice">
                    <i class="fas fa-robot"></i> OCR extracted data (review and edit as needed):
                </div>
                <pre id="ocrData" style="background: #020305; padding: 15px; border-radius: 8px; color: #94a3b8; font-size: 12px;"></pre>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeExpenseModal()">Cancel</button>
                <button type="submit" class="btn-primary">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<style>
.accounts-payable {
    padding: 20px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background: #10b981;
    color: white;
}

.alert-error {
    background: #ef4444;
    color: white;
}

.expenses-table {
    background: #0a0f16;
    border-radius: 10px;
    padding: 20px;
    overflow-x: auto;
}

.expenses-table h3 {
    margin: 0 0 20px 0;
    color: #e2e8f0;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th {
    padding: 12px;
    background: #020305;
    color: #94a3b8;
    text-align: left;
    font-size: 12px;
    text-transform: uppercase;
}

td {
    padding: 12px;
    border-bottom: 1px solid #1e293b;
    color: #e2e8f0;
}

.category-badge {
    display: inline-block;
    padding: 4px 12px;
    background: #7000a4;
    color: white;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.btn-icon {
    background: transparent;
    border: 1px solid #334155;
    color: #94a3b8;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    margin-right: 5px;
}

.btn-icon:hover {
    background: #1e293b;
    color: #fff;
}

.btn-icon.btn-danger:hover {
    background: #ef4444;
    border-color: #ef4444;
    color: white;
}

.btn-primary {
    background: var(--primary, #7000a4);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
}

.btn-secondary {
    background: #334155;
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
}

.modal {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    overflow-y: auto;
}

.modal-content {
    background: #0a0f16;
    margin: 50px auto;
    padding: 30px;
    border-radius: 12px;
    max-width: 700px;
    position: relative;
    color: #e2e8f0;
}

.modal-large {
    max-width: 900px;
}

.close {
    position: absolute;
    right: 20px;
    top: 20px;
    font-size: 28px;
    font-weight: bold;
    color: #94a3b8;
    cursor: pointer;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-row.three-col {
    grid-template-columns: 1fr 1fr 1fr;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: #94a3b8;
    font-weight: 600;
    font-size: 14px;
}

.form-group input,
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 10px;
    background: #020305;
    border: 1px solid #334155;
    border-radius: 6px;
    color: #e2e8f0;
    font-size: 14px;
}

.required {
    color: #ef4444;
}

.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 30px;
}

.ocr-notice {
    background: #1e293b;
    padding: 10px 15px;
    border-radius: 6px;
    color: #94a3b8;
    margin-bottom: 10px;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function openExpenseModal() {
    document.getElementById('modalTitle').textContent = 'Add Expense';
    document.getElementById('formAction').value = 'create';
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseId').value = '';
    document.getElementById('receiptPreview').style.display = 'none';
    document.getElementById('ocrResults').style.display = 'none';
    document.getElementById('expenseModal').style.display = 'block';
}

function closeExpenseModal() {
    document.getElementById('expenseModal').style.display = 'none';
}

function calculateTotal() {
    const amount = parseFloat(document.getElementById('expenseAmount').value) || 0;
    const tax = parseFloat(document.getElementById('taxAmount').value) || 0;
    document.getElementById('totalAmount').value = (amount + tax).toFixed(2);
}

function previewReceipt(input) {
    const preview = document.getElementById('receiptPreview');
    const img = document.getElementById('previewImage');
    
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
            
            // Simulate OCR (placeholder - real OCR would happen server-side)
            simulateOCR();
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function simulateOCR() {
    // Placeholder for OCR functionality
    document.getElementById('ocrResults').style.display = 'block';
    document.getElementById('ocrData').textContent = 
        'OCR processing simulated.\nIn production, this would use Tesseract.js or server-side OCR\nto extract vendor name, amount, date, and line items from the receipt.';
}

function editExpense(expense) {
    document.getElementById('modalTitle').textContent = 'Edit Expense';
    document.getElementById('formAction').value = 'update';
    document.getElementById('expenseId').value = expense.id;
    document.getElementById('vendorName').value = expense.vendor_name;
    document.getElementById('expenseDate').value = expense.expense_date;
    document.getElementById('categoryId').value = expense.category_id;
    document.getElementById('paymentMethod').value = expense.payment_method || '';
    document.getElementById('expenseDescription').value = expense.description || '';
    document.getElementById('expenseAmount').value = expense.amount;
    document.getElementById('taxAmount').value = expense.tax_amount;
    document.getElementById('totalAmount').value = expense.total_amount;
    document.getElementById('referenceNumber').value = expense.reference_number || '';
    document.getElementById('expenseModal').style.display = 'block';
}

function deleteExpense(id) {
    if (confirm('Are you sure you want to delete this expense?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_expenses.php';
        
        const csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = 'csrf_token';
        csrf.value = '<?php echo generateCsrfToken(); ?>';
        
        const action = document.createElement('input');
        action.type = 'hidden';
        action.name = 'action';
        action.value = 'delete';
        
        const expId = document.createElement('input');
        expId.type = 'hidden';
        expId.name = 'expense_id';
        expId.value = id;
        
        form.appendChild(csrf);
        form.appendChild(action);
        form.appendChild(expId);
        document.body.appendChild(form);
        form.submit();
    }
}

function viewExpense(id) {
    // Could open a detailed view modal
    window.open('process_expenses.php?action=view&expense_id=' + id, '_blank');
}
</script>
