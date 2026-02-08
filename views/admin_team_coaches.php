<?php
/**
 * Team Coach Assignments Management
 * Admins can assign team coaches to teams for specific seasons
 */

require_once __DIR__ . '/../security.php';

// Only admins can access
if ($user_role !== 'admin') {
    header('Location: dashboard.php');
    exit;
}

// Get all seasons
$seasons_stmt = $pdo->query("SELECT * FROM seasons ORDER BY start_date DESC");
$seasons = $seasons_stmt->fetchAll();

// Get active season
$active_season_stmt = $pdo->query("SELECT * FROM seasons WHERE is_active = 1 LIMIT 1");
$active_season = $active_season_stmt->fetch();

// Get all team coaches
$coaches_stmt = $pdo->query("
    SELECT id, first_name, last_name, email 
    FROM users 
    WHERE role IN ('coach', 'team_coach', 'admin') 
    ORDER BY last_name, first_name
");
$coaches = $coaches_stmt->fetchAll();
$coaches = decryptUserRows($coaches);

// Get all teams
$teams_stmt = $pdo->query("
    SELECT id, name, division, season
    FROM teams 
    WHERE is_active = 1
    ORDER BY name
");
$teams = $teams_stmt->fetchAll();

// Get all assignments
$assignments_stmt = $pdo->query("
    SELECT 
        tca.*,
        u.first_name, u.last_name, u.email,
        t.name as team_name,
        s.name as season_name, s.is_active
    FROM team_coach_assignments tca
    INNER JOIN users u ON tca.coach_id = u.id
    INNER JOIN teams t ON tca.team_id = t.id
    INNER JOIN seasons s ON tca.season_id = s.id
    ORDER BY s.is_active DESC, s.start_date DESC, u.last_name, t.name
");
$assignments = $assignments_stmt->fetchAll();
$assignments = decryptUserRows($assignments);

// Get team-season associations
$team_seasons_stmt = $pdo->query("
    SELECT 
        ts.*,
        t.name as team_name, t.division,
        s.name as season_name, s.is_active as season_active
    FROM team_seasons ts
    INNER JOIN teams t ON ts.team_id = t.id
    INNER JOIN seasons s ON ts.season_id = s.id
    ORDER BY s.is_active DESC, s.start_date DESC, t.name
");
$team_seasons = $team_seasons_stmt->fetchAll();

// Get all athletes for roster assignment
$athletes_stmt = $pdo->query("
    SELECT id, first_name, last_name, email
    FROM users
    WHERE role = 'athlete'
    ORDER BY last_name, first_name
");
$athletes = $athletes_stmt->fetchAll();
$athletes = decryptUserRows($athletes);

// Get roster entries grouped by team and season
$roster_stmt = $pdo->query("
    SELECT 
        tr.*,
        u.first_name, u.last_name, u.email,
        t.name as team_name,
        s.name as season_name, s.is_active as season_active
    FROM team_roster tr
    INNER JOIN users u ON tr.athlete_id = u.id
    INNER JOIN teams t ON tr.team_id = t.id
    LEFT JOIN seasons s ON tr.season_id = s.id
    ORDER BY s.is_active DESC, s.start_date DESC, t.name, u.last_name
");
$roster_entries = $roster_stmt->fetchAll();
$roster_entries = decryptUserRows($roster_entries);

// Build list of team-season combos that exist (for roster assignment dropdown)
$team_season_combos = [];
foreach ($team_seasons as $ts) {
    $team_season_combos[] = [
        'team_id' => $ts['team_id'],
        'season_id' => $ts['season_id'],
        'label' => $ts['team_name'] . ' — ' . $ts['season_name']
    ];
}
?>

<style>
    :root {
        --primary: #7000a4;
    }
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 24px;
    }
    .page-title {
        font-size: 28px;
        font-weight: 900;
        color: #fff;
    }
    .btn-primary {
        background: var(--primary);
        color: #fff;
        padding: 12px 24px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 700;
        font-size: 14px;
        border: none;
        cursor: pointer;
        transition: all 0.2s;
    }
    .btn-primary:hover {
        background: #e64500;
    }
    .section-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .section-title {
        font-size: 20px;
        font-weight: 700;
        color: #fff;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }
    .form-group {
        display: flex;
        flex-direction: column;
    }
    .form-label {
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        margin-bottom: 8px;
        text-transform: uppercase;
    }
    .form-input, .form-select {
        width: 100%;
        padding: 12px;
        background: #06080b;
        border: 1px solid #1e293b;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
    }
    .form-input:focus, .form-select:focus {
        outline: none;
        border-color: var(--primary);
    }
    .table-container {
        overflow-x: auto;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    .data-table th {
        background: #06080b;
        padding: 12px;
        text-align: left;
        font-size: 12px;
        font-weight: 700;
        color: #94a3b8;
        text-transform: uppercase;
        border-bottom: 2px solid var(--primary);
    }
    .data-table td {
        padding: 16px 12px;
        border-bottom: 1px solid #1e293b;
        color: #fff;
        font-size: 14px;
    }
    .data-table tr:hover {
        background: #06080b;
    }
    .badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-active {
        background: rgba(34, 197, 94, 0.2);
        color: #22c55e;
    }
    .badge-inactive {
        background: rgba(100, 116, 139, 0.2);
        color: #64748b;
    }
    .btn-delete {
        background: #ef4444;
        color: #fff;
        padding: 6px 12px;
        border-radius: 4px;
        border: none;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
    }
    .btn-delete:hover {
        background: #dc2626;
    }
    .empty-state {
        text-align: center;
        padding: 40px;
        color: #64748b;
    }
</style>

<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users-cog"></i> Team Coach Management
    </h1>
</div>

<!-- Seasons Management -->
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Manage Seasons</h2>
    
    <form method="POST" action="process_admin_team_coaches.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create_season">
        
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Season Name</label>
                <input type="text" name="season_name" class="form-input" placeholder="2024-2025" required>
            </div>
            <div class="form-group">
                <label class="form-label">Start Date</label>
                <input type="date" name="start_date" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">End Date</label>
                <input type="date" name="end_date" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Active Season</label>
                <select name="is_active" class="form-select">
                    <option value="0">No</option>
                    <option value="1">Yes</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fas fa-plus"></i> Create Season
        </button>
    </form>
    
    <?php if (!empty($seasons)): ?>
    <div class="table-container" style="margin-top: 24px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Season</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($seasons as $season): ?>
                <tr>
                    <td><?= htmlspecialchars($season['name']) ?></td>
                    <td><?= date('M d, Y', strtotime($season['start_date'])) ?></td>
                    <td><?= date('M d, Y', strtotime($season['end_date'])) ?></td>
                    <td>
                        <span class="badge badge-<?= $season['is_active'] ? 'active' : 'inactive' ?>">
                            <?= $season['is_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!$season['is_active']): ?>
                        <form method="POST" action="process_admin_team_coaches.php" style="display: inline;">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="activate_season">
                            <input type="hidden" name="season_id" value="<?= $season['id'] ?>">
                            <button type="submit" class="btn-primary" style="padding: 6px 12px; font-size: 12px;">
                                Activate
                            </button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" action="process_admin_team_coaches.php" style="display: inline;" 
                              onsubmit="return confirm('Delete this season?');">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="delete_season">
                            <input type="hidden" name="season_id" value="<?= $season['id'] ?>">
                            <button type="submit" class="btn-delete">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Team Coach Assignments -->
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-link"></i> Assign Team Coaches</h2>
    
    <form method="POST" action="process_admin_team_coaches.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="create_assignment">
        
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Team Coach</label>
                <select name="coach_id" class="form-select" required>
                    <option value="">Select Coach</option>
                    <?php foreach ($coaches as $coach): ?>
                        <option value="<?= $coach['id'] ?>">
                            <?= htmlspecialchars($coach['first_name'] . ' ' . $coach['last_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Team</label>
                <select name="team_id" class="form-select" required>
                    <option value="">Select Team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['id'] ?>">
                            <?= htmlspecialchars($team['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Season</label>
                <select name="season_id" class="form-select" required>
                    <option value="">Select Season</option>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= $season['id'] ?>" <?= $season['is_active'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($season['name']) ?>
                            <?= $season['is_active'] ? '(Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fas fa-plus"></i> Create Assignment
        </button>
    </form>
    
    <?php if (!empty($assignments)): ?>
    <div class="table-container" style="margin-top: 24px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Coach</th>
                    <th>Team</th>
                    <th>Season</th>
                    <th>Assigned Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($assignments as $assignment): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($assignment['first_name'] . ' ' . $assignment['last_name']) ?>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                            <?= htmlspecialchars($assignment['email']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($assignment['team_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($assignment['season_name']) ?>
                        <?php if ($assignment['is_active']): ?>
                            <span class="badge badge-active" style="margin-left: 5px;">Active</span>
                        <?php endif; ?>
                    </td>
                    <td><?= date('M d, Y', strtotime($assignment['assigned_at'])) ?></td>
                    <td>
                        <form method="POST" action="process_admin_team_coaches.php" 
                              onsubmit="return confirm('Remove this assignment?');">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="delete_assignment">
                            <input type="hidden" name="assignment_id" value="<?= $assignment['id'] ?>">
                            <button type="submit" class="btn-delete">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-link" style="font-size: 48px; opacity: 0.3;"></i>
            <p>No team coach assignments yet</p>
        </div>
    <?php endif; ?>
</div>

<!-- Team Seasons Management -->
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-layer-group"></i> Team Seasons</h2>
    <p style="color: #94a3b8; margin-bottom: 20px;">Assign seasons to teams. Each team can participate in multiple seasons.</p>
    
    <form method="POST" action="process_admin_team_coaches.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_team_season">
        
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Team</label>
                <select name="team_id" class="form-select" required>
                    <option value="">Select Team</option>
                    <?php foreach ($teams as $team): ?>
                        <option value="<?= $team['id'] ?>">
                            <?= htmlspecialchars($team['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Season</label>
                <select name="season_id" class="form-select" required>
                    <option value="">Select Season</option>
                    <?php foreach ($seasons as $season): ?>
                        <option value="<?= $season['id'] ?>" <?= $season['is_active'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($season['name']) ?>
                            <?= $season['is_active'] ? '(Active)' : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fas fa-plus"></i> Add Season to Team
        </button>
    </form>
    
    <?php if (!empty($team_seasons)): ?>
    <div class="table-container" style="margin-top: 24px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Team</th>
                    <th>Division</th>
                    <th>Season</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($team_seasons as $ts): ?>
                <tr>
                    <td><?= htmlspecialchars($ts['team_name']) ?></td>
                    <td><?= htmlspecialchars($ts['division'] ?? '—') ?></td>
                    <td><?= htmlspecialchars($ts['season_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $ts['season_active'] ? 'active' : 'inactive' ?>">
                            <?= $ts['season_active'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="process_admin_team_coaches.php" style="display: inline;"
                              onsubmit="return confirm('Remove this season from the team? This will also remove all athlete roster entries for this team/season.');">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="remove_team_season">
                            <input type="hidden" name="team_season_id" value="<?= $ts['id'] ?>">
                            <button type="submit" class="btn-delete">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-layer-group" style="font-size: 48px; opacity: 0.3;"></i>
            <p>No team-season associations yet. Add seasons to teams above.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Team Roster (Athletes per Team per Season) -->
<div class="section-card">
    <h2 class="section-title"><i class="fas fa-running"></i> Team Roster — Assign Athletes</h2>
    <p style="color: #94a3b8; margin-bottom: 20px;">Assign athletes to teams for specific seasons. Athletes can be on different teams each season.</p>
    
    <?php if (!empty($team_season_combos)): ?>
    <form method="POST" action="process_admin_team_coaches.php">
        <?= csrfTokenInput() ?>
        <input type="hidden" name="action" value="add_roster_athlete">
        
        <div class="form-grid">
            <div class="form-group">
                <label class="form-label">Team &amp; Season</label>
                <select name="team_season_combo" class="form-select" required id="team_season_combo">
                    <option value="">Select Team &amp; Season</option>
                    <?php foreach ($team_season_combos as $combo): ?>
                        <option value="<?= $combo['team_id'] ?>|<?= $combo['season_id'] ?>">
                            <?= htmlspecialchars($combo['label']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="hidden" name="team_id" id="roster_team_id">
                <input type="hidden" name="season_id" id="roster_season_id">
            </div>
            <div class="form-group">
                <label class="form-label">Athlete</label>
                <select name="athlete_id" class="form-select" required>
                    <option value="">Select Athlete</option>
                    <?php foreach ($athletes as $athlete): ?>
                        <option value="<?= $athlete['id'] ?>">
                            <?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?>
                            (<?= htmlspecialchars($athlete['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Jersey #</label>
                <input type="number" name="jersey_number" class="form-input" placeholder="Optional" min="0" max="99">
            </div>
            <div class="form-group">
                <label class="form-label">Position</label>
                <select name="position" class="form-select">
                    <option value="">Select Position</option>
                    <option value="Forward">Forward</option>
                    <option value="Defense">Defense</option>
                    <option value="Goalie">Goalie</option>
                    <option value="Left Wing">Left Wing</option>
                    <option value="Center">Center</option>
                    <option value="Right Wing">Right Wing</option>
                    <option value="Left Defense">Left Defense</option>
                    <option value="Right Defense">Right Defense</option>
                </select>
            </div>
        </div>
        
        <button type="submit" class="btn-primary">
            <i class="fas fa-user-plus"></i> Add Athlete to Roster
        </button>
    </form>
    <?php else: ?>
        <div class="empty-state" style="margin-bottom: 24px;">
            <i class="fas fa-exclamation-triangle" style="font-size: 32px; opacity: 0.5; color: #f59e0b;"></i>
            <p>You must add seasons to teams first before assigning athletes.</p>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($roster_entries)): ?>
    <div class="table-container" style="margin-top: 24px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Athlete</th>
                    <th>Team</th>
                    <th>Season</th>
                    <th>Jersey #</th>
                    <th>Position</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($roster_entries as $entry): ?>
                <tr>
                    <td>
                        <?= htmlspecialchars($entry['first_name'] . ' ' . $entry['last_name']) ?>
                        <div style="font-size: 12px; color: #64748b; margin-top: 2px;">
                            <?= htmlspecialchars($entry['email']) ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($entry['team_name']) ?></td>
                    <td>
                        <?= htmlspecialchars($entry['season_name'] ?? '—') ?>
                        <?php if (!empty($entry['season_active'])): ?>
                            <span class="badge badge-active" style="margin-left: 5px;">Active</span>
                        <?php endif; ?>
                    </td>
                    <td><?= $entry['jersey_number'] !== null ? htmlspecialchars($entry['jersey_number']) : '—' ?></td>
                    <td><?= htmlspecialchars($entry['position'] ?? '—') ?></td>
                    <td>
                        <form method="POST" action="process_admin_team_coaches.php"
                              onsubmit="return confirm('Remove this athlete from the roster?');">
                            <?= csrfTokenInput() ?>
                            <input type="hidden" name="action" value="remove_roster_athlete">
                            <input type="hidden" name="roster_id" value="<?= $entry['id'] ?>">
                            <button type="submit" class="btn-delete">Remove</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-running" style="font-size: 48px; opacity: 0.3;"></i>
            <p>No athletes assigned to team rosters yet</p>
        </div>
    <?php endif; ?>
</div>

<script>
// Split team_season_combo value into separate hidden fields
document.getElementById('team_season_combo')?.addEventListener('change', function() {
    const parts = this.value.split('|');
    document.getElementById('roster_team_id').value = parts[0] || '';
    document.getElementById('roster_season_id').value = parts[1] || '';
});
</script>
