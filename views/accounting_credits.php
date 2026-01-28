<?php
// Fetch credits and refunds
$creditsQuery = "SELECT cr.*, u.first_name, u.last_name
    FROM credits_refunds cr
    LEFT JOIN users u ON cr.user_id = u.id
    ORDER BY cr.created_at DESC
    LIMIT 20";
$credits = $pdo->query($creditsQuery);

// Fetch credit stats
$creditStatsQuery = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type = 'credit' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_credits,
    COALESCE(SUM(CASE WHEN transaction_type = 'refund' AND status = 'completed' THEN amount ELSE 0 END), 0) as total_refunds,
    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
    COUNT(*) as total_count
    FROM credits_refunds";
try {
    $statsResult = $pdo->query($creditStatsQuery);
    $creditStats = $statsResult->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $creditStats = ['total_credits' => 0, 'total_refunds' => 0, 'pending_count' => 0, 'total_count' => 0];
}
?>
<!-- Accounting Credits View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-undo-alt"></i> Credits & Refunds
    </h1>
    <p class="page-description">Manage client credits and process refund requests</p>
</div>

<div class="credits-content">
    <!-- Credit Stats -->
    <div class="credit-stats">
        <div class="credit-stat-card credits">
            <div class="stat-icon"><i class="fas fa-plus-circle"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($creditStats['total_credits'], 2) ?></span>
                <span class="stat-label">Total Credits Issued</span>
            </div>
        </div>
        <div class="credit-stat-card refunds">
            <div class="stat-icon"><i class="fas fa-undo"></i></div>
            <div class="stat-info">
                <span class="stat-value">$<?= number_format($creditStats['total_refunds'], 2) ?></span>
                <span class="stat-label">Total Refunds</span>
            </div>
        </div>
        <div class="credit-stat-card pending">
            <div class="stat-icon"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $creditStats['pending_count'] ?></span>
                <span class="stat-label">Pending Requests</span>
            </div>
        </div>
        <div class="credit-stat-card total">
            <div class="stat-icon"><i class="fas fa-list-ol"></i></div>
            <div class="stat-info">
                <span class="stat-value"><?= $creditStats['total_count'] ?></span>
                <span class="stat-label">Total Transactions</span>
            </div>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" class="form-input-small" placeholder="Search client..." data-filter="search">
            </div>
            <select class="form-input-small" data-filter="type">
                <option value="">All Types</option>
                <option value="credit">Credits</option>
                <option value="refund">Refunds</option>
            </select>
            <select class="form-input-small" data-filter="status">
                <option value="">All Status</option>
                <option value="pending">Pending</option>
                <option value="approved">Approved</option>
                <option value="completed">Completed</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
        <button class="btn-primary" data-action="add" data-modal="issue-credit-refund-modal"><i class="fas fa-plus"></i> Issue Credit/Refund</button>
    </div>

    <!-- Credits & Refunds Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Transaction History</h3>
            <button class="btn-secondary btn-small" data-action="export" data-type="credits"><i class="fas fa-file-export"></i> Export</button>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Reason</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($credits && $credits->rowCount() > 0): ?>
                            <?php while($credit = $credits->fetch()): 
                                $typeClass = strtolower($credit['type']);
                                $statusClass = strtolower($credit['status']);
                            ?>
                            <tr>
                                <td><strong class="ref-number">#<?= htmlspecialchars($credit['reference_number'] ?? $credit['id']) ?></strong></td>
                                <td>
                                    <div class="client-info">
                                        <span class="client-name"><?= htmlspecialchars($credit['first_name'] . ' ' . $credit['last_name']) ?></span>
                                    </div>
                                </td>
                                <td><span class="type-badge <?= $typeClass ?>"><?= ucfirst($credit['transaction_type'] ?? $credit['type'] ?? 'credit') ?></span></td>
                                <td><strong class="amount">$<?= number_format($credit['amount'], 2) ?></strong></td>
                                <td class="reason-cell"><?= htmlspecialchars($credit['reason'] ?? '') ?></td>
                                <td><?= date('M j, Y', strtotime($credit['created_at'])) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($credit['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <?php if($credit['status'] === 'pending'): ?>
                                            <button class="btn-icon btn-approve" title="Approve" data-action="approve" data-id="<?= $credit['id'] ?>"><i class="fas fa-check"></i></button>
                                            <button class="btn-icon btn-reject" title="Reject" data-action="reject" data-id="<?= $credit['id'] ?>"><i class="fas fa-times"></i></button>
                                        <?php else: ?>
                                            <button class="btn-icon" title="View Details" data-action="view" data-id="<?= $credit['id'] ?>"><i class="fas fa-eye"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="empty-state">
                                    <div class="empty-state-content">
                                        <i class="fas fa-undo-alt"></i>
                                        <p>No credits or refunds found</p>
                                        <span>Start by issuing a credit or refund to a client</span>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
/* Credit Stats */
.credit-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.credit-stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 22px;
    display: flex;
    align-items: center;
    gap: 18px;
    transition: all 0.3s ease;
}

.credit-stat-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
}

.credit-stat-card .stat-icon {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.credit-stat-card.credits .stat-icon { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.credit-stat-card.refunds .stat-icon { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.credit-stat-card.pending .stat-icon { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.credit-stat-card.total .stat-icon { background: rgba(107, 70, 193, 0.15); color: #8B5CF6; }

.credit-stat-card .stat-info { flex: 1; }

.credit-stat-card .stat-value {
    font-size: 26px;
    font-weight: 900;
    color: var(--text-white);
    display: block;
    margin-bottom: 4px;
}

.credit-stat-card .stat-label {
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
    min-width: 200px;
}

/* Table styling */
.ref-number {
    color: var(--primary);
    font-family: 'Consolas', monospace;
}

.client-name {
    font-weight: 600;
}

.amount {
    color: var(--text-white);
    font-size: 15px;
}

.reason-cell {
    max-width: 200px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.type-badge {
    display: inline-flex;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.type-badge.credit {
    background: rgba(59, 130, 246, 0.15);
    color: #3b82f6;
}

.type-badge.refund {
    background: rgba(245, 158, 11, 0.15);
    color: #f59e0b;
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

.status-badge.pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-badge.approved { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.status-badge.completed { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-badge.rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; }

.btn-approve { color: #10b981 !important; }
.btn-approve:hover { background: rgba(16, 185, 129, 0.15) !important; }
.btn-reject { color: #ef4444 !important; }
.btn-reject:hover { background: rgba(239, 68, 68, 0.15) !important; }

.empty-state {
    padding: 60px 20px !important;
}

.empty-state-content {
    text-align: center;
}

.empty-state-content i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 16px;
}

.empty-state-content p {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-white);
    margin-bottom: 8px;
}

.empty-state-content span {
    font-size: 13px;
    color: var(--text-dim);
}

@media (max-width: 768px) {
    .credit-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .search-box input {
        min-width: auto;
    }
}

@media (max-width: 480px) {
    .credit-stats {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Issue Credit/Refund Modal -->
<div id="issue-credit-refund-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Issue Credit/Refund</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('issue-credit-refund-modal')">&times;</button>
        </div>
        <form method="POST" action="process_refunds.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Client *</label>
                    <select name="user_id" class="form-input" required id="credit-user-select">
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
                
                <div id="purchase-history" style="display: none; margin-bottom: 20px;">
                    <label class="form-label">Recent Purchases</label>
                    <div id="purchase-list" style="background: var(--bg-main); padding: 12px; border-radius: 8px; border: 1px solid var(--border);">
                        <p style="color: var(--text-dim); font-size: 13px;">Select a client to view their purchase history</p>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="type" class="form-input" required>
                        <option value="">Select Type</option>
                        <option value="credit">Credit</option>
                        <option value="refund">Refund</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Amount *</label>
                    <input type="number" name="amount" class="form-input" step="0.01" min="0.01" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reason *</label>
                    <textarea name="reason" class="form-textarea" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Reference/Booking ID</label>
                    <input type="text" name="booking_id" class="form-input" placeholder="Optional - related booking ID">
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" name="auto_approve" value="1"> Auto-approve immediately
                    </label>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('issue-credit-refund-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Issue Credit/Refund</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var csrfToken = document.querySelector('[name="csrf_token"]')?.value || '';
    
    // Show notification helper
    function showNotification(message, type) {
        var existing = document.querySelector('.notification-widget');
        if (existing) existing.remove();
        
        var div = document.createElement('div');
        div.className = 'notification-widget';
        div.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; padding: 16px 24px; border-radius: 8px; display: flex; align-items: center; gap: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);';
        if (type === 'success') {
            div.style.background = 'rgba(16, 185, 129, 0.95)';
            div.style.color = '#fff';
        } else {
            div.style.background = 'rgba(239, 68, 68, 0.95)';
            div.style.color = '#fff';
        }
        var safeMsg = document.createElement('span');
        safeMsg.textContent = message;
        div.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ';
        div.appendChild(safeMsg);
        var closeBtn = document.createElement('button');
        closeBtn.innerHTML = '&times;';
        closeBtn.style.cssText = 'margin-left: 16px; background: none; border: none; color: inherit; cursor: pointer; font-size: 18px;';
        closeBtn.onclick = function() { div.remove(); };
        div.appendChild(closeBtn);
        document.body.appendChild(div);
        setTimeout(function() { if (div.parentElement) div.remove(); }, 5000);
    }
    
    // Handle modal open buttons
    document.querySelectorAll('[data-action="add"][data-modal]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var modalId = this.getAttribute('data-modal');
            var modal = document.getElementById(modalId);
            if (modal) modal.classList.add('active');
        });
    });
    
    // Handle approve/reject actions
    document.querySelectorAll('[data-action="approve"], [data-action="reject"]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var action = this.getAttribute('data-action');
            var id = this.getAttribute('data-id');
            var confirmMsg = action === 'approve' ? 'Approve this credit/refund?' : 'Reject this credit/refund?';
            
            if (!confirm(confirmMsg)) return;
            
            fetch('process_refunds.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=' + action + '&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    showNotification(data.message || (action === 'approve' ? 'Approved!' : 'Rejected!'), 'success');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotification('Error: ' + (data.message || 'Operation failed'), 'error');
                }
            })
            .catch(function() { showNotification('An error occurred', 'error'); });
        });
    });
    
    // Handle form submission via AJAX
    var creditForm = document.querySelector('#issue-credit-refund-modal form');
    if (creditForm) {
        creditForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var form = this;
            var formData = new FormData(form);
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            submitBtn.disabled = true;
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                
                if (data.success) {
                    showNotification(data.message || 'Credit/Refund issued successfully!', 'success');
                    closeModal('issue-credit-refund-modal');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    showNotification('Error: ' + (data.message || 'Failed to process'), 'error');
                }
            })
            .catch(function() {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
                showNotification('An error occurred', 'error');
            });
        });
    }
    
    // User purchase history loading
    const userSelect = document.getElementById('credit-user-select');
    const purchaseHistory = document.getElementById('purchase-history');
    const purchaseList = document.getElementById('purchase-list');
    
    if (userSelect) {
        userSelect.addEventListener('change', function() {
            if (this.value) {
                purchaseHistory.style.display = 'block';
                purchaseList.innerHTML = '<p style="color: var(--text-dim); font-size: 13px;"><i class="fas fa-spinner fa-spin"></i> Loading purchase history...</p>';
                
                // Fetch purchase history via AJAX
                fetch('process_refunds.php?action=get_purchases&user_id=' + this.value)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.purchases && data.purchases.length > 0) {
                            // Clear and build content safely
                            purchaseList.innerHTML = '';
                            var container = document.createElement('div');
                            container.style.cssText = 'display: flex; flex-direction: column; gap: 8px;';
                            
                            data.purchases.forEach(function(purchase) {
                                var item = document.createElement('div');
                                item.style.cssText = 'padding: 8px; background: var(--bg-card); border-radius: 4px; border: 1px solid var(--border); font-size: 13px;';
                                
                                var desc = document.createElement('strong');
                                desc.textContent = purchase.description;
                                item.appendChild(desc);
                                
                                var price = document.createTextNode(' - $' + purchase.amount);
                                item.appendChild(price);
                                
                                var dateSpan = document.createElement('span');
                                dateSpan.style.cssText = 'color: var(--text-dim); margin-left: 8px;';
                                dateSpan.textContent = purchase.date;
                                item.appendChild(dateSpan);
                                
                                container.appendChild(item);
                            });
                            purchaseList.appendChild(container);
                        } else {
                            purchaseList.innerHTML = '<p style="color: var(--text-dim); font-size: 13px;">No recent purchases found</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching purchases:', error);
                        purchaseList.innerHTML = '<p style="color: var(--error); font-size: 13px;">Error loading purchase history</p>';
                    });
            } else {
                purchaseHistory.style.display = 'none';
            }
        });
    }
});

function closeModal(modalId) {
    var modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('active');
        var form = modal.querySelector('form');
        if (form) form.reset();
    }
}
</script>
