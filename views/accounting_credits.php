<?php
// Fetch credits and refunds
$creditsQuery = "SELECT cr.*, u.first_name, u.last_name
    FROM credits_refunds cr
    LEFT JOIN users u ON cr.user_id = u.id
    ORDER BY cr.created_at DESC
    LIMIT 20";
$credits = $pdo->query($creditsQuery);
?>
<!-- Accounting Credits View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-undo-alt"></i> Credits & Refunds
    </h1>
    <p class="page-description">Manage client credits and process refunds</p>
</div>

<div class="credits-content">
    <!-- Action Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search..." data-filter="search">
            <select class="form-input-small" data-filter="type">
                <option>All Types</option>
                <option>Credits</option>
                <option>Refunds</option>
            </select>
            <select class="form-input-small" data-filter="status">
                <option>All Status</option>
                <option>Pending</option>
                <option>Approved</option>
                <option>Completed</option>
            </select>
        </div>
        <button class="btn-primary" data-action="add" data-modal="issue-credit-refund-modal"><i class="fas fa-plus"></i> Issue Credit/Refund</button>
    </div>

    <!-- Credits & Refunds Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Credits & Refunds</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
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
                                <td><strong>#<?= htmlspecialchars($credit['reference_number']) ?></strong></td>
                                <td><?= htmlspecialchars($credit['first_name'] . ' ' . $credit['last_name']) ?></td>
                                <td><span class="type-badge <?= $typeClass ?>"><?= ucfirst($credit['type']) ?></span></td>
                                <td><strong>$<?= number_format($credit['amount'], 2) ?></strong></td>
                                <td><?= htmlspecialchars($credit['reason']) ?></td>
                                <td><?= date('M j, Y', strtotime($credit['created_at'])) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= ucfirst($credit['status']) ?></span></td>
                                <td>
                                    <div class="table-actions">
                                        <?php if($credit['status'] === 'pending'): ?>
                                            <button class="btn-icon" title="Approve" data-action="approve"><i class="fas fa-check"></i></button>
                                            <button class="btn-icon" title="Reject" data-action="reject"><i class="fas fa-times"></i></button>
                                        <?php else: ?>
                                            <button class="btn-icon" title="View"><i class="fas fa-eye"></i></button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; padding: 30px;">
                                    <p class="placeholder-text">No credits or refunds found.</p>
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
.type-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.type-badge.credit {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.type-badge.refund {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}
</style>

<!-- Issue Credit/Refund Modal -->
<div id="issue-credit-refund-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Issue Credit/Refund</h2>
            <button class="modal-close" onclick="closeModal('issue-credit-refund-modal')">&times;</button>
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
                <button type="button" class="btn-secondary" onclick="closeModal('issue-credit-refund-modal')">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-save"></i> Issue Credit/Refund</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
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
                            let html = '<div style="display: flex; flex-direction: column; gap: 8px;">';
                            data.purchases.forEach(purchase => {
                                html += `<div style="padding: 8px; background: var(--bg-card); border-radius: 4px; border: 1px solid var(--border); font-size: 13px;">
                                    <strong>${purchase.description}</strong> - $${purchase.amount}
                                    <span style="color: var(--text-dim); margin-left: 8px;">${purchase.date}</span>
                                </div>`;
                            });
                            html += '</div>';
                            purchaseList.innerHTML = html;
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
</script>
