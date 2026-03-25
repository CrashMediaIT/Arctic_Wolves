<?php
/**
 * PWA Health - Mobile-native workouts and nutrition view
 * Purpose-built for mobile phones with full CRUD functionality.
 */

$canManageHealth = $canAccessHealthManagement;

if ($canManageHealth):
// --- Coach View Data ---
// Workouts
$workouts = [];
try {
    $stmt = $pdo->prepare("SELECT id, COALESCE(title, workout_name) AS title, description, workout_type, duration_minutes, user_id AS created_by FROM workouts ORDER BY created_at DESC LIMIT 30");
    $stmt->execute();
    $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $workouts = []; }

// Meal Plans
$mealPlans = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, target_calories AS calories, created_by FROM nutrition_plans ORDER BY created_at DESC LIMIT 30");
    $stmt->execute();
    $mealPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $mealPlans = []; }

else:
// --- Athlete View Data ---
$athleteId = (int)$user_id;

// Assigned workout plans with coach name
$assignedWorkouts = [];
try {
    $stmt = $pdo->prepare("
        SELECT awa.id AS assignment_id, awa.status, awa.assigned_date, awa.start_date, awa.notes AS assignment_notes,
               wp.id AS plan_id, wp.name, wp.description, wp.duration_weeks, wp.total_workouts, wp.difficulty_level,
               u.first_name AS coach_first, u.last_name AS coach_last
        FROM athlete_workout_assignments awa
        JOIN workout_plans wp ON wp.id = awa.workout_plan_id
        LEFT JOIN users u ON u.id = awa.assigned_by
        WHERE awa.athlete_id = ?
        ORDER BY FIELD(awa.status, 'active', 'paused', 'completed'), awa.assigned_date DESC
    ");
    $stmt->execute([$athleteId]);
    $assignedWorkouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $assignedWorkouts = decryptUserRows($assignedWorkouts);
    } elseif (class_exists('FieldEncryption')) {
        $assignedWorkouts = FieldEncryption::decryptRows($assignedWorkouts, ['coach_first', 'coach_last']);
    }
} catch (PDOException $e) { $assignedWorkouts = []; }

// Fetch exercises for each assigned workout plan
$workoutExercises = [];
try {
    $planIds = array_unique(array_column($assignedWorkouts, 'plan_id'));
    if (!empty($planIds)) {
        $placeholders = implode(',', array_fill(0, count($planIds), '?'));
        $stmt = $pdo->prepare("
            SELECT wpe.workout_plan_id, wpe.day_number, wpe.sets, wpe.reps, wpe.rest_seconds, wpe.notes, wpe.exercise_order,
                   el.name, el.muscle_group, el.equipment, el.difficulty_level AS exercise_difficulty
            FROM workout_plan_exercises wpe
            JOIN exercise_library el ON el.id = wpe.exercise_id
            WHERE wpe.workout_plan_id IN ($placeholders)
            ORDER BY wpe.day_number, wpe.exercise_order
        ");
        $stmt->execute($planIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ex) {
            $workoutExercises[(int)$ex['workout_plan_id']][] = $ex;
        }
    }
} catch (PDOException $e) { $workoutExercises = []; }

// Assigned nutrition plans with coach name
$assignedNutrition = [];
try {
    $stmt = $pdo->prepare("
        SELECT ana.id AS assignment_id, ana.status, ana.assigned_date, ana.start_date, ana.notes AS assignment_notes,
               np.id AS plan_id, COALESCE(np.name, np.title) AS name, COALESCE(np.description, np.content) AS description,
               np.target_calories, np.target_protein_g, np.target_carbs_g, np.target_fat_g,
               u.first_name AS coach_first, u.last_name AS coach_last
        FROM athlete_nutrition_assignments ana
        JOIN nutrition_plans np ON np.id = ana.nutrition_plan_id
        LEFT JOIN users u ON u.id = ana.assigned_by
        WHERE ana.athlete_id = ?
        ORDER BY FIELD(ana.status, 'active', 'paused', 'completed'), ana.assigned_date DESC
    ");
    $stmt->execute([$athleteId]);
    $assignedNutrition = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (function_exists('decryptUserRows')) {
        $assignedNutrition = decryptUserRows($assignedNutrition);
    } elseif (class_exists('FieldEncryption')) {
        $assignedNutrition = FieldEncryption::decryptRows($assignedNutrition, ['coach_first', 'coach_last']);
    }
} catch (PDOException $e) { $assignedNutrition = []; }

// Fetch meals and foods for each assigned nutrition plan
$nutritionMeals = [];
try {
    $nPlanIds = array_unique(array_column($assignedNutrition, 'plan_id'));
    if (!empty($nPlanIds)) {
        $placeholders = implode(',', array_fill(0, count($nPlanIds), '?'));
        $stmt = $pdo->prepare("
            SELECT npm.id AS meal_id, npm.nutrition_plan_id, npm.meal_type, npm.day_number, npm.meal_order,
                   fl.name AS food_name, fl.calories, fl.protein_g, fl.carbs_g, fl.fat_g, fl.serving_size,
                   npmf.serving_quantity, npmf.notes AS food_notes
            FROM nutrition_plan_meals npm
            LEFT JOIN nutrition_plan_meal_foods npmf ON npmf.meal_id = npm.id
            LEFT JOIN food_library fl ON fl.id = npmf.food_id
            WHERE npm.nutrition_plan_id IN ($placeholders)
            ORDER BY npm.day_number, npm.meal_order, fl.name
        ");
        $stmt->execute($nPlanIds);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $meal) {
            $nutritionMeals[(int)$meal['nutrition_plan_id']][] = $meal;
        }
    }
} catch (PDOException $e) { $nutritionMeals = []; }

endif;
?>
<style>
.m-health { padding: 0; font-family: Inter, sans-serif; }
.m-segment-control {
    display: flex; background: #1E1E2E; border-radius: 12px; padding: 4px;
    margin: 0 16px 16px; position: relative; border: 1px solid #2D2D3F;
}
.m-segment {
    flex: 1; padding: 10px 12px; border: none; background: transparent;
    color: #A8A8B8; font-size: 13px; font-weight: 600; font-family: inherit;
    cursor: pointer; border-radius: 10px; display: flex; align-items: center;
    justify-content: center; gap: 6px; z-index: 1; transition: color 0.2s;
    min-height: 44px; -webkit-tap-highlight-color: transparent;
}
.m-segment i { font-size: 14px; }
.m-segment-active {
    color: #fff; background: #6B46C1;
    box-shadow: 0 2px 8px rgba(107,70,193,0.3);
}
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

/* Athlete health view styles */
.m-health-assigned-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    margin-bottom: 10px; overflow: hidden; transition: border-color 0.2s;
}
.m-health-assigned-card.m-health-expanded { border-color: #6B46C1; }
.m-health-card-header {
    padding: 14px; cursor: pointer; display: flex; flex-direction: column; gap: 8px;
    min-height: 44px; -webkit-tap-highlight-color: transparent;
}
.m-health-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; }
.m-health-plan-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; }
.m-health-status-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; text-transform: capitalize; flex-shrink: 0;
}
.m-health-status-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-health-status-completed { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-health-status-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-health-card-meta {
    display: flex; flex-wrap: wrap; gap: 10px; align-items: center; font-size: 11px; color: #6B6B7B;
}
.m-health-card-meta i { font-size: 10px; }
.m-health-card-desc {
    font-size: 12px; color: #A8A8B8; margin: 0; padding: 0 14px 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.m-health-expand-icon {
    color: #6B6B7B; font-size: 12px; transition: transform 0.2s; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
}
.m-health-expanded .m-health-expand-icon { transform: rotate(180deg); }
.m-health-card-body {
    display: none; padding: 0 14px 14px; border-top: 1px solid #2D2D3F;
}
.m-health-expanded .m-health-card-body { display: block; }
.m-health-day-group { margin-top: 12px; }
.m-health-day-label {
    font-size: 11px; font-weight: 600; color: #8B5CF6; text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 8px;
}
.m-health-exercise-item {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    padding: 10px 12px; margin-bottom: 6px;
}
.m-health-exercise-name { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-health-exercise-details {
    display: flex; flex-wrap: wrap; gap: 8px; font-size: 11px; color: #A8A8B8;
}
.m-health-exercise-details span { display: inline-flex; align-items: center; gap: 3px; }
.m-health-exercise-notes { font-size: 11px; color: #6B6B7B; margin-top: 4px; font-style: italic; }
.m-health-meal-group { margin-top: 12px; }
.m-health-meal-type-label {
    font-size: 11px; font-weight: 600; color: #8B5CF6; text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;
}
.m-health-food-item {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 8px;
    padding: 10px 12px; margin-bottom: 6px;
}
.m-health-food-name { font-size: 13px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-health-food-macros {
    display: flex; flex-wrap: wrap; gap: 8px; font-size: 11px; color: #A8A8B8;
}
.m-health-food-macros span { display: inline-flex; align-items: center; gap: 3px; }
.m-health-macro-highlight { color: #10B981; font-weight: 600; }
.m-health-plan-targets {
    display: flex; flex-wrap: wrap; gap: 6px; margin-top: 10px; padding-top: 10px;
    border-top: 1px solid #2D2D3F;
}
.m-health-target-chip {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.1); color: #8B5CF6;
}
.m-health-assignment-note {
    font-size: 11px; color: #A8A8B8; margin-top: 10px; padding: 8px 10px;
    background: rgba(107,70,193,0.05); border-radius: 8px; border-left: 3px solid #6B46C1;
}
.m-health-no-exercises {
    font-size: 12px; color: #6B6B7B; text-align: center; padding: 16px 0;
}
</style>

<?= csrfTokenInput() ?>

<?php if ($canManageHealth): ?>
<div class="m-health">
    <div class="m-segment-control">
        <button class="m-segment m-segment-active" data-panel="workouts" aria-pressed="true">
            <i class="fas fa-dumbbell"></i> Workouts
        </button>
        <button class="m-segment" data-panel="nutrition" aria-pressed="false">
            <i class="fas fa-utensils"></i> Nutrition
        </button>
        <div class="m-segment-slider"></div>
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
                $diff = strtolower($w['workout_type'] ?? 'default');
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
                        <button onclick="mEditWorkout(<?= (int)$w['id'] ?>, <?= htmlspecialchars(json_encode($w['title'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['description'] ?? ''), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($w['workout_type'] ?? ''), ENT_QUOTES) ?>, <?= (int)($w['duration_minutes'] ?? 0) ?>)" title="Edit"><i class="fas fa-pen"></i></button>
                        <button class="m-del-btn" onclick="mDeleteWorkout(<?= (int)$w['id'] ?>)" title="Delete"><i class="fas fa-trash"></i></button>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($w['description'])): ?>
                <p class="m-workout-desc"><?= htmlspecialchars($w['description']) ?></p>
                <?php endif; ?>
                <div class="m-workout-footer">
                    <?php if (!empty($w['workout_type'])): ?>
                    <span class="m-workout-badge m-workout-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($w['workout_type'])) ?></span>
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

    <button class="m-fab" id="mHealthFab" onclick="mOpenCreateModal()" title="Create"><i class="fas fa-plus"></i></button>
</div>
<?php else: ?>
<!-- ============================================================ -->
<!-- ATHLETE VIEW — assigned workout & nutrition plans (read-only) -->
<!-- ============================================================ -->
<div class="m-health">
    <div class="m-segment-control">
        <button class="m-segment m-segment-active" data-panel="workouts" aria-pressed="true">
            <i class="fas fa-dumbbell"></i> Workouts
        </button>
        <button class="m-segment" data-panel="nutrition" aria-pressed="false">
            <i class="fas fa-utensils"></i> Nutrition
        </button>
        <div class="m-segment-slider"></div>
    </div>

    <!-- Assigned Workouts Tab -->
    <div class="m-tab-panel m-tab-visible" id="m-panel-workouts">
        <?php if (empty($assignedWorkouts)): ?>
            <div class="m-empty-state">
                <i class="fas fa-dumbbell"></i>
                <p>No workout plans assigned yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($assignedWorkouts as $aw):
                $statusClass = match($aw['status'] ?? 'active') {
                    'active' => 'active',
                    'completed' => 'completed',
                    'paused' => 'paused',
                    default => 'active',
                };
                $diffLevel = strtolower($aw['difficulty_level'] ?? '');
                $diffBadge = match($diffLevel) {
                    'beginner', 'easy' => 'beginner',
                    'intermediate', 'medium' => 'intermediate',
                    'advanced', 'hard' => 'advanced',
                    default => '',
                };
                $coachName = trim(($aw['coach_first'] ?? '') . ' ' . ($aw['coach_last'] ?? ''));
                $exercises = $workoutExercises[(int)$aw['plan_id']] ?? [];
                $exercisesByDay = [];
                foreach ($exercises as $ex) {
                    $exercisesByDay[(int)($ex['day_number'] ?? 1)][] = $ex;
                }
                ksort($exercisesByDay);
            ?>
            <div class="m-health-assigned-card" data-card-id="aw-<?= (int)$aw['assignment_id'] ?>">
                <div class="m-health-card-header" onclick="mToggleHealthCard(this)">
                    <div class="m-health-card-top">
                        <span class="m-health-plan-name"><?= htmlspecialchars($aw['name'] ?? 'Untitled Plan') ?></span>
                        <span class="m-health-status-badge m-health-status-<?= $statusClass ?>"><?= htmlspecialchars($aw['status'] ?? 'active') ?></span>
                        <span class="m-health-expand-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="m-health-card-meta">
                        <?php if ($coachName): ?>
                        <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($coachName) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($aw['assigned_date'])): ?>
                        <span><i class="fas fa-calendar"></i> <?= htmlspecialchars(date('M j, Y', strtotime($aw['assigned_date']))) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($aw['duration_weeks'])): ?>
                        <span><i class="fas fa-clock"></i> <?= (int)$aw['duration_weeks'] ?> weeks</span>
                        <?php endif; ?>
                        <?php if ($diffLevel && $diffBadge): ?>
                        <span class="m-workout-badge m-workout-badge-<?= $diffBadge ?>"><?= htmlspecialchars(ucfirst($diffLevel)) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($aw['description'])): ?>
                <p class="m-health-card-desc"><?= htmlspecialchars($aw['description']) ?></p>
                <?php endif; ?>
                <div class="m-health-card-body">
                    <?php if (!empty($aw['assignment_notes'])): ?>
                    <div class="m-health-assignment-note"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($aw['assignment_notes']) ?></div>
                    <?php endif; ?>
                    <?php if (empty($exercises)): ?>
                    <p class="m-health-no-exercises">No exercises added to this plan yet</p>
                    <?php else: ?>
                        <?php foreach ($exercisesByDay as $dayNum => $dayExercises): ?>
                        <div class="m-health-day-group">
                            <div class="m-health-day-label">Day <?= (int)$dayNum ?></div>
                            <?php foreach ($dayExercises as $ex): ?>
                            <div class="m-health-exercise-item">
                                <div class="m-health-exercise-name"><?= htmlspecialchars($ex['name'] ?? '') ?></div>
                                <div class="m-health-exercise-details">
                                    <?php if (!empty($ex['sets'])): ?>
                                    <span><i class="fas fa-layer-group"></i> <?= (int)$ex['sets'] ?> sets</span>
                                    <?php endif; ?>
                                    <?php if (!empty($ex['reps'])): ?>
                                    <span><i class="fas fa-redo"></i> <?= htmlspecialchars($ex['reps']) ?> reps</span>
                                    <?php endif; ?>
                                    <?php if (!empty($ex['rest_seconds'])): ?>
                                    <span><i class="fas fa-hourglass-half"></i> <?= (int)$ex['rest_seconds'] ?>s rest</span>
                                    <?php endif; ?>
                                    <?php if (!empty($ex['muscle_group'])): ?>
                                    <span><i class="fas fa-bullseye"></i> <?= htmlspecialchars($ex['muscle_group']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($ex['notes'])): ?>
                                <div class="m-health-exercise-notes"><?= htmlspecialchars($ex['notes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Assigned Nutrition Tab -->
    <div class="m-tab-panel" id="m-panel-nutrition">
        <?php if (empty($assignedNutrition)): ?>
            <div class="m-empty-state">
                <i class="fas fa-utensils"></i>
                <p>No nutrition plans assigned yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($assignedNutrition as $an):
                $statusClass = match($an['status'] ?? 'active') {
                    'active' => 'active',
                    'completed' => 'completed',
                    'paused' => 'paused',
                    default => 'active',
                };
                $coachName = trim(($an['coach_first'] ?? '') . ' ' . ($an['coach_last'] ?? ''));
                $meals = $nutritionMeals[(int)$an['plan_id']] ?? [];
                // Group meals by meal_type, then by day
                $mealsByType = [];
                foreach ($meals as $m) {
                    $type = $m['meal_type'] ?? 'other';
                    $mealsByType[$type][] = $m;
                }
                $mealTypeOrder = ['breakfast', 'lunch', 'dinner', 'snack', 'pre_workout', 'post_workout'];
                $mealTypeLabels = [
                    'breakfast' => 'Breakfast', 'lunch' => 'Lunch', 'dinner' => 'Dinner',
                    'snack' => 'Snack', 'pre_workout' => 'Pre-Workout', 'post_workout' => 'Post-Workout',
                ];
                $mealTypeIcons = [
                    'breakfast' => 'fa-sun', 'lunch' => 'fa-cloud-sun', 'dinner' => 'fa-moon',
                    'snack' => 'fa-apple-alt', 'pre_workout' => 'fa-bolt', 'post_workout' => 'fa-battery-full',
                ];
            ?>
            <div class="m-health-assigned-card" data-card-id="an-<?= (int)$an['assignment_id'] ?>">
                <div class="m-health-card-header" onclick="mToggleHealthCard(this)">
                    <div class="m-health-card-top">
                        <span class="m-health-plan-name"><?= htmlspecialchars($an['name'] ?? 'Untitled Plan') ?></span>
                        <span class="m-health-status-badge m-health-status-<?= $statusClass ?>"><?= htmlspecialchars($an['status'] ?? 'active') ?></span>
                        <span class="m-health-expand-icon"><i class="fas fa-chevron-down"></i></span>
                    </div>
                    <div class="m-health-card-meta">
                        <?php if ($coachName): ?>
                        <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($coachName) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($an['assigned_date'])): ?>
                        <span><i class="fas fa-calendar"></i> <?= htmlspecialchars(date('M j, Y', strtotime($an['assigned_date']))) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($an['target_calories'])): ?>
                        <span><i class="fas fa-fire"></i> <?= (int)$an['target_calories'] ?> cal</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($an['description'])): ?>
                <p class="m-health-card-desc"><?= htmlspecialchars($an['description']) ?></p>
                <?php endif; ?>
                <div class="m-health-card-body">
                    <?php if (!empty($an['assignment_notes'])): ?>
                    <div class="m-health-assignment-note"><i class="fas fa-sticky-note"></i> <?= htmlspecialchars($an['assignment_notes']) ?></div>
                    <?php endif; ?>
                    <?php
                    $hasTargets = !empty($an['target_calories']) || !empty($an['target_protein_g']) || !empty($an['target_carbs_g']) || !empty($an['target_fat_g']);
                    if ($hasTargets): ?>
                    <div class="m-health-plan-targets">
                        <?php if (!empty($an['target_calories'])): ?>
                        <span class="m-health-target-chip"><i class="fas fa-fire"></i> <?= (int)$an['target_calories'] ?> cal</span>
                        <?php endif; ?>
                        <?php if (!empty($an['target_protein_g'])): ?>
                        <span class="m-health-target-chip"><i class="fas fa-drumstick-bite"></i> <?= (int)$an['target_protein_g'] ?>g protein</span>
                        <?php endif; ?>
                        <?php if (!empty($an['target_carbs_g'])): ?>
                        <span class="m-health-target-chip"><i class="fas fa-bread-slice"></i> <?= (int)$an['target_carbs_g'] ?>g carbs</span>
                        <?php endif; ?>
                        <?php if (!empty($an['target_fat_g'])): ?>
                        <span class="m-health-target-chip"><i class="fas fa-cheese"></i> <?= (int)$an['target_fat_g'] ?>g fat</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (empty($meals)): ?>
                    <p class="m-health-no-exercises">No meals added to this plan yet</p>
                    <?php else: ?>
                        <?php foreach ($mealTypeOrder as $type):
                            if (!isset($mealsByType[$type])) continue;
                            $typeFoods = $mealsByType[$type];
                            $icon = $mealTypeIcons[$type] ?? 'fa-utensils';
                            $label = $mealTypeLabels[$type] ?? ucfirst(str_replace('_', ' ', $type));
                        ?>
                        <div class="m-health-meal-group">
                            <div class="m-health-meal-type-label"><i class="fas <?= $icon ?>"></i> <?= htmlspecialchars($label) ?></div>
                            <?php foreach ($typeFoods as $food):
                                if (empty($food['food_name'])) continue;
                            ?>
                            <div class="m-health-food-item">
                                <div class="m-health-food-name"><?= htmlspecialchars($food['food_name']) ?></div>
                                <div class="m-health-food-macros">
                                    <?php if (!empty($food['calories'])): ?>
                                    <span class="m-health-macro-highlight"><i class="fas fa-fire"></i> <?= (int)$food['calories'] ?> cal</span>
                                    <?php endif; ?>
                                    <?php if (!empty($food['protein_g'])): ?>
                                    <span><i class="fas fa-drumstick-bite"></i> <?= number_format((float)$food['protein_g'], 1) ?>g P</span>
                                    <?php endif; ?>
                                    <?php if (!empty($food['carbs_g'])): ?>
                                    <span><i class="fas fa-bread-slice"></i> <?= number_format((float)$food['carbs_g'], 1) ?>g C</span>
                                    <?php endif; ?>
                                    <?php if (!empty($food['fat_g'])): ?>
                                    <span><i class="fas fa-cheese"></i> <?= number_format((float)$food['fat_g'], 1) ?>g F</span>
                                    <?php endif; ?>
                                    <?php if (!empty($food['serving_quantity']) && !empty($food['serving_size'])): ?>
                                    <span><i class="fas fa-balance-scale"></i> <?= number_format((float)$food['serving_quantity'], 1) ?> × <?= htmlspecialchars($food['serving_size']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php if (!empty($food['food_notes'])): ?>
                                <div class="m-health-exercise-notes"><?= htmlspecialchars($food['food_notes']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                        <?php
                        // Show any meal types not in the standard order
                        foreach ($mealsByType as $type => $typeFoods):
                            if (in_array($type, $mealTypeOrder)) continue;
                            $label = ucfirst(str_replace('_', ' ', $type));
                        ?>
                        <div class="m-health-meal-group">
                            <div class="m-health-meal-type-label"><i class="fas fa-utensils"></i> <?= htmlspecialchars($label) ?></div>
                            <?php foreach ($typeFoods as $food):
                                if (empty($food['food_name'])) continue;
                            ?>
                            <div class="m-health-food-item">
                                <div class="m-health-food-name"><?= htmlspecialchars($food['food_name']) ?></div>
                                <div class="m-health-food-macros">
                                    <?php if (!empty($food['calories'])): ?>
                                    <span class="m-health-macro-highlight"><i class="fas fa-fire"></i> <?= (int)$food['calories'] ?> cal</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($canManageHealth): ?>
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
<?php endif; ?>

<script>
<?php if ($canManageHealth): ?>
var mCurrentHealthTab = 'workouts';
var mCsrf = document.querySelector('[name="csrf_token"]') ? document.querySelector('[name="csrf_token"]').value : '';

function mCheckCsrf() {
    if (!mCsrf) { showToast('Session expired. Please refresh the page.', 'error'); return false; }
    return true;
}

document.querySelectorAll('.m-segment-control .m-segment').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var control = this.closest('.m-segment-control');
        control.querySelectorAll('.m-segment').forEach(function(s) {
            s.classList.remove('m-segment-active');
            s.setAttribute('aria-pressed', 'false');
        });
        this.classList.add('m-segment-active');
        this.setAttribute('aria-pressed', 'true');
        var panelId = this.getAttribute('data-panel');
        mCurrentHealthTab = panelId;
        document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
        var target = document.getElementById('m-panel-' + panelId);
        if (target) target.classList.add('m-tab-visible');
    });
});

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
    if (!mCheckCsrf()) return;
    var id = document.getElementById('mWorkoutId').value;
    var title = document.getElementById('mWorkoutTitle').value.trim();
    if (!title) { showToast('Workout title is required', 'error'); return; }
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
        if (d.success) { mCloseModal('mWorkoutModal'); persistToast(d.message || 'Operation completed successfully', 'success'); window.location.href = window.location.href; }
        else { showToast(d.message || 'Error saving workout', 'error'); }
    }).catch(function() { showToast('Network error', 'error'); });
}

async function mDeleteWorkout(id) {
    if (!mCheckCsrf()) return;
    if (!await showConfirmModal('Delete this workout?')) return;
    fetch('process_workout.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams({ action: 'delete_plan', id: id, csrf_token: mCsrf })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { var el = document.getElementById('mw-' + id); if (el) el.remove(); showToast(d.message || 'Deleted', 'success'); }
        else { showToast(d.message || 'Error', 'error'); }
    }).catch(function() { showToast('Network error', 'error'); });
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
    if (!mCheckCsrf()) return;
    var id = document.getElementById('mMealId').value;
    var name = document.getElementById('mMealName').value.trim();
    if (!name) { showToast('Meal plan name is required', 'error'); return; }
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
        if (d.success) { mCloseModal('mMealModal'); persistToast(d.message || 'Operation completed successfully', 'success'); window.location.href = window.location.href; }
        else { showToast(d.message || 'Error saving meal plan', 'error'); }
    }).catch(function() { showToast('Network error', 'error'); });
}

async function mDeleteMeal(id) {
    if (!mCheckCsrf()) return;
    if (!await showConfirmModal('Delete this meal plan?')) return;
    fetch('process_nutrition.php', {
        method: 'POST', headers: {'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body: new URLSearchParams({ action: 'delete_plan', id: id, csrf_token: mCsrf })
    }).then(function(r) { return r.json(); }).then(function(d) {
        if (d.success) { var el = document.getElementById('mm-' + id); if (el) el.remove(); showToast(d.message || 'Deleted', 'success'); }
        else { showToast(d.message || 'Error', 'error'); }
    }).catch(function() { showToast('Network error', 'error'); });
}

// Close modals on overlay click
document.querySelectorAll('.m-modal-overlay').forEach(function(o) {
    o.addEventListener('click', function(e) { if (e.target === o) o.classList.remove('m-show'); });
});

<?php else: ?>
// --- Athlete view: tab switching and card expand/collapse ---
document.querySelectorAll('.m-segment-control .m-segment').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var control = this.closest('.m-segment-control');
        control.querySelectorAll('.m-segment').forEach(function(s) {
            s.classList.remove('m-segment-active');
            s.setAttribute('aria-pressed', 'false');
        });
        this.classList.add('m-segment-active');
        this.setAttribute('aria-pressed', 'true');
        var panelId = this.getAttribute('data-panel');
        document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
        var target = document.getElementById('m-panel-' + panelId);
        if (target) target.classList.add('m-tab-visible');
    });
});

function mToggleHealthCard(headerEl) {
    var card = headerEl.closest('.m-health-assigned-card');
    if (card) card.classList.toggle('m-health-expanded');
}
<?php endif; ?>
</script>
