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
$plan_count = (int)$pdo->query("SELECT COUNT(*) FROM practice_plans")->fetchColumn();
$drill_count = (int)$pdo->query("SELECT COUNT(*) FROM drills")->fetchColumn();
?>

<style>
    .ei-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-6);
        margin-top: var(--space-5);
    }
    @media (max-width: 768px) {
        .ei-grid {
            grid-template-columns: 1fr;
        }
    }
    .ei-card-title {
        color: var(--text-white);
        font-weight: var(--font-weight-black);
        font-size: var(--font-size-xl);
        margin-bottom: var(--space-2);
    }
    .ei-card-desc {
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
        line-height: 1.6;
        margin-bottom: var(--space-5);
    }
    .ei-card-icon {
        width: 60px;
        height: 60px;
        background: rgba(107, 70, 193, 0.15);
        border-radius: var(--radius-2xl);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: var(--space-4);
        font-size: 28px;
        color: var(--primary);
    }
    .ei-stat {
        background: var(--bg-main);
        border-radius: var(--radius-lg);
        padding: var(--space-3) var(--space-4);
        margin-bottom: var(--space-4);
        display: flex;
        justify-content: space-between;
        align-items: center;
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
    }
    .ei-stat-value {
        color: var(--text-white);
        font-weight: var(--font-weight-bold);
        font-size: var(--font-size-lg);
    }
    .ei-file-input {
        width: 100%;
        background: var(--bg-main);
        border: 2px dashed var(--border);
        border-radius: var(--radius-lg);
        padding: var(--space-5);
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
        margin-bottom: var(--space-3);
        cursor: pointer;
        transition: all var(--transition-normal);
    }
    .ei-file-input:hover {
        border-color: var(--primary);
    }
    .ei-checkbox {
        display: flex;
        align-items: center;
        gap: var(--space-2);
        color: var(--text-secondary);
        font-size: var(--font-size-sm);
        margin-bottom: var(--space-4);
    }
    .ei-checkbox input[type="checkbox"] {
        accent-color: var(--primary);
        width: 18px;
        height: 18px;
    }
</style>

<div id="ei-alert-success" class="alert alert-success" style="display:none;">
    <i class="fas fa-check-circle"></i> <span id="ei-success-msg"></span>
</div>
<div id="ei-alert-error" class="alert alert-error" style="display:none;">
    <i class="fas fa-exclamation-circle"></i> <span id="ei-error-msg"></span>
</div>

<!-- Import Progress Bar -->
<div id="ei-progress-container" style="display:none; margin-bottom: var(--space-5);">
    <div style="background: var(--bg-card, #0d1117); border: 1px solid var(--border, #1e293b); border-radius: var(--radius-lg); padding: var(--space-5); text-align: center;">
        <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: var(--space-3);">
            <i class="fas fa-spinner fa-spin" style="color: var(--primary); font-size: 20px;"></i>
            <span id="ei-progress-text" style="color: var(--text-white); font-weight: 700; font-size: var(--font-size-base);">Importing practice plans…</span>
        </div>
        <div style="background: var(--bg-main, #06080b); border-radius: 999px; height: 8px; overflow: hidden; position: relative;">
            <div id="ei-progress-bar" style="height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--primary), #a855f7); width: 0%; transition: width 0.4s ease; position: relative; overflow: hidden;">
                <div style="position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); animation: ei-shimmer 1.5s infinite;"></div>
            </div>
        </div>
        <p id="ei-progress-detail" style="color: var(--text-dim, #64748b); font-size: var(--font-size-sm); margin-top: var(--space-2);">Reading file and uploading to server…</p>
    </div>
</div>

<style>
@keyframes ei-shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
</style>

<div class="ei-grid">
    <!-- Export Card -->
    <div class="card">
        <div class="card-body">
            <div class="ei-card-icon"><i class="fas fa-file-export"></i></div>
            <h3 class="ei-card-title">Export All Practice Plans</h3>
            <p class="ei-card-desc">
                Download all practice plans with their associated drills as a JSON file. Use this to back up your practice library or transfer plans to another system.
            </p>
            
            <div class="ei-stat">
                <span>Total Practice Plans</span>
                <span class="ei-stat-value"><?= number_format($plan_count) ?></span>
            </div>
            <div class="ei-stat">
                <span>Available Drills</span>
                <span class="ei-stat-value"><?= number_format($drill_count) ?></span>
            </div>
            
            <a href="process_practice_plans_export.php?csrf_token=<?= urlencode($csrf_token) ?>" class="btn btn-primary" style="width:100%;text-decoration:none;<?= $plan_count === 0 ? 'pointer-events:none;opacity:0.5;' : '' ?>">
                <i class="fas fa-download"></i> Export All Practice Plans (JSON)
            </a>
        </div>
    </div>
    
    <!-- Import Card -->
    <div class="card">
        <div class="card-body">
            <div class="ei-card-icon"><i class="fas fa-file-import"></i></div>
            <h3 class="ei-card-title">Import Practice Plans from File</h3>
            <p class="ei-card-desc">
                Upload a JSON file previously exported from this system to import practice plans and their associated drills.
            </p>
            
            <div class="alert alert-info">
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
                
                <button type="submit" class="btn btn-primary" style="width:100%;" id="plan-import-btn">
                    <i class="fas fa-upload"></i> Import Practice Plans
                </button>
            </form>
        </div>
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
    
    const progressContainer = document.getElementById('ei-progress-container');
    const progressBar = document.getElementById('ei-progress-bar');
    const progressText = document.getElementById('ei-progress-text');
    const progressDetail = document.getElementById('ei-progress-detail');
    
    // Read file to count items for progress display
    const reader = new FileReader();
    reader.onload = function(event) {
        let planCount = 0;
        let drillCount = 0;
        try {
            const json = JSON.parse(event.target.result);
            planCount = (json.practice_plans || []).length;
            (json.practice_plans || []).forEach(function(p) {
                drillCount += (p.drills || []).length;
            });
        } catch(e) { /* will be caught server-side */ }
        
        // Show progress bar
        progressContainer.style.display = 'block';
        progressBar.style.width = '10%';
        progressText.textContent = planCount > 0
            ? 'Importing ' + planCount + ' practice plan' + (planCount !== 1 ? 's' : '') + (drillCount > 0 ? ' with ' + drillCount + ' drill' + (drillCount !== 1 ? 's' : '') : '') + '…'
            : 'Importing practice plans…';
        progressDetail.textContent = 'Uploading file and processing. Images saving to cloud storage may take a moment…';
        
        // Animate progress bar to simulate progress
        let progress = 10;
        const progressInterval = setInterval(function() {
            if (progress < 85) {
                progress += Math.random() * 8;
                if (progress > 85) progress = 85;
                progressBar.style.width = progress + '%';
            }
        }, 800);
        
        const formData = new FormData(document.getElementById('plan-import-form'));
        
        fetch('process_practice_plans.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            clearInterval(progressInterval);
            progressBar.style.width = '100%';
            
            if (data.success) {
                progressText.textContent = 'Import complete!';
                progressDetail.textContent = data.message;
                setTimeout(function() {
                    progressContainer.style.display = 'none';
                    showEiAlert('success', data.message);
                }, 1500);
                fileInput.value = '';
            } else {
                progressContainer.style.display = 'none';
                showEiAlert('error', data.message);
            }
        })
        .catch(error => {
            clearInterval(progressInterval);
            progressContainer.style.display = 'none';
            showEiAlert('error', 'Import failed. Please try again.');
            console.error(error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-upload"></i> Import Practice Plans';
        });
    };
    reader.readAsText(fileInput.files[0]);
});

function showEiAlert(type, message) {
    const successEl = document.getElementById('ei-alert-success');
    const errorEl = document.getElementById('ei-alert-error');
    
    successEl.style.display = 'none';
    errorEl.style.display = 'none';
    
    if (type === 'success') {
        document.getElementById('ei-success-msg').textContent = message;
        successEl.style.display = 'flex';
    } else {
        document.getElementById('ei-error-msg').textContent = message;
        errorEl.style.display = 'flex';
    }
    
    setTimeout(() => {
        successEl.style.display = 'none';
        errorEl.style.display = 'none';
    }, 8000);
}
</script>
