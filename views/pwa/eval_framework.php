<?php
/**
 * PWA Eval Framework - Mobile-native evaluation framework view
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$skills = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, category FROM eval_skills ORDER BY category, name LIMIT 30");
    $stmt->execute();
    $skills = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $skills = []; }

$grouped = [];
foreach ($skills as $s) {
    $cat = $s['category'] ?? 'Uncategorized';
    $grouped[$cat][] = $s;
}
?>
<style>
.m-evalfw { padding: 16px; font-family: Inter, sans-serif; }
.m-evalfw-header { margin-bottom: 16px; }
.m-evalfw-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-evalfw-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-evalfw-group { font-size: 13px; font-weight: 600; color: #8B5CF6; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-evalfw-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-evalfw-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-evalfw-desc { font-size: 12px; color: #A8A8B8; margin-top: 4px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-evalfw-desktop {
    display: block; text-align: center; margin-top: 16px; padding: 12px;
    background: rgba(107,70,193,0.15); color: #8B5CF6; border-radius: 10px;
    font-size: 13px; font-weight: 600; text-decoration: none; min-height: 44px;
    line-height: 20px;
}
</style>

<div class="m-evalfw">
    <div class="m-evalfw-header">
        <h2 class="m-evalfw-title">Evaluation Framework</h2>
        <p class="m-evalfw-sub"><?= count($skills) ?> skill<?= count($skills) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($skills)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No evaluation skills defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $cat => $items): ?>
            <div class="m-evalfw-group"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $cat))) ?></div>
            <?php foreach ($items as $s): ?>
            <div class="m-evalfw-card">
                <div class="m-evalfw-name"><?= htmlspecialchars($s['name'] ?? '') ?></div>
                <?php if (!empty($s['description'])): ?>
                <div class="m-evalfw-desc"><?= htmlspecialchars($s['description']) ?></div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <a href="?page=eval_framework&desktop=1" class="m-evalfw-desktop">
        <i class="fas fa-desktop"></i> Manage on Desktop
    </a>
</div>
