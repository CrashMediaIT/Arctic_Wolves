<?php
/**
 * System Validation UI
 * Interface to run comprehensive system validation checks
 */

require_once __DIR__ . '/../security.php';

// Only admins can access this page
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home&error=' . urlencode('Admin privileges required to access system checks.'));
    exit;
}
?>

<style>
    :root {
        --primary: #7000a4;
        --success: #10b981;
        --warning: #f59e0b;
        --danger: #ef4444;
        --bg-dark: #0d1117;
        --bg-darker: #06080b;
        --border: #1e293b;
        --text-light: #94a3b8;
    }
    
    .system-check-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
    
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
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .btn-run-check {
        background: var(--primary);
        color: #fff;
        padding: 14px 28px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .btn-run-check:hover:not(:disabled) {
        background: #5a0083;
        transform: translateY(-1px);
    }
    
    .btn-run-check:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .loading-spinner {
        border: 3px solid rgba(255, 255, 255, 0.3);
        border-top: 3px solid #fff;
        border-radius: 50%;
        width: 20px;
        height: 20px;
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    .status-banner {
        background: var(--bg-dark);
        border: 2px solid var(--border);
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
        display: none;
    }
    
    .status-banner.show {
        display: block;
    }
    
    .status-banner.critical {
        border-color: var(--danger);
        background: rgba(239, 68, 68, 0.1);
    }
    
    .status-banner.warning {
        border-color: var(--warning);
        background: rgba(245, 158, 11, 0.1);
    }
    
    .status-banner.healthy {
        border-color: var(--success);
        background: rgba(16, 185, 129, 0.1);
    }
    
    .status-title {
        font-size: 20px;
        font-weight: 900;
        margin-bottom: 10px;
    }
    
    .status-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    .stat-item {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }
    
    .stat-label {
        font-size: 12px;
        color: var(--text-light);
        text-transform: uppercase;
        font-weight: 700;
    }
    
    .stat-value {
        font-size: 24px;
        font-weight: 900;
    }
    
    .results-container {
        display: none;
    }
    
    .results-container.show {
        display: block;
    }
    
    .category-section {
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        margin-bottom: 25px;
        overflow: hidden;
    }
    
    .category-header {
        background: var(--bg-darker);
        padding: 20px 25px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.2s;
    }
    
    .category-header:hover {
        background: rgba(112, 0, 164, 0.1);
    }
    
    .category-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    
    .category-badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 700;
    }
    
    .badge-critical { background: rgba(239, 68, 68, 0.2); color: var(--danger); }
    .badge-warning { background: rgba(245, 158, 11, 0.2); color: var(--warning); }
    .badge-success { background: rgba(16, 185, 129, 0.2); color: var(--success); }
    
    .category-body {
        padding: 25px;
        display: none;
    }
    
    .category-section.expanded .category-body {
        display: block;
    }
    
    .category-section.expanded .category-header i {
        transform: rotate(90deg);
    }
    
    .check-group {
        margin-bottom: 20px;
    }
    
    .check-group:last-child {
        margin-bottom: 0;
    }
    
    .check-group-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .check-item {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        align-items: start;
        gap: 12px;
    }
    
    .check-item.critical {
        border-color: var(--danger);
        background: rgba(239, 68, 68, 0.05);
    }
    
    .check-item.warning {
        border-color: var(--warning);
        background: rgba(245, 158, 11, 0.05);
    }
    
    .check-item.success {
        border-color: var(--success);
        background: rgba(16, 185, 129, 0.05);
    }
    
    .check-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        flex-shrink: 0;
    }
    
    .check-icon.critical {
        background: var(--danger);
        color: #fff;
    }
    
    .check-icon.warning {
        background: var(--warning);
        color: #fff;
    }
    
    .check-icon.success {
        background: var(--success);
        color: #fff;
    }
    
    .check-content {
        flex: 1;
    }
    
    .check-title {
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 4px;
    }
    
    .check-message {
        font-size: 13px;
        color: var(--text-light);
    }
    
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-light);
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
</style>

<div class="system-check-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-shield-alt"></i> System Validation
        </h1>
        <button class="btn-run-check" id="runCheckBtn" onclick="runValidation()">
            <i class="fas fa-play"></i> Run Validation
        </button>
    </div>
    
    <!-- Status Banner -->
    <div class="status-banner" id="statusBanner">
        <div class="status-title" id="statusTitle"></div>
        <div class="status-stats" id="statusStats"></div>
    </div>
    
    <!-- Results Container -->
    <div class="results-container" id="resultsContainer">
        <!-- File System -->
        <div class="category-section" id="fileSystemSection">
            <div class="category-header" onclick="toggleCategory('fileSystem')">
                <div class="category-title">
                    <i class="fas fa-chevron-right"></i>
                    <i class="fas fa-folder"></i> File System Audit
                </div>
                <span class="category-badge" id="fileSystemBadge"></span>
            </div>
            <div class="category-body" id="fileSystemBody"></div>
        </div>
        
        <!-- Database Integrity -->
        <div class="category-section" id="databaseSection">
            <div class="category-header" onclick="toggleCategory('database')">
                <div class="category-title">
                    <i class="fas fa-chevron-right"></i>
                    <i class="fas fa-database"></i> Database Integrity
                </div>
                <span class="category-badge" id="databaseBadge"></span>
            </div>
            <div class="category-body" id="databaseBody"></div>
        </div>
        
        <!-- Code Cross-References -->
        <div class="category-section" id="codeReferencesSection">
            <div class="category-header" onclick="toggleCategory('codeReferences')">
                <div class="category-title">
                    <i class="fas fa-chevron-right"></i>
                    <i class="fas fa-code"></i> Code Cross-References
                </div>
                <span class="category-badge" id="codeReferencesBadge"></span>
            </div>
            <div class="category-body" id="codeReferencesBody"></div>
        </div>
        
        <!-- Security Scan -->
        <div class="category-section" id="securitySection">
            <div class="category-header" onclick="toggleCategory('security')">
                <div class="category-title">
                    <i class="fas fa-chevron-right"></i>
                    <i class="fas fa-lock"></i> Security Scan
                </div>
                <span class="category-badge" id="securityBadge"></span>
            </div>
            <div class="category-body" id="securityBody"></div>
        </div>
    </div>
    
    <!-- Empty State -->
    <div class="empty-state" id="emptyState">
        <i class="fas fa-clipboard-check"></i>
        <h3>No validation results yet</h3>
        <p>Click "Run Validation" to perform a comprehensive system check</p>
    </div>
</div>

<script>
function toggleCategory(category) {
    const section = document.getElementById(category + 'Section');
    section.classList.toggle('expanded');
}

async function runValidation() {
    const btn = document.getElementById('runCheckBtn');
    const emptyState = document.getElementById('emptyState');
    const resultsContainer = document.getElementById('resultsContainer');
    const statusBanner = document.getElementById('statusBanner');
    
    // Disable button and show loading
    btn.disabled = true;
    btn.innerHTML = '<div class="loading-spinner"></div> Running Validation...';
    
    emptyState.style.display = 'none';
    resultsContainer.classList.remove('show');
    statusBanner.classList.remove('show');
    
    try {
        const formData = new FormData();
        formData.append('action', 'run_validation');
        formData.append('csrf_token', '<?= generateCsrfToken() ?>');
        
        const response = await fetch('process_system_validation.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            displayResults(data.results);
        } else {
            alert('Error running validation: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Error running validation. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-play"></i> Run Validation';
    }
}

function displayResults(results) {
    const resultsContainer = document.getElementById('resultsContainer');
    const statusBanner = document.getElementById('statusBanner');
    const summary = results.summary;
    
    // Show status banner
    statusBanner.className = 'status-banner show ' + summary.overall_status;
    
    let statusIcon = summary.overall_status === 'healthy' ? 'check-circle' :
                     summary.overall_status === 'warning' ? 'exclamation-triangle' : 'times-circle';
    
    document.getElementById('statusTitle').innerHTML = `
        <i class="fas fa-${statusIcon}"></i> 
        System ${summary.overall_status === 'healthy' ? 'Healthy' : 
                 summary.overall_status === 'warning' ? 'Needs Attention' : 'Critical Issues Detected'}
    `;
    
    document.getElementById('statusStats').innerHTML = `
        <div class="stat-item">
            <div class="stat-label">Total Checks</div>
            <div class="stat-value">${summary.total_checks}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Passed</div>
            <div class="stat-value" style="color: var(--success)">${summary.passed}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Warnings</div>
            <div class="stat-value" style="color: var(--warning)">${summary.warnings}</div>
        </div>
        <div class="stat-item">
            <div class="stat-label">Critical</div>
            <div class="stat-value" style="color: var(--danger)">${summary.critical}</div>
        </div>
    `;
    
    // Display each category
    displayCategory('fileSystem', 'File System Audit', results.file_system);
    displayCategory('database', 'Database Integrity', results.database);
    displayCategory('codeReferences', 'Code Cross-References', results.code_references);
    displayCategory('security', 'Security Scan', results.security);
    
    resultsContainer.classList.add('show');
}

function displayCategory(categoryId, categoryName, categoryData) {
    const bodyEl = document.getElementById(categoryId + 'Body');
    const badgeEl = document.getElementById(categoryId + 'Badge');
    
    let criticalCount = 0;
    let warningCount = 0;
    let successCount = 0;
    let html = '';
    
    // Process each check group
    for (const [groupName, items] of Object.entries(categoryData)) {
        if (!Array.isArray(items) || items.length === 0) continue;
        
        html += `<div class="check-group">`;
        html += `<div class="check-group-title">${formatGroupName(groupName)}</div>`;
        
        items.forEach(item => {
            const severity = item.severity || 'info';
            if (severity === 'critical') criticalCount++;
            if (severity === 'warning') warningCount++;
            if (severity === 'success') successCount++;
            
            const icon = severity === 'success' ? 'check' :
                        severity === 'warning' ? 'exclamation' : 'times';
            
            html += `
                <div class="check-item ${severity}">
                    <div class="check-icon ${severity}">
                        <i class="fas fa-${icon}"></i>
                    </div>
                    <div class="check-content">
                        <div class="check-title">${formatCheckTitle(item)}</div>
                        <div class="check-message">${formatCheckMessage(item)}</div>
                    </div>
                </div>
            `;
        });
        
        html += `</div>`;
    }
    
    bodyEl.innerHTML = html || '<p style="color: var(--text-light);">No issues found in this category</p>';
    
    // Set badge
    if (criticalCount > 0) {
        badgeEl.className = 'category-badge badge-critical';
        badgeEl.textContent = criticalCount + ' Critical';
    } else if (warningCount > 0) {
        badgeEl.className = 'category-badge badge-warning';
        badgeEl.textContent = warningCount + ' Warnings';
    } else {
        badgeEl.className = 'category-badge badge-success';
        badgeEl.textContent = 'Passed';
    }
}

function formatGroupName(name) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function formatCheckTitle(item) {
    if (item.file) return item.file;
    if (item.table) return 'Table: ' + item.table;
    if (item.directory) return item.directory;
    if (item.relationship) return item.relationship;
    return 'Check';
}

function formatCheckMessage(item) {
    if (item.message) return item.message;
    if (item.issue) return item.issue;
    if (item.status === 'exists') return 'File exists';
    if (item.status === 'missing') return 'File missing';
    if (item.exists === true) return 'Valid';
    if (item.exists === false) return 'Does not exist';
    if (item.writable === true) return 'Writable (Permissions: ' + (item.permissions || 'N/A') + ')';
    if (item.writable === false) return 'Not writable';
    return 'Status: ' + (item.status || 'Unknown');
}
</script>
