<?php
/**
 * Admin Database Restore
 * Wizard-style interface for restoring database from backup files
 * WITH MULTIPLE SAFEGUARDS AGAINST DATA LOSS
 */

require_once __DIR__ . '/../security.php';

// Check if user is admin
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Fetch available backups from Nextcloud/SMB if configured
$available_backups = [];
try {
    $settings_query = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('nextcloud_enabled', 'nextcloud_backup_path', 'smb_enabled', 'smb_backup_path')");
    $settings = [];
    while ($row = $settings_query->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Get recent backups from backup_history table
    $recent_backups_query = $pdo->query("
        SELECT bh.*, bj.job_name, bj.storage_location
        FROM backup_history bh
        LEFT JOIN backup_jobs bj ON bh.job_id = bj.id
        WHERE bh.status = 'completed'
        ORDER BY bh.backup_date DESC
        LIMIT 20
    ");
    $available_backups = $recent_backups_query->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Silently fail if tables don't exist
}
?>

<style>
    :root {
        --primary: #7000a4;
        --danger: #dc2626;
        --warning: #f59e0b;
        --success: #10b981;
    }
    
    .restore-header {
        margin-bottom: 30px;
    }
    
    .restore-header h1 {
        font-size: 32px;
        font-weight: 900;
        margin: 0 0 8px 0;
        color: #fff;
    }
    
    .restore-header p {
        color: #94a3b8;
        font-size: 14px;
        margin: 0;
    }
    
    .danger-banner {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.2), rgba(220, 38, 38, 0.1));
        border: 2px solid var(--danger);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        align-items: start;
        gap: 15px;
    }
    
    .danger-banner-icon {
        font-size: 32px;
        flex-shrink: 0;
    }
    
    .danger-banner-content h3 {
        color: var(--danger);
        font-size: 18px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }
    
    .danger-banner-content p {
        color: #e2e8f0;
        font-size: 14px;
        margin: 0;
        line-height: 1.6;
    }
    
    /* Wizard Steps */
    .wizard-steps {
        display: flex;
        justify-content: space-between;
        margin-bottom: 40px;
        position: relative;
    }
    
    .wizard-steps::before {
        content: '';
        position: absolute;
        top: 20px;
        left: 40px;
        right: 40px;
        height: 2px;
        background: #1e293b;
        z-index: 0;
    }
    
    .wizard-step {
        flex: 1;
        text-align: center;
        position: relative;
        z-index: 1;
    }
    
    .wizard-step-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: #1e293b;
        border: 2px solid #1e293b;
        color: #64748b;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 10px;
        font-weight: 700;
        transition: all 0.3s;
    }
    
    .wizard-step.active .wizard-step-circle {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
        box-shadow: 0 0 0 4px rgba(112, 0, 164, 0.2);
    }
    
    .wizard-step.completed .wizard-step-circle {
        background: var(--success);
        border-color: var(--success);
        color: #fff;
    }
    
    .wizard-step-circle i {
        font-size: 18px;
    }
    
    .wizard-step-label {
        font-size: 13px;
        font-weight: 600;
        color: #64748b;
        transition: color 0.3s;
    }
    
    .wizard-step.active .wizard-step-label {
        color: var(--primary);
    }
    
    .wizard-step.completed .wizard-step-label {
        color: var(--success);
    }
    
    /* Wizard Content */
    .wizard-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 40px;
        min-height: 400px;
    }
    
    .step-content {
        display: none;
    }
    
    .step-content.active {
        display: block;
    }
    
    .step-title {
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 10px 0;
        color: #fff;
    }
    
    .step-description {
        color: #94a3b8;
        font-size: 14px;
        margin: 0 0 30px 0;
        line-height: 1.6;
    }
    
    /* Upload Area */
    .upload-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 25px;
        border-bottom: 2px solid #1e293b;
    }
    
    .upload-tab {
        padding: 12px 20px;
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
    }
    
    .upload-tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    
    .upload-tab:hover:not(.active) {
        color: #fff;
    }
    
    .upload-tab-content {
        display: none;
    }
    
    .upload-tab-content.active {
        display: block;
    }
    
    .dropzone {
        border: 3px dashed #1e293b;
        border-radius: 8px;
        padding: 60px 30px;
        text-align: center;
        transition: all 0.3s;
        cursor: pointer;
        background: #0a0d12;
    }
    
    .dropzone:hover,
    .dropzone.drag-over {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.05);
    }
    
    .dropzone-icon {
        font-size: 48px;
        color: #64748b;
        margin-bottom: 15px;
    }
    
    .dropzone.drag-over .dropzone-icon {
        color: var(--primary);
    }
    
    .dropzone-text {
        font-size: 16px;
        font-weight: 600;
        color: #e2e8f0;
        margin-bottom: 8px;
    }
    
    .dropzone-subtext {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 20px;
    }
    
    .dropzone-browse {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 10px 24px;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .dropzone-browse:hover {
        background: #5a0083;
    }
    
    .file-input {
        display: none;
    }
    
    .file-selected {
        margin-top: 20px;
        padding: 15px;
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid var(--success);
        border-radius: 6px;
        display: none;
    }
    
    .file-selected.show {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .file-selected i {
        color: var(--success);
        font-size: 20px;
    }
    
    .file-selected-info {
        flex: 1;
    }
    
    .file-selected-name {
        font-weight: 600;
        color: #fff;
        margin-bottom: 3px;
    }
    
    .file-selected-size {
        font-size: 12px;
        color: #94a3b8;
    }
    
    .file-selected-remove {
        background: none;
        border: none;
        color: var(--danger);
        cursor: pointer;
        font-size: 18px;
        padding: 5px;
        transition: all 0.2s;
    }
    
    .file-selected-remove:hover {
        color: #ef4444;
    }
    
    /* Available Backups List */
    .backups-list {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .backup-item {
        background: #0a0d12;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .backup-item:hover {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.05);
    }
    
    .backup-item.selected {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .backup-item-icon {
        font-size: 24px;
        color: #64748b;
    }
    
    .backup-item.selected .backup-item-icon {
        color: var(--primary);
    }
    
    .backup-item-info {
        flex: 1;
    }
    
    .backup-item-name {
        font-weight: 600;
        color: #fff;
        margin-bottom: 3px;
    }
    
    .backup-item-meta {
        font-size: 12px;
        color: #64748b;
    }
    
    .backup-item-radio {
        width: 20px;
        height: 20px;
        accent-color: var(--primary);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    /* Verification Stats */
    .verification-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: #0a0d12;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 20px;
        text-align: center;
    }
    
    .stat-icon {
        font-size: 32px;
        color: var(--primary);
        margin-bottom: 10px;
    }
    
    .stat-value {
        font-size: 28px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 5px;
    }
    
    .stat-label {
        font-size: 13px;
        color: #64748b;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .warning-box {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.1));
        border: 2px solid var(--warning);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        align-items: start;
        gap: 15px;
    }
    
    .warning-box-icon {
        font-size: 24px;
        color: var(--warning);
        flex-shrink: 0;
    }
    
    .warning-box-content h4 {
        color: var(--warning);
        font-size: 16px;
        font-weight: 700;
        margin: 0 0 8px 0;
    }
    
    .warning-box-content p {
        color: #e2e8f0;
        font-size: 14px;
        margin: 0;
        line-height: 1.6;
    }
    
    /* Confirmation Box */
    .confirmation-box {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.3), rgba(220, 38, 38, 0.2));
        border: 3px solid var(--danger);
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 25px;
    }
    
    .confirmation-box-icon {
        font-size: 64px;
        color: var(--danger);
        text-align: center;
        margin-bottom: 20px;
    }
    
    .confirmation-box h3 {
        color: var(--danger);
        font-size: 24px;
        font-weight: 900;
        text-align: center;
        margin: 0 0 15px 0;
        text-transform: uppercase;
        letter-spacing: 1px;
    }
    
    .confirmation-box-warnings {
        background: rgba(0, 0, 0, 0.3);
        border-radius: 6px;
        padding: 20px;
        margin-bottom: 25px;
    }
    
    .confirmation-box-warnings ul {
        margin: 0;
        padding-left: 20px;
        color: #e2e8f0;
        line-height: 2;
    }
    
    .confirmation-box-warnings li {
        font-size: 14px;
    }
    
    .confirmation-checkbox {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 20px;
        background: rgba(0, 0, 0, 0.3);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .confirmation-checkbox:hover {
        background: rgba(0, 0, 0, 0.4);
    }
    
    .confirmation-checkbox input[type="checkbox"] {
        width: 24px;
        height: 24px;
        accent-color: var(--danger);
        cursor: pointer;
    }
    
    .confirmation-checkbox label {
        flex: 1;
        font-size: 15px;
        font-weight: 600;
        color: #fff;
        cursor: pointer;
    }
    
    /* Progress */
    .progress-container {
        display: none;
        margin-top: 30px;
    }
    
    .progress-container.show {
        display: block;
    }
    
    .progress-bar-wrapper {
        background: #1e293b;
        border-radius: 8px;
        height: 40px;
        overflow: hidden;
        margin-bottom: 15px;
        position: relative;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary), #9333ea);
        transition: width 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-weight: 700;
        font-size: 14px;
    }
    
    .progress-status {
        text-align: center;
        color: #94a3b8;
        font-size: 14px;
    }
    
    .progress-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #1e293b;
        border-top-color: var(--primary);
        border-radius: 50%;
        animation: spin 0.8s linear infinite;
        margin-right: 8px;
    }
    
    @keyframes spin {
        to { transform: rotate(360deg); }
    }
    
    /* Results */
    .results-container {
        display: none;
    }
    
    .results-container.show {
        display: block;
    }
    
    .result-success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.1));
        border: 2px solid var(--success);
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        margin-bottom: 25px;
    }
    
    .result-success-icon {
        font-size: 64px;
        color: var(--success);
        margin-bottom: 15px;
    }
    
    .result-success h3 {
        color: var(--success);
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 10px 0;
    }
    
    .result-success p {
        color: #e2e8f0;
        font-size: 14px;
        margin: 0;
    }
    
    .result-error {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.2), rgba(220, 38, 38, 0.1));
        border: 2px solid var(--danger);
        border-radius: 8px;
        padding: 30px;
        text-align: center;
        margin-bottom: 25px;
    }
    
    .result-error-icon {
        font-size: 64px;
        color: var(--danger);
        margin-bottom: 15px;
    }
    
    .result-error h3 {
        color: var(--danger);
        font-size: 24px;
        font-weight: 700;
        margin: 0 0 10px 0;
    }
    
    .result-error p {
        color: #e2e8f0;
        font-size: 14px;
        margin: 0;
    }
    
    .result-details {
        background: #0a0d12;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .result-details h4 {
        color: #fff;
        font-size: 14px;
        font-weight: 700;
        margin: 0 0 15px 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .result-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .result-stat-item {
        text-align: center;
    }
    
    .result-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 3px;
    }
    
    .result-stat-label {
        font-size: 12px;
        color: #64748b;
    }
    
    .result-errors {
        max-height: 300px;
        overflow-y: auto;
        background: rgba(220, 38, 38, 0.1);
        border: 1px solid var(--danger);
        border-radius: 6px;
        padding: 15px;
    }
    
    .result-error-item {
        font-family: monospace;
        font-size: 12px;
        color: #fca5a5;
        margin-bottom: 8px;
        line-height: 1.5;
    }
    
    .result-error-item:last-child {
        margin-bottom: 0;
    }
    
    /* Buttons */
    .wizard-actions {
        display: flex;
        gap: 15px;
        margin-top: 40px;
        justify-content: space-between;
    }
    
    .btn {
        padding: 12px 30px;
        border: none;
        border-radius: 6px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    
    .btn-primary:hover:not(:disabled) {
        background: #5a0083;
    }
    
    .btn-danger {
        background: var(--danger);
        color: #fff;
    }
    
    .btn-danger:hover:not(:disabled) {
        background: #b91c1c;
    }
    
    .btn-secondary {
        background: #1e293b;
        color: #fff;
    }
    
    .btn-secondary:hover {
        background: #334155;
    }
    
    .btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .btn i {
        font-size: 16px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .wizard-steps::before {
            display: none;
        }
        
        .wizard-steps {
            flex-direction: column;
            gap: 15px;
        }
        
        .wizard-step {
            display: flex;
            align-items: center;
            gap: 15px;
            text-align: left;
        }
        
        .wizard-step-circle {
            margin: 0;
        }
        
        .verification-stats {
            grid-template-columns: 1fr;
        }
        
        .wizard-content {
            padding: 25px;
        }
    }
</style>

<div class="restore-header">
    <h1>🔄 Database Restore</h1>
    <p>Restore your database from a backup file</p>
</div>

<div class="danger-banner">
    <div class="danger-banner-icon">⚠️</div>
    <div class="danger-banner-content">
        <h3>CRITICAL: This Operation Will Overwrite All Data</h3>
        <p>Database restoration is a destructive operation that will replace ALL current data with the backup. This action cannot be undone. Please ensure you have a current backup before proceeding.</p>
    </div>
</div>

<!-- Wizard Steps -->
<div class="wizard-steps">
    <div class="wizard-step active" data-step="1">
        <div class="wizard-step-circle">
            <i class="fas fa-upload"></i>
        </div>
        <div class="wizard-step-label">Upload Backup</div>
    </div>
    <div class="wizard-step" data-step="2">
        <div class="wizard-step-circle">
            <i class="fas fa-search"></i>
        </div>
        <div class="wizard-step-label">Verify Contents</div>
    </div>
    <div class="wizard-step" data-step="3">
        <div class="wizard-step-circle">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div class="wizard-step-label">Confirm & Restore</div>
    </div>
</div>

<!-- Wizard Content -->
<div class="wizard-content">
    <!-- Step 1: Upload Backup -->
    <div class="step-content active" data-step="1">
        <h2 class="step-title">📤 Step 1: Upload Backup File</h2>
        <p class="step-description">Choose a backup file to restore. You can upload a new file or select from available backups.</p>
        
        <div class="upload-tabs">
            <button class="upload-tab active" onclick="switchUploadTab('upload')">
                <i class="fas fa-upload"></i> Upload File
            </button>
            <button class="upload-tab" onclick="switchUploadTab('available')">
                <i class="fas fa-database"></i> Available Backups (<?= count($available_backups) ?>)
            </button>
        </div>
        
        <!-- Upload Tab -->
        <div class="upload-tab-content active" id="upload-tab">
            <div class="dropzone" id="dropzone">
                <div class="dropzone-icon">📁</div>
                <div class="dropzone-text">Drag & Drop Your Backup File</div>
                <div class="dropzone-subtext">Supported formats: .sql, .sql.gz (Max 500MB)</div>
                <label class="dropzone-browse" for="backupFile">
                    <i class="fas fa-folder-open"></i> Browse Files
                </label>
                <input type="file" id="backupFile" class="file-input" accept=".sql,.gz">
            </div>
            
            <div class="file-selected" id="fileSelected">
                <i class="fas fa-file-archive"></i>
                <div class="file-selected-info">
                    <div class="file-selected-name" id="fileName"></div>
                    <div class="file-selected-size" id="fileSize"></div>
                </div>
                <button class="file-selected-remove" onclick="removeFile()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        
        <!-- Available Backups Tab -->
        <div class="upload-tab-content" id="available-tab">
            <?php if (count($available_backups) > 0): ?>
                <div class="backups-list" id="backupsList">
                    <?php foreach ($available_backups as $backup): ?>
                        <div class="backup-item" onclick="selectBackup(this, '<?= htmlspecialchars($backup['file_path'] ?? '', ENT_QUOTES) ?>', '<?= htmlspecialchars($backup['file_name'] ?? '', ENT_QUOTES) ?>', '<?= $backup['file_size'] ?? 0 ?>')">
                            <div class="backup-item-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="backup-item-info">
                                <div class="backup-item-name"><?= htmlspecialchars($backup['job_name'] ?? 'Manual Backup', ENT_QUOTES) ?></div>
                                <div class="backup-item-meta">
                                    <?= date('M j, Y g:i A', strtotime($backup['backup_date'])) ?> • 
                                    <?= isset($backup['file_size']) ? number_format($backup['file_size'] / 1024 / 1024, 2) . ' MB' : 'Unknown size' ?> •
                                    <?= htmlspecialchars($backup['storage_location'] ?? 'local', ENT_QUOTES) ?>
                                </div>
                            </div>
                            <input type="radio" name="selectedBackup" class="backup-item-radio">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>No backups available. Create a backup first or upload a file.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="wizard-actions">
            <div></div>
            <button class="btn btn-primary" id="step1Next" onclick="proceedToVerify()" disabled>
                Next: Verify Backup <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
    
    <!-- Step 2: Verify Contents -->
    <div class="step-content" data-step="2">
        <h2 class="step-title">🔍 Step 2: Verify Backup Contents</h2>
        <p class="step-description">Review the backup file statistics before proceeding with the restore.</p>
        
        <div class="verification-stats" id="verificationStats">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-table"></i></div>
                <div class="stat-value" id="tableCount">-</div>
                <div class="stat-label">Tables</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-database"></i></div>
                <div class="stat-value" id="insertCount">-</div>
                <div class="stat-label">Insert Statements</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file"></i></div>
                <div class="stat-value" id="backupSize">-</div>
                <div class="stat-label">File Size</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-calendar"></i></div>
                <div class="stat-value" id="backupDate">-</div>
                <div class="stat-label">Backup Date</div>
            </div>
        </div>
        
        <div class="warning-box">
            <div class="warning-box-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="warning-box-content">
                <h4>Important Information</h4>
                <p>The statistics above show what's contained in the backup file. Proceeding with the restore will replace all current database tables with the data from this backup. Make sure this is the correct backup before continuing.</p>
            </div>
        </div>
        
        <div class="wizard-actions">
            <button class="btn btn-secondary" onclick="goToStep(1)">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button class="btn btn-primary" onclick="goToStep(3)">
                Proceed to Restore <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
    
    <!-- Step 3: Confirm & Restore -->
    <div class="step-content" data-step="3">
        <h2 class="step-title">⚠️ Step 3: Confirm & Restore Database</h2>
        <p class="step-description">Final step: Confirm that you understand the risks and initiate the restore.</p>
        
        <div class="confirmation-box">
            <div class="confirmation-box-icon">🚨</div>
            <h3>⚠️ WARNING: DESTRUCTIVE OPERATION ⚠️</h3>
            
            <div class="confirmation-box-warnings">
                <ul>
                    <li><strong>ALL CURRENT DATA WILL BE DELETED</strong></li>
                    <li>All tables will be dropped and recreated from backup</li>
                    <li>This action CANNOT be undone</li>
                    <li>Users may be logged out during the restore</li>
                    <li>The application will be unavailable during restore</li>
                    <li>Ensure you have a current backup before proceeding</li>
                </ul>
            </div>
            
            <div class="confirmation-checkbox" onclick="toggleConfirmation()">
                <input type="checkbox" id="confirmCheckbox" onchange="updateRestoreButton()">
                <label for="confirmCheckbox">
                    I understand this will REPLACE ALL CURRENT DATA and cannot be undone
                </label>
            </div>
        </div>
        
        <!-- Progress Container -->
        <div class="progress-container" id="progressContainer">
            <div class="progress-bar-wrapper">
                <div class="progress-bar" id="progressBar" style="width: 0%;">0%</div>
            </div>
            <div class="progress-status" id="progressStatus">
                <span class="progress-spinner"></span>
                Preparing restore...
            </div>
        </div>
        
        <!-- Results Container -->
        <div class="results-container" id="resultsContainer">
            <!-- Success Result -->
            <div class="result-success" id="resultSuccess" style="display: none;">
                <div class="result-success-icon"><i class="fas fa-check-circle"></i></div>
                <h3>✅ Database Restored Successfully!</h3>
                <p>Your database has been restored from the backup file.</p>
            </div>
            
            <!-- Error Result -->
            <div class="result-error" id="resultError" style="display: none;">
                <div class="result-error-icon"><i class="fas fa-times-circle"></i></div>
                <h3>❌ Restore Failed</h3>
                <p id="resultErrorMessage">An error occurred during the restore process.</p>
            </div>
            
            <!-- Details -->
            <div class="result-details" id="resultDetails" style="display: none;">
                <h4>Restore Statistics</h4>
                <div class="result-stats">
                    <div class="result-stat-item">
                        <div class="result-stat-value" id="resultSuccessCount">0</div>
                        <div class="result-stat-label">Successful</div>
                    </div>
                    <div class="result-stat-item">
                        <div class="result-stat-value" id="resultFailedCount">0</div>
                        <div class="result-stat-label">Failed</div>
                    </div>
                    <div class="result-stat-item">
                        <div class="result-stat-value" id="resultDuration">0s</div>
                        <div class="result-stat-label">Duration</div>
                    </div>
                </div>
                
                <div id="resultErrorsContainer" style="display: none;">
                    <h4 style="margin-top: 20px; color: var(--danger);">Errors</h4>
                    <div class="result-errors" id="resultErrorsList"></div>
                </div>
            </div>
        </div>
        
        <div class="wizard-actions">
            <button class="btn btn-secondary" id="step3Back" onclick="goToStep(2)">
                <i class="fas fa-arrow-left"></i> Back
            </button>
            <button class="btn btn-danger" id="restoreButton" onclick="startRestore()" disabled>
                <i class="fas fa-exclamation-triangle"></i> RESTORE DATABASE NOW
            </button>
            <button class="btn btn-primary" id="finishButton" onclick="finishRestore()" style="display: none;">
                <i class="fas fa-check"></i> Done
            </button>
        </div>
    </div>
</div>

<script>
let currentStep = 1;
let selectedFile = null;
let selectedBackupPath = null;
let backupMetadata = null;

// File upload handling
const dropzone = document.getElementById('dropzone');
const fileInput = document.getElementById('backupFile');
const fileSelected = document.getElementById('fileSelected');

// Drag and drop events
dropzone.addEventListener('dragover', (e) => {
    e.preventDefault();
    dropzone.classList.add('drag-over');
});

dropzone.addEventListener('dragleave', () => {
    dropzone.classList.remove('drag-over');
});

dropzone.addEventListener('drop', (e) => {
    e.preventDefault();
    dropzone.classList.remove('drag-over');
    
    const files = e.dataTransfer.files;
    if (files.length > 0) {
        handleFileSelect(files[0]);
    }
});

// Click to browse
dropzone.addEventListener('click', (e) => {
    if (e.target === dropzone || e.target.closest('.dropzone') && !e.target.closest('label')) {
        fileInput.click();
    }
});

fileInput.addEventListener('change', (e) => {
    if (e.target.files.length > 0) {
        handleFileSelect(e.target.files[0]);
    }
});

function handleFileSelect(file) {
    // Validate file type
    const validExtensions = ['.sql', '.gz'];
    const fileName = file.name.toLowerCase();
    const isValid = validExtensions.some(ext => fileName.endsWith(ext));
    
    if (!isValid) {
        alert('Invalid file type. Please select a .sql or .sql.gz file.');
        return;
    }
    
    // Validate file size (500MB max)
    const maxSize = 500 * 1024 * 1024;
    if (file.size > maxSize) {
        alert('File is too large. Maximum size is 500MB.');
        return;
    }
    
    selectedFile = file;
    selectedBackupPath = null; // Clear any selected backup from list
    
    // Update UI
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatBytes(file.size);
    fileSelected.classList.add('show');
    document.getElementById('step1Next').disabled = false;
    
    // Clear backup list selection
    document.querySelectorAll('.backup-item').forEach(item => {
        item.classList.remove('selected');
        item.querySelector('input[type="radio"]').checked = false;
    });
}

function removeFile() {
    selectedFile = null;
    fileSelected.classList.remove('show');
    fileInput.value = '';
    document.getElementById('step1Next').disabled = selectedBackupPath === null;
}

function switchUploadTab(tab) {
    // Update tabs
    document.querySelectorAll('.upload-tab').forEach(t => t.classList.remove('active'));
    document.querySelectorAll('.upload-tab-content').forEach(c => c.classList.remove('active'));
    
    event.target.classList.add('active');
    document.getElementById(tab + '-tab').classList.add('active');
}

function selectBackup(element, path, name, size) {
    // Update selection
    document.querySelectorAll('.backup-item').forEach(item => {
        item.classList.remove('selected');
        item.querySelector('input[type="radio"]').checked = false;
    });
    
    element.classList.add('selected');
    element.querySelector('input[type="radio"]').checked = true;
    
    selectedBackupPath = path;
    selectedFile = null; // Clear uploaded file
    
    // Clear file upload UI
    fileSelected.classList.remove('show');
    fileInput.value = '';
    
    // Enable next button
    document.getElementById('step1Next').disabled = false;
}

function proceedToVerify() {
    if (!selectedFile && !selectedBackupPath) {
        alert('Please select a backup file first.');
        return;
    }
    
    // Upload file if needed and analyze
    const formData = new FormData();
    formData.append('csrf_token', '<?= $csrf_token ?>');
    formData.append('action', 'analyze_backup');
    
    if (selectedFile) {
        formData.append('backup_file', selectedFile);
    } else {
        formData.append('backup_path', selectedBackupPath);
    }
    
    // Show loading state
    const nextBtn = document.getElementById('step1Next');
    const originalText = nextBtn.innerHTML;
    nextBtn.disabled = true;
    nextBtn.innerHTML = '<span class="progress-spinner"></span> Analyzing...';
    
    fetch('process_database_restore.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        nextBtn.innerHTML = originalText;
        
        if (data.success) {
            backupMetadata = data.metadata;
            updateVerificationStats(data.metadata);
            goToStep(2);
        } else {
            alert('Error analyzing backup: ' + (data.message || 'Unknown error'));
            nextBtn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to analyze backup file. Please try again.');
        nextBtn.innerHTML = originalText;
        nextBtn.disabled = false;
    });
}

function updateVerificationStats(metadata) {
    document.getElementById('tableCount').textContent = metadata.table_count || '-';
    document.getElementById('insertCount').textContent = metadata.insert_count || '-';
    document.getElementById('backupSize').textContent = formatBytes(metadata.file_size || 0);
    document.getElementById('backupDate').textContent = metadata.backup_date || '-';
}

function toggleConfirmation() {
    const checkbox = document.getElementById('confirmCheckbox');
    checkbox.checked = !checkbox.checked;
    updateRestoreButton();
}

function updateRestoreButton() {
    const checkbox = document.getElementById('confirmCheckbox');
    document.getElementById('restoreButton').disabled = !checkbox.checked;
}

function startRestore() {
    if (!confirm('FINAL WARNING: This will delete all current data. Are you absolutely sure?')) {
        return;
    }
    
    // Disable buttons
    document.getElementById('restoreButton').disabled = true;
    document.getElementById('step3Back').disabled = true;
    
    // Show progress
    const progressContainer = document.getElementById('progressContainer');
    progressContainer.classList.add('show');
    
    // Prepare form data
    const formData = new FormData();
    formData.append('csrf_token', '<?= $csrf_token ?>');
    formData.append('action', 'restore_database');
    
    if (selectedFile) {
        formData.append('backup_file', selectedFile);
    } else {
        formData.append('backup_path', selectedBackupPath);
    }
    
    const startTime = Date.now();
    
    // Simulate progress (actual progress tracking would require chunked responses)
    let progress = 0;
    const progressInterval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 90) progress = 90;
        updateProgress(progress, 'Restoring database...');
    }, 1000);
    
    fetch('process_database_restore.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        clearInterval(progressInterval);
        const duration = ((Date.now() - startTime) / 1000).toFixed(1);
        
        updateProgress(100, 'Complete!');
        
        setTimeout(() => {
            showResults(data, duration);
        }, 500);
    })
    .catch(error => {
        clearInterval(progressInterval);
        console.error('Error:', error);
        showResults({
            success: false,
            message: 'Failed to restore database: ' + error.message,
            errors: [error.toString()]
        }, 0);
    });
}

function updateProgress(percent, status) {
    const progressBar = document.getElementById('progressBar');
    const progressStatus = document.getElementById('progressStatus');
    
    progressBar.style.width = percent + '%';
    progressBar.textContent = Math.round(percent) + '%';
    progressStatus.innerHTML = '<span class="progress-spinner"></span> ' + status;
}

function showResults(data, duration) {
    const resultsContainer = document.getElementById('resultsContainer');
    const progressContainer = document.getElementById('progressContainer');
    const resultSuccess = document.getElementById('resultSuccess');
    const resultError = document.getElementById('resultError');
    const resultDetails = document.getElementById('resultDetails');
    
    progressContainer.classList.remove('show');
    resultsContainer.classList.add('show');
    
    if (data.success) {
        resultSuccess.style.display = 'block';
        resultError.style.display = 'none';
    } else {
        resultSuccess.style.display = 'none';
        resultError.style.display = 'block';
        document.getElementById('resultErrorMessage').textContent = data.message || 'An error occurred during restore.';
    }
    
    // Show details
    if (data.stats) {
        resultDetails.style.display = 'block';
        document.getElementById('resultSuccessCount').textContent = data.stats.successful || 0;
        document.getElementById('resultFailedCount').textContent = data.stats.failed || 0;
        document.getElementById('resultDuration').textContent = duration + 's';
        
        // Show errors if any
        if (data.errors && data.errors.length > 0) {
            document.getElementById('resultErrorsContainer').style.display = 'block';
            const errorsList = document.getElementById('resultErrorsList');
            errorsList.innerHTML = data.errors.map(err => 
                '<div class="result-error-item">' + escapeHtml(err) + '</div>'
            ).join('');
        }
    }
    
    // Show finish button
    document.getElementById('restoreButton').style.display = 'none';
    document.getElementById('finishButton').style.display = 'inline-flex';
}

function finishRestore() {
    window.location.href = 'dashboard.php?page=admin_database_tools';
}

function goToStep(step) {
    // Update step UI
    document.querySelectorAll('.wizard-step').forEach(s => {
        const stepNum = parseInt(s.dataset.step);
        s.classList.remove('active', 'completed');
        
        if (stepNum === step) {
            s.classList.add('active');
        } else if (stepNum < step) {
            s.classList.add('completed');
            s.querySelector('.wizard-step-circle i').className = 'fas fa-check';
        } else {
            // Reset icon for future steps
            if (stepNum === 1) s.querySelector('.wizard-step-circle i').className = 'fas fa-upload';
            if (stepNum === 2) s.querySelector('.wizard-step-circle i').className = 'fas fa-search';
            if (stepNum === 3) s.querySelector('.wizard-step-circle i').className = 'fas fa-exclamation-triangle';
        }
    });
    
    // Update content
    document.querySelectorAll('.step-content').forEach(c => {
        c.classList.remove('active');
    });
    document.querySelector(`.step-content[data-step="${step}"]`).classList.add('active');
    
    currentStep = step;
    
    // Scroll to top
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
