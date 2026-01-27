<?php
/**
 * AJAX endpoint to get shop order details
 */
session_start();
require_once __DIR__ . '/db_config.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo '<p style="color: #ef4444;">Unauthorized</p>';
    exit();
}

// Check access
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'front_desk_staff'])) {
    http_response_code(403);
    echo '<p style="color: #ef4444;">Access denied</p>';
    exit();
}

$orderId = intval($_GET['id'] ?? 0);

if ($orderId <= 0) {
    echo '<p style="color: #ef4444;">Invalid order ID</p>';
    exit();
}

try {
    // Fetch order
    $stmt = $pdo->prepare("SELECT * FROM shop_orders WHERE id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        echo '<p style="color: #ef4444;">Order not found</p>';
        exit();
    }
    
    // Fetch order items
    $itemsStmt = $pdo->prepare("SELECT * FROM shop_order_items WHERE order_id = ?");
    $itemsStmt->execute([$orderId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Order details fetch error: " . $e->getMessage());
    echo '<p style="color: #ef4444;">Failed to load order details</p>';
    exit();
}
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
    <!-- Order Info -->
    <div>
        <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Order Information</h4>
        <div style="background: var(--bg); border-radius: 10px; padding: 15px;">
            <p style="margin-bottom: 8px;"><strong>Order #:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
            <p style="margin-bottom: 8px;"><strong>Date:</strong> <?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
            <p style="margin-bottom: 8px;"><strong>Status:</strong> 
                <span style="padding: 3px 8px; border-radius: 10px; font-size: 11px; background: rgba(107, 70, 193, 0.2); color: var(--primary-light);">
                    <?= ucfirst($order['status']) ?>
                </span>
            </p>
            <p><strong>Payment:</strong> 
                <span style="padding: 3px 8px; border-radius: 10px; font-size: 11px; background: <?= $order['payment_status'] === 'paid' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(245, 158, 11, 0.2)' ?>; color: <?= $order['payment_status'] === 'paid' ? '#10b981' : '#f59e0b' ?>;">
                    <?= ucfirst($order['payment_status']) ?>
                </span>
            </p>
        </div>
    </div>
    
    <!-- Customer Info -->
    <div>
        <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Customer</h4>
        <div style="background: var(--bg); border-radius: 10px; padding: 15px;">
            <p style="margin-bottom: 8px;"><strong><?= htmlspecialchars($order['customer_first_name'] . ' ' . $order['customer_last_name']) ?></strong></p>
            <p style="margin-bottom: 8px; color: var(--text-dim);"><?= htmlspecialchars($order['customer_email']) ?></p>
            <?php if ($order['customer_phone']): ?>
                <p style="color: var(--text-dim);"><?= htmlspecialchars($order['customer_phone']) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Billing Address -->
<div style="margin-bottom: 25px;">
    <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Billing Address</h4>
    <div style="background: var(--bg); border-radius: 10px; padding: 15px;">
        <p><?= htmlspecialchars($order['billing_address_line1']) ?></p>
        <?php if ($order['billing_address_line2']): ?>
            <p><?= htmlspecialchars($order['billing_address_line2']) ?></p>
        <?php endif; ?>
        <p><?= htmlspecialchars($order['billing_city']) ?>, <?= htmlspecialchars($order['billing_state']) ?> <?= htmlspecialchars($order['billing_postal_code']) ?></p>
        <p><?= htmlspecialchars($order['billing_country']) ?></p>
    </div>
</div>

<!-- Order Items -->
<div style="margin-bottom: 25px;">
    <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Order Items</h4>
    <div style="background: var(--bg); border-radius: 10px; padding: 15px;">
        <?php foreach ($items as $item): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border);">
                <div>
                    <p style="font-weight: 600;"><?= htmlspecialchars($item['product_name']) ?></p>
                    <?php if ($item['size']): ?>
                        <p style="font-size: 12px; color: var(--text-dim);">Size: <?= htmlspecialchars($item['size']) ?></p>
                    <?php endif; ?>
                    <p style="font-size: 12px; color: var(--text-dim);">Qty: <?= $item['quantity'] ?> × $<?= number_format($item['unit_price'], 2) ?></p>
                </div>
                <div style="font-weight: 700; color: var(--primary);">
                    $<?= number_format($item['total_price'], 2) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Order Totals -->
<div style="background: var(--bg); border-radius: 10px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <span style="color: var(--text-dim);">Subtotal</span>
        <span>$<?= number_format($order['subtotal'], 2) ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <span style="color: var(--text-dim);">Tax</span>
        <span>$<?= number_format($order['tax_amount'], 2) ?></span>
    </div>
    <?php if ($order['discount_amount'] > 0): ?>
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span style="color: var(--text-dim);">Discount</span>
            <span style="color: #10b981;">-$<?= number_format($order['discount_amount'], 2) ?></span>
        </div>
    <?php endif; ?>
    <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 1px solid var(--border); font-size: 20px; font-weight: 700;">
        <span>Total</span>
        <span style="color: var(--primary);">$<?= number_format($order['total'], 2) ?></span>
    </div>
</div>

<?php if ($order['notes']): ?>
<div style="margin-top: 20px; padding: 15px; background: rgba(245, 158, 11, 0.1); border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.3);">
    <p style="font-weight: 600; margin-bottom: 5px;"><i class="fas fa-sticky-note" style="color: #f59e0b;"></i> Notes</p>
    <p style="color: var(--text-dim);"><?= nl2br(htmlspecialchars($order['notes'])) ?></p>
</div>
<?php endif; ?>
