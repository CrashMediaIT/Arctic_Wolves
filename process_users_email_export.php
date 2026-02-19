<?php
/**
 * User Email Export Handler
 * Exports a CSV of all user emails
 */

session_start();
require_once 'db_config.php';
require_once 'security.php';
require_once __DIR__ . '/lib/auditor.php';
require_once __DIR__ . '/error_logger.php';

// Check if user is admin
$user_role = $_SESSION['user_role'] ?? '';
if ($user_role !== 'admin') {
    http_response_code(403);
    exit('Access denied');
}

try {
    $stmt = $pdo->prepare("
        SELECT u.first_name, u.last_name, u.email, u.role, u.created_at
        FROM users u
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $users = decryptUserRows($users);

    // Set headers for CSV download
    $filename = 'user_emails_' . date('Y-m-d_His') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Write CSV header
    fputcsv($output, ['First Name', 'Last Name', 'Email', 'Role', 'Created At']);

    // Write data rows
    foreach ($users as $user) {
        fputcsv($output, [
            $user['first_name'] ?? '',
            $user['last_name'] ?? '',
            $user['email'] ?? '',
            $user['role'] ?? '',
            $user['created_at'] ?? ''
        ]);
    }

    fclose($output);
    exit;

} catch (PDOException $e) {
    ErrorLogger::error("User email export error: " . $e->getMessage());
    http_response_code(500);
    exit('Export failed');
}
