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

$csrf_token = generateCsrfToken();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    
    .audit-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .audit-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin: 0;
    }
    
    .audit-header p {
        color: #94a3b8;
        font-size: 14px;
        margin: 5px 0 0 0;
    }
    
    .filters-container {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .filters-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .filter-group label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 5px;
        text-transform: uppercase;
    }
    
    .filter-select {
        width: 100%;
        padding: 10px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    
    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
        align-items: flex-end;
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
        padding: 10px 20px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background: #5a0080;
    }
    
    .btn-secondary {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .btn-secondary:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .logs-table-container {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    
    .logs-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .logs-table thead {
        background: #06080b;
    }
    
    .logs-table th {
        padding: 15px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #1e293b;
    }
    
    .logs-table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
        font-size: 14px;
    }
    
    .logs-table tbody tr:hover {
        background: rgba(112, 0, 164, 0.05);
    }
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    
    .badge-UPDATE {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid #3b82f6;
    }
    
    .badge-INSERT {
        background: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border: 1px solid #00ff88;
    }
    
    .badge-DELETE {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    
    .btn-restore {
        background: var(--primary);
        color: #fff;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-restore:hover {
        background: #5a0080;
    }
    
    .btn-view {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-view:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 10px;
        margin-top: 20px;
        padding: 20px;
    }
    
    .pagination a,
    .pagination span {
        padding: 8px 12px;
        border: 1px solid #1e293b;
        border-radius: 4px;
        color: #94a3b8;
        text-decoration: none;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .pagination a:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .pagination .active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        overflow-y: auto;
        padding: 20px;
    }
    
    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        width: 100%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
    }
    
    .modal-header {
        padding: 20px 25px;
        border-bottom: 1px solid #1e293b;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h2 {
        font-size: 20px;
        font-weight: 700;
        margin: 0;
        color: #fff;
    }
    
    .modal-close {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .json-display {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        color: #00ff88;
        overflow-x: auto;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #1e293b;
        margin-bottom: 20px;
    }
</style>

<div class="audit-header">
    <div>
        <h1><i class="fas fa-history"></i> Audit Logs</h1>
        <p>Complete audit trail with restore capabilities</p>
    </div>
</div>

<!-- Filters -->
<div class="filters-container">
    <form method="GET" action="">
        <input type="hidden" name="page" value="admin_audit_logs">
        <div class="filters-grid">
            <div class="filter-group">
                <label>Table</label>
                <select name="table" class="filter-select">
                    <option value="">All Tables</option>
                    <?php foreach ($tables as $table): ?>
                        <option value="<?= htmlspecialchars($table) ?>" <?= $filter_table === $table ? 'selected' : '' ?>>
                            <?= htmlspecialchars($table) ?>
                        </option>
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
                        <option value="<?= $user['id'] ?>" <?= $filter_user == $user['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($user['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="filter-buttons">
                <button type="submit" class="btn-primary">
                    <i class="fas fa-filter"></i> Filter
                </button>
                <a href="?page=admin_audit_logs" class="btn-secondary">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </div>
    </form>
</div>

<?php if (empty($logs)): ?>
    <div class="logs-table-container">
        <div class="empty-state">
            <i class="fas fa-history"></i>
            <h3>No Audit Logs Found</h3>
            <p>No logs match your filter criteria</p>
        </div>
    </div>
<?php else: ?>
    <div class="logs-table-container">
        <table class="logs-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Table</th>
                    <th>Record ID</th>
                    <th>Timestamp</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td>#<?= $log['id'] ?></td>
                        <td>
                            <?= htmlspecialchars($log['user_name']) ?>
                            <div style="font-size: 11px; color: #64748b;">
                                <?= htmlspecialchars($log['user_role']) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($log['action_type']) ?>">
                                <?= htmlspecialchars($log['action_type']) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($log['table_name']) ?></td>
                        <td>#<?= $log['record_id'] ?></td>
                        <td style="font-size: 12px; color: #64748b;">
                            <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                        </td>
                        <td>
                            <button class="btn-view" onclick="viewLog(<?= $log['id'] ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($log['action_type'] === 'UPDATE' || $log['action_type'] === 'DELETE'): ?>
                                <button class="btn-restore" onclick="restoreData(<?= $log['id'] ?>)">
                                    <i class="fas fa-undo"></i> Restore
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
                <a href="?page=admin_audit_logs&p=<?= $page_num - 1 ?>&table=<?= urlencode($filter_table) ?>&action=<?= urlencode($filter_action) ?>&user=<?= urlencode($filter_user) ?>">
                    <i class="fas fa-chevron-left"></i> Previous
                </a>
            <?php endif; ?>
            
            <?php for ($i = max(1, $page_num - 2); $i <= min($total_pages, $page_num + 2); $i++): ?>
                <?php if ($i === $page_num): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?page=admin_audit_logs&p=<?= $i ?>&table=<?= urlencode($filter_table) ?>&action=<?= urlencode($filter_action) ?>&user=<?= urlencode($filter_user) ?>">
                        <?= $i ?>
                    </a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page_num < $total_pages): ?>
                <a href="?page=admin_audit_logs&p=<?= $page_num + 1 ?>&table=<?= urlencode($filter_table) ?>&action=<?= urlencode($filter_action) ?>&user=<?= urlencode($filter_user) ?>">
                    Next <i class="fas fa-chevron-right"></i>
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>

<!-- View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-eye"></i> Audit Log Details</h2>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body" id="modalContent">
            <!-- Content loaded via JavaScript -->
        </div>
    </div>
</div>

<script>
const logsData = <?= json_encode($logs) ?>;

function viewLog(logId) {
    const log = logsData.find(l => l.id == logId);
    if (!log) return;
    
    let html = `
        <div style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 10px; color: #fff;">Log Information</h3>
            <table style="width: 100%; font-size: 14px;">
                <tr>
                    <td style="padding: 8px; color: #94a3b8; width: 150px;">ID:</td>
                    <td style="padding: 8px; color: #fff;">#${log.id}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #94a3b8;">User:</td>
                    <td style="padding: 8px; color: #fff;">${log.user_name} (${log.user_role})</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #94a3b8;">Action:</td>
                    <td style="padding: 8px; color: #fff;">${log.action_type}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #94a3b8;">Table:</td>
                    <td style="padding: 8px; color: #fff;">${log.table_name}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #94a3b8;">Record ID:</td>
                    <td style="padding: 8px; color: #fff;">#${log.record_id}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #94a3b8;">IP Address:</td>
                    <td style="padding: 8px; color: #fff;">${log.ip_address || 'N/A'}</td>
                </tr>
                <tr>
                    <td style="padding: 8px; color: #94a3b8;">Timestamp:</td>
                    <td style="padding: 8px; color: #fff;">${log.created_at}</td>
                </tr>
            </table>
        </div>
    `;
    
    if (log.old_values) {
        html += `
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px; color: #fff;">Old Values</h3>
                <div class="json-display">${escapeHtml(JSON.stringify(JSON.parse(log.old_values), null, 2))}</div>
            </div>
        `;
    }
    
    if (log.new_values) {
        html += `
            <div style="margin-bottom: 20px;">
                <h3 style="margin-bottom: 10px; color: #fff;">New Values</h3>
                <div class="json-display">${escapeHtml(JSON.stringify(JSON.parse(log.new_values), null, 2))}</div>
            </div>
        `;
    }
    
    document.getElementById('modalContent').innerHTML = html;
    document.getElementById('viewModal').classList.add('show');
}

function restoreData(logId) {
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

function closeModal() {
    document.getElementById('viewModal').classList.remove('show');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
document.getElementById('viewModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});
</script>
