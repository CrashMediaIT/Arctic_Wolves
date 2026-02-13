<?php
/**
 * PWA Stats - Mobile-native performance stats for athletes
 * Purpose-built for mobile phones.
 */

// Determine which athlete to show stats for
$statsUserId = $user_id;
$statsUserName = $user_name;
if ($isParent && !empty($_SESSION['viewing_athlete_id'])) {
    $statsUserId = (int)$_SESSION['viewing_athlete_id'];
    try {
        $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) FROM users WHERE id = ?");
        $stmt->execute([$statsUserId]);
        $statsUserName = $stmt->fetchColumn() ?: $user_name;
    } catch (PDOException $e) { /* keep default */ }
}

// Total sessions attended
$totalSessions = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN sessions s ON s.id = b.session_id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.status = 'completed'
    ");
    $stmt->execute([$statsUserId]);
    $totalSessions = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $totalSessions = 0; }

// Sessions this month
$monthSessions = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM bookings b
        JOIN sessions s ON s.id = b.session_id
        WHERE b.user_id = ? AND b.status = 'confirmed' AND s.status = 'completed'
          AND s.session_date >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ");
    $stmt->execute([$statsUserId]);
    $monthSessions = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $monthSessions = 0; }

// Goals stats
$activeGoals = 0;
$completedGoals = 0;
$goalCompletionRate = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE athlete_id = ? AND status = 'active'");
    $stmt->execute([$statsUserId]);
    $activeGoals = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE athlete_id = ? AND status = 'completed'");
    $stmt->execute([$statsUserId]);
    $completedGoals = (int)$stmt->fetchColumn();

    $totalGoals = $activeGoals + $completedGoals;
    $goalCompletionRate = $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100) : 0;
} catch (PDOException $e) { /* fallback to zeros */ }

// Average goal progress
$avgGoalProgress = 0;
try {
    $stmt = $pdo->prepare("SELECT AVG(completion_percentage) FROM goals WHERE athlete_id = ? AND status = 'active'");
    $stmt->execute([$statsUserId]);
    $avgGoalProgress = round((float)$stmt->fetchColumn());
} catch (PDOException $e) { $avgGoalProgress = 0; }

// Recent skill evaluations
$recentEvals = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, es.comments,
               ek.name as skill_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC, es.created_at DESC
        LIMIT 6
    ");
    $stmt->execute([$statsUserId]);
    $recentEvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentEvals = []; }

// Average score
$avgScore = 0;
$avgMaxScore = 10;
try {
    $stmt = $pdo->prepare("SELECT AVG(score), AVG(max_score) FROM evaluation_scores WHERE athlete_id = ?");
    $stmt->execute([$statsUserId]);
    $row = $stmt->fetch(PDO::FETCH_NUM);
    if ($row && $row[0] !== null) {
        $avgScore = round((float)$row[0], 1);
        $avgMaxScore = round((float)$row[1], 1);
    }
} catch (PDOException $e) { /* fallback */ }
?>
<style>
.m-stats { padding: 16px; font-family: Inter, sans-serif; }
.m-stats-header { margin-bottom: 16px; }
.m-stats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-stats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-kpi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
.m-kpi {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-kpi-icon { font-size: 16px; margin-bottom: 6px; }
.m-kpi-value { font-size: 28px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-kpi-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-section { margin-bottom: 20px; }
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-progress-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-progress-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 8px;
}
.m-progress-name { font-size: 13px; color: #fff; font-weight: 500; }
.m-progress-value { font-size: 13px; color: #8B5CF6; font-weight: 600; }
.m-progress-bar {
    height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden;
}
.m-progress-fill {
    height: 100%; border-radius: 3px;
    transition: width 0.5s ease;
}
.m-eval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-eval-top {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 6px;
}
.m-eval-skill { font-size: 13px; font-weight: 600; color: #fff; }
.m-eval-score { font-size: 14px; font-weight: 700; }
.m-eval-date { font-size: 11px; color: #6B6B7B; }
.m-eval-bar { height: 4px; background: #2D2D3F; border-radius: 2px; margin-top: 6px; overflow: hidden; }
.m-eval-bar-fill { height: 100%; border-radius: 2px; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-stats">
    <div class="m-stats-header">
        <h2 class="m-stats-title">Performance</h2>
        <p class="m-stats-sub"><?= htmlspecialchars($statsUserName) ?></p>
    </div>

    <!-- KPI Grid -->
    <div class="m-kpi-grid">
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#10B981;"><i class="fas fa-check-circle"></i></div>
            <div class="m-kpi-value"><?= $totalSessions ?></div>
            <div class="m-kpi-label">Sessions Done</div>
        </div>
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#3B82F6;"><i class="fas fa-calendar"></i></div>
            <div class="m-kpi-value"><?= $monthSessions ?></div>
            <div class="m-kpi-label">This Month</div>
        </div>
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#8B5CF6;"><i class="fas fa-bullseye"></i></div>
            <div class="m-kpi-value"><?= $activeGoals ?></div>
            <div class="m-kpi-label">Active Goals</div>
        </div>
        <div class="m-kpi">
            <div class="m-kpi-icon" style="color:#F59E0B;"><i class="fas fa-star"></i></div>
            <div class="m-kpi-value"><?= $avgScore ?><span style="font-size:14px;color:#6B6B7B;">/<?= $avgMaxScore ?></span></div>
            <div class="m-kpi-label">Avg Score</div>
        </div>
    </div>

    <!-- Goal Progress -->
    <div class="m-section">
        <h3 class="m-section-title">Goal Progress</h3>
        <?php if ($activeGoals === 0 && $completedGoals === 0): ?>
            <div class="m-empty-state">
                <i class="fas fa-bullseye"></i>
                No goals set yet
            </div>
        <?php else: ?>
            <div class="m-progress-card">
                <div class="m-progress-header">
                    <span class="m-progress-name">Completion Rate</span>
                    <span class="m-progress-value"><?= $goalCompletionRate ?>%</span>
                </div>
                <div class="m-progress-bar">
                    <div class="m-progress-fill" style="width:<?= $goalCompletionRate ?>%;background:#10B981;"></div>
                </div>
            </div>
            <div class="m-progress-card">
                <div class="m-progress-header">
                    <span class="m-progress-name">Avg Active Goal Progress</span>
                    <span class="m-progress-value"><?= $avgGoalProgress ?>%</span>
                </div>
                <div class="m-progress-bar">
                    <div class="m-progress-fill" style="width:<?= $avgGoalProgress ?>%;background:#8B5CF6;"></div>
                </div>
            </div>
            <div style="display:flex;gap:10px;margin-top:10px;">
                <div style="flex:1;background:#16161F;border:1px solid #2D2D3F;border-radius:10px;padding:12px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#10B981;"><?= $completedGoals ?></div>
                    <div style="font-size:11px;color:#A8A8B8;">Completed</div>
                </div>
                <div style="flex:1;background:#16161F;border:1px solid #2D2D3F;border-radius:10px;padding:12px;text-align:center;">
                    <div style="font-size:20px;font-weight:700;color:#8B5CF6;"><?= $activeGoals ?></div>
                    <div style="font-size:11px;color:#A8A8B8;">Active</div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Evaluations -->
    <div class="m-section">
        <h3 class="m-section-title">Recent Evaluations</h3>
        <?php if (empty($recentEvals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-chart-line"></i>
                No evaluations yet
            </div>
        <?php else: ?>
            <?php foreach ($recentEvals as $ev):
                $score = (float)$ev['score'];
                $maxScore = (float)($ev['max_score'] ?: 10);
                $pct = $maxScore > 0 ? round(($score / $maxScore) * 100) : 0;
                $color = $pct >= 70 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
                $skillName = $ev['skill_name'] ?? 'Skill';
            ?>
            <div class="m-eval-card">
                <div class="m-eval-top">
                    <span class="m-eval-skill"><?= htmlspecialchars($skillName) ?></span>
                    <span class="m-eval-score" style="color:<?= $color ?>;"><?= $score ?><span style="font-size:11px;color:#6B6B7B;">/<?= $maxScore ?></span></span>
                </div>
                <div class="m-eval-date">
                    <i class="fas fa-calendar" style="font-size:10px;"></i>
                    <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
                </div>
                <div class="m-eval-bar">
                    <div class="m-eval-bar-fill" style="width:<?= $pct ?>%;background:<?= $color ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
