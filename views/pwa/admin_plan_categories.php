<?php
/**
 * PWA Admin Plan Categories - Mobile-native plan categories list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description FROM plan_categories ORDER BY name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; }
?>
<style>
.m-plancat { padding: 16px; font-family: Inter, sans-serif; }
.m-plancat-header { margin-bottom: 16px; }
.m-plancat-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-plancat-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-plancat-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-plancat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(107,70,193,0.15); color: #8B5CF6; font-size: 14px; flex-shrink: 0;
}
.m-plancat-body { flex: 1; min-width: 0; }
.m-plancat-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-plancat-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-plancat">
    <div class="m-plancat-header">
        <h2 class="m-plancat-title">Plan Categories</h2>
        <p class="m-plancat-sub"><?= count($categories) ?> categor<?= count($categories) !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($categories)): ?>
        <div class="m-empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No plan categories found</p>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $c): ?>
        <div class="m-plancat-card">
            <div class="m-plancat-icon"><i class="fas fa-folder"></i></div>
            <div class="m-plancat-body">
                <div class="m-plancat-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                <?php if (!empty($c['description'])): ?>
                <div class="m-plancat-desc"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
