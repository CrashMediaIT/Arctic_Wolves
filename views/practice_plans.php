<?php
/**
 * Practice Plans View
 * Browse, create, and manage practice plans
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/image_helper.php';

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
           COALESCE(pp.title, pp.name) as title,
           COALESCE(pp.total_duration, pp.duration_minutes, 60) as total_duration,
           u.first_name, u.last_name,
           COUNT(ppd.id) as drill_count
    FROM practice_plans pp
    LEFT JOIN users u ON pp.created_by = u.id
    LEFT JOIN practice_plan_drills ppd ON pp.id = ppd.practice_plan_id
    $where_clause
    GROUP BY pp.id
    ORDER BY pp.created_at DESC
");
$stmt->execute($params);
$plans = $stmt->fetchAll();
$plans = decryptUserRows($plans);

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

// Fetch center ice logo URL from theme settings for drill thumbnails (same as drills_library.php)
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
        $centerLogoUrl = resolveRustfsUrl($pdo, $logoResult['logo_url']);
    }
} catch (PDOException $e) {
    error_log("Error fetching center ice logo URL: " . $e->getMessage());
}
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
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
    .filter-box { background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 12px; margin-bottom: 24px; overflow: hidden; }
    .filter-box-header { background: var(--bg-main, #0A0A0F); padding: 14px 20px; font-weight: 700; color: var(--text-white, #fff); font-size: 14px; border-bottom: 1px solid var(--border, #2D2D3F); display: flex; align-items: center; gap: 10px; }
    .filter-box-header i { color: var(--primary, #6B46C1); }
    .filter-box-content { padding: 20px; }
    .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end; }
    .filter-field { display: flex; flex-direction: column; gap: 8px; }
    .filter-field label { font-size: 12px; font-weight: 600; color: var(--text-dim, #9CA3AF); text-transform: uppercase; }
    .filter-actions { display: flex; flex-direction: row !important; gap: 8px !important; }
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
        margin-bottom: 12px;
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
        padding: 24px;
        max-width: 900px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
    }
    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
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
        padding: 16px;
        margin-bottom: 20px;
    }
    .drill-search {
        margin-bottom: 10px;
    }
    .available-drills {
        max-height: 300px;
        overflow-y: auto;
        margin-bottom: 12px;
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
        padding: 16px;
        margin-bottom: 20px;
    }
    .selected-drills-header {
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
        font-size: 13px;
    }
    /* Page Header - Financial Reports Hub Style */
    .practice-page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 32px;
        padding-bottom: 24px;
        border-bottom: 1px solid var(--border);
        flex-wrap: wrap;
        gap: 20px;
    }
    .practice-page-header .page-header-content {
        display: flex;
        align-items: center;
        gap: 20px;
    }
    .practice-page-header .page-header-icon {
        width: 56px;
        height: 56px;
        background: linear-gradient(135deg, var(--primary), #5a0080);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
    }
    .practice-page-header .page-title {
        font-size: 28px;
        font-weight: 800;
        margin: 0 0 4px 0;
        letter-spacing: -0.5px;
    }
    .practice-page-header .page-description {
        font-size: 14px;
        color: #94a3b8;
        margin: 0;
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
        margin-top: 12px;
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
    
    /* View Plan Modal Styles */
    .view-plan-info {
        padding: 20px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .view-plan-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 20px;
        align-items: center;
        margin-bottom: 15px;
    }
    .plan-detail-badges {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .plan-detail-item {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #94a3b8;
        font-size: 13px;
    }
    .plan-detail-item i {
        color: var(--primary);
    }
    .plan-description-text {
        color: #94a3b8;
        line-height: 1.6;
        margin: 0;
    }
    .section-title {
        font-size: 14px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 12px 0;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .section-title i {
        color: var(--primary);
    }
    .view-plan-share {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .view-plan-drills {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
    }
    .view-plan-drills-list {
        display: flex;
        flex-direction: column;
        gap: 16px;
    }
    .view-drill-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        overflow: hidden;
    }
    .view-drill-card:hover {
        border-color: var(--primary);
    }
    .view-drill-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        background: rgba(124, 58, 237, 0.05);
        border-bottom: 1px solid #1e293b;
    }
    .view-drill-number {
        width: 28px;
        height: 28px;
        background: var(--primary);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 700;
        font-size: 13px;
        flex-shrink: 0;
    }
    .view-drill-title-section {
        flex: 1;
        margin-left: 12px;
    }
    .view-drill-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin: 0;
    }
    .view-drill-category {
        font-size: 12px;
        color: #64748b;
        margin-top: 2px;
    }
    .view-drill-duration {
        background: #1e293b;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        color: #94a3b8;
        font-weight: 600;
    }
    .view-drill-body {
        padding: 20px;
        display: grid;
        grid-template-columns: 1fr;
        gap: 20px;
    }
    .view-drill-diagram {
        background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
        border: 2px solid #0033a0;
        border-radius: 12px;
        min-height: 300px;
        max-height: 400px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        width: 100%;
    }
    .view-drill-diagram img {
        max-width: 100%;
        max-height: 380px;
        width: auto;
        height: auto;
        object-fit: contain;
    }
    .view-drill-diagram canvas {
        width: 100%;
        height: 300px;
    }
    .view-drill-details {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }
    .drill-detail-section {
        margin-bottom: 8px;
    }
    .drill-detail-section h5 {
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        color: #64748b;
        margin: 0 0 6px 0;
        letter-spacing: 0.5px;
    }
    .drill-detail-section p {
        color: #94a3b8;
        font-size: 13px;
        line-height: 1.5;
        margin: 0;
    }
    .view-drill-link {
        margin-top: auto;
    }
    .btn-view-full {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 8px 16px;
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        text-decoration: none;
        transition: 0.2s;
    }
    .btn-view-full:hover {
        background: var(--primary);
        color: #fff;
    }
    .no-diagram-placeholder {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
        color: #64748b;
        font-size: 14px;
        padding: 40px;
    }
    .no-diagram-placeholder i {
        font-size: 64px;
        opacity: 0.3;
    }
    @media (max-width: 768px) {
        .view-drill-body {
            grid-template-columns: 1fr;
        }
        .view-drill-diagram {
            min-height: 200px;
            max-height: 300px;
        }
        .view-drill-diagram canvas {
            height: 200px;
        }
    }
    
    /* Drill Cards for Practice Plan Modal - Same style as drill library */
    .available-drills-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 16px;
        max-height: 400px;
        overflow-y: auto;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .modal-drill-card {
        background: var(--bg-card, #0d1117);
        border: 1px solid var(--border, #1e293b);
        border-radius: 12px;
        overflow: hidden;
        transition: transform 0.2s, border-color 0.2s;
    }
    .modal-drill-card:hover {
        border-color: var(--primary, #7c3aed);
    }
    .modal-drill-card.added {
        opacity: 0.5;
        pointer-events: none;
    }
    .modal-drill-card .drill-image {
        height: 120px;
        background: linear-gradient(135deg, #f0f7fa 0%, #e8f4f8 100%);
        border-bottom: 2px solid #0033a0;
        position: relative;
        overflow: hidden;
    }
    .modal-drill-card .drill-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .modal-drill-card .drill-diagram-preview {
        width: 100%;
        height: 100%;
    }
    .modal-drill-card .drill-diagram-preview canvas {
        width: 100%;
        height: 100%;
    }
    .modal-drill-card .drill-content {
        padding: 12px 16px;
    }
    .modal-drill-card .drill-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 8px;
        gap: 8px;
    }
    .modal-drill-card .drill-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--text-white, #fff);
        flex: 1;
        margin: 0;
    }
    .modal-drill-card .drill-category {
        display: flex;
        gap: 6px;
        flex-shrink: 0;
    }
    .modal-drill-card .category-badge {
        background: rgba(107, 70, 193, 0.15);
        color: var(--primary, #7c3aed);
        padding: 3px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .modal-drill-card .drill-description {
        font-size: 12px;
        color: var(--text-secondary, #94a3b8);
        line-height: 1.4;
        margin-bottom: 8px;
    }
    .modal-drill-card .drill-meta {
        display: flex;
        gap: 12px;
        font-size: 11px;
        color: var(--text-dim, #64748b);
    }
    .modal-drill-card .drill-meta i {
        color: var(--primary, #7c3aed);
        margin-right: 4px;
    }
    .modal-drill-card .drill-actions {
        padding: 12px 16px;
        background: var(--bg-main, #06080b);
        border-top: 1px solid var(--border, #1e293b);
    }
    .modal-drill-card .add-drill-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    .modal-drill-card.hidden {
        display: none !important;
    }
</style>

<div class="practice-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-clipboard-list"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Practice Plans</h1>
            <p class="page-description">Browse, create, and manage practice plans</p>
        </div>
    </div>
    <?php if ($can_create): ?>
        <button class="btn btn-primary" onclick="openPlanModal()">
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

<div class="filter-box">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Practice Plans</div>
    <div class="filter-box-content">
        <div class="filter-row">
            <div class="filter-field">
                <label>Search Plans</label>
                <input type="text" class="form-select" placeholder="Search practice plans..." data-search-table="plans" id="planSearchInput">
            </div>
            <div class="filter-field">
                <label>Age Group</label>
                <select class="form-select" id="ageGroupFilter" data-filter-table="plans" data-filter-column="age_group">
                    <option value="">All Age Groups</option>
                    <?php foreach ($age_groups as $age): ?>
                        <option value="<?= htmlspecialchars($age) ?>" <?= $age_group_filter == $age ? 'selected' : '' ?>>
                            <?= htmlspecialchars($age) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Focus Area</label>
                <select class="form-select" id="focusFilter" data-filter-table="plans" data-filter-column="focus_area">
                    <option value="">All Focus Areas</option>
                    <?php foreach ($focus_areas as $focus): ?>
                        <option value="<?= htmlspecialchars($focus) ?>" <?= $focus_filter == $focus ? 'selected' : '' ?>>
                            <?= htmlspecialchars($focus) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field filter-actions">
                <button class="btn btn-primary" onclick="applyFilters()"><i class="fas fa-search"></i> Apply</button>
            </div>
        </div>
    </div>
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
                    <a href="?page=view_practice_plan&id=<?= $plan['id'] ?>" class="btn-icon">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <?php if ($can_share && $plan['created_by'] == $user_id): ?>
                        <button class="btn-icon" data-id="<?= $plan['id'] ?>" onclick="openShareModal(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['share_token'] ?? '') ?>')">
                            <i class="fas fa-share"></i> Share
                        </button>
                    <?php endif; ?>
                    <?php if ($can_create && $plan['created_by'] == $user_id): ?>
                        <button class="btn-icon" data-action="edit" data-id="<?= $plan['id'] ?>" data-modal="planModal">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    <?php endif; ?>
                    <?php if ($can_delete && $plan['created_by'] == $user_id): ?>
                        <form method="POST" action="process_practice_plans.php" style="display: inline;" onsubmit="return confirm('Delete this practice plan?');" data-form-type="delete">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="delete_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit" class="btn-icon danger" data-action="delete" data-id="<?= $plan['id'] ?>">
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
                        <input type="text" id="drillSearchInput" class="form-input" placeholder="Search drills..." onkeyup="filterModalDrills()">
                    </div>
                    <div class="available-drills-grid" id="availableDrills">
                        <?php foreach ($drills as $drill): 
                            // Extract ice view from diagram data (same as drills_library.php)
                            $drillIceView = 'full';
                            if (!empty($drill['diagram_data'])) {
                                $diagramParsed = json_decode($drill['diagram_data'], true);
                                if (is_array($diagramParsed) && isset($diagramParsed['iceView'])) {
                                    $drillIceView = $diagramParsed['iceView'];
                                }
                            }
                        ?>
                            <!-- Using same drill-card structure as drills_library.php -->
                            <div class="drill-card modal-drill-card" 
                                 data-drill-id="<?= $drill['id'] ?>" 
                                 data-drill-title="<?= htmlspecialchars($drill['title']) ?>" 
                                 data-drill-duration="<?= $drill['duration_minutes'] ?? 10 ?>"
                                 data-category="<?= $drill['category_id'] ?? '' ?>"
                                 data-title="<?= htmlspecialchars(strtolower($drill['title'])); ?>">
                                <div class="drill-image" data-ice-view="<?= htmlspecialchars($drillIceView); ?>">
                                    <?php if (!empty($drill['custom_image'])): ?>
                                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $drill['custom_image'])); ?>" alt="<?= htmlspecialchars($drill['title']); ?>">
                                    <?php else: ?>
                                        <div class="drill-diagram-preview" data-diagram='<?= htmlspecialchars($drill['diagram_data'] ?? '[]'); ?>' data-center-logo="<?= htmlspecialchars($centerLogoUrl); ?>">
                                            <canvas class="drill-thumbnail-canvas"></canvas>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="drill-content">
                                    <div class="drill-header">
                                        <h4 class="drill-title"><?= htmlspecialchars($drill['title']); ?></h4>
                                        <?php if (!empty($drill['category_name'])): ?>
                                            <div class="drill-category">
                                                <span class="category-badge"><?= htmlspecialchars($drill['category_name']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="drill-description">
                                        <?= htmlspecialchars(substr($drill['description'] ?? 'No description available', 0, 80)); ?>
                                        <?= strlen($drill['description'] ?? '') > 80 ? '...' : ''; ?>
                                    </p>
                                    <div class="drill-meta">
                                        <?php if (!empty($drill['duration_minutes'])): ?>
                                            <span><i class="fas fa-clock"></i> <?= $drill['duration_minutes']; ?> min</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="drill-actions">
                                    <button type="button" class="btn btn-primary btn-sm add-drill-btn" onclick="addDrillFromCard(this)">
                                        <i class="fas fa-plus"></i> Add to Plan
                                    </button>
                                </div>
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
            <p style="color: #94a3b8; margin-bottom: 12px;">Generate a shareable link to this practice plan:</p>
            
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
            <form method="POST" action="process_practice_plans.php" style="margin-top: 12px;">
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

<!-- View Plan Modal -->
<div id="viewPlanModal" class="modal">
    <div class="modal-content" style="max-width: 1000px;">
        <div class="modal-header">
            <h2 class="modal-title" id="viewPlanTitle">Practice Plan Details</h2>
            <button class="close-modal" onclick="closeViewPlanModal()">&times;</button>
        </div>
        
        <div id="viewPlanLoading" style="text-align: center; padding: 40px;">
            <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i>
            <p style="margin-top: 15px; color: #94a3b8;">Loading practice plan...</p>
        </div>
        
        <div id="viewPlanContent" style="display: none;">
            <!-- Plan Info Section -->
            <div class="view-plan-info">
                <div class="view-plan-meta">
                    <div class="plan-detail-badges" id="viewPlanBadges"></div>
                    <div class="plan-detail-item">
                        <i class="fas fa-clock"></i>
                        <span id="viewPlanDuration">-</span> min total
                    </div>
                    <div class="plan-detail-item">
                        <i class="fas fa-hockey-puck"></i>
                        <span id="viewPlanDrillCount">-</span> drills
                    </div>
                    <div class="plan-detail-item">
                        <i class="fas fa-user"></i>
                        <span id="viewPlanCreator">-</span>
                    </div>
                </div>
                
                <div id="viewPlanDescriptionSection" style="display: none;">
                    <h4 class="section-title"><i class="fas fa-align-left"></i> Description</h4>
                    <p id="viewPlanDescription" class="plan-description-text"></p>
                </div>
            </div>
            
            <!-- Share URL Section -->
            <div id="viewPlanShareSection" class="view-plan-share">
                <h4 class="section-title"><i class="fas fa-share-alt"></i> Share This Plan</h4>
                <div class="share-link-container">
                    <input type="text" class="share-link-input" id="viewPlanShareUrl" readonly>
                    <button class="btn" onclick="copyViewPlanShareLink()">
                        <i class="fas fa-copy"></i> Copy
                    </button>
                </div>
            </div>
            
            <!-- Drills Section -->
            <div class="view-plan-drills">
                <h4 class="section-title"><i class="fas fa-list"></i> Drills in This Plan</h4>
                <div id="viewPlanDrillsList" class="view-plan-drills-list"></div>
            </div>
        </div>
        
        <div class="modal-footer" style="border-top: 1px solid #1e293b; padding-top: 20px; margin-top: 20px;">
            <button class="btn btn-secondary" onclick="closeViewPlanModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Shared Ice Canvas Renderer - ensures consistent rink drawing across all views -->
<script src="js/ice_canvas.js"></script>
<script>
// Use shared NHL_RINK constants from ice_canvas.js
const NHL_RINK = window.ICE_CANVAS_NHL_RINK || {
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
    // Reset added state on drill cards
    document.querySelectorAll('.modal-drill-card.added').forEach(card => {
        card.classList.remove('added');
    });
    // Render drill thumbnails after modal opens (ensure canvas elements are visible)
    setTimeout(renderModalDrillThumbnails, 100);
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

// View Plan Modal Functions
let currentViewPlanId = null;
let viewPlanDrillCanvases = [];

function viewPlan(id) {
    currentViewPlanId = id;
    
    // Show modal and loading state
    document.getElementById('viewPlanModal').classList.add('active');
    document.getElementById('viewPlanLoading').style.display = 'block';
    document.getElementById('viewPlanContent').style.display = 'none';
    
    // Fetch plan details via AJAX
    const csrfToken = document.querySelector('input[name="csrf_token"]').value;
    
    fetch('process_practice_plans.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: new URLSearchParams({
            action: 'get_plan',
            plan_id: id,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPlanDetails(data.plan, data.drills);
        } else {
            showNotification(data.message || 'Failed to load practice plan', 'error');
            closeViewPlanModal();
        }
    })
    .catch(error => {
        console.error('Error fetching plan:', error);
        showNotification('Failed to load practice plan', 'error');
        closeViewPlanModal();
    });
}

function displayPlanDetails(plan, drills) {
    // Update title
    document.getElementById('viewPlanTitle').textContent = plan.name || plan.title || 'Practice Plan';
    
    // Update badges
    let badgesHtml = '';
    if (plan.age_group) {
        badgesHtml += '<span class="badge">' + escapeHtml(plan.age_group) + '</span>';
    }
    if (plan.focus_area) {
        badgesHtml += '<span class="badge badge-secondary">' + escapeHtml(plan.focus_area) + '</span>';
    }
    document.getElementById('viewPlanBadges').innerHTML = badgesHtml;
    
    // Update meta info
    document.getElementById('viewPlanDuration').textContent = plan.total_duration || calculateTotalDuration(drills);
    document.getElementById('viewPlanDrillCount').textContent = drills.length;
    document.getElementById('viewPlanCreator').textContent = plan.creator_name || 'Unknown';
    
    // Update description
    if (plan.description) {
        document.getElementById('viewPlanDescriptionSection').style.display = 'block';
        document.getElementById('viewPlanDescription').textContent = plan.description;
    } else {
        document.getElementById('viewPlanDescriptionSection').style.display = 'none';
    }
    
    // Update share URL
    if (plan.share_token) {
        const baseUrl = window.location.origin + window.location.pathname.replace('dashboard.php', '');
        const shareUrl = baseUrl + 'practice_plan_share.php?token=' + plan.share_token;
        document.getElementById('viewPlanShareUrl').value = shareUrl;
        document.getElementById('viewPlanShareSection').style.display = 'block';
    } else {
        document.getElementById('viewPlanShareSection').style.display = 'none';
    }
    
    // Build drills list with large diagrams
    let drillsHtml = '';
    viewPlanDrillCanvases = [];
    
    drills.forEach((drill, index) => {
        const drillNumber = index + 1;
        const duration = drill.duration_minutes || 10;
        const categoryName = drill.category_name || 'General';
        const description = drill.description || 'No description available.';
        const hasCustomImage = drill.custom_image && drill.custom_image.trim() !== '';
        const hasDiagramData = drill.diagram_data && drill.diagram_data.trim() !== '' && drill.diagram_data !== '[]';
        
        drillsHtml += `
            <div class="view-drill-card">
                <div class="view-drill-header">
                    <div style="display: flex; align-items: center;">
                        <span class="view-drill-number">${drillNumber}</span>
                        <div class="view-drill-title-section">
                            <h4 class="view-drill-title">${escapeHtml(drill.title || 'Untitled Drill')}</h4>
                            <div class="view-drill-category">${escapeHtml(categoryName)}</div>
                        </div>
                    </div>
                    <span class="view-drill-duration"><i class="fas fa-clock"></i> ${duration} min</span>
                </div>
                <div class="view-drill-body">
                    <!-- Large Drill Diagram -->
                    <div class="view-drill-diagram" id="drill-diagram-${drill.drill_id}">
                        ${hasCustomImage 
                            ? `<img src="${escapeHtml(drill.custom_image)}" alt="${escapeHtml(drill.title)} Diagram" onerror="handleImageError(this)">`
                            : hasDiagramData 
                                ? `<canvas id="drill-canvas-${drill.drill_id}" class="drill-view-canvas"></canvas>`
                                : `<div class="no-diagram-placeholder"><i class="fas fa-hockey-puck"></i><span>No diagram available</span></div>`
                        }
                    </div>
                    
                    <!-- Drill Details -->
                    <div class="view-drill-details">
                        <div class="drill-detail-section">
                            <h5>Description</h5>
                            <p>${escapeHtml(description)}</p>
                        </div>
                        ${drill.setup ? `<div class="drill-detail-section"><h5><i class="fas fa-cog"></i> Setup</h5><p>${escapeHtml(drill.setup)}</p></div>` : ''}
                        ${drill.coaching_points ? `<div class="drill-detail-section"><h5><i class="fas fa-bullseye"></i> Coaching Points</h5><p>${escapeHtml(drill.coaching_points)}</p></div>` : ''}
                        ${drill.progression ? `<div class="drill-detail-section"><h5><i class="fas fa-level-up-alt"></i> Progression</h5><p>${escapeHtml(drill.progression)}</p></div>` : ''}
                        ${drill.notes ? `<div class="drill-detail-section"><h5><i class="fas fa-sticky-note"></i> Notes</h5><p>${escapeHtml(drill.notes)}</p></div>` : ''}
                        <div class="view-drill-link">
                            <a href="?page=view_drill&id=${drill.drill_id}" class="btn-view-full">
                                <i class="fas fa-external-link-alt"></i> View Full Drill Details
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Store canvas data for rendering
        if (!hasCustomImage && hasDiagramData) {
            viewPlanDrillCanvases.push({
                drillId: drill.drill_id,
                diagramData: drill.diagram_data
            });
        }
    });
    
    document.getElementById('viewPlanDrillsList').innerHTML = drillsHtml || '<p style="color: #64748b; text-align: center; padding: 40px;">No drills in this practice plan.</p>';
    
    // Hide loading, show content
    document.getElementById('viewPlanLoading').style.display = 'none';
    document.getElementById('viewPlanContent').style.display = 'block';
    
    // Render drill canvases after DOM is updated
    setTimeout(() => {
        viewPlanDrillCanvases.forEach(canvasData => {
            renderDrillCanvas(canvasData.drillId, canvasData.diagramData);
        });
    }, 100);
}

function renderDrillCanvas(drillId, diagramDataStr) {
    const canvas = document.getElementById('drill-canvas-' + drillId);
    if (!canvas) return;
    
    const container = canvas.parentElement;
    canvas.width = container.offsetWidth || 600;
    canvas.height = 300;
    
    const ctx = canvas.getContext('2d');
    const w = canvas.width;
    const h = canvas.height;
    
    // Draw ice background
    ctx.fillStyle = '#f0f7fa';
    ctx.fillRect(0, 0, w, h);
    
    // Draw center branding (subtle)
    ctx.save();
    ctx.globalAlpha = 0.08;
    ctx.fillStyle = '#7000a4';
    ctx.font = 'bold 32px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText('ARCTIC WOLVES', w/2, h/2);
    ctx.restore();
    
    // Draw rink markings
    // Center line
    ctx.strokeStyle = '#c41e3a';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(w/2, 0);
    ctx.lineTo(w/2, h);
    ctx.stroke();
    
    // Blue lines
    ctx.strokeStyle = '#0033a0';
    ctx.lineWidth = 2;
    ctx.beginPath();
    ctx.moveTo(w * 0.25, 0);
    ctx.lineTo(w * 0.25, h);
    ctx.stroke();
    ctx.beginPath();
    ctx.moveTo(w * 0.75, 0);
    ctx.lineTo(w * 0.75, h);
    ctx.stroke();
    
    // Center circle
    ctx.beginPath();
    ctx.arc(w/2, h/2, Math.min(w, h) * 0.12, 0, 2 * Math.PI);
    ctx.stroke();
    
    // Center dot
    ctx.fillStyle = '#0033a0';
    ctx.beginPath();
    ctx.arc(w/2, h/2, 4, 0, 2 * Math.PI);
    ctx.fill();
    
    // Draw diagram objects
    try {
        const objects = JSON.parse(diagramDataStr);
        if (Array.isArray(objects)) {
            // Scale factor for display - original drill designer canvas is 800x400
            const DRILL_DESIGNER_WIDTH = 800;
            const DRILL_DESIGNER_HEIGHT = 400;
            // Use uniform scaling to preserve object proportions
            const scaleX = w / DRILL_DESIGNER_WIDTH;
            const scaleY = h / DRILL_DESIGNER_HEIGHT;
            const uniformScale = Math.min(scaleX, scaleY);
            
            // Calculate offset to center content
            const offsetX = (w - DRILL_DESIGNER_WIDTH * uniformScale) / 2;
            const offsetY = (h - DRILL_DESIGNER_HEIGHT * uniformScale) / 2;
            
            objects.forEach(obj => {
                drawDrillObject(ctx, obj, uniformScale, offsetX, offsetY);
            });
        }
    } catch (e) {
        console.log('Could not parse diagram data for drill ' + drillId);
    }
}

function drawDrillObject(ctx, obj, scale, offsetX, offsetY) {
    ctx.save();
    
    const x = (obj.x || 0) * scale + offsetX;
    const y = (obj.y || 0) * scale + offsetY;
    const x1 = (obj.x1 || 0) * scale + offsetX;
    const y1 = (obj.y1 || 0) * scale + offsetY;
    const x2 = (obj.x2 || 0) * scale + offsetX;
    const y2 = (obj.y2 || 0) * scale + offsetY;
    
    if (obj.type === 'player') {
        ctx.translate(x, y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.fillStyle = obj.color || '#00bfff';
        ctx.beginPath();
        ctx.arc(0, 0, 12, 0, 2 * Math.PI);
        ctx.fill();
        ctx.strokeStyle = '#fff';
        ctx.lineWidth = 2;
        ctx.stroke();
        if (obj.label) {
            ctx.fillStyle = '#fff';
            ctx.font = 'bold 9px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(obj.label, 0, 0);
        }
    } else if (obj.type === 'cone') {
        ctx.translate(x, y);
        ctx.fillStyle = obj.color || '#ff6b00';
        ctx.beginPath();
        ctx.moveTo(0, -12);
        ctx.lineTo(-8, 8);
        ctx.lineTo(8, 8);
        ctx.closePath();
        ctx.fill();
    } else if (obj.type === 'puck') {
        ctx.fillStyle = obj.color || '#000';
        ctx.beginPath();
        ctx.arc(x, y, 6, 0, 2 * Math.PI);
        ctx.fill();
    } else if (obj.type === 'line') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
    } else if (obj.type === 'dashed') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.lineWidth = 2;
        ctx.setLineDash([6, 4]);
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
    } else if (obj.type === 'arrow') {
        ctx.strokeStyle = obj.color || '#333';
        ctx.fillStyle = obj.color || '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(x1, y1);
        ctx.lineTo(x2, y2);
        ctx.stroke();
        const angle = Math.atan2(y2 - y1, x2 - x1);
        ctx.beginPath();
        ctx.moveTo(x2, y2);
        ctx.lineTo(x2 - 12 * Math.cos(angle - Math.PI/6), y2 - 12 * Math.sin(angle - Math.PI/6));
        ctx.lineTo(x2 - 12 * Math.cos(angle + Math.PI/6), y2 - 12 * Math.sin(angle + Math.PI/6));
        ctx.closePath();
        ctx.fill();
    } else if (obj.type === 'net') {
        ctx.translate(x, y);
        ctx.rotate((obj.rotation || 0) * Math.PI / 180);
        ctx.strokeStyle = obj.color || '#c41e3a';
        ctx.lineWidth = 2;
        ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
        ctx.beginPath();
        ctx.moveTo(-16, -12);
        ctx.lineTo(-20, 12);
        ctx.lineTo(20, 12);
        ctx.lineTo(16, -12);
        ctx.closePath();
        ctx.fill();
        ctx.stroke();
    } else if (obj.type === 'text') {
        ctx.translate(x, y);
        ctx.fillStyle = obj.color || '#000';
        ctx.font = 'bold 12px Inter, sans-serif';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(obj.text || '', 0, 0);
    }
    
    ctx.restore();
}

function calculateTotalDuration(drills) {
    return drills.reduce((total, drill) => total + (parseInt(drill.duration_minutes) || 10), 0);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function handleImageError(imgElement) {
    // Safe DOM manipulation to replace failed image with placeholder
    const container = imgElement.parentElement;
    container.textContent = ''; // Clear the container safely
    
    const placeholder = document.createElement('div');
    placeholder.className = 'no-diagram-placeholder';
    
    const icon = document.createElement('i');
    icon.className = 'fas fa-image';
    icon.setAttribute('aria-hidden', 'true');
    
    const text = document.createElement('span');
    text.textContent = 'Image failed to load';
    
    placeholder.appendChild(icon);
    placeholder.appendChild(text);
    container.appendChild(placeholder);
}

function closeViewPlanModal() {
    document.getElementById('viewPlanModal').classList.remove('active');
    currentViewPlanId = null;
    viewPlanDrillCanvases = [];
}

function copyViewPlanShareLink() {
    const input = document.getElementById('viewPlanShareUrl');
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

function editPlan(id) {
    // In a real implementation, fetch plan data via AJAX and populate form
    showNotification('Edit functionality requires AJAX implementation to load existing plan data', 'info');
}

function applyFilters() {
    const ageGroup = document.getElementById('ageGroupFilter').value;
    const focus = document.getElementById('focusFilter').value;
    
    let url = 'dashboard.php?page=practice_library';
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

// Render drill thumbnails in the practice plan modal using shared IceCanvasRenderer
function renderModalDrillThumbnails() {
    const previews = document.querySelectorAll('#availableDrills .drill-diagram-preview');
    
    previews.forEach(preview => {
        const canvas = preview.querySelector('.drill-thumbnail-canvas');
        if (!canvas) return;
        
        // Skip if already rendered
        if (canvas.dataset.rendered === 'true') return;
        
        // Get diagram data
        let diagramData = [];
        let sourceWidth = 800;
        let sourceHeight = 400;
        let iceView = 'full';
        
        // First, check if parent .drill-image has data-ice-view attribute (set by PHP)
        const drillImageParent = preview.closest('.drill-image');
        if (drillImageParent && drillImageParent.dataset.iceView) {
            iceView = drillImageParent.dataset.iceView;
        }
        
        try {
            const dataStr = preview.getAttribute('data-diagram') || '[]';
            const parsed = JSON.parse(dataStr);
            
            if (Array.isArray(parsed)) {
                diagramData = parsed;
            } else if (parsed && parsed.objects && Array.isArray(parsed.objects)) {
                diagramData = parsed.objects;
                sourceWidth = parsed.canvasWidth || 800;
                sourceHeight = parsed.canvasHeight || 400;
                // Get saved ice view (overrides parent attribute if present)
                if (parsed.iceView) {
                    iceView = parsed.iceView;
                }
            }
        } catch (e) {
            diagramData = [];
        }
        
        const centerLogoUrl = preview.getAttribute('data-center-logo') || '';
        
        // Set canvas size
        canvas.width = preview.offsetWidth || 280;
        canvas.height = preview.offsetHeight || 120;
        
        const ctx = canvas.getContext('2d');
        const w = canvas.width;
        const h = canvas.height;
        
        function renderThumbnail(logoImage, logoLoaded) {
            // Use the shared IceCanvasRenderer for consistent rink drawing
            if (window.IceCanvasRenderer) {
                IceCanvasRenderer.drawRink(ctx, w, h, iceView, {
                    logoImage: logoImage,
                    logoLoaded: logoLoaded,
                    lineScale: 1
                });
            } else {
                console.warn('IceCanvasRenderer not loaded - using basic fallback for practice plan thumbnail');
                ctx.fillStyle = '#f0f7fa';
                ctx.fillRect(0, 0, w, h);
                ctx.strokeStyle = '#0033a0';
                ctx.lineWidth = 2;
                ctx.strokeRect(2, 2, w - 4, h - 4);
            }
            
            // Draw diagram objects if available
            if (diagramData && diagramData.length > 0) {
                const scaleX = w / sourceWidth;
                const scaleY = h / sourceHeight;
                const uniformScale = Math.min(scaleX, scaleY);
                const offsetX = (w - sourceWidth * uniformScale) / 2;
                const offsetY = (h - sourceHeight * uniformScale) / 2;
                
                diagramData.forEach(obj => {
                    const x = (obj.x || 0) * uniformScale + offsetX;
                    const y = (obj.y || 0) * uniformScale + offsetY;
                    
                    if (obj.type === 'player') {
                        ctx.fillStyle = obj.color || '#00bfff';
                        ctx.beginPath();
                        ctx.arc(x, y, 8, 0, 2 * Math.PI);
                        ctx.fill();
                        ctx.strokeStyle = '#fff';
                        ctx.lineWidth = 1;
                        ctx.stroke();
                        if (obj.label) {
                            ctx.fillStyle = '#fff';
                            ctx.font = 'bold 6px Inter, sans-serif';
                            ctx.textAlign = 'center';
                            ctx.textBaseline = 'middle';
                            ctx.fillText(obj.label, x, y);
                        }
                    } else if (obj.type === 'cone') {
                        ctx.fillStyle = obj.color || '#ff6b00';
                        ctx.beginPath();
                        ctx.moveTo(x, y - 8);
                        ctx.lineTo(x - 5, y + 5);
                        ctx.lineTo(x + 5, y + 5);
                        ctx.closePath();
                        ctx.fill();
                    } else if (obj.type === 'puck') {
                        ctx.fillStyle = '#000';
                        ctx.beginPath();
                        ctx.arc(x, y, 4, 0, 2 * Math.PI);
                        ctx.fill();
                    }
                    // Note: Simplified object rendering for thumbnails
                });
            }
        }
        
        // Load center logo if provided, then render
        if (centerLogoUrl) {
            const logoImage = new Image();
            logoImage.crossOrigin = 'anonymous';
            logoImage.onload = function() {
                renderThumbnail(logoImage, true);
                canvas.dataset.rendered = 'true';
            };
            logoImage.onerror = function() {
                renderThumbnail(null, false);
                canvas.dataset.rendered = 'true';
            };
            logoImage.src = centerLogoUrl;
        } else {
            renderThumbnail(null, false);
            canvas.dataset.rendered = 'true';
        }
    });
}

// Add drill from card (new function for drill cards with preview)
function addDrillFromCard(button) {
    const card = button.closest('.modal-drill-card');
    if (!card) return;
    
    const drillId = card.dataset.drillId;
    const drillTitle = card.dataset.drillTitle;
    const drillDuration = parseInt(card.dataset.drillDuration) || 10;
    
    // Check if already added
    if (selectedDrills.find(d => d.id === drillId)) {
        showNotification('This drill is already in your plan', 'info');
        return;
    }
    
    selectedDrills.push({ id: drillId, title: drillTitle, duration: drillDuration });
    updateSelectedDrillsDisplay();
    
    // Mark card as added
    card.classList.add('added');
    
    showNotification('Drill added to plan', 'success');
}

// Filter drills in modal
function filterModalDrills() {
    const searchText = document.getElementById('drillSearchInput').value.toLowerCase().trim();
    const cards = document.querySelectorAll('#availableDrills .modal-drill-card');
    
    cards.forEach(card => {
        const title = card.dataset.title || '';
        if (searchText === '' || title.includes(searchText)) {
            card.classList.remove('hidden');
        } else {
            card.classList.add('hidden');
        }
    });
}
</script>
