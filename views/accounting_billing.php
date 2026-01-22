<?php
// Fetch invoices
$invoicesQuery = "SELECT i.*, u.first_name, u.last_name, u.email
    FROM invoices i
    LEFT JOIN users u ON i.user_id = u.id
    ORDER BY i.invoice_date DESC
    LIMIT 20";
$invoices = $pdo->query($invoicesQuery);

// Fetch recent payments
$paymentsQuery = "SELECT p.*, i.invoice_number, u.first_name, u.last_name
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.payment_date DESC
    LIMIT 5";
$payments = $pdo->query($paymentsQuery);
?>
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
            <input type="text" class="form-input-small" placeholder="Search invoices..." data-filter="search">
            <select class="form-input-small" data-filter="status">
                <option>All Status</option>
                <option>Paid</option>
                <option>Pending</option>
                <option>Overdue</option>
                <option>Draft</option>
            </select>
            <select class="form-input-small" data-filter="date-range">
                <option>This Month</option>
                <option>Last Month</option>
                <option>Last 3 Months</option>
                <option>This Year</option>
                <option>Custom Range</option>
            </select>
        </div>
        <button class="btn-primary" data-action="add" data-modal="create-invoice-modal"><i class="fas fa-plus"></i> Create Invoice</button>
    </div>

    <!-- Invoices Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Invoices</h3>
            <div class="header-actions">
                <button class="btn-secondary" data-action="export" data-type="invoices"><i class="fas fa-file-export"></i> Export</button>
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
                        <?php if($invoices && $invoices->rowCount() > 0): ?>
                            <?php while($invoice = $invoices->fetch()): 
                                $initials = strtoupper(substr($invoice['first_name'], 0, 1) . substr($invoice['last_name'], 0, 1));
                                $statusClass = strtolower($invoice['status']);
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td>
                                    <div class="client-info">
                                        <div class="client-avatar"><?= $initials ?></div>
                                        <span><?= htmlspecialchars($invoice['first_name'] . ' ' . $invoice['last_name']) ?></span>
                                    </div>
                                </td>
                                <td><?= date('M j, Y', strtotime($invoice['invoice_date'])) ?></td>
                                <td><?= date('M j, Y', strtotime($invoice['due_date'])) ?></td>
                                <td><strong>$<?= number_format($invoice['amount'], 2) ?></strong></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($invoice['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Download"><i class="fas fa-download"></i></button>
                                        <button class="btn-icon" title="Email"><i class="fas fa-envelope"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 24px;">
                                    <p class="placeholder-text">No invoices found.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
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
                <?php if($payments && $payments->rowCount() > 0): ?>
                    <?php while($payment = $payments->fetch()): ?>
                        <div class="payment-item">
                            <div class="payment-icon">
                                <i class="fas fa-<?= $payment['payment_method'] === 'credit_card' ? 'credit-card' : 'money-check' ?>"></i>
                            </div>
                            <div class="payment-details">
                                <h4>Payment Received - <?= htmlspecialchars($payment['invoice_number']) ?></h4>
                                <p><?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?> • <?= ucwords(str_replace('_', ' ', $payment['payment_method'])) ?></p>
                                <span class="payment-date"><?= date('M j, Y \a\t g:i A', strtotime($payment['payment_date'])) ?></span>
                            </div>
                            <div class="payment-amount">$<?= number_format($payment['amount'], 2) ?></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="placeholder-text">No recent payments.</p>
                <?php endif; ?>
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
    padding: 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.data-table td {
    padding: 16px;
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

<!-- Create Invoice Modal -->
<div id="create-invoice-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Create Invoice</h2>
            <button class="modal-close" onclick="closeModal('create-invoice-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_invoice">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Client *</label>
                    <select name="user_id" class="form-input" required>
                        <option value="">Select Client</option>
                        <?php
                        // Fetch users for dropdown
                        try {
                            $userStmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role IN ('athlete', 'parent') ORDER BY first_name, last_name");
                            while ($user = $userStmt->fetch(PDO::FETCH_ASSOC)) {
                                echo '<option value="' . $user['id'] . '">' . 
                                     htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) . 
                                     ' (' . htmlspecialchars($user['email']) . ')</option>';
                            }
                        } catch (PDOException $e) {
                            error_log("User fetch error: " . $e->getMessage());
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" name="invoice_date" class="form-input" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-input" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-textarea" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Line Items</label>
                    <div id="line-items">
                        <div class="line-item" style="display: flex; gap: 12px; margin-bottom: 12px;">
                            <input type="text" name="item_description[]" class="form-input" placeholder="Description" style="flex: 2;">
                            <input type="number" name="item_quantity[]" class="form-input" placeholder="Qty" step="1" min="1" value="1" style="flex: 1;">
                            <input type="number" name="item_price[]" class="form-input" placeholder="Price" step="0.01" min="0" style="flex: 1;">
                        </div>
                    </div>
                    <button type="button" class="btn-secondary btn-small" onclick="addLineItem()">
                        <i class="fas fa-plus"></i> Add Line Item
                    </button>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Total Amount *</label>
                    <input type="number" name="total_amount" class="form-input" step="0.01" min="0" required id="invoice-total">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="2"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('create-invoice-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<script>
function addLineItem() {
    const container = document.getElementById('line-items');
    const newItem = document.createElement('div');
    newItem.className = 'line-item';
    newItem.style.cssText = 'display: flex; gap: 12px; margin-bottom: 12px;';
    newItem.innerHTML = `
        <input type="text" name="item_description[]" class="form-input" placeholder="Description" style="flex: 2;">
        <input type="number" name="item_quantity[]" class="form-input" placeholder="Qty" step="1" min="1" value="1" style="flex: 1;">
        <input type="number" name="item_price[]" class="form-input" placeholder="Price" step="0.01" min="0" style="flex: 1;">
        <button type="button" class="btn-icon" onclick="this.parentElement.remove(); calculateInvoiceTotal();" style="flex-shrink: 0;">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(newItem);
    
    // Add event listeners to calculate total
    const priceInputs = newItem.querySelectorAll('input[name="item_price[]"], input[name="item_quantity[]"]');
    priceInputs.forEach(input => {
        input.addEventListener('input', calculateInvoiceTotal);
    });
}

function calculateInvoiceTotal() {
    const items = document.querySelectorAll('.line-item');
    let total = 0;
    
    items.forEach(item => {
        const qty = parseFloat(item.querySelector('input[name="item_quantity[]"]')?.value || 0);
        const price = parseFloat(item.querySelector('input[name="item_price[]"]')?.value || 0);
        total += qty * price;
    });
    
    const totalInput = document.getElementById('invoice-total');
    if (totalInput) {
        totalInput.value = total.toFixed(2);
    }
}

// Add event listeners when modal opens
document.addEventListener('DOMContentLoaded', function() {
    const lineItems = document.querySelectorAll('.line-item input[name="item_price[]"], .line-item input[name="item_quantity[]"]');
    lineItems.forEach(input => {
        input.addEventListener('input', calculateInvoiceTotal);
    });
});
</script>
