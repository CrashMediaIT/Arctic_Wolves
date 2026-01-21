<?php
/**
 * Admin Database Backup Management
 * Configure automated backups and view backup history
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Fetch all backup jobs
$jobs_query = $pdo->query("
    SELECT bj.*, u.full_name as created_by_name
    FROM backup_jobs bj
    LEFT JOIN users u ON bj.created_by = u.id
    ORDER BY bj.created_at DESC
");
$jobs = $jobs_query->fetchAll(PDO::FETCH_ASSOC);

// Fetch backup history
$history_query = $pdo->query("
    SELECT bh.*, bj.job_name
    FROM backup_history bh
    LEFT JOIN backup_jobs bj ON bh.job_id = bj.id
    ORDER BY bh.backup_date DESC
    LIMIT 100
");
$history = $history_query->fetchAll(PDO::FETCH_ASSOC);

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
?>

<style>
    :root {
        --primary: #7000a4;
    }
    
    .backup-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    
    .backup-header-left h1 {
        font-size: 32px;
        font-weight: 900;
        margin: 0 0 5px 0;
    }
    
    .backup-header-left p {
        color: #94a3b8;
        font-size: 14px;
        margin: 0;
    }
    
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-create:hover {
        background: #5a0080;
        transform: translateY(-2px);
    }
    
    .tabs {
        display: flex;
        gap: 8px;
        border-bottom: 2px solid #1e293b;
        margin-bottom: 30px;
        overflow-x: auto;
    }
    
    .tab {
        padding: 12px 20px;
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
        white-space: nowrap;
    }
    
    .tab:hover {
        color: #fff;
        background: rgba(112, 0, 164, 0.1);
    }
    
    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .tab-content {
        display: none;
    }
    
    .tab-content.active {
        display: block;
    }
    
    .alert {
        padding: 15px 20px;
        border-radius: 6px;
        margin-bottom: 20px;
        display: none;
        align-items: center;
        gap: 10px;
    }
    
    .alert.show {
        display: flex;
    }
    
    .alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    
    .info-box {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        border-radius: 6px;
        padding: 15px 20px;
        margin-bottom: 20px;
    }
    
    .info-box-title {
        color: #3b82f6;
        font-weight: 700;
        font-size: 14px;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .info-box-content {
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.6;
    }
    
    .info-box-content ul {
        margin: 8px 0 0 0;
        padding-left: 20px;
    }
    
    .info-box-content li {
        margin: 4px 0;
    }
    
    .table-container {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .table thead {
        background: #06080b;
    }
    
    .table th {
        padding: 15px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 1px solid #1e293b;
    }
    
    .table td {
        padding: 15px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
        font-size: 14px;
    }
    
    .table tbody tr:hover {
        background: rgba(112, 0, 164, 0.05);
    }
    
    .table tbody tr:last-child td {
        border-bottom: none;
    }
    
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .badge-active {
        background: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border: 1px solid #00ff88;
    }
    
    .badge-inactive {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    
    .badge-success {
        background: rgba(0, 255, 136, 0.15);
        color: #00ff88;
        border: 1px solid #00ff88;
    }
    
    .badge-failed {
        background: rgba(239, 68, 68, 0.15);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    
    .badge-running {
        background: rgba(251, 191, 36, 0.15);
        color: #fbbf24;
        border: 1px solid #fbbf24;
    }
    
    .badge-nextcloud {
        background: rgba(59, 130, 246, 0.15);
        color: #3b82f6;
        border: 1px solid #3b82f6;
    }
    
    .badge-smb {
        background: rgba(168, 85, 247, 0.15);
        color: #a855f7;
        border: 1px solid #a855f7;
    }
    
    .badge-both {
        background: rgba(112, 0, 164, 0.15);
        color: var(--primary);
        border: 1px solid var(--primary);
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
    }
    
    .btn-icon {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 8px 12px;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .btn-icon:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
    
    .btn-icon.danger:hover {
        border-color: #ef4444;
        color: #ef4444;
    }
    
    .btn-icon.success:hover {
        border-color: #00ff88;
        color: #00ff88;
    }
    
    .btn-icon:disabled {
        opacity: 0.5;
        cursor: not-allowed;
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
        max-width: 700px;
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
        padding: 0;
        width: 30px;
        height: 30px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    
    .modal-close:hover {
        color: #fff;
    }
    
    .modal-body {
        padding: 25px;
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    .form-label {
        display: block;
        color: #fff;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 8px;
    }
    
    .form-label.required::after {
        content: ' *';
        color: #ef4444;
    }
    
    .form-input {
        width: 100%;
        background: #161b22;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .form-input:focus {
        outline: none;
        border-color: var(--primary);
        background: #0d1117;
    }
    
    .form-select {
        width: 100%;
        background: #161b22;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 10px 14px;
        color: #fff;
        font-size: 14px;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .form-select:focus {
        outline: none;
        border-color: var(--primary);
        background: #0d1117;
    }
    
    .form-help {
        color: #94a3b8;
        font-size: 12px;
        margin-top: 5px;
        display: block;
    }
    
    .cron-helper {
        display: flex;
        gap: 10px;
        margin-bottom: 10px;
    }
    
    .cron-helper select {
        flex: 1;
    }
    
    .smb-fields {
        display: none;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
        background: #161b22;
    }
    
    .smb-fields.show {
        display: block;
    }
    
    .smb-fields-title {
        color: #fff;
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 1px solid #1e293b;
    }
    
    .btn-test-connection {
        background: #3b82f6;
        color: #fff;
        border: none;
        padding: 8px 16px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-test-connection:hover {
        background: #2563eb;
    }
    
    .btn-test-connection:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .modal-footer {
        padding: 15px 25px;
        border-top: 1px solid #1e293b;
        display: flex;
        justify-content: flex-end;
        gap: 10px;
    }
    
    .btn-secondary {
        background: transparent;
        color: #94a3b8;
        border: 1px solid #1e293b;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-secondary:hover {
        border-color: #94a3b8;
        color: #fff;
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.2s;
    }
    
    .btn-primary:hover {
        background: #5a0080;
    }
    
    .btn-primary:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .filter-bar {
        margin-bottom: 20px;
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .filter-bar label {
        color: #94a3b8;
        font-size: 14px;
        font-weight: 600;
    }
    
    .loading-spinner {
        display: inline-block;
        width: 14px;
        height: 14px;
        border: 2px solid #1e293b;
        border-top-color: #fff;
        border-radius: 50%;
        animation: spin 0.6s linear infinite;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #94a3b8;
    }
    
    .empty-state-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .empty-state-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 8px;
        color: #fff;
    }
    
    .empty-state-text {
        font-size: 14px;
    }
</style>

<div class="backup-header">
    <div class="backup-header-left">
        <h1>Database Backups</h1>
        <p>Configure automated backups and manage backup history</p>
    </div>
    <button class="btn-create" onclick="showJobModal()">
        <span>➕</span> Create Backup Job
    </button>
</div>

<div class="alert alert-success" id="success-alert">
    <span>✓</span> <span id="success-message"></span>
</div>

<div class="alert alert-error" id="error-alert">
    <span>✗</span> <span id="error-message"></span>
</div>

<div class="tabs">
    <button class="tab active" onclick="switchTab('jobs')">Backup Jobs</button>
    <button class="tab" onclick="switchTab('history')">Backup History</button>
</div>

<!-- TAB 1: Backup Jobs -->
<div class="tab-content active" id="jobs-tab">
    <div class="info-box">
        <div class="info-box-title">
            <span>ℹ️</span> Backup Configuration
        </div>
        <div class="info-box-content">
            <strong>Backup Format:</strong> All backups are compressed and named <code>crashhockey_backup_YYYYMMDD_HHMMSS.sql.gz</code>
            <ul>
                <li><strong>Nextcloud:</strong> Backups stored in /CrashHockey/Backups/ (configurable)</li>
                <li><strong>SMB:</strong> Direct network share storage (Windows/Samba)</li>
                <li><strong>Retention:</strong> Backups older than specified days are automatically deleted</li>
            </ul>
        </div>
    </div>
    
    <?php if (empty($jobs)): ?>
        <div class="table-container">
            <div class="empty-state">
                <div class="empty-state-icon">📦</div>
                <div class="empty-state-title">No Backup Jobs</div>
                <div class="empty-state-text">Create your first backup job to get started</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Schedule</th>
                        <th>Destination</th>
                        <th>Last Backup</th>
                        <th>Next Backup</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($jobs as $job): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($job['job_name']) ?></strong>
                            </td>
                            <td>
                                <code style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($job['cron_schedule']) ?></code>
                            </td>
                            <td>
                                <?php
                                $dest = $job['destination_type'];
                                if ($dest === 'nextcloud') {
                                    echo '<span class="badge badge-nextcloud">Nextcloud</span>';
                                } elseif ($dest === 'smb') {
                                    echo '<span class="badge badge-smb">SMB</span>';
                                } else {
                                    echo '<span class="badge badge-both">Both</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php if ($job['last_backup_date']): ?>
                                    <?= date('M j, Y g:i A', strtotime($job['last_backup_date'])) ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($job['next_backup_date']): ?>
                                    <?= date('M j, Y g:i A', strtotime($job['next_backup_date'])) ?>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">Not scheduled</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= $job['status'] === 'active' ? 'active' : 'inactive' ?>">
                                    <?= ucfirst($job['status']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn-icon success" onclick="runBackupNow(<?= $job['id'] ?>)" title="Backup Now">
                                        ▶️ Run
                                    </button>
                                    <button class="btn-icon" onclick="editJob(<?= $job['id'] ?>)" title="Edit">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn-icon" onclick="toggleJobStatus(<?= $job['id'] ?>, '<?= $job['status'] ?>')" title="Toggle Status">
                                        <?= $job['status'] === 'active' ? '⏸️ Pause' : '▶️ Activate' ?>
                                    </button>
                                    <button class="btn-icon danger" onclick="deleteJob(<?= $job['id'] ?>)" title="Delete">
                                        🗑️ Delete
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- TAB 2: Backup History -->
<div class="tab-content" id="history-tab">
    <div class="filter-bar">
        <label for="status-filter">Filter by Status:</label>
        <select id="status-filter" class="form-select" style="width: auto;" onchange="filterHistory()">
            <option value="all">All</option>
            <option value="success">Success</option>
            <option value="failed">Failed</option>
            <option value="running">Running</option>
        </select>
    </div>
    
    <?php if (empty($history)): ?>
        <div class="table-container">
            <div class="empty-state">
                <div class="empty-state-icon">📋</div>
                <div class="empty-state-title">No Backup History</div>
                <div class="empty-state-text">Backup history will appear here once backups are run</div>
            </div>
        </div>
    <?php else: ?>
        <div class="table-container">
            <table class="table" id="history-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Job Name</th>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>Destination</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr data-status="<?= htmlspecialchars($item['status']) ?>">
                            <td><?= date('M j, Y g:i A', strtotime($item['backup_date'])) ?></td>
                            <td><?= htmlspecialchars($item['job_name'] ?? 'Manual') ?></td>
                            <td>
                                <code style="font-size: 12px; color: #94a3b8;"><?= htmlspecialchars($item['filename']) ?></code>
                            </td>
                            <td>
                                <?php
                                if ($item['file_size']) {
                                    $size = $item['file_size'];
                                    $units = ['B', 'KB', 'MB', 'GB'];
                                    $i = 0;
                                    while ($size >= 1024 && $i < count($units) - 1) {
                                        $size /= 1024;
                                        $i++;
                                    }
                                    echo number_format($size, 2) . ' ' . $units[$i];
                                } else {
                                    echo '<span style="color: #94a3b8;">-</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <?php
                                $dest = $item['destination'];
                                if ($dest === 'nextcloud') {
                                    echo '<span class="badge badge-nextcloud">Nextcloud</span>';
                                } elseif ($dest === 'smb') {
                                    echo '<span class="badge badge-smb">SMB</span>';
                                } else {
                                    echo '<span class="badge badge-both">Both</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= htmlspecialchars($item['status']) ?>">
                                    <?= ucfirst($item['status']) ?>
                                </span>
                                <?php if ($item['status'] === 'failed' && $item['error_message']): ?>
                                    <br><small style="color: #ef4444; font-size: 11px;" title="<?= htmlspecialchars($item['error_message']) ?>">
                                        <?= htmlspecialchars(substr($item['error_message'], 0, 50)) ?><?= strlen($item['error_message']) > 50 ? '...' : '' ?>
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['status'] === 'success' && $item['file_path']): ?>
                                    <button class="btn-icon" onclick="downloadBackup('<?= htmlspecialchars($item['filename']) ?>')" title="Download">
                                        ⬇️ Download
                                    </button>
                                <?php else: ?>
                                    <span style="color: #94a3b8; font-size: 12px;">N/A</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal for Create/Edit Backup Job -->
<div class="modal" id="job-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modal-title">Create Backup Job</h2>
            <button class="modal-close" onclick="closeJobModal()">&times;</button>
        </div>
        <form id="job-form" onsubmit="saveJob(event)">
            <div class="modal-body">
                <input type="hidden" id="job-id" name="job_id">
                <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                
                <div class="form-group">
                    <label class="form-label required" for="job-name">Job Name</label>
                    <input type="text" id="job-name" name="job_name" class="form-input" required placeholder="Daily Backup">
                    <span class="form-help">A descriptive name for this backup job</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label required" for="cron-schedule">Schedule</label>
                    <div class="cron-helper">
                        <select id="cron-preset" class="form-select" onchange="applyCronPreset()">
                            <option value="">Select a preset...</option>
                            <option value="0 2 * * *">Daily at 2:00 AM</option>
                            <option value="0 3 * * 0">Weekly (Sunday at 3:00 AM)</option>
                            <option value="0 4 1 * *">Monthly (1st at 4:00 AM)</option>
                            <option value="0 */6 * * *">Every 6 hours</option>
                            <option value="0 */12 * * *">Every 12 hours</option>
                            <option value="custom">Custom...</option>
                        </select>
                    </div>
                    <input type="text" id="cron-schedule" name="cron_schedule" class="form-input" required placeholder="0 2 * * *">
                    <span class="form-help">Cron expression (minute hour day month weekday)</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label required" for="destination-type">Destination Type</label>
                    <select id="destination-type" name="destination_type" class="form-select" required onchange="toggleSmbFields()">
                        <option value="nextcloud">Nextcloud</option>
                        <option value="smb">SMB Network Share</option>
                        <option value="both">Both</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="nextcloud-folder">Nextcloud Folder</label>
                    <input type="text" id="nextcloud-folder" name="nextcloud_folder" class="form-input" value="/CrashHockey/Backups/" placeholder="/CrashHockey/Backups/">
                    <span class="form-help">Path in Nextcloud where backups will be stored</span>
                </div>
                
                <!-- SMB Fields (conditionally shown) -->
                <div id="smb-fields" class="smb-fields">
                    <div class="smb-fields-title">SMB Configuration</div>
                    
                    <div class="form-group">
                        <label class="form-label" for="smb-path">SMB Path</label>
                        <input type="text" id="smb-path" name="smb_path" class="form-input" placeholder="//server/share/backups">
                        <span class="form-help">UNC path to network share</span>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="smb-username">SMB Username</label>
                        <input type="text" id="smb-username" name="smb_username" class="form-input" placeholder="username">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="smb-password">SMB Password</label>
                        <input type="password" id="smb-password" name="smb_password" class="form-input" placeholder="••••••••">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="smb-domain">SMB Domain</label>
                        <input type="text" id="smb-domain" name="smb_domain" class="form-input" placeholder="WORKGROUP">
                        <span class="form-help">Optional domain/workgroup</span>
                    </div>
                    
                    <button type="button" class="btn-test-connection" onclick="testSmbConnection()">
                        🔌 Test SMB Connection
                    </button>
                    <span id="smb-test-result" style="margin-left: 10px; font-size: 13px;"></span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="retention-days">Retention Days</label>
                    <input type="number" id="retention-days" name="retention_days" class="form-input" value="30" min="1" max="365">
                    <span class="form-help">Delete backups older than this many days</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="status">Status</label>
                    <select id="status" name="status" class="form-select">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeJobModal()">Cancel</button>
                <button type="submit" class="btn-primary" id="save-btn">Create Job</button>
            </div>
        </form>
    </div>
</div>

<script>
// Tab switching
function switchTab(tab) {
    document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
}

// Show/hide alerts
function showAlert(type, message) {
    const alertId = type === 'success' ? 'success-alert' : 'error-alert';
    const messageId = type === 'success' ? 'success-message' : 'error-message';
    
    document.getElementById(messageId).textContent = message;
    document.getElementById(alertId).classList.add('show');
    
    setTimeout(() => {
        document.getElementById(alertId).classList.remove('show');
    }, 5000);
}

// Modal functions
function showJobModal(jobId = null) {
    document.getElementById('job-modal').classList.add('show');
    document.getElementById('job-form').reset();
    
    if (jobId) {
        document.getElementById('modal-title').textContent = 'Edit Backup Job';
        document.getElementById('save-btn').textContent = 'Update Job';
        loadJobData(jobId);
    } else {
        document.getElementById('modal-title').textContent = 'Create Backup Job';
        document.getElementById('save-btn').textContent = 'Create Job';
        document.getElementById('job-id').value = '';
    }
    
    toggleSmbFields();
}

function closeJobModal() {
    document.getElementById('job-modal').classList.remove('show');
}

// Toggle SMB fields based on destination type
function toggleSmbFields() {
    const destType = document.getElementById('destination-type').value;
    const smbFields = document.getElementById('smb-fields');
    
    if (destType === 'smb' || destType === 'both') {
        smbFields.classList.add('show');
    } else {
        smbFields.classList.remove('show');
    }
}

// Apply cron preset
function applyCronPreset() {
    const preset = document.getElementById('cron-preset').value;
    if (preset && preset !== 'custom') {
        document.getElementById('cron-schedule').value = preset;
    }
}

// Load job data for editing
function loadJobData(jobId) {
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=get_job&job_id=${jobId}&csrf_token=<?= $csrf_token ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const job = data.job;
            document.getElementById('job-id').value = job.id;
            document.getElementById('job-name').value = job.job_name;
            document.getElementById('cron-schedule').value = job.cron_schedule;
            document.getElementById('destination-type').value = job.destination_type;
            document.getElementById('nextcloud-folder').value = job.nextcloud_folder || '';
            document.getElementById('smb-path').value = job.smb_path || '';
            document.getElementById('smb-username').value = job.smb_username || '';
            document.getElementById('smb-domain').value = job.smb_domain || '';
            document.getElementById('retention-days').value = job.retention_days;
            document.getElementById('status').value = job.status;
            toggleSmbFields();
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Failed to load job data');
        console.error(error);
    });
}

// Save job (create or update)
function saveJob(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    const jobId = document.getElementById('job-id').value;
    formData.append('action', jobId ? 'update_job' : 'create_job');
    
    const saveBtn = document.getElementById('save-btn');
    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="loading-spinner"></span> Saving...';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            closeJobModal();
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Failed to save job');
        console.error(error);
    })
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.textContent = jobId ? 'Update Job' : 'Create Job';
    });
}

// Edit job
function editJob(jobId) {
    showJobModal(jobId);
}

// Delete job
function deleteJob(jobId) {
    if (!confirm('Are you sure you want to delete this backup job?')) {
        return;
    }
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=delete_job&job_id=${jobId}&csrf_token=<?= $csrf_token ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Failed to delete job');
        console.error(error);
    });
}

// Toggle job status
function toggleJobStatus(jobId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=toggle_status&job_id=${jobId}&status=${newStatus}&csrf_token=<?= $csrf_token ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert('error', data.message);
        }
    })
    .catch(error => {
        showAlert('error', 'Failed to toggle status');
        console.error(error);
    });
}

// Run backup now
function runBackupNow(jobId) {
    if (!confirm('Run this backup job now?')) {
        return;
    }
    
    const btn = event.target;
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Running...';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=run_backup&job_id=${jobId}&csrf_token=<?= $csrf_token ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('success', data.message);
            setTimeout(() => location.reload(), 2000);
        } else {
            showAlert('error', data.message);
            btn.disabled = false;
            btn.innerHTML = '▶️ Run';
        }
    })
    .catch(error => {
        showAlert('error', 'Failed to run backup');
        console.error(error);
        btn.disabled = false;
        btn.innerHTML = '▶️ Run';
    });
}

// Test SMB connection
function testSmbConnection() {
    const btn = event.target;
    const resultSpan = document.getElementById('smb-test-result');
    
    const smbPath = document.getElementById('smb-path').value;
    const smbUsername = document.getElementById('smb-username').value;
    const smbPassword = document.getElementById('smb-password').value;
    const smbDomain = document.getElementById('smb-domain').value;
    
    if (!smbPath) {
        resultSpan.style.color = '#ef4444';
        resultSpan.textContent = '⚠️ Please enter SMB path';
        return;
    }
    
    btn.disabled = true;
    btn.innerHTML = '<span class="loading-spinner"></span> Testing...';
    resultSpan.textContent = '';
    
    fetch('process_database_backup.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=test_smb&smb_path=${encodeURIComponent(smbPath)}&smb_username=${encodeURIComponent(smbUsername)}&smb_password=${encodeURIComponent(smbPassword)}&smb_domain=${encodeURIComponent(smbDomain)}&csrf_token=<?= $csrf_token ?>`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultSpan.style.color = '#00ff88';
            resultSpan.textContent = '✓ Connection successful';
        } else {
            resultSpan.style.color = '#ef4444';
            resultSpan.textContent = '✗ ' + data.message;
        }
    })
    .catch(error => {
        resultSpan.style.color = '#ef4444';
        resultSpan.textContent = '✗ Connection failed';
        console.error(error);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '🔌 Test SMB Connection';
    });
}

// Filter backup history
function filterHistory() {
    const filter = document.getElementById('status-filter').value;
    const rows = document.querySelectorAll('#history-table tbody tr');
    
    rows.forEach(row => {
        const status = row.getAttribute('data-status');
        if (filter === 'all' || status === filter) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// Download backup
function downloadBackup(filename) {
    window.location.href = `process_database_backup.php?action=download&filename=${encodeURIComponent(filename)}&csrf_token=<?= $csrf_token ?>`;
}

// Close modal on outside click
document.getElementById('job-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeJobModal();
    }
});
</script>
