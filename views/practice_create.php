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
                    <div class="drill-selector-card" data-drill-id="<?= $drillId ?>" data-title="<?= $title ?>" data-category="<?= $category ?>">
                        <div class="drill-selector-image" data-ice-view="<?= htmlspecialchars($drillIceView) ?>">
                            <?php if ($hasCustomImage): ?>
                                <img src="<?= htmlspecialchars($drill['custom_image']) ?>" alt="<?= $title ?>">
                            <?php else: ?>
                                <div class="drill-diagram-preview" data-diagram='<?= htmlspecialchars($drill['diagram_data'] ?? '[]') ?>' data-center-logo="<?= htmlspecialchars($centerLogoUrl) ?>">
                                    <canvas class="drill-thumbnail-canvas"></canvas>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="drill-selector-content">
                            <h4 class="drill-selector-title"><?= $title ?></h4>
                            <?php if (!empty($category)): ?>
                                <span class="drill-selector-category"><?= $category ?></span>
                            <?php endif; ?>
                            <p class="drill-selector-description"><?= $description ?><?= strlen($drill['description'] ?? '') > 100 ? '...' : '' ?></p>
                        </div>
                        <button type="button" class="btn btn-primary btn-sm drill-add-btn"><i class="fas fa-plus"></i> Add</button>
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

/* Drill Selector Modal - Card-based layout */
.drill-selector-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

@media (max-width: 768px) {
    .drill-selector-grid {
        grid-template-columns: 1fr;
    }
}

.drill-selector-card {
    display: flex;
    flex-direction: column;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.2s;
}

.drill-selector-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.drill-selector-image {
    position: relative;
    width: 100%;
    /* NHL rink aspect ratio: 200 ft (length) × 85 ft (width)
     * For horizontal layout: height/width = 85/200 = 0.425 = 42.5% */
    padding-top: 42.5%;
    background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
    border-bottom: 1px solid var(--border);
    overflow: hidden;
}

.drill-selector-image[data-ice-view="half-top"],
.drill-selector-image[data-ice-view="half-bottom"] {
    /* Half ice view (vertical): width/half-length = 85/100 = 0.85, inverted = 117.6% */
    padding-top: 117.6%;
}

.drill-selector-image[data-ice-view="left-zone"],
.drill-selector-image[data-ice-view="right-zone"] {
    /* Zone view: approximately 85% aspect ratio */
    padding-top: 85%;
}

.drill-selector-image[data-ice-view="center"] {
    /* Center ice view: similar to half ice */
    padding-top: 118.1%;
}

.drill-selector-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.drill-selector-image .drill-diagram-preview {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
}

.drill-selector-image .drill-thumbnail-canvas {
    width: 100%;
    height: 100%;
}

.drill-selector-content {
    padding: 12px;
    flex: 1;
}

.drill-selector-title {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0 0 4px 0;
    line-height: 1.3;
}

.drill-selector-category {
    display: inline-block;
    font-size: 10px;
    color: var(--primary);
    background: rgba(107, 70, 193, 0.15);
    padding: 2px 6px;
    border-radius: 4px;
    margin-bottom: 6px;
}

.drill-selector-description {
    font-size: 12px;
    color: var(--text-dim);
    line-height: 1.4;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.drill-add-btn {
    margin: 0 12px 12px 12px;
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
    document.getElementById('drillSelectorModal').classList.add('active');
}

function closeDrillSelector() {
    document.getElementById('drillSelectorModal').classList.remove('active');
}

function filterDrillSelector() {
    const search = document.getElementById('drillSelectorSearch').value.toLowerCase();
    // Handle both old (.drill-selector-item) and new (.drill-selector-card) selectors
    const items = document.querySelectorAll('.drill-selector-card, .drill-selector-item');
    items.forEach(item => {
        const title = (item.dataset.title || '').toLowerCase();
        const category = (item.dataset.category || '').toLowerCase();
        const matches = title.includes(search) || category.includes(search);
        item.style.display = matches ? '' : 'none';
    });
}

// Use event delegation for drill selection
document.addEventListener('click', function(e) {
    // Handle new card-based selector
    const drillCard = e.target.closest('.drill-selector-card');
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
    CORNER_RADIUS: 28 / 85
};

// Thumbnail object sizes for consistent rendering
const THUMBNAIL_SIZES = {
    PLAYER_RADIUS: 8,
    CONE_HEIGHT: 8,
    CONE_WIDTH: 5,
    PUCK_RADIUS: 4,
    ARROW_HEAD_LENGTH: 6,
    LINE_WIDTH: 2,
    FONT_SIZE: 6
};

// Render drill thumbnails in selector modal (matching drills_library.php)
function renderDrillSelectorThumbnails() {
    const previews = document.querySelectorAll('.drill-selector-card .drill-diagram-preview');
    
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
            // Draw ice background
            ctx.fillStyle = '#f0f7fa';
            ctx.fillRect(0, 0, w, h);
            
            // Draw center branding
            ctx.save();
            ctx.globalAlpha = 0.12;
            
            if (logoLoaded && logoImage) {
                const maxLogoWidth = w * 0.35;
                const maxLogoHeight = h * 0.3;
                const imgAspect = logoImage.width / logoImage.height;
                let logoWidth = maxLogoWidth;
                let logoHeight = logoWidth / imgAspect;
                
                if (logoHeight > maxLogoHeight) {
                    logoHeight = maxLogoHeight;
                    logoWidth = logoHeight * imgAspect;
                }
                
                ctx.drawImage(logoImage, (w - logoWidth) / 2, (h - logoHeight) / 2, logoWidth, logoHeight);
            } else {
                ctx.fillStyle = '#7000a4';
                ctx.font = 'bold 18px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                ctx.fillText('ARCTIC WOLVES', w/2, h/2);
            }
            ctx.restore();
            
            // Draw rink markings based on ice view
            drawThumbnailRink(ctx, w, h, iceView);
            
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
            
            // Draw rink border
            drawThumbnailBorder(ctx, w, h, iceView);
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

function drawThumbnailRink(ctx, w, h, iceView) {
    // Center line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    // Blue lines
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    const blueLinePos = NHL_RINK.BLUE_LINE;
    ctx.beginPath();
    ctx.moveTo(w * blueLinePos, 0);
    ctx.lineTo(w * blueLinePos, h);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(w * (1 - blueLinePos), 0);
    ctx.lineTo(w * (1 - blueLinePos), h);
    ctx.stroke();
    
    // Center circle
    ctx.beginPath();
    ctx.arc(w/2, h/2, h * NHL_RINK.CENTER_CIRCLE_RADIUS, 0, 2 * Math.PI);
    ctx.stroke();
    
    // Center dot
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 3, 0, 2 * Math.PI);
    ctx.fill();
    
    // Faceoff circles
    ctx.strokeStyle = '#c41e3a';
    const faceoffRadius = h * NHL_RINK.FACEOFF_RADIUS;
    const faceoffFromGoal = NHL_RINK.GOAL_LINE + NHL_RINK.FACEOFF_FROM_GOAL;
    const faceoffFromBoards = NHL_RINK.FACEOFF_FROM_BOARDS;
    
    const circles = [
        { x: w * faceoffFromGoal, y: h * faceoffFromBoards },
        { x: w * faceoffFromGoal, y: h * (1 - faceoffFromBoards) },
        { x: w * (1 - faceoffFromGoal), y: h * faceoffFromBoards },
        { x: w * (1 - faceoffFromGoal), y: h * (1 - faceoffFromBoards) }
    ];
    circles.forEach(circle => {
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, faceoffRadius, 0, 2 * Math.PI);
        ctx.stroke();
        ctx.fillStyle = '#c41e3a';
        ctx.beginPath();
        ctx.arc(circle.x, circle.y, 2, 0, 2 * Math.PI);
        ctx.fill();
    });
    
    // Goal creases
    const creaseRadius = h * NHL_RINK.CREASE_RADIUS;
    const goalLinePos = NHL_RINK.GOAL_LINE;
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
}

function drawThumbnailBorder(ctx, w, h, iceView) {
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    const cornerRadius = h * NHL_RINK.CORNER_RADIUS;
    
    ctx.beginPath();
    ctx.moveTo(cornerRadius, 0);
    ctx.lineTo(w - cornerRadius, 0);
    ctx.quadraticCurveTo(w, 0, w, cornerRadius);
    ctx.lineTo(w, h - cornerRadius);
    ctx.quadraticCurveTo(w, h, w - cornerRadius, h);
    ctx.lineTo(cornerRadius, h);
    ctx.quadraticCurveTo(0, h, 0, h - cornerRadius);
    ctx.lineTo(0, cornerRadius);
    ctx.quadraticCurveTo(0, 0, cornerRadius, 0);
    ctx.closePath();
    ctx.stroke();
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
