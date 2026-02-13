<?php
/**
 * PWA Coach Evaluations - Mobile-native evaluations performed by coach
 * Purpose-built for mobile phones.
 */

if (!$isAnyCoach) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Coach access required.</p>';
    echo '</div>';
    return;
}

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.id, es.score, es.max_score, es.evaluation_date,
               ek.name as skill_name, u.first_name, u.last_name
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        LEFT JOIN users u ON u.id = es.athlete_id
        WHERE es.evaluator_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }

$totalEvals = count($evaluations);
?>
<style>
.m-coach-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-coach-evals-header { margin-bottom: 16px; }
.m-coach-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-coach-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-ceval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-ceval-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px; }
.m-ceval-skill { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-ceval-score-badge {
    font-size: 12px; font-weight: 700; color: #8B5CF6; flex-shrink: 0;
}
.m-ceval-athlete { font-size: 12px; color: #A8A8B8; margin-bottom: 8px; display: flex; align-items: center; gap: 4px; }
.m-ceval-bar-wrap { margin-bottom: 8px; }
.m-ceval-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-ceval-bar-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-ceval-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-coach-evals">
    <div class="m-coach-evals-header">
        <h2 class="m-coach-evals-title">My Evaluations</h2>
        <p class="m-coach-evals-sub"><?= $totalEvals ?> evaluation<?= $totalEvals !== 1 ? 's' : '' ?> performed</p>
    </div>

    <?php if (empty($evaluations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations performed yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($evaluations as $ev):
            $score = (float)($ev['score'] ?? 0);
            $maxScore = (float)($ev['max_score'] ?? 10);
            $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
            $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
            $athName = htmlspecialchars(($ev['first_name'] ?? '') . ' ' . ($ev['last_name'] ?? ''));
        ?>
        <div class="m-ceval-card">
            <div class="m-ceval-top">
                <span class="m-ceval-skill"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                <span class="m-ceval-score-badge"><?= $score ?>/<?= $maxScore ?></span>
            </div>
            <div class="m-ceval-athlete">
                <i class="fas fa-user"></i> <?= $athName ?>
            </div>
            <div class="m-ceval-bar-wrap">
                <div class="m-ceval-bar">
                    <div class="m-ceval-bar-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if (!empty($ev['evaluation_date'])): ?>
            <div class="m-ceval-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
