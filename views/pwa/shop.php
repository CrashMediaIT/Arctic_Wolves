<?php
/**
 * PWA Shop - Mobile-native product grid
 * Purpose-built for mobile phones.
 */

$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.price, p.image_url, p.stock_quantity,
               c.name as category_name
        FROM products p
        LEFT JOIN product_categories c ON c.id = p.category_id
        WHERE p.is_active = 1
        ORDER BY p.name ASC
        LIMIT 30
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; }
?>
<style>
.m-shop { padding: 16px; font-family: Inter, sans-serif; }
.m-shop-header { margin-bottom: 16px; }
.m-shop-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-shop-count { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-product-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.m-product-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    overflow: hidden; text-decoration: none; display: flex; flex-direction: column;
}
.m-product-img {
    width: 100%; aspect-ratio: 1; background: #1E1E2E;
    display: flex; align-items: center; justify-content: center;
    overflow: hidden;
}
.m-product-img img { width: 100%; height: 100%; object-fit: cover; }
.m-product-img i { font-size: 32px; color: #2D2D3F; }
.m-product-info { padding: 12px; flex: 1; display: flex; flex-direction: column; }
.m-product-cat { font-size: 10px; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.m-product-name { font-size: 13px; font-weight: 600; color: #fff; margin: 0 0 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
.m-product-bottom { display: flex; justify-content: space-between; align-items: center; margin-top: auto; }
.m-product-price { font-size: 15px; font-weight: 700; color: #10B981; }
.m-product-stock { font-size: 10px; font-weight: 600; padding: 2px 6px; border-radius: 4px; }
.m-product-stock-in { background: rgba(16,185,129,0.15); color: #10B981; }
.m-product-stock-low { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-product-stock-out { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-shop">
    <div class="m-shop-header">
        <h2 class="m-shop-title">Shop</h2>
        <p class="m-shop-count"><?= count($products) ?> product<?= count($products) !== 1 ? 's' : '' ?></p>
    </div>

    <?php if (empty($products)): ?>
        <div class="m-empty-state">
            <i class="fas fa-store-slash"></i>
            <p>No products available</p>
        </div>
    <?php else: ?>
        <div class="m-product-grid">
            <?php foreach ($products as $p):
                $stock = (int)($p['stock_quantity'] ?? 0);
                if ($stock <= 0) {
                    $stockClass = 'out';
                    $stockLabel = 'Out of Stock';
                } elseif ($stock <= 5) {
                    $stockClass = 'low';
                    $stockLabel = 'Low Stock';
                } else {
                    $stockClass = 'in';
                    $stockLabel = 'In Stock';
                }
            ?>
            <a href="?page=shop&product_id=<?= (int)$p['id'] ?>" class="m-product-card">
                <div class="m-product-img">
                    <?php if (!empty($p['image_url'])): ?>
                        <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                    <?php else: ?>
                        <i class="fas fa-box"></i>
                    <?php endif; ?>
                </div>
                <div class="m-product-info">
                    <?php if (!empty($p['category_name'])): ?>
                    <div class="m-product-cat"><?= htmlspecialchars($p['category_name']) ?></div>
                    <?php endif; ?>
                    <h3 class="m-product-name"><?= htmlspecialchars($p['name']) ?></h3>
                    <div class="m-product-bottom">
                        <span class="m-product-price">$<?= number_format((float)$p['price'], 2) ?></span>
                        <span class="m-product-stock m-product-stock-<?= $stockClass ?>"><?= $stockLabel ?></span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
