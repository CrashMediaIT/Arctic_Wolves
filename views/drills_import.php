<?php
/**
 * Import Drill from IHS (Ice Hockey Systems)
 * Allows importing drills by pasting an IHS URL
 * Fetches drill images, description, setup, coaching points and progressions
 */
require_once __DIR__ . '/../lib/image_helper.php';

// Fetch recently imported drills
$recentImportsQuery = "SELECT d.*, u.first_name, u.last_name 
    FROM drills d 
    LEFT JOIN users u ON d.created_by = u.id 
    WHERE d.ihs_source_url IS NOT NULL 
    ORDER BY d.created_at DESC 
    LIMIT 10";
$recentImports = $pdo->query($recentImportsQuery)->fetchAll(PDO::FETCH_ASSOC);
$recentImports = decryptUserRows($recentImports);

// Handle error and status messages
$error = $_GET['error'] ?? null;
$status = $_GET['status'] ?? null;

$error_messages = [
    'url_required' => 'Please enter a valid IHS drill URL.',
    'invalid_url' => 'The URL format is invalid. Please enter a valid URL.',
    'already_imported' => 'This drill has already been imported to your library.',
    'import_failed' => 'Import failed. Please try again or contact support.',
    'title_required' => 'Drill name is required for import.',
    'permission_denied' => 'You do not have permission to import drills.',
    'untrusted_domain' => 'Only URLs from icehockeysystems.com are allowed for import.',
    'csrf_token_missing' => 'Security token missing. Please refresh the page and try again.',
    'csrf_token_invalid' => 'Security token expired. Please refresh the page and try again.',
    'fetch_failed' => 'Unable to fetch drill data from the URL. Please check the URL and try again.',
    'parse_failed' => 'Unable to parse drill data from the page. The page format may have changed.'
];

$status_messages = [
    'drill_imported' => 'Drill successfully imported to your library!'
];
?>
<!-- Import Drill from IHS View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-file-import"></i> Import from IHS
    </h1>
    <p class="page-description">Import drills from Ice Hockey Systems by pasting a drill URL</p>
</div>

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
            <strong>How to Import a Drill from IHS:</strong>
            <ol style="margin: 10px 0 0 20px; padding: 0;">
                <li>Go to <a href="https://www.icehockeysystems.com" target="_blank" rel="noopener noreferrer">icehockeysystems.com</a> and find the drill you want to import</li>
                <li>Copy the URL from your browser's address bar</li>
                <li>Paste the URL below and click "Fetch Drill"</li>
                <li>Review the drill details and click "Import to Library"</li>
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
                        <input type="url" class="form-input" id="ihsUrlInput" placeholder="https://www.icehockeysystems.com/hockey-drills/...">
                    </div>
                    <button type="button" class="btn-primary" id="fetchDrillBtn" onclick="fetchDrillFromUrl()">
                        <i class="fas fa-search"></i> Fetch Drill
                    </button>
                    <button type="button" class="btn-secondary" onclick="showManualEntry()">
                        <i class="fas fa-edit"></i> Manual Entry
                    </button>
                </div>
                <p class="url-help-text"><i class="fas fa-info-circle"></i> Paste a drill URL from icehockeysystems.com, or click "Manual Entry" to enter drill details directly</p>
            </div>

            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="loading-indicator" style="display: none;">
                <div class="spinner"></div>
                <span>Fetching drill data...</span>
            </div>

            <!-- Drill Preview Section (hidden until data is fetched) -->
            <div id="drillPreviewSection" class="drill-preview-section" style="display: none;">
                <h4 class="preview-title"><i class="fas fa-eye"></i> Drill Preview</h4>
                
                <form method="POST" action="process_drills.php" id="importDrillForm">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="import_ihs_url">
                    <input type="hidden" name="ihs_url" id="importIhsUrl">
                    
                    <div class="preview-content">
                        <!-- Rink Image Preview -->
                        <div class="preview-image-section">
                            <label class="form-label">Rink Diagram</label>
                            <div class="rink-image-preview" id="rinkImagePreview">
                                <i class="fas fa-hockey-puck placeholder-icon"></i>
                                <span>No image available</span>
                            </div>
                            <input type="text" name="rink_image_url" id="rinkImageUrl" class="form-input" placeholder="Image URL (optional)" style="margin-top: 10px;">
                        </div>
                        
                        <!-- Drill Details -->
                        <div class="preview-details-section">
                            <div class="form-group">
                                <label class="form-label">Drill Title <span class="required">*</span></label>
                                <input type="text" name="drill_title" id="previewTitle" class="form-input" required>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Category</label>
                                <select name="category" id="previewCategory" class="form-select">
                                    <option value="">-- Select Category --</option>
                                    <?php
                                    $categories = $pdo->query("SELECT id, name FROM drill_categories ORDER BY name")->fetchAll();
                                    foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['name']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Description</label>
                                <textarea name="description" id="previewDescription" class="form-textarea" rows="3"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Sections: Setup, Coaching Points, Progression -->
                    <div class="preview-sections">
                        <div class="preview-section-item">
                            <label class="form-label"><i class="fas fa-cog"></i> Setup</label>
                            <textarea name="setup" id="previewSetup" class="form-textarea" rows="3"></textarea>
                        </div>
                        
                        <div class="preview-section-item">
                            <label class="form-label"><i class="fas fa-bullseye"></i> Coaching Points</label>
                            <textarea name="coaching_points" id="previewCoachingPoints" class="form-textarea" rows="3"></textarea>
                        </div>
                        
                        <div class="preview-section-item">
                            <label class="form-label"><i class="fas fa-level-up-alt"></i> Progression</label>
                            <textarea name="progression" id="previewProgression" class="form-textarea" rows="3"></textarea>
                        </div>
                    </div>
                    
                    <div class="import-actions">
                        <button type="button" class="btn-secondary" onclick="clearPreview()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                        <button type="submit" class="btn-primary" id="importDrillSubmitBtn">
                            <i class="fas fa-download"></i> Import to Library
                        </button>
                    </div>
                </form>

                <!-- Import Progress Overlay -->
                <div id="importProgressOverlay" class="import-progress-overlay" style="display: none;">
                    <div class="import-progress-card">
                        <div class="spinner"></div>
                        <h4>Importing Drill...</h4>
                        <p class="import-progress-text">Downloading images and saving to library. This may take a moment.</p>
                        <div class="import-progress-bar-container">
                            <div class="import-progress-bar" id="importProgressBar"></div>
                        </div>
                        <span class="import-progress-status" id="importProgressStatus">Please wait...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recently Imported -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Recently Imported Drills</h3>
        </div>
        <div class="card-body">
            <div class="recent-imports-list">
                <?php if(!empty($recentImports)): ?>
                    <?php foreach($recentImports as $import): ?>
                        <div class="import-history-item">
                            <div class="import-thumbnail">
                                <?php if (!empty($import['custom_image'])): ?>
                                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $import['custom_image'])) ?>" alt="Drill image">
                                <?php else: ?>
                                    <i class="fas fa-hockey-puck"></i>
                                <?php endif; ?>
                            </div>
                            <div class="import-info">
                                <h4><?= htmlspecialchars($import['title'] ?? 'Imported Drill') ?></h4>
                                <span class="import-meta">
                                    Imported by <?= htmlspecialchars(($import['first_name'] ?? '') . ' ' . ($import['last_name'] ?? '')) ?> 
                                    on <?= date('M j, Y', strtotime($import['created_at'])) ?>
                                </span>
                                <?php if (!empty($import['ihs_source_url'])): ?>
                                    <a href="<?= htmlspecialchars($import['ihs_source_url']) ?>" target="_blank" class="source-link">
                                        <i class="fas fa-external-link-alt"></i> View Original
                                    </a>
                                <?php endif; ?>
                            </div>
                            <a href="?page=view_drill&id=<?= $import['id'] ?>" class="btn-secondary btn-small"><i class="fas fa-eye"></i> View</a>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text">No imported drills yet. Import your first drill using the form above.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
let currentDrillData = null;

async function fetchDrillFromUrl() {
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
    document.getElementById('drillPreviewSection').style.display = 'none';
    document.getElementById('fetchDrillBtn').disabled = true;
    
    try {
        const response = await fetch('process_drills.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({
                action: 'fetch_ihs_drill',
                ihs_url: url,
                csrf_token: document.querySelector('input[name="csrf_token"]').value
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentDrillData = data.drill;
            populatePreview(data.drill);
            document.getElementById('drillPreviewSection').style.display = 'block';
            document.getElementById('importIhsUrl').value = url;
            showNotification('Drill data fetched successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to fetch drill data', 'error');
        }
    } catch (error) {
        console.error('Fetch error:', error);
        showNotification('An error occurred while fetching the drill', 'error');
    } finally {
        document.getElementById('loadingIndicator').style.display = 'none';
        document.getElementById('fetchDrillBtn').disabled = false;
    }
}

function populatePreview(drill) {
    document.getElementById('previewTitle').value = drill.title || '';
    document.getElementById('previewDescription').value = drill.description || '';
    document.getElementById('previewSetup').value = drill.setup || '';
    document.getElementById('previewCoachingPoints').value = drill.coaching_points || '';
    document.getElementById('previewProgression').value = drill.progression || '';
    
    // Set category if matched
    if (drill.category) {
        const categorySelect = document.getElementById('previewCategory');
        for (let option of categorySelect.options) {
            if (option.value.toLowerCase() === drill.category.toLowerCase()) {
                option.selected = true;
                break;
            }
        }
    }
    
    // Display rink image using safe DOM methods
    const imagePreview = document.getElementById('rinkImagePreview');
    if (drill.rink_image) {
        setImagePreview(imagePreview, drill.rink_image);
        document.getElementById('rinkImageUrl').value = drill.rink_image;
    } else {
        setPlaceholderIcon(imagePreview, 'hockey-puck', 'No image available');
        document.getElementById('rinkImageUrl').value = '';
    }
}

// Safe function to set image preview using DOM methods
function setImagePreview(container, src) {
    container.innerHTML = '';
    const img = document.createElement('img');
    img.src = src;
    img.alt = 'Rink diagram';
    img.onerror = function() {
        setPlaceholderIcon(container, 'exclamation-triangle', 'Image failed to load');
    };
    container.appendChild(img);
}

// Safe function to set placeholder icon using DOM methods
function setPlaceholderIcon(container, icon, text) {
    container.innerHTML = '';
    const i = document.createElement('i');
    i.className = 'fas fa-' + icon + ' placeholder-icon';
    const span = document.createElement('span');
    span.textContent = text;
    container.appendChild(i);
    container.appendChild(span);
}

function showManualEntry() {
    // Show the preview section with empty fields for manual entry
    document.getElementById('drillPreviewSection').style.display = 'block';
    document.getElementById('importIhsUrl').value = document.getElementById('ihsUrlInput').value || '';
    
    // Clear all fields
    document.getElementById('previewTitle').value = '';
    document.getElementById('previewDescription').value = '';
    document.getElementById('previewSetup').value = '';
    document.getElementById('previewCoachingPoints').value = '';
    document.getElementById('previewProgression').value = '';
    document.getElementById('previewCategory').value = '';
    document.getElementById('rinkImageUrl').value = '';
    
    const imagePreview = document.getElementById('rinkImagePreview');
    setPlaceholderIcon(imagePreview, 'hockey-puck', 'Enter image URL below');
    
    // Focus on the title field
    document.getElementById('previewTitle').focus();
    
    showNotification('Enter drill details manually, then click Import to Library', 'info');
}

function clearPreview() {
    document.getElementById('drillPreviewSection').style.display = 'none';
    document.getElementById('ihsUrlInput').value = '';
    currentDrillData = null;
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

// Show import progress overlay on form submit
document.getElementById('importDrillForm').addEventListener('submit', function(e) {
    var overlay = document.getElementById('importProgressOverlay');
    var btn = document.getElementById('importDrillSubmitBtn');
    var bar = document.getElementById('importProgressBar');
    var status = document.getElementById('importProgressStatus');
    overlay.style.display = 'flex';
    btn.disabled = true;

    // Animate progress bar to simulate progress during server-side import
    var progress = 0;
    var interval = setInterval(function() {
        if (progress < 70) {
            progress += Math.random() * 8 + 2;
        } else if (progress < 90) {
            progress += Math.random() * 2 + 0.5;
        }
        if (progress > 95) progress = 95;
        bar.style.width = progress + '%';
        if (progress < 30) {
            status.textContent = 'Downloading drill image...';
        } else if (progress < 60) {
            status.textContent = 'Saving image to cloud storage...';
        } else if (progress < 85) {
            status.textContent = 'Creating drill in library...';
        } else {
            status.textContent = 'Almost done...';
        }
    }, 500);
});

// Update image preview when URL changes
document.getElementById('rinkImageUrl').addEventListener('change', function() {
    const url = this.value.trim();
    const imagePreview = document.getElementById('rinkImagePreview');
    if (url) {
        setImagePreview(imagePreview, url);
    } else {
        setPlaceholderIcon(imagePreview, 'hockey-puck', 'No image available');
    }
});
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

/* Drill Preview Section */
.drill-preview-section {
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

.preview-content {
    display: grid;
    grid-template-columns: 300px 1fr;
    gap: 24px;
    margin-bottom: 24px;
}

.preview-image-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.rink-image-preview {
    width: 100%;
    aspect-ratio: 4/3;
    background: var(--bg-main, #06080b);
    border: 1px solid var(--border, #1e293b);
    border-radius: 8px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    color: var(--text-dim, #64748b);
    overflow: hidden;
}

.rink-image-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.rink-image-preview .placeholder-icon {
    font-size: 48px;
    opacity: 0.3;
}

.preview-details-section {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

/* Form Elements */
.form-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
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

.form-label .required {
    color: #ef4444;
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

/* Preview Sections */
.preview-sections {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 24px;
}

.preview-section-item {
    display: flex;
    flex-direction: column;
    gap: 8px;
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
    overflow: hidden;
}

.import-thumbnail img {
    width: 100%;
    height: 100%;
    object-fit: cover;
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
    margin-bottom: 4px;
}

.source-link {
    font-size: 11px;
    color: var(--primary, #7c3aed);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}

.source-link:hover {
    text-decoration: underline;
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

/* Import Progress Overlay */
.import-progress-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
}

.import-progress-card {
    background: var(--bg-card, #0d1117);
    border: 1px solid var(--border, #1e293b);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
    max-width: 420px;
    width: 90%;
}

.import-progress-card .spinner {
    width: 36px;
    height: 36px;
    margin: 0 auto 16px;
}

.import-progress-card h4 {
    color: var(--text-white, #fff);
    font-size: 18px;
    margin-bottom: 8px;
}

.import-progress-text {
    color: var(--text-dim, #64748b);
    font-size: 13px;
    margin-bottom: 20px;
}

.import-progress-bar-container {
    width: 100%;
    height: 8px;
    background: var(--bg-main, #06080b);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 12px;
}

.import-progress-bar {
    height: 100%;
    width: 0%;
    background: linear-gradient(90deg, var(--primary, #7c3aed), #a78bfa);
    border-radius: 4px;
    transition: width 0.4s ease;
}

.import-progress-status {
    color: var(--text-dim, #64748b);
    font-size: 12px;
}

/* Responsive */
@media (max-width: 768px) {
    .preview-content {
        grid-template-columns: 1fr;
    }
    
    .preview-sections {
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
}
</style>
