<?php
/**
 * PWA Reports Athlete - Mobile-native athlete performance reports
 * Purpose-built for mobile phones.
 */

$athleteId = $user_id;
if (($isAnyCoach || $isAdmin) && !empty($_GET['athlete_id'])) {
    $athleteId = (int)$_GET['athlete_id'];
}

$recentEvals = [];
$recentGoals = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, ek.name as skill_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 5
    ");
    $stmt->execute([$athleteId]);
    $recentEvals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentEvals = []; }

try {
    $stmt = $pdo->prepare("
        SELECT COALESCE(title, goal_title) as title, status, completion_percentage
        FROM goals WHERE athlete_id = ?
        ORDER BY created_at DESC LIMIT 5
    ");
    $stmt->execute([$athleteId]);
    $recentGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentGoals = []; }
?>
<style>
.m-rptath { padding: 16px; font-family: Inter, sans-serif; }
.m-rptath-header { margin-bottom: 16px; }
.m-rptath-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-rptath-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-rptath-section { margin-bottom: 20px; }
.m-rptath-section-title {
    font-size: 13px; font-weight: 600; color: #6B6B7B;
    text-transform: uppercase; letter-spacing: 0.5px;
    margin: 0 0 10px; padding: 0 4px;
}
.m-rptath-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
}
.m-rptath-card-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.m-rptath-card-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; }
.m-rptath-card-score { font-size: 12px; font-weight: 700; color: #8B5CF6; }
.m-rptath-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-rptath-bar-fill { height: 100%; border-radius: 3px; }
.m-rptath-meta { font-size: 11px; color: #6B6B7B; margin-top: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-rptath">
    <div class="m-rptath-header">
        <h2 class="m-rptath-title">Athlete Report</h2>
        <p class="m-rptath-sub">Performance metrics</p>
    </div>

    <div class="m-rptath-section">
        <h3 class="m-rptath-section-title">Recent Evaluations</h3>
        <?php if (empty($recentEvals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-clipboard-check"></i>
                <p>No evaluations yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentEvals as $ev):
                $score = (float)($ev['score'] ?? 0);
                $maxScore = (float)($ev['max_score'] ?? 10);
                $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            ?>
            <div class="m-rptath-card">
                <div class="m-rptath-card-top">
                    <span class="m-rptath-card-name"><?= htmlspecialchars($ev['skill_name'] ?? 'Skill') ?></span>
                    <span class="m-rptath-card-score"><?= $score ?>/<?= $maxScore ?></span>
                </div>
                <div class="m-rptath-bar">
                    <div class="m-rptath-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
                <?php if (!empty($ev['evaluation_date'])): ?>
                <div class="m-rptath-meta"><?= date('M j, Y', strtotime($ev['evaluation_date'])) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="m-rptath-section">
        <h3 class="m-rptath-section-title">Recent Goals</h3>
        <?php if (empty($recentGoals)): ?>
            <div class="m-empty-state">
                <i class="fas fa-bullseye"></i>
                <p>No goals yet</p>
            </div>
        <?php else: ?>
            <?php foreach ($recentGoals as $g):
                $pct = max(0, min(100, (int)($g['completion_percentage'] ?? 0)));
                $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#8B5CF6');
            ?>
            <div class="m-rptath-card">
                <div class="m-rptath-card-top">
                    <span class="m-rptath-card-name"><?= htmlspecialchars($g['title'] ?? 'Goal') ?></span>
                    <span class="m-rptath-card-score"><?= $pct ?>%</span>
                </div>
                <div class="m-rptath-bar">
                    <div class="m-rptath-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
