<?php
/**
 * Export / Import All Practice Plans
 * Allows exporting all practice plans to JSON and importing from a JSON file
 */

$csrf_token = $_SESSION['csrf_token'] ?? '';
if (empty($csrf_token)) {
    $csrf_token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrf_token;
}

// Count plans for display
$plan_count = $pdo->query("SELECT COUNT(*) FROM practice_plans")->fetchColumn();
$drill_count = $pdo->query("SELECT COUNT(*) FROM drills")->fetchColumn();
?>

<style>
    .export-import-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 24px;
        margin-top: 20px;
    }
    @media (max-width: 768px) {
        .export-import-container {
            grid-template-columns: 1fr;
        }
    }
    .ei-card {
        background: #0a0f14;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
    }
    .ei-card-icon {
        width: 60px;
        height: 60px;
        background: rgba(112, 0, 164, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 16px;
        font-size: 28px;
        color: #7000a4;
    }
    .ei-card-title {
        font-size: 22px;
        font-weight: 800;
        color: #fff;
        margin-bottom: 8px;
    }
    .ei-card-desc {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 20px;
    }
    .ei-stat {
        background: #161b22;
        border-radius: 8px;
        padding: 12px 16px;
        margin-bottom: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: #94a3b8;
        font-size: 14px;
    }
    .ei-stat-value {
        color: #fff;
        font-weight: 700;
        font-size: 18px;
    }
    .ei-btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 14px 28px;
        border: none;
        border-radius: 8px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
        justify-content: center;
        text-decoration: none;
    }
    .ei-btn-export {
        background: #7000a4;
        color: #fff;
    }
    .ei-btn-export:hover {
        background: #5a0085;
    }
    .ei-btn-import {
        background: #3b82f6;
        color: #fff;
    }
    .ei-btn-import:hover {
        background: #2563eb;
    }
    .ei-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .ei-file-input {
        width: 100%;
        background: #161b22;
        border: 2px dashed #1e293b;
        border-radius: 8px;
        padding: 20px;
        color: #94a3b8;
        font-size: 14px;
        margin-bottom: 12px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .ei-file-input:hover {
        border-color: #3b82f6;
    }
    .ei-checkbox {
        display: flex;
        align-items: center;
        gap: 8px;
        color: #94a3b8;
        font-size: 14px;
        margin-bottom: 16px;
    }
    .ei-checkbox input[type="checkbox"] {
        accent-color: #7000a4;
        width: 18px;
        height: 18px;
    }
    .ei-alert {
        padding: 14px 18px;
        border-radius: 8px;
        margin-top: 16px;
        display: none;
        align-items: center;
        gap: 10px;
        font-size: 14px;
    }
    .ei-alert.show {
        display: flex;
    }
    .ei-alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    .ei-alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    .ei-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        border-radius: 8px;
        padding: 14px 18px;
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.6;
        margin-bottom: 16px;
    }
</style>

<div id="ei-alert-success" class="ei-alert ei-alert-success">
    <i class="fas fa-check-circle"></i> <span id="ei-success-msg"></span>
</div>
<div id="ei-alert-error" class="ei-alert ei-alert-error">
    <i class="fas fa-exclamation-circle"></i> <span id="ei-error-msg"></span>
</div>

<div class="export-import-container">
    <!-- Export Card -->
    <div class="ei-card">
        <div class="ei-card-icon"><i class="fas fa-file-export"></i></div>
        <div class="ei-card-title">Export All Practice Plans</div>
        <div class="ei-card-desc">
            Download all practice plans with their associated drills as a JSON file. Use this to back up your practice library or transfer plans to another system.
        </div>
        
        <div class="ei-stat">
            <span>Total Practice Plans</span>
            <span class="ei-stat-value"><?= number_format($plan_count) ?></span>
        </div>
        <div class="ei-stat">
            <span>Available Drills</span>
            <span class="ei-stat-value"><?= number_format($drill_count) ?></span>
        </div>
        
        <a href="process_practice_plans_export.php?csrf_token=<?= urlencode($csrf_token) ?>" class="ei-btn ei-btn-export" <?= $plan_count == 0 ? 'style="pointer-events:none;opacity:0.5;"' : '' ?>>
            <i class="fas fa-download"></i> Export All Practice Plans (JSON)
        </a>
    </div>
    
    <!-- Import Card -->
    <div class="ei-card">
        <div class="ei-card-icon"><i class="fas fa-file-import"></i></div>
        <div class="ei-card-title">Import Practice Plans from File</div>
        <div class="ei-card-desc">
            Upload a JSON file previously exported from this system to import practice plans and their associated drills.
        </div>
        
        <div class="ei-info">
            <i class="fas fa-info-circle"></i> The import file must be a JSON file exported using the Export function. Plans and any missing drills will be created under your user account.
        </div>
        
        <form id="plan-import-form" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_json">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
            
            <input type="file" name="import_file" accept=".json" class="ei-file-input" id="plan-import-file" required>
            
            <label class="ei-checkbox">
                <input type="checkbox" name="skip_duplicates" value="1" checked>
                Skip plans that already exist (match by name)
            </label>
            
            <button type="submit" class="ei-btn ei-btn-import" id="plan-import-btn">
                <i class="fas fa-upload"></i> Import Practice Plans
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('plan-import-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const fileInput = document.getElementById('plan-import-file');
    if (!fileInput.files.length) {
        showEiAlert('error', 'Please select a JSON file to import.');
        return;
    }
    
    const btn = document.getElementById('plan-import-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing...';
    
    const formData = new FormData(this);
    
    fetch('process_practice_plans.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showEiAlert('success', data.message);
            fileInput.value = '';
        } else {
            showEiAlert('error', data.message);
        }
    })
    .catch(error => {
        showEiAlert('error', 'Import failed. Please try again.');
        console.error(error);
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-upload"></i> Import Practice Plans';
    });
});

function showEiAlert(type, message) {
    const successEl = document.getElementById('ei-alert-success');
    const errorEl = document.getElementById('ei-alert-error');
    
    successEl.classList.remove('show');
    errorEl.classList.remove('show');
    
    if (type === 'success') {
        document.getElementById('ei-success-msg').textContent = message;
        successEl.classList.add('show');
    } else {
        document.getElementById('ei-error-msg').textContent = message;
        errorEl.classList.add('show');
    }
    
    setTimeout(() => {
        successEl.classList.remove('show');
        errorEl.classList.remove('show');
    }, 8000);
}
</script>
