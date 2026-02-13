<?php
/**
 * PWA Merchandise Products - Mobile-native product management
 * Purpose-built for mobile phones.
 */

if (!$isAdmin) {
    echo '<div style="text-align:center;padding:40px 20px;color:#6B6B7B;font-family:Inter,sans-serif;">';
    echo '<i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>';
    echo '<p style="font-size:14px;">Admin access required.</p>';
    echo '</div>';
    return;
}

$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, name, price, stock_quantity, is_active
        FROM products
        ORDER BY name
        LIMIT 30
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; }

$totalProducts = count($products);
?>
<style>
.m-merchprod { padding: 16px; font-family: Inter, sans-serif; }
.m-merchprod-header { margin-bottom: 16px; }
.m-merchprod-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-merchprod-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-merchprod-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-merchprod-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; }
.m-merchprod-name { font-size: 14px; font-weight: 600; color: #fff; flex: 1; margin-right: 8px; }
.m-merchprod-price { font-size: 15px; font-weight: 700; color: #10B981; flex-shrink: 0; }
.m-merchprod-bottom { display: flex; justify-content: space-between; align-items: center; }
.m-merchprod-stock { font-size: 12px; color: #A8A8B8; }
.m-merchprod-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    white-space: nowrap;
}
.m-merchprod-badge-active { background: rgba(16,185,129,0.15); color: #10B981; }
.m-merchprod-badge-inactive { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-merchprod">
    <div class="m-merchprod-header">
        <h2 class="m-merchprod-title">Merchandise Products</h2>
        <p class="m-merchprod-sub"><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($products)): ?>
        <div class="m-empty-state">
            <i class="fas fa-box-open"></i>
            <p>No products found</p>
        </div>
    <?php else: ?>
        <?php foreach ($products as $p):
            $isActive = (int)($p['is_active'] ?? 1);
            $stock = (int)($p['stock_quantity'] ?? 0);
        ?>
        <div class="m-merchprod-card">
            <div class="m-merchprod-top">
                <span class="m-merchprod-name"><?= htmlspecialchars($p['name']) ?></span>
                <span class="m-merchprod-price">$<?= number_format((float)($p['price'] ?? 0), 2) ?></span>
            </div>
            <div class="m-merchprod-bottom">
                <span class="m-merchprod-stock"><i class="fas fa-box" style="font-size:10px;"></i> Stock: <?= $stock ?></span>
                <span class="m-merchprod-badge m-merchprod-badge-<?= $isActive ? 'active' : 'inactive' ?>"><?= $isActive ? 'Active' : 'Inactive' ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
