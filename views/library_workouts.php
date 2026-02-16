<?php
/**
 * Strength & Conditioning Library
 * Three tabs: Exercise Library, Workout Plans, Create Workout Plan
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission to view library
if (!in_array($user_role, ['health_coach', 'coach', 'coach_plus', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Determine active tab
$activeTab = $_GET['tab'] ?? 'exercises';
$validTabs = ['exercises', 'plans', 'create'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'exercises';
}

// Fetch all exercises for Exercise Library tab
$exercises = $pdo->query("
    SELECT el.*, u.first_name, u.last_name 
    FROM exercise_library el
    LEFT JOIN users u ON el.created_by = u.id
    ORDER BY el.name ASC
")->fetchAll();
$exercises = decryptUserRows($exercises);

// Fetch all workout plans with assigned athlete count
$workoutPlans = $pdo->query("
    SELECT wp.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM workout_plan_exercises WHERE workout_plan_id = wp.id) as exercise_count,
           (SELECT COUNT(*) FROM athlete_workout_assignments WHERE workout_plan_id = wp.id AND status = 'active') as assigned_count
    FROM workout_plans wp
    LEFT JOIN users u ON wp.created_by = u.id
    ORDER BY wp.created_at DESC
")->fetchAll();
$workoutPlans = decryptUserRows($workoutPlans);

// For edit modal - fetch exercises for each plan
$planExercises = [];
foreach ($workoutPlans as $plan) {
    $stmt = $pdo->prepare("
        SELECT wpe.*, el.name as exercise_name, el.description as exercise_description
        FROM workout_plan_exercises wpe
        JOIN exercise_library el ON wpe.exercise_id = el.id
        WHERE wpe.workout_plan_id = ?
        ORDER BY wpe.exercise_order ASC
    ");
    $stmt->execute([$plan['id']]);
    $planExercises[$plan['id']] = $stmt->fetchAll();
}

// Fetch assigned athletes for each plan
$assignedAthletes = [];
foreach ($workoutPlans as $plan) {
    $stmt = $pdo->prepare("
        SELECT awa.*, u.first_name, u.last_name
        FROM athlete_workout_assignments awa
        JOIN users u ON awa.athlete_id = u.id
        WHERE awa.workout_plan_id = ? AND awa.status = 'active'
    ");
    $stmt->execute([$plan['id']]);
    $assignedAthletes[$plan['id']] = decryptUserRows($stmt->fetchAll());
}

// Fetch all active users for assignment modal (all roles can receive workout assignments)
$allAthletes = $pdo->query("
    SELECT id, first_name, last_name, role 
    FROM users 
    WHERE is_active = 1 
    ORDER BY last_name, first_name
")->fetchAll();
$allAthletes = decryptUserRows($allAthletes);
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-dumbbell"></i> Strength & Conditioning</h1>
        <p class="page-description">Manage exercises, workout plans, and user assignments</p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="page-tabs">
    <button type="button" class="page-tab <?= $activeTab === 'exercises' ? 'active' : '' ?>" data-tab="exercises" data-action="switch-tab">
        <i class="fas fa-running"></i> Exercise Library
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'plans' ? 'active' : '' ?>" data-tab="plans" data-action="switch-tab">
        <i class="fas fa-clipboard-list"></i> Workout Plans
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'create' ? 'active' : '' ?>" data-tab="create" data-action="switch-tab">
        <i class="fas fa-plus-circle"></i> Create Workout Plan
    </button>
</div>

<div class="page-tab-content">
    <!-- Exercise Library Tab -->
    <div class="tab-content <?= $activeTab === 'exercises' ? 'active' : '' ?>" id="exercises-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-running"></i> Exercise Library</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-exercise-modal">
                    <i class="fas fa-plus"></i> Add Exercise
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Create and manage exercises that can be used in workout plans. Include details like reps, sets, and weight requirements.
                </p>
                <div class="exercise-grid">
                    <?php if (count($exercises) > 0): ?>
                        <?php foreach ($exercises as $exercise): ?>
                        <div class="exercise-card">
                            <?php if ($exercise['image_url']): ?>
                            <div class="exercise-image">
                                <img src="<?= htmlspecialchars($exercise['image_url']) ?>" alt="<?= htmlspecialchars($exercise['name']) ?>">
                            </div>
                            <?php else: ?>
                            <div class="exercise-image placeholder">
                                <i class="fas fa-dumbbell"></i>
                            </div>
                            <?php endif; ?>
                            <div class="exercise-content">
                                <h4><?= htmlspecialchars($exercise['name']) ?></h4>
                                <p class="exercise-description"><?= htmlspecialchars($exercise['description'] ?: 'No description') ?></p>
                                <?php if ($exercise['category']): ?>
                                <span class="exercise-category"><?= htmlspecialchars($exercise['category']) ?></span>
                                <?php endif; ?>
                                <?php if ($exercise['difficulty_level']): ?>
                                <span class="exercise-difficulty <?= strtolower($exercise['difficulty_level']) ?>"><?= htmlspecialchars($exercise['difficulty_level']) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="exercise-actions">
                                <button type="button" class="btn-icon" title="Edit"
                                        data-action="edit-exercise"
                                        data-id="<?= $exercise['id'] ?>"
                                        data-name="<?= htmlspecialchars($exercise['name']) ?>"
                                        data-description="<?= htmlspecialchars($exercise['description'] ?? '') ?>"
                                        data-category="<?= htmlspecialchars($exercise['category'] ?? '') ?>"
                                        data-equipment="<?= htmlspecialchars($exercise['equipment_needed'] ?? '') ?>"
                                        data-difficulty="<?= htmlspecialchars($exercise['difficulty_level'] ?? '') ?>"
                                        data-video="<?= htmlspecialchars($exercise['video_url'] ?? '') ?>"
                                        data-image="<?= htmlspecialchars($exercise['image_url'] ?? '') ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-icon btn-icon-danger" title="Delete"
                                        data-action="delete-exercise"
                                        data-id="<?= $exercise['id'] ?>"
                                        data-name="<?= htmlspecialchars($exercise['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-dumbbell"></i>
                            <h4>No Exercises Found</h4>
                            <p>Create your first exercise to start building workout plans.</p>
                            <button type="button" class="btn btn-primary" data-action="add" data-modal="add-exercise-modal">
                                <i class="fas fa-plus"></i> Add Exercise
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Workout Plans Tab -->
    <div class="tab-content <?= $activeTab === 'plans' ? 'active' : '' ?>" id="plans-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Workout Plans</h3>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    View all workout plans. Click to see assigned athletes and edit plan details.
                </p>
                <div class="plans-grid">
                    <?php if (count($workoutPlans) > 0): ?>
                        <?php foreach ($workoutPlans as $plan): ?>
                        <div class="plan-card">
                            <div class="plan-header">
                                <h4><?= htmlspecialchars($plan['name']) ?></h4>
                                <div class="plan-meta">
                                    <span><i class="fas fa-dumbbell"></i> <?= $plan['exercise_count'] ?> exercises</span>
                                    <span><i class="fas fa-users"></i> <?= $plan['assigned_count'] ?> athletes</span>
                                </div>
                            </div>
                            <p class="plan-description"><?= htmlspecialchars($plan['description'] ?: 'No description') ?></p>
                            
                            <?php if (!empty($assignedAthletes[$plan['id']])): ?>
                            <div class="assigned-athletes">
                                <span class="athletes-label">Assigned to:</span>
                                <?php foreach ($assignedAthletes[$plan['id']] as $athlete): ?>
                                <span class="athlete-badge"><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            
                            <div class="plan-actions">
                                <button type="button" class="btn btn-secondary btn-sm" 
                                        data-action="view-plan"
                                        data-id="<?= $plan['id'] ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button type="button" class="btn btn-success btn-sm"
                                        data-action="assign-athletes"
                                        data-id="<?= $plan['id'] ?>"
                                        data-name="<?= htmlspecialchars($plan['name']) ?>"
                                        data-exercises='<?= json_encode($planExercises[$plan['id']] ?? []) ?>'>
                                    <i class="fas fa-users"></i> Assign Athletes
                                </button>
                                <button type="button" class="btn btn-primary btn-sm"
                                        data-action="edit-plan"
                                        data-id="<?= $plan['id'] ?>"
                                        data-name="<?= htmlspecialchars($plan['name']) ?>"
                                        data-description="<?= htmlspecialchars($plan['description'] ?? '') ?>"
                                        data-duration-weeks="<?= $plan['duration_weeks'] ?? '' ?>"
                                        data-difficulty-level="<?= htmlspecialchars($plan['difficulty_level'] ?? '') ?>"
                                        data-exercises='<?= json_encode($planExercises[$plan['id']] ?? []) ?>'>
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <button type="button" class="btn btn-danger btn-sm"
                                        data-action="delete-plan"
                                        data-id="<?= $plan['id'] ?>"
                                        data-name="<?= htmlspecialchars($plan['name']) ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-clipboard-list"></i>
                            <h4>No Workout Plans Found</h4>
                            <p>Create a workout plan to start assigning to athletes.</p>
                            <button type="button" class="btn btn-primary" data-action="switch-tab" data-tab="create">
                                <i class="fas fa-plus"></i> Create Workout Plan
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Workout Plan Tab -->
    <div class="tab-content <?= $activeTab === 'create' ? 'active' : '' ?>" id="create-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Create Workout Plan</h3>
            </div>
            <div class="card-body">
                <form id="create-plan-form" method="POST" action="process_workout.php" enctype="multipart/form-data">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="create_plan">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Plan Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="e.g., Beginner Strength Program">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Difficulty Level</label>
                            <select name="difficulty_level" class="form-input">
                                <option value="">Select Difficulty</option>
                                <option value="beginner">Beginner</option>
                                <option value="intermediate">Intermediate</option>
                                <option value="advanced">Advanced</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the workout plan goals and structure"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Plan Image</label>
                        <input type="file" name="image" class="form-input" accept="image/*">
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-list"></i> Select Exercises</h4>
                        <p class="info-text" style="margin-bottom: 16px;">
                            <i class="fas fa-info-circle"></i>
                            Add exercises to this workout plan. Drag to reorder. Sets, reps, and weights are configured per athlete when assigning.
                        </p>
                        
                        <div class="selected-exercises" id="selected-exercises">
                            <!-- Selected exercises will appear here -->
                        </div>
                        
                        <button type="button" class="btn btn-secondary" id="add-exercise-to-plan">
                            <i class="fas fa-plus"></i> Add Exercise
                        </button>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Workout Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Strength & Conditioning Styles */
.info-text {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    padding: var(--space-4);
    background: rgba(107, 70, 193, 0.1);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.info-text i { color: var(--primary-light); }

/* Exercise Grid */
.exercise-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-4);
}

.exercise-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    overflow: hidden;
    transition: all var(--transition-normal);
}

.exercise-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.exercise-image {
    height: 160px;
    background: var(--bg-main);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.exercise-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.exercise-image.placeholder {
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
}

.exercise-image.placeholder i {
    font-size: 48px;
    color: rgba(255,255,255,0.3);
}

.exercise-content {
    padding: var(--space-4);
}

.exercise-content h4 {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
}

.exercise-description {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    margin-bottom: var(--space-3);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.exercise-category {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    margin-right: var(--space-2);
}

.exercise-difficulty {
    display: inline-block;
    padding: 4px 10px;
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
}

.exercise-difficulty.beginner { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.exercise-difficulty.intermediate { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.exercise-difficulty.advanced { background: rgba(239, 68, 68, 0.15); color: var(--error); }

.exercise-actions {
    display: flex;
    gap: var(--space-2);
    padding: var(--space-3) var(--space-4);
    background: var(--bg-main);
    border-top: 1px solid var(--border);
}

/* Plans Grid */
.plans-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: var(--space-4);
}

.plan-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: var(--space-5);
    transition: all var(--transition-normal);
}

.plan-card:hover {
    border-color: var(--primary);
}

.plan-header {
    margin-bottom: var(--space-3);
}

.plan-header h4 {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
}

.plan-meta {
    display: flex;
    gap: var(--space-4);
    font-size: var(--font-size-sm);
    color: var(--text-muted);
}

.plan-meta i { color: var(--primary-light); margin-right: var(--space-1); }

.plan-description {
    font-size: var(--font-size-sm);
    color: var(--text-dim);
    margin-bottom: var(--space-4);
}

.assigned-athletes {
    margin-bottom: var(--space-4);
    padding: var(--space-3);
    background: var(--bg-main);
    border-radius: var(--radius-lg);
}

.athletes-label {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    display: block;
    margin-bottom: var(--space-2);
}

.athlete-badge {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(107, 70, 193, 0.2);
    color: var(--primary-light);
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    margin-right: var(--space-2);
    margin-bottom: var(--space-1);
}

.plan-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--space-2);
    padding-top: var(--space-4);
    border-top: 1px solid var(--border);
}

.plan-actions .btn {
    width: 100%;
    justify-content: center;
}

/* Form Styles */
.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--space-4);
}

.form-section {
    margin-top: var(--space-6);
    padding-top: var(--space-6);
    border-top: 1px solid var(--border);
}

.section-title {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-4);
    display: flex;
    align-items: center;
    gap: var(--space-2);
}

.section-title i { color: var(--primary-light); }

.form-actions {
    margin-top: var(--space-6);
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
}

.selected-exercises {
    margin-bottom: var(--space-4);
}

.selected-exercise-item {
    display: grid;
    grid-template-columns: auto 1fr auto;
    gap: var(--space-3);
    align-items: center;
    padding: var(--space-3);
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    margin-bottom: var(--space-2);
    cursor: grab;
    transition: box-shadow 0.2s, opacity 0.2s;
}

.selected-exercise-item:active {
    cursor: grabbing;
}

.selected-exercise-item.dragging {
    opacity: 0.5;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.selected-exercise-item.drag-over {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.3);
}

.drag-handle {
    color: var(--text-dim);
    cursor: grab;
    font-size: 14px;
    padding: 4px;
}

.drag-handle:active {
    cursor: grabbing;
}

/* Exercise Selector Modal */
.exercise-selector-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 12px;
}

.exercise-selector-item {
    padding: 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 12px;
}

.exercise-selector-item:hover {
    border-color: var(--primary);
    background: var(--bg-secondary);
}

.exercise-selector-icon {
    color: var(--primary-light);
    font-size: 18px;
}

.exercise-selector-info h5 {
    color: var(--text-white);
    font-size: 14px;
    margin-bottom: 4px;
}

.exercise-selector-info span {
    font-size: 12px;
    color: var(--text-muted);
}

/* Button Icons */
.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all var(--transition-normal);
}

.btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: var(--text-white);
}

.btn-icon-danger:hover {
    background: var(--error);
    border-color: var(--error);
}

/* Empty State */
.empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: var(--space-10) var(--space-6);
    background: var(--bg-secondary);
    border: 1px dashed var(--border);
    border-radius: var(--radius-xl);
}

.empty-state i {
    font-size: 48px;
    color: var(--text-muted);
    margin-bottom: var(--space-4);
    display: block;
}

.empty-state h4 {
    font-size: var(--font-size-lg);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
}

.empty-state p {
    font-size: var(--font-size-base);
    color: var(--text-muted);
    margin-bottom: var(--space-5);
}
</style>

<!-- Add Exercise Modal -->
<div id="add-exercise-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-dumbbell"></i> Add Exercise</h2>
            <button type="button" class="modal-close" onclick="closeModal('add-exercise-modal')">&times;</button>
        </div>
        <form method="POST" action="process_workout.php" enctype="multipart/form-data">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_exercise">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Exercise Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Barbell Squat">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the exercise and proper form"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-input">
                            <option value="">Select Category</option>
                            <option value="Upper Body">Upper Body</option>
                            <option value="Lower Body">Lower Body</option>
                            <option value="Core">Core</option>
                            <option value="Cardio">Cardio</option>
                            <option value="Full Body">Full Body</option>
                            <option value="Flexibility">Flexibility</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Difficulty Level</label>
                        <select name="difficulty_level" class="form-input">
                            <option value="">Select Difficulty</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Equipment Needed</label>
                    <input type="text" name="equipment_needed" class="form-input" placeholder="e.g., Barbell, Dumbbells, Bench">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Exercise Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Video URL</label>
                    <input type="url" name="video_url" class="form-input" placeholder="https://youtube.com/...">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-exercise-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Exercise
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Exercise Modal -->
<div id="edit-exercise-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Exercise</h2>
            <button type="button" class="modal-close" onclick="closeModal('edit-exercise-modal')">&times;</button>
        </div>
        <form method="POST" action="process_workout.php" enctype="multipart/form-data">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_exercise">
            <input type="hidden" name="id" id="edit-exercise-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Exercise Name *</label>
                    <input type="text" name="name" id="edit-exercise-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-exercise-description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="edit-exercise-category" class="form-input">
                            <option value="">Select Category</option>
                            <option value="Upper Body">Upper Body</option>
                            <option value="Lower Body">Lower Body</option>
                            <option value="Core">Core</option>
                            <option value="Cardio">Cardio</option>
                            <option value="Full Body">Full Body</option>
                            <option value="Flexibility">Flexibility</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Difficulty Level</label>
                        <select name="difficulty_level" id="edit-exercise-difficulty" class="form-input">
                            <option value="">Select Difficulty</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Equipment Needed</label>
                    <input type="text" name="equipment_needed" id="edit-exercise-equipment" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Exercise Image</label>
                    <input type="file" name="image" class="form-input" accept="image/*">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Video URL</label>
                    <input type="url" name="video_url" id="edit-exercise-video" class="form-input">
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-exercise-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Exercise
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Workout Plan Modal -->
<div id="edit-plan-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Workout Plan</h2>
            <button type="button" class="modal-close" onclick="closeModal('edit-plan-modal')">&times;</button>
        </div>
        <form method="POST" action="process_workout.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_plan">
            <input type="hidden" name="id" id="edit-plan-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Plan Name *</label>
                    <input type="text" name="name" id="edit-plan-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-plan-description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Duration (Weeks)</label>
                        <input type="number" name="duration_weeks" id="edit-plan-duration" class="form-input" min="1" placeholder="Number of weeks">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Difficulty Level</label>
                        <select name="difficulty_level" id="edit-plan-difficulty" class="form-input">
                            <option value="">Select Level</option>
                            <option value="beginner">Beginner</option>
                            <option value="intermediate">Intermediate</option>
                            <option value="advanced">Advanced</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-list"></i> Plan Exercises</h4>
                    <p class="info-text" style="margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i>
                        Drag exercises to reorder them. You can add new exercises or remove existing ones.
                    </p>
                    <div id="edit-plan-exercises" class="selected-exercises">
                        <!-- Exercises will be populated via JavaScript -->
                    </div>
                    <button type="button" class="btn btn-secondary" id="edit-add-exercise-to-plan">
                        <i class="fas fa-plus"></i> Add Exercise
                    </button>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-plan-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Plan
                </button>
            </div>
        </form>
    </div>
</div>

<!-- View Workout Plan Modal -->
<div id="view-plan-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-eye"></i> View Workout Plan</h2>
            <button type="button" class="modal-close" onclick="closeModal('view-plan-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <!-- Content will be populated via JavaScript -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('view-plan-modal')">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Exercise Selector Modal -->
<div id="exercise-selector-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-dumbbell"></i> Select Exercise</h2>
            <button type="button" class="modal-close" onclick="closeModal('exercise-selector-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="exercise-selector-grid">
                <?php foreach ($exercises as $exercise): ?>
                <div class="exercise-selector-item" 
                     data-id="<?= $exercise['id'] ?>"
                     data-name="<?= htmlspecialchars($exercise['name']) ?>"
                     onclick="selectExerciseForPlan(<?= $exercise['id'] ?>, '<?= htmlspecialchars(addslashes($exercise['name'])) ?>')">
                    <div class="exercise-selector-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="exercise-selector-info">
                        <h5><?= htmlspecialchars($exercise['name']) ?></h5>
                        <span><?= htmlspecialchars($exercise['category'] ?? 'No category') ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
// Tab switching
document.querySelectorAll('[data-action="switch-tab"]').forEach(button => {
    button.addEventListener('click', function() {
        const tabName = this.getAttribute('data-tab');
        
        document.querySelectorAll('.page-tab').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        this.classList.add('active');
        document.getElementById(tabName + '-tab').classList.add('active');
        
        const url = new URL(window.location);
        url.searchParams.set('tab', tabName);
        window.history.replaceState({}, '', url);
    });
});

// Modal handlers
document.querySelectorAll('[data-action="add"][data-modal]').forEach(button => {
    button.addEventListener('click', function() {
        openModal(this.getAttribute('data-modal'));
    });
});

// Edit exercise handler
document.querySelectorAll('[data-action="edit-exercise"]').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit-exercise-id').value = this.dataset.id;
        document.getElementById('edit-exercise-name').value = this.dataset.name;
        document.getElementById('edit-exercise-description').value = this.dataset.description;
        document.getElementById('edit-exercise-category').value = this.dataset.category;
        document.getElementById('edit-exercise-equipment').value = this.dataset.equipment;
        document.getElementById('edit-exercise-difficulty').value = this.dataset.difficulty;
        document.getElementById('edit-exercise-video').value = this.dataset.video;
        openModal('edit-exercise-modal');
    });
});

// Delete exercise handler
document.querySelectorAll('[data-action="delete-exercise"]').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        if (confirm('Are you sure you want to delete "' + name + '"?')) {
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            fetch('process_workout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete_exercise&id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
                else alert('Error: ' + data.message);
            });
        }
    });
});

// Edit plan handler
let editExerciseCount = 0;
document.querySelectorAll('[data-action="edit-plan"]').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit-plan-id').value = this.dataset.id;
        document.getElementById('edit-plan-name').value = this.dataset.name;
        document.getElementById('edit-plan-description').value = this.dataset.description;
        document.getElementById('edit-plan-duration').value = this.dataset.durationWeeks || '';
        document.getElementById('edit-plan-difficulty').value = this.dataset.difficultyLevel || '';
        
        // Populate exercises with full controls
        const exercises = JSON.parse(this.dataset.exercises || '[]');
        const container = document.getElementById('edit-plan-exercises');
        container.innerHTML = '';
        editExerciseCount = 0;
        
        if (exercises.length === 0) {
            container.innerHTML = '<p class="edit-plan-empty-msg" style="color: var(--text-dim);">No exercises added to this plan yet.</p>';
        } else {
            exercises.forEach((ex) => {
                addExerciseToEditPlan(ex.exercise_id, ex.exercise_name || 'Exercise');
            });
        }
        
        initExerciseDragAndDrop(container);
        openModal('edit-plan-modal');
    });
});

function addExerciseToEditPlan(id, name) {
    const container = document.getElementById('edit-plan-exercises');
    // Remove "no exercises" message if present
    const emptyMsg = container.querySelector('.edit-plan-empty-msg');
    if (emptyMsg) emptyMsg.remove();
    
    const index = editExerciseCount++;
    
    const div = document.createElement('div');
    div.className = 'selected-exercise-item';
    div.draggable = true;
    div.innerHTML = '<span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>' +
        '<span>' + name + '</span>' +
        '<input type="hidden" name="exercises[' + index + '][id]" value="' + id + '">' +
        '<button type="button" class="btn-icon btn-icon-danger" onclick="this.parentElement.remove(); reindexExercises(document.getElementById(\'edit-plan-exercises\'))"><i class="fas fa-times"></i></button>';
    container.appendChild(div);
}

// View plan handler
document.querySelectorAll('[data-action="view-plan"]').forEach(button => {
    button.addEventListener('click', function() {
        const planId = this.dataset.id;
        const planCard = this.closest('.plan-card');
        const planName = planCard ? planCard.querySelector('h4').textContent : 'Workout Plan';
        
        // Find the exercises data from the edit button on the same card
        const editBtn = planCard ? planCard.querySelector('[data-action="edit-plan"]') : null;
        const exercises = editBtn ? JSON.parse(editBtn.dataset.exercises || '[]') : [];
        
        let exercisesHtml = '<div style="padding: 20px;">';
        exercisesHtml += '<h3 style="margin-bottom: 16px;"><i class="fas fa-dumbbell"></i> ' + planName + '</h3>';
        
        if (exercises.length === 0) {
            exercisesHtml += '<p style="color: var(--text-dim);">No exercises in this plan yet.</p>';
        } else {
            exercisesHtml += '<div style="display: grid; gap: 12px;">';
            exercises.forEach((ex, i) => {
                exercisesHtml += '<div style="padding: 12px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px;">' +
                    '<strong>' + (i + 1) + '. ' + (ex.exercise_name || 'Exercise') + '</strong>' +
                    '<div style="display: flex; gap: 16px; margin-top: 8px; color: var(--text-dim); font-size: 13px;">' +
                    (ex.sets ? '<span><i class="fas fa-layer-group"></i> ' + ex.sets + ' sets</span>' : '') +
                    (ex.reps ? '<span><i class="fas fa-redo"></i> ' + ex.reps + ' reps</span>' : '') +
                    (ex.rest_seconds ? '<span><i class="fas fa-clock"></i> ' + ex.rest_seconds + 's rest</span>' : '') +
                    '</div>' +
                    '</div>';
            });
            exercisesHtml += '</div>';
        }
        exercisesHtml += '</div>';
        
        // Use the view modal
        if (document.getElementById('view-plan-modal')) {
            document.getElementById('view-plan-modal').querySelector('.modal-body').innerHTML = exercisesHtml;
            openModal('view-plan-modal');
        } else {
            alert('Exercises in this plan:\\n\\n' + exercises.map((ex, i) => (i+1) + '. ' + (ex.exercise_name || 'Exercise')).join('\\n'));
        }
    });
});

// Delete plan handler
document.querySelectorAll('[data-action="delete-plan"]').forEach(button => {
    button.addEventListener('click', function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        if (confirm('Are you sure you want to delete "' + name + '"? This will also remove all athlete assignments.')) {
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            fetch('process_workout.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete_plan&id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
                else alert('Error: ' + data.message);
            });
        }
    });
});

// Add exercise to plan (Create tab)
let exerciseCount = 0;
let exerciseSelectorTarget = 'create'; // 'create' or 'edit'

document.getElementById('add-exercise-to-plan').addEventListener('click', function() {
    exerciseSelectorTarget = 'create';
    openModal('exercise-selector-modal');
});

document.getElementById('edit-add-exercise-to-plan').addEventListener('click', function() {
    exerciseSelectorTarget = 'edit';
    openModal('exercise-selector-modal');
});

function selectExerciseForPlan(id, name) {
    if (exerciseSelectorTarget === 'edit') {
        addExerciseToEditPlan(id, name);
        reindexExercises(document.getElementById('edit-plan-exercises'));
        initExerciseDragAndDrop(document.getElementById('edit-plan-exercises'));
    } else {
        const container = document.getElementById('selected-exercises');
        const index = exerciseCount++;
        
        const div = document.createElement('div');
        div.className = 'selected-exercise-item';
        div.draggable = true;
        div.innerHTML = '<span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>' +
            '<span>' + name + '</span>' +
            '<input type="hidden" name="exercises[' + index + '][id]" value="' + id + '">' +
            '<button type="button" class="btn-icon btn-icon-danger" onclick="this.parentElement.remove(); reindexExercises(document.getElementById(\'selected-exercises\'))"><i class="fas fa-times"></i></button>';
        container.appendChild(div);
        
        initExerciseDragAndDrop(container);
    }
    
    closeModal('exercise-selector-modal');
}

// Reindex exercise hidden inputs after reorder or removal
function reindexExercises(container) {
    const items = container.querySelectorAll('.selected-exercise-item');
    items.forEach((item, i) => {
        const hiddenInput = item.querySelector('input[type="hidden"][name*="[id]"]');
        if (hiddenInput) hiddenInput.name = 'exercises[' + i + '][id]';
    });
}

// Drag and drop support for exercise reordering
function initExerciseDragAndDrop(container) {
    const items = container.querySelectorAll('.selected-exercise-item');
    
    items.forEach(item => {
        item.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            container.querySelectorAll('.selected-exercise-item').forEach(el => el.classList.remove('drag-over'));
            reindexExercises(container);
        });
        
        item.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const dragging = container.querySelector('.dragging');
            if (dragging && dragging !== this) {
                this.classList.add('drag-over');
            }
        });
        
        item.addEventListener('dragleave', function() {
            this.classList.remove('drag-over');
        });
        
        item.addEventListener('drop', function(e) {
            e.preventDefault();
            this.classList.remove('drag-over');
            const dragging = container.querySelector('.dragging');
            if (dragging && dragging !== this) {
                const allItems = [...container.querySelectorAll('.selected-exercise-item')];
                const dragIdx = allItems.indexOf(dragging);
                const dropIdx = allItems.indexOf(this);
                if (dragIdx < dropIdx) {
                    container.insertBefore(dragging, this.nextSibling);
                } else {
                    container.insertBefore(dragging, this);
                }
            }
        });
    });
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Convert forms to AJAX (includes modal forms and create plan form)
document.querySelectorAll('.modal form, #create-plan-form').forEach(form => {
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        submitBtn.disabled = true;
        
        // Use getAttribute to avoid conflict with input[name="action"]
        fetch(this.getAttribute('action'), {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(r => r.json())
        .then(data => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            if (data.success) {
                persistToast(data.message || 'Operation completed successfully', 'success');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err => {
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
            console.error(err);
        });
    });
});
</script>

<!-- Assign Athletes Modal -->
<div id="assign-athletes-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-users"></i> Assign Athletes to Workout Plan</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('assign-athletes-modal')">&times;</button>
        </div>
        <form id="assign-athletes-form" method="POST" action="process_workout.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="assign_athletes">
            <input type="hidden" name="workout_plan_id" id="assign-workout-plan-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Workout Plan</label>
                    <input type="text" id="assign-workout-plan-name" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Athletes</label>
                    <div id="workout-athlete-typeahead"></div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Start Date</label>
                    <input type="date" name="start_date" class="form-input" value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-textarea" rows="2" placeholder="Optional notes for this assignment"></textarea>
                </div>
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-sliders-h"></i> Custom Exercise Settings (Optional)</h4>
                    <p class="info-text" style="margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i>
                        Override default sets, reps, and weights for each exercise. Leave blank to use plan defaults.
                    </p>
                    <div id="exercise-settings-container">
                        <!-- Exercise settings will be populated dynamically -->
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assign-athletes-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Assign Athletes</button>
            </div>
        </form>
    </div>
</div>

<style>
.exercise-setting-row {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 10px;
    padding: 12px;
    background: var(--bg-main);
    border-radius: var(--radius-md);
    margin-bottom: 8px;
    align-items: center;
}
.exercise-setting-row label {
    font-weight: 600;
    font-size: var(--font-size-sm);
}
.exercise-setting-row input {
    padding: 6px 10px;
    font-size: var(--font-size-sm);
}
.exercise-settings-header {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr 1fr 1fr;
    gap: 10px;
    padding: 8px 12px;
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
}
</style>

<script>
// Assign Athletes Modal Handler
document.querySelectorAll('[data-action="assign-athletes"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const planId = this.dataset.id;
        const planName = this.dataset.name;
        const exercises = JSON.parse(this.dataset.exercises || '[]');
        
        document.getElementById('assign-workout-plan-id').value = planId;
        document.getElementById('assign-workout-plan-name').value = planName;
        
        // Build exercise settings form
        const container = document.getElementById('exercise-settings-container');
        if (exercises.length > 0) {
            let html = '<div class="exercise-settings-header"><span>Exercise</span><span>Sets</span><span>Reps</span><span>Weight</span><span>Unit</span></div>';
            exercises.forEach((ex, idx) => {
                html += `
                    <div class="exercise-setting-row">
                        <label>${ex.exercise_name || 'Exercise'}</label>
                        <input type="hidden" name="exercises[${idx}][exercise_id]" value="${ex.exercise_id}">
                        <input type="number" name="exercises[${idx}][custom_sets]" placeholder="${ex.sets || 'Sets'}" class="form-input" min="1">
                        <input type="text" name="exercises[${idx}][custom_reps]" placeholder="${ex.reps || 'Reps'}" class="form-input">
                        <input type="number" name="exercises[${idx}][custom_weight]" placeholder="Weight" class="form-input" step="0.5" min="0">
                        <select name="exercises[${idx}][custom_weight_unit]" class="form-input">
                            <option value="lbs">lbs</option>
                            <option value="kg">kg</option>
                        </select>
                    </div>
                `;
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="info-text">No exercises in this plan yet.</p>';
        }
        
        openModal('assign-athletes-modal');
    });
});

// Form submission with AJAX
document.getElementById('assign-athletes-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const formData = new FormData(this);
    const submitBtn = this.querySelector('[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    submitBtn.disabled = true;
    
    fetch(this.getAttribute('action'), {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        if (data.success) {
            closeModal('assign-athletes-modal');
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(err => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        console.error(err);
        alert('An error occurred. Please try again.');
    });
});
</script>
<script>
new ArcticTypeahead({
    container: '#workout-athlete-typeahead',
    name: 'athlete_ids',
    placeholder: 'Search for athletes…',
    roles: 'athlete',
    multiple: true
});
</script>
