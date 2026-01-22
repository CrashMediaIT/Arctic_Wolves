<?php
// Get filter parameters
$search = $_GET['search'] ?? '';
$filter_program = $_GET['program'] ?? 'all';
$filter_age = $_GET['age_group'] ?? 'all';

// Build query for athletes
$athletes_query = "
    SELECT u.id, u.first_name, u.last_name, u.email, u.date_of_birth,
           (SELECT COUNT(*) FROM sessions WHERE athlete_id = u.id AND status = 'completed') as total_sessions,
           (SELECT COUNT(DISTINCT ps.package_id) FROM package_sessions ps JOIN bookings b ON ps.package_id = b.package_id WHERE b.user_id = u.id) as package_sessions,
           (SELECT MAX(session_date) FROM sessions WHERE athlete_id = u.id) as last_session,
           p.name as program_name
    FROM users u
    LEFT JOIN athlete_programs ap ON ap.athlete_id = u.id AND ap.status = 'active'
    LEFT JOIN programs p ON ap.program_id = p.id
    WHERE u.role = 'athlete' AND u.is_active = 1
    AND EXISTS (SELECT 1 FROM sessions WHERE coach_id = ? AND athlete_id = u.id)
";

$params = [$user_id];

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

<!-- Coach Roster View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-user-friends"></i> Athlete Roster
    </h1>
    <p class="page-description">Manage your athletes and track their progress</p>
</div>

<div class="coach-roster-content">
    <!-- Filter and Actions Bar -->
    <div class="action-bar">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="page" value="coach_roster">
            <input type="text" name="search" class="form-input-small" placeholder="Search athletes..." value="<?= htmlspecialchars($search) ?>" data-action="search-debounce">
            <select name="program" class="form-input-small" data-action="auto-submit">
                <option value="all">All Programs</option>
                <?php foreach ($programs as $prog): ?>
                    <option value="<?= $prog['id'] ?>" <?= $filter_program == $prog['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($prog['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <select name="age_group" class="form-input-small" data-action="auto-submit">
                <option value="all">All Age Groups</option>
                <option value="6-9" <?= $filter_age === '6-9' ? 'selected' : '' ?>>Under 10</option>
                <option value="10-11" <?= $filter_age === '10-11' ? 'selected' : '' ?>>Under 12</option>
                <option value="12-13" <?= $filter_age === '12-13' ? 'selected' : '' ?>>Under 14</option>
                <option value="14-15" <?= $filter_age === '14-15' ? 'selected' : '' ?>>Under 16</option>
                <option value="16-17" <?= $filter_age === '16-17' ? 'selected' : '' ?>>Under 18</option>
            </select>
        </form>
        <button class="btn-primary" data-action="add-athlete"><i class="fas fa-user-plus"></i> Add Athlete</button>
    </div>

    <!-- Athletes Table -->
    <div class="content-card">
        <div class="card-header">
            <h3><i class="fas fa-users"></i> My Athletes (<?= count($athletes) ?>)</h3>
            <div class="view-toggle">
                <button class="view-btn active" data-view="table"><i class="fas fa-table"></i></button>
                <button class="view-btn" data-view="grid"><i class="fas fa-th"></i></button>
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
                            $age = date_diff(date_create($athlete['date_of_birth']), date_create('today'))->y;
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
                            <td><?= $age ?></td>
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
                                    <button class="btn-icon" title="Schedule Session" data-action="schedule-session" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-calendar-plus"></i></button>
                                    <button class="btn-icon" title="Message" data-action="message-athlete" data-athlete-id="<?= $athlete['id'] ?>"><i class="fas fa-envelope"></i></button>
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
                <p class="placeholder-text">No athletes found. Adjust your filters or add new athletes.</p>
            </div>
            <?php endif; ?>
        </div>
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
    padding: 15px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border);
}

.athletes-table td {
    padding: 15px;
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
</style>
