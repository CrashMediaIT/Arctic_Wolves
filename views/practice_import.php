<?php
/**
 * Import Practice Plan from IHS (Ice Hockey Systems)
 * Allows importing practice plans by pasting an IHS URL
 * Creates all drills from the practice plan in the drill library
 */

// Fetch recently imported practice plans
$recentImportsQuery = "SELECT pp.*, u.first_name, u.last_name 
    FROM practice_plans pp 
    LEFT JOIN users u ON pp.created_by = u.id 
    WHERE pp.description LIKE '%Imported from IHS%'
    ORDER BY pp.created_at DESC 
    LIMIT 10";
$recentImports = $pdo->query($recentImportsQuery)->fetchAll(PDO::FETCH_ASSOC);
$recentImports = decryptUserRows($recentImports);

// Handle error and status messages
$error = $_GET['error'] ?? null;
$status = $_GET['status'] ?? null;

$error_messages = [
    'url_required' => 'Please enter a valid IHS practice plan URL.',
    'invalid_url' => 'The URL format is invalid. Please enter a valid URL.',
    'already_imported' => 'This practice plan has already been imported.',
    'import_failed' => 'Import failed. Please try again or contact support.',
    'title_required' => 'Practice plan name is required for import.',
    'permission_denied' => 'You do not have permission to import practice plans.',
    'untrusted_domain' => 'Only URLs from icehockeysystems.com are allowed for import.',
    'csrf_token_missing' => 'Security token missing. Please refresh the page and try again.',
    'csrf_token_invalid' => 'Security token expired. Please refresh the page and try again.',
    'fetch_failed' => 'Unable to fetch practice plan data from the URL. Please check the URL and try again.',
    'parse_failed' => 'Unable to parse practice plan data from the page. The page format may have changed.'
];

$status_messages = [
    'plan_imported' => 'Practice plan successfully imported! All drills have been added to your library.'
];
?>
<!-- Import Practice Plan from IHS View -->

<?php if ($error && isset($error_messages[$error])): ?>
<div class="alert alert-error">
    <i class="fas fa-exclamation-circle"></i>
    <?= htmlspecialchars($error_messages[$error]) ?>
</div>
<?php endif; ?>

<?php if ($status && isset($status_messages[$status])): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i>
    <?= htmlspecialchars($status_messages[$status]) ?>
</div>
<?php endif; ?>

<div class="import-content">
    <!-- Info Box -->
    <div class="info-box">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>How to Import a Practice Plan from IHS:</strong>
            <ol style="margin: 10px 0 0 20px; padding: 0;">
                <li>Go to <a href="https://www.icehockeysystems.com" target="_blank" rel="noopener noreferrer">icehockeysystems.com</a> and find the practice plan you want to import</li>
                <li>Copy the share URL (e.g., https://www.icehockeysystems.com/share/practice/...)</li>
                <li>Paste the URL below and click "Fetch Practice Plan"</li>
                <li>Review the practice plan details and drills</li>
                <li>Click "Import Practice Plan" - all drills will be created in your library</li>
            </ol>
        </div>
    </div>

    <!-- Import from URL Section -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-link"></i> Import from IHS URL</h3>
        </div>
        <div class="card-body">
            <div class="url-fetch-section">
                <div class="url-input-row">
                    <div class="url-input-group">
                        <i class="fas fa-link"></i>
                        <input type="url" class="form-input" id="ihsUrlInput" placeholder="https://www.icehockeysystems.com/share/practice/...">
                    </div>
                    <button type="button" class="btn-primary" id="fetchPlanBtn" onclick="fetchPracticePlanFromUrl()">
                        <i class="fas fa-search"></i> Fetch Practice Plan
                    </button>
                    <button type="button" class="btn-secondary" onclick="showManualEntry()">
                        <i class="fas fa-edit"></i> Manual Entry
                    </button>
                </div>
                <p class="url-help-text"><i class="fas fa-info-circle"></i> Paste a practice plan share URL from icehockeysystems.com, or click "Manual Entry" to enter details directly</p>
            </div>

            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="loading-indicator" style="display: none;">
                <div class="spinner"></div>
                <span>Fetching practice plan data...</span>
            </div>

            <!-- Practice Plan Preview Section (hidden until data is fetched) -->
            <div id="planPreviewSection" class="plan-preview-section" style="display: none;">
                <h4 class="preview-title"><i class="fas fa-eye"></i> Practice Plan Details</h4>
                
                <form method="POST" action="process_practice_plans.php" id="importPlanForm">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="import_ihs_practice_plan">
                    <input type="hidden" name="ihs_url" id="importIhsUrl">
                    <input type="hidden" name="drills_json" id="drillsJson">
                    
                    <div class="preview-header-section">
                        <div class="form-group">
                            <label class="form-label">Practice Plan Title <span class="required">*</span></label>
                            <input type="text" name="plan_title" id="previewTitle" class="form-input" required>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Duration (minutes)</label>
                                <input type="text" name="duration" id="previewDuration" class="form-input" placeholder="60">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Age Group</label>
                                <select name="age_group" id="previewAgeGroup" class="form-select">
                                    <option value="">-- Select --</option>
                                    <option value="U8">U8</option>
                                    <option value="U10">U10</option>
                                    <option value="U12">U12</option>
                                    <option value="U14">U14</option>
                                    <option value="U16">U16</option>
                                    <option value="U18">U18</option>
                                    <option value="Adult">Adult</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Focus Area</label>
                                <input type="text" name="focus_area" id="previewFocusArea" class="form-input" placeholder="e.g., Skating, Passing, Team Play">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="previewDescription" class="form-textarea" rows="2" placeholder="Practice plan description or goals"></textarea>
                        </div>
                    </div>
                    
                    <!-- Drills in Practice Plan -->
                    <div class="drills-preview-section">
                        <h5 class="drills-header">
                            <i class="fas fa-hockey-puck"></i> Drills in this Practice Plan <span id="drillCount">(0)</span>
                            <button type="button" class="btn-secondary btn-small" onclick="addDrillManually()" style="margin-left: auto;">
                                <i class="fas fa-plus"></i> Add Drill
                            </button>
                        </h5>
                        <p class="drills-note">All drills will be imported to your drill library. You can add, remove, or edit drills below.</p>
                        
                        <div id="drillsList" class="drills-list">
                            <!-- Drills will be populated by JavaScript -->
                        </div>
                    </div>
                    
                    <div class="import-actions">
                        <button type="button" class="btn-secondary" onclick="clearPreview()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <button type="submit" class="btn-primary" id="importPlanBtn">
                            <i class="fas fa-download"></i> Import Practice Plan & All Drills
                        </button>
                    </div>
                    
                    <!-- Import Progress Indicator -->
                    <div id="importProgressContainer" style="display:none; margin-top: 20px; text-align: center; padding: 20px; background: var(--bg-main, #06080b); border: 1px solid var(--border, #1e293b); border-radius: 8px;">
                        <div style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 12px;">
                            <i class="fas fa-spinner fa-spin" style="color: var(--primary, #7c3aed); font-size: 20px;"></i>
                            <span style="color: #fff; font-weight: 700;">Importing practice plan and drills…</span>
                        </div>
                        <div style="background: rgba(30, 41, 59, 0.5); border-radius: 999px; height: 6px; overflow: hidden; position: relative;">
                            <div id="importProgressBar" style="height: 100%; border-radius: 999px; background: linear-gradient(90deg, var(--primary, #7c3aed), #a855f7); width: 20%; transition: width 0.4s ease; position: relative; overflow: hidden;">
                                <div style="position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent); animation: importShimmer 1.5s infinite;"></div>
                            </div>
                        </div>
                        <p style="color: var(--text-dim, #64748b); font-size: 12px; margin-top: 8px;">Creating practice plan, saving drills and uploading images to cloud storage…</p>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Recently Imported -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recently Imported Practice Plans</h3>
        </div>
        <div class="card-body">
            <div class="recent-imports-list">
                <?php if(!empty($recentImports)): ?>
                    <?php foreach($recentImports as $import): ?>
                        <div class="import-history-item">
                            <div class="import-thumbnail">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="import-info">
                                <h4><?= htmlspecialchars($import['name'] ?? 'Imported Practice Plan') ?></h4>
                                <span class="import-meta">
                                    Imported by <?= htmlspecialchars(($import['first_name'] ?? '') . ' ' . ($import['last_name'] ?? '')) ?> 
                                    on <?= date('M j, Y', strtotime($import['created_at'])) ?>
                                </span>
                            </div>
                            <a href="?page=practice_library" class="btn-secondary btn-small"><i class="fas fa-eye"></i> View</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text">No imported practice plans yet. Import your first plan using the form above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentPlanData = null;

// Show progress bar when import form is submitted
document.getElementById('importPlanForm').addEventListener('submit', function() {
    var btn = document.getElementById('importPlanBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Importing…';
    var container = document.getElementById('importProgressContainer');
    container.style.display = 'block';
    var bar = document.getElementById('importProgressBar');
    var progress = 20;
    var interval = setInterval(function() {
        if (progress < 85) {
            progress += Math.random() * 10;
            if (progress > 85) progress = 85;
            bar.style.width = progress + '%';
        } else {
            clearInterval(interval);
        }
    }, 600);
});

async function fetchPracticePlanFromUrl() {
    const urlInput = document.getElementById('ihsUrlInput');
    const url = urlInput.value.trim();
    
    if (!url) {
        showNotification('Please enter a URL', 'error');
        return;
    }
    
    // Validate URL is from icehockeysystems.com
    if (!url.includes('icehockeysystems.com')) {
        showNotification('Only URLs from icehockeysystems.com are supported', 'error');
        return;
    }
    
    // Show loading indicator
    document.getElementById('loadingIndicator').style.display = 'flex';
    document.getElementById('planPreviewSection').style.display = 'none';
    document.getElementById('fetchPlanBtn').disabled = true;
    
    try {
        const response = await fetch('process_practice_plans.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'fetch_ihs_practice_plan',
                ihs_url: url,
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentPlanData = data.plan;
            populatePlanPreview(data.plan);
            document.getElementById('planPreviewSection').style.display = 'block';
            document.getElementById('importIhsUrl').value = url;
            showNotification('Practice plan data fetched successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to fetch practice plan data', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showNotification('An error occurred while fetching the practice plan', 'error');
    } finally {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('fetchPlanBtn').disabled = false;
    }
}

function populatePlanPreview(plan) {
    document.getElementById('previewTitle').value = plan.title || '';
    document.getElementById('previewDescription').value = plan.description || '';
    document.getElementById('previewDuration').value = plan.duration || '';
    document.getElementById('previewFocusArea').value = plan.focus_area || '';
    
    // Set age group if matched
    if (plan.age_group) {
        const ageSelect = document.getElementById('previewAgeGroup');
        for (let option of ageSelect.options) {
            if (option.value.toLowerCase() === plan.age_group.toLowerCase()) {
                option.selected = true;
                break;
            }
        }
    }
    
    // Populate drills list
    const drillsList = document.getElementById('drillsList');
    const drills = plan.drills || [];
    
    document.getElementById('drillCount').textContent = '(' + drills.length + ')';
    updateDrillsJson(drills);
    renderDrillsList(drills);
}

let currentDrills = [];

function renderDrillsList(drills) {
    currentDrills = drills;
    const drillsList = document.getElementById('drillsList');
    document.getElementById('drillCount').textContent = '(' + drills.length + ')';
    
    // Clear existing content
    drillsList.innerHTML = '';
    
    if (drills.length === 0) {
        const p = document.createElement('p');
        p.className = 'no-drills-message';
        p.textContent = 'No drills added yet. Click "Add Drill" to add drills to this practice plan.';
        drillsList.appendChild(p);
    } else {
        drills.forEach((drill, index) => {
            const drillItem = createDrillElement(drill, index);
            drillsList.appendChild(drillItem);
        });
    }
}

// Create drill element using safe DOM methods
function createDrillElement(drill, index) {
    const container = document.createElement('div');
    container.className = 'drill-preview-item';
    container.dataset.index = index;
    
    // Order number
    const orderDiv = document.createElement('div');
    orderDiv.className = 'drill-order';
    orderDiv.textContent = index + 1;
    container.appendChild(orderDiv);
    
    // Image preview
    const imageDiv = document.createElement('div');
    imageDiv.className = 'drill-image-preview';
    if (drill.rink_image) {
        const img = document.createElement('img');
        img.src = drill.rink_image;
        img.alt = 'Drill diagram';
        img.onerror = function() {
            this.parentElement.innerHTML = '';
            const icon = document.createElement('i');
            icon.className = 'fas fa-hockey-puck';
            this.parentElement.appendChild(icon);
        };
        imageDiv.appendChild(img);
    } else {
        const icon = document.createElement('i');
        icon.className = 'fas fa-hockey-puck';
        imageDiv.appendChild(icon);
    }
    container.appendChild(imageDiv);
    
    // Details
    const detailsDiv = document.createElement('div');
    detailsDiv.className = 'drill-details';
    
    const titleInput = document.createElement('input');
    titleInput.type = 'text';
    titleInput.className = 'form-input drill-title-input';
    titleInput.value = drill.title || '';
    titleInput.placeholder = 'Drill title';
    titleInput.addEventListener('change', function() { updateDrill(index, 'title', this.value); });
    detailsDiv.appendChild(titleInput);
    
    const durationInput = document.createElement('input');
    durationInput.type = 'text';
    durationInput.className = 'form-input drill-duration-input';
    durationInput.value = drill.duration || '';
    durationInput.placeholder = 'Duration (min)';
    durationInput.addEventListener('change', function() { updateDrill(index, 'duration', this.value); });
    detailsDiv.appendChild(durationInput);
    
    const descTextarea = document.createElement('textarea');
    descTextarea.className = 'form-textarea drill-desc-input';
    descTextarea.placeholder = 'Description, setup, coaching points...';
    descTextarea.value = drill.description || '';
    descTextarea.addEventListener('change', function() { updateDrill(index, 'description', this.value); });
    detailsDiv.appendChild(descTextarea);
    
    const imageInput = document.createElement('input');
    imageInput.type = 'text';
    imageInput.className = 'form-input drill-image-input';
    imageInput.value = drill.rink_image || '';
    imageInput.placeholder = 'Image URL';
    imageInput.addEventListener('change', function() { updateDrillImage(index, this.value); });
    detailsDiv.appendChild(imageInput);
    
    container.appendChild(detailsDiv);
    
    // Actions
    const actionsDiv = document.createElement('div');
    actionsDiv.className = 'drill-actions';
    
    const removeBtn = document.createElement('button');
    removeBtn.type = 'button';
    removeBtn.className = 'btn-icon';
    removeBtn.title = 'Remove drill';
    removeBtn.addEventListener('click', function() { removeDrill(index); });
    const trashIcon = document.createElement('i');
    trashIcon.className = 'fas fa-trash';
    removeBtn.appendChild(trashIcon);
    actionsDiv.appendChild(removeBtn);
    
    container.appendChild(actionsDiv);
    
    return container;
}

function updateDrill(index, field, value) {
    if (currentDrills[index]) {
        currentDrills[index][field] = value;
        updateDrillsJson(currentDrills);
    }
}

function updateDrillImage(index, value) {
    if (currentDrills[index]) {
        currentDrills[index].rink_image = value;
        updateDrillsJson(currentDrills);
        
        // Update the image preview using safe DOM methods
        const drillItem = document.querySelector(`.drill-preview-item[data-index="${index}"] .drill-image-preview`);
        if (drillItem) {
            drillItem.innerHTML = '';
            if (value) {
                const img = document.createElement('img');
                img.src = value;
                img.alt = 'Drill diagram';
                img.onerror = function() {
                    this.parentElement.innerHTML = '';
                    const icon = document.createElement('i');
                    icon.className = 'fas fa-exclamation-triangle';
                    this.parentElement.appendChild(icon);
                };
                drillItem.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = 'fas fa-hockey-puck';
                drillItem.appendChild(icon);
            }
        }
    }
}

function removeDrill(index) {
    currentDrills.splice(index, 1);
    updateDrillsJson(currentDrills);
    renderDrillsList(currentDrills);
}

function addDrillManually() {
    const newDrill = {
        title: '',
        description: '',
        setup: '',
        coaching_points: '',
        progression: '',
        rink_image: '',
        duration: ''
    };
    currentDrills.push(newDrill);
    updateDrillsJson(currentDrills);
    renderDrillsList(currentDrills);
    
    // Focus on the new drill's title input
    setTimeout(() => {
        const inputs = document.querySelectorAll('.drill-title-input');
        if (inputs.length > 0) {
            inputs[inputs.length - 1].focus();
        }
    }, 100);
}

function updateDrillsJson(drills) {
    document.getElementById('drillsJson').value = JSON.stringify(drills);
}

function showManualEntry() {
    // Show the preview section with empty fields for manual entry
    document.getElementById('planPreviewSection').style.display = 'block';
    document.getElementById('importIhsUrl').value = document.getElementById('ihsUrlInput').value || '';
    
    // Clear all fields
    document.getElementById('previewTitle').value = '';
    document.getElementById('previewDescription').value = '';
    document.getElementById('previewDuration').value = '60';
    document.getElementById('previewFocusArea').value = '';
    document.getElementById('previewAgeGroup').value = '';
    
    // Clear drills
    currentDrills = [];
    updateDrillsJson([]);
    renderDrillsList([]);
    
    // Focus on the title field
    document.getElementById('previewTitle').focus();
    
    showNotification('Enter practice plan details manually. Click "Add Drill" to add drills.', 'info');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function clearPreview() {
    document.getElementById('planPreviewSection').style.display = 'none';
    document.getElementById('ihsUrlInput').value = '';
    currentPlanData = null;
    currentDrills = [];
}

function showNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'notification-toast notification-' + type;
    const icon = document.createElement('i');
    icon.className = 'fas fa-' + (type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle');
    alertDiv.appendChild(icon);
    alertDiv.appendChild(document.createTextNode(' ' + message));
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 4000);
}
</script>

<style>
/* Alert Styles */
.alert {
    padding: 14px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
}

.alert-error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
}

.alert-success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid rgba(16, 185, 129, 0.3);
    color: #10b981;
}

/* Info Box */
.info-box {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid #3b82f6;
    color: #93c5fd;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 24px;
    display: flex;
    gap: 15px;
    font-size: 14px;
    line-height: 1.6;
}

.info-box i {
    color: #3b82f6;
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.info-box a {
    color: #60a5fa;
    text-decoration: underline;
}

.info-box ol li {
    margin-bottom: 6px;
}

/* Content Card */
.content-card {
    background: var(--bg-card, #0d1117);
    border: 1px solid var(--border, #1e293b);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}

.card-header {
    background: var(--bg-main, #06080b);
    padding: 16px 20px;
    border-bottom: 1px solid var(--border, #1e293b);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #fff);
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header h3 i {
    color: var(--primary, #7c3aed);
}

.card-body {
    padding: 24px;
}

/* URL Input Section */
.url-fetch-section {
    margin-bottom: 24px;
}

.url-input-row {
    display: flex;
    gap: 12px;
    align-items: stretch;
    margin-bottom: 10px;
}

.url-input-group {
    flex: 1;
    position: relative;
    display: flex;
    align-items: center;
}

.url-input-group i {
    position: absolute;
    left: 14px;
    color: var(--primary, #7c3aed);
    font-size: 16px;
    z-index: 1;
}

.url-input-group .form-input {
    padding-left: 42px;
    width: 100%;
    padding: 12px 14px 12px 42px;
    background: var(--bg-main, #06080b);
    border: 1px solid var(--border, #1e293b);
    border-radius: 6px;
    color: var(--text-white, #fff);
    font-size: 14px;
}

.url-input-group .form-input:focus {
    outline: none;
    border-color: var(--primary, #7c3aed);
}

.url-help-text {
    font-size: 12px;
    color: var(--text-dim, #64748b);
    display: flex;
    align-items: center;
    gap: 6px;
}

.url-help-text i {
    color: var(--primary, #7c3aed);
}

/* Loading Indicator */
.loading-indicator {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 40px;
    color: var(--text-dim, #64748b);
}

.spinner {
    width: 24px;
    height: 24px;
    border: 3px solid var(--border, #1e293b);
    border-top-color: var(--primary, #7c3aed);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes importShimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}

/* Practice Plan Preview Section */
.plan-preview-section {
    border-top: 1px solid var(--border, #1e293b);
    padding-top: 24px;
    margin-top: 24px;
}

.preview-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.preview-title i {
    color: var(--primary, #7c3aed);
}

.preview-header-section {
    margin-bottom: 24px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 16px;
}

/* Form Elements */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-bottom: 16px;
}

.form-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--text-dim, #64748b);
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.form-label i {
    color: var(--primary, #7c3aed);
}

.form-input,
.form-select,
.form-textarea {
    padding: 10px 14px;
    background: var(--bg-main, #06080b);
    border: 1px solid var(--border, #1e293b);
    border-radius: 6px;
    color: var(--text-white, #fff);
    font-size: 14px;
    font-family: inherit;
}

.form-input:focus,
.form-select:focus,
.form-textarea:focus {
    outline: none;
    border-color: var(--primary, #7c3aed);
}

.form-textarea {
    resize: vertical;
    min-height: 80px;
}

/* Drills Preview Section */
.drills-preview-section {
    margin-bottom: 24px;
}

.drills-header {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.drills-header i {
    color: var(--primary, #7c3aed);
}

.drills-header span {
    color: var(--text-dim, #64748b);
    font-weight: 400;
}

.drills-note {
    font-size: 13px;
    color: var(--text-dim, #64748b);
    margin-bottom: 16px;
}

.drills-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-height: 400px;
    overflow-y: auto;
}

.drill-preview-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main, #06080b);
    border: 1px solid var(--border, #1e293b);
    border-radius: 8px;
    align-items: flex-start;
}

.drill-order {
    width: 32px;
    height: 32px;
    background: var(--primary, #7c3aed);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 14px;
    flex-shrink: 0;
}

.drill-image-preview {
    width: 100px;
    height: 75px;
    background: rgba(124, 58, 237, 0.1);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--primary, #7c3aed);
    flex-shrink: 0;
    overflow: hidden;
}

.drill-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.drill-details {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.drill-details h5 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin-bottom: 4px;
}

.drill-title-input {
    font-size: 14px;
    font-weight: 600;
    padding: 8px 12px;
}

.drill-duration-input {
    font-size: 12px;
    padding: 6px 10px;
    width: 120px;
}

.drill-desc-input {
    font-size: 13px;
    min-height: 60px;
    resize: vertical;
}

.drill-image-input {
    font-size: 11px;
    padding: 6px 10px;
    color: var(--text-dim, #64748b);
}

.drill-duration {
    font-size: 12px;
    color: var(--primary, #7c3aed);
    font-weight: 600;
    margin-bottom: 4px;
}

.drill-desc {
    font-size: 13px;
    color: var(--text-dim, #64748b);
    line-height: 1.4;
}

.drill-actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.btn-icon {
    width: 32px;
    height: 32px;
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    border-radius: 6px;
    color: #ef4444;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: 0.2s;
}

.btn-icon:hover {
    background: rgba(239, 68, 68, 0.2);
    border-color: #ef4444;
}

.no-drills-message {
    color: var(--text-dim, #64748b);
    font-size: 14px;
    text-align: center;
    padding: 20px;
}

.form-label .required {
    color: #ef4444;
}

/* Import Actions */
.import-actions {
    display: flex;
    gap: 12px;
    justify-content: flex-end;
    padding-top: 20px;
    border-top: 1px solid var(--border, #1e293b);
}

/* Buttons */
.btn-primary {
    padding: 12px 24px;
    background: var(--primary, #7c3aed);
    color: #fff;
    border: none;
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary:hover:not(:disabled) {
    background: #5a0080;
    transform: translateY(-2px);
}

.btn-primary:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.btn-secondary {
    padding: 12px 24px;
    background: var(--bg-main, #1e293b);
    color: #fff;
    border: 1px solid var(--border, #1e293b);
    border-radius: 6px;
    font-weight: 700;
    cursor: pointer;
    transition: 0.2s;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-secondary:hover {
    background: #2d3b52;
}

.btn-small {
    padding: 8px 16px;
    font-size: 12px;
}

/* Recent Imports */
.recent-imports-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.import-history-item {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 16px;
    background: var(--bg-main, #06080b);
    border: 1px solid var(--border, #1e293b);
    border-radius: 8px;
    transition: border-color 0.3s;
}

.import-history-item:hover {
    border-color: var(--primary, #7c3aed);
}

.import-thumbnail {
    width: 60px;
    height: 45px;
    background: rgba(124, 58, 237, 0.1);
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--primary, #7c3aed);
    flex-shrink: 0;
}

.import-info {
    flex: 1;
    min-width: 0;
}

.import-info h4 {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-white, #fff);
    margin-bottom: 4px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.import-meta {
    font-size: 12px;
    color: var(--text-dim, #64748b);
    display: block;
}

.placeholder-text {
    color: var(--text-dim, #64748b);
    font-size: 14px;
    text-align: center;
    padding: 40px 20px;
}

/* Notification Toast */
.notification-toast {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 10000;
    min-width: 300px;
    padding: 15px 20px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    animation: slideIn 0.3s ease;
}

.notification-error {
    background: rgba(239, 68, 68, 0.9);
    color: #fff;
}

.notification-success {
    background: rgba(16, 185, 129, 0.9);
    color: #fff;
}

.notification-info {
    background: rgba(59, 130, 246, 0.9);
    color: #fff;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(100px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .url-input-row {
        flex-direction: column;
    }
    
    .url-input-row .btn-primary {
        width: 100%;
    }
    
    .import-actions {
        flex-direction: column;
    }
    
    .import-actions button {
        width: 100%;
    }
    
    .drill-preview-item {
        flex-direction: column;
    }
    
    .drill-image-preview {
        width: 100%;
        height: 150px;
    }
}
</style>
