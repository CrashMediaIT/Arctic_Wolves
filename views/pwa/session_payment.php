<?php
/**
 * PWA Session Payment - Mobile-native payment view for a booking
 * Purpose-built for mobile phones.
 */

$bookingIdParam = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$booking = null;
$session = null;

if ($bookingIdParam > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT b.id as booking_id, b.status as booking_status, b.user_id,
                   s.id as session_id, s.title, s.session_date, s.session_time,
                   s.duration_minutes, s.price, s.arena, s.session_type,
                   u.first_name as coach_first, u.last_name as coach_last
            FROM bookings b
            JOIN sessions s ON s.id = b.session_id
            LEFT JOIN users u ON u.id = s.coach_id
            WHERE b.id = ? AND b.user_id = ?
        ");
        $stmt->execute([$bookingIdParam, $user_id]);
        $booking = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($booking) {
            $booking['coach_first'] = FieldEncryption::decrypt($booking['coach_first'] ?? '');
            $booking['coach_last'] = FieldEncryption::decrypt($booking['coach_last'] ?? '');
        }
    } catch (PDOException $e) { $booking = null; }
}
?>
<style>
.m-session-pay { padding: 16px; font-family: Inter, sans-serif; }
.m-back-link {
    display: inline-flex; align-items: center; gap: 6px;
    color: #8B5CF6; font-size: 13px; font-weight: 500;
    text-decoration: none; margin-bottom: 16px;
    min-height: 44px; padding: 8px 0;
}
.m-pay-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px;
    padding: 20px; margin-bottom: 16px;
}
.m-pay-title { font-size: 15px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 16px; }
.m-pay-session-name { font-size: 18px; font-weight: 700; color: #fff; margin: 0 0 12px; }
.m-pay-field {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 0; border-bottom: 1px solid #2D2D3F;
}
.m-pay-field:last-child { border-bottom: none; }
.m-pay-field-label { font-size: 13px; color: #A8A8B8; }
.m-pay-field-value { font-size: 13px; color: #fff; font-weight: 500; }
.m-pay-total {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 0 0; margin-top: 12px;
    border-top: 2px solid #2D2D3F;
}
.m-pay-total-label { font-size: 15px; font-weight: 600; color: #fff; }
.m-pay-total-value { font-size: 22px; font-weight: 700; color: #10B981; }
.m-pay-methods { margin-bottom: 16px; }
.m-pay-methods-title { font-size: 13px; font-weight: 600; color: #6B6B7B; text-transform: uppercase; letter-spacing: 0.5px; margin: 0 0 10px; }
.m-pay-method {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 8px;
    min-height: 44px; cursor: pointer;
}
.m-pay-method-icon {
    width: 40px; height: 40px; border-radius: 10px;
    background: rgba(107,70,193,0.15);
    display: flex; align-items: center; justify-content: center;
    font-size: 16px; color: #8B5CF6; flex-shrink: 0;
}
.m-pay-method-name { font-size: 14px; font-weight: 600; color: #fff; }
.m-pay-method-desc { font-size: 11px; color: #A8A8B8; margin-top: 1px; }
.m-pay-method-body { flex: 1; }
.m-pay-action {
    display: flex; align-items: center; justify-content: center; gap: 8px;
    padding: 16px; border-radius: 12px;
    background: #6B46C1; color: #fff; font-size: 15px; font-weight: 600;
    text-decoration: none; min-height: 48px;
    font-family: Inter, sans-serif; border: none; cursor: pointer;
    width: 100%; box-sizing: border-box;
}
.m-pay-note {
    text-align: center; font-size: 12px; color: #6B6B7B; margin-top: 12px;
    padding: 0 12px;
}
.m-empty-state { text-align: center; padding: 40px 20px; color: #6B6B7B; }
.m-empty-state i { font-size: 32px; display: block; margin-bottom: 12px; }
.m-empty-state p { font-size: 14px; margin: 0; }
</style>

<div class="m-session-pay">
    <a href="?page=sessions" class="m-back-link">
        <i class="fas fa-chevron-left"></i> Back to Sessions
    </a>

    <?php if (!$booking): ?>
        <div class="m-empty-state">
            <i class="fas fa-receipt"></i>
            <p>Booking not found</p>
        </div>
    <?php else:
        $sDate = $booking['session_date'] ? date('l, F j, Y', strtotime($booking['session_date'])) : 'TBD';
        $sTime = $booking['session_time'] ? date('g:i A', strtotime($booking['session_time'])) : 'TBD';
        $coachName = trim(($booking['coach_first'] ?? '') . ' ' . ($booking['coach_last'] ?? ''));
        $amount = (float)($booking['price'] ?? 0);
    ?>
        <!-- Payment Summary -->
        <div class="m-pay-card">
            <h3 class="m-pay-title">Payment Summary</h3>
            <h2 class="m-pay-session-name"><?= htmlspecialchars($booking['title'] ?? 'Session') ?></h2>

            <div class="m-pay-field">
                <span class="m-pay-field-label">Date</span>
                <span class="m-pay-field-value"><?= htmlspecialchars($sDate) ?></span>
            </div>
            <div class="m-pay-field">
                <span class="m-pay-field-label">Time</span>
                <span class="m-pay-field-value"><?= htmlspecialchars($sTime) ?></span>
            </div>
            <?php if ($coachName): ?>
            <div class="m-pay-field">
                <span class="m-pay-field-label">Coach</span>
                <span class="m-pay-field-value"><?= htmlspecialchars($coachName) ?></span>
            </div>
            <?php endif; ?>
            <?php if (!empty($booking['session_type'])): ?>
            <div class="m-pay-field">
                <span class="m-pay-field-label">Type</span>
                <span class="m-pay-field-value"><?= htmlspecialchars($booking['session_type']) ?></span>
            </div>
            <?php endif; ?>

            <div class="m-pay-total">
                <span class="m-pay-total-label">Amount Due</span>
                <span class="m-pay-total-value">$<?= number_format($amount, 2) ?></span>
            </div>
        </div>

        <!-- Payment Methods -->
        <div class="m-pay-methods">
            <h3 class="m-pay-methods-title">Payment Method</h3>
            <div class="m-pay-method">
                <div class="m-pay-method-icon"><i class="fas fa-credit-card"></i></div>
                <div class="m-pay-method-body">
                    <div class="m-pay-method-name">Credit / Debit Card</div>
                    <div class="m-pay-method-desc">Visa, Mastercard, Amex</div>
                </div>
            </div>
            <div class="m-pay-method">
                <div class="m-pay-method-icon"><i class="fas fa-building-columns"></i></div>
                <div class="m-pay-method-body">
                    <div class="m-pay-method-name">Bank Transfer</div>
                    <div class="m-pay-method-desc">Direct bank payment</div>
                </div>
            </div>
            <div class="m-pay-method">
                <div class="m-pay-method-icon"><i class="fas fa-money-bill"></i></div>
                <div class="m-pay-method-body">
                    <div class="m-pay-method-name">Cash</div>
                    <div class="m-pay-method-desc">Pay at front desk</div>
                </div>
            </div>
        </div>

        <!-- Complete Payment Action -->
        <a href="shop_checkout.php?booking_id=<?= (int)$booking['booking_id'] ?>" class="m-pay-action">
            <i class="fas fa-lock"></i> Complete Payment on Desktop
        </a>
        <p class="m-pay-note">
            <i class="fas fa-shield-halved"></i> Secure payment processing is available on the full desktop site.
        </p>
    <?php endif; ?>
</div>
