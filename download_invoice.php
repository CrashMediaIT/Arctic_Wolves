<?php
/**
 * Download Invoice - User-accessible invoice download
 * Allows logged-in users to view/download their own invoices.
 * Admins can view any invoice.
 */
session_start();
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/lib/encryption.php';
require_once __DIR__ . '/error_logger.php';

setSecurityHeaders();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$invoice_id = intval($_GET['invoice_id'] ?? 0);
$current_user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? '';

if ($invoice_id <= 0) {
    header("Location: dashboard.php?page=payment_history&error=invalid_invoice");
    exit();
}

try {
    // Get invoice details
    $stmt = $pdo->prepare("
        SELECT i.*, u.first_name, u.last_name, u.email
        FROM invoices i
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$invoice) {
        header("Location: dashboard.php?page=payment_history&error=invoice_not_found");
        exit();
    }

    // Access control: user can only view their own invoices, admins can view all
    if ($user_role !== 'admin' && (int)$invoice['user_id'] !== (int)$current_user_id) {
        // Check if parent viewing managed athlete's invoice
        $is_parent_of = false;
        if ($user_role === 'parent') {
            $parent_check = $pdo->prepare("SELECT 1 FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
            $parent_check->execute([$current_user_id, $invoice['user_id']]);
            $is_parent_of = (bool)$parent_check->fetch();
            if (!$is_parent_of) {
                // Also check parent_athlete_relationships as fallback
                $parent_check2 = $pdo->prepare("SELECT 1 FROM parent_athlete_relationships WHERE parent_id = ? AND athlete_id = ?");
                $parent_check2->execute([$current_user_id, $invoice['user_id']]);
                $is_parent_of = (bool)$parent_check2->fetch();
            }
        }
        if (!$is_parent_of) {
            header("Location: dashboard.php?page=payment_history&error=access_denied");
            exit();
        }
    }

    $invoice = decryptUserRow($invoice);

    // Get line items
    $items_stmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $items_stmt->execute([$invoice_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get business name from settings
    $business_name = 'Arctic Wolves Hockey Training';
    try {
        $biz_stmt = $pdo->query("SELECT setting_value FROM system_settings WHERE setting_key = 'business_name'");
        $biz_val = $biz_stmt->fetchColumn();
        if ($biz_val) $business_name = $biz_val;
    } catch (PDOException $e) { /* use default */ }

    // Generate HTML invoice
    $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Invoice ' . htmlspecialchars($invoice['invoice_number']) . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        .header { border-bottom: 2px solid #6B46C1; padding-bottom: 20px; margin-bottom: 30px; }
        .header h1 { color: #6B46C1; margin: 0; font-size: 28px; }
        .invoice-info { display: flex; justify-content: space-between; margin-bottom: 30px; }
        .invoice-info div { flex: 1; }
        .invoice-info h3 { margin: 0 0 10px 0; font-size: 14px; color: #666; text-transform: uppercase; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: 700; color: #6B46C1; }
        .total-row { font-weight: bold; font-size: 18px; background: #6B46C1; color: white; }
        .status-badge { display: inline-block; padding: 6px 16px; border-radius: 20px; font-weight: 700; font-size: 13px; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-draft { background: #e2e8f0; color: #475569; }
        .status-sent { background: #dbeafe; color: #1e40af; }
        .status-overdue { background: #fce7f3; color: #be123c; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; text-align: center; color: #666; font-size: 13px; }
        .actions { margin-top: 30px; text-align: center; }
        .btn { display: inline-block; padding: 10px 24px; background: #6B46C1; color: white; text-decoration: none; border-radius: 6px; margin: 0 8px; font-weight: 600; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <div class="header">
        <h1>INVOICE</h1>
        <p>' . htmlspecialchars($business_name) . '</p>
    </div>
    
    <div class="invoice-info">
        <div>
            <h3>Invoice To</h3>
            <p><strong>' . htmlspecialchars(($invoice['first_name'] ?? '') . ' ' . ($invoice['last_name'] ?? '')) . '</strong></p>
            <p>' . htmlspecialchars($invoice['email'] ?? '') . '</p>
        </div>
        <div style="text-align: right;">
            <h3>Invoice Details</h3>
            <p><strong>' . htmlspecialchars($invoice['invoice_number']) . '</strong></p>
            <p>Date: ' . date('M j, Y', strtotime($invoice['invoice_date'])) . '</p>
            <p>Status: <span class="status-badge status-' . htmlspecialchars($invoice['status']) . '">' . htmlspecialchars(ucfirst($invoice['status'])) . '</span></p>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: center;">Qty</th>
                <th style="text-align: right;">Unit Price</th>
                <th style="text-align: right;">Total</th>
            </tr>
        </thead>
        <tbody>';

    if (!empty($items)) {
        foreach ($items as $item) {
            $html .= '
            <tr>
                <td>' . htmlspecialchars($item['description']) . '</td>
                <td style="text-align: center;">' . intval($item['quantity']) . '</td>
                <td style="text-align: right;">$' . number_format($item['unit_price'], 2) . '</td>
                <td style="text-align: right;">$' . number_format($item['total_price'], 2) . '</td>
            </tr>';
        }
    } else {
        $html .= '
            <tr>
                <td>Purchase</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: right;">$' . number_format($invoice['total_amount'], 2) . '</td>
                <td style="text-align: right;">$' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>';
    }

    $html .= '
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">Subtotal</td>
                <td style="text-align: right;">$' . number_format($invoice['subtotal'], 2) . '</td>
            </tr>
            <tr>
                <td colspan="3" style="text-align: right; font-weight: bold;">Tax</td>
                <td style="text-align: right;">$' . number_format($invoice['tax_amount'], 2) . '</td>
            </tr>
            <tr class="total-row">
                <td colspan="3" style="text-align: right;">Total</td>
                <td style="text-align: right;">$' . number_format($invoice['total_amount'], 2) . '</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <p>Thank you for your business!</p>
        <p>' . htmlspecialchars($business_name) . '</p>
    </div>
    
    <div class="actions">
        <a href="javascript:window.print()" class="btn">Print</a>
        <a href="dashboard.php?page=payment_history" class="btn" style="background: #666;">Back to Payment History</a>
    </div>
</body>
</html>';

    echo $html;
    exit();

} catch (PDOException $e) {
    ErrorLogger::error("Invoice download error: " . $e->getMessage());
    header("Location: dashboard.php?page=payment_history&error=download_failed");
    exit();
}
