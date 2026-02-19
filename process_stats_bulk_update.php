<?php
// process_stats_bulk_update.php
session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

if (!isset($_SESSION['logged_in']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit('Unauthorized');
}

// Validate CSRF token for POST requests
checkCsrfToken();

$user_id = $_SESSION['user_id'];
$team_id = $_POST['team_id'];
$new_stats = $_POST['stats']; // Array from the form

try {
    // Build dynamic update query
    $sql = "UPDATE athlete_stats SET ";
    $params = [];
    foreach ($new_stats as $col => $val) {
        $sql .= "$col = ?, ";
        $params[] = $val;
    }
    
    // Auto-recalculate Points and Save % in the same flow
    $sql .= "points = ?, save_percentage = ? WHERE user_id = ? AND team_id = ?";
    
    // Calculate values
    $pts = ($new_stats['goals'] ?? 0) + ($new_stats['assists'] ?? 0);
    $sa = $new_stats['shots_against'] ?? 0;
    $ga = $new_stats['goals_against'] ?? 0;
    $sv_pct = ($sa > 0) ? ($sa - $ga) / $sa : 0;

    array_push($params, $pts, $sv_pct, $user_id, $team_id);

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    Auditor::log($pdo, $user_id, 'update', 'athlete_stats', null, ['action' => 'bulk_stats_updated', 'team_id' => $team_id]);

    header("Location: dashboard.php?page=stats&mode=view");
} catch (Exception $e) {
    die("Update failed: " . $e->getMessage());
}