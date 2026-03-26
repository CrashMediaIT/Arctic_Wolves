<?php
/**
 * Arctic Wolves - System Health Validator
 * 
 * Comprehensive validation tool to check system health and verify fixes.
 * Tests database connectivity, file permissions, routing, and feature availability.
 * 
 * @version 1.0
 * @date January 23, 2026
 */

session_start();
require_once __DIR__ . '/db_config.php';

// Only allow admin access
if (!isset($_SESSION['logged_in']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

class SystemHealthValidator {
    private $pdo;
    private $results = [];
    private $totalChecks = 0;
    private $passedChecks = 0;
    private $failedChecks = 0;
    private $warningChecks = 0;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Run all validation checks
     */
    public function runAllChecks() {
        $this->results = [
            'database' => $this->checkDatabase(),
            'files' => $this->checkFiles(),
            'routing' => $this->checkRouting(),
            'tables' => $this->checkTables(),
            'demo_data' => $this->checkDemoData(),
            'security' => $this->checkSecurity(),
        ];
        
        return $this->results;
    }
    
    /**
     * Check database connectivity and configuration
     */
    private function checkDatabase() {
        $checks = [];
        
        // Test database connection
        try {
            $this->pdo->query("SELECT 1");
            $checks[] = $this->pass("Database connection successful");
            
            // Check database version
            $version = $this->pdo->query("SELECT VERSION()")->fetchColumn();
            $checks[] = $this->info("MySQL Version: " . $version);
            
            // Check character set
            $charset = $this->pdo->query("SELECT @@character_set_database")->fetchColumn();
            if ($charset === 'utf8mb4') {
                $checks[] = $this->pass("Database charset: utf8mb4 (correct)");
            } else {
                $checks[] = $this->warn("Database charset: $charset (should be utf8mb4)");
            }
            
        } catch (PDOException $e) {
            $checks[] = $this->fail("Database connection failed: " . $e->getMessage());
        }
        
        return $checks;
    }
    
    /**
     * Check critical files exist and are readable
     */
    private function checkFiles() {
        $checks = [];
        
        $critical_files = [
            'db_config.php',
            'dashboard.php',
            'setup.php',
            'process_admin_action.php',
            'security.php',
            'csrf_protection.php',
            'error_logger.php',
        ];
        
        foreach ($critical_files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path) && is_readable($path)) {
                $checks[] = $this->pass("File exists: $file");
            } else {
                $checks[] = $this->fail("Missing or unreadable: $file");
            }
        }
        
        // Check writable directories
        $writable_dirs = ['logs', 'backups', 'uploads', 'cache'];
        foreach ($writable_dirs as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (is_dir($path) && is_writable($path)) {
                $checks[] = $this->pass("Directory writable: $dir");
            } else {
                $checks[] = $this->warn("Directory not writable: $dir");
            }
        }
        
        return $checks;
    }
    
    /**
     * Check routing configuration
     */
    private function checkRouting() {
        $checks = [];
        
        // Check if dashboard.php exists and has routing
        $dashboard_content = file_get_contents(__DIR__ . '/dashboard.php');
        
        if (strpos($dashboard_content, '$allowed_pages') !== false) {
            $checks[] = $this->pass("Routing table exists in dashboard.php");
            
            // Count routes
            preg_match_all('/\'[a-z_]+\'\s*=>\s*\'views\/[a-z_]+\.php\'/', $dashboard_content, $matches);
            $route_count = count($matches[0]);
            $checks[] = $this->info("Total routes configured: $route_count");
        } else {
            $checks[] = $this->fail("Routing table not found in dashboard.php");
        }
        
        // Check critical views exist
        $critical_views = [
            'views/home.php',
            'views/sessions.php',
            'views/drills.php',
            'views/admin_system_tools.php',
        ];
        
        foreach ($critical_views as $view) {
            if (file_exists(__DIR__ . '/' . $view)) {
                $checks[] = $this->pass("View exists: $view");
            } else {
                $checks[] = $this->fail("Missing view: $view");
            }
        }
        
        return $checks;
    }
    
    /**
     * Check database tables
     */
    private function checkTables() {
        $checks = [];
        
        try {
            // Count total tables
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $table_count = count($tables);
            
            $checks[] = $this->info("Total database tables: $table_count");
            
            // Check critical tables
            $critical_tables = [
                'users', 'sessions', 'teams', 'drills', 'practice_plans',
                'packages', 'locations', 'goals', 'eval_categories', 'eval_skills'
            ];
            
            foreach ($critical_tables as $table) {
                if (in_array($table, $tables)) {
                    $checks[] = $this->pass("Table exists: $table");
                } else {
                    $checks[] = $this->fail("Missing table: $table");
                }
            }
            
            // Check for is_demo column in sample tables
            $sample_tables = ['users', 'sessions', 'drills'];
            foreach ($sample_tables as $table) {
                if (in_array($table, $tables)) {
                    $stmt = $this->pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
                    $stmt->execute(['is_demo']);
                    $columns = $stmt->fetchAll();
                    if (count($columns) > 0) {
                        $checks[] = $this->pass("Demo column exists in $table");
                    } else {
                        $checks[] = $this->warn("Demo column missing in $table");
                    }
                }
            }
            
        } catch (PDOException $e) {
            $checks[] = $this->fail("Table check failed: " . $e->getMessage());
        }
        
        return $checks;
    }
    
    /**
     * Check demo data status
     */
    private function checkDemoData() {
        $checks = [];
        
        try {
            // Count demo records
            $total_demo = 0;
            $stmt = $this->pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                try {
                    $count_stmt = $this->pdo->query("SELECT COUNT(*) FROM `$table` WHERE is_demo = 1");
                    $count = $count_stmt->fetchColumn();
                    $total_demo += $count;
                } catch (PDOException $e) {
                    // Table might not have is_demo column
                }
            }
            
            if ($total_demo > 0) {
                $checks[] = $this->info("Demo data present: $total_demo records");
                $checks[] = $this->warn("System contains demo data (use Production Mode to remove)");
            } else {
                $checks[] = $this->pass("No demo data found - database is clean");
            }
            
        } catch (PDOException $e) {
            $checks[] = $this->warn("Could not check demo data: " . $e->getMessage());
        }
        
        return $checks;
    }
    
    /**
     * Check security configuration
     */
    private function checkSecurity() {
        $checks = [];
        
        // Check if error display is disabled (should be in production)
        $display_errors = ini_get('display_errors');
        if ($display_errors == '0' || $display_errors === false) {
            $checks[] = $this->pass("Error display disabled (production safe)");
        } else {
            $checks[] = $this->warn("Error display enabled (development mode)");
        }
        
        // Check if session is secure
        if (session_status() === PHP_SESSION_ACTIVE) {
            $checks[] = $this->pass("Session active");
        } else {
            $checks[] = $this->fail("Session not active");
        }
        
        // Check .env file exists (should have database config)
        if (file_exists(__DIR__ . '/arctic_wolves.env')) {
            $checks[] = $this->pass("Environment file exists");
            
            // Check permissions (should not be world-readable)
            $perms = fileperms(__DIR__ . '/arctic_wolves.env');
            if (($perms & 0x0004) === 0) {
                $checks[] = $this->pass("Environment file not world-readable");
            } else {
                $checks[] = $this->warn("Environment file is world-readable");
            }
        } else {
            $checks[] = $this->warn("Environment file not found (using db_config.php)");
        }
        
        // Check setup completion
        if (file_exists(__DIR__ . '/.setup_complete')) {
            $checks[] = $this->pass("Setup completed");
        } else {
            $checks[] = $this->warn("Setup not marked as complete");
        }
        
        return $checks;
    }
    
    /**
     * Create a pass result
     */
    private function pass($message) {
        $this->totalChecks++;
        $this->passedChecks++;
        return ['status' => 'pass', 'message' => $message];
    }
    
    /**
     * Create a fail result
     */
    private function fail($message) {
        $this->totalChecks++;
        $this->failedChecks++;
        return ['status' => 'fail', 'message' => $message];
    }
    
    /**
     * Create a warning result
     */
    private function warn($message) {
        $this->totalChecks++;
        $this->warningChecks++;
        return ['status' => 'warn', 'message' => $message];
    }
    
    /**
     * Create an info result
     */
    private function info($message) {
        $this->totalChecks++;
        return ['status' => 'info', 'message' => $message];
    }
    
    /**
     * Get summary statistics
     */
    public function getSummary() {
        return [
            'total' => $this->totalChecks,
            'passed' => $this->passedChecks,
            'failed' => $this->failedChecks,
            'warnings' => $this->warningChecks,
            'health_score' => $this->totalChecks > 0 
                ? round(($this->passedChecks / $this->totalChecks) * 100) 
                : 0
        ];
    }
}

// Run validation if requested
$validator = new SystemHealthValidator($pdo);
$results = null;
$summary = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_validation'])) {
    $results = $validator->runAllChecks();
    $summary = $validator->getSummary();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Health Validator | Arctic Wolves</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .validator-container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }
        
        .validator-header {
            background: linear-gradient(135deg, #6B46C1 0%, #7C3AED 100%);
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .validator-header h1 {
            font-size: 32px;
            font-weight: 900;
            color: white;
            margin-bottom: 10px;
        }
        
        .validator-header p {
            font-size: 16px;
            color: rgba(255, 255, 255, 0.9);
        }
        
        .run-validation-btn {
            background: white;
            color: #6B46C1;
            padding: 16px 32px;
            font-size: 16px;
            font-weight: 700;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-top: 20px;
            transition: all 0.3s ease;
        }
        
        .run-validation-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }
        
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            text-align: center;
        }
        
        .summary-card .number {
            font-size: 42px;
            font-weight: 900;
            margin-bottom: 8px;
        }
        
        .summary-card .label {
            font-size: 14px;
            color: var(--text-dim);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .summary-card.passed .number { color: #10b981; }
        .summary-card.failed .number { color: #ef4444; }
        .summary-card.warnings .number { color: #f59e0b; }
        .summary-card.health .number { color: #6B46C1; }
        
        .results-section {
            margin-bottom: 30px;
        }
        
        .results-card {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 20px;
        }
        
        .results-card h3 {
            font-size: 20px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .check-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .check-item.pass {
            background: rgba(16, 185, 129, 0.1);
            border-left: 3px solid #10b981;
        }
        
        .check-item.fail {
            background: rgba(239, 68, 68, 0.1);
            border-left: 3px solid #ef4444;
        }
        
        .check-item.warn {
            background: rgba(245, 158, 11, 0.1);
            border-left: 3px solid #f59e0b;
        }
        
        .check-item.info {
            background: rgba(59, 130, 246, 0.1);
            border-left: 3px solid #3b82f6;
        }
        
        .check-item i {
            font-size: 18px;
        }
        
        .check-item.pass i { color: #10b981; }
        .check-item.fail i { color: #ef4444; }
        .check-item.warn i { color: #f59e0b; }
        .check-item.info i { color: #3b82f6; }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="validator-container">
        <a href="dashboard.php?page=admin_system_tools" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to System Tools
        </a>
        
        <div class="validator-header">
            <h1><i class="fas fa-heartbeat"></i> System Health Validator</h1>
            <p>Comprehensive system health check and validation tool</p>
            <form method="POST">
                <button type="submit" name="run_validation" class="run-validation-btn">
                    <i class="fas fa-play"></i> Run System Validation
                </button>
            </form>
        </div>
        
        <?php if ($results): ?>
            <div class="summary-grid">
                <div class="summary-card passed">
                    <div class="number"><?= $summary['passed'] ?></div>
                    <div class="label">Passed</div>
                </div>
                <div class="summary-card failed">
                    <div class="number"><?= $summary['failed'] ?></div>
                    <div class="label">Failed</div>
                </div>
                <div class="summary-card warnings">
                    <div class="number"><?= $summary['warnings'] ?></div>
                    <div class="label">Warnings</div>
                </div>
                <div class="summary-card health">
                    <div class="number"><?= $summary['health_score'] ?>%</div>
                    <div class="label">Health Score</div>
                </div>
            </div>
            
            <div class="results-section">
                <?php foreach ($results as $category => $checks): ?>
                    <div class="results-card">
                        <h3>
                            <i class="fas fa-<?= $category === 'database' ? 'database' : 
                                               ($category === 'files' ? 'file' : 
                                               ($category === 'routing' ? 'route' : 
                                               ($category === 'tables' ? 'table' : 
                                               ($category === 'demo_data' ? 'vial' : 'shield-alt')))) ?>"></i>
                            <?= ucfirst(str_replace('_', ' ', $category)) ?>
                        </h3>
                        <?php foreach ($checks as $check): ?>
                            <div class="check-item <?= $check['status'] ?>">
                                <i class="fas fa-<?= $check['status'] === 'pass' ? 'check-circle' : 
                                                    ($check['status'] === 'fail' ? 'times-circle' : 
                                                    ($check['status'] === 'warn' ? 'exclamation-triangle' : 'info-circle')) ?>"></i>
                                <span><?= htmlspecialchars($check['message']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="results-card">
                <p style="text-align: center; color: var(--text-dim); font-size: 16px;">
                    <i class="fas fa-info-circle"></i> Click "Run System Validation" to start health check
                </p>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
