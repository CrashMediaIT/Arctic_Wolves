<?php
/**
 * Process Get Athlete Dashboard - Returns athlete dashboard data for parent view
 * Used by the parent's home page to show child athlete sessions and progress.
 */

require_once __DIR__ . '/config/session.php';
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

header('Content-Type: application/json');

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$athlete_id = isset($_GET['athlete_id']) ? intval($_GET['athlete_id']) : 0;

if ($athlete_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
    exit;
}

// Verify parent has access to this athlete
$has_access = false;
try {
    $stmt = $pdo->prepare("
        SELECT 1 FROM parent_athlete_relationships 
        WHERE parent_id = ? AND athlete_id = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $athlete_id]);
    $has_access = $stmt->rowCount() > 0;

    if (!$has_access) {
        $stmt2 = $pdo->prepare("
            SELECT 1 FROM managed_athletes 
            WHERE parent_id = ? AND athlete_id = ?
            LIMIT 1
        ");
        $stmt2->execute([$user_id, $athlete_id]);
        $has_access = $stmt2->rowCount() > 0;
    }
} catch (PDOException $e) {
    error_log("Get athlete dashboard permission error: " . $e->getMessage());
}

// Allow admins and coaches too
if (!$has_access) {
    $user_role = $_SESSION['user_role'] ?? '';
    if (in_array($user_role, ['admin', 'coach', 'coach_plus'])) {
        $has_access = true;
    }
}

if (!$has_access) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

// Fetch upcoming sessions
$sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title as type, 
               DATE_FORMAT(s.session_date, '%b %d, %Y') as date,
               COALESCE(TIME_FORMAT(s.session_time, '%h:%i %p'), 'TBD') as time
        FROM sessions s
        INNER JOIN bookings b ON b.session_id = s.id AND b.user_id = ? AND b.status != 'cancelled'
        WHERE s.session_date >= CURDATE()
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 5
    ");
    $stmt->execute([$athlete_id]);
    $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Get athlete sessions error: " . $e->getMessage());
}

// Fetch basic stats
$stats = ['sessions_attended' => 0, 'goals_completed' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM bookings 
        WHERE user_id = ? AND status = 'confirmed'
    ");
    $stmt->execute([$athlete_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['sessions_attended'] = (int)($row['cnt'] ?? 0);
} catch (PDOException $e) {
    error_log("Get athlete session count error: " . $e->getMessage());
}

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as cnt FROM goals 
        WHERE athlete_id = ? AND status = 'completed'
    ");
    $stmt->execute([$athlete_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $stats['goals_completed'] = (int)($row['cnt'] ?? 0);
} catch (PDOException $e) {
    error_log("Get athlete goals count error: " . $e->getMessage());
}

echo json_encode([
    'success' => true,
    'sessions' => $sessions,
    'stats' => $stats
]);
