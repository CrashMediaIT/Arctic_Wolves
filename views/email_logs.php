<?php
/**
 * Email Logs Viewer (Admin Only)
 * View email sending history and troubleshoot delivery issues
 */

require_once __DIR__ . '/../security.php';

// Admin only
if ($user_role !== 'admin') {
    echo '<h2 style="color:#ef4444;">Access Denied</h2>';
    echo '<p>Only administrators can access this page.</p>';
    exit;
}

// Pagination setup
$per_page = 20;
$page = isset($_GET['log_page']) ? (int)$_GET['log_page'] : 1;
$offset = ($page - 1) * $per_page;

// Get total count
$total_logs = $pdo->query("SELECT COUNT(*) FROM email_logs")->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Get email logs
$stmt = $pdo->prepare("
    SELECT id, recipient, subject, status, error_message, sent_at
    FROM email_logs
    ORDER BY sent_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$per_page, $offset]);
$logs = $stmt->fetchAll();
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .stats-bar {
        display: flex;
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        flex: 1;
    }
    .stat-label {
        font-size: 12px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .stat-value {
        font-size: 32px;
        font-weight: 900;
        color: #fff;
    }
    .stat-success { color: #00ff88; }
    .stat-failed { color: #ef4444; }
    .logs-table {
        width: 100%;
        border-collapse: collapse;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    .logs-table th {
        background: #06080b;
        text-align: left;
        padding: 15px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        border-bottom: 2px solid #1e293b;
    }
    .logs-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        font-size: 13px;
        color: #fff;
    }
    .logs-table tr:last-child td {
        border-bottom: none;
    }
    .logs-table tr:hover {
        background: rgba(139, 92, 246, 0.03);
    }
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-success {
        background: rgba(0, 255, 136, 0.1);
        color: #00ff88;
        border: 1px solid #00ff88;
    }
    .status-failed {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    .error-message {
        color: #ef4444;
        font-size: 12px;
        font-family: monospace;
        background: rgba(239, 68, 68, 0.05);
        padding: 5px 8px;
        border-radius: 4px;
        margin-top: 5px;
        max-width: 500px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .pagination {
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-top: 30px;
        align-items: center;
    }
    .page-btn {
        padding: 8px 16px;
        background: #0d1117;
        border: 1px solid #1e293b;
        color: #fff;
        border-radius: 4px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        text-decoration: none;
        font-size: 13px;
    }
    .page-btn:hover {
        border-color: var(--primary);
        background: rgba(139, 92, 246, 0.1);
    }
    .page-btn.active {
        background: var(--primary);
        border-color: var(--primary);
    }
    .page-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }
    .page-info {
        color: #64748b;
        font-size: 13px;
    }
    .no-data {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
    .timestamp {
        color: #64748b;
        font-size: 12px;
    }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-envelope"></i> Email Logs</h1>
</div>

<?php
// Calculate stats
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'SUCCESS' THEN 1 ELSE 0 END) as success,
        SUM(CASE WHEN status = 'FAILED' THEN 1 ELSE 0 END) as failed
    FROM email_logs
")->fetch();
?>

<div class="stats-bar">
    <div class="stat-card">
        <div class="stat-label">Total Emails</div>
        <div class="stat-value"><?= number_format($stats['total']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Successful</div>
        <div class="stat-value stat-success"><?= number_format($stats['success']) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Failed</div>
        <div class="stat-value stat-failed"><?= number_format($stats['failed']) ?></div>
    </div>
</div>

<?php if (count($logs) > 0): ?>
    <table class="logs-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Recipient</th>
                <th>Subject</th>
                <th>Status</th>
                <th>Sent At</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= htmlspecialchars($log['id']) ?></td>
                    <td><?= htmlspecialchars($log['recipient']) ?></td>
                    <td>
                        <?= htmlspecialchars($log['subject']) ?>
                        <?php if ($log['error_message']): ?>
                            <div class="error-message" title="<?= htmlspecialchars($log['error_message']) ?>">
                                <?= htmlspecialchars($log['error_message']) ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="status-badge status-<?= strtolower($log['status']) ?>">
                            <?= htmlspecialchars($log['status']) ?>
                        </span>
                    </td>
                    <td class="timestamp">
                        <?= date('M j, Y g:i A', strtotime($log['sent_at'])) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=email_logs&log_page=<?= $page - 1 ?>" class="page-btn">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php else: ?>
                <button class="page-btn" disabled>
                    <i class="fas fa-chevron-left"></i> Previous
                </button>
            <?php endif; ?>

            <span class="page-info">Page <?= $page ?> of <?= $total_pages ?></span>

            <?php if ($page < $total_pages): ?>
                <a href="?page=email_logs&log_page=<?= $page + 1 ?>" class="page-btn">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php else: ?>
                <button class="page-btn" disabled>
                    Next <i class="fas fa-chevron-right"></i>
                </button>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php else: ?>
    <div class="no-data">
        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; opacity: 0.3;"></i>
        <p>No email logs found.</p>
    </div>
<?php endif; ?>
