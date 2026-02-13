<?php
/**
 * PWA Packages - Mobile-native packages list
 * Purpose-built for mobile phones.
 */

$packages = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, sessions_included, validity_days, is_active FROM packages WHERE is_active = 1 ORDER BY price ASC");
    $stmt->execute();
    $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $packages = []; }
?>
<style>
.m-packages { padding: 16px; font-family: Inter, sans-serif; }
.m-packages-header { margin-bottom: 16px; }
.m-packages-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-packages-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-package-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 12px;
}
.m-package-name { font-size: 16px; font-weight: 700; color: #fff; margin: 0 0 6px; }
.m-package-desc { font-size: 12px; color: #A8A8B8; margin: 0 0 14px; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.m-package-details { display: flex; gap: 12px; margin-bottom: 14px; flex-wrap: wrap; }
.m-package-detail {
    display: flex; align-items: center; gap: 6px;
    font-size: 12px; color: #A8A8B8;
}
.m-package-detail i { font-size: 12px; color: #6B6B7B; }
.m-package-footer {
    display: flex; justify-content: space-between; align-items: center;
    padding-top: 14px; border-top: 1px solid #2D2D3F;
}
.m-package-price { font-size: 22px; font-weight: 700; color: #10B981; }
.m-package-price-sub { font-size: 11px; color: #6B6B7B; font-weight: 400; }
.m-package-buy {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 10px 20px; border-radius: 10px;
    background: #6B46C1; color: #fff; font-size: 13px; font-weight: 600;
    text-decoration: none; min-height: 44px;
    font-family: Inter, sans-serif; border: none; cursor: pointer;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-packages">
    <div class="m-packages-header">
        <h2 class="m-packages-title">Training Packages</h2>
        <p class="m-packages-sub"><?= count($packages) ?> package<?= count($packages) !== 1 ? 's' : '' ?> available</p>
    </div>

    <?php if (empty($packages)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            <p>No packages available</p>
        </div>
    <?php else: ?>
        <?php foreach ($packages as $pkg): ?>
        <div class="m-package-card">
            <h3 class="m-package-name"><?= htmlspecialchars($pkg['name']) ?></h3>
            <?php if (!empty($pkg['description'])): ?>
            <p class="m-package-desc"><?= htmlspecialchars($pkg['description']) ?></p>
            <?php endif; ?>
            <div class="m-package-details">
                <?php if (!empty($pkg['sessions_included'])): ?>
                <span class="m-package-detail">
                    <i class="fas fa-calendar-check"></i>
                    <?= (int)$pkg['sessions_included'] ?> session<?= (int)$pkg['sessions_included'] !== 1 ? 's' : '' ?>
                </span>
                <?php endif; ?>
                <?php if (!empty($pkg['validity_days'])): ?>
                <span class="m-package-detail">
                    <i class="fas fa-clock"></i>
                    <?= (int)$pkg['validity_days'] ?> day<?= (int)$pkg['validity_days'] !== 1 ? 's' : '' ?> validity
                </span>
                <?php endif; ?>
            </div>
            <div class="m-package-footer">
                <div>
                    <span class="m-package-price">$<?= number_format((float)$pkg['price'], 2) ?></span>
                    <?php if (!empty($pkg['sessions_included']) && (float)$pkg['price'] > 0): ?>
                    <div class="m-package-price-sub">$<?= number_format((float)$pkg['price'] / (int)$pkg['sessions_included'], 2) ?>/session</div>
                    <?php endif; ?>
                </div>
                <a href="?page=purchase_package&package_id=<?= (int)$pkg['id'] ?>" class="m-package-buy">
                    <i class="fas fa-cart-plus"></i> Purchase
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
