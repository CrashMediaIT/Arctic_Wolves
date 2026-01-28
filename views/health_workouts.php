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
$categories_stmt = $pdo->prepare("SELECT DISTINCT category FROM exercise_library WHERE category IS NOT NULL ORDER BY category");
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// No demo exercises - show empty state when no real data exists

// Calculate program progress
$program_progress = 0;
if ($current_program && $current_program['total_workouts'] > 0) {
    $program_progress = ($current_program['completed_workouts'] / $current_program['total_workouts']) * 100;
}
?>

<style>
/* Health Workouts Page Header - Financial Reports Hub Style */
.health-workouts-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.health-workouts-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.health-workouts-page-header .page-header-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), #5a0080);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: #fff;
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.3);
}
.health-workouts-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.health-workouts-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
.health-workouts-page-header .btn-contact {
    background: var(--primary);
    color: #fff;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 700;
    font-size: 14px;
    transition: all 0.3s;
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}
.health-workouts-page-header .btn-contact:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 16px rgba(107, 70, 193, 0.3);
}
</style>

<!-- Health Workouts View -->
<div class="health-workouts-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-dumbbell"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Strength & Conditioning</h1>
            <p class="page-description">Your personalized workout programs</p>
        </div>
    </div>
    <button class="btn-contact" data-action="contact" data-modal="contact-coach-modal">
        <i class="fas fa-envelope"></i> Contact Coach
    </button>
</div>

<!-- Time Filter -->
<div class="time-filter-bar">
    <span class="filter-label">View:</span>
    <div class="time-filter-buttons">
        <button class="time-filter-btn active" data-filter="day" onclick="setTimeFilter('day')">Day</button>
        <button class="time-filter-btn" data-filter="week" onclick="setTimeFilter('week')">Week</button>
        <button class="time-filter-btn" data-filter="month" onclick="setTimeFilter('month')">Month</button>
        <button class="time-filter-btn" data-filter="year" onclick="setTimeFilter('year')">Year</button>
    </div>
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
            <button class="btn-secondary" data-action="contact" data-modal="contact-coach-modal"><i class="fas fa-envelope"></i> Contact Coach</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- Workout Calendar -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-calendar-week"></i> This Week's Schedule</h3>
            <div class="calendar-nav">
                <button class="btn-icon" data-action="prev-week"><i class="fas fa-chevron-left"></i></button>
                <span class="current-week"><?= date('F j, Y', strtotime('monday this week')) ?></span>
                <button class="btn-icon" data-action="next-week"><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        <div class="card-body">
            <?php if (empty($schedule)): ?>
            <div class="empty-state-small">
                <i class="fas fa-calendar-times"></i>
                <p>No workout schedule assigned for this week. Contact your coach for a personalized training plan.</p>
            </div>
            <?php else: ?>
            <div class="workout-schedule">
                <?php
                // Group schedule by day number
                $schedule_by_day = [];
                foreach ($schedule as $item) {
                    $day_num = $item['day_number'];
                    if (!isset($schedule_by_day[$day_num])) {
                        $schedule_by_day[$day_num] = [];
                    }
                    $schedule_by_day[$day_num][] = $item;
                }
                
                $days = ['MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'];
                foreach ($days as $index => $day_name):
                    $day_number = $index + 1;
                    $day_exercises = $schedule_by_day[$day_number] ?? [];
                    $has_workout = !empty($day_exercises);
                    $date = date('j', strtotime("monday this week +{$index} days"));
                    $workout_label = $has_workout ? $day_name . ' Workout' : 'Rest Day';
                ?>
                <div class="schedule-day clickable <?= $has_workout ? '' : 'rest' ?>" 
                     data-day="<?= $day_name ?>" 
                     data-workout="<?= htmlspecialchars($workout_label) ?>"
                     data-exercises='<?= htmlspecialchars(json_encode(array_map(function($e) {
                         return [
                             'name' => $e['exercise_name'] ?? '',
                             'sets' => $e['sets'] ?? 0,
                             'reps' => $e['reps'] ?? 0,
                             'weight' => $e['weight'] ?? 'N/A',
                             'description' => $e['description'] ?? ''
                         ];
                     }, $day_exercises), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>'
                     onclick="showDayDetails(this)">
                    <div class="day-header">
                        <span class="day-name"><?= $day_name ?></span>
                        <span class="day-date"><?= $date ?></span>
                    </div>
                    <div class="day-workout">
                        <i class="fas fa-<?= $has_workout ? 'dumbbell' : 'bed' ?>"></i>
                        <span><?= $has_workout ? count($day_exercises) . ' exercise(s)' : 'Rest Day' ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Day Details Modal -->
    <div class="day-details-panel" id="dayDetailsPanel" style="display: none;">
        <div class="day-details-header">
            <h4 id="dayDetailTitle">Day Details</h4>
            <button class="btn-icon" onclick="closeDayDetails()"><i class="fas fa-times"></i></button>
        </div>
        <div class="day-details-body">
            <h5>Exercises for Today:</h5>
            <div id="dayDetailExercises" class="exercise-detail-list"></div>
        </div>
    </div>

    <!-- Exercise Library -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-book"></i> Exercise Library</h3>
        </div>
        <!-- Filters Box -->
        <div class="exercise-filters-box">
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
                <button type="submit" class="btn btn-primary btn-with-icon"><i class="fas fa-search"></i> Search</button>
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
                        <button class="btn-secondary btn-small btn-with-icon" data-action="view-demo" data-exercise-id="<?= $exercise['id'] ?>"><i class="fas fa-play"></i> View Demo</button>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="placeholder-text">No exercises found.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Workout History Section -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-history"></i> Workout History</h3>
        </div>
        <div class="card-body">
            <div class="workout-history-list">
                <?php
                // Fetch real workout history from database
                $workout_history = [];
                try {
                    $history_query = "
                        SELECT 
                            DATE(awf.feedback_date) as date,
                            wp.name,
                            CONCAT(FLOOR(awf.duration_minutes / 60), ' min') as duration,
                            (SELECT COUNT(*) FROM athlete_workout_feedback awf2 
                             WHERE awf2.assignment_id = awf.assignment_id 
                             AND DATE(awf2.feedback_date) = DATE(awf.feedback_date) 
                             AND awf2.status = 'completed') as exercises_completed,
                            (SELECT COUNT(DISTINCT wpe.id) FROM workout_plan_exercises wpe 
                             WHERE wpe.workout_plan_id = wp.id) as total_exercises
                        FROM athlete_workout_feedback awf
                        INNER JOIN athlete_workout_assignments awa ON awf.assignment_id = awa.id
                        INNER JOIN workout_plans wp ON awa.workout_plan_id = wp.id
                        WHERE awa.athlete_id = ?
                        GROUP BY DATE(awf.feedback_date), wp.id
                        ORDER BY awf.feedback_date DESC
                        LIMIT 10
                    ";
                    $history_stmt = $pdo->prepare($history_query);
                    $history_stmt->execute([$user_id]);
                    $workout_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    error_log("Error fetching workout history: " . $e->getMessage());
                    $workout_history = [];
                }
                
                if (count($workout_history) > 0):
                    foreach ($workout_history as $history):
                        $completion_percent = $history['total_exercises'] > 0 ? ($history['exercises_completed'] / $history['total_exercises']) * 100 : 0;
                ?>
                <div class="history-item" 
                     data-date="<?= htmlspecialchars($history['date'], ENT_QUOTES, 'UTF-8') ?>"
                     data-name="<?= htmlspecialchars($history['name'], ENT_QUOTES, 'UTF-8') ?>"
                     onclick="viewHistoryDetails(this.dataset.date, this.dataset.name)">
                    <div class="history-date">
                        <span class="date-day"><?= date('d', strtotime($history['date'])) ?></span>
                        <span class="date-month"><?= date('M', strtotime($history['date'])) ?></span>
                    </div>
                    <div class="history-details">
                        <h4><?= htmlspecialchars($history['name']) ?></h4>
                        <div class="history-meta">
                            <span><i class="fas fa-clock"></i> <?= $history['duration'] ?></span>
                            <span><i class="fas fa-check-circle"></i> <?= $history['exercises_completed'] ?>/<?= $history['total_exercises'] ?> exercises</span>
                        </div>
                    </div>
                    <div class="history-status">
                        <?php if ($completion_percent === 100.0): ?>
                            <span class="status-badge completed"><i class="fas fa-check"></i> Completed</span>
                        <?php else: ?>
                            <span class="status-badge partial"><i class="fas fa-exclamation"></i> <?= number_format($completion_percent) ?>%</span>
                        <?php endif; ?>
                    </div>
                    <button class="btn-icon"><i class="fas fa-chevron-right"></i></button>
                </div>
                <?php 
                    endforeach;
                else:
                ?>
                <div class="placeholder-container">
                    <i class="fas fa-history placeholder-icon"></i>
                    <p class="placeholder-text">No workout history yet. Complete your first workout to see it here!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Contact Coach Modal -->
<div class="modal" id="contact-coach-modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Contact Your Coach</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('contact-coach-modal')">&times;</button>
        </div>
        <form method="POST" action="process_contact.php" id="contact-coach-form">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="send_message">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Subject *</label>
                    <input type="text" name="subject" class="form-input" placeholder="Enter message subject" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Message *</label>
                    <textarea name="message" class="form-textarea" rows="6" placeholder="Type your message here..." required></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-input">
                        <option value="normal">Normal</option>
                        <option value="high">High</option>
                        <option value="urgent">Urgent</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('contact-coach-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-paper-plane"></i> Send Message</button>
            </div>
        </form>
    </div>
</div>

<style>
/* Time Filter Bar */
.time-filter-bar {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 24px;
    padding: 16px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
}

.filter-label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
}

.time-filter-buttons {
    display: flex;
    gap: 8px;
}

.time-filter-btn {
    padding: 8px 16px;
    border: 1px solid var(--border);
    background: var(--bg-main);
    color: var(--text-dim);
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.time-filter-btn:hover {
    border-color: var(--primary);
    color: var(--primary);
}

.time-filter-btn.active {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

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
    color: var(--primary);
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
    color: var(--primary);
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
    border: 1px solid var(--primary);
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
    color: var(--primary);
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
    color: var(--primary);
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
    background: linear-gradient(90deg, var(--primary), var(--accent));
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
    border-color: var(--primary);
}

.schedule-day.completed {
    border-color: #10b981;
    background: rgba(16, 185, 129, 0.1);
}

.schedule-day.active {
    border-color: var(--primary);
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
    color: var(--primary);
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
    border-color: var(--primary);
    transform: translateY(-3px);
}

.exercise-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, var(--primary), var(--accent));
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

.calendar-nav .btn-icon {
    width: 36px;
    height: 36px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-white);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.calendar-nav .btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

.calendar-nav .btn-icon i {
    font-size: 14px;
}

/* Exercise Library Filters Box */
.exercise-filters-box {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 16px 20px;
    margin: 0 20px 20px 20px;
}

.exercise-filters-box .filter-group {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

/* Button with icon styling */
.btn-with-icon {
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-with-icon i {
    color: inherit;
}

.btn-primary.btn-with-icon i {
    color: #fff;
}

.btn-secondary.btn-with-icon i {
    color: var(--text-white);
}

/* Clickable schedule days */
.schedule-day.clickable {
    cursor: pointer;
}

.schedule-day.clickable:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

/* Day Details Panel */
.day-details-panel {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 20px;
    margin-top: 20px;
}

.day-details-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border);
}

.day-details-header h4 {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
}

.day-details-body h5 {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-dim);
    margin-bottom: 12px;
}

.exercise-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.exercise-list li {
    padding: 10px 12px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 14px;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 10px;
}

.exercise-list li::before {
    content: '•';
    color: var(--primary);
    font-size: 18px;
}

/* Exercise Detail List */
.exercise-detail-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.exercise-detail-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    overflow: hidden;
    cursor: pointer;
    transition: all 0.3s;
}

.exercise-detail-card:hover {
    border-color: var(--primary);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.exercise-detail-card.expanded {
    border-color: var(--primary);
}

.exercise-detail-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px;
    background: var(--bg-card);
}

.exercise-detail-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    display: flex;
    align-items: center;
    gap: 10px;
}

.exercise-detail-name i {
    color: var(--primary);
}

.exercise-detail-stats {
    display: flex;
    gap: 16px;
}

.exercise-stat {
    text-align: center;
    padding: 4px 12px;
    background: rgba(107, 70, 193, 0.1);
    border-radius: 6px;
}

.exercise-stat-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
}

.exercise-stat-label {
    font-size: 10px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.exercise-detail-body {
    display: none;
    padding: 16px;
    border-top: 1px solid var(--border);
}

.exercise-detail-card.expanded .exercise-detail-body {
    display: block;
}

.exercise-instructions {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
    margin-bottom: 16px;
}

.exercise-instructions strong {
    color: var(--text-white);
}

.exercise-toggle {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: var(--primary);
}

.rest-day-message {
    text-align: center;
    padding: 30px;
    color: var(--text-dim);
}

.rest-day-message i {
    font-size: 48px;
    color: var(--primary);
    opacity: 0.3;
    display: block;
    margin-bottom: 12px;
}

/* Workout History Styles */
.workout-history-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.history-item {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 16px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.history-item:hover {
    border-color: var(--primary);
    transform: translateX(4px);
}

.history-date {
    min-width: 50px;
    text-align: center;
    padding: 8px;
    background: var(--bg-card);
    border-radius: 8px;
}

.history-date .date-day {
    display: block;
    font-size: 24px;
    font-weight: 900;
    color: var(--text-white);
    line-height: 1;
}

.history-date .date-month {
    display: block;
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.history-details {
    flex: 1;
}

.history-details h4 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.history-meta {
    display: flex;
    gap: 16px;
    font-size: 13px;
    color: var(--text-dim);
}

.history-meta i {
    color: var(--primary);
    margin-right: 5px;
}

.history-status .status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 15px;
    font-size: 12px;
    font-weight: 700;
}

.status-badge.completed {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.status-badge.partial {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.history-item .btn-icon {
    width: 32px;
    height: 32px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-dim);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.history-item:hover .btn-icon {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* Exercise Completion Checkbox */
.exercise-completion {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px;
    background: var(--bg-card);
    border-radius: 6px;
    margin-top: 12px;
}

.exercise-checkbox-wrapper {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.exercise-checkbox-wrapper input[type="checkbox"] {
    width: 22px;
    height: 22px;
}

.exercise-checkbox-wrapper label {
    font-size: 14px;
    font-weight: 600;
    color: var(--text-white);
    cursor: pointer;
}

.exercise-checkbox-wrapper.completed label {
    color: #10b981;
    text-decoration: line-through;
}
</style>

<script>
function showDayDetails(element) {
    const day = element.dataset.day;
    const workout = element.dataset.workout;
    const exercises = JSON.parse(element.dataset.exercises || '[]');
    
    const panel = document.getElementById('dayDetailsPanel');
    const title = document.getElementById('dayDetailTitle');
    const exerciseContainer = document.getElementById('dayDetailExercises');
    
    title.textContent = day + ' - ' + workout;
    
    if (exercises.length > 0) {
        exerciseContainer.innerHTML = exercises.map((ex, index) => {
            // Handle both old format (string) and new format (object)
            if (typeof ex === 'string') {
                return `<div class="exercise-detail-card">
                    <div class="exercise-detail-header">
                        <span class="exercise-detail-name"><i class="fas fa-dumbbell"></i> ${ex}</span>
                    </div>
                </div>`;
            }
            
            return `<div class="exercise-detail-card" onclick="toggleExerciseDetails(this)">
                <div class="exercise-detail-header">
                    <span class="exercise-detail-name">
                        <i class="fas fa-dumbbell"></i> ${ex.name}
                    </span>
                    <div class="exercise-detail-stats">
                        <div class="exercise-stat">
                            <div class="exercise-stat-value">${ex.sets}</div>
                            <div class="exercise-stat-label">Sets</div>
                        </div>
                        <div class="exercise-stat">
                            <div class="exercise-stat-value">${ex.reps}</div>
                            <div class="exercise-stat-label">Reps</div>
                        </div>
                        <div class="exercise-stat">
                            <div class="exercise-stat-value">${ex.weight}</div>
                            <div class="exercise-stat-label">Weight</div>
                        </div>
                    </div>
                    <span class="exercise-toggle">
                        <i class="fas fa-chevron-down"></i>
                    </span>
                </div>
                <div class="exercise-detail-body">
                    <div class="exercise-instructions">
                        <strong>How to perform:</strong><br>
                        ${ex.description}
                    </div>
                    <div class="exercise-completion">
                        <div class="exercise-checkbox-wrapper" onclick="event.stopPropagation(); toggleExerciseCompletion(this, '${ex.name}')">
                            <input type="checkbox" id="ex-complete-${index}">
                            <label for="ex-complete-${index}">Mark as completed</label>
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');
    } else {
        exerciseContainer.innerHTML = `<div class="rest-day-message">
            <i class="fas fa-bed"></i>
            <p>Rest day - no exercises scheduled</p>
            <p style="font-size: 13px;">Take time to recover and let your muscles rebuild.</p>
        </div>`;
    }
    
    panel.style.display = 'block';
    panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function toggleExerciseDetails(card) {
    card.classList.toggle('expanded');
    const icon = card.querySelector('.exercise-toggle i');
    if (icon) {
        icon.classList.toggle('fa-chevron-down');
        icon.classList.toggle('fa-chevron-up');
    }
}

function closeDayDetails() {
    document.getElementById('dayDetailsPanel').style.display = 'none';
}

// Time filter function
function setTimeFilter(filter) {
    // Update button states
    document.querySelectorAll('.time-filter-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.dataset.filter === filter) {
            btn.classList.add('active');
        }
    });
    
    // Show notification about filter change
    showWorkoutNotification('Showing ' + filter + ' view of workout plans', 'info');
}

// Toggle exercise completion
function toggleExerciseCompletion(wrapper, exerciseName) {
    const checkbox = wrapper.querySelector('input[type="checkbox"]');
    checkbox.checked = !checkbox.checked;
    
    if (checkbox.checked) {
        wrapper.classList.add('completed');
        showWorkoutNotification(exerciseName + ' marked as completed!', 'success');
    } else {
        wrapper.classList.remove('completed');
        showWorkoutNotification(exerciseName + ' unmarked', 'info');
    }
}

// View workout history details
function viewHistoryDetails(date, workoutName) {
    showWorkoutNotification('Loading workout details for ' + workoutName + ' on ' + date, 'info');
}

// Notification helper
function showWorkoutNotification(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = 'workout-notification';
    
    let icon = 'info-circle';
    let bgColor = 'rgba(107, 70, 193, 0.9)';
    
    if (type === 'error') {
        icon = 'exclamation-circle';
        bgColor = 'rgba(239, 68, 68, 0.9)';
    } else if (type === 'success') {
        icon = 'check-circle';
        bgColor = 'rgba(16, 185, 129, 0.9)';
    }
    
    // Create icon element separately to avoid XSS
    const iconEl = document.createElement('i');
    iconEl.className = 'fas fa-' + icon;
    
    const textEl = document.createElement('span');
    textEl.textContent = message;
    
    alertDiv.appendChild(iconEl);
    alertDiv.appendChild(textEl);
    alertDiv.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        z-index: 10000;
        min-width: 280px;
        padding: 15px 20px;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        background: ${bgColor};
        color: #fff;
        display: flex;
        align-items: center;
        gap: 10px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    `;
    
    document.body.appendChild(alertDiv);
    setTimeout(() => alertDiv.remove(), 3000);
}
</script>
