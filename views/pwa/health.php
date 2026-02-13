<?php
/**
 * PWA Health - Mobile-native workouts and nutrition view
 * Purpose-built for mobile phones with full CRUD functionality.
 */

$canManageHealth = $isAnyCoach || $isAdmin || ($user_role === 'health_coach');

// Workouts
$workouts = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, description, difficulty_level, duration_minutes, created_by FROM workouts WHERE is_active = 1 ORDER BY created_at DESC LIMIT 30");
    $stmt->execute();
    $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $workouts = []; }

// Meal Plans
$mealPlans = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, calories, created_by FROM meal_plans WHERE is_active = 1 ORDER BY created_at DESC LIMIT 30");
    $stmt->execute();
    $mealPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $mealPlans = []; }
?>
<style>
.m-health { padding: 0; font-family: Inter, sans-serif; }
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
.m-workout-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-workout-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-workout-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; }
.m-workout-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-workout-footer { display: flex; gap: 10px; align-items: center; }
.m-workout-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-workout-badge-beginner { background: rgba(16,185,129,0.15); color: #10B981; }
.m-workout-badge-intermediate { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-workout-badge-advanced { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-workout-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-workout-dur { font-size: 12px; color: #6B6B7B; }
.m-workout-actions { display: flex; gap: 6px; flex-shrink: 0; }
.m-workout-actions button {
    width: 34px; height: 34px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #A8A8B8; font-size: 13px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; min-height: 34px;
}
.m-workout-actions button:active { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-workout-actions .m-del-btn:active { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-meal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-meal-top { display: flex; justify-content: space-between; align-items: flex-start; }
.m-meal-title { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 4px; flex: 1; }
.m-meal-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-meal-cal {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; color: #10B981; font-weight: 600;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}
/* Slide-up modal */
.m-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; }
.m-modal-overlay.m-show { display: flex; align-items: flex-end; }
.m-modal-sheet {
    width: 100%; max-height: 90vh; background: #16161F; border-radius: 16px 16px 0 0;
    padding: 20px; overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.m-modal-handle { width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px; margin: 0 auto 16px; }
.m-modal-title { font-size: 17px; font-weight: 700; color: #fff; margin-bottom: 16px; }
.m-modal-field { margin-bottom: 14px; }
.m-modal-field label { display: block; font-size: 12px; color: #A8A8B8; font-weight: 600; margin-bottom: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-modal-field input, .m-modal-field select, .m-modal-field textarea {
    width: 100%; padding: 12px; background: #0A0A0F; border: 1px solid #2D2D3F;
    border-radius: 10px; color: #fff; font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px;
}
.m-modal-field textarea { min-height: 80px; resize: vertical; }
.m-modal-field input:focus, .m-modal-field select:focus, .m-modal-field textarea:focus {
    outline: none; border-color: #6B46C1; box-shadow: 0 0 0 2px rgba(107,70,193,0.2);
}
.m-modal-actions { display: flex; gap: 10px; margin-top: 16px; }
.m-modal-actions button {
    flex: 1; padding: 14px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; font-family: Inter, sans-serif; min-height: 44px;
}
.m-modal-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
.m-modal-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
</style>

<?= csrfTokenInput() ?>

<div class="m-health">
    <div class="m-tabs">
        <button class="m-tab m-tab-active" onclick="mHealthTab('workouts', this)" type="button">Workouts</button>
        <button class="m-tab" onclick="mHealthTab('nutrition', this)" type="button">Nutrition</button>
    </div>

    <!-- Workouts Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-workouts">
        <?php if (empty($workouts)): ?>
            <div class="m-empty-state">
                <i class="fas fa-dumbbell"></i>
                <p>No workouts available</p>
            </div>
        <?php else: ?>
            <?php foreach ($workouts as $w):
                $diff = strtolower($w['difficulty_level'] ?? 'default');
                $badgeClass = match($diff) {
                    'beginner', 'easy' => 'beginner',
                    'intermediate', 'medium' => 'intermediate',
                    'advanced', 'hard' => 'advanced',
                    default => 'default',
                };
                $canEdit = $canManageHealth || ((int)($w['created_by'] ?? 0) === (int)$user_id);
            ?>
            <div class="m-workout-card" id="mw-<?= (int)$w['id'] ?>">
                <div class="m-workout-top">
                    <span class="m-workout-title"><?= htmlspecialchars($w['title'] ?? 'Untitled') ?></span>
                    <?php if ($canEdit): ?>
                    <div class="m-workout-actions">
                        <button onclick="mEditWorkout(<?= (int)$w['id'] ?>, <?= htmlspecialchars(json_encode($w['title'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['description'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['difficulty_level'] ?? ''), ENT_QUOTES) ?>, <?= (int)($w['duration_minutes'] ?? 0) ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="m-del-btn" onclick="mDeleteWorkout(<?= (int)$w['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($w['description'])): ?>
                <p class="m-workout-desc"><?= htmlspecialchars($w['description']) ?></p>
                <?php endif; ?>
                <div class="m-workout-footer">
                    <?php if (!empty($w['difficulty_level'])): ?>
                    <span class="m-workout-badge m-workout-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($w['difficulty_level'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($w['duration_minutes'])): ?>
                    <span class="m-workout-dur"><i class="fas fa-clock" style="font-size:10px;"></i> <?= (int)$w['duration_minutes'] ?> min</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Nutrition Tab -->
    <div class="m-tab-panel" id="m-panel-nutrition">
        <?php if (empty($mealPlans)): ?>
            <div class="m-empty-state">
                <i class="fas fa-utensils"></i>
                <p>No meal plans available</p>
            </div>
        <?php else: ?>
            <?php foreach ($mealPlans as $mp):
                $canEditMeal = $canManageHealth || ((int)($mp['created_by'] ?? 0) === (int)$user_id);
            ?>
            <div class="m-meal-card" id="mm-<?= (int)$mp['id'] ?>">
                <div class="m-meal-top">
                    <h4 class="m-meal-title"><?= htmlspecialchars($mp['name'] ?? 'Untitled') ?></h4>
                    <?php if ($canEditMeal): ?>
                    <div class="m-workout-actions">
                        <button onclick="mEditMeal(<?= (int)$mp['id'] ?>, <?= htmlspecialchars(json_encode($mp['name'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($mp['description'] ?? ''), ENT_QUOTES) ?>, <?= (int)($mp['calories'] ?? 0) ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="m-del-btn" onclick="mDeleteMeal(<?= (int)$mp['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($mp['description'])): ?>
                <p class="m-meal-desc"><?= htmlspecialchars($mp['description']) ?></p>
                <?php endif; ?>
                <?php if (!empty($mp['calories'])): ?>
                <span class="m-meal-cal"><i class="fas fa-fire"></i> <?= (int)$mp['calories'] ?> cal</span>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($canManageHealth): ?>
    <button class="m-fab" id="mHealthFab" onclick="mOpenCreateModal()" title="Create"><i class="fas fa-plus"></i></button>
    <?php endif; ?>
</div>

<!-- Create/Edit Workout Modal -->
<div class="m-modal-overlay" id="mWorkoutModal">
    <div class="m-modal-sheet">
        <div class="m-modal-handle"></div>
        <div class="m-modal-title" id="mWorkoutModalTitle">Create Workout</div>
        <input type="hidden" id="mWorkoutId" value="">
        <div class="m-modal-field"><label>Title *</label><input type="text" id="mWorkoutTitle" placeholder="Workout name" required></div>
        <div class="m-modal-field"><label>Description</label><textarea id="mWorkoutDesc" placeholder="Describe the workout..."></textarea></div>
        <div class="m-modal-field"><label>Difficulty</label>
            <select id="mWorkoutDiff">
                <option value="">Select</option>
                <option value="Beginner">Beginner</option>
                <option value="Intermediate">Intermediate</option>
                <option value="Advanced">Advanced</option>
            </select>
        </div>
        <div class="m-modal-field"><label>Duration (minutes)</label><input type="number" id="mWorkoutDur" placeholder="30" min="1"></div>
        <div class="m-modal-actions">
            <button class="m-modal-btn-cancel" onclick="mCloseModal('mWorkoutModal')">Cancel</button>
            <button class="m-modal-btn-save" onclick="mSaveWorkout()">Save</button>
        </div>
    </div>
</div>

<!-- Create/Edit Meal Plan Modal -->
<div class="m-modal-overlay" id="mMealModal">
    <div class="m-modal-sheet">
        <div class="m-modal-handle"></div>
        <div class="m-modal-title" id="mMealModalTitle">Create Meal Plan</div>
        <input type="hidden" id="mMealId" value="">
        <div class="m-modal-field"><label>Name *</label><input type="text" id="mMealName" placeholder="Meal plan name" required></div>
        <div class="m-modal-field"><label>Description</label><textarea id="mMealDesc" placeholder="Describe the plan..."></textarea></div>
        <div class="m-modal-field"><label>Calories</label><input type="number" id="mMealCal" placeholder="2000" min="0"></div>
        <div class="m-modal-actions">
            <button class="m-modal-btn-cancel" onclick="mCloseModal('mMealModal')">Cancel</button>
            <button class="m-modal-btn-save" onclick="mSaveMeal()">Save</button>
        </div>
    </div>
</div>

<script>
var mCurrentHealthTab = 'workouts';
var mCsrf = document.querySelector('[name="csrf_token"]') ? document.querySelector('[name="csrf_token"]').value : '';

function mHealthTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
    mCurrentHealthTab = tabId;
}

function mCloseModal(id) { document.getElementById(id).classList.remove('m-show'); }

function mOpenCreateModal() {
    if (mCurrentHealthTab === 'nutrition') {
        document.getElementById('mMealModalTitle').textContent = 'Create Meal Plan';
        document.getElementById('mMealId').value = '';
        document.getElementById('mMealName').value = '';
        document.getElementById('mMealDesc').value = '';
        document.getElementById('mMealCal').value = '';
        document.getElementById('mMealModal').classList.add('m-show');
    } else {
        document.getElementById('mWorkoutModalTitle').textContent = 'Create Workout';
        document.getElementById('mWorkoutId').value = '';
        document.getElementById('mWorkoutTitle').value = '';
        document.getElementById('mWorkoutDesc').value = '';
        document.getElementById('mWorkoutDiff').value = '';
        document.getElementById('mWorkoutDur').value = '';
        document.getElementById('mWorkoutModal').classList.add('m-show');
    }
}

function mEditWorkout(id, title, desc, diff, dur) {
    document.getElementById('mWorkoutModalTitle').textContent = 'Edit Workout';
    document.getElementById('mWorkoutId').value = id;
    document.getElementById('mWorkoutTitle').value = title;
    document.getElementById('mWorkoutDesc').value = desc;
    document.getElementById('mWorkoutDiff').value = diff;
    document.getElementById('mWorkoutDur').value = dur || '';
    document.getElementById('mWorkoutModal').classList.add('m-show');
}

function mSaveWorkout() {
    var id = document.getElementById('mWorkoutId').value;
    var title = document.getElementById('mWorkoutTitle').value.trim();
    if (!title) { alert('Title is required'); return; }
    var body = new URLSearchParams({
        action: id ? 'update_plan' : 'create_plan',
        csrf_token: mCsrf,
        title: title,
        description: document.getElementById('mWorkoutDesc').value.trim(),
        difficulty_level: document.getElementById('mWorkoutDiff').value,
        duration_minutes: document.getElementById('mWorkoutDur').value
    });
    if (id) body.set('id', id);
    fetch('process_workout.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body: body
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { mCloseModal('mWorkoutModal'); location.reload(); }
        else { alert(d.message || 'Error saving workout'); }
    }).catch(function() { alert('Network error'); });
}

function mDeleteWorkout(id) {
    if (!confirm('Delete this workout?')) return;
    fetch('process_workout.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams({ action: 'delete_plan', id: id, csrf_token: mCsrf })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { var el = document.getElementById('mw-' + id); if (el) el.remove(); }
        else { alert(d.message || 'Error'); }
    }).catch(function() { alert('Network error'); });
}

function mEditMeal(id, name, desc, cal) {
    document.getElementById('mMealModalTitle').textContent = 'Edit Meal Plan';
    document.getElementById('mMealId').value = id;
    document.getElementById('mMealName').value = name;
    document.getElementById('mMealDesc').value = desc;
    document.getElementById('mMealCal').value = cal || '';
    document.getElementById('mMealModal').classList.add('m-show');
}

function mSaveMeal() {
    var id = document.getElementById('mMealId').value;
    var name = document.getElementById('mMealName').value.trim();
    if (!name) { alert('Name is required'); return; }
    var body = new URLSearchParams({
        action: id ? 'update_plan' : 'create_plan',
        csrf_token: mCsrf,
        name: name,
        description: document.getElementById('mMealDesc').value.trim(),
        calories: document.getElementById('mMealCal').value
    });
    if (id) body.set('id', id);
    fetch('process_nutrition.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'}, body: body
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { mCloseModal('mMealModal'); location.reload(); }
        else { alert(d.message || 'Error saving meal plan'); }
    }).catch(function() { alert('Network error'); });
}

function mDeleteMeal(id) {
    if (!confirm('Delete this meal plan?')) return;
    fetch('process_nutrition.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams({ action: 'delete_plan', id: id, csrf_token: mCsrf })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { var el = document.getElementById('mm-' + id); if (el) el.remove(); }
        else { alert(d.message || 'Error'); }
    }).catch(function() { alert('Network error'); });
}

// Close modals on overlay click
document.querySelectorAll('.m-modal-overlay').forEach(function(o) {
    o.addEventListener('click', function(e) { if (e.target === o) o.classList.remove('m-show'); });
});
</script>
