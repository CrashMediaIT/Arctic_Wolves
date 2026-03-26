<?php
require_once __DIR__ . '/config/session.php';
session_start();
require 'db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/pwa_detect.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Detect POS subdomain (pos.arcticwolves.ca) - redirect to kiosk login
// Strict validation: must end with arcticwolves.ca
$host = $_SERVER['HTTP_HOST'] ?? '';
$isPosSubdomain = (
    strpos($host, 'pos.') === 0 && 
    (preg_match('/^pos\.arcticwolves\.ca$/i', $host) || preg_match('/^pos\..*\.arcticwolves\.ca$/i', $host))
);
if ($isPosSubdomain) {
    header("Location: pos_kiosk.php");
    exit();
}

// Detect Scoreboard subdomain (scoreboard.arcticwolves.ca)
// Scoreboard uses the main login flow; after login, user is redirected back to scoreboard.php
$isScoreboardSubdomain = (
    strpos($host, 'scoreboard.') === 0 &&
    (preg_match('/^scoreboard\.arcticwolves\.ca$/i', $host) || preg_match('/^scoreboard\..*\.arcticwolves\.ca$/i', $host))
);

// PWA: redirect mobile phones to PWA login
redirectToPwaIfMobile('pwa_login.php', 'pwa_login.php');

/**
 * Record login attempt in login_history table
 */
function recordLoginHistory($pdo, $user_id, $status, $failure_reason = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO login_history (user_id, login_time, ip_address, user_agent, login_status, failure_reason, last_activity)
            VALUES (?, NOW(), ?, ?, ?, ?, CASE WHEN ? = 'success' THEN NOW() ELSE NULL END)
        ");
        $stmt->execute([
            $user_id,
            getClientIP(),
            $_SERVER['HTTP_USER_AGENT'] ?? null,
            $status,
            $failure_reason,
            $status
        ]);
    } catch (PDOException $e) {
        error_log("Failed to record login history: " . $e->getMessage());
    }
}

// Generate CSRF token for this session
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || !$pdo) {
    die("Database connection failed. Please check your configuration. Error: " . ($db_error ?? 'Unknown error'));
}

// Handle session intent from public sessions page
$sessionIntent = $_GET['session_intent'] ?? $_SESSION['session_intent'] ?? null;
if (isset($_GET['session_intent'])) {
    $_SESSION['session_intent'] = $_GET['session_intent'];
}

// If already logged in, redirect to dashboard (or session intent)
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Check if there's a session intent to complete
    if ($sessionIntent && $db_connected) {
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM session_registration_intents 
                WHERE intent_token = ? AND status = 'pending' AND expires_at > NOW()
            ");
            $stmt->execute([$sessionIntent]);
            $intent = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($intent) {
                // Mark intent as completed and link to user
                $pdo->prepare("UPDATE session_registration_intents SET user_id = ?, status = 'completed' WHERE id = ?")
                    ->execute([$_SESSION['user_id'], $intent['id']]);
                
                // Clear session intent
                unset($_SESSION['session_intent']);
                
                // Redirect to booking page with session pre-selected
                if ($intent['template_id']) {
                    header("Location: dashboard.php?page=booking&session=" . $intent['template_id']);
                    exit();
                } elseif ($intent['package_id']) {
                    // Check if this is a camp/program package to redirect to dedicated page
                    $pkgCheck = $pdo->prepare("SELECT package_type FROM packages WHERE id = ?");
                    $pkgCheck->execute([$intent['package_id']]);
                    $pkgType = $pkgCheck->fetchColumn();
                    if (in_array($pkgType, ['camp', 'multi_week'])) {
                        header("Location: dashboard.php?page=booking");
                    } else {
                        header("Location: dashboard.php?page=packages&package_id=" . $intent['package_id']);
                    }
                    exit();
                } elseif (!empty($intent['session_id'])) {
                    header("Location: dashboard.php?page=booking&session_id=" . intval($intent['session_id']));
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Session intent processing error: " . $e->getMessage());
        }
    }

    // Honor redirect parameter from trusted subdomains(e.g. gameplan.arcticwolves.ca)
    $redirect = $_GET['redirect'] ?? '';
    if ($redirect !== '') {
        $parsed = parse_url($redirect);
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        $currentParts = explode('.', explode(':', $currentHost)[0]);
        $parentDomain = (count($currentParts) >= 2)
            ? implode('.', array_slice($currentParts, -2))
            : $currentHost;

        if (
            isset($parsed['host']) && isset($parsed['scheme'])
            && in_array($parsed['scheme'], ['https', 'http'], true)
            && !isset($parsed['user']) && !isset($parsed['pass'])
            && (
                $parsed['host'] === $parentDomain
                || str_ends_with($parsed['host'], '.' . $parentDomain)
            )
        ) {
            // Sanitize to prevent header injection (strip newlines/carriage returns)
            $safeRedirect = str_replace(["\r", "\n", "\0"], '', $redirect);
            header("Location: " . $safeRedirect);
            exit();
        }
    }

    // Scoreboard subdomain: redirect to scoreboard.php instead of dashboard
    if ($isScoreboardSubdomain) {
        header("Location: scoreboard.php");
        exit();
    }

    header("Location: dashboard.php");
    exit();
}

$error = "";
$show_verify_link = false; // Flag to show the "Enter Code" button

// Check if there's a login error from process_login.php
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}

// Check if we should show the verification link
if (isset($_SESSION['show_verify_link']) && $_SESSION['show_verify_link'] === true) {
    $show_verify_link = true;
    unset($_SESSION['show_verify_link']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $email = trim($_POST['email']);
        $pass  = $_POST['password'];
        
        // Fetch User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Verify Password
        if ($user && password_verify($pass, $user['password'])) {
            
            // 1. CHECK VERIFICATION STATUS
            if ($user['is_verified'] === 0) {
                recordLoginHistory($pdo, $user['id'], 'blocked', 'Account not verified');
                $error = "Account pending verification.";
                $show_verify_link = true; // Trigger the verification button
            } else {
                // 2. LOGIN SUCCESS - SET SESSION
                $_SESSION['logged_in'] = true;
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = FieldEncryption::decrypt($user['first_name']) . ' ' . FieldEncryption::decrypt($user['last_name']);
                $_SESSION['user_email'] = $user['email']; // Useful for test emails
                
                recordLoginHistory($pdo, $user['id'], 'success');
                
                // 3. CHECK FORCE PASSWORD CHANGE (Coach-created accounts)
                if (isset($user['force_pass_change']) && $user['force_pass_change'] === 1) {
                    header("Location: force_change_password.php");
                    exit();
                }
                
                // 4. CHECK FOR SESSION INTENT (from public sessions page)
                if ($sessionIntent) {
                    try {
                        $intentStmt = $pdo->prepare("
                            SELECT * FROM session_registration_intents 
                            WHERE intent_token = ? AND status = 'pending' AND expires_at > NOW()
                        ");
                        $intentStmt->execute([$sessionIntent]);
                        $intent = $intentStmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($intent) {
                            // Mark intent as completed and link to user
                            $pdo->prepare("UPDATE session_registration_intents SET user_id = ?, status = 'completed' WHERE id = ?")
                                ->execute([$user['id'], $intent['id']]);
                            
                            // Clear session intent
                            unset($_SESSION['session_intent']);
                            
                            // Redirect to appropriate page with package pre-selected
                            if ($intent['template_id']) {
                                header("Location: dashboard.php?page=booking&session=" . $intent['template_id']);
                                exit();
                            } elseif ($intent['package_id']) {
                                // Check if this is a camp/program package
                                $pkgCheck2 = $pdo->prepare("SELECT package_type FROM packages WHERE id = ?");
                                $pkgCheck2->execute([$intent['package_id']]);
                                $pkgType2 = $pkgCheck2->fetchColumn();
                                if (in_array($pkgType2, ['camp', 'multi_week'])) {
                                    header("Location: dashboard.php?page=booking");
                                } else {
                                    header("Location: dashboard.php?page=packages&package_id=" . $intent['package_id']);
                                }
                                exit();
                            } elseif (!empty($intent['session_id'])) {
                                header("Location: dashboard.php?page=booking&session_id=" . intval($intent['session_id']));
                                exit();
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Session intent processing error on login: " . $e->getMessage());
                    }
                }
                
                // 5. REDIRECT TO DASHBOARD
                header("Location: dashboard.php");
                exit();
            }
        } else {
            if ($user) {
                recordLoginHistory($pdo, $user['id'], 'failed', 'Invalid password');
            }
            $error = "Invalid email address or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login | Arctic Wolves</title>
    
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">

    <style>
        body { 
            margin: 0; 
            height: 100vh; 
            display: flex; 
            background: #06080b; 
            font-family: 'Inter', sans-serif; 
            overflow: hidden; 
        }

        /* LEFT SIDE: HERO / BRANDING */
        .split-left {
            flex: 1.2;
            background: linear-gradient(135deg, rgba(107, 70, 193, 0.1), rgba(6, 8, 11, 0.9)), url('https://images.unsplash.com/photo-1580748141549-71748dbe0bdc?q=80&w=2574&auto=format&fit=crop'); 
            background-size: cover;
            background-position: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 40px;
            color: #fff;
        }
        
        .split-left::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.6);
            z-index: 1;
        }

        .brand-content {
            position: relative;
            z-index: 2;
            text-align: center;
        }

        .brand-content h1 {
            font-size: 3rem;
            font-weight: 900;
            margin: 10px 0;
            letter-spacing: -1px;
        }
        
        .brand-content p {
            font-size: 1.1rem;
            color: rgba(255, 255, 255, 0.8);
            max-width: 400px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* RIGHT SIDE: LOGIN FORM */
        .split-right {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #06080b;
            padding: 40px;
            position: relative;
        }

        .login-card {
            width: 100%;
            max-width: 380px;
        }

        .input-box {
            background: #0d1116;
            border: 1px solid #1e293b;
            border-radius: 6px;
            padding: 12px 15px;
            margin-bottom: 15px;
            transition: 0.2s;
        }
        
        .input-box:focus-within {
            border-color: var(--neon);
            box-shadow: 0 0 0 2px rgba(107, 70, 193, 0.1);
        }

        .input-box label {
            display: block;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 700;
            color: #64748b;
            margin-bottom: 5px;
        }

        .input-box input {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            font-size: 14px;
        }

        /* MOBILE RESPONSIVENESS */
        @media (max-width: 900px) {
            .split-left { display: none; }
            .split-right { flex: 1; padding: 20px; }
        }
    </style>
</head>
<body>

    <div class="split-left">
        <div class="brand-content">
            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" style="height: 80px; margin-bottom: 20px;">
            <h1>ARCTIC <span style="color: var(--neon);">WOLVES</span></h1>
            <p>Player Development. Track your progress. Dominate the ice.</p>
        </div>
        
        <div style="position: absolute; bottom: 30px; z-index: 2; font-size: 12px; color: rgba(255,255,255,0.4);">
            &copy; <?php echo date('Y'); ?> Arctic Wolves Performance.
        </div>
    </div>

    <div class="split-right">
        <div class="login-card">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 24px; color: #fff; margin-bottom: 5px;">Welcome Back</h2>
                <p style="color: #64748b; font-size: 14px; margin: 0;">Please enter your details to sign in.</p>
            </div>

            <?php if($error && !$show_verify_link): ?>
                <div style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if($show_verify_link): ?>
                <div style="background: rgba(107, 70, 193, 0.1); border: 1px solid var(--neon); color: var(--neon); padding: 20px; border-radius: 6px; font-size: 13px; margin-bottom: 25px; text-align: center;">
                    <i class="fa-solid fa-lock" style="font-size: 20px; margin-bottom: 10px; display: block;"></i>
                    <strong style="font-size: 14px; display: block; margin-bottom: 5px;">Account Not Verified</strong>
                    <span style="color: rgba(255,255,255,0.7);">We sent a code to your email.</span>
                    
                    <a href="verify.php" style="display: block; margin-top: 15px; background: var(--neon); color: #000; text-decoration: none; padding: 10px; font-weight: bold; border-radius: 4px;">Enter Code Now</a>
                </div>
            <?php endif; ?>

            <?php if(isset($_SESSION['success_msg'])): ?>
                <div style="background: rgba(0, 255, 136, 0.1); border: 1px solid #00ff88; color: #00ff88; padding: 12px; border-radius: 6px; font-size: 13px; margin-bottom: 25px; display: flex; align-items: center; gap: 10px;">
                    <i class="fa-solid fa-check-circle"></i> <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                
                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com">
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="password" id="password-field" required placeholder="••••••••" style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password-field', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
                    <label style="color: #94a3b8; font-size: 13px; display: flex; align-items: center; gap: 5px; cursor: pointer;">
                        <input type="checkbox" style="accent-color: var(--neon);"> Remember me
                    </label>
                    <a href="forgot_password.php" style="color: var(--neon); font-size: 13px; text-decoration: none; font-weight: 600;">Forgot Password?</a>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; font-size: 14px; border: none; cursor: pointer; border-radius: 6px; font-weight: 700; letter-spacing: 0.5px;">SIGN IN</button>
            
            </form>

            <div style="margin-top: 30px; text-align: center; font-size: 13px; color: #64748b;">
                Don't have an account? <a href="register.php" style="color: #fff; text-decoration: none; font-weight: 700;">Join the Team</a>
            </div>

        </div>
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