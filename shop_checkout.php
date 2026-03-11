<?php
/**
 * Shop Checkout Page
 * Guest checkout with Stripe integration
 */
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/site_branding.php';
require_once __DIR__ . '/lib/image_helper.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

session_start();

// Check if cart is empty
if (empty($_SESSION['shop_cart'])) {
    header('Location: shop.php');
    exit();
}

// Generate CSRF token if needed
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Get user info if logged in
$isLoggedIn = isset($_SESSION['user_id']);
$userInfo = null;
if ($isLoggedIn) {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email, phone FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    $userInfo = decryptUserRow($userInfo);
}

// Get Google Maps API key for address autocomplete
$google_maps_api_key = '';
try {
    $api_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
    $google_maps_api_key = $api_key_stmt->fetchColumn() ?: '';
    if (function_exists('decryptCredential') && !empty($google_maps_api_key)) { $google_maps_api_key = decryptCredential($google_maps_api_key); }
} catch (Exception $e) {
    // Silently continue without autocomplete
}

// Calculate cart totals
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
    $currency = $settings['currency'] ?? 'CAD';
    
    $taxAmount = round($subtotal * ($taxRate / 100), 2);
    $total = $subtotal + $taxAmount;
    
    return [
        'items' => $cart,
        'item_count' => $itemCount,
        'subtotal' => $subtotal,
        'tax_rate' => $taxRate,
        'tax_name' => $taxName,
        'tax_amount' => $taxAmount,
        'total' => $total,
        'currency' => $currency
    ];
}

$cartData = calculateCartTotals($pdo);
$cartCount = $cartData['item_count'];

// Get Stripe settings
$stripeSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('stripe_publishable_key', 'stripe_secret_key')")->fetchAll(PDO::FETCH_KEY_PAIR);
if (function_exists('decryptCredential')) {
    if (!empty($stripeSettings['stripe_secret_key'])) $stripeSettings['stripe_secret_key'] = decryptCredential($stripeSettings['stripe_secret_key']);
    if (!empty($stripeSettings['stripe_publishable_key'])) $stripeSettings['stripe_publishable_key'] = decryptCredential($stripeSettings['stripe_publishable_key']);
}
$stripeConfigured = !empty($stripeSettings['stripe_publishable_key']) && !empty($stripeSettings['stripe_secret_key']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout | Arctic Wolves Shop</title>
    
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .checkout-container {
            padding: 40px 0;
            min-height: 60vh;
        }
        
        .checkout-title {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 40px;
        }
        
        .checkout-layout {
            display: grid;
            grid-template-columns: 1fr 420px;
            gap: 40px;
        }
        
        .checkout-form-section {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 30px;
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title i {
            color: var(--primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .form-group label span.required {
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            background: var(--bg-main);
            border: 1px solid var(--border);
            border-radius: 10px;
            color: #fff;
            font-size: 15px;
            transition: 0.2s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.2);
        }
        
        .form-group input::placeholder {
            color: var(--text-dim);
        }
        
        .form-group.error input {
            border-color: #ef4444;
        }
        
        .form-group .error-message {
            color: #ef4444;
            font-size: 12px;
            margin-top: 5px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: var(--primary);
        }
        
        .checkbox-group label {
            font-size: 14px;
            color: var(--text-dim);
            cursor: pointer;
        }
        
        .order-summary {
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
        
        .order-items {
            margin-bottom: 25px;
        }
        
        .order-item {
            display: flex;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .order-item-image {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            overflow: hidden;
            flex-shrink: 0;
        }
        
        .order-item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .order-item-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .order-item-placeholder i {
            font-size: 20px;
            color: rgba(255,255,255,0.4);
        }
        
        .order-item-details {
            flex: 1;
        }
        
        .order-item-name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .order-item-meta {
            color: var(--text-dim);
            font-size: 12px;
        }
        
        .order-item-price {
            font-weight: 700;
            color: var(--primary);
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
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
        
        .place-order-btn {
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
        
        .place-order-btn:hover:not(:disabled) {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(107, 70, 193, 0.3);
        }
        
        .place-order-btn:disabled {
            background: var(--border);
            cursor: not-allowed;
        }
        
        .secure-notice {
            text-align: center;
            margin-top: 15px;
            font-size: 12px;
            color: var(--text-dim);
        }
        
        .secure-notice i {
            color: #10b981;
        }
        
        .back-to-cart {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--text-dim);
            text-decoration: none;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .back-to-cart:hover {
            color: var(--primary);
        }
        
        .login-prompt {
            background: rgba(107, 70, 193, 0.1);
            border: 1px solid var(--primary);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .login-prompt p {
            color: var(--text-dim);
            margin: 0;
            font-size: 14px;
        }
        
        .login-prompt a {
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        .login-prompt a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 1000px) {
            .checkout-layout {
                grid-template-columns: 1fr;
            }
            
            .order-summary {
                position: static;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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

    <div class="container checkout-container">
        <a href="shop_cart.php" class="back-to-cart">
            <i class="fas fa-arrow-left"></i> Back to Cart
        </a>
        
        <h1 class="checkout-title">Checkout</h1>
        
        <?php if (!$isLoggedIn): ?>
            <div class="login-prompt">
                <p><i class="fas fa-user"></i> Already have an account?</p>
                <a href="login.php?redirect=shop_checkout.php">Log in for faster checkout</a>
            </div>
        <?php endif; ?>
        
        <form id="checkout-form" action="process_shop_checkout.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="checkout-layout">
                <div class="checkout-form">
                    <!-- Contact Information -->
                    <div class="checkout-form-section">
                        <h2 class="section-title"><i class="fas fa-user"></i> Contact Information</h2>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>First Name <span class="required">*</span></label>
                                <input type="text" name="first_name" value="<?= htmlspecialchars($userInfo['first_name'] ?? '') ?>" required placeholder="John">
                            </div>
                            <div class="form-group">
                                <label>Last Name <span class="required">*</span></label>
                                <input type="text" name="last_name" value="<?= htmlspecialchars($userInfo['last_name'] ?? '') ?>" required placeholder="Doe">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" value="<?= htmlspecialchars($userInfo['email'] ?? '') ?>" required placeholder="john@example.com">
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?= htmlspecialchars($userInfo['phone'] ?? '') ?>" placeholder="(555) 123-4567">
                        </div>
                    </div>
                    
                    <!-- Billing Address -->
                    <div class="checkout-form-section">
                        <h2 class="section-title"><i class="fas fa-file-invoice"></i> Billing Address</h2>
                        
                        <div class="form-group">
                            <label>Address Line 1 <span class="required">*</span></label>
                            <input type="text" name="billing_address1" required placeholder="123 Main Street">
                        </div>
                        
                        <div class="form-group">
                            <label>Address Line 2</label>
                            <input type="text" name="billing_address2" placeholder="Apartment, suite, unit, etc.">
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>City <span class="required">*</span></label>
                                <input type="text" name="billing_city" required placeholder="Toronto">
                            </div>
                            <div class="form-group">
                                <label>Province/State <span class="required">*</span></label>
                                <select name="billing_state" required>
                                    <option value="">Select Province</option>
                                    <option value="AB">Alberta</option>
                                    <option value="BC">British Columbia</option>
                                    <option value="MB">Manitoba</option>
                                    <option value="NB">New Brunswick</option>
                                    <option value="NL">Newfoundland and Labrador</option>
                                    <option value="NS">Nova Scotia</option>
                                    <option value="NT">Northwest Territories</option>
                                    <option value="NU">Nunavut</option>
                                    <option value="ON" selected>Ontario</option>
                                    <option value="PE">Prince Edward Island</option>
                                    <option value="QC">Quebec</option>
                                    <option value="SK">Saskatchewan</option>
                                    <option value="YT">Yukon</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Postal Code <span class="required">*</span></label>
                                <input type="text" name="billing_postal" required placeholder="M5V 1A1" pattern="[A-Za-z][0-9][A-Za-z] ?[0-9][A-Za-z][0-9]" title="Please enter a valid Canadian postal code">
                            </div>
                            <div class="form-group">
                                <label>Country</label>
                                <select name="billing_country">
                                    <option value="CA" selected>Canada</option>
                                    <option value="US">United States</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Shipping Address -->
                    <div class="checkout-form-section">
                        <h2 class="section-title"><i class="fas fa-truck"></i> Shipping Address</h2>
                        
                        <div class="checkbox-group">
                            <input type="checkbox" id="same-as-billing" name="shipping_same" value="1" checked onchange="toggleShippingAddress()">
                            <label for="same-as-billing">Same as billing address</label>
                        </div>
                        
                        <div id="shipping-fields" style="display: none;">
                            <div class="form-group">
                                <label>Address Line 1 <span class="required">*</span></label>
                                <input type="text" name="shipping_address1" placeholder="123 Main Street">
                            </div>
                            
                            <div class="form-group">
                                <label>Address Line 2</label>
                                <input type="text" name="shipping_address2" placeholder="Apartment, suite, unit, etc.">
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>City <span class="required">*</span></label>
                                    <input type="text" name="shipping_city" placeholder="Toronto">
                                </div>
                                <div class="form-group">
                                    <label>Province/State <span class="required">*</span></label>
                                    <select name="shipping_state">
                                        <option value="">Select Province</option>
                                        <option value="AB">Alberta</option>
                                        <option value="BC">British Columbia</option>
                                        <option value="MB">Manitoba</option>
                                        <option value="NB">New Brunswick</option>
                                        <option value="NL">Newfoundland and Labrador</option>
                                        <option value="NS">Nova Scotia</option>
                                        <option value="NT">Northwest Territories</option>
                                        <option value="NU">Nunavut</option>
                                        <option value="ON">Ontario</option>
                                        <option value="PE">Prince Edward Island</option>
                                        <option value="QC">Quebec</option>
                                        <option value="SK">Saskatchewan</option>
                                        <option value="YT">Yukon</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Postal Code <span class="required">*</span></label>
                                    <input type="text" name="shipping_postal" placeholder="M5V 1A1">
                                </div>
                                <div class="form-group">
                                    <label>Country</label>
                                    <select name="shipping_country">
                                        <option value="CA" selected>Canada</option>
                                        <option value="US">United States</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="order-summary">
                    <h3 class="summary-title">Order Summary</h3>
                    
                    <div class="order-items">
                        <?php foreach ($_SESSION['shop_cart'] as $item): ?>
                            <div class="order-item">
                                <div class="order-item-image">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $item['image_url'])) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                                    <?php else: ?>
                                        <div class="order-item-placeholder">
                                            <i class="fas fa-tshirt"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="order-item-details">
                                    <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="order-item-meta">
                                        <?php if (!empty($item['size'])): ?>Size: <?= htmlspecialchars($item['size']) ?> • <?php endif; ?>
                                        Qty: <?= $item['quantity'] ?>
                                    </div>
                                </div>
                                <div class="order-item-price">$<?= number_format($item['price'] * $item['quantity'], 2) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">$<?= number_format($cartData['subtotal'], 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label"><?= htmlspecialchars($cartData['tax_name']) ?> (<?= $cartData['tax_rate'] ?>%)</span>
                        <span class="summary-value">$<?= number_format($cartData['tax_amount'], 2) ?></span>
                    </div>
                    <div class="summary-row total">
                        <span class="summary-label">Total</span>
                        <span class="summary-value">$<?= number_format($cartData['total'], 2) ?> <?= $cartData['currency'] ?></span>
                    </div>
                    
                    <?php if ($stripeConfigured): ?>
                        <button type="submit" class="place-order-btn" id="place-order-btn">
                            <i class="fas fa-lock"></i> Place Order
                        </button>
                    <?php else: ?>
                        <button type="button" class="place-order-btn" disabled>
                            <i class="fas fa-exclamation-triangle"></i> Payment Not Configured
                        </button>
                    <?php endif; ?>
                    
                    <p class="secure-notice">
                        <i class="fas fa-shield-alt"></i> Secure checkout powered by Stripe
                    </p>
                </div>
            </div>
        </form>
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
        function toggleShippingAddress() {
            const checkbox = document.getElementById('same-as-billing');
            const shippingFields = document.getElementById('shipping-fields');
            
            if (checkbox.checked) {
                shippingFields.style.display = 'none';
                // Remove required from shipping fields
                shippingFields.querySelectorAll('input, select').forEach(input => {
                    input.removeAttribute('required');
                });
            } else {
                shippingFields.style.display = 'block';
                // Add required to shipping fields
                shippingFields.querySelector('[name="shipping_address1"]').setAttribute('required', '');
                shippingFields.querySelector('[name="shipping_city"]').setAttribute('required', '');
                shippingFields.querySelector('[name="shipping_state"]').setAttribute('required', '');
                shippingFields.querySelector('[name="shipping_postal"]').setAttribute('required', '');
            }
        }
        
        document.getElementById('checkout-form').addEventListener('submit', function(e) {
            const btn = document.getElementById('place-order-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    </script>
    <?php if (!empty($google_maps_api_key)): ?>
    <script>(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.googleapis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
      key: "<?= htmlspecialchars($google_maps_api_key) ?>",
      v: "weekly"
    });</script>
    <script>
    (async function() {
        try {
            var { PlaceAutocompleteElement } = await google.maps.importLibrary('places');

            var addressFields = [
                { input: 'billing_address1', city: 'billing_city', province: 'billing_state', postal: 'billing_postal' },
                { input: 'shipping_address1', city: 'shipping_city', province: 'shipping_state', postal: 'shipping_postal' }
            ];

            addressFields.forEach(function(field) {
                var input = document.querySelector('input[name="' + field.input + '"]');
                if (input && !input.dataset.autocompleteInit) {
                    var autocompleteEl = new PlaceAutocompleteElement();
                    autocompleteEl.style.cssText = 'width: 100%;';
                    autocompleteEl.setAttribute('placeholder', input.placeholder || 'Enter address');
                    autocompleteEl.className = input.className;
                    autocompleteEl.setAttribute('name', input.name);
                    if (input.value) autocompleteEl.value = input.value;

                    autocompleteEl.addEventListener('gmp-placeselect', async function(event) {
                        var place = event.place;
                        try {
                            await place.fetchFields({ fields: ['formattedAddress', 'addressComponents'] });
                            if (place.addressComponents) {
                                var city = '', province = '', postal = '';
                                place.addressComponents.forEach(function(c) {
                                    if (c.types.includes('locality')) city = c.longText;
                                    if (c.types.includes('administrative_area_level_1')) province = c.shortText;
                                    if (c.types.includes('postal_code')) postal = c.longText;
                                });
                                var cityInput = document.querySelector('input[name="' + field.city + '"]');
                                var postalInput = document.querySelector('input[name="' + field.postal + '"]');
                                var provinceSelect = document.querySelector('select[name="' + field.province + '"]');
                                if (cityInput && city) cityInput.value = city;
                                if (postalInput && postal) postalInput.value = postal;
                                if (provinceSelect && province) {
                                    for (var i = 0; i < provinceSelect.options.length; i++) {
                                        if (provinceSelect.options[i].value === province) {
                                            provinceSelect.selectedIndex = i;
                                            break;
                                        }
                                    }
                                }
                            }
                        } catch (err) {
                            console.error('Failed to fetch place details:', err);
                        }
                    });

                    input.parentNode.replaceChild(autocompleteEl, input);
                    autocompleteEl.dataset.autocompleteInit = 'true';
                }
            });
        } catch (e) {
            console.error('Failed to initialize Google Maps Places:', e);
        }
    })();
    </script>
    <?php endif; ?>
</body>
</html>
