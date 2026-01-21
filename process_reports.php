<?php
/**
 * Process Report Generation and Management
 * Handles PDF/CSV generation, scheduling, and sharing
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';

checkCsrfToken();

if (!isset($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'athlete';

// Check permissions
if (!in_array($user_role, ['coach', 'coach_plus', 'admin', 'team_coach'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    if ($action === 'generate') {
        generateReport();
    } elseif ($action === 'delete') {
        deleteReport();
    } elseif ($action === 'delete_schedule' || $action === 'schedule_delete') {
        deleteSchedule();
    } elseif ($action === 'toggle_schedule' || $action === 'schedule_toggle') {
        toggleSchedule();
    } elseif ($action === 'schedule_create') {
        createSchedule();
    } elseif ($action === 'schedule_update') {
        updateSchedule();
    } else {
        throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit;
}

function generateReport() {
    global $pdo, $user_id, $user_role;
    
    $report_type = $_POST['report_type'] ?? '';
    $format = $_POST['format'] ?? 'pdf';
    $date_from = $_POST['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $_POST['date_to'] ?? date('Y-m-d');
    $schedule = isset($_POST['schedule']) && $_POST['schedule'] == '1';
    
    if (empty($report_type)) {
        throw new Exception('Report type is required');
    }
    
    // Build parameters
    $parameters = [
        'date_from' => $date_from,
        'date_to' => $date_to,
        'athlete_ids' => $_POST['athlete_ids'] ?? [],
        'team_ids' => $_POST['team_ids'] ?? []
    ];
    
    // Generate the report
    $report_data = fetchReportData($report_type, $parameters);
    
    // Create file
    $filename = generateReportFile($report_type, $format, $report_data, $parameters);
    
    // Generate share token
    $share_token = bin2hex(random_bytes(32));
    
    // Save report record
    $stmt = $pdo->prepare("
        INSERT INTO reports (report_type, generated_by, parameters, format, file_path, share_token, scheduled, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $report_type,
        $user_id,
        json_encode($parameters),
        $format,
        $filename,
        $share_token,
        $schedule ? 1 : 0
    ]);
    
    $report_id = $pdo->lastInsertId();
    
    // If scheduled, create schedule record
    if ($schedule) {
        $frequency = $_POST['frequency'] ?? 'weekly';
        $email_recipients = $_POST['email_recipients'] ?? '';
        
        $next_run = calculateNextRun($frequency);
        
        $stmt = $pdo->prepare("
            INSERT INTO report_schedules (user_id, report_type, parameters, frequency, format, email_recipients, next_run, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $user_id,
            $report_type,
            json_encode($parameters),
            $frequency,
            $format,
            $email_recipients,
            $next_run
        ]);
    }
    
    header('Location: dashboard.php?page=reports&success=1');
    exit;
}

function fetchReportData($report_type, $parameters) {
    global $pdo, $user_id, $user_role;
    
    $data = [];
    
    switch ($report_type) {
        case 'athlete_progress':
            $athlete_ids = $parameters['athlete_ids'] ?? [];
            if (empty($athlete_ids)) {
                // Get all coach's athletes
                $stmt = $pdo->prepare("SELECT id FROM users WHERE assigned_coach_id = ? AND role = 'athlete'");
                $stmt->execute([$user_id]);
                $athlete_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            foreach ($athlete_ids as $athlete_id) {
                $data[] = getAthleteProgressData($athlete_id, $parameters);
            }
            break;
            
        case 'team_roster':
            $team_ids = $parameters['team_ids'] ?? [];
            if (in_array('all', $team_ids) || empty($team_ids)) {
                $stmt = $pdo->query("SELECT id FROM athlete_teams");
                $team_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            
            foreach ($team_ids as $team_id) {
                $data[] = getTeamRosterData($team_id);
            }
            break;
            
        case 'session_attendance':
            $data = getSessionAttendanceData($parameters);
            break;
            
        case 'all_athletes':
            if ($user_role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }
            $data = getAllAthletesData($parameters);
            break;
            
        case 'all_teams':
            if ($user_role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }
            $data = getAllTeamsData();
            break;
            
        case 'packages_discounts':
            if ($user_role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }
            $data = getPackagesDiscountsData($parameters);
            break;
    }
    
    return $data;
}

function getAthleteProgressData($athlete_id, $parameters) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM goals WHERE user_id = u.id AND status = 'completed') as completed_goals,
               (SELECT COUNT(*) FROM goals WHERE user_id = u.id AND status = 'active') as active_goals,
               (SELECT COUNT(*) FROM bookings b 
                INNER JOIN sessions s ON b.session_id = s.id 
                WHERE (b.user_id = u.id OR b.booked_for_user_id = u.id) 
                AND b.status = 'paid' 
                AND s.session_date BETWEEN ? AND ?) as sessions_attended
        FROM users u
        WHERE u.id = ?
    ");
    $stmt->execute([$parameters['date_from'], $parameters['date_to'], $athlete_id]);
    
    return $stmt->fetch();
}

function getTeamRosterData($team_id) {
    global $pdo;
    
    // Get team info
    $team_stmt = $pdo->prepare("SELECT * FROM athlete_teams WHERE id = ?");
    $team_stmt->execute([$team_id]);
    $team = $team_stmt->fetch();
    
    // Get team members
    $members_stmt = $pdo->prepare("
        SELECT u.*
        FROM users u
        WHERE EXISTS (
            SELECT 1 FROM athlete_teams at 
            WHERE at.id = ? AND at.user_id = u.id
        )
    ");
    $members_stmt->execute([$team_id]);
    $members = $members_stmt->fetchAll();
    
    return [
        'team' => $team,
        'members' => $members
    ];
}

function getSessionAttendanceData($parameters) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT s.*, st.name as session_type, l.name as location_name,
               COUNT(b.id) as total_bookings,
               COUNT(CASE WHEN b.status = 'paid' THEN 1 END) as confirmed_bookings
        FROM sessions s
        LEFT JOIN session_types st ON s.session_type_id = st.id
        LEFT JOIN locations l ON s.location_id = l.id
        LEFT JOIN bookings b ON b.session_id = s.id
        WHERE s.session_date BETWEEN ? AND ?
        GROUP BY s.id
        ORDER BY s.session_date DESC
    ");
    $stmt->execute([$parameters['date_from'], $parameters['date_to']]);
    
    return $stmt->fetchAll();
}

function getAllAthletesData($parameters) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*,
               CONCAT(c.first_name, ' ', c.last_name) as coach_name,
               (SELECT COUNT(*) FROM bookings b 
                INNER JOIN sessions s ON b.session_id = s.id 
                WHERE (b.user_id = u.id OR b.booked_for_user_id = u.id) 
                AND b.status = 'paid' 
                AND s.session_date BETWEEN ? AND ?) as sessions_attended
        FROM users u
        LEFT JOIN users c ON u.assigned_coach_id = c.id
        WHERE u.role = 'athlete'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$parameters['date_from'], $parameters['date_to']]);
    
    return $stmt->fetchAll();
}

function getAllTeamsData() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(DISTINCT at.user_id) as member_count,
               GROUP_CONCAT(DISTINCT CONCAT(u.first_name, ' ', u.last_name) SEPARATOR ', ') as coaches
        FROM athlete_teams t
        LEFT JOIN team_coach_assignments tca ON t.id = tca.team_id
        LEFT JOIN users u ON tca.coach_id = u.id
        LEFT JOIN users at ON at.id IN (SELECT user_id FROM athlete_teams WHERE id = t.id)
        GROUP BY t.id
        ORDER BY t.name
    ");
    
    return $stmt->fetchAll();
}

function getPackagesDiscountsData($parameters) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT p.name as package_name, 
               COUNT(upc.id) as purchases,
               SUM(p.price) as revenue,
               COUNT(CASE WHEN upc.discount_code_id IS NOT NULL THEN 1 END) as discounted_purchases
        FROM user_package_credits upc
        INNER JOIN packages p ON upc.package_id = p.id
        WHERE upc.created_at BETWEEN ? AND ?
        GROUP BY p.id
        ORDER BY revenue DESC
    ");
    $stmt->execute([$parameters['date_from'], $parameters['date_to']]);
    
    return $stmt->fetchAll();
}

function generateReportFile($report_type, $format, $data, $parameters) {
    if ($format === 'csv') {
        return generateCSV($report_type, $data, $parameters);
    } else {
        return generatePDF($report_type, $data, $parameters);
    }
}

function generateCSV($report_type, $data, $parameters) {
    $filename = 'reports/' . $report_type . '_' . date('Y-m-d_His') . '.csv';
    $filepath = __DIR__ . '/' . $filename;
    
    // Ensure reports directory exists with secure permissions
    $dir = dirname($filepath);
    if (!file_exists($dir)) {
        mkdir($dir, 0750, true); // Restrictive permissions
    }
    
    $fp = fopen($filepath, 'w');
    
    // Add headers based on report type
    switch ($report_type) {
        case 'athlete_progress':
            fputcsv($fp, ['Name', 'Email', 'Age', 'Position', 'Active Goals', 'Completed Goals', 'Sessions Attended']);
            foreach ($data as $athlete) {
                fputcsv($fp, [
                    $athlete['first_name'] . ' ' . $athlete['last_name'],
                    $athlete['email'],
                    $athlete['birth_date'] ? floor((time() - strtotime($athlete['birth_date'])) / 31556926) : 'N/A',
                    ucfirst($athlete['position'] ?? 'N/A'),
                    $athlete['active_goals'],
                    $athlete['completed_goals'],
                    $athlete['sessions_attended']
                ]);
            }
            break;
            
        case 'all_athletes':
            fputcsv($fp, ['Name', 'Email', 'Coach', 'Position', 'Birth Date', 'Sessions Attended']);
            foreach ($data as $athlete) {
                fputcsv($fp, [
                    $athlete['first_name'] . ' ' . $athlete['last_name'],
                    $athlete['email'],
                    $athlete['coach_name'] ?? 'Unassigned',
                    ucfirst($athlete['position'] ?? 'N/A'),
                    $athlete['birth_date'] ?? 'N/A',
                    $athlete['sessions_attended']
                ]);
            }
            break;
            
        case 'session_attendance':
            fputcsv($fp, ['Date', 'Session Type', 'Location', 'Total Bookings', 'Confirmed']);
            foreach ($data as $session) {
                fputcsv($fp, [
                    date('Y-m-d', strtotime($session['session_date'])),
                    $session['session_type'],
                    $session['location_name'],
                    $session['total_bookings'],
                    $session['confirmed_bookings']
                ]);
            }
            break;
            
        case 'all_teams':
            fputcsv($fp, ['Team Name', 'Members', 'Coaches']);
            foreach ($data as $team) {
                fputcsv($fp, [
                    $team['name'],
                    $team['member_count'],
                    $team['coaches'] ?? 'None'
                ]);
            }
            break;
            
        case 'packages_discounts':
            fputcsv($fp, ['Package', 'Purchases', 'Revenue', 'Discounted Purchases']);
            foreach ($data as $package) {
                fputcsv($fp, [
                    $package['package_name'],
                    $package['purchases'],
                    '$' . number_format($package['revenue'], 2),
                    $package['discounted_purchases']
                ]);
            }
            break;
    }
    
    fclose($fp);
    
    return $filename;
}

function generatePDF($report_type, $data, $parameters) {
    // For PDF generation, we'll use a simple HTML to PDF approach
    // In production, you would use TCPDF or mPDF library
    
    $filename = 'reports/' . $report_type . '_' . date('Y-m-d_His') . '.pdf';
    $filepath = __DIR__ . '/' . $filename;
    
    // Ensure reports directory exists with secure permissions
    $dir = dirname($filepath);
    if (!file_exists($dir)) {
        mkdir($dir, 0750, true); // Restrictive permissions
    }
    
    // Generate HTML content
    $html = generatePDFHTML($report_type, $data, $parameters);
    
    // For now, save as HTML (in production, convert to PDF using library)
    // This is a placeholder - proper PDF generation requires TCPDF/mPDF
    $html_file = str_replace('.pdf', '.html', $filepath);
    file_put_contents($html_file, $html);
    
    // Return HTML file for now (in production, return PDF)
    return str_replace('.pdf', '.html', $filename);
}

function generatePDFHTML($report_type, $data, $parameters) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title><?= htmlspecialchars(ucwords(str_replace('_', ' ', $report_type))) ?> Report</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
            .header { background: #7000a4; color: #fff; padding: 30px; margin: -40px -40px 30px -40px; }
            .header h1 { margin: 0; font-size: 28px; }
            .header .meta { margin-top: 10px; font-size: 14px; opacity: 0.9; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #f8f9fa; font-weight: 700; color: #7000a4; }
            tr:hover { background: #f8f9fa; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #7000a4; text-align: center; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1><?= htmlspecialchars(ucwords(str_replace('_', ' ', $report_type))) ?> Report</h1>
            <div class="meta">
                Generated: <?= date('F j, Y g:i A') ?><br>
                Period: <?= htmlspecialchars($parameters['date_from']) ?> to <?= htmlspecialchars($parameters['date_to']) ?>
            </div>
        </div>
        
        <?php if ($report_type === 'athlete_progress'): ?>
        <h2>Athlete Progress Summary</h2>
        <table>
            <thead>
                <tr>
                    <th>Athlete</th>
                    <th>Email</th>
                    <th>Position</th>
                    <th>Active Goals</th>
                    <th>Completed Goals</th>
                    <th>Sessions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $athlete): ?>
                <tr>
                    <td><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></td>
                    <td><?= htmlspecialchars($athlete['email']) ?></td>
                    <td><?= htmlspecialchars(ucfirst($athlete['position'] ?? 'N/A')) ?></td>
                    <td><?= $athlete['active_goals'] ?></td>
                    <td><?= $athlete['completed_goals'] ?></td>
                    <td><?= $athlete['sessions_attended'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if ($report_type === 'all_athletes'): ?>
        <h2>All Athletes Database</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Coach</th>
                    <th>Position</th>
                    <th>Sessions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $athlete): ?>
                <tr>
                    <td><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></td>
                    <td><?= htmlspecialchars($athlete['email']) ?></td>
                    <td><?= htmlspecialchars($athlete['coach_name'] ?? 'Unassigned') ?></td>
                    <td><?= htmlspecialchars(ucfirst($athlete['position'] ?? 'N/A')) ?></td>
                    <td><?= $athlete['sessions_attended'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <?php if ($report_type === 'session_attendance'): ?>
        <h2>Session Attendance Report</h2>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Session Type</th>
                    <th>Location</th>
                    <th>Bookings</th>
                    <th>Confirmed</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $session): ?>
                <tr>
                    <td><?= date('M j, Y', strtotime($session['session_date'])) ?></td>
                    <td><?= htmlspecialchars($session['session_type']) ?></td>
                    <td><?= htmlspecialchars($session['location_name']) ?></td>
                    <td><?= $session['total_bookings'] ?></td>
                    <td><?= $session['confirmed_bookings'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="footer">
            <strong>Crash Hockey Platform</strong><br>
            Confidential - For Internal Use Only
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

function calculateNextRun($frequency) {
    switch ($frequency) {
        case 'daily':
            return date('Y-m-d H:i:s', strtotime('+1 day'));
        case 'weekly':
            return date('Y-m-d H:i:s', strtotime('+1 week'));
        case 'monthly':
            return date('Y-m-d H:i:s', strtotime('+1 month'));
        default:
            return date('Y-m-d H:i:s', strtotime('+1 week'));
    }
}

function deleteReport() {
    global $pdo, $user_id;
    
    $report_id = $_POST['report_id'] ?? 0;
    
    // Verify ownership
    $stmt = $pdo->prepare("SELECT file_path FROM reports WHERE id = ? AND generated_by = ?");
    $stmt->execute([$report_id, $user_id]);
    $report = $stmt->fetch();
    
    if (!$report) {
        throw new Exception('Report not found');
    }
    
    // Delete file
    if ($report['file_path'] && file_exists(__DIR__ . '/' . $report['file_path'])) {
        unlink(__DIR__ . '/' . $report['file_path']);
    }
    
    // Delete record
    $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->execute([$report_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

function deleteSchedule() {
    global $pdo, $user_id;
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    
    $stmt = $pdo->prepare("DELETE FROM report_schedules WHERE id = ? AND user_id = ?");
    $stmt->execute([$schedule_id, $user_id]);
    
    echo json_encode(['success' => true]);
    exit;
}

function toggleSchedule() {
    global $pdo, $user_id;
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $status = $_POST['is_active'] ?? $_POST['status'] ?? 1;
    
    $stmt = $pdo->prepare("UPDATE report_schedules SET is_active = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$status, $schedule_id, $user_id]);
    
    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
    exit;
}

function createSchedule() {
    global $pdo, $user_id;
    
    $report_type = $_POST['report_type'] ?? '';
    $frequency = $_POST['frequency'] ?? '';
    $format = $_POST['format'] ?? 'pdf';
    $email_recipients = $_POST['email_recipients'] ?? '';
    $parameters = $_POST['parameters'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate required fields
    if (empty($report_type) || empty($frequency) || empty($email_recipients)) {
        throw new Exception('Missing required fields');
    }
    
    // Validate email format
    $emails = array_map('trim', explode(',', $email_recipients));
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address: ' . $email);
        }
    }
    
    // Calculate next run time
    $next_run = new DateTime();
    switch ($frequency) {
        case 'daily':
            $next_run->modify('+1 day');
            break;
        case 'weekly':
            $next_run->modify('+1 week');
            break;
        case 'monthly':
            $next_run->modify('+1 month');
            break;
        default:
            throw new Exception('Invalid frequency');
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO report_schedules 
        (user_id, report_type, parameters, frequency, format, email_recipients, next_run, is_active, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    $stmt->execute([
        $user_id,
        $report_type,
        $parameters,
        $frequency,
        $format,
        $email_recipients,
        $next_run->format('Y-m-d H:i:s'),
        $is_active
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Schedule created successfully']);
    exit;
}

function updateSchedule() {
    global $pdo, $user_id;
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $report_type = $_POST['report_type'] ?? '';
    $frequency = $_POST['frequency'] ?? '';
    $format = $_POST['format'] ?? 'pdf';
    $email_recipients = $_POST['email_recipients'] ?? '';
    $parameters = $_POST['parameters'] ?? '';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Validate required fields
    if (empty($schedule_id) || empty($report_type) || empty($frequency) || empty($email_recipients)) {
        throw new Exception('Missing required fields');
    }
    
    // Validate email format
    $emails = array_map('trim', explode(',', $email_recipients));
    foreach ($emails as $email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Invalid email address: ' . $email);
        }
    }
    
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM report_schedules WHERE id = ? AND user_id = ?");
    $check->execute([$schedule_id, $user_id]);
    if (!$check->fetch()) {
        throw new Exception('Schedule not found or access denied');
    }
    
    // Calculate next run time if frequency changed
    $next_run = new DateTime();
    switch ($frequency) {
        case 'daily':
            $next_run->modify('+1 day');
            break;
        case 'weekly':
            $next_run->modify('+1 week');
            break;
        case 'monthly':
            $next_run->modify('+1 month');
            break;
        default:
            throw new Exception('Invalid frequency');
    }
    
    $stmt = $pdo->prepare("
        UPDATE report_schedules 
        SET report_type = ?, parameters = ?, frequency = ?, format = ?, 
            email_recipients = ?, next_run = ?, is_active = ?
        WHERE id = ? AND user_id = ?
    ");
    
    $stmt->execute([
        $report_type,
        $parameters,
        $frequency,
        $format,
        $email_recipients,
        $next_run->format('Y-m-d H:i:s'),
        $is_active,
        $schedule_id,
        $user_id
    ]);
    
    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
    exit;
}
