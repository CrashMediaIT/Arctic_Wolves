<?php
// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_program = $_GET['program'] ?? 'all';
$filter_age = $_GET['age_group'] ?? 'all';

// Build query for athletes
$athletes_query = "
    SELECT u.id, u.first_name, u.last_name, u.email, COALESCE(u.date_of_birth, u.birth_date) as date_of_birth,
           (SELECT COUNT(*) FROM bookings b JOIN sessions s ON b.session_id = s.id WHERE b.user_id = u.id AND s.status = 'completed') as total_sessions,
           (SELECT COUNT(*) FROM user_packages up WHERE up.user_id = u.id) as package_sessions,
           (SELECT MAX(s.session_date) FROM bookings b JOIN sessions s ON b.session_id = s.id WHERE b.user_id = u.id) as last_session,
           p.name as program_name
    FROM users u
    LEFT JOIN athlete_programs ap ON ap.athlete_id = u.id AND ap.status = 'active'
    LEFT JOIN programs p ON ap.program_id = p.id
    WHERE u.is_active = 1
    AND (
        u.assigned_coach_id = ?
        OR u.created_by_coach_id = ?
        OR EXISTS (SELECT 1 FROM bookings b JOIN sessions s ON b.session_id = s.id WHERE s.coach_id = ? AND b.user_id = u.id)
        OR EXISTS (SELECT 1 FROM bookings b JOIN sessions s ON b.session_id = s.id JOIN session_coaches sc ON sc.session_id = s.id WHERE sc.coach_id = ? AND b.user_id = u.id)
    )
";

$params = [$user_id, $user_id, $user_id, $user_id];

// Apply search filter
if (!empty($search)) {
    $athletes_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

// Apply program filter  
if ($filter_program !== 'all') {
    $athletes_query .= " AND p.id = ?";
    $params[] = $filter_program;
}

// Apply age group filter
if ($filter_age !== 'all') {
    [$min_age, $max_age] = explode('-', $filter_age);
    $athletes_query .= " AND TIMESTAMPDIFF(YEAR, u.date_of_birth, CURDATE()) BETWEEN ? AND ?";
    $params[] = $min_age;
    $params[] = $max_age;
}

$athletes_query .= " ORDER BY u.last_name, u.first_name LIMIT 100";

$athletes_stmt = $pdo->prepare($athletes_query);
$athletes_stmt->execute($params);
$athletes = $athletes_stmt->fetchAll();

// Get programs for filter
$programs = $pdo->query("SELECT id, name FROM programs WHERE is_active = 1 ORDER BY name")->fetchAll();
?>

<style>
/* Coach Roster - View-specific styles */
</style>

<!-- Coach Roster View -->
<?php if (isset($_GET['status']) && $_GET['status'] === 'athlete_created'): ?>
<div class="success-alert" style="background: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; border-radius: 8px; padding: 16px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
    <i class="fas fa-check-circle" style="color: #10b981; font-size: 20px;"></i>
    <span style="color: #10b981; font-weight: 600;">Athlete created successfully! A welcome email has been sent with login credentials.</span>
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
        'creation_failed' => 'Failed to create athlete. Please try again.'
    ];
    echo $error_messages[$_GET['error']] ?? 'An error occurred.';
    ?>
    </span>
    <button type="button" onclick="this.parentElement.remove()" style="margin-left: auto; background: none; border: none; color: #ef4444; cursor: pointer; font-size: 18px;">&times;</button>
</div>
<?php endif; ?>

<div class="page-header">
    <h1 class="page-title"><i class="fas fa-users-gear"></i> Athlete Roster</h1>
    <p class="page-description">Manage your athletes and track their progress</p>
</div>

<div class="coach-roster-content">
    <!-- Filter Box - Separated from title bar -->
    <div class="filter-box">
        <div class="filter-box-header">
            <i class="fas fa-filter"></i> Search & Filter Athletes
        </div>
        <div class="filter-box-content">
            <form method="GET" action="" class="filter-row">
                <input type="hidden" name="page" value="roster">
                <div class="filter-field" style="flex: 2;">
                    <label>Search</label>
                    <input type="text" name="search" class="form-input" placeholder="Search by name or email..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="filter-field">
                    <label>Program</label>
                    <select name="program" class="form-select">
                        <option value="all">All Programs</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['id'] ?>" <?= $filter_program == $prog['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prog['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
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
                    <a href="?page=roster" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Action Bar -->
    <div class="action-bar">
        <div class="results-info">
            <span><?= count($athletes) ?> athlete<?= count($athletes) !== 1 ? 's' : '' ?> found</span>
        </div>
        <button class="btn-primary" data-action="add" data-modal="add-athlete-modal"><i class="fas fa-user-plus"></i> Add Athlete</button>
    </div>

    <!-- Athletes Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> My Athletes</h3>
            <div class="view-toggle">
                <button class="view-btn active" data-view="table" title="Table View"><i class="fas fa-table"></i></button>
                <button class="view-btn" data-view="grid" title="Grid View"><i class="fas fa-th"></i></button>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($athletes) > 0): ?>
            <div class="athletes-table-container" data-component="DataTable">
                <table class="athletes-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Age</th>
                            <th>Program</th>
                            <th>Sessions</th>
                            <th>Last Session</th>
                            <th>Progress</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($athletes as $athlete): 
                            $age = !empty($athlete['date_of_birth']) ? date_diff(date_create($athlete['date_of_birth']), date_create('today'))->y : null;
                            $session_progress = $athlete['package_sessions'] > 0 ? ($athlete['total_sessions'] / $athlete['package_sessions']) * 100 : 0;
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
                            <td><?= $age !== null ? $age : 'N/A' ?></td>
                            <td><span class="program-badge"><?= htmlspecialchars($athlete['program_name'] ?? 'None') ?></span></td>
                            <td>
                                <div class="sessions-info">
                                    <span class="sessions-count"><?= $athlete['total_sessions'] ?> / <?= $athlete['package_sessions'] ?></span>
                                    <div class="mini-progress">
                                        <div class="mini-progress-bar" style="width: <?= min($session_progress, 100) ?>%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= $athlete['last_session'] ? date('M d, Y', strtotime($athlete['last_session'])) : 'Never' ?></td>
                            <td>
                                <span class="progress-badge <?= $session_progress >= 70 ? 'excellent' : ($session_progress >= 40 ? 'good' : 'needs-attention') ?>">
                                    <?= $session_progress >= 70 ? 'Excellent' : ($session_progress >= 40 ? 'Good' : 'Needs Attention') ?>
                                </span>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button class="btn-icon" title="View Profile" data-action="view-profile" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-eye"></i></button>
                                    <a href="?page=stats&tab=goals&athlete_id=<?= $athlete['id'] ?>" class="btn-icon" title="Manage Goals"><i class="fas fa-bullseye"></i></a>
                                    <button class="btn-icon" title="Schedule Session" data-action="schedule-session" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-calendar-plus"></i></button>
                                    <button class="btn-icon" title="Message" data-action="message-athlete" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-envelope"></i></button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="athletes-grid-container" style="display: none;">
                <div class="athletes-grid">
                    <?php foreach ($athletes as $athlete): 
                        $age = !empty($athlete['date_of_birth']) ? date_diff(date_create($athlete['date_of_birth']), date_create('today'))->y : null;
                        $session_progress = $athlete['package_sessions'] > 0 ? ($athlete['total_sessions'] / $athlete['package_sessions']) * 100 : 0;
                    ?>
                    <div class="athlete-grid-card" data-athlete-id="<?= $athlete['id'] ?>">
                        <div class="athlete-grid-avatar">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="athlete-grid-name"><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></div>
                        <div class="athlete-grid-email"><?= htmlspecialchars($athlete['email']) ?></div>
                        <div class="athlete-grid-details">
                            <span class="athlete-grid-age"><?= $age !== null ? $age . ' yrs' : 'N/A' ?></span>
                            <span class="program-badge"><?= htmlspecialchars($athlete['program_name'] ?? 'None') ?></span>
                        </div>
                        <div class="athlete-grid-sessions">
                            <span class="sessions-count"><?= $athlete['total_sessions'] ?> / <?= $athlete['package_sessions'] ?> sessions</span>
                            <div class="mini-progress">
                                <div class="mini-progress-bar" style="width: <?= min($session_progress, 100) ?>%;"></div>
                            </div>
                        </div>
                        <div class="athlete-grid-actions">
                            <button class="btn-icon" title="View Profile" data-action="view-profile" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-eye"></i></button>
                            <a href="?page=stats&tab=goals&athlete_id=<?= $athlete['id'] ?>" class="btn-icon" title="Manage Goals"><i class="fas fa-bullseye"></i></a>
                            <button class="btn-icon" title="Schedule Session" data-action="schedule-session" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-calendar-plus"></i></button>
                            <button class="btn-icon" title="Message" data-action="message-athlete" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-envelope"></i></button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php else: ?>
            <div class="placeholder-container">
                <i class="fas fa-users placeholder-icon"></i>
                <p class="placeholder-text">No athletes found. Adjust your filters or add new athletes.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Athlete Modal -->
<div id="add-athlete-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title"><i class="fas fa-user-plus"></i> Add Athlete</h2>
            <button class="modal-close" aria-label="Close modal" onclick="closeModal('add-athlete-modal')">&times;</button>
        </div>
        <form method="POST" action="process_create_athlete.php">
            <?php echo csrfTokenInput(); ?>
            
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
                <button type="button" class="btn-secondary" onclick="closeModal('add-athlete-modal')"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" class="btn-primary"><i class="fas fa-check"></i> Add Athlete</button>
            </div>
        </form>
    </div>
</div>

<style>
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

.program-badge {
    display: inline-block;
    background: rgba(255, 77, 0, 0.1);
    color: var(--neon);
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.sessions-info {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.sessions-count {
    font-size: 13px;
    font-weight: 700;
}

.mini-progress {
    width: 80px;
    height: 6px;
    background: var(--bg-main);
    border-radius: 3px;
    overflow: hidden;
}

.mini-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, var(--neon), var(--accent));
    border-radius: 3px;
    transition: width 0.5s;
}

.progress-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.progress-badge.excellent {
    background: rgba(16, 185, 129, 0.1);
    color: #10b981;
}

.progress-badge.good {
    background: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
}

.progress-badge.needs-attention {
    background: rgba(245, 158, 11, 0.1);
    color: #f59e0b;
}

.table-actions {
    display: flex;
    gap: 5px;
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

.results-info {
    color: var(--text-dim);
    font-size: 14px;
}

/* Grid View Styles */
.athletes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    padding: 10px 0;
}

.athlete-grid-card {
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 20px;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    transition: all 0.3s;
}

.athlete-grid-card:hover {
    border-color: var(--primary);
}

.athlete-grid-avatar {
    width: 60px;
    height: 60px;
    background: var(--bg-card);
    border: 2px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: var(--text-dim);
}

.athlete-grid-name {
    font-weight: 700;
    color: var(--text-white);
    font-size: 16px;
}

.athlete-grid-email {
    font-size: 12px;
    color: var(--text-dim);
}

.athlete-grid-details {
    display: flex;
    gap: 10px;
    align-items: center;
}

.athlete-grid-age {
    font-size: 13px;
    color: var(--text-dim);
}

.athlete-grid-sessions {
    width: 100%;
    display: flex;
    flex-direction: column;
    gap: 5px;
    align-items: center;
}

.athlete-grid-actions {
    display: flex;
    gap: 8px;
    margin-top: 5px;
}
</style>

<script>
// View toggle for table/grid
document.querySelectorAll('.view-btn[data-view]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var view = this.getAttribute('data-view');
        var tableContainer = document.querySelector('.athletes-table-container');
        var gridContainer = document.querySelector('.athletes-grid-container');

        document.querySelectorAll('.view-btn[data-view]').forEach(function(b) {
            b.classList.remove('active');
        });
        this.classList.add('active');

        if (view === 'grid') {
            if (tableContainer) tableContainer.style.display = 'none';
            if (gridContainer) gridContainer.style.display = 'block';
        } else {
            if (tableContainer) tableContainer.style.display = 'block';
            if (gridContainer) gridContainer.style.display = 'none';
        }
    });
});
</script>
