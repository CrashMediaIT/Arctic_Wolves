<?php
/**
 * PWA Accounting Products - Mobile-native product catalog
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$isAdmin) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Admin access required</div>';
    return;
}

$products = [];
try {
    $stmt = $pdo->prepare("SELECT id, name, description, price, stock_quantity, is_active FROM products ORDER BY name ASC LIMIT 30");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; }
?>
<style>
.m-products { padding: 16px; font-family: Inter, sans-serif; }
.m-products-header { margin-bottom: 16px; }
.m-products-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-products-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-products-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.m-product-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; min-height: 44px;
}
.m-product-name { font-size: 14px; font-weight: 600; color: #fff; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-product-desc { font-size: 11px; color: #6B6B7B; margin-bottom: 10px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-product-price { font-size: 18px; font-weight: 700; color: #8B5CF6; margin-bottom: 6px; }
.m-product-meta { display: flex; justify-content: space-between; align-items: center; }
.m-product-stock { font-size: 11px; color: #A8A8B8; }
.m-product-badge {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    display: inline-block;
}
.m-product-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-product-badge-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
</style>

<div class="m-products">
    <div class="m-products-header">
        <h2 class="m-products-title">Products</h2>
        <p class="m-products-sub"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($products)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            No products found
        </div>
    <?php else: ?>
        <div class="m-products-grid">
        <?php foreach ($products as $prod):
            $isActive = (int)($prod['is_active'] ?? 0);
            $stock = (int)($prod['stock_quantity'] ?? 0);
        ?>
            <div class="m-product-card">
                <div class="m-product-name"><?= htmlspecialchars($prod['name'] ?? 'Product') ?></div>
                <div class="m-product-desc"><?= htmlspecialchars($prod['description'] ?? '') ?></div>
                <div class="m-product-price">$<?= number_format((float)($prod['price'] ?? 0), 2) ?></div>
                <div class="m-product-meta">
                    <span class="m-product-stock"><i class="fas fa-box"></i> <?= $stock ?> in stock</span>
                    <span class="m-product-badge m-product-badge-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
                </div>
            </div>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
