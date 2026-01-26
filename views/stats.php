<!-- Stats & Performance View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-chart-line"></i> Performance Stats
    </h1>
    <p class="page-description">Track your progress and achieve your goals</p>
</div>

<?php
// Fetch real stats data - Each query in its own try/catch to prevent one failure from affecting others

// Initialize defaults
$goalsStats = ['total_goals' => 0, 'completed_goals' => 0];
$streakData = ['streak_days' => 0];
$skillsData = ['skills_mastered' => 0];
$activeGoals = [];
$perfStats = [];
$skillProgress = [];

// Get goals stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_goals,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals
        FROM goals 
        WHERE athlete_id = ?
    ");
    $stmt->execute([$user_id]);
    $goalsStats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - goals stats error: " . $e->getMessage());
}

// Get training streak (consecutive days with sessions)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT DATE(s.session_date)) as streak_days
        FROM sessions s
        INNER JOIN bookings b ON s.id = b.session_id
        WHERE b.user_id = ? 
        AND s.session_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
        AND s.status = 'completed'
    ");
    $stmt->execute([$user_id]);
    $streakData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - streak data error: " . $e->getMessage());
}

// Get skills mastered (completed evaluations with high scores)
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT skill_id) as skills_mastered
        FROM evaluation_scores
        WHERE athlete_id = ?
        AND score >= 4
    ");
    $stmt->execute([$user_id]);
    $skillsData = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - skills data error: " . $e->getMessage());
}

// Get active goals
try {
    $stmt = $pdo->prepare("
        SELECT g.id, 
               COALESCE(g.goal_title, g.title) as goal_title, 
               COALESCE(g.goal_description, g.description) as goal_description, 
               g.target_value, 
               g.current_value, g.target_date, g.status,
               CASE 
                   WHEN g.target_value > 0 THEN ROUND((g.current_value / g.target_value) * 100, 0)
                   ELSE ROUND(COALESCE(g.completion_percentage, 0), 0)
               END as progress_percentage
        FROM goals g
        WHERE g.athlete_id = ?
        AND g.status = 'active'
        ORDER BY g.target_date ASC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $activeGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - active goals error: " . $e->getMessage());
}

// Get recent performance stats
try {
    $stmt = $pdo->prepare("
        SELECT ps.id, ps.stat_date, ps.stat_type as metric_name, 
               ps.stat_value as value, ps.stat_unit as unit, ps.notes
        FROM performance_stats ps
        WHERE ps.athlete_id = ?
        ORDER BY ps.stat_date DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id]);
    $perfStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - performance stats error: " . $e->getMessage());
}

// Get recent evaluations - Use eval_skills table if evaluation_framework tables don't exist
try {
    // First check if evaluation_framework tables exist
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'evaluation_framework_skills'")->fetch();
    
    if ($tableCheck) {
        // Use evaluation framework tables
        $stmt = $pdo->prepare("
            SELECT es.*, efs.name as skill_name, efc.name as category_name
            FROM evaluation_scores es
            LEFT JOIN evaluation_framework_skills efs ON es.skill_id = efs.id
            LEFT JOIN evaluation_framework_categories efc ON efs.category_id = efc.id
            WHERE es.athlete_id = ?
            ORDER BY es.evaluation_date DESC
            LIMIT 10
        ");
    } else {
        // Fallback: Use eval_skills and eval_categories tables
        $stmt = $pdo->prepare("
            SELECT es.*, 
                   COALESCE(sk.name, sk.skill_name, 'Skill') as skill_name, 
                   COALESCE(cat.name, cat.category_name, 'General') as category_name
            FROM evaluation_scores es
            LEFT JOIN eval_skills sk ON es.skill_id = sk.id
            LEFT JOIN eval_categories cat ON sk.category_id = cat.id
            WHERE es.athlete_id = ?
            ORDER BY es.evaluation_date DESC
            LIMIT 10
        ");
    }
    $stmt->execute([$user_id]);
    $skillProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - skill progress error: " . $e->getMessage());
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

    <!-- Success Message Widget -->
    <?php 
    $msg = $_GET['msg'] ?? '';
    if ($msg === 'goal_created'): 
    ?>
    <div class="success-message-widget" id="successWidget">
        <i class="fas fa-check-circle"></i>
        <span>Goal created successfully!</span>
        <button type="button" onclick="document.getElementById('successWidget').style.display='none'">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Goals Tracker -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullseye"></i> Goals Tracker</h3>
            <button type="button" class="btn btn-primary" onclick="openGoalModal()">
                <i class="fas fa-plus"></i> Add Goal
            </button>
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
                    <button type="button" class="btn btn-primary" onclick="openGoalModal()">
                        <i class="fas fa-plus"></i> Create Your First Goal
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Goal Creation Modal -->
    <div id="goalModal" class="modal-overlay" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-bullseye"></i> Create New Goal</h3>
                <button type="button" class="modal-close" onclick="closeGoalModal()">&times;</button>
            </div>
            <form id="goalForm" method="POST" action="process_stats_goal.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="create_goal">
                <input type="hidden" name="athlete_id" value="<?php echo $user_id; ?>">
                <input type="hidden" name="return_page" value="stats">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Goal Title *</label>
                        <input type="text" name="title" class="form-input" required placeholder="e.g., Improve skating speed">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-textarea" rows="3" placeholder="Describe your goal..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" class="form-input">
                                <option value="">Select Category</option>
                                <option value="Skating">Skating</option>
                                <option value="Shooting">Shooting</option>
                                <option value="Passing">Passing</option>
                                <option value="Stickhandling">Stickhandling</option>
                                <option value="Conditioning">Conditioning</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Date</label>
                            <input type="date" name="target_date" class="form-input">
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeGoalModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Create Goal</button>
                </div>
            </form>
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
    font-size: 64px;
    color: var(--primary);
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

/* Ensure button icons are visible */
.btn i, .btn-primary i, .btn-secondary i {
    color: inherit;
    font-size: 14px;
}

.card-header .btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.card-header .btn i {
    color: #fff;
}

/* Success Message Widget */
.success-message-widget {
    background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(16, 185, 129, 0.25));
    border: 1px solid rgba(16, 185, 129, 0.5);
    border-radius: 12px;
    padding: 16px 24px;
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
    animation: slideIn 0.3s ease;
}

.success-message-widget i {
    font-size: 24px;
    color: #10B981;
}

.success-message-widget span {
    flex: 1;
    font-weight: 600;
    color: #10B981;
}

.success-message-widget button {
    background: none;
    border: none;
    color: #10B981;
    font-size: 20px;
    cursor: pointer;
    padding: 4px 8px;
    opacity: 0.7;
    transition: opacity 0.2s;
}

.success-message-widget button:hover {
    opacity: 1;
}

@keyframes slideIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Modal Styles */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.8);
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-content {
    background: var(--bg-card, #16161F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 16px;
    width: 90%;
    max-width: 500px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
}

.modal-header {
    padding: 20px 24px;
    border-bottom: 1px solid var(--border, #2D2D3F);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.modal-header h3 i {
    color: var(--primary);
}

.modal-close {
    background: none;
    border: none;
    color: var(--text-dim);
    font-size: 24px;
    cursor: pointer;
    padding: 4px;
    transition: color 0.2s;
}

.modal-close:hover {
    color: #fff;
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border, #2D2D3F);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}

.form-group {
    margin-bottom: 16px;
}

.form-label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: var(--text-dim);
    margin-bottom: 8px;
}

.form-input, .form-textarea {
    width: 100%;
    padding: 12px 16px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    font-family: inherit;
    transition: border-color 0.2s;
}

.form-input:focus, .form-textarea:focus {
    outline: none;
    border-color: var(--primary);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}

@media (max-width: 500px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
function openGoalModal() {
    document.getElementById('goalModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeGoalModal() {
    document.getElementById('goalModal').style.display = 'none';
    document.body.style.overflow = '';
}

// Close modal on outside click
document.getElementById('goalModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeGoalModal();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeGoalModal();
    }
});
</script>
