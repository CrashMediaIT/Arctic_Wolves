<?php
/**
 * Notification Helper
 * Creates notifications and sends emails based on user preferences
 */

require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/mailer.php';

/**
 * Create a notification and optionally send email
 */
function createNotification($pdo, $user_id, $type, $title, $message, $link = null, $send_email = true) {
    try {
        // Insert notification in database
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, type, title, message, link)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $type, $title, $message, $link]);
        
        // Check if user has email notifications enabled
        if ($send_email) {
            $user_stmt = $pdo->prepare("SELECT email, first_name, email_notifications FROM users WHERE id = ?");
            $user_stmt->execute([$user_id]);
            $user = $user_stmt->fetch();
            
            if ($user && $user['email_notifications'] == 1) {
                // Send email notification
                sendEmail($user['email'], 'notification', [
                    'name' => $user['first_name'],
                    'title' => $title,
                    'message' => $message,
                    'link' => $link
                ]);
            }
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notify athlete about new practice plan assignment
 */
function notifyPracticePlanAssignment($pdo, $session_id, $practice_plan_id) {
    try {
        // Get all athletes booked for this session
        $stmt = $pdo->prepare("
            SELECT DISTINCT b.user_id, s.title as session_title, pp.title as plan_title, s.session_date
            FROM bookings b
            INNER JOIN sessions s ON b.session_id = s.id
            INNER JOIN practice_plans pp ON s.practice_plan_id = pp.id
            WHERE b.session_id = ? AND b.status = 'paid'
        ");
        $stmt->execute([$session_id]);
        $bookings = $stmt->fetchAll();
        
        foreach ($bookings as $booking) {
            createNotification(
                $pdo,
                $booking['user_id'],
                'practice_plan',
                'New Practice Plan Assigned',
                'A practice plan "' . $booking['plan_title'] . '" has been assigned to your session "' . $booking['session_title'] . '" on ' . date('M d, Y', strtotime($booking['session_date'])),
                'dashboard.php?page=session_detail&id=' . $session_id
            );
        }
    } catch (PDOException $e) {
        error_log("Practice plan notification error: " . $e->getMessage());
    }
}

/**
 * Notify athlete about workout assignment
 */
function notifyWorkoutAssignment($pdo, $user_id, $workout_id, $coach_name) {
    try {
        $stmt = $pdo->prepare("SELECT title FROM workouts WHERE id = ?");
        $stmt->execute([$workout_id]);
        $workout = $stmt->fetch();
        
        if ($workout) {
            createNotification(
                $pdo,
                $user_id,
                'workout',
                'New Workout Assigned',
                'Coach ' . $coach_name . ' has assigned you a new workout: "' . $workout['title'] . '"',
                'dashboard.php?page=workout_builder'
            );
        }
    } catch (PDOException $e) {
        error_log("Workout notification error: " . $e->getMessage());
    }
}

/**
 * Notify athlete about nutrition plan assignment
 */
function notifyNutritionAssignment($pdo, $user_id, $plan_id, $coach_name) {
    try {
        $stmt = $pdo->prepare("SELECT title FROM nutrition_plans WHERE id = ?");
        $stmt->execute([$plan_id]);
        $plan = $stmt->fetch();
        
        if ($plan) {
            createNotification(
                $pdo,
                $user_id,
                'nutrition',
                'New Nutrition Plan Assigned',
                'Coach ' . $coach_name . ' has assigned you a new nutrition plan: "' . $plan['title'] . '"',
                'dashboard.php?page=nutrition_builder'
            );
        }
    } catch (PDOException $e) {
        error_log("Nutrition notification error: " . $e->getMessage());
    }
}

/**
 * Notify athlete about new note
 */
function notifyNewNote($pdo, $user_id, $coach_name, $is_private = false) {
    if (!$is_private) { // Only notify for public notes
        createNotification(
            $pdo,
            $user_id,
            'note',
            'New Note from Coach',
            'Coach ' . $coach_name . ' has added a new note to your profile',
            'dashboard.php?page=profile#notes'
        );
    }
}

/**
 * Notify athlete about video review
 */
function notifyVideoReview($pdo, $video_id, $coach_name) {
    try {
        $stmt = $pdo->prepare("SELECT uploader_id, title FROM videos WHERE id = ?");
        $stmt->execute([$video_id]);
        $video = $stmt->fetch();
        
        if ($video) {
            createNotification(
                $pdo,
                $video['uploader_id'],
                'video_review',
                'Video Reviewed',
                'Coach ' . $coach_name . ' has reviewed your video: "' . $video['title'] . '"',
                'dashboard.php?page=video_library'
            );
        }
    } catch (PDOException $e) {
        error_log("Video review notification error: " . $e->getMessage());
    }
}
