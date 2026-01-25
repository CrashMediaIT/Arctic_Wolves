<!-- Practice Library View -->
<?php
// Fetch practice plans from database
try {
    // Get filter parameters
    $search = $_GET['search'] ?? '';
    $filter_team = $_GET['team'] ?? 'all';
    
    // Get teams for filter
    $teams_query = "SELECT id, name FROM teams WHERE is_active = 1 ORDER BY name";
    $teams_stmt = $pdo->query($teams_query);
    $teams = $teams_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Build practice plans query
    $plans_query = "
        SELECT pp.*, 
               CONCAT(u.first_name, ' ', u.last_name) as creator_name,
               (SELECT COUNT(*) FROM practice_plan_drills WHERE practice_plan_id = pp.id) as drill_count
        FROM practice_plans pp
        LEFT JOIN users u ON pp.created_by = u.id
        WHERE 1=1
    ";
    $params = [];
    
    // Apply search filter
    if (!empty($search)) {
        $plans_query .= " AND (pp.name LIKE ? OR pp.description LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $plans_query .= " ORDER BY pp.created_at DESC LIMIT 50";
    
    $plans_stmt = $pdo->prepare($plans_query);
    $plans_stmt->execute($params);
    $practice_plans = $plans_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Practice plans fetch error: " . $e->getMessage());
    $teams = [];
    $practice_plans = [];
}
?>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-clipboard-list"></i> Practice Plans
    </h1>
    <p class="page-description">Browse and manage your practice plans</p>
</div>

<div class="practice-content">
    <!-- Actions Bar -->
    <div class="action-bar">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="page" value="practice_library">
            <input type="text" name="search" class="form-input-small" placeholder="Search practice plans..." value="<?= htmlspecialchars($search) ?>">
            <select name="team" class="form-input-small">
                <option value="all">All Teams</option>
                <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['id'] ?>" <?= $filter_team == $team['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($team['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <button class="btn-primary" data-action="view" data-page="create_practice"><i class="fas fa-plus"></i> Create Practice Plan</button>
    </div>

    <!-- Practice Plans List -->
    <div class="practice-list">
        <?php if (count($practice_plans) > 0): ?>
            <?php foreach ($practice_plans as $plan): ?>
            <div class="practice-card" data-plan-id="<?= $plan['id'] ?>">
                <div class="practice-header">
                    <div class="practice-title-section">
                        <h3 class="practice-title"><?= htmlspecialchars($plan['name']) ?></h3>
                        <div class="practice-meta">
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($plan['creator_name'] ?? 'Unknown') ?></span>
                            <span><i class="fas fa-list"></i> <?= $plan['drill_count'] ?> drills</span>
                            <span><i class="fas fa-clock"></i> <?= date('M d, Y', strtotime($plan['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php if (!empty($plan['description'])): ?>
                <div class="practice-body">
                    <p><?= htmlspecialchars(substr($plan['description'], 0, 200)) ?><?= strlen($plan['description']) > 200 ? '...' : '' ?></p>
                </div>
                <?php endif; ?>
                <div class="practice-actions">
                    <button class="btn-secondary btn-sm" data-action="view-plan" data-plan-id="<?= $plan['id'] ?>">
                        <i class="fas fa-eye"></i> View
                    </button>
                    <button class="btn-secondary btn-sm" data-action="edit-plan" data-plan-id="<?= $plan['id'] ?>">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn-danger btn-sm" data-action="delete-plan" data-plan-id="<?= $plan['id'] ?>">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-clipboard-list placeholder-icon"></i>
                <p class="placeholder-text">No practice plans found. Create your first practice plan to get started!</p>
                <button class="btn btn-primary" style="margin-top: 20px;" data-action="view" data-page="create_practice">
                    <i class="fas fa-plus"></i> Create Practice Plan
                </button>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.practice-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.practice-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s;
}

.practice-card:hover {
    border-color: var(--neon);
    box-shadow: 0 4px 20px rgba(255, 77, 0, 0.1);
}

.practice-header {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
    background: var(--bg-main);
    border-bottom: 1px solid var(--border);
}

.practice-date {
    flex-shrink: 0;
}

.date-box.completed {
    background: #10b981;
}

.practice-title-section {
    flex: 1;
}

.practice-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.practice-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.practice-meta span {
    font-size: 14px;
    color: var(--text-dim);
}

.practice-meta i {
    color: var(--neon);
    margin-right: 5px;
}

.practice-status {
    flex-shrink: 0;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-badge.upcoming {
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.draft {
    background: rgba(148, 163, 184, 0.1);
    color: var(--text-dim);
}

.practice-body {
    padding: 24px;
}

.practice-drills h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 12px;
}

.practice-drills h4 i {
    color: var(--neon);
    margin-right: 8px;
}

.drill-list-compact {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.drill-item-compact {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 10px 15px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 4px;
}

.drill-time {
    font-size: 12px;
    font-weight: 700;
    color: var(--neon);
    min-width: 50px;
}

.drill-name {
    font-size: 14px;
    color: var(--text-white);
}

.practice-actions {
    padding: 20px 25px;
    background: var(--bg-main);
    border-top: 1px solid var(--border);
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

/* Placeholder/Empty State Styles */
.placeholder-container {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 60px 24px;
    text-align: center;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.5;
    display: block;
    margin-bottom: 20px;
}

.placeholder-text {
    font-size: 16px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 24px;
}
</style>
