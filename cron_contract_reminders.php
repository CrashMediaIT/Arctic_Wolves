<?php
/**
 * Contract Renewal Reminder Cron Job
 * Sends email reminders to admins and accounting department
 * at 60, 30, and 15 days before contract renewal dates
 * Example: 0 8 * * * /usr/bin/php /path/to/cron_contract_reminders.php
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY') ?: 'change_this_in_production';
    
    if ($secret_key !== $expected_key) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Contract Renewal Reminders: Starting...\n";

try {
    $today = new DateTime();
    $reminders_sent = 0;
    
    // Get all active recurring expenses with renewal dates
    $stmt = $pdo->query("
        SELECT * FROM recurring_expenses 
        WHERE status = 'active' 
        AND renewal_date IS NOT NULL 
        AND renewal_date >= CURDATE()
        ORDER BY renewal_date ASC
    ");
    $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($contracts) . " active contracts with upcoming renewals\n";
    
    // Get admin and accounting email addresses for notifications
    $admin_stmt = $pdo->query("SELECT id, email, first_name, last_name FROM users WHERE role = 'admin' AND is_verified = 1");
    $admins = $admin_stmt->fetchAll(PDO::FETCH_ASSOC);
    $admins = decryptUserRows($admins);
    
    if (empty($admins)) {
        echo "No admin users found to send reminders to. Exiting.\n";
        exit(0);
    }
    
    foreach ($contracts as $contract) {
        $renewal_date = new DateTime($contract['renewal_date']);
        $days_until = $today->diff($renewal_date)->days;
        $is_future = $renewal_date > $today;
        
        if (!$is_future) continue;
        
        $reminder_type = null;
        $reminder_field = null;
        
        // Check 60-day reminder
        if ($days_until <= 60 && !$contract['reminder_60_sent']) {
            $reminder_type = '60-day';
            $reminder_field = 'reminder_60_sent';
        }
        // Check 30-day reminder
        elseif ($days_until <= 30 && !$contract['reminder_30_sent']) {
            $reminder_type = '30-day';
            $reminder_field = 'reminder_30_sent';
        }
        // Check 15-day reminder
        elseif ($days_until <= 15 && !$contract['reminder_15_sent']) {
            $reminder_type = '15-day';
            $reminder_field = 'reminder_15_sent';
        }
        
        if ($reminder_type && $reminder_field) {
            echo "Sending $reminder_type reminder for: {$contract['vendor_name']} (renewal: {$contract['renewal_date']})\n";
            
            $urgency = $days_until <= 15 ? 'URGENT' : ($days_until <= 30 ? 'IMPORTANT' : 'NOTICE');
            
            // Send reminder to all admins
            foreach ($admins as $admin) {
                $subject = "[$urgency] Contract Renewal - {$contract['vendor_name']} - $days_until days remaining";
                
                $body = "<h2>Contract Renewal Reminder</h2>";
                $body .= "<p><strong>$urgency:</strong> The following contract is due for renewal in <strong>$days_until days</strong>.</p>";
                $body .= "<table style='border-collapse:collapse; width:100%;'>";
                $body .= "<tr><td style='padding:8px; border:1px solid #ddd; font-weight:bold;'>Vendor</td><td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($contract['vendor_name']) . "</td></tr>";
                $body .= "<tr><td style='padding:8px; border:1px solid #ddd; font-weight:bold;'>Type</td><td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($contract['contract_type'] ?? 'N/A') . "</td></tr>";
                $body .= "<tr><td style='padding:8px; border:1px solid #ddd; font-weight:bold;'>Amount</td><td style='padding:8px; border:1px solid #ddd;'>$" . number_format($contract['amount'], 2) . " (" . ucfirst(str_replace('_', ' ', $contract['frequency'])) . ")</td></tr>";
                $body .= "<tr><td style='padding:8px; border:1px solid #ddd; font-weight:bold;'>Renewal Date</td><td style='padding:8px; border:1px solid #ddd;'>" . date('F j, Y', strtotime($contract['renewal_date'])) . "</td></tr>";
                $body .= "<tr><td style='padding:8px; border:1px solid #ddd; font-weight:bold;'>Auto-Renew</td><td style='padding:8px; border:1px solid #ddd;'>" . ($contract['auto_renew'] ? 'Yes' : 'No') . "</td></tr>";
                if ($contract['description']) {
                    $body .= "<tr><td style='padding:8px; border:1px solid #ddd; font-weight:bold;'>Description</td><td style='padding:8px; border:1px solid #ddd;'>" . htmlspecialchars($contract['description']) . "</td></tr>";
                }
                $body .= "</table>";
                $body .= "<p style='margin-top:16px;'>Please review this contract and take appropriate action before the renewal date.</p>";
                $body .= "<p><a href='" . (getenv('APP_URL') ?: 'https://arcticwolves.ca') . "/dashboard.php?page=expenses&expenses_tab=recurring'>View Recurring Expenses</a></p>";
                
                try {
                    sendEmail($admin['email'], 'custom', [
                        'name' => $admin['first_name'],
                        'subject' => $subject,
                        'body' => $body
                    ]);
                    $reminders_sent++;
                    echo "  -> Sent to: {$admin['email']}\n";
                } catch (Exception $e) {
                    echo "  -> ERROR sending to {$admin['email']}: {$e->getMessage()}\n";
                }
            }
            
            // Mark reminder as sent (whitelist validation for column name)
            $valid_fields = ['reminder_60_sent', 'reminder_30_sent', 'reminder_15_sent'];
            if (in_array($reminder_field, $valid_fields, true)) {
                $update_stmt = $pdo->prepare("UPDATE recurring_expenses SET $reminder_field = 1 WHERE id = ?");
                $update_stmt->execute([$contract['id']]);
                echo "  -> Marked $reminder_field as sent for contract #{$contract['id']}\n";
            }
        }
    }
    
    // Also check for expired contracts and update status
    $expired_stmt = $pdo->prepare("
        UPDATE recurring_expenses 
        SET status = 'expired' 
        WHERE status = 'active' 
        AND contract_end_date IS NOT NULL 
        AND contract_end_date < CURDATE()
        AND auto_renew = 0
    ");
    $expired_stmt->execute();
    $expired_count = $expired_stmt->rowCount();
    
    if ($expired_count > 0) {
        echo "Marked $expired_count contracts as expired\n";
    }
    
    echo "[" . date('Y-m-d H:i:s') . "] Contract Renewal Reminders: Complete. Sent $reminders_sent reminders.\n";
    
} catch (Exception $e) {
    echo "[ERROR] " . $e->getMessage() . "\n";
    error_log("Contract renewal cron error: " . $e->getMessage());
    exit(1);
}
?>
