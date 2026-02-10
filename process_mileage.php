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
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $session_id = intval($_POST['session_id'] ?? 0);
            $purpose = trim($_POST['purpose']);
            
            // Handle both JSON waypoints and simple form submission
            if (isset($_POST['waypoints']) && !empty($_POST['waypoints'])) {
                $waypoints = json_decode($_POST['waypoints'], true);
            } else {
                // Create waypoints from simple form fields
                $from_location = trim($_POST['from_location'] ?? '');
                $to_location = trim($_POST['to_location'] ?? '');
                $waypoints = [
                    ['name' => 'Start', 'address' => $from_location],
                    ['name' => 'End', 'address' => $to_location]
                ];
            }
            
            // Try to calculate distance via Google Maps API first
            // Only fall back to manual distance if API fails (e.g., service is down)
            $distance_data = tryCalculateDistanceFromWaypoints($waypoints);
            
            if ($distance_data) {
                $distance_km = floatval($distance_data['distance_km']);
                $distance_miles = floatval($distance_data['distance_miles']);
            } else {
                // Fall back to manual distance only if API calculation failed
                $distance_miles = floatval($_POST['distance_miles'] ?? 0);
                $distance_km = floatval($_POST['distance_km'] ?? ($distance_miles * 1.60934));
                
                if ($distance_miles > 0 && floatval($_POST['distance_km'] ?? 0) == 0) {
                    $distance_km = $distance_miles * 1.60934;
                }
            }
            
            // Get mileage rate from settings
            $rate_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mileage_rate_per_km', 'mileage_rate_after_5000_per_km', 'mileage_rate_per_mile')");
            $rates = $rate_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $rate_per_km = floatval($rates['mileage_rate_per_km'] ?? 0.70);
            $rate_after_5000_per_km = floatval($rates['mileage_rate_after_5000_per_km'] ?? 0.64);
            $rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? 1.10);
            
            // Calculate year-to-date km for CRA tiered rate
            $year_km_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_distance_km), 0) FROM mileage_logs WHERE user_id = ? AND YEAR(trip_date) = YEAR(CURDATE())");
            $year_km_stmt->execute([$user_id]);
            $year_km_total = floatval($year_km_stmt->fetchColumn());
            
            // CRA tiered reimbursement calculation
            $remaining_first_5000 = max(0, 5000 - $year_km_total);
            $km_at_high_rate = min($distance_km, $remaining_first_5000);
            $km_at_low_rate = max(0, $distance_km - $km_at_high_rate);
            $reimbursement_amount = ($km_at_high_rate * $rate_per_km) + ($km_at_low_rate * $rate_after_5000_per_km);
            
            // Store the effective blended rate for this trip
            $effective_rate = $distance_km > 0 ? ($reimbursement_amount / $distance_km) : $rate_per_km;
            
            // Insert mileage log
            $stmt = $pdo->prepare("
                INSERT INTO mileage_logs (user_id, trip_date, title, description, athlete_id, session_id, purpose, 
                                         total_distance_km, total_distance_miles, reimbursement_rate, 
                                         reimbursement_amount, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $user_id, $trip_date, $title ?: null, $description ?: null,
                $athlete_id ?: null, $session_id ?: null, $purpose,
                $distance_km, $distance_miles, $effective_rate, $reimbursement_amount
            ]);
            
            $mileage_log_id = $pdo->lastInsertId();
            
            // Insert waypoints
            if ($waypoints && is_array($waypoints)) {
                $stop_stmt = $pdo->prepare("
                    INSERT INTO mileage_stops (mileage_log_id, stop_order, location_name, address)
                    VALUES (?, ?, ?, ?)
                ");
                
                foreach ($waypoints as $index => $waypoint) {
                    $stop_stmt->execute([
                        $mileage_log_id,
                        $index,
                        $waypoint['name'] ?? '',
                        $waypoint['address'] ?? ''
                    ]);
                }
            }
            
            // Check if this is an AJAX request or regular form submission
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                echo json_encode(['success' => true, 'message' => 'Mileage log created successfully', 'id' => $mileage_log_id]);
            } else {
                // Regular form submission - redirect back with success message
                header("Location: dashboard.php?page=mileage&status=success&message=Mileage+entry+added+successfully");
                exit();
            }
            break;
            
        case 'update':
            checkCsrfToken();
            
            $log_id = intval($_POST['log_id']);
            $trip_date = $_POST['trip_date'];
            $title = trim($_POST['title'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $athlete_id = intval($_POST['athlete_id'] ?? 0);
            $session_id = intval($_POST['session_id'] ?? 0);
            $purpose = trim($_POST['purpose']);
            $waypoints = json_decode($_POST['waypoints'], true);
            
            // Try to calculate distance via Google Maps API first
            $distance_data = tryCalculateDistanceFromWaypoints($waypoints);
            
            if ($distance_data) {
                $distance_km = floatval($distance_data['distance_km']);
                $distance_miles = floatval($distance_data['distance_miles']);
            } else {
                // Fall back to manual distance only if API calculation failed
                $distance_km = floatval($_POST['distance_km']);
                $distance_miles = floatval($_POST['distance_miles']);
            }
            
            // Get mileage rate from settings
            $rate_stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('mileage_rate_per_km', 'mileage_rate_after_5000_per_km', 'mileage_rate_per_mile')");
            $rates = $rate_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            $rate_per_km = floatval($rates['mileage_rate_per_km'] ?? 0.70);
            $rate_after_5000_per_km = floatval($rates['mileage_rate_after_5000_per_km'] ?? 0.64);
            $rate_per_mile = floatval($rates['mileage_rate_per_mile'] ?? 1.10);
            
            // Calculate year-to-date km for CRA tiered rate (exclude current entry being updated)
            $year_km_stmt = $pdo->prepare("SELECT COALESCE(SUM(total_distance_km), 0) FROM mileage_logs WHERE user_id = ? AND YEAR(trip_date) = YEAR(CURDATE()) AND id != ?");
            $year_km_stmt->execute([$user_id, $log_id]);
            $year_km_total = floatval($year_km_stmt->fetchColumn());
            
            // CRA tiered reimbursement calculation
            $remaining_first_5000 = max(0, 5000 - $year_km_total);
            $km_at_high_rate = min($distance_km, $remaining_first_5000);
            $km_at_low_rate = max(0, $distance_km - $km_at_high_rate);
            $reimbursement_amount = ($km_at_high_rate * $rate_per_km) + ($km_at_low_rate * $rate_after_5000_per_km);
            
            // Store the effective blended rate for this trip
            $effective_rate = $distance_km > 0 ? ($reimbursement_amount / $distance_km) : $rate_per_km;
            
            // Update mileage log
            $stmt = $pdo->prepare("
                UPDATE mileage_logs 
                SET trip_date = ?, title = ?, description = ?, athlete_id = ?, session_id = ?, purpose = ?,
                    total_distance_km = ?, total_distance_miles = ?, reimbursement_rate = ?,
                    reimbursement_amount = ?
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([
                $trip_date, $title ?: null, $description ?: null,
                $athlete_id ?: null, $session_id ?: null, $purpose,
                $distance_km, $distance_miles, $effective_rate,
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
            // Get filter parameters
            $period = $_GET['period'] ?? 'month';
            $search = trim($_GET['search'] ?? '');
            $athlete_filter = intval($_GET['athlete_id'] ?? 0);
            $session_filter = intval($_GET['session_id'] ?? 0);
            
            // Build date filter based on period
            $date_condition = "";
            switch ($period) {
                case 'week':
                    $date_condition = "ml.trip_date >= DATE_SUB(CURDATE(), INTERVAL 1 WEEK)";
                    break;
                case 'month':
                    $date_condition = "ml.trip_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'last_month':
                    $date_condition = "ml.trip_date >= DATE_SUB(CURDATE(), INTERVAL 2 MONTH) AND ml.trip_date < DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case '3months':
                    $date_condition = "ml.trip_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case '6months':
                    $date_condition = "ml.trip_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)";
                    break;
                case 'year':
                    $date_condition = "YEAR(ml.trip_date) = YEAR(CURDATE())";
                    break;
                case 'last_year':
                    $date_condition = "YEAR(ml.trip_date) = YEAR(CURDATE()) - 1";
                    break;
                default:
                    $date_condition = "1=1"; // All time
            }
            
            $params = [];
            $where_conditions = [$date_condition];
            
            if (!empty($search)) {
                $where_conditions[] = "(ml.title LIKE ? OR ml.purpose LIKE ? OR ml.description LIKE ?)";
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
                $params[] = '%' . $search . '%';
            }
            
            if ($athlete_filter > 0) {
                $where_conditions[] = "ml.athlete_id = ?";
                $params[] = $athlete_filter;
            }
            
            if ($session_filter > 0) {
                $where_conditions[] = "ml.session_id = ?";
                $params[] = $session_filter;
            }
            
            $where_clause = implode(' AND ', $where_conditions);
            
            $stmt = $pdo->prepare("
                SELECT ml.*, u.first_name, u.last_name,
                       a.first_name as athlete_first_name, a.last_name as athlete_last_name,
                       s.title as session_name
                FROM mileage_logs ml
                LEFT JOIN users u ON ml.user_id = u.id
                LEFT JOIN users a ON ml.athlete_id = a.id
                LEFT JOIN sessions s ON ml.session_id = s.id
                WHERE $where_clause
                ORDER BY ml.trip_date DESC
            ");
            $stmt->execute($params);
            $logs = $stmt->fetchAll();
            $logs = decryptUserRows($logs);
            // Build athlete_name from decrypted fields
            foreach ($logs as &$log) {
                $log['athlete_name'] = (!empty($log['athlete_first_name'])) ? $log['athlete_first_name'] . ' ' . $log['athlete_last_name'] : null;
            }
            unset($log);
            
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="mileage_logs_' . date('Y-m-d') . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, ['Date', 'Title', 'Coach', 'Athlete', 'Session', 'Purpose', 'Description', 'Distance (km)', 'Distance (mi)', 'Rate/km', 'Reimbursement', 'Reimbursed']);
            
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['trip_date'],
                    $log['title'] ?? '',
                    $log['first_name'] . ' ' . $log['last_name'],
                    $log['athlete_name'] ?: 'N/A',
                    $log['session_name'] ?: 'N/A',
                    $log['purpose'],
                    $log['description'] ?? '',
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
                       a.first_name as athlete_first_name, a.last_name as athlete_last_name,
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
            $logs = decryptUserRows($logs);
            // Build athlete_name from decrypted fields
            foreach ($logs as &$log) {
                $log['athlete_name'] = (!empty($log['athlete_first_name'])) ? $log['athlete_first_name'] . ' ' . $log['athlete_last_name'] : null;
            }
            unset($log);
            
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
 * Try to calculate distance via Google Maps API, return null on failure
 */
function tryCalculateDistanceFromWaypoints($waypoints) {
    if (!$waypoints || count($waypoints) < 2) {
        return null;
    }
    
    foreach ($waypoints as $wp) {
        if (empty(trim($wp['address'] ?? ''))) {
            return null;
        }
    }
    
    try {
        return calculateDistance($waypoints);
    } catch (Exception $e) {
        error_log('Google Maps distance calculation failed, using manual entry: ' . $e->getMessage());
        return null;
    }
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
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        $response = curl_exec($ch);
        $curl_error = curl_error($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            error_log('Google Maps Distance Matrix API curl error: ' . $curl_error);
            throw new Exception('Failed to connect to Google Maps API: ' . $curl_error);
        }
        
        $data = json_decode($response, true);
        
        if (!$data || ($data['status'] ?? '') !== 'OK') {
            error_log('Google Maps Distance Matrix API error: ' . ($response ?: 'empty response') . ' HTTP: ' . $http_code);
            throw new Exception('Google Maps API error: ' . ($data['error_message'] ?? $data['status'] ?? 'Unknown error'));
        }
        
        $element = $data['rows'][0]['elements'][0] ?? null;
        if (!$element || !isset($element['distance'])) {
            $elementStatus = $element['status'] ?? 'Unknown';
            throw new Exception('Could not calculate distance between stops ' . ($i + 1) . ' and ' . ($i + 2) . ': ' . $elementStatus);
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
