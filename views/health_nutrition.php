<?php
// Get nutrition plan for athlete
$nutrition_query = "
    SELECT np.*, 
           CONCAT(c.first_name, ' ', c.last_name) as coach_name
    FROM nutrition_plans np
    LEFT JOIN users c ON np.coach_id = c.id
    WHERE np.athlete_id = ? AND np.status = 'active'
    ORDER BY np.created_at DESC
    LIMIT 1
";
$nutrition_stmt = $pdo->prepare($nutrition_query);
$nutrition_stmt->execute([$user_id]);
$nutrition_plan = $nutrition_stmt->fetch();

// Get today's meals
$meals_query = "
    SELECT m.*, ml.is_logged, ml.logged_at
    FROM meals m
    LEFT JOIN meal_logs ml ON ml.meal_id = m.id AND DATE(ml.logged_at) = CURDATE() AND ml.athlete_id = ?
    WHERE m.plan_id = ? AND m.day_of_week = DAYOFWEEK(CURDATE())
    ORDER BY m.meal_time
";
$meals = [];
$daily_totals = [
    'calories' => 0,
    'protein' => 0,
    'carbs' => 0,
    'fats' => 0,
    'calories_goal' => $nutrition_plan['daily_calories'] ?? 2500,
    'protein_goal' => $nutrition_plan['daily_protein'] ?? 180,
    'carbs_goal' => $nutrition_plan['daily_carbs'] ?? 300,
    'fats_goal' => $nutrition_plan['daily_fats'] ?? 70
];

if ($nutrition_plan) {
    $meals_stmt = $pdo->prepare($meals_query);
    $meals_stmt->execute([$user_id, $nutrition_plan['id']]);
    $meals = $meals_stmt->fetchAll();
    
    // Calculate daily totals from logged meals
    foreach ($meals as $meal) {
        if ($meal['is_logged']) {
            $daily_totals['calories'] += $meal['calories'];
            $daily_totals['protein'] += $meal['protein_g'];
            $daily_totals['carbs'] += $meal['carbs_g'];
            $daily_totals['fats'] += $meal['fats_g'];
        }
    }
}
?>

<!-- Health Nutrition View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-apple-alt"></i> Nutrition Plan
    </h1>
    <p class="page-description">Fuel your performance with proper nutrition</p>
</div>

<div class="nutrition-content">
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
            <button class="btn-primary" data-action="log-meal"><i class="fas fa-plus"></i> Log Meal</button>
        </div>
        <div class="card-body">
            <div class="meals-timeline">
                <?php if (count($meals) > 0): ?>
                    <?php foreach ($meals as $meal): ?>
                    <div class="meal-item <?= $meal['is_logged'] ? 'completed' : 'pending' ?>" data-component="MealItem" data-meal-id="<?= $meal['id'] ?>">
                        <div class="meal-time">
                            <i class="fas fa-<?= $meal['is_logged'] ? 'check-circle' : 'circle' ?>"></i>
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
                            <button class="btn-secondary btn-small" data-action="log-meal" data-meal-id="<?= $meal['id'] ?>">Log</button>
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
    <div class="placeholder-container">
        <i class="fas fa-apple-alt placeholder-icon"></i>
        <p class="placeholder-text">No active nutrition plan. Contact your coach to create one.</p>
    </div>
    <?php endif; ?>

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

<style>
.daily-overview-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
}

.daily-overview-card h3 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 25px;
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
    padding: 25px;
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
    margin-bottom: 15px;
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
</style>
