<?php
/**
 * PWA Inventory Management View
 * Mobile-optimized version of views/inventory_management.php
 * In-store and warehouse inventory tracking, incoming shipments, and outgoing orders
 */
require_once __DIR__ . '/../../lib/image_helper.php';

// Check access - require authentication in dashboard context
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-lock" style="font-size:32px;display:block;margin-bottom:12px;"></i>Access denied</div>';
    return;
}

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS access denied from unauthorized IP', ['ip' => getClientIP(), 'page' => 'inventory_management']);
    echo '<div style="padding:40px 20px;text-align:center;color:#EF4444;font-family:Inter,sans-serif;"><i class="fas fa-ban" style="font-size:32px;display:block;margin-bottom:12px;"></i>POS access is not available from this location.</div>';
    return;
}

// Active tab (validated against whitelist)
$active_tab = in_array($_GET['tab'] ?? 'in_store', ['in_store', 'warehouse', 'incoming', 'outgoing']) ? $_GET['tab'] : 'in_store';

// Check if stock_location column exists in merchandise_product_sizes
$hasStockLocation = false;
try {
    $colCheck = $pdo->query("SHOW COLUMNS FROM merchandise_product_sizes LIKE 'stock_location'");
    $hasStockLocation = ($colCheck->rowCount() > 0);
} catch (PDOException $e) {
    // Column doesn't exist yet
}

// --- In-Store Inventory ---
$inStoreProducts = [];
$inStoreStats = ['total_products' => 0, 'total_units' => 0, 'total_value' => 0];

if ($active_tab === 'in_store') {
    try {
        $params = [];
        $locationJoin = "LEFT JOIN merchandise_product_sizes mps ON mps.product_id = mp.id";
        if ($hasStockLocation) {
            $locationJoin .= " AND mps.stock_location = ?";
            $params[] = 'in_store';
        }
        $stmt = $pdo->prepare("
            SELECT mp.id, mp.name, mp.sku, mp.price, mp.cost_price, mp.image_url, mp.is_active,
                   mc.name as category_name,
                   mps.id as size_id, mps.size, mps.quantity, mps.sku_suffix
            FROM merchandise_products mp
            LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
            {$locationJoin}
            WHERE mp.track_inventory = 1
            ORDER BY mp.name ASC, mps.size ASC
        ");
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $grouped = [];
        foreach ($rows as $row) {
            $pid = $row['id'];
            if (!isset($grouped[$pid])) {
                $grouped[$pid] = [
                    'id' => $row['id'],
                    'name' => $row['name'],
                    'sku' => $row['sku'],
                    'price' => $row['price'],
                    'cost_price' => $row['cost_price'],
                    'image_url' => $row['image_url'],
                    'is_active' => $row['is_active'],
                    'category_name' => $row['category_name'],
                    'sizes' => [],
                    'total_qty' => 0,
                ];
            }
            if ($row['size_id']) {
                $grouped[$pid]['sizes'][] = [
                    'size' => $row['size'],
                    'quantity' => intval($row['quantity']),
                    'sku_suffix' => $row['sku_suffix'],
                ];
                $grouped[$pid]['total_qty'] += intval($row['quantity']);
            }
        }
        $inStoreProducts = array_values($grouped);

        $inStoreStats['total_products'] = count($inStoreProducts);
        $inStoreStats['total_units'] = array_sum(array_column($inStoreProducts, 'total_qty'));
        $inStoreStats['total_value'] = array_sum(array_map(function($p) {
            return ($p['price'] ?? 0) * $p['total_qty'];
        }, $inStoreProducts));
    } catch (PDOException $e) {
        error_log("Inventory in-store fetch error: " . $e->getMessage());
    }
}

// --- Warehouse Inventory ---
$warehouseProducts = [];
$warehouseStats = ['total_products' => 0, 'total_units' => 0, 'total_value' => 0];

if ($active_tab === 'warehouse') {
    try {
        if (!$hasStockLocation) {
            $warehouseProducts = [];
        } else {
            $stmt = $pdo->prepare("
                SELECT mp.id, mp.name, mp.sku, mp.price, mp.cost_price, mp.image_url, mp.is_active,
                       mc.name as category_name,
                       mps.id as size_id, mps.size, mps.quantity, mps.sku_suffix
                FROM merchandise_products mp
                LEFT JOIN merchandise_categories mc ON mp.category_id = mc.id
                LEFT JOIN merchandise_product_sizes mps ON mps.product_id = mp.id AND mps.stock_location = 'warehouse'
                WHERE mp.track_inventory = 1
                ORDER BY mp.name ASC, mps.size ASC
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $grouped = [];
            foreach ($rows as $row) {
                $pid = $row['id'];
                if (!isset($grouped[$pid])) {
                    $grouped[$pid] = [
                        'id' => $row['id'],
                        'name' => $row['name'],
                        'sku' => $row['sku'],
                        'price' => $row['price'],
                        'cost_price' => $row['cost_price'],
                        'image_url' => $row['image_url'],
                        'is_active' => $row['is_active'],
                        'category_name' => $row['category_name'],
                        'sizes' => [],
                        'total_qty' => 0,
                    ];
                }
                if ($row['size_id']) {
                    $grouped[$pid]['sizes'][] = [
                        'size' => $row['size'],
                        'quantity' => intval($row['quantity']),
                        'sku_suffix' => $row['sku_suffix'],
                    ];
                    $grouped[$pid]['total_qty'] += intval($row['quantity']);
                }
            }
            $warehouseProducts = array_values(array_filter($grouped, function($p) {
                return !empty($p['sizes']);
            }));
        }

        $warehouseStats['total_products'] = count($warehouseProducts);
        $warehouseStats['total_units'] = array_sum(array_column($warehouseProducts, 'total_qty'));
        $warehouseStats['total_value'] = array_sum(array_map(function($p) {
            return ($p['price'] ?? 0) * $p['total_qty'];
        }, $warehouseProducts));
    } catch (PDOException $e) {
        error_log("Inventory warehouse fetch error: " . $e->getMessage());
    }
}

// --- Incoming Packages (shipment movements) ---
$incomingPackages = [];

if ($active_tab === 'incoming') {
    try {
        $stmt = $pdo->prepare("
            SELECT msm.*,
                   mp.name as product_name, mp.sku as product_sku,
                   mps.size as size_name,
                   u.first_name as creator_first_name, u.last_name as creator_last_name
            FROM merchandise_stock_movements msm
            LEFT JOIN merchandise_products mp ON msm.product_id = mp.id
            LEFT JOIN merchandise_product_sizes mps ON msm.size_id = mps.id
            LEFT JOIN users u ON msm.created_by = u.id
            WHERE msm.movement_type = 'shipment'
            ORDER BY msm.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $incomingPackages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (function_exists('decryptUserRows')) {
            $incomingPackages = decryptUserRows($incomingPackages);
        }
    } catch (PDOException $e) {
        error_log("Inventory incoming fetch error: " . $e->getMessage());
    }
}

$totalUnitsReceived = array_sum(array_map(function($p) { return max(0, intval($p['quantity_change'])); }, $incomingPackages));

// --- Outgoing Packages (processing/shipped orders) ---
$outgoingOrders = [];

if ($active_tab === 'outgoing') {
    try {
        $stmt = $pdo->prepare("
            SELECT o.*,
                (SELECT GROUP_CONCAT(CONCAT(oi.quantity, 'x ', mp.name, IF(oi.size IS NOT NULL, CONCAT(' (', oi.size, ')'), '')) SEPARATOR ', ')
                 FROM shop_order_items oi
                 LEFT JOIN merchandise_products mp ON oi.product_id = mp.id
                 WHERE oi.order_id = o.id) as items_summary,
                (SELECT SUM(oi.quantity) FROM shop_order_items oi WHERE oi.order_id = o.id) as item_count
            FROM shop_orders o
            WHERE o.status IN ('processing', 'shipped')
            ORDER BY o.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        $outgoingOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (function_exists('decryptUserRows')) {
            $outgoingOrders = decryptUserRows($outgoingOrders);
        }
    } catch (PDOException $e) {
        error_log("Inventory outgoing fetch error: " . $e->getMessage());
    }
}
?>
<style>
.m-inv { padding: 0; font-family: Inter, sans-serif; padding-bottom: 100px; }
.m-inv-header { padding: 16px; }
.m-inv-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-inv-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }

/* Sticky tab navigation */
.m-inv-tabs {
    display: flex; position: sticky; top: 0; z-index: 10;
    background: #0A0A0F; border-bottom: 1px solid #2D2D3F;
    overflow-x: auto; -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
}
.m-inv-tabs::-webkit-scrollbar { display: none; }
.m-inv-tab {
    flex: 0 0 auto; text-align: center; padding: 12px 16px;
    font-size: 12px; font-weight: 600; color: #6B6B7B;
    border: none; background: none; cursor: pointer;
    border-bottom: 2px solid transparent; min-height: 44px;
    white-space: nowrap; text-decoration: none;
    display: flex; align-items: center; gap: 6px;
}
.m-inv-tab.m-active { color: #8B5CF6; border-bottom-color: #8B5CF6; }
.m-inv-tab i { font-size: 13px; }

/* Stats row */
.m-inv-stats { display: flex; gap: 8px; padding: 16px; overflow-x: auto; }
.m-inv-stat {
    flex: 1; min-width: 90px; background: #16161F;
    border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; text-align: center;
}
.m-inv-stat-val { display: block; font-size: 20px; font-weight: 800; color: #fff; }
.m-inv-stat-lbl { display: block; font-size: 10px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 2px; }
.m-inv-stat.m-stat-purple .m-inv-stat-val { color: #8B5CF6; }
.m-inv-stat.m-stat-green .m-inv-stat-val { color: #10B981; }
.m-inv-stat.m-stat-blue .m-inv-stat-val { color: #3B82F6; }

/* Content area */
.m-inv-content { padding: 0 16px; }

/* Product cards */
.m-inv-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-inv-card-header { display: flex; align-items: center; gap: 10px; margin-bottom: 10px; }
.m-inv-card-img {
    width: 44px; height: 44px; border-radius: 10px; object-fit: cover;
    background: #0A0A0F; border: 1px solid #2D2D3F; flex-shrink: 0;
}
.m-inv-card-img-placeholder {
    width: 44px; height: 44px; border-radius: 10px; background: #0A0A0F;
    border: 1px solid #2D2D3F; display: flex; align-items: center;
    justify-content: center; color: #3D3D4F; font-size: 18px; flex-shrink: 0;
}
.m-inv-card-info { flex: 1; min-width: 0; }
.m-inv-card-name {
    font-size: 14px; font-weight: 600; color: #fff; margin: 0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.m-inv-card-sku { font-size: 11px; color: #6B6B7B; margin: 1px 0 0; }
.m-inv-card-cat { font-size: 11px; color: #A8A8B8; margin: 1px 0 0; }
.m-inv-card-qty {
    font-size: 18px; font-weight: 800; color: #8B5CF6; text-align: right;
    flex-shrink: 0; min-width: 36px;
}

/* Size badges row */
.m-inv-sizes { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 10px; }
.m-inv-size {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 10px; font-size: 12px; font-weight: 600;
    border-radius: 6px; background: rgba(107,70,193,0.1);
    color: #8B5CF6; border: 1px solid rgba(107,70,193,0.2);
}
.m-inv-size-qty { color: #A8A8B8; font-weight: 400; }

/* Card footer with price/value */
.m-inv-card-footer {
    display: flex; justify-content: space-between; align-items: center;
    border-top: 1px solid #2D2D3F; padding-top: 10px; margin-top: 2px;
}
.m-inv-card-price { font-size: 13px; color: #A8A8B8; }
.m-inv-card-value { font-size: 13px; font-weight: 700; color: #8B5CF6; }

/* Card actions */
.m-inv-card-actions { display: flex; gap: 8px; margin-top: 10px; border-top: 1px solid #2D2D3F; padding-top: 10px; }
.m-inv-btn {
    flex: 1; display: flex; align-items: center; justify-content: center; gap: 4px;
    min-height: 44px; border: none; border-radius: 8px; font-size: 12px; font-weight: 600;
    cursor: pointer; font-family: Inter, sans-serif;
}
.m-inv-btn-primary { background: rgba(107,70,193,0.15); color: #8B5CF6; }
.m-inv-btn-green { background: rgba(16,185,129,0.15); color: #10B981; }
.m-inv-btn-blue { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-inv-btn-orange { background: rgba(245,158,11,0.15); color: #F59E0B; }

/* Incoming/outgoing cards */
.m-inv-shipment-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-inv-shipment-top { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px; }
.m-inv-shipment-product { font-size: 14px; font-weight: 600; color: #fff; }
.m-inv-shipment-size { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-inv-shipment-qty {
    font-size: 14px; font-weight: 700; padding: 4px 10px; border-radius: 6px;
}
.m-inv-shipment-qty.m-positive { background: rgba(16,185,129,0.15); color: #10B981; }
.m-inv-shipment-qty.m-negative { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-inv-shipment-meta { font-size: 11px; color: #6B6B7B; }
.m-inv-shipment-meta span { display: block; margin-top: 2px; }
.m-inv-shipment-ref { color: #A8A8B8 !important; }

/* Order cards */
.m-inv-order-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 10px;
}
.m-inv-order-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.m-inv-order-num { font-size: 15px; font-weight: 700; color: #fff; }
.m-inv-order-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 700;
    text-transform: uppercase; letter-spacing: 0.3px;
}
.m-inv-order-badge-processing { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-inv-order-badge-shipped { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-inv-order-customer { font-size: 13px; font-weight: 500; color: #E0E0E0; }
.m-inv-order-email { font-size: 11px; color: #6B6B7B; margin-top: 1px; }
.m-inv-order-items {
    font-size: 12px; color: #A8A8B8; margin: 8px 0;
    padding: 8px 10px; background: #0A0A0F; border-radius: 8px;
}
.m-inv-order-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.m-inv-order-date { font-size: 11px; color: #6B6B7B; }
.m-inv-order-tracking { font-size: 12px; color: #8B5CF6; font-weight: 500; }

/* Warning banner */
.m-inv-warning {
    background: rgba(234,179,8,0.1); border: 1px solid rgba(234,179,8,0.3);
    border-radius: 8px; padding: 12px; margin-bottom: 12px;
    display: flex; align-items: center; gap: 10px;
    font-size: 12px; color: #EAB308;
}
.m-inv-warning i { font-size: 16px; flex-shrink: 0; }

/* Empty state */
.m-inv-empty { text-align: center; padding: 40px 20px; color: #6B6B7B; font-size: 13px; }
.m-inv-empty i { font-size: 32px; display: block; margin-bottom: 10px; }

/* Bottom-sheet modal */
.m-inv-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 200; }
.m-inv-overlay.m-show { display: flex; align-items: flex-end; }
.m-inv-sheet {
    width: 100%; max-height: 90vh; background: #16161F;
    border-radius: 16px 16px 0 0; padding: 20px;
    overflow-y: auto; -webkit-overflow-scrolling: touch;
}
.m-inv-handle { width: 40px; height: 4px; background: #3D3D4F; border-radius: 2px; margin: 0 auto 16px; }
.m-inv-sheet-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0 0 16px; }
.m-inv-field { margin-bottom: 14px; }
.m-inv-field label {
    display: block; font-size: 12px; font-weight: 600; color: #A8A8B8; margin-bottom: 6px;
}
.m-inv-field input,
.m-inv-field select,
.m-inv-field textarea {
    width: 100%; padding: 10px 12px; border-radius: 10px;
    background: #0A0A0F; border: 1px solid #2D2D3F; color: #fff;
    font-size: 14px; font-family: Inter, sans-serif;
    min-height: 44px; box-sizing: border-box;
}
.m-inv-field textarea { min-height: 66px; resize: vertical; }
.m-inv-field input:focus,
.m-inv-field select:focus,
.m-inv-field textarea:focus { outline: none; border-color: #6B46C1; }
.m-inv-field-row { display: flex; gap: 10px; }
.m-inv-field-row .m-inv-field { flex: 1; }
.m-inv-modal-actions {
    display: flex; gap: 10px; margin-top: 16px; padding-bottom: env(safe-area-inset-bottom, 12px);
}
.m-inv-btn-cancel, .m-inv-btn-save {
    flex: 1; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600;
    border: none; cursor: pointer; min-height: 44px; font-family: Inter, sans-serif;
}
.m-inv-btn-cancel { background: #2D2D3F; color: #A8A8B8; }
.m-inv-btn-save { background: linear-gradient(135deg, #6B46C1, #8B5CF6); color: #fff; }
</style>

<div class="m-inv">
    <!-- Header -->
    <div class="m-inv-header">
        <h2 class="m-inv-title"><i class="fas fa-warehouse"></i> Inventory</h2>
        <p class="m-inv-sub">Stock, shipments &amp; orders</p>
    </div>

    <!-- Sticky Tab Navigation -->
    <div class="m-inv-tabs">
        <a href="?page=inventory_management&tab=in_store" class="m-inv-tab <?= $active_tab === 'in_store' ? 'm-active' : '' ?>">
            <i class="fas fa-store"></i> In-Store
        </a>
        <a href="?page=inventory_management&tab=warehouse" class="m-inv-tab <?= $active_tab === 'warehouse' ? 'm-active' : '' ?>">
            <i class="fas fa-boxes"></i> Warehouse
        </a>
        <a href="?page=inventory_management&tab=incoming" class="m-inv-tab <?= $active_tab === 'incoming' ? 'm-active' : '' ?>">
            <i class="fas fa-truck-loading"></i> Incoming
        </a>
        <a href="?page=inventory_management&tab=outgoing" class="m-inv-tab <?= $active_tab === 'outgoing' ? 'm-active' : '' ?>">
            <i class="fas fa-shipping-fast"></i> Outgoing
        </a>
    </div>

<?php if ($active_tab === 'in_store'): ?>
    <!-- ============ In-Store Inventory Tab ============ -->
    <div class="m-inv-stats">
        <div class="m-inv-stat m-stat-purple">
            <span class="m-inv-stat-val"><?= intval($inStoreStats['total_products']) ?></span>
            <span class="m-inv-stat-lbl">Products</span>
        </div>
        <div class="m-inv-stat m-stat-green">
            <span class="m-inv-stat-val"><?= intval($inStoreStats['total_units']) ?></span>
            <span class="m-inv-stat-lbl">Units</span>
        </div>
        <div class="m-inv-stat m-stat-blue">
            <span class="m-inv-stat-val">$<?= number_format($inStoreStats['total_value'], 0) ?></span>
            <span class="m-inv-stat-lbl">Value</span>
        </div>
    </div>

    <div class="m-inv-content">
        <?php if (empty($inStoreProducts)): ?>
            <div class="m-inv-empty">
                <i class="fas fa-box-open"></i>
                No in-store inventory found.
            </div>
        <?php else: ?>
            <?php foreach ($inStoreProducts as $product): ?>
            <div class="m-inv-card">
                <div class="m-inv-card-header">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $product['image_url'])) ?>" alt="" class="m-inv-card-img">
                    <?php else: ?>
                        <div class="m-inv-card-img-placeholder"><i class="fas fa-tshirt"></i></div>
                    <?php endif; ?>
                    <div class="m-inv-card-info">
                        <div class="m-inv-card-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="m-inv-card-sku"><?= htmlspecialchars($product['sku'] ?? '—') ?></div>
                        <?php if (!empty($product['category_name'])): ?>
                            <div class="m-inv-card-cat"><?= htmlspecialchars($product['category_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-inv-card-qty"><?= intval($product['total_qty']) ?></div>
                </div>
                <?php if (!empty($product['sizes'])): ?>
                <div class="m-inv-sizes">
                    <?php foreach ($product['sizes'] as $size): ?>
                        <span class="m-inv-size"><?= htmlspecialchars($size['size']) ?> <span class="m-inv-size-qty">×<?= intval($size['quantity']) ?></span></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="m-inv-card-footer">
                    <span class="m-inv-card-price">$<?= number_format($product['price'] ?? 0, 2) ?> each</span>
                    <span class="m-inv-card-value">$<?= number_format(($product['price'] ?? 0) * $product['total_qty'], 2) ?></span>
                </div>
                <div class="m-inv-card-actions">
                    <button class="m-inv-btn m-inv-btn-primary" onclick="mInvAdjustStock(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')"><i class="fas fa-sliders-h"></i> Adjust</button>
                    <?php if ($hasStockLocation): ?>
                    <button class="m-inv-btn m-inv-btn-green" onclick="mInvTransfer(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>', 'in_store')"><i class="fas fa-exchange-alt"></i> Transfer</button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab === 'warehouse'): ?>
    <!-- ============ Warehouse Inventory Tab ============ -->
    <div class="m-inv-stats">
        <div class="m-inv-stat m-stat-purple">
            <span class="m-inv-stat-val"><?= intval($warehouseStats['total_products']) ?></span>
            <span class="m-inv-stat-lbl">Products</span>
        </div>
        <div class="m-inv-stat m-stat-green">
            <span class="m-inv-stat-val"><?= intval($warehouseStats['total_units']) ?></span>
            <span class="m-inv-stat-lbl">Units</span>
        </div>
        <div class="m-inv-stat m-stat-blue">
            <span class="m-inv-stat-val">$<?= number_format($warehouseStats['total_value'], 0) ?></span>
            <span class="m-inv-stat-lbl">Value</span>
        </div>
    </div>

    <div class="m-inv-content">
        <?php if (!$hasStockLocation): ?>
        <div class="m-inv-warning">
            <i class="fas fa-exclamation-triangle"></i>
            <span>The <strong>stock_location</strong> column has not been added yet. Run the migration to enable warehouse tracking.</span>
        </div>
        <?php endif; ?>

        <?php if (empty($warehouseProducts)): ?>
            <div class="m-inv-empty">
                <i class="fas fa-box-open"></i>
                No warehouse inventory found.
            </div>
        <?php else: ?>
            <?php foreach ($warehouseProducts as $product): ?>
            <div class="m-inv-card">
                <div class="m-inv-card-header">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $product['image_url'])) ?>" alt="" class="m-inv-card-img">
                    <?php else: ?>
                        <div class="m-inv-card-img-placeholder"><i class="fas fa-tshirt"></i></div>
                    <?php endif; ?>
                    <div class="m-inv-card-info">
                        <div class="m-inv-card-name"><?= htmlspecialchars($product['name']) ?></div>
                        <div class="m-inv-card-sku"><?= htmlspecialchars($product['sku'] ?? '—') ?></div>
                        <?php if (!empty($product['category_name'])): ?>
                            <div class="m-inv-card-cat"><?= htmlspecialchars($product['category_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="m-inv-card-qty"><?= intval($product['total_qty']) ?></div>
                </div>
                <?php if (!empty($product['sizes'])): ?>
                <div class="m-inv-sizes">
                    <?php foreach ($product['sizes'] as $size): ?>
                        <span class="m-inv-size"><?= htmlspecialchars($size['size']) ?> <span class="m-inv-size-qty">×<?= intval($size['quantity']) ?></span></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <div class="m-inv-card-footer">
                    <span class="m-inv-card-price">$<?= number_format($product['price'] ?? 0, 2) ?> each</span>
                    <span class="m-inv-card-value">$<?= number_format(($product['price'] ?? 0) * $product['total_qty'], 2) ?></span>
                </div>
                <div class="m-inv-card-actions">
                    <button class="m-inv-btn m-inv-btn-primary" onclick="mInvAdjustStock(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>')"><i class="fas fa-sliders-h"></i> Adjust</button>
                    <button class="m-inv-btn m-inv-btn-green" onclick="mInvTransfer(<?= (int)$product['id'] ?>, '<?= htmlspecialchars($product['name'], ENT_QUOTES) ?>', 'warehouse')"><i class="fas fa-exchange-alt"></i> Transfer</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab === 'incoming'): ?>
    <!-- ============ Incoming Packages Tab ============ -->
    <div class="m-inv-stats">
        <div class="m-inv-stat m-stat-purple">
            <span class="m-inv-stat-val"><?= count($incomingPackages) ?></span>
            <span class="m-inv-stat-lbl">Shipments</span>
        </div>
        <div class="m-inv-stat m-stat-green">
            <span class="m-inv-stat-val"><?= $totalUnitsReceived ?></span>
            <span class="m-inv-stat-lbl">Units Received</span>
        </div>
    </div>

    <div class="m-inv-content">
        <?php if (empty($incomingPackages)): ?>
            <div class="m-inv-empty">
                <i class="fas fa-truck"></i>
                No incoming shipments recorded.
            </div>
        <?php else: ?>
            <?php foreach ($incomingPackages as $pkg):
                $qtyChange = intval($pkg['quantity_change']);
                $isPositive = $qtyChange >= 0;
            ?>
            <div class="m-inv-shipment-card">
                <div class="m-inv-shipment-top">
                    <div>
                        <div class="m-inv-shipment-product"><?= htmlspecialchars($pkg['product_name'] ?? '—') ?></div>
                        <div class="m-inv-shipment-size"><?= htmlspecialchars($pkg['size_name'] ?? '—') ?></div>
                    </div>
                    <span class="m-inv-shipment-qty <?= $isPositive ? 'm-positive' : 'm-negative' ?>">
                        <?= $isPositive ? '+' : '' ?><?= $qtyChange ?>
                    </span>
                </div>
                <div class="m-inv-shipment-meta">
                    <span><?= date('M j, Y · g:i A', strtotime($pkg['created_at'])) ?></span>
                    <?php if (!empty($pkg['reference'])): ?>
                        <span class="m-inv-shipment-ref">Ref: <?= htmlspecialchars($pkg['reference']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($pkg['notes'])): ?>
                        <span><?= htmlspecialchars($pkg['notes']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php elseif ($active_tab === 'outgoing'): ?>
    <!-- ============ Outgoing Packages Tab ============ -->
    <?php
    $shippedCount = count(array_filter($outgoingOrders, function($o) { return $o['status'] === 'shipped'; }));
    $processingCount = count(array_filter($outgoingOrders, function($o) { return $o['status'] === 'processing'; }));
    ?>
    <div class="m-inv-stats">
        <div class="m-inv-stat m-stat-purple">
            <span class="m-inv-stat-val"><?= count($outgoingOrders) ?></span>
            <span class="m-inv-stat-lbl">Active</span>
        </div>
        <div class="m-inv-stat m-stat-green">
            <span class="m-inv-stat-val"><?= $shippedCount ?></span>
            <span class="m-inv-stat-lbl">Shipped</span>
        </div>
        <div class="m-inv-stat m-stat-blue">
            <span class="m-inv-stat-val"><?= $processingCount ?></span>
            <span class="m-inv-stat-lbl">Processing</span>
        </div>
    </div>

    <div class="m-inv-content">
        <?php if (empty($outgoingOrders)): ?>
            <div class="m-inv-empty">
                <i class="fas fa-box"></i>
                No processing or shipped orders found.
            </div>
        <?php else: ?>
            <?php foreach ($outgoingOrders as $order):
                $badgeClass = $order['status'] === 'shipped' ? 'm-inv-order-badge-shipped' : 'm-inv-order-badge-processing';
            ?>
            <div class="m-inv-order-card">
                <div class="m-inv-order-top">
                    <span class="m-inv-order-num">#<?= htmlspecialchars($order['order_number']) ?></span>
                    <span class="m-inv-order-badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span>
                </div>
                <div class="m-inv-order-customer">
                    <?= htmlspecialchars(implode(' ', array_filter([$order['customer_first_name'] ?? '', $order['customer_last_name'] ?? '']))) ?>
                </div>
                <?php if (!empty($order['customer_email'])): ?>
                    <div class="m-inv-order-email"><?= htmlspecialchars($order['customer_email']) ?></div>
                <?php endif; ?>
                <div class="m-inv-order-items">
                    <?= htmlspecialchars($order['items_summary'] ?? ($order['item_count'] ?? 0) . ' item(s)') ?>
                </div>
                <div class="m-inv-order-footer">
                    <span class="m-inv-order-date"><?= date('M j, Y · g:i A', strtotime($order['created_at'])) ?></span>
                    <?php if (!empty($order['tracking_number'])): ?>
                        <span class="m-inv-order-tracking"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($order['tracking_number']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="m-inv-card-actions">
                    <button class="m-inv-btn m-inv-btn-blue" onclick="mInvUpdateFulfillment(<?= (int)$order['id'] ?>, '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>', '<?= htmlspecialchars($order['status'], ENT_QUOTES) ?>')"><i class="fas fa-edit"></i> Update</button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php endif; ?>
</div><!-- .m-inv -->

<!-- Adjust Stock Bottom-Sheet -->
<div class="m-inv-overlay" id="mInvAdjustModal">
    <div class="m-inv-sheet">
        <div class="m-inv-handle"></div>
        <div class="m-inv-sheet-title" id="mInvAdjustTitle">Adjust Stock</div>
        <form method="POST" action="process_merchandise_products.php" id="mInvAdjustForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="stock_audit">
            <input type="hidden" name="product_id" id="mInvAdjustProductId" value="">
            <div id="mInvAdjustSizes"></div>
            <div class="m-inv-field">
                <label for="mInvAdjustNotes">Notes</label>
                <textarea name="audit_notes" id="mInvAdjustNotes" placeholder="Reason for adjustment..." rows="2"></textarea>
            </div>
            <div class="m-inv-modal-actions">
                <button type="button" class="m-inv-btn-cancel" onclick="mInvCloseModal('mInvAdjustModal')">Cancel</button>
                <button type="submit" class="m-inv-btn-save" id="mInvAdjustSaveBtn">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Transfer Stock Bottom-Sheet -->
<div class="m-inv-overlay" id="mInvTransferModal">
    <div class="m-inv-sheet">
        <div class="m-inv-handle"></div>
        <div class="m-inv-sheet-title" id="mInvTransferTitle">Transfer Stock</div>
        <form method="POST" action="process_merchandise_products.php" id="mInvTransferForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="transfer_stock">
            <input type="hidden" name="product_id" id="mInvTransferProductId" value="">
            <input type="hidden" name="from_location" id="mInvTransferFrom" value="">
            <div id="mInvTransferSizes"></div>
            <div class="m-inv-modal-actions">
                <button type="button" class="m-inv-btn-cancel" onclick="mInvCloseModal('mInvTransferModal')">Cancel</button>
                <button type="submit" class="m-inv-btn-save" id="mInvTransferSaveBtn">Transfer</button>
            </div>
        </form>
    </div>
</div>

<!-- Update Fulfillment Bottom-Sheet -->
<div class="m-inv-overlay" id="mInvFulfillModal">
    <div class="m-inv-sheet">
        <div class="m-inv-handle"></div>
        <div class="m-inv-sheet-title" id="mInvFulfillTitle">Update Fulfillment</div>
        <form method="POST" action="process_shop_checkout.php" id="mInvFulfillForm">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="action" value="update_shipment">
            <input type="hidden" name="order_id" id="mInvFulfillOrderId" value="">
            <div class="m-inv-field">
                <label for="mInvFulfillStatus">Status</label>
                <select name="status" id="mInvFulfillStatus">
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                </select>
            </div>
            <div class="m-inv-field">
                <label for="mInvFulfillCarrier">Carrier</label>
                <input type="text" name="carrier" id="mInvFulfillCarrier" placeholder="e.g., Canada Post, UPS">
            </div>
            <div class="m-inv-field">
                <label for="mInvFulfillTracking">Tracking Number</label>
                <input type="text" name="tracking_number" id="mInvFulfillTracking" placeholder="Tracking #">
            </div>
            <div class="m-inv-field">
                <label for="mInvFulfillNotes">Notes</label>
                <textarea name="fulfillment_notes" id="mInvFulfillNotes" placeholder="Fulfillment notes..." rows="2"></textarea>
            </div>
            <div class="m-inv-modal-actions">
                <button type="button" class="m-inv-btn-cancel" onclick="mInvCloseModal('mInvFulfillModal')">Cancel</button>
                <button type="submit" class="m-inv-btn-save" id="mInvFulfillSaveBtn">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    // Close modal helper
    window.mInvCloseModal = function(id) {
        var el = document.getElementById(id);
        if (el) el.classList.remove('m-show');
    };

    // Close on overlay tap
    document.querySelectorAll('.m-inv-overlay').forEach(function(overlay) {
        overlay.addEventListener('click', function(e) {
            if (e.target === overlay) overlay.classList.remove('m-show');
        });
    });

    // Adjust Stock
    window.mInvAdjustStock = function(productId, productName) {
        document.getElementById('mInvAdjustTitle').textContent = 'Adjust: ' + productName;
        document.getElementById('mInvAdjustProductId').value = productId;
        document.getElementById('mInvAdjustNotes').value = '';
        var container = document.getElementById('mInvAdjustSizes');
        container.innerHTML = '<div style="text-align:center;padding:16px;color:#6B6B7B;">Loading sizes...</div>';
        document.getElementById('mInvAdjustModal').classList.add('m-show');

        fetch('process_merchandise_products.php?action=get_sizes&product_id=' + productId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.sizes && data.sizes.length) {
                var html = '';
                data.sizes.forEach(function(s) {
                    html += '<div class="m-inv-field-row">' +
                        '<div class="m-inv-field" style="flex:1;"><label>' + escHtml(s.size) + ' (current: ' + s.quantity + ')</label>' +
                        '<input type="hidden" name="size_ids[]" value="' + s.id + '">' +
                        '<input type="number" name="actual_quantities[]" value="' + s.quantity + '" min="0" inputmode="numeric" style="min-height:44px;"></div></div>';
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="text-align:center;padding:16px;color:#6B6B7B;">No sizes found for this product.</div>';
            }
        })
        .catch(function() {
            container.innerHTML = '<div style="text-align:center;padding:16px;color:#EF4444;">Failed to load sizes.</div>';
        });
    };

    // Transfer Stock
    window.mInvTransfer = function(productId, productName, fromLocation) {
        var toLocation = fromLocation === 'in_store' ? 'Warehouse' : 'In-Store';
        document.getElementById('mInvTransferTitle').textContent = 'Transfer to ' + toLocation;
        document.getElementById('mInvTransferProductId').value = productId;
        document.getElementById('mInvTransferFrom').value = fromLocation;
        var container = document.getElementById('mInvTransferSizes');
        container.innerHTML = '<div style="text-align:center;padding:16px;color:#6B6B7B;">Loading sizes...</div>';
        document.getElementById('mInvTransferModal').classList.add('m-show');

        fetch('process_merchandise_products.php?action=get_sizes&product_id=' + productId, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.sizes && data.sizes.length) {
                var html = '';
                data.sizes.forEach(function(s) {
                    html += '<div class="m-inv-field-row">' +
                        '<div class="m-inv-field" style="flex:1;"><label>' + escHtml(s.size) + ' (avail: ' + s.quantity + ')</label>' +
                        '<input type="hidden" name="size_ids[]" value="' + s.id + '">' +
                        '<input type="number" name="transfer_quantities[]" value="0" min="0" max="' + s.quantity + '" inputmode="numeric" style="min-height:44px;"></div></div>';
                });
                container.innerHTML = html;
            } else {
                container.innerHTML = '<div style="text-align:center;padding:16px;color:#6B6B7B;">No sizes found.</div>';
            }
        })
        .catch(function() {
            container.innerHTML = '<div style="text-align:center;padding:16px;color:#EF4444;">Failed to load sizes.</div>';
        });
    };

    // Update Fulfillment
    window.mInvUpdateFulfillment = function(orderId, orderNumber, currentStatus) {
        document.getElementById('mInvFulfillTitle').textContent = 'Order #' + orderNumber;
        document.getElementById('mInvFulfillOrderId').value = orderId;
        document.getElementById('mInvFulfillStatus').value = currentStatus;
        document.getElementById('mInvFulfillCarrier').value = '';
        document.getElementById('mInvFulfillTracking').value = '';
        document.getElementById('mInvFulfillNotes').value = '';
        document.getElementById('mInvFulfillModal').classList.add('m-show');
    };

    // AJAX form submissions
    ['mInvAdjustForm', 'mInvTransferForm', 'mInvFulfillForm'].forEach(function(formId) {
        var form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = form.querySelector('.m-inv-btn-save');
            var origText = btn.textContent;
            btn.textContent = 'Saving...';
            btn.disabled = true;

            fetch(form.getAttribute('action'), {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                btn.textContent = origText;
                btn.disabled = false;
                if (data.success) {
                    form.closest('.m-inv-overlay').classList.remove('m-show');
                    if (typeof persistToast === 'function') {
                        persistToast(data.message || 'Updated successfully', 'success');
                    }
                    location.reload();
                } else {
                    if (typeof showToast === 'function') {
                        showToast('Error: ' + (data.message || 'Operation failed'), 'error');
                    }
                }
            })
            .catch(function() {
                btn.textContent = origText;
                btn.disabled = false;
                if (typeof showToast === 'function') {
                    showToast('An error occurred. Please try again.', 'error');
                }
            });
        });
    });

    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str || ''));
        return div.innerHTML;
    }
})();
</script>
