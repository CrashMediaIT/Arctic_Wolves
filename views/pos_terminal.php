<?php
/**
 * POS Terminal View
 * Point of Sale interface for front desk staff and admins
 * Integrated with Stripe Terminal (bbpos wisepos e)
 */

// Check access - require authentication in dashboard context
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Fetch all active products
try {
    $stmt = $pdo->prepare("
        SELECT mp.*, mc.name as category_name,
               (SELECT SUM(mps.quantity) FROM merchandise_product_sizes mps WHERE mps.product_id = mp.id) as total_quantity
        FROM merchandise_products mp
        LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
        WHERE mp.is_active = 1
        ORDER BY mc.display_order ASC, mc.name ASC, mp.name ASC
    ");
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch sizes for each product
    $sizesStmt = $pdo->prepare("SELECT * FROM merchandise_product_sizes WHERE product_id = ? ORDER BY id ASC");
    foreach ($products as &$product) {
        $sizesStmt->execute([$product['id']]);
        $product['sizes'] = $sizesStmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($product);
    
    // Get categories for filtering
    $catStmt = $pdo->query("SELECT * FROM merchandise_categories WHERE is_active = 1 AND (parent_id IS NULL OR parent_id = 0) ORDER BY display_order ASC, name ASC");
    $categories = $catStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("POS products fetch error: " . $e->getMessage());
    $products = [];
    $categories = [];
}

// Get Stripe settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('stripe_publishable_key', 'stripe_secret_key', 'currency', 'tax_rate', 'tax_name')")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripeConfigured = !empty($settings['stripe_publishable_key']) && !empty($settings['stripe_secret_key']);
$currency = $settings['currency'] ?? 'CAD';
$taxRate = floatval($settings['tax_rate'] ?? 13.00);
$taxName = $settings['tax_name'] ?? 'HST';

// Get available terminal readers
$readers = [];
try {
    $readersStmt = $pdo->query("SELECT * FROM pos_terminal_readers WHERE is_active = 1 ORDER BY label ASC");
    $readers = $readersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Table might not exist yet
    $readers = [];
}

// Get users for order assignment
$posUsers = [];
try {
    $usersStmt = $pdo->query("SELECT id, first_name, last_name, email, role FROM users WHERE is_active = 1 ORDER BY first_name, last_name");
    $posUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("POS users fetch error: " . $e->getMessage());
    $posUsers = [];
}
?>

<div class="page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-cash-register"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">POS Terminal</h1>
            <p class="page-description">Point of Sale for merchandise transactions</p>
        </div>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= count($products) ?></span>
            <span class="stat-label">Products</span>
        </div>
        <div class="header-stat">
            <span class="stat-value" style="color: <?= $stripeConfigured ? '#10b981' : '#ef4444' ?>;">
                <i class="fas fa-<?= $stripeConfigured ? 'check-circle' : 'times-circle' ?>"></i>
            </span>
            <span class="stat-label">Stripe</span>
        </div>
    </div>
</div>

<style>
    .pos-container {
        display: grid;
        grid-template-columns: 1fr 400px;
        gap: 25px;
        height: calc(100vh - 200px);
    }
    
    .pos-products {
        background: var(--bg-secondary);
        border-radius: 16px;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .pos-products-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        gap: 15px;
        align-items: center;
    }
    
    .pos-search {
        flex: 1;
        position: relative;
    }
    
    .pos-search input {
        width: 100%;
        padding: 12px 16px 12px 45px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: #fff;
        font-size: 14px;
    }
    
    .pos-search i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--text-dim);
    }
    
    .pos-categories {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .pos-category-btn {
        padding: 8px 16px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 20px;
        color: var(--text-dim);
        font-size: 13px;
        cursor: pointer;
        transition: 0.2s;
    }
    
    .pos-category-btn:hover,
    .pos-category-btn.active {
        background: var(--primary);
        border-color: var(--primary);
        color: #fff;
    }
    
    .pos-products-grid {
        flex: 1;
        overflow-y: auto;
        padding: 20px;
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 15px;
        align-content: start;
    }
    
    .pos-product-card {
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        cursor: pointer;
        transition: 0.2s;
    }
    
    .pos-product-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    
    .pos-product-card.out-of-stock {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    .pos-product-image {
        height: 100px;
        background: linear-gradient(135deg, var(--primary), var(--primary-hover));
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
    }
    
    .pos-product-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    
    .pos-product-image i {
        font-size: 32px;
        color: rgba(255,255,255,0.4);
    }
    
    .pos-product-info {
        padding: 12px;
    }
    
    .pos-product-name {
        font-size: 13px;
        font-weight: 600;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .pos-product-price {
        font-size: 16px;
        font-weight: 700;
        color: var(--primary);
    }
    
    /* Cart Section */
    .pos-cart {
        background: var(--bg-secondary);
        border-radius: 16px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    
    .pos-cart-header {
        padding: 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .pos-cart-header h3 {
        font-size: 18px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .pos-cart-header h3 i {
        color: var(--primary);
    }
    
    .clear-cart-btn {
        padding: 6px 12px;
        background: rgba(239, 68, 68, 0.1);
        border: none;
        border-radius: 6px;
        color: #ef4444;
        font-size: 12px;
        cursor: pointer;
    }
    
    .pos-cart-items {
        flex: 1;
        overflow-y: auto;
        padding: 15px;
    }
    
    .pos-cart-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        background: var(--bg);
        border-radius: 10px;
        margin-bottom: 10px;
    }
    
    .pos-cart-item-info {
        flex: 1;
    }
    
    .pos-cart-item-name {
        font-weight: 600;
        font-size: 14px;
        margin-bottom: 2px;
    }
    
    .pos-cart-item-size {
        font-size: 12px;
        color: var(--text-dim);
    }
    
    .pos-cart-item-price {
        font-weight: 700;
        color: var(--primary);
    }
    
    .pos-cart-item-qty {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .qty-btn {
        width: 28px;
        height: 28px;
        background: var(--bg-secondary);
        border: 1px solid var(--border);
        border-radius: 6px;
        color: #fff;
        cursor: pointer;
        font-size: 14px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .qty-btn:hover {
        background: var(--primary);
    }
    
    .pos-cart-empty {
        text-align: center;
        padding: 40px;
        color: var(--text-dim);
    }
    
    .pos-cart-empty i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.5;
    }
    
    .pos-cart-totals {
        padding: 20px;
        background: var(--bg);
        border-top: 1px solid var(--border);
    }
    
    .pos-total-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 14px;
    }
    
    .pos-total-row.grand-total {
        font-size: 20px;
        font-weight: 700;
        padding-top: 10px;
        border-top: 1px solid var(--border);
        margin-top: 10px;
    }
    
    .pos-total-row.grand-total .total-value {
        color: var(--primary);
    }
    
    .pos-payment-btns {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
        margin-top: 15px;
    }
    
    .pos-pay-btn {
        padding: 16px;
        border: none;
        border-radius: 10px;
        font-size: 14px;
        font-weight: 700;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        transition: 0.2s;
    }
    
    .pos-pay-btn.card {
        background: var(--primary);
        color: #fff;
        grid-column: span 2;
    }
    
    .pos-pay-btn.card:hover {
        background: var(--primary-hover);
    }
    
    .pos-pay-btn.cash {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
    }
    
    .pos-pay-btn.cash:hover {
        background: rgba(16, 185, 129, 0.2);
    }
    
    .pos-pay-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    /* Size Selection Modal */
    .pos-size-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.7);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .pos-size-modal.active {
        display: flex;
    }
    
    .pos-size-content {
        background: var(--bg-secondary);
        border-radius: 16px;
        padding: 25px;
        width: 90%;
        max-width: 400px;
        position: relative;
    }
    
    .pos-modal-close {
        position: absolute;
        top: 12px;
        right: 12px;
        background: none;
        border: none;
        color: var(--text);
        font-size: 24px;
        cursor: pointer;
        width: 32px;
        height: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        transition: 0.2s;
    }
    
    .pos-modal-close:hover {
        background: rgba(255,255,255,0.1);
        color: #fff;
    }
    
    .pos-size-title {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 20px;
    }
    
    .pos-size-options {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .pos-size-btn {
        padding: 15px;
        background: var(--bg);
        border: 2px solid var(--border);
        border-radius: 10px;
        cursor: pointer;
        text-align: center;
        transition: 0.2s;
    }
    
    .pos-size-btn:hover:not(.disabled) {
        border-color: var(--primary);
    }
    
    .pos-size-btn.selected {
        background: var(--primary);
        border-color: var(--primary);
    }
    
    .pos-size-btn.disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
    
    .pos-size-btn .size-name {
        font-weight: 600;
        font-size: 16px;
    }
    
    .pos-size-btn .size-stock {
        font-size: 11px;
        color: var(--text-dim);
    }
    
    .pos-size-actions {
        display: flex;
        gap: 10px;
    }
    
    .pos-size-actions button {
        flex: 1;
        padding: 14px;
        border-radius: 10px;
        font-weight: 600;
        cursor: pointer;
    }
    
    .pos-size-cancel {
        background: var(--bg);
        border: 1px solid var(--border);
        color: #fff;
    }
    
    .pos-size-add {
        background: var(--primary);
        border: none;
        color: #fff;
    }
    
    /* Terminal Reader Section */
    .pos-reader-section {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .pos-reader-select {
        flex: 1;
        padding: 10px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        font-size: 13px;
    }
    
    .reader-status {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #ef4444;
    }
    
    .reader-status.online {
        background: #10b981;
    }
    
    /* Customer Assignment Section */
    .pos-customer-section {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
    }
    
    .pos-customer-select {
        width: 100%;
        padding: 10px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 8px;
        color: #fff;
        font-size: 13px;
    }
    
    .pos-customer-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    
    @media (max-width: 1000px) {
        .pos-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .pos-products {
            min-height: 400px;
        }
    }
</style>

<div class="pos-container">
    <!-- Products Section -->
    <div class="pos-products">
        <div class="pos-products-header">
            <div class="pos-search">
                <i class="fas fa-search"></i>
                <input type="text" id="pos-search" placeholder="Search products..." oninput="filterProducts()">
            </div>
            <div class="pos-categories">
                <button class="pos-category-btn active" onclick="filterByCategory(null, this)">All</button>
                <?php foreach ($categories as $cat): ?>
                    <button class="pos-category-btn" onclick="filterByCategory(<?= $cat['id'] ?>, this)"><?= htmlspecialchars($cat['name']) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="pos-products-grid" id="pos-products">
            <?php foreach ($products as $product): 
                $inStock = ($product['total_quantity'] ?? 0) > 0 || empty($product['sizes']);
            ?>
                <div class="pos-product-card <?= !$inStock ? 'out-of-stock' : '' ?>" 
                     data-id="<?= $product['id'] ?>"
                     data-name="<?= htmlspecialchars($product['name']) ?>"
                     data-price="<?= $product['price'] ?>"
                     data-category="<?= $product['category_id'] ?>"
                     data-sizes='<?= json_encode($product['sizes']) ?>'
                     onclick="<?= $inStock ? 'selectProduct(this)' : '' ?>">
                    <div class="pos-product-image">
                        <?php if ($product['image_url']): ?>
                            <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                        <?php else: ?>
                            <i class="fas fa-tshirt"></i>
                        <?php endif; ?>
                    </div>
                    <div class="pos-product-info">
                        <div class="pos-product-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="pos-product-price">$<?= number_format($product['price'], 2) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Cart Section -->
    <div class="pos-cart">
        <div class="pos-cart-header">
            <h3><i class="fas fa-shopping-cart"></i> Current Sale</h3>
            <button class="clear-cart-btn" onclick="clearCart()">
                <i class="fas fa-trash"></i> Clear
            </button>
        </div>
        
        <?php if (!empty($readers)): ?>
        <div class="pos-reader-section">
            <span class="reader-status" id="reader-status"></span>
            <select class="pos-reader-select" id="terminal-reader" onchange="selectReader(this.value)">
                <option value="">No terminal reader</option>
                <?php foreach ($readers as $reader): ?>
                    <option value="<?= htmlspecialchars($reader['stripe_reader_id']) ?>"><?= htmlspecialchars($reader['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif; ?>
        
        <!-- Customer Assignment Section -->
        <div class="pos-customer-section">
            <label style="font-size: 12px; color: var(--text-dim); margin-bottom: 8px; display: block;">
                <i class="fas fa-user"></i> Assign to Customer (Optional)
            </label>
            <select class="pos-customer-select" id="customer-user-id">
                <option value="">-- Walk-in Customer --</option>
                <?php foreach ($posUsers as $user): ?>
                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['first_name'] . ' ' . $user['last_name']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="pos-cart-items" id="pos-cart-items">
            <div class="pos-cart-empty">
                <i class="fas fa-shopping-basket"></i>
                <p>Cart is empty</p>
                <p style="font-size: 12px;">Select products to add</p>
            </div>
        </div>
        
        <div class="pos-cart-totals">
            <div class="pos-total-row">
                <span>Subtotal</span>
                <span id="cart-subtotal">$0.00</span>
            </div>
            <div class="pos-total-row">
                <span><?= htmlspecialchars($taxName) ?> (<?= $taxRate ?>%)</span>
                <span id="cart-tax">$0.00</span>
            </div>
            <div class="pos-total-row grand-total">
                <span>Total</span>
                <span class="total-value" id="cart-total">$0.00</span>
            </div>
            
            <div class="pos-payment-btns">
                <button class="pos-pay-btn card" id="pay-card-btn" onclick="processCardPayment()" disabled>
                    <i class="fas fa-credit-card"></i> Pay with Card
                </button>
                <button class="pos-pay-btn cash" id="pay-cash-btn" onclick="openCashModal()" disabled>
                    <i class="fas fa-money-bill"></i> Cash
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Size Selection Modal -->
<div class="pos-size-modal" id="size-modal">
    <div class="pos-size-content">
        <button class="modal-close pos-modal-close" onclick="closeSizeModal()" aria-label="Close modal">&times;</button>
        <h3 class="pos-size-title" id="size-modal-title">Select Size</h3>
        <div class="pos-size-options" id="size-options"></div>
        <div class="pos-size-actions">
            <button class="pos-size-cancel" onclick="closeSizeModal()">Cancel</button>
            <button class="pos-size-add" onclick="addToCartWithSize()">Add to Cart</button>
        </div>
    </div>
</div>

<script>
// POS State
let cart = [];
let currentProduct = null;
let selectedSize = '';
const taxRate = <?= $taxRate ?>;
const currency = '<?= $currency ?>';

function filterProducts() {
    const search = document.getElementById('pos-search').value.toLowerCase();
    document.querySelectorAll('.pos-product-card').forEach(card => {
        const name = card.dataset.name.toLowerCase();
        card.style.display = name.includes(search) ? '' : 'none';
    });
}

function filterByCategory(categoryId, btn) {
    document.querySelectorAll('.pos-category-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    
    document.querySelectorAll('.pos-product-card').forEach(card => {
        if (!categoryId || card.dataset.category == categoryId) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
}

function selectProduct(element) {
    currentProduct = {
        id: element.dataset.id,
        name: element.dataset.name,
        price: parseFloat(element.dataset.price),
        sizes: JSON.parse(element.dataset.sizes || '[]')
    };
    
    if (currentProduct.sizes.length > 0) {
        openSizeModal();
    } else {
        addToCart(currentProduct.id, currentProduct.name, currentProduct.price, '');
    }
}

function openSizeModal() {
    document.getElementById('size-modal-title').textContent = currentProduct.name + ' - Select Size';
    const optionsDiv = document.getElementById('size-options');
    optionsDiv.innerHTML = '';
    selectedSize = '';
    
    currentProduct.sizes.forEach(size => {
        const btn = document.createElement('div');
        btn.className = 'pos-size-btn' + (size.quantity <= 0 ? ' disabled' : '');
        btn.innerHTML = `
            <div class="size-name">${size.size}</div>
            <div class="size-stock">${size.quantity > 0 ? size.quantity + ' left' : 'Sold out'}</div>
        `;
        if (size.quantity > 0) {
            btn.onclick = function() {
                document.querySelectorAll('.pos-size-btn').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                selectedSize = size.size;
            };
        }
        optionsDiv.appendChild(btn);
    });
    
    document.getElementById('size-modal').classList.add('active');
}

function closeSizeModal() {
    document.getElementById('size-modal').classList.remove('active');
    currentProduct = null;
    selectedSize = '';
}

function addToCartWithSize() {
    if (!selectedSize) {
        alert('Please select a size');
        return;
    }
    addToCart(currentProduct.id, currentProduct.name, currentProduct.price, selectedSize);
    closeSizeModal();
}

function addToCart(id, name, price, size) {
    const cartKey = id + '_' + size;
    const existing = cart.find(item => item.key === cartKey);
    
    if (existing) {
        existing.quantity++;
    } else {
        cart.push({
            key: cartKey,
            id: id,
            name: name,
            price: price,
            size: size,
            quantity: 1
        });
    }
    
    renderCart();
}

function updateQuantity(key, delta) {
    const item = cart.find(i => i.key === key);
    if (item) {
        item.quantity += delta;
        if (item.quantity <= 0) {
            cart = cart.filter(i => i.key !== key);
        }
    }
    renderCart();
}

function clearCart() {
    if (cart.length > 0 && !confirm('Clear all items from cart?')) return;
    cart = [];
    renderCart();
}

function renderCart() {
    const itemsDiv = document.getElementById('pos-cart-items');
    
    if (cart.length === 0) {
        itemsDiv.innerHTML = `
            <div class="pos-cart-empty">
                <i class="fas fa-shopping-basket"></i>
                <p>Cart is empty</p>
                <p style="font-size: 12px;">Select products to add</p>
            </div>
        `;
        document.getElementById('cart-subtotal').textContent = '$0.00';
        document.getElementById('cart-tax').textContent = '$0.00';
        document.getElementById('cart-total').textContent = '$0.00';
        document.getElementById('pay-card-btn').disabled = true;
        document.getElementById('pay-cash-btn').disabled = true;
        return;
    }
    
    let html = '';
    let subtotal = 0;
    
    cart.forEach(item => {
        const itemTotal = item.price * item.quantity;
        subtotal += itemTotal;
        
        html += `
            <div class="pos-cart-item">
                <div class="pos-cart-item-info">
                    <div class="pos-cart-item-name">${item.name}</div>
                    ${item.size ? `<div class="pos-cart-item-size">Size: ${item.size}</div>` : ''}
                    <div class="pos-cart-item-price">$${itemTotal.toFixed(2)}</div>
                </div>
                <div class="pos-cart-item-qty">
                    <button class="qty-btn" onclick="updateQuantity('${item.key}', -1)">-</button>
                    <span>${item.quantity}</span>
                    <button class="qty-btn" onclick="updateQuantity('${item.key}', 1)">+</button>
                </div>
            </div>
        `;
    });
    
    itemsDiv.innerHTML = html;
    
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    document.getElementById('cart-subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('cart-tax').textContent = '$' + tax.toFixed(2);
    document.getElementById('cart-total').textContent = '$' + total.toFixed(2);
    
    document.getElementById('pay-card-btn').disabled = false;
    document.getElementById('pay-cash-btn').disabled = false;
}

function processCardPayment() {
    if (cart.length === 0) return;
    
    const btn = document.getElementById('pay-card-btn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    const terminalReader = document.getElementById('terminal-reader')?.value || '';
    const customerUserId = document.getElementById('customer-user-id')?.value || '';
    
    fetch('process_pos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'process_card_payment',
            items: cart,
            terminal_reader: terminalReader,
            customer_user_id: customerUserId,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment successful! Transaction #' + data.transaction_number);
            cart = [];
            renderCart();
            document.getElementById('customer-user-id').value = '';
        } else {
            alert('Payment failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    })
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-credit-card"></i> Pay with Card';
    });
}

function openCashModal() {
    // For now, process cash payment directly
    if (cart.length === 0) return;
    
    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    const received = prompt('Total: $' + total.toFixed(2) + '\n\nEnter amount received:');
    if (received === null) return;
    
    const receivedAmount = parseFloat(received);
    if (isNaN(receivedAmount) || receivedAmount < total) {
        alert('Insufficient amount');
        return;
    }
    
    const change = receivedAmount - total;
    const customerUserId = document.getElementById('customer-user-id')?.value || '';
    
    fetch('process_pos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'process_cash_payment',
            items: cart,
            cash_received: receivedAmount,
            customer_user_id: customerUserId,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payment successful!\nTransaction #' + data.transaction_number + '\nChange: $' + change.toFixed(2));
            cart = [];
            renderCart();
            document.getElementById('customer-user-id').value = '';
        } else {
            alert('Payment failed: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function selectReader(readerId) {
    const statusDot = document.getElementById('reader-status');
    if (!readerId) {
        statusDot.classList.remove('online');
        return;
    }
    
    // Check reader status
    fetch('process_pos.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'check_reader_status',
            reader_id: readerId,
            csrf_token: '<?= $_SESSION['csrf_token'] ?? '' ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.online) {
            statusDot.classList.add('online');
        } else {
            statusDot.classList.remove('online');
        }
    });
}
</script>
