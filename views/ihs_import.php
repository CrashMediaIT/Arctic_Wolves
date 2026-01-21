<?php
/**
 * IHS Import Interface
 * Import drills and practice plans from IHS Hockey
 */

require_once __DIR__ . '/../security.php';

// Check permission
requirePermission($pdo, $user_id, $user_role, 'import_from_ihs');
?>

<style>
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
    }
    .import-section {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 25px;
    }
    .section-title {
        font-size: 18px;
        font-weight: 700;
        color: var(--primary);
        margin-bottom: 15px;
    }
    .info-box {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        color: #3b82f6;
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 13px;
        line-height: 1.6;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
    }
    .form-input, .form-textarea {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
    }
    .form-textarea {
        min-height: 200px;
        font-family: 'Courier New', monospace;
    }
    .btn {
        padding: 12px 24px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        transition: 0.2s;
        font-size: 14px;
    }
    .btn:hover {
        background: #5a0080;
        transform: translateY(-2px);
    }
    .btn-secondary {
        background: #1e293b;
    }
    .btn-secondary:hover {
        background: #2d3b52;
    }
    .import-preview {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-top: 15px;
        max-height: 400px;
        overflow-y: auto;
    }
    .preview-item {
        padding: 10px;
        border-bottom: 1px solid #1e293b;
        color: #94a3b8;
        font-size: 13px;
    }
    .preview-item:last-child {
        border-bottom: none;
    }
    .warning-box {
        background: rgba(255, 77, 0, 0.1);
        border: 1px solid var(--primary);
        color: var(--primary);
        padding: 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 13px;
    }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-file-import"></i> IHS Import</h1>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-success">
        <?php
        if ($_GET['status'] === 'drills_imported') {
            $count = $_GET['count'] ?? 0;
            $skipped = $_GET['skipped'] ?? 0;
            echo "<i class='fas fa-check-circle'></i> Successfully imported $count drill(s)";
            if ($skipped > 0) echo ". Skipped $skipped duplicate(s)";
            echo ".";
        } elseif ($_GET['status'] === 'plan_imported') {
            echo "<i class='fas fa-check-circle'></i> Practice plan imported successfully!";
        }
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444;">
        <?php
        $errors = [
            'no_data' => 'No data provided. Please paste your import data.',
            'invalid_json' => 'Invalid JSON format. Please check your data.',
            'missing_title' => 'Practice plan title is required.',
            'import_failed' => 'Import failed. Please check your data format and try again.'
        ];
        echo "<i class='fas fa-exclamation-triangle'></i> " . ($errors[$_GET['error']] ?? 'An error occurred.');
        ?>
    </div>
<?php endif; ?>

<div class="info-box">
    <i class="fas fa-info-circle"></i>
    <strong>About IHS Import:</strong> This feature allows you to import drills and practice plans from 
    IHS Hockey (International Hockey School) format. You can import individual drills or complete practice plans.
    All imported content will be tagged with <code>imported_from_ihs = 1</code> for tracking purposes.
</div>

<!-- Import Drills Section -->
<div class="import-section">
    <div class="section-title">
        <i class="fas fa-hockey-puck"></i> Import Drills
    </div>
    
    <form method="POST" action="process_ihs_import.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="import_drills">
        
        <div class="form-group">
            <label class="form-label">IHS Drill Data (JSON or XML)</label>
            <textarea name="drill_data" class="form-textarea" placeholder='Paste IHS drill data here...

Example JSON format:
{
  "drills": [
    {
      "title": "2-on-1 Rush Drill",
      "description": "Offensive rush drill focusing on decision making",
      "duration": 15,
      "skill_level": "intermediate",
      "equipment": "Cones, pucks",
      "coaching_points": "Keep head up, support the puck carrier"
    }
  ]
}'></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Import Options</label>
            <label style="color: #94a3b8; font-size: 13px; display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <input type="checkbox" name="auto_categorize" value="1" style="accent-color: var(--primary);">
                Automatically assign categories based on drill type
            </label>
            <label style="color: #94a3b8; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="skip_duplicates" value="1" checked style="accent-color: var(--primary);">
                Skip drills with duplicate titles
            </label>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="previewDrills()">
                <i class="fas fa-eye"></i> Preview
            </button>
            <button type="submit" class="btn">
                <i class="fas fa-upload"></i> Import Drills
            </button>
        </div>
        
        <div id="drill-preview" class="import-preview" style="display: none;">
            <!-- Preview will be populated by JavaScript -->
        </div>
    </form>
</div>

<!-- Import Practice Plans Section -->
<div class="import-section">
    <div class="section-title">
        <i class="fas fa-clipboard-list"></i> Import Practice Plans
    </div>
    
    <div class="warning-box">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Note:</strong> Practice plan import requires that referenced drills already exist in your library.
        If drills are missing, they will be created automatically.
    </div>
    
    <form method="POST" action="process_ihs_import.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="import_plans">
        
        <div class="form-group">
            <label class="form-label">IHS Practice Plan Data (JSON or XML)</label>
            <textarea name="plan_data" class="form-textarea" placeholder='Paste IHS practice plan data here...

Example JSON format:
{
  "practice_plan": {
    "title": "U12 Skill Development",
    "description": "Focus on skating and puck handling",
    "total_duration": 60,
    "age_group": "U12",
    "drills": [
      {
        "title": "Warm-up Skating",
        "duration": 10,
        "notes": "Focus on proper stride technique"
      }
    ]
  }
}'></textarea>
        </div>
        
        <div class="form-group">
            <label class="form-label">Import Options</label>
            <label style="color: #94a3b8; font-size: 13px; display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <input type="checkbox" name="create_missing_drills" value="1" checked style="accent-color: var(--primary);">
                Create drills that don't exist
            </label>
            <label style="color: #94a3b8; font-size: 13px; display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="make_public" value="1" style="accent-color: var(--primary);">
                Make imported plans public
            </label>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="button" class="btn btn-secondary" onclick="previewPlans()">
                <i class="fas fa-eye"></i> Preview
            </button>
            <button type="submit" class="btn">
                <i class="fas fa-upload"></i> Import Practice Plan
            </button>
        </div>
        
        <div id="plan-preview" class="import-preview" style="display: none;">
            <!-- Preview will be populated by JavaScript -->
        </div>
    </form>
</div>

<!-- Import History -->
<div class="import-section">
    <div class="section-title">
        <i class="fas fa-history"></i> Recent Imports
    </div>
    
    <?php
    // Get recent IHS imports
    $imports = $pdo->query("
        SELECT 'drill' as type, id, title, created_at 
        FROM drills 
        WHERE imported_from_ihs = 1 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    $plan_imports = $pdo->query("
        SELECT 'plan' as type, id, title, created_at 
        FROM practice_plans 
        WHERE imported_from_ihs = 1 
        ORDER BY created_at DESC 
        LIMIT 5
    ")->fetchAll();
    
    $all_imports = array_merge($imports, $plan_imports);
    usort($all_imports, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $all_imports = array_slice($all_imports, 0, 10);
    ?>
    
    <?php if (empty($all_imports)): ?>
        <p style="color: #64748b; font-size: 14px;">No imports yet.</p>
    <?php else: ?>
        <?php foreach ($all_imports as $import): ?>
            <div class="preview-item">
                <i class="fas fa-<?= $import['type'] == 'drill' ? 'hockey-puck' : 'clipboard-list' ?>"></i>
                <strong><?= htmlspecialchars($import['title']) ?></strong>
                <span style="color: #64748b; margin-left: 10px;">
                    <?= date('M d, Y', strtotime($import['created_at'])) ?>
                </span>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function previewDrills() {
    const data = document.querySelector('textarea[name="drill_data"]').value;
    const preview = document.getElementById('drill-preview');
    
    if (!data.trim()) {
        alert('Please paste drill data first');
        return;
    }
    
    try {
        const parsed = JSON.parse(data);
        const drills = parsed.drills || [parsed];
        
        let html = '<h4 style="color: #fff; margin-bottom: 10px;">Preview: ' + drills.length + ' drill(s)</h4>';
        drills.forEach((drill, i) => {
            html += '<div class="preview-item">';
            html += '<strong>' + (i + 1) + '. ' + (drill.title || 'Untitled') + '</strong><br>';
            html += drill.description || 'No description';
            html += '</div>';
        });
        
        preview.innerHTML = html;
        preview.style.display = 'block';
    } catch (e) {
        alert('Invalid JSON format. Please check your data.');
    }
}

function previewPlans() {
    const data = document.querySelector('textarea[name="plan_data"]').value;
    const preview = document.getElementById('plan-preview');
    
    if (!data.trim()) {
        alert('Please paste practice plan data first');
        return;
    }
    
    try {
        const parsed = JSON.parse(data);
        const plan = parsed.practice_plan || parsed;
        
        let html = '<h4 style="color: #fff; margin-bottom: 10px;">Preview: ' + (plan.title || 'Untitled') + '</h4>';
        html += '<div class="preview-item">';
        html += '<strong>Duration:</strong> ' + (plan.total_duration || 'N/A') + ' minutes<br>';
        html += '<strong>Age Group:</strong> ' + (plan.age_group || 'N/A') + '<br>';
        html += '<strong>Drills:</strong> ' + ((plan.drills || []).length) + ' drills';
        html += '</div>';
        
        preview.innerHTML = html;
        preview.style.display = 'block';
    } catch (e) {
        alert('Invalid JSON format. Please check your data.');
    }
}
</script>
