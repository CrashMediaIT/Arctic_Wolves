<?php
/**
 * Process Time Tracking Operations
 * Handles clock in/out, lunch breaks, shift management
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
            
            Auditor::log($pdo, $userId, 'create', 'staff_shifts', $shiftId, ['action' => 'Clock in']);
            
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
            
            Auditor::log($pdo, $userId, 'update', 'staff_shifts', $shiftId, ['action' => 'Lunch break started']);
            
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
            
            Auditor::log($pdo, $userId, 'update', 'staff_shifts', $shiftId, ['action' => 'Lunch break ended']);
            
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
            
            Auditor::log($pdo, $userId, 'update', 'staff_shifts', $shiftId, ['action' => 'Shift ended', 'total_hours' => $totalHours]);
            
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
            if (function_exists('decryptUserRows')) {
                $schedules = decryptUserRows($schedules);
            }
            
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
            $lunchBreakMinutes = intval($input['lunch_break_minutes'] ?? 30);
            $location = $input['location'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$staffId || !$scheduleDate || !$startTime || !$endTime) {
                echo json_encode(['success' => false, 'message' => 'Missing required fields']);
                break;
            }
            
            // Validate lunch break minutes is within reasonable bounds
            if ($lunchBreakMinutes < 0 || $lunchBreakMinutes > 120) {
                echo json_encode(['success' => false, 'message' => 'Lunch break must be between 0 and 120 minutes']);
                break;
            }
            
            $stmt = $pdo->prepare("
                INSERT INTO staff_schedules (staff_id, schedule_date, start_time, end_time, lunch_break_minutes, location, notes, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$staffId, $scheduleDate, $startTime, $endTime, $lunchBreakMinutes, $location, $notes, $userId]);
            
            Auditor::log($pdo, $userId, 'create', 'staff_schedules', $pdo->lastInsertId(), ['action' => 'Schedule created']);
            
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
            $staffId = $input['staff_id'] ?? null;
            $scheduleDate = $input['schedule_date'] ?? null;
            $startTime = $input['start_time'] ?? null;
            $endTime = $input['end_time'] ?? null;
            $lunchBreakMinutes = isset($input['lunch_break_minutes']) ? intval($input['lunch_break_minutes']) : null;
            $location = $input['location'] ?? null;
            $notes = $input['notes'] ?? null;
            
            if (!$scheduleId) {
                echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
                break;
            }
            
            // Validate lunch break minutes if provided
            if ($lunchBreakMinutes !== null && ($lunchBreakMinutes < 0 || $lunchBreakMinutes > 120)) {
                echo json_encode(['success' => false, 'message' => 'Lunch break must be between 0 and 120 minutes']);
                break;
            }
            
            $stmt = $pdo->prepare("
                UPDATE staff_schedules 
                SET staff_id = COALESCE(?, staff_id),
                    schedule_date = COALESCE(?, schedule_date),
                    start_time = COALESCE(?, start_time),
                    end_time = COALESCE(?, end_time),
                    lunch_break_minutes = COALESCE(?, lunch_break_minutes),
                    location = ?,
                    notes = ?
                WHERE id = ?
            ");
            $stmt->execute([$staffId, $scheduleDate, $startTime, $endTime, $lunchBreakMinutes, $location, $notes, $scheduleId]);
            
            Auditor::log($pdo, $userId, 'update', 'staff_schedules', $scheduleId, ['action' => 'Schedule updated']);
            
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
            
            Auditor::log($pdo, $userId, 'delete', 'staff_schedules', $scheduleId, ['action' => 'Schedule deleted']);
            
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
            
            // Insert or update PIN (compatible with older MySQL versions)
            $stmt = $pdo->prepare("
                INSERT INTO staff_pins (user_id, pin_hash, is_active) 
                VALUES (?, ?, 1)
                ON DUPLICATE KEY UPDATE pin_hash = ?, is_active = 1
            ");
            $stmt->execute([$staffId, $pinHash, $pinHash]);
            
            Auditor::log($pdo, $userId, 'update', 'staff_pins', null, ['action' => 'Staff PIN set', 'staff_id' => $staffId]);
            
            echo json_encode(['success' => true, 'message' => 'PIN set successfully']);
            break;
        
        // Time tracking reports and payroll integration
        case 'calculate_payroll_hours':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;
            $staffId = $input['staff_id'] ?? 'all';
            
            if (!$startDate || !$endDate) {
                echo json_encode(['success' => false, 'message' => 'Date range required']);
                break;
            }
            
            // Build query based on staff selection
            $staffCondition = $staffId === 'all' ? '' : 'AND ss.staff_id = :staff_id';
            
            $query = "
                SELECT 
                    u.id as staff_id,
                    u.first_name, u.last_name,
                    COUNT(ss.id) as shifts,
                    COALESCE(SUM(ss.total_hours), 0) as hours
                FROM users u
                LEFT JOIN staff_shifts ss ON u.id = ss.staff_id 
                    AND ss.shift_date BETWEEN :start_date AND :end_date
                    AND ss.status = 'completed'
                WHERE u.role = 'front_desk_staff' AND u.is_active = 1
                $staffCondition
                GROUP BY u.id, u.first_name, u.last_name
                ORDER BY u.last_name, u.first_name
            ";
            
            $stmt = $pdo->prepare($query);
            $params = [':start_date' => $startDate, ':end_date' => $endDate];
            if ($staffId !== 'all') {
                $params[':staff_id'] = $staffId;
            }
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $results = decryptUserRows($results);
            // Build name from decrypted fields
            foreach ($results as &$r) {
                $r['name'] = $r['first_name'] . ' ' . $r['last_name'];
            }
            unset($r);
            
            $totalHours = 0;
            $staffBreakdown = [];
            foreach ($results as $row) {
                $hours = floatval($row['hours']);
                $totalHours += $hours;
                $staffBreakdown[] = [
                    'staff_id' => $row['staff_id'],
                    'name' => $row['name'],
                    'shifts' => intval($row['shifts']),
                    'hours' => $hours
                ];
            }
            
            echo json_encode([
                'success' => true,
                'total_hours' => $totalHours,
                'staff_breakdown' => $staffBreakdown,
                'period' => ['start' => $startDate, 'end' => $endDate]
            ]);
            break;
            
        case 'sync_to_payroll':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $startDate = $input['start_date'] ?? null;
            $endDate = $input['end_date'] ?? null;
            $staffId = $input['staff_id'] ?? 'all';
            
            if (!$startDate || !$endDate) {
                echo json_encode(['success' => false, 'message' => 'Date range required']);
                break;
            }
            
            // Get hours for each hourly front desk staff member
            $staffCondition = $staffId === 'all' ? '' : 'AND u.id = :staff_id';
            
            $query = "
                SELECT 
                    u.id as user_id,
                    COALESCE(SUM(ss.total_hours), 0) as total_hours
                FROM users u
                LEFT JOIN staff_shifts ss ON u.id = ss.staff_id 
                    AND ss.shift_date BETWEEN :start_date AND :end_date
                    AND ss.status = 'completed'
                WHERE u.role = 'front_desk_staff' AND u.is_active = 1
                $staffCondition
                GROUP BY u.id
            ";
            
            $stmt = $pdo->prepare($query);
            $params = [':start_date' => $startDate, ':end_date' => $endDate];
            if ($staffId !== 'all') {
                $params[':staff_id'] = $staffId;
            }
            $stmt->execute($params);
            $hoursData = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Log the sync for audit purposes
            $auditStmt = $pdo->prepare("
                INSERT INTO audit_logs (user_id, action_type, table_name, new_values, ip_address, created_at)
                VALUES (?, 'SYNC', 'payroll_time_sync', ?, ?, NOW())
            ");
            $auditStmt->execute([
                $userId,
                json_encode(['period' => ['start' => $startDate, 'end' => $endDate], 'hours_data' => $hoursData]),
                getClientIP()
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Time tracking data synced to payroll',
                'synced_count' => count($hoursData)
            ]);
            break;
            
        case 'generate_report':
            if ($userRole !== 'admin') {
                echo json_encode(['success' => false, 'message' => 'Admin access required']);
                break;
            }
            
            $staffId = $input['staff_id'] ?? 'all';
            $period = $input['period'] ?? 'month';
            $format = $input['format'] ?? 'view';
            $detailLevel = $input['detail_level'] ?? 'summary';
            $customStartDate = $input['start_date'] ?? null;
            $customEndDate = $input['end_date'] ?? null;
            
            // Calculate date range based on period
            $today = new DateTime();
            switch ($period) {
                case 'day':
                    $startDate = $today->format('Y-m-d');
                    $endDate = $today->format('Y-m-d');
                    break;
                case 'week':
                    $startDate = (clone $today)->modify('monday this week')->format('Y-m-d');
                    $endDate = (clone $today)->modify('sunday this week')->format('Y-m-d');
                    break;
                case 'pay_period':
                    $startDate = $today->format('d') <= 15 
                        ? $today->format('Y-m-01') 
                        : $today->format('Y-m-16');
                    $endDate = $today->format('d') <= 15 
                        ? $today->format('Y-m-15')
                        : $today->format('Y-m-t');
                    break;
                case 'month':
                    $startDate = $today->format('Y-m-01');
                    $endDate = $today->format('Y-m-t');
                    break;
                case 'year':
                    $startDate = $today->format('Y-01-01');
                    $endDate = $today->format('Y-12-31');
                    break;
                case 'last_year':
                    $lastYear = (int)$today->format('Y') - 1;
                    $startDate = "$lastYear-01-01";
                    $endDate = "$lastYear-12-31";
                    break;
                case 'custom':
                    $startDate = $customStartDate;
                    $endDate = $customEndDate;
                    break;
                default:
                    $startDate = $today->format('Y-m-01');
                    $endDate = $today->format('Y-m-t');
            }
            
            if (!$startDate || !$endDate) {
                echo json_encode(['success' => false, 'message' => 'Invalid date range']);
                break;
            }
            
            // Build query
            $staffCondition = $staffId === 'all' ? '' : 'AND ss.staff_id = :staff_id';
            
            $query = "
                SELECT ss.*, u.first_name, u.last_name
                FROM staff_shifts ss
                JOIN users u ON ss.staff_id = u.id
                WHERE ss.shift_date BETWEEN :start_date AND :end_date
                AND ss.status = 'completed'
                $staffCondition
                ORDER BY ss.shift_date DESC, u.last_name, u.first_name
            ";
            
            $stmt = $pdo->prepare($query);
            $params = [':start_date' => $startDate, ':end_date' => $endDate];
            if ($staffId !== 'all') {
                $params[':staff_id'] = $staffId;
            }
            $stmt->execute($params);
            $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (function_exists('decryptUserRows')) {
                $shifts = decryptUserRows($shifts);
            }
            
            if ($format === 'csv') {
                // Generate CSV content
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="time_report_' . date('Y-m-d') . '.csv"');
                
                $output = fopen('php://output', 'w');
                fputcsv($output, ['Date', 'Staff Name', 'Clock In', 'Clock Out', 'Lunch Start', 'Lunch End', 'Total Hours']);
                
                foreach ($shifts as $shift) {
                    fputcsv($output, [
                        $shift['shift_date'],
                        $shift['first_name'] . ' ' . $shift['last_name'],
                        $shift['clock_in'],
                        $shift['clock_out'],
                        $shift['lunch_start'] ?? '',
                        $shift['lunch_end'] ?? '',
                        $shift['total_hours']
                    ]);
                }
                
                fclose($output);
                exit;
            }
            
            // Return JSON for view format
            $totalHours = array_sum(array_column($shifts, 'total_hours'));
            echo json_encode([
                'success' => true,
                'shifts' => $shifts,
                'total_hours' => $totalHours,
                'shift_count' => count($shifts),
                'period' => ['start' => $startDate, 'end' => $endDate]
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
    
} catch (PDOException $e) {
    ErrorLogger::error("Time tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    ErrorLogger::error("Time tracking error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred']);
}
