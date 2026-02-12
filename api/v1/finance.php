<?php
/**
 * API v1 - Finance Endpoints
 * Provides financial data for ACWolvesAPP.
 *
 * Endpoints:
 *   GET /v1/finance/overview       - Financial overview / summary
 *   GET /v1/finance/transactions   - List transactions
 *   GET /v1/finance/billing        - Billing / invoices
 */

require_once __DIR__ . '/../api_auth.php';

$auth = requireApiAuth();
$method = $GLOBALS['api_method'];
$action = $GLOBALS['api_resource_id'] ?? null;

if ($method === 'GET' && $action === 'overview') {
    handleFinanceOverview($auth);
} elseif ($method === 'GET' && $action === 'transactions') {
    handleListTransactions($auth);
} elseif ($method === 'GET' && $action === 'billing') {
    handleBilling($auth);
} elseif ($method === 'GET' && !$action) {
    handleFinanceOverview($auth);
} else {
    apiResponse(404, ['success' => false, 'error' => 'Finance endpoint not found. Use: overview, transactions, billing']);
}

/**
 * GET /v1/finance/overview
 */
function handleFinanceOverview($auth) {
    global $pdo;

    if ($auth['user_role'] !== 'admin') {
        apiResponse(403, ['success' => false, 'error' => 'Admin access required']);
    }

    try {
        $overview = [];

        // Total revenue (last 30 days)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) FROM transactions
            WHERE status = 'completed' AND transaction_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute();
        $overview['revenue_30d'] = (float) $stmt->fetchColumn();

        // Total revenue (current month)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_amount), 0) FROM transactions
            WHERE status = 'completed' AND MONTH(transaction_date) = MONTH(NOW()) AND YEAR(transaction_date) = YEAR(NOW())
        ");
        $stmt->execute();
        $overview['revenue_month'] = (float) $stmt->fetchColumn();

        // Total expenses (current month)
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(amount), 0) FROM expenses
            WHERE status = 'approved' AND MONTH(expense_date) = MONTH(NOW()) AND YEAR(expense_date) = YEAR(NOW())
        ");
        $stmt->execute();
        $overview['expenses_month'] = (float) $stmt->fetchColumn();

        // Outstanding payments
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE status = 'pending'");
        $stmt->execute();
        $overview['pending_transactions'] = (int) $stmt->fetchColumn();

        // Active bookings value
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount_paid), 0) FROM bookings WHERE payment_status = 'paid'");
        $stmt->execute();
        $overview['total_paid_bookings'] = (float) $stmt->fetchColumn();

        logApiAccess('finance_overview', 'Viewed finance overview', $auth['user_id']);
        apiResponse(200, ['success' => true, 'data' => $overview]);
    } catch (PDOException $e) {
        error_log('[API FINANCE ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/finance/transactions
 */
function handleListTransactions($auth) {
    global $pdo;

    if ($auth['user_role'] !== 'admin') {
        apiResponse(403, ['success' => false, 'error' => 'Admin access required']);
    }

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions");
        $count_stmt->execute();
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT t.id, t.user_id, t.transaction_type, t.amount, t.hst_amount, t.total_amount,
                   t.payment_method, t.transaction_date, t.description, t.status,
                   u.first_name, u.last_name, u.email
            FROM transactions t
            LEFT JOIN users u ON t.user_id = u.id
            ORDER BY t.transaction_date DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$per_page, $offset]);
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($transactions as &$txn) {
            $txn['user_name'] = trim(
                FieldEncryption::decrypt($txn['first_name'] ?? '') . ' ' .
                FieldEncryption::decrypt($txn['last_name'] ?? '')
            );
            unset($txn['first_name'], $txn['last_name']);
        }
        unset($txn);

        logApiAccess('list_transactions', "Listed transactions (page $page)", $auth['user_id']);
        paginatedResponse($transactions, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API FINANCE ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}

/**
 * GET /v1/finance/billing
 */
function handleBilling($auth) {
    global $pdo;

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $per_page = min(100, max(1, (int) ($_GET['per_page'] ?? 20)));
    $offset = ($page - 1) * $per_page;

    $where = [];
    $params = [];

    // Non-admin users see only their own payments
    if ($auth['user_role'] !== 'admin') {
        $where[] = 'p.user_id = ?';
        $params[] = $auth['user_id'];
    }

    $where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    try {
        $count_stmt = $pdo->prepare("SELECT COUNT(*) FROM payments p $where_sql");
        $count_stmt->execute($params);
        $total = (int) $count_stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT p.id, p.user_id, p.amount, p.payment_method, p.payment_date,
                   p.payment_status, p.notes
            FROM payments p
            $where_sql
            ORDER BY p.payment_date DESC
            LIMIT ? OFFSET ?
        ");
        $all_params = array_merge($params, [$per_page, $offset]);
        $stmt->execute($all_params);
        $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        logApiAccess('list_billing', "Listed billing (page $page)", $auth['user_id']);
        paginatedResponse($payments, $total, $page, $per_page);
    } catch (PDOException $e) {
        error_log('[API FINANCE ERROR] ' . $e->getMessage());
        apiResponse(500, ['success' => false, 'error' => 'Internal server error']);
    }
}
