<?php
/**
 * PWA Shop - Mobile-native product grid with add-to-cart
 * Purpose-built for mobile phones.
 */
require_once __DIR__ . '/../../lib/image_helper.php';

if (!isset($_SESSION['shop_cart'])) { $_SESSION['shop_cart'] = []; }
$shopCartCount = array_sum(array_column($_SESSION['shop_cart'], 'quantity'));

// Fetch categories for filter pills
$shopCategories = [];
try {
    $catStmt = $pdo->prepare("SELECT id, name FROM product_categories ORDER BY name ASC");
    $catStmt->execute();
    $shopCategories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $shopCategories = []; }

$products = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.name, p.description, p.price, p.image_url, p.stock_quantity,
               p.category_id, c.name as category_name
        FROM products p
        LEFT JOIN product_categories c ON c.id = p.category_id
        WHERE p.is_active = 1
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $products = []; }
?>
<style>
.m-shop { padding: 16px; font-family: Inter, sans-serif; }
.m-shop-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
.m-shop-header-left { flex: 1; }
.m-shop-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-shop-count { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-shop-cart-btn {
    position: relative; background: #6B46C1; color: #fff; border: none; border-radius: 10px;
    width: 44px; height: 44px; display: flex; align-items: center; justify-content: center;
    font-size: 16px; cursor: pointer; flex-shrink: 0;
}
.m-shop-cart-btn:active { opacity: 0.85; }
.m-shop-cart-badge {
    position: absolute; top: -4px; right: -4px; background: #EF4444; color: #fff;
    font-size: 10px; font-weight: 700; min-width: 18px; height: 18px; border-radius: 9px;
    display: flex; align-items: center; justify-content: center; padding: 0 4px;
}
.m-product-grid {
    display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
}
.m-product-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    overflow: hidden; display: flex; flex-direction: column;
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
.m-product-add-btn {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px;
    font-weight: 600; font-size: 12px; cursor: pointer; width: 100%; margin-top: 8px;
    font-family: Inter, sans-serif; display: flex; align-items: center; justify-content: center; gap: 4px;
}
.m-product-add-btn:active { opacity: 0.85; }
.m-product-add-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.m-shop-filter-bar { margin-bottom: 14px; }
.m-shop-search-row { display: flex; gap: 8px; margin-bottom: 10px; }
.m-shop-search {
    flex: 1; min-height: 44px; background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 14px; padding: 0 14px; font-family: Inter, sans-serif;
    outline: none; -webkit-appearance: none;
}
.m-shop-search::placeholder { color: #6B6B7B; }
.m-shop-search:focus { border-color: #6B46C1; }
.m-shop-sort {
    min-height: 44px; background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; font-size: 12px; padding: 0 10px; font-family: Inter, sans-serif;
    cursor: pointer; outline: none; -webkit-appearance: none; flex-shrink: 0;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='6'%3E%3Cpath d='M0 0l5 6 5-6z' fill='%236B6B7B'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 10px center; padding-right: 26px;
}
.m-shop-sort:focus { border-color: #6B46C1; }
.m-shop-cats {
    display: flex; gap: 8px; overflow-x: auto; padding-bottom: 4px;
    -webkit-overflow-scrolling: touch; scrollbar-width: none;
}
.m-shop-cats::-webkit-scrollbar { display: none; }
.m-shop-cat-pill {
    flex-shrink: 0; min-height: 36px; padding: 0 14px; border-radius: 18px;
    background: #16161F; border: 1px solid #2D2D3F; color: #A8A8B8;
    font-size: 12px; font-weight: 600; font-family: Inter, sans-serif;
    cursor: pointer; white-space: nowrap; display: flex; align-items: center;
    transition: background 0.15s, border-color 0.15s, color 0.15s;
}
.m-shop-cat-pill.m-active {
    background: #6B46C1; border-color: #6B46C1; color: #fff;
}
.m-shop-cat-pill:active { opacity: 0.85; }
.m-product-details-link {
    display: flex; align-items: center; justify-content: center; gap: 4px;
    color: #8B5CF6; font-size: 12px; font-weight: 600; text-decoration: none;
    min-height: 36px; margin-top: 4px; border-radius: 8px;
    background: rgba(107,70,193,0.1);
}
.m-product-details-link:active { opacity: 0.85; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
.m-empty-filter { text-align: center; padding: 30px 16px; color: #6B6B7B; display: none; }
.m-empty-filter i { font-size: 28px; display: block; margin-bottom: 10px; }
.m-empty-filter p { font-size: 13px; margin: 0; }
.m-shop-pagination {
    display: flex; justify-content: center; align-items: center; gap: 6px;
    margin-top: 16px; padding: 8px 0;
}
.m-shop-page-btn {
    min-width: 40px; min-height: 40px; border-radius: 10px; border: 1px solid #2D2D3F;
    background: #16161F; color: #A8A8B8; font-size: 13px; font-weight: 600;
    font-family: Inter, sans-serif; cursor: pointer; display: flex;
    align-items: center; justify-content: center;
}
.m-shop-page-btn.m-active { background: #6B46C1; border-color: #6B46C1; color: #fff; }
.m-shop-page-btn:disabled { opacity: 0.3; cursor: not-allowed; }
.m-shop-page-btn:active:not(:disabled) { opacity: 0.85; }
.m-shop-page-info { font-size: 12px; color: #6B6B7B; margin: 0 4px; }
/* Cart bottom sheet */
.m-shop-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1000; align-items: flex-end; justify-content: center;
}
.m-shop-overlay.m-visible { display: flex; }
.m-shop-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 500px;
    max-height: 80vh; overflow-y: auto; padding: 20px 16px 32px;
    animation: mShopSlideUp 0.3s ease;
}
@keyframes mShopSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-shop-sheet-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-shop-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; text-align: center; }
.m-shop-cart-item {
    display: flex; align-items: center; gap: 10px; padding: 12px 0;
    border-bottom: 1px solid #2D2D3F;
}
.m-shop-cart-item-img { width: 44px; height: 44px; border-radius: 8px; background: #0A0A0F; display: flex; align-items: center; justify-content: center; overflow: hidden; flex-shrink: 0; }
.m-shop-cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
.m-shop-cart-item-img i { color: #2D2D3F; font-size: 16px; }
.m-shop-cart-item-info { flex: 1; min-width: 0; }
.m-shop-cart-item-name { font-size: 13px; font-weight: 600; color: #fff; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.m-shop-cart-item-size { font-size: 11px; color: #6B6B7B; }
.m-shop-cart-item-price { font-size: 13px; font-weight: 700; color: #10B981; }
.m-shop-cart-item-remove {
    background: none; border: none; color: #EF4444; font-size: 14px; cursor: pointer;
    width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;
}
.m-shop-cart-empty { text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px; }
.m-shop-cart-total {
    display: flex; justify-content: space-between; padding: 14px 0 0;
    font-size: 16px; font-weight: 700; color: #fff;
}
.m-shop-checkout-btn {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px;
    font-weight: 600; font-size: 14px; cursor: pointer; width: 100%; margin-top: 14px;
    font-family: Inter, sans-serif; display: flex; align-items: center; justify-content: center; gap: 6px;
    text-decoration: none;
}
.m-shop-checkout-btn:active { opacity: 0.85; }
.m-shop-toast {
    display: none; position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    background: #10B981; color: #fff; padding: 10px 20px; border-radius: 10px;
    font-size: 13px; font-weight: 600; z-index: 1100; font-family: Inter, sans-serif;
}
.m-shop-toast.m-visible { display: block; }
</style>

<div class="m-shop">
    <div class="m-shop-header">
        <div class="m-shop-header-left">
            <h2 class="m-shop-title">Shop</h2>
            <p class="m-shop-count" id="mShopCountWrap"><span id="mShopCount"><?= count($products) ?></span> product<?= count($products) !== 1 ? 's' : '' ?></p>
        </div>
        <button class="m-shop-cart-btn" onclick="mOpenShopCart()" type="button">
            <i class="fas fa-shopping-cart"></i>
            <span class="m-shop-cart-badge" id="mShopCartBadge" style="<?= $shopCartCount > 0 ? '' : 'display:none' ?>"><?= $shopCartCount ?></span>
        </button>
    </div>

    <?php if (empty($products)): ?>
        <div class="m-empty-state">
            <i class="fas fa-store-slash"></i>
            <p>No products available</p>
        </div>
    <?php else: ?>
        <!-- Search, Sort & Category Filter -->
        <div class="m-shop-filter-bar">
            <div class="m-shop-search-row">
                <input type="text" class="m-shop-search" id="mShopSearch" placeholder="Search products...">
                <select class="m-shop-sort" id="mShopSort">
                    <option value="newest">Newest</option>
                    <option value="price_low">Price: Low→High</option>
                    <option value="price_high">Price: High→Low</option>
                    <option value="name_az">Name: A-Z</option>
                </select>
            </div>
            <div class="m-shop-cats" id="mShopCats">
                <button type="button" class="m-shop-cat-pill m-active" data-cat="">All</button>
                <?php foreach ($shopCategories as $sc): ?>
                <button type="button" class="m-shop-cat-pill" data-cat="<?= (int)$sc['id'] ?>"><?= htmlspecialchars($sc['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="m-empty-filter" id="mShopEmptyFilter">
            <i class="fas fa-search"></i>
            <p>No products match your filters</p>
        </div>

        <div class="m-product-grid" id="mProductGrid">
            <?php foreach ($products as $idx => $p):
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
            <div class="m-product-card"
                 data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>"
                 data-price="<?= (float)$p['price'] ?>"
                 data-cat="<?= (int)($p['category_id'] ?? 0) ?>"
                 data-idx="<?= $idx ?>">
                <a href="?page=shop&product_id=<?= (int)$p['id'] ?>" style="text-decoration:none;">
                    <div class="m-product-img">
                        <?php if (!empty($p['image_url'])): ?>
                            <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $p['image_url'])) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-box"></i>
                        <?php endif; ?>
                    </div>
                </a>
                <div class="m-product-info">
                    <?php if (!empty($p['category_name'])): ?>
                    <div class="m-product-cat"><?= htmlspecialchars($p['category_name']) ?></div>
                    <?php endif; ?>
                    <h3 class="m-product-name"><?= htmlspecialchars($p['name']) ?></h3>
                    <div class="m-product-bottom">
                        <span class="m-product-price">$<?= number_format((float)$p['price'], 2) ?></span>
                        <span class="m-product-stock m-product-stock-<?= $stockClass ?>"><?= $stockLabel ?></span>
                    </div>
                    <button class="m-product-add-btn"
                            <?= $stock <= 0 ? 'disabled' : '' ?>
                            onclick="mShopAddToCart(<?= (int)$p['id'] ?>, this)"
                            type="button">
                        <i class="fas fa-cart-plus"></i> Add to Cart
                    </button>
                    <a href="?page=shop&product_id=<?= (int)$p['id'] ?>" class="m-product-details-link">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="m-shop-pagination" id="mShopPagination"></div>
    <?php endif; ?>
</div>

<!-- Cart Bottom Sheet -->
<div class="m-shop-overlay" id="mShopOverlay" onclick="if(event.target===this)mCloseShopCart()">
    <div class="m-shop-sheet">
        <div class="m-shop-sheet-handle"></div>
        <div class="m-shop-sheet-title">Your Cart</div>
        <div id="mShopCartContent">
            <?php if (empty($_SESSION['shop_cart'])): ?>
            <div class="m-shop-cart-empty"><i class="fas fa-shopping-cart" style="font-size:24px;display:block;margin-bottom:8px;"></i>Your cart is empty</div>
            <?php else: ?>
                <?php
                $cartTotal = 0;
                foreach ($_SESSION['shop_cart'] as $ck => $ci):
                    $lineTotal = (float)$ci['price'] * (int)$ci['quantity'];
                    $cartTotal += $lineTotal;
                ?>
                <div class="m-shop-cart-item">
                    <div class="m-shop-cart-item-img">
                        <?php if (!empty($ci['image_url'])): ?><img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $ci['image_url'])) ?>"><?php else: ?><i class="fas fa-box"></i><?php endif; ?>
                    </div>
                    <div class="m-shop-cart-item-info">
                        <div class="m-shop-cart-item-name"><?= htmlspecialchars($ci['name']) ?></div>
                        <?php if (!empty($ci['size'])): ?><div class="m-shop-cart-item-size">Size: <?= htmlspecialchars($ci['size']) ?></div><?php endif; ?>
                        <div class="m-shop-cart-item-price">$<?= number_format((float)$ci['price'], 2) ?> × <?= (int)$ci['quantity'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div id="mShopCartFooter" style="<?= empty($_SESSION['shop_cart']) ? 'display:none' : '' ?>">
            <div class="m-shop-cart-total">
                <span>Total</span>
                <span id="mShopCartTotal">$<?= number_format($cartTotal ?? 0, 2) ?></span>
            </div>
            <a href="shop_checkout.php" class="m-shop-checkout-btn">
                <i class="fas fa-lock"></i> Checkout
            </a>
        </div>
    </div>
</div>

<div class="m-shop-toast" id="mShopToast"></div>

<script>
function mOpenShopCart() {
    document.getElementById('mShopOverlay').classList.add('m-visible');
}
function mCloseShopCart() { document.getElementById('mShopOverlay').classList.remove('m-visible'); }

function mShopAddToCart(productId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    var formData = new FormData();
    formData.append('action', 'add_to_cart');
    formData.append('size', '');
    formData.append('quantity', '1');
    fetch('shop_product.php?id=' + productId, {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var badge = document.getElementById('mShopCartBadge');
            badge.textContent = data.cart_count;
            badge.style.display = 'flex';
            mShowShopToast('Added to cart!');
        } else {
            showToast(data.message || 'Could not add to cart', 'error');
        }
    })
    .catch(function() { showToast('An error occurred.', 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-cart-plus"></i> Add to Cart'; });
}

function mShowShopToast(msg) {
    var t = document.getElementById('mShopToast');
    t.textContent = msg;
    t.classList.add('m-visible');
    setTimeout(function() { t.classList.remove('m-visible'); }, 2500);
}

/* --- Client-side Search, Category Filter, Sort & Pagination --- */
(function() {
    var PER_PAGE = 20;
    var currentPage = 1;
    var grid = document.getElementById('mProductGrid');
    if (!grid) return;

    var allCards = Array.prototype.slice.call(grid.querySelectorAll('.m-product-card'));
    var searchInput = document.getElementById('mShopSearch');
    var sortSelect = document.getElementById('mShopSort');
    var catContainer = document.getElementById('mShopCats');
    var paginationEl = document.getElementById('mShopPagination');
    var emptyFilterEl = document.getElementById('mShopEmptyFilter');
    var countEl = document.getElementById('mShopCount');
    var activeCat = '';

    function getFiltered() {
        var query = (searchInput ? searchInput.value.toLowerCase().trim() : '');
        var filtered = [];
        for (var i = 0; i < allCards.length; i++) {
            var card = allCards[i];
            var name = card.getAttribute('data-name') || '';
            var cat = card.getAttribute('data-cat') || '';
            var matchSearch = !query || name.indexOf(query) !== -1;
            var matchCat = !activeCat || cat === activeCat;
            if (matchSearch && matchCat) filtered.push(card);
        }
        return filtered;
    }

    function sortCards(cards) {
        var sortVal = sortSelect ? sortSelect.value : 'newest';
        var sorted = cards.slice();
        sorted.sort(function(a, b) {
            if (sortVal === 'price_low') return parseFloat(a.getAttribute('data-price')) - parseFloat(b.getAttribute('data-price'));
            if (sortVal === 'price_high') return parseFloat(b.getAttribute('data-price')) - parseFloat(a.getAttribute('data-price'));
            if (sortVal === 'name_az') return (a.getAttribute('data-name') || '').localeCompare(b.getAttribute('data-name') || '');
            return parseInt(b.getAttribute('data-idx')) - parseInt(a.getAttribute('data-idx'));
        });
        return sorted;
    }

    function renderPagination(total) {
        if (!paginationEl) return;
        var totalPages = Math.ceil(total / PER_PAGE);
        if (totalPages <= 1) { paginationEl.innerHTML = ''; return; }
        var html = '';
        html += '<button class="m-shop-page-btn" onclick="mShopGoPage(' + (currentPage - 1) + ')"' + (currentPage <= 1 ? ' disabled' : '') + '><i class="fas fa-chevron-left"></i></button>';
        var start = Math.max(1, currentPage - 2);
        var end = Math.min(totalPages, currentPage + 2);
        for (var i = start; i <= end; i++) {
            html += '<button class="m-shop-page-btn' + (i === currentPage ? ' m-active' : '') + '" onclick="mShopGoPage(' + i + ')">' + i + '</button>';
        }
        html += '<button class="m-shop-page-btn" onclick="mShopGoPage(' + (currentPage + 1) + ')"' + (currentPage >= totalPages ? ' disabled' : '') + '><i class="fas fa-chevron-right"></i></button>';
        paginationEl.innerHTML = html;
    }

    function applyFilters() {
        var filtered = sortCards(getFiltered());
        var total = filtered.length;
        var totalPages = Math.ceil(total / PER_PAGE) || 1;
        if (currentPage > totalPages) currentPage = totalPages;
        var startIdx = (currentPage - 1) * PER_PAGE;
        var endIdx = startIdx + PER_PAGE;
        var visible = {};
        for (var i = 0; i < filtered.length; i++) {
            if (i >= startIdx && i < endIdx) visible[filtered[i].getAttribute('data-idx')] = true;
        }
        // Reorder DOM to match sort and show/hide
        for (var j = 0; j < filtered.length; j++) {
            grid.appendChild(filtered[j]);
        }
        for (var k = 0; k < allCards.length; k++) {
            var idx = allCards[k].getAttribute('data-idx');
            allCards[k].style.display = visible[idx] ? '' : 'none';
        }
        var countWrap = document.getElementById('mShopCountWrap');
        if (countWrap) countWrap.innerHTML = '<span id="mShopCount">' + total + '</span> product' + (total !== 1 ? 's' : '');
        if (emptyFilterEl) emptyFilterEl.style.display = total === 0 ? 'block' : 'none';
        if (grid) grid.style.display = total === 0 ? 'none' : '';
        renderPagination(total);
    }

    window.mShopGoPage = function(p) {
        var filtered = getFiltered();
        var totalPages = Math.ceil(filtered.length / PER_PAGE) || 1;
        if (p < 1 || p > totalPages) return;
        currentPage = p;
        applyFilters();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    if (searchInput) {
        var debounceTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() { currentPage = 1; applyFilters(); }, 200);
        });
    }

    if (sortSelect) {
        sortSelect.addEventListener('change', function() { applyFilters(); });
    }

    if (catContainer) {
        catContainer.addEventListener('click', function(e) {
            var pill = e.target.closest('.m-shop-cat-pill');
            if (!pill) return;
            var pills = catContainer.querySelectorAll('.m-shop-cat-pill');
            for (var i = 0; i < pills.length; i++) pills[i].classList.remove('m-active');
            pill.classList.add('m-active');
            activeCat = pill.getAttribute('data-cat') || '';
            currentPage = 1;
            applyFilters();
        });
    }

    // Initial render with pagination
    applyFilters();
})();
</script>
