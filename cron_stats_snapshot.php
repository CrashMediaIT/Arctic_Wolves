<?php
/**
 * Automated Performance Stats Snapshot Cron Job
 * Takes daily snapshots of athlete performance metrics for trend analysis
 * Example: 0 1 * * * /usr/bin/php /path/to/cron_stats_snapshot.php
 */

require_once __DIR__ . '/db_config.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY');
    
    if (empty($expected_key) || !hash_equals($expected_key, $secret_key)) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Stats Snapshot Cron: Starting...\n";

try {
    // Get all active athletes
    $stmt = $pdo->prepare("
        SELECT id, username FROM users 
        WHERE role IN ('athlete', 'parent') 
        AND status = 'active'
    ");
    $stmt->execute();
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($athletes)) {
        echo "No active athletes found.\n";
        exit(0);
    }
    
    echo "Found " . count($athletes) . " active athlete(s).\n";
    
    $snapshot_count = 0;
    $snapshot_date = date('Y-m-d');
    
    foreach ($athletes as $athlete) {
        // Check if snapshot already exists for today
        $stmt = $pdo->prepare("
            SELECT id FROM performance_stats 
            WHERE athlete_id = ? AND stat_date = ?
            LIMIT 1
        ");
        $stmt->execute([$athlete['id'], $snapshot_date]);
        
        if ($stmt->fetch()) {
            echo "Snapshot already exists for athlete {$athlete['username']}, skipping.\n";
            continue;
        }
        
        // Get latest stats for the athlete
        $stmt = $pdo->prepare("
            SELECT * FROM athlete_stats 
            WHERE athlete_id = ? 
            ORDER BY updated_at DESC 
            LIMIT 1
        ");
        $stmt->execute([$athlete['id']]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stats) {
            echo "No stats found for athlete {$athlete['username']}, skipping.\n";
            continue;
        }
        
        // Create snapshot
        $stmt = $pdo->prepare("
            INSERT INTO performance_stats (
                athlete_id, stat_date, stat_type, stat_value, 
                notes, created_at
            ) VALUES (?, ?, 'snapshot', ?, 'Daily automated snapshot', NOW())
        ");
        
        // Store key metrics as JSON
        $stat_value = json_encode([
            'goals_active' => $stats['goals_active'] ?? 0,
            'goals_completed' => $stats['goals_completed'] ?? 0,
            'sessions_attended' => $stats['sessions_attended'] ?? 0,
            'total_training_hours' => $stats['total_training_hours'] ?? 0
        ]);
        
        $stmt->execute([$athlete['id'], $snapshot_date, $stat_value]);
        $snapshot_count++;
        
        echo "✓ Created snapshot for athlete {$athlete['username']}\n";
    }
    
    echo "\n✓ Successfully created $snapshot_count snapshot(s).\n";
    echo "[" . date('Y-m-d H:i:s') . "] Stats Snapshot Cron: Completed successfully.\n";
    exit(0);
    
} catch (PDOException $e) {
    error_log("Stats Snapshot Cron Error: " . $e->getMessage());
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
