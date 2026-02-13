<?php
/**
 * PWA Merchandise Categories - Mobile-native product category management
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$categories = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, description
        FROM product_categories
        ORDER BY name
    ");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; }

$totalCats = count($categories);
?>
<style>
.m-merchcats { padding: 16px; font-family: Inter, sans-serif; }
.m-merchcats-header { margin-bottom: 16px; }
.m-merchcats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-merchcats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-merchcat-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
    display: flex; align-items: center; gap: 12px; min-height: 44px;
}
.m-merchcat-icon {
    width: 44px; height: 44px; border-radius: 10px;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px; color: #8B5CF6; flex-shrink: 0;
}
.m-merchcat-info { flex: 1; min-width: 0; }
.m-merchcat-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-merchcat-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; display: -webkit-box; -webkit-line-clamp: 1; -webkit-box-orient: vertical; overflow: hidden; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-merchcats">
    <div class="m-merchcats-header">
        <h2 class="m-merchcats-title">Merchandise Categories</h2>
        <p class="m-merchcats-sub"><?= $totalCats ?> categor<?= $totalCats !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($categories)): ?>
        <div class="m-empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No categories defined</p>
        </div>
    <?php else: ?>
        <?php foreach ($categories as $c): ?>
        <div class="m-merchcat-card">
            <div class="m-merchcat-icon"><i class="fas fa-tag"></i></div>
            <div class="m-merchcat-info">
                <div class="m-merchcat-name"><?= htmlspecialchars($c['name']) ?></div>
                <?php if (!empty($c['description'])): ?>
                <div class="m-merchcat-desc"><?= htmlspecialchars($c['description']) ?></div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
