<?php
/**
 * PWA Goals - Mobile-native goals tracker
 * Purpose-built for mobile phones.
 */

// Determine which athlete to show goals for
$goalsUserId = $user_id;
if ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $goalsUserId = (int)$_SESSION['viewing_athlete_id'];
}

$goals = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, COALESCE(title, goal_title) as title, description, status,
               completion_percentage, target_date, category
        FROM goals
        WHERE athlete_id = ?
        ORDER BY CASE status WHEN 'active' THEN 1 WHEN 'completed' THEN 2 ELSE 3 END,
                 created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$goalsUserId]);
    $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $goals = []; }

$activeGoals = [];
$completedGoals = [];
foreach ($goals as $g) {
    if (($g['status'] ?? '') === 'completed') {
        $completedGoals[] = $g;
    } else {
        $activeGoals[] = $g;
    }
}
?>
<style>
.m-goals { padding: 16px; font-family: Inter, sans-serif; }
.m-goals-header { margin-bottom: 16px; }
.m-goals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-goals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-section { margin-bottom: 20px; }
.m-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-goal-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-goal-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-goal-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-goal-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap; flex-shrink: 0;
}
.m-goal-badge-active { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-goal-badge-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-goal-badge-paused { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-goal-badge-cancelled { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-goal-badge-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-goal-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-goal-progress { margin-bottom: 8px; }
.m-goal-progress-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-goal-progress-label { font-size: 11px; color: #6B6B7B; }
.m-goal-progress-pct { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-goal-progress-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-goal-progress-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-goal-footer { display: flex; gap: 12px; align-items: center; }
.m-goal-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-goals">
    <div class="m-goals-header">
        <h2 class="m-goals-title">Goals</h2>
        <p class="m-goals-sub"><?= count($activeGoals) ?> active · <?= count($completedGoals) ?> completed</p>
    </div>

    <?php if (empty($goals)): ?>
        <div class="m-empty-state">
            <i class="fas fa-bullseye"></i>
            <p>No goals set yet</p>
        </div>
    <?php else: ?>
        <!-- Active Goals -->
        <?php if (!empty($activeGoals)): ?>
        <div class="m-section">
            <h3 class="m-section-title">Active Goals</h3>
            <?php foreach ($activeGoals as $g):
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
            <div class="m-goal-card">
                <div class="m-goal-top">
                    <span class="m-goal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                    <span class="m-goal-badge m-goal-badge-<?= $badgeClass ?>"><?= htmlspecialchars(ucfirst($status)) ?></span>
                </div>
                <?php if (!empty($g['description'])): ?>
                <p class="m-goal-desc"><?= htmlspecialchars($g['description']) ?></p>
                <?php endif; ?>
                <div class="m-goal-progress">
                    <div class="m-goal-progress-header">
                        <span class="m-goal-progress-label">Progress</span>
                        <span class="m-goal-progress-pct"><?= $pct ?>%</span>
                    </div>
                    <div class="m-goal-progress-bar">
                        <div class="m-goal-progress-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                </div>
                <div class="m-goal-footer">
                    <?php if (!empty($g['target_date'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['category'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-tag"></i> <?= htmlspecialchars($g['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Completed Goals -->
        <?php if (!empty($completedGoals)): ?>
        <div class="m-section">
            <h3 class="m-section-title">Completed</h3>
            <?php foreach ($completedGoals as $g): ?>
            <div class="m-goal-card">
                <div class="m-goal-top">
                    <span class="m-goal-title"><?= htmlspecialchars($g['title'] ?? 'Untitled Goal') ?></span>
                    <span class="m-goal-badge m-goal-badge-completed"><i class="fas fa-check"></i> Done</span>
                </div>
                <?php if (!empty($g['description'])): ?>
                <p class="m-goal-desc"><?= htmlspecialchars($g['description']) ?></p>
                <?php endif; ?>
                <div class="m-goal-progress">
                    <div class="m-goal-progress-bar">
                        <div class="m-goal-progress-fill" style="width:100%;background:#10B981;"></div>
                    </div>
                </div>
                <div class="m-goal-footer">
                    <?php if (!empty($g['target_date'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-flag"></i> <?= date('M j, Y', strtotime($g['target_date'])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($g['category'])): ?>
                    <span class="m-goal-meta"><i class="fas fa-tag"></i> <?= htmlspecialchars($g['category']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
