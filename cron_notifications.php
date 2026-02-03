<?php
// cron_notifications.php
require 'db_config.php';
require 'mailer.php';
require 'notifications.php';

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

$count = 0;
$skipped = 0;

foreach ($bookings as $b) {
    // Check if user has session_reminders preference enabled
    if (isUserPreferenceEnabled($pdo, $b['user_id'], 'session_reminders')) {
        sendEmail($b['email'], 'session_reminder', [
            'name' => $b['first_name'],
            'session_title' => $b['title'],
            'time' => date('g:i A', strtotime($b['session_time'])),
            'location' => $b['arena']
        ]);
        $count++;
    } else {
        $skipped++;
    }
}

echo "Sent $count reminders for sessions on $tomorrow. Skipped $skipped (notifications disabled).";
?>