<?php
/**
 * Inventory Management View
 * In-store and warehouse inventory tracking, incoming shipments, and outgoing orders
 */

// Check access - require authentication in dashboard context
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS access denied from unauthorized IP', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'page' => 'inventory_management']);
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>POS access is not available from this location. Please contact an administrator.</p></div>';
    return;
}

// Active tab
$active_tab = $_GET['tab'] ?? 'in_store';

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

        // Group by product
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
            // No stock_location column — warehouse tab is empty
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
            // Only keep products that have warehouse sizes
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

// Pre-calculate incoming stats
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
.inv-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.inv-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.inv-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.inv-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.inv-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
.inv-stat-cards {
    display: flex;
    gap: 20px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}
.inv-stat-card {
    flex: 1;
    min-width: 160px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.inv-stat-card .stat-value {
    display: block;
    font-size: 28px;
    font-weight: 800;
    color: var(--text-white);
}
.inv-stat-card .stat-label {
    display: block;
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}
.inv-stat-card.stat-primary .stat-value { color: var(--primary-light); }
.inv-stat-card.stat-success .stat-value { color: #10b981; }
.inv-stat-card.stat-info .stat-value { color: #3b82f6; }
.inv-data-table { width: 100%; border-collapse: collapse; }
.inv-data-table thead { background: var(--bg-main); }
.inv-data-table th { padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 2px solid var(--border); }
.inv-data-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text-white); }
.inv-data-table tbody tr { transition: all 0.3s; }
.inv-data-table tbody tr:hover { background: rgba(107, 70, 193, 0.05); }
.inv-size-badge {
    display: inline-block;
    padding: 4px 10px;
    margin: 2px 4px 2px 0;
    font-size: 12px;
    font-weight: 600;
    border-radius: 6px;
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary-light);
    border: 1px solid rgba(107, 70, 193, 0.2);
}
.inv-size-badge .qty { color: var(--text-dim); font-weight: 400; margin-left: 4px; }
.inv-product-img {
    width: 40px;
    height: 40px;
    border-radius: 8px;
    object-fit: cover;
    background: var(--bg-secondary);
    border: 1px solid var(--border);
}
</style>

<!-- Page Header -->
<div class="inv-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-warehouse"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Inventory Management</h1>
            <p class="page-description">Track in-store and warehouse stock, shipments, and outgoing orders</p>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="page-tabs">
    <a href="?page=inventory_management&tab=in_store" class="page-tab <?= $active_tab === 'in_store' ? 'active' : '' ?>">
        <i class="fas fa-store"></i> In-Store Inventory
    </a>
    <a href="?page=inventory_management&tab=warehouse" class="page-tab <?= $active_tab === 'warehouse' ? 'active' : '' ?>">
        <i class="fas fa-boxes"></i> Warehouse Inventory
    </a>
    <a href="?page=inventory_management&tab=incoming" class="page-tab <?= $active_tab === 'incoming' ? 'active' : '' ?>">
        <i class="fas fa-truck-loading"></i> Incoming Packages
    </a>
    <a href="?page=inventory_management&tab=outgoing" class="page-tab <?= $active_tab === 'outgoing' ? 'active' : '' ?>">
        <i class="fas fa-shipping-fast"></i> Outgoing Packages
    </a>
</div>

<div class="page-tab-content">

<?php if ($active_tab === 'in_store'): ?>
<!-- ============ In-Store Inventory Tab ============ -->
<div class="inv-stat-cards">
    <div class="inv-stat-card stat-primary">
        <span class="stat-value"><?= intval($inStoreStats['total_products']) ?></span>
        <span class="stat-label">Products</span>
    </div>
    <div class="inv-stat-card stat-success">
        <span class="stat-value"><?= intval($inStoreStats['total_units']) ?></span>
        <span class="stat-label">In-Store Units</span>
    </div>
    <div class="inv-stat-card stat-info">
        <span class="stat-value">$<?= number_format($inStoreStats['total_value'], 0) ?></span>
        <span class="stat-label">In-Store Value</span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-store"></i> In-Store Stock</h3>
    </div>
    <div class="card-body">
        <?php if (empty($inStoreProducts)): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                <p style="color: var(--text-dim);">No in-store inventory found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="inv-data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Sizes &amp; Quantities</th>
                            <th>Total Qty</th>
                            <th>Price</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($inStoreProducts as $product): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="" class="inv-product-img">
                                    <?php endif; ?>
                                    <span style="font-weight: 600;"><?= htmlspecialchars($product['name']) ?></span>
                                </div>
                            </td>
                            <td style="font-size: 13px; color: var(--text-dim);"><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                            <td style="font-size: 13px;"><?= htmlspecialchars($product['category_name'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($product['sizes'])): ?>
                                    <?php foreach ($product['sizes'] as $size): ?>
                                        <span class="inv-size-badge"><?= htmlspecialchars($size['size']) ?><span class="qty">×<?= intval($size['quantity']) ?></span></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 13px;">No sizes</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600;"><?= intval($product['total_qty']) ?></td>
                            <td>$<?= number_format($product['price'] ?? 0, 2) ?></td>
                            <td style="font-weight: 600; color: var(--primary-light);">$<?= number_format(($product['price'] ?? 0) * $product['total_qty'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'warehouse'): ?>
<!-- ============ Warehouse Inventory Tab ============ -->
<div class="inv-stat-cards">
    <div class="inv-stat-card stat-primary">
        <span class="stat-value"><?= intval($warehouseStats['total_products']) ?></span>
        <span class="stat-label">Products</span>
    </div>
    <div class="inv-stat-card stat-success">
        <span class="stat-value"><?= intval($warehouseStats['total_units']) ?></span>
        <span class="stat-label">Warehouse Units</span>
    </div>
    <div class="inv-stat-card stat-info">
        <span class="stat-value">$<?= number_format($warehouseStats['total_value'], 0) ?></span>
        <span class="stat-label">Warehouse Value</span>
    </div>
</div>

<?php if (!$hasStockLocation): ?>
<div style="background: rgba(234, 179, 8, 0.1); border: 1px solid rgba(234, 179, 8, 0.3); border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-triangle" style="color: #eab308; font-size: 20px;"></i>
    <span style="color: #eab308; font-size: 13px;">The <code>stock_location</code> column has not been added to the database yet. Run the migration to enable warehouse inventory tracking.</span>
</div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-boxes"></i> Warehouse Stock</h3>
    </div>
    <div class="card-body">
        <?php if (empty($warehouseProducts)): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-box-open" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                <p style="color: var(--text-dim);">No warehouse inventory found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="inv-data-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Category</th>
                            <th>Sizes &amp; Quantities</th>
                            <th>Total Qty</th>
                            <th>Price</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($warehouseProducts as $product): ?>
                        <tr>
                            <td>
                                <div style="display: flex; align-items: center; gap: 10px;">
                                    <?php if (!empty($product['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($product['image_url']) ?>" alt="" class="inv-product-img">
                                    <?php endif; ?>
                                    <span style="font-weight: 600;"><?= htmlspecialchars($product['name']) ?></span>
                                </div>
                            </td>
                            <td style="font-size: 13px; color: var(--text-dim);"><?= htmlspecialchars($product['sku'] ?? '—') ?></td>
                            <td style="font-size: 13px;"><?= htmlspecialchars($product['category_name'] ?? '—') ?></td>
                            <td>
                                <?php if (!empty($product['sizes'])): ?>
                                    <?php foreach ($product['sizes'] as $size): ?>
                                        <span class="inv-size-badge"><?= htmlspecialchars($size['size']) ?><span class="qty">×<?= intval($size['quantity']) ?></span></span>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-dim); font-size: 13px;">No sizes</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 600;"><?= intval($product['total_qty']) ?></td>
                            <td>$<?= number_format($product['price'] ?? 0, 2) ?></td>
                            <td style="font-weight: 600; color: var(--primary-light);">$<?= number_format(($product['price'] ?? 0) * $product['total_qty'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'incoming'): ?>
<!-- ============ Incoming Packages Tab ============ -->
<div class="inv-stat-cards">
    <div class="inv-stat-card stat-primary">
        <span class="stat-value"><?= count($incomingPackages) ?></span>
        <span class="stat-label">Recent Shipments</span>
    </div>
    <div class="inv-stat-card stat-success">
        <span class="stat-value"><?= $totalUnitsReceived ?></span>
        <span class="stat-label">Total Units Received</span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-truck-loading"></i> Incoming Shipments</h3>
    </div>
    <div class="card-body">
        <?php if (empty($incomingPackages)): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-truck" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                <p style="color: var(--text-dim);">No incoming shipments recorded.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="inv-data-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Product</th>
                            <th>Size</th>
                            <th>Qty Change</th>
                            <th>Reference</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($incomingPackages as $pkg): ?>
                        <tr>
                            <td>
                                <div style="font-size: 13px;">
                                    <?= date('M j, Y', strtotime($pkg['created_at'])) ?>
                                    <div style="color: var(--text-dim); font-size: 11px;"><?= date('g:i A', strtotime($pkg['created_at'])) ?></div>
                                </div>
                            </td>
                            <td style="font-weight: 500;"><?= htmlspecialchars($pkg['product_name'] ?? '—') ?></td>
                            <td><?= htmlspecialchars($pkg['size_name'] ?? '—') ?></td>
                            <td>
                                <?php
                                $qtyChange = intval($pkg['quantity_change']);
                                $changeColor = $qtyChange >= 0 ? '#10b981' : '#ef4444';
                                $changePrefix = $qtyChange >= 0 ? '+' : '';
                                ?>
                                <span style="font-weight: 600; color: <?= $changeColor ?>;"><?= $changePrefix . $qtyChange ?></span>
                            </td>
                            <td style="font-size: 13px; color: var(--text-dim);"><?= htmlspecialchars($pkg['reference'] ?? '—') ?></td>
                            <td style="font-size: 13px; color: var(--text-dim);"><?= htmlspecialchars($pkg['notes'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($active_tab === 'outgoing'): ?>
<!-- ============ Outgoing Packages Tab ============ -->
<div class="inv-stat-cards">
    <div class="inv-stat-card stat-primary">
        <span class="stat-value"><?= count($outgoingOrders) ?></span>
        <span class="stat-label">Active Orders</span>
    </div>
    <div class="inv-stat-card stat-success">
        <span class="stat-value"><?= count(array_filter($outgoingOrders, function($o) { return $o['status'] === 'shipped'; })) ?></span>
        <span class="stat-label">Shipped</span>
    </div>
    <div class="inv-stat-card stat-info">
        <span class="stat-value"><?= count(array_filter($outgoingOrders, function($o) { return $o['status'] === 'processing'; })) ?></span>
        <span class="stat-label">Processing</span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-shipping-fast"></i> Outgoing Orders</h3>
    </div>
    <div class="card-body">
        <?php if (empty($outgoingOrders)): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-box" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                <p style="color: var(--text-dim);">No processing or shipped orders found.</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="inv-data-table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Status</th>
                            <th>Tracking</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($outgoingOrders as $order): ?>
                        <?php
                        $statusBadgeMap = [
                            'processing' => 'badge-info',
                            'shipped' => 'badge-primary',
                        ];
                        $badgeClass = $statusBadgeMap[$order['status']] ?? 'badge-secondary';
                        ?>
                        <tr>
                            <td><span style="font-weight: 600;">#<?= htmlspecialchars($order['order_number']) ?></span></td>
                            <td>
                                <div style="font-size: 13px;">
                                    <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                    <div style="color: var(--text-dim); font-size: 11px;"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
                                </div>
                            </td>
                            <td>
                                <div style="font-weight: 500;"><?= htmlspecialchars(implode(' ', array_filter([$order['customer_first_name'] ?? '', $order['customer_last_name'] ?? '']))) ?></div>
                                <div style="color: var(--text-dim); font-size: 12px;"><?= htmlspecialchars($order['customer_email'] ?? '') ?></div>
                            </td>
                            <td style="font-size: 13px;"><?= htmlspecialchars($order['items_summary'] ?? $order['item_count'] . ' item(s)') ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span></td>
                            <td style="font-size: 13px;">
                                <?php if (!empty($order['tracking_number'])): ?>
                                    <span style="color: var(--primary-light); font-weight: 500;"><?= htmlspecialchars($order['tracking_number']) ?></span>
                                <?php else: ?>
                                    <span style="color: var(--text-dim);">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php endif; ?>

</div><!-- .page-tab-content -->
