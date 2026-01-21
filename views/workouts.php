<?php
/**
 * Workout Builder
 * Create and manage workout plans for athletes
 */

require_once __DIR__ . '/../security.php';

$is_coach = ($user_role === 'coach' || $user_role === 'coach_plus' || $user_role === 'admin');
$viewing_user_id = $user_id;

// Allow coaches to view athlete workouts
if ($is_coach && isset($_GET['athlete_id'])) {
    $viewing_user_id = intval($_GET['athlete_id']);
}

// Get workouts
$workouts_stmt = $pdo->prepare("
    SELECT uw.*, u.first_name, u.last_name, coach.first_name as coach_first, coach.last_name as coach_last,
           (SELECT COUNT(*) FROM user_workout_items WHERE user_workout_id = uw.id) as exercise_count,
           (SELECT COUNT(*) FROM user_workout_items WHERE user_workout_id = uw.id AND is_completed = 1) as completed_count
    FROM user_workouts uw
    INNER JOIN users u ON uw.user_id = u.id
    LEFT JOIN users coach ON uw.coach_id = coach.id
    WHERE uw.user_id = ?
    ORDER BY uw.assigned_date DESC
");
$workouts_stmt->execute([$viewing_user_id]);
$workouts = $workouts_stmt->fetchAll();

// Get simple workouts (legacy)
$simple_workouts_stmt = $pdo->prepare("
    SELECT w.*, u.first_name, u.last_name
    FROM workouts w
    INNER JOIN users u ON w.user_id = u.id
    WHERE w.user_id = ?
    ORDER BY w.created_at DESC
");
$simple_workouts_stmt->execute([$viewing_user_id]);
$simple_workouts = $simple_workouts_stmt->fetchAll();
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn-create {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        transition: all 0.2s;
    }
    .btn-create:hover {
        background: #e64500;
    }
    .workout-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        margin-bottom: 20px;
        transition: all 0.2s;
    }
    .workout-card:hover {
        border-color: var(--primary);
    }
    .workout-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 15px;
    }
    .workout-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 10px;
    }
    .workout-meta {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 5px;
    }
    .workout-description {
        color: #94a3b8;
        font-size: 14px;
        margin: 15px 0;
        line-height: 1.6;
    }
    .progress-bar {
        width: 100%;
        height: 8px;
        background: #1e293b;
        border-radius: 4px;
        overflow: hidden;
        margin: 15px 0;
    }
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, var(--primary) 0%, #e64500 100%);
        transition: width 0.3s;
    }
    .progress-text {
        font-size: 13px;
        color: #94a3b8;
        margin-bottom: 10px;
    }
    .exercise-list {
        margin: 20px 0;
    }
    .exercise-item {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        margin-bottom: 10px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .exercise-item.completed {
        border-color: #10b981;
        background: rgba(16, 185, 129, 0.05);
    }
    .exercise-name {
        font-size: 15px;
        font-weight: 600;
        color: #fff;
    }
    .exercise-details {
        font-size: 13px;
        color: #64748b;
        margin-top: 5px;
    }
    .exercise-checkbox {
        width: 24px;
        height: 24px;
        cursor: pointer;
    }
    .btn-toggle {
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
        padding: 8px 16px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-toggle:hover {
        background: var(--primary);
        color: #fff;
    }
    .workout-link {
        color: var(--primary);
        text-decoration: none;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .workout-link:hover {
        text-decoration: underline;
    }
    .completed-badge {
        background: #10b981;
        color: #fff;
        padding: 4px 12px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: 700;
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
    }
    .empty-state i {
        font-size: 64px;
        color: #64748b;
        opacity: 0.3;
        margin-bottom: 20px;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-dumbbell"></i> My Workouts
    </h1>
    <?php if ($is_coach): ?>
        <a href="?page=library_workouts" class="btn-create">
            <i class="fas fa-book"></i> Workout Library
        </a>
    <?php endif; ?>
</div>

<?php if (empty($workouts) && empty($simple_workouts)): ?>
    <div class="empty-state">
        <i class="fas fa-dumbbell"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Workouts Assigned</h2>
        <p style="color: #64748b;">Your coach will assign workouts here</p>
    </div>
<?php else: ?>
    
    <!-- Detailed Workouts -->
    <?php foreach ($workouts as $workout): ?>
        <div class="workout-card">
            <div class="workout-header">
                <div>
                    <h3 class="workout-title"><?= htmlspecialchars($workout['title']) ?></h3>
                    <div class="workout-meta">
                        <i class="fas fa-calendar"></i>
                        Assigned: <?= date('M d, Y', strtotime($workout['assigned_date'])) ?>
                    </div>
                    <?php if ($workout['coach_first']): ?>
                        <div class="workout-meta">
                            <i class="fas fa-user-tie"></i>
                            Coach: <?= htmlspecialchars($workout['coach_first'] . ' ' . $workout['coach_last']) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if ($workout['completed_count'] === $workout['exercise_count'] && $workout['exercise_count'] > 0): ?>
                    <span class="completed-badge">
                        <i class="fas fa-check-circle"></i> COMPLETED
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($workout['description']): ?>
                <div class="workout-description">
                    <?= nl2br(htmlspecialchars($workout['description'])) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($workout['exercise_count'] > 0): ?>
                <div class="progress-text">
                    Progress: <?= $workout['completed_count'] ?> / <?= $workout['exercise_count'] ?> exercises completed
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?= ($workout['completed_count'] / $workout['exercise_count']) * 100 ?>%;"></div>
                </div>
                
                <div class="exercise-list" id="exercises_<?= $workout['id'] ?>" style="display: none;">
                    <?php
                    $exercises_stmt = $pdo->prepare("
                        SELECT uwi.*, e.name, e.target_area, e.description
                        FROM user_workout_items uwi
                        INNER JOIN exercises e ON uwi.exercise_id = e.id
                        WHERE uwi.user_workout_id = ?
                        ORDER BY uwi.id
                    ");
                    $exercises_stmt->execute([$workout['id']]);
                    $exercises = $exercises_stmt->fetchAll();
                    
                    foreach ($exercises as $exercise):
                    ?>
                        <div class="exercise-item <?= $exercise['is_completed'] ? 'completed' : '' ?>">
                            <div>
                                <div class="exercise-name"><?= htmlspecialchars($exercise['name']) ?></div>
                                <div class="exercise-details">
                                    <?= $exercise['sets'] ?> sets × <?= $exercise['reps'] ?> reps
                                    <?php if ($exercise['weight']): ?>
                                        @ <?= $exercise['weight'] ?>lbs
                                    <?php endif; ?>
                                    <?php if ($exercise['target_area']): ?>
                                        • <?= htmlspecialchars($exercise['target_area']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <input type="checkbox" class="exercise-checkbox" 
                                   <?= $exercise['is_completed'] ? 'checked' : '' ?>
                                   onchange="toggleExercise(<?= $exercise['id'] ?>, this.checked)">
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button class="btn-toggle" onclick="toggleExercises(<?= $workout['id'] ?>)">
                    <i class="fas fa-chevron-down"></i> Show Exercises
                </button>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
    <!-- Simple Workouts (Legacy) -->
    <?php foreach ($simple_workouts as $workout): ?>
        <div class="workout-card">
            <div class="workout-header">
                <div>
                    <h3 class="workout-title"><?= htmlspecialchars($workout['title']) ?></h3>
                    <div class="workout-meta">
                        <i class="fas fa-calendar"></i>
                        <?= date('M d, Y', strtotime($workout['created_at'])) ?>
                    </div>
                </div>
                <?php if ($workout['is_completed']): ?>
                    <span class="completed-badge">
                        <i class="fas fa-check-circle"></i> COMPLETED
                    </span>
                <?php endif; ?>
            </div>
            
            <?php if ($workout['description']): ?>
                <div class="workout-description">
                    <?= nl2br(htmlspecialchars($workout['description'])) ?>
                </div>
            <?php endif; ?>
            
            <?php if ($workout['link']): ?>
                <a href="<?= htmlspecialchars($workout['link']) ?>" target="_blank" class="workout-link">
                    <i class="fas fa-external-link-alt"></i> View Workout Link
                </a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    
<?php endif; ?>

<script>
function toggleExercises(workoutId) {
    const list = document.getElementById('exercises_' + workoutId);
    const btn = event.target;
    
    if (list.style.display === 'none') {
        list.style.display = 'block';
        btn.innerHTML = '<i class="fas fa-chevron-up"></i> Hide Exercises';
    } else {
        list.style.display = 'none';
        btn.innerHTML = '<i class="fas fa-chevron-down"></i> Show Exercises';
    }
}

function toggleExercise(itemId, isCompleted) {
    fetch('process_toggle_workout.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'item_id=' + itemId + '&completed=' + (isCompleted ? '1' : '0') + '&<?= csrfTokenInput(true) ?>'
    }).then(() => {
        location.reload();
    });
}
</script>
