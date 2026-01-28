<?php
// Validate and sanitize team_id
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Get all available teams for dropdown selection
try {
    $all_teams_query = "SELECT id, name, division, season FROM teams WHERE is_active = 1 ORDER BY name";
    $all_teams_stmt = $pdo->query($all_teams_query);
    $all_teams = $all_teams_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Teams fetch error: " . $e->getMessage());
    $all_teams = [];
}

// If no team_id specified, show team selection page
if ($team_id === 0) {
    $team = null;
    $players = [];
} else {
    // Verify user has permission to view this team (coaches, admins, team members)
    $team_access_query = "
        SELECT 1 FROM teams t
        WHERE t.id = ? 
        AND (
            t.coach_id = ? 
            OR t.assistant_coach_id = ?
            OR ? IN (SELECT athlete_id FROM team_roster WHERE team_id = t.id)
            OR (SELECT role FROM users WHERE id = ?) IN ('admin', 'superadmin', 'coach', 'team_coach')
        )
        LIMIT 1
    ";
    $access_stmt = $pdo->prepare($team_access_query);
    $access_stmt->execute([$team_id, $user_id, $user_id, $user_id, $user_id]);
    $has_access = $access_stmt->fetch();
    
    if (!$has_access && !in_array($user_role, ['admin', 'superadmin', 'coach', 'team_coach'])) {
        $team = null;
        $players = [];
    } else {
        // Get team information
        $team_query = "
            SELECT t.*, 
                   (SELECT COUNT(*) FROM team_roster WHERE team_id = t.id) as player_count
            FROM teams t
            WHERE t.id = ? AND t.is_active = 1
        ";
        $team_stmt = $pdo->prepare($team_query);
        $team_stmt->execute([$team_id]);
        $team = $team_stmt->fetch();
    }
}

// Get filter parameters
$filter_position = $_GET['position'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get current season ID once (with fallback)
$current_season_id = null;
try {
    $current_season_query = "SELECT id FROM seasons WHERE is_current = 1 LIMIT 1";
    $season_stmt = $pdo->prepare($current_season_query);
    $season_stmt->execute();
    $current_season_id = $season_stmt->fetchColumn();
} catch (PDOException $e) {
    // Season table might not exist yet
    $current_season_id = null;
}

// Get team roster only if team was found
$players = [];
if ($team) {
    try {
        // Build base query for roster with season condition in JOIN
        $season_join_condition = $current_season_id ? " AND s.season_id = ?" : "";
        $roster_query = "
            SELECT u.id, u.first_name, u.last_name, u.email,
                   tr.jersey_number, tr.position,
                   COALESCE(s.goals, 0) as goals,
                   COALESCE(s.assists, 0) as assists,
                   COALESCE(s.points, 0) as points,
                   COALESCE(s.save_percentage, 0) as save_percentage,
                   COALESCE(s.gaa, 0) as gaa,
                   COALESCE(s.wins, 0) as wins
            FROM team_roster tr
            INNER JOIN users u ON tr.athlete_id = u.id
            LEFT JOIN athlete_stats s ON s.user_id = u.id" . $season_join_condition . "
        ";
        
        // Build WHERE conditions
        $where_conditions = ["tr.team_id = ?"];
        $params = [$team['id']];
        
        // Add season_id to params if needed (for the JOIN condition)
        if ($current_season_id) {
            array_splice($params, 1, 0, [$current_season_id]); // Insert after team_id
        }
        
        // Apply position filter
        if ($filter_position !== 'all') {
            $where_conditions[] = "tr.position = ?";
            $params[] = $filter_position;
        }
        
        // Apply search filter
        if (!empty($search)) {
            $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        // Construct final query
        $roster_query .= " WHERE " . implode(" AND ", $where_conditions);
        $roster_query .= " ORDER BY tr.jersey_number";

        $roster_stmt = $pdo->prepare($roster_query);
        $roster_stmt->execute($params);
        $players = $roster_stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Team roster fetch error: " . $e->getMessage());
        $players = [];
    }
}
?>

<!-- Team Roster View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i> Team Roster
    </h1>
    <p class="page-description">View and manage team members</p>
</div>

<div class="roster-content">
    <!-- Team Selection Section -->
    <?php if (!$team): ?>
    <div class="team-selection-section">
        <div class="selection-header">
            <h2><i class="fas fa-hockey-puck"></i> Select a Team</h2>
            <p>Choose a team to view its roster</p>
        </div>
        
        <?php if (count($all_teams) > 0): ?>
        <div class="teams-grid">
            <?php foreach ($all_teams as $t): ?>
            <a href="?page=team_roster&team_id=<?= $t['id'] ?>" class="team-select-card">
                <div class="team-card-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="team-card-content">
                    <h3><?= htmlspecialchars($t['name']) ?></h3>
                    <div class="team-card-meta">
                        <?php if (!empty($t['division'])): ?>
                            <span class="team-division"><?= htmlspecialchars($t['division']) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($t['season'])): ?>
                            <span class="team-season"><?= htmlspecialchars($t['season']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="team-card-arrow">
                    <i class="fas fa-chevron-right"></i>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="placeholder-container">
            <i class="fas fa-hockey-puck placeholder-icon"></i>
            <h3>No Teams Available</h3>
            <p class="placeholder-text">No active teams have been created yet. Contact an administrator to set up teams.</p>
        </div>
        <?php endif; ?>
    </div>
    
    <?php else: ?>
    <!-- Team Info Card -->
    <div class="team-info-card" data-component="TeamCard">
        <div class="team-details">
            <div class="team-name-section">
                <a href="?page=team_roster" class="back-link" title="Back to team selection">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <h3><?= htmlspecialchars($team['name']) ?></h3>
            </div>
            <div class="team-stats">
                <span><i class="fas fa-users"></i> <?= $team['player_count'] ?> Players</span>
                <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($team['season'] ?? date('Y') . '-' . (date('Y') + 1) . ' Season') ?></span>
                <?php if (!empty($team['division'])): ?>
                    <span><i class="fas fa-trophy"></i> <?= htmlspecialchars($team['division']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <button class="btn-primary" data-action="add-player" data-team-id="<?= $team['id'] ?>"><i class="fas fa-user-plus"></i> Add Player</button>
    </div>

    <!-- Filter and Search -->
    <div class="filter-bar">
        <form method="GET" action="" class="filter-group">
            <input type="hidden" name="page" value="team_roster">
            <input type="hidden" name="team_id" value="<?= $team['id'] ?>">
            <select name="position" class="form-input-small" onchange="this.form.submit()">
                <option value="all">All Positions</option>
                <option value="Forward" <?= $filter_position === 'Forward' ? 'selected' : '' ?>>Forward</option>
                <option value="Defense" <?= $filter_position === 'Defense' ? 'selected' : '' ?>>Defense</option>
                <option value="Goalie" <?= $filter_position === 'Goalie' ? 'selected' : '' ?>>Goalie</option>
            </select>
            <input type="text" name="search" class="form-input-small" placeholder="Search players..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn-secondary btn-sm"><i class="fas fa-search"></i></button>
        </form>
        <div class="view-toggle">
            <button class="view-btn active" data-view="grid"><i class="fas fa-th"></i></button>
            <button class="view-btn" data-view="list"><i class="fas fa-list"></i></button>
        </div>
    </div>

    <!-- Roster Grid -->
    <?php if (count($players) > 0): ?>
    <div class="roster-grid" data-component="PlayerGrid">
        <?php foreach ($players as $player): ?>
        <div class="player-card" data-component="PlayerCard" data-player-id="<?= $player['id'] ?>">
            <div class="player-number"><?= htmlspecialchars($player['jersey_number'] ?? '#') ?></div>
            <div class="player-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4 class="player-name"><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></h4>
            <div class="player-position"><?= htmlspecialchars($player['position'] ?? 'Player') ?></div>
            
            <?php if (($player['position'] ?? '') === 'Goalie'): ?>
                <div class="player-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= number_format($player['save_percentage'], 3) ?></span>
                        <span class="stat-label">Save %</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= number_format($player['gaa'], 2) ?></span>
                        <span class="stat-label">GAA</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $player['wins'] ?></span>
                        <span class="stat-label">Wins</span>
                    </div>
                </div>
            <?php else: ?>
                <div class="player-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?= $player['goals'] ?></span>
                        <span class="stat-label">Goals</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $player['assists'] ?></span>
                        <span class="stat-label">Assists</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?= $player['points'] ?></span>
                        <span class="stat-label">Points</span>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="player-actions">
                <button class="btn-secondary btn-small" data-action="view-profile" data-player-id="<?= $player['id'] ?>"><i class="fas fa-eye"></i> View Profile</button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="placeholder-container">
        <i class="fas fa-users placeholder-icon"></i>
        <p class="placeholder-text">No players found. Add players to your team.</p>
    </div>
    <?php endif; ?>
    
    <?php endif; ?>
</div>

<style>
.team-info-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 24px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.team-details h3 {
    font-size: 28px;
    font-weight: 900;
    color: var(--text-white);
    margin-bottom: 12px;
}

.team-stats {
    display: flex;
    gap: 25px;
    flex-wrap: wrap;
}

.team-stats span {
    font-size: 14px;
    color: var(--text-dim);
}

.team-stats i {
    color: var(--neon);
    margin-right: 5px;
}

.view-toggle {
    display: flex;
    gap: 5px;
}

.view-btn {
    width: 40px;
    height: 40px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    color: var(--text-dim);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.3s;
}

.view-btn:hover,
.view-btn.active {
    background: var(--neon);
    border-color: var(--neon);
    color: #fff;
}

.roster-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}

.player-card {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    padding: 24px;
    text-align: center;
    position: relative;
    transition: all 0.3s;
}

.player-card:hover {
    border-color: var(--neon);
    transform: translateY(-5px);
    box-shadow: 0 10px 30px rgba(255, 77, 0, 0.2);
}

.player-number {
    position: absolute;
    top: 15px;
    right: 15px;
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, var(--neon), var(--accent));
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: 900;
    color: #fff;
}

.player-avatar {
    width: 80px;
    height: 80px;
    background: var(--bg-main);
    border: 3px solid var(--border);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 15px;
    font-size: 32px;
    color: var(--text-dim);
}

.player-name {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 5px;
}

.player-position {
    font-size: 12px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid var(--border);
}

.player-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.stat-item {
    text-align: center;
}

.stat-value {
    display: block;
    font-size: 20px;
    font-weight: 900;
    color: var(--neon);
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    display: block;
    font-size: 11px;
    color: var(--text-dim);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.player-actions {
    padding-top: 15px;
    border-top: 1px solid var(--border);
}

/* Team Selection Styles */
.team-selection-section {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 40px;
}

.selection-header {
    text-align: center;
    margin-bottom: 32px;
}

.selection-header h2 {
    font-size: 24px;
    font-weight: 800;
    color: var(--text-white);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.selection-header h2 i {
    color: var(--primary);
}

.selection-header p {
    color: var(--text-dim);
    font-size: 14px;
}

.teams-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}

.team-select-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: var(--bg-main);
    border: 1px solid var(--border);
    border-radius: 10px;
    text-decoration: none;
    transition: all 0.3s ease;
}

.team-select-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(107, 70, 193, 0.15);
}

.team-card-icon {
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, var(--primary), var(--neon));
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.team-card-icon i {
    font-size: 24px;
    color: #fff;
}

.team-card-content {
    flex: 1;
}

.team-card-content h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 6px;
}

.team-card-meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.team-division,
.team-season {
    font-size: 12px;
    color: var(--text-dim);
    padding: 2px 8px;
    background: var(--bg-card);
    border-radius: 4px;
}

.team-card-arrow {
    color: var(--text-dim);
    transition: all 0.3s ease;
}

.team-select-card:hover .team-card-arrow {
    color: var(--primary);
    transform: translateX(4px);
}

.team-name-section {
    display: flex;
    align-items: center;
    gap: 12px;
}

.back-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 8px;
    color: var(--text-dim);
    text-decoration: none;
    transition: all 0.3s ease;
}

.back-link:hover {
    background: var(--primary);
    border-color: var(--primary);
    color: #fff;
}

/* Placeholder Styles */
.placeholder-container {
    text-align: center;
    padding: 60px 20px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
}

.placeholder-container h3 {
    font-size: 20px;
    font-weight: 700;
    color: var(--text-white);
    margin-bottom: 8px;
}

.placeholder-icon {
    font-size: 64px;
    color: var(--primary);
    opacity: 0.4;
    margin-bottom: 20px;
    display: block;
}

.placeholder-text {
    font-size: 14px;
    color: var(--text-dim);
    line-height: 1.6;
}

/* Filter bar styling */
.filter-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 16px;
}

.filter-group {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    align-items: center;
}

.form-input-small {
    padding: 10px 14px;
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 6px;
    color: var(--text-white);
    font-size: 14px;
    min-width: 150px;
}

.form-input-small:focus {
    outline: none;
    border-color: var(--primary);
}

.btn-sm {
    padding: 10px 16px;
    font-size: 13px;
}
</style>
