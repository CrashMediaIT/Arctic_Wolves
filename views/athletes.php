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
           (SELECT COUNT(*) FROM athlete_teams at WHERE at.athlete_id = u.id AND at.status = 'active') as current_teams,
           (SELECT COUNT(*) FROM bookings b INNER JOIN sessions s ON b.session_id = s.id WHERE b.user_id = u.id AND b.status IN ('confirmed', 'waitlisted') AND s.session_date <= CURDATE()) as sessions_attended,
           (SELECT GROUP_CONCAT(t.name SEPARATOR ', ') FROM athlete_teams at2 INNER JOIN teams t ON at2.team_id = t.id WHERE at2.athlete_id = u.id AND at2.status = 'active') as team_names
    FROM users u
    WHERE u.assigned_coach_id = ?
";

$params = [$user_id];

// Add filter conditions
if (!empty($filter_team)) {
    $query .= " AND EXISTS (SELECT 1 FROM athlete_teams at WHERE at.athlete_id = u.id AND at.team_id = ? AND at.status = 'active')";
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
    if (FieldEncryption::isConfigured()) {
        // When encryption is enabled, names are encrypted so search by email only
        $query .= " AND u.email LIKE ?";
        $search_term = '%' . $filter_name . '%';
        $params[] = $search_term;
    } else {
        $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
        $search_term = '%' . $filter_name . '%';
        $params[] = $search_term;
        $params[] = $search_term;
        $params[] = $search_term;
    }
}

$query .= " ORDER BY u.last_name, u.first_name";

$athletes_stmt = $pdo->prepare($query);
$athletes_stmt->execute($params);
$athletes = $athletes_stmt->fetchAll();
$athletes = decryptUserRows($athletes);

// Get teams for filter dropdown
$teams_stmt = $pdo->query("SELECT id, name FROM teams ORDER BY name");
$teams = $teams_stmt->fetchAll();

// Get age groups for filter dropdown
$age_groups_stmt = $pdo->query("SELECT id, name FROM age_groups ORDER BY min_age");
$age_groups = $age_groups_stmt->fetchAll();
?>

<style>
    /* =========================================================
       ATHLETES PAGE - Component Specific Styles
       ========================================================= */
    
    /* Stats Summary Enhancement */
    .stats-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 28px;
    }
    
    .summary-card {
        background: linear-gradient(135deg, #6B46C1 0%, #4a0070 100%);
        border-radius: 12px;
        padding: 24px;
        color: #fff;
        box-shadow: 0 8px 24px rgba(107, 70, 193, 0.2);
        transition: all 0.3s ease;
    }
    
    .summary-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 32px rgba(107, 70, 193, 0.3);
    }
    
    .summary-value {
        font-size: 36px;
        font-weight: 900;
        margin-bottom: 6px;
        letter-spacing: -1px;
    }
    
    .summary-label {
        font-size: 13px;
        opacity: 0.9;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    /* Athletes Grid Enhancement */
    .athletes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
        gap: 24px;
    }
    
    .athlete-card {
        background: #16161f;
        border: 1px solid #2d2d3f;
        border-radius: 12px;
        padding: 24px;
        transition: all 0.3s ease;
    }
    
    .athlete-card:hover {
        border-color: #6B46C1;
        transform: translateY(-4px);
        box-shadow: 0 12px 32px rgba(107, 70, 193, 0.2);
    }
    
    .athlete-header {
        display: flex;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .athlete-avatar {
        width: 72px;
        height: 72px;
        background: linear-gradient(135deg, #6B46C1 0%, #4a0070 100%);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 26px;
        font-weight: 900;
        flex-shrink: 0;
        box-shadow: 0 4px 16px rgba(107, 70, 193, 0.3);
    }
    
    .athlete-info {
        flex: 1;
    }
    
    .athlete-name {
        font-size: 18px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 8px;
    }
    
    .athlete-meta {
        font-size: 13px;
        color: #9ca3af;
        margin-bottom: 4px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    
    .athlete-meta i {
        color: #6B46C1;
        width: 16px;
        text-align: center;
    }
    
    /* Stats Box Enhancement */
    .athlete-stats {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 12px;
        margin: 20px 0;
    }
    
    .stat-box {
        background: #0d1117;
        border: 1px solid #2d2d3f;
        border-radius: 10px;
        padding: 14px 10px;
        text-align: center;
        transition: all 0.3s ease;
    }
    
    .stat-box:hover {
        border-color: #6B46C1;
    }
    
    .stat-value {
        font-size: 22px;
        font-weight: 900;
        color: #6B46C1;
        display: block;
        margin-bottom: 4px;
    }
    
    .stat-label {
        font-size: 11px;
        color: #9ca3af;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 0.5px;
    }
    
    /* Action Buttons Enhancement */
    .athlete-actions {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .btn-action {
        padding: 11px 14px;
        background: #6B46C1;
        color: #fff;
        text-align: center;
        border-radius: 8px;
        text-decoration: none;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s ease;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
    }
    
    .btn-action:hover {
        background: #7C3AED;
        transform: translateY(-1px);
    }
    
    .btn-action.secondary {
        background: transparent;
        border: 1px solid #2d2d3f;
        color: #e0e0e0;
    }
    
    .btn-action.secondary:hover {
        background: rgba(107, 70, 193, 0.1);
        border-color: #6B46C1;
        color: #6B46C1;
    }
    
    /* Empty State Enhancement */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        background: #16161f;
        border: 1px solid #2d2d3f;
        border-radius: 12px;
    }
    
    .empty-state i {
        font-size: 64px;
        color: #6B46C1;
        opacity: 0.4;
        margin-bottom: 20px;
        display: block;
    }
    
    .empty-state h2 {
        font-size: 22px;
        color: #fff;
        margin-bottom: 10px;
    }
    
    .empty-state p {
        color: #9ca3af;
        font-size: 14px;
    }
    
    /* Filter Box */
    .filter-box { background: var(--bg-card, #16161F); border: 1px solid var(--border, #2D2D3F); border-radius: 12px; margin-bottom: 24px; overflow: hidden; }
    .filter-box-header { background: var(--bg-main, #0A0A0F); padding: 14px 20px; font-weight: 700; color: var(--text-white, #fff); font-size: 14px; border-bottom: 1px solid var(--border, #2D2D3F); display: flex; align-items: center; gap: 10px; }
    .filter-box-header i { color: var(--primary, #6B46C1); }
    .filter-box-content { padding: 20px; }
    .filter-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; align-items: end; }
    .filter-field { display: flex; flex-direction: column; gap: 8px; }
    .filter-field label { font-size: 12px; font-weight: 600; color: var(--text-dim, #9CA3AF); text-transform: uppercase; }
    .filter-actions { display: flex; flex-direction: row !important; gap: 8px !important; }
    
    /* Responsive Design */
    @media (max-width: 768px) {
        .athletes-grid {
            grid-template-columns: 1fr;
        }
        
        .filter-row {
            grid-template-columns: 1fr;
        }
        
        .athlete-actions {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="page-header">
    <div class="page-header-content">
        <h1 class="page-title"><i class="fas fa-users"></i> My Athletes</h1>
        <p class="page-description">Manage and track your coached athletes</p>
    </div>
    <?php if ($user_role === 'admin'): ?>
        <a href="?page=manage_athletes&action=create" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Add Athlete
        </a>
    <?php endif; ?>
</div>

<!-- Filter Bar -->
<div class="filter-box">
    <div class="filter-box-header"><i class="fas fa-filter"></i> Filter Athletes</div>
    <div class="filter-box-content">
        <form method="GET" action="dashboard.php" class="filter-row">
            <input type="hidden" name="page" value="athletes">
            <div class="filter-field">
                <label>Team</label>
                <select name="filter_team" class="form-select">
                    <option value="">All Teams</option>
                    <?php foreach ($teams as $team): ?>
                    <option value="<?= $team['id'] ?>" <?= $filter_team == $team['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($team['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Age Group</label>
                <select name="filter_age_group" class="form-select">
                    <option value="">All Ages</option>
                    <?php foreach ($age_groups as $age_group): ?>
                    <option value="<?= $age_group['id'] ?>" <?= $filter_age_group == $age_group['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($age_group['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-field">
                <label>Name / Email</label>
                <input type="text" name="filter_name" class="form-select" placeholder="Search by name or email"
                       value="<?= htmlspecialchars($filter_name) ?>">
            </div>
            <div class="filter-field filter-actions">
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Apply</button>
                <a href="dashboard.php?page=athletes" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
            </div>
        </form>
    </div>
</div>

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
                    <a href="?page=athlete_notes&athlete_id=<?= $athlete['id'] ?>" class="btn-action secondary">
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
