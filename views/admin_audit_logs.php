<?php
/**
 * Admin Audit Logs - View and Restore System
 * Comprehensive audit trail with restore points
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Pagination
$page_num = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$per_page = 50;
$offset = ($page_num - 1) * $per_page;

// Filters
$filter_table = $_GET['table'] ?? '';
$filter_action = $_GET['action'] ?? '';
$filter_user = $_GET['user'] ?? '';

// Build query
$where = [];
$params = [];

if ($filter_table) {
    $where[] = "table_name = ?";
    $params[] = $filter_table;
}

if ($filter_action) {
    $where[] = "action_type = ?";
    $params[] = $filter_action;
}

if ($filter_user) {
    $where[] = "al.user_id = ?";
    $params[] = $filter_user;
}

$where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Get audit logs
$logs_query = $pdo->prepare("
    SELECT 
        al.*,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        u.role as user_role
    FROM audit_logs al
    LEFT JOIN users u ON al.user_id = u.id
    $where_clause
    ORDER BY al.created_at DESC
    LIMIT ? OFFSET ?
");
$params[] = $per_page;
$params[] = $offset;
$logs_query->execute($params);
$logs = $logs_query->fetchAll(PDO::FETCH_ASSOC);
$logs = decryptUserRows($logs);

// Get total count
$count_query = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al $where_clause");
$count_query->execute(array_slice($params, 0, -2));
$total_logs = $count_query->fetchColumn();
$total_pages = ceil($total_logs / $per_page);

// Get unique tables for filter
$tables_query = $pdo->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name");
$tables = $tables_query->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users_query = $pdo->query("
    SELECT DISTINCT u.id, CONCAT(u.first_name, ' ', u.last_name) as name
    FROM users u
    INNER JOIN audit_logs al ON al.user_id = u.id
    ORDER BY name
");
$users = $users_query->fetchAll(PDO::FETCH_ASSOC);
$users = decryptUserRows($users);

// Count by action type
$insert_count = 0;
$update_count = 0;
$delete_count = 0;
// Estimate from current page (for display purposes)
foreach ($logs as $log) {
    switch ($log['action_type']) {
        case 'INSERT': $insert_count++; break;
        case 'UPDATE': $update_count++; break;
        case 'DELETE': $delete_count++; break;
    }
}

$csrf_token = generateCsrfToken();
?>

<style>
    /* Filters Card */
    .filters-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 14px;
        padding: 20px 24px;
        margin-bottom: 24px;
    }
    
    .filters-form {
        display: flex;
        gap: 16px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .filter-group {
        flex: 1;
        min-width: 180px;
    }
    
    .filter-group label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: var(--text-dim);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .filter-select {
        width: 100%;
        padding: 12px 16px;
        background: var(--bg-main);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--text-white);
        font-size: 13px;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .filter-select:hover,
    .filter-select:focus {
        border-color: var(--primary);
        outline: none;
    }
    
    .filter-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-filter {
        padding: 12px 24px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-filter:hover {
        background: var(--primary-hover);
        transform: translateY(-2px);
    }
    
    .btn-reset {
        padding: 12px 24px;
        background: transparent;
        color: var(--text-dim);
        border: 1px solid var(--border);
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
        transition: all 0.3s;
    }
    
    .btn-reset:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .logs-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 14px;
        overflow: hidden;
    }
    
    .logs-card .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 20px 24px;
        background: linear-gradient(180deg, rgba(112, 0, 164, 0.08) 0%, transparent 100%);
        border-bottom: 1px solid #1e293b;
    }
    
    .logs-card .card-header h3 {
        font-size: 18px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .logs-card .card-header h3 i {
        color: var(--primary);
    }
    
    .logs-count {
        padding: 6px 14px;
        background: rgba(168, 85, 247, 0.15);
        color: #a855f7;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .logs-table-container {
        overflow-x: auto;
    }
    
    .logs-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }
    
    .logs-table thead th {
        padding: 14px 18px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        background: rgba(112, 0, 164, 0.05);
        border-bottom: 2px solid #1e293b;
        white-space: nowrap;
    }
    
    .logs-table tbody td {
        padding: 16px 18px;
        border-bottom: 1px solid #1e293b;
        font-size: 13px;
        vertical-align: middle;
    }
    
    .logs-table tbody tr {
        transition: all 0.2s;
    }
    
    .logs-table tbody tr:hover {
        background: rgba(112, 0, 164, 0.05);
    }
    
    .action-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .action-badge.insert {
        background: rgba(16, 185, 129, 0.15);
        color: #10b981;
        border: 1px solid rgba(16, 185, 129, 0.3);
    }
    
    .action-badge.update {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid rgba(59, 130, 246, 0.3);
    }
    
    .action-badge.delete {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid rgba(239, 68, 68, 0.3);
    }
    
    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .user-avatar {
        width: 36px;
        height: 36px;
        background: linear-gradient(135deg, var(--primary), #5a0080);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        font-weight: 700;
        color: #fff;
        flex-shrink: 0;
    }
    
    .user-details {
        display: flex;
        flex-direction: column;
    }
    
    .user-name {
        font-weight: 600;
        color: #fff;
        font-size: 13px;
    }
    
    .user-role-badge {
        font-size: 10px;
        color: #64748b;
        text-transform: capitalize;
    }
    
    .table-name {
        padding: 6px 12px;
        background: #06080b;
        border-radius: 8px;
        font-family: 'Monaco', 'Menlo', monospace;
        font-size: 12px;
        color: #a855f7;
    }
    
    .timestamp {
        color: #64748b;
        font-size: 12px;
    }
    
    .btn-icon {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 8px 12px;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 13px;
    }
    
    .btn-icon:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .btn-icon.danger:hover {
        border-color: #ef4444;
        color: #ef4444;
        background: rgba(239, 68, 68, 0.1);
    }
    
    /* Pagination Enhanced */
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        padding: 24px;
        border-top: 1px solid #1e293b;
    }
    
    .page-link {
        padding: 10px 16px;
        background: transparent;
        border: 1px solid #1e293b;
        border-radius: 8px;
        color: #94a3b8;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .page-link:hover {
        border-color: var(--primary);
        color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .page-link.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    
    .page-link.disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .page-info {
        color: #64748b;
        font-size: 13px;
        padding: 0 16px;
    }
    
    .empty-state {
        text-align: center;
        padding: 80px 24px;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #1e293b;
        margin-bottom: 24px;
    }
    
    .empty-state h3 {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    
    @media (max-width: 768px) {
        .filters-form {
            flex-direction: column;
        }
        
        .filter-group {
            width: 100%;
        }
        
        .filter-actions {
            width: 100%;
            justify-content: flex-end;
        }
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-history"></i> Audit Logs</h1>
        <p class="page-description">Track all system changes with comprehensive audit trail and restore capabilities</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= number_format($total_logs) ?></span>
            <span class="stat-label">Total Logs</span>
        </div>
        <div class="header-stat stat-success">
            <span class="stat-value"><?= $insert_count ?></span>
            <span class="stat-label">Inserts</span>
        </div>
        <div class="header-stat stat-info">
            <span class="stat-value"><?= $update_count ?></span>
            <span class="stat-label">Updates</span>
        </div>
        <div class="header-stat stat-error">
            <span class="stat-value"><?= $delete_count ?></span>
            <span class="stat-label">Deletes</span>
        </div>
    </div>
</div>

<!-- Filters Card -->
<div class="filters-card">
    <form method="GET" action="" class="filters-form">
        <input type="hidden" name="page" value="audit_log">
        <div class="filter-group">
            <label>Table</label>
            <select name="table" class="filter-select">
                <option value="">All Tables</option>
                <?php foreach ($tables as $table): ?>
                    <option value="<?= htmlspecialchars($table) ?>" <?= $filter_table === $table ? 'selected' : '' ?>><?= htmlspecialchars($table) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Action</label>
            <select name="action" class="filter-select">
                <option value="">All Actions</option>
                <option value="INSERT" <?= $filter_action === 'INSERT' ? 'selected' : '' ?>>INSERT</option>
                <option value="UPDATE" <?= $filter_action === 'UPDATE' ? 'selected' : '' ?>>UPDATE</option>
                <option value="DELETE" <?= $filter_action === 'DELETE' ? 'selected' : '' ?>>DELETE</option>
            </select>
        </div>
        <div class="filter-group">
            <label>User</label>
            <select name="user" class="filter-select">
                <option value="">All Users</option>
                <?php foreach ($users as $user): ?>
                    <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Apply</button>
            <a href="?page=audit_log" class="btn-reset">Reset</a>
        </div>
    </form>
</div>

<!-- Logs Table -->
<div class="logs-card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Log Entries</h3>
        <span class="logs-count"><?= number_format($total_logs) ?> entries</span>
    </div>
    
    <?php if (count($logs) > 0): ?>
        <div class="logs-table-container">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Table</th>
                        <th>User</th>
                        <th>Record ID</th>
                        <th>Date & Time</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td>
                                <span class="action-badge <?= strtolower($log['action_type']) ?>">
                                    <i class="fas fa-<?= $log['action_type'] === 'INSERT' ? 'plus' : ($log['action_type'] === 'UPDATE' ? 'edit' : 'trash') ?>"></i>
                                    <?= $log['action_type'] ?>
                                </span>
                            </td>
                            <td>
                                <span class="table-name"><?= htmlspecialchars($log['table_name'] ?? '') ?></span>
                            </td>
                            <td>
                                <div class="user-info">
                                    <div class="user-avatar">
                                        <?php 
                                            $initials = '';
                                            if ($log['user_name']) {
                                                $parts = explode(' ', $log['user_name']);
                                                $initials = strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
                                            } else {
                                                $initials = 'SY';
                                            }
                                            echo $initials;
                                        ?>
                                    </div>
                                    <div class="user-details">
                                        <span class="user-name"><?= htmlspecialchars($log['user_name'] ?? 'System') ?></span>
                                        <span class="user-role-badge"><?= htmlspecialchars($log['user_role'] ?? 'system') ?></span>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($log['record_id'] ?? '') ?></td>
                            <td class="timestamp"><?= date('M d, Y h:i A', strtotime($log['created_at'])) ?></td>
                            <td>
                                <button class="btn-icon" onclick="viewLogDetails(<?= $log['id'] ?>)" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($log['action_type'] !== 'INSERT' && !empty($log['old_values'])): ?>
                                    <button class="btn-icon" onclick="confirmRestore(<?= $log['id'] ?>)" title="Restore">
                                        <i class="fas fa-undo"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php if ($page_num > 1): ?>
                    <a href="?page=audit_log&p=1<?= $filter_table ? '&table=' . urlencode($filter_table) : '' ?><?= $filter_action ? '&action=' . urlencode($filter_action) : '' ?><?= $filter_user ? '&user=' . urlencode($filter_user) : '' ?>" class="page-link"><i class="fas fa-angles-left"></i></a>
                    <a href="?page=audit_log&p=<?= $page_num - 1 ?><?= $filter_table ? '&table=' . urlencode($filter_table) : '' ?><?= $filter_action ? '&action=' . urlencode($filter_action) : '' ?><?= $filter_user ? '&user=' . urlencode($filter_user) : '' ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                <?php endif; ?>
                
                <span class="page-info">Page <?= $page_num ?> of <?= $total_pages ?></span>
                
                <?php if ($page_num < $total_pages): ?>
                    <a href="?page=audit_log&p=<?= $page_num + 1 ?><?= $filter_table ? '&table=' . urlencode($filter_table) : '' ?><?= $filter_action ? '&action=' . urlencode($filter_action) : '' ?><?= $filter_user ? '&user=' . urlencode($filter_user) : '' ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                    <a href="?page=audit_log&p=<?= $total_pages ?><?= $filter_table ? '&table=' . urlencode($filter_table) : '' ?><?= $filter_action ? '&action=' . urlencode($filter_action) : '' ?><?= $filter_user ? '&user=' . urlencode($filter_user) : '' ?>" class="page-link"><i class="fas fa-angles-right"></i></a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>No Audit Logs Found</h3>
            <p>No logs match your current filters. Try adjusting your search criteria.</p>
        </div>
    <?php endif; ?>
</div>

<!-- View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header" style="background: linear-gradient(180deg, rgba(112, 0, 164, 0.08) 0%, transparent 100%); padding: 24px; border-bottom: 1px solid #1e293b;">
            <h2 style="margin: 0; font-size: 20px; display: flex; align-items: center; gap: 12px;"><i class="fas fa-eye" style="color: #a855f7;"></i> Audit Log Details</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeViewModal()" style="background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>
        <div class="modal-body" id="modalContent" style="padding: 24px;">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<script>
const logsData = <?= json_encode($logs) ?>;

function viewLogDetails(logId) {
    const log = logsData.find(l => l.id == logId);
    if (!log) return;
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 12px; color: #fff; font-size: 16px;">Log Information</h3>
            <table style="width: 100%; font-size: 14px; background: #06080b; border-radius: 10px; overflow: hidden;">
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; width: 140px; border-bottom: 1px solid #1e293b;">ID</td>
                    <td style="padding: 12px 16px; color: #fff; border-bottom: 1px solid #1e293b;">#${log.id}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; border-bottom: 1px solid #1e293b;">User</td>
                    <td style="padding: 12px 16px; color: #fff; border-bottom: 1px solid #1e293b;">${log.user_name || 'System'} (${log.user_role || 'system'})</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; border-bottom: 1px solid #1e293b;">Action</td>
                    <td style="padding: 12px 16px; color: #fff; border-bottom: 1px solid #1e293b;">${log.action_type}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; border-bottom: 1px solid #1e293b;">Table</td>
                    <td style="padding: 12px 16px; color: #a855f7; border-bottom: 1px solid #1e293b; font-family: monospace;">${log.table_name}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; border-bottom: 1px solid #1e293b;">Record ID</td>
                    <td style="padding: 12px 16px; color: #fff; border-bottom: 1px solid #1e293b;">#${log.record_id}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; color: #64748b; border-bottom: 1px solid #1e293b;">IP Address</td>
                    <td style="padding: 12px 16px; color: #fff; border-bottom: 1px solid #1e293b;">${log.ip_address || 'N/A'}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 16px; color: #64748b;">Timestamp</td>
                    <td style="padding: 12px 16px; color: #fff;">${log.created_at}</td>
                </tr>
            </table>
        </div>
    `;
    
    if (log.old_values) {
        html += `
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 12px; color: #fff; font-size: 16px;">Old Values</h3>
                <pre style="background: #06080b; border: 1px solid #1e293b; border-radius: 10px; padding: 16px; font-family: monospace; font-size: 12px; color: #ef4444; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(JSON.stringify(JSON.parse(log.old_values), null, 2))}</pre>
            </div>
        `;
    }
    
    if (log.new_values) {
        html += `
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 12px; color: #fff; font-size: 16px;">New Values</h3>
                <pre style="background: #06080b; border: 1px solid #1e293b; border-radius: 10px; padding: 16px; font-family: monospace; font-size: 12px; color: #10b981; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(JSON.stringify(JSON.parse(log.new_values), null, 2))}</pre>
            </div>
        `;
    }
    
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('viewModal').style.display = 'flex';
}

function confirmRestore(logId) {
    if (!confirm('Are you sure you want to restore this data? This will create a new audit log entry.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'restore');
    formData.append('log_id', logId);
    formData.append('csrf_token', '<?= $csrf_token ?>');
    
    fetch('../process_audit_restore.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function exportAuditLogs() {
    const currentUrl = new URL(window.location.href);
    const exportUrl = 'process_audit_logs_export.php?' + currentUrl.searchParams.toString();
    window.location.href = exportUrl;
}


</script>
