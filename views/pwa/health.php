<?php
/**
 * PWA Health - Mobile-native workouts and nutrition view
 * Purpose-built for mobile phones.
 */

// Workouts
$workouts = [];
try {
    $stmt = $pdo->prepare("SELECT id, title, description, difficulty_level, duration_minutes FROM workouts WHERE is_active = 1 ORDER BY created_at DESC LIMIT 20");
    $stmt->execute();
    $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $workouts = []; }

// Meal Plans
$mealPlans = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, calories FROM meal_plans WHERE is_active = 1 ORDER BY created_at DESC LIMIT 20");
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
.m-meal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-meal-title { font-size: 14px; font-weight: 600; color: #fff; margin: 0 0 4px; }
.m-meal-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-meal-cal {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 12px; color: #10B981; font-weight: 600;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

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
            ?>
            <div class="m-workout-card">
                <div class="m-workout-top">
                    <span class="m-workout-title"><?= htmlspecialchars($w['title'] ?? 'Untitled') ?></span>
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
            <?php foreach ($mealPlans as $mp): ?>
            <div class="m-meal-card">
                <h4 class="m-meal-title"><?= htmlspecialchars($mp['name'] ?? 'Untitled') ?></h4>
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
</div>

<script>
function mHealthTab(tabId, btn) {
    document.querySelectorAll('.m-tab-panel').forEach(function(p) { p.classList.remove('m-tab-visible'); });
    document.querySelectorAll('.m-tab').forEach(function(t) { t.classList.remove('m-tab-active'); });
    var panel = document.getElementById('m-panel-' + tabId);
    if (panel) panel.classList.add('m-tab-visible');
    if (btn) btn.classList.add('m-tab-active');
}
</script>
