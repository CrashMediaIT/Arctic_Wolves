<?php
/**
 * PWA Goals - Mobile-native goals tracker with full CRUD
 * Purpose-built for mobile phones.
 */

// Coach athlete selector
$goalsUserId = $user_id;
$athletes = [];
if ($isAnyCoach) {
    if (!empty($_GET['athlete_id'])) {
        $goalsUserId = (int)$_GET['athlete_id'];
    }
    try {
        $stmt = $pdo->prepare("SELECT id, CONCAT(first_name, ' ', last_name) AS name FROM users WHERE role = 'athlete' ORDER BY first_name ASC LIMIT 100");
        $stmt->execute();
        $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) { $athletes = []; }
} elseif ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $goalsUserId = (int)$_SESSION['viewing_athlete_id'];
}

// Filter parameters
$filterStatus = $_GET['status'] ?? 'all';
$filterCategory = $_GET['cat'] ?? '';
$filterTag = $_GET['tag'] ?? '';

// Build query with filters
$whereClauses = ['athlete_id = ?'];
$params = [$goalsUserId];
if ($filterStatus === 'active') {
    $whereClauses[] = "status IN ('active', 'in_progress')";
} elseif ($filterStatus === 'completed') {
    $whereClauses[] = "status = 'completed'";
} elseif ($filterStatus === 'archived') {
    $whereClauses[] = "status = 'archived'";
} else {
    $whereClauses[] = "status != 'archived'";
}
if ($filterCategory !== '') {
    $whereClauses[] = 'category = ?';
    $params[] = $filterCategory;
}
if ($filterTag !== '') {
    $safeTag = str_replace(['%', '_'], ['\\%', '\\_'], $filterTag);
    $whereClauses[] = '(tags LIKE ? OR tags LIKE ? OR tags LIKE ? OR tags = ?)';
    $params[] = $safeTag . ',%';
    $params[] = '%,' . $safeTag . ',%';
    $params[] = '%,' . $safeTag;
    $params[] = $filterTag;
}
$whereSQL = implode(' AND ', $whereClauses);

$goals = [];
$categories = [];
$allTags = [];
try {
    $stmt = $pdo->prepare("
        SELECT g.id, COALESCE(g.title, g.goal_title) as title, g.description, g.status,
               g.completion_percentage, g.target_date, g.category, g.tags,
               (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id) as total_steps,
               (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id AND is_completed = 1) as completed_steps
        FROM goals g
        WHERE $whereSQL
        ORDER BY CASE g.status WHEN 'active' THEN 1 WHEN 'in_progress' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END,
                 g.created_at DESC
        LIMIT 50
    ");
    $stmt->execute($params);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $catStmt = $pdo->prepare("SELECT DISTINCT category FROM goals WHERE athlete_id = ? AND category IS NOT NULL AND category != '' ORDER BY category");
    $catStmt->execute([$goalsUserId]);
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);

    $tagStmt = $pdo->prepare("SELECT DISTINCT tags FROM goals WHERE athlete_id = ? AND tags IS NOT NULL AND tags != ''");
    $tagStmt->execute([$goalsUserId]);
    $tagRows = $tagStmt->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tagRows as $ts) {
        foreach (array_map('trim', explode(',', $ts)) as $t) {
            if ($t !== '') $allTags[$t] = true;
        }
    }
    $allTags = array_keys($allTags);
    sort($allTags);
} catch (PDOException $e) { $goals = []; }

$activeGoals = [];
$completedGoals = [];
foreach ($goals as $g) {
    if (($g['status'] ?? '') === 'completed') {
        $completedGoals[] = $g;
    } else {
        $activeGoals[] = $g;
    }
}

$canEdit = $isAnyCoach || $isAdmin || ($goalsUserId === $user_id);
$canDelete = $isAnyCoach || $isAdmin;
?>
<style>
.m-goals { padding: 16px; font-family: Inter, sans-serif; padding-bottom: 80px; }
.m-goals-header { margin-bottom: 16px; }
.m-goals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-goals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-section { margin-bottom: 20px; }
.m-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-goal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-goal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-goal-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-goal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-goal-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-goal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-goal-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-goal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-goal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-goal-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-goal-progress { margin-bottom: 8px; }
.m-goal-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-goal-progress-label { font-size: 11px; color: #6B6B7B; }
.m-goal-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-goal-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-goal-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-goal-footer { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.m-goal-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }

/* Coach athlete selector */
.m-athlete-select {
    width: 100%; padding: 10px 12px; margin-bottom: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B6B7B' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-athlete-select option { background: #16161F; color: #fff; }

/* Filter controls */
.m-filters { display: flex; gap: 8px; margin-bottom: 14px; flex-wrap: wrap; }
.m-filter-btn {
    padding: 7px 14px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #16161F; color: #A8A8B8; font-size: 12px; font-weight: 600;
    cursor: pointer; min-height: 36px; font-family: Inter, sans-serif;
    transition: all 0.15s ease;
}
.m-filter-btn.m-filter-active {
    background: rgba(107,70,193,0.15); color: #8B5CF6; border-color: #6B46C1;
}
.m-filter-cat {
    padding: 7px 12px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #16161F; color: #A8A8B8; font-size: 12px; font-family: Inter, sans-serif;
    min-height: 36px; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' fill='%236B6B7B' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center;
    padding-right: 28px;
}

/* Card action buttons */
.m-goal-actions {
    display: flex; gap: 8px; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #2D2D3F;
}
.m-goal-act {
    display: flex; align-items: center; justify-content: center; gap: 4px;
    padding: 8px 12px; border-radius: 8px; border: none;
    font-size: 12px; font-weight: 500; cursor: pointer;
    min-height: 36px; min-width: 44px; font-family: Inter, sans-serif;
    transition: opacity 0.15s;
}
.m-goal-act:active { opacity: 0.7; }
.m-goal-act-edit { background: rgba(107,70,193,0.12); color: #8B5CF6; }
.m-goal-act-delete { background: rgba(239,68,68,0.12); color: #EF4444; }
.m-goal-act-progress { background: rgba(16,185,129,0.12); color: #10B981; flex: 1; }

/* Progress update slider */
.m-progress-update {
    display: none; margin-top: 8px; padding: 10px; background: #1A1A25;
    border-radius: 8px; border: 1px solid #2D2D3F;
}
.m-progress-update.m-visible { display: block; }
.m-progress-slider {
    width: 100%; margin: 6px 0; accent-color: #8B5CF6; height: 6px;
}
.m-progress-val { font-size: 13px; color: #8B5CF6; font-weight: 600; text-align: center; margin-bottom: 4px; }
.m-progress-save {
    width: 100%; padding: 10px; border-radius: 8px; border: none;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff;
    font-size: 13px; font-weight: 600; cursor: pointer; min-height: 44px;
    font-family: Inter, sans-serif; margin-top: 6px;
}

/* FAB */
.m-goal-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    border: none; cursor: pointer;
    box-shadow: 0 4px 16px rgba(107,70,193,0.4);
}

/* Modal overlay */
.m-goal-overlay {
    display: none; position: fixed; inset: 0; z-index: 100;
    background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
}
.m-goal-overlay.m-visible { display: flex; align-items: flex-end; }
.m-goal-modal {
    width: 100%; max-height: 92vh; background: #0A0A0F;
    border-radius: 16px 16px 0 0; overflow-y: auto;
    animation: mGoalSlideUp 0.25s ease-out;
}
@keyframes mGoalSlideUp {
    from { transform: translateY(100%); }
    to { transform: translateY(0); }
}
.m-modal-header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px; border-bottom: 1px solid #2D2D3F;
    position: sticky; top: 0; background: #0A0A0F; z-index: 1;
}
.m-modal-title { font-size: 16px; font-weight: 700; color: #fff; margin: 0; }
.m-modal-close {
    width: 36px; height: 36px; border-radius: 50%; border: none;
    background: #16161F; color: #A8A8B8; font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
    min-width: 44px; min-height: 44px;
}
.m-modal-body { padding: 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; }
.m-form-input, .m-form-select, .m-form-textarea {
    width: 100%; padding: 11px 12px; border-radius: 10px;
    border: 1px solid #2D2D3F; background: #16161F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-form-textarea { min-height: 80px; resize: vertical; }
.m-form-input:focus, .m-form-select:focus, .m-form-textarea:focus {
    outline: none; border-color: #6B46C1;
}
.m-form-select { appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='%236B6B7B' viewBox='0 0 16 16'%3E%3Cpath d='M8 11L3 6h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-form-select option { background: #16161F; color: #fff; }
.m-form-submit {
    width: 100%; padding: 14px; border-radius: 12px; border: none;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff;
    font-size: 15px; font-weight: 700; cursor: pointer;
    min-height: 50px; font-family: Inter, sans-serif;
    margin-top: 4px;
}
.m-form-submit:disabled { opacity: 0.5; cursor: not-allowed; }
.m-form-required { color: #EF4444; }
.m-toast {
    display: none; position: fixed; bottom: 100px; left: 50%; transform: translateX(-50%);
    padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 600;
    z-index: 200; font-family: Inter, sans-serif;
}
.m-toast-success { background: rgba(16,185,129,0.9); color: #fff; }
.m-toast-error { background: rgba(239,68,68,0.9); color: #fff; }

/* Goal card tap target */
.m-goal-card-body { cursor: pointer; }

/* Complete / Archive buttons */
.m-goal-act-complete { background: rgba(16,185,129,0.12); color: #10B981; }
.m-goal-act-archive { background: rgba(100,116,139,0.12); color: #94A3B8; }
.m-goal-badge-archived { background: rgba(100,116,139,0.15); color: #94A3B8; }

/* Steps in create/edit form */
.m-steps-section { margin-top: 10px; padding-top: 10px; border-top: 1px solid #2D2D3F; }
.m-steps-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.m-steps-title { font-size: 13px; font-weight: 600; color: #A8A8B8; }
.m-btn-add-step {
    padding: 6px 12px; border-radius: 6px; border: 1px solid #6B46C1;
    background: transparent; color: #8B5CF6; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: Inter, sans-serif;
}
.m-step-item {
    display: flex; align-items: center; gap: 8px; margin-bottom: 6px;
    background: #1A1A25; border: 1px solid #2D2D3F; border-radius: 8px; padding: 8px 10px;
}
.m-step-item input[type="text"] {
    flex: 1; background: transparent; border: none; color: #fff; font-size: 13px;
    font-family: Inter, sans-serif; outline: none; min-height: 28px;
}
.m-step-remove {
    background: transparent; border: none; color: #EF4444; cursor: pointer;
    font-size: 14px; padding: 4px 6px; min-width: 32px; min-height: 32px;
    display: flex; align-items: center; justify-content: center;
}

/* Detail modal */
.m-detail-category {
    display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 10px;
    font-weight: 700; text-transform: uppercase; margin-bottom: 6px;
    background: rgba(107,70,193,0.2); color: #8B5CF6; border: 1px solid #6B46C1;
}
.m-detail-desc { font-size: 13px; color: #A8A8B8; line-height: 1.5; margin: 8px 0 12px; }
.m-detail-tags { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.m-detail-tag {
    padding: 2px 8px; background: rgba(107,70,193,0.1); border: 1px solid #2D2D3F;
    border-radius: 4px; font-size: 11px; color: #A8A8B8;
}
.m-detail-badges { display: flex; gap: 8px; align-items: center; margin-bottom: 12px; flex-wrap: wrap; }
.m-detail-status {
    display: inline-block; padding: 3px 10px; border-radius: 12px;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
}
.m-detail-status-active { background: rgba(59,130,246,0.2); color: #3B82F6; }
.m-detail-status-completed { background: rgba(16,185,129,0.2); color: #10B981; }
.m-detail-status-archived { background: rgba(100,116,139,0.2); color: #94A3B8; }
.m-detail-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }

/* Steps in detail modal */
.m-detail-steps { margin-top: 16px; }
.m-detail-steps-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-detail-step {
    display: flex; align-items: flex-start; gap: 10px; padding: 10px;
    background: #1A1A25; border: 1px solid #2D2D3F; border-radius: 8px; margin-bottom: 6px;
}
.m-detail-step.m-step-done { border-color: rgba(16,185,129,0.3); background: rgba(16,185,129,0.05); }
.m-detail-step input[type="checkbox"] {
    width: 20px; height: 20px; cursor: pointer; margin-top: 1px; accent-color: #10B981; flex-shrink: 0;
}
.m-detail-step-title { font-size: 13px; font-weight: 600; color: #fff; }
.m-detail-step-done-info { font-size: 11px; color: #10B981; margin-top: 3px; }

/* Progress history */
.m-detail-history { margin-top: 16px; padding-top: 14px; border-top: 1px solid #2D2D3F; }
.m-detail-history-title { font-size: 14px; font-weight: 700; color: #fff; margin-bottom: 8px; }
.m-detail-entry {
    background: #0D0D14; border-left: 3px solid #6B46C1; padding: 10px 12px;
    margin-bottom: 8px; border-radius: 4px;
}
.m-detail-entry-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-detail-entry-user { font-size: 12px; font-weight: 600; color: #fff; }
.m-detail-entry-date { font-size: 11px; color: #6B6B7B; }
.m-detail-entry-note { font-size: 12px; color: #A8A8B8; line-height: 1.4; }

/* Add progress note btn in detail */
.m-btn-add-progress {
    width: 100%; padding: 10px; border-radius: 8px; border: none; margin-top: 12px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff;
    font-size: 13px; font-weight: 600; cursor: pointer; min-height: 44px;
    font-family: Inter, sans-serif;
}

/* Steps count in footer */
.m-goal-steps-count { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
</style>

<div class="m-goals">
    <div class="m-goals-header">
        <h2 class="m-goals-title">Goals</h2>
        <p class="m-goals-sub"><?= count($activeGoals) ?> active · <?= count($completedGoals) ?> completed</p>
    </div>

    <?php if ($isAnyCoach && !empty($athletes)): ?>
    <select class="m-athlete-select" onchange="mGoalSwitchAthlete(this.value)" aria-label="Select athlete">
        <option value="">-- Select Athlete --</option>
        <?php foreach ($athletes as $a): ?>
        <option value="<?= (int)$a['id'] ?>" <?= $goalsUserId === (int)$a['id'] ? 'selected' : '' ?>><?= htmlspecialchars($a['name']) ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>

    <!-- Filters -->
    <div class="m-filters">
        <button type="button" class="m-filter-btn <?= $filterStatus === 'all' ? 'm-filter-active' : '' ?>" onclick="mGoalFilter('all')">All</button>
        <button type="button" class="m-filter-btn <?= $filterStatus === 'active' ? 'm-filter-active' : '' ?>" onclick="mGoalFilter('active')">Active</button>
        <button type="button" class="m-filter-btn <?= $filterStatus === 'completed' ? 'm-filter-active' : '' ?>" onclick="mGoalFilter('completed')">Completed</button>
        <button type="button" class="m-filter-btn <?= $filterStatus === 'archived' ? 'm-filter-active' : '' ?>" onclick="mGoalFilter('archived')">Archived</button>
        <?php if (!empty($categories)): ?>
        <select class="m-filter-cat" onchange="mGoalFilterCat(this.value)" aria-label="Filter by category">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
            <option value="<?= htmlspecialchars($cat) ?>" <?= $filterCategory === $cat ? 'selected' : '' ?>><?= htmlspecialchars($cat) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <?php if (!empty($allTags)): ?>
        <select class="m-filter-cat" onchange="mGoalFilterTag(this.value)" aria-label="Filter by tag">
            <option value="">All Tags</option>
            <?php foreach ($allTags as $tag): ?>
            <option value="<?= htmlspecialchars($tag) ?>" <?= $filterTag === $tag ? 'selected' : '' ?>><?= htmlspecialchars($tag) ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
    </div>

    <?php if (empty($goals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullseye"></i>
            <p>No goals set yet</p>
        </div>
    <?php else: ?>
        <!-- Active Goals -->
        <?php if (!empty($activeGoals)): ?>
        <div class="m-section">
            <h3 class="m-section-title">Active Goals</h3>
            <?php foreach ($activeGoals as $g):
                $pct = max(0, min(100, (int)($g['completion_percentage'] ?? 0)));
                $status = strtolower($g['status'] ?? 'active');
                $badgeClass = match($status) {
                    'active', 'in_progress' => 'active',
                    'completed' => 'completed',
                    'paused' => 'paused',
                    'cancelled' => 'cancelled',
                    default => 'default',
                };
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
                $gJson = htmlspecialchars(json_encode([
                    'id' => (int)$g['id'], 'title' => $g['title'] ?? '',
                    'description' => $g['description'] ?? '', 'category' => $g['category'] ?? '',
                    'tags' => $g['tags'] ?? '', 'target_date' => $g['target_date'] ?? '',
                    'status' => $status,
                ]), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="m-goal-card" data-goal-id="<?= (int)$g['id'] ?>">
                <div class="m-goal-card-body" role="button" tabindex="0" onclick="mGoalViewDetail(<?= (int)$g['id'] ?>)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();mGoalViewDetail(<?= (int)$g['id'] ?>)}">
                <div class="m-goal-top">
                    <span class="m-goal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                    <span class="m-goal-badge m-goal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <?php if (!empty($g['description'])): ?>
                <p class="m-goal-desc"><?= htmlspecialchars($g['description']) ?></p>
                <?php endif; ?>
                <div class="m-goal-progress">
                    <div class="m-goal-progress-header">
                        <span class="m-goal-progress-label">Progress</span>
                        <span class="m-goal-progress-pct" id="m-pct-<?= (int)$g['id'] ?>"><?= $pct ?>%</span>
                    </div>
                    <div class="m-goal-progress-bar">
                        <div class="m-goal-progress-fill" id="m-bar-<?= (int)$g['id'] ?>" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <div class="m-goal-footer">
                    <?php if (!empty($g['target_date'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['category'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-tag"></i> <?= htmlspecialchars($g['category']) ?></span>
                    <?php endif; ?>
                    <?php if (((int)($g['total_steps'] ?? 0)) > 0): ?>
                    <span class="m-goal-steps-count"><i class="fas fa-list-check"></i> <?= (int)$g['completed_steps'] ?>/<?= (int)$g['total_steps'] ?> steps</span>
                    <?php endif; ?>
                </div>
                </div>
                <?php if ($canEdit): ?>
                <div class="m-goal-actions">
                    <button type="button" class="m-goal-act m-goal-act-edit" onclick='mGoalEdit(<?= $gJson ?>)' aria-label="Edit <?= htmlspecialchars($g['title'] ?? 'goal') ?>"><i class="fas fa-pen"></i> Edit</button>
                    <button type="button" class="m-goal-act m-goal-act-progress" onclick="mGoalToggleProgress(<?= (int)$g['id'] ?>, <?= $pct ?>)" aria-label="Update progress for <?= htmlspecialchars($g['title'] ?? 'goal') ?>"><i class="fas fa-chart-line"></i> Progress</button>
                    <button type="button" class="m-goal-act m-goal-act-complete" onclick="mGoalComplete(<?= (int)$g['id'] ?>)" aria-label="Complete goal"><i class="fas fa-check"></i></button>
                    <button type="button" class="m-goal-act m-goal-act-archive" onclick="mGoalArchive(<?= (int)$g['id'] ?>)" aria-label="Archive goal"><i class="fas fa-archive"></i></button>
                    <?php if ($canDelete): ?>
                    <button type="button" class="m-goal-act m-goal-act-delete" onclick="mGoalDelete(<?= (int)$g['id'] ?>)" aria-label="Delete <?= htmlspecialchars($g['title'] ?? 'goal') ?>"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>
                </div>
                <div class="m-progress-update" id="m-prog-<?= (int)$g['id'] ?>">
                    <div class="m-progress-val" id="m-prog-val-<?= (int)$g['id'] ?>"><?= $pct ?>%</div>
                    <input type="range" class="m-progress-slider" min="0" max="100" value="<?= $pct ?>" oninput="mGoalSliderChange(<?= (int)$g['id'] ?>, this.value)">
                    <button type="button" class="m-progress-save" onclick="mGoalSaveProgress(<?= (int)$g['id'] ?>)">Save Progress</button>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Completed Goals -->
        <?php if (!empty($completedGoals)): ?>
        <div class="m-section">
            <h3 class="m-section-title">Completed</h3>
            <?php foreach ($completedGoals as $g):
                $gJson = htmlspecialchars(json_encode([
                    'id' => (int)$g['id'], 'title' => $g['title'] ?? '',
                    'description' => $g['description'] ?? '', 'category' => $g['category'] ?? '',
                    'tags' => $g['tags'] ?? '', 'target_date' => $g['target_date'] ?? '',
                    'status' => 'completed',
                ]), ENT_QUOTES, 'UTF-8');
            ?>
            <div class="m-goal-card" data-goal-id="<?= (int)$g['id'] ?>">
                <div class="m-goal-card-body" role="button" tabindex="0" onclick="mGoalViewDetail(<?= (int)$g['id'] ?>)" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();mGoalViewDetail(<?= (int)$g['id'] ?>)}">
                <div class="m-goal-top">
                    <span class="m-goal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                    <span class="m-goal-badge m-goal-badge-completed"><i class="fas fa-check"></i> Done</span>
                </div>
                <?php if (!empty($g['description'])): ?>
                <p class="m-goal-desc"><?= htmlspecialchars($g['description']) ?></p>
                <?php endif; ?>
                <div class="m-goal-progress">
                    <div class="m-goal-progress-bar">
                        <div class="m-goal-progress-fill" style="width:100%;background:#10B981;"></div>
                    </div>
                </div>
                <div class="m-goal-footer">
                    <?php if (!empty($g['target_date'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['category'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-tag"></i> <?= htmlspecialchars($g['category']) ?></span>
                    <?php endif; ?>
                </div>
                </div>
                <?php if ($canEdit): ?>
                <div class="m-goal-actions">
                    <button type="button" class="m-goal-act m-goal-act-edit" onclick='mGoalEdit(<?= $gJson ?>)' aria-label="Edit <?= htmlspecialchars($g['title'] ?? 'goal') ?>"><i class="fas fa-pen"></i> Edit</button>
                    <button type="button" class="m-goal-act m-goal-act-archive" onclick="mGoalArchive(<?= (int)$g['id'] ?>)" aria-label="Archive goal"><i class="fas fa-archive"></i> Archive</button>
                    <?php if ($canDelete): ?>
                    <button type="button" class="m-goal-act m-goal-act-delete" onclick="mGoalDelete(<?= (int)$g['id'] ?>)" aria-label="Delete <?= htmlspecialchars($g['title'] ?? 'goal') ?>"><i class="fas fa-trash-alt"></i></button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- FAB: Create Goal -->
<?php if ($canEdit): ?>
<button type="button" class="m-goal-fab" onclick="mGoalCreate()" title="New Goal"><i class="fas fa-plus"></i></button>
<?php endif; ?>

<!-- Modal: Create / Edit Goal -->
<div class="m-goal-overlay" id="mGoalOverlay" onclick="if(event.target===this)mGoalCloseModal()">
    <div class="m-goal-modal">
        <div class="m-modal-header">
            <h3 class="m-modal-title" id="mGoalModalTitle">New Goal</h3>
            <button type="button" class="m-modal-close" onclick="mGoalCloseModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="m-modal-body">
            <form id="mGoalForm" onsubmit="return mGoalSubmit(event)">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" id="mGoalAction" value="create_goal">
                <input type="hidden" name="goal_id" id="mGoalId" value="">
                <input type="hidden" name="athlete_id" value="<?= (int)$goalsUserId ?>">
                <div class="m-form-group">
                    <label class="m-form-label">Title <span class="m-form-required">*</span></label>
                    <input type="text" name="title" id="mGoalTitle" class="m-form-input" required placeholder="e.g. Improve shot accuracy">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Description</label>
                    <textarea name="description" id="mGoalDesc" class="m-form-textarea" placeholder="Describe this goal..." rows="3"></textarea>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Category</label>
                    <select name="category" id="mGoalCategory" class="m-form-select">
                        <option value="">-- None --</option>
                        <option value="Skating">Skating</option>
                        <option value="Shooting">Shooting</option>
                        <option value="Passing">Passing</option>
                        <option value="Defense">Defense</option>
                        <option value="Fitness">Fitness</option>
                        <option value="Mental">Mental</option>
                        <option value="Teamwork">Teamwork</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Tags</label>
                    <input type="text" name="tags" id="mGoalTags" class="m-form-input" placeholder="e.g. speed, agility">
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Target Date</label>
                    <input type="date" name="target_date" id="mGoalDate" class="m-form-input">
                </div>
                <div class="m-steps-section">
                    <div class="m-steps-header">
                        <span class="m-steps-title">Steps</span>
                        <button type="button" class="m-btn-add-step" onclick="mGoalAddStep()"><i class="fas fa-plus"></i> Add Step</button>
                    </div>
                    <div id="mGoalStepsList"></div>
                </div>
                <button type="submit" class="m-form-submit" id="mGoalSubmitBtn">Create Goal</button>
            </form>
        </div>
    </div>
</div>

<!-- Toast notification -->
<div class="m-toast" id="mGoalToast"></div>

<!-- Modal: Goal Detail -->
<div class="m-goal-overlay" id="mGoalDetailOverlay" onclick="if(event.target===this)mGoalCloseDetail()">
    <div class="m-goal-modal">
        <div class="m-modal-header">
            <h3 class="m-modal-title">Goal Details</h3>
            <button type="button" class="m-modal-close" onclick="mGoalCloseDetail()"><i class="fas fa-times"></i></button>
        </div>
        <div class="m-modal-body" id="mGoalDetailContent">
            <p style="text-align:center;color:#6B6B7B;">Loading...</p>
        </div>
    </div>
</div>

<!-- Modal: Add Progress Note -->
<div class="m-goal-overlay" id="mGoalProgressOverlay" onclick="if(event.target===this)mGoalCloseProgressNote()">
    <div class="m-goal-modal">
        <div class="m-modal-header">
            <h3 class="m-modal-title">Add Progress Note</h3>
            <button type="button" class="m-modal-close" onclick="mGoalCloseProgressNote()"><i class="fas fa-times"></i></button>
        </div>
        <div class="m-modal-body">
            <form id="mGoalProgressNoteForm" onsubmit="return mGoalSubmitProgressNote(event)">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="goal_id" id="mGoalProgressNoteId" value="">
                <div class="m-form-group">
                    <label class="m-form-label">Progress Note <span class="m-form-required">*</span></label>
                    <textarea name="progress_note" id="mGoalProgressNoteText" class="m-form-textarea" required placeholder="Describe your progress..." rows="3"></textarea>
                </div>
                <div class="m-form-group">
                    <label class="m-form-label">Progress Percentage (optional)</label>
                    <input type="number" name="progress_percentage" id="mGoalProgressNotePct" class="m-form-input" min="0" max="100" placeholder="e.g. 75">
                </div>
                <button type="submit" class="m-form-submit" id="mGoalProgressNoteBtn">Save Progress Note</button>
            </form>
        </div>
    </div>
</div>

<script>
var mGoalStepCounter = 0;
var mGoalCanEdit = <?= $canEdit ? 'true' : 'false' ?>;

function mGoalSwitchAthlete(id) {
    if (id) {
        window.location.href = 'pwa.php?page=goals&athlete_id=' + encodeURIComponent(id);
    }
}

function mGoalFilter(status) {
    var params = new URLSearchParams(window.location.search);
    params.set('page', 'goals');
    params.set('status', status);
    window.location.href = 'pwa.php?' + params.toString();
}

function mGoalFilterCat(cat) {
    var params = new URLSearchParams(window.location.search);
    params.set('page', 'goals');
    if (cat) { params.set('cat', cat); } else { params.delete('cat'); }
    window.location.href = 'pwa.php?' + params.toString();
}

function mGoalFilterTag(tag) {
    var params = new URLSearchParams(window.location.search);
    params.set('page', 'goals');
    if (tag) { params.set('tag', tag); } else { params.delete('tag'); }
    window.location.href = 'pwa.php?' + params.toString();
}

/* Steps management in create/edit form */
function mGoalAddStep() {
    mGoalStepCounter++;
    var html = '<div class="m-step-item" data-step-idx="' + mGoalStepCounter + '">'
        + '<input type="text" name="steps[' + mGoalStepCounter + '][title]" placeholder="Step title" required>'
        + '<input type="hidden" name="steps[' + mGoalStepCounter + '][order]" value="' + mGoalStepCounter + '">'
        + '<button type="button" class="m-step-remove" onclick="mGoalRemoveStep(' + mGoalStepCounter + ')" aria-label="Remove step"><i class="fas fa-times"></i></button>'
        + '</div>';
    document.getElementById('mGoalStepsList').insertAdjacentHTML('beforeend', html);
}

function mGoalRemoveStep(idx) {
    var el = document.querySelector('[data-step-idx="' + idx + '"]');
    if (el) el.remove();
}

function mGoalCreate() {
    document.getElementById('mGoalModalTitle').textContent = 'New Goal';
    document.getElementById('mGoalAction').value = 'create_goal';
    document.getElementById('mGoalId').value = '';
    document.getElementById('mGoalSubmitBtn').textContent = 'Create Goal';
    document.getElementById('mGoalForm').reset();
    document.getElementById('mGoalStepsList').innerHTML = '';
    mGoalStepCounter = 0;
    document.getElementById('mGoalOverlay').classList.add('m-visible');
}

function mGoalEdit(g) {
    document.getElementById('mGoalModalTitle').textContent = 'Edit Goal';
    document.getElementById('mGoalAction').value = 'update_goal';
    document.getElementById('mGoalId').value = g.id;
    document.getElementById('mGoalTitle').value = g.title || '';
    document.getElementById('mGoalDesc').value = g.description || '';
    document.getElementById('mGoalTags').value = g.tags || '';
    document.getElementById('mGoalDate').value = g.target_date || '';
    document.getElementById('mGoalSubmitBtn').textContent = 'Save Changes';
    var catSel = document.getElementById('mGoalCategory');
    catSel.value = g.category || '';
    if (catSel.value !== (g.category || '') && g.category) {
        var opt = document.createElement('option');
        opt.value = g.category;
        opt.textContent = g.category;
        catSel.appendChild(opt);
        catSel.value = g.category;
    }
    // Load existing steps via AJAX
    document.getElementById('mGoalStepsList').innerHTML = '';
    mGoalStepCounter = 0;
    fetch('process_goals.php?action=get_goal&goal_id=' + g.id, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.steps && data.steps.length) {
                data.steps.forEach(function(step) {
                    mGoalStepCounter++;
                    var html = '<div class="m-step-item" data-step-idx="' + mGoalStepCounter + '">'
                        + '<input type="text" name="steps[' + mGoalStepCounter + '][title]" value="' + mEscapeAttr(step.title || '') + '" placeholder="Step title" required>'
                        + '<input type="hidden" name="steps[' + mGoalStepCounter + '][id]" value="' + (step.id || '') + '">'
                        + '<input type="hidden" name="steps[' + mGoalStepCounter + '][order]" value="' + mGoalStepCounter + '">'
                        + '<button type="button" class="m-step-remove" onclick="mGoalRemoveStep(' + mGoalStepCounter + ')" aria-label="Remove step"><i class="fas fa-times"></i></button>'
                        + '</div>';
                    document.getElementById('mGoalStepsList').insertAdjacentHTML('beforeend', html);
                });
            }
        })
        .catch(function() {});
    document.getElementById('mGoalOverlay').classList.add('m-visible');
}

function mGoalCloseModal() {
    document.getElementById('mGoalOverlay').classList.remove('m-visible');
}

function mGoalSubmit(e) {
    e.preventDefault();
    var form = document.getElementById('mGoalForm');
    var btn = document.getElementById('mGoalSubmitBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    var data = new FormData(form);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) {
            if (r.redirected || r.ok) {
                persistToast('Goal saved!', 'success');
                window.location.reload();
            } else {
                return r.text().then(function(t) { throw new Error(t || 'Save failed'); });
            }
        })
        .catch(function(err) {
            mGoalToast(err.message || 'Error saving goal', 'error');
            btn.disabled = false;
            btn.textContent = document.getElementById('mGoalAction').value === 'create_goal' ? 'Create Goal' : 'Save Changes';
        });
    return false;
}

function mGoalDelete(id) {
    if (!confirm('Delete this goal? This action cannot be undone.')) return;
    var data = new FormData();
    data.append('action', 'delete_goal');
    data.append('goal_id', id);
    var tokenInput = document.querySelector('#mGoalForm input[name="csrf_token"]');
    if (tokenInput) data.append('csrf_token', tokenInput.value);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) {
            if (r.redirected || r.ok) {
                persistToast('Goal deleted', 'success');
                var card = document.querySelector('.m-goal-card[data-goal-id="' + id + '"]');
                if (card) card.style.display = 'none';
                window.location.reload();
            } else {
                return r.text().then(function(t) { throw new Error(t || 'Delete failed'); });
            }
        })
        .catch(function(err) { mGoalToast(err.message || 'Error deleting goal', 'error'); });
}

/* Complete Goal */
function mGoalComplete(id) {
    if (!confirm('Mark this goal as completed?')) return;
    var data = new FormData();
    data.append('action', 'complete_goal');
    data.append('goal_id', id);
    var tokenInput = document.querySelector('#mGoalForm input[name="csrf_token"]');
    if (tokenInput) data.append('csrf_token', tokenInput.value);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) {
            if (r.redirected || r.ok) {
                persistToast('Goal completed!', 'success');
                window.location.reload();
            } else {
                return r.text().then(function(t) { throw new Error(t || 'Complete failed'); });
            }
        })
        .catch(function(err) { mGoalToast(err.message || 'Error completing goal', 'error'); });
}

/* Archive Goal */
function mGoalArchive(id) {
    if (!confirm('Archive this goal?')) return;
    var data = new FormData();
    data.append('action', 'archive_goal');
    data.append('goal_id', id);
    var tokenInput = document.querySelector('#mGoalForm input[name="csrf_token"]');
    if (tokenInput) data.append('csrf_token', tokenInput.value);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) {
            if (r.redirected || r.ok) {
                persistToast('Goal archived', 'success');
                var card = document.querySelector('.m-goal-card[data-goal-id="' + id + '"]');
                if (card) card.style.display = 'none';
                window.location.reload();
            } else {
                return r.text().then(function(t) { throw new Error(t || 'Archive failed'); });
            }
        })
        .catch(function(err) { mGoalToast(err.message || 'Error archiving goal', 'error'); });
}

function mGoalToggleProgress(id, current) {
    var el = document.getElementById('m-prog-' + id);
    if (el) el.classList.toggle('m-visible');
}

function mGoalSliderChange(id, val) {
    var label = document.getElementById('m-prog-val-' + id);
    if (label) label.textContent = val + '%';
}

function mGoalSaveProgress(id) {
    var slider = document.querySelector('#m-prog-' + id + ' .m-progress-slider');
    if (!slider) return;
    var val = slider.value;
    var data = new FormData();
    data.append('action', 'update_goal');
    data.append('goal_id', id);
    data.append('title', document.querySelector('.m-goal-card[data-goal-id="' + id + '"] .m-goal-title').textContent.trim());
    data.append('completion_percentage', val);
    if (parseInt(val) >= 100) {
        if (!confirm('Setting progress to 100% will mark this goal as completed. Continue?')) return;
        data.append('status', 'completed');
    }
    var tokenInput = document.querySelector('#mGoalForm input[name="csrf_token"]');
    if (tokenInput) data.append('csrf_token', tokenInput.value);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) {
            if (r.redirected || r.ok) {
                mGoalToast('Progress updated!', 'success');
                var pctEl = document.getElementById('m-pct-' + id);
                var barEl = document.getElementById('m-bar-' + id);
                if (pctEl) pctEl.textContent = val + '%';
                if (barEl) {
                    barEl.style.width = val + '%';
                    barEl.style.background = val >= 75 ? '#10B981' : (val >= 40 ? '#F59E0B' : '#8B5CF6');
                }
                document.getElementById('m-prog-' + id).classList.remove('m-visible');
                if (parseInt(val) >= 100) window.location.reload();
            } else {
                return r.text().then(function(t) { throw new Error(t || 'Update failed'); });
            }
        })
        .catch(function(err) { mGoalToast(err.message || 'Error updating progress', 'error'); });
}

/* Goal Detail Modal */
function mGoalViewDetail(goalId) {
    document.getElementById('mGoalDetailContent').innerHTML = '<p style="text-align:center;color:#6B6B7B;">Loading...</p>';
    document.getElementById('mGoalDetailOverlay').classList.add('m-visible');
    fetch('process_goals.php?action=get_goal_detail&goal_id=' + goalId, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) { mGoalRenderDetail(data); })
        .catch(function() {
            document.getElementById('mGoalDetailContent').innerHTML = '<p style="text-align:center;color:#EF4444;">Failed to load goal details.</p>';
        });
}

function mGoalRenderDetail(data) {
    var statusClass = 'default';
    var s = (data.status || '').toLowerCase();
    if (s === 'active' || s === 'in_progress') statusClass = 'active';
    else if (s === 'completed') statusClass = 'completed';
    else if (s === 'archived') statusClass = 'archived';

    var html = '';
    // Badges row
    html += '<div class="m-detail-badges">';
    if (data.category) html += '<span class="m-detail-category">' + mEscapeHtml(data.category) + '</span>';
    html += '<span class="m-detail-status m-detail-status-' + statusClass + '">' + mEscapeHtml(data.status || 'active') + '</span>';
    html += '</div>';

    // Description
    if (data.description) {
        html += '<div class="m-detail-desc">' + mEscapeHtml(data.description) + '</div>';
    }

    // Tags
    if (data.tags) {
        html += '<div class="m-detail-tags">';
        data.tags.split(',').forEach(function(t) {
            t = t.trim();
            if (t) html += '<span class="m-detail-tag">' + mEscapeHtml(t) + '</span>';
        });
        html += '</div>';
    }

    // Progress bar
    var pct = Math.round(data.completion_percentage || 0);
    var barColor = pct >= 75 ? '#10B981' : (pct >= 40 ? '#F59E0B' : '#8B5CF6');
    html += '<div class="m-goal-progress"><div class="m-goal-progress-header">'
        + '<span class="m-goal-progress-label">Overall Progress</span>'
        + '<span class="m-goal-progress-pct">' + pct + '%</span></div>'
        + '<div class="m-goal-progress-bar"><div class="m-goal-progress-fill" style="width:' + pct + '%;background:' + barColor + ';"></div></div></div>';

    // Steps
    if (data.steps && data.steps.length > 0) {
        html += '<div class="m-detail-steps"><div class="m-detail-steps-title">Steps</div>';
        data.steps.forEach(function(step) {
            var done = step.is_completed == 1;
            html += '<div class="m-detail-step' + (done ? ' m-step-done' : '') + '">';
            if (mGoalCanEdit) {
                html += '<input type="checkbox"' + (done ? ' checked' : '') + ' onchange="mGoalToggleStep(' + step.id + ',' + data.id + ',this.checked)">';
            } else {
                html += '<i class="fas ' + (done ? 'fa-check-circle' : 'fa-circle') + '" style="color:' + (done ? '#10B981' : '#6B6B7B') + ';margin-top:2px;"></i>';
            }
            html += '<div><div class="m-detail-step-title">' + mEscapeHtml(step.title || '') + '</div>';
            if (done && step.completed_at) {
                html += '<div class="m-detail-step-done-info"><i class="fas fa-check"></i> Completed ' + mFormatDate(step.completed_at) + '</div>';
            }
            html += '</div></div>';
        });
        html += '</div>';
    }

    // Add Progress Note button
    if (mGoalCanEdit) {
        html += '<button type="button" class="m-btn-add-progress" onclick="mGoalOpenProgressNote(' + data.id + ')"><i class="fas fa-plus"></i> Add Progress Note</button>';
    }

    // Progress history
    if (data.progress && data.progress.length > 0) {
        html += '<div class="m-detail-history"><div class="m-detail-history-title">Progress History</div>';
        data.progress.forEach(function(entry) {
            html += '<div class="m-detail-entry"><div class="m-detail-entry-header">'
                + '<span class="m-detail-entry-user">' + mEscapeHtml(entry.user_name || '') + '</span>'
                + '<span class="m-detail-entry-date">' + mFormatDate(entry.created_at) + '</span></div>'
                + '<div class="m-detail-entry-note">' + mEscapeHtml(entry.progress_note || '') + '</div></div>';
        });
        html += '</div>';
    }

    document.getElementById('mGoalDetailContent').innerHTML = html;
}

function mGoalCloseDetail() {
    document.getElementById('mGoalDetailOverlay').classList.remove('m-visible');
}

/* Toggle Step Completion */
function mGoalToggleStep(stepId, goalId, isChecked) {
    var data = new FormData();
    data.append('action', 'complete_step');
    data.append('step_id', stepId);
    data.append('goal_id', goalId);
    data.append('is_completed', isChecked ? '1' : '0');
    var tokenInput = document.querySelector('#mGoalForm input[name="csrf_token"]');
    if (tokenInput) data.append('csrf_token', tokenInput.value);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(res) {
            if (res.success) {
                mGoalViewDetail(goalId);
            } else {
                mGoalToast(res.message || 'Error toggling step', 'error');
            }
        })
        .catch(function(err) { mGoalToast('Error toggling step', 'error'); });
}

/* Progress Note Modal */
function mGoalOpenProgressNote(goalId) {
    document.getElementById('mGoalProgressNoteForm').reset();
    document.getElementById('mGoalProgressNoteId').value = goalId;
    document.getElementById('mGoalProgressOverlay').classList.add('m-visible');
}

function mGoalCloseProgressNote() {
    document.getElementById('mGoalProgressOverlay').classList.remove('m-visible');
}

function mGoalSubmitProgressNote(e) {
    e.preventDefault();
    var form = document.getElementById('mGoalProgressNoteForm');
    var btn = document.getElementById('mGoalProgressNoteBtn');
    btn.disabled = true;
    btn.textContent = 'Saving...';
    var data = new FormData(form);
    fetch('process_goals.php', { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function(r) {
            if (r.redirected || r.ok) {
                persistToast('Progress note added!', 'success');
                mGoalCloseProgressNote();
                var goalId = document.getElementById('mGoalProgressNoteId').value;
                if (goalId) mGoalViewDetail(parseInt(goalId));
                window.location.reload();
            } else {
                return r.text().then(function(t) { throw new Error(t || 'Save failed'); });
            }
        })
        .catch(function(err) {
            mGoalToast(err.message || 'Error saving note', 'error');
            btn.disabled = false;
            btn.textContent = 'Save Progress Note';
        });
    return false;
}

/* Utility functions */
function mEscapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

function mEscapeAttr(text) {
    return (text || '').replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function mFormatDate(dateStr) {
    if (!dateStr) return '';
    var d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function mGoalToast(msg, type) {
    var el = document.getElementById('mGoalToast');
    el.textContent = msg;
    el.className = 'm-toast m-toast-' + (type || 'success');
    el.style.display = 'block';
    setTimeout(function() { el.style.display = 'none'; }, 2500);
}
</script>
