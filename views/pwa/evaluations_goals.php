<?php
/**
 * PWA Evaluations & Goals - Mobile-native tab view for goals and evaluations
 * Purpose-built for mobile phones.
 */

$goalsUserId = $user_id;
if ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $goalsUserId = (int)$_SESSION['viewing_athlete_id'];
}

// Goals
$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(title, goal_title) as title, status, completion_percentage, target_date, category
        FROM goals
        WHERE athlete_id = ?
        ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$goalsUserId]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

// Evaluations
$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, ek.name as skill_name, ek.category
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 20
    ");
    $stmt->execute([$goalsUserId]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }
?>
<style>
.m-evalgoals { padding: 0; font-family: Inter, sans-serif; }
.m-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    padding: 0 16px;
}
.m-tab {
    flex: 1; text-align: center; padding: 14px 0; font-size: 14px; font-weight: 600;
    color: #6B6B7B; border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent;
    min-height: 44px; font-family: Inter, sans-serif;
}
.m-tab.m-tab-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-tab-panel { display: none; padding: 16px; }
.m-tab-panel.m-tab-visible { display: block; }
.m-eg-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-eg-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-eg-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-eg-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-eg-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-eg-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-eg-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-eg-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-eg-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-eg-badge-cat { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-eg-progress { margin-bottom: 8px; }
.m-eg-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-eg-progress-label { font-size: 11px; color: #6B6B7B; }
.m-eg-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-eg-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-eg-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-eg-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-eg-footer { display: flex; gap: 12px; align-items: center; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-eg-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-eg-btn { background: rgba(107,70,193,0.15); color: #8B5CF6; border: none; border-radius: 8px; padding: 8px 12px; font-size: 12px; font-weight: 600; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px; display: flex; align-items: center; gap: 4px; flex: 1; justify-content: center; }
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
.m-form-check { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #A8A8B8; min-height: 44px; }
.m-form-check input[type="checkbox"] { width: 18px; height: 18px; accent-color: #6B46C1; }
</style>

<div class="m-evalgoals">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mEGTab('goals', this)" type="button">Goals (<?= count($goals) ?>)</button>
        <button class="m-tab" onclick="mEGTab('evals', this)" type="button">Evaluations (<?= count($evaluations) ?>)</button>
    </div>

    <!-- Goals Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-goals">
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
            <div class="m-eg-card">
                <div class="m-eg-top">
                    <span class="m-eg-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                    <span class="m-eg-badge m-eg-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <div class="m-eg-progress">
                    <div class="m-eg-progress-header">
                        <span class="m-eg-progress-label">Progress</span>
                        <span class="m-eg-progress-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="m-eg-progress-bar">
                        <div class="m-eg-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <div class="m-eg-footer">
                    <?php if (!empty($g['target_date'])): ?>
                    <span class="m-eg-meta"><i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['category'])): ?>
                    <span class="m-eg-meta"><i class="fas fa-tag"></i> <?= htmlspecialchars($g['category']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="m-eg-actions">
                    <button class="m-eg-btn" onclick="mOpenEditEgGoal(<?= (int)$g['id'] ?>)"><i class="fas fa-edit"></i> Edit</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Evaluations Tab -->
    <div class="m-tab-panel" id="m-panel-evals">
        <?php if (empty($evaluations)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No evaluations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($evaluations as $ev):
                $score = (float)($ev['score'] ?? 0);
                $maxScore = (float)($ev['max_score'] ?? 10);
                $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            ?>
            <div class="m-eg-card">
                <div class="m-eg-top">
                    <span class="m-eg-title"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                    <?php if (!empty($ev['category'])): ?>
                    <span class="m-eg-badge m-eg-badge-cat"><?= htmlspecialchars($ev['category']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="m-eg-progress">
                    <div class="m-eg-progress-header">
                        <span class="m-eg-progress-label">Score</span>
                        <span class="m-eg-progress-pct"><?= $score ?> / <?= $maxScore ?></span>
                    </div>
                    <div class="m-eg-progress-bar">
                        <div class="m-eg-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <?php if (!empty($ev['evaluation_date'])): ?>
                <div class="m-eg-footer">
                    <span class="m-eg-meta"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<button class="m-fab" id="mEgFab" onclick="mEgFabAction()" aria-label="Create new"><i class="fas fa-plus"></i></button>

<!-- Create Goal Sheet -->
<div class="m-overlay" id="mEgGoalOv" onclick="mCloseSheet('mEgGoalOv','mEgGoalSh')"></div>
<div class="m-sheet" id="mEgGoalSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Create Goal</h3>
    <form method="POST" action="process_goals.php">
        <input type="hidden" name="action" value="create">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Title *</label>
            <input type="text" name="goal_title" class="m-form-input" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="goal_description" class="m-form-textarea"></textarea>
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
                <label class="m-form-label">Target Value *</label>
                <input type="number" name="target_value" class="m-form-input" required>
            </div>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Target Date</label>
            <input type="date" name="target_date" class="m-form-input">
        </div>
        <button type="submit" class="m-form-submit">Create Goal</button>
    </form>
</div>

<!-- Create Evaluation Sheet -->
<div class="m-overlay" id="mEgEvalOv" onclick="mCloseSheet('mEgEvalOv','mEgEvalSh')"></div>
<div class="m-sheet" id="mEgEvalSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Create Evaluation</h3>
    <form method="POST" action="process_evaluations.php">
        <input type="hidden" name="action" value="create_evaluation">
        <input type="hidden" name="athlete_id" value="<?= (int)$goalsUserId ?>">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Title *</label>
            <input type="text" name="title" class="m-form-input" required>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" class="m-form-textarea"></textarea>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Status</label>
            <select name="status" class="m-form-select">
                <option value="active">Active</option>
                <option value="completed">Completed</option>
                <option value="archived">Archived</option>
            </select>
        </div>
        <div class="m-form-check">
            <input type="checkbox" name="is_public" id="mEgIsPublic" value="1">
            <label for="mEgIsPublic">Make this evaluation public</label>
        </div>
        <button type="submit" class="m-form-submit">Create Evaluation</button>
    </form>
</div>

<!-- Edit Goal Sheet -->
<div class="m-overlay" id="mEgEditOv" onclick="mCloseSheet('mEgEditOv','mEgEditSh')"></div>
<div class="m-sheet" id="mEgEditSh">
    <div class="m-sheet-handle"></div>
    <h3 class="m-sheet-title">Edit Goal</h3>
    <form method="POST" action="process_goals.php">
        <input type="hidden" name="action" value="update_goal">
        <input type="hidden" name="goal_id" id="mEgEditGoalId">
        <?= csrfTokenInput() ?>
        <div class="m-form-group">
            <label class="m-form-label">Title</label>
            <input type="text" name="title" id="mEgEditTitle" class="m-form-input">
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Description</label>
            <textarea name="description" id="mEgEditDesc" class="m-form-textarea"></textarea>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Category</label>
                <select name="category" id="mEgEditCat" class="m-form-select">
                    <option value="">—</option>
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
                <select name="status" id="mEgEditStatus" class="m-form-select">
                    <option value="active">Active</option>
                    <option value="completed">Completed</option>
                    <option value="paused">Paused</option>
                </select>
            </div>
        </div>
        <div class="m-form-row">
            <div class="m-form-group">
                <label class="m-form-label">Target Value</label>
                <input type="number" name="target_value" id="mEgEditTarget" class="m-form-input">
            </div>
            <div class="m-form-group">
                <label class="m-form-label">Current Value</label>
                <input type="number" name="current_value" id="mEgEditCurrent" class="m-form-input">
            </div>
        </div>
        <div class="m-form-group">
            <label class="m-form-label">Target Date</label>
            <input type="date" name="target_date" id="mEgEditDate" class="m-form-input">
        </div>
        <button type="submit" class="m-form-submit">Update Goal</button>
    </form>
</div>

<script>
var mEgActiveTab = 'goals';
function mEGTab(tabId, btn) {
    mEgActiveTab = tabId;
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}

function mOpenSheet(ovId, shId) { document.getElementById(ovId).classList.add('m-visible'); document.getElementById(shId).classList.add('m-visible'); }
function mCloseSheet(ovId, shId) { document.getElementById(ovId).classList.remove('m-visible'); document.getElementById(shId).classList.remove('m-visible'); }

function mEgFabAction() {
    if (mEgActiveTab === 'goals') { mOpenSheet('mEgGoalOv', 'mEgGoalSh'); }
    else { mOpenSheet('mEgEvalOv', 'mEgEvalSh'); }
}

function mOpenEditEgGoal(id) {
    document.getElementById('mEgEditGoalId').value = id;
    fetch('process_goals.php?action=get_goal&goal_id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(g) {
            document.getElementById('mEgEditTitle').value = g.title || g.goal_title || '';
            document.getElementById('mEgEditDesc').value = g.description || g.goal_description || '';
            document.getElementById('mEgEditCat').value = g.category || '';
            document.getElementById('mEgEditTarget').value = g.target_value || '';
            document.getElementById('mEgEditCurrent').value = g.current_value || '';
            document.getElementById('mEgEditDate').value = g.target_date || '';
            document.getElementById('mEgEditStatus').value = g.status || 'active';
            mOpenSheet('mEgEditOv', 'mEgEditSh');
        }).catch(function(e) { showToast('Could not load goal data. Please try again.', 'error'); });
}
</script>
