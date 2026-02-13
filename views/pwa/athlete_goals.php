<?php
/**
 * PWA Athlete Goals - Mobile-native goals view for athletes
 * Purpose-built for mobile phones.
 */

$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(title, goal_title) as title, status, completion_percentage, target_date
        FROM goals
        WHERE athlete_id = ?
        ORDER BY status ASC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

$totalGoals = count($goals);
?>
<style>
.m-ath-goals { padding: 16px; font-family: Inter, sans-serif; }
.m-ath-goals-header { margin-bottom: 16px; }
.m-ath-goals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ath-goals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-agoal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-agoal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-agoal-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-agoal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-agoal-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-agoal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-agoal-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-agoal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-agoal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-agoal-progress { margin-bottom: 8px; }
.m-agoal-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-agoal-progress-label { font-size: 11px; color: #6B6B7B; }
.m-agoal-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-agoal-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-agoal-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-agoal-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-agoal-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-agoal-btn { border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center; }
.m-agoal-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-agoal-btn-progress { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-agoal-btn-delete { background: rgba(239,68,68,0.15); color: #EF4444; }
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
.m-form-delete { background: rgba(239,68,68,0.15); color: #EF4444; border-radius: 10px; min-height: 44px; font-weight: 600; width: 100%; border: none; cursor: pointer; font-family: Inter, sans-serif; font-size: 14px; margin-top: 8px; }
.m-form-row { display: flex; gap: 10px; }
.m-form-row .m-form-group { flex: 1; }
.m-form-help { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
</style>

<div class="m-ath-goals">
    <div class="m-ath-goals-header">
        <h2 class="m-ath-goals-title">My Goals</h2>
        <p class="m-ath-goals-sub"><?= $totalGoals ?> goal<?= $totalGoals !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($goals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullseye"></i>
            <p>No goals set yet</p>
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
        ?>
        <div class="m-agoal-card">
            <div class="m-agoal-top">
                <span class="m-agoal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                <span class="m-agoal-badge m-agoal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-agoal-progress">
                <div class="m-agoal-progress-header">
                    <span class="m-agoal-progress-label">Progress</span>
                    <span class="m-agoal-progress-pct"><?= $pct ?>%</span>
                </div>
                <div class="m-agoal-progress-bar">
                    <div class="m-agoal-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if (!empty($g['target_date'])): ?>
            <div class="m-agoal-date">
                <i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?>
            </div>
            <?php endif; ?>
            <div class="m-agoal-actions">
                <button class="m-agoal-btn m-agoal-btn-edit" onclick="mOpenEditGoal(<?= (int)$g['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                <button class="m-agoal-btn m-agoal-btn-progress" onclick="mOpenProgress(<?= (int)$g['id'] ?>)"><i class="fas fa-arrow-up"></i> Progress</button>
                <button class="m-agoal-btn m-agoal-btn-delete" onclick="mDeleteGoal(<?= (int)$g['id'] ?>)"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button class="m-fab" onclick="mOpenSheet('mAgoalCreateOv','mAgoalCreateSh')" aria-label="New goal"><i class="fas fa-plus"></i></button>

<!-- Create Goal Sheet -->
<div class="m-overlay" id="mAgoalCreateOv" onclick="mCloseSheet('mAgoalCreateOv','mAgoalCreateSh')"></div>
<div class="m-sheet" id="mAgoalCreateSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Create Goal</h3>
    <form method="POST" action="process_goals.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create">
        <div class="m-form-group">
            <label class="m-form-label">Goal Title</label>
            <input type="text" name="goal_title" class="m-form-input" required placeholder="Enter goal title">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="goal_description" class="m-form-textarea" placeholder="Describe your goal"></textarea>
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
                <input type="number" name="target_value" class="m-form-input" required placeholder="0">
            </div>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Target Date</label>
            <input type="date" name="target_date" class="m-form-input">
        </div>
        <button type="submit" class="m-form-submit">Create Goal</button>
    </form>
</div>

<!-- Edit Goal Sheet -->
<div class="m-overlay" id="mAgoalEditOv" onclick="mCloseSheet('mAgoalEditOv','mAgoalEditSh')"></div>
<div class="m-sheet" id="mAgoalEditSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Edit Goal</h3>
    <form method="POST" action="process_goals.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_goal">
        <input type="hidden" name="goal_id" id="mEditGoalId">
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="title" id="mEditTitle" class="m-form-input" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" id="mEditDesc" class="m-form-textarea"></textarea>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Category</label>
            <select name="category" id="mEditCat" class="m-form-select">
                <option value="">Select category</option>
                <option value="skating">Skating</option>
                <option value="shooting">Shooting</option>
                <option value="passing">Passing</option>
                <option value="stickhandling">Stickhandling</option>
                <option value="fitness">Fitness</option>
                <option value="mental">Mental</option>
                <option value="general">General</option>
            </select>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Target Value</label>
                <input type="number" name="target_value" id="mEditTarget" class="m-form-input">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Current Value</label>
                <input type="number" name="current_value" id="mEditCurrent" class="m-form-input">
            </div>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Target Date</label>
                <input type="date" name="target_date" id="mEditDate" class="m-form-input">
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
        <button type="submit" class="m-form-submit">Update Goal</button>
    </form>
</div>

<!-- Update Progress Sheet -->
<div class="m-overlay" id="mAgoalProgOv" onclick="mCloseSheet('mAgoalProgOv','mAgoalProgSh')"></div>
<div class="m-sheet" id="mAgoalProgSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Update Progress</h3>
    <form method="POST" action="process_goals.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="update_progress">
        <input type="hidden" name="goal_id" id="mProgGoalId">
        <div class="m-form-group">
            <label class="m-form-label">Current Value</label>
            <input type="number" name="current_value" class="m-form-input" required placeholder="0">
            <div class="m-form-help">Enter your current progress value</div>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Notes</label>
            <textarea name="notes" class="m-form-textarea" placeholder="Add progress notes"></textarea>
        </div>
        <button type="submit" class="m-form-submit">Update Progress</button>
    </form>
</div>

<!-- Delete Goal Form -->
<form id="mDeleteGoalForm" method="POST" action="process_goals.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="goal_id" id="mDeleteGoalId">
</form>

<script>
function mOpenSheet(ovId, shId) { document.getElementById(ovId).classList.add('m-visible'); document.getElementById(shId).classList.add('m-visible'); }
function mCloseSheet(ovId, shId) { document.getElementById(ovId).classList.remove('m-visible'); document.getElementById(shId).classList.remove('m-visible'); }
function mOpenEditGoal(id) {
    document.getElementById('mEditGoalId').value = id;
    fetch('process_goals.php?action=get_goal&goal_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(g) {
            document.getElementById('mEditTitle').value = g.title || g.goal_title || '';
            document.getElementById('mEditDesc').value = g.description || g.goal_description || '';
            document.getElementById('mEditCat').value = g.category || '';
            document.getElementById('mEditTarget').value = g.target_value || '';
            document.getElementById('mEditCurrent').value = g.current_value || '';
            document.getElementById('mEditDate').value = g.target_date || '';
            document.getElementById('mEditStatus').value = g.status || 'active';
            mOpenSheet('mAgoalEditOv', 'mAgoalEditSh');
        }).catch(function() { alert('Error loading goal'); });
}
function mOpenProgress(id) { document.getElementById('mProgGoalId').value = id; mOpenSheet('mAgoalProgOv', 'mAgoalProgSh'); }
function mDeleteGoal(id) { if (confirm('Delete this goal?')) { document.getElementById('mDeleteGoalId').value = id; document.getElementById('mDeleteGoalForm').submit(); } }
</script>
