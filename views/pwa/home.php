<?php
/**
 * PWA Home - Mobile-native dashboard
 * Purpose-built for mobile phones, not a desktop adaptation.
 */

$greeting = 'Good ' . (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening'));
$today = date('l, M j');

// Unread notifications
$unreadCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_status = 0");
    $stmt->execute([$user_id]);
    $unreadCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) { $unreadCount = 0; }

// Upcoming sessions (next 5)
$upcomingSessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT s.id, s.title, s.session_date, s.session_time, s.duration_minutes,
               s.status, s.arena, s.session_type, s.coach_id
        FROM sessions s
        WHERE s.session_date >= CURDATE() AND s.status = 'scheduled'
        ORDER BY s.session_date ASC, s.session_time ASC
        LIMIT 5
    ");
    $stmt->execute();
    $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $upcomingSessions = []; }

// Also include registered training session template dates
try {
    if ($isAnyCoach) {
        $tplStmt = $pdo->prepare("
            SELECT tst.id, tst.name as title, DATE(tsd.session_date) as session_date,
                   TIME(tsd.session_date) as session_time, tst.duration_minutes,
                   'scheduled' as status, NULL as arena, tst.session_type, tst.coach_id
            FROM training_session_templates tst
            INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
            WHERE tst.is_active = 1 AND tsd.session_date >= CURDATE()
            ORDER BY tsd.session_date ASC
            LIMIT 5
        ");
        $tplStmt->execute();
    } else {
        // For athletes: only show template sessions they are registered for
        $tplStmt = $pdo->prepare("
            SELECT tst.id, tst.name as title, DATE(tsd.session_date) as session_date,
                   TIME(tsd.session_date) as session_time, tst.duration_minutes,
                   'scheduled' as status, NULL as arena, tst.session_type, tst.coach_id
            FROM training_session_templates tst
            INNER JOIN training_session_dates tsd ON tsd.template_id = tst.id AND tsd.is_active = 1
            INNER JOIN session_date_athletes sda ON sda.session_date_id = tsd.id AND sda.athlete_id = ?
            WHERE tst.is_active = 1 AND tsd.session_date >= CURDATE()
            ORDER BY tsd.session_date ASC
            LIMIT 5
        ");
        $tplStmt->execute([$user_id]);
    }
    $tplSessions = $tplStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($tplSessions)) {
        $upcomingSessions = array_merge($upcomingSessions, $tplSessions);
        usort($upcomingSessions, function($a, $b) {
            return strtotime(($a['session_date'] ?? '') . ' ' . ($a['session_time'] ?? '00:00'))
                 - strtotime(($b['session_date'] ?? '') . ' ' . ($b['session_time'] ?? '00:00'));
        });
        $upcomingSessions = array_slice($upcomingSessions, 0, 5);
    }
} catch (PDOException $e) { /* Template tables may not exist yet */ }

// Role-specific stats
$sessionsCompleted = 0;
$activeGoals = 0;
$pendingVideos = 0;
$todaySessions = 0;
$performanceStats = ['sessions_completed' => 0, 'videos_reviewed' => 0, 'active_goals' => 0];
$goalsMetrics = ['total_goals' => 0, 'completed_goals' => 0, 'active_goals' => 0, 'avg_progress' => 0];
$skillsMetrics = ['total_skills' => 0, 'avg_score' => 0, 'last_evaluation' => null];
$recentGoals = [];
$pendingPayments = [];
$notifications = [];
$coachNotes = [];
$pendingReviews = [];
$athleteUpdates = [];
$parentAthletes = [];

try {
    if ($isAnyCoach) {
        // Coach: today's sessions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM sessions WHERE coach_id = ? AND session_date = CURDATE() AND status = 'scheduled'");
        $stmt->execute([$user_id]);
        $todaySessions = (int)$stmt->fetchColumn();

        // Pending video reviews
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM videos WHERE coach_id = ? AND status = 'pending_review'");
            $stmt->execute([$user_id]);
            $pendingVideos = (int)$stmt->fetchColumn();
        } catch (PDOException $e) { $pendingVideos = 0; }

        // Pending video reviews with athlete names
        try {
            $stmt = $pdo->prepare("
                SELECT v.id, v.title, v.status, v.created_at, v.athlete_id,
                       u.first_name as athlete_first_name, u.last_name as athlete_last_name
                FROM videos v
                LEFT JOIN users u ON v.athlete_id = u.id
                WHERE v.coach_id = ? AND v.status = 'pending_review'
                ORDER BY v.created_at ASC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $pendingReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $pendingReviews = decryptUserRows($pendingReviews);
        } catch (PDOException $e) { $pendingReviews = []; }

        // Athlete notifications (injuries, absences)
        try {
            $stmt = $pdo->prepare("
                SELECT n.*, u.first_name as athlete_first_name, u.last_name as athlete_last_name
                FROM notifications n
                LEFT JOIN users u ON n.user_id = u.id
                WHERE n.type IN ('injury', 'absence', 'alert')
                ORDER BY n.created_at DESC
                LIMIT 5
            ");
            $stmt->execute();
            $athleteUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $athleteUpdates = decryptUserRows($athleteUpdates);
        } catch (PDOException $e) { $athleteUpdates = []; }
    } elseif ($user_role === 'parent') {
        // Parent: get associated athletes
        try {
            $stmt = $pdo->prepare("
                SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
                FROM users u
                INNER JOIN managed_athletes ma ON u.id = ma.athlete_id AND ma.parent_id = ?
                ORDER BY u.last_name ASC, u.first_name ASC
            ");
            $stmt->execute([$user_id]);
            $parentAthletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $parentAthletes = decryptUserRows($parentAthletes);
        } catch (PDOException $e) { $parentAthletes = []; }
    } else {
        // Athlete: completed sessions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed'");
        $stmt->execute([$user_id]);
        $sessionsCompleted = (int)$stmt->fetchColumn();

        // Active goals
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM goals WHERE athlete_id = ? AND status = 'active'");
        $stmt->execute([$user_id]);
        $activeGoals = (int)$stmt->fetchColumn();

        // Performance stats
        try {
            $stmt = $pdo->prepare("
                SELECT
                    (SELECT COUNT(*) FROM bookings b JOIN sessions s ON b.session_id = s.id
                     WHERE b.user_id = ? AND s.status = 'completed') as sessions_completed,
                    (SELECT COUNT(*) FROM videos WHERE athlete_id = ? AND status = 'reviewed') as videos_reviewed,
                    (SELECT COUNT(*) FROM performance_goals WHERE user_id = ? AND status = 'active') as active_goals
            ");
            $stmt->execute([$user_id, $user_id, $user_id]);
            $performanceStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $performanceStats;
        } catch (PDOException $e) { /* keep defaults */ }

        // Goals metrics
        try {
            $goalsStmt = $pdo->prepare("
                SELECT
                    COUNT(*) as total_goals,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_goals,
                    AVG(CASE WHEN status = 'active' AND completion_percentage IS NOT NULL THEN completion_percentage ELSE NULL END) as avg_progress
                FROM goals
                WHERE athlete_id = ?
            ");
            $goalsStmt->execute([$user_id]);
            $goalsMetrics = $goalsStmt->fetch(PDO::FETCH_ASSOC) ?: $goalsMetrics;
        } catch (PDOException $e) { /* keep defaults */ }

        // Recent active goals
        try {
            $recentGoalsStmt = $pdo->prepare("
                SELECT id, COALESCE(title, goal_title) as title,
                       COALESCE(completion_percentage, 0) as progress,
                       target_date, status
                FROM goals
                WHERE athlete_id = ? AND status = 'active'
                ORDER BY created_at DESC
                LIMIT 3
            ");
            $recentGoalsStmt->execute([$user_id]);
            $recentGoals = $recentGoalsStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $recentGoals = []; }

        // Skill evaluations summary
        try {
            $skillsStmt = $pdo->prepare("
                SELECT
                    COUNT(DISTINCT skill_id) as total_skills,
                    AVG(score) as avg_score,
                    MAX(evaluation_date) as last_evaluation
                FROM evaluation_scores
                WHERE athlete_id = ?
            ");
            $skillsStmt->execute([$user_id]);
            $skillsMetrics = $skillsStmt->fetch(PDO::FETCH_ASSOC) ?: $skillsMetrics;
        } catch (PDOException $e) { /* keep defaults */ }

        // Pending payments
        try {
            $pendingStmt = $pdo->prepare("
                SELECT b.id as booking_id, b.amount_due, b.created_at as assigned_at,
                       s.id as session_id, s.title as session_title,
                       s.session_date, s.start_time, s.duration_minutes,
                       COALESCE(l.name, s.arena) as location_name
                FROM bookings b
                JOIN sessions s ON b.session_id = s.id
                LEFT JOIN locations l ON s.location_id = l.id
                WHERE b.user_id = ?
                  AND b.status = 'pending'
                  AND b.payment_status = 'pending'
                  AND s.session_date >= CURDATE()
                ORDER BY s.session_date ASC, s.start_time ASC
                LIMIT 5
            ");
            $pendingStmt->execute([$user_id]);
            $pendingPayments = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $pendingPayments = []; }

        // Unread notifications
        try {
            $stmt = $pdo->prepare("
                SELECT * FROM notifications
                WHERE user_id = ? AND is_read = 0
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) { $notifications = []; }

        // Coach notes / feedback
        try {
            $stmt = $pdo->prepare("
                SELECT vr.*, u.first_name as coach_first_name, u.last_name as coach_last_name
                FROM video_reviews vr
                LEFT JOIN users u ON vr.coach_id = u.id
                WHERE vr.athlete_id = ?
                ORDER BY vr.created_at DESC
                LIMIT 5
            ");
            $stmt->execute([$user_id]);
            $coachNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $coachNotes = decryptUserRows($coachNotes);
        } catch (PDOException $e) { $coachNotes = []; }
    }
} catch (PDOException $e) { /* fallback to zeros */ }

// System notifications
$sysNotifs = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, message, notification_type, end_date
        FROM system_notifications
        WHERE is_active = 1
          AND (start_date IS NULL OR start_date <= NOW())
          AND (end_date IS NULL OR end_date >= NOW())
        ORDER BY
            CASE notification_type
                WHEN 'alert' THEN 1
                WHEN 'maintenance' THEN 2
                WHEN 'warning' THEN 3
                ELSE 4
            END,
            created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $sysNotifs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $sysNotifs = []; }
?>
<style>
.m-home { padding: 16px; font-family: Inter, sans-serif; }
.m-greeting {
    background: linear-gradient(135deg, #6B46C1, #8B5CF6);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 16px;
}
.m-greeting-name { font-size: 20px; font-weight: 700; color: #fff; margin: 0; }
.m-greeting-date { font-size: 13px; color: rgba(255,255,255,0.7); margin: 4px 0 0; }
.m-greeting-notif {
    display: inline-flex; align-items: center; gap: 6px;
    margin-top: 12px; padding: 6px 12px;
    background: rgba(255,255,255,0.15); border-radius: 20px;
    color: #fff; font-size: 12px; font-weight: 500;
    text-decoration: none;
}
.m-stat-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px; }
.m-stat {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 16px; text-align: center;
}
.m-stat-value { font-size: 28px; font-weight: 700; color: #fff; }
.m-stat-label { font-size: 11px; color: #A8A8B8; margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; }
.m-stat-icon { font-size: 16px; margin-bottom: 6px; }
.m-quick-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 20px; }
.m-quick-btn {
    display: flex; flex-direction: column; align-items: center; gap: 6px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px 8px; text-decoration: none;
    min-height: 44px; min-width: 44px;
}
.m-quick-btn i { font-size: 18px; color: #8B5CF6; }
.m-quick-btn span { font-size: 10px; color: #A8A8B8; font-weight: 500; text-align: center; }
.m-section-title {
    font-size: 15px; font-weight: 600; color: #fff;
    margin: 0 0 12px; padding: 0;
}
.m-session-item {
    display: flex; align-items: center; gap: 12px;
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 12px; margin-bottom: 8px;
    text-decoration: none; min-height: 44px;
}
.m-session-date {
    min-width: 44px; text-align: center;
    background: rgba(107,70,193,0.15); border-radius: 10px;
    padding: 8px 6px;
}
.m-session-date-month { font-size: 10px; color: #8B5CF6; text-transform: uppercase; font-weight: 600; display: block; }
.m-session-date-day { font-size: 18px; color: #fff; font-weight: 700; display: block; line-height: 1.1; }
.m-session-info { flex: 1; min-width: 0; }
.m-session-title { font-size: 14px; color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.m-session-meta { font-size: 12px; color: #A8A8B8; margin-top: 2px; }
.m-session-badge {
    font-size: 10px; padding: 3px 8px; border-radius: 6px; font-weight: 600;
    background: rgba(16,185,129,0.15); color: #10B981;
}
.m-alert {
    border-radius: 10px; padding: 12px; margin-bottom: 8px;
    display: flex; align-items: flex-start; gap: 10px;
    font-size: 13px; color: #fff;
}
.m-alert-info { background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.25); }
.m-alert-warning { background: rgba(245,158,11,0.12); border: 1px solid rgba(245,158,11,0.25); }
.m-alert-alert { background: rgba(239,68,68,0.12); border: 1px solid rgba(239,68,68,0.25); }
.m-alert-maintenance { background: rgba(251,191,36,0.12); border: 1px solid rgba(251,191,36,0.25); }
.m-alert i { margin-top: 2px; }
.m-alert-body { flex: 1; min-width: 0; }
.m-alert-dismiss {
    background: transparent; border: none; color: #64748b; cursor: pointer;
    padding: 8px; font-size: 14px; flex-shrink: 0; min-height: 44px; min-width: 44px;
    display: flex; align-items: center; justify-content: center; transition: color 0.2s;
}
.m-alert-dismiss:active { color: #fff; }
.m-empty { text-align: center; padding: 24px; color: #6B6B7B; font-size: 13px; }
.m-card {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 12px;
    padding: 14px; margin-bottom: 12px;
}
.m-card-header {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 10px;
}
.m-card-header h4 {
    font-size: 14px; font-weight: 700; color: #fff; margin: 0;
    display: flex; align-items: center; gap: 8px;
}
.m-card-header h4 i { color: #8B5CF6; font-size: 14px; }
.m-card-header a {
    font-size: 11px; color: #8B5CF6; text-decoration: none; font-weight: 600;
}
.m-card-divider {
    border: none; border-top: 1px solid #2D2D3F; margin: 10px 0;
}
/* Pending payments */
.m-payment-alert {
    background: rgba(245,158,11,0.08); border: 1px solid rgba(245,158,11,0.25);
    border-radius: 12px; padding: 14px; margin-bottom: 12px;
}
.m-payment-header {
    display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
    padding-bottom: 10px; border-bottom: 1px solid rgba(245,158,11,0.15);
}
.m-payment-header i { color: #F59E0B; font-size: 18px; }
.m-payment-header div { flex: 1; }
.m-payment-header strong { font-size: 14px; color: #fff; display: block; }
.m-payment-header span { font-size: 11px; color: #F59E0B; }
.m-payment-item {
    background: #16161F; border-radius: 10px; padding: 10px 12px; margin-bottom: 8px;
}
.m-payment-item:last-child { margin-bottom: 0; }
.m-payment-item-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-payment-item-meta { font-size: 11px; color: #A8A8B8; margin-top: 2px; }
.m-payment-item-row {
    display: flex; justify-content: space-between; align-items: center; margin-top: 8px;
}
.m-payment-amount { font-size: 16px; font-weight: 700; color: #F59E0B; }
.m-pay-btn {
    display: inline-flex; align-items: center; gap: 4px;
    background: #F59E0B; color: #000; font-size: 11px; font-weight: 700;
    padding: 6px 12px; border-radius: 6px; text-decoration: none;
    min-height: 32px;
}
/* Metrics grid (2x2) */
.m-metrics-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.m-metric {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px; display: flex; align-items: center; gap: 10px;
}
.m-metric-icon {
    width: 36px; height: 36px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center; font-size: 14px; flex-shrink: 0;
}
.m-metric-icon.goals { background: rgba(16,185,129,0.15); color: #10B981; }
.m-metric-icon.progress { background: rgba(59,130,246,0.15); color: #3B82F6; }
.m-metric-icon.skills { background: rgba(245,158,11,0.15); color: #F59E0B; }
.m-metric-icon.active { background: rgba(139,92,246,0.15); color: #8B5CF6; }
.m-metric-value { font-size: 18px; font-weight: 700; color: #fff; line-height: 1.1; }
.m-metric-label { font-size: 10px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 1px; }
/* Goals progress */
.m-goal-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px; background: #0D0D14; border-radius: 8px; margin-bottom: 6px; gap: 10px;
}
.m-goal-item:last-child { margin-bottom: 0; }
.m-goal-info { flex: 1; min-width: 0; }
.m-goal-title {
    font-size: 13px; font-weight: 600; color: #fff;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}
.m-goal-date { font-size: 10px; color: #6B6B7B; margin-top: 1px; display: block; }
.m-goal-progress { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.m-progress-bar {
    width: 60px; height: 5px; background: #2D2D3F; border-radius: 3px; overflow: hidden;
}
.m-progress-fill {
    height: 100%; background: linear-gradient(90deg, #6B46C1, #8B5CF6); border-radius: 3px;
}
.m-progress-text { font-size: 11px; font-weight: 700; color: #8B5CF6; min-width: 30px; text-align: right; }
/* Stats overview row */
.m-stats-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 8px; margin-bottom: 12px; }
.m-stats-item {
    background: #16161F; border: 1px solid #2D2D3F; border-radius: 10px;
    padding: 12px 8px; text-align: center;
}
.m-stats-item-icon { font-size: 16px; margin-bottom: 4px; }
.m-stats-item-value { font-size: 20px; font-weight: 700; color: #fff; }
.m-stats-item-label { font-size: 9px; color: #A8A8B8; text-transform: uppercase; letter-spacing: 0.3px; margin-top: 2px; }
/* Notification list items */
.m-notif-item {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px; background: #0D0D14; border-radius: 8px; margin-bottom: 6px;
}
.m-notif-item:last-child { margin-bottom: 0; }
.m-notif-dot {
    width: 8px; height: 8px; border-radius: 50%; background: #8B5CF6;
    flex-shrink: 0; margin-top: 5px;
}
.m-notif-text { font-size: 13px; color: #fff; flex: 1; line-height: 1.4; }
.m-notif-time { font-size: 10px; color: #6B6B7B; display: block; margin-top: 2px; }
/* Coach notes */
.m-note-item {
    padding: 10px; background: #0D0D14; border-radius: 8px; margin-bottom: 6px;
}
.m-note-item:last-child { margin-bottom: 0; }
.m-note-header { display: flex; justify-content: space-between; margin-bottom: 4px; }
.m-note-coach { font-size: 12px; font-weight: 600; color: #8B5CF6; }
.m-note-date { font-size: 10px; color: #6B6B7B; }
.m-note-text { font-size: 12px; color: #A8A8B8; line-height: 1.4; margin: 0; }
/* Coach: review items */
.m-review-item {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px; background: #0D0D14; border-radius: 8px; margin-bottom: 6px;
}
.m-review-item:last-child { margin-bottom: 0; }
.m-review-name { font-size: 13px; font-weight: 600; color: #fff; }
.m-review-type { font-size: 11px; color: #A8A8B8; }
.m-review-btn {
    font-size: 11px; font-weight: 700; color: #fff; background: #8B5CF6;
    padding: 5px 10px; border-radius: 6px; text-decoration: none;
    min-height: 28px; display: inline-flex; align-items: center;
}
/* Coach: athlete alerts */
.m-athlete-alert {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 10px; background: #0D0D14; border-radius: 8px; margin-bottom: 6px;
}
.m-athlete-alert:last-child { margin-bottom: 0; }
.m-athlete-alert-icon {
    width: 28px; height: 28px; border-radius: 50%; flex-shrink: 0;
    display: flex; align-items: center; justify-content: center; font-size: 12px;
    background: rgba(239,68,68,0.15); color: #EF4444;
}
.m-athlete-alert-content { flex: 1; }
.m-athlete-alert-name { font-size: 13px; font-weight: 600; color: #fff; display: block; }
.m-athlete-alert-msg { font-size: 12px; color: #A8A8B8; margin: 2px 0 0; }
.m-athlete-alert-time { font-size: 10px; color: #6B6B7B; }
/* Parent: athlete selector */
.m-select-wrap { margin-bottom: 16px; }
.m-select {
    width: 100%; height: 44px; background: #16161F; border: 1px solid #2D2D3F;
    color: #fff; padding: 0 12px; border-radius: 10px; font-size: 14px;
    font-family: Inter, sans-serif; appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23A8A8B8' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat; background-position: right 12px center;
}
.m-select:focus { outline: none; border-color: #8B5CF6; }
#m-athlete-dashboard { display: none; }
.m-parent-sessions, .m-parent-stats { min-height: 40px; }
</style>

<div class="m-home">
    <!-- Greeting Card -->
    <div class="m-greeting">
        <?php $firstName = explode(' ', trim($user_name ?: 'Guest'))[0]; ?>
        <p class="m-greeting-name" id="pwa-greeting"><?= htmlspecialchars($greeting) ?>, <?= htmlspecialchars($firstName) ?>!</p>
        <p class="m-greeting-date" id="pwa-greeting-date"><?= $today ?></p>
        <?php if ($unreadCount > 0): ?>
        <a href="?page=notifications" class="m-greeting-notif">
            <i class="fas fa-bell"></i> <?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?>
        </a>
        <?php endif; ?>
    </div>

    <!-- System Alerts -->
    <?php foreach ($sysNotifs as $sn):
        $aType = $sn['notification_type'] ?? 'info';
        $aIcon = match($aType) {
            'warning' => 'fa-exclamation-triangle',
            'alert' => 'fa-circle-exclamation',
            'maintenance' => 'fa-wrench',
            default => 'fa-info-circle',
        };
        $aColor = match($aType) {
            'warning' => '#F59E0B',
            'alert' => '#EF4444',
            'maintenance' => '#FBBF24',
            default => '#3B82F6',
        };
    ?>
    <div class="m-alert m-alert-<?= $aType ?>" id="pwa-sys-alert-<?= (int)$sn['id'] ?>">
        <i class="fas <?= $aIcon ?>" style="color:<?= $aColor ?>"></i>
        <div class="m-alert-body">
            <strong style="font-size:13px;"><?= htmlspecialchars($sn['title']) ?></strong>
            <div style="font-size:12px;color:#A8A8B8;margin-top:2px;"><?= htmlspecialchars($sn['message']) ?></div>
            <?php if (!empty($sn['end_date'])): ?>
                <div style="font-size:11px;color:#64748b;margin-top:4px;">Until <?= date('M j, Y g:i A', strtotime($sn['end_date'])) ?></div>
            <?php endif; ?>
        </div>
        <button class="m-alert-dismiss" aria-label="Dismiss notification: <?= htmlspecialchars($sn['title']) ?>" data-notif-id="<?= (int)$sn['id'] ?>">
            <i class="fas fa-times"></i>
        </button>
    </div>
    <?php endforeach; ?>

    <!-- KPI Stats Grid -->
    <div class="m-stat-grid">
        <?php if ($isAnyCoach): ?>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#8B5CF6;"><i class="fas fa-calendar-day"></i></div>
                <div class="m-stat-value"><?= $todaySessions ?></div>
                <div class="m-stat-label">Today's Sessions</div>
            </div>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#F59E0B;"><i class="fas fa-video"></i></div>
                <div class="m-stat-value"><?= $pendingVideos ?></div>
                <div class="m-stat-label">Video Reviews</div>
            </div>
        <?php else: ?>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#10B981;"><i class="fas fa-check-circle"></i></div>
                <div class="m-stat-value"><?= $sessionsCompleted ?></div>
                <div class="m-stat-label">Sessions</div>
            </div>
            <div class="m-stat">
                <div class="m-stat-icon" style="color:#3B82F6;"><i class="fas fa-bullseye"></i></div>
                <div class="m-stat-value"><?= $activeGoals ?></div>
                <div class="m-stat-label">Active Goals</div>
            </div>
        <?php endif; ?>
        <div class="m-stat">
            <div class="m-stat-icon" style="color:#10B981;"><i class="fas fa-arrow-up"></i></div>
            <div class="m-stat-value"><?= count($upcomingSessions) ?></div>
            <div class="m-stat-label">Upcoming</div>
        </div>
        <div class="m-stat">
            <div class="m-stat-icon" style="color:#EF4444;"><i class="fas fa-bell"></i></div>
            <div class="m-stat-value"><?= $unreadCount ?></div>
            <div class="m-stat-label">Notifications</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="m-quick-grid">
        <a href="?page=sessions" class="m-quick-btn">
            <i class="fas fa-calendar-check"></i><span>Sessions</span>
        </a>
        <a href="?page=messages" class="m-quick-btn">
            <i class="fas fa-comment-dots"></i><span>Messages</span>
        </a>
        <?php if ($isAnyCoach): ?>
            <a href="?page=create_session" class="m-quick-btn">
                <i class="fas fa-calendar-plus" style="color:#10B981;"></i><span>New Session</span>
            </a>
            <a href="?page=roster" class="m-quick-btn">
                <i class="fas fa-users"></i><span>Roster</span>
            </a>
            <a href="?page=drills" class="m-quick-btn">
                <i class="fas fa-hockey-puck"></i><span>Drills</span>
            </a>
            <a href="?page=practice" class="m-quick-btn">
                <i class="fas fa-clipboard-list"></i><span>Plans</span>
            </a>
            <a href="?page=coach_calendar" class="m-quick-btn">
                <i class="fas fa-calendar"></i><span>Calendar</span>
            </a>
            <a href="?page=video" class="m-quick-btn">
                <i class="fas fa-video"></i><span>Video</span>
            </a>
        <?php else: ?>
            <a href="?page=stats" class="m-quick-btn">
                <i class="fas fa-chart-line"></i><span>Stats</span>
            </a>
            <a href="?page=goals" class="m-quick-btn">
                <i class="fas fa-bullseye"></i><span>Goals</span>
            </a>
            <a href="?page=health" class="m-quick-btn">
                <i class="fas fa-heartbeat"></i><span>Health</span>
            </a>
            <a href="?page=video" class="m-quick-btn">
                <i class="fas fa-video"></i><span>Video</span>
            </a>
            <a href="?page=shop" class="m-quick-btn">
                <i class="fas fa-store"></i><span>Shop</span>
            </a>
            <a href="?page=notifications" class="m-quick-btn">
                <i class="fas fa-bell"></i><span>Alerts</span>
            </a>
        <?php endif; ?>
    </div>

    <!-- Upcoming Sessions -->
    <h3 class="m-section-title">Upcoming Sessions</h3>
    <?php if (empty($upcomingSessions)): ?>
        <div class="m-empty"><i class="fas fa-calendar-xmark" style="font-size:24px;display:block;margin-bottom:8px;"></i>No upcoming sessions</div>
    <?php else: ?>
        <?php foreach ($upcomingSessions as $sess):
            $sDate = strtotime($sess['session_date']);
            $sTime = $sess['session_time'] ? date('g:i A', strtotime($sess['session_time'])) : '';
        ?>
        <a href="?page=session_detail&id=<?= (int)$sess['id'] ?>" class="m-session-item">
            <div class="m-session-date">
                <span class="m-session-date-month"><?= date('M', $sDate) ?></span>
                <span class="m-session-date-day"><?= date('j', $sDate) ?></span>
            </div>
            <div class="m-session-info">
                <div class="m-session-title"><?= htmlspecialchars($sess['title']) ?></div>
                <div class="m-session-meta">
                    <?php if ($sTime): ?><i class="fas fa-clock"></i> <?= $sTime ?><?php endif; ?>
                    <?php if ($sess['duration_minutes']): ?> · <?= (int)$sess['duration_minutes'] ?>min<?php endif; ?>
                    <?php if ($sess['arena']): ?> · <?= htmlspecialchars($sess['arena']) ?><?php endif; ?>
                </div>
            </div>
            <span class="m-session-badge">Upcoming</span>
        </a>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($user_role === 'athlete' || (!$isAnyCoach && $user_role !== 'parent')): ?>
        <!-- Pending Payments -->
        <?php if (!empty($pendingPayments)): ?>
        <div class="m-payment-alert">
            <div class="m-payment-header">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Payment Required</strong>
                    <span><?= count($pendingPayments) ?> session<?= count($pendingPayments) !== 1 ? 's' : '' ?> awaiting payment</span>
                </div>
            </div>
            <?php foreach ($pendingPayments as $payment): ?>
            <div class="m-payment-item">
                <div class="m-payment-item-name"><?= htmlspecialchars($payment['session_title'] ?? '') ?></div>
                <div class="m-payment-item-meta">
                    <i class="fas fa-calendar"></i> <?= date('M j, Y', strtotime($payment['session_date'])) ?>
                    <?php if (!empty($payment['start_time'])): ?> at <?= date('g:i A', strtotime($payment['start_time'])) ?><?php endif; ?>
                    <?php if (!empty($payment['location_name'])): ?>
                    · <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($payment['location_name']) ?>
                    <?php endif; ?>
                </div>
                <div class="m-payment-item-row">
                    <span class="m-payment-amount">$<?= number_format((float)($payment['amount_due'] ?? 0), 2) ?></span>
                    <a href="?page=session_payment&booking_id=<?= (int)$payment['booking_id'] ?>" class="m-pay-btn">
                        <i class="fas fa-credit-card"></i> Pay Now
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Performance Metrics -->
        <h3 class="m-section-title">Performance Metrics</h3>
        <div class="m-metrics-grid">
            <div class="m-metric">
                <div class="m-metric-icon goals"><i class="fas fa-bullseye"></i></div>
                <div>
                    <div class="m-metric-value"><?= intval($goalsMetrics['completed_goals']) ?>/<?= intval($goalsMetrics['total_goals']) ?></div>
                    <div class="m-metric-label">Goals Done</div>
                </div>
            </div>
            <div class="m-metric">
                <div class="m-metric-icon progress"><i class="fas fa-tasks"></i></div>
                <div>
                    <div class="m-metric-value"><?= round($goalsMetrics['avg_progress'] ?? 0) ?>%</div>
                    <div class="m-metric-label">Avg Progress</div>
                </div>
            </div>
            <div class="m-metric">
                <div class="m-metric-icon skills"><i class="fas fa-star"></i></div>
                <div>
                    <div class="m-metric-value"><?= number_format((float)($skillsMetrics['avg_score'] ?? 0), 1) ?>/5</div>
                    <div class="m-metric-label">Skill Score</div>
                </div>
            </div>
            <div class="m-metric">
                <div class="m-metric-icon active"><i class="fas fa-flag-checkered"></i></div>
                <div>
                    <div class="m-metric-value"><?= intval($goalsMetrics['active_goals']) ?></div>
                    <div class="m-metric-label">Active Goals</div>
                </div>
            </div>
        </div>

        <!-- Recent Goals Progress -->
        <?php if (!empty($recentGoals)): ?>
        <div class="m-card">
            <div class="m-card-header">
                <h4><i class="fas fa-list"></i> Recent Goals</h4>
                <a href="?page=goals">View All</a>
            </div>
            <?php foreach ($recentGoals as $goal): ?>
            <div class="m-goal-item">
                <div class="m-goal-info">
                    <span class="m-goal-title"><?= htmlspecialchars($goal['title'] ?? '') ?></span>
                    <?php if (!empty($goal['target_date'])): ?>
                    <span class="m-goal-date">Due: <?= date('M j', strtotime($goal['target_date'])) ?></span>
                    <?php endif; ?>
                </div>
                <div class="m-goal-progress">
                    <div class="m-progress-bar">
                        <div class="m-progress-fill" style="width: <?= intval($goal['progress']) ?>%"></div>
                    </div>
                    <span class="m-progress-text"><?= intval($goal['progress']) ?>%</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Performance Stats Overview -->
        <h3 class="m-section-title">Performance Stats</h3>
        <div class="m-stats-row">
            <div class="m-stats-item">
                <div class="m-stats-item-icon" style="color:#10B981;"><i class="fas fa-running"></i></div>
                <div class="m-stats-item-value"><?= (int)($performanceStats['sessions_completed'] ?? 0) ?></div>
                <div class="m-stats-item-label">Sessions</div>
            </div>
            <div class="m-stats-item">
                <div class="m-stats-item-icon" style="color:#3B82F6;"><i class="fas fa-video"></i></div>
                <div class="m-stats-item-value"><?= (int)($performanceStats['videos_reviewed'] ?? 0) ?></div>
                <div class="m-stats-item-label">Videos</div>
            </div>
            <div class="m-stats-item">
                <div class="m-stats-item-icon" style="color:#8B5CF6;"><i class="fas fa-bullseye"></i></div>
                <div class="m-stats-item-value"><?= (int)($performanceStats['active_goals'] ?? 0) ?></div>
                <div class="m-stats-item-label">Active Goals</div>
            </div>
        </div>

        <!-- Notifications -->
        <?php if (!empty($notifications)): ?>
        <div class="m-card">
            <div class="m-card-header">
                <h4><i class="fas fa-bell"></i> Notifications</h4>
                <a href="?page=notifications">View All</a>
            </div>
            <?php foreach ($notifications as $notif): ?>
            <div class="m-notif-item">
                <div class="m-notif-dot"></div>
                <div class="m-notif-text">
                    <?= htmlspecialchars($notif['message'] ?? '') ?>
                    <span class="m-notif-time"><?= !empty($notif['created_at']) ? date('M j, Y', strtotime($notif['created_at'])) : '' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Coach Notes -->
        <?php if (!empty($coachNotes)): ?>
        <div class="m-card">
            <div class="m-card-header">
                <h4><i class="fas fa-comments"></i> Coach Notes</h4>
            </div>
            <?php foreach ($coachNotes as $note): ?>
            <div class="m-note-item">
                <div class="m-note-header">
                    <span class="m-note-coach"><?= htmlspecialchars(trim(($note['coach_first_name'] ?? '') . ' ' . ($note['coach_last_name'] ?? '')) ?: 'Coach') ?></span>
                    <span class="m-note-date"><?= !empty($note['created_at']) ? date('M j', strtotime($note['created_at'])) : '' ?></span>
                </div>
                <p class="m-note-text"><?= htmlspecialchars(substr($note['feedback'] ?? 'No feedback', 0, 100)) ?>...</p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($isAnyCoach): ?>
        <!-- Pending Video Reviews -->
        <?php if (!empty($pendingReviews)): ?>
        <div class="m-card">
            <div class="m-card-header">
                <h4><i class="fas fa-clipboard-check"></i> Pending Reviews</h4>
            </div>
            <?php foreach ($pendingReviews as $review): ?>
            <div class="m-review-item">
                <div>
                    <span class="m-review-name"><?= htmlspecialchars(trim(($review['athlete_first_name'] ?? '') . ' ' . ($review['athlete_last_name'] ?? '')) ?: 'Athlete') ?></span>
                    <span class="m-review-type">Video Review</span>
                </div>
                <a href="?page=video_review&id=<?= (int)$review['id'] ?>" class="m-review-btn">Review</a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Athlete Notifications -->
        <?php if (!empty($athleteUpdates)): ?>
        <div class="m-card">
            <div class="m-card-header">
                <h4><i class="fas fa-user-clock"></i> Athlete Alerts</h4>
            </div>
            <?php foreach ($athleteUpdates as $update): ?>
            <div class="m-athlete-alert">
                <div class="m-athlete-alert-icon"><i class="fas fa-exclamation-circle"></i></div>
                <div class="m-athlete-alert-content">
                    <span class="m-athlete-alert-name"><?= htmlspecialchars(trim(($update['athlete_first_name'] ?? '') . ' ' . ($update['athlete_last_name'] ?? '')) ?: 'Athlete') ?></span>
                    <p class="m-athlete-alert-msg"><?= htmlspecialchars($update['message'] ?? '') ?></p>
                    <span class="m-athlete-alert-time"><?= !empty($update['created_at']) ? date('M j, Y', strtotime($update['created_at'])) : '' ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($user_role === 'parent'): ?>
        <!-- Parent: Athlete Selector -->
        <h3 class="m-section-title"><i class="fas fa-users" style="color:#8B5CF6;"></i> Your Athletes</h3>
        <?php if (!empty($parentAthletes)): ?>
        <div class="m-select-wrap">
            <select class="m-select" id="m-athlete-selector">
                <option value="">-- Select an athlete --</option>
                <?php foreach ($parentAthletes as $athlete): ?>
                <option value="<?= (int)$athlete['id'] ?>"><?= htmlspecialchars(trim(($athlete['first_name'] ?? '') . ' ' . ($athlete['last_name'] ?? ''))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div id="m-athlete-dashboard">
            <div class="m-card">
                <div class="m-card-header"><h4><i class="fas fa-calendar-check"></i> Upcoming Sessions</h4></div>
                <div class="m-parent-sessions" id="m-athlete-sessions">
                    <div class="m-empty">Select an athlete to view sessions.</div>
                </div>
            </div>
            <div class="m-card">
                <div class="m-card-header"><h4><i class="fas fa-chart-line"></i> Progress Overview</h4></div>
                <div class="m-parent-stats" id="m-athlete-progress">
                    <div class="m-empty">Select an athlete to view progress.</div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="m-empty"><i class="fas fa-user-slash" style="font-size:20px;display:block;margin-bottom:6px;"></i>No athletes linked to your account.</div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<script>
(function() {
    // System notification dismiss handler
    document.querySelectorAll('.m-alert-dismiss').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var alert = this.closest('.m-alert');
            var notifId = this.getAttribute('data-notif-id');
            if (alert) {
                alert.style.opacity = '0';
                alert.style.transform = 'translateX(100%)';
                alert.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                setTimeout(function() { alert.style.display = 'none'; }, 300);
                if (notifId) {
                    var dismissed = JSON.parse(sessionStorage.getItem('dismissedNotifications') || '[]');
                    if (!dismissed.includes(notifId)) {
                        dismissed.push(notifId);
                        sessionStorage.setItem('dismissedNotifications', JSON.stringify(dismissed));
                    }
                }
            }
        });
    });
    // Hide already dismissed notifications on page load
    var dismissed = JSON.parse(sessionStorage.getItem('dismissedNotifications') || '[]');
    dismissed.forEach(function(id) {
        var alert = document.getElementById('pwa-sys-alert-' + id);
        if (alert) alert.style.display = 'none';
    });
})();
</script>
<script>
(function() {
    var tz = window.APP_TIMEZONE;
    var h = parseInt(new Date().toLocaleString('en-US', {timeZone: tz, hour: 'numeric', hour12: false}));
    var timeOfDay = h < 12 ? 'Morning' : (h < 17 ? 'Afternoon' : 'Evening');
    var days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
    var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    var now = new Date();
    var el = document.getElementById('pwa-greeting');
    if (el) {
        var parts = el.textContent.split(',');
        var name = parts.length > 1 ? parts.slice(1).join(',').trim() : '';
        if (name) {
            el.textContent = 'Good ' + timeOfDay + ', ' + name;
        } else {
            el.textContent = 'Good ' + timeOfDay;
        }
    }
    var dateEl = document.getElementById('pwa-greeting-date');
    if (dateEl) {
        dateEl.textContent = now.toLocaleDateString('en-US', {timeZone: tz, weekday: 'long', month: 'short', day: 'numeric'});
    }
})();
<?php if ($user_role === 'parent' && !empty($parentAthletes)): ?>
function mLoadAthleteDashboard(athleteId) {
    var dash = document.getElementById('m-athlete-dashboard');
    if (!athleteId) { dash.style.display = 'none'; return; }
    dash.style.display = 'block';
    document.getElementById('m-athlete-sessions').innerHTML = '<div class="m-empty">Loading...</div>';
    document.getElementById('m-athlete-progress').innerHTML = '<div class="m-empty">Loading...</div>';
    fetch('process_get_athlete_dashboard.php?athlete_id=' + encodeURIComponent(athleteId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var sh = '';
                if (data.sessions && data.sessions.length > 0) {
                    data.sessions.forEach(function(s) {
                        var type = mEsc(s.type || 'Session');
                        var dateStr = mEsc(s.date || '');
                        var timeStr = mEsc(s.time || '');
                        sh += '<div class="m-session-item" style="text-decoration:none;">'
                            + '<div class="m-session-info">'
                            + '<div class="m-session-title">' + type + '</div>'
                            + '<div class="m-session-meta">' + dateStr + (timeStr ? ' at ' + timeStr : '') + '</div>'
                            + '</div></div>';
                    });
                } else {
                    sh = '<div class="m-empty">No upcoming sessions.</div>';
                }
                document.getElementById('m-athlete-sessions').innerHTML = sh;
                var stats = data.stats || {};
                var sa = parseInt(stats.sessions_attended, 10) || 0;
                var gc = parseInt(stats.goals_completed, 10) || 0;
                var ag = parseInt(stats.active_goals, 10) || 0;
                document.getElementById('m-athlete-progress').innerHTML =
                    '<div class="m-stats-row">'
                    + '<div class="m-stats-item"><div class="m-stats-item-value">' + sa + '</div><div class="m-stats-item-label">Attended</div></div>'
                    + '<div class="m-stats-item"><div class="m-stats-item-value">' + gc + '</div><div class="m-stats-item-label">Goals Done</div></div>'
                    + '<div class="m-stats-item"><div class="m-stats-item-value">' + ag + '</div><div class="m-stats-item-label">Active Goals</div></div>'
                    + '</div>';
            }
        })
        .catch(function(e) {
            document.getElementById('m-athlete-sessions').innerHTML = '<div class="m-empty">Error loading data.</div>';
            document.getElementById('m-athlete-progress').innerHTML = '';
        });
}
function mEsc(str) {
    var d = document.createElement('div');
    d.appendChild(document.createTextNode(str));
    return d.innerHTML;
}
document.addEventListener('DOMContentLoaded', function() {
    var sel = document.getElementById('m-athlete-selector');
    if (sel) {
        sel.addEventListener('change', function() { mLoadAthleteDashboard(this.value); });
    }
});
<?php endif; ?>
</script>
