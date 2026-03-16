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

// Get session booking history (from bookings table)
$bookings_stmt = $pdo->prepare("
    SELECT b.id, b.session_id, b.user_id, b.booking_date as created_at, b.payment_status, 
           b.amount, b.amount_paid, b.original_price, b.discount_code,
           COALESCE(0, 0) as credit_applied,
           s.title as session_title, s.session_date, s.session_time,
           NULL as package_name, 'session' as payment_type,
           NULL as booked_for_user_id, NULL as first_name, NULL as last_name
    FROM bookings b
    LEFT JOIN sessions s ON b.session_id = s.id
    WHERE b.user_id = ? AND b.payment_status = 'paid'
    ORDER BY b.booking_date DESC
    LIMIT 100
");
$bookings_stmt->execute([$viewing_user_id]);
$session_payments = $bookings_stmt->fetchAll();

// Get package purchase history (from user_packages table)
$packages_stmt = $pdo->prepare("
    SELECT up.id, up.user_id, up.purchase_date as created_at, up.payment_status,
           up.amount_paid, up.amount_paid as amount, up.amount_paid as original_price,
           NULL as discount_code, COALESCE(0, 0) as credit_applied,
           NULL as session_title, NULL as session_date, NULL as session_time,
           p.name as package_name, 'package' as payment_type,
           NULL as booked_for_user_id, NULL as first_name, NULL as last_name
    FROM user_packages up
    LEFT JOIN packages p ON up.package_id = p.id
    WHERE up.user_id = ? AND up.payment_status = 'paid'
    ORDER BY up.purchase_date DESC
    LIMIT 100
");
$packages_stmt->execute([$viewing_user_id]);
$package_payments = $packages_stmt->fetchAll();

// Combine and sort all payments by date
$payments = array_merge($session_payments, $package_payments);
usort($payments, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});
$payments = array_slice($payments, 0, 200);

// Get user credits history
$credits_stmt = $pdo->prepare("
    SELECT c.*
    FROM user_credits c
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
");
$credits_stmt->execute([$viewing_user_id]);
$credits = $credits_stmt->fetchAll();

// Get user invoices for download links
$user_invoices = [];
try {
    $inv_stmt = $pdo->prepare("
        SELECT id, invoice_number, invoice_date, total_amount, status
        FROM invoices
        WHERE user_id = ?
        ORDER BY invoice_date DESC
    ");
    $inv_stmt->execute([$viewing_user_id]);
    $user_invoices = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $user_invoices = []; }

// Calculate totals
$total_spent = array_sum(array_column($payments, 'amount_paid'));
$total_credits = array_sum(array_column($credits, 'credit_amount'));
?>

<style>
    /* Payment History Styles - Using site-wide CSS variables */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 24px;
    }
    .summary-card {
        background: linear-gradient(135deg, var(--primary, #6B46C1) 0%, var(--primary-hover, #7C3AED) 100%);
        border-radius: var(--radius-lg, 8px);
        padding: 20px;
        color: var(--text-white, #fff);
    }
    .summary-card.green {
        background: linear-gradient(135deg, var(--success, #10b981) 0%, var(--success-hover, #059669) 100%);
    }
    .summary-value {
        font-size: 32px;
        font-weight: var(--font-weight-black, 900);
        margin-bottom: 5px;
    }
    .summary-label {
        font-size: 13px;
        opacity: 0.9;
    }
    .section-card {
        background: var(--bg-card, #16161F);
        border: 1px solid var(--border, #2D2D3F);
        border-radius: var(--radius-lg, 8px);
        padding: 24px;
        margin-bottom: 24px;
    }
    .section-title {
        font-size: var(--font-size-xl, 20px);
        font-weight: var(--font-weight-bold, 700);
        color: var(--text-white, #fff);
        margin-bottom: 20px;
    }
    .payment-table {
        width: 100%;
        border-collapse: collapse;
    }
    .payment-table thead {
        background: var(--bg-main, #0A0A0F);
    }
    .payment-table th {
        text-align: left;
        padding: 12px;
        color: var(--text-secondary, #A8A8B8);
        font-size: var(--font-size-sm, 12px);
        text-transform: uppercase;
        font-weight: var(--font-weight-bold, 700);
        border-bottom: 1px solid var(--border, #2D2D3F);
    }
    .payment-table td {
        padding: 16px 12px;
        border-bottom: 1px solid var(--border, #2D2D3F);
        color: var(--text-white, #fff);
    }
    .payment-table tr:hover {
        background: rgba(107, 70, 193, 0.05);
    }
    .payment-type-badge {
        display: inline-block;
        background: var(--primary, #6B46C1);
        color: var(--text-white, #fff);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: var(--font-weight-bold, 700);
    }
    .payment-type-badge.package {
        background: var(--success, #10b981);
    }
    .credit-badge {
        display: inline-block;
        background: var(--success, #10b981);
        color: var(--text-white, #fff);
        padding: 4px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: var(--font-weight-bold, 700);
    }
    .credit-badge.refund {
        background: var(--warning, #f59e0b);
    }
    .credit-badge.bonus {
        background: var(--primary-light, #8b5cf6);
    }
    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: var(--text-muted, #6B6B7B);
    }
    .empty-state i {
        font-size: 48px;
        margin-bottom: 12px;
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

<!-- Invoices -->
<?php if (!empty($user_invoices)): ?>
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-file-invoice"></i> Invoices</h2>
    
    <table class="payment-table">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($user_invoices as $inv): ?>
                <tr>
                    <td style="font-weight: 600; font-family: monospace;">
                        <?= htmlspecialchars($inv['invoice_number']) ?>
                    </td>
                    <td style="white-space: nowrap;">
                        <?= date('M d, Y', strtotime($inv['invoice_date'])) ?>
                    </td>
                    <td style="font-weight: 700; color: var(--primary);">
                        $<?= number_format((float)$inv['total_amount'], 2) ?>
                    </td>
                    <td>
                        <?php
                            $inv_status = $inv['status'] ?? 'draft';
                            $status_colors = [
                                'paid' => 'background: rgba(16,185,129,0.15); color: #10b981;',
                                'sent' => 'background: rgba(59,130,246,0.15); color: #3b82f6;',
                                'draft' => 'background: rgba(148,163,184,0.15); color: #94a3b8;',
                                'overdue' => 'background: rgba(239,68,68,0.15); color: #ef4444;',
                                'cancelled' => 'background: rgba(148,163,184,0.15); color: #94a3b8;',
                            ];
                        ?>
                        <span style="display: inline-block; padding: 4px 10px; border-radius: 12px; font-size: 11px; font-weight: 700; <?= $status_colors[$inv_status] ?? $status_colors['draft'] ?>">
                            <?= strtoupper($inv_status) ?>
                        </span>
                    </td>
                    <td>
                        <a href="download_invoice.php?invoice_id=<?= $inv['id'] ?>" target="_blank" 
                           style="color: var(--primary, #6B46C1); text-decoration: none; font-weight: 600; font-size: 13px;">
                            <i class="fas fa-download"></i> View Invoice
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
