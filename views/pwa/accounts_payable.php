<?php
/**
 * PWA Accounts Payable - Mobile-native AP management
 * Purpose-built for mobile phones.
 */

if (!$canAccessAccounting) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Accounting access required.</p>';
    echo '</div>';
    return;
}

$payables = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, vendor_name, COALESCE(total_amount, amount) as amount,
               expense_date as due_date, status, category, description
        FROM expenses
        ORDER BY expense_date ASC
        LIMIT 20
    ");
    $stmt->execute();
    $payables = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $payables = []; }

$categories = [];
try {
    $categories = $pdo->query("SELECT name FROM expense_categories WHERE is_active = 1 ORDER BY display_order")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; }

$totalPayables = count($payables);
$csrf_token = $_SESSION['csrf_token'] ?? '';
?>
<style>
.m-ap { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 90px; }
.m-ap-header { margin-bottom: 16px; }
.m-ap-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ap-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ap-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-ap-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-ap-vendor { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-ap-amount { font-size: 15px; font-weight: 700; color: #EF4444; flex-shrink: 0; }
.m-ap-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-ap-due { font-size: 12px; color: #A8A8B8; display: flex; align-items: center; gap: 4px; }
.m-ap-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-ap-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-ap-badge-paid { background: rgba(16,185,129,0.15); color: #10B981; }
.m-ap-badge-overdue { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-ap-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* Card actions */
.m-ap-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-ap-actions button {
    flex: 1; padding: 8px 10px; border-radius: 8px; border: none; cursor: pointer;
    font-size: 12px; font-weight: 600; font-family: Inter, sans-serif;
    display: inline-flex; align-items: center; justify-content: center; gap: 4px;
    min-height: 44px;
}
.m-ap-btn-paid { background: rgba(16,185,129,0.15); color: #10B981; }
.m-ap-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }

/* FAB */
.m-ap-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}

/* Bottom-sheet modal */
.m-ap-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; }
.m-ap-overlay.m-show { display: flex; align-items: flex-end; }
.m-ap-sheet {
    width: 100%; max-height: 90vh; background: #0A0A0F;
    border-radius: 16px 16px 0 0;
    padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.m-ap-handle {
    width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-ap-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-ap-field { margin-bottom: 14px; }
.m-ap-field label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px;
}
.m-ap-field input,
.m-ap-field select,
.m-ap-field textarea {
    width: 100%; padding: 10px 12px; border-radius: 10px;
    background: #16161F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-ap-field textarea { min-height: 60px; resize: vertical; }
.m-ap-field input:focus,
.m-ap-field select:focus,
.m-ap-field textarea:focus { outline: none; border-color: #6B46C1; }
.m-ap-sheet-actions {
    display: flex; gap: 10px; margin-top: 16px; padding-bottom: env(safe-area-inset-bottom, 12px);
}
.m-ap-btn-cancel, .m-ap-btn-save {
    flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; min-height: 44px; font-family: Inter, sans-serif;
}
.m-ap-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-ap-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }

/* Toast */
.m-ap-toast {
    position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
    padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    font-family: Inter, sans-serif; z-index: 300; opacity: 0;
    transition: opacity 0.3s;
}
.m-ap-toast.m-show { opacity: 1; }
.m-ap-toast-success { background: rgba(16,185,129,0.9); color: #fff; }
.m-ap-toast-error { background: rgba(239,68,68,0.9); color: #fff; }
</style>

<div class="m-ap">
    <div class="m-ap-header">
        <h2 class="m-ap-title">Accounts Payable</h2>
        <p class="m-ap-sub"><?= $totalPayables ?> record<?= $totalPayables !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($payables)): ?>
        <div class="m-empty-state">
            <i class="fas fa-file-invoice-dollar"></i>
            <p>No accounts payable records</p>
        </div>
    <?php else: ?>
        <?php foreach ($payables as $p):
            $rawStatus = strtolower($p['status'] ?? 'pending');
            $status = match($rawStatus) {
                'approved' => 'paid',
                default => $rawStatus,
            };
            $badgeClass = match($status) {
                'paid' => 'paid',
                'overdue' => 'overdue',
                'pending' => 'pending',
                'rejected' => 'overdue',
                default => 'default',
            };
            $isPending = in_array($status, ['pending', 'overdue']);
        ?>
        <div class="m-ap-card">
            <div class="m-ap-top">
                <span class="m-ap-vendor"><?= htmlspecialchars($p['vendor_name'] ?? 'Unknown Vendor') ?></span>
                <span class="m-ap-amount">$<?= number_format((float)($p['amount'] ?? 0), 2) ?></span>
            </div>
            <div class="m-ap-bottom">
                <div class="m-ap-due">
                    <?php if (!empty($p['due_date'])): ?>
                    <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($p['due_date'])) ?>
                    <?php endif; ?>
                </div>
                <span class="m-ap-badge m-ap-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-ap-actions">
                <?php if ($isPending): ?>
                <button class="m-ap-btn-paid" data-ap-mark="<?= (int)$p['id'] ?>"><i class="fas fa-check-circle"></i> Mark Paid</button>
                <?php endif; ?>
                <button class="m-ap-btn-delete" data-ap-delete="<?= (int)$p['id'] ?>"><i class="fas fa-trash"></i> Delete</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB: Add Bill -->
<button class="m-ap-fab" onclick="mApOpenSheet()" title="Add Bill">
    <i class="fas fa-plus"></i>
</button>

<!-- Add Bill Bottom Sheet -->
<div class="m-ap-overlay" id="mApOverlay" onclick="if(event.target===this)mApCloseSheet()">
    <div class="m-ap-sheet">
        <div class="m-ap-handle"></div>
        <div class="m-ap-sheet-title">Add Bill</div>
        <form method="POST" action="process_expenses.php" id="mApForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="create">
            <div class="m-ap-field">
                <label for="mApVendor">Vendor Name *</label>
                <input type="text" name="vendor_name" id="mApVendor" required placeholder="e.g. Office Supply Co.">
            </div>
            <div class="m-ap-field">
                <label for="mApAmount">Amount *</label>
                <input type="number" name="subtotal" id="mApAmount" step="0.01" min="0.01" required placeholder="0.00">
            </div>
            <input type="hidden" name="tax_amount" value="0">
            <div class="m-ap-field">
                <label for="mApDate">Due Date *</label>
                <input type="date" name="expense_date" id="mApDate" required>
            </div>
            <div class="m-ap-field">
                <label for="mApCategory">Category *</label>
                <select name="category" id="mApCategory" required>
                    <option value="">Select category</option>
                    <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="equipment">Equipment</option>
                    <option value="travel">Travel</option>
                    <option value="office">Office</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="m-ap-field">
                <label for="mApDesc">Description</label>
                <textarea name="description" id="mApDesc" rows="3" placeholder="Details about this bill..."></textarea>
            </div>
            <div class="m-ap-sheet-actions">
                <button type="button" class="m-ap-btn-cancel" onclick="mApCloseSheet()">Cancel</button>
                <button type="submit" class="m-ap-btn-save">Save Bill</button>
            </div>
        </form>
    </div>
</div>

<!-- Hidden Delete Form -->
<form method="POST" action="process_expenses.php" id="mApDeleteForm" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="expense_id" value="" id="mApDeleteId">
</form>

<!-- Toast -->
<div class="m-ap-toast" id="mApToast"></div>

<script>
(function() {
    var overlay = document.getElementById('mApOverlay');
    var csrfToken = document.querySelector('#mApForm input[name="csrf_token"]').value;

    window.mApOpenSheet = function() {
        document.getElementById('mApForm').reset();
        document.getElementById('mApDate').value = new Date().toISOString().split('T')[0];
        overlay.classList.add('m-show');
    };

    window.mApCloseSheet = function() {
        overlay.classList.remove('m-show');
    };

    // Compute total_amount from subtotal on submit
    document.getElementById('mApForm').addEventListener('submit', function() {
        var sub = parseFloat(document.getElementById('mApAmount').value) || 0;
        var existing = this.querySelector('input[name="total_amount"]');
        if (!existing) {
            var h = document.createElement('input');
            h.type = 'hidden'; h.name = 'total_amount'; h.value = sub.toFixed(2);
            this.appendChild(h);
        } else {
            existing.value = sub.toFixed(2);
        }
    });

    function showToast(msg, type) {
        var t = document.getElementById('mApToast');
        t.textContent = msg;
        t.className = 'm-ap-toast m-ap-toast-' + (type || 'success') + ' m-show';
        setTimeout(function() { t.classList.remove('m-show'); }, 2500);
    }

    // Mark as Paid
    document.querySelectorAll('[data-ap-mark]').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var id = this.getAttribute('data-ap-mark');
            if (!await showConfirmModal('Mark this bill as paid?')) return;
            var self = this;
            self.disabled = true;
            fetch('process_expenses.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=mark_paid&expense_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    persistToast(data.message || 'Marked as paid!', 'success');
                    location.reload();
                } else {
                    self.disabled = false;
                    showToast(data.message || 'Failed', 'error');
                }
            })
            .catch(function() {
                self.disabled = false;
                showToast('An error occurred', 'error');
            });
        });
    });

    // Delete
    document.querySelectorAll('[data-ap-delete]').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            if (!await showConfirmModal('Delete this bill?')) return;
            document.getElementById('mApDeleteId').value = this.getAttribute('data-ap-delete');
            document.getElementById('mApDeleteForm').submit();
        });
    });
})();
</script>
