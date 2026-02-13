<?php
/**
 * PWA Session Templates - Mobile-native session template library
 * Purpose-built for mobile phones.
 */

$templates = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description, duration_minutes
        FROM training_session_templates
        WHERE is_active = 1
        ORDER BY name
        LIMIT 20
    ");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $templates = []; }

$totalTemplates = count($templates);
?>
<style>
.m-sesstpl { padding: 16px; font-family: Inter, sans-serif; }
.m-sesstpl-header { margin-bottom: 16px; }
.m-sesstpl-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-sesstpl-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-sesstpl-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-sesstpl-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; }
.m-sesstpl-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-sesstpl-meta {
    display: flex; align-items: center; gap: 8px;
    padding-top: 10px; border-top: 1px solid #2D2D3F;
}
.m-sesstpl-dur { font-size: 12px; color: #6B6B7B; display: flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-sesstpl">
    <div class="m-sesstpl-header">
        <h2 class="m-sesstpl-title">Session Templates</h2>
        <p class="m-sesstpl-sub"><?= $totalTemplates ?> template<?= $totalTemplates !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($templates)): ?>
        <div class="m-empty-state">
            <i class="fas fa-clipboard-list"></i>
            <p>No session templates available</p>
        </div>
    <?php else: ?>
        <?php foreach ($templates as $t): ?>
        <div class="m-sesstpl-card">
            <div class="m-sesstpl-name"><?= htmlspecialchars($t['name']) ?></div>
            <?php if (!empty($t['description'])): ?>
            <p class="m-sesstpl-desc"><?= htmlspecialchars($t['description']) ?></p>
            <?php endif; ?>
            <div class="m-sesstpl-meta">
                <?php if (!empty($t['duration_minutes'])): ?>
                <span class="m-sesstpl-dur"><i class="fas fa-clock"></i> <?= (int)$t['duration_minutes'] ?> min</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
