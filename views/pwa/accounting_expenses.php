<?php
/**
 * PWA Accounting Expenses - Mobile-native expense tracking
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

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
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE $periodWhere");
    $stmt->execute();
    $monthlyTotal = (float)$stmt->fetchColumn();
} catch (PDOException $e) { $monthlyTotal = 0; }

$expenses = [];
try {
    $stmt = $pdo->prepare("SELECT id, description, amount, category, expense_date, status, receipt_url FROM expenses WHERE $periodWhere ORDER BY expense_date DESC LIMIT 50");
    $stmt->execute();
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $expenses = []; }

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
    display: flex; align-items: center; gap: 12px;
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
.m-expense-desc { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-expense-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
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
.m-expense-actions {
    display: flex; gap: 6px; margin-top: 8px;
}
.m-expense-actions button {
    padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
    border: none; cursor: pointer; min-height: 28px;
    display: inline-flex; align-items: center; gap: 4px;
}
.m-expense-btn-edit { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-expense-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }

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
            <p class="m-expenses-sub"><?= count($expenses) ?> expense<?= count($expenses) !== 1 ? 's' : '' ?></p>
        </div>
        <button class="m-expenses-export" onclick="mExportExpenses()" title="Export Expenses">
            <i class="fas fa-file-export"></i>
        </button>
    </div>

    <div class="m-expenses-summary">
        <div class="m-expenses-summary-label"><?= htmlspecialchars($periodLabel) ?>'s Expenses</div>
        <div class="m-expenses-summary-value">$<?= number_format($monthlyTotal, 2) ?></div>
    </div>

    <!-- Period Filter -->
    <div class="m-expenses-filter">
        <a href="?page=expenses&period=week" class="m-filter-chip <?= $period === 'week' ? 'active' : '' ?>">This Week</a>
        <a href="?page=expenses&period=month" class="m-filter-chip <?= $period === 'month' ? 'active' : '' ?>">This Month</a>
        <a href="?page=expenses&period=quarter" class="m-filter-chip <?= $period === 'quarter' ? 'active' : '' ?>">This Quarter</a>
        <a href="?page=expenses&period=year" class="m-filter-chip <?= $period === 'year' ? 'active' : '' ?>">This Year</a>
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
            $expJson = htmlspecialchars(json_encode($exp), ENT_QUOTES, 'UTF-8');
        ?>
        <div class="m-expense-card" data-expense="<?= $expJson ?>">
            <div class="m-expense-icon">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="m-expense-body">
                <div class="m-expense-desc"><?= htmlspecialchars($exp['description'] ?: 'Expense') ?></div>
                <div class="m-expense-meta">
                    <?php if (!empty($exp['category'])): ?>
                        <span class="m-expense-badge m-expense-cat"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $exp['category']))) ?></span>
                    <?php endif; ?>
                    <span><?= date('M j, Y', strtotime($exp['expense_date'])) ?></span>
                    <span class="m-expense-badge m-expense-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <div class="m-expense-actions">
                    <button class="m-expense-btn-edit" onclick="mEditExpense(this.closest('.m-expense-card').dataset.expense)"><i class="fas fa-pen"></i> Edit</button>
                    <button class="m-expense-btn-delete" onclick="mDeleteExpense(<?= (int)$exp['id'] ?>)"><i class="fas fa-trash"></i> Delete</button>
                </div>
            </div>
            <div class="m-expense-right">
                <div class="m-expense-amount">$<?= number_format((float)$exp['amount'], 2) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB: Add Expense -->
<button class="m-fab" onclick="mOpenExpenseModal()" title="Add Expense">
    <i class="fas fa-plus"></i>
</button>

<!-- Add/Edit Expense Bottom-Sheet Modal -->
<div class="m-modal-overlay" id="mExpenseModal">
    <div class="m-modal-sheet">
        <div class="m-modal-handle"></div>
        <div class="m-modal-title" id="mExpenseModalTitle">Add Expense</div>
        <form method="POST" action="process_expenses.php" enctype="multipart/form-data" id="mExpenseForm">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
            <input type="hidden" name="action" value="create" id="mExpenseAction">
            <input type="hidden" name="expense_id" value="" id="mExpenseId">
            <div class="m-modal-field">
                <label for="mExpenseDesc">Description</label>
                <input type="text" name="description" id="mExpenseDesc" placeholder="What was this expense for?">
            </div>
            <div class="m-modal-field">
                <label for="mExpenseAmount">Amount *</label>
                <input type="number" name="amount" id="mExpenseAmount" placeholder="0.00" step="0.01" min="0.01" required>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseCategory">Category *</label>
                <select name="category" id="mExpenseCategory" required>
                    <option value="">Select category</option>
                    <option value="equipment">Equipment</option>
                    <option value="travel">Travel</option>
                    <option value="training">Training</option>
                    <option value="food">Food</option>
                    <option value="medical">Medical</option>
                    <option value="office">Office</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseDate">Date *</label>
                <input type="date" name="expense_date" id="mExpenseDate" required>
            </div>
            <div class="m-modal-field">
                <label for="mExpenseReceipt">Receipt</label>
                <input type="file" name="receipt_file" id="mExpenseReceipt" accept="image/*,application/pdf" capture="environment">
            </div>
            <div class="m-modal-actions">
                <button type="button" class="m-modal-btn-cancel" onclick="mCloseExpenseModal()">Cancel</button>
                <button type="submit" class="m-modal-btn-save" id="mExpenseSaveBtn">Save Expense</button>
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
    var modal = document.getElementById('mExpenseModal');
    var form = document.getElementById('mExpenseForm');

    // Open modal for new expense
    window.mOpenExpenseModal = function() {
        document.getElementById('mExpenseModalTitle').textContent = 'Add Expense';
        document.getElementById('mExpenseAction').value = 'create';
        document.getElementById('mExpenseId').value = '';
        form.reset();
        document.getElementById('mExpenseDate').value = new Date().toISOString().split('T')[0];
        modal.classList.add('m-show');
    };

    // Close modal
    window.mCloseExpenseModal = function() {
        modal.classList.remove('m-show');
    };

    // Edit expense - pre-fill the modal
    window.mEditExpense = function(jsonStr) {
        var exp = JSON.parse(jsonStr);
        document.getElementById('mExpenseModalTitle').textContent = 'Edit Expense';
        document.getElementById('mExpenseAction').value = 'update';
        document.getElementById('mExpenseId').value = exp.id;
        document.getElementById('mExpenseDesc').value = exp.description || '';
        document.getElementById('mExpenseAmount').value = parseFloat(exp.amount) || '';
        document.getElementById('mExpenseCategory').value = exp.category || '';
        document.getElementById('mExpenseDate').value = exp.expense_date || '';
        modal.classList.add('m-show');
    };

    // Delete expense with confirmation
    window.mDeleteExpense = function(id) {
        if (confirm('Are you sure you want to delete this expense?')) {
            document.getElementById('mDeleteExpenseId').value = id;
            document.getElementById('mDeleteForm').submit();
        }
    };

    // Export expenses as CSV
    window.mExportExpenses = function() {
        var rows = [['Description','Amount','Category','Date','Status']];
        <?php foreach ($expenses as $exp): ?>
        rows.push([
            <?= json_encode($exp['description'] ?? '') ?>,
            <?= json_encode((string)($exp['amount'] ?? '0')) ?>,
            <?= json_encode($exp['category'] ?? '') ?>,
            <?= json_encode($exp['expense_date'] ?? '') ?>,
            <?= json_encode($exp['status'] ?? '') ?>

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

    // Close modal on overlay tap
    modal.addEventListener('click', function(e) {
        if (e.target === modal) mCloseExpenseModal();
    });
})();
</script>
