<?php
/**
 * PWA Coach Session Evaluations - Mobile-native evaluation list for coaches
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAnyCoach):
?>
<style>
.m-denied { display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 60px 20px; color: #6B6B7B; font-family: Inter, sans-serif; text-align: center; }
.m-denied i { font-size: 48px; margin-bottom: 16px; }
.m-denied p { font-size: 15px; margin: 0; }
</style>
<div class="m-denied">
    <i class="fas fa-lock"></i>
    <p>Access denied</p>
</div>
<?php
    return;
endif;

$evaluations = [];
try {
    $stmt = $pdo->prepare("
        SELECT se.id, se.session_id, se.created_at,
               s.title as session_title, s.session_date,
               (SELECT COUNT(*) FROM evaluation_scores es WHERE es.evaluation_id = se.id) as score_count
        FROM session_evaluations se
        JOIN sessions s ON s.id = se.session_id
        WHERE se.evaluator_id = ?
        ORDER BY se.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id]);
    $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $evaluations = []; }
?>
<style>
.m-evals { padding: 16px; font-family: Inter, sans-serif; }
.m-evals-header { margin-bottom: 16px; }
.m-evals-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-evals-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-eval-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; min-height: 44px;
}
.m-eval-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #8B5CF6; flex-shrink: 0;
}
.m-eval-body { flex: 1; min-width: 0; }
.m-eval-title { font-size: 14px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-eval-meta { font-size: 12px; color: #A8A8B8; margin-top: 3px; }
.m-eval-count {
    font-size: 12px; padding: 4px 10px; border-radius: 8px; font-weight: 600;
    background: rgba(59,130,246,0.15); color: #3B82F6; white-space: nowrap; flex-shrink: 0;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-evals">
    <div class="m-evals-header">
        <h2 class="m-evals-title">Session Evaluations</h2>
        <p class="m-evals-sub"><?= count($evaluations) ?> evaluation<?= count($evaluations) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($evaluations)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-check"></i>
            <p>No evaluations submitted yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($evaluations as $ev): ?>
        <a href="?page=session_detail&id=<?= (int)$ev['session_id'] ?>" class="m-eval-card">
            <div class="m-eval-icon"><i class="fas fa-clipboard-check"></i></div>
            <div class="m-eval-body">
                <div class="m-eval-title"><?= htmlspecialchars($ev['session_title'] ?? 'Session Evaluation') ?></div>
                <div class="m-eval-meta">
                    <i class="fas fa-calendar" style="font-size:10px;"></i>
                    <?= date('M j, Y', strtotime($ev['session_date'])) ?>
                    · <?= date('g:i A', strtotime($ev['created_at'])) ?>
                </div>
            </div>
            <span class="m-eval-count"><?= (int)$ev['score_count'] ?> score<?= (int)$ev['score_count'] !== 1 ? 's' : '' ?></span>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
