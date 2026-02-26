<?php
/**
 * Public Shop Page - Browse and purchase merchandise
 * Supports guest checkout and logged-in user purchases
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/site_branding.php';
require_once __DIR__ . '/lib/image_helper.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Start session for cart functionality
session_start();

// Initialize cart if not exists
if (!isset($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

// Get filter parameters
$categorySlug = $_GET['category'] ?? '';
$subcategorySlug = $_GET['subcategory'] ?? '';
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'newest';
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Fetch all active categories with subcategories
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
    error_log("Shop categories fetch error: " . $e->getMessage());
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
    error_log("Shop products fetch error: " . $e->getMessage());
    $products = [];
}

// Get cart count
$cartCount = array_sum(array_column($_SESSION['shop_cart'], 'quantity'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shop | Arctic Wolves</title>
    <meta name="description" content="Shop official Arctic Wolves merchandise - jerseys, apparel, and accessories.">
    
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        /* Shop-specific styles */
        .shop-container {
            display: flex;
            gap: 30px;
            padding: 40px 0;
        }
        
        .shop-sidebar {
            width: 280px;
            flex-shrink: 0;
        }
        
        .shop-main {
            flex: 1;
        }
        
        .category-list {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .category-list h3 {
            font-size: 14px;
            text-transform: uppercase;
            color: var(--text-dim);
            margin-bottom: 15px;
            letter-spacing: 1px;
        }
        
        .category-item {
            margin-bottom: 8px;
        }
        
        .category-link {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 12px;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: 0.2s;
        }
        
        .category-link:hover,
        .category-link.active {
            background: rgba(107, 70, 193, 0.1);
            color: var(--primary);
        }
        
        .category-count {
            font-size: 12px;
            color: var(--text-dim);
        }
        
        .subcategory-list {
            margin-left: 15px;
            padding-left: 15px;
            border-left: 2px solid var(--border);
        }
        
        .subcategory-link {
            display: block;
            padding: 6px 12px;
            color: var(--text-dim);
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }
        
        .subcategory-link:hover,
        .subcategory-link.active {
            color: var(--primary);
        }
        
        .shop-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .shop-title {
            font-size: 32px;
            font-weight: 900;
        }
        
        .shop-filters {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box input {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 12px 45px 12px 16px;
            border-radius: 8px;
            color: #fff;
            width: 250px;
        }
        
        .search-box button {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-dim);
            cursor: pointer;
        }
        
        .sort-select {
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 12px 16px;
            border-radius: 8px;
            color: #fff;
            cursor: pointer;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
        }
        
        .product-card {
            background: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            transition: 0.3s;
            border: 1px solid var(--border);
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            border-color: var(--primary);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.2);
        }
        
        .product-image {
            height: 220px;
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
            height: 220px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .product-placeholder i {
            font-size: 60px;
            color: rgba(255,255,255,0.4);
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
        
        .product-info {
            padding: 20px;
        }
        
        .product-category {
            font-size: 11px;
            color: var(--primary);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        
        .product-name {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #fff;
            text-decoration: none;
            display: block;
        }
        
        .product-name:hover {
            color: var(--primary);
        }
        
        .product-price {
            font-size: 22px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .product-sizes {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            margin-bottom: 15px;
        }
        
        .size-tag {
            padding: 4px 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            font-size: 11px;
            color: var(--text-dim);
        }
        
        .add-to-cart-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .add-to-cart-btn:hover {
            background: var(--accent);
        }
        
        .add-to-cart-btn:disabled {
            background: var(--border);
            cursor: not-allowed;
        }
        
        .out-of-stock {
            opacity: 0.6;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 40px;
        }
        
        .page-link {
            padding: 10px 16px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 8px;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            transition: 0.2s;
        }
        
        .page-link:hover,
        .page-link.active {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .cart-icon {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: var(--primary);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 5px 20px rgba(107, 70, 193, 0.4);
            transition: 0.3s;
            text-decoration: none;
            z-index: 100;
        }
        
        .cart-icon:hover {
            transform: scale(1.1);
        }
        
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 700;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 60px;
            color: var(--text-dim);
            margin-bottom: 20px;
        }
        
        .empty-state p {
            color: var(--text-dim);
            margin-bottom: 20px;
        }
        
        @media (max-width: 900px) {
            .shop-container {
                flex-direction: column;
            }
            
            .shop-sidebar {
                width: 100%;
            }
            
            .category-list {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
            }
            
            .category-item {
                margin-bottom: 0;
            }
        }
        
        @media (max-width: 600px) {
            .shop-filters {
                flex-direction: column;
                width: 100%;
            }
            
            .search-box input {
                width: 100%;
            }
            
            .products-grid {
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container nav-flex">
            <div class="logo-area" style="display: flex; align-items: center; gap: 15px;">
                <a href="index.php" style="display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit;">
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves Logo" style="height: 40px; width: auto;">
                    <div>
                        <div class="logo-text">ARCTIC<span>WOLVES</span></div>
                        <div class="header-affiliation">Player Development</div>
                    </div>
                </a>
            </div>
            
            <div class="nav-menu">
                <a href="index.php">Home</a>
                <a href="shop.php" style="color: var(--primary);">Shop</a>
                <a href="shop_cart.php" style="position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                    <?php if ($cartCount > 0): ?>
                        <span style="position: absolute; top: -8px; right: -8px; background: var(--primary); width: 18px; height: 18px; border-radius: 50%; font-size: 10px; display: flex; align-items: center; justify-content: center;"><?= $cartCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <div class="container shop-container">
        <!-- Sidebar -->
        <aside class="shop-sidebar">
            <div class="category-list">
                <h3>Categories</h3>
                <div class="category-item">
                    <a href="shop.php" class="category-link <?= empty($categorySlug) && empty($subcategorySlug) ? 'active' : '' ?>">
                        <span>All Products</span>
                        <span class="category-count"><?= $totalProducts ?></span>
                    </a>
                </div>
                <?php foreach ($categories as $cat): ?>
                    <div class="category-item">
                        <a href="shop.php?category=<?= urlencode($cat['slug'] ?: $cat['name']) ?>" 
                           class="category-link <?= $categorySlug == ($cat['slug'] ?: $cat['name']) ? 'active' : '' ?>">
                            <span><?= htmlspecialchars($cat['name']) ?></span>
                            <span class="category-count"><?= $cat['product_count'] ?></span>
                        </a>
                        <?php if (!empty($cat['subcategories'])): ?>
                            <div class="subcategory-list">
                                <?php foreach ($cat['subcategories'] as $subcat): ?>
                                    <a href="shop.php?category=<?= urlencode($cat['slug'] ?: $cat['name']) ?>&subcategory=<?= urlencode($subcat['slug'] ?: $subcat['name']) ?>" 
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
            <div class="shop-header">
                <h1 class="shop-title">
                    <?php if ($subcategorySlug): ?>
                        <?= htmlspecialchars($subcategorySlug) ?>
                    <?php elseif ($categorySlug): ?>
                        <?= htmlspecialchars($categorySlug) ?>
                    <?php elseif ($search): ?>
                        Search: "<?= htmlspecialchars($search) ?>"
                    <?php else: ?>
                        All Products
                    <?php endif; ?>
                </h1>
                
                <div class="shop-filters">
                    <form action="shop.php" method="GET" class="search-box">
                        <?php if ($categorySlug): ?>
                            <input type="hidden" name="category" value="<?= htmlspecialchars($categorySlug) ?>">
                        <?php endif; ?>
                        <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                        <button type="submit"><i class="fas fa-search"></i></button>
                    </form>
                    
                    <select class="sort-select" onchange="window.location.href=this.value">
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'newest'])) ?>" <?= $sortBy == 'newest' ? 'selected' : '' ?>>Newest</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_low'])) ?>" <?= $sortBy == 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'price_high'])) ?>" <?= $sortBy == 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                        <option value="?<?= http_build_query(array_merge($_GET, ['sort' => 'name_az'])) ?>" <?= $sortBy == 'name_az' ? 'selected' : '' ?>>Name: A-Z</option>
                    </select>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <p>No products found matching your criteria.</p>
                    <a href="shop.php" class="btn-primary">View All Products</a>
                </div>
            <?php else: ?>
                <div class="products-grid">
                    <?php foreach ($products as $product): 
                        $inStock = ($product['total_quantity'] ?? 0) > 0;
                    ?>
                        <div class="product-card <?= !$inStock ? 'out-of-stock' : '' ?>">
                            <?php if (!empty($product['image_url'])): ?>
                                <div class="product-image">
                                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $product['image_url'])) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                                    <?php if (!$inStock): ?>
                                        <span class="product-badge" style="background: #ef4444;">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <div class="product-placeholder">
                                    <i class="fas fa-tshirt"></i>
                                    <?php if (!$inStock): ?>
                                        <span class="product-badge" style="background: #ef4444; position: absolute; top: 10px; left: 10px;">Out of Stock</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="product-info">
                                <?php if ($product['category_name']): ?>
                                    <div class="product-category"><?= htmlspecialchars($product['parent_category_name'] ? $product['parent_category_name'] . ' / ' . $product['category_name'] : $product['category_name']) ?></div>
                                <?php endif; ?>
                                
                                <a href="shop_product.php?id=<?= $product['id'] ?>" class="product-name"><?= htmlspecialchars($product['name']) ?></a>
                                
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
                                
                                <a href="shop_product.php?id=<?= $product['id'] ?>" class="add-to-cart-btn" <?= !$inStock ? 'disabled' : '' ?>>
                                    <i class="fas fa-eye"></i> View Details
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-link <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Floating Cart Icon -->
    <a href="shop_cart.php" class="cart-icon">
        <i class="fas fa-shopping-cart"></i>
        <?php if ($cartCount > 0): ?>
            <span class="cart-badge"><?= $cartCount ?></span>
        <?php endif; ?>
    </a>

    <footer class="site-footer">
        <div class="container footer-flex">
            <div class="footer-left">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" style="height: 30px; opacity: 0.8;">
                    <div class="logo-text" style="font-size: 1.2rem;">ARCTIC<span>WOLVES</span></div>
                </div>
                <p class="footer-desc">High-performance athletic development.</p>
            </div>
            <div class="footer-right">
                <div class="footer-col">
                    <h4>Shop</h4>
                    <a href="shop.php">All Products</a>
                    <a href="shop_cart.php">Cart</a>
                </div>
                <div class="footer-col">
                    <h4>Account</h4>
                    <a href="login.php">Athlete Portal</a>
                    <a href="register.php">Registration</a>
                </div>
            </div>
        </div>
        <div class="footer-bottom">
            <div class="container footer-bottom-flex">
                <p>&copy; 2026 Arctic Wolves Player Development. All Rights Reserved.</p>
            </div>
        </div>
    </footer>
</body>
</html>
