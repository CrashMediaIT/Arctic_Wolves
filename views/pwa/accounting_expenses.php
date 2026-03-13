<?php
/**
 * PWA Accounting Expenses - Mobile-native expense tracking
 * Purpose-built for mobile phones, not a desktop adaptation.
 * Includes: Expenses tab with OCR camera scanning, Recurring Expenses tab
 */

if (!$canAccessAccounting) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Accounting access required</div>';
    return;
}

$expenses_tab = $_GET['expenses_tab'] ?? 'expenses';

$allowedPeriods = ['week', 'month', 'quarter', 'year'];
$period = in_array($_GET['period'] ?? 'month', $allowedPeriods, true) ? $_GET['period'] : 'month';
$periodConditions = [
    'week'    => "expense_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)",
    'month'   => "expense_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')",
    'quarter' => "expense_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
    'year'    => "YEAR(expense_date) = YEAR(CURDATE())",
];
$periodWhere = $periodConditions[$period];

$monthlyTotal = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(total_amount, amount)), 0) FROM expenses WHERE $periodWhere");
    $stmt->execute();
    $monthlyTotal = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $monthlyTotal = 0; }

// Fetch expense categories from DB
$pwa_categories = [];
try {
    $pwa_categories = $pdo->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY display_order, name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $pwa_categories = []; }

// Fetch payees
$pwa_payees = [];
try {
    $pwa_payees = $pdo->query("SELECT * FROM payees WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $pwa_payees = FieldEncryption::decryptRows($pwa_payees, ['name', 'email', 'phone', 'address_line1', 'address_line2', 'city', 'etransfer_email']);
} catch (PDOException $e) { $pwa_payees = []; }

$expenses = [];
try {
    $stmt = $pdo->prepare("SELECT e.id, e.description, e.amount, e.category, e.expense_date, e.status, e.receipt_url,
        e.vendor_name, e.subtotal, e.tax_amount, e.total_amount, e.payment_method, e.currency, e.reference_number, e.payee_id,
        p.name as payee_name
        FROM expenses e LEFT JOIN payees p ON e.payee_id = p.id
        WHERE $periodWhere ORDER BY e.expense_date DESC LIMIT 50");
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Decrypt payee_name (encrypted in payees.name)
    $expenses = FieldEncryption::decryptRows($expenses, ['payee_name']);
} catch (PDOException $e) { $expenses = []; }

// Fetch recurring expenses for the recurring tab
$recurring_expenses = [];
if ($expenses_tab === 'recurring') {
    try {
        $rec_stmt = $pdo->prepare("SELECT re.*, 
            (SELECT COUNT(*) FROM recurring_expense_documents WHERE recurring_expense_id = re.id) as doc_count
            FROM recurring_expenses re ORDER BY re.renewal_date ASC, re.created_at DESC");
        $rec_stmt->execute();
        $recurring_expenses = $rec_stmt->fetchAll(PDO::FETCH_ASSOC);
        if (function_exists('decryptUserRows')) {
            $recurring_expenses = decryptUserRows($recurring_expenses);
        }
    } catch (PDOException $e) { $recurring_expenses = []; }
}

$periodLabels = ['week' => 'This Week', 'month' => 'This Month', 'quarter' => 'This Quarter', 'year' => 'This Year'];
$periodLabel = $periodLabels[$period] ?? 'This Month';
?>
<style>
.m-expenses { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-expenses-header { margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; }
.m-expenses-header-left { flex: 1; }
.m-expenses-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-expenses-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-expenses-export {
    width: 36px; height: 36px; border-radius: 10px; border: 1px solid #2D2D3F;
    background: #16161F; color: #8B5CF6; font-size: 14px;
    display: flex; align-items: center; justify-content: center; cursor: pointer;
}
.m-expenses-summary {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 12px;
    text-align: center;
}
.m-expenses-summary-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.m-expenses-summary-value { font-size: 28px; font-weight: 700; color: #fff; margin-top: 4px; }

/* Segmented control */
.m-segment-control {
    display: flex; background: #1E1E2E; border-radius: 12px; padding: 4px;
    margin-bottom: 16px; position: relative; border: 1px solid #2D2D3F;
}
.m-segment {
    flex: 1; padding: 10px 12px; border: none; background: transparent;
    color: #A8A8B8; font-size: 13px; font-weight: 600; cursor: pointer;
    border-radius: 10px; display: flex; align-items: center; justify-content: center;
    gap: 6px; z-index: 1; transition: color 0.2s; min-height: 44px;
    -webkit-tap-highlight-color: transparent; text-decoration: none;
}
.m-segment i { font-size: 14px; }
.m-segment-active {
    color: #fff; background: #6B46C1;
    box-shadow: 0 2px 8px rgba(107,70,193,0.3);
}

.m-expenses-filter {
    display: flex; gap: 6px; margin-bottom: 16px; overflow-x: auto;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.m-expenses-filter::-webkit-scrollbar { display: none; }
.m-filter-chip {
    padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 600;
    white-space: nowrap; border: 1px solid #2D2D3F; background: #16161F; color: #A8A8B8;
    cursor: pointer; text-decoration: none; min-height: 32px;
    display: flex; align-items: center;
}
.m-filter-chip.active { background: rgba(139,92,246,0.2); color: #8B5CF6; border-color: #8B5CF6; }
.m-expense-card {
    display: flex; align-items: flex-start; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
    position: relative;
}
.m-expense-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(239,68,68,0.15); color: #EF4444;
}
.m-expense-body { flex: 1; min-width: 0; }
.m-expense-vendor { font-size: 13px; font-weight: 700; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-expense-desc { font-size: 12px; color: #A8A8B8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 1px; }
.m-expense-meta { font-size: 11px; color: #A8A8B8; margin-top: 4px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.m-expense-right { text-align: right; flex-shrink: 0; }
.m-expense-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-expense-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-expense-cat { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-expense-status-approved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-expense-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-expense-status-rejected { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-expense-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-expense-payment-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6;
    display: inline-flex; align-items: center; gap: 3px;
}
.m-expense-receipt-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    background: rgba(16,185,129,0.15); color: #10B981;
    display: inline-flex; align-items: center; gap: 3px; cursor: pointer;
    text-decoration: none;
}
.m-expense-actions {
    display: flex; gap: 6px; margin-top: 8px;
}
.m-expense-actions button,
.m-expense-actions a {
    padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
    border: none; cursor: pointer; min-height: 28px;
    display: inline-flex; align-items: center; gap: 4px;
    text-decoration: none;
}
.m-expense-btn-edit { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-expense-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-expense-btn-receipt { background: rgba(16,185,129,0.15); color: #10B981; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }

/* Recurring expense cards */
.m-rec-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-rec-card-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
.m-rec-vendor { font-size: 14px; font-weight: 700; color: #fff; }
.m-rec-type { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-rec-amount { font-size: 15px; font-weight: 700; color: #fff; text-align: right; }
.m-rec-freq { font-size: 11px; color: #A8A8B8; text-align: right; }
.m-rec-details { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
.m-rec-detail {
    font-size: 11px; color: #A8A8B8;
    display: flex; align-items: center; gap: 4px;
}
.m-rec-detail i { font-size: 10px; color: #6B6B7B; }
.m-rec-status-active { color: #10B981; }
.m-rec-status-cancelled { color: #EF4444; }
.m-rec-status-paused { color: #F59E0B; }
.m-rec-status-expired { color: #6B6B7B; }
.m-rec-renewal-warn { color: #F59E0B; }
.m-rec-renewal-urgent { color: #EF4444; }
.m-rec-renewal-ok { color: #10B981; }
.m-rec-description { font-size: 12px; color: #6B6B7B; margin-top: 8px; }

/* OCR scan button */
.m-scan-btn {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    width: 100%; padding: 12px; border-radius: 10px;
    background: rgba(16,185,129,0.15); border: 1px dashed #10B981;
    color: #10B981; font-size: 13px; font-weight: 600;
    cursor: pointer; min-height: 44px; margin-bottom: 14px;
    font-family: Inter, sans-serif;
}
.m-scan-btn:active { background: rgba(16,185,129,0.25); }
.m-scan-btn.m-scanning {
    opacity: 0.7; pointer-events: none;
}
.m-scan-result {
    background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3);
    border-radius: 10px; padding: 10px 12px; margin-bottom: 14px;
    font-size: 12px; color: #10B981; display: none;
}
.m-scan-result.m-show { display: block; }

/* Form row for side-by-side fields */
.m-form-row {
    display: flex; gap: 10px;
}
.m-form-row .m-modal-field { flex: 1; min-width: 0; }

/* FAB */
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}

/* Bottom-sheet modal */
.m-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; }
.m-modal-overlay.m-show { display: flex; align-items: flex-end; }
.m-modal-sheet {
    width: 100%; max-height: 90vh; background: #16161F;
    border-radius: 16px 16px 0 0;
    padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.m-modal-handle {
    width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-modal-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-modal-field { margin-bottom: 14px; }
.m-modal-field label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px;
}
.m-modal-field input,
.m-modal-field select,
.m-modal-field textarea {
    width: 100%; padding: 10px 12px; border-radius: 10px;
    background: #0A0A0F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-modal-field input[type="file"] {
    padding: 8px; font-size: 12px; min-height: auto;
}
.m-modal-field textarea { min-height: 60px; resize: vertical; }
.m-modal-field input:focus,
.m-modal-field select:focus,
.m-modal-field textarea:focus { outline: none; border-color: #8B5CF6; }
.m-modal-field input[readonly] { opacity: 0.7; }
.m-modal-actions {
    display: flex; gap: 10px; margin-top: 16px; padding-bottom: env(safe-area-inset-bottom, 12px);
}
.m-modal-btn-cancel, .m-modal-btn-save {
    flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; min-height: 44px; font-family: Inter, sans-serif;
}
.m-modal-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-modal-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
</style>

<div class="m-expenses">
    <div class="m-expenses-header">
        <div class="m-expenses-header-left">
            <h2 class="m-expenses-title">Expenses</h2>
            <p class="m-expenses-sub"><?php if ($expenses_tab === 'expenses'): ?><?= count($expenses) ?> expense<?= count($expenses) !== 1 ? 's' : '' ?><?php else: ?><?= count($recurring_expenses) ?> contract<?= count($recurring_expenses) !== 1 ? 's' : '' ?><?php endif; ?></p>
        </div>
        <?php if ($expenses_tab === 'expenses'): ?>
        <button class="m-expenses-export" onclick="mExportExpenses()" title="Export Expenses">
            <i class="fas fa-file-export"></i>
        </button>
        <?php endif; ?>
    </div>

    <!-- Segmented Control -->
    <div class="m-segment-control">
        <a href="?page=expenses&expenses_tab=expenses&period=<?= htmlspecialchars($period) ?>" class="m-segment <?= $expenses_tab === 'expenses' ? 'm-segment-active' : '' ?>" aria-pressed="<?= $expenses_tab === 'expenses' ? 'true' : 'false' ?>">
            <i class="fas fa-file-invoice-dollar"></i> Expenses
        </a>
        <a href="?page=expenses&expenses_tab=recurring" class="m-segment <?= $expenses_tab === 'recurring' ? 'm-segment-active' : '' ?>" aria-pressed="<?= $expenses_tab === 'recurring' ? 'true' : 'false' ?>">
            <i class="fas fa-sync-alt"></i> Recurring & Contracts
        </a>
    </div>

    <?php if ($expenses_tab === 'expenses'): ?>

    <div class="m-expenses-summary">
        <div class="m-expenses-summary-label"><?= htmlspecialchars($periodLabel) ?>'s Expenses</div>
        <div class="m-expenses-summary-value">$<?= number_format($monthlyTotal, 2) ?></div>
    </div>

    <!-- Period Filter -->
    <div class="m-expenses-filter">
        <a href="?page=expenses&expenses_tab=expenses&period=week" class="m-filter-chip <?= $period === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="?page=expenses&expenses_tab=expenses&period=month" class="m-filter-chip <?= $period === 'month' ? 'active' : '' ?>">This Month</a>
        <a href="?page=expenses&expenses_tab=expenses&period=quarter" class="m-filter-chip <?= $period === 'quarter' ? 'active' : '' ?>">This Quarter</a>
        <a href="?page=expenses&expenses_tab=expenses&period=year" class="m-filter-chip <?= $period === 'year' ? 'active' : '' ?>">This Year</a>
    </div>

    <?php if (empty($expenses)): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            No expenses recorded
        </div>
    <?php else: ?>
        <?php foreach ($expenses as $exp):
            $status = strtolower($exp['status'] ?? 'default');
            $statusClass = match($status) {
                'approved' => 'approved',
                'pending' => 'pending',
                'rejected', 'denied' => 'rejected',
                default => 'default',
            };
            $displayAmount = (float)($exp['total_amount'] ?: $exp['amount']);
            $payMethodLabel = ucwords(str_replace('_', ' ', $exp['payment_method'] ?? ''));
            $expJson = htmlspecialchars(json_encode($exp), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="m-expense-card" data-expense="<?= $expJson ?>">
            <div class="m-expense-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="m-expense-body">
                <div class="m-expense-vendor"><?= htmlspecialchars($exp['vendor_name'] ?: ($exp['description'] ?: 'Expense')) ?></div>
                <?php if (!empty($exp['vendor_name']) && !empty($exp['description'])): ?>
                    <div class="m-expense-desc"><?= htmlspecialchars($exp['description']) ?></div>
                <?php endif; ?>
                <div class="m-expense-meta">
                    <?php if (!empty($exp['category'])): ?>
                        <span class="m-expense-badge m-expense-cat"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $exp['category']))) ?></span>
                    <?php endif; ?>
                    <span><?= date('M j, Y', strtotime($exp['expense_date'])) ?></span>
                    <span class="m-expense-badge m-expense-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                    <?php if (!empty($exp['payment_method'])): ?>
                        <span class="m-expense-payment-badge"><i class="fas fa-credit-card"></i> <?= htmlspecialchars($payMethodLabel) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($exp['receipt_url'])): ?>
                        <a href="<?= htmlspecialchars($exp['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="m-expense-receipt-badge" onclick="event.stopPropagation()"><i class="fas fa-paperclip"></i> Receipt</a>
                    <?php endif; ?>
                </div>
                <div class="m-expense-actions">
                    <button class="m-expense-btn-edit" onclick="mEditExpense(this.closest('.m-expense-card').dataset.expense)"><i class="fas fa-pen"></i> Edit</button>
                    <button class="m-expense-btn-delete" onclick="mDeleteExpense(<?= (int)$exp['id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
                    <?php if (!empty($exp['receipt_url'])): ?>
                        <a href="<?= htmlspecialchars($exp['receipt_url']) ?>" target="_blank" rel="noopener noreferrer" class="m-expense-btn-receipt" onclick="event.stopPropagation()"><i class="fas fa-eye"></i> View</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="m-expense-right">
                <div class="m-expense-amount">$<?= number_format($displayAmount, 2) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php elseif ($expenses_tab === 'recurring'): ?>

    <!-- Recurring Expenses Tab -->
    <?php if (empty($recurring_expenses)): ?>
        <div class="m-empty-state">
            <i class="fas fa-sync-alt"></i>
            No recurring expenses or contracts
        </div>
    <?php else: ?>
        <?php foreach ($recurring_expenses as $rec):
            $recStatus = strtolower($rec['status'] ?? 'active');
            $recStatusClass = match($recStatus) {
                'active' => 'active',
                'cancelled' => 'cancelled',
                'paused' => 'paused',
                'expired' => 'expired',
                default => 'active',
            };
            $renewalDate = $rec['renewal_date'] ?? null;
            $daysUntilRenewal = null;
            $renewalClass = 'm-rec-renewal-ok';
            if ($renewalDate) {
                $daysUntilRenewal = (int)((strtotime($renewalDate) - time()) / 86400);
                if ($daysUntilRenewal < 0) {
                    $renewalClass = 'm-rec-renewal-urgent';
                } elseif ($daysUntilRenewal <= 30) {
                    $renewalClass = 'm-rec-renewal-warn';
                }
            }
            $freqLabel = ucwords(str_replace('_', ' ', $rec['frequency'] ?? 'monthly'));
        ?>
        <div class="m-rec-card">
            <div class="m-rec-card-header">
                <div>
                    <div class="m-rec-vendor"><?= htmlspecialchars($rec['vendor_name'] ?? 'Unknown Vendor') ?></div>
                    <?php if (!empty($rec['contract_type'])): ?>
                        <div class="m-rec-type"><?= htmlspecialchars($rec['contract_type']) ?></div>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="m-rec-amount">$<?= number_format((float)($rec['amount'] ?? 0), 2) ?></div>
                    <div class="m-rec-freq"><?= htmlspecialchars($freqLabel) ?></div>
                </div>
            </div>
            <div class="m-rec-details">
                <span class="m-rec-detail">
                    <i class="fas fa-circle"></i>
                    <span class="m-rec-status-<?= $recStatusClass ?>"><?= htmlspecialchars(ucfirst($recStatus)) ?></span>
                </span>
                <?php if ($renewalDate): ?>
                <span class="m-rec-detail">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="<?= $renewalClass ?>">
                        Renews <?= date('M j, Y', strtotime($renewalDate)) ?>
                        <?php if ($daysUntilRenewal !== null): ?>
                            (<?= $daysUntilRenewal < 0 ? abs($daysUntilRenewal) . 'd overdue' : $daysUntilRenewal . 'd' ?>)
                        <?php endif; ?>
                    </span>
                </span>
                <?php endif; ?>
                <?php if (!empty($rec['payment_method'])): ?>
                <span class="m-rec-detail">
                    <i class="fas fa-credit-card"></i>
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $rec['payment_method']))) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($rec['category'])): ?>
                <span class="m-rec-detail">
                    <i class="fas fa-tag"></i>
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $rec['category']))) ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($rec['doc_count']) && $rec['doc_count'] > 0): ?>
                <span class="m-rec-detail">
                    <i class="fas fa-paperclip"></i>
                    <?= (int)$rec['doc_count'] ?> doc<?= $rec['doc_count'] > 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($rec['description'])): ?>
                <div class="m-rec-description"><?= htmlspecialchars(mb_strimwidth($rec['description'], 0, 100, '...')) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php endif; ?>
</div>

<!-- FAB: Add Expense or Recurring -->
<?php if ($expenses_tab === 'expenses'): ?>
<button class="m-fab" onclick="mOpenExpenseModal()" title="Add Expense">
    <i class="fas fa-plus"></i>
</button>
<?php else: ?>
<button class="m-fab" onclick="mOpenRecurringModal()" title="Add Recurring Expense">
    <i class="fas fa-plus"></i>
</button>
<?php endif; ?>

<!-- Add/Edit Expense Bottom-Sheet Modal -->
<div class="m-modal-overlay" id="mExpenseModal">
    <div class="m-modal-sheet">
        <div class="m-modal-handle"></div>
        <div class="m-modal-title" id="mExpenseModalTitle">Add Expense</div>

        <!-- OCR Scan Receipt Button -->
        <button type="button" class="m-scan-btn" id="mScanBtn" onclick="mScanReceipt()">
            <i class="fas fa-camera"></i> Scan Receipt (OCR)
        </button>
        <input type="file" id="mOcrFileInput" accept="image/*" capture="environment" style="display:none;">
        <div class="m-scan-result" id="mScanResult">
            <i class="fas fa-check-circle"></i> <span id="mScanResultText">Receipt scanned successfully</span>
        </div>

        <form method="POST" action="process_expenses.php" enctype="multipart/form-data" id="mExpenseForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
            <input type="hidden" name="action" value="create" id="mExpenseAction">
            <input type="hidden" name="expense_id" value="" id="mExpenseId">

            <div class="m-modal-field">
                <label for="mExpenseVendor">Vendor Name *</label>
                <input type="text" name="vendor_name" id="mExpenseVendor" placeholder="Business or vendor name" required>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseDate">Date *</label>
                <input type="date" name="expense_date" id="mExpenseDate" required>
            </div>
            <div class="m-form-row">
                <div class="m-modal-field">
                    <label for="mExpenseSubtotal">Subtotal *</label>
                    <input type="number" name="subtotal" id="mExpenseSubtotal" placeholder="0.00" step="0.01" min="0" required oninput="mCalcTotal()">
                </div>
                <div class="m-modal-field">
                    <label for="mExpenseTax">Tax (GST/HST)</label>
                    <input type="number" name="tax_amount" id="mExpenseTax" placeholder="0.00" step="0.01" min="0" value="0" oninput="mCalcTotal()">
                </div>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseTotal">Total</label>
                <input type="number" name="total_amount" id="mExpenseTotal" placeholder="0.00" step="0.01" readonly>
            </div>
            <!-- Hidden amount field for backward compatibility -->
            <input type="hidden" name="amount" id="mExpenseAmount">
            <div class="m-form-row">
                <div class="m-modal-field">
                    <label for="mExpensePayment">Payment Method</label>
                    <select name="payment_method" id="mExpensePayment">
                        <option value="">Select</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="debit">Debit Card</option>
                        <option value="cash">Cash</option>
                        <option value="e_transfer">E-Transfer</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="stripe">Stripe</option>
                    </select>
                </div>
                <div class="m-modal-field">
                    <label for="mExpenseCurrency">Currency</label>
                    <select name="currency" id="mExpenseCurrency">
                        <option value="CAD">CAD</option>
                        <option value="USD">USD</option>
                    </select>
                </div>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseCategory">Category *</label>
                <select name="category" id="mExpenseCategory" required>
                    <option value="">Select category</option>
                    <?php if (!empty($pwa_categories)): ?>
                        <?php foreach ($pwa_categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['name'] ?? $cat['slug'] ?? '') ?>"><?= htmlspecialchars($cat['name'] ?? '') ?></option>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <option value="equipment">Equipment</option>
                        <option value="travel">Travel</option>
                        <option value="training">Training</option>
                        <option value="food">Food</option>
                        <option value="medical">Medical</option>
                        <option value="office">Office</option>
                        <option value="other">Other</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="m-modal-field">
                <label for="mExpensePayee">Payee</label>
                <select name="payee_id" id="mExpensePayee">
                    <option value="">No payee</option>
                    <?php foreach ($pwa_payees as $payee): ?>
                        <option value="<?= (int)$payee['id'] ?>"><?= htmlspecialchars($payee['name'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseRef">Reference Number</label>
                <input type="text" name="reference_number" id="mExpenseRef" placeholder="Invoice or reference #">
            </div>
            <div class="m-modal-field">
                <label for="mExpenseDesc">Description</label>
                <textarea name="description" id="mExpenseDesc" placeholder="Details about this expense" rows="2"></textarea>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseReceipt">Receipt / Invoice</label>
                <input type="file" name="receipt_file" id="mExpenseReceipt" accept="image/*,application/pdf" capture="environment">
            </div>
            <div class="m-modal-actions">
                <button type="button" class="m-modal-btn-cancel" onclick="mCloseExpenseModal()">Cancel</button>
                <button type="submit" class="m-modal-btn-save" id="mExpenseSaveBtn">Save Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Recurring Expense Bottom-Sheet Modal -->
<div class="m-modal-overlay" id="mRecurringModal">
    <div class="m-modal-sheet">
        <div class="m-modal-handle"></div>
        <div class="m-modal-title">Add Recurring Expense</div>
        <form method="POST" action="process_recurring_expenses.php" enctype="multipart/form-data" id="mRecurringForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
            <input type="hidden" name="action" value="create">
            <div class="m-modal-field">
                <label for="mRecVendor">Vendor Name *</label>
                <input type="text" name="vendor_name" id="mRecVendor" placeholder="Company or vendor" required>
            </div>
            <div class="m-form-row">
                <div class="m-modal-field">
                    <label for="mRecType">Contract Type</label>
                    <input type="text" name="contract_type" id="mRecType" placeholder="e.g. Lease, Software">
                </div>
                <div class="m-modal-field">
                    <label for="mRecCategory">Category</label>
                    <select name="category" id="mRecCategory">
                        <option value="">Select</option>
                        <?php if (!empty($pwa_categories)): ?>
                            <?php foreach ($pwa_categories as $cat): ?>
                                <option value="<?= htmlspecialchars($cat['name'] ?? $cat['slug'] ?? '') ?>"><?= htmlspecialchars($cat['name'] ?? '') ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="software">Software</option>
                            <option value="rent">Rent</option>
                            <option value="insurance">Insurance</option>
                            <option value="utilities">Utilities</option>
                            <option value="equipment">Equipment</option>
                            <option value="other">Other</option>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            <div class="m-modal-field">
                <label for="mRecDesc">Description</label>
                <textarea name="description" id="mRecDesc" placeholder="Contract details" rows="2"></textarea>
            </div>
            <div class="m-form-row">
                <div class="m-modal-field">
                    <label for="mRecAmount">Amount *</label>
                    <input type="number" name="amount" id="mRecAmount" placeholder="0.00" step="0.01" min="0.01" required>
                </div>
                <div class="m-modal-field">
                    <label for="mRecFrequency">Frequency *</label>
                    <select name="frequency" id="mRecFrequency" required>
                        <option value="monthly">Monthly</option>
                        <option value="quarterly">Quarterly</option>
                        <option value="semi_annual">Semi-Annual</option>
                        <option value="annual">Annual</option>
                    </select>
                </div>
            </div>
            <div class="m-form-row">
                <div class="m-modal-field">
                    <label for="mRecStart">Start Date *</label>
                    <input type="date" name="contract_start_date" id="mRecStart" required>
                </div>
                <div class="m-modal-field">
                    <label for="mRecEnd">End Date</label>
                    <input type="date" name="contract_end_date" id="mRecEnd">
                </div>
            </div>
            <div class="m-form-row">
                <div class="m-modal-field">
                    <label for="mRecRenewal">Renewal Date</label>
                    <input type="date" name="renewal_date" id="mRecRenewal">
                </div>
                <div class="m-modal-field">
                    <label for="mRecPayment">Payment Method</label>
                    <select name="payment_method" id="mRecPayment">
                        <option value="">Select</option>
                        <option value="credit_card">Credit Card</option>
                        <option value="bank_transfer">Bank Transfer</option>
                        <option value="e_transfer">E-Transfer</option>
                        <option value="cheque">Cheque</option>
                        <option value="cash">Cash</option>
                        <option value="stripe">Stripe</option>
                    </select>
                </div>
            </div>
            <div class="m-modal-field">
                <label for="mRecContract">Contract File</label>
                <input type="file" name="contract_file" id="mRecContract" accept="image/*,application/pdf,.doc,.docx">
            </div>
            <div class="m-modal-actions">
                <button type="button" class="m-modal-btn-cancel" onclick="mCloseRecurringModal()">Cancel</button>
                <button type="submit" class="m-modal-btn-save">Save Recurring Expense</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Expense Hidden Form -->
<form method="POST" action="process_expenses.php" id="mDeleteForm" style="display:none;">
    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="expense_id" value="" id="mDeleteExpenseId">
</form>

<script>
(function() {
    var expModal = document.getElementById('mExpenseModal');
    var recModal = document.getElementById('mRecurringModal');
    var expForm = document.getElementById('mExpenseForm');

    // Auto-calculate total from subtotal + tax
    window.mCalcTotal = function() {
        var sub = parseFloat(document.getElementById('mExpenseSubtotal').value) || 0;
        var tax = parseFloat(document.getElementById('mExpenseTax').value) || 0;
        var total = (sub + tax).toFixed(2);
        document.getElementById('mExpenseTotal').value = total;
        document.getElementById('mExpenseAmount').value = total;
    };

    // Open expense modal for new entry
    window.mOpenExpenseModal = function() {
        document.getElementById('mExpenseModalTitle').textContent = 'Add Expense';
        document.getElementById('mExpenseAction').value = 'create';
        document.getElementById('mExpenseId').value = '';
        expForm.reset();
        document.getElementById('mExpenseDate').value = new Date().toISOString().split('T')[0];
        document.getElementById('mExpenseTotal').value = '';
        document.getElementById('mExpenseAmount').value = '';
        document.getElementById('mScanResult').classList.remove('m-show');
        document.getElementById('mScanBtn').classList.remove('m-scanning');
        expModal.classList.add('m-show');
    };

    // Close expense modal
    window.mCloseExpenseModal = function() {
        expModal.classList.remove('m-show');
    };

    // Edit expense - pre-fill all enhanced fields
    window.mEditExpense = function(jsonStr) {
        var exp = JSON.parse(jsonStr);
        document.getElementById('mExpenseModalTitle').textContent = 'Edit Expense';
        document.getElementById('mExpenseAction').value = 'update';
        document.getElementById('mExpenseId').value = exp.id;
        document.getElementById('mExpenseVendor').value = exp.vendor_name || '';
        document.getElementById('mExpenseDesc').value = exp.description || '';
        document.getElementById('mExpenseSubtotal').value = parseFloat(exp.subtotal) || parseFloat(exp.amount) || '';
        document.getElementById('mExpenseTax').value = parseFloat(exp.tax_amount) || 0;
        document.getElementById('mExpenseCategory').value = exp.category || '';
        document.getElementById('mExpenseDate').value = exp.expense_date || '';
        document.getElementById('mExpensePayment').value = exp.payment_method || '';
        document.getElementById('mExpenseCurrency').value = exp.currency || 'CAD';
        document.getElementById('mExpensePayee').value = exp.payee_id || '';
        document.getElementById('mExpenseRef').value = exp.reference_number || '';
        document.getElementById('mScanResult').classList.remove('m-show');
        mCalcTotal();
        expModal.classList.add('m-show');
    };

    // Delete expense
    window.mDeleteExpense = async function(id) {
        if (await showConfirmModal('Are you sure you want to delete this expense?')) {
            document.getElementById('mDeleteExpenseId').value = id;
            document.getElementById('mDeleteForm').submit();
        }
    };

    // OCR Receipt Scanning
    var ocrInput = document.getElementById('mOcrFileInput');

    window.mScanReceipt = function() {
        ocrInput.click();
    };

    ocrInput.addEventListener('change', function() {
        if (!this.files || !this.files[0]) return;

        var file = this.files[0];
        var scanBtn = document.getElementById('mScanBtn');
        var scanResult = document.getElementById('mScanResult');
        var scanText = document.getElementById('mScanResultText');

        scanBtn.classList.add('m-scanning');
        scanBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Scanning receipt...';
        scanResult.classList.remove('m-show');

        var formData = new FormData();
        formData.append('receipt_file', file);
        formData.append('action', 'ocr_scan');
        formData.append('csrf_token', '<?= $csrf_token ?? '' ?>');

        fetch('process_expenses.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            scanBtn.classList.remove('m-scanning');
            scanBtn.innerHTML = '<i class="fas fa-camera"></i> Scan Receipt (OCR)';

            if (data.success && data.ocr_data) {
                var ocr = data.ocr_data;
                if (ocr.vendor) document.getElementById('mExpenseVendor').value = ocr.vendor;
                if (ocr.date) document.getElementById('mExpenseDate').value = ocr.date;
                if (ocr.subtotal) document.getElementById('mExpenseSubtotal').value = parseFloat(ocr.subtotal).toFixed(2);
                if (ocr.tax) document.getElementById('mExpenseTax').value = parseFloat(ocr.tax).toFixed(2);
                mCalcTotal();
                scanText.textContent = 'Receipt scanned — fields auto-filled';
                scanResult.style.color = '#10B981';
                scanResult.style.borderColor = 'rgba(16,185,129,0.3)';
                scanResult.classList.add('m-show');
            } else {
                scanText.textContent = data.message || 'Could not read receipt. Fill fields manually.';
                scanResult.style.color = '#F59E0B';
                scanResult.style.borderColor = 'rgba(245,158,11,0.3)';
                scanResult.classList.add('m-show');
            }
        })
        .catch(function() {
            scanBtn.classList.remove('m-scanning');
            scanBtn.innerHTML = '<i class="fas fa-camera"></i> Scan Receipt (OCR)';
            scanText.textContent = 'Scan failed. Please fill fields manually.';
            scanResult.style.color = '#EF4444';
            scanResult.style.borderColor = 'rgba(239,68,68,0.3)';
            scanResult.classList.add('m-show');
        });

        // Reset file input so the same file can be re-selected
        this.value = '';
    });

    // Sync amount hidden field on form submit
    expForm.addEventListener('submit', function() {
        mCalcTotal();
    });

    // Open recurring modal
    window.mOpenRecurringModal = function() {
        document.getElementById('mRecurringForm').reset();
        document.getElementById('mRecStart').value = new Date().toISOString().split('T')[0];
        recModal.classList.add('m-show');
    };

    // Close recurring modal
    window.mCloseRecurringModal = function() {
        recModal.classList.remove('m-show');
    };

    // Export expenses as CSV with all enhanced fields
    window.mExportExpenses = function() {
        var rows = [['Vendor','Description','Subtotal','Tax','Total','Category','Date','Status','Payment Method','Currency','Payee','Reference','Receipt']];
        <?php foreach ($expenses as $exp): ?>
        rows.push([
            <?= json_encode($exp['vendor_name'] ?? '') ?>,
            <?= json_encode($exp['description'] ?? '') ?>,
            <?= json_encode((string)($exp['subtotal'] ?? '')) ?>,
            <?= json_encode((string)($exp['tax_amount'] ?? '')) ?>,
            <?= json_encode((string)($exp['total_amount'] ?? $exp['amount'] ?? '0')) ?>,
            <?= json_encode($exp['category'] ?? '') ?>,
            <?= json_encode($exp['expense_date'] ?? '') ?>,
            <?= json_encode($exp['status'] ?? '') ?>,
            <?= json_encode($exp['payment_method'] ?? '') ?>,
            <?= json_encode($exp['currency'] ?? '') ?>,
            <?= json_encode($exp['payee_name'] ?? '') ?>,
            <?= json_encode($exp['reference_number'] ?? '') ?>,
            <?= json_encode($exp['receipt_url'] ?? '') ?>
        ]);
        <?php endforeach; ?>
        function escapeCsvField(f) { return '"' + (f || '').replace(/"/g, '""') + '"'; }
        var csv = rows.map(function(r) {
            return r.map(escapeCsvField).join(',');
        }).join('\n');
        var blob = new Blob([csv], {type:'text/csv'});
        var a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'expenses_export.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    };

    // Close modals on overlay tap
    expModal.addEventListener('click', function(e) {
        if (e.target === expModal) mCloseExpenseModal();
    });
    recModal.addEventListener('click', function(e) {
        if (e.target === recModal) mCloseRecurringModal();
    });
})();
</script>
