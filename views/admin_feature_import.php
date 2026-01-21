<?php
/**
 * Feature Import UI
 * Upload and import packaged features with progress tracking
 */

require_once __DIR__ . '/../security.php';

// Only admins can access this page
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home&error=' . urlencode('Admin privileges required to import features.'));
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
    
    .feature-import-container {
        padding: 20px;
        max-width: 1000px;
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
    
    .info-banner {
        background: rgba(112, 0, 164, 0.1);
        border: 1px solid var(--primary);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
    }
    
    .info-banner h3 {
        color: var(--primary);
        margin: 0 0 10px 0;
        font-size: 16px;
        font-weight: 700;
    }
    
    .info-banner p {
        color: var(--text-light);
        margin: 0;
        font-size: 14px;
        line-height: 1.6;
    }
    
    .upload-section {
        background: var(--bg-dark);
        border: 2px dashed var(--border);
        border-radius: 8px;
        padding: 40px;
        text-align: center;
        margin-bottom: 30px;
        transition: all 0.2s;
        cursor: pointer;
    }
    
    .upload-section:hover {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.05);
    }
    
    .upload-section.dragover {
        border-color: var(--primary);
        background: rgba(112, 0, 164, 0.1);
    }
    
    .upload-icon {
        font-size: 48px;
        color: var(--primary);
        margin-bottom: 15px;
    }
    
    .upload-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    
    .upload-description {
        font-size: 14px;
        color: var(--text-light);
        margin-bottom: 20px;
    }
    
    .file-input {
        display: none;
    }
    
    .btn-browse {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-browse:hover {
        background: #5a0083;
    }
    
    .selected-file {
        display: none;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .selected-file.show {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .file-icon {
        font-size: 32px;
        color: var(--primary);
    }
    
    .file-info {
        flex: 1;
    }
    
    .file-name {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 4px;
    }
    
    .file-size {
        font-size: 14px;
        color: var(--text-light);
    }
    
    .btn-remove {
        background: var(--danger);
        color: #fff;
        padding: 8px 16px;
        border-radius: 6px;
        border: none;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .btn-remove:hover {
        background: #dc2626;
    }
    
    .btn-import {
        background: var(--success);
        color: #fff;
        padding: 14px 28px;
        border-radius: 6px;
        border: none;
        font-weight: 700;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    
    .btn-import:hover:not(:disabled) {
        background: #059669;
    }
    
    .btn-import:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }
    
    .progress-section {
        display: none;
        background: var(--bg-dark);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .progress-section.show {
        display: block;
    }
    
    .progress-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 15px;
    }
    
    .progress-bar-container {
        background: var(--bg-darker);
        border-radius: 6px;
        height: 8px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    
    .progress-bar {
        background: var(--primary);
        height: 100%;
        width: 0;
        transition: width 0.3s;
    }
    
    .log-container {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 15px;
        max-height: 300px;
        overflow-y: auto;
        font-family: 'Courier New', monospace;
        font-size: 13px;
    }
    
    .log-entry {
        padding: 6px 0;
        display: flex;
        align-items: start;
        gap: 10px;
    }
    
    .log-time {
        color: var(--text-light);
        flex-shrink: 0;
    }
    
    .log-message {
        flex: 1;
    }
    
    .log-entry.info .log-message { color: var(--text-light); }
    .log-entry.success .log-message { color: var(--success); }
    .log-entry.warning .log-message { color: var(--warning); }
    .log-entry.error .log-message { color: var(--danger); }
    
    .result-banner {
        display: none;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .result-banner.show {
        display: block;
    }
    
    .result-banner.success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid var(--success);
        color: var(--success);
    }
    
    .result-banner.error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid var(--danger);
        color: var(--danger);
    }
    
    .result-banner h3 {
        margin: 0 0 10px 0;
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .result-banner p {
        margin: 0;
        opacity: 0.9;
    }
    
    .manifest-example {
        background: var(--bg-darker);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 20px;
        margin-top: 30px;
    }
    
    .manifest-example h3 {
        color: var(--primary);
        margin: 0 0 15px 0;
        font-size: 16px;
    }
    
    .manifest-example pre {
        background: #000;
        border: 1px solid var(--border);
        border-radius: 6px;
        padding: 15px;
        overflow-x: auto;
        margin: 0;
        color: #fff;
        font-size: 12px;
    }
</style>

<div class="feature-import-container">
    <div class="page-header">
        <h1 class="page-title">
            <i class="fas fa-file-import"></i> Feature Import
        </h1>
    </div>
    
    <div class="info-banner">
        <h3><i class="fas fa-info-circle"></i> Intelligent Feature Import System</h3>
        <p>
            Import packaged features with intelligent database schema migration support. The system automatically:
            handles table/column renames, updates code references, validates version compatibility, tracks migration history,
            and provides rollback on errors. All changes are backed up before execution.
        </p>
    </div>
    
    <!-- Installed Versions -->
    <div class="manifest-example" id="installedVersions" style="display: none;">
        <h3><i class="fas fa-history"></i> Installed Feature Versions</h3>
        <div id="versionsContainer" style="max-height: 200px; overflow-y: auto;">
            <p style="text-align: center; color: var(--text-light);">Loading...</p>
        </div>
    </div>
    
    <!-- Upload Section -->
    <div class="upload-section" id="uploadSection" onclick="document.getElementById('fileInput').click()">
        <div class="upload-icon">
            <i class="fas fa-cloud-upload-alt"></i>
        </div>
        <div class="upload-title">Upload Feature Package</div>
        <div class="upload-description">Click to browse or drag and drop a ZIP file here</div>
        <button type="button" class="btn-browse">Browse Files</button>
        <input type="file" id="fileInput" class="file-input" accept=".zip" onchange="handleFileSelect(event)">
    </div>
    
    <!-- Selected File -->
    <div class="selected-file" id="selectedFile">
        <div class="file-icon">
            <i class="fas fa-file-archive"></i>
        </div>
        <div class="file-info">
            <div class="file-name" id="fileName"></div>
            <div class="file-size" id="fileSize"></div>
        </div>
        <button type="button" class="btn-remove" onclick="removeFile()">
            <i class="fas fa-times"></i> Remove
        </button>
    </div>
    
    <!-- Import Button -->
    <button type="button" class="btn-import" id="importBtn" onclick="startImport()" disabled>
        <i class="fas fa-download"></i> Import Feature
    </button>
    
    <!-- Result Banner -->
    <div class="result-banner" id="resultBanner"></div>
    
    <!-- Progress Section -->
    <div class="progress-section" id="progressSection">
        <div class="progress-title">
            <i class="fas fa-spinner fa-spin"></i> Importing Feature...
        </div>
        <div class="progress-bar-container">
            <div class="progress-bar" id="progressBar"></div>
        </div>
        <div class="log-container" id="logContainer"></div>
    </div>
    
    <!-- Manifest Example -->
    <div class="manifest-example">
        <h3><i class="fas fa-code"></i> Enhanced Manifest Format</h3>
        <pre>{
  "name": "Feature Name",
  "version": "2.0.0",
  "requires_version": "1.0.0",
  "requires_validation": true,
  "database_migrations": [
    {
      "type": "rename_table",
      "old_name": "old_table",
      "new_name": "new_table"
    },
    {
      "type": "rename_column",
      "table": "users",
      "old_name": "old_col",
      "new_name": "new_col"
    },
    {
      "type": "add_column",
      "table": "users",
      "column_definition": "new_col VARCHAR(255) DEFAULT NULL"
    }
  ],
  "file_migrations": [
    {
      "type": "move",
      "old_path": "views/old.php",
      "new_path": "views/new.php"
    }
  ],
  "files": {
    "create": ["views/new_view.php"],
    "update": ["dashboard.php"]
  },
  "directories": ["uploads/new_folder/"]
}</pre>
    </div>
</div>

<script>
let selectedFile = null;

// Load installed versions on page load
document.addEventListener('DOMContentLoaded', function() {
    loadInstalledVersions();
});

// Load installed feature versions
async function loadInstalledVersions() {
    try {
        const formData = new FormData();
        formData.append('action', 'get_installed_versions');
        formData.append('csrf_token', '<?= generateCsrfToken() ?>');
        
        const response = await fetch('process_feature_import.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success && data.versions && data.versions.length > 0) {
            const container = document.getElementById('versionsContainer');
            const versionsSection = document.getElementById('installedVersions');
            
            // Group versions by feature
            const grouped = {};
            data.versions.forEach(v => {
                if (!grouped[v.feature_name]) {
                    grouped[v.feature_name] = [];
                }
                grouped[v.feature_name].push(v);
            });
            
            let html = '<table style="width: 100%; border-collapse: collapse; font-size: 13px;">';
            html += '<tr style="border-bottom: 1px solid var(--border); color: var(--text-light);">';
            html += '<th style="padding: 8px; text-align: left;">Feature</th>';
            html += '<th style="padding: 8px; text-align: left;">Version</th>';
            html += '<th style="padding: 8px; text-align: left;">Installed</th>';
            html += '</tr>';
            
            for (const [feature, versions] of Object.entries(grouped)) {
                const latest = versions[0];
                html += '<tr style="border-bottom: 1px solid var(--border);">';
                html += `<td style="padding: 8px; color: #fff;">${feature}</td>`;
                html += `<td style="padding: 8px; color: var(--success);">${latest.version}</td>`;
                html += `<td style="padding: 8px; color: var(--text-light);">${new Date(latest.applied_at).toLocaleDateString()}</td>`;
                html += '</tr>';
            }
            
            html += '</table>';
            container.innerHTML = html;
            versionsSection.style.display = 'block';
        }
    } catch (error) {
        console.error('Error loading versions:', error);
    }
}

// Drag and drop
const uploadSection = document.getElementById('uploadSection');

uploadSection.addEventListener('dragover', (e) => {
    e.preventDefault();
    uploadSection.classList.add('dragover');
});

uploadSection.addEventListener('dragleave', () => {
    uploadSection.classList.remove('dragover');
});

uploadSection.addEventListener('drop', (e) => {
    e.preventDefault();
    uploadSection.classList.remove('dragover');
    
    const files = e.dataTransfer.files;
    if (files.length > 0 && files[0].name.endsWith('.zip')) {
        handleFile(files[0]);
    } else {
        alert('Please select a ZIP file');
    }
});

function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        handleFile(file);
    }
}

function handleFile(file) {
    selectedFile = file;
    
    document.getElementById('fileName').textContent = file.name;
    document.getElementById('fileSize').textContent = formatFileSize(file.size);
    document.getElementById('selectedFile').classList.add('show');
    document.getElementById('importBtn').disabled = false;
    document.getElementById('uploadSection').style.display = 'none';
}

function removeFile() {
    selectedFile = null;
    document.getElementById('selectedFile').classList.remove('show');
    document.getElementById('importBtn').disabled = true;
    document.getElementById('uploadSection').style.display = 'block';
    document.getElementById('fileInput').value = '';
    document.getElementById('resultBanner').classList.remove('show');
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

async function startImport() {
    if (!selectedFile) {
        alert('Please select a file first');
        return;
    }
    
    const importBtn = document.getElementById('importBtn');
    const progressSection = document.getElementById('progressSection');
    const logContainer = document.getElementById('logContainer');
    const resultBanner = document.getElementById('resultBanner');
    
    // Reset UI
    importBtn.disabled = true;
    progressSection.classList.add('show');
    logContainer.innerHTML = '';
    resultBanner.classList.remove('show');
    
    // Prepare form data
    const formData = new FormData();
    formData.append('action', 'import_feature');
    formData.append('feature_package', selectedFile);
    formData.append('csrf_token', '<?= generateCsrfToken() ?>');
    
    try {
        const response = await fetch('process_feature_import.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        // Display log entries
        if (data.log && Array.isArray(data.log)) {
            data.log.forEach(entry => {
                addLogEntry(entry);
            });
        }
        
        // Show result
        if (data.success) {
            resultBanner.className = 'result-banner show success';
            resultBanner.innerHTML = `
                <h3><i class="fas fa-check-circle"></i> Import Successful</h3>
                <p>${data.message || 'Feature imported successfully'}</p>
                ${data.backup_id ? '<p style="font-size: 12px; margin-top: 10px;">Backup ID: ' + data.backup_id + '</p>' : ''}
            `;
            
            // Reload installed versions
            loadInstalledVersions();
            
            // Reset after success
            setTimeout(() => {
                removeFile();
                progressSection.classList.remove('show');
            }, 3000);
            
        } else {
            resultBanner.className = 'result-banner show error';
            resultBanner.innerHTML = `
                <h3><i class="fas fa-times-circle"></i> Import Failed</h3>
                <p>${data.error || 'An error occurred during import'}</p>
            `;
        }
        
    } catch (error) {
        console.error('Error:', error);
        resultBanner.className = 'result-banner show error';
        resultBanner.innerHTML = `
            <h3><i class="fas fa-times-circle"></i> Import Failed</h3>
            <p>Network error or server not responding</p>
        `;
    } finally {
        importBtn.disabled = false;
    }
}

function addLogEntry(entry) {
    const logContainer = document.getElementById('logContainer');
    const logEntry = document.createElement('div');
    logEntry.className = 'log-entry ' + (entry.type || 'info');
    
    logEntry.innerHTML = `
        <span class="log-time">${entry.timestamp || new Date().toLocaleTimeString()}</span>
        <span class="log-message">${entry.message}</span>
    `;
    
    logContainer.appendChild(logEntry);
    logContainer.scrollTop = logContainer.scrollHeight;
}
</script>
