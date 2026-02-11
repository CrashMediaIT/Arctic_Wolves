<?php
/**
 * POS Online Orders View
 * Allows front desk staff and admins to view, fulfill, and ship online orders
 * Includes integration with Stallion Express shipping fulfillment service for creating and printing shipping labels
 */

// Check access - require authentication in dashboard context
if (!isset($_SESSION['user_id']) || !$canAccessPOS) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS access denied from unauthorized IP', ['ip' => $_SERVER['REMOTE_ADDR'] ?? '', 'page' => 'pos_online_orders']);
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>POS access is not available from this location. Please contact an administrator.</p></div>';
    return;
}

// Handle AJAX status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit;
    }
    
    $orderId = intval($_POST['order_id']);
    $newStatus = $_POST['new_status'] ?? '';
    $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    
    if (!in_array($newStatus, $validStatuses)) {
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        exit;
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE shop_orders SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $orderId]);
        echo json_encode(['success' => true]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error']);
    }
    exit;
}

// Check if Stallion Express is configured
$stallionEnabled = false;
try {
    $stallionCheck = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'stallion_enabled'");
    $stallionCheck->execute();
    $stallionEnabled = ($stallionCheck->fetchColumn() === '1');
} catch (PDOException $e) {
    // Stallion not configured
}

// Fetch orders with filters
$statusFilter = $_GET['status'] ?? '';
$searchQuery = $_GET['search'] ?? '';

try {
    $sql = "
        SELECT o.*, 
            (SELECT COUNT(*) FROM shop_order_items WHERE order_id = o.id) as item_count,
            (SELECT GROUP_CONCAT(CONCAT(product_name, IF(size IS NOT NULL, CONCAT(' (', size, ')'), ''), ' x', quantity) SEPARATOR ', ')
             FROM shop_order_items WHERE order_id = o.id) as items_summary,
            sl.id as label_id,
            sl.tracking_number as stallion_tracking,
            sl.label_url as stallion_label_url,
            sl.status as label_status
        FROM shop_orders o
        LEFT JOIN stallion_shipping_labels sl ON sl.order_id = o.id
        WHERE 1=1
    ";
    $params = [];
    
    if (!empty($statusFilter)) {
        $sql .= " AND o.status = ?";
        $params[] = $statusFilter;
    }
    
    if (!empty($searchQuery)) {
        // Search on order_number and customer_email (non-encrypted fields)
        // Customer name fields may be encrypted and cannot be searched via LIKE
        $sql .= " AND (o.order_number LIKE ? OR o.customer_email LIKE ?)";
        $search = '%' . $searchQuery . '%';
        $params = array_merge($params, [$search, $search]);
    }
    
    $sql .= " ORDER BY o.created_at DESC LIMIT 50";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Decrypt customer data if encryption is available
    if (function_exists('decryptUserRows')) {
        $orders = decryptUserRows($orders);
    }
    
    // Get stats
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as ready_to_ship,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_count
        FROM shop_orders
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("POS orders fetch error: " . $e->getMessage());
    $orders = [];
    $stats = ['total_orders' => 0, 'ready_to_ship' => 0, 'processing_count' => 0, 'shipped_count' => 0];
}
?>

<style>
.pos-orders-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 16px;
}
.pos-orders-header .header-left {
    display: flex;
    align-items: center;
    gap: 16px;
}
.pos-orders-header .header-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    color: #fff;
}
.pos-orders-header h1 {
    font-size: 24px;
    font-weight: 800;
    margin: 0;
}
.pos-orders-header .header-subtitle {
    font-size: 13px;
    color: var(--text-dim);
    margin: 4px 0 0 0;
}
.pos-stats {
    display: flex;
    gap: 20px;
}
.pos-stat {
    text-align: center;
}
.pos-stat .stat-value {
    font-size: 22px;
    font-weight: 800;
    color: var(--text-white);
}
.pos-stat .stat-label {
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.pos-filters {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 20px;
    align-items: center;
}
.pos-filter-btn {
    padding: 8px 16px;
    background: var(--bg);
    border: 1px solid var(--border);
    border-radius: 20px;
    color: var(--text-dim);
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: 0.2s;
    cursor: pointer;
}
.pos-filter-btn:hover,
.pos-filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}
.order-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 12px;
    transition: 0.2s;
}
.order-card:hover {
    border-color: var(--primary);
}
.order-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    flex-wrap: wrap;
    gap: 10px;
}
.order-number {
    font-weight: 700;
    font-size: 16px;
    color: var(--text-white);
}
.order-date {
    font-size: 12px;
    color: var(--text-dim);
}
.order-card-body {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
.order-detail-label {
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.3px;
    margin-bottom: 4px;
}
.order-detail-value {
    font-size: 14px;
    color: var(--text-white);
}
.order-card-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    padding-top: 12px;
    border-top: 1px solid var(--border);
}
.order-action-btn {
    padding: 8px 16px;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: 0.2s;
}
.order-action-btn.primary {
    background: var(--primary);
    color: #fff;
}
.order-action-btn.primary:hover {
    opacity: 0.85;
}
.order-action-btn.secondary {
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary-light);
}
.order-action-btn.secondary:hover {
    background: rgba(107, 70, 193, 0.2);
}
.order-action-btn.success {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}
.order-action-btn.success:hover {
    background: rgba(16, 185, 129, 0.2);
}
.order-action-btn.info {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}
.order-action-btn.info:hover {
    background: rgba(59, 130, 246, 0.2);
}
.status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    display: inline-block;
}
.status-pending { background: rgba(245, 158, 11, 0.15); color: #f59e0b; }
.status-paid { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-processing { background: rgba(59, 130, 246, 0.15); color: #3b82f6; }
.status-shipped { background: rgba(139, 92, 246, 0.15); color: #8b5cf6; }
.status-delivered { background: rgba(16, 185, 129, 0.15); color: #10b981; }
.status-cancelled { background: rgba(239, 68, 68, 0.15); color: #ef4444; }
.status-refunded { background: rgba(107, 114, 128, 0.15); color: #6b7280; }
</style>

<!-- Page Header -->
<div class="pos-orders-header">
    <div class="header-left">
        <div class="header-icon">
            <i class="fas fa-shipping-fast"></i>
        </div>
        <div>
            <h1>Online Orders</h1>
            <p class="header-subtitle">Fulfill orders and print shipping labels</p>
        </div>
    </div>
    <div class="pos-stats">
        <div class="pos-stat">
            <div class="stat-value" style="color: #10b981;"><?= intval($stats['ready_to_ship']) ?></div>
            <div class="stat-label">Ready to Ship</div>
        </div>
        <div class="pos-stat">
            <div class="stat-value" style="color: #3b82f6;"><?= intval($stats['processing_count']) ?></div>
            <div class="stat-label">Processing</div>
        </div>
        <div class="pos-stat">
            <div class="stat-value" style="color: #8b5cf6;"><?= intval($stats['shipped_count']) ?></div>
            <div class="stat-label">Shipped</div>
        </div>
        <div class="pos-stat">
            <div class="stat-value"><?= intval($stats['total_orders']) ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="pos-filters">
    <a href="?page=pos_online_orders" class="pos-filter-btn <?= empty($statusFilter) ? 'active' : '' ?>">All Orders</a>
    <a href="?page=pos_online_orders&status=paid" class="pos-filter-btn <?= $statusFilter === 'paid' ? 'active' : '' ?>">
        <i class="fas fa-check-circle"></i> Ready to Ship
    </a>
    <a href="?page=pos_online_orders&status=processing" class="pos-filter-btn <?= $statusFilter === 'processing' ? 'active' : '' ?>">
        <i class="fas fa-spinner"></i> Processing
    </a>
    <a href="?page=pos_online_orders&status=shipped" class="pos-filter-btn <?= $statusFilter === 'shipped' ? 'active' : '' ?>">
        <i class="fas fa-truck"></i> Shipped
    </a>
    <a href="?page=pos_online_orders&status=delivered" class="pos-filter-btn <?= $statusFilter === 'delivered' ? 'active' : '' ?>">
        <i class="fas fa-box-open"></i> Delivered
    </a>
    
    <div style="flex-grow: 1;"></div>
    
    <form method="GET" style="display: flex; gap: 8px;">
        <input type="hidden" name="page" value="pos_online_orders">
        <?php if ($statusFilter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($statusFilter) ?>"><?php endif; ?>
        <input type="text" name="search" placeholder="Search orders..." value="<?= htmlspecialchars($searchQuery) ?>" class="form-input" style="width: 200px; padding: 8px 14px; font-size: 13px;">
        <button type="submit" class="order-action-btn primary"><i class="fas fa-search"></i></button>
    </form>
</div>

<!-- Orders List -->
<?php if (empty($orders)): ?>
    <div style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-box" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
        <p style="color: var(--text-dim); font-size: 16px;">No orders found.</p>
        <?php if (!empty($statusFilter) || !empty($searchQuery)): ?>
            <a href="?page=pos_online_orders" style="color: var(--primary-light); margin-top: 8px; display: inline-block;">Clear filters</a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <?php foreach ($orders as $order): ?>
        <div class="order-card">
            <div class="order-card-header">
                <div>
                    <span class="order-number">#<?= htmlspecialchars($order['order_number']) ?></span>
                    <span class="order-date" style="margin-left: 12px;"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></span>
                </div>
                <div style="display: flex; gap: 8px; align-items: center;">
                    <span class="status-badge status-<?= htmlspecialchars($order['status']) ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span>
                    <?php if (!empty($order['stallion_tracking'])): ?>
                        <span class="status-badge" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;">
                            <i class="fas fa-tag"></i> <?= htmlspecialchars($order['stallion_tracking']) ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="order-card-body">
                <div>
                    <div class="order-detail-label">Customer</div>
                    <div class="order-detail-value">
                        <?= htmlspecialchars(implode(' ', array_filter([$order['customer_first_name'] ?? '', $order['customer_last_name'] ?? '']))) ?>
                        <div style="font-size: 12px; color: var(--text-dim);"><?= htmlspecialchars($order['customer_email'] ?? '') ?></div>
                    </div>
                </div>
                <div>
                    <div class="order-detail-label">Items</div>
                    <div class="order-detail-value" style="font-size: 13px;">
                        <?= htmlspecialchars($order['items_summary'] ?? $order['item_count'] . ' item(s)') ?>
                    </div>
                </div>
                <div>
                    <div class="order-detail-label">Total</div>
                    <div class="order-detail-value" style="font-weight: 700; color: var(--primary-light);">$<?= number_format($order['total'] ?? 0, 2) ?></div>
                </div>
                <div>
                    <div class="order-detail-label">Shipping Address</div>
                    <div class="order-detail-value" style="font-size: 13px;">
                        <?php
                        $addr = $order['shipping_address_line1'] ?? $order['billing_address_line1'] ?? '';
                        $city = $order['shipping_city'] ?? $order['billing_city'] ?? '';
                        $prov = $order['shipping_state'] ?? $order['billing_state'] ?? '';
                        $postal = $order['shipping_postal_code'] ?? $order['billing_postal_code'] ?? '';
                        echo htmlspecialchars(implode(', ', array_filter([$addr, $city, $prov, $postal])));
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="order-card-actions">
                <button class="order-action-btn secondary" onclick="viewOrderDetails(<?= intval($order['id']) ?>)">
                    <i class="fas fa-eye"></i> Details
                </button>
                
                <?php if (in_array($order['status'], ['paid', 'processing'])): ?>
                    <?php if ($stallionEnabled): ?>
                        <?php if (empty($order['label_id'])): ?>
                            <button class="order-action-btn primary" onclick="openCreateLabel(<?= intval($order['id']) ?>, '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>')">
                                <i class="fas fa-tag"></i> Create Shipping Label
                            </button>
                        <?php else: ?>
                            <button class="order-action-btn info" onclick="printLabel('<?= htmlspecialchars($order['stallion_label_url'] ?? '', ENT_QUOTES) ?>', <?= intval($order['label_id']) ?>)">
                                <i class="fas fa-print"></i> Print Label
                            </button>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <button class="order-action-btn success" onclick="openShipOrder(<?= intval($order['id']) ?>, '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>')">
                        <i class="fas fa-shipping-fast"></i> Ship Order
                    </button>
                <?php endif; ?>
                
                <?php if (!empty($order['stallion_label_url']) && $order['status'] === 'shipped'): ?>
                    <button class="order-action-btn info" onclick="printLabel('<?= htmlspecialchars($order['stallion_label_url'] ?? '', ENT_QUOTES) ?>', <?= intval($order['label_id']) ?>)">
                        <i class="fas fa-print"></i> Reprint Label
                    </button>
                <?php endif; ?>
                
                <select class="form-input" style="padding: 6px 10px; font-size: 12px; max-width: 140px;" onchange="updateOrderStatus(<?= intval($order['id']) ?>, this.value)">
                    <?php 
                    $statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
                    foreach ($statuses as $status): 
                    ?>
                        <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<!-- Order Details Modal -->
<div id="order-details-modal" class="modal">
    <div class="modal-content" style="max-width: 700px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-shopping-bag"></i> Order Details</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('order-details-modal')">&times;</button>
        </div>
        <div class="modal-body" id="order-details-content">
            <div style="text-align: center; padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i>
            </div>
        </div>
    </div>
</div>

<!-- Create Stallion Shipping Label Modal -->
<div id="create-label-modal" class="modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-tag"></i> Create Shipping Label - <span id="label-order-number"></span></h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('create-label-modal')">&times;</button>
        </div>
        <form id="create-label-form" onsubmit="submitCreateLabel(event)">
            <input type="hidden" name="order_id" id="label-order-id">
            <div class="modal-body">
                <div style="background: rgba(107, 70, 193, 0.1); border: 1px solid rgba(107, 70, 193, 0.3); border-radius: 8px; padding: 12px; margin-bottom: 16px;">
                    <i class="fas fa-info-circle" style="color: var(--primary-light);"></i>
                    <span style="color: var(--text-dim); font-size: 13px;">A shipping label will be created through Stallion Express, which will automatically find the best carrier and rate for this shipment. You can override the package details below.</span>
                </div>
                
                <h4 style="color: var(--text-white); margin-bottom: 12px; font-size: 14px;"><i class="fas fa-box"></i> Package Details (Override Defaults)</h4>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                    <div class="form-group">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" name="weight" class="form-input" step="0.01" min="0.01" placeholder="Default">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Length (cm)</label>
                        <input type="number" name="length" class="form-input" step="0.1" min="1" placeholder="Default">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Width (cm)</label>
                        <input type="number" name="width" class="form-input" step="0.1" min="1" placeholder="Default">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Height (cm)</label>
                        <input type="number" name="height" class="form-input" step="0.1" min="1" placeholder="Default">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('create-label-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-tag"></i> Create Label</button>
            </div>
        </form>
    </div>
</div>

<!-- Ship Order Modal -->
<div id="ship-order-modal" class="modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-shipping-fast"></i> Ship Order - <span id="ship-order-number"></span></h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('ship-order-modal')">&times;</button>
        </div>
        <form id="ship-order-form" onsubmit="submitShipOrder(event)">
            <input type="hidden" name="order_id" id="ship-order-id">
            <div class="modal-body">
                <p style="color: var(--text-dim); margin-bottom: 16px;">Enter shipping details to mark this order as shipped.</p>
                
                <div class="form-group">
                    <label class="form-label">Shipping Carrier / Fulfillment *</label>
                    <select name="shipping_carrier" class="form-input" required>
                        <option value="">-- Select Option --</option>
                        <option value="Stallion Express">Stallion Express (Multi-Carrier)</option>
                        <option value="Canada Post">Canada Post</option>
                        <option value="UPS">UPS</option>
                        <option value="FedEx">FedEx</option>
                        <option value="Purolator">Purolator</option>
                        <option value="DHL">DHL</option>
                        <option value="Pickup at Session">Pickup at Session</option>
                        <option value="Local Pickup">Local Pickup</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tracking Number</label>
                    <input type="text" name="tracking_number" class="form-input" placeholder="e.g., 1Z999AA10123456784">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Tracking URL</label>
                    <input type="url" name="tracking_url" class="form-input" placeholder="https://...">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Fulfillment Notes</label>
                    <textarea name="fulfillment_notes" class="form-textarea" rows="2" placeholder="Optional notes"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('ship-order-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-shipping-fast"></i> Mark as Shipped</button>
            </div>
        </form>
    </div>
</div>

<script>
var csrfToken = '<?= $_SESSION['csrf_token'] ?? '' ?>';

function updateOrderStatus(orderId, newStatus) {
    fetch('dashboard.php?page=pos_online_orders', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'update_status=1&order_id=' + orderId + '&new_status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Failed to update status: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating order status');
    });
}

function viewOrderDetails(orderId) {
    var modal = document.getElementById('order-details-modal');
    var content = document.getElementById('order-details-content');
    
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i></div>';
    modal.classList.add('active');
    
    fetch('ajax_get_order_details.php?id=' + orderId)
        .then(response => response.text())
        .then(html => { content.innerHTML = html; })
        .catch(error => {
            content.innerHTML = '<p style="color: #ef4444; text-align: center;">Failed to load order details</p>';
        });
}

function openCreateLabel(orderId, orderNumber) {
    document.getElementById('create-label-form').reset();
    document.getElementById('label-order-id').value = orderId;
    document.getElementById('label-order-number').textContent = '#' + orderNumber;
    document.getElementById('create-label-modal').classList.add('active');
}

function submitCreateLabel(e) {
    e.preventDefault();
    var form = e.target;
    var formData = new FormData(form);
    formData.append('csrf_token', csrfToken);
    
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating Label...';
    submitBtn.disabled = true;
    
    fetch('process_shop_checkout.php?action=create_stallion_label', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            closeModal('create-label-modal');
            alert('Shipping label created successfully!\nTracking: ' + (data.tracking_number || 'N/A'));
            
            // If label URL is returned, offer to print
            if (data.label_url) {
                if (confirm('Would you like to print the shipping label now?')) {
                    window.open(data.label_url, '_blank');
                }
            }
            
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to create shipping label'));
        }
    })
    .catch(error => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        console.error('Error:', error);
        alert('An error occurred while creating the label. Please try again.');
    });
}

function printLabel(labelUrl, labelId) {
    if (!labelUrl) {
        alert('No label URL available for this shipment.');
        return;
    }
    
    // Open label in new window for printing
    window.open(labelUrl, '_blank');
    
    // Update label status to printed
    var formData = new FormData();
    formData.append('label_id', labelId);
    formData.append('csrf_token', csrfToken);
    
    fetch('process_shop_checkout.php?action=mark_label_printed', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).catch(function() {});
}

function openShipOrder(orderId, orderNumber) {
    document.getElementById('ship-order-form').reset();
    document.getElementById('ship-order-id').value = orderId;
    document.getElementById('ship-order-number').textContent = '#' + orderNumber;
    document.getElementById('ship-order-modal').classList.add('active');
}

function submitShipOrder(e) {
    e.preventDefault();
    var form = e.target;
    var formData = new FormData(form);
    formData.append('csrf_token', csrfToken);
    
    var submitBtn = form.querySelector('button[type="submit"]');
    var originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    submitBtn.disabled = true;
    
    fetch('process_shop_checkout.php?action=ship_order', {
        method: 'POST',
        body: formData,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(response => response.json())
    .then(data => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        
        if (data.success) {
            closeModal('ship-order-modal');
            alert(data.message || 'Order shipped successfully!');
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to ship order'));
        }
    })
    .catch(error => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal.active').forEach(function(modal) {
            closeModal(modal.id);
        });
    }
});
</script>
