<?php
/**
 * PWA Accounting Credits - Mobile-native credits & refunds view
 * Purpose-built for mobile phones, not a desktop adaptation.
 * Features: summary cards, search/filter, approve/reject, issue credit/refund modal, FAB
 */

if (!$canAccessAccounting) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Accounting access required</div>';
    return;
}

// Fetch credits from credits_refunds table (matches desktop view & process_refunds.php)
$credits = [];
try {
    $stmt = $pdo->prepare("
        SELECT cr.id, cr.amount, cr.transaction_type, cr.status, cr.created_at, cr.reason,
               cr.reference_number, cr.booking_id,
               u.first_name, u.last_name
        FROM credits_refunds cr
        LEFT JOIN users u ON u.id = cr.user_id
        ORDER BY cr.created_at DESC LIMIT 50
    ");
    $stmt->execute();
    $credits = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $credits = decryptUserRows($credits);
} catch (PDOException $e) { $credits = []; }

// Fetch summary stats
$creditStats = ['total_credits' => 0, 'total_refunds' => 0, 'pending_count' => 0];
try {
    $statsStmt = $pdo->query("SELECT
        COALESCE(SUM(CASE WHEN transaction_type = 'credit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_credits,
        COALESCE(SUM(CASE WHEN transaction_type = 'refund' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_refunds,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
        FROM credits_refunds");
    $creditStats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: $creditStats;
} catch (PDOException $e) { /* use defaults */ }

// Fetch users for the modal - no longer needed as we use typeahead
$modalUsers = [];
?>
<style>
.m-credits { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-credits-header { margin-bottom: 16px; }
.m-credits-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-credits-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }

/* Summary cards */
.m-credit-stats {
    display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-bottom: 14px;
}
.m-credit-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px 10px; text-align: center;
}
.m-credit-stat-icon { font-size: 16px; margin-bottom: 4px; }
.m-credit-stat-icon.credits { color: #3B82F6; }
.m-credit-stat-icon.refunds { color: #F59E0B; }
.m-credit-stat-icon.pending { color: #EF4444; }
.m-credit-stat-val { font-size: 16px; font-weight: 800; color: #fff; }
.m-credit-stat-lbl { font-size: 10px; color: #6B6B7B; text-transform: uppercase; font-weight: 600; letter-spacing: 0.3px; }

/* Search & filter bar */
.m-credit-filters { margin-bottom: 12px; display: flex; flex-direction: column; gap: 8px; }
.m-credit-search {
    width: 100%; padding: 10px 12px 10px 36px; border-radius: 10px;
    background: #16161F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif; min-height: 44px; box-sizing: border-box;
}
.m-credit-search:focus { outline: none; border-color: #8B5CF6; }
.m-credit-search-wrap { position: relative; }
.m-credit-search-wrap i {
    position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
    color: #6B6B7B; font-size: 13px; pointer-events: none;
}
.m-credit-filter-row { display: flex; gap: 6px; }
.m-filter-chip {
    flex: 1; padding: 8px 0; text-align: center; border-radius: 8px;
    background: #16161F; border: 1px solid #2D2D3F; color: #A8A8B8;
    font-size: 12px; font-weight: 600; font-family: Inter, sans-serif;
    cursor: pointer; min-height: 36px; box-sizing: border-box;
}
.m-filter-chip.active { background: rgba(139,92,246,0.2); color: #8B5CF6; border-color: #8B5CF6; }

/* Credit cards */
.m-credit-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-credit-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(139,92,246,0.15); color: #8B5CF6;
}
.m-credit-body { flex: 1; min-width: 0; }
.m-credit-user { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-credit-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-credit-right { text-align: right; flex-shrink: 0; }
.m-credit-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-credit-meta { display: flex; gap: 6px; margin-top: 4px; flex-wrap: wrap; justify-content: flex-end; }
.m-credit-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-credit-type-credit { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-credit-type-refund { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-credit-type-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-credit-status-active, .m-credit-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-credit-status-used { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-credit-status-expired, .m-credit-status-rejected { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-credit-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-credit-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-credit-date { font-size: 11px; color: #6B6B7B; margin-top: 4px; }

/* Approve / Reject action buttons */
.m-credit-actions { display: flex; gap: 6px; margin-top: 6px; justify-content: flex-end; }
.m-credit-actions button {
    padding: 4px 10px; border-radius: 6px; font-size: 11px; font-weight: 600;
    border: none; cursor: pointer; min-height: 28px; font-family: Inter, sans-serif;
}
.m-btn-approve { background: rgba(16,185,129,0.15); color: #10B981; }
.m-btn-reject { background: rgba(239,68,68,0.15); color: #EF4444; }

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

/* Toast notification */
.m-toast {
    position: fixed; top: 20px; left: 16px; right: 16px; z-index: 300;
    padding: 14px 16px; border-radius: 10px; font-size: 13px; font-weight: 600;
    font-family: Inter, sans-serif; display: flex; align-items: center; gap: 8px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.4);
}
.m-toast.success { background: rgba(16,185,129,0.95); color: #fff; }
.m-toast.error { background: rgba(239,68,68,0.95); color: #fff; }
</style>

<div class="m-credits">
    <div class="m-credits-header">
        <h2 class="m-credits-title">Credits &amp; Refunds</h2>
        <p class="m-credits-sub"><?= count($credits) ?> record<?= count($credits) !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Status Summary Cards -->
    <div class="m-credit-stats">
        <div class="m-credit-stat">
            <div class="m-credit-stat-icon credits"><i class="fas fa-plus-circle"></i></div>
            <div class="m-credit-stat-val">$<?= number_format((float)$creditStats['total_credits'], 2) ?></div>
            <div class="m-credit-stat-lbl">Credits</div>
        </div>
        <div class="m-credit-stat">
            <div class="m-credit-stat-icon refunds"><i class="fas fa-undo"></i></div>
            <div class="m-credit-stat-val">$<?= number_format((float)$creditStats['total_refunds'], 2) ?></div>
            <div class="m-credit-stat-lbl">Refunds</div>
        </div>
        <div class="m-credit-stat">
            <div class="m-credit-stat-icon pending"><i class="fas fa-clock"></i></div>
            <div class="m-credit-stat-val"><?= (int)$creditStats['pending_count'] ?></div>
            <div class="m-credit-stat-lbl">Pending</div>
        </div>
    </div>

    <!-- Search & Type Filter -->
    <div class="m-credit-filters">
        <div class="m-credit-search-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="m-credit-search" id="mCreditSearch" placeholder="Search client name...">
        </div>
        <div class="m-credit-filter-row">
            <button class="m-filter-chip active" data-type-filter="all">All</button>
            <button class="m-filter-chip" data-type-filter="credit">Credits</button>
            <button class="m-filter-chip" data-type-filter="refund">Refunds</button>
        </div>
    </div>

    <!-- Transaction List -->
    <div id="mCreditList">
    <?php if (empty($credits)): ?>
        <div class="m-empty-state">
            <i class="fas fa-hand-holding-dollar"></i>
            No credits or refunds found
        </div>
    <?php else: ?>
        <?php foreach ($credits as $c):
            $type = strtolower($c['transaction_type'] ?? 'default');
            $typeClass = match($type) {
                'credit' => 'credit',
                'refund' => 'refund',
                default => 'default',
            };
            $status = strtolower($c['status'] ?? 'default');
            $statusClass = match($status) {
                'active', 'completed' => 'completed',
                'used', 'redeemed' => 'used',
                'expired' => 'expired',
                'pending' => 'pending',
                'rejected' => 'rejected',
                default => 'default',
            };
            $userName = htmlspecialchars(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? '')) ?: 'Unknown User');
        ?>
        <div class="m-credit-card" data-type="<?= $typeClass ?>" data-user="<?= $userName ?>">
            <div class="m-credit-icon">
                <i class="fas <?= $type === 'refund' ? 'fa-rotate-left' : 'fa-coins' ?>"></i>
            </div>
            <div class="m-credit-body">
                <div class="m-credit-user"><?= $userName ?></div>
                <div class="m-credit-desc"><?= htmlspecialchars(($c['reason'] ?: ($c['reference_number'] ?? 'No description'))) ?></div>
                <div class="m-credit-date"><i class="fas fa-calendar" style="font-size:10px;"></i> <?= date('M j, Y', strtotime($c['created_at'])) ?></div>
            </div>
            <div class="m-credit-right">
                <div class="m-credit-amount">$<?= number_format((float)$c['amount'], 2) ?></div>
                <div class="m-credit-meta">
                    <span class="m-credit-badge m-credit-type-<?= $typeClass ?>"><?= htmlspecialchars(ucfirst($type)) ?></span>
                    <span class="m-credit-badge m-credit-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <?php if ($status === 'pending'): ?>
                <div class="m-credit-actions">
                    <button class="m-btn-approve" data-credit-action="approve" data-credit-id="<?= (int)$c['id'] ?>" aria-label="Approve credit/refund"><i class="fas fa-check"></i> Approve</button>
                    <button class="m-btn-reject" data-credit-action="reject" data-credit-id="<?= (int)$c['id'] ?>" aria-label="Reject credit/refund"><i class="fas fa-times"></i> Reject</button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
    </div>
</div>

<!-- FAB: Issue Credit/Refund -->
<button class="m-fab" id="mCreditFab" aria-label="Issue Credit or Refund"><i class="fas fa-plus"></i></button>

<!-- Bottom-Sheet Modal: Issue Credit/Refund -->
<div class="m-modal-overlay" id="mCreditModal">
    <div class="m-modal-sheet">
        <div class="m-modal-handle"></div>
        <div class="m-modal-title">Issue Credit / Refund</div>
        <form id="mCreditForm" method="POST" action="process_refunds.php">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?? '' ?>">
            <input type="hidden" name="action" value="create">

            <div class="m-modal-field">
                <label for="mCreditUser">Client *</label>
                <input type="hidden" name="user_id" id="mCreditUserId">
                <div style="position:relative;">
                    <input type="text" id="mCreditUser" placeholder="Search by name or email..." autocomplete="off" required style="width:100%;padding:10px 12px;border-radius:10px;background:#16161F;border:1px solid #2D2D3F;color:#fff;font-size:14px;font-family:Inter,sans-serif;min-height:44px;box-sizing:border-box;">
                    <div id="mCreditUserResults" style="display:none;position:absolute;top:100%;left:0;right:0;background:#16161F;border:1px solid #2D2D3F;border-top:none;border-radius:0 0 10px 10px;max-height:180px;overflow-y:auto;z-index:1000;"></div>
                </div>
            </div>

            <div class="m-modal-field">
                <label for="mCreditType">Type *</label>
                <select name="type" id="mCreditType" required>
                    <option value="">Select Type</option>
                    <option value="credit">Credit</option>
                    <option value="refund">Refund</option>
                </select>
            </div>

            <div class="m-modal-field">
                <label for="mCreditAmount">Amount *</label>
                <input type="number" name="amount" id="mCreditAmount" step="0.01" min="0.01" placeholder="0.00" required>
            </div>

            <div class="m-modal-field">
                <label for="mCreditReason">Reason *</label>
                <textarea name="reason" id="mCreditReason" rows="3" placeholder="Reason for credit/refund" required></textarea>
            </div>

            <div class="m-modal-field">
                <label for="mCreditBooking">Booking ID (optional)</label>
                <input type="text" name="booking_id" id="mCreditBooking" placeholder="Related booking ID">
            </div>

            <div class="m-modal-actions">
                <button type="button" class="m-modal-btn-cancel" id="mCreditCancel">Cancel</button>
                <button type="submit" class="m-modal-btn-save"><i class="fas fa-paper-plane"></i> Submit</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var csrfToken = <?= json_encode($csrf_token ?? '') ?>;

    // Toast helper
    function showToast(msg, type) {
        var old = document.querySelector('.m-toast');
        if (old) old.remove();
        var el = document.createElement('div');
        el.className = 'm-toast ' + (type || 'success');
        var icon = document.createElement('i');
        icon.className = 'fas fa-' + (type === 'error' ? 'exclamation-circle' : 'check-circle');
        el.appendChild(icon);
        var span = document.createElement('span');
        span.textContent = msg;
        el.appendChild(span);
        document.body.appendChild(el);
        setTimeout(function() { if (el.parentElement) el.remove(); }, 3500);
    }

    // Modal open/close
    var modal = document.getElementById('mCreditModal');
    var fab = document.getElementById('mCreditFab');
    var cancelBtn = document.getElementById('mCreditCancel');

    if (fab) fab.addEventListener('click', function() { modal.classList.add('m-show'); });
    if (cancelBtn) cancelBtn.addEventListener('click', function() { closeModal(); });
    if (modal) modal.addEventListener('click', function(e) { if (e.target === modal) closeModal(); });

    function closeModal() {
        modal.classList.remove('m-show');
        var form = document.getElementById('mCreditForm');
        if (form) form.reset();
        // Clear typeahead state
        var hiddenInput = document.getElementById('mCreditUserId');
        if (hiddenInput) hiddenInput.value = '';
        var resultsDiv = document.getElementById('mCreditUserResults');
        if (resultsDiv) resultsDiv.style.display = 'none';
    }

    // Client typeahead search
    var mSearchInput = document.getElementById('mCreditUser');
    var mHiddenInput = document.getElementById('mCreditUserId');
    var mResultsDiv = document.getElementById('mCreditUserResults');
    var mSearchTimeout = null;
    
    if (mSearchInput) {
        mSearchInput.addEventListener('input', function() {
            var query = this.value.trim();
            mHiddenInput.value = '';
            
            if (mSearchTimeout) clearTimeout(mSearchTimeout);
            
            if (query.length < 1) {
                mResultsDiv.style.display = 'none';
                return;
            }
            
            mSearchTimeout = setTimeout(function() {
                fetch('ajax_search_users.php?q=' + encodeURIComponent(query) + '&limit=15')
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        if (!data.success || !data.results || data.results.length === 0) {
                            mResultsDiv.innerHTML = '<div style="padding:10px 12px;color:#6B6B7B;font-size:13px;">No users found</div>';
                            mResultsDiv.style.display = 'block';
                            return;
                        }
                        mResultsDiv.innerHTML = '';
                        data.results.forEach(function(user) {
                            var item = document.createElement('div');
                            item.style.cssText = 'padding:10px 12px;cursor:pointer;border-bottom:1px solid #2D2D3F;font-size:13px;';
                            item.onmouseenter = function() { this.style.background = '#2D2D3F'; };
                            item.onmouseleave = function() { this.style.background = 'transparent'; };
                            
                            var nameSpan = document.createElement('strong');
                            nameSpan.style.color = '#fff';
                            nameSpan.textContent = user.name;
                            item.appendChild(nameSpan);
                            
                            var emailSpan = document.createElement('span');
                            emailSpan.style.cssText = 'color:#6B6B7B;margin-left:6px;font-size:12px;';
                            emailSpan.textContent = user.email;
                            item.appendChild(emailSpan);
                            
                            if (user.role) {
                                var roleSpan = document.createElement('span');
                                roleSpan.style.cssText = 'color:#8B5CF6;margin-left:6px;font-size:10px;background:rgba(139,92,246,0.15);padding:2px 6px;border-radius:4px;';
                                roleSpan.textContent = user.role;
                                item.appendChild(roleSpan);
                            }
                            
                            item.onclick = function() {
                                mHiddenInput.value = user.id;
                                mSearchInput.value = user.name + ' (' + user.email + ')';
                                mResultsDiv.style.display = 'none';
                            };
                            mResultsDiv.appendChild(item);
                        });
                        mResultsDiv.style.display = 'block';
                    })
                    .catch(function() {
                        mResultsDiv.innerHTML = '<div style="padding:10px 12px;color:#EF4444;font-size:13px;">Search failed</div>';
                        mResultsDiv.style.display = 'block';
                    });
            }, 300);
        });
        
        document.addEventListener('click', function(e) {
            if (!mSearchInput.contains(e.target) && !mResultsDiv.contains(e.target)) {
                mResultsDiv.style.display = 'none';
            }
        });
        
        mSearchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !mHiddenInput.value) {
                e.preventDefault();
            }
        });
    }

    // Form submit via AJAX
    var form = document.getElementById('mCreditForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var submitBtn = form.querySelector('button[type="submit"]');
            var origHTML = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            submitBtn.disabled = true;

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                submitBtn.innerHTML = origHTML;
                submitBtn.disabled = false;
                if (data.success) {
                    persistToast(data.message || 'Submitted successfully!', 'success');
                    closeModal();
                    location.reload();
                } else {
                    showToast(data.message || 'Failed to process', 'error');
                }
            })
            .catch(function() {
                submitBtn.innerHTML = origHTML;
                submitBtn.disabled = false;
                showToast('An error occurred', 'error');
            });
        });
    }

    // Approve / Reject actions
    document.querySelectorAll('[data-credit-action]').forEach(function(btn) {
        btn.addEventListener('click', async function() {
            var action = this.getAttribute('data-credit-action');
            var id = this.getAttribute('data-credit-id');
            var label = action === 'approve' ? 'Approve' : 'Reject';
            if (!await showConfirmModal(label + ' this credit/refund?')) return;

            var self = this;
            self.disabled = true;

            fetch('process_refunds.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=' + encodeURIComponent(action) + '&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    persistToast(data.message || (label + 'd!'), 'success');
                    location.reload();
                } else {
                    self.disabled = false;
                    showToast(data.message || 'Operation failed', 'error');
                }
            })
            .catch(function() {
                self.disabled = false;
                showToast('An error occurred', 'error');
            });
        });
    });

    // Search filter
    var searchInput = document.getElementById('mCreditSearch');
    if (searchInput) {
        searchInput.addEventListener('input', applyFilters);
    }

    // Type filter chips
    document.querySelectorAll('[data-type-filter]').forEach(function(chip) {
        chip.addEventListener('click', function() {
            document.querySelectorAll('[data-type-filter]').forEach(function(c) { c.classList.remove('active'); });
            this.classList.add('active');
            applyFilters();
        });
    });

    function applyFilters() {
        var search = (searchInput ? searchInput.value : '').toLowerCase();
        var typeFilter = (document.querySelector('[data-type-filter].active') || {}).getAttribute('data-type-filter') || 'all';
        document.querySelectorAll('.m-credit-card').forEach(function(card) {
            var user = (card.getAttribute('data-user') || '').toLowerCase();
            var type = card.getAttribute('data-type') || '';
            var matchSearch = !search || user.indexOf(search) !== -1;
            var matchType = typeFilter === 'all' || type === typeFilter;
            card.style.display = (matchSearch && matchType) ? '' : 'none';
        });
    }
})();
</script>
