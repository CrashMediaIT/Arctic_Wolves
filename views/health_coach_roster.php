<?php
/**
 * Health Coach Roster
 * View athletes assigned to health coach and manage their health plans
 */

// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_age = $_GET['age_group'] ?? 'all';

// Determine if we're a health coach or admin
$is_health_coach = ($user_role === 'health_coach');
$is_admin = ($user_role === 'admin');

// Build query for athletes - health coaches see their assigned athletes, admins see all
if ($is_admin) {
    // Admins can see all athletes
    $athletes_query = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.date_of_birth, u.position,
               u.assigned_coach_id, u.created_by_coach_id,
               (SELECT COUNT(*) FROM athlete_workout_assignments awa WHERE awa.athlete_id = u.id AND awa.status = 'active') as active_workout_plans,
               (SELECT COUNT(*) FROM athlete_nutrition_assignments ana WHERE ana.athlete_id = u.id AND ana.status = 'active') as active_nutrition_plans,
               CONCAT(coach.first_name, ' ', coach.last_name) as assigned_coach_name
        FROM users u
        LEFT JOIN users coach ON u.assigned_coach_id = coach.id
        WHERE u.is_active = 1
    ";
    $params = [];
} else {
    // Health coaches see users assigned to them or created by them (any role can receive health assignments)
    $athletes_query = "
        SELECT u.id, u.first_name, u.last_name, u.email, u.date_of_birth, u.position,
               u.assigned_coach_id, u.created_by_coach_id,
               (SELECT COUNT(*) FROM athlete_workout_assignments awa WHERE awa.athlete_id = u.id AND awa.status = 'active') as active_workout_plans,
               (SELECT COUNT(*) FROM athlete_nutrition_assignments ana WHERE ana.athlete_id = u.id AND ana.status = 'active') as active_nutrition_plans,
               CONCAT(coach.first_name, ' ', coach.last_name) as assigned_coach_name
        FROM users u
        LEFT JOIN users coach ON u.assigned_coach_id = coach.id
        WHERE u.is_active = 1
        AND (u.assigned_coach_id = ? OR u.created_by_coach_id = ?)
    ";
    $params = [$user_id, $user_id];
}

// Apply search filter
if (!empty($search)) {
    $athletes_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply age group filter - validate format first
if ($filter_age !== 'all') {
    // Validate that filter_age matches expected format (e.g., "6-9", "10-11")
    if (preg_match('/^(\d+)-(\d+)$/', $filter_age, $matches)) {
        $min_age = intval($matches[1]);
        $max_age = intval($matches[2]);
        $athletes_query .= " AND TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) BETWEEN ? AND ?";
        $params[] = $min_age;
        $params[] = $max_age;
    }
}

$athletes_query .= " ORDER BY u.last_name, u.first_name LIMIT 100";

$athletes_stmt = $pdo->prepare($athletes_query);
$athletes_stmt->execute($params);
$athletes = $athletes_stmt->fetchAll();
?>

<!-- Health Coach Roster View -->
<?php if (isset($_GET['status']) && $_GET['status'] === 'athlete_created'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Athlete created successfully! A welcome email has been sent with login credentials.</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<?php if (isset($_GET['status']) && $_GET['status'] === 'plan_assigned'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Plan assigned successfully to athlete!</span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #10b981; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="error-alert" style="background: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 20px;"></i>
    <span style="color: #ef4444; font-weight: 600;">
    <?php
    $error_messages = [
        'email_taken' => 'An athlete with this email already exists.',
        'creation_failed' => 'Failed to create athlete. Please try again.',
        'assignment_failed' => 'Failed to assign plan. Please try again.'
    ];
    echo $error_messages[$_GET['error']] ?? 'An error occurred.';
    ?>
    </span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<style>
/* Roster Page Header - Financial Reports Hub Style */
.roster-page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 32px;
    padding-bottom: 24px;
    border-bottom: 1px solid var(--border);
    flex-wrap: wrap;
    gap: 20px;
}
.roster-page-header .page-header-content {
    display: flex;
    align-items: center;
    gap: 20px;
}
.roster-page-header .page-header-icon {
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
.roster-page-header .page-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 4px 0;
    letter-spacing: -0.5px;
}
.roster-page-header .page-description {
    font-size: 14px;
    color: var(--text-dim);
    margin: 0;
}
</style>

<div class="roster-page-header">
    <div class="page-header-content">
        <div class="page-header-icon">
            <i class="fas fa-heart-pulse"></i>
        </div>
        <div class="page-header-text">
            <h1 class="page-title">Athlete Roster</h1>
            <p class="page-description">Manage health plans for your assigned athletes</p>
        </div>
    </div>
</div>

<div class="health-roster-content">
    <!-- Filter Box -->
    <div class="filter-box">
        <div class="filter-box-header">
            <i class="fas fa-filter"></i> Search & Filter Athletes
        </div>
        <div class="filter-box-content">
            <form method="GET" action="" class="filter-row">
                <input type="hidden" name="page" value="health_coach_roster">
                <div class="filter-field" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" name="search" class="form-input" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-field">
                    <label>Age Group</label>
                    <select name="age_group" class="form-select">
                        <option value="all">All Age Groups</option>
                        <option value="6-9" <?= $filter_age === '6-9' ? 'selected' : '' ?>>Under 10</option>
                        <option value="10-11" <?= $filter_age === '10-11' ? 'selected' : '' ?>>Under 12</option>
                        <option value="12-13" <?= $filter_age === '12-13' ? 'selected' : '' ?>>Under 14</option>
                        <option value="14-15" <?= $filter_age === '14-15' ? 'selected' : '' ?>>Under 16</option>
                        <option value="16-17" <?= $filter_age === '16-17' ? 'selected' : '' ?>>Under 18</option>
                    </select>
                </div>
                <div class="filter-field filter-actions">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
                    <a href="?page=health_coach_roster" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="results-info">
            <span><?= count($athletes) ?> athlete<?= count($athletes) !== 1 ? 's' : '' ?> found</span>
        </div>
        <button class="btn-primary" data-action="add" data-modal="add-health-athlete-modal"><i class="fas fa-user-plus"></i> Add Athlete</button>
    </div>

    <!-- Athletes Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> Athletes</h3>
        </div>
        <div class="card-body">
            <?php if (count($athletes) > 0): ?>
            <div class="athletes-table-container" data-component="DataTable">
                <table class="athletes-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Position</th>
                            <th>Workout Plans</th>
                            <th>Nutrition Plans</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($athletes as $athlete): 
                            $age = $athlete['date_of_birth'] ? date_diff(date_create($athlete['date_of_birth']), date_create('today'))->y : 'N/A';
                        ?>
                        <tr data-athlete-id="<?= $athlete['id'] ?>">
                            <td>
                                <div class="athlete-cell">
                                    <div class="athlete-avatar-small">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div class="athlete-info">
                                        <div class="athlete-name"><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></div>
                                        <div class="athlete-email"><?= htmlspecialchars($athlete['email']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= $age ?></td>
                            <td><span class="position-badge"><?= htmlspecialchars($athlete['position'] ?? 'Not Set') ?></span></td>
                            <td>
                                <span class="plan-count <?= $athlete['active_workout_plans'] > 0 ? 'has-plan' : 'no-plan' ?>">
                                    <?= $athlete['active_workout_plans'] ?> active
                                </span>
                            </td>
                            <td>
                                <span class="plan-count <?= $athlete['active_nutrition_plans'] > 0 ? 'has-plan' : 'no-plan' ?>">
                                    <?= $athlete['active_nutrition_plans'] ?> active
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="Assign Workout Plan" 
                                            data-athlete-id="<?= $athlete['id'] ?>"
                                            data-athlete-name="<?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="openAssignWorkoutModal(this.dataset.athleteId, this.dataset.athleteName)">
                                        <i class="fas fa-dumbbell"></i>
                                    </button>
                                    <button class="btn-icon" title="Assign Nutrition Plan" 
                                            data-athlete-id="<?= $athlete['id'] ?>"
                                            data-athlete-name="<?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="openAssignNutritionModal(this.dataset.athleteId, this.dataset.athleteName)">
                                        <i class="fas fa-utensils"></i>
                                    </button>
                                    <a href="?page=athlete_detail&id=<?= $athlete['id'] ?>" class="btn-icon" title="View Profile">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button class="btn-icon" title="Message Athlete" data-action="message-athlete" data-athlete-id="<?= $athlete['id'] ?>">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-users placeholder-icon"></i>
                <p class="placeholder-text">No athletes found. Add new athletes or adjust your filters.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Health Athlete Modal -->
<div id="add-health-athlete-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-user-plus"></i> Add Athlete</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-health-athlete-modal')">&times;</button>
        </div>
        <form method="POST" action="process_create_athlete.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="assign_to_health_coach" value="1">
            
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" name="first_name" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" name="last_name" class="form-input" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Email *</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" name="birth_date" class="form-input" max="<?= date('Y-m-d') ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <select name="position" class="form-input">
                            <option value="">Select Position</option>
                            <option value="Forward">Forward</option>
                            <option value="Defense">Defense</option>
                            <option value="Goalie">Goalie</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('add-health-athlete-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Add Athlete</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Workout Plan Modal -->
<div id="assign-workout-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-dumbbell"></i> Assign Workout Plan</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('assign-workout-modal')">&times;</button>
        </div>
        <form method="POST" action="process_health_assignments.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="assign_workout">
            <input type="hidden" name="athlete_id" id="workout-athlete-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Athlete</label>
                    <input type="text" class="form-input" id="workout-athlete-name" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Workout Plan *</label>
                    <select name="workout_plan_id" class="form-input" required>
                        <option value="">-- Select a workout plan --</option>
                        <?php
                        $plans_stmt = $pdo->query("SELECT id, name FROM workout_plans WHERE is_active = 1 ORDER BY name");
                        while ($plan = $plans_stmt->fetch()):
                        ?>
                            <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-input" required value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-input" rows="3" placeholder="Add any special instructions..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('assign-workout-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Assign Plan</button>
            </div>
        </form>
    </div>
</div>

<!-- Assign Nutrition Plan Modal -->
<div id="assign-nutrition-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-utensils"></i> Assign Nutrition Plan</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('assign-nutrition-modal')">&times;</button>
        </div>
        <form method="POST" action="process_health_assignments.php">
            <?php echo csrfTokenInput(); ?>
            <input type="hidden" name="action" value="assign_nutrition">
            <input type="hidden" name="athlete_id" id="nutrition-athlete-id">
            
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Athlete</label>
                    <input type="text" class="form-input" id="nutrition-athlete-name" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Select Nutrition Plan *</label>
                    <select name="nutrition_plan_id" class="form-input" required>
                        <option value="">-- Select a nutrition plan --</option>
                        <?php
                        $plans_stmt = $pdo->query("SELECT id, name FROM nutrition_plans WHERE is_active = 1 ORDER BY name");
                        while ($plan = $plans_stmt->fetch()):
                        ?>
                            <option value="<?= $plan['id'] ?>"><?= htmlspecialchars($plan['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Start Date *</label>
                    <input type="date" name="start_date" class="form-input" required value="<?= date('Y-m-d') ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Notes (optional)</label>
                    <textarea name="notes" class="form-input" rows="3" placeholder="Add any special instructions..."></textarea>
                </div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('assign-nutrition-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Assign Plan</button>
            </div>
        </form>
    </div>
</div>

<style>
.health-roster-content {
    max-width: 1400px;
}

.athletes-table-container {
    overflow-x: auto;
}

.athletes-table {
    width: 100%;
    border-collapse: collapse;
}

.athletes-table thead {
    background: var(--bg-main);
}

.athletes-table th {
    padding: 16px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.athletes-table td {
    padding: 16px;
    border-bottom: 1px solid var(--border);
    font-size: 14px;
    color: var(--text-white);
}

.athletes-table tbody tr {
    transition: all 0.3s;
}

.athletes-table tbody tr:hover {
    background: var(--bg-main);
}

.athlete-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.athlete-avatar-small {
    width: 40px;
    height: 40px;
    background: var(--bg-main);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: var(--text-dim);
    flex-shrink: 0;
}

.athlete-info {
    display: flex;
    flex-direction: column;
}

.athlete-name {
    font-weight: 700;
    color: var(--text-white);
}

.athlete-email {
    font-size: 12px;
    color: var(--text-dim);
}

.position-badge {
    display: inline-block;
    background: rgba(107, 70, 193, 0.1);
    color: var(--primary);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.plan-count {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
}

.plan-count.has-plan {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.plan-count.no-plan {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.table-actions {
    display: flex;
    gap: 5px;
}

.table-actions .btn-icon {
    width: 32px;
    height: 32px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s;
}

.table-actions .btn-icon:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* Filter Box Styles */
.filter-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    margin-bottom: 24px;
    overflow: hidden;
}

.filter-box-header {
    background: var(--bg-main);
    padding: 14px 20px;
    font-weight: 700;
    color: var(--text-white);
    font-size: 14px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-box-header i {
    color: var(--primary);
}

.filter-box-content {
    padding: 20px;
}

.filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 16px;
    align-items: end;
}

.filter-field {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-field label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-actions {
    display: flex;
    flex-direction: row !important;
    gap: 8px !important;
    align-items: flex-end;
}

.filter-actions label {
    display: none;
}

.action-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.results-info {
    color: var(--text-dim);
    font-size: 14px;
}

.placeholder-container {
    text-align: center;
    padding: 60px 20px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--text-dim);
    opacity: 0.3;
    margin-bottom: 20px;
}

.placeholder-text {
    color: var(--text-dim);
    font-size: 14px;
}
</style>

<script>
function openAssignWorkoutModal(athleteId, athleteName) {
    document.getElementById('workout-athlete-id').value = athleteId;
    document.getElementById('workout-athlete-name').value = athleteName;
    openModal('assign-workout-modal');
}

function openAssignNutritionModal(athleteId, athleteName) {
    document.getElementById('nutrition-athlete-id').value = athleteId;
    document.getElementById('nutrition-athlete-name').value = athleteName;
    openModal('assign-nutrition-modal');
}
</script>
