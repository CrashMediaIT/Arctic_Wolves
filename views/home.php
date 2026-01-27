<!-- Home Dashboard View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-home"></i> Dashboard
    </h1>
    <p class="page-description">Welcome back, <?php echo htmlspecialchars($user_name); ?>! Here's your overview.</p>
</div>

<?php
// Fetch real data from database
try {
    if ($user_role === 'athlete' || $user_role === 'parent') {
        // Get upcoming sessions
        $stmt = $pdo->prepare("
            SELECT s.*, st.name as session_type_name, st.duration
            FROM sessions s
            LEFT JOIN session_types st ON s.session_type_id = st.id
            WHERE s.date >= CURDATE()
            AND s.status = 'scheduled'
            ORDER BY s.date ASC, s.start_time ASC
            LIMIT 5
        ");
        $stmt->execute();
        $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent notifications
        $stmt = $pdo->prepare("
            SELECT * FROM notifications
            WHERE user_id = ? AND is_read = 0
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get performance stats for athlete
        $stmt = $pdo->prepare("
            SELECT 
                (SELECT COUNT(*) FROM bookings b JOIN sessions s ON b.session_id = s.id 
                 WHERE b.user_id = ? AND s.status = 'completed') as sessions_completed,
                (SELECT COUNT(*) FROM videos WHERE athlete_id = ? AND status = 'reviewed') as videos_reviewed,
                (SELECT COUNT(*) FROM performance_goals WHERE user_id = ? AND status = 'active') as active_goals
        ");
        $stmt->execute([$user_id, $user_id, $user_id]);
        $performanceStats = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get recent coach notes (feedback)
        $stmt = $pdo->prepare("
            SELECT vr.*, u.name as coach_name
            FROM video_reviews vr
            LEFT JOIN users u ON vr.coach_id = u.id
            WHERE vr.athlete_id = ?
            ORDER BY vr.created_at DESC
            LIMIT 5
        ");
        $stmt->execute([$user_id]);
        $coachNotes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif (in_array($user_role, ['coach', 'health_coach', 'team_coach', 'admin'])) {
        // Get upcoming sessions (next 7 days) instead of just today
        $stmt = $pdo->prepare("
            SELECT s.*, st.name as session_type_name, st.duration,
                   COUNT(DISTINCT sa.athlete_id) as attendee_count
            FROM sessions s
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN session_attendance sa ON s.id = sa.session_id
            WHERE s.date >= CURDATE() AND s.date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            AND s.status = 'scheduled'
            GROUP BY s.id
            ORDER BY s.date ASC, s.start_time ASC
            LIMIT 10
        ");
        $stmt->execute();
        $upcomingSessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get pending video reviews
        $stmt = $pdo->prepare("
            SELECT vr.*, u.name as athlete_name
            FROM video_drill_reviews vr
            LEFT JOIN users u ON vr.athlete_id = u.id
            WHERE vr.status = 'pending'
            ORDER BY vr.created_at ASC
            LIMIT 5
        ");
        $stmt->execute();
        $pendingReviews = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get recent athlete updates
        $stmt = $pdo->prepare("
            SELECT n.*, u.name as athlete_name
            FROM notifications n
            LEFT JOIN users u ON n.user_id = u.id
            WHERE n.type IN ('injury', 'absence', 'alert')
            ORDER BY n.created_at DESC
            LIMIT 5
        ");
        $stmt->execute();
        $athleteUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    error_log("Dashboard data fetch error: " . $e->getMessage());
    $upcomingSessions = [];
    $notifications = [];
    $coachNotes = [];
    $performanceStats = ['sessions_completed' => 0, 'videos_reviewed' => 0, 'active_goals' => 0];
    $pendingReviews = [];
    $athleteUpdates = [];
}

// Fetch active system notifications for all users
$systemNotifications = [];
try {
    $stmt = $pdo->prepare("
        SELECT id, title, message, notification_type, start_date, end_date
        FROM system_notifications
        WHERE is_active = 1
          AND start_date <= NOW()
          AND (end_date IS NULL OR end_date >= NOW())
        ORDER BY 
            CASE notification_type 
                WHEN 'alert' THEN 1 
                WHEN 'maintenance' THEN 2 
                WHEN 'warning' THEN 3 
                ELSE 4 
            END,
            start_date DESC
        LIMIT 5
    ");
    $stmt->execute();
    $systemNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("System notifications fetch error: " . $e->getMessage());
}
?>

<?php if (!empty($systemNotifications)): ?>
<!-- System Notifications Banner -->
<div class="system-notifications-widget">
    <?php foreach ($systemNotifications as $sysNotif): ?>
        <div class="system-alert system-alert-<?php echo htmlspecialchars($sysNotif['notification_type']); ?>" id="system-alert-<?php echo (int)$sysNotif['id']; ?>">
            <div class="system-alert-icon">
                <?php 
                $icon = 'info-circle';
                switch ($sysNotif['notification_type']) {
                    case 'warning': $icon = 'exclamation-triangle'; break;
                    case 'alert': $icon = 'exclamation-circle'; break;
                    case 'maintenance': $icon = 'tools'; break;
                }
                ?>
                <i class="fas fa-<?php echo $icon; ?>" aria-hidden="true"></i>
            </div>
            <div class="system-alert-content">
                <strong><?php echo htmlspecialchars($sysNotif['title']); ?></strong>
                <p><?php echo htmlspecialchars($sysNotif['message']); ?></p>
                <?php if ($sysNotif['end_date']): ?>
                    <small>Until <?php echo date('M j, Y g:i A', strtotime($sysNotif['end_date'])); ?></small>
                <?php endif; ?>
            </div>
            <button class="system-alert-dismiss" 
                    aria-label="Dismiss notification: <?php echo htmlspecialchars($sysNotif['title']); ?>"
                    data-notification-id="<?php echo (int)$sysNotif['id']; ?>">
                <i class="fas fa-times" aria-hidden="true"></i>
            </button>
        </div>
    <?php endforeach; ?>
</div>

<script>
// System notification dismiss handler
document.querySelectorAll('.system-alert-dismiss').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var alert = this.closest('.system-alert');
        var notifId = this.getAttribute('data-notification-id');
        if (alert) {
            alert.style.opacity = '0';
            alert.style.transform = 'translateX(100%)';
            setTimeout(function() { alert.style.display = 'none'; }, 300);
            // Store dismissed notification in sessionStorage to persist during session
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
document.addEventListener('DOMContentLoaded', function() {
    var dismissed = JSON.parse(sessionStorage.getItem('dismissedNotifications') || '[]');
    dismissed.forEach(function(id) {
        var alert = document.getElementById('system-alert-' + id);
        if (alert) alert.style.display = 'none';
    });
});
</script>

<style>
/* System Notifications Widget */
.system-notifications-widget {
    margin-bottom: 24px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.system-alert {
    display: flex;
    align-items: flex-start;
    gap: 16px;
    padding: 16px 20px;
    border-radius: 12px;
    border-left: 4px solid;
    position: relative;
    animation: slideIn 0.3s ease;
    transition: opacity 0.3s ease, transform 0.3s ease;
}

.system-alert-info {
    background: rgba(59, 130, 246, 0.1);
    border-color: #3b82f6;
}

.system-alert-info .system-alert-icon {
    color: #3b82f6;
}

.system-alert-warning {
    background: rgba(245, 158, 11, 0.1);
    border-color: #f59e0b;
}

.system-alert-warning .system-alert-icon {
    color: #f59e0b;
}

.system-alert-alert {
    background: rgba(239, 68, 68, 0.1);
    border-color: #ef4444;
}

.system-alert-alert .system-alert-icon {
    color: #ef4444;
}

.system-alert-maintenance {
    background: rgba(251, 191, 36, 0.1);
    border-color: #fbbf24;
}

.system-alert-maintenance .system-alert-icon {
    color: #fbbf24;
}

.system-alert-icon {
    font-size: 20px;
    flex-shrink: 0;
    margin-top: 2px;
}

.system-alert-content {
    flex: 1;
}

.system-alert-content strong {
    display: block;
    font-size: 15px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}

.system-alert-content p {
    font-size: 14px;
    color: #94a3b8;
    margin: 0 0 4px 0;
    line-height: 1.5;
}

.system-alert-content small {
    font-size: 12px;
    color: #64748b;
}

.system-alert-dismiss {
    background: transparent;
    border: none;
    color: #64748b;
    cursor: pointer;
    padding: 4px;
    font-size: 16px;
    transition: color 0.2s;
    flex-shrink: 0;
}

.system-alert-dismiss:hover {
    color: #fff;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
<?php endif; ?>

<div class="dashboard-content">
    <!-- Role-specific content will be loaded here -->
    <?php if ($user_role === 'athlete'): ?>
        <!-- Athlete Dashboard -->
        
        <?php
        // Fetch additional performance metrics for the athlete
        try {
            // Get goals stats
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
            $goalsMetrics = $goalsStmt->fetch(PDO::FETCH_ASSOC);
            
            // Get recent active goals
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
            
            // Get skill evaluations summary
            $skillsStmt = $pdo->prepare("
                SELECT 
                    COUNT(DISTINCT skill_id) as total_skills,
                    AVG(score) as avg_score,
                    MAX(evaluation_date) as last_evaluation
                FROM evaluation_scores
                WHERE athlete_id = ?
            ");
            $skillsStmt->execute([$user_id]);
            $skillsMetrics = $skillsStmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            error_log("Dashboard metrics error: " . $e->getMessage());
            $goalsMetrics = ['total_goals' => 0, 'completed_goals' => 0, 'active_goals' => 0, 'avg_progress' => 0];
            $recentGoals = [];
            $skillsMetrics = ['total_skills' => 0, 'avg_score' => 0, 'last_evaluation' => null];
        }
        ?>
        
        <!-- Performance Metrics Section - Active Data -->
        <div class="performance-metrics-section">
            <div class="section-header-bar">
                <h2 class="section-header"><i class="fas fa-chart-bar"></i> Performance Metrics</h2>
                <a href="?page=stats" class="btn btn-primary btn-sm">
                    <i class="fas fa-chart-line"></i> View Details
                </a>
            </div>
            
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-icon goals">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="metric-info">
                        <div class="metric-value"><?php echo intval($goalsMetrics['completed_goals']); ?>/<?php echo intval($goalsMetrics['total_goals']); ?></div>
                        <div class="metric-label">Goals Completed</div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon progress">
                        <i class="fas fa-tasks"></i>
                    </div>
                    <div class="metric-info">
                        <div class="metric-value"><?php echo round($goalsMetrics['avg_progress'] ?? 0); ?>%</div>
                        <div class="metric-label">Avg. Goal Progress</div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon skills">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="metric-info">
                        <div class="metric-value"><?php echo number_format($skillsMetrics['avg_score'] ?? 0, 1); ?>/5</div>
                        <div class="metric-label">Avg. Skill Score</div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon active">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <div class="metric-info">
                        <div class="metric-value"><?php echo intval($goalsMetrics['active_goals']); ?></div>
                        <div class="metric-label">Active Goals</div>
                    </div>
                </div>
            </div>
            
            <?php if (count($recentGoals) > 0): ?>
            <div class="recent-goals-widget">
                <h4><i class="fas fa-list"></i> Recent Goals Progress</h4>
                <div class="goals-list">
                    <?php foreach ($recentGoals as $goal): ?>
                    <div class="goal-item">
                        <div class="goal-info">
                            <span class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></span>
                            <?php if ($goal['target_date']): ?>
                            <span class="goal-date">Due: <?php echo date('M d', strtotime($goal['target_date'])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="goal-progress">
                            <div class="progress-bar-mini">
                                <div class="progress-fill-mini" style="width: <?php echo intval($goal['progress']); ?>%"></div>
                            </div>
                            <span class="progress-text"><?php echo intval($goal['progress']); ?>%</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="no-goals-widget">
                <p>No active goals yet. <a href="?page=stats">Create your first goal</a> to start tracking!</p>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Performance Stats Overview -->
        <div class="stats-overview">
            <h2 class="section-header"><i class="fas fa-chart-line"></i> Performance Stats</h2>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-running"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $performanceStats['sessions_completed'] ?? 0; ?></div>
                        <div class="stat-label">Sessions Completed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-video"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $performanceStats['videos_reviewed'] ?? 0; ?></div>
                        <div class="stat-label">Videos Reviewed</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-bullseye"></i>
                    </div>
                    <div class="stat-info">
                        <div class="stat-value"><?php echo $performanceStats['active_goals'] ?? 0; ?></div>
                        <div class="stat-label">Active Goals</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-check"></i> Upcoming Sessions</h3>
                    <a href="?page=sessions" class="btn-sm btn-secondary">View All</a>
                </div>
                <div class="card-body">
                    <?php if (count($upcomingSessions) > 0): ?>
                        <div class="session-list">
                            <?php foreach ($upcomingSessions as $session): ?>
                                <div class="session-item">
                                    <div class="session-info">
                                        <strong><?php echo htmlspecialchars($session['session_type_name'] ?? 'Session'); ?></strong>
                                        <span class="session-date">
                                            <?php echo date('M d, Y', strtotime($session['date'])); ?> at 
                                            <?php echo date('g:i A', strtotime($session['start_time'])); ?>
                                        </span>
                                    </div>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($session['duration'] ?? 60); ?> min
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">No upcoming sessions scheduled. Book one now!</p>
                        <a href="?page=booking" class="btn btn-primary">Book Session</a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                </div>
                <div class="card-body">
                    <?php if (count($notifications) > 0): ?>
                        <div class="notification-list">
                            <?php foreach ($notifications as $notification): ?>
                                <div class="notification-item">
                                    <div class="notification-icon">
                                        <i class="fas fa-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="notification-time">
                                            <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">No new notifications.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-comments"></i> Coach Notes</h3>
                </div>
                <div class="card-body">
                    <?php if (count($coachNotes) > 0): ?>
                        <div class="notes-list">
                            <?php foreach ($coachNotes as $note): ?>
                                <div class="note-item">
                                    <div class="note-header">
                                        <strong><?php echo htmlspecialchars($note['coach_name'] ?? 'Coach'); ?></strong>
                                        <span class="note-date">
                                            <?php echo date('M d', strtotime($note['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="note-content">
                                        <?php echo htmlspecialchars(substr($note['feedback'] ?? 'No feedback', 0, 100)); ?>...
                                    </p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">No coach feedback yet.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif (in_array($user_role, ['coach', 'health_coach', 'team_coach', 'admin'])): ?>
        <!-- Coach/Admin Dashboard -->
        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-calendar-alt"></i> Upcoming Sessions</h3>
                    <a href="?page=booking" class="btn-sm btn-secondary">
                        <i class="fas fa-plus"></i> Add Session
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($upcomingSessions) > 0): ?>
                        <div class="session-list">
                            <?php foreach ($upcomingSessions as $session): ?>
                                <div class="session-item">
                                    <div class="session-info">
                                        <strong><?php echo htmlspecialchars($session['session_type_name'] ?? 'Session'); ?></strong>
                                        <span class="session-meta">
                                            <?php echo date('M d, Y', strtotime($session['date'])); ?> at
                                            <?php echo date('g:i A', strtotime($session['start_time'])); ?> •
                                            <?php echo htmlspecialchars($session['attendee_count'] ?? 0); ?> athletes
                                        </span>
                                    </div>
                                    <span class="badge <?php echo (date('Y-m-d') === $session['date']) ? 'badge-success' : 'badge-primary'; ?>">
                                        <?php echo (date('Y-m-d') === $session['date']) ? 'Today' : date('M d', strtotime($session['date'])); ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">No upcoming sessions scheduled.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-clock"></i> Athlete Notifications</h3>
                </div>
                <div class="card-body">
                    <?php if (count($athleteUpdates) > 0): ?>
                        <div class="notification-list">
                            <?php foreach ($athleteUpdates as $update): ?>
                                <div class="notification-item">
                                    <div class="notification-icon <?php echo htmlspecialchars($update['type']); ?>">
                                        <i class="fas fa-exclamation-circle"></i>
                                    </div>
                                    <div class="notification-content">
                                        <strong><?php echo htmlspecialchars($update['athlete_name'] ?? 'Athlete'); ?></strong>
                                        <p><?php echo htmlspecialchars($update['message']); ?></p>
                                        <span class="notification-time">
                                            <?php echo date('M d, Y', strtotime($update['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">No athlete alerts at this time.</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <h3><i class="fas fa-clipboard-check"></i> Pending Reviews</h3>
                </div>
                <div class="card-body">
                    <?php if (count($pendingReviews) > 0): ?>
                        <div class="review-list">
                            <?php foreach ($pendingReviews as $review): ?>
                                <div class="review-item">
                                    <div class="review-info">
                                        <strong><?php echo htmlspecialchars($review['athlete_name'] ?? 'Athlete'); ?></strong>
                                        <span class="review-type">Video Review</span>
                                    </div>
                                    <a href="?page=video_review&id=<?php echo $review['id']; ?>" class="btn-sm btn-primary">
                                        Review
                                    </a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">All reviews complete!</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    <?php elseif ($user_role === 'parent'): ?>
        <!-- Parent Dashboard -->
        <?php
        // Get parent's associated athletes
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email
            FROM users u
            INNER JOIN parent_athlete_relationships par ON u.id = par.athlete_id
            WHERE par.parent_id = ?
            ORDER BY u.name ASC
        ");
        $stmt->execute([$user_id]);
        $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        ?>
        
        <div class="parent-dashboard">
            <div class="athlete-selector-card">
                <h3><i class="fas fa-users"></i> Select Athlete</h3>
                <select class="form-input" id="athlete-selector" onchange="loadAthleteDashboard(this.value)">
                    <option value="">-- Select an athlete --</option>
                    <?php foreach ($athletes as $athlete): ?>
                        <option value="<?php echo $athlete['id']; ?>">
                            <?php echo htmlspecialchars($athlete['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div id="athlete-dashboard" style="display: none;">
                <div class="dashboard-grid">
                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-calendar-check"></i> Upcoming Sessions</h3>
                        </div>
                        <div class="card-body" id="athlete-sessions">
                            <p class="placeholder-text">Select an athlete to view their sessions.</p>
                        </div>
                    </div>

                    <div class="dashboard-card">
                        <div class="card-header">
                            <h3><i class="fas fa-chart-line"></i> Progress Overview</h3>
                        </div>
                        <div class="card-body" id="athlete-progress">
                            <p class="placeholder-text">Select an athlete to view their progress.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        function loadAthleteDashboard(athleteId) {
            if (!athleteId) {
                document.getElementById('athlete-dashboard').style.display = 'none';
                return;
            }
            
            document.getElementById('athlete-dashboard').style.display = 'block';
            
            // Fetch athlete sessions
            fetch(`process_get_athlete_dashboard.php?athlete_id=${athleteId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate sessions
                        const sessionsHtml = data.sessions.length > 0
                            ? data.sessions.map(s => `
                                <div class="session-item">
                                    <div class="session-info">
                                        <strong>${s.type}</strong>
                                        <span class="session-date">${s.date} at ${s.time}</span>
                                    </div>
                                </div>
                            `).join('')
                            : '<p class="placeholder-text">No upcoming sessions.</p>';
                        document.getElementById('athlete-sessions').innerHTML = sessionsHtml;
                        
                        // Populate progress
                        document.getElementById('athlete-progress').innerHTML = `
                            <div class="progress-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Sessions Attended</span>
                                    <span class="stat-value">${data.stats.sessions_attended}</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Goals Completed</span>
                                    <span class="stat-value">${data.stats.goals_completed}</span>
                                </div>
                            </div>
                        `;
                    }
                })
                .catch(error => console.error('Error loading athlete data:', error));
        }
        </script>
    <?php endif; ?>

</div>

<style>
.page-header {
    margin-bottom: 32px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border);
}

.page-title {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i {
    color: var(--primary);
}

.page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 24px;
    margin-top: 24px;
}

.dashboard-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.dashboard-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.card-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    background: linear-gradient(135deg, rgba(107, 70, 193, 0.05) 0%, transparent 100%);
}

.card-header h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-header h3 i {
    color: var(--primary);
    font-size: 18px;
}

.card-body {
    padding: 24px;
}

.placeholder-text {
    color: var(--text-dim);
    font-size: 14px;
    text-align: center;
    padding: 40px 20px;
    margin: 0;
}

/* Session List Styles */
.session-list, .notification-list, .notes-list, .review-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.session-item, .notification-item, .note-item, .review-item {
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: border-color 0.2s ease;
}

.session-item:hover, .notification-item:hover, .note-item:hover, .review-item:hover {
    border-color: var(--primary);
}

.session-info, .review-info, .notification-content {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex: 1;
}

.session-info strong, .review-info strong {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white);
}

.session-date, .session-meta, .review-type {
    font-size: 13px;
    color: var(--text-dim);
}

.notification-item {
    align-items: flex-start;
    gap: 12px;
}

.notification-icon {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(107, 70, 193, 0.1);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.notification-icon i {
    color: var(--primary);
    font-size: 14px;
}

.notification-icon.injury {
    background: rgba(239, 68, 68, 0.1);
}

.notification-icon.injury i {
    color: var(--error);
}

.notification-content p {
    font-size: 14px;
    color: var(--text-white);
    margin: 0 0 4px 0;
}

.notification-time {
    font-size: 12px;
    color: var(--text-muted);
}

/* Note Styles */
.note-item {
    flex-direction: column;
    align-items: flex-start;
}

.note-header {
    display: flex;
    justify-content: space-between;
    width: 100%;
    margin-bottom: 8px;
}

.note-header strong {
    font-size: 14px;
    font-weight: 600;
    color: var(--primary);
}

.note-date {
    font-size: 12px;
    color: var(--text-muted);
}

.note-content {
    font-size: 13px;
    color: var(--text-secondary);
    margin: 0;
    line-height: 1.5;
}

/* Form Elements */
.form-input {
    width: 100%;
    height: 45px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    color: var(--text-white);
    padding: 0 16px;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 70, 193, 0.1);
}

.form-input:hover {
    border-color: var(--primary-light);
}

/* Button Styles */
.btn, .btn-primary, .btn-secondary {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    height: 45px;
    padding: 0 24px;
    border-radius: 8px;
    font-family: 'Inter', sans-serif;
    font-size: 14px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
    border: none;
    white-space: nowrap;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-hover);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.3);
}

.btn-secondary {
    background: transparent;
    border: 1px solid var(--border);
    color: var(--text-white);
}

.btn-secondary:hover {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary);
}

.btn-sm {
    height: 36px;
    padding: 0 16px;
    font-size: 13px;
}

/* Badge Styles */
.badge {
    display: inline-flex;
    align-items: center;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}

.badge-primary {
    background: rgba(107, 70, 193, 0.15);
    color: var(--primary-light);
}

.badge-success {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success);
}

/* Parent Dashboard */
.parent-dashboard {
    max-width: 100%;
}

.athlete-selector-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    margin-bottom: 24px;
}

.athlete-selector-card h3 {
    font-size: 18px;
    font-weight: 700;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.athlete-selector-card h3 i {
    color: var(--primary);
}

/* Progress Stats */
.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
}

.stat-item {
    background: var(--bg-main);
    padding: 16px;
    border-radius: 8px;
    border: 1px solid var(--border);
    text-align: center;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 8px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
    
    .card-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .btn-sm {
        width: 100%;
    }
}

/* Performance Stats Overview */
.stats-overview {
    margin-bottom: 24px;
}

.section-header {
    font-size: 20px;
    font-weight: 700;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--text-white);
}

.section-header i {
    color: var(--neon);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    border-color: var(--neon);
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--primary));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: #fff;
}

.stat-info {
    flex: 1;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: var(--text-white);
    line-height: 1;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: var(--text-dim);
    font-weight: 500;
}

/* Performance Metrics Section */
.performance-metrics-section {
    margin-bottom: 30px;
}

.section-header-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.section-header-bar .section-header {
    margin-bottom: 0;
}

/* New Performance Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-bottom: 20px;
}

.metric-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.2s ease;
}

.metric-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
    border-color: var(--primary);
}

.metric-icon {
    width: 50px;
    height: 50px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;
}

.metric-icon.goals { background: rgba(16, 185, 129, 0.15); color: #10B981; }
.metric-icon.progress { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.metric-icon.skills { background: rgba(245, 158, 11, 0.15); color: #F59E0B; }
.metric-icon.active { background: rgba(139, 92, 246, 0.15); color: #8B5CF6; }

.metric-info {
    flex: 1;
}

.metric-value {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    line-height: 1.2;
}

.metric-label {
    font-size: 12px;
    color: var(--text-dim);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 4px;
}

/* Recent Goals Widget */
.recent-goals-widget {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
}

.recent-goals-widget h4 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.recent-goals-widget h4 i {
    color: var(--primary);
}

.goals-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.goal-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    background: var(--bg-main);
    border-radius: 8px;
    gap: 16px;
}

.goal-info {
    flex: 1;
    min-width: 0;
}

.goal-title {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.goal-date {
    font-size: 11px;
    color: var(--text-dim);
    margin-top: 2px;
    display: block;
}

.goal-progress {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}

.progress-bar-mini {
    width: 80px;
    height: 6px;
    background: var(--bg-secondary);
    border-radius: 3px;
    overflow: hidden;
}

.progress-fill-mini {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), #8B5CF6);
    transition: width 0.3s ease;
}

.goal-progress .progress-text {
    font-size: 12px;
    font-weight: 700;
    color: var(--primary);
    min-width: 35px;
    text-align: right;
}

.no-goals-widget {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}

.no-goals-widget p {
    color: var(--text-dim);
    font-size: 14px;
    margin: 0;
}

.no-goals-widget a {
    color: var(--primary);
    font-weight: 600;
    text-decoration: none;
}

.no-goals-widget a:hover {
    text-decoration: underline;
}

/* Keep placeholder styles for backwards compatibility */
.metrics-placeholder-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px;
    text-align: center;
}

.placeholder-content {
    max-width: 500px;
    margin: 0 auto;
}

.placeholder-content .placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.5;
    margin-bottom: 20px;
    display: block;
}

.placeholder-content h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 12px;
}

.placeholder-content p {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 20px;
}
</style>
