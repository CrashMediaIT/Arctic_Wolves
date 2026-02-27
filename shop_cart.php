<?php
/**
 * Shopping Cart Page
 * View and manage cart items before checkout
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/site_branding.php';
require_once __DIR__ . '/lib/image_helper.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

session_start();

// Initialize cart if not exists
if (!isset($_SESSION['shop_cart'])) {
    $_SESSION['shop_cart'] = [];
}

// Handle cart actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_quantity':
            $cartKey = $_POST['cart_key'] ?? '';
            $quantity = intval($_POST['quantity'] ?? 0);
            
            if (isset($_SESSION['shop_cart'][$cartKey])) {
                if ($quantity <= 0) {
                    unset($_SESSION['shop_cart'][$cartKey]);
                } else {
                    // Check stock
                    $productId = $_SESSION['shop_cart'][$cartKey]['product_id'];
                    $size = $_SESSION['shop_cart'][$cartKey]['size'];
                    
                    if ($size) {
                        $stmt = $pdo->prepare("SELECT quantity FROM merchandise_product_sizes WHERE product_id = ? AND size = ?");
                        $stmt->execute([$productId, $size]);
                        $stock = $stmt->fetchColumn();
                        
                        if ($stock !== false && $quantity > $stock) {
                            echo json_encode(['success' => false, 'message' => 'Only ' . $stock . ' items available']);
                            exit();
                        }
                    }
                    
                    $_SESSION['shop_cart'][$cartKey]['quantity'] = $quantity;
                }
            }
            
            echo json_encode(['success' => true, 'cart' => calculateCartTotals($pdo)]);
            exit();
            
        case 'remove_item':
            $cartKey = $_POST['cart_key'] ?? '';
            if (isset($_SESSION['shop_cart'][$cartKey])) {
                unset($_SESSION['shop_cart'][$cartKey]);
            }
            echo json_encode(['success' => true, 'cart' => calculateCartTotals($pdo)]);
            exit();
            
        case 'clear_cart':
            $_SESSION['shop_cart'] = [];
            echo json_encode(['success' => true]);
            exit();
    }
}

function calculateCartTotals($pdo) {
    $cart = $_SESSION['shop_cart'];
    $subtotal = 0;
    $itemCount = 0;
    
    foreach ($cart as $item) {
        $subtotal += $item['price'] * $item['quantity'];
        $itemCount += $item['quantity'];
    }
    
    // Get tax settings
    $settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('tax_rate', 'tax_name', 'currency')")->fetchAll(PDO::FETCH_KEY_PAIR);
    $taxRate = floatval($settings['tax_rate'] ?? 13.00);
    $taxName = $settings['tax_name'] ?? 'HST';
    
    $taxAmount = round($subtotal * ($taxRate / 100), 2);
    $total = $subtotal + $taxAmount;
    
    return [
        'items' => $cart,
        'item_count' => $itemCount,
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_name' => $taxName,
        'tax_amount' => $taxAmount,
        'total' => $total
    ];
}

$cartData = calculateCartTotals($pdo);
$cartCount = $cartData['item_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart | Arctic Wolves</title>
    
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .cart-container {
            padding: 40px 0;
            min-height: 60vh;
        }
        
        .cart-title {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 40px;
        }
        
        .cart-layout {
            display: grid;
            grid-template-columns: 1fr 380px;
            gap: 40px;
        }
        
        .cart-items {
            background: var(--bg-card);
            border-radius: 16px;
            overflow: hidden;
        }
        
        .cart-item {
            display: flex;
            gap: 20px;
            padding: 25px;
            border-bottom: 1px solid var(--border);
        }
        
        .cart-item:last-child {
            border-bottom: none;
        }
        
        .cart-item-image {
            width: 120px;
            height: 120px;
            border-radius: 12px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .cart-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .cart-item-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .cart-item-placeholder i {
            font-size: 40px;
            color: rgba(255,255,255,0.4);
        }
        
        .cart-item-details {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .cart-item-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
            color: #fff;
            text-decoration: none;
        }
        
        .cart-item-name:hover {
            color: var(--primary);
        }
        
        .cart-item-size {
            color: var(--text-dim);
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .cart-item-price {
            font-size: 20px;
            font-weight: 700;
            color: var(--primary);
            margin-top: auto;
        }
        
        .cart-item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 15px;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 0;
        }
        
        .qty-btn {
            width: 36px;
            height: 36px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            color: #fff;
            cursor: pointer;
            transition: 0.2s;
        }
        
        .qty-btn:first-child {
            border-radius: 8px 0 0 8px;
        }
        
        .qty-btn:last-child {
            border-radius: 0 8px 8px 0;
        }
        
        .qty-btn:hover {
            background: var(--primary);
        }
        
        .qty-input {
            width: 50px;
            height: 36px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-left: none;
            border-right: none;
            color: #fff;
            text-align: center;
            font-weight: 600;
        }
        
        .remove-btn {
            color: var(--text-dim);
            background: none;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: 0.2s;
        }
        
        .remove-btn:hover {
            color: #ef4444;
        }
        
        .cart-summary {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            height: fit-content;
            position: sticky;
            top: 100px;
        }
        
        .summary-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 15px;
        }
        
        .summary-row.total {
            font-size: 20px;
            font-weight: 700;
            padding-top: 15px;
            border-top: 1px solid var(--border);
            margin-top: 15px;
        }
        
        .summary-row.total .summary-value {
            color: var(--primary);
        }
        
        .summary-label {
            color: var(--text-dim);
        }
        
        .checkout-btn {
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
            gap: 10px;
            margin-top: 25px;
        }
        
        .checkout-btn:hover {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.3);
        }
        
        .continue-shopping {
            display: block;
            text-align: center;
            color: var(--text-dim);
            text-decoration: none;
            margin-top: 15px;
            font-size: 14px;
        }
        
        .continue-shopping:hover {
            color: var(--primary);
        }
        
        .empty-cart {
            text-align: center;
            padding: 80px 20px;
        }
        
        .empty-cart i {
            font-size: 80px;
            color: var(--text-dim);
            margin-bottom: 30px;
        }
        
        .empty-cart h2 {
            font-size: 28px;
            margin-bottom: 15px;
        }
        
        .empty-cart p {
            color: var(--text-dim);
            margin-bottom: 30px;
        }
        
        .empty-cart .btn-primary {
            display: inline-block;
            padding: 16px 35px;
            text-decoration: none;
        }
        
        @media (max-width: 900px) {
            .cart-layout {
                grid-template-columns: 1fr;
            }
            
            .cart-summary {
                position: static;
            }
            
            .cart-item {
                flex-direction: column;
            }
            
            .cart-item-image {
                width: 100%;
                height: 200px;
            }
            
            .cart-item-actions {
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
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
                <a href="shop.php">Shop</a>
                <a href="shop_cart.php" style="position: relative; color: var(--primary);">
                    <i class="fas fa-shopping-cart"></i>
                    <span id="nav-cart-count" style="position: absolute; top: -8px; right: -8px; background: var(--primary); width: 18px; height: 18px; border-radius: 50%; font-size: 10px; display: <?= $cartCount > 0 ? 'flex' : 'none' ?>; align-items: center; justify-content: center;"><?= $cartCount ?></span>
                </a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <div class="container cart-container">
        <h1 class="cart-title">Shopping Cart</h1>
        
        <?php if (empty($_SESSION['shop_cart'])): ?>
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h2>Your cart is empty</h2>
                <p>Looks like you haven't added any items to your cart yet.</p>
                <a href="shop.php" class="btn-primary">Continue Shopping</a>
            </div>
        <?php else: ?>
            <div class="cart-layout">
                <div class="cart-items" id="cart-items">
                    <?php foreach ($_SESSION['shop_cart'] as $cartKey => $item): ?>
                        <div class="cart-item" data-key="<?= htmlspecialchars($cartKey) ?>">
                            <div class="cart-item-image">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $item['image_url'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                <?php else: ?>
                                    <div class="cart-item-placeholder">
                                        <i class="fas fa-tshirt"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="cart-item-details">
                                <a href="shop_product.php?id=<?= $item['product_id'] ?>" class="cart-item-name"><?= htmlspecialchars($item['name']) ?></a>
                                <?php if (!empty($item['size'])): ?>
                                    <div class="cart-item-size">Size: <?= htmlspecialchars($item['size']) ?></div>
                                <?php endif; ?>
                                <div class="cart-item-price">$<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                            </div>
                            <div class="cart-item-actions">
                                <div class="quantity-control">
                                    <button type="button" class="qty-btn" onclick="updateQuantity('<?= htmlspecialchars($cartKey) ?>', -1)">-</button>
                                    <input type="number" class="qty-input" value="<?= $item['quantity'] ?>" readonly>
                                    <button type="button" class="qty-btn" onclick="updateQuantity('<?= htmlspecialchars($cartKey) ?>', 1)">+</button>
                                </div>
                                <button type="button" class="remove-btn" onclick="removeItem('<?= htmlspecialchars($cartKey) ?>')">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="cart-summary" id="cart-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    <div class="summary-row">
                        <span class="summary-label">Subtotal (<span id="item-count"><?= $cartData['item_count'] ?></span> items)</span>
                        <span class="summary-value" id="subtotal">$<?= number_format($cartData['subtotal'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label"><?= htmlspecialchars($cartData['tax_name']) ?> (<?= $cartData['tax_rate'] ?>%)</span>
                        <span class="summary-value" id="tax-amount">$<?= number_format($cartData['tax_amount'], 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span class="summary-label">Total</span>
                        <span class="summary-value" id="total">$<?= number_format($cartData['total'], 2) ?></span>
                    </div>
                    <a href="shop_checkout.php" class="checkout-btn">
                        <i class="fas fa-lock"></i> Proceed to Checkout
                    </a>
                    <a href="shop.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

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

    <script>
        function updateQuantity(cartKey, delta) {
            const item = document.querySelector(`.cart-item[data-key="${cartKey}"]`);
            const input = item.querySelector('.qty-input');
            let newQty = parseInt(input.value) + delta;
            
            if (newQty < 1) {
                removeItem(cartKey);
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'update_quantity');
            formData.append('cart_key', cartKey);
            formData.append('quantity', newQty);
            
            fetch('shop_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = newQty;
                    updateSummary(data.cart);
                    
                    // Update item price
                    const itemData = data.cart.items[cartKey];
                    if (itemData) {
                        item.querySelector('.cart-item-price').textContent = '$' + (itemData.price * itemData.quantity).toFixed(2);
                    }
                } else {
                    showToast(data.message || 'Failed to update quantity', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        async function removeItem(cartKey) {
            if (!await showConfirmModal('Remove this item from cart?')) return;
            
            const formData = new FormData();
            formData.append('action', 'remove_item');
            formData.append('cart_key', cartKey);
            
            fetch('shop_cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = document.querySelector(`.cart-item[data-key="${cartKey}"]`);
                    item.remove();
                    updateSummary(data.cart);
                    
                    // Check if cart is empty
                    if (data.cart.item_count === 0) {
                        location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }
        
        function updateSummary(cart) {
            document.getElementById('item-count').textContent = cart.item_count;
            document.getElementById('subtotal').textContent = '$' + cart.subtotal.toFixed(2);
            document.getElementById('tax-amount').textContent = '$' + cart.tax_amount.toFixed(2);
            document.getElementById('total').textContent = '$' + cart.total.toFixed(2);
            
            // Update nav cart count
            const navCount = document.getElementById('nav-cart-count');
            navCount.textContent = cart.item_count;
            navCount.style.display = cart.item_count > 0 ? 'flex' : 'none';
        }
    </script>
</body>
</html>
