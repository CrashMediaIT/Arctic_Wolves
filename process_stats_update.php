<?php
session_start();
require 'db_config.php';

// 1. Security Check
if (!isset($_SESSION['logged_in'])) { 
    header("Location: login.php"); 
    exit(); 
}

// 2. Determine Target User (Self vs. Coach Override)
$target_user_id = $_SESSION['user_id']; // Default: Update my own stats

// If Coach/Admin creates the request, they can override the target ID
if (isset($_POST['target_user_id']) && ($_SESSION['user_role'] == 'admin' || $_SESSION['user_role'] == 'coach')) {
    $target_user_id = $_POST['target_user_id'];
}

$team_id = $_POST['team_id'];
$col     = $_POST['column'];

// 3. Whitelist Allowed Columns (Security)
$allowed = [
    'games_played', 'goals', 'assists', 'penalty_minutes', 'plus_minus', 
    'wins', 'losses', 'ties', 'games_started', 'shots_against', 'goals_against'
];

if (in_array($col, $allowed)) {
    try {
        // A. Increment the specific stat
        $sql = "UPDATE athlete_stats SET $col = $col + 1 WHERE user_id = ? AND team_id = ?";
        $pdo->prepare($sql)->execute([$target_user_id, $team_id]);

        // B. Auto-Calculate Derived Stats
        
        // Recalculate Points (Goals + Assists)
        $pdo->prepare("UPDATE athlete_stats SET points = goals + assists WHERE user_id = ? AND team_id = ?")
            ->execute([$target_user_id, $team_id]);

        // Recalculate Save % ( (Shots - Goals) / Shots )
        $sql_sv = "UPDATE athlete_stats 
                   SET save_percentage = 
                   CASE 
                       WHEN shots_against > 0 THEN (shots_against - goals_against) / shots_against
                       ELSE 0.000 
                   END
                   WHERE user_id = ? AND team_id = ?";
        $pdo->prepare($sql_sv)->execute([$target_user_id, $team_id]);

        // 4. Redirect Back correctly
        // If it was a coach, go back to the detail view. If athlete, go to their stats page.
        if (isset($_POST['target_user_id'])) {
            header("Location: dashboard.php?page=athlete_detail&id=" . $target_user_id);
        } else {
            header("Location: dashboard.php?page=stats");
        }
        exit();

    } catch (PDOException $e) {
        die("Error updating stats: " . $e->getMessage());
    }
} else {
    die("Invalid stat column.");
}
?>