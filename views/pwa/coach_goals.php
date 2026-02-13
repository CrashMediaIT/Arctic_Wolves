<?php
/**
 * PWA Coach Goals - Mobile-native goals assigned to athletes by coach
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT g.id, COALESCE(g.title, g.goal_title) as title, g.status,
               g.completion_percentage, u.first_name, u.last_name
        FROM goals g
        LEFT JOIN users u ON u.id = g.athlete_id
        WHERE g.coach_id = ?
        ORDER BY g.status ASC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

$totalGoals = count($goals);
?>
<style>
.m-coach-goals { padding: 16px; font-family: Inter, sans-serif; }
.m-coach-goals-header { margin-bottom: 16px; }
.m-coach-goals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coach-goals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cgoal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-cgoal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-cgoal-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-cgoal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-cgoal-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-cgoal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-cgoal-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-cgoal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-cgoal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-cgoal-athlete { font-size: 12px; color: #A8A8B8; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; }
.m-cgoal-progress { margin-bottom: 4px; }
.m-cgoal-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-cgoal-progress-label { font-size: 11px; color: #6B6B7B; }
.m-cgoal-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-cgoal-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-cgoal-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-coach-goals">
    <div class="m-coach-goals-header">
        <h2 class="m-coach-goals-title">Assigned Goals</h2>
        <p class="m-coach-goals-sub"><?= $totalGoals ?> goal<?= $totalGoals !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($goals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullseye"></i>
            <p>No goals assigned yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($goals as $g):
            $pct = max(0, min(100, (int)($g['completion_percentage'] ?? 0)));
            $status = strtolower($g['status'] ?? 'active');
            $badgeClass = match($status) {
                'active', 'in_progress' => 'active',
                'completed' => 'completed',
                'paused' => 'paused',
                'cancelled' => 'cancelled',
                default => 'default',
            };
            $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
            $athName = htmlspecialchars(($g['first_name'] ?? '') . ' ' . ($g['last_name'] ?? ''));
        ?>
        <div class="m-cgoal-card">
            <div class="m-cgoal-top">
                <span class="m-cgoal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                <span class="m-cgoal-badge m-cgoal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-cgoal-athlete">
                <i class="fas fa-user"></i> <?= $athName ?>
            </div>
            <div class="m-cgoal-progress">
                <div class="m-cgoal-progress-header">
                    <span class="m-cgoal-progress-label">Progress</span>
                    <span class="m-cgoal-progress-pct"><?= $pct ?>%</span>
                </div>
                <div class="m-cgoal-progress-bar">
                    <div class="m-cgoal-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
