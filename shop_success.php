<?php
/**
 * Shop Order Success Page
 * Handles successful payment callback from Stripe
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Load Stripe library
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} elseif (file_exists('stripe-php/init.php')) {
    require 'stripe-php/init.php';
}

// Get settings
$settings = $pdo->query("SELECT setting_key, setting_value FROM system_settings")->fetchAll(PDO::FETCH_KEY_PAIR);
$stripeSecret = $settings['stripe_secret_key'] ?? '';
if (function_exists('decryptCredential') && !empty($stripeSecret)) { $stripeSecret = decryptCredential($stripeSecret); }

if (!empty($stripeSecret)) {
    \Stripe\Stripe::setApiKey($stripeSecret);
}

$stripeSessionId = $_GET['session_id'] ?? '';
$order = null;
$orderItems = [];

if (!$stripeSessionId) {
    header("Location: shop.php");
    exit();
}

try {
    // Verify payment with Stripe
    if (!empty($stripeSecret)) {
        $checkout = \Stripe\Checkout\Session::retrieve($stripeSessionId);
        
        if ($checkout->payment_status === 'paid') {
            // Find order by Stripe session ID
            $stmt = $pdo->prepare("SELECT * FROM shop_orders WHERE stripe_session_id = ?");
            $stmt->execute([$stripeSessionId]);
            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            $order = decryptUserRow($order);
            
            if ($order && $order['payment_status'] === 'pending') {
                // Update order status
                $updateStmt = $pdo->prepare("
                    UPDATE shop_orders 
                    SET status = 'paid', 
                        payment_status = 'paid',
                        stripe_payment_intent = ?
                    WHERE id = ?
                ");
                $updateStmt->execute([$checkout->payment_intent, $order['id']]);
                
                // Update inventory
                $itemsStmt = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
                $itemsStmt->execute([$order['id']]);
                $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($orderItems as $item) {
                    if (!empty($item['size'])) {
                        // Decrease size-specific inventory
                        $invStmt = $pdo->prepare("
                            UPDATE merchandise_product_sizes 
                            SET quantity = quantity - ? 
                            WHERE product_id = ? AND size = ? AND quantity >= ?
                        ");
                        $invStmt->execute([$item['quantity'], $item['product_id'], $item['size'], $item['quantity']]);
                    }
                }
                
                // Clear cart
                $_SESSION['shop_cart'] = [];
                unset($_SESSION['pending_shop_order_id']);
                
                // Refresh order data
                $stmt->execute([$stripeSessionId]);
                $order = $stmt->fetch(PDO::FETCH_ASSOC);
                $order = decryptUserRow($order);
            } elseif ($order) {
                // Order already processed, just fetch items
                $itemsStmt = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
                $itemsStmt->execute([$order['id']]);
                $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Clear cart anyway
                $_SESSION['shop_cart'] = [];
            }
        }
    }
} catch (Exception $e) {
    error_log("Shop success page error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmed | Arctic Wolves Shop</title>
    
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="style.css">
    <style>
        .success-container {
            padding: 60px 0;
            text-align: center;
        }
        
        .success-icon {
            width: 120px;
            height: 120px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
        }
        
        .success-icon i {
            font-size: 60px;
            color: #10b981;
        }
        
        .success-title {
            font-size: 36px;
            font-weight: 900;
            margin-bottom: 15px;
        }
        
        .success-subtitle {
            color: var(--text-dim);
            font-size: 18px;
            margin-bottom: 40px;
        }
        
        .order-details {
            background: var(--bg-card);
            border-radius: 16px;
            padding: 40px;
            max-width: 600px;
            margin: 0 auto 30px;
            text-align: left;
        }
        
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border);
        }
        
        .order-number {
            font-size: 14px;
            color: var(--text-dim);
        }
        
        .order-number span {
            color: #fff;
            font-weight: 700;
        }
        
        .order-status {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .order-items-list {
            margin-bottom: 25px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
        }
        
        .order-item:last-child {
            border-bottom: none;
        }
        
        .item-name {
            font-weight: 600;
        }
        
        .item-meta {
            font-size: 13px;
            color: var(--text-dim);
        }
        
        .item-price {
            font-weight: 600;
            color: var(--primary);
        }
        
        .order-totals {
            background: rgba(107, 70, 193, 0.05);
            border-radius: 12px;
            padding: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 15px;
        }
        
        .total-row:last-child {
            margin-bottom: 0;
            padding-top: 10px;
            border-top: 1px solid var(--border);
            font-size: 20px;
            font-weight: 700;
        }
        
        .total-row:last-child .total-value {
            color: var(--primary);
        }
        
        .total-label {
            color: var(--text-dim);
        }
        
        .confirmation-email {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .confirmation-email i {
            color: #3b82f6;
            margin-right: 10px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 40px;
        }
        
        .action-btn {
            padding: 16px 35px;
            border-radius: 12px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.3s;
        }
        
        .action-btn.primary {
            background: var(--primary);
            color: #fff;
        }
        
        .action-btn.primary:hover {
            background: var(--accent);
            transform: translateY(-2px);
        }
        
        .action-btn.secondary {
            background: transparent;
            border: 2px solid var(--border);
            color: #fff;
        }
        
        .action-btn.secondary:hover {
            border-color: var(--primary);
            color: var(--primary);
        }
        
        @media (max-width: 600px) {
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
                justify-content: center;
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
                </a>
                <a href="login.php" class="nav-btn">Athlete Login</a>
            </div>
        </nav>
    </header>

    <div class="container success-container">
        <?php if ($order): ?>
            <div class="success-icon">
                <i class="fas fa-check"></i>
            </div>
            
            <h1 class="success-title">Order Confirmed!</h1>
            <p class="success-subtitle">Thank you for your purchase. Your order has been received.</p>
            
            <div class="order-details">
                <div class="order-header">
                    <div class="order-number">
                        Order Number: <span><?= htmlspecialchars($order['order_number']) ?></span>
                    </div>
                    <div class="order-status">
                        <i class="fas fa-check-circle"></i> Paid
                    </div>
                </div>
                
                <div class="order-items-list">
                    <?php foreach ($orderItems as $item): ?>
                        <div class="order-item">
                            <div>
                                <div class="item-name"><?= htmlspecialchars($item['product_name']) ?></div>
                                <div class="item-meta">
                                    <?php if (!empty($item['size'])): ?>Size: <?= htmlspecialchars($item['size']) ?> • <?php endif; ?>
                                    Qty: <?= $item['quantity'] ?>
                                </div>
                            </div>
                            <div class="item-price">$<?= number_format($item['total_price'], 2) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="order-totals">
                    <div class="total-row">
                        <span class="total-label">Subtotal</span>
                        <span class="total-value">$<?= number_format($order['subtotal'], 2) ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Tax</span>
                        <span class="total-value">$<?= number_format($order['tax_amount'], 2) ?></span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Total</span>
                        <span class="total-value">$<?= number_format($order['total'], 2) ?></span>
                    </div>
                </div>
                
                <div class="confirmation-email">
                    <i class="fas fa-envelope"></i>
                    A confirmation email has been sent to <strong><?= htmlspecialchars($order['customer_email']) ?></strong>
                </div>
            </div>
        <?php else: ?>
            <div class="success-icon" style="background: rgba(239, 68, 68, 0.1);">
                <i class="fas fa-exclamation-triangle" style="color: #ef4444;"></i>
            </div>
            
            <h1 class="success-title">Order Not Found</h1>
            <p class="success-subtitle">We couldn't find your order. If you completed a payment, please contact support.</p>
        <?php endif; ?>
        
        <div class="action-buttons">
            <a href="shop.php" class="action-btn primary">
                <i class="fas fa-shopping-bag"></i> Continue Shopping
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="dashboard.php?page=home" class="action-btn secondary">
                    <i class="fas fa-user"></i> Go to Dashboard
                </a>
            <?php endif; ?>
        </div>
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
</body>
</html>
