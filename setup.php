<?php
// =========================================================
// ARCTIC WOLVES - SYSTEM SETUP WIZARD
// =========================================================
// This wizard helps configure the system for first-time setup
// It should be removed or restricted in production

session_start();

// =========================================================
// AUTOMATIC PERMISSION SETUP FOR DOCKER ENVIRONMENTS
// =========================================================
// This function sets up directories and permissions automatically
// when running in Docker containers (linuxserver/nginx)
function setupPermissions() {
    $base_dir = __DIR__;
    $required_dirs = [
        'uploads',
        'sessions',
        'cache',
        'logs',
        'backups',
        'receipts',
        'videos',
        'tmp'
    ];
    
    $permission_issues = [];
    
    // Create required directories if they don't exist
    foreach ($required_dirs as $dir) {
        $full_path = $base_dir . '/' . $dir;
        if (!file_exists($full_path)) {
            if (!@mkdir($full_path, 0775, true)) {
                $last_error = error_get_last();
                $error_msg = $last_error ? $last_error['message'] : 'unknown error';
                $permission_issues[] = "Failed to create directory: $dir - $error_msg";
                continue;
            }
        }
        
        // Set permissions to 775 for writable directories
        if (file_exists($full_path)) {
            if (!@chmod($full_path, 0775)) {
                $permission_issues[] = "Failed to set permissions on directory: $dir";
            }
        }
    }
    
    // Ensure root directory is writable (775)
    if (!@chmod($base_dir, 0775)) {
        $permission_issues[] = "Failed to set permissions on root directory";
    }
    
    return $permission_issues;
}

// Run permission setup automatically on first load
// Use a persistent flag file to prevent repeated attempts
$permissions_flag_file = __DIR__ . '/.permissions_setup_done';
if (!file_exists($permissions_flag_file)) {
    $permission_issues = setupPermissions();
    
    // Create flag file to mark permissions as set up
    @file_put_contents($permissions_flag_file, date('Y-m-d H:i:s'));
    
    if (!empty($permission_issues)) {
        $_SESSION['permission_warnings'] = $permission_issues;
    }
}

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
            $env_file = __DIR__ . '/arctic_wolves.env';
            
            if (file_put_contents($env_file, $env_content) === false) {
                throw new Exception("Failed to write configuration file. Please check directory permissions.");
            }
            
            // Verify the file was written correctly
            if (!file_exists($env_file) || !is_readable($env_file)) {
                throw new Exception("Configuration file was created but is not readable. Please check file permissions.");
            }
            
            $_SESSION['setup']['database'] = true;
            $_SESSION['db_credentials'] = ['host' => $host, 'name' => $name, 'user' => $user, 'pass' => $pass];
            
            // Import schema
            $schema = file_get_contents(__DIR__ . '/database_schema.sql');
            $pdo->exec($schema);
            
            // Run migrations for existing installations
            // Use try-catch approach for portability
            try {
                // Try to add display_order column to eval_categories
                $pdo->exec("ALTER TABLE eval_categories ADD COLUMN display_order INT DEFAULT 0 AFTER description");
            } catch (PDOException $e) {
                // Column might already exist, which is fine
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    // Some other error occurred, but don't fail setup
                }
            }
            
            try {
                // Try to add display_order column to eval_skills
                $pdo->exec("ALTER TABLE eval_skills ADD COLUMN display_order INT DEFAULT 0 AFTER description");
            } catch (PDOException $e) {
                // Column might already exist, which is fine
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    // Some other error occurred, but don't fail setup
                }
            }
            
            header("Location: setup.php?step=2");
            exit();
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
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
        // Demo Data Setup
        if (!isset($_SESSION['db_credentials'])) {
            $error = "Database credentials not found. Please restart setup.";
        } else {
            $add_demo = isset($_POST['add_demo_data']) ? $_POST['add_demo_data'] : 'no';
            $_SESSION['setup']['demo'] = ($add_demo === 'yes');
            
            if ($add_demo === 'yes') {
                try {
                    $db_creds = $_SESSION['db_credentials'];
                    $pdo = new PDO("mysql:host={$db_creds['host']};dbname={$db_creds['name']};charset=utf8mb4", 
                                  $db_creds['user'], $db_creds['pass']);
                    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    
                    // Load and run demo data seeder
                    require_once __DIR__ . '/demo_data_seeder.php';
                    $seeder = new DemoDataSeeder($pdo);
                    
                    // Add demo columns to all tables
                    ob_start();
                    $seeder->addDemoColumns();
                    ob_end_clean();
                    
                    // Seed demo data
                    ob_start();
                    $seeder->seedAll();
                    $demo_output = ob_get_clean();
                    
                    $_SESSION['demo_data_added'] = true;
                    $success = "Demo data has been successfully added to the database!";
                } catch (Exception $e) {
                    $error = "Failed to add demo data: " . $e->getMessage();
                }
            }
            
            header("Location: setup.php?step=5");
            exit();
        }
    } elseif ($step == 5) {
        // Finalize Setup - Verify database connection one more time
        try {
            // Clear any session-based DB credentials to force reading from .env
            unset($_SESSION['db_credentials']);
            
            // Try to establish connection using saved .env file
            $env_file = __DIR__ . '/arctic_wolves.env';
            if (!file_exists($env_file)) {
                throw new Exception("Configuration file not found. Please restart setup.");
            }
            
            // Load environment variables from file
            // Note: We don't use loadEnv() from db_config.php here because setup.php
            // needs to be self-contained and work independently during initial setup
            $env_lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $env_vars = [];
            foreach ($env_lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $env_vars[trim($key)] = trim($value);
                }
            }
            
            // Verify all required variables exist
            if (empty($env_vars['DB_HOST']) || empty($env_vars['DB_NAME']) || empty($env_vars['DB_USER'])) {
                throw new Exception("Configuration file is incomplete. Please restart setup.");
            }
            
            // Test database connection
            $test_pdo = new PDO(
                "mysql:host={$env_vars['DB_HOST']};dbname={$env_vars['DB_NAME']};charset=utf8mb4",
                $env_vars['DB_USER'],
                isset($env_vars['DB_PASS']) ? $env_vars['DB_PASS'] : ''
            );
            $test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $test_pdo->query("SELECT 1");
            
            // All checks passed - finalize setup
            file_put_contents($setup_complete_file, date('Y-m-d H:i:s'));
            
            // Clear setup session
            unset($_SESSION['setup']);
            
            // Redirect to login
            $_SESSION['setup_success'] = true;
            header("Location: login.php");
            exit();
            
        } catch (PDOException $e) {
            $error = "Database connection test failed: " . $e->getMessage() . ". Please restart setup.";
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Wizard | Arctic Wolves</title>
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
        .alert-warning { background: rgba(251, 191, 36, 0.1); border: 1px solid #fbbf24; color: #fbbf24; }
        .step-info { background: rgba(107, 70, 193, 0.05); border-left: 3px solid var(--primary); padding: 15px; margin-bottom: 20px; font-size: 13px; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo">
            <h1>ARCTIC <span>WOLVES</span></h1>
            <p style="margin-bottom: 0; margin-top: 10px;">System Setup Wizard</p>
        </div>
        
        <div class="progress-bar">
            <div class="progress-step <?= $step >= 1 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 2 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 3 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 4 ? 'active' : '' ?>"></div>
            <div class="progress-step <?= $step >= 5 ? 'active' : '' ?>"></div>
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
        
        <?php if (isset($_SESSION['permission_warnings']) && !empty($_SESSION['permission_warnings'])): ?>
            <div class="alert alert-warning">
                <i class="fa-solid fa-exclamation-triangle"></i> <strong>Permission Warnings:</strong><br/>
                <?php foreach ($_SESSION['permission_warnings'] as $warning): ?>
                    • <?= htmlspecialchars($warning) ?><br/>
                <?php endforeach; ?>
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
                    <input type="text" name="db_name" value="arctic_wolves" required>
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
            <h2>Step 4: Demo Data</h2>
            <p>Add demo data to test all features of the application</p>
            <div class="step-info">
                <i class="fa-solid fa-info-circle"></i> Demo data includes sample users, sessions, drills, and more. All demo entries can be removed later from the Admin Portal.
            </div>
            <form method="POST">
                <div class="form-group">
                    <label>Add Demo Data?</label>
                    <select name="add_demo_data" style="width: 100%; height: 45px;">
                        <option value="yes">Yes - Add demo data for testing</option>
                        <option value="no">No - Start with empty database</option>
                    </select>
                </div>
                <div style="background: rgba(107, 70, 193, 0.05); border: 1px solid var(--border); border-radius: 6px; padding: 15px; margin-bottom: 20px; font-size: 12px; color: #94a3b8;">
                    <strong style="color: #fff;">What's included in demo data:</strong><br/>
                    • Sample coaches, athletes, and parents<br/>
                    • Training sessions and practice plans<br/>
                    • Drills and exercises<br/>
                    • Goals and evaluations<br/>
                    • Equipment and locations<br/>
                    • All demo entries marked with "Demo" prefix
                </div>
                <button type="submit" class="btn-primary">Continue to Step 5</button>
            </form>
        <?php elseif ($step == 5): ?>
            <h2>Step 5: Complete Setup</h2>
            <p>Setup is complete! Click below to finalize and access your dashboard.</p>
            <div class="step-info">
                <i class="fa-solid fa-check-circle"></i> All configuration has been saved successfully.
            </div>
            <?php if (isset($_SESSION['demo_data_added']) && $_SESSION['demo_data_added']): ?>
                <div class="alert alert-success" style="margin-bottom: 20px;">
                    <i class="fa-solid fa-check-circle"></i> Demo data has been added successfully!
                </div>
            <?php endif; ?>
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
