<?php
// Get current workout program for this athlete
$program_query = "
    SELECT wp.*, 
           awa.status as assignment_status,
           awa.start_date,
           (SELECT COUNT(*) FROM athlete_workout_feedback WHERE assignment_id = awa.id) as completed_workouts,
           (SELECT COUNT(*) FROM athlete_workout_feedback WHERE assignment_id = awa.id AND DATE(feedback_date) >= DATE_SUB(NOW(), INTERVAL 1 WEEK)) as week_workouts
    FROM athlete_workout_assignments awa
    INNER JOIN workout_plans wp ON awa.workout_plan_id = wp.id
    WHERE awa.athlete_id = ? AND awa.status = 'active'
    ORDER BY awa.assigned_date DESC
    LIMIT 1
";
$program_stmt = $pdo->prepare($program_query);
$program_stmt->execute([$user_id]);
$current_program = $program_stmt->fetch();

// Get this week's exercises from the plan
$schedule = [];
if ($current_program) {
    $schedule_query = "
        SELECT wpe.*, el.name as exercise_name, el.description
        FROM workout_plan_exercises wpe
        INNER JOIN exercise_library el ON wpe.exercise_id = el.id
        WHERE wpe.workout_plan_id = ?
        ORDER BY wpe.day_number, wpe.exercise_order
    ";
    $schedule_stmt = $pdo->prepare($schedule_query);
    $schedule_stmt->execute([$current_program['id']]);
    $schedule = $schedule_stmt->fetchAll();
}

// Get exercises with filter
$filter_category = $_GET['category'] ?? 'all';
$search_exercise = $_GET['search_exercise'] ?? '';

$exercises_query = "
    SELECT e.* 
    FROM exercise_library e
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
$categories_stmt = $pdo->prepare("SELECT DISTINCT category FROM exercises WHERE category IS NOT NULL ORDER BY category");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

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
    <!-- Current Program Section (Always show header) -->
    <div class="content-section">
        <div class="section-header-main">
            <h2><i class="fas fa-fire"></i> Active Program</h2>
        </div>
        
        <?php if ($current_program): ?>
        <div class="current-program-card" data-component="ProgramCard">
            <div class="program-header">
                <div>
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
        <div class="empty-state-card">
            <i class="fas fa-dumbbell empty-state-icon"></i>
            <h3 class="empty-state-title">No Workout Plan Currently Assigned</h3>
            <p class="empty-state-text">Contact your coach to get a personalized workout program tailored to your hockey training needs.</p>
            <button class="btn-secondary" data-action="contact" data-page="coach"><i class="fas fa-envelope"></i> Contact Coach</button>
        </div>
        <?php endif; ?>
    </div>

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
                <select name="category" class="form-input-small" onchange="this.form.submit()">
                    <option value="all">All Categories</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>" <?= $filter_category === $cat ? 'selected' : '' ?>>
                            <?= htmlspecialchars($cat) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="search_exercise" class="form-input-small" placeholder="Search exercises..." value="<?= htmlspecialchars($search_exercise) ?>">
                <button type="submit" class="btn btn-secondary"><i class="fas fa-search"></i></button>
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
.content-section {
    margin-bottom: 24px;
}

.section-header-main {
    margin-bottom: 20px;
}

.section-header-main h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 10px;
}

.section-header-main h2 i {
    color: var(--neon);
}

.empty-state-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 60px 40px;
    text-align: center;
}

.empty-state-icon {
    font-size: 64px;
    color: var(--neon);
    opacity: 0.3;
    display: block;
    margin-bottom: 20px;
}

.empty-state-title {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 12px;
}

.empty-state-text {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 24px;
    max-width: 500px;
    margin-left: auto;
    margin-right: auto;
}

.current-program-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
}

.program-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
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
    padding: 16px;
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
    margin-bottom: 12px;
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
    margin-bottom: 12px;
}

.btn-small {
    height: 35px;
    padding: 0 15px;
    font-size: 12px;
}
</style>
