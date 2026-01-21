<?php
/**
 * Weekly Security Vulnerability Scanner
 * Scans application for security vulnerabilities
 * Run via cron: 0 2 * * 0 (Every Sunday at 2 AM)
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY') ?: 'change_this_in_production';
    
    if ($secret_key !== $expected_key) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "Starting security vulnerability scan...\n";
$scan_start = microtime(true);

$vulnerabilities = [];
$checks_performed = 0;

// 1. Check for SQL Injection vulnerabilities in process files
echo "Checking for SQL injection vulnerabilities...\n";
$sql_injection_check = checkSQLInjection();
$vulnerabilities = array_merge($vulnerabilities, $sql_injection_check);
$checks_performed++;

// 2. Check for XSS vulnerabilities in view files
echo "Checking for XSS vulnerabilities...\n";
$xss_check = checkXSS();
$vulnerabilities = array_merge($vulnerabilities, $xss_check);
$checks_performed++;

// 3. Check for insecure file permissions
echo "Checking file permissions...\n";
$permissions_check = checkFilePermissions();
$vulnerabilities = array_merge($vulnerabilities, $permissions_check);
$checks_performed++;

// 4. Check for missing CSRF protection
echo "Checking CSRF protection...\n";
$csrf_check = checkCSRFProtection();
$vulnerabilities = array_merge($vulnerabilities, $csrf_check);
$checks_performed++;

// 5. Check for outdated dependencies (if composer.json exists)
echo "Checking dependencies...\n";
$dependency_check = checkDependencies();
$vulnerabilities = array_merge($vulnerabilities, $dependency_check);
$checks_performed++;

// 6. Check for exposed sensitive files
echo "Checking for exposed sensitive files...\n";
$sensitive_files_check = checkSensitiveFiles();
$vulnerabilities = array_merge($vulnerabilities, $sensitive_files_check);
$checks_performed++;

// 7. Check password security
echo "Checking password security...\n";
$password_check = checkPasswordSecurity();
$vulnerabilities = array_merge($vulnerabilities, $password_check);
$checks_performed++;

// 8. Check session security
echo "Checking session security...\n";
$session_check = checkSessionSecurity();
$vulnerabilities = array_merge($vulnerabilities, $session_check);
$checks_performed++;

$scan_duration = round(microtime(true) - $scan_start, 2);
$vulnerability_count = count($vulnerabilities);

echo "Scan completed in {$scan_duration} seconds.\n";
echo "Checks performed: {$checks_performed}\n";
echo "Vulnerabilities found: {$vulnerability_count}\n";

// Save scan results to database
try {
    $stmt = $pdo->prepare("
        INSERT INTO security_scans (scan_date, vulnerabilities_found, details, scan_status, scan_duration)
        VALUES (NOW(), ?, ?, 'completed', ?)
    ");
    
    $details = [
        'checks_performed' => $checks_performed,
        'scan_duration' => $scan_duration,
        'vulnerabilities' => $vulnerabilities,
        'summary' => generateSummary($vulnerabilities)
    ];
    
    $stmt->execute([
        $vulnerability_count,
        json_encode($details),
        $scan_duration
    ]);
    
    $scan_id = $pdo->lastInsertId();
    
    // If vulnerabilities found, notify admins
    if ($vulnerability_count > 0) {
        notifyAdmins($vulnerability_count, $vulnerabilities, $scan_id);
        
        // Mark as notified
        $pdo->prepare("UPDATE security_scans SET notified_admins = 1 WHERE id = ?")->execute([$scan_id]);
    }
    
    echo "Scan results saved to database (ID: $scan_id)\n";
    
} catch (Exception $e) {
    echo "Error saving scan results: " . $e->getMessage() . "\n";
}

// Functions for vulnerability checks

function checkSQLInjection() {
    $issues = [];
    $process_files = glob(__DIR__ . '/process_*.php');
    
    foreach ($process_files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);
        
        // Check for direct variable interpolation in queries
        if (preg_match('/\$pdo->query\([\'"].*\$.*[\'"]\)/', $content)) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'SQL Injection',
                'file' => $filename,
                'description' => 'Potential SQL injection: Direct variable interpolation in query'
            ];
        }
        
        // Check for unprepared statements with user input
        if (preg_match('/\$pdo->exec\([\'"].*\$_(GET|POST|REQUEST).*[\'"]\)/', $content)) {
            $issues[] = [
                'severity' => 'critical',
                'type' => 'SQL Injection',
                'file' => $filename,
                'description' => 'Critical SQL injection: User input in exec() without preparation'
            ];
        }
    }
    
    return $issues;
}

function checkXSS() {
    $issues = [];
    $view_files = glob(__DIR__ . '/views/*.php');
    
    foreach ($view_files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);
        
        // Check for unescaped echo statements
        if (preg_match_all('/echo\s+\$[^;]+(?<!htmlspecialchars\([^)]+\))/', $content, $matches)) {
            // This is a simplistic check - may have false positives
            $count = count($matches[0]);
            if ($count > 5) { // Only flag if multiple instances
                $issues[] = [
                    'severity' => 'medium',
                    'type' => 'XSS',
                    'file' => $filename,
                    'description' => "Potential XSS: $count unescaped echo statements found"
                ];
            }
        }
    }
    
    return $issues;
}

function checkFilePermissions() {
    $issues = [];
    
    $sensitive_files = [
        'db_config.php',
        'crashhockey.env',
        'cloud_config.php'
    ];
    
    foreach ($sensitive_files as $file) {
        $filepath = __DIR__ . '/' . $file;
        if (file_exists($filepath)) {
            $perms = fileperms($filepath);
            $perms_octal = substr(sprintf('%o', $perms), -4);
            
            // Check if file is world-readable (last digit is 4 or higher)
            if ($perms_octal[3] >= 4) {
                $issues[] = [
                    'severity' => 'high',
                    'type' => 'File Permissions',
                    'file' => $file,
                    'description' => "Sensitive file is world-readable (permissions: $perms_octal)"
                ];
            }
        }
    }
    
    // Check if uploads directory is executable
    $uploads_dir = __DIR__ . '/uploads';
    if (file_exists($uploads_dir) && is_executable($uploads_dir)) {
        $issues[] = [
            'severity' => 'medium',
            'type' => 'File Permissions',
            'file' => 'uploads/',
            'description' => 'Uploads directory should not have execute permissions'
        ];
    }
    
    return $issues;
}

function checkCSRFProtection() {
    $issues = [];
    $process_files = glob(__DIR__ . '/process_*.php');
    
    foreach ($process_files as $file) {
        $content = file_get_contents($file);
        $filename = basename($file);
        
        // Check if file handles POST requests but doesn't check CSRF
        if (strpos($content, '$_POST') !== false && 
            strpos($content, 'csrf') === false && 
            strpos($content, 'checkCsrfToken') === false) {
            
            $issues[] = [
                'severity' => 'medium',
                'type' => 'CSRF',
                'file' => $filename,
                'description' => 'POST request handler missing CSRF protection'
            ];
        }
    }
    
    return $issues;
}

function checkDependencies() {
    $issues = [];
    
    // This is a placeholder - in production, you'd check composer.lock or package.json
    // and compare against known vulnerability databases
    
    return $issues;
}

function checkSensitiveFiles() {
    $issues = [];
    
    $sensitive_patterns = [
        '.git',
        '.env',
        'composer.json',
        'package.json',
        '.htaccess'
    ];
    
    foreach ($sensitive_patterns as $pattern) {
        $files = glob(__DIR__ . '/' . $pattern);
        foreach ($files as $file) {
            // Check if file is accessible via web (this is a basic check)
            $basename = basename($file);
            $issues[] = [
                'severity' => 'low',
                'type' => 'Sensitive Files',
                'file' => $basename,
                'description' => 'Sensitive file present - ensure it\'s protected by .htaccess'
            ];
        }
    }
    
    return $issues;
}

function checkPasswordSecurity() {
    global $pdo;
    $issues = [];
    
    try {
        // Check for weak password hashes (bcrypt should be used)
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE LENGTH(password) < 60");
        $result = $stmt->fetch();
        
        if ($result['count'] > 0) {
            $issues[] = [
                'severity' => 'high',
                'type' => 'Password Security',
                'file' => 'database',
                'description' => "{$result['count']} users have weak password hashes"
            ];
        }
    } catch (Exception $e) {
        // Skip if can't check
    }
    
    return $issues;
}

function checkSessionSecurity() {
    $issues = [];
    
    // Check php.ini settings (if accessible)
    if (ini_get('session.cookie_httponly') != 1) {
        $issues[] = [
            'severity' => 'medium',
            'type' => 'Session Security',
            'file' => 'php.ini',
            'description' => 'session.cookie_httponly is not enabled'
        ];
    }
    
    if (ini_get('session.cookie_secure') != 1) {
        $issues[] = [
            'severity' => 'medium',
            'type' => 'Session Security',
            'file' => 'php.ini',
            'description' => 'session.cookie_secure is not enabled (cookies sent over HTTP)'
        ];
    }
    
    return $issues;
}

function generateSummary($vulnerabilities) {
    $summary = [
        'critical' => 0,
        'high' => 0,
        'medium' => 0,
        'low' => 0
    ];
    
    foreach ($vulnerabilities as $vuln) {
        $severity = $vuln['severity'] ?? 'low';
        $summary[$severity]++;
    }
    
    return $summary;
}

function notifyAdmins($count, $vulnerabilities, $scan_id) {
    global $pdo;
    
    // Get all admins
    $stmt = $pdo->query("SELECT id, email, first_name, last_name FROM users WHERE role = 'admin'");
    $admins = $stmt->fetchAll();
    
    $summary = generateSummary($vulnerabilities);
    
    foreach ($admins as $admin) {
        // Prepare data for custom email type
        $data = [
            'name' => $admin['first_name'] . ' ' . $admin['last_name'],
            'vulnerability_count' => $count,
            'critical' => $summary['critical'],
            'high' => $summary['high'],
            'medium' => $summary['medium'],
            'low' => $summary['low'],
            'vulnerabilities' => array_slice($vulnerabilities, 0, 5),
            'scan_id' => $scan_id,
            'app_url' => getenv('APP_URL') ?: 'https://yourdomain.com'
        ];
        
        // Use a custom email template for security scans
        // Note: You'll need to add this template type to mailer.php
        // For now, send a simple email directly
        $subject = "Security Alert: $count Vulnerabilities Found";
        
        $message = "
        <div style='font-family: Arial, sans-serif; background: #06080b; color: #fff; padding: 30px; border-radius: 8px; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #ff4444; margin-top: 0;'>Security Vulnerability Scan Results</h2>
            
            <p style='color: #ccc;'>The weekly security scan has detected <strong>$count vulnerabilities</strong> in the Crash Hockey platform.</p>
            
            <div style='background: #1e293b; padding: 20px; border-radius: 6px; margin: 20px 0;'>
                <h3 style='color: #fff; margin-top: 0;'>Summary by Severity:</h3>
                <ul style='color: #ccc;'>
                    <li><strong>Critical:</strong> {$summary['critical']}</li>
                    <li><strong>High:</strong> {$summary['high']}</li>
                    <li><strong>Medium:</strong> {$summary['medium']}</li>
                    <li><strong>Low:</strong> {$summary['low']}</li>
                </ul>
            </div>
            
            <div style='background: #1e293b; padding: 20px; border-radius: 6px; margin: 20px 0;'>
                <h3 style='color: #fff; margin-top: 0;'>Top Vulnerabilities:</h3>
                <ul style='color: #ccc;'>";
        
        $top_vulns = array_slice($vulnerabilities, 0, 5);
        foreach ($top_vulns as $vuln) {
            $message .= "<li><strong>[{$vuln['severity']}]</strong> {$vuln['type']} in {$vuln['file']}: {$vuln['description']}</li>";
        }
        
        $message .= "
                </ul>
            </div>
            
            <p style='color: #ccc;'>Please review the full scan results in the admin dashboard.</p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='{$data['app_url']}/dashboard.php?page=security_scans&scan_id=$scan_id' 
                   style='background: #7000a4; color: #fff; padding: 12px 24px; text-decoration: none; border-radius: 6px; display: inline-block; font-weight: 700;'>
                   View Full Scan Results
                </a>
            </div>
            
            <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #333; text-align: center; color: #555; font-size: 11px;'>
                &copy; " . date('Y') . " Crash Hockey Performance. All rights reserved.
            </div>
        </div>";
        
        // Use custom email
        try {
            $smtp_config = [
                'smtp_host' => getenv('SMTP_HOST') ?: 'localhost',
                'smtp_port' => getenv('SMTP_PORT') ?: 25,
                'smtp_user' => getenv('SMTP_USER') ?: '',
                'smtp_pass' => getenv('SMTP_PASS') ?: '',
                'smtp_encryption' => getenv('SMTP_ENCRYPTION') ?: 'none'
            ];
            
            $mailer = new SmtpMailer();
            $mailer->send($admin['email'], $subject, $message, $smtp_config);
        } catch (Exception $e) {
            echo "Failed to send email to {$admin['email']}: " . $e->getMessage() . "\n";
        }
        
        // Create in-app notification
        $notif_stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, priority, created_at)
            VALUES (?, 'security', ?, ?, 'high', NOW())
        ");
        
        $notif_stmt->execute([
            $admin['id'],
            "Security Scan: $count Vulnerabilities Found",
            "The weekly security scan detected $count vulnerabilities. Critical: {$summary['critical']}, High: {$summary['high']}, Medium: {$summary['medium']}, Low: {$summary['low']}"
        ]);
    }
    
    echo "Notified " . count($admins) . " administrators\n";
}

echo "\n=== Security Scan Complete ===\n";
