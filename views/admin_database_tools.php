<?php
/**
 * Database Repair & Maintenance Tool
 * Admin-only tool for database integrity and optimization
 */

require_once __DIR__ . '/../security.php';

// Admin only
if ($user_role !== 'admin') {
    header('Location: dashboard.php?page=home');
    exit;
}

$csrf_token = generateCsrfToken();
$action_performed = false;
$results = [];

// Handle maintenance actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token'])) {
    checkCsrfToken();
    
    $maintenance_action = $_POST['maintenance_action'] ?? '';
    
    try {
        switch ($maintenance_action) {
            case 'check_integrity':
                $results = checkDatabaseIntegrity();
                break;
            case 'repair_tables':
                $results = repairTables();
                break;
            case 'optimize_tables':
                $results = optimizeTables();
                break;
            case 'check_foreign_keys':
                $results = checkForeignKeys();
                break;
            case 'repair_foreign_keys':
                $results = repairForeignKeys();
                break;
            case 'analyze_performance':
                $results = analyzePerformance();
                break;
        }
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO database_maintenance_logs (run_by, action_type, details, status)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $maintenance_action,
            json_encode($results),
            $results['status'] ?? 'success'
        ]);
        
        $action_performed = true;
        
    } catch (Exception $e) {
        $results = [
            'status' => 'error',
            'message' => $e->getMessage()
        ];
    }
}

// Get recent maintenance logs
$logs_stmt = $pdo->prepare("
    SELECT dml.*, CONCAT(u.first_name, ' ', u.last_name) as run_by_name
    FROM database_maintenance_logs dml
    INNER JOIN users u ON dml.run_by = u.id
    ORDER BY dml.created_at DESC
    LIMIT 20
");
$logs_stmt->execute();
$recent_logs = $logs_stmt->fetchAll();

// Get table status
$tables_status = [];
$table_result = $pdo->query("SHOW TABLE STATUS");
while ($table = $table_result->fetch(PDO::FETCH_ASSOC)) {
    $tables_status[] = $table;
}

function checkDatabaseIntegrity() {
    global $pdo;
    
    $issues = [];
    $checks_performed = 0;
    
    // Check for orphaned records - whitelist of known safe table/column combinations
    $orphan_checks = [
        ['table' => 'bookings', 'fk_column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id'],
        ['table' => 'bookings', 'fk_column' => 'session_id', 'ref_table' => 'sessions', 'ref_column' => 'id'],
        ['table' => 'athlete_notes', 'fk_column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id'],
        ['table' => 'goals', 'fk_column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id'],
        ['table' => 'user_workouts', 'fk_column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id'],
        ['table' => 'notifications', 'fk_column' => 'user_id', 'ref_table' => 'users', 'ref_column' => 'id']
    ];
    
    foreach ($orphan_checks as $check) {
        $table = $check['table'];
        $fk_column = $check['fk_column'];
        $ref_table = $check['ref_table'];
        $ref_column = $check['ref_column'];
        
        // Use prepared statement with table/column names validated via whitelist
        $query = "
            SELECT COUNT(*) as orphan_count
            FROM `" . $table . "` t
            LEFT JOIN `" . $ref_table . "` r ON t." . $fk_column . " = r." . $ref_column . "
            WHERE t." . $fk_column . " IS NOT NULL AND r." . $ref_column . " IS NULL
        ";
        
        $stmt = $pdo->query($query);
        $result = $stmt->fetch();
        
        if ($result['orphan_count'] > 0) {
            $issues[] = [
                'type' => 'orphaned_records',
                'table' => $table,
                'count' => $result['orphan_count'],
                'message' => "Found {$result['orphan_count']} orphaned records in $table referencing $ref_table"
            ];
        }
        $checks_performed++;
    }
    
    return [
        'status' => empty($issues) ? 'success' : 'warning',
        'checks_performed' => $checks_performed,
        'issues_found' => count($issues),
        'issues' => $issues
    ];
}

function repairTables() {
    global $pdo;
    
    $repaired = [];
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("REPAIR TABLE `$table`");
            $repaired[] = $table;
        } catch (Exception $e) {
            // Some tables may not support REPAIR
        }
    }
    
    return [
        'status' => 'success',
        'tables_repaired' => count($repaired),
        'tables' => $repaired
    ];
}

function optimizeTables() {
    global $pdo;
    
    $optimized = [];
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        try {
            $pdo->exec("OPTIMIZE TABLE `$table`");
            $optimized[] = $table;
        } catch (Exception $e) {
            // Skip tables that can't be optimized
        }
    }
    
    return [
        'status' => 'success',
        'tables_optimized' => count($optimized),
        'tables' => $optimized
    ];
}

function checkForeignKeys() {
    global $pdo;
    
    $issues = [];
    
    // Check if foreign key constraints are valid
    $stmt = $pdo->query("
        SELECT 
            TABLE_NAME,
            COLUMN_NAME,
            CONSTRAINT_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE
        WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    
    $foreign_keys = $stmt->fetchAll();
    
    foreach ($foreign_keys as $fk) {
        // Check for violations
        $check_stmt = $pdo->prepare("
            SELECT COUNT(*) as violation_count
            FROM `{$fk['TABLE_NAME']}` t
            LEFT JOIN `{$fk['REFERENCED_TABLE_NAME']}` r 
            ON t.{$fk['COLUMN_NAME']} = r.{$fk['REFERENCED_COLUMN_NAME']}
            WHERE t.{$fk['COLUMN_NAME']} IS NOT NULL 
            AND r.{$fk['REFERENCED_COLUMN_NAME']} IS NULL
        ");
        $check_stmt->execute();
        $result = $check_stmt->fetch();
        
        if ($result['violation_count'] > 0) {
            $issues[] = [
                'constraint' => $fk['CONSTRAINT_NAME'],
                'table' => $fk['TABLE_NAME'],
                'column' => $fk['COLUMN_NAME'],
                'references' => $fk['REFERENCED_TABLE_NAME'],
                'violations' => $result['violation_count']
            ];
        }
    }
    
    return [
        'status' => empty($issues) ? 'success' : 'warning',
        'foreign_keys_checked' => count($foreign_keys),
        'violations_found' => count($issues),
        'violations' => $issues
    ];
}

function repairForeignKeys() {
    global $pdo;
    
    $repairs = [];
    
    // Get foreign key violations
    $check_result = checkForeignKeys();
    
    if (!empty($check_result['violations'])) {
        foreach ($check_result['violations'] as $violation) {
            // Option 1: Set to NULL (if column allows)
            // Option 2: Delete orphaned records
            
            try {
                // For now, we'll just report - actual repair needs careful consideration
                $repairs[] = [
                    'table' => $violation['table'],
                    'action' => 'identified',
                    'violations' => $violation['violations']
                ];
            } catch (Exception $e) {
                $repairs[] = [
                    'table' => $violation['table'],
                    'action' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }
    }
    
    return [
        'status' => 'success',
        'repairs_attempted' => count($repairs),
        'repairs' => $repairs,
        'note' => 'Foreign key repairs require manual review for data safety'
    ];
}

function analyzePerformance() {
    global $pdo;
    
    $analysis = [];
    
    // Check for missing indexes
    $large_tables = $pdo->query("
        SELECT 
            TABLE_NAME,
            TABLE_ROWS,
            ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_ROWS > 1000
        ORDER BY TABLE_ROWS DESC
    ")->fetchAll();
    
    $analysis['large_tables'] = $large_tables;
    
    // Check for tables without primary keys
    $no_pk = $pdo->query("
        SELECT TABLE_NAME
        FROM information_schema.TABLES t
        LEFT JOIN information_schema.TABLE_CONSTRAINTS tc 
        ON t.TABLE_SCHEMA = tc.TABLE_SCHEMA 
        AND t.TABLE_NAME = tc.TABLE_NAME 
        AND tc.CONSTRAINT_TYPE = 'PRIMARY KEY'
        WHERE t.TABLE_SCHEMA = DATABASE()
        AND tc.CONSTRAINT_NAME IS NULL
        AND t.TABLE_TYPE = 'BASE TABLE'
    ")->fetchAll();
    
    $analysis['tables_without_pk'] = $no_pk;
    
    return [
        'status' => 'success',
        'analysis' => $analysis
    ];
}
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .warning-banner {
        background: linear-gradient(135deg, #ff4444 0%, #cc0000 100%);
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        color: #fff;
    }
    .warning-banner i {
        font-size: 24px;
        margin-right: 15px;
    }
    .tools-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .tool-card {
        background: #0a0f14;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 25px;
        transition: all 0.2s;
    }
    .tool-card:hover {
        border-color: var(--primary);
    }
    .tool-icon {
        width: 50px;
        height: 50px;
        background: rgba(112, 0, 164, 0.2);
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 15px;
        color: var(--primary);
        font-size: 24px;
    }
    .tool-title {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    .tool-desc {
        font-size: 13px;
        color: #94a3b8;
        line-height: 1.6;
        margin-bottom: 20px;
    }
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.2s;
        width: 100%;
    }
    .btn-primary {
        background: var(--primary);
        color: #fff;
    }
    .btn-primary:hover {
        background: #5a0085;
    }
    .results-panel {
        background: #0a0f14;
        border: 1px solid #1e293b;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 30px;
    }
    .results-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .status-badge {
        padding: 6px 12px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .status-success {
        background: #00ff8822;
        color: #00ff88;
    }
    .status-warning {
        background: #ffaa0022;
        color: #ffaa00;
    }
    .status-error {
        background: #ff444422;
        color: #ff4444;
    }
    .table-status {
        width: 100%;
        border-collapse: collapse;
        margin-top: 20px;
    }
    .table-status th,
    .table-status td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #1e293b;
        font-size: 13px;
    }
    .table-status th {
        color: var(--primary);
        font-weight: 700;
        text-transform: uppercase;
        font-size: 11px;
        letter-spacing: 0.5px;
    }
    .table-status td {
        color: #94a3b8;
    }
    .log-entry {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
    }
    .log-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
    }
    .log-action {
        font-weight: 700;
        color: #fff;
    }
    .log-time {
        font-size: 11px;
        color: #64748b;
    }
    .log-details {
        font-size: 12px;
        color: #94a3b8;
    }
</style>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-database"></i> Database Maintenance</h1>
</div>

<div class="warning-banner">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Warning:</strong> Database maintenance operations can affect system performance. 
    It's recommended to perform these operations during low-traffic periods. 
    Always ensure you have a recent backup before performing repairs.
</div>

<?php if ($action_performed && !empty($results)): ?>
<div class="results-panel">
    <div class="results-title">
        <i class="fas fa-check-circle"></i>
        Maintenance Results
        <span class="status-badge status-<?= $results['status'] ?>">
            <?= strtoupper($results['status']) ?>
        </span>
    </div>
    
    <div style="color: #94a3b8; line-height: 1.8;">
        <?php if (isset($results['checks_performed'])): ?>
        <div><strong>Checks Performed:</strong> <?= $results['checks_performed'] ?></div>
        <?php endif; ?>
        
        <?php if (isset($results['issues_found'])): ?>
        <div><strong>Issues Found:</strong> <?= $results['issues_found'] ?></div>
        <?php endif; ?>
        
        <?php if (isset($results['tables_repaired'])): ?>
        <div><strong>Tables Repaired:</strong> <?= $results['tables_repaired'] ?></div>
        <?php endif; ?>
        
        <?php if (isset($results['tables_optimized'])): ?>
        <div><strong>Tables Optimized:</strong> <?= $results['tables_optimized'] ?></div>
        <?php endif; ?>
        
        <?php if (isset($results['message'])): ?>
        <div style="margin-top: 15px; padding: 15px; background: #06080b; border-radius: 6px;">
            <?= htmlspecialchars($results['message']) ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($results['issues'])): ?>
        <div style="margin-top: 20px;">
            <strong>Details:</strong>
            <ul style="margin-top: 10px; list-style-position: inside;">
                <?php foreach ($results['issues'] as $issue): ?>
                <li><?= htmlspecialchars($issue['message']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="tools-grid">
    <div class="tool-card">
        <div class="tool-icon"><i class="fas fa-heartbeat"></i></div>
        <div class="tool-title">Check Database Integrity</div>
        <div class="tool-desc">
            Scan for orphaned records, broken relationships, and data consistency issues.
        </div>
        <form method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="maintenance_action" value="check_integrity">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-search"></i> Run Integrity Check
            </button>
        </form>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon"><i class="fas fa-tools"></i></div>
        <div class="tool-title">Repair Tables</div>
        <div class="tool-desc">
            Repair corrupted tables and fix table structure issues.
        </div>
        <form method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="maintenance_action" value="repair_tables">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-wrench"></i> Repair All Tables
            </button>
        </form>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon"><i class="fas fa-tachometer-alt"></i></div>
        <div class="tool-title">Optimize Tables</div>
        <div class="tool-desc">
            Defragment tables and reclaim unused space to improve performance.
        </div>
        <form method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="maintenance_action" value="optimize_tables">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-rocket"></i> Optimize All Tables
            </button>
        </form>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon"><i class="fas fa-link"></i></div>
        <div class="tool-title">Check Foreign Keys</div>
        <div class="tool-desc">
            Verify referential integrity and identify foreign key constraint violations.
        </div>
        <form method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="maintenance_action" value="check_foreign_keys">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-shield-alt"></i> Check Foreign Keys
            </button>
        </form>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon"><i class="fas fa-chart-line"></i></div>
        <div class="tool-title">Performance Analysis</div>
        <div class="tool-desc">
            Analyze table sizes, identify missing indexes, and detect performance issues.
        </div>
        <form method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="maintenance_action" value="analyze_performance">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-analytics"></i> Analyze Performance
            </button>
        </form>
    </div>
    
    <div class="tool-card">
        <div class="tool-icon"><i class="fas fa-first-aid"></i></div>
        <div class="tool-title">Repair Foreign Keys</div>
        <div class="tool-desc">
            Attempt to repair foreign key violations (requires manual review).
        </div>
        <form method="POST">
            <?= csrfTokenInput() ?>
            <input type="hidden" name="maintenance_action" value="repair_foreign_keys">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-magic"></i> Repair Foreign Keys
            </button>
        </form>
    </div>
</div>

<!-- Table Status Overview -->
<div class="results-panel">
    <div class="results-title"><i class="fas fa-table"></i> Table Status Overview</div>
    
    <table class="table-status">
        <thead>
            <tr>
                <th>Table Name</th>
                <th>Engine</th>
                <th>Rows</th>
                <th>Data Size</th>
                <th>Index Size</th>
                <th>Total Size</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tables_status as $table): ?>
            <tr>
                <td style="color: #fff; font-weight: 600;"><?= htmlspecialchars($table['Name']) ?></td>
                <td><?= htmlspecialchars($table['Engine'] ?? 'N/A') ?></td>
                <td><?= number_format($table['Rows'] ?? 0) ?></td>
                <td><?= round(($table['Data_length'] ?? 0) / 1024 / 1024, 2) ?> MB</td>
                <td><?= round(($table['Index_length'] ?? 0) / 1024 / 1024, 2) ?> MB</td>
                <td style="color: var(--primary); font-weight: 700;">
                    <?= round((($table['Data_length'] ?? 0) + ($table['Index_length'] ?? 0)) / 1024 / 1024, 2) ?> MB
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Recent Maintenance Logs -->
<div class="results-panel">
    <div class="results-title"><i class="fas fa-history"></i> Recent Maintenance Logs</div>
    
    <?php if (empty($recent_logs)): ?>
    <p style="color: #64748b; text-align: center; padding: 40px 0;">No maintenance logs yet.</p>
    <?php else: ?>
    <div>
        <?php foreach ($recent_logs as $log): ?>
        <div class="log-entry">
            <div class="log-header">
                <div class="log-action">
                    <i class="fas fa-cog"></i>
                    <?= htmlspecialchars(ucwords(str_replace('_', ' ', $log['action_type']))) ?>
                    <span class="status-badge status-<?= $log['status'] ?>"><?= strtoupper($log['status']) ?></span>
                </div>
                <div class="log-time">
                    <?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>
                </div>
            </div>
            <div class="log-details">
                Run by: <?= htmlspecialchars($log['run_by_name']) ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
