<?php
/**
 * Two-Factor Authentication Verification Page
 * Shown after successful password login when 2FA is enabled
 */
session_start();
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/pwa_detect.php';

// If no 2FA pending, redirect
if (empty($_SESSION['2fa_pending'])) {
    if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']) {
        redirectToPwaIfMobile('pwa.php', 'pwa_tablet.php');
        header("Location: dashboard.php");
    } else {
        redirectToPwaIfMobile('pwa_login.php', 'pwa_login.php');
        header("Location: login.php");
    }
    exit();
}

setSecurityHeaders();
CSRFProtection::generateToken();
generateCSRFToken();

$csrf_token = $_SESSION['csrf_token'] ?? '';
$error = $_SESSION['2fa_error'] ?? '';
unset($_SESSION['2fa_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication - Arctic Wolves</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background: #0a0a0f; color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .verify-container { width: 100%; max-width: 420px; padding: 20px; }
        .verify-card { background: #16161F; border: 1px solid #2D2D3F; border-radius: 16px; padding: 40px 32px; text-align: center; }
        .verify-icon { width: 64px; height: 64px; background: rgba(107, 70, 193, 0.15); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; }
        .verify-icon i { font-size: 28px; color: #6B46C1; }
        .verify-card h1 { font-size: 22px; font-weight: 900; margin-bottom: 8px; }
        .verify-card p { color: #94a3b8; font-size: 14px; margin-bottom: 24px; line-height: 1.5; }
        .code-input { width: 100%; padding: 16px; background: #0a0a0f; border: 2px solid #2D2D3F; border-radius: 10px; color: #fff; font-size: 24px; font-weight: 700; text-align: center; letter-spacing: 8px; outline: none; font-family: 'Inter', monospace; }
        .code-input:focus { border-color: #6B46C1; }
        .code-input::placeholder { font-size: 14px; letter-spacing: normal; color: #475569; }
        .verify-btn { width: 100%; padding: 14px; background: #6B46C1; color: #fff; border: none; border-radius: 10px; font-size: 15px; font-weight: 700; cursor: pointer; margin-top: 16px; transition: background 0.2s; }
        .verify-btn:hover { background: #7C3AED; }
        .verify-btn:disabled { opacity: 0.6; cursor: not-allowed; }
        .error-msg { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; border: 1px solid rgba(239, 68, 68, 0.2); }
        .backup-link { display: block; margin-top: 16px; color: #94a3b8; font-size: 13px; text-decoration: none; }
        .backup-link:hover { color: #6B46C1; }
        .cancel-link { display: block; margin-top: 12px; color: #ef4444; font-size: 13px; text-decoration: none; }
        .cancel-link:hover { text-decoration: underline; }
        .spinner { display: none; animation: spin 1s linear infinite; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-icon">
                <i class="fas fa-shield-halved"></i>
            </div>
            <h1>Two-Factor Authentication</h1>
            <p>Enter the 6-digit code from your authenticator app or a backup code</p>
            
            <?php if ($error): ?>
            <div class="error-msg">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form id="verifyForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="verify_login">
                <input type="text" name="code" class="code-input" id="codeInput" maxlength="8" autocomplete="one-time-code" inputmode="numeric" placeholder="000000" autofocus required>
                <button type="submit" class="verify-btn" id="verifyBtn">
                    <span id="btnText"><i class="fas fa-check"></i> Verify</span>
                    <i class="fas fa-spinner spinner" id="btnSpinner"></i>
                </button>
            </form>
            
            <a href="#" class="backup-link" id="backupToggle">
                <i class="fas fa-key"></i> Use a backup code instead
            </a>
            <a href="login.php" class="cancel-link">
                <i class="fas fa-arrow-left"></i> Cancel and return to login
            </a>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var form = document.getElementById('verifyForm');
        var input = document.getElementById('codeInput');
        var btn = document.getElementById('verifyBtn');
        var btnText = document.getElementById('btnText');
        var btnSpinner = document.getElementById('btnSpinner');
        
        // Auto-submit when 6 digits entered
        input.addEventListener('input', function() {
            this.value = this.value.replace(/[^a-zA-Z0-9]/g, '');
            if (this.value.length === 6 && /^\d{6}$/.test(this.value)) {
                form.dispatchEvent(new Event('submit'));
            }
        });
        
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var code = input.value.trim();
            if (!code) return;
            
            btn.disabled = true;
            btnText.style.display = 'none';
            btnSpinner.style.display = 'inline-block';
            
            fetch('process_2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: 'action=verify_login&code=' + encodeURIComponent(code) + '&csrf_token=' + encodeURIComponent(document.querySelector('[name="csrf_token"]').value)
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.href = data.redirect || 'dashboard.php';
                } else {
                    btn.disabled = false;
                    btnText.style.display = 'inline';
                    btnSpinner.style.display = 'none';
                    input.value = '';
                    input.focus();
                    
                    var errorDiv = document.querySelector('.error-msg');
                    if (!errorDiv) {
                        errorDiv = document.createElement('div');
                        errorDiv.className = 'error-msg';
                        form.parentNode.insertBefore(errorDiv, form);
                    }
                    errorDiv.textContent = '';
                    var icon = document.createElement('i');
                    icon.className = 'fas fa-exclamation-circle';
                    errorDiv.appendChild(icon);
                    errorDiv.appendChild(document.createTextNode(' ' + (data.message || 'Invalid code')));
                }
            })
            .catch(function() {
                btn.disabled = false;
                btnText.style.display = 'inline';
                btnSpinner.style.display = 'none';
                showToast('An error occurred. Please try again.', 'error');
            });
        });
        
        // Toggle backup code mode
        document.getElementById('backupToggle').addEventListener('click', function(e) {
            e.preventDefault();
            input.placeholder = 'BACKUPCODE';
            input.setAttribute('maxlength', '8');
            input.setAttribute('inputmode', 'text');
            this.textContent = 'Enter your 8-character backup code above';
        });
    });
    </script>
</body>
</html>
