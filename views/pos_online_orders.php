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
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: var(--bg-main); }
.data-table th { padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 2px solid var(--border); }
.data-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text-white); }
.data-table tbody tr { transition: all 0.3s; }
.data-table tbody tr:hover { background: rgba(107, 70, 193, 0.05); }
.select-compact { padding: 6px 10px; font-size: 12px; width: auto; max-width: 120px; height: auto; }
</style>

<!-- Page Header -->
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-shipping-fast"></i> Online Orders</h1>
        <p class="page-description">Fulfill orders and print shipping labels</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat stat-success">
            <span class="stat-value"><?= intval($stats['ready_to_ship']) ?></span>
            <span class="stat-label">Ready to Ship</span>
        </div>
        <div class="header-stat stat-info">
            <span class="stat-value"><?= intval($stats['processing_count']) ?></span>
            <span class="stat-label">Processing</span>
        </div>
        <div class="header-stat stat-primary">
            <span class="stat-value"><?= intval($stats['shipped_count']) ?></span>
            <span class="stat-label">Shipped</span>
        </div>
        <div class="header-stat">
            <span class="stat-value"><?= intval($stats['total_orders']) ?></span>
            <span class="stat-label">Total</span>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="filter-box">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Orders</div>
    <div class="filter-box-content">
        <form method="GET" action="" class="filter-row">
            <input type="hidden" name="page" value="pos_online_orders">
            <div class="filter-field">
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Ready to Ship</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="filter-field">
                <label>Search</label>
                <input type="text" name="search" placeholder="Order # or email..." value="<?= htmlspecialchars($searchQuery) ?>" class="form-input">
            </div>
            <div class="filter-field filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="?page=pos_online_orders" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<!-- Orders List -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-shopping-bag"></i> Orders<?php if (!empty($statusFilter)): ?> — <?= ucfirst(htmlspecialchars($statusFilter)) ?><?php endif; ?></h3>
    </div>
    <div class="card-body">
<?php if (empty($orders)): ?>
        <div class="empty-state" style="text-align: center; padding: 60px 20px;">
            <i class="fas fa-box" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
            <p style="color: var(--text-dim);">No orders found.</p>
            <?php if (!empty($statusFilter) || !empty($searchQuery)): ?>
                <a href="?page=pos_online_orders" class="btn btn-secondary" style="margin-top: 12px;"><i class="fas fa-times"></i> Clear Filters</a>
            <?php endif; ?>
        </div>
<?php else: ?>
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Shipping</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $statusBadgeMap = [
                            'pending' => 'badge-warning',
                            'paid' => 'badge-success',
                            'processing' => 'badge-info',
                            'shipped' => 'badge-primary',
                            'delivered' => 'badge-success',
                            'cancelled' => 'badge-danger',
                            'refunded' => 'badge-secondary',
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
                            <td style="font-weight: 600; color: var(--primary-light);">$<?= number_format($order['total'] ?? 0, 2) ?></td>
                            <td style="font-size: 13px;">
                                <?php
                                $addr = $order['shipping_address_line1'] ?? $order['billing_address_line1'] ?? '';
                                $city = $order['shipping_city'] ?? $order['billing_city'] ?? '';
                                $prov = $order['shipping_state'] ?? $order['billing_state'] ?? '';
                                $postal = $order['shipping_postal_code'] ?? $order['billing_postal_code'] ?? '';
                                echo htmlspecialchars(implode(', ', array_filter([$addr, $city, $prov, $postal])));
                                ?>
                                <?php if (!empty($order['stallion_tracking'])): ?>
                                    <div><span class="badge badge-primary"><i class="fas fa-tag"></i> <?= htmlspecialchars($order['stallion_tracking']) ?></span></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= $badgeClass ?>"><?= ucfirst(htmlspecialchars($order['status'])) ?></span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 4px; flex-wrap: wrap; align-items: center;">
                                    <button class="btn btn-sm btn-secondary" onclick="viewOrderDetails(<?= intval($order['id']) ?>)" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if (in_array($order['status'], ['paid', 'processing'])): ?>
                                        <?php if ($stallionEnabled): ?>
                                            <?php if (empty($order['label_id'])): ?>
                                                <button class="btn btn-sm btn-primary" onclick="openCreateLabel(<?= intval($order['id']) ?>, '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>')" title="Create Shipping Label">
                                                    <i class="fas fa-tag"></i>
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-sm btn-secondary" onclick="printLabel('<?= htmlspecialchars($order['stallion_label_url'] ?? '', ENT_QUOTES) ?>', <?= intval($order['label_id']) ?>)" title="Print Label">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <button class="btn btn-sm btn-success" onclick="openShipOrder(<?= intval($order['id']) ?>, '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>')" title="Ship Order">
                                            <i class="fas fa-shipping-fast"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($order['stallion_label_url']) && $order['status'] === 'shipped'): ?>
                                        <button class="btn btn-sm btn-secondary" onclick="printLabel('<?= htmlspecialchars($order['stallion_label_url'] ?? '', ENT_QUOTES) ?>', <?= intval($order['label_id']) ?>)" title="Reprint Label">
                                            <i class="fas fa-print"></i>
                                        </button>
                                    <?php endif; ?>
                                    
                                    <select class="form-select select-compact" onchange="updateOrderStatus(<?= intval($order['id']) ?>, this.value)">
                                        <?php 
                                        $statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
                                        foreach ($statuses as $status): 
                                        ?>
                                            <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
<?php endif; ?>
    </div>
</div>

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
                    <select name="shipping_carrier" class="form-select" required>
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
            persistToast(data.message || 'Operation completed successfully', 'success');
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
            content.innerHTML = '<p style="color: var(--error); text-align: center;">Failed to load order details</p>';
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
            persistToast(data.message || 'Order shipped successfully!', 'success');
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
