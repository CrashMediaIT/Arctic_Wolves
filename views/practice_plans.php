<?php
/**
 * Practice Plans View
 * Browse, create, and manage practice plans
 */

require_once __DIR__ . '/../security.php';

$can_create = hasPermission($pdo, $user_id, $user_role, 'create_practice_plans');
$can_delete = hasPermission($pdo, $user_id, $user_role, 'delete_practice_plans');
$can_share = hasPermission($pdo, $user_id, $user_role, 'share_practice_plans');

// Get filters
$age_group_filter = $_GET['age_group'] ?? '';
$focus_filter = $_GET['focus'] ?? '';

// Build query
$where = [];
$params = [];

if (!empty($age_group_filter)) {
    $where[] = "pp.age_group = ?";
    $params[] = $age_group_filter;
}

if (!empty($focus_filter)) {
    $where[] = "pp.focus_area = ?";
    $params[] = $focus_filter;
}

$where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

// Get practice plans
$stmt = $pdo->prepare("
    SELECT pp.*, 
           u.first_name, u.last_name,
           COUNT(ppd.id) as drill_count
    FROM practice_plans pp
    LEFT JOIN users u ON pp.created_by = u.id
    LEFT JOIN practice_plan_drills ppd ON pp.id = ppd.plan_id
    $where_clause
    GROUP BY pp.id
    ORDER BY pp.created_at DESC
");
$stmt->execute($params);
$plans = $stmt->fetchAll();

// Get all available drills for the create modal
$drills = $pdo->query("
    SELECT d.*, dc.name as category_name
    FROM drills d
    LEFT JOIN drill_categories dc ON d.category_id = dc.id
    ORDER BY d.title
")->fetchAll();

// Get unique age groups and focus areas
$age_groups = $pdo->query("SELECT DISTINCT age_group FROM practice_plans WHERE age_group IS NOT NULL AND age_group != '' ORDER BY age_group")->fetchAll(PDO::FETCH_COLUMN);
$focus_areas = $pdo->query("SELECT DISTINCT focus_area FROM practice_plans WHERE focus_area IS NOT NULL AND focus_area != '' ORDER BY focus_area")->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        flex-wrap: wrap;
        gap: 15px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn {
        padding: 10px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: 0.2s;
        font-size: 13px;
    }
    .btn:hover {
        background: #5a0080;
        transform: translateY(-2px);
    }
    .btn-secondary {
        background: #1e293b;
        color: #fff;
    }
    .btn-secondary:hover {
        background: #2d3b52;
    }
    .filter-bar {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
        min-width: 200px;
    }
    .filter-label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        margin-bottom: 8px;
        letter-spacing: 0.5px;
    }
    .filter-select {
        width: 100%;
        padding: 10px 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 13px;
    }
    .filter-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    .plans-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    .plan-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        transition: 0.2s;
    }
    .plan-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .plan-header {
        margin-bottom: 12px;
    }
    .plan-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    .plan-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-bottom: 10px;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(255, 77, 0, 0.1);
        color: var(--primary);
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-secondary {
        background: #1e293b;
        color: #94a3b8;
    }
    .plan-description {
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.5;
        margin-bottom: 15px;
    }
    .plan-meta {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        font-size: 12px;
        color: #64748b;
        margin-bottom: 12px;
    }
    .plan-meta-item {
        display: flex;
        align-items: center;
        gap: 5px;
    }
    .plan-actions {
        display: flex;
        gap: 8px;
        padding-top: 15px;
        border-top: 1px solid #1e293b;
        flex-wrap: wrap;
    }
    .btn-icon {
        padding: 8px 12px;
        background: #1e293b;
        color: #fff;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        transition: 0.2s;
    }
    .btn-icon:hover {
        background: #2d3b52;
    }
    .btn-icon.danger:hover {
        background: #dc2626;
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
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    .close-modal {
        background: none;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
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
    .form-input, .form-textarea, .form-select {
        width: 100%;
        padding: 10px 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
    }
    .form-textarea {
        min-height: 80px;
        resize: vertical;
    }
    .form-input:focus, .form-textarea:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    .drills-selector {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .drill-search {
        margin-bottom: 10px;
    }
    .available-drills {
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 15px;
    }
    .drill-item {
        padding: 10px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 4px;
        margin-bottom: 8px;
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .drill-item:hover {
        border-color: var(--primary);
    }
    .drill-item-info {
        flex: 1;
    }
    .drill-item-title {
        font-weight: 600;
        color: #fff;
        font-size: 13px;
        margin-bottom: 2px;
    }
    .drill-item-meta {
        font-size: 11px;
        color: #64748b;
    }
    .selected-drills {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 20px;
    }
    .selected-drills-header {
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
        font-size: 13px;
    }
    .selected-drill {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 4px;
        padding: 12px;
        margin-bottom: 8px;
    }
    .selected-drill-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .selected-drill-title {
        font-weight: 600;
        color: #fff;
        font-size: 13px;
    }
    .selected-drill-controls {
        display: grid;
        grid-template-columns: 1fr 1fr auto;
        gap: 8px;
        align-items: end;
    }
    .alert {
        padding: 12px 15px;
        border-radius: 6px;
        margin-bottom: 20px;
        font-size: 13px;
    }
    .alert-success {
        background: rgba(0, 255, 136, 0.1);
        border: 1px solid #00ff88;
        color: #00ff88;
    }
    .alert-error {
        background: rgba(239, 68, 68, 0.1);
        border: 1px solid #ef4444;
        color: #ef4444;
    }
    .alert-info {
        background: rgba(59, 130, 246, 0.1);
        border: 1px solid #3b82f6;
        color: #3b82f6;
    }
    .share-link-container {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 12px;
        display: flex;
        gap: 8px;
        margin-top: 15px;
    }
    .share-link-input {
        flex: 1;
        padding: 8px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 4px;
        color: #fff;
        font-size: 12px;
    }
    @media (max-width: 768px) {
        .plans-grid {
            grid-template-columns: 1fr;
        }
        .modal-content {
            padding: 20px;
        }
    }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-clipboard-list"></i> Practice Plans</h1>
    <?php if ($can_create): ?>
        <button class="btn" onclick="openPlanModal()">
            <i class="fas fa-plus"></i> Create Plan
        </button>
    <?php endif; ?>
</div>

<?php if (isset($_GET['status'])): ?>
    <div class="alert alert-success">
        <?php
        $messages = [
            'plan_saved' => 'Practice plan saved successfully!',
            'plan_deleted' => 'Practice plan deleted successfully!',
            'token_generated' => 'Share link generated!',
            'token_removed' => 'Share link removed!'
        ];
        echo $messages[$_GET['status']] ?? 'Operation completed successfully!';
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
    <div class="alert alert-error">
        <?php
        $errors = [
            'title_required' => 'Plan title is required.',
            'save_failed' => 'Failed to save practice plan.',
            'delete_failed' => 'Failed to delete practice plan.',
            'token_failed' => 'Failed to generate share token.',
            'permission_denied' => 'You do not have permission to perform this action.'
        ];
        echo $errors[$_GET['error']] ?? 'An error occurred.';
        ?>
    </div>
<?php endif; ?>

<div class="filter-bar">
    <div class="filter-group">
        <label class="filter-label">Age Group</label>
        <select class="filter-select" id="ageGroupFilter">
            <option value="">All Age Groups</option>
            <?php foreach ($age_groups as $age): ?>
                <option value="<?= htmlspecialchars($age) ?>" <?= $age_group_filter == $age ? 'selected' : '' ?>>
                    <?= htmlspecialchars($age) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="filter-group">
        <label class="filter-label">Focus Area</label>
        <select class="filter-select" id="focusFilter">
            <option value="">All Focus Areas</option>
            <?php foreach ($focus_areas as $focus): ?>
                <option value="<?= htmlspecialchars($focus) ?>" <?= $focus_filter == $focus ? 'selected' : '' ?>>
                    <?= htmlspecialchars($focus) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn" onclick="applyFilters()">
        <i class="fas fa-filter"></i> Apply
    </button>
</div>

<?php if (empty($plans)): ?>
    <div style="text-align: center; padding: 60px 20px; color: #64748b;">
        <i class="fas fa-clipboard-list" style="font-size: 48px; margin-bottom: 20px; opacity: 0.3;"></i>
        <p style="font-size: 16px;">No practice plans found. <?= $can_create ? 'Create your first practice plan to get started!' : '' ?></p>
    </div>
<?php else: ?>
    <div class="plans-grid">
        <?php foreach ($plans as $plan): ?>
            <div class="plan-card">
                <div class="plan-header">
                    <h3 class="plan-title"><?= htmlspecialchars($plan['title']) ?></h3>
                    <div class="plan-badges">
                        <?php if ($plan['age_group']): ?>
                            <span class="badge"><?= htmlspecialchars($plan['age_group']) ?></span>
                        <?php endif; ?>
                        <?php if ($plan['focus_area']): ?>
                            <span class="badge badge-secondary"><?= htmlspecialchars($plan['focus_area']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if ($plan['description']): ?>
                    <p class="plan-description"><?= htmlspecialchars(substr($plan['description'], 0, 120)) ?><?= strlen($plan['description']) > 120 ? '...' : '' ?></p>
                <?php endif; ?>
                
                <div class="plan-meta">
                    <span class="plan-meta-item">
                        <i class="fas fa-clock"></i> <?= $plan['total_duration'] ?> min
                    </span>
                    <span class="plan-meta-item">
                        <i class="fas fa-hockey-puck"></i> <?= $plan['drill_count'] ?> drill<?= $plan['drill_count'] != 1 ? 's' : '' ?>
                    </span>
                    <span class="plan-meta-item">
                        <i class="fas fa-user"></i> <?= htmlspecialchars($plan['first_name'] . ' ' . $plan['last_name']) ?>
                    </span>
                </div>
                
                <div class="plan-actions">
                    <button class="btn-icon" onclick="viewPlan(<?= $plan['id'] ?>)">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <?php if ($can_share && $plan['created_by'] == $user_id): ?>
                        <button class="btn-icon" onclick="openShareModal(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['share_token'] ?? '') ?>')">
                            <i class="fas fa-share"></i> Share
                        </button>
                    <?php endif; ?>
                    <?php if ($can_create && $plan['created_by'] == $user_id): ?>
                        <button class="btn-icon" onclick="editPlan(<?= $plan['id'] ?>)">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    <?php endif; ?>
                    <?php if ($can_delete && $plan['created_by'] == $user_id): ?>
                        <form method="POST" action="process_practice_plans.php" style="display: inline;" onsubmit="return confirm('Delete this practice plan?');">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="delete_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit" class="btn-icon danger">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create/Edit Plan Modal -->
<div id="planModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="planModalTitle">Create Practice Plan</h2>
            <button class="close-modal" onclick="closePlanModal()">&times;</button>
        </div>
        <form method="POST" action="process_practice_plans.php" id="planForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="save_plan">
            <input type="hidden" name="plan_id" id="planId">
            <input type="hidden" name="drills" id="drillsData">
            
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Plan Title *</label>
                    <input type="text" name="title" id="planTitle" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Total Duration (min)</label>
                    <input type="number" name="total_duration" id="planDuration" class="form-input" value="60" min="1">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" id="planDescription" class="form-textarea"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                <div class="form-group">
                    <label class="form-label">Age Group</label>
                    <input type="text" name="age_group" id="planAgeGroup" class="form-input" placeholder="e.g., U10, U12, U14">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Focus Area</label>
                    <input type="text" name="focus_area" id="planFocusArea" class="form-input" placeholder="e.g., Skating, Shooting">
                </div>
            </div>
            
            <div class="form-group">
                <label class="form-label">Select Drills</label>
                <div class="drills-selector">
                    <div class="drill-search">
                        <input type="text" id="drillSearchInput" class="form-input" placeholder="Search drills..." onkeyup="filterDrills()">
                    </div>
                    <div class="available-drills" id="availableDrills">
                        <?php foreach ($drills as $drill): ?>
                            <div class="drill-item" 
                                 data-drill-id="<?= $drill['id'] ?>" 
                                 data-drill-title="<?= htmlspecialchars($drill['title']) ?>" 
                                 data-drill-duration="<?= $drill['duration_minutes'] ?? 10 ?>"
                                 onclick="addDrillFromData(this)">
                                <div class="drill-item-info">
                                    <div class="drill-item-title"><?= htmlspecialchars($drill['title']) ?></div>
                                    <div class="drill-item-meta">
                                        <?= $drill['category_name'] ? htmlspecialchars($drill['category_name']) . ' • ' : '' ?>
                                        <?= $drill['duration_minutes'] ? $drill['duration_minutes'] . ' min' : '' ?>
                                    </div>
                                </div>
                                <button type="button" class="btn-icon" onclick="event.stopPropagation(); addDrillFromData(this.parentElement)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            
            <div class="selected-drills" id="selectedDrills" style="display: none;">
                <div class="selected-drills-header">
                    <i class="fas fa-list"></i> Selected Drills (<span id="drillCount">0</span>)
                </div>
                <div id="selectedDrillsList"></div>
            </div>
            
            <button type="submit" class="btn" style="width: 100%;">
                <i class="fas fa-save"></i> Save Practice Plan
            </button>
        </form>
    </div>
</div>

<!-- Share Modal -->
<div id="shareModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 class="modal-title">Share Practice Plan</h2>
            <button class="close-modal" onclick="closeShareModal()">&times;</button>
        </div>
        
        <div id="shareContent">
            <p style="color: #94a3b8; margin-bottom: 15px;">Generate a shareable link to this practice plan:</p>
            
            <form method="POST" action="process_practice_plans.php" id="shareForm">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="plan_id" id="sharePlanId">
                <input type="hidden" name="action" value="generate_share_token">
                <button type="submit" class="btn" style="width: 100%;">
                    <i class="fas fa-link"></i> Generate Share Link
                </button>
            </form>
        </div>
        
        <div id="shareLinkDisplay" style="display: none;">
            <p style="color: #94a3b8; margin-bottom: 10px;">Share this link with others:</p>
            <div class="share-link-container">
                <input type="text" class="share-link-input" id="shareLinkInput" readonly>
                <button class="btn" onclick="copyShareLink()">
                    <i class="fas fa-copy"></i> Copy
                </button>
            </div>
            <form method="POST" action="process_practice_plans.php" style="margin-top: 15px;">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="plan_id" id="removeSharePlanId">
                <input type="hidden" name="action" value="remove_share_token">
                <button type="submit" class="btn btn-secondary" style="width: 100%;" onclick="return confirm('Remove share link? The current link will no longer work.');">
                    <i class="fas fa-times"></i> Remove Share Link
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let selectedDrills = [];

function showNotification(message, type = 'info') {
    const alertClass = type === 'error' ? 'alert-error' : type === 'success' ? 'alert-success' : 'alert-success';
    const alertDiv = document.createElement('div');
    alertDiv.className = 'alert ' + alertClass;
    alertDiv.textContent = message;
    alertDiv.style.position = 'fixed';
    alertDiv.style.top = '20px';
    alertDiv.style.right = '20px';
    alertDiv.style.zIndex = '10000';
    alertDiv.style.minWidth = '300px';
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}

function openPlanModal() {
    document.getElementById('planModal').classList.add('active');
    document.getElementById('planModalTitle').textContent = 'Create Practice Plan';
    document.getElementById('planForm').reset();
    document.getElementById('planId').value = '';
    selectedDrills = [];
    updateSelectedDrillsDisplay();
}

function closePlanModal() {
    document.getElementById('planModal').classList.remove('active');
}

function openShareModal(planId, shareToken) {
    document.getElementById('shareModal').classList.add('active');
    document.getElementById('sharePlanId').value = planId;
    document.getElementById('removeSharePlanId').value = planId;
    
    if (shareToken) {
        const baseUrl = window.location.origin + window.location.pathname.replace('dashboard.php', '');
        const shareUrl = baseUrl + 'practice_plan_share.php?token=' + shareToken;
        document.getElementById('shareLinkInput').value = shareUrl;
        document.getElementById('shareContent').style.display = 'none';
        document.getElementById('shareLinkDisplay').style.display = 'block';
    } else {
        document.getElementById('shareContent').style.display = 'block';
        document.getElementById('shareLinkDisplay').style.display = 'none';
    }
}

function closeShareModal() {
    document.getElementById('shareModal').classList.remove('active');
}

function copyShareLink() {
    const input = document.getElementById('shareLinkInput');
    input.select();
    
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(input.value).then(() => {
            showNotification('Share link copied to clipboard!', 'success');
        }).catch(() => {
            document.execCommand('copy');
            showNotification('Share link copied to clipboard!', 'success');
        });
    } else {
        document.execCommand('copy');
        showNotification('Share link copied to clipboard!', 'success');
    }
}

function addDrillFromData(element) {
    const drillId = parseInt(element.dataset.drillId);
    const drillTitle = element.dataset.drillTitle;
    const defaultDuration = parseInt(element.dataset.drillDuration) || 10;
    addDrill(drillId, drillTitle, defaultDuration);
}

function addDrill(drillId, drillTitle, defaultDuration) {
    // Check if already added
    if (selectedDrills.find(d => d.drill_id === drillId)) {
        showNotification('This drill is already in your plan', 'error');
        return;
    }
    
    selectedDrills.push({
        drill_id: drillId,
        title: drillTitle,
        duration: defaultDuration || 10,
        notes: ''
    });
    
    updateSelectedDrillsDisplay();
}

function removeDrill(index) {
    selectedDrills.splice(index, 1);
    updateSelectedDrillsDisplay();
}

function moveDrillUp(index) {
    if (index > 0) {
        const temp = selectedDrills[index];
        selectedDrills[index] = selectedDrills[index - 1];
        selectedDrills[index - 1] = temp;
        updateSelectedDrillsDisplay();
    }
}

function moveDrillDown(index) {
    if (index < selectedDrills.length - 1) {
        const temp = selectedDrills[index];
        selectedDrills[index] = selectedDrills[index + 1];
        selectedDrills[index + 1] = temp;
        updateSelectedDrillsDisplay();
    }
}

function updateDrillDuration(index, duration) {
    selectedDrills[index].duration = parseInt(duration) || 0;
}

function updateSelectedDrillsDisplay() {
    const container = document.getElementById('selectedDrills');
    const list = document.getElementById('selectedDrillsList');
    const countSpan = document.getElementById('drillCount');
    
    if (selectedDrills.length === 0) {
        container.style.display = 'none';
        return;
    }
    
    container.style.display = 'block';
    countSpan.textContent = selectedDrills.length;
    
    list.innerHTML = selectedDrills.map((drill, index) => `
        <div class="selected-drill">
            <div class="selected-drill-header">
                <span class="selected-drill-title">${index + 1}. ${drill.title}</span>
                <div style="display: flex; gap: 4px;">
                    ${index > 0 ? '<button type="button" class="btn-icon" onclick="moveDrillUp(' + index + ')" title="Move Up"><i class="fas fa-arrow-up"></i></button>' : ''}
                    ${index < selectedDrills.length - 1 ? '<button type="button" class="btn-icon" onclick="moveDrillDown(' + index + ')" title="Move Down"><i class="fas fa-arrow-down"></i></button>' : ''}
                    <button type="button" class="btn-icon danger" onclick="removeDrill(' + index + ')"><i class="fas fa-times"></i></button>
                </div>
            </div>
            <div class="selected-drill-controls">
                <div class="form-group" style="margin: 0;">
                    <label class="form-label" style="margin-bottom: 4px;">Duration (min)</label>
                    <input type="number" class="form-input" value="${drill.duration}" min="1" onchange="updateDrillDuration(${index}, this.value)">
                </div>
            </div>
        </div>
    `).join('');
}

function filterDrills() {
    const search = document.getElementById('drillSearchInput').value.toLowerCase();
    const items = document.querySelectorAll('.drill-item');
    
    items.forEach(item => {
        const text = item.textContent.toLowerCase();
        item.style.display = text.includes(search) ? 'flex' : 'none';
    });
}

function viewPlan(id) {
    // In a real implementation, this would show a detailed view
    showNotification('View plan details (implement detailed view page)', 'info');
}

function editPlan(id) {
    // In a real implementation, fetch plan data via AJAX and populate form
    showNotification('Edit functionality requires AJAX implementation to load existing plan data', 'info');
}

function applyFilters() {
    const ageGroup = document.getElementById('ageGroupFilter').value;
    const focus = document.getElementById('focusFilter').value;
    
    let url = 'dashboard.php?page=practice_plans';
    if (ageGroup) url += '&age_group=' + encodeURIComponent(ageGroup);
    if (focus) url += '&focus=' + encodeURIComponent(focus);
    
    window.location.href = url;
}

// Submit form with drills data
document.getElementById('planForm').addEventListener('submit', function(e) {
    document.getElementById('drillsData').value = JSON.stringify(selectedDrills);
});

// Close modal when clicking outside
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>
