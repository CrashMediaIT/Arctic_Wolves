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
