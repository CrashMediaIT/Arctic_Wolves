<?php
/**
 * API v1 - HR Endpoints
 * Provides HR data for ACWolvesAPP.
 *
 * Endpoints:
 *   GET /v1/hr/payroll        - List payroll records
 *   GET /v1/hr/contracts      - List employee contracts
 *   GET /v1/hr/time-tracking  - List time tracking / shifts
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;
$sub_action = $GLOBALS['api_action'] ?? null;

if ($method === 'GET' && $action === 'payroll') {
    handlePayroll($auth);
} elseif ($method === 'GET' && $action === 'contracts') {
    handleContracts($auth);
} elseif ($method === 'GET' && $action === 'time-tracking') {
    handleTimeTracking($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'HR endpoint not found. Use: payroll, contracts, time-tracking']);
}

/**
 * GET /v1/hr/payroll
 */
function handlePayroll($auth) {
    global $pdo;

    if ($auth['user_role'] !== 'admin') {
        apiResponse(403, ['success' => false, 'error' => 'Admin access required']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM payroll_history");
        $count_stmt->execute();
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ph.id, ph.user_id, ph.pay_period_start, ph.pay_period_end, ph.pay_date,
                   ph.hours_worked, ph.gross_pay, ph.total_deductions, ph.net_pay,
                   ph.payment_status, ph.payment_method,
                   u.first_name, u.last_name, u.email
            FROM payroll_history ph
            LEFT JOIN users u ON ph.user_id = u.id
            ORDER BY ph.pay_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($records as &$record) {
            $record['employee_name'] = trim(
                FieldEncryption::decrypt($record['first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($record['last_name'] ?? '')
            );
            unset($record['first_name'], $record['last_name']);
        }
        unset($record);

        logApiAccess('list_payroll', "Listed payroll (page $page)", $auth['user_id']);
        paginatedResponse($records, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API HR ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/hr/contracts
 */
function handleContracts($auth) {
    global $pdo;

    if ($auth['user_role'] !== 'admin') {
        apiResponse(403, ['success' => false, 'error' => 'Admin access required']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM employee_contracts");
        $count_stmt->execute();
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ec.id, ec.employee_name, ec.employee_email, ec.contract_title,
                   ec.status, ec.sent_at, ec.signed_at, ec.created_at
            FROM employee_contracts ec
            ORDER BY ec.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiAccess('list_contracts', "Listed contracts (page $page)", $auth['user_id']);
        paginatedResponse($contracts, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API HR ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/hr/time-tracking
 */
function handleTimeTracking($auth) {
    global $pdo;

    if ($auth['user_role'] !== 'admin') {
        apiResponse(403, ['success' => false, 'error' => 'Admin access required']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM staff_shifts");
        $count_stmt->execute();
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT ss.id, ss.staff_id, ss.shift_date, ss.clock_in, ss.clock_out,
                   ss.total_hours, ss.status, ss.notes,
                   u.first_name, u.last_name, u.email
            FROM staff_shifts ss
            LEFT JOIN users u ON ss.staff_id = u.id
            ORDER BY ss.shift_date DESC, ss.clock_in DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        $shifts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($shifts as &$shift) {
            $shift['staff_name'] = trim(
                FieldEncryption::decrypt($shift['first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($shift['last_name'] ?? '')
            );
            unset($shift['first_name'], $shift['last_name']);
        }
        unset($shift);

        logApiAccess('list_time_tracking', "Listed time tracking (page $page)", $auth['user_id']);
        paginatedResponse($shifts, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API HR ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
