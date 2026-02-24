<?php
/**
 * PWA Refunds - Mobile-native refunds list
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

// Query refunds table (existing records)
$refundsRaw = [];
try {
    $stmt = $pdo->prepare("
        SELECT r.id, r.amount, r.status, r.reason, r.created_at,
               u.first_name, u.last_name, 'refunds' AS source
        FROM refunds r
        LEFT JOIN users u ON u.id = r.user_id
        ORDER BY r.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $refundsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $refundsRaw = decryptUserRows($refundsRaw);
} catch (PDOException $e) { $refundsRaw = []; }

// Query credits_refunds table (actionable records)
$creditsRefundsRaw = [];
try {
    $stmt = $pdo->prepare("
        SELECT cr.id, cr.amount, cr.status, cr.reason, cr.created_at,
               cr.transaction_type, u.first_name, u.last_name, 'credits_refunds' AS source
        FROM credits_refunds cr
        LEFT JOIN users u ON u.id = cr.user_id
        ORDER BY cr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $creditsRefundsRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $creditsRefundsRaw = decryptUserRows($creditsRefundsRaw);
} catch (PDOException $e) { $creditsRefundsRaw = []; }

// Merge and sort by date descending
$allRefunds = array_merge($refundsRaw, $creditsRefundsRaw);
usort($allRefunds, function($a, $b) {
    return strtotime($b['created_at'] ?? '0') - strtotime($a['created_at'] ?? '0');
});

$totalRefunds = count($allRefunds);
?>
<style>
.m-refunds { padding: 0; font-family: Inter, sans-serif; }
.m-refunds-header { padding: 16px 16px 0; margin-bottom: 0; }
.m-refunds-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-refunds-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-refunds-list { padding: 0 16px 80px; }
.m-refund-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-refund-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-refund-user { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-refund-amount { font-size: 15px; font-weight: 700; color: #EF4444; flex-shrink: 0; }
.m-refund-reason { font-size: 12px; color: #A8A8B8; margin: 4px 0 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-refund-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-refund-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-refund-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-refund-badge-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-refund-badge-approved { background: rgba(16,185,129,0.15); color: #10B981; }
.m-refund-badge-rejected { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-refund-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-refund-actions {
    display: flex; gap: 8px; margin-top: 10px;
}
.m-refund-actions button {
    flex: 1; min-height: 44px; border-radius: 10px; border: none;
    font-size: 13px; font-weight: 600; cursor: pointer;
    font-family: Inter, sans-serif; display: flex; align-items: center;
    justify-content: center; gap: 6px;
}
.m-btn-approve { background: rgba(16,185,129,0.15); color: #10B981; }
.m-btn-reject { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
/* Tabs */
.m-refund-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-refund-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 13px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-refund-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
/* FAB */
.m-refund-fab {
    position: fixed; bottom: 60px; right: 16px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: #6B46C1; color: #fff; border: none; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    font-size: 22px; box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    min-height: 44px; font-family: Inter, sans-serif;
}
/* Bottom sheet */
.m-refund-overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.6);
}
.m-refund-overlay.m-overlay-open { display: flex; align-items: flex-end; }
.m-refund-sheet {
    width: 100%; max-height: 90vh; overflow-y: auto;
    background: #16161F; border: 1px solid #2D2D3F;
    border-radius: 16px 16px 0 0; padding: 20px 16px 32px;
}
.m-refund-sheet-handle {
    width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px;
    margin: 0 auto 16px;
}
.m-refund-sheet-title {
    font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 16px;
    font-family: Inter, sans-serif;
}
.m-refund-form-group { margin-bottom: 14px; }
.m-refund-form-label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8;
    margin-bottom: 6px; font-family: Inter, sans-serif;
}
.m-refund-form-input {
    width: 100%; box-sizing: border-box;
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px;
    font-size: 14px; font-family: Inter, sans-serif;
    -webkit-appearance: none;
}
.m-refund-form-input:focus { outline: none; border-color: #6B46C1; }
.m-refund-form-submit {
    width: 100%; background: #6B46C1; color: #fff; border: none;
    border-radius: 10px; min-height: 44px; font-size: 14px;
    font-weight: 600; cursor: pointer; margin-top: 8px;
    font-family: Inter, sans-serif;
}
.m-refund-form-cancel {
    width: 100%; background: #2D2D3F; color: #A8A8B8; border: none;
    border-radius: 10px; min-height: 44px; font-size: 14px;
    font-weight: 600; cursor: pointer; margin-top: 8px;
    font-family: Inter, sans-serif;
}
.m-refund-toast {
    display: none; position: fixed; bottom: 80px; left: 16px; right: 16px;
    z-index: 200; padding: 14px 16px; border-radius: 10px;
    font-size: 13px; font-weight: 600; text-align: center;
    font-family: Inter, sans-serif;
}
.m-refund-toast-success { background: rgba(16,185,129,0.9); color: #fff; }
.m-refund-toast-error { background: rgba(239,68,68,0.9); color: #fff; }
.m-refund-type-label {
    font-size: 10px; color: #6B6B7B; margin-left: 6px; font-weight: 400;
}
</style>

<div class="m-refunds">
    <div class="m-refunds-header">
        <h2 class="m-refunds-title">Refunds</h2>
        <p class="m-refunds-sub"><?= $totalRefunds ?> refund<?= $totalRefunds !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Status Filter Tabs -->
    <div class="m-refund-tabs">
        <button class="m-refund-tab m-tab-active" onclick="mRefundFilter('all', this)" type="button">All</button>
        <button class="m-refund-tab" onclick="mRefundFilter('pending', this)" type="button">Pending</button>
        <button class="m-refund-tab" onclick="mRefundFilter('approved', this)" type="button">Approved</button>
        <button class="m-refund-tab" onclick="mRefundFilter('rejected', this)" type="button">Rejected</button>
    </div>

    <div class="m-refunds-list">
    <?php if (empty($allRefunds)): ?>
        <div class="m-empty-state" id="m-refund-empty-all">
            <i class="fas fa-receipt"></i>
            <p>No refund records</p>
        </div>
    <?php else: ?>
        <?php foreach ($allRefunds as $r):
            $status = strtolower($r['status'] ?? 'pending');
            $badgeClass = match($status) {
                'approved', 'completed', 'processed' => 'approved',
                'rejected', 'denied' => 'rejected',
                'pending' => 'pending',
                default => 'default',
            };
            $filterGroup = match($status) {
                'approved', 'completed', 'processed' => 'approved',
                'rejected', 'denied' => 'rejected',
                'pending' => 'pending',
                default => 'other',
            };
            $statusLabel = match($status) {
                'completed', 'processed' => 'Approved',
                default => ucfirst($status),
            };
            $userName = htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')) ?: 'Unknown');
            $source = $r['source'] ?? 'refunds';
            $isPending = ($status === 'pending' && $source === 'credits_refunds');
            $typeLabel = ($source === 'credits_refunds' && !empty($r['transaction_type'])) ? htmlspecialchars(ucfirst($r['transaction_type'])) : '';
        ?>
        <div class="m-refund-card" data-status="<?= $filterGroup ?>">
            <div class="m-refund-top">
                <span class="m-refund-user"><?= $userName ?><?php if ($typeLabel): ?><span class="m-refund-type-label"><?= $typeLabel ?></span><?php endif; ?></span>
                <span class="m-refund-amount">$<?= number_format((float)($r['amount'] ?? 0), 2) ?></span>
            </div>
            <?php if (!empty($r['reason'])): ?>
            <div class="m-refund-reason"><?= htmlspecialchars($r['reason']) ?></div>
            <?php endif; ?>
            <div class="m-refund-bottom">
                <div class="m-refund-date">
                    <?php if (!empty($r['created_at'])): ?>
                    <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($r['created_at'])) ?>
                    <?php endif; ?>
                </div>
                <span class="m-refund-badge m-refund-badge-<?= $badgeClass ?>"><?= $statusLabel ?></span>
            </div>
            <?php if ($isPending): ?>
            <div class="m-refund-actions">
                <button type="button" class="m-btn-approve" onclick="mRefundAction('approve', <?= (int)$r['id'] ?>)"><i class="fas fa-check"></i> Approve</button>
                <button type="button" class="m-btn-reject" onclick="mRefundAction('reject', <?= (int)$r['id'] ?>)"><i class="fas fa-times"></i> Reject</button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="m-empty-state" id="m-refund-empty-filtered" style="display:none;">
            <i class="fas fa-filter"></i>
            <p>No refunds match this filter</p>
        </div>
    <?php endif; ?>
    </div>

    <!-- FAB: Process Refund -->
    <button type="button" class="m-refund-fab" onclick="mRefundOpenSheet('create')" aria-label="Process refund"><i class="fas fa-plus"></i></button>

    <!-- Process Refund Bottom Sheet -->
    <div class="m-refund-overlay" id="m-refund-sheet-create">
        <div class="m-refund-sheet">
            <div class="m-refund-sheet-handle"></div>
            <h2 class="m-refund-sheet-title">Process Refund</h2>
            <form method="POST" action="process_refunds.php" id="m-refund-create-form">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="create">
                <div class="m-refund-form-group">
                    <label class="m-refund-form-label">User ID *</label>
                    <input type="number" name="user_id" class="m-refund-form-input" required min="1" placeholder="Enter user ID">
                </div>
                <div class="m-refund-form-group">
                    <label class="m-refund-form-label">Type *</label>
                    <select name="type" class="m-refund-form-input" required>
                        <option value="refund">Refund</option>
                        <option value="credit">Credit</option>
                    </select>
                </div>
                <div class="m-refund-form-group">
                    <label class="m-refund-form-label">Amount *</label>
                    <input type="number" name="amount" class="m-refund-form-input" required step="0.01" min="0.01" placeholder="0.00">
                </div>
                <div class="m-refund-form-group">
                    <label class="m-refund-form-label">Reason *</label>
                    <textarea name="reason" class="m-refund-form-input" rows="3" required placeholder="Enter reason for refund" style="min-height:66px;resize:vertical;"></textarea>
                </div>
                <button type="submit" class="m-refund-form-submit"><i class="fas fa-check"></i> Process Refund</button>
            </form>
            <button type="button" class="m-refund-form-cancel" onclick="mRefundCloseSheet('create')">Cancel</button>
        </div>
    </div>

    <!-- Toast notification -->
    <div class="m-refund-toast" id="m-refund-toast"></div>
</div>

<script>
/* Status filter tabs */
function mRefundFilter(status, btn) {
    document.querySelectorAll('.m-refund-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    if (btn) btn.classList.add('m-tab-active');
    var cards = document.querySelectorAll('.m-refund-card[data-status]');
    var visible = 0;
    cards.forEach(function(card) {
        if (status === 'all' || card.getAttribute('data-status') === status) {
            card.style.display = '';
            visible++;
        } else {
            card.style.display = 'none';
        }
    });
    var emptyFiltered = document.getElementById('m-refund-empty-filtered');
    var emptyAll = document.getElementById('m-refund-empty-all');
    if (emptyFiltered) emptyFiltered.style.display = (visible === 0 && cards.length > 0) ? '' : 'none';
    if (emptyAll) emptyAll.style.display = (cards.length === 0) ? '' : 'none';
}

/* Bottom sheet open/close */
function mRefundOpenSheet(name) {
    var el = document.getElementById('m-refund-sheet-' + name);
    if (el) el.classList.add('m-overlay-open');
}
function mRefundCloseSheet(name) {
    var el = document.getElementById('m-refund-sheet-' + name);
    if (el) el.classList.remove('m-overlay-open');
}
document.querySelectorAll('.m-refund-overlay').forEach(function(overlay) {
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) overlay.classList.remove('m-overlay-open');
    });
});

/* Toast notification */
function mRefundToast(msg, isError) {
    var toast = document.getElementById('m-refund-toast');
    if (!toast) return;
    toast.textContent = msg;
    toast.className = 'm-refund-toast ' + (isError ? 'm-refund-toast-error' : 'm-refund-toast-success');
    toast.style.display = 'block';
    setTimeout(function() { toast.style.display = 'none'; }, 3000);
}

/* Approve / Reject action */
function mRefundAction(action, id) {
    if (!confirm(action === 'approve' ? 'Approve this refund?' : 'Reject this refund?')) return;
    var csrfToken = document.querySelector('#m-refund-create-form input[name="csrf_token"]');
    var formData = new FormData();
    formData.append('action', action);
    formData.append('id', id);
    if (csrfToken) formData.append('csrf_token', csrfToken.value);
    fetch('process_refunds.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            persistToast(data.message || (action === 'approve' ? 'Refund approved' : 'Refund rejected'), 'success');
            location.reload();
        } else {
            mRefundToast(data.message || 'Action failed', true);
        }
    })
    .catch(function() { mRefundToast('Network error', true); });
}

/* Create form AJAX submit */
(function() {
    var form = document.getElementById('m-refund-create-form');
    if (!form) return;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        var formData = new FormData(form);
        fetch('process_refunds.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                persistToast(data.message || 'Refund processed', 'success');
                mRefundCloseSheet('create');
                form.reset();
                location.reload();
            } else {
                mRefundToast(data.message || 'Failed to process refund', true);
            }
        })
        .catch(function() { mRefundToast('Network error', true); });
    });
})();
</script>
