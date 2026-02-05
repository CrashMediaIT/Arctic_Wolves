<!-- Create/Edit Practice Plan View -->
<?php
// Check if editing an existing plan
$edit_plan_id = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$edit_plan = null;
$edit_drills = [];

// Fetch center ice logo URL from theme settings for drill preview thumbnails
$centerLogoUrl = '';
try {
    $logoStmt = $pdo->prepare("
        SELECT COALESCE(
            MAX(CASE WHEN setting_name = 'center_ice_logo_url' AND setting_value != '' THEN setting_value END),
            MAX(CASE WHEN setting_name = 'logo_url' AND setting_value != '' THEN setting_value END)
        ) as logo_url 
        FROM theme_settings 
        WHERE setting_name IN ('center_ice_logo_url', 'logo_url')
    ");
    $logoStmt->execute();
    $logoResult = $logoStmt->fetch(PDO::FETCH_ASSOC);
    if ($logoResult && !empty($logoResult['logo_url'])) {
        $centerLogoUrl = $logoResult['logo_url'];
    }
} catch (PDOException $e) {
    error_log("Error fetching center ice logo URL: " . $e->getMessage());
}

if ($edit_plan_id > 0) {
    try {
        // Get the practice plan
        $stmt = $pdo->prepare("SELECT * FROM practice_plans WHERE id = ?");
        $stmt->execute([$edit_plan_id]);
        $edit_plan = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($edit_plan) {
            // Get the drills for this plan
            $drill_stmt = $pdo->prepare("
                SELECT ppd.*, d.title, d.description, dc.name as category_name
                FROM practice_plan_drills ppd
                LEFT JOIN drills d ON ppd.drill_id = d.id
                LEFT JOIN drill_categories dc ON d.category_id = dc.id
                WHERE ppd.practice_plan_id = ?
                ORDER BY ppd.drill_order ASC
            ");
            $drill_stmt->execute([$edit_plan_id]);
            $edit_drills = $drill_stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Edit plan load error: " . $e->getMessage());
    }
}

$is_editing = $edit_plan !== null;
$page_title = $is_editing ? 'Edit Practice Plan' : 'Create Practice Plan';
$action_value = $is_editing ? 'update' : 'create';
?>
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-list"></i> <?= $page_title ?>
    </h1>
    <p class="page-description"><?= $is_editing ? 'Modify your practice plan' : 'Build a comprehensive practice plan for your team' ?></p>
</div>

<div class="create-practice-content">
    <!-- Practice Info Form -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-info-circle"></i> Practice Information</h3>
        </div>
        <div class="card-body">
            <form class="practice-form" id="practiceForm" method="POST" action="process_practice_plans.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="<?= $action_value ?>">
                <input type="hidden" name="drills" id="drillsData" value="[]">
                <?php if ($is_editing): ?>
                <input type="hidden" name="plan_id" value="<?= $edit_plan_id ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Practice Title *</label>
                        <input type="text" name="practice_title" id="practiceTitle" class="form-input" placeholder="e.g., Power Play Development" required value="<?= $is_editing ? htmlspecialchars($edit_plan['name'] ?? '') : '' ?>">
                    </div>
                    <div class="form-group">
                        <label>Team</label>
                        <select name="team_id" class="form-input">
                            <option value="">-- Select Team --</option>
                            <?php
                            try {
                                $teamsStmt = $pdo->query("SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name");
                                $teamsCount = 0;
                                while ($team = $teamsStmt->fetch()) {
                                    echo '<option value="' . $team['id'] . '">' . htmlspecialchars($team['name']) . '</option>';
                                    $teamsCount++;
                                }
                                // If no teams found, show demo options
                                if ($teamsCount === 0) {
                                    echo '<option value="" disabled>No teams available - contact administrator</option>';
                                }
                            } catch (PDOException $e) {
                                // Fallback if is_active column doesn't exist
                                try {
                                    $teamsStmt = $pdo->query("SELECT id, name FROM teams ORDER BY name");
                                    while ($team = $teamsStmt->fetch()) {
                                        echo '<option value="' . $team['id'] . '">' . htmlspecialchars($team['name']) . '</option>';
                                    }
                                } catch (PDOException $e2) {
                                    error_log("Teams fetch error: " . $e2->getMessage());
                                    echo '<option value="" disabled>Unable to load teams</option>';
                                }
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Date</label>
                        <input type="date" name="practice_date" class="form-input" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label>Time</label>
                        <input type="time" name="practice_time" class="form-input" value="18:00">
                    </div>
                    <div class="form-group">
                        <label>Duration (minutes) *</label>
                        <input type="number" name="duration" id="totalDuration" class="form-input" placeholder="90" min="1" value="60" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Location</label>
                    <input type="text" name="location" class="form-input" placeholder="e.g., Main Rink">
                </div>

                <div class="form-group">
                    <label>Practice Goals</label>
                    <textarea name="practice_goals" class="form-textarea" rows="3" placeholder="What are the key objectives for this practice?"><?= $is_editing ? htmlspecialchars($edit_plan['description'] ?? '') : '' ?></textarea>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-textarea" rows="3" placeholder="Any additional notes or reminders..."></textarea>
                </div>
            </form>
        </div>
    </div>

    <!-- Drill Builder -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-list-ol"></i> Practice Drills</h3>
            <button class="btn-primary" id="addDrillBtn" onclick="showDrillSelector()"><i class="fas fa-plus"></i> Add Drill</button>
        </div>
        <div class="card-body">
            <!-- Drills Timeline -->
            <div class="drills-timeline" id="drillsTimeline">
                <!-- Drills will be added here dynamically -->
            </div>
            
            <div class="timeline-summary" id="timelineSummary" style="display: none;">
                <div class="summary-item">
                    <span class="summary-label">Total Drills:</span>
                    <span class="summary-value" id="totalDrillsCount">0</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Total Time:</span>
                    <span class="summary-value" id="totalDrillsTime">0 min</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Remaining:</span>
                    <span class="summary-value" id="remainingTime">60 min</span>
                </div>
            </div>

            <div class="empty-state" id="emptyState">
                <i class="fas fa-clipboard-list placeholder-icon"></i>
                <p class="placeholder-text">No drills added yet. Click "Add Drill" to start building your practice plan.</p>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="form-actions-bar">
        <a href="?page=practice_library" class="btn btn-secondary"><i class="fas fa-times"></i> Cancel</a>
        <div class="action-group">
            <button type="button" class="btn btn-secondary" onclick="saveDraft()"><i class="fas fa-save"></i> Save Draft</button>
            <button type="button" class="btn btn-secondary" onclick="printPracticePlan()"><i class="fas fa-print"></i> Print</button>
            <button type="button" class="btn btn-primary" onclick="submitPracticePlan()"><i class="fas fa-check"></i> <?= $is_editing ? 'Update' : 'Create' ?> Practice Plan</button>
        </div>
    </div>
</div>

<!-- Drill Selector Modal -->
<div id="drillSelectorModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h2 class="modal-title">Select Drill</h2>
            <button class="close-modal" onclick="closeDrillSelector()">&times;</button>
        </div>
        <div class="modal-body">
            <input type="text" class="form-input" id="drillSelectorSearch" placeholder="Search drills..." onkeyup="filterDrillSelector()">
            <div class="drill-selector-grid" id="drillSelectorList" style="max-height: 500px; overflow-y: auto; margin-top: 15px;">
                <?php
                try {
                    $drillsStmt = $pdo->query("SELECT d.*, dc.name as category_name FROM drills d LEFT JOIN drill_categories dc ON d.category_id = dc.id ORDER BY d.title");
                    $drillsForSelector = $drillsStmt->fetchAll();
                } catch (PDOException $e) {
                    error_log("Practice create drills fetch error: " . $e->getMessage());
                    $drillsForSelector = [];
                }
                
                // No demo drills - show empty state when no real data exists
                $isDemoDrills = false;
                
                foreach ($drillsForSelector as $drill) {
                    $title = htmlspecialchars($drill['title'], ENT_QUOTES, 'UTF-8');
                    $category = htmlspecialchars($drill['category_name'] ?? '', ENT_QUOTES, 'UTF-8');
                    $description = htmlspecialchars(substr($drill['description'] ?? 'No description', 0, 100), ENT_QUOTES, 'UTF-8');
                    $drillId = is_numeric($drill['id']) ? intval($drill['id']) : htmlspecialchars($drill['id'], ENT_QUOTES, 'UTF-8');
                    $hasCustomImage = !empty($drill['custom_image']);
                    
                    // Extract ice view from diagram data
                    $drillIceView = 'full';
                    if (!empty($drill['diagram_data'])) {
                        $diagramParsed = json_decode($drill['diagram_data'], true);
                        if (is_array($diagramParsed) && isset($diagramParsed['iceView'])) {
                            $drillIceView = $diagramParsed['iceView'];
                        }
                    }
                    ?>
                    <div class="drill-card" data-drill-id="<?= $drillId ?>" data-title="<?= $title ?>" data-category="<?= $category ?>">
                        <div class="drill-image" data-ice-view="<?= htmlspecialchars($drillIceView) ?>">
                            <?php if ($hasCustomImage): ?>
                                <img src="<?= htmlspecialchars($drill['custom_image']) ?>" alt="<?= $title ?>">
                            <?php else: ?>
                                <div class="drill-diagram-preview" data-diagram='<?= htmlspecialchars($drill['diagram_data'] ?? '[]') ?>' data-center-logo="<?= htmlspecialchars($centerLogoUrl) ?>">
                                    <canvas class="drill-thumbnail-canvas"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="drill-content">
                            <div class="drill-header">
                                <h4 class="drill-title"><?= $title ?></h4>
                                <?php if (!empty($category)): ?>
                                    <div class="drill-category">
                                        <span class="category-badge"><?= $category ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <p class="drill-description"><?= $description ?><?= strlen($drill['description'] ?? '') > 100 ? '...' : '' ?></p>
                        </div>
                        <div class="drill-actions">
                            <button type="button" class="btn btn-primary btn-sm drill-add-btn"><i class="fas fa-plus"></i> Add</button>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <?php if (count($drillsForSelector) == 0): ?>
                    <p class="placeholder-text">No drills available. <a href="?page=drill_library">Create some drills first</a>.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.drills-timeline {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.timeline-drill-item {
    display: flex;
    gap: 15px;
    align-items: start;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.timeline-drill-item:hover {
    border-color: var(--neon);
}

.timeline-drill-item.dragging {
    opacity: 0.5;
    border-color: var(--primary);
}

.drill-handle {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    color: var(--text-dim);
    cursor: grab;
}

.drill-handle:active {
    cursor: grabbing;
}

.drill-timing {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 80px;
}

.time-input {
    width: 60px;
    height: 45px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 0 10px;
    border-radius: 4px;
    font-family: 'Inter', sans-serif;
    font-size: 16px;
    font-weight: 700;
    text-align: center;
}

.time-input:focus {
    outline: none;
    border-color: var(--neon);
}

.drill-timing span {
    font-size: 14px;
    color: var(--text-dim);
}

.drill-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.drill-title-display {
    font-weight: 700;
    color: var(--text-white);
    font-size: 15px;
}

.drill-category-badge {
    display: inline-block;
    padding: 2px 8px;
    background: rgba(107, 70, 193, 0.2);
    color: var(--primary);
    font-size: 11px;
    border-radius: 4px;
    margin-left: 8px;
}

.drill-actions-inline {
    display: flex;
    gap: 5px;
}

.drill-actions-inline .btn-icon {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
}

.drill-actions-inline .btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.drill-actions-inline .btn-icon.danger:hover {
    background: #dc2626;
    border-color: #dc2626;
}

.timeline-summary {
    display: flex;
    justify-content: space-around;
    padding: 24px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(139, 92, 246, 0.1));
    border: 1px solid var(--primary);
    border-radius: 8px;
    margin-top: 15px;
}

.summary-item {
    text-align: center;
}

.summary-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 5px;
}

.summary-value {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--primary);
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
}

.empty-state .placeholder-icon {
    font-size: 48px;
    color: var(--text-dim);
    opacity: 0.3;
    margin-bottom: 15px;
}

.empty-state .placeholder-text {
    color: var(--text-dim);
    font-size: 14px;
}

/* Drill Selector Modal - Using same card styles as Drill Library */
.drill-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 24px;
}

@media (max-width: 768px) {
    .drill-selector-grid {
        grid-template-columns: 1fr;
    }
}

.drill-selector-grid .drill-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: all 0.3s ease;
    max-width: 380px;
    cursor: pointer;
}

.drill-selector-grid .drill-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 70, 193, 0.2);
}

.drill-selector-grid .drill-image {
    position: relative;
    width: 100%;
    /* Default for full ice (200/85 ratio) - width/height ≈ 2.35, so height as % of width ≈ 42.5% */
    padding-top: 42.5%;
    background: var(--bg-main);
    overflow: hidden;
    transition: padding-top 0.3s ease-in-out;
}

/* Dynamic aspect ratios based on ice view for thumbnails */
/* Full ice: 200 ft × 85 ft (horizontal, net on left/right) - height/width = 85/200 = 42.5% */
.drill-selector-grid .drill-image[data-ice-view="full"] {
    padding-top: 42.5%;
}

/* Half ice: Reduced height for better display in modal (was 117.6%, now 75%) */
.drill-selector-grid .drill-image[data-ice-view="half-top"],
.drill-selector-grid .drill-image[data-ice-view="half-bottom"] {
    padding-top: 75%;
}

/* Zone views: 100 ft × 85 ft (horizontal, like half of full ice) - height/width = 85/100 = 85% */
.drill-selector-grid .drill-image[data-ice-view="left-zone"],
.drill-selector-grid .drill-image[data-ice-view="right-zone"] {
    padding-top: 85%;
}

/* Center ice: Reduced height for better display (was 118.1%, now 75%) */
.drill-selector-grid .drill-image[data-ice-view="center"] {
    padding-top: 75%;
}

.drill-selector-grid .drill-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.drill-selector-grid .drill-diagram-preview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
    overflow: hidden;
}

.drill-selector-grid .drill-diagram-preview canvas.drill-thumbnail-canvas {
    width: 100%;
    height: 100%;
    display: block;
}

.drill-selector-grid .drill-content {
    padding: 20px;
}

.drill-selector-grid .drill-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 12px;
    gap: 12px;
}

.drill-selector-grid .drill-title {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    flex: 1;
    margin: 0;
}

.drill-selector-grid .drill-category {
    display: flex;
    gap: 6px;
    flex-shrink: 0;
}

.drill-selector-grid .category-badge {
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary);
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.drill-selector-grid .drill-description {
    font-size: 14px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin-bottom: 0;
}

.drill-selector-grid .drill-actions {
    padding: 16px 20px;
    background: var(--bg-main);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 8px;
    align-items: center;
}

.drill-add-btn {
    width: 100%;
}

/* Legacy drill-selector-item support */
.drill-selector-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.drill-selector-item:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.drill-selector-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.drill-selector-info strong {
    color: var(--text-white);
    font-size: 14px;
}

.drill-category {
    font-size: 11px;
    color: var(--text-dim);
}

.modal {
    display: none;
    position: fixed;
    top: 0; left: 0; right: 0; bottom: 0;
    background: rgba(0, 0, 0, 0.8);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
}

.close-modal {
    background: none;
    border: none;
    color: var(--text-dim);
    font-size: 28px;
    cursor: pointer;
    padding: 0;
    line-height: 1;
}

.close-modal:hover {
    color: var(--text-white);
}

/* Form Actions Bar */
.form-actions-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-top: 24px;
}

.action-group {
    display: flex;
    gap: 12px;
}

/* Content Card */
.content-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 24px;
}

.content-card .card-header {
    background: var(--bg-main);
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.content-card .card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.content-card .card-header h3 i {
    color: var(--primary);
}

.content-card .card-body {
    padding: 20px;
}
</style>

<!-- Shared Ice Canvas Renderer - ensures consistent rink drawing across all views -->
<script src="js/ice_canvas.js"></script>
<script>
// Notification helper function
function showNotification(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-error' : type === 'success' ? 'alert-success' : 'alert-info';
    const alertDiv = document.createElement('div');
    alertDiv.className = 'notification-toast ' + alertClass;
    alertDiv.innerHTML = '<i class="fas fa-' + (type === 'error' ? 'exclamation-circle' : type === 'success' ? 'check-circle' : 'info-circle') + '"></i> ' + message;
    alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 10000; min-width: 300px; padding: 15px 20px; border-radius: 8px; font-size: 14px; font-weight: 600; animation: slideIn 0.3s ease;';
    
    if (type === 'error') {
        alertDiv.style.background = 'rgba(239, 68, 68, 0.9)';
        alertDiv.style.color = '#fff';
    } else if (type === 'success') {
        alertDiv.style.background = 'rgba(16, 185, 129, 0.9)';
        alertDiv.style.color = '#fff';
    } else {
        alertDiv.style.background = 'rgba(59, 130, 246, 0.9)';
        alertDiv.style.color = '#fff';
    }
    
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 4000);
}

// Practice plan drill management
let practiceDrills = [];
let draggedItem = null;

function showDrillSelector() {
    // Hide drills that are already in the practice plan
    const addedDrillIds = practiceDrills.map(d => d.id);
    const items = document.querySelectorAll('.drill-selector-grid .drill-card, .drill-selector-item');
    items.forEach(item => {
        const drillId = parseInt(item.dataset.drillId, 10);
        if (addedDrillIds.includes(drillId)) {
            item.style.display = 'none';
            item.setAttribute('data-already-added', 'true');
        } else {
            item.style.display = '';
            item.removeAttribute('data-already-added');
        }
    });
    
    document.getElementById('drillSelectorModal').classList.add('active');
}

function closeDrillSelector() {
    document.getElementById('drillSelectorModal').classList.remove('active');
}

function filterDrillSelector() {
    const search = document.getElementById('drillSelectorSearch').value.toLowerCase();
    const addedDrillIds = practiceDrills.map(d => d.id);
    // Handle both old (.drill-selector-item) and new (.drill-card) selectors in drill selector grid
    const items = document.querySelectorAll('.drill-selector-grid .drill-card, .drill-selector-item');
    items.forEach(item => {
        const drillId = parseInt(item.dataset.drillId, 10);
        // Don't show drills that are already added
        if (addedDrillIds.includes(drillId)) {
            item.style.display = 'none';
            return;
        }
        const title = (item.dataset.title || '').toLowerCase();
        const category = (item.dataset.category || '').toLowerCase();
        const matches = title.includes(search) || category.includes(search);
        item.style.display = matches ? '' : 'none';
    });
}

// Use event delegation for drill selection
document.addEventListener('click', function(e) {
    // Handle new card-based selector (now using .drill-card within .drill-selector-grid)
    const drillCard = e.target.closest('.drill-selector-grid .drill-card');
    if (drillCard) {
        const id = parseInt(drillCard.dataset.drillId, 10);
        const title = drillCard.dataset.title || '';
        const category = drillCard.dataset.category || '';
        selectDrill(id, title, category);
        return;
    }
    
    // Handle legacy list-based selector
    const drillItem = e.target.closest('.drill-selector-item');
    if (drillItem) {
        const id = parseInt(drillItem.dataset.drillId, 10);
        const title = drillItem.dataset.title || '';
        const category = drillItem.dataset.category || '';
        selectDrill(id, title, category);
    }
});

function selectDrill(id, title, category) {
    // Check if already added
    if (practiceDrills.find(d => d.id === id)) {
        showNotification('This drill is already in your plan.', 'error');
        return;
    }
    
    practiceDrills.push({
        id: id,
        title: title,
        category: category,
        duration: 10,
        notes: ''
    });
    
    updateDrillsDisplay();
    closeDrillSelector();
}

function removeDrill(index) {
    practiceDrills.splice(index, 1);
    updateDrillsDisplay();
}

function moveDrillUp(index) {
    if (index > 0) {
        const temp = practiceDrills[index];
        practiceDrills[index] = practiceDrills[index - 1];
        practiceDrills[index - 1] = temp;
        updateDrillsDisplay();
    }
}

function moveDrillDown(index) {
    if (index < practiceDrills.length - 1) {
        const temp = practiceDrills[index];
        practiceDrills[index] = practiceDrills[index + 1];
        practiceDrills[index + 1] = temp;
        updateDrillsDisplay();
    }
}

function updateDrillDuration(index, duration) {
    practiceDrills[index].duration = parseInt(duration) || 0;
    updateSummary();
}

function updateDrillNotes(index, notes) {
    practiceDrills[index].notes = notes;
}

function updateDrillsDisplay() {
    const timeline = document.getElementById('drillsTimeline');
    const emptyState = document.getElementById('emptyState');
    const summary = document.getElementById('timelineSummary');
    
    if (practiceDrills.length === 0) {
        timeline.innerHTML = '';
        emptyState.style.display = 'block';
        summary.style.display = 'none';
        return;
    }
    
    emptyState.style.display = 'none';
    summary.style.display = 'flex';
    
    timeline.innerHTML = practiceDrills.map((drill, index) => `
        <div class="timeline-drill-item" draggable="true" data-index="${index}">
            <div class="drill-handle">
                <i class="fas fa-grip-vertical"></i>
            </div>
            <div class="drill-timing">
                <input type="number" class="time-input" value="${drill.duration}" min="1" onchange="updateDrillDuration(${index}, this.value)">
                <span>min</span>
            </div>
            <div class="drill-details">
                <div>
                    <span class="drill-title-display">${drill.title}</span>
                    ${drill.category ? `<span class="drill-category-badge">${drill.category}</span>` : ''}
                </div>
                <textarea class="form-textarea" rows="2" placeholder="Add notes or modifications..." onchange="updateDrillNotes(${index}, this.value)">${drill.notes}</textarea>
            </div>
            <div class="drill-actions-inline">
                <button type="button" class="btn-icon" title="Move Up" onclick="moveDrillUp(${index})" ${index === 0 ? 'disabled' : ''}><i class="fas fa-arrow-up"></i></button>
                <button type="button" class="btn-icon" title="Move Down" onclick="moveDrillDown(${index})" ${index === practiceDrills.length - 1 ? 'disabled' : ''}><i class="fas fa-arrow-down"></i></button>
                <button type="button" class="btn-icon danger" title="Remove" onclick="removeDrill(${index})"><i class="fas fa-trash"></i></button>
            </div>
        </div>
    `).join('');
    
    // Setup drag and drop
    setupDragAndDrop();
    updateSummary();
}

function setupDragAndDrop() {
    const items = document.querySelectorAll('.timeline-drill-item[draggable="true"]');
    
    items.forEach(item => {
        item.addEventListener('dragstart', handleDragStart);
        item.addEventListener('dragend', handleDragEnd);
        item.addEventListener('dragover', handleDragOver);
        item.addEventListener('drop', handleDrop);
    });
}

function handleDragStart(e) {
    draggedItem = this;
    this.classList.add('dragging');
    e.dataTransfer.effectAllowed = 'move';
}

function handleDragEnd(e) {
    this.classList.remove('dragging');
    draggedItem = null;
}

function handleDragOver(e) {
    e.preventDefault();
    e.dataTransfer.dropEffect = 'move';
}

function handleDrop(e) {
    e.preventDefault();
    if (this !== draggedItem) {
        const fromIndex = parseInt(draggedItem.dataset.index);
        const toIndex = parseInt(this.dataset.index);
        
        const moved = practiceDrills.splice(fromIndex, 1)[0];
        practiceDrills.splice(toIndex, 0, moved);
        
        updateDrillsDisplay();
    }
}

function updateSummary() {
    const totalDrills = practiceDrills.length;
    const totalTime = practiceDrills.reduce((sum, d) => sum + d.duration, 0);
    const totalDuration = parseInt(document.getElementById('totalDuration').value) || 60;
    const remaining = totalDuration - totalTime;
    
    document.getElementById('totalDrillsCount').textContent = totalDrills;
    document.getElementById('totalDrillsTime').textContent = totalTime + ' min';
    document.getElementById('remainingTime').textContent = remaining + ' min';
    document.getElementById('remainingTime').style.color = remaining < 0 ? '#ef4444' : 'var(--primary)';
}

function saveDraft() {
    const title = document.getElementById('practiceTitle').value || 'Untitled Practice';
    const draftData = {
        title: title,
        drills: practiceDrills,
        form: new FormData(document.getElementById('practiceForm'))
    };
    
    localStorage.setItem('practice_plan_draft', JSON.stringify({
        title: title,
        drills: practiceDrills,
        timestamp: new Date().toISOString()
    }));
    
    showNotification('Draft saved! Your progress has been saved locally.', 'success');
}

function printPracticePlan() {
    window.print();
}

function submitPracticePlan() {
    // Update drills data
    document.getElementById('drillsData').value = JSON.stringify(practiceDrills);
    
    // Validate
    const title = document.getElementById('practiceTitle').value;
    if (!title) {
        showNotification('Please enter a practice title.', 'error');
        return;
    }
    
    // Submit form
    document.getElementById('practiceForm').submit();
}

// Load draft on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load existing drills if editing
    <?php if ($is_editing && !empty($edit_drills)): ?>
    var existingDrills = <?= json_encode(array_values(array_filter(array_map(function($d) {
        // Only include drills that have valid data
        if (empty($d['drill_id']) || empty($d['title'])) {
            return null;
        }
        return [
            'id' => intval($d['drill_id']),
            'title' => $d['title'],
            'category' => $d['category_name'] ?? '',
            'duration' => intval($d['duration_minutes'] ?? 10),
            'notes' => $d['notes'] ?? ''
        ];
    }, $edit_drills)))) ?>;
    console.log('Loading existing drills for editing:', existingDrills);
    if (existingDrills && existingDrills.length > 0) {
        practiceDrills = existingDrills;
        updateDrillsDisplay();
    }
    // Clear draft when editing existing plan
    localStorage.removeItem('practice_plan_draft');
    <?php else: ?>
    const draft = localStorage.getItem('practice_plan_draft');
    if (draft) {
        const loadDraft = confirm('You have a saved draft. Would you like to load it?');
        if (loadDraft) {
            const draftData = JSON.parse(draft);
            if (draftData.drills) {
                practiceDrills = draftData.drills;
                updateDrillsDisplay();
            }
            if (draftData.title) {
                document.getElementById('practiceTitle').value = draftData.title;
            }
        }
    }
    <?php endif; ?>
    
    // Update summary when duration changes
    document.getElementById('totalDuration').addEventListener('change', updateSummary);
    
    // Close modal when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                modal.classList.remove('active');
            }
        });
    });
    
    // Render drill thumbnails in the selector modal
    renderDrillSelectorThumbnails();
});

// NHL/Hockey Canada Rink Proportions (200 ft × 85 ft rink)
const NHL_RINK = {
    GOAL_LINE: 11 / 200,
    BLUE_LINE: 64 / 200,
    FACEOFF_RADIUS: 15 / 85,
    CENTER_CIRCLE_RADIUS: 15 / 85,
    CREASE_RADIUS: 6 / 85,
    FACEOFF_FROM_GOAL: 20 / 200,
    FACEOFF_FROM_BOARDS: 22 / 85,
    TRAPEZOID_BASE: 22 / 85,
    TRAPEZOID_TOP: 28 / 85,
    RESTRAINT_LINE_LENGTH: 2 / 85,
    CORNER_RADIUS: 28 / 85
};

// Thumbnail object sizes for consistent rendering
const THUMBNAIL_SIZES = {
    PLAYER_RADIUS: 8,
    CONE_HEIGHT: 8,
    CONE_WIDTH: 5,
    PUCK_RADIUS: 4,
    PUCK_SMALL_RADIUS: 3,
    ARROW_HEAD_LENGTH: 6,
    SHOT_ARROW_HEAD_LENGTH: 8,
    LINE_WIDTH: 2,
    SHOT_LINE_WIDTH: 3,
    TIRE_LINE_WIDTH: 3,
    TIRE_RADIUS: 6,
    FONT_SIZE: 6
};

// Render drill thumbnails in selector modal (matching drills_library.php)
function renderDrillSelectorThumbnails() {
    const previews = document.querySelectorAll('.drill-selector-grid .drill-card .drill-diagram-preview');
    
    previews.forEach(preview => {
        const canvas = preview.querySelector('.drill-thumbnail-canvas');
        if (!canvas) return;
        
        // Get diagram data
        let diagramData = [];
        let sourceWidth = 800;
        let sourceHeight = 400;
        let iceView = 'full';
        try {
            const dataStr = preview.getAttribute('data-diagram') || '[]';
            const parsed = JSON.parse(dataStr);
            
            if (Array.isArray(parsed)) {
                diagramData = parsed;
            } else if (parsed && parsed.objects && Array.isArray(parsed.objects)) {
                diagramData = parsed.objects;
                sourceWidth = parsed.canvasWidth || 800;
                sourceHeight = parsed.canvasHeight || 400;
                if (parsed.iceView) {
                    iceView = parsed.iceView;
                }
            }
        } catch (e) {
            diagramData = [];
        }
        
        const centerLogoUrl = preview.getAttribute('data-center-logo') || '';
        
        // Set canvas size to match container
        canvas.width = preview.offsetWidth || 280;
        canvas.height = preview.offsetHeight || 140;
        
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        
        function renderThumbnail(logoImage, logoLoaded) {
            // Use the shared IceCanvasRenderer for consistent rink drawing
            if (window.IceCanvasRenderer) {
                IceCanvasRenderer.drawRink(ctx, w, h, iceView, {
                    logoImage: logoImage,
                    logoLoaded: logoLoaded
                });
            } else {
                // Fallback if shared module not loaded - draw basic ice
                ctx.fillStyle = '#f0f7fa';
                ctx.fillRect(0, 0, w, h);
                ctx.strokeStyle = '#0033a0';
                ctx.lineWidth = 2;
                ctx.strokeRect(2, 2, w - 4, h - 4);
            }
            
            // Draw diagram objects
            if (diagramData && diagramData.length > 0) {
                const scaleX = w / sourceWidth;
                const scaleY = h / sourceHeight;
                const uniformScale = Math.min(scaleX, scaleY);
                const offsetX = (w - sourceWidth * uniformScale) / 2;
                const offsetY = (h - sourceHeight * uniformScale) / 2;
                
                diagramData.forEach(obj => {
                    drawThumbnailObject(ctx, obj, uniformScale, offsetX, offsetY);
                });
            }
        }
        
        if (centerLogoUrl) {
            const logoImage = new Image();
            logoImage.crossOrigin = 'anonymous';
            logoImage.onload = function() {
                renderThumbnail(logoImage, true);
            };
            logoImage.onerror = function() {
                renderThumbnail(null, false);
            };
            logoImage.src = centerLogoUrl;
        } else {
            renderThumbnail(null, false);
        }
    });
}

// Ice drawing helper functions (matching drills_library.php and drill_designer.js)
function drawThumbnailHashMarks(ctx, cx, cy, radius, netPosition) {
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.lineCap = 'round';
    
    const hashLength = radius * (2 / 15);
    const hashSpacing = radius * (3 / 15);
    const gapOutsideCircle = radius * 0.05;
    const startDistance = radius + gapOutsideCircle;
    
    const sides = [-1, 1];
    
    if (netPosition === 'vertical') {
        sides.forEach(function(side) {
            const startX = cx + side * startDistance;
            const endX = startX + side * hashLength;
            ctx.beginPath();
            ctx.moveTo(startX, cy - hashSpacing / 2);
            ctx.lineTo(endX, cy - hashSpacing / 2);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(startX, cy + hashSpacing / 2);
            ctx.lineTo(endX, cy + hashSpacing / 2);
            ctx.stroke();
        });
    } else {
        sides.forEach(function(side) {
            const startY = cy + side * startDistance;
            const endY = startY + side * hashLength;
            ctx.beginPath();
            ctx.moveTo(cx - hashSpacing / 2, startY);
            ctx.lineTo(cx - hashSpacing / 2, endY);
            ctx.stroke();
            ctx.beginPath();
            ctx.moveTo(cx + hashSpacing / 2, startY);
            ctx.lineTo(cx + hashSpacing / 2, endY);
            ctx.stroke();
        });
    }
}

function drawThumbnailRestraintLines(ctx, cx, cy, radius, zone, canvasRefDimension, isVertical) {
    const lineLength = canvasRefDimension * NHL_RINK.RESTRAINT_LINE_LENGTH * 1.5;
    const offset = radius * 0.15;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.lineCap = 'round';
    
    if (isVertical) {
        const goalDirection = zone === 'top' ? -1 : 1;
        drawThumbnailLShapeVertical(ctx, cx - offset, cy - offset, lineLength, goalDirection);
        drawThumbnailLShapeVertical(ctx, cx - offset, cy + offset, lineLength, goalDirection);
        drawThumbnailLShapeVertical(ctx, cx + offset, cy - offset, lineLength, goalDirection);
        drawThumbnailLShapeVertical(ctx, cx + offset, cy + offset, lineLength, goalDirection);
    } else {
        const goalDirection = zone === 'left' ? -1 : 1;
        drawThumbnailLShape(ctx, cx - offset, cy - offset, lineLength, goalDirection, -1);
        drawThumbnailLShape(ctx, cx + offset, cy - offset, lineLength, goalDirection, -1);
        drawThumbnailLShape(ctx, cx - offset, cy + offset, lineLength, goalDirection, 1);
        drawThumbnailLShape(ctx, cx + offset, cy + offset, lineLength, goalDirection, 1);
    }
}

function drawThumbnailLShape(ctx, x, y, length, hDir, vDir) {
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x, y + vDir * length);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x + hDir * length, y);
    ctx.stroke();
}

function drawThumbnailLShapeVertical(ctx, x, y, length, vDir) {
    ctx.beginPath();
    ctx.moveTo(x, y);
    ctx.lineTo(x, y + vDir * length);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(x - length/2, y);
    ctx.lineTo(x + length/2, y);
    ctx.stroke();
}

function drawThumbnailFullIce(ctx, w, h) {
    const goalLinePos = NHL_RINK.GOAL_LINE;
    const blueLinePos = NHL_RINK.BLUE_LINE;
    const faceoffFromGoal = goalLinePos + NHL_RINK.FACEOFF_FROM_GOAL;
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w * blueLinePos, 0);
    ctx.lineTo(w * blueLinePos, h);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(w * (1 - blueLinePos), 0);
    ctx.lineTo(w * (1 - blueLinePos), h);
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(w/2, h/2, h * NHL_RINK.CENTER_CIRCLE_RADIUS, 0, 2 * Math.PI);
    ctx.stroke();
    
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 3, 0, 2 * Math.PI);
    ctx.fill();
    
    ctx.strokeStyle = '#c41e3a';
    const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
    const circles = [
        { x: w * faceoffFromGoal, y: h * faceoffFromBoards, zone: 'left' },
        { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards), zone: 'left' },
        { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards, zone: 'right' },
        { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards), zone: 'right' }
    ];
    circles.forEach(function(circle) {
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, 2, 0, 2 * Math.PI);
        ctx.fill();
        drawThumbnailHashMarks(ctx, circle.x, circle.y, faceoffRadius, 'horizontal');
        drawThumbnailRestraintLines(ctx, circle.x, circle.y, faceoffRadius, circle.zone, h, false);
    });
    
    const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    
    ctx.beginPath();
    ctx.arc(w * goalLinePos, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    ctx.fill();
    ctx.stroke();
    
    ctx.beginPath();
    ctx.arc(w * (1 - goalLinePos), h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2, true);
    ctx.fill();
    ctx.stroke();
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    
    const leftGoalLineX = w * goalLinePos;
    let leftGoalLineStartY = 0;
    let leftGoalLineEndY = h;
    if (leftGoalLineX < cornerRadius) {
        const dx = cornerRadius - leftGoalLineX;
        const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
        leftGoalLineStartY = yOffset;
        leftGoalLineEndY = h - yOffset;
    }
    ctx.beginPath();
    ctx.moveTo(leftGoalLineX, leftGoalLineStartY);
    ctx.lineTo(leftGoalLineX, leftGoalLineEndY);
    ctx.stroke();
    
    const rightGoalLineX = w * (1 - goalLinePos);
    let rightGoalLineStartY = 0;
    let rightGoalLineEndY = h;
    if ((w - rightGoalLineX) < cornerRadius) {
        const dx = cornerRadius - (w - rightGoalLineX);
        const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
        rightGoalLineStartY = yOffset;
        rightGoalLineEndY = h - yOffset;
    }
    ctx.beginPath();
    ctx.moveTo(rightGoalLineX, rightGoalLineStartY);
    ctx.lineTo(rightGoalLineX, rightGoalLineEndY);
    ctx.stroke();
    
    drawThumbnailTrapezoid(ctx, w, h, 'left');
    drawThumbnailTrapezoid(ctx, w, h, 'right');
    
    const neutralZoneDotOffset = 5 / 200;
    ctx.fillStyle = '#c41e3a';
    const neutralDots = [
        { x: w * (blueLinePos + neutralZoneDotOffset), y: h * faceoffFromBoards },
        { x: w * (blueLinePos + neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) },
        { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * faceoffFromBoards },
        { x: w * (1 - blueLinePos - neutralZoneDotOffset), y: h * (1 - faceoffFromBoards) }
    ];
    neutralDots.forEach(function(dot) {
        ctx.beginPath();
        ctx.arc(dot.x, dot.y, 2, 0, 2 * Math.PI);
        ctx.fill();
    });
}

function drawThumbnailTrapezoid(ctx, w, h, side) {
    const goalLinePos = NHL_RINK.GOAL_LINE;
    const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
    const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    
    if (side === 'left') {
        const goalX = w * goalLinePos;
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 - trapezoidBase);
        ctx.lineTo(0, h/2 - trapezoidTop);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 + trapezoidBase);
        ctx.lineTo(0, h/2 + trapezoidTop);
        ctx.stroke();
    } else {
        const goalX = w * (1 - goalLinePos);
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 - trapezoidBase);
        ctx.lineTo(w, h/2 - trapezoidTop);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(goalX, h/2 + trapezoidBase);
        ctx.lineTo(w, h/2 + trapezoidTop);
        ctx.stroke();
    }
}

function drawThumbnailHalfIce(ctx, w, h, side) {
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const faceoffRadius = w * NHL_RINK.FACEOFF_RADIUS;
    const creaseRadius = w * NHL_RINK.CREASE_RADIUS;
    const cornerRadius = w * NHL_RINK.CORNER_RADIUS;
    
    const goalLineRatio = 11 / 100;
    const blueLineRatio = 64 / 100;
    const faceoffYRatio = 31 / 100;
    
    const blueLineY = side === 'top' ? h * blueLineRatio : h * (1 - blueLineRatio);
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(0, blueLineY);
    ctx.lineTo(w, blueLineY);
    ctx.stroke();
    
    const goalY = side === 'top' ? h * goalLineRatio : h * (1 - goalLineRatio);
    const faceoffY = side === 'top' ? h * faceoffYRatio : h * (1 - faceoffYRatio);
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.arc(w * faceoffFromBoards, faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(w * faceoffFromBoards, faceoffY, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, 'vertical');
    drawThumbnailRestraintLines(ctx, w * faceoffFromBoards, faceoffY, faceoffRadius, side, w, true);
    
    ctx.strokeStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(w * (1 - faceoffFromBoards), faceoffY, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, 'vertical');
    drawThumbnailRestraintLines(ctx, w * (1 - faceoffFromBoards), faceoffY, faceoffRadius, side, w, true);
    
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (side === 'top') {
        ctx.arc(w * 0.5, goalY, creaseRadius, 0, Math.PI);
    } else {
        ctx.arc(w * 0.5, goalY, creaseRadius, Math.PI, 0);
    }
    ctx.fill();
    ctx.stroke();
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    const distFromEnd = side === 'top' ? goalY : (h - goalY);
    let goalLineStartX = 0;
    let goalLineEndX = w;
    if (distFromEnd < cornerRadius) {
        const dy = cornerRadius - distFromEnd;
        const xOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dy * dy);
        goalLineStartX = xOffset;
        goalLineEndX = w - xOffset;
    }
    ctx.moveTo(goalLineStartX, goalY);
    ctx.lineTo(goalLineEndX, goalY);
    ctx.stroke();
    
    drawThumbnailHalfIceTrapezoid(ctx, w, h, side, goalY);
}

function drawThumbnailHalfIceTrapezoid(ctx, w, h, side, goalY) {
    const trapezoidBase = w * NHL_RINK.TRAPEZOID_BASE / 2;
    const trapezoidTop = w * NHL_RINK.TRAPEZOID_TOP / 2;
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    const boardY = side === 'top' ? 0 : h;
    ctx.beginPath();
    ctx.moveTo(w/2 - trapezoidBase, goalY);
    ctx.lineTo(w/2 - trapezoidTop, boardY);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(w/2 + trapezoidBase, goalY);
    ctx.lineTo(w/2 + trapezoidTop, boardY);
    ctx.stroke();
}

function drawThumbnailZone(ctx, w, h, side) {
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
    const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
    const centerCircleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
    const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
    
    const goalLineRatio = 11 / 100;
    const blueLineRatio = 64 / 100;
    const faceoffXRatio = 31 / 100;
    const neutralZoneDotRatio = (64 + 5) / 100;
    
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    if (side === 'left') {
        ctx.beginPath();
        ctx.moveTo(w, 0);
        ctx.lineTo(w, h);
        ctx.stroke();
    } else {
        ctx.beginPath();
        ctx.moveTo(0, 0);
        ctx.lineTo(0, h);
        ctx.stroke();
    }
    
    const blueLineX = side === 'left' ? w * blueLineRatio : w * (1 - blueLineRatio);
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(blueLineX, 0);
    ctx.lineTo(blueLineX, h);
    ctx.stroke();
    
    const goalLineX = side === 'left' ? w * goalLineRatio : w * (1 - goalLineRatio);
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    const distFromEnd = side === 'left' ? goalLineX : (w - goalLineX);
    let zoneGoalLineStartY = 0;
    let zoneGoalLineEndY = h;
    if (distFromEnd < cornerRadius) {
        const dx = cornerRadius - distFromEnd;
        const yOffset = cornerRadius - Math.sqrt(cornerRadius * cornerRadius - dx * dx);
        zoneGoalLineStartY = yOffset;
        zoneGoalLineEndY = h - yOffset;
    }
    ctx.moveTo(goalLineX, zoneGoalLineStartY);
    ctx.lineTo(goalLineX, zoneGoalLineEndY);
    ctx.stroke();
    
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(w, h/2, centerCircleRadius, Math.PI/2, -Math.PI/2);
    } else {
        ctx.arc(0, h/2, centerCircleRadius, -Math.PI/2, Math.PI/2);
    }
    ctx.stroke();
    
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(w, h/2, 3, 0, 2 * Math.PI);
    } else {
        ctx.arc(0, h/2, 3, 0, 2 * Math.PI);
    }
    ctx.fill();
    
    const faceoffX = side === 'left' ? w * faceoffXRatio : w * (1 - faceoffXRatio);
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.arc(faceoffX, h * faceoffFromBoards, faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(faceoffX, h * faceoffFromBoards, 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, 'horizontal');
    drawThumbnailRestraintLines(ctx, faceoffX, h * faceoffFromBoards, faceoffRadius, side, h, false);
    
    ctx.strokeStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 0, 2 * Math.PI);
    ctx.stroke();
    ctx.beginPath();
    ctx.arc(faceoffX, h * (1 - faceoffFromBoards), 2, 0, 2 * Math.PI);
    ctx.fill();
    drawThumbnailHashMarks(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, 'horizontal');
    drawThumbnailRestraintLines(ctx, faceoffX, h * (1 - faceoffFromBoards), faceoffRadius, side, h, false);
    
    const neutralDotX = side === 'left' ? w * neutralZoneDotRatio : w * (1 - neutralZoneDotRatio);
    ctx.fillStyle = '#c41e3a';
    ctx.beginPath();
    ctx.arc(neutralDotX, h * faceoffFromBoards, 2, 0, 2 * Math.PI);
    ctx.fill();
    ctx.beginPath();
    ctx.arc(neutralDotX, h * (1 - faceoffFromBoards), 2, 0, 2 * Math.PI);
    ctx.fill();
    
    ctx.fillStyle = 'rgba(135, 206, 235, 0.4)';
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    ctx.beginPath();
    if (side === 'left') {
        ctx.arc(goalLineX, h * 0.5, creaseRadius, -Math.PI/2, Math.PI/2);
    } else {
        ctx.arc(goalLineX, h * 0.5, creaseRadius, Math.PI/2, -Math.PI/2);
    }
    ctx.fill();
    ctx.stroke();
    
    drawThumbnailZoneTrapezoid(ctx, w, h, side, goalLineX);
}

function drawThumbnailZoneTrapezoid(ctx, w, h, side, goalLineX) {
    const trapezoidBase = h * NHL_RINK.TRAPEZOID_BASE / 2;
    const trapezoidTop = h * NHL_RINK.TRAPEZOID_TOP / 2;
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 1;
    const boardX = side === 'left' ? 0 : w;
    ctx.beginPath();
    ctx.moveTo(goalLineX, h/2 - trapezoidBase);
    ctx.lineTo(boardX, h/2 - trapezoidTop);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(goalLineX, h/2 + trapezoidBase);
    ctx.lineTo(boardX, h/2 + trapezoidTop);
    ctx.stroke();
}

function drawThumbnailCenterIce(ctx, w, h) {
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 1;
    const circleRadius = h * NHL_RINK.CENTER_CIRCLE_RADIUS;
    ctx.beginPath();
    ctx.arc(w/2, h/2, circleRadius, 0, 2 * Math.PI);
    ctx.stroke();
    
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 4, 0, 2 * Math.PI);
    ctx.fill();
}

function drawThumbnailObject(ctx, obj, uniformScale, offsetX, offsetY) {
    const x = (obj.x || 0) * uniformScale + offsetX;
    const y = (obj.y || 0) * uniformScale + offsetY;
    
    if (obj.type === 'player') {
        ctx.fillStyle = obj.color || '#00bfff';
        ctx.beginPath();
        ctx.arc(x, y, THUMBNAIL_SIZES.PLAYER_RADIUS, 0, 2 * Math.PI);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 1;
        ctx.stroke();
        
        if (obj.label) {
            ctx.fillStyle = '#fff';
            ctx.font = 'bold ' + THUMBNAIL_SIZES.FONT_SIZE + 'px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(obj.label, x, y);
        }
    } else if (obj.type === 'cone') {
        ctx.fillStyle = obj.color || '#ff6b00';
        ctx.beginPath();
        ctx.moveTo(x, y - THUMBNAIL_SIZES.CONE_HEIGHT);
        ctx.lineTo(x - THUMBNAIL_SIZES.CONE_WIDTH, y + THUMBNAIL_SIZES.CONE_WIDTH);
        ctx.lineTo(x + THUMBNAIL_SIZES.CONE_WIDTH, y + THUMBNAIL_SIZES.CONE_WIDTH);
        ctx.closePath();
        ctx.fill();
    } else if (obj.type === 'puck') {
        ctx.fillStyle = '#000';
        ctx.beginPath();
        ctx.arc(x, y, THUMBNAIL_SIZES.PUCK_RADIUS, 0, 2 * Math.PI);
        ctx.fill();
    } else if (obj.type === 'line' || obj.type === 'freehand') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
            ctx.stroke();
        }
    } else if (obj.type === 'arrow' || obj.type === 'freehand_arrow') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.fillStyle = obj.color || '#333';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        
        let x2, y2, angle;
        const headlen = THUMBNAIL_SIZES.ARROW_HEAD_LENGTH;
        
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
            
            const last = obj.points[obj.points.length - 1];
            const secondLast = obj.points[obj.points.length - 2];
            x2 = last.x * uniformScale + offsetX;
            y2 = last.y * uniformScale + offsetY;
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
            x2 = (obj.x2 || 0) * uniformScale + offsetX;
            y2 = (obj.y2 || 0) * uniformScale + offsetY;
            angle = Math.atan2(y2 - y1, x2 - x1);
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }
        
        if (x2 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    } else if (obj.type === 'dashed' || obj.type === 'freehand_dashed') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.setLineDash([4, 3]);
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
            ctx.stroke();
        }
        ctx.setLineDash([]);
    } else if (obj.type === 'squiggly') {
        // Squiggly line
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
            ctx.stroke();
        }
    } else if (obj.type === 'freehand_skating' || obj.type === 'skating_forward') {
        // Skating lines with arrows
        ctx.strokeStyle = obj.color || '#0033a0';
        ctx.fillStyle = obj.color || '#0033a0';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        let x2, y2, angle;
        const headlen = THUMBNAIL_SIZES.ARROW_HEAD_LENGTH;
        
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
            
            const last = obj.points[obj.points.length - 1];
            const secondLast = obj.points[obj.points.length - 2];
            x2 = last.x * uniformScale + offsetX;
            y2 = last.y * uniformScale + offsetY;
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
            x2 = (obj.x2 || 0) * uniformScale + offsetX;
            y2 = (obj.y2 || 0) * uniformScale + offsetY;
            angle = Math.atan2(y2 - y1, x2 - x1);
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }
        
        if (x2 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    } else if (obj.type === 'skating_backward') {
        // Backward skating - dashed with arrow
        ctx.strokeStyle = obj.color || '#c41e3a';
        ctx.fillStyle = obj.color || '#c41e3a';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.setLineDash([6, 3]);
        
        let x2, y2, angle;
        const headlen = THUMBNAIL_SIZES.ARROW_HEAD_LENGTH;
        
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
            
            const last = obj.points[obj.points.length - 1];
            const secondLast = obj.points[obj.points.length - 2];
            x2 = last.x * uniformScale + offsetX;
            y2 = last.y * uniformScale + offsetY;
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
            x2 = (obj.x2 || 0) * uniformScale + offsetX;
            y2 = (obj.y2 || 0) * uniformScale + offsetY;
            angle = Math.atan2(y2 - y1, x2 - x1);
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }
        ctx.setLineDash([]);
        
        if (x2 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
    } else if (obj.type === 'skating_lateral' || obj.type === 'skating_ccuts') {
        // Lateral/C-cuts skating - solid line
        ctx.strokeStyle = obj.color || '#10b981';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
        } else if (obj.x1 !== undefined) {
            ctx.beginPath();
            ctx.moveTo((obj.x1 || 0) * uniformScale + offsetX, (obj.y1 || 0) * uniformScale + offsetY);
            ctx.lineTo((obj.x2 || 0) * uniformScale + offsetX, (obj.y2 || 0) * uniformScale + offsetY);
            ctx.stroke();
        }
    } else if (obj.type === 'skating_forward_puck' || obj.type === 'skating_backward_puck') {
        // Skating with puck - line with arrow and puck circle
        const color = obj.color || '#00bfff';
        ctx.strokeStyle = color;
        ctx.fillStyle = color;
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        
        if (obj.type === 'skating_backward_puck') {
            ctx.setLineDash([6, 3]);
        }
        
        let x1, y1, x2, y2, angle;
        const headlen = THUMBNAIL_SIZES.ARROW_HEAD_LENGTH;
        
        if (obj.points && obj.points.length > 1) {
            x1 = obj.points[0].x * uniformScale + offsetX;
            y1 = obj.points[0].y * uniformScale + offsetY;
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
            
            const last = obj.points[obj.points.length - 1];
            const secondLast = obj.points[obj.points.length - 2];
            x2 = last.x * uniformScale + offsetX;
            y2 = last.y * uniformScale + offsetY;
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            x1 = (obj.x1 || 0) * uniformScale + offsetX;
            y1 = (obj.y1 || 0) * uniformScale + offsetY;
            x2 = (obj.x2 || 0) * uniformScale + offsetX;
            y2 = (obj.y2 || 0) * uniformScale + offsetY;
            angle = Math.atan2(y2 - y1, x2 - x1);
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }
        ctx.setLineDash([]);
        
        // Arrow
        if (x2 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.closePath();
            ctx.fill();
        }
        
        // Puck at start
        if (x1 !== undefined) {
            ctx.fillStyle = '#000';
            ctx.beginPath();
            ctx.arc(x1, y1, THUMBNAIL_SIZES.PUCK_SMALL_RADIUS, 0, 2 * Math.PI);
            ctx.fill();
        }
    } else if (obj.type === 'pass') {
        // Pass - dashed with hollow arrow
        ctx.strokeStyle = obj.color || '#0033a0';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.setLineDash([5, 3]);
        
        let x2, y2, angle;
        const headlen = THUMBNAIL_SIZES.ARROW_HEAD_LENGTH;
        
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
            
            const last = obj.points[obj.points.length - 1];
            const secondLast = obj.points[obj.points.length - 2];
            x2 = last.x * uniformScale + offsetX;
            y2 = last.y * uniformScale + offsetY;
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
            x2 = (obj.x2 || 0) * uniformScale + offsetX;
            y2 = (obj.y2 || 0) * uniformScale + offsetY;
            angle = Math.atan2(y2 - y1, x2 - x1);
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }
        ctx.setLineDash([]);
        
        // Hollow arrow
        if (x2 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 6), y2 - headlen * Math.sin(angle - Math.PI / 6));
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 6), y2 - headlen * Math.sin(angle + Math.PI / 6));
            ctx.stroke();
        }
    } else if (obj.type === 'shot') {
        // Shot - thick solid with large arrow
        ctx.strokeStyle = obj.color || '#c41e3a';
        ctx.fillStyle = obj.color || '#c41e3a';
        ctx.lineWidth = THUMBNAIL_SIZES.SHOT_LINE_WIDTH;
        ctx.lineCap = 'round';
        
        let x2, y2, angle;
        const headlen = THUMBNAIL_SIZES.SHOT_ARROW_HEAD_LENGTH;
        
        if (obj.points && obj.points.length > 1) {
            ctx.beginPath();
            ctx.moveTo(obj.points[0].x * uniformScale + offsetX, obj.points[0].y * uniformScale + offsetY);
            for (let i = 1; i < obj.points.length; i++) {
                ctx.lineTo(obj.points[i].x * uniformScale + offsetX, obj.points[i].y * uniformScale + offsetY);
            }
            ctx.stroke();
            
            const last = obj.points[obj.points.length - 1];
            const secondLast = obj.points[obj.points.length - 2];
            x2 = last.x * uniformScale + offsetX;
            y2 = last.y * uniformScale + offsetY;
            angle = Math.atan2(last.y - secondLast.y, last.x - secondLast.x);
        } else if (obj.x1 !== undefined) {
            const x1 = (obj.x1 || 0) * uniformScale + offsetX;
            const y1 = (obj.y1 || 0) * uniformScale + offsetY;
            x2 = (obj.x2 || 0) * uniformScale + offsetX;
            y2 = (obj.y2 || 0) * uniformScale + offsetY;
            angle = Math.atan2(y2 - y1, x2 - x1);
            
            ctx.beginPath();
            ctx.moveTo(x1, y1);
            ctx.lineTo(x2, y2);
            ctx.stroke();
        }
        
        // Large filled arrow
        if (x2 !== undefined) {
            ctx.beginPath();
            ctx.moveTo(x2, y2);
            ctx.lineTo(x2 - headlen * Math.cos(angle - Math.PI / 5), y2 - headlen * Math.sin(angle - Math.PI / 5));
            ctx.lineTo(x2 - headlen * Math.cos(angle + Math.PI / 5), y2 - headlen * Math.sin(angle + Math.PI / 5));
            ctx.closePath();
            ctx.fill();
        }
    } else if (obj.type === 'pucks') {
        // Group of pucks
        ctx.fillStyle = '#000';
        const positions = [
            {x: -4, y: -4}, {x: 4, y: -4},
            {x: -4, y: 4}, {x: 4, y: 4}, {x: 0, y: 0}
        ];
        positions.forEach(pos => {
            ctx.beginPath();
            ctx.arc(x + pos.x, y + pos.y, THUMBNAIL_SIZES.PUCK_SMALL_RADIUS, 0, 2 * Math.PI);
            ctx.fill();
        });
    } else if (obj.type === 'tire') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = THUMBNAIL_SIZES.TIRE_LINE_WIDTH;
        ctx.fillStyle = 'rgba(0, 0, 0, 0.1)';
        ctx.beginPath();
        ctx.arc(x, y, THUMBNAIL_SIZES.TIRE_RADIUS, 0, 2 * Math.PI);
        ctx.fill();
        ctx.stroke();
    } else if (obj.type === 'stick') {
        ctx.strokeStyle = obj.color || '#8B4513';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.lineCap = 'round';
        ctx.beginPath();
        ctx.moveTo(x, y - 10);
        ctx.lineTo(x, y + 6);
        ctx.stroke();
        ctx.lineWidth = THUMBNAIL_SIZES.TIRE_LINE_WIDTH;
        ctx.beginPath();
        ctx.moveTo(x, y + 6);
        ctx.quadraticCurveTo(x + 4, y + 8, x + 7, y + 6);
        ctx.stroke();
    } else if (obj.type === 'net' || obj.type === 'mininet') {
        const netWidth = obj.type === 'mininet' ? 20 : 30;
        const netDepth = obj.type === 'mininet' ? 8 : 10;
        ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.strokeStyle = obj.color || '#c41e3a';
        ctx.lineWidth = THUMBNAIL_SIZES.LINE_WIDTH;
        ctx.beginPath();
        ctx.rect(x - netWidth/2, y - netDepth/2, netWidth, netDepth);
        ctx.fill();
        ctx.stroke();
    } else if (obj.type === 'text') {
        ctx.fillStyle = obj.color || '#333';
        ctx.font = 'bold ' + THUMBNAIL_SIZES.PLAYER_RADIUS + 'px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(obj.text || '', x, y);
    } else if (obj.type === 'number') {
        ctx.fillStyle = '#fff';
        ctx.beginPath();
        ctx.arc(x, y, THUMBNAIL_SIZES.PLAYER_RADIUS, 0, 2 * Math.PI);
        ctx.fill();
        ctx.strokeStyle = obj.color || '#000';
        ctx.lineWidth = 1;
        ctx.stroke();
        ctx.fillStyle = obj.color || '#000';
        ctx.font = 'bold ' + THUMBNAIL_SIZES.PLAYER_RADIUS + 'px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(obj.value || '', x, y);
    }
}
</script>
