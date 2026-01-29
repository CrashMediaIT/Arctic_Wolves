<?php
/**
 * Dashboard Shop View
 * Displays merchandise products within the dashboard interface
 * Pulls the same data as the public shop.php
 */

// Get filter parameters from URL
$categorySlug = $_GET['category'] ?? '';
$subcategorySlug = $_GET['subcategory'] ?? '';
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';
$shopPage = max(1, intval($_GET['shop_page'] ?? 1));
$perPage = 12;
$offset = ($shopPage - 1) * $perPage;

// Fetch all active categories with subcategories
$categories = [];
try {
    // Get parent categories
    $catStmt = $pdo->prepare("
        SELECT mc.*, 
               (SELECT COUNT(*) FROM merchandise_products mp WHERE mp.category_id = mc.id AND mp.is_active = 1) as product_count
        FROM merchandise_categories mc
        WHERE mc.is_active = 1 AND (mc.parent_id IS NULL OR mc.parent_id = 0)
        ORDER BY mc.display_order ASC, mc.name ASC
    ");
    $catStmt->execute();
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get subcategories for each parent
    $subCatStmt = $pdo->prepare("
        SELECT mc.*, 
               (SELECT COUNT(*) FROM merchandise_products mp WHERE mp.category_id = mc.id AND mp.is_active = 1) as product_count
        FROM merchandise_categories mc
        WHERE mc.is_active = 1 AND mc.parent_id = ?
        ORDER BY mc.display_order ASC, mc.name ASC
    ");
    
    foreach ($categories as &$cat) {
        $subCatStmt->execute([$cat['id']]);
        $cat['subcategories'] = $subCatStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($cat);
    
} catch (PDOException $e) {
    error_log("Dashboard shop categories fetch error: " . $e->getMessage());
    $categories = [];
}

// Build product query based on filters
$where = ["mp.is_active = 1"];
$params = [];

// Category filter
if ($categorySlug) {
    // Find category by slug or name
    $catIdStmt = $pdo->prepare("SELECT id FROM merchandise_categories WHERE slug = ? OR name = ? LIMIT 1");
    $catIdStmt->execute([$categorySlug, $categorySlug]);
    $filterCatId = $catIdStmt->fetchColumn();
    
    if ($filterCatId) {
        // Get all products in this category and its subcategories
        $subCatIds = [$filterCatId];
        $subStmt = $pdo->prepare("SELECT id FROM merchandise_categories WHERE parent_id = ?");
        $subStmt->execute([$filterCatId]);
        $subIds = $subStmt->fetchAll(PDO::FETCH_COLUMN);
        $subCatIds = array_merge($subCatIds, $subIds);
        
        $placeholders = implode(',', array_fill(0, count($subCatIds), '?'));
        $where[] = "mp.category_id IN ($placeholders)";
        $params = array_merge($params, $subCatIds);
    }
}

// Subcategory filter (more specific)
if ($subcategorySlug) {
    $subCatIdStmt = $pdo->prepare("SELECT id FROM merchandise_categories WHERE (slug = ? OR name = ?) AND parent_id IS NOT NULL LIMIT 1");
    $subCatIdStmt->execute([$subcategorySlug, $subcategorySlug]);
    $filterSubCatId = $subCatIdStmt->fetchColumn();
    
    if ($filterSubCatId) {
        // Reset category filter if subcategory is selected
        $where = ["mp.is_active = 1", "mp.category_id = ?"];
        $params = [$filterSubCatId];
    }
}

// Search filter
if ($search) {
    $where[] = "(mp.name LIKE ? OR mp.description LIKE ? OR mp.sku LIKE ?)";
    $searchTerm = "%$search%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

// Sort order
$orderBy = match($sortBy) {
    'price_low' => 'mp.price ASC',
    'price_high' => 'mp.price DESC',
    'name_az' => 'mp.name ASC',
    'name_za' => 'mp.name DESC',
    default => 'mp.created_at DESC'
};

$whereClause = implode(' AND ', $where);

// Get total count
$countSql = "SELECT COUNT(*) FROM merchandise_products mp WHERE $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalProducts = $countStmt->fetchColumn();
$totalPages = ceil($totalProducts / $perPage);

// Fetch products
$products = [];
try {
    $sql = "
        SELECT mp.*, mc.name as category_name, mc.slug as category_slug,
               pc.name as parent_category_name, pc.slug as parent_category_slug,
               (SELECT SUM(mps.quantity) FROM merchandise_product_sizes mps WHERE mps.product_id = mp.id) as total_quantity
        FROM merchandise_products mp
        LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
        LEFT JOIN merchandise_categories pc ON mc.parent_id = pc.id
        WHERE $whereClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";
    $params[] = $perPage;
    $params[] = $offset;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch sizes for each product
    $sizesStmt = $pdo->prepare("SELECT * FROM merchandise_product_sizes WHERE product_id = ? AND quantity > 0 ORDER BY id ASC");
    foreach ($products as &$product) {
        $sizesStmt->execute([$product['id']]);
        $product['sizes'] = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($product);
    
} catch (PDOException $e) {
    error_log("Dashboard shop products fetch error: " . $e->getMessage());
    $products = [];
}

// Helper function to build query string with only allowed parameters
function buildShopUrl($params = []) {
    // Only allow specific parameters to be carried over
    $allowedParams = ['page', 'category', 'subcategory', 'search', 'sort', 'shop_page'];
    $filtered = [];
    foreach ($params as $key => $value) {
        if (in_array($key, $allowedParams)) {
            $filtered[$key] = $value;
        }
    }
    $base = ['page' => 'shop'];
    return '?' . http_build_query(array_merge($base, $filtered));
}
?>

<!-- Shop Page Header -->
<div class="page-header">
    <h1 class="page-title"><i class="fas fa-store"></i> Shop</h1>
    <p class="page-description">Browse official Arctic Wolves merchandise</p>
</div>

<!-- Shop Container -->
<div class="shop-dashboard-container">
    <!-- Sidebar with Categories -->
    <aside class="shop-sidebar">
        <div class="shop-category-list">
            <h3><i class="fas fa-folder-tree"></i> Categories</h3>
            <div class="category-item">
                <a href="<?= buildShopUrl() ?>" class="category-link <?= empty($categorySlug) && empty($subcategorySlug) ? 'active' : '' ?>">
                    <span>All Products</span>
                    <span class="category-count"><?= $totalProducts ?></span>
                </a>
            </div>
            <?php foreach ($categories as $cat): ?>
                <div class="category-item">
                    <a href="<?= buildShopUrl(['category' => $cat['slug'] ?: $cat['name']]) ?>" 
                       class="category-link <?= $categorySlug == ($cat['slug'] ?: $cat['name']) ? 'active' : '' ?>">
                        <span><?= htmlspecialchars($cat['name']) ?></span>
                        <span class="category-count"><?= $cat['product_count'] ?></span>
                    </a>
                    <?php if (!empty($cat['subcategories'])): ?>
                        <div class="subcategory-list">
                            <?php foreach ($cat['subcategories'] as $subcat): ?>
                                <a href="<?= buildShopUrl(['category' => $cat['slug'] ?: $cat['name'], 'subcategory' => $subcat['slug'] ?: $subcat['name']]) ?>" 
                                   class="subcategory-link <?= $subcategorySlug == ($subcat['slug'] ?: $subcat['name']) ? 'active' : '' ?>">
                                    <?= htmlspecialchars($subcat['name']) ?> (<?= $subcat['product_count'] ?>)
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="shop-main">
        <!-- Filters Bar -->
        <div class="shop-filters-bar">
            <div class="shop-title-section">
                <h2>
                    <?php if ($subcategorySlug): ?>
                        <?= htmlspecialchars($subcategorySlug) ?>
                    <?php elseif ($categorySlug): ?>
                        <?= htmlspecialchars($categorySlug) ?>
                    <?php elseif ($search): ?>
                        Search: "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h2>
                <span class="product-count"><?= $totalProducts ?> product<?= $totalProducts !== 1 ? 's' : '' ?></span>
            </div>
            
            <div class="shop-filter-controls">
                <form action="dashboard.php" method="GET" class="shop-search-box">
                    <input type="hidden" name="page" value="shop">
                    <?php if ($categorySlug): ?>
                        <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
                    <?php endif; ?>
                    <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit"><i class="fas fa-search"></i></button>
                </form>
                
                <select class="shop-sort-select" onchange="window.location.href=this.value">
                    <option value="<?= buildShopUrl(array_merge($_GET, ['sort' => 'newest'])) ?>" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="<?= buildShopUrl(array_merge($_GET, ['sort' => 'price_low'])) ?>" <?= $sortBy == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                    <option value="<?= buildShopUrl(array_merge($_GET, ['sort' => 'price_high'])) ?>" <?= $sortBy == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                    <option value="<?= buildShopUrl(array_merge($_GET, ['sort' => 'name_az'])) ?>" <?= $sortBy == 'name_az' ? 'selected' : '' ?>>Name: A-Z</option>
                </select>
            </div>
        </div>

        <!-- Products Grid -->
        <?php if (empty($products)): ?>
            <div class="shop-empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No products found</h3>
                <p>No products match your current search or filter criteria.</p>
                <a href="<?= buildShopUrl() ?>" class="btn btn-primary">View All Products</a>
            </div>
        <?php else: ?>
            <div class="products-grid">
                <?php foreach ($products as $product): 
                    $inStock = ($product['total_quantity'] ?? 0) > 0;
                ?>
                    <div class="product-card <?= !$inStock ? 'out-of-stock' : '' ?>">
                        <?php if (!empty($product['image_url'])): ?>
                            <div class="product-image">
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                <?php if (!$inStock): ?>
                                    <span class="product-badge out-of-stock-badge">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="product-placeholder">
                                <i class="fas fa-tshirt"></i>
                                <?php if (!$inStock): ?>
                                    <span class="product-badge out-of-stock-badge">Out of Stock</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="product-info">
                            <?php if ($product['category_name']): ?>
                                <div class="product-category"><?= htmlspecialchars($product['parent_category_name'] ? $product['parent_category_name'] . ' / ' . $product['category_name'] : $product['category_name']) ?></div>
                            <?php endif; ?>
                            
                            <a href="shop_product.php?id=<?= $product['id'] ?>" class="product-name" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($product['name']) ?></a>
                            
                            <div class="product-price">$<?= number_format($product['price'], 2) ?></div>
                            
                            <?php if (!empty($product['sizes'])): ?>
                                <div class="product-sizes">
                                    <?php foreach (array_slice($product['sizes'], 0, 5) as $size): ?>
                                        <span class="size-tag"><?= htmlspecialchars($size['size']) ?></span>
                                    <?php endforeach; ?>
                                    <?php if (count($product['sizes']) > 5): ?>
                                        <span class="size-tag">+<?= count($product['sizes']) - 5 ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <a href="shop_product.php?id=<?= $product['id'] ?>" class="view-product-btn <?= !$inStock ? 'disabled-link' : '' ?>" target="_blank" rel="noopener noreferrer">
                                <i class="fas fa-eye"></i> View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="shop-pagination">
                    <?php if ($shopPage > 1): ?>
                        <a href="<?= buildShopUrl(array_merge($_GET, ['shop_page' => $shopPage - 1])) ?>" class="page-link">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $shopPage - 2); $i <= min($totalPages, $shopPage + 2); $i++): ?>
                        <a href="<?= buildShopUrl(array_merge($_GET, ['shop_page' => $i])) ?>" 
                           class="page-link <?= $i === $shopPage ? 'active' : '' ?>">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($shopPage < $totalPages): ?>
                        <a href="<?= buildShopUrl(array_merge($_GET, ['shop_page' => $shopPage + 1])) ?>" class="page-link">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>

<style>
/* Dashboard Shop Styles */
.shop-dashboard-container {
    display: flex;
    gap: 24px;
    min-height: 500px;
}

.shop-sidebar {
    width: 260px;
    flex-shrink: 0;
}

.shop-category-list {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.shop-category-list h3 {
    font-size: 13px;
    text-transform: uppercase;
    color: var(--text-dim);
    margin-bottom: 16px;
    letter-spacing: 1px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.shop-category-list h3 i {
    color: var(--primary);
}

.shop-sidebar .category-item {
    margin-bottom: 4px;
}

.shop-sidebar .category-link {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    color: var(--text-white);
    text-decoration: none;
    border-radius: 8px;
    font-weight: 500;
    font-size: 14px;
    transition: 0.2s;
}

.shop-sidebar .category-link:hover,
.shop-sidebar .category-link.active {
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary);
}

.shop-sidebar .category-count {
    font-size: 12px;
    color: var(--text-dim);
    background: var(--bg-main);
    padding: 2px 8px;
    border-radius: 10px;
}

.shop-sidebar .subcategory-list {
    margin-left: 12px;
    padding-left: 12px;
    border-left: 2px solid var(--border);
    margin-top: 4px;
}

.shop-sidebar .subcategory-link {
    display: block;
    padding: 6px 12px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 13px;
    transition: 0.2s;
    border-radius: 6px;
}

.shop-sidebar .subcategory-link:hover,
.shop-sidebar .subcategory-link.active {
    color: var(--primary);
    background: rgba(107, 70, 193, 0.05);
}

.shop-main {
    flex: 1;
    min-width: 0;
}

.shop-filters-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.shop-title-section h2 {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 4px;
}

.shop-title-section .product-count {
    font-size: 13px;
    color: var(--text-dim);
}

.shop-filter-controls {
    display: flex;
    gap: 12px;
    align-items: center;
}

.shop-search-box {
    position: relative;
}

.shop-search-box input {
    background: var(--bg-card);
    border: 1px solid var(--border);
    padding: 10px 40px 10px 14px;
    border-radius: 8px;
    color: var(--text-white);
    width: 220px;
    font-size: 14px;
}

.shop-search-box input:focus {
    outline: none;
    border-color: var(--primary);
}

.shop-search-box button {
    position: absolute;
    right: 10px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    color: var(--text-dim);
    cursor: pointer;
}

.shop-sort-select {
    background: var(--bg-card);
    border: 1px solid var(--border);
    padding: 10px 14px;
    border-radius: 8px;
    color: var(--text-white);
    cursor: pointer;
    font-size: 14px;
}

.shop-sort-select:focus {
    outline: none;
    border-color: var(--primary);
}

.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 20px;
}

.product-card {
    background: var(--bg-card);
    border-radius: 12px;
    overflow: hidden;
    transition: 0.3s;
    border: 1px solid var(--border);
}

.product-card:hover {
    transform: translateY(-4px);
    border-color: var(--primary);
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.15);
}

.product-card.out-of-stock {
    opacity: 0.65;
}

.product-image {
    height: 180px;
    overflow: hidden;
    position: relative;
}

.product-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: 0.3s;
}

.product-card:hover .product-image img {
    transform: scale(1.05);
}

.product-placeholder {
    height: 180px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.product-placeholder i {
    font-size: 50px;
    color: rgba(255,255,255,0.3);
}

.product-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    background: var(--primary);
    color: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
}

.product-badge.out-of-stock-badge {
    background: #ef4444;
}

.product-info {
    padding: 16px;
}

.product-category {
    font-size: 11px;
    color: var(--primary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 6px;
}

.product-name {
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 8px;
    color: var(--text-white);
    text-decoration: none;
    display: block;
    line-height: 1.3;
}

.product-name:hover {
    color: var(--primary);
}

.product-price {
    font-size: 20px;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 12px;
}

.product-sizes {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    margin-bottom: 12px;
}

.size-tag {
    padding: 3px 8px;
    background: rgba(255,255,255,0.08);
    border-radius: 4px;
    font-size: 11px;
    color: var(--text-dim);
}

.view-product-btn {
    width: 100%;
    padding: 10px;
    background: var(--primary);
    border: none;
    border-radius: 8px;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    text-decoration: none;
    font-size: 13px;
}

.view-product-btn:hover {
    background: var(--primary-hover);
}

.view-product-btn.disabled-link {
    background: var(--border);
    cursor: not-allowed;
    pointer-events: none;
    opacity: 0.6;
}

.shop-empty-state {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.shop-empty-state i {
    font-size: 50px;
    color: var(--text-dim);
    margin-bottom: 16px;
}

.shop-empty-state h3 {
    font-size: 18px;
    margin-bottom: 8px;
}

.shop-empty-state p {
    color: var(--text-dim);
    margin-bottom: 20px;
}

.shop-pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 32px;
}

.shop-pagination .page-link {
    padding: 10px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    text-decoration: none;
    font-weight: 600;
    transition: 0.2s;
}

.shop-pagination .page-link:hover,
.shop-pagination .page-link.active {
    background: var(--primary);
    border-color: var(--primary);
}

@media (max-width: 900px) {
    .shop-dashboard-container {
        flex-direction: column;
    }
    
    .shop-sidebar {
        width: 100%;
    }
    
    .shop-category-list {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        padding: 16px;
    }
    
    .shop-category-list h3 {
        width: 100%;
    }
    
    .shop-sidebar .category-item {
        margin-bottom: 0;
    }
    
    .shop-sidebar .subcategory-list {
        display: none;
    }
}

@media (max-width: 600px) {
    .shop-filter-controls {
        flex-direction: column;
        width: 100%;
    }
    
    .shop-search-box input {
        width: 100%;
    }
    
    .shop-sort-select {
        width: 100%;
    }
    
    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
    }
    
    .product-info {
        padding: 12px;
    }
    
    .product-price {
        font-size: 18px;
    }
}
</style>
