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
        <button class="btn-primary" data-action="create"><i class="fas fa-plus"></i> Issue Credit/Refund</button>
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
