<?php
// =========================================================
// FINANCE BILLING TAB
// Stripe invoice creation, manual payments, invoices table,
// payments history with filtering and export
// =========================================================

// Load Stripe configuration
$stripeSettingsQuery = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('stripe_publishable_key', 'stripe_secret_key', 'currency', 'tax_rate', 'tax_name')";
$stripeSettings = $pdo->query($stripeSettingsQuery)->fetchAll(PDO::FETCH_KEY_PAIR);
$stripeConfigured = !empty($stripeSettings['stripe_publishable_key']) && !empty($stripeSettings['stripe_secret_key']);
$currency = $stripeSettings['currency'] ?? 'CAD';
$taxRate = floatval($stripeSettings['tax_rate'] ?? 13.00);
$taxName = $stripeSettings['tax_name'] ?? 'HST';

// Get filter parameters
$invoiceFilter = $_GET['invoice_filter'] ?? 'month';
$invoiceYear = $_GET['invoice_year'] ?? date('Y');
$paymentFilter = $_GET['payment_filter'] ?? 'month';
$paymentYear = $_GET['payment_year'] ?? date('Y');

// Calculate invoice date range
switch ($invoiceFilter) {
    case 'day':
        $invoiceStartDate = date('Y-m-d');
        $invoiceEndDate = date('Y-m-d');
        break;
    case 'week':
        $invoiceStartDate = date('Y-m-d', strtotime('monday this week'));
        $invoiceEndDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $invoiceStartDate = date('Y-m-01');
        $invoiceEndDate = date('Y-m-t');
        break;
    case 'year':
        $invoiceStartDate = $invoiceYear . '-01-01';
        $invoiceEndDate = $invoiceYear . '-12-31';
        break;
    default:
        $invoiceStartDate = date('Y-m-01');
        $invoiceEndDate = date('Y-m-t');
}

// Calculate payment date range
switch ($paymentFilter) {
    case 'day':
        $paymentStartDate = date('Y-m-d');
        $paymentEndDate = date('Y-m-d');
        break;
    case 'week':
        $paymentStartDate = date('Y-m-d', strtotime('monday this week'));
        $paymentEndDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $paymentStartDate = date('Y-m-01');
        $paymentEndDate = date('Y-m-t');
        break;
    case 'year':
        $paymentStartDate = $paymentYear . '-01-01';
        $paymentEndDate = $paymentYear . '-12-31';
        break;
    default:
        $paymentStartDate = date('Y-m-01');
        $paymentEndDate = date('Y-m-t');
}

// Fetch invoices with filters
try {
    $invoicesQuery = "SELECT i.*, u.first_name, u.last_name, u.email
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE DATE(i.invoice_date) BETWEEN :start_date AND :end_date
        ORDER BY i.invoice_date DESC";
    $invoicesStmt = $pdo->prepare($invoicesQuery);
    $invoicesStmt->execute([':start_date' => $invoiceStartDate, ':end_date' => $invoiceEndDate]);
    $invoices = $invoicesStmt->fetchAll(PDO::FETCH_ASSOC);
    $invoices = decryptUserRows($invoices);
} catch (PDOException $e) {
    error_log("Invoice fetch error: " . $e->getMessage());
    $invoices = [];
}

// Fetch payments with filters
try {
    $paymentsQuery = "SELECT p.*, i.invoice_number, u.first_name, u.last_name
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        LEFT JOIN users u ON p.user_id = u.id
        WHERE DATE(p.payment_date) BETWEEN :start_date AND :end_date
        ORDER BY p.payment_date DESC";
    $paymentsStmt = $pdo->prepare($paymentsQuery);
    $paymentsStmt->execute([':start_date' => $paymentStartDate, ':end_date' => $paymentEndDate]);
    $payments = $paymentsStmt->fetchAll(PDO::FETCH_ASSOC);
    $payments = decryptUserRows($payments);
} catch (PDOException $e) {
    error_log("Payments fetch error: " . $e->getMessage());
    $payments = [];
}

// Fetch billing statistics (includes invoices, POS transactions, and shop orders)
try {
    // Invoice stats
    $invoiceStatsQuery = "SELECT 
        COALESCE(SUM(CASE WHEN status = 'paid' THEN total_amount ELSE 0 END), 0) as invoice_paid,
        COALESCE(SUM(CASE WHEN status IN ('sent', 'pending') THEN total_amount ELSE 0 END), 0) as total_pending,
        COALESCE(SUM(CASE WHEN status = 'overdue' THEN total_amount ELSE 0 END), 0) as total_overdue,
        COUNT(*) as total_invoices
        FROM invoices";
    $invoiceStatsResult = $pdo->query($invoiceStatsQuery);
    $invoiceStats = $invoiceStatsResult->fetch(PDO::FETCH_ASSOC);
    
    // POS transactions revenue (Stripe + cash) - handles card, cash, and mixed payment methods
    $posStatsQuery = "SELECT 
        COALESCE(SUM(total), 0) as pos_collected,
        COALESCE(SUM(CASE 
            WHEN payment_method = 'card' THEN total 
            WHEN payment_method = 'mixed' THEN COALESCE(card_amount, 0) 
            ELSE 0 END), 0) as pos_card,
        COALESCE(SUM(CASE 
            WHEN payment_method = 'cash' THEN total 
            WHEN payment_method = 'mixed' THEN COALESCE(cash_amount, 0) 
            ELSE 0 END), 0) as pos_cash
        FROM pos_transactions 
        WHERE status = 'completed'";
    $posStatsResult = $pdo->query($posStatsQuery);
    $posStats = $posStatsResult->fetch(PDO::FETCH_ASSOC);
    
    // Shop orders revenue (Stripe payments)
    $shopStatsQuery = "SELECT 
        COALESCE(SUM(total), 0) as shop_collected
        FROM shop_orders 
        WHERE payment_status = 'paid'";
    $shopStatsResult = $pdo->query($shopStatsQuery);
    $shopStats = $shopStatsResult->fetch(PDO::FETCH_ASSOC);
    
    // Combine all collected revenue
    $stats = [
        'total_paid' => ($invoiceStats['invoice_paid'] ?? 0) + ($posStats['pos_collected'] ?? 0) + ($shopStats['shop_collected'] ?? 0),
        'total_pending' => $invoiceStats['total_pending'] ?? 0,
        'total_overdue' => $invoiceStats['total_overdue'] ?? 0,
        'total_invoices' => $invoiceStats['total_invoices'] ?? 0,
        'pos_collected' => $posStats['pos_collected'] ?? 0,
        'pos_card' => $posStats['pos_card'] ?? 0,
        'pos_cash' => $posStats['pos_cash'] ?? 0,
        'shop_collected' => $shopStats['shop_collected'] ?? 0
    ];
} catch (PDOException $e) {
    $stats = ['total_paid' => 0, 'total_pending' => 0, 'total_overdue' => 0, 'total_invoices' => 0, 'pos_collected' => 0, 'pos_card' => 0, 'pos_cash' => 0, 'shop_collected' => 0];
}

// Fetch users for invoice creation dropdown
try {
    $usersStmt = $pdo->query("SELECT id, first_name, last_name, email FROM users WHERE role IN ('athlete', 'parent') AND is_active = 1 ORDER BY first_name, last_name");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
    $users = decryptUserRows($users);
} catch (PDOException $e) {
    $users = [];
}

// Fetch unpaid invoices for payment recording
try {
    $unpaidStmt = $pdo->query("SELECT i.id, i.invoice_number, i.total_amount, u.first_name, u.last_name 
        FROM invoices i LEFT JOIN users u ON i.user_id = u.id 
        WHERE i.status IN ('sent', 'pending', 'overdue', 'draft') 
        ORDER BY i.invoice_date DESC");
    $unpaidInvoices = $unpaidStmt->fetchAll(PDO::FETCH_ASSOC);
    $unpaidInvoices = decryptUserRows($unpaidInvoices);
} catch (PDOException $e) {
    $unpaidInvoices = [];
}

// Get available years for filtering
$years = [];
for ($y = date('Y'); $y >= date('Y') - 5; $y--) {
    $years[] = $y;
}
?>

<!-- Stripe Integration Status -->
<div class="stripe-status-bar <?= $stripeConfigured ? 'configured' : 'not-configured' ?>">
    <div class="stripe-status-icon">
        <i class="fab fa-stripe-s"></i>
    </div>
    <div class="stripe-status-info">
        <?php if ($stripeConfigured): ?>
            <strong>Stripe Payment Processing Active</strong>
            <span>Currency: <?= htmlspecialchars($currency) ?> | Tax: <?= htmlspecialchars($taxName) ?> (<?= number_format($taxRate, 2) ?>%)</span>
        <?php else: ?>
            <strong>Stripe Not Configured</strong>
            <span>Configure Stripe in <a href="?page=system_tools&tab=payments">System Tools → Payments</a> to enable online payments</span>
        <?php endif; ?>
    </div>
    <?php if ($stripeConfigured): ?>
    <div class="stripe-status-badge active">
        <i class="fas fa-check-circle"></i> Active
    </div>
    <?php else: ?>
    <div class="stripe-status-badge inactive">
        <i class="fas fa-times-circle"></i> Inactive
    </div>
    <?php endif; ?>
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
    
    <!-- Revenue Breakdown Cards -->
    <div class="billing-stats revenue-breakdown" style="margin-top: 16px;">
        <div class="billing-stat-card pos-card">
            <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fas fa-cash-register"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['pos_collected'] ?? 0, 2) ?></span>
                <span class="stat-label">POS Revenue</span>
            </div>
        </div>
        <div class="billing-stat-card stripe-card">
            <div class="stat-icon" style="background: rgba(99, 102, 241, 0.15); color: #6366f1;"><i class="fas fa-credit-card"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['pos_card'] ?? 0, 2) ?></span>
                <span class="stat-label">POS Card/Stripe</span>
            </div>
        </div>
        <div class="billing-stat-card cash-card">
            <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fas fa-money-bill"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['pos_cash'] ?? 0, 2) ?></span>
                <span class="stat-label">POS Cash</span>
            </div>
        </div>
        <div class="billing-stat-card shop-card">
            <div class="stat-icon" style="background: rgba(168, 85, 247, 0.15); color: #a855f7;"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($stats['shop_collected'] ?? 0, 2) ?></span>
                <span class="stat-label">Shop Revenue</span>
            </div>
        </div>
    </div>

    <!-- Actions Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-input-small" placeholder="Search invoices..." id="invoiceSearch">
            </div>
        </div>
        <div class="action-buttons">
            <button class="btn btn-secondary" onclick="openModal('record-payment-modal')">
                <i class="fas fa-money-bill-wave"></i> Record Payment
            </button>
            <button class="btn btn-primary" onclick="openModal('create-invoice-modal')">
                <i class="fas fa-plus"></i> Create Invoice
            </button>
        </div>
    </div>

    <!-- Invoices Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-file-invoice"></i> Invoices</h3>
            <div class="table-filters">
                <select class="form-input-small" id="invoiceFilterSelect" onchange="updateInvoiceFilter(this.value)">
                    <option value="day" <?= $invoiceFilter === 'day' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $invoiceFilter === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $invoiceFilter === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="year" <?= $invoiceFilter === 'year' ? 'selected' : '' ?>>Year</option>
                </select>
                <select class="form-input-small" id="invoiceYearSelect" onchange="updateInvoiceYear(this.value)" style="<?= $invoiceFilter !== 'year' ? 'display:none;' : '' ?>">
                    <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>" <?= $invoiceYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-secondary btn-small" onclick="exportInvoices()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" id="invoicesTable">
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
                        <?php if(count($invoices) > 0): ?>
                            <?php foreach($invoices as $invoice): 
                                $initials = strtoupper(substr($invoice['first_name'] ?? 'U', 0, 1) . substr($invoice['last_name'] ?? 'N', 0, 1));
                                $statusClass = strtolower($invoice['status']);
                            ?>
                            <tr data-invoice-search="<?= strtolower(htmlspecialchars($invoice['invoice_number'] . ' ' . ($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? ''))) ?>">
                                <td><strong><?= htmlspecialchars($invoice['invoice_number']) ?></strong></td>
                                <td>
                                    <div class="client-info">
                                        <div class="client-avatar"><?= $initials ?></div>
                                        <span><?= htmlspecialchars(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '')) ?></span>
                                    </div>
                                </td>
                                <td><?= date('M j, Y', strtotime($invoice['invoice_date'])) ?></td>
                                <td><?= $invoice['due_date'] ? date('M j, Y', strtotime($invoice['due_date'])) : '-' ?></td>
                                <td><strong>$<?= number_format($invoice['total_amount'] ?? 0, 2) ?></strong></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($invoice['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-icon" title="View" onclick="viewInvoice(<?= $invoice['id'] ?>)"><i class="fas fa-eye"></i></button>
                                        <button class="btn-icon" title="Download" onclick="downloadInvoice(<?= $invoice['id'] ?>)"><i class="fas fa-download"></i></button>
                                        <button class="btn-icon" title="Email" onclick="emailInvoice(<?= $invoice['id'] ?>)"><i class="fas fa-envelope"></i></button>
                                        <?php if ($stripeConfigured && $invoice['status'] !== 'paid'): ?>
                                        <button class="btn-icon stripe-pay" title="Send Stripe Payment Link" onclick="sendStripePaymentLink(<?= $invoice['id'] ?>, '<?= htmlspecialchars($invoice['email'] ?? '') ?>', <?= $invoice['total_amount'] ?>)"><i class="fab fa-stripe-s"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 40px;">
                                    <p class="placeholder-text">No invoices found for the selected period.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Payments History -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-credit-card"></i> Payment History</h3>
            <div class="table-filters">
                <select class="form-input-small" id="paymentFilterSelect" onchange="updatePaymentFilter(this.value)">
                    <option value="day" <?= $paymentFilter === 'day' ? 'selected' : '' ?>>Today</option>
                    <option value="week" <?= $paymentFilter === 'week' ? 'selected' : '' ?>>This Week</option>
                    <option value="month" <?= $paymentFilter === 'month' ? 'selected' : '' ?>>This Month</option>
                    <option value="year" <?= $paymentFilter === 'year' ? 'selected' : '' ?>>Year</option>
                </select>
                <select class="form-input-small" id="paymentYearSelect" onchange="updatePaymentYear(this.value)" style="<?= $paymentFilter !== 'year' ? 'display:none;' : '' ?>">
                    <?php foreach ($years as $year): ?>
                    <option value="<?= $year ?>" <?= $paymentYear == $year ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-secondary btn-small" onclick="exportPayments()">
                    <i class="fas fa-download"></i> Export
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table" id="paymentsTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Client</th>
                            <th>Invoice #</th>
                            <th>Method</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(count($payments) > 0): ?>
                            <?php foreach($payments as $payment): ?>
                            <tr>
                                <td><?= date('M j, Y', strtotime($payment['payment_date'])) ?></td>
                                <td><?= htmlspecialchars(($payment['first_name'] ?? '') . ' ' . ($payment['last_name'] ?? '')) ?></td>
                                <td><?= htmlspecialchars($payment['invoice_number'] ?? 'N/A') ?></td>
                                <td>
                                    <span class="payment-method-badge <?= strtolower(str_replace(' ', '-', $payment['payment_method'] ?? '')) ?>">
                                        <i class="fas fa-<?= getPaymentMethodIcon($payment['payment_method'] ?? 'other') ?>"></i>
                                        <?= ucwords(str_replace('_', ' ', $payment['payment_method'] ?? 'Unknown')) ?>
                                    </span>
                                </td>
                                <td><strong class="payment-amount">$<?= number_format($payment['amount'], 2) ?></strong></td>
                                <td><span class="status-badge <?= strtolower($payment['payment_status'] ?? $payment['status'] ?? 'completed') ?>"><?= ucfirst($payment['payment_status'] ?? $payment['status'] ?? 'Completed') ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 40px;">
                                    <p class="placeholder-text">No payments found for the selected period.</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
function getPaymentMethodIcon($method) {
    $icons = [
        'cash' => 'money-bill',
        'check' => 'money-check',
        'credit_card' => 'credit-card',
        'debit_card' => 'credit-card',
        'bank_transfer' => 'university',
        'e_transfer' => 'exchange-alt',
        'etransfer' => 'exchange-alt',
        'stripe' => 'stripe-s',
        'other' => 'receipt'
    ];
    return $icons[strtolower($method)] ?? 'receipt';
}
?>

<!-- Create Invoice Modal -->
<div id="create-invoice-modal" class="modal">
    <div class="modal-content modal-large">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-file-invoice-dollar"></i> Create Invoice</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('create-invoice-modal')">&times;</button>
        </div>
        <div id="invoice-success-message" class="success-widget" style="display: none;">
            <div class="success-icon"><i class="fas fa-check-circle"></i></div>
            <div class="success-text">Invoice created successfully!</div>
            <button type="button" class="btn btn-primary btn-small" onclick="resetInvoiceForm()">Create Another Invoice</button>
        </div>
        <form id="create-invoice-form" method="POST" action="process_admin_action.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_invoice">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Client *</label>
                        <select name="user_id" class="form-input" required>
                            <option value="">Select Client</option>
                            <?php foreach ($users as $user): ?>
                            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Invoice Date *</label>
                        <input type="date" name="invoice_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-input" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description *</label>
                    <textarea name="description" class="form-textarea" rows="3" required placeholder="Enter invoice description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Line Items</label>
                    <div id="line-items">
                        <div class="line-item">
                            <input type="text" name="item_description[]" class="form-input" placeholder="Description" style="flex: 2;">
                            <input type="number" name="item_quantity[]" class="form-input" placeholder="Qty" step="1" min="1" value="1" style="flex: 1;">
                            <input type="number" name="item_price[]" class="form-input" placeholder="Price" step="0.01" min="0" style="flex: 1;">
                        </div>
                    </div>
                    <button type="button" class="btn btn-secondary btn-small" onclick="addLineItem()">
                        <i class="fas fa-plus"></i> Add Line Item
                    </button>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Subtotal</label>
                        <input type="number" name="subtotal" class="form-input" step="0.01" min="0" id="invoice-subtotal" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tax (<?= htmlspecialchars($taxName) ?> <?= $taxRate ?>%)</label>
                        <input type="number" name="tax_amount" class="form-input" step="0.01" min="0" id="invoice-tax" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Total Amount *</label>
                        <input type="number" name="total_amount" class="form-input" step="0.01" min="0" required id="invoice-total" readonly>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="Optional notes for the invoice..."></textarea>
                </div>
                
                <?php if ($stripeConfigured): ?>
                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="send_stripe_link" value="1">
                        <span><i class="fab fa-stripe"></i> Send Stripe payment link with invoice</span>
                    </label>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-invoice-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="record-payment-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-money-bill-wave"></i> Record Payment</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('record-payment-modal')">&times;</button>
        </div>
        <form method="POST" action="process_admin_action.php" id="recordPaymentForm">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="record_payment">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Link to Invoice (Optional)</label>
                    <select name="invoice_id" class="form-input">
                        <option value="">-- No Invoice / Manual Entry --</option>
                        <?php foreach ($unpaidInvoices as $inv): ?>
                        <option value="<?= $inv['id'] ?>"><?= htmlspecialchars($inv['invoice_number']) ?> - <?= htmlspecialchars($inv['first_name'] . ' ' . $inv['last_name']) ?> ($<?= number_format($inv['total_amount'], 2) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="manual-client-group">
                    <label class="form-label">Client *</label>
                    <select name="user_id" class="form-input" id="payment-user-id">
                        <option value="">Select Client</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Payment Amount *</label>
                        <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Payment Date *</label>
                        <input type="date" name="payment_date" class="form-input" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Payment Method *</label>
                    <select name="payment_method" class="form-input" required>
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="e_transfer">E-Transfer</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="credit_card">Credit Card (Manual)</option>
                        <option value="debit_card">Debit Card</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reference/Transaction ID</label>
                    <input type="text" name="reference_number" class="form-input" placeholder="e.g., Check number, e-transfer confirmation">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="Optional payment notes"></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('record-payment-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Record Payment</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Stripe Status Bar */
.stripe-status-bar {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px 20px;
    border-radius: 12px;
    margin-bottom: 24px;
    border: 1px solid;
}

.stripe-status-bar.configured {
    background: rgba(99, 91, 255, 0.1);
    border-color: rgba(99, 91, 255, 0.3);
}

.stripe-status-bar.not-configured {
    background: rgba(245, 158, 11, 0.1);
    border-color: rgba(245, 158, 11, 0.3);
}

.stripe-status-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    flex-shrink: 0;
}

.stripe-status-bar.configured .stripe-status-icon {
    background: rgba(99, 91, 255, 0.2);
    color: #635BFF;
}

.stripe-status-bar.not-configured .stripe-status-icon {
    background: rgba(245, 158, 11, 0.2);
    color: #f59e0b;
}

.stripe-status-info {
    flex: 1;
}

.stripe-status-info strong {
    display: block;
    font-size: 14px;
    color: var(--text-white);
    margin-bottom: 4px;
}

.stripe-status-info span {
    font-size: 12px;
    color: var(--text-dim);
}

.stripe-status-info a {
    color: #8B5CF6;
    text-decoration: none;
}

.stripe-status-info a:hover {
    text-decoration: underline;
}

.stripe-status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 8px;
    font-size: 12px;
    font-weight: 700;
}

.stripe-status-badge.active {
    background: rgba(16, 185, 129, 0.15);
    color: #10b981;
}

.stripe-status-badge.inactive {
    background: rgba(239, 68, 68, 0.15);
    color: #ef4444;
}

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

/* Action Bar */
.action-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.filter-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

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

/* Table Filters */
.table-filters {
    display: flex;
    gap: 10px;
    align-items: center;
}

/* Content Card */
.content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    margin-bottom: 24px;
    overflow: hidden;
}

.content-card .card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    background: var(--bg-main);
}

.content-card .card-header h3 {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
}

.content-card .card-header h3 i {
    color: var(--primary);
}

.content-card .card-body {
    padding: 0;
}

/* Data Table */
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
    padding: 16px 20px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.8px;
    border-bottom: 2px solid var(--border);
}

.data-table td {
    padding: 16px 20px;
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
    background: linear-gradient(135deg, var(--primary), var(--accent, #8B5CF6));
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

.status-badge.paid, .status-badge.completed { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.sent { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.overdue { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-badge.draft { background: rgba(148, 163, 184, 0.15); color: #94a3b8; }

.table-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    border: 1px solid var(--border);
    background: var(--bg-main);
    color: var(--text-dim);
    cursor: pointer;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
}

.btn-icon.stripe-pay {
    color: #635BFF;
}

.btn-icon.stripe-pay:hover {
    background: rgba(99, 91, 255, 0.15);
    border-color: #635BFF;
}

.payment-method-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    background: rgba(107, 70, 193, 0.1);
    color: var(--text-white);
}

.payment-amount {
    color: #10b981;
}

/* Line Items */
.line-item {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
    align-items: center;
}

/* Form Checkbox */
.form-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: pointer;
    font-size: 14px;
    color: var(--text-white);
}

.form-checkbox input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--primary);
}

.form-checkbox span {
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Success Widget */
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

.placeholder-text {
    color: var(--text-dim);
    text-align: center;
    padding: 20px;
    font-size: 14px;
}

/* Modal Large */
.modal-large {
    max-width: 700px;
}

@media (max-width: 768px) {
    .billing-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .action-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group, .action-buttons {
        width: 100%;
    }
    
    .search-box input {
        min-width: auto;
        width: 100%;
    }
    
    .table-filters {
        flex-wrap: wrap;
    }
}

@media (max-width: 480px) {
    .billing-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Invoice search filter
document.getElementById('invoiceSearch')?.addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const rows = document.querySelectorAll('#invoicesTable tbody tr[data-invoice-search]');
    
    rows.forEach(row => {
        const searchData = row.getAttribute('data-invoice-search');
        row.style.display = searchData.includes(searchTerm) ? '' : 'none';
    });
});

// Filter updates
function updateInvoiceFilter(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('invoice_filter', value);
    if (value === 'year') {
        document.getElementById('invoiceYearSelect').style.display = '';
    } else {
        url.searchParams.delete('invoice_year');
    }
    window.location.href = url.toString();
}

function updateInvoiceYear(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('invoice_year', value);
    window.location.href = url.toString();
}

function updatePaymentFilter(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('payment_filter', value);
    if (value === 'year') {
        document.getElementById('paymentYearSelect').style.display = '';
    } else {
        url.searchParams.delete('payment_year');
    }
    window.location.href = url.toString();
}

function updatePaymentYear(value) {
    const url = new URL(window.location.href);
    url.searchParams.set('payment_year', value);
    window.location.href = url.toString();
}

// Export functions
function exportInvoices() {
    const rows = document.querySelectorAll('#invoicesTable tbody tr:not([style*="display: none"])');
    let csv = 'Invoice #,Client,Date,Due Date,Amount,Status\n';
    
    rows.forEach(row => {
        if (row.querySelector('td')) {
            const cells = row.querySelectorAll('td');
            const invoiceNum = cells[0]?.textContent.trim() || '';
            const client = cells[1]?.textContent.trim() || '';
            const date = cells[2]?.textContent.trim() || '';
            const dueDate = cells[3]?.textContent.trim() || '';
            const amount = cells[4]?.textContent.trim() || '';
            const status = cells[5]?.textContent.trim() || '';
            csv += `"${invoiceNum}","${client}","${date}","${dueDate}","${amount}","${status}"\n`;
        }
    });
    
    downloadCSV(csv, 'invoices_export_' + new Date().toISOString().split('T')[0] + '.csv');
}

function exportPayments() {
    const rows = document.querySelectorAll('#paymentsTable tbody tr');
    let csv = 'Date,Client,Invoice #,Method,Amount,Status\n';
    
    rows.forEach(row => {
        if (row.querySelector('td')) {
            const cells = row.querySelectorAll('td');
            const date = cells[0]?.textContent.trim() || '';
            const client = cells[1]?.textContent.trim() || '';
            const invoiceNum = cells[2]?.textContent.trim() || '';
            const method = cells[3]?.textContent.trim() || '';
            const amount = cells[4]?.textContent.trim() || '';
            const status = cells[5]?.textContent.trim() || '';
            csv += `"${date}","${client}","${invoiceNum}","${method}","${amount}","${status}"\n`;
        }
    });
    
    downloadCSV(csv, 'payments_export_' + new Date().toISOString().split('T')[0] + '.csv');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
}

// Modal functions
function openModal(modalId) {
    document.getElementById(modalId).style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Invoice actions
function viewInvoice(invoiceId) {
    window.open('process_admin_action.php?action=view_invoice&invoice_id=' + invoiceId, '_blank');
}

function downloadInvoice(invoiceId) {
    window.location.href = 'process_admin_action.php?action=download_invoice&invoice_id=' + invoiceId;
}

function emailInvoice(invoiceId) {
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
    if (!csrfToken) {
        alert('Security error: Please refresh the page.');
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
            alert(data.success ? 'Invoice email sent successfully!' : ('Error: ' + (data.message || 'Failed to send email')));
        })
        .catch(() => alert('Error sending invoice email.'));
    }
}

function sendStripePaymentLink(invoiceId, email, amount) {
    const csrfToken = document.querySelector('[name="csrf_token"]')?.value;
    if (!csrfToken) {
        alert('Security error: Please refresh the page.');
        return;
    }
    
    if (confirm('Send Stripe payment link to ' + email + ' for $' + amount.toFixed(2) + '?')) {
        fetch('process_admin_action.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=send_stripe_payment_link&invoice_id=' + invoiceId + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(response => response.json())
        .then(data => {
            alert(data.success ? 'Stripe payment link sent successfully!' : ('Error: ' + (data.message || 'Failed to send payment link')));
        })
        .catch(() => alert('Error sending Stripe payment link.'));
    }
}

// Line items and invoice total calculation
const taxRate = <?= $taxRate ?>;

function addLineItem() {
    const container = document.getElementById('line-items');
    const newItem = document.createElement('div');
    newItem.className = 'line-item';
    newItem.innerHTML = `
        <input type="text" name="item_description[]" class="form-input" placeholder="Description" style="flex: 2;">
        <input type="number" name="item_quantity[]" class="form-input" placeholder="Qty" step="1" min="1" value="1" style="flex: 1;" onchange="calculateInvoiceTotal()" oninput="calculateInvoiceTotal()">
        <input type="number" name="item_price[]" class="form-input" placeholder="Price" step="0.01" min="0" style="flex: 1;" onchange="calculateInvoiceTotal()" oninput="calculateInvoiceTotal()">
        <button type="button" class="btn-icon" onclick="this.parentElement.remove(); calculateInvoiceTotal();" style="flex-shrink: 0;">
            <i class="fas fa-trash"></i>
        </button>
    `;
    container.appendChild(newItem);
}

function calculateInvoiceTotal() {
    const items = document.querySelectorAll('.line-item');
    let subtotal = 0;
    
    items.forEach(item => {
        const qty = parseFloat(item.querySelector('input[name="item_quantity[]"]')?.value || 0);
        const price = parseFloat(item.querySelector('input[name="item_price[]"]')?.value || 0);
        subtotal += qty * price;
    });
    
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    document.getElementById('invoice-subtotal').value = subtotal.toFixed(2);
    document.getElementById('invoice-tax').value = tax.toFixed(2);
    document.getElementById('invoice-total').value = total.toFixed(2);
}

function resetInvoiceForm() {
    const form = document.getElementById('create-invoice-form');
    const successMsg = document.getElementById('invoice-success-message');
    
    form.reset();
    form.style.display = 'block';
    successMsg.style.display = 'none';
    
    form.querySelector('[name="invoice_date"]').value = new Date().toISOString().split('T')[0];
    const dueDate = new Date();
    dueDate.setDate(dueDate.getDate() + 30);
    form.querySelector('[name="due_date"]').value = dueDate.toISOString().split('T')[0];
    
    const lineItemsContainer = document.getElementById('line-items');
    lineItemsContainer.innerHTML = `
        <div class="line-item">
            <input type="text" name="item_description[]" class="form-input" placeholder="Description" style="flex: 2;">
            <input type="number" name="item_quantity[]" class="form-input" placeholder="Qty" step="1" min="1" value="1" style="flex: 1;" onchange="calculateInvoiceTotal()" oninput="calculateInvoiceTotal()">
            <input type="number" name="item_price[]" class="form-input" placeholder="Price" step="0.01" min="0" style="flex: 1;" onchange="calculateInvoiceTotal()" oninput="calculateInvoiceTotal()">
        </div>
    `;
    
    calculateInvoiceTotal();
}

// Initialize event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Add listeners for initial line items
    const lineItems = document.querySelectorAll('.line-item input[name="item_price[]"], .line-item input[name="item_quantity[]"]');
    lineItems.forEach(input => {
        input.addEventListener('input', calculateInvoiceTotal);
        input.addEventListener('change', calculateInvoiceTotal);
    });
    
    // Invoice form submission
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
                const contentType = response.headers.get('content-type');
                if (contentType && contentType.includes('application/json')) {
                    return response.json();
                }
                return { success: true };
            })
            .then(data => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                const form = document.getElementById('create-invoice-form');
                form.style.display = 'none';
                
                persistToast('Invoice created successfully!', 'success');
                window.location.reload();
            })
            .catch(error => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                console.error('Error:', error);
                alert('Error creating invoice. Please try again.');
            });
        });
    }
    
    // Invoice selector for payment - auto-fill user if invoice selected
    const invoiceSelect = document.querySelector('[name="invoice_id"]');
    const userIdSelect = document.getElementById('payment-user-id');
    const manualClientGroup = document.getElementById('manual-client-group');
    
    if (invoiceSelect) {
        invoiceSelect.addEventListener('change', function() {
            if (this.value) {
                manualClientGroup.style.display = 'none';
                userIdSelect.removeAttribute('required');
            } else {
                manualClientGroup.style.display = 'block';
                userIdSelect.setAttribute('required', 'required');
            }
        });
    }
    
});
</script>
