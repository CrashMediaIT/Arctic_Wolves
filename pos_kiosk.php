<?php
/**
 * POS Kiosk Mode
 * Front desk staff login with PIN and integrated time tracking
 * Supports pos.arcticwolves.ca subdomain for dedicated POS-only access
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/csrf_protection.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/lib/site_branding.php';

$site_logo_url = getSiteLogoUrl($pdo ?? null);
$site_favicon_url = getSiteFaviconUrl($pdo ?? null);

// Detect POS subdomain (pos.arcticwolves.ca)
// Strict validation: must end with arcticwolves.ca
$host = $_SERVER['HTTP_HOST'] ?? '';
$isPosSubdomain = (
    strpos($host, 'pos.') === 0 && 
    (preg_match('/^pos\.arcticwolves\.ca$/i', $host) || preg_match('/^pos\..*\.arcticwolves\.ca$/i', $host))
);

// Store subdomain mode in session for persistent kiosk-only access
if ($isPosSubdomain) {
    $_SESSION['pos_subdomain'] = true;
}

// Generate CSRF token
CSRFProtection::generateToken();
generateCSRFToken();

// Check database connection
if (!$db_connected || !$pdo) {
    die("Database connection failed. Please check your configuration.");
}

$error = '';

// Check IP whitelist for POS kiosk access (admins exempt)
// For kiosk login page, users are not yet authenticated so treat as non-admin
$kiosk_user_role = $_SESSION['user_role'] ?? '';
if (!checkPOSIPAccess($pdo, $kiosk_user_role)) {
    logSecurityEvent('pos_ip_blocked', 'POS kiosk access denied from unauthorized IP', ['ip' => getClientIP()]);
    die('<div style="text-align: center; padding: 60px; font-family: sans-serif; color: #ef4444;"><h2>Access Denied</h2><p>POS kiosk access is not available from this location. Please contact an administrator.</p></div>');
}

// Handle PIN login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'pin_login') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $error = "Invalid request. Please refresh and try again.";
    } else {
        $pin = $_POST['pin'] ?? '';
        
        if (strlen($pin) !== 4 || !ctype_digit($pin)) {
            $error = "Please enter a valid 4-digit PIN.";
        } else {
            // Find staff member by PIN (allow admin, coach, health_coach, front_desk_staff)
            try {
                $stmt = $pdo->prepare("
                    SELECT u.id, u.first_name, u.last_name, u.role, sp.pin_hash
                    FROM users u
                    INNER JOIN staff_pins sp ON u.id = sp.user_id
                    WHERE u.role IN ('admin', 'coach', 'health_coach', 'front_desk_staff') 
                    AND u.is_active = 1 
                    AND sp.is_active = 1
                ");
                $stmt->execute();
                $staffMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $matchedStaff = null;
                foreach ($staffMembers as $staff) {
                    if (password_verify($pin, $staff['pin_hash'])) {
                        $matchedStaff = $staff;
                        break;
                    }
                }
                
                if ($matchedStaff) {
                    // Set session variables
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id'] = $matchedStaff['id'];
                    $_SESSION['user_role'] = $matchedStaff['role'];
                    $_SESSION['user_name'] = FieldEncryption::decrypt($matchedStaff['first_name']);
                    $_SESSION['kiosk_mode'] = true;
                    
                    // Check for active shift and auto clock-in if needed
                    $shiftStmt = $pdo->prepare("
                        SELECT * FROM staff_shifts 
                        WHERE staff_id = ? AND shift_date = CURDATE() AND status = 'active'
                    ");
                    $shiftStmt->execute([$matchedStaff['id']]);
                    $activeShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$activeShift) {
                        // Auto clock-in on first login of the day
                        $insertShift = $pdo->prepare("
                            INSERT INTO staff_shifts (staff_id, shift_date, clock_in, status) 
                            VALUES (?, CURDATE(), NOW(), 'active')
                        ");
                        $insertShift->execute([$matchedStaff['id']]);
                        $_SESSION['shift_id'] = $pdo->lastInsertId();
                    } else {
                        $_SESSION['shift_id'] = $activeShift['id'];
                    }
                    
                    // Redirect to kiosk dashboard if on POS subdomain, otherwise regular dashboard
                    if (isset($_SESSION['pos_subdomain']) && $_SESSION['pos_subdomain']) {
                        header("Location: dashboard_kiosk.php");
                    } else {
                        header("Location: dashboard.php?page=pos_terminal");
                    }
                    exit();
                } else {
                    $error = "Invalid PIN. Please try again.";
                }
            } catch (PDOException $e) {
                error_log("Kiosk login error: " . $e->getMessage());
                $error = "An error occurred. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS Kiosk | Arctic Wolves</title>
    <?php $__favType = getFaviconMimeType($site_favicon_url); ?>
    <link rel="icon" <?= $__favType ? 'type="' . $__favType . '"' : '' ?> href="<?= htmlspecialchars($site_favicon_url) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #6B46C1;
            --primary-hover: #7C3AED;
            --bg: #0A0A0F;
            --bg-secondary: #13131A;
            --border: #2D2D3F;
            --text: #A8A8B8;
        }
        
        * { box-sizing: border-box; margin: 0; padding: 0; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .kiosk-container {
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        
        .brand {
            margin-bottom: 40px;
        }
        
        .brand img {
            height: 80px;
            margin-bottom: 15px;
        }
        
        .brand h1 {
            font-size: 28px;
            font-weight: 900;
            letter-spacing: -1px;
        }
        
        .brand h1 span { color: var(--primary); }
        
        .brand p {
            color: var(--text);
            font-size: 14px;
            margin-top: 8px;
        }
        
        .kiosk-card {
            background: var(--bg-secondary);
            border-radius: 20px;
            padding: 40px;
            border: 1px solid var(--border);
        }
        
        .kiosk-card h2 {
            font-size: 20px;
            margin-bottom: 8px;
        }
        
        .kiosk-card .subtitle {
            color: var(--text);
            font-size: 14px;
            margin-bottom: 30px;
        }
        
        .pin-display {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 25px;
        }
        
        .pin-dot {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--border);
            transition: all 0.2s;
        }
        
        .pin-dot.filled {
            background: var(--primary);
            box-shadow: 0 0 15px rgba(107, 70, 193, 0.5);
        }
        
        .keypad {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }
        
        .key-btn {
            height: 70px;
            font-size: 28px;
            font-weight: 700;
            background: var(--bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            color: #fff;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .key-btn:hover {
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .key-btn:active {
            transform: scale(0.95);
        }
        
        .key-btn.clear {
            font-size: 14px;
            color: #ef4444;
        }
        
        .key-btn.submit {
            font-size: 14px;
            background: var(--primary);
            border-color: var(--primary);
        }
        
        .key-btn.submit:hover {
            background: var(--primary-hover);
        }
        
        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #ef4444;
            padding: 12px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        
        .admin-link {
            margin-top: 30px;
            font-size: 13px;
            color: var(--text);
        }
        
        .admin-link a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .admin-link a:hover {
            text-decoration: underline;
        }
        
        .time-display {
            font-size: 48px;
            font-weight: 300;
            margin-bottom: 5px;
            color: #fff;
        }
        
        .date-display {
            font-size: 14px;
            color: var(--text);
            margin-bottom: 40px;
        }
    </style>
</head>
<body>
    <div class="kiosk-container">
        <div class="brand">
            <img src="<?= htmlspecialchars($site_logo_url) ?>" alt="Arctic Wolves">
            <h1>ARCTIC <span>WOLVES</span></h1>
            <p>Staff Time Clock</p>
        </div>
        
        <div class="time-display" id="current-time">--:--</div>
        <div class="date-display" id="current-date">Loading...</div>
        
        <div class="kiosk-card">
            <h2>Enter Your PIN</h2>
            <p class="subtitle">Use your 4-digit staff PIN to clock in</p>
            
            <?php if ($error): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="pin-form">
                <input type="hidden" name="action" value="pin_login">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="pin" id="pin-input" value="">
                
                <div class="pin-display">
                    <div class="pin-dot" id="dot-0"></div>
                    <div class="pin-dot" id="dot-1"></div>
                    <div class="pin-dot" id="dot-2"></div>
                    <div class="pin-dot" id="dot-3"></div>
                </div>
                
                <div class="keypad">
                    <button type="button" class="key-btn" onclick="addDigit('1')">1</button>
                    <button type="button" class="key-btn" onclick="addDigit('2')">2</button>
                    <button type="button" class="key-btn" onclick="addDigit('3')">3</button>
                    <button type="button" class="key-btn" onclick="addDigit('4')">4</button>
                    <button type="button" class="key-btn" onclick="addDigit('5')">5</button>
                    <button type="button" class="key-btn" onclick="addDigit('6')">6</button>
                    <button type="button" class="key-btn" onclick="addDigit('7')">7</button>
                    <button type="button" class="key-btn" onclick="addDigit('8')">8</button>
                    <button type="button" class="key-btn" onclick="addDigit('9')">9</button>
                    <button type="button" class="key-btn clear" onclick="clearPin()"><i class="fas fa-backspace"></i></button>
                    <button type="button" class="key-btn" onclick="addDigit('0')">0</button>
                    <button type="button" class="key-btn submit" onclick="submitPin()"><i class="fas fa-check"></i></button>
                </div>
            </form>
        </div>
        
        <?php if (!$isPosSubdomain): ?>
        <div class="admin-link">
            Admin access? <a href="login.php">Login with email</a>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        let pin = '';
        
        function addDigit(digit) {
            if (pin.length < 4) {
                pin += digit;
                updateDisplay();
                
                // Auto-submit when 4 digits entered
                if (pin.length === 4) {
                    setTimeout(submitPin, 200);
                }
            }
        }
        
        function clearPin() {
            pin = pin.slice(0, -1);
            updateDisplay();
        }
        
        function updateDisplay() {
            for (let i = 0; i < 4; i++) {
                const dot = document.getElementById('dot-' + i);
                if (i < pin.length) {
                    dot.classList.add('filled');
                } else {
                    dot.classList.remove('filled');
                }
            }
            document.getElementById('pin-input').value = pin;
        }
        
        function submitPin() {
            if (pin.length === 4) {
                document.getElementById('pin-form').submit();
            }
        }
        
        // Update time display
        function updateTime() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });
            const dateStr = now.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' });
            
            document.getElementById('current-time').textContent = timeStr;
            document.getElementById('current-date').textContent = dateStr;
        }
        
        updateTime();
        setInterval(updateTime, 1000);
        
        // Keyboard support
        document.addEventListener('keydown', function(e) {
            if (e.key >= '0' && e.key <= '9') {
                addDigit(e.key);
            } else if (e.key === 'Backspace') {
                clearPin();
            } else if (e.key === 'Enter' && pin.length === 4) {
                submitPin();
            }
        });
    </script>
</body>
</html>
