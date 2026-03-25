<?php
/**
 * PWA Payment History - Mobile-native payment list
 * Queries bookings, packages, and invoices (matching desktop payment_history.php).
 */

$viewing_user_id = $user_id;
$is_parent = ($user_role === 'parent');

// Allow parents to view athlete payments
if ($is_parent && isset($_GET['athlete_id'])) {
    try {
        $verify_stmt = $pdo->prepare("SELECT athlete_id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
        $verify_stmt->execute([$user_id, intval($_GET['athlete_id'])]);
        if ($verify_stmt->fetch()) {
            $viewing_user_id = intval($_GET['athlete_id']);
        }
    } catch (PDOException $e) { /* ignore */ }
}

// Get session booking history (from bookings table)
$session_payments = [];
try {
    $bookings_stmt = $pdo->prepare("
        SELECT b.id, b.booking_date as created_at, b.payment_status as status,
               b.amount_paid as amount, b.original_price, b.discount_code,
               s.title as description, b.invoice_id,
               'session' as payment_type, 'stripe' as payment_method
        FROM bookings b
        LEFT JOIN sessions s ON b.session_id = s.id
        WHERE b.user_id = ? AND b.payment_status = 'paid'
        ORDER BY b.booking_date DESC
        LIMIT 50
    ");
    $bookings_stmt->execute([$viewing_user_id]);
    $session_payments = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $session_payments = []; }

// Get package purchase history (from user_packages table)
$package_payments = [];
try {
    $packages_stmt = $pdo->prepare("
        SELECT up.id, up.purchase_date as created_at, up.payment_status as status,
               up.amount_paid as amount, up.amount_paid as original_price, NULL as discount_code,
               p.name as description, NULL as invoice_id,
               'package' as payment_type, 'stripe' as payment_method
        FROM user_packages up
        LEFT JOIN packages p ON up.package_id = p.id
        WHERE up.user_id = ? AND up.payment_status = 'paid'
        ORDER BY up.purchase_date DESC
        LIMIT 50
    ");
    $packages_stmt->execute([$viewing_user_id]);
    $package_payments = $packages_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $package_payments = []; }

// Also get direct payments table records (for dev programs, manual payments, etc.)
$direct_payments = [];
try {
    $stmt = $pdo->prepare("
        SELECT p.id, p.amount, p.payment_method, p.payment_status as status,
               p.created_at, p.notes as description, p.invoice_id,
               'payment' as payment_type
        FROM payments p
        WHERE p.user_id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ");
    $stmt->execute([$viewing_user_id]);
    $direct_payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $direct_payments = []; }

// Combine all payments and sort by date
$payments = array_merge($session_payments, $package_payments, $direct_payments);
usort($payments, function($a, $b) {
    return strtotime($b['created_at'] ?? '1970-01-01') - strtotime($a['created_at'] ?? '1970-01-01');
});
$payments = array_slice($payments, 0, 100);

// Get user invoices
$pwa_invoices = [];
try {
    $inv_stmt = $pdo->prepare("
        SELECT id, invoice_number, invoice_date, total_amount, status
        FROM invoices
        WHERE user_id = ?
        ORDER BY invoice_date DESC
        LIMIT 30
    ");
    $inv_stmt->execute([$viewing_user_id]);
    $pwa_invoices = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $pwa_invoices = []; }

$totalPaid = 0;
foreach ($payments as $p) {
    $s = strtolower($p['status'] ?? '');
    if ($s === 'completed' || $s === 'paid' || $s === 'succeeded') {
        $totalPaid += (float)($p['amount'] ?? 0);
    }
}
?>
<style>
.m-payments { padding: 16px; font-family: Inter, sans-serif; }
.m-payments-header { margin-bottom: 16px; }
.m-payments-title { font-size: 17px; font-weight: 700; color: #fff; margin: 0; }
.m-payments-sub { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-payment-summary {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px; padding: 20px; margin-bottom: 16px;
    text-align: center;
}
.m-payment-summary-label { font-size: 12px; color: rgba(255,255,255,0.7); }
.m-payment-summary-value { font-size: 28px; font-weight: 700; color: #fff; margin-top: 4px; }
.m-payment-card {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    min-height: 44px; cursor: pointer; transition: border-color 0.2s;
}
.m-payment-card:active { border-color: #6B46C1; }
.m-payment-detail { display:none; background:#0A0A0F; border:1px solid #2D2D3F; border-radius:10px; padding:12px; margin:-4px 0 8px; font-size:13px; }
.m-payment-detail-row { display:flex; justify-content:space-between; padding:6px 0; border-bottom:1px solid #1A1A2F; color:#A8A8B8; }
.m-payment-detail-row:last-child { border-bottom:none; }
.m-payment-detail-row span:last-child { color:#fff; font-weight:600; }
.m-payment-icon {
    width: 40px; height: 40px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 14px; flex-shrink: 0;
}
.m-payment-icon-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-payment-icon-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-payment-icon-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-payment-icon-refunded { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-payment-icon-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-payment-body { flex: 1; min-width: 0; }
.m-payment-desc { font-size: 13px; font-weight: 600; color: #fff; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-payment-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-payment-right { text-align: right; flex-shrink: 0; }
.m-payment-amount { font-size: 14px; font-weight: 700; color: #fff; }
.m-payment-status {
    font-size: 10px; padding: 2px 6px; border-radius: 4px; font-weight: 600;
    margin-top: 3px; display: inline-block;
}
.m-payment-status-completed { background: rgba(16,185,129,0.15); color: #10B981; }
.m-payment-status-pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-payment-status-failed { background: rgba(239,68,68,0.15); color: #EF4444; }
.m-payment-status-refunded { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-payment-status-default { background: rgba(168,168,184,0.15); color: #A8A8B8; }
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-payments">
    <div class="m-payments-header">
        <h2 class="m-payments-title">Payment History</h2>
        <p class="m-payments-sub"><?= count($payments) ?> transaction<?= count($payments) !== 1 ? 's' : '' ?></p>
    </div>

    <!-- Date Filter -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px;">
        <div>
            <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">From</label>
            <input type="date" id="mPayDateFrom" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;">
        </div>
        <div>
            <label style="font-size:11px;color:#A8A8B8;display:block;margin-bottom:4px;">To</label>
            <input type="date" id="mPayDateTo" style="width:100%;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:12px;min-height:44px;font-size:14px;" value="<?= date('Y-m-d') ?>">
        </div>
    </div>
    <button onclick="mPayFilter()" style="width:100%;background:#6B46C1;color:#fff;border:none;border-radius:10px;padding:12px;font-size:14px;font-weight:600;min-height:44px;cursor:pointer;margin-bottom:16px;">
        <i class="fas fa-filter"></i> Filter Payments
    </button>

    <div class="m-payment-summary">
        <div class="m-payment-summary-label">Total Paid</div>
        <div class="m-payment-summary-value">$<?= number_format($totalPaid, 2) ?></div>
    </div>

    <?php if (empty($payments)): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            <p>No payment history</p>
        </div>
    <?php else: ?>
        <?php foreach ($payments as $p):
            $status = strtolower($p['status'] ?? 'default');
            $statusClass = match($status) {
                'completed', 'paid', 'succeeded' => 'completed',
                'pending', 'processing' => 'pending',
                'failed', 'declined' => 'failed',
                'refunded' => 'refunded',
                default => 'default',
            };
            $methodIcon = match(strtolower($p['payment_method'] ?? '')) {
                'credit_card', 'card', 'stripe' => 'fa-credit-card',
                'cash' => 'fa-money-bill',
                'bank', 'transfer' => 'fa-building-columns',
                'paypal' => 'fa-paypal',
                default => 'fa-receipt',
            };
        ?>
        <div class="m-payment-card" onclick="mPayToggle(this)" data-date="<?= date('Y-m-d', strtotime($p['created_at'] ?? 'now')) ?>">
            <div class="m-payment-icon m-payment-icon-<?= $statusClass ?>">
                <i class="fas <?= $methodIcon ?>"></i>
            </div>
            <div class="m-payment-body">
                <div class="m-payment-desc"><?= htmlspecialchars($p['description'] ?: 'Payment') ?></div>
                <div class="m-payment-meta">
                    <?php $ptype = $p['payment_type'] ?? 'payment'; ?>
                    <span style="font-size:10px;padding:1px 5px;border-radius:4px;font-weight:600;<?= $ptype === 'session' ? 'background:rgba(107,70,193,0.15);color:#8B5CF6;' : ($ptype === 'package' ? 'background:rgba(16,185,129,0.15);color:#10B981;' : 'background:rgba(168,168,184,0.15);color:#A8A8B8;') ?>"><?= strtoupper($ptype) ?></span>
                    · <?= date('M j, Y', strtotime($p['created_at'] ?? 'now')) ?>
                </div>
            </div>
            <div class="m-payment-right">
                <div class="m-payment-amount">$<?= number_format((float)($p['amount'] ?? 0), 2) ?></div>
                <span class="m-payment-status m-payment-status-<?= $statusClass ?>"><?= htmlspecialchars(ucfirst($status === 'paid' ? 'completed' : $status)) ?></span>
            </div>
        </div>
        <div class="m-payment-detail" id="mPayDetail<?= $p['id'] ?>">
            <div class="m-payment-detail-row"><span>Type</span><span><?= htmlspecialchars(ucfirst($ptype)) ?></span></div>
            <div class="m-payment-detail-row"><span>Date</span><span><?= date('M j, Y g:i A', strtotime($p['created_at'] ?? 'now')) ?></span></div>
            <div class="m-payment-detail-row"><span>Amount</span><span>$<?= number_format((float)($p['amount'] ?? 0), 2) ?></span></div>
            <div class="m-payment-detail-row"><span>Status</span><span><?= htmlspecialchars(ucfirst($status === 'paid' ? 'completed' : $status)) ?></span></div>
            <?php if ($statusClass === 'completed' && !empty($p['invoice_id'])): ?>
            <a href="download_invoice.php?invoice_id=<?= (int)$p['invoice_id'] ?>" target="_blank" style="width:100%;margin-top:10px;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:10px;font-size:13px;font-weight:600;min-height:44px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;text-decoration:none;" onclick="event.stopPropagation();">
                <i class="fas fa-file-invoice" style="color:#8B5CF6;"></i> View Invoice
            </a>
            <?php elseif ($statusClass === 'completed'): ?>
            <button onclick="event.stopPropagation();mPayReceipt(<?= (int)$p['id'] ?>)" style="width:100%;margin-top:10px;background:#0A0A0F;border:1px solid #2D2D3F;border-radius:10px;color:#fff;padding:10px;font-size:13px;font-weight:600;min-height:44px;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;">
                <i class="fas fa-download" style="color:#8B5CF6;"></i> Download Receipt
            </button>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Invoices Section -->
    <?php if (!empty($pwa_invoices)): ?>
    <div style="margin-top: 20px;">
        <h3 style="font-size: 15px; font-weight: 700; color: #fff; margin-bottom: 12px;"><i class="fas fa-file-invoice" style="color: #8B5CF6; margin-right: 6px;"></i> Invoices</h3>
        <?php foreach ($pwa_invoices as $inv):
            $inv_status = strtolower($inv['status'] ?? 'draft');
            $inv_status_style = match($inv_status) {
                'paid' => 'background: rgba(16,185,129,0.15); color: #10B981;',
                'sent' => 'background: rgba(59,130,246,0.15); color: #3B82F6;',
                'overdue' => 'background: rgba(239,68,68,0.15); color: #EF4444;',
                default => 'background: rgba(168,168,184,0.15); color: #A8A8B8;',
            };
        ?>
        <a href="download_invoice.php?invoice_id=<?= (int)$inv['id'] ?>" target="_blank" style="display:flex;align-items:center;gap:12px;background:#16161F;border:1px solid #2D2D3F;border-radius:12px;padding:14px;margin-bottom:8px;min-height:44px;text-decoration:none;color:inherit;">
            <div style="width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0;background:rgba(107,70,193,0.15);color:#8B5CF6;">
                <i class="fas fa-file-invoice"></i>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="font-size:13px;font-weight:600;color:#fff;font-family:monospace;"><?= htmlspecialchars($inv['invoice_number']) ?></div>
                <div style="font-size:12px;color:#A8A8B8;margin-top:2px;"><?= date('M j, Y', strtotime($inv['invoice_date'])) ?></div>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:14px;font-weight:700;color:#fff;">$<?= number_format((float)$inv['total_amount'], 2) ?></div>
                <span style="font-size:10px;padding:2px 6px;border-radius:4px;font-weight:600;<?= $inv_status_style ?>"><?= strtoupper($inv_status) ?></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function mPayToggle(card) {
    var detail = card.nextElementSibling;
    if (detail && detail.classList.contains('m-payment-detail')) {
        detail.style.display = detail.style.display === 'block' ? 'none' : 'block';
    }
}
function mPayFilter() {
    var from = document.getElementById('mPayDateFrom').value;
    var to = document.getElementById('mPayDateTo').value;
    var cards = document.querySelectorAll('.m-payment-card');
    cards.forEach(function(c) {
        var d = c.getAttribute('data-date');
        var detail = c.nextElementSibling;
        var show = true;
        if (from && d < from) show = false;
        if (to && d > to) show = false;
        c.style.display = show ? 'flex' : 'none';
        if (detail && detail.classList.contains('m-payment-detail')) {
            detail.style.display = 'none';
        }
    });
}
function mPayReceipt(id) {
    window.open('payment_success.php?payment_id=' + id + '&receipt=1', '_blank');
}
</script>
