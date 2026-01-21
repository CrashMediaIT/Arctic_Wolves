<?php
/**
 * Payment History
 * View all payment transactions and bookings
 */

require_once __DIR__ . '/../security.php';

$viewing_user_id = $user_id;
$is_parent = ($user_role === 'parent');

// Allow parents to view athlete payments
if ($is_parent && isset($_GET['athlete_id'])) {
    $verify_stmt = $pdo->prepare("SELECT athlete_id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
    $verify_stmt->execute([$user_id, intval($_GET['athlete_id'])]);
    if ($verify_stmt->fetch()) {
        $viewing_user_id = intval($_GET['athlete_id']);
    }
}

// Get payment history
$payments_stmt = $pdo->prepare("
    SELECT b.*, s.title as session_title, s.session_date, s.session_time,
           p.name as package_name,
           u.first_name, u.last_name
    FROM bookings b
    LEFT JOIN sessions s ON b.session_id = s.id
    LEFT JOIN packages p ON b.package_id = p.id
    LEFT JOIN users u ON b.booked_for_user_id = u.id
    WHERE b.user_id = ? AND b.status = 'paid'
    ORDER BY b.created_at DESC
    LIMIT 200
");
$payments_stmt->execute([$viewing_user_id]);
$payments = $payments_stmt->fetchAll();

// Get user credits history
$credits_stmt = $pdo->prepare("
    SELECT c.*, r.refund_type
    FROM user_credits c
    LEFT JOIN refunds r ON c.refund_id = r.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$credits_stmt->execute([$viewing_user_id]);
$credits = $credits_stmt->fetchAll();

// Calculate totals
$total_spent = array_sum(array_column($payments, 'amount_paid'));
$total_credits = array_sum(array_column($credits, 'credit_amount'));
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
        margin-bottom: 10px;
    }
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 20px;
        color: #fff;
    }
    .summary-card.green {
        background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    }
    .summary-value {
        font-size: 32px;
        font-weight: 900;
        margin-bottom: 5px;
    }
    .summary-label {
        font-size: 13px;
        opacity: 0.9;
    }
    .section-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
    }
    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 20px;
    }
    .payment-table {
        width: 100%;
        border-collapse: collapse;
    }
    .payment-table thead {
        background: #06080b;
    }
    .payment-table th {
        text-align: left;
        padding: 12px;
        color: #94a3b8;
        font-size: 12px;
        text-transform: uppercase;
        font-weight: 700;
        border-bottom: 1px solid #1e293b;
    }
    .payment-table td {
        padding: 15px 12px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
    }
    .payment-table tr:hover {
        background: rgba(255, 77, 0, 0.05);
    }
    .payment-type-badge {
        display: inline-block;
        background: var(--primary);
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }
    .payment-type-badge.package {
        background: #10b981;
    }
    .credit-badge {
        display: inline-block;
        background: #10b981;
        color: #fff;
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
    }
    .credit-badge.refund {
        background: #f59e0b;
    }
    .credit-badge.bonus {
        background: #8b5cf6;
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #64748b;
    }
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.3;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-file-invoice-dollar"></i> Payment History
    </h1>
</div>

<div class="stats-summary">
    <div class="summary-card">
        <div class="summary-value">$<?= number_format($total_spent, 2) ?></div>
        <div class="summary-label">Total Spent</div>
    </div>
    <div class="summary-card">
        <div class="summary-value"><?= count($payments) ?></div>
        <div class="summary-label">Total Transactions</div>
    </div>
    <div class="summary-card green">
        <div class="summary-value">$<?= number_format($total_credits, 2) ?></div>
        <div class="summary-label">Total Credits Received</div>
    </div>
</div>

<!-- Payment Transactions -->
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-receipt"></i> Payment Transactions</h2>
    
    <?php if (empty($payments)): ?>
        <div class="empty-state">
            <i class="fas fa-file-invoice"></i>
            <p>No payment history yet</p>
        </div>
    <?php else: ?>
        <table class="payment-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Description</th>
                    <th>Booked For</th>
                    <th>Original Price</th>
                    <th>Discount</th>
                    <th>Credit Applied</th>
                    <th>Amount Paid</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($payments as $payment): ?>
                    <tr>
                        <td style="white-space: nowrap;">
                            <?= date('M d, Y', strtotime($payment['created_at'])) ?><br>
                            <small style="color: #64748b;"><?= date('g:i A', strtotime($payment['created_at'])) ?></small>
                        </td>
                        <td>
                            <span class="payment-type-badge <?= $payment['payment_type'] ?>">
                                <?= strtoupper($payment['payment_type']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($payment['payment_type'] === 'session'): ?>
                                <?= htmlspecialchars($payment['session_title']) ?><br>
                                <small style="color: #64748b;">
                                    <?= date('M d, Y', strtotime($payment['session_date'])) ?>
                                    at <?= date('g:i A', strtotime($payment['session_time'])) ?>
                                </small>
                            <?php else: ?>
                                <?= htmlspecialchars($payment['package_name']) ?>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payment['booked_for_user_id']): ?>
                                <?= htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']) ?>
                            <?php else: ?>
                                <span style="color: #64748b;">Self</span>
                            <?php endif; ?>
                        </td>
                        <td>$<?= number_format($payment['original_price'], 2) ?></td>
                        <td>
                            <?php if ($payment['discount_code']): ?>
                                $<?= number_format($payment['original_price'] - $payment['amount_paid'] - $payment['credit_applied'], 2) ?><br>
                                <small style="color: #10b981;"><?= htmlspecialchars($payment['discount_code']) ?></small>
                            <?php else: ?>
                                <span style="color: #64748b;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($payment['credit_applied'] > 0): ?>
                                <span style="color: #10b981; font-weight: 600;">
                                    $<?= number_format($payment['credit_applied'], 2) ?>
                                </span>
                            <?php else: ?>
                                <span style="color: #64748b;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 700; color: var(--primary);">
                            $<?= number_format($payment['amount_paid'], 2) ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Credits History -->
<?php if (!empty($credits)): ?>
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-wallet"></i> Store Credits History</h2>
    
    <table class="payment-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Source</th>
                <th>Amount</th>
                <th>Used</th>
                <th>Remaining</th>
                <th>Expiry</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($credits as $credit): ?>
                <tr>
                    <td style="white-space: nowrap;">
                        <?= date('M d, Y', strtotime($credit['created_at'])) ?>
                    </td>
                    <td>
                        <span class="credit-badge <?= $credit['credit_source'] ?>">
                            <?= strtoupper($credit['credit_source']) ?>
                        </span>
                    </td>
                    <td style="color: #10b981; font-weight: 600;">
                        $<?= number_format($credit['credit_amount'], 2) ?>
                    </td>
                    <td>$<?= number_format($credit['used_amount'], 2) ?></td>
                    <td style="font-weight: 600;">
                        $<?= number_format($credit['remaining_amount'], 2) ?>
                    </td>
                    <td>
                        <?php if ($credit['expiry_date']): ?>
                            <?= date('M d, Y', strtotime($credit['expiry_date'])) ?>
                            <?php if (strtotime($credit['expiry_date']) < time()): ?>
                                <br><small style="color: #ef4444;">Expired</small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #64748b;">No expiry</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 13px; color: #94a3b8;">
                        <?= htmlspecialchars($credit['notes'] ?? '') ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
