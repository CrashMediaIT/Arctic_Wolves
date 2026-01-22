<!-- Stats & Performance View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-line"></i> Performance Stats
    </h1>
    <p class="page-description">Track your progress and achieve your goals</p>
</div>

<?php
// Fetch real stats data
try {
    // Get goals stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_goals,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals
        FROM goals 
        WHERE athlete_id = ?
    ");
    $stmt->execute([$user_id]);
    $goalsStats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get training streak (consecutive days with sessions)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(s.date)) as streak_days
        FROM sessions s
        INNER JOIN bookings b ON s.id = b.session_id
        WHERE b.user_id = ? 
        AND s.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND s.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $streakData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get skills mastered (completed evaluations with high scores)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT skill_id) as skills_mastered
        FROM evaluation_scores
        WHERE athlete_id = ?
        AND score >= 4
    ");
    $stmt->execute([$user_id]);
    $skillsData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get active goals
    $stmt = $pdo->prepare("
        SELECT g.id, g.goal_title, g.goal_description, g.target_value, 
               g.current_value, g.target_date, g.status,
               CASE 
                   WHEN g.target_value > 0 THEN ROUND((g.current_value / g.target_value) * 100, 0)
                   ELSE 0
               END as progress_percentage
        FROM goals g
        WHERE g.athlete_id = ?
        AND g.status = 'active'
        ORDER BY g.target_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $activeGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent performance stats
    $stmt = $pdo->prepare("
        SELECT *
        FROM performance_stats
        WHERE athlete_id = ?
        ORDER BY stat_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $perfStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent evaluations
    $stmt = $pdo->prepare("
        SELECT es.*, efs.name as skill_name, efc.name as category_name
        FROM evaluation_scores es
        LEFT JOIN evaluation_framework_skills efs ON es.skill_id = efs.id
        LEFT JOIN evaluation_framework_categories efc ON efs.category_id = efc.id
        WHERE es.athlete_id = ?
        ORDER BY es.evaluation_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $skillProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Stats data fetch error: " . $e->getMessage());
    $goalsStats = ['total_goals' => 0, 'completed_goals' => 0];
    $streakData = ['streak_days' => 0];
    $skillsData = ['skills_mastered' => 0];
    $activeGoals = [];
    $perfStats = [];
    $skillProgress = [];
}
?>

<div class="stats-content">
    <!-- Stats Overview Cards -->
    <div class="stats-overview">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-bullseye"></i></div>
            <div class="stat-details">
                <h4>Goals Completed</h4>
                <p class="stat-value"><?php echo $goalsStats['completed_goals']; ?> / <?php echo $goalsStats['total_goals']; ?></p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-fire"></i></div>
            <div class="stat-details">
                <h4>Training Streak</h4>
                <p class="stat-value"><?php echo $streakData['streak_days']; ?> days</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <div class="stat-details">
                <h4>Skills Mastered</h4>
                <p class="stat-value"><?php echo $skillsData['skills_mastered']; ?></p>
            </div>
        </div>
    </div>

    <!-- Goals Tracker -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullseye"></i> Goals Tracker</h3>
            <a href="?page=goals" class="btn btn-primary">
                <i class="fas fa-plus"></i> Add Goal
            </a>
        </div>
        <div class="card-body">
            <?php if (count($activeGoals) > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Goal</th>
                                <th>Target Date</th>
                                <th>Progress</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($activeGoals as $goal): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($goal['goal_title'] ?? 'Goal'); ?></td>
                                    <td><?php echo $goal['target_date'] ? date('M d, Y', strtotime($goal['target_date'])) : 'No date'; ?></td>
                                    <td>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo $goal['progress_percentage'] ?? 0; ?>%"></div>
                                        </div>
                                        <span class="progress-text"><?php echo $goal['progress_percentage'] ?? 0; ?>%</span>
                                    </td>
                                    <td><span class="badge badge-success"><?php echo ucfirst($goal['status']); ?></span></td>
                                    <td>
                                        <a href="?page=goals&goal_id=<?php echo $goal['id']; ?>" class="btn-sm btn-secondary">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-bullseye empty-icon"></i>
                    <p class="placeholder-text">No active goals. Start tracking your progress!</p>
                    <a href="?page=goals" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Your First Goal
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Performance Metrics</h3>
            <div class="filter-group">
                <select class="form-select" id="timeRange" data-filter-table="performance">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="all">All Time</option>
                </select>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($perfStats) > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Metric</th>
                                <th>Value</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($perfStats as $stat): ?>
                                <tr>
                                    <td><?php echo date('M d, Y', strtotime($stat['stat_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($stat['metric_name'] ?? 'Performance'); ?></td>
                                    <td><strong><?php echo htmlspecialchars($stat['value']); ?></strong> <?php echo htmlspecialchars($stat['unit'] ?? ''); ?></td>
                                    <td><?php echo htmlspecialchars($stat['notes'] ?? '-'); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="placeholder-text">No performance data recorded yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Skills Progress -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-tasks"></i> Skills Progress</h3>
            <input type="text" class="form-input search-input" placeholder="Search skills..." data-search-table="skills">
        </div>
        <div class="card-body">
            <?php if (count($skillProgress) > 0): ?>
                <div class="table-wrapper">
                    <table class="data-table" id="skills-table">
                        <thead>
                            <tr>
                                <th>Skill</th>
                                <th>Category</th>
                                <th>Score</th>
                                <th>Evaluation Date</th>
                                <th>Feedback</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($skillProgress as $skill): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($skill['skill_name'] ?? 'Skill'); ?></td>
                                    <td><?php echo htmlspecialchars($skill['category_name'] ?? 'General'); ?></td>
                                    <td>
                                        <div class="score-badge score-<?php echo $skill['score']; ?>">
                                            <?php echo $skill['score']; ?>/5
                                        </div>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($skill['evaluation_date'])); ?></td>
                                    <td><?php echo htmlspecialchars(substr($skill['notes'] ?? '-', 0, 50)); ?><?php echo strlen($skill['notes'] ?? '') > 50 ? '...' : ''; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="placeholder-text">No skill evaluations yet. Your coach will evaluate your progress.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.stats-overview {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(0, 0, 0, 0.3);
}

.stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--primary-hover));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    flex-shrink: 0;
}

.stat-details h4 {
    font-size: 13px;
    color: var(--text-dim);
    margin-bottom: 8px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-value {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white);
    margin: 0;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: var(--bg-main);
    border-radius: 4px;
    overflow: hidden;
    margin-bottom: 4px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, var(--primary), var(--primary-hover));
    transition: width 0.3s ease;
}

.progress-text {
    font-size: 12px;
    color: var(--text-dim);
    font-weight: 600;
}

.score-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
}

.score-badge.score-5 { background: rgba(16, 185, 129, 0.15); color: var(--success); }
.score-badge.score-4 { background: rgba(59, 130, 246, 0.15); color: #3B82F6; }
.score-badge.score-3 { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.score-badge.score-2 { background: rgba(251, 146, 60, 0.15); color: #FB923C; }
.score-badge.score-1 { background: rgba(239, 68, 68, 0.15); color: var(--error); }

.empty-state {
    text-align: center;
    padding: 40px 20px;
}

.empty-state .empty-icon {
    font-size: 48px;
    color: var(--text-dim);
    opacity: 0.5;
    margin-bottom: 16px;
    display: block;
}

.empty-state .placeholder-text {
    margin-bottom: 24px;
    font-size: 16px;
    color: var(--text-dim);
}

.empty-state .btn {
    margin-top: 8px;
}
</style>
