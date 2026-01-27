<?php
/**
 * Process Time Tracking Operations
 * Handles clock in/out, lunch breaks, shift management
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

// Set security headers
setSecurityHeaders();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Check if user is front desk staff or admin
$userRole = $_SESSION['user_role'] ?? '';
if (!in_array($userRole, ['admin', 'front_desk_staff'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate CSRF token
if (!isset($input['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $input['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token']);
    exit();
}

$action = $input['action'] ?? '';
$userId = intval($_SESSION['user_id']); // Ensure user ID is an integer

try {
    switch ($action) {
        case 'get_current_shift':
            // Get active shift for today
            $stmt = $pdo->prepare("
                SELECT * FROM staff_shifts 
                WHERE staff_id = ? AND shift_date = CURDATE() AND status = 'active'
            ");
            $stmt->execute([$userId]);
            $shift = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($shift) {
                // Calculate current duration
                $clockIn = new DateTime($shift['clock_in']);
                $now = new DateTime();
                $duration = $now->diff($clockIn);
                
                // Calculate lunch duration if applicable
                $lunchDuration = 0;
                if ($shift['lunch_start'] && $shift['lunch_end']) {
                    $lunchStart = new DateTime($shift['lunch_start']);
                    $lunchEnd = new DateTime($shift['lunch_end']);
                    $lunchDuration = ($lunchEnd->getTimestamp() - $lunchStart->getTimestamp()) / 60;
                }
                
                // Check if on lunch break
                $onLunch = $shift['lunch_start'] && !$shift['lunch_end'];
                
                echo json_encode([
                    'success' => true,
                    'shift' => [
                        'id' => $shift['id'],
                        'clock_in' => $shift['clock_in'],
                        'lunch_start' => $shift['lunch_start'],
                        'lunch_end' => $shift['lunch_end'],
                        'on_lunch' => $onLunch,
                        'duration_hours' => $duration->h + ($duration->days * 24),
                        'duration_minutes' => $duration->i,
                        'lunch_minutes' => (int)$lunchDuration
                    ]
                ]);
            } else {
                echo json_encode(['success' => true, 'shift' => null]);
            }
            break;
            
        case 'clock_in':
            // Check if already clocked in today
            $checkStmt = $pdo->prepare("
                SELECT id FROM staff_shifts 
                WHERE staff_id = ? AND shift_date = CURDATE() AND status = 'active'
            ");
            $checkStmt->execute([$userId]);
            
            if ($checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Already clocked in today']);
                break;
            }
            
            // Create new shift
            $stmt = $pdo->prepare("
                INSERT INTO staff_shifts (staff_id, shift_date, clock_in, status) 
                VALUES (?, CURDATE(), NOW(), 'active')
            ");
            $stmt->execute([$userId]);
            $shiftId = $pdo->lastInsertId();
            
            $_SESSION['shift_id'] = $shiftId;
            
            echo json_encode([
                'success' => true,
                'message' => 'Clocked in successfully',
                'shift_id' => $shiftId
            ]);
            break;
            
        case 'start_lunch':
            $shiftId = $input['shift_id'] ?? $_SESSION['shift_id'] ?? null;
            
            if (!$shiftId) {
                echo json_encode(['success' => false, 'message' => 'No active shift found']);
                break;
            }
            
            // Verify shift belongs to user and is active
            $checkStmt = $pdo->prepare("
                SELECT * FROM staff_shifts 
                WHERE id = ? AND staff_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$shiftId, $userId]);
            $shift = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shift) {
                echo json_encode(['success' => false, 'message' => 'Invalid shift']);
                break;
            }
            
            if ($shift['lunch_start']) {
                echo json_encode(['success' => false, 'message' => 'Lunch break already started']);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE staff_shifts SET lunch_start = NOW() WHERE id = ?
            ");
            $stmt->execute([$shiftId]);
            
            echo json_encode(['success' => true, 'message' => 'Lunch break started']);
            break;
            
        case 'end_lunch':
            $shiftId = $input['shift_id'] ?? $_SESSION['shift_id'] ?? null;
            
            if (!$shiftId) {
                echo json_encode(['success' => false, 'message' => 'No active shift found']);
                break;
            }
            
            // Verify shift belongs to user
            $checkStmt = $pdo->prepare("
                SELECT * FROM staff_shifts 
                WHERE id = ? AND staff_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$shiftId, $userId]);
            $shift = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shift || !$shift['lunch_start'] || $shift['lunch_end']) {
                echo json_encode(['success' => false, 'message' => 'Invalid lunch break state']);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE staff_shifts SET lunch_end = NOW() WHERE id = ?
            ");
            $stmt->execute([$shiftId]);
            
            echo json_encode(['success' => true, 'message' => 'Lunch break ended']);
            break;
            
        case 'end_shift':
            $shiftId = $input['shift_id'] ?? $_SESSION['shift_id'] ?? null;
            
            if (!$shiftId) {
                echo json_encode(['success' => false, 'message' => 'No active shift found']);
                break;
            }
            
            // Verify shift belongs to user
            $checkStmt = $pdo->prepare("
                SELECT * FROM staff_shifts 
                WHERE id = ? AND staff_id = ? AND status = 'active'
            ");
            $checkStmt->execute([$shiftId, $userId]);
            $shift = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$shift) {
                echo json_encode(['success' => false, 'message' => 'Invalid shift']);
                break;
            }
            
            // Calculate total hours
            $clockIn = new DateTime($shift['clock_in']);
            $clockOut = new DateTime();
            $totalMinutes = ($clockOut->getTimestamp() - $clockIn->getTimestamp()) / 60;
            
            // Subtract lunch break if applicable
            if ($shift['lunch_start'] && $shift['lunch_end']) {
                $lunchStart = new DateTime($shift['lunch_start']);
                $lunchEnd = new DateTime($shift['lunch_end']);
                $lunchMinutes = ($lunchEnd->getTimestamp() - $lunchStart->getTimestamp()) / 60;
                $totalMinutes -= $lunchMinutes;
            } elseif ($shift['lunch_start'] && !$shift['lunch_end']) {
                // End lunch break automatically
                $lunchStart = new DateTime($shift['lunch_start']);
                $lunchMinutes = ($clockOut->getTimestamp() - $lunchStart->getTimestamp()) / 60;
                $totalMinutes -= $lunchMinutes;
                
                $pdo->prepare("UPDATE staff_shifts SET lunch_end = NOW() WHERE id = ?")->execute([$shiftId]);
            }
            
            $totalHours = round($totalMinutes / 60, 2);
            
            $stmt = $pdo->prepare("
                UPDATE staff_shifts 
                SET clock_out = NOW(), total_hours = ?, status = 'completed' 
                WHERE id = ?
            ");
            $stmt->execute([$totalHours, $shiftId]);
            
            // Clear shift from session
            unset($_SESSION['shift_id']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Shift ended successfully',
                'total_hours' => $totalHours
            ]);
            break;
            
        case 'get_shift_history':
            $filter = $input['filter'] ?? 'week';
            $staffId = $input['staff_id'] ?? $userId;
            
            // Only admins can view other staff's history
            if ($staffId != $userId && $userRole !== 'admin') {
                $staffId = $userId;
            }
            
            switch ($filter) {
                case 'week':
                    $dateCondition = "AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $dateCondition = "AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'quarter':
                    $dateCondition = "AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)";
                    break;
                case 'year':
                    $dateCondition = "AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)";
                    break;
                default:
                    $dateCondition = "AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
            }
            
            $stmt = $pdo->prepare("
                SELECT * FROM staff_shifts 
                WHERE staff_id = ? $dateCondition
                ORDER BY shift_date DESC, clock_in DESC
            ");
            $stmt->execute([$staffId]);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate totals
            $totalHours = 0;
            foreach ($shifts as $shift) {
                if ($shift['total_hours']) {
                    $totalHours += floatval($shift['total_hours']);
                }
            }
            
            echo json_encode([
                'success' => true,
                'shifts' => $shifts,
                'total_hours' => round($totalHours, 2),
                'shift_count' => count($shifts)
            ]);
            break;
            
        case 'get_schedule':
            $filter = $input['filter'] ?? 'month';
            $view = $input['view'] ?? 'list';
            $staffId = $input['staff_id'] ?? $userId;
            
            // Only admins can view other staff's schedules
            if ($staffId != $userId && $userRole !== 'admin') {
                $staffId = $userId;
            }
            
            switch ($filter) {
                case 'day':
                    $dateCondition = "AND schedule_date = CURDATE()";
                    break;
                case 'week':
                    $dateCondition = "AND schedule_date >= CURDATE() AND schedule_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $dateCondition = "AND schedule_date >= CURDATE() AND schedule_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
                    break;
                case 'calendar':
                    $dateCondition = "AND schedule_date >= CURDATE() AND schedule_date <= DATE_ADD(CURDATE(), INTERVAL 4 WEEK)";
                    break;
                default:
                    $dateCondition = "AND schedule_date >= CURDATE() AND schedule_date <= DATE_ADD(CURDATE(), INTERVAL 1 MONTH)";
            }
            
            $stmt = $pdo->prepare("
                SELECT ss.*, u.first_name, u.last_name
                FROM staff_schedules ss
                LEFT JOIN users u ON ss.created_by = u.id
                WHERE ss.staff_id = ? $dateCondition
                ORDER BY ss.schedule_date ASC, ss.start_time ASC
            ");
            $stmt->execute([$staffId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'schedules' => $schedules,
                'total_shifts' => count($schedules)
            ]);
            break;
            
        case 'get_hours_summary':
            $staffId = $input['staff_id'] ?? $userId;
            
            // Only admins can view other staff's summaries
            if ($staffId != $userId && $userRole !== 'admin') {
                $staffId = $userId;
            }
            
            // Get hours for different periods
            $periods = [
                'week' => 'DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
                'month' => 'DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
                'quarter' => 'DATE_SUB(CURDATE(), INTERVAL 3 MONTH)',
                'year' => 'DATE_SUB(CURDATE(), INTERVAL 1 YEAR)'
            ];
            
            $summary = [];
            foreach ($periods as $period => $dateExpr) {
                $stmt = $pdo->prepare("
                    SELECT COALESCE(SUM(total_hours), 0) as total_hours, COUNT(*) as shift_count
                    FROM staff_shifts 
                    WHERE staff_id = ? AND shift_date >= $dateExpr AND status = 'completed'
                ");
                $stmt->execute([$staffId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $summary[$period] = [
                    'total_hours' => round(floatval($result['total_hours']), 2),
                    'shift_count' => intval($result['shift_count'])
                ];
            }
            
            // Get weekly breakdown for chart (last 12 weeks)
            $stmt = $pdo->prepare("
                SELECT 
                    YEARWEEK(shift_date, 1) as week_number,
                    MIN(shift_date) as week_start,
                    COALESCE(SUM(total_hours), 0) as total_hours
                FROM staff_shifts 
                WHERE staff_id = ? 
                AND shift_date >= DATE_SUB(CURDATE(), INTERVAL 12 WEEK)
                AND status = 'completed'
                GROUP BY YEARWEEK(shift_date, 1)
                ORDER BY week_number ASC
            ");
            $stmt->execute([$staffId]);
            $weeklyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'summary' => $summary,
                'weekly_data' => $weeklyData
            ]);
            break;
            
        // Admin functions for managing schedules
        case 'create_schedule':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $staffId = $input['staff_id'] ?? null;
            $scheduleDate = $input['schedule_date'] ?? null;
            $startTime = $input['start_time'] ?? null;
            $endTime = $input['end_time'] ?? null;
            $location = $input['location'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$staffId || !$scheduleDate || !$startTime || !$endTime) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO staff_schedules (staff_id, schedule_date, start_time, end_time, location, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$staffId, $scheduleDate, $startTime, $endTime, $location, $notes, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Schedule created successfully',
                'schedule_id' => $pdo->lastInsertId()
            ]);
            break;
            
        case 'update_schedule':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $scheduleId = $input['schedule_id'] ?? null;
            $startTime = $input['start_time'] ?? null;
            $endTime = $input['end_time'] ?? null;
            $location = $input['location'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$scheduleId) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE staff_schedules 
                SET start_time = COALESCE(?, start_time),
                    end_time = COALESCE(?, end_time),
                    location = ?,
                    notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$startTime, $endTime, $location, $notes, $scheduleId]);
            
            echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
            break;
            
        case 'delete_schedule':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $scheduleId = $input['schedule_id'] ?? null;
            
            if (!$scheduleId) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
                break;
            }
            
            $stmt = $pdo->prepare("DELETE FROM staff_schedules WHERE id = ?");
            $stmt->execute([$scheduleId]);
            
            echo json_encode(['success' => true, 'message' => 'Schedule deleted successfully']);
            break;
            
        case 'set_staff_pin':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $staffId = $input['staff_id'] ?? null;
            $pin = $input['pin'] ?? null;
            
            if (!$staffId || !$pin || strlen($pin) !== 4 || !ctype_digit($pin)) {
                echo json_encode(['success' => false, 'message' => 'Valid 4-digit PIN required']);
                break;
            }
            
            // Verify user is front desk staff
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'front_desk_staff'");
            $checkStmt->execute([$staffId]);
            if (!$checkStmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Invalid staff member']);
                break;
            }
            
            $pinHash = password_hash($pin, PASSWORD_DEFAULT);
            
            // Insert or update PIN - use new_values alias for MySQL 8.0.20+ compatibility
            $stmt = $pdo->prepare("
                INSERT INTO staff_pins (user_id, pin_hash, is_active) 
                VALUES (?, ?, 1) AS new_values
                ON DUPLICATE KEY UPDATE pin_hash = new_values.pin_hash, is_active = 1
            ");
            $stmt->execute([$staffId, $pinHash]);
            
            echo json_encode(['success' => true, 'message' => 'PIN set successfully']);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    error_log("Time tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Time tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
