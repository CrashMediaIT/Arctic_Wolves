<?php
/**
 * PWA Admin Packages - Mobile-native packages management list
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$packages = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, sessions_included, is_active FROM packages ORDER BY name");
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $packages = []; }
?>
<style>
.m-adminpkg { padding: 16px; font-family: Inter, sans-serif; }
.m-adminpkg-header { margin-bottom: 16px; }
.m-adminpkg-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-adminpkg-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-adminpkg-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-adminpkg-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
.m-adminpkg-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-adminpkg-badge { font-size: 10px; padding: 2px 8px; border-radius: 4px; font-weight: 600; margin-left: 8px; flex-shrink: 0; }
.m-adminpkg-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-adminpkg-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-adminpkg-desc { font-size: 12px; color: #A8A8B8; margin-bottom: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-adminpkg-meta { display: flex; gap: 14px; flex-wrap: wrap; }
.m-adminpkg-price { font-size: 15px; font-weight: 700; color: #10B981; }
.m-adminpkg-sessions { font-size: 12px; color: #6B6B7B; display: inline-flex; align-items: center; gap: 4px; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-adminpkg">
    <div class="m-adminpkg-header">
        <h2 class="m-adminpkg-title">Manage Packages</h2>
        <p class="m-adminpkg-sub"><?= count($packages) ?> package<?= count($packages) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($packages)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            <p>No packages found</p>
        </div>
    <?php else: ?>
        <?php foreach ($packages as $pkg):
            $active = (int)($pkg['is_active'] ?? 0);
        ?>
        <div class="m-adminpkg-card">
            <div class="m-adminpkg-top">
                <div class="m-adminpkg-name"><?= htmlspecialchars($pkg['name'] ?? '') ?></div>
                <span class="m-adminpkg-badge <?= $active ? 'm-adminpkg-active' : 'm-adminpkg-inactive' ?>"><?= $active ? 'Active' : 'Inactive' ?></span>
            </div>
            <?php if (!empty($pkg['description'])): ?>
            <div class="m-adminpkg-desc"><?= htmlspecialchars($pkg['description']) ?></div>
            <?php endif; ?>
            <div class="m-adminpkg-meta">
                <span class="m-adminpkg-price">$<?= number_format((float)($pkg['price'] ?? 0), 2) ?></span>
                <?php if (!empty($pkg['sessions_included'])): ?>
                <span class="m-adminpkg-sessions"><i class="fas fa-calendar-check"></i> <?= (int)$pkg['sessions_included'] ?> sessions</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
