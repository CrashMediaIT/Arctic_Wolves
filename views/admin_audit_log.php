<?php
// Fetch audit logs
$auditQuery = "SELECT a.*, u.first_name, u.last_name, u.email
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    ORDER BY a.created_at DESC
    LIMIT 50";
$auditLogs = $pdo->query($auditQuery);
?>
<!-- Admin Audit Log View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-history"></i> Audit Log
    </h1>
    <p class="page-description">Track and review system activity</p>
</div>

<div class="audit-content">
    <!-- Filter Bar -->
    <div class="action-bar">
        <div class="filter-group">
            <input type="text" class="form-input-small" placeholder="Search logs..." data-filter="search">
            <select class="form-input-small" data-filter="action">
                <option>All Actions</option>
                <option>Login/Logout</option>
                <option>User Management</option>
                <option>Data Changes</option>
                <option>Settings</option>
                <option>Security</option>
            </select>
            <select class="form-input-small" data-filter="user">
                <option>All Users</option>
                <!-- Users will be populated -->
            </select>
            <input type="date" class="form-input-small" placeholder="Start Date" data-filter="date-start">
            <input type="date" class="form-input-small" placeholder="End Date" data-filter="date-end">
        </div>
        <button class="btn-secondary" data-action="export"><i class="fas fa-file-export"></i> Export</button>
    </div>

    <!-- Audit Log Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Activity Log</h3>
        </div>
        <div class="card-body">
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Details</th>
                            <th>IP Address</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($auditLogs && $auditLogs->rowCount() > 0): ?>
                            <?php while($log = $auditLogs->fetch()): 
                                $userName = $log['first_name'] ? ($log['first_name'] . ' ' . $log['last_name']) : 'Unknown';
                                $actionType = strtolower(str_replace(' ', '-', $log['action_type']));
                                $statusClass = $log['success'] ? 'success' : 'failed';
                            ?>
                            <tr>
                                <td><?= date('M j, Y g:i A', strtotime($log['timestamp'])) ?></td>
                                <td><?= htmlspecialchars($userName) ?></td>
                                <td><span class="action-badge <?= $actionType ?>"><?= htmlspecialchars($log['action']) ?></span></td>
                                <td><?= htmlspecialchars($log['details']) ?></td>
                                <td><?= htmlspecialchars($log['ip_address']) ?></td>
                                <td><span class="status-badge <?= $statusClass ?>"><?= $log['success'] ? 'Success' : 'Failed' ?></span></td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; padding: 30px;">
                                    <p class="placeholder-text">No audit logs found.</p>
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
.action-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.action-badge.login {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.action-badge.data {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
}

.action-badge.security {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.status-badge.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.failed {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}
</style>
