<?php
/**
 * PWA Nutrition Library - Mobile-native meal plan library
 * Purpose-built for mobile phones.
 */

$mealPlans = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description, calories
        FROM meal_plans
        WHERE is_active = 1
        ORDER BY name
        LIMIT 30
    ");
    $stmt->execute();
    $mealPlans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $mealPlans = []; }

$totalPlans = count($mealPlans);
?>
<style>
.m-libnutrition { padding: 16px; font-family: Inter, sans-serif; }
.m-libnutrition-header { margin-bottom: 16px; }
.m-libnutrition-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-libnutrition-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-meal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-meal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-meal-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-meal-cal {
    font-size: 12px; font-weight: 700; color: #10B981; flex-shrink: 0;
}
.m-meal-desc { font-size: 12px; color: #A8A8B8; margin: 4px 0 0; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-libnutrition">
    <div class="m-libnutrition-header">
        <h2 class="m-libnutrition-title">Nutrition Library</h2>
        <p class="m-libnutrition-sub"><?= $totalPlans ?> meal plan<?= $totalPlans !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($mealPlans)): ?>
        <div class="m-empty-state">
            <i class="fas fa-utensils"></i>
            <p>No meal plans available</p>
        </div>
    <?php else: ?>
        <?php foreach ($mealPlans as $m): ?>
        <div class="m-meal-card">
            <div class="m-meal-top">
                <span class="m-meal-name"><?= htmlspecialchars($m['name']) ?></span>
                <?php if (!empty($m['calories'])): ?>
                <span class="m-meal-cal"><?= (int)$m['calories'] ?> cal</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($m['description'])): ?>
            <div class="m-meal-desc"><?= htmlspecialchars($m['description']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
