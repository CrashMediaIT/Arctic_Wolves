<?php
/**
 * Shop Orders View
 * Manage online shop orders
 */

// Check access
if (!$canAccessPOS && !$isAdmin) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not have permission to access this page.</p></div>';
    return;
}

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    header('Content-Type: application/json');
    
    $orderId = intval($_POST['order_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    // Verify CSRF token
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token']);
        exit();
    }
    
    $validStatuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
    
    if ($orderId > 0 && in_array($newStatus, $validStatuses)) {
        try {
            $stmt = $pdo->prepare("UPDATE shop_orders SET status = ? WHERE id = ?");
            $stmt->execute([$newStatus, $orderId]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    }
    exit();
}

// Fetch orders
$statusFilter = $_GET['status'] ?? '';
$searchQuery = trim($_GET['search'] ?? '');
$currentPage = max(1, intval($_GET['pg'] ?? 1));
$perPage = 20;
$offset = ($currentPage - 1) * $perPage;

$where = ["1=1"];
$params = [];

if ($statusFilter) {
    $where[] = "so.status = ?";
    $params[] = $statusFilter;
}

if ($searchQuery) {
    $where[] = "(so.order_number LIKE ? OR so.customer_email LIKE ? OR so.customer_first_name LIKE ? OR so.customer_last_name LIKE ?)";
    $searchTerm = "%$searchQuery%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
}

$whereClause = implode(' AND ', $where);

try {
    // Count total
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM shop_orders so WHERE $whereClause");
    $countStmt->execute($params);
    $totalOrders = $countStmt->fetchColumn();
    $totalPages = ceil($totalOrders / $perPage);
    
    // Fetch orders with parameterized LIMIT/OFFSET
    $stmt = $pdo->prepare("
        SELECT so.*, 
               (SELECT COUNT(*) FROM shop_order_items WHERE order_id = so.id) as item_count
        FROM shop_orders so
        WHERE $whereClause
        ORDER BY so.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $params[] = $perPage;
    $params[] = $offset;
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $orders = decryptUserRows($orders);
    
    // Get order stats
    $statsStmt = $pdo->query("
        SELECT 
            COUNT(*) as total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count,
            SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_count,
            SUM(CASE WHEN status = 'shipped' THEN 1 ELSE 0 END) as shipped_count,
            SUM(CASE WHEN payment_status = 'paid' THEN total ELSE 0 END) as total_revenue
        FROM shop_orders
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Shop orders fetch error: " . $e->getMessage());
    $orders = [];
    $stats = [];
}

// Check if being loaded as a tab in Finance Dashboard
$in_finance_dashboard = (isset($tab) && in_array($tab, ['pos_transactions', 'shop_orders']));
?>

<style>
.data-table { width: 100%; border-collapse: collapse; }
.data-table thead { background: var(--bg-main); }
.data-table th { padding: 16px 20px; text-align: left; font-size: 11px; font-weight: 700; color: var(--text-dim); text-transform: uppercase; letter-spacing: 0.8px; border-bottom: 2px solid var(--border); }
.data-table td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; color: var(--text-white); }
.data-table tbody tr { transition: all 0.3s; }
.data-table tbody tr:hover { background: rgba(107, 70, 193, 0.05); }
.select-compact { padding: 6px 10px; font-size: 12px; width: auto; height: auto; }
</style>

<?php if (!$in_finance_dashboard): ?>
<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-shopping-bag"></i> Shop Orders</h1>
        <p class="page-description">Manage online merchandise orders</p>
    </div>
    <div class="page-header-stats">
        <div class="header-stat">
            <span class="stat-value"><?= $stats['total_orders'] ?? 0 ?></span>
            <span class="stat-label">Total Orders</span>
        </div>
        <div class="header-stat stat-warning">
            <span class="stat-value"><?= $stats['pending_count'] ?? 0 ?></span>
            <span class="stat-label">Pending</span>
        </div>
        <div class="header-stat stat-success">
            <span class="stat-value"><?= $stats['processing_count'] ?? 0 ?></span>
            <span class="stat-label">Processing</span>
        </div>
        <div class="header-stat">
            <span class="stat-value">$<?= number_format($stats['total_revenue'] ?? 0, 0) ?></span>
            <span class="stat-label">Revenue</span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Stats Row for tab view -->
<?php if ($in_finance_dashboard): ?>
<div class="page-header-stats" style="margin-bottom: 24px;">
    <div class="header-stat">
        <span class="stat-value"><?= $stats['total_orders'] ?? 0 ?></span>
        <span class="stat-label">Total Orders</span>
    </div>
    <div class="header-stat stat-warning">
        <span class="stat-value"><?= $stats['pending_count'] ?? 0 ?></span>
        <span class="stat-label">Pending</span>
    </div>
    <div class="header-stat stat-success">
        <span class="stat-value"><?= $stats['processing_count'] ?? 0 ?></span>
        <span class="stat-label">Processing</span>
    </div>
    <div class="header-stat stat-success">
        <span class="stat-value">$<?= number_format($stats['total_revenue'] ?? 0, 0) ?></span>
        <span class="stat-label">Revenue</span>
    </div>
</div>
<?php endif; ?>

<?php
// Determine base URL for filters
$baseUrl = $in_finance_dashboard ? '?page=finance_dashboard&tab=shop_orders' : '?page=shop_orders';
?>

<div class="filter-box">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Orders</div>
    <div class="filter-box-content">
        <form method="GET" action="" class="filter-row">
            <?php if ($in_finance_dashboard): ?>
            <input type="hidden" name="page" value="finance_dashboard">
            <input type="hidden" name="tab" value="shop_orders">
            <?php else: ?>
            <input type="hidden" name="page" value="shop_orders">
            <?php endif; ?>
            <div class="filter-field">
                <label>Status</label>
                <select name="status" class="form-select">
                    <option value="">All Statuses</option>
                    <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                    <option value="paid" <?= $statusFilter === 'paid' ? 'selected' : '' ?>>Paid</option>
                    <option value="processing" <?= $statusFilter === 'processing' ? 'selected' : '' ?>>Processing</option>
                    <option value="shipped" <?= $statusFilter === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                    <option value="delivered" <?= $statusFilter === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                    <option value="cancelled" <?= $statusFilter === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    <option value="refunded" <?= $statusFilter === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                </select>
            </div>
            <div class="filter-field">
                <label>Search</label>
                <input type="text" name="search" placeholder="Order #, email, or name..." value="<?= htmlspecialchars($searchQuery) ?>" class="form-input">
            </div>
            <div class="filter-field filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="<?= $baseUrl ?>" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-shopping-bag"></i> Orders<?php if (!empty($statusFilter)): ?> — <?= ucfirst(htmlspecialchars($statusFilter)) ?><?php endif; ?></h3>
    </div>
    
    <div class="card-body">
        <?php if (empty($orders)): ?>
            <div class="empty-state" style="text-align: center; padding: 60px 20px;">
                <i class="fas fa-shopping-bag" style="font-size: 48px; color: var(--text-dim); margin-bottom: 16px;"></i>
                <p style="color: var(--text-dim);">No orders found.</p>
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
                            <th>Payment</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>
                                    <span style="font-weight: 600;"><?= htmlspecialchars($order['order_number']) ?></span>
                                </td>
                                <td>
                                    <div style="font-size: 13px;">
                                        <?= date('M j, Y', strtotime($order['created_at'])) ?>
                                        <div style="color: var(--text-dim); font-size: 11px;"><?= date('g:i A', strtotime($order['created_at'])) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-weight: 500;"><?= htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></div>
                                    <div style="color: var(--text-dim); font-size: 12px;"><?= htmlspecialchars($order['customer_email']) ?></div>
                                </td>
                                <td><?= $order['item_count'] ?></td>
                                <td style="font-weight: 600; color: var(--primary);">$<?= number_format($order['total'], 2) ?></td>
                                <td>
                                    <?php
                                    $paymentBadgeMap = [
                                        'pending' => 'badge-warning',
                                        'paid' => 'badge-success',
                                        'failed' => 'badge-danger',
                                        'refunded' => 'badge-primary',
                                        'partially_refunded' => 'badge-info'
                                    ];
                                    $paymentBadge = $paymentBadgeMap[$order['payment_status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?= $paymentBadge ?>">
                                        <?= ucfirst($order['payment_status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <select class="form-select select-compact" onchange="updateOrderStatus(<?= $order['id'] ?>, this.value)">
                                        <?php 
                                        $statuses = ['pending', 'paid', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded'];
                                        foreach ($statuses as $status): 
                                        ?>
                                            <option value="<?= $status ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button class="btn btn-sm btn-secondary" onclick="viewOrderDetails(<?= $order['id'] ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if (in_array($order['status'], ['paid', 'processing'])): ?>
                                        <button class="btn btn-sm btn-primary" onclick="openShipOrder(<?= $order['id'] ?>, '<?= htmlspecialchars($order['order_number'], ENT_QUOTES) ?>')" title="Ship Order">
                                            <i class="fas fa-shipping-fast"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination" style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= $baseUrl ?>&<?= http_build_query(array_merge($_GET, ['pg' => $currentPage - 1])) ?>" class="page-link" style="padding: 8px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: #fff; text-decoration: none;">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                        <a href="<?= $baseUrl ?>&<?= http_build_query(array_merge($_GET, ['pg' => $i])) ?>" class="page-link <?= $i === $currentPage ? 'active' : '' ?>" style="padding: 8px 14px; background: <?= $i === $currentPage ? 'var(--primary)' : 'var(--bg)' ?>; border: 1px solid <?= $i === $currentPage ? 'var(--primary)' : 'var(--border)' ?>; border-radius: 6px; color: #fff; text-decoration: none;">
                            <?= $i ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= $baseUrl ?>&<?= http_build_query(array_merge($_GET, ['pg' => $currentPage + 1])) ?>" class="page-link" style="padding: 8px 14px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; color: #fff; text-decoration: none;">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
                <p style="color: var(--text-dim); margin-bottom: 16px;">Enter shipping details to mark this order as shipped. The customer can use the tracking information to follow their package.</p>
                
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
                    <small style="color: var(--text-dim);">Direct link to track the package (optional)</small>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Fulfillment Notes</label>
                    <textarea name="fulfillment_notes" class="form-textarea" rows="2" placeholder="Optional notes about this shipment"></textarea>
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
function updateOrderStatus(orderId, newStatus) {
    fetch('dashboard.php?page=shop_orders', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'update_status=1&order_id=' + orderId + '&new_status=' + encodeURIComponent(newStatus) + '&csrf_token=' + encodeURIComponent('<?= $_SESSION['csrf_token'] ?? '' ?>')
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            persistToast(data.message || 'Failed to update status', 'error');
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        location.reload();
    });
}

function viewOrderDetails(orderId) {
    const modal = document.getElementById('order-details-modal');
    const content = document.getElementById('order-details-content');
    
    content.innerHTML = '<div style="text-align: center; padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i></div>';
    modal.classList.add('active');
    
    fetch('ajax_get_order_details.php?id=' + orderId)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<p style="color: var(--error); text-align: center;">Failed to load order details</p>';
        });
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
    formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
    
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
</script>
