<?php
/**
 * Admin Security Center
 * Comprehensive security management with Login History, Audit Logs, Error Logs, and Registration Blocklist
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/blocklist.php';

// Check if user is admin (or actual admin in persona mode)
$actualRole = $_SESSION['persona_original_role'] ?? $user_role;
if ($actualRole !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

$security_tab = $_GET['tab'] ?? 'login_history';
$page_num = isset($_GET['p']) ? max(1, intval($_GET['p'])) : 1;
$per_page = 50;
$offset = ($page_num - 1) * $per_page;

// Filters
$filter_user = $_GET['filter_user'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

$csrf_token = generateCsrfToken();

// ---- LOGIN HISTORY DATA ----
$login_logs = [];
$login_total = 0;
$online_users = [];

if ($security_tab === 'login_history') {
    try {
        // Get currently online users (active session with activity within last 15 minutes or no logout)
        $online_stmt = $pdo->query("
            SELECT DISTINCT lh.user_id, u.first_name, u.last_name, u.role, u.email,
                   lh.login_time, lh.ip_address, lh.last_activity
            FROM login_history lh
            JOIN users u ON lh.user_id = u.id
            WHERE lh.login_status = 'success' 
            AND lh.logout_time IS NULL
            AND COALESCE(lh.last_activity, lh.login_time) >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
            ORDER BY COALESCE(lh.last_activity, lh.login_time) DESC
        ");
        $online_users = $online_stmt->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt PII fields
        $online_users = FieldEncryption::decryptRows($online_users, ['first_name', 'last_name']);
        
        // Build login history query with filters
        $where = [];
        $params = [];
        
        if ($filter_user) {
            $where[] = "lh.user_id = ?";
            $params[] = $filter_user;
        }
        if ($filter_status) {
            $where[] = "lh.login_status = ?";
            $params[] = $filter_status;
        }
        if ($filter_date_from) {
            $where[] = "lh.login_time >= ?";
            $params[] = $filter_date_from . ' 00:00:00';
        }
        if ($filter_date_to) {
            $where[] = "lh.login_time <= ?";
            $params[] = $filter_date_to . ' 23:59:59';
        }
        
        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        // Count total
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM login_history lh $where_clause");
        $count_stmt->execute($params);
        $login_total = $count_stmt->fetchColumn();
        
        // Fetch logs
        $params[] = $per_page;
        $params[] = $offset;
        $log_stmt = $pdo->prepare("
            SELECT lh.*, 
                   u.first_name as user_first_name, u.last_name as user_last_name,
                   u.role as user_role_name, u.email as user_email
            FROM login_history lh
            LEFT JOIN users u ON lh.user_id = u.id
            $where_clause
            ORDER BY lh.login_time DESC
            LIMIT ? OFFSET ?
        ");
        $log_stmt->execute($params);
        $login_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt PII and build display names
        foreach ($login_logs as &$ll) {
            $ll['user_first_name'] = FieldEncryption::decrypt($ll['user_first_name'] ?? '');
            $ll['user_last_name'] = FieldEncryption::decrypt($ll['user_last_name'] ?? '');
            $ll['user_name'] = trim(($ll['user_first_name'] ?? '') . ' ' . ($ll['user_last_name'] ?? ''));
        }
        unset($ll);
        
    } catch (PDOException $e) {
        error_log("Security center - login history error: " . $e->getMessage());
    }
}

// ---- AUDIT LOGS DATA ----
$audit_logs = [];
$audit_total = 0;
$audit_tables = [];
$audit_users = [];

if ($security_tab === 'audit_logs') {
    try {
        $filter_table = $_GET['table'] ?? '';
        $filter_action_type = $_GET['action_type'] ?? '';
        
        $where = [];
        $params = [];
        
        if ($filter_table) { $where[] = "al.table_name = ?"; $params[] = $filter_table; }
        if ($filter_action_type) { $where[] = "al.action_type = ?"; $params[] = $filter_action_type; }
        if ($filter_user) { $where[] = "al.user_id = ?"; $params[] = $filter_user; }
        if ($filter_date_from) { $where[] = "al.created_at >= ?"; $params[] = $filter_date_from . ' 00:00:00'; }
        if ($filter_date_to) { $where[] = "al.created_at <= ?"; $params[] = $filter_date_to . ' 23:59:59'; }
        
        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM audit_logs al $where_clause");
        $count_stmt->execute($params);
        $audit_total = $count_stmt->fetchColumn();
        
        $params[] = $per_page;
        $params[] = $offset;
        $log_stmt = $pdo->prepare("
            SELECT al.*, u.first_name as user_first_name, u.last_name as user_last_name, u.role as user_role_name
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            $where_clause
            ORDER BY al.created_at DESC LIMIT ? OFFSET ?
        ");
        $log_stmt->execute($params);
        $audit_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt PII and build display names
        foreach ($audit_logs as &$al_row) {
            $al_row['user_first_name'] = FieldEncryption::decrypt($al_row['user_first_name'] ?? '');
            $al_row['user_last_name'] = FieldEncryption::decrypt($al_row['user_last_name'] ?? '');
            $al_row['user_name'] = trim(($al_row['user_first_name'] ?? '') . ' ' . ($al_row['user_last_name'] ?? ''));
        }
        unset($al_row);
        
        // Get unique tables/users for filters
        $audit_tables = $pdo->query("SELECT DISTINCT table_name FROM audit_logs ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
        $audit_users = $pdo->query("SELECT DISTINCT u.id, u.first_name, u.last_name FROM users u INNER JOIN audit_logs al ON al.user_id = u.id ORDER BY u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt and build display names
        foreach ($audit_users as &$au) {
            $au['first_name'] = FieldEncryption::decrypt($au['first_name']);
            $au['last_name'] = FieldEncryption::decrypt($au['last_name']);
            $au['name'] = $au['first_name'] . ' ' . $au['last_name'];
        }
        unset($au);
        
    } catch (PDOException $e) {
        error_log("Security center - audit logs error: " . $e->getMessage());
    }
}

// ---- ERROR LOGS DATA ----
$error_logs = [];
$error_total = 0;

if ($security_tab === 'error_logs') {
    try {
        $filter_level = $_GET['level'] ?? '';
        
        $where = [];
        $params = [];
        
        if ($filter_level) { $where[] = "el.error_level = ?"; $params[] = $filter_level; }
        if ($filter_date_from) { $where[] = "el.created_at >= ?"; $params[] = $filter_date_from . ' 00:00:00'; }
        if ($filter_date_to) { $where[] = "el.created_at <= ?"; $params[] = $filter_date_to . ' 23:59:59'; }
        
        $where_clause = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM error_logs el $where_clause");
        $count_stmt->execute($params);
        $error_total = $count_stmt->fetchColumn();
        
        $params[] = $per_page;
        $params[] = $offset;
        $log_stmt = $pdo->prepare("
            SELECT el.*, u.first_name as user_first_name, u.last_name as user_last_name
            FROM error_logs el
            LEFT JOIN users u ON el.user_id = u.id
            $where_clause
            ORDER BY el.created_at DESC LIMIT ? OFFSET ?
        ");
        $log_stmt->execute($params);
        $error_logs = $log_stmt->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt PII and build display names
        foreach ($error_logs as &$el_row) {
            $el_row['user_first_name'] = FieldEncryption::decrypt($el_row['user_first_name'] ?? '');
            $el_row['user_last_name'] = FieldEncryption::decrypt($el_row['user_last_name'] ?? '');
            $el_row['user_name'] = trim(($el_row['user_first_name'] ?? '') . ' ' . ($el_row['user_last_name'] ?? ''));
        }
        unset($el_row);
        
    } catch (PDOException $e) {
        error_log("Security center - error logs error: " . $e->getMessage());
        // Table may not exist yet
    }
}

// Get all users for filter dropdowns
$all_users_for_filter = [];
try {
    $all_users_for_filter = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY first_name, last_name")->fetchAll(PDO::FETCH_ASSOC);
    // Decrypt and build display names
    foreach ($all_users_for_filter as &$uf) {
        $uf['first_name'] = FieldEncryption::decrypt($uf['first_name']);
        $uf['last_name'] = FieldEncryption::decrypt($uf['last_name']);
        $uf['name'] = $uf['first_name'] . ' ' . $uf['last_name'];
    }
    unset($uf);
} catch (PDOException $e) { /* ignore */ }

// ---- BLOCKLIST DATA ----
$restrictions = [];
$blocklist_total = 0;

if ($security_tab === 'blocklist') {
    $restrictions = Blocklist::getRestrictions($pdo);
    foreach ($restrictions as $r) {
        $blocklist_total += count($r['entries']);
    }
}

// ---- POS IP WHITELIST DATA ----
$pos_whitelist_entries = [];
$pos_whitelist_total = 0;

if ($security_tab === 'pos_whitelist') {
    try {
        $wl_stmt = $pdo->query("
            SELECT pw.*, u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM pos_allowed_ips pw
            LEFT JOIN users u ON pw.created_by = u.id
            ORDER BY pw.created_at DESC
        ");
        $pos_whitelist_entries = $wl_stmt->fetchAll(PDO::FETCH_ASSOC);
        // Decrypt creator names and build display name
        foreach ($pos_whitelist_entries as &$pw_row) {
            $pw_row['creator_first_name'] = FieldEncryption::decrypt($pw_row['creator_first_name'] ?? '');
            $pw_row['creator_last_name'] = FieldEncryption::decrypt($pw_row['creator_last_name'] ?? '');
            $pw_row['created_by_name'] = trim(($pw_row['creator_first_name'] ?? '') . ' ' . ($pw_row['creator_last_name'] ?? ''));
        }
        unset($pw_row);
        $pos_whitelist_total = count($pos_whitelist_entries);
    } catch (PDOException $e) {
        error_log("Security center - POS whitelist error: " . $e->getMessage());
    }
}

$login_total_pages = ceil($login_total / $per_page);
$audit_total_pages = ceil($audit_total / $per_page);
$error_total_pages = ceil($error_total / $per_page);
?>

<style>
    /* Use standard .tabs/.tab from shared_styles.css for tab navigation */
    .tabs a.tab { text-decoration: none; display: inline-flex; align-items: center; gap: 8px; }
    .tabs a.tab .badge { padding: 2px 8px; background: rgba(107, 70, 193, 0.15); color: var(--primary-light, #a78bfa); border-radius: 10px; font-size: 11px; font-weight: 700; }
    .security-filters { display: flex; gap: 12px; flex-wrap: wrap; margin-bottom: 20px; align-items: flex-end; }
    .security-filters .form-group { margin-bottom: 0; }
    .security-filters .form-group label { font-size: 11px; font-weight: 700; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; display: block; }
    .online-users-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 12px; margin-bottom: 24px; }
    .online-user-card { background: var(--card-bg, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 10px; padding: 14px 16px; display: flex; align-items: center; gap: 12px; }
    .online-indicator { width: 10px; height: 10px; background: #10b981; border-radius: 50%; flex-shrink: 0; box-shadow: 0 0 6px rgba(16, 185, 129, 0.5); animation: pulse-green 2s infinite; }
    @keyframes pulse-green { 0%, 100% { box-shadow: 0 0 6px rgba(16, 185, 129, 0.3); } 50% { box-shadow: 0 0 12px rgba(16, 185, 129, 0.6); } }
    .online-user-info { flex: 1; }
    .online-user-name { font-weight: 600; font-size: 14px; color: #fff; }
    .online-user-meta { font-size: 12px; color: var(--text-muted, #64748b); }
    .log-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .log-table thead th { padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; background: rgba(107, 70, 193, 0.05); border-bottom: 2px solid var(--border, #2D2D3F); }
    .log-table tbody td { padding: 12px 16px; border-bottom: 1px solid var(--border, #2D2D3F); font-size: 13px; vertical-align: middle; }
    .log-table tbody tr:hover { background: rgba(107, 70, 193, 0.03); }
    .status-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .status-pill.success { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .status-pill.failed { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .status-pill.blocked { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
    .status-pill.insert { background: rgba(16, 185, 129, 0.15); color: #10b981; }
    .status-pill.update { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
    .status-pill.delete { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .status-pill.error { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
    .status-pill.warning { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
    .status-pill.info { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
    .pagination-bar { display: flex; justify-content: space-between; align-items: center; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border, #2D2D3F); }
    .pagination-info { font-size: 13px; color: var(--text-muted, #64748b); }
    .pagination-links { display: flex; gap: 4px; }
    .pagination-links a { padding: 6px 12px; background: var(--bg, #0a0a0f); border: 1px solid var(--border, #2D2D3F); border-radius: 6px; color: var(--text, #94a3b8); text-decoration: none; font-size: 13px; font-weight: 600; transition: all 0.2s; }
    .pagination-links a:hover, .pagination-links a.active { background: var(--primary, #6B46C1); border-color: var(--primary, #6B46C1); color: #fff; }
    .detail-modal-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 9999; align-items: center; justify-content: center; }
    .detail-modal-overlay.active { display: flex; }
    .detail-modal { background: var(--card-bg, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 12px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto; padding: 24px; }
    .ip-cell { font-family: monospace; font-size: 12px; color: var(--text-dim, #64748b); }
    .blocklist-type-pill { display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; text-transform: uppercase; }
    .blocklist-type-pill.email { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
    .blocklist-type-pill.name { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
    .blocklist-type-pill.ip { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-shield-halved"></i> Security Center</h1>
        <p class="page-description">Monitor login activity, audit trails, system errors, and registration restrictions</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?php echo count($online_users); ?></span>
            <span class="stat-label">Online Now</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?php echo number_format($login_total); ?></span>
            <span class="stat-label">Login Records</span>
        </div>
    </div>
</div>

<!-- Security Tabs - using standard .tabs/.tab classes -->
<div class="tabs">
    <a href="?page=admin_security&tab=login_history" class="tab <?= $security_tab === 'login_history' ? 'active' : '' ?>">
        <i class="fas fa-clock-rotate-left"></i> Login History
    </a>
    <a href="?page=admin_security&tab=audit_logs" class="tab <?= $security_tab === 'audit_logs' ? 'active' : '' ?>">
        <i class="fas fa-list-check"></i> Audit Log
    </a>
    <a href="?page=admin_security&tab=error_logs" class="tab <?= $security_tab === 'error_logs' ? 'active' : '' ?>">
        <i class="fas fa-bug"></i> Error Log
    </a>
    <a href="?page=admin_security&tab=blocklist" class="tab <?= $security_tab === 'blocklist' ? 'active' : '' ?>">
        <i class="fas fa-ban"></i> Registration Restrictions
        <?php if ($security_tab === 'blocklist' && count($restrictions) > 0): ?>
        <span class="badge"><?php echo count($restrictions); ?></span>
        <?php endif; ?>
    </a>
    <a href="?page=admin_security&tab=pos_whitelist" class="tab <?= $security_tab === 'pos_whitelist' ? 'active' : '' ?>">
        <i class="fas fa-cash-register"></i> POS IP Whitelist
        <?php if ($security_tab === 'pos_whitelist' && $pos_whitelist_total > 0): ?>
        <span class="badge"><?php echo $pos_whitelist_total; ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($security_tab === 'login_history'): ?>
<!-- Online Users Section -->
<?php if (count($online_users) > 0): ?>
<div class="card" style="margin-bottom: 24px;">
    <div class="card-header">
        <h3><i class="fas fa-circle" style="color: #10b981; font-size: 10px;"></i> Currently Online (<?php echo count($online_users); ?>)</h3>
    </div>
    <div class="card-body">
        <div class="online-users-grid">
            <?php foreach ($online_users as $ou): ?>
            <div class="online-user-card">
                <div class="online-indicator"></div>
                <div class="online-user-info">
                    <div class="online-user-name"><?php echo htmlspecialchars($ou['first_name'] . ' ' . $ou['last_name']); ?></div>
                    <div class="online-user-meta">
                        <span class="role-badge <?php echo $ou['role']; ?>" style="padding: 2px 8px; font-size: 10px;"><?php echo ucfirst(str_replace('_', ' ', $ou['role'])); ?></span>
                        · Since <?php echo date('g:ia', strtotime($ou['login_time'])); ?>
                        · <?php echo htmlspecialchars($ou['ip_address'] ?? ''); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filters -->
<div class="security-filters">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="admin_security">
        <input type="hidden" name="tab" value="login_history">
        <div class="form-group">
            <label>User</label>
            <select name="filter_user" class="form-input" style="min-width: 180px;">
                <option value="">All Users</option>
                <?php foreach ($all_users_for_filter as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?php echo htmlspecialchars($u['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Status</label>
            <select name="filter_status" class="form-input">
                <option value="">All</option>
                <option value="success" <?= $filter_status === 'success' ? 'selected' : '' ?>>Success</option>
                <option value="failed" <?= $filter_status === 'failed' ? 'selected' : '' ?>>Failed</option>
                <option value="blocked" <?= $filter_status === 'blocked' ? 'selected' : '' ?>>Blocked</option>
            </select>
        </div>
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" class="form-input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" class="form-input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<!-- Login History Table -->
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Status</th>
                    <th>Login Time</th>
                    <th>Logout Time</th>
                    <th>IP Address</th>
                    <th>Reason</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($login_logs)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No login records found</td></tr>
                <?php else: ?>
                <?php foreach ($login_logs as $log): ?>
                <tr>
                    <td>
                        <div style="font-weight: 600;"><?php echo htmlspecialchars($log['user_name'] ?? 'Unknown'); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo htmlspecialchars($log['user_email'] ?? ''); ?> · <?php echo ucfirst(str_replace('_', ' ', $log['user_role_name'] ?? '')); ?></div>
                    </td>
                    <td><span class="status-pill <?php echo $log['login_status']; ?>"><?php echo ucfirst($log['login_status']); ?></span></td>
                    <td><?php echo date('M d, Y g:i:s a', strtotime($log['login_time'])); ?></td>
                    <td>
                        <?php if ($log['logout_time']): ?>
                            <?php echo date('M d, Y g:i:s a', strtotime($log['logout_time'])); ?>
                        <?php elseif ($log['login_status'] === 'success'): ?>
                            <span style="color: #10b981; font-size: 12px;"><i class="fas fa-circle" style="font-size: 8px;"></i> Active</span>
                        <?php else: ?>
                            <span style="color: var(--text-muted);">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="ip-cell"><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                    <td style="font-size: 12px; color: var(--text-muted);"><?php echo htmlspecialchars($log['failure_reason'] ?? '—'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($login_total > $per_page): ?>
        <div class="pagination-bar">
            <div class="pagination-info">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $login_total); ?> of <?php echo $login_total; ?></div>
            <div class="pagination-links">
                <?php if ($page_num > 1): ?>
                <a href="?page=admin_security&tab=login_history&p=<?php echo $page_num - 1; ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_status=<?php echo urlencode($filter_status); ?>">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page_num - 2); $i <= min($login_total_pages, $page_num + 2); $i++): ?>
                <a href="?page=admin_security&tab=login_history&p=<?php echo $i; ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_status=<?php echo urlencode($filter_status); ?>" class="<?= $i === $page_num ? 'active' : '' ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page_num < $login_total_pages): ?>
                <a href="?page=admin_security&tab=login_history&p=<?php echo $page_num + 1; ?>&filter_user=<?php echo urlencode($filter_user); ?>&filter_status=<?php echo urlencode($filter_status); ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($security_tab === 'audit_logs'): ?>
<!-- Audit Logs Filters -->
<div class="security-filters">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="admin_security">
        <input type="hidden" name="tab" value="audit_logs">
        <div class="form-group">
            <label>Table</label>
            <select name="table" class="form-input" style="min-width: 150px;">
                <option value="">All Tables</option>
                <?php foreach ($audit_tables as $t): ?>
                <option value="<?php echo htmlspecialchars($t); ?>" <?= ($_GET['table'] ?? '') === $t ? 'selected' : '' ?>><?php echo htmlspecialchars($t); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Action</label>
            <select name="action_type" class="form-input">
                <option value="">All Actions</option>
                <option value="INSERT" <?= ($_GET['action_type'] ?? '') === 'INSERT' ? 'selected' : '' ?>>Insert</option>
                <option value="UPDATE" <?= ($_GET['action_type'] ?? '') === 'UPDATE' ? 'selected' : '' ?>>Update</option>
                <option value="DELETE" <?= ($_GET['action_type'] ?? '') === 'DELETE' ? 'selected' : '' ?>>Delete</option>
            </select>
        </div>
        <div class="form-group">
            <label>User</label>
            <select name="filter_user" class="form-input" style="min-width: 180px;">
                <option value="">All Users</option>
                <?php foreach ($audit_users as $u): ?>
                <option value="<?php echo $u['id']; ?>" <?= $filter_user == $u['id'] ? 'selected' : '' ?>><?php echo htmlspecialchars($u['name']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" class="form-input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" class="form-input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<!-- Audit Logs Table -->
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Table</th>
                    <th>User</th>
                    <th>Record ID</th>
                    <th>Timestamp</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($audit_logs)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No audit records found</td></tr>
                <?php else: ?>
                <?php foreach ($audit_logs as $log): ?>
                <tr>
                    <td><span class="status-pill <?php echo strtolower($log['action_type']); ?>"><?php echo htmlspecialchars($log['action_type']); ?></span></td>
                    <td><code style="font-size: 12px; background: rgba(107,70,193,0.1); padding: 2px 8px; border-radius: 4px;"><?php echo htmlspecialchars($log['table_name'] ?? ''); ?></code></td>
                    <td>
                        <div style="font-weight: 600; font-size: 13px;"><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></div>
                        <div style="font-size: 11px; color: var(--text-muted);"><?php echo ucfirst(str_replace('_', ' ', $log['user_role_name'] ?? '')); ?></div>
                    </td>
                    <td style="font-family: monospace;">#<?php echo htmlspecialchars($log['record_id'] ?? ''); ?></td>
                    <td style="font-size: 12px;"><?php echo date('M d, Y g:i:s a', strtotime($log['created_at'])); ?></td>
                    <td>
                        <?php if (!empty($log['old_values']) || !empty($log['new_values'])): ?>
                        <button class="btn-icon" onclick="showAuditDetail(<?php echo $log['id']; ?>)" title="View Details">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php endif; ?>
                        <?php if ($log['action_type'] === 'UPDATE' || $log['action_type'] === 'DELETE'): ?>
                        <button class="btn-icon" onclick="restoreAuditEntry(<?php echo $log['id']; ?>)" title="Revert Change" style="margin-left: 4px;">
                            <i class="fas fa-undo"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($audit_total > $per_page): ?>
        <div class="pagination-bar">
            <div class="pagination-info">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $audit_total); ?> of <?php echo $audit_total; ?></div>
            <div class="pagination-links">
                <?php if ($page_num > 1): ?>
                <a href="?page=admin_security&tab=audit_logs&p=<?php echo $page_num - 1; ?>">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page_num - 2); $i <= min($audit_total_pages, $page_num + 2); $i++): ?>
                <a href="?page=admin_security&tab=audit_logs&p=<?php echo $i; ?>" class="<?= $i === $page_num ? 'active' : '' ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page_num < $audit_total_pages): ?>
                <a href="?page=admin_security&tab=audit_logs&p=<?php echo $page_num + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Audit Detail Modal -->
<div id="audit-detail-modal" class="detail-modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="detail-modal">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;"><i class="fas fa-list-check"></i> Audit Log Detail</h3>
            <button onclick="document.getElementById('audit-detail-modal').classList.remove('active')" style="background: none; border: none; color: #fff; font-size: 20px; cursor: pointer;">&times;</button>
        </div>
        <div id="audit-detail-content">Loading...</div>
    </div>
</div>

<script>
function showAuditDetail(logId) {
    var modal = document.getElementById('audit-detail-modal');
    var content = document.getElementById('audit-detail-content');
    modal.classList.add('active');
    
    // Find the log data from the table
    var logs = <?php echo json_encode($audit_logs); ?>;
    var log = logs.find(function(l) { return l.id == logId; });
    
    if (log) {
        var html = '<div style="margin-bottom: 12px;"><strong>Action:</strong> ' + log.action_type + ' on <code>' + (log.table_name || '') + '</code></div>';
        html += '<div style="margin-bottom: 12px;"><strong>Record ID:</strong> #' + (log.record_id || '') + '</div>';
        html += '<div style="margin-bottom: 12px;"><strong>User:</strong> ' + (log.user_name || 'System') + '</div>';
        html += '<div style="margin-bottom: 12px;"><strong>Time:</strong> ' + log.created_at + '</div>';
        if (log.ip_address) html += '<div style="margin-bottom: 12px;"><strong>IP:</strong> ' + log.ip_address + '</div>';
        
        if (log.old_values) {
            html += '<div style="margin-bottom: 8px;"><strong>Old Values:</strong></div>';
            html += '<pre style="background: rgba(239,68,68,0.1); padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 12px; max-height: 200px;">' + JSON.stringify(JSON.parse(log.old_values), null, 2) + '</pre>';
        }
        if (log.new_values) {
            html += '<div style="margin-bottom: 8px;"><strong>New Values:</strong></div>';
            html += '<pre style="background: rgba(16,185,129,0.1); padding: 12px; border-radius: 8px; overflow-x: auto; font-size: 12px; max-height: 200px;">' + JSON.stringify(JSON.parse(log.new_values), null, 2) + '</pre>';
        }
        content.innerHTML = html;
    }
}

function restoreAuditEntry(logId) {
    if (!confirm('Are you sure you want to revert this change? This will restore the previous values.')) return;
    
    var csrfToken = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>';
    
    fetch('process_audit_restore.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'log_id=' + logId + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert('Change reverted successfully');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to revert'));
        }
    })
    .catch(function() { alert('An error occurred'); });
}
</script>

<?php elseif ($security_tab === 'error_logs'): ?>
<!-- Error Logs Filters -->
<div class="security-filters">
    <form method="GET" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
        <input type="hidden" name="page" value="admin_security">
        <input type="hidden" name="tab" value="error_logs">
        <div class="form-group">
            <label>Level</label>
            <select name="level" class="form-input">
                <option value="">All Levels</option>
                <option value="ERROR" <?= ($_GET['level'] ?? '') === 'ERROR' ? 'selected' : '' ?>>Error</option>
                <option value="WARNING" <?= ($_GET['level'] ?? '') === 'WARNING' ? 'selected' : '' ?>>Warning</option>
                <option value="INFO" <?= ($_GET['level'] ?? '') === 'INFO' ? 'selected' : '' ?>>Info</option>
                <option value="SECURITY" <?= ($_GET['level'] ?? '') === 'SECURITY' ? 'selected' : '' ?>>Security</option>
            </select>
        </div>
        <div class="form-group">
            <label>From</label>
            <input type="date" name="date_from" class="form-input" value="<?php echo htmlspecialchars($filter_date_from); ?>">
        </div>
        <div class="form-group">
            <label>To</label>
            <input type="date" name="date_to" class="form-input" value="<?php echo htmlspecialchars($filter_date_to); ?>">
        </div>
        <button type="submit" class="btn btn-secondary"><i class="fas fa-filter"></i> Filter</button>
    </form>
</div>

<!-- Error Logs Table -->
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th>Level</th>
                    <th>Message</th>
                    <th>File / Line</th>
                    <th>User</th>
                    <th>URL</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($error_logs)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">No error logs found. Error logging table may need to be created.</td></tr>
                <?php else: ?>
                <?php foreach ($error_logs as $log): ?>
                <tr>
                    <td><span class="status-pill <?php echo strtolower($log['error_level']); ?>"><?php echo htmlspecialchars($log['error_level']); ?></span></td>
                    <td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?php echo htmlspecialchars($log['message']); ?>"><?php echo htmlspecialchars(substr($log['message'], 0, 100)); ?></td>
                    <td style="font-size: 12px; font-family: monospace;">
                        <?php if ($log['file']): ?>
                            <?php echo htmlspecialchars(basename($log['file'])); ?>:<?php echo $log['line']; ?>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 13px;"><?php echo htmlspecialchars($log['user_name'] ?? '—'); ?></td>
                    <td style="font-size: 11px; max-width: 150px; overflow: hidden; text-overflow: ellipsis;" title="<?php echo htmlspecialchars($log['url'] ?? ''); ?>"><?php echo htmlspecialchars($log['url'] ?? '—'); ?></td>
                    <td style="font-size: 12px;"><?php echo date('M d, Y g:i:s a', strtotime($log['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($error_total > $per_page): ?>
        <div class="pagination-bar">
            <div class="pagination-info">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $per_page, $error_total); ?> of <?php echo $error_total; ?></div>
            <div class="pagination-links">
                <?php if ($page_num > 1): ?>
                <a href="?page=admin_security&tab=error_logs&p=<?php echo $page_num - 1; ?>">&laquo; Prev</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page_num - 2); $i <= min($error_total_pages, $page_num + 2); $i++): ?>
                <a href="?page=admin_security&tab=error_logs&p=<?php echo $i; ?>" class="<?= $i === $page_num ? 'active' : '' ?>"><?php echo $i; ?></a>
                <?php endfor; ?>
                <?php if ($page_num < $error_total_pages): ?>
                <a href="?page=admin_security&tab=error_logs&p=<?php echo $page_num + 1; ?>">Next &raquo;</a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php elseif ($security_tab === 'blocklist'): ?>

<!-- Restrictions Action Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">
        <i class="fas fa-info-circle"></i> Create named restrictions and add multiple trigger entries (email, name, or IP) to each one.
    </p>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('restriction-create-modal').classList.add('active')">
        <i class="fas fa-plus"></i> Create Restriction
    </button>
</div>

<!-- Restrictions List -->
<?php if (empty($restrictions)): ?>
<div class="card">
    <div class="card-body" style="text-align: center; padding: 40px; color: var(--text-muted);">
        <i class="fas fa-ban" style="font-size: 24px; margin-bottom: 8px; display: block; opacity: 0.3;"></i>
        No restrictions found. Click "Create Restriction" to create one.
    </div>
</div>
<?php else: ?>
<?php foreach ($restrictions as $restriction): ?>
<div class="card" id="restriction-card-<?php echo (int)$restriction['id']; ?>" style="margin-bottom: 16px;">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px;">
            <div>
                <h4 style="margin: 0; font-size: 15px; font-weight: 700;"><?php echo htmlspecialchars($restriction['title']); ?></h4>
                <span style="font-size: 11px; color: var(--text-muted, #64748b);">
                    Created by <?php echo htmlspecialchars($restriction['created_by_name'] ?: 'System'); ?> on <?php echo date('M d, Y', strtotime($restriction['created_at'])); ?>
                    &bull; <?php echo count($restriction['entries']); ?> trigger<?php echo count($restriction['entries']) !== 1 ? 's' : ''; ?>
                </span>
            </div>
            <div style="display: flex; gap: 8px;">
                <button class="btn btn-primary btn-sm" onclick="openAddEntryModal(<?php echo (int)$restriction['id']; ?>, '<?php echo htmlspecialchars(addslashes($restriction['title']), ENT_QUOTES); ?>')">
                    <i class="fas fa-plus"></i> Add Entry
                </button>
                <button class="btn btn-danger btn-sm" onclick="removeRestriction(<?php echo (int)$restriction['id']; ?>)" title="Delete restriction and all entries">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        </div>
        <?php if (!empty($restriction['entries'])): ?>
        <div style="overflow-x: auto;">
            <table class="log-table" style="margin-bottom: 0;">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Date Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($restriction['entries'] as $entry): ?>
                    <tr id="blocklist-row-<?php echo $entry['id']; ?>">
                        <td><span class="blocklist-type-pill <?php echo htmlspecialchars($entry['block_type']); ?>">
                            <i class="fas fa-<?php echo $entry['block_type'] === 'email' ? 'envelope' : ($entry['block_type'] === 'name' ? 'user' : 'globe'); ?>"></i>
                            <?php echo ucfirst(htmlspecialchars($entry['block_type'])); ?>
                        </span></td>
                        <td style="font-weight: 600;"><?php echo htmlspecialchars($entry['block_value']); ?></td>
                        <td style="font-size: 12px;"><?php echo date('M d, Y g:i a', strtotime($entry['created_at'])); ?></td>
                        <td>
                            <button class="btn btn-danger btn-sm" onclick="removeBlocklistEntry(<?php echo (int)$entry['id']; ?>)" title="Remove entry">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 8px 0 0; text-align: center; padding: 16px 0;">
            No trigger entries yet. Click "Add Entry" to add one.
        </p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<!-- Create Restriction Modal -->
<div id="restriction-create-modal" class="detail-modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="detail-modal">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;"><i class="fas fa-ban" style="color: var(--primary, #6B46C1);"></i> Create Restriction</h3>
            <button onclick="document.getElementById('restriction-create-modal').classList.remove('active')" style="background: none; border: none; color: #9CA3AF; font-size: 20px; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>
        <p style="font-size: 13px; color: var(--text-muted, #64748b); margin-bottom: 20px;">
            Give this restriction a title. You can then add multiple trigger entries to it.
        </p>
        <form id="restriction-create-form" onsubmit="return createRestriction(event)">
            <div style="margin-bottom: 20px;">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Title <span style="color: #ef4444;">*</span></label>
                <input type="text" name="title" id="restriction-title" class="form-input" required placeholder="e.g. Banned User - John Doe" style="width: 100%;">
            </div>
            <div id="restriction-form-message" style="display: none; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;"></div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('restriction-create-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="restriction-submit-btn"><i class="fas fa-plus"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Entry to Restriction Modal -->
<div id="blocklist-add-modal" class="detail-modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="detail-modal">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;"><i class="fas fa-plus-circle" style="color: var(--primary, #6B46C1);"></i> Add Entry</h3>
            <button onclick="document.getElementById('blocklist-add-modal').classList.remove('active')" style="background: none; border: none; color: #9CA3AF; font-size: 20px; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>
        <p style="font-size: 13px; color: var(--text-muted, #64748b); margin-bottom: 20px;">
            Adding entry to: <strong id="entry-modal-restriction-title"></strong>
        </p>
        <form id="blocklist-add-form" onsubmit="return addBlocklistEntry(event)">
            <input type="hidden" name="restriction_id" id="entry-restriction-id" value="">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Trigger Type <span style="color: #ef4444;">*</span></label>
                <select name="block_type" id="blocklist-type" class="form-input" required onchange="updateBlocklistPlaceholder()" style="width: 100%;">
                    <option value="email">Email Address</option>
                    <option value="name">Full Name</option>
                    <option value="ip">IP Address</option>
                </select>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Value <span style="color: #ef4444;">*</span></label>
                <input type="text" name="block_value" id="blocklist-value" class="form-input" required placeholder="user@example.com" style="width: 100%;">
            </div>
            <div id="blocklist-form-message" style="display: none; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;"></div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('blocklist-add-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="blocklist-submit-btn"><i class="fas fa-plus"></i> Add Entry</button>
            </div>
        </form>
    </div>
</div>

<script>
var blocklistCsrfToken = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>';

function updateBlocklistPlaceholder() {
    var type = document.getElementById('blocklist-type').value;
    var input = document.getElementById('blocklist-value');
    switch (type) {
        case 'email': input.placeholder = 'user@example.com'; break;
        case 'name': input.placeholder = 'John Smith'; break;
        case 'ip': input.placeholder = '192.168.1.100'; break;
    }
}

function openAddEntryModal(restrictionId, restrictionTitle) {
    document.getElementById('entry-restriction-id').value = restrictionId;
    document.getElementById('entry-modal-restriction-title').textContent = restrictionTitle;
    document.getElementById('blocklist-add-form').reset();
    document.getElementById('entry-restriction-id').value = restrictionId;
    document.getElementById('blocklist-form-message').style.display = 'none';
    updateBlocklistPlaceholder();
    document.getElementById('blocklist-add-modal').classList.add('active');
}

function createRestriction(e) {
    e.preventDefault();
    var form = document.getElementById('restriction-create-form');
    var btn = document.getElementById('restriction-submit-btn');
    var msg = document.getElementById('restriction-form-message');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';

    var formData = new FormData(form);
    formData.append('action', 'create_restriction');
    formData.append('csrf_token', blocklistCsrfToken);

    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(16,185,129,0.15)';
            msg.style.color = '#10b981';
            msg.textContent = data.message;
            setTimeout(function() { location.reload(); }, 800);
        } else {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239,68,68,0.15)';
            msg.style.color = '#ef4444';
            msg.textContent = data.message || 'Failed to create restriction';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Create';
        }
    })
    .catch(function() {
        msg.style.display = 'block';
        msg.style.background = 'rgba(239,68,68,0.15)';
        msg.style.color = '#ef4444';
        msg.textContent = 'An error occurred. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus"></i> Create';
    });
    return false;
}

function addBlocklistEntry(e) {
    e.preventDefault();
    var form = document.getElementById('blocklist-add-form');
    var btn = document.getElementById('blocklist-submit-btn');
    var msg = document.getElementById('blocklist-form-message');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

    var formData = new FormData(form);
    formData.append('action', 'add_blocklist_entry');
    formData.append('csrf_token', blocklistCsrfToken);

    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(16,185,129,0.15)';
            msg.style.color = '#10b981';
            msg.textContent = data.message;
            setTimeout(function() { location.reload(); }, 800);
        } else {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239,68,68,0.15)';
            msg.style.color = '#ef4444';
            msg.textContent = data.message || 'Failed to add entry';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add Entry';
        }
    })
    .catch(function() {
        msg.style.display = 'block';
        msg.style.background = 'rgba(239,68,68,0.15)';
        msg.style.color = '#ef4444';
        msg.textContent = 'An error occurred. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus"></i> Add Entry';
    });
    return false;
}

function removeBlocklistEntry(entryId) {
    if (!confirm('Are you sure you want to remove this entry?')) return;

    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=remove_blocklist_entry&entry_id=' + entryId + '&csrf_token=' + encodeURIComponent(blocklistCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var row = document.getElementById('blocklist-row-' + entryId);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function() { row.remove(); }, 300);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to remove entry'));
        }
    })
    .catch(function() { alert('An error occurred'); });
}

function removeRestriction(restrictionId) {
    if (!confirm('Are you sure you want to delete this restriction and all its entries?')) return;

    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=remove_restriction&restriction_id=' + restrictionId + '&csrf_token=' + encodeURIComponent(blocklistCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var card = document.getElementById('restriction-card-' + restrictionId);
            if (card) {
                card.style.transition = 'opacity 0.3s';
                card.style.opacity = '0';
                setTimeout(function() { card.remove(); }, 300);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to remove restriction'));
        }
    })
    .catch(function() { alert('An error occurred'); });
}
</script>

<?php elseif ($security_tab === 'pos_whitelist'): ?>

<!-- POS IP Whitelist Action Bar -->
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 12px;">
    <div>
        <p style="font-size: 13px; color: var(--text-muted, #64748b); margin: 0;">
            <i class="fas fa-info-circle"></i> Configure which IP addresses are allowed to access the POS system. Admins are always exempt from IP restrictions.
            <?php if ($pos_whitelist_total === 0): ?>
            <br><strong>No IPs configured — POS access is currently open to all locations.</strong>
            <?php endif; ?>
        </p>
    </div>
    <button class="btn btn-primary btn-sm" onclick="document.getElementById('pos-whitelist-add-modal').classList.add('active')">
        <i class="fas fa-plus"></i> Add Allowed IP
    </button>
</div>

<!-- POS IP Whitelist Table -->
<div class="card">
    <div class="card-body" style="overflow-x: auto;">
        <table class="log-table">
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Label</th>
                    <th>Status</th>
                    <th>Created By</th>
                    <th>Date Added</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($pos_whitelist_entries)): ?>
                <tr><td colspan="6" style="text-align: center; padding: 40px; color: var(--text-muted);">
                    <i class="fas fa-cash-register" style="font-size: 24px; margin-bottom: 8px; display: block; opacity: 0.3;"></i>
                    No POS IP whitelist entries. Click "Add Allowed IP" to restrict POS access to specific locations.
                </td></tr>
                <?php else: ?>
                <?php foreach ($pos_whitelist_entries as $entry): ?>
                <tr id="pos-wl-row-<?php echo $entry['id']; ?>">
                    <td class="ip-cell" style="font-weight: 600;"><?php echo htmlspecialchars($entry['ip_address']); ?></td>
                    <td><?php echo htmlspecialchars($entry['label'] ?? '—'); ?></td>
                    <td>
                        <span class="status-pill <?php echo $entry['is_active'] ? 'success' : 'blocked'; ?>" style="cursor: pointer;" onclick="togglePosWhitelistEntry(<?php echo (int)$entry['id']; ?>, <?php echo $entry['is_active'] ? '0' : '1'; ?>)" title="Click to <?php echo $entry['is_active'] ? 'disable' : 'enable'; ?>">
                            <i class="fas fa-<?php echo $entry['is_active'] ? 'check-circle' : 'pause-circle'; ?>"></i>
                            <?php echo $entry['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </td>
                    <td style="font-size: 13px;"><?php echo htmlspecialchars($entry['created_by_name'] ?? 'System'); ?></td>
                    <td style="font-size: 12px;"><?php echo date('M d, Y g:i a', strtotime($entry['created_at'])); ?></td>
                    <td>
                        <button class="btn-icon" onclick="togglePosWhitelistEntry(<?php echo (int)$entry['id']; ?>, <?php echo $entry['is_active'] ? '0' : '1'; ?>)" title="<?php echo $entry['is_active'] ? 'Disable' : 'Enable'; ?>" style="margin-right: 4px;">
                            <i class="fas fa-<?php echo $entry['is_active'] ? 'toggle-on' : 'toggle-off'; ?>" style="color: <?php echo $entry['is_active'] ? '#10b981' : '#64748b'; ?>;"></i>
                        </button>
                        <button class="btn btn-danger btn-sm" onclick="removePosWhitelistEntry(<?php echo (int)$entry['id']; ?>)" title="Remove">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add POS IP Whitelist Modal -->
<div id="pos-whitelist-add-modal" class="detail-modal-overlay" onclick="if(event.target===this)this.classList.remove('active')">
    <div class="detail-modal">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h3 style="margin: 0; font-size: 16px; font-weight: 700;"><i class="fas fa-cash-register" style="color: var(--primary, #6B46C1);"></i> Add Allowed IP Address</h3>
            <button onclick="document.getElementById('pos-whitelist-add-modal').classList.remove('active')" style="background: none; border: none; color: #9CA3AF; font-size: 20px; cursor: pointer; padding: 4px 8px;">&times;</button>
        </div>
        <p style="font-size: 13px; color: var(--text-muted, #64748b); margin-bottom: 20px;">
            Add an IP address that is allowed to access the POS system. Non-admin staff will only be able to use the POS from whitelisted IP addresses.
        </p>
        <form id="pos-whitelist-add-form" onsubmit="return addPosWhitelistEntry(event)">
            <div style="margin-bottom: 16px;">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">IP Address <span style="color: #ef4444;">*</span></label>
                <input type="text" name="ip_address" id="pos-wl-ip" class="form-input" required placeholder="e.g. 192.168.1.100" style="width: 100%;" pattern="^(\d{1,3}\.){3}\d{1,3}$|^([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}$">
                <small style="color: var(--text-muted, #64748b); font-size: 11px;">Enter a valid IPv4 or IPv6 address</small>
            </div>
            <div style="margin-bottom: 20px;">
                <label style="font-size: 12px; font-weight: 600; color: var(--text-secondary, #94a3b8); text-transform: uppercase; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">Label (optional)</label>
                <input type="text" name="label" id="pos-wl-label" class="form-input" placeholder="e.g. Front Desk Terminal, Main Register" style="width: 100%;">
            </div>
            <div id="pos-wl-form-message" style="display: none; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;"></div>
            <div style="display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('pos-whitelist-add-modal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary btn-sm" id="pos-wl-submit-btn"><i class="fas fa-plus"></i> Add IP</button>
            </div>
        </form>
    </div>
</div>

<script>
var posWlCsrfToken = '<?php echo htmlspecialchars($csrf_token, ENT_QUOTES); ?>';

function addPosWhitelistEntry(e) {
    e.preventDefault();
    var form = document.getElementById('pos-whitelist-add-form');
    var btn = document.getElementById('pos-wl-submit-btn');
    var msg = document.getElementById('pos-wl-form-message');

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';

    var formData = new FormData(form);
    formData.append('action', 'add_pos_whitelist_entry');
    formData.append('csrf_token', posWlCsrfToken);

    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            msg.style.display = 'block';
            msg.style.background = 'rgba(16,185,129,0.15)';
            msg.style.color = '#10b981';
            msg.textContent = data.message;
            setTimeout(function() { location.reload(); }, 800);
        } else {
            msg.style.display = 'block';
            msg.style.background = 'rgba(239,68,68,0.15)';
            msg.style.color = '#ef4444';
            msg.textContent = data.message || 'Failed to add entry';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-plus"></i> Add IP';
        }
    })
    .catch(function() {
        msg.style.display = 'block';
        msg.style.background = 'rgba(239,68,68,0.15)';
        msg.style.color = '#ef4444';
        msg.textContent = 'An error occurred. Please try again.';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-plus"></i> Add IP';
    });
    return false;
}

function togglePosWhitelistEntry(entryId, newStatus) {
    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=toggle_pos_whitelist_entry&entry_id=' + entryId + '&is_active=' + newStatus + '&csrf_token=' + encodeURIComponent(posWlCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to update entry'));
        }
    })
    .catch(function() { alert('An error occurred'); });
}

function removePosWhitelistEntry(entryId) {
    if (!confirm('Are you sure you want to remove this IP from the POS whitelist?')) return;

    fetch('process_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=remove_pos_whitelist_entry&entry_id=' + entryId + '&csrf_token=' + encodeURIComponent(posWlCsrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var row = document.getElementById('pos-wl-row-' + entryId);
            if (row) {
                row.style.transition = 'opacity 0.3s';
                row.style.opacity = '0';
                setTimeout(function() { row.remove(); }, 300);
            }
        } else {
            alert('Error: ' + (data.message || 'Failed to remove entry'));
        }
    })
    .catch(function() { alert('An error occurred'); });
}
</script>

<?php endif; ?>
