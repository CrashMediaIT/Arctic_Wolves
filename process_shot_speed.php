<?php
/**
 * Process Shot Speed Recording
 * Handles saving/loading shot speed measurements for athletes
 */
session_start();
require 'db_config.php';
require 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

setSecurityHeaders();

// Security check - only coaches and admins can record shot speeds
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['coach', 'admin'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? 0;

// All actions return JSON
header('Content-Type: application/json');

try {
    checkCsrfToken();
    
    switch ($action) {
        case 'record_speed':
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $speed = floatval($_POST['speed'] ?? 0);
            $unit = in_array($_POST['unit'] ?? '', ['mph', 'km/h']) ? $_POST['unit'] : 'mph';
            $notes = trim($_POST['notes'] ?? '');
            
            // Validation
            if ($athlete_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
                exit;
            }
            
            // Validate speed range (0-150 mph or 0-240 km/h)
            $max_speed = ($unit === 'mph') ? 150 : 240;
            if ($speed <= 0 || $speed > $max_speed) {
                echo json_encode(['success' => false, 'message' => "Speed must be between 1 and $max_speed $unit"]);
                exit;
            }
            
            // Verify athlete exists (any active user can have shot speed recorded)
            $athlete_check = $pdo->prepare("
                SELECT u.id 
                FROM users u
                WHERE u.id = ? AND u.is_active = 1
            ");
            $athlete_check->execute([$athlete_id]);
            if (!$athlete_check->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Athlete not found']);
                exit;
            }
            
            // Insert shot speed record
            $stmt = $pdo->prepare("
                INSERT INTO performance_stats 
                (athlete_id, stat_date, stat_type, stat_value, stat_unit, recorded_by, notes, created_at)
                VALUES (?, CURDATE(), 'shot_speed', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$athlete_id, $speed, $unit, $user_id, $notes]);
            
            // Log the action
            Auditor::log($pdo, $user_id, 'create', 'performance_stats', $pdo->lastInsertId(), [
                'athlete_id' => $athlete_id,
                'stat_type' => 'shot_speed',
                'stat_value' => $speed,
                'stat_unit' => $unit
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Shot speed recorded successfully']);
            exit;
            
        case 'get_recent_speeds':
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $limit = intval($_POST['limit'] ?? 20);
            
            if ($athlete_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
                exit;
            }
            
            // Get recent shot speeds for this athlete
            $stmt = $pdo->prepare("
                SELECT 
                    ps.id,
                    ps.stat_value as speed,
                    ps.stat_unit as unit,
                    ps.stat_date,
                    ps.notes,
                    ps.created_at,
                    u.first_name,
                    u.last_name
                FROM performance_stats ps
                LEFT JOIN users u ON ps.recorded_by = u.id
                WHERE ps.athlete_id = ? AND ps.stat_type = 'shot_speed'
                ORDER BY ps.created_at DESC
                LIMIT ?
            ");
            $stmt->execute([$athlete_id, $limit]);
            $speeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $speeds = decryptUserRows($speeds);
            
            echo json_encode(['success' => true, 'speeds' => $speeds]);
            exit;
            
        case 'get_stats':
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            
            if ($athlete_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid athlete ID']);
                exit;
            }
            
            // Get max and average speeds
            $stmt = $pdo->prepare("
                SELECT 
                    MAX(stat_value) as max_speed,
                    AVG(stat_value) as avg_speed,
                    COUNT(*) as total_measurements,
                    stat_unit as unit
                FROM performance_stats
                WHERE athlete_id = ? AND stat_type = 'shot_speed'
                GROUP BY stat_unit
            ");
            $stmt->execute([$athlete_id]);
            $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
            
        case 'delete_speed':
            $speed_id = intval($_POST['speed_id'] ?? 0);
            
            if ($speed_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid speed ID']);
                exit;
            }
            
            // Delete the record
            $stmt = $pdo->prepare("DELETE FROM performance_stats WHERE id = ? AND stat_type = 'shot_speed'");
            $stmt->execute([$speed_id]);
            
            // Log the action
            Auditor::log($pdo, $user_id, 'delete', 'performance_stats', $speed_id, [
                'stat_type' => 'shot_speed'
            ]);
            
            echo json_encode(['success' => true, 'message' => 'Shot speed deleted']);
            exit;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            exit;
    }
    
} catch (PDOException $e) {
    ErrorLogger::error("Shot speed error", ["error" => $e->getMessage()]);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    exit;
}
?>
