<?php
/**
 * PWA Athlete Evaluations - Mobile-native evaluation view for athletes
 * Purpose-built for mobile phones.
 */

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT es.score, es.max_score, es.evaluation_date, ek.name as skill_name, ek.category
        FROM evaluation_scores es
        LEFT JOIN eval_skills ek ON ek.id = es.skill_id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }

$totalEvals = count($evaluations);
?>
<style>
.m-ath-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-ath-evals-header { margin-bottom: 16px; }
.m-ath-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-ath-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-eval-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-eval-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.m-eval-skill { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-eval-cat {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6; white-space: nowrap;
}
.m-eval-score-wrap { margin-bottom: 8px; }
.m-eval-score-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-eval-score-label { font-size: 11px; color: #6B6B7B; }
.m-eval-score-value { font-size: 11px; color: #8B5CF6; font-weight: 600; }
.m-eval-score-bar { height: 6px; background: #2D2D3F; border-radius: 3px; overflow: hidden; }
.m-eval-score-fill { height: 100%; border-radius: 3px; transition: width 0.5s ease; }
.m-eval-date { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-ath-evals">
    <div class="m-ath-evals-header">
        <h2 class="m-ath-evals-title">My Evaluations</h2>
        <p class="m-ath-evals-sub"><?= $totalEvals ?> evaluation<?= $totalEvals !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($evaluations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($evaluations as $ev):
            $score = (float)($ev['score'] ?? 0);
            $maxScore = (float)($ev['max_score'] ?? 10);
            $pct = $maxScore > 0 ? min(100, round(($score / $maxScore) * 100)) : 0;
            $barColor = $pct >= 75 ? '#10B981' : ($pct >= 40 ? '#F59E0B' : '#EF4444');
        ?>
        <div class="m-eval-card">
            <div class="m-eval-top">
                <span class="m-eval-skill"><?= htmlspecialchars($ev['skill_name'] ?? 'Unnamed Skill') ?></span>
                <?php if (!empty($ev['category'])): ?>
                <span class="m-eval-cat"><?= htmlspecialchars($ev['category']) ?></span>
                <?php endif; ?>
            </div>
            <div class="m-eval-score-wrap">
                <div class="m-eval-score-header">
                    <span class="m-eval-score-label">Score</span>
                    <span class="m-eval-score-value"><?= $score ?> / <?= $maxScore ?></span>
                </div>
                <div class="m-eval-score-bar">
                    <div class="m-eval-score-fill" style="width:<?= $pct ?>%;background:<?= $barColor ?>;"></div>
                </div>
            </div>
            <?php if (!empty($ev['evaluation_date'])): ?>
            <div class="m-eval-date">
                <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($ev['evaluation_date'])) ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
