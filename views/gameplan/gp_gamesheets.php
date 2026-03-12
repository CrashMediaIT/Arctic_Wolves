<?php
/**
 * Game Plan - Gamesheets Archive
 * Browse and search all completed game scoresheets.
 * Supports filtering by time period, team name, age group, player, and season.
 * Data comes from the scoreboard module (scoreboard_games, scoreboard_goals, scoreboard_penalties).
 */

// ── Filter Parameters ─────────────────────────────────────────
$gs_team      = isset($_GET['team']) ? trim($_GET['team']) : '';
$gs_age_group = isset($_GET['age_group']) ? trim($_GET['age_group']) : '';
$gs_player    = isset($_GET['player']) ? trim($_GET['player']) : '';
$gs_season_id = isset($_GET['season_id']) ? (int)$_GET['season_id'] : 0;
$gs_date_from = isset($_GET['date_from']) ? trim($_GET['date_from']) : '';
$gs_date_to   = isset($_GET['date_to']) ? trim($_GET['date_to']) : '';
$gs_search    = isset($_GET['q']) ? trim($_GET['q']) : '';

// Validate date formats (YYYY-MM-DD)
if ($gs_date_from && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gs_date_from)) $gs_date_from = '';
if ($gs_date_to && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $gs_date_to)) $gs_date_to = '';

// ── Load filter options ───────────────────────────────────────
$gs_seasons = [];
try {
    $stmt = $pdo->query("SELECT id, name, is_active FROM seasons ORDER BY start_date DESC");
    $gs_seasons = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist */ }

$gs_teams = [];
try {
    $stmt = $pdo->query("SELECT id, name, age_group FROM teams WHERE is_active = 1 ORDER BY name");
    $gs_teams = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { /* table may not exist */ }

$gs_age_groups = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT age_group FROM teams WHERE age_group IS NOT NULL AND age_group != '' ORDER BY age_group");
    $gs_age_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { /* ignore */ }

// ── Build query for completed games ───────────────────────────
$gs_where = ["sg.status = 'final'"];
$gs_params = [];

// Team name filter (matches home or away)
if ($gs_team !== '') {
    $gs_where[] = "(sg.home_team_name LIKE ? OR sg.away_team_name LIKE ?)";
    $gs_params[] = '%' . $gs_team . '%';
    $gs_params[] = '%' . $gs_team . '%';
}

// Age group filter (via team_id joins)
if ($gs_age_group !== '') {
    $gs_where[] = "(ht.age_group = ? OR at.age_group = ?)";
    $gs_params[] = $gs_age_group;
    $gs_params[] = $gs_age_group;
}

// Season filter
if ($gs_season_id > 0) {
    $gs_where[] = "gs_sched.season_id = ?";
    $gs_params[] = $gs_season_id;
}

// Time period filter
if ($gs_date_from !== '') {
    $gs_where[] = "DATE(sg.created_at) >= ?";
    $gs_params[] = $gs_date_from;
}
if ($gs_date_to !== '') {
    $gs_where[] = "DATE(sg.created_at) <= ?";
    $gs_params[] = $gs_date_to;
}

// Player filter (search across goals and penalties)
if ($gs_player !== '') {
    $gs_where[] = "(
        EXISTS (SELECT 1 FROM scoreboard_goals sgl WHERE sgl.game_id = sg.id AND (sgl.scorer_name LIKE ? OR sgl.assist1_name LIKE ? OR sgl.assist2_name LIKE ?))
        OR EXISTS (SELECT 1 FROM scoreboard_penalties spl WHERE spl.game_id = sg.id AND spl.player_name LIKE ?)
    )";
    $gs_params[] = '%' . $gs_player . '%';
    $gs_params[] = '%' . $gs_player . '%';
    $gs_params[] = '%' . $gs_player . '%';
    $gs_params[] = '%' . $gs_player . '%';
}

// Free text search (team names, period)
if ($gs_search !== '') {
    $gs_where[] = "(sg.home_team_name LIKE ? OR sg.away_team_name LIKE ?)";
    $gs_params[] = '%' . $gs_search . '%';
    $gs_params[] = '%' . $gs_search . '%';
}

$gs_where_sql = implode(' AND ', $gs_where);

// Pagination
$gs_page = max(1, (int)($_GET['pg'] ?? 1));
$gs_per_page = 20;
$gs_offset = ($gs_page - 1) * $gs_per_page;

// Count total
$gs_total = 0;
$gs_games = [];
try {
    $count_sql = "
        SELECT COUNT(DISTINCT sg.id)
        FROM scoreboard_games sg
        LEFT JOIN teams ht ON sg.home_team_id = ht.id
        LEFT JOIN teams at ON sg.away_team_id = at.id
        LEFT JOIN game_schedules gs_sched ON (gs_sched.team_id = COALESCE(sg.home_team_id, sg.away_team_id)
            AND DATE(gs_sched.game_date) = DATE(sg.created_at))
        WHERE $gs_where_sql
    ";
    $stmt = $pdo->prepare($count_sql);
    $stmt->execute($gs_params);
    $gs_total = (int)$stmt->fetchColumn();

    // Fetch games
    $games_sql = "
        SELECT sg.*,
               ht.age_group AS home_age_group,
               at.age_group AS away_age_group,
               ht.name AS home_team_display,
               at.name AS away_team_display,
               s.name AS season_name
        FROM scoreboard_games sg
        LEFT JOIN teams ht ON sg.home_team_id = ht.id
        LEFT JOIN teams at ON sg.away_team_id = at.id
        LEFT JOIN game_schedules gs_sched ON (gs_sched.team_id = COALESCE(sg.home_team_id, sg.away_team_id)
            AND DATE(gs_sched.game_date) = DATE(sg.created_at))
        LEFT JOIN seasons s ON gs_sched.season_id = s.id
        WHERE $gs_where_sql
        GROUP BY sg.id
        ORDER BY sg.created_at DESC
        LIMIT ? OFFSET ?
    ";
    $stmt = $pdo->prepare($games_sql);
    $all_params = array_merge($gs_params, [$gs_per_page, $gs_offset]);
    $stmt->execute($all_params);
    $gs_games = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Gamesheets query: ' . $e->getMessage());
}

$gs_total_pages = max(1, (int)ceil($gs_total / $gs_per_page));

// ── Load goals and penalties for detail view ──────────────────
$gs_game_detail_id = isset($_GET['game_id']) ? (int)$_GET['game_id'] : 0;
$gs_detail_game = null;
$gs_detail_goals = [];
$gs_detail_penalties = [];
if ($gs_game_detail_id > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT sg.*, ht.age_group AS home_age_group, at.age_group AS away_age_group
            FROM scoreboard_games sg
            LEFT JOIN teams ht ON sg.home_team_id = ht.id
            LEFT JOIN teams at ON sg.away_team_id = at.id
            WHERE sg.id = ? AND sg.status = 'final'
        ");
        $stmt->execute([$gs_game_detail_id]);
        $gs_detail_game = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($gs_detail_game) {
            $stmt = $pdo->prepare("SELECT * FROM scoreboard_goals WHERE game_id = ? ORDER BY period ASC, game_time_seconds ASC");
            $stmt->execute([$gs_game_detail_id]);
            $gs_detail_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT * FROM scoreboard_penalties WHERE game_id = ? ORDER BY period ASC, game_time_seconds ASC");
            $stmt->execute([$gs_game_detail_id]);
            $gs_detail_penalties = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) { error_log('Gamesheet detail: ' . $e->getMessage()); }
}
?>

<?php if ($gs_detail_game): ?>
<!-- ═══════════════════════════════════════════════════════
     GAMESHEET DETAIL VIEW
     ═══════════════════════════════════════════════════════ -->
<div style="margin-bottom: 16px;">
    <a href="/gameplan.php?page=gamesheets" class="btn btn-secondary" style="height: 36px; padding: 0 16px; font-size: 13px; text-decoration: none;">
        <i class="fas fa-arrow-left"></i> Back to Gamesheets
    </a>
</div>

<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Gamesheet — <?= htmlspecialchars($gs_detail_game['home_team_name']) ?> vs <?= htmlspecialchars($gs_detail_game['away_team_name']) ?></h3>
    </div>
    <div class="card-body">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Date</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--text-white);"><?= htmlspecialchars(date('M j, Y g:i A', strtotime($gs_detail_game['created_at']))) ?></div>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Final Score</div>
                <div style="font-size: 24px; font-weight: 900; color: var(--text-white);">
                    <?= (int)$gs_detail_game['home_score'] ?> – <?= (int)$gs_detail_game['away_score'] ?>
                </div>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Shots on Goal</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--text-white);">
                    <?= (int)$gs_detail_game['home_shots'] ?> – <?= (int)$gs_detail_game['away_shots'] ?>
                </div>
            </div>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Periods</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--text-white);"><?= htmlspecialchars($gs_detail_game['current_period'] ?? '3') ?></div>
            </div>
            <?php if (!empty($gs_detail_game['home_age_group']) || !empty($gs_detail_game['away_age_group'])): ?>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Age Group</div>
                <div style="font-size: 14px; font-weight: 600; color: var(--text-white);"><?= htmlspecialchars($gs_detail_game['home_age_group'] ?? $gs_detail_game['away_age_group'] ?? '') ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($gs_detail_game['synced_to_gameplan'])): ?>
            <div>
                <div style="font-size: 12px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Status</div>
                <div style="font-size: 13px; color: var(--success);"><i class="fas fa-check-circle"></i> Synced to Game Plan</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Goals Table -->
        <?php if (!empty($gs_detail_goals)): ?>
        <h4 style="font-size: 14px; font-weight: 700; color: var(--text-white); margin: 20px 0 10px;"><i class="fas fa-hockey-puck"></i> Goals</h4>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Period</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Time</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Team</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Scorer</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Assist 1</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Assist 2</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gs_detail_goals as $g): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 8px; color: var(--text-secondary);"><?= htmlspecialchars($g['period'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= htmlspecialchars($g['game_time'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-white); font-weight: 600;"><?= htmlspecialchars($g['team'] === 'home' ? $gs_detail_game['home_team_name'] : $gs_detail_game['away_team_name']) ?></td>
                        <td style="padding: 8px; color: var(--text-white);">#<?= htmlspecialchars($g['scorer_number'] ?? '') ?> <?= htmlspecialchars($g['scorer_name'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= !empty($g['assist1_name']) ? '#' . htmlspecialchars($g['assist1_number'] ?? '') . ' ' . htmlspecialchars($g['assist1_name']) : '—' ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= !empty($g['assist2_name']) ? '#' . htmlspecialchars($g['assist2_number'] ?? '') . ' ' . htmlspecialchars($g['assist2_name']) : '—' ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= htmlspecialchars($g['goal_type'] ?? 'Even Strength') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: var(--text-muted); font-size: 13px; padding: 12px 0;">No goals recorded for this game.</p>
        <?php endif; ?>

        <!-- Penalties Table -->
        <?php if (!empty($gs_detail_penalties)): ?>
        <h4 style="font-size: 14px; font-weight: 700; color: var(--text-white); margin: 20px 0 10px;"><i class="fas fa-gavel"></i> Penalties</h4>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Period</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Time</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Team</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Player</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Infraction</th>
                        <th style="text-align: left; padding: 8px; color: var(--text-muted); font-weight: 600;">Minutes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gs_detail_penalties as $p): ?>
                    <tr style="border-bottom: 1px solid var(--border);">
                        <td style="padding: 8px; color: var(--text-secondary);"><?= htmlspecialchars($p['period'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= htmlspecialchars($p['game_time'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-white); font-weight: 600;"><?= htmlspecialchars($p['team'] === 'home' ? $gs_detail_game['home_team_name'] : $gs_detail_game['away_team_name']) ?></td>
                        <td style="padding: 8px; color: var(--text-white);">#<?= htmlspecialchars($p['player_number'] ?? '') ?> <?= htmlspecialchars($p['player_name'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= htmlspecialchars($p['infraction'] ?? '') ?></td>
                        <td style="padding: 8px; color: var(--text-secondary);"><?= (int)($p['duration_minutes'] ?? 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p style="color: var(--text-muted); font-size: 13px; padding: 12px 0;">No penalties recorded for this game.</p>
        <?php endif; ?>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════
     GAMESHEETS LIST VIEW
     ═══════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom: 16px;">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Gamesheets</h3>
        <span style="font-size: 13px; color: var(--text-muted);"><?= $gs_total ?> completed game<?= $gs_total !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body">
        <!-- Search & Filter Bar -->
        <form method="get" action="/gameplan.php" style="margin-bottom: 20px;">
            <input type="hidden" name="page" value="gamesheets">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; margin-bottom: 12px;">
                <!-- Free text search -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Search</label>
                    <input type="text" name="q" value="<?= htmlspecialchars($gs_search) ?>" placeholder="Team name…"
                           style="width: 100%; height: 38px; padding: 0 12px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                </div>
                <!-- Team filter -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Team</label>
                    <select name="team" style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                        <option value="">All Teams</option>
                        <?php foreach ($gs_teams as $t): ?>
                        <option value="<?= htmlspecialchars($t['name']) ?>" <?= $gs_team === $t['name'] ? 'selected' : '' ?>><?= htmlspecialchars($t['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Age Group filter -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Age Group</label>
                    <select name="age_group" style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                        <option value="">All Age Groups</option>
                        <?php foreach ($gs_age_groups as $ag): ?>
                        <option value="<?= htmlspecialchars($ag) ?>" <?= $gs_age_group === $ag ? 'selected' : '' ?>><?= htmlspecialchars($ag) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Season filter -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Season</label>
                    <select name="season_id" style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                        <option value="0">All Seasons</option>
                        <?php foreach ($gs_seasons as $s): ?>
                        <option value="<?= (int)$s['id'] ?>" <?= $gs_season_id === (int)$s['id'] ? 'selected' : '' ?>><?= htmlspecialchars($s['name']) ?><?= !empty($s['is_active']) ? ' (Current)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <!-- Player filter -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">Player</label>
                    <input type="text" name="player" value="<?= htmlspecialchars($gs_player) ?>" placeholder="Player name…"
                           style="width: 100%; height: 38px; padding: 0 12px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                </div>
                <!-- Date From -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">From Date</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($gs_date_from) ?>"
                           style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                </div>
                <!-- Date To -->
                <div>
                    <label style="display: block; font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px;">To Date</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($gs_date_to) ?>"
                           style="width: 100%; height: 38px; padding: 0 10px; font-size: 13px; background: var(--bg-main); border: 1px solid var(--border); border-radius: 8px; color: var(--text-white);">
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="height: 36px; padding: 0 20px; font-size: 13px;">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="/gameplan.php?page=gamesheets" class="btn btn-secondary" style="height: 36px; padding: 0 16px; font-size: 13px; text-decoration: none;">
                    <i class="fas fa-times"></i> Clear
                </a>
            </div>
        </form>

        <?php if (empty($gs_games)): ?>
        <!-- Empty State -->
        <div style="text-align: center; padding: 40px 20px;">
            <i class="fas fa-file-alt" style="font-size: 48px; color: var(--text-muted); display: block; margin-bottom: 16px;"></i>
            <h3 style="color: var(--text-secondary); margin-bottom: 8px;">No Gamesheets Found</h3>
            <p style="color: var(--text-muted); font-size: 13px;">
                <?php if ($gs_search || $gs_team || $gs_age_group || $gs_player || $gs_season_id || $gs_date_from || $gs_date_to): ?>
                No completed games match your filters. Try adjusting your search criteria.
                <?php else: ?>
                Completed games from the scoreboard will appear here automatically.
                <?php endif; ?>
            </p>
        </div>
        <?php else: ?>
        <!-- Games Table -->
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--border);">
                        <th style="text-align: left; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Date</th>
                        <th style="text-align: left; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Home</th>
                        <th style="text-align: center; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Score</th>
                        <th style="text-align: left; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Away</th>
                        <th style="text-align: center; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Shots</th>
                        <th style="text-align: left; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;">Season</th>
                        <th style="text-align: center; padding: 10px 8px; color: var(--text-muted); font-weight: 700; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gs_games as $game): ?>
                    <tr style="border-bottom: 1px solid var(--border); transition: background 0.1s;" onmouseover="this.style.background='rgba(107,70,193,0.05)'" onmouseout="this.style.background=''">
                        <td style="padding: 10px 8px; color: var(--text-secondary); white-space: nowrap;">
                            <?= htmlspecialchars(date('M j, Y', strtotime($game['created_at']))) ?>
                        </td>
                        <td style="padding: 10px 8px; color: var(--text-white); font-weight: 600;">
                            <?= htmlspecialchars($game['home_team_name']) ?>
                            <?php if (!empty($game['home_age_group'])): ?>
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 400;">(<?= htmlspecialchars($game['home_age_group']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 8px; text-align: center; font-weight: 900; font-size: 15px; color: var(--text-white);">
                            <?= (int)$game['home_score'] ?> – <?= (int)$game['away_score'] ?>
                        </td>
                        <td style="padding: 10px 8px; color: var(--text-white); font-weight: 600;">
                            <?= htmlspecialchars($game['away_team_name']) ?>
                            <?php if (!empty($game['away_age_group'])): ?>
                            <span style="font-size: 11px; color: var(--text-muted); font-weight: 400;">(<?= htmlspecialchars($game['away_age_group']) ?>)</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 10px 8px; text-align: center; color: var(--text-secondary);">
                            <?= (int)$game['home_shots'] ?> – <?= (int)$game['away_shots'] ?>
                        </td>
                        <td style="padding: 10px 8px; color: var(--text-secondary);">
                            <?= htmlspecialchars($game['season_name'] ?? '—') ?>
                        </td>
                        <td style="padding: 10px 8px; text-align: center;">
                            <a href="/gameplan.php?page=gamesheets&game_id=<?= (int)$game['id'] ?>" style="color: var(--primary-light); text-decoration: none; font-size: 12px; font-weight: 600;">
                                <i class="fas fa-eye"></i> View
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($gs_total_pages > 1): ?>
        <!-- Pagination -->
        <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 20px;">
            <?php
            // Build base URL preserving filters
            $pg_params = $_GET;
            unset($pg_params['pg']);
            $pg_base = '/gameplan.php?' . http_build_query($pg_params);
            ?>
            <?php if ($gs_page > 1): ?>
            <a href="<?= htmlspecialchars($pg_base . '&pg=' . ($gs_page - 1)) ?>" style="padding: 6px 14px; border: 1px solid var(--border); border-radius: 6px; color: var(--text-secondary); text-decoration: none; font-size: 13px;">← Prev</a>
            <?php endif; ?>
            <span style="font-size: 13px; color: var(--text-muted);">Page <?= $gs_page ?> of <?= $gs_total_pages ?></span>
            <?php if ($gs_page < $gs_total_pages): ?>
            <a href="<?= htmlspecialchars($pg_base . '&pg=' . ($gs_page + 1)) ?>" style="padding: 6px 14px; border: 1px solid var(--border); border-radius: 6px; color: var(--text-secondary); text-decoration: none; font-size: 13px;">Next →</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
