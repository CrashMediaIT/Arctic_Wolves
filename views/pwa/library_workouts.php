<?php
/**
 * PWA Workout Library - Mobile-native workout library
 * Purpose-built for mobile phones.
 */

// Permission check — match desktop views/library_workouts.php
if (!in_array($user_role, ['health_coach', 'coach', 'coach_plus', 'admin'])) {
    echo '<div style="text-align:center;padding:60px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:48px;display:block;margin-bottom:16px;opacity:0.5;"></i>';
    echo '<h3 style="color:#fff;">Access Denied</h3>';
    echo '<p style="font-size:14px;">Health coach, coach, or admin access required.</p>';
    echo '</div>';
    return;
}

$workouts = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, description, difficulty_level, duration_minutes
        FROM workouts
        WHERE is_active = 1
        ORDER BY title
        LIMIT 30
    ");
    $stmt->execute();
    $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $workouts = []; }

$allAthletes = [];
try {
    $stmt2 = $pdo->query("SELECT id, first_name, last_name FROM users WHERE is_active = 1 AND role = 'athlete' ORDER BY first_name, last_name");
    $allAthletes = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) $allAthletes = decryptUserRows($allAthletes);
} catch (PDOException $e) { $allAthletes = []; }

$totalWorkouts = count($workouts);
?>
<style>
.m-libworkouts { padding: 16px; padding-bottom: 80px; font-family: Inter, sans-serif; }
.m-libworkouts-header { margin-bottom: 16px; }
.m-libworkouts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-libworkouts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-workout-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-workout-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-workout-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-workout-diff {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-workout-diff-easy { background: rgba(16,185,129,0.15); color: #10B981; }
.m-workout-diff-medium { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-workout-diff-hard { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-workout-diff-default { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-workout-desc { font-size: 12px; color: #A8A8B8; margin: 4px 0 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-workout-meta { font-size: 12px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-workout-actions { display: flex; gap: 8px; margin-top: 10px; }
.m-workout-action-btn {
    font-size: 12px; padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer;
    font-weight: 600; font-family: Inter, sans-serif; display: inline-flex; align-items: center; gap: 4px;
}
.m-workout-btn-edit { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-workout-btn-del { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-workout-btn-assign { background: rgba(16,185,129,0.15); color: #10B981; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-lw-fab {
    position: fixed; bottom: 60px; right: 20px; width: 56px; height: 56px;
    background: #6B46C1; color: #fff; border: none; border-radius: 50%;
    font-size: 24px; cursor: pointer; z-index: 999;
    box-shadow: 0 4px 12px rgba(107,70,193,0.4);
    display: flex; align-items: center; justify-content: center;
}
.m-lw-overlay {
    position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 1000; display: none;
}
.m-lw-overlay.active { display: block; }
.m-lw-sheet {
    position: fixed; bottom: 0; left: 0; right: 0; z-index: 1001;
    background: #16161F; border-radius: 16px 16px 0 0; max-height: 85vh;
    overflow-y: auto; transform: translateY(100%); transition: transform 0.3s ease;
    padding: 20px 16px 32px;
}
.m-lw-sheet.active { transform: translateY(0); }
.m-lw-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-lw-field label {
    font-size: 13px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px; display: block;
}
.m-lw-field { margin-bottom: 14px; }
.m-lw-field input, .m-lw-field select, .m-lw-field textarea {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #fff;
    padding: 12px; min-height: 44px; width: 100%; box-sizing: border-box;
    font-family: Inter, sans-serif; font-size: 14px;
}
.m-lw-field textarea { min-height: 80px; resize: vertical; }
.m-lw-submit {
    background: #6B46C1; color: #fff; border-radius: 10px; min-height: 44px;
    font-weight: 600; width: 100%; border: none; cursor: pointer;
    font-family: Inter, sans-serif; font-size: 15px; margin-top: 8px;
}
</style>

<div class="m-libworkouts">
    <div class="m-libworkouts-header">
        <h2 class="m-libworkouts-title">Workout Library</h2>
        <p class="m-libworkouts-sub"><?= $totalWorkouts ?> workout<?= $totalWorkouts !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($workouts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-dumbbell"></i>
            <p>No workouts available</p>
        </div>
    <?php else: ?>
        <?php foreach ($workouts as $w):
            $diff = strtolower($w['difficulty_level'] ?? 'medium');
            $diffClass = match($diff) {
                'easy', 'beginner' => 'easy',
                'medium', 'intermediate' => 'medium',
                'hard', 'advanced' => 'hard',
                default => 'default',
            };
        ?>
        <div class="m-workout-card">
            <div class="m-workout-top">
                <span class="m-workout-name"><?= htmlspecialchars($w['title']) ?></span>
                <span class="m-workout-diff m-workout-diff-<?= $diffClass ?>"><?= htmlspecialchars(ucfirst($diff)) ?></span>
            </div>
            <?php if (!empty($w['description'])): ?>
            <div class="m-workout-desc"><?= htmlspecialchars($w['description']) ?></div>
            <?php endif; ?>
            <?php if (!empty($w['duration_minutes'])): ?>
            <div class="m-workout-meta">
                <i class="fas fa-clock"></i> <?= (int)$w['duration_minutes'] ?> min
            </div>
            <?php endif; ?>
            <div class="m-workout-actions">
                <button type="button" class="m-workout-action-btn m-workout-btn-edit" onclick="mLwEdit(<?= (int)$w['id'] ?>, <?= htmlspecialchars(json_encode($w['title']), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['description'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['difficulty_level'] ?? ''), ENT_QUOTES) ?>)"><i class="fas fa-pen"></i> Edit</button>
                <button type="button" class="m-workout-action-btn m-workout-btn-assign" onclick="mLwAssign(<?= (int)$w['id'] ?>, <?= htmlspecialchars(json_encode($w['title']), ENT_QUOTES) ?>)"><i class="fas fa-user-plus"></i> Assign</button>
                <button type="button" class="m-workout-action-btn m-workout-btn-del" onclick="mLwDelete(<?= (int)$w['id'] ?>)"><i class="fas fa-trash"></i></button>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<button type="button" class="m-lw-fab" onclick="mLwOpenCreate()"><i class="fas fa-plus"></i></button>

<div class="m-lw-overlay" id="mLwOverlay" onclick="mLwClose()"></div>
<div class="m-lw-sheet" id="mLwSheet">
    <h3 class="m-lw-sheet-title" id="mLwSheetTitle">Create Workout Template</h3>
    <form method="POST" action="process_workout.php" id="mLwForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" id="mLwAction" value="create_plan">
        <input type="hidden" name="id" id="mLwId" value="">
        <div class="m-lw-field">
            <label>Plan Name *</label>
            <input type="text" name="name" id="mLwName" required placeholder="e.g., Beginner Strength Program">
        </div>
        <div class="m-lw-field">
            <label>Difficulty Level</label>
            <select name="difficulty_level" id="mLwDifficulty">
                <option value="">Select Difficulty</option>
                <option value="beginner">Beginner</option>
                <option value="intermediate">Intermediate</option>
                <option value="advanced">Advanced</option>
            </select>
        </div>
        <div class="m-lw-field">
            <label>Description</label>
            <textarea name="description" id="mLwDesc" placeholder="Describe the workout plan"></textarea>
        </div>
        <button type="submit" class="m-lw-submit" id="mLwSubmitBtn">Create Workout Template</button>
    </form>
</div>

<!-- Assign Sheet -->
<div class="m-lw-overlay" id="mLwAssignOverlay" onclick="mLwCloseAssign()"></div>
<div class="m-lw-sheet" id="mLwAssignSheet">
    <h3 class="m-lw-sheet-title">Assign to Athlete</h3>
    <form method="POST" action="process_workout.php" id="mLwAssignForm">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="assign_athletes">
        <input type="hidden" name="workout_plan_id" id="mLwAssignPlanId">
        <div class="m-lw-field">
            <label>Workout Plan</label>
            <input type="text" id="mLwAssignPlanName" readonly>
        </div>
        <div class="m-lw-field">
            <label>Select Athlete *</label>
            <select name="athlete_ids[]" id="mLwAssignAthlete" required>
                <option value="">-- Select Athlete --</option>
                <?php foreach ($allAthletes as $ath): ?>
                <option value="<?= (int)$ath['id'] ?>"><?= htmlspecialchars($ath['first_name'] . ' ' . $ath['last_name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="m-lw-field">
            <label>Start Date</label>
            <input type="date" name="start_date" value="<?= date('Y-m-d') ?>">
        </div>
        <div class="m-lw-field">
            <label>Notes</label>
            <textarea name="notes" placeholder="Optional notes"></textarea>
        </div>
        <button type="submit" class="m-lw-submit">Assign Workout</button>
    </form>
</div>

<form id="mLwDeleteForm" method="POST" action="process_workout.php" style="display:none;">
    <?= csrfTokenInput() ?>
    <input type="hidden" name="action" value="delete_plan">
    <input type="hidden" name="id" id="mLwDeleteId">
</form>

<script>
function mLwOpenCreate() {
    document.getElementById('mLwSheetTitle').textContent = 'Create Workout Template';
    document.getElementById('mLwAction').value = 'create_plan';
    document.getElementById('mLwId').value = '';
    document.getElementById('mLwName').value = '';
    document.getElementById('mLwDifficulty').value = '';
    document.getElementById('mLwDesc').value = '';
    document.getElementById('mLwSubmitBtn').textContent = 'Create Workout Template';
    document.getElementById('mLwOverlay').classList.add('active');
    document.getElementById('mLwSheet').classList.add('active');
}
function mLwEdit(id, title, desc, diff) {
    document.getElementById('mLwSheetTitle').textContent = 'Edit Workout Template';
    document.getElementById('mLwAction').value = 'update_plan';
    document.getElementById('mLwId').value = id;
    document.getElementById('mLwName').value = title;
    document.getElementById('mLwDifficulty').value = (diff || '').toLowerCase();
    document.getElementById('mLwDesc').value = desc;
    document.getElementById('mLwSubmitBtn').textContent = 'Update Workout Template';
    document.getElementById('mLwOverlay').classList.add('active');
    document.getElementById('mLwSheet').classList.add('active');
}
async function mLwDelete(id) {
    if (await showConfirmModal('Delete this workout template?')) {
        document.getElementById('mLwDeleteId').value = id;
        document.getElementById('mLwDeleteForm').submit();
    }
}
function mLwClose() {
    document.getElementById('mLwOverlay').classList.remove('active');
    document.getElementById('mLwSheet').classList.remove('active');
}
function mLwAssign(id, name) {
    document.getElementById('mLwAssignPlanId').value = id;
    document.getElementById('mLwAssignPlanName').value = name;
    document.getElementById('mLwAssignOverlay').classList.add('active');
    document.getElementById('mLwAssignSheet').classList.add('active');
}
function mLwCloseAssign() {
    document.getElementById('mLwAssignOverlay').classList.remove('active');
    document.getElementById('mLwAssignSheet').classList.remove('active');
}
</script>
