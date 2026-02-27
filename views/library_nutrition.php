<?php
/**
 * Nutrition Library
 * Three tabs: Meals Library, Meal Plans, Create Meal Plan
 */

require_once __DIR__ . '/../security.php';

// Check if user has permission to view library
if (!in_array($user_role, ['health_coach', 'coach', 'coach_plus', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Determine active tab
$activeTab = $_GET['tab'] ?? 'meals';
$validTabs = ['meals', 'plans', 'create'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = 'meals';
}

// Fetch all foods/meals from library
$meals = $pdo->query("
    SELECT fl.*, u.first_name, u.last_name 
    FROM food_library fl
    LEFT JOIN users u ON fl.created_by = u.id
    ORDER BY fl.name ASC
")->fetchAll();
$meals = decryptUserRows($meals);

// Fetch all nutrition plans with assigned athlete count
$nutritionPlans = $pdo->query("
    SELECT np.*, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM nutrition_plan_meals WHERE nutrition_plan_id = np.id) as meal_count,
           (SELECT COUNT(*) FROM athlete_nutrition_assignments WHERE nutrition_plan_id = np.id AND status = 'active') as assigned_count
    FROM nutrition_plans np
    LEFT JOIN users u ON np.created_by = u.id
    ORDER BY np.created_at DESC
")->fetchAll();
$nutritionPlans = decryptUserRows($nutritionPlans);

// For edit modal - fetch meals for each plan
$planMeals = [];
foreach ($nutritionPlans as $plan) {
    $stmt = $pdo->prepare("
        SELECT npm.*, fl.name as food_name, fl.calories, fl.protein_g, fl.carbs_g, fl.fat_g, npmf.food_id
        FROM nutrition_plan_meals npm
        LEFT JOIN nutrition_plan_meal_foods npmf ON npm.id = npmf.meal_id
        LEFT JOIN food_library fl ON npmf.food_id = fl.id
        WHERE npm.nutrition_plan_id = ?
        ORDER BY npm.day_number, npm.meal_order ASC
    ");
    $stmt->execute([$plan['id']]);
    $planMeals[$plan['id']] = $stmt->fetchAll();
}

// Fetch assigned athletes for each plan
$assignedAthletes = [];
foreach ($nutritionPlans as $plan) {
    $stmt = $pdo->prepare("
        SELECT ana.*, u.first_name, u.last_name
        FROM athlete_nutrition_assignments ana
        JOIN users u ON ana.athlete_id = u.id
        WHERE ana.nutrition_plan_id = ? AND ana.status = 'active'
    ");
    $stmt->execute([$plan['id']]);
    $assignedAthletes[$plan['id']] = decryptUserRows($stmt->fetchAll());
}

// Fetch all active users for assignment modal (all roles can receive nutrition assignments)
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
        <h1 class="page-title"><i class="fas fa-utensils"></i> Nutrition</h1>
        <p class="page-description">Manage meals, nutrition plans, and user dietary assignments</p>
    </div>
</div>

<!-- Tab Navigation -->
<div class="page-tabs">
    <button type="button" class="page-tab <?= $activeTab === 'meals' ? 'active' : '' ?>" data-tab="meals" data-action="switch-tab">
        <i class="fas fa-apple-whole"></i> Meals Library
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'plans' ? 'active' : '' ?>" data-tab="plans" data-action="switch-tab">
        <i class="fas fa-clipboard-list"></i> Meal Plans
    </button>
    <button type="button" class="page-tab <?= $activeTab === 'create' ? 'active' : '' ?>" data-tab="create" data-action="switch-tab">
        <i class="fas fa-plus-circle"></i> Create Meal Plan
    </button>
</div>

<div class="page-tab-content">
    <!-- Meals Library Tab -->
    <div class="tab-content <?= $activeTab === 'meals' ? 'active' : '' ?>" id="meals-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-apple-whole"></i> Meals Library</h3>
                <button type="button" class="btn btn-primary" data-action="add" data-modal="add-meal-modal">
                    <i class="fas fa-plus"></i> Add Meal
                </button>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    Create and manage individual meals and foods that can be used in nutrition plans.
                </p>
                <div class="meals-grid">
                    <?php if (count($meals) > 0): ?>
                        <?php foreach ($meals as $meal): ?>
                        <div class="meal-card">
                            <div class="meal-icon">
                                <i class="fas fa-utensils"></i>
                            </div>
                            <div class="meal-content">
                                <h4><?= htmlspecialchars($meal['name']) ?></h4>
                                <p class="meal-description"><?= htmlspecialchars($meal['description'] ?: 'No description') ?></p>
                                <?php if ($meal['category']): ?>
                                <span class="meal-category"><?= htmlspecialchars($meal['category']) ?></span>
                                <?php endif; ?>
                                <div class="meal-macros">
                                    <?php if ($meal['calories']): ?>
                                    <span class="macro-item"><i class="fas fa-fire"></i> <?= $meal['calories'] ?> cal</span>
                                    <?php endif; ?>
                                    <?php if ($meal['protein_g']): ?>
                                    <span class="macro-item protein"><i class="fas fa-drumstick-bite"></i> <?= $meal['protein_g'] ?>g P</span>
                                    <?php endif; ?>
                                    <?php if ($meal['carbs_g']): ?>
                                    <span class="macro-item carbs"><i class="fas fa-bread-slice"></i> <?= $meal['carbs_g'] ?>g C</span>
                                    <?php endif; ?>
                                    <?php if ($meal['fat_g']): ?>
                                    <span class="macro-item fat"><i class="fas fa-cheese"></i> <?= $meal['fat_g'] ?>g F</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="meal-actions">
                                <button type="button" class="btn-icon" title="Edit"
                                        data-action="edit-meal"
                                        data-id="<?= $meal['id'] ?>"
                                        data-name="<?= htmlspecialchars($meal['name']) ?>"
                                        data-description="<?= htmlspecialchars($meal['description'] ?? '') ?>"
                                        data-category="<?= htmlspecialchars($meal['category'] ?? '') ?>"
                                        data-calories="<?= $meal['calories'] ?? '' ?>"
                                        data-protein="<?= $meal['protein_g'] ?? '' ?>"
                                        data-carbs="<?= $meal['carbs_g'] ?? '' ?>"
                                        data-fat="<?= $meal['fat_g'] ?? '' ?>"
                                        data-serving="<?= htmlspecialchars($meal['serving_size'] ?? '') ?>">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-icon btn-icon-danger" title="Delete"
                                        data-action="delete-meal"
                                        data-id="<?= $meal['id'] ?>"
                                        data-name="<?= htmlspecialchars($meal['name']) ?>">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-utensils"></i>
                            <h4>No Meals Found</h4>
                            <p>Create your first meal to start building nutrition plans.</p>
                            <button type="button" class="btn btn-primary" data-action="add" data-modal="add-meal-modal">
                                <i class="fas fa-plus"></i> Add Meal
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Meal Plans Tab -->
    <div class="tab-content <?= $activeTab === 'plans' ? 'active' : '' ?>" id="plans-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-clipboard-list"></i> Meal Plans</h3>
            </div>
            <div class="card-body">
                <p class="info-text">
                    <i class="fas fa-info-circle"></i>
                    View all meal plans. Click to see assigned athletes and edit plan details.
                </p>
                <div class="plans-grid">
                    <?php if (count($nutritionPlans) > 0): ?>
                        <?php foreach ($nutritionPlans as $plan): ?>
                        <div class="plan-card">
                            <div class="plan-header">
                                <h4><?= htmlspecialchars($plan['name']) ?></h4>
                                <div class="plan-meta">
                                    <span><i class="fas fa-utensils"></i> <?= $plan['meal_count'] ?> meals</span>
                                    <span><i class="fas fa-users"></i> <?= $plan['assigned_count'] ?> athletes</span>
                                </div>
                            </div>
                            <p class="plan-description"><?= htmlspecialchars($plan['description'] ?: 'No description') ?></p>
                            
                            <?php if ($plan['target_calories']): ?>
                            <div class="plan-targets">
                                <span><strong>Target:</strong> <?= $plan['target_calories'] ?> cal</span>
                                <?php if ($plan['target_protein_g']): ?><span>| <?= $plan['target_protein_g'] ?>g P</span><?php endif; ?>
                                <?php if ($plan['target_carbs_g']): ?><span>| <?= $plan['target_carbs_g'] ?>g C</span><?php endif; ?>
                                <?php if ($plan['target_fat_g']): ?><span>| <?= $plan['target_fat_g'] ?>g F</span><?php endif; ?>
                            </div>
                            <?php endif; ?>
                            
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
                                        data-meals='<?= json_encode($planMeals[$plan['id']] ?? []) ?>'>
                                    <i class="fas fa-users"></i> Assign Athletes
                                </button>
                                <button type="button" class="btn btn-primary btn-sm"
                                        data-action="edit-plan"
                                        data-id="<?= $plan['id'] ?>"
                                        data-name="<?= htmlspecialchars($plan['name']) ?>"
                                        data-description="<?= htmlspecialchars($plan['description'] ?? '') ?>"
                                        data-calories="<?= $plan['target_calories'] ?? '' ?>"
                                        data-protein="<?= $plan['target_protein_g'] ?? '' ?>"
                                        data-carbs="<?= $plan['target_carbs_g'] ?? '' ?>"
                                        data-fat="<?= $plan['target_fat_g'] ?? '' ?>"
                                        data-meals='<?= json_encode($planMeals[$plan['id']] ?? []) ?>'>
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
                            <h4>No Meal Plans Found</h4>
                            <p>Create a meal plan to start assigning to athletes.</p>
                            <button type="button" class="btn btn-primary" data-action="switch-tab" data-tab="create">
                                <i class="fas fa-plus"></i> Create Meal Plan
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Meal Plan Tab -->
    <div class="tab-content <?= $activeTab === 'create' ? 'active' : '' ?>" id="create-tab">
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-plus-circle"></i> Create Meal Plan</h3>
            </div>
            <div class="card-body">
                <form id="create-plan-form" method="POST" action="process_nutrition.php">
                    <?php echo csrfTokenInput(); ?>
                    <input type="hidden" name="action" value="create_plan">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Plan Name *</label>
                            <input type="text" name="name" class="form-input" required placeholder="e.g., High Protein Athlete Diet">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the nutrition plan goals and guidelines"></textarea>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-bullseye"></i> Daily Targets (Optional)</h4>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Target Calories</label>
                                <input type="number" name="target_calories" class="form-input" placeholder="e.g., 2500">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Protein (g)</label>
                                <input type="number" name="target_protein_g" class="form-input" placeholder="e.g., 150">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Carbs (g)</label>
                                <input type="number" name="target_carbs_g" class="form-input" placeholder="e.g., 300">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Target Fat (g)</label>
                                <input type="number" name="target_fat_g" class="form-input" placeholder="e.g., 80">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h4 class="section-title"><i class="fas fa-list"></i> Add Meals to Plan</h4>
                        <p class="info-text" style="margin-bottom: 16px;">
                            <i class="fas fa-info-circle"></i>
                            Select meals to include in this nutrition plan. You can specify meal types (breakfast, lunch, etc).
                        </p>
                        
                        <div class="selected-meals" id="selected-meals">
                            <!-- Selected meals will appear here -->
                        </div>
                        
                        <button type="button" class="btn btn-secondary" id="add-meal-to-plan">
                            <i class="fas fa-plus"></i> Add Meal
                        </button>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Create Meal Plan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
/* Nutrition Library Styles */
.info-text {
    display: flex;
    align-items: center;
    gap: var(--space-3);
    margin-bottom: var(--space-5);
    padding: var(--space-4);
    background: rgba(16, 185, 129, 0.1);
    border-radius: var(--radius-lg);
    color: var(--text-secondary);
    font-size: var(--font-size-sm);
}

.info-text i { color: var(--success); }

/* Meals Grid */
.meals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: var(--space-4);
}

.meal-card {
    background: var(--bg-secondary);
    border: 1px solid var(--border);
    border-radius: var(--radius-xl);
    padding: var(--space-4);
    display: flex;
    gap: var(--space-4);
    transition: all var(--transition-normal);
}

.meal-card:hover {
    border-color: var(--success);
    transform: translateY(-2px);
    box-shadow: var(--shadow-lg);
}

.meal-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #10B981, #059669);
    border-radius: var(--radius-lg);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: var(--text-white);
    flex-shrink: 0;
}

.meal-content {
    flex: 1;
    min-width: 0;
}

.meal-content h4 {
    font-size: var(--font-size-md);
    font-weight: var(--font-weight-bold);
    color: var(--text-white);
    margin-bottom: var(--space-2);
}

.meal-description {
    font-size: var(--font-size-sm);
    color: var(--text-muted);
    margin-bottom: var(--space-3);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

.meal-category {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
    border-radius: var(--radius-md);
    font-size: var(--font-size-xs);
    font-weight: var(--font-weight-semibold);
    margin-bottom: var(--space-2);
}

.meal-macros {
    display: flex;
    flex-wrap: wrap;
    gap: var(--space-2);
    margin-top: var(--space-2);
}

.macro-item {
    font-size: var(--font-size-xs);
    color: var(--text-muted);
    display: flex;
    align-items: center;
    gap: 4px;
}

.macro-item i { font-size: 10px; }
.macro-item.protein i { color: #EF4444; }
.macro-item.carbs i { color: #F59E0B; }
.macro-item.fat i { color: #3B82F6; }

.meal-actions {
    display: flex;
    flex-direction: column;
    gap: var(--space-2);
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
    border-color: var(--success);
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

.plan-meta i { color: var(--success); margin-right: var(--space-1); }

.plan-description {
    font-size: var(--font-size-sm);
    color: var(--text-dim);
    margin-bottom: var(--space-3);
}

.plan-targets {
    font-size: var(--font-size-sm);
    color: var(--text-secondary);
    padding: var(--space-2) var(--space-3);
    background: var(--bg-main);
    border-radius: var(--radius-md);
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
    background: rgba(16, 185, 129, 0.2);
    color: var(--success);
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
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

.section-title i { color: var(--success); }

.form-actions {
    margin-top: var(--space-6);
    display: flex;
    justify-content: flex-end;
    gap: var(--space-3);
}

.selected-meals {
    margin-bottom: var(--space-4);
}

.selected-meal-item {
    display: grid;
    grid-template-columns: auto 2fr 1fr auto;
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

.selected-meal-item:active {
    cursor: grabbing;
}

.selected-meal-item.dragging {
    opacity: 0.5;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.selected-meal-item.drag-over {
    border-color: var(--success);
    box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.3);
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
    background: var(--success);
    border-color: var(--success);
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

<!-- Add Meal Modal -->
<div id="add-meal-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-utensils"></i> Add Meal</h2>
            <button type="button" class="modal-close" onclick="closeModal('add-meal-modal')">&times;</button>
        </div>
        <form method="POST" action="process_nutrition.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="create_meal">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Meal Name *</label>
                    <input type="text" name="name" class="form-input" required placeholder="e.g., Grilled Chicken Breast">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3" placeholder="Describe the meal and preparation"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-input">
                            <option value="">Select Category</option>
                            <option value="Protein">Protein</option>
                            <option value="Carbohydrates">Carbohydrates</option>
                            <option value="Vegetables">Vegetables</option>
                            <option value="Fruits">Fruits</option>
                            <option value="Dairy">Dairy</option>
                            <option value="Fats">Fats</option>
                            <option value="Snack">Snack</option>
                            <option value="Complete Meal">Complete Meal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Serving Size</label>
                        <input type="text" name="serving_size" class="form-input" placeholder="e.g., 1 cup, 100g">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Calories</label>
                        <input type="number" name="calories" class="form-input" step="0.01" placeholder="e.g., 165">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Protein (g)</label>
                        <input type="number" name="protein_g" class="form-input" step="0.01" placeholder="e.g., 31">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Carbs (g)</label>
                        <input type="number" name="carbs_g" class="form-input" step="0.01" placeholder="e.g., 0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fat (g)</label>
                        <input type="number" name="fat_g" class="form-input" step="0.01" placeholder="e.g., 3.6">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('add-meal-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Add Meal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Meal Modal -->
<div id="edit-meal-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Meal</h2>
            <button type="button" class="modal-close" onclick="closeModal('edit-meal-modal')">&times;</button>
        </div>
        <form method="POST" action="process_nutrition.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="update_meal">
            <input type="hidden" name="id" id="edit-meal-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Meal Name *</label>
                    <input type="text" name="name" id="edit-meal-name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="edit-meal-description" class="form-textarea" rows="3"></textarea>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" id="edit-meal-category" class="form-input">
                            <option value="">Select Category</option>
                            <option value="Protein">Protein</option>
                            <option value="Carbohydrates">Carbohydrates</option>
                            <option value="Vegetables">Vegetables</option>
                            <option value="Fruits">Fruits</option>
                            <option value="Dairy">Dairy</option>
                            <option value="Fats">Fats</option>
                            <option value="Snack">Snack</option>
                            <option value="Complete Meal">Complete Meal</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Serving Size</label>
                        <input type="text" name="serving_size" id="edit-meal-serving" class="form-input">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Calories</label>
                        <input type="number" name="calories" id="edit-meal-calories" class="form-input" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Protein (g)</label>
                        <input type="number" name="protein_g" id="edit-meal-protein" class="form-input" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Carbs (g)</label>
                        <input type="number" name="carbs_g" id="edit-meal-carbs" class="form-input" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fat (g)</label>
                        <input type="number" name="fat_g" id="edit-meal-fat" class="form-input" step="0.01">
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('edit-meal-modal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Update Meal
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Meal Plan Modal -->
<div id="edit-plan-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-edit"></i> Edit Meal Plan</h2>
            <button type="button" class="modal-close" onclick="closeModal('edit-plan-modal')">&times;</button>
        </div>
        <form method="POST" action="process_nutrition.php">
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
                        <label class="form-label">Target Calories</label>
                        <input type="number" name="target_calories" id="edit-plan-calories" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Protein (g)</label>
                        <input type="number" name="target_protein_g" id="edit-plan-protein" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Carbs (g)</label>
                        <input type="number" name="target_carbs_g" id="edit-plan-carbs" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Target Fat (g)</label>
                        <input type="number" name="target_fat_g" id="edit-plan-fat" class="form-input">
                    </div>
                </div>
                
                <div class="form-section">
                    <h4 class="section-title"><i class="fas fa-utensils"></i> Plan Meals</h4>
                    <p class="info-text" style="margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i>
                        Drag meals to reorder them. You can add new meals or remove existing ones.
                    </p>
                    <div id="edit-plan-meals" class="selected-meals">
                        <!-- Meals will be populated via JavaScript -->
                    </div>
                    <button type="button" class="btn btn-secondary" id="edit-add-meal-to-plan">
                        <i class="fas fa-plus"></i> Add Meal
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

<!-- View Meal Plan Modal -->
<div id="view-plan-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-eye"></i> View Meal Plan</h2>
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

<!-- Meal Selector Modal -->
<div id="meal-selector-modal" class="modal">
    <div class="modal-content modal-lg">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-utensils"></i> Select Meal</h2>
            <button type="button" class="modal-close" onclick="closeModal('meal-selector-modal')">&times;</button>
        </div>
        <div class="modal-body">
            <div class="meal-selector-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 12px;">
                <?php foreach ($meals as $meal): ?>
                <div class="meal-selector-item" 
                     style="padding: 12px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; cursor: pointer; transition: all 0.2s;"
                     data-id="<?= $meal['id'] ?>"
                     data-name="<?= htmlspecialchars($meal['name']) ?>"
                     onclick="selectMealForPlan(<?= $meal['id'] ?>, '<?= htmlspecialchars(addslashes($meal['name'])) ?>')">
                    <h5 style="color: var(--text-white); font-size: 14px; margin-bottom: 4px;"><?= htmlspecialchars($meal['name']) ?></h5>
                    <span style="font-size: 12px; color: var(--text-muted);"><?= htmlspecialchars($meal['category'] ?? 'No category') ?></span>
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

// Edit meal handler
document.querySelectorAll('[data-action="edit-meal"]').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit-meal-id').value = this.dataset.id;
        document.getElementById('edit-meal-name').value = this.dataset.name;
        document.getElementById('edit-meal-description').value = this.dataset.description;
        document.getElementById('edit-meal-category').value = this.dataset.category;
        document.getElementById('edit-meal-serving').value = this.dataset.serving;
        document.getElementById('edit-meal-calories').value = this.dataset.calories;
        document.getElementById('edit-meal-protein').value = this.dataset.protein;
        document.getElementById('edit-meal-carbs').value = this.dataset.carbs;
        document.getElementById('edit-meal-fat').value = this.dataset.fat;
        openModal('edit-meal-modal');
    });
});

// Delete meal handler
document.querySelectorAll('[data-action="delete-meal"]').forEach(button => {
    button.addEventListener('click', async function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        if (await showConfirmModal('Are you sure you want to delete "' + name + '"?')) {
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            fetch('process_nutrition.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete_meal&id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
                else showToast('Error: ' + data.message, 'error');
            });
        }
    });
});

// Edit plan handler
let editMealCount = 0;
document.querySelectorAll('[data-action="edit-plan"]').forEach(button => {
    button.addEventListener('click', function() {
        document.getElementById('edit-plan-id').value = this.dataset.id;
        document.getElementById('edit-plan-name').value = this.dataset.name;
        document.getElementById('edit-plan-description').value = this.dataset.description;
        document.getElementById('edit-plan-calories').value = this.dataset.calories;
        document.getElementById('edit-plan-protein').value = this.dataset.protein;
        document.getElementById('edit-plan-carbs').value = this.dataset.carbs;
        document.getElementById('edit-plan-fat').value = this.dataset.fat;
        
        // Populate meals with full controls
        const meals = JSON.parse(this.dataset.meals || '[]');
        const container = document.getElementById('edit-plan-meals');
        container.innerHTML = '';
        editMealCount = 0;
        
        if (meals.length === 0) {
            container.innerHTML = '<p class="edit-plan-empty-msg" style="color: var(--text-dim);">No meals added to this plan yet.</p>';
        } else {
            meals.forEach((meal) => {
                addMealToEditPlan(meal.food_id || meal.id, meal.food_name || meal.name || 'Meal', meal.meal_type || 'breakfast');
            });
        }
        
        initDragAndDrop(container, 'edit_meals');
        openModal('edit-plan-modal');
    });
});

function addMealToEditPlan(id, name, type) {
    const container = document.getElementById('edit-plan-meals');
    // Remove "no meals" message if present
    const emptyMsg = container.querySelector('.edit-plan-empty-msg');
    if (emptyMsg) emptyMsg.remove();
    
    const index = editMealCount++;
    const mealTypeOptions = ['breakfast', 'lunch', 'dinner', 'snack', 'pre_workout', 'post_workout'];
    let selectHtml = '<select name="meals[' + index + '][type]" class="form-input">';
    mealTypeOptions.forEach(opt => {
        const label = opt.replaceAll('_', '-').replace(/\b\w/g, c => c.toUpperCase());
        selectHtml += '<option value="' + opt + '"' + (opt === type ? ' selected' : '') + '>' + label + '</option>';
    });
    selectHtml += '</select>';
    
    const div = document.createElement('div');
    div.className = 'selected-meal-item';
    div.draggable = true;
    div.innerHTML = '<span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>' +
        '<span>' + name + '</span>' +
        '<input type="hidden" name="meals[' + index + '][id]" value="' + id + '">' +
        selectHtml +
        '<button type="button" class="btn-icon btn-icon-danger" onclick="this.parentElement.remove(); reindexMeals(document.getElementById(\'edit-plan-meals\'), \'meals\')"><i class="fas fa-times"></i></button>';
    container.appendChild(div);
}

// View plan handler
document.querySelectorAll('[data-action="view-plan"]').forEach(button => {
    button.addEventListener('click', function() {
        const planId = this.dataset.id;
        const planCard = this.closest('.plan-card');
        const planName = planCard ? planCard.querySelector('h4').textContent : 'Meal Plan';
        
        // Find the meals data from the edit button on the same card
        const editBtn = planCard ? planCard.querySelector('[data-action="edit-plan"]') : null;
        const meals = editBtn ? JSON.parse(editBtn.dataset.meals || '[]') : [];
        
        let mealsHtml = '<div style="padding: 20px;">';
        mealsHtml += '<h3 style="margin-bottom: 16px;"><i class="fas fa-utensils"></i> ' + planName + '</h3>';
        
        if (meals.length === 0) {
            mealsHtml += '<p style="color: var(--text-dim);">No meals in this plan yet.</p>';
        } else {
            mealsHtml += '<div style="display: grid; gap: 12px;">';
            meals.forEach(meal => {
                mealsHtml += '<div style="padding: 12px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px;">' +
                    '<strong>' + (meal.food_name || meal.name || 'Meal') + '</strong>' +
                    '<div style="display: flex; gap: 16px; margin-top: 8px; color: var(--text-dim); font-size: 13px;">' +
                    (meal.calories ? '<span><i class="fas fa-fire"></i> ' + meal.calories + ' cal</span>' : '') +
                    (meal.protein_g ? '<span><i class="fas fa-drumstick-bite"></i> ' + meal.protein_g + 'g protein</span>' : '') +
                    (meal.carbs_g ? '<span><i class="fas fa-bread-slice"></i> ' + meal.carbs_g + 'g carbs</span>' : '') +
                    (meal.fat_g ? '<span><i class="fas fa-cheese"></i> ' + meal.fat_g + 'g fat</span>' : '') +
                    '</div>' +
                    '</div>';
            });
            mealsHtml += '</div>';
        }
        mealsHtml += '</div>';
        
        // Use the view modal or create a simple alert
        if (document.getElementById('view-plan-modal')) {
            document.getElementById('view-plan-modal').querySelector('.modal-body').innerHTML = mealsHtml;
            openModal('view-plan-modal');
        } else {
            showToast('Meals in this plan:\\n\\n' + meals.map(m => '• ' + (m.food_name || m.name || 'Meal')).join('\\n'), 'info');
        }
    });
});

// Delete plan handler
document.querySelectorAll('[data-action="delete-plan"]').forEach(button => {
    button.addEventListener('click', async function() {
        const id = this.dataset.id;
        const name = this.dataset.name;
        if (await showConfirmModal('Are you sure you want to delete "' + name + '"? This will also remove all athlete assignments.')) {
            const csrfToken = document.querySelector('input[name="csrf_token"]').value;
            fetch('process_nutrition.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=delete_plan&id=' + id + '&csrf_token=' + encodeURIComponent(csrfToken)
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) { persistToast(data.message || 'Operation completed successfully', 'success'); location.reload(); }
                else showToast('Error: ' + data.message, 'error');
            });
        }
    });
});

// Add meal to plan (Create tab)
let mealCount = 0;
let mealSelectorTarget = 'create'; // 'create' or 'edit'

document.getElementById('add-meal-to-plan').addEventListener('click', function() {
    mealSelectorTarget = 'create';
    openModal('meal-selector-modal');
});

document.getElementById('edit-add-meal-to-plan').addEventListener('click', function() {
    mealSelectorTarget = 'edit';
    openModal('meal-selector-modal');
});

function selectMealForPlan(id, name) {
    if (mealSelectorTarget === 'edit') {
        addMealToEditPlan(id, name, 'breakfast');
        reindexMeals(document.getElementById('edit-plan-meals'), 'meals');
        initDragAndDrop(document.getElementById('edit-plan-meals'), 'edit_meals');
    } else {
        const container = document.getElementById('selected-meals');
        const index = mealCount++;
        
        const div = document.createElement('div');
        div.className = 'selected-meal-item';
        div.draggable = true;
        div.innerHTML = '<span class="drag-handle"><i class="fas fa-grip-vertical"></i></span>' +
            '<span>' + name + '</span>' +
            '<input type="hidden" name="meals[' + index + '][id]" value="' + id + '">' +
            '<select name="meals[' + index + '][type]" class="form-input">' +
                '<option value="breakfast">Breakfast</option>' +
                '<option value="lunch">Lunch</option>' +
                '<option value="dinner">Dinner</option>' +
                '<option value="snack">Snack</option>' +
                '<option value="pre_workout">Pre-Workout</option>' +
                '<option value="post_workout">Post-Workout</option>' +
            '</select>' +
            '<button type="button" class="btn-icon btn-icon-danger" onclick="this.parentElement.remove(); reindexMeals(document.getElementById(\'selected-meals\'), \'meals\')"><i class="fas fa-times"></i></button>';
        container.appendChild(div);
        
        initDragAndDrop(container, 'create_meals');
    }
    
    closeModal('meal-selector-modal');
}

// Reindex meal hidden inputs after reorder or removal
function reindexMeals(container, prefix) {
    const items = container.querySelectorAll('.selected-meal-item');
    items.forEach((item, i) => {
        const hiddenInput = item.querySelector('input[type="hidden"][name*="[id]"]');
        const selectInput = item.querySelector('select[name*="[type]"]');
        if (hiddenInput) hiddenInput.name = prefix + '[' + i + '][id]';
        if (selectInput) selectInput.name = prefix + '[' + i + '][type]';
    });
}

// Drag and drop support for meal reordering
function initDragAndDrop(container, context) {
    const items = container.querySelectorAll('.selected-meal-item');
    
    items.forEach(item => {
        // Remove old listeners by cloning
        item.removeAttribute('data-drag-init');
        
        item.addEventListener('dragstart', function(e) {
            this.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', '');
        });
        
        item.addEventListener('dragend', function() {
            this.classList.remove('dragging');
            container.querySelectorAll('.selected-meal-item').forEach(el => el.classList.remove('drag-over'));
            // Reindex after drop
            reindexMeals(container, 'meals');
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
                const allItems = [...container.querySelectorAll('.selected-meal-item')];
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
                showToast('Error: ' + (data.message || 'Unknown error'), 'error');
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

<!-- Assign Athletes to Nutrition Plan Modal -->
<div id="assign-nutrition-athletes-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-users"></i> Assign Athletes to Nutrition Plan</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('assign-nutrition-athletes-modal')">&times;</button>
        </div>
        <form id="assign-nutrition-athletes-form" method="POST" action="process_nutrition.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="assign_athletes">
            <input type="hidden" name="nutrition_plan_id" id="assign-nutrition-plan-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Nutrition Plan</label>
                    <input type="text" id="assign-nutrition-plan-name" class="form-input" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Athletes</label>
                    <div id="nutrition-athlete-typeahead"></div>
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
                    <h4 class="section-title"><i class="fas fa-balance-scale"></i> Custom Portion Settings (Optional)</h4>
                    <p class="info-text" style="margin-bottom: 16px;">
                        <i class="fas fa-info-circle"></i>
                        Override default serving sizes for each meal. Leave blank to use plan defaults.
                    </p>
                    <div id="meal-settings-container">
                        <!-- Meal settings will be populated dynamically -->
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('assign-nutrition-athletes-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Assign Athletes</button>
            </div>
        </form>
    </div>
</div>

<style>
.meal-setting-row {
    display: grid;
    grid-template-columns: 2fr 1fr 2fr;
    gap: 10px;
    padding: 12px;
    background: var(--bg-main);
    border-radius: var(--radius-md);
    margin-bottom: 8px;
    align-items: center;
}
.meal-setting-row label {
    font-weight: 600;
    font-size: var(--font-size-sm);
}
.meal-setting-row input {
    padding: 6px 10px;
    font-size: var(--font-size-sm);
}
.meal-settings-header {
    display: grid;
    grid-template-columns: 2fr 1fr 2fr;
    gap: 10px;
    padding: 8px 12px;
    font-size: var(--font-size-xs);
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
}
</style>

<script>
// Assign Athletes to Nutrition Plan Modal Handler
document.querySelectorAll('[data-action="assign-athletes"]').forEach(btn => {
    btn.addEventListener('click', function() {
        const planId = this.dataset.id;
        const planName = this.dataset.name;
        const meals = JSON.parse(this.dataset.meals || '[]');
        
        document.getElementById('assign-nutrition-plan-id').value = planId;
        document.getElementById('assign-nutrition-plan-name').value = planName;
        
        // Build meal settings form
        const container = document.getElementById('meal-settings-container');
        if (meals.length > 0) {
            // Group meals by meal type
            const mealsByType = {};
            meals.forEach(meal => {
                const type = meal.meal_type || 'meal';
                if (!mealsByType[type]) mealsByType[type] = [];
                mealsByType[type].push(meal);
            });
            
            let html = '<div class="meal-settings-header"><span>Meal/Food</span><span>Serving Qty</span><span>Notes</span></div>';
            let idx = 0;
            Object.entries(mealsByType).forEach(([type, items]) => {
                html += `<div style="font-weight: bold; margin: 10px 0 5px; text-transform: capitalize;">${type.replace('_', ' ')}</div>`;
                items.forEach(item => {
                    html += `
                        <div class="meal-setting-row">
                            <label>${item.food_name || item.meal_type || 'Item'}</label>
                            <input type="hidden" name="meals[${idx}][meal_id]" value="${item.id}">
                            <input type="hidden" name="meals[${idx}][food_id]" value="${item.food_id || ''}">
                            <input type="number" name="meals[${idx}][custom_serving_quantity]" placeholder="1.0" class="form-input" step="0.25" min="0.25">
                            <input type="text" name="meals[${idx}][custom_portion_notes]" placeholder="e.g., extra protein" class="form-input">
                        </div>
                    `;
                    idx++;
                });
            });
            container.innerHTML = html;
        } else {
            container.innerHTML = '<p class="info-text">No meals in this plan yet.</p>';
        }
        
        openModal('assign-nutrition-athletes-modal');
    });
});

// Form submission with AJAX
document.getElementById('assign-nutrition-athletes-form').addEventListener('submit', function(e) {
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
            closeModal('assign-nutrition-athletes-modal');
            persistToast(data.message || 'Operation completed successfully', 'success');
            location.reload();
        } else {
            showToast('Error: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(err => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        console.error(err);
        showToast('An error occurred. Please try again.', 'error');
    });
});
</script>
<script>
new ArcticTypeahead({
    container: '#nutrition-athlete-typeahead',
    name: 'athlete_ids',
    placeholder: 'Search for athletes…',
    roles: 'athlete',
    multiple: true
});
</script>
