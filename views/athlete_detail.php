<?php
/**
 * Athlete Detail View
 * Detailed athlete profile with stats, evaluations, and management options
 */

require_once __DIR__ . '/../security.php';
require_once __DIR__ . '/../lib/image_helper.php';

$athlete_id = isset($_GET['id']) ? intval($_GET['id']) : $user_id;

// Get athlete details
$athlete_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('athlete', 'coach', 'coach_plus')");
$athlete_stmt->execute([$athlete_id]);
$athlete = $athlete_stmt->fetch();
$athlete = $athlete ? decryptUserRow($athlete) : $athlete;

if (!$athlete) {
    echo "<div class='alert alert-error'>Athlete not found.</div>";
    exit;
}

// Check permissions
$can_view = false;
if ($isAdmin || $isCoach) {
    $can_view = true;
} elseif ($user_id == $athlete_id) {
    $can_view = true;
} elseif ($isParent) {
    $check = $pdo->prepare("SELECT id FROM managed_athletes WHERE parent_id = ? AND athlete_id = ?");
    $check->execute([$user_id, $athlete_id]);
    $can_view = ($check->rowCount() > 0);
}

if (!$can_view) {
    echo "<div class='alert alert-error'>You do not have permission to view this athlete.</div>";
    exit;
}

// Get athlete stats
$stats_stmt = $pdo->prepare("SELECT * FROM athlete_stats WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$stats_stmt->execute([$athlete_id]);
$stats = $stats_stmt->fetchAll();

// Get recent evaluations
$eval_stmt = $pdo->prepare("
    SELECT ae.*, u.first_name, u.last_name 
    FROM athlete_evaluations ae 
    LEFT JOIN users u ON ae.evaluator_id = u.id
    WHERE ae.athlete_id = ? 
    ORDER BY ae.evaluation_date DESC 
    LIMIT 5
");
$eval_stmt->execute([$athlete_id]);
$evaluations = $eval_stmt->fetchAll();
$evaluations = decryptUserRows($evaluations);

// Get assigned teams (combine athlete_teams and team_roster for complete view)
$teams_stmt = $pdo->prepare("
    SELECT at.team_name, at.season, at.position, at.jersey_number, at.is_current, at.status, at.team_id
    FROM athlete_teams at 
    WHERE at.athlete_id = ? OR at.user_id = ?
    UNION
    SELECT t.name as team_name, s.name as season, tr.position, tr.jersey_number, 1 as is_current, 'active' as status, tr.team_id
    FROM team_roster tr
    INNER JOIN teams t ON tr.team_id = t.id
    LEFT JOIN seasons s ON tr.season_id = s.id
    WHERE tr.athlete_id = ?
    ORDER BY is_current DESC, season DESC
");
$teams_stmt->execute([$athlete_id, $athlete_id, $athlete_id]);
$teams = $teams_stmt->fetchAll();
?>

<style>
    :root {
        --primary: #7000a4;
        --neon: #7000a4;
    }
    .athlete-header {
        background: linear-gradient(135deg, var(--primary) 0%, #4a0070 100%);
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
        color: #fff;
    }
    .athlete-header h1 {
        margin: 0 0 10px 0;
        font-size: 28px;
        font-weight: 900;
    }
    .detail-card {
        background: #0d1117;
        border: 1px solid #1e293b;
        border-radius: 8px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .stat-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    .stat-item {
        background: #161b22;
        padding: 16px;
        border-radius: 6px;
        text-align: center;
    }
    .stat-value {
        font-size: 24px;
        font-weight: 900;
        color: var(--neon);
        display: block;
    }
    .stat-label {
        font-size: 12px;
        color: #8b949e;
        text-transform: uppercase;
        font-weight: 600;
    }
</style>

<div class="athlete-header">
    <h1><?= htmlspecialchars($athlete['first_name'] . ' ' . $athlete['last_name']) ?></h1>
    <p><?= ucfirst($athlete['role']) ?> • ID: <?= $athlete['id'] ?></p>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
    <div class="alert alert-success">Action completed successfully!</div>
<?php endif; ?>

<div class="detail-card">
    <h2>Profile Information</h2>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 12px;">
        <div>
            <strong>Email:</strong> <?= htmlspecialchars($athlete['email']) ?>
        </div>
        <div>
            <strong>Position:</strong> <?= htmlspecialchars($athlete['position'] ?? 'N/A') ?>
        </div>
        <div>
            <strong>Birth Date:</strong> <?= $athlete['birth_date'] ? date('M d, Y', strtotime($athlete['birth_date'])) : 'N/A' ?>
        </div>
        <div>
            <strong>Shooting Hand:</strong> <?= ucfirst($athlete['shooting_hand'] ?? 'N/A') ?>
        </div>
    </div>
</div>

<div class="detail-card">
    <h2>Statistics</h2>
    <?php if (count($stats) > 0): ?>
        <div class="stat-grid">
            <?php foreach ($stats as $stat): ?>
                <div class="stat-item">
                    <span class="stat-value"><?= $stat['stat_value'] ?></span>
                    <span class="stat-label"><?= htmlspecialchars($stat['stat_name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p style="color: #8b949e; margin-top: 12px;">No statistics recorded yet.</p>
    <?php endif; ?>
</div>

<div class="detail-card">
    <h2>Recent Evaluations</h2>
    <?php if (count($evaluations) > 0): ?>
        <table style="width: 100%; margin-top: 12px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Evaluator</th>
                    <th>Overall Rating</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($evaluations as $eval): ?>
                    <tr>
                        <td><?= date('M d, Y', strtotime($eval['evaluation_date'])) ?></td>
                        <td><?= htmlspecialchars($eval['first_name'] . ' ' . $eval['last_name']) ?></td>
                        <td><?= $eval['overall_rating'] ?>/10</td>
                        <td>
                            <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="btn-sm">View Details</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #8b949e; margin-top: 12px;">No evaluations recorded yet.</p>
    <?php endif; ?>
</div>

<div class="detail-card">
    <h2>Team Assignments</h2>
    <?php if (count($teams) > 0): ?>
        <?php
        // Pre-fetch all team logos to avoid N+1 queries
        $team_ids = array_filter(array_unique(array_column($teams, 'team_id')));
        $team_logos = [];
        if (!empty($team_ids)) {
            $placeholders = implode(',', array_fill(0, count($team_ids), '?'));
            $logo_stmt = $pdo->prepare("SELECT id, logo_url FROM teams WHERE id IN ($placeholders)");
            $logo_stmt->execute(array_values($team_ids));
            foreach ($logo_stmt->fetchAll() as $lr) {
                $team_logos[$lr['id']] = $lr['logo_url'];
            }
        }
        ?>
        <table style="width: 100%; margin-top: 12px;">
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Season</th>
                    <th>Position</th>
                    <th>Jersey #</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $displayed_teams = [];
                foreach ($teams as $team): 
                    // Deduplicate by team_name + season
                    $key = ($team['team_name'] ?? '') . '|' . ($team['season'] ?? '');
                    if (isset($displayed_teams[$key])) continue;
                    $displayed_teams[$key] = true;
                ?>
                    <tr>
                        <td>
                            <?php if (!empty($team['team_id']) && !empty($team_logos[$team['team_id']])): ?>
                                <img src="<?= htmlspecialchars(resolveRustfsUrl($pdo, $team_logos[$team['team_id']])) ?>" alt="" style="width: 24px; height: 24px; border-radius: 4px; object-fit: contain; vertical-align: middle; margin-right: 8px;">
                            <?php endif; ?>
                            <?= htmlspecialchars($team['team_name']) ?>
                        </td>
                        <td><?= htmlspecialchars($team['season'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($team['position'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($team['jersey_number'] ?? 'N/A') ?></td>
                        <td>
                            <?php if (!empty($team['is_current'])): ?>
                                <span style="background: rgba(34, 197, 94, 0.2); color: #22c55e; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">Active</span>
                            <?php else: ?>
                                <span style="background: rgba(100, 116, 139, 0.2); color: #64748b; padding: 2px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #8b949e; margin-top: 12px;">No team assignments yet.</p>
    <?php endif; ?>
</div>

<?php if ($isAdmin || $isCoach): ?>
    <div class="detail-card">
        <h2>Management Actions</h2>
        <div style="display: flex; gap: 10px; margin-top: 12px; flex-wrap: wrap;">
            <a href="?page=manage_athletes&id=<?= $athlete_id ?>" class="btn-primary"><i class="fas fa-user-edit"></i> Edit Profile</a>
            <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="btn-primary"><i class="fas fa-clipboard-check"></i> New Evaluation</a>
            <a href="?page=stats&athlete_id=<?= $athlete_id ?>" class="btn-primary"><i class="fas fa-chart-line"></i> Update Stats</a>
            <a href="?page=messages&user_id=<?= $athlete_id ?>" class="btn-primary"><i class="fas fa-comments"></i> Message</a>
        </div>
    </div>
<?php endif; ?>
