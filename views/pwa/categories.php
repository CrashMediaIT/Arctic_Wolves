<?php
/**
 * PWA Categories - Mobile-native resource categories list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$categories = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, type, description FROM categories ORDER BY type, name");
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $categories = []; }

$grouped = [];
foreach ($categories as $c) {
    $type = $c['type'] ?? 'Other';
    $grouped[$type][] = $c;
}
?>
<style>
.m-cats { padding: 16px; font-family: Inter, sans-serif; }
.m-cats-header { margin-bottom: 16px; }
.m-cats-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-cats-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-cats-group-title { font-size: 13px; font-weight: 600; color: #8B5CF6; margin: 16px 0 8px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-cat-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-cat-icon {
    width: 36px; height: 36px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    background: rgba(107,70,193,0.15); color: #8B5CF6; font-size: 14px; flex-shrink: 0;
}
.m-cat-body { flex: 1; min-width: 0; }
.m-cat-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-cat-desc { font-size: 12px; color: #A8A8B8; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-cats">
    <div class="m-cats-header">
        <h2 class="m-cats-title">Categories</h2>
        <p class="m-cats-sub"><?= count($categories) ?> categor<?= count($categories) !== 1 ? 'ies' : 'y' ?></p>
    </div>

    <?php if (empty($categories)): ?>
        <div class="m-empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No categories found</p>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $type => $items): ?>
            <div class="m-cats-group-title"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $type))) ?></div>
            <?php foreach ($items as $c): ?>
            <div class="m-cat-card">
                <div class="m-cat-icon"><i class="fas fa-tag"></i></div>
                <div class="m-cat-body">
                    <div class="m-cat-name"><?= htmlspecialchars($c['name'] ?? '') ?></div>
                    <?php if (!empty($c['description'])): ?>
                    <div class="m-cat-desc"><?= htmlspecialchars($c['description']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
