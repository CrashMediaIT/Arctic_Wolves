<?php
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Generate CSRF token
generateCSRFToken();

// 1. SECURITY: Ensure user is actually logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

// Optional: Double check if they actually need to be here
// (You could re-query the DB, but session logic from login.php is usually sufficient)
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Security Update | Arctic Wolves</title>
    
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        body { 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            height: 100vh; 
            background: #06080b; 
            font-family: 'Inter', sans-serif;
            margin: 0;
        }
        .security-card {
            width: 100%; 
            max-width: 400px; 
            background: #0d1116; 
            border: 1px solid var(--neon); /* Neon border to indicate alert */
            padding: 40px; 
            border-radius: 8px; 
            text-align: center;
            box-shadow: 0 0 20px rgba(255, 77, 0, 0.1);
        }
    </style>
</head>
<body>
    
    <div class="security-card">
        <div style="margin-bottom: 20px;">
            <i class="fa-solid fa-shield-halved" style="font-size: 40px; color: var(--neon);"></i>
        </div>
        
        <h2 style="color: #fff; margin-bottom: 10px;">Security Update Required</h2>
        
        <p style="color: var(--text-dim); margin-bottom: 30px; font-size: 14px; line-height: 1.6;">
            Your account was created with a temporary password. To secure your account, you must set a new permanent password.
        </p>

        <form action="process_profile_update.php" method="POST">
            <input type="hidden" name="action" value="force_password_reset">
            <input type="hidden" name="user_id" value="<?php echo $_SESSION['user_id']; ?>">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
            
            <div style="text-align: left; margin-bottom: 20px;">
                <label style="font-size: 11px; font-weight: 700; color: var(--text-dim); text-transform: uppercase;">New Secure Password</label>
                <div style="position: relative; display: flex; align-items: center;">
                    <input type="password" name="new_password" id="new-password-field" required minlength="6" placeholder="Minimum 6 characters" 
                           style="width: 100%; padding: 12px; padding-right: 40px; margin-top: 5px; background: #06080b; border: 1px solid var(--border); color: #fff; border-radius: 4px; outline: none;">
                    <button type="button" onclick="togglePasswordVisibility('new-password-field', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; padding: 5px; margin-top: 2.5px;">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; border: none; cursor: pointer; font-weight: 700; border-radius: 4px;">
                Update Password & Login
            </button>
        </form>
    </div>

<script>
function togglePasswordVisibility(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}
</script>
</body>
</html>