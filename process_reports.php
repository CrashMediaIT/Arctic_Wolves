<?php
/**
 * Process Report Generation and Management
 * Handles PDF/CSV generation, scheduling, and sharing
 */

session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

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
    if ($action === 'generate' || $action === 'generate_report' || $action === 'generate_quick_report') {
        generateReport();
    } elseif ($action === 'delete' || $action === 'delete_report') {
        deleteReport();
    } elseif ($action === 'delete_schedule' || $action === 'schedule_delete') {
        deleteSchedule();
    } elseif ($action === 'toggle_schedule' || $action === 'schedule_toggle') {
        toggleSchedule();
    } elseif ($action === 'schedule_create' || $action === 'create_schedule') {
        createSchedule();
    } elseif ($action === 'schedule_update' || $action === 'update_schedule') {
        updateSchedule();
    } else {
        throw new Exception('Invalid action: ' . htmlspecialchars($action));
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
    $date_range = $_POST['date_range'] ?? 'this_month';
    $schedule = isset($_POST['schedule']) && $_POST['schedule'] == '1';
    
    if (empty($report_type)) {
        throw new Exception('Report type is required');
    }
    
    // Calculate date range based on selection
    $dates = calculateDateRange($date_range, $_POST);
    $date_from = $dates['from'];
    $date_to = $dates['to'];
    
    // Build parameters
    $parameters = [
        'date_from' => $date_from,
        'date_to' => $date_to,
        'date_range' => $date_range,
        'athlete_ids' => $_POST['athlete_ids'] ?? [],
        'team_ids' => $_POST['team_ids'] ?? [],
        'detailed_breakdown' => isset($_POST['detailed_breakdown']),
        'show_charts' => isset($_POST['show_charts']),
        'compare_previous' => isset($_POST['compare_previous']),
        'compare_year_1' => $_POST['compare_year_1'] ?? null,
        'compare_year_2' => $_POST['compare_year_2'] ?? null,
    ];
    
    // Generate the report
    $report_data = fetchReportData($report_type, $parameters);
    
    // Create file
    $filename = generateReportFile($report_type, $format, $report_data, $parameters);
    
    // Generate share token
    $share_token = bin2hex(random_bytes(32));
    
    // Save report record
    $report_name = $_POST['report_name'] ?? ucfirst(str_replace('_', ' ', $report_type)) . ' Report - ' . date('Y-m-d');
    $stmt = $pdo->prepare("
        INSERT INTO reports (report_name, report_type, generated_by, parameters, file_path, status)
        VALUES (?, ?, ?, ?, ?, 'completed')
    ");
    $stmt->execute([
        $report_name,
        $report_type,
        $user_id,
        json_encode($parameters),
        $filename
    ]);
    
    $report_id = $pdo->lastInsertId();
    
    // Add audit log entry for report generation
    $auditData = [
        'report_id' => $report_id,
        'report_name' => $report_name,
        'report_type' => $report_type,
        'format' => $format,
        'date_range' => $date_range,
        'date_from' => $date_from,
        'date_to' => $date_to,
    ];
    
    $auditStmt = $pdo->prepare("
        INSERT INTO audit_logs 
        (user_id, action_type, action, table_name, record_id, new_values, ip_address, user_agent, created_at)
        VALUES (?, 'CREATE', 'report_generated', 'reports', ?, ?, ?, ?, NOW())
    ");
    $auditStmt->execute([
        $user_id,
        $report_id,
        json_encode($auditData),
        $_SERVER['REMOTE_ADDR'] ?? 'CLI',
        $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
    ]);
    
    // If scheduled, create schedule record
    if ($schedule) {
        $frequency = $_POST['frequency'] ?? 'weekly';
        $email_recipients = $_POST['email_recipients'] ?? '';
        
        $next_run = calculateNextRun($frequency);
        
        $stmt = $pdo->prepare("
            INSERT INTO report_schedules (created_by, report_type, parameters, schedule_frequency, format, recipients, next_run, is_active, report_name)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id,
            $report_type,
            json_encode($parameters),
            $frequency,
            $format,
            $email_recipients,
            $next_run,
            1,
            $report_type . ' Report'
        ]);
        
        $schedule_id = $pdo->lastInsertId();
        
        // Audit log for schedule creation
        $scheduleAuditData = [
            'schedule_id' => $schedule_id,
            'report_type' => $report_type,
            'frequency' => $frequency,
            'format' => $format,
            'recipients' => $email_recipients,
        ];
        
        $auditStmt = $pdo->prepare("
            INSERT INTO audit_logs 
            (user_id, action_type, action, table_name, record_id, new_values, ip_address, user_agent, created_at)
            VALUES (?, 'CREATE', 'schedule_created', 'report_schedules', ?, ?, ?, ?, NOW())
        ");
        $auditStmt->execute([
            $user_id,
            $schedule_id,
            json_encode($scheduleAuditData),
            $_SERVER['REMOTE_ADDR'] ?? 'CLI',
            $_SERVER['HTTP_USER_AGENT'] ?? 'CLI'
        ]);
    }
    
    header('Location: dashboard.php?page=financial_reports&tab=history&success=1');
    exit;
}

/**
 * Calculate date range based on selection
 */
function calculateDateRange($date_range, $post_data) {
    $now = new DateTime();
    
    switch ($date_range) {
        case 'today':
            return ['from' => $now->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
            
        case 'yesterday':
            $yesterday = (clone $now)->modify('-1 day');
            return ['from' => $yesterday->format('Y-m-d'), 'to' => $yesterday->format('Y-m-d')];
            
        case 'this_week':
            $start = (clone $now)->modify('monday this week');
            return ['from' => $start->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
            
        case 'last_week':
            $start = (clone $now)->modify('monday last week');
            $end = (clone $start)->modify('+6 days');
            return ['from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')];
            
        case 'this_month':
            return ['from' => $now->format('Y-m-01'), 'to' => $now->format('Y-m-d')];
            
        case 'last_month':
            $start = (clone $now)->modify('first day of last month');
            $end = (clone $now)->modify('last day of last month');
            return ['from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')];
            
        case 'this_quarter':
            $quarter = ceil($now->format('n') / 3);
            $start_month = ($quarter - 1) * 3 + 1;
            $start = new DateTime($now->format('Y') . '-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01');
            return ['from' => $start->format('Y-m-d'), 'to' => $now->format('Y-m-d')];
            
        case 'last_quarter':
            $quarter = ceil($now->format('n') / 3);
            $prev_quarter = $quarter == 1 ? 4 : $quarter - 1;
            $year = $quarter == 1 ? $now->format('Y') - 1 : $now->format('Y');
            $start_month = ($prev_quarter - 1) * 3 + 1;
            $end_month = $prev_quarter * 3;
            $start = new DateTime($year . '-' . str_pad($start_month, 2, '0', STR_PAD_LEFT) . '-01');
            $end = new DateTime($year . '-' . str_pad($end_month, 2, '0', STR_PAD_LEFT) . '-01');
            $end->modify('last day of this month');
            return ['from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')];
            
        case 'this_year':
            return ['from' => $now->format('Y-01-01'), 'to' => $now->format('Y-m-d')];
            
        case 'last_year':
            $last_year = $now->format('Y') - 1;
            return ['from' => $last_year . '-01-01', 'to' => $last_year . '-12-31'];
            
        case 'year_comparison':
            // For year comparison, use full years
            $year1 = $post_data['compare_year_1'] ?? $now->format('Y');
            return ['from' => $year1 . '-01-01', 'to' => $year1 . '-12-31'];
            
        case 'custom':
            return [
                'from' => $post_data['date_from'] ?? $now->format('Y-m-01'),
                'to' => $post_data['date_to'] ?? $now->format('Y-m-d')
            ];
            
        default:
            return ['from' => $now->format('Y-m-01'), 'to' => $now->format('Y-m-d')];
    }
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
        case 'session_analytics':
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
        case 'package_performance':
        case 'package_sales':
            $data = getPackagesDiscountsData($parameters);
            break;
            
        // Accounting report types
        case 'revenue_summary':
        case 'monthly_revenue':
            $data = getRevenueData($parameters);
            break;
            
        case 'stripe_transactions':
            $data = getStripeTransactionsData($parameters);
            break;
            
        case 'expense_report':
            $data = getExpenseData($parameters);
            break;
            
        case 'profit_loss':
            $data = getProfitLossData($parameters);
            break;
            
        case 'tax_summary':
        case 'tax_report':
            $data = getTaxData($parameters);
            break;
            
        case 'client_billing':
        case 'client_summary':
            $data = getClientBillingData($parameters);
            break;
            
        case 'coach_payments':
            $data = getCoachPaymentsData($parameters);
            break;
            
        case 'user_activity':
            if ($user_role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }
            $data = getUserActivityData($parameters);
            break;
            
        case 'user_stats':
            if ($user_role !== 'admin') {
                throw new Exception('Insufficient permissions');
            }
            $data = getUserStatsData($parameters);
            break;
            
        default:
            // Log unknown report type for debugging
            ErrorLogger::error("Unknown report type requested: " . htmlspecialchars($report_type));
            // Generate placeholder report with message
            $data = [
                'message' => 'Report generated for type: ' . htmlspecialchars($report_type), 
                'generated_at' => date('Y-m-d H:i:s'),
                'note' => 'This report type may not have specific data handlers configured.'
            ];
    }
    
    return $data;
}

// Stripe Transactions Report
function getStripeTransactionsData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    $transactions = [];
    
    // Load Stripe settings
    $stripeSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('stripe_secret_key', 'currency')")->fetchAll(PDO::FETCH_KEY_PAIR);
    if (function_exists('decryptCredential') && !empty($stripeSettings['stripe_secret_key'])) {
        $stripeSettings['stripe_secret_key'] = decryptCredential($stripeSettings['stripe_secret_key']);
    }
    
    if (empty($stripeSettings['stripe_secret_key'])) {
        return [
            'error' => 'Stripe not configured',
            'message' => 'Please configure Stripe in System Tools → Payments to generate this report.',
            'local_payments' => getLocalPaymentsWithStripeInfo($date_from, $date_to)
        ];
    }
    
    try {
        // Load Stripe library
        $stripeLibLoaded = false;
        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
            $stripeLibLoaded = true;
        } elseif (file_exists(__DIR__ . '/stripe-php/init.php')) {
            require_once __DIR__ . '/stripe-php/init.php';
            $stripeLibLoaded = true;
        }
        
        if (!$stripeLibLoaded) {
            throw new Exception('Stripe library not found');
        }
        
        \Stripe\Stripe::setApiKey($stripeSettings['stripe_secret_key']);
        
        // Fetch charges from Stripe
        $charges = \Stripe\Charge::all([
            'created' => [
                'gte' => strtotime($date_from),
                'lte' => strtotime($date_to . ' 23:59:59')
            ],
            'limit' => 100
        ]);
        
        foreach ($charges->data as $charge) {
            // Safely access nested properties
            $billingEmail = isset($charge->billing_details) && isset($charge->billing_details->email) 
                ? $charge->billing_details->email : null;
            $paymentMethodType = isset($charge->payment_method_details) && isset($charge->payment_method_details->type) 
                ? $charge->payment_method_details->type : 'card';
            
            $transactions[] = [
                'id' => $charge->id,
                'amount' => $charge->amount / 100,
                'currency' => strtoupper($charge->currency),
                'status' => $charge->status,
                'description' => $charge->description ?? 'N/A',
                'customer_email' => $charge->receipt_email ?? $billingEmail ?? 'N/A',
                'created' => date('Y-m-d H:i:s', $charge->created),
                'payment_method' => $paymentMethodType,
                'refunded' => $charge->refunded ? 'Yes' : 'No',
                'receipt_url' => $charge->receipt_url ?? null
            ];
        }
        
        return [
            'stripe_transactions' => $transactions,
            'total_count' => count($transactions),
            'period' => $date_from . ' to ' . $date_to,
            'total_amount' => array_sum(array_column($transactions, 'amount')),
            'currency' => $stripeSettings['currency'] ?? 'CAD'
        ];
        
    } catch (Exception $e) {
        ErrorLogger::error("Stripe report error: " . $e->getMessage());
        return [
            'error' => 'Stripe API Error',
            'message' => $e->getMessage(),
            'local_payments' => getLocalPaymentsWithStripeInfo($date_from, $date_to)
        ];
    }
}

function getLocalPaymentsWithStripeInfo($date_from, $date_to) {
    global $pdo;
    $stmt = $pdo->prepare("
        SELECT p.*, u.first_name, u.last_name, u.email
        FROM payments p
        LEFT JOIN users u ON p.user_id = u.id
        WHERE p.payment_date BETWEEN ? AND ?
        AND p.transaction_id IS NOT NULL
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Add new accounting report data functions
function getRevenueData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(payment_date, '%Y-%m') as month,
            COUNT(*) as transaction_count,
            SUM(amount) as total_revenue,
            payment_method
        FROM payments
        WHERE payment_date BETWEEN ? AND ?
        GROUP BY DATE_FORMAT(payment_date, '%Y-%m'), payment_method
        ORDER BY month DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getExpenseData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    // Fetch expenses from database
    // Note: expenses table uses VARCHAR 'category' field directly, not a foreign key
    $stmt = $pdo->prepare("
        SELECT e.*, e.category as category_name
        FROM expenses e
        WHERE e.expense_date BETWEEN ? AND ?
        ORDER BY e.expense_date DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $totalAmount = 0;
    foreach ($expenses as $expense) {
        $totalAmount += floatval($expense['total_amount'] ?? $expense['amount'] ?? 0);
    }
    
    return [
        'period' => $date_from . ' to ' . $date_to,
        'total_amount' => $totalAmount,
        'expense_count' => count($expenses),
        'expenses' => $expenses
    ];
}

function getProfitLossData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    // Get revenue
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) as revenue FROM payments WHERE payment_date BETWEEN ? AND ?");
    $stmt->execute([$date_from, $date_to]);
    $revenue = floatval($stmt->fetch(PDO::FETCH_ASSOC)['revenue']);
    
    // Get expenses
    $expenseStmt = $pdo->prepare("SELECT COALESCE(SUM(COALESCE(total_amount, amount)), 0) as total_expenses FROM expenses WHERE expense_date BETWEEN ? AND ?");
    $expenseStmt->execute([$date_from, $date_to]);
    $expenses = floatval($expenseStmt->fetch(PDO::FETCH_ASSOC)['total_expenses']);
    
    return [
        'period' => $date_from . ' to ' . $date_to,
        'revenue' => $revenue,
        'expenses' => $expenses,
        'profit' => $revenue - $expenses
    ];
}

function getTaxData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(total_amount), 0) as gross_revenue,
            COALESCE(SUM(tax_amount), 0) as tax_collected
        FROM invoices
        WHERE invoice_date BETWEEN ? AND ? AND status = 'paid'
    ");
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getClientBillingData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            u.first_name, u.last_name, u.email,
            COUNT(i.id) as invoice_count,
            COALESCE(SUM(i.total_amount), 0) as total_billed,
            COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN i.status IN ('pending', 'sent') THEN i.total_amount ELSE 0 END), 0) as outstanding
        FROM users u
        LEFT JOIN invoices i ON u.id = i.user_id AND i.invoice_date BETWEEN ? AND ?
        WHERE u.role IN ('athlete', 'parent')
        GROUP BY u.id
        HAVING invoice_count > 0
        ORDER BY total_billed DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getCoachPaymentsData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            u.first_name, u.last_name,
            COUNT(DISTINCT s.id) as sessions_coached,
            COUNT(DISTINCT b.id) as athletes_trained
        FROM users u
        LEFT JOIN sessions s ON u.id = s.coach_id AND s.session_date BETWEEN ? AND ?
        LEFT JOIN bookings b ON s.id = b.session_id AND b.status = 'paid'
        WHERE u.role IN ('coach', 'coach_plus')
        GROUP BY u.id
        ORDER BY sessions_coached DESC
    ");
    $stmt->execute([$date_from, $date_to]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getAthleteProgressData($athlete_id, $parameters) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM goals WHERE user_id = u.id AND status = 'completed') as completed_goals,
               (SELECT COUNT(*) FROM goals WHERE user_id = u.id AND status = 'active') as active_goals,
               (SELECT COUNT(*) FROM bookings b 
                INNER JOIN sessions s ON b.session_id = s.id 
                WHERE b.user_id = u.id 
                AND b.payment_status = 'paid' 
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
               c.first_name as coach_first_name, c.last_name as coach_last_name,
               (SELECT COUNT(*) FROM bookings b 
                INNER JOIN sessions s ON b.session_id = s.id 
                WHERE b.user_id = u.id 
                AND b.payment_status = 'paid' 
                AND s.session_date BETWEEN ? AND ?) as sessions_attended
        FROM users u
        LEFT JOIN users c ON u.assigned_coach_id = c.id
        WHERE u.role = 'athlete'
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$parameters['date_from'], $parameters['date_to']]);
    
    $rows = $stmt->fetchAll();
    $rows = decryptUserRows($rows);
    foreach ($rows as &$r) {
        $r['coach_name'] = trim(($r['coach_first_name'] ?? '') . ' ' . ($r['coach_last_name'] ?? ''));
    }
    unset($r);
    return $rows;
}

function getAllTeamsData() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT t.*, 
               COUNT(DISTINCT at.user_id) as member_count
        FROM athlete_teams t
        LEFT JOIN team_coach_assignments tca ON t.id = tca.team_id
        LEFT JOIN users at ON at.id IN (SELECT user_id FROM athlete_teams WHERE id = t.id)
        GROUP BY t.id
        ORDER BY t.name
    ");
    
    $teams = $stmt->fetchAll();
    
    // Fetch all coach assignments in one query to avoid N+1
    $coach_stmt = $pdo->query("
        SELECT tca.team_id, u.first_name, u.last_name
        FROM team_coach_assignments tca
        JOIN users u ON tca.coach_id = u.id
    ");
    $allCoaches = $coach_stmt->fetchAll();
    $allCoaches = decryptUserRows($allCoaches);
    
    // Group coaches by team_id
    $coachesByTeam = [];
    foreach ($allCoaches as $c) {
        $coachesByTeam[$c['team_id']][] = trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''));
    }
    
    foreach ($teams as &$team) {
        $team['coaches'] = implode(', ', $coachesByTeam[$team['id']] ?? []);
    }
    unset($team);
    
    return $teams;
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

/**
 * Get detailed user activity data - registrations, sessions, packages in a time frame
 */
function getUserActivityData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    $athlete_ids = $parameters['athlete_ids'] ?? [];
    
    // Build optional user ID filter
    $id_filter = '';
    if (!empty($athlete_ids) && is_array($athlete_ids)) {
        $id_filter = "AND u.id IN (" . implode(',', array_fill(0, count($athlete_ids), '?')) . ")";
    }
    
    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.role, u.created_at as member_since,
               (SELECT COUNT(*) FROM bookings b
                INNER JOIN sessions s ON b.session_id = s.id
                WHERE b.user_id = u.id
                AND b.status = 'confirmed' AND b.payment_status = 'paid'
                AND s.session_date BETWEEN ? AND ?) as sessions_attended,
               (SELECT COUNT(*) FROM user_packages up
                WHERE up.user_id = u.id
                AND up.purchase_date BETWEEN ? AND ?) as packages_purchased,
               (SELECT COALESCE(SUM(up.amount_paid), 0) FROM user_packages up
                WHERE up.user_id = u.id
                AND up.purchase_date BETWEEN ? AND ?) as total_spent
        FROM users u
        WHERE u.role IN ('athlete', 'parent')
        $id_filter
        ORDER BY u.last_name, u.first_name
    ";
    
    // Date params for subqueries come first, then optional ID filter params
    $params = [$date_from, $date_to, $date_from, $date_to, $date_from, $date_to];
    if (!empty($athlete_ids) && is_array($athlete_ids)) {
        foreach ($athlete_ids as $aid) {
            $params[] = intval($aid);
        }
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = decryptUserRows($users);
    
    // For each user, get their session detail list
    $result = [];
    foreach ($users as $user) {
        $detail_stmt = $pdo->prepare("
            SELECT s.title as session_title, s.session_date, s.session_time,
                   st.name as session_type, l.name as location_name,
                   b.amount as amount_paid, b.booking_date, b.payment_status
            FROM bookings b
            INNER JOIN sessions s ON b.session_id = s.id
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN locations l ON s.location_id = l.id
            WHERE b.user_id = ?
            AND s.session_date BETWEEN ? AND ?
            ORDER BY s.session_date DESC
        ");
        $detail_stmt->execute([$user['id'], $date_from, $date_to]);
        $sessions = $detail_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $user['sessions'] = $sessions;
        $result[] = $user;
    }
    
    return $result;
}

/**
 * Get user stats data - athlete performance statistics
 */
function getUserStatsData($parameters) {
    global $pdo;
    $date_from = $parameters['date_from'] ?? date('Y-m-d', strtotime('-365 days'));
    $date_to = $parameters['date_to'] ?? date('Y-m-d');
    $athlete_ids = $parameters['athlete_ids'] ?? [];
    
    $id_filter = '';
    $params = [];
    if (!empty($athlete_ids) && is_array($athlete_ids)) {
        $id_filter = "AND u.id IN (" . implode(',', array_fill(0, count($athlete_ids), '?')) . ")";
        foreach ($athlete_ids as $aid) {
            $params[] = intval($aid);
        }
    }
    
    // Get users with their stats
    $sql = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.role,
               ast.season, ast.games_played, ast.goals, ast.assists, ast.points,
               ast.penalty_minutes, ast.shots, ast.plus_minus,
               ast.shots_against, ast.goals_against, ast.saves, ast.save_percentage,
               (SELECT COUNT(*) FROM athlete_evaluations ae
                WHERE ae.athlete_id = u.id
                AND ae.evaluation_date BETWEEN ? AND ?) as evaluation_count,
               (SELECT ROUND(AVG(ae.rating), 1) FROM athlete_evaluations ae
                WHERE ae.athlete_id = u.id
                AND ae.evaluation_date BETWEEN ? AND ?) as avg_evaluation_rating,
               (SELECT COUNT(*) FROM goals g
                WHERE g.athlete_id = u.id AND g.status = 'completed') as completed_goals,
               (SELECT COUNT(*) FROM goals g
                WHERE g.athlete_id = u.id AND g.status = 'active') as active_goals
        FROM users u
        LEFT JOIN athlete_stats ast ON u.id = ast.user_id
        WHERE u.role = 'athlete'
        $id_filter
        ORDER BY u.last_name, u.first_name
    ";
    
    $all_params = array_merge([$date_from, $date_to, $date_from, $date_to], $params);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($all_params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = decryptUserRows($users);
    
    return $users;
}

function generateReportFile($report_type, $format, $data, $parameters) {
    if ($format === 'csv') {
        return generateCSV($report_type, $data, $parameters);
    } elseif ($format === 'excel') {
        return generateExcel($report_type, $data, $parameters);
    } else {
        return generatePDF($report_type, $data, $parameters);
    }
}

/**
 * Sanitize report type for safe filename use
 */
function sanitizeReportType($report_type) {
    // Remove any path traversal characters and only allow alphanumeric and underscores
    return preg_replace('/[^a-zA-Z0-9_]/', '', $report_type);
}

/**
 * Generate Excel file (tab-separated with .xls extension for compatibility)
 */
function generateExcel($report_type, $data, $parameters) {
    $safe_report_type = sanitizeReportType($report_type);
    $filename = 'reports/' . $safe_report_type . '_' . date('Y-m-d_His') . '.xls';
    $filepath = __DIR__ . '/' . $filename;
    
    // Ensure reports directory exists with secure permissions
    $dir = dirname($filepath);
    if (!file_exists($dir)) {
        mkdir($dir, 0750, true);
    }
    
    // Generate HTML table that Excel can open
    $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
    $html .= '<style>table { border-collapse: collapse; } th, td { border: 1px solid #000; padding: 8px; text-align: left; }</style>';
    $html .= '</head><body>';
    $html .= '<h1>' . htmlspecialchars(ucwords(str_replace('_', ' ', $report_type))) . ' Report</h1>';
    $html .= '<p>Period: ' . htmlspecialchars($parameters['date_from']) . ' to ' . htmlspecialchars($parameters['date_to']) . '</p>';
    $html .= '<p>Generated: ' . date('F j, Y g:i A') . '</p>';
    $html .= '<table>';
    
    // Generate table based on report type
    $html .= generateExcelTable($report_type, $data);
    
    $html .= '</table></body></html>';
    
    file_put_contents($filepath, $html);
    
    return $filename;
}

/**
 * Generate HTML table content for Excel export
 */
function generateExcelTable($report_type, $data) {
    $html = '';
    
    switch ($report_type) {
        case 'revenue_summary':
        case 'monthly_revenue':
            $html .= '<tr><th>Month</th><th>Transactions</th><th>Revenue</th><th>Payment Method</th></tr>';
            if (is_array($data)) {
                foreach ($data as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['month'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['transaction_count'] ?? 0) . '</td>';
                    $html .= '<td>$' . number_format($row['total_revenue'] ?? 0, 2) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['payment_method'] ?? '') . '</td>';
                    $html .= '</tr>';
                }
            }
            break;
            
        case 'profit_loss':
            $html .= '<tr><th>Metric</th><th>Amount</th></tr>';
            $html .= '<tr><td>Period</td><td>' . htmlspecialchars($data['period'] ?? '') . '</td></tr>';
            $html .= '<tr><td>Revenue</td><td>$' . number_format($data['revenue'] ?? 0, 2) . '</td></tr>';
            $html .= '<tr><td>Expenses</td><td>$' . number_format($data['expenses'] ?? 0, 2) . '</td></tr>';
            $html .= '<tr><td>Net Profit</td><td>$' . number_format($data['profit'] ?? 0, 2) . '</td></tr>';
            break;
            
        case 'session_analytics':
        case 'session_attendance':
            $html .= '<tr><th>Date</th><th>Session Type</th><th>Location</th><th>Bookings</th><th>Confirmed</th></tr>';
            if (is_array($data)) {
                foreach ($data as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars(date('M j, Y', strtotime($row['session_date'] ?? ''))) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['session_type'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['location_name'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['total_bookings'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['confirmed_bookings'] ?? 0) . '</td>';
                    $html .= '</tr>';
                }
            }
            break;
            
        case 'package_performance':
        case 'package_sales':
            $html .= '<tr><th>Package</th><th>Purchases</th><th>Revenue</th><th>Discounted</th></tr>';
            if (is_array($data)) {
                foreach ($data as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($row['package_name'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['purchases'] ?? 0) . '</td>';
                    $html .= '<td>$' . number_format($row['revenue'] ?? 0, 2) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['discounted_purchases'] ?? 0) . '</td>';
                    $html .= '</tr>';
                }
            }
            break;
            
        case 'client_billing':
            $html .= '<tr><th>Name</th><th>Email</th><th>Invoices</th><th>Total Billed</th><th>Paid</th><th>Outstanding</th></tr>';
            if (is_array($data)) {
                foreach ($data as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['invoice_count'] ?? 0) . '</td>';
                    $html .= '<td>$' . number_format($row['total_billed'] ?? 0, 2) . '</td>';
                    $html .= '<td>$' . number_format($row['total_paid'] ?? 0, 2) . '</td>';
                    $html .= '<td>$' . number_format($row['outstanding'] ?? 0, 2) . '</td>';
                    $html .= '</tr>';
                }
            }
            break;
            
        case 'user_activity':
            $html .= '<tr><th>Name</th><th>Email</th><th>Role</th><th>Sessions</th><th>Packages</th><th>Total Spent</th></tr>';
            if (is_array($data)) {
                foreach ($data as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['email'] ?? '') . '</td>';
                    $html .= '<td>' . htmlspecialchars(ucfirst($row['role'] ?? '')) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['sessions_attended'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['packages_purchased'] ?? 0) . '</td>';
                    $html .= '<td>$' . number_format($row['total_spent'] ?? 0, 2) . '</td>';
                    $html .= '</tr>';
                }
            }
            break;
            
        case 'user_stats':
            $html .= '<tr><th>Name</th><th>Season</th><th>GP</th><th>G</th><th>A</th><th>PTS</th><th>PIM</th><th>+/-</th><th>Evaluations</th><th>Avg Rating</th></tr>';
            if (is_array($data)) {
                foreach ($data as $row) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? '')) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['season'] ?? 'N/A') . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['games_played'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['goals'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['assists'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['points'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['penalty_minutes'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['plus_minus'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['evaluation_count'] ?? 0) . '</td>';
                    $html .= '<td>' . htmlspecialchars($row['avg_evaluation_rating'] ?? 'N/A') . '</td>';
                    $html .= '</tr>';
                }
            }
            break;
            
        default:
            $html .= '<tr><th>Data</th></tr>';
            $html .= '<tr><td>' . htmlspecialchars(json_encode($data)) . '</td></tr>';
    }
    
    return $html;
}

function generateCSV($report_type, $data, $parameters) {
    $safe_report_type = sanitizeReportType($report_type);
    $filename = 'reports/' . $safe_report_type . '_' . date('Y-m-d_His') . '.csv';
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
            
        case 'user_activity':
            fputcsv($fp, ['Name', 'Email', 'Role', 'Member Since', 'Sessions Attended', 'Packages Purchased', 'Total Spent']);
            foreach ($data as $user) {
                fputcsv($fp, [
                    ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
                    $user['email'] ?? '',
                    ucfirst($user['role'] ?? ''),
                    $user['member_since'] ? date('Y-m-d', strtotime($user['member_since'])) : 'N/A',
                    $user['sessions_attended'] ?? 0,
                    $user['packages_purchased'] ?? 0,
                    '$' . number_format($user['total_spent'] ?? 0, 2)
                ]);
            }
            // Add detailed session rows
            fputcsv($fp, []);
            fputcsv($fp, ['--- Session Details ---']);
            fputcsv($fp, ['User', 'Session', 'Date', 'Time', 'Type', 'Location', 'Amount', 'Status']);
            foreach ($data as $user) {
                $name = ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '');
                foreach ($user['sessions'] ?? [] as $sess) {
                    fputcsv($fp, [
                        $name,
                        $sess['session_title'] ?? '',
                        $sess['session_date'] ? date('Y-m-d', strtotime($sess['session_date'])) : '',
                        $sess['session_time'] ?? '',
                        $sess['session_type'] ?? '',
                        $sess['location_name'] ?? '',
                        '$' . number_format($sess['amount_paid'] ?? 0, 2),
                        $sess['payment_status'] ?? ''
                    ]);
                }
            }
            break;
            
        case 'user_stats':
            fputcsv($fp, ['Name', 'Email', 'Season', 'Games Played', 'Goals', 'Assists', 'Points', 'PIM', 'Shots', '+/-', 'Evaluations', 'Avg Rating', 'Active Goals', 'Completed Goals']);
            foreach ($data as $user) {
                fputcsv($fp, [
                    ($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''),
                    $user['email'] ?? '',
                    $user['season'] ?? 'N/A',
                    $user['games_played'] ?? 0,
                    $user['goals'] ?? 0,
                    $user['assists'] ?? 0,
                    $user['points'] ?? 0,
                    $user['penalty_minutes'] ?? 0,
                    $user['shots'] ?? 0,
                    $user['plus_minus'] ?? 0,
                    $user['evaluation_count'] ?? 0,
                    $user['avg_evaluation_rating'] ?? 'N/A',
                    $user['active_goals'] ?? 0,
                    $user['completed_goals'] ?? 0
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
    
    $safe_report_type = sanitizeReportType($report_type);
    $filename = 'reports/' . $safe_report_type . '_' . date('Y-m-d_His') . '.pdf';
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
        
        <?php if ($report_type === 'user_activity'): ?>
        <h2>User Activity Report</h2>
        <p>Showing registrations and sessions for <?= htmlspecialchars($parameters['date_from']) ?> to <?= htmlspecialchars($parameters['date_to']) ?></p>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Sessions Attended</th>
                    <th>Packages Purchased</th>
                    <th>Total Spent</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $user): ?>
                <tr>
                    <td><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($user['email'] ?? '') ?></td>
                    <td><?= htmlspecialchars(ucfirst($user['role'] ?? '')) ?></td>
                    <td><?= $user['sessions_attended'] ?? 0 ?></td>
                    <td><?= $user['packages_purchased'] ?? 0 ?></td>
                    <td>$<?= number_format($user['total_spent'] ?? 0, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php foreach ($data as $user): ?>
        <?php if (!empty($user['sessions'])): ?>
        <h3 style="margin-top: 30px;"><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?> - Session Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Session</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Type</th>
                    <th>Location</th>
                    <th>Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($user['sessions'] as $sess): ?>
                <tr>
                    <td><?= htmlspecialchars($sess['session_title'] ?? '') ?></td>
                    <td><?= $sess['session_date'] ? date('M j, Y', strtotime($sess['session_date'])) : '' ?></td>
                    <td><?= $sess['session_time'] ?? '' ?></td>
                    <td><?= htmlspecialchars($sess['session_type'] ?? '') ?></td>
                    <td><?= htmlspecialchars($sess['location_name'] ?? '') ?></td>
                    <td>$<?= number_format($sess['amount_paid'] ?? 0, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php endif; ?>
        
        <?php if ($report_type === 'user_stats'): ?>
        <h2>User Stats Report</h2>
        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Season</th>
                    <th>GP</th>
                    <th>G</th>
                    <th>A</th>
                    <th>PTS</th>
                    <th>PIM</th>
                    <th>+/-</th>
                    <th>Evaluations</th>
                    <th>Avg Rating</th>
                    <th>Active Goals</th>
                    <th>Completed Goals</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data as $user): ?>
                <tr>
                    <td><?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></td>
                    <td><?= htmlspecialchars($user['season'] ?? 'N/A') ?></td>
                    <td><?= $user['games_played'] ?? 0 ?></td>
                    <td><?= $user['goals'] ?? 0 ?></td>
                    <td><?= $user['assists'] ?? 0 ?></td>
                    <td><?= $user['points'] ?? 0 ?></td>
                    <td><?= $user['penalty_minutes'] ?? 0 ?></td>
                    <td><?= $user['plus_minus'] ?? 0 ?></td>
                    <td><?= $user['evaluation_count'] ?? 0 ?></td>
                    <td><?= $user['avg_evaluation_rating'] ?? 'N/A' ?></td>
                    <td><?= $user['active_goals'] ?? 0 ?></td>
                    <td><?= $user['completed_goals'] ?? 0 ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <div class="footer">
            <strong>Arctic Wolves Platform</strong><br>
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
        case 'quarterly':
            return date('Y-m-d H:i:s', strtotime('+3 months'));
        case 'annually':
            return date('Y-m-d H:i:s', strtotime('+1 year'));
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
    
    $stmt = $pdo->prepare("DELETE FROM reports WHERE id = ?");
    $stmt->execute([$report_id]);
    
    Auditor::log($pdo, $user_id, 'delete', 'reports', $report_id, ['action' => 'report_deleted']);
    
    echo json_encode(['success' => true]);
    exit;
}

function deleteSchedule() {
    global $pdo, $user_id;
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    
    $stmt = $pdo->prepare("DELETE FROM report_schedules WHERE id = ? AND created_by = ?");
    $stmt->execute([$schedule_id, $user_id]);
    
    Auditor::log($pdo, $user_id, 'delete', 'report_schedules', $schedule_id, ['action' => 'report_schedule_deleted']);
    
    echo json_encode(['success' => true]);
    exit;
}

function toggleSchedule() {
    global $pdo, $user_id;
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $status = $_POST['is_active'] ?? $_POST['status'] ?? 1;
    
    $stmt = $pdo->prepare("UPDATE report_schedules SET is_active = ? WHERE id = ? AND created_by = ?");
    $stmt->execute([$status, $schedule_id, $user_id]);
    
    Auditor::log($pdo, $user_id, 'update', 'report_schedules', $schedule_id, ['action' => 'report_schedule_toggled']);
    
    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
    exit;
}

function createSchedule() {
    global $pdo, $user_id;
    
    // Sanitize and validate inputs
    $schedule_name = trim(strip_tags($_POST['schedule_name'] ?? ''));
    $report_type = trim(strip_tags($_POST['report_type'] ?? ''));
    $frequency = trim(strtolower($_POST['frequency'] ?? '')); // Normalize to lowercase
    $format = in_array($_POST['format'] ?? 'pdf', ['pdf', 'excel', 'csv']) ? $_POST['format'] : 'pdf';
    $email_recipients = trim($_POST['email_recipients'] ?? '');
    $time = $_POST['time'] ?? '09:00';
    $day_of_period = trim(strip_tags($_POST['day_of_period'] ?? ''));
    $parameters = trim($_POST['parameters'] ?? '');
    $is_active = 1;
    
    // Validate time format (HH:MM)
    if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time)) {
        $time = '09:00'; // Default to 9:00 AM if invalid
    }
    
    // Validate required fields
    if (empty($report_type)) {
        header('Location: dashboard.php?page=schedules&error=' . urlencode('Report type is required'));
        exit;
    }
    if (empty($frequency)) {
        header('Location: dashboard.php?page=schedules&error=' . urlencode('Frequency is required'));
        exit;
    }
    
    // Validate email recipients if provided
    if (!empty($email_recipients)) {
        $emails = array_map('trim', explode(',', $email_recipients));
        foreach ($emails as $email) {
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                header('Location: dashboard.php?page=schedules&error=' . urlencode('Invalid email address: ' . htmlspecialchars($email)));
                exit;
            }
        }
        // Clean up the emails
        $email_recipients = implode(', ', array_filter($emails, function($e) { 
            return filter_var($e, FILTER_VALIDATE_EMAIL); 
        }));
    }
    
    // Calculate next run time
    $next_run = new DateTime();
    $frequency_normalized = strtolower(trim($frequency));
    if (strpos($frequency_normalized, 'daily') !== false || $frequency_normalized === 'daily') {
        $next_run->modify('+1 day');
    } elseif (strpos($frequency_normalized, 'weekly') !== false || $frequency_normalized === 'weekly') {
        $next_run->modify('+1 week');
    } elseif (strpos($frequency_normalized, 'monthly') !== false || $frequency_normalized === 'monthly') {
        $next_run->modify('+1 month');
    } elseif (strpos($frequency_normalized, 'quarterly') !== false || $frequency_normalized === 'quarterly') {
        $next_run->modify('+3 months');
    } elseif (strpos($frequency_normalized, 'annual') !== false || $frequency_normalized === 'annually') {
        $next_run->modify('+1 year');
    } else {
        $next_run->modify('+1 week');
    }
    
    $report_name = !empty($schedule_name) ? $schedule_name : ucwords(str_replace('_', ' ', $report_type)) . ' Report';
    
    $stmt = $pdo->prepare("
        INSERT INTO report_schedules 
        (created_by, report_type, parameters, schedule_frequency, format, recipients, next_run, is_active, report_name)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $user_id,
        $report_type,
        $parameters,
        $frequency_normalized,
        $format,
        $email_recipients,
        $next_run->format('Y-m-d H:i:s'),
        $is_active,
        $report_name
    ]);
    
    $new_schedule_id = $pdo->lastInsertId();
    Auditor::log($pdo, $user_id, 'create', 'report_schedules', $new_schedule_id, ['action' => 'report_schedule_created']);
    
    header('Location: dashboard.php?page=financial_reports&tab=schedules&success=Schedule+created+successfully');
    exit;
}

function updateSchedule() {
    global $pdo, $user_id;
    
    $schedule_id = $_POST['schedule_id'] ?? 0;
    $schedule_name = trim(strip_tags($_POST['schedule_name'] ?? ''));
    $report_type = $_POST['report_type'] ?? '';
    $frequency = $_POST['frequency'] ?? '';
    $format = $_POST['format'] ?? 'pdf';
    $time = $_POST['time'] ?? '09:00';
    $email_recipients = trim($_POST['email_recipients'] ?? '');
    $parameters = $_POST['parameters'] ?? '';
    $is_active = isset($_POST['is_active']) ? intval($_POST['is_active']) : 1;
    
    // Validate required fields
    if (empty($schedule_id) || empty($report_type) || empty($frequency)) {
        throw new Exception('Missing required fields');
    }
    
    // Validate email format if provided
    if (!empty($email_recipients)) {
        $emails = array_map('trim', explode(',', $email_recipients));
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Invalid email address: ' . $email);
            }
        }
    }
    
    // Verify ownership
    $check = $pdo->prepare("SELECT id FROM report_schedules WHERE id = ? AND created_by = ?");
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
        case 'quarterly':
            $next_run->modify('+3 months');
            break;
        case 'annually':
            $next_run->modify('+1 year');
            break;
        default:
            $next_run->modify('+1 month'); // Default to monthly
    }
    
    // Generate report name if not provided
    $report_name = !empty($schedule_name) ? $schedule_name : ucwords(str_replace('_', ' ', $report_type)) . ' Report';
    
    $stmt = $pdo->prepare("
        UPDATE report_schedules 
        SET report_type = ?, report_name = ?, parameters = ?, schedule_frequency = ?, schedule_time = ?,
            recipients = ?, next_run = ?, is_active = ?, format = ?
        WHERE id = ? AND created_by = ?
    ");
    
    $stmt->execute([
        $report_type,
        $report_name,
        $parameters,
        $frequency,
        $time,
        $email_recipients,
        $next_run->format('Y-m-d H:i:s'),
        $is_active,
        $format,
        $schedule_id,
        $user_id
    ]);
    
    Auditor::log($pdo, $user_id, 'update', 'report_schedules', $schedule_id, ['action' => 'report_schedule_updated']);
    
    echo json_encode(['success' => true, 'message' => 'Schedule updated successfully']);
    exit;
}
