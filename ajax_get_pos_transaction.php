<?php
/**
 * AJAX endpoint to get POS transaction details
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

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

// Check IP whitelist for POS access (admins exempt)
if (!checkPOSIPAccess($pdo, $userRole)) {
    logSecurityEvent('pos_ip_blocked', 'POS transaction access denied from unauthorized IP', ['ip' => getClientIP()]);
    http_response_code(403);
    echo '<p style="color: #ef4444;">POS access is not available from this location</p>';
    exit();
}

$transactionId = intval($_GET['id'] ?? 0);

if ($transactionId <= 0) {
    echo '<p style="color: #ef4444;">Invalid transaction ID</p>';
    exit();
}

try {
    // Fetch transaction
    $stmt = $pdo->prepare("
        SELECT pt.*, u.first_name as staff_first_name, u.last_name as staff_last_name
        FROM pos_transactions pt
        LEFT JOIN users u ON pt.staff_id = u.id
        WHERE pt.id = ?
    ");
    $stmt->execute([$transactionId]);
    $trans = $stmt->fetch(PDO::FETCH_ASSOC);
    $trans = decryptUserRow($trans);
    if ($trans) {
        $trans['staff_name'] = (!empty($trans['staff_first_name'])) ? $trans['staff_first_name'] . ' ' . $trans['staff_last_name'] : null;
    }
    
    if (!$trans) {
        echo '<p style="color: #ef4444;">Transaction not found</p>';
        exit();
    }
    
    // Fetch transaction items
    $itemsStmt = $pdo->prepare("SELECT * FROM pos_transaction_items WHERE transaction_id = ?");
    $itemsStmt->execute([$transactionId]);
    $items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Transaction details fetch error: " . $e->getMessage());
    echo '<p style="color: #ef4444;">Failed to load transaction details</p>';
    exit();
}

$paymentIcons = ['card' => 'fa-credit-card', 'cash' => 'fa-money-bill', 'mixed' => 'fa-coins'];
$paymentColors = ['card' => '#3b82f6', 'cash' => '#10b981', 'mixed' => '#f59e0b'];
$icon = $paymentIcons[$trans['payment_method']] ?? 'fa-dollar-sign';
$color = $paymentColors[$trans['payment_method']] ?? '#6b7280';
?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
    <!-- Transaction Info -->
    <div>
        <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Transaction</h4>
        <div style="background: var(--bg); border-radius: 10px; padding: 15px;">
            <p style="margin-bottom: 8px;"><strong>Transaction #:</strong></p>
            <p style="font-family: monospace; margin-bottom: 12px;"><?= htmlspecialchars($trans['transaction_number']) ?></p>
            <p style="margin-bottom: 8px;"><strong>Date/Time:</strong></p>
            <p style="color: var(--text-dim);"><?= date('M j, Y g:i:s A', strtotime($trans['created_at'])) ?></p>
        </div>
    </div>
    
    <!-- Staff & Payment Info -->
    <div>
        <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Details</h4>
        <div style="background: var(--bg); border-radius: 10px; padding: 15px;">
            <p style="margin-bottom: 8px;"><strong>Staff:</strong> <?= htmlspecialchars($trans['staff_name'] ?? 'Unknown') ?></p>
            <p style="margin-bottom: 8px;"><strong>Payment:</strong> 
                <span style="display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?= $color ?>20; color: <?= $color ?>;">
                    <i class="fas <?= $icon ?>"></i>
                    <?= ucfirst($trans['payment_method']) ?>
                </span>
            </p>
            <p><strong>Status:</strong> 
                <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?= $trans['status'] === 'completed' ? '#10b98120' : '#f59e0b20' ?>; color: <?= $trans['status'] === 'completed' ? '#10b981' : '#f59e0b' ?>;">
                    <?= ucfirst($trans['status']) ?>
                </span>
            </p>
        </div>
    </div>
</div>

<!-- Items -->
<div style="margin-bottom: 25px;">
    <h4 style="font-size: 14px; color: var(--text-dim); margin-bottom: 12px; text-transform: uppercase;">Items Sold</h4>
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

<!-- Totals -->
<div style="background: var(--bg); border-radius: 10px; padding: 20px;">
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <span style="color: var(--text-dim);">Subtotal</span>
        <span>$<?= number_format($trans['subtotal'], 2) ?></span>
    </div>
    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
        <span style="color: var(--text-dim);">Tax</span>
        <span>$<?= number_format($trans['tax_amount'], 2) ?></span>
    </div>
    <?php if ($trans['discount_amount'] > 0): ?>
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
            <span style="color: var(--text-dim);">Discount</span>
            <span style="color: #10b981;">-$<?= number_format($trans['discount_amount'], 2) ?></span>
        </div>
    <?php endif; ?>
    <div style="display: flex; justify-content: space-between; padding-top: 15px; border-top: 1px solid var(--border); font-size: 20px; font-weight: 700;">
        <span>Total</span>
        <span style="color: var(--primary);">$<?= number_format($trans['total'], 2) ?></span>
    </div>
    
    <?php if ($trans['payment_method'] === 'cash'): ?>
        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px dashed var(--border);">
            <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                <span style="color: var(--text-dim);">Cash Received</span>
                <span>$<?= number_format($trans['cash_amount'], 2) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-dim);">Change Given</span>
                <span style="color: #10b981;">$<?= number_format($trans['change_given'], 2) ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if ($trans['stripe_payment_intent']): ?>
<div style="margin-top: 15px; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: 8px; font-size: 12px;">
    <i class="fab fa-stripe" style="color: #3b82f6;"></i>
    <span style="color: var(--text-dim);">Stripe Payment Intent:</span>
    <span style="font-family: monospace;"><?= htmlspecialchars($trans['stripe_payment_intent']) ?></span>
</div>
<?php endif; ?>

<?php if ($trans['notes']): ?>
<div style="margin-top: 15px; padding: 15px; background: rgba(245, 158, 11, 0.1); border-radius: 10px; border: 1px solid rgba(245, 158, 11, 0.3);">
    <p style="font-weight: 600; margin-bottom: 5px;"><i class="fas fa-sticky-note" style="color: #f59e0b;"></i> Notes</p>
    <p style="color: var(--text-dim);"><?= nl2br(htmlspecialchars($trans['notes'])) ?></p>
</div>
<?php endif; ?>
