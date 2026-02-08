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
$nutrition_plan = decryptUserRow($nutrition_plan);

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

// No demo nutrition data - show empty state when no real plan exists
$is_demo_nutrition = false;
?>

<!-- Health Nutrition View -->
<div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
    <div>
        <h1 class="page-title">
            <i class="fas fa-apple-alt"></i> Nutrition Plan
        </h1>
        <p class="page-description">Fuel your performance with proper nutrition</p>
    </div>
    <button class="btn-primary" data-action="contact" data-modal="contact-coach-modal">
        <i class="fas fa-envelope"></i> Contact Coach
    </button>
</div>

<!-- Time Filter -->
<div class="nutrition-filter-bar">
    <span class="filter-label">View:</span>
    <div class="time-filter-buttons">
        <button class="time-filter-btn active" data-filter="day" onclick="setNutritionFilter('day')">Day</button>
        <button class="time-filter-btn" data-filter="week" onclick="setNutritionFilter('week')">Week</button>
        <button class="time-filter-btn" data-filter="month" onclick="setNutritionFilter('month')">Month</button>
    </div>
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
    
    <!-- Meal Plan History Section -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Meal Plan History</h3>
        </div>
        <div class="card-body">
            <div class="meal-history-list">
                <?php
                // Fetch real meal plan history from database
                $meal_history = [];
                try {
                    $meal_history_query = "
                        SELECT 
                            DATE(ml.logged_at) as date,
                            np.name as plan_name,
                            COALESCE(SUM(ml.calories), 0) as calories_logged,
                            np.target_calories,
                            COUNT(DISTINCT ml.id) as meals_completed,
                            (SELECT COUNT(*) FROM nutrition_plan_meals WHERE nutrition_plan_id = np.id) as total_meals
                        FROM meal_logs ml
                        INNER JOIN nutrition_plans np ON ml.nutrition_plan_id = np.id
                        WHERE np.user_id = ?
                        GROUP BY DATE(ml.logged_at), np.id, np.name, np.target_calories
                        ORDER BY DATE(ml.logged_at) DESC
                        LIMIT 10
                    ";
                    $meal_history_stmt = $pdo->prepare($meal_history_query);
                    $meal_history_stmt->execute([$user_id]);
                    $meal_history = $meal_history_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Error fetching meal history: " . $e->getMessage());
                    $meal_history = [];
                }
                
                if (count($meal_history) > 0):
                    foreach ($meal_history as $history):
                        $completion_percent = $history['total_meals'] > 0 ? ($history['meals_completed'] / $history['total_meals']) * 100 : 0;
                        $calorie_percent = $history['target_calories'] > 0 ? ($history['calories_logged'] / $history['target_calories']) * 100 : 0;
                ?>
                <div class="meal-history-item" 
                     data-date="<?= htmlspecialchars($history['date'], ENT_QUOTES, 'UTF-8') ?>"
                     onclick="viewMealHistoryDetails(this.dataset.date)">
                    <div class="history-date">
                        <span class="date-day"><?= date('d', strtotime($history['date'])) ?></span>
                        <span class="date-month"><?= date('M', strtotime($history['date'])) ?></span>
                    </div>
                    <div class="history-details">
                        <h4><?= htmlspecialchars($history['plan_name']) ?></h4>
                        <div class="history-meta">
                            <span><i class="fas fa-fire"></i> <?= number_format($history['calories_logged']) ?> / <?= number_format($history['target_calories']) ?> cal</span>
                            <span><i class="fas fa-utensils"></i> <?= $history['meals_completed'] ?>/<?= $history['total_meals'] ?> meals</span>
                        </div>
                    </div>
                    <div class="history-status">
                        <?php if ($completion_percent >= 100.0 && $calorie_percent >= 90): ?>
                            <span class="status-badge completed"><i class="fas fa-check"></i> On Track</span>
                        <?php elseif ($completion_percent >= 80): ?>
                            <span class="status-badge partial"><i class="fas fa-exclamation"></i> <?= number_format($calorie_percent) ?>%</span>
                        <?php else: ?>
                            <span class="status-badge low"><i class="fas fa-times"></i> Incomplete</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-icon"><i class="fas fa-chevron-right"></i></button>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <div class="placeholder-container">
                    <i class="fas fa-history placeholder-icon"></i>
                    <p class="placeholder-text">No meal history yet. Log your first meal to see it here!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Contact Coach Modal -->
<div class="modal" id="contact-coach-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Contact Your Coach</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('contact-coach-modal')">&times;</button>
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
/* Nutrition Filter Bar */
.nutrition-filter-bar {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 24px;
    padding: 16px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.filter-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
}

.time-filter-buttons {
    display: flex;
    gap: 8px;
}

.time-filter-btn {
    padding: 8px 16px;
    border: 1px solid var(--border);
    background: var(--bg-main);
    color: var(--text-dim);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.time-filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.time-filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

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
    color: var(--primary);
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
    color: var(--primary);
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
    border: 1px solid var(--primary);
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
    color: var(--primary);
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
    border-color: var(--primary);
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
    border-color: var(--primary);
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
    color: var(--primary);
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
    border-color: var(--primary);
    transform: translateY(-3px);
}

.tip-card i {
    font-size: 36px;
    color: var(--primary);
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

/* Meal History Styles */
.meal-history-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.meal-history-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.meal-history-item:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.meal-history-item .history-date {
    min-width: 50px;
    text-align: center;
    padding: 8px;
    background: var(--bg-card);
    border-radius: 8px;
}

.meal-history-item .history-date .date-day {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    line-height: 1;
}

.meal-history-item .history-date .date-month {
    display: block;
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.meal-history-item .history-details {
    flex: 1;
}

.meal-history-item .history-details h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.meal-history-item .history-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--text-dim);
}

.meal-history-item .history-meta i {
    color: var(--primary);
    margin-right: 5px;
}

.meal-history-item .history-status .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 700;
}

.meal-history-item .status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.meal-history-item .status-badge.partial {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.meal-history-item .status-badge.low {
    background: rgba(239, 68, 68, 0.1);
    color: #ef4444;
}

.meal-history-item .btn-icon {
    width: 32px;
    height: 32px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.meal-history-item:hover .btn-icon {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.placeholder-container {
    text-align: center;
    padding: 40px 20px;
}

.placeholder-icon {
    font-size: 48px;
    color: var(--text-dim);
    opacity: 0.3;
    margin-bottom: 16px;
}

.placeholder-text {
    color: var(--text-dim);
    font-size: 14px;
}
</style>

<!-- Log Meal Modal -->
<div class="log-meal-modal" id="logMealModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3><i class="fas fa-utensils"></i> Log Meal</h3>
            <button class="modal-close" aria-label="Close modal" onclick="closeLogMealModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="logMealForm" method="POST" action="process_nutrition.php">
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
    
    // Create icon element separately to avoid XSS
    const iconEl = document.createElement('i');
    iconEl.className = 'fas fa-' + icon;
    
    const textEl = document.createElement('span');
    textEl.textContent = message;
    
    alertDiv.appendChild(iconEl);
    alertDiv.appendChild(textEl);
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

// Nutrition time filter function
function setNutritionFilter(filter) {
    // Update button states
    document.querySelectorAll('.nutrition-filter-bar .time-filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === filter) {
            btn.classList.add('active');
        }
    });
    
    // Show notification about filter change
    showNutritionNotification('Showing ' + filter + ' view of meal plans', 'info');
}

// View meal history details
function viewMealHistoryDetails(date) {
    showNutritionNotification('Loading meal plan details for ' + date, 'info');
}
</script>
