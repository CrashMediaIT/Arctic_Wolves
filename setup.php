<?php
// =========================================================
// ARCTIC WOLVES - SYSTEM SETUP WIZARD
// =========================================================
// This wizard helps configure the system for first-time setup
// It should be removed or restricted in production

session_start();
require_once __DIR__ . '/lib/encryption.php';

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
            // NOTE: 0775 permissions allow web server (group) write access, required for uploads/sessions/cache
            // This is appropriate for these specific writable directories in Docker environments
            if (!@mkdir($full_path, 0775, true)) {
                $last_error = error_get_last();
                $error_msg = $last_error ? $last_error['message'] : 'unknown error';
                $permission_issues[] = "Failed to create directory: $dir - $error_msg";
                continue;
            }
        }
        
        // Try to set permissions to 775 for writable directories
        // In many environments (Docker, cloud hosting) chmod may fail even though
        // the directory is already writable — only report an issue if it's not writable
        if (file_exists($full_path)) {
            @chmod($full_path, 0775);
            if (!is_writable($full_path)) {
                $permission_issues[] = "Directory is not writable: $dir";
            }
        }
    }
    
    // Ensure root directory is writable (775)
    // NOTE: 775 is required for setup.php to write arctic_wolves.env file during initial setup
    // This is specific to Docker environments where PHP-FPM runs as 'abc' user (UID 911)
    @chmod($base_dir, 0775);
    if (!is_writable($base_dir)) {
        $permission_issues[] = "Root directory is not writable";
    }
    
    return $permission_issues;
}

// Run permission setup automatically on first load
// Use a persistent flag file to prevent repeated attempts
$permissions_flag_file = __DIR__ . '/.permissions_setup_done';
if (!file_exists($permissions_flag_file)) {
    $permission_issues = setupPermissions();
    
    // Create flag file to mark permissions as set up
    // Suppress errors as this is a convenience flag - if it fails, setup will just run again
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
        'encryption' => false,
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
            
            // Detect if this is an existing database with tables
            $stmt = $pdo->query("SHOW TABLES");
            $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $has_users_table = in_array('users', $existing_tables);
            
            if ($has_users_table) {
                // Existing database detected — check if it has user data
                $user_count_stmt = $pdo->query("SELECT COUNT(*) FROM users");
                $user_count = (int)$user_count_stmt->fetchColumn();
                $_SESSION['setup']['existing_database'] = true;
                $_SESSION['setup']['existing_user_count'] = $user_count;
                $_SESSION['setup']['existing_table_count'] = count($existing_tables);
            } else {
                // Fresh database — import the full schema
                $_SESSION['setup']['existing_database'] = false;
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
            
            try {
                // Try to add roster_player_id column to vr_game_plan_lines for non-user roster players
                $pdo->exec("ALTER TABLE vr_game_plan_lines ADD COLUMN roster_player_id INT DEFAULT NULL COMMENT 'References roster_players.id for non-user players' AFTER athlete_id");
            } catch (PDOException $e) {
                // Column might already exist (error code 42S21 / 1060), which is fine
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Note: Could not add roster_player_id column: " . $e->getMessage());
                }
            }
            
            try {
                // Add foreign key for roster_player_id if not already present
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE CONSTRAINT_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'vr_game_plan_lines' 
                    AND CONSTRAINT_NAME = 'fk_gpl_roster_player'
                ");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result !== false && (int)$result['cnt'] === 0) {
                    $pdo->exec("ALTER TABLE `vr_game_plan_lines` ADD CONSTRAINT `fk_gpl_roster_player` FOREIGN KEY (`roster_player_id`) REFERENCES `roster_players`(`id`) ON DELETE SET NULL");
                }
            } catch (PDOException $e) {
                error_log("Note: Could not add fk_gpl_roster_player constraint: " . $e->getMessage());
            }
            
            try {
                // Add game_id column to vr_game_plan_lines for game-specific lines
                $pdo->exec("ALTER TABLE vr_game_plan_lines ADD COLUMN game_id INT DEFAULT NULL COMMENT 'NULL = default/standard lineup, set = game-specific lines' AFTER team_id");
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Note: Could not add game_id column to vr_game_plan_lines: " . $e->getMessage());
                }
            }
            
            try {
                // Add foreign key for game_id in vr_game_plan_lines if not already present
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS 
                    WHERE CONSTRAINT_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'vr_game_plan_lines' 
                    AND CONSTRAINT_NAME = 'fk_gpl_game'
                ");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result !== false && (int)$result['cnt'] === 0) {
                    $pdo->exec("ALTER TABLE `vr_game_plan_lines` ADD CONSTRAINT `fk_gpl_game` FOREIGN KEY (`game_id`) REFERENCES `game_schedules`(`id`) ON DELETE CASCADE");
                }
            } catch (PDOException $e) {
                error_log("Note: Could not add fk_gpl_game constraint: " . $e->getMessage());
            }
            
            // Add is_managed column to teams table for managed vs unmanaged (opponent) teams
            try {
                $pdo->exec("ALTER TABLE teams ADD COLUMN is_managed TINYINT(1) DEFAULT 1 COMMENT '1 = managed team (our teams), 0 = unmanaged (opponent teams)' AFTER is_demo");
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Note: Could not add is_managed column to teams: " . $e->getMessage());
                }
            }
            
            // Add ical_url column to teams table for calendar re-sync
            try {
                $pdo->exec("ALTER TABLE teams ADD COLUMN ical_url VARCHAR(1000) DEFAULT NULL COMMENT 'Stored iCal URL for calendar re-sync' AFTER is_managed");
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Note: Could not add ical_url column to teams: " . $e->getMessage());
                }
            }
            
            // Add ical_uid column to game_schedules for tracking imported events
            try {
                $pdo->exec("ALTER TABLE game_schedules ADD COLUMN ical_uid VARCHAR(500) DEFAULT NULL COMMENT 'UID from iCal event for sync/update tracking' AFTER season_id");
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Note: Could not add ical_uid column to game_schedules: " . $e->getMessage());
                }
            }
            
            // Add unique index on ical_uid + team_id for upsert support
            try {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as cnt FROM information_schema.STATISTICS 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME = 'game_schedules' 
                    AND INDEX_NAME = 'idx_ical_uid_team'
                ");
                $stmt->execute();
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result !== false && (int)$result['cnt'] === 0) {
                    $pdo->exec("ALTER TABLE game_schedules ADD UNIQUE INDEX idx_ical_uid_team (ical_uid, team_id)");
                }
            } catch (PDOException $e) {
                error_log("Note: Could not add idx_ical_uid_team index: " . $e->getMessage());
            }
            
            // Add sip_wss_port column to users table for configurable WSS port
            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN sip_wss_port INT DEFAULT 7443 COMMENT 'WebSocket Secure port for SIP/WSS connection to FusionPBX' AFTER sip_password");
            } catch (PDOException $e) {
                if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                    error_log("Note: Could not add sip_wss_port column to users: " . $e->getMessage());
                }
            }
            
            // Add fk_expense_payee foreign key constraint if it doesn't exist
            // This is done separately to ensure idempotent schema setup
            // The expenses table and payee_id column are created by database_schema.sql
            // The payees table is also created before this point in the schema
            try {
                // First verify that both required tables exist
                $tablesCheck = $pdo->query("
                    SELECT TABLE_NAME FROM information_schema.TABLES 
                    WHERE TABLE_SCHEMA = DATABASE() 
                    AND TABLE_NAME IN ('expenses', 'payees')
                ");
                $existingTables = $tablesCheck->fetchAll(PDO::FETCH_COLUMN);
                
                // Only proceed if both tables exist
                if (in_array('expenses', $existingTables) && in_array('payees', $existingTables)) {
                    // Check if the constraint already exists
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) as cnt FROM information_schema.TABLE_CONSTRAINTS 
                        WHERE CONSTRAINT_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'expenses' 
                        AND CONSTRAINT_NAME = 'fk_expense_payee'
                    ");
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Use strict comparison and verify result is valid
                    if ($result !== false && (int)$result['cnt'] === 0) {
                        // Constraint doesn't exist, add it
                        $pdo->exec("ALTER TABLE `expenses` ADD CONSTRAINT `fk_expense_payee` FOREIGN KEY (`payee_id`) REFERENCES `payees`(`id`) ON DELETE SET NULL");
                    }
                }
            } catch (PDOException $e) {
                // Log the specific error for debugging, but don't fail setup
                // This constraint is optional for initial setup to succeed
                error_log("Note: Could not add fk_expense_payee constraint: " . $e->getMessage());
            }
            
            } // End of fresh database schema import block
            
            header("Location: setup.php?step=2");
            exit();
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    } elseif ($step == 2) {
        // Encryption Key Configuration
        $encryption_key = trim($_POST['encryption_key'] ?? '');
        $is_existing_db = !empty($_SESSION['setup']['existing_database']);
        
        // Validate the key is exactly 64 hex characters
        if (!preg_match('/^[a-fA-F0-9]{64}$/', $encryption_key)) {
            $error = "Invalid encryption key. Must be exactly 64 hexadecimal characters (0-9, a-f).";
        } else {
            // Save the encryption key to the .env file
            $env_file = __DIR__ . '/arctic_wolves.env';
            $env_content = file_exists($env_file) ? file_get_contents($env_file) : '';
            
            // Update or add ENCRYPTION_KEY
            if (preg_match('/^ENCRYPTION_KEY=.*$/m', $env_content)) {
                $env_content = preg_replace('/^ENCRYPTION_KEY=.*$/m', 'ENCRYPTION_KEY=' . $encryption_key, $env_content);
            } else {
                $env_content = rtrim($env_content) . "\nENCRYPTION_KEY=" . $encryption_key . "\n";
            }
            
            if (file_put_contents($env_file, $env_content) === false) {
                $error = "Failed to write encryption key to configuration file. Please check directory permissions.";
            } else {
                // Load the key into the current environment so it takes effect immediately
                $_ENV['ENCRYPTION_KEY'] = $encryption_key;
                
                // For existing databases, validate the encryption key against stored data
                if ($is_existing_db && isset($_SESSION['db_credentials'])) {
                    $db_creds = $_SESSION['db_credentials'];
                    try {
                        $pdo = new PDO("mysql:host={$db_creds['host']};dbname={$db_creds['name']};charset=utf8mb4",
                                      $db_creds['user'], $db_creds['pass']);
                        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        
                        // Try to decrypt a sample user's first_name to validate the key
                        $stmt = $pdo->query("SELECT first_name FROM users WHERE first_name IS NOT NULL AND first_name != '' LIMIT 1");
                        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($sample) {
                            $decrypted = FieldEncryption::decrypt($sample['first_name']);
                            // If decryption returns gibberish (non-UTF8), the key is likely wrong
                            if ($decrypted !== $sample['first_name'] && !mb_check_encoding($decrypted, 'UTF-8')) {
                                $error = "Encryption key validation failed. The provided key could not decrypt existing data. Please enter the correct encryption key that was used when the database was originally set up.";
                            }
                        }
                    } catch (PDOException $e) {
                        error_log("Encryption validation DB error: " . $e->getMessage());
                    }
                }
                
                if (empty($error)) {
                    $_SESSION['setup']['encryption'] = true;
                    
                    if ($is_existing_db) {
                        // Skip admin creation for existing databases — users already exist
                        $_SESSION['setup']['admin'] = true;
                        header("Location: setup.php?step=3");
                    } else {
                        header("Location: setup.php?step=3");
                    }
                    exit();
                }
            }
        }
    } elseif ($step == 3 && !empty($_SESSION['setup']['existing_database'])) {
        // Schema Migration for Existing Database
        if (!isset($_SESSION['db_credentials'])) {
            $error = "Database credentials not found. Please restart setup.";
        } else {
            $db_creds = $_SESSION['db_credentials'];
            
            try {
                $pdo = new PDO("mysql:host={$db_creds['host']};dbname={$db_creds['name']};charset=utf8mb4",
                              $db_creds['user'], $db_creds['pass']);
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                
                require_once __DIR__ . '/lib/database_migrator.php';
                $migrator = new DatabaseMigrator($pdo, __DIR__);
                
                // Parse the expected schema from database_schema.sql
                $expected_schema = $migrator->parseSchemaFile(__DIR__ . '/database_schema.sql');
                
                // Get the current live database schema
                $current_schema = $migrator->getCurrentSchema();
                
                // Compare and generate migration steps
                $migrations = $migrator->compareSchemas($current_schema, $expected_schema);
                
                $migration_results = [];
                $migration_errors = [];
                
                foreach ($migrations as $migration) {
                    try {
                        if ($migration['type'] === 'create_table') {
                            // For missing tables, extract the CREATE TABLE from the schema file
                            $schema_sql = file_get_contents(__DIR__ . '/database_schema.sql');
                            $table_name = $migration['table'];
                            
                            // Extract the full CREATE TABLE statement from the schema file
                            if (preg_match('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?' . preg_quote($table_name) . '`?\s*\(.*?\)\s*ENGINE[^;]*;/is', $schema_sql, $match)) {
                                $pdo->exec($match[0]);
                                $migration_results[] = "Created missing table: $table_name";
                            }
                        } elseif ($migration['type'] === 'add_column') {
                            $result = $migrator->executeMigration($migration);
                            if (!empty($result['skipped'])) {
                                $migration_results[] = $result['message'] . ' (skipped)';
                            } else {
                                $migration_results[] = $result['message'];
                            }
                        }
                    } catch (Exception $e) {
                        $migration_errors[] = "Migration error: " . $e->getMessage();
                        error_log("Setup migration error: " . $e->getMessage());
                    }
                }
                
                // Run the inline migrations for columns that may not be detected by schema comparison
                // (same as fresh install migrations)
                $inline_migrations = [
                    ["ALTER TABLE eval_categories ADD COLUMN display_order INT DEFAULT 0 AFTER description", "eval_categories.display_order"],
                    ["ALTER TABLE eval_skills ADD COLUMN display_order INT DEFAULT 0 AFTER description", "eval_skills.display_order"],
                    ["ALTER TABLE vr_game_plan_lines ADD COLUMN roster_player_id INT DEFAULT NULL COMMENT 'References roster_players.id for non-user players' AFTER athlete_id", "vr_game_plan_lines.roster_player_id"],
                    ["ALTER TABLE vr_game_plan_lines ADD COLUMN game_id INT DEFAULT NULL COMMENT 'NULL = default/standard lineup, set = game-specific lines' AFTER team_id", "vr_game_plan_lines.game_id"],
                    ["ALTER TABLE teams ADD COLUMN is_managed TINYINT(1) DEFAULT 1 COMMENT '1 = managed team (our teams), 0 = unmanaged (opponent teams)' AFTER is_demo", "teams.is_managed"],
                    ["ALTER TABLE teams ADD COLUMN ical_url VARCHAR(1000) DEFAULT NULL COMMENT 'Stored iCal URL for calendar re-sync' AFTER is_managed", "teams.ical_url"],
                    ["ALTER TABLE game_schedules ADD COLUMN ical_uid VARCHAR(500) DEFAULT NULL COMMENT 'UID from iCal event for sync/update tracking' AFTER season_id", "game_schedules.ical_uid"],
                    ["ALTER TABLE users ADD COLUMN sip_wss_port INT DEFAULT 7443 COMMENT 'WebSocket Secure port for SIP/WSS connection to FusionPBX' AFTER sip_password", "users.sip_wss_port"],
                ];
                
                foreach ($inline_migrations as $mig) {
                    try {
                        $pdo->exec($mig[0]);
                        $migration_results[] = "Added column: " . $mig[1];
                    } catch (PDOException $e) {
                        if ($e->getCode() !== '42S21' && strpos($e->getMessage(), 'Duplicate column') === false) {
                            $migration_errors[] = "Could not add " . $mig[1] . ": " . $e->getMessage();
                        }
                    }
                }
                
                // Verify schema after migration
                $post_schema = $migrator->getCurrentSchema();
                $remaining = $migrator->compareSchemas($post_schema, $expected_schema);
                $remaining_tables = array_filter($remaining, function($m) { return $m['type'] === 'create_table'; });
                
                $_SESSION['setup']['schema_migration'] = [
                    'results' => $migration_results,
                    'errors' => $migration_errors,
                    'remaining_issues' => count($remaining_tables)
                ];
                
                $_SESSION['setup']['schema_migrated'] = true;
                
                // Skip admin creation for existing databases, go to SMTP
                header("Location: setup.php?step=4");
                exit();
            } catch (PDOException $e) {
                $error = "Schema migration failed: " . $e->getMessage();
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }
    } elseif ($step == 3) {
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
                    $enc_fn = FieldEncryption::encrypt($first_name);
                    $enc_ln = FieldEncryption::encrypt($last_name);
                    $stmt = $pdo->prepare("INSERT INTO users (email, password, first_name, last_name, role, is_verified) VALUES (?, ?, ?, ?, 'admin', 1)");
                    $stmt->execute([$email, $hashed, $enc_fn, $enc_ln]);
                    
                    $_SESSION['setup']['admin'] = true;
                    header("Location: setup.php?step=4");
                    exit();
                }
            } catch (PDOException $e) {
                $error = "Failed to create admin user: " . $e->getMessage();
            }
        }
    } elseif ($step == 4) {
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
                ['smtp_from_email', $smtp_from],
                ['smtp_from_name', 'Arctic Wolves'],
                ['smtp_encryption', 'tls']
            ];
            
            foreach ($settings as $setting) {
                $stmt = $pdo->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
                $stmt->execute([$setting[0], $setting[1], $setting[1]]);
            }
            
            // Test SMTP connection (optional)
            // ... smtp test code ...
                
                $_SESSION['setup']['smtp'] = true;
                header("Location: setup.php?step=5");
                exit();
            } catch (PDOException $e) {
                $error = "Failed to save SMTP settings: " . $e->getMessage();
            }
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
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="db_pass" id="db_pass" style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('db_pass', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Continue to Step 2</button>
            </form>
        <?php elseif ($step == 2): ?>
            <?php $is_existing_db = !empty($_SESSION['setup']['existing_database']); ?>
            <?php if ($is_existing_db): ?>
                <h2>Step 2: Enter Encryption Key</h2>
                <p>Enter the encryption key used to encrypt the existing database</p>
                <div class="step-info">
                    <i class="fa-solid fa-database"></i> <strong>Existing database detected</strong> with <?= intval($_SESSION['setup']['existing_table_count'] ?? 0) ?> tables and <?= intval($_SESSION['setup']['existing_user_count'] ?? 0) ?> users. Enter the encryption key that was used when the database was originally configured. This key is needed to decrypt the existing data.
                </div>
            <?php else: ?>
                <h2>Step 2: Encryption Key Setup</h2>
                <p>Configure the encryption key used to protect sensitive data at rest</p>
                <div class="step-info">
                    <i class="fa-solid fa-info-circle"></i> This key is used for AES-256-CBC encryption of personal data (names, phone numbers, addresses). Only the first admin account can change this key later in System Tools.
                </div>
            <?php endif; ?>
            <form method="POST" onsubmit="return validateSetupEncryptionKey()">
                <div class="form-group">
                    <label>Encryption Key (64-character hex string)</label>
                    <input type="text" name="encryption_key" id="setup-encryption-key" 
                           placeholder="<?= $is_existing_db ? 'Enter your existing encryption key' : 'Enter or generate a 64-character hex key' ?>" 
                           pattern="[a-fA-F0-9]{64}" maxlength="64" required
                           style="font-family: monospace;">
                    <?php if (!$is_existing_db): ?>
                    <div style="margin-top: 8px;">
                        <button type="button" onclick="generateSetupKey()" style="background: none; border: 1px solid var(--border); color: var(--primary); padding: 6px 12px; border-radius: 4px; cursor: pointer; font-size: 12px; font-family: 'Inter', sans-serif;">
                            <i class="fa-solid fa-random"></i> Generate Random Key
                        </button>
                    </div>
                    <?php endif; ?>
                    <p style="color: #94a3b8; font-size: 11px; margin-top: 6px;">Must be exactly 64 hexadecimal characters (0-9, a-f). <?= $is_existing_db ? 'This must match the key originally used to encrypt the database.' : 'This key will be saved to your environment file.' ?></p>
                </div>
                <div class="alert alert-warning" style="margin-bottom: 20px;">
                    <i class="fa-solid fa-exclamation-triangle"></i> <strong>Important:</strong> <?= $is_existing_db ? 'If you enter the wrong key, encrypted data will not be readable. Make sure you have the correct key before proceeding.' : 'Back up your encryption key securely. If the key is lost, encrypted data cannot be recovered. Store a copy in a secure password manager or offline backup.' ?>
                </div>
                <button type="submit" class="btn-primary"><?= $is_existing_db ? 'Validate Key & Continue' : 'Continue to Step 3' ?></button>
            </form>
            <script>
            <?php if (!$is_existing_db): ?>
            function generateSetupKey() {
                var array = new Uint8Array(32);
                crypto.getRandomValues(array);
                var hex = Array.from(array).map(function(b) { return b.toString(16).padStart(2, '0'); }).join('');
                document.getElementById('setup-encryption-key').value = hex;
            }
            <?php endif; ?>
            function validateSetupEncryptionKey() {
                var key = document.getElementById('setup-encryption-key').value.trim();
                if (!/^[a-fA-F0-9]{64}$/.test(key)) {
                    alert('The encryption key must be exactly 64 hexadecimal characters (0-9, a-f).');
                    return false;
                }
                return true;
            }
            </script>
        <?php elseif ($step == 3 && !empty($_SESSION['setup']['existing_database'])): ?>
            <h2>Step 3: Database Schema Migration</h2>
            <p>Scan and update the database schema to ensure it is current</p>
            <div class="step-info">
                <i class="fa-solid fa-database"></i> The existing database will be scanned against the expected schema. Missing tables will be created and missing columns will be added. Existing data will be preserved.
            </div>
            <?php if (isset($_SESSION['setup']['schema_migration'])): ?>
                <?php $mig = $_SESSION['setup']['schema_migration']; ?>
                <?php if (!empty($mig['results'])): ?>
                    <div class="alert alert-success" style="max-height: 200px; overflow-y: auto;">
                        <i class="fa-solid fa-check-circle"></i> <strong>Migration Results:</strong><br/>
                        <?php foreach ($mig['results'] as $r): ?>
                            • <?= htmlspecialchars($r) ?><br/>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($mig['errors'])): ?>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-exclamation-triangle"></i> <strong>Warnings:</strong><br/>
                        <?php foreach ($mig['errors'] as $e): ?>
                            • <?= htmlspecialchars($e) ?><br/>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ($mig['remaining_issues'] === 0): ?>
                    <div class="alert alert-success">
                        <i class="fa-solid fa-check-circle"></i> Schema verification passed. The database is up to date.
                    </div>
                <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fa-solid fa-exclamation-triangle"></i> <?= $mig['remaining_issues'] ?> table(s) could not be created automatically. Review the warnings above.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            <form method="POST">
                <button type="submit" class="btn-primary">Scan & Update Schema</button>
            </form>
        <?php elseif ($step == 3): ?>
            <h2>Step 3: Create Admin User</h2>
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
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="admin_password" id="admin_password" required minlength="8" style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('admin_password', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="admin_password_confirm" id="admin_password_confirm" required minlength="8" style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('admin_password_confirm', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn-primary">Continue to Step 4</button>
            </form>
        <?php elseif ($step == 4): ?>
            <h2>Step 4: SMTP Configuration</h2>
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
                    <div style="position: relative; display: flex; align-items: center;">
                        <input type="password" name="smtp_pass" id="smtp_pass" required style="flex: 1; padding-right: 40px;">
                        <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility('smtp_pass', this)" aria-label="Toggle password visibility" style="position: absolute; right: 10px; background: none; border: none; cursor: pointer; color: #64748b; padding: 5px;">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="form-group">
                    <label>From Email Address</label>
                    <input type="email" name="smtp_from" required>
                </div>
                <button type="submit" class="btn-primary">Continue to Step 5</button>
            </form>
        <?php elseif ($step == 5): ?>
            <h2>Step 5: Complete Setup</h2>
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
