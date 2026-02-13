<?php
/**
 * PWA Practice Plans - Mobile-native practice plan list for coaches
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

$plans = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, description, total_duration, created_at
        FROM practice_plans
        WHERE created_by = ?
        ORDER BY created_at DESC
        LIMIT 30
    ");
    $stmt->execute([$user_id]);
    $plans = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $plans = []; }
?>
<style>
.m-practice { padding: 16px; font-family: Inter, sans-serif; }
.m-practice-header { margin-bottom: 16px; }
.m-practice-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-practice-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-plan-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    text-decoration: none; display: block; min-height: 44px;
}
.m-plan-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-plan-title { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-plan-duration {
    font-size: 11px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(107,70,193,0.15); color: #8B5CF6; white-space: nowrap; flex-shrink: 0;
}
.m-plan-desc {
    font-size: 12px; color: #A8A8B8; margin: 0 0 10px;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
}
.m-plan-footer { display: flex; gap: 12px; align-items: center; }
.m-plan-meta { font-size: 11px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-fab {
    position: fixed; bottom: 80px; right: 20px; z-index: 50;
    width: 56px; height: 56px; border-radius: 50%;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; font-size: 22px;
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; box-shadow: 0 4px 16px rgba(107,70,193,0.4);
    border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-practice">
    <div class="m-practice-header">
        <h2 class="m-practice-title">Practice Plans</h2>
        <p class="m-practice-sub"><?= count($plans) ?> plan<?= count($plans) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($plans)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No practice plans created yet</p>
        </div>
    <?php else: ?>
        <?php foreach ($plans as $p): ?>
        <a href="?page=view_practice_plan&id=<?= (int)$p['id'] ?>" class="m-plan-card">
            <div class="m-plan-top">
                <span class="m-plan-title"><?= htmlspecialchars($p['title']) ?></span>
                <?php if ($p['total_duration']): ?>
                <span class="m-plan-duration"><i class="fas fa-clock"></i> <?= (int)$p['total_duration'] ?>min</span>
                <?php endif; ?>
            </div>
            <?php if (!empty($p['description'])): ?>
            <p class="m-plan-desc"><?= htmlspecialchars($p['description']) ?></p>
            <?php endif; ?>
            <div class="m-plan-footer">
                <span class="m-plan-meta"><i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($p['created_at'])) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="?page=create_practice_plan" class="m-fab" title="Create Practice Plan"><i class="fas fa-plus"></i></a>
</div>
