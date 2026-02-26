<?php
/**
 * Registration Page
 * Allows users to register as Parent or Athlete
 * Parents can add multiple athletes during registration
 */
session_start();
require 'db_config.php';
require_once __DIR__ . '/lib/site_branding.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';

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

// Generate CSRF token
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

// Handle parent invitation token
$invitationToken = $_GET['invitation'] ?? $_SESSION['parent_invitation_token'] ?? null;
if (isset($_GET['invitation'])) {
    $_SESSION['parent_invitation_token'] = $_GET['invitation'];
}

// Look up invitation details for display
$invitationInfo = null;
if ($invitationToken && $db_connected) {
    try {
        $inv_stmt = $pdo->prepare("
            SELECT pi.*, u.first_name as inviter_first_name, u.last_name as inviter_last_name
            FROM parent_invitations pi
            INNER JOIN users u ON pi.inviter_id = u.id
            WHERE pi.token = ? AND pi.status = 'pending' AND pi.expires_at > NOW()
        ");
        $inv_stmt->execute([$invitationToken]);
        $invitationInfo = $inv_stmt->fetch(PDO::FETCH_ASSOC);
        if ($invitationInfo) {
            $invitationInfo = decryptUserRow($invitationInfo);
        }
    } catch (PDOException $e) {
        // Table may not exist yet
    }
}

// If already logged in, redirect to dashboard or session intent
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
                
                // Redirect to appropriate page with session/package pre-selected
                if ($intent['template_id']) {
                    header("Location: dashboard.php?page=booking&session=" . $intent['template_id']);
                    exit();
                } elseif ($intent['package_id']) {
                    // Check if this is a camp/program package
                    $pkgCheck = $pdo->prepare("SELECT package_type FROM packages WHERE id = ?");
                    $pkgCheck->execute([$intent['package_id']]);
                    $pkgType = $pkgCheck->fetchColumn();
                    if (in_array($pkgType, ['camp', 'multi_week'])) {
                        header("Location: dashboard.php?page=booking");
                    } else {
                        header("Location: dashboard.php?page=packages&package_id=" . $intent['package_id']);
                    }
                    exit();
                }
            }
        } catch (PDOException $e) {
            error_log("Session intent processing error: " . $e->getMessage());
        }
    }
    header("Location: dashboard.php");
    exit();
}

$error = "";

// Check for error from process_register.php
if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'email_taken':
            $error = "An account with this email address already exists.";
            break;
        case 'invalid_data':
            $error = "Please fill in all required fields correctly.";
            break;
        case 'password_mismatch':
            $error = "Passwords do not match.";
            break;
        case 'database_error':
            $error = "A database error occurred. Please try again later.";
            break;
        case 'blocked':
            $error = "Registration is not available for the provided information. Please contact the administrator.";
            break;
        case 'rate_limited':
            $error = "Too many registration attempts. Please try again later.";
            break;
        case 'captcha_failed':
            $error = "Security verification failed. Please try again.";
            break;
        case 'csrf_invalid':
            $error = "Security token expired. Please refresh and try again.";
            break;
        case 'agreements_not_accepted':
            $error = "You must accept both the Safety Waiver and Privacy Policy to register.";
            break;
        default:
            $error = "An error occurred during registration. Please try again.";
    }
}

// Load reCAPTCHA site key from database (encrypted in system_settings)
$recaptcha_site_key = '';
if ($db_connected && $pdo) {
    try {
        $rc_stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = 'recaptcha_site_key'");
        $rc_stmt->execute();
        $rc_val = $rc_stmt->fetchColumn();
        if (!empty($rc_val) && function_exists('decryptCredential')) {
            $recaptcha_site_key = decryptCredential($rc_val);
        }
    } catch (PDOException $e) {
        // Setting may not exist yet
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join the Team | Arctic Wolves</title>
    
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($site_favicon_url) ?>">
    
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <?php if (!empty($recaptcha_site_key)): ?>
    <script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($recaptcha_site_key); ?>"></script>
    <?php endif; ?>
    <style>
        body { 
            margin: 0; 
            min-height: 100vh; 
            display: flex; 
            background: #06080b; 
            font-family: 'Inter', sans-serif; 
            overflow-x: hidden;
        }

        /* LEFT SIDE: HERO / BRANDING */
        .split-left {
            flex: 1;
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
            position: sticky;
            top: 0;
            height: 100vh;
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

        /* RIGHT SIDE: REGISTRATION FORM */
        .split-right {
            flex: 1.2;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            background: #06080b;
            padding: 40px;
            overflow-y: auto;
            min-height: 100vh;
        }

        .register-card {
            width: 100%;
            max-width: 500px;
            padding: 20px 0;
        }

        /* ROLE SELECTOR */
        .role-selector {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 30px;
        }

        .role-option {
            background: #0d1116;
            border: 2px solid #1e293b;
            border-radius: 12px;
            padding: 25px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-option:hover {
            border-color: #6B46C1;
            transform: translateY(-2px);
        }

        .role-option.selected {
            border-color: var(--neon);
            background: rgba(107, 70, 193, 0.1);
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-icon {
            font-size: 36px;
            color: #64748b;
            margin-bottom: 12px;
            transition: color 0.3s;
        }

        .role-option.selected .role-icon {
            color: var(--neon);
        }

        .role-title {
            font-size: 18px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }

        .role-desc {
            font-size: 12px;
            color: #64748b;
            line-height: 1.4;
        }

        /* FORM SECTIONS */
        .form-section {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .form-section.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--neon);
        }

        /* INPUT BOXES */
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

        .input-box input, .input-box select {
            width: 100%;
            background: transparent;
            border: none;
            color: #fff;
            outline: none;
            font-size: 14px;
        }

        .input-box select {
            cursor: pointer;
        }

        .input-box select option {
            background: #0d1116;
            color: #fff;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        /* ATHLETE CARDS */
        .athletes-container {
            margin-bottom: 20px;
        }

        .athlete-card {
            background: #0d1116;
            border: 1px solid #1e293b;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 15px;
            position: relative;
        }

        .athlete-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .athlete-number {
            font-size: 14px;
            font-weight: 700;
            color: var(--neon);
        }

        .remove-athlete-btn {
            background: transparent;
            border: none;
            color: #ef4444;
            cursor: pointer;
            font-size: 14px;
            padding: 5px 10px;
            border-radius: 4px;
            transition: all 0.2s;
        }

        .remove-athlete-btn:hover {
            background: rgba(239, 68, 68, 0.1);
        }

        .add-athlete-btn {
            width: 100%;
            padding: 15px;
            background: transparent;
            border: 2px dashed #1e293b;
            border-radius: 8px;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .add-athlete-btn:hover {
            border-color: var(--neon);
            color: var(--neon);
            background: rgba(107, 70, 193, 0.05);
        }

        /* CHECKBOX */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
            padding: 15px;
            background: rgba(107, 70, 193, 0.05);
            border-radius: 6px;
            border: 1px solid #1e293b;
        }

        .checkbox-group input[type="checkbox"] {
            margin-top: 3px;
            accent-color: var(--neon);
            width: 16px;
            height: 16px;
        }

        .checkbox-label {
            font-size: 13px;
            color: #94a3b8;
            line-height: 1.5;
        }

        .checkbox-label strong {
            color: #fff;
        }

        /* ERROR MESSAGE */
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 12px;
            border-radius: 6px;
            font-size: 13px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* MOBILE RESPONSIVENESS */
        @media (max-width: 900px) {
            body {
                flex-direction: column;
            }
            .split-left { 
                display: none; 
            }
            .split-right { 
                flex: 1; 
                padding: 20px; 
                min-height: auto;
            }
            .form-row {
                grid-template-columns: 1fr;
            }
            .role-selector {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="split-left">
        <div class="brand-content">
            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Logo" style="height: 80px; margin-bottom: 20px;">
            <h1>ARCTIC <span style="color: var(--neon);">WOLVES</span></h1>
            <p>Join our community of dedicated athletes and parents. Track progress, book sessions, and dominate the ice.</p>
        </div>
        
        <div style="position: absolute; bottom: 30px; z-index: 2; font-size: 12px; color: rgba(255,255,255,0.4);">
            &copy; <?php echo date('Y'); ?> Arctic Wolves Performance.
        </div>
    </div>

    <div class="split-right">
        <div class="register-card">
            
            <div style="text-align: center; margin-bottom: 30px;">
                <h2 style="font-size: 24px; color: #fff; margin-bottom: 5px;">Create Your Account</h2>
                <p style="color: #64748b; font-size: 14px; margin: 0;">Select your role to get started</p>
            </div>

            <?php if($error): ?>
                <div class="error-message">
                    <i class="fa-solid fa-circle-exclamation"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="process_register.php" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="recaptcha_token" id="recaptchaToken" value="">
                <?php if ($invitationToken): ?>
                    <input type="hidden" name="invitation_token" value="<?php echo htmlspecialchars($invitationToken); ?>">
                <?php endif; ?>
                
                <?php if ($invitationInfo): ?>
                    <div style="padding: 16px; border-radius: 8px; margin-bottom: 20px; background: rgba(107, 70, 193, 0.1); border: 1px solid #6B46C1; color: #e2e8f0;">
                        <i class="fas fa-envelope-open-text" style="color: #6B46C1;"></i>
                        <strong>You've been invited!</strong>
                        <p style="margin: 8px 0 0 0; font-size: 13px; color: #94a3b8;">
                            <?= htmlspecialchars(($invitationInfo['inviter_first_name'] ?? '') . ' ' . ($invitationInfo['inviter_last_name'] ?? '')) ?>
                            has invited you as a <strong style="color: #e2e8f0;"><?= htmlspecialchars($invitationInfo['relationship'] ?? 'parent') ?></strong> to manage their athletes.
                            Register as a Parent below to accept.
                        </p>
                    </div>
                <?php endif; ?>
                
                <!-- Role Selection -->
                <div class="role-selector">
                    <label class="role-option" id="athleteOption">
                        <input type="radio" name="role" value="athlete" checked>
                        <div class="role-icon"><i class="fa-solid fa-skating"></i></div>
                        <div class="role-title">Athlete</div>
                        <div class="role-desc">I'm a player looking to improve my skills</div>
                    </label>
                    
                    <label class="role-option" id="parentOption">
                        <input type="radio" name="role" value="parent">
                        <div class="role-icon"><i class="fa-solid fa-users"></i></div>
                        <div class="role-title">Parent</div>
                        <div class="role-desc">I want to manage athletes in my family</div>
                    </label>
                </div>

                <!-- Common Fields -->
                <div class="section-title">
                    <i class="fa-solid fa-user"></i> Your Information
                </div>

                <div class="form-row">
                    <div class="input-box">
                        <label>First Name</label>
                        <input type="text" name="first_name" required placeholder="John" maxlength="100" pattern="[a-zA-ZÀ-ÿ\s'\-]{1,100}" title="Letters, spaces, hyphens and apostrophes only">
                    </div>
                    
                    <div class="input-box">
                        <label>Last Name</label>
                        <input type="text" name="last_name" required placeholder="Smith" maxlength="100" pattern="[a-zA-ZÀ-ÿ\s'\-]{1,100}" title="Letters, spaces, hyphens and apostrophes only">
                    </div>
                </div>

                <div class="input-box">
                    <label>Email Address</label>
                    <input type="email" name="email" required placeholder="name@example.com" maxlength="255">
                </div>

                <div class="input-box">
                    <label>Phone Number (Optional)</label>
                    <input type="tel" name="phone" placeholder="(555) 555-5555" maxlength="20" pattern="[0-9+\-\s().]{0,20}" title="Digits, plus sign, dashes, and parentheses only">
                </div>

                <!-- Athlete-only fields -->
                <div class="form-section active" id="athleteFields">
                    <div class="form-row">
                        <div class="input-box">
                            <label>Date of Birth</label>
                            <input type="date" name="birth_date">
                        </div>
                        
                        <div class="input-box">
                            <label>Position</label>
                            <select name="position">
                                <option value="">Select Position</option>
                                <option value="Forward">Forward</option>
                                <option value="Defense">Defense</option>
                                <option value="Goalie">Goalie</option>
                                <option value="Center">Center</option>
                                <option value="Left Wing">Left Wing</option>
                                <option value="Right Wing">Right Wing</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Parent-only fields: Athletes Management -->
                <div class="form-section" id="parentFields">
                    <div class="section-title">
                        <i class="fa-solid fa-child"></i> Add Your Athletes
                    </div>
                    
                    <div class="athletes-container" id="athletesContainer">
                        <!-- Athlete cards will be added here dynamically -->
                    </div>
                    
                    <button type="button" class="add-athlete-btn" onclick="addAthleteCard()">
                        <i class="fa-solid fa-plus"></i> Add Another Athlete
                    </button>
                </div>

                <!-- Password fields -->
                <div class="section-title" style="margin-top: 20px;">
                    <i class="fa-solid fa-lock"></i> Create Password
                </div>

                <div class="input-box">
                    <label>Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="password" id="password-field" required placeholder="••••••••" minlength="8" style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('password-field', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <div class="input-box">
                    <label>Confirm Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="confirm_password" id="confirm-password-field" required placeholder="••••••••" minlength="8" style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('confirm-password-field', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Agreements Section -->
                <div class="section-title" style="margin-top: 20px;">
                    <i class="fa-solid fa-file-signature"></i> Agreements & Privacy
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="waiver_accepted" id="waiver_accepted" required>
                    <label class="checkbox-label" for="waiver_accepted">
                        <strong>Hockey Player Safety Waiver *</strong><br>
                        I have read and agree to the <a href="#" onclick="showAgreementModal('waiver'); return false;" style="color: var(--neon); text-decoration: underline;">Hockey Player Safety Waiver</a> based on Hockey Canada best practices. I acknowledge the inherent risks of participating in hockey activities and agree to follow all safety protocols.
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="privacy_accepted" id="privacy_accepted" required>
                    <label class="checkbox-label" for="privacy_accepted">
                        <strong>Privacy Policy *</strong><br>
                        I have read and agree to the <a href="#" onclick="showAgreementModal('privacy'); return false;" style="color: var(--neon); text-decoration: underline;">Privacy Policy & Data Usage Agreement</a>. I understand that my data will not be shared with outside companies for data mining. Evaluations may be shared with my current team coaches.
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="share_evaluations_potential_teams" id="share_evaluations_potential_teams">
                    <label class="checkbox-label" for="share_evaluations_potential_teams">
                        <strong>Share Evaluations with Potential Teams (Optional)</strong><br>
                        I consent to having my evaluations shared with potential teams I may play for. This can help with recruitment and team placement opportunities.
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="promotional_opt_in" id="promotional_opt_in" checked>
                    <label class="checkbox-label" for="promotional_opt_in">
                        <strong>Promotional Material Consent (Optional)</strong><br>
                        I consent to having photos and videos of me/my child used in promotional materials (website, social media, marketing). Uncheck to opt out while still allowing technology use for training purposes.
                    </label>
                </div>

                <button type="submit" class="btn-primary" style="width: 100%; padding: 14px; font-size: 14px; border: none; cursor: pointer; border-radius: 6px; font-weight: 700; letter-spacing: 0.5px; margin-top: 10px;">
                    CREATE ACCOUNT
                </button>

                <!-- Agreement Modal -->
                <div id="agreementModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.85); z-index:9999; justify-content:center; align-items:center; padding:20px;">
                    <div style="background:#0d1116; border:1px solid #1e293b; border-radius:12px; max-width:700px; width:100%; max-height:80vh; overflow-y:auto; padding:30px; position:relative;">
                        <button type="button" onclick="closeAgreementModal()" style="position:absolute; top:15px; right:15px; background:none; border:none; color:#64748b; font-size:24px; cursor:pointer;">&times;</button>
                        <div id="agreementModalContent"></div>
                        <button type="button" onclick="closeAgreementModal()" style="margin-top:20px; width:100%; padding:12px; background:var(--neon); border:none; color:#fff; border-radius:6px; font-weight:700; cursor:pointer;">I Understand</button>
                    </div>
                </div>
            
            </form>

            <div style="margin-top: 30px; text-align: center; font-size: 13px; color: #64748b;">
                Already have an account? <a href="login.php" style="color: #fff; text-decoration: none; font-weight: 700;">Sign In</a>
            </div>

        </div>
    </div>

    <script>
        let athleteCount = 0;

        // Role selection handling
        const roleOptions = document.querySelectorAll('.role-option');
        const athleteFields = document.getElementById('athleteFields');
        const parentFields = document.getElementById('parentFields');
        const athletesContainer = document.getElementById('athletesContainer');

        roleOptions.forEach(option => {
            option.addEventListener('click', function() {
                roleOptions.forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
                
                const role = this.querySelector('input').value;
                
                if (role === 'parent') {
                    athleteFields.classList.remove('active');
                    parentFields.classList.add('active');
                    
                    // Add first athlete card if none exist
                    if (athleteCount === 0) {
                        addAthleteCard();
                    }
                } else {
                    athleteFields.classList.add('active');
                    parentFields.classList.remove('active');
                }
            });
        });

        // Set initial state
        document.getElementById('athleteOption').classList.add('selected');

        function addAthleteCard() {
            athleteCount++;
            const card = document.createElement('div');
            card.className = 'athlete-card';
            card.id = `athlete-card-${athleteCount}`;
            
            card.innerHTML = `
                <div class="athlete-card-header">
                    <span class="athlete-number"><i class="fa-solid fa-user"></i> Athlete ${athleteCount}</span>
                    ${athleteCount > 1 ? `<button type="button" class="remove-athlete-btn" onclick="removeAthleteCard(${athleteCount})"><i class="fa-solid fa-trash"></i> Remove</button>` : ''}
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>First Name</label>
                        <input type="text" name="athletes[${athleteCount}][first_name]" required placeholder="First name">
                    </div>
                    
                    <div class="input-box">
                        <label>Last Name</label>
                        <input type="text" name="athletes[${athleteCount}][last_name]" required placeholder="Last name">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="input-box">
                        <label>Date of Birth</label>
                        <input type="date" name="athletes[${athleteCount}][birth_date]">
                    </div>
                    
                    <div class="input-box">
                        <label>Position</label>
                        <select name="athletes[${athleteCount}][position]">
                            <option value="">Select Position</option>
                            <option value="Forward">Forward</option>
                            <option value="Defense">Defense</option>
                            <option value="Goalie">Goalie</option>
                            <option value="Center">Center</option>
                            <option value="Left Wing">Left Wing</option>
                            <option value="Right Wing">Right Wing</option>
                        </select>
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="athletes[${athleteCount}][use_alt_email]" id="alt-email-${athleteCount}" onchange="toggleAltEmail(${athleteCount})">
                    <label class="checkbox-label" for="alt-email-${athleteCount}">
                        <strong>Use alternate email for this athlete</strong><br>
                        By default, notifications will be sent to your (parent's) email address.
                    </label>
                </div>
                
                <div class="input-box" id="alt-email-box-${athleteCount}" style="display: none;">
                    <label>Alternate Email (Optional)</label>
                    <input type="email" name="athletes[${athleteCount}][alt_email]" placeholder="athlete@example.com">
                </div>
            `;
            
            athletesContainer.appendChild(card);
            updateAthleteNumbers();
        }

        function removeAthleteCard(id) {
            const card = document.getElementById(`athlete-card-${id}`);
            if (card) {
                card.remove();
                updateAthleteNumbers();
            }
        }

        function updateAthleteNumbers() {
            const cards = athletesContainer.querySelectorAll('.athlete-card');
            cards.forEach((card, index) => {
                const numberSpan = card.querySelector('.athlete-number');
                numberSpan.innerHTML = `<i class="fa-solid fa-user"></i> Athlete ${index + 1}`;
            });
        }

        function toggleAltEmail(id) {
            const checkbox = document.getElementById(`alt-email-${id}`);
            const emailBox = document.getElementById(`alt-email-box-${id}`);
            
            if (checkbox.checked) {
                emailBox.style.display = 'block';
            } else {
                emailBox.style.display = 'none';
            }
        }

        // Form validation with input sanitization and reCAPTCHA
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const form = this;

            // Sanitize and validate first/last name (letters, spaces, hyphens, apostrophes only)
            const namePattern = /^[a-zA-ZÀ-ÿ\s'\-]{1,100}$/;
            const firstName = document.querySelector('input[name="first_name"]').value.trim();
            const lastName = document.querySelector('input[name="last_name"]').value.trim();
            if (!namePattern.test(firstName) || !namePattern.test(lastName)) {
                alert('Names may only contain letters, spaces, hyphens, and apostrophes.');
                return false;
            }

            // Validate email format
            const email = document.querySelector('input[name="email"]').value.trim();
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                alert('Please enter a valid email address.');
                return false;
            }

            // Validate phone (optional, digits and + only)
            const phone = document.querySelector('input[name="phone"]').value.trim();
            if (phone && !/^[0-9+\-\s().]{0,20}$/.test(phone)) {
                alert('Please enter a valid phone number.');
                return false;
            }

            const password = document.querySelector('input[name="password"]').value;
            const confirmPassword = document.querySelector('input[name="confirm_password"]').value;
            
            if (password !== confirmPassword) {
                alert('Passwords do not match!');
                return false;
            }
            
            if (password.length < 8) {
                alert('Password must be at least 8 characters long.');
                return false;
            }

            // Password complexity: at least one uppercase, one lowercase, one digit
            if (!/[A-Z]/.test(password) || !/[a-z]/.test(password) || !/[0-9]/.test(password)) {
                alert('Password must contain at least one uppercase letter, one lowercase letter, and one digit.');
                return false;
            }

            // Validate agreements
            if (!document.getElementById('waiver_accepted').checked) {
                alert('You must accept the Hockey Player Safety Waiver to register.');
                return false;
            }
            if (!document.getElementById('privacy_accepted').checked) {
                alert('You must accept the Privacy Policy to register.');
                return false;
            }
            
            // If parent role is selected, ensure at least one athlete is added
            const role = document.querySelector('input[name="role"]:checked').value;
            if (role === 'parent') {
                const athleteCards = document.querySelectorAll('.athlete-card');
                if (athleteCards.length === 0) {
                    alert('Please add at least one athlete.');
                    return false;
                }
            }

            // Fetch reCAPTCHA v3 token before submitting (if configured)
            const siteKey = <?php echo json_encode($recaptcha_site_key); ?>;
            if (siteKey && typeof grecaptcha !== 'undefined') {
                grecaptcha.ready(function() {
                    grecaptcha.execute(siteKey, {action: 'register'}).then(function(token) {
                        document.getElementById('recaptchaToken').value = token;
                        form.submit();
                    });
                });
            } else {
                form.submit();
            }
        });

        // Agreement modal functions
        const agreementContent = {
            waiver: `<h3 style="color:#fff; margin-bottom:15px;"><i class="fa-solid fa-shield-halved" style="color:var(--neon);"></i> Hockey Player Safety Waiver</h3>
                <p style="color:#94a3b8; margin-bottom:10px;">Based on Hockey Canada Best Practices</p>
                <div style="color:#ccc; line-height:1.8;">
                <p>By signing this waiver, I acknowledge and agree to the following:</p>
                <ol style="padding-left:20px;">
                <li><strong style="color:#fff;">Assumption of Risk:</strong> I understand that participation in hockey activities involves inherent risks including physical injury, concussion, sprains, fractures, and other bodily harm.</li>
                <li><strong style="color:#fff;">Safety Compliance:</strong> I agree to follow all safety guidelines and protocols as outlined by Hockey Canada.</li>
                <li><strong style="color:#fff;">Medical Disclosure:</strong> I confirm that I have disclosed any medical conditions that may affect my ability to safely participate.</li>
                <li><strong style="color:#fff;">Equipment Responsibility:</strong> I agree to wear all required protective equipment during practices and games.</li>
                <li><strong style="color:#fff;">Concussion Protocol:</strong> I understand and agree to comply with Hockey Canada&apos;s concussion protocol.</li>
                <li><strong style="color:#fff;">Code of Conduct:</strong> I agree to conduct myself in a respectful and sportsmanlike manner.</li>
                <li><strong style="color:#fff;">Release of Liability:</strong> I release and hold harmless the organization from claims arising from participation, except in cases of gross negligence.</li>
                </ol>
                </div>`,
            privacy: `<h3 style="color:#fff; margin-bottom:15px;"><i class="fa-solid fa-lock" style="color:var(--neon);"></i> Privacy Policy & Data Usage Agreement</h3>
                <div style="color:#ccc; line-height:1.8;">
                <h4 style="color:#fff; margin:15px 0 8px;">Data Collection & Usage</h4>
                <ul style="padding-left:20px;">
                <li><strong style="color:#fff;">We will NOT share any personal information with outside companies for data mining purposes.</strong></li>
                <li>Your data is used solely for the operation of our hockey programs.</li>
                </ul>
                <h4 style="color:#fff; margin:15px 0 8px;">Evaluation Sharing</h4>
                <ul style="padding-left:20px;">
                <li><strong style="color:#fff;">Current Teams:</strong> Evaluations are accessible by your current team coaches.</li>
                <li><strong style="color:#fff;">Potential Teams:</strong> Evaluations may be shared with potential teams only with your explicit consent.</li>
                </ul>
                <h4 style="color:#fff; margin:15px 0 8px;">Technology & Media Usage</h4>
                <ul style="padding-left:20px;">
                <li>We use photos and videos for training analysis and coaching purposes.</li>
                <li><strong style="color:#fff;">Promotional Material:</strong> You may opt in or out of having photos/videos used in promotional materials.</li>
                </ul>
                <h4 style="color:#fff; margin:15px 0 8px;">Data Security</h4>
                <ul style="padding-left:20px;">
                <li>All personal information is encrypted at rest and in transit.</li>
                <li>We follow Canadian privacy laws (PIPEDA) in all data handling.</li>
                </ul>
                </div>`
        };

        function showAgreementModal(type) {
            const modal = document.getElementById('agreementModal');
            const content = document.getElementById('agreementModalContent');
            content.innerHTML = agreementContent[type] || '';
            modal.style.display = 'flex';
        }

        function closeAgreementModal() {
            document.getElementById('agreementModal').style.display = 'none';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeAgreementModal();
        });

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
