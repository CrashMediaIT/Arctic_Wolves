<?php
// Get current workout program
$program_query = "
    SELECT wp.*, 
           (SELECT COUNT(*) FROM workout_logs WHERE program_id = wp.id AND athlete_id = ?) as completed_workouts,
           (SELECT COUNT(*) FROM workout_logs WHERE program_id = wp.id AND athlete_id = ? AND DATE(completed_at) >= DATE_SUB(NOW(), INTERVAL 1 WEEK)) as week_workouts
    FROM workout_programs wp
    WHERE wp.athlete_id = ? AND wp.status = 'active'
    ORDER BY wp.created_at DESC
    LIMIT 1
";
$program_stmt = $pdo->prepare($program_query);
$program_stmt->execute([$user_id, $user_id, $user_id]);
$current_program = $program_stmt->fetch();

// Get this week's schedule
$schedule_query = "
    SELECT ws.*
    FROM workout_schedule ws
    WHERE ws.program_id = ? 
    AND ws.week_day >= DAYOFWEEK(CURDATE()) - 1
    AND ws.week_day < DAYOFWEEK(CURDATE()) + 6
    ORDER BY ws.week_day
";
$schedule = [];
if ($current_program) {
    $schedule_stmt = $pdo->prepare($schedule_query);
    $schedule_stmt->execute([$current_program['id']]);
    $schedule = $schedule_stmt->fetchAll();
}

// Get exercises with filter
$filter_category = $_GET['category'] ?? 'all';
$search_exercise = $_GET['search_exercise'] ?? '';

$exercises_query = "
    SELECT e.* 
    FROM exercises e
    WHERE 1=1
";
$params = [];

if ($filter_category !== 'all') {
    $exercises_query .= " AND e.category = ?";
    $params[] = $filter_category;
}

if (!empty($search_exercise)) {
    $exercises_query .= " AND (e.name LIKE ? OR e.description LIKE ?)";
    $params[] = "%$search_exercise%";
    $params[] = "%$search_exercise%";
}

$exercises_query .= " ORDER BY e.name LIMIT 20";

$exercises_stmt = $pdo->prepare($exercises_query);
$exercises_stmt->execute($params);
$exercises = $exercises_stmt->fetchAll();

// Get exercise categories
$categories = $pdo->query("SELECT DISTINCT category FROM exercises WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// Calculate program progress
$program_progress = 0;
if ($current_program && $current_program['total_workouts'] > 0) {
    $program_progress = ($current_program['completed_workouts'] / $current_program['total_workouts']) * 100;
}
?>

<!-- Health Workouts View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-dumbbell"></i> Strength & Conditioning
    </h1>
    <p class="page-description">Your personalized workout programs</p>
</div>

<div class="workouts-content">
    <?php if ($current_program): ?>
    <!-- Current Program Card -->
    <div class="current-program-card" data-component="ProgramCard">
        <div class="program-header">
            <div>
                <h3><i class="fas fa-fire"></i> Active Program</h3>
                <p class="program-name"><?= htmlspecialchars($current_program['name']) ?></p>
            </div>
            <button class="btn-primary" data-action="start-workout" data-program-id="<?= $current_program['id'] ?>"><i class="fas fa-play"></i> Start Workout</button>
        </div>
        <div class="program-progress">
            <div class="progress-stats">
                <div class="stat">
                    <span class="stat-value"><?= $current_program['completed_workouts'] ?></span>
                    <span class="stat-label">Workouts Completed</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?= $current_program['week_workouts'] ?></span>
                    <span class="stat-label">This Week</span>
                </div>
                <div class="stat">
                    <span class="stat-value"><?= number_format($program_progress, 0) ?>%</span>
                    <span class="stat-label">Program Progress</span>
                </div>
            </div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: <?= $program_progress ?>%;"></div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="placeholder-container">
        <i class="fas fa-dumbbell placeholder-icon"></i>
        <p class="placeholder-text">No active workout program. Contact your coach to get started.</p>
    </div>
    <?php endif; ?>

    <!-- Workout Calendar -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> This Week's Schedule</h3>
            <div class="calendar-nav">
                <button class="btn-icon"><i class="fas fa-chevron-left"></i></button>
                <span class="current-week">Week of Jan 15, 2024</span>
                <button class="btn-icon"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="card-body">
            <div class="workout-schedule">
                <div class="schedule-day completed">
                    <div class="day-header">
                        <span class="day-name">MON</span>
                        <span class="day-date">15</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-check-circle"></i>
                        <span>Upper Body Strength</span>
                    </div>
                </div>
                <div class="schedule-day completed">
                    <div class="day-header">
                        <span class="day-name">TUE</span>
                        <span class="day-date">16</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-check-circle"></i>
                        <span>Cardio & Core</span>
                    </div>
                </div>
                <div class="schedule-day active">
                    <div class="day-header">
                        <span class="day-name">WED</span>
                        <span class="day-date">17</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-play-circle"></i>
                        <span>Lower Body Power</span>
                    </div>
                </div>
                <div class="schedule-day">
                    <div class="day-header">
                        <span class="day-name">THU</span>
                        <span class="day-date">18</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-circle"></i>
                        <span>Active Recovery</span>
                    </div>
                </div>
                <div class="schedule-day">
                    <div class="day-header">
                        <span class="day-name">FRI</span>
                        <span class="day-date">19</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-circle"></i>
                        <span>Full Body Circuit</span>
                    </div>
                </div>
                <div class="schedule-day rest">
                    <div class="day-header">
                        <span class="day-name">SAT</span>
                        <span class="day-date">20</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-bed"></i>
                        <span>Rest Day</span>
                    </div>
                </div>
                <div class="schedule-day rest">
                    <div class="day-header">
                        <span class="day-name">SUN</span>
                        <span class="day-date">21</span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-bed"></i>
                        <span>Rest Day</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exercise Library -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Exercise Library</h3>
            <form method="GET" action="" class="filter-group">
                <input type="hidden" name="page" value="strength_conditioning">
                <select name="category" class="form-input-small" data-action="auto-submit">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search_exercise" class="form-input-small" placeholder="Search exercises..." value="<?= htmlspecialchars($search_exercise) ?>" data-action="search-debounce">
            </form>
        </div>
        <div class="card-body">
            <div class="exercise-grid">
                <?php if (count($exercises) > 0): ?>
                    <?php foreach ($exercises as $exercise): ?>
                    <div class="exercise-card" data-component="ExerciseCard" data-exercise-id="<?= $exercise['id'] ?>">
                        <div class="exercise-icon">
                            <i class="fas fa-<?= $exercise['category'] === 'Upper Body' ? 'dumbbell' : ($exercise['category'] === 'Lower Body' ? 'running' : 'heartbeat') ?>"></i>
                        </div>
                        <h4><?= htmlspecialchars($exercise['name']) ?></h4>
                        <p class="exercise-category"><?= htmlspecialchars($exercise['category']) ?></p>
                        <button class="btn-secondary btn-small" data-action="view-demo" data-exercise-id="<?= $exercise['id'] ?>"><i class="fas fa-play"></i> View Demo</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text">No exercises found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.current-program-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
}

.program-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
    gap: 15px;
}

.program-header h3 {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 8px;
}

.program-header h3 i {
    color: var(--neon);
    margin-right: 8px;
}

.program-name {
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.progress-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-bottom: 20px;
}

.stat {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 32px;
    font-weight: 900;
    color: var(--neon);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    display: block;
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.progress-bar-container {
    background: var(--bg-main);
    height: 8px;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--neon), var(--accent));
    border-radius: 4px;
    transition: width 0.5s;
}

.calendar-nav {
    display: flex;
    align-items: center;
    gap: 15px;
}

.current-week {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-white);
}

.workout-schedule {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 15px;
}

.schedule-day {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.3s;
}

.schedule-day:hover {
    border-color: var(--neon);
}

.schedule-day.completed {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.schedule-day.active {
    border-color: var(--neon);
    background: rgba(255, 77, 0, 0.1);
}

.schedule-day.rest {
    opacity: 0.5;
}

.day-header {
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border);
}

.day-name {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.day-date {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
}

.day-workout {
    font-size: 12px;
    color: var(--text-dim);
}

.day-workout i {
    display: block;
    font-size: 24px;
    margin-bottom: 8px;
}

.schedule-day.completed .day-workout i {
    color: #10b981;
}

.schedule-day.active .day-workout i {
    color: var(--neon);
}

.exercise-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 20px;
}

.exercise-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    text-align: center;
    transition: all 0.3s;
}

.exercise-card:hover {
    border-color: var(--neon);
    transform: translateY(-3px);
}

.exercise-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 24px;
    color: #fff;
}

.exercise-card h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.exercise-category {
    font-size: 12px;
    color: var(--text-dim);
    margin-bottom: 15px;
}

.btn-small {
    height: 35px;
    padding: 0 15px;
    font-size: 12px;
}
</style>
