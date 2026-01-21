<?php
/**
 * Athlete Management (Coach View)
 * Manage coached athletes, notes, and assignments
 */

require_once __DIR__ . '/../security.php';

/**
 * Format athlete position to proper title case
 * Maps lowercase position values to proper display format
 */
function formatPosition($position) {
    $position_map = [
        'forward' => 'Forward',
        'defense' => 'Defense',
        'goalie' => 'Goalie'
    ];
    
    // For known positions, return directly. For unknown, escape and format
    $lower_position = strtolower($position ?? '');
    if (isset($position_map[$lower_position])) {
        return $position_map[$lower_position];
    }
    return htmlspecialchars(ucfirst($position));
}

// Check if user has permission
if (!in_array($user_role, ['coach', 'coach_plus', 'admin'])) {
    header('Location: dashboard.php?page=home');
    exit;
}

// Get filter parameters
$filter_team = $_GET['filter_team'] ?? '';
$filter_age_group = $_GET['filter_age_group'] ?? '';
$filter_name = $_GET['filter_name'] ?? '';

// Build query with filters
$query = "
    SELECT u.*, 
           (SELECT COUNT(*) FROM athlete_notes WHERE user_id = u.id) as note_count,
           (SELECT COUNT(*) FROM athlete_teams at WHERE at.user_id = u.id AND at.is_current = 1) as current_teams,
           (SELECT COUNT(*) FROM bookings b INNER JOIN sessions s ON b.session_id = s.id WHERE (b.user_id = u.id OR b.booked_for_user_id = u.id) AND b.status = 'paid' AND s.session_date <= CURDATE()) as sessions_attended,
           (SELECT GROUP_CONCAT(at2.name SEPARATOR ', ') FROM athlete_teams at2 WHERE at2.user_id = u.id AND at2.is_current = 1) as team_names
    FROM users u
    WHERE u.assigned_coach_id = ? AND u.role = 'athlete'
";

$params = [$user_id];

// Add filter conditions
if (!empty($filter_team)) {
    $query .= " AND EXISTS (SELECT 1 FROM athlete_teams at WHERE at.user_id = u.id AND at.id = ? AND at.is_current = 1)";
    $params[] = $filter_team;
}

if (!empty($filter_age_group)) {
    $query .= " AND TIMESTAMPDIFF(YEAR, u.birth_date, CURDATE()) BETWEEN 
                (SELECT min_age FROM age_groups WHERE id = ?) AND 
                (SELECT max_age FROM age_groups WHERE id = ?)";
    $params[] = $filter_age_group;
    $params[] = $filter_age_group;
}

if (!empty($filter_name)) {
    $query .= " AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR u.email LIKE ?)";
    $search_term = '%' . $filter_name . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

$query .= " ORDER BY u.last_name, u.first_name";

$athletes_stmt = $pdo->prepare($query);
$athletes_stmt->execute($params);
$athletes = $athletes_stmt->fetchAll();

// Get teams for filter dropdown
$teams_stmt = $pdo->query("SELECT id, name FROM athlete_teams WHERE is_current = 1 ORDER BY name");
$teams = $teams_stmt->fetchAll();

// Get age groups for filter dropdown
$age_groups_stmt = $pdo->query("SELECT id, name FROM age_groups ORDER BY min_age");
$age_groups = $age_groups_stmt->fetchAll();
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
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .summary-card {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 20px;
        color: #fff;
    }
    .summary-value {
        font-size: 32px;
        font-weight: 900;
        margin-bottom: 5px;
    }
    .summary-label {
        font-size: 13px;
        opacity: 0.9;
    }
    .athletes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
        gap: 20px;
    }
    .athlete-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 25px;
        transition: all 0.2s;
    }
    .athlete-card:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
    }
    .athlete-header {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }
    .athlete-avatar {
        width: 80px;
        height: 80px;
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 32px;
        font-weight: 900;
        flex-shrink: 0;
    }
    .athlete-info {
        flex: 1;
    }
    .athlete-name {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    .athlete-meta {
        font-size: 13px;
        color: #64748b;
        margin-bottom: 5px;
    }
    .athlete-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
        margin: 20px 0;
    }
    .stat-box {
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        padding: 15px;
        text-align: center;
    }
    .stat-value {
        font-size: 24px;
        font-weight: 900;
        color: var(--primary);
        display: block;
    }
    .stat-label {
        font-size: 11px;
        color: #64748b;
        text-transform: uppercase;
        font-weight: 700;
        margin-top: 5px;
    }
    .athlete-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    .btn-action {
        padding: 10px;
        background: var(--primary);
        color: #fff;
        text-align: center;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.2s;
        border: none;
        cursor: pointer;
    }
    .btn-action:hover {
        background: #e64500;
    }
    .btn-action.secondary {
        background: transparent;
        border: 1px solid var(--primary);
        color: var(--primary);
    }
    .btn-action.secondary:hover {
        background: var(--primary);
        color: #fff;
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
    .filter-bar {
        background: #0a0f14;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 30px;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    .filter-label {
        font-size: 11px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .filter-control {
        padding: 10px 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .filter-control:focus {
        outline: none;
        border-color: var(--primary);
    }
    .btn-filter {
        padding: 10px 20px;
        background: var(--primary);
        color: #fff;
        border: none;
        border-radius: 6px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-filter:hover {
        background: #5a0085;
    }
    .btn-clear {
        padding: 10px 20px;
        background: transparent;
        color: #94a3b8;
        border: 1px solid #1e293b;
        border-radius: 6px;
        font-weight: 700;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-clear:hover {
        border-color: var(--primary);
        color: var(--primary);
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i> My Athletes
    </h1>
    <?php if ($user_role === 'admin'): ?>
        <a href="?page=manage_athletes&action=create" class="btn-create">
            <i class="fas fa-user-plus"></i> Add Athlete
        </a>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<form method="GET" action="dashboard.php" class="filter-bar">
    <input type="hidden" name="page" value="athletes">
    
    <div class="filter-group">
        <label class="filter-label">Team</label>
        <select name="filter_team" class="filter-control">
            <option value="">All Teams</option>
            <?php foreach ($teams as $team): ?>
            <option value="<?= $team['id'] ?>" <?= $filter_team == $team['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($team['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Age Group</label>
        <select name="filter_age_group" class="filter-control">
            <option value="">All Ages</option>
            <?php foreach ($age_groups as $age_group): ?>
            <option value="<?= $age_group['id'] ?>" <?= $filter_age_group == $age_group['id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($age_group['name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="filter-group">
        <label class="filter-label">Name / Email</label>
        <input type="text" name="filter_name" class="filter-control" placeholder="Search by name or email" 
               value="<?= htmlspecialchars($filter_name) ?>">
    </div>
    
    <div class="filter-group" style="display: flex; gap: 10px;">
        <button type="submit" class="btn-filter">
            <i class="fas fa-filter"></i> Filter
        </button>
        <a href="dashboard.php?page=athletes" class="btn-clear" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">
            <i class="fas fa-times"></i> Clear
        </a>
    </div>
</form>

<div class="stats-summary">
    <div class="summary-card">
        <div class="summary-value"><?= count($athletes) ?></div>
        <div class="summary-label">Total Athletes</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">
            <?= array_sum(array_column($athletes, 'sessions_attended')) ?>
        </div>
        <div class="summary-label">Total Sessions Attended</div>
    </div>
    <div class="summary-card">
        <div class="summary-value">
            <?= array_sum(array_column($athletes, 'note_count')) ?>
        </div>
        <div class="summary-label">Total Notes</div>
    </div>
</div>

<?php if (empty($athletes)): ?>
    <div class="empty-state">
        <i class="fas fa-users-slash"></i>
        <h2 style="font-size: 24px; color: #fff; margin-bottom: 10px;">No Athletes Assigned</h2>
        <p style="color: #64748b;">Athletes will appear here when assigned to you</p>
    </div>
<?php else: ?>
    <div class="athletes-grid">
        <?php foreach ($athletes as $athlete): ?>
            <?php
            $initials = strtoupper(substr($athlete['first_name'], 0, 1) . substr($athlete['last_name'], 0, 1));
            ?>
            <div class="athlete-card">
                <div class="athlete-header">
                    <div class="athlete-avatar">
                        <?= $initials ?>
                    </div>
                    <div class="athlete-info">
                        <div class="athlete-name">
                            <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                        </div>
                        <?php if ($athlete['position']): ?>
                            <div class="athlete-meta">
                                <i class="fas fa-hockey-puck"></i>
                                <?= formatPosition($athlete['position']) ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($athlete['birth_date']): ?>
                            <div class="athlete-meta">
                                <i class="fas fa-birthday-cake"></i>
                                <?php
                                $age = date_diff(date_create($athlete['birth_date']), date_create('today'))->y;
                                echo $age . ' years old';
                                ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($athlete['height'] || $athlete['weight']): ?>
                            <div class="athlete-meta">
                                <i class="fas fa-ruler-vertical"></i>
                                <?php if ($athlete['height']) echo $athlete['height'] . 'cm'; ?>
                                <?php if ($athlete['height'] && $athlete['weight']) echo ' • '; ?>
                                <?php if ($athlete['weight']) echo $athlete['weight'] . 'lbs'; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($athlete['shooting_hand']): ?>
                            <div class="athlete-meta">
                                <i class="fas fa-hand-point-right"></i>
                                Shoots: <?= htmlspecialchars(ucfirst($athlete['shooting_hand'])) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="athlete-stats">
                    <div class="stat-box">
                        <span class="stat-value"><?= $athlete['sessions_attended'] ?></span>
                        <span class="stat-label">Sessions</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value"><?= $athlete['current_teams'] ?></span>
                        <span class="stat-label">Teams</span>
                    </div>
                    <div class="stat-box">
                        <span class="stat-value"><?= $athlete['note_count'] ?></span>
                        <span class="stat-label">Notes</span>
                    </div>
                </div>
                
                <div class="athlete-actions">
                    <a href="?page=stats&athlete_id=<?= $athlete['id'] ?>" class="btn-action">
                        <i class="fas fa-chart-line"></i> View Stats
                    </a>
                    <a href="?page=manage_athletes&action=notes&id=<?= $athlete['id'] ?>" class="btn-action secondary">
                        <i class="fas fa-sticky-note"></i> Notes
                    </a>
                    <a href="?page=workouts&athlete_id=<?= $athlete['id'] ?>" class="btn-action secondary">
                        <i class="fas fa-dumbbell"></i> Workouts
                    </a>
                    <a href="?page=nutrition&athlete_id=<?= $athlete['id'] ?>" class="btn-action secondary">
                        <i class="fas fa-apple-whole"></i> Nutrition
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
