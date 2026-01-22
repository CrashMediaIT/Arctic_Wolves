<?php
// Validate and sanitize team_id
$team_id = isset($_GET['team_id']) ? intval($_GET['team_id']) : 0;

// Verify user has permission to view this team (coaches, admins, team members)
$team_access_query = "
    SELECT 1 FROM teams t
    WHERE t.id = ? 
    AND (
        t.coach_id = ? 
        OR t.assistant_coach_id = ?
        OR ? IN (SELECT user_id FROM team_members WHERE team_id = t.id)
        OR (SELECT role FROM users WHERE id = ?) IN ('admin', 'superadmin')
    )
    LIMIT 1
";
$access_stmt = $pdo->prepare($team_access_query);
$access_stmt->execute([$team_id, $user_id, $user_id, $user_id, $user_id]);
if (!$access_stmt->fetch()) {
    die("Access denied: You do not have permission to view this team.");
}

// Get team information
$team_query = "
    SELECT t.*, 
           (SELECT COUNT(*) FROM team_members WHERE team_id = t.id AND is_active = 1) as player_count
    FROM teams t
    WHERE t.id = ? AND t.is_active = 1
";
$team_stmt = $pdo->prepare($team_query);
$team_stmt->execute([$team_id]);
$team = $team_stmt->fetch();

// Get filter parameters
$filter_position = $_GET['position'] ?? 'all';
$search = $_GET['search'] ?? '';

// Get current season ID once
$current_season_query = "SELECT id FROM seasons WHERE is_current = 1 LIMIT 1";
$season_stmt = $pdo->prepare($current_season_query);
$season_stmt->execute();
$current_season_id = $season_stmt->fetchColumn();

// Get team roster
$roster_query = "
    SELECT u.id, u.first_name, u.last_name, u.email,
           tm.jersey_number, tm.position,
           COALESCE(s.goals, 0) as goals,
           COALESCE(s.assists, 0) as assists,
           COALESCE(s.points, 0) as points,
           COALESCE(s.save_percentage, 0) as save_percentage,
           COALESCE(s.gaa, 0) as gaa,
           COALESCE(s.wins, 0) as wins
    FROM team_members tm
    INNER JOIN users u ON tm.user_id = u.id
    LEFT JOIN player_stats s ON s.player_id = u.id AND s.season_id = ?
    WHERE tm.team_id = ? AND tm.is_active = 1
";

$params = [$current_season_id, $team['id'] ?? 0];

if ($filter_position !== 'all') {
    $roster_query .= " AND tm.position = ?";
    $params[] = $filter_position;
}

if (!empty($search)) {
    $roster_query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$roster_query .= " ORDER BY tm.jersey_number";

$roster_stmt = $pdo->prepare($roster_query);
$roster_stmt->execute($params);
$players = $roster_stmt->fetchAll();
?>

<!-- Team Roster View -->
<div class="page-header">
    <h1 class="page-title">
        <i class="fas fa-users"></i> Team Roster
    </h1>
    <p class="page-description">Manage your team members</p>
</div>

<div class="roster-content">
    <?php if ($team): ?>
    <!-- Team Info Card -->
    <div class="team-info-card" data-component="TeamCard">
        <div class="team-details">
            <h3><?= htmlspecialchars($team['name']) ?></h3>
            <div class="team-stats">
                <span><i class="fas fa-users"></i> <?= $team['player_count'] ?> Players</span>
                <span><i class="fas fa-calendar"></i> <?= htmlspecialchars($team['season'] ?? '2023-2024 Season') ?></span>
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
            <select name="position" class="form-input-small" data-action="auto-submit">
                <option value="all">All Positions</option>
                <option value="Forward" <?= $filter_position === 'Forward' ? 'selected' : '' ?>>Forward</option>
                <option value="Defense" <?= $filter_position === 'Defense' ? 'selected' : '' ?>>Defense</option>
                <option value="Goalie" <?= $filter_position === 'Goalie' ? 'selected' : '' ?>>Goalie</option>
            </select>
            <input type="text" name="search" class="form-input-small" placeholder="Search players..." value="<?= htmlspecialchars($search) ?>" data-action="search-debounce">
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
            <div class="player-number"><?= $player['jersey_number'] ?></div>
            <div class="player-avatar">
                <i class="fas fa-user"></i>
            </div>
            <h4 class="player-name"><?= htmlspecialchars($player['first_name'] . ' ' . $player['last_name']) ?></h4>
            <div class="player-position"><?= htmlspecialchars($player['position']) ?></div>
            
            <?php if ($player['position'] === 'Goalie'): ?>
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
    
    <?php else: ?>
    <div class="placeholder-container">
        <i class="fas fa-exclamation-triangle placeholder-icon"></i>
        <p class="placeholder-text">Team not found or inactive.</p>
    </div>
    <?php endif; ?>
</div>

<style>
.team-info-card {
    background: linear-gradient(135deg, rgba(255, 77, 0, 0.1), rgba(255, 157, 0, 0.1));
    border: 1px solid var(--neon);
    border-radius: 8px;
    padding: 30px;
    margin-bottom: 30px;
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
    padding: 25px;
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
</style>
