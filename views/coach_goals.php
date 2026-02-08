<!-- Coach Goals View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-bullseye"></i> Athlete Goals
    </h1>
    <p class="page-description">Review and manage athlete development goals</p>
</div>

<?php
// Fetch athlete list for coach
try {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.first_name, u.last_name, u.email
        FROM users u
        INNER JOIN managed_athletes ma ON u.id = ma.athlete_id
        WHERE ma.coach_id = ?
        AND u.role IN ('athlete', 'parent')
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$user_id]);
    $athletes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $athletes = decryptUserRows($athletes);
    
    // Get selected athlete
    $selected_athlete_id = $_GET['athlete_id'] ?? ($athletes[0]['id'] ?? null);
    
    // Find the selected athlete info for the typeahead pre-selection
    $selected_athlete = null;
    if ($selected_athlete_id) {
        foreach ($athletes as $a) {
            if ($a['id'] == $selected_athlete_id) {
                $selected_athlete = $a;
                break;
            }
        }
    }
    
    $goals = [];
    if ($selected_athlete_id) {
        $stmt = $pdo->prepare("
            SELECT g.*,
                CASE 
                    WHEN g.target_value > 0 THEN ROUND((g.current_value / g.target_value) * 100, 0)
                    ELSE 0
                END as progress_percentage
            FROM goals g
            WHERE g.athlete_id = ?
            ORDER BY 
                CASE WHEN g.status = 'active' THEN 1 ELSE 2 END,
                g.target_date ASC
        ");
        $stmt->execute([$selected_athlete_id]);
        $goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    error_log("Coach goals fetch error: " . $e->getMessage());
    $athletes = [];
    $goals = [];
}
?>

<div class="coach-goals-content">
    <!-- Athlete Selector -->
    <div class="athlete-selector">
        <label>Select Athlete:</label>
        <div id="goals-athlete-typeahead"></div>
    </div>
    
    <?php if (empty($goals)): ?>
        <!-- Empty State -->
        <div class="empty-state">
            <i class="fas fa-bullseye"></i>
            <h3>No Goals Set</h3>
            <p>This athlete doesn't have any goals yet. Encourage them to create development goals.</p>
        </div>
    <?php else: ?>
        <!-- Goals List -->
        <div class="goals-grid">
            <?php foreach ($goals as $goal): ?>
                <div class="goal-card <?= $goal['status'] ?>">
                    <div class="goal-header">
                        <h3><?= htmlspecialchars($goal['goal_title']) ?></h3>
                        <span class="goal-status status-<?= $goal['status'] ?>">
                            <?= ucfirst($goal['status']) ?>
                        </span>
                    </div>
                    
                    <div class="goal-description">
                        <?= htmlspecialchars($goal['goal_description']) ?>
                    </div>
                    
                    <div class="goal-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= $goal['progress_percentage'] ?>%"></div>
                        </div>
                        <div class="progress-text">
                            <?= $goal['current_value'] ?> / <?= $goal['target_value'] ?>
                            (<?= $goal['progress_percentage'] ?>%)
                        </div>
                    </div>
                    
                    <?php if ($goal['target_date']): ?>
                        <div class="goal-deadline">
                            <i class="far fa-calendar"></i>
                            Target: <?= date('M j, Y', strtotime($goal['target_date'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.coach-goals-content {
    padding: 24px;
}

.athlete-selector {
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.athlete-selector label {
    font-weight: 600;
    color: var(--text-primary);
}

.athlete-selector select {
    min-width: 300px;
}

.goals-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
    gap: 20px;
}
</style>
<script>
new ArcticTypeahead({
    container: '#goals-athlete-typeahead',
    name: 'athlete_id',
    placeholder: 'Search for an athlete…',
    roles: 'athlete',
    multiple: false,
    navigateOnSelect: '?page=coach_goals&athlete_id=',
    preSelected: <?= $selected_athlete ? json_encode([['id' => (int)$selected_athlete['id'], 'name' => $selected_athlete['first_name'] . ' ' . $selected_athlete['last_name'], 'role' => 'Athlete']]) : '[]' ?>
});
</script>
