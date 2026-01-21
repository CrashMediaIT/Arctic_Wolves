<?php
/**
 * Goals and Progress Tracking System
 * Allows coaches and athletes to create, track, and manage goals with steps and progress updates
 */

require_once __DIR__ . '/../security.php';

// Get user info from session
$current_user_id = $user_id;
$current_user_role = $user_role;

// Determine which athlete's goals to view
$viewing_athlete_id = $current_user_id;

// Coaches can switch between athletes
if ($isCoach && isset($_GET['athlete_id'])) {
    $viewing_athlete_id = intval($_GET['athlete_id']);
}

// Get athlete list for coaches
$athletes = [];
if ($isCoach) {
    $athletes_query = "
        SELECT u.id, u.first_name, u.last_name, u.email
        FROM users u
        WHERE u.role = 'athlete' AND u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ";
    $athletes = $pdo->query($athletes_query)->fetchAll();
}

// Get athlete info
$athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
$athlete_stmt->execute([$viewing_athlete_id]);
$athlete_info = $athlete_stmt->fetch();

// Get filter parameters
$filter_status = $_GET['status'] ?? 'active';
$filter_category = $_GET['category'] ?? '';
$filter_tag = $_GET['tag'] ?? '';

// Build query for goals
$goals_query = "
    SELECT g.*,
           CONCAT(u.first_name, ' ', u.last_name) as creator_name,
           (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id) as total_steps,
           (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id AND is_completed = 1) as completed_steps
    FROM goals g
    LEFT JOIN users u ON g.created_by = u.id
    WHERE g.athlete_id = ?
";

$params = [$viewing_athlete_id];

// Apply status filter
if ($filter_status === 'active') {
    $goals_query .= " AND g.status = 'active'";
} elseif ($filter_status === 'completed') {
    $goals_query .= " AND g.status = 'completed'";
} elseif ($filter_status === 'archived') {
    $goals_query .= " AND g.status = 'archived'";
}

// Apply category filter
if (!empty($filter_category)) {
    $goals_query .= " AND g.category = ?";
    $params[] = $filter_category;
}

// Apply tag filter
if (!empty($filter_tag)) {
    $goals_query .= " AND (g.tags LIKE ? OR g.tags LIKE ? OR g.tags LIKE ? OR g.tags = ?)";
    $params[] = $filter_tag . ',%';
    $params[] = '%,' . $filter_tag . ',%';
    $params[] = '%,' . $filter_tag;
    $params[] = $filter_tag;
}

$goals_query .= " ORDER BY g.created_at DESC";

$goals_stmt = $pdo->prepare($goals_query);
$goals_stmt->execute($params);
$goals = $goals_stmt->fetchAll();

// Get all categories for filter
$categories = $pdo->query("SELECT DISTINCT category FROM goals WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Get all tags for filter
$tags_result = $pdo->query("SELECT DISTINCT tags FROM goals WHERE tags IS NOT NULL AND tags != ''")->fetchAll(PDO::FETCH_COLUMN);
$all_tags = [];
foreach ($tags_result as $tag_string) {
    $tags = array_map('trim', explode(',', $tag_string));
    $all_tags = array_merge($all_tags, $tags);
}
$all_tags = array_unique($all_tags);
sort($all_tags);
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .goals-container {
        padding: 20px;
        max-width: 1400px;
        margin: 0 auto;
    }
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
    .header-actions {
        display: flex;
        gap: 12px;
        align-items: center;
    }
    .athlete-selector {
        padding: 10px 16px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .athlete-selector:hover {
        border-color: var(--primary);
    }
    .btn-create-goal {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-create-goal:hover {
        background: #5a008a;
        transform: translateY(-2px);
    }
    .filters-bar {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    .filter-label {
        font-size: 12px;
        color: #94a3b8;
        text-transform: uppercase;
        font-weight: 700;
    }
    .filter-select {
        padding: 8px 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 4px;
        color: #fff;
        font-size: 14px;
        min-width: 150px;
    }
    .goals-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .goal-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        transition: all 0.2s;
        position: relative;
        overflow: hidden;
    }
    .goal-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(112, 0, 164, 0.2);
    }
    .goal-card.completed {
        opacity: 0.8;
        border-color: #10b981;
    }
    .goal-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }
    .goal-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin: 0 0 8px 0;
    }
    .goal-category {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(112, 0, 164, 0.2);
        border: 1px solid var(--primary);
        border-radius: 4px;
        font-size: 11px;
        color: var(--primary);
        font-weight: 700;
        text-transform: uppercase;
        margin-bottom: 8px;
    }
    .goal-description {
        color: #94a3b8;
        font-size: 14px;
        line-height: 1.6;
        margin-bottom: 15px;
    }
    .goal-tags {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        margin-bottom: 15px;
    }
    .goal-tag {
        padding: 3px 8px;
        background: rgba(148, 163, 184, 0.1);
        border: 1px solid #1e293b;
        border-radius: 3px;
        font-size: 11px;
        color: #94a3b8;
    }
    .goal-progress {
        margin: 15px 0;
    }
    .progress-label {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
        font-size: 12px;
        color: #94a3b8;
    }
    .progress-bar-container {
        width: 100%;
        height: 8px;
        background: #1e293b;
        border-radius: 4px;
        overflow: hidden;
    }
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, var(--primary) 0%, #a855f7 100%);
        transition: width 0.3s ease;
    }
    .goal-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #1e293b;
        font-size: 12px;
        color: #64748b;
    }
    .goal-actions {
        display: flex;
        gap: 8px;
        margin-top: 15px;
    }
    .btn-goal-action {
        flex: 1;
        padding: 8px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        border: none;
        text-decoration: none;
        display: inline-block;
    }
    .btn-view {
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
    }
    .btn-view:hover {
        background: rgba(112, 0, 164, 0.1);
    }
    .btn-edit {
        background: var(--primary);
        color: #fff;
    }
    .btn-edit:hover {
        background: #5a008a;
    }
    .btn-complete {
        background: #10b981;
        color: #fff;
    }
    .btn-complete:hover {
        background: #059669;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #64748b;
    }
    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.3;
    }
    .empty-state h3 {
        font-size: 20px;
        margin-bottom: 10px;
    }
    
    /* Modal Styles */
    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.8);
        z-index: 10000;
        align-items: center;
        justify-content: center;
    }
    .modal.active {
        display: flex;
    }
    .modal-content {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
    }
    .modal-header {
        padding: 20px 24px;
        border-bottom: 1px solid #1e293b;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modal-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
    }
    .modal-close {
        background: transparent;
        border: none;
        color: #94a3b8;
        font-size: 24px;
        cursor: pointer;
        padding: 0;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        transition: all 0.2s;
    }
    .modal-close:hover {
        background: rgba(255, 255, 255, 0.1);
        color: #fff;
    }
    .modal-body {
        padding: 24px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-label {
        display: block;
        margin-bottom: 8px;
        font-size: 14px;
        font-weight: 600;
        color: #fff;
    }
    .form-input, .form-textarea, .form-select {
        width: 100%;
        padding: 10px 14px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 4px;
        color: #fff;
        font-size: 14px;
        font-family: inherit;
    }
    .form-input:focus, .form-textarea:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    .form-textarea {
        resize: vertical;
        min-height: 100px;
    }
    .steps-section {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #1e293b;
    }
    .steps-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    .steps-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
    }
    .btn-add-step {
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-add-step:hover {
        background: rgba(112, 0, 164, 0.1);
    }
    .steps-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    .step-item {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 4px;
        padding: 12px;
        display: flex;
        align-items: start;
        gap: 10px;
    }
    .step-handle {
        cursor: move;
        color: #64748b;
        padding: 4px;
    }
    .step-content {
        flex: 1;
    }
    .step-input {
        width: 100%;
        padding: 6px 10px;
        background: transparent;
        border: none;
        color: #fff;
        font-size: 14px;
    }
    .step-input:focus {
        outline: none;
    }
    .step-remove {
        background: transparent;
        border: none;
        color: #ef4444;
        cursor: pointer;
        padding: 4px 8px;
        font-size: 14px;
    }
    .modal-footer {
        padding: 20px 24px;
        border-top: 1px solid #1e293b;
        display: flex;
        justify-content: flex-end;
        gap: 12px;
    }
    .btn-cancel {
        background: transparent;
        border: 1px solid #1e293b;
        color: #94a3b8;
        padding: 10px 20px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
    }
    .btn-cancel:hover {
        border-color: #64748b;
        color: #fff;
    }
    .btn-submit {
        background: var(--primary);
        border: none;
        color: #fff;
        padding: 10px 24px;
        border-radius: 4px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-submit:hover {
        background: #5a008a;
    }
    
    /* Goal Detail Modal */
    .goal-detail-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 20px;
    }
    .goal-detail-info {
        flex: 1;
    }
    .goal-detail-actions {
        display: flex;
        gap: 8px;
    }
    .steps-progress {
        margin: 20px 0;
    }
    .step-detail-item {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 4px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        align-items: start;
        gap: 12px;
    }
    .step-detail-item.completed {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.05);
    }
    .step-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
        margin-top: 2px;
    }
    .step-detail-content {
        flex: 1;
    }
    .step-detail-title {
        font-size: 14px;
        font-weight: 600;
        color: #fff;
        margin-bottom: 4px;
    }
    .step-detail-description {
        font-size: 13px;
        color: #94a3b8;
    }
    .step-completed-info {
        font-size: 11px;
        color: #10b981;
        margin-top: 6px;
    }
    .progress-history {
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #1e293b;
    }
    .progress-history-title {
        font-size: 16px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 15px;
    }
    .progress-entry {
        background: #06080b;
        border-left: 3px solid var(--primary);
        padding: 12px 15px;
        margin-bottom: 12px;
        border-radius: 4px;
    }
    .progress-entry-header {
        display: flex;
        justify-content: space-between;
        margin-bottom: 6px;
    }
    .progress-entry-user {
        font-size: 13px;
        font-weight: 600;
        color: #fff;
    }
    .progress-entry-date {
        font-size: 12px;
        color: #64748b;
    }
    .progress-entry-note {
        font-size: 13px;
        color: #94a3b8;
        line-height: 1.5;
    }
    .btn-add-progress {
        background: var(--primary);
        border: none;
        color: #fff;
        padding: 10px 20px;
        border-radius: 4px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-add-progress:hover {
        background: #5a008a;
    }
    .status-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-active {
        background: rgba(59, 130, 246, 0.2);
        color: #3b82f6;
    }
    .status-completed {
        background: rgba(16, 185, 129, 0.2);
        color: #10b981;
    }
    .status-archived {
        background: rgba(100, 116, 139, 0.2);
        color: #64748b;
    }
</style>

<div class="goals-container">
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <i class="fas fa-bullseye"></i> Goals & Progress
            </h1>
            <?php if ($athlete_info): ?>
                <p style="color: #94a3b8; margin-top: 8px;">
                    <?php echo htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']); ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <?php if ($isCoach && count($athletes) > 0): ?>
                <select class="athlete-selector" onchange="window.location.href='?page=goals&athlete_id=' + this.value">
                    <option value="">Select Athlete</option>
                    <?php foreach ($athletes as $athlete): ?>
                        <option value="<?php echo $athlete['id']; ?>" 
                                <?php echo $viewing_athlete_id == $athlete['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($athlete['last_name'] . ', ' . $athlete['first_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <?php if ($isCoach): ?>
                <button class="btn-create-goal" onclick="openCreateGoalModal()">
                    <i class="fas fa-plus"></i> Create Goal
                </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="filters-bar">
        <div class="filter-group">
            <label class="filter-label">Status</label>
            <select class="filter-select" onchange="updateFilter('status', this.value)">
                <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
            </select>
        </div>
        <?php if (count($categories) > 0): ?>
            <div class="filter-group">
                <label class="filter-label">Category</label>
                <select class="filter-select" onchange="updateFilter('category', this.value)">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" 
                                <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($cat); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
        <?php if (count($all_tags) > 0): ?>
            <div class="filter-group">
                <label class="filter-label">Tag</label>
                <select class="filter-select" onchange="updateFilter('tag', this.value)">
                    <option value="">All Tags</option>
                    <?php foreach ($all_tags as $tag): ?>
                        <option value="<?php echo htmlspecialchars($tag); ?>" 
                                <?php echo $filter_tag === $tag ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($tag); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>

    <?php if (count($goals) > 0): ?>
        <div class="goals-grid">
            <?php foreach ($goals as $goal): 
                $progress_pct = $goal['completion_percentage'] ?? 0;
                $is_completed = $goal['status'] === 'completed';
            ?>
                <div class="goal-card <?php echo $is_completed ? 'completed' : ''; ?>">
                    <?php if ($goal['category']): ?>
                        <span class="goal-category"><?php echo htmlspecialchars($goal['category']); ?></span>
                    <?php endif; ?>
                    
                    <h3 class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></h3>
                    
                    <?php if ($goal['description']): ?>
                        <p class="goal-description"><?php echo nl2br(htmlspecialchars($goal['description'])); ?></p>
                    <?php endif; ?>
                    
                    <?php if ($goal['tags']): ?>
                        <div class="goal-tags">
                            <?php foreach (explode(',', $goal['tags']) as $tag): ?>
                                <span class="goal-tag"><?php echo htmlspecialchars(trim($tag)); ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="goal-progress">
                        <div class="progress-label">
                            <span>Progress</span>
                            <span><strong><?php echo round($progress_pct); ?>%</strong></span>
                        </div>
                        <div class="progress-bar-container">
                            <div class="progress-bar" style="width: <?php echo $progress_pct; ?>%"></div>
                        </div>
                    </div>
                    
                    <div class="goal-meta">
                        <span>
                            <i class="fas fa-list-check"></i> 
                            <?php echo $goal['completed_steps']; ?> / <?php echo $goal['total_steps']; ?> steps
                        </span>
                        <?php if ($goal['target_date']): ?>
                            <span>
                                <i class="fas fa-calendar"></i> 
                                <?php echo date('M d, Y', strtotime($goal['target_date'])); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="goal-actions">
                        <button class="btn-goal-action btn-view" onclick="viewGoalDetail(<?php echo $goal['id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php if ($isCoach): ?>
                            <button class="btn-goal-action btn-edit" onclick="editGoal(<?php echo $goal['id']; ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <?php if (!$is_completed): ?>
                                <button class="btn-goal-action btn-complete" onclick="completeGoal(<?php echo $goal['id']; ?>)">
                                    <i class="fas fa-check"></i> Complete
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-bullseye"></i>
            <h3>No Goals Found</h3>
            <p>
                <?php if ($isCoach): ?>
                    Create a goal to start tracking progress
                <?php else: ?>
                    Your coach will create goals for you to work towards
                <?php endif; ?>
            </p>
        </div>
    <?php endif; ?>
</div>

<!-- Create/Edit Goal Modal -->
<div id="goalModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Create Goal</h2>
            <button class="modal-close" onclick="closeGoalModal()">&times;</button>
        </div>
        <form id="goalForm" method="POST" action="process_goals.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" id="formAction" value="create_goal">
            <input type="hidden" name="goal_id" id="goalId" value="">
            <input type="hidden" name="athlete_id" value="<?php echo $viewing_athlete_id; ?>">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title *</label>
                    <input type="text" name="title" id="goalTitle" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="goalDescription" class="form-textarea"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Category</label>
                    <input type="text" name="category" id="goalCategory" class="form-input" 
                           placeholder="e.g., Skating, Shooting, Fitness">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tags (comma-separated)</label>
                    <input type="text" name="tags" id="goalTags" class="form-input" 
                           placeholder="e.g., speed, power, technique">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Target Date</label>
                    <input type="date" name="target_date" id="goalTargetDate" class="form-input">
                </div>
                
                <div class="steps-section">
                    <div class="steps-header">
                        <h3 class="steps-title">Steps</h3>
                        <button type="button" class="btn-add-step" onclick="addStep()">
                            <i class="fas fa-plus"></i> Add Step
                        </button>
                    </div>
                    <div class="steps-list" id="stepsList">
                        <!-- Steps will be added dynamically -->
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeGoalModal()">Cancel</button>
                <button type="submit" class="btn-submit">Save Goal</button>
            </div>
        </form>
    </div>
</div>

<!-- Goal Detail Modal -->
<div id="goalDetailModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Goal Details</h2>
            <button class="modal-close" onclick="closeGoalDetailModal()">&times;</button>
        </div>
        <div class="modal-body" id="goalDetailContent">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<!-- Progress Note Modal -->
<div id="progressNoteModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Add Progress Note</h2>
            <button class="modal-close" onclick="closeProgressNoteModal()">&times;</button>
        </div>
        <form id="progressNoteForm" method="POST" action="process_goals.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_progress">
            <input type="hidden" name="goal_id" id="progressGoalId" value="">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Progress Note *</label>
                    <textarea name="progress_note" class="form-textarea" required 
                              placeholder="Describe your progress..."></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Progress Percentage</label>
                    <input type="number" name="progress_percentage" class="form-input" 
                           min="0" max="100" placeholder="Optional override">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeProgressNoteModal()">Cancel</button>
                <button type="submit" class="btn-submit">Save Progress</button>
            </div>
        </form>
    </div>
</div>

<script>
let stepCounter = 0;

function updateFilter(type, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(type, value);
    window.location.href = url.toString();
}

function openCreateGoalModal() {
    document.getElementById('modalTitle').textContent = 'Create Goal';
    document.getElementById('formAction').value = 'create_goal';
    document.getElementById('goalId').value = '';
    document.getElementById('goalForm').reset();
    document.getElementById('stepsList').innerHTML = '';
    stepCounter = 0;
    document.getElementById('goalModal').classList.add('active');
}

function closeGoalModal() {
    document.getElementById('goalModal').classList.remove('active');
}

function addStep() {
    stepCounter++;
    const stepHtml = `
        <div class="step-item" data-step-id="${stepCounter}">
            <span class="step-handle"><i class="fas fa-grip-vertical"></i></span>
            <div class="step-content">
                <input type="text" name="steps[${stepCounter}][title]" class="step-input" 
                       placeholder="Step title" required>
                <input type="hidden" name="steps[${stepCounter}][order]" value="${stepCounter}">
            </div>
            <button type="button" class="step-remove" onclick="removeStep(${stepCounter})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('stepsList').insertAdjacentHTML('beforeend', stepHtml);
}

function removeStep(id) {
    const step = document.querySelector(`[data-step-id="${id}"]`);
    if (step) step.remove();
}

function editGoal(goalId) {
    // Fetch goal data and populate modal
    fetch(`process_goals.php?action=get_goal&goal_id=${goalId}`)
        .then(response => response.json())
        .then(data => {
            document.getElementById('modalTitle').textContent = 'Edit Goal';
            document.getElementById('formAction').value = 'update_goal';
            document.getElementById('goalId').value = goalId;
            document.getElementById('goalTitle').value = data.title;
            document.getElementById('goalDescription').value = data.description || '';
            document.getElementById('goalCategory').value = data.category || '';
            document.getElementById('goalTags').value = data.tags || '';
            document.getElementById('goalTargetDate').value = data.target_date || '';
            
            // Load steps
            document.getElementById('stepsList').innerHTML = '';
            stepCounter = 0;
            data.steps.forEach(step => {
                stepCounter++;
                const stepHtml = `
                    <div class="step-item" data-step-id="${stepCounter}">
                        <span class="step-handle"><i class="fas fa-grip-vertical"></i></span>
                        <div class="step-content">
                            <input type="text" name="steps[${stepCounter}][title]" class="step-input" 
                                   value="${escapeHtml(step.title)}" required>
                            <input type="hidden" name="steps[${stepCounter}][id]" value="${step.id}">
                            <input type="hidden" name="steps[${stepCounter}][order]" value="${stepCounter}">
                        </div>
                        <button type="button" class="step-remove" onclick="removeStep(${stepCounter})">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                `;
                document.getElementById('stepsList').insertAdjacentHTML('beforeend', stepHtml);
            });
            
            document.getElementById('goalModal').classList.add('active');
        });
}

function viewGoalDetail(goalId) {
    document.getElementById('goalDetailContent').innerHTML = '<p style="text-align:center;padding:20px;">Loading...</p>';
    document.getElementById('goalDetailModal').classList.add('active');
    
    fetch(`process_goals.php?action=get_goal_detail&goal_id=${goalId}`)
        .then(response => response.json())
        .then(data => {
            renderGoalDetail(data);
        });
}

function renderGoalDetail(data) {
    const isCoach = <?php echo $isCoach ? 'true' : 'false'; ?>;
    let html = `
        <div class="goal-detail-header">
            <div class="goal-detail-info">
                ${data.category ? `<span class="goal-category">${escapeHtml(data.category)}</span>` : ''}
                <h3 class="goal-title">${escapeHtml(data.title)}</h3>
                ${data.description ? `<p class="goal-description">${escapeHtml(data.description)}</p>` : ''}
                ${data.tags ? `
                    <div class="goal-tags">
                        ${data.tags.split(',').map(tag => `<span class="goal-tag">${escapeHtml(tag.trim())}</span>`).join('')}
                    </div>
                ` : ''}
            </div>
            <div class="goal-detail-actions">
                <span class="status-badge status-${data.status}">${data.status}</span>
            </div>
        </div>
        
        <div class="goal-progress">
            <div class="progress-label">
                <span>Overall Progress</span>
                <span><strong>${Math.round(data.completion_percentage)}%</strong></span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: ${data.completion_percentage}%"></div>
            </div>
        </div>
        
        <div class="steps-progress">
            <h4 class="steps-title">Steps</h4>
            ${data.steps.map(step => `
                <div class="step-detail-item ${step.is_completed ? 'completed' : ''}">
                    ${isCoach ? `
                        <input type="checkbox" class="step-checkbox" 
                               ${step.is_completed ? 'checked' : ''} 
                               onchange="toggleStep(${step.id}, ${data.id}, this.checked)">
                    ` : `
                        <i class="fas ${step.is_completed ? 'fa-check-circle' : 'fa-circle'}" 
                           style="color: ${step.is_completed ? '#10b981' : '#64748b'}; margin-top: 2px;"></i>
                    `}
                    <div class="step-detail-content">
                        <div class="step-detail-title">${escapeHtml(step.title)}</div>
                        ${step.description ? `<div class="step-detail-description">${escapeHtml(step.description)}</div>` : ''}
                        ${step.is_completed ? `
                            <div class="step-completed-info">
                                <i class="fas fa-check"></i> Completed ${formatDate(step.completed_at)}
                            </div>
                        ` : ''}
                    </div>
                </div>
            `).join('')}
        </div>
        
        ${isCoach ? `
            <div style="margin-top: 20px;">
                <button class="btn-add-progress" onclick="openProgressNoteModal(${data.id})">
                    <i class="fas fa-plus"></i> Add Progress Note
                </button>
            </div>
        ` : ''}
        
        ${data.progress.length > 0 ? `
            <div class="progress-history">
                <h4 class="progress-history-title">Progress History</h4>
                ${data.progress.map(entry => `
                    <div class="progress-entry">
                        <div class="progress-entry-header">
                            <span class="progress-entry-user">${escapeHtml(entry.user_name)}</span>
                            <span class="progress-entry-date">${formatDate(entry.created_at)}</span>
                        </div>
                        <div class="progress-entry-note">${escapeHtml(entry.progress_note)}</div>
                    </div>
                `).join('')}
            </div>
        ` : ''}
    `;
    
    document.getElementById('goalDetailContent').innerHTML = html;
}

function closeGoalDetailModal() {
    document.getElementById('goalDetailModal').classList.remove('active');
}

function openProgressNoteModal(goalId) {
    document.getElementById('progressGoalId').value = goalId;
    document.getElementById('progressNoteModal').classList.add('active');
}

function closeProgressNoteModal() {
    document.getElementById('progressNoteModal').classList.remove('active');
}

function toggleStep(stepId, goalId, isCompleted) {
    const formData = new FormData();
    formData.append('action', 'complete_step');
    formData.append('step_id', stepId);
    formData.append('goal_id', goalId);
    formData.append('is_completed', isCompleted ? '1' : '0');
    formData.append('csrf_token', '<?php echo generateCsrfToken(); ?>');
    
    fetch('process_goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            viewGoalDetail(goalId); // Refresh detail view
            setTimeout(() => location.reload(), 500); // Refresh main view
        }
    });
}

function completeGoal(goalId) {
    if (!confirm('Mark this goal as completed?')) return;
    
    const formData = new FormData();
    formData.append('action', 'complete_goal');
    formData.append('goal_id', goalId);
    formData.append('csrf_token', '<?php echo generateCsrfToken(); ?>');
    
    fetch('process_goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    });
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>
