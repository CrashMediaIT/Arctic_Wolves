<?php
// Get nutrition plan for athlete
$nutrition_query = "
    SELECT np.*, 
           CONCAT(c.first_name, ' ', c.last_name) as coach_name
    FROM nutrition_plans np
    LEFT JOIN users c ON np.coach_id = c.id
    WHERE np.user_id = ?
    ORDER BY np.created_at DESC
    LIMIT 1
";
$nutrition_stmt = $pdo->prepare($nutrition_query);
$nutrition_stmt->execute([$user_id]);
$nutrition_plan = $nutrition_stmt->fetch();

// Initialize daily totals - will be set properly below based on plan existence
$daily_totals = [
    'calories' => 0,
    'protein' => 0,
    'carbs' => 0,
    'fats' => 0,
    'calories_goal' => 2500,
    'protein_goal' => 180,
    'carbs_goal' => 300,
    'fats_goal' => 70
];

// Get nutrition plan meals if plan exists
$meals = [];
if ($nutrition_plan) {
    $meals_query = "
        SELECT npm.*
        FROM nutrition_plan_meals npm
        WHERE npm.nutrition_plan_id = ?
        ORDER BY npm.meal_order
    ";
    $meals_stmt = $pdo->prepare($meals_query);
    $meals_stmt->execute([$nutrition_plan['id']]);
    $meals = $meals_stmt->fetchAll();
    
    // Update daily totals with plan targets
    $daily_totals['calories_goal'] = $nutrition_plan['target_calories'] ?? 2500;
    $daily_totals['protein_goal'] = $nutrition_plan['target_protein_g'] ?? 180;
    $daily_totals['carbs_goal'] = $nutrition_plan['target_carbs_g'] ?? 300;
    $daily_totals['fats_goal'] = $nutrition_plan['target_fat_g'] ?? 70;
}

// Demo nutrition data if no real plan exists
if (!$nutrition_plan) {
    // Create demo nutrition plan
    $nutrition_plan = [
        'id' => 'demo',
        'name' => 'Hockey Performance Plan',
        'target_calories' => 2500,
        'target_protein_g' => 180,
        'target_carbs_g' => 300,
        'target_fat_g' => 70,
        'coach_name' => 'Coach Martinez'
    ];
    
    $daily_totals = [
        'calories' => 1850,
        'protein' => 95,
        'carbs' => 210,
        'fats' => 45,
        'calories_goal' => 2500,
        'protein_goal' => 180,
        'carbs_goal' => 300,
        'fats_goal' => 70
    ];
    
    // Demo meals
    $meals = [
        [
            'id' => 1,
            'meal_name' => 'Breakfast',
            'meal_time' => '07:00:00',
            'is_logged' => true,
            'meal_type_icon' => 'sun',
            'protein_g' => 35,
            'carbs_g' => 45,
            'fats_g' => 12,
            'foods_json' => json_encode([
                ['name' => 'Scrambled Eggs (3)', 'calories' => 270],
                ['name' => 'Whole Wheat Toast (2)', 'calories' => 160],
                ['name' => 'Greek Yogurt', 'calories' => 120],
                ['name' => 'Orange Juice', 'calories' => 110]
            ])
        ],
        [
            'id' => 2,
            'meal_name' => 'Morning Snack',
            'meal_time' => '10:00:00',
            'is_logged' => true,
            'meal_type_icon' => 'apple-alt',
            'protein_g' => 15,
            'carbs_g' => 30,
            'fats_g' => 8,
            'foods_json' => json_encode([
                ['name' => 'Protein Bar', 'calories' => 200],
                ['name' => 'Banana', 'calories' => 105]
            ])
        ],
        [
            'id' => 3,
            'meal_name' => 'Lunch',
            'meal_time' => '12:30:00',
            'is_logged' => false,
            'meal_type_icon' => 'utensils',
            'protein_g' => 45,
            'carbs_g' => 60,
            'fats_g' => 15,
            'foods_json' => json_encode([
                ['name' => 'Grilled Chicken Breast', 'calories' => 280],
                ['name' => 'Brown Rice (1 cup)', 'calories' => 215],
                ['name' => 'Steamed Broccoli', 'calories' => 55],
                ['name' => 'Mixed Salad', 'calories' => 45]
            ])
        ],
        [
            'id' => 4,
            'meal_name' => 'Pre-Workout',
            'meal_time' => '15:00:00',
            'is_logged' => false,
            'meal_type_icon' => 'bolt',
            'protein_g' => 20,
            'carbs_g' => 40,
            'fats_g' => 5,
            'foods_json' => json_encode([
                ['name' => 'Protein Shake', 'calories' => 150],
                ['name' => 'Apple', 'calories' => 95],
                ['name' => 'Almonds (handful)', 'calories' => 80]
            ])
        ],
        [
            'id' => 5,
            'meal_name' => 'Dinner',
            'meal_time' => '18:30:00',
            'is_logged' => false,
            'meal_type_icon' => 'moon',
            'protein_g' => 50,
            'carbs_g' => 55,
            'fats_g' => 20,
            'foods_json' => json_encode([
                ['name' => 'Salmon Fillet', 'calories' => 350],
                ['name' => 'Sweet Potato', 'calories' => 180],
                ['name' => 'Asparagus', 'calories' => 40],
                ['name' => 'Quinoa', 'calories' => 120]
            ])
        ]
    ];
    
    $is_demo_nutrition = true;
} else {
    $is_demo_nutrition = false;
}
?>

<!-- Health Nutrition View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-apple-alt"></i> Nutrition Plan
    </h1>
    <p class="page-description">Fuel your performance with proper nutrition</p>
</div>

<?php if (isset($is_demo_nutrition) && $is_demo_nutrition): ?>
<div class="demo-data-notice">
    <i class="fas fa-info-circle"></i>
    <span>Showing demo nutrition plan. Contact your coach for a personalized plan.</span>
</div>
<?php endif; ?>

<div class="nutrition-content">
    <!-- Nutrition Plan Section (Always show header) -->
    <div class="content-section">
        <div class="section-header-main">
            <h2><i class="fas fa-apple-alt"></i> Active Nutrition Plan</h2>
        </div>
        
        <?php if ($nutrition_plan): ?>
        <!-- Daily Overview Card -->
        <div class="daily-overview-card" data-component="NutritionOverview">
            <h3><i class="fas fa-calendar-day"></i> Today's Nutrition</h3>
            <div class="macros-grid">
                <div class="macro-card">
                    <div class="macro-circle calories">
                        <div class="macro-value"><?= $daily_totals['calories'] ?></div>
                        <div class="macro-target">/ <?= $daily_totals['calories_goal'] ?></div>
                    </div>
                    <div class="macro-label">Calories</div>
                </div>
                <div class="macro-card">
                    <div class="macro-circle protein">
                        <div class="macro-value"><?= $daily_totals['protein'] ?>g</div>
                        <div class="macro-target">/ <?= $daily_totals['protein_goal'] ?>g</div>
                    </div>
                    <div class="macro-label">Protein</div>
                </div>
                <div class="macro-card">
                    <div class="macro-circle carbs">
                        <div class="macro-value"><?= $daily_totals['carbs'] ?>g</div>
                        <div class="macro-target">/ <?= $daily_totals['carbs_goal'] ?>g</div>
                    </div>
                    <div class="macro-label">Carbs</div>
                </div>
                <div class="macro-card">
                    <div class="macro-circle fats">
                        <div class="macro-value"><?= $daily_totals['fats'] ?>g</div>
                        <div class="macro-target">/ <?= $daily_totals['fats_goal'] ?>g</div>
                    </div>
                    <div class="macro-label">Fats</div>
                </div>
            </div>
        </div>

        <!-- Meal Plan -->
        <div class="content-card">
            <div class="card-header">
                <h3><i class="fas fa-utensils"></i> Today's Meal Plan</h3>
                <div class="meal-header-actions">
                    <div class="day-selector">
                        <button class="btn-icon" id="prevDay" onclick="changeDay(-1)"><i class="fas fa-chevron-left"></i></button>
                        <span class="current-day" id="currentDayDisplay"><?= date('l, F j') ?></span>
                        <button class="btn-icon" id="nextDay" onclick="changeDay(1)"><i class="fas fa-chevron-right"></i></button>
                    </div>
                    <button class="btn-primary" onclick="openLogMealModal()"><i class="fas fa-plus"></i> Log Meal</button>
                </div>
            </div>
            <div class="card-body">
                <div class="meals-timeline">
                    <?php if (count($meals) > 0): ?>
                        <?php foreach ($meals as $meal): ?>
                        <div class="meal-item <?= $meal['is_logged'] ? 'completed' : 'pending' ?>" data-component="MealItem" data-meal-id="<?= $meal['id'] ?>">
                            <div class="meal-checkbox" onclick="toggleMealLogged(this, <?= $meal['id'] ?>)">
                                <input type="checkbox" id="meal-check-<?= $meal['id'] ?>" <?= $meal['is_logged'] ? 'checked' : '' ?>>
                                <label for="meal-check-<?= $meal['id'] ?>"></label>
                            </div>
                            <div class="meal-time">
                                <span><?= date('g:i A', strtotime($meal['meal_time'])) ?></span>
                            </div>
                            <div class="meal-content">
                                <h4><i class="fas fa-<?= $meal['meal_type_icon'] ?? 'utensils' ?>"></i> <?= htmlspecialchars($meal['meal_name']) ?></h4>
                                <ul class="meal-foods">
                                    <?php 
                                    $foods = json_decode($meal['foods_json'], true) ?? [];
                                    foreach ($foods as $food): 
                                    ?>
                                        <li><?= htmlspecialchars($food['name']) ?> - <?= $food['calories'] ?> cal</li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="meal-macros">
                                    <span><strong>P:</strong> <?= $meal['protein_g'] ?>g</span>
                                    <span><strong>C:</strong> <?= $meal['carbs_g'] ?>g</span>
                                    <span><strong>F:</strong> <?= $meal['fats_g'] ?>g</span>
                                </div>
                            </div>
                            <?php if (!$meal['is_logged']): ?>
                                <button class="btn-secondary btn-small" onclick="logSingleMeal(<?= $meal['id'] ?>)"><i class="fas fa-check"></i> Log</button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="placeholder-text">No meal plan for today. Contact your coach for a nutrition plan.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-state-card">
            <i class="fas fa-apple-alt empty-state-icon"></i>
            <h3 class="empty-state-title">No Nutrition Plan Currently Assigned</h3>
            <p class="empty-state-text">Get a customized nutrition plan from your coach to optimize your performance and recovery. Proper nutrition is key to athletic success!</p>
            <button class="btn-secondary" data-action="contact" data-modal="contact-coach-modal"><i class="fas fa-envelope"></i> Contact Coach</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Nutrition Tips -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-lightbulb"></i> Nutrition Tips</h3>
        </div>
        <div class="card-body">
            <div class="tips-grid">
                <div class="tip-card">
                    <i class="fas fa-tint"></i>
                    <h4>Stay Hydrated</h4>
                    <p>Drink at least 8-10 glasses of water daily, more on training days.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-clock"></i>
                    <h4>Meal Timing</h4>
                    <p>Eat within 30-60 minutes after training for optimal recovery.</p>
                </div>
                <div class="tip-card">
                    <i class="fas fa-carrot"></i>
                    <h4>Eat the Rainbow</h4>
                    <p>Include a variety of colorful fruits and vegetables for nutrients.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Contact Coach Modal -->
<div class="modal" id="contact-coach-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Contact Your Coach</h2>
            <button class="modal-close" onclick="closeModal('contact-coach-modal')">&times;</button>
        </div>
        <form method="POST" action="process_contact.php" id="contact-coach-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="send_message">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-input" placeholder="Enter message subject" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-textarea" rows="6" placeholder="Type your message here..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-input">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('contact-coach-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Message</button>
            </div>
        </form>
    </div>
</div>

<style>
.content-section {
    margin-bottom: 24px;
}

.section-header-main {
    margin-bottom: 20px;
}

.section-header-main h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header-main h2 i {
    color: var(--neon);
}

.empty-state-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 60px 40px;
    text-align: center;
}

.empty-state-icon {
    font-size: 64px;
    color: var(--neon);
    opacity: 0.3;
    display: block;
    margin-bottom: 20px;
}

.empty-state-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 12px;
}

.empty-state-text {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 24px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.daily-overview-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.daily-overview-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 24px;
}

.daily-overview-card h3 i {
    color: var(--neon);
    margin-right: 10px;
}

.macros-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 25px;
}

.macro-card {
    text-align: center;
}

.macro-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    border: 4px solid;
    position: relative;
}

.macro-circle.calories {
    border-color: var(--neon);
    background: rgba(255, 77, 0, 0.1);
}

.macro-circle.protein {
    border-color: #3b82f6;
    background: rgba(59, 130, 246, 0.1);
}

.macro-circle.carbs {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.macro-circle.fats {
    border-color: #f59e0b;
    background: rgba(245, 158, 11, 0.1);
}

.macro-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    line-height: 1;
}

.macro-target {
    font-size: 12px;
    color: var(--text-dim);
}

.macro-label {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.meals-timeline {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.meal-item {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
}

.meal-item:hover {
    border-color: var(--neon);
}

.meal-item.completed {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.meal-time {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    min-width: 80px;
}

.meal-time i {
    font-size: 24px;
    color: var(--border);
}

.meal-item.completed .meal-time i {
    color: #10b981;
}

.meal-item.pending .meal-time i {
    color: var(--text-dim);
}

.meal-time span {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
}

.meal-content {
    flex: 1;
}

.meal-content h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 12px;
}

.meal-content h4 i {
    color: var(--neon);
    margin-right: 8px;
}

.meal-foods {
    list-style: none;
    margin-bottom: 12px;
}

.meal-foods li {
    font-size: 14px;
    color: var(--text-dim);
    padding: 5px 0;
}

.meal-macros {
    display: flex;
    gap: 20px;
    font-size: 13px;
    color: var(--text-dim);
}

.meal-macros strong {
    color: var(--text-white);
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
}

.tip-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    transition: all 0.3s;
}

.tip-card:hover {
    border-color: var(--neon);
    transform: translateY(-3px);
}

.tip-card i {
    font-size: 36px;
    color: var(--neon);
    display: block;
    margin-bottom: 12px;
}

.tip-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 10px;
}

.tip-card p {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
}

/* Demo Data Notice */
.demo-data-notice {
    background: rgba(107, 70, 193, 0.1);
    border: 1px solid rgba(107, 70, 193, 0.3);
    border-radius: 8px;
    padding: 12px 20px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--primary-light);
    font-size: 14px;
}

.demo-data-notice i {
    font-size: 16px;
}

/* Meal Header Actions */
.meal-header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
    flex-wrap: wrap;
}

/* Day Selector */
.day-selector {
    display: flex;
    align-items: center;
    gap: 12px;
}

.day-selector .btn-icon {
    width: 32px;
    height: 32px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    cursor: pointer;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    justify-content: center;
}

.day-selector .btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
}

.current-day {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white);
    min-width: 150px;
    text-align: center;
}

/* Meal Checkbox */
.meal-checkbox {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.meal-checkbox input[type="checkbox"] {
    width: 24px;
    height: 24px;
    accent-color: var(--primary);
    cursor: pointer;
    margin: 0;
}

.meal-checkbox input[type="checkbox"]:checked + label {
    color: #10b981;
}

/* Update meal item layout */
.meal-item {
    display: flex;
    gap: 16px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: all 0.3s;
    align-items: flex-start;
}

/* Log Meal Modal */
.log-meal-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    align-items: center;
    justify-content: center;
}

.log-meal-modal.active {
    display: flex;
}

.log-meal-modal .modal-content {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.log-meal-modal .modal-header {
    padding: 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.log-meal-modal .modal-header h3 {
    margin: 0;
    font-size: 18px;
    color: var(--text-white);
}

.log-meal-modal .modal-header h3 i {
    color: var(--primary);
    margin-right: 10px;
}

.log-meal-modal .modal-body {
    padding: 20px;
}

.log-meal-modal .modal-footer {
    padding: 16px 20px;
    border-top: 1px solid var(--border);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

.log-meal-modal .form-group {
    margin-bottom: 16px;
}

.log-meal-modal .form-group label {
    display: block;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
}

.log-meal-modal .form-input {
    width: 100%;
    padding: 10px 14px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    font-size: 14px;
}

.log-meal-modal .form-input:focus {
    outline: none;
    border-color: var(--primary);
}
</style>

<!-- Log Meal Modal -->
<div class="log-meal-modal" id="logMealModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-utensils"></i> Log Meal</h3>
            <button class="modal-close" onclick="closeLogMealModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="logMealForm">
                <div class="form-group">
                    <label>Meal Name *</label>
                    <input type="text" class="form-input" name="meal_name" placeholder="e.g., Breakfast, Lunch, Snack" required>
                </div>
                <div class="form-group">
                    <label>Foods Eaten</label>
                    <textarea class="form-input" name="foods" rows="3" placeholder="List the foods you ate..."></textarea>
                </div>
                <div class="form-group">
                    <label>Calories (estimated)</label>
                    <input type="number" class="form-input" name="calories" placeholder="e.g., 500">
                </div>
                <div class="form-group" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px;">
                    <div>
                        <label>Protein (g)</label>
                        <input type="number" class="form-input" name="protein" placeholder="0">
                    </div>
                    <div>
                        <label>Carbs (g)</label>
                        <input type="number" class="form-input" name="carbs" placeholder="0">
                    </div>
                    <div>
                        <label>Fats (g)</label>
                        <input type="number" class="form-input" name="fats" placeholder="0">
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeLogMealModal()"><i class="fas fa-times"></i> Cancel</button>
            <button class="btn btn-primary" onclick="submitLogMeal()"><i class="fas fa-check"></i> Log Meal</button>
        </div>
    </div>
</div>

<script>
// Current date for day selector
let currentSelectedDate = new Date();

// Change day function
function changeDay(delta) {
    currentSelectedDate.setDate(currentSelectedDate.getDate() + delta);
    updateDayDisplay();
    // In demo mode, just show a notification
    showNutritionNotification('Viewing meal plan for ' + formatDate(currentSelectedDate), 'info');
}

function formatDate(date) {
    const options = { weekday: 'long', month: 'long', day: 'numeric' };
    return date.toLocaleDateString('en-US', options);
}

function updateDayDisplay() {
    const display = document.getElementById('currentDayDisplay');
    if (display) {
        display.textContent = formatDate(currentSelectedDate);
    }
}

// Toggle meal logged status
function toggleMealLogged(element, mealId) {
    const checkbox = element.querySelector('input[type="checkbox"]');
    const mealItem = element.closest('.meal-item');
    
    if (checkbox.checked) {
        checkbox.checked = false;
        mealItem.classList.remove('completed');
        mealItem.classList.add('pending');
        showNutritionNotification('Meal unmarked', 'info');
    } else {
        checkbox.checked = true;
        mealItem.classList.add('completed');
        mealItem.classList.remove('pending');
        showNutritionNotification('Meal logged successfully!', 'success');
    }
}

// Log single meal
function logSingleMeal(mealId) {
    const mealItem = document.querySelector(`[data-meal-id="${mealId}"]`);
    const checkbox = mealItem?.querySelector('input[type="checkbox"]');
    
    if (checkbox) {
        checkbox.checked = true;
    }
    
    mealItem?.classList.add('completed');
    mealItem?.classList.remove('pending');
    
    // Hide the log button
    const logBtn = mealItem?.querySelector('.btn-small');
    if (logBtn) {
        logBtn.style.display = 'none';
    }
    
    showNutritionNotification('Meal logged successfully!', 'success');
}

// Log meal modal functions
function openLogMealModal() {
    document.getElementById('logMealModal').classList.add('active');
}

function closeLogMealModal() {
    document.getElementById('logMealModal').classList.remove('active');
    document.getElementById('logMealForm').reset();
}

function submitLogMeal() {
    const form = document.getElementById('logMealForm');
    const mealName = form.querySelector('[name="meal_name"]').value;
    
    if (!mealName) {
        showNutritionNotification('Please enter a meal name', 'error');
        return;
    }
    
    // In demo mode, just show success
    showNutritionNotification('Meal "' + mealName + '" logged successfully!', 'success');
    closeLogMealModal();
}

// Notification helper
function showNutritionNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'nutrition-notification';
    
    let icon = 'info-circle';
    let bgColor = 'rgba(59, 130, 246, 0.9)';
    
    if (type === 'error') {
        icon = 'exclamation-circle';
        bgColor = 'rgba(239, 68, 68, 0.9)';
    } else if (type === 'success') {
        icon = 'check-circle';
        bgColor = 'rgba(16, 185, 129, 0.9)';
    }
    
    alertDiv.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 280px;
        padding: 15px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        background: ${bgColor};
        color: #fff;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;
    
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 4000);
}
</script>
