<!-- Stats & Performance View (Combined Goals and Stats) -->
<?php
// Determine viewing athlete ID (for coaches viewing athlete data)
$viewing_athlete_id = $user_id;
$current_user_role = $user_role;

// Coaches and parents can switch between athletes
if (($isCoach || $isParent || $isAdmin) && isset($_GET['athlete_id'])) {
    $viewing_athlete_id = intval($_GET['athlete_id']);
}

// Get active tab (default to goals)
$active_tab = $_GET['tab'] ?? 'goals';

// Get athlete list for coaches
$athletes = [];
if ($isCoach) {
    try {
        $athletes_query = "
            SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
            FROM users u
            WHERE u.role = 'athlete' AND u.is_active = 1
            ORDER BY u.last_name, u.first_name
        ";
        $athletes = $pdo->query($athletes_query)->fetchAll();
        $athletes = decryptUserRows($athletes);
    } catch (PDOException $e) {
        error_log("Stats - fetch athletes error: " . $e->getMessage());
    }
}

// Get athlete info for display
$athlete_info = null;
if ($viewing_athlete_id != $user_id) {
    try {
        $athlete_stmt = $pdo->prepare("SELECT first_name, last_name FROM users WHERE id = ?");
        $athlete_stmt->execute([$viewing_athlete_id]);
        $athlete_info = $athlete_stmt->fetch();
        $athlete_info = decryptUserRow($athlete_info);
    } catch (PDOException $e) {
        error_log("Stats - fetch athlete info error: " . $e->getMessage());
    }
}
?>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title">
            <i class="fas fa-chart-line"></i> Performance Stats
            <?php if ($athlete_info): ?>
                <span style="font-size: 20px; color: #a78bfa;"> — <?php echo htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']); ?></span>
            <?php endif; ?>
        </h1>
        <p class="page-description">
            <?php if ($athlete_info): ?>
                Viewing stats and goals for <strong><?php echo htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']); ?></strong>
            <?php else: ?>
                Track your progress and achieve your goals
            <?php endif; ?>
        </p>
    </div>
    <div class="page-header-actions">
        <?php if ($isCoach && count($athletes) > 0): ?>
            <select class="athlete-selector" onchange="window.location.href='?page=stats&tab=<?php echo $active_tab; ?>&athlete_id=' + this.value">
                <option value="">Select Athlete</option>
                <?php foreach ($athletes as $athlete): ?>
                    <option value="<?php echo $athlete['id']; ?>" 
                            <?php echo $viewing_athlete_id == $athlete['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($athlete['last_name'] . ', ' . $athlete['first_name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php endif; ?>
    </div>
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
$userTeams = [];

// Get goals stats
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_goals,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_goals
        FROM goals 
        WHERE athlete_id = ?
    ");
    $stmt->execute([$viewing_athlete_id]);
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
    $stmt->execute([$viewing_athlete_id]);
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
    $stmt->execute([$viewing_athlete_id]);
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
    $stmt->execute([$viewing_athlete_id]);
    $activeGoals = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - active goals error: " . $e->getMessage());
}

// Get lap times (recent and stats)
$lapTimes = [];
$lapTimeStats = ['best' => null, 'avg' => null, 'count' => 0];
try {
    // Get recent lap times
    $stmt = $pdo->prepare("
        SELECT ps.stat_value as lap_time, ps.stat_date, ps.notes, ps.created_at
        FROM performance_stats ps
        WHERE ps.athlete_id = ? AND ps.stat_type = 'lap_time'
        ORDER BY ps.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$viewing_athlete_id]);
    $lapTimes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get lap time statistics
    $stmt = $pdo->prepare("
        SELECT 
            MIN(stat_value) as best,
            AVG(stat_value) as avg,
            COUNT(*) as count
        FROM performance_stats
        WHERE athlete_id = ? AND stat_type = 'lap_time'
    ");
    $stmt->execute([$viewing_athlete_id]);
    $lapTimeStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $lapTimeStats;
} catch (PDOException $e) {
    error_log("Stats - lap times error: " . $e->getMessage());
}

// Get shot speeds (recent and stats)
$shotSpeeds = [];
$shotSpeedStats = ['max_mph' => null, 'avg_mph' => null, 'max_kmh' => null, 'avg_kmh' => null, 'count' => 0];
try {
    // Get recent shot speeds
    $stmt = $pdo->prepare("
        SELECT ps.stat_value as speed, ps.stat_unit as unit, ps.stat_date, ps.notes, ps.created_at
        FROM performance_stats ps
        WHERE ps.athlete_id = ? AND ps.stat_type = 'shot_speed'
        ORDER BY ps.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$viewing_athlete_id]);
    $shotSpeeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get shot speed statistics (grouped by unit)
    $stmt = $pdo->prepare("
        SELECT 
            stat_unit,
            MAX(stat_value) as max_speed,
            AVG(stat_value) as avg_speed,
            COUNT(*) as count
        FROM performance_stats
        WHERE athlete_id = ? AND stat_type = 'shot_speed'
        GROUP BY stat_unit
    ");
    $stmt->execute([$viewing_athlete_id]);
    $speedsByUnit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($speedsByUnit as $row) {
        if ($row['stat_unit'] === 'mph') {
            $shotSpeedStats['max_mph'] = $row['max_speed'];
            $shotSpeedStats['avg_mph'] = $row['avg_speed'];
        } elseif ($row['stat_unit'] === 'km/h') {
            $shotSpeedStats['max_kmh'] = $row['max_speed'];
            $shotSpeedStats['avg_kmh'] = $row['avg_speed'];
        }
        $shotSpeedStats['count'] += $row['count'];
    }
} catch (PDOException $e) {
    error_log("Stats - shot speeds error: " . $e->getMessage());
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
    $stmt->execute([$viewing_athlete_id]);
    $perfStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - performance stats error: " . $e->getMessage());
}

// Get recent evaluations - Use eval_skills table if evaluation_framework tables don't exist
try {
    // Check if evaluation_framework tables exist using INFORMATION_SCHEMA with prepared statement
    $tableCheckStmt = $pdo->prepare("
        SELECT COUNT(*) as table_exists 
        FROM INFORMATION_SCHEMA.TABLES 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = ?
    ");
    $tableCheckStmt->execute(['evaluation_framework_skills']);
    $tableCheck = $tableCheckStmt->fetch();
    
    if ($tableCheck && $tableCheck['table_exists'] > 0) {
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
    $stmt->execute([$viewing_athlete_id]);
    $skillProgress = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - skill progress error: " . $e->getMessage());
}

// Get user's teams for performance stats
try {
    $teamsStmt = $pdo->prepare("
        SELECT id, team_name, league, position, season_year, season_type, season, is_current, created_at
        FROM athlete_teams 
        WHERE user_id = ? OR athlete_id = ?
        ORDER BY is_current DESC, created_at DESC
    ");
    $teamsStmt->execute([$viewing_athlete_id, $viewing_athlete_id]);
    $userTeams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $teamsError) {
    // Fallback without specific columns
    try {
        $teamsStmt = $pdo->prepare("
            SELECT id, team_name, '' as league, '' as position, season_year, season_type, season, is_current, created_at
            FROM athlete_teams 
            WHERE user_id = ? OR athlete_id = ?
            ORDER BY is_current DESC, created_at DESC
        ");
        $teamsStmt->execute([$viewing_athlete_id, $viewing_athlete_id]);
        $userTeams = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $fallbackError) {
        error_log("Failed to load user teams: " . $fallbackError->getMessage());
    }
}

// Also include teams from team_roster that may not be in athlete_teams yet
try {
    $rosterTeamsStmt = $pdo->prepare("
        SELECT NULL as id, t.name as team_name, '' as league, tr.position, '' as season_year, '' as season_type, 
               s.name as season, 1 as is_current, tr.joined_date as created_at
        FROM team_roster tr
        INNER JOIN teams t ON tr.team_id = t.id
        LEFT JOIN seasons s ON tr.season_id = s.id
        WHERE tr.athlete_id = ?
    ");
    $rosterTeamsStmt->execute([$viewing_athlete_id]);
    $rosterAssigned = $rosterTeamsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $existingKeys = [];
    foreach ($userTeams as $ut) {
        $existingKeys[($ut['team_name'] ?? '') . '|' . ($ut['season'] ?? '')] = true;
    }
    foreach ($rosterAssigned as $rt) {
        $key = ($rt['team_name'] ?? '') . '|' . ($rt['season'] ?? '');
        if (!isset($existingKeys[$key])) {
            $userTeams[] = $rt;
            $existingKeys[$key] = true;
        }
    }
} catch (PDOException $e) {
    // team_roster query may fail if table doesn't exist
}

// Get athlete stats based on teams
$athleteStats = [];
try {
    $statsStmt = $pdo->prepare("
        SELECT * FROM athlete_stats 
        WHERE user_id = ? 
        ORDER BY season DESC
    ");
    $statsStmt->execute([$viewing_athlete_id]);
    $athleteStats = $statsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Stats - athlete stats error: " . $e->getMessage());
}

// Get all goals for goal tracker tab (including with steps)
$allGoals = [];
$filter_status = $_GET['status'] ?? 'active';
$filter_category = $_GET['category'] ?? '';

try {
    $goals_query = "
        SELECT g.*,
               u.first_name as creator_first_name, u.last_name as creator_last_name,
               (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id) as total_steps,
               (SELECT COUNT(*) FROM goal_steps WHERE goal_id = g.id AND is_completed = 1) as completed_steps
        FROM goals g
        LEFT JOIN users u ON g.created_by = u.id
        WHERE g.athlete_id = ?
    ";
    
    $params = [$viewing_athlete_id];
    
    // Apply status filter
    if ($filter_status === 'active') {
        $goals_query .= " AND g.status = 'active'";
    } elseif ($filter_status === 'completed') {
        $goals_query .= " AND g.status = 'completed'";
    } elseif ($filter_status === 'archived') {
        $goals_query .= " AND g.status = 'archived'";
    }
    
    // Apply category filter
    if (!empty($filter_category)) {
        $goals_query .= " AND g.category = ?";
        $params[] = $filter_category;
    }
    
    $goals_query .= " ORDER BY g.created_at DESC";
    
    $goals_stmt = $pdo->prepare($goals_query);
    $goals_stmt->execute($params);
    $allGoals = $goals_stmt->fetchAll();
    $allGoals = decryptUserRows($allGoals);
} catch (PDOException $e) {
    error_log("Stats - all goals error: " . $e->getMessage());
}

// Get all categories for filter
$categories = [];
try {
    $categories = $pdo->query("SELECT DISTINCT category FROM goals WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Stats - categories error: " . $e->getMessage());
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

    <!-- Tab Navigation -->
    <div class="stats-tabs-wrapper">
        <div class="stats-tabs">
            <a href="?page=stats&tab=goals<?php echo $viewing_athlete_id != $user_id ? '&athlete_id=' . $viewing_athlete_id : ''; ?>" 
               class="stats-tab-btn <?php echo $active_tab === 'goals' ? 'active' : ''; ?>">
                <i class="fas fa-bullseye"></i>
                <span>Goal Tracker</span>
            </a>
            <a href="?page=stats&tab=performance<?php echo $viewing_athlete_id != $user_id ? '&athlete_id=' . $viewing_athlete_id : ''; ?>" 
               class="stats-tab-btn <?php echo $active_tab === 'performance' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                <span>Performance Stats</span>
            </a>
        </div>
    </div>

    <!-- Success Message Widget -->
    <?php 
    $msg = $_GET['msg'] ?? '';
    if ($msg === 'goal_created' || $msg === 'created'): 
    ?>
    <div class="success-message-widget" id="successWidget">
        <i class="fas fa-check-circle"></i>
        <span>Goal created successfully!</span>
        <button type="button" onclick="document.getElementById('successWidget').style.display='none'" aria-label="Dismiss message">&times;</button>
    </div>
    <?php elseif ($msg === 'updated'): ?>
    <div class="success-message-widget" id="successWidget">
        <i class="fas fa-check-circle"></i>
        <span>Goal updated successfully!</span>
        <button type="button" onclick="document.getElementById('successWidget').style.display='none'" aria-label="Dismiss message">&times;</button>
    </div>
    <?php elseif ($msg === 'archived'): ?>
    <div class="success-message-widget" id="successWidget">
        <i class="fas fa-check-circle"></i>
        <span>Goal archived successfully!</span>
        <button type="button" onclick="document.getElementById('successWidget').style.display='none'" aria-label="Dismiss message">&times;</button>
    </div>
    <?php elseif ($msg === 'progress_added'): ?>
    <div class="success-message-widget" id="successWidget">
        <i class="fas fa-check-circle"></i>
        <span>Progress note added successfully!</span>
        <button type="button" onclick="document.getElementById('successWidget').style.display='none'" aria-label="Dismiss message">&times;</button>
    </div>
    <?php endif; ?>

    <!-- TAB 1: Goal Tracker -->
    <div class="tab-content <?php echo $active_tab === 'goals' ? 'active' : ''; ?>" id="goals-tab">
        <?php if ($athlete_info): ?>
        <div class="athlete-goals-banner">
            <i class="fas fa-user"></i>
            <span>Viewing goals for <strong><?php echo htmlspecialchars($athlete_info['first_name'] . ' ' . $athlete_info['last_name']); ?></strong></span>
        </div>
        <?php endif; ?>
        <!-- Filter Bar -->
        <div class="filters-bar">
            <div class="filter-group">
                <label class="filter-label">Status</label>
                <select class="filter-select" onchange="updateFilter('status', this.value)">
                    <option value="active" <?php echo $filter_status === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="archived" <?php echo $filter_status === 'archived' ? 'selected' : ''; ?>>Archived</option>
                    <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All</option>
                </select>
            </div>
            <?php if (count($categories) > 0): ?>
                <div class="filter-group">
                    <label class="filter-label">Category</label>
                    <select class="filter-select" onchange="updateFilter('category', this.value)">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" 
                                    <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            <?php if ($isCoach || $viewing_athlete_id == $user_id): ?>
            <div class="filter-group filter-action">
                <button type="button" class="btn btn-primary" onclick="openCreateGoalModal()">
                    <i class="fas fa-plus"></i> Create Goal
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Goals Grid -->
        <?php if (count($allGoals) > 0): ?>
            <div class="goals-grid">
                <?php foreach ($allGoals as $goal): 
                    $progress_pct = $goal['completion_percentage'] ?? 0;
                    $is_completed = $goal['status'] === 'completed';
                ?>
                    <div class="goal-card <?php echo $is_completed ? 'completed' : ''; ?>">
                        <?php if ($goal['category']): ?>
                            <span class="goal-category"><?php echo htmlspecialchars($goal['category']); ?></span>
                        <?php endif; ?>
                        
                        <h3 class="goal-title"><?php echo htmlspecialchars($goal['title']); ?></h3>
                        
                        <?php if ($goal['description']): ?>
                            <p class="goal-description"><?php echo nl2br(htmlspecialchars(substr($goal['description'], 0, 100))); ?><?php echo strlen($goal['description']) > 100 ? '...' : ''; ?></p>
                        <?php endif; ?>
                        
                        <div class="goal-progress">
                            <div class="progress-label">
                                <span>Progress</span>
                                <span><strong><?php echo round($progress_pct); ?>%</strong></span>
                            </div>
                            <div class="progress-bar-container">
                                <div class="progress-bar" style="width: <?php echo $progress_pct; ?>%"></div>
                            </div>
                        </div>
                        
                        <div class="goal-meta">
                            <span>
                                <i class="fas fa-list-check"></i> 
                                <?php echo $goal['completed_steps']; ?> / <?php echo $goal['total_steps']; ?> steps
                            </span>
                            <?php if ($goal['target_date']): ?>
                                <span>
                                    <i class="fas fa-calendar"></i> 
                                    <?php echo date('M d, Y', strtotime($goal['target_date'])); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="goal-actions">
                            <button class="btn-goal-action btn-view" onclick="viewGoalDetail(<?php echo $goal['id']; ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <?php if ($isCoach || $viewing_athlete_id == $user_id): ?>
                                <button class="btn-goal-action btn-edit" onclick="editGoal(<?php echo $goal['id']; ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <?php if (!$is_completed): ?>
                                    <button class="btn-goal-action btn-complete" onclick="completeGoal(<?php echo $goal['id']; ?>)">
                                        <i class="fas fa-check"></i>
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-bullseye empty-icon"></i>
                <h3>No Goals Found</h3>
                <p class="placeholder-text">
                    <?php if ($isCoach || $viewing_athlete_id == $user_id): ?>
                        Create a goal to start tracking progress
                    <?php else: ?>
                        Your coach will create goals for you to work towards
                    <?php endif; ?>
                </p>
                <?php if ($isCoach || $viewing_athlete_id == $user_id): ?>
                <button type="button" class="btn btn-primary" onclick="openCreateGoalModal()">
                    <i class="fas fa-plus"></i> Create Your First Goal
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- TAB 2: Performance Stats -->
    <div class="tab-content <?php echo $active_tab === 'performance' ? 'active' : ''; ?>" id="performance-tab">
        <!-- Team-based Performance Stats -->
        <?php if (count($userTeams) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-hockey-puck"></i> Team Performance</h3>
            </div>
            <div class="card-body">
                <div class="teams-stats-grid">
                    <?php foreach ($userTeams as $team): ?>
                    <div class="team-stats-card <?php echo $team['is_current'] ? 'current-team' : ''; ?>">
                        <div class="team-info">
                            <h4><?php echo htmlspecialchars($team['team_name']); ?></h4>
                            <?php if ($team['league']): ?>
                                <span class="league-badge"><?php echo htmlspecialchars($team['league']); ?></span>
                            <?php endif; ?>
                            <?php if ($team['season_year'] || $team['season']): ?>
                                <span class="season-info"><?php echo htmlspecialchars($team['season_year'] ?? $team['season']); ?></span>
                            <?php endif; ?>
                            <?php if ($team['is_current']): ?>
                                <span class="current-badge">Current</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($team['position']): ?>
                            <div class="team-position">Position: <?php echo htmlspecialchars($team['position']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Athlete Stats by Season -->
        <?php if (count($athleteStats) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-bar"></i> Season Statistics</h3>
            </div>
            <div class="card-body">
                <div class="table-wrapper">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Season</th>
                                <th>GP</th>
                                <th>Goals</th>
                                <th>Assists</th>
                                <th>Points</th>
                                <th>+/-</th>
                                <th>PIM</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($athleteStats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['season'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($stat['games_played'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($stat['goals'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($stat['assists'] ?? 0); ?></td>
                                    <td><strong><?php echo htmlspecialchars($stat['points'] ?? (($stat['goals'] ?? 0) + ($stat['assists'] ?? 0))); ?></strong></td>
                                    <td><?php echo htmlspecialchars($stat['plus_minus'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars($stat['penalty_minutes'] ?? 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Speed & Power Metrics -->
        <?php if (count($lapTimes) > 0 || count($shotSpeeds) > 0): ?>
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Speed & Power</h3>
            </div>
            <div class="card-body">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 24px;">
                    
                    <!-- Lap Times Section -->
                    <?php if (count($lapTimes) > 0): ?>
                    <div>
                        <h4 style="margin-bottom: 16px; color: var(--text-white); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-stopwatch"></i> Lap Times
                        </h4>
                        
                        <!-- Stats Summary -->
                        <?php if ($lapTimeStats['count'] > 0): ?>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                            <div class="stat-box">
                                <div class="stat-label">Best Time</div>
                                <div class="stat-value"><?php echo number_format($lapTimeStats['best'], 2); ?>s</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Average</div>
                                <div class="stat-value"><?php echo number_format($lapTimeStats['avg'], 2); ?>s</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Total Laps</div>
                                <div class="stat-value"><?php echo $lapTimeStats['count']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Recent Lap Times -->
                        <div class="metric-list">
                            <div class="metric-header">
                                <span>Recent Times</span>
                                <span>Date</span>
                            </div>
                            <?php foreach (array_slice($lapTimes, 0, 5) as $lap): ?>
                                <div class="metric-item">
                                    <div>
                                        <span class="metric-value" style="font-family: monospace; font-weight: bold; color: var(--primary-light);">
                                            <?php echo number_format($lap['lap_time'], 2); ?>s
                                        </span>
                                        <?php if ($lap['notes']): ?>
                                            <span class="metric-note"><?php echo htmlspecialchars($lap['notes']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="metric-date"><?php echo date('M j, Y', strtotime($lap['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Shot Speed Section -->
                    <?php if (count($shotSpeeds) > 0): ?>
                    <div>
                        <h4 style="margin-bottom: 16px; color: var(--text-white); display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-hockey-puck"></i> Shot Speed
                        </h4>
                        
                        <!-- Stats Summary -->
                        <?php if ($shotSpeedStats['count'] > 0): ?>
                        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 16px;">
                            <?php if ($shotSpeedStats['max_mph']): ?>
                            <div class="stat-box">
                                <div class="stat-label">Max Speed</div>
                                <div class="stat-value"><?php echo number_format($shotSpeedStats['max_mph'], 1); ?> MPH</div>
                            </div>
                            <div class="stat-box">
                                <div class="stat-label">Average</div>
                                <div class="stat-value"><?php echo number_format($shotSpeedStats['avg_mph'], 1); ?> MPH</div>
                            </div>
                            <?php endif; ?>
                            <?php if ($shotSpeedStats['max_kmh']): ?>
                            <div class="stat-box">
                                <div class="stat-label">Max Speed</div>
                                <div class="stat-value"><?php echo number_format($shotSpeedStats['max_kmh'], 1); ?> KM/H</div>
                            </div>
                            <?php endif; ?>
                            <div class="stat-box">
                                <div class="stat-label">Total Shots</div>
                                <div class="stat-value"><?php echo $shotSpeedStats['count']; ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Recent Shot Speeds -->
                        <div class="metric-list">
                            <div class="metric-header">
                                <span>Recent Shots</span>
                                <span>Date</span>
                            </div>
                            <?php foreach (array_slice($shotSpeeds, 0, 5) as $shot): ?>
                                <div class="metric-item">
                                    <div>
                                        <span class="metric-value" style="font-family: monospace; font-weight: bold; color: var(--success);">
                                            <?php echo number_format($shot['speed'], 1); ?> <?php echo htmlspecialchars($shot['unit']); ?>
                                        </span>
                                        <?php if ($shot['notes']): ?>
                                            <span class="metric-note"><?php echo htmlspecialchars($shot['notes']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="metric-date"><?php echo date('M j, Y', strtotime($shot['created_at'])); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Performance Metrics -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-line"></i> Performance Metrics</h3>
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
                    <div class="empty-state-sm">
                        <i class="fas fa-chart-line"></i>
                        <p class="placeholder-text">No performance data recorded yet. Performance metrics will appear here based on your training sessions and evaluations.</p>
                    </div>
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
                    <div class="empty-state-sm">
                        <i class="fas fa-tasks"></i>
                        <p class="placeholder-text">No skill evaluations yet. Your coach will evaluate your progress.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($userTeams) == 0 && count($athleteStats) == 0 && count($perfStats) == 0): ?>
        <div class="empty-state">
            <i class="fas fa-chart-bar empty-icon"></i>
            <h3>No Performance Data Yet</h3>
            <p class="placeholder-text">
                Add teams to your profile settings to start tracking performance statistics. 
                Performance stats will be automatically organized based on your team history.
            </p>
            <a href="?page=profile&tab=player" class="btn btn-primary">
                <i class="fas fa-cog"></i> Go to Profile Settings
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Goal Creation/Edit Modal -->
    <div id="goalModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 class="modal-title" id="modalTitle"><i class="fas fa-bullseye"></i> Create Goal</h2>
                <button class="modal-close" aria-label="Close modal" onclick="closeGoalModal()">&times;</button>
            </div>
            <form id="goalForm" method="POST" action="process_goals.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" id="formAction" value="create_goal">
                <input type="hidden" name="goal_id" id="goalId" value="">
                <input type="hidden" name="athlete_id" value="<?php echo $viewing_athlete_id; ?>">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Title *</label>
                        <input type="text" name="title" id="goalTitle" class="form-input" required placeholder="e.g., Improve skating speed">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="goalDescription" class="form-textarea" rows="3" placeholder="Describe your goal..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Category</label>
                            <select name="category" id="goalCategory" class="form-input">
                                <option value="">Select Category</option>
                                <option value="Skating">Skating</option>
                                <option value="Shooting">Shooting</option>
                                <option value="Passing">Passing</option>
                                <option value="Stickhandling">Stickhandling</option>
                                <option value="Conditioning">Conditioning</option>
                                <option value="Defense">Defense</option>
                                <option value="Goaltending">Goaltending</option>
                                <option value="Mental">Mental</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Target Date</label>
                            <input type="date" name="target_date" id="goalTargetDate" class="form-input">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Tags (comma-separated)</label>
                        <input type="text" name="tags" id="goalTags" class="form-input" placeholder="e.g., speed, power, technique">
                    </div>
                    
                    <!-- Steps Section -->
                    <div class="steps-section">
                        <div class="steps-header">
                            <h3 class="steps-title"><i class="fas fa-list-check"></i> Steps (Optional)</h3>
                            <button type="button" class="btn-add-step" onclick="addStep()">
                                <i class="fas fa-plus"></i> Add Step
                            </button>
                        </div>
                        <p class="steps-help">Break down your goal into smaller, achievable steps</p>
                        <div class="steps-list" id="stepsList">
                            <!-- Steps will be added dynamically -->
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeGoalModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Goal</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Goal Detail Modal -->
    <div id="goalDetailModal" class="modal" style="display: none;">
        <div class="modal-content modal-lg">
            <div class="modal-header">
                <h2 class="modal-title">Goal Details</h2>
                <button class="modal-close" aria-label="Close modal" onclick="closeGoalDetailModal()">&times;</button>
            </div>
            <div class="modal-body" id="goalDetailContent">
                <!-- Content loaded via AJAX -->
                <p style="text-align:center;padding:20px;">Loading...</p>
            </div>
        </div>
    </div>

    <!-- Progress Note Modal -->
    <div id="progressNoteModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title"><i class="fas fa-plus-circle"></i> Add Progress Note</h2>
                <button class="modal-close" aria-label="Close modal" onclick="closeProgressNoteModal()">&times;</button>
            </div>
            <form id="progressNoteForm" method="POST" action="process_goals.php">
                <?php echo csrfTokenInput(); ?>
                <input type="hidden" name="action" value="update_progress">
                <input type="hidden" name="goal_id" id="progressGoalId" value="">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Progress Note *</label>
                        <textarea name="progress_note" class="form-textarea" required placeholder="Describe your progress..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Progress Percentage (Optional)</label>
                        <input type="number" name="progress_percentage" class="form-input" min="0" max="100" placeholder="Leave blank to auto-calculate from steps">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeProgressNoteModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-check"></i> Save Progress</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.athlete-goals-banner {
    background: rgba(59, 130, 246, 0.1);
    border: 1px solid rgba(59, 130, 246, 0.3);
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 10px;
    color: #3b82f6;
    font-size: 14px;
}

.athlete-goals-banner i {
    font-size: 16px;
}

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

/* Tab Navigation Styles */
.stats-tabs-wrapper {
    margin-bottom: -1px;
}

.stats-tabs {
    display: flex;
    gap: 0;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px 12px 0 0;
    overflow: hidden;
}

.stats-tab-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 18px 24px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    color: var(--text-dim);
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s;
}

.stats-tab-btn:hover {
    background: rgba(139, 92, 246, 0.05);
    color: var(--text-white);
}

.stats-tab-btn.active {
    background: rgba(139, 92, 246, 0.1);
    color: var(--primary);
    border-bottom-color: var(--primary);
}

.stats-tab-btn i {
    font-size: 16px;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Athlete Selector */
.athlete-selector {
    padding: 10px 16px;
    background: var(--bg, #0d1117);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    min-width: 200px;
}

.athlete-selector:hover {
    border-color: var(--primary, #6B46C1);
}

/* Filter Bar */
.filters-bar {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 16px 20px;
    margin-bottom: 24px;
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.filter-group.filter-action {
    margin-left: auto;
}

.filter-label {
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    font-weight: 700;
    letter-spacing: 0.5px;
}

.filter-select {
    padding: 10px 14px;
    background: var(--bg-main, #0A0A0F);
    border: 1px solid var(--border, #2D2D3F);
    border-radius: 8px;
    color: #fff;
    font-size: 14px;
    min-width: 140px;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-select:hover {
    border-color: var(--primary);
}

/* Goals Grid */
.goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
    gap: 20px;
    margin-bottom: 24px;
}

.goal-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    transition: all 0.2s;
}

.goal-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 70, 193, 0.2);
}

.goal-card.completed {
    opacity: 0.8;
    border-color: #10b981;
}

.goal-category {
    display: inline-block;
    padding: 4px 10px;
    background: rgba(107, 70, 193, 0.2);
    border: 1px solid var(--primary);
    border-radius: 6px;
    font-size: 11px;
    color: var(--primary-light, #8B5CF6);
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.goal-title {
    font-size: 18px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 8px 0;
}

.goal-description {
    color: var(--text-dim);
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 12px;
}

.goal-progress {
    margin: 12px 0;
}

.progress-label {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
    font-size: 12px;
    color: var(--text-dim);
}

.progress-bar-container {
    width: 100%;
    height: 8px;
    background: var(--bg-main);
    border-radius: 4px;
    overflow: hidden;
}

.goal-meta {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border);
    font-size: 12px;
    color: var(--text-dim);
}

.goal-meta i {
    margin-right: 4px;
}

.goal-actions {
    display: flex;
    gap: 8px;
    margin-top: 12px;
}

.btn-goal-action {
    flex: 1;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    text-align: center;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn-view {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary-light, #8B5CF6);
}

.btn-view:hover {
    background: rgba(107, 70, 193, 0.1);
}

.btn-edit {
    background: var(--primary);
    color: #fff;
}

.btn-edit:hover {
    background: var(--primary-hover);
}

.btn-complete {
    background: #10b981;
    color: #fff;
}

.btn-complete:hover {
    background: #059669;
}

/* Team Stats Styles */
.teams-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
}

.team-stats-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
}

.team-stats-card.current-team {
    border-color: var(--primary);
    background: rgba(107, 70, 193, 0.1);
}

.team-info h4 {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    margin: 0 0 8px 0;
}

.league-badge {
    display: inline-block;
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    margin-right: 8px;
}

.season-info {
    font-size: 12px;
    color: var(--text-dim);
}

.current-badge {
    display: inline-block;
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    margin-left: 8px;
}

.team-position {
    font-size: 13px;
    color: var(--text-dim);
    margin-top: 8px;
}

/* Empty State Small */
.empty-state-sm {
    text-align: center;
    padding: 30px 20px;
    color: var(--text-dim);
}

.empty-state-sm i {
    font-size: 40px;
    opacity: 0.5;
    margin-bottom: 12px;
    display: block;
    color: var(--primary);
}

/* Modal Enhancements */
.modal {
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
}

.modal-lg {
    max-width: 700px;
}

.modal-title {
    font-size: 20px;
    font-weight: 700;
    color: #fff;
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0;
}

.modal-title i {
    color: var(--primary);
}

/* Steps Section */
.steps-section {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.steps-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.steps-title {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.steps-title i {
    color: var(--primary);
}

.steps-help {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 12px;
}

.btn-add-step {
    background: transparent;
    border: 1px solid var(--primary);
    color: var(--primary-light);
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-add-step:hover {
    background: rgba(107, 70, 193, 0.1);
}

.steps-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.step-item {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 12px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.step-handle {
    cursor: move;
    color: var(--text-dim);
    padding: 4px;
}

.step-content {
    flex: 1;
}

.step-input {
    width: 100%;
    padding: 8px 12px;
    background: transparent;
    border: 1px solid var(--border);
    border-radius: 6px;
    color: #fff;
    font-size: 14px;
}

.step-input:focus {
    outline: none;
    border-color: var(--primary);
}

.step-remove {
    background: transparent;
    border: none;
    color: #ef4444;
    cursor: pointer;
    padding: 4px 8px;
    font-size: 14px;
}

.step-remove:hover {
    color: #dc2626;
}

/* Goal Detail Styles */
.step-detail-item {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 10px;
    display: flex;
    align-items: start;
    gap: 12px;
}

.step-detail-item.completed {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.05);
}

.step-checkbox {
    width: 20px;
    height: 20px;
    cursor: pointer;
    margin-top: 2px;
}

.step-detail-content {
    flex: 1;
}

.step-detail-title {
    font-size: 14px;
    font-weight: 600;
    color: #fff;
    margin-bottom: 4px;
}

.step-detail-description {
    font-size: 13px;
    color: var(--text-dim);
}

.step-completed-info {
    font-size: 11px;
    color: #10b981;
    margin-top: 6px;
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.status-active {
    background: rgba(59, 130, 246, 0.2);
    color: #3b82f6;
}

.status-completed {
    background: rgba(16, 185, 129, 0.2);
    color: #10b981;
}

.status-archived {
    background: rgba(100, 116, 139, 0.2);
    color: #64748b;
}

/* Progress History */
.progress-history {
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--border);
}

.progress-history-title {
    font-size: 16px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 12px;
}

.progress-entry {
    background: var(--bg-main);
    border-left: 3px solid var(--primary);
    padding: 12px 15px;
    margin-bottom: 12px;
    border-radius: 0 4px 4px 0;
}

.progress-entry-header {
    display: flex;
    justify-content: space-between;
    margin-bottom: 6px;
}

.progress-entry-user {
    font-size: 13px;
    font-weight: 600;
    color: #fff;
}

.progress-entry-date {
    font-size: 12px;
    color: var(--text-dim);
}

.progress-entry-note {
    font-size: 13px;
    color: var(--text-dim);
    line-height: 1.5;
}

.btn-add-progress {
    background: var(--primary);
    border: none;
    color: #fff;
    padding: 10px 20px;
    border-radius: 6px;
    font-weight: 600;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add-progress:hover {
    background: var(--primary-hover);
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.page-header-content {
    flex: 1;
}

.page-header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

@media (max-width: 768px) {
    .stats-tabs {
        flex-direction: column;
    }
    
    .stats-tab-btn {
        justify-content: center;
    }
    
    .goals-grid {
        grid-template-columns: 1fr;
    }
    
    .filters-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group.filter-action {
        margin-left: 0;
        margin-top: 8px;
    }
    
    .page-header {
        flex-direction: column;
    }
    
    .page-header-actions {
        width: 100%;
    }
    
    .athlete-selector {
        width: 100%;
    }
}

/* Speed & Power Styles */
.stat-box {
    background: var(--bg-secondary);
    padding: 12px;
    border-radius: var(--radius-md);
    text-align: center;
    border: 1px solid var(--border);
}

.stat-box .stat-label {
    font-size: 11px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.stat-box .stat-value {
    font-size: 20px;
    font-weight: var(--font-weight-bold);
    color: var(--primary-light);
    font-family: 'Courier New', monospace;
}

.metric-list {
    background: var(--bg-secondary);
    border-radius: var(--radius-md);
    overflow: hidden;
}

.metric-header {
    display: flex;
    justify-content: space-between;
    padding: 12px 16px;
    background: var(--bg-main);
    font-size: 12px;
    font-weight: var(--font-weight-semibold);
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.metric-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid rgba(255,255,255,0.03);
}

.metric-item:last-child {
    border-bottom: none;
}

.metric-item:hover {
    background: rgba(107, 70, 193, 0.05);
}

.metric-value {
    font-size: 18px;
}

.metric-note {
    display: block;
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 2px;
}

.metric-date {
    font-size: 12px;
    color: var(--text-muted);
}
</style>

<script>
let stepCounter = 0;
const isCoach = <?php echo json_encode($isCoach); ?>;
const viewingAthleteId = <?php echo json_encode($viewing_athlete_id); ?>;
const currentUserId = <?php echo json_encode($user_id); ?>;
const canManageGoals = isCoach || viewingAthleteId === currentUserId;

function updateFilter(type, value) {
    const url = new URL(window.location.href);
    url.searchParams.set(type, value);
    url.searchParams.set('tab', 'goals');
    window.location.href = url.toString();
}

function openCreateGoalModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-bullseye"></i> Create Goal';
    document.getElementById('formAction').value = 'create_goal';
    document.getElementById('goalId').value = '';
    document.getElementById('goalForm').reset();
    document.getElementById('stepsList').innerHTML = '';
    stepCounter = 0;
    document.getElementById('goalModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeGoalModal() {
    document.getElementById('goalModal').style.display = 'none';
    document.body.style.overflow = '';
}

function addStep() {
    stepCounter++;
    const stepHtml = `
        <div class="step-item" data-step-id="${stepCounter}">
            <span class="step-handle"><i class="fas fa-grip-vertical"></i></span>
            <div class="step-content">
                <input type="text" name="steps[${stepCounter}][title]" class="step-input" 
                       placeholder="Step title" required>
                <input type="hidden" name="steps[${stepCounter}][order]" value="${stepCounter}">
            </div>
            <button type="button" class="step-remove" onclick="removeStep(${stepCounter})">
                <i class="fas fa-times"></i>
            </button>
        </div>
    `;
    document.getElementById('stepsList').insertAdjacentHTML('beforeend', stepHtml);
}

function removeStep(id) {
    const step = document.querySelector(`[data-step-id="${id}"]`);
    if (step) step.remove();
}

function editGoal(goalId) {
    // Fetch goal data and populate modal
    fetch(`process_goals.php?action=get_goal&goal_id=${goalId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch goal data');
            }
            return response.json();
        })
        .then(data => {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-bullseye"></i> Edit Goal';
            document.getElementById('formAction').value = 'update_goal';
            document.getElementById('goalId').value = goalId;
            document.getElementById('goalTitle').value = data.title || '';
            document.getElementById('goalDescription').value = data.description || '';
            document.getElementById('goalCategory').value = data.category || '';
            document.getElementById('goalTags').value = data.tags || '';
            document.getElementById('goalTargetDate').value = data.target_date || '';
            
            // Load steps
            document.getElementById('stepsList').innerHTML = '';
            stepCounter = 0;
            if (data.steps && data.steps.length > 0) {
                data.steps.forEach(step => {
                    stepCounter++;
                    const escapedTitle = escapeHtml(step.title || '');
                    const stepHtml = `
                        <div class="step-item" data-step-id="${stepCounter}">
                            <span class="step-handle"><i class="fas fa-grip-vertical"></i></span>
                            <div class="step-content">
                                <input type="text" name="steps[${stepCounter}][title]" class="step-input" 
                                       value="${escapedTitle}" required>
                                <input type="hidden" name="steps[${stepCounter}][id]" value="${step.id}">
                                <input type="hidden" name="steps[${stepCounter}][order]" value="${stepCounter}">
                            </div>
                            <button type="button" class="step-remove" onclick="removeStep(${stepCounter})">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    document.getElementById('stepsList').insertAdjacentHTML('beforeend', stepHtml);
                });
            }
            
            document.getElementById('goalModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        })
        .catch(err => {
            console.error('Error fetching goal:', err);
            alert('Failed to load goal data. Please try again.');
        });
}

function viewGoalDetail(goalId) {
    document.getElementById('goalDetailContent').innerHTML = '<p style="text-align:center;padding:20px;">Loading...</p>';
    document.getElementById('goalDetailModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    fetch(`process_goals.php?action=get_goal_detail&goal_id=${goalId}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to fetch goal details');
            }
            return response.json();
        })
        .then(data => {
            renderGoalDetail(data);
        })
        .catch(err => {
            console.error('Error fetching goal details:', err);
            document.getElementById('goalDetailContent').innerHTML = '<p style="text-align:center;padding:20px;color:#ef4444;">Failed to load goal details.</p>';
        });
}

function renderGoalDetail(data) {
    let stepsHtml = '';
    if (data.steps && data.steps.length > 0) {
        stepsHtml = data.steps.map(step => `
            <div class="step-detail-item ${step.is_completed ? 'completed' : ''}">
                ${canManageGoals ? `
                    <input type="checkbox" class="step-checkbox" 
                           ${step.is_completed ? 'checked' : ''} 
                           onchange="toggleStep(${step.id}, ${data.id}, this.checked)">
                ` : `
                    <i class="fas ${step.is_completed ? 'fa-check-circle' : 'fa-circle'}" 
                       style="color: ${step.is_completed ? '#10b981' : '#64748b'}; margin-top: 2px;"></i>
                `}
                <div class="step-detail-content">
                    <div class="step-detail-title">${escapeHtml(step.title || 'Step')}</div>
                    ${step.description ? `<div class="step-detail-description">${escapeHtml(step.description)}</div>` : ''}
                    ${step.is_completed && step.completed_at ? `
                        <div class="step-completed-info">
                            <i class="fas fa-check"></i> Completed ${formatDate(step.completed_at)}
                        </div>
                    ` : ''}
                </div>
            </div>
        `).join('');
    } else {
        stepsHtml = '<p class="placeholder-text">No steps defined for this goal.</p>';
    }
    
    let progressHtml = '';
    if (data.progress && data.progress.length > 0) {
        progressHtml = `
            <div class="progress-history">
                <h4 class="progress-history-title">Progress History</h4>
                ${data.progress.map(entry => `
                    <div class="progress-entry">
                        <div class="progress-entry-header">
                            <span class="progress-entry-user">${escapeHtml(entry.user_name || 'User')}</span>
                            <span class="progress-entry-date">${formatDate(entry.created_at)}</span>
                        </div>
                        <div class="progress-entry-note">${escapeHtml(entry.progress_note || '')}</div>
                    </div>
                `).join('')}
            </div>
        `;
    }
    
    const html = `
        <div class="goal-detail-header" style="margin-bottom: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    ${data.category ? `<span class="goal-category">${escapeHtml(data.category)}</span>` : ''}
                    <h3 class="goal-title">${escapeHtml(data.title || 'Goal')}</h3>
                    ${data.description ? `<p class="goal-description">${escapeHtml(data.description)}</p>` : ''}
                </div>
                <span class="status-badge status-${data.status}">${data.status || 'active'}</span>
            </div>
        </div>
        
        <div class="goal-progress">
            <div class="progress-label">
                <span>Overall Progress</span>
                <span><strong>${Math.round(data.completion_percentage || 0)}%</strong></span>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: ${data.completion_percentage || 0}%"></div>
            </div>
        </div>
        
        <div class="steps-progress" style="margin-top: 20px;">
            <h4 class="steps-title"><i class="fas fa-list-check"></i> Steps</h4>
            ${stepsHtml}
        </div>
        
        ${canManageGoals ? `
            <div style="margin-top: 20px;">
                <button class="btn-add-progress" onclick="openProgressNoteModal(${data.id})">
                    <i class="fas fa-plus"></i> Add Progress Note
                </button>
            </div>
        ` : ''}
        
        ${progressHtml}
    `;
    
    document.getElementById('goalDetailContent').innerHTML = html;
}

function closeGoalDetailModal() {
    document.getElementById('goalDetailModal').style.display = 'none';
    document.body.style.overflow = '';
}

function openProgressNoteModal(goalId) {
    document.getElementById('progressGoalId').value = goalId;
    document.getElementById('progressNoteModal').style.display = 'flex';
}

function closeProgressNoteModal() {
    document.getElementById('progressNoteModal').style.display = 'none';
}

function getCsrfToken() {
    // Get CSRF token from an existing form
    const tokenField = document.querySelector('input[name="csrf_token"]');
    return tokenField ? tokenField.value : '';
}

function toggleStep(stepId, goalId, isCompleted) {
    const formData = new FormData();
    formData.append('action', 'complete_step');
    formData.append('step_id', stepId);
    formData.append('goal_id', goalId);
    formData.append('is_completed', isCompleted ? '1' : '0');
    formData.append('csrf_token', getCsrfToken());
    
    fetch('process_goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to update step');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            viewGoalDetail(goalId); // Refresh detail view
            location.reload(); // Refresh main view
        } else {
            alert('Error: ' + (data.message || 'Failed to update step'));
        }
    })
    .catch(err => {
        console.error('Error toggling step:', err);
        alert('Failed to update step. Please try again.');
    });
}

function completeGoal(goalId) {
    if (!confirm('Mark this goal as completed?')) return;
    
    const formData = new FormData();
    formData.append('action', 'complete_goal');
    formData.append('goal_id', goalId);
    formData.append('csrf_token', getCsrfToken());
    
    fetch('process_goals.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Failed to complete goal');
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + (data.message || 'Failed to complete goal'));
        }
    })
    .catch(err => {
        console.error('Error completing goal:', err);
        alert('Failed to complete goal. Please try again.');
    });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatDate(dateString) {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Close modals on outside click
document.querySelectorAll('.modal').forEach(modal => {
    modal.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});

// Close modals on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal').forEach(modal => {
            modal.style.display = 'none';
        });
        document.body.style.overflow = '';
    }
});

// Auto-open goal detail modal if goal_id is in URL
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    const goalId = urlParams.get('goal_id');
    if (goalId) {
        viewGoalDetail(parseInt(goalId, 10));
    }
});
</script>
