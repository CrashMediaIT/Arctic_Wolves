<?php
// =========================================================
// CRASH HOCKEY - SYSTEM SETUP WIZARD
// =========================================================
// This wizard helps configure the system for first-time setup
// It should be removed or restricted in production

session_start();

// Check if setup is already completed
$setup_complete_file = __DIR__ . '/.setup_complete';
if (file_exists($setup_complete_file) && !isset($_GET['force'])) {
    header("Location: login.php");
    exit();
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

// Initialize session data if not exists
if (!isset($_SESSION['setup'])) {
    $_SESSION['setup'] = [
        'database' => false,
        'admin' => false,
        'smtp' => false
    ];
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step == 1) {
        // Database Configuration
        $host = trim($_POST['db_host']);
        $name = trim($_POST['db_name']);
        $user = trim($_POST['db_user']);
        $pass = $_POST['db_pass'];
        
        try {
            $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8mb4", $user, $pass);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Save to .env file
            $env_content = "DB_HOST=$host\nDB_NAME=$name\nDB_USER=$user\nDB_PASS=$pass\n";
            file_put_contents(__DIR__ . '/crashhockey.env', $env_content);
            
            $_SESSION['setup']['database'] = true;
            $_SESSION['db_credentials'] = ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass];
            
            // Import schema
            $schema = file_get_contents(__DIR__ . '/database_schema.sql');
            $pdo->exec($schema);
            
            header("Location: setup.php?step=2");
            exit();
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($step == 2) {
        // Admin User Creation
        // Recreate PDO connection from session credentials
        if (!isset($_SESSION['db_credentials'])) {
            $error = "Database credentials not found. Please restart setup.";
        } else {
            $db_creds = $_SESSION['db_credentials'];
            
            try {
                $pdo = new PDO("mysql:host={$db_creds['host']};dbname={$db_creds['name']};charset=utf8mb4", 
                              $db_creds['user'], $db_creds['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $email = trim($_POST['admin_email']);
                $password = $_POST['admin_password'];
                $confirm = $_POST['admin_password_confirm'];
                $first_name = trim($_POST['first_name']);
                $last_name = trim($_POST['last_name']);
                
                if ($password !== $confirm) {
                    $error = "Passwords do not match";
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, is_verified) VALUES (?, ?, ?, ?, 'admin', 1)");
                    $stmt->execute([$email, $hashed, $first_name, $last_name]);
                    
                    $_SESSION['setup']['admin'] = true;
                    header("Location: setup.php?step=3");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Failed to create admin user: " . $e->getMessage();
            }
        }
    } elseif ($step == 3) {
        // SMTP Configuration
        // Recreate PDO connection from session credentials
        if (!isset($_SESSION['db_credentials'])) {
            $error = "Database credentials not found. Please restart setup.";
        } else {
            $db_creds = $_SESSION['db_credentials'];
            
            try {
                $pdo = new PDO("mysql:host={$db_creds['host']};dbname={$db_creds['name']};charset=utf8mb4", 
                              $db_creds['user'], $db_creds['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                $smtp_host = trim($_POST['smtp_host']);
                $smtp_port = trim($_POST['smtp_port']);
                $smtp_user = trim($_POST['smtp_user']);
                $smtp_pass = $_POST['smtp_pass'];
                $smtp_from = trim($_POST['smtp_from']);
            $settings = [
                ['smtp_host', $smtp_host],
                ['smtp_port', $smtp_port],
                ['smtp_user', $smtp_user],
                ['smtp_pass', $smtp_pass],
                ['smtp_from', $smtp_from]
            ];
            
            foreach ($settings as $setting) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$setting[0], $setting[1], $setting[1]]);
            }
            
            // Test SMTP connection (optional)
            // ... smtp test code ...
                
                $_SESSION['setup']['smtp'] = true;
                header("Location: setup.php?step=4");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to save SMTP settings: " . $e->getMessage();
            }
        }
    } elseif ($step == 4) {
        // Finalize Setup
        file_put_contents($setup_complete_file, date('Y-m-d H:i:s'));
        
        // Clear setup session
        unset($_SESSION['setup']);
        
        // Redirect to login
        $_SESSION['setup_success'] = true;
        header("Location: login.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Wizard | Crash Hockey</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #6B46C1; 
            --primary-hover: #7C3AED;
            --bg: #0A0A0F; 
            --card-bg: #16161F; 
            --border: #2D2D3F; 
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { background: var(--bg); color: #fff; font-family: 'Inter', sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .setup-container { max-width: 600px; width: 100%; background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 40px; }
        .logo { text-align: center; margin-bottom: 30px; }
        .logo h1 { font-size: 28px; font-weight: 900; letter-spacing: -1px; }
        .logo h1 span { color: var(--primary); }
        .progress-bar { display: flex; gap: 10px; margin-bottom: 40px; }
        .progress-step { flex: 1; height: 4px; background: var(--border); border-radius: 2px; }
        .progress-step.active { background: var(--primary); }
        h2 { font-size: 22px; margin-bottom: 10px; }
        p { color: #94a3b8; margin-bottom: 30px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 8px; color: #cbd5e1; }
        .form-group input, .form-group select { width: 100%; height: 45px; background: var(--bg); border: 1px solid var(--border); border-radius: 6px; padding: 0 15px; color: #fff; font-size: 14px; font-family: 'Inter', sans-serif; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--primary); }
        .btn-primary { width: 100%; height: 45px; background: var(--primary); color: #fff; border: none; border-radius: 6px; font-size: 14px; font-weight: 700; cursor: pointer; font-family: 'Inter', sans-serif; }
        .btn-primary:hover { background: var(--primary-hover); }
        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 13px; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #ef4444; }
        .alert-success { background: rgba(0, 255, 136, 0.1); border: 1px solid #00ff88; color: #00ff88; }
        .step-info { background: rgba(107, 70, 193, 0.05); border-left: 3px solid var(--primary); padding: 15px; margin-bottom: 20px; font-size: 13px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo">
            <h1>CRASH <span>HOCKEY</span></h1>
            <p style="margin-bottom: 0; margin-top: 10px;">System Setup Wizard</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-step <?= $step >= 1 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 2 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 3 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 4 ? 'active' : '' ?>"></div>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-check-circle"></i> <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($step == 1): ?>
            <h2>Step 1: Database Configuration</h2>
            <p>Enter your database connection details</p>
            <div class="step-info">
                <i class="fa-solid fa-info-circle"></i> Make sure your MySQL database is created and accessible.
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" value="crashhockey" required>
                </div>
                <div class="form-group">
                    <label>Database User</label>
                    <input type="text" name="db_user" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass">
                </div>
                <button type="submit" class="btn-primary">Continue to Step 2</button>
            </form>
        <?php elseif ($step == 2): ?>
            <h2>Step 2: Create Admin User</h2>
            <p>Set up the initial administrator account</p>
            <form method="POST">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="admin_password" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="admin_password_confirm" required minlength="8">
                </div>
                <button type="submit" class="btn-primary">Continue to Step 3</button>
            </form>
        <?php elseif ($step == 3): ?>
            <h2>Step 3: SMTP Configuration</h2>
            <p>Configure email settings for notifications</p>
            <div class="step-info">
                <i class="fa-solid fa-info-circle"></i> SMTP is required for sending verification emails and notifications.
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>SMTP Host</label>
                    <input type="text" name="smtp_host" placeholder="smtp.gmail.com" required>
                </div>
                <div class="form-group">
                    <label>SMTP Port</label>
                    <input type="number" name="smtp_port" value="587" required>
                </div>
                <div class="form-group">
                    <label>SMTP Username</label>
                    <input type="text" name="smtp_user" required>
                </div>
                <div class="form-group">
                    <label>SMTP Password</label>
                    <input type="password" name="smtp_pass" required>
                </div>
                <div class="form-group">
                    <label>From Email Address</label>
                    <input type="email" name="smtp_from" required>
                </div>
                <button type="submit" class="btn-primary">Continue to Step 4</button>
            </form>
        <?php elseif ($step == 4): ?>
            <h2>Step 4: Complete Setup</h2>
            <p>Setup is complete! Click below to finalize and access your dashboard.</p>
            <div class="step-info">
                <i class="fa-solid fa-check-circle"></i> All configuration has been saved successfully.
            </div>
            <form method="POST">
                <button type="submit" class="btn-primary">Complete Setup & Go to Login</button>
            </form>
        <?php endif; ?>
        
        <?php if ($step > 1): ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="setup.php?step=<?= $step - 1 ?>" style="color: var(--primary); text-decoration: none; font-size: 13px;">
                    <i class="fa-solid fa-arrow-left"></i> Back to Previous Step
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
