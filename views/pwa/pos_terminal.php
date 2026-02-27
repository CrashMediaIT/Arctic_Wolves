<?php
/**
 * PWA POS Terminal - Mobile-native POS with product selector and cart
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

if (!$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access required</div>';
    return;
}

// Fetch active products for mobile POS
$posProducts = [];
$posCategories = [];
try {
    $stmt = $pdo->prepare("
        SELECT mp.id, mp.name, mp.price, mp.image_url, mc.name as category_name,
               (SELECT SUM(mps.quantity) FROM merchandise_product_sizes mps WHERE mps.product_id = mp.id) as total_quantity
        FROM merchandise_products mp
        LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
        WHERE mp.is_active = 1
        ORDER BY mc.display_order ASC, mc.name ASC, mp.name ASC
    ");
    $stmt->execute();
    $posProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $catStmt = $pdo->query("SELECT DISTINCT name FROM merchandise_categories WHERE is_active = 1 AND (parent_id IS NULL OR parent_id = 0) ORDER BY display_order ASC, name ASC");
    $posCategories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $posProducts = []; $posCategories = []; }

// Get tax settings
$posTaxRate = 0;
try {
    $taxStmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('tax_rate','tax_name')");
    $taxSettings = $taxStmt->fetchAll(PDO::FETCH_KEY_PAIR);
    $posTaxRate = floatval($taxSettings['tax_rate'] ?? 0);
    $posTaxName = $taxSettings['tax_name'] ?? 'Tax';
} catch (PDOException $e) { $posTaxRate = 0; $posTaxName = 'Tax'; }

$recentPOS = [];
try {
    $stmt = $pdo->prepare("SELECT id, total_amount, payment_method, created_at FROM pos_transactions ORDER BY created_at DESC LIMIT 10");
    $stmt->execute();
    $recentPOS = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $recentPOS = []; }
?>
<style>
.m-pos { padding: 16px; font-family: Inter, sans-serif; }
.m-pos-header { margin-bottom: 16px; }
.m-pos-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-pos-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-pos-notice {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 24px; text-align: center; margin-bottom: 20px;
}
.m-pos-notice-icon { font-size: 32px; color: #8B5CF6; margin-bottom: 12px; }
.m-pos-notice-text { font-size: 14px; color: #A8A8B8; margin-bottom: 16px; }
.m-pos-notice-btn {
    display: inline-flex; align-items: center; gap: 8px;
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    color: #fff; padding: 12px 24px; border-radius: 10px;
    text-decoration: none; font-size: 14px; font-weight: 600;
    min-height: 44px;
}
.m-section-title { font-size: 15px; font-weight: 600; color: #fff; margin: 0 0 12px; }
.m-pos-tx {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px; min-height: 44px;
}
.m-pos-tx-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
    background: rgba(16,185,129,0.15); color: #10B981;
}
.m-pos-tx-body { flex: 1; min-width: 0; }
.m-pos-tx-id { font-size: 13px; font-weight: 600; color: #fff; }
.m-pos-tx-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-pos-tx-amount { font-size: 14px; font-weight: 700; color: #fff; flex-shrink: 0; }
.m-empty-state { text-align: center; padding: 32px 20px; color: #6B6B7B; font-size: 13px; }
.m-empty-state i { font-size: 28px; display: block; margin-bottom: 10px; }
/* Quick Sale button */
.m-pos-quick-sale-btn {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px;
    font-weight: 600; font-size: 14px; cursor: pointer; width: 100%; margin-bottom: 20px;
    font-family: Inter, sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px;
}
.m-pos-quick-sale-btn:active { opacity: 0.85; }
/* Product grid in bottom sheet */
.m-pos-search {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 14px; font-family: Inter, sans-serif;
    box-sizing: border-box; margin-bottom: 12px;
}
.m-pos-search:focus { outline: none; border-color: #8B5CF6; }
.m-pos-cat-filters { display: flex; gap: 8px; overflow-x: auto; padding-bottom: 10px; margin-bottom: 10px; }
.m-pos-cat-btn {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; color: #A8A8B8;
    padding: 8px 14px; font-size: 12px; font-weight: 600; cursor: pointer; white-space: nowrap;
    font-family: Inter, sans-serif; min-height: 36px;
}
.m-pos-cat-btn.m-active { background: rgba(107,70,193,0.2); border-color: #8B5CF6; color: #8B5CF6; }
.m-pos-product-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; max-height: 40vh; overflow-y: auto; margin-bottom: 12px; }
.m-pos-product-item {
    background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px; padding: 12px;
    cursor: pointer; text-align: center; transition: border-color 0.15s;
}
.m-pos-product-item:active { border-color: #8B5CF6; }
.m-pos-product-item.m-out-of-stock { opacity: 0.5; pointer-events: none; }
.m-pos-product-name { font-size: 12px; font-weight: 600; color: #fff; margin-bottom: 4px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.m-pos-product-price { font-size: 13px; font-weight: 700; color: #10B981; }
/* Cart sheet */
.m-pos-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1000; align-items: flex-end; justify-content: center;
}
.m-pos-overlay.m-visible { display: flex; }
.m-pos-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 500px;
    max-height: 90vh; overflow-y: auto; padding: 20px 16px 32px;
    animation: mPosSlideUp 0.3s ease;
}
@keyframes mPosSlideUp { from { transform: translateY(100%); } to { transform: translateY(0); } }
.m-pos-sheet-handle { width: 36px; height: 4px; background: #2D2D3F; border-radius: 2px; margin: 0 auto 16px; }
.m-pos-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; text-align: center; }
/* Cart items */
.m-pos-cart-section { margin-top: 12px; border-top: 1px solid #2D2D3F; padding-top: 12px; }
.m-pos-cart-item {
    display: flex; align-items: center; justify-content: space-between; padding: 10px 0;
    border-bottom: 1px solid #2D2D3F;
}
.m-pos-cart-item-name { font-size: 13px; font-weight: 600; color: #fff; flex: 1; }
.m-pos-cart-item-qty {
    display: flex; align-items: center; gap: 8px;
}
.m-pos-cart-qty-btn {
    width: 32px; height: 32px; border-radius: 8px; border: 1px solid #2D2D3F;
    background: #0A0A0F; color: #fff; font-size: 16px; cursor: pointer;
    display: flex; align-items: center; justify-content: center; font-weight: 700;
}
.m-pos-cart-qty-val { font-size: 14px; font-weight: 600; color: #fff; min-width: 20px; text-align: center; }
.m-pos-cart-item-price { font-size: 13px; font-weight: 700; color: #10B981; margin-left: 12px; min-width: 60px; text-align: right; }
.m-pos-cart-empty { text-align: center; padding: 20px; color: #6B6B7B; font-size: 13px; }
/* Totals */
.m-pos-totals { margin-top: 12px; padding: 12px; background: #0A0A0F; border-radius: 10px; }
.m-pos-total-row { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 13px; color: #A8A8B8; }
.m-pos-total-row.m-total-final { font-size: 16px; font-weight: 700; color: #fff; margin-top: 8px; padding-top: 8px; border-top: 1px solid #2D2D3F; }
/* Payment buttons */
.m-pos-pay-btns { display: flex; gap: 10px; margin-top: 14px; }
.m-pos-pay-btn {
    flex: 1; min-height: 44px; border: none; border-radius: 10px; font-weight: 600;
    font-size: 13px; cursor: pointer; font-family: Inter, sans-serif;
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-pos-pay-card { background: #6B46C1; color: #fff; }
.m-pos-pay-cash { background: #10B981; color: #fff; }
.m-pos-pay-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.m-pos-pay-btn:active:not(:disabled) { opacity: 0.85; }
/* Cash modal */
.m-pos-cash-overlay {
    display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6);
    z-index: 1100; align-items: flex-end; justify-content: center;
}
.m-pos-cash-overlay.m-visible { display: flex; }
.m-pos-cash-sheet {
    background: #16161F; border-radius: 16px 16px 0 0; width: 100%; max-width: 500px;
    padding: 20px 16px 32px; animation: mPosSlideUp 0.3s ease;
}
.m-pos-cash-input {
    width: 100%; background: #0A0A0F; border: 1px solid #2D2D3F; border-radius: 10px;
    color: #fff; padding: 12px; min-height: 44px; font-size: 18px; font-weight: 700;
    text-align: center; font-family: Inter, sans-serif; box-sizing: border-box;
}
.m-pos-cash-input:focus { outline: none; border-color: #8B5CF6; }
.m-pos-cash-change { text-align: center; margin: 12px 0; font-size: 15px; color: #A8A8B8; }
.m-pos-cash-change span { font-weight: 700; color: #10B981; }
.m-pos-confirm-cash {
    background: #6B46C1; color: #fff; border: none; border-radius: 10px; min-height: 44px;
    font-weight: 600; font-size: 14px; cursor: pointer; width: 100%;
    font-family: Inter, sans-serif; display: flex; align-items: center; justify-content: center; gap: 6px;
}
.m-pos-confirm-cash:disabled { opacity: 0.5; }
.m-pos-toast {
    display: none; position: fixed; bottom: 80px; left: 50%; transform: translateX(-50%);
    background: #10B981; color: #fff; padding: 10px 20px; border-radius: 10px;
    font-size: 13px; font-weight: 600; z-index: 1200; font-family: Inter, sans-serif;
}
.m-pos-toast.m-visible { display: block; }
</style>

<div class="m-pos">
    <div class="m-pos-header">
        <h2 class="m-pos-title">POS Terminal</h2>
        <p class="m-pos-sub">Point of Sale</p>
    </div>

    <button class="m-pos-quick-sale-btn" onclick="mOpenPosSheet()" type="button">
        <i class="fas fa-cart-plus"></i> New Sale
    </button>

    <div class="m-pos-notice">
        <div class="m-pos-notice-icon"><i class="fas fa-cash-register"></i></div>
        <div class="m-pos-notice-text">For full POS features, use tablet or desktop</div>
        <a href="/pos_kiosk.php" class="m-pos-notice-btn">
            <i class="fas fa-external-link-alt"></i> Open Full POS
        </a>
    </div>

    <h3 class="m-section-title">Recent Transactions</h3>
    <?php if (empty($recentPOS)): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            No recent POS transactions
        </div>
    <?php else: ?>
        <?php foreach ($recentPOS as $tx):
            $methodIcon = match(strtolower($tx['payment_method'] ?? '')) {
                'credit_card', 'card', 'stripe' => 'fa-credit-card',
                'cash' => 'fa-money-bill',
                default => 'fa-receipt',
            };
        ?>
        <div class="m-pos-tx">
            <div class="m-pos-tx-icon">
                <i class="fas <?= $methodIcon ?>"></i>
            </div>
            <div class="m-pos-tx-body">
                <div class="m-pos-tx-id">Transaction #<?= (int)$tx['id'] ?></div>
                <div class="m-pos-tx-meta">
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $tx['payment_method'] ?? 'N/A'))) ?>
                    · <?= date('M j, g:i A', strtotime($tx['created_at'])) ?>
                </div>
            </div>
            <div class="m-pos-tx-amount">$<?= number_format((float)$tx['total_amount'], 2) ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- POS Sale Bottom Sheet -->
<div class="m-pos-overlay" id="mPosOverlay" onclick="if(event.target===this)mClosePosSheet()">
    <div class="m-pos-sheet">
        <div class="m-pos-sheet-handle"></div>
        <div class="m-pos-sheet-title">New Sale</div>

        <input type="text" class="m-pos-search" id="mPosSearch" placeholder="Search products..." oninput="mFilterPosProducts()">

        <div class="m-pos-cat-filters" id="mPosCatFilters">
            <button class="m-pos-cat-btn m-active" onclick="mFilterPosCat(this,'')" type="button">All</button>
            <?php foreach ($posCategories as $cat): ?>
            <button class="m-pos-cat-btn" onclick="mFilterPosCat(this,'<?= htmlspecialchars(addslashes($cat)) ?>')" type="button"><?= htmlspecialchars($cat) ?></button>
            <?php endforeach; ?>
        </div>

        <div class="m-pos-product-grid" id="mPosProductGrid">
            <?php foreach ($posProducts as $pp):
                $inStock = ((int)($pp['total_quantity'] ?? 0)) > 0;
            ?>
            <div class="m-pos-product-item <?= !$inStock ? 'm-out-of-stock' : '' ?>"
                 data-id="<?= (int)$pp['id'] ?>"
                 data-name="<?= htmlspecialchars($pp['name']) ?>"
                 data-price="<?= (float)$pp['price'] ?>"
                 data-cat="<?= htmlspecialchars($pp['category_name'] ?? '') ?>"
                 onclick="mAddToCart(this)">
                <div class="m-pos-product-name"><?= htmlspecialchars($pp['name']) ?></div>
                <div class="m-pos-product-price">$<?= number_format((float)$pp['price'], 2) ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="m-pos-cart-section">
            <h4 style="color:#fff;font-size:14px;margin:0 0 8px;">Cart</h4>
            <div id="mPosCartItems">
                <div class="m-pos-cart-empty"><i class="fas fa-shopping-basket"></i> Cart is empty</div>
            </div>
            <div class="m-pos-totals" id="mPosTotals" style="display:none;">
                <div class="m-pos-total-row"><span>Subtotal</span><span id="mPosSubtotal">$0.00</span></div>
                <div class="m-pos-total-row"><span><?= htmlspecialchars($posTaxName) ?> (<?= $posTaxRate ?>%)</span><span id="mPosTax">$0.00</span></div>
                <div class="m-pos-total-row m-total-final"><span>Total</span><span id="mPosTotal">$0.00</span></div>
            </div>
            <div class="m-pos-pay-btns">
                <button class="m-pos-pay-btn m-pos-pay-card" id="mPosPayCard" disabled onclick="mProcessCard()" type="button">
                    <i class="fas fa-credit-card"></i> Card
                </button>
                <button class="m-pos-pay-btn m-pos-pay-cash" id="mPosPayCash" disabled onclick="mOpenCashSheet()" type="button">
                    <i class="fas fa-money-bill"></i> Cash
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Cash Payment Bottom Sheet -->
<div class="m-pos-cash-overlay" id="mPosCashOverlay" onclick="if(event.target===this)mCloseCashSheet()">
    <div class="m-pos-cash-sheet">
        <div class="m-pos-sheet-handle"></div>
        <div class="m-pos-sheet-title">Cash Payment</div>
        <div style="text-align:center;font-size:24px;font-weight:700;color:#fff;margin-bottom:12px;" id="mCashTotal">$0.00</div>
        <input type="number" class="m-pos-cash-input" id="mCashReceived" placeholder="Amount received" step="0.01" min="0" oninput="mCalcChange()">
        <div class="m-pos-cash-change">Change: <span id="mCashChange">$0.00</span></div>
        <button class="m-pos-confirm-cash" id="mCashConfirmBtn" disabled onclick="mProcessCash()" type="button">
            <i class="fas fa-check"></i> Complete Sale
        </button>
    </div>
</div>

<div class="m-pos-toast" id="mPosToast"></div>

<script>
var mPosCart = [];
var mPosTaxRate = <?= $posTaxRate ?>;
var mPosCsrf = '<?= $_SESSION['csrf_token'] ?? '' ?>';

function mOpenPosSheet() { document.getElementById('mPosOverlay').classList.add('m-visible'); }
function mClosePosSheet() { document.getElementById('mPosOverlay').classList.remove('m-visible'); }
function mOpenCashSheet() {
    var totals = mPosCalcTotals();
    document.getElementById('mCashTotal').textContent = '$' + totals.total.toFixed(2);
    document.getElementById('mCashReceived').value = '';
    document.getElementById('mCashChange').textContent = '$0.00';
    document.getElementById('mCashConfirmBtn').disabled = true;
    document.getElementById('mPosCashOverlay').classList.add('m-visible');
}
function mCloseCashSheet() { document.getElementById('mPosCashOverlay').classList.remove('m-visible'); }

function mFilterPosProducts() {
    var q = document.getElementById('mPosSearch').value.toLowerCase();
    document.querySelectorAll('.m-pos-product-item').forEach(function(el) {
        el.style.display = el.dataset.name.toLowerCase().includes(q) ? '' : 'none';
    });
}
function mFilterPosCat(btn, cat) {
    document.querySelectorAll('.m-pos-cat-btn').forEach(function(b) { b.classList.remove('m-active'); });
    btn.classList.add('m-active');
    document.querySelectorAll('.m-pos-product-item').forEach(function(el) {
        el.style.display = (!cat || el.dataset.cat === cat) ? '' : 'none';
    });
}

function mAddToCart(el) {
    var id = el.dataset.id, name = el.dataset.name, price = parseFloat(el.dataset.price);
    var existing = mPosCart.find(function(i) { return i.id === id; });
    if (existing) { existing.quantity++; } else { mPosCart.push({ id: id, name: name, price: price, quantity: 1 }); }
    mRenderPosCart();
}

function mUpdatePosQty(id, delta) {
    var item = mPosCart.find(function(i) { return i.id === id; });
    if (item) { item.quantity += delta; if (item.quantity <= 0) mPosCart = mPosCart.filter(function(i) { return i.id !== id; }); }
    mRenderPosCart();
}

function mPosCalcTotals() {
    var subtotal = mPosCart.reduce(function(s, i) { return s + i.price * i.quantity; }, 0);
    var tax = subtotal * (mPosTaxRate / 100);
    return { subtotal: subtotal, tax: tax, total: subtotal + tax };
}

function mRenderPosCart() {
    var container = document.getElementById('mPosCartItems');
    var totalsDiv = document.getElementById('mPosTotals');
    if (mPosCart.length === 0) {
        container.innerHTML = '<div class="m-pos-cart-empty"><i class="fas fa-shopping-basket"></i> Cart is empty</div>';
        totalsDiv.style.display = 'none';
        document.getElementById('mPosPayCard').disabled = true;
        document.getElementById('mPosPayCash').disabled = true;
        return;
    }
    var html = '';
    mPosCart.forEach(function(item) {
        html += '<div class="m-pos-cart-item">' +
            '<div class="m-pos-cart-item-name">' + item.name + '</div>' +
            '<div class="m-pos-cart-item-qty">' +
                '<button class="m-pos-cart-qty-btn" type="button" onclick="mUpdatePosQty(\'' + item.id + '\',-1)">−</button>' +
                '<span class="m-pos-cart-qty-val">' + item.quantity + '</span>' +
                '<button class="m-pos-cart-qty-btn" type="button" onclick="mUpdatePosQty(\'' + item.id + '\',1)">+</button>' +
            '</div>' +
            '<div class="m-pos-cart-item-price">$' + (item.price * item.quantity).toFixed(2) + '</div>' +
        '</div>';
    });
    container.innerHTML = html;
    var t = mPosCalcTotals();
    document.getElementById('mPosSubtotal').textContent = '$' + t.subtotal.toFixed(2);
    document.getElementById('mPosTax').textContent = '$' + t.tax.toFixed(2);
    document.getElementById('mPosTotal').textContent = '$' + t.total.toFixed(2);
    totalsDiv.style.display = 'block';
    document.getElementById('mPosPayCard').disabled = false;
    document.getElementById('mPosPayCash').disabled = false;
}

function mCalcChange() {
    var received = parseFloat(document.getElementById('mCashReceived').value) || 0;
    var t = mPosCalcTotals();
    var change = received - t.total;
    document.getElementById('mCashChange').textContent = '$' + Math.max(0, change).toFixed(2);
    document.getElementById('mCashConfirmBtn').disabled = (received < t.total);
}

function mProcessCard() {
    if (mPosCart.length === 0) return;
    var btn = document.getElementById('mPosPayCard');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    fetch('process_pos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'process_card_payment',
            items: mPosCart.map(function(i) { return { id: i.id, name: i.name, price: i.price, quantity: i.quantity, key: i.id }; }),
            csrf_token: mPosCsrf
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            mPosCart = [];
            mRenderPosCart();
            mClosePosSheet();
            mShowPosToast('Payment successful! #' + data.transaction_number);
        } else {
            showToast('Payment failed: ' + (data.message || 'Unknown error'), 'error');
        }
    })
    .catch(function() { showToast('An error occurred. Please try again.', 'error'); })
    .finally(function() { btn.disabled = false; btn.innerHTML = '<i class="fas fa-credit-card"></i> Card'; });
}

function mProcessCash() {
    if (mPosCart.length === 0) return;
    var received = parseFloat(document.getElementById('mCashReceived').value) || 0;
    var btn = document.getElementById('mCashConfirmBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    fetch('process_pos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action: 'process_cash_payment',
            items: mPosCart.map(function(i) { return { id: i.id, name: i.name, price: i.price, quantity: i.quantity, key: i.id }; }),
            cash_received: received,
            csrf_token: mPosCsrf
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            mPosCart = [];
            mRenderPosCart();
            mCloseCashSheet();
            mClosePosSheet();
            mShowPosToast('Cash sale complete! #' + data.transaction_number);
        } else {
            showToast('Payment failed: ' + (data.message || 'Unknown error'), 'error');
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-check"></i> Complete Sale';
        }
    })
    .catch(function() {
        showToast('An error occurred. Please try again.', 'error');
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-check"></i> Complete Sale';
    });
}

function mShowPosToast(msg) {
    var t = document.getElementById('mPosToast');
    t.textContent = msg;
    t.classList.add('m-visible');
    setTimeout(function() { t.classList.remove('m-visible'); }, 3000);
}
</script>
