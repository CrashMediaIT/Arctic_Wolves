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

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS access denied from unauthorized IP', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'page' => 'pos_terminal']);
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>POS access is not available from this location. Please contact an administrator.</p></div>';
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
if (function_exists('decryptCredential')) {
    if (!empty($settings['stripe_secret_key'])) $settings['stripe_secret_key'] = decryptCredential($settings['stripe_secret_key']);
    if (!empty($settings['stripe_publishable_key'])) $settings['stripe_publishable_key'] = decryptCredential($settings['stripe_publishable_key']);
}
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
    $posUsers = decryptUserRows($posUsers);
} catch (PDOException $e) {
    error_log("POS users fetch error: " . $e->getMessage());
    $posUsers = [];
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-cash-register"></i> POS Terminal</h1>
        <p class="page-description">Point of Sale for merchandise transactions</p>
    </div>
    <div style="display: flex; gap: 12px; align-items: center;">
        <button type="button" onclick="openChildCheckinScanner()" style="padding: 12px 20px; background: #10b981; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-qrcode"></i> Child Check-In/Out
        </button>
        <a href="?page=sip_settings" style="padding: 12px 20px; background: #6366f1; color: #fff; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 14px; display: flex; align-items: center; gap: 8px; text-decoration: none;">
            <i class="fas fa-address-book"></i> Company Directory
        </a>
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
    
    /* Cash Payment Modal */
    .pos-cash-modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.8);
        z-index: 1000;
        align-items: center;
        justify-content: center;
    }
    
    .pos-cash-modal.active {
        display: flex;
    }
    
    .pos-cash-content {
        background: var(--bg-secondary);
        border-radius: 16px;
        padding: 30px;
        width: 90%;
        max-width: 450px;
        position: relative;
    }
    
    .pos-cash-header {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 24px;
    }
    
    .pos-cash-header i {
        font-size: 28px;
        color: #10b981;
    }
    
    .pos-cash-header h3 {
        font-size: 22px;
        font-weight: 700;
        margin: 0;
    }
    
    .pos-cash-totals {
        background: var(--bg);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 24px;
    }
    
    .pos-cash-row {
        display: flex;
        justify-content: space-between;
        margin-bottom: 10px;
        font-size: 15px;
    }
    
    .pos-cash-row:last-child {
        margin-bottom: 0;
    }
    
    .pos-cash-row.total-row {
        font-size: 22px;
        font-weight: 700;
        padding-top: 12px;
        border-top: 1px solid var(--border);
        margin-top: 12px;
    }
    
    .pos-cash-row.total-row .cash-value {
        color: var(--primary);
    }
    
    .pos-cash-input-group {
        margin-bottom: 20px;
    }
    
    .pos-cash-input-group label {
        display: block;
        font-size: 14px;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text);
    }
    
    .pos-cash-input-group label i {
        margin-right: 8px;
        color: #10b981;
    }
    
    .pos-cash-input {
        width: 100%;
        padding: 16px 20px;
        background: var(--bg);
        border: 2px solid var(--border);
        border-radius: 12px;
        color: #fff;
        font-size: 24px;
        font-weight: 700;
        text-align: center;
        transition: 0.2s;
    }
    
    .pos-cash-input:focus {
        outline: none;
        border-color: #10b981;
    }
    
    .pos-cash-input.error {
        border-color: #ef4444;
    }
    
    .pos-quick-amounts {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 10px;
        margin-bottom: 20px;
    }
    
    .pos-quick-btn {
        padding: 14px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: #fff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
    }
    
    .pos-quick-btn:hover {
        background: rgba(16, 185, 129, 0.15);
        border-color: #10b981;
        color: #10b981;
    }
    
    .pos-change-display {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.05));
        border: 2px solid #10b981;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        margin-bottom: 24px;
    }
    
    .pos-change-display.insufficient {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(239, 68, 68, 0.05));
        border-color: #ef4444;
    }
    
    .pos-change-label {
        font-size: 14px;
        color: var(--text-dim);
        margin-bottom: 8px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .pos-change-value {
        font-size: 36px;
        font-weight: 800;
        color: #10b981;
    }
    
    .pos-change-display.insufficient .pos-change-value {
        color: #ef4444;
    }
    
    .pos-cash-actions {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 12px;
    }
    
    .pos-cash-cancel {
        padding: 16px;
        background: var(--bg);
        border: 1px solid var(--border);
        border-radius: 12px;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
    }
    
    .pos-cash-cancel:hover {
        background: rgba(239, 68, 68, 0.1);
        border-color: #ef4444;
        color: #ef4444;
    }
    
    .pos-cash-confirm {
        padding: 16px;
        background: #10b981;
        border: none;
        border-radius: 12px;
        color: #fff;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: 0.2s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    
    .pos-cash-confirm:hover:not(:disabled) {
        background: #059669;
    }
    
    .pos-cash-confirm:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    
    @media (max-width: 1000px) {
        .pos-container {
            grid-template-columns: 1fr;
            height: auto;
        }
        
        .pos-products {
            min-height: 400px;
        }
        
        .pos-quick-amounts {
            grid-template-columns: repeat(2, 1fr);
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

<!-- Cash Payment Modal -->
<div class="pos-cash-modal" id="cash-modal">
    <div class="pos-cash-content">
        <button class="modal-close pos-modal-close" onclick="closeCashModal()" aria-label="Close modal">&times;</button>
        
        <div class="pos-cash-header">
            <i class="fas fa-money-bill-wave"></i>
            <h3>Cash Payment</h3>
        </div>
        
        <div class="pos-cash-totals">
            <div class="pos-cash-row">
                <span>Subtotal</span>
                <span id="cash-modal-subtotal">$0.00</span>
            </div>
            <div class="pos-cash-row">
                <span><?= htmlspecialchars($taxName) ?> (<?= $taxRate ?>%)</span>
                <span id="cash-modal-tax">$0.00</span>
            </div>
            <div class="pos-cash-row total-row">
                <span>Total Due</span>
                <span class="cash-value" id="cash-modal-total">$0.00</span>
            </div>
        </div>
        
        <div class="pos-cash-input-group">
            <label><i class="fas fa-hand-holding-usd"></i> Cash Received</label>
            <input type="number" class="pos-cash-input" id="cash-received-input" 
                   placeholder="0.00" step="0.01" min="0" 
                   oninput="calculateChange()">
        </div>
        
        <div class="pos-quick-amounts" id="quick-amounts">
            <!-- Quick amount buttons will be populated by JavaScript -->
        </div>
        
        <div class="pos-change-display" id="change-display">
            <div class="pos-change-label">Change to Give</div>
            <div class="pos-change-value" id="change-amount">$0.00</div>
        </div>
        
        <div class="pos-cash-actions">
            <button class="pos-cash-cancel" onclick="closeCashModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button class="pos-cash-confirm" id="confirm-cash-btn" onclick="processCashPayment()" disabled>
                <i class="fas fa-check"></i> Complete Sale
            </button>
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
    if (cart.length === 0) return;
    
    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    // Update modal display values
    document.getElementById('cash-modal-subtotal').textContent = '$' + subtotal.toFixed(2);
    document.getElementById('cash-modal-tax').textContent = '$' + tax.toFixed(2);
    document.getElementById('cash-modal-total').textContent = '$' + total.toFixed(2);
    
    // Reset input and change display
    const cashInput = document.getElementById('cash-received-input');
    cashInput.value = '';
    cashInput.classList.remove('error');
    
    document.getElementById('change-amount').textContent = '$0.00';
    document.getElementById('change-display').classList.remove('insufficient');
    document.getElementById('confirm-cash-btn').disabled = true;
    
    // Generate quick amount buttons (round up to common denominations)
    const quickAmountsDiv = document.getElementById('quick-amounts');
    quickAmountsDiv.innerHTML = '';
    
    // Calculate quick amounts: exact, round to next $5, $10, $20, etc.
    const quickAmounts = [
        { label: 'Exact', value: total },
        { label: '$' + Math.ceil(total / 5) * 5, value: Math.ceil(total / 5) * 5 },
        { label: '$' + Math.ceil(total / 10) * 10, value: Math.ceil(total / 10) * 10 },
        { label: '$' + Math.ceil(total / 20) * 20, value: Math.ceil(total / 20) * 20 }
    ];
    
    // Remove duplicates and filter valid amounts
    const uniqueAmounts = [];
    quickAmounts.forEach(amt => {
        if (amt.value >= total && !uniqueAmounts.find(a => a.value === amt.value)) {
            uniqueAmounts.push(amt);
        }
    });
    
    // Add common bills if higher than total
    [50, 100].forEach(bill => {
        if (bill >= total && !uniqueAmounts.find(a => a.value === bill)) {
            uniqueAmounts.push({ label: '$' + bill, value: bill });
        }
    });
    
    // Show first 4 quick amounts
    uniqueAmounts.slice(0, 4).forEach(amt => {
        const btn = document.createElement('button');
        btn.className = 'pos-quick-btn';
        btn.textContent = amt.label;
        btn.onclick = function() {
            document.getElementById('cash-received-input').value = amt.value.toFixed(2);
            calculateChange();
        };
        quickAmountsDiv.appendChild(btn);
    });
    
    // Show modal
    document.getElementById('cash-modal').classList.add('active');
    
    // Focus on input
    setTimeout(() => cashInput.focus(), 100);
}

function closeCashModal() {
    document.getElementById('cash-modal').classList.remove('active');
}

function calculateChange() {
    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    const cashInput = document.getElementById('cash-received-input');
    const received = parseFloat(cashInput.value) || 0;
    const change = received - total;
    
    const changeDisplay = document.getElementById('change-display');
    const changeAmount = document.getElementById('change-amount');
    const confirmBtn = document.getElementById('confirm-cash-btn');
    
    if (received === 0) {
        changeAmount.textContent = '$0.00';
        changeDisplay.classList.remove('insufficient');
        cashInput.classList.remove('error');
        confirmBtn.disabled = true;
    } else if (received < total) {
        changeAmount.textContent = '-$' + Math.abs(change).toFixed(2);
        changeDisplay.classList.add('insufficient');
        cashInput.classList.add('error');
        confirmBtn.disabled = true;
    } else {
        changeAmount.textContent = '$' + change.toFixed(2);
        changeDisplay.classList.remove('insufficient');
        cashInput.classList.remove('error');
        confirmBtn.disabled = false;
    }
}

function processCashPayment() {
    const cashInput = document.getElementById('cash-received-input');
    const receivedAmount = parseFloat(cashInput.value) || 0;
    
    const subtotal = cart.reduce((sum, item) => sum + item.price * item.quantity, 0);
    const tax = subtotal * (taxRate / 100);
    const total = subtotal + tax;
    
    if (receivedAmount < total) {
        alert('Insufficient payment amount');
        return;
    }
    
    const change = receivedAmount - total;
    const customerUserId = document.getElementById('customer-user-id')?.value || '';
    
    // Disable button and show loading
    const confirmBtn = document.getElementById('confirm-cash-btn');
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
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
            // Show success in a nicer way using the change display
            const changeDisplay = document.getElementById('change-display');
            const changeAmount = document.getElementById('change-amount');
            changeDisplay.innerHTML = `
                <div class="pos-change-label" style="color: #10b981;"><i class="fas fa-check"></i> Payment Successful</div>
                <div class="pos-change-value">Transaction #${data.transaction_number}</div>
                <div style="font-size: 18px; margin-top: 10px;">Change: $${change.toFixed(2)}</div>
            `;
            
            // Reset cart and close modal after delay
            setTimeout(() => {
                cart = [];
                renderCart();
                document.getElementById('customer-user-id').value = '';
                closeCashModal();
            }, 2000);
        } else {
            alert('Payment failed: ' + (data.message || 'Unknown error'));
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = '<i class="fas fa-check"></i> Complete Sale';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = '<i class="fas fa-check"></i> Complete Sale';
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

// =========================================================
// Child Check-In/Check-Out Scanner
// =========================================================
function openChildCheckinScanner() {
    document.getElementById('checkin-scanner-modal').style.display = 'flex';
    document.getElementById('checkin-scanner-input').value = '';
    document.getElementById('checkin-scanner-input').focus();
    document.getElementById('checkin-scanner-result').innerHTML = '';
    document.getElementById('checkin-scanner-result').style.display = 'none';
}

function closeChildCheckinScanner() {
    document.getElementById('checkin-scanner-modal').style.display = 'none';
    // Stop camera if active
    if (window._checkinVideoStream) {
        window._checkinVideoStream.getTracks().forEach(function(t) { t.stop(); });
        window._checkinVideoStream = null;
    }
    var cameraSection = document.getElementById('checkin-camera-section');
    if (cameraSection) cameraSection.style.display = 'none';
}

function processCheckinScan() {
    var code = document.getElementById('checkin-scanner-input').value.trim();
    if (!code) return;

    var resultDiv = document.getElementById('checkin-scanner-result');
    resultDiv.style.display = 'block';
    resultDiv.innerHTML = '<div style="text-align: center; padding: 20px; color: #94a3b8;"><i class="fas fa-spinner fa-spin"></i> Processing...</div>';

    var csrfToken = document.querySelector('[name="csrf_token"]') ? document.querySelector('[name="csrf_token"]').value : '';
    
    fetch('process_camp_checkin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
        body: 'action=scan_code&code=' + encodeURIComponent(code) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            var itemsHtml = data.items ? '<div style="margin-top: 10px; padding: 10px; background: #06080b; border-radius: 6px;"><strong>Items:</strong> ' + escapeHtml(data.items) + '</div>' : '';
            var sharedHtml = data.shared_with ? '<p style="margin-top: 8px; font-size: 13px; color: #f59e0b;"><i class="fas fa-user"></i> Presented by: ' + escapeHtml(data.shared_with) + '</p>' : '';
            resultDiv.innerHTML =
                '<div style="padding: 20px; background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; text-align: center;">' +
                '<i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 12px; display: block;"></i>' +
                '<h3 style="color: #10b981; margin: 0 0 8px 0;">' + escapeHtml(data.message) + '</h3>' +
                '<p style="color: #94a3b8; margin: 0;">Session: ' + escapeHtml(data.session_title || '') + '</p>' +
                '<p style="color: #94a3b8; margin: 4px 0 0 0;">Date: ' + escapeHtml(data.session_date || '') + (data.session_time ? ' at ' + escapeHtml(data.session_time) : '') + '</p>' +
                '<p style="color: #94a3b8; margin: 4px 0 0 0;">Parent: ' + escapeHtml(data.parent_name || '') + '</p>' +
                sharedHtml + itemsHtml +
                '</div>';
        } else {
            var icon = data.status === 'already_used' ? 'fa-exclamation-triangle' : (data.status === 'expired' ? 'fa-clock' : 'fa-times-circle');
            var color = data.status === 'already_used' ? '#f59e0b' : '#ef4444';
            resultDiv.innerHTML =
                '<div style="padding: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid ' + color + '; border-radius: 8px; text-align: center;">' +
                '<i class="fas ' + icon + '" style="font-size: 48px; color: ' + color + '; margin-bottom: 12px; display: block;"></i>' +
                '<h3 style="color: ' + color + '; margin: 0 0 8px 0;">' + escapeHtml(data.message) + '</h3>' +
                (data.athlete_name ? '<p style="color: #94a3b8;">Athlete: ' + escapeHtml(data.athlete_name) + '</p>' : '') +
                '</div>';
        }
        // Clear input for next scan
        document.getElementById('checkin-scanner-input').value = '';
        document.getElementById('checkin-scanner-input').focus();
    })
    .catch(function(err) {
        resultDiv.innerHTML = '<div style="padding: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; text-align: center; color: #ef4444;"><i class="fas fa-exclamation-circle"></i> Network error. Please try again.</div>';
    });
}

function escapeHtml(text) {
    if (!text) return '';
    var d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

// Camera-based QR scanning
function startCameraScanner() {
    var cameraSection = document.getElementById('checkin-camera-section');
    var video = document.getElementById('checkin-camera-video');
    cameraSection.style.display = 'block';

    navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(function(stream) {
        window._checkinVideoStream = stream;
        video.srcObject = stream;
        video.play();
        scanFromCamera();
    })
    .catch(function(err) {
        cameraSection.innerHTML = '<p style="color: #ef4444; text-align: center; padding: 20px;"><i class="fas fa-exclamation-circle"></i> Camera not available: ' + err.message + '</p>';
    });
}

function scanFromCamera() {
    var video = document.getElementById('checkin-camera-video');
    var canvas = document.getElementById('checkin-camera-canvas');
    if (!video || !canvas || !window._checkinVideoStream) return;

    var ctx = canvas.getContext('2d');
    if (video.readyState === video.HAVE_ENOUGH_DATA) {
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

        try {
            var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
            if (typeof jsQR !== 'undefined') {
                var qrCode = jsQR(imageData.data, imageData.width, imageData.height);
                if (qrCode) {
                    document.getElementById('checkin-scanner-input').value = qrCode.data;
                    // Stop camera
                    window._checkinVideoStream.getTracks().forEach(function(t) { t.stop(); });
                    window._checkinVideoStream = null;
                    document.getElementById('checkin-camera-section').style.display = 'none';
                    processCheckinScan();
                    return;
                }
            }
        } catch(e) { /* continue scanning */ }
    }
    requestAnimationFrame(scanFromCamera);
}
</script>

<!-- jsQR library for camera-based QR code scanning -->
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>

<!-- Child Check-In/Check-Out Scanner Modal -->
<div id="checkin-scanner-modal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.85); z-index: 10000; align-items: center; justify-content: center;">
    <div style="background: #0d1117; border: 1px solid #1e293b; border-radius: 12px; padding: 30px; max-width: 550px; width: 95%;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px;">
            <h2 style="font-size: 22px; font-weight: 900; color: #fff; margin: 0;">
                <i class="fas fa-qrcode" style="color: #10b981;"></i> Child Check-In / Check-Out
            </h2>
            <button onclick="closeChildCheckinScanner()" style="background: none; border: none; color: #94a3b8; font-size: 24px; cursor: pointer;">&times;</button>
        </div>

        <p style="color: #94a3b8; font-size: 14px; margin-bottom: 20px;">
            Scan a QR code using a barcode scanner or use the camera to check in or check out a child.
        </p>

        <!-- Barcode Scanner Input (works with hardware barcode scanners) -->
        <div style="margin-bottom: 20px;">
            <label style="display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase;">
                Scan or Enter Code
            </label>
            <div style="display: flex; gap: 10px;">
                <input type="text" id="checkin-scanner-input" placeholder="Scan QR code here or type code..." 
                       style="flex: 1; padding: 14px; background: #06080b; border: 1px solid #1e293b; border-radius: 6px; color: #fff; font-size: 16px; font-family: monospace;"
                       onkeydown="if(event.key==='Enter'){event.preventDefault();processCheckinScan();}">
                <button onclick="processCheckinScan()" style="padding: 14px 20px; background: #10b981; color: #fff; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; font-size: 14px;">
                    <i class="fas fa-search"></i> Scan
                </button>
            </div>
            <small style="color: #64748b; font-size: 12px; display: block; margin-top: 6px;">
                <i class="fas fa-barcode"></i> Point your barcode scanner at the QR code, or type the code manually
            </small>
        </div>

        <!-- Camera Scanner Option -->
        <div style="text-align: center; margin-bottom: 20px;">
            <button onclick="startCameraScanner()" style="padding: 12px 20px; background: #1e293b; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; font-size: 13px;">
                <i class="fas fa-camera"></i> Use Camera to Scan
            </button>
        </div>

        <div id="checkin-camera-section" style="display: none; margin-bottom: 20px;">
            <div style="position: relative; border-radius: 8px; overflow: hidden; background: #000;">
                <video id="checkin-camera-video" style="width: 100%; display: block;" playsinline></video>
                <canvas id="checkin-camera-canvas" style="display: none;"></canvas>
                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 200px; height: 200px; border: 2px solid #10b981; border-radius: 8px; pointer-events: none;"></div>
            </div>
            <p style="color: #94a3b8; font-size: 12px; text-align: center; margin-top: 8px;">
                <i class="fas fa-crosshairs"></i> Point your camera at the QR code
            </p>
        </div>

        <!-- Scan Result -->
        <div id="checkin-scanner-result" style="display: none;"></div>
    </div>
</div>
