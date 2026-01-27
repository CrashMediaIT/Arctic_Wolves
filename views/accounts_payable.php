<?php
// views/accounts_payable.php - Accounts Payable with Stripe Integration
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: dashboard.php");
    exit();
}

require_once 'security.php';

$activeTab = $_GET['tab'] ?? 'expenses';

// Get expense categories
try {
    $categories = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $categories = [];
}

// Get payees
try {
    $payees = $pdo->query("SELECT * FROM payees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $payees = [];
}

// Get recent expenses
try {
    $recent_expenses = $pdo->query("
        SELECT e.*, p.name as payee_name
        FROM expenses e
        LEFT JOIN payees p ON e.payee_id = p.id
        ORDER BY e.expense_date DESC, e.created_at DESC
        LIMIT 20
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recent_expenses = [];
}

// Get payment batches
try {
    $batches = $pdo->query("
        SELECT pb.*, u.first_name as created_by_name,
            (SELECT COUNT(*) FROM batch_payments WHERE batch_id = pb.id) as payment_count
        FROM payment_batches pb
        LEFT JOIN users u ON pb.created_by = u.id
        ORDER BY pb.created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $batches = [];
}

// Get virtual cards
try {
    $virtual_cards = $pdo->query("
        SELECT vc.*, ch.name as cardholder_name, ch.email as cardholder_email
        FROM stripe_virtual_cards vc
        JOIN stripe_cardholders ch ON vc.cardholder_id = ch.id
        ORDER BY vc.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $virtual_cards = [];
}

// Check Stripe configuration
$stripe_configured = false;
try {
    $stripe_key = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'stripe_secret_key'")->fetchColumn();
    $stripe_configured = !empty($stripe_key);
} catch (PDOException $e) {}
?>

<div class="accounts-payable">
    <div class="page-header">
        <h2><i class="fas fa-file-invoice-dollar"></i> Accounts Payable</h2>
        <p class="page-description">Manage payees, payments, and Stripe virtual cards</p>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-<?= $_GET['status'] === 'success' ? 'success' : 'error' ?>">
            <?php 
            if ($_GET['status'] === 'success') {
                echo 'Operation completed successfully!';
            } else {
                echo htmlspecialchars($_GET['message'] ?? 'An error occurred.', ENT_QUOTES, 'UTF-8');
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Tabs Navigation -->
    <div class="tabs-navigation">
        <a href="?page=accounts_payable&tab=expenses" class="tab-link <?= $activeTab === 'expenses' ? 'active' : '' ?>">
            <i class="fas fa-receipt"></i> Expenses
        </a>
        <a href="?page=accounts_payable&tab=payees" class="tab-link <?= $activeTab === 'payees' ? 'active' : '' ?>">
            <i class="fas fa-users"></i> Payees
        </a>
        <a href="?page=accounts_payable&tab=batches" class="tab-link <?= $activeTab === 'batches' ? 'active' : '' ?>">
            <i class="fas fa-layer-group"></i> Batch Payments
        </a>
        <a href="?page=accounts_payable&tab=virtual_cards" class="tab-link <?= $activeTab === 'virtual_cards' ? 'active' : '' ?>">
            <i class="fas fa-credit-card"></i> Virtual Cards
        </a>
    </div>

    <!-- Expenses Tab -->
    <?php if ($activeTab === 'expenses'): ?>
    <div class="tab-content">
        <div class="content-header">
            <h3><i class="fas fa-receipt"></i> Recent Expenses</h3>
            <button onclick="openExpenseModal()" class="btn-primary">
                <i class="fas fa-plus"></i> Add Expense
            </button>
        </div>
        
        <div class="expenses-table">
            <table>
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
                    <?php foreach ($recent_expenses as $expense): ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($expense['expense_date'])) ?></td>
                        <td><strong><?= htmlspecialchars($expense['vendor_name'] ?? 'N/A') ?></strong></td>
                        <td><span class="category-badge"><?= htmlspecialchars($expense['category'] ?? 'N/A') ?></span></td>
                        <td><?= htmlspecialchars(substr($expense['description'] ?? '', 0, 50)) ?><?= strlen($expense['description'] ?? '') > 50 ? '...' : '' ?></td>
                        <td>$<?= number_format($expense['subtotal'] ?? $expense['amount'], 2) ?></td>
                        <td>$<?= number_format($expense['tax_amount'] ?? 0, 2) ?></td>
                        <td><strong>$<?= number_format($expense['total_amount'] ?? $expense['amount'], 2) ?></strong></td>
                        <td>
                            <?php if ($expense['receipt_url']): ?>
                                <a href="<?= htmlspecialchars($expense['receipt_url']) ?>" target="_blank" class="btn-icon" title="View Receipt">
                                    <i class="fas fa-file-image"></i>
                                </a>
                            <?php else: ?>
                                <span style="color: #64748b;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <button onclick='editExpense(<?= htmlspecialchars(json_encode($expense)) ?>)' class="btn-icon" title="Edit">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="deleteExpense(<?= $expense['id'] ?>)" class="btn-icon btn-danger" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recent_expenses)): ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 40px;">No expenses recorded yet.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payees Tab -->
    <?php if ($activeTab === 'payees'): ?>
    <div class="tab-content">
        <div class="content-header">
            <h3><i class="fas fa-users"></i> Manage Payees</h3>
            <button onclick="openPayeeModal()" class="btn-primary">
                <i class="fas fa-plus"></i> Add Payee
            </button>
        </div>
        
        <div class="payees-grid">
            <?php foreach ($payees as $payee): ?>
            <div class="payee-card">
                <div class="payee-header">
                    <div class="payee-avatar"><i class="fas fa-user-tie"></i></div>
                    <div class="payee-info">
                        <h4><?= htmlspecialchars($payee['name']) ?></h4>
                        <?php if ($payee['company_name']): ?>
                        <span class="company"><?= htmlspecialchars($payee['company_name']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="payee-details">
                    <?php if ($payee['email']): ?>
                    <p><i class="fas fa-envelope"></i> <?= htmlspecialchars($payee['email']) ?></p>
                    <?php endif; ?>
                    <?php if ($payee['phone']): ?>
                    <p><i class="fas fa-phone"></i> <?= htmlspecialchars($payee['phone']) ?></p>
                    <?php endif; ?>
                    <p><i class="fas fa-credit-card"></i> <?= ucwords(str_replace('_', ' ', $payee['default_payment_method'])) ?></p>
                    <p><i class="fas fa-dollar-sign"></i> <?= $payee['default_currency'] ?></p>
                </div>
                <div class="payee-actions">
                    <button onclick='editPayee(<?= htmlspecialchars(json_encode($payee)) ?>)' class="btn-secondary btn-small">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button onclick="deletePayee(<?= $payee['id'] ?>)" class="btn-danger btn-small">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($payees)): ?>
            <div class="empty-state-card">
                <i class="fas fa-users"></i>
                <p>No payees configured</p>
                <span>Add payees to track vendors and suppliers</span>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Batch Payments Tab -->
    <?php if ($activeTab === 'batches'): ?>
    <div class="tab-content">
        <div class="content-header">
            <h3><i class="fas fa-layer-group"></i> Batch Payments</h3>
            <button onclick="openBatchModal()" class="btn-primary">
                <i class="fas fa-plus"></i> Create Batch
            </button>
        </div>
        
        <div class="batches-table">
            <table>
                <thead>
                    <tr>
                        <th>Batch Name</th>
                        <th>Date</th>
                        <th>Payments</th>
                        <th>Total Amount</th>
                        <th>Currency</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($batches as $batch): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($batch['batch_name']) ?></strong></td>
                        <td><?= date('M j, Y', strtotime($batch['batch_date'])) ?></td>
                        <td><?= $batch['payment_count'] ?> payments</td>
                        <td><strong>$<?= number_format($batch['total_amount'], 2) ?></strong></td>
                        <td><?= $batch['currency'] ?></td>
                        <td>
                            <span class="status-badge status-<?= $batch['status'] ?>">
                                <?= ucfirst($batch['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($batch['status'] === 'draft'): ?>
                            <button onclick="processBatch(<?= $batch['id'] ?>)" class="btn-primary btn-small">
                                <i class="fas fa-play"></i> Process
                            </button>
                            <?php endif; ?>
                            <button onclick="viewBatch(<?= $batch['id'] ?>)" class="btn-icon" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($batches)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">No batch payments created yet.</td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- Virtual Cards Tab -->
    <?php if ($activeTab === 'virtual_cards'): ?>
    <div class="tab-content">
        <div class="content-header">
            <h3><i class="fas fa-credit-card"></i> Stripe Virtual Cards</h3>
            <?php if ($stripe_configured): ?>
            <button onclick="openVirtualCardModal()" class="btn-primary">
                <i class="fas fa-plus"></i> Create Virtual Card
            </button>
            <?php endif; ?>
        </div>
        
        <?php if (!$stripe_configured): ?>
        <div class="warning-box">
            <i class="fas fa-exclamation-triangle"></i>
            <div>
                <h4>Stripe Not Configured</h4>
                <p>Please configure your Stripe API keys in <a href="?page=system_tools&tab=payments">System Tools → Payments</a> to create virtual cards.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="virtual-cards-grid">
            <?php foreach ($virtual_cards as $card): ?>
            <div class="virtual-card <?= $card['status'] ?>">
                <div class="card-chip"><i class="fas fa-microchip"></i></div>
                <div class="card-brand"><?= strtoupper($card['brand'] ?? 'VISA') ?></div>
                <div class="card-number">•••• •••• •••• <?= $card['last4'] ?></div>
                <div class="card-details">
                    <div class="card-name"><?= htmlspecialchars($card['card_name'] ?? $card['cardholder_name']) ?></div>
                    <div class="card-expiry">EXP <?= str_pad($card['exp_month'], 2, '0', STR_PAD_LEFT) ?>/<?= substr($card['exp_year'], -2) ?></div>
                </div>
                <div class="card-limit">
                    <span>Limit: $<?= number_format($card['spending_limit'] ?? 0, 2) ?> / <?= ucfirst($card['spending_limit_interval'] ?? 'monthly') ?></span>
                </div>
                <div class="card-status">
                    <span class="status-badge status-<?= $card['status'] ?>"><?= ucfirst($card['status']) ?></span>
                </div>
                <div class="card-actions">
                    <?php if ($card['status'] === 'inactive'): ?>
                    <button onclick="activateCard(<?= $card['id'] ?>)" class="btn-primary btn-small">
                        <i class="fas fa-check"></i> Activate
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            <?php if (empty($virtual_cards)): ?>
            <div class="empty-state-card">
                <i class="fas fa-credit-card"></i>
                <p>No virtual cards created</p>
                <span>Create virtual cards for secure online payments</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Expense Modal -->
<div id="expenseModal" class="modal">
    <div class="modal-content modal-large">
        <span class="close" onclick="closeExpenseModal()">&times;</span>
        <h3 id="expenseModalTitle">Add Expense</h3>
        
        <form action="process_expenses.php" method="POST" enctype="multipart/form-data" id="expenseForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create" id="expenseFormAction">
            <input type="hidden" name="expense_id" id="expenseId">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Vendor Name <span class="required">*</span></label>
                    <input type="text" name="vendor_name" id="vendorName" required>
                </div>
                <div class="form-group">
                    <label>Expense Date <span class="required">*</span></label>
                    <input type="date" name="expense_date" id="expenseDate" required value="<?= date('Y-m-d') ?>">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Category <span class="required">*</span></label>
                    <select name="category" id="categorySelect" required>
                        <option value="">Select Category</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                        <?php endforeach; ?>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="payment_method" id="paymentMethod">
                        <option value="">Select Method</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit">Debit</option>
                        <option value="cash">Cash</option>
                        <option value="etransfer">E-Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="stripe">Stripe Virtual Card</option>
                    </select>
                </div>
            </div>
            
            <div class="form-row three-col">
                <div class="form-group">
                    <label>Subtotal <span class="required">*</span></label>
                    <input type="number" name="subtotal" id="expenseSubtotal" step="0.01" min="0" required onchange="calculateTotal()">
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
            
            <div class="form-row">
                <div class="form-group">
                    <label>Payee (Optional)</label>
                    <select name="payee_id" id="payeeSelect">
                        <option value="">No Payee</option>
                        <?php foreach ($payees as $payee): ?>
                        <option value="<?= $payee['id'] ?>"><?= htmlspecialchars($payee['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency" id="currencySelect">
                        <option value="CAD">CAD - Canadian Dollar</option>
                        <option value="USD">USD - US Dollar</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" id="expenseDescription" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Reference Number</label>
                <input type="text" name="reference_number" id="referenceNumber" placeholder="Invoice #, PO #, etc.">
            </div>
            
            <div class="form-group">
                <label>Upload Receipt</label>
                <input type="file" name="receipt_file" id="receiptFile" accept="image/*,.pdf">
                <small style="color: #64748b;">Supported: Images and PDF. Uploads to Nextcloud automatically.</small>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeExpenseModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Payee Modal -->
<div id="payeeModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closePayeeModal()">&times;</span>
        <h3 id="payeeModalTitle">Add Payee</h3>
        
        <form id="payeeForm">
            <input type="hidden" name="payee_id" id="payeeId">
            
            <div class="form-row">
                <div class="form-group">
                    <label>Name <span class="required">*</span></label>
                    <input type="text" name="name" id="payeeName" required>
                </div>
                <div class="form-group">
                    <label>Company Name</label>
                    <input type="text" name="company_name" id="payeeCompany">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" id="payeeEmail">
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" id="payeePhone">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Payment Method</label>
                    <select name="default_payment_method" id="payeePaymentMethod">
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="stripe">Stripe</option>
                        <option value="etransfer">E-Transfer</option>
                        <option value="cash">Cash</option>
                        <option value="credit_card">Credit Card</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="default_currency" id="payeeCurrency">
                        <option value="CAD">CAD</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>E-Transfer Email</label>
                <input type="email" name="etransfer_email" id="payeeEtransfer" placeholder="For e-transfer payments">
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="payeeNotes" rows="2"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closePayeeModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Save Payee</button>
            </div>
        </form>
    </div>
</div>

<!-- Virtual Card Modal -->
<div id="virtualCardModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeVirtualCardModal()">&times;</span>
        <h3>Create Virtual Card</h3>
        
        <form id="virtualCardForm">
            <div class="form-group">
                <label>Cardholder Name <span class="required">*</span></label>
                <input type="text" name="cardholder_name" id="cardholderName" required>
            </div>
            
            <div class="form-group">
                <label>Cardholder Email <span class="required">*</span></label>
                <input type="email" name="cardholder_email" id="cardholderEmail" required>
            </div>
            
            <div class="form-group">
                <label>Card Name/Purpose</label>
                <input type="text" name="card_name" id="cardName" placeholder="e.g., Marketing Expenses">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Spending Limit</label>
                    <input type="number" name="spending_limit" id="spendingLimit" value="500" min="1" step="1">
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency" id="cardCurrency">
                        <option value="cad">CAD</option>
                        <option value="usd">USD</option>
                    </select>
                </div>
            </div>
            
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <p>Virtual cards are created through Stripe Issuing. The card will be created in an inactive state and must be activated before use.</p>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeVirtualCardModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-credit-card"></i> Create Card</button>
            </div>
        </form>
    </div>
</div>

<!-- Batch Modal -->
<div id="batchModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeBatchModal()">&times;</span>
        <h3>Create Payment Batch</h3>
        
        <form action="process_expenses.php" method="POST" id="batchForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create_batch">
            
            <div class="form-group">
                <label>Batch Name <span class="required">*</span></label>
                <input type="text" name="batch_name" id="batchName" required value="Batch <?= date('Y-m-d H:i') ?>">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Batch Date</label>
                    <input type="date" name="batch_date" id="batchDate" value="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="currency" id="batchCurrency">
                        <option value="CAD">CAD</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>
            
            <div class="form-group">
                <label>Notes</label>
                <textarea name="notes" id="batchNotes" rows="2"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn-secondary" onclick="closeBatchModal()"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-layer-group"></i> Create Batch</button>
            </div>
        </form>
    </div>
</div>

<style>
.accounts-payable { padding: 20px; }
.page-header { margin-bottom: 24px; }
.page-header h2 { margin: 0 0 8px 0; color: var(--text-white); }
.page-description { color: var(--text-dim); margin: 0; }

.tabs-navigation { display: flex; gap: 8px; margin-bottom: 24px; background: var(--bg-card); padding: 8px; border-radius: 12px; }
.tab-link { padding: 12px 20px; border-radius: 8px; color: var(--text-dim); text-decoration: none; display: flex; align-items: center; gap: 8px; transition: all 0.2s; }
.tab-link:hover { color: var(--text-white); background: var(--bg-main); }
.tab-link.active { color: var(--text-white); background: var(--primary); }

.content-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
.content-header h3 { margin: 0; color: var(--text-white); }

.expenses-table, .batches-table { background: var(--bg-card); border-radius: 12px; overflow: hidden; }
table { width: 100%; border-collapse: collapse; }
th, td { padding: 14px 16px; text-align: left; }
th { background: var(--bg-main); color: var(--text-dim); font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; }
td { border-bottom: 1px solid var(--border); color: var(--text-white); }

.category-badge { display: inline-block; padding: 4px 12px; background: rgba(107, 70, 193, 0.15); color: #8B5CF6; border-radius: 12px; font-size: 11px; font-weight: 600; }
.status-badge { display: inline-block; padding: 4px 12px; border-radius: 12px; font-size: 11px; font-weight: 600; }
.status-badge.status-draft { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.status-pending { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-badge.status-processing { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }
.status-badge.status-completed { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.status-failed { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-badge.status-active { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.status-inactive { background: rgba(107, 114, 128, 0.15); color: #6b7280; }

.payees-grid, .virtual-cards-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.payee-card { background: var(--bg-card); border: 1px solid var(--border); border-radius: 12px; padding: 20px; }
.payee-header { display: flex; align-items: center; gap: 16px; margin-bottom: 16px; }
.payee-avatar { width: 48px; height: 48px; background: rgba(107, 70, 193, 0.15); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #8B5CF6; font-size: 20px; }
.payee-info h4 { margin: 0 0 4px 0; color: var(--text-white); }
.payee-info .company { color: var(--text-dim); font-size: 13px; }
.payee-details { margin-bottom: 16px; }
.payee-details p { margin: 8px 0; color: var(--text-dim); font-size: 13px; }
.payee-details i { width: 16px; margin-right: 8px; color: var(--primary); }
.payee-actions { display: flex; gap: 8px; }

.virtual-card { background: linear-gradient(135deg, #1e1b4b 0%, #4c1d95 100%); border-radius: 16px; padding: 24px; color: white; position: relative; overflow: hidden; min-height: 180px; }
.virtual-card.inactive { opacity: 0.7; }
.card-chip { position: absolute; top: 20px; left: 20px; font-size: 24px; color: rgba(255,255,255,0.6); }
.card-brand { position: absolute; top: 20px; right: 20px; font-size: 14px; font-weight: 700; letter-spacing: 2px; }
.card-number { font-size: 18px; letter-spacing: 3px; margin: 50px 0 20px 0; font-family: monospace; }
.card-details { display: flex; justify-content: space-between; margin-bottom: 12px; }
.card-name { font-size: 12px; text-transform: uppercase; letter-spacing: 1px; }
.card-expiry { font-size: 12px; }
.card-limit { font-size: 11px; color: rgba(255,255,255,0.7); margin-bottom: 12px; }
.card-status { margin-bottom: 12px; }
.card-actions { margin-top: 12px; }

.empty-state-card { background: var(--bg-card); border: 2px dashed var(--border); border-radius: 12px; padding: 40px; text-align: center; }
.empty-state-card i { font-size: 48px; color: var(--border); margin-bottom: 16px; }
.empty-state-card p { font-size: 16px; font-weight: 600; color: var(--text-white); margin: 0 0 8px 0; }
.empty-state-card span { font-size: 13px; color: var(--text-dim); }

.warning-box { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); border-radius: 12px; padding: 20px; display: flex; gap: 16px; align-items: flex-start; }
.warning-box i { font-size: 24px; color: #f59e0b; }
.warning-box h4 { margin: 0 0 8px 0; color: #f59e0b; }
.warning-box p { margin: 0; color: var(--text-dim); }
.warning-box a { color: var(--primary); }

.info-box { background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 8px; padding: 16px; display: flex; gap: 12px; margin: 16px 0; }
.info-box i { color: #3b82f6; }
.info-box p { margin: 0; color: var(--text-dim); font-size: 13px; }

.alert { padding: 16px; border-radius: 8px; margin-bottom: 20px; }
.alert-success { background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #10b981; }
.alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }

.btn-icon { background: transparent; border: 1px solid var(--border); color: var(--text-dim); padding: 8px 12px; border-radius: 6px; cursor: pointer; transition: all 0.2s; }
.btn-icon:hover { background: var(--bg-main); color: var(--text-white); }
.btn-icon.btn-danger:hover { background: rgba(239, 68, 68, 0.15); border-color: #ef4444; color: #ef4444; }
.btn-primary { background: var(--primary); color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: 600; }
.btn-secondary { background: var(--bg-main); color: var(--text-white); border: 1px solid var(--border); padding: 12px 24px; border-radius: 8px; cursor: pointer; }
.btn-danger { background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; }
.btn-small { padding: 8px 16px; font-size: 13px; }

.modal { display: none; position: fixed; z-index: 10000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.8); overflow-y: auto; }
.modal.active { display: block; }
.modal-content { background: var(--bg-card); margin: 50px auto; padding: 24px; border-radius: 12px; max-width: 600px; position: relative; color: var(--text-white); }
.modal-large { max-width: 800px; }
.close { position: absolute; right: 20px; top: 20px; font-size: 28px; color: var(--text-dim); cursor: pointer; }
.close:hover { color: var(--text-white); }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.form-row.three-col { grid-template-columns: 1fr 1fr 1fr; }
.form-group { margin-bottom: 20px; }
.form-group label { display: block; margin-bottom: 8px; color: var(--text-dim); font-weight: 600; font-size: 14px; }
.form-group input, .form-group select, .form-group textarea { width: 100%; padding: 12px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white); font-size: 14px; }
.required { color: #ef4444; }
.form-actions { display: flex; justify-content: flex-end; gap: 12px; margin-top: 24px; padding-top: 24px; border-top: 1px solid var(--border); }

@media (max-width: 768px) {
    .tabs-navigation { flex-wrap: wrap; }
    .form-row, .form-row.three-col { grid-template-columns: 1fr; }
    .payees-grid, .virtual-cards-grid { grid-template-columns: 1fr; }
}
</style>

<script>
var csrfToken = '<?= generateCsrfToken() ?>';

// Expense functions
function openExpenseModal() {
    document.getElementById('expenseModalTitle').textContent = 'Add Expense';
    document.getElementById('expenseFormAction').value = 'create';
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseModal').classList.add('active');
}

function closeExpenseModal() { document.getElementById('expenseModal').classList.remove('active'); }

function calculateTotal() {
    var subtotal = parseFloat(document.getElementById('expenseSubtotal').value) || 0;
    var tax = parseFloat(document.getElementById('taxAmount').value) || 0;
    document.getElementById('totalAmount').value = (subtotal + tax).toFixed(2);
}

function editExpense(expense) {
    document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
    document.getElementById('expenseFormAction').value = 'update';
    document.getElementById('expenseId').value = expense.id;
    document.getElementById('vendorName').value = expense.vendor_name || '';
    document.getElementById('expenseDate').value = expense.expense_date;
    document.getElementById('categorySelect').value = expense.category || '';
    document.getElementById('paymentMethod').value = expense.payment_method || '';
    document.getElementById('expenseSubtotal').value = expense.subtotal || expense.amount;
    document.getElementById('taxAmount').value = expense.tax_amount || 0;
    document.getElementById('totalAmount').value = expense.total_amount || expense.amount;
    document.getElementById('expenseDescription').value = expense.description || '';
    document.getElementById('referenceNumber').value = expense.reference_number || '';
    document.getElementById('payeeSelect').value = expense.payee_id || '';
    document.getElementById('currencySelect').value = expense.currency || 'CAD';
    document.getElementById('expenseModal').classList.add('active');
}

function deleteExpense(id) {
    if (confirm('Are you sure you want to delete this expense?')) {
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = 'process_expenses.php';
        form.innerHTML = '<input type="hidden" name="csrf_token" value="' + csrfToken + '"><input type="hidden" name="action" value="delete"><input type="hidden" name="expense_id" value="' + id + '">';
        document.body.appendChild(form);
        form.submit();
    }
}

// Payee functions
function openPayeeModal() {
    document.getElementById('payeeModalTitle').textContent = 'Add Payee';
    document.getElementById('payeeForm').reset();
    document.getElementById('payeeId').value = '';
    document.getElementById('payeeModal').classList.add('active');
}

function closePayeeModal() { document.getElementById('payeeModal').classList.remove('active'); }

function editPayee(payee) {
    document.getElementById('payeeModalTitle').textContent = 'Edit Payee';
    document.getElementById('payeeId').value = payee.id;
    document.getElementById('payeeName').value = payee.name || '';
    document.getElementById('payeeCompany').value = payee.company_name || '';
    document.getElementById('payeeEmail').value = payee.email || '';
    document.getElementById('payeePhone').value = payee.phone || '';
    document.getElementById('payeePaymentMethod').value = payee.default_payment_method || 'bank_transfer';
    document.getElementById('payeeCurrency').value = payee.default_currency || 'CAD';
    document.getElementById('payeeEtransfer').value = payee.etransfer_email || '';
    document.getElementById('payeeNotes').value = payee.notes || '';
    document.getElementById('payeeModal').classList.add('active');
}

function deletePayee(id) {
    if (confirm('Are you sure you want to delete this payee?')) {
        fetch('process_expenses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=delete_payee&payee_id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert(data.message || 'Error deleting payee'); }
        });
    }
}

document.getElementById('payeeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('csrf_token', csrfToken);
    formData.append('action', document.getElementById('payeeId').value ? 'update_payee' : 'create_payee');
    
    fetch('process_expenses.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        if (data.success) { location.reload(); }
        else { alert(data.message || 'Error saving payee'); }
    });
});

// Virtual Card functions
function openVirtualCardModal() { document.getElementById('virtualCardModal').classList.add('active'); }
function closeVirtualCardModal() { document.getElementById('virtualCardModal').classList.remove('active'); }

document.getElementById('virtualCardForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('csrf_token', csrfToken);
    formData.append('action', 'create_virtual_card');
    
    var btn = this.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
    
    fetch('process_expenses.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-credit-card"></i> Create Card';
        if (data.success) {
            alert('Virtual card created successfully!');
            location.reload();
        } else {
            alert(data.message || 'Error creating virtual card');
        }
    });
});

function activateCard(id) {
    if (confirm('Activate this virtual card?')) {
        fetch('process_expenses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=activate_card&card_id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert(data.message || 'Error activating card'); }
        });
    }
}

// Batch functions
function openBatchModal() { document.getElementById('batchModal').classList.add('active'); }
function closeBatchModal() { document.getElementById('batchModal').classList.remove('active'); }

function processBatch(id) {
    if (confirm('Process all payments in this batch?')) {
        fetch('process_expenses.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=process_batch_payment&batch_id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(r => r.json())
        .then(data => {
            alert(data.message || (data.success ? 'Batch processed' : 'Error'));
            if (data.success) location.reload();
        });
    }
}

function viewBatch(id) { alert('Batch details view coming soon'); }
</script>
