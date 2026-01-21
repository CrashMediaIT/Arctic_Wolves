<?php
/**
 * Testing View
 * System testing and diagnostics interface
 */

require_once __DIR__ . '/../security.php';

// Admin only
if (!$isAdmin) {
    echo "<div class='alert alert-error'>Access Denied: Admin privileges required.</div>";
    exit;
}

// Get recent testing results
$results_stmt = $pdo->query("
    SELECT * FROM testing_results 
    ORDER BY created_at DESC 
    LIMIT 50
");
$test_results = $results_stmt->fetchAll();

// Get system info
$db_version = $pdo->query("SELECT VERSION()")->fetchColumn();
$php_version = phpversion();
$server_software = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
?>

<style>
    :root {
        --primary: #7000a4;
        --neon: #7000a4;
    }
    .testing-header {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 30px;
        margin-bottom: 30px;
        color: #fff;
    }
    .testing-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 900;
    }
    .section-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 30px;
    }
    .test-item {
        background: #161b22;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .test-item.passed {
        border-left: 4px solid #10b981;
    }
    .test-item.failed {
        border-left: 4px solid #ef4444;
    }
    .badge {
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-success {
        background: #10b981;
        color: #fff;
    }
    .badge-error {
        background: #ef4444;
        color: #fff;
    }
    .sys-info {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .sys-info-item {
        background: #161b22;
        padding: 15px;
        border-radius: 6px;
    }
</style>

<div class="testing-header">
    <h1><i class="fa-solid fa-flask-vial"></i> System Testing</h1>
    <p>Run tests and view diagnostics for the Crash Hockey system</p>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] === 'recorded'): ?>
    <div class="alert alert-success">
        Test results recorded successfully!
    </div>
<?php endif; ?>

<div class="section-card">
    <h2>System Information</h2>
    <div class="sys-info">
        <div class="sys-info-item">
            <strong>PHP Version</strong>
            <p style="margin: 5px 0 0 0; color: #8b949e;"><?= htmlspecialchars($php_version) ?></p>
        </div>
        <div class="sys-info-item">
            <strong>Database Version</strong>
            <p style="margin: 5px 0 0 0; color: #8b949e;"><?= htmlspecialchars($db_version) ?></p>
        </div>
        <div class="sys-info-item">
            <strong>Server Software</strong>
            <p style="margin: 5px 0 0 0; color: #8b949e;"><?= htmlspecialchars($server_software) ?></p>
        </div>
        <div class="sys-info-item">
            <strong>Memory Limit</strong>
            <p style="margin: 5px 0 0 0; color: #8b949e;"><?= ini_get('memory_limit') ?></p>
        </div>
    </div>
</div>

<div class="section-card">
    <h2>Quick Tests</h2>
    <form action="process_testing.php" method="POST">
        <?php csrfTokenGenerate(); ?>
        <input type="hidden" name="action" value="run_tests">
        
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tests[]" value="database_connection">
                Database Connection
            </label>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tests[]" value="email_config">
                Email Configuration
            </label>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tests[]" value="file_permissions">
                File Permissions
            </label>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tests[]" value="api_endpoints">
                API Endpoints
            </label>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tests[]" value="security_headers">
                Security Headers
            </label>
            <label style="display: flex; align-items: center; gap: 8px;">
                <input type="checkbox" name="tests[]" value="session_handling">
                Session Handling
            </label>
        </div>

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-play"></i> Run Selected Tests
        </button>
    </form>
</div>

<div class="section-card">
    <h2>Recent Test Results (Last 50)</h2>
    <?php if (count($test_results) > 0): ?>
        <?php foreach ($test_results as $result): ?>
            <div class="test-item <?= $result['status'] === 'passed' ? 'passed' : 'failed' ?>">
                <div>
                    <strong><?= htmlspecialchars($result['test_name']) ?></strong>
                    <p style="margin: 5px 0 0 0; font-size: 13px; color: #8b949e;">
                        <?= htmlspecialchars($result['message'] ?? '') ?>
                    </p>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #6b7280;">
                        <?= date('M d, Y H:i:s', strtotime($result['created_at'])) ?>
                    </p>
                </div>
                <div>
                    <span class="badge badge-<?= $result['status'] === 'passed' ? 'success' : 'error' ?>">
                        <?= ucfirst($result['status']) ?>
                    </span>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="color: #8b949e; margin-top: 15px;">No test results recorded yet. Run some tests to get started!</p>
    <?php endif; ?>
</div>

<div class="section-card">
    <h2>Manual Test Entry</h2>
    <form action="process_testing.php" method="POST">
        <?php csrfTokenGenerate(); ?>
        <input type="hidden" name="action" value="record_test">
        
        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Test Name</label>
            <input type="text" name="test_name" required class="form-control" placeholder="e.g., User Authentication Test">
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Status</label>
            <select name="status" required class="form-control">
                <option value="passed">Passed</option>
                <option value="failed">Failed</option>
            </select>
        </div>

        <div style="margin-bottom: 15px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Message</label>
            <textarea name="message" class="form-control" rows="3" placeholder="Optional test details or notes"></textarea>
        </div>

        <button type="submit" class="btn-primary">
            <i class="fa-solid fa-save"></i> Record Test Result
        </button>
    </form>
</div>

<div class="section-card">
    <h2>Diagnostic Tools</h2>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="?page=admin_system_check" class="btn-secondary">
            <i class="fa-solid fa-stethoscope"></i> System Health Check
        </a>
        <a href="?page=admin_database_tools" class="btn-secondary">
            <i class="fa-solid fa-database"></i> Database Tools
        </a>
        <a href="?page=admin_audit_logs" class="btn-secondary">
            <i class="fa-solid fa-list"></i> Audit Logs
        </a>
        <a href="?page=admin_cron_jobs" class="btn-secondary">
            <i class="fa-solid fa-clock"></i> Cron Jobs
        </a>
    </div>
</div>
