<?php
/**
 * Athlete Detail View
 * Detailed athlete profile with stats, evaluations, and management options
 */

require_once __DIR__ . '/../security.php';

$athlete_id = isset($_GET['id']) ? intval($_GET['id']) : $user_id;

// Get athlete details
$athlete_stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role IN ('athlete', 'coach', 'coach_plus')");
$athlete_stmt->execute([$athlete_id]);
$athlete = $athlete_stmt->fetch();

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

// Get assigned teams
$teams_stmt = $pdo->prepare("
    SELECT at.*, s.season_name 
    FROM athlete_teams at 
    LEFT JOIN seasons s ON at.season_id = s.id
    WHERE at.athlete_id = ?
    ORDER BY at.created_at DESC
");
$teams_stmt->execute([$athlete_id]);
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
        padding: 30px;
        margin-bottom: 30px;
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
        padding: 15px;
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
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 15px;">
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
        <p style="color: #8b949e; margin-top: 15px;">No statistics recorded yet.</p>
    <?php endif; ?>
</div>

<div class="detail-card">
    <h2>Recent Evaluations</h2>
    <?php if (count($evaluations) > 0): ?>
        <table style="width: 100%; margin-top: 15px;">
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
        <p style="color: #8b949e; margin-top: 15px;">No evaluations recorded yet.</p>
    <?php endif; ?>
</div>

<div class="detail-card">
    <h2>Team Assignments</h2>
    <?php if (count($teams) > 0): ?>
        <table style="width: 100%; margin-top: 15px;">
            <thead>
                <tr>
                    <th>Team Name</th>
                    <th>Season</th>
                    <th>Position</th>
                    <th>Jersey #</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($teams as $team): ?>
                    <tr>
                        <td><?= htmlspecialchars($team['team_name']) ?></td>
                        <td><?= htmlspecialchars($team['season_name'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($team['position'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($team['jersey_number'] ?? 'N/A') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p style="color: #8b949e; margin-top: 15px;">No team assignments yet.</p>
    <?php endif; ?>
</div>

<?php if ($isAdmin || $isCoach): ?>
    <div class="detail-card">
        <h2>Management Actions</h2>
        <div style="display: flex; gap: 10px; margin-top: 15px;">
            <a href="?page=manage_athletes&id=<?= $athlete_id ?>" class="btn-primary">Edit Profile</a>
            <a href="?page=evaluations_skills&athlete_id=<?= $athlete_id ?>" class="btn-primary">New Evaluation</a>
            <a href="?page=stats&athlete_id=<?= $athlete_id ?>" class="btn-primary">Update Stats</a>
        </div>
    </div>
<?php endif; ?>
