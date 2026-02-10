<?php
/**
 * Automated Session Reminder Cron Job
 * Sends email reminders to athletes about upcoming sessions
 * Example: 0 18 * * * /usr/bin/php /path/to/cron_session_reminders.php
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/notifications.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY') ?: 'change_this_in_production';
    
    if ($secret_key !== $expected_key) {
        http_response_code(403);
        die('Unauthorized');
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Session Reminders Cron: Starting...\n";

try {
    // Find sessions happening tomorrow
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    echo "Finding sessions for: $tomorrow\n";
    
    $stmt = $pdo->prepare("
        SELECT 
            b.user_id, 
            u.email, 
            u.first_name, 
            u.last_name,
            s.title, 
            s.session_date,
            s.session_time, 
            l.name as location_name,
            l.address as location_address
        FROM bookings b
        JOIN sessions s ON b.session_id = s.id
        JOIN users u ON b.user_id = u.id
        LEFT JOIN locations l ON s.location_id = l.id
        WHERE s.session_date = ?
        AND b.status = 'confirmed'
        ORDER BY s.session_time ASC
    ");
    $stmt->execute([$tomorrow]);
    $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $bookings = decryptUserRows($bookings);
    
    if (empty($bookings)) {
        echo "No sessions scheduled for tomorrow.\n";
        exit(0);
    }
    
    echo "Found " . count($bookings) . " booking(s) to notify.\n";
    
    $sent_count = 0;
    $failed_count = 0;
    $skipped_count = 0;
    
    foreach ($bookings as $booking) {
        try {
            // Check if user has session_reminders preference enabled
            if (!isUserPreferenceEnabled($pdo, $booking['user_id'], 'session_reminders')) {
                $skipped_count++;
                echo "- Skipped {$booking['first_name']} {$booking['last_name']} (notifications disabled)\n";
                continue;
            }
            
            // Format time for display
            $time_formatted = date('g:i A', strtotime($booking['session_time']));
            $location_info = $booking['location_name'] ?? 'TBD';
            if (!empty($booking['location_address'])) {
                $location_info .= ' - ' . $booking['location_address'];
            }
            
            // Send reminder email
            $result = sendEmail(
                $booking['email'], 
                'session_reminder', 
                [
                    'name' => $booking['first_name'],
                    'session_title' => $booking['title'],
                    'date' => date('l, F j, Y', strtotime($booking['session_date'])),
                    'time' => $time_formatted,
                    'location' => $location_info
                ]
            );
            
            if ($result) {
                $sent_count++;
                echo "✓ Sent reminder to {$booking['first_name']} {$booking['last_name']} ({$booking['email']})\n";
            } else {
                $failed_count++;
                echo "✗ Failed to send reminder to {$booking['email']}\n";
            }
            
        } catch (Exception $e) {
            $failed_count++;
            error_log("Session Reminder Error for {$booking['email']}: " . $e->getMessage());
            echo "✗ Error sending to {$booking['email']}: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✓ Sent $sent_count reminder(s), $failed_count failed, $skipped_count skipped (notifications disabled).\n";
    echo "[" . date('Y-m-d H:i:s') . "] Session Reminders Cron: Completed.\n";
    exit(0);
    
} catch (PDOException $e) {
    error_log("Session Reminders Cron Error: " . $e->getMessage());
    echo "✗ Database Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>
