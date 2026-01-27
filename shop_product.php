<?php
/**
 * Shop Product Detail Page
 * View single product with size selection and add to cart
 */
require_once __DIR__ . '/db_config.php';

session_start();

// Initialize cart if not exists
if (!isset($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

$productId = intval($_GET['id'] ?? 0);

if ($productId <= 0) {
    header('Location: shop.php');
    exit();
}

// Fetch product details
try {
    $stmt = $pdo->prepare("
        SELECT mp.*, mc.name as category_name, mc.slug as category_slug,
               pc.name as parent_category_name, pc.slug as parent_category_slug
        FROM merchandise_products mp
        LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
        LEFT JOIN merchandise_categories pc ON mc.parent_id = pc.id
        WHERE mp.id = ? AND mp.is_active = 1
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header('Location: shop.php');
        exit();
    }
    
    // Fetch sizes
    $sizesStmt = $pdo->prepare("SELECT * FROM merchandise_product_sizes WHERE product_id = ? ORDER BY id ASC");
    $sizesStmt->execute([$productId]);
    $sizes = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch additional images
    $imagesStmt = $pdo->prepare("SELECT * FROM merchandise_product_images WHERE product_id = ? ORDER BY display_order ASC");
    $imagesStmt->execute([$productId]);
    $additionalImages = $imagesStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get related products
    $relatedStmt = $pdo->prepare("
        SELECT mp.*, 
               (SELECT SUM(mps.quantity) FROM merchandise_product_sizes mps WHERE mps.product_id = mp.id) as total_quantity
        FROM merchandise_products mp
        WHERE mp.category_id = ? AND mp.id != ? AND mp.is_active = 1
        ORDER BY RAND()
        LIMIT 4
    ");
    $relatedStmt->execute([$product['category_id'], $productId]);
    $relatedProducts = $relatedStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Product fetch error: " . $e->getMessage());
    header('Location: shop.php');
    exit();
}

$totalQuantity = array_sum(array_column($sizes, 'quantity'));
$inStock = $totalQuantity > 0;
$cartCount = array_sum(array_column($_SESSION['shop_cart'], 'quantity'));

// Handle add to cart via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    header('Content-Type: application/json');
    
    $size = trim($_POST['size'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);
    
    if (empty($size) && !empty($sizes)) {
        echo json_encode(['success' => false, 'message' => 'Please select a size']);
        exit();
    }
    
    if ($quantity < 1) {
        $quantity = 1;
    }
    
    // Check stock
    if (!empty($sizes)) {
        $sizeStock = array_filter($sizes, fn($s) => $s['size'] === $size);
        $sizeStock = reset($sizeStock);
        if (!$sizeStock || $sizeStock['quantity'] < $quantity) {
            echo json_encode(['success' => false, 'message' => 'Not enough stock available']);
            exit();
        }
    }
    
    // Add to cart
    $cartKey = $productId . '_' . $size;
    if (isset($_SESSION['shop_cart'][$cartKey])) {
        $_SESSION['shop_cart'][$cartKey]['quantity'] += $quantity;
    } else {
        $_SESSION['shop_cart'][$cartKey] = [
            'product_id' => $productId,
            'name' => $product['name'],
            'price' => $product['price'],
            'size' => $size,
            'quantity' => $quantity,
            'image_url' => $product['image_url']
        ];
    }
    
    $newCartCount = array_sum(array_column($_SESSION['shop_cart'], 'quantity'));
    echo json_encode(['success' => true, 'message' => 'Added to cart!', 'cart_count' => $newCartCount]);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($product['name']) ?> | Arctic Wolves Shop</title>
    <meta name="description" content="<?= htmlspecialchars(substr($product['description'] ?? '', 0, 160)) ?>">
    
    <link rel="icon" type="image/png" href="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .product-container {
            padding: 40px 0;
        }
        
        .breadcrumb {
            margin-bottom: 30px;
            font-size: 13px;
        }
        
        .breadcrumb a {
            color: var(--text-dim);
            text-decoration: none;
        }
        
        .breadcrumb a:hover {
            color: var(--primary);
        }
        
        .breadcrumb span {
            color: var(--text-dim);
            margin: 0 10px;
        }
        
        .product-detail {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 60px;
        }
        
        .product-gallery {
            position: sticky;
            top: 100px;
        }
        
        .main-image {
            width: 100%;
            aspect-ratio: 1;
            border-radius: 16px;
            overflow: hidden;
            background: var(--bg-card);
            margin-bottom: 15px;
        }
        
        .main-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .main-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .main-image-placeholder i {
            font-size: 100px;
            color: rgba(255,255,255,0.4);
        }
        
        .thumbnail-gallery {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            border: 2px solid transparent;
            transition: 0.2s;
        }
        
        .thumbnail:hover,
        .thumbnail.active {
            border-color: var(--primary);
        }
        
        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .product-details-content h1 {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 10px;
        }
        
        .product-category-link {
            color: var(--primary);
            text-decoration: none;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: block;
        }
        
        .product-category-link:hover {
            text-decoration: underline;
        }
        
        .product-price-large {
            font-size: 42px;
            font-weight: 900;
            color: var(--primary);
            margin-bottom: 20px;
        }
        
        .product-description {
            color: var(--text-dim);
            line-height: 1.8;
            margin-bottom: 30px;
        }
        
        .stock-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            margin-bottom: 25px;
        }
        
        .stock-status.in-stock {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }
        
        .stock-status.out-of-stock {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        
        .size-selector {
            margin-bottom: 25px;
        }
        
        .size-selector label {
            display: block;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .size-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .size-option {
            padding: 12px 24px;
            background: var(--bg-card);
            border: 2px solid var(--border);
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: 0.2s;
            position: relative;
        }
        
        .size-option:hover:not(.disabled) {
            border-color: var(--primary);
        }
        
        .size-option.selected {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .size-option.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            text-decoration: line-through;
        }
        
        .size-stock {
            font-size: 11px;
            color: var(--text-dim);
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
        }
        
        .quantity-selector {
            margin-bottom: 25px;
        }
        
        .quantity-selector label {
            display: block;
            font-weight: 700;
            margin-bottom: 12px;
        }
        
        .quantity-input {
            display: flex;
            align-items: center;
            gap: 0;
            width: fit-content;
        }
        
        .quantity-btn {
            width: 45px;
            height: 45px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            color: #fff;
            font-size: 18px;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .quantity-btn:first-child {
            border-radius: 8px 0 0 8px;
        }
        
        .quantity-btn:last-child {
            border-radius: 0 8px 8px 0;
        }
        
        .quantity-btn:hover {
            background: var(--primary);
        }
        
        .quantity-value {
            width: 60px;
            height: 45px;
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-left: none;
            border-right: none;
            color: #fff;
            text-align: center;
            font-size: 16px;
            font-weight: 600;
        }
        
        .add-to-cart-large {
            width: 100%;
            padding: 18px;
            background: var(--primary);
            border: none;
            border-radius: 12px;
            color: #fff;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 15px;
        }
        
        .add-to-cart-large:hover:not(:disabled) {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.3);
        }
        
        .add-to-cart-large:disabled {
            background: var(--border);
            cursor: not-allowed;
        }
        
        .buy-now-btn {
            width: 100%;
            padding: 18px;
            background: transparent;
            border: 2px solid var(--primary);
            border-radius: 12px;
            color: var(--primary);
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .buy-now-btn:hover:not(:disabled) {
            background: rgba(107, 70, 193, 0.1);
        }
        
        .product-meta {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid var(--border);
        }
        
        .meta-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .meta-item strong {
            color: #fff;
            min-width: 80px;
        }
        
        .meta-item span {
            color: var(--text-dim);
        }
        
        .related-section {
            margin-top: 80px;
            padding-top: 40px;
            border-top: 1px solid var(--border);
        }
        
        .related-section h2 {
            font-size: 28px;
            margin-bottom: 30px;
        }
        
        .related-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
            gap: 25px;
        }
        
        .related-card {
            background: var(--bg-card);
            border-radius: 12px;
            overflow: hidden;
            transition: 0.3s;
            text-decoration: none;
        }
        
        .related-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.3);
        }
        
        .related-image {
            height: 180px;
            overflow: hidden;
        }
        
        .related-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .related-placeholder {
            height: 180px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .related-placeholder i {
            font-size: 40px;
            color: rgba(255,255,255,0.4);
        }
        
        .related-info {
            padding: 15px;
        }
        
        .related-name {
            color: #fff;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .related-price {
            color: var(--primary);
            font-weight: 700;
        }
        
        .cart-notification {
            position: fixed;
            top: 100px;
            right: 30px;
            background: #10b981;
            color: #fff;
            padding: 16px 24px;
            border-radius: 12px;
            display: none;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
            z-index: 1000;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
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
        
        @media (max-width: 900px) {
            .product-detail {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .product-gallery {
                position: static;
            }
            
            .product-details-content h1 {
                font-size: 28px;
            }
            
            .product-price-large {
                font-size: 32px;
            }
        }
    </style>
</head>
<body>
    <header>
        <nav class="container nav-flex">
            <div class="logo-area" style="display: flex; align-items: center; gap: 15px;">
                <a href="index.php" style="display: flex; align-items: center; gap: 15px; text-decoration: none; color: inherit;">
                    <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Arctic Wolves Logo" style="height: 40px; width: auto;">
                    <div>
                        <div class="logo-text">ARCTIC<span>WOLVES</span></div>
                        <div class="header-affiliation">Player Development</div>
                    </div>
                </a>
            </div>
            
            <div class="nav-menu">
                <a href="index.php#programs">Programs</a>
                <a href="sessions_public.php">Sessions</a>
                <a href="shop.php" style="color: var(--primary);">Shop</a>
                <a href="index.php#standards">Standards</a>
                <a href="shop_cart.php" style="position: relative;">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="nav-cart-count" style="position: absolute; top: -8px; right: -8px; background: var(--primary); width: 18px; height: 18px; border-radius: 50%; font-size: 10px; display: <?= $cartCount > 0 ? 'flex' : 'none' ?>; align-items: center; justify-content: center;"><?= $cartCount ?></span>
                </a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <div class="container product-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="shop.php">Shop</a>
            <?php if ($product['parent_category_name']): ?>
                <span>/</span>
                <a href="shop.php?category=<?= urlencode($product['parent_category_slug'] ?: $product['parent_category_name']) ?>"><?= htmlspecialchars($product['parent_category_name']) ?></a>
            <?php endif; ?>
            <?php if ($product['category_name']): ?>
                <span>/</span>
                <a href="shop.php?category=<?= urlencode($product['category_slug'] ?: $product['category_name']) ?>"><?= htmlspecialchars($product['category_name']) ?></a>
            <?php endif; ?>
            <span>/</span>
            <span style="color: #fff;"><?= htmlspecialchars($product['name']) ?></span>
        </div>

        <div class="product-detail">
            <!-- Gallery -->
            <div class="product-gallery">
                <div class="main-image" id="main-image">
                    <?php if ($product['image_url']): ?>
                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>" id="main-image-img">
                    <?php else: ?>
                        <div class="main-image-placeholder">
                            <i class="fas fa-tshirt"></i>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($additionalImages) || $product['image_url']): ?>
                    <div class="thumbnail-gallery">
                        <?php if ($product['image_url']): ?>
                            <div class="thumbnail active" onclick="changeImage('<?= htmlspecialchars($product['image_url']) ?>', this)">
                                <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="Main">
                            </div>
                        <?php endif; ?>
                        <?php foreach ($additionalImages as $img): ?>
                            <div class="thumbnail" onclick="changeImage('<?= htmlspecialchars($img['image_url']) ?>', this)">
                                <img src="<?= htmlspecialchars($img['image_url']) ?>" alt="Product image">
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Details -->
            <div class="product-details-content">
                <?php if ($product['category_name']): ?>
                    <a href="shop.php?category=<?= urlencode($product['category_slug'] ?: $product['category_name']) ?>" class="product-category-link">
                        <?= htmlspecialchars($product['parent_category_name'] ? $product['parent_category_name'] . ' / ' . $product['category_name'] : $product['category_name']) ?>
                    </a>
                <?php endif; ?>
                
                <h1><?= htmlspecialchars($product['name']) ?></h1>
                
                <div class="product-price-large">$<?= number_format($product['price'], 2) ?></div>
                
                <div class="stock-status <?= $inStock ? 'in-stock' : 'out-of-stock' ?>">
                    <i class="fas <?= $inStock ? 'fa-check-circle' : 'fa-times-circle' ?>"></i>
                    <?= $inStock ? 'In Stock' : 'Out of Stock' ?>
                </div>
                
                <?php if ($product['description']): ?>
                    <div class="product-description">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($sizes)): ?>
                    <div class="size-selector">
                        <label>Select Size</label>
                        <div class="size-options">
                            <?php foreach ($sizes as $size): ?>
                                <div class="size-option <?= $size['quantity'] <= 0 ? 'disabled' : '' ?>" 
                                     data-size="<?= htmlspecialchars($size['size']) ?>"
                                     data-stock="<?= $size['quantity'] ?>"
                                     onclick="selectSize(this)">
                                    <?= htmlspecialchars($size['size']) ?>
                                    <span class="size-stock"><?= $size['quantity'] > 0 ? $size['quantity'] . ' left' : 'Sold out' ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="quantity-selector">
                    <label>Quantity</label>
                    <div class="quantity-input">
                        <button type="button" class="quantity-btn" onclick="changeQuantity(-1)">-</button>
                        <input type="number" class="quantity-value" id="quantity" value="1" min="1" max="99" readonly>
                        <button type="button" class="quantity-btn" onclick="changeQuantity(1)">+</button>
                    </div>
                </div>
                
                <button class="add-to-cart-large" id="add-to-cart-btn" onclick="addToCart()" <?= !$inStock ? 'disabled' : '' ?>>
                    <i class="fas fa-shopping-cart"></i>
                    <?= $inStock ? 'Add to Cart' : 'Out of Stock' ?>
                </button>
                
                <button class="buy-now-btn" onclick="buyNow()" <?= !$inStock ? 'disabled' : '' ?>>
                    Buy Now
                </button>
                
                <div class="product-meta">
                    <?php if ($product['sku']): ?>
                        <div class="meta-item">
                            <strong>SKU:</strong>
                            <span><?= htmlspecialchars($product['sku']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ($product['category_name']): ?>
                        <div class="meta-item">
                            <strong>Category:</strong>
                            <span><?= htmlspecialchars($product['parent_category_name'] ? $product['parent_category_name'] . ' / ' . $product['category_name'] : $product['category_name']) ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($relatedProducts)): ?>
            <div class="related-section">
                <h2>Related Products</h2>
                <div class="related-grid">
                    <?php foreach ($relatedProducts as $related): ?>
                        <a href="shop_product.php?id=<?= $related['id'] ?>" class="related-card">
                            <?php if ($related['image_url']): ?>
                                <div class="related-image">
                                    <img src="<?= htmlspecialchars($related['image_url']) ?>" alt="<?= htmlspecialchars($related['name']) ?>">
                                </div>
                            <?php else: ?>
                                <div class="related-placeholder">
                                    <i class="fas fa-tshirt"></i>
                                </div>
                            <?php endif; ?>
                            <div class="related-info">
                                <div class="related-name"><?= htmlspecialchars($related['name']) ?></div>
                                <div class="related-price">$<?= number_format($related['price'], 2) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cart Notification -->
    <div class="cart-notification" id="cart-notification">
        <i class="fas fa-check-circle"></i>
        <span>Added to cart!</span>
    </div>

    <!-- Floating Cart Icon -->
    <a href="shop_cart.php" class="cart-icon">
        <i class="fas fa-shopping-cart"></i>
        <span class="cart-badge" id="cart-badge" style="display: <?= $cartCount > 0 ? 'flex' : 'none' ?>;"><?= $cartCount ?></span>
    </a>

    <footer class="site-footer">
        <div class="container footer-flex">
            <div class="footer-left">
                <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                    <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Logo" style="height: 30px; opacity: 0.8;">
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

    <script>
        let selectedSize = '';
        const productId = <?= $productId ?>;
        const hasSizes = <?= !empty($sizes) ? 'true' : 'false' ?>;
        
        function selectSize(element) {
            if (element.classList.contains('disabled')) return;
            
            document.querySelectorAll('.size-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            selectedSize = element.dataset.size;
            
            // Reset quantity to 1 when size changes
            document.getElementById('quantity').value = 1;
        }
        
        function changeQuantity(delta) {
            const input = document.getElementById('quantity');
            let value = parseInt(input.value) + delta;
            if (value < 1) value = 1;
            if (value > 99) value = 99;
            input.value = value;
        }
        
        function changeImage(src, element) {
            const img = document.getElementById('main-image-img');
            if (img) {
                img.src = src;
            }
            document.querySelectorAll('.thumbnail').forEach(t => t.classList.remove('active'));
            element.classList.add('active');
        }
        
        function addToCart() {
            if (hasSizes && !selectedSize) {
                alert('Please select a size');
                return;
            }
            
            const quantity = document.getElementById('quantity').value;
            const btn = document.getElementById('add-to-cart-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
            
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('size', selectedSize);
            formData.append('quantity', quantity);
            
            fetch('shop_product.php?id=' + productId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update cart counts
                    document.getElementById('cart-badge').textContent = data.cart_count;
                    document.getElementById('cart-badge').style.display = 'flex';
                    document.getElementById('nav-cart-count').textContent = data.cart_count;
                    document.getElementById('nav-cart-count').style.display = 'flex';
                    
                    // Show notification
                    const notification = document.getElementById('cart-notification');
                    notification.style.display = 'flex';
                    setTimeout(() => {
                        notification.style.display = 'none';
                    }, 3000);
                } else {
                    alert(data.message || 'Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-shopping-cart"></i> Add to Cart';
            });
        }
        
        function buyNow() {
            if (hasSizes && !selectedSize) {
                alert('Please select a size');
                return;
            }
            
            const quantity = document.getElementById('quantity').value;
            
            const formData = new FormData();
            formData.append('action', 'add_to_cart');
            formData.append('size', selectedSize);
            formData.append('quantity', quantity);
            
            fetch('shop_product.php?id=' + productId, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = 'shop_cart.php';
                } else {
                    alert(data.message || 'Failed to add to cart');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }
    </script>
</body>
</html>
