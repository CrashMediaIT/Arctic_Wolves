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

<style>
    .checkin-container {
        max-width: 800px;
        margin: 0 auto;
    }
    .checkin-header {
        background: linear-gradient(135deg, var(--primary, #7000a4) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
        color: #fff;
    }
    .checkin-header h1 {
        margin: 0 0 10px 0;
        font-size: 24px;
        font-weight: 900;
    }
    .checkin-header .meta {
        font-size: 14px;
        opacity: 0.9;
    }
    .checkin-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 20px;
    }
    .checkin-card h3 {
        color: #fff;
        margin: 0 0 16px 0;
        font-size: 18px;
        font-weight: 700;
    }
    .qr-container {
        text-align: center;
        padding: 20px;
        background: #fff;
        border-radius: 8px;
        margin-bottom: 16px;
        display: inline-block;
    }
    .qr-container canvas {
        display: block;
    }
    .code-status {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 700;
    }
    .code-status.active {
        background: rgba(16, 185, 129, 0.1);
        color: #10b981;
        border: 1px solid #10b981;
    }
    .code-status.used {
        background: rgba(239, 68, 68, 0.1);
        color: #ef4444;
        border: 1px solid #ef4444;
    }
    .code-status.not-generated {
        background: rgba(100, 116, 139, 0.1);
        color: #94a3b8;
        border: 1px solid #64748b;
    }
    .items-form textarea {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        min-height: 80px;
        resize: vertical;
        font-family: inherit;
        margin-bottom: 12px;
    }
    .items-form textarea:focus {
        outline: none;
        border-color: var(--primary, #7000a4);
    }
    .btn-generate {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        cursor: pointer;
        font-size: 14px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: all 0.2s;
    }
    .btn-checkin {
        background: #10b981;
        color: #fff;
    }
    .btn-checkin:hover {
        background: #059669;
    }
    .btn-checkout {
        background: #f59e0b;
        color: #000;
    }
    .btn-checkout:hover {
        background: #d97706;
    }
    .share-section {
        border-top: 1px solid #1e293b;
        padding-top: 16px;
        margin-top: 16px;
    }
    .share-section h4 {
        color: #fff;
        margin: 0 0 12px 0;
        font-size: 14px;
    }
    .share-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .share-form input {
        padding: 10px 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 13px;
        flex: 1;
        min-width: 150px;
    }
    .share-form input:focus {
        outline: none;
        border-color: var(--primary, #7000a4);
    }
    .share-form button {
        padding: 10px 16px;
        background: #1e293b;
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        font-size: 13px;
    }
    .share-form button:hover {
        background: #334155;
    }
    .alert-box {
        padding: 16px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-size: 14px;
    }
    .alert-box.success {
        background: rgba(16, 185, 129, 0.1);
        border: 1px solid #10b981;
        color: #10b981;
    }
    .alert-box.info {
        background: rgba(107, 70, 193, 0.1);
        border: 1px solid #6B46C1;
        color: #e2e8f0;
    }
    @media (max-width: 768px) {
        .share-form {
            flex-direction: column;
        }
        .share-form input {
            min-width: 100%;
        }
    }
</style>

<div class="checkin-container">
    <div class="checkin-header">
        <h1><i class="fas fa-qrcode"></i> Camp Check-In / Check-Out</h1>
        <div class="meta">
            <strong><?= htmlspecialchars($booking_info['athlete_first_name'] . ' ' . $booking_info['athlete_last_name']) ?></strong>
            — <?= htmlspecialchars($booking_info['title'] ?? 'Camp Session') ?>
            <br>
            <?= date('l, F j, Y', strtotime($booking_info['session_date'])) ?>
            <?php if ($booking_info['session_time']): ?>
                at <?= date('g:i A', strtotime($booking_info['session_time'])) ?>
            <?php endif; ?>
            <?php if ($booking_info['arena']): ?>
                — <?= htmlspecialchars($booking_info['arena']) ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($_GET['status'])): ?>
        <div class="alert-box success">
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

    <div class="alert-box info">
        <i class="fas fa-info-circle"></i>
        <strong>How it works:</strong> Generate a check-in QR code below before dropping off your child. 
        Staff will scan the code at the front desk. For pickup, generate a check-out code. 
        You can also share codes with other authorized people via email.
    </div>

    <!-- Check-In (Drop-Off) Card -->
    <div class="checkin-card">
        <h3><i class="fas fa-sign-in-alt" style="color: #10b981;"></i> Check-In (Drop-Off)</h3>
        
        <?php if ($checkin_code): ?>
            <div style="text-align: center; margin-bottom: 16px;">
                <span class="code-status active"><i class="fas fa-check-circle"></i> Code Active</span>
            </div>
            <div style="text-align: center;">
                <div class="qr-container">
                    <canvas id="checkin-qr"></canvas>
                </div>
            </div>
            <p style="text-align: center; color: #94a3b8; font-size: 13px; margin: 8px 0;">
                Code: <code style="background: #1e293b; padding: 2px 8px; border-radius: 4px; color: #e2e8f0;"><?= htmlspecialchars(substr($checkin_code['code'], 0, 12)) ?>...</code>
            </p>
            <?php if ($checkin_code['items_description']): ?>
                <p style="color: #94a3b8; font-size: 13px; margin-top: 8px;">
                    <i class="fas fa-box"></i> Items: <?= htmlspecialchars($checkin_code['items_description']) ?>
                </p>
            <?php endif; ?>

            <!-- Share Section -->
            <div class="share-section">
                <h4><i class="fas fa-share-alt"></i> Share with Someone Else</h4>
                <form method="POST" action="process_camp_checkin.php" class="share-form">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="share_code">
                    <input type="hidden" name="code_id" value="<?= $checkin_code['id'] ?>">
                    <input type="text" name="share_name" placeholder="Their name" required>
                    <input type="email" name="share_email" placeholder="Their email address" required>
                    <button type="submit"><i class="fas fa-paper-plane"></i> Send</button>
                </form>
                <?php if ($checkin_code['shared_email']): ?>
                    <p style="color: #94a3b8; font-size: 12px; margin-top: 8px;">
                        <i class="fas fa-check"></i> Shared with <?= htmlspecialchars($checkin_code['shared_name'] ?? $checkin_code['shared_email']) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 16px;">
                <span class="code-status not-generated"><i class="fas fa-circle"></i> Not Generated</span>
            </div>
            <form method="POST" action="process_camp_checkin.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="generate_checkin_code">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <input type="hidden" name="session_id" value="<?= $session_id ?>">
                <input type="hidden" name="athlete_id" value="<?= $athlete_id ?>">
                <input type="hidden" name="code_type" value="checkin">
                
                <div class="items-form">
                    <label style="display: block; font-size: 12px; font-weight: 700; color: #94a3b8; margin-bottom: 8px; text-transform: uppercase;">
                        What is your child bringing? (optional)
                    </label>
                    <textarea name="items_description" placeholder="e.g., Lunch box, hockey equipment, water bottle, show and tell toy..."></textarea>
                </div>
                
                <div style="text-align: center;">
                    <button type="submit" class="btn-generate btn-checkin">
                        <i class="fas fa-qrcode"></i> Generate Check-In Code
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Check-Out (Pickup) Card -->
    <div class="checkin-card">
        <h3><i class="fas fa-sign-out-alt" style="color: #f59e0b;"></i> Check-Out (Pickup)</h3>
        
        <?php if ($checkout_code): ?>
            <div style="text-align: center; margin-bottom: 16px;">
                <span class="code-status active"><i class="fas fa-check-circle"></i> Code Active</span>
            </div>
            <div style="text-align: center;">
                <div class="qr-container">
                    <canvas id="checkout-qr"></canvas>
                </div>
            </div>
            <p style="text-align: center; color: #94a3b8; font-size: 13px; margin: 8px 0;">
                Code: <code style="background: #1e293b; padding: 2px 8px; border-radius: 4px; color: #e2e8f0;"><?= htmlspecialchars(substr($checkout_code['code'], 0, 12)) ?>...</code>
            </p>

            <!-- Share Section -->
            <div class="share-section">
                <h4><i class="fas fa-share-alt"></i> Share with Someone Else for Pickup</h4>
                <form method="POST" action="process_camp_checkin.php" class="share-form">
                    <?= csrfTokenInput() ?>
                    <input type="hidden" name="action" value="share_code">
                    <input type="hidden" name="code_id" value="<?= $checkout_code['id'] ?>">
                    <input type="text" name="share_name" placeholder="Their name" required>
                    <input type="email" name="share_email" placeholder="Their email address" required>
                    <button type="submit"><i class="fas fa-paper-plane"></i> Send</button>
                </form>
                <?php if ($checkout_code['shared_email']): ?>
                    <p style="color: #94a3b8; font-size: 12px; margin-top: 8px;">
                        <i class="fas fa-check"></i> Shared with <?= htmlspecialchars($checkout_code['shared_name'] ?? $checkout_code['shared_email']) ?>
                    </p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; margin-bottom: 16px;">
                <span class="code-status not-generated"><i class="fas fa-circle"></i> Not Generated</span>
            </div>
            <form method="POST" action="process_camp_checkin.php">
                <?= csrfTokenInput() ?>
                <input type="hidden" name="action" value="generate_checkin_code">
                <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                <input type="hidden" name="session_id" value="<?= $session_id ?>">
                <input type="hidden" name="athlete_id" value="<?= $athlete_id ?>">
                <input type="hidden" name="code_type" value="checkout">
                
                <div style="text-align: center;">
                    <button type="submit" class="btn-generate btn-checkout">
                        <i class="fas fa-qrcode"></i> Generate Pickup Code
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <div style="text-align: center; margin-top: 20px;">
        <a href="?page=parent_home" style="color: var(--primary, #7000a4); text-decoration: none; font-weight: 600;">
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
