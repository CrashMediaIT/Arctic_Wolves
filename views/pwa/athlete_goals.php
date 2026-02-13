<?php
/**
 * PWA Athlete Goals - Mobile-native goals view for athletes
 * Purpose-built for mobile phones.
 */

$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(title, goal_title) as title, status, completion_percentage, target_date
        FROM goals
        WHERE athlete_id = ?
        ORDER BY status ASC, created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

$totalGoals = count($goals);
?>
<style>
.m-ath-goals { padding: 16px; font-family: Inter, sans-serif; }
.m-ath-goals-header { margin-bottom: 16px; }
.m-ath-goals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ath-goals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-agoal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-agoal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-agoal-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-agoal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-agoal-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-agoal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-agoal-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-agoal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-agoal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-agoal-progress { margin-bottom: 8px; }
.m-agoal-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-agoal-progress-label { font-size: 11px; color: #6B6B7B; }
.m-agoal-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-agoal-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-agoal-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-agoal-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-ath-goals">
    <div class="m-ath-goals-header">
        <h2 class="m-ath-goals-title">My Goals</h2>
        <p class="m-ath-goals-sub"><?= $totalGoals ?> goal<?= $totalGoals !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($goals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullseye"></i>
            <p>No goals set yet</p>
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
        ?>
        <div class="m-agoal-card">
            <div class="m-agoal-top">
                <span class="m-agoal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                <span class="m-agoal-badge m-agoal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
            </div>
            <div class="m-agoal-progress">
                <div class="m-agoal-progress-header">
                    <span class="m-agoal-progress-label">Progress</span>
                    <span class="m-agoal-progress-pct"><?= $pct ?>%</span>
                </div>
                <div class="m-agoal-progress-bar">
                    <div class="m-agoal-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if (!empty($g['target_date'])): ?>
            <div class="m-agoal-date">
                <i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
