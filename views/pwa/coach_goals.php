<?php
/**
 * PWA Coach Goals - Mobile-native goals assigned to athletes by coach
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT g.id, COALESCE(g.title, g.goal_title) as title, g.status,
               g.completion_percentage, u.first_name, u.last_name
        FROM goals g
        LEFT JOIN users u ON u.id = g.athlete_id
        WHERE g.coach_id = ?
        ORDER BY g.status ASC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

$athletes = [];
try {
    $stmtA = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name, u.last_name
        FROM users u
        INNER JOIN managed_athletes ma ON u.id = ma.athlete_id
        WHERE ma.coach_id = ? AND u.is_active = 1
        ORDER BY u.last_name, u.first_name
    ");
    $stmtA->execute([$user_id]);
    $athletes = $stmtA->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) { $athletes = decryptUserRows($athletes); }
} catch (PDOException $e) { $athletes = []; }

$totalGoals = count($goals);
?>
<style>
.m-coach-goals { padding: 16px; font-family: Inter, sans-serif; }
.m-coach-goals-header { margin-bottom: 16px; }
.m-coach-goals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coach-goals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cgoal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-cgoal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-cgoal-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-cgoal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-cgoal-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-cgoal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cgoal-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-cgoal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-cgoal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-cgoal-athlete { font-size: 12px; color: #A8A8B8; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; }
.m-cgoal-progress { margin-bottom: 4px; }
.m-cgoal-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-cgoal-progress-label { font-size: 11px; color: #6B6B7B; }
.m-cgoal-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-cgoal-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-cgoal-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-cgoal-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-cgoal-btn { background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center; }
.m-cgoal-btn-warn { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-fab { position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px; border-radius: 50%; background: #6B46C1; color: #fff; border: none; font-size: 22px; cursor: pointer; z-index: 999; box-shadow: 0 4px 12px rgba(107,70,193,0.4); display: flex; align-items: center; justify-content: center; }
.m-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none; }
.m-overlay.m-visible { display: block; }
.m-sheet { position: fixed; bottom: 0; left: 0; right: 0; background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh; overflow-y: auto; z-index: 1001; transform: translateY(100%); transition: transform 0.3s ease; padding: 20px 16px 32px; }
.m-sheet.m-visible { transform: translateY(0); }
.m-sheet-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-form-group { margin-bottom: 14px; }
.m-form-label { font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block; }
.m-form-input, .m-form-select, .m-form-textarea { background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff; padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box; font-family: Inter, sans-serif; font-size: 14px; }
.m-form-textarea { min-height: 80px; resize: vertical; }
.m-form-submit { background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; border: none; cursor: pointer; font-family: Inter, sans-serif; font-size: 14px; margin-top: 8px; }
.m-form-row { display: flex; gap: 10px; }
.m-form-row .m-form-group { flex: 1; }
</style>

<div class="m-coach-goals">
    <div class="m-coach-goals-header">
        <h2 class="m-coach-goals-title">Assigned Goals</h2>
        <p class="m-coach-goals-sub"><?= $totalGoals ?> goal<?= $totalGoals !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($goals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullseye"></i>
            <p>No goals assigned yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($goals as $g):
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
            $athName = htmlspecialchars(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? ''));
        ?>
        <div class="m-cgoal-card">
            <div class="m-cgoal-top">
                <span class="m-cgoal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                <span class="m-cgoal-badge m-cgoal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-cgoal-athlete">
                <i class="fas fa-user"></i> <?= $athName ?>
            </div>
            <div class="m-cgoal-progress">
                <div class="m-cgoal-progress-header">
                    <span class="m-cgoal-progress-label">Progress</span>
                    <span class="m-cgoal-progress-pct"><?= $pct ?>%</span>
                </div>
                <div class="m-cgoal-progress-bar">
                    <div class="m-cgoal-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <div class="m-cgoal-actions">
                <button class="m-cgoal-btn" onclick="mOpenEditGoal(<?= (int)$g['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                <button class="m-cgoal-btn m-cgoal-btn-warn" onclick="mOpenProgressGoal(<?= (int)$g['id'] ?>)"><i class="fas fa-arrow-up"></i> Progress</button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- FAB Button -->
<button class="m-fab" onclick="mOpenSheet('mCreateOverlay','mCreateSheet')" aria-label="Create Goal">
    <i class="fas fa-plus"></i>
</button>

<!-- Create Goal Overlay + Sheet -->
<div id="mCreateOverlay" class="m-overlay" onclick="mCloseSheet('mCreateOverlay','mCreateSheet')"></div>
<div id="mCreateSheet" class="m-sheet">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Create Goal</h3>
    <form method="POST" action="process_goals.php">
        <input type="hidden" name="action" value="create">
        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
        <div class="m-form-group">
            <label class="m-form-label">Athlete</label>
            <select name="athlete_id" class="m-form-select" required>
                <option value="">Select Athlete</option>
                <?php foreach ($athletes as $a): ?>
                    <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars(($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="goal_title" class="m-form-input" required placeholder="Goal title">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="goal_description" class="m-form-textarea" placeholder="Goal description"></textarea>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Type</label>
                <select name="goal_type" class="m-form-select">
                    <option value="general">General</option>
                    <option value="skill">Skill</option>
                    <option value="fitness">Fitness</option>
                    <option value="performance">Performance</option>
                </select>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Target Value</label>
                <input type="number" name="target_value" class="m-form-input" placeholder="0" step="any">
            </div>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Target Date</label>
            <input type="date" name="target_date" class="m-form-input">
        </div>
        <button type="submit" class="m-form-submit">Create Goal</button>
    </form>
</div>

<!-- Edit Goal Overlay + Sheet -->
<div id="mEditOverlay" class="m-overlay" onclick="mCloseSheet('mEditOverlay','mEditSheet')"></div>
<div id="mEditSheet" class="m-sheet">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Edit Goal</h3>
    <form method="POST" action="process_goals.php">
        <input type="hidden" name="action" value="update_goal">
        <input type="hidden" name="goal_id" id="mEditGoalId" value="">
        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="title" id="mEditTitle" class="m-form-input" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" id="mEditDescription" class="m-form-textarea"></textarea>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Category</label>
                <select name="category" id="mEditCategory" class="m-form-select">
                    <option value="skating">Skating</option>
                    <option value="shooting">Shooting</option>
                    <option value="passing">Passing</option>
                    <option value="stickhandling">Stickhandling</option>
                    <option value="fitness">Fitness</option>
                    <option value="mental">Mental</option>
                    <option value="general">General</option>
                </select>
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Status</label>
                <select name="status" id="mEditStatus" class="m-form-select">
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="paused">Paused</option>
                </select>
            </div>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Target Value</label>
                <input type="number" name="target_value" id="mEditTargetValue" class="m-form-input" step="any">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Current Value</label>
                <input type="number" name="current_value" id="mEditCurrentValue" class="m-form-input" step="any">
            </div>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Target Date</label>
            <input type="date" name="target_date" id="mEditTargetDate" class="m-form-input">
        </div>
        <button type="submit" class="m-form-submit">Save Changes</button>
    </form>
</div>

<!-- Update Progress Overlay + Sheet -->
<div id="mProgressOverlay" class="m-overlay" onclick="mCloseSheet('mProgressOverlay','mProgressSheet')"></div>
<div id="mProgressSheet" class="m-sheet">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Update Progress</h3>
    <form method="POST" action="process_goals.php">
        <input type="hidden" name="action" value="update_progress">
        <input type="hidden" name="goal_id" id="mProgressGoalId" value="">
        <?php if (function_exists('csrfTokenInput')) echo csrfTokenInput(); ?>
        <div class="m-form-group">
            <label class="m-form-label">Current Value</label>
            <input type="number" name="current_value" class="m-form-input" required step="any" placeholder="0">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Notes</label>
            <textarea name="notes" class="m-form-textarea" placeholder="Progress notes"></textarea>
        </div>
        <button type="submit" class="m-form-submit">Update Progress</button>
    </form>
</div>

<script>
function mOpenSheet(overlayId, sheetId) {
    document.getElementById(overlayId).classList.add('m-visible');
    document.getElementById(sheetId).classList.add('m-visible');
}
function mCloseSheet(overlayId, sheetId) {
    document.getElementById(sheetId).classList.remove('m-visible');
    document.getElementById(overlayId).classList.remove('m-visible');
}
function mOpenEditGoal(goalId) {
    fetch('process_goals.php?action=get_goal&goal_id=' + goalId)
        .then(function(r) { return r.json(); })
        .then(function(g) {
            document.getElementById('mEditGoalId').value = g.id || goalId;
            document.getElementById('mEditTitle').value = g.title || g.goal_title || '';
            document.getElementById('mEditDescription').value = g.description || '';
            document.getElementById('mEditCategory').value = g.category || 'general';
            document.getElementById('mEditStatus').value = g.status || 'active';
            document.getElementById('mEditTargetValue').value = g.target_value || '';
            document.getElementById('mEditCurrentValue').value = g.current_value || '';
            document.getElementById('mEditTargetDate').value = g.target_date || '';
            mOpenSheet('mEditOverlay', 'mEditSheet');
        })
        .catch(function() {
            document.getElementById('mEditGoalId').value = goalId;
            mOpenSheet('mEditOverlay', 'mEditSheet');
        });
}
function mOpenProgressGoal(goalId) {
    document.getElementById('mProgressGoalId').value = goalId;
    mOpenSheet('mProgressOverlay', 'mProgressSheet');
}
</script>
