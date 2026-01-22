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
        // Get today's sessions
        $stmt = $pdo->prepare("
            SELECT s.*, st.name as session_type_name, st.duration,
                   COUNT(DISTINCT sa.athlete_id) as attendee_count
            FROM sessions s
            LEFT JOIN session_types st ON s.session_type_id = st.id
            LEFT JOIN session_attendance sa ON s.id = sa.session_id
            WHERE s.date = CURDATE()
            GROUP BY s.id
            ORDER BY s.start_time ASC
        ");
        $stmt->execute();
        $todaySessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
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
    $todaySessions = [];
    $pendingReviews = [];
    $athleteUpdates = [];
}
?>

<div class="dashboard-content">
    <!-- Role-specific content will be loaded here -->
    <?php if ($user_role === 'athlete'): ?>
        <!-- Athlete Dashboard -->
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
                    <h3><i class="fas fa-calendar-alt"></i> Today's Sessions</h3>
                    <a href="?page=create_session" class="btn-sm btn-secondary">
                        <i class="fas fa-plus"></i> Add Session
                    </a>
                </div>
                <div class="card-body">
                    <?php if (count($todaySessions) > 0): ?>
                        <div class="session-list">
                            <?php foreach ($todaySessions as $session): ?>
                                <div class="session-item">
                                    <div class="session-info">
                                        <strong><?php echo htmlspecialchars($session['session_type_name'] ?? 'Session'); ?></strong>
                                        <span class="session-meta">
                                            <?php echo date('g:i A', strtotime($session['start_time'])); ?> •
                                            <?php echo htmlspecialchars($session['attendee_count'] ?? 0); ?> athletes
                                        </span>
                                    </div>
                                    <span class="badge badge-success">Today</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="placeholder-text">No sessions scheduled for today.</p>
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
</style>
