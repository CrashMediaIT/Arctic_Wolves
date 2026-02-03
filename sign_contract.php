<?php
/**
 * Contract Signing Page
 * Public page for employees to sign their contracts via e-signature
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once 'lib/stirling_pdf.php';

// Set security headers
setSecurityHeaders();

$error = null;
$contract = null;
$success = false;

// Get signing token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    $error = 'Invalid signing link. Please use the link provided in your email.';
} else {
    // Validate token
    try {
        $stmt = $pdo->prepare("
            SELECT ec.*, eo.first_name, eo.last_name
            FROM employee_contracts ec
            LEFT JOIN employee_onboarding eo ON ec.onboarding_id = eo.id
            WHERE ec.signing_token = ?
        ");
        $stmt->execute([$token]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            $error = 'This signing link is invalid or has already been used.';
        } elseif (strtotime($contract['signing_token_expires']) < time()) {
            $error = 'This signing link has expired. Please contact HR to request a new link.';
        } elseif ($contract['status'] === 'signed') {
            $error = 'This contract has already been signed.';
        } elseif ($contract['status'] === 'cancelled') {
            $error = 'This contract has been cancelled.';
        }
    } catch (PDOException $e) {
        error_log("Contract signing error: " . $e->getMessage());
        $error = 'An error occurred. Please try again later.';
    }
}

// Handle signature submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $contract) {
    $signatureData = $_POST['signature_data'] ?? '';
    
    if (empty($signatureData)) {
        $error = 'Please provide your signature.';
    } else {
        // Process the signed contract
        $result = processSignedContract($pdo, $token, $signatureData);
        
        if ($result['success']) {
            $success = true;
            
            // Send confirmation email
            require_once 'mailer.php';
            sendContractSignedEmail(
                $contract['employee_email'],
                $contract['employee_name'],
                $contract['contract_title']
            );
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Contract - Arctic Wolves</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-main: #06080b;
            --bg-card: #0f1419;
            --border: #1e2530;
            --text-primary: #fff;
            --text-secondary: #94a3b8;
            --primary: #6B46C1;
            --primary-hover: #8B5CF6;
            --success: #00ff88;
            --error: #ef4444;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-main);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 600px;
            width: 100%;
        }
        
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            font-size: 24px;
            color: var(--primary);
        }
        
        .logo p {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 5px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: var(--error);
        }
        
        .alert-success {
            background: rgba(0, 255, 136, 0.1);
            border: 1px solid rgba(0, 255, 136, 0.3);
            color: var(--success);
        }
        
        .contract-info {
            background: rgba(107, 70, 193, 0.1);
            border: 1px solid rgba(107, 70, 193, 0.3);
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .contract-info h3 {
            color: var(--primary);
            margin-bottom: 12px;
        }
        
        .contract-info p {
            color: var(--text-secondary);
            margin-bottom: 8px;
        }
        
        .contract-info strong {
            color: var(--text-primary);
        }
        
        .signature-area {
            margin-bottom: 24px;
        }
        
        .signature-area label {
            display: block;
            margin-bottom: 12px;
            font-weight: 500;
        }
        
        .signature-canvas-container {
            background: #fff;
            border-radius: 8px;
            padding: 4px;
        }
        
        #signature-canvas {
            width: 100%;
            height: 200px;
            border: 2px dashed #ccc;
            border-radius: 4px;
            cursor: crosshair;
        }
        
        .signature-actions {
            display: flex;
            gap: 12px;
            margin-top: 12px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .btn-primary {
            background: var(--primary);
            color: #fff;
        }
        
        .btn-primary:hover {
            background: var(--primary-hover);
        }
        
        .btn-secondary {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--bg-main);
            color: var(--text-primary);
        }
        
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 24px;
            padding: 16px;
            background: var(--bg-main);
            border-radius: 8px;
        }
        
        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            width: 18px;
            height: 18px;
            accent-color: var(--primary);
        }
        
        .checkbox-group label {
            color: var(--text-secondary);
            font-size: 14px;
            line-height: 1.5;
        }
        
        .success-content {
            text-align: center;
            padding: 40px 0;
        }
        
        .success-icon {
            width: 80px;
            height: 80px;
            background: rgba(0, 255, 136, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .success-icon i {
            font-size: 36px;
            color: var(--success);
        }
        
        .success-content h2 {
            margin-bottom: 12px;
        }
        
        .success-content p {
            color: var(--text-secondary);
        }
        
        .pdf-preview {
            margin-bottom: 24px;
        }
        
        .pdf-preview iframe {
            width: 100%;
            height: 400px;
            border: 1px solid var(--border);
            border-radius: 8px;
        }
        
        @media (max-width: 480px) {
            .card {
                padding: 24px;
            }
            
            .signature-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1><i class="fas fa-hockey-puck"></i> Arctic Wolves</h1>
                <p>Contract E-Signature</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <p style="text-align: center; color: var(--text-secondary);">
                    If you believe this is an error, please contact HR for assistance.
                </p>
            <?php elseif ($success): ?>
                <div class="success-content">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2>Contract Signed Successfully!</h2>
                    <p>Thank you for signing your <?= htmlspecialchars($contract['contract_title']) ?>.</p>
                    <p style="margin-top: 12px;">A confirmation email has been sent to <?= htmlspecialchars($contract['employee_email']) ?>.</p>
                    <p style="margin-top: 24px; font-size: 14px;">You can close this window now.</p>
                </div>
            <?php else: ?>
                <div class="contract-info">
                    <h3><i class="fas fa-file-contract"></i> <?= htmlspecialchars($contract['contract_title']) ?></h3>
                    <p><strong>Name:</strong> <?= htmlspecialchars($contract['employee_name']) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($contract['employee_email']) ?></p>
                    <p><strong>Date:</strong> <?= date('F j, Y') ?></p>
                </div>
                
                <?php if (!empty($contract['temp_file_path']) && file_exists($contract['temp_file_path'])): ?>
                <div class="pdf-preview">
                    <p style="margin-bottom: 12px; color: var(--text-secondary);">
                        <i class="fas fa-info-circle"></i> Please review the contract above before signing.
                    </p>
                    <!-- In production, you would serve the PDF through a secure endpoint -->
                    <div style="background: var(--bg-main); border-radius: 8px; padding: 40px; text-align: center;">
                        <i class="fas fa-file-pdf" style="font-size: 48px; color: var(--primary); margin-bottom: 16px;"></i>
                        <p>Contract Document</p>
                        <p style="color: var(--text-secondary); font-size: 14px; margin-top: 8px;">
                            Review carefully before signing below
                        </p>
                    </div>
                </div>
                <?php endif; ?>
                
                <form method="POST" id="signature-form">
                    <div class="signature-area">
                        <label><i class="fas fa-pen"></i> Your Signature</label>
                        <div class="signature-canvas-container">
                            <canvas id="signature-canvas"></canvas>
                        </div>
                        <div class="signature-actions">
                            <button type="button" class="btn btn-secondary" id="clear-signature">
                                <i class="fas fa-eraser"></i> Clear
                            </button>
                        </div>
                        <input type="hidden" name="signature_data" id="signature-data">
                    </div>
                    
                    <div class="checkbox-group">
                        <input type="checkbox" id="agree-terms" required>
                        <label for="agree-terms">
                            I have read and understand the contract. By signing below, I agree to be legally bound by its terms and conditions. I confirm that I am <strong><?= htmlspecialchars($contract['employee_name']) ?></strong> and I am authorized to sign this document.
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" style="width: 100%;" id="submit-btn" disabled>
                        <i class="fas fa-signature"></i> Sign Contract
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
    
    <?php if (!$error && !$success): ?>
    <script>
        // Signature pad functionality
        const canvas = document.getElementById('signature-canvas');
        const ctx = canvas.getContext('2d');
        const signatureData = document.getElementById('signature-data');
        const clearBtn = document.getElementById('clear-signature');
        const submitBtn = document.getElementById('submit-btn');
        const agreeTerms = document.getElementById('agree-terms');
        const form = document.getElementById('signature-form');
        
        let isDrawing = false;
        let hasSignature = false;
        
        // Set canvas size
        function resizeCanvas() {
            const container = canvas.parentElement;
            canvas.width = container.clientWidth - 8;
            canvas.height = 200;
            ctx.strokeStyle = '#000';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';
        }
        
        resizeCanvas();
        window.addEventListener('resize', resizeCanvas);
        
        // Drawing functions
        function getPosition(e) {
            const rect = canvas.getBoundingClientRect();
            const x = (e.clientX || e.touches[0].clientX) - rect.left;
            const y = (e.clientY || e.touches[0].clientY) - rect.top;
            return { x, y };
        }
        
        function startDrawing(e) {
            isDrawing = true;
            const pos = getPosition(e);
            ctx.beginPath();
            ctx.moveTo(pos.x, pos.y);
            e.preventDefault();
        }
        
        function draw(e) {
            if (!isDrawing) return;
            const pos = getPosition(e);
            ctx.lineTo(pos.x, pos.y);
            ctx.stroke();
            hasSignature = true;
            updateSubmitButton();
            e.preventDefault();
        }
        
        function stopDrawing() {
            isDrawing = false;
        }
        
        // Mouse events
        canvas.addEventListener('mousedown', startDrawing);
        canvas.addEventListener('mousemove', draw);
        canvas.addEventListener('mouseup', stopDrawing);
        canvas.addEventListener('mouseout', stopDrawing);
        
        // Touch events
        canvas.addEventListener('touchstart', startDrawing);
        canvas.addEventListener('touchmove', draw);
        canvas.addEventListener('touchend', stopDrawing);
        
        // Clear signature
        clearBtn.addEventListener('click', function() {
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            hasSignature = false;
            updateSubmitButton();
        });
        
        // Update submit button state
        function updateSubmitButton() {
            submitBtn.disabled = !(hasSignature && agreeTerms.checked);
        }
        
        agreeTerms.addEventListener('change', updateSubmitButton);
        
        // Form submission
        form.addEventListener('submit', function(e) {
            if (!hasSignature) {
                e.preventDefault();
                alert('Please provide your signature.');
                return;
            }
            
            // Convert canvas to base64
            signatureData.value = canvas.toDataURL('image/png');
            
            // Disable button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    </script>
    <?php endif; ?>
</body>
</html>
