<?php
/**
 * PWA Workouts - Mobile-native assigned workouts for user
 * Purpose-built for mobile phones.
 */

$is_coach = in_array(($user_role ?? ''), ['coach', 'coach_plus', 'admin']);

$workouts = [];
try {
    $stmt = $pdo->prepare("
        SELECT w.id, w.title, w.difficulty_level, w.duration_minutes, uw.status
        FROM user_workouts uw
        JOIN workouts w ON w.id = uw.workout_id
        WHERE uw.user_id = ?
        ORDER BY uw.assigned_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $workouts = []; }

$totalWorkouts = count($workouts);
?>
<style>
.m-workouts { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-workouts-header { margin-bottom: 16px; }
.m-workouts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-workouts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-wk-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-wk-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-wk-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-wk-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-wk-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-wk-badge-assigned { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-wk-badge-in_progress { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-wk-badge-skipped { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-wk-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-wk-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 10px; flex-wrap: wrap; }
.m-wk-meta i { font-size: 10px; }
.m-wk-diff-easy { color: #10B981; }
.m-wk-diff-medium { color: #F59E0B; }
.m-wk-diff-hard { color: #EF4444; }
.m-wk-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-wk-action-btn {
    font-size: 12px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer;
    font-weight: 600; font-family: Inter, sans-serif; display: inline-flex; align-items: center; gap: 4px;
}
.m-wk-action-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-wk-action-del { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-wk-fab {
    position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px;
    background: #6B46C1; color: #fff; border: none; border-radius: 50%;
    font-size: 24px; cursor: pointer; z-index: 999;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    display: flex; align-items: center; justify-content: center;
}
.m-wk-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none;
}
.m-wk-overlay.active { display: block; }
.m-wk-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh;
    overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease;
    padding: 20px 16px 32px;
}
.m-wk-sheet.active { transform: translateY(0); }
.m-wk-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-wk-field label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block;
}
.m-wk-field { margin-bottom: 14px; }
.m-wk-field input, .m-wk-field select, .m-wk-field textarea {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 14px;
}
.m-wk-field textarea { min-height: 80px; resize: vertical; }
.m-wk-submit {
    background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px;
    font-weight: 600; width: 100%; border: none; cursor: pointer;
    font-family: Inter, sans-serif; font-size: 15px; margin-top: 8px;
}
</style>

<div class="m-workouts">
    <div class="m-workouts-header">
        <h2 class="m-workouts-title">My Workouts</h2>
        <p class="m-workouts-sub"><?= $totalWorkouts ?> workout<?= $totalWorkouts !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($workouts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-dumbbell"></i>
            <p>No workouts assigned</p>
        </div>
    <?php else: ?>
        <?php foreach ($workouts as $w):
            $status = strtolower($w['status'] ?? 'assigned');
            $badgeClass = match($status) {
                'completed' => 'completed',
                'assigned', 'pending' => 'assigned',
                'in_progress' => 'in_progress',
                'skipped' => 'skipped',
                default => 'default',
            };
            $diff = strtolower($w['difficulty_level'] ?? 'medium');
            $diffColorClass = match($diff) {
                'easy', 'beginner' => 'easy',
                'medium', 'intermediate' => 'medium',
                'hard', 'advanced' => 'hard',
                default => 'medium',
            };
        ?>
        <div class="m-wk-card">
            <div class="m-wk-top">
                <span class="m-wk-name"><?= htmlspecialchars($w['title']) ?></span>
                <span class="m-wk-badge m-wk-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?></span>
            </div>
            <div class="m-wk-meta">
                <?php if (!empty($w['difficulty_level'])): ?>
                <span class="m-wk-diff-<?= $diffColorClass ?>"><i class="fas fa-signal"></i> <?= htmlspecialchars(ucfirst($diff)) ?></span>
                <?php endif; ?>
                <?php if (!empty($w['duration_minutes'])): ?>
                <span><i class="fas fa-clock"></i> <?= (int)$w['duration_minutes'] ?> min</span>
                <?php endif; ?>
            </div>
            <?php if ($is_coach): ?>
            <div class="m-wk-actions">
                <button type="button" class="m-wk-action-btn m-wk-action-edit" onclick="mWkEdit(<?= (int)$w['id'] ?>, <?= htmlspecialchars(json_encode($w['title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['difficulty_level'] ?? ''), ENT_QUOTES) ?>, <?= (int)($w['duration_minutes'] ?? 0) ?>)"><i class="fas fa-pen"></i> Edit</button>
                <button type="button" class="m-wk-action-btn m-wk-action-del" onclick="mWkDelete(<?= (int)$w['id'] ?>)"><i class="fas fa-trash"></i></button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if ($is_coach): ?>
<button type="button" class="m-wk-fab" onclick="mWkOpenCreate()"><i class="fas fa-plus"></i></button>

<div class="m-wk-overlay" id="mWkOverlay" onclick="mWkClose()"></div>
<div class="m-wk-sheet" id="mWkSheet">
    <h3 class="m-wk-sheet-title" id="mWkSheetTitle">Create Workout</h3>
    <form method="POST" action="process_workout.php" id="mWkForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mWkAction" value="create_plan">
        <input type="hidden" name="id" id="mWkId" value="">
        <div class="m-wk-field">
            <label>Workout Name *</label>
            <input type="text" name="name" id="mWkName" required placeholder="e.g., Beginner Strength Program">
        </div>
        <div class="m-wk-field">
            <label>Difficulty Level</label>
            <select name="difficulty_level" id="mWkDifficulty">
                <option value="">Select Difficulty</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
        </div>
        <div class="m-wk-field">
            <label>Description</label>
            <textarea name="description" id="mWkDesc" placeholder="Describe the workout plan goals"></textarea>
        </div>
        <button type="submit" class="m-wk-submit" id="mWkSubmitBtn">Create Workout</button>
    </form>
</div>

<form id="mWkDeleteForm" method="POST" action="process_workout.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_plan">
    <input type="hidden" name="id" id="mWkDeleteId">
</form>

<script>
function mWkOpenCreate() {
    document.getElementById('mWkSheetTitle').textContent = 'Create Workout';
    document.getElementById('mWkAction').value = 'create_plan';
    document.getElementById('mWkId').value = '';
    document.getElementById('mWkName').value = '';
    document.getElementById('mWkDifficulty').value = '';
    document.getElementById('mWkDesc').value = '';
    document.getElementById('mWkSubmitBtn').textContent = 'Create Workout';
    document.getElementById('mWkOverlay').classList.add('active');
    document.getElementById('mWkSheet').classList.add('active');
}
function mWkEdit(id, title, diff, dur) {
    document.getElementById('mWkSheetTitle').textContent = 'Edit Workout';
    document.getElementById('mWkAction').value = 'update_plan';
    document.getElementById('mWkId').value = id;
    document.getElementById('mWkName').value = title;
    document.getElementById('mWkDifficulty').value = (diff || '').toLowerCase();
    document.getElementById('mWkDesc').value = '';
    document.getElementById('mWkSubmitBtn').textContent = 'Update Workout';
    document.getElementById('mWkOverlay').classList.add('active');
    document.getElementById('mWkSheet').classList.add('active');
}
function mWkDelete(id) {
    if (confirm('Delete this workout?')) {
        document.getElementById('mWkDeleteId').value = id;
        document.getElementById('mWkDeleteForm').submit();
    }
}
function mWkClose() {
    document.getElementById('mWkOverlay').classList.remove('active');
    document.getElementById('mWkSheet').classList.remove('active');
}
</script>
<?php endif; ?>
