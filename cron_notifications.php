<?php
/**
 * Automated Notification Cron Job
 * Sends session reminder emails to users with upcoming bookings
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/notifications.php';

// Only run via CLI or with secret key
if (php_sapi_name() !== 'cli') {
    $secret_key = $_GET['key'] ?? '';
    $expected_key = getenv('CRON_SECRET_KEY');

    if (empty($expected_key) || !hash_equals($expected_key, $secret_key)) {
        http_response_code(403);
        die('Unauthorized');
    }
}

// 1. Find sessions happening tomorrow
$tomorrow = date('Y-m-d', strtotime('+1 day'));

$stmt = $pdo->prepare("
    SELECT b.user_id, u.email, u.first_name, s.title, s.session_time, s.arena 
    FROM bookings b
    JOIN sessions s ON b.session_id = s.id
    JOIN users u ON b.user_id = u.id
    WHERE s.session_date = ?
");
$stmt->execute([$tomorrow]);
$bookings = $stmt->fetchAll();
$bookings = decryptUserRows($bookings);

$count = 0;
$skipped = 0;
$failed = 0;

foreach ($bookings as $b) {
    // Check if user has session_reminders preference enabled
    if (isUserPreferenceEnabled($pdo, $b['user_id'], 'session_reminders')) {
        try {
            $result = sendEmail($b['email'], 'session_reminder', [
                'name' => $b['first_name'],
                'session_title' => $b['title'],
                'time' => date('g:i A', strtotime($b['session_time'])),
                'location' => $b['arena']
            ]);
            if ($result) {
                $count++;
            } else {
                $failed++;
            }
        } catch (Exception $e) {
            error_log("Session reminder email error for {$b['email']}: " . $e->getMessage());
            $failed++;
        }
    } else {
        $skipped++;
    }
}

echo "Sent $count reminders for sessions on $tomorrow. Skipped $skipped (notifications disabled). Failed $failed.";
?>