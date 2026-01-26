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
    LIMIT 8";
$payments = $pdo->query($paymentsQuery);

// Fetch billing statistics
$statsQuery = "SELECT 
    COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as total_paid,
    COALESCE(SUM(CASE WHEN status IN ('sent', 'pending') THEN total_amount ELSE 0 END), 0) as total_pending,
    COALESCE(SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END), 0) as total_overdue,
    COUNT(*) as total_invoices
    FROM invoices";
try {
    $statsResult = $pdo->query($statsQuery);
    $stats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $stats = ['total_paid' => 0, 'total_pending' => 0, 'total_overdue' => 0, 'total_invoices' => 0];
}
?>
<!-- Accounting Billing View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-file-invoice-dollar"></i> Billing & Invoices
    </h1>
    <p class="page-description">Manage invoices, track payments, and monitor billing status</p>
</div>

<div class="billing-content">
    <!-- Billing Statistics Cards -->
    <div class="billing-stats">
        <div class="billing-stat-card paid">
            <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['total_paid'], 2) ?></span>
                <span class="stat-label">Total Collected</span>
            </div>
        </div>
        <div class="billing-stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['total_pending'], 2) ?></span>
                <span class="stat-label">Pending Payment</span>
            </div>
        </div>
        <div class="billing-stat-card overdue">
            <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['total_overdue'], 2) ?></span>
                <span class="stat-label">Overdue</span>
            </div>
        </div>
        <div class="billing-stat-card total">
            <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $stats['total_invoices'] ?></span>
                <span class="stat-label">Total Invoices</span>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-input-small" placeholder="Search invoices..." data-filter="search">
            </div>
            <select class="form-input-small" data-filter="status">
                <option value="">All Status</option>
                <option value="paid">Paid</option>
                <option value="sent">Sent</option>
                <option value="pending">Pending</option>
                <option value="overdue">Overdue</option>
                <option value="draft">Draft</option>
            </select>
            <select class="form-input-small" data-filter="date-range">
                <option value="this_month">This Month</option>
                <option value="last_month">Last Month</option>
                <option value="last_3_months">Last 3 Months</option>
                <option value="this_year">This Year</option>
                <option value="custom">Custom Range</option>
            </select>
        </div>
        <div class="action-buttons">
            <button class="btn-secondary" data-action="export" data-type="invoices"><i class="fas fa-file-export"></i> Export</button>
            <button class="btn-primary" data-action="add" data-modal="create-invoice-modal"><i class="fas fa-plus"></i> Create Invoice</button>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Invoices</h3>
            <div class="header-actions">
                <span class="results-count"><?= $invoices ? $invoices->rowCount() : 0 ?> invoices</span>
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
                                <td><strong>$<?= number_format($invoice['total_amount'] ?? 0, 2) ?></strong></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($invoice['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="View" data-action="view-invoice" data-invoice-id="<?= $invoice['id'] ?>"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Download" data-action="download-invoice" data-invoice-id="<?= $invoice['id'] ?>"><i class="fas fa-download"></i></button>
                                        <button class="btn-icon" title="Email" data-action="email-invoice" data-invoice-id="<?= $invoice['id'] ?>"><i class="fas fa-envelope"></i></button>
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
/* Billing Statistics Cards */
.billing-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.billing-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.billing-stat-card::after {
    content: '';
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100%;
    opacity: 0.05;
    pointer-events: none;
}

.billing-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.billing-stat-card .stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.billing-stat-card.paid .stat-icon { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.billing-stat-card.pending .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.billing-stat-card.overdue .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.billing-stat-card.total .stat-icon { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }

.billing-stat-card .stat-info { flex: 1; }

.billing-stat-card .stat-value {
    font-size: 26px;
    font-weight: 900;
    color: var(--text-white);
    display: block;
    margin-bottom: 4px;
}

.billing-stat-card .stat-label {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
}

/* Search Box */
.search-box {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box i {
    position: absolute;
    left: 14px;
    color: var(--text-dim);
    font-size: 14px;
    pointer-events: none;
}

.search-box input {
    padding-left: 40px;
    min-width: 250px;
}

.action-buttons {
    display: flex;
    gap: 12px;
}

.results-count {
    font-size: 13px;
    color: var(--text-dim);
    font-weight: 500;
}

.header-actions {
    display: flex;
    gap: 10px;
    align-items: center;
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
    font-size: 11px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    border-bottom: 2px solid var(--border);
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
    background: rgba(107, 70, 193, 0.05);
}

.client-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.client-avatar {
    width: 38px;
    height: 38px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 800;
    color: #fff;
}

.status-badge {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.paid { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.sent { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.overdue { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-badge.draft { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }

.payments-list {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 16px;
}

.payment-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    transition: all 0.3s ease;
}

.payment-item:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
}

.payment-icon {
    width: 52px;
    height: 52px;
    background: rgba(16, 185, 129, 0.15);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    color: #10b981;
    flex-shrink: 0;
}

.payment-details {
    flex: 1;
    min-width: 0;
}

.payment-details h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.payment-details p {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 4px;
}

.payment-date {
    font-size: 11px;
    color: var(--text-dim);
}

.payment-amount {
    font-size: 20px;
    font-weight: 900;
    color: #10b981;
    white-space: nowrap;
}

.placeholder-text {
    color: var(--text-dim);
    text-align: center;
    padding: 40px;
    font-size: 14px;
}

@media (max-width: 768px) {
    .billing-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .payments-list {
        grid-template-columns: 1fr;
    }
    
    .action-bar {
        flex-direction: column;
        gap: 16px;
    }
    
    .filter-group {
        flex-direction: column;
        width: 100%;
    }
    
    .search-box input {
        min-width: auto;
        width: 100%;
    }
    
    .action-buttons {
        width: 100%;
        justify-content: stretch;
    }
    
    .action-buttons .btn-primary,
    .action-buttons .btn-secondary {
        flex: 1;
    }
}

@media (max-width: 480px) {
    .billing-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Create Invoice Modal -->
<div id="create-invoice-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Create Invoice</h2>
            <button class="modal-close" onclick="closeModal('create-invoice-modal')">&times;</button>
        </div>
        <div id="invoice-success-message" class="success-widget" style="display: none;">
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <div class="success-text">Invoice created successfully!</div>
            <button type="button" class="btn-primary btn-small" onclick="resetInvoiceForm()">Create Another Invoice</button>
        </div>
        <form id="create-invoice-form" method="POST" action="process_admin_action.php">
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
                <button type="button" class="btn-secondary" onclick="closeModal('create-invoice-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<style>
.success-widget {
    text-align: center;
    padding: 30px;
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid #10b981;
    border-radius: 12px;
    margin: 20px;
}
.success-widget .success-icon {
    font-size: 48px;
    color: #10b981;
    margin-bottom: 15px;
}
.success-widget .success-text {
    font-size: 18px;
    font-weight: 700;
    color: #10b981;
    margin-bottom: 20px;
}
</style>

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

function resetInvoiceForm() {
    const form = document.getElementById('create-invoice-form');
    const successMsg = document.getElementById('invoice-success-message');
    
    // Reset form
    form.reset();
    form.style.display = 'block';
    successMsg.style.display = 'none';
    
    // Reset dates to defaults
    form.querySelector('[name="invoice_date"]').value = new Date().toISOString().split('T')[0];
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    form.querySelector('[name="due_date"]').value = dueDate.toISOString().split('T')[0];
    
    // Reset line items to single item
    const lineItemsContainer = document.getElementById('line-items');
    lineItemsContainer.innerHTML = `
        <div class="line-item" style="display: flex; gap: 12px; margin-bottom: 12px;">
            <input type="text" name="item_description[]" class="form-input" placeholder="Description" style="flex: 2;">
            <input type="number" name="item_quantity[]" class="form-input" placeholder="Qty" step="1" min="1" value="1" style="flex: 1;">
            <input type="number" name="item_price[]" class="form-input" placeholder="Price" step="0.01" min="0" style="flex: 1;">
        </div>
    `;
}

// Add event listeners when modal opens
document.addEventListener('DOMContentLoaded', function() {
    const lineItems = document.querySelectorAll('.line-item input[name="item_price[]"], .line-item input[name="item_quantity[]"]');
    lineItems.forEach(input => {
        input.addEventListener('input', calculateInvoiceTotal);
    });
    
    // Handle invoice form submission via AJAX
    const invoiceForm = document.getElementById('create-invoice-form');
    if (invoiceForm) {
        invoiceForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
            submitBtn.disabled = true;
            
            fetch('process_admin_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is JSON or redirect
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                // If it was a redirect (success), show success message
                return { success: true };
            })
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                // Show success message in the modal
                const form = document.getElementById('create-invoice-form');
                const successMsg = document.getElementById('invoice-success-message');
                form.style.display = 'none';
                successMsg.style.display = 'block';
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                console.error('Error:', error);
                alert('Error creating invoice. Please try again.');
            });
        });
    }
    
    // Invoice action button handlers
    document.querySelectorAll('[data-action="view-invoice"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const invoiceId = this.getAttribute('data-invoice-id');
            window.location.href = '?page=billing_dashboard&view_invoice=' + invoiceId;
        });
    });
    
    document.querySelectorAll('[data-action="download-invoice"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const invoiceId = this.getAttribute('data-invoice-id');
            window.open('process_admin_action.php?action=download_invoice&invoice_id=' + invoiceId, '_blank');
        });
    });
    
    document.querySelectorAll('[data-action="email-invoice"]').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const invoiceId = this.getAttribute('data-invoice-id');
            const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
            
            if (!csrfToken) {
                alert('Security error: CSRF token not found. Please refresh the page.');
                return;
            }
            
            if (confirm('Send invoice email to the client?')) {
                fetch('process_admin_action.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=email_invoice&invoice_id=' + invoiceId + '&csrf_token=' + encodeURIComponent(csrfToken)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Invoice email sent successfully!');
                    } else {
                        alert('Error: ' + (data.message || 'Failed to send email'));
                    }
                })
                .catch(error => {
                    alert('Error sending invoice email. Please try again or contact support if the issue persists.');
                    console.error('Invoice email error:', error);
                });
            }
        });
    });
});
</script>
