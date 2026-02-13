<?php
/**
 * PWA Workouts - Mobile-native assigned workouts for user
 * Purpose-built for mobile phones.
 */

$workouts = [];
try {
    $stmt = $pdo->prepare("
        SELECT w.id, w.title, w.difficulty_level, w.duration_minutes, uw.status
        FROM user_workouts uw
        JOIN workouts w ON w.id = uw.workout_id
        WHERE uw.user_id = ?
        ORDER BY uw.assigned_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $workouts = []; }

$totalWorkouts = count($workouts);
?>
<style>
.m-workouts { padding: 16px; font-family: Inter, sans-serif; }
.m-workouts-header { margin-bottom: 16px; }
.m-workouts-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-workouts-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-wk-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-wk-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-wk-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-wk-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-wk-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-wk-badge-assigned { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-wk-badge-in_progress { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-wk-badge-skipped { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-wk-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-wk-meta { font-size: 12px; color: #A8A8B8; display: flex; gap: 10px; flex-wrap: wrap; }
.m-wk-meta i { font-size: 10px; }
.m-wk-diff-easy { color: #10B981; }
.m-wk-diff-medium { color: #F59E0B; }
.m-wk-diff-hard { color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-workouts">
    <div class="m-workouts-header">
        <h2 class="m-workouts-title">My Workouts</h2>
        <p class="m-workouts-sub"><?= $totalWorkouts ?> workout<?= $totalWorkouts !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($workouts)): ?>
        <div class="m-empty-state">
            <i class="fas fa-dumbbell"></i>
            <p>No workouts assigned</p>
        </div>
    <?php else: ?>
        <?php foreach ($workouts as $w):
            $status = strtolower($w['status'] ?? 'assigned');
            $badgeClass = match($status) {
                'completed' => 'completed',
                'assigned', 'pending' => 'assigned',
                'in_progress' => 'in_progress',
                'skipped' => 'skipped',
                default => 'default',
            };
            $diff = strtolower($w['difficulty_level'] ?? 'medium');
            $diffColorClass = match($diff) {
                'easy', 'beginner' => 'easy',
                'medium', 'intermediate' => 'medium',
                'hard', 'advanced' => 'hard',
                default => 'medium',
            };
        ?>
        <div class="m-wk-card">
            <div class="m-wk-top">
                <span class="m-wk-name"><?= htmlspecialchars($w['title']) ?></span>
                <span class="m-wk-badge m-wk-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst(str_replace('_', ' ', $status))) ?></span>
            </div>
            <div class="m-wk-meta">
                <?php if (!empty($w['difficulty_level'])): ?>
                <span class="m-wk-diff-<?= $diffColorClass ?>"><i class="fas fa-signal"></i> <?= htmlspecialchars(ucfirst($diff)) ?></span>
                <?php endif; ?>
                <?php if (!empty($w['duration_minutes'])): ?>
                <span><i class="fas fa-clock"></i> <?= (int)$w['duration_minutes'] ?> min</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
