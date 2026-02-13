<?php
/**
 * PWA Login - Mobile-optimized login page
 * Falls through to the same session/auth logic as login.php
 */
require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/pwa_detect.php';

// If already logged in, go to appropriate PWA dashboard
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    $pref = getPwaViewPreference();
    if ($pref === 'pwa_tablet') {
        header("Location: pwa_tablet.php");
    } else {
        header("Location: pwa.php");
    }
    exit();
}

// Allow desktop override
if (isset($_GET['view']) && $_GET['view'] === 'desktop') {
    $_SESSION['pwa_view_override'] = 'desktop';
    header("Location: login.php");
    exit();
}

// Generate CSRF token
CSRFProtection::generateToken();
generateCSRFToken();

$error = $_GET['error'] ?? '';
$errorMessages = [
    'invalid'       => 'Invalid email or password.',
    'not_verified'  => 'Account not verified. Please check your email.',
    'deactivated'   => 'Account deactivated. Contact administrator.',
    'rate_limited'  => 'Too many attempts. Please wait.',
    'csrf'          => 'Session expired. Please try again.',
    'force_password' => 'Password change required.',
];
$errorText = $errorMessages[$error] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover, maximum-scale=1">
    <meta name="theme-color" content="#6B46C1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Arctic Wolves - Sign In</title>
    <link rel="manifest" href="manifest.json">
    <link rel="icon" type="image/png" href="assets/pwa/icon-192.png">
    <link rel="apple-touch-icon" href="assets/pwa/icon-192.png">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style-guide.css">
    <link rel="stylesheet" href="css/pwa.css">
</head>
<body class="pwa-body">

<div class="pwa-login-wrapper">
    <div class="pwa-login-box">
        <div class="pwa-login-brand">
            <img src="https://images.crashmedia.ca/images/2026/01/21/ArcticWolves.png" alt="Arctic Wolves">
            <h1>ARCTIC <span>WOLVES</span></h1>
            <p style="color:var(--text-secondary);font-size:13px;margin-top:4px;">Sign in to your account</p>
        </div>

        <div class="pwa-login-error <?= $errorText ? 'show' : '' ?>" id="loginError">
            <i class="fas fa-exclamation-circle"></i> <span id="loginErrorText"><?= htmlspecialchars($errorText) ?></span>
        </div>

        <form method="POST" action="process_login.php" id="pwaLoginForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
            <input type="hidden" name="pwa_login" value="1">

            <div class="pwa-login-field">
                <label for="email">Email</label>
                <input type="email" name="email" id="email" placeholder="you@example.com" required autocomplete="email" inputmode="email">
            </div>

            <div class="pwa-login-field">
                <label for="password">Password</label>
                <input type="password" name="password" id="password" placeholder="Your password" required autocomplete="current-password">
            </div>

            <button type="submit" class="pwa-login-btn" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <div class="text-center mt-16">
            <a href="?view=desktop" class="pwa-view-toggle">Switch to Desktop View</a>
        </div>
    </div>

    <div class="text-center mt-16">
        <p style="font-size:11px;color:var(--text-muted);">Arctic Wolves &copy; <?= date('Y') ?></p>
    </div>
</div>

<script>
// Register service worker (relative path for subdirectory deployments)
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('pwa-sw.js').catch(() => {});
}
</script>
</body>
</html>
