<?php
/**
 * Parent Camp Check-In/Check-Out View
 * Allows parents to generate and share QR codes for child drop-off and pickup
 */

require_once __DIR__ . '/../security.php';

// Only parents can access this view
if ($user_role !== 'parent') {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>This page is only available to parents.</p></div>';
    return;
}

$booking_id = intval($_GET['booking_id'] ?? 0);
$session_id = intval($_GET['session_id'] ?? 0);
$athlete_id = intval($_GET['athlete_id'] ?? 0);

// Verify parent manages this athlete
$stmt = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
$stmt->execute([$user_id, $athlete_id]);
if (!$stmt->fetch()) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Access Denied</h2><p>You do not manage this athlete.</p></div>';
    return;
}

// Get booking, session, and athlete details
$stmt = $pdo->prepare("
    SELECT b.id as booking_id, b.user_id as athlete_id,
           s.id as session_id, s.title, s.session_date, s.session_time, s.duration_minutes,
           s.arena, s.city, s.enable_child_checkin,
           u.first_name as athlete_first_name, u.last_name as athlete_last_name
    FROM bookings b
    INNER JOIN sessions s ON b.session_id = s.id
    INNER JOIN users u ON b.user_id = u.id
    WHERE b.id = ? AND b.session_id = ? AND b.user_id = ? AND b.status = 'paid'
");
$stmt->execute([$booking_id, $session_id, $athlete_id]);
$booking_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking_info) {
    echo '<div style="text-align: center; padding: 60px;"><h2>Not Found</h2><p>Booking not found or not eligible for check-in.</p></div>';
    return;
}

$booking_info = decryptUserRow($booking_info);

// Get existing codes
$stmt = $pdo->prepare("
    SELECT * FROM camp_checkin_codes 
    WHERE booking_id = ? AND athlete_id = ? AND session_id = ? AND parent_id = ?
    ORDER BY code_type, created_at DESC
");
$stmt->execute([$booking_id, $athlete_id, $session_id, $user_id]);
$existing_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$checkin_code = null;
$checkout_code = null;
foreach ($existing_codes as $ec) {
    if ($ec['code_type'] === 'checkin' && !$ec['is_used'] && strtotime($ec['expires_at']) > time()) {
        $checkin_code = $ec;
    }
    if ($ec['code_type'] === 'checkout' && !$ec['is_used'] && strtotime($ec['expires_at']) > time()) {
        $checkout_code = $ec;
    }
}
?>

<div style="max-width: 800px; margin: 0 auto;">
    <div class="page-header" style="margin-bottom: 24px;">
        <h1 class="page-title"><i class="fas fa-qrcode"></i> Camp Check-In / Check-Out</h1>
        <p class="page-description">
            <strong><?= htmlspecialchars($booking_info['athlete_first_name'] . ' ' . $booking_info['athlete_last_name']) ?></strong>
            — <?= htmlspecialchars($booking_info['title'] ?? 'Camp Session') ?>
            &bull; <?= date('l, F j, Y', strtotime($booking_info['session_date'])) ?>
            <?php if ($booking_info['session_time']): ?>
                at <?= date('g:i A', strtotime($booking_info['session_time'])) ?>
            <?php endif; ?>
            <?php if ($booking_info['arena']): ?>
                — <?= htmlspecialchars($booking_info['arena']) ?>
            <?php endif; ?>
        </p>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php
            switch ($_GET['status']) {
                case 'code_generated': echo 'QR code generated successfully!'; break;
                case 'code_shared': echo 'Code shared via email successfully!'; break;
                default: echo 'Action completed.';
            }
            ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fas fa-info-circle"></i>
        <div>
            <strong>How it works:</strong> Generate a check-in QR code below before dropping off your child. 
            Staff will scan the code at the front desk. For pickup, generate a check-out code. 
            You can also share codes with other authorized people via email.
        </div>
    </div>

    <!-- Check-In (Drop-Off) Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-sign-in-alt" style="color: #10B981;"></i> Check-In (Drop-Off)</h3>
            <?php if ($checkin_code): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Code Active</span>
            <?php else: ?>
                <span class="badge badge-secondary"><i class="fas fa-circle"></i> Not Generated</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($checkin_code): ?>
                <div style="text-align: center;">
                    <div style="display: inline-block; padding: 20px; background: #fff; border-radius: 8px; margin-bottom: 16px;">
                        <canvas id="checkin-qr"></canvas>
                    </div>
                </div>
                <p style="text-align: center; color: var(--text-muted, #6B6B7B); font-size: 13px; margin: 8px 0;">
                    Code: <code style="background: var(--bg-card, #16161F); padding: 2px 8px; border-radius: 4px; color: var(--text-white, #E0E0E0);"><?= htmlspecialchars(substr($checkin_code['code'], 0, 12)) ?>...</code>
                </p>
                <?php if ($checkin_code['items_description']): ?>
                    <p style="color: var(--text-muted, #6B6B7B); font-size: 13px; margin-top: 8px; text-align: center;">
                        <i class="fas fa-box"></i> Items: <?= htmlspecialchars($checkin_code['items_description']) ?>
                    </p>
                <?php endif; ?>

                <!-- Share Section -->
                <div style="border-top: 1px solid var(--border, #2D2D3F); padding-top: 16px; margin-top: 16px;">
                    <h4 style="color: var(--text-white, #fff); margin: 0 0 12px 0; font-size: 14px;"><i class="fas fa-share-alt"></i> Share with Someone Else</h4>
                    <form method="POST" action="process_camp_checkin.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="share_code">
                        <input type="hidden" name="code_id" value="<?= $checkin_code['id'] ?>">
                        <input type="text" name="share_name" placeholder="Their name" required style="flex: 1; min-width: 120px;">
                        <input type="email" name="share_email" placeholder="Their email address" required style="flex: 1; min-width: 150px;">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                    <?php if ($checkin_code['shared_email']): ?>
                        <p style="color: var(--text-muted, #6B6B7B); font-size: 12px; margin-top: 8px;">
                            <i class="fas fa-check"></i> Shared with <?= htmlspecialchars($checkin_code['shared_name'] ?? $checkin_code['shared_email']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" action="process_camp_checkin.php">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="generate_checkin_code">
                    <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                    <input type="hidden" name="session_id" value="<?= $session_id ?>">
                    <input type="hidden" name="athlete_id" value="<?= $athlete_id ?>">
                    <input type="hidden" name="code_type" value="checkin">
                    
                    <div class="form-group">
                        <label>What is your child bringing? (optional)</label>
                        <textarea name="items_description" placeholder="e.g., Lunch box, hockey equipment, water bottle, show and tell toy..."></textarea>
                    </div>
                    
                    <div style="text-align: center;">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-qrcode"></i> Generate Check-In Code
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Check-Out (Pickup) Card -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-sign-out-alt" style="color: #F59E0B;"></i> Check-Out (Pickup)</h3>
            <?php if ($checkout_code): ?>
                <span class="badge badge-success"><i class="fas fa-check-circle"></i> Code Active</span>
            <?php else: ?>
                <span class="badge badge-secondary"><i class="fas fa-circle"></i> Not Generated</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <?php if ($checkout_code): ?>
                <div style="text-align: center;">
                    <div style="display: inline-block; padding: 20px; background: #fff; border-radius: 8px; margin-bottom: 16px;">
                        <canvas id="checkout-qr"></canvas>
                    </div>
                </div>
                <p style="text-align: center; color: var(--text-muted, #6B6B7B); font-size: 13px; margin: 8px 0;">
                    Code: <code style="background: var(--bg-card, #16161F); padding: 2px 8px; border-radius: 4px; color: var(--text-white, #E0E0E0);"><?= htmlspecialchars(substr($checkout_code['code'], 0, 12)) ?>...</code>
                </p>

                <!-- Share Section -->
                <div style="border-top: 1px solid var(--border, #2D2D3F); padding-top: 16px; margin-top: 16px;">
                    <h4 style="color: var(--text-white, #fff); margin: 0 0 12px 0; font-size: 14px;"><i class="fas fa-share-alt"></i> Share with Someone Else for Pickup</h4>
                    <form method="POST" action="process_camp_checkin.php" style="display: flex; gap: 10px; flex-wrap: wrap;">
                        <?= csrfTokenInput() ?>
                        <input type="hidden" name="action" value="share_code">
                        <input type="hidden" name="code_id" value="<?= $checkout_code['id'] ?>">
                        <input type="text" name="share_name" placeholder="Their name" required style="flex: 1; min-width: 120px;">
                        <input type="email" name="share_email" placeholder="Their email address" required style="flex: 1; min-width: 150px;">
                        <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-paper-plane"></i> Send</button>
                    </form>
                    <?php if ($checkout_code['shared_email']): ?>
                        <p style="color: var(--text-muted, #6B6B7B); font-size: 12px; margin-top: 8px;">
                            <i class="fas fa-check"></i> Shared with <?= htmlspecialchars($checkout_code['shared_name'] ?? $checkout_code['shared_email']) ?>
                        </p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <form method="POST" action="process_camp_checkin.php">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="generate_checkin_code">
                    <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                    <input type="hidden" name="session_id" value="<?= $session_id ?>">
                    <input type="hidden" name="athlete_id" value="<?= $athlete_id ?>">
                    <input type="hidden" name="code_type" value="checkout">
                    
                    <div style="text-align: center;">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-qrcode"></i> Generate Pickup Code
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <a href="?page=parent_home" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</div>

<!-- QR Code Generation Script (using qrcode.js via CDN) -->
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($checkin_code): ?>
    var checkinCanvas = document.getElementById('checkin-qr');
    if (checkinCanvas) {
        QRCode.toCanvas(checkinCanvas, <?= json_encode($checkin_code['code']) ?>, {
            width: 200,
            margin: 2,
            color: { dark: '#000000', light: '#ffffff' }
        });
    }
    <?php endif; ?>
    
    <?php if ($checkout_code): ?>
    var checkoutCanvas = document.getElementById('checkout-qr');
    if (checkoutCanvas) {
        QRCode.toCanvas(checkoutCanvas, <?= json_encode($checkout_code['code']) ?>, {
            width: 200,
            margin: 2,
            color: { dark: '#000000', light: '#ffffff' }
        });
    }
    <?php endif; ?>
});
</script>
