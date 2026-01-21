<?php
// process_mileage.php - Handle mileage tracking operations
session_start();
require 'db_config.php';
require 'security.php';

setSecurityHeaders();

$user_role = $_SESSION['user_role'] ?? '';
if (!in_array($user_role, ['admin', 'coach', 'coach_plus'])) {
    http_response_code(403);
    die(json_encode(['success' => false, 'message' => 'Access denied']));
}

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user_id = $_SESSION['user_id'];

try {
    switch ($action) {
        case 'get_distance':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Invalid request method');
            }
            
            checkCsrfToken();
            
            $waypoints = json_decode($_POST['waypoints'], true);
            if (!$waypoints || count($waypoints) < 2) {
                throw new Exception('At least 2 locations required');
            }
            
            $distance_data = calculateDistance($waypoints);
            echo json_encode(['success' => true, 'data' => $distance_data]);
            break;
            
        case 'create':
            checkCsrfToken();
            
            $trip_date = $_POST['trip_date'];
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $session_id = intval($_POST['session_id'] ?? 0);
            $purpose = trim($_POST['purpose']);
            $waypoints = json_decode($_POST['waypoints'], true);
            $distance_km = floatval($_POST['distance_km']);
            $distance_miles = floatval($_POST['distance_miles']);
            
            // Get mileage rate from settings
            $rate_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key IN ('mileage_rate_per_km', 'mileage_rate_per_mile')");
            $rates = $rate_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $rate_per_km = floatval($rates['mileage_rate_per_km'] ?? 0.68);
            $rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? 1.10);
            
            $reimbursement_amount = $distance_km * $rate_per_km;
            
            // Insert mileage log
            $stmt = $pdo->prepare("
                INSERT INTO mileage_logs (user_id, trip_date, athlete_id, session_id, purpose, 
                                         total_distance_km, total_distance_miles, reimbursement_rate, 
                                         reimbursement_amount, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id, $trip_date, $athlete_id ?: null, $session_id ?: null, $purpose,
                $distance_km, $distance_miles, $rate_per_km, $reimbursement_amount
            ]);
            
            $mileage_log_id = $pdo->lastInsertId();
            
            // Insert waypoints
            $stop_stmt = $pdo->prepare("
                INSERT INTO mileage_stops (mileage_log_id, stop_order, location_name, address)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($waypoints as $index => $waypoint) {
                $stop_stmt->execute([
                    $mileage_log_id,
                    $index,
                    $waypoint['name'] ?? '',
                    $waypoint['address']
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Mileage log created successfully', 'id' => $mileage_log_id]);
            break;
            
        case 'update':
            checkCsrfToken();
            
            $log_id = intval($_POST['log_id']);
            $trip_date = $_POST['trip_date'];
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $session_id = intval($_POST['session_id'] ?? 0);
            $purpose = trim($_POST['purpose']);
            $waypoints = json_decode($_POST['waypoints'], true);
            $distance_km = floatval($_POST['distance_km']);
            $distance_miles = floatval($_POST['distance_miles']);
            
            // Get mileage rate from settings
            $rate_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key IN ('mileage_rate_per_km', 'mileage_rate_per_mile')");
            $rates = $rate_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $rate_per_km = floatval($rates['mileage_rate_per_km'] ?? 0.68);
            $rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? 1.10);
            
            $reimbursement_amount = $distance_km * $rate_per_km;
            
            // Update mileage log
            $stmt = $pdo->prepare("
                UPDATE mileage_logs 
                SET trip_date = ?, athlete_id = ?, session_id = ?, purpose = ?,
                    total_distance_km = ?, total_distance_miles = ?, reimbursement_rate = ?,
                    reimbursement_amount = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $trip_date, $athlete_id ?: null, $session_id ?: null, $purpose,
                $distance_km, $distance_miles, $rate_per_km,
                $reimbursement_amount, $log_id, $user_id
            ]);
            
            // Delete old stops and insert new ones
            $pdo->prepare("DELETE FROM mileage_stops WHERE mileage_log_id = ?")->execute([$log_id]);
            
            $stop_stmt = $pdo->prepare("
                INSERT INTO mileage_stops (mileage_log_id, stop_order, location_name, address)
                VALUES (?, ?, ?, ?)
            ");
            
            foreach ($waypoints as $index => $waypoint) {
                $stop_stmt->execute([
                    $log_id,
                    $index,
                    $waypoint['name'] ?? '',
                    $waypoint['address']
                ]);
            }
            
            echo json_encode(['success' => true, 'message' => 'Mileage log updated successfully']);
            break;
            
        case 'delete':
            checkCsrfToken();
            
            $log_id = intval($_POST['log_id']);
            
            $stmt = $pdo->prepare("DELETE FROM mileage_logs WHERE id = ? AND user_id = ?");
            $stmt->execute([$log_id, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Mileage log deleted successfully']);
            break;
            
        case 'mark_reimbursed':
            checkCsrfToken();
            
            if ($user_role !== 'admin') {
                throw new Exception('Only admins can mark as reimbursed');
            }
            
            $log_id = intval($_POST['log_id']);
            
            $stmt = $pdo->prepare("UPDATE mileage_logs SET is_reimbursed = 1 WHERE id = ?");
            $stmt->execute([$log_id]);
            
            echo json_encode(['success' => true, 'message' => 'Marked as reimbursed']);
            break;
            
        case 'export_csv':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            
            $stmt = $pdo->prepare("
                SELECT ml.*, u.first_name, u.last_name,
                       CONCAT(a.first_name, ' ', a.last_name) as athlete_name
                FROM mileage_logs ml
                LEFT JOIN users u ON ml.user_id = u.id
                LEFT JOIN users a ON ml.athlete_id = a.id
                WHERE ml.trip_date BETWEEN ? AND ?
                ORDER BY ml.trip_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $logs = $stmt->fetchAll();
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="mileage_logs_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Coach', 'Athlete', 'Purpose', 'Distance (km)', 'Distance (mi)', 'Rate/km', 'Reimbursement', 'Reimbursed']);
            
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['trip_date'],
                    $log['first_name'] . ' ' . $log['last_name'],
                    $log['athlete_name'] ?: 'N/A',
                    $log['purpose'],
                    number_format($log['total_distance_km'], 2),
                    number_format($log['total_distance_miles'], 2),
                    '$' . number_format($log['reimbursement_rate'], 2),
                    '$' . number_format($log['reimbursement_amount'], 2),
                    $log['is_reimbursed'] ? 'Yes' : 'No'
                ]);
            }
            
            fclose($output);
            exit;
            
        case 'get_logs':
            $start_date = $_GET['start_date'] ?? date('Y-m-01');
            $end_date = $_GET['end_date'] ?? date('Y-m-t');
            
            $stmt = $pdo->prepare("
                SELECT ml.*, u.first_name, u.last_name,
                       CONCAT(a.first_name, ' ', a.last_name) as athlete_name,
                       GROUP_CONCAT(ms.address ORDER BY ms.stop_order SEPARATOR ' → ') as route
                FROM mileage_logs ml
                LEFT JOIN users u ON ml.user_id = u.id
                LEFT JOIN users a ON ml.athlete_id = a.id
                LEFT JOIN mileage_stops ms ON ml.id = ms.mileage_log_id
                WHERE ml.trip_date BETWEEN ? AND ?
                GROUP BY ml.id
                ORDER BY ml.trip_date DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $logs = $stmt->fetchAll();
            
            echo json_encode(['success' => true, 'logs' => $logs]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

/**
 * Calculate distance using Google Maps Distance Matrix API
 */
function calculateDistance($waypoints) {
    global $pdo;
    
    // Get API key
    $api_key_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'google_maps_api_key'");
    $api_key = $api_key_stmt->fetchColumn();
    
    if (empty($api_key)) {
        throw new Exception('Google Maps API key not configured');
    }
    
    $total_km = 0;
    $total_miles = 0;
    
    // Calculate distance between each consecutive pair of waypoints
    for ($i = 0; $i < count($waypoints) - 1; $i++) {
        $origin = urlencode($waypoints[$i]['address']);
        $destination = urlencode($waypoints[$i + 1]['address']);
        
        $url = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$origin&destinations=$destination&key=$api_key";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if ($data['status'] !== 'OK' || !isset($data['rows'][0]['elements'][0]['distance'])) {
            throw new Exception('Google Maps API error: ' . ($data['error_message'] ?? 'Unknown error'));
        }
        
        $distance_meters = $data['rows'][0]['elements'][0]['distance']['value'];
        $total_km += $distance_meters / 1000;
    }
    
    $total_miles = $total_km * 0.621371;
    
    return [
        'distance_km' => round($total_km, 2),
        'distance_miles' => round($total_miles, 2)
    ];
}
?>
