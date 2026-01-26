<?php
/**
 * Audit Logs Export Handler
 * Exports audit logs to CSV format
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';

// Check if user is admin
$user_role = $_SESSION['user_role'] ?? '';
if ($user_role !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

try {
    // Get filters from URL
    $filter_table = $_GET['table'] ?? '';
    $filter_action = $_GET['action'] ?? '';
    $filter_user = $_GET['user'] ?? '';
    
    // Build query with proper parameter binding
    $where = [];
    $params = [];
    
    if (!empty($filter_table)) {
        $where[] = "table_name = ?";
        $params[] = $filter_table;
    }
    
    if (!empty($filter_action)) {
        $where[] = "action_type = ?";
        $params[] = $filter_action;
    }
    
    if (!empty($filter_user)) {
        $where[] = "al.user_id = ?";
        $params[] = $filter_user;
    }
    
    $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
    
    // Get audit logs
    $query = $pdo->prepare("
        SELECT 
            al.id,
            al.table_name,
            al.record_id,
            al.action_type,
            CONCAT(u.first_name, ' ', u.last_name) as user_name,
            u.role as user_role,
            al.created_at
        FROM audit_logs al
        LEFT JOIN users u ON al.user_id = u.id
        $where_clause
        ORDER BY al.created_at DESC
        LIMIT 10000
    ");
    $query->execute($params);
    $logs = $query->fetchAll(PDO::FETCH_ASSOC);
    
    // Set headers for CSV download
    $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Open output stream
    $output = fopen('php://output', 'w');
    
    // Write CSV header
    fputcsv($output, ['ID', 'Table', 'Record ID', 'Action', 'User', 'Role', 'Date/Time']);
    
    // Write data rows
    foreach ($logs as $log) {
        fputcsv($output, [
            $log['id'],
            $log['table_name'],
            $log['record_id'],
            $log['action_type'],
            $log['user_name'] ?: 'Unknown',
            $log['user_role'] ?: 'N/A',
            $log['created_at']
        ]);
    }
    
    fclose($output);
    exit;
    
} catch (PDOException $e) {
    error_log("Audit logs export error: " . $e->getMessage());
    http_response_code(500);
    exit('Export failed');
}
