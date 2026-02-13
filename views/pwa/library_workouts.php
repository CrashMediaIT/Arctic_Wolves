<?php
/**
 * PWA Workout Library - Mobile-native workout library
 * Purpose-built for mobile phones.
 */

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

$totalWorkouts = count($workouts);
?>
<style>
.m-libworkouts { padding: 16px; font-family: Inter, sans-serif; }
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
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
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
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
