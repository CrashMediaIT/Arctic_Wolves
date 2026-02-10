<?php
/**
 * Camp Check-In Code Display
 * Public-facing page that displays a QR code for shared check-in/check-out codes
 * Used when a parent shares a code via email to an alternative pickup person
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/lib/encryption.php';

$code = trim($_GET['code'] ?? '');

if (empty($code)) {
    http_response_code(400);
    $error = 'No code provided.';
} else {
    // Look up code details
    try {
        $stmt = $pdo->prepare("
            SELECT cc.*, 
                   s.title as session_title, s.session_date, s.session_time, s.arena
            FROM camp_checkin_codes cc
            INNER JOIN sessions s ON cc.session_id = s.id
            WHERE cc.code = ?
        ");
        $stmt->execute([$code]);
        $code_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$code_record) {
            $error = 'Invalid code. This QR code is not recognized.';
        } elseif ($code_record['is_used']) {
            $error = 'This code has already been used.';
        } elseif (strtotime($code_record['expires_at']) < time()) {
            $error = 'This code has expired.';
        }
    } catch (PDOException $e) {
        $error = 'System error. Please try again later.';
        error_log("Camp checkin scan error: " . $e->getMessage());
    }
}

$code_type_label = isset($code_record) && $code_record['code_type'] === 'checkout' ? 'Check-Out (Pickup)' : 'Check-In (Drop-Off)';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($code_type_label) ?> | Arctic Wolves</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #6B46C1; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: #0A0A0F; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .container { max-width: 450px; width: 100%; background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px; padding: 40px; text-align: center; }
        .logo { margin-bottom: 24px; }
        .logo h1 { font-size: 22px; font-weight: 900; letter-spacing: -1px; }
        .logo h1 span { color: var(--primary); }
        .qr-container { display: inline-block; padding: 20px; background: #fff; border-radius: 12px; margin: 20px 0; }
        .qr-container canvas { display: block; }
        .info { color: #94a3b8; font-size: 14px; margin: 12px 0; line-height: 1.6; }
        .info strong { color: #e2e8f0; }
        .badge { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 20px; font-size: 13px; font-weight: 700; margin-bottom: 16px; }
        .badge.checkin { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid #10b981; }
        .badge.checkout { background: rgba(245, 158, 11, 0.1); color: #f59e0b; border: 1px solid #f59e0b; }
        .error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 20px; border-radius: 8px; }
        .instruction { color: #64748b; font-size: 13px; margin-top: 20px; padding-top: 16px; border-top: 1px solid #2D2D3F; }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>ARCTIC <span>WOLVES</span></h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle" style="font-size: 36px; display: block; margin-bottom: 12px;"></i>
                <p><?= htmlspecialchars($error) ?></p>
            </div>
        <?php else: ?>
            <div class="badge <?= $code_record['code_type'] ?>">
                <i class="fas fa-<?= $code_record['code_type'] === 'checkin' ? 'sign-in-alt' : 'sign-out-alt' ?>"></i>
                <?= htmlspecialchars($code_type_label) ?>
            </div>

            <div class="qr-container">
                <canvas id="scan-qr"></canvas>
            </div>

            <div class="info">
                <p><strong><?= htmlspecialchars($code_record['session_title'] ?? 'Camp Session') ?></strong></p>
                <p><?= date('l, F j, Y', strtotime($code_record['session_date'])) ?>
                <?php if ($code_record['session_time']): ?>
                    at <?= date('g:i A', strtotime($code_record['session_time'])) ?>
                <?php endif; ?>
                </p>
                <?php if ($code_record['arena']): ?>
                    <p><?= htmlspecialchars($code_record['arena']) ?></p>
                <?php endif; ?>
                <?php if ($code_record['items_description']): ?>
                    <p style="margin-top: 8px;"><i class="fas fa-box"></i> Items: <?= htmlspecialchars($code_record['items_description']) ?></p>
                <?php endif; ?>
            </div>

            <div class="instruction">
                <i class="fas fa-info-circle"></i> Show this QR code to the front desk staff for scanning.
            </div>

            <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
            <script>
            document.addEventListener('DOMContentLoaded', function() {
                var canvas = document.getElementById('scan-qr');
                QRCode.toCanvas(canvas, <?= json_encode($code_record['code']) ?>, {
                    width: 250,
                    margin: 2,
                    color: { dark: '#000000', light: '#ffffff' }
                });
            });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
